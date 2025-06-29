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
        sc.id LIKE :s1 OR
        s.full_name LIKE :s2 OR
        pr.rule_name LIKE :s3
    )";
    $likeSearch = '%' . $search . '%';
    $params = [
        's1' => $likeSearch,
        's2' => $likeSearch,
        's3' => $likeSearch
    ];
}

$sql = "
SELECT sc.*, s.full_name AS supplier_name, pr.rule_name
FROM supplier_costs sc
LEFT JOIN suppliers s ON sc.supplier_id = s.id
LEFT JOIN price_rules pr ON sc.price_rule_id = pr.id
$where
ORDER BY sc.valid_from DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$costs = $stmt->fetchAll();
?>

<div class="content">
    <h2 class="mb-4">📊 Tedarikçi Maliyet Listesi</h2>

    <form method="GET" style="margin-bottom: 20px; display: flex; gap: 10px;">
        <input type="text" name="search" placeholder="Tedarikçi, Hizmet, ID..." value="<?= htmlspecialchars($search) ?>" style="padding: 8px; width: 250px;">
        <button type="submit" class="btn-search">🔍 Ara</button>
        <a href="supplier_costs_add.php" class="btn-add"><i class="fas fa-plus"></i> Yeni Maliyet Ekle</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Tedarikçi</th>
                <th>Hizmet</th>
                <th>Tür</th>
                <th>Geçerlilik</th>
                <th>Oluşturulma</th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($costs as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                    <td><?= htmlspecialchars($row['rule_name']) ?></td>
                    <td>
                        <?php
                        switch ($row['price_rule_type_id']) {
                            case 1: echo 'Kişi Başı'; break;
                            case 2: echo 'Araç Başı'; break;
                            case 3: echo 'Dinamik'; break;
                            default: echo 'Bilinmiyor';
                        }
                        ?>
                    </td>
                    <td><?= htmlspecialchars($row['valid_from']) ?> - <?= htmlspecialchars($row['valid_to']) ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td>
                        <a href="supplier_costs_edit.php?id=<?= $row['id'] ?>" class="btn-edit"><i class="fas fa-edit"></i> Düzenle</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.btn-add {
    background-color: #28a745;
    color: white;
    padding: 8px 14px;
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

.btn-search {
    background-color: #6c757d;
    color: white;
    padding: 8px 14px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.btn-search:hover {
    background-color: #5a6268;
}
</style>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-Avb2QiuDEEvB4bZJYdft2mNjVShBftLdPG8FJ0V7irTLQ8Uo0qcPxh4Plq7G5tGm0rU+1SPhVotteLpBERwTkw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<?php require_once 'includes/footer.php'; ?>