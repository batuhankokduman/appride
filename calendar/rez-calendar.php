<?php

// SİSTEMİNİZE GÖRE BU DOSYA YOLLARINI AYARLAYIN
require_once '../functions/db.php';     // Veritabanı bağlantınız
require_once '../functions/trigger_engine.php'; // Mevcutsa
require_once '../includes/auth.php';    // Oturum yönetimi, yetkilendirme vb. (Mevcutsa)
require_once '../includes/header.php';  // HTML <head>, ana CSS linkleri, üst navigasyon vb.
require_once '../includes/menu.php';    // Sol menünüz

// 1. TÜM EKSTRA SERVİSLERİ ÇEK VE BİR EŞLEME DİZİSİ OLUŞTUR
$extras_map = [];
// extras tablonuzdaki 'extra_service_id' sütununun, reservations tablosundaki JSON içindeki
// 'extra_service_id' ile eşleştiğini varsayıyoruz.
try {
    $stmtAllExtras = $pdo->query("SELECT extra_service_id, service_name FROM extras");
    if ($stmtAllExtras) {
        while ($extra_row = $stmtAllExtras->fetch(PDO::FETCH_ASSOC)) {
            $extras_map[$extra_row['extra_service_id']] = $extra_row['service_name'];
        }
    }
} catch (PDOException $e) {
    // Hata durumunda loglama veya kullanıcıya bilgi verme (opsiyonel)
    // error_log("Ekstra servisler çekilirken hata: " . $e->getMessage());
    // Şimdilik $extras_map boş kalacak, bu durumda ID'ler gösterilmeye devam edebilir.
}


// Rezervasyonları çekmek için SQL sorgusu (Gerekli tüm alanları ekleyin)
$stmt = $pdo->query("
    SELECT
        id,
        reservation_id,
        reservation_created_at,
        reservation_status,
        customer_first_name,
        customer_last_name,
        customer_email,
        customer_phone,
        schedule_selected_date,
        schedule_selected_time,
        schedule_pickup_date,
        schedule_pickup_time,
        pickup_address,
        pickup_geofence_name,
        dropoff_address,
        dropoff_geofence_name,
        passengers_adults,
        passengers_children,
        currency,
        gross_price,
        paid_amount,
        remaining_amount,
        extras, /* Bu JSON string olarak gelecek */
        stopovers,
        flight_info,
        metadata_comment,
        rule_name,
        access_token, /* Bilet linki için eklendi */
        maps_url /* Harita linki için (opsiyonel) */
    FROM
        reservations
    ORDER BY
        schedule_selected_date, schedule_selected_time
");
$reservations_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$calendar_events = [];
foreach ($reservations_data as $row) {
    $customer_full_name = trim(($row['customer_first_name'] ?? '') . ' ' . ($row['customer_last_name'] ?? ''));
    if (empty($customer_full_name) && empty($row['rule_name'])) {
        $title = 'Rezervasyon';
    } elseif (empty($customer_full_name)) {
        $title = $row['rule_name'] ?? 'İsimsiz Servis';
    } elseif (empty($row['rule_name'])) {
        $title = $customer_full_name;
    } else {
        $title = $customer_full_name . ' / ' . ($row['rule_name'] ?? 'Belirtilmemiş Servis');
    }

    // EKSTRA SERVİSLERİ İŞLE VE İSİMLERİNİ EKLE
    $processed_extras = [];
    if (!empty($row['extras'])) {
        $extras_json_decoded = json_decode($row['extras'], true);
        if (is_array($extras_json_decoded)) {
            foreach ($extras_json_decoded as $extra_item) {
                $service_id_from_json = $extra_item['extra_service_id'] ?? null;
                // $extras_map dizisinden ismi bul. Bulamazsa ID'yi veya varsayılan bir metni kullan.
                $service_name = $extras_map[$service_id_from_json] ?? 'Servis ID: ' . $service_id_from_json;
                
                $processed_extras[] = [
                    'name' => $service_name, // Eşlenmiş isim
                    'id_from_json' => $service_id_from_json, // Orijinal ID'yi de saklayabiliriz (opsiyonel)
                    'quantity' => $extra_item['extra_service_quantity'] ?? 0,
                    'note' => $extra_item['extra_service_note'] ?? ''
                    // 'total' => $extra_item['extra_service_total'] ?? 0 // Eğer gerekiyorsa bu da eklenebilir
                ];
            }
        }
    }

    $calendar_events[] = [
        'id' => $row['id'], // Veritabanı primary key (düzenleme vb. için)
        'reservation_id_text' => $row['reservation_id'], // Görüntülenen/kullanılan Rez. ID
        'access_token' => $row['access_token'], // Bilet linki için
        'maps_url' => $row['maps_url'], // Harita linki (opsiyonel)

        'date' => $row['schedule_selected_date'], // Takvim için ana tarih
        'time' => substr($row['schedule_selected_time'] ?? '', 0, 5), // Takvim için ana saat

        'title' => $title, // Takvimde görünecek başlık
        'rule_name' => $row['rule_name'],

        // Müşteri Bilgileri
        'customer_name' => $customer_full_name,
        'customer_phone' => $row['customer_phone'],
        'customer_email' => $row['customer_email'],
        'status' => $row['reservation_status'],
        'reservation_created_at' => $row['reservation_created_at'],

        // Seyahat Bilgileri
        'schedule_selected_date_raw' => $row['schedule_selected_date'],
        'schedule_selected_time_raw' => $row['schedule_selected_time'],
        'schedule_pickup_date_raw' => $row['schedule_pickup_date'],
        'schedule_pickup_time_raw' => $row['schedule_pickup_time'],
        'pickup_address' => $row['pickup_address'] ?: $row['pickup_geofence_name'] ?: 'Belirtilmemiş',
        'dropoff_address' => $row['dropoff_address'] ?: $row['dropoff_geofence_name'] ?: 'Belirtilmemiş',
        'stopovers_json' => $row['stopovers'] ?: '[]', // JSON string olarak
        'passengers_adults' => $row['passengers_adults'],
        'passengers_children' => $row['passengers_children'],

        // Ödeme Bilgileri
        'currency' => $row['currency'],
        'gross_price' => $row['gross_price'],
        'paid_amount' => $row['paid_amount'],
        'remaining_amount' => $row['remaining_amount'],

        // Ekstra ve Uçuş Bilgileri
        // 'extras_json' => $row['extras'] ?: '[]', // Bunun yerine işlenmiş olanı kullanacağız
        'extras_processed' => $processed_extras, // İsimleri içeren işlenmiş dizi

        'flight_info_json' => $row['flight_info'] ?: '[]', // JSON string olarak
        
        'description' => $row['metadata_comment'] ?: '', // Notlar/Açıklama
    ];
}
?>

<script>
    var initialCalendarEvents = <?php echo json_encode($calendar_events, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    // Dil ayarını da JavaScript'e aktarabiliriz (opsiyonel, eğer modal içinde çok fazla metin JS ile üretilecekse)
    // var currentLang = '<?php echo $lang ?? 'tr'; ?>'; // $lang değişkeniniz varsa
</script>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>📅 Rezervasyon Takvimi</h2>
    </div>

    <div class="calendar-container">
        <header class="calendar-header">
            <div class="calendar-nav">
                <button id="prevMonth" aria-label="Önceki"><i class="fas fa-chevron-left"></i></button>
                <h2 id="currentMonthYear">Yükleniyor...</h2>
                <button id="nextMonth" aria-label="Sonraki"><i class="fas fa-chevron-right"></i></button>
                <button id="todayButton">Bugün</button>
            </div>
            <div class="calendar-search">
                <input type="text" id="searchInput" placeholder="Müşteri, Kural Adı, Rez. ID...">
                <button id="searchButton"><i class="fas fa-search"></i></button>
            </div>
            <div class="calendar-view-toggle">
                <button id="monthViewButton" class="active">Bu Ay</button>
                <button id="weekViewButton">Bu Hafta</button>
                <button id="dayViewButton">Gün</button>
            </div>
        </header>

        <div class="calendar-grid-container">
            <div class="calendar-weekdays">
                <div>Pzt</div><div>Sal</div><div>Çar</div><div>Per</div><div>Cum</div><div>Cmt</div><div>Paz</div>
            </div>
            <div class="calendar-days" id="calendarDays"></div>
        </div>
        <div class="calendar-list-view" id="calendarListView" style="display: none;"></div>
    </div>

    <div id="eventModal" class="modal">
        <div class="modal-content reservation-card">
            <span class="close-button">&times;</span>
            <div class="card-header">
                <h3 id="modalCardTitle">Rezervasyon Detayı</h3>
                <span id="modalCardReservationId" class="reservation-id-badge"></span>
            </div>

            <div class="card-body">
                <div class="card-section">
                    <h4><i class="fas fa-user-circle"></i> Müşteri Bilgileri</h4>
                    <p><strong>Ad Soyad:</strong> <span id="modalCardCustomerName"></span></p>
                    <p><strong>Telefon:</strong> <span id="modalCardCustomerPhone"></span></p>
                    <p><strong>Email:</strong> <span id="modalCardCustomerEmail"></span></p>
                    <p><strong>Durum:</strong> <span id="modalCardStatus"></span></p>
                    <p><strong>Rez. Oluşturma:</strong> <span id="modalCardCreatedAt"></span></p>
                </div>

                <div class="card-section">
                    <h4><i class="fas fa-route"></i> Seyahat Bilgileri</h4>
                    <p><strong>Ana Zamanlama:</strong> <span id="modalCardSelectedDateTime"></span></p>
                    <div class="pickup-datetime-section">
                        <p style="margin-bottom: 5px;"><strong>Alınış Zamanı:</strong> <span id="modalPickupDateTimeDisplay"></span></p>
                        <button id="btnTogglePickupForm" class="btn btn-sm btn-action">Alınış Zamanını Değiştir</button>
                        <div id="pickupFormContainer" style="display: none;">
                            <label for="modalPickupDateInput">Yeni Alınış Tarihi:</label>
                            <input type="date" id="modalPickupDateInput">
                            <label for="modalPickupTimeInput">Yeni Alınış Saati:</label>
                            <input type="time" id="modalPickupTimeInput">
                            <button id="btnSavePickupDateTime" class="btn btn-sm btn-success">Kaydet</button>
                            <button type="button" id="btnCancelPickupEdit" class="btn btn-sm btn-light">İptal</button>
                        </div>
                    </div>
                    <p><strong>Alış Adresi:</strong> <span id="modalCardPickupAddress"></span></p>
                    <p><strong>Bırakış Adresi:</strong> <span id="modalCardDropoffAddress"></span></p>
                    <p><strong>Yolcu Sayısı:</strong> <span id="modalCardPassengers"></span></p>
                    <div id="modalCardStopoversContainer" style="display:none;">
                        <p><strong>Duraklar:</strong></p>
                        <ul id="modalCardStopoversList"></ul>
                    </div>
                </div>

                <div class="card-section" id="modalCardFlightInfoContainer" style="display:none;">
                    <h4><i class="fas fa-plane"></i> Uçuş Bilgileri</h4>
                    <div id="modalCardFlightInfoList"></div>
                </div>
                
                <div class="card-section" id="modalCardExtrasContainer" style="display:none;">
                    <h4><i class="fas fa-plus-circle"></i> Ekstra Hizmetler</h4>
                    <ul id="modalCardExtrasList"></ul> </div>

                <div class="card-section">
                    <h4><i class="fas fa-credit-card"></i> Ödeme Bilgileri</h4>
                    <p><strong>Toplam Tutar:</strong> <span id="modalCardGrossPrice"></span></p>
                    <p><strong>Ödenen Tutar:</strong> <span id="modalCardPaidAmount"></span></p>
                    <p><strong>Kalan Tutar:</strong> <span id="modalCardRemainingAmount"></span></p>
                </div>

                <div class="card-section" id="modalCardCommentContainer" style="display:none;">
                    <h4><i class="fas fa-comment-dots"></i> Notlar</h4>
                    <p id="modalCardComment" style="white-space: pre-wrap;"></p>
                </div>
            </div>

            <div class="card-footer">
                <a href="#" id="modalViewFullTicketLink" class="btn btn-sm btn-info" target="_blank" style="display:none;">Tam Bileti Görüntüle</a>
                <a href="#" id="modalEditLink" class="btn btn-sm btn-primary" style="display:none;">Rezervasyonu Düzenle (Admin)</a>
            </div>
        </div>
    </div>

</div> <?php require_once '../includes/footer.php'; ?>