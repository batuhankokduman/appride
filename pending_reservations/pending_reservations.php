<?php
require_once '../functions/db.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../includes/menu.php';

$sql = "SELECT * FROM reservations WHERE supplier_id IS NULL ORDER BY reservation_created_at DESC";
$stmt = $pdo->query($sql);
$reservations = $stmt->fetchAll();
$eurToTry = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'eur_to_try'")->fetchColumn();
?>

<script>
const eurToTry = <?= floatval($eurToTry) ?>;
const extrasMap = {
    "2270": "Bebek Koltuğu",
    "2271": "Ekstra Bagaj",
    "2272": "Karşılamalı Transfer"
};
</script>

<div class="content">
    <h2 class="mb-4">⏳ Bekleyen Rezervasyonlar</h2>

    <div id="debug-log" style="font-family: monospace; font-size: 13px; background: #f5f5f5; padding: 15px; border: 1px solid #ddd; margin-bottom: 20px; display:none;"></div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Ad Soyad</th>
                <th>Tur</th>
                <th>Tarih</th>
                <th>Kisi Sayısı</th>
                <th>Fiyat Kuralı</th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody id="reservation-table-body">
            <?php foreach ($reservations as $r):
                $stmt2 = $pdo->prepare("SELECT * FROM price_rules WHERE rule_id = ? LIMIT 1");
                $stmt2->execute([$r['rule_id']]);
                $rule = $stmt2->fetch();

                if (!$rule) continue;

                $rule_id_db = $rule['id'];
                $type_id = $rule['price_rule_type_id'];

                $stmt3 = $pdo->prepare("
                    SELECT scp.*, s.full_name, pr.price_rule_type_id
                    FROM supplier_cost_periods scp
                    INNER JOIN suppliers s ON s.id = scp.supplier_id
                    INNER JOIN price_rules pr ON pr.id = scp.price_rule_id
                    WHERE scp.price_rule_id = ?
                      AND scp.valid_from <= CURDATE()
                      AND (scp.valid_to IS NULL OR scp.valid_to >= CURDATE())
                ");
                $stmt3->execute([$rule_id_db]);
                $suppliers = $stmt3->fetchAll();
            ?>
            <tr>
                <td><?= $r['reservation_id'] ?></td>
                <td><?= htmlspecialchars($r['customer_first_name'] . ' ' . $r['customer_last_name']) ?></td>
                <td><?= htmlspecialchars($r['rule_name']) ?></td>
                <td><?= $r['schedule_pickup_date'] ?></td>
                <td><?= $r['passengers_adults'] + $r['passengers_children'] ?></td>
                <td>Tip <?= $type_id ?></td>
                <td>
                    <?php if (!empty($suppliers)): ?>
                        <button class="btn-assign-custom" data-reservation='<?= json_encode($r) ?>' data-suppliers='<?= json_encode($suppliers) ?>'>Tedarikçi Ata</button>
                    <?php else: ?>
                        <span class="text-danger">❌ Tedarikçi yok</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="assignModal" onclick="closeAssignModal(event)" style="
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.4);
    z-index: 1000;
    align-items: center;
    justify-content: center;
">
    <div id="assignModalContent" style="
        position: relative;
        background: #fff;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        max-width: 500px;
        width: 100%;
    ">
        <button onclick="document.getElementById('assignModal').style.display='none'; document.body.style.overflow='auto';" style="
            position: absolute;
            top: 10px;
            right: 10px;
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
        ">❌</button>

        <h3 style="margin-bottom: 20px;">🚗 Tedarikçi Seçimi</h3>
        <div id="modal-content"></div>
    </div>
</div>

<style>
.btn-assign-custom {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 8px 16px;
    font-size: 14px;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background-color 0.2s ease;
}
.btn-assign-custom:hover {
    background-color: #0056b3;
}
</style>

<script>
function closeAssignModal(event) {
    const modalContent = document.getElementById('assignModalContent');
    if (!modalContent.contains(event.target)) {
        document.getElementById('assignModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

document.querySelectorAll('.btn-assign-custom').forEach(button => {
    button.addEventListener('click', () => {
        const reservation = JSON.parse(button.getAttribute('data-reservation'));
        const suppliers = JSON.parse(button.getAttribute('data-suppliers'));
        const debugEl = document.getElementById('debug-log');
        debugEl.innerHTML = '';

        let html = `<ul style='list-style:none; padding:0;'>`;

        suppliers.forEach((s, index) => {
            let costEUR = 0;
            let breakdown = "<div style='margin-top:10px; font-size: 13px;'>";

            if (s.price_rule_type_id == 1) {
                breakdown += `👤 Yetişkin Fiyatı: ${s.cost_per_adult} EUR<br>`;
                breakdown += `🧒 Çocuk Fiyatı: ${s.cost_per_child} EUR<br>`;
                costEUR = (reservation.passengers_adults * s.cost_per_adult) + (reservation.passengers_children * s.cost_per_child);
            } else if (s.price_rule_type_id == 2) {
                breakdown += `🚘 Araç Fiyatı: ${s.cost_per_vehicle} EUR<br>`;
                costEUR = parseFloat((s.cost_per_vehicle + '').replace(',', '.')) || 0;
            } else if (s.price_rule_type_id == 3) {
                breakdown += `📟 Açılış: ${s.fixed_base_price} EUR<br>`;
                breakdown += `⏱️ Dakika Başına: ${s.price_per_minute} EUR<br>`;
                costEUR = parseFloat((s.fixed_base_price + '').replace(',', '.')) || 0;

                const dur = parseFloat((reservation.stopovers_duration + '').replace(',', '.')) || 0;
                const ext = parseFloat((reservation.extra_time + '').replace(',', '.')) || 0;
                const perMin = parseFloat((s.price_per_minute + '').replace(',', '.')) || 0;

                costEUR += dur * perMin + ext * perMin;
            }

            try {
                const reservationExtras = JSON.parse(reservation.extras || "[]");
                const supplierExtras = JSON.parse(s.extras_json || "{}");
                let extraTotal = 0;

                reservationExtras.forEach(extra => {
                    const extraId = extra.extra_service_id;
                    const quantity = parseFloat(extra.extra_service_quantity || 0);
                    const supplierPrice = parseFloat(supplierExtras[extraId] || 0);
                    const name = extrasMap[extraId] || `Bilinmeyen Extra`;

                    const totalCost = supplierPrice * quantity;
                    breakdown += `➕ ${name}: ${quantity} x ${supplierPrice} EUR = ${totalCost} EUR<br>`;
                    extraTotal += totalCost;
                });

                costEUR += extraTotal;
            } catch (err) {
                breakdown += `<div style='color:red;'>Extra hesaplamasında hata: ${err.message}</div>`;
            }

            const gross = parseFloat((reservation.gross_price + '').replace(',', '.')) || 0;
            let grossEUR = reservation.currency === 'TRY' ? gross / eurToTry : gross;
            let kar = grossEUR - costEUR;

            const id = `customCostInput_${index}`;

            html += `<li style='margin-bottom:12px; padding:12px; border:1px solid #eee; border-radius:8px; background:#fafafa'>
                <strong>${s.full_name}</strong><br>
                <span style='font-size:14px;'>
                    <strong>Maliyet:</strong> <span id="displayCost_${index}">${costEUR.toFixed(2)} EUR</span><br>
                    <strong>Satış:</strong> ${gross.toFixed(2)} TRY (${grossEUR.toFixed(2)} EUR)<br>
                    <strong>Kar (EUR):</strong> <span id="displayProfit_${index}" style="color:${kar >= 10 ? 'green' : kar < 0 ? 'purple' : 'red'};">${kar.toFixed(2)}</span><br>
                    <span style='font-size:12px; color:#666;'>Kur: ${eurToTry} | ${reservation.currency} → EUR</span>
                </span>
                ${breakdown}
                <label style="margin-top:10px; display:block; font-size:13px;">💰 Maliyeti Düzenle (EUR):</label>
                <input type="number" id="${id}" step="0.01" value="${costEUR.toFixed(2)}" style="width:100%; padding:6px; font-size:13px; border-radius:6px; border:1px solid #ccc; margin-bottom:10px;">
                <button type="button" onclick="recalculate(${index}, ${gross}, ${eurToTry}, '${reservation.currency}')" style="margin-bottom:10px; background:#ffc107; color:black; padding:6px 12px; border:none; border-radius:6px; cursor:pointer;">Yeniden Hesapla</button>

                <form method='post' action='assign_supplier.php'>
                    <input type='hidden' name='reservation_id' value='${reservation.reservation_id}'>
                    <input type='hidden' name='supplier_id' value='${s.supplier_id}'>
                    <input type='hidden' id='finalCostInput_${index}' name='custom_cost' value='${costEUR.toFixed(2)}'>
                    <button type='submit' style='background:#28a745; color:white; padding:6px 12px; border:none; border-radius:6px; cursor:pointer;'>Seç</button>
                </form>
            </li>`;
        });

        html += `</ul>`;
        document.getElementById('modal-content').innerHTML = html;
        document.getElementById('assignModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        document.getElementById('debug-log').style.display = 'block';
    });
});

function recalculate(index, gross, eurToTry, currency) {
    const input = document.getElementById(`customCostInput_${index}`);
    const newCostEUR = parseFloat(input.value);
    const grossEUR = currency === 'TRY' ? gross / eurToTry : gross;
    const kar = grossEUR - newCostEUR;

    document.getElementById(`displayCost_${index}`).innerText = `${newCostEUR.toFixed(2)} EUR`;
    document.getElementById(`displayProfit_${index}`).innerText = kar.toFixed(2);
    document.getElementById(`displayProfit_${index}`).style.color = kar >= 10 ? 'green' : kar < 0 ? 'purple' : 'red';
    document.getElementById(`finalCostInput_${index}`).value = newCostEUR.toFixed(2);
}
</script>

<?php require_once '../includes/footer.php'; ?>