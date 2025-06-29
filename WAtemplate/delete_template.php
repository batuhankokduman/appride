<?php
require_once '../functions/db.php';
require_once '../includes/header.php';
require_once '../includes/menu.php';

$template_id = $_GET['id'] ?? null;

if ($template_id) {
    // Şablonu veritabanından silme
    $stmt = $pdo->prepare("DELETE FROM wa_templates WHERE id = ?");
    $stmt->execute([$template_id]);

    $_SESSION['success_message'] = 'Şablon başarıyla silindi!';
} else {
    $_SESSION['error_message'] = 'Şablon bulunamadı!';
}

header('Location: template.php'); // Şablonlar sayfasına yönlendir
exit;
