<?php
require_once '../functions/db.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../includes/menu.php';

$eurToTry = floatval($pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'eur_to_try'")->fetchColumn());

$sql = "
    SELECT s.id AS supplier_id, s.full_name,
            COUNT(l.id) AS reservation_count,
            SUM(r.passengers_adults + r.passengers_children) AS total_guests,
            SUM(l.gross_price_eur) AS total_sales_eur,
            SUM(l.supplier_cost_eur) AS total_cost_eur,
            SUM(CASE WHEN r.currency = 'TRY' THEN r.paid_amount / :eurToTry ELSE r.paid_amount END) AS total_paid_eur,
            SUM(
                (CASE WHEN r.currency = 'TRY' THEN r.remaining_amount / :eurToTry2 ELSE r.remaining_amount END)
                - l.supplier_cost_eur
            ) AS balance_eur
    FROM supplier_assignment_logs l
    INNER JOIN suppliers s ON s.id = l.supplier_id
    INNER JOIN reservations r ON r.reservation_id = l.reservation_id
    GROUP BY s.id
    ORDER BY s.full_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'eurToTry' => $eurToTry,
    'eurToTry2' => $eurToTry
]);
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content">
    <h2>📃 Tedarikçi Hesap Dökümü</h2>

    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead>
            <tr>
                <th>Tedarikçi</th>
                <th>Rezervasyon</th>
                <th>Kisi</th>
                <th>Toplam Satış (EUR)</th>
                <th>Maliyet (EUR)</th>
                <th>Ödeme Alındı (EUR)</th>
                <th>Borç/Alacak (EUR)</th>
                <th>Detay</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['full_name']) ?></td>
                    <td><?= (int)$s['reservation_count'] ?></td>
                    <td><?= (int)$s['total_guests'] ?></td>
                    <td>€<?= number_format($s['total_sales_eur'], 2) ?></td>
                    <td>€<?= number_format($s['total_cost_eur'], 2) ?></td>
                    <td>€<?= number_format($s['total_paid_eur'], 2) ?></td>
                    <td>
                        <?php if ($s['balance_eur'] >= 0): ?>
                            <span class="status active">€<?= number_format($s['balance_eur'], 2) ?> Alacak</span>
                        <?php else: ?>
                            <span class="status passive">€<?= number_format(abs($s['balance_eur']), 2) ?> Borç</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="supplier_statement_detail.php?supplier_id=<?= $s['supplier_id'] ?>" class="btn-sm btn-primary">Detay</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<style>
.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    font-size: 14px;
    background-color: #fff;
}

.data-table th,
.data-table td {
    padding: 10px 12px;
    border: 1px solid #ddd;
    text-align: left;
}

.data-table th {
    background-color: #f5f5f5;
    font-weight: bold;
}

.status.active {
    color: green;
    font-weight: bold;
}

.status.passive {
    color: red;
    font-weight: bold;
}

.btn-sm {
    display: inline-block;
    padding: 5px 10px;
    font-size: 13px;
    border-radius: 4px;
    background-color: #007bff;
    color: #fff;
    text-decoration: none;
}

.btn-sm:hover {
    background-color: #0056b3;
}
</style>

<?php require_once '../includes/footer.php'; ?>