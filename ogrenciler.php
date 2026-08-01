<?php
/** Öğrenciler — tablo, filtre (grup/aktiflik), yeni öğrenci. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('ogrenciler.php');
    $res = student_save($_POST);
    if ($res['ok']) {
        flash_set('basari', 'Öğrenci kaydedildi.');
        redirect('ogrenci.php?id=' . $res['id']);
    }
    flash_set('hata', $res['error']);
    set_old($_POST);
    redirect('ogrenciler.php');
}

$grupFiltre = isset($_GET['grup_id']) && $_GET['grup_id'] !== '' ? (int)$_GET['grup_id'] : null;
$aktifFiltre = isset($_GET['aktif']) && $_GET['aktif'] !== '' ? (int)$_GET['aktif'] : null;
$ogrenciler = students_list($grupFiltre, $aktifFiltre);
$gruplar = groups_list();

$PAGE_TITLE = 'Öğrenciler';
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik"><h1>Öğrenciler</h1></div>

<div class="kart">
  <form method="get" action="<?= e(url('ogrenciler.php')) ?>" class="filtre-satir">
    <label class="form-alan">Grup
      <select name="grup_id" class="secim">
        <option value="">Tümü</option>
        <?php foreach ($gruplar as $g): ?>
        <option value="<?= (int)$g['id'] ?>" <?= $grupFiltre === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['ad']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan">Durum
      <select name="aktif" class="secim">
        <option value="">Tümü</option>
        <option value="1" <?= $aktifFiltre === 1 ? 'selected' : '' ?>>Aktif</option>
        <option value="0" <?= $aktifFiltre === 0 ? 'selected' : '' ?>>Pasif</option>
      </select>
    </label>
    <button type="submit" class="btn btn-golge">Filtrele</button>
    <a class="btn btn-golge" href="<?= e(url('ogrenciler.php')) ?>">Temizle</a>
  </form>

  <?php if (!$ogrenciler): ?>
    <div class="bos-durum">Kayıt bulunamadı<?= $grupFiltre || $aktifFiltre !== null ? ' — filtreyi genişletmeyi deneyin' : ' — aşağıdan ilk öğrenciyi ekleyin' ?>.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th scope="col">Kod</th><th scope="col">Doğum yılı</th><th scope="col">Grup</th><th scope="col">Veli notu</th><th scope="col">Kayıt</th><th scope="col">Durum</th></tr></thead>
      <tbody>
        <?php foreach ($ogrenciler as $o): ?>
        <tr>
          <td><a href="<?= e(url('ogrenci.php?id=' . (int)$o['id'])) ?>"><?= e($o['kod']) ?></a></td>
          <td><?= $o['dogum_yili'] ? (int)$o['dogum_yili'] : '—' ?></td>
          <td><?= $o['grup_adlari'] ? e($o['grup_adlari']) : '<span class="rozet rozet-gri">Grupsuz</span>' ?></td>
          <td><?= e(mb_strimwidth((string)$o['veli_notu'], 0, 60, '…')) ?></td>
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
  <h2>Yeni öğrenci</h2>
  <p class="alan-ipucu">Öğrenci <strong>kod / takma ad</strong> ile kaydedilir; açık kimlik bilgisi tutulmaz.</p>
  <form method="post" action="<?= e(url('ogrenciler.php')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <label class="form-alan">Kod (takma ad)
        <input type="text" name="kod" class="girdi" value="<?= e(old('kod')) ?>" maxlength="40" required
               placeholder="Örn. K-07 veya Deniz">
      </label>
      <label class="form-alan">Doğum yılı
        <input type="number" name="dogum_yili" class="girdi" value="<?= e(old('dogum_yili')) ?>"
               min="1920" max="<?= (int)now()->format('Y') ?>" placeholder="Örn. 2015">
      </label>
      <label class="form-alan">İlk ders / grup
        <select name="grup_id" class="secim">
          <option value="">Daha sonra seç</option>
          <?php foreach ($gruplar as $g): if ((int)$g['aktif'] !== 1) continue; ?>
          <?php if (($g['tur'] ?? 'grup') === 'ozel' && (int)$g['uyelik_sayisi'] >= 1) continue; ?>
          <option value="<?= (int)$g['id'] ?>" <?= (string)old('grup_id', (string)($grupFiltre ?? '')) === (string)$g['id'] ? 'selected' : '' ?>><?= e($g['ad']) ?> — <?= e(GRUP_TUR_LABELS[$g['tur'] ?? 'grup']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="alan-ipucu">Kaydettikten sonra kişiyi başka özel veya grup derslerine de ekleyebilirsiniz.</span>
      </label>
      <label class="form-alan form-genis">Veli notu
        <textarea name="veli_notu" class="girdi" maxlength="500"
                  placeholder="İletişim tercihi, dikkat edilecekler vb."><?= e(old('veli_notu')) ?></textarea>
      </label>
    </div>
    <div class="form-butonlar">
      <button type="submit" class="btn btn-birincil">Öğrenci Ekle</button>
    </div>
  </form>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
