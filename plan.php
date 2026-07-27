<?php
/**
 * Ders Planı — bir gruba oturum planla: tarih seç, teknikleri sırala
 * (sürükle-bırak), toplam süre otomatik hesaplanır (60 dk hedef, aşımda uyarı).
 * ?oturum_id= verilirse mevcut planı düzenler.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$oturumId = (int)($_GET['oturum_id'] ?? $_POST['oturum_id'] ?? 0) ?: null;
$oturum = $oturumId ? session_get($oturumId) : null;
if ($oturumId && !$oturum) { not_found('Oturum bulunamadı.'); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('plan.php' . ($oturumId ? '?oturum_id=' . $oturumId : ''));
    $ids    = (array)($_POST['teknik_id'] ?? []);
    $sureler = (array)($_POST['sure_dk'] ?? []);
    $notlar  = (array)($_POST['uygulama_notu'] ?? []);
    $items = [];
    foreach (array_values($ids) as $i => $tid) {
        $items[] = [
            'teknik_id'     => (int)$tid,
            'sure_dk'       => (int)($sureler[$i] ?? 10),
            'uygulama_notu' => (string)($notlar[$i] ?? ''),
        ];
    }
    $res = session_save_plan($_POST, $items, $oturumId);
    if ($res['ok']) {
        flash_set('basari', $oturumId ? 'Plan güncellendi.' : 'Oturum planlandı.');
        redirect('oturum.php?id=' . $res['id']);
    }
    flash_set('hata', $res['error']);
    redirect('plan.php' . ($oturumId ? '?oturum_id=' . $oturumId : ($_POST['grup_id'] ? '?grup_id=' . (int)$_POST['grup_id'] : '')));
}

$gruplar = groups_list(true);
$teknikler = techniques_list(['aktif' => 1]);
$planli = $oturumId ? session_techniques($oturumId) : [];

$seciliGrupId = $oturum ? (int)$oturum['grup_id'] : (int)($_GET['grup_id'] ?? 0);
$seciliGrup = $seciliGrupId ? group_get($seciliGrupId) : null;
$varsayilanTarih = $oturum ? $oturum['tarih'] : ($seciliGrup ? group_next_date($seciliGrup) : today());

$PAGE_TITLE = $oturum ? 'Planı Düzenle' : 'Oturum Planla';
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik">
  <h1><?= $oturum ? 'Planı Düzenle — ' . e($oturum['grup_ad']) . ', ' . e(format_date_tr($oturum['tarih'])) : 'Oturum Planla' ?></h1>
  <?php if ($oturum): ?>
  <div class="sag"><a class="btn btn-golge" href="<?= e(url('oturum.php?id=' . $oturumId)) ?>">← Oturuma dön</a></div>
  <?php endif; ?>
</div>

<?php if (!$gruplar && !$oturum): ?>
<div class="kart"><div class="bos-durum">Önce bir grup oluşturmalısınız. <a href="<?= e(url('gruplar.php')) ?>">Gruplar</a> sayfasına gidin.</div></div>
<?php else: ?>
<form method="post" action="<?= e(url('plan.php' . ($oturumId ? '?oturum_id=' . $oturumId : ''))) ?>">
  <?= csrf_field() ?>
  <?php if ($oturumId): ?><input type="hidden" name="oturum_id" value="<?= $oturumId ?>"><?php endif; ?>

  <div class="kart">
    <h2>Oturum bilgileri</h2>
    <div class="form-grid">
      <label class="form-alan">Grup
        <?php if ($oturum): ?>
          <input type="hidden" name="grup_id" value="<?= (int)$oturum['grup_id'] ?>">
          <input type="text" class="girdi" value="<?= e($oturum['grup_ad']) ?>" disabled>
          <span class="alan-ipucu">Mevcut oturumun grubu değiştirilemez.</span>
        <?php else: ?>
          <select name="grup_id" class="secim" id="grupSec" required>
            <option value="">— Grup seçin —</option>
            <?php foreach ($gruplar as $g): ?>
            <option value="<?= (int)$g['id'] ?>" data-sonraki="<?= e(group_next_date($g)) ?>"
                    <?= $seciliGrupId === (int)$g['id'] ? 'selected' : '' ?>>
              <?= e($g['ad']) ?> (<?= e(GUNLER[(int)$g['gun']] ?? '') ?><?= $g['saat'] ? ' ' . e($g['saat']) : '' ?>)
            </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </label>
      <label class="form-alan">Tarih
        <input type="date" name="tarih" id="tarihGirdi" class="girdi" value="<?= e($varsayilanTarih) ?>" required>
      </label>
      <label class="form-alan">Hafta no
        <input type="number" name="hafta_no" class="girdi" min="1" max="999"
               value="<?= $oturum ? (int)$oturum['hafta_no'] : '' ?>" placeholder="Otomatik">
        <span class="alan-ipucu">Boş bırakılırsa grubun başlangıç tarihinden hesaplanır.</span>
      </label>
      <label class="form-alan">Hedef süre (dk)
        <input type="number" id="hedefSure" class="girdi" value="60" min="10" max="180">
        <span class="alan-ipucu">Yalnız uyarı içindir; kaydedilmez.</span>
      </label>
      <label class="form-alan">Haftanın protokolü
        <select name="protokol" class="secim">
          <option value="">— Yok —</option>
          <?php foreach (PROTOKOL_LABELS as $pKod => $pAd): ?>
          <option value="<?= e($pKod) ?>" <?= ($oturum['protokol'] ?? '') === $pKod ? 'selected' : '' ?>><?= e($pAd) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="alan-ipucu">Oturum ekranından Stüdyo'ya tek tıkla açılır.</span>
      </label>
    </div>
  </div>

  <div class="kart">
    <div class="kart-baslik">
      <h2>Teknikler</h2>
      <span class="alan-ipucu">Sıralamak için satırları sürükleyin.</span>
    </div>
    <div class="filtre-satir">
      <label class="form-alan" style="flex:1;min-width:230px">Kütüphaneden teknik seç
        <select id="teknikSec" class="secim">
          <?php
          $sonKategori = null;
          foreach ($teknikler as $t):
              if ($t['kategori'] !== $sonKategori):
                  if ($sonKategori !== null) { echo '</optgroup>'; }
                  echo '<optgroup label="' . e($t['kategori']) . '">';
                  $sonKategori = $t['kategori'];
              endif; ?>
          <option value="<?= (int)$t['id'] ?>"
                  data-ad="<?= e($t['ad']) ?>"
                  data-kategori="<?= e($t['kategori']) ?>"
                  data-sure="<?= (int)$t['sure_dk'] ?>"
                  data-kanit="<?= e($t['kanit_duzeyi']) ?>"
                  data-kanit-etiket="<?= e(KANIT_LABELS[$t['kanit_duzeyi']] ?? '') ?>">
            <?= e($t['ad']) ?> · <?= (int)$t['sure_dk'] ?> dk · Sev. <?= (int)$t['seviye'] ?>
          </option>
          <?php endforeach;
          if ($sonKategori !== null) { echo '</optgroup>'; } ?>
        </select>
      </label>
      <button type="button" class="btn btn-birincil" id="teknikEkleBtn">+ Plana Ekle</button>
    </div>

    <div class="sure-ozet">
      <span class="sure-metin" id="sureToplam">0 dk</span>
      <div class="sure-cubuk"><div class="sure-cubuk-dolu" id="sureCubukDolu" style="width:0"></div></div>
    </div>
    <div class="uyari-kutu" id="sureUyari" style="display:none">
      Planlanan toplam süre hedefi aşıyor. Bir tekniği çıkarmayı veya süreleri kısaltmayı düşünün.
    </div>

    <div class="bos-durum" id="planBosMesaj">Plan boş — yukarıdan teknik ekleyin.</div>
    <ul class="plan-liste" id="planListe">
      <?php foreach ($planli as $p): ?>
      <li draggable="true">
        <span class="plan-tutamac" title="Sürükleyerek sırala">☰</span>
        <input type="hidden" name="teknik_id[]" value="<?= (int)$p['teknik_id'] ?>">
        <div class="plan-bilgi">
          <span class="ad plan-ad"><?= e($p['ad']) ?></span>
          <span class="alan-ipucu plan-kategori"><?= e($p['kategori']) ?></span>
          <span class="rozet plan-kanit rozet-kanit-<?= e($p['kanit_duzeyi']) ?>"><?= e(KANIT_LABELS[$p['kanit_duzeyi']] ?? '') ?></span>
          <input type="text" name="uygulama_notu[]" class="girdi plan-not-girdi" maxlength="200"
                 placeholder="Uygulama notu (isteğe bağlı)" value="<?= e($p['uygulama_notu']) ?>">
        </div>
        <label class="form-alan" style="width:86px">dk
          <input type="number" name="sure_dk[]" class="girdi plan-sure-girdi" value="<?= (int)$p['sure_dk'] ?>" min="1" max="120">
        </label>
        <button type="button" class="btn btn-kucuk btn-tehlike plan-kaldir" title="Plandan çıkar">✕</button>
      </li>
      <?php endforeach; ?>
    </ul>

    <template id="planSablon">
      <li draggable="true">
        <span class="plan-tutamac" title="Sürükleyerek sırala">☰</span>
        <input type="hidden" name="teknik_id[]" value="">
        <div class="plan-bilgi">
          <span class="ad plan-ad"></span>
          <span class="alan-ipucu plan-kategori"></span>
          <span class="rozet plan-kanit"></span>
          <input type="text" name="uygulama_notu[]" class="girdi plan-not-girdi" maxlength="200"
                 placeholder="Uygulama notu (isteğe bağlı)" value="">
        </div>
        <label class="form-alan" style="width:86px">dk
          <input type="number" name="sure_dk[]" class="girdi plan-sure-girdi" value="10" min="1" max="120">
        </label>
        <button type="button" class="btn btn-kucuk btn-tehlike plan-kaldir" title="Plandan çıkar">✕</button>
      </li>
    </template>
  </div>

  <div class="form-butonlar">
    <button type="submit" class="btn btn-birincil"><?= $oturum ? 'Planı Kaydet' : 'Oturumu Planla' ?></button>
    <a class="btn btn-golge" href="<?= e(url($oturum ? 'oturum.php?id=' . $oturumId : 'oturumlar.php')) ?>">Vazgeç</a>
  </div>
</form>

<script>
// Grup seçilince tarih alanını grubun bir sonraki ders gününe getir (yalnız yeni planda)
(function () {
  var grupSec = document.getElementById('grupSec');
  var tarih = document.getElementById('tarihGirdi');
  if (grupSec && tarih) {
    grupSec.addEventListener('change', function () {
      var opt = grupSec.options[grupSec.selectedIndex];
      if (opt && opt.dataset.sonraki) { tarih.value = opt.dataset.sonraki; }
    });
  }
})();
</script>
<?php endif; ?>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
