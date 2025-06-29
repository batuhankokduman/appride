<?php
// Sunucuya 200 OK kodunu göndermesi için zorla (Hosting kaynaklı 206 hatasına karşı)
header("HTTP/1.1 200 OK");

// 1. Veritabanı bağlantısını dahil et
require_once 'functions/db.php';
require_once 'config/stripe.php';


// 2. URL Parametrelerini Güvenli Bir Şekilde Al ve İşle
parse_str(str_replace('&amp;', '&', $_SERVER['QUERY_STRING']), $_GET);

$id = $_GET['id'] ?? null;
$token = $_GET['t'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'save_passengers') {
        $res_id = intval($_POST['reservation_id']);
        $token  = trim($_POST['token'] ?? '');
        $names  = $_POST['passenger_name'] ?? [];
        $ids    = $_POST['passenger_id'] ?? [];

        foreach ($names as $i => $full) {
            $full = trim($full);
            if ($full === '') { continue; }
            $parts = preg_split('/\s+/', $full, 2);
            $fn = $parts[0] ?? '';
            $ln = $parts[1] ?? '';
            $pid = intval($ids[$i] ?? 0);

            if ($pid) {
                $stmt = $pdo->prepare("UPDATE reservation_passengers SET first_name = ?, last_name = ? WHERE id = ? AND reservation_id = ?");
                $stmt->execute([$fn, $ln, $pid, $res_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO reservation_passengers (reservation_id, first_name, last_name, is_main_contact) VALUES (?, ?, ?, 0)");
                $stmt->execute([$res_id, $fn, $ln]);
            }
        }

        header("Location: rez.php?id=$res_id&t=" . urlencode($token));
        exit;
    }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_flight_details') {
    $res_id = intval($_POST['reservation_id'] ?? 0);
    $token = trim($_POST['token'] ?? '');
    $flight_number = trim($_POST['flight_number'] ?? '');
    $flight_direction = trim($_POST['flight_direction'] ?? '');

    if (!$res_id || !$token || !$flight_number || !$flight_direction) {
        die('Geçersiz istek. Tüm alanlar doldurulmalıdır.');
    }

    // Token ve mevcut uçuş bilgilerini veritabanından al
    $stmt = $pdo->prepare("SELECT token, flight_info FROM reservations WHERE reservation_id = ?");
    $stmt->execute([$res_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || $row['token'] !== $token) {
        die('Token doğrulaması başarısız.');
    }

    $info = json_decode($row['flight_info'] ?? '[]', true) ?: [];

    // Uçuş Numarası için yardımcı fonksiyon
    $update_info = function(&$info_array, $search_regex, $new_value, $default_label) {
        $found = false;
        foreach ($info_array as &$entry) {
            if (isset($entry['label']) && preg_match($search_regex, $entry['label'])) {
                $entry['value'] = $new_value;
                $found = true;
                break;
            }
        }
        unset($entry);
        if (!$found) {
            $info_array[] = ['label' => $default_label, 'value' => $new_value];
        }
    };

    // Uçuş Numarasını güncelle veya ekle
    $update_info($info, '/(pnr|uçuş|flight)/i', $flight_number, 'Flight Number / PNR');

    // Uçuş Yönünü güncelle veya ekle
    $update_info($info, '/(yön|direction)/i', $flight_direction, 'Flight Direction');

    // flight_info alanını veritabanında güncelle
    $stmt = $pdo->prepare("UPDATE reservations SET flight_info = ? WHERE reservation_id = ?");
    $stmt->execute([json_encode($info, JSON_UNESCAPED_UNICODE), $res_id]);

    // Sayfayı yeniden yükle
    header("Location: rez.php?id=$res_id&t=" . urlencode($token));
    exit;
}

}

// 3. Erişim Kontrolü
if (!$id || !$token) {
    echo "<div style='padding:20px; font-family:sans-serif; color:darkred;'>❌ Erişim reddedildi. Eksik parametre.</div>";
    exit;
}

// 4. Veritabanından Rezervasyonu ve Araç Bilgilerini Sorgula
$stmt = $pdo->prepare("
    SELECT r.*, v.* FROM reservations r
    LEFT JOIN vehicles v ON r.vehicle_id = v.vehicle_id
    WHERE r.reservation_id = ? AND r.access_token = ?
");
$stmt->execute([$id, $token]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);

// 5. Yetki Kontrolü
if (!$res || empty($res['access_token'])) {
    echo "<div style='padding:20px; font-family:sans-serif; color:darkred;'>🛑 Erişim yetkiniz yok veya geçersiz bağlantı.</div>";
    exit;
}

// --- MANTIK KATMANI ---

// 6. Dil ve Metinleri Ayarla (ÖDEME METİNLERİ GÜNCELLENDİ)
$lang = ($res['currency'] ?? '') === 'TRY' ? 'tr' : 'en';

$texts = [
    'tr' => [
        'header_title' => 'E-Bilet: Rezervasyon Detayları',
        'header_subtitle' => 'Keyifli ve konforlu bir yolculuk dileriz!',
        'payment_status_none' => 'Ödeme yapmadığınız için rezervasyonunuz henüz onaylanmamıştır.',
        'payment_status_pay_on_arrival' => 'Rezervasyonunuz onaylandı. Kalan ödemeyi araçta yapabilirsiniz. İyi yolculuklar!', // YENİ
        'payment_status_partial' => 'Ön ödeme alındı. Kalan ödemeyi araçta yapabilirsiniz.', // GÜNCELLENDİ
        'payment_status_full' => 'Ödemeniz tamamlanmıştır. İyi yolculuklar dileriz!',
        'ticket_title' => 'Rezervasyon Detayları',
        'customer_info' => 'Rezervasyon Sahibi',
        'name' => 'İsim Soyisim',
        'phone' => 'Telefon',
        'email' => 'E-posta',
        'passenger_count' => 'Yolcu Sayısı',
        'adult' => 'Yetişkin',
        'child' => 'Çocuk',
        'flight_info_title' => 'Uçuş ve Transfer Detayları',
        'flight_date_time' => 'Uçuş Tarihi ve Saati',
        'pickup_date_time' => 'Transfer için Alınış Saati',
        'trip_info_title' => 'Seyahat Detayları',
        'selected_date_time' => 'Seçilen Tarih ve Saat',
        'confirmed_pickup_time' => 'Kesin Alınış Tarihi ve Saati',
        'not_confirmed_yet' => 'Henüz belirlenmedi',
        'flight_number' => 'Uçuş Numarası',
        'vehicle_info' => 'Araç Bilgileri',
        'capacity' => 'Kapasite',
        'passengers' => 'Yolcu',
        'luggage' => 'Bagaj',
        'features' => 'Özellikler',
        'route_info' => 'Rota Bilgileri',
        'pickup_location' => 'Alınış Adresi',
        'stopovers' => 'Ara Duraklar',
        'dropoff_location' => 'Bırakılış Adresi',
        'total_distance' => 'Toplam Mesafe',
        'total_duration' => 'Tahmini Süre',
        'extras' => 'Ekstra Hizmetler',
        'payment_info' => 'Ödeme Bilgileri',
        'total_price' => 'Toplam Tutar',
        'paid_amount' => 'Ödenen',
        'remaining_amount' => 'Kalan',
        'pay_now' => 'Hemen Öde',
        'footer_whatsapp' => 'WhatsApp Destek',
        'footer_faq' => 'Sıkça Sorulan Sorular',
        'footer_blog' => 'Blog',
        'og_title_format' => '%s %s - RideAndGoo Rezervasyon Bileti',
        'og_description_format' => '📅 %s 🕔 %s 📍 Alınış: %s',
        'passenger_list_title' => 'Yolcu Listesi',
        'first_name_label' => 'Adı',
        'last_name_label' => 'Soyadı',
        'save_button' => 'Kaydet',
        'enter_flight_number' => 'Lütfen uçuş numaranızı giriniz',
        'passenger_warning' => 'Lütfen yolculuğa katılacak herkesin isim soyisim bilgilerini kaydedin.',
        'flight_direction_domestic' => 'İç Hatlar',
        'flight_direction_international' => 'Dış Hatlar',

    ],
    'en' => [
        'header_title' => 'E-Ticket: Reservation Details',
        'header_subtitle' => 'We wish you a pleasant and comfortable journey!',
        'payment_status_none' => 'Your reservation is not yet confirmed because no payment has been made.',
        'payment_status_pay_on_arrival' => 'Your reservation is confirmed. You can pay the remaining balance in the vehicle. Have a nice trip!', // NEW
        'payment_status_partial' => 'A down payment has been received. You can pay the remaining balance in the vehicle.', // UPDATED
        'payment_status_full' => 'Your payment is complete. We wish you a pleasant journey!',
        'ticket_title' => 'Reservation Details',
        'customer_info' => 'Reservation Owner Info',
        'name' => 'Full Name',
        'phone' => 'Phone',
        'email' => 'Email',
        'passenger_count' => 'Passenger Count',
        'adult' => 'Adult',
        'child' => 'Child',
        'flight_info_title' => 'Flight and Transfer Details',
        'flight_date_time' => 'Flight Date and Time',
        'pickup_date_time' => 'Pickup Time for Transfer',
        'trip_info_title' => 'Trip Details',
        'selected_date_time' => 'Selected Date and Time',
        'confirmed_pickup_time' => 'Confirmed Pickup Date and Time',
        'not_confirmed_yet' => 'Not yet determined',
        'flight_number' => 'Flight Number',
        'vehicle_info' => 'Vehicle Information',
        'capacity' => 'Capacity',
        'passengers' => 'Passengers',
        'luggage' => 'Luggage',
        'features' => 'Features',
        'route_info' => 'Route Information',
        'pickup_location' => 'Pickup Address',
        'stopovers' => 'Stopovers',
        'dropoff_location' => 'Dropoff Address',
        'total_distance' => 'Total Distance',
        'total_duration' => 'Estimated Duration',
        'extras' => 'Extra Services',
        'payment_info' => 'Payment Information',
        'total_price' => 'Total Price',
        'paid_amount' => 'Paid',
        'remaining_amount' => 'Remaining',
        'pay_now' => 'Pay Now',
        'footer_whatsapp' => 'WhatsApp Support',
        'footer_faq' => 'Frequently Asked Questions',
        'footer_blog' => 'Blog',
        'og_title_format' => '%s %s - RideAndGoo Reservation Ticket',
        'og_description_format' => '📅 %s 🕔 %s 📍 Pickup: %s',
        'passenger_list_title' => 'Passenger List',
        'first_name_label' => 'First Name',
        'last_name_label' => 'Last Name',
        'save_button' => 'Save',
        'enter_flight_number' => 'Please enter your flight number',
        'passenger_warning' => 'Please enter and save full names of all travellers.',
        'flight_direction_domestic' => 'Domestic Flights',
        'flight_direction_international' => 'International Flights',

    ]
];
$t = $texts[$lang];

// 7. Tüm Verileri Hazırla
$extras = json_decode($res['extras'] ?? '[]', true);
$stopovers = json_decode($res['stopovers'] ?? '[]', true);
$flight_info = json_decode($res['flight_info'] ?? '[]', true);

$extra_ids = array_column($extras, 'extra_service_id');
$extras_map = [];
if (!empty($extra_ids)) {
    $in = str_repeat('?,', count($extra_ids) - 1) . '?';
    $stmtExtras = $pdo->prepare("SELECT id, service_name FROM extras WHERE id IN ($in)");
    $stmtExtras->execute($extra_ids);
    foreach ($stmtExtras->fetchAll(PDO::FETCH_ASSOC) as $e) { $extras_map[$e['id']] = $e['service_name']; }
}

$customer_email = $res['customer_email'] ?? '';

$vehicle_details = [
    'name' => $res['vehicle_name'] ?? null,
    'model' => $res['vehicle_model'] ?? null,
    'pax' => $res['vehicle_passenger'] ?? 0,
    'luggage' => $res['vehicle_luggage'] ?? 0,
    'features' => json_decode($res['vehicle_features'] ?? '[]', true),
    'photo_url' => $res['vehicle_photo_url'] ?? null // YENİ EKLENDİ
];
$duration_minutes = (int) round((float) ($res['total_duration'] ?? 0));
$hours = floor($duration_minutes / 60);
$minutes = $duration_minutes % 60;
$formatted_duration = ($hours > 0 ? $hours . ($lang === 'tr' ? ' saat ' : 'h ') : '') . $minutes . ($lang === 'tr' ? ' dk' : ' min');

$route_summary = [
    'distance' => round((float) ($res['total_distance'] ?? 0), 1),
    'duration' => $formatted_duration
];

// Yolcu bilgilerini al ve eksikleri hesapla
$stmt = $pdo->prepare("SELECT * FROM reservation_passengers WHERE reservation_id = ? ORDER BY id ASC");
$stmt->execute([$res['reservation_id']]);
$passengers = $stmt->fetchAll();

$totalPassengers = (int) $res['passengers_adults'] + (int) $res['passengers_children'];
$missingPassengerSlots = $totalPassengers - count($passengers);

// Uçuş yönü ve numarası kontrolü
$needsFlightNumber = false;
$direction = '';
$hasFlightNumber = false;

foreach ($flight_info as $entry) {
    if (stripos($entry['label'], 'Yön') !== false) {
        $direction = $entry['value'];
    }
    if (preg_match('/(pnr|uçuş|flight)/i', $entry['label'])) {
        $hasFlightNumber = true;
    }
}
if (in_array($direction, ['İç Hatlar', 'Dış Hatlar']) && !$hasFlightNumber) {
    $needsFlightNumber = true;
}


// Sosyal Medya Verileri
$customerFirstName = htmlspecialchars($res['customer_first_name'] ?? '');
$customerLastName = htmlspecialchars($res['customer_last_name'] ?? '');
$pickupAddress = htmlspecialchars($res['pickup_address'] ?? '');
$reservationTime = htmlspecialchars($res['schedule_selected_time'] ?? '');
$rawDate = $res['schedule_selected_date'] ?? '';
$reservationDate = !empty($rawDate) ? date('d.m.Y', strtotime($rawDate)) : '';
$og_title = sprintf($t['og_title_format'], $customerFirstName, $customerLastName);
$og_description = sprintf($t['og_description_format'], $reservationDate, $reservationTime, $pickupAddress);
$og_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$og_image_landscape = 'https://rideandgoo.com/uploads/OG-Logo-Yatay.webp';
$og_image_square    = 'https://rideandgoo.com/uploads/OG-Logo-Kare.webp';

// Harita Verileri
$pickupLat = $res['pickup_lat'] ?? null;
$pickupLng = $res['pickup_lng'] ?? null;
$dropLat = $res['dropoff_lat'] ?? null;
$dropLng = $res['dropoff_lng'] ?? null;
$stopoversJson = json_encode($stopovers ?? []);

// --- MANTIK BÖLÜMÜ SONA ERDİ ---

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ticket-template_tedarik.php';