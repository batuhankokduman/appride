<?php
require_once 'functions/db.php';
require_once 'includes/auth.php';

// Hataları görmek için aç (geliştirme sırasında)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Formdan gelen verileri al
$supplier_id = $_POST['supplier_id'] ?? null;
$transaction_type = $_POST['transaction_type'] ?? null;
$amount = $_POST['amount'] ?? null;
$note = $_POST['note'] ?? null;
echo $supplier_id .  $transaction_type . $amount .  $note;
if (!$supplier_id || !$transaction_type || !$amount || !is_numeric($amount)) {
    echo "<div class='content'><p class='flash-error'>Geçersiz giriş verisi.</p></div>";
    exit;
}

// Geçerli tür mü?
if (!in_array($transaction_type, ['credit', 'debit'])) {
    echo "<div class='content'><p class='flash-error'>Geçersiz işlem türü.</p></div>";
    exit;
}

// 'credit' → 'in', 'debit' → 'out' çevirisi
$type = $transaction_type === 'credit' ? 'in' : 'out';

// EUR cinsinden kayıt yapılıyor
$currency = 'EUR';

$stmt = $pdo->prepare("INSERT INTO supplier_transactions (supplier_id, type, amount, currency, note) VALUES (?, ?, ?, ?, ?)");
$success = $stmt->execute([$supplier_id, $type, $amount, $currency, $note]);

if ($success) {
    header("Location: supplier_wallet.php?supplier_id=$supplier_id&success=1");
    exit;
} else {
    echo "<div class='content'><p class='flash-error'>Kayıt sırasında bir hata oluştu.</p></div>";
    exit;
}
