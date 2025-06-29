<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../classes/Reservation.php';

$reservationManager = new Reservation($pdo);

// JSON kaynakları
$json_sources = [
    'tr' => 'https://tr.rideandgoo.com/wp-json/cha-booking/v1/reservations?token=18IvxAKOlSlkQj4B7yCFMRqkUcOSGwfq2YuWEt2mYOrdKJ8QpH',
    'en' => 'https://rideandgoo.com/wp-json/cha-booking/v1/reservations?token=18IvxAKOlSlkQj4B7yCFMRqkUcOSGwfq2YuWEt2mYOrdKJ8QpH'
];

foreach ($json_sources as $lang => $url) {
    $json = file_get_contents($url);
    if (!$json) {
        echo "[$lang] JSON alinamadi: $url\n";
        continue;
    }

    $data = json_decode($json, true);

    if (!is_array($data)) {
        echo "[$lang] Gecersiz JSON verisi.\n";
        continue;
    }

    foreach ($data as $entry) {
        $res = $entry['reservation'];
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

        // Ucus bilgilerini ayikla
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

        $id = (int)$res['id'];
        $db_row = $reservationManager->exists($id);

        $reservationData = [
            'id' => $id,
            'lang' => $lang,
            'status' => $res['status'],
            'created_at' => $res['created_at'],

            'customer_first_name' => $customer['first_name'],
            'customer_last_name' => $customer['last_name'],
            'customer_email' => $customer['email'],
            'customer_phone' => $customer['phone'],

            'pickup_date' => date('Y-m-d', strtotime($schedule['pickup_date'] ?? null)),
            'pickup_time' => $schedule['pickup_time'] ?? null,
            'return_date' => $schedule['return_date'] ? date('Y-m-d', strtotime($schedule['return_date'])) : null,
            'return_time' => $schedule['return_time'] ?? null,

            'vehicle_id' => $vehicle['vehicle_id'],
            'driver_id' => $vehicle['driver_id'],
            'company_id' => $vehicle['company_id'],

            'adults' => $passengers['adults'],
            'children' => $passengers['children'],

            'rule_id' => $rule['rule_id'],
            'rule_name' => $rule['rule_name'],
            'pickup_geofence_id' => $geofence['pickup']['geofence_id'] ?? null,
            'pickup_geofence_name' => $geofence['pickup']['geofence_name'] ?? null,
            'dropoff_geofence_id' => $geofence['dropoff']['geofence_id'] ?? null,
            'dropoff_geofence_name' => $geofence['dropoff']['geofence_name'] ?? null,

            'payment_method' => $payment['payment_method'],
            'currency' => $payment['currency'],
            'net_price' => $payment['net_price'],
            'gross_price' => $payment['gross_price'],
            'paid_amount' => $payment['paid_amount'],
            'remaining_amount' => $payment['remaining_amount'],
            'gratuity_amount' => $payment['gratuity_amount'],

            'pickup_lat' => $travel['pickup']['lat'],
            'pickup_lng' => $travel['pickup']['lng'],
            'pickup_address' => $travel['pickup']['address'],
            'dropoff_lat' => $travel['dropoff']['lat'],
            'dropoff_lng' => $travel['dropoff']['lng'],
            'dropoff_address' => $travel['dropoff']['address'],
            'stopovers' => json_encode($travel['stopovers']),
            'stopovers_duration' => $travel['stopovers_duration'],
            'extra_time' => $travel['extra_time'],
            'total_duration' => $travel['total_duration'],
            'total_distance' => $travel['total_distance'],

            'comment' => $meta['comment'],
            'flight_direction' => $flight_data['flight_direction'],
            'flight_pnr' => $flight_data['flight_pnr'],
            'flight_idno' => $flight_data['flight_idno'],
            'flight_info' => json_encode($flight_info),
            'extras' => json_encode($entry['extras'] ?? [])
        ];

        if ($db_row) {
            $reservationManager->update($reservationData);
            echo "[$lang] Güncellendi: #$id\n";
        } else {
            $reservationManager->insert($reservationData);
            echo "[$lang] Eklendi: #$id\n";
        }
    }
}
