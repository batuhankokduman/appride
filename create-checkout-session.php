<?php
require_once 'functions/db.php';
require_once 'config/stripe.php';
header('Content-Type: application/json');

$id = $_GET['id'] ?? null;
$token = $_GET['t'] ?? null;

if (!$id || !$token) {
    http_response_code(400);
    echo json_encode(['error' => 'missing-params']);
    exit;
}

$stmt = $pdo->prepare("SELECT reservation_id, remaining_amount, currency, access_token FROM reservations WHERE reservation_id = ? AND access_token = ?");
$stmt->execute([$id, $token]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$res) {
    http_response_code(404);
    echo json_encode(['error' => 'not-found']);
    exit;
}

$amount = (float) $res['remaining_amount'];
if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'no-amount']);
    exit;
}

$session = \Stripe\Checkout\Session::create([
    'mode' => 'payment',
    'payment_method_types' => ['card'],
    'line_items' => [[
        'price_data' => [
            'currency' => strtolower($res['currency']),
            'product_data' => ['name' => 'Reservation '.$res['reservation_id']],
            'unit_amount' => (int) round($amount * 100),
        ],
        'quantity' => 1,
    ]],
    'success_url' => "https://".$_SERVER['HTTP_HOST']."/payment_success.php?session_id={CHECKOUT_SESSION_ID}&id=$id&t=$token",
    'cancel_url' => "https://".$_SERVER['HTTP_HOST']."/rez.php?id=$id&t=$token"
]);

echo json_encode(['id' => $session->id]);
