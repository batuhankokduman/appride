<?php
require_once '../functions/db.php';
require_once '../includes/auth.php';

$id = $_POST['id'] ?? null;
$vehicle_id = $_POST['vehicle_id'] ?? null;
$rule_id = $_POST['rule_id'] ?? null;
$rule_name = $_POST['rule_name'] ?? null;
$pickup_geofence_id = $_POST['pickup_geofence_id'] ?? null;
$dropoff_geofence_id = $_POST['dropoff_geofence_id'] ?? null;
$price_rule_type_id = $_POST['price_rule_type_id'] ?? null;

if (!$id || !$price_rule_type_id) {
    die("Gerekli alanlar eksik.");
}

// Kayıt var mı kontrol et
$stmt = $pdo->prepare("SELECT COUNT(*) FROM price_rules WHERE id = ?");
$stmt->execute([$id]);
$exists = $stmt->fetchColumn();

if ($exists) {
    // Güncelle
    $stmt = $pdo->prepare("UPDATE price_rules SET
        vehicle_id = :vehicle_id,
        rule_id = :rule_id,
        rule_name = :rule_name,
        pickup_geofence_id = :pickup_geofence_id,
        dropoff_geofence_id = :dropoff_geofence_id,
        price_rule_type_id = :price_rule_type_id
        WHERE id = :id");
} else {
    // Ekle
    $stmt = $pdo->prepare("INSERT INTO price_rules (
        id, vehicle_id, rule_id, rule_name,
        pickup_geofence_id, dropoff_geofence_id, price_rule_type_id
    ) VALUES (
        :id, :vehicle_id, :rule_id, :rule_name,
        :pickup_geofence_id, :dropoff_geofence_id, :price_rule_type_id
    )");
}

$stmt->execute([
    ':id' => $id,
    ':vehicle_id' => $vehicle_id ?: null,
    ':rule_id' => $rule_id ?: null,
    ':rule_name' => $rule_name ?: null,
    ':pickup_geofence_id' => $pickup_geofence_id ?: null,
    ':dropoff_geofence_id' => $dropoff_geofence_id ?: null,
    ':price_rule_type_id' => $price_rule_type_id
]);

header('Location: price_rules.php');
exit;