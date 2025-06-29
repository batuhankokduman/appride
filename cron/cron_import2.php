<?php
// Güvenli token kontrolü
$allowedOrigins = ['https://tr.rideandgoo.com', 'https://rideandgoo.com'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
}
define('CRON_SECRET_TOKEN', 'SyhYFDDKLsSTsNtybanAtrksSakLsnSaYtvZs15S486AK445');
if (!isset($_GET['token']) || $_GET['token'] !== CRON_SECRET_TOKEN) {
    header('HTTP/1.0 403 Forbidden');
    error_log("CRON KORUMASI: Yetkisiz erişim denemesi. IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor'));
    die('Erişim reddedildi.');
}

require_once __DIR__ . '/../functions/db.php';
require_once __DIR__ . '/../classes/Reservation.php';
require_once __DIR__ . '/../functions/trigger_engine.php';

function parse_price($value) {
    return floatval(str_replace(',', '', $value));
}

function generateToken($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $token;
}

function build_google_maps_url($pickupLat, $pickupLng, $stopovers, $dropoffLat, $dropoffLng) {
    $origin = "{$pickupLat},{$pickupLng}";
    $destination = "{$dropoffLat},{$dropoffLng}";
    $waypoints = [];

    if (is_array($stopovers)) {
        foreach ($stopovers as $stop) {
            if (isset($stop['lat'], $stop['lng'])) {
                $waypoints[] = $stop['lat'] . ',' . $stop['lng'];
            }
        }
    }

    $url = 'https://www.google.com/maps/dir/?api=1';
    $url .= '&origin=' . urlencode($origin);
    $url .= '&destination=' . urlencode($destination);
    $url .= '&travelmode=driving';
    if (!empty($waypoints)) {
        $url .= '&waypoints=' . urlencode(implode('|', $waypoints));
    }

    return $url;
}

$reservationManager = new Reservation($pdo);

$json_sources = [
    'tr' => 'https://tr.rideandgoo.com/wp-json/cha-booking/v1/reservations?token=18IvxAKOlSlkQj4B7yCFMRqkUcOSGwfq2YuWEt2mYOrdKJ8QpH',
    'en' => 'https://rideandgoo.com/wp-json/cha-booking/v1/reservations?token=18IvxAKOlSlkQj4B7yCFMRqkUcOSGwfq2YuWEt2mYOrdKJ8QpH'
];

$latestReservation = null;

foreach ($json_sources as $lang => $url) {
    $json = file_get_contents($url);
    if (!$json) continue;

    $data = json_decode($json, true);
    if (!is_array($data)) continue;

    foreach ($data as $entry) {
        $res = $entry['reservation'];
        $id = (int)$res['id'];
        $db_row = $reservationManager->exists($id);
        if ($db_row) continue;

        $customer = $entry['customer'];
        $schedule = $entry['schedule'];
        $vehicle = $entry['vehicle'];
        $passengers = $entry['passengers'];
        $rule = $entry['price_rule'];
        $geofence = $rule['geofence'];
        $payment = $entry['payment'];
        $travel = $entry['travel'];
        $meta = $entry['metadata'];
        $flight_info = $meta['flight_info'] ?? [];

        $flight_data = [
            'flight_direction' => '',
            'flight_pnr' => '',
            'flight_idno' => ''
        ];

        foreach ($flight_info as $item) {
            if (in_array($item['label'], ['Flight Direction', 'Uçuş Yönü'])) $flight_data['flight_direction'] = $item['value'];
            if (in_array($item['label'], ['Flight PNR Number', 'Uçuş PNR Numarası'])) $flight_data['flight_pnr'] = $item['value'];
            if (in_array($item['label'], ['ID or Passport Number', 'TC Kimlik veya Pasaport No'])) $flight_data['flight_idno'] = $item['value'];
        }

        $maps_url = build_google_maps_url(
            $travel['pickup']['lat'],
            $travel['pickup']['lng'],
            $travel['stopovers'],
            $travel['dropoff']['lat'],
            $travel['dropoff']['lng']
        );

        $accessToken = generateToken(8);

        $reservationData = [
            'reservation_id' => $id,
            'reservation_created_at' => $res['created_at'],
            'reservation_status' => $res['status'],
            'reservation_lang' => $lang,

            'customer_first_name' => $customer['first_name'],
            'customer_last_name' => $customer['last_name'],
            'customer_email' => $customer['email'],
            'customer_phone' => $customer['phone'],
            'schedule_selected_date'=> date('Y-m-d', strtotime($schedule['selected_date'] ?? null)),
            'schedule_selected_time' => $schedule['selected_time'] ?? null,
            'schedule_pickup_date' => null,
            'schedule_pickup_time' => null,
            'schedule_return_date' => $schedule['return_date'] ? date('Y-m-d', strtotime($schedule['return_date'])) : null,
            'schedule_return_time' => $schedule['return_time'] ?? null,

            'vehicle_id' => $vehicle['vehicle_id'],
            'driver_id' => $vehicle['driver_id'],
            'company_id' => $vehicle['company_id'],

            'passengers_adults' => $passengers['adults'],
            'passengers_children' => $passengers['children'],

            'rule_id' => $rule['rule_id'],
            'rule_name' => $rule['rule_name'],
            'pickup_geofence_id' => $geofence['pickup']['geofence_id'] ?? null,
            'pickup_geofence_name' => $geofence['pickup']['geofence_name'] ?? null,
            'dropoff_geofence_id' => $geofence['dropoff']['geofence_id'] ?? null,
            'dropoff_geofence_name' => $geofence['dropoff']['geofence_name'] ?? null,

            'payment_method' => $payment['payment_method'],
            'currency' => $payment['currency'],
            'net_price' => parse_price($payment['net_price']),
            'gross_price' => parse_price($payment['gross_price']),
            'paid_amount' => parse_price($payment['paid_amount']),
            'remaining_amount' => parse_price($payment['remaining_amount']),
            'gratuity_amount' => parse_price($payment['gratuity_amount']),

            'pickup_lat' => $travel['pickup']['lat'],
            'pickup_lng' => $travel['pickup']['lng'],
            'pickup_address' => $travel['pickup']['address'],
            'dropoff_lat' => $travel['dropoff']['lat'],
            'dropoff_lng' => $travel['dropoff']['lng'],
            'dropoff_address' => $travel['dropoff']['address'],
            'maps_url' => $maps_url,
            'stopovers' => json_encode($travel['stopovers'] ?? []),
            'stopovers_duration' => $travel['stopovers_duration'],
            'extra_time' => $travel['extra_time'],
            'total_duration' => $travel['total_duration'],
            'total_distance' => $travel['total_distance'],

            'metadata_comment' => $meta['comment'],
            'flight_info' => json_encode($flight_info),
            'extras' => json_encode($entry['extras'] ?? []),
            'access_token' => $accessToken
        ];

        try {
            
            // Eğer return rezervasyonu varsa fiyatları ikiye böl
if (!empty($schedule['return_date']) && !empty($schedule['return_time'])) {
    $reservationData['net_price'] = $reservationData['net_price'] / 2;
    $reservationData['gross_price'] = $reservationData['gross_price'] / 2;
    $reservationData['paid_amount'] = $reservationData['paid_amount'] / 2;
    $reservationData['remaining_amount'] = $reservationData['remaining_amount'] / 2;
    // gratuity_amount olduğu gibi bırakılıyor
}

            
            $reservationManager->insert($reservationData);
            TriggerEngine::checkAndSend($id, [], $reservationData, true);
            // ✅ RETURN rezervasyonu kontrol et
// ✅ RETURN rezervasyonu kontrol et
if (!empty($schedule['return_date']) && !empty($schedule['return_time'])) {
    $returnId = (int)('999' . $id);
    $returnData = $reservationData;
    $returnData['reservation_id'] = $returnId;
    $returnData['schedule_selected_date'] = date('Y-m-d', strtotime($schedule['return_date']));
    $returnData['schedule_selected_time'] = $schedule['return_time'];

    // Konumları birebir değiştir
    $returnData['pickup_lat'] = $travel['dropoff']['lat'];
    $returnData['pickup_lng'] = $travel['dropoff']['lng'];
    $returnData['pickup_address'] = $travel['dropoff']['address'];
    $returnData['dropoff_lat'] = $travel['pickup']['lat'];
    $returnData['dropoff_lng'] = $travel['pickup']['lng'];
    $returnData['dropoff_address'] = $travel['pickup']['address'];

    // Fiyatı böl
    $returnData['net_price'] = $returnData['net_price'];
    $returnData['gross_price'] = $returnData['gross_price'];
    $returnData['paid_amount'] = $returnData['paid_amount'];
    $returnData['remaining_amount'] = $returnData['remaining_amount'];

    $returnData['access_token'] = generateToken(8);

    try {
        $reservationManager->insert($returnData);
        TriggerEngine::checkAndSend($returnId, [], $returnData, true);
    } catch (Throwable $e) {
        error_log("İade rezervasyon eklenemedi [ID: $returnId]: " . $e->getMessage());
    }
}


            $latestReservation = [
                'reservation_id' => $id,
                'access_token' => $accessToken
            ];
            break 2; // 🔥 Bir tane ekleyince çık
        } catch (Throwable $e) {
            error_log("Ekleme hatası [ID: $id]: " . $e->getMessage());
        }
    }
}

// 🎯 JSON çıktı:
header('Content-Type: application/json');
if ($latestReservation) {
    echo json_encode([
        'success' => true,
        'reservation_id' => $latestReservation['reservation_id'],
        'access_token' => $latestReservation['access_token']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Yeni rezervasyon bulunamadı veya işlenemedi.'
    ]);
}
exit;
