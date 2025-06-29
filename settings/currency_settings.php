<?php
require_once '../functions/db.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../includes/menu.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_rate = floatval(str_replace(',', '.', $_POST['eur_to_try']));
    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value)
        VALUES ('eur_to_try', ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$new_rate]);
    $message = "Kur başarıyla güncellendi ✅";
}

$rate = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'eur_to_try'")->fetchColumn();
?>

<style>
.currency-settings-form {
    max-width: 400px;
    margin: 30px auto;
    padding: 30px;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 0 10px rgba(0,0,0,0.08);
    font-family: 'Segoe UI', sans-serif;
}

.currency-settings-form h2 {
    margin-bottom: 20px;
    font-size: 24px;
    color: #333;
    text-align: center;
}

.currency-settings-form label {
    font-weight: 600;
    display: block;
    margin-bottom: 10px;
    color: #444;
}

.currency-settings-form input[type="text"] {
    width: 100%;
    padding: 12px;
    font-size: 16px;
    border: 1px solid #ccc;
    border-radius: 8px;
    margin-bottom: 20px;
    transition: 0.3s;
}

.currency-settings-form input[type="text"]:focus {
    border-color: #007bff;
    outline: none;
    box-shadow: 0 0 4px rgba(0, 123, 255, 0.4);
}

.currency-settings-form button {
    background: #007bff;
    color: white;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    width: 100%;
    transition: 0.3s;
}

.currency-settings-form button:hover {
    background: #0056b3;
}

.currency-settings-form .success-message {
    text-align: center;
    color: green;
    font-weight: 600;
    margin-bottom: 20px;
}
</style>

<div class="currency-settings-form">
    <h2>💱 Kur Ayarları</h2>

    <?php if (!empty($message)): ?>
        <div class="success-message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>1 EUR kaç TL olsun?</label>
        <input type="text" name="eur_to_try" value="<?= htmlspecialchars($rate) ?>" required>
        <button type="submit">Kaydet</button>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
