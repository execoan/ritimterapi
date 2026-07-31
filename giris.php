<?php
/** Eğitmen girişi — tanıtım sitesinden panele geçiş kapısı. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$hedef = (string)($_GET['hedef'] ?? $_POST['hedef'] ?? '');
// Yalnız uygulama içi dosya adları kabul edilir (açık yönlendirme koruması)
if (!preg_match('/^[a-z0-9-]+\.php(\?[^\s]*)?$/i', $hedef)) { $hedef = 'panel.php'; }

if (educator_logged_in()) { redirect($hedef); }

$hataVar = false;
$hataMesaji = 'Kullanıcı adı veya şifre doğru değil.';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('giris.php');

    // Deneme kilidi: 5 başarısız denemeden sonra 30 saniye beklenir
    $deneme = $_SESSION['giris_deneme'] ?? ['adet' => 0, 'son' => 0];
    if ($deneme['adet'] >= 5 && time() - (int)$deneme['son'] < 30) {
        $hataVar = true;
        $hataMesaji = 'Çok fazla başarısız deneme — 30 saniye bekleyip yeniden deneyin.';
    } else {
        if (time() - (int)$deneme['son'] >= 30) { $deneme = ['adet' => 0, 'son' => 0]; }

        /*
         * Tek tıkla hızlı giriş — YALNIZ eğitmenin kendi makinesinden.
         * start.bat sunucuyu 0.0.0.0'a bağlar (telefon/tablet bağlanabilsin);
         * hızlı giriş ağa da açık olsaydı aynı Wi-Fi'daki herkes tek tıkla
         * panele, yani öğrenci kayıtlarına ve veli notlarına girerdi.
         * Yerelde kolaylık aynen sürüyor; ağdan giren şifre yazar.
         * (gizli.php'de HIZLI_GIRIS=false ile tamamen kapatılabilir.)
         */
        $hizli = (string)($_POST['hizli'] ?? '');
        if (HIZLI_GIRIS && yerel_istek_mi() && $hizli !== '' && array_key_exists($hizli, PANEL_KULLANICILAR)) {
            unset($_SESSION['giris_deneme']);
            session_regenerate_id(true);
            $_SESSION['egitmen'] = 1;
            $_SESSION['rol'] = $hizli;
            redirect($hedef);
        }

        $kullanici = strtolower(trim((string)($_POST['kullanici'] ?? '')));
        $sifre = (string)($_POST['sifre'] ?? '');
        $beklenen = PANEL_KULLANICILAR[$kullanici] ?? null;
        if ($beklenen !== null && $sifre !== '' && hash_equals((string)$beklenen, $sifre)) {
            unset($_SESSION['giris_deneme']);
            session_regenerate_id(true);
            $_SESSION['egitmen'] = 1;
            $_SESSION['rol'] = $kullanici;
            redirect($hedef);
        }
        usleep(400000); // kaba deneme yavaşlatma
        $_SESSION['giris_deneme'] = ['adet' => (int)$deneme['adet'] + 1, 'son' => time()];
        $hataVar = true;
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Giriş Yap | RitimTerapi</title>
<meta name="theme-color" content="#0c0a09">
<link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/landing.css')) ?>">
<style>
  .giris-sahne {
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    padding: 1.5rem; position: relative; overflow: hidden;
    background:
      radial-gradient(900px 460px at 70% -10%, rgba(245,158,11,.16), transparent 60%),
      radial-gradient(600px 380px at 8% 108%, rgba(217,119,6,.12), transparent 60%),
      var(--t-zemin);
  }
  .giris-kart {
    width: 100%; max-width: 400px; background: rgba(28,25,23,.88);
    border: 1px solid rgba(255,255,255,.1); border-radius: 22px;
    padding: 2.2rem 2rem; position: relative; z-index: 2;
    box-shadow: 0 24px 70px rgba(0,0,0,.55);
    animation: giris-gel .5s ease;
  }
  @keyframes giris-gel { from { opacity: 0; transform: translateY(22px) scale(.98); } }
  .giris-kart .t-logo { width: 56px; height: 56px; display: block; margin: 0 auto .8rem; }
  .giris-kart h1 { text-align: center; font-size: 1.35rem; margin-bottom: .3rem; }
  .giris-kart .alt-yazi { text-align: center; color: var(--t-soluk); font-size: .9rem; margin: 0 0 1.5rem; }
  .giris-alan { display: block; font-size: .85rem; font-weight: 700; margin-bottom: 1.1rem; }
  .giris-girdi {
    width: 100%; margin-top: .35rem; padding: .7rem .9rem; border-radius: 12px;
    border: 1px solid rgba(255,255,255,.18); background: rgba(12,10,9,.7);
    color: var(--t-metin); font-size: 1rem; font-family: inherit;
  }
  .giris-girdi:focus { outline: 2px solid rgba(245,158,11,.5); border-color: var(--t-amber); }
  .giris-hata {
    background: rgba(185,28,28,.18); border: 1px solid rgba(248,113,113,.4); color: #fca5a5;
    padding: .6rem .9rem; border-radius: 12px; font-size: .88rem; margin-bottom: 1rem;
    animation: sars .35s ease;
  }
  @keyframes sars { 0%,100% { transform: translateX(0); } 25% { transform: translateX(-7px); } 75% { transform: translateX(7px); } }
  .giris-btn { width: 100%; text-align: center; }
  .giris-geri { display: block; text-align: center; margin-top: 1.1rem; color: var(--t-soluk); font-size: .88rem; }
  .giris-geri:hover { color: var(--t-amber); }
  .giris-hizli { display: flex; gap: .7rem; margin-bottom: .4rem; }
  .giris-hizli-btn { flex: 1; text-align: center; padding: .7rem 0; }
  .giris-ayrac {
    display: flex; align-items: center; gap: .8rem; color: var(--t-soluk);
    font-size: .8rem; margin: .9rem 0;
  }
  .giris-ayrac::before, .giris-ayrac::after {
    content: ""; flex: 1; height: 1px; background: rgba(255, 255, 255, .14);
  }
  .giris-girdi-kucuk { letter-spacing: normal; text-transform: none; font-size: 1rem; text-align: left; }
  .giris-ipucu { color: var(--t-soluk); font-size: .76rem; margin: 1rem 0 0; text-align: center; }
  .giris-ipucu code { color: #fbbf24; }
</style>
</head>
<body class="tanitim">
<div class="giris-sahne">
  <div class="t-halka t-halka-1" aria-hidden="true"></div>
  <div class="t-halka t-halka-2" aria-hidden="true"></div>
  <div class="giris-kart">
    <svg class="t-logo" viewBox="0 0 64 64" aria-hidden="true">
      <defs><linearGradient id="gg" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#fbbf24"/><stop offset="1" stop-color="#d97706"/>
      </linearGradient></defs>
      <path d="M25 5 H39 L52.5 53.5 Q53.8 58 49.2 58 H14.8 Q10.2 58 11.5 53.5 Z" fill="url(#gg)"/>
      <path d="M29.2 12 H34.8 L40.6 51 H23.4 Z" fill="#1e293b"/>
      <g class="t-sarkac">
        <line x1="32" y1="49" x2="32" y2="12.5" stroke="#f8fafc" stroke-width="2.6" stroke-linecap="round"/>
        <rect x="28.3" y="24.2" width="7.4" height="5" rx="1.2" fill="#f59e0b" stroke="#92400e" stroke-width="1"/>
      </g>
      <circle cx="32" cy="49" r="3" fill="#f8fafc"/><circle cx="32" cy="49" r="1.4" fill="#b45309"/>
      <rect x="9" y="58" width="46" height="3.2" rx="1.6" fill="#78350f"/>
    </svg>
    <h1>Eğitmen Girişi</h1>
    <p class="alt-yazi">Atölye yönetim paneline hoş geldiniz.</p>
    <?php if ($hataVar): ?>
    <div class="giris-hata"><?= e($hataMesaji) ?></div>
    <?php endif; ?>

    <?php if (HIZLI_GIRIS && yerel_istek_mi()): ?>
    <form method="post" action="<?= e(url('giris.php')) ?>" class="giris-hizli">
      <?= csrf_field() ?>
      <input type="hidden" name="hedef" value="<?= e($hedef) ?>">
      <button type="submit" name="hizli" value="admin" class="t-btn t-btn-dolu giris-hizli-btn">⚡ Admin</button>
      <button type="submit" name="hizli" value="egitmen" class="t-btn t-btn-dolu giris-hizli-btn">⚡ Eğitmen</button>
    </form>
    <div class="giris-ayrac"><span>veya</span></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('giris.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="hedef" value="<?= e($hedef) ?>">
      <label class="giris-alan">Kullanıcı adı
        <input type="text" name="kullanici" class="giris-girdi giris-girdi-kucuk" required
               autocomplete="username" autocapitalize="off" placeholder="admin">
      </label>
      <label class="giris-alan">Şifre
        <input type="password" name="sifre" class="giris-girdi giris-girdi-kucuk" required
               autocomplete="current-password" placeholder="••••••">
      </label>
      <button type="submit" class="t-btn t-btn-dolu t-btn-buyuk giris-btn">Giriş Yap</button>
    </form>
    <?php if (HIZLI_GIRIS && yerel_istek_mi()): ?>
    <p class="giris-ipucu">Hızlı giriş geliştirme kolaylığıdır; yayına alırken
       <code>storage/gizli.php</code> içinde kapatın ve şifreleri değiştirin.</p>
    <?php endif; ?>
    <a class="giris-geri" href="<?= e(url('index.php')) ?>">← Tanıtım sayfasına dön</a>
  </div>
</div>
</body>
</html>
