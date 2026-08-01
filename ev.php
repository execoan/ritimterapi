<?php
/**
 * Öğrenci Ev Sayfası — erişim koduyla girilir (eğitmen şifresi gerekmez).
 * Haftalık ev çalışmaları, günlük "yaptım" işaretleme, etkileşimli modüller
 * (mini metronom, vuruş tutturma, ritim okuma) ve paket durumu.
 * Dil: teşvik edici, yarışmasız; klinik/sonuç iddiası yok.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$onizle = educator_logged_in() && isset($_GET['onizle']) ? (int)$_GET['onizle'] : 0;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('ev.php');
    $islem = (string)($_POST['islem'] ?? '');

    if ($islem === 'giris') {
        /*
         * Deneme kilidi SUNUCU TARAFLI (IP başına), çerez silinerek atlatılamaz.
         * Saldırgan belirli bir öğrenciyi değil HERHANGİ birini aradığı için
         * arama uzayı öğrenci sayısına bölünür — asıl kaldıraç hız sınırıdır.
         * Pencere: 5 dakikada 15 deneme.
         */
        $hs = hiz_siniri_dene('evkod', 15, 300);
        if (!$hs['izin']) {
            flash_set('hata', 'Çok fazla deneme oldu. Biraz bekleyip tekrar dene.');
            redirect('ev.php');
        }
        $ogrenci = student_by_code((string)($_POST['kod'] ?? ''));
        if ($ogrenci) {
            hiz_siniri_sifirla('evkod');
            session_regenerate_id(true);
            $_SESSION['ev_ogrenci_id'] = (int)$ogrenci['id'];
            flash_set('basari', 'Hoş geldin ' . $ogrenci['kod'] . '! 🥁');
        } else {
            flash_set('hata', 'Kod bulunamadı. Eğitmeninden aldığın 6 haneli kodu kontrol et.');
        }
        redirect('ev.php');
    }
    if ($islem === 'cikis') {
        unset($_SESSION['ev_ogrenci_id']);
        redirect('ev.php');
    }

    $evOgrenciId = (int)($_SESSION['ev_ogrenci_id'] ?? 0);
    /*
     * Öğrenci pasifleştirilmişse açık oturum YAZMAYA DEVAM ETMEMELİ.
     * Önceden 'aktif' denetimi yalnız görüntüleme dalındaydı; kod iptal edilse
     * bile mevcut oturum 12 saat boyunca kayıt üretebiliyordu.
     */
    if ($evOgrenciId > 0) {
        $ogr = student_get($evOgrenciId);
        if (!$ogr || (int)$ogr['aktif'] !== 1) {
            unset($_SESSION['ev_ogrenci_id']);
            flash_set('hata', 'Erişimin kapatılmış. Eğitmeninle görüş.');
            redirect('ev.php');
        }
    }
    if ($evOgrenciId > 0) {
        if ($islem === 'isaretle') {
            $odev = assignment_get((int)($_POST['odev_id'] ?? 0));
            if ($odev && (int)$odev['ogrenci_id'] === $evOgrenciId
                && $odev['baslangic'] <= today() && $odev['bitis'] >= today()) {
                $yapildi = completion_toggle((int)$odev['id'], today());
                flash_set($yapildi ? 'basari' : 'bilgi',
                    $yapildi ? '“' . $odev['ad'] . '” bugün için işaretlendi. 🎉' : 'İşaret geri alındı.');
            }
            redirect('ev.php');
        }
        if ($islem === 'modul_sonuc') {
            $odev = assignment_get((int)($_POST['odev_id'] ?? 0));
            $protokol = (string)($_POST['protokol'] ?? '');
            $puanliTurler = ['vurus_tutturma', 'ritim_okuma', 'icsel_ritim'];
            if ($odev
                && (int)$odev['ogrenci_id'] === $evOgrenciId
                && $odev['baslangic'] <= today() && $odev['bitis'] >= today()
                && in_array($protokol, $puanliTurler, true)
                && (string)$odev['tur'] === $protokol) {
                $skor = max(0, min(100, (int)($_POST['skor'] ?? 0)));
                protocol_result_save([
                    'ogrenci_id' => $evOgrenciId,
                    'protokol'   => $protokol,
                    'bpm'        => (int)($_POST['bpm'] ?? 0),
                    'skor'       => $skor,
                    'detay'      => (string)($_POST['detay'] ?? '{}'),
                    'notlar'     => 'Ev çalışması: ' . $odev['ad'],
                    'kaynak'     => 'ev',
                    'sd_ms'      => $_POST['sd_ms'] ?? null,
                    'kalite'     => (string)($_POST['kalite'] ?? ''),
                ]);
                completion_mark((int)$odev['id'], today(), ['skor' => $skor, 'protokol' => $protokol]);
                flash_set('basari', 'Skorun kaydedildi: ' . $skor . '/100. Bugün de yapıldı işaretlendi. ⭐');
            }
            redirect('ev.php');
        }
    }
    redirect('ev.php');
}

$evOgrenciId = $onizle ?: (int)($_SESSION['ev_ogrenci_id'] ?? 0);
$ogrenci = $evOgrenciId ? student_get($evOgrenciId) : null;
if ($ogrenci && (int)$ogrenci['aktif'] !== 1 && !$onizle) { $ogrenci = null; }

$flashlar = flash_get();
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ev Çalışmalarım | <?= e(REPORT_BRAND) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0c0a09">
<link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/landing.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/metronom.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/ev.css')) ?>">
</head>
<body class="tanitim">

<?php if (!$ogrenci): ?>
<div class="ev-giris">
  <div class="t-halka t-halka-1" aria-hidden="true"></div>
  <div class="ev-giris-kart">
    <svg class="t-logo" viewBox="0 0 64 64" style="width:56px;height:56px" aria-hidden="true">
      <defs><linearGradient id="eg" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#fbbf24"/><stop offset="1" stop-color="#d97706"/>
      </linearGradient></defs>
      <path d="M25 5 H39 L52.5 53.5 Q53.8 58 49.2 58 H14.8 Q10.2 58 11.5 53.5 Z" fill="url(#eg)"/>
      <path d="M29.2 12 H34.8 L40.6 51 H23.4 Z" fill="#1e293b"/>
      <g class="t-sarkac"><line x1="32" y1="49" x2="32" y2="12.5" stroke="#f8fafc" stroke-width="2.6" stroke-linecap="round"/>
      <rect x="28.3" y="24.2" width="7.4" height="5" rx="1.2" fill="#f59e0b" stroke="#92400e" stroke-width="1"/></g>
      <circle cx="32" cy="49" r="3" fill="#f8fafc"/><circle cx="32" cy="49" r="1.4" fill="#b45309"/>
      <rect x="9" y="58" width="46" height="3.2" rx="1.6" fill="#78350f"/>
    </svg>
    <h1 style="font-size:1.3rem;margin:.6rem 0 .2rem">Ev Çalışmalarım</h1>
    <p style="color:var(--t-soluk);font-size:.9rem;margin:0 0 1.2rem">Eğitmeninden aldığın 6 haneli kodu gir.</p>
    <?php $hataVarMi = false; foreach ($flashlar as $f): $hataVarMi = $hataVarMi || $f['type'] === 'hata'; ?>
    <!-- role="alert": hata sesli okunsun. autofocus koddaydı, bu yüzden mesaj hiç duyulmuyordu. -->
    <div class="ev-flash ev-flash-<?= e($f['type']) ?>"<?= $f['type'] === 'hata' ? ' role="alert" tabindex="-1" id="evFlashHata"' : ' role="status"' ?>><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('ev.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="islem" value="giris">
      <!-- Etiket ŞART: placeholder erişilebilir ad sayılmaz ve yazmaya başlayınca kaybolur -->
      <label for="evKod" class="ev-kod-etiket">Erişim kodun</label>
      <input type="text" name="kod" id="evKod" class="ev-kod-girdi" maxlength="8" required
             <?= $hataVarMi ? 'aria-invalid="true" aria-describedby="evFlashHata"' : 'autofocus' ?>
             autocomplete="off" inputmode="text" spellcheck="false" placeholder="ABC123">
      <button type="submit" class="t-btn t-btn-dolu t-btn-buyuk" style="width:100%;margin-top:1rem">Giriş</button>
    </form>
    <a href="<?= e(url('index.php')) ?>" style="display:block;margin-top:1rem;color:var(--t-soluk);font-size:.85rem">← Ana sayfa</a>
  </div>
</div>

<?php else:
    $odevler = assignments_active_for_student((int)$ogrenci['id']);
    $zamanlamaOdeviVar = array_reduce($odevler, static function (bool $var, array $odev): bool {
        return $var || in_array((string)$odev['tur'], ['vurus_tutturma', 'ritim_okuma', 'icsel_ritim'], true);
    }, false);
    [$pzt, $paz] = week_bounds();
    $harita = completions_map(array_map(fn($o) => (int)$o['id'], $odevler), $pzt, $paz);
    $seri = streak_for_student((int)$ogrenci['id']);
    $paket = package_active((int)$ogrenci['id']);
    $grupProgramlari = student_group_programs((int)$ogrenci['id']);
    $bugun = today();
?>
<div class="ev-govde">
  <div class="ev-ust">
    <svg class="t-logo" viewBox="0 0 64 64" aria-hidden="true">
      <defs><linearGradient id="eg2" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#fbbf24"/><stop offset="1" stop-color="#d97706"/>
      </linearGradient></defs>
      <path d="M25 5 H39 L52.5 53.5 Q53.8 58 49.2 58 H14.8 Q10.2 58 11.5 53.5 Z" fill="url(#eg2)"/>
      <path d="M29.2 12 H34.8 L40.6 51 H23.4 Z" fill="#1e293b"/>
      <g class="t-sarkac"><line x1="32" y1="49" x2="32" y2="12.5" stroke="#f8fafc" stroke-width="2.6" stroke-linecap="round"/>
      <rect x="28.3" y="24.2" width="7.4" height="5" rx="1.2" fill="#f59e0b" stroke="#92400e" stroke-width="1"/></g>
      <circle cx="32" cy="49" r="3" fill="#f8fafc"/><circle cx="32" cy="49" r="1.4" fill="#b45309"/>
      <rect x="9" y="58" width="46" height="3.2" rx="1.6" fill="#78350f"/>
    </svg>
    <div>
      <h1>Ev Çalışmalarım</h1>
      <div class="ev-kim">Merhaba <strong><?= e($ogrenci['kod']) ?></strong> 👋<?= $onizle ? ' · (eğitmen önizlemesi)' : '' ?></div>
    </div>
    <?php if (!$onizle): ?>
    <form method="post" action="<?= e(url('ev.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="islem" value="cikis">
      <button type="submit" class="t-btn t-btn-cerceve">Çıkış</button>
    </form>
    <?php endif; ?>
  </div>

  <?php foreach ($flashlar as $f): ?>
  <div class="ev-flash ev-flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>

  <?php if ($seri > 0): ?>
  <p><span class="ev-seri">🔥 Üst üste <?= $seri ?> gün çalıştın</span></p>
  <?php endif; ?>

  <?php if ($grupProgramlari): ?>
  <section class="ev-kart ev-program-karti">
    <div class="ev-program-baslik">
      <div>
        <h2>📅 Derslerim ve grup programım</h2>
        <p class="aciklama">Ders saatlerini, yaklaşan çalışmaları ve grup arkadaşlarının takma adlarını burada görebilirsin.</p>
      </div>
      <span class="ev-program-sayi"><?= count($grupProgramlari) ?> ders</span>
    </div>
    <div class="ev-program-listesi">
    <?php foreach ($grupProgramlari as $ders): ?>
      <article class="ev-program-ders">
        <div class="ev-program-ders-ust">
          <div>
            <strong><?= e($ders['ad']) ?></strong>
            <span><?= e(GRUP_TUR_LABELS[$ders['tur'] ?? 'grup']) ?></span>
          </div>
          <time><?= e(GUNLER[(int)$ders['gun']] ?? '') ?><?= $ders['saat'] ? ' · ' . e($ders['saat']) : '' ?></time>
        </div>
        <div class="ev-uyeler" aria-label="Grup üyeleri">
          <small><?= ($ders['tur'] ?? 'grup') === 'ozel' ? 'Katılımcı' : 'Grup üyeleri' ?></small>
          <?php foreach ($ders['uyeler'] as $uye): ?>
            <span class="<?= (int)$uye['kendisi'] === 1 ? 'kendisi' : '' ?>">
              <?= e($uye['kod']) ?><?= (int)$uye['kendisi'] === 1 ? ' · sen' : '' ?>
            </span>
          <?php endforeach; ?>
        </div>
        <?php if (!empty($ders['duyurular'])): ?>
          <div class="ev-duyurular" aria-label="Grup duyuruları">
            <small>📣 Duyurular</small>
            <?php foreach ($ders['duyurular'] as $duyuru): ?>
              <aside class="ev-duyuru">
                <strong><?= e($duyuru['baslik']) ?></strong>
                <?php if ($duyuru['mesaj']): ?><p><?= nl2br(e($duyuru['mesaj'])) ?></p><?php endif; ?>
              </aside>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if (!$ders['program']): ?>
          <p class="ev-program-bos">Henüz yaklaşan bir oturum planlanmamış.</p>
        <?php else: ?>
          <ol class="ev-oturum-programi">
          <?php foreach ($ders['program'] as $oturum): ?>
            <li>
              <div>
                <time><?= e(format_date_tr($oturum['tarih'])) ?></time>
                <small>Hafta <?= (int)$oturum['hafta_no'] ?><?= (int)$oturum['sure_dk'] > 0 ? ' · ' . (int)$oturum['sure_dk'] . ' dk' : '' ?></small>
              </div>
              <span><?= e($oturum['teknikler'] ?: 'İçerik yakında eklenecek') ?></span>
            </li>
          <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
    </div>
    <p class="ev-mahremiyet">🔒 Grup arkadaşların yalnızca takma adını görür. Kişisel notların, yaş bilgin, gözlemlerin ve skorların paylaşılmaz.</p>
  </section>
  <?php endif; ?>

  <?php if ($zamanlamaOdeviVar): ?>
  <section class="ev-kart ev-zaman-kalite" id="evZamanKalite">
    <div class="ev-zaman-ust">
      <div>
        <h2>⏱ Cihaz zamanlama ayarı</h2>
        <p class="aciklama">Bu ayar bütün vuruş çalışmalarının aynı ve adil ölçülmesini sağlar.</p>
      </div>
      <span class="ev-zaman-rozet" id="evZkRozet">Kontrol ediliyor</span>
    </div>
    <p class="ev-zaman-ozet" id="evZkOzet">Kalibrasyon bilgisi okunuyor…</p>
    <div class="ev-buton-satir">
      <button type="button" class="t-btn t-btn-dolu" id="evZkKalibre">🎧 Kalibre et</button>
      <button type="button" class="t-btn t-btn-cerceve" id="evZkSifirla">Sıfırla</button>
    </div>
    <div class="ev-zaman-sahne" id="evZkSahne" hidden>
      <strong id="evZkSayac">Hazırlan…</strong>
      <small id="evZkIlerleme">0/12</small>
      <button type="button" class="m-pad ro-pad" id="evZkPad">VUR<small>duyduğun her klikte</small></button>
      <button type="button" class="t-btn t-btn-cerceve" id="evZkIptal">İptal</button>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!$odevler): ?>
  <div class="ev-kart">
    <h2>Bu hafta atanmış çalışma yok</h2>
    <p class="aciklama">Eğitmenin yeni çalışmalar atadığında burada görünecek. 🎵</p>
  </div>
  <?php endif; ?>

  <?php foreach ($odevler as $odev):
      $oid = (int)$odev['id'];
      $buHafta = 0;
      $bugunYapildi = isset($harita[$oid][$bugun]);
      foreach ($harita[$oid] ?? [] as $t => $_) { $buHafta++; }
  ?>
  <div class="ev-kart" data-gorev="<?= e($odev['tur'] === 'vurus_tutturma' ? 'vurus'
        : ($odev['tur'] === 'ritim_okuma' ? 'ritim' : ($odev['tur'] === 'icsel_ritim' ? 'icsel' : $odev['tur']))) ?>"
       data-bpm="<?= (int)$odev['bpm'] ?>" data-sure="<?= (int)$odev['sure_dk'] ?>"
       data-seviye="<?= (int)$odev['seviye'] ?>" <?= $bugunYapildi ? 'data-bugun-yapildi="1"' : '' ?>>
    <h2><?= e($odev['ad']) ?></h2>
    <div class="ev-etiketler">
      <span>⏱ <?= (int)$odev['sure_dk'] ?> dk</span>
      <span>🎯 haftada <?= (int)$odev['hedef_gun'] ?> gün</span>
      <?php if ($odev['hedef_beceri']): ?><span><?= e($odev['hedef_beceri']) ?></span><?php endif; ?>
    </div>
    <p class="aciklama"><?= e($odev['aciklama']) ?></p>
    <?php if (trim((string)$odev['notlar']) !== ''): ?>
    <p class="aciklama">📝 Eğitmen notu: <?= e($odev['notlar']) ?></p>
    <?php endif; ?>
    <?php if (trim((string)$odev['veli_yonerge']) !== ''): ?>
    <p class="veli">👨‍👩‍👧 Veliye: <?= e($odev['veli_yonerge']) ?></p>
    <?php endif; ?>

    <div class="ev-hafta" title="Bu hafta: <?= $buHafta ?>/<?= (int)$odev['hedef_gun'] ?> gün">
      <?php for ($g = 0; $g < 7; $g++):
          $t = (new DateTime($pzt))->modify('+' . $g . ' days')->format('Y-m-d'); ?>
      <span class="ev-gun<?= isset($harita[$oid][$t]) ? ' yapildi' : '' ?><?= $t === $bugun ? ' bugun' : '' ?>">
        <?= e(GUNLER_KISA[$g + 1]) ?>
      </span>
      <?php endfor; ?>
      <span style="color:var(--t-soluk);font-size:.82rem"><?= $buHafta ?>/<?= (int)$odev['hedef_gun'] ?></span>
    </div>

    <?php if ($odev['tur'] === 'metronom'): ?>
    <div class="ev-buton-satir">
      <button type="button" class="t-btn t-btn-dolu ev-gorev-baslat">▶ Başlat</button>
      <span class="ev-sayac"></span>
    </div>
    <?php elseif ($odev['tur'] === 'vurus_tutturma' || $odev['tur'] === 'icsel_ritim'): ?>
    <div class="ev-buton-satir">
      <button type="button" class="t-btn t-btn-dolu ev-gorev-baslat">▶ Teste Başla</button>
      <button type="button" class="m-pad ro-pad" hidden>VUR<small>boşluk / dokun</small></button>
      <span class="ev-sayac"></span>
    </div>
    <?php elseif ($odev['tur'] === 'ritim_okuma'): ?>
    <div class="ro-kok"></div>
    <?php endif; ?>

    <div class="ev-buton-satir" style="margin-top:.7rem">
      <form method="post" action="<?= e(url('ev.php')) ?>" class="ev-tamamla-form">
        <?= csrf_field() ?>
        <input type="hidden" name="islem" value="isaretle">
        <input type="hidden" name="odev_id" value="<?= $oid ?>">
        <button type="submit" class="t-btn <?= $bugunYapildi ? 't-btn-cerceve' : 't-btn-dolu' ?>">
          <?= $bugunYapildi ? '✓ Bugün yapıldı — geri al' : '✓ Bugün yaptım' ?>
        </button>
      </form>
      <?php if (in_array($odev['tur'], ['vurus_tutturma', 'ritim_okuma', 'icsel_ritim'], true)): ?>
      <form method="post" action="<?= e(url('ev.php')) ?>" class="ev-sonuc-form" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="islem" value="modul_sonuc">
        <input type="hidden" name="odev_id" value="<?= $oid ?>">
        <input type="hidden" name="protokol" value="<?= e($odev['tur']) ?>">
        <input type="hidden" name="skor" value="">
        <input type="hidden" name="bpm" value="">
        <input type="hidden" name="detay" value="">
        <input type="hidden" name="sd_ms" value="">
        <input type="hidden" name="kalite" value="">
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if ($paket): ?>
  <div class="ev-kart">
    <h2>🎫 Ders paketim</h2>
    <!-- MOXO rozeti BİLEREK yok: klinik bir test markasını çocuğun kendi
         sayfasında, hiçbir çerçeve olmadan göstermek CLAUDE.md §2'nin klinik
         etiket yasağına giriyor. Bilgi eğitmen panelinde (ogrenci.php) duruyor. -->
    <p class="aciklama"><?= e($paket['ad']) ?>
       — <?= (int)$paket['kullanilan'] ?>/<?= (int)$paket['toplam_seans'] ?> oturum kullanıldı</p>
    <div class="ev-paket-cubuk">
      <div class="ev-paket-dolu" style="width:<?= min(100, (int)round(100 * $paket['kullanilan'] / max(1, (int)$paket['toplam_seans']))) ?>%"></div>
    </div>
    <!-- Yenileme hatırlatması BİLEREK yok: ticari bir uyarıyı çocuk üzerinden
         kurmak doğru değil; eğitmen panelinde zaten "Yenileme yaklaşıyor" rozeti var. -->
    <p class="aciklama"><?= (int)$paket['kalan'] ?> oturum kaldı.</p>
  </div>
  <?php endif; ?>

  <p class="ev-alt-not">Skorlar yalnız kendi kaydın içindir; yarışma değildir; yarışma değildir.
     Kısa ve düzenli çalışmak, uzun tek seferden daha değerlidir. 🥁</p>
</div>
<?php endif; ?>

<script src="<?= e(asset('js/zamanlama-cekirdegi.js')) ?>"></script>
<script src="<?= e(asset('js/ritim-ogrenme.js')) ?>"></script>
<script src="<?= e(asset('vendor/abcjs/abcjs-basic-min.js')) ?>"></script>
<script src="<?= e(asset('js/ritim-okuma.js')) ?>"></script>
<script src="<?= e(asset('js/ev.js')) ?>"></script>
</body>
</html>
