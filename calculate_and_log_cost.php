<?php

function calculateAndLogCost($pdo, $reservation, $supplierData) {
    $reservation_id = $reservation['reservation_id'];
    $supplier_id = $supplierData['supplier_id'];
    $price_rule_id = $reservation['rule_id'];

    // 1. Fiyat kuralını çek
    $ruleStmt = $pdo->prepare("SELECT * FROM price_rules WHERE rule_id = ?");
    $ruleStmt->execute([$price_rule_id]);
    $priceRule = $ruleStmt->fetch();

    if (!$priceRule) {
        error_log("Fiyat kuralı bulunamadı. Reservation ID: " . $reservation_id);
        return false;
    }

    // 2. Kur bilgisini al
    $eurToTry = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'eur_to_try'")->fetchColumn();
    $eurToTry = floatval($eurToTry);

    // 3. Maliyet hesapla
    $type = $supplierData['price_rule_type_id'];
    $cost = 0;
    $breakdown = [];

    if ($type == 1) {
        $cost += $reservation['passengers_adults'] * $supplierData['cost_per_adult'];
        $cost += $reservation['passengers_children'] * $supplierData['cost_per_child'];
        $breakdown['cost_per_adult'] = $supplierData['cost_per_adult'];
        $breakdown['cost_per_child'] = $supplierData['cost_per_child'];
    } elseif ($type == 2) {
        $cost += floatval($supplierData['cost_per_vehicle']);
        $breakdown['cost_per_vehicle'] = $supplierData['cost_per_vehicle'];
    } elseif ($type == 3) {
        $base = floatval($supplierData['fixed_base_price']);
        $perMin = floatval($supplierData['price_per_minute']);
        $dur = floatval($reservation['stopovers_duration']);
        $ext = floatval($reservation['extra_time']);
        $cost += $base + ($dur + $ext) * $perMin;
        $breakdown['base'] = $base;
        $breakdown['per_minute'] = $perMin;
        $breakdown['duration'] = $dur;
        $breakdown['extra_time'] = $ext;

        // km-range maliyeti
        if (!empty($supplierData['price_per_km_range']) && $reservation['total_distance']) {
            $kmRanges = json_decode($supplierData['price_per_km_range'], true);
            $km = floatval($reservation['total_distance']);
            foreach ($kmRanges as $range => $val) {
                [$min, $max] = explode('-', $range);
                if ($km >= $min && $km <= $max) {
                    $cost += $km * floatval($val);
                    $breakdown['km'] = $km;
                    $breakdown['km_price'] = $val;
                    break;
                }
            }
        }
    }

    // 4. Extra maliyetleri
    try {
        $reservationExtras = json_decode($reservation['extras'] ?? "[]", true);
        $supplierExtras = json_decode($supplierData['extras_json'] ?? "{}", true);
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
            $cost += $total;
        }

        $breakdown['extras'] = $extraBreakdown;
    } catch (Exception $e) {
        $breakdown['extras_error'] = $e->getMessage();
    }

    // 5. Kar hesapla
    $gross = floatval($reservation['gross_price']);
    $grossEUR = $reservation['currency'] === 'TRY' ? $gross / $eurToTry : $gross;
    $costEUR = $reservation['currency'] === 'TRY' ? $cost / $eurToTry : $cost;
    $profitEUR = $grossEUR - $costEUR;

    // 6. Log tablosuna kaydet
    $logStmt = $pdo->prepare("
        INSERT INTO supplier_assignment_logs (
            reservation_id, supplier_id, price_rule_id, price_rule_type_id,
            currency, eur_to_try, gross_price_original, gross_price_eur,
            supplier_cost, supplier_cost_eur, profit_eur, cost_breakdown_json
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $success = $logStmt->execute([
        $reservation_id,
        $supplier_id,
        $priceRule['id'],
        $supplierData['price_rule_type_id'],
        $reservation['currency'],
        $eurToTry,
        $gross,
        $grossEUR,
        $cost,
        $costEUR,
        $profitEUR,
        json_encode($breakdown)
    ]);

    return $success;
}
function calculateOnlyCost($reservation, $supplierData, $eurToTry) {
    $type = $supplierData['price_rule_type_id'];
    $cost = 0;

    if ($type == 1) {
        $cost += $reservation['passengers_adults'] * $supplierData['cost_per_adult'];
        $cost += $reservation['passengers_children'] * $supplierData['cost_per_child'];
    } elseif ($type == 2) {
        $cost += floatval($supplierData['cost_per_vehicle']);
    } elseif ($type == 3) {
        $base = floatval($supplierData['fixed_base_price']);
        $perMin = floatval($supplierData['price_per_minute']);
        $dur = floatval($reservation['stopovers_duration']);
        $ext = floatval($reservation['extra_time']);
        $cost += $base + ($dur + $ext) * $perMin;

        if (!empty($supplierData['price_per_km_range']) && $reservation['total_distance']) {
            $kmRanges = json_decode($supplierData['price_per_km_range'], true);
            $km = floatval($reservation['total_distance']);
            foreach ($kmRanges as $range => $val) {
                [$min, $max] = explode('-', $range);
                if ($km >= $min && $km <= $max) {
                    $cost += $km * floatval($val);
                    break;
                }
            }
        }
    }

    try {
        $reservationExtras = json_decode($reservation['extras'] ?? "[]", true);
        $supplierExtras = json_decode($supplierData['extras_json'] ?? "{}", true);
        foreach ($reservationExtras as $extra) {
            $id = $extra['extra_service_id'];
            $qty = $extra['extra_service_quantity'];
            $unit = floatval($supplierExtras[$id] ?? 0);
            $cost += $qty * $unit;
        }
    } catch (Exception $e) {
        // ignore
    }

    return round($cost, 2);
}

?>