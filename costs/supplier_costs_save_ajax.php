<?php
require_once '../functions/db.php';
require_once '../functions/log.php'; // log_error fonksiyonunuzu içerir
header('Content-Type: application/json');

// PHP hata görüntülemeyi açın (Sadece geliştirme ortamı için!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Gelen POST verilerini loglayın
log_error("🚀 RECEIVED POST DATA:\n" . print_r($_POST, true), 'supplier_costs_debug.log');

// Test amaçlı veritabanı bağlantısını ve tablo sütunlarını kontrol edin
try {
    // db.php zaten yukarıda require edildi, tekrar require etmeye gerek yok.
    // $pdo global veya uygun scope'ta erişilebilir olmalı.
    $checkStmt = $pdo->query("DESCRIBE supplier_cost_periods");
    $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    error_log("DATABASE COLUMNS: " . implode(', ', $columns), 3, __DIR__ . '/supplier_costs_debug.log');
    if (!in_array('updated_at', $columns)) {
        error_log("ERROR: 'updated_at' column NOT FOUND by PDO in current database connection!", 3, __DIR__ . '/supplier_costs_debug.log');
    } else {
        error_log("SUCCESS: 'updated_at' column FOUND by PDO in current database connection!", 3, __DIR__ . '/supplier_costs_debug.log');
    }
} catch (PDOException $e) {
    error_log("PDO CONNECTION ERROR DURING COLUMN CHECK: " . $e->getMessage(), 3, __DIR__ . '/supplier_costs_debug.log');
}

// Güvenli veri alma fonksiyonu
function safeInput($key, $default = null) {
    return (isset($_POST[$key]) && $_POST[$key] !== '') ? $_POST[$key] : $default;
}

// JSON geçerliliği kontrolü
function json_validate($string) {
    json_decode($string);
    return (json_last_error() === JSON_ERROR_NONE);
}

try {
    // Zorunlu alanlar
    $supplier_id = safeInput('supplier_id');
    $price_rule_id = safeInput('price_rule_id');
    $valid_from = safeInput('valid_from');
    $currency = safeInput('currency', 'TRY');

    // NEW: Get the ID for existing records
    $record_id = safeInput('id'); // This will be null for new records, which is fine

    if (!$supplier_id || !$price_rule_id || !$valid_from) {
        throw new Exception('Gerekli alanlar eksik: Tedarikçi ID, Fiyat Kuralı ID veya Geçerlilik Tarihi.');
    }

    // Opsiyonel alanlar (3 type'a göre)
    $cost_per_adult = safeInput('cost_per_adult');
    $cost_per_child = safeInput('cost_per_child');
    $cost_per_vehicle = safeInput('cost_per_vehicle');
    $fixed_base_price = safeInput('fixed_base_price');
    $price_per_minute = safeInput('price_per_minute');
    $price_per_extra_minute = safeInput('price_per_extra_minute');

    $price_per_km_range = safeInput('price_per_km_range', '{}');
    if (!json_validate($price_per_km_range)) {
        $price_per_km_range = '{}';
        log_error("Invalid JSON for price_per_km_range: " . ($_POST['price_per_km_range'] ?? 'N/A'), 'supplier_costs_errors.log');
    }

    $extras_json = safeInput('extras_json', '{}');
    if (!json_validate($extras_json)) {
        $extras_json = '{}';
        log_error("Invalid JSON for extras_json: " . ($_POST['extras_json'] ?? 'N/A'), 'supplier_costs_errors.log');
    }

    $pdo->beginTransaction();

    // Determine if it's an update to an *existing specific record* or a new time-period entry.
    // If 'record_id' is provided and valid, it's an update.
    // Otherwise, check for existing time-based conflicts.
    $is_direct_update = false;
    if ($record_id) {
        // Check if the provided ID actually exists and belongs to the given rule/supplier
        $checkIdStmt = $pdo->prepare("SELECT id FROM supplier_cost_periods WHERE id = :id AND supplier_id = :supplier_id AND price_rule_id = :price_rule_id");
        $checkIdStmt->execute([':id' => $record_id, ':supplier_id' => $supplier_id, ':price_rule_id' => $price_rule_id]);
        if ($checkIdStmt->fetch(PDO::FETCH_ASSOC)) {
            $is_direct_update = true; // We are updating an existing row by its ID
        }
    }
    
    // is_direct_update durumunu loglayın
    log_error("DEBUG: is_direct_update = " . ($is_direct_update ? 'TRUE' : 'FALSE'), 'supplier_costs_debug.log');


    if ($is_direct_update) {
        // ✅ Direct Update of an existing record by its ID
        $updateParams = [
            ':cost_per_adult' => $cost_per_adult,
            ':cost_per_child' => $cost_per_child,
            ':cost_per_vehicle' => $cost_per_vehicle,
            ':fixed_base_price' => $fixed_base_price,
            ':price_per_km_range' => $price_per_km_range,
            ':price_per_minute' => $price_per_minute,
            ':price_per_extra_minute' => $price_per_extra_minute,
            ':extras_json' => $extras_json,
            ':currency' => $currency,
            ':id' => $record_id // Use the ID passed from the form
        ];

        log_error("🛠 DIRECT UPDATE PARAMS:\n" . print_r($updateParams, true), 'supplier_costs_errors.log');

        $updateStmt = $pdo->prepare("
            UPDATE supplier_cost_periods
            SET 
                cost_per_adult = :cost_per_adult,
                cost_per_child = :cost_per_child,
                cost_per_vehicle = :cost_per_vehicle,
                fixed_base_price = :fixed_base_price,
                price_per_km_range = :price_per_km_range,
                price_per_minute = :price_per_minute,
                price_per_extra_minute = :price_per_extra_minute,
                extras_json = :extras_json,
                currency = :currency,
                updated_at = NOW()
            WHERE id = :id
        ");
        $updateResult = $updateStmt->execute($updateParams);

        if (!$updateResult) {
            $err = $updateStmt->errorInfo();
            log_error("❌ DIRECT UPDATE ERROR INFO:\n" . print_r($err, true), 'supplier_costs_errors.log');
            throw new Exception("Kayıt güncellenemedi: " . ($err[2] ?? 'Bilinmeyen hata'));
        }

    } else {
        // This is either a brand new cost period or an update that creates a new period (i.e., new valid_from date).

        // Check for an existing 'current' record (valid_to IS NULL) for this supplier and price rule
        $checkStmt = $pdo->prepare("SELECT id, valid_from FROM supplier_cost_periods WHERE supplier_id = :supplier_id AND price_rule_id = :price_rule_id AND valid_to IS NULL ORDER BY valid_from DESC LIMIT 1");
        $checkStmt->execute([
            ':supplier_id' => $supplier_id,
            ':price_rule_id' => $price_rule_id
        ]);
        $latestExistingRecordForPeriod = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // If a latest existing record exists AND its valid_from is GREATER THAN or EQUAL TO the new valid_from,
        // it means we are trying to add an older or same-date record after a newer one.
        // This might indicate an error or require specific business logic.
        // For simplicity, we'll prevent adding an older/same-date record if a newer 'current' one already exists.
        // If the valid_from is exactly the same, it means an attempt to duplicate, which the previous 'is_direct_update' should have caught if 'id' was sent.
        if ($latestExistingRecordForPeriod && $latestExistingRecordForPeriod['valid_from'] >= $valid_from) {
             throw new Exception("Geçerlilik Başlangıç Tarihi, mevcut aktif maliyet periyodundan önceki veya aynı bir tarih olamaz.");
        }


        // If a previous 'current' record exists (valid_to IS NULL), close it by setting its valid_to
        if ($latestExistingRecordForPeriod) {
            $updatePreviousParams = [
                ':new_valid_from' => $valid_from,
                ':id' => $latestExistingRecordForPeriod['id']
            ];

            log_error("⏳ CLOSING PREVIOUS PERIOD PARAMS:\n" . print_r($updatePreviousParams, true), 'supplier_costs_errors.log');

            $updatePreviousValidToStmt = $pdo->prepare("
                UPDATE supplier_cost_periods 
                SET valid_to = DATE_SUB(:new_valid_from, INTERVAL 1 DAY), updated_at = NOW()
                WHERE id = :id
            ");
            $updatePreviousValidToStmt->execute($updatePreviousParams);
            
            if (!$updatePreviousValidToStmt->rowCount()) {
                // This might happen if the new valid_from is not strictly greater than the old one,
                // or if the record was already closed by another means.
                // Log it, but don't necessarily throw an error unless it's critical.
                log_error("⚠️ No previous period closed for supplier_id: $supplier_id, price_rule_id: $price_rule_id when adding new valid_from: $valid_from", 'supplier_costs_warnings.log');
            }
        }

        // ✅ Insert New record
        $insertParams = [
            ':supplier_id' => $supplier_id,
            ':price_rule_id' => $price_rule_id,
            ':valid_from' => $valid_from,
            ':cost_per_adult' => $cost_per_adult,
            ':cost_per_child' => $cost_per_child,
            ':cost_per_vehicle' => $cost_per_vehicle,
            ':fixed_base_price' => $fixed_base_price,
            ':price_per_km_range' => $price_per_km_range,
            ':price_per_minute' => $price_per_minute,
            ':price_per_extra_minute' => $price_per_extra_minute,
            ':extras_json' => $extras_json,
            ':currency' => $currency
        ];

        log_error("🧾 INSERT PARAMS:\n" . print_r($insertParams, true), 'supplier_costs_errors.log');

        $insertStmt = $pdo->prepare("
            INSERT INTO supplier_cost_periods (
                supplier_id, price_rule_id, valid_from, valid_to,
                cost_per_adult, cost_per_child,
                cost_per_vehicle,
                fixed_base_price, price_per_km_range, price_per_minute, price_per_extra_minute,
                extras_json, currency, created_at, updated_at
            ) VALUES (
                :supplier_id, :price_rule_id, :valid_from, NULL,
                :cost_per_adult, :cost_per_child,
                :cost_per_vehicle,
                :fixed_base_price, :price_per_km_range, :price_per_minute, :price_per_extra_minute,
                :extras_json, :currency, NOW(), NOW()
            )
        ");

        $insertResult = $insertStmt->execute($insertParams);
        if (!$insertResult) {
            $err = $insertStmt->errorInfo();
            log_error("💥 INSERT ERROR INFO:\n" . print_r($err, true), 'supplier_costs_errors.log');
            throw new Exception("Yeni kayıt eklenemedi: " . ($err[2] ?? 'Bilinmeyen hata'));
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Maliyet periyodu başarıyla kaydedildi.']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errMsg = "Veritabanı Hatası: " . $e->getMessage() . " | Kod: " . $e->getCode();
    log_error("🛑 PDOException:\n" . $errMsg, 'supplier_costs_errors.log');
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası oluştu.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errMsg = "Genel Hata: " . $e->getMessage();
    log_error("⚠️ Exception:\n" . $errMsg, 'supplier_costs_errors.log');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>