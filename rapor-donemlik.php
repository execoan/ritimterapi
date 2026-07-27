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
$protokolRapor = report_group_protocols($grupId, $from, $to);
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
  </div>
</div>

<?php $BELGE_ETIKET = 'Raporu'; require APP_DIR . '/includes/view/belge-arac-cubugu.php'; ?>
<div class="kart" data-belge>
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

  <?php if ($protokolRapor['haftalik']): ?>
  <h2 style="margin-top:1.3rem">Protokol gelişimi (iç izleme)</h2>
  <p class="alan-ipucu">Metronom Stüdyosu ve ev çalışması ölçümlerinin haftalık ortalamaları.
     Skorlar eğitmenin iç izleme aracıdır; veli raporuna yansıtılmaz.</p>
  <p class="alan-ipucu">📏 işaretli satırlarda ilk→son karşılaştırması yalnız standart koşullu ölçümlerden
     yapılmıştır; işaretsiz satırlarda koşullar (tempo/zorluk) değişmiş olabilir.</p>
  <?php foreach ($protokolRapor['haftalik'] as $pKod => $haftalar):
      ksort($haftalar); ?>
  <h3 style="margin:.9rem 0 .4rem">🧭 <?= e(PROTOKOL_LABELS[$pKod] ?? $pKod) ?></h3>
  <?php foreach ($haftalar as $pzt => $veri):
      $ortalama = (int)round($veri['toplam'] / max(1, $veri['adet'])); ?>
  <div class="cubuk-satir">
    <span class="cubuk-etiket"><?= e(format_date_tr($pzt, false)) ?> haftası</span>
    <div class="cubuk-kanal"><div class="cubuk" style="width:<?= $ortalama ?>%"></div></div>
    <span class="cubuk-deger"><?= $ortalama ?>/100 (<?= (int)$veri['adet'] ?> ölçüm)</span>
  </div>
  <?php endforeach; ?>
  <?php
      $gelisenler = array_filter($protokolRapor['ogrenciler'][$pKod] ?? [], fn($v) => $v['adet'] >= 2);
      if ($gelisenler): ?>
  <div class="tablo-sar" style="margin:.4rem 0 .8rem">
    <table class="tablo">
      <thead><tr><th>Öğrenci</th><th class="sayi">İlk skor</th><th class="sayi">Son skor</th><th class="sayi">Değişim</th><th class="sayi">Ölçüm</th></tr></thead>
      <tbody>
        <?php foreach ($gelisenler as $kod => $v):
            $fark = (int)$v['son'] - (int)$v['ilk']; ?>
        <tr>
          <td><?= e($kod) ?><?= !empty($v['standart']) ? ' <span title="İlk→son karşılaştırması yalnız standart koşullu (📏) ölçümlerden">📏</span>' : '' ?></td>
          <td class="sayi"><?= (int)$v['ilk'] ?></td>
          <td class="sayi"><strong><?= (int)$v['son'] ?></strong></td>
          <td class="sayi" style="color:<?= $fark > 0 ? 'var(--yesil)' : ($fark < 0 ? 'var(--kirmizi)' : 'inherit') ?>">
            <?= $fark > 0 ? '▲ +' . $fark : ($fark < 0 ? '▼ ' . $fark : '—') ?></td>
          <td class="sayi"><?= (int)$v['adet'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
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
<script src="<?= e(asset('js/belge-duzenle.js')) ?>" defer></script>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
