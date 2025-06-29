<?php
session_start();
require_once 'functions/db.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['is_admin'] = $user['is_admin'];
    header('Location: /WAtemplate/template.php');
    exit;
} else {
    $_SESSION['error'] = 'Kullanıcı adı veya şifre hatalı!';
    header('Location: login.php');
    exit;
}
