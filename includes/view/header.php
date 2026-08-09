<?php
if (!defined('RITIM')) { http_response_code(403); exit; }
/**
 * Sayfa başı. $PAGE_TITLE desteklenir. Rapor sayfalarında üst bar yazdırmada
 * otomatik gizlenir (print CSS).
 */
$PAGE_TITLE = $PAGE_TITLE ?? 'Panel';
$aktifSayfa = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));

/*
 * Üst menü kategorileri. 'tek' = doğrudan bağlantı, 'menu' = açılır kategori.
 * Bir kategori, içindeki maddelerden biri açıkken vurgulanır.
 */
$navYapisi = [
    ['tur' => 'tek', 'dosya' => 'panel.php', 'ikon' => '🏠', 'etiket' => 'Panel'],

    ['tur' => 'menu', 'ikon' => '🥁', 'etiket' => 'Atölye', 'ogeler' => [
        ['dosya' => 'gruplar.php',    'ikon' => '👥', 'etiket' => 'Gruplar',    'not' => 'Sınıflar, ders günü ve saati'],
        ['dosya' => 'ogrenciler.php', 'ikon' => '🧒', 'etiket' => 'Öğrenciler', 'not' => 'Katılımcılar, erişim kodları'],
        ['dosya' => 'oturumlar.php',  'ikon' => '📅', 'etiket' => 'Oturumlar',  'not' => 'Yoklama ve oturum kaydı'],
        ['dosya' => 'plan.php',       'ikon' => '📝', 'etiket' => 'Ders Planı', 'not' => 'Yeni oturum planla'],
        ['dosya' => 'grup-atolyesi.php','ikon' => '🫶', 'etiket' => 'Grup Atölyesi', 'not' => 'Güvenli ve kanıt çerçeveli grup akışları'],
        ['dosya' => 'motor-studyo.php','ikon' => '👐', 'etiket' => 'Motor Koordinasyon', 'not' => 'İki el ritim ve zamanlama çalışması'],
        ['dosya' => 'poliritim.php',  'ikon' => '🌀', 'etiket' => 'Poliritim Stüdyosu', 'not' => 'İki elde farklı ritim, aşamalı çalışma'],
        ['dosya' => 'ritim-yolu.php','ikon' => '🛤️', 'etiket' => 'Ritim Yolu', 'not' => 'Deşifre müfredatı yol hâlinde — kilit, yıldız, ilerleme'],
        ['dosya' => 'ritim-okuma.php','ikon' => '🎼', 'etiket' => 'Ritim Okuma', 'not' => '384 kademeli alıştırma, notadan deşifre'],
        ['dosya' => 'kulak.php','ikon' => '👂', 'etiket' => 'Kulak Yolu', 'not' => '5 bölümlük işitsel ayrım yolu — tiz/pes, kontur, ritmik kulak'],
        ['dosya' => 'tini-kartlari.php','ikon' => '🔊', 'etiket' => 'Kalın–İnce Kartları', 'not' => 'Atölye aracı — kalıp göster ve çal, puanlama yok'],
    ]],

    ['tur' => 'menu', 'ikon' => '📚', 'etiket' => 'İçerik', 'ogeler' => [
        ['dosya' => 'teknikler.php',   'ikon' => '🎯', 'etiket' => 'Teknik Kütüphanesi',  'not' => 'Ders içerikleri, kanıt düzeyi'],
        ['dosya' => 'calismalar.php',  'ikon' => '🔬', 'etiket' => 'Bilimsel Çalışmalar', 'not' => 'DOI kayıt defteri'],
        ['dosya' => 'sablonlar.php',   'ikon' => '🗂', 'etiket' => 'Plan Şablonları',     'not' => '12 haftalık hazır izler'],
        ['dosya' => 'ev-programi.php', 'ikon' => '🏡', 'etiket' => 'Ev Programı',         'not' => 'Ödevler ve günlük takip'],
    ]],

    ['tur' => 'tek', 'dosya' => 'metronom.php', 'ikon' => '🎛', 'etiket' => 'Metronom'],

    ['tur' => 'menu', 'ikon' => '📊', 'etiket' => 'Raporlar', 'ogeler' => [
        ['dosya' => 'raporlar.php',       'ikon' => '📋', 'etiket' => 'Rapor Merkezi',   'not' => 'Öğrenci raporu, veli raporu, sertifika'],
        ['dosya' => 'rapor-haftalik.php', 'ikon' => '🗓', 'etiket' => 'Haftalık Rapor',  'not' => 'Bu haftanın oturumları'],
        ['ayrac' => true],
        ['dosya' => 'disa-aktar.php?tur=protokol', 'ikon' => '📈', 'etiket' => 'Protokol CSV', 'not' => 'Ölçüm sonuçlarını indir'],
        ['dosya' => 'disa-aktar.php?tur=yoklama',  'ikon' => '📉', 'etiket' => 'Yoklama CSV',  'not' => 'Katılım kayıtlarını indir'],
    ]],

    ['tur' => 'menu', 'ikon' => '⚙️', 'etiket' => 'Yönetim', 'ogeler' => [
        ['dosya' => 'site.php',  'ikon' => '🌐', 'etiket' => 'Tanıtım Sitesi', 'not' => 'Metinler, bölümler, galeri'],
        ['dosya' => 'on-kayitlar.php', 'ikon' => '📨', 'etiket' => 'İletişim Talepleri', 'not' => 'Siteden gelen iletişim mesajları'],
        ['dosya' => 'yedek.php', 'ikon' => '💾', 'etiket' => 'Yedekleme',      'not' => 'İndir, geri yükle'],
        ['ayrac' => true],
        ['cikis' => true],
    ]],
];

// Alt sayfaları üst menü maddesine bağla (grup.php → gruplar.php gibi)
$navesle = [
    'grup.php' => 'gruplar.php', 'ogrenci.php' => 'ogrenciler.php', 'teknik.php' => 'teknikler.php',
    'oturum.php' => 'oturumlar.php', 'sablon-oturum.php' => 'sablonlar.php',
    'rapor-donemlik.php' => 'raporlar.php', 'rapor-veli.php' => 'raporlar.php',
    'sertifika.php' => 'raporlar.php', 'ogrenci-rapor.php' => 'raporlar.php',
];
$aktifNav = $navesle[$aktifSayfa] ?? $aktifSayfa;

/** Menü maddesinin eşleştiği dosya adı (sorgu dizesi atılır). */
$navAnahtar = fn(array $oge): string => strtok((string)($oge['dosya'] ?? ''), '?') ?: '';
/** Kategori, içindeki bir madde açıkken vurgulanır. */
$navAktifMi = function (array $kategori) use ($aktifNav, $navAnahtar): bool {
    foreach ($kategori['ogeler'] as $oge) {
        if (!empty($oge['dosya']) && $navAnahtar($oge) === $aktifNav) { return true; }
    }
    return false;
};
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
<?php /* Telefon ana ekranındaki ad DIŞ yüzeydir: PUBLIC_BRAND kullanılır. */ ?>
<meta name="apple-mobile-web-app-title" content="<?= e(PUBLIC_BRAND) ?>">
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
      <button type="button" class="btn btn-kucuk yukle-btn" id="uygulamaYukleBtn" hidden><span class="emoji-sus" aria-hidden="true">📲</span> Uygulamayı Yükle</button>

      <?php foreach ($navYapisi as $kategori): ?>
        <?php if ($kategori['tur'] === 'tek'): ?>
        <?php $buAktif = $aktifNav === $kategori['dosya']; ?>
        <a class="nav-link<?= $buAktif ? ' nav-aktif' : '' ?>"<?= $buAktif ? ' aria-current="page"' : '' ?>
           href="<?= e(url($kategori['dosya'])) ?>">
          <span class="nav-ikon" aria-hidden="true"><?= $kategori['ikon'] ?></span><?= e($kategori['etiket']) ?>
        </a>
        <?php else: ?>
        <details class="nav-grup<?= $navAktifMi($kategori) ? ' nav-grup-aktif' : '' ?>">
          <summary class="nav-link">
            <span class="nav-ikon" aria-hidden="true"><?= $kategori['ikon'] ?></span><?= e($kategori['etiket']) ?>
            <span class="nav-ok" aria-hidden="true">▾</span>
          </summary>
          <div class="nav-menu">
            <?php foreach ($kategori['ogeler'] as $oge): ?>
              <?php if (!empty($oge['ayrac'])): ?>
              <div class="nav-menu-ayrac" role="separator"></div>
              <?php elseif (!empty($oge['cikis'])): ?>
              <form method="post" action="<?= e(url('cikis.php')) ?>" class="nav-cikis-form">
                <?= csrf_field() ?>
                <button type="submit" class="nav-menu-oge nav-cikis" title="Oturumu kapat">
                  <span class="oge-ikon" aria-hidden="true">🚪</span>
                  <span class="oge-metin"><strong>Çıkış</strong><small>Oturumu kapat</small></span>
                </button>
              </form>
              <?php else: ?>
              <?php $ogeAktif = $navAnahtar($oge) === $aktifNav; ?>
              <a class="nav-menu-oge<?= $ogeAktif ? ' nav-aktif' : '' ?>"<?= $ogeAktif ? ' aria-current="page"' : '' ?>
                 href="<?= e(url($oge['dosya'])) ?>">
                <span class="oge-ikon" aria-hidden="true"><?= $oge['ikon'] ?></span>
                <span class="oge-metin"><strong><?= e($oge['etiket']) ?></strong><small><?= e($oge['not']) ?></small></span>
              </a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </details>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
<main class="kapsayici govde">
<?php foreach (flash_get() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>" role="alert"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
