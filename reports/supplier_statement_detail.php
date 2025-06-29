<?php
require_once '../functions/db.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../includes/menu.php';

$supplier_id = $_GET['supplier_id'] ?? null;
if (!$supplier_id) {
    die("Geçersiz tedarikçi ID.");
}

$eurToTry = floatval($pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'eur_to_try'")->fetchColumn());

$sql = "
    SELECT r.reservation_id, r.customer_first_name, r.customer_last_name,
           r.reservation_created_at, r.schedule_pickup_date,
           r.passengers_adults, r.passengers_children,
           r.currency, -- Bu satır eklendi: Rezervasyonun para birimini almak için
           pr.rule_name,
           l.gross_price_eur, l.supplier_cost_eur,
           (CASE WHEN r.currency = 'TRY' THEN r.paid_amount / :eurToTry ELSE r.paid_amount END) AS paid_amount_eur,
           ((CASE WHEN r.currency = 'TRY' THEN r.remaining_amount / :eurToTry2 ELSE r.remaining_amount END) - l.supplier_cost_eur) AS balance_eur
    FROM supplier_assignment_logs l
    INNER JOIN reservations r ON r.reservation_id = l.reservation_id
    INNER JOIN price_rules pr ON pr.rule_id = r.rule_id
    WHERE l.supplier_id = :supplier_id
    ORDER BY r.reservation_created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'eurToTry' => $eurToTry,
    'eurToTry2' => $eurToTry,
    'supplier_id' => $supplier_id
]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content">
    <h2>📒 Tedarikçi Rezervasyon Detayları</h2>

    <table class="data-table">
        <thead>
            <tr>
                <th>Müşteri</th>
                <th>Rez. Tarihi</th>
                <th>Tur Tarihi</th>
                <th>Yetişkin</th>
                <th>Çocuk</th>
                <th>Fiyat Kuralı</th>
                <th>Satış (EUR)</th>
                <th>Maliyet (EUR)</th>
                <th>Ödeme (EUR)</th>
                <th>Borç/Alacak</th>
                <th>Detay</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reservations as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['customer_first_name'] . ' ' . $r['customer_last_name']) ?></td>
                <td><?= htmlspecialchars($r['reservation_created_at'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['schedule_pickup_date'] ?? '') ?></td>
                <td><?= (int)$r['passengers_adults'] ?></td>
                <td><?= (int)$r['passengers_children'] ?></td>
                <td><?= htmlspecialchars($r['rule_name'] ?? '') ?></td>
                <td>€<?= number_format($r['gross_price_eur'], 2) ?></td>
                <td>€<?= number_format($r['supplier_cost_eur'], 2) ?></td>
                <td>€<?= number_format($r['paid_amount_eur'], 2) ?></td>
                <td>
                    <?php if ($r['balance_eur'] >= 0): ?>
                        <span class="status active">€<?= number_format($r['balance_eur'], 2) ?> Alacak</span>
                    <?php else: ?>
                        <span class="status passive">€<?= number_format(abs($r['balance_eur']), 2) ?> Borç</span>
                    <?php endif; ?>
                </td>
                <td><a href="reservation_detail.php?id=<?= $r['reservation_id'] ?>" class="btn-sm btn-primary">Gör</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
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