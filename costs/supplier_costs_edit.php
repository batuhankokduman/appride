<?php
require_once '../functions/db.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../includes/menu.php';

$price_rule_id = $_GET['price_rule_id'] ?? null;
if (!$price_rule_id) die('Price Rule ID bulunamadı');

$supplier_id_to_add = $_GET['supplier_id'] ?? null;

$ruleStmt = $pdo->prepare("SELECT pr.*, v.vehicle_name FROM price_rules pr LEFT JOIN vehicles v ON pr.vehicle_id = v.vehicle_id WHERE pr.id = ?");
$ruleStmt->execute([$price_rule_id]);
$rule = $ruleStmt->fetch();

$suppliers = $pdo->query("SELECT id, full_name FROM suppliers WHERE status = 1 ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Extras listesini çekerken extra_service_id'yi de al
$extras = $pdo->query("SELECT id, extra_service_id, service_name FROM extras ORDER BY service_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// JavaScript'te addNewCost fonksiyonunda kullanmak için extra_service_id'yi de içeren ve JSON formatına çevrilmiş ekstralar listesi
$extrasDataForJsButton = htmlspecialchars(json_encode(array_map(function($ex) {
    return ['id' => $ex['id'], 'extra_service_id' => $ex['extra_service_id'], 'service_name' => $ex['service_name']];
}, $extras)), ENT_QUOTES, 'UTF-8');


$savedSuppliersStmt = $pdo->prepare("
    SELECT scp.*, scp.price_per_extra_minute, s.full_name FROM supplier_cost_periods scp
    INNER JOIN suppliers s ON s.id = scp.supplier_id
    WHERE scp.price_rule_id = ?
    ORDER BY s.full_name ASC, scp.valid_from DESC
");
$savedSuppliersStmt->execute([$price_rule_id]);
$savedSuppliersRaw = $savedSuppliersStmt->fetchAll(PDO::FETCH_ASSOC);

$savedSuppliers = [];
foreach ($savedSuppliersRaw as $item) {
    if (!isset($savedSuppliers[$item['supplier_id']])) {
        $savedSuppliers[$item['supplier_id']] = [
            'full_name' => $item['full_name'],
            'costs' => []
        ];
    }
    $savedSuppliers[$item['supplier_id']]['costs'][] = $item;
}

if ($supplier_id_to_add && !isset($savedSuppliers[$supplier_id_to_add])) {
    $newSupplier = array_filter($suppliers, fn($s) => $s['id'] == $supplier_id_to_add);
    if ($newSupplier) {
        $supplier = array_values($newSupplier)[0];
        $savedSuppliers[$supplier['id']] = [
            'full_name' => $supplier['full_name'],
            'costs' => []
        ];
    }
}
?>
<div class="content">
  <h2 class="mb-4">
    <i class="fas fa-tags"></i>
    Price Rule: <?= htmlspecialchars($rule['rule_name']) ?> | Vehicle: <?= htmlspecialchars($rule['vehicle_name']) ?> | Price Type: <?= $rule['price_rule_type_id'] ?>
  </h2>

  <div class="supplier-tabs">
    <?php foreach ($savedSuppliers as $supplier_id => $data): ?>
      <button class="supplier-tab" onclick="toggleSupplier(<?= $supplier_id ?>)"><?= htmlspecialchars($data['full_name']) ?></button>
    <?php endforeach; ?>
    <button class="btn-add-supplier" onclick="openSupplierSelector()"><i class="fas fa-plus"></i></button>
  </div>

  <?php foreach ($savedSuppliers as $supplier_id => $data):
        $costs = $data['costs'];
        $costCount = count($costs);
        $lastCostIndex = $costCount > 0 ? $costCount - 1 : -1;
  ?>
    <div id="supplier-<?= $supplier_id ?>" class="supplier-details" style="display:none;">
      <div class="accordion">
        <?php foreach ($costs as $index => $row): ?>
          <div class="accordion-item">
            <div class="accordion-header"
                 data-open="<?= $index === $lastCostIndex ? 'true' : 'false' ?>"
                 onclick="showForm(this, <?= $supplier_id ?>, <?= $index ?>)">
              <?= date('d.m.Y', strtotime($row['valid_from'])) ?>
            </div>
            <div id="form-<?= $supplier_id ?>-<?= $index ?>" class="form-card" style="display: <?= $index === $lastCostIndex ? 'block' : 'none' ?>;">
              <form onsubmit="return saveForm(this)">
                <input type="hidden" name="price_rule_id" value="<?= $price_rule_id ?>">
                <input type="hidden" name="supplier_id" value="<?= $supplier_id ?>">
                <input type="hidden" name="valid_from" value="<?= $row['valid_from'] ?>">
                <input type="hidden" name="id" value="<?= htmlspecialchars($row['id'] ?? '') ?>">

                <label>Para Birimi:</label>
                <select name="currency">
                    <option value="TRY" <?= ($row['currency'] ?? 'TRY') === 'TRY' ? 'selected' : '' ?>>TRY</option>
                    <option value="EUR" <?= ($row['currency'] ?? 'TRY') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                </select>
                <br><br>

                <?php if ($rule['price_rule_type_id'] == 1): ?>
                  <label>Yetişkin Maliyeti:</label>
                  <input name="cost_per_adult" type="number" step="0.01" value="<?= htmlspecialchars($row['cost_per_adult'] ?? '') ?>">
                  <label>Çocuk Maliyeti:</label>
                  <input name="cost_per_child" type="number" step="0.01" value="<?= htmlspecialchars($row['cost_per_child'] ?? '') ?>">

                <?php elseif ($rule['price_rule_type_id'] == 2): ?>
                  <label>Araç Başı Maliyet:</label>
                  <input name="cost_per_vehicle" type="number" step="0.01" value="<?= htmlspecialchars($row['cost_per_vehicle'] ?? '') ?>">

                <?php elseif ($rule['price_rule_type_id'] == 3): ?>
                  <label>Sabit Açılış Ücreti:</label>
                  <input name="fixed_base_price" type="number" step="0.01" value="<?= htmlspecialchars($row['fixed_base_price'] ?? '') ?>">
                  
                  <label>KM Aralığına Göre Ücret:</label>
                  <div id="km-range-<?= $supplier_id ?>-<?= $index ?>" class="km-range-container"></div>
                  <input type="hidden" name="price_per_km_range" id="km-range-<?= $supplier_id ?>-<?= $index ?>-hidden" value="<?= htmlspecialchars($row['price_per_km_range'] ?? '{}') ?>">
                  <script>
                  document.addEventListener('DOMContentLoaded', function () {
                    try {
                      renderKmRangeInputs("km-range-<?= $supplier_id ?>-<?= $index ?>", JSON.parse(<?= json_encode($row['price_per_km_range'] ?? '{}') ?>));
                    } catch (e) {
                      console.error("KM Aralığı JSON Parse hatası (varolan form):", e, <?= json_encode($row['price_per_km_range'] ?? '{}') ?>);
                      renderKmRangeInputs("km-range-<?= $supplier_id ?>-<?= $index ?>", {});
                    }
                  });
                  </script>

                  <label>Dakika Başına Durak Ücreti:</label>
                  <input name="price_per_minute" type="number" step="0.01" value="<?= htmlspecialchars($row['price_per_minute'] ?? '') ?>">
                  <label>Dakika Başına Ekstra Süre Ücreti:</label>
                  <input name="price_per_extra_minute" type="number" step="0.01" value="<?= htmlspecialchars($row['price_per_extra_minute'] ?? '') ?>">
                <?php endif; ?>

                <?php // Ekstralar bölümü tüm fiyat tipleri için buraya taşındı ?>
                <?php if (!empty($extras)): ?>
                  <label style="margin-top: 15px; display: block; font-weight: bold;">Ekstralar:</label>
                  <div class="extras-wrapper" data-index="<?= $index ?>" data-supplier="<?= $supplier_id ?>">
                    <?php foreach ($extras as $ex):
                      $extrasData = json_decode($row['extras_json'] ?? '{}', true);
                      $checked = isset($extrasData[$ex['extra_service_id']]);  
                      $price = $checked ? htmlspecialchars($extrasData[$ex['extra_service_id']]) : '';
                    ?>
                      <div class="extra-item">
                        <label>
                          <input type="checkbox" class="extra-checkbox" data-extra-id="<?= $ex['extra_service_id'] ?>" <?= $checked ? 'checked' : '' ?>> <?= htmlspecialchars($ex['service_name']) ?>
                        </label>
                        <input type="number" step="0.01" placeholder="Adet fiyatı" class="extra-price" data-extra-id="<?= $ex['extra_service_id'] ?>" value="<?= $price ?>" <?= $checked ? '' : 'style="display:none;"' ?>>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <input type="hidden" name="extras_json" class="extras-json-hidden" value="<?= htmlspecialchars($row['extras_json'] ?? '{}') ?>">
                <?php endif; ?>

                <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Güncelle</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <button class="btn-add-new" 
              data-extras='<?= $extrasDataForJsButton ?>'
              onclick="addNewCost(<?= $supplier_id ?>, <?= $rule['price_rule_type_id'] ?>, this.getAttribute('data-extras'))">
        <i class="fas fa-plus"></i> Yeni Maliyet Ekle
      </button>
      <div id="new-cost-form-<?= $supplier_id ?>"></div>
    </div>
  <?php endforeach; ?>

<div id="supplier-selector" class="modal" style="display:none;">
  <div class="modal-overlay" onclick="closeSupplierSelector()"></div>
  <div class="modal-content enhanced-popup">
    <h3><i class="fas fa-user-plus"></i> Tedarikçi Seç</h3>
    <select id="supplier-dropdown" class="supplier-dropdown">
      <option value="">Seçiniz</option>
      <?php
      foreach ($suppliers as $s):
        if (!isset($savedSuppliers[$s['id']])): ?>
          <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
      <?php endif; endforeach; ?>
    </select>
    <button onclick="addSupplierBox()" class="submit-btn" style="margin-top: 15px;"><i class="fas fa-check"></i> Ekle</button>
  </div>
</div>
</div>

<style>
/* Mevcut stilleriniz */
.accordion-header {
  padding: 10px;
  cursor: pointer;
  background-color: #fff9db;
  border: 1px solid #ddd;
  font-weight: bold;
  transition: background-color 0.3s ease;
}
.accordion-header[data-open="true"] {
  background-color: #d4edda;
}
.supplier-dropdown {
  width: 100%;
  padding: 10px;
  border-radius: 8px;
  border: 1px solid #ccc;
  font-size: 14px;
  margin-top: 10px;
}
.km-range-group {
  display: flex;
  gap: 10px;
  margin-bottom: 10px;
  align-items: center;
}
.km-range-group input { flex: 1; }
.km-range-group button {
  background-color: #dc3545; color: white; border: none;
  padding: 6px 12px; border-radius: 6px; cursor: pointer;
}
.km-range-group button:hover { background-color: #c82333; }
.km-range-add-button {
  background-color: #007bff; color: white; border: none;
  padding: 8px 14px; border-radius: 6px; font-size: 14px;
  margin-bottom: 10px; cursor: pointer;
}
.km-range-add-button:hover { background-color: #0069d9; }
.km-range-container { margin-bottom: 10px; }
.modal {
  position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
  display: flex; justify-content: center; align-items: center; z-index: 9999;
}
.modal-overlay {
  position: absolute; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0, 0, 0, 0.5); z-index: 1;
}
.modal-content.enhanced-popup {
  position: relative; background: #fff; padding: 30px; border-radius: 12px;
  width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 2;
  animation: fadeInScale 0.3s ease;
}
@keyframes fadeInScale {
  from { transform: scale(0.9); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

/* Ekstralar bölümü için stiller */
.extras-wrapper {
  margin-bottom: 15px;
  border: 1px solid #eee;
  padding: 10px;
  border-radius: 4px;
}
.extra-item {
  margin-bottom: 8px;
  display: flex;  
  align-items: center; /* Label ve fiyat inputunu dikeyde ortala */
}
/* Hizalama Düzeltmesi */
.extra-item label {
  display: inline-flex; /* Checkbox ve metni aynı satırda tutar */
  align-items: center;  /* Checkbox ve metni dikeyde ortalar */
  margin-bottom: 0; 
  margin-right: 10px; /* Label ile fiyat inputu arasında boşluk */
  flex-grow: 1; /* Label'ın mümkün olduğunca fazla yer kaplamasını sağlar */
}
.extra-item label input[type="checkbox"] {
  margin-right: 8px; /* Checkbox ile metin arasında boşluk */
  flex-shrink: 0; /* Checkbox'ın küçülmesini engelle */
}
.extra-item input.extra-price {
  /* margin-left: 10px; // Artık label'daki margin-right ile yönetiliyor */
  width: 100px; /* Genişliği biraz azalttık, ihtiyaca göre ayarlanabilir */
  padding: 6px 8px; 
  font-size: 13px; 
  flex-shrink: 0; /* Fiyat inputunun küçülmesini engelle */
}
</style>

<script>
function toggleSupplier(id) {
  document.querySelectorAll('.supplier-details').forEach(div => div.style.display = 'none');
  const el = document.getElementById('supplier-' + id);
  if (el) {
      el.style.display = 'block';
      const firstHeader = el.querySelector('.accordion .accordion-item .accordion-header');
      if(firstHeader) {
          const allHeaders = el.querySelectorAll('.accordion .accordion-header');
          const allFormCards = el.querySelectorAll('.accordion .form-card');

          allHeaders.forEach(h => h.setAttribute('data-open', 'false'));
          allFormCards.forEach(fc => fc.style.display = 'none');
          
          const lastCostItem = el.querySelector('.accordion .accordion-item:last-child');
          if (lastCostItem) {
              const lastCostHeader = lastCostItem.querySelector('.accordion-header');
              const lastCostForm = lastCostItem.querySelector('.form-card');
              if(lastCostHeader && lastCostForm){
                lastCostHeader.setAttribute('data-open', 'true');
                lastCostForm.style.display = 'block';
              }
          }
      }
  }
}

function showForm(clickedHeader, supplierId, index) {
  const supplierDetailsDiv = document.getElementById('supplier-' + supplierId);
  if (!supplierDetailsDiv) return;

  supplierDetailsDiv.querySelectorAll('.form-card').forEach(el => el.style.display = 'none');
  const formToShow = supplierDetailsDiv.querySelector(`#form-${supplierId}-${index}`);
  if (formToShow) {
    formToShow.style.display = 'block';
  }

  supplierDetailsDiv.querySelectorAll('.accordion .accordion-header').forEach(h => h.setAttribute('data-open', 'false'));
  if (clickedHeader) {
    clickedHeader.setAttribute('data-open', 'true');
  }
}

function saveForm(form) {
  const formData = new FormData(form);
  const kmRangeContainerId = form.querySelector('.km-range-container')?.id;
  if (kmRangeContainerId) {
    updateKmJson(kmRangeContainerId);  
  }
  
  // Update extras_json hidden input before sending the form
  const extrasWrapper = form.querySelector('.extras-wrapper');
  if (extrasWrapper) {
      const hiddenInput = extrasWrapper.querySelector('.extras-json-hidden');
      if (hiddenInput) {
          // This call needs to happen *before* formData is sent if you're not explicitly setting it elsewhere.
          // However, setupExtrasInteractions already sets up event listeners for this, so it should be up-to-date.
          // Just to be safe, you could explicitly call updateExtrasJson on this form's wrapper here too.
          const checkboxes = extrasWrapper.querySelectorAll('.extra-checkbox');
          const result = {};
          checkboxes.forEach(cb => {
              const id = cb.getAttribute('data-extra-id');
              const priceInput = extrasWrapper.querySelector(`.extra-price[data-extra-id="${id}"]`);
              if (cb.checked && priceInput && priceInput.value.trim() !== "" && !isNaN(parseFloat(priceInput.value)) && parseFloat(priceInput.value) >= 0) {
                result[id] = parseFloat(priceInput.value);
              }
          });
          hiddenInput.value = JSON.stringify(result);
          formData.set('extras_json', hiddenInput.value); // Ensure it's in the formData
      }
  }

  fetch('supplier_costs_save_ajax.php', {
    method: 'POST',
    body: formData
  })
  .then(res => {
      if (!res.ok) {  
          return res.text().then(text => { throw new Error("Sunucu hatası: " + res.status + " " + text) });
      }
      return res.json();
  })
  .then(data => {
    if (data.success) {
      alert('Kaydedildi!');  
      location.reload();
    } else {
      alert('Hata: ' + (data.message || 'Bilinmeyen bir hata oluştu. Lütfen konsolu kontrol edin.'));
      console.error("Kaydetme Hatası (sunucudan gelen):", data);
    }
  })
  .catch(error => {
      console.error('Kaydetme sırasında JavaScript hatası:', error);
      alert('Kaydetme sırasında bir sorun oluştu: ' + error.message);
  });
  return false;  
}

function escapeHTML(str) {
    if (typeof str !== 'string') return '';
    const p = document.createElement('p');
    p.textContent = str;
    return p.innerHTML;
}

function addNewCost(supplierId, ruleType, extrasListJsonString) {
  const container = document.getElementById('new-cost-form-' + supplierId);
  if (!container) {
      console.error("Yeni maliyet formu için container bulunamadı: new-cost-form-" + supplierId);
      return;
  }
  container.innerHTML = '';  

  let extrasList = [];
  try {
      extrasList = JSON.parse(extrasListJsonString || '[]');  
  } catch (e) {
      console.error("Ekstralar JSON Parse hatası (addNewCost):", e, "Gelen string:", extrasListJsonString);
      alert("Ekstralar yüklenirken bir hata oluştu. Lütfen sayfayı yenileyip tekrar deneyin.");
      return;  
  }

  const today = new Date().toISOString().split('T')[0];
  const formWrapper = document.createElement('div');
  formWrapper.className = 'form-card';
  formWrapper.style.marginTop = '15px';  

  // Ekstralar HTML'ini her zaman oluştur (eğer extrasList boş değilse)
  let extrasHtmlContent = '';
  if (Array.isArray(extrasList) && extrasList.length > 0) {
      extrasHtmlContent += `<label style="margin-top: 15px; display: block; font-weight: bold;">Ekstralar:</label>
                             <div class="extras-wrapper" data-index="new" data-supplier="${supplierId}">`;
      extrasList.forEach(ex => {
        const extraId = ex.extra_service_id || ex.id;  
        const serviceName = ex.service_name || 'Bilinmeyen Ekstra';
        extrasHtmlContent += `<div class="extra-item">
                               <label>
                                 <input type="checkbox" class="extra-checkbox" data-extra-id="${escapeHTML(String(extraId))}"> ${escapeHTML(serviceName)}
                               </label>
                               <input type="number" step="0.01" placeholder="Adet fiyatı" class="extra-price" data-extra-id="${escapeHTML(String(extraId))}" style="display:none;">
                             </div>`;
      });
      extrasHtmlContent += `</div>
                             <input type="hidden" name="extras_json" class="extras-json-hidden" value="{}">`;
  }

  const form = document.createElement('form');
  form.onsubmit = function () { return saveForm(this); };
  let formFieldsHtml = `
    <input type='hidden' name='price_rule_id' value='<?= $price_rule_id ?>'>
    <input type='hidden' name='supplier_id' value='${supplierId}'>
    <label>Geçerlilik Başlangıç Tarihi:</label>
    <input name='valid_from' type='date' value='${today}' required>
    
    <label>Para Birimi:</label>
    <select name="currency">
        <option value="TRY">TRY</option>
        <option value="EUR">EUR</option>
    </select>
    <br><br>
  `;

  if (ruleType == 1) {
    formFieldsHtml += `
      <label>Yetişkin Maliyeti:</label><input name='cost_per_adult' type='number' step='0.01' value="">
      <label>Çocuk Maliyeti:</label><input name='cost_per_child' type='number' step='0.01' value="">
    `;
  } else if (ruleType == 2) {
    formFieldsHtml += `
      <label>Araç Başı Maliyet:</label><input name='cost_per_vehicle' type='number' step='0.01' value="">
    `;
  } else if (ruleType == 3) {
    formFieldsHtml += `
      <label>Sabit Açılış Ücreti:</label><input name='fixed_base_price' type='number' step='0.01' value="">
      <label>KM Aralığına Göre Ücret:</label>
      <div id='km-range-new-${supplierId}' class='km-range-container'></div>
      <input type='hidden' name='price_per_km_range' id='km-range-new-${supplierId}-hidden' value="{}">
      <label>Dakika Başına Durak Ücreti:</label><input name='price_per_minute' type='number' step='0.01' value="">
      <label>Dakika Başına Ekstra Süre Ücreti:</label><input name='price_per_extra_minute' type='number' step='0.01' value="">
    `;
  }
  
  // Kural tipine özel alanlardan sonra ekstraları ekle
  formFieldsHtml += extrasHtmlContent;

  formFieldsHtml += `<button type='submit' class='submit-btn' style="margin-top:15px;"><i class='fas fa-save'></i> Kaydet</button>`;
  form.innerHTML = formFieldsHtml;

  formWrapper.appendChild(form);
  container.appendChild(formWrapper);

  // KM range setup (sadece tip 3 için)
  if (ruleType == 3) {
    setTimeout(() => {  
        renderKmRangeInputs('km-range-new-' + supplierId, {});
    }, 0);
  }
  // Ekstralar setup (eğer ekstralar eklendiyse)
  if (extrasHtmlContent !== '') {
      setTimeout(() => {
          const newExtrasWrapper = formWrapper.querySelector('.extras-wrapper');
          if (newExtrasWrapper) {
              setupExtrasInteractions(newExtrasWrapper);  
          }
      }, 0);
  }
}

function renderKmRangeInputs(containerId, data = {}) {  
  const container = document.getElementById(containerId);
  if (!container) {
      console.error("KM Aralığı container bulunamadı:", containerId);
      return;
  }
  container.innerHTML = '';  
  let parsedData = {};
  if (typeof data === 'string') {
      try {
          parsedData = JSON.parse(data || '{}');
      } catch (e) {
          console.error("KM Aralığı JSON Parse hatası (renderKmRangeInputs):", e, "Gelen string:", data);
          parsedData = {};  
      }
  } else if (typeof data === 'object' && data !== null) {
      parsedData = data;
  }

  Object.entries(parsedData).forEach(([range, value]) => {
    addKmRangeInput(containerId, range, value);
  });
  if(Object.keys(parsedData).length === 0){
      addKmRangeInput(containerId);  
  }
}


function addKmRangeInput(containerId, range = '', value = '') {
  const container = document.getElementById(containerId);
  if (!container) return;

  const existingAddBtn = container.querySelector('.km-range-add-button');
  if (existingAddBtn) existingAddBtn.remove();

  const div = document.createElement('div');
  div.className = 'km-range-group';
  div.innerHTML = `
    <input type="text" placeholder="KM Aralığı (ör: 0-100)" value="${escapeHTML(String(range))}" oninput="updateKmJson('${containerId}')">
    <input type="number" step="0.01" placeholder="Ücret" value="${escapeHTML(String(value))}" oninput="updateKmJson('${containerId}')">
    <button type="button" onclick="this.parentElement.remove(); updateKmJson('${containerId}'); ensureAddButtonExists('${containerId}');">Sil</button>
  `;
  container.appendChild(div);
  updateKmJson(containerId);  

  ensureAddButtonExists(containerId);  
}

function ensureAddButtonExists(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    if (!container.querySelector('.km-range-add-button')) {
        const newAddBtn = document.createElement('button');
        newAddBtn.textContent = '+ KM Aralığı Ekle';
        newAddBtn.type = 'button';
        newAddBtn.className = 'km-range-add-button';
        newAddBtn.onclick = () => addKmRangeInput(containerId);  
        container.appendChild(newAddBtn);
    }
}


function updateKmJson(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;
  const groups = container.querySelectorAll('.km-range-group');
  const result = {};
  groups.forEach(group => {
    const inputs = group.querySelectorAll('input[type="text"], input[type="number"]');  
    if (inputs.length === 2) {  
        const key = inputs[0].value.trim();
        const val = parseFloat(inputs[1].value);
        if (key && !isNaN(val) && val >= 0) {  
          result[key] = val;
        }
    }
  });
  const hidden = document.getElementById(containerId + '-hidden');
  if (hidden) hidden.value = JSON.stringify(result);
}

function setupExtrasInteractions(wrapper) {
  const checkboxes = wrapper.querySelectorAll('.extra-checkbox');
  const form = wrapper.closest('form');  
  if (!form) {
    console.error("Extras wrapper bir form içinde değil:", wrapper);
    return;
  }
  const hiddenInput = form.querySelector('.extras-json-hidden');  
  if (!hiddenInput) {
    console.error("Gizli input .extras-json-hidden bulunamadı, form:", form, "wrapper:", wrapper);
    return;
  }

  const updateExtrasJson = () => {
    const result = {};
    checkboxes.forEach(cb => {
      const id = cb.getAttribute('data-extra-id');  
      const priceInput = wrapper.querySelector(`.extra-price[data-extra-id="${id}"]`);
      if (cb.checked && priceInput && priceInput.value.trim() !== "" && !isNaN(parseFloat(priceInput.value)) && parseFloat(priceInput.value) >= 0) {
        result[id] = parseFloat(priceInput.value);
      }
    });
    hiddenInput.value = JSON.stringify(result);
  };

  checkboxes.forEach(cb => {
    cb.addEventListener('change', () => {
      const id = cb.getAttribute('data-extra-id');
      const priceInput = wrapper.querySelector(`.extra-price[data-extra-id="${id}"]`);
      if (priceInput) {
          if (cb.checked) {
            priceInput.style.display = 'inline-block';
          } else {
            priceInput.style.display = 'none';
            priceInput.value = '';  
          }
      }
      updateExtrasJson();  
    });
    const id = cb.getAttribute('data-extra-id');
    const priceInput = wrapper.querySelector(`.extra-price[data-extra-id="${id}"]`);
    if (priceInput) {
        if (cb.checked) {
          priceInput.style.display = 'inline-block';
        } else {
          priceInput.style.display = 'none';
        }
    }
  });

  wrapper.querySelectorAll('.extra-price').forEach(input => {
    input.addEventListener('input', updateExtrasJson);  
    input.addEventListener('blur', updateExtrasJson);    
  });

  updateExtrasJson();  
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.extras-wrapper').forEach(wrapper => {
    setupExtrasInteractions(wrapper);
  });

  const firstSupplierTabButton = document.querySelector('.supplier-tabs .supplier-tab');  
  if (firstSupplierTabButton) {
      const onclickAttr = firstSupplierTabButton.getAttribute('onclick');
      const matches = onclickAttr ? onclickAttr.match(/toggleSupplier\((\d+)\)/) : null;  
      if (matches && matches[1]) {
          toggleSupplier(parseInt(matches[1]));
      }
  }
});

function openSupplierSelector() {
  document.getElementById('supplier-selector').style.display = 'flex';
}
function closeSupplierSelector() {
  document.getElementById('supplier-selector').style.display = 'none';
}

function addSupplierBox() {
  const id = document.getElementById('supplier-dropdown').value;
  if (!id) return;  
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.set('price_rule_id', '<?= $price_rule_id ?>');  
  currentUrl.searchParams.set('supplier_id', id);
  window.location.href = currentUrl.toString();
}

</script>

<?php require_once '../includes/footer.php'; ?>