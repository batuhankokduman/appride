<?php
require_once 'functions/db.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';
require_once 'includes/menu.php';

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "<div class='content'><p class='flash-error'>Geçersiz tedarikçi ID.</p></div>";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$id]);
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supplier) {
    echo "<div class='content'><p class='flash-error'>Tedarikçi bulunamadı.</p></div>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier['full_name'] = $_POST['full_name'] ?? $supplier['full_name'];
    $supplier['company_name'] = $_POST['company_name'] ?? $supplier['company_name'];
    $supplier['phone_number'] = $_POST['phone_number'] ?? $supplier['phone_number'];
    $supplier['email'] = $_POST['email'] ?? $supplier['email'];
    $supplier['language'] = $_POST['language'] ?? $supplier['language'];
    $supplier['currency'] = $_POST['currency'] ?? $supplier['currency'];
    $supplier['status'] = isset($_POST['status']) ? 1 : 0;

    $update = $pdo->prepare("UPDATE suppliers SET full_name = ?, company_name = ?, phone_number = ?, email = ?, language = ?, currency = ?, status = ? WHERE id = ?");
    $update->execute([
        $supplier['full_name'],
        $supplier['company_name'],
        $supplier['phone_number'],
        $supplier['email'],
        $supplier['language'],
        $supplier['currency'],
        $supplier['status'],
        $id
    ]);

    echo "<div class='content'><p class='flash-success'>✅ Tedarikçi bilgileri güncellendi.</p></div>";
}
?>

<div class="content">
    <h2>✏️ Tedarikçi Düzenle</h2>

    <form method="POST" class="template-form" style="margin-bottom: 30px;">
        <div class="form-group">
            <label for="full_name">Ad Soyad</label>
            <input type="text" name="full_name" id="full_name" value="<?= htmlspecialchars($supplier['full_name']) ?>" required>
        </div>

        <div class="form-group">
            <label for="company_name">Firma Adı</label>
            <input type="text" name="company_name" id="company_name" value="<?= htmlspecialchars($supplier['company_name']) ?>">
        </div>

        <div class="form-group">
            <label for="phone_number">Telefon</label>
            <input type="text" name="phone_number" id="phone_number" value="<?= htmlspecialchars($supplier['phone_number']) ?>">
        </div>

        <div class="form-group">
            <label for="email">E-posta</label>
            <input type="email" name="email" id="email" value="<?= htmlspecialchars($supplier['email']) ?>">
        </div>

        <div class="form-group">
            <label for="language">Dil</label>
            <select name="language" id="language">
                <option value="tr" <?= $supplier['language'] === 'tr' ? 'selected' : '' ?>>Türkçe</option>
                <option value="en" <?= $supplier['language'] === 'en' ? 'selected' : '' ?>>İngilizce</option>
                <option value="de" <?= $supplier['language'] === 'de' ? 'selected' : '' ?>>Almanca</option>
            </select>
        </div>

        <div class="form-group">
            <label for="currency">Para Birimi</label>
            <select name="currency" id="currency">
                <option value="EUR" <?= $supplier['currency'] === 'EUR' ? 'selected' : '' ?>>Euro (EUR)</option>
                <option value="USD" <?= $supplier['currency'] === 'USD' ? 'selected' : '' ?>>Dolar (USD)</option>
                <option value="TRY" <?= $supplier['currency'] === 'TRY' ? 'selected' : '' ?>>Türk Lirası (TRY)</option>
            </select>
        </div>

        <div class="form-group">
            <label><input type="checkbox" name="status" value="1" <?= $supplier['status'] ? 'checked' : '' ?>> Aktif</label>
        </div>

<div class="form-group" style="display: flex; gap: 10px;">
    <button type="submit" class="btn btn-primary">💾 Kaydet</button>
</div>
</form> <!-- Kaydet formu burada biter -->

<!-- Silme işlemi ayrı form olmalı ve dışında tanımlanmalı -->
<form method="POST" action="supplier_delete.php" onsubmit="return confirm('Bu tedarikçiyi silmek istediğinize emin misiniz?');" style="margin-top: 10px;">
    <input type="hidden" name="id" value="<?= $supplier['id'] ?>">
    <button type="submit" class="btn btn-danger">🗑️ Tedarikçiyi Sil</button>
</form>

        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
