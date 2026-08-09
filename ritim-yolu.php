<?php
/**
 * Ritim Yolu — deşifre müfredatının ilerlemeli yol sunumu.
 *
 * Motor YENİDEN YAZILMADI: Ritim Okuma Laboratuvarı'nın 3 seviye × 8 ders ×
 * 16 alıştırmalık deterministik kataloğu zaten aynı pedagojik diziyi izliyor
 * (dörtlükler → sekizlikler → senkop → onaltılık → üçleme → karma deşifre).
 * Bu sayfa o kataloğu yol/harita olarak gösterir: kilit zinciri, yıldızlar,
 * "buradasın" işareti. Bir düğüme tıklamak laboratuvarı o derste açar.
 *
 * Tek doğruluk kaynağı laboratuvarın kendi localStorage kayıtlarıdır
 * (ritim_okuma_tamam_v3_*): yol ayrı ilerleme TUTMAZ, türetir — iki kayıt
 * birbirinden asla sapamaz.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$PAGE_TITLE = 'Ritim Yolu';
require APP_DIR . '/includes/view/header.php';
?>
<link rel="stylesheet" href="<?= e(asset('css/yol.css')) ?>">

<div class="sayfa-baslik">
  <div>
    <h1><span class="emoji-sus" aria-hidden="true">🛤️</span> Ritim Yolu</h1>
    <p class="alan-ipucu">Deşifre müfredatı adım adım: her düğüm bir ders, her ders 16 alıştırma.
      Bir dersten yıldız almadan sonraki açılmaz.</p>
  </div>
  <a class="btn btn-golge" href="<?= e(url('ritim-okuma.php')) ?>">Laboratuvarı serbest aç →</a>
</div>

<div class="kart yol-sahne">
  <div class="yol-ust">
    <h2>Deşifre Yolculuğu</h2>
    <div class="yol-ilerleme" id="yolOzet" aria-live="polite">–<small>ders tamamlandı</small></div>
  </div>
  <p class="alan-ipucu" style="position:relative">
    Yıldızlar dersin 16 alıştırmasından kaçını tamamladığını gösterir:
    ★ en az 4 · ★★ en az 10 · ★★★ en az 14. İlerleme bu cihazda saklanır.
  </p>
  <div id="yolBolumler" aria-live="polite"></div>
</div>

<script src="<?= e(asset('js/yol-cekirdegi.js')) ?>"></script>
<script src="<?= e(asset('js/zamanlama-cekirdegi.js')) ?>"></script>
<script src="<?= e(asset('js/ritim-ogrenme.js')) ?>"></script>
<script src="<?= e(asset('vendor/abcjs/abcjs-basic-min.js')) ?>"></script>
<script src="<?= e(asset('js/ritim-okuma.js')) ?>"></script>
<script src="<?= e(asset('js/ritim-yolu.js')) ?>"></script>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
