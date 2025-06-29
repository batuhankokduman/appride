<?php
require_once '../functions/db.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../includes/menu.php';

$columns = $pdo->query("SHOW COLUMNS FROM reservations")->fetchAll(PDO::FETCH_COLUMN);
$columns[] = 'vehicle_type'; // vehicles tablosundan gelen özel alan
$reservationStatuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed']; // Bu değişken kullanılıyor mu kontrol edilecek, eğer değilse kaldırılabilir.
?>

<div class="content">
    <div class="page-header">
        <h2>➕ Yeni Mesaj Şablonu Oluştur</h2>
        <p class="subtitle">Rezervasyon verilerindeki değişikliklere veya planlanan zamanlara göre otomatik WhatsApp bildirimi tanımlayın.</p>
    </div>

    <div class="template-container">
        <form id="add-template-form" method="POST" action="save_template.php" class="template-form">
            <div class="form-group">
                <label for="title">Şablon Başlığı</label>
                <input type="text" name="title" id="title" placeholder="Örn: Ödeme Onay Bildirimi" required>
                <small class="form-hint">Bu şablon sistemde nasıl görünüsün istiyorsanız o şekilde adlandırın.</small>
            </div>

            <div class="form-group">
                <label for="event">Durum (event)</label>
                <input type="text" name="event" id="event" placeholder="örn: payment_approved"> <small class="form-hint">Bu şablona özel sistemsel tanım (örn: payment_approved). Hatırlatma şablonları için boş bırakılabilir veya özel bir tanım (örn: scheduled_reminder) girilebilir.</small>
            </div>

            <div class="form-group">
                <label for="recipient_type">Mesajın Gönderileceği Kişi</label>
                <select name="recipient_type" id="recipient_type" required>
                    <option value="">Seçin</option>
                    <option value="customer">Müşteri</option>
                    <option value="supplier">Tedarikçi</option>
                     <option value="live">Operasyon Grubu</option>

                </select>
                <small class="form-hint">Bu şablonun kime gönderileceğini belirtin.</small>
            </div>

            <div id="event_trigger_options">
                <div class="form-group">
                    <label for="fields">Tetiklenecek Alanlar</label>
                    <select name="trigger_fields[]" id="fields" class="trigger-fields-select" multiple>
                        <?php foreach ($columns as $col): ?>
                            <option value="<?= htmlspecialchars($col, ENT_QUOTES) ?>"><?= htmlspecialchars($col, ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-hint">Bu alan(lar) değiştiğinde mesaj tetiklenecektir. (Hatırlatma şablonları için geçerli değildir)</small>
                </div>

                <div class="form-group">
                    <label><input type="checkbox" name="trigger_on_new" id="trigger_on_new" value="1"> Yeni rezervasyon eklendiğinde bu şablonu tetikle</label>
                    <small class="form-hint">Bu seçenek işaretliyse sadece yeni kayıt oluştuğunda ve koşullar sağlandığında çalışır. (Hatırlatma şablonları için geçerli değildir)</small>
                </div>

                <div class="form-group">
                    <label><input type="checkbox" name="trigger_on_null_to_value" id="trigger_on_null_to_value" value="1"> Boş (null) alana yeni değer girildiğinde tetikle</label>
                    <small class="form-hint">Önceki değeri boş olan alan(lar)a ilk kez değer atandığında mesaj gönder. (Hatırlatma şablonları için geçerli değildir)</small>
                </div>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_reminder_template" id="is_reminder_template" value="1"> Bu bir hatırlatma mesajı şablonudur</label>
                <small class="form-hint">İşaretlenirse, bu şablon rezervasyonun alınış saatinden belirli bir süre önce otomatik olarak gönderilir.</small>
            </div>

            <div class="form-group" id="reminder_options_group" style="display: none;">
                <label for="reminder_lead_time_minutes">Hatırlatma Zamanı (Alınıştan Önce)</label>
                <select name="reminder_lead_time_minutes" id="reminder_lead_time_minutes" class="form-control">
                    <option value="">Seçiniz...</option>
                    <option value="30">30 Dakika Önce</option>
                    <option value="60">1 Saat Önce</option>
                    <option value="120">2 Saat Önce</option>
                    <option value="180">3 Saat Önce</option>
                    <option value="360">6 Saat Önce</option>
                    <option value="720">12 Saat Önce</option>
                    <option value="1440">1 Gün Önce (24 Saat)</option>
                    <option value="2880">2 Gün Önce (48 Saat)</option>
                </select>
                <small class="form-hint">Rezervasyonun planlanan alınış saatinden ne kadar süre önce hatırlatma mesajının gönderileceğini seçin.</small>
            </div>
            <div class="form-group">
                <label for="conditions">Koşullar (Opsiyonel)</label>
                <div id="condition-container" class="condition-container">
                    <div class="condition-row">
                        <select name="condition_field[]" class="condition-field">
                            <option value="">Seçilen Alan</option>
                            <?php foreach ($columns as $col): ?>
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
                </div>
                <button type="button" id="add-condition-btn" class="add-condition-btn">+ Koşul Ekle</button>
                <small class="form-hint">Şablonun tetiklenmesi için sağlanması gereken ek şartlar. (Hem olay bazlı hem de hatırlatma şablonları için geçerlidir)</small>
            </div>

            <div class="form-group">
                <label for="message">Mesaj Şablonu</label>
                <textarea name="message" id="message" rows="6" placeholder="Merhaba {{customer_first_name}}, rezervasyonunuz {{reservation_status}} oldu." required></textarea>
                <small class="form-hint">Mesaj içinde {{alan_adi}} şeklinde değişken kullanabilirsiniz. Özel değişkenler: {{rez_detay_link}}, {{vehicle_type}}</small>
            </div>

            <div class="form-group">
                <label>Aktiflik Durumu</label><br>
                <label><input type="checkbox" name="is_active" value="1" checked> Bu şablon aktif olsun</label>
                <small class="form-hint">Pasif yapılırsa sistem mesajı tetiklemez.</small>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">📂 Şablonu Kaydet</button>
            </div>
        </form>

        <div class="variable-sidebar">
            <h3>📌 Kullanılabilir Değişkenler</h3>
            <p class="var-info">Tıklayarak kopyalayabilirsiniz</p>
            <div class="var-list">
                <?php foreach ($columns as $col): ?>
                    <div class='var-item' onclick="copyToClipboard('{{<?= htmlspecialchars($col, ENT_QUOTES) ?>}}', this)">{{<?= htmlspecialchars($col, ENT_QUOTES) ?>}}</div>
                <?php endforeach; ?>
                <div class='var-item' onclick="copyToClipboard('{{rez_detay_link}}', this)">{{rez_detay_link}}</div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text, el) {
    navigator.clipboard.writeText(text).then(function () {
        const msg = document.createElement('div');
        msg.textContent = 'Kopyalandı';
        msg.style.position = 'absolute';
        msg.style.background = '#333';
        msg.style.color = '#fff';
        msg.style.padding = '4px 8px';
        msg.style.fontSize = '12px';
        msg.style.borderRadius = '4px';
        const rect = el.getBoundingClientRect();
        msg.style.top = rect.top + window.scrollY - 30 + 'px';
        msg.style.left = rect.left + window.scrollX + 'px';
        msg.style.zIndex = 9999;
        msg.style.opacity = 0.9;
        document.body.appendChild(msg);

        setTimeout(() => {
            msg.remove();
        }, 3000);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const addConditionBtn = document.getElementById('add-condition-btn');
    if (addConditionBtn) {
        addConditionBtn.addEventListener('click', function() {
            var conditionRow = document.createElement('div');
            conditionRow.classList.add('condition-row');
            // $columns değişkenini JavaScript'e güvenli bir şekilde aktarmak için json_encode kullanmak daha iyi bir pratiktir.
            // Ancak mevcut yapınızda PHP içinde script oluşturuyorsanız, htmlspecialchars önemlidir.
            const columnsForJs = <?php echo json_encode($columns); ?>;
            let optionsHtml = '<option value="">Seçilen Alan</option>';
            columnsForJs.forEach(function(col) {
                optionsHtml += `<option value="${col.replace(/"/g, '&quot;')}">${col.replace(/"/g, '&quot;')}</option>`;
            });

            conditionRow.innerHTML = `
                <select name="condition_field[]" class="condition-field">
                    ${optionsHtml}
                </select>
                <select name="condition_operator[]" class="condition-operator">
                    <option value="=">Eşittir (=)</option>
                    <option value=">">Büyüktür (>)</option>
                    <option value="<">Küçüktür (<)</option>
                    <option value="!=">Farklıdır (!=)</option>
                </select>
                <input type="text" name="condition_value[]" class="condition-value" placeholder="Değer girin">
                <button type="button" class="remove-condition-btn">-</button>
            `;

            const conditionContainer = document.getElementById('condition-container');
            if (conditionContainer) {
                 conditionContainer.appendChild(conditionRow);
            }

            const removeBtn = conditionRow.querySelector('.remove-condition-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    conditionRow.remove();
                });
            }
        });
    }

    const isReminderCheckbox = document.getElementById('is_reminder_template');
    const reminderOptionsGroup = document.getElementById('reminder_options_group');
    const reminderLeadTimeSelect = document.getElementById('reminder_lead_time_minutes');
    const eventTriggerOptionsDiv = document.getElementById('event_trigger_options'); // Sadece olay bazlı seçenekleri içeren div
    const eventInput = document.getElementById('event');
    const triggerFieldsSelect = document.getElementById('fields');
    const triggerOnNewCheckbox = document.getElementById('trigger_on_new');
    const triggerOnNullToValueCheckbox = document.getElementById('trigger_on_null_to_value');

    function toggleReminderOptions() {
        if (!isReminderCheckbox) return;

        if (isReminderCheckbox.checked) { // Hatırlatma şablonu
            if(reminderOptionsGroup) reminderOptionsGroup.style.display = 'block';
            if(reminderLeadTimeSelect) reminderLeadTimeSelect.required = true;

            if(eventTriggerOptionsDiv) eventTriggerOptionsDiv.style.display = 'none'; // Olay bazlı tetikleyicileri gizle
            if(eventInput) eventInput.required = false; // Event input'u hatırlatma için zorunlu değil
            if(triggerFieldsSelect) triggerFieldsSelect.required = false;
            if(triggerOnNewCheckbox) triggerOnNewCheckbox.checked = false;
            if(triggerOnNullToValueCheckbox) triggerOnNullToValueCheckbox.checked = false;

        } else { // Olay bazlı şablon (hatırlatma değil)
            if(reminderOptionsGroup) reminderOptionsGroup.style.display = 'none';
            if(reminderLeadTimeSelect) {
                reminderLeadTimeSelect.required = false;
                reminderLeadTimeSelect.value = '';
            }

            if(eventTriggerOptionsDiv) eventTriggerOptionsDiv.style.display = 'block'; // Olay bazlı tetikleyicileri göster
            if(eventInput) eventInput.required = true; // Event input'u olay bazlı için zorunlu
            // triggerFieldsSelect'in required durumu submit'te kontrol edilecek
        }
    }

    if (isReminderCheckbox) {
        isReminderCheckbox.addEventListener('change', toggleReminderOptions);
        toggleReminderOptions(); // Sayfa ilk yüklendiğinde durumu ayarla
    }

    const form = document.getElementById('add-template-form');
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
                // Hatırlatma için event alanı zorunlu değil, boşsa save_template.php'de 'scheduled_reminder' gibi bir değer atanabilir.
                if(eventInput && !eventInput.value.trim()) {
                    // Opsiyonel: Hatırlatma için event boşsa, özel bir değerle doldur
                    // eventInput.value = 'scheduled_reminder';
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

            // Koşul alanları için genel doğrulama (hem hatırlatma hem olay bazlı için)
            const conditionRows = document.querySelectorAll('#condition-container .condition-row');
            for (let i = 0; i < conditionRows.length; i++) {
                const row = conditionRows[i];
                const field = row.querySelector('.condition-field');
                const valueInput = row.querySelector('.condition-value');

                if (field && field.value && valueInput && !valueInput.value.trim()) {
                    alert(`Lütfen ${i+1}. koşul satırı için bir değer girin veya koşul alanını boş bırakın ya da tüm koşul satırını kaldırın.`);
                    event.preventDefault();
                    if(valueInput) valueInput.focus();
                    return;
                }
                if (field && !field.value && valueInput && valueInput.value.trim()) {
                    alert(`Lütfen ${i+1}. koşul satırı için bir alan seçin veya değer alanını boş bırakın ya da tüm koşul satırını kaldırın.`);
                    event.preventDefault();
                    if(field) field.focus();
                    return;
                }
                // Eğer satırda hiçbir şey seçili/girili değilse ama satır boş değilse (örn. sadece operatör seçiliyse) bu da bir hata olabilir.
                // Şimdilik, alan seçiliyse değer de olmalı, değer girildiyse alan da seçili olmalı kontrolü yeterli.
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>