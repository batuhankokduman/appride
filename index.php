<?php
session_start();
require_once 'includes/auth.php';

// Giriş yaptıysa dashboard'a yönlendir
header("Location: /dashboard.php");
exit;
