<?php
require_once 'functions/db.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';
require_once 'includes/menu.php';

$vehicle_id = $_GET['vehicle_id'] ?? '';
if (empty($vehicle_id)) {
    die('Araç ID bulunamadı.');
}

$extra_services = $pdo->query("SELECT id, service_name FROM extras ORDER BY service_name")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ?");
$stmt->execute([$vehicle_id]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$vehicle) {
    die('Araç bulunamadı.');
}

$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $stmt = $pdo->prepare("UPDATE vehicles SET
        vehicle_name = ?, vehicle_photo_url = ?, vehicle_model = ?, vehicle_type = ?,
        vehicle_passenger = ?, vehicle_luggage = ?, vehicle_features = ?, 
        vehicle_advantages = ?, vehicle_restrictions = ?, vehicle_extras = ?
        WHERE vehicle_id = ?");

    $stmt->execute([
        $vehicle_name, $vehicle_photo_url, $vehicle_model, $vehicle_type,
        $vehicle_passenger, $vehicle_luggage, $vehicle_features,
        $vehicle_advantages, $vehicle_restrictions, $vehicle_extras_json,
        $vehicle_id
    ]);

    $success_message = '✅ Araç güncellendi.';
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_id = ?");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
}

$selected_extras = json_decode($vehicle['vehicle_extras'] ?? '[]', true);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" />
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
    .form-group .btn { padding: 10px 20px; border-radius: 10px; }
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
    <h2>✏️ Araç Düzenle</h2>

    <?php if (!empty($success_message)): ?>
        <div class="flash-success"><?= $success_message ?></div>
    <?php endif; ?>

    <form method="POST" class="template-form">
        <div class="form-group">
            <label for="vehicle_name">Araç Adı</label>
            <input type="text" name="vehicle_name" id="vehicle_name" value="<?= htmlspecialchars($vehicle['vehicle_name']) ?>" required>
        </div>

        <div class="form-group">
            <label for="vehicle_photo_url">Fotoğraf URL</label>
            <input type="text" name="vehicle_photo_url" id="vehicle_photo_url" value="<?= htmlspecialchars($vehicle['vehicle_photo_url']) ?>">
        </div>

        <div class="form-group">
            <label for="vehicle_model">Marka - Model</label>
            <input type="text" name="vehicle_model" id="vehicle_model" value="<?= htmlspecialchars($vehicle['vehicle_model']) ?>">
        </div>

        <div class="form-group">
            <label for="vehicle_type">Tip</label>
            <input type="text" name="vehicle_type" id="vehicle_type" value="<?= htmlspecialchars($vehicle['vehicle_type']) ?>">
        </div>

        <div class="form-group">
            <label for="vehicle_passenger">Yolcu Kapasitesi</label>
            <input type="number" name="vehicle_passenger" id="vehicle_passenger" value="<?= htmlspecialchars($vehicle['vehicle_passenger']) ?>">
        </div>

        <div class="form-group">
            <label for="vehicle_luggage">Bagaj Kapasitesi</label>
            <input type="number" name="vehicle_luggage" id="vehicle_luggage" value="<?= htmlspecialchars($vehicle['vehicle_luggage']) ?>">
        </div>

        <div class="form-group">
            <label for="vehicle_features">Özellikler (virgülle ayır)</label>
            <textarea name="vehicle_features" id="vehicle_features" rows="2"><?= htmlspecialchars(implode(', ', json_decode($vehicle['vehicle_features'] ?? '[]'))) ?></textarea>
        </div>

        <div class="form-group">
            <label for="vehicle_advantages">Avantajlar (virgülle ayır)</label>
            <textarea name="vehicle_advantages" id="vehicle_advantages" rows="2"><?= htmlspecialchars(implode(', ', json_decode($vehicle['vehicle_advantages'] ?? '[]'))) ?></textarea>
        </div>

        <div class="form-group">
            <label for="vehicle_restrictions">Kısıtlamalar (virgülle ayır)</label>
            <textarea name="vehicle_restrictions" id="vehicle_restrictions" rows="2"><?= htmlspecialchars(implode(', ', json_decode($vehicle['vehicle_restrictions'] ?? '[]'))) ?></textarea>
        </div>

        <div class="form-group">
            <label>Ekstra Hizmetler</label>
            <div class="extras-columns">
                <?php foreach ($extra_services as $extra): ?>
                    <label>
                        <input type="checkbox" name="vehicle_extras[]" value="<?= $extra['id'] ?>" <?= in_array($extra['id'], $selected_extras) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($extra['service_name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-save"></i> Güncelle
            </button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>