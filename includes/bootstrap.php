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
define('APP_NAME', 'RitimTerapi');
// Veliye giden çıktılarda kullanılan ad — "terapi" sözcüğü geçmez (CLAUDE.md).
define('REPORT_BRAND', 'Ritim Atölyesi');

/**
 * İstek eğitmenin KENDİ makinesinden mi geliyor?
 *
 * Uygulama start.bat ile 0.0.0.0'a bağlanır (telefon/tablet aynı Wi-Fi'dan
 * girebilsin diye). Bu, hata ayıklama kolaylıklarının ağdaki HERKESE açık
 * olması demek — o yüzden bunlar yalnız yerel istekte etkin:
 *   • display_errors (yol ve SQL sızdırır)
 *   • ayrıntılı hata mesajı
 *   • HIZLI_GIRIS tek tık butonları (bkz. giris.php)
 */
function yerel_istek_mi(): bool
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return $ip === '127.0.0.1' || $ip === '::1' || $ip === '' || PHP_SAPI === 'cli';
}

error_reporting(E_ALL);
// Hatalar ekrana YALNIZ yerelde basılır; ağdan gelen istekte yalnız günlüğe.
@ini_set('display_errors', yerel_istek_mi() ? '1' : '0');
@ini_set('log_errors', '1');

// Temel güvenlik başlıkları (yerelde de zarar vermez)
if (!headers_sent()) {
    header_remove('X-Powered-By');          // PHP sürümünü duyurma
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'");
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('RITIMSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/seed.php';
require __DIR__ . '/model.php';

/*
 * Eğitmen giriş şifresi — storage/gizli.php içinde tutulur (ilk çalıştırmada
 * varsayılanla oluşturulur). Uygulama Wi-Fi ağına açık çalıştığı için panel
 * hafif bir şifre kapısının arkasındadır; tanıtım sayfası herkese açıktır.
 */
$gizliDosya = STORAGE_DIR . '/gizli.php';
if (!is_file($gizliDosya)) {
    if (!is_dir(STORAGE_DIR)) { @mkdir(STORAGE_DIR, 0755, true); }
    @file_put_contents($gizliDosya,
        "<?php\n// Giriş hesapları: kullanıcı adı => şifre. Düzenleyerek değiştirin.\n"
        . "define('PANEL_KULLANICILAR', ['admin' => 'ritim', 'egitmen' => 'ritim']);\n"
        . "// Giriş ekranındaki tek tıkla Admin/Eğitmen butonları (yayınlarken false yapın).\n"
        . "define('HIZLI_GIRIS', true);\n");
}
if (is_file($gizliDosya)) { require $gizliDosya; }
if (!defined('PANEL_SIFRE')) { define('PANEL_SIFRE', 'ritim'); }
// Eski kurulumla uyum: yalnız PANEL_SIFRE tanımlıysa iki hesap da onu kullanır.
if (!defined('PANEL_KULLANICILAR')) {
    define('PANEL_KULLANICILAR', ['admin' => PANEL_SIFRE, 'egitmen' => PANEL_SIFRE]);
}
if (!defined('HIZLI_GIRIS')) { define('HIZLI_GIRIS', true); }

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
seed_scientific_corrections_2026();   // tek seferlik; mevcut kurulumlara da ulaşsın
ensure_student_codes();
auto_backup_daily();
