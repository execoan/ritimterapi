<?php
/** Grup detayı — bilgiler, öğrenciler, oturum geçmişi. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$grup = group_get($id);
if (!$grup) { not_found('Grup bulunamadı.'); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('grup.php?id=' . $id);
    $res = group_save($_POST, $id);
    flash_set($res['ok'] ? 'basari' : 'hata', $res['ok'] ? 'Grup güncellendi.' : $res['error']);
    redirect('grup.php?id=' . $id);
}

$ogrenciler = students_list($id);
$oturumlar = sessions_list($id);

$PAGE_TITLE = $grup['ad'];
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik">
  <h1><?= e($grup['ad']) ?></h1>
  <?= (int)$grup['aktif'] === 1 ? '<span class="rozet rozet-tamam">Aktif</span>' : '<span class="rozet rozet-gri">Pasif</span>' ?>
  <div class="sag">
    <a class="btn btn-birincil" href="<?= e(url('plan.php?grup_id=' . $id)) ?>">+ Bu gruba oturum planla</a>
    <a class="btn btn-golge" href="<?= e(url('rapor-donemlik.php?grup_id=' . $id)) ?>">Dönem raporu</a>
  </div>
</div>

<div class="kart">
  <h2>Grup bilgileri</h2>
  <form method="post" action="<?= e(url('grup.php?id=' . $id)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="form-grid">
      <label class="form-alan">Grup adı
        <input type="text" name="ad" class="girdi" value="<?= e($grup['ad']) ?>" maxlength="80" required>
      </label>
      <label class="form-alan">Yaş aralığı
        <input type="text" name="yas_araligi" class="girdi" value="<?= e($grup['yas_araligi']) ?>" maxlength="40">
      </label>
      <label class="form-alan">Ders günü
        <select name="gun" class="secim">
          <?php foreach (GUNLER as $no => $ad): ?>
          <option value="<?= $no ?>" <?= (int)$grup['gun'] === $no ? 'selected' : '' ?>><?= e($ad) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-alan">Saat
        <input type="time" name="saat" class="girdi" value="<?= e($grup['saat']) ?>">
      </label>
      <label class="form-alan">Başlangıç tarihi
        <input type="date" name="baslangic_tarihi" class="girdi" value="<?= e($grup['baslangic_tarihi']) ?>">
      </label>
      <label class="form-alan">Durum
        <input type="hidden" name="aktif" value="0">
        <span class="onay-kutu"><input type="checkbox" name="aktif" value="1" <?= (int)$grup['aktif'] === 1 ? 'checked' : '' ?>> Grup aktif</span>
      </label>
    </div>
    <div class="form-butonlar">
      <button type="submit" class="btn btn-birincil">Kaydet</button>
    </div>
  </form>
</div>

<div class="kart">
  <div class="kart-baslik">
    <h2>Öğrenciler</h2>
    <span class="rozet rozet-acik"><?= count($ogrenciler) ?> öğrenci</span>
    <div class="sag"><a class="btn btn-kucuk btn-golge" href="<?= e(url('ogrenciler.php?grup_id=' . $id)) ?>">Öğrenci ekle / yönet</a></div>
  </div>
  <?php if (!$ogrenciler): ?>
    <div class="bos-durum">Bu grupta öğrenci yok. <a href="<?= e(url('ogrenciler.php')) ?>">Öğrenciler</a> sayfasından ekleyin.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th>Kod</th><th>Doğum yılı</th><th>Kayıt tarihi</th><th>Durum</th></tr></thead>
      <tbody>
        <?php foreach ($ogrenciler as $o): ?>
        <tr>
          <td><a href="<?= e(url('ogrenci.php?id=' . (int)$o['id'])) ?>"><?= e($o['kod']) ?></a></td>
          <td><?= $o['dogum_yili'] ? (int)$o['dogum_yili'] : '—' ?></td>
          <td><?= e(format_date_tr($o['kayit_tarihi'], false)) ?></td>
          <td><?= (int)$o['aktif'] === 1 ? '<span class="rozet rozet-tamam">Aktif</span>' : '<span class="rozet rozet-gri">Pasif</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="kart">
  <div class="kart-baslik">
    <h2>Oturum geçmişi</h2>
    <span class="rozet rozet-acik"><?= count($oturumlar) ?> oturum</span>
  </div>
  <?php if (!$oturumlar): ?>
    <div class="bos-durum">Henüz oturum yok. <a href="<?= e(url('plan.php?grup_id=' . $id)) ?>">İlk oturumu planlayın</a>.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th>Tarih</th><th>Hafta</th><th class="sayi">Teknik</th><th class="sayi">Plan süresi</th><th>Yoklama</th></tr></thead>
      <tbody>
        <?php foreach ($oturumlar as $s): ?>
        <tr>
          <td><a href="<?= e(url('oturum.php?id=' . (int)$s['id'])) ?>"><?= e(format_date_tr($s['tarih'])) ?></a></td>
          <td>Hafta <?= (int)$s['hafta_no'] ?></td>
          <td class="sayi"><?= (int)$s['teknik_sayisi'] ?></td>
          <td class="sayi"><?= (int)$s['plan_sure'] ?> dk</td>
          <td>
            <?php if ((int)$s['yoklama_sayisi'] > 0): ?>
              <span class="rozet rozet-tamam"><?= (int)$s['gelen_sayisi'] ?>/<?= (int)$s['yoklama_sayisi'] ?> geldi</span>
            <?php elseif ($s['tarih'] <= today()): ?>
              <span class="rozet rozet-bekliyor">Bekliyor</span>
            <?php else: ?>
              <span class="rozet rozet-gri">Planlandı</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="kart">
  <h2>Tehlikeli bölge</h2>
  <p class="alan-ipucu">Grubu silmek, bu gruba ait <strong>tüm oturum ve yoklama kayıtlarını</strong> da siler.
     Öğrenciler silinmez, grupsuz kalır. Geri alınamaz.</p>
  <form method="post" action="<?= e(url('grup-sil.php')) ?>"
        data-onay="<?= e($grup['ad']) ?> grubunu ve tüm oturum geçmişini silmek üzeresiniz. Bu işlem geri alınamaz. Emin misiniz?">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <button type="submit" class="btn btn-tehlike">Grubu Sil</button>
  </form>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
