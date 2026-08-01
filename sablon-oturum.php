<?php
/**
 * Şablon oturumu düzenleyici — ders planı ekranıyla aynı sürükle-bırak
 * altyapısını kullanır (app.js #planListe). Hafta, oturum adı, hedef ve
 * teknik listesi burada düzenlenir; kayıt şablona yazılır.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$oturum = template_session_get($id);
if (!$oturum) { not_found('Şablon oturumu bulunamadı.'); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('sablon-oturum.php?id=' . $id);
    $ids = (array)($_POST['teknik_id'] ?? []);
    $sureler = (array)($_POST['sure_dk'] ?? []);
    $notlar = (array)($_POST['uygulama_notu'] ?? []);
    $items = [];
    foreach (array_values($ids) as $i => $tid) {
        $items[] = [
            'teknik_id'     => (int)$tid,
            'sure_dk'       => (int)($sureler[$i] ?? 10),
            'uygulama_notu' => (string)($notlar[$i] ?? ''),
        ];
    }
    $res = template_session_save($id, $_POST, $items);
    if ($res['ok']) {
        // Haftanın ev görevleri şablon + hafta düzeyinde tutulur (A/B ortak)
        template_home_tasks_set((int)$oturum['sablon_id'],
            max(1, min(52, (int)($_POST['hafta_no'] ?? $oturum['hafta_no']))),
            (array)($_POST['ev_gorevleri'] ?? []));
        flash_set('basari', 'Şablon oturumu kaydedildi.');
        redirect('sablonlar.php?id=' . (int)$oturum['sablon_id']);
    }
    flash_set('hata', $res['error']);
    redirect('sablon-oturum.php?id=' . $id);
}

$teknikler = techniques_list(['aktif' => 1]);
$planli = $oturum['teknikler'];
$evCalismalari = home_exercises_list(true);
$haftaGorevleri = template_home_tasks((int)$oturum['sablon_id'])[(int)$oturum['hafta_no']] ?? [];
$seciliGorevler = array_map('intval', array_column($haftaGorevleri, 'calisma_id'));

$PAGE_TITLE = 'Şablon Oturumu — ' . $oturum['sablon_ad'];
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik">
  <h1>Şablon Oturumu Düzenle</h1>
  <span class="rozet rozet-acik"><?= e($oturum['sablon_ad']) ?></span>
  <span class="rozet rozet-gri">Hafta <?= (int)$oturum['hafta_no'] ?><?= e($oturum['oturum_adi']) ?></span>
  <div class="sag">
    <a class="btn btn-golge" href="<?= e(url('sablonlar.php?id=' . (int)$oturum['sablon_id'])) ?>"><span class="emoji-sus" aria-hidden="true">←</span> Şablona dön</a>
  </div>
</div>

<form method="post" action="<?= e(url('sablon-oturum.php?id=' . $id)) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">

  <div class="kart">
    <h2>Oturum bilgileri</h2>
    <div class="form-grid">
      <label class="form-alan">Hafta no
        <input type="number" name="hafta_no" class="girdi" value="<?= (int)$oturum['hafta_no'] ?>" min="1" max="52" required>
      </label>
      <label class="form-alan">Oturum adı
        <select name="oturum_adi" class="secim">
          <?php foreach (['A', 'B', 'C'] as $ad): ?>
          <option value="<?= $ad ?>" <?= $oturum['oturum_adi'] === $ad ? 'selected' : '' ?>><?= $ad ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-alan">Hedef süre (dk)
        <input type="number" id="hedefSure" class="girdi" value="<?= (int)$oturum['sablon_sure'] ?>" min="10" max="180">
        <span class="alan-ipucu">Şablonun oturum süresi; yalnız uyarı içindir.</span>
      </label>
      <label class="form-alan">Haftanın protokolü
        <select name="protokol" class="secim">
          <option value="">— Yok —</option>
          <?php foreach (PROTOKOL_LABELS as $pKod => $pAd): ?>
          <option value="<?= e($pKod) ?>" <?= ($oturum['protokol'] ?? '') === $pKod ? 'selected' : '' ?>><?= e($pAd) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="alan-ipucu">Şablon uygulanınca oturuma taşınır.</span>
      </label>
      <label class="form-alan form-genis">Haftanın hedefi
        <input type="text" name="hedef" class="girdi" value="<?= e($oturum['hedef']) ?>" maxlength="160"
               placeholder="Örn. Başla–dur, güvenli katılım">
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

  <div class="kart">
    <div class="kart-baslik">
      <h2><span class="emoji-sus" aria-hidden="true">🏠</span> Haftanın ev görevleri</h2>
      <span class="alan-ipucu">Haftaya bağlıdır (A/B ortak). Şablon gruba uygulanınca bu görevler
        o haftanın tarih aralığıyla öğrencilere otomatik ödev atanır.</span>
    </div>
    <div class="ev-gorev-secim">
      <?php foreach ($evCalismalari as $c): ?>
      <label class="ev-gorev-kutu">
        <input type="checkbox" name="ev_gorevleri[]" value="<?= (int)$c['id'] ?>"
               <?= in_array((int)$c['id'], $seciliGorevler, true) ? 'checked' : '' ?>>
        <span>
          <?= e($c['ad']) ?>
          <small class="alan-ipucu"><?= e(EV_TUR_LABELS[$c['tur']] ?? $c['tur']) ?>
            · <?= e(KITLE_LABELS[$c['kitle']] ?? $c['kitle']) ?><?= $c['hafta_onerisi'] ? ' · öneri H' . (int)$c['hafta_onerisi'] : '' ?></small>
        </span>
      </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="form-butonlar">
    <button type="submit" class="btn btn-birincil">Şablon Oturumunu Kaydet</button>
    <a class="btn btn-golge" href="<?= e(url('sablonlar.php?id=' . (int)$oturum['sablon_id'])) ?>">Vazgeç</a>
  </div>
</form>
<style>
  .ev-gorev-secim { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: .35rem .8rem; }
  .ev-gorev-kutu { display: flex; gap: .45rem; align-items: flex-start; border: 1px solid var(--cizgi);
                   border-radius: 8px; padding: .4rem .55rem; background: #fff; cursor: pointer; }
  .ev-gorev-kutu input { margin-top: .2rem; }
  .ev-gorev-kutu small { display: block; }
  .ev-gorev-kutu:has(input:checked) { border-color: var(--amber); background: var(--amber-acik); }
</style>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
