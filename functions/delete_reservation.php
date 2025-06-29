<?php
require_once '../functions/db.php';

header('Content-Type: application/json; charset=utf-8');

$reservation_id = $_POST['reservation_id'] ?? null;

if (!$reservation_id || !is_numeric($reservation_id)) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz rezervasyon ID.']);
    exit;
}

try {
    // Önce rezervasyon verisini al
    $select = $pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ?");
    $select->execute([$reservation_id]);
    $reservation = $select->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        echo json_encode(['success' => false, 'message' => 'Rezervasyon bulunamadı.']);
        exit;
    }

    // Çöp kutusu tablosuna eklemek için trash_at alanını hazırla
    $reservation['trash_at'] = date('Y-m-d H:i:s');

    // Dinamik olarak alanları belirle
    $columns = array_keys($reservation);
    $placeholders = array_map(fn($c) => ":$c", $columns);
    $insertSql = "INSERT INTO reservations_trash (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
    $insert = $pdo->prepare($insertSql);
    $insert->execute($reservation);

    // Asıl rezervasyon kaydını sil
    $delete = $pdo->prepare("DELETE FROM reservations WHERE reservation_id = ?");
    $delete->execute([$reservation_id]);

    echo json_encode(['success' => true, 'message' => 'Rezervasyon başarıyla silindi.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Silme iişlemi sırasında hata oluştu.: ' . $e->getMessage()]);
}