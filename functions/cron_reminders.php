<?php
// cron_reminders.php
$startTime = microtime(true);

// 1. Beklenen Güvenlik Token'ını Tanımlayın
define('EXPECTED_CRON_TOKEN', 'Hjdgsıuwue45a4sodvVayxz'); // Sizin belirttiğiniz token

// 2. Loglama Fonksiyonunu En Başa Alın (Token hatasını da loglayabilmek için)
// Zaman dilimini de loglamadan önce ayarlamak iyi olur.
date_default_timezone_set('Europe/Istanbul'); // Uygulamanızın zaman dilimi

function log_cron_message(string $message, string $level = "INFO"): void {
    $timestamp = date('Y-m-d H:i:s');
    // Log dosyasının yolu (LÜTFEN KONTROL EDİN VE GEREKİRSE DÜZENLEYİN)
    // Bu yol, cron_reminders.php dosyasının bulunduğu dizinin bir üstündeki 'logs' klasörünü işaret eder.
    $logFile = __DIR__ . '/../logs/cron_reminders.log';
    
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        // @mkdir_recursive($logDir, 0755); // Eğer alt klasörler de yoksa
        @mkdir($logDir, 0755, true); // PHP 5+ için recursive true yeterli
    }
    $formattedMessage = "[$timestamp] [$level] $message\n";
    
    // Komut satırından çalışıyorsa konsola da bas
  
        echo $formattedMessage;
    
    
    @file_put_contents($logFile, $formattedMessage, FILE_APPEND);
}

// 3. Gelen Token'ı Kontrol Edin
$provided_token = null;
$is_cli = (php_sapi_name() === 'cli' || php_sapi_name() === 'cgi-fcgi');

if ($is_cli) {
    // Komut satırından çalıştırılıyorsa: php cron_reminders.php SIZIN_TOKENINIZ
    // $argv[0] script adıdır, $argv[1] ilk parametredir.
    $provided_token = $argv[1] ?? null;
} else {
    // Web üzerinden (GET isteği) çalıştırılıyorsa: cron_reminders.php?token=SIZIN_TOKENINIZ
    $provided_token = $_GET['token'] ?? null;
}

if ($provided_token !== EXPECTED_CRON_TOKEN) {
    $sapi_type = php_sapi_name();
    log_cron_message("KRİTİK HATA: Geçersiz veya eksik cron token ile erişim denemesi. SAPI: {$sapi_type}. Sağlanan Token: '" . print_r($provided_token, true) . "'", "ERROR");
    
    if (!$is_cli) {
        header("HTTP/1.1 403 Forbidden");
        echo "Erişim engellendi: Geçersiz token.\n"; // Tarayıcı için de mesaj
    } else {
        echo "Erişim engellendi: Geçersiz token.\n"; // CLI için mesaj
    }
    exit; // Script'i sonlandır
}

// Token doğrulandı, script'in geri kalanı çalışabilir.
log_cron_message("Cron token doğrulandı. Cron job başlatılıyor: Hatırlatma mesajları (çoklu şablon sistemi) kontrol ediliyor.");


// ----- BURADAN SONRASI MEVCUT CRON SCRİPTİNİZİN DEVAMI -----

// Proje kök dizinini ve yolları doğru ayarladığınızdan emin olun
// Örnek: define('PROJECT_ROOT_CRON', dirname(__DIR__)); // Eğer bu dosya /cron/ içindeyse
// require_once PROJECT_ROOT_CRON . '/functions/db.php';
// require_once PROJECT_ROOT_CRON . '/engine/trigger_engine.php';

// DİKKAT: Aşağıdaki require_once yolları, cron_reminders.php dosyanızın bulunduğu dizine göre ayarlanmalıdır.
// Eğer cron_reminders.php, db.php ve trigger_engine.php ile aynı dizindeyse __DIR__ . '/dosyaadi.php' doğrudur.
// Eğer db.php ve trigger_engine.php bir üst dizindeki 'functions' ve 'engine' klasörlerindeyse, önceki gibi __DIR__ . '/../functions/db.php' olmalıdır.
// Sizin verdiğiniz son kodda __DIR__ . '/db.php' ve __DIR__ . '/trigger_engine.php' idi, bunu koruyorum.
// Eğer bu yollar doğru değilse, lütfen projenizin dosya yapısına göre güncelleyin.
require_once __DIR__ . '/db.php';             // Veritabanı bağlantısı (YOLU KONTROL EDİN)
require_once __DIR__ . '/trigger_engine.php';  // TriggerEngine sınıfı (YOLU KONTROL EDİN)


global $pdo;

if (!$pdo) {
    log_cron_message("KRİTİK HATA: Veritabanı bağlantısı kurulamadı (Token kontrolünden sonra).", "ERROR");
    exit;
}

try {
    // ... (cron script'inizin geri kalan kısmı buraya gelecek) ...
    // 1. Aktif, hatırlatma türünde ve geçerli hatırlatma süresi olan şablonları al
    $stmtTemplates = $pdo->prepare(
        "SELECT * FROM wa_templates 
         WHERE is_active = 1 
           AND is_reminder_template = 1 
           AND reminder_lead_time_minutes IS NOT NULL 
           AND reminder_lead_time_minutes > 0"
    );
    $stmtTemplates->execute();
    $reminderTemplates = $stmtTemplates->fetchAll(PDO::FETCH_ASSOC);

    if (empty($reminderTemplates)) {
        log_cron_message("Aktif hatırlatma şablonu bulunamadı. Cron sonlandırılıyor.");
        // exit; // Token doğrulandıktan sonra ana işlevi yapmadan çıkmak yerine, finally bloğunun çalışması için bu exit'i kaldırabiliriz.
    } else { // Sadece şablon varsa devam et
        log_cron_message(count($reminderTemplates) . " adet aktif hatırlatma şablonu bulundu.");
        $totalMessagesSentThisRun = 0;
        $currentTimePHP = date('Y-m-d H:i:s'); // PHP'nin zaman dilimine göre şimdiki zaman

        foreach ($reminderTemplates as $template) {
            $templateId = (int)$template['id'];
            $templateTitle = $template['template_title'];
            $leadTimeMinutes = (int)$template['reminder_lead_time_minutes'];

            log_cron_message("Şablon işleniyor: ID {$templateId} - Başlık: '{$templateTitle}', Süre: {$leadTimeMinutes} dk.");

            $sqlReservations = "
                SELECT res.* FROM reservations res
                WHERE res.schedule_pickup_date IS NOT NULL
                  AND res.schedule_pickup_time IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1
                      FROM reservation_reminders_sent rrs
                      WHERE rrs.reservation_id = res.id
                        AND rrs.template_id = :current_template_id
                  )
                  AND STR_TO_DATE(CONCAT(res.schedule_pickup_date, ' ', IF(TIME_FORMAT(res.schedule_pickup_time, '%H:%i:%s') IS NULL, CONCAT(res.schedule_pickup_time, ':00'), res.schedule_pickup_time)), '%Y-%m-%d %H:%i:%s') >= :currentTimeForFutureCheck
                  AND TIMESTAMPADD(
                        MINUTE, 
                        -{$leadTimeMinutes}, 
                        STR_TO_DATE(CONCAT(res.schedule_pickup_date, ' ', IF(TIME_FORMAT(res.schedule_pickup_time, '%H:%i:%s') IS NULL, CONCAT(res.schedule_pickup_time, ':00'), res.schedule_pickup_time)), '%Y-%m-%d %H:%i:%s')
                      ) <= :currentTimeForPastCheck
                ORDER BY res.id ASC 
                LIMIT 20; 
            ";

            $stmtReservations = $pdo->prepare($sqlReservations);
            $stmtReservations->bindParam(':current_template_id', $templateId, PDO::PARAM_INT);
            $stmtReservations->bindParam(':currentTimeForFutureCheck', $currentTimePHP);
            $stmtReservations->bindParam(':currentTimeForPastCheck', $currentTimePHP);
            $stmtReservations->execute();
            $reservations = $stmtReservations->fetchAll(PDO::FETCH_ASSOC);

            if (empty($reservations)) {
                log_cron_message("Şablon ID {$templateId} için gönderilecek yeni uygun rezervasyon bulunamadı.");
                continue; 
            }

            log_cron_message(count($reservations) . " adet uygun rezervasyon bulundu (Şablon ID: {$templateId}).");

            foreach ($reservations as $reservation) {
                $reservationId = (int)$reservation['id'];
                log_cron_message("Rezervasyon ID {$reservationId} işleniyor (Şablon ID: {$templateId}).");

                $dataForTemplate = $reservation;
                if (!isset($dataForTemplate['reservation_id'])) { 
                    $dataForTemplate['reservation_id'] = $reservationId;
                }
                // if (empty($dataForTemplate['access_token'])) { $dataForTemplate['access_token'] = ...; }

                $conditions = !empty($template['condition_json']) ? json_decode($template['condition_json'], true) : [];
                if (!is_array($conditions)) $conditions = []; 

                if (TriggerEngine::conditionsMatch($conditions, $dataForTemplate)) {
                    log_cron_message("Koşullar eşleşti. Mesaj gönderiliyor (Rez. ID: {$reservationId}, Şablon ID: {$templateId}).");
                    
                    TriggerEngine::sendMessageWithTemplate($template, $dataForTemplate, $reservationId);
                    
                    $stmtInsertSent = $pdo->prepare(
                        "INSERT INTO reservation_reminders_sent (reservation_id, template_id, sent_at) 
                         VALUES (:reservation_id, :template_id, NOW())
                         ON DUPLICATE KEY UPDATE sent_at = VALUES(sent_at)"
                    );
                    $insertSuccess = $stmtInsertSent->execute([
                        ':reservation_id' => $reservationId,
                        ':template_id' => $templateId
                    ]);
                    
                    if ($insertSuccess) {
                        log_cron_message("Rezervasyon ID {$reservationId} için Şablon ID {$templateId} hatırlatması gönderildi olarak işaretlendi.");
                        $totalMessagesSentThisRun++;
                    } else {
                        $errorInfoInsert = $stmtInsertSent->errorInfo();
                        log_cron_message("HATA: Rezervasyon ID {$reservationId}, Şablon ID {$templateId} için gönderildi bilgisi kaydedilemedi! Error: " . ($errorInfoInsert[2] ?? 'Bilinmeyen DB hatası'), "ERROR");
                    }
                } else {
                    log_cron_message("Koşullar eşleşmedi (Rez. ID: {$reservationId}, Şablon ID: {$templateId}).");
                }
            } // foreach $reservations
        } // foreach $reminderTemplates
    } // else (şablon varsa)

} catch (PDOException $e) {
    log_cron_message("VERİTABANI HATASI: " . $e->getMessage() . " (Dosya: " . $e->getFile() . ", Satır: " . $e->getLine() . ")", "ERROR");
} catch (Throwable $e) { // PHP 7+ için tüm hataları yakalar
    log_cron_message("BEKLENMEYEN HATA: " . $e->getMessage() . " (Dosya: " . $e->getFile() . ", Satır: " . $e->getLine() . ")", "ERROR");
} finally {
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    log_cron_message("Cron job tamamlandı. Bu çalıştırmada toplam " . ($totalMessagesSentThisRun ?? 0) . " mesaj gönderildi/denendi. Süre: {$executionTime} saniye.");
}
?>