<?php
if (!defined('RITIM')) { http_response_code(403); exit; }
/**
 * Sayfa başı. $PAGE_TITLE desteklenir. Rapor sayfalarında üst bar yazdırmada
 * otomatik gizlenir (print CSS).
 */
$PAGE_TITLE = $PAGE_TITLE ?? 'Panel';
$aktifSayfa = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$navLinkler = [
    'panel.php'      => 'Panel',
    'gruplar.php'    => 'Gruplar',
    'ogrenciler.php' => 'Öğrenciler',
    'teknikler.php'  => 'Teknikler',
    'sablonlar.php'  => 'Şablonlar',
    'ev-programi.php' => 'Ev Programı',
    'metronom.php'   => 'Metronom',
    'oturumlar.php'  => 'Oturumlar',
    'raporlar.php'   => 'Raporlar',
    'site.php'       => 'Site',
    'yedek.php'      => 'Yedek',
];
// Alt sayfaları üst menü maddesine bağla (grup.php → gruplar.php gibi)
$navesle = [
    'grup.php' => 'gruplar.php', 'ogrenci.php' => 'ogrenciler.php', 'teknik.php' => 'teknikler.php',
    'calismalar.php' => 'teknikler.php',
    'plan.php' => 'oturumlar.php', 'oturum.php' => 'oturumlar.php', 'sablon-oturum.php' => 'sablonlar.php',
    'rapor-haftalik.php' => 'raporlar.php', 'rapor-donemlik.php' => 'raporlar.php', 'rapor-veli.php' => 'raporlar.php',
    'sertifika.php' => 'raporlar.php',
];
$aktifNav = $navesle[$aktifSayfa] ?? $aktifSayfa;
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($PAGE_TITLE) ?> | <?= e(APP_NAME) ?></title>
<meta name="theme-color" content="#1c1917">
<link rel="manifest" href="<?= e(url('manifest.json')) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('img/apple-touch-icon.png')) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(APP_NAME) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<header class="ust-bar yazdirmada-gizle">
  <div class="kapsayici ust-bar-ic">
    <a class="marka" href="<?= e(url('panel.php')) ?>" title="Panele dön">
      <svg class="marka-logo" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
        <defs>
          <linearGradient id="mgov" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#fbbf24"/><stop offset="1" stop-color="#d97706"/>
          </linearGradient>
        </defs>
        <path d="M25 5 H39 L52.5 53.5 Q53.8 58 49.2 58 H14.8 Q10.2 58 11.5 53.5 Z" fill="url(#mgov)"/>
        <path d="M29.2 12 H34.8 L40.6 51 H23.4 Z" fill="#1e293b"/>
        <line x1="32" y1="49" x2="43.5" y2="14.5" stroke="#f8fafc" stroke-width="2.6" stroke-linecap="round"/>
        <rect x="35.4" y="26.2" width="7.4" height="5" rx="1.2" fill="#f59e0b" stroke="#92400e" stroke-width="1" transform="rotate(18 39.1 28.7)"/>
        <circle cx="32" cy="49" r="3" fill="#f8fafc"/>
        <circle cx="32" cy="49" r="1.4" fill="#b45309"/>
        <rect x="9" y="58" width="46" height="3.2" rx="1.6" fill="#78350f"/>
      </svg>
      <span class="marka-metin"><?= e(APP_NAME) ?></span>
    </a>
    <button type="button" class="nav-hamburger" id="navHamburger" aria-label="Menüyü aç/kapat"
            aria-expanded="false" aria-controls="ustNav">☰</button>
    <nav class="ust-nav" id="ustNav">
      <button type="button" class="btn btn-kucuk yukle-btn" id="uygulamaYukleBtn" hidden>📲 Uygulamayı Yükle</button>
      <?php foreach ($navLinkler as $dosya => $etiket): ?>
      <a class="nav-link<?= $aktifNav === $dosya ? ' nav-aktif' : '' ?>" href="<?= e(url($dosya)) ?>"><?= e($etiket) ?></a>
      <?php endforeach; ?>
      <form method="post" action="<?= e(url('cikis.php')) ?>" class="nav-cikis-form">
        <?= csrf_field() ?>
        <button type="submit" class="nav-link nav-cikis" title="Oturumu kapat">Çıkış</button>
      </form>
    </nav>
  </div>
</header>
<main class="kapsayici govde">
<?php foreach (flash_get() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>" role="alert"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
