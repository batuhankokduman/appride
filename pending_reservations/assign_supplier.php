<?php  
require_once '../functions/db.php';
require_once '../includes/auth.php';
require_once '../functions/trigger_engine.php';

$reservation_id = $_POST['reservation_id'] ?? null;
$supplier_id = $_POST['supplier_id'] ?? null;

if (!$reservation_id || !$supplier_id) {
    die("Eksik bilgi.");
}

$resStmt = $pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ?");
$resStmt->execute([$reservation_id]);
$reservation = $resStmt->fetch();

if (!$reservation) {
    die("Rezervasyon bulunamadı.");
}

$ruleStmt = $pdo->prepare("SELECT * FROM price_rules WHERE rule_id = ?");
$ruleStmt->execute([$reservation['rule_id']]);
$priceRule = $ruleStmt->fetch();

if (!$priceRule) {
    die("Fiyat kuralı bulunamadı.");
}

$supplierStmt = $pdo->prepare("
    SELECT scp.*, pr.price_rule_type_id 
    FROM supplier_cost_periods scp 
    INNER JOIN price_rules pr ON pr.id = scp.price_rule_id
    WHERE scp.supplier_id = ? AND scp.price_rule_id = ?
    AND scp.valid_from <= CURDATE()
    AND (scp.valid_to IS NULL OR scp.valid_to >= CURDATE())
    ORDER BY scp.valid_from DESC LIMIT 1
");
$supplierStmt->execute([$supplier_id, $priceRule['id']]);
$supplier = $supplierStmt->fetch();

if (!$supplier) {
    die("Tedarikçi maliyeti bulunamadı.");
}

$eurToTry = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'eur_to_try'")->fetchColumn();
$eurToTry = floatval($eurToTry);

$type = $supplier['price_rule_type_id'];
$costEUR = 0;
$breakdown = [];

if ($type == 1) {
    $costEUR += $reservation['passengers_adults'] * $supplier['cost_per_adult'];
    $costEUR += $reservation['passengers_children'] * $supplier['cost_per_child'];
    $breakdown['cost_per_adult'] = $supplier['cost_per_adult'];
    $breakdown['cost_per_child'] = $supplier['cost_per_child'];
} elseif ($type == 2) {
    $costEUR += floatval($supplier['cost_per_vehicle']);
    $breakdown['cost_per_vehicle'] = $supplier['cost_per_vehicle'];
} elseif ($type == 3) {
    $base = floatval($supplier['fixed_base_price']);
    $perMin = floatval($supplier['price_per_minute']);
    $dur = floatval($reservation['stopovers_duration']);
    $ext = floatval($reservation['extra_time']);
    $costEUR += $base + ($dur + $ext) * $perMin;
    $breakdown['base'] = $base;
    $breakdown['per_minute'] = $perMin;
    $breakdown['duration'] = $dur;
    $breakdown['extra_time'] = $ext;

    if (!empty($supplier['price_per_km_range']) && $reservation['total_distance']) {
        $kmRanges = json_decode($supplier['price_per_km_range'], true);
        $km = floatval($reservation['total_distance']);
        foreach ($kmRanges as $range => $val) {
            [$min, $max] = explode('-', $range);
            if ($km >= $min && $km <= $max) {
                $costEUR += $km * floatval($val);
                $breakdown['km'] = $km;
                $breakdown['km_price'] = $val;
                break;
            }
        }
    }
}

try {
    $reservationExtras = json_decode($reservation['extras'] ?? "[]", true);
    $supplierExtras = json_decode($supplier['extras_json'] ?? "{}", true);
    $extraBreakdown = [];

    foreach ($reservationExtras as $extra) {
        $id = $extra['extra_service_id'];
        $qty = $extra['extra_service_quantity'];
        $unit = floatval($supplierExtras[$id] ?? 0);
        $total = $qty * $unit;

        $extraBreakdown[] = [
            'extra_service_id' => $id,
            'quantity' => $qty,
            'unit_cost' => $unit,
            'total' => $total
        ];
        $costEUR += $total;
    }

    $breakdown['extras'] = $extraBreakdown;
} catch (Exception $e) {
    $breakdown['extras_error'] = $e->getMessage();
}

// Custom cost varsa override
$customCost = isset($_POST['custom_cost']) ? floatval(str_replace(',', '.', $_POST['custom_cost'])) : null;
if ($customCost !== null && $customCost > 0) {
    $costEUR = $customCost;
    $breakdown['custom_cost_override'] = true;
}

$gross = floatval($reservation['gross_price']);
$grossEUR = $reservation['currency'] === 'TRY' ? $gross / $eurToTry : $gross;
$profitEUR = $grossEUR - $costEUR;

// Reservations tablosunu güncelle
$updateStmt = $pdo->prepare("UPDATE reservations SET supplier_id = ? WHERE reservation_id = ?");
$updateStmt->execute([$supplier_id, $reservation_id]);

$reservationAfterUpdate = $reservation;
$reservationAfterUpdate['supplier_id'] = $supplier_id;

TriggerEngine::checkAndSend(
    $reservation_id,
    $reservation,
    $reservationAfterUpdate,
    false
);

// Log kaydı
$logStmt = $pdo->prepare("
    INSERT INTO supplier_assignment_logs (
        reservation_id, supplier_id, price_rule_id, price_rule_type_id,
        currency, eur_to_try, gross_price_original, gross_price_eur,
        supplier_cost, supplier_cost_eur, profit_eur, cost_breakdown_json
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$logStmt->execute([
    $reservation_id,
    $supplier_id,
    $priceRule['id'],
    $supplier['price_rule_type_id'],
    $reservation['currency'],
    $eurToTry,
    $gross,
    $grossEUR,
    $costEUR,  // artık bu direkt EUR
    $costEUR,
    $profitEUR,
    json_encode($breakdown)
]);

// POST ile bir geri dönüş URL'si gönderildiyse onu kullan, gönderilmediyse varsayılan olarak eski sayfaya yönlendir.
$return_to = $_POST['return_url'] ?? 'pending_reservations.php';

// Başarı parametresini ekleyerek dinamik URL'ye yönlendir.
header("Location: " . $return_to . "?assign_success=1");
exit;
