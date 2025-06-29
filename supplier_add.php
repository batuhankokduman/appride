<?php
require_once 'functions/db.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';
require_once 'includes/menu.php';

$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name     = $_POST['full_name'] ?? '';
    $company_name  = $_POST['company_name'] ?? '';
    $phone_number  = $_POST['phone_number'] ?? '';
    $email         = $_POST['email'] ?? '';
    $language      = $_POST['language'] ?? 'tr';
    $currency      = $_POST['currency'] ?? 'EUR';
    $status        = isset($_POST['status']) ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO suppliers (full_name, company_name, phone_number, email, language, currency, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$full_name, $company_name, $phone_number, $email, $language, $currency, $status]);

    $success_message = '✅ Tedarikçi başarıyla eklendi.';
}
?>

<div class="content">
    <h2>➕ Yeni Tedarikçi Ekle</h2>

    <?php if (!empty($success_message)): ?>
        <div class="flash-success"><?= $success_message ?></div>
    <?php endif; ?>

    <form method="POST" class="template-form">
        <div class="form-group">
            <label for="full_name">Ad Soyad</label>
            <input type="text" name="full_name" id="full_name" required>
        </div>

        <div class="form-group">
            <label for="company_name">Firma Adı</label>
            <input type="text" name="company_name" id="company_name">
        </div>

        <div class="form-group">
            <label for="phone_number">Telefon</label>
            <input type="text" name="phone_number" id="phone_number">
        </div>

        <div class="form-group">
            <label for="email">E-posta</label>
            <input type="email" name="email" id="email">
        </div>

        <div class="form-group">
            <label for="language">Dil</label>
            <select name="language" id="language">
                <option value="tr" selected>Türkçe</option>
                <option value="en">İngilizce</option>
                <option value="de">Almanca</option>
            </select>
        </div>

        <div class="form-group">
            <label for="currency">Para Birimi</label>
            <select name="currency" id="currency">
                <option value="EUR" selected>Euro (EUR)</option>
                <option value="USD">Dolar (USD)</option>
                <option value="TRY">Türk Lirası (TRY)</option>
            </select>
        </div>

        <div class="form-group">
            <label><input type="checkbox" name="status" value="1" checked> Aktif</label>
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
