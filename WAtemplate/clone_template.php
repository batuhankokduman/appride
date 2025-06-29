<?php
session_start();
require_once '../functions/db.php';

if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "Geçersiz şablon ID'si.";
    header('Location: wa_templates.php');
    exit;
}

$id = intval($_GET['id']);

// Orijinal veriyi al
$stmt = $pdo->prepare("SELECT * FROM wa_templates WHERE id = ?");
$stmt->execute([$id]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    $_SESSION['error_message'] = "Şablon bulunamadı.";
    header('Location: wa_templates.php');
    exit;
}

// 'id' alanını çıkar (otomatik artan)
unset($template['id']);

// Başlığa (kopya) ekle
if (isset($template['template_title'])) {
    $template['template_title'] .= ' (kopya)';
}

// Dinamik olarak alan isimlerini ve değerleri oluştur
$columns = array_keys($template);
$placeholders = array_map(fn($col) => ":$col", $columns);
$sql = "INSERT INTO wa_templates (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
$insert = $pdo->prepare($sql);

// Execute et
$insert->execute($template);

$_SESSION['success_message'] = "Şablon başarıyla kopyalandı.";
header('Location: template.php');
exit;
?>
