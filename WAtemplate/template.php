<?php
session_start();
require_once '../functions/db.php';
require_once '../includes/header.php';
require_once '../includes/menu.php';

// === Toplu İşlem İşleyicisi ===
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    !empty($_POST['bulk_action']) &&
    !empty($_POST['selected']) &&
    is_array($_POST['selected'])
) {
    $action = $_POST['bulk_action'];
    $ids = array_map('intval', $_POST['selected']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $success = false;

    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM wa_templates WHERE id IN ($placeholders)");
        $success = $stmt->execute($ids);
        $msg = 'Seçilen şablonlar silindi!';
    } elseif ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE wa_templates SET is_active = 1 WHERE id IN ($placeholders)");
        $success = $stmt->execute($ids);
        $msg = 'Seçilen şablonlar aktif edildi!';
    } elseif ($action === 'deactivate') {
        $stmt = $pdo->prepare("UPDATE wa_templates SET is_active = 0 WHERE id IN ($placeholders)");
        $success = $stmt->execute($ids);
        $msg = 'Seçilen şablonlar pasif edildi!';
    }

    if ($success) {
        $_SESSION['success_message'] = $msg;
    } else {
        $_SESSION['error_message'] = 'Toplu işlem gerçekleştirilemedi.';
    }
    header('Location: template.php');
    exit;
}

// === Arama ve Sıralama ===
$search = $_GET['search'] ?? '';
$order  = $_GET['order'] ?? 'title_asc';
$params = [];
$sql = "SELECT * FROM wa_templates";

if ($search !== '') {
    $sql .= " WHERE template_title LIKE :search";
    $params['search'] = '%' . $search . '%';
}

if ($order === 'title_desc') {
    $sql .= " ORDER BY template_title DESC";
} else {
    $sql .= " ORDER BY template_title ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
?>

<div class="content">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="flash-message flash-success">
            <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php elseif (isset($_SESSION['error_message'])): ?>
        <div class="flash-message flash-error">
            <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h2>Şablonlar</h2>
        <p class="subtitle">Kayıtlı WhatsApp mesaj şablonlarını aşağıda görebilirsiniz.</p>
        <br>
        <a href="add.php" class="btn btn-success">Yeni Şablon Ekle</a>
        <br>
    </div>

    <form method="GET" class="search-form" style="margin-bottom:20px;">
        <input type="text" name="search" placeholder="Başlık ara..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn-search">Ara</button>
    </form>

    <form method="POST" id="bulk-form">
    <div class="bulk-actions">
        <select name="bulk_action">
            <option value="">Toplu İşlem</option>
            <option value="activate">Aktif Et</option>
            <option value="deactivate">Deaktif Et</option>
            <option value="delete">Sil</option>
        </select>
        <button type="submit" onclick="return confirmBulk();">Uygula</button>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>ID</th>
                <th>
                    <?php
                        $orderParam = $order === 'title_asc' ? 'title_desc' : 'title_asc';
                        $query = array_merge($_GET, ['order' => $orderParam]);
                    ?>
                    <a href="?<?= http_build_query($query) ?>">Başlık
                        <?php if ($order === 'title_asc'): ?>▲<?php elseif ($order === 'title_desc'): ?>▼<?php endif; ?>
                    </a>
                </th>
                <th>Tetikleyici Alanlar</th>
                <th>Aktif</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                <tr>
                    <td><input type="checkbox" name="selected[]" value="<?= $row['id'] ?>"></td>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['template_title']) ?></td>
                    <td><?= htmlspecialchars($row['trigger_fields']) ?></td>
                    <td><?= $row['is_active'] ? '✅ Evet' : '❌ Hayır' ?></td>
                    <td>
                        <a href="clone_template.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Kopyala</a>
                        <a href="edit_template.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Düzenle</a>
                        <a href="delete_template.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bu şablonu silmek istediğinizden emin misiniz?');">Sil</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </form>
</div>

<script>
document.getElementById('select-all').addEventListener('change', function() {
    const boxes = document.querySelectorAll('input[name="selected[]"]');
    boxes.forEach(cb => cb.checked = this.checked);
});
function confirmBulk() {
    const val = document.querySelector('select[name="bulk_action"]').value;
    if (!val) {
        alert('Lütfen bir işlem seçin.');
        return false;
    }
    if (val === 'delete') {
        return confirm('Seçilen şablonları silmek istediğinize emin misiniz?');
    }
    return true;
}
</script>

<?php require_once '../includes/footer.php'; ?>