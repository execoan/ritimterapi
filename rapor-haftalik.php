<?php
/** Haftalık eğitmen raporu — hangi gruplarda ne işlendi, katılım, notlar. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$tarih = trim((string)($_GET['tarih'] ?? today()));
if (!DateTime::createFromFormat('Y-m-d', $tarih)) { $tarih = today(); }
[$pzt, $paz] = week_bounds($tarih);
$rapor = report_weekly($pzt, $paz);

$PAGE_TITLE = 'Haftalık Rapor';
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik yazdirmada-gizle">
  <h1>Haftalık Eğitmen Raporu</h1>
  <div class="sag">
    <a class="btn btn-golge" href="<?= e(url('rapor-haftalik.php?tarih=' . (new DateTime($pzt))->modify('-7 days')->format('Y-m-d'))) ?>">← Önceki hafta</a>
    <a class="btn btn-golge" href="<?= e(url('rapor-haftalik.php?tarih=' . (new DateTime($pzt))->modify('+7 days')->format('Y-m-d'))) ?>">Sonraki hafta →</a>
    <button type="button" class="btn btn-birincil" data-yazdir>🖨 Yazdır</button>
  </div>
</div>

<div class="kart">
  <div class="rapor-baslik">
    <div>
      <div class="rapor-marka">Haftalık Eğitmen Raporu</div>
      <div class="rapor-tur"><?= e(format_date_tr($pzt, false)) ?> – <?= e(format_date_tr($paz, false)) ?></div>
    </div>
  </div>

  <?php if (!$rapor): ?>
    <div class="bos-durum">Bu hafta oturum yok.</div>
  <?php else: ?>
    <?php foreach ($rapor as $grupVeri): ?>
    <h2 style="margin-top:1.2rem"><?= e($grupVeri['grup_ad']) ?></h2>
    <?php foreach ($grupVeri['oturumlar'] as $s): ?>
    <div style="border:1px solid var(--cizgi);border-radius:10px;padding:.8rem 1rem;margin-bottom:.8rem">
      <p style="margin:0 0 .4rem">
        <strong><?= e(format_date_tr($s['tarih'])) ?></strong> · Hafta <?= (int)$s['hafta_no'] ?>
        <?php if ((int)$s['yoklama_sayisi'] > 0): ?>
          <span class="rozet rozet-tamam">Katılım: <?= (int)$s['gelen_sayisi'] ?>/<?= (int)$s['yoklama_sayisi'] ?></span>
        <?php else: ?>
          <span class="rozet rozet-bekliyor">Yoklama girilmedi</span>
        <?php endif; ?>
      </p>
      <?php if ($s['teknikler']): ?>
      <ul style="margin:.3rem 0 .4rem;padding-left:1.2rem">
        <?php foreach ($s['teknikler'] as $t): ?>
        <li>
          <?php if ($t['islendi'] === null): ?><span title="Kayıt girilmedi">▫</span>
          <?php elseif ((int)$t['islendi'] === 1): ?><span title="İşlendi">✔</span>
          <?php else: ?><span title="Atlandı">✖</span><?php endif; ?>
          <?= e($t['ad']) ?> (<?= (int)$t['sure_dk'] ?> dk)
          <?= trim((string)$t['uygulama_notu']) !== '' ? '— <em>' . e($t['uygulama_notu']) . '</em>' : '' ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if (trim((string)$s['notlar']) !== ''): ?>
      <p style="margin:.2rem 0 0" class="alan-ipucu">Oturum notu: <?= e($s['notlar']) ?></p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endforeach; ?>
    <p class="alan-ipucu">✔ işlendi · ✖ atlandı · ▫ kayıt girilmedi</p>
  <?php endif; ?>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
