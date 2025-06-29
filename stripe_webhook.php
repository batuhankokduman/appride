<?php
require_once __DIR__ . '/vendor/autoload.php';
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/functions/db.php';
require_once __DIR__ . '/functions/log.php';

$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $config['stripe_webhook_secret']);
} catch (Throwable $e) {
    log_error('Webhook signature verification failed: ' . $e->getMessage());
    http_response_code(400);
    exit;
}

if ($event->type === 'checkout.session.completed') {
    $session = $event->data->object;
    $reservationId = $session->metadata->reservation_id ?? null;
    $token = $session->metadata->access_token ?? null;
    $amount = $session->amount_total / 100;
    $currency = strtoupper($session->currency);

    if ($reservationId && $token) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO reservation_payments (reservation_id, payment_date, payment_method, amount, currency, notes) VALUES (?, NOW(), ?, ?, ?, ?)');
            $stmt->execute([$reservationId, 'Stripe', $amount, $currency, 'Stripe Checkout']);

            $stmtSum = $pdo->prepare('SELECT SUM(amount) AS total_paid FROM reservation_payments WHERE reservation_id = ?');
            $stmtSum->execute([$reservationId]);
            $totalPaid = $stmtSum->fetchColumn();

            $stmtUpd = $pdo->prepare('UPDATE reservations SET paid_amount = ?, remaining_amount = GREATEST(gross_price - ?, 0) WHERE reservation_id = ? AND access_token = ?');
            $stmtUpd->execute([$totalPaid, $totalPaid, $reservationId, $token]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_error('Webhook DB error: ' . $e->getMessage());
            http_response_code(500);
            exit;
        }
    }
}

http_response_code(200);