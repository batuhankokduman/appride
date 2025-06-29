<?php
require_once '../functions/db.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../includes/menu.php';

$template_id = $_GET['id'] ?? null;
if (!$template_id || !is_numeric($template_id)) {
    $_SESSION['error_message'] = 'Geçersiz Şablon ID!';
    header('Location: template.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM wa_templates WHERE id = ?");
$stmt->execute([$template_id]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) { // Basitleştirilmiş kontrol
    $_SESSION['error_message'] = 'Şablon bulunamadı!';
    header('Location: template.php');
    exit;
}

// Form elemanlarında kullanılacak DB sütunları
$db_columns_query = $pdo->query("SHOW COLUMNS FROM reservations");
$db_columns = $db_columns_query ? $db_columns_query->fetchAll(PDO::FETCH_COLUMN) : [];

// Kenar çubuğunda gösterilecek değişkenler (DB sütunları + özel değişkenler)
$sidebar_variables = $db_columns; // Önce DB sütunlarını al
$sidebar_variables[] = 'rez_detay_link';
$sidebar_variables[] = 'ted_detay_link';// Sonra özel değişkeni ekle
$sidebar_variables[] = 'vehicle_type'; // araç tipi özel alanı


// Mevcut şablon verilerini işle
$current_trigger_fields = !empty($template['trigger_fields']) ? explode(',', $template['trigger_fields']) : [];
$current_conditions = !empty($template['condition_json']) ? json_decode($template['condition_json'], true) : [];
if (!is_array($current_conditions)) { // JSON decode başarısız olursa veya null ise boş dizi yap
    $current_conditions = [];
}

$is_reminder_template_checked = !empty($template['is_reminder_template']);
$current_reminder_lead_time = $template['reminder_lead_time_minutes'] ?? '';
?>

<div class="content">
    <div class="page-header">
        <h2>✏️ Şablon Düzenle</h2>
        <p class="subtitle">Bu sayfada mevcut WhatsApp Şablonunu güncelleyebilirsiniz.</p>
    </div>

    <div class="template-container">
        <form id="edit-template-form" method="POST" action="update_template.php" class="template-form">
            <input type="hidden" name="id" value="<?= (int) $template['id'] ?>">

            <div class="form-group">
                <label for="title">Şablon Başlığı</label>
                <input type="text" name="title" id="title" value="<?= htmlspecialchars($template['template_title'] ?? '') ?>" required>
                <small class="form-hint">Bu şablon sistemde nasıl görünüsün istiyorsanız o şekilde adlandırın.</small>
            </div>

            <div class="form-group">
                <label for="event">Durum (event)</label>
                <input type="text" name="event" id="event" value="<?= htmlspecialchars($template['event'] ?? '') ?>"> <small class="form-hint">Bu şablona özel sistemsel tanım (örn: payment_approved). Hatırlatma şablonları için boş bırakılabilir veya özel bir tanım (örn: scheduled_reminder) girilebilir.</small>
            </div>

            <div class="form-group">
                <label for="recipient_type">Mesajın Gönderileceği Kişi</label>
                <select name="recipient_type" id="recipient_type" required>
                    <option value="">Seçin</option>
                    <option value="customer" <?= ($template['recipient_type'] ?? '') === 'customer' ? 'selected' : '' ?>>Müşteri</option>
                    <option value="supplier" <?= ($template['recipient_type'] ?? '') === 'supplier' ? 'selected' : '' ?>>Tedarikçi</option>
                    <option value="live" <?= ($template['recipient_type'] ?? '') === 'live' ? 'selected' : '' ?>>Operasyon Grubu</option>
                </select>
                <small class="form-hint">Bu şablonun kime gönderileceğini belirtin.</small>
            </div>

            <div id="event_trigger_options">
                <div class="form-group">
                    <label for="fields">Tetiklenecek Alanlar</label>
                    <select name="trigger_fields[]" id="fields" class="trigger-fields-select" multiple>
                        <?php foreach ($db_columns as $col): ?>
                            <option value="<?= htmlspecialchars($col, ENT_QUOTES) ?>" <?= in_array($col, $current_trigger_fields) ? 'selected' : '' ?>><?= htmlspecialchars($col, ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint">Bu alan(lar) değiştiğinde mesaj tetiklenecektir. (Hatırlatma şablonları için geçerli değildir)</small>
                </div>

                <div class="form-group">
                    <label><input type="checkbox" name="trigger_on_new" id="trigger_on_new" value="1" <?= !empty($template['trigger_on_new']) ? 'checked' : '' ?>> Yeni rezervasyon eklendiğinde bu şablonu tetikle</label>
                    <small class="form-hint">Bu seçenek işaretliyse sadece yeni kayıt oluştuğunda ve koşullar sağlandığında çalışır. (Hatırlatma şablonları için geçerli değildir)</small>
                </div>

                <div class="form-group">
                    <label><input type="checkbox" name="trigger_on_null_to_value" id="trigger_on_null_to_value" value="1" <?= !empty($template['trigger_on_null_to_value']) ? 'checked' : '' ?>> Boş (null) alana yeni değer girildiğinde tetikle</label>
                    <small class="form-hint">Önceki değeri boş olan alan(lar)a ilk kez değer atandığında mesaj gönder. (Hatırlatma şablonları için geçerli değildir)</small>
                </div>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_reminder_template" id="is_reminder_template" value="1" <?= $is_reminder_template_checked ? 'checked' : '' ?>> Bu bir hatırlatma mesajı şablonudur</label>
                <small class="form-hint">İşaretlenirse, bu şablon rezervasyonun alınış saatinden belirli bir süre önce otomatik olarak gönderilir.</small>
            </div>

            <div class="form-group" id="reminder_options_group" style="display: <?= $is_reminder_template_checked ? 'block' : 'none' ?>;">
                <label for="reminder_lead_time_minutes">Hatırlatma Zamanı (Alınıştan Önce)</label>
                <select name="reminder_lead_time_minutes" id="reminder_lead_time_minutes" class="form-control">
                    <option value="">Seçiniz...</option>
                    <?php
                    $reminder_times = [
                        "30" => "30 Dakika Önce", "60" => "1 Saat Önce", "120" => "2 Saat Önce",
                        "180" => "3 Saat Önce", "360" => "6 Saat Önce", "720" => "12 Saat Önce",
                        "1440" => "1 Gün Önce (24 Saat)", "2880" => "2 Gün Önce (48 Saat)"
                    ];
                    foreach ($reminder_times as $value => $label): ?>
                        <option value="<?= $value ?>" <?= (string)$current_reminder_lead_time === (string)$value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint">Rezervasyonun planlanan alınış saatinden ne kadar süre önce hatırlatma mesajının gönderileceğini seçin.</small>
            </div>
            <div class="form-group">
                <label for="conditions">Koşullar (Opsiyonel)</label>
                <div id="condition-container" class="condition-container">
                    <?php if (!empty($current_conditions)): ?>
                        <?php foreach ($current_conditions as $index => $condition): ?>
                            <div class="condition-row">
                                <select name="condition_field[]" class="condition-field"> <option value="">Seçilen Alan</option>
                                    <?php foreach ($db_columns as $col): ?>
                                        <option value="<?= htmlspecialchars($col, ENT_QUOTES) ?>" <?= ($condition['field'] ?? '') === $col ? 'selected' : '' ?>><?= htmlspecialchars($col, ENT_QUOTES) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="condition_operator[]" class="condition-operator">
                                    <option value="=" <?= ($condition['operator'] ?? '') === '=' ? 'selected' : '' ?>>Eşittir (=)</option>
                                    <option value=">" <?= ($condition['operator'] ?? '') === '>' ? 'selected' : '' ?>>Büyüktür (>)</option>
                                    <option value="<" <?= ($condition['operator'] ?? '') === '<' ? 'selected' : '' ?>>Küçüktür (<)</option>
                                    <option value="!=" <?= ($condition['operator'] ?? '') === '!=' ? 'selected' : '' ?>>Farklıdır (!=)</option>
                                </select>
                                <input type="text" name="condition_value[]" value="<?= htmlspecialchars($condition['value'] ?? '') ?>" class="condition-value" placeholder="Değer girin">
                                <button type="button" class="remove-condition-btn">-</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: // Hiç koşul yoksa, add.php'deki gibi boş bir satır gösterelim (isteğe bağlı) ?>
                         <div class="condition-row" style="display:none;"> <select name="condition_field[]" class="condition-field">
                                <option value="">Seçilen Alan</option>
                                <?php foreach ($db_columns as $col): ?>
                                    <option value="<?= htmlspecialchars($col, ENT_QUOTES) ?>"><?= htmlspecialchars($col, ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="condition_operator[]" class="condition-operator">
                                <option value="=">Eşittir (=)</option>
                                <option value=">">Büyüktür (>)</option>
                                <option value="<">Küçüktür (<)</option>
                                <option value="!=">Farklıdır (!=)</option>
                            </select>
                            <input type="text" name="condition_value[]" class="condition-value" placeholder="Değer girin">
                            <button type="button" class="remove-condition-btn">-</button>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" id="add-condition-btn" class="add-condition-btn">+ Koşul Ekle</button>
                <small class="form-hint">Şablonun tetiklenmesi için sağlanması gereken ek şartlar. (Hem olay bazlı hem de hatırlatma şablonları için geçerlidir)</small>
            </div>

            <div class="form-group">
                <label for="message">Mesaj Şablonu</label>
                <textarea name="message" id="message" rows="6" required><?= htmlspecialchars($template['template_body'] ?? '') ?></textarea>
                <small class="form-hint">Mesaj içinde {{alan_adi}} şeklinde değişken kullanabilirsiniz. Özel değişkenler: {{rez_detay_link}}, {{vehicle_type}}</small>
            </div>

            <div class="form-group">
                <label>Aktiflik Durumu</label><br>
                <label><input type="checkbox" name="is_active" value="1" <?= !empty($template['is_active']) ? 'checked' : '' ?>> Bu şablon aktif olsun</label>
                <small class="form-hint">Pasif yapılırsa sistem mesajı tetiklemez.</small>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">📂 Şablonu Güncelle</button>
            </div>
        </form>

        <div class="variable-sidebar">
            <h3>📌 Kullanılabilir Değişkenler</h3>
            <p class="var-info">Tıklayarak kopyalayabilirsiniz</p>
            <div class="var-list">
                <?php foreach ($sidebar_variables as $col): // Kenar çubuğu için ayrı değişken listesi ?>
                    <div class='var-item' onclick="copyToClipboard('{{<?= htmlspecialchars($col, ENT_QUOTES) ?>}}', this)">{{<?= htmlspecialchars($col, ENT_QUOTES) ?>}}</div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
.copy-notice { /* ... (kullanıcının verdiği stilden kopyalanacak) ... */ }
.var-item { /* ... */ }
.condition-row { display: flex; align-items: center; margin-bottom: 10px; }
.condition-row select, .condition-row input { margin-right: 10px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
.condition-row input { flex-grow: 1; }
.remove-condition-btn, .add-condition-btn { padding: 8px 12px; color: white; border: none; border-radius: 4px; cursor: pointer; min-width: 40px; text-align: center; }
.remove-condition-btn { background-color: #f44336; }
.add-condition-btn { background-color: #4CAF50; margin-top: 10px; }
.remove-condition-btn:hover { background-color: #d32f2f; }
.add-condition-btn:hover { background-color: #45a049; }
</style>

<script>
// copyToClipboard fonksiyonu (add.php veya kullanıcının verdiği scriptten)
function copyToClipboard(text, el) {
    navigator.clipboard.writeText(text).then(function () {
        // ... (add.php'deki copyToClipboard implementasyonu)
        const msg = document.createElement('div');
        msg.textContent = 'Kopyalandı';
        msg.style.position = 'absolute'; // Örnek stil, add.php'deki gibi detaylandırın
        // ... kalan stil ve timeout mantığı
        document.body.appendChild(msg);
        setTimeout(() => { msg.remove(); }, 3000);
    });
}


document.addEventListener('DOMContentLoaded', function() {
    const conditionContainer = document.getElementById('condition-container');
    const dbColumnsForJs = <?php echo json_encode($db_columns); ?>; // Sadece DB sütunları

    function addConditionRowHtml(condition = null) {
        let newRow = document.createElement('div');
        newRow.classList.add('condition-row');

        let optionsHtml = '<option value="">Seçilen Alan</option>';
        dbColumnsForJs.forEach(function(col) {
            const escapedCol = String(col).replace(/"/g, '&quot;');
            const selected = condition && condition.field === col ? 'selected' : '';
            optionsHtml += `<option value="${escapedCol}" ${selected}>${escapedCol}</option>`;
        });

        const operator = condition ? condition.operator : '=';
        const value = condition ? condition.value : '';

        newRow.innerHTML = `
            <select name="condition_field[]" class="condition-field">${optionsHtml}</select>
            <select name="condition_operator[]" class="condition-operator">
                <option value="=" ${operator === '=' ? 'selected' : ''}>Eşittir (=)</option>
                <option value=">" ${operator === '>' ? 'selected' : ''}>Büyüktür (>)</option>
                <option value="<" ${operator === '<' ? 'selected' : ''}>Küçüktür (<)</option>
                <option value="!=" ${operator === '!=' ? 'selected' : ''}>Farklıdır (!=)</option>
            </select>
            <input type="text" name="condition_value[]" class="condition-value" placeholder="Değer girin" value="${String(value).replace(/"/g, '&quot;')}">
            <button type="button" class="remove-condition-btn">-</button>
        `;
        if (conditionContainer) {
            conditionContainer.appendChild(newRow);
            const removeBtn = newRow.querySelector('.remove-condition-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function() { newRow.remove(); });
            }
        }
    }
    
    // Mevcut koşulları yüklemek için bu fonksiyonu kullanamayız çünkü PHP zaten onları render etti.
    // Sadece yeni eklenenler için.
    // Mevcut remove butonlarına listener ekle:
    if (conditionContainer) {
        conditionContainer.querySelectorAll('.remove-condition-btn').forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.condition-row').remove();
            });
        });
    }


    const addConditionBtn = document.getElementById('add-condition-btn');
    if (addConditionBtn) {
        addConditionBtn.addEventListener('click', function() {
            // Eğer hiç koşul yoksa ve PHP tarafından gizli bir ilk satır render edildiyse, onu göster/kullan
            const firstHiddenRow = conditionContainer ? conditionContainer.querySelector('.condition-row[style*="display:none"]') : null;
            if (firstHiddenRow) {
                firstHiddenRow.style.display = 'flex'; // veya 'block' veya ''
            } else {
                 addConditionRowHtml(); // Tamamen yeni bir satır ekle
            }
        });
    }
    
    // Hatırlatma ve olay bazlı alan yönetimi (add.php'den uyarlandı)
    const isReminderCheckbox = document.getElementById('is_reminder_template');
    const reminderOptionsGroup = document.getElementById('reminder_options_group');
    const reminderLeadTimeSelect = document.getElementById('reminder_lead_time_minutes');
    const eventTriggerOptionsDiv = document.getElementById('event_trigger_options');
    const eventInput = document.getElementById('event');
    const triggerFieldsSelect = document.getElementById('fields');
    const triggerOnNewCheckbox = document.getElementById('trigger_on_new');
    const triggerOnNullToValueCheckbox = document.getElementById('trigger_on_null_to_value');

    function toggleReminderOptions() {
        if (!isReminderCheckbox) return;

        const isReminder = isReminderCheckbox.checked;

        if (reminderOptionsGroup) reminderOptionsGroup.style.display = isReminder ? 'block' : 'none';
        if (reminderLeadTimeSelect) reminderLeadTimeSelect.required = isReminder;
        
        if (eventTriggerOptionsDiv) eventTriggerOptionsDiv.style.display = isReminder ? 'none' : 'block';
        if (eventInput) eventInput.required = !isReminder;
        if (triggerFieldsSelect) triggerFieldsSelect.required = !isReminder; // Bu submit'te daha detaylı kontrol edilecek

        if (isReminder) {
            if (triggerOnNewCheckbox) triggerOnNewCheckbox.checked = false;
            if (triggerOnNullToValueCheckbox) triggerOnNullToValueCheckbox.checked = false;
            // Hatırlatma seçildiğinde triggerFieldsSelect'in seçimlerini temizlemek isteyebilirsiniz
            // if (triggerFieldsSelect) Array.from(triggerFieldsSelect.options).forEach(opt => opt.selected = false);
        } else {
             if (reminderLeadTimeSelect) reminderLeadTimeSelect.value = '';
        }
    }

    if (isReminderCheckbox) {
        isReminderCheckbox.addEventListener('change', toggleReminderOptions);
        // Sayfa yüklendiğinde mevcut duruma göre ayarla (önemli!)
        toggleReminderOptions();
    }

    // Form Gönderim Doğrulaması (add.php'den uyarlandı)
    const form = document.getElementById('edit-template-form'); // Form ID'sini güncelle
    if (form) {
        form.addEventListener('submit', function(event) {
            const isReminder = isReminderCheckbox ? isReminderCheckbox.checked : false;

            if (isReminder) {
                if (reminderLeadTimeSelect && !reminderLeadTimeSelect.value) {
                    alert('Hatırlatma şablonu için lütfen bir "Hatırlatma Zamanı" seçin.');
                    event.preventDefault();
                    if(reminderLeadTimeSelect) reminderLeadTimeSelect.focus();
                    return;
                }
            } else { // Olay bazlı şablon
                if (eventInput && !eventInput.value.trim()) {
                     alert('Lütfen "Durum (event)" alanını doldurun.');
                     event.preventDefault();
                     if(eventInput) eventInput.focus();
                     return;
                }

                const isTriggerOnNew = triggerOnNewCheckbox ? triggerOnNewCheckbox.checked : false;
                const isTriggerOnNull = triggerOnNullToValueCheckbox ? triggerOnNullToValueCheckbox.checked : false;
                let fieldsSelected = false;
                if (triggerFieldsSelect) {
                    for (let i = 0; i < triggerFieldsSelect.options.length; i++) {
                        if (triggerFieldsSelect.options[i].selected) {
                            fieldsSelected = true;
                            break;
                        }
                    }
                }
                if (!isTriggerOnNew && !isTriggerOnNull && !fieldsSelected) {
                    alert('Olay bazlı şablon için lütfen en az bir "Tetiklenecek Alan" seçin veya "Yeni rezervasyon eklendiğinde" ya da "Boş (null) alana yeni değer girildiğinde" seçeneklerinden birini işaretleyin.');
                    event.preventDefault();
                    if(triggerFieldsSelect) triggerFieldsSelect.focus();
                    return;
                }
            }

            // Koşul alanları için genel doğrulama
            const conditionRows = document.querySelectorAll('#condition-container .condition-row');
            conditionRows.forEach((row, index) => {
                // Eğer satır görünür durumdaysa (örn. display:none değilse) kontrol et
                if (row.offsetParent === null) return; // Görünmüyorsa atla

                const field = row.querySelector('.condition-field');
                const valueInput = row.querySelector('.condition-value');

                if (field && field.value && valueInput && !valueInput.value.trim()) {
                    alert(`Lütfen ${index + 1}. koşul satırı için bir değer girin veya koşul alanını boş bırakın ya da tüm koşul satırını kaldırın.`);
                    event.preventDefault();
                    if(valueInput) valueInput.focus();
                    return; // Hata durumunda fonksiyondan çık (forEach için bu işe yaramaz, event.preventDefault() yeterli)
                }
                if (field && !field.value && valueInput && valueInput.value.trim()) {
                     alert(`Lütfen ${index + 1}. koşul satırı için bir alan seçin veya değer alanını boş bırakın ya da tüm koşul satırını kaldırın.`);
                    event.preventDefault();
                    if(field) field.focus();
                    return;
                }
            });
            if (event.defaultPrevented) return; // Eğer zaten bir hata varsa devam etme
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>