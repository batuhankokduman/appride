<?php
require_once 'functions/db.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';
require_once 'includes/menu.php';

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['new_service_name'])) {
        $stmt = $pdo->prepare("INSERT INTO extras (service_name) VALUES (?)");
        $stmt->execute([trim($_POST['new_service_name'])]);
    }

    if (!empty($_POST['edit_id']) && isset($_POST['edit_service_name'])) {
        $stmt = $pdo->prepare("UPDATE extras SET service_name = ? WHERE id = ?");
        $stmt->execute([trim($_POST['edit_service_name']), $_POST['edit_id']]);
    }

    if (!empty($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM extras WHERE id = ?");
        $stmt->execute([$_POST['delete_id']]);
    }
}

$extras = $pdo->query("SELECT * FROM extras ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
table.extras-table {
    min-width: 0px !important;
}
.popup-container {
    max-width: 900px;
    width: 800px;
    margin: 40px auto;
    background: #fff;
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}
.extras-header {
    font-size: 22px;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.extras-header button {
    background: #198754;
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
}
table.extras-table {
    width: 100%;
    border-collapse: collapse;
}
table.extras-table th, table.extras-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
}
.edit-btn, .delete-btn {
    padding: 6px 12px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    color: white;
    font-size: 13px;
}
.edit-btn { background: #0d6efd; margin-right: 5px; }
.delete-btn { background: #dc3545; }
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.5);
}
.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 30px;
    border-radius: 12px;
    width: 400px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
}
.modal-content input[type=text] {
    width: 100%;
    padding: 10px;
    margin-bottom: 20px;
    border-radius: 8px;
    border: 1px solid #ccc;
}
.modal-content button {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    background-color: #198754;
    color: white;
    cursor: pointer;
}
</style>

<div class="popup-container">
    <div class="extras-header">
        <span><i class="fa-solid fa-sliders"></i> Ekstra Hizmetler</span>
        <button onclick="openNewModal()">Yeni Ekle</button>
    </div>

    <table class="extras-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Hizmet Adı</th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($extras as $extra): ?>
                <tr>
                    <td><?= $extra['id'] ?></td>
                    <td><?= htmlspecialchars($extra['service_name']) ?></td>
                    <td>
                        <button class="edit-btn" onclick="openEditModal(<?= $extra['id'] ?>, '<?= htmlspecialchars($extra['service_name'], ENT_QUOTES) ?>')">
                            Düzenle
                        </button>
                        <form method="POST" style="display:inline-block" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                            <input type="hidden" name="delete_id" value="<?= $extra['id'] ?>">
                            <button type="submit" class="delete-btn"> Sil</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Yeni Ekle Modal -->
<div class="modal" id="newModal">
    <div class="modal-content">
        <form method="POST">
            <h3>Yeni Ekstra Hizmet</h3>
            <input type="text" name="new_service_name" placeholder="Hizmet adı girin" required>
            <button type="submit">Kaydet</button>
        </form>
    </div>
</div>

<!-- Düzenle Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <form method="POST">
            <h3>Hizmeti Düzenle</h3>
            <input type="hidden" name="edit_id" id="edit_id">
            <input type="text" name="edit_service_name" id="edit_service_name" required>
            <button type="submit">Güncelle</button>
        </form>
    </div>
</div>

<script>
function openNewModal() {
    document.getElementById('newModal').style.display = 'block';
}
function openEditModal(id, name) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_service_name').value = name;
    document.getElementById('editModal').style.display = 'block';
}
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>