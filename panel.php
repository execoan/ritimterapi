<?php
/** Panel — bu haftaki oturumlar, bekleyen yoklamalar, genel sayılar. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

[$pzt, $paz] = week_bounds();
$haftaOturumlari = sessions_list(null, $pzt, $paz);
usort($haftaOturumlari, fn($a, $b) => strcmp($a['tarih'], $b['tarih']) ?: strcmp($a['grup_saat'], $b['grup_saat']));
$bekleyenler = pending_sessions();
$bitenPaketler = packages_expiring(2);
$yeniTalepler = pre_registration_count_new();

$aktifGrup    = (int)db()->query('SELECT COUNT(*) FROM gruplar WHERE aktif = 1')->fetchColumn();
$aktifOgrenci = (int)db()->query('SELECT COUNT(*) FROM ogrenciler WHERE aktif = 1')->fetchColumn();
$teknikSayisi = (int)db()->query('SELECT COUNT(*) FROM teknikler WHERE aktif = 1')->fetchColumn();

$PAGE_TITLE = 'Panel';
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik">
  <h1>Panel</h1>
  <span class="rozet rozet-gri"><?= e(format_date_tr($pzt, false)) ?> – <?= e(format_date_tr($paz, false)) ?></span>
  <div class="sag">
    <a class="btn btn-birincil" href="<?= e(url('plan.php')) ?>">+ Oturum Planla</a>
  </div>
</div>

<div class="ozet-dizi">
  <div class="ozet-kart"><div class="sayi"><?= $aktifGrup ?></div><div class="etiket">Aktif grup</div></div>
  <div class="ozet-kart"><div class="sayi"><?= $aktifOgrenci ?></div><div class="etiket">Aktif öğrenci</div></div>
  <div class="ozet-kart"><div class="sayi"><?= $teknikSayisi ?></div><div class="etiket">Teknik (kütüphane)</div></div>
  <div class="ozet-kart"><div class="sayi"><?= count($haftaOturumlari) ?></div><div class="etiket">Bu hafta oturum</div></div>
</div>

<?php if ($yeniTalepler > 0): ?>
<div class="uyari-kutu">
  📨 Tanıtım sitesinden <strong><?= $yeniTalepler ?></strong> yeni deneme oturumu talebi var.
  <a href="<?= e(url('on-kayitlar.php')) ?>">Talepleri aç →</a>
</div>
<?php endif; ?>

<?php if ($bekleyenler): ?>
<div class="kart">
  <div class="kart-baslik">
    <h2>Bekleyen yoklamalar</h2>
    <span class="rozet rozet-bekliyor"><?= count($bekleyenler) ?> oturum</span>
  </div>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th>Tarih</th><th>Grup</th><th>Hafta</th><th class="sayi">Teknik</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($bekleyenler as $s): ?>
        <tr>
          <td><?= e(format_date_tr($s['tarih'])) ?></td>
          <td><a href="<?= e(url('oturum.php?id=' . (int)$s['id'])) ?>"><?= e($s['grup_ad']) ?></a></td>
          <td>Hafta <?= (int)$s['hafta_no'] ?></td>
          <td class="sayi"><?= (int)$s['teknik_sayisi'] ?></td>
          <td style="text-align:right"><a class="btn btn-kucuk btn-birincil" href="<?= e(url('oturum.php?id=' . (int)$s['id'])) ?>">Yoklama gir</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($bitenPaketler): ?>
<div class="kart">
  <div class="kart-baslik">
    <h2>Seans paketi bitmek üzere</h2>
    <span class="rozet rozet-bekliyor"><?= count($bitenPaketler) ?> öğrenci</span>
  </div>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th>Öğrenci</th><th>Paket</th><th class="sayi">Kullanılan</th><th class="sayi">Kalan</th></tr></thead>
      <tbody>
        <?php foreach ($bitenPaketler as $p): ?>
        <tr>
          <td><a href="<?= e(url('ogrenci.php?id=' . (int)$p['ogrenci_id'])) ?>"><?= e($p['ogrenci_kod']) ?></a></td>
          <td><?= e($p['ad']) ?></td>
          <td class="sayi"><?= (int)$p['kullanilan'] ?>/<?= (int)$p['toplam_seans'] ?></td>
          <td class="sayi"><strong><?= (int)$p['kalan'] ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="kart">
  <div class="kart-baslik">
    <h2>Bu haftaki oturumlar</h2>
    <div class="sag"><a class="btn btn-kucuk btn-golge" href="<?= e(url('oturumlar.php')) ?>">Tüm oturumlar</a></div>
  </div>
  <?php if (!$haftaOturumlari): ?>
    <div class="bos-durum">Bu hafta için planlanmış oturum yok.
      <a href="<?= e(url('plan.php')) ?>">Oturum planlayın</a>.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th>Tarih</th><th>Saat</th><th>Grup</th><th>Hafta</th><th class="sayi">Teknik</th><th>Durum</th></tr></thead>
      <tbody>
        <?php foreach ($haftaOturumlari as $s): ?>
        <tr>
          <td><?= $s['tarih'] === today() ? '<strong>Bugün</strong> — ' : '' ?><?= e(format_date_tr($s['tarih'])) ?></td>
          <td><?= e($s['grup_saat'] ?: '—') ?></td>
          <td><a href="<?= e(url('oturum.php?id=' . (int)$s['id'])) ?>"><?= e($s['grup_ad']) ?></a></td>
          <td>Hafta <?= (int)$s['hafta_no'] ?></td>
          <td class="sayi"><?= (int)$s['teknik_sayisi'] ?> (<?= (int)$s['plan_sure'] ?> dk)</td>
          <td>
            <?php if ((int)$s['yoklama_sayisi'] > 0): ?>
              <span class="rozet rozet-tamam">Kaydedildi — <?= (int)$s['gelen_sayisi'] ?>/<?= (int)$s['yoklama_sayisi'] ?> geldi</span>
            <?php elseif ($s['tarih'] <= today()): ?>
              <span class="rozet rozet-bekliyor">Yoklama bekliyor</span>
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
  <h2>Hızlı işlemler</h2>
  <div class="form-butonlar" style="margin-top:.3rem">
    <a class="btn btn-golge" href="<?= e(url('gruplar.php')) ?>">+ Yeni grup</a>
    <a class="btn btn-golge" href="<?= e(url('ogrenciler.php')) ?>">+ Yeni öğrenci</a>
    <a class="btn btn-golge" href="<?= e(url('teknikler.php')) ?>">+ Yeni teknik</a>
    <a class="btn btn-golge" href="<?= e(url('metronom.php')) ?>">🎵 Metronom Stüdyosu</a>
    <a class="btn btn-golge" href="<?= e(url('raporlar.php')) ?>">Rapor hazırla</a>
  </div>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
