<?php
// save_template.php
session_start();
require_once '../functions/db.php'; // Veritabanı bağlantısı

// Formdan gelen veriler
$template_title = trim($_POST['title'] ?? '');
$event = trim($_POST['event'] ?? '');
$recipient_type = $_POST['recipient_type'] ?? 'customer';

// Yeni hatırlatma alanları
$is_reminder_template = isset($_POST['is_reminder_template']) ? 1 : 0;
$reminder_lead_time_minutes_raw = $_POST['reminder_lead_time_minutes'] ?? null;
$reminder_lead_time_minutes = null;

if ($is_reminder_template == 1) {
    // Bu bir hatırlatma şablonu
    if (!empty($reminder_lead_time_minutes_raw) && is_numeric($reminder_lead_time_minutes_raw)) {
        $reminder_lead_time_minutes = (int)$reminder_lead_time_minutes_raw;
    } else {
        // Hatırlatma şablonu ama süre seçilmemişse hata ver (add.php'de JS kontrolü var ama sunucu tarafı da önemli)
        $_SESSION['error_message'] = "Hatırlatma şablonu için lütfen geçerli bir hatırlatma süresi seçin.";
        header("Location: add.php"); // Kullanıcıyı forma geri yönlendir
        exit;
    }

    // Hatırlatma şablonu ise, olay bazlı tetikleyiciler genellikle kullanılmaz.
    // Bunları sıfırlayalım veya boş bırakalım.
    $trigger_fields = []; // Boş dizi
    $trigger_fields_str = '';
    $trigger_on_new = 0;
    $trigger_on_null_to_value = 0;

    // Hatırlatma şablonları için 'event' alanı boşsa, varsayılan bir değer atayabiliriz.
    if (empty($event)) {
        $event = 'scheduled_reminder'; // Örneğin
    }

} else {
    // Bu bir olay bazlı şablon (hatırlatma değil)
    $trigger_fields = $_POST['trigger_fields'] ?? [];
    $trigger_fields_str = implode(',', array_map('trim', $trigger_fields)); // Her bir alanı trim'le
    $trigger_on_new = isset($_POST['trigger_on_new']) ? 1 : 0;
    $trigger_on_null_to_value = isset($_POST['trigger_on_null_to_value']) ? 1 : 0;
    $reminder_lead_time_minutes = null; // Olay bazlıda bu null olmalı

    // Olay bazlı şablonlar için 'event' alanı zorunlu olmalı
    if (empty($event)) {
        $_SESSION['error_message'] = "Olay bazlı şablonlar için 'Durum (event)' alanı zorunludur.";
        header("Location: add.php");
        exit;
    }
    // Olay bazlı şablonlarda ya trigger_fields ya da diğer trigger checkbox'larından biri aktif olmalı
    if (empty($trigger_fields_str) && $trigger_on_new == 0 && $trigger_on_null_to_value == 0) {
         $_SESSION['error_message'] = "Olay bazlı şablon için lütfen tetiklenecek alanları veya tetikleme yöntemini (yeni kayıt/boş alan) belirtin.";
         header("Location: add.php");
         exit;
    }
}

// Koşullar (hem hatırlatma hem de olay bazlı için ortak)
$condition_fields = $_POST['condition_field'] ?? [];
$condition_operators = $_POST['condition_operator'] ?? [];
$condition_values = $_POST['condition_value'] ?? [];
$conditions_data = [];

for ($i = 0; $i < count($condition_fields); $i++) {
    // Sadece alan adı doluysa ve operatör varsa koşulu ekle
    if (!empty(trim($condition_fields[$i])) && !empty(trim($condition_operators[$i])) ) {
        // Değer boş olabilir (örn: IS NULL, IS NOT NULL gibi operatörler eklenecekse)
        // Şimdilik değerin de olması gerektiğini varsayıyoruz mevcut operatörlere göre
        // Ama add.php'deki JS validasyonu sadece alan ve değerin birlikte varlığını kontrol ediyordu (biri varsa diğeri de olmalı).
        // Eğer sadece field seçilip value boş bırakılabiliyorsa ve bu geçerliyse, aşağıdaki kontrol gevşetilebilir.
        // Şimdilik add.php'deki JS ile uyumlu olması için, field varsa value'nun da (boş bile olsa) POST edileceğini varsayıyoruz.
        $conditions_data[] = [
            'field' => trim($condition_fields[$i]),
            'operator' => trim($condition_operators[$i]),
            'value' => trim($condition_values[$i] ?? '') // Değer boş olabilir
        ];
    }
}
// Eğer hiçbir geçerli koşul yoksa boş bir JSON array yerine null veya boş string kaydedebilirsiniz.
// Ancak JSON array ('[]') genellikle daha tutarlıdır.
$condition_json = json_encode($conditions_data);


$template_body = trim($_POST['message'] ?? '');
$is_active = isset($_POST['is_active']) ? 1 : 0;

// Temel doğrulamalar
if (empty($template_title)) {
    $_SESSION['error_message'] = "Şablon başlığı boş bırakılamaz.";
    header("Location: add.php");
    exit;
}
if (empty($template_body)) {
    $_SESSION['error_message'] = "Mesaj şablonu boş bırakılamaz.";
    header("Location: add.php");
    exit;
}


// SQL sorgusu (yeni alanlar eklendi: is_reminder_template, reminder_lead_time_minutes)
$sql = "INSERT INTO wa_templates (
            template_title,
            event,
            recipient_type,
            trigger_fields,
            condition_json,
            template_body,
            is_active,
            trigger_on_new,
            trigger_on_null_to_value,
            is_reminder_template,          -- YENİ
            reminder_lead_time_minutes,    -- YENİ
            created_at
        ) VALUES (
            :template_title,
            :event,
            :recipient_type,
            :trigger_fields,
            :condition_json,
            :template_body,
            :is_active,
            :trigger_on_new,
            :trigger_on_null_to_value,
            :is_reminder_template,          -- YENİ
            :reminder_lead_time_minutes,    -- YENİ
            NOW()
        )";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':template_title', $template_title);
    $stmt->bindParam(':event', $event);
    $stmt->bindParam(':recipient_type', $recipient_type);
    $stmt->bindParam(':trigger_fields', $trigger_fields_str);
    $stmt->bindParam(':condition_json', $condition_json);
    $stmt->bindParam(':template_body', $template_body);
    $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
    $stmt->bindParam(':trigger_on_new', $trigger_on_new, PDO::PARAM_INT);
    $stmt->bindParam(':trigger_on_null_to_value', $trigger_on_null_to_value, PDO::PARAM_INT);
    $stmt->bindParam(':is_reminder_template', $is_reminder_template, PDO::PARAM_INT); // YENİ

    if ($reminder_lead_time_minutes === null) {
        $stmt->bindParam(':reminder_lead_time_minutes', $reminder_lead_time_minutes, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':reminder_lead_time_minutes', $reminder_lead_time_minutes, PDO::PARAM_INT);
    }

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Şablon başarıyla kaydedildi!";
    } else {
        $_SESSION['error_message'] = "Şablon kaydedilemedi. Veritabanı hatası: " . implode(" ", $stmt->errorInfo());
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Veritabanı bağlantı hatası: " . $e->getMessage();
    // Geliştirme aşamasında detaylı hata loglamak iyi olabilir: error_log($e->getMessage());
}

header("Location: template.php"); // Başarı veya hata durumunda yönlendirilecek sayfa
exit;