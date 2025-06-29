<?php
require_once 'functions/db.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';
require_once 'includes/menu.php';

// Tedarikçi ve fiyat kurallarını çekiyoruz
$suppliers = $pdo->query("SELECT id, full_name FROM suppliers WHERE status = 1 ORDER BY full_name ASC")->fetchAll();
$rules = $pdo->query("SELECT id, rule_name, price_rule_type_id FROM price_rules ORDER BY rule_name ASC")->fetchAll();
?>

<div class="content">
    <h2 class="mb-4">➕ Yeni Tedarikçi Maliyeti Ekle</h2>

    <form action="supplier_costs_save.php" method="POST" class="form-container">
        <div class="form-group">
            <label for="supplier_id">Tedarikçi:</label>
            <select name="supplier_id" id="supplier_id" required>
                <option value="">Seçiniz</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="price_rule_id">Fiyat Kuralı:</label>
            <select name="price_rule_id" id="price_rule_select" required>
                <option value="">Seçiniz</option>
                <?php foreach ($rules as $r): ?>
                    <option value="<?= $r['id'] ?>" data-type="<?= $r['price_rule_type_id'] ?>">
                        <?= htmlspecialchars($r['rule_name']) ?> (Tip <?= $r['price_rule_type_id'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="type-1-fields" class="price-type-fields form-group" style="display:none;">
            <label>Yetişkin Başına Maliyet: <input type="number" step="0.01" name="cost_per_adult"></label>
            <label>Çocuk Başına Maliyet: <input type="number" step="0.01" name="cost_per_child"></label>
        </div>

        <div id="type-2-fields" class="price-type-fields form-group" style="display:none;">
            <label>Araç Başına Maliyet: <input type="number" step="0.01" name="cost_per_vehicle"></label>
        </div>

        <div id="type-3-fields" class="price-type-fields form-group" style="display:none;">
            <label>Sabit Açılış Ücreti: <input type="number" step="0.01" name="fixed_base_price"></label>
            <label>KM Aralığına Göre Ücret (JSON): <textarea name="price_per_km_range" rows="3" placeholder='{"0-100":5, "100-200":4}'></textarea></label>
            <label>Dakika Başına Durak Ücreti: <input type="number" step="0.01" name="price_per_minute"></label>
            <label>Saat Başına Ekstra Süre Ücreti: <input type="number" step="0.01" name="price_per_hour"></label>
        </div>

        <div class="form-group">
            <label>Geçerlilik Başlangıç Tarihi: <input type="date" name="valid_from" required></label>
            <label>Geçerlilik Bitiş Tarihi: <input type="date" name="valid_to"></label>
        </div>

        <button type="submit" class="btn btn-success mt-3">💾 Kaydet</button>
    </form>
</div>

<style>
.form-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 600px;
    padding: 20px;
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.form-group label {
    font-weight: 500;
    color: #333;
}
.form-group input,
.form-group select,
.form-group textarea {
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #ccc;
    width: 100%;
    font-size: 14px;
}
.btn-success {
    background-color: #28a745;
    color: #fff;
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}
.btn-success:hover {
    background-color: #218838;
}

/* Select2 stil ayarları */
.select2-container--default .select2-selection--single {
    height: 44px;
    border-radius: 8px;
    border: 1px solid #ccc;
    padding: 8px 10px;
    font-size: 14px;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #333;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 44px;
}
</style>

<!-- jQuery (Select2 için gerekli) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Select2 CSS ve JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function () {
    // Select2 başlat
    $('#price_rule_select').select2({
        placeholder: "Fiyat kuralı seçiniz",
        width: '100%',
        allowClear: true
    });

    // Tip alanlarını göster/gizle
    $('#price_rule_select').on('change', function () {
        $('.price-type-fields').hide();
        const selectedType = $(this).find(':selected').data('type');
        if (selectedType == 1) {
            $('#type-1-fields').css('display', 'flex');
        } else if (selectedType == 2) {
            $('#type-2-fields').css('display', 'flex');
        } else if (selectedType == 3) {
            $('#type-3-fields').css('display', 'flex');
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
