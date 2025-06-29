<?php
require_once 'functions/db.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';
require_once 'includes/menu.php';

$search = $_GET['search'] ?? '';
$where = "WHERE 1";
$params = [];

if (!empty($search)) {
    $where .= " AND (
        vehicle_name LIKE :s1 OR
        vehicle_model LIKE :s2 OR
        vehicle_type LIKE :s3
    )";
    $likeSearch = '%' . $search . '%';
    $params = [
        's1' => $likeSearch,
        's2' => $likeSearch,
        's3' => $likeSearch
    ];
}

$sql = "SELECT * FROM vehicles $where ORDER BY vehicle_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();

// Extras tablosunu ID ile eşleyip isimlerini kolayca göstermek için önceden alalım
$extras_stmt = $pdo->query("SELECT id, service_name FROM extras");
$extras_map = [];
foreach ($extras_stmt->fetchAll(PDO::FETCH_ASSOC) as $extra) {
    $extras_map[$extra['id']] = $extra['service_name'];
}
?>

<div class="content">
    <h2>🚗 Araçlar</h2>

    <form method="GET" action="?" class="search-form">
        <div class="search-wrapper">
            <input type="text" name="search" placeholder="Araç adı, model, tip..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">
                <i class="fa fa-search"></i>
                <span style="display: none;">Ara</span>
            </button>
        </div>
        <a href="vehicle_add.php" class="btn-add"><i class="fa fa-plus-circle"></i> Araç Ekle</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>İşlem</th>
                <th>Görsel</th>
                <th>Araç Kodu</th>
                <th>Ad</th>
                <th>Model</th>
                <th>Tip</th>
                <th>Yolcu</th>
                <th>Bagaj</th>
                <th>Ekstralar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vehicles as $v): ?>
                <tr>
                    <td>
                        <a href="vehicle_edit.php?vehicle_id=<?= urlencode($v['vehicle_id']) ?>" class="btn-edit">
                            <i class="fa-solid fa-pen-to-square"></i> Düzenle
                        </a>
                    </td>
                    <td>
                        <?php if (!empty($v['vehicle_photo_url'])): ?>
                            <img src="<?= htmlspecialchars($v['vehicle_photo_url']) ?>" alt="" style="width: 60px; height: auto; border-radius: 6px; border: 1px solid #ccc;">
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($v['vehicle_id']) ?></td>
                    <td><?= htmlspecialchars($v['vehicle_name']) ?></td>
                    <td><?= htmlspecialchars($v['vehicle_model']) ?></td>
                    <td><?= htmlspecialchars($v['vehicle_type']) ?></td>
                    <td><?= htmlspecialchars($v['vehicle_passenger']) ?></td>
                    <td><?= htmlspecialchars($v['vehicle_luggage']) ?></td>
                    <td>
                        <?php
                            $extra_ids = json_decode($v['vehicle_extras'] ?? '[]', true);
                            $names = array_map(fn($id) => $extras_map[$id] ?? '', $extra_ids);
                            echo htmlspecialchars(implode(', ', array_filter($names)));
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- Stil -->
<style>
.search-form {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    margin-bottom: 20px;
}
.search-wrapper {
    display: flex;
    align-items: center;
    border: 1px solid #ccc;
    border-radius: 8px;
    overflow: hidden;
    max-width: 350px;
    width: 100%;
}
.search-wrapper input {
    flex: 1;
    padding: 10px 14px;
    border: none;
    font-size: 14px;
    outline: none;
}
.search-wrapper button {
    background-color: #6c757d;
    color: white;
    padding: 10px 16px;
    border: none;
    cursor: pointer;
    transition: background-color 0.3s ease;
}
.search-wrapper button:hover {
    background-color: #5a6268;
}

.btn-add {
    background-color: #28a745;
    color: white;
    padding: 10px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.btn-add:hover {
    background-color: #218838;
}

.btn-edit {
    background-color: #007bff;
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.btn-edit:hover {
    background-color: #0069d9;
}
</style>

<?php require_once 'includes/footer.php'; ?>
