<?php
/**
 * Ortak başlangıç: her sayfanın ilk require'ı.
 * Kullanım: define('RITIM', 1); require __DIR__ . '/includes/bootstrap.php';
 *
 * Tek kullanıcılı, yerelde çalışan eğitim aracı. Kimlik doğrulama yok (Faz 1
 * kararı); CSRF ve temel güvenlik başlıkları yine de uygulanır.
 */
if (!defined('RITIM')) { http_response_code(403); exit; }

date_default_timezone_set('Europe/Istanbul');
mb_internal_encoding('UTF-8');

define('APP_DIR', dirname(__DIR__));
/*
 * Depolama dizini. RITIM_STORAGE ortam değişkeni yalnız SÜRECİ BAŞLATAN
 * tarafından ayarlanabilir (HTTP ile değiştirilemez); duman testi bununla
 * kendini izole eder ve gerçek veriye asla dokunmaz.
 */
$ozelDepo = getenv('RITIM_STORAGE');
define('STORAGE_DIR', is_string($ozelDepo) && $ozelDepo !== ''
    ? rtrim($ozelDepo, '/\\')
    : APP_DIR . '/storage');
/*
 * İKİ AD, İKİ KİTLE — karıştırılmamalı:
 *
 *  APP_NAME     yalnız EĞİTMENİN gördüğü panelde geçer (sekme başlığı, üst
 *               menü markası). Yereldir; klasör adı gibi.
 *  PUBLIC_BRAND DIŞARIYA açılan her yüzeyde geçer: tanıtım sitesi, giriş
 *               sayfası, PWA manifest'i, telefon ana ekranı adı, veli
 *               belgeleri, katılımcı portalı.
 *
 * Gerekçe (CLAUDE.md §2): uygulama bir eğitim aracı, sağlık ürünü değil.
 * Arama motorunda ya da telefonun ana ekranında "terapi" adıyla görünmek
 * sunulmayan bir hizmeti çağrıştırır. Site internete açıldığı için bu ayrım
 * artık yalnız belgelerde değil, her dış yüzeyde geçerli.
 */
define('APP_NAME', 'RitimTerapi');
define('PUBLIC_BRAND', 'Ritim Atölyesi');
/* Geriye dönük ad: veli belgelerinde bu sabit kullanılıyordu, aynı değer. */
define('REPORT_BRAND', PUBLIC_BRAND);

/**
 * DAĞITIM KİPİ — uygulama nerede çalışıyor?
 *
 * 'yerel'   : eğitmenin kendi makinesi / atölye Wi-Fi'ı (start.bat)
 * 'yayin'   : internete açık sunucu
 *
 * storage/gizli.php içinde DAGITIM sabitiyle belirlenir. Tanımlı değilse
 * GÜVENLİ TARAF seçilir: 'yayin'. Yani bir kurulum yapılandırılmayı unutursa
 * kolaylıklar kapalı kalır, açık kalmaz.
 */
function dagitim_kipi(): string
{
    return (defined('DAGITIM') && DAGITIM === 'yerel') ? 'yerel' : 'yayin';
}

/**
 * İstek eğitmenin KENDİ makinesinden mi geliyor?
 *
 * !!! REMOTE_ADDR'E TEK BAŞINA GÜVENİLMEZ !!!
 * Uygulama yayında tipik olarak nginx/Apache ters vekilinin arkasında
 * PHP-FPM ile çalışır ve orada REMOTE_ADDR HER İSTEK İÇİN 127.0.0.1'dir.
 * Yalnız IP'ye bakan bir "yerel mi?" denetimi, yayına alındığı anda
 * şifresiz tek-tık girişi TÜM İNTERNETE açardı.
 *
 * Bu yüzden iki koşul birlikte aranır:
 *   1) dağıtım kipi açıkça 'yerel' olarak yapılandırılmış olacak, VE
 *   2) isteğin kaynağı gerçekten döngü arayüzü olacak.
 * X-Forwarded-For gibi istemcinin uydurabileceği başlıklara HİÇ bakılmaz.
 */
function yerel_istek_mi(): bool
{
    if (PHP_SAPI === 'cli') { return true; }
    if (dagitim_kipi() !== 'yerel') { return false; }   // yayında asla
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return $ip === '127.0.0.1' || $ip === '::1' || $ip === '';
}

/** İstek HTTPS üzerinden mi geldi? (ters vekil başlığı yalnız yerelden kabul) */
function guvenli_baglanti_mi(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') { return true; }
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) { return true; }
    /* Ters vekil başlığına YALNIZ döngü arayüzünden gelen istekte güvenilir;
       aksi hâlde istemci başlığı uydurup Secure çerezi düşürebilir. */
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip === '127.0.0.1' || $ip === '::1') {
        $p = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($p === 'https') { return true; }
    }
    return false;
}

/**
 * CSP nonce — istek başına bir kez üretilir.
 *
 * script-src'den 'unsafe-inline' kaldırıldı: o bayrak açıkken saklı bir XSS
 * yükü sayfaya girer girmez ÇALIŞIR ve CSP hiçbir şey engellemez. Nonce ile
 * yalnız sunucunun kendi bastığı <script nonce="…"> blokları çalışır;
 * saldırganın enjekte ettiği blok doğru nonce'u bilemez (her istekte değişir).
 *
 * Sayfalarda kullanımı: <script <?= csp_nonce_attr() ?>> … </script>
 */
function csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) { $nonce = base64_encode(random_bytes(16)); }
    return $nonce;
}

/** Gömülü <script> için hazır öznitelik. */
function csp_nonce_attr(): string
{
    return 'nonce="' . htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8') . '"';
}

error_reporting(E_ALL);
// Hatalar ekrana YALNIZ yerelde basılır; ağdan gelen istekte yalnız günlüğe.
@ini_set('display_errors', yerel_istek_mi() ? '1' : '0');
@ini_set('log_errors', '1');

if (!headers_sent()) {
    header_remove('X-Powered-By');          // PHP sürümünü duyurma
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    /* frame-ancestors, X-Frame-Options'ın yerini alır (CSP2+); ikisi de yollanır
       çünkü eski tarayıcılar yalnız başlığı anlar. */
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: geolocation=(), camera=(), payment=(), usb=(), interest-cohort=()');
    /* script-src: 'unsafe-inline' YOK — gömülü bloklar nonce ile çalışır,
       satır içi on* öznitelikleri app.js'te delegasyona taşındı.
       style-src'de 'unsafe-inline' KALIYOR: sayfalarda ~140 satır içi style
       özniteliği var ve stil enjeksiyonu betik çalıştıramaz; asıl tehlike olan
       kod yürütmesi kapatıldı. Bunu da kapatmak stil taşıma işi ister. */
    /* frame-src: tanıtım videosu gömme için YALNIZ iki kaynak — YouTube'un
       çerezsiz alanı ve Vimeo oynatıcısı. video_embed_bilgisi() zaten yalnız
       bu ikisini üretir; CSP ikinci kilittir (biri delinirse öbürü tutar). */
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'nonce-" . csp_nonce() . "'; "
        . "style-src 'self' 'unsafe-inline'; img-src 'self' data:; object-src 'none'; "
        . "frame-src 'self' https://www.youtube-nocookie.com https://player.vimeo.com; "
        . "base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    /* Pencere referansı ve kaynak paylaşımı: başka origin bizim pencereye
       window.opener ile dokunamasın, kaynaklarımızı gömemesin. */
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    /* Önbellek: sayfalar tarayıcıda ya da paylaşılan vekilde KALMAMALI.
       PHP'nin oturum modülü zaten benzerini yolluyor (session.cache_limiter
       varsayılanı 'nocache') — ama o bir php.ini ayarı ve sunucuda 'public'
       yapılmış olabilir. Garantiyi ayara bırakmıyoruz; 'private' de ekleniyor
       ki ortak vekil bir eğitmenin sayfasını başkasına servis etmesin. */
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    /* HSTS yalnız gerçekten HTTPS'teyken: HTTP üzerinden yollamak anlamsız,
       yanlış kurulumda siteyi erişilemez kılabilir. */
    if (guvenli_baglanti_mi()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $guvenli = guvenli_baglanti_mi();
    session_name($guvenli ? '__Host-RITIMSESS' : 'RITIMSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $guvenli,      // HTTPS'te çerez düz metin bağlantıya gitmez
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    /* Oturum modülü kendi Cache-Control'ünü basar ve YUKARIDA kurduğumuz
       başlığın ÜZERİNE YAZAR (ölçüldü: 'private' kayboluyordu). Kendi
       sınırlayıcısı kapatılır, garanti bizim açık başlığımızda kalır. */
    session_cache_limiter('');
    session_start();
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
    }

    /*
     * OTURUM ÖMRÜ — iki ayrı sınır:
     *  • boşta kalma: 2 saat işlem yoksa düşer (paylaşılan cihaz riski)
     *  • mutlak    : 12 saat sonra her hâlükârda düşer (çalınan çerez süresiz olmasın)
     */
    $simdi = time();
    $bosta = 2 * 3600;
    $mutlak = 12 * 3600;
    $son = (int)($_SESSION['_son_islem'] ?? 0);
    $bas = (int)($_SESSION['_oturum_basi'] ?? 0);
    if (($son && $simdi - $son > $bosta) || ($bas && $simdi - $bas > $mutlak)) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    if (empty($_SESSION['_oturum_basi'])) { $_SESSION['_oturum_basi'] = $simdi; }
    $_SESSION['_son_islem'] = $simdi;
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/seed.php';
require __DIR__ . '/model.php';

/*
 * Eğitmen giriş hesapları — storage/gizli.php.
 *
 * ŞİFRELER ARTIK ÖZETLENMİŞ (password_hash) SAKLANIR. Önceden düz metindi;
 * yerelde tek kullanıcılı bir araç için savunulabilirdi, internete açılan bir
 * kurulumda değil — dosyayı okuyabilen (yedek, günlük, hatalı yapılandırma)
 * doğrudan şifreyi ele geçiriyordu.
 *
 * Eski kurulumlar kırılmaz: düz metin bir değer görülürse ilk açılışta
 * kendiliğinden özete çevrilir ve dosya yeniden yazılır (bkz. gizli_dosyayi_tasi).
 */
$gizliDosya = STORAGE_DIR . '/gizli.php';

/** Değer bir password_hash çıktısı mı? ($2y$, $argon2 …) */
function sifre_ozeti_mi(string $deger): bool
{
    return (bool)preg_match('/^\$(2y|2a|2b|argon2i|argon2id)\$/', $deger);
}

/** gizli.php'yi verilen hesaplarla (özetlenmiş) yeniden yazar. */
function gizli_dosyayi_yaz(string $yol, array $hesaplar, string $dagitim, bool $hizli): bool
{
    $satirlar = [];
    foreach ($hesaplar as $ad => $ozet) {
        $satirlar[] = "    '" . str_replace("'", "\\'", (string)$ad) . "' => '" . str_replace("'", "\\'", (string)$ozet) . "',";
    }
    $icerik = "<?php\n"
        . "// RitimTerapi gizli yapılandırma — bu dosya sürüm denetimine GİRMEZ.\n"
        . "//\n"
        . "// Şifreler password_hash ile ÖZETLENMİŞTİR; düz metin yazmayın.\n"
        . "// Şifre değiştirmek için düz metin yazıp kaydedin — uygulama ilk açılışta\n"
        . "// kendiliğinden özete çevirir ve bu dosyayı yeniden yazar.\n"
        . "define('PANEL_KULLANICILAR', [\n" . implode("\n", $satirlar) . "\n]);\n\n"
        . "// 'yerel' = kendi makineniz/atölye Wi-Fi'ı · 'yayin' = internete açık sunucu.\n"
        . "// Tanımlı değilse GÜVENLİ taraf ('yayin') seçilir.\n"
        . "define('DAGITIM', '" . ($dagitim === 'yerel' ? 'yerel' : 'yayin') . "');\n\n"
        . "// Tek tıkla giriş butonları — YALNIZ 'yerel' dağıtımda ve yalnız\n"
        . "// döngü arayüzünden gelen istekte çalışır. Yayında etkisizdir.\n"
        . "define('HIZLI_GIRIS', " . ($hizli ? 'true' : 'false') . ");\n";
    return @file_put_contents($yol, $icerik, LOCK_EX) !== false;
}

if (!is_file($gizliDosya)) {
    if (!is_dir(STORAGE_DIR)) { @mkdir(STORAGE_DIR, 0755, true); }
    /* İlk kurulum: rastgele şifre üretilir ve YALNIZ bir kez ekranda gösterilir.
       Varsayılan 'ritim' şifresiyle kurulmuş bir uygulama internete açılırsa
       ilk denenecek şey odur. */
    $ilkSifre = bin2hex(random_bytes(6));
    /*
     * DAĞITIM KİPİ İLK KURULUMDA NASIL SEÇİLİR — güvenlik açısından kritik.
     *
     * Eskiden burada 'yerel' + HIZLI_GIRIS=true SABİT yazılıyordu. Sonucu:
     * uygulama nginx/PHP-FPM arkasında yayına alındığında ilk istek bu
     * dosyayı üretiyor, REMOTE_ADDR vekil yüzünden 127.0.0.1 göründüğü için
     * yerel_istek_mi() TRUE dönüyor ve giriş sayfası ŞİFRESİZ TEK TIK panel
     * düğmelerini TÜM İNTERNETE gösteriyordu.
     *
     * Ayrım tek başına REMOTE_ADDR'den yapılamaz (vekil de döngü görünür).
     * Ayırt edici sinyal SAPI: start.bat `php -S` çalıştırır → 'cli-server'.
     * Apache/FPM ile yayınlanan bir kurulum asla 'cli-server' olmaz. Şüpheli
     * her durumda GÜVENLİ taraf ('yayin') seçilir; eğitmen isterse dosyadan
     * 'yerel' yapar — kolaylık geri alınabilir, sızan panel geri alınamaz.
     */
    $yerelKurulum = PHP_SAPI === 'cli-server'
        && in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true);
    gizli_dosyayi_yaz($gizliDosya, [
        'admin'   => password_hash($ilkSifre, PASSWORD_DEFAULT),
        'egitmen' => password_hash($ilkSifre, PASSWORD_DEFAULT),
    ], $yerelKurulum ? 'yerel' : 'yayin', $yerelKurulum);
    @file_put_contents(STORAGE_DIR . '/ILK-SIFRE.txt',
        "RitimTerapi ilk giriş şifresi: {$ilkSifre}\n\n"
        . "Kullanıcı adları: admin, egitmen\n"
        . "Şifreyi değiştirmek için storage/gizli.php dosyasında ilgili satıra düz metin\n"
        . "yeni şifreyi yazın; uygulama ilk açılışta özete çevirir.\n\n"
        . "Dağıtım kipi: " . ($yerelKurulum ? 'yerel' : 'yayin') . "\n"
        . ($yerelKurulum
            ? "Tek tıkla giriş açık (yalnız bu bilgisayardan).\n"
            : "Tek tıkla giriş KAPALI. Bu uygulamayı yalnız kendi bilgisayarınızda\n"
              . "kullanacaksanız storage/gizli.php içinde DAGITIM'i 'yerel',\n"
              . "HIZLI_GIRIS'i true yapabilirsiniz. İNTERNETE AÇIK sunucuda ASLA yapmayın.\n")
        . "BU DOSYAYI OKUDUKTAN SONRA SİLİN.\n");
}
if (is_file($gizliDosya)) { require $gizliDosya; }

/* Eski kurulum uyumu: yalnız PANEL_SIFRE tanımlıysa iki hesap da onu kullanır. */
if (!defined('PANEL_KULLANICILAR')) {
    $eski = defined('PANEL_SIFRE') ? PANEL_SIFRE : 'ritim';
    define('PANEL_KULLANICILAR', ['admin' => $eski, 'egitmen' => $eski]);
}
if (!defined('HIZLI_GIRIS')) { define('HIZLI_GIRIS', false); }

/*
 * DÜZ METİNDEN ÖZETE TAŞIMA — bir kez, kendiliğinden.
 * Kullanıcının şifresini bilmeye gerek yok: dosyadaki düz metin değer
 * özetlenip dosya yeniden yazılır. Şifre aynı kalır, saklanışı değişir.
 */
$duzMetinVar = false;
$hesaplarOzet = [];
foreach (PANEL_KULLANICILAR as $ad => $deger) {
    $deger = (string)$deger;
    if (sifre_ozeti_mi($deger)) { $hesaplarOzet[$ad] = $deger; continue; }
    $hesaplarOzet[$ad] = password_hash($deger, PASSWORD_DEFAULT);
    $duzMetinVar = true;
}
if ($duzMetinVar && is_writable($gizliDosya)) {
    gizli_dosyayi_yaz($gizliDosya, $hesaplarOzet,
        dagitim_kipi(), defined('HIZLI_GIRIS') && HIZLI_GIRIS);
}
/** Doğrulamada kullanılacak nihai hesap listesi (her zaman özetli). */
define('PANEL_HESAPLAR', $hesaplarOzet);

/**
 * Şifre doğrulama. Kullanıcı adı bilinmese bile SABİT SÜRE harcanır —
 * aksi hâlde yanıt süresi "bu kullanıcı var mı?" bilgisini sızdırır.
 */
function panel_sifre_dogrula(string $kullanici, string $sifre): bool
{
    $ozet = PANEL_HESAPLAR[$kullanici] ?? null;
    if ($ozet === null) {
        /* Sahte doğrulama: zamanlama farkını kapatır */
        password_verify($sifre, '$2y$10$usesomesillystringfoxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
        return false;
    }
    return password_verify($sifre, (string)$ozet);
}

/**
 * İstemcinin IP adresi.
 *
 * Ters vekil arkasında REMOTE_ADDR her istekte vekilin adresidir; gerçek
 * istemci X-Forwarded-For'un SON atlamasındadır. Ama bu başlık istemci
 * tarafından uydurulabilir — o yüzden yalnız GÜVENİLİR VEKİL listesindeki
 * bir kaynaktan geldiğinde dikkate alınır. Liste storage/gizli.php'de
 * GUVENILIR_VEKILLER ile tanımlanır; tanımlı değilse başlığa hiç bakılmaz.
 */
function istemci_ip(): string
{
    $uzak = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $guvenilir = defined('GUVENILIR_VEKILLER') ? (array)GUVENILIR_VEKILLER : [];
    if ($uzak !== '' && in_array($uzak, $guvenilir, true)) {
        $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff !== '') {
            $parcalar = array_map('trim', explode(',', $xff));
            /* Son atlama = güvenilir vekilin gördüğü adres; öncekiler uydurulmuş olabilir */
            $aday = (string)end($parcalar);
            if (filter_var($aday, FILTER_VALIDATE_IP)) { return $aday; }
        }
    }
    return $uzak !== '' ? $uzak : 'bilinmiyor';
}

/**
 * SUNUCU TARAFLI HIZ SINIRI — istemci çerez silerek atlatamaz.
 *
 * @param string $ad     'giris' | 'evkod' gibi kapı adı
 * @param int    $sinir  pencere içinde izin verilen deneme
 * @param int    $pencereSn pencere boyu (saniye)
 * @return array{izin:bool, kalan:int, bekleSn:int}
 */
function hiz_siniri_dene(string $ad, int $sinir, int $pencereSn): array
{
    $simdi = time();
    $pencere = intdiv($simdi, max(1, $pencereSn));
    $anahtar = $ad . ':' . istemci_ip();
    try {
        $pdo = db();
        $pdo->prepare('INSERT INTO hiz_siniri (anahtar, pencere, adet, son_deneme)
                       VALUES (?, ?, 1, ?)
                       ON CONFLICT(anahtar, pencere)
                       DO UPDATE SET adet = adet + 1, son_deneme = excluded.son_deneme')
            ->execute([$anahtar, $pencere, $simdi]);
        $st = $pdo->prepare('SELECT adet FROM hiz_siniri WHERE anahtar = ? AND pencere = ?');
        $st->execute([$anahtar, $pencere]);
        $adet = (int)$st->fetchColumn();
        /* Eski pencereleri ara sıra temizle (ayrı bir görev kurmaya değmez) */
        if (random_int(1, 50) === 1) {
            $pdo->prepare('DELETE FROM hiz_siniri WHERE son_deneme < ?')->execute([$simdi - 86400]);
        }
    } catch (Throwable $e) {
        /* Veritabanı erişilemezse kapıyı KİLİTLEME — hizmeti durdurmak da bir zarar */
        error_log('RitimTerapi hız sınırı hatası: ' . $e->getMessage());
        return ['izin' => true, 'kalan' => $sinir, 'bekleSn' => 0];
    }
    return [
        'izin'    => $adet <= $sinir,
        'kalan'   => max(0, $sinir - $adet),
        'bekleSn' => ($pencere + 1) * $pencereSn - $simdi,
    ];
}

/** Başarılı girişten sonra sayacı sıfırlar. */
function hiz_siniri_sifirla(string $ad): void
{
    try {
        db()->prepare('DELETE FROM hiz_siniri WHERE anahtar = ?')->execute([$ad . ':' . istemci_ip()]);
    } catch (Throwable $e) { /* önemsiz */ }
}

function educator_logged_in(): bool
{
    return !empty($_SESSION['egitmen']);
}

// Merkezî koruma: tanıtım, giriş ve öğrenci ev sayfası dışındaki her sayfa
// eğitmen oturumu ister. (ev.php kendi öğrenci-kodu oturumunu yönetir.)
$SERBEST_SAYFALAR = ['index.php', 'giris.php', 'ev.php'];
$simdikiSayfa = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
if (!in_array($simdikiSayfa, $SERBEST_SAYFALAR, true) && !educator_logged_in()) {
    $hedef = $simdikiSayfa . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    redirect('giris.php?hedef=' . urlencode($hedef));
}

// Yakalanmayan hata ziyaretçiye boş sayfa göstermesin
set_exception_handler(function (Throwable $ex) {
    error_log('RitimTerapi hata: ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    // Ayrıntı yalnız eğitmenin kendi makinesinde; ağdan gelen istekte SQL
    // ve dosya yolu sızdırmamak için genel mesaj gösterilir.
    $ayrinti = yerel_istek_mi()
        ? htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8')
        : 'Sorun kaydedildi. Eğitmenin bilgisayarındaki günlükten ayrıntı görülebilir.';
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>Hata</title></head>'
       . '<body style="font-family:system-ui,sans-serif;background:#f8fafc;color:#0f172a;padding:3rem;text-align:center">'
       . '<h1 style="font-size:1.3rem">Beklenmeyen bir sorun oluştu</h1>'
       . '<p>' . $ayrinti . '</p>'
       . '<p><a href="javascript:location.reload()">Sayfayı yenile</a></p></body></html>';
    exit;
});

// Şema ve seed her istekte kontrol edilir; güncelse anında geçer.
run_migrations();
seed_techniques();
seed_site();
seed_articles();
seed_templates();
seed_home_exercises();
seed_template_home_tasks();
seed_studies();
seed_group_workshop_studies();
seed_scientific_corrections_2026();       // tek seferlik; mevcut kurulumlara da ulaşsın
seed_unsupported_technique_studies();     // arka plan kaynakları; kanıt düzeyine DOKUNMAZ
seed_timbre_technique_2026();             // "Kalın–İnce Tını" tekniği + 7 doğrulanmış kaynak
seed_ritmoterapi_techniques();            // sertifika kursundan 16 etkinlik; kanıt düzeyleri kurstan BAĞIMSIZ verildi
ensure_student_codes();
auto_backup_daily();
purge_expired_personal_data();   // saklama süresi dolan iletişim talepleri
