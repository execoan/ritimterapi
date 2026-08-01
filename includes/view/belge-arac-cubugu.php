<?php
if (!defined('RITIM')) { http_response_code(403); exit; }
/**
 * Yazdırılabilir belgeler için ORTAK araç çubuğu: Yazdır · Düzenle · Orijinale Dön.
 * (optik-kocluk desenidir; belge-duzenle.js ile birlikte çalışır.)
 *
 * Kullanım:
 *   1) Belgenin dış sarmalayıcısına data-belge özniteliğini verin:
 *        <div class="kart" data-belge> ...belge içeriği... </div>
 *   2) Sarmalayıcıdan HEMEN ÖNCE bu dosyayı çağırın:
 *        <?php require APP_DIR . '/includes/view/belge-arac-cubugu.php'; ?>
 *   3) Sayfa sonunda belge-duzenle.js dosyasını yükleyin.
 *
 * İsteğe bağlı: $BELGE_ETIKET — düğme metnindeki belge adı (örn. 'Sertifikayı').
 * Düzenlemeler yalnız ekran ve PDF çıktısı içindir; kayıtlı veri DEĞİŞMEZ.
 */
$belgeEtiket = isset($BELGE_ETIKET) && $BELGE_ETIKET !== '' ? (string)$BELGE_ETIKET : 'Belgeyi';
?>
<div class="belge-arac-cubugu yazdirmada-gizle">
  <button type="button" class="btn btn-birincil" data-belge-yazdir><span class="emoji-sus" aria-hidden="true">🖨</span> Yazdır / PDF Kaydet</button>
  <button type="button" class="btn btn-golge" data-belge-duzenle><span class="emoji-sus" aria-hidden="true">✏️</span> <?= e($belgeEtiket) ?> Düzenle</button>
  <button type="button" class="btn btn-golge gizli" data-belge-orijinal>Orijinale Dön</button>
</div>
<div class="bilgi-kutu gizli yazdirmada-gizle" data-belge-not>
  ✏️ <strong>Düzenleme modu açık.</strong> Başlık, metin ve tablo hücrelerini tıklayıp serbestçe
  değiştirebilir, gereksiz satırları silebilirsiniz. Değişiklikler yalnız bu ekran ve
  yazdırma/PDF içindir — <strong>kayıtlı veri değişmez</strong>. Sayfayı yenileyince veya
  "Orijinale Dön" ile kayıtlı hâl geri gelir. Veliye giden belgede dil kurallarını koruyun.
</div>
<?php unset($BELGE_ETIKET); ?>
