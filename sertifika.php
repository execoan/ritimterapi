<?php
/**
 * Katılım Belgesi (sertifika) — veliye giden çıktı: marka REPORT_BRAND, kilitli dil.
 * İstenirse (olcumler=1) dönem başı/sonu metronom ölçümleri YALIN SAYI olarak eklenir;
 * yorum, değerlendirme veya sonuç iddiası içermez (CLAUDE.md §2).
 * Belge ekranda düzenlenebilir (belge-duzenle.js): takma kod yerine gerçek ad yazılabilir,
 * satır silinebilir — kayıtlı veri hiçbir zaman değişmez.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$ogrenciId = (int)($_GET['ogrenci_id'] ?? 0);
$ogrenci = student_get($ogrenciId);
if (!$ogrenci) { not_found('Öğrenci bulunamadı.'); }

$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
if (!valid_date_ymd($from)) { $from = now()->modify('-12 weeks')->format('Y-m-d'); }
if (!valid_date_ymd($to))   { $to = today(); }

$olcumlerIstendi = (string)($_GET['olcumler'] ?? '') === '1';
$rapor = report_student($ogrenciId, $from, $to);
$olcumler = [];
if ($olcumlerIstendi) {
    // "İlk ve son ölçüm" ancak en az iki ölçümle anlamlı; tek ölçümlü protokoller atlanır.
    $olcumler = array_filter(student_protocol_first_last($ogrenciId, $from, $to),
                             fn($v) => (int)$v['adet'] >= 2);
}
$belgeNo = 'RA-' . substr($to, 0, 4) . '-' . str_pad((string)$ogrenciId, 3, '0', STR_PAD_LEFT);
$gorunum = fn(bool $o) => url('sertifika.php?ogrenci_id=' . $ogrenciId . '&from=' . $from . '&to=' . $to . ($o ? '&olcumler=1' : ''));

$PAGE_TITLE = 'Katılım Belgesi — ' . $ogrenci['kod'];
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik yazdirmada-gizle">
  <h1>Katılım Belgesi</h1>
  <div class="sag">
    <a class="btn btn-golge" href="<?= e(url('raporlar.php')) ?>"><span class="emoji-sus" aria-hidden="true">←</span> Raporlar</a>
    <a class="btn btn-golge" href="<?= e(url('ogrenci.php?id=' . $ogrenciId)) ?>">Öğrenci sayfası</a>
    <?php if ($olcumlerIstendi): ?>
      <a class="btn btn-golge" href="<?= e($gorunum(false)) ?>">Ölçümsüz görünüm</a>
    <?php else: ?>
      <a class="btn btn-golge" href="<?= e($gorunum(true)) ?>">Ölçümleri ekle</a>
    <?php endif; ?>
  </div>
</div>

<div class="bilgi-kutu yazdirmada-gizle">
  Belgede öğrencinin <strong>takma kodu</strong> yazar. Yazdırmadan önce
  <strong><span class="emoji-sus" aria-hidden="true">✏️</span> Sertifikayı Düzenle</strong> düğmesiyle gerçek adı yazabilir, istemediğiniz satırı
  silebilirsiniz — <strong>kayıtlı veri değişmez</strong>. Belge veliye gider: dil kuralları
  gereği yalnız yapılanlar anlatılır, sonuç/etki iddiası eklenmez.
</div>
<?php if ($olcumlerIstendi && !$olcumler): ?>
<div class="uyari-kutu yazdirmada-gizle">
  Bu tarih aralığında en az iki ölçümü olan protokol bulunamadı; belge ölçüm bölümü olmadan hazırlandı.
</div>
<?php endif; ?>

<?php $BELGE_ETIKET = 'Sertifikayı'; require APP_DIR . '/includes/view/belge-arac-cubugu.php'; ?>

<div class="sertifika-sayfa" data-belge>
  <div class="sertifika-cerceve">
    <span class="sertifika-sus s1" aria-hidden="true">♪</span>
    <span class="sertifika-sus s2" aria-hidden="true">♫</span>
    <span class="sertifika-sus s3" aria-hidden="true">♫</span>
    <span class="sertifika-sus s4" aria-hidden="true">♪</span>
    <div class="sertifika-filigran" aria-hidden="true">♩</div>

    <svg class="sertifika-logo" viewBox="0 0 64 64" aria-hidden="true">
      <defs><linearGradient id="sgov" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#fbbf24"/><stop offset="1" stop-color="#d97706"/>
      </linearGradient></defs>
      <path d="M25 5 H39 L52.5 53.5 Q53.8 58 49.2 58 H14.8 Q10.2 58 11.5 53.5 Z" fill="url(#sgov)"/>
      <path d="M29.2 12 H34.8 L40.6 51 H23.4 Z" fill="#1e293b"/>
      <line x1="32" y1="49" x2="43.5" y2="14.5" stroke="#f8fafc" stroke-width="2.6" stroke-linecap="round"/>
      <rect x="35.4" y="26.2" width="7.4" height="5" rx="1.2" fill="#f59e0b" stroke="#92400e" stroke-width="1" transform="rotate(18 39.1 28.7)"/>
      <circle cx="32" cy="49" r="3" fill="#f8fafc"/><circle cx="32" cy="49" r="1.4" fill="#b45309"/>
      <rect x="9" y="58" width="46" height="3.2" rx="1.6" fill="#78350f"/>
    </svg>
    <div class="sertifika-marka"><?= e(tr_upper(REPORT_BRAND)) ?></div>
    <div class="sertifika-cizgi" aria-hidden="true">—&nbsp;&nbsp;♪&nbsp;&nbsp;—</div>
    <h1 class="sertifika-baslik">Katılım Belgesi</h1>

    <p class="sertifika-giris">Bu belge,</p>
    <div class="sertifika-ad"><?= e($ogrenci['kod']) ?></div>
    <p class="sertifika-metin">
      adına düzenlenmiştir. Adı geçen katılımcı,
      <strong><?= e(format_date_tr($from, false)) ?> – <?= e(format_date_tr($to, false)) ?></strong>
      tarihleri arasında düzenlenen ritim atölyesi programına katılmıştır.
    </p>
    <?php if ((int)$rapor['toplam'] > 0): ?>
    <p class="sertifika-metin">
      Bu dönemde yoklama alınan <strong><?= (int)$rapor['toplam'] ?></strong> oturumun
      <strong><?= (int)$rapor['gelen'] ?></strong> tanesinde atölyede bulunmuştur
      (katılım oranı <strong>%<?= (int)$rapor['oran'] ?></strong>).
    </p>
    <?php endif; ?>

    <?php if ($olcumler): ?>
    <div class="sertifika-olcumler">
      <div class="sertifika-olcum-baslik">Dönem Başı ve Dönem Sonu Metronom Ölçümleri</div>
      <table>
        <thead>
          <tr><th scope="col" class="sol">Çalışma</th><th scope="col">Dönem başı</th><th scope="col">Dönem sonu</th></tr>
        </thead>
        <tbody>
          <?php $karisikVar = false;
          foreach ($olcumler as $pKod => $v):
              if (empty($v['standart'])) { $karisikVar = true; } ?>
          <tr>
            <td class="sol"><?= e(protokol_etiketi($pKod)) ?><?= !empty($v['standart']) ? ' 📏' : '' ?></td>
            <td><?= (int)$v['ilk'] ?> / 100 <span class="olcum-tarih">(<?= e(format_date_tr($v['ilk_tarih'], false)) ?>)</span></td>
            <td><?= (int)$v['son'] ?> / 100 <span class="olcum-tarih">(<?= e(format_date_tr($v['son_tarih'], false)) ?>)</span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="sertifika-dipnot">
        Metronom çalışmalarında kaydedilen zamanlama ölçümleridir (0–100 iç ölçek).
        Eğitsel izleme amaçlıdır; bir değerlendirme veya tanı aracı değildir.
        <?php if ($karisikVar): ?>
        📏 işaretsiz satırlarda ölçüm koşulları (tempo/zorluk) dönem içinde değişmiş olabilir.
        <?php else: ?>
        📏: iki ölçüm de aynı standart koşullarda alınmıştır.
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="sertifika-alt">
      <div class="sertifika-alan"><span>Tarih</span><?= e(format_date_tr($to, false)) ?></div>
      <div class="sertifika-alan"><span>Belge No</span><?= e($belgeNo) ?></div>
      <div class="sertifika-alan"><span>Eğitmen</span><div class="imza-cizgi"></div></div>
    </div>
  </div>
</div>

<style>
  @page { size: A4 landscape; margin: 10mm; }

  .sertifika-sayfa {
    max-width: 1050px; margin: 0 auto;
    background: linear-gradient(160deg, #fffdf4, #fdf3dc);
    border: 3px solid #b45309; border-radius: 6px; padding: 9px;
    box-shadow: 0 14px 34px rgba(120, 53, 15, .16);
  }
  .sertifika-cerceve {
    position: relative; border: 1.5px solid #d97706; border-radius: 3px;
    padding: 2rem 2.4rem 1.7rem; text-align: center; overflow: hidden;
  }
  .sertifika-sus { position: absolute; font-size: 2.1rem; color: rgba(180, 83, 9, .22); line-height: 1; }
  .sertifika-sus.s1 { top: .7rem; left: 1.1rem; }
  .sertifika-sus.s2 { top: .7rem; right: 1.1rem; }
  .sertifika-sus.s3 { bottom: .7rem; left: 1.1rem; }
  .sertifika-sus.s4 { bottom: .7rem; right: 1.1rem; }
  .sertifika-filigran {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    font-size: 21rem; color: rgba(180, 83, 9, .045); pointer-events: none; user-select: none;
  }
  .sertifika-cerceve > *:not(.sertifika-filigran):not(.sertifika-sus) { position: relative; }
  .sertifika-logo { width: 52px; height: 52px; }
  .sertifika-marka { letter-spacing: .34em; text-indent: .34em; font-weight: 700; color: #92400e; font-size: .95rem; margin-top: .45rem; }
  .sertifika-cizgi { color: #d6a354; font-size: .85rem; margin: .3rem 0 .1rem; }
  .sertifika-baslik {
    font-family: Georgia, 'Times New Roman', serif; font-weight: 700;
    font-size: 2.6rem; letter-spacing: .04em; color: #1c1917; margin: .15rem 0 .55rem;
  }
  .sertifika-giris { color: #78716c; font-style: italic; margin: .1rem 0; }
  .sertifika-ad {
    display: inline-block; min-width: 320px; max-width: 90%;
    font-family: 'Segoe Script', 'Segoe Print', cursive;
    font-size: 2rem; line-height: 1.35; color: #b45309;
    border-bottom: 2px dotted #d6a354; padding: 0 1.2rem .15rem; margin: .1rem auto .45rem;
  }
  .sertifika-metin { max-width: 640px; margin: .3rem auto; font-size: 1.02rem; line-height: 1.55; color: #44403c; }
  .sertifika-olcumler { margin: 1rem auto .1rem; max-width: 580px; }
  .sertifika-olcum-baslik {
    font-weight: 700; letter-spacing: .12em; font-size: .78rem; color: #92400e;
    text-transform: uppercase; margin-bottom: .35rem;
  }
  .sertifika-olcumler table { width: 100%; border-collapse: collapse; font-size: .93rem; color: #44403c; }
  .sertifika-olcumler th { font-weight: 600; color: #78716c; font-size: .82rem; }
  .sertifika-olcumler th, .sertifika-olcumler td { padding: .3rem .5rem; border-bottom: 1px solid #ecd9b8; text-align: center; }
  .sertifika-olcumler .sol { text-align: left; }
  .olcum-tarih { color: #a8a29e; font-size: .78rem; }
  /* Kontrast: #a8a29e beyaz uzerinde ~2,5:1 idi. Bu dipnot CLAUDE.md §2'nin
     cekince ibaresini tasiyor — okunamayan bir cekince, cekince degildir. */
  .sertifika-dipnot { font-size: .8rem; color: #57534e; margin-top: .45rem; }
  .sertifika-alt {
    display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem;
    flex-wrap: wrap; margin-top: 1.5rem; padding: 0 .5rem;
  }
  .sertifika-alan { font-size: .95rem; color: #44403c; text-align: center; }
  .sertifika-alan span {
    display: block; font-size: .7rem; letter-spacing: .14em; text-transform: uppercase;
    color: #a8a29e; margin-bottom: .3rem;
  }
  .imza-cizgi { border-bottom: 1.5px solid #78716c; width: 190px; height: 2rem; }

  @media (max-width: 640px) {
    .sertifika-cerceve { padding: 1.2rem .8rem 1.1rem; }
    .sertifika-ad { min-width: 0; font-size: 1.5rem; }
    .sertifika-baslik { font-size: 1.9rem; }
  }
  @media print {
    body { background: #fff; }
    .sertifika-sayfa { box-shadow: none; max-width: none; }
    .sertifika-cerceve { padding: 2.4rem 2.8rem 2rem; }
  }
</style>
<script src="<?= e(asset('js/belge-duzenle.js')) ?>" defer></script>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
