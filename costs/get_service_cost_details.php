<?php
require_once '../functions/db.php'; // Veritabanı bağlantı dosyanızın yolu
// require_once '../includes/auth.php'; // Gerekirse yetkilendirme ekleyin

header('Content-Type: application/json');

$price_rule_id = filter_input(INPUT_GET, 'price_rule_id', FILTER_VALIDATE_INT);

if (!$price_rule_id) {
    echo json_encode(['success' => false, 'message' => 'Fiyat Kuralı ID gerekli.']);
    exit;
}

try {
    // Fiyat kuralının tipini al
    $ruleTypeStmt = $pdo->prepare("SELECT price_rule_type_id FROM price_rules WHERE id = ?");
    $ruleTypeStmt->execute([$price_rule_id]);
    $ruleTypeResult = $ruleTypeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$ruleTypeResult) {
        echo json_encode(['success' => false, 'message' => 'Fiyat Kuralı bulunamadı.']);
        exit;
    }
    $price_rule_type_id = $ruleTypeResult['price_rule_type_id'];

    // Fiyat kuralına bağlı maliyet periyotlarını ve tedarikçi bilgilerini çek
    $stmt = $pdo->prepare("
        SELECT
            s.full_name AS supplier_name,
            scp.valid_from,
            scp.valid_to,
            scp.cost_per_adult,
            scp.cost_per_child,
            scp.cost_per_vehicle,
            scp.fixed_base_price,
            scp.price_per_km_range,
            scp.price_per_minute,
            scp.price_per_extra_minute,
            scp.extras_json
        FROM supplier_cost_periods scp
        JOIN suppliers s ON scp.supplier_id = s.id
        WHERE scp.price_rule_id = :price_rule_id
        ORDER BY s.full_name ASC, scp.valid_from DESC
    ");
    $stmt->execute([':price_rule_id' => $price_rule_id]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ekstra isimlerini almak için (extras_json içindeki ID'leri isimlerle eşleştirmek için)
    $extrasMasterListStmt = $pdo->query("SELECT extra_service_id, service_name FROM extras");
    $extrasMasterListRaw = $extrasMasterListStmt->fetchAll(PDO::FETCH_KEY_PAIR); // extra_service_id => service_name
    
    // JSON anahtarları string olmalı, bu yüzden extra_service_id'leri string'e çeviriyoruz
    $extrasMasterList = [];
    foreach($extrasMasterListRaw as $key => $value) {
        $extrasMasterList[(string)$key] = $value;
    }


    echo json_encode([
        'success' => true,
        'details' => $details,
        'price_rule_type_id' => $price_rule_type_id,
        'extras_master_list' => $extrasMasterList // Ekstra isimlerini de gönderiyoruz
    ]);

} catch (PDOException $e) {
    // Geliştirme aşamasında detaylı hata, canlıda daha genel bir mesaj veya loglama yapılabilir.
    error_log("Veritabanı Hatası (get_service_cost_details.php): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası oluştu. Detaylar için logları kontrol edin.']);
} catch (Exception $e) {
    error_log("Genel Hata (get_service_cost_details.php): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu. Detaylar için logları kontrol edin.']);
}
?>