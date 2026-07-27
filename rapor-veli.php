<?php
/**
 * Veli raporu — kilitli dil: ne yapıldığını anlatır, kazanım/sonuç iddia etmez.
 * Serbest metin (gözlem notları) rapor öncesi dil denetiminden geçirilir ve
 * uygunsuz ifade varsa eğitmene ekranda uyarı gösterilir (CLAUDE.md §2).
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$ogrenciId = (int)($_GET['ogrenci_id'] ?? 0);
$ogrenci = student_get($ogrenciId);
if (!$ogrenci) { not_found('Öğrenci bulunamadı.'); }

$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
if (!DateTime::createFromFormat('Y-m-d', $from)) { $from = now()->modify('-8 weeks')->format('Y-m-d'); }
if (!DateTime::createFromFormat('Y-m-d', $to))   { $to = today(); }

$rapor = report_student($ogrenciId, $from, $to);
$evOzet = report_student_home($ogrenciId, $from, $to);

// Dil denetimi: gözlem notlarında kırmızı çizgi ifadeleri ara
$dilSorunlari = [];
foreach ($rapor['gozlemler'] as $g) {
    $bulunan = locked_language_flags((string)$g['gozlem_notu']);
    if ($bulunan) {
        $dilSorunlari[] = ['tarih' => $g['tarih'], 'oturum_id' => (int)$g['oturum_id'], 'kelimeler' => $bulunan];
    }
}

$PAGE_TITLE = 'Veli Raporu — ' . $ogrenci['kod'];
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik yazdirmada-gizle">
  <h1>Veli Raporu</h1>
  <div class="sag">
    <a class="btn btn-golge" href="<?= e(url('ogrenci.php?id=' . $ogrenciId)) ?>">← Öğrenciye dön</a>
  </div>
</div>

<?php if ($dilSorunlari): ?>
<div class="uyari-kutu yazdirmada-gizle">
  <strong>Dil denetimi uyarısı:</strong> Aşağıdaki gözlem notlarında veliye giden raporda kullanılmaması gereken
  ifadeler bulundu. Rapor ne yapıldığını anlatır; sonuç, etki veya değerlendirme iddiası içermez.
  Yazdırmadan önce ilgili oturum kaydına dönüp notu düzeltmeniz önerilir.
  <ul style="margin:.4rem 0 0;padding-left:1.2rem">
    <?php foreach ($dilSorunlari as $ds): ?>
    <li>
      <a href="<?= e(url('oturum.php?id=' . $ds['oturum_id'])) ?>"><?= e(format_date_tr($ds['tarih'], false)) ?> oturumu</a>:
      <em>“<?= e(implode('”, “', $ds['kelimeler'])) ?>”</em>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
<div class="bilgi-kutu yazdirmada-gizle">
  Bu şablon kilitlidir: rapor yalnız <strong>çalışılan teknikleri, katılımı ve gözlemleri</strong> aktarır.
  Başlıkta ve metinde sağlık/sonuç çağrışımı yapan ifade kullanılmaz.
</div>

<?php $BELGE_ETIKET = 'Raporu'; require APP_DIR . '/includes/view/belge-arac-cubugu.php'; ?>
<div class="kart" data-belge>
  <div class="rapor-baslik">
    <svg class="marka-logo" viewBox="0 0 64 64" aria-hidden="true">
      <defs><linearGradient id="vgov" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#fbbf24"/><stop offset="1" stop-color="#d97706"/>
      </linearGradient></defs>
      <path d="M25 5 H39 L52.5 53.5 Q53.8 58 49.2 58 H14.8 Q10.2 58 11.5 53.5 Z" fill="url(#vgov)"/>
      <path d="M29.2 12 H34.8 L40.6 51 H23.4 Z" fill="#1e293b"/>
      <line x1="32" y1="49" x2="43.5" y2="14.5" stroke="#f8fafc" stroke-width="2.6" stroke-linecap="round"/>
      <rect x="35.4" y="26.2" width="7.4" height="5" rx="1.2" fill="#f59e0b" stroke="#92400e" stroke-width="1" transform="rotate(18 39.1 28.7)"/>
      <circle cx="32" cy="49" r="3" fill="#f8fafc"/><circle cx="32" cy="49" r="1.4" fill="#b45309"/>
      <rect x="9" y="58" width="46" height="3.2" rx="1.6" fill="#78350f"/>
    </svg>
    <div>
      <div class="rapor-marka"><?= e(REPORT_BRAND) ?></div>
      <div class="rapor-tur">Katılımcı Raporu</div>
    </div>
  </div>

  <div class="rapor-meta">
    <div><strong>Katılımcı</strong> <?= e($ogrenci['kod']) ?></div>
    <div><strong>Grup</strong> <?= e($ogrenci['grup_ad'] ?: '—') ?></div>
    <div><strong>Dönem</strong> <?= e(format_date_tr($from, false)) ?> – <?= e(format_date_tr($to, false)) ?></div>
    <div><strong>Hazırlanma</strong> <?= e(format_date_tr(today(), false)) ?></div>
  </div>

  <p>Bu rapor, belirtilen dönemde ritim atölyesi oturumlarında yapılan çalışmaları ve eğitmen gözlemlerini
     özetler. Bir değerlendirme, ölçüm veya gelişim iddiası içermez.</p>

  <h2>Katılım</h2>
  <?php if ($rapor['toplam'] === 0): ?>
    <p>Bu dönemde kaydedilmiş oturum yoklaması bulunmuyor.</p>
  <?php else: ?>
    <p>
      Dönem içinde yoklaması alınan <strong><?= (int)$rapor['toplam'] ?></strong> oturumun
      <strong><?= (int)$rapor['gelen'] ?></strong>'inde atölyede bulundu
      (katılım oranı <strong>%<?= (int)$rapor['oran'] ?></strong>).
    </p>
  <?php endif; ?>

  <h2>Çalışılan teknikler</h2>
  <?php if (!$rapor['teknikler']): ?>
    <p>Bu dönemde katıldığı oturumlarda işlenen teknik kaydı bulunmuyor.</p>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th>Teknik</th><th>Kategori</th><th class="sayi">Kaç oturumda çalışıldı</th></tr></thead>
      <tbody>
        <?php foreach ($rapor['teknikler'] as $t): ?>
        <tr>
          <td><?= e($t['ad']) ?></td>
          <td><?= e($t['kategori']) ?></td>
          <td class="sayi"><?= (int)$t['oturum_adedi'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($evOzet): ?>
  <h2>Evde yapılan çalışmalar</h2>
  <p>Dönem içinde eve verilen kısa çalışmalar ve öğrencinin "yaptım" olarak işaretlediği gün sayıları:</p>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th>Çalışma</th><th class="sayi">İşaretlenen gün</th></tr></thead>
      <tbody>
        <?php foreach ($evOzet as $eo): ?>
        <tr>
          <td><?= e($eo['ad']) ?></td>
          <td class="sayi"><?= (int)$eo['gun_sayisi'] ?> gün</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <h2>Eğitmen gözlemleri</h2>
  <?php if (!$rapor['gozlemler']): ?>
    <p>Bu dönem için kayıtlı gözlem notu bulunmuyor.</p>
  <?php else: ?>
  <ul class="zaman-cizelge">
    <?php foreach ($rapor['gozlemler'] as $g):
        $sorunlu = (bool)locked_language_flags((string)$g['gozlem_notu']); ?>
    <li>
      <div class="tarih"><?= e(format_date_tr($g['tarih'])) ?></div>
      <div class="not<?= $sorunlu ? ' dil-sorunlu' : '' ?>"><?= e($g['gozlem_notu']) ?></div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <p style="margin-top:1.2rem">Sorularınız için oturum saatlerinde eğitmenle görüşebilirsiniz.</p>
  <div style="display:flex;gap:3rem;margin-top:2rem;flex-wrap:wrap">
    <div>Eğitmen: ______________________</div>
    <div>İmza: ______________________</div>
  </div>
</div>

<style>
  .dil-sorunlu { background: var(--kirmizi-acik); border-radius: 6px; padding: .1rem .3rem; }
  @media print { .dil-sorunlu { background: transparent; padding: 0; } }
</style>
<script src="<?= e(asset('js/belge-duzenle.js')) ?>" defer></script>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
