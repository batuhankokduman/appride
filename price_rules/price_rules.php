<?php
require_once '../functions/db.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../includes/menu.php';

$search = $_GET['search'] ?? '';
$where = "WHERE 1";
$params = [];

if (!empty($search)) {
    $where .= " AND (
        pr.rule_id LIKE :s1 OR
        pr.rule_name LIKE :s2 OR
        pr.pickup_geofence_id LIKE :s3 OR
        pr.dropoff_geofence_id LIKE :s4
    )";
    $likeSearch = "%$search%";
    $params = [
        's1' => $likeSearch,
        's2' => $likeSearch,
        's3' => $likeSearch,
        's4' => $likeSearch
    ];
}

$sql = "SELECT pr.*, v.vehicle_name FROM price_rules pr
LEFT JOIN vehicles v ON pr.vehicle_id = v.vehicle_id
$where ORDER BY pr.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rules = $stmt->fetchAll();
?>

<div class="content">
    <h2 class="mb-4">📋 Fiyat Kuralları</h2>

    <!-- Modern Arama ve Ekle Butonları -->
    <form method="GET" action="?" class="search-form">
        <div class="search-wrapper">
            <input type="text" name="search" placeholder="Kural adı, ID, Geofence..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">
                <i class="fa fa-search"></i>
                <span style="display:none;">Ara</span>
            </button>
        </div>
        <a href="price_rules_add.php" class="btn-add"><i class="fas fa-plus"></i> Yeni Kural</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Vehicle ID</th>
                <th>Araç Adı</th>
                <th>Rule ID</th>
                <th>Adı</th>
                <th>Pickup</th>
                <th>Dropoff</th>
                <th>Tip</th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rules as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= $row['vehicle_id'] ?></td>
                    <td><?= htmlspecialchars($row['vehicle_name']) ?></td>
                    <td><?= $row['rule_id'] ?></td>
                    <td><?= htmlspecialchars($row['rule_name']) ?></td>
                    <td><?= htmlspecialchars($row['pickup_geofence_id']) ?></td>
                    <td><?= htmlspecialchars($row['dropoff_geofence_id']) ?></td>
                    <td><?= htmlspecialchars($row['price_rule_type_id']) ?></td>
                    <td>
                        <a href="price_rules_edit.php?id=<?= $row['id'] ?>" class="btn-edit"><i class="fas fa-edit"></i> Düzenle</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Stil Dosyaları -->


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
    padding: 6px 10px;
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

<?php require_once '../includes/footer.php'; ?>
