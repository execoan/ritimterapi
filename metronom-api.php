<?php
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function api_cevap(array $veri, int $durum = 200): never
{
    http_response_code($durum);
    echo json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_cevap(['ok' => false, 'error' => 'Yalnız POST desteklenir.'], 405);
}

$ham = file_get_contents('php://input');
$veri = json_decode($ham ?: '{}', true);
if (!is_array($veri)) { api_cevap(['ok' => false, 'error' => 'Geçersiz JSON.'], 400); }
$gelenToken = (string)($veri['csrfToken'] ?? '');
if ($gelenToken === '' || !hash_equals(csrf_token(), $gelenToken)) {
    api_cevap(['ok' => false, 'error' => 'Oturum doğrulaması başarısız. Sayfayı yenileyin.'], 403);
}

$kullanici = metronom_kullanici_anahtari();
$islem = (string)($veri['islem'] ?? '');

try {
    if ($islem === 'set_kaydet') {
        $sonuc = metronom_seti_kaydet($kullanici, $veri);
        api_cevap($sonuc, $sonuc['ok'] ? 200 : 422);
    }
    if ($islem === 'set_sil') {
        api_cevap(['ok' => metronom_seti_sil($kullanici, (int)($veri['id'] ?? 0))]);
    }
    if ($islem === 'calisma_kaydet') {
        $sonuc = metronom_calisma_kaydet($kullanici, $veri);
        $sonuc['ozet'] = metronom_calisma_ozeti($kullanici);
        api_cevap($sonuc);
    }
    if ($islem === 'hedef_kaydet') {
        $hedef = metronom_hedef_kaydet(
            $kullanici,
            (int)($veri['gunlukDk'] ?? 20),
            (int)($veri['haftalikGun'] ?? 5)
        );
        api_cevap(['ok' => true, 'hedef' => $hedef, 'ozet' => metronom_calisma_ozeti($kullanici)]);
    }
    api_cevap(['ok' => false, 'error' => 'Bilinmeyen işlem.'], 400);
} catch (Throwable $e) {
    error_log('Metronom API hatası: ' . $e->getMessage());
    api_cevap(['ok' => false, 'error' => 'İşlem tamamlanamadı.'], 500);
}
