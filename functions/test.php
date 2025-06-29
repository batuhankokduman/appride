<?php

// Sadece sendMessage fonksiyonunu içeren dosyayı dahil etmeniz yeterli.
// (sendMessage.php zaten WPApi.php'yi kendi içinde dahil ediyor)
require_once 'send_message.php';

// --- Testler ---

// 1. Telefon Numarasına Mesaj Gönderme
echo "<h2>Kişiye Mesaj Gönderme Testi</h2>";
$telefonNumarasi = "905455570627"; // Test edilecek numara
$mesaj1 = "Bu, en basit haliyle çalışan fonksiyondan gelen bir mesajdır.";

// DİKKAT: Artık $api parametresi yok!
if (sendMessage($telefonNumarasi, $mesaj1)) {
    echo "<b>Sonuç: Kişisel mesaj başarıyla gönderildi.</b> 👍";
} else {
    echo "<b>Sonuç: Kişisel mesaj gönderilemedi.</b> 👎";
}

echo "<hr>";


// 2. Gruba Mesaj Gönderme
echo "<h2>Gruba Mesaj Gönderme Testi</h2>";
$grupId = "8498761xxxxxxxx@g.us"; // Test edilecek grup ID'si
$mesaj2 = "Bu da gruba gönderilen süper basit bir mesaj.";

if (sendMessage($grupId, $mesaj2)) {
    echo "<b>Sonuç: Grup mesajı başarıyla gönderildi.</b> 👍";
} else {
    echo "<b>Sonuç: Grup mesajı gönderilemedi.</b> 👎";
}