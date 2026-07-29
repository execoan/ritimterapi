<?php
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function motor_api_cevap(array $veri, int $durum = 200): never
{
    http_response_code($durum);
    echo json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    motor_api_cevap(['ok' => false, 'error' => 'Yalnız POST desteklenir.'], 405);
}
$veri = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($veri)) { motor_api_cevap(['ok' => false, 'error' => 'Geçersiz JSON.'], 400); }
$token = (string)($veri['csrfToken'] ?? '');
if ($token === '' || !hash_equals(csrf_token(), $token)) {
    motor_api_cevap(['ok' => false, 'error' => 'Oturum doğrulaması başarısız. Sayfayı yenileyin.'], 403);
}

$kullanici = metronom_kullanici_anahtari();
$islem = (string)($veri['islem'] ?? '');
try {
    if ($islem === 'protokol_kaydet') {
        $sonuc = motor_protokol_kaydet($kullanici, $veri);
        motor_api_cevap($sonuc, $sonuc['ok'] ? 200 : 422);
    }
    if ($islem === 'protokol_sil') {
        $ok = motor_protokol_sil($kullanici, (int)($veri['id'] ?? 0));
        motor_api_cevap(['ok' => $ok, 'error' => $ok ? null : 'Protokol bulunamadı.'], $ok ? 200 : 404);
    }
    if ($islem === 'sonuc_kaydet') {
        $sonuc = motor_sonuc_kaydet($kullanici, $veri);
        if ($sonuc['ok']) { $sonuc['sonKayitlar'] = motor_sonuclari_son($kullanici, 20); }
        motor_api_cevap($sonuc, $sonuc['ok'] ? 200 : 422);
    }
    motor_api_cevap(['ok' => false, 'error' => 'Bilinmeyen işlem.'], 400);
} catch (Throwable $e) {
    error_log('Motor koordinasyon API hatası: ' . $e->getMessage());
    motor_api_cevap(['ok' => false, 'error' => 'İşlem tamamlanamadı.'], 500);
}
