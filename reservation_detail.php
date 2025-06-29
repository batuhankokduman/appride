<?php
// ÇIKTI ARABELLEĞE ALMAYI BAŞLAT (Headers already sent hatasını önlemek için)
// Bu satır dosyanın en başında olmalı, öncesinde hiçbir şey olmamalı.
ob_start();

// OTURUM KULLANIMI BU KODDA YOKTUR (URL parametreleri ile mesajlaşma yapılıyor)

require_once 'functions/db.php';
require_once 'functions/trigger_engine.php';
require_once 'includes/auth.php'; // Bu dosyada session_start() veya erken çıktı olmadığından emin olun
require_once 'includes/header.php'; // Bu dosyada session_start() veya erken çıktı olmadığından emin olun
require_once 'includes/menu.php'; // Hatanın kaynağı olarak belirtilen dosya, başında çıktı olmamalı

$id = $_GET['id'] ?? null;

// Flash mesajını URL parametrelerinden alıp hazırlayalım
$flash_message = null;
if (isset($_GET['status']) && isset($_GET['msg_code'])) {
    $status_type = $_GET['status'];
    $message_code = $_GET['msg_code'];
    $messages = [
        'success' => [
            '1' => '✅ Ödeme başarıyla eklendi ve toplamlar güncellendi.',
            '2' => '✅ Müşteri bilgileri başarıyla güncellendi.',
            '3' => '✅ Seyahat bilgileri başarıyla güncellendi.',
            '4' => '✅ İlk ödeme yöntemi başarıyla güncellendi.',
            '5' => '✅ Uçuş bilgileri başarıyla güncellendi.',
            '6' => '✅ Ekstra hizmetler başarıyla güncellendi ve fiyatlar yeniden hesaplandı.'
        ],
        'error' => [
            '101' => '❌ Ödeme için lütfen tüm gerekli alanları doğru doldurun.',
            '102' => '❌ Ödeme eklenirken bir veritabanı hatası oluştu. Detaylar loglandı.',
            '103' => '❌ Ödeme eklenirken genel bir hata oluştu. Detaylar loglandı.',
            '201' => '❌ Müşteri bilgileri güncellenirken bir hata oluştu. Detaylar loglandı.',
            '301' => '❌ Geçersiz rezervasyon ID.',
            '302' => '❌ Rezervasyon bulunamadı.',
            '303' => '❌ Rezervasyon yüklenirken bir sorun oluştu.',
            '401' => '❌ Duraklar JSON formatı hatalı.',
            '501' => '❌ Uçuş bilgileri JSON formatı hatalı.',
            '601' => '❌ Ekstra hizmetler JSON formatı hatalı.'
        ]
    ];

    if (isset($messages[$status_type][$message_code])) {
        $flash_message = ['type' => $status_type, 'text' => $messages[$status_type][$message_code]];
    } elseif (isset($_GET['custom_msg'])) {
        $flash_message = ['type' => $status_type, 'text' => urldecode($_GET['custom_msg'])];
    }
}


if (!$id || !is_numeric($id)) {
    header("Location: reservations_list.php?status=error&msg_code=301"); // Uygun bir listeleme sayfasına yönlendirin
    exit;
}

// Rezervasyon bilgilerini en başta çekelim
$stmt_main_res_initial = $pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ?");
$stmt_main_res_initial->execute([$id]);
$res = $stmt_main_res_initial->fetch(PDO::FETCH_ASSOC);

if (!$res) {
    header("Location: reservations_list.php?status=error&msg_code=302");
    exit;
}

$oldDataForTriggers = $res; // TriggerEngine için POST işlemleri öncesi $res'in kopyası

// --- ÖDEME EKLEME İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_payment') {
    $payment_reservation_id = $_POST['reservation_id'] ?? null;
    $payment_amount = $_POST['payment_amount'] ?? null;
    $payment_method = $_POST['payment_method'] ?? null;
    $payment_notes = $_POST['payment_notes'] ?? '';
    $payment_currency = $res['currency'] ?? 'TRY'; // $res o anki (POST öncesi) durumu yansıtıyor

    $redirect_url = $_SERVER['PHP_SELF'] . "?id=" . $id;

    if ($payment_reservation_id && $payment_reservation_id == $id && is_numeric($payment_amount) && (float)$payment_amount > 0 && !empty($payment_method) && !empty($payment_currency)) {
        try {
            $pdo->beginTransaction();

            $stmt_check_payments = $pdo->prepare("SELECT COUNT(*) FROM reservation_payments WHERE reservation_id = ?");
            $stmt_check_payments->execute([$payment_reservation_id]);
            $existing_payment_count = $stmt_check_payments->fetchColumn();

            // Handle initial paid_amount if no payment records exist but reservation has a paid amount
            if ($existing_payment_count == 0 && isset($res['paid_amount']) && (float)$res['paid_amount'] > 0) {
                $initial_paid_amount = (float)$res['paid_amount'];
                $initial_payment_method = $res['payment_method'] ?? 'Online Ödeme';
                $initial_payment_date_str = $res['reservation_created_at'] ?? date('Y-m-d H:i:s');
                $initial_payment_date_obj = new DateTime($initial_payment_date_str);
                $formatted_initial_payment_date = $initial_payment_date_obj->format('Y-m-d H:i:s');

                $stmt_seed_payment = $pdo->prepare(
                    "INSERT INTO reservation_payments (reservation_id, payment_date, payment_method, amount, currency, notes)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt_seed_payment->execute([
                    $payment_reservation_id, $formatted_initial_payment_date, $initial_payment_method,
                    $initial_paid_amount, $payment_currency, 'Sistemden gelen ilk ödeme'
                ]);
            }

            $stmt_add_payment = $pdo->prepare(
                "INSERT INTO reservation_payments (reservation_id, payment_date, payment_method, amount, currency, notes)
                 VALUES (?, NOW(), ?, ?, ?, ?)"
            );
            $stmt_add_payment->execute([
                $payment_reservation_id, $payment_method, (float)$payment_amount,
                $payment_currency, $payment_notes
            ]);

            $stmt_sum_payments = $pdo->prepare("SELECT SUM(amount) as total_paid FROM reservation_payments WHERE reservation_id = ?");
            $stmt_sum_payments->execute([$payment_reservation_id]);
            $total_paid_result = $stmt_sum_payments->fetch(PDO::FETCH_ASSOC);
            $new_total_paid = $total_paid_result['total_paid'] ?? 0.00;
            $new_remaining_amount = (float)($res['gross_price'] ?? 0) - (float)$new_total_paid;

            $stmt_update_reservation = $pdo->prepare("UPDATE reservations SET paid_amount = ?, remaining_amount = ? WHERE reservation_id = ?");
            $stmt_update_reservation->execute([(float)$new_total_paid, (float)$new_remaining_amount, $payment_reservation_id]);

            // TriggerEngine için $newData'yı hazırlayalım
            $newDataForPaymentTrigger = $oldDataForTriggers; // Ödeme öncesi orijinal $res'i al
            $newDataForPaymentTrigger['paid_amount'] = $new_total_paid;
            $newDataForPaymentTrigger['remaining_amount'] = $new_remaining_amount;

            try {
                TriggerEngine::checkAndSend((int)$id, $oldDataForTriggers, $newDataForPaymentTrigger);
            } catch (Throwable $e_trigger) {
                error_log("[TriggerEngine after Payment] Hata (ID $id): " . $e_trigger->getMessage());
            }

            $pdo->commit();
            header("Location: " . $redirect_url . "&status=success&msg_code=1");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Ödeme ekleme hatası (Rez. ID: $payment_reservation_id): " . $e->getMessage());
            header("Location: " . $redirect_url . "&status=error&msg_code=103");
            exit;
        }
    } else {
        header("Location: " . $redirect_url . "&status=error&msg_code=101");
        exit;
    }
}

// --- MÜŞTERİ BİLGİLERİ GÜNCELLEME İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_customer_info') {
    $redirect_url = $_SERVER['PHP_SELF'] . "?id=" . $id;

    $newDataForCustomerTrigger = $oldDataForTriggers;
    $newDataForCustomerTrigger['customer_first_name'] = $_POST['customer_first_name'] ?? $oldDataForTriggers['customer_first_name'];
    $newDataForCustomerTrigger['customer_last_name'] = $_POST['customer_last_name'] ?? $oldDataForTriggers['customer_last_name'];
    $newDataForCustomerTrigger['customer_phone'] = $_POST['customer_phone'] ?? $oldDataForTriggers['customer_phone'];
    $newDataForCustomerTrigger['reservation_status'] = $_POST['reservation_status'] ?? $oldDataForTriggers['reservation_status'];

    $changed = false;
    foreach (['customer_first_name', 'customer_last_name', 'customer_phone', 'reservation_status'] as $key) {
        if (($oldDataForTriggers[$key] ?? '') !== $newDataForCustomerTrigger[$key]) {
            $changed = true;
            break;
        }
    }

    if (!$changed) {
        header("Location: " . $redirect_url);
        exit;
    }

    try {
        $stmt_update_customer = $pdo->prepare("UPDATE reservations SET customer_first_name = ?, customer_last_name = ?, customer_phone = ?, reservation_status = ? WHERE reservation_id = ?");
        $stmt_update_customer->execute([
            $newDataForCustomerTrigger['customer_first_name'], $newDataForCustomerTrigger['customer_last_name'],
            $newDataForCustomerTrigger['customer_phone'], $newDataForCustomerTrigger['reservation_status'], $id
        ]);

        try {
            TriggerEngine::checkAndSend((int)$id, $oldDataForTriggers, $newDataForCustomerTrigger);
        } catch (Throwable $e_trigger) {
            error_log("[TriggerEngine after Customer Update] Hata (ID $id): " . $e_trigger->getMessage());
        }

        header("Location: " . $redirect_url . "&status=success&msg_code=2");
        exit;

    } catch (Exception $e) {
        error_log("[Customer Update] Hata (ID $id): " . $e->getMessage());
        header("Location: " . $redirect_url . "&status=error&msg_code=201");
        exit;
    }
}

// --- SEYAHAT BİLGİLERİ GÜNCELLEME İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_travel_info') {
    $redirect_url = $_SERVER['PHP_SELF'] . "?id=" . $id;

    $updatedFields = [];
    $allowedFields = [
        'rule_name', 'pickup_address', 'pickup_geofence_name', 'dropoff_address',
        'dropoff_geofence_name', 'passengers_adults', 'passengers_children',
        'schedule_selected_date', 'schedule_selected_time', 'maps_url'
    ];

    foreach ($allowedFields as $field) {
        if (isset($_POST[$field])) {
            $updatedFields[$field] = $_POST[$field];
        }
    }

    // Handle stopovers JSON separately (still using textarea for simplicity here, can be enhanced with JS like flight/extras)
    $stopovers_json_input = $_POST['stopovers_json'] ?? '[]';
    $decoded_stopovers = json_decode($stopovers_json_input, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $updatedFields['stopovers'] = $stopovers_json_input;
    } else {
        header("Location: " . $redirect_url . "&status=error&msg_code=401"); // Use specific msg_code
        exit;
    }

    $newDataForTravelTrigger = $oldDataForTriggers;
    $changed = false;

    foreach ($updatedFields as $key => $value) {
        // Ensure comparison handles potential type differences for empty strings vs nulls
        $oldValue = $oldDataForTriggers[$key] ?? '';
        $newValue = $value;

        if ($key === 'passengers_adults' || $key === 'passengers_children') {
             // Convert to int for comparison
            $oldValue = (int)$oldValue;
            $newValue = (int)$newValue;
        }

        if ($oldValue !== $newValue) {
            $newDataForTravelTrigger[$key] = $value;
            $changed = true;
        }
    }

    if (!$changed) {
        header("Location: " . $redirect_url);
        exit;
    }

    try {
        $setClauses = [];
        $executeParams = [];
        foreach ($updatedFields as $key => $value) {
            $setClauses[] = "$key = ?";
            $executeParams[] = $value;
        }
        $executeParams[] = $id;

        $stmt_update_travel = $pdo->prepare("UPDATE reservations SET " . implode(', ', $setClauses) . " WHERE reservation_id = ?");
        $stmt_update_travel->execute($executeParams);

        try {
            TriggerEngine::checkAndSend((int)$id, $oldDataForTriggers, $newDataForTravelTrigger);
        } catch (Throwable $e_trigger) {
            error_log("[TriggerEngine after Travel Update] Hata (ID $id): " . $e_trigger->getMessage());
        }

        header("Location: " . $redirect_url . "&status=success&msg_code=3"); // Use specific msg_code
        exit;

    } catch (Exception $e) {
        error_log("[Travel Update] Hata (ID $id): " . $e->getMessage());
        header("Location: " . $redirect_url . "&status=error&custom_msg=" . urlencode("❌ Seyahat bilgileri güncellenirken bir hata oluştu: " . $e->getMessage())); // More detailed error
        exit;
    }
}

// --- İLK ÖDEME YÖNTEMİ GÜNCELLEME İŞLEMİ (REZERVASYON ÜZERİNDEN) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_initial_payment_method') {
    $new_initial_payment_method = $_POST['initial_payment_method'] ?? null;
    $redirect_url = $_SERVER['PHP_SELF'] . "?id=" . $id;

    if ($new_initial_payment_method !== null && $new_initial_payment_method !== ($oldDataForTriggers['payment_method'] ?? '')) {
        $newDataForInitialPaymentTrigger = $oldDataForTriggers;
        $newDataForInitialPaymentTrigger['payment_method'] = $new_initial_payment_method;

        try {
            $stmt_update_initial_payment_method = $pdo->prepare("UPDATE reservations SET payment_method = ? WHERE reservation_id = ?");
            $stmt_update_initial_payment_method->execute([$new_initial_payment_method, $id]);

            try {
                TriggerEngine::checkAndSend((int)$id, $oldDataForTriggers, $newDataForInitialPaymentTrigger);
            } catch (Throwable $e_trigger) {
                error_log("[TriggerEngine after Initial Payment Method Update] Hata (ID $id): " . $e_trigger->getMessage());
            }

            header("Location: " . $redirect_url . "&status=success&msg_code=4"); // Use specific msg_code
            exit;

        } catch (Exception $e) {
            error_log("[Initial Payment Method Update] Hata (ID $id): " . $e->getMessage());
            header("Location: " . $redirect_url . "&status=error&custom_msg=" . urlencode("❌ İlk ödeme yöntemi güncellenirken bir hata oluştu: " . $e->getMessage()));
            exit;
        }
    } else {
        header("Location: " . $redirect_url); // No change, redirect without message
        exit;
    }
}

// --- UÇUŞ BİLGİLERİ GÜNCELLEME İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_flight_info') {
    $redirect_url = $_SERVER['PHP_SELF'] . "?id=" . $id;

    // flight_info_data array'ini alıp JSON'a dönüştürüyoruz
    $flight_info_data = [];
    // Ensure we are getting the data from the hidden JSON field generated by JavaScript
    $flight_info_json_input = $_POST['flight_info_json_hidden'] ?? '[]';

    $decoded_flight_info = json_decode($flight_info_json_input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        header("Location: " . $redirect_url . "&status=error&msg_code=501"); // Use specific msg_code
        exit;
    }
    // Now, $decoded_flight_info is the array we want to save, so re-encode it for consistency
    $flight_info_json_output = json_encode($decoded_flight_info, JSON_UNESCAPED_UNICODE);

    $metadata_comment = $_POST['metadata_comment'] ?? '';

    $newDataForFlightTrigger = $oldDataForTriggers;
    $changed = false;

    // Compare with the potentially re-encoded version for consistency
    if (($oldDataForTriggers['flight_info'] ?? '[]') !== $flight_info_json_output) {
        $newDataForFlightTrigger['flight_info'] = $flight_info_json_output;
        $changed = true;
    }
    if (($oldDataForTriggers['metadata_comment'] ?? '') !== $metadata_comment) {
        $newDataForFlightTrigger['metadata_comment'] = $metadata_comment;
        $changed = true;
    }

    if (!$changed) {
        header("Location: " . $redirect_url);
        exit;
    }

    try {
        $stmt_update_flight = $pdo->prepare("UPDATE reservations SET flight_info = ?, metadata_comment = ? WHERE reservation_id = ?");
        $stmt_update_flight->execute([$flight_info_json_output, $metadata_comment, $id]);

        try {
            TriggerEngine::checkAndSend((int)$id, $oldDataForTriggers, $newDataForFlightTrigger);
        } catch (Throwable $e_trigger) {
            error_log("[TriggerEngine after Flight Info Update] Hata (ID $id): " . $e_trigger->getMessage());
        }

        header("Location: " . $redirect_url . "&status=success&msg_code=5"); // Use specific msg_code
        exit;

    } catch (Exception $e) {
        error_log("[Flight Info Update] Hata (ID $id): " . $e->getMessage());
        header("Location: " . $redirect_url . "&status=error&custom_msg=" . urlencode("❌ Uçuş bilgileri güncellenirken bir hata oluştu: " . $e->getMessage()));
        exit;
    }
}

// --- EKSTRA HİZMETLER GÜNCELLEME İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_extras') {
    $redirect_url = $_SERVER['PHP_SELF'] . "?id=" . $id;

    // extras_data array'ini alıp JSON'a dönüştürüyoruz
    $extras_json_input = $_POST['extras_json_hidden'] ?? '[]';

    $decoded_extras = json_decode($extras_json_input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        header("Location: " . $redirect_url . "&status=error&msg_code=601"); // Use specific msg_code
        exit;
    }
    // Now, $decoded_extras is the array we want to save, so re-encode it for consistency
    $extras_json_output = json_encode($decoded_extras, JSON_UNESCAPED_UNICODE);


    $newDataForExtrasTrigger = $oldDataForTriggers;
    $changed = false;

    if (($oldDataForTriggers['extras'] ?? '[]') !== $extras_json_output) {
        $newDataForExtrasTrigger['extras'] = $extras_json_output;
        $changed = true;
    }

    if (!$changed) {
        header("Location: " . $redirect_url);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt_update_extras = $pdo->prepare("UPDATE reservations SET extras = ? WHERE reservation_id = ?");
        $stmt_update_extras->execute([$extras_json_output, $id]);

        // Recalculate gross_price if extras affect it
        $stmt_get_reservation_details = $pdo->prepare("SELECT base_price, extras, paid_amount FROM reservations WHERE reservation_id = ?");
        $stmt_get_reservation_details->execute([$id]);
        $current_res_for_recalc = $stmt_get_reservation_details->fetch(PDO::FETCH_ASSOC);

        $base_price = (float)($current_res_for_recalc['base_price'] ?? 0);
        $recalculated_extras_price = 0;
        // Use the just updated extras for recalculation
        $recalculated_extras = json_decode($extras_json_output, true);
        foreach ($recalculated_extras as $extra) {
            $recalculated_extras_price += (float)($extra['price'] ?? 0);
        }
        $new_gross_price = $base_price + $recalculated_extras_price;
        $new_remaining_amount_after_recalc = $new_gross_price - (float)($current_res_for_recalc['paid_amount'] ?? 0);

        $stmt_update_gross_price = $pdo->prepare("UPDATE reservations SET gross_price = ?, remaining_amount = ? WHERE reservation_id = ?");
        $stmt_update_gross_price->execute([(float)$new_gross_price, (float)$new_remaining_amount_after_recalc, $id]);

        // Update trigger data with new prices
        $newDataForExtrasTrigger['gross_price'] = $new_gross_price;
        $newDataForExtrasTrigger['remaining_amount'] = $new_remaining_amount_after_recalc;

        try {
            TriggerEngine::checkAndSend((int)$id, $oldDataForTriggers, $newDataForExtrasTrigger);
        } catch (Throwable $e_trigger) {
            error_log("[TriggerEngine after Extras Update] Hata (ID $id): " . $e_trigger->getMessage());
        }

        $pdo->commit();
        header("Location: " . $redirect_url . "&status=success&msg_code=6"); // Use specific msg_code
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("[Extras Update] Hata (ID $id): " . $e->getMessage());
        header("Location: " . $redirect_url . "&status=error&custom_msg=" . urlencode("❌ Ekstra hizmetler güncellenirken bir hata oluştu: " . $e->getMessage()));
        exit;
    }
}

// Sayfa her GET isteğiyle yüklendiğinde (POST sonrası redirect dahil) $res'i tazeleyelim
// Bu, $oldDataForTriggers'ın bir sonraki POST için doğru (güncel) olmasını da sağlar.
$stmt_reload_res_for_display = $pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ?");
$stmt_reload_res_for_display->execute([$id]);
$res = $stmt_reload_res_for_display->fetch(PDO::FETCH_ASSOC);

if (!$res) {
    header("Location: reservations_list.php?status=error&msg_code=303");
    exit;
}
// $oldDataForTriggers'ı da bu yeni $res ile güncelleyelim ki bir sonraki POST'ta doğru "eski veri" olsun
$oldDataForTriggers = $res;


// Diğer verileri ayrıştır
$flight_info = json_decode($res['flight_info'] ?? '[]', true);
$extras = json_decode($res['extras'] ?? '[]', true);
$stopovers = json_decode($res['stopovers'] ?? '[]', true);
$maps_url = $res['maps_url'] ?? null;
?>

<style>
/* Önceki mesajdaki stiller (container, flash-message-container, flash-success, flash-error, card, vb.) */
.container { max-width: 1200px; margin: 0 auto; padding: 0 20px 30px 20px; display: grid; grid-template-columns: 1fr; gap: 20px; position: relative; }
@media (min-width: 992px) { /* Daha geniş ekranlarda iki sütun */
    .container { grid-template-columns: 1fr 1fr; }
}
.flash-message-container { grid-column: 1 / -1; margin-bottom: 20px; }
.flash-success { padding: 15px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 8px; font-size: 15px; }
.flash-error { padding: 15px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 8px; font-size: 15px; }
.left-col, .right-col { display: flex; flex-direction: column; gap: 20px; }
.card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.card h3 { margin-top: 0; margin-bottom: 15px; font-size: 1.2rem; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; }
.card h4 { margin-top: 20px; margin-bottom: 10px; font-size: 1.1rem; color: #111827; }
.card p { margin: 8px 0; font-size: 15px; color: #374151; word-break: break-word; }
.card strong { color: #111827; }
.card input, .card select, .card textarea { width: 100%; padding: 10px; font-size: 14px; border-radius: 8px; border: 1px solid #d1d5db; background: #f9fafb; margin-top: 5px; margin-bottom: 12px; box-sizing: border-box; }
.btn-primary { background-color: #2563eb; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; transition: background-color 0.3s ease; display: inline-block; }
.btn-primary:hover { background-color: #1d4ed8; }
.page-header { grid-column: 1 / -1; font-size: 1.5rem; margin-bottom: 0; }
.payment-list { list-style-type: none; padding-left: 0; }
.payment-list li { border-bottom: 1px solid #f0f0f0; padding: 10px 0; margin-bottom: 5px; }
.payment-list li:last-child { border-bottom: none; margin-bottom: 0; }
.payment-list .payment-meta { font-size: 0.9em; color: #555; }
.payment-list .payment-notes { font-size: 0.9em; color: #777; margin-top: 4px; background-color: #f9f9f9; padding: 5px; border-radius: 4px; }
.stopover-step { margin: 8px 0; padding-left: 20px; position: relative; }
.stopover-step::before { content: '↳'; position: absolute; left: 0; color: #888; }
.stopover-step .stop-label { font-weight: bold; display: inline-block; margin-right: 6px; }
.stopover-step small { color: #6b7280; margin-left: 4px; }

/* Styles for dynamic fields */
.dynamic-input-group {
    display: flex;
    flex-wrap: wrap; /* Allow wrapping on smaller screens */
    gap: 10px;
    margin-bottom: 10px;
    align-items: flex-end;
}
.dynamic-input-group label {
    flex-basis: 100%; /* Labels take full width on smaller screens */
    margin-bottom: -5px; /* Adjust spacing */
    font-size: 0.9em;
    color: #4a5568;
}
.dynamic-input-group input[type="text"],
.dynamic-input-group input[type="number"] {
    flex: 1; /* Allow inputs to grow */
    min-width: 120px; /* Minimum width before wrapping */
    margin-bottom: 0; /* Override default margin */
}
.dynamic-input-group .remove-btn {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9em;
    height: 38px; /* Match input height */
    line-height: 22px;
    box-sizing: border-box;
    flex-shrink: 0; /* Prevent button from shrinking */
}
.dynamic-input-group .remove-btn:hover {
    background-color: #c82333;
}
.add-new-btn {
    background-color: #28a745;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    transition: background-color 0.3s ease;
    display: inline-block;
    margin-top: 15px;
}
.add-new-btn:hover {
    background-color: #218838;
}
</style>

<div class="content">
    <div class="container">
        <?php if ($flash_message): ?>
        <div class="flash-message-container">
            <div class="flash-<?= htmlspecialchars($flash_message['type']) ?>">
                <?= htmlspecialchars($flash_message['text']) ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="page-header">
            📄 Rezervasyon Detayı (#<?= htmlspecialchars($res['reservation_id'] ?? 'N/A') ?>)
        </div>

        <div class="left-col">
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . "?id=" . $id) ?>">
                <input type="hidden" name="action" value="update_customer_info">
                <div class="card">
                    <h3>👤 Müşteri Bilgileri</h3>
                    <label for="customer_first_name">Ad</label>
                    <input type="text" id="customer_first_name" name="customer_first_name" value="<?= htmlspecialchars($res['customer_first_name'] ?? '') ?>">
                    <label for="customer_last_name">Soyad</label>
                    <input type="text" id="customer_last_name" name="customer_last_name" value="<?= htmlspecialchars($res['customer_last_name'] ?? '') ?>">
                    <label for="customer_phone">Telefon</label>
                    <input type="text" id="customer_phone" name="customer_phone" value="<?= htmlspecialchars($res['customer_phone'] ?? '') ?>">
                    <label for="reservation_status">Durum</label>
                    <select id="reservation_status" name="reservation_status">
                        <?php
                        $statuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'Partially Paid', 'Paid']; // Gerekirse ek durumlar
                        foreach ($statuses as $status_item):
                            $selected = (isset($res['reservation_status']) && $res['reservation_status'] === $status_item) ? 'selected' : '';
                            echo "<option value='{$status_item}' {$selected}>{$status_item}</option>";
                        endforeach;
                        ?>
                    </select>
                    <button type="submit" class="btn-primary">💾 Müşteri Bilgilerini Kaydet</button>
                </div>
            </form>

            <div class="card">
                <h3>🚌 Seyahat Bilgileri</h3>
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . "?id=" . $id) ?>">
                    <input type="hidden" name="action" value="update_travel_info">
                    <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['reservation_id'] ?? '') ?>">

                    <label for="rule_name">Tur:</label>
                    <input type="text" id="rule_name" name="rule_name" value="<?= htmlspecialchars($res['rule_name'] ?? '') ?>">

                    <label for="pickup_address">Pickup Adresi:</label>
                    <textarea id="pickup_address" name="pickup_address" rows="2"><?= htmlspecialchars($res['pickup_address'] ?? '') ?></textarea>

                    <label for="pickup_geofence_name">Pickup Bölge:</label>
                    <input type="text" id="pickup_geofence_name" name="pickup_geofence_name" value="<?= htmlspecialchars($res['pickup_geofence_name'] ?? '') ?>">

                    <h4>Duraklar (JSON olarak düzenleyin):</h4>
                    <p><small>Gelişmiş düzenleme için, durak bilgilerini JSON formatında doğrudan düzenleyebilirsiniz. Her durak için `{"address": "...", "duration": "..."}` şeklinde olmalıdır.</small></p>
                    <textarea name="stopovers_json" rows="5"><?= htmlspecialchars($res['stopovers'] ?? '[]') ?></textarea>

                    <label for="dropoff_address">Dropoff Adresi:</label>
                    <textarea id="dropoff_address" name="dropoff_address" rows="2"><?= htmlspecialchars($res['dropoff_address'] ?? '') ?></textarea>

                    <label for="dropoff_geofence_name">Dropoff Bölge:</label>
                    <input type="text" id="dropoff_geofence_name" name="dropoff_geofence_name" value="<?= htmlspecialchars($res['dropoff_geofence_name'] ?? '') ?>">

                    <label for="passengers_adults">Yetişkin Yolcu:</label>
                    <input type="number" id="passengers_adults" name="passengers_adults" value="<?= htmlspecialchars($res['passengers_adults'] ?? 0) ?>" min="0">

                    <label for="passengers_children">Çocuk Yolcu:</label>
                    <input type="number" id="passengers_children" name="passengers_children" value="<?= htmlspecialchars($res['passengers_children'] ?? 0) ?>" min="0">

                    <label for="schedule_selected_date">Tarih:</label>
                    <input type="date" id="schedule_selected_date" name="schedule_selected_date" value="<?= htmlspecialchars($res['schedule_selected_date'] ?? '') ?>">

                    <label for="schedule_selected_time">Saat:</label>
                    <input type="time" id="schedule_selected_time" name="schedule_selected_time" value="<?= htmlspecialchars($res['schedule_selected_time'] ?? '') ?>">

                    <label for="maps_url">Rota (Google Haritalar URL):</label>
                    <input type="url" id="maps_url" name="maps_url" value="<?= htmlspecialchars($res['maps_url'] ?? '') ?>">

                    <button type="submit" class="btn-primary">💾 Seyahat Bilgilerini Kaydet</button>
                </form>
            </div>
        </div>

        <div class="right-col">
            <div class="card">
                <h3>💳 Ödeme Bilgileri</h3>
                <p><strong>Toplam Fiyat:</strong> <?= htmlspecialchars(number_format((float)($res['gross_price'] ?? 0), 2, ',', '.')) ?> <?= htmlspecialchars($res['currency'] ?? '') ?></p>
                <p><strong>Ödenen Tutar:</strong> <strong style="color: green;"><?= htmlspecialchars(number_format((float)($res['paid_amount'] ?? 0), 2, ',', '.')) ?> <?= htmlspecialchars($res['currency'] ?? '') ?></strong></p>
                <p><strong>Kalan Tutar:</strong> <strong style="color: red;"><?= htmlspecialchars(number_format((float)($res['remaining_amount'] ?? 0), 2, ',', '.')) ?> <?= htmlspecialchars($res['currency'] ?? '') ?></strong></p>

                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . "?id=" . $id) ?>">
                    <input type="hidden" name="action" value="update_initial_payment_method">
                    <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['reservation_id'] ?? '') ?>">
                    <label for="initial_payment_method">İlk Ödeme Yöntemi (Rez.):</label>
                    <input type="text" id="initial_payment_method" name="initial_payment_method" value="<?= htmlspecialchars($res['payment_method'] ?? '') ?>">
                    <button type="submit" class="btn-primary" style="margin-top: 10px;">💾 İlk Ödeme Yöntemini Kaydet</button>
                </form>

                <?php if (isset($res['gratuity_amount']) && (float)$res['gratuity_amount'] > 0): ?>
                <p><strong>Komisyon:</strong> <?= htmlspecialchars(number_format((float)$res['gratuity_amount'], 2, ',', '.')) ?> <?= htmlspecialchars($res['currency'] ?? '') ?></p>
                <?php endif; ?>
                <hr style="margin: 20px 0;">
                <h4>📝 Alınan Ödemeler Geçmişi</h4>
                <?php
                $stmt_fetch_payments = $pdo->prepare("SELECT * FROM reservation_payments WHERE reservation_id = ? ORDER BY payment_date DESC");
                $stmt_fetch_payments->execute([$id]);
                $payments_history = $stmt_fetch_payments->fetchAll(PDO::FETCH_ASSOC);
                if (count($payments_history) > 0):
                ?>
                    <ul class="payment-list">
                        <?php foreach ($payments_history as $payment_item): ?>
                            <li>
                                <div class="payment-meta">
                                    <strong>Tarih:</strong> <?= htmlspecialchars(date('d.m.Y H:i', strtotime($payment_item['payment_date']))) ?> |
                                    <strong>Tutar:</strong> <?= htmlspecialchars(number_format((float)$payment_item['amount'], 2, ',', '.')) ?> <?= htmlspecialchars($payment_item['currency']) ?> |
                                    <strong>Yöntem:</strong> <?= htmlspecialchars($payment_item['payment_method']) ?>
                                </div>
                                <?php if (!empty($payment_item['notes'])): ?>
                                    <div class="payment-notes"><strong>Not:</strong> <?= nl2br(htmlspecialchars($payment_item['notes'])) ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Bu rezervasyon için henüz kayıtlı bir ödeme hareketi bulunmamaktadır.</p>
                <?php endif; ?>
                <hr style="margin: 20px 0;">
                <h4>➕ Yeni Ödeme Ekle</h4>
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . "?id=" . $id) ?>">
                    <input type="hidden" name="action" value="add_payment">
                    <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['reservation_id'] ?? '') ?>">
                    <label for="payment_amount_form">Ödenecek Tutar (<?= htmlspecialchars($res['currency'] ?? '') ?>):</label>
                    <input type="number" id="payment_amount_form" name="payment_amount" step="0.01" min="0.01" required>
                    <label for="payment_method_form">Ödeme Yöntemi:</label>
                    <select id="payment_method_form" name="payment_method" required>
                        <option value="">Seçiniz...</option>
                        <option value="Stripe">Stripe</option> <option value="IBAN">IBAN</option>
                        <option value="Nakit">Nakit</option> <option value="Diğer">Diğer</option>
                    </select>
                    <label for="payment_notes_form">Not (İsteğe Bağlı):</label>
                    <textarea id="payment_notes_form" name="payment_notes" rows="3"></textarea>
                    <button type="submit" class="btn-primary">💰 Ödemeyi Kaydet</button>
                </form>
            </div>

            <div class="card">
                <h3>✈️ Uçuş Bilgileri</h3>
                <form id="flightInfoForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . "?id=" . $id) ?>">
                    <input type="hidden" name="action" value="update_flight_info">
                    <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['reservation_id'] ?? '') ?>">
                    <input type="hidden" name="flight_info_json_hidden" id="flight_info_json_hidden">

                    <div id="flight_info_container">
                        <?php
                        // PHP ile mevcut uçuş bilgilerini döngüye alarak dinamik inputları oluştur
                        if (!empty($flight_info)) {
                            foreach ($flight_info as $index => $item) {
                                echo '<div class="dynamic-input-group">';
                                echo '<label>Etiket:</label>';
                                echo '<input type="text" name="flight_labels[]" value="' . htmlspecialchars($item['label'] ?? '') . '" placeholder="Etiket (örn: Uçuş No)">';
                                echo '<label>Değer:</label>';
                                echo '<input type="text" name="flight_values[]" value="' . htmlspecialchars($item['value'] ?? '') . '" placeholder="Değer (örn: TK1234)">';
                                echo '<button type="button" class="remove-btn">Kaldır</button>';
                                echo '</div>';
                            }
                        } else {
                            // Eğer hiç uçuş bilgisi yoksa, varsayılan boş bir alan göster
                            echo '<div class="dynamic-input-group">';
                            echo '<label>Etiket:</label>';
                            echo '<input type="text" name="flight_labels[]" value="" placeholder="Etiket (örn: Uçuş No)">';
                            echo '<label>Değer:</label>';
                            echo '<input type="text" name="flight_values[]" value="" placeholder="Değer (örn: TK1234)">';
                            echo '<button type="button" class="remove-btn">Kaldır</button>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <button type="button" id="addFlightInfo" class="add-new-btn">➕ Yeni Uçuş Bilgisi Ekle</button>

                    <label for="metadata_comment">Müşteri Notu:</label>
                    <textarea id="metadata_comment" name="metadata_comment" rows="3"><?= htmlspecialchars($res['metadata_comment'] ?? '') ?></textarea>

                    <button type="submit" class="btn-primary">💾 Uçuş Bilgilerini Kaydet</button>
                </form>
            </div>

            <div class="card">
                <h3>🧳 Ekstra Hizmetler</h3>
                <form id="extrasForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . "?id=" . $id) ?>">
                    <input type="hidden" name="action" value="update_extras">
                    <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['reservation_id'] ?? '') ?>">
                    <input type="hidden" name="extras_json_hidden" id="extras_json_hidden">

                    <div id="extras_container">
                        <?php
                        // PHP ile mevcut ekstra hizmetleri döngüye alarak dinamik inputları oluştur
                        if (!empty($extras)) {
                            foreach ($extras as $index => $extra) {
                                echo '<div class="dynamic-input-group">';
                                echo '<label>Hizmet Adı:</label>';
                                echo '<input type="text" name="extra_labels[]" value="' . htmlspecialchars($extra['label'] ?? '') . '" placeholder="Hizmet Adı">';
                                echo '<label>Fiyat (' . htmlspecialchars($res['currency'] ?? '') . '):</label>';
                                echo '<input type="number" step="0.01" name="extra_prices[]" value="' . htmlspecialchars($extra['price'] ?? '') . '" placeholder="Fiyat">';
                                echo '<button type="button" class="remove-btn">Kaldır</button>';
                                echo '</div>';
                            }
                        } else {
                            // Eğer hiç ekstra hizmet yoksa, varsayılan boş bir alan göster
                            echo '<div class="dynamic-input-group">';
                            echo '<label>Hizmet Adı:</label>';
                            echo '<input type="text" name="extra_labels[]" value="" placeholder="Hizmet Adı">';
                            echo '<label>Fiyat (' . htmlspecialchars($res['currency'] ?? '') . '):</label>';
                            echo '<input type="number" step="0.01" name="extra_prices[]" value="" placeholder="Fiyat">';
                            echo '<button type="button" class="remove-btn">Kaldır</button>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <button type="button" id="addExtraService" class="add-new-btn">➕ Yeni Ekstra Hizmet Ekle</button>

                    <button type="submit" class="btn-primary">💾 Ekstra Hizmetleri Kaydet</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Uçuş Bilgileri Dinamik Alanları ---
    const flightInfoContainer = document.getElementById('flight_info_container');
    const addFlightInfoBtn = document.getElementById('addFlightInfo');
    const flightInfoForm = document.getElementById('flightInfoForm');
    const flightInfoJsonHidden = document.getElementById('flight_info_json_hidden');

    function createFlightInfoField(label = '', value = '') {
        const div = document.createElement('div');
        div.classList.add('dynamic-input-group');
        div.innerHTML = `
            <label>Etiket:</label>
            <input type="text" name="flight_labels[]" value="${escapeHtml(label)}" placeholder="Etiket (örn: Uçuş No)">
            <label>Değer:</label>
            <input type="text" name="flight_values[]" value="${escapeHtml(value)}" placeholder="Değer (örn: TK1234)">
            <button type="button" class="remove-btn">Kaldır</button>
        `;
        return div;
    }

    addFlightInfoBtn.addEventListener('click', function() {
        flightInfoContainer.appendChild(createFlightInfoField());
    });

    flightInfoContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-btn')) {
            e.target.closest('.dynamic-input-group').remove();
            // If all fields are removed, add an empty one back for user convenience
            if (flightInfoContainer.children.length === 0) {
                 flightInfoContainer.appendChild(createFlightInfoField());
            }
        }
    });

    flightInfoForm.addEventListener('submit', function() {
        const flightInfoData = [];
        flightInfoContainer.querySelectorAll('.dynamic-input-group').forEach(group => {
            const labelInput = group.querySelector('input[name="flight_labels[]"]');
            const valueInput = group.querySelector('input[name="flight_values[]"]');

            // Sadece boş olmayan alanları JSON'a dahil et
            if (labelInput.value.trim() !== '' || valueInput.value.trim() !== '') {
                flightInfoData.push({
                    label: labelInput.value.trim(),
                    value: valueInput.value.trim()
                });
            }
        });
        flightInfoJsonHidden.value = JSON.stringify(flightInfoData);
    });

    // --- Ekstra Hizmetler Dinamik Alanları ---
    const extrasContainer = document.getElementById('extras_container');
    const addExtraServiceBtn = document.getElementById('addExtraService');
    const extrasForm = document.getElementById('extrasForm');
    const extrasJsonHidden = document.getElementById('extras_json_hidden');
    const currencySymbol = '<?= htmlspecialchars($res['currency'] ?? '') ?>'; // PHP'den para birimi al

    function createExtraServiceField(label = '', price = '') {
        const div = document.createElement('div');
        div.classList.add('dynamic-input-group');
        div.innerHTML = `
            <label>Hizmet Adı:</label>
            <input type="text" name="extra_labels[]" value="${escapeHtml(label)}" placeholder="Hizmet Adı">
            <label>Fiyat (${currencySymbol}):</label>
            <input type="number" step="0.01" name="extra_prices[]" value="${escapeHtml(price)}" placeholder="Fiyat">
            <button type="button" class="remove-btn">Kaldır</button>
        `;
        return div;
    }

    addExtraServiceBtn.addEventListener('click', function() {
        extrasContainer.appendChild(createExtraServiceField());
    });

    extrasContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-btn')) {
            e.target.closest('.dynamic-input-group').remove();
            // If all fields are removed, add an empty one back for user convenience
            if (extrasContainer.children.length === 0) {
                extrasContainer.appendChild(createExtraServiceField());
            }
        }
    });

    extrasForm.addEventListener('submit', function() {
        const extrasData = [];
        extrasContainer.querySelectorAll('.dynamic-input-group').forEach(group => {
            const labelInput = group.querySelector('input[name="extra_labels[]"]');
            const priceInput = group.querySelector('input[name="extra_prices[]"]');

            // Sadece boş olmayan veya geçerli sayısal fiyat içeren alanları JSON'a dahil et
            if (labelInput.value.trim() !== '' || (priceInput.value.trim() !== '' && !isNaN(parseFloat(priceInput.value)))) {
                extrasData.push({
                    label: labelInput.value.trim(),
                    price: parseFloat(priceInput.value) || 0 // Sayısal bir değer olduğundan emin ol
                });
            }
        });
        extrasJsonHidden.value = JSON.stringify(extrasData);
    });

    // Helper function to escape HTML for input values
    function escapeHtml(text) {
      const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>

<?php
require_once 'includes/footer.php';
ob_end_flush(); // Arabellekteki çıktıyı gönder ve arabelleği kapat
?>