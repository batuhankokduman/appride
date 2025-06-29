<?php
require_once 'functions/db.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';
require_once 'includes/menu.php';

$supplier_id = $_GET['supplier_id'] ?? null;
if (!$supplier_id || !is_numeric($supplier_id)) {
    echo "<div class='content'><p class='flash-error'>Geçersiz tedarikçi ID.</p></div>";
    exit;
}

$stmt = $pdo->prepare("SELECT full_name FROM suppliers WHERE id = ?");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    echo "<div class='content'><p class='flash-error'>Tedarikçi bulunamadı.</p></div>";
    exit;
}
?>

<style>
.wallet-form {
    max-width: 500px;
    background: #fff;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    margin: 30px auto;
    font-family: 'Segoe UI', sans-serif;
}
.wallet-form h2 {
    margin-bottom: 20px;
    font-size: 22px;
    color: #1e3a5f;
}
.wallet-form label {
    display: block;
    margin: 12px 0 6px;
    font-weight: bold;
    color: #333;
}
.wallet-form select,
.wallet-form input[type="number"],
.wallet-form textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    background: #f9f9f9;
    font-size: 14px;
}
.wallet-form button {
    margin-top: 20px;
    background-color: #28a745;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    transition: background-color 0.3s;
}
.wallet-form button:hover {
    background-color: #218838;
}
</style>

<div class="content">
    <form action="supplier_wallet_save.php" method="POST" class="wallet-form">
        <h2>💳 Cari Hareket Ekle: <?= htmlspecialchars($supplier['full_name']) ?></h2>

        <input type="hidden" name="supplier_id" value="<?= (int)$supplier_id ?>">

        <label for="type">İşlem Türü:</label>
        <select name="transaction_type" id="type" required>
            <option value="credit">💰 Ödeme Aldık (Tedarikçi Borçlu)</option>
            <option value="debit">💸 Ödeme Yaptık (Biz Borçlandık)</option>
        </select>

        <label for="amount">Tutar (EUR):</label>
        <input type="number" step="0.01" name="amount" id="amount" placeholder="0.00" required>

        <label for="note">Not (opsiyonel):</label>
        <textarea name="note" id="note" rows="3" placeholder="Açıklama ekleyin..."></textarea>

        <button type="submit">💾 Kaydet</button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>