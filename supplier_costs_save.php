<?php
require_once 'functions/db.php';
require_once 'includes/auth.php';

$id = $_POST['id'] ?? null;
$supplier_id = $_POST['supplier_id'] ?? null;
$price_rule_id = $_POST['price_rule_id'] ?? null;
$valid_from = $_POST['valid_from'] ?? null;
$valid_to = $_POST['valid_to'] ?? '9999-12-31';

$cost_per_adult = $_POST['cost_per_adult'] ?? null;
$cost_per_child = $_POST['cost_per_child'] ?? null;
$cost_per_vehicle = $_POST['cost_per_vehicle'] ?? null;
$fixed_base_price = $_POST['fixed_base_price'] ?? null;
$price_per_km_range = $_POST['price_per_km_range'] ?? null;
$price_per_minute = $_POST['price_per_minute'] ?? null;
$price_per_hour = $_POST['price_per_hour'] ?? null;

// price_rule_type_id'yi veritabanından çek
$stmt = $pdo->prepare("SELECT price_rule_type_id FROM price_rules WHERE id = ? LIMIT 1");
$stmt->execute([$price_rule_id]);
$rule = $stmt->fetch();
$price_rule_type_id = $rule['price_rule_type_id'] ?? null;

if (!$supplier_id || !$price_rule_id || !$valid_from || !$price_rule_type_id) {
    die("Eksik bilgi gönderildi.");
}

// Geçerli tarih aralığında çakışma kontrolü (güncellemede mevcut satırı hariç tut)
$check = $pdo->prepare("SELECT COUNT(*) FROM supplier_costs WHERE supplier_id = ? AND price_rule_id = ? AND valid_from <= ? AND valid_to >= ?" . ($id ? " AND id != ?" : ""));
$params = [$supplier_id, $price_rule_id, $valid_to, $valid_from];
if ($id) $params[] = $id;
$check->execute($params);
$exists = $check->fetchColumn();

if ($exists > 0) {
    die("Bu tarih aralığında aynı tedarikçi için kayıt mevcut.");
}

// JSON kontrolü
if ($price_per_km_range) {
    json_decode($price_per_km_range);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("Geçersiz JSON formatı.");
    }
}

if ($id) {
    // Güncelleme işlemi
    $stmt = $pdo->prepare("UPDATE supplier_costs SET
        supplier_id = :supplier_id,
        price_rule_id = :price_rule_id,
        price_rule_type_id = :type,
        valid_from = :valid_from,
        valid_to = :valid_to,
        cost_per_adult = :adult,
        cost_per_child = :child,
        cost_per_vehicle = :vehicle,
        fixed_base_price = :base,
        price_per_km_range = :km_range,
        price_per_minute = :minute,
        price_per_hour = :hour
        WHERE id = :id");

    $stmt->execute([
        ':supplier_id' => $supplier_id,
        ':price_rule_id' => $price_rule_id,
        ':type' => $price_rule_type_id,
        ':valid_from' => $valid_from,
        ':valid_to' => $valid_to,
        ':adult' => $cost_per_adult ?: null,
        ':child' => $cost_per_child ?: null,
        ':vehicle' => $cost_per_vehicle ?: null,
        ':base' => $fixed_base_price ?: null,
        ':km_range' => $price_per_km_range ?: null,
        ':minute' => $price_per_minute ?: null,
        ':hour' => $price_per_hour ?: null,
        ':id' => $id
    ]);
} else {
    // Yeni kayıt
    $stmt = $pdo->prepare("INSERT INTO supplier_costs (
        supplier_id, price_rule_id, price_rule_type_id,
        valid_from, valid_to,
        cost_per_adult, cost_per_child,
        cost_per_vehicle, fixed_base_price,
        price_per_km_range, price_per_minute, price_per_hour
    ) VALUES (
        :supplier_id, :price_rule_id, :type,
        :valid_from, :valid_to,
        :adult, :child, :vehicle, :base,
        :km_range, :minute, :hour
    )");

    $stmt->execute([
        ':supplier_id' => $supplier_id,
        ':price_rule_id' => $price_rule_id,
        ':type' => $price_rule_type_id,
        ':valid_from' => $valid_from,
        ':valid_to' => $valid_to,
        ':adult' => $cost_per_adult ?: null,
        ':child' => $cost_per_child ?: null,
        ':vehicle' => $cost_per_vehicle ?: null,
        ':base' => $fixed_base_price ?: null,
        ':km_range' => $price_per_km_range ?: null,
        ':minute' => $price_per_minute ?: null,
        ':hour' => $price_per_hour ?: null
    ]);
}

header('Location: supplier_costs.php');
exit;