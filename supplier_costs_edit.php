<?php
require_once 'functions/db.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';
require_once 'includes/menu.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Geçersiz ID");
}

$stmt = $pdo->prepare("SELECT * FROM supplier_costs WHERE id = ?");
$stmt->execute([$id]);
$cost = $stmt->fetch();
if (!$cost) {
    die("Kayıt bulunamadı");
}

$suppliers = $pdo->query("SELECT id, full_name FROM suppliers WHERE status = 1 ORDER BY full_name ASC")->fetchAll();
$rules = $pdo->query("SELECT id, rule_name, price_rule_type_id FROM price_rules ORDER BY rule_name ASC")->fetchAll();
?>

<div class="content">
    <h2 class="mb-4">✏️ Tedarikçi Maliyeti Düzenle</h2>

    <form action="supplier_costs_save.php" method="POST" class="form-container">
        <input type="hidden" name="id" value="<?= $cost['id'] ?>">
        <div class="form-group">
            <label for="supplier_id">Tedarikçi:</label>
            <select name="supplier_id" id="supplier_id" required>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $cost['supplier_id'] == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="price_rule_id">Fiyat Kuralı:</label>
            <select name="price_rule_id" id="price_rule_select" required>
                <?php foreach ($rules as $r): ?>
                    <option value="<?= $r['id'] ?>" data-type="<?= $r['price_rule_type_id'] ?>" <?= $cost['price_rule_id'] == $r['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['rule_name']) ?> (Tip <?= $r['price_rule_type_id'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="type-1-fields" class="price-type-fields form-group" style="display:none;">
            <label>Yetişkin Başına Maliyet: <input type="number" step="0.01" name="cost_per_adult" value="<?= $cost['cost_per_adult'] ?>"></label>
            <label>Çocuk Başına Maliyet: <input type="number" step="0.01" name="cost_per_child" value="<?= $cost['cost_per_child'] ?>"></label>
        </div>

        <div id="type-2-fields" class="price-type-fields form-group" style="display:none;">
            <label>Araç Başına Maliyet: <input type="number" step="0.01" name="cost_per_vehicle" value="<?= $cost['cost_per_vehicle'] ?>"></label>
        </div>

        <div id="type-3-fields" class="price-type-fields form-group" style="display:none;">
            <label>Sabit Açılış Ücreti: <input type="number" step="0.01" name="fixed_base_price" value="<?= $cost['fixed_base_price'] ?>"></label>
            <label>KM Aralığına Göre Ücret (JSON): <textarea name="price_per_km_range" rows="3"><?= htmlspecialchars($cost['price_per_km_range']) ?></textarea></label>
            <label>Dakika Başına Durak Ücreti: <input type="number" step="0.01" name="price_per_minute" value="<?= $cost['price_per_minute'] ?>"></label>
            <label>Saat Başına Ekstra Süre Ücreti: <input type="number" step="0.01" name="price_per_hour" value="<?= $cost['price_per_hour'] ?>"></label>
        </div>

        <div class="form-group">
            <label>Geçerlilik Başlangıç Tarihi: <input type="date" name="valid_from" value="<?= $cost['valid_from'] ?>" required></label>
            <label>Geçerlilik Bitiş Tarihi: <input type="date" name="valid_to" value="<?= $cost['valid_to'] ?>"></label>
        </div>

        <button type="submit" class="btn btn-primary">💾 Güncelle</button>
    </form>
</div>

<script>
function togglePriceTypeFields(type) {
    document.querySelectorAll('.price-type-fields').forEach(e => e.style.display = 'none');
    if (type === '1') {
        document.getElementById('type-1-fields').style.display = 'flex';
    } else if (type === '2') {
        document.getElementById('type-2-fields').style.display = 'flex';
    } else if (type === '3') {
        document.getElementById('type-3-fields').style.display = 'flex';
    }
}

const select = document.getElementById('price_rule_select');
const selectedOption = select.options[select.selectedIndex];
togglePriceTypeFields(selectedOption.getAttribute('data-type'));

select.addEventListener('change', function () {
    const selected = this.options[this.selectedIndex];
    togglePriceTypeFields(selected.getAttribute('data-type'));
});
</script>

<?php require_once 'includes/footer.php'; ?>