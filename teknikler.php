<?php
/** Teknik Kütüphanesi — tablo, filtreler, kanıt düzeyi rozeti, yeni teknik. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('teknikler.php');
    $res = technique_save($_POST);
    if ($res['ok']) {
        flash_set('basari', 'Teknik kütüphaneye eklendi.');
        redirect('teknik.php?id=' . $res['id']);
    }
    flash_set('hata', $res['error']);
    set_old($_POST);
    redirect('teknikler.php');
}

$f = [
    'kategori' => trim((string)($_GET['kategori'] ?? '')),
    'seviye'   => trim((string)($_GET['seviye'] ?? '')),
    'kanit'    => trim((string)($_GET['kanit'] ?? '')),
    'q'        => trim((string)($_GET['q'] ?? '')),
    'aktif'    => trim((string)($_GET['aktif'] ?? '')),
];
$teknikler = techniques_list($f);
$kategoriler = technique_categories();

$PAGE_TITLE = 'Teknik Kütüphanesi';
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik">
  <h1>Teknik Kütüphanesi</h1>
  <div class="sag">
    <a class="btn btn-golge" href="<?= e(url('calismalar.php')) ?>"><span class="emoji-sus" aria-hidden="true">📚</span> Akademik Çalışmalar</a>
  </div>
</div>

<div class="kart">
  <form method="get" action="<?= e(url('teknikler.php')) ?>" class="filtre-satir">
    <label class="form-alan">Ara
      <input type="search" name="q" class="girdi" value="<?= e($f['q']) ?>" placeholder="Ad, beceri, açıklama…">
    </label>
    <label class="form-alan">Kategori
      <select name="kategori" class="secim">
        <option value="">Tümü</option>
        <?php foreach ($kategoriler as $k): ?>
        <option value="<?= e($k) ?>" <?= $f['kategori'] === $k ? 'selected' : '' ?>><?= e($k) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan">Seviye
      <select name="seviye" class="secim">
        <option value="">Tümü</option>
        <?php foreach (SEVIYE_LABELS as $no => $ad): ?>
        <option value="<?= $no ?>" <?= $f['seviye'] === (string)$no ? 'selected' : '' ?>><?= $no ?> — <?= e($ad) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan">Kanıt düzeyi
      <select name="kanit" class="secim">
        <option value="">Tümü</option>
        <?php foreach (KANIT_LABELS as $kod => $ad): ?>
        <option value="<?= e($kod) ?>" <?= $f['kanit'] === $kod ? 'selected' : '' ?>><?= e($ad) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="btn btn-golge">Filtrele</button>
    <a class="btn btn-golge" href="<?= e(url('teknikler.php')) ?>">Temizle</a>
  </form>

  <?php if (!$teknikler): ?>
    <div class="bos-durum">Filtreye uyan teknik bulunamadı.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th scope="col">Teknik</th><th scope="col">Kategori</th><th scope="col">Seviye</th><th scope="col" class="sayi">Süre</th><th scope="col">Hedef beceri</th><th scope="col">Kanıt</th><th scope="col">Dayanak</th><th scope="col" class="sayi">İşlendi</th></tr></thead>
      <tbody>
        <?php foreach ($teknikler as $t): ?>
        <tr>
          <td>
            <a href="<?= e(url('teknik.php?id=' . (int)$t['id'])) ?>"><?= e($t['ad']) ?></a>
            <?= (int)$t['aktif'] !== 1 ? ' <span class="rozet rozet-gri">Pasif</span>' : '' ?>
          </td>
          <td><?= e($t['kategori']) ?></td>
          <td><?= (int)$t['seviye'] ?> — <?= e(SEVIYE_LABELS[(int)$t['seviye']] ?? '') ?></td>
          <td class="sayi"><?= (int)$t['sure_dk'] ?> dk</td>
          <td><?= e($t['hedef_beceri']) ?></td>
          <td><?= kanit_rozet($t['kanit_duzeyi']) ?></td>
          <td>
            <?php if ((int)$t['calisma_sayisi'] > 0): ?>
              <span class="rozet rozet-acik" title="Bağlı akademik çalışma sayısı"><span class="emoji-sus" aria-hidden="true">📚</span> <?= (int)$t['calisma_sayisi'] ?></span>
            <?php else: ?>
              <span class="rozet rozet-gri" title="Bağlı akademik çalışma yok">yok</span>
            <?php endif; ?>
          </td>
          <td class="sayi"><?= (int)$t['islenme_sayisi'] ?>×</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="kart">
  <h2>Yeni teknik</h2>
  <div class="bilgi-kutu">
    <strong>Kanıt düzeyi zorunludur.</strong> Amaç, içeriğe dürüst bakmak: iddia edilen etki literatürde ne kadar
    destekli? <em>“Kanıt yok”</em> etiketi de meşrudur — etkinlik keyifli ve pedagojik olarak değerli olabilir;
    bilimsel iddia taşımaması onu değersizleştirmez.
  </div>
  <form method="post" action="<?= e(url('teknikler.php')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <label class="form-alan">Teknik adı
        <input type="text" name="ad" class="girdi" value="<?= e(old('ad')) ?>" maxlength="80" required>
      </label>
      <label class="form-alan">Kategori
        <input type="text" name="kategori" class="girdi" value="<?= e(old('kategori')) ?>" maxlength="60" required
               list="kategoriListesi" placeholder="Mevcut kategori seçin veya yenisini yazın">
        <datalist id="kategoriListesi">
          <?php foreach ($kategoriler as $k): ?><option value="<?= e($k) ?>"></option><?php endforeach; ?>
        </datalist>
      </label>
      <label class="form-alan">Enstrüman
        <input type="text" name="enstruman" class="girdi" value="<?= e(old('enstruman')) ?>" maxlength="60"
               placeholder="Örn. Davul / pad">
      </label>
      <label class="form-alan">Seviye
        <select name="seviye" class="secim">
          <?php foreach (SEVIYE_LABELS as $no => $ad): ?>
          <option value="<?= $no ?>" <?= (string)old('seviye', '1') === (string)$no ? 'selected' : '' ?>><?= $no ?> — <?= e($ad) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-alan">Süre (dk)
        <input type="number" name="sure_dk" class="girdi" value="<?= e(old('sure_dk', '10')) ?>" min="1" max="120">
      </label>
      <label class="form-alan">Kanıt düzeyi
        <select name="kanit_duzeyi" class="secim" required>
          <option value="">— Seçin (zorunlu) —</option>
          <?php foreach (KANIT_LABELS as $kod => $ad): ?>
          <option value="<?= e($kod) ?>" <?= old('kanit_duzeyi') === $kod ? 'selected' : '' ?>><?= e($ad) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-alan">Hedef beceri
        <input type="text" name="hedef_beceri" class="girdi" value="<?= e(old('hedef_beceri')) ?>" maxlength="120"
               placeholder="Örn. Senkronizasyon, zamanlama">
      </label>
      <label class="form-alan">Malzeme
        <input type="text" name="malzeme" class="girdi" value="<?= e(old('malzeme')) ?>" maxlength="160">
      </label>
      <label class="form-alan form-genis">Açıklama (nasıl uygulanır)
        <textarea name="aciklama" class="girdi" maxlength="1500"><?= e(old('aciklama')) ?></textarea>
      </label>
      <label class="form-alan form-genis">Kaynak
        <input type="text" name="kaynak" class="girdi" value="<?= e(old('kaynak')) ?>" maxlength="300"
               placeholder='Makale künyesi veya "pedagojik gelenek"'>
      </label>
    </div>
    <div class="form-butonlar">
      <button type="submit" class="btn btn-birincil">Teknik Ekle</button>
    </div>
  </form>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
