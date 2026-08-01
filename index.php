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
<meta name="description" content="Özel ders ve küçük grup ritim programları: bilimsel literatürden beslenen, kanıt düzeyi etiketli teknikler; şeffaf oturum kaydı ve raporlama.">
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
      <a href="#<?= e($b['anahtar']) ?>"><?= e(['deney' => 'Canlı Deney', 'yontem' => 'Yöntem', 'program' => 'Program',
          'bilim' => 'Bilim', 'nabiz' => 'Nabız', 'protokoller' => 'Protokoller', 'moxo' => 'MOXO',
          'galeri' => 'Galeri', 'bizkimiz' => 'Biz Kimiz', 'iletisim' => 'İletişim'][$b['anahtar']] ?? $b['baslik']) ?></a>
      <?php endforeach; ?>
    </div>
    <a class="t-btn t-btn-cerceve t-nav-kayit" href="#kayit">İletişim</a>
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

      <!-- ===== Tempoyu Yakala: gizli tempo, ayarlanamaz, çoktan seçmeli tahmin =====
           Sabit 5 saniyelik dinletme (tempodan bağımsız — her tur aynı sürede biter),
           kartın içinde gerçek zamanlı nabız (zıplayan ikon + vuruş noktaları). -->
      <div class="t-tempo-oyun" id="tempoOyun">
        <div class="t-tempo-isik" aria-hidden="true"></div>
        <p class="t-tempo-oyun-ust">🎯 TEMPOYU YAKALA</p>
        <div class="t-tempo-gorsel" id="tempoGorsel" aria-hidden="true">
          <span class="t-tempo-ikon" id="tempoIkon">🎵</span>
          <span class="t-tempo-noktalar" id="tempoNoktalar"></span>
        </div>
        <p class="t-tempo-oyun-durum" id="tempoDurum">
          Gizli bir tempo 5 saniye çalacağız — hızını göstermeyeceğiz. Sonra dört seçenekten birini işaretleyin.
        </p>
        <button type="button" class="t-btn t-btn-cerceve" id="tempoBaslat">▶ Gizli Tempoyu Çal</button>
        <div class="t-tempo-secenekler" id="tempoSecenekler" hidden></div>
        <p class="t-tempo-sonuc" id="tempoSonuc" hidden></p>
        <p class="t-tempo-skor" id="tempoSkor" hidden></p>
      </div>

      <a class="t-hero-davet" href="#deney">
        <span class="t-hero-davet-nokta" aria-hidden="true"></span>
        Bir vuruş kaybolacak. Yerini bulabilir misiniz? <b>Hazır mısınız? ↓</b>
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
    <p class="t-bolum-ustbaslik kayarak">7 SANİYELİK CANLI DENEY</p>
    <h2 class="kayarak">Ritim sustuğunda, vuruş sende kalır mı?</h2>
    <p class="t-bolum-aciklama kayarak">
      Dört vuruşu dinle. Beşinci vuruş sessiz kalacak. Onu tam olması gereken yerde
      tek dokunuşla sen tamamla.
    </p>

    <article class="t-tek-deney kayarak" id="sessizMeydan">
      <div class="t-tek-deney-ust">
        <span class="t-canli-rozet"><i aria-hidden="true"></i> CANLI SES</span>
        <div>
          <h3>Eksik vuruş</h3>
          <p>Kulaklık gerekmez. Sesi açman ve tek bir kez dokunman yeterli.</p>
        </div>
        <span class="t-tek-deney-sure">≈ 7 sn</span>
      </div>

      <div class="t-tek-deney-sahne">
        <div class="t-tek-deney-gorsel" aria-hidden="true">
          <div class="t-ritim-yol" id="meydanNoktalar">
            <span data-sira="0">1</span>
            <span data-sira="1">2</span>
            <span data-sira="2">3</span>
            <span data-sira="3">4</span>
            <span class="eksik" data-sira="4">?</span>
          </div>
          <div class="t-sessiz-halka" id="meydanHalka">
            <i></i><i></i><i></i>
            <strong id="meydanMerkez">HAZIR</strong>
            <small id="meydanAlt">4 ses + 1 sessizlik</small>
          </div>
        </div>

        <div class="t-tek-deney-panel">
          <p class="t-meydan-etiket" id="meydanEtiket">NASIL ÇALIŞIR?</p>
          <p class="t-meydan-durum" id="meydanDurum" role="status">
            Başlatınca dört vuruş duyacaksın. Bir sonraki vuruş sessiz olacak;
            tam o anda büyük düğmeye dokun.
          </p>
          <button type="button" class="t-btn t-btn-dolu t-btn-buyuk t-meydan-baslat" id="meydanBaslat">
            ▶ Ritmi Başlat
          </button>
          <button type="button" class="t-meydan-vur" id="meydanVur" disabled>
            <span>VURUŞU TAMAMLA</span>
            <small>boşluk tuşu da olur</small>
          </button>
          <div class="t-meydan-sonuc" id="meydanSonuc" hidden>
            <strong id="meydanSonucBaslik"></strong>
            <span id="meydanSonucDetay"></span>
          </div>
        </div>
      </div>

      <div class="t-tek-deney-alt">
        <span>🎧 Ses tarayıcında üretilir</span>
        <span>🔒 Otomatik kaydedilmez</span>
        <span>◎ Bu bir değerlendirme değildir</span>
      </div>
    </article>

    <div class="t-deney-cta kayarak">
      <p>Bir vuruşla başlıyor. Birlikte çalınca gerçek bir atölyeye dönüşüyor.</p>
      <a class="t-btn t-btn-dolu t-btn-buyuk" href="#kayit">→ İletişime Geç</a>
    </div>
  </div>
</section>

<!-- ===================== ZAMAN ÖLÇEĞİ (scroll anlatısı) ===================== -->
<section class="t-olcek" id="olcek" aria-label="Zaman ölçeği">
  <div class="t-olcek-sabit">
    <div class="t-olcek-halka" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="t-olcek-icerik">
      <p class="t-olcek-etiket" id="olcekEtiket">2 AY</p>
      <div class="t-olcek-deger" id="olcekDeger">4 838 400 000 ms</div>
      <p class="t-olcek-aciklama" id="olcekAciklama">Yoğunlaştırılmış akış: sekiz hafta, on altı oturum.</p>
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
              aria-pressed="false"
              aria-label="<?= (int)$n['bpm'] ?> BPM — <?= e($n['ad']) ?> temposunu dinle">
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
        <h3>Özel ders veya küçük grup</h3>
        <p>Bire bir çalışmada kişisel tempo ve hedefler; küçük grupta ortak nabız, sıra alma ve
           birlikte üretme öne çıkar. İki biçim de planlı bir oturum akışını izler.</p>
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

    <div class="t-program-ozet kayarak" aria-label="Program özeti">
      <div><strong>8</strong><span>hafta</span></div>
      <div><strong>16</strong><span>oturum</span></div>
      <div><strong>2</strong><span>ders biçimi</span></div>
      <div><strong>2</strong><span>yaş programı</span></div>
    </div>

    <h3 class="t-program-baslik kayarak"><span>1</span> Ders biçimini seçin</h3>
    <div class="t-ders-turu-dizi">
      <article class="t-ders-turu t-ders-turu-grup kayarak">
        <div class="t-ders-turu-ikon" aria-hidden="true">🥁</div>
        <div>
          <p class="t-ders-turu-ust">BİRLİKTE ÜRETİM</p>
          <h3>Grup Dersi</h3>
          <p>Ortak nabzı dinleme, sırayla liderlik etme ve farklı ritim katmanlarını birlikte koruma üzerine kurulur.</p>
          <ul>
            <li>Çocuk ve gençlerde 4–6 kişi</li>
            <li>Yetişkinlerde 4–8 kişi</li>
            <li>Grup senkronisi ve birlikte çalma</li>
          </ul>
          <a class="t-ders-turu-link" href="#kayit" data-ders-tercihi="grup">Grup dersini seç →</a>
        </div>
      </article>
      <article class="t-ders-turu t-ders-turu-ozel kayarak">
        <div class="t-ders-turu-ikon" aria-hidden="true">🎧</div>
        <div>
          <p class="t-ders-turu-ust">KİŞİSEL TEMPO</p>
          <h3>Özel Ders</h3>
          <p>Tempo, zorluk ve çalışma sırası katılımcıya göre ayarlanır; eğitmenle bire bir geri bildirimle ilerler.</p>
          <ul>
            <li>Kişiye uygun başlangıç temposu</li>
            <li>Esnek hedef ve ders planı</li>
            <li>Bire bir ritim, koordinasyon ve okuma çalışması</li>
          </ul>
          <a class="t-ders-turu-link" href="#kayit" data-ders-tercihi="ozel">Özel dersi seç →</a>
        </div>
      </article>
    </div>

    <h3 class="t-program-baslik kayarak"><span>2</span> Katılımcıya uygun program izini seçin</h3>
    <div class="t-iz-dizi">
      <article class="t-iz kayarak" data-tilt>
        <div class="t-iz-bas">
          <span class="t-iz-ikon">🧒</span>
          <div><h3>Çocuk &amp; Genç</h3><p class="t-iz-alt">8–15 yaş · RitimOdak-Ö izi</p></div>
        </div>
        <ul class="t-iz-liste">
          <li><b>45 dk</b> oturum · özel ders veya <b>4–6</b> kişilik grup</li>
          <li>Haftada 2 uygulama, 8 haftalık yoğunlaştırılmış akış</li>
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
          <li><b>60 dk</b> oturum · özel ders veya <b>4–8</b> kişilik grup</li>
          <li>Haftada 2 oturum, 8 haftalık yoğunlaştırılmış akış</li>
          <li>Her katılımcı bir gerçek yaşam hedefi seçer (odak bloğu, göreve dönüş…)</li>
          <li>Masa tapping seçeneği; iş/öğrenim simülasyonları</li>
          <li>Haftalık mikro uygulamalarla ev pratiği</li>
        </ul>
        <div class="t-iz-etiketler">
          <span>odak bloğu</span><span>gecikmiş tepki</span><span>görev geçişi</span><span>tempo düzenleme</span>
        </div>
      </article>
    </div>

    <h3 class="t-program-baslik kayarak"><span>3</span> Her oturumun anlaşılır bir omurgası vardır</h3>
    <p class="t-program-yardim kayarak">Aşağıdaki örnek çocuk programının 45 dakikalık akışıdır; özel derste süreler katılımcıya göre ayarlanabilir.</p>
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

    <h3 class="t-program-baslik kayarak"><span>4</span> 2 aylık yoğunlaştırılmış program haritası</h3>
    <div class="t-yolculuk kayarak">
      <?php
      $evreler = [
        ['1–2', 'Temel ritim ve güvenli katılım',
         'İlk iki hafta güven inşa etmeye ayrılır: metronoma eşlik ile sabit vuruşu bulma, vücut perküsyonuyla ısınma ve dur–devam oyunlarıyla işaretle başlayıp işaretle durma. Amaç, katılımcının çalışma alanına ve eğitmene güvenebileceği, hatanın rahat karşılandığı bir zemin kurmaktır.'],
        ['3–4', 'Bellek, koordinasyon ve seçici dikkat',
         'Ritmik dizi tekrarıyla kısa kalıpları ezberden yeniden çalma, iki el/ayak koordinasyonu ve yalnız hedef tınıya cevap verme birlikte çalışılır. Kalıplar kademeli uzar; zorluk aynı anda yalnız bir basamak yükseltilir.'],
        ['5–6', 'Dürtü kontrolü, esneklik ve çift görev',
         'Bekle–Dinle–Vur ile tepkiyi erteleme, Kural Değiştir ile yeni işarete uyum sağlama ve Çift Hat ile ritmi korurken ikinci bir görevi sürdürme çalışılır. Yoğun akışta doğruluk, hızdan önce gelir.'],
        ['7–8', 'Grup senkronisi, aktarım ve kapanış',
         'Daire Senkronisi ve Ritim Planla–Uygula–Onar ile katılımcı hem takip eden hem yön veren tarafı dener. Son aşamada ritimden sessiz göreve geçiş yapılır; çalışılanlar gözden geçirilir ve kişiye uygun devam planı oluşturulur.'],
      ];
      foreach ($evreler as $i => [$hafta, $ad, $detay]): ?>
      <div class="t-evre" tabindex="0" style="--gecikme:<?= $i * .1 ?>s">
        <span class="t-evre-nokta"></span>
        <span class="t-evre-hafta">Hafta <?= e($hafta) ?></span>
        <span class="t-evre-ad"><?= e($ad) ?></span>
        <div class="t-evre-detay"><p><?= e($detay) ?></p></div>
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
            <div><strong>2 aylık yoğunlaştırılmış atölye programı</strong>
              <p>Haftada iki oturum + kısa ev çalışmaları; atölye kendi iç ölçümlerini ayrıca tutar.</p></div>
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
        <!-- Buyutme klavyeyle de acilabilmeli: dinleyici odaklanamayan <img>
             uzerindeydi, yani yalniz fareyle calisiyordu (WCAG 2.1.1). -->
        <button type="button" class="t-galeri-btn"
                aria-label="<?= e($foto['baslik'] ?: 'Atölyeden kare') ?> — büyüt">
          <img class="t-galeri-resim" loading="lazy"
               src="<?= e(asset('img/galeri/' . basename($foto['dosya']))) ?>"
               alt="<?= e($foto['baslik'] ?: 'Atölyeden kare') ?>"
               data-baslik="<?= e($foto['baslik']) ?>">
        </button>
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
    <p class="t-not kayarak">Program ve dönem takvimi hakkında bilgi almak için iletişime geçebilirsiniz.</p>
  </div>
</section>
<?php endif; ?>
<?php endforeach; ?>

<!-- ===================== İLETİŞİM ===================== -->
<section class="t-bolum t-kayit-bolum" id="kayit">
  <div class="t-kayit-fon" aria-hidden="true"></div>
  <div class="t-kapsayici t-kayit-ic">
    <div class="kayarak">
      <p class="t-bolum-ustbaslik">İLETİŞİM</p>
      <h2>Merak ettiklerinizi sorun</h2>
      <p class="t-bolum-aciklama">
        Adınızı ve size ulaşabileceğimiz bir yolu bırakın; özel ders veya grup programı,
        uygun günler ve aklınıza takılan her şey için size dönelim.
      </p>
      <ul class="t-liste">
        <li>Ritim veya müzik geçmişi gerekmez — hiç başlamamış olmak sorun değil.</li>
        <li>Katılımcılar sistemde kod/takma adla tutulur; açık kimlik saklanmaz.</li>
        <li>Programı ve teknikleri sorularınıza göre birebir anlatırız.</li>
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
      <!-- role=alert/status: form sonucu ekran okuyucuda duyulsun (WCAG 4.1.3) -->
      <div class="t-form-flash <?= $f['type'] === 'basari' ? 'basari' : 'hata' ?>"
           role="<?= $f['type'] === 'basari' ? 'status' : 'alert' ?>">
        <?= $f['type'] === 'basari' ? '✓' : '⚠' ?> <?= e($f['msg']) ?>
      </div>
      <?php endforeach; ?>

      <h3>İletişim formu</h3>
      <label class="t-alan">
        <span>Adınız</span>
        <input type="text" name="ad" maxlength="80" required autocomplete="name"
               placeholder="Nasıl hitap edelim?">
      </label>
      <label class="t-alan">
        <span>Telefon veya e-posta</span>
        <!-- Alan tek: telefon VEYA e-posta kabul ediyor. WCAG 1.3.5 tek bir
             autocomplete jetonu ister; ikisini birden yazamayiz. Alani ikiye
             bolmek model tarafinda degisiklik gerektirir (tek sutun) — su an
             en dogru jeton "tel" degil, genel iletisim oldugu icin verilmiyor;
             bunun yerine inputmode ve aciklama ile yardim ediliyor. -->
        <input type="text" name="iletisim" maxlength="120" required
               aria-describedby="iletisimIpucu" placeholder="Size nasıl ulaşalım?">
        <small id="iletisimIpucu" class="t-alan-ipucu">Telefon veya e-posta yazabilirsiniz.</small>
      </label>
      <label class="t-alan">
        <span>Kimin için?</span>
        <select name="kitle">
          <option value="belirtilmedi">Seçmek istemiyorum</option>
          <option value="cocuk">Çocuk / genç (8–15 yaş)</option>
          <option value="yetiskin">Yetişkin (18+)</option>
        </select>
      </label>
      <fieldset class="t-ders-secim">
        <legend>Ders tercihiniz</legend>
        <div class="t-ders-secim-grid">
          <label>
            <input type="radio" name="ders_turu" value="grup" required>
            <span><b>🥁 Grup dersi</b><small>Birlikte çalma ve grup senkronisi</small></span>
          </label>
          <label>
            <input type="radio" name="ders_turu" value="ozel" required>
            <span><b>🎧 Özel ders</b><small>Kişiye göre tempo ve çalışma planı</small></span>
          </label>
          <label>
            <input type="radio" name="ders_turu" value="kararsiz" required>
            <span><b>Kararsızım</b><small>Görüşmede birlikte belirleyelim</small></span>
          </label>
        </div>
      </fieldset>
      <label class="t-alan">
        <span>Eklemek istediğiniz bir şey var mı? <small>(isteğe bağlı)</small></span>
        <textarea name="mesaj" rows="3" maxlength="600" placeholder="Uygun gün/saatiniz, merak ettikleriniz…"></textarea>
      </label>
      <div class="t-profil-eklendi" id="profilEklendi" hidden>
        🎧 Canlı deneme sonucunuz talebe eklenecek: <span id="profilEklendiMetin"></span>
      </div>
      <button type="submit" class="t-btn t-btn-dolu t-btn-buyuk t-tam-genislik">Mesajımı Gönder →</button>
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
