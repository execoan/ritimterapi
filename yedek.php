<?php
/**
 * Yedekleme — veritabanının tutarlı kopyasını indir / geri yükle.
 * Kopya VACUUM INTO ile alınır (WAL içeriği dahil, uygulama açıkken güvenli).
 * Otomatik yedekler: her gün ilk istekte storage/yedek/otomatik-*.sqlite (son 10).
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$yedekDizin = backup_dir();
$dbDosya = APP_DIR . '/storage/ritim.sqlite';

/** storage/yedek içindeki bir dosya adını güvenle doğrular. */
function yedek_dosya_dogrula(string $ad): ?string
{
    if (!preg_match('/^(otomatik|oncesi)-[0-9\-]{8,17}\.sqlite$/', $ad)) { return null; }
    $yol = backup_dir() . '/' . $ad;
    return is_file($yol) ? $yol : null;
}

/** Yüklenen/seçilen dosyanın gerçekten bu uygulamanın SQLite yedeği olduğunu doğrular. */
function yedek_icerik_dogrula(string $yol): ?string
{
    $bas = (string)@file_get_contents($yol, false, null, 0, 16);
    if ($bas !== "SQLite format 3\0") { return 'Dosya bir SQLite veritabanı değil.'; }
    try {
        $pdo = new PDO('sqlite:' . $yol, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $adet = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master
                                   WHERE type = 'table' AND name IN ('gruplar','ogrenciler','teknikler')")->fetchColumn();
        $pdo = null;
        if ($adet < 3) { return 'Dosya bir RitimTerapi yedeği görünmüyor (çekirdek tablolar yok).'; }
    } catch (Throwable $e) {
        return 'Dosya açılamadı: bozuk olabilir.';
    }
    return null;
}

/** Doğrulanmış kaynağı canlı veritabanının yerine koyar (öncesinde emniyet yedeği). */
function yedek_geri_yukle(string $kaynak): ?string
{
    $hata = yedek_icerik_dogrula($kaynak);
    if ($hata !== null) { return $hata; }
    $emniyet = backup_dir() . '/oncesi-' . date('Ymd-His') . '.sqlite';
    if (!backup_snapshot($emniyet)) { return 'Emniyet yedeği alınamadı; geri yükleme durduruldu.'; }
    db_close();
    @unlink(APP_DIR . '/storage/ritim.sqlite-wal');
    @unlink(APP_DIR . '/storage/ritim.sqlite-shm');
    if (!@copy($kaynak, APP_DIR . '/storage/ritim.sqlite')) {
        return 'Veritabanı dosyası değiştirilemedi (dosya kilitli olabilir).';
    }
    return null;
}

/* ---- İndirme (GET, salt okunur) ---- */
if (($_GET['islem'] ?? '') === 'indir') {
    $ad = (string)($_GET['dosya'] ?? '');
    if ($ad !== '') {
        $yol = yedek_dosya_dogrula($ad);
        if (!$yol) { not_found('Yedek dosyası bulunamadı.'); }
        $indirAd = $ad;
        $sil = false;
    } else {
        $yol = $yedekDizin . '/indir-' . bin2hex(random_bytes(4)) . '.sqlite';
        if (!backup_snapshot($yol)) {
            flash_set('hata', 'Yedek kopyası alınamadı.');
            redirect('yedek.php');
        }
        $indirAd = 'ritim-yedek-' . date('Y-m-d-Hi') . '.sqlite';
        $sil = true;
    }
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($yol));
    header('Content-Disposition: attachment; filename="' . $indirAd . '"');
    header('Cache-Control: no-store');
    readfile($yol);
    if ($sil) { @unlink($yol); }
    exit;
}

/* ---- Geri yükleme (POST) ---- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('yedek.php');
    $islem = (string)($_POST['islem'] ?? '');

    if ($islem === 'geri_yukle') {
        if (($_POST['onay'] ?? '') !== '1') {
            flash_set('hata', 'Geri yükleme için onay kutusunu işaretleyin.');
            redirect('yedek.php');
        }
        $dosya = $_FILES['dosya'] ?? null;
        if (!$dosya || ($dosya['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash_set('hata', 'Yedek dosyası seçilmedi veya yüklenemedi.');
            redirect('yedek.php');
        }
        if ((int)$dosya['size'] > 200 * 1024 * 1024) {
            flash_set('hata', 'Dosya beklenmedik ölçüde büyük (200 MB sınırı).');
            redirect('yedek.php');
        }
        $tmp = $yedekDizin . '/gelen-' . bin2hex(random_bytes(4)) . '.sqlite';
        if (!move_uploaded_file($dosya['tmp_name'], $tmp)) {
            flash_set('hata', 'Yüklenen dosya işlenemedi.');
            redirect('yedek.php');
        }
        $hata = yedek_geri_yukle($tmp);
        @unlink($tmp);
        flash_set($hata === null ? 'basari' : 'hata',
            $hata === null
                ? 'Yedek geri yüklendi. Önceki durumun emniyet kopyası da yedekler listesine eklendi.'
                : $hata);
        redirect('yedek.php');
    }

    if ($islem === 'geri_yukle_oto') {
        if (($_POST['onay'] ?? '') !== '1') {
            flash_set('hata', 'Geri yükleme için onay kutusunu işaretleyin.');
            redirect('yedek.php');
        }
        $yol = yedek_dosya_dogrula((string)($_POST['dosya'] ?? ''));
        if (!$yol) {
            flash_set('hata', 'Yedek dosyası bulunamadı.');
            redirect('yedek.php');
        }
        $hata = yedek_geri_yukle($yol);
        flash_set($hata === null ? 'basari' : 'hata',
            $hata === null ? 'Sunucudaki yedek geri yüklendi.' : $hata);
        redirect('yedek.php');
    }

    if ($islem === 'yedek_sil') {
        $yol = yedek_dosya_dogrula((string)($_POST['dosya'] ?? ''));
        if ($yol) { @unlink($yol); flash_set('basari', 'Yedek dosyası silindi.'); }
        redirect('yedek.php');
    }

    redirect('yedek.php');
}

/* ---- Görünüm ---- */
$dbBoyut = is_file($dbDosya) ? filesize($dbDosya) : 0;
$yedekler = [];
$dosyalar = array_merge(glob($yedekDizin . '/otomatik-*.sqlite') ?: [],
                        glob($yedekDizin . '/oncesi-*.sqlite') ?: []);
foreach ($dosyalar as $y) {
    $yedekler[] = ['ad' => basename($y), 'boyut' => filesize($y), 'zaman' => filemtime($y)];
}
usort($yedekler, fn($a, $b) => $b['zaman'] <=> $a['zaman']);
$boyutYaz = function (int $b): string {
    if ($b >= 1048576) { return number_format($b / 1048576, 1, ',', '.') . ' MB'; }
    return number_format(max(1, (int)round($b / 1024)), 0, ',', '.') . ' KB';
};

$PAGE_TITLE = 'Yedekleme';
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik"><h1>Yedekleme</h1></div>

<div class="kart">
  <div class="kart-baslik">
    <h2>Veritabanı yedeği</h2>
    <span class="rozet rozet-gri">ritim.sqlite · <?= e($boyutYaz($dbBoyut)) ?></span>
  </div>
  <p class="alan-ipucu">Tüm veriler tek dosyadadır. İndirilen kopya uygulama açıkken bile tutarlıdır
     (WAL içeriği dahil edilir). Kopyayı başka bir diske/buluta taşımak tam yedektir.</p>
  <div class="filtre-satir">
    <a class="btn btn-birincil" href="<?= e(url('yedek.php?islem=indir')) ?>">⬇ Yedeği İndir</a>
    <span class="alan-ipucu">Her gün ilk açılışta otomatik yedek de alınır (aşağıdaki liste, son 10 gün).</span>
  </div>
</div>

<div class="kart">
  <h2>Geri yükleme</h2>
  <p class="alan-ipucu">Daha önce indirilmiş bir <code>.sqlite</code> yedeğini geri yükler.
     Geri yüklemeden hemen önce mevcut durumun <strong>emniyet kopyası</strong> otomatik alınır
     ("oncesi-…" adıyla listeye düşer); yanlışlıkla geri yüklerseniz oradan dönebilirsiniz.</p>
  <form method="post" action="<?= e(url('yedek.php')) ?>" enctype="multipart/form-data" class="filtre-satir"
        data-onay="Mevcut veritabanının ÜZERİNE seçtiğiniz yedek yazılacak. Devam edilsin mi?">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="geri_yukle">
    <label class="form-alan">Yedek dosyası
      <input type="file" name="dosya" class="girdi" accept=".sqlite" required>
    </label>
    <label class="form-alan" style="justify-content:flex-end">
      <span style="display:flex;align-items:center;gap:.4rem;padding:.45rem 0">
        <input type="checkbox" name="onay" value="1" required> Mevcut verilerin üzerine yazılacağını anladım
      </span>
    </label>
    <button type="submit" class="btn btn-tehlike">Geri Yükle</button>
  </form>
</div>

<div class="kart">
  <h2>Sunucudaki yedekler</h2>
  <?php if (!$yedekler): ?>
    <div class="bos-durum">Henüz otomatik yedek yok — yarınki ilk açılışta oluşur, ya da yukarıdan indirip saklayın.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th>Dosya</th><th>Alınma</th><th class="sayi">Boyut</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($yedekler as $y): ?>
        <tr>
          <td>
            <?= str_starts_with($y['ad'], 'oncesi-') ? '🛟' : '🕗' ?> <?= e($y['ad']) ?>
            <?php if (str_starts_with($y['ad'], 'oncesi-')): ?>
              <span class="rozet rozet-gri" title="Geri yükleme öncesi otomatik emniyet kopyası">emniyet</span>
            <?php endif; ?>
          </td>
          <td><?= e(format_date_tr(date('Y-m-d', $y['zaman']))) ?> <?= e(date('H:i', $y['zaman'])) ?></td>
          <td class="sayi"><?= e($boyutYaz((int)$y['boyut'])) ?></td>
          <td style="text-align:right;white-space:nowrap">
            <a class="btn btn-kucuk btn-golge" href="<?= e(url('yedek.php?islem=indir&dosya=' . rawurlencode($y['ad']))) ?>">İndir</a>
            <form method="post" action="<?= e(url('yedek.php')) ?>" style="display:inline"
                  data-onay="Mevcut veritabanının üzerine <?= e($y['ad']) ?> yazılacak. Devam edilsin mi?">
              <?= csrf_field() ?>
              <input type="hidden" name="islem" value="geri_yukle_oto">
              <input type="hidden" name="dosya" value="<?= e($y['ad']) ?>">
              <input type="hidden" name="onay" value="1">
              <button type="submit" class="btn btn-kucuk btn-golge">Bu Yedeğe Dön</button>
            </form>
            <form method="post" action="<?= e(url('yedek.php')) ?>" style="display:inline"
                  data-onay="<?= e($y['ad']) ?> silinsin mi?">
              <?= csrf_field() ?>
              <input type="hidden" name="islem" value="yedek_sil">
              <input type="hidden" name="dosya" value="<?= e($y['ad']) ?>">
              <button type="submit" class="btn btn-kucuk btn-tehlike">Sil</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <p class="alan-ipucu">Otomatik yedekler bu bilgisayardadır; disk arızasına karşı arada bir
     <strong>indirip</strong> başka bir yere (USB, bulut) kopyalamanız önerilir.</p>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
