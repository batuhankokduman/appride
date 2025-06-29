<?php
$response = ['success' => false, 'message' => '', 'url' => ''];

$uploadDir = 'uploads/'; // klasör var mı kontrol et
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if (isset($_FILES['photo'])) {
    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('vehicle_', true) . '.' . strtolower($ext);
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
        $response['success'] = true;
        $response['url'] = $targetPath;
    } else {
        $response['message'] = 'Dosya yüklenemedi.';
    }
} else {
    $response['message'] = 'Dosya alınamadı.';
}

header('Content-Type: application/json');
echo json_encode($response);
