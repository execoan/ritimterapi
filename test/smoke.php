<?php
/**
 * RitimTerapi otomatik duman testi (smoke test).
 *
 * Kullanım:  php test/smoke.php     (Windows'ta kökteki test.bat da çalıştırır)
 * Çıkış kodu: 0 = tüm denetimler geçti, 1 = en az biri başarısız.
 *
 * GÜVENLİK İLKELERİ
 * - GERÇEK VERİYE DOKUNMAZ: RITIM_STORAGE ortam değişkeniyle geçici bir
 *   depolama dizini kullanan KENDİ sunucusunu (127.0.0.1, rastgele port) açar;
 *   test sonunda süreç ve geçici dizin silinir. Gerçek veritabanının özeti
 *   test öncesi/sonrası karşılaştırılarak dokunulmadığı ayrıca kanıtlanır.
 * - Test hesabı yalnız geçici gizli.php'de yaşar; HIZLI_GIRIS kapalı tutulur.
 * - İşlev denetimlerinin yanında güvenlik denetimleri koşar: giriş kapısı,
 *   CSRF, XSS kaçışı, dosya erişim engelleri, yol gezinmesi, deneme kilidi,
 *   veli çıktısında kilitli dil (CLAUDE.md §2).
 */

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Yalnız komut satırından çalışır.'); }

error_reporting(E_ALL);
ini_set('display_errors', '1');

$KOK  = dirname(__DIR__);
$TEMP = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'ritim-smoke-' . bin2hex(random_bytes(4));
$SIFRE = 'smoke-Sifre-' . random_int(1000, 9999);
$SUNUCU = null;
$TABAN = '';

/* ---------- Sonuç çatısı ---------- */
$GECTI = 0;
$KALDI = 0;
$BASARISIZLAR = [];

function bolum(string $ad): void { echo "\n— {$ad} —\n"; }

function dogrula(bool $kosul, string $ad, string $detay = ''): void
{
    global $GECTI, $KALDI, $BASARISIZLAR;
    if ($kosul) {
        $GECTI++;
        echo "  \u{2714} {$ad}\n";
    } else {
        $KALDI++;
        $BASARISIZLAR[] = $ad . ($detay !== '' ? " — {$detay}" : '');
        echo "  \u{2718} {$ad}" . ($detay !== '' ? "  [{$detay}]" : '') . "\n";
    }
}

/* ---------- Temizlik ---------- */
function dizin_sil(string $dizin): void
{
    if (!is_dir($dizin)) { return; }
    foreach (scandir($dizin) ?: [] as $ad) {
        if ($ad === '.' || $ad === '..') { continue; }
        $yol = $dizin . DIRECTORY_SEPARATOR . $ad;
        is_dir($yol) ? dizin_sil($yol) : @unlink($yol);
    }
    @rmdir($dizin);
}

register_shutdown_function(function () {
    global $SUNUCU, $TEMP;
    if (is_resource($SUNUCU)) {
        proc_terminate($SUNUCU);
        proc_close($SUNUCU);
    }
    dizin_sil($TEMP);
});

/* ---------- HTTP istemcisi (bağımlılıksız; çerez kavanozlu) ---------- */
function istek(string $metod, string $yol, ?array $veri, array &$cerez): array
{
    global $TABAN;
    $basliklar = "Accept: text/html\r\n";
    if ($cerez) {
        $parcalar = [];
        foreach ($cerez as $k => $v) { $parcalar[] = $k . '=' . $v; }
        $basliklar .= 'Cookie: ' . implode('; ', $parcalar) . "\r\n";
    }
    $icerik = '';
    if ($veri !== null) {
        $icerik = http_build_query($veri);
        $basliklar .= "Content-Type: application/x-www-form-urlencoded\r\n";
    }
    $ctx = stream_context_create(['http' => [
        'method'          => $metod,
        'header'          => $basliklar,
        'content'         => $icerik,
        'ignore_errors'   => true,   // 4xx/5xx gövdesini de al
        'follow_location' => 0,      // yönlendirmeyi ELLE izleriz
        'timeout'         => 15,
    ]]);
    $govde = @file_get_contents($TABAN . $yol, false, $ctx);
    $ham = $http_response_header ?? [];
    $durum = 0;
    $yer = '';
    foreach ($ham as $satir) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $satir, $m)) { $durum = (int)$m[1]; }
        if (stripos($satir, 'Location:') === 0) { $yer = trim(substr($satir, 9)); }
        if (stripos($satir, 'Set-Cookie:') === 0
            && preg_match('/Set-Cookie:\s*([^=]+)=([^;]*)/i', $satir, $m)) {
            $cerez[trim($m[1])] = trim($m[2]);
        }
    }
    return ['durum' => $durum, 'govde' => (string)$govde, 'yer' => $yer,
            'basliklar' => implode("\n", $ham)];
}

function git_(string $yol, array &$cerez): array { return istek('GET', $yol, null, $cerez); }
function gonder(string $yol, array $veri, array &$cerez): array { return istek('POST', $yol, $veri, $cerez); }

function csrf_al(string $yol, array &$cerez): string
{
    $y = git_($yol, $cerez);
    if (preg_match('/name="csrf_token" value="([^"]+)"/', $y['govde'], $m)) { return $m[1]; }
    return '';
}

/** Sunucuya HAM HTTP satırı gönderir (URL normalleştirmesini aşan testler için). */
function ham_istek(string $istekYolu): array
{
    global $PORT;
    $s = @fsockopen('127.0.0.1', $PORT, $hataNo, $hata, 5);
    if (!$s) { return ['durum' => 0, 'govde' => '']; }
    fwrite($s, "GET {$istekYolu} HTTP/1.0\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
    $yanit = stream_get_contents($s) ?: '';
    fclose($s);
    $durum = preg_match('#^HTTP/\S+\s+(\d{3})#', $yanit, $m) ? (int)$m[1] : 0;
    $govde = ($p = strpos($yanit, "\r\n\r\n")) !== false ? substr($yanit, $p + 4) : '';
    return ['durum' => $durum, 'govde' => $govde];
}

/** data-belge sarmalayıcısının içini kabaca ayıklar (veli-dili taraması için). */
function belge_icerigi(string $html): string
{
    // 'data-belge>' = sarmalayıcının açılışı (araç çubuğundaki data-belge-yazdir
    // gibi öznitelikleri yakalamamak için '>' ile aranır)
    $bas = strpos($html, 'data-belge>');
    if ($bas === false) { return ''; }
    $son = strpos($html, 'yazdirmada-gizle', $bas); // belgeden sonraki ekran öğeleri
    return substr($html, $bas, $son !== false ? $son - $bas : null);
}

/** Sayfadaki flash mesajını döndürür (tanı için). */
function flash_metni(string $html): string
{
    return preg_match('/class="flash[^"]*"[^>]*>\s*([^<]+)/', $html, $m) ? trim($m[1]) : '';
}

/* =================================================================
   HAZIRLIK
   ================================================================= */
echo "RitimTerapi duman testi\n";
echo "Geçici depo: {$TEMP}\n";

$gercekDb = $KOK . '/storage/ritim.sqlite';
$gercekOzet = is_file($gercekDb) ? md5_file($gercekDb) . ':' . filesize($gercekDb) : null;

if (!mkdir($TEMP, 0755, true)) { fwrite(STDERR, "Geçici dizin açılamadı.\n"); exit(1); }
// Test hesabı: güçlü rastgele şifre, hızlı giriş KAPALI (üretim duruşu)
file_put_contents($TEMP . '/gizli.php',
    "<?php\ndefine('PANEL_KULLANICILAR', ['admin' => '{$SIFRE}']);\ndefine('HIZLI_GIRIS', false);\n");

/* Boş port bul ve sunucuyu geçici depoyla başlat */
$PORT = 0;
for ($p = 18100; $p <= 18199; $p++) {
    $s = @fsockopen('127.0.0.1', $p, $hataNo, $hata, 0.2);
    if ($s) { fclose($s); continue; }
    $PORT = $p;
    break;
}
if ($PORT === 0) { fwrite(STDERR, "Boş port bulunamadı.\n"); exit(1); }
$TABAN = "http://127.0.0.1:{$PORT}/";

putenv('RITIM_STORAGE=' . $TEMP);
$SUNUCU = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:{$PORT}", 'router.php'],
    [1 => ['file', $TEMP . '/sunucu.log', 'a'], 2 => ['file', $TEMP . '/sunucu.log', 'a']],
    $borular, $KOK, null, ['bypass_shell' => true]
);
if (!is_resource($SUNUCU)) { fwrite(STDERR, "Sunucu başlatılamadı.\n"); exit(1); }

$hazir = false;
$jar = [];
for ($i = 0; $i < 100; $i++) {
    usleep(150000);
    $y = git_('giris.php', $jar);
    if ($y['durum'] === 200) { $hazir = true; break; }
}
if (!$hazir) {
    fwrite(STDERR, "Sunucu 15 sn içinde yanıt vermedi. Günlük:\n"
        . (string)@file_get_contents($TEMP . '/sunucu.log') . "\n");
    exit(1);
}
echo "Sunucu ayakta: {$TABAN} (PHP " . PHP_VERSION . ")\n";

/* =================================================================
   A) SUNUCU, STATİKLER, BAŞLIKLAR
   ================================================================= */
bolum('Sunucu ve statik dosyalar');
$y = git_('giris.php', $jar);
dogrula($y['durum'] === 200 && str_contains($y['govde'], 'csrf_token'), 'giris.php açılıyor ve CSRF alanı var');
dogrula(str_contains($y['basliklar'], 'Content-Security-Policy')
     && str_contains($y['basliklar'], 'X-Content-Type-Options'), 'güvenlik başlıkları gönderiliyor (CSP + nosniff)');
$y = git_('index.php', $jar);
dogrula($y['durum'] === 200 && !str_contains($y['govde'], 'Fatal error'), 'tanıtım sayfası hatasız açılıyor');
dogrula(git_('manifest.json', $jar)['durum'] === 200, 'manifest.json sunuluyor');
dogrula(git_('sw.js', $jar)['durum'] === 200, 'sw.js sunuluyor');

/* =================================================================
   B) DOSYA ERİŞİM ENGELLERİ (güvenlik)
   ================================================================= */
bolum('Dosya erişim engelleri');
$y = git_('storage/gizli.php', $jar);
dogrula($y['durum'] === 403 && !str_contains($y['govde'], 'PANEL_KULLANICILAR'), 'storage/gizli.php dışarıya kapalı');
dogrula(git_('storage/ritim.sqlite', $jar)['durum'] === 403, 'storage/ritim.sqlite dışarıya kapalı');
dogrula(git_('includes/model.php', $jar)['durum'] === 403, 'includes/ dışarıya kapalı');
$y = git_('.git/config', $jar);
dogrula($y['durum'] === 403 && !str_contains($y['govde'], '[core]'), '.git/ dışarıya kapalı');
dogrula(git_('test/smoke.php', $jar)['durum'] === 403, 'test/ dışarıya kapalı');
$y = ham_istek('/assets/../storage/gizli.php');
dogrula($y['durum'] !== 200 && !str_contains($y['govde'], 'PANEL_KULLANICILAR'), 'yol gezinmesi (..) engelli');

/* =================================================================
   C) KİMLİK KAPISI (güvenlik)
   ================================================================= */
bolum('Kimlik kapısı (çıkış yapılmışken)');
foreach (['panel.php', 'ogrenciler.php', 'yedek.php', 'disa-aktar.php?tur=protokol', 'metronom.php'] as $sayfa) {
    $y = git_($sayfa, $jar);
    dogrula($y['durum'] >= 300 && $y['durum'] < 400 && str_contains($y['yer'], 'giris.php'),
        "{$sayfa} girişe yönlendiriyor", 'durum ' . $y['durum']);
}

bolum('Giriş denetimleri');
// CSRF'siz giriş denemesi işe yaramamalı
gonder('giris.php', ['kullanici' => 'admin', 'sifre' => $SIFRE], $jar);
$y = git_('panel.php', $jar);
dogrula($y['durum'] >= 300 && str_contains($y['yer'], 'giris.php'), 'CSRF jetonu olmadan giriş reddediliyor');

// HIZLI_GIRIS=false iken tek tık girişi reddedilmeli
$t = csrf_al('giris.php', $jar);
gonder('giris.php', ['csrf_token' => $t, 'hizli' => 'admin'], $jar);
$y = git_('panel.php', $jar);
dogrula($y['durum'] >= 300 && str_contains($y['yer'], 'giris.php'), 'HIZLI_GIRIS kapalıyken tek tık giriş reddediliyor');

// Yanlış şifre
$t = csrf_al('giris.php', $jar);
$y = gonder('giris.php', ['csrf_token' => $t, 'kullanici' => 'admin', 'sifre' => 'yanlis'], $jar);
dogrula(str_contains($y['govde'], 'doğru değil'), 'yanlış şifre reddediliyor');

// Deneme kilidi: ayrı oturumda 5 başarısız → 6. denemede kilit; kilitliyken doğru şifre bile geçmez
$kilitJar = [];
for ($i = 0; $i < 5; $i++) {
    $t = csrf_al('giris.php', $kilitJar);
    gonder('giris.php', ['csrf_token' => $t, 'kullanici' => 'admin', 'sifre' => 'yanlis' . $i], $kilitJar);
}
$t = csrf_al('giris.php', $kilitJar);
$y = gonder('giris.php', ['csrf_token' => $t, 'kullanici' => 'admin', 'sifre' => $SIFRE], $kilitJar);
dogrula(str_contains($y['govde'], 'Çok fazla başarısız deneme'), '5 hatadan sonra deneme kilidi devrede (doğru şifre bile bekletiliyor)');

// Doğru giriş (ana oturum)
$t = csrf_al('giris.php', $jar);
$y = gonder('giris.php', ['csrf_token' => $t, 'kullanici' => 'admin', 'sifre' => $SIFRE], $jar);
dogrula($y['durum'] >= 300 && str_contains($y['yer'], 'panel.php'), 'doğru şifreyle giriş başarılı');
$y = git_('panel.php', $jar);
dogrula($y['durum'] === 200 && !str_contains($y['govde'], 'Beklenmeyen bir sorun'), 'panel açılıyor');

/* =================================================================
   D) ÇEKİRDEK SAYFALAR + GÖÇ + SEED
   ================================================================= */
bolum('Çekirdek sayfalar (giriş sonrası)');
$sayfalar = ['gruplar.php', 'ogrenciler.php', 'teknikler.php', 'sablonlar.php', 'ev-programi.php',
             'metronom.php', 'oturumlar.php', 'raporlar.php', 'site.php', 'yedek.php', 'calismalar.php'];
foreach ($sayfalar as $sayfa) {
    $y = git_($sayfa, $jar);
    dogrula($y['durum'] === 200 && !str_contains($y['govde'], 'Beklenmeyen bir sorun')
        && !str_contains($y['govde'], 'Fatal error'), "{$sayfa} hatasız açılıyor", 'durum ' . $y['durum']);
}

bolum('Göç ve başlangıç verisi');
try {
    $pdo = new PDO('sqlite:' . $TEMP . '/ritim.sqlite');
    $surum = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    $teknikAdet = (int)$pdo->query('SELECT COUNT(*) FROM teknikler')->fetchColumn();
    $pdo = null;
} catch (Throwable $e) { $surum = -1; $teknikAdet = -1; }
dogrula($surum === 9, 'göçler uygulanmış (user_version=9)', 'bulunan: ' . $surum);
dogrula($teknikAdet >= 18, 'teknik kütüphanesi seed edilmiş', 'adet: ' . $teknikAdet);
dogrula(str_contains(git_('teknikler.php', $jar)['govde'], 'Metronoma eşlik'), 'seed içeriği sayfada görünüyor');
dogrula(str_contains(git_('sablonlar.php', $jar)['govde'], 'RitimOdak'), 'RitimOdak şablonları hazır');
dogrula((bool)glob($TEMP . '/yedek/otomatik-*.sqlite'), 'günlük otomatik yedek alınmış');

/* =================================================================
   E) CRUD + XSS KAÇIŞI + PROTOKOL AKIŞI
   ================================================================= */
bolum('Veri akışı ve XSS kaçışı');
$t = csrf_al('gruplar.php', $jar);

// CSRF'siz POST veri OLUŞTURMAMALI
gonder('gruplar.php', ['ad' => 'CSRFSIZ-GRUP', 'yas_araligi' => '', 'gun' => 1, 'saat' => '',
                       'baslangic_tarihi' => date('Y-m-d'), 'aktif' => 1], $jar);
dogrula(!str_contains(git_('gruplar.php', $jar)['govde'], 'CSRFSIZ-GRUP'), 'CSRF jetonu olmadan kayıt oluşmuyor');

// XSS: kötü niyetli ad kaçırılarak basılmalı
$kotuAd = 'Duman <script>alert(1)</script>';
gonder('gruplar.php', ['csrf_token' => $t, 'ad' => $kotuAd, 'yas_araligi' => '7-10', 'gun' => 2,
                       'saat' => '17:00', 'baslangic_tarihi' => date('Y-m-d'), 'aktif' => 1], $jar);
$g = git_('gruplar.php', $jar)['govde'];
dogrula(str_contains($g, '&lt;script&gt;'), 'grup oluşturuldu ve HTML kaçışı uygulanıyor');
dogrula(!str_contains($g, '<script>alert(1)'), 'ham betik etiketi sayfaya sızmıyor (XSS yok)');
preg_match('/grup\.php\?id=(\d+)/', $g, $m);
$grupId = (int)($m[1] ?? 0);

// Öğrenci + protokol sonucu (standart işaretli)
$t = csrf_al('ogrenciler.php', $jar);
gonder('ogrenciler.php', ['csrf_token' => $t, 'kod' => 'DUMAN-1', 'dogum_yili' => 2015,
                          'grup_id' => $grupId, 'veli_notu' => '', 'aktif' => 1], $jar);
$o = git_('ogrenciler.php', $jar)['govde'];
preg_match('/ogrenci\.php\?id=(\d+)(?=[^>]*>DUMAN-1)/', $o, $m);
if (!isset($m[1])) { preg_match('/ogrenci\.php\?id=(\d+)/', $o, $m); }
$ogrenciId = (int)($m[1] ?? 0);
dogrula($ogrenciId > 0 && str_contains($o, 'DUMAN-1'), 'öğrenci kaydı oluşturuldu');

foreach ([[52, 1], [83, 1]] as [$skor, $std]) {
    $t = csrf_al('metronom.php', $jar);
    gonder('metronom.php', ['csrf_token' => $t, 'islem' => 'sonuc_kaydet', 'ogrenci_id' => $ogrenciId,
                            'protokol' => 'vurus_tutturma', 'skor' => $skor, 'bpm' => 72,
                            'detay' => '{}', 'standart' => $std], $jar);
}
$og = git_('ogrenci.php?id=' . $ogrenciId, $jar)['govde'];
dogrula(str_contains($og, '83') && str_contains($og, "\u{1F4CF}"), 'protokol sonuçları öğrenci sayfasında (📏 işaretli)');

bolum('Belgeler ve kilitli dil (CLAUDE.md §2)');
$sert = git_('sertifika.php?ogrenci_id=' . $ogrenciId . '&olcumler=1', $jar)['govde'];
dogrula(str_contains($sert, '52 / 100') && str_contains($sert, '83 / 100'), 'sertifikada ilk/son ölçüm görünüyor');
$sertBelge = belge_icerigi($sert);
$sizinti = ($p = strpos($sertBelge, 'RitimTerapi')) !== false
    ? '…' . substr($sertBelge, max(0, $p - 60), 130) . '…' : '';
$markaVar = str_contains($sertBelge, 'RİTİM ATÖLYESİ') || str_contains($sertBelge, 'Ritim Atölyesi');
dogrula($sertBelge !== '' && $markaVar && $sizinti === '',
    'sertifika belgesinde marka doğru (RitimTerapi geçmiyor)',
    $sertBelge === '' ? 'belge bloğu bulunamadı' : ($sizinti !== '' ? $sizinti : 'marka satırı yok'));
$veli = git_('rapor-veli.php?ogrenci_id=' . $ogrenciId, $jar)['govde'];
$veliBelge = belge_icerigi($veli);
$yasakli = ['terapi', 'tedavi', 'semptom', 'DEHB', 'hasta', 'iyileşme'];
$bulunan = array_values(array_filter($yasakli, fn($k) => mb_stripos($veliBelge, $k) !== false));
dogrula($veliBelge !== '' && !$bulunan, 'veli raporu belgesinde kilitli dil temiz', implode(', ', $bulunan));
dogrula(git_('rapor-haftalik.php', $jar)['durum'] === 200
     && git_('rapor-donemlik.php?grup_id=' . $grupId, $jar)['durum'] === 200, 'haftalık ve dönemlik raporlar açılıyor');

bolum('Dışa aktarma ve yedek uçları');
$csv = git_('disa-aktar.php?tur=protokol', $jar);
dogrula(strncmp($csv['govde'], "\xEF\xBB\xBF", 3) === 0 && str_contains($csv['govde'], 'Standart'),
    'protokol CSV: BOM + doğru başlıklar');
dogrula(git_('disa-aktar.php?tur=abc', $jar)['durum'] === 404, 'bilinmeyen dışa aktarma türü 404');
$yd = git_('yedek.php?islem=indir', $jar);
dogrula(strncmp($yd['govde'], 'SQLite format 3', 15) === 0
     && str_contains($yd['basliklar'], 'attachment'), 'yedek indirme tutarlı SQLite dosyası veriyor');
$yd = git_('yedek.php?islem=indir&dosya=' . rawurlencode('../gizli.php'), $jar);
dogrula($yd['durum'] === 404 && !str_contains($yd['govde'], 'PANEL_KULLANICILAR'), 'yedek indirme yol gezinmesine kapalı');

bolum('Hatalı girdi dayanıklılığı');
$y = git_('ogrenci.php?id=abc', $jar);
dogrula($y['durum'] === 404 && !str_contains($y['govde'], 'SQLSTATE'), 'bozuk id → temiz 404 (SQL hatası sızmıyor)');
$y = git_('sertifika.php?ogrenci_id=999999', $jar);
dogrula($y['durum'] === 404, 'olmayan kayıt → 404');

bolum('Temizlik (silme + basamaklı silme)');
$t = csrf_al('ogrenciler.php', $jar);
$y = gonder('ogrenci-sil.php', ['csrf_token' => $t, 'id' => $ogrenciId], $jar);
$flash = flash_metni(git_('ogrenciler.php', $jar)['govde']); // flash'ı tüket
$liste = git_('ogrenciler.php', $jar)['govde'];              // temiz liste
dogrula(!str_contains($liste, 'DUMAN-1'), 'öğrenci silindi',
    'durum ' . $y['durum'] . ' | flash: ' . $flash);
$t = csrf_al('gruplar.php', $jar);
$y = gonder('grup-sil.php', ['csrf_token' => $t, 'id' => $grupId], $jar);
$flash = flash_metni(git_('gruplar.php', $jar)['govde']);
$liste = git_('gruplar.php', $jar)['govde'];
dogrula(!str_contains($liste, '&lt;script&gt;'), 'grup silindi',
    'durum ' . $y['durum'] . ' | flash: ' . $flash);

/* =================================================================
   F) İZOLASYON KANITI
   ================================================================= */
bolum('İzolasyon');
dogrula(is_file($TEMP . '/ritim.sqlite'), 'test veritabanı geçici dizinde oluştu');
if ($gercekOzet === null) {
    dogrula(!is_file($gercekDb), 'gerçek veritabanı yok — dokunulmadı');
} else {
    $sonOzet = is_file($gercekDb) ? md5_file($gercekDb) . ':' . filesize($gercekDb) : null;
    dogrula($sonOzet === $gercekOzet, 'GERÇEK veritabanına dokunulmadı (özet birebir aynı)');
}

/* =================================================================
   SONUÇ
   ================================================================= */
echo "\n=================================\n";
echo "  Geçen: {$GECTI}   Kalan: {$KALDI}\n";
if ($BASARISIZLAR) {
    echo "\nBaşarısız denetimler:\n";
    foreach ($BASARISIZLAR as $b) { echo "  • {$b}\n"; }
    echo "\nSunucu günlüğü: {$TEMP}\\sunucu.log (temizlik kapatmadan kopyalayın)\n";
}
echo "=================================\n";
exit($KALDI === 0 ? 0 : 1);
