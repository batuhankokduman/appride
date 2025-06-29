<!DOCTYPE html>


<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?= htmlspecialchars($og_title) ?></title>
    <meta property="og:title" content="<?= htmlspecialchars($og_title) ?>" />
    <meta property="og:description" content="<?= htmlspecialchars($og_description) ?>" />
    <meta property="og:url" content="<?= htmlspecialchars($og_url) ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="RideAndGoo" />
    <meta property="og:image" content="<?= htmlspecialchars($og_image_landscape) ?>" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image" content="<?= htmlspecialchars($og_image_square) ?>" />
    <meta property="og:image:width" content="1080" />
    <meta property="og:image:height" content="1080" />
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($og_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($og_description) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($og_image_landscape) ?>">
    <script src="https://js.stripe.com/v3/"></script>
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0], j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-5GHWW72D');</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBAGxmniufZ1vcWlzUtT9Op_Wk3u7j9DcM&libraries=places&callback=initMap" async defer></script>
    
<style>
    :root {
        --primary-color: #fe783d; --secondary-color: #333; --text-color: #555;
        --border-color: #e5e7eb; --background-color: #f9fafb; --card-background: #ffffff;
        --font-family: 'Roboto', sans-serif;
        /* Durum Renkleri */
        --status-red-bg: #fee2e2; --status-red-text: #b91c1c; --status-red-border: #fca5a5;
        --status-amber-bg: #fffbeb; --status-amber-text: #b45309; --status-amber-border: #fcd34d;
        --status-green-bg: #dcfce7; --status-green-text: #166534; --status-green-border: #86efac;
    }
    body { font-family: var(--font-family); background-color: var(--background-color); margin: 0; padding: 20px 10px; color: var(--text-color); }
    .ticket-container { max-width: 800px; margin: auto; background-color: var(--card-background); border-radius: 16px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); overflow: hidden; }

    /* ======================================== */
    /* BAŞLIK STİLLERİ */
    /* ======================================== */
    
    .ticket-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 25px 30px;
        background: #ededed;
        border-bottom: 4px solid var(--primary-color);
        color: var(--secondary-color);
        text-align: left;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }

    .ticket-header img {
        max-height: 45px;
        margin-bottom: 0;
    }

    .ticket-header h1 {
        margin: 0 0 4px 0;
        font-size: 24px;
        font-weight: 700;
        color: var(--secondary-color);
    }

    .header-right {
        text-align: right;
    }

    .tagline {
        font-size: 14px;
        font-weight: 400;
        color: var(--text-color);
        margin: 0 0 10px 0;
        opacity: 0.9;
    }

    .reservation-meta {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 15px;
    }

    .rez-no {
        font-size: 15px;
        font-weight: 500;
        color: var(--text-color);
    }

    .ticket-body { padding: 30px; display: grid; grid-template-columns: 1fr; gap: 25px; }
    .card { background-color: var(--card-background); border: 1px solid var(--border-color); border-radius: 12px; padding: 25px; }
    .card-header { display: flex; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); }
    .card-header .icon { width: 24px; height: 24px; margin-right: 12px; color: var(--primary-color); }
    .card-header h3 { margin: 0; font-size: 20px; color: var(--secondary-color); font-weight: 700; }
    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
    .info-item { display: flex; flex-direction: column; }
    .info-item .label { font-size: 14px; color: #6b7280; margin-bottom: 5px; font-weight: 500; }
    .info-item .value { font-size: 16px; color: var(--secondary-color); font-weight: 400; word-break: break-word; }
    .info-item .highlight { font-weight: 700; color: var(--primary-color); }
    .not-confirmed { color: #ef4444; font-style: italic; }
    .vehicle-card-content { display: grid; grid-template-columns: 150px 1fr; gap: 25px; align-items: center; }
    .vehicle-photo { width: 150px; height: 100px; object-fit: cover; border-radius: 8px; background-color: #f3f4f6; border: 1px solid var(--border-color); }
    .payment-card { border-radius: 12px; padding: 25px; }
    .payment-card.status-red { background-color: var(--status-red-bg); border-left: 5px solid var(--status-red-text); }
    .payment-card.status-amber { background-color: var(--status-amber-bg); border-left: 5px solid var(--status-amber-text); }
    .payment-card.status-green { background-color: var(--status-green-bg); border-left: 5px solid var(--status-green-text); }
    .payment-info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
    .payment-info-grid .info-item { background: rgba(255,255,255,0.5); padding: 15px; border-radius: 8px; text-align: center; }
    .payment-status-message { margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(0,0,0,0.1); font-size: 15px; text-align: center; }
    .status-red .payment-status-message { color: var(--status-red-text); }
    .status-amber .payment-status-message { color: var(--status-amber-text); }
    .status-green .payment-status-message { color: var(--status-green-text); }
    .features-list { display: flex; flex-wrap: wrap; gap: 8px; list-style: none; padding: 0; }
    .feature-tag { background-color: #f3f4f6; color: #4b5563; padding: 5px 10px; border-radius: 20px; font-size: 13px; }
    #map { width: 100%; height: 350px; border-radius: 12px; margin-top: 20px; }
    .ticket-footer { text-align: center; padding: 30px; background: #f3f4f6; border-top: 1px solid var(--border-color); }
    .footer-links { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; }
    .footer-links a { text-decoration: none; color: var(--text-color); font-weight: 500; display: flex; align-items: center; gap: 8px; transition: color 0.2s; }
    .footer-links a:hover { color: var(--primary-color); }
    .footer-links .icon { width: 20px; height: 20px; }

    .flight-details-container { display: flex; flex-direction: column; gap: 20px; padding-top: 15px; }
    .timing-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px 30px; }
    .timing-details .info-item { padding: 15px; background-color: var(--background-color); border-radius: 8px; border-left: 4px solid var(--primary-color); }
    .timing-details .value.large-text { font-size: 18px; font-weight: 700; margin-top: 5px; display: block; }
    .timing-details .not-confirmed.large-text { color: var(--status-amber-text); font-style: normal; }
    .details-divider { border: none; border-top: 1px solid var(--border-color); margin: 5px 0; }
    .additional-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }

    .route-addresses {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-bottom: 20px;
        border-left: 2px solid var(--border-color);
        padding-left: 25px;
        margin-left: 10px;
    }
    .route-point {
        display: flex;
        align-items: flex-start;
        position: relative;
    }
    .route-icon-container {
        position: absolute;
        left: -36px;
        top: 0;
        background-color: var(--card-background);
        padding: 2px 0;
    }
    .route-icon {
        width: 20px;
        height: 20px;
        color: var(--primary-color);
    }

    .status-badge {
        display: inline-block;
        font-size: 13px;
        font-weight: 500;
        padding: 4px 10px;
        border-radius: 999px;
        margin-top: 0;
        background-color: #eee;
        color: #555;
    }
    .status-amber.status-badge {
        background-color: var(--status-amber-bg);
        color: var(--status-amber-text);
    }
    .status-green.status-badge {
        background-color: var(--status-green-bg);
        color: var(--status-green-text);
    }
    .status-red.status-badge {
        background-color: var(--status-red-bg);
        color: var(--status-red-text);
    }
    
    /* =================================================================== */
    /* DÜZENLEME / KAYDETME BÖLÜMLERİ İÇİN GÜNCELLENMİŞ STİLLER */
    /* =================================================================== */
    .card-header-actions {
        margin-left: auto;
    }

    .btn {
        display: inline-block;
        padding: 8px 16px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        transition: background-color 0.2s, color 0.2s, box-shadow 0.2s;
        text-align: center;
    }
    .btn-primary {
        background-color: var(--primary-color);
        color: white;
    }
    .btn-primary:hover {
        background-color: #e56a35;
        box-shadow: 0 4px 10px rgba(254, 120, 61, 0.3);
    }
    .btn-secondary {
        background-color: #f3f4f6;
        color: var(--secondary-color);
        border: 1px solid var(--border-color);
    }
    .btn-secondary:hover {
        background-color: #e5e7eb;
    }
    .btn-sm {
        padding: 5px 10px;
        font-size: 13px;
    }

    .edit-mode {
        display: none; /* JavaScript ile aktif edilecek */
    }

    .view-mode .info-grid, .view-mode .passenger-list {
        padding-top: 15px;
    }

    .view-mode .passenger-list .passenger-item {
        padding: 8px;
        background-color: var(--background-color);
        border-radius: 6px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
    }
    .view-mode .passenger-list .passenger-item .p-index {
        margin-right: 10px;
        font-weight: 500;
        color: var(--primary-color);
    }

    /* Form Elemanları için Düzeltilmiş ve Geliştirilmiş Stiller */
    .form-item {
        display: flex;
        flex-direction: column;
        gap: 8px;
        width: 100%;
    }
    .styled-input, .styled-select {
        width: 100%;
        padding: 10px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 15px;
        box-sizing: border-box; /* Önemli: padding'in genişliği etkilememesi için */
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .styled-input:focus, .styled-select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(254, 120, 61, 0.2);
    }
    .passenger-form-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    .passenger-form-row input[type="text"] {
        flex: 1;
    }

    /* Kaydet Butonları için İyileştirme */
    .form-item .btn {
        align-self: flex-start;
    }
    
    /* Uyarı Mesajları İçin Stiller */
    .passenger-warning, .field-warning {
        color: var(--status-red-text);
        background-color: var(--status-red-bg);
        border: 1px solid var(--status-red-border);
        padding: 10px;
        border-radius: 8px;
        font-size: 14px;
        margin-top: 15px;
        text-align: center;
    }

    /* ======================================== */
    /* RESPONSIVE (MOBİL) STİLLER */
    /* ======================================== */
    @media(max-width: 768px) {
        body { padding: 0; }
        .ticket-container { border-radius: 0; }
        
        .ticket-header { flex-direction: column; gap: 20px; padding: 20px; }
        .header-right { text-align: center; }
        .reservation-meta { justify-content: center; }

        .ticket-body { padding: 20px; }
        .card, .payment-card { padding: 20px; }
        .vehicle-card-content { grid-template-columns: 1fr; text-align: center; }
        .vehicle-photo { margin: 0 auto 20px auto; }
        .payment-info-grid { grid-template-columns: 1fr; }
        .timing-details { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5GHWW72D" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    
    <div class="ticket-container">
        
        <header class="ticket-header">
            <div class="header-left">
                <?php 
                    // Dil kontrolü ile doğru URL'yi belirle
                    $home_url = ($lang ?? 'en') === 'tr' 
                        ? 'https://tr.rideandgoo.com' 
                        : 'https://rideandgoo.com'; 
                ?>
                <a href="<?= htmlspecialchars($home_url) ?>" target="_blank" title="RideAndGoo Ana Sayfası">
                    <img src="https://rideandgoo.com/wp-content/uploads/2024/09/RideAndGoo-MainLogo.webp" alt="RideAndGoo Logo">
                </a>
            </div>
            
            <div class="header-right">
                <h1><?= htmlspecialchars($t['header_title']) ?></h1>
                <p class="tagline"><?= htmlspecialchars($t['header_subtitle']) ?></p>
                <div class="reservation-meta">
                    <span class="rez-no">Rez. No: <?= htmlspecialchars($res['reservation_id']) ?></span>
                    <?php
                        $status = strtolower($res['reservation_status'] ?? 'pending');
                        $status_classes = [
                            'pending' => 'status-amber',
                            'confirmed' => 'status-green',
                            'completed' => 'status-green',
                            'paid' => 'status-green',
                            'partially paid' => 'status-green',
                            'cancelled' => 'status-red'
                        ];
                        $badge_class = $status_classes[$status] ?? 'status-amber';
                    ?>
                    <div class="status-badge <?= $badge_class ?>">
                        <?= ucfirst($status) ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="ticket-body">
            <section class="card">
                <div class="card-header">
                    <div class="icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg></div>
                    <h3><?= htmlspecialchars($t['customer_info']) ?></h3>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label"><?= htmlspecialchars($t['name']) ?></span>
                        <span class="value"><?= htmlspecialchars(($res['customer_first_name'] ?? '') . ' ' . ($res['customer_last_name'] ?? '')) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label"><?= htmlspecialchars($t['email']) ?></span>
                        <span class="value"><?= htmlspecialchars($customer_email) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label"><?= htmlspecialchars($t['phone']) ?></span>
                        <span class="value"><?= htmlspecialchars($res['customer_phone'] ?? '') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label"><?= htmlspecialchars($t['passenger_count']) ?></span>
                        <span class="value"><?= (int)$res['passengers_adults'] ?> <?= htmlspecialchars($t['adult']) ?>, <?= (int)$res['passengers_children'] ?> <?= htmlspecialchars($t['child']) ?></span>
                    </div>
                </div>
            </section>
            
<?php if ($totalPassengers > 0): ?>
<?php
    // Yolcuların hepsinin girilip girilmediğini kontrol et
    $passengers_all_entered = ($missingPassengerSlots === 0);
?>
<section class="card" id="passenger-section">
    <div class="card-header">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
        </div>
        <h3><?= htmlspecialchars($t['passenger_list_title']) ?></h3>
        <?php if ($passengers_all_entered): // Eğer tüm yolcular girilmişse Düzenle butonunu göster ?>
        <div class="card-header-actions">
            <button class="btn btn-secondary btn-sm" id="edit-passengers-btn"><?= htmlspecialchars($t['edit_button'] ?? 'Düzenle') ?></button>
        </div>
        <?php endif; ?>
    </div>

    <div class="view-mode" style="<?= $passengers_all_entered ? 'display: block;' : 'display: none;' ?>">
        <div class="passenger-list" style="padding-top:15px;">
            <?php foreach ($passengers as $i => $p): ?>
                <div class="passenger-item">
                    <span class="p-index"><?= $i + 1 ?>.</span>
                    <span class="p-name"><?= htmlspecialchars(trim($p['first_name'] . ' ' . $p['last_name'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="edit-mode" style="<?= !$passengers_all_entered ? 'display: block;' : 'display: none;' ?>">
        <form method="POST" class="passenger-form" style="margin-top:10px;">
            <input type="hidden" name="action" value="save_passengers">
            <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['reservation_id']) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <?php for ($i = 0; $i < $totalPassengers; $i++): ?>
                <?php $p = $passengers[$i] ?? null; ?>
                <div class="passenger-form-row">
                    <span class="label" style="width:30px;"><?= $i + 1 ?>.</span>
                    <input type="hidden" name="passenger_id[]" value="<?= $p['id'] ?? '' ?>">
                    <input class="styled-input" type="text" name="passenger_name[]" value="<?= $p ? htmlspecialchars(trim($p['first_name'] . ' ' . $p['last_name'])) : '' ?>" placeholder="<?= htmlspecialchars($t['name']) ?>" required>
                </div>
            <?php endfor; ?>

            <button type="submit" class="btn btn-primary" style="margin-top: 20px; width:100%;">
                <?= htmlspecialchars($t['save_button']) ?>
            </button>
        </form>
        <?php if (!$passengers_all_entered): ?>
            <p class="passenger-warning" style="margin-top:15px; text-align:center;"><?= htmlspecialchars($t['passenger_warning']) ?></p>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($flight_info)): // EĞER UÇUŞ BİLGİSİ VARSA ?>
<section class="card" id="flight-info-section">
    <div class="card-header">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
        </div>
        <h3><?= htmlspecialchars($t['flight_info_title']) ?></h3>
        <?php
            // PNR ve Yön değerlerini önceden bulalım
            $flight_number_value = '';
            $flight_direction_value = '';
            foreach($flight_info as $item) {
                if (preg_match('/(pnr|uçuş|flight|numara)/i', $item['label'])) { $flight_number_value = $item['value'] ?? ''; }
                if (preg_match('/(yön|direction)/i', $item['label'])) { $flight_direction_value = $item['value'] ?? ''; }
            }
            $is_pnr_entered = !empty($flight_number_value);
        ?>
        <?php if ($is_pnr_entered): // Sadece PNR girilmişse Düzenle butonu görünsün ?>
        <div class="card-header-actions">
            <button class="btn btn-secondary btn-sm" id="edit-flight-btn"><?= htmlspecialchars($t['edit_button'] ?? 'Düzenle') ?></button>
        </div>
        <?php endif; ?>
    </div>

    <div class="flight-details-container">
        <div class="timing-details">
            <div class="info-item">
                <span class="label"><?= htmlspecialchars($t['flight_date_time']) ?></span>
                <span class="value highlight large-text"><?= htmlspecialchars(date('d.m.Y', strtotime($res['schedule_selected_date']))) ?> │ <?= htmlspecialchars($res['schedule_selected_time']) ?></span>
            </div>
            <div class="info-item">
                <span class="label"><?= htmlspecialchars($t['pickup_date_time']) ?></span>
                <?php if (!empty($res['schedule_pickup_date']) && !empty($res['schedule_pickup_time'])): ?>
                    <span class="value highlight large-text"><?= htmlspecialchars(date('d.m.Y', strtotime($res['schedule_pickup_date']))) ?> │ <?= htmlspecialchars($res['schedule_pickup_time']) ?></span>
                <?php else: ?>
                    <span class="value not-confirmed large-text"><?= htmlspecialchars($t['not_confirmed_yet']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <hr class="details-divider">
        
        <div class="view-mode" style="<?= $is_pnr_entered ? 'display: block;' : 'display: none;' ?>">
            <div class="additional-details">
                <div class="info-item">
                    <span class="label"><?= htmlspecialchars($t['flight_number']) ?></span>
                    <span class="value"><?= htmlspecialchars($flight_number_value) ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><?= htmlspecialchars($t['flight_direction'] ?? 'Uçuş Yönü') ?></span>
                    <span class="value"><?= htmlspecialchars($flight_direction_value) ?></span>
                </div>
            </div>
        </div>

        <div class="edit-mode" style="<?= !$is_pnr_entered ? 'display: block;' : 'display: none;' ?>">
            <form method="POST">
                <input type="hidden" name="action" value="save_flight_details">
                <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['reservation_id']) ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="additional-details">
                    <div class="form-item">
                        <label class="label"><?= htmlspecialchars($t['flight_number']) ?></label>
                        <input type="text" name="flight_number" value="<?= htmlspecialchars($flight_number_value) ?>" required class="styled-input">
                    </div>
                    
                    <div class="form-item">
                        <label class="label"><?= htmlspecialchars($t['flight_direction'] ?? 'Uçuş Yönü') ?></label>
                        <select name="flight_direction" class="styled-select" required>
                            <option value="<?= htmlspecialchars($t['flight_direction_domestic']) ?>" <?= trim($flight_direction_value) === $t['flight_direction_domestic'] ? 'selected' : '' ?>><?= htmlspecialchars($t['flight_direction_domestic']) ?></option>
                            <option value="<?= htmlspecialchars($t['flight_direction_international']) ?>" <?= trim($flight_direction_value) === $t['flight_direction_international'] ? 'selected' : '' ?>><?= htmlspecialchars($t['flight_direction_international']) ?></option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 20px; width: 100%;">
                    <?= htmlspecialchars($t['save_button']) ?>
                </button>
            </form>
            
            <?php if (!$is_pnr_entered): // Uyarı sadece PNR girilmemişse gösterilir ?>
                <p class="field-warning" style="margin-top: 15px;"><?= htmlspecialchars($t['enter_flight_number']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php else: // EĞER UÇUŞ BİLGİSİ YOKSA (MEVCUT YAPI KORUNDU) ?>
<section class="card">
    <div class="card-header">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0h18M-4.5 12h22.5" />
            </svg>
        </div>
        <h3><?= htmlspecialchars($t['trip_info_title']) ?></h3>
    </div>
    <div class="info-grid">
        <div class="info-item">
            <span class="label"><?= htmlspecialchars($t['selected_date_time']) ?></span>
            <span class="value highlight"><?= htmlspecialchars(date('d.m.Y', strtotime($res['schedule_selected_date']))) ?> │ <?= htmlspecialchars($res['schedule_selected_time']) ?></span>
        </div>
        <div class="info-item">
            <span class="label"><?= htmlspecialchars($t['confirmed_pickup_time']) ?></span>
            <?php if (!empty($res['schedule_pickup_date']) && !empty($res['schedule_pickup_time'])): ?>
                <span class="value highlight"><?= htmlspecialchars(date('d.m.Y', strtotime($res['schedule_pickup_date']))) ?> │ <?= htmlspecialchars($res['schedule_pickup_time']) ?></span>
            <?php else: ?>
                <span class="value not-confirmed"><?= htmlspecialchars($t['not_confirmed_yet']) ?></span>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>


            
            
            
            <?php if($vehicle_details['name']): ?>
            <section class="card vehicle-info-section">
                <div class="card-header">
                    <div class="icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75A2.25 2.25 0 016 16.5v-2.25m6-13.5v2.25A2.25 2.25 0 0010.5 5.25h-3a2.25 2.25 0 00-2.25 2.25v10.5a2.25 2.25 0 002.25 2.25h9.75a2.25 2.25 0 002.25-2.25v-5.25a2.25 2.25 0 00-2.25-2.25h-3a2.25 2.25 0 01-2.25-2.25z" /></svg></div>
                    <h3><?= htmlspecialchars($t['vehicle_info']) ?></h3>
                </div>
                <div class="vehicle-card-content">
                    <?php if($vehicle_details['photo_url']): ?>
                    <div>
                        <img class="vehicle-photo" src="https://app.rideandgoo.com/<?= htmlspecialchars($vehicle_details['photo_url']) ?>" alt="<?= htmlspecialchars($vehicle_details['name']) ?>">
                    </div>
                    <?php endif; ?>
                    <div>
                         <div class="info-item">
                             <span class="label"><?= htmlspecialchars($vehicle_details['model']) ?></span>
                             <span class="value"><?= htmlspecialchars($vehicle_details['name']) ?></span>
                         </div>
                        <div class="info-item" style="margin-top: 15px;">
                            <span class="label"><?= htmlspecialchars($t['capacity']) ?></span>
                            <span class="value">
                                <span style="margin-right: 15px;">👤 <?= (int)$vehicle_details['pax'] ?> <?= htmlspecialchars($t['passengers']) ?></span>
                                <span>🧳 <?= (int)$vehicle_details['luggage'] ?> <?= htmlspecialchars($t['luggage']) ?></span>
                            </span>
                        </div>
                    </div>
                </div>
                <?php if(!empty($vehicle_details['features'])): ?>
                <div class="info-item" style="margin-top:20px; border-top:1px solid var(--border-color); padding-top:20px;">
                    <span class="label"><?= htmlspecialchars($t['features']) ?></span>
                    <ul class="features-list">
                        <?php foreach($vehicle_details['features'] as $feature): ?>
                            <li class="feature-tag"><?= htmlspecialchars($feature) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if ($pickupLat && $pickupLng && $dropLat && $dropLng): ?>
            <section class="card">
                <div class="card-header">
                    <div class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A2.25 2.25 0 013 15.208V5.25A2.25 2.25 0 015.25 3h13.5A2.25 2.25 0 0121 5.25v9.958a2.25 2.25 0 01-1.553 2.068L14 20" />
                        </svg>
                    </div>
                    <h3><?= htmlspecialchars($t['route_map'] ?? 'Rota Haritası') ?></h3>
                </div>

                <div class="route-addresses">
                    <div class="route-point">
                        <div class="route-icon-container">
                            <svg class="route-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1.293-8.293a1 1 0 011.414 0L12 11.586l1.879-1.88a1 1 0 111.414 1.414l-2.5 2.5a1 1 0 01-1.414 0l-2.5-2.5a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="info-item">
                            <span class="label"><?= htmlspecialchars($t['pickup_address'] ?? 'Alınış Adresi') ?></span>
                            <span class="value"><?= htmlspecialchars($res['pickup_address'] ?? 'Belirtilmemiş') ?></span>
                        </div>
                    </div>
                    
                    <div class="route-point">
                        <div class="route-icon-container">
                            <svg class="route-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="info-item">
                            <span class="label"><?= htmlspecialchars($t['dropoff_address'] ?? 'Bırakılış Adresi') ?></span>
                            <span class="value"><?= htmlspecialchars($res['dropoff_address'] ?? 'Belirtilmemiş') ?></span>
                        </div>
                    </div>
                </div>
                <div id="map"></div>
            </section>
            <?php endif; ?>
            
            <?php
                $paid = (int)round((float)($res['paid_amount'] ?? 0));
                $total = (int)round((float)($res['gross_price'] ?? 0));
                $remaining = (int)round((float)($res['remaining_amount'] ?? 0));
                $status = strtolower($res['reservation_status'] ?? '');

                $status_class = '';
                $status_message = '';

                if ($total > 0) {
                    if ($paid === 0) {
                        if ($status === 'confirmed') {
                            // Durum: Ödeme yok, ama rezervasyon onaylı (Araçta Ödeme)
                            $status_class = 'status-green';
                            $status_message = $t['payment_status_pay_on_arrival'] ?? '';
                        } else {
                            // Durum: Ödeme yok, rezervasyon onaysız
                            $status_class = 'status-red';
                            $status_message = $t['payment_status_none'] ?? '';
                        }
                    } elseif ($paid < $total) {
                        // Durum: Kısmi ödeme yapılmış
                        $status_class = 'status-green';
                        $status_message = $t['payment_status_partial'] ?? '';
                    } else {
                        // Durum: Tamamı ödenmiş
                        $status_class = 'status-green';
                        $status_message = $t['payment_status_full'] ?? '';
                    }
                }
            ?>

            <section class="payment-card <?= $status_class ?>">
                <div class="card-header" style="border:none; padding-bottom:0; margin-bottom: 15px;">
                    <div class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 21z"/>
                        </svg>
                    </div>
                    <h3><?= htmlspecialchars($t['payment_info']) ?></h3>
                </div>

                <div class="payment-info-grid">
                    <div class="info-item">
                        <span class="label"><?= htmlspecialchars($t['total_price']) ?></span>
                        <span class="value" style="font-size:20px; font-weight:700;">
                            <?= number_format($total, 2, ',', '.') ?> <?= htmlspecialchars($res['currency']) ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="label"><?= htmlspecialchars($t['paid_amount']) ?></span>
                        <span class="value" style="font-size:20px; font-weight:700; color:var(--status-green-text);">
                            <?= number_format($paid, 2, ',', '.') ?> <?= htmlspecialchars($res['currency']) ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="label"><?= htmlspecialchars($t['remaining_amount']) ?></span>
                        <span class="value" style="font-size:20px; font-weight:700; color:var(--status-red-text);">
                            <?= number_format($remaining, 2, ',', '.') ?> <?= htmlspecialchars($res['currency']) ?>
                        </span>
                    </div>
                </div>

                <?php if (!empty($status_message)): ?>
                    <p class="payment-status-message"><?= htmlspecialchars($status_message) ?></p>
                <?php endif; ?>
            </section>
            
            
            <?php if ($remaining > 0): ?>
    <button id="pay-button" 
            style="background:#fe783d;color:#fff;padding:10px 20px;
                   border:none;border-radius:8px;cursor:pointer;">
        <?= htmlspecialchars($t['pay_now'] ?? 'Pay Now') ?>
    </button>
    <script>
    document.getElementById('pay-button').addEventListener('click', function () {
        fetch('/create-checkout-session.php?id=<?= $res['reservation_id'] ?>&t=<?= $token ?>')
            .then(r => r.json())
            .then(data => {
                const stripe = Stripe('<?= $stripePublic ?>');
                return stripe.redirectToCheckout({ sessionId: data.id });
            })
            .catch(err => console.error(err));
    });
    </script>
<?php endif; ?>

            
            
            </main>
        
        <footer class="ticket-footer">
            <div class="footer-links">
                <a href="https://wa.me/902422771666" target="_blank"> <div class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943c-.049-.084-.182-.133-.38-.232z"/></svg></div>
                    <span><?= htmlspecialchars($t['footer_whatsapp']) ?></span>
                </a>
                <a href="https://rideandgoo.com/frequently-asked-transfer-questions/" target="_blank">
                    <div class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg></div>
                    <span><?= htmlspecialchars($t['footer_faq']) ?></span>
                </a>
            </div>
        </footer>
    </div>

    <?php if ($pickupLat && $pickupLng && $dropLat && $dropLng): ?>
    <script>
        function initMap() {
            const map = new google.maps.Map(document.getElementById("map"), {
                zoom: 10,
                center: { lat: <?= $pickupLat ?>, lng: <?= $pickupLng ?> }
            });

            const directionsService = new google.maps.DirectionsService();
            const directionsRenderer = new google.maps.DirectionsRenderer({ map: map });

            const waypts = <?= !empty($stopoversJson) ? $stopoversJson : '[]' ?>.map(s => ({
                location: { lat: parseFloat(s.lat), lng: parseFloat(s.lng) },
                stopover: true
            }));

            directionsService.route({
                origin: { lat: <?= $pickupLat ?>, lng: <?= $pickupLng ?> },
                destination: { lat: <?= $dropLat ?>, lng: <?= $dropLng ?> },
                waypoints: waypts,
                optimizeWaypoints: false,
                travelMode: google.maps.TravelMode.DRIVING
            }, (response, status) => {
                if (status === "OK") {
                    directionsRenderer.setDirections(response);
                } else {
                    console.error("Rota çizilemedi, sebep: " + status);
                }
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {

    // Yolcu Listesi için Düzenleme Mantığı
    const passengerSection = document.getElementById('passenger-section');
    if (passengerSection) {
        const editPassengersBtn = document.getElementById('edit-passengers-btn');
        const passengerViewMode = passengerSection.querySelector('.view-mode');
        const passengerEditMode = passengerSection.querySelector('.edit-mode');

        editPassengersBtn.addEventListener('click', function() {
            passengerViewMode.style.display = 'none';
            passengerEditMode.style.display = 'block';
            this.style.display = 'none'; // Düzenle butonunu gizle
        });
    }

    // Uçuş Bilgileri için Düzenleme Mantığı
    const flightInfoSection = document.getElementById('flight-info-section');
    if (flightInfoSection) {
        const editFlightBtn = document.getElementById('edit-flight-btn');
        const flightViewMode = flightInfoSection.querySelector('.view-mode');
        const flightEditMode = flightInfoSection.querySelector('.edit-mode');
        
        // Başlangıçta düzenleme modunu gizli tut
        flightEditMode.style.display = 'none';
        
        editFlightBtn.addEventListener('click', function() {
            flightViewMode.style.display = 'none';
            flightEditMode.style.display = 'block';
            this.style.display = 'none'; // Düzenle butonunu gizle
        });
    }
});


    </script>
    

    
    <?php endif; ?>

</body>
</html>