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

function json_gonder(string $yol, array $veri, array &$cerez): array
{
    global $TABAN;
    $basliklar = "Accept: application/json\r\nContent-Type: application/json\r\n";
    if ($cerez) {
        $parcalar = [];
        foreach ($cerez as $k => $v) { $parcalar[] = $k . '=' . $v; }
        $basliklar .= 'Cookie: ' . implode('; ', $parcalar) . "\r\n";
    }
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => $basliklar,
        'content' => json_encode($veri, JSON_UNESCAPED_UNICODE),
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 15,
    ]]);
    $govde = @file_get_contents($TABAN . $yol, false, $ctx);
    $ham = $http_response_header ?? [];
    $durum = 0;
    foreach ($ham as $satir) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $satir, $m)) { $durum = (int)$m[1]; }
    }
    return ['durum' => $durum, 'govde' => (string)$govde,
            'json' => json_decode((string)$govde, true)];
}

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

/* CSP'nin asıl işi XSS'i durdurmak; script-src'de 'unsafe-inline' varsa
   enjekte edilen betik zaten çalışır ve başlık süs olur. Gerileme testi: */
preg_match('/script-src([^;]*)/i', $y['basliklar'], $mS);
$scriptSrc = $mS[1] ?? '';
dogrula($scriptSrc !== '' && !str_contains($scriptSrc, 'unsafe-inline'),
    "CSP script-src'de 'unsafe-inline' YOK");
dogrula(str_contains($scriptSrc, "'nonce-"), 'CSP script-src nonce taşıyor');
/* Nonce her istekte değişmeli; sabit olsaydı saldırgan bir kez okuyup kullanırdı. */
preg_match("/'nonce-([^']+)'/", $y['basliklar'], $m1);
$y2 = git_('giris.php', $jar);
preg_match("/'nonce-([^']+)'/", $y2['basliklar'], $m2);
dogrula(!empty($m1[1]) && !empty($m2[1]) && $m1[1] !== $m2[1], 'CSP nonce her istekte değişiyor');

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

/*
 * REGRESYON — router.php yol normalleştirmesi.
 * Bu liste gerçek bir açıktan doğdu: "/storage%5Critim.sqlite" (%5C = ters
 * bölü) 200 dönüp TÜM veritabanını girişsiz veriyordu. Eski denetim yalnız
 * düz "storage/ritim.sqlite" biçimine bakıyordu, o yüzden yakalayamadı.
 * Yeni bir ayırıcı/kodlama numarası akla geldikçe BU LİSTEYE eklenmeli.
 */
$sizdiranlar = [];
$hedefler = [
    'storage%5Critim.sqlite', '//storage/ritim.sqlite', '///storage/ritim.sqlite',
    '//storage%5Critim.sqlite', '/./storage/ritim.sqlite', '/.//storage/ritim.sqlite',
    'STORAGE%5CRITIM.SQLITE', 'sToRaGe%5Critim.sqlite', 'storage%5C%5Critim.sqlite',
    '%2f%2fstorage%5Critim.sqlite', 'storage%5Cgizli.php', '//storage/gizli.php',
    'includes%5Cdb.php', '//includes/model.php', '.git%5Cconfig', '//.git/config',
    'test%5Csmoke.php', '//test/smoke.php', 'docs%5Colcum-kilavuzu.md',
    'ritim.sqlite', 'start.bat',
];
foreach ($hedefler as $h) {
    $y = ham_istek('/' . $h);
    if ($y['durum'] === 200) { $sizdiranlar[] = $h . ' (' . strlen($y['govde']) . ' bayt)'; }
}
dogrula(!$sizdiranlar,
    'ayırıcı/kodlama varyantlarının hiçbiri korunan dosyayı sızdırmıyor',
    implode(', ', $sizdiranlar));

/* Sızan gövde gerçekten veritabanı mıydı? (sihirli bayt ile kesin denetim) */
$y = ham_istek('/storage%5Critim.sqlite');
dogrula(!str_starts_with($y['govde'], 'SQLite format 3'),
    'ters bölü varyantı SQLite dosyası döndürmüyor');

/* Apache/htdocs kurulumu için de koruma dosyası bulunmalı (router orada çalışmaz) */
$ht = $KOK . '/.htaccess';
dogrula(is_file($ht) && str_contains((string)file_get_contents($ht), 'storage'),
    '.htaccess var ve storage dizinini engelliyor (XAMPP htdocs kurulumu)');

/* =================================================================
   C) KİMLİK KAPISI (güvenlik)
   ================================================================= */
bolum('Kimlik kapısı (çıkış yapılmışken)');
foreach (['panel.php', 'ogrenciler.php', 'yedek.php', 'disa-aktar.php?tur=protokol', 'metronom.php', 'motor-studyo.php', 'grup-atolyesi.php', 'poliritim.php', 'ritim-okuma.php', 'tini-kartlari.php'] as $sayfa) {
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

/*
 * REGRESYON — TERS VEKİL ALTINDA TEK TIK GİRİŞ.
 *
 * Bu denetim gerçek bir açıktan doğdu: hızlı giriş yalnız
 * REMOTE_ADDR === '127.0.0.1' koşuluna bakıyordu. Sunucuda uygulama
 * nginx/Apache ters vekilinin arkasında PHP-FPM ile çalışır ve orada
 * REMOTE_ADDR HER İSTEK İÇİN 127.0.0.1'dir — yani şifresiz tek tık girişi
 * tüm internete açılırdı. Artık iki koşul birlikte aranıyor: DAGITIM='yerel'
 * VE döngü arayüzü. Test yerleşik sunucuda koştuğu için REMOTE_ADDR zaten
 * 127.0.0.1'dir; yani bu senaryonun BİREBİR kendisi sınanıyor.
 */
$gizliYol = $TEMP . '/gizli.php';
$gizliYedek = (string)file_get_contents($gizliYol);
// HIZLI_GIRIS açık + DAGITIM yayın → tek tık giriş KAPALI olmalı
file_put_contents($gizliYol,
    "<?php\ndefine('PANEL_KULLANICILAR', ['admin' => '{$SIFRE}']);\n"
    . "define('DAGITIM', 'yayin');\ndefine('HIZLI_GIRIS', true);\n");
$vekilJar = [];
$t = csrf_al('giris.php', $vekilJar);
gonder('giris.php', ['csrf_token' => $t, 'hizli' => 'admin'], $vekilJar);
$y = git_('panel.php', $vekilJar);
dogrula($y['durum'] >= 300 && str_contains($y['yer'], 'giris.php'),
    'DAGITIM=yayin iken tek tık giriş REDDEDİLİYOR (ters vekil senaryosu)',
    'durum ' . $y['durum']);
// Butonlar da hiç basılmamalı — görünmeyen ama çalışan bir yol kalmasın
$y = git_('giris.php', $vekilJar);
dogrula(!str_contains($y['govde'], 'name="hizli"'),
    'DAGITIM=yayin iken tek tık butonları HTML\'de hiç yok');

// Aynı yapılandırma yerel dağıtımda ÇALIŞMALI (kolaylık kaybolmasın)
file_put_contents($gizliYol,
    "<?php\ndefine('PANEL_KULLANICILAR', ['admin' => '{$SIFRE}']);\n"
    . "define('DAGITIM', 'yerel');\ndefine('HIZLI_GIRIS', true);\n");
$yerelJar = [];
$t = csrf_al('giris.php', $yerelJar);
gonder('giris.php', ['csrf_token' => $t, 'hizli' => 'admin'], $yerelJar);
$y = git_('panel.php', $yerelJar);
dogrula($y['durum'] === 200, 'DAGITIM=yerel iken tek tık giriş çalışıyor', 'durum ' . $y['durum']);
file_put_contents($gizliYol, $gizliYedek);   // yapılandırmayı geri koy

/* Şifre özeti: düz metin yazılan gizli.php kendiliğinden özete çevrildi mi? */
$t = csrf_al('giris.php', $jar);            // bir istek atıp göçü tetikle
$gizliSonrasi = (string)file_get_contents($gizliYol);
dogrula(str_contains($gizliSonrasi, '$2y$') && !str_contains($gizliSonrasi, "'{$SIFRE}'"),
    'düz metin şifre kendiliğinden password_hash özetine çevrildi');
$t = csrf_al('giris.php', $jar);
$y = gonder('giris.php', ['csrf_token' => $t, 'kullanici' => 'admin', 'sifre' => $SIFRE], $jar);
dogrula($y['durum'] >= 300 && !str_contains($y['yer'], 'giris.php'),
    'özete çevrildikten sonra ESKİ şifreyle giriş hâlâ çalışıyor (kurulum kırılmadı)');

/*
 * HIZ SINIRI — ÇEREZ SİLİNEREK ATLATILAMAMALI.
 * Sayaç önceden $_SESSION'daydı: her istekte yeni çerez kavanozu kullanan bir
 * istemci sıfır sayaçla başlıyordu, yani panel şifresine sınırsız kaba kuvvet
 * mümkündü. Sayaç artık veritabanında ve IP başına. Bu test tam da bypass'ı
 * dener: HER DENEMEDE YENİ KAVANOZ kullanır.
 */
for ($i = 0; $i < 11; $i++) {
    $temizJar = [];                                   // her denemede çerezleri at
    $t = csrf_al('giris.php', $temizJar);
    $y = gonder('giris.php', ['csrf_token' => $t, 'kullanici' => 'admin', 'sifre' => 'yanlis' . $i], $temizJar);
}
dogrula(str_contains($y['govde'], 'Çok fazla başarısız deneme'),
    'hız sınırı ÇEREZ SİLİNEREK atlatılamıyor (sunucu tarafı sayaç)',
    'son yanıtta kilit mesajı yok');

// Kilitliyken doğru şifre bile geçmemeli
$kilitJar = [];
$t = csrf_al('giris.php', $kilitJar);
$y = gonder('giris.php', ['csrf_token' => $t, 'kullanici' => 'admin', 'sifre' => $SIFRE], $kilitJar);
dogrula(str_contains($y['govde'], 'Çok fazla başarısız deneme'),
    'kilitliyken doğru şifre de reddediliyor');

/* Sayaç gerçekten veritabanında mı? (oturumda kalmadığının kanıtı) */
$pdoHiz = new PDO('sqlite:' . $TEMP . '/ritim.sqlite');
$hizSatir = (int)$pdoHiz->query("SELECT COALESCE(MAX(adet),0) FROM hiz_siniri WHERE anahtar LIKE 'giris:%'")->fetchColumn();
$pdoHiz = null;
dogrula($hizSatir >= 11, 'deneme sayacı veritabanında tutuluyor', 'bulunan: ' . $hizSatir);

/* Kilidi kaldır ki sonraki denetimler giriş yapabilsin */
$pdoHiz = new PDO('sqlite:' . $TEMP . '/ritim.sqlite');
$pdoHiz->exec('DELETE FROM hiz_siniri');
$pdoHiz = null;

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
             'metronom.php', 'motor-studyo.php', 'poliritim.php', 'ritim-okuma.php', 'tini-kartlari.php', 'grup-atolyesi.php', 'oturumlar.php', 'raporlar.php', 'site.php', 'yedek.php', 'calismalar.php',
             'plan.php'];
foreach ($sayfalar as $sayfa) {
    $y = git_($sayfa, $jar);
    dogrula($y['durum'] === 200 && !str_contains($y['govde'], 'Beklenmeyen bir sorun')
        && !str_contains($y['govde'], 'Fatal error'), "{$sayfa} hatasız açılıyor", 'durum ' . $y['durum']);
}

bolum('Üst menü (kategoriler)');
$panel = git_('panel.php', $jar)['govde'];
// Yalnız gerçek <summary> başlıklarını say (sayfa metninde geçen kelimeler değil)
preg_match_all('#<summary class="nav-link">(.*?)</summary>#s', $panel, $sm);
$basliklar = array_map(fn($h) => trim(strip_tags($h)), $sm[1] ?? []);
foreach (['Atölye', 'İçerik', 'Raporlar', 'Yönetim'] as $kategori) {
    $var = (bool)array_filter($basliklar, fn($b) => str_contains($b, $kategori));
    dogrula($var, "menüde '{$kategori}' kategorisi var", 'başlıklar: ' . implode(' | ', $basliklar));
}
// Menüdeki her hedef gerçekten açılmalı (kırık bağlantı regresyonu)
preg_match_all('#class="nav-menu-oge[^"]*"\s+href="([^"]+)"#', $panel, $mm);
$hedefler = array_unique(array_map(fn($h) => ltrim(html_entity_decode($h), '/'), $mm[1] ?? []));
dogrula(count($hedefler) >= 10, 'menü maddeleri okunabildi', 'bulunan: ' . count($hedefler));
$kirik = [];
foreach ($hedefler as $hedef) {
    $y = git_($hedef, $jar);
    if ($y['durum'] !== 200) { $kirik[] = $hedef . ' (' . $y['durum'] . ')'; }
}
dogrula(!$kirik, 'menüdeki tüm bağlantılar açılıyor', implode(', ', $kirik));

bolum('Göç ve başlangıç verisi');
try {
    $pdo = new PDO('sqlite:' . $TEMP . '/ritim.sqlite');
    $surum = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    $teknikAdet = (int)$pdo->query('SELECT COUNT(*) FROM teknikler')->fetchColumn();
    $pdo = null;
} catch (Throwable $e) { $surum = -1; $teknikAdet = -1; }
dogrula($surum === 18, 'göçler uygulanmış (user_version=18)', 'bulunan: ' . $surum);
dogrula($teknikAdet >= 18, 'teknik kütüphanesi seed edilmiş', 'adet: ' . $teknikAdet);
dogrula(str_contains(git_('teknikler.php', $jar)['govde'], 'Metronoma eşlik'), 'seed içeriği sayfada görünüyor');
$grupAtolyesi = git_('grup-atolyesi.php', $jar)['govde'];
dogrula(str_contains($grupAtolyesi, 'Grup Atölyesi Çalışma Alanı')
    && str_contains($grupAtolyesi, 'Dört hazırlık vuruşuyla başlat'),
    'grup atölyesi akış kurucusu görünüyor');
dogrula(str_contains($grupAtolyesi, 'Katılım isteğe bağlıdır')
    && str_contains($grupAtolyesi, 'Tam dönüş ve göğse vurma'),
    'grup atölyesi seçim ve güvenlik sınırlarını gösteriyor');
$calismaDefteri = git_('calismalar.php', $jar)['govde'];
dogrula(str_contains($calismaDefteri, 'Prosocial Consequences of Interpersonal Synchrony')
    && str_contains($calismaDefteri, 'The effects of rhythmic auditory stimulation'),
    'grup atölyesi bilimsel kaynakları kayıt defterinde');
dogrula(str_contains(git_('sablonlar.php', $jar)['govde'], 'RitimOdak'), 'RitimOdak şablonları hazır');
dogrula((bool)glob($TEMP . '/yedek/otomatik-*.sqlite'), 'günlük otomatik yedek alınmış');

bolum('Metronom çalışma merkezi');
$t = csrf_al('metronom.php', $jar);
$y = json_gonder('metronom-api.php', [
    'csrfToken' => $t, 'islem' => 'set_kaydet', 'ad' => 'Duman Seti', 'aciklama' => 'Otomatik test',
    'adimlar' => [[
        'baslik' => 'Isınma', 'bpm' => 88, 'olcu' => 4, 'payda' => 4, 'gruplama' => '4',
        'alt' => 2, 'swing' => 50, 'poliritim' => 3, 'poliDuzey' => 55,
        'girisOlcu' => 1, 'sureSn' => 60, 'gecis' => 'otomatik', 'desen' => [2,1,1,1]
    ]]
], $jar);
$setId = (int)($y['json']['id'] ?? 0);
dogrula($y['durum'] === 200 && $setId > 0, 'setlist API ile kaydediliyor', 'durum ' . $y['durum']);

$y = json_gonder('metronom-api.php', [
    'csrfToken' => $t, 'islem' => 'calisma_kaydet', 'tur' => 'setlist', 'setId' => $setId,
    'baslik' => 'Duman Seti', 'sureSn' => 75, 'bpmMin' => 88, 'bpmMax' => 88,
    'detay' => ['adimSayisi' => 1, 'tamamlananAdim' => 1]
], $jar);
dogrula($y['durum'] === 200 && !empty($y['json']['ozet']['sonKayitlar']),
    'setlist çalışması otomatik günlüğe yazılıyor', 'durum ' . $y['durum']);

$y = json_gonder('metronom-api.php', [
    'csrfToken' => $t, 'islem' => 'hedef_kaydet', 'gunlukDk' => 25, 'haftalikGun' => 4
], $jar);
dogrula(($y['json']['hedef']['gunlukDk'] ?? 0) === 25 && ($y['json']['hedef']['haftalikGun'] ?? 0) === 4,
    'çalışma hedefi kalıcı kaydediliyor');

$y = json_gonder('metronom-api.php', ['islem' => 'set_sil', 'id' => $setId], $jar);
dogrula($y['durum'] === 403, 'çalışma merkezi API uçları CSRF ile korunuyor', 'durum ' . $y['durum']);

bolum('İki el motor koordinasyon modülü');
$t = csrf_al('motor-studyo.php', $jar);
$y = json_gonder('motor-api.php', [
    'csrfToken' => $t, 'islem' => 'protokol_kaydet',
    'ad' => 'Duman Motor Protokolü', 'hedef' => 'İki el dönüşümlü zamanlama',
    'desen' => 'donusumlu', 'bpm' => 60, 'sureSn' => 30, 'hazirlikVurus' => 4, 'toleransMs' => 140
], $jar);
$motorProtokolId = (int)($y['json']['protokol']['id'] ?? 0);
dogrula($y['durum'] === 200 && $motorProtokolId > 0,
    'uzman motor protokolü API ile kaydediliyor', 'durum ' . $y['durum']);

$y = json_gonder('motor-api.php', [
    'csrfToken' => $t, 'islem' => 'sonuc_kaydet', 'protokolId' => $motorProtokolId,
    'durum' => 'tamamlandi', 'skor' => 87, 'dogruluk' => 91,
    'detay' => ['sonuc' => ['asimetriMs' => 28], 'zamanlamaSurumu' => 'test']
], $jar);
dogrula($y['durum'] === 200 && !empty($y['json']['sonKayitlar']),
    'motor koordinasyon sonucu ayrıntılarıyla kaydediliyor', 'durum ' . $y['durum']);

$y = json_gonder('motor-api.php', [
    'csrfToken' => $t, 'islem' => 'sonuc_kaydet', 'protokolId' => $motorProtokolId,
    'durum' => 'guvenlik', 'skor' => 99, 'dogruluk' => 40,
    'detay' => ['durmaNedeni' => 'Olağandışı yorulma']
], $jar);
$guvenlikKaydi = $y['json']['sonKayitlar'][0] ?? [];
dogrula($y['durum'] === 200 && ($guvenlikKaydi['durum'] ?? '') === 'guvenlik'
    && array_key_exists('skor', $guvenlikKaydi) && $guvenlikKaydi['skor'] === null,
    'güvenlik durdurması skor üretmeden kaydediliyor', 'durum ' . $y['durum']);

$y = json_gonder('motor-api.php', [
    'islem' => 'sonuc_kaydet', 'protokolId' => $motorProtokolId,
    'durum' => 'guvenlik', 'dogruluk' => 0, 'detay' => ['durmaNedeni' => 'test']
], $jar);
dogrula($y['durum'] === 403, 'motor koordinasyon API uçları CSRF ile korunuyor', 'durum ' . $y['durum']);

/* =================================================================
   E) CRUD + XSS KAÇIŞI + PROTOKOL AKIŞI
   ================================================================= */
bolum('Veri akışı ve XSS kaçışı');
$t = csrf_al('gruplar.php', $jar);

// CSRF'siz POST veri OLUŞTURMAMALI
gonder('gruplar.php', ['ad' => 'CSRFSIZ-GRUP', 'yas_araligi' => '', 'gun' => 1, 'saat' => '',
                       'baslangic_tarihi' => date('Y-m-d'), 'aktif' => 1], $jar);
dogrula(!str_contains(git_('gruplar.php', $jar)['govde'], 'CSRFSIZ-GRUP'), 'CSRF jetonu olmadan kayıt oluşmuyor');

gonder('gruplar.php', ['csrf_token' => $t, 'ad' => 'GECERSIZ-SAAT', 'yas_araligi' => '', 'gun' => 1,
                       'saat' => '99:99', 'baslangic_tarihi' => '2026-02-30', 'aktif' => 1], $jar);
$pdoKontrol = new PDO('sqlite:' . $TEMP . '/ritim.sqlite');
$gecersizGrup = (int)$pdoKontrol->query("SELECT COUNT(*) FROM gruplar WHERE ad = 'GECERSIZ-SAAT'")->fetchColumn();
$pdoKontrol = null;
dogrula($gecersizGrup === 0,
    'takvim dışı tarih ve saat sunucu tarafında reddediliyor');

// XSS: kötü niyetli ad kaçırılarak basılmalı
$kotuAd = 'Duman <script>alert(1)</script>';
gonder('gruplar.php', ['csrf_token' => $t, 'ad' => $kotuAd, 'yas_araligi' => '7-10', 'gun' => 2,
                       'saat' => '17:00', 'baslangic_tarihi' => date('Y-m-d'), 'aktif' => 1], $jar);
$g = git_('gruplar.php', $jar)['govde'];
dogrula(str_contains($g, '&lt;script&gt;'), 'grup oluşturuldu ve HTML kaçışı uygulanıyor');
dogrula(!str_contains($g, '<script>alert(1)'), 'ham betik etiketi sayfaya sızmıyor (XSS yok)');
preg_match('/grup\.php\?id=(\d+)/', $g, $m);
$grupId = (int)($m[1] ?? 0);

// Şablon uygulaması da takvimde olmayan bir tarihi oturuma dönüştürmemeli.
$pdoKontrol = new PDO('sqlite:' . $TEMP . '/ritim.sqlite');
$sablonId = (int)$pdoKontrol->query('SELECT id FROM plan_sablonlari ORDER BY id LIMIT 1')->fetchColumn();
$oturumOnce = (int)$pdoKontrol->query("SELECT COUNT(*) FROM oturumlar WHERE grup_id = {$grupId}")->fetchColumn();
$t = csrf_al('sablonlar.php?id=' . $sablonId, $jar);
gonder('sablonlar.php?id=' . $sablonId, [
    'csrf_token' => $t, 'islem' => 'uygula', 'sablon_id' => $sablonId,
    'grup_id' => $grupId, 'baslangic' => '2026-02-30', 'mod' => 'A', 'b_fark' => 3,
], $jar);
$oturumSonra = (int)$pdoKontrol->query("SELECT COUNT(*) FROM oturumlar WHERE grup_id = {$grupId}")->fetchColumn();
$pdoKontrol = null;
dogrula($oturumSonra === $oturumOnce,
    'takvim dışı tarih plan şablonundan oturum üretmiyor');

// Öğrenci + protokol sonucu (standart işaretli)
$t = csrf_al('ogrenciler.php', $jar);
gonder('ogrenciler.php', ['csrf_token' => $t, 'kod' => 'BOZUK-YIL', 'dogum_yili' => '2015abc',
                          'grup_id' => $grupId, 'veli_notu' => '', 'aktif' => 1], $jar);
$pdoKontrol = new PDO('sqlite:' . $TEMP . '/ritim.sqlite');
$gecersizOgrenci = (int)$pdoKontrol->query("SELECT COUNT(*) FROM ogrenciler WHERE kod = 'BOZUK-YIL'")->fetchColumn();
$pdoKontrol = null;
dogrula($gecersizOgrenci === 0,
    'bozuk doğum yılı sunucu tarafında reddediliyor');
$t = csrf_al('ogrenciler.php', $jar);
gonder('ogrenciler.php', ['csrf_token' => $t, 'kod' => 'DUMAN-1', 'dogum_yili' => 2015,
                          'grup_id' => $grupId, 'veli_notu' => '', 'aktif' => 1], $jar);
$o = git_('ogrenciler.php', $jar)['govde'];
preg_match('/ogrenci\.php\?id=(\d+)(?=[^>]*>DUMAN-1)/', $o, $m);
if (!isset($m[1])) { preg_match('/ogrenci\.php\?id=(\d+)/', $o, $m); }
$ogrenciId = (int)($m[1] ?? 0);
dogrula($ogrenciId > 0 && str_contains($o, 'DUMAN-1'), 'öğrenci kaydı oluşturuldu');

bolum('Çoklu grup üyeliği ve katılımcı portalı');
// İkinci bir grup oluştur, var olan kişiyi bu gruba da ekle.
$t = csrf_al('gruplar.php', $jar);
gonder('gruplar.php', [
    'csrf_token' => $t, 'ad' => 'Çarşamba Ansambl', 'tur' => 'grup',
    'yas_araligi' => '10-14', 'gun' => 3, 'saat' => '18:00',
    'baslangic_tarihi' => date('Y-m-d'), 'aktif' => 1,
], $jar);
$pdo = new PDO('sqlite:' . $TEMP . '/ritim.sqlite');
$grup2Id = (int)$pdo->query("SELECT id FROM gruplar WHERE ad = 'Çarşamba Ansambl'")->fetchColumn();
$t = csrf_al('grup.php?id=' . $grup2Id, $jar);
gonder('grup.php?id=' . $grup2Id, [
    'csrf_token' => $t, 'id' => $grup2Id, 'islem' => 'duyuru_ekle',
    'baslik' => 'GECERSIZ-TARIH-DUYURU', 'mesaj' => '',
    'yayin_tarihi' => '2026-02-30', 'bitis_tarihi' => '',
], $jar);
$gecersizDuyuru = (int)$pdo->query("SELECT COUNT(*) FROM grup_duyurulari
    WHERE baslik = 'GECERSIZ-TARIH-DUYURU'")->fetchColumn();
dogrula($gecersizDuyuru === 0, 'takvim dışı duyuru tarihi kaydedilmiyor');
$t = csrf_al('grup.php?id=' . $grup2Id, $jar);
gonder('grup.php?id=' . $grup2Id, [
    'csrf_token' => $t, 'id' => $grup2Id, 'islem' => 'uye_ekle', 'ogrenci_id' => $ogrenciId,
], $jar);
$uyeSayisi = (int)$pdo->query("SELECT COUNT(*) FROM grup_uyelikleri
    WHERE ogrenci_id = {$ogrenciId} AND aktif = 1")->fetchColumn();
$og = git_('ogrenci.php?id=' . $ogrenciId, $jar)['govde'];
dogrula($uyeSayisi === 2 && str_contains($og, 'Çarşamba Ansambl'),
    'var olan kişi ikinci gruba eklenebiliyor ve iki üyelik birlikte görünüyor');

// Aynı gün/saatteki başka bir derse aynı kişi eklenememeli.
$t = csrf_al('gruplar.php', $jar);
gonder('gruplar.php', [
    'csrf_token' => $t, 'ad' => 'Çakışan Prova', 'tur' => 'grup',
    'yas_araligi' => '', 'gun' => 3, 'saat' => '18:00',
    'baslangic_tarihi' => date('Y-m-d'), 'aktif' => 1,
], $jar);
$cakisanGrupId = (int)$pdo->query("SELECT id FROM gruplar WHERE ad = 'Çakışan Prova'")->fetchColumn();
$t = csrf_al('grup.php?id=' . $cakisanGrupId, $jar);
gonder('grup.php?id=' . $cakisanGrupId, [
    'csrf_token' => $t, 'id' => $cakisanGrupId, 'islem' => 'uye_ekle', 'ogrenci_id' => $ogrenciId,
], $jar);
$cakismaUyelik = (int)$pdo->query("SELECT COUNT(*) FROM grup_uyelikleri
    WHERE grup_id = {$cakisanGrupId} AND ogrenci_id = {$ogrenciId} AND aktif = 1")->fetchColumn();
$cakismaFlash = flash_metni(git_('grup.php?id=' . $cakisanGrupId, $jar)['govde']);
dogrula($cakismaUyelik === 0 && str_contains($cakismaFlash, 'Program çakışması'),
    'aynı katılımcının çakışan gün-saatteki derse eklenmesi engelleniyor', $cakismaFlash);

// Mevcut dersin saati değiştirilirken de üyelerin diğer programları korunmalı.
$t = csrf_al('grup.php?id=' . $grupId, $jar);
gonder('grup.php?id=' . $grupId, [
    'csrf_token' => $t, 'id' => $grupId, 'islem' => 'guncelle',
    'ad' => $kotuAd, 'tur' => 'grup', 'yas_araligi' => '7-10',
    'gun' => 3, 'saat' => '18:00', 'baslangic_tarihi' => date('Y-m-d'), 'aktif' => 1,
], $jar);
$korunanSaat = $pdo->query("SELECT gun, saat FROM gruplar WHERE id = {$grupId}")->fetch();
$guncellemeFlash = flash_metni(git_('grup.php?id=' . $grupId, $jar)['govde']);
dogrula((int)$korunanSaat['gun'] === 2 && $korunanSaat['saat'] === '17:00'
    && str_contains($guncellemeFlash, 'program çakışması'),
    'ders günü/saatini değiştirmek üyelerde çakışma yaratıyorsa değişiklik reddediliyor',
    $guncellemeFlash);

// Grup arkadaşı: portalda yalnızca takma adı görünmeli.
$t = csrf_al('ogrenciler.php', $jar);
gonder('ogrenciler.php', [
    'csrf_token' => $t, 'kod' => 'ARKADAS-2', 'dogum_yili' => 2012,
    'grup_id' => $grup2Id, 'veli_notu' => 'GIZLI-VELI-NOTU', 'aktif' => 1,
], $jar);
$arkadasId = (int)$pdo->query("SELECT id FROM ogrenciler WHERE kod = 'ARKADAS-2'")->fetchColumn();

// Grup duyurusu yayın aralığıyla yönetilmeli.
$t = csrf_al('grup.php?id=' . $grup2Id, $jar);
gonder('grup.php?id=' . $grup2Id, [
    'csrf_token' => $t, 'id' => $grup2Id, 'islem' => 'duyuru_ekle',
    'baslik' => 'Bagetlerini getir', 'mesaj' => 'Bu hafta çalışma pedi de yanında olsun.',
    'yayin_tarihi' => date('Y-m-d'), 'bitis_tarihi' => date('Y-m-d', strtotime('+7 days')),
], $jar);
$duyuruId = (int)$pdo->query("SELECT id FROM grup_duyurulari WHERE baslik = 'Bagetlerini getir'")->fetchColumn();
$t = csrf_al('grup.php?id=' . $grup2Id, $jar);
gonder('grup.php?id=' . $grup2Id, [
    'csrf_token' => $t, 'id' => $grup2Id, 'islem' => 'duyuru_ekle',
    'baslik' => 'GELECEK-DUYURU', 'mesaj' => 'Henüz görünmemeli.',
    'yayin_tarihi' => date('Y-m-d', strtotime('+3 days')), 'bitis_tarihi' => '',
], $jar);

// Özel derste ikinci aktif kişi sunucu tarafında reddedilmeli.
$t = csrf_al('gruplar.php', $jar);
gonder('gruplar.php', [
    'csrf_token' => $t, 'ad' => 'DUMAN Özel Ders', 'tur' => 'ozel',
    'yas_araligi' => '', 'gun' => 5, 'saat' => '16:00',
    'baslangic_tarihi' => date('Y-m-d'), 'aktif' => 1,
], $jar);
$ozelGrupId = (int)$pdo->query("SELECT id FROM gruplar WHERE ad = 'DUMAN Özel Ders'")->fetchColumn();
$t = csrf_al('grup.php?id=' . $ozelGrupId, $jar);
gonder('grup.php?id=' . $ozelGrupId, [
    'csrf_token' => $t, 'id' => $ozelGrupId, 'islem' => 'uye_ekle', 'ogrenci_id' => $ogrenciId,
], $jar);
$t = csrf_al('grup.php?id=' . $ozelGrupId, $jar);
gonder('grup.php?id=' . $ozelGrupId, [
    'csrf_token' => $t, 'id' => $ozelGrupId, 'islem' => 'uye_ekle', 'ogrenci_id' => $arkadasId,
], $jar);
$ozelSayisi = (int)$pdo->query("SELECT COUNT(*) FROM grup_uyelikleri
    WHERE grup_id = {$ozelGrupId} AND aktif = 1")->fetchColumn();
$ozelFlash = flash_metni(git_('grup.php?id=' . $ozelGrupId, $jar)['govde']);
dogrula($ozelSayisi === 1 && str_contains($ozelFlash, 'yalnızca bir'),
    'özel derse ikinci aktif katılımcı eklenmesi engelleniyor', $ozelFlash);

// Yaklaşan grup programı ve teknik adları katılımcı portalında görünmeli.
$yarin = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$pdo->prepare('INSERT INTO oturumlar (grup_id, tarih, hafta_no, notlar, created_at, protokol)
               VALUES (?, ?, 2, ?, ?, ?)')
    ->execute([$grup2Id, $yarin, 'GIZLI-OTURUM-NOTU', date('Y-m-d H:i:s'), '']);
$portalOturumId = (int)$pdo->lastInsertId();
$teknikId = (int)$pdo->query('SELECT id FROM teknikler ORDER BY id LIMIT 1')->fetchColumn();
$pdo->prepare('INSERT INTO oturum_teknikleri
    (oturum_id, teknik_id, sira, sure_dk, uygulama_notu, islendi)
    VALUES (?, ?, 1, 12, ?, NULL)')
    ->execute([$portalOturumId, $teknikId, 'GIZLI-UYGULAMA-NOTU']);
$teknikAdi = (string)$pdo->query("SELECT ad FROM teknikler WHERE id = {$teknikId}")->fetchColumn();
$erisimKodu = (string)$pdo->query("SELECT erisim_kodu FROM ogrenciler WHERE id = {$ogrenciId}")->fetchColumn();
$evJar = [];
$t = csrf_al('ev.php', $evJar);
gonder('ev.php', ['csrf_token' => $t, 'islem' => 'giris', 'kod' => $erisimKodu], $evJar);
$evHtml = git_('ev.php', $evJar)['govde'];
dogrula(str_contains($evHtml, 'Çarşamba Ansambl')
    && str_contains($evHtml, 'ARKADAS-2')
    && str_contains($evHtml, $teknikAdi)
    && str_contains($evHtml, 'Bagetlerini getir')
    && !str_contains($evHtml, 'GELECEK-DUYURU'),
    'katılımcı programı, üye takma adlarını ve yalnız geçerli duyuruları görebiliyor');
dogrula(!str_contains($evHtml, 'GIZLI-VELI-NOTU')
    && !str_contains($evHtml, 'GIZLI-OTURUM-NOTU')
    && !str_contains($evHtml, 'GIZLI-UYGULAMA-NOTU')
    && !str_contains($evHtml, '>2012<'),
    'portal grup arkadaşlarının kişisel ve eğitmen notlarını sızdırmıyor');

$t = csrf_al('grup.php?id=' . $grup2Id, $jar);
gonder('grup.php?id=' . $grup2Id, [
    'csrf_token' => $t, 'id' => $grup2Id, 'islem' => 'duyuru_durum',
    'duyuru_id' => $duyuruId, 'aktif_yap' => 0,
], $jar);
$evHtmlKapali = git_('ev.php', $evJar)['govde'];
dogrula(!str_contains($evHtmlKapali, 'Bagetlerini getir'),
    'yayından kaldırılan duyuru katılımcı portalından anında kalkıyor');

// Portal yalnız ödevin kendi modül türünü ve etkin tarih aralığını kaydedebilmeli.
$calismaId = (int)$pdo->query("SELECT id FROM ev_calismalari
    WHERE tur = 'vurus_tutturma' ORDER BY id LIMIT 1")->fetchColumn();
$pdo->prepare('INSERT INTO ev_odevleri
    (ogrenci_id, calisma_id, baslangic, bitis, hedef_gun, notlar, created_at)
    VALUES (?, ?, ?, ?, 5, ?, ?)')
    ->execute([$ogrenciId, $calismaId, date('Y-m-d'), date('Y-m-d', strtotime('+2 days')), '', date('Y-m-d H:i:s')]);
$aktifOdevId = (int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO ev_odevleri
    (ogrenci_id, calisma_id, baslangic, bitis, hedef_gun, notlar, created_at)
    VALUES (?, ?, ?, ?, 5, ?, ?)')
    ->execute([$ogrenciId, $calismaId, '2025-01-01', '2025-01-02', '', date('Y-m-d H:i:s')]);
$eskiOdevId = (int)$pdo->lastInsertId();
$evSonucOnce = (int)$pdo->query("SELECT COUNT(*) FROM protokol_sonuclari
    WHERE ogrenci_id = {$ogrenciId} AND kaynak = 'ev'")->fetchColumn();
$tEv = csrf_al('ev.php', $evJar);
gonder('ev.php', [
    'csrf_token' => $tEv, 'islem' => 'modul_sonuc', 'odev_id' => $aktifOdevId,
    'protokol' => 'ritim_okuma', 'skor' => 99, 'bpm' => 72, 'detay' => '{}',
], $evJar);
$tEv = csrf_al('ev.php', $evJar);
gonder('ev.php', [
    'csrf_token' => $tEv, 'islem' => 'modul_sonuc', 'odev_id' => $eskiOdevId,
    'protokol' => 'vurus_tutturma', 'skor' => 99, 'bpm' => 72, 'detay' => '{}',
], $evJar);
$evSonucSahte = (int)$pdo->query("SELECT COUNT(*) FROM protokol_sonuclari
    WHERE ogrenci_id = {$ogrenciId} AND kaynak = 'ev'")->fetchColumn();
$tEv = csrf_al('ev.php', $evJar);
gonder('ev.php', [
    'csrf_token' => $tEv, 'islem' => 'modul_sonuc', 'odev_id' => $aktifOdevId,
    'protokol' => 'vurus_tutturma', 'skor' => 88, 'bpm' => 72, 'detay' => '{}',
], $evJar);
$evSonucGecerli = (int)$pdo->query("SELECT COUNT(*) FROM protokol_sonuclari
    WHERE ogrenci_id = {$ogrenciId} AND kaynak = 'ev'")->fetchColumn();
dogrula($evSonucSahte === $evSonucOnce && $evSonucGecerli === $evSonucOnce + 1,
    'portal farklı modül türü ve süresi geçmiş ödev için sahte sonuç kaydetmiyor');
$pdo->exec("DELETE FROM protokol_sonuclari WHERE ogrenci_id = {$ogrenciId} AND kaynak = 'ev'");
$pdo->exec("DELETE FROM ev_odevleri WHERE id IN ({$aktifOdevId}, {$eskiOdevId})");

$t = csrf_al('ev-programi.php', $jar);
gonder('ev-programi.php', [
    'csrf_token' => $t, 'islem' => 'ata', 'hedef' => 'o' . $ogrenciId,
    'calisma_id' => $calismaId, 'baslangic' => '2026-08-10', 'bitis' => '2026-08-01',
    'hedef_gun' => 5, 'notlar' => '',
], $jar);
$tersOdev = (int)$pdo->query("SELECT COUNT(*) FROM ev_odevleri
    WHERE ogrenci_id = {$ogrenciId} AND baslangic = '2026-08-10' AND bitis = '2026-08-01'")->fetchColumn();
dogrula($tersOdev === 0, 'ödev bitiş tarihi başlangıçtan önceyse atama reddediliyor');
$pdo = null;

foreach ([[52, 1], [83, 1]] as [$skor, $std]) {
    $t = csrf_al('metronom.php', $jar);
    gonder('metronom.php', ['csrf_token' => $t, 'islem' => 'sonuc_kaydet', 'ogrenci_id' => $ogrenciId,
                            'protokol' => 'vurus_tutturma', 'skor' => $skor, 'bpm' => 72,
                            'detay' => '{}', 'standart' => $std], $jar);
}
$og = git_('ogrenci.php?id=' . $ogrenciId, $jar)['govde'];
dogrula(str_contains($og, '83') && str_contains($og, "\u{1F4CF}"), 'protokol sonuçları öğrenci sayfasında (📏 işaretli)');

/*
 * HTML BÜTÜNLÜĞÜ — açık kalan yorum sayfanın betiklerini öldürür.
 *
 * Gerçek olay: metronom.php'deki bir JS yorumuna, HTML yorumu AÇAN bir dizi
 * düz metin olarak yazılmıştı. Tarayıcı sayfanın kalanını yorum sandı ve
 * metronom.js dahil ALTI betik hiç yüklenmedi — ama sunucu 200 döndüğü,
 * beklenen dizeler de gövdede geçtiği için duman testi bunu göremiyordu.
 * Artık her sayfada yorum dengesi ve betik etiketlerinin sayfanın SON
 * bölümünde bozulmadan durduğu denetleniyor.
 */
bolum('HTML bütünlüğü (açık yorum / bozuk ayrıştırma)');
/* -------- Erişilebilirlik desenleri (WCAG 2.1.1 / 2.2.2 / 4.1.2) --------
   Bu bağlar kolay kopar: yeni sekme eklenirken aria-controls unutulur ve
   ekran okuyucu sekmeyi panelle ilişkilendiremez; hata sessizdir. */
$mg = git_('metronom.php', $jar)['govde'];
preg_match_all('/<button[^>]*role="tab"[^>]*>/i', $mg, $mt);
$sekmeSayisi = count($mt[0]);
$eksikBag = [];
foreach ($mt[0] as $etiket) {
    if (!preg_match('/aria-controls="([^"]+)"/', $etiket, $mc)
        || !preg_match('/\sid="([^"]+)"/', $etiket, $mi)
        || !preg_match('/tabindex="(0|-1)"/', $etiket)) { $eksikBag[] = $etiket; continue; }
    if (!preg_match('/<div[^>]*id="' . preg_quote($mc[1], '/') . '"[^>]*role="tabpanel"[^>]*aria-labelledby="'
        . preg_quote($mi[1], '/') . '"/i', $mg)) { $eksikBag[] = $mc[1]; }
}
dogrula($sekmeSayisi >= 5 && !$eksikBag,
    "sekmeler tabpanel'e bağlı (aria-controls + aria-labelledby + gezici tabindex)");
/* Gezici odak: şerit TEK Tab durağı olmalı, yoksa klavye kullanıcısı
   beş sekmeyi tek tek Tab'lamak zorunda kalır. */
dogrula(substr_count($mg, 'tabindex="0"') >= 1
     && preg_match_all('/role="tab"[^>]*tabindex="0"/', $mg) === 1, 'sekme şeridinde tek Tab durağı var');

$ig = git_('index.php', $jar)['govde'];
dogrula(str_contains($ig, 'id="tHareketAnahtari"') && str_contains($ig, 'aria-pressed'),
    'tanıtım sayfasında hareket denetimi var (WCAG 2.2.2)');
$lc = git_('assets/css/landing.css', $jar)['govde'];
dogrula(str_contains($lc, 'html.hareket-kapali *'), 'hareket-kapalı kuralı tüm animasyonları kapsıyor');
$lj = git_('assets/js/landing.js', $jar)['govde'];
dogrula(str_contains($lj, 'prefers-reduced-motion') && str_contains($lj, 'ritim-hareket-kapali'),
    'hareket tercihi işletim sistemi ayarını okuyor ve kalıcı');

$gj = git_('assets/js/grup-atolyesi.js', $jar)['govde'];
dogrula(str_contains($gj, "ArrowRight") && str_contains($gj, "'tabindex'"),
    'radyo grubunda ok tuşu gezinmesi ve gezici odak var');

/* Geri sayım her vuruşta değişir; assertive olsaydı ekran okuyucuyu
   saniyede bir keserdi (WCAG 4.1.3 amacına aykırı kullanım). */
$rj = git_('assets/js/ritim-okuma.js', $jar)['govde'];
/* Yalnız ÜRETİLEN etikete bak: 'assertive' sözcüğü açıklama satırında
   geçebilir ve düz arama boşuna kırmızı yakar. */
preg_match_all('/<div[^>]*class="ro-baslangic-sayaci"[^>]*>/', $rj, $mSay);
$sayacEtiket = $mSay[0][0] ?? '';
dogrula($sayacEtiket !== '' && !str_contains($sayacEtiket, 'assertive')
     && str_contains($sayacEtiket, 'aria-hidden="true"'),
    'geri sayım tik başına duyuru yapmıyor (assertive yerine aria-hidden)');

/* Nonce'a geçince gömülü blokların gerçekten nonce alması gerekir; almazsa
   sayfa sessizce ölür (CSP bloklar, sunucu yine 200 döner). */
$nonceli = 0; $noncesiz = [];
foreach (['metronom.php', 'plan.php'] as $sf) {
    $g = git_($sf, $jar)['govde'];
    foreach (['/<script(?![^>]*src=)[^>]*>/i'] as $rx) {
        preg_match_all($rx, $g, $mm);
        foreach ($mm[0] as $etiket) {
            if (str_contains($etiket, 'nonce=')) { $nonceli++; } else { $noncesiz[] = "{$sf}: {$etiket}"; }
        }
    }
}
dogrula($nonceli > 0 && !$noncesiz, 'gömülü <script> blokları nonce taşıyor'
    . ($noncesiz ? ' — eksik: ' . implode(', ', $noncesiz) : ''));

/* Satır içi on* öznitelikleri nonce ile ÇALIŞMAZ; delegasyona taşındıklarını
   doğrula, yoksa silme onayları sessizce kaybolur. */
$olayli = [];
foreach (['on-kayitlar.php', 'poliritim.php', 'ritim-okuma.php', 'site.php'] as $sf) {
    if (preg_match('/\son(click|submit|change|input)=/i', git_($sf, $jar)['govde'])) { $olayli[] = $sf; }
}
dogrula(!$olayli, 'satır içi on* olay özniteliği kalmadı' . ($olayli ? ' — ' . implode(', ', $olayli) : ''));

$htmlSayfalar = ['panel.php', 'metronom.php', 'poliritim.php', 'ritim-okuma.php',
                 'tini-kartlari.php', 'motor-studyo.php', 'grup-atolyesi.php',
                 'oturumlar.php', 'teknikler.php', 'raporlar.php', 'index.php', 'giris.php'];
$bozuk = [];
$betiksiz = [];
foreach ($htmlSayfalar as $sayfa) {
    $govde = git_($sayfa, $jar)['govde'];
    if ($govde === '') { continue; }
    $ac = substr_count($govde, '<!--');
    $kapa = substr_count($govde, '-->');
    if ($ac !== $kapa) { $bozuk[] = "{$sayfa} (açılan {$ac}, kapanan {$kapa})"; }
    /* Sayfanın kapanışından SONRA içerik olmamalı: açık yorum bunu üretir */
    $son = strrpos($govde, '</html>');
    if ($son !== false && trim(substr($govde, $son + 7)) !== '') {
        $bozuk[] = "{$sayfa} (</html> sonrası artık içerik)";
    }
    /* app.js her panel sayfasında yüklenir; yoksa ayrıştırma bozulmuş demektir */
    if ($sayfa !== 'index.php' && $sayfa !== 'giris.php'
        && !str_contains($govde, 'assets/js/app.js')) {
        $betiksiz[] = $sayfa;
    }
}
dogrula(!$bozuk, 'hiçbir sayfada açık kalan HTML yorumu yok', implode(' · ', $bozuk));
dogrula(!$betiksiz, 'panel sayfalarında betik etiketleri yerinde', implode(', ', $betiksiz));

bolum('Poliritim stüdyosu ve ölçüm varyantı (db v16)');
$t = csrf_al('poliritim.php', $jar);
/* İstemci başka bir protokol adı göndermeye çalışsa da sunucu poliritim'e sabitler */
gonder('poliritim.php', ['csrf_token' => $t, 'islem' => 'sonuc_kaydet', 'ogrenci_id' => $ogrenciId,
                         'protokol' => 'vurus_tutturma', 'varyant' => '3:2', 'skor' => 74,
                         'bpm' => 80, 'sd_ms' => 31, 'detay' => '{"oran":"3:2"}', 'standart' => 1], $jar);
$t = csrf_al('poliritim.php', $jar);
gonder('poliritim.php', ['csrf_token' => $t, 'islem' => 'sonuc_kaydet', 'ogrenci_id' => $ogrenciId,
                         'varyant' => '7:4', 'skor' => 22, 'bpm' => 80,
                         'sd_ms' => 96, 'detay' => '{"oran":"7:4"}', 'standart' => 1], $jar);
$pdo = new PDO('sqlite:' . $TEMP . '/ritim.sqlite');
$poliSatirlar = $pdo->query("SELECT protokol, varyant, skor FROM protokol_sonuclari
                              WHERE protokol = 'poliritim' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
dogrula(count($poliSatirlar) === 2
    && $poliSatirlar[0]['varyant'] === '3:2' && $poliSatirlar[1]['varyant'] === '7:4',
    'poliritim sonucu varyantıyla (oran) kaydediliyor',
    json_encode($poliSatirlar, JSON_UNESCAPED_UNICODE));
dogrula(count(array_filter($poliSatirlar, fn($r) => $r['protokol'] !== 'poliritim')) === 0,
    'istemcinin gönderdiği protokol adı yok sayılıyor (sunucuda sabit)');
$sahteVaryant = $pdo->query("SELECT COUNT(*) FROM protokol_sonuclari WHERE varyant LIKE '%<%'")->fetchColumn();
$pdo = null;
dogrula((int)$sahteVaryant === 0, 'varyant alanı süzülüyor');
$og = git_('ogrenci.php?id=' . $ogrenciId, $jar)['govde'];
dogrula(str_contains($og, 'Poliritim · 3:2') && str_contains($og, 'Poliritim · 7:4'),
    'öğrenci sayfasında 3:2 ve 7:4 AYRI seri olarak listeleniyor (karışmıyor)');
$poliSayfa = git_('poliritim.php', $jar)['govde'];
dogrula(str_contains($poliSayfa, 'poliritim-cekirdegi.js') && str_contains($poliSayfa, 'prSagPad')
    && str_contains($poliSayfa, 'prSolPad'),
    'poliritim sayfası çekirdeği ve iki el padini yüklüyor');
/* 'terapi' ve 'tanı' bilinçli olarak taranmaz: ikisi de yalnız üst menüden
   gelir (RitimTerapi markası ve "Tanıtım Sitesi" bağlantısı) ve CLAUDE.md §2
   uygulama içi adı açıkça meşru sayar. Taranan sözcükler kabukta hiç geçmez. */
$yasakliPoli = array_values(array_filter(
    ['tedavi', 'hasta', 'danışan', 'DEHB', 'semptom', 'iyileşme', 'dikkat eksikliği',
     'titreşim', 'rezonans', 'hücresel'],
    fn($k) => mb_stripos($poliSayfa, $k) !== false));
dogrula(!$yasakliPoli, 'poliritim sayfasında kilitli dil temiz (CLAUDE.md §2)', implode(', ', $yasakliPoli));
dogrula(str_contains($poliSayfa, 'veli raporuna yansıtılmaz'),
    'poliritim skorunun veli raporuna gitmediği sayfada yazılı');

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

/* -------- Marka: iki ad, iki kitle (CLAUDE.md §3) --------
   Panel içi ad "RitimTerapi" kalır; DIŞ yüzeylerin hiçbirinde geçmez.
   Bu ayrım kolay bozulur: yeni bir herkese açık sayfa APP_NAME kullanıverir. */
/* -------- Görünüm paneli (tema + video) --------
   WordPress benzeri: eğitmen panelden renk/animasyon/video yönetir.
   Buradaki zincir uçtan uca: kaydet → tanıtım sayfasına yansıdı mı. */
bolum('Görünüm paneli (tema + video)');
$t = csrf_al('site.php', $jar);
dogrula($t !== '', 'site.php CSRF jetonu alındı');

/* 1) Tema + sakin animasyon + YouTube videosu birlikte kaydedilir */
gonder('site.php', ['csrf_token' => $t, 'islem' => 'gorunum',
    'tema_vurgu' => '#22c55e', 'tema_ikincil' => '#38bdf8',
    'tema_animasyon' => 'sakin',
    'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'], $jar);
$g = git_('index.php', $jar)['govde'];
dogrula(str_contains($g, '--vurgu:34 197 94'), 'tema rengi tanıtım sayfasına yansıdı (#22c55e → 34 197 94)');
dogrula(str_contains($g, 'class="tema-sakin"'), 'sakin animasyon kipi <html> sınıfına yansıdı');
dogrula(str_contains($g, 'data-embed="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'),
    'YouTube bağlantısı çerezsiz gömme kapağına dönüştü');
dogrula(str_contains($g, '#video') && str_contains($g, 'Tanıtım'), 'video bölümü ve menü bağlantısı görünür');
/* '<iframe' aranır, 'iframe' değil: sözcük sayfadaki açıklamada geçiyor
   ve düz arama boşuna kırmızı yakıyordu. */
dogrula(!str_contains($g, '<iframe'), 'iframe etiketi SAYFADA YOK — yalnız tıklanınca kurulur');

/* 2) Geçersiz bağlantı: kaydedilmez, eski video kalır, açıkça söylenir */
$t = csrf_al('site.php', $jar);
$y = gonder('site.php', ['csrf_token' => $t, 'islem' => 'gorunum',
    'tema_vurgu' => '#22c55e', 'tema_ikincil' => '#38bdf8', 'tema_animasyon' => 'sakin',
    'video_url' => 'javascript:alert(1)'], $jar);
$panel = git_('site.php', $jar)['govde'];
dogrula(str_contains($panel, 'video bağlantısı'), 'geçersiz video bağlantısı açıkça reddedildi');
dogrula(str_contains(git_('index.php', $jar)['govde'], 'youtube-nocookie.com/embed/dQw4w9WgXcQ'),
    'geçersiz denemede eski video korundu');

/* 3) Hazır tema düğmesi + kapalı animasyon */
$t = csrf_al('site.php', $jar);
gonder('site.php', ['csrf_token' => $t, 'islem' => 'gorunum', 'hazir' => 'okyanus'], $jar);
dogrula(str_contains(git_('index.php', $jar)['govde'], '--vurgu:56 189 248'),
    'hazır tema (Okyanus) tek düğmeyle uygulandı');

/* 4) Varsayılana dönüş: stil bloğu KAYBOLMALI (fazladan CSS taşınmaz),
      video boşalınca bölüm ve menü bağlantısı kaybolmalı */
$t = csrf_al('site.php', $jar);
gonder('site.php', ['csrf_token' => $t, 'islem' => 'gorunum',
    'tema_vurgu' => '#f59e0b', 'tema_ikincil' => '#7c3aed',
    'tema_animasyon' => 'tam', 'video_url' => ''], $jar);
$g = git_('index.php', $jar)['govde'];
dogrula(!str_contains($g, '--vurgu:'), 'varsayılan temada stil bloğu hiç basılmıyor');
dogrula(!str_contains($g, 'id="video"') && !str_contains($g, 'data-embed'),
    'video bağlantısı silinince bölüm kayboldu');
dogrula(!preg_match('/<html[^>]*class=/', $g), 'tam animasyonda <html> sınıfsız');

/* 5) KONTRAST KORUMASI — renk seçici serbest, zemin koyu (#0c0a09).
      Çok koyu bir ana renk marka yazısını ve dolu düğmeleri okunamaz yapardı
      (#000000 → türevler #141414). Sessizce kabul etmek yerine açılır. */
$t = csrf_al('site.php', $jar);
gonder('site.php', ['csrf_token' => $t, 'islem' => 'gorunum',
    'tema_vurgu' => '#000000', 'tema_ikincil' => '#7c3aed',
    'tema_animasyon' => 'tam', 'video_url' => ''], $jar);
$panel = git_('site.php', $jar)['govde'];
dogrula(str_contains($panel, 'koyu zeminde okunmuyordu'),
    'okunmaz renk düzeltildi ve kullanıcıya bildirildi');
preg_match('/--vurgu:(\d+) (\d+) (\d+)/', git_('index.php', $jar)['govde'], $mv);
$parlaklik = $mv ? (0.2126 * (int)$mv[1] + 0.7152 * (int)$mv[2] + 0.0722 * (int)$mv[3]) : 0;
dogrula($parlaklik > 90, 'kaydedilen renk gerçekten açıldı (siyah kalmadı)',
    'parlaklık: ' . round($parlaklik));

/* 6) Tema TÜM dış yüzeylere işlemeli — mesajda öyle deniyor.
      Denetimde giris.php ve ev.css amber'ı sabit taşıyordu. */
$cssJar2 = [];
$evC = git_('assets/css/ev.css', $cssJar2)['govde'];
dogrula(!preg_match('/#fbbf24|#f59e0b|#d97706|rgba\(245, ?158, ?11/', $evC),
    'katılımcı portalı CSS\'i sabit marka rengi taşımıyor');
$grsC = git_('giris.php', $cssJar2)['govde'];
dogrula(!preg_match('/#fbbf24|#f59e0b|#d97706|rgba\(245, ?158, ?11/', $grsC),
    'giriş sayfası sabit marka rengi taşımıyor');

/* 7) .htaccess: <Directory> yönergesi .htaccess bağlamında YASAK — Apache
      "Directory not allowed here" der ve site TÜMÜYLE 500 döner. */
$ht = @file_get_contents(dirname(__DIR__) . '/.htaccess');
dogrula($ht !== false && !preg_match('/^\s*<Directory/mi', $ht),
    'kök .htaccess Apache\'yi kıracak <Directory> yönergesi içermiyor');
dogrula(is_file(dirname(__DIR__) . '/assets/img/galeri/.htaccess'),
    'galeri klasöründe yüklenen dosyayı çalıştırmayı engelleyen .htaccess var');

/* 8) Video metinleri PANELDEN düzenlenebilmeli — index.php okuyordu ama
      site.php'de alan yoktu, yani kaydetmenin hiçbir yolu yoktu. */
$t = csrf_al('site.php', $jar);
$panel = git_('site.php', $jar)['govde'];
dogrula(str_contains($panel, 'metin[video_baslik]') && str_contains($panel, 'metin[video_ustbaslik]'),
    'video bölümü metinleri panelde düzenlenebiliyor');

/* 9) Önbellek güvencesi php.ini'ye BAĞIMLI OLMAMALI. PHP'nin oturum modülü
      varsayılanda no-store yolluyor, ama session.cache_limiter sunucuda
      'public' yapılabilir; o durumda ortak bir vekil öğrenci adlarını ve
      veli notlarını başka ziyaretçiye servis edebilirdi. 'private' yalnız
      bizim açık başlığımızdan gelir — onu arıyoruz. */
dogrula(str_contains(git_('ogrenciler.php', $jar)['basliklar'], 'private'),
    'kimlikli sayfada açık Cache-Control (private) — ayara bağımlı değil');

/* 10) Ön kayıt hız sınırı SUNUCUDA: çerez atan bot sınırsız kişisel veri
       satırı yazabiliyordu (tablo ad + telefon/e-posta tutuyor). */
$mCode = @file_get_contents(dirname(__DIR__) . '/includes/model.php');
dogrula($mCode !== false && str_contains($mCode, "hiz_siniri_dene('onkayit'")
     && !str_contains($mCode, 'on_kayit_gecmis'),
    'ön kayıt hız sınırı oturumdan veritabanına taşındı');

/* 11) Uzantı kara listeleri AYRIŞMAMALI: router (php -S) .md engelliyordu
       ama Apache engellemiyordu → XAMPP yayınında CLAUDE.md herkese açıktı. */
$htx = @file_get_contents(dirname(__DIR__) . '/.htaccess');
dogrula($htx !== false && str_contains($htx, '|md|yml|yaml'),
    '.htaccess uzantı listesi router.php ile aynı (.md sızmıyor)');

/* 12) Yeni güvenlik başlıkları */
$anonJar2 = [];
$bas = git_('giris.php', $anonJar2)['basliklar'];
dogrula(str_contains($bas, 'frame-src') && str_contains($bas, 'youtube-nocookie.com'),
    'CSP frame-src yalnız tanınan oynatıcılara açık');
dogrula(str_contains($bas, 'Cross-Origin-Opener-Policy: same-origin'),
    'COOP başlığı gönderiliyor');

/* -------- Mobil düzen gerilemeleri --------
   Yerleşim HTTP düzeyinde ölçülemez; onun yerine yerleşimi BOZAN kuralların
   varlığı denetlenir. Süs halkası kabın dışına taşacak biçimde konumlanır;
   kırpma kuralı silinirse telefonda sayfa yatay kayar (390px görünümde
   belge 490px ölçülmüştü). */
bolum('Mobil düzen');
$cssJar = [];
$evCss = git_('assets/css/ev.css', $cssJar)['govde'];
dogrula((bool)preg_match('/\.ev-giris\s*\{[^}]*overflow:\s*hidden/', $evCss),
    'katılımcı giriş ekranı süs halkasını kırpıyor (yatay kayma yok)');
$lCss = git_('assets/css/landing.css', $cssJar)['govde'];
dogrula(str_contains($lCss, '@media (max-width: 720px)') && str_contains($lCss, '.t-nav-linkler'),
    'telefonda gezinme bağlantıları gizlenmiyor, şeride iniyor');
$mCss = git_('assets/css/metronom.css', $cssJar)['govde'];
dogrula((bool)preg_match('/\.m-sekmeler\s*\{[^}]*overflow-x:\s*auto/', $mCss),
    'metronom sekme şeridi telefonda kaydırılabilir');

/* Dokunma kuralları TABLETİ de kapsamalı. İlk sürüm 760px'te bitiyordu ve
   768px'lik tablette hiçbiri uygulanmıyordu — ölçümle bulundu (tablette 62
   denetim 44px altında kalıyordu). */
foreach ([['assets/css/app.css', $lCss = null], ['assets/css/metronom.css', null]] as [$sf, $_]) {
    $c = git_($sf, $cssJar)['govde'];
    dogrula(str_contains($c, '@media (max-width: 1024px)'),
        basename($sf) . ' dokunma kuralları tableti de kapsıyor');
}
$aCss = git_('assets/css/app.css', $cssJar)['govde'];
/* Girdilerde 16px: iOS Safari altındaki değerlerde odaklanınca sayfayı
   zorla yakınlaştırır ve kullanıcı elle geri uzaklaştırmak zorunda kalır. */
dogrula((bool)preg_match('/font-size:\s*16px/', $aCss),
    'telefonda girdi yazı boyu 16px (iOS zorla yakınlaştırma yok)');
/* WCAG 2.5.8 AA = 24x24. Onay kutuları yerelde 13x13 gelir. */
dogrula((bool)preg_match('/input\[type="checkbox"\][^{]*\{[^}]*(width|min-width):\s*2[4-9]px/s', $aCss),
    'onay kutuları dokunmatikte en az 24px');

/* Kayan şeridin scale(1.01)\'i belgeyi görünümden geniş yapıyor ve sayfa
   HER genişlikte yana kayıyordu (1280px\'te 7px ölçüldü). */
$lCssTam = git_('assets/css/landing.css', $cssJar)['govde'];
dogrula(!str_contains($lCssTam, 'rotate(-.6deg) scale('),
    'kayan şerit sayfayı yana kaydırmıyor (scale kaldırıldı)');

/* Dokunmatikte İÇERİK KAYBI: program evre kartlarının açıklaması yalnız
   :hover ile açılıyordu; telefonda/tablette 277 karakter hiç görünmüyordu
   (ölçüldü: max-height 0, opacity 0, yükseklik 0). */
dogrula(str_contains($lCssTam, '@media (hover: none)') && str_contains($lCssTam, '.t-evre-detay'),
    'hover olmayan cihazda program açıklamaları görünür');

/* .gorsel-gizli yalnız app.css'te tanımlıydı; ev.php ve index.php onu
   YÜKLEMEZ, yani ekran okuyucuya özel metinler gözle görünür basılıyordu
   ("— yapılmadı, bugün" 30px'lik gün yuvarlağının içine taşıyordu). */
dogrula((bool)preg_match('/\.gorsel-gizli\s*\{[^}]*clip-path/s', $lCssTam),
    'ekran okuyucu metni herkese açık sayfalarda da gizli (landing.css)');

/* Koyu sahnede koyu mürekkep: BPM oyunu kartında başlık ve etiketler
   rgb(28,25,23) idi — parlaklık 25, okunmuyordu. */
$mCssTam = git_('assets/css/metronom.css', $cssJar)['govde'];
dogrula(str_contains($mCssTam, '.oyun-kart h3') || str_contains($mCssTam, '.m-sahne h3'),
    'koyu sahnedeki başlık ve etiketler açık renge çekildi');

bolum('Marka ayrımı (panel içi / herkese açık)');
/* ÇIKIŞLI çerezle bakılır: girişli oturumda giris.php panele yönlenir ve
   denetim panelin HTML'ini ölçer — kontrol boşa çıkar. */
$anonJar = [];
$sizan = [];
foreach (['index.php', 'giris.php', 'manifest.json', 'offline.html'] as $sf) {
    $g = git_($sf, $anonJar);
    if ($g['durum'] !== 200 || $g['govde'] === '') { $sizan[] = $sf . ' (durum ' . $g['durum'] . ')'; continue; }
    if (str_contains($g['govde'], 'RitimTerapi')) { $sizan[] = $sf; }
}
dogrula(!$sizan, 'herkese açık yüzeyde "RitimTerapi" geçmiyor'
    . ($sizan ? ' — ' . implode(', ', $sizan) : ''));
dogrula(str_contains(git_('index.php', $jar)['govde'], 'Ritim Atölyesi'),
    'tanıtım sayfasında marka Ritim Atölyesi');
dogrula(str_contains(git_('manifest.json', $jar)['govde'], 'Ritim Atölyesi'),
    'PWA manifest markası Ritim Atölyesi');
/* Panel içi ad korunmalı — toplu değiştirmeyle her yerden silinmiş olmasın */
dogrula(str_contains(git_('panel.php', $jar)['govde'], 'RitimTerapi'),
    'panel içi ad RitimTerapi olarak duruyor');

/* -------- Kurulabilir uygulama (PWA) --------
   Üç kitle var (eğitmen → panel, katılımcı → ev, ziyaretçi → tanıtım) ama
   tek manifest. start_url panel.php iken katılımcı kurunca giriş kapısına
   düşüyordu; şerit de yalnız panelde vardı, yani dışarıdan kurulamıyordu. */
bolum('Kurulabilir uygulama');
$mf = git_('manifest.json', $anonJar);
$mj = json_decode($mf['govde'], true);
dogrula($mf['durum'] === 200 && is_array($mj), 'manifest.json geçerli JSON');
dogrula(($mj['start_url'] ?? '') === './index.php',
    'start_url herkese açık sayfa (giriş kapısı değil)', (string)($mj['start_url'] ?? '—'));
dogrula(($mj['display'] ?? '') === 'standalone' && !empty($mj['icons']),
    'manifest standalone ve ikonlu');
/* İkon 404 verirse tarayıcı kurulumu hiç önermez — sessiz bir arıza. */
$ikonKotu = [];
foreach ($mj['icons'] ?? [] as $ic) {
    if (git_((string)$ic['src'], $anonJar)['durum'] !== 200) { $ikonKotu[] = $ic['src']; }
}
dogrula(!$ikonKotu, 'manifest ikonları sunuluyor', implode(', ', $ikonKotu));
$kisayolUrl = array_column($mj['shortcuts'] ?? [], 'url');
dogrula(in_array('./ev.php', $kisayolUrl, true) && in_array('./panel.php', $kisayolUrl, true),
    'kısayollarda hem panel hem katılımcı portalı var');

/* Şerit ve betik herkese açık ÜÇ yüzeyde de olmalı */
$eksikSerit = $eksikManifest = [];
foreach (['index.php', 'giris.php', 'ev.php'] as $sf) {
    $g = git_($sf, $anonJar)['govde'];
    if (!str_contains($g, 'id="uygulamaSerit"') || !str_contains($g, 'uygulama-yukle.js')) {
        $eksikSerit[] = $sf;
    }
    if (!str_contains($g, 'rel="manifest"')) { $eksikManifest[] = $sf; }
}
dogrula(!$eksikSerit, 'kurulum şeridi herkese açık yüzeylerde', implode(', ', $eksikSerit));
dogrula(!$eksikManifest, 'manifest herkese açık yüzeylere bağlı', implode(', ', $eksikManifest));
/* Şerit varsayılan GİZLİ gelmeli: kararı JS verir (kurulu mu, tarayıcı
   destekliyor mu). Açık gelirse JS kapalı kullanıcı çalışmayan düğme görür. */
dogrula((bool)preg_match('/id="uygulamaSerit"[^>]*\shidden/', git_('index.php', $anonJar)['govde']),
    'şerit varsayılan gizli (görünürlüğe JS karar verir)');
dogrula(str_contains(git_('index.php', $anonJar)['govde'], 'ev.php'),
    'tanıtım sayfasında katılımcı portalı bağlantısı var');
dogrula(str_contains(git_('assets/js/uygulama-yukle.js', $anonJar)['govde'], 'beforeinstallprompt')
     && str_contains(git_('assets/js/uygulama-yukle.js', $anonJar)['govde'], 'Ana Ekrana Ekle'),
    'kurulum betiği hem Android hem iOS yolunu içeriyor');

bolum('Ön kayıt (herkese açık form)');
$halkJar = [];                              // giriş yapmamış ziyaretçi
$t = csrf_al('index.php', $halkJar);
dogrula($t !== '', 'tanıtım sayfasında CSRF alanı var');
// CSRF'siz gönderim kayıt oluşturmamalı
gonder('index.php', ['islem' => 'on_kayit', 'ad' => 'CSRFSIZ-TALEP', 'iletisim' => '0555 000 00 00'], $halkJar);
$y = gonder('index.php', ['islem' => 'on_kayit', 'csrf_token' => $t,
    'ad' => 'Duman Talep', 'iletisim' => 'duman@example.test', 'kitle' => 'cocuk',
    'ders_turu' => 'ozel', 'mesaj' => 'duman testi', 'profil' => 'eşik 20 Hz'], $halkJar);
dogrula($y['durum'] >= 300 && $y['durum'] < 400, 'geçerli talep kabul edildi (yönlendirme)', 'durum ' . $y['durum']);
// Bal küpü dolu → kayıt OLUŞMAMALI
$t = csrf_al('index.php', $halkJar);
gonder('index.php', ['islem' => 'on_kayit', 'csrf_token' => $t, 'ad' => 'Bot Talep',
    'iletisim' => 'bot@example.test', 'website' => 'http://spam.example'], $halkJar);
// Kısa ad reddedilmeli
$t = csrf_al('index.php', $halkJar);
gonder('index.php', ['islem' => 'on_kayit', 'csrf_token' => $t, 'ad' => 'X', 'iletisim' => 'kisa@example.test'], $halkJar);

$liste = git_('on-kayitlar.php', $jar)['govde'];   // eğitmen oturumuyla
dogrula(str_contains($liste, 'Duman Talep'), 'talep panelde görünüyor');
dogrula(!str_contains($liste, 'CSRFSIZ-TALEP'), 'CSRF jetonu olmadan talep oluşmuyor');
dogrula(!str_contains($liste, 'Bot Talep'), 'bal küpü dolu gönderim kaydedilmiyor');
dogrula(!str_contains($liste, 'kisa@example.test'), 'geçersiz ad reddediliyor');
dogrula(str_contains($liste, 'Ders tercihi: Özel ders'), 'grup/özel ders tercihi talebe eklendi');
dogrula(str_contains($liste, 'eşik 20 Hz'), 'ritim profili talebe eklendi');
dogrula(str_contains(git_('panel.php', $jar)['govde'], 'yeni iletişim talebi'), 'panelde yeni talep uyarısı var');
// Ziyaretçi iletişim talepleri listesini GÖREMEMELİ
$y = git_('on-kayitlar.php', $halkJar);
dogrula($y['durum'] >= 300 && str_contains($y['yer'], 'giris.php'), 'iletişim talepleri listesi girişe kapalı');
// Temizlik: kişisel veri bırakmayalım
preg_match('/name="id" value="(\d+)"/', $liste, $m);
if (!empty($m[1])) {
    $t = csrf_al('on-kayitlar.php', $jar);
    gonder('on-kayitlar.php', ['csrf_token' => $t, 'islem' => 'sil', 'id' => $m[1]], $jar);
    dogrula(!str_contains(git_('on-kayitlar.php', $jar)['govde'], 'Duman Talep'), 'talep silinebiliyor');
}

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

/* -------- GERİ YÜKLEME: TAM TUR --------
   İndirme test ediliyordu ama GERİ GETİRME hiç sınanmamıştı — yedeklemenin
   asıl işe yarayan yarısı o. Buradaki tur gerçek: sunucudaki günlük yedek
   (test verisi oluşmadan ÖNCE alınmıştır) geri yüklenir, test öğrencisinin
   KAYBOLMASI beklenir; ardından geri yüklemenin kendi emniyet kopyası
   (oncesi-*) yüklenip öğrenci GERİ GELMELİ. Böylece hem "gerçekten değişti"
   hem "geri alınabiliyor" kanıtlanır ve veritabanı testin bulduğu hâle döner. */
$otoYedekler = glob($TEMP . '/yedek/otomatik-*.sqlite') ?: [];
$otoAd = $otoYedekler ? basename($otoYedekler[0]) : '';
dogrula($otoAd !== '', 'sunucuda geri yüklenebilir günlük yedek var');

if ($otoAd !== '') {
    /* Onay kutusu işaretlenmeden geri yükleme OLMAMALI */
    $t = csrf_al('yedek.php', $jar);
    gonder('yedek.php', ['csrf_token' => $t, 'islem' => 'geri_yukle_oto', 'dosya' => $otoAd], $jar);
    $pk = new PDO('sqlite:' . $TEMP . '/ritim.sqlite');
    dogrula((int)$pk->query("SELECT COUNT(*) FROM ogrenciler WHERE kod='DUMAN-1'")->fetchColumn() === 1,
        'onaysız geri yükleme reddedildi (veri duruyor)');
    $pk = null;

    /* Yol gezinmesi: yedek dizini dışına çıkılamamalı */
    $t = csrf_al('yedek.php', $jar);
    gonder('yedek.php', ['csrf_token' => $t, 'islem' => 'geri_yukle_oto',
                         'dosya' => '../gizli.php', 'onay' => '1'], $jar);
    dogrula(is_file($TEMP . '/ritim.sqlite')
        && strncmp((string)@file_get_contents($TEMP . '/ritim.sqlite', false, null, 0, 15), 'SQLite format 3', 15) === 0,
        'geri yüklemede yol gezinmesi engellendi (veritabanı bozulmadı)');

    /* Gerçek geri yükleme: test öncesi duruma dön */
    $t = csrf_al('yedek.php', $jar);
    gonder('yedek.php', ['csrf_token' => $t, 'islem' => 'geri_yukle_oto',
                         'dosya' => $otoAd, 'onay' => '1'], $jar);
    $pk = new PDO('sqlite:' . $TEMP . '/ritim.sqlite');
    $kaldiMi = (int)$pk->query("SELECT COUNT(*) FROM ogrenciler WHERE kod='DUMAN-1'")->fetchColumn();
    $pk = null;
    dogrula($kaldiMi === 0, 'geri yükleme veritabanını GERÇEKTEN değiştirdi (test verisi gitti)');

    /* Geri yükleme kendi emniyet kopyasını almış olmalı */
    $emniyetler = glob($TEMP . '/yedek/oncesi-*.sqlite') ?: [];
    dogrula((bool)$emniyetler, 'geri yüklemeden önce emniyet kopyası alındı');

    /* Emniyet kopyasından dönülebiliyor mu — yanlış yedeği yükleyen eğitmen
       kilitlenmemeli. Bu adım aynı zamanda testin verisini geri getirir. */
    if ($emniyetler) {
        usort($emniyetler, fn($a, $b) => filemtime($b) <=> filemtime($a));
        $t = csrf_al('yedek.php', $jar);
        gonder('yedek.php', ['csrf_token' => $t, 'islem' => 'geri_yukle_oto',
                             'dosya' => basename($emniyetler[0]), 'onay' => '1'], $jar);
        $pk = new PDO('sqlite:' . $TEMP . '/ritim.sqlite');
        $geldiMi = (int)$pk->query("SELECT COUNT(*) FROM ogrenciler WHERE kod='DUMAN-1'")->fetchColumn();
        $pk = null;
        dogrula($geldiMi === 1, 'emniyet kopyasından geri dönüldü (yanlış yedek kurtarılabiliyor)');
    }

    /* Uygulama geri yüklemeden sonra çalışır durumda mı */
    dogrula(git_('ogrenciler.php', $jar)['durum'] === 200
         && str_contains(git_('panel.php', $jar)['govde'], 'RitimTerapi'),
        'geri yükleme sonrası uygulama açılıyor');
}

bolum('Hatalı girdi dayanıklılığı');
$y = git_('ogrenci.php?id=abc', $jar);
dogrula($y['durum'] === 404 && !str_contains($y['govde'], 'SQLSTATE'), 'bozuk id → temiz 404 (SQL hatası sızmıyor)');
$y = git_('sertifika.php?ogrenci_id=999999', $jar);
dogrula($y['durum'] === 404, 'olmayan kayıt → 404');

bolum('Temizlik (silme + basamaklı silme)');
$t = csrf_al('ogrenciler.php', $jar);
$y = gonder('ogrenci-sil.php', ['csrf_token' => $t, 'id' => $arkadasId], $jar);
$t = csrf_al('gruplar.php', $jar);
gonder('grup-sil.php', ['csrf_token' => $t, 'id' => $ozelGrupId], $jar);
$t = csrf_al('gruplar.php', $jar);
gonder('grup-sil.php', ['csrf_token' => $t, 'id' => $grup2Id], $jar);
$t = csrf_al('gruplar.php', $jar);
gonder('grup-sil.php', ['csrf_token' => $t, 'id' => $cakisanGrupId], $jar);
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
