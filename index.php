<?php
/**
 * RitimTerapi tanıtım sitesi — herkese açık tek sayfa.
 * İçerik ve bölüm sırası panelden yönetilir (Site Yönetimi): metinler
 * site_icerik, bölüm sırası/görünürlüğü site_bolumleri tablosundadır.
 * Dil kuralı: ne yapıldığı anlatılır, sonuç vaat edilmez (CLAUDE.md §2).
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

/* Ön kayıt formu — herkese açık uç: CSRF + bal küpü + model tarafında hız sınırı */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['islem'] ?? '') === 'on_kayit') {
    csrf_check('index.php');
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        // Bal küpü doldurulmuş: bot. Sessizce başarı göster, kaydetme.
        flash_set('basari', 'Talebiniz alındı.');
        redirect('index.php#kayit');
    }
    $res = pre_registration_save($_POST);
    flash_set($res['ok'] ? 'basari' : 'hata', $res['ok']
        ? 'Teşekkürler! Talebiniz bize ulaştı — en kısa sürede size döneceğiz.'
        : $res['error']);
    redirect('index.php#kayit');
}

$girisli = educator_logged_in();
$kayitFlash = flash_get();
$bolumler = site_sections(true);
$galeriFotolar = gallery_list(true);
// Galeride görsel yoksa bölüm (ve menü bağlantısı) hiç gösterilmez.
if (!$galeriFotolar) {
    $bolumler = array_values(array_filter($bolumler, fn($b) => $b['anahtar'] !== 'galeri'));
}

$heroSatirlar = preg_split('/\r\n|\r|\n/', site_text('hero_baslik', "Vuruşu bul.\nRitmi koru.\nKendi temponu keşfet."));
$heroSatirlar = array_values(array_filter(array_map('trim', $heroSatirlar), fn($s) => $s !== ''));

/** Metronom SVG (sarkaç sınıfıyla). */
function metronom_svg(string $sinif, string $gradyanId): string
{
    return '<svg class="' . $sinif . '" viewBox="0 0 64 64" aria-hidden="true">'
        . '<defs><linearGradient id="' . $gradyanId . '" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0" stop-color="#fbbf24"/><stop offset="1" stop-color="#d97706"/>'
        . '</linearGradient></defs>'
        . '<path d="M25 5 H39 L52.5 53.5 Q53.8 58 49.2 58 H14.8 Q10.2 58 11.5 53.5 Z" fill="url(#' . $gradyanId . ')"/>'
        . '<path d="M29.2 12 H34.8 L40.6 51 H23.4 Z" fill="#1e293b"/>'
        . '<g class="t-sarkac"><line x1="32" y1="49" x2="32" y2="11" stroke="#f8fafc" stroke-width="2.5" stroke-linecap="round"/>'
        . '<rect x="28.3" y="22.5" width="7.4" height="5.2" rx="1.2" fill="#f59e0b" stroke="#92400e" stroke-width="1"/></g>'
        . '<circle cx="32" cy="49" r="3" fill="#f8fafc"/><circle cx="32" cy="49" r="1.4" fill="#b45309"/>'
        . '<rect x="9" y="58" width="46" height="3.2" rx="1.6" fill="#78350f"/></svg>';
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>RitimTerapi — Ritim, Dikkat ve Öz-Düzenleme Atölyesi</title>
<meta name="description" content="Küçük grup ritim atölyeleri: bilimsel literatürden beslenen, kanıt düzeyi etiketli teknikler; şeffaf oturum kaydı ve raporlama.">
<meta name="theme-color" content="#0c0a09">
<link rel="manifest" href="<?= e(url('manifest.json')) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('img/apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/landing.css')) ?>">
</head>
<body class="tanitim">

<div class="t-ilerleme" id="tIlerleme" aria-hidden="true"></div>

<nav class="t-nav" id="tNav">
  <div class="t-kapsayici t-nav-ic">
    <a class="t-marka" href="#ust"><?= metronom_svg('t-logo', 'tg') ?><span>RitimTerapi</span></a>
    <div class="t-nav-linkler">
      <?php foreach ($bolumler as $b): ?>
      <a href="#<?= e($b['anahtar']) ?>"><?= e(['deney' => 'Deneyler', 'yontem' => 'Yöntem', 'program' => 'Program',
          'bilim' => 'Bilim', 'nabiz' => 'Nabız', 'protokoller' => 'Protokoller', 'moxo' => 'MOXO',
          'galeri' => 'Galeri', 'bizkimiz' => 'Biz Kimiz', 'iletisim' => 'İletişim'][$b['anahtar']] ?? $b['baslik']) ?></a>
      <?php endforeach; ?>
    </div>
    <a class="t-btn t-btn-cerceve t-nav-kayit" href="#kayit">Deneme oturumu</a>
    <a class="t-btn t-btn-dolu" href="<?= e(url($girisli ? 'panel.php' : 'giris.php')) ?>">
      <?= $girisli ? 'Panele Git' : 'Giriş Yap' ?>
    </a>
  </div>
</nav>

<header class="t-hero" id="ust">
  <canvas class="t-alan-tuval" id="ritimAlani" aria-hidden="true"></canvas>
  <div class="t-halka t-halka-1" aria-hidden="true"></div>
  <div class="t-halka t-halka-2" aria-hidden="true"></div>
  <div class="t-halka t-halka-3" aria-hidden="true"></div>
  <div class="t-parcaciklar" aria-hidden="true">
    <?php for ($i = 0; $i < 14; $i++): ?>
    <i style="left:<?= 4 + $i * 7 ?>%;animation-duration:<?= 7 + ($i * 137) % 9 ?>s;animation-delay:<?= ($i * 53) % 70 / 10 ?>s"></i>
    <?php endfor; ?>
  </div>
  <div class="t-notalar" aria-hidden="true">
    <span style="left:8%;animation-delay:0s">♪</span>
    <span style="left:22%;animation-delay:2.2s">♩</span>
    <span style="left:38%;animation-delay:4.4s">♬</span>
    <span style="left:64%;animation-delay:1.1s">♪</span>
    <span style="left:78%;animation-delay:3.6s">♫</span>
    <span style="left:90%;animation-delay:5.2s">♩</span>
  </div>

  <div class="t-kapsayici t-hero-ic">
    <div class="t-hero-metin kayarak gorunur">
      <p class="t-ustbaslik"><?= e(site_text('hero_ustbaslik', 'RİTİM · DİKKAT · ÖZ-DÜZENLEME')) ?></p>
      <h1>
        <?php foreach ($heroSatirlar as $i => $satir): ?>
          <?php if ($i === count($heroSatirlar) - 1): ?><em class="t-gradyan"><?= e($satir) ?></em>
          <?php else: ?><?= e($satir) ?><br><?php endif; ?>
        <?php endforeach; ?>
      </h1>
      <p class="t-aciklama"><?= e(site_text('hero_aciklama')) ?></p>
      <div class="t-cta-satir">
        <a class="t-btn t-btn-dolu t-btn-buyuk" href="<?= e(url($girisli ? 'panel.php' : 'giris.php')) ?>">
          <?= $girisli ? '→ Panele Git' : '→ Giriş Yap' ?>
        </a>
      </div>

      <!-- ===== Tempoyu Yakala: gizli tempo, ayarlanamaz, çoktan seçmeli tahmin ===== -->
      <div class="t-tempo-oyun" id="tempoOyun">
        <p class="t-tempo-oyun-ust">🎯 TEMPOYU YAKALA</p>
        <p class="t-tempo-oyun-durum" id="tempoDurum">
          Gizli bir tempo çalacağız — hızını göstermeyeceğiz. Sonra dört seçenekten birini işaretleyin.
        </p>
        <button type="button" class="t-btn t-btn-cerceve" id="tempoBaslat">▶ Gizli Tempoyu Çal</button>
        <div class="t-tempo-secenekler" id="tempoSecenekler" hidden></div>
        <p class="t-tempo-sonuc" id="tempoSonuc" hidden></p>
        <p class="t-tempo-skor" id="tempoSkor" hidden></p>
      </div>

      <a class="t-hero-davet" href="#deney">
        <span class="t-hero-davet-nokta" aria-hidden="true"></span>
        Şimdi dinleyin: saniyede kaç ses duyuyorsunuz? <b>Deneyi aç ↓</b>
      </a>
      <div class="t-ekolayzer" id="ekolayzer" aria-hidden="true">
        <i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i>
      </div>
    </div>

    <div class="t-hero-gorsel" id="heroGorsel" aria-hidden="true">
      <div class="t-vurus-halkasi" id="vurusHalkasi"></div>
      <div class="t-yorunge" aria-hidden="true"><b>🥁</b><b>🪘</b><b>🎵</b></div>
      <?= metronom_svg('t-buyuk-metronom t-sarkacli', 'hg') ?>
    </div>
  </div>

  <div class="t-kapsayici t-sayilar kayarak">
    <?php for ($i = 1; $i <= 4; $i++): ?>
    <div class="t-sayi-kart">
      <span class="t-sayi" data-hedef="<?= e(site_text('sayi_' . $i . '_deger', '0')) ?>">0</span>
      <span class="t-sayi-etiket"><?= e(site_text('sayi_' . $i . '_etiket')) ?></span>
    </div>
    <?php endfor; ?>
  </div>
</header>

<div class="t-serit" aria-hidden="true">
  <div class="t-serit-ic">
    <span><?= e(site_text('serit_metin')) ?> · </span><span><?= e(site_text('serit_metin')) ?> · </span>
  </div>
</div>

<?php foreach ($bolumler as $bolum): ?>
<?php if ($bolum['anahtar'] === 'deney'): ?>
<section class="t-bolum t-deney-bolum" id="deney">
  <div class="t-deney-fon" aria-hidden="true"></div>
  <div class="t-kapsayici">
    <p class="t-bolum-ustbaslik kayarak">ŞİMDİ DİNLEYİN</p>
    <h2 class="kayarak"><?= e($bolum['baslik']) ?></h2>
    <p class="t-bolum-aciklama kayarak"><?= e(site_text('deney_aciklama')) ?></p>

    <!-- ===== Deney 1: ritim → ton eşiği ===== -->
    <article class="t-deney kayarak" id="deney1">
      <div class="t-deney-bas">
        <span class="t-deney-no">01</span>
        <div>
          <h3>Saniyede kaç ses duyuyorsunuz?</h3>
          <p class="t-deney-alt">Vuruşlar hızlandıkça bir yerde <em>saymayı bırakır, nota duymaya başlarsınız</em>.
             O sınırı kendi kulağınızla bulun.</p>
        </div>
      </div>

      <div class="t-deney-sahne">
        <canvas class="t-dalga" id="d1Dalga" width="900" height="150" aria-hidden="true"></canvas>
        <div class="t-deney-okuma">
          <div class="t-deney-buyuk"><span id="d1Hz">4</span><small>vuruş / saniye</small></div>
          <div class="t-deney-durum" id="d1Durum">Ayrı vuruşlar — sayabilirsiniz</div>
          <div class="t-deney-nota" id="d1Nota" hidden></div>
        </div>
      </div>

      <label class="t-kaydirac-satir">
        <span>Yavaş</span>
        <input type="range" id="d1Surgu" class="t-kaydirac" min="1" max="120" value="4" step="1"
               aria-label="Saniyedeki vuruş sayısı">
        <span>Hızlı</span>
      </label>

      <div class="t-deney-butonlar">
        <button type="button" class="t-btn t-btn-dolu" id="d1Btn">▶ Deneyi Başlat</button>
        <button type="button" class="t-btn t-btn-cerceve" id="d1Otomatik">⟳ Yavaştan hızlıya otomatik</button>
        <button type="button" class="t-btn t-btn-cerceve" id="d1Isaretle">🎯 İşte burada tek sese döndü</button>
      </div>

      <p class="t-deney-bilgi">
        <strong>Neden böyle?</strong> Ritim ve nota aslında aynı olgunun iki hızı. Ayrı ayrı duyduğunuz
        vuruşlar saniyede yaklaşık <strong>20</strong>'yi geçince kulak onları tek tek ayıramaz ve
        <strong>perde (ton)</strong> olarak algılar. Atölyede çalıştığımız her şey bu eşiğin
        <em>altındaki</em> dünyada geçer: saniyeler, yarım saniyeler ve milisaniyeler.
      </p>
    </article>

    <!-- ===== Deney 2: milisaniye hassasiyeti ===== -->
    <article class="t-deney kayarak" id="deney2">
      <div class="t-deney-bas">
        <span class="t-deney-no">02</span>
        <div>
          <h3>Vuruşu ne kadar yakalıyorsunuz?</h3>
          <p class="t-deney-alt">8 vuruş çalacak. Siz de birlikte vurun — her vuruşun kaç milisaniye
             kaydığını göstereceğiz.</p>
        </div>
      </div>

      <div class="t-deney-sahne t-deney-sahne-tap">
        <button type="button" class="t-tap-pad" id="d2Pad">
          <span id="d2PadYazi">VUR</span>
          <small>boşluk tuşu da olur</small>
        </button>
        <div class="t-deney-okuma">
          <div class="t-deney-buyuk"><span id="d2Sonuc">—</span><small>ortalama sapma (ms)</small></div>
          <div class="t-deney-durum" id="d2Durum">Başlat'a basın, 4 vuruş hazırlık sayacağız.</div>
          <div class="t-sapma-serit" id="d2Serit" aria-hidden="true"></div>
        </div>
      </div>

      <div class="t-deney-butonlar">
        <button type="button" class="t-btn t-btn-dolu" id="d2Btn">▶ Başlat</button>
      </div>

      <p class="t-deney-bilgi">
        <strong>Ne anlama geliyor?</strong> İnsanlar metronoma genellikle birkaç on milisaniye
        <em>önce</em> vurur; bu bilinen ve normal bir eğilimdir. Önemli olan tek bir vuruş değil,
        vuruşların <strong>ne kadar tutarlı</strong> olduğudur. Bu sayfadaki hızlı gösterim
        cihaz gecikmesini hesaba katmaz — atölyedeki ölçümde cihaz kalibre edilir ve
        tutarlılık ayrıca hesaplanır. <em>Bu bir değerlendirme değildir.</em>
      </p>
    </article>

    <!-- ===== Deney 3: aksayanı bul ===== -->
    <article class="t-deney kayarak" id="deney3">
      <div class="t-deney-bas">
        <span class="t-deney-no">03</span>
        <div>
          <h3>Aksayanı duyabilir misiniz?</h3>
          <p class="t-deney-alt">Altı vuruşluk bir dizi çalacak. Ya kusursuz düzenli olacak,
             ya da bir vuruş azıcık kayacak. Hangisi?</p>
        </div>
      </div>

      <div class="t-deney-sahne t-deney-sahne-aksak">
        <div class="t-aksak-noktalar" id="d3Noktalar" aria-hidden="true">
          <i></i><i></i><i></i><i></i><i></i><i></i>
        </div>
        <div class="t-deney-okuma">
          <div class="t-deney-durum" id="d3Durum">Hazır olduğunuzda başlatın.</div>
          <div class="t-aksak-cevaplar" id="d3Cevaplar" hidden>
            <button type="button" class="t-btn t-btn-cerceve" data-cevap="duzenli">✓ Düzenliydi</button>
            <button type="button" class="t-btn t-btn-cerceve" data-cevap="aksak">⚠ Aksadı</button>
          </div>
          <div class="t-aksak-skor" id="d3Skor"></div>
        </div>
      </div>

      <div class="t-deney-butonlar">
        <button type="button" class="t-btn t-btn-dolu" id="d3Btn">▶ 3 Tur Oyna</button>
      </div>

      <p class="t-deney-bilgi">
        <strong>Kulak ne kadar hassas?</strong> Eğitimli dinleyiciler düzenli bir dizideki
        yüzde birkaçlık kaymayı fark edebilir. Bu bir <em>algı</em> ölçümüdür; el hareketi
        gerektirmez. Atölye yazılımında bunun kademeli zorluklu hâli var — ve programda
        bilerek <strong>çalıştırmadığımız</strong> bir ölçüm olarak duruyor, çünkü karşılaştırma
        yapabilmek için "eğitilmemiş" bir referans gerekiyor.
      </p>
    </article>

    <!-- ===== Ritim profili: üç deneyin ortak sonucu ===== -->
    <div class="t-profil" id="ritimProfil" hidden>
      <div class="t-profil-isik" aria-hidden="true"></div>
      <p class="t-profil-ustbaslik">RİTİM PROFİLİN</p>
      <div class="t-profil-rozetler" id="profilRozetler"></div>
      <p class="t-profil-metin" id="profilMetin"></p>
      <div class="t-profil-butonlar">
        <a class="t-btn t-btn-dolu t-btn-buyuk" href="#kayit" id="profilKayitBtn">→ Profilimle deneme oturumu iste</a>
        <button type="button" class="t-btn t-btn-cerceve" id="profilSifirla">↻ Deneyleri sıfırla</button>
      </div>
      <p class="t-profil-not">Bu profil yalnız tarayıcınızda oluşur; deney sonuçları hiçbir yere
         kaydedilmez. Formu doldurursanız yalnız özet cümlesi talebinize eklenir.</p>
    </div>

    <div class="t-deney-cta kayarak">
      <p><?= e(site_text('deney_cta')) ?></p>
      <a class="t-btn t-btn-dolu t-btn-buyuk" href="#kayit">→ Deneme oturumu iste</a>
    </div>
  </div>
</section>

<!-- ===================== ZAMAN ÖLÇEĞİ (scroll anlatısı) ===================== -->
<section class="t-olcek" id="olcek" aria-label="Zaman ölçeği">
  <div class="t-olcek-sabit">
    <div class="t-olcek-halka" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="t-olcek-icerik">
      <p class="t-olcek-etiket" id="olcekEtiket">12 HAFTA</p>
      <div class="t-olcek-deger" id="olcekDeger">7 257 600 000 ms</div>
      <p class="t-olcek-aciklama" id="olcekAciklama">Bir dönem böyle başlar: on iki hafta, yirmi dört oturum.</p>
      <div class="t-olcek-cubuk"><i id="olcekDolu"></i></div>
      <p class="t-olcek-ipucu">↓ kaydırmaya devam edin</p>
    </div>
  </div>
</section>

<?php elseif ($bolum['anahtar'] === 'nabiz'): ?>
<section class="t-bolum t-bolum-koyu" id="nabiz">
  <div class="t-kapsayici">
    <p class="t-bolum-ustbaslik kayarak">TEMPO HER YERDE</p>
    <h2 class="kayarak"><?= e($bolum['baslik']) ?></h2>
    <p class="t-bolum-aciklama kayarak"><?= e(site_text('nabiz_aciklama')) ?></p>

    <div class="t-nabiz-dizi kayarak" id="nabizDizi">
      <?php
      /* Her kart KENDİ temposunda atar: --sure = 60/bpm saniye. Tıklayınca o tempo çalar. */
      $nabizlar = [
        ['bpm' => 60,  'ikon' => '🌙', 'ad' => 'Ninni',            'not' => 'Uyku öncesi şarkılar bu civarda gezinir'],
        ['bpm' => 72,  'ikon' => '❤️', 'ad' => 'Dinlenik kalp',    'not' => 'Yetişkin istirahat nabzı ortalaması'],
        ['bpm' => 100, 'ikon' => '🚶', 'ad' => 'Rahat yürüyüş',    'not' => 'Adımlar da bir metronomdur'],
        ['bpm' => 120, 'ikon' => '🪩', 'ad' => 'Dans müziği',      'not' => 'İnsanların kendiliğinden seçtiği tempoya yakın'],
        ['bpm' => 140, 'ikon' => '🏃', 'ad' => 'Koşu adımı',       'not' => 'Kondisyon müziklerinin klasik aralığı'],
        ['bpm' => 176, 'ikon' => '🥁', 'ad' => 'Hızlı davul',      'not' => 'Saniyede yaklaşık üç vuruş'],
      ];
      foreach ($nabizlar as $i => $n): ?>
      <button type="button" class="t-nabiz" data-bpm="<?= (int)$n['bpm'] ?>"
              style="--sure:<?= round(60 / $n['bpm'], 3) ?>s;--gecikme:<?= $i * 0.1 ?>s"
              aria-label="<?= e($n['ad']) ?> temposunu dinle">
        <span class="t-nabiz-halka" aria-hidden="true"></span>
        <span class="t-nabiz-ikon" aria-hidden="true"><?= $n['ikon'] ?></span>
        <span class="t-nabiz-bpm"><?= (int)$n['bpm'] ?><small>BPM</small></span>
        <span class="t-nabiz-ad"><?= e($n['ad']) ?></span>
        <span class="t-nabiz-not"><?= e($n['not']) ?></span>
      </button>
      <?php endforeach; ?>
    </div>
    <p class="t-not kayarak">Karta dokunun — o tempo çalmaya başlar, tekrar dokunun susar.
       Değerler tipik aralıkları anlatır; kişiden kişiye değişir.</p>
  </div>
</section>

<?php elseif ($bolum['anahtar'] === 'yontem'): ?>
<section class="t-bolum" id="yontem">
  <div class="t-kapsayici">
    <p class="t-bolum-ustbaslik kayarak">YÖNTEM</p>
    <h2 class="kayarak"><?= e($bolum['baslik']) ?></h2>
    <div class="t-kart-dizi">
      <article class="t-kart kayarak" data-tilt>
        <div class="t-kart-ikon">🥁</div>
        <h3>Küçük grup, canlı ritim</h3>
        <p>4–6 kişilik gruplar; el davulu, pad ve beden perküsyonu. Isınma, temel zamanlama,
           haftanın hedefi ve sakinleşme — planlı bir akış.</p>
      </article>
      <article class="t-kart kayarak" data-tilt>
        <div class="t-kart-ikon">📚</div>
        <h3>Kanıt etiketli teknik kütüphanesi</h3>
        <p>Metronoma eşlikten daire senkronisine 18 teknik. Her tekniğin hedef becerisi,
           kaynağı ve <strong>güçlü / orta / zayıf / kanıt yok</strong> etiketi açıkça yazılıdır.</p>
      </article>
      <article class="t-kart kayarak" data-tilt>
        <div class="t-kart-ikon">📝</div>
        <h3>Oturum kaydı ve gözlem</h3>
        <p>Her oturumda yoklama, işlenen teknikler ve kısa gözlem notları tutulur.
           Gözlemler ne yapıldığını anlatır; etiketlemez, yorumlamaz.</p>
      </article>
      <article class="t-kart kayarak" data-tilt>
        <div class="t-kart-ikon">📊</div>
        <h3>Şeffaf raporlama</h3>
        <p>Haftalık ve dönemlik özetler; veli raporu çalışılan teknikleri, katılımı ve
           gözlemleri aktarır. <strong>Sonuç iddiası içermez</strong> — bu bir tasarım kararıdır.</p>
      </article>
    </div>
  </div>
</section>

<?php elseif ($bolum['anahtar'] === 'program'): ?>
<section class="t-bolum t-bolum-koyu" id="program">
  <div class="t-kapsayici">
    <p class="t-bolum-ustbaslik kayarak">PROGRAM</p>
    <h2 class="kayarak"><?= e($bolum['baslik']) ?></h2>
    <p class="t-bolum-aciklama kayarak"><?= e(site_text('program_aciklama')) ?></p>

    <div class="t-iz-dizi">
      <article class="t-iz kayarak" data-tilt>
        <div class="t-iz-bas">
          <span class="t-iz-ikon">🧒</span>
          <div><h3>Çocuk &amp; Genç</h3><p class="t-iz-alt">8–15 yaş · RitimOdak-Ö izi</p></div>
        </div>
        <ul class="t-iz-liste">
          <li><b>45 dk</b> oturum · <b>4–6</b> kişilik grup</li>
          <li>Haftada 2 uygulama, 12 haftalık kademeli akış</li>
          <li>Görsel kartlar, oyunlaştırılmış görevler, kısa yönergeler</li>
          <li>Ritim/müzik geçmişi gerekmez</li>
        </ul>
        <div class="t-iz-etiketler">
          <span>başla–dur</span><span>sıra bekleme</span><span>kural koruma</span><span>hata sonrası dönüş</span>
        </div>
      </article>
      <article class="t-iz kayarak" data-tilt>
        <div class="t-iz-bas">
          <span class="t-iz-ikon">🧑‍💼</span>
          <div><h3>Yetişkin</h3><p class="t-iz-alt">18+ · RitimOdak-Y izi</p></div>
        </div>
        <ul class="t-iz-liste">
          <li><b>60 dk</b> oturum · <b>4–8</b> kişilik grup</li>
          <li>Her katılımcı bir gerçek yaşam hedefi seçer (odak bloğu, göreve dönüş…)</li>
          <li>Masa tapping seçeneği; iş/öğrenim simülasyonları</li>
          <li>Haftalık mikro uygulamalarla ev pratiği</li>
        </ul>
        <div class="t-iz-etiketler">
          <span>odak bloğu</span><span>gecikmiş tepki</span><span>görev geçişi</span><span>tempo düzenleme</span>
        </div>
      </article>
    </div>

    <h3 class="t-alt-baslik kayarak">Standart oturum akışı (çocuk izi, 45 dk)</h3>
    <div class="t-akis kayarak" id="oturumAkisi">
      <?php
      $akis = [['Giriş', 4], ['Isınma', 5], ['Zamanlama', 8], ['Bilişsel hedef', 12], ['Oyun & grup', 9], ['Sakinleşme', 5], ['Kayıt', 2]];
      foreach ($akis as $i => [$ad, $dk]): ?>
      <div class="t-akis-parca" style="--buyume:<?= $dk ?>;--gecikme:<?= $i * .12 ?>s">
        <span class="t-akis-dk"><?= $dk ?>&#8217;</span>
        <span class="t-akis-ad"><?= e($ad) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <h3 class="t-alt-baslik kayarak">12 haftalık yolculuk</h3>
    <div class="t-yolculuk kayarak">
      <?php
      $evreler = [['1–2', 'Temel ritim ve güvenli katılım'], ['3–4', 'Bellek ve koordinasyon'],
                  ['5–6', 'Seçici dikkat ve dürtü kontrolü'], ['7–8', 'Esneklik ve çift görev'],
                  ['9–10', 'Senkroni ve liderlik'], ['11–12', 'Aktarım ve kapanış']];
      foreach ($evreler as $i => [$hafta, $ad]): ?>
      <div class="t-evre" style="--gecikme:<?= $i * .1 ?>s">
        <span class="t-evre-nokta"></span>
        <span class="t-evre-hafta">Hafta <?= e($hafta) ?></span>
        <span class="t-evre-ad"><?= e($ad) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="t-not kayarak">İlerleme kuralı: iki ardışık oturumda hedef doğruluk sağlanmadan zorluk yükseltilmez;
       zorlanma artarsa bir basamak geri alınır. Kaçırılan oturum telafi edilmeden hafta atlanmaz.</p>
  </div>
</section>

<?php elseif ($bolum['anahtar'] === 'bilim'): ?>
<section class="t-bolum" id="bilim">
  <div class="t-kapsayici">
    <p class="t-bolum-ustbaslik kayarak">BİLİM</p>
    <h2 class="kayarak"><?= e($bolum['baslik']) ?></h2>
    <p class="t-bolum-aciklama kayarak">
      Programın içeriği hakemli çalışmalardan türetildi. Aşağıda her çalışmanın bulgusu ve
      atölyedeki karşılığı var — kanıt düzeyi rozetiyle birlikte. Bu alan gelişen bir alan:
      bulgular <em>umut verici ama çoğu kesin değil</em>; biz de öyle söylüyoruz.
    </p>
    <div class="t-makale-dizi">
      <?php foreach (site_articles(true) as $mk): ?>
      <article class="t-makale kayarak" data-tilt>
        <span class="t-rozet t-rozet-<?= e($mk['rozet']) ?>"><?= e(KANIT_LABELS[$mk['rozet']] ?? $mk['rozet']) ?></span>
        <h3><?= e($mk['baslik']) ?></h3>
        <p class="t-kunye"><?= e($mk['kunye']) ?></p>
        <p class="t-bulgu"><?= e($mk['bulgu']) ?></p>
        <?php if (trim((string)$mk['yansima']) !== ''): ?>
        <p class="t-yansima"><strong>Atölyede:</strong> <?= e($mk['yansima']) ?></p>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
    <div class="t-durustluk kayarak">
      <div class="t-durustluk-isaret">✳</div>
      <div>
        <h3>Dürüstlük ilkemiz</h3>
        <p>"Kanıt yok" etiketi de meşrudur. Serbest doğaçlama gibi etkinlikler keyifli ve
        pedagojik olarak değerlidir; bilimsel iddia taşımaması onları değersizleştirmez.
        Bu site ve atölye <strong>tanı koymaz, tedavi vaat etmez</strong>; eğitim programıdır,
        sağlık hizmeti değildir. Klinik bir sorunuz varsa yetkili uzmana danışın.</p>
      </div>
    </div>
  </div>
</section>

<?php elseif ($bolum['anahtar'] === 'protokoller'): ?>
<section class="t-bolum t-bolum-koyu" id="protokoller">
  <div class="t-kapsayici">
    <p class="t-bolum-ustbaslik kayarak">SİSTEMİN İÇİNDE</p>
    <h2 class="kayarak"><?= e($bolum['baslik']) ?></h2>
    <p class="t-bolum-aciklama kayarak">
      Atölye yazılımının içinde, haftalık programla birlikte yürüyen ölçümlü çalışmalar var.
      Hepsi aynı gelişmiş metronom motoru üzerinde çalışır; müzik yaparken de, protokollerde de.
    </p>
    <div class="t-kart-dizi t-kart-dizi-3">
      <article class="t-kart t-kart-koyu kayarak" data-tilt>
        <div class="t-kart-ikon">🎯</div>
        <h3>Vuruş Tutturma</h3>
        <p>Metronomla birlikte vur; sonra metronom susar, sen devam edersin.
           Her vuruşun milisaniye sapması ölçülür, 0–100 skorla haftalık izlenir.</p>
      </article>
      <article class="t-kart t-kart-koyu kayarak" data-tilt>
        <div class="t-kart-ikon">🔇</div>
        <h3>Sessiz Aralık</h3>
        <p>Ölçüler sesli–sessiz döngüsünde akar: içinden saymayı sürdür,
           metronom geri döndüğünde neredesin?</p>
      </article>
      <article class="t-kart t-kart-koyu kayarak" data-tilt>
        <div class="t-kart-ikon">🎧</div>
        <h3>BPM Bulma</h3>
        <p>Gizli tempoda çalan vuruşu dinle, tempoyu sen sürdür.
           Tahminin gerçek BPM ile karşılaştırılır.</p>
      </article>
    </div>
    <p class="t-not kayarak">Protokol skorları eğitmenin iç izleme aracıdır; veli raporlarına
       sonuç iddiası olarak yansıtılmaz.</p>
  </div>
</section>

<?php elseif ($bolum['anahtar'] === 'moxo'): ?>
<section class="t-bolum" id="moxo">
  <div class="t-kapsayici">
    <p class="t-bolum-ustbaslik kayarak">İSTEĞE BAĞLI NESNEL ÖLÇÜM</p>
    <h2 class="kayarak"><?= e($bolum['baslik']) ?></h2>
    <p class="t-bolum-aciklama kayarak"><?= e(site_text('moxo_aciklama')) ?></p>

    <div class="t-moxo-dizi">
      <div class="kayarak">
        <div class="t-adimlar">
          <div class="t-adim" style="--gecikme:0s">
            <span class="t-adim-no">1</span>
            <div><strong>Ön ölçüm</strong>
              <p>Program başlamadan, standart koşullarda MOXO uygulanır.</p></div>
          </div>
          <div class="t-adim" style="--gecikme:.12s">
            <span class="t-adim-no">2</span>
            <div><strong>12 haftalık atölye programı</strong>
              <p>Haftalık oturumlar + ev çalışmaları; atölye kendi iç ölçümlerini ayrıca tutar.</p></div>
          </div>
          <div class="t-adim" style="--gecikme:.24s">
            <span class="t-adim-no">3</span>
            <div><strong>Son ölçüm ve uzman raporu</strong>
              <p>Aynı koşullarda tekrar; ön–son karşılaştırması uzman tarafından raporlanır.</p></div>
          </div>
        </div>
        <p class="t-yansima kayarak" style="margin-top:1rem"><strong>🧠 Uygulayıcı:</strong>
          <?= e(site_text('moxo_uygulayici')) ?></p>
        <div class="t-iz-etiketler kayarak" style="margin-top:.8rem">
          <span>pakete isteğe bağlı eklenir</span><span>standart test koşulları</span><span>yazılı uzman raporu</span>
        </div>
      </div>

      <div class="t-moxo-kart kayarak" data-tilt>
        <div class="t-moxo-kart-bas">MOXO d-CPT</div>
        <p class="t-moxo-kart-alt">Dört başlıkta nesnel performans indeksi</p>
        <div class="t-moxo-indeks"><i>👁</i><span>Dikkat</span></div>
        <div class="t-moxo-indeks"><i>⏱</i><span>Zamanlama</span></div>
        <div class="t-moxo-indeks"><i>⚡</i><span>Dürtüsellik</span></div>
        <div class="t-moxo-indeks"><i>🌀</i><span>Hiperaktivite</span></div>
        <div class="t-moxo-onson"><span>ÖN ÖLÇÜM</span> ⟶ <span>SON ÖLÇÜM</span></div>
      </div>
    </div>

    <div class="t-durustluk kayarak" style="margin-top:2rem">
      <div class="t-durustluk-isaret">ⓘ</div>
      <div>
        <h3>Dürüst çerçeve</h3>
        <p><?= e(site_text('moxo_not')) ?></p>
      </div>
    </div>
  </div>
</section>

<?php elseif ($bolum['anahtar'] === 'galeri'):
    if ($galeriFotolar): ?>
<section class="t-bolum" id="galeri">
  <div class="t-kapsayici">
    <p class="t-bolum-ustbaslik kayarak">GALERİ</p>
    <h2 class="kayarak"><?= e($bolum['baslik']) ?></h2>
    <div class="t-galeri kayarak">
      <?php foreach ($galeriFotolar as $foto): ?>
      <figure class="t-galeri-kart">
        <img class="t-galeri-resim" loading="lazy"
             src="<?= e(asset('img/galeri/' . basename($foto['dosya']))) ?>"
             alt="<?= e($foto['baslik'] ?: 'Atölyeden kare') ?>"
             data-baslik="<?= e($foto['baslik']) ?>">
        <?php if (trim((string)$foto['baslik']) !== ''): ?>
        <figcaption><?= e($foto['baslik']) ?></figcaption>
        <?php endif; ?>
      </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php elseif ($bolum['anahtar'] === 'bizkimiz'): ?>
<section class="t-bolum" id="bizkimiz">
  <div class="t-kapsayici t-bizkimiz">
    <div class="kayarak">
      <p class="t-bolum-ustbaslik">BİZ KİMİZ</p>
      <h2><?= e($bolum['baslik']) ?></h2>
      <p class="t-bolum-aciklama"><?= e(site_text('bizkimiz_metin')) ?></p>
      <ul class="t-liste">
        <li>Katılımcılar kod/takma adla kaydedilir; açık kimlik bilgisi tutulmaz.</li>
        <li>Her teknik kaynağıyla yazılır: makale künyesi ya da "pedagojik gelenek".</li>
        <li>Raporlar ne yapıldığını anlatır; gelişim iddiasında bulunmaz.</li>
      </ul>
      <a class="t-btn t-btn-dolu t-btn-buyuk" href="<?= e(url($girisli ? 'panel.php' : 'giris.php')) ?>">
        <?= $girisli ? '→ Panele Git' : '→ Eğitmen Girişi' ?>
      </a>
    </div>
    <div class="t-vurgu-kutu kayarak" data-tilt>
      <div class="t-vurgu-buyuk">♩ = 60</div>
      <p>Saniyede bir vuruş. Metronomun kalbi, atölyenin nabzı.</p>
      <div class="t-ekolayzer t-ekolayzer-kucuk" aria-hidden="true">
        <i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i>
      </div>
    </div>
  </div>
</section>

<?php elseif ($bolum['anahtar'] === 'iletisim'): ?>
<section class="t-bolum t-bolum-koyu" id="iletisim">
  <div class="t-kapsayici">
    <p class="t-bolum-ustbaslik kayarak">İLETİŞİM</p>
    <h2 class="kayarak"><?= e($bolum['baslik']) ?></h2>
    <div class="t-kart-dizi">
      <article class="t-kart t-kart-koyu kayarak" data-tilt>
        <div class="t-kart-ikon">📞</div><h3>Telefon</h3>
        <p><?= e(site_text('iletisim_telefon', '—')) ?></p>
      </article>
      <article class="t-kart t-kart-koyu kayarak" data-tilt>
        <div class="t-kart-ikon">✉️</div><h3>E-posta</h3>
        <p><?= e(site_text('iletisim_eposta', '—')) ?></p>
      </article>
      <article class="t-kart t-kart-koyu kayarak" data-tilt>
        <div class="t-kart-ikon">📸</div><h3>Instagram</h3>
        <p><?= e(site_text('iletisim_instagram', '—')) ?></p>
      </article>
      <article class="t-kart t-kart-koyu kayarak" data-tilt>
        <div class="t-kart-ikon">📍</div><h3>Atölye</h3>
        <p><?= e(site_text('iletisim_adres', '—')) ?></p>
      </article>
    </div>
    <p class="t-not kayarak">Deneme oturumu ve dönem takvimi için iletişime geçebilirsiniz.</p>
  </div>
</section>
<?php endif; ?>
<?php endforeach; ?>

<!-- ===================== ÖN KAYIT ===================== -->
<section class="t-bolum t-kayit-bolum" id="kayit">
  <div class="t-kayit-fon" aria-hidden="true"></div>
  <div class="t-kapsayici t-kayit-ic">
    <div class="kayarak">
      <p class="t-bolum-ustbaslik">YER AYIRT</p>
      <h2>Bir oturum deneyin — sonra karar verin</h2>
      <p class="t-bolum-aciklama">
        Deneme oturumu ücretsizdir ve hiçbir taahhüt içermez. Adınızı ve size
        ulaşabileceğimiz bir yolu bırakın; grup saatleri ve dönem takvimiyle biz size dönelim.
      </p>
      <ul class="t-liste">
        <li>Ritim veya müzik geçmişi gerekmez — hiç başlamamış olmak sorun değil.</li>
        <li>Katılımcılar sistemde kod/takma adla tutulur; açık kimlik saklanmaz.</li>
        <li>İlk oturumda ne yapıldığını görür, sorularınızı sorarsınız.</li>
      </ul>
      <p class="t-kayit-gizlilik">
        🔒 Bıraktığınız iletişim bilgisi <strong>yalnız size dönmek için</strong> kullanılır;
        bu bilgisayarda saklanır, üçüncü kişilerle paylaşılmaz, pazarlama listesine eklenmez.
      </p>
    </div>

    <form class="t-kayit-form kayarak" method="post" action="<?= e(url('index.php')) ?>#kayit">
      <?= csrf_field() ?>
      <input type="hidden" name="islem" value="on_kayit">
      <input type="hidden" name="profil" id="kayitProfil" value="">
      <!-- bal küpü: insanlar görmez, botlar doldurur -->
      <div class="t-bal-kupu" aria-hidden="true">
        <label>Web siteniz<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <?php foreach ($kayitFlash as $f): ?>
      <div class="t-form-flash <?= $f['type'] === 'basari' ? 'basari' : 'hata' ?>">
        <?= $f['type'] === 'basari' ? '✓' : '⚠' ?> <?= e($f['msg']) ?>
      </div>
      <?php endforeach; ?>

      <h3>Deneme oturumu talebi</h3>
      <label class="t-alan">
        <span>Adınız</span>
        <input type="text" name="ad" maxlength="80" required placeholder="Nasıl hitap edelim?">
      </label>
      <label class="t-alan">
        <span>Telefon veya e-posta</span>
        <input type="text" name="iletisim" maxlength="120" required placeholder="Size nasıl ulaşalım?">
      </label>
      <label class="t-alan">
        <span>Kimin için?</span>
        <select name="kitle">
          <option value="belirtilmedi">Seçmek istemiyorum</option>
          <option value="cocuk">Çocuk / genç (8–15 yaş)</option>
          <option value="yetiskin">Yetişkin (18+)</option>
        </select>
      </label>
      <label class="t-alan">
        <span>Eklemek istediğiniz bir şey var mı? <small>(isteğe bağlı)</small></span>
        <textarea name="mesaj" rows="3" maxlength="600" placeholder="Uygun gün/saatiniz, merak ettikleriniz…"></textarea>
      </label>
      <div class="t-profil-eklendi" id="profilEklendi" hidden>
        🎧 Ritim profiliniz talebe eklenecek: <span id="profilEklendiMetin"></span>
      </div>
      <button type="submit" class="t-btn t-btn-dolu t-btn-buyuk t-tam-genislik">Talebimi Gönder →</button>
      <p class="t-kayit-alt">Formu göndermek sizi hiçbir şeye bağlamaz.</p>
    </form>
  </div>
</section>

<footer class="t-alt">
  <div class="t-kapsayici">
    <p><strong>RitimTerapi</strong> — <?= e(site_text('alt_uyari')) ?></p>
    <p class="t-alt-kucuk">© <?= (int)now()->format('Y') ?> RitimTerapi ·
      <?= e(site_text('iletisim_eposta', '')) ?> · <?= e(site_text('iletisim_instagram', '')) ?></p>
  </div>
</footer>

<script src="<?= e(asset('js/landing.js')) ?>"></script>
</body>
</html>
