<?php
require_once 'functions/db.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';
require_once 'includes/menu.php';

$success_message = '';

// Var olan tum extras kayitlarini cek
$extra_services = $pdo->query("SELECT id, service_name FROM extras ORDER BY service_name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_id           = $_POST['vehicle_id'] ?? '';
    $vehicle_name         = $_POST['vehicle_name'] ?? '';
    $vehicle_photo_url    = $_POST['vehicle_photo_url'] ?? '';
    $vehicle_model        = $_POST['vehicle_model'] ?? '';
    $vehicle_type         = $_POST['vehicle_type'] ?? '';
    $vehicle_passenger    = $_POST['vehicle_passenger'] ?? null;
    $vehicle_luggage      = $_POST['vehicle_luggage'] ?? null;

    $vehicle_features     = json_encode(array_map('trim', explode(',', $_POST['vehicle_features'] ?? '')));
    $vehicle_advantages   = json_encode(array_map('trim', explode(',', $_POST['vehicle_advantages'] ?? '')));
    $vehicle_restrictions = json_encode(array_map('trim', explode(',', $_POST['vehicle_restrictions'] ?? '')));

    $vehicle_extras = isset($_POST['vehicle_extras']) ? array_map('intval', $_POST['vehicle_extras']) : [];
    $vehicle_extras_json = json_encode($vehicle_extras);

    $stmt = $pdo->prepare("INSERT INTO vehicles (
        vehicle_id, vehicle_name, vehicle_photo_url, vehicle_model, vehicle_type,
        vehicle_passenger, vehicle_luggage, vehicle_features,
        vehicle_advantages, vehicle_restrictions, vehicle_extras
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $vehicle_id, $vehicle_name, $vehicle_photo_url, $vehicle_model, $vehicle_type,
        $vehicle_passenger, $vehicle_luggage, $vehicle_features,
        $vehicle_advantages, $vehicle_restrictions, $vehicle_extras_json
    ]);

    $success_message = '✅ Araç başarıyla eklendi.';
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

<style>
    .template-form {
        max-width: 700px;
        margin: auto;
        padding: 30px;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 0 25px rgba(0,0,0,0.07);
    }
    .form-group { margin-bottom: 20px; }
    .form-group label { font-weight: 600; display: block; margin-bottom: 6px; color: #333; }
    .form-group input, .form-group textarea, .form-group select {
        width: 100%; padding: 10px 14px; border: 1px solid #ccc; border-radius: 10px;
    }
    .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
        border-color: #007bff; outline: none;
    }
    .form-group .btn { padding: 10px 20px; border-radius: 10px; }
    #photo_preview img { border-radius: 10px; max-height: 100px; border: 1px solid #ccc; padding: 5px; }

    .extras-columns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 20px;
    }
    .extras-columns label {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
    }

</style>

<div class="content">
    <h2>🚗 Yeni Araç Ekle</h2>

    <?php if (!empty($success_message)): ?>
        <div class="flash-success"><?= $success_message ?></div>
    <?php endif; ?>

    <form method="POST" class="template-form">
        <div class="form-group">
            <label for="vehicle_id"><i class="fa-solid fa-barcode"></i> Araç Kodu</label>
            <input type="text" name="vehicle_id" id="vehicle_id" required>
        </div>

        <div class="form-group">
            <label for="vehicle_name"><i class="fa-solid fa-car-side"></i> Araç Adı</label>
            <input type="text" name="vehicle_name" id="vehicle_name" required>
        </div>

        <div class="form-group">
            <label for="vehicle_photo_url"><i class="fa-solid fa-image"></i> Araç Fotoğrafı</label>
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="text" name="vehicle_photo_url" id="vehicle_photo_url" readonly>
                <input type="file" id="upload_photo" accept="image/*" style="display: none;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('upload_photo').click();">
                    <i class="fa-solid fa-upload"></i> Fotoğraf Yükle
                </button>
            </div>
            <div id="photo_preview" style="margin-top:10px;"></div>
        </div>

        <div class="form-group">
            <label for="vehicle_model"><i class="fa-solid fa-tags"></i> Marka - Model</label>
            <input type="text" name="vehicle_model" id="vehicle_model">
        </div>

        <div class="form-group">
            <label for="vehicle_type"><i class="fa-solid fa-car"></i> Tip</label>
            <input type="text" name="vehicle_type" id="vehicle_type">
        </div>

        <div class="form-group">
            <label for="vehicle_passenger"><i class="fa-solid fa-user-group"></i> Yolcu Kapasitesi</label>
            <input type="number" name="vehicle_passenger" id="vehicle_passenger" min="1">
        </div>

        <div class="form-group">
            <label for="vehicle_luggage"><i class="fa-solid fa-suitcase-rolling"></i> Bagaj Kapasitesi</label>
            <input type="number" name="vehicle_luggage" id="vehicle_luggage" min="0">
        </div>

        <div class="form-group">
            <label for="vehicle_features"><i class="fa-solid fa-star"></i> Özellikler (virgülle ayır)</label>
            <textarea name="vehicle_features" id="vehicle_features" rows="2"></textarea>
        </div>

        <div class="form-group">
            <label for="vehicle_advantages"><i class="fa-solid fa-thumbs-up"></i> Avantajlar (virgülle ayır)</label>
            <textarea name="vehicle_advantages" id="vehicle_advantages" rows="2"></textarea>
        </div>

        <div class="form-group">
            <label for="vehicle_restrictions"><i class="fa-solid fa-ban"></i> Kısıtlamalar (virgülle ayır)</label>
            <textarea name="vehicle_restrictions" id="vehicle_restrictions" rows="2"></textarea>
        </div>

        <div class="form-group">
            <label><i class="fa-solid fa-plus"></i> Ekstra Hizmetler (var olanlardan seç)</label>
            <div class="extras-columns">
                <?php foreach ($extra_services as $extra): ?>
                    <label>
                        <input type="checkbox" name="vehicle_extras[]" value="<?= $extra['id'] ?>">
                        <?= htmlspecialchars($extra['service_name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Kaydet
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('upload_photo').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('photo', file);

    fetch('upload_photo.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('vehicle_photo_url').value = data.url;
            document.getElementById('photo_preview').innerHTML = `
                <img src="${data.url}" alt="Önizleme">
            `;
        } else {
            alert('Yükleme başarısız: ' + data.message);
        }
    })
    .catch(err => alert('Bir hata oluştu: ' + err));
});
</script>

<?php require_once 'includes/footer.php'; ?>