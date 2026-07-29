<?php
/** Oturumlar — tüm oturum listesi, filtre, hızlı erişim. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$grupFiltre = isset($_GET['grup_id']) && $_GET['grup_id'] !== '' ? (int)$_GET['grup_id'] : null;
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$fromOk = valid_date_ymd($from) ? $from : null;
$toOk   = valid_date_ymd($to) ? $to : null;

$oturumlar = sessions_list($grupFiltre, $fromOk, $toOk);
$gruplar = groups_list();

$PAGE_TITLE = 'Oturumlar';
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik">
  <h1>Oturumlar</h1>
  <div class="sag"><a class="btn btn-birincil" href="<?= e(url('plan.php')) ?>">+ Oturum Planla</a></div>
</div>

<div class="kart">
  <form method="get" action="<?= e(url('oturumlar.php')) ?>" class="filtre-satir">
    <label class="form-alan">Grup
      <select name="grup_id" class="secim">
        <option value="">Tümü</option>
        <?php foreach ($gruplar as $g): ?>
        <option value="<?= (int)$g['id'] ?>" <?= $grupFiltre === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['ad']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan">Başlangıç
      <input type="date" name="from" class="girdi" value="<?= e($fromOk ?? '') ?>">
    </label>
    <label class="form-alan">Bitiş
      <input type="date" name="to" class="girdi" value="<?= e($toOk ?? '') ?>">
    </label>
    <button type="submit" class="btn btn-golge">Filtrele</button>
    <a class="btn btn-golge" href="<?= e(url('oturumlar.php')) ?>">Temizle</a>
  </form>

  <?php if (!$oturumlar): ?>
    <div class="bos-durum">Oturum bulunamadı. <a href="<?= e(url('plan.php')) ?>">İlk oturumu planlayın</a>.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th>Tarih</th><th>Grup</th><th>Hafta</th><th class="sayi">Teknik</th><th class="sayi">Plan</th><th>Durum</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($oturumlar as $s): ?>
        <tr>
          <td><?= e(format_date_tr($s['tarih'])) ?></td>
          <td><a href="<?= e(url('oturum.php?id=' . (int)$s['id'])) ?>"><?= e($s['grup_ad']) ?></a></td>
          <td>Hafta <?= (int)$s['hafta_no'] ?></td>
          <td class="sayi"><?= (int)$s['teknik_sayisi'] ?></td>
          <td class="sayi"><?= (int)$s['plan_sure'] ?> dk</td>
          <td>
            <?php if ((int)$s['yoklama_sayisi'] > 0): ?>
              <span class="rozet rozet-tamam"><?= (int)$s['gelen_sayisi'] ?>/<?= (int)$s['yoklama_sayisi'] ?> geldi</span>
            <?php elseif ($s['tarih'] <= today()): ?>
              <span class="rozet rozet-bekliyor">Yoklama bekliyor</span>
            <?php else: ?>
              <span class="rozet rozet-gri">Planlandı</span>
            <?php endif; ?>
          </td>
          <td style="text-align:right">
            <a class="btn btn-kucuk btn-golge" href="<?= e(url('oturum.php?id=' . (int)$s['id'])) ?>">
              <?= (int)$s['yoklama_sayisi'] > 0 ? 'Görüntüle' : 'Kaydet' ?>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
