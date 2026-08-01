<?php
/** Dönemlik grup raporu — teknik dağılımı ve katılım grafiği. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$grupId = (int)($_GET['grup_id'] ?? 0);
$grup = group_get($grupId);
if (!$grup) { not_found('Grup bulunamadı.'); }

$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
if (!valid_date_ymd($from)) {
    $from = valid_date_ymd((string)$grup['baslangic_tarihi'])
        ? (string)$grup['baslangic_tarihi']
        : now()->modify('-8 weeks')->format('Y-m-d');
}
if (!valid_date_ymd($to)) { $to = today(); }

$rapor = report_group_period($grupId, $from, $to);
$protokolRapor = report_group_protocols($grupId, $from, $to);
$pratikDoz = report_group_practice($grupId, $from, $to);
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
    <a class="btn btn-golge" href="<?= e(url('raporlar.php')) ?>"><span class="emoji-sus" aria-hidden="true">←</span> Raporlar</a>
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
  <p class="alan-ipucu"><span class="emoji-sus" aria-hidden="true">📏</span> işaretli satırlarda ilk→son karşılaştırması yalnız standart koşullu ölçümlerden
     yapılmıştır; işaretsiz satırlarda koşullar (tempo/zorluk) değişmiş olabilir.
     Uçlardaki tek ölçüm yerine <strong>ilk/son blok medyanı</strong> alınır (ölçüm sayısı yettiğinde 3'er),
     böylece ilk denemedeki "görevi tanıma" etkisi ve günlük dalgalanma azalır.
     <strong>Gürültü bandı</strong>, öğrencinin kendi ölçüm dalgalanmasından hesaplanır: farkın bu bandın
     altında kalması "değişim yok" demek değildir — <em>ölçümle ayırt edilemiyor</em> demektir.</p>
  <?php foreach ($protokolRapor['haftalik'] as $pKod => $haftalar):
      ksort($haftalar); ?>
  <h3 style="margin:.9rem 0 .4rem"><span class="emoji-sus" aria-hidden="true">🧭</span> <?= e(protokol_etiketi($pKod)) ?>
    <?php if ($pKod === 'aksak_bulma'): ?>
    <span class="rozet rozet-gri" title="Ev programında çalışılmayan, saf dinleme ölçümü">eğitilmeyen sonda</span>
    <?php endif; ?>
  </h3>
  <?php if ($pKod === 'aksak_bulma'): ?>
  <p class="alan-ipucu">Bu protokol ev programında <strong>çalışılmıyor</strong>; kasten eğitilmeyen kontrol ölçümüdür.
     Eğitilen protokollerle birlikte bu da benzer oranda yükseliyorsa, tabloda görülen büyük olasılıkla
     genel alışma/test tekrarı etkisidir. Yalnız eğitilenler yükseliyorsa değişim daha özgüldür.</p>
  <?php endif; ?>
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
      <thead><tr><th scope="col">Öğrenci</th><th scope="col" class="sayi">Dönem başı</th><th scope="col" class="sayi">Dönem sonu</th>
                 <th scope="col" class="sayi">Değişim</th><th scope="col">Yorum</th><th scope="col" class="sayi">Ölçüm</th></tr></thead>
      <tbody>
        <?php foreach ($gelisenler as $kod => $v):
            $fark = (int)$v['fark'];
            $mdc = $v['mdc'] ?? null;
            // Gürültünün üstünde olmayan farkı renklendirmeyiz: renk "gerçek değişim" izlenimi verir
            $belirgin = $v['anlamli'] === true; ?>
        <tr>
          <td><?= e($kod) ?><?= !empty($v['standart']) ? ' <span title="Karşılaştırma yalnız standart koşullu (📏) ölçümlerden">📏</span>' : '' ?>
            <?php if (!empty($v['supheli_olcum'])): ?>
            <span title="<?= (int)$v['supheli_olcum'] ?> ölçüm kalibrasyonsuz/şüpheli kalibrasyonla alınmış">⚠</span>
            <?php endif; ?>
          </td>
          <td class="sayi"><?= (int)$v['ilk'] ?></td>
          <td class="sayi"><strong><?= (int)$v['son'] ?></strong></td>
          <td class="sayi" style="color:<?= $belirgin ? ($fark > 0 ? 'var(--yesil)' : 'var(--kirmizi)') : 'inherit' ?>">
            <?= $fark > 0 ? '▲ +' . $fark : ($fark < 0 ? '▼ ' . $fark : '—') ?></td>
          <td>
            <?php if ($v['anlamli'] === null): ?>
              <span class="alan-ipucu">gürültü bandı için en az 3 ölçüm gerekir</span>
            <?php elseif ($belirgin): ?>
              gürültü bandının (±<?= (int)$mdc ?>) <strong>üstünde</strong>
            <?php else: ?>
              <span class="alan-ipucu">gürültü bandı (±<?= (int)$mdc ?>) içinde — ölçümle ayırt edilemiyor</span>
            <?php endif; ?>
          </td>
          <td class="sayi"><?= (int)$v['adet'] ?><?= (int)$v['blok'] > 1 ? ' <span class="alan-ipucu" title="Uçlardan ' . (int)$v['blok'] . '\'er ölçümün medyanı">(medyan ' . (int)$v['blok'] . ')</span>' : '' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($pratikDoz): ?>
  <h2 style="margin-top:1.3rem">Ev pratiği dozu</h2>
  <p class="alan-ipucu">Hafta başına işaretlenen pratik günü (aynı gün birden çok çalışma bir gün sayılır).
     Protokol trendiyle birlikte okunur: skorlardaki değişim pratiğin yoğun olduğu haftalarla birlikte
     gidiyor mu? Bu bir nedensellik kanıtı değildir, yalnız tutarlılık kontrolüdür.</p>
  <?php $enYuksekDoz = max(array_map(fn($v) => (int)$v['gun'], $pratikDoz)); ?>
  <?php foreach ($pratikDoz as $pzt => $v): ?>
  <div class="cubuk-satir">
    <span class="cubuk-etiket"><?= e(format_date_tr($pzt, false)) ?> haftası</span>
    <div class="cubuk-kanal"><div class="cubuk yesil" style="width:<?= $enYuksekDoz > 0 ? (int)round(100 * (int)$v['gun'] / $enYuksekDoz) : 0 ?>%"></div></div>
    <span class="cubuk-deger"><?= (int)$v['gun'] ?> gün · <?= (int)$v['ogrenci'] ?> öğrenci</span>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <h2 style="margin-top:1.3rem">Oturum listesi</h2>
  <?php if (!$oturumlar): ?>
    <div class="bos-durum">Bu aralıkta oturum yok.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th scope="col">Tarih</th><th scope="col">Hafta</th><th scope="col" class="sayi">Teknik</th><th scope="col" class="sayi">Süre</th><th scope="col">Katılım</th><th scope="col">Not</th></tr></thead>
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
