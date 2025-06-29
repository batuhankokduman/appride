<?php

require_once __DIR__ . '/../functions/db.php';
require_once __DIR__ . '/../classes/FieldChangeDetector.php'; // Bu dosyanın var olduğundan ve doğru çalıştığından emin olun
require_once __DIR__ . '/send_message.php'; // Bu dosyanın var olduğundan ve doğru çalıştığından emin olun

class TriggerEngine
{
    public static function checkAndSend(int $reservation_id, array $oldData, array $newData, bool $isNew = false): void
    {
        global $pdo;

        // Aktif şablonları al (henüz hatırlatma olanları filtrelemiyoruz, döngü içinde yapacağız)
        $stmt = $pdo->prepare("SELECT * FROM wa_templates WHERE is_active = 1");
        $stmt->execute();
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Alan değişimlerini tespit et
        $changedFields = [];
        if (!$isNew) { // Sadece güncelleme durumunda değişiklikleri hesapla
            $changedFields = FieldChangeDetector::getChangedFields($oldData, $newData);
        }


        foreach ($templates as $template) {
            // YENİ EKLENEN KONTROL:
            // Eğer şablon bir hatırlatma şablonu ise, bu motor (checkAndSend) tarafından işlenmemeli.
            if (!empty($template['is_reminder_template']) && $template['is_reminder_template'] == 1) {
                continue; // Bu şablonu atla, bir sonraki şablona geç.
            }

            $triggerFields = array_map('trim', explode(',', $template['trigger_fields'] ?? ''));
            $conditions = json_decode($template['condition_json'] ?? '[]', true);
            if (!is_array($conditions)) $conditions = [];

            $isNewTrigger = !empty($template['trigger_on_new']);
            $isNullToValueTrigger = !empty($template['trigger_on_null_to_value']);

            // 🟢 Yeni kayıt durumu
            if ($isNew && $isNewTrigger) {
                if (!self::conditionsMatch($conditions, $newData)) {
                    continue;
                }
                self::sendMessageWithTemplate($template, $newData, $reservation_id);
                continue;
            }

            // 🟡 Güncelleme durumu
            if (!$isNew) {
                $triggeredByFieldChange = false;
                if (!empty($triggerFields[0])) {
                    foreach ($triggerFields as $field) {
                        if (in_array($field, $changedFields)) {
                            if ($isNullToValueTrigger) {
                                $wasNull = !isset($oldData[$field]) || $oldData[$field] === null || $oldData[$field] === '' || strtolower((string)$oldData[$field]) === 'null';
                                $isNowFilled = isset($newData[$field]) && $newData[$field] !== '' && strtolower((string)$newData[$field]) !== 'null';
                                if ($wasNull && $isNowFilled) {
                                    $triggeredByFieldChange = true;
                                    break;
                                }
                            } else {
                                if (isset($oldData[$field]) && isset($newData[$field]) && $oldData[$field] != $newData[$field]) {
                                    $triggeredByFieldChange = true;
                                    break;
                                }
                            }
                        }
                    }
                }

                if ($triggeredByFieldChange) {
                    if (!self::conditionsMatch($conditions, $newData)) {
                        continue;
                    }
                    self::sendMessageWithTemplate($template, $newData, $reservation_id);
                }
            }
        }
    }

    public static function conditionsMatch(array $conditions, array $data): bool
    {
        if (empty($conditions)) {
            return true;
        }
        foreach ($conditions as $cond) {
            $field = $cond['field'] ?? null;
            $operator = $cond['operator'] ?? '=';
            $value = $cond['value'] ?? null;

            if ($field === null) continue;

            $actualValueExists = isset($data[$field]);
            $actual = $actualValueExists ? (string) $data[$field] : null;
            $expected = (string) $value;

            switch ($operator) {
                case '=':
                    if ($actual !== $expected) return false;
                    break;
                case '!=':
                    if ($actual === $expected) return false;
                    break;
                case '>':
                    if (!$actualValueExists || !is_numeric($actual) || !is_numeric($expected) || !($actual > $expected)) return false;
                    break;
                case '<':
                    if (!$actualValueExists || !is_numeric($actual) || !is_numeric($expected) || !($actual < $expected)) return false;
                    break;
                default:
                    return false;
            }
        }
        return true;
    }

    public static function sendMessageWithTemplate(array $template, array $data, ?int $reservation_id = null): void
    {
        global $pdo;

        if (!isset($data['reservation_id']) && $reservation_id !== null) {
            $data['reservation_id'] = $reservation_id;
        }

     // Araç tipi bilgisini ekle
        if (!isset($data['vehicle_type']) && isset($data['vehicle_id'])) {
            try {
                $stmtVeh = $pdo->prepare("SELECT vehicle_type FROM vehicles WHERE vehicle_id = ?");
                $stmtVeh->execute([$data['vehicle_id']]);
                $vehRow = $stmtVeh->fetch(PDO::FETCH_ASSOC);
                if ($vehRow && isset($vehRow['vehicle_type'])) {
                    $data['vehicle_type'] = $vehRow['vehicle_type'];
                }
            } catch (PDOException $e) {
                error_log("TriggerEngine: vehicle_type fetch error for reservation " . ($data['reservation_id'] ?? 'N/A') . ': ' . $e->getMessage());
            }
        }


        $message = self::renderTemplate($template['template_body'], $data);
        $recipientType = $template['recipient_type'] ?? 'customer';
        $phone = null;

        if ($recipientType === 'customer') {
            $phone = $data['customer_phone'] ?? null;
        } elseif ($recipientType === 'supplier') {
            $supplierId = $data['supplier_id'] ?? null;
            if ($supplierId) {
                $stmtSup = $pdo->prepare("SELECT phone_number FROM suppliers WHERE id = ?");
                $stmtSup->execute([$supplierId]);
                $supplier = $stmtSup->fetch(PDO::FETCH_ASSOC);
                if ($supplier && !empty($supplier['phone_number'])) {
                    $phone = $supplier['phone_number'];
                }
            }
        } elseif ($recipientType === 'live') { // <--- YENİ EKLENEN BLOK BAŞLANGICI
            try {
                // site_settings tablosundan 'live' key'ine sahip telefon numarasını al
                $stmtSettings = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'live'");
                $stmtSettings->execute();
                $setting = $stmtSettings->fetch(PDO::FETCH_ASSOC);

                if ($setting && !empty($setting['setting_value'])) {
                    $phone = $setting['setting_value'];
                    error_log("TriggerEngine: 'live' alıcısı için numara bulundu: " . $phone);
                } else {
                    // Telefon numarası bulunamazsa veya boşsa log kaydı yap
                    error_log("TriggerEngine Uyarı: 'live' alıcı tipi için site_settings tablosunda 'live' anahtarıyla eşleşen telefon numarası bulunamadı veya değeri boş. Şablon ID: " . ($template['id'] ?? 'Bilinmiyor'));
                }
            } catch (PDOException $e) {
                error_log("TriggerEngine Veritabanı Hatası ('live' alıcı tipi için numara alınırken): " . $e->getMessage() . " Şablon ID: " . ($template['id'] ?? 'Bilinmiyor'));
            }
        } // <--- YENİ EKLENEN BLOK SONU

        if ($phone && !empty(trim($message)) && $message !== '[BOŞ]') {
            error_log("Mesaj gönderiliyor: Telefon: {$phone}, Şablon ID: {$template['id']}, Mesaj: {$message}");
            try {
                sendMessage($phone, $message); // Gerçek mesaj gönderme fonksiyonu
                error_log("Mesaj başarıyla gönderildi: {$phone}");
            } catch (Exception $e) {
                error_log("Mesaj gönderme hatası: Telefon: {$phone}, Şablon ID: {$template['id']}, Hata: " . $e->getMessage());
            }
        } else {
            if (empty(trim($message)) || $message === '[BOŞ]') {
                 error_log("Mesaj gönderilemedi: Mesaj içeriği boş/geçersiz. Şablon ID: {$template['id']}, Telefon: {$phone}");
            } elseif (!$phone) {
                 error_log("Mesaj gönderilemedi: Telefon numarası alınamadı/boş. Alıcı Tipi: {$recipientType}, Şablon ID: {$template['id']}");
            } else {
                 error_log("Mesaj gönderilemedi: Bilinmeyen neden. Telefon ({$phone}), Mesaj ({$message}). Şablon ID: {$template['id']}");
            }
        }
    }

    private static function renderTemplate(string $templateBody, array $data): string
    {
        global $pdo;

        if (empty(trim($templateBody))) {
            return '';
        }

        $renderedTemplate = preg_replace_callback('/{{(.*?)}}/', function ($matches) use ($data, $pdo) {
            $key = trim($matches[1]);

            if ($key === 'rez_detay_link') {
                if (isset($data['reservation_id']) && isset($data['access_token'])) {
                    return 'https://app.rideandgoo.com/rez.php?id=' . $data['reservation_id'] . '&t=' . $data['access_token'];
                } else {
                    error_log("renderTemplate: rez_detay_link için reservation_id veya access_token eksik. Data: " . json_encode($data));
                    return '[rez_detay_link_bilgi_eksik]';
                }
            }
               if ($key === 'ted_detay_link') {
                if (isset($data['reservation_id']) && isset($data['access_token'])) {
                    return 'https://app.rideandgoo.com/reztd.php?id=' . $data['reservation_id'] . '&t=' . $data['access_token'];
                } else {
                    error_log("renderTemplate: rez_detay_link için reservation_id veya access_token eksik. Data: " . json_encode($data));
                    return '[rez_detay_link_bilgi_eksik]';
                }
            }

            if ($key === 'stopovers') {
                $raw = $data['stopovers'] ?? '';
                $stopovers = json_decode($raw, true);
                if (!is_array($stopovers) || empty($stopovers)) return '';
                $lang = ($data['currency'] ?? '') === 'TRY' ? 'tr' : 'en';
                $labelDurak = $lang === 'tr' ? 'Durak' : 'Stop';
                $labelBekleme = $lang === 'tr' ? 'Bekleme' : 'Wait';
                $lines = [];
                foreach ($stopovers as $i => $stop) {
                    $num = $i + 1;
                    $address = $stop['address'] ?? '';
                    $duration = isset($stop['duration']) && (int)$stop['duration'] > 0 ? " ({$labelBekleme}: " . (int)$stop['duration'] . " dk)" : '';
                    if (!empty(trim($address))) {
                        $lines[] = "{$labelDurak} {$num}: {$address}{$duration}";
                    }
                }
                return implode("\n", $lines);
            }
            if ($key === 'flight_info') {
    $raw = $data['flight_info'] ?? '';
    $info = json_decode($raw, true);
    if (!is_array($info) || empty($info)) return '';

    $output = [];
    foreach ($info as $item) {
        $label = $item['label'] ?? '';
        $value = $item['value'] ?? '';
        if ($label && $value) {
            $output[] = "{$label}: {$value}";
        }
    }
    return implode("\n", $output);
}


            if ($key === 'extras') {
                $extrasRaw = $data['extras'] ?? '[]';
                $extras = json_decode($extrasRaw, true);
                if (!is_array($extras) || empty($extras)) return '';
                $ids = array_filter(array_map(function ($e) {
                    return isset($e['extra_service_id']) ? (int)$e['extra_service_id'] : null;
                }, $extras));
                if (empty($ids)) return '';
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                try {
                    $stmtExtras = $pdo->prepare("SELECT extra_service_id, service_name FROM extras WHERE extra_service_id IN ($placeholders)");
                    $stmtExtras->execute($ids);
                    $rows = $stmtExtras->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    error_log("renderTemplate extras PDOException: " . $e->getMessage());
                    return '[HATA: Extra çekilemedi]';
                }
                $map = [];
                foreach ($rows as $row) {
                    $map[(int)$row['extra_service_id']] = $row['service_name'];
                }
                $output = '';
                foreach ($extras as $e) {
                    $id = (int)($e['extra_service_id'] ?? 0);
                    $qty = (int)($e['extra_service_quantity'] ?? 1);
                    if ($id > 0 && $qty > 0) {
                        $name = $map[$id] ?? 'Ekstra Servis';
                        $output .= "{$name} × {$qty}\n";
                    }
                }
                return trim($output);
            }

            if (isset($data[$key])) {
                return $data[$key] !== null ? (string)$data[$key] : '';
            }
            return '[BOŞ]';
        }, $templateBody);

        $renderedTemplate = html_entity_decode($renderedTemplate, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $renderedTemplate;
    }
}