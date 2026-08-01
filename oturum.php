<?php
/**
 * Oturum Kaydı — o günkü ders: yoklama listesi, işlenen teknikleri işaretle,
 * öğrenci başına kısa gözlem notu, oturum notu.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$oturum = session_get($id);
if (!$oturum) { not_found('Oturum bulunamadı.'); }

$planTeknikleri = session_techniques($id);
$mevcutYoklama = session_attendance($id);

// Yoklama listesi: grubun aktif öğrencileri + bu oturumda kaydı olan herkes
$ogrenciler = students_list((int)$oturum['grup_id'], 1);
$gosterilen = [];
foreach ($ogrenciler as $o) { $gosterilen[(int)$o['id']] = $o; }
if ($mevcutYoklama) {
    $eksikler = array_diff(array_keys($mevcutYoklama), array_keys($gosterilen));
    foreach ($eksikler as $oid) {
        $o = student_get((int)$oid);
        if ($o) { $gosterilen[(int)$oid] = $o; }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('oturum.php?id=' . $id);
    $durumlar = (array)($_POST['durum'] ?? []);
    $gozlemler = (array)($_POST['gozlem'] ?? []);
    $yoklama = [];
    foreach (array_keys($gosterilen) as $oid) {
        if (isset($durumlar[$oid])) {
            $yoklama[$oid] = ['durum' => (string)$durumlar[$oid], 'gozlem_notu' => (string)($gozlemler[$oid] ?? '')];
        }
    }
    $islendiler = (array)($_POST['islendi'] ?? []);
    $teknikNotlari = (array)($_POST['teknik_notu'] ?? []);
    $teknikKayit = [];
    foreach ($planTeknikleri as $p) {
        $tid = (int)$p['teknik_id'];
        $teknikKayit[$tid] = [
            'islendi'       => isset($islendiler[$tid]) ? 1 : 0,
            'uygulama_notu' => (string)($teknikNotlari[$tid] ?? ''),
        ];
    }
    session_record($id, $yoklama, $teknikKayit, (string)($_POST['notlar'] ?? ''));
    flash_set('basari', 'Oturum kaydedildi.');
    redirect('oturum.php?id=' . $id);
}

$kayitliMi = count($mevcutYoklama) > 0;
$planSure = array_sum(array_map(fn($p) => (int)$p['sure_dk'], $planTeknikleri));

$PAGE_TITLE = 'Oturum — ' . $oturum['grup_ad'] . ' ' . format_date_tr($oturum['tarih'], false);
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik">
  <h1>Oturum Kaydı</h1>
  <span class="rozet rozet-acik"><?= e($oturum['grup_ad']) ?></span>
  <span class="rozet rozet-gri"><?= e(format_date_tr($oturum['tarih'])) ?><?= $oturum['grup_saat'] ? ' · ' . e($oturum['grup_saat']) : '' ?></span>
  <span class="rozet rozet-gri">Hafta <?= (int)$oturum['hafta_no'] ?></span>
  <?= $kayitliMi
      ? '<span class="rozet rozet-tamam">Yoklama kaydedildi</span>'
      : ($oturum['tarih'] <= today() ? '<span class="rozet rozet-bekliyor">Yoklama bekliyor</span>' : '<span class="rozet rozet-gri">Planlandı</span>') ?>
  <?php if (!empty($oturum['protokol']) && isset(PROTOKOL_LABELS[$oturum['protokol']])): ?>
  <span class="rozet rozet-acik">🧭 <?= e(PROTOKOL_LABELS[$oturum['protokol']]) ?></span>
  <?php endif; ?>
  <div class="sag">
    <a class="btn btn-birincil" href="<?= e(url('metronom.php?akis=' . $id)) ?>"
       title="Plandaki teknikleri sırayla, süre sayacı ve zille çalıştır">🧭 Ders Akışını Stüdyoda Başlat</a>
    <?php if (!empty($oturum['protokol']) && isset(PROTOKOL_LABELS[$oturum['protokol']])): ?>
    <a class="btn btn-golge" href="<?= e(url('metronom.php?protokol=' . $oturum['protokol'])) ?>">
      🎛 Protokolü Stüdyoda Aç</a>
    <?php endif; ?>
    <a class="btn btn-golge" href="<?= e(url('plan.php?oturum_id=' . $id)) ?>">Planı düzenle</a>
  </div>
</div>

<form method="post" action="<?= e(url('oturum.php?id=' . $id)) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">

  <div class="kart">
    <div class="kart-baslik">
      <h2>İşlenen teknikler</h2>
      <span class="rozet rozet-gri">Plan: <?= count($planTeknikleri) ?> teknik · <?= $planSure ?> dk</span>
    </div>
    <?php if (!$planTeknikleri): ?>
      <div class="bos-durum">Bu oturumun planı boş. <a href="<?= e(url('plan.php?oturum_id=' . $id)) ?>">Plana teknik ekleyin</a>.</div>
    <?php else: ?>
    <div class="tablo-sar">
      <table class="tablo">
        <thead><tr><th style="width:70px">İşlendi</th><th>Teknik</th><th class="sayi" style="width:70px">Süre</th><th>Uygulama notu</th></tr></thead>
        <tbody>
          <?php foreach ($planTeknikleri as $p):
              $tid = (int)$p['teknik_id'];
              $isaretli = $p['islendi'] === null ? true : (int)$p['islendi'] === 1; ?>
          <tr>
            <td style="text-align:center">
              <input type="checkbox" name="islendi[<?= $tid ?>]" value="1" <?= $isaretli ? 'checked' : '' ?>
                     style="width:19px;height:19px;accent-color:var(--amber)" aria-label="<?= e($p['ad']) ?> işlendi">
            </td>
            <td>
              <strong><?= e($p['ad']) ?></strong> <?= kanit_rozet($p['kanit_duzeyi']) ?><br>
              <span class="alan-ipucu"><?= e($p['kategori']) ?><?= $p['malzeme'] ? ' · ' . e($p['malzeme']) : '' ?></span>
            </td>
            <td class="sayi"><?= (int)$p['sure_dk'] ?> dk</td>
            <td>
              <input type="text" name="teknik_notu[<?= $tid ?>]" class="girdi" maxlength="200"
                     aria-label="<?= e($p['ad']) ?> — uygulama notu"
                     value="<?= e($p['uygulama_notu']) ?>" placeholder="Tempo, uyarlama, gözlem…">
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div class="kart">
    <div class="kart-baslik">
      <h2>Yoklama ve gözlem notları</h2>
      <div class="sag">
        <button type="button" class="btn btn-kucuk btn-golge" id="tumKatildiBtn">Tümünü “katıldı” işaretle</button>
      </div>
    </div>
    <?php if (!$gosterilen): ?>
      <div class="bos-durum">Bu grupta aktif öğrenci yok.
        <a href="<?= e(url('ogrenciler.php?grup_id=' . (int)$oturum['grup_id'])) ?>">Öğrenci ekleyin</a>.</div>
    <?php else: ?>
    <div class="tablo-sar">
      <table class="tablo">
        <thead><tr><th>Öğrenci</th><th>Durum</th><th>Gözlem notu</th></tr></thead>
        <tbody>
          <?php foreach ($gosterilen as $oid => $o):
              $mevcut = $mevcutYoklama[$oid]['durum'] ?? 'katildi'; ?>
          <tr>
            <td>
              <a href="<?= e(url('ogrenci.php?id=' . (int)$oid)) ?>"><?= e($o['kod']) ?></a>
              <?= (int)$o['aktif'] !== 1 ? ' <span class="rozet rozet-gri">Pasif</span>' : '' ?>
            </td>
            <td>
              <span class="yoklama-secenek">
                <?php foreach (KATILIM_LABELS as $kod => $etiket): ?>
                <label><input type="radio" name="durum[<?= (int)$oid ?>]" value="<?= e($kod) ?>"
                       <?= $mevcut === $kod ? 'checked' : '' ?>><?= e($etiket) ?></label>
                <?php endforeach; ?>
              </span>
            </td>
            <td>
              <input type="text" name="gozlem[<?= (int)$oid ?>]" class="girdi" maxlength="300"
                     aria-label="<?= e($k['kod'] ?? 'Katılımcı') ?> — gözlem notu"
                     value="<?= e($mevcutYoklama[$oid]['gozlem_notu'] ?? '') ?>"
                     placeholder="Kısa gözlem (veli raporunda görünebilir)">
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="alan-ipucu">Gözlem notları veli raporuna girebilir: ne yapıldığını yazın, sonuç/iddia dili kullanmayın.
       Rapor oluşturulurken uygun olmayan ifadeler için uyarılırsınız.</p>
    <?php endif; ?>
  </div>

  <div class="kart">
    <h2>Oturum notu</h2>
    <textarea name="notlar" class="girdi" rows="3" maxlength="1500"
              placeholder="Genel akış, grup dinamiği, gelecek oturum için hatırlatma…"><?= e($oturum['notlar']) ?></textarea>
  </div>

  <div class="form-butonlar">
    <button type="submit" class="btn btn-birincil">Oturumu Kaydet</button>
    <a class="btn btn-golge" href="<?= e(url('oturumlar.php')) ?>">Oturum listesine dön</a>
  </div>
</form>

<div class="kart">
  <h2>Tehlikeli bölge</h2>
  <form method="post" action="<?= e(url('oturum-sil.php')) ?>"
        data-onay="Bu oturumu, planını ve yoklama kayıtlarını silmek üzeresiniz. Geri alınamaz. Emin misiniz?">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <button type="submit" class="btn btn-tehlike">Oturumu Sil</button>
  </form>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
