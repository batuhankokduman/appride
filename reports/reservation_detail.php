<?php
require_once '../functions/db.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../includes/menu.php';

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "<div class='content'><p class='flash-error'>Geçersiz rezervasyon ID.</p></div>";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ?");
$stmt->execute([$id]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$res) {
    echo "<div class='content'><p class='flash-error'>Rezervasyon bulunamadı.</p></div>";
    exit;
}

$eurToTry = floatval($pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'eur_to_try'")->fetchColumn());

$supplier = null;
$costBreakdown = [];
$totalExtrasCost = 0; // Ekstralar toplam maliyetini burada başlatıyoruz

if ($res['supplier_id']) {
    $stmtSupp = $pdo->prepare("SELECT s.*, pr.rule_name, l.supplier_cost_eur, l.cost_breakdown_json
        FROM supplier_assignment_logs l
        INNER JOIN suppliers s ON s.id = l.supplier_id
        INNER JOIN price_rules pr ON pr.id = l.price_rule_id
        WHERE l.reservation_id = ? LIMIT 1");
    $stmtSupp->execute([$id]);
    $supplier = $stmtSupp->fetch(PDO::FETCH_ASSOC);

    if ($supplier) {
        $costBreakdown = json_decode($supplier['cost_breakdown_json'] ?? '{}', true);

        // Ekstraların toplam maliyetini hesapla
        if (isset($costBreakdown['extras']) && is_array($costBreakdown['extras'])) {
            foreach ($costBreakdown['extras'] as $supplierExtra) {
                $totalExtrasCost += $supplierExtra['total'] ?? 0;
            }
        }
    }
}

$flight_info = json_decode($res['flight_info'] ?? '[]', true);
$extras = json_decode($res['extras'] ?? '[]', true);
$stopovers = json_decode($res['stopovers'] ?? '[]', true);
$maps_url = $res['maps_url'] ?? null;

$gross = floatval($res['gross_price']);
$cost = floatval($supplier['supplier_cost_eur'] ?? 0);
$paid = floatval($res['paid_amount']);
$remaining = floatval($res['remaining_amount']);

// Para birimi dönüşümleri, mevcut yapı zaten doğru ve açık
$grossEUR = $res['currency'] === 'TRY' ? $gross / $eurToTry : $gross;
$paidEUR = $res['currency'] === 'TRY' ? $paid / $eurToTry : $paid;
$remainingEUR = $res['currency'] === 'TRY' ? $remaining / $eurToTry : $remaining;

$balance = $remainingEUR - $cost;

// Ekstra adları için map oluştur
$extras_map = [];
$extra_ids = array_column($extras, 'extra_service_id');
if (!empty($extra_ids)) {
    $in = str_repeat('?,', count($extra_ids) - 1) . '?';
    $stmtExtras = $pdo->prepare("SELECT id, service_name FROM extras WHERE id IN ($in)");
    $stmtExtras->execute($extra_ids);
    foreach ($stmtExtras->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $extras_map[$row['id']] = $row['service_name'];
    }
}
?>
<div class="content">
    <h2>Rezervasyon Detayı (#<?= $id ?>)</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">

        <div class="card">
            <h3>Müşteri Bilgisi</h3>
            <p><strong>Ad Soyad:</strong> <?= htmlspecialchars($res['customer_first_name'] . ' ' . $res['customer_last_name']) ?></p>
            <p><strong>Telefon:</strong> <?= htmlspecialchars($res['customer_phone']) ?></p>
            <p><strong>Durum:</strong> <?= htmlspecialchars($res['reservation_status']) ?></p>
            <p><strong>Oluş. Tarihi:</strong> <?= htmlspecialchars($res['reservation_created_at']) ?></p>
        </div>

        <div class="card">
            <h3>Transfer Bilgisi</h3>
            <p><strong>Fiyat Kuralı:</strong> <?= htmlspecialchars($supplier['rule_name'] ?? '-') ?></p>
            <p><strong>Tarih/Saat:</strong> <?= htmlspecialchars($res['schedule_pickup_date'] ?? '') ?> <?= htmlspecialchars($res['schedule_selected_time'] ?? '') ?></p>
            <p><strong>Pickup:</strong> <?= htmlspecialchars($res['pickup_address']) ?> (<?= htmlspecialchars($res['pickup_geofence_name']) ?>)</p>
            <p><strong>Dropoff:</strong> <?= htmlspecialchars($res['dropoff_address']) ?> (<?= htmlspecialchars($res['dropoff_geofence_name']) ?>)</p>
        </div>

        <div class="card">
            <h3>Yolcu Bilgisi</h3>
            <p><strong>Yetişkin:</strong> <?= (int)$res['passengers_adults'] ?></p>
            <p><strong>Çocuk:</strong> <?= (int)$res['passengers_children'] ?></p>
            <?php if (!empty($stopovers)): ?>
                <p><strong>Duraklar:</strong></p>
                <ul>
                    <?php foreach ($stopovers as $index => $stop): ?>
                        <li><?= htmlspecialchars($stop['address'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><strong>Duraklar:</strong> Yok</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Ödeme Bilgisi</h3>
            <p><strong>Müşteri Toplam Fiyatı:</strong> <?= number_format($gross, 2) ?> <?= htmlspecialchars($res['currency']) ?> (Yaklaşık €<?= number_format($grossEUR, 2) ?>)</p>
            <p><strong>Müşteriden Alınan Ödeme:</strong> <?= number_format($paid, 2) ?> <?= htmlspecialchars($res['currency']) ?> (Yaklaşık €<?= number_format($paidEUR, 2) ?>)</p>
            <p><strong>Müşteriden Kalan Tutar:</strong> <?= number_format($remaining, 2) ?> <?= htmlspecialchars($res['currency']) ?> (Yaklaşık €<?= number_format($remainingEUR, 2) ?>)</p>
            <p><strong>Tedarikçi Maliyeti:</strong> €<?= number_format($cost, 2) ?> EUR</p>
            <p><strong>Borç / Alacak (EUR):</strong>
                <?php if ($balance >= 0): ?>
                    <span style="color:green; font-weight:bold;">€<?= number_format($balance, 2) ?> Alacak</span>
                <?php else: ?>
                    <span style="color:red; font-weight:bold;">€<?= number_format(abs($balance), 2) ?> Borç</span>
                <?php endif; ?>
            </p>
        </div>

        <?php if ($supplier && !empty($costBreakdown)): ?>
        <div class="card">
            <h3>Tedarikçi Maliyet Detayları (EUR)</h3>
            <ul>
                <?php if (isset($costBreakdown['custom_cost_override'])): ?>
                    <li><strong>Maliyet Türü:</strong> Özel Uygulanan Maliyet</li>
                <?php endif; ?>

                <?php if (($supplier['price_rule_type_id'] ?? null) == 1): // Yolcu Başına Fiyat Kuralı ?>
                    <li><strong>Kural Tipi:</strong> Yetişkin/Çocuk Başına</li>
                    <li><strong>Yetişkin Başına Maliyet:</strong> €<?= number_format($costBreakdown['cost_per_adult'] ?? 0, 2) ?></li>
                    <li><strong>Çocuk Başına Maliyet:</strong> €<?= number_format($costBreakdown['cost_per_child'] ?? 0, 2) ?></li>
                    <li><strong>Hesaplama:</strong> <?= (int)($res['passengers_adults'] ?? 0) ?> Yetişkin &times; €<?= number_format($costBreakdown['cost_per_adult'] ?? 0, 2) ?> + <?= (int)($res['passengers_children'] ?? 0) ?> Çocuk &times; €<?= number_format($costBreakdown['cost_per_child'] ?? 0, 2) ?></li>
                <?php elseif (($supplier['price_rule_type_id'] ?? null) == 2): // Araç Başına Fiyat Kuralı ?>
                    <li><strong>Kural Tipi:</strong> Araç Başına</li>
                    <li><strong>Araç Başına Maliyet:</strong> €<?= number_format($costBreakdown['cost_per_vehicle'] ?? 0, 2) ?></li>
                <?php elseif (($supplier['price_rule_type_id'] ?? null) == 3): // Sabit Fiyat + KM/Dakika Kuralı ?>
                    <li><strong>Kural Tipi:</strong> Sabit Fiyat + Süre/Mesafe</li>
                    <li><strong>Sabit Taban Fiyat:</strong> €<?= number_format($costBreakdown['base'] ?? 0, 2) ?></li>
                    <li><strong>Dakika Başına Fiyat:</strong> €<?= number_format($costBreakdown['per_minute'] ?? 0, 2) ?></li>
                    <li><strong>Transfer Süresi:</strong> <?= number_format($costBreakdown['duration'] ?? 0) ?> dakika</li>
                    <?php if (isset($costBreakdown['extra_time']) && $costBreakdown['extra_time'] > 0): ?>
                        <li><strong>Ekstra Bekleme Süresi:</strong> <?= number_format($costBreakdown['extra_time'] ?? 0) ?> dakika</li>
                    <?php endif; ?>
                    <?php if (isset($costBreakdown['km']) && isset($costBreakdown['km_price'])): ?>
                        <li><strong>Transfer Mesafesi:</strong> <?= number_format($costBreakdown['km'] ?? 0) ?> km</li>
                        <li><strong>KM Başına Maliyet:</strong> €<?= number_format($costBreakdown['km_price'] ?? 0, 2) ?></li>
                    <?php endif; ?>
                    <li><strong>Hesaplama:</strong> Sabit (€<?= number_format($costBreakdown['base'] ?? 0, 2) ?>) + (Süre (<?= number_format($costBreakdown['duration'] ?? 0) ?> dk) + Ekstra Süre (<?= number_format($costBreakdown['extra_time'] ?? 0) ?> dk)) &times; €<?= number_format($costBreakdown['per_minute'] ?? 0, 2) ?>/dk
                    <?php if (isset($costBreakdown['km']) && isset($costBreakdown['km_price'])): ?>
                     + Mesafe (<?= number_format($costBreakdown['km'] ?? 0) ?> km) &times; €<?= number_format($costBreakdown['km_price'] ?? 0, 2) ?>/km
                    <?php endif; ?>
                    </li>
                <?php endif; ?>
                <?php if (!empty($costBreakdown['extras']) && $totalExtrasCost > 0): ?>
                    <li style="margin-top: 10px;"><strong>Ekstralar Toplam Maliyeti:</strong> €<?= number_format($totalExtrasCost, 2) ?></li>
                <?php endif; ?>
                <?php if (isset($costBreakdown['extras_error'])): ?>
                    <li style="color: red;"><strong>Ekstra Maliyet Hesaplama Hatası:</strong> <?= htmlspecialchars($costBreakdown['extras_error']) ?></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($extras)): ?>
        <div class="card">
            <h3>Müşteri Ekstraları (Tedarikçi Maliyet Detayı)</h3>
            <ul>
                <?php
                $supplierExtrasBreakdown = $costBreakdown['extras'] ?? [];
                ?>
                <?php foreach ($extras as $extra):
                    $id = $extra['extra_service_id'] ?? null;
                    $qty = $extra['extra_service_quantity'] ?? 1;
                    $name = $extras_map[$id] ?? 'Bilinmeyen Ekstra';

                    $unitCost = 0;
                    $extraTotalCost = 0;
                    foreach ($supplierExtrasBreakdown as $supplierExtra) {
                        if (isset($supplierExtra['extra_service_id']) && $supplierExtra['extra_service_id'] == $id) {
                            $unitCost = $supplierExtra['unit_cost'] ?? 0;
                            $extraTotalCost = $supplierExtra['total'] ?? 0;
                            break;
                        }
                    }
                ?>
                    <li>
                        <?= htmlspecialchars($name) ?> &times; <?= (int)$qty ?>
                        <?php if ($unitCost > 0): ?>
                            (Birim Maliyeti: €<?= number_format($unitCost, 2) ?>)
                        <?php endif; ?>
                        <br> <strong>Toplam Ekstra Maliyeti: €<?= number_format($extraTotalCost, 2) ?></strong>
                    </li>
                <?php endforeach; ?>
                <?php if ($totalExtrasCost > 0): ?>
                    <li style="margin-top: 10px;"><strong>Tüm Ekstralar İçin Toplam Maliyet: €<?= number_format($totalExtrasCost, 2) ?></strong></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>


        <?php if (!empty($flight_info)): ?>
        <div class="card">
            <h3>Uçuş Bilgisi</h3>
            <?php foreach ($flight_info as $item): ?>
                <p><strong><?= htmlspecialchars($item['label']) ?>:</strong> <?= htmlspecialchars($item['value']) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($maps_url): ?>
        <div class="card">
            <h3>Google Harita</h3>
            <p><a href="<?= htmlspecialchars($maps_url) ?>" target="_blank" class="btn-sm btn-primary">Rotayı Haritada Görüntüle</a></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Mevcut stil kodları olduğu gibi bırakıldı, görsel düzenlemeler için gerekiyorsa eklenebilir */
.card {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.card h3 {
    font-size: 16px;
    margin-bottom: 10px;
    border-bottom: 1px solid #eee;
    padding-bottom: 4px;
    color: #1f2937;
}
.card p, .card li {
    font-size: 14px;
    margin: 4px 0;
    color: #374151;
}
.card ul {
    margin: 0;
    padding-left: 16px;
}
.flash-error {
    color: #dc3545;
    background-color: #f8d7da;
    border-color: #f5c6cb;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 20px;
}
.btn-sm {
    display: inline-block;
    padding: 5px 10px;
    font-size: 13px;
    border-radius: 4px;
    background-color: #007bff;
    color: #fff;
    text-decoration: none;
    text-align: center;
}

.btn-sm:hover {
    background-color: #0056b3;
}
</style>

<?php require_once '../includes/footer.php'; ?>