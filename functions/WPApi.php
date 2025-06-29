<?php

/**
 * wpotomesaj.com WhatsApp REST API için PHP Sınıfı
 * * Bu sınıf, API'ye istek gönderme işlemlerini basitleştirir.
 * * @author Google Gemini
 * @version 1.0
 */
class WPApi
{
    /**
     * @var string API'nin ana URL adresi.
     */
    private $baseUrl = "https://cloud.wpotomesaj.com/api/";

    /**
     * @var string API için size özel erişim anahtarı (access token).
     */
    private $accessToken;

    /**
     * @var string Yönetilecek olan örnek kimliği (instance ID).
     */
    private $instanceId;

    /**
     * Sınıfı başlatır ve gerekli kimlik bilgilerini ayarlar.
     *
     * @param string $accessToken API Erişim Anahtarınız.
     * @param string $instanceId  Kullanılacak Örnek Kimliğiniz.
     */
    public function __construct(string $accessToken, string $instanceId)
    {
        $this->accessToken = $accessToken;
        $this->instanceId = $instanceId;
    }

    /**
     * API'ye cURL isteği gönderen özel (private) bir yardımcı metot.
     *
     * @param string $endpoint Istek atılacak API endpoint'i (örn: "send", "get_qrcode").
     * @param array $data      POST body'sinde gönderilecek veya GET query'sine eklenecek veri.
     * @param string $method    HTTP metodu ('POST' veya 'GET').
     * @return array             API'den dönen yanıtı ve HTTP durum kodunu içeren bir dizi.
     */
    private function sendRequest(string $endpoint, array $data = [], string $method = 'POST'): array
    {
        $url = $this->baseUrl . $endpoint;
        
        // POST body'si için kullanılacak veriler ve query string için kullanılacaklar ayrılıyor.
        $postData = null;
        $queryParams = [];

        // Bazı endpoint'ler parametreleri query string'de bekler.
        $queryStringEndpoints = ['get_qrcode', 'reboot', 'reset_instance', 'reconnect', 'set_webhook', 'create_instance'];

        if (in_array($endpoint, $queryStringEndpoints)) {
            $queryParams = $data;
        } else {
            // Diğerleri (send, send_group) JSON body'si bekler.
            $postData = json_encode($data);
        }
        
        // URL'ye query parametrelerini ekle
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Gerekirse true yapıp sertifika ayarlarını yapın

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($postData) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => 'cURL Hatası: ' . $error];
        }

        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true) ?? $response // JSON ise diziye çevir, değilse olduğu gibi bırak
        ];
    }
    
    // --- Örnek (Instance) Yönetimi ---

    /**
     * Yeni bir örnek (instance) oluşturur.
     * NOT: Bu statik bir metottur çünkü henüz bir instanceId olmadan çağrılması gerekir.
     *
     * @param string $accessToken API Erişim Anahtarınız.
     * @return array API yanıtı.
     */
    public static function createInstance(string $accessToken): array
    {
        // Bu metot özel olduğu için kendi cURL çağrısını yapar.
        $url = "https://cloud.wpotomesaj.com/api/create_instance?access_token=" . $accessToken;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? [];
    }
    
    /**
     * WhatsApp'a giriş için QR kodunu alır.
     *
     * @return array API yanıtı.
     */
    public function getQrCode(): array
    {
        $data = [
            'instance_id' => $this->instanceId,
            'access_token' => $this->accessToken
        ];
        return $this->sendRequest('get_qrcode', $data);
    }

    /**
     * Gelen bildirimler için bir webhook URL'si ayarlar.
     *
     * @param string $webhookUrl Bildirimlerin gönderileceği URL.
     * @param bool $enable       Webhook'u etkinleştirmek için true, devre dışı bırakmak için false.
     * @return array API yanıtı.
     */
    public function setWebhook(string $webhookUrl, bool $enable = true): array
    {
        $data = [
            'webhook_url' => $webhookUrl,
            'enable' => $enable,
            'instance_id' => $this->instanceId,
            'access_token' => $this->accessToken
        ];
        return $this->sendRequest('set_webhook', $data);
    }
    
    // --- Mesaj Gönderme ---

    /**
     * Belirtilen numaraya metin mesajı gönderir.
     *
     * @param string $recipientNumber Alıcı telefon numarası (örn: "905551234567").
     * @param string $message         Gönderilecek metin mesajı.
     * @return array API yanıtı.
     */
    public function sendText(string $recipientNumber, string $message): array
    {
        $data = [
            'number' => $recipientNumber,
            'type' => 'text',
            'message' => $message,
            'instance_id' => $this->instanceId,
            'access_token' => $this->accessToken
        ];
        return $this->sendRequest('send', $data);
    }

    /**
     * Belirtilen numaraya medya (resim, video) veya dosya gönderir.
     *
     * @param string $recipientNumber Alıcı telefon numarası.
     * @param string $mediaUrl        Medyaya ait genel erişilebilir URL.
     * @param string $caption         Medya için başlık/açıklama.
     * @param string|null $filename   Eğer bir belge gönderiliyorsa, dosya adı (örn: "fatura.pdf").
     * @return array API yanıtı.
     */
    public function sendMedia(string $recipientNumber, string $mediaUrl, string $caption = '', ?string $filename = null): array
    {
        $data = [
            'number' => $recipientNumber,
            'type' => 'media',
            'message' => $caption,
            'media_url' => $mediaUrl,
            'instance_id' => $this->instanceId,
            'access_token' => $this->accessToken
        ];

        if ($filename) {
            $data['filename'] = $filename;
        }

        return $this->sendRequest('send', $data);
    }
    
    // --- Grup Mesajları ---

    /**
     * Belirtilen gruba metin mesajı gönderir.
     *
     * @param string $groupId Grup kimliği (örn: "8498761xxxxxxxx@g.us").
     * @param string $message Gönderilecek metin mesajı.
     * @return array API yanıtı.
     */
    public function sendGroupText(string $groupId, string $message): array
    {
        $data = [
            'group_id' => $groupId,
            'type' => 'text',
            'message' => $message,
            'instance_id' => $this->instanceId,
            'access_token' => $this->accessToken
        ];
        return $this->sendRequest('send_group', $data);
    }
}