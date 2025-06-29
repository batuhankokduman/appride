<?php
class Reservation
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Kayıt var mı kontrol et (hem ana tabloyu hem de çöp kutusunu kontrol eder)
    public function exists($reservation_id)
    {
        // Önce aktif rezervasyonlar tablosuna bak
        $stmt = $this->pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ?");
        $stmt->execute([$reservation_id]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }

        // Eğer bulunamadıysa çöp kutusu tablosunu kontrol et
        $stmt = $this->pdo->prepare("SELECT * FROM reservations_trash WHERE reservation_id = ?");
        $stmt->execute([$reservation_id]);
        return $stmt->fetch();
    }

// Yeni kayıt ekle
public function insert($data)
{
    $sql = "INSERT INTO reservations (
        reservation_id, reservation_created_at, reservation_status, reservation_lang,
        customer_first_name, customer_last_name, customer_email, customer_phone,
        schedule_selected_date, schedule_selected_time, schedule_pickup_date, schedule_pickup_time, schedule_return_date, schedule_return_time,
        vehicle_id, driver_id, company_id,
        passengers_adults, passengers_children,
        rule_id, rule_name, pickup_geofence_id, pickup_geofence_name, dropoff_geofence_id, dropoff_geofence_name,
        payment_method, currency, net_price, gross_price, paid_amount, remaining_amount, gratuity_amount,
        pickup_lat, pickup_lng, pickup_address,
        dropoff_lat, dropoff_lng, dropoff_address,
        maps_url,
        stopovers, stopovers_duration, extra_time, total_duration, total_distance,
        metadata_comment, flight_info, extras, access_token
    ) VALUES (
        :reservation_id, :reservation_created_at, :reservation_status, :reservation_lang,
        :customer_first_name, :customer_last_name, :customer_email, :customer_phone,
        :schedule_selected_date, :schedule_selected_time, :schedule_pickup_date, :schedule_pickup_time, :schedule_return_date, :schedule_return_time,
        :vehicle_id, :driver_id, :company_id,
        :passengers_adults, :passengers_children,
        :rule_id, :rule_name, :pickup_geofence_id, :pickup_geofence_name, :dropoff_geofence_id, :dropoff_geofence_name,
        :payment_method, :currency, :net_price, :gross_price, :paid_amount, :remaining_amount, :gratuity_amount,
        :pickup_lat, :pickup_lng, :pickup_address,
        :dropoff_lat, :dropoff_lng, :dropoff_address,
        :maps_url,
        :stopovers, :stopovers_duration, :extra_time, :total_duration, :total_distance,
        :metadata_comment, :flight_info, :extras, :access_token
    )";

    $stmt = $this->pdo->prepare($sql);
    $success = $stmt->execute($data);

    if ($success) {
        $reservation_id = $data['reservation_id'];

        // Ana yolcu daha önce eklenmemişse ekle
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reservation_passengers WHERE reservation_id = ? AND is_main_contact = 1");
        $stmt->execute([$reservation_id]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $this->pdo->prepare("INSERT INTO reservation_passengers (reservation_id, first_name, last_name, is_main_contact) VALUES (?, ?, ?, 1)");
            $stmt->execute([
                $reservation_id,
                $data['customer_first_name'] ?? '',
                $data['customer_last_name'] ?? ''
            ]);
        }
    }

    return $success;
}


    // Kayıt güncelle
    public function update($data)
    {
        $sql = "UPDATE reservations SET
            reservation_created_at = :reservation_created_at,
            reservation_status = :reservation_status,
            reservation_lang = :reservation_lang,
            customer_first_name = :customer_first_name,
            customer_last_name = :customer_last_name,
            customer_email = :customer_email,
            customer_phone = :customer_phone,
            schedule_selected_date = :schedule_selected_date,
            schedule_selected_time = :schedule_selected_time,
            schedule_pickup_date = :schedule_pickup_date,
            schedule_pickup_time = :schedule_pickup_time,
            schedule_return_date = :schedule_return_date,
            schedule_return_time = :schedule_return_time,
            vehicle_id = :vehicle_id,
            driver_id = :driver_id,
            company_id = :company_id,
            passengers_adults = :passengers_adults,
            passengers_children = :passengers_children,
            rule_id = :rule_id,
            rule_name = :rule_name,
            pickup_geofence_id = :pickup_geofence_id,
            pickup_geofence_name = :pickup_geofence_name,
            dropoff_geofence_id = :dropoff_geofence_id,
            dropoff_geofence_name = :dropoff_geofence_name,
            payment_method = :payment_method,
            currency = :currency,
            net_price = :net_price,
            gross_price = :gross_price,
            paid_amount = :paid_amount,
            remaining_amount = :remaining_amount,
            gratuity_amount = :gratuity_amount,
            pickup_lat = :pickup_lat,
            pickup_lng = :pickup_lng,
            pickup_address = :pickup_address,
            dropoff_lat = :dropoff_lat,
            dropoff_lng = :dropoff_lng,
            dropoff_address = :dropoff_address,
            maps_url = :maps_url,
            stopovers = :stopovers,
            stopovers_duration = :stopovers_duration,
            extra_time = :extra_time,
            total_duration = :total_duration,
            total_distance = :total_distance,
            metadata_comment = :metadata_comment,
            flight_info = :flight_info,
            extras = :extras
        WHERE reservation_id = :reservation_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }
}
