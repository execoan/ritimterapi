<?php
/** Gruplar — liste + yeni grup. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('gruplar.php');
    $res = group_save($_POST);
    if ($res['ok']) {
        flash_set('basari', 'Grup oluşturuldu.');
        redirect('grup.php?id=' . $res['id']);
    }
    flash_set('hata', $res['error']);
    set_old($_POST);
    redirect('gruplar.php');
}

$gruplar = groups_list();

$PAGE_TITLE = 'Gruplar';
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik"><h1>Dersler ve gruplar</h1></div>

<div class="kart">
  <div class="kart-baslik">
    <h2>Ders listesi</h2>
    <span class="rozet rozet-acik"><?= count($gruplar) ?> kayıt</span>
  </div>
  <?php if (!$gruplar): ?>
    <div class="bos-durum">Henüz grup yok — aşağıdan ilk grubu ekleyin.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th scope="col">Ders / grup</th><th scope="col">Tür</th><th scope="col">Yaş aralığı</th><th scope="col">Ders günü</th><th scope="col" class="sayi">Katılımcı</th><th scope="col" class="sayi">Oturum</th><th scope="col">Durum</th></tr></thead>
      <tbody>
        <?php foreach ($gruplar as $g): ?>
        <tr>
          <td><a href="<?= e(url('grup.php?id=' . (int)$g['id'])) ?>"><?= e($g['ad']) ?></a></td>
          <td><span class="rozet rozet-acik"><?= e(GRUP_TUR_LABELS[$g['tur'] ?? 'grup']) ?></span></td>
          <td><?= e($g['yas_araligi'] ?: '—') ?></td>
          <td><?= e(GUNLER[(int)$g['gun']] ?? '—') ?><?= $g['saat'] ? ' ' . e($g['saat']) : '' ?></td>
          <td class="sayi"><?= (int)$g['ogrenci_sayisi'] ?></td>
          <td class="sayi"><?= (int)$g['oturum_sayisi'] ?></td>
          <td><?= (int)$g['aktif'] === 1 ? '<span class="rozet rozet-tamam">Aktif</span>' : '<span class="rozet rozet-gri">Pasif</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="kart">
  <h2>Yeni ders / grup</h2>
  <form method="post" action="<?= e(url('gruplar.php')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <label class="form-alan">Ders / grup adı
        <input type="text" name="ad" class="girdi" value="<?= e(old('ad')) ?>" maxlength="80" required
               placeholder="Örn. Çarşamba 8–11 yaş">
      </label>
      <label class="form-alan">Ders türü
        <select name="tur" class="secim">
          <?php foreach (GRUP_TUR_LABELS as $tur => $etiket): ?>
          <option value="<?= e($tur) ?>" <?= (string)old('tur', 'grup') === $tur ? 'selected' : '' ?>><?= e($etiket) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="alan-ipucu">Özel ders yalnızca bir aktif katılımcı kabul eder.</span>
      </label>
      <label class="form-alan">Yaş aralığı
        <input type="text" name="yas_araligi" class="girdi" value="<?= e(old('yas_araligi')) ?>" maxlength="40"
               placeholder="Örn. 8–11">
      </label>
      <label class="form-alan">Ders günü
        <select name="gun" class="secim" required>
          <?php foreach (GUNLER as $no => $ad): ?>
          <option value="<?= $no ?>" <?= (string)old('gun', '3') === (string)$no ? 'selected' : '' ?>><?= e($ad) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-alan">Saat
        <input type="time" name="saat" class="girdi" value="<?= e(old('saat')) ?>">
      </label>
      <label class="form-alan">Başlangıç tarihi
        <input type="date" name="baslangic_tarihi" class="girdi" value="<?= e(old('baslangic_tarihi', today())) ?>">
        <span class="alan-ipucu">Hafta numaraları bu tarihten hesaplanır.</span>
      </label>
    </div>
    <div class="form-butonlar">
      <button type="submit" class="btn btn-birincil">Ders Ekle</button>
    </div>
  </form>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
