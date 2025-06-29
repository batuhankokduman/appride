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
        full_name LIKE :s1 OR
        company_name LIKE :s2 OR
        phone_number LIKE :s3 OR
        email LIKE :s4 OR
        currency LIKE :s5
    )";
    $likeSearch = '%' . $search . '%';
    $params = [
        's1' => $likeSearch,
        's2' => $likeSearch,
        's3' => $likeSearch,
        's4' => $likeSearch,
        's5' => $likeSearch
    ];
}

$sql = "SELECT * FROM suppliers $where ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();
?>

<div class="content">
    <h2>📦 Tedarikçiler</h2>

    <form method="GET" style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
        <input type="text" name="search" placeholder="Ad, firma, telefon..." value="<?= htmlspecialchars($search) ?>" style="padding: 8px; width: 250px;">
        <button type="submit" class="btn btn-primary" style="padding: 8px 14px;">🔍 Ara</button>
        <a href="supplier_add.php" class="btn btn-success" style="padding: 8px 14px;">➕ Tedarikçi Ekle</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>İşlem</th>
               <th>Cari</th>
                <th>ID</th>
                <th>Ad Soyad</th>
                <th>Firma</th>
                <th>Telefon</th>
                <th>E-posta</th>
                <th>Dil</th>
                <th>Para Birimi</th>
                <th>Durum</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($suppliers as $row): ?>
                <tr>
                    <td>
                        <a href="supplier_edit.php?id=<?= $row['id'] ?>" class="btn-sm btn-primary">Düzenle</a>
                    </td>
                  <td>   <a href="supplier_wallet.php?supplier_id=<?= $row['id'] ?>" class="btn-sm btn-warning">💰 Cari</a></td>

                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['company_name']) ?></td>
                    <td><?= htmlspecialchars($row['phone_number']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars(strtoupper($row['language'])) ?></td>
                    <td><?= htmlspecialchars($row['currency']) ?></td>
                    <td>
                        <span class="status <?= $row['status'] ? 'active' : 'passive' ?>">
                            <?= $row['status'] ? 'Aktif' : 'Pasif' ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
