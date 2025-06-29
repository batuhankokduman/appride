<?php
require_once 'functions/db.php';
require_once 'config/stripe.php';

$id = $_GET['id'] ?? null;
$token = $_GET['t'] ?? null;
$sess = $_GET['session_id'] ?? null;

if (!$id || !$token || !$sess) die('Invalid request');

$session = \Stripe\Checkout\Session::retrieve($sess);
if ($session->payment_status !== 'paid') die('Payment incomplete');

$amount = $session->amount_total / 100;
$currency = strtoupper($session->currency);

$stmt = $pdo->prepare("SELECT reservation_id, gross_price, paid_amount FROM reservations WHERE reservation_id = ? AND access_token = ?");
$stmt->execute([$id, $token]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$res) die('not found');

$paid = (float) $res['paid_amount'] + $amount;
$remaining = max((float) $res['gross_price'] - $paid, 0);

$pdo->prepare("INSERT INTO reservation_payments (reservation_id, payment_date, payment_method, amount, currency, notes) VALUES (?, NOW(), 'Stripe', ?, ?, '')")
    ->execute([$id, $amount, $currency]);

$pdo->prepare("UPDATE reservations SET paid_amount = ?, remaining_amount = ?, payment_method = 'Stripe' WHERE reservation_id = ?")
    ->execute([$paid, $remaining, $id]);

header("Location: rez.php?id=$id&t=$token");
exit;
