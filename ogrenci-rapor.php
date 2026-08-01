<?php
/**
 * Öğrenci Rapor Merkezi — tek öğrencinin dönem görünümü: protokol trendleri
 * (SVG çizgi grafik), katılım, ev pratiği, teknikler, gözlemler ve eğitmen
 * değerlendirmesi. Egitimgen V2 deseni: bölüm seçimi + hazır cümle havuzu +
 * ekranda düzenlenip Yazdır/PDF ile çıkarılabilen belge (kayıtlı veri değişmez).
 *
 * BU BİR İÇ RAPORDUR (eğitmenin izleme aracı). Protokol skorları içerdiği için
 * VELİYE VERİLMEZ (CLAUDE.md §2) — veli çıktısı için rapor-veli.php kullanılır.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$ogrenciId = (int)($_GET['ogrenci_id'] ?? 0);
$ogrenci = student_get($ogrenciId);
if (!$ogrenci) { not_found('Öğrenci bulunamadı.'); }

$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
if (!DateTime::createFromFormat('Y-m-d', $from)) { $from = now()->modify('-12 weeks')->format('Y-m-d'); }
if (!DateTime::createFromFormat('Y-m-d', $to))   { $to = today(); }

/* Bölüm seçimi (Egitimgen deseni): hiç seçim gelmezse hepsi açık */
const RAPOR_BOLUMLERI = [
    'ozet'          => 'Dönem özeti',
    'protokoller'   => 'Protokol trendleri (grafikli)',
    'katilim'       => 'Katılım çizelgesi',
    'pratik'        => 'Ev pratiği',
    'teknikler'     => 'Çalışılan teknikler',
    'gozlemler'     => 'Gözlem notları',
    'degerlendirme' => 'Eğitmen değerlendirmesi',
];
$secilenBolumler = array_values(array_intersect(
    (array)($_GET['bolum'] ?? []), array_keys(RAPOR_BOLUMLERI)));
if (!$secilenBolumler) { $secilenBolumler = array_keys(RAPOR_BOLUMLERI); }
$bolumAcik = fn(string $b) => in_array($b, $secilenBolumler, true);

$rapor = report_student($ogrenciId, $from, $to);
$seriler = student_protocol_series($ogrenciId, $from, $to);
$karsilastirma = student_protocol_first_last($ogrenciId, $from, $to);
$pratik = student_practice_weekly($ogrenciId, $from, $to);
$evOzet = report_student_home($ogrenciId, $from, $to);

/*
 * Hazır değerlendirme cümleleri (Egitimgen V2'nin metin havuzu deseni).
 * KİLİTLİ DİL: yalnız gözlemlenebilir davranış — sonuç/etki iddiası, klinik
 * çağrışım yok. Eğitmen düzenleme modunda tıklayıp belgeye ekler.
 */
$hazirCumleler = [
    'Katılım' => [
        'Dönem boyunca oturumlara düzenli katıldı.',
        'Oturumlara katılımı dönem içinde dalgalı seyretti; devamlılık üzerine konuşuldu.',
        'Geç kaldığı oturumlarda dahi çalışmalara dahil oldu.',
    ],
    'Çalışma davranışı' => [
        'Yönergeleri çoğunlukla ilk seferde takip etti.',
        'Sıra bekleme ve grup içi kurallara uyum gösterdi.',
        'Zor bulduğu kalıplarda yeniden denemeyi kendisi talep etti.',
        'Çalışmalarda kendi hatasını fark edip düzeltme girişiminde bulundu.',
    ],
    'Ritim çalışmaları' => [
        'Sabit tempoda eşlik çalışmalarını tamamladı.',
        'Sessiz fazlı çalışmalarda vuruşa devam etme yönergesini uyguladı.',
        'Yeni tanıtılan kalıpları model gösterildikten sonra tekrar edebildi.',
        'Grup çalışmasında kendi partisini sürdürdü.',
    ],
    'Ev programı' => [
        'Ev çalışmalarını düzenli olarak işaretledi.',
        'Ev çalışmalarında işaretleme düzensizdi; birlikte yeni bir plan yapıldı.',
    ],
];

$PAGE_TITLE = 'Öğrenci Raporu — ' . $ogrenci['kod'];
require APP_DIR . '/includes/view/header.php';

/* ---------- SVG grafik yardımcıları (sunucuda çizilir; yazdırmaya doğrudan girer) ---------- */

/** 0-100 skor serisinin çizgi grafiği. Dolu nokta = 📏 standart, halka = değil. */
function skor_grafigi_svg(array $seri): string
{
    $n = count($seri);
    $G = 560; $Y = 150; $solPay = 34; $altPay = 22; $ustPay = 10;
    $ic = $G - $solPay - 8;
    $yuk = $Y - $altPay - $ustPay;
    $x = fn(int $i) => $solPay + ($n > 1 ? $ic * $i / ($n - 1) : $ic / 2);
    $y = fn(float $v) => $ustPay + $yuk * (1 - max(0, min(100, $v)) / 100);

    $s = '<svg viewBox="0 0 ' . $G . ' ' . $Y . '" class="rpr-grafik" role="img" aria-label="Skor trendi">';
    foreach ([0, 25, 50, 75, 100] as $t) {
        $yy = $y($t);
        $s .= '<line x1="' . $solPay . '" y1="' . $yy . '" x2="' . ($G - 8) . '" y2="' . $yy . '" class="rpr-izgara"/>';
        $s .= '<text x="' . ($solPay - 6) . '" y="' . ($yy + 3.5) . '" class="rpr-eksen" text-anchor="end">' . $t . '</text>';
    }
    $nokta = [];
    foreach ($seri as $i => $p) { $nokta[] = round($x($i), 1) . ',' . round($y($p['skor']), 1); }
    if ($n > 1) { $s .= '<polyline points="' . implode(' ', $nokta) . '" class="rpr-cizgi"/>'; }
    foreach ($seri as $i => $p) {
        $cx = round($x($i), 1); $cy = round($y($p['skor']), 1);
        $sinif = $p['standart'] ? 'rpr-nokta std' : 'rpr-nokta';
        $s .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="4.5" class="' . $sinif . '">'
            . '<title>' . e(format_date_tr($p['tarih'], false)) . ' · ' . (int)$p['skor'] . '/100'
            . ($p['standart'] ? ' · 📏' : '') . ($p['kaynak'] === 'ev' ? ' · ev' : '')
            . ($p['sd_ms'] !== null ? ' · SD ' . (int)$p['sd_ms'] . ' ms' : '') . '</title></circle>';
        if ($p['kaynak'] === 'ev') {
            $s .= '<text x="' . $cx . '" y="' . ($cy - 8) . '" class="rpr-ev" text-anchor="middle">🏠</text>';
        }
    }
    $s .= '<text x="' . $solPay . '" y="' . ($Y - 6) . '" class="rpr-eksen">' . e(format_date_tr($seri[0]['tarih'], false)) . '</text>';
    if ($n > 1) {
        $s .= '<text x="' . ($G - 8) . '" y="' . ($Y - 6) . '" class="rpr-eksen" text-anchor="end">'
            . e(format_date_tr($seri[$n - 1]['tarih'], false)) . '</text>';
    }
    return $s . '</svg>';
}

/** Kararlılık (SD, ms) mini grafiği — düşük değer iyi. */
function sd_grafigi_svg(array $seri): string
{
    $noktalar = array_values(array_filter($seri, fn($p) => $p['sd_ms'] !== null));
    if (count($noktalar) < 2) { return ''; }
    $enB = max(10, max(array_map(fn($p) => (int)$p['sd_ms'], $noktalar)));
    $n = count($noktalar);
    $G = 560; $Y = 74; $solPay = 34; $ic = $G - $solPay - 8; $yuk = $Y - 26;
    $x = fn(int $i) => $solPay + ($n > 1 ? $ic * $i / ($n - 1) : $ic / 2);
    $y = fn(int $v) => 8 + $yuk * (1 - $v / $enB);
    $s = '<svg viewBox="0 0 ' . $G . ' ' . $Y . '" class="rpr-grafik sd" role="img" aria-label="Kararlılık (SD)">';
    $s .= '<text x="' . ($solPay - 6) . '" y="14" class="rpr-eksen" text-anchor="end">' . $enB . '</text>'
        . '<text x="' . ($solPay - 6) . '" y="' . ($Y - 14) . '" class="rpr-eksen" text-anchor="end">0</text>';
    $cizgi = [];
    foreach ($noktalar as $i => $p) { $cizgi[] = round($x($i), 1) . ',' . round($y((int)$p['sd_ms']), 1); }
    $s .= '<polyline points="' . implode(' ', $cizgi) . '" class="rpr-cizgi sd"/>';
    foreach ($noktalar as $i => $p) {
        $s .= '<circle cx="' . round($x($i), 1) . '" cy="' . round($y((int)$p['sd_ms']), 1) . '" r="3.5" class="rpr-nokta sd">'
            . '<title>' . e(format_date_tr($p['tarih'], false)) . ' · SD ' . (int)$p['sd_ms'] . ' ms</title></circle>';
    }
    $s .= '<text x="' . ($G - 8) . '" y="' . ($Y - 4) . '" class="rpr-eksen" text-anchor="end">Kararlılık SD (ms) — düşük iyi</text>';
    return $s . '</svg>';
}
?>
<div class="sayfa-baslik yazdirmada-gizle">
  <h1>Öğrenci Rapor Merkezi</h1>
  <div class="sag">
    <a class="btn btn-golge" href="<?= e(url('raporlar.php')) ?>"><span class="emoji-sus" aria-hidden="true">←</span> Raporlar</a>
    <a class="btn btn-golge" href="<?= e(url('ogrenci.php?id=' . $ogrenciId)) ?>">Öğrenci sayfası</a>
  </div>
</div>

<div class="uyari-kutu yazdirmada-gizle">
  <strong>İç rapordur:</strong> protokol skorları içerdiği için veliye verilmez (CLAUDE.md §2).
  Veli çıktısı için <a href="<?= e(url('rapor-veli.php?ogrenci_id=' . $ogrenciId . '&from=' . $from . '&to=' . $to)) ?>">veli raporunu</a>
  kullanın. Skorlar eğitsel izleme amaçlıdır; bir değerlendirme veya tanı aracı değildir.
</div>

<form method="get" action="<?= e(url('ogrenci-rapor.php')) ?>" class="kart yazdirmada-gizle" id="bolumFormu">
  <input type="hidden" name="ogrenci_id" value="<?= $ogrenciId ?>">
  <div class="filtre-satir">
    <label class="form-alan">Başlangıç <input type="date" name="from" class="girdi" value="<?= e($from) ?>"></label>
    <label class="form-alan">Bitiş <input type="date" name="to" class="girdi" value="<?= e($to) ?>"></label>
    <button type="submit" class="btn btn-birincil">Uygula</button>
  </div>
  <div class="rpr-bolum-secim">
    <?php foreach (RAPOR_BOLUMLERI as $anahtar => $etiket): ?>
    <label><input type="checkbox" name="bolum[]" value="<?= e($anahtar) ?>" <?= $bolumAcik($anahtar) ? 'checked' : '' ?>>
      <?= e($etiket) ?></label>
    <?php endforeach; ?>
  </div>
</form>

<?php $BELGE_ETIKET = 'Raporu'; require APP_DIR . '/includes/view/belge-arac-cubugu.php'; ?>

<div class="kart" data-belge>
  <div class="rapor-baslik">
    <div>
      <div class="rapor-marka"><?= e(APP_NAME) ?> — Öğrenci Raporu</div>
      <div class="rapor-tur">İç izleme belgesi · veli çıktısı değildir</div>
    </div>
  </div>

  <div class="rapor-meta">
    <div><strong>Öğrenci</strong> <?= e($ogrenci['kod']) ?></div>
    <div><strong>Grup</strong> <?= e($ogrenci['grup_adlari'] ?: ($ogrenci['grup_ad'] ?: '—')) ?></div>
    <div><strong>Dönem</strong> <?= e(format_date_tr($from, false)) ?> – <?= e(format_date_tr($to, false)) ?></div>
    <div><strong>Hazırlanma</strong> <?= e(format_date_tr(today(), false)) ?></div>
  </div>

  <?php if ($bolumAcik('ozet')): ?>
  <h2>Dönem özeti</h2>
  <div class="rpr-ozet">
    <div class="rpr-kutu">
      <div class="deger"><?= $rapor['oran'] === null ? '—' : '%' . (int)$rapor['oran'] ?></div>
      <div class="etiket">Katılım (<?= (int)$rapor['gelen'] ?>/<?= (int)$rapor['toplam'] ?> oturum)</div>
    </div>
    <div class="rpr-kutu">
      <div class="deger"><?= array_sum(array_map('count', $seriler)) ?></div>
      <div class="etiket">Protokol ölçümü (<?= count($seriler) ?> protokol)</div>
    </div>
    <div class="rpr-kutu">
      <div class="deger"><?= array_sum($pratik) ?></div>
      <div class="etiket">Ev pratiği günü</div>
    </div>
    <div class="rpr-kutu">
      <div class="deger"><?= count($rapor['teknikler']) ?></div>
      <div class="etiket">Çalışılan teknik</div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($bolumAcik('protokoller')): ?>
  <h2>Protokol trendleri</h2>
  <?php if (!$seriler): ?>
    <div class="bos-durum">Bu dönemde protokol ölçümü yok.</div>
  <?php else: ?>
    <p class="alan-ipucu">Dolu nokta = 📏 standart koşul · halka = koşullar farklı · 🏠 = ev ölçümü.
       Dönem başı/sonu, uç blokların medyanıdır; fark yalnız gürültü bandının üstündeyse renklendirilir.</p>
    <?php foreach ($seriler as $pKod => $seri):
        $k = $karsilastirma[$pKod] ?? null; ?>
    <div class="rpr-protokol">
      <h3><span class="emoji-sus" aria-hidden="true">🧭</span> <?= e(protokol_etiketi($pKod)) ?>
        <?php if ($pKod === 'aksak_bulma'): ?><span class="rozet rozet-gri">eğitilmeyen sonda</span><?php endif; ?>
      </h3>
      <?= skor_grafigi_svg($seri) ?>
      <?= sd_grafigi_svg($seri) ?>
      <?php if ($k && $k['adet'] >= 2): ?>
      <p class="rpr-karsilastirma">
        Dönem başı <strong><?= (int)$k['ilk'] ?></strong> → dönem sonu <strong><?= (int)$k['son'] ?></strong>
        <?php if ($k['anlamli'] === true): ?>
          <span style="color:<?= $k['fark'] > 0 ? 'var(--yesil)' : 'var(--kirmizi)' ?>">
            (<?= $k['fark'] > 0 ? '▲ +' : '▼ ' ?><?= (int)$k['fark'] ?> — gürültü bandının ±<?= (int)$k['mdc'] ?> üstünde)</span>
        <?php elseif ($k['anlamli'] === false): ?>
          (<?= $k['fark'] > 0 ? '+' : '' ?><?= (int)$k['fark'] ?> — gürültü bandı ±<?= (int)$k['mdc'] ?> içinde, ölçümle ayırt edilemiyor)
        <?php else: ?>
          (karar için en az 3 ölçüm gerekir)
        <?php endif; ?>
        <?= !empty($k['standart']) ? ' 📏' : '' ?>
        <?php if (!empty($k['supheli_olcum'])): ?>
          <span title="<?= (int)$k['supheli_olcum'] ?> ölçüm kalibrasyonsuz/şüpheli">⚠</span>
        <?php endif; ?>
      </p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($bolumAcik('katilim')): ?>
  <h2>Katılım çizelgesi</h2>
  <?php if (!$rapor['katilimlar']): ?>
    <div class="bos-durum">Bu dönemde yoklama kaydı yok.</div>
  <?php else: ?>
  <div class="rpr-katilim">
    <?php foreach ($rapor['katilimlar'] as $kt): ?>
    <span class="rpr-gun <?= e($kt['durum']) ?>"
          title="<?= e(format_date_tr($kt['tarih'], false)) ?> · <?= e(KATILIM_LABELS[$kt['durum']] ?? $kt['durum']) ?>"></span>
    <?php endforeach; ?>
  </div>
  <p class="alan-ipucu"><span class="emoji-sus" aria-hidden="true">■</span> katıldı · ■ geç · ■ gelmedi (soldan sağa kronolojik)</p>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($bolumAcik('pratik')): ?>
  <h2>Ev pratiği</h2>
  <?php if (!$pratik): ?>
    <div class="bos-durum">Bu dönemde işaretlenmiş ev çalışması yok.</div>
  <?php else: ?>
    <?php $enCok = max($pratik); ?>
    <?php foreach ($pratik as $pzt => $gun): ?>
    <div class="cubuk-satir">
      <span class="cubuk-etiket"><?= e(format_date_tr($pzt, false)) ?> haftası</span>
      <div class="cubuk-kanal"><div class="cubuk yesil" style="width:<?= (int)round(100 * $gun / max(1, $enCok)) ?>%"></div></div>
      <span class="cubuk-deger"><?= (int)$gun ?> gün</span>
    </div>
    <?php endforeach; ?>
    <?php if ($evOzet): ?>
    <p class="alan-ipucu">Çalışma bazında: <?= e(implode(' · ', array_map(
        fn($eo) => $eo['ad'] . ' ' . (int)$eo['gun_sayisi'] . ' gün', $evOzet))) ?></p>
    <?php endif; ?>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($bolumAcik('teknikler')): ?>
  <h2>Çalışılan teknikler</h2>
  <?php if (!$rapor['teknikler']): ?>
    <div class="bos-durum">Katıldığı oturumlarda işlenmiş teknik kaydı yok.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th scope="col">Teknik</th><th scope="col">Kategori</th><th scope="col" class="sayi">Oturum</th></tr></thead>
      <tbody>
        <?php foreach ($rapor['teknikler'] as $t): ?>
        <tr><td><?= e($t['ad']) ?></td><td><?= e($t['kategori']) ?></td>
            <td class="sayi"><?= (int)$t['oturum_adedi'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($bolumAcik('gozlemler')): ?>
  <h2>Gözlem notları</h2>
  <?php if (!$rapor['gozlemler']): ?>
    <div class="bos-durum">Bu dönemde gözlem notu yok.</div>
  <?php else: ?>
  <ul class="zaman-cizelge">
    <?php foreach ($rapor['gozlemler'] as $g): ?>
    <li><div class="tarih"><?= e(format_date_tr($g['tarih'])) ?></div>
        <div class="not"><?= e($g['gozlem_notu']) ?></div></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($bolumAcik('degerlendirme')): ?>
  <h2>Eğitmen değerlendirmesi</h2>
  <div class="rpr-degerlendirme" id="degerlendirmeAlani">
    <p><em>Bu bölümü ✏️ Düzenle modunda doldurun — aşağıdaki hazır cümleleri tıklayarak ekleyebilir,
       serbestçe yazabilirsiniz. Dil kuralı: yalnız gözlemlenebilir davranış; sonuç/etki iddiası yok.</em></p>
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:3rem;margin-top:1.6rem;flex-wrap:wrap">
    <div>Eğitmen: ______________________</div>
    <div>Tarih: <?= e(format_date_tr(today(), false)) ?></div>
  </div>
</div>

<?php if ($bolumAcik('degerlendirme')): ?>
<div class="kart yazdirmada-gizle" id="cumleHavuzu">
  <div class="kart-baslik">
    <h2>Hazır cümle havuzu</h2>
    <span class="alan-ipucu"><span class="emoji-sus" aria-hidden="true">✏️</span> Düzenle modu açıkken tıkla → "Eğitmen değerlendirmesi" bölümüne eklenir.
       Cümleler gözlemsel dille yazılmıştır; ekledikten sonra serbestçe değiştirebilirsiniz.</span>
  </div>
  <?php foreach ($hazirCumleler as $baslik => $cumleler): ?>
  <h3 style="margin:.6rem 0 .3rem"><?= e($baslik) ?></h3>
  <div class="rpr-cumleler">
    <?php foreach ($cumleler as $c): ?>
    <button type="button" class="rpr-cumle" data-cumle="<?= e($c) ?>"><?= e($c) ?></button>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
  .rpr-bolum-secim { display: flex; gap: .4rem 1.1rem; flex-wrap: wrap; margin-top: .6rem; font-size: .92rem; }
  .rpr-bolum-secim label { display: flex; gap: .35rem; align-items: center; cursor: pointer; }
  .rpr-ozet { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: .7rem; margin: .5rem 0 1rem; }
  .rpr-kutu { border: 1px solid var(--cizgi); border-radius: 10px; padding: .7rem .5rem; text-align: center; }
  .rpr-kutu .deger { font-size: 1.7rem; font-weight: 800; color: var(--murekkep); }
  .rpr-kutu .etiket { font-size: .78rem; color: var(--metin-soluk); margin-top: .15rem; }
  .rpr-protokol { margin-bottom: 1.2rem; break-inside: avoid; }
  .rpr-grafik { width: 100%; max-width: 620px; display: block; }
  .rpr-izgara { stroke: #e7e2d9; stroke-width: 1; }
  .rpr-eksen { font-size: 10px; fill: #a8a29e; }
  .rpr-cizgi { fill: none; stroke: var(--amber); stroke-width: 2.2; }
  .rpr-cizgi.sd { stroke: #8b5cf6; stroke-dasharray: 5 3; stroke-width: 1.8; }
  .rpr-nokta { fill: #fff; stroke: var(--amber); stroke-width: 2; }
  .rpr-nokta.std { fill: var(--amber); }
  .rpr-nokta.sd { fill: #fff; stroke: #8b5cf6; stroke-width: 1.6; }
  .rpr-ev { font-size: 9px; }
  .rpr-karsilastirma { margin: .3rem 0 0; font-size: .93rem; }
  .rpr-katilim { display: flex; gap: 4px; flex-wrap: wrap; margin: .4rem 0 .2rem; }
  .rpr-gun { width: 17px; height: 17px; border-radius: 4px; display: inline-block; }
  .rpr-gun.katildi { background: var(--yesil); }
  .rpr-gun.gec { background: var(--amber); }
  .rpr-gun.gelmedi { background: var(--kirmizi); }
  .rpr-degerlendirme { border: 1px dashed var(--cizgi); border-radius: 10px; padding: .6rem .9rem; min-height: 4.5rem; }
  .rpr-cumleler { display: flex; gap: .35rem; flex-wrap: wrap; }
  .rpr-cumle { border: 1px solid var(--cizgi); background: #fff; border-radius: 999px;
               padding: .3rem .7rem; font-size: .85rem; cursor: pointer; font-family: inherit; text-align: left; }
  .rpr-cumle:hover { border-color: var(--amber); background: var(--amber-acik); }
  .rpr-cumle:disabled { opacity: .45; cursor: not-allowed; }
  @media print { .rpr-grafik { max-width: none; } .rpr-degerlendirme { border-style: solid; } }
</style>

<script src="<?= e(asset('js/belge-duzenle.js')) ?>" defer></script>
<script <?= csp_nonce_attr() ?>>
/* Hazır cümle havuzu: düzenleme modu açıkken tıklanan cümle değerlendirme
   bölümüne paragraf olarak eklenir (yalnız ekran/PDF — kayıtlı veri değişmez). */
document.addEventListener('DOMContentLoaded', function () {
  var belge = document.querySelector('[data-belge]');
  var alan = document.getElementById('degerlendirmeAlani');
  var havuz = document.getElementById('cumleHavuzu');
  if (!belge || !alan || !havuz) { return; }
  havuz.addEventListener('click', function (ev) {
    var dugme = ev.target.closest('.rpr-cumle');
    if (!dugme) { return; }
    if (belge.getAttribute('contenteditable') !== 'true') {
      window.alert('Önce "✏️ Raporu Düzenle" düğmesiyle düzenleme modunu açın.');
      return;
    }
    var ipucu = alan.querySelector('em');
    if (ipucu) { ipucu.closest('p').remove(); }
    var p = document.createElement('p');
    p.textContent = dugme.dataset.cumle;
    alan.appendChild(p);
    belge.dispatchEvent(new Event('input', { bubbles: true }));
    dugme.disabled = true;
  });
});
</script>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
