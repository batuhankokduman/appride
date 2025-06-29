<?php
// auth.php
session_start();

// Giriş yapılmamışsa login sayfasına yönlendir
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

// Admin yetkisi yoksa erişimi engelle
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    die('❌ Yetkisiz erişim!');
}
