<?php
/** Dönemlik grup raporu — teknik dağılımı ve katılım grafiği. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$grupId = (int)($_GET['grup_id'] ?? 0);
$grup = group_get($grupId);
if (!$grup) { not_found('Grup bulunamadı.'); }

$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
if (!DateTime::createFromFormat('Y-m-d', $from)) { $from = $grup['baslangic_tarihi'] ?: now()->modify('-8 weeks')->format('Y-m-d'); }
if (!DateTime::createFromFormat('Y-m-d', $to))   { $to = today(); }

$rapor = report_group_period($grupId, $from, $to);
$oturumlar = $rapor['oturumlar'];
$kayitliOturumlar = array_filter($oturumlar, fn($s) => (int)$s['yoklama_sayisi'] > 0);
$oranlar = array_map(fn($s) => (int)$s['yoklama_sayisi'] > 0 ? 100 * (int)$s['gelen_sayisi'] / (int)$s['yoklama_sayisi'] : 0, $kayitliOturumlar);
$ortKatilim = $oranlar ? (int)round(array_sum($oranlar) / count($oranlar)) : null;
$maksAdet = 0;
foreach ($rapor['kategori_dagilimi'] as $k) { $maksAdet = max($maksAdet, (int)$k['adet']); }

$PAGE_TITLE = 'Dönemlik Rapor — ' . $grup['ad'];
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik yazdirmada-gizle">
  <h1>Dönemlik Grup Raporu</h1>
  <div class="sag">
    <a class="btn btn-golge" href="<?= e(url('raporlar.php')) ?>">← Raporlar</a>
    <button type="button" class="btn btn-birincil" data-yazdir>🖨 Yazdır</button>
  </div>
</div>

<div class="kart">
  <div class="rapor-baslik">
    <div>
      <div class="rapor-marka"><?= e($grup['ad']) ?> — Dönem Raporu</div>
      <div class="rapor-tur"><?= e(format_date_tr($from, false)) ?> – <?= e(format_date_tr($to, false)) ?></div>
    </div>
  </div>

  <div class="rapor-meta">
    <div><strong>Oturum</strong> <?= count($oturumlar) ?> planlandı, <?= count($kayitliOturumlar) ?> kaydedildi</div>
    <div><strong>Öğrenci</strong> <?= (int)$rapor['ogrenci_sayisi'] ?> katılımcı</div>
    <div><strong>Ortalama katılım</strong> <?= $ortKatilim === null ? '—' : '%' . $ortKatilim ?></div>
    <div><strong>Ders günü</strong> <?= e(GUNLER[(int)$grup['gun']] ?? '—') ?><?= $grup['saat'] ? ' ' . e($grup['saat']) : '' ?></div>
  </div>

  <h2>Teknik dağılımı (işlenen)</h2>
  <?php if (!$rapor['kategori_dagilimi']): ?>
    <div class="bos-durum">Bu aralıkta işlenmiş teknik kaydı yok. Oturum kaydı girildikçe dağılım oluşur.</div>
  <?php else: ?>
    <?php foreach ($rapor['kategori_dagilimi'] as $k): ?>
    <div class="cubuk-satir">
      <span class="cubuk-etiket"><?= e($k['kategori']) ?></span>
      <div class="cubuk-kanal"><div class="cubuk" style="width:<?= $maksAdet > 0 ? (int)round(100 * (int)$k['adet'] / $maksAdet) : 0 ?>%"></div></div>
      <span class="cubuk-deger"><?= (int)$k['adet'] ?>× · <?= (int)$k['sure'] ?> dk</span>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h2 style="margin-top:1.3rem">Katılım grafiği</h2>
  <?php if (!$kayitliOturumlar): ?>
    <div class="bos-durum">Kaydedilmiş yoklama yok.</div>
  <?php else: ?>
    <?php foreach ($kayitliOturumlar as $s):
        $oran = (int)round(100 * (int)$s['gelen_sayisi'] / max(1, (int)$s['yoklama_sayisi'])); ?>
    <div class="cubuk-satir">
      <span class="cubuk-etiket"><?= e(format_date_tr($s['tarih'], false)) ?> · H<?= (int)$s['hafta_no'] ?></span>
      <div class="cubuk-kanal"><div class="cubuk yesil" style="width:<?= $oran ?>%"></div></div>
      <span class="cubuk-deger">%<?= $oran ?> (<?= (int)$s['gelen_sayisi'] ?>/<?= (int)$s['yoklama_sayisi'] ?>)</span>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h2 style="margin-top:1.3rem">Oturum listesi</h2>
  <?php if (!$oturumlar): ?>
    <div class="bos-durum">Bu aralıkta oturum yok.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th>Tarih</th><th>Hafta</th><th class="sayi">Teknik</th><th class="sayi">Süre</th><th>Katılım</th><th>Not</th></tr></thead>
      <tbody>
        <?php foreach ($oturumlar as $s): ?>
        <tr>
          <td><a class="yazdirmada-baglanti" href="<?= e(url('oturum.php?id=' . (int)$s['id'])) ?>"><?= e(format_date_tr($s['tarih'])) ?></a></td>
          <td>H<?= (int)$s['hafta_no'] ?></td>
          <td class="sayi"><?= (int)$s['teknik_sayisi'] ?></td>
          <td class="sayi"><?= (int)$s['plan_sure'] ?> dk</td>
          <td><?= (int)$s['yoklama_sayisi'] > 0 ? (int)$s['gelen_sayisi'] . '/' . (int)$s['yoklama_sayisi'] : '—' ?></td>
          <td><?= e(mb_strimwidth((string)$s['notlar'], 0, 60, '…')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
