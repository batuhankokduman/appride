<?php
require_once '../functions/db.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../includes/menu.php';

$sql = "
SELECT 
    pr.id AS price_rule_id,
    pr.rule_name,
    pr.price_rule_type_id,
    v.vehicle_name,
    GROUP_CONCAT(DISTINCT s.full_name SEPARATOR ', ') AS supplier_names,
    MAX(scp.valid_from) AS latest_valid_from
FROM price_rules pr
LEFT JOIN vehicles v ON pr.vehicle_id = v.vehicle_id
LEFT JOIN supplier_cost_periods scp ON scp.price_rule_id = pr.id
LEFT JOIN suppliers s ON scp.supplier_id = s.id
GROUP BY pr.id, pr.rule_name, pr.price_rule_type_id, v.vehicle_name
ORDER BY pr.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$rules = $stmt->fetchAll();
?>

<div class="content">
    <h2 class="mb-4">📋 Fiyat Kuralı Bazlı Maliyet Yönetimi</h2>

    <table id="mainTable">
        <thead>
            <tr>
                <th data-column-index="0" data-sortable="true" data-sort-type="number">ID <span class="sort-arrow"></span></th>
                <th data-column-index="1" data-sortable="true" data-sort-type="text">Hizmet <small>(Detay için tıklayın)</small> <span class="sort-arrow"></span></th>
                <th data-column-index="2" data-sortable="true" data-sort-type="text">Tedarikçiler <span class="sort-arrow"></span></th>
                <th data-column-index="3" data-sortable="true" data-sort-type="date">Son Başlangıç Tarihi <span class="sort-arrow"></span></th>
                <th data-column-index="4" data-sortable="true" data-sort-type="text">Araç <span class="sort-arrow"></span></th>
                <th data-column-index="5" data-sortable="true" data-sort-type="text">Tür <span class="sort-arrow"></span></th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody id="mainTableBody">
            <?php foreach ($rules as $row): ?>
                <tr>
                    <td data-sort-value="<?= $row['price_rule_id'] ?>"><?= $row['price_rule_id'] ?></td>
                    <td data-sort-value="<?= htmlspecialchars($row['rule_name']) ?>">
                        <a href="javascript:void(0);" class="service-name-toggle" onclick="toggleServiceDetails(<?= $row['price_rule_id'] ?>, this)">
                            <?= htmlspecialchars($row['rule_name']) ?>
                            <i class="fas fa-chevron-down details-arrow"></i>
                        </a>
                    </td>
                    <td data-sort-value="<?= htmlspecialchars($row['supplier_names'] ?? '') ?>"><?= htmlspecialchars($row['supplier_names'] ?? '-') ?></td>
                    <td data-sort-value="<?= $row['latest_valid_from'] ? $row['latest_valid_from'] : '' ?>">
                        <?= $row['latest_valid_from'] ? date('d.m.Y', strtotime($row['latest_valid_from'])) : '-' ?>
                    </td>
                    <td data-sort-value="<?= htmlspecialchars($row['vehicle_name'] ?? '') ?>"><?= htmlspecialchars($row['vehicle_name'] ?? '-') ?></td>
                    <?php
                    $tur_text = '';
                    switch ($row['price_rule_type_id']) {
                        case 1: $tur_text = 'Kişi Başı'; break;
                        case 2: $tur_text = 'Araç Başı'; break;
                        case 3: $tur_text = 'Dinamik'; break;
                        default: $tur_text = 'Bilinmiyor';
                    }
                    ?>
                    <td data-sort-value="<?= htmlspecialchars($tur_text) ?>"><?= htmlspecialchars($tur_text) ?></td>
                    <td><a href="supplier_costs_edit.php?price_rule_id=<?= $row['price_rule_id'] ?>" class="btn-edit"><i class="fas fa-edit"></i> Düzenle</a></td>
                </tr>
                <tr class="details-row" id="details-row-<?= $row['price_rule_id'] ?>" style="display: none;">
                    <td colspan="7"> 
                        <div class="details-content" id="details-content-<?= $row['price_rule_id'] ?>">
                            Yükleniyor...
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.btn-edit {
    background-color: #007bff;
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-edit:hover {
    background-color: #0069d9;
}

/* Detay açma/kapama stilleri */
.service-name-toggle {
    cursor: pointer;
    text-decoration: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: bold;
    color: #333; /* Ana hizmet adı rengi */
}
.service-name-toggle:hover {
    text-decoration: underline;
    color: #007bff;
}
.details-arrow {
    margin-left: 8px;
    transition: transform 0.2s ease-in-out;
}

/* Açılan detay bölümü için stiller */
.details-row td {
    
    background-color: #f8f9fa; 
    border-top: 1px solid #dee2e6;
}
.details-content {
    padding: 10px; /* Ana içerik kutusu için iç boşluk */
    background-color: #ffffff; 
    border: 1px solid #e9ecef; 
    margin: 8px; /* Kenarlardan boşluk */
    border-radius: 4px;
}
.details-content table {
    width: 100%;
    margin-top: 0; /* Tablo üstündeki boşluğu kaldırdık, details-content padding'i yeterli */
    border-collapse: collapse;
    font-size: 0.9em;
}
/* Detay tablosu başlık (th) ve hücre (td) stilleri */
.details-content th, .details-content td {
    border: 1px solid #ddd;
    padding: 8px 10px; /* Dikeyde 8px, yatayda 10px boşluk */
    text-align: left;
    vertical-align: top; /* İçerik üste yaslansın */
}
.details-content th {
    background-color: #e9ecef; /* Başlık arka planı */
    color: #212529; /* Başlık metin rengi - SİYAH'a yakın koyu gri */
    font-weight: bold; /* Başlıklar kalın olsun */
}
/* Maliyet detayları içindeki liste stilleri */
.details-content ul {
    margin-top: 0; 
    margin-bottom: 0;
    margin-left: 0; /* Sol margin'i sıfırla, td padding'i iş görsün */
    padding-left: 20px; /* Madde işaretleri için iç sol boşluk */
    list-style-position: outside; /* Madde işaretleri metin bloğunun dışında kalsın */
}
.details-content li {
    margin-bottom: 5px; /* Liste elemanları arası boşluk */
    padding-left: 2px; /* Madde işareti ile metin arasına hafif boşluk */
}
.details-content li:last-child {
    margin-bottom: 0; /* Son elemanın alt boşluğunu kaldır */
}
.details-content .cost-detail-label {
    font-weight: normal; /* Etiketler için normal kalınlık */
    color: #555; /* Etiket rengi biraz daha açık */
}
.details-content .cost-detail-value {
    font-weight: bold; /* Değerler kalın olsun */
    color: #333;
}
.details-content .extras-list {
    margin-top: 8px; /* Ekstralar başlığı ile üstteki eleman arasına boşluk */
}
.details-content .extras-list .cost-detail-label {
    font-weight: bold; /* "Ekstralar:" etiketi kalın olsun */
    color: #333;
}
.details-content .extras-list ul {
    margin-top: 4px; /* "Ekstralar:" başlığı ile liste arasına boşluk */
    padding-left: 15px; /* Ekstra listesinin iç boşluğu biraz daha az olabilir */
}
.details-content .extras-list li {
    font-size: 0.95em;
    font-weight: normal; /* Ekstra elemanları normal kalınlıkta */
}

/* Tablo Sıralama Stilleri */
#mainTable th[data-sortable="true"] {
    cursor: pointer;
}

#mainTable th[data-sortable="true"] .sort-arrow {
    margin-left: 5px;
    font-size: 0.9em;
    color: #555;
    display: inline-block; /* Ok için sabit alan sağlar */
    width: 1em; 
    text-align: left;
}
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

<script>
function escapeHTML(str) {
    if (typeof str !== 'string') return String(str);
    const p = document.createElement('p');
    p.textContent = str;
    return p.innerHTML;
}

function toggleServiceDetails(priceRuleId, clickedElement) {
    const detailsRow = document.getElementById('details-row-' + priceRuleId);
    const detailsContent = document.getElementById('details-content-' + priceRuleId);
    const arrowIcon = clickedElement.querySelector('.details-arrow');

    if (detailsRow.style.display === 'none') {
        detailsContent.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yükleniyor...';
        fetch(`get_service_cost_details.php?price_rule_id=${priceRuleId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.details.length > 0) {
                    let html = '<table class="table table-sm table-hover">';
                    html += `<thead><tr>
                                <th>Tedarikçi</th>
                                <th>Maliyet Detayları</th>
                                <th>Geçerlilik Başlangıç</th>
                                <th>Geçerlilik Bitiş</th>
                             </tr></thead><tbody>`;
                    data.details.forEach(detail => {
                        html += `<tr>
                                    <td>${escapeHTML(detail.supplier_name)}</td>
                                    <td>${formatCostDetails(detail, data.price_rule_type_id, data.extras_master_list)}</td>
                                    <td>${new Date(detail.valid_from).toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' })}</td>
                                    <td>${detail.valid_to ? new Date(detail.valid_to).toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : 'Aktif'}</td>
                                 </tr>`;
                    });
                    html += '</tbody></table>';
                    detailsContent.innerHTML = html;
                } else if (data.success && data.details.length === 0) {
                    detailsContent.innerHTML = '<p style="padding:10px; text-align:center;">Bu hizmet için kayıtlı maliyet detayı bulunamadı.</p>';
                } else {
                    detailsContent.innerHTML = '<p style="padding:10px; color: red; text-align:center;">Detaylar yüklenirken bir hata oluştu: ' + (escapeHTML(data.message) || 'Bilinmeyen hata') + '</p>';
                }
                detailsRow.style.display = 'table-row';
                if(arrowIcon) {
                    arrowIcon.classList.remove('fa-chevron-down');
                    arrowIcon.classList.add('fa-chevron-up');
                }
            })
            .catch(error => {
                console.error('Error fetching service details:', error);
                detailsContent.innerHTML = `<p style="padding:10px; color: red; text-align:center;">Detaylar yüklenemedi: ${escapeHTML(error.message)}. Lütfen konsolu kontrol edin.</p>`;
                detailsRow.style.display = 'table-row';
                if(arrowIcon) { 
                    arrowIcon.classList.remove('fa-chevron-down');
                    arrowIcon.classList.add('fa-chevron-up');
                }
            });
    } else {
        detailsRow.style.display = 'none';
        if(arrowIcon) {
            arrowIcon.classList.remove('fa-chevron-up');
            arrowIcon.classList.add('fa-chevron-down');
        }
    }
}

function formatCostDetails(detail, priceRuleTypeId, extrasMasterList = {}) {
    let detailsHtml = '<ul>';
    const formatCurrency = (value) => value !== null && value !== undefined && value !== '' ? parseFloat(value).toLocaleString('tr-TR', { style: 'currency', currency: 'TRY' }) : '-';

    if (priceRuleTypeId == 1) { 
        detailsHtml += `<li><span class="cost-detail-label">Yetişkin:</span> <span class="cost-detail-value">${formatCurrency(detail.cost_per_adult)}</span></li>`;
        detailsHtml += `<li><span class="cost-detail-label">Çocuk:</span> <span class="cost-detail-value">${formatCurrency(detail.cost_per_child)}</span></li>`;
    } else if (priceRuleTypeId == 2) { 
        detailsHtml += `<li><span class="cost-detail-label">Araç Başı:</span> <span class="cost-detail-value">${formatCurrency(detail.cost_per_vehicle)}</span></li>`;
    } else if (priceRuleTypeId == 3) { 
        detailsHtml += `<li><span class="cost-detail-label">Sabit Açılış:</span> <span class="cost-detail-value">${formatCurrency(detail.fixed_base_price)}</span></li>`;
        if (detail.price_per_km_range && detail.price_per_km_range !== '{}' && detail.price_per_km_range !== '[]') {
            try {
                const kmRanges = JSON.parse(detail.price_per_km_range);
                if (Object.keys(kmRanges).length > 0) {
                    detailsHtml += '<li><span class="cost-detail-label">KM Aralığı Ücretleri:</span><ul>';
                    for (const range in kmRanges) {
                        detailsHtml += `<li>${escapeHTML(range)}: ${formatCurrency(kmRanges[range])}</li>`;
                    }
                    detailsHtml += '</ul></li>';
                }
            } catch (e) { console.error("KM Range JSON parse error in details:", e, detail.price_per_km_range); }
        }
        detailsHtml += `<li><span class="cost-detail-label">Dakika Başı Durak:</span> <span class="cost-detail-value">${formatCurrency(detail.price_per_minute)}</span></li>`;
        detailsHtml += `<li><span class="cost-detail-label">Dakika Başı Ekstra Süre:</span> <span class="cost-detail-value">${formatCurrency(detail.price_per_extra_minute)}</span></li>`;
    }

    if (detail.extras_json && detail.extras_json !== '{}' && detail.extras_json !== '[]') {
        try {
            const extras = JSON.parse(detail.extras_json);
            if (Object.keys(extras).length > 0) {
                let extraDetailsContent = '';
                for (const extraServiceId in extras) {
                    const extraName = extrasMasterList[extraServiceId] ? escapeHTML(extrasMasterList[extraServiceId]) : `Ekstra ID ${escapeHTML(extraServiceId)}`;
                    extraDetailsContent += `<li>${extraName}: ${formatCurrency(extras[extraServiceId])}</li>`;
                }
                if (extraDetailsContent) {
                    detailsHtml += '<li class="extras-list"><span class="cost-detail-label">Ekstralar:</span><ul>' + extraDetailsContent + '</ul></li>';
                }
            }
        } catch (e) { console.error("Extras JSON parse error in details:", e, detail.extras_json); }
    }
    
    let tempUlContent = '';
    if (priceRuleTypeId == 1) { 
        if (detail.cost_per_adult !== null || detail.cost_per_child !== null) tempUlContent += 'data';
    } else if (priceRuleTypeId == 2) { 
        if (detail.cost_per_vehicle !== null) tempUlContent += 'data';
    } else if (priceRuleTypeId == 3) { 
        if (detail.fixed_base_price !== null || (detail.price_per_km_range && detail.price_per_km_range !== '{}' && detail.price_per_km_range !== '[]') || detail.price_per_minute !== null || detail.price_per_extra_minute !== null) tempUlContent += 'data';
    }
    if (detail.extras_json && detail.extras_json !== '{}' && detail.extras_json !== '[]') {
        try { const extras = JSON.parse(detail.extras_json); if (Object.keys(extras).length > 0) tempUlContent += 'data'; } catch(e){}
    }

    if (tempUlContent === '') {
        detailsHtml += '<li>Bu dönem için özel maliyet bilgisi girilmemiş.</li>';
    }
    detailsHtml += '</ul>';
    return detailsHtml;
}

// YENİ EKLENEN SIRALAMA FONKSİYONLARI
let currentSort = {
    columnIndex: -1,
    ascending: true
};

function getCellValue(rowPair, columnIndex, sortType) {
    const mainRow = rowPair.main;
    const cell = mainRow.cells[columnIndex];
    
    let value;
    if (cell.hasAttribute('data-sort-value')) {
        value = cell.dataset.sortValue;
    } else {
        value = cell.textContent.trim();
    }

    if (sortType === 'number') {
        // '-' veya boş değerleri en düşük sayı olarak kabul et, böylece doğru sıralanır
        if (value === '' || value === '-') return currentSort.ascending ? -Infinity : Infinity;
        return parseFloat(value) || (currentSort.ascending ? -Infinity : Infinity); 
    }
    if (sortType === 'date') {
        if (!value || value === '-') {
            return currentSort.ascending ? -Infinity : Infinity; // Geçersiz veya boş tarihler için
        }
        const date = new Date(value); 
        return !isNaN(date.getTime()) ? date.getTime() : (currentSort.ascending ? -Infinity : Infinity);
    }
    // Metin sıralaması
    return String(value).toLowerCase();
}

function sortTableByColumn(columnIndex, sortType) {
    const tableBody = document.getElementById('mainTableBody');
    if (!tableBody) return;

    const rows = Array.from(tableBody.rows);
    const rowPairs = [];

    // Ana satırları ve onlara bağlı detay satırlarını grupla
    for (let i = 0; i < rows.length; i++) {
        const mainRow = rows[i];
        let detailRow = null;
        if (i + 1 < rows.length && rows[i + 1].classList.contains('details-row')) {
            detailRow = rows[i + 1];
            i++; 
        }
        if (!mainRow.classList.contains('details-row')) {
             rowPairs.push({ main: mainRow, detail: detailRow });
        }
    }

    if (currentSort.columnIndex === columnIndex) {
        currentSort.ascending = !currentSort.ascending;
    } else {
        currentSort.columnIndex = columnIndex;
        currentSort.ascending = true; // Yeni sütuna tıklandığında varsayılan olarak artan sıralama
    }

    rowPairs.sort((pairA, pairB) => {
        const valA = getCellValue(pairA, columnIndex, sortType);
        const valB = getCellValue(pairB, columnIndex, sortType);

        let comparison = 0;
        if (valA > valB) {
            comparison = 1;
        } else if (valA < valB) {
            comparison = -1;
        }
        return currentSort.ascending ? comparison : comparison * -1;
    });

    while (tableBody.firstChild) {
        tableBody.removeChild(tableBody.firstChild);
    }

    rowPairs.forEach(pair => {
        tableBody.appendChild(pair.main);
        if (pair.detail) {
            tableBody.appendChild(pair.detail);
        }
    });

    updateSortArrows(columnIndex, currentSort.ascending);
}

function updateSortArrows(activeIndex, isAscending) {
    document.querySelectorAll('#mainTable th[data-sortable="true"]').forEach(th => {
        const arrowSpan = th.querySelector('.sort-arrow');
        if (arrowSpan) {
            if (parseInt(th.dataset.columnIndex) === activeIndex) {
                arrowSpan.textContent = isAscending ? ' ▲' : ' ▼';
            } else {
                arrowSpan.textContent = ''; // Diğer başlıklardaki okları temizle
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#mainTable th[data-sortable="true"]').forEach(th => {
        th.addEventListener('click', () => {
            const colIndex = parseInt(th.dataset.columnIndex);
            const sortType = th.dataset.sortType || 'text';
            sortTableByColumn(colIndex, sortType);
        });
    });
     // Sayfa yüklendiğinde ID'ye göre DESC sıralı geldiği için ve JS DESC sıralamayı
     // ilk tıklamada ASC yapacağı için, ID sütununu başlangıçta DESC olarak işaretleyebiliriz
     // veya varsayılan sıralama okunu göstermeyebiliriz.
     // Şimdilik, kullanıcı tıklayana kadar ok göstermiyoruz.
     // Eğer başlangıçta ID DESC okunu göstermek isterseniz:
     // const initialIdTh = document.querySelector('#mainTable th[data-column-index="0"] .sort-arrow');
     // if (initialIdTh) initialIdTh.textContent = ' ▼'; 
     // currentSort.columnIndex = 0;
     // currentSort.ascending = false; // Veritabanından DESC geldiği varsayımıyla
});

</script>

<?php require_once '../includes/footer.php'; ?>