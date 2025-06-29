<?php
require_once 'functions/db.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';
require_once 'includes/menu.php';
require_once 'calculate_and_log_cost.php';

$sort_column = $_GET['sort'] ?? 'schedule_selected_date';
$sort_order = $_GET['order'] ?? 'DESC';
$allowed_columns = [
    'reservation_created_at', 'schedule_pickup_date', 'schedule_selected_date',
    'customer_first_name', 'vehicle_type', 'reservation_status', 'passengers_adults'
];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'reservation_created_at';
}
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

$order_by_sql_column = ($sort_column === 'passengers_adults') ? '(res.passengers_adults + res.passengers_children)' : "res.$sort_column";
if ($sort_column === 'vehicle_type') {
    $order_by_sql_column = 'veh.vehicle_type';
}
$order_by_clause = "ORDER BY $order_by_sql_column $sort_order";

$search = $_GET['search'] ?? '';
$where = "";
$params = [];
if (!empty($search)) {
    $where = "WHERE (
        res.reservation_id LIKE :s1 OR res.customer_first_name LIKE :s2 OR res.customer_last_name LIKE :s3 OR
        res.customer_email LIKE :s4 OR res.customer_phone LIKE :s5 OR res.rule_name LIKE :s6 OR
        res.pickup_geofence_name LIKE :s7 OR res.dropoff_geofence_name LIKE :s8
    )";
    $likeSearch = '%' . $search . '%';
    $params = [
        's1' => $likeSearch, 's2' => $likeSearch, 's3' => $likeSearch, 's4' => $likeSearch,
        's5' => $likeSearch, 's6' => $likeSearch, 's7' => $likeSearch, 's8' => $likeSearch
    ];
}

$eurToTry = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'eur_to_try'")->fetchColumn();

$sql = "
    SELECT 
        res.*, 
        veh.vehicle_type
    FROM 
        reservations AS res
    LEFT JOIN 
        vehicles AS veh ON res.vehicle_id = veh.vehicle_id
    $where 
    $order_by_clause
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getSortLink($column, $current_sort, $current_order, $current_search) {
    $order = ($current_sort === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    return "?search=" . urlencode($current_search) . "&sort=$column&order=$order";
}

function getSortIcon($column, $current_sort, $current_order) {
    if ($current_sort === $column) {
        return $current_order === 'ASC' ? '▲' : '▼';
    }
    return '';
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    .content { padding: 20px; }
    .page-header { margin-bottom: 20px; }
    .page-header h2 { font-size: 24px; font-weight: 600; margin-bottom: 15px; }
    .search-wrapper-enhanced { position: relative; margin-bottom: 20px; }
    .search-input-enhanced { width: 100%; box-sizing: border-box; padding: 12px 20px 12px 45px; border-radius: 8px; border: 1px solid #ddd; font-size: 15px; background-color: #f8f9fa; transition: all 0.2s ease-in-out; }
    .search-input-enhanced:focus { background-color: #fff; border-color: #007bff; box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25); }
    .search-wrapper-enhanced .fa-search { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
    .table-responsive { width: 100%; overflow-x: auto; }
    table { border-collapse: collapse; width: 100%; font-size: 13px; }
    th, td { padding: 12px 10px; border: 1px solid #e0e0e0; text-align: left; vertical-align: middle; white-space: nowrap; }
    tr:nth-child(even) { background-color: #f8f9fa; }
    tr:hover { background-color: #e9ecef; }
    th { background-color: #f2f2f7; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #555; cursor: pointer; }
    th a { color: inherit; text-decoration: none; }
    th a .sort-icon { margin-left: 4px; font-size: 9px; color: #333; }
    .address-cell { white-space: normal; max-width: 250px; }
    .btn-sm { padding: 5px 10px; font-size: 12px; border-radius: 5px; color: white; border: none; cursor: pointer; font-weight: bold; text-decoration: none; /* a etiketi için */ display: inline-block; /* a etiketi için */ }
    .btn-orange { background-color: #fd7e14; }
    .btn-success { background-color: #28a745 !important; }
    .btn-danger { background-color: #dc3545 !important; }
    .fw-bold { font-weight: 600; }
    .status { padding: 4px 8px; border-radius: 5px; color: white !important; font-weight: bold; text-transform: capitalize; display: inline-block; text-align: center; }
    .status.confirmed { background-color: #17a2b8; }
    .status.pending { background-color: #ffc107; }
    .status.completed { background-color: #28a745; }
    .status.cancelled { background-color: #dc3545; }
    .payment-status { padding: 5px 8px; border-radius: 4px; font-weight: bold; }
    .payment-paid { background-color: #d4edda; color: #155724; }
    .payment-partial { background-color: #fff3cd; color: #856404; }
    .payment-unpaid { background-color: #f8d7da; color: #721c24; }
    .pickup-form { display: none; position: absolute; z-index: 10; background-color: white; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }

    /* YENİ: SPLIT BUTTON (BÖLÜNMÜŞ BUTON) STİLLERİ */
    .action-dropdown { position: relative; display: inline-block; }
    .split-button-group { display: flex; align-items: stretch; }
    .split-button-group .btn-main { border-top-right-radius: 0; border-bottom-right-radius: 0; }
    .split-button-group .btn-toggle { border-top-left-radius: 0; border-bottom-left-radius: 0; border-left: 1px solid rgba(0, 0, 0, 0.1); padding-left: 8px; padding-right: 8px; }
    .dropdown-menu { display: none; position: absolute; right: 0; background-color: #f9f9f9; min-width: 120px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); z-index: 10; border-radius: 5px; overflow: hidden; }
    .dropdown-menu a { color: black; padding: 10px 15px; text-decoration: none; display: block; font-size: 13px; }
    .dropdown-menu a:hover { background-color: #f1f1f1; }
    .dropdown-menu .delete-link { color: #dc3545; }
    .dropdown-menu .delete-link:hover { background-color: #f1f1f1; color: #a71d2a; }
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

<div class="content">
    <div class="page-header">
        <h2>Rezervasyonlar</h2>
        <div id="debug-log" style="
        font-family: monospace;
        font-size: 13px;
        background: #f5f5f5;
        padding: 15px;
        border: 1px solid #ddd;
        margin-bottom: 20px;
        display: none;"></div>
        <form method="GET" action="?" class="search-form">
            <div class="search-wrapper-enhanced">
                <i class="fa fa-search"></i>
                <input type="search" name="search" class="search-input-enhanced" placeholder="İsim, telefon, rezervasyon ID ara..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>İşlem</th>
                    <th><a href="<?= getSortLink('schedule_pickup_date', $sort_column, $sort_order, $search) ?>">Alınış Saati <span class="sort-icon"><?= getSortIcon('schedule_pickup_date', $sort_column, $sort_order) ?></span></a></th>
                    <th><a href="<?= getSortLink('schedule_selected_date', $sort_column, $sort_order, $search) ?>">Seçilen T. <span class="sort-icon"><?= getSortIcon('schedule_selected_date', $sort_column, $sort_order) ?></span></a></th>
                    <th><a href="<?= getSortLink('customer_first_name', $sort_column, $sort_order, $search) ?>">İsim-Soyisim <span class="sort-icon"><?= getSortIcon('customer_first_name', $sort_column, $sort_order) ?></span></a></th>
                    <th>Telefon</th>
                    <th><a href="<?= getSortLink('vehicle_type', $sort_column, $sort_order, $search) ?>">Araç Tipi <span class="sort-icon"><?= getSortIcon('vehicle_type', $sort_column, $sort_order) ?></span></a></th>
                    <th>Alınış Adresi</th>
                    <th>Bırakılış Adresi</th>
                    <th>Ödeme</th>
                    <th><a href="<?= getSortLink('passengers_adults', $sort_column, $sort_order, $search) ?>">Yolcu <span class="sort-icon"><?= getSortIcon('passengers_adults', $sort_column, $sort_order) ?></span></a></th>
                    <th><a href="<?= getSortLink('reservation_status', $sort_column, $sort_order, $search) ?>">Durum <span class="sort-icon"><?= getSortIcon('reservation_status', $sort_column, $sort_order) ?></span></a></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservations as $row):
                    $ruleStmt = $pdo->prepare("SELECT * FROM price_rules WHERE rule_id = ? LIMIT 1");
$ruleStmt->execute([$row['rule_id']]);
$rule = $ruleStmt->fetch();

if (!$rule) continue;

$rule_id_db = $rule['id'];

                $eurToTry = floatval($pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'eur_to_try'")->fetchColumn());

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
$rawSuppliers = $stmt3->fetchAll();

$suppliers = [];
foreach ($rawSuppliers as $s) {
    // Assuming calculateOnlyCost returns a value in EUR, as inferred from original JS.
    // The `currency` field from `scp` is also available in `$s`.
    $s['calculated_cost'] = calculateOnlyCost($row, $s, $eurToTry);
    $suppliers[] = $s;
}

                ?>
                    <tr>
                        <td>
                            <div class="action-dropdown">
                                <div class="split-button-group">
                                    <a href="reservation_detail.php?id=<?= $row['reservation_id'] ?>" class="btn-sm btn-orange btn-main">Düzenle</a>
                                    <button class="btn-sm btn-orange btn-toggle split-button-toggle"><i class="fa fa-caret-down"></i></button>
                                </div>
                                <div class="dropdown-menu">
                                    <a href="#" class="delete-link btn-delete" data-id="<?= $row['reservation_id'] ?>">Sil</a>
                                    </div>
                            </div>
                        </td>
                        <td>
                            <?php
                                $pickupDate = $row['schedule_pickup_date'] ?? '';
                                $pickupTime = $row['schedule_pickup_time'] ?? '';
                                $isPickupAssigned = !empty($pickupDate) && !empty($pickupTime);
                                $buttonClass = $isPickupAssigned ? 'btn-success' : 'btn-danger';
                            ?>
                            <button class="btn-sm <?= $buttonClass ?> btn-set-pickup" data-id="<?= htmlspecialchars($row['reservation_id']) ?>">
                                <?= $isPickupAssigned ? htmlspecialchars($pickupDate . ' ' . substr($pickupTime, 0, 5)) : 'Ata' ?>
                            </button>
                            <div class="pickup-form">
                                <input type="date" class="pickup-date" value="<?= htmlspecialchars($pickupDate) ?>">
                                <input type="time" class="pickup-time" value="<?= htmlspecialchars($pickupTime) ?>">
                                <button class="btn-sm btn-success btn-save-pickup">Kaydet</button>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($row['schedule_selected_date'] . ' ' . substr($row['schedule_selected_time'], 0, 5)) ?></td>
                        <td><?= htmlspecialchars(trim($row['customer_first_name'] . ' ' . $row['customer_last_name'])) ?></td>
                        <td><a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $row['customer_phone']) ?>" target="_blank"><?= htmlspecialchars($row['customer_phone']) ?></a></td>
                        <td class="fw-bold"><?= htmlspecialchars($row['vehicle_type'] ?? 'N/A') ?></td>
                        <td class="address-cell"><?= htmlspecialchars($row['pickup_address']) ?></td>
                        <td class="address-cell"><?= htmlspecialchars($row['dropoff_address']) ?></td>
                        <td>
                            <?php
                                $paid = (float)$row['paid_amount'];
                                $gross = (float)$row['gross_price'];
                                $payment_class = 'payment-unpaid';
                                if ($paid >= $gross && $gross > 0) { $payment_class = 'payment-paid'; } 
                                elseif ($paid > 0 && $paid < $gross) { $payment_class = 'payment-partial'; }
                            ?>
                            <span class="payment-status <?= $payment_class ?>"><?= htmlspecialchars($row['paid_amount']) ?> / <?= htmlspecialchars($row['gross_price']) ?> <?= htmlspecialchars($row['currency']) ?></span>
                        </td>
                        <td class="fw-bold"><?= htmlspecialchars($row['passengers_adults']) ?>/<?= htmlspecialchars($row['passengers_children']) ?></td>
                        <td><span class="status <?= strtolower(htmlspecialchars($row['reservation_status'])) ?>"><?= htmlspecialchars($row['reservation_status']) ?></span></td>
                    <td>  <?php if (!empty($suppliers)): ?>
    <button class="btn-assign-custom"
        data-reservation='<?= json_encode($row) ?>'
        data-suppliers='<?= json_encode($suppliers) ?>'>
    Tedarikçi Ata
</button>

<?php else: ?>
    <span class="text-danger">❌ Tedarikçi yok</span>
<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function () {
    // Mevcut Alınış Saati Ata popup'ı
    $('.btn-set-pickup').click(function (e) {
        e.stopPropagation();
        const form = $(this).siblings('.pickup-form');
        $('.pickup-form').not(form).hide();
        $('.dropdown-menu').hide();
        form.toggle();
    });

    // YENİ: Sadece menü açma butonuna tıklanıldığında çalışacak kod
    $('.split-button-toggle').click(function(e) {
        e.stopPropagation();
        const menu = $(this).closest('.action-dropdown').find('.dropdown-menu');
        $('.dropdown-menu').not(menu).hide();
        $('.pickup-form').hide();
        menu.toggle();
    });

    // Dışarı tıklanınca tüm pop-up ve menüleri kapat
    $(document).click(function() {
        $('.pickup-form').hide();
        $('.dropdown-menu').hide();
    });
    // Menülerin içine tıklanınca kapanmasını engelle
    $('.pickup-form, .dropdown-menu').click(function (e) { e.stopPropagation(); });

    // Mevcut Alınış Saati Kaydetme
    $('.btn-save-pickup').click(function () {
        const container = $(this).closest('td');
        const id = container.find('.btn-set-pickup').data('id');
        const date = container.find('.pickup-date').val();
        const time = container.find('.pickup-time').val();
        if (!date || !time) { alert("Lütfen tarih ve saat girin."); return; }
        $.post('functions/update_pickup_datetime.php', { reservation_id: id, date: date, time: time }, function (response) {
            alert(response.message);
            if (response.success) { window.location.href = window.location.href; }
        }, 'json').fail(function() { alert("Bir hata oluştu."); });
    });

    // Silme İşlemi (Bu kısım değişmedi)
    $('.btn-delete').click(function(e) {
        e.preventDefault();
        
        if (!confirm('Bu rezervasyonu silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
            return;
        }

        const id = $(this).data('id');
        const row = $(this).closest('tr');

        $.post('functions/delete_reservation.php', { reservation_id: id }, function(response) {
            alert(response.message);
            if (response.success) {
                row.fadeOut(400, function() {
                    $(this).remove();
                });
            }
        }, 'json').fail(function() {
            alert('Silme işlemi sırasında bir sunucu hatası oluştu. Lütfen tekrar deneyin.');
        });
    });
});




$(document).on('click', '.btn-assign-custom', function() {
    const reservation = JSON.parse($(this).attr('data-reservation'));
    const suppliers = JSON.parse($(this).attr('data-suppliers'));

    const debugEl = document.getElementById('debug-log');
    debugEl.innerHTML = '';
    debugEl.style.display = 'block';

    if (!suppliers.length) {
        debugEl.innerHTML = 'Tedarikçi bulunamadı!';
        return;
    }

    let html = `<h4>${reservation.customer_first_name} ${reservation.customer_last_name} için tedarikçi seç:</h4>`;
    suppliers.forEach(s => {
        html += `<button class="btn btn-sm assign-btn" data-reservation-id="${reservation.reservation_id}" data-supplier-id="${s.supplier_id}">${s.full_name}</button><br>`;
    });

    debugEl.innerHTML = html;
});

$(document).on('click', '.assign-btn', function() {
    const reservationId = $(this).data('reservation-id');
    const supplierId = $(this).data('supplier-id');

    $.post('assign_supplier.php', {
        reservation_id: reservationId,
        supplier_id: supplierId
    }, function(response) {
        alert(response);
        location.reload();
    });
});

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
        const eurToTry = <?= floatval($eurToTry) ?>;

        let html = `<ul style='list-style:none; padding:0;'>`;

        suppliers.forEach((s, index) => {
            // s.calculated_cost is assumed to be in EUR based on existing JS usage
            const calculatedCostEUR = parseFloat(s.calculated_cost || 0);
            const supplierOriginalCurrency = s.currency; // e.g., 'TRY' or 'EUR'

            let costInSupplierCurrency = calculatedCostEUR; // Default if supplier currency is EUR
            const gross = parseFloat(reservation.gross_price) || 0;
            let grossInSupplierCurrency;

            // Determine costs and gross amount in supplier's original currency
            if (supplierOriginalCurrency === 'TRY') {
                costInSupplierCurrency = calculatedCostEUR * eurToTry; // Convert supplier's EUR cost to TRY
                grossInSupplierCurrency = reservation.currency === 'EUR' ? gross * eurToTry : gross;
            } else { // supplierOriginalCurrency === 'EUR'
                costInSupplierCurrency = calculatedCostEUR; // Supplier cost is already in EUR
                grossInSupplierCurrency = reservation.currency === 'TRY' ? gross / eurToTry : gross;
            }

            const profit = grossInSupplierCurrency - costInSupplierCurrency;

            const id = `customCostInput_${index}`;

            html += `<li style='margin-bottom:12px; padding:12px; border:1px solid #eee; border-radius:8px; background:#fafafa'>
                <strong>${s.full_name}</strong><br>
                <span style='font-size:14px;'>
                    <strong>Maliyet:</strong> <span id="displayCost_${index}">${costInSupplierCurrency.toFixed(2)} ${supplierOriginalCurrency}</span><br>
                    <strong>Satış:</strong> ${gross.toFixed(2)} ${reservation.currency} (${grossInSupplierCurrency.toFixed(2)} ${supplierOriginalCurrency})<br>
                    <strong>Kar (${supplierOriginalCurrency}):</strong> <span id="displayProfit_${index}" style="color:${profit >= 10 ? 'green' : profit < 0 ? 'purple' : 'red'};">${profit.toFixed(2)}</span><br>
                    <span style='font-size:12px; color:#666;'>Kur: ${eurToTry} | ${reservation.currency} ${reservation.currency === supplierOriginalCurrency ? '' : `→ ${supplierOriginalCurrency}`}</span>
                </span>
                <div style='margin-top:10px; font-size: 13px;'>💡 Maliyet PHP tarafından hesaplandı.</div>
                <label style="margin-top:10px; display:block; font-size:13px;">💰 Maliyeti Düzenle (${supplierOriginalCurrency}):</label>
                <input type="number" id="${id}" step="0.01" value="${costInSupplierCurrency.toFixed(2)}" style="width:100%; padding:6px; font-size:13px; border-radius:6px; border:1px solid #ccc; margin-bottom:10px;">
                <button type="button" onclick="recalculate(${index}, ${gross}, ${eurToTry}, '${reservation.currency}', '${supplierOriginalCurrency}')" style="margin-bottom:10px; background:#ffc107; color:black; padding:6px 12px; border:none; border-radius:6px; cursor:pointer;">Yeniden Hesapla</button>

                <form method='post' action='assign_supplier.php'>
                    <input type='hidden' name='reservation_id' value='${reservation.reservation_id}'>
                    <input type='hidden' name='supplier_id' value='${s.supplier_id}'>
                    <input type='hidden' id='finalCostInput_${index}' name='custom_cost' value='${costInSupplierCurrency.toFixed(2)}'>
                    <input type='hidden' name='custom_cost_currency' value='${supplierOriginalCurrency}'>
                    <button type='submit' style='background:#28a745; color:white; padding:6px 12px; border:none; border-radius:6px; cursor:pointer;'>Seç</button>
                </form>
            </li>`;
        });

        html += `</ul>`;
        document.getElementById('modal-content').innerHTML = html;
        document.getElementById('assignModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    });
});

function recalculate(index, gross, eurToTry, reservationCurrency, supplierCurrency) {
    const customCostInput = document.getElementById(`customCostInput_${index}`);
    const displayCost = document.getElementById(`displayCost_${index}`);
    const displayProfit = document.getElementById(`displayProfit_${index}`);
    const finalCostInput = document.getElementById(`finalCostInput_${index}`);

    const newCostInSupplierCurrency = parseFloat(customCostInput.value || 0);

    let grossInSupplierCurrency;
    if (supplierCurrency === 'TRY') {
        grossInSupplierCurrency = reservationCurrency === 'EUR' ? gross * eurToTry : gross;
    } else { // supplierCurrency === 'EUR'
        grossInSupplierCurrency = reservationCurrency === 'TRY' ? gross / eurToTry : gross;
    }

    const newProfit = grossInSupplierCurrency - newCostInSupplierCurrency;

    displayCost.textContent = `${newCostInSupplierCurrency.toFixed(2)} ${supplierCurrency}`;
    displayProfit.textContent = newProfit.toFixed(2);
    displayProfit.style.color = newProfit >= 10 ? 'green' : newProfit < 0 ? 'purple' : 'red';
    finalCostInput.value = newCostInSupplierCurrency.toFixed(2);
}
</script>


<?php require_once 'includes/footer.php'; ?>