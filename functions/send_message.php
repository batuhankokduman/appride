<?php
// functions/send_message.php

function sendMessage(string $phone, string $message): bool
{
    // Forcemsg API bilgileri
    $api_url = 'https://service.forcemsg.com/api/send/whatsapp';
    $api_secret = '27ba91c9433fa99c1b02103ed0f0822e115149b9'; // .env ya da config dosyasına taşıman önerilir
    $account_id = '1743156722c81e728d9d4c2f636f067f89cc14862c67e675f2d6db5'; // özel hesap ID'n

    // --- KESİN ÇÖZÜM BAŞLANGICI ---

    // 1. ADIM: Mesajın içinde gelmiş olabilecek '&amp;' kodlamasını sert bir şekilde '&' karakterine geri çevir.
    // Bu, verinin bu fonksiyona gelmeden önce bozulmasını engeller.
    $message = str_replace('&amp;', '&', $message);

    // Gönderilecek verileri bir dizi olarak hazırla
    $payloadArray = [
        'secret' => $api_secret,
        'account' => $account_id,
        'recipient' => $phone,
        'type' => 'text',
        'message' => $message,
        'priority' => 1
    ];

    // 2. ADIM: PHP dizisini manuel olarak URL-uyumlu bir sorgu dizesine dönüştür.
    // Bu işlem, 'message' içindeki '&' karakterini doğru bir şekilde '%26' olarak kodlar.
    $postData = http_build_query($payloadArray);

    // --- KESİN ÇÖZÜM BİTİŞİ ---

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    
    // 3. ADIM: cURL'e dizi yerine, manuel oluşturduğumuz ve doğru kodlanmış string'i ver.
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Gönderilen verinin türünü net olarak belirtmek her zaman en iyisidir.
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200 && stripos($response, 'success') !== false) {
        return true;
    } else {
        // Hata durumunda detaylı loglama
        $logMessage = "ForceMsg API Hatası: HTTP Kodu: $httpCode";
        if (!empty($curlError)) {
            $logMessage .= " - cURL Hatası: $curlError";
        }
        $logMessage .= " - API Yanıtı: $response";
        error_log($logMessage);
        
        return false;
    }
}