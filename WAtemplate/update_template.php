<?php
// update_template.php
session_start();
require_once '../functions/db.php';

// Formdan gelen veriler
$template_id = $_POST['id'] ?? null;
if (!$template_id || !is_numeric($template_id)) {
    $_SESSION['error_message'] = "Geçersiz şablon ID!";
    header("Location: template.php");
    exit;
}

$template_title = trim($_POST['title'] ?? '');
$event = trim($_POST['event'] ?? '');
$recipient_type = $_POST['recipient_type'] ?? 'customer';

// Yeni hatırlatma alanları
$is_reminder_template = isset($_POST['is_reminder_template']) ? 1 : 0;
$reminder_lead_time_minutes_raw = $_POST['reminder_lead_time_minutes'] ?? null;
$reminder_lead_time_minutes = null;

if ($is_reminder_template == 1) {
    // Bu bir hatırlatma şablonu
    if (!empty($reminder_lead_time_minutes_raw) && is_numeric($reminder_lead_time_minutes_raw) && (int)$reminder_lead_time_minutes_raw > 0) {
        $reminder_lead_time_minutes = (int)$reminder_lead_time_minutes_raw;
    } else {
        $_SESSION['error_message'] = "Hatırlatma şablonu için lütfen geçerli bir hatırlatma süresi seçin.";
        header("Location: edit_template.php?id=" . $template_id);
        exit;
    }

    // Hatırlatma şablonu ise, olay bazlı tetikleyiciler sıfırlanır.
    $trigger_fields_str = '';
    $trigger_on_new = 0;
    $trigger_on_null_to_value = 0;

    // Hatırlatma şablonları için 'event' alanı boşsa, varsayılan bir değer atanabilir.
    if (empty($event)) {
        $event = 'scheduled_reminder'; // Örneğin
    }
} else {
    // Bu bir olay bazlı şablon (hatırlatma değil)
    $trigger_fields_posted = $_POST['trigger_fields'] ?? [];
    $trigger_fields_str = implode(',', array_map('trim', $trigger_fields_posted));
    $trigger_on_new = isset($_POST['trigger_on_new']) ? 1 : 0;
    $trigger_on_null_to_value = isset($_POST['trigger_on_null_to_value']) ? 1 : 0;
    $reminder_lead_time_minutes = null; // Olay bazlıda bu null olmalı

    // Olay bazlı şablonlar için 'event' alanı zorunlu olmalı
    if (empty($event)) {
        $_SESSION['error_message'] = "Olay bazlı şablonlar için 'Durum (event)' alanı zorunludur.";
        header("Location: edit_template.php?id=" . $template_id);
        exit;
    }
    // Olay bazlı şablonlarda ya trigger_fields ya da diğer trigger checkbox'larından biri aktif olmalı
    if (empty($trigger_fields_str) && $trigger_on_new == 0 && $trigger_on_null_to_value == 0) {
         $_SESSION['error_message'] = "Olay bazlı şablon için lütfen tetiklenecek alanları veya tetikleme yöntemini (yeni kayıt/boş alan) belirtin.";
         header("Location: edit_template.php?id=" . $template_id);
         exit;
    }
}

// Koşullar (hem hatırlatma hem de olay bazlı için ortak)
$condition_fields = $_POST['condition_field'] ?? [];
$condition_operators = $_POST['condition_operator'] ?? [];
$condition_values = $_POST['condition_value'] ?? [];
$conditions_data = [];

for ($i = 0; $i < count($condition_fields); $i++) {
    if (!empty(trim($condition_fields[$i]))) { // Sadece alan adı doluysa koşulu dikkate al
        $conditions_data[] = [
            'field' => trim($condition_fields[$i]),
            'operator' => trim($condition_operators[$i] ?? '='),
            'value' => trim($condition_values[$i] ?? '')
        ];
    }
}
$condition_json = json_encode($conditions_data);

$template_body = trim($_POST['message'] ?? '');
$is_active = isset($_POST['is_active']) ? 1 : 0;

// Temel doğrulamalar
if (empty($template_title)) {
    $_SESSION['error_message'] = "Şablon başlığı boş bırakılamaz.";
    header("Location: edit_template.php?id=" . $template_id);
    exit;
}
if (empty($template_body)) {
    $_SESSION['error_message'] = "Mesaj şablonu boş bırakılamaz.";
    header("Location: edit_template.php?id=" . $template_id);
    exit;
}


// SQL UPDATE sorgusu (yeni alanlar eklendi)
$sql = "UPDATE wa_templates
        SET template_title = :template_title,
            event = :event,
            recipient_type = :recipient_type,
            trigger_fields = :trigger_fields,
            condition_json = :condition_json,
            template_body = :template_body,
            is_active = :is_active,
            trigger_on_new = :trigger_on_new,
            trigger_on_null_to_value = :trigger_on_null_to_value,
            is_reminder_template = :is_reminder_template,              -- YENİ
            reminder_lead_time_minutes = :reminder_lead_time_minutes   -- YENİ
        WHERE id = :id";

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
    $stmt->bindParam(':id', $template_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Şablon başarıyla güncellendi!";
    } else {
        $_SESSION['error_message'] = "Şablon güncellenemedi. Veritabanı hatası: " . implode(" ", $stmt->errorInfo());
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Veritabanı bağlantı hatası: " . $e->getMessage();
    // error_log("Update template error: " . $e->getMessage());
}

header("Location: template.php"); // Başarı veya hata durumunda yönlendir
exit;