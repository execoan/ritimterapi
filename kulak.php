<?php
/**
 * Kulak Yolu — ilerlemeli işitsel ayrım çalışması.
 *
 * 5 bölüm · 24 adım · adım başına 10 soru (bkz. kulak-cekirdegi.js).
 * Sesler cihazda sentezlenir (dosya yok); sorular her açılışta yeni
 * tohumla üretilir — ezber değil kulak çalışır. Skor ŞANS DÜZELTMELİDİR:
 * iki seçenekli soruda rastgele işaretleyen 0 alır (bkz. yol-cekirdegi.js).
 *
 * İlerleme bu cihazda saklanır; protokol ölçümlerine YAZILMAZ (bilinçli:
 * bu bir çalışma alanıdır, ölçüm serilerini kirletmez — şarkı BPM oyunu
 * ile aynı karar).
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$PAGE_TITLE = 'Kulak Yolu';
require APP_DIR . '/includes/view/header.php';
?>
<link rel="stylesheet" href="<?= e(asset('css/yol.css')) ?>">

<div class="sayfa-baslik">
  <div>
    <h1><span class="emoji-sus" aria-hidden="true">👂</span> Kulak Yolu</h1>
    <p class="alan-ipucu">Gözler kapalı da olur: sesleri dinle, farkı bul.
      Tiz–pes'ten ritmik kulağa beş bölümlük yol.</p>
  </div>
</div>

<!-- Yol görünümü -->
<div class="kart yol-sahne" id="kulakYolKarti">
  <div class="yol-ust">
    <h2>İşitme Yolculuğu</h2>
    <div class="yol-ilerleme" id="kulakOzet" aria-live="polite">–<small>adım tamamlandı</small></div>
  </div>
  <p class="alan-ipucu" style="position:relative">
    Her adım 10 soru. Yıldız eşikleri şans payı düşülerek hesaplanır:
    rastgele işaretleyen yıldız alamaz. ★ %50 · ★★ %70 · ★★★ %90.
  </p>
  <div id="kulakBolumler"></div>
</div>

<!-- Adım sahnesi (yoldan bir düğüme tıklayınca açılır) -->
<div class="kart yol-sahne kulak-sahne" id="kulakAdimKarti" hidden>
  <div class="kulak-soru-ust">
    <button type="button" class="btn btn-kucuk btn-golge" id="kulakGeri">← Yola dön</button>
    <strong id="kulakAdimAdi"></strong>
    <span class="kulak-soru-no" id="kulakSoruNo"></span>
  </div>

  <div class="kulak-can" id="kulakCan" aria-hidden="true">👂</div>
  <div>
    <button type="button" class="btn btn-golge" id="kulakDinle">
      <span class="emoji-sus" aria-hidden="true">🔊</span> Tekrar Dinle
    </button>
  </div>

  <div class="kulak-secenekler" id="kulakSecenekler" role="group" aria-label="Cevap seçenekleri"></div>
  <p class="kulak-geri-bildirim" id="kulakGeriBildirim" role="status" aria-live="polite"></p>
  <div class="kulak-ilerleme-cubugu" aria-hidden="true"><div class="kulak-ilerleme-dolu" id="kulakIlerleme"></div></div>

  <div id="kulakSonuc" hidden>
    <div class="kulak-sonuc-yildiz" id="kulakSonucYildiz" aria-hidden="true"></div>
    <p id="kulakSonucMetin" role="status"></p>
    <div class="filtre-satir" style="justify-content:center">
      <button type="button" class="btn btn-birincil" id="kulakTekrar">Bu Adımı Tekrar Çalış</button>
      <button type="button" class="btn btn-golge" id="kulakSonrakiAdim">Yola Dön →</button>
    </div>
  </div>
</div>

<script src="<?= e(asset('js/yol-cekirdegi.js')) ?>"></script>
<script src="<?= e(asset('js/kulak-cekirdegi.js')) ?>"></script>
<script src="<?= e(asset('js/kulak.js')) ?>"></script>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
