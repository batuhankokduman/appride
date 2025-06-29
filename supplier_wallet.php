<?php
// Gerekli dosyaların ve ayarların dahil edilmesi
require_once 'functions/db.php'; // Veritabanı bağlantısı ($pdo)
require_once 'includes/auth.php'; // Kullanıcı kimlik doğrulama
require_once 'includes/header.php'; // Sayfa başlığı, meta etiketleri vb.
require_once 'includes/menu.php';   // Navigasyon menüsü

// PHP hata raporlamasını geliştirme aşamasında açmak faydalı olabilir
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// Tedarikçi ID'sini al ve doğrula
$supplier_id = $_GET['supplier_id'] ?? null;
if (!$supplier_id || !is_numeric($supplier_id)) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Geçersiz Tedarikçi ID.</div></div>";
    require_once 'includes/footer.php';
    exit;
}

// Tedarikçi bilgilerini çek
try {
    $stmt_supplier = $pdo->prepare("SELECT id, full_name, company_name, currency AS supplier_main_currency FROM suppliers WHERE id = ?");
    $stmt_supplier->execute([$supplier_id]);
    $supplier = $stmt_supplier->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Tedarikçi bilgileri çekilirken veritabanı hatası: " . $e->getMessage());
    echo "<div class='container mt-4'><div class='alert alert-danger'>Tedarikçi bilgileri alınırken bir hata oluştu. Lütfen daha sonra tekrar deneyin.</div></div>";
    require_once 'includes/footer.php';
    exit;
}

if (!$supplier) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Tedarikçi bulunamadı (ID: " . htmlspecialchars($supplier_id) . ").</div></div>";
    require_once 'includes/footer.php';
    exit;
}

// Finansal toplamları başlat (EUR cinsinden)
$total_system_alacak_eur = 0.0;
$total_system_borc_eur = 0.0;
$total_manual_alacak_eur = 0.0;
$total_manual_borc_eur = 0.0;
$gercek_bakiye_eur = 0.0;

// EUR/TRY kurunu site_settings tablosundan çek
$eur_to_try_rate = null;
$site_settings_rate_error_message = "";

$rate_table_name = 'site_settings';
$rate_key_column = 'setting_key';
$rate_value_column = 'setting_value';
$rate_setting_key_value = 'eur_to_try'; // Doğrulanmış anahtar

$sql_rate = "SELECT {$rate_value_column} FROM {$rate_table_name} WHERE {$rate_key_column} = :setting_key LIMIT 1";

try {
    $stmt_rate = $pdo->prepare($sql_rate);
    $stmt_rate->execute(['setting_key' => $rate_setting_key_value]);
    $rate_result = $stmt_rate->fetch(PDO::FETCH_ASSOC);

    if ($rate_result && isset($rate_result[$rate_value_column]) && is_numeric($rate_result[$rate_value_column]) && $rate_result[$rate_value_column] > 0) {
        $eur_to_try_rate = (float) $rate_result[$rate_value_column];
        error_log("EUR to TRY kuru başarıyla çekildi ('{$rate_setting_key_value}'): " . $eur_to_try_rate);
    } else {
        $site_settings_rate_error_message = "EUR to TRY kuru '{$rate_table_name}' tablosunda bulunamadı/geçersiz. Anahtar: '{$rate_setting_key_value}'.";
        error_log($site_settings_rate_error_message . " Sorgu sonucu: " . print_r($rate_result, true));
    }
} catch (PDOException $e) {
    $site_settings_rate_error_message = "EUR to TRY kuru çekilirken veritabanı hatası: " . $e->getMessage();
    error_log($site_settings_rate_error_message);
}

// Para birimi dönüştürme yardımcı fonksiyonu
function convert_to_eur($amount, $currency, $eur_to_try_rate_param) {
    if ($amount === null || $currency === null) return null;
    $amount_float = (float) $amount;
    $currency_upper = strtoupper(trim($currency));

    if ($currency_upper === 'EUR') {
        return $amount_float;
    }
    if ($currency_upper === 'TRY') {
        if ($eur_to_try_rate_param !== null && $eur_to_try_rate_param > 0) {
            return $amount_float / $eur_to_try_rate_param;
        } else {
            error_log("convert_to_eur: TRY to EUR dönüşümü için kur eksik/geçersiz. Miktar: {$amount_float}, Kur: " . print_r($eur_to_try_rate_param, true));
            return null;
        }
    }
    error_log("convert_to_eur: Desteklenmeyen para birimi veya dönüşüm hatası. Miktar: {$amount_float}, Para Birimi: {$currency_upper}");
    return null;
}

// 1. Sistem Alacak/Borç Hesaplaması
try {
    $stmt_system_items = $pdo->prepare("
        SELECT
            r.reservation_id AS res_id_debug,
            r.remaining_amount,
            r.currency AS reservation_currency,
            sal.supplier_cost_eur,
            sal.id AS sal_id_debug
        FROM
            supplier_assignment_logs sal
        JOIN
            reservations r ON sal.reservation_id = r.reservation_id
        WHERE
            sal.supplier_id = :supplier_id
    ");
    $stmt_system_items->execute(['supplier_id' => $supplier['id']]);
    $system_items = $stmt_system_items->fetchAll(PDO::FETCH_ASSOC);

    error_log("Tedarikçi ID {$supplier['id']} için sistem kalemleri sorgusu: " . count($system_items) . " adet kayıt.");

    foreach ($system_items as $item) {
        $remaining_amount_eur = convert_to_eur($item['remaining_amount'], $item['reservation_currency'], $eur_to_try_rate);
        $current_supplier_cost_eur = (float) $item['supplier_cost_eur'];

        if ($remaining_amount_eur !== null) {
            $borc_alacak_item = $remaining_amount_eur - $current_supplier_cost_eur;
            if ($borc_alacak_item > 0) {
                $total_system_alacak_eur += $borc_alacak_item;
            } elseif ($borc_alacak_item < 0) {
                $total_system_borc_eur += abs($borc_alacak_item);
            }
        } else {
            error_log("Sistem kalemi (SAL ID: {$item['sal_id_debug']}) için remaining_amount EUR'a çevrilemedi. Miktar: {$item['remaining_amount']}, Para Birimi: {$item['reservation_currency']}");
        }
    }
} catch (PDOException $e) {
    error_log("Sistem alacak/borç hesaplaması sırasında DB hatası (Tedarikçi ID: {$supplier['id']}): " . $e->getMessage());
    $page_error_message = "Sistem borç/alacak verileri çekilirken bir hata oluştu.";
}

// 2. Manuel Alacak/Borç Hesaplaması
$manual_transactions = [];
try {
    $stmt_transactions = $pdo->prepare("SELECT id, type, amount, currency, note, created_at FROM supplier_transactions WHERE supplier_id = ? ORDER BY created_at DESC");
    $stmt_transactions->execute([$supplier['id']]);
    $manual_transactions = $stmt_transactions->fetchAll(PDO::FETCH_ASSOC);

    foreach ($manual_transactions as $t) {
        $amount_eur = convert_to_eur($t['amount'], $t['currency'], $eur_to_try_rate);
        if ($amount_eur !== null) {
            if ($t['type'] === 'in') {
                $total_manual_alacak_eur += $amount_eur;
            } elseif ($t['type'] === 'out') {
                $total_manual_borc_eur += $amount_eur;
            }
        } else {
             error_log("Manuel işlem (ID: {$t['id']}) EUR'a çevrilemedi. Miktar: {$t['amount']}, Para Birimi: {$t['currency']}");
        }
    }
} catch (PDOException $e) {
    error_log("Manuel işlemler çekilirken DB hatası (Tedarikçi ID: {$supplier['id']}): " . $e->getMessage());
    if (!isset($page_error_message)) $page_error_message = "Manuel işlemler çekilirken bir hata oluştu.";
}

// 3. Gerçek Bakiye Hesapla
$gercek_bakiye_eur = ($total_system_alacak_eur - $total_system_borc_eur) + ($total_manual_alacak_eur - $total_manual_borc_eur);

?>

<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f4f7f6;
        color: #333;
        line-height: 1.6;
    }
    .wallet-container {
        max-width: 1200px;
        width: 1000px;
        margin: 30px auto;
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .wallet-header {
        border-bottom: 1px solid #e0e0e0;
        padding-bottom: 20px;
        margin-bottom: 25px;
    }
    .wallet-header h1 {
        font-size: 28px;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    .wallet-header .supplier-company {
        font-size: 16px;
        color: #7f8c8d;
    }
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .summary-item {
        background-color: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        border-left: 5px solid #3498db;
    }
    .summary-item.alacak { border-left-color: #2ecc71; }
    .summary-item.borc { border-left-color: #e74c3c; }
    .summary-item .label {
        font-size: 14px;
        color: #555;
        margin-bottom: 5px;
        display: block;
    }
    .summary-item .value {
        font-size: 22px;
        font-weight: bold;
        color: #333;
    }
    .balance-highlight {
        background-color: #2c3e50;
        color: #fff;
        padding: 25px;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 30px;
    }
    .balance-highlight .label {
        font-size: 18px;
        margin-bottom: 8px;
    }
    .balance-highlight .value {
        font-size: 32px;
        font-weight: bold;
    }
    .actions-bar {
        margin-bottom: 30px;
    }
    .btn-custom-action {
        background-color: #3498db;
        color: white;
        padding: 12px 20px;
        border: none;
        border-radius: 6px;
        text-decoration: none;
        font-size: 16px;
        transition: background-color 0.3s ease;
    }
    .btn-custom-action:hover {
        background-color: #2980b9;
        color: white;
    }
    .transactions-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .transactions-table th, .transactions-table td {
        border: 1px solid #e0e0e0;
        padding: 12px 15px;
        text-align: left;
        font-size: 15px;
    }
    .transactions-table th {
        background-color: #f2f2f2;
        font-weight: 600;
        color: #333;
    }
    .transactions-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    .transactions-table .type-in { color: #27ae60; font-weight: bold; }
    .transactions-table .type-out { color: #c0392b; font-weight: bold; }
    .alert-custom-warning {
        background-color: #fcf8e3;
        color: #8a6d3b;
        padding: 15px;
        border: 1px solid #faebcc;
        border-radius: 6px;
        margin-bottom: 20px;
    }
    .alert-custom-danger {
        background-color: #f2dede;
        color: #a94442;
        padding: 15px;
        border: 1px solid #ebccd1;
        border-radius: 6px;
        margin-bottom: 20px;
    }
    .section-title {
        font-size: 22px;
        color: #2c3e50;
        margin-top: 40px;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #3498db;
    }
    @media (max-width: 768px) {
        .summary-grid {
            grid-template-columns: 1fr; /* Stack on smaller screens */
        }
        .wallet-header h1 { font-size: 24px; }
        .balance-highlight .value { font-size: 28px; }
    }
</style>

<div class="container wallet-container">

    <div class="wallet-header">
        <h1><?= htmlspecialchars($supplier['full_name']) ?></h1>
        <?php if (!empty($supplier['company_name'])): ?>
            <div class="supplier-company"><?= htmlspecialchars($supplier['company_name']) ?></div>
        <?php endif; ?>
        <p class="mt-1">Cari Hesap Özeti (EUR)</p>
    </div>

    <?php if (!empty($page_error_message)): ?>
        <div class="alert alert-custom-danger"><?= htmlspecialchars($page_error_message) ?></div>
    <?php endif; ?>

    <?php if ($eur_to_try_rate === null && !empty($site_settings_rate_error_message)): ?>
        <div class="alert alert-custom-warning">
            <strong>Uyarı:</strong> EUR/TRY kuru alınamadı. TRY cinsinden tutarlar doğru çevrilemeyebilir. Lütfen sistem yöneticinizle iletişime geçin.
            <small class="d-block mt-1">Log Mesajı: <?= htmlspecialchars($site_settings_rate_error_message) ?></small>
        </div>
    <?php endif; ?>

    <h3 class="section-title">Genel Bakiye Durumu</h3>
    <div class="summary-grid">
        <div class="summary-item alacak">
            <span class="label">Sistem Alacağı</span>
            <span class="value"><?= number_format($total_system_alacak_eur, 2, ',', '.') ?> EUR</span>
        </div>
        <div class="summary-item borc">
            <span class="label">Sistem Borcu</span>
            <span class="value"><?= number_format($total_system_borc_eur, 2, ',', '.') ?> EUR</span>
        </div>
        <div class="summary-item alacak">
            <span class="label">Manuel Alacak</span>
            <span class="value"><?= number_format($total_manual_alacak_eur, 2, ',', '.') ?> EUR</span>
        </div>
        <div class="summary-item borc">
            <span class="label">Manuel Borç</span>
            <span class="value"><?= number_format($total_manual_borc_eur, 2, ',', '.') ?> EUR</span>
        </div>
    </div>

    <div class="balance-highlight">
        <div class="label">📊 GERÇEK CARİ BAKİYE</div>
        <div class="value"><?= number_format($gercek_bakiye_eur, 2, ',', '.') ?> EUR</div>
        <small>(Sistem Alacağı - Sistem Borcu) + (Manuel Alacak - Manuel Borç)</small>
    </div>

    <div class="actions-bar">
        <a href="supplier_wallet_add.php?supplier_id=<?= $supplier['id'] ?>" class="btn btn-custom-action">
            <i class="fas fa-plus-circle"></i> Yeni Manuel İşlem Ekle
        </a>
    </div>

    <h3 class="section-title">Manuel İşlem Geçmişi</h3>
    <?php if (empty($manual_transactions)): ?>
        <p>Bu tedarikçi için kayıtlı manuel işlem bulunmamaktadır.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="transactions-table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>İşlem Tipi</th>
                        <th>Tutar</th>
                      
                        <th>Açıklama</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($manual_transactions as $t): ?>
                        <tr>
                            <td><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></td>
                            <td class="<?= $t['type'] === 'in' ? 'type-in' : 'type-out' ?>">
                                <?= $t['type'] === 'in' ? '🟢 Ödeme Alındı' : '🔴 Ödeme Yapıldı' ?>
                            </td>
                            <td><?= number_format($t['amount'], 2, ',', '.') ?> <?= htmlspecialchars($t['currency']) ?></td>
                            <td><?= nl2br(htmlspecialchars($t['note'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e0e0e0; text-align: center;">
        <small class="text-muted">Tüm hesaplamalar EUR cinsinden yapılmıştır. Güncel EUR/TRY kuru (kullanıldıysa): <?= $eur_to_try_rate ? number_format($eur_to_try_rate, 4, ',', '.') : 'Alınamadı' ?></small>
    </div>

</div> <?php require_once 'includes/footer.php'; // Sayfa altlığı, scriptler vb. ?>