<?php
require_once '../functions/db.php';
require_once '../functions/trigger_engine.php';

header('Content-Type: application/json; charset=utf-8');

$reservation_id = $_POST['reservation_id'] ?? null;
$date = $_POST['date'] ?? null;
$time = $_POST['time'] ?? null;

if (!$reservation_id || !$date || !$time) {
    echo json_encode(['success' => false, 'message' => 'Eksik veri: reservation_id, date ve time gerekli.']);
    exit;
}

try {
    // 🔁 Eski veriyi çek
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ?");
    $stmt->execute([$reservation_id]);
    $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldData) {
        echo json_encode(['success' => false, 'message' => 'Rezervasyon bulunamadı.']);
        exit;
    }

    // 🔄 Yeni veriyle güncel yapıyı oluştur
    $newData = $oldData;
    $newData['schedule_pickup_date'] = $date;
    $newData['schedule_pickup_time'] = $time;

    // 🔄 Veritabanında güncelle
    $update = $pdo->prepare("UPDATE reservations SET schedule_pickup_date = ?, schedule_pickup_time = ? WHERE reservation_id = ?");
    $update->execute([$date, $time, $reservation_id]);

    // ✅ TriggerEngine'i tetikle
    TriggerEngine::checkAndSend((int)$reservation_id, $oldData, $newData);

    echo json_encode(['success' => true, 'message' => 'Pickup tarihi ve saati başarıyla güncellendi.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'İşlem sırasında hata oluştu: ' . $e->getMessage()]);
}
