<?php
/**
 * "Uygulama olarak kullan" şeridi — sayfanın EN ÜSTÜNE konur.
 *
 * Herkese açık her yüzeyde aynı parça kullanılır (tanıtım sayfası, giriş,
 * katılımcı portalı) ki kurulum daveti tek yerden yönetilsin.
 *
 * Görünürlük kararını JS verir (assets/js/uygulama-yukle.js): tarayıcı
 * kurulumu desteklemiyorsa ya da uygulama zaten kuruluysa şerit hiç açılmaz.
 * O yüzden burada `hidden` ile başlar — JS kapalıyken de sayfa temiz kalır.
 */
if (!defined('RITIM')) { exit; }
?>
<div class="uyg-serit" id="uygulamaSerit" hidden role="region" aria-label="Uygulama olarak kullan">
  <div class="uyg-serit-ic">
    <span class="uyg-serit-ikon" aria-hidden="true">📲</span>
    <p class="uyg-serit-metin">
      <strong><?= e(PUBLIC_BRAND) ?></strong>'ni telefonuna ya da tabletine
      <strong>uygulama olarak</strong> ekleyebilirsin — tarayıcı açmadan, tam ekran çalışır.
    </p>
    <button type="button" class="uyg-serit-kur">Uygulama olarak ekle</button>
    <button type="button" class="uyg-serit-kapat" aria-label="Bu öneriyi kapat">✕</button>
  </div>
  <p class="uyg-serit-yardim" hidden>
    Paylaş düğmesi Safari'nin alt çubuğundadır (yukarı ok simgesi).
  </p>
</div>
