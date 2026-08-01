<?php
/**
 * İki El Motor Koordinasyon Stüdyosu
 * Eğitim/destek amaçlı, uzman kontrollü ritmik motor çalışma ve ölçüm aracı.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$kullanici = metronom_kullanici_anahtari();
$protokoller = motor_protokolleri_listele($kullanici);
$sonKayitlar = motor_sonuclari_son($kullanici, 20);
$ogrenciler = students_list(null, 1);

$PAGE_TITLE = 'İki El Motor Koordinasyonu';
require APP_DIR . '/includes/view/header.php';
?>
<link rel="stylesheet" href="<?= e(asset('css/motor.css')) ?>">

<div class="sayfa-baslik motor-sayfa-baslik">
  <div>
    <h1>İki El Motor Koordinasyon Stüdyosu</h1>
    <p class="alan-ipucu">Uzmanın belirlediği ritim içinde sağ ve sol el zamanlamasını ayrı ölçer.</p>
  </div>
  <span class="rozet rozet-acik">Eğitim ve uzman destek aracı</span>
</div>

<section class="motor-guvenlik" aria-labelledby="motorGuvenlikBaslik">
  <span class="emoji-sus" aria-hidden="true">🛡️</span>
  <div>
    <strong id="motorGuvenlikBaslik">Güvenli uygulama sınırı</strong>
    <p>Bu modül tanı koymaz ve bağımsız tedavi önermez. Hedef ve yoğunluk ilgili uzman tarafından belirlenmelidir.
      Ağrı, baş dönmesi, uyuşma veya olağandışı zorlanma olursa çalışma hemen durdurulmalıdır.</p>
  </div>
  <button type="button" class="btn btn-tehlike" id="motorAcilDurdur" disabled><span class="emoji-sus" aria-hidden="true">■</span> Güvenlik Durdurması</button>
</section>

<div class="motor-ana-grid">
  <section class="kart motor-protokol-kart" aria-labelledby="protokolBaslik">
    <div class="kart-baslik">
      <div>
        <h2 id="protokolBaslik">Uzman Protokol Oluşturucu</h2>
        <span class="alan-ipucu">Her protokolün hedefi ve ölçüm sınırları sürümlü olarak saklanır.</span>
      </div>
      <span class="motor-durum" id="motorKayitDurum" role="status" aria-live="polite">Hazır</span>
    </div>

    <div class="motor-sablonlar" aria-label="Hızlı başlangıç protokolleri">
      <button type="button" class="btn btn-kucuk btn-golge" data-motor-sablon="baslangic">Başlangıç koordinasyonu</button>
      <button type="button" class="btn btn-kucuk btn-golge" data-motor-sablon="eszamanli">Eşzamanlı iki el</button>
      <button type="button" class="btn btn-kucuk btn-golge" data-motor-sablon="ileri">İleri karma desen</button>
    </div>

    <div class="form-grid motor-protokol-form">
      <label class="form-alan form-genis">Kayıtlı protokol
        <select id="motorProtokolSec" class="secim">
          <option value="">— Yeni protokol —</option>
        </select>
      </label>
      <label class="form-alan form-genis">Protokol adı
        <input type="text" id="motorProtokolAd" class="girdi" maxlength="80"
               placeholder="Örn. Dönüşümlü el koordinasyonu">
      </label>
      <label class="form-alan form-genis">Uzman hedefi ve uygulama notu
        <textarea id="motorHedef" class="girdi" maxlength="300"
                  placeholder="Ölçülebilir hedef, uygulanacak koşul ve dikkat edilecek sınırlar"></textarea>
      </label>
      <label class="form-alan">Desen
        <select id="motorDesen" class="secim">
          <option value="donusumlu">L–R · dönüşümlü</option>
          <option value="ikiserli">L–L–R–R · ikişerli</option>
          <option value="capraz">L–Birlikte–R–Birlikte</option>
          <option value="eszamanli">İki el birlikte</option>
          <option value="karma">Karma koordinasyon</option>
        </select>
      </label>
      <label class="form-alan">Tempo
        <span class="motor-birim-girdi"><input type="number" id="motorBpm" class="girdi" min="30" max="180" value="60"><b>BPM</b></span>
      </label>
      <label class="form-alan">Çalışma süresi
        <select id="motorSure" class="secim">
          <option value="15">15 saniye</option>
          <option value="30" selected>30 saniye</option>
          <option value="45">45 saniye</option>
          <option value="60">1 dakika</option>
          <option value="90">1,5 dakika</option>
          <option value="120">2 dakika</option>
          <option value="180">3 dakika</option>
        </select>
      </label>
      <label class="form-alan">Hazırlık
        <select id="motorHazirlik" class="secim">
          <option value="4" selected>4 vuruş</option>
          <option value="8">8 vuruş</option>
          <option value="12">12 vuruş</option>
        </select>
      </label>
      <label class="form-alan">Değerlendirme penceresi
        <select id="motorTolerans" class="secim">
          <option value="200">Rahat · ±200 ms</option>
          <option value="140" selected>Standart · ±140 ms</option>
          <option value="100">Hassas · ±100 ms</option>
          <option value="70">İleri · ±70 ms</option>
        </select>
      </label>
    </div>
    <div class="form-butonlar">
      <button type="button" class="btn btn-golge" id="motorYeni">＋ Yeni</button>
      <button type="button" class="btn btn-birincil" id="motorProtokolKaydet">Protokolü Kaydet</button>
      <button type="button" class="btn btn-tehlike" id="motorProtokolSil" disabled>Sil</button>
    </div>
  </section>

  <section class="kart motor-oturum-kart" aria-labelledby="oturumBaslik">
    <div class="kart-baslik">
      <div>
        <h2 id="oturumBaslik">Çalışma Oturumu</h2>
        <span class="alan-ipucu">Dokunmatik ekran, F/J tuşları veya MIDI pad kullanılabilir.</span>
      </div>
      <span class="motor-kal-rozet" id="motorKalRozet">Kalibrasyon kontrol ediliyor</span>
    </div>

    <div class="motor-kalibrasyon-panel">
      <div>
        <strong><span class="emoji-sus" aria-hidden="true">⏱</span> Cihaz zamanlama kalibrasyonu</strong>
        <small id="motorKalAciklama">Puanlı ölçümden önce kullanılan ses çıkışıyla kalibre edin.</small>
      </div>
      <button type="button" class="btn btn-kucuk btn-golge" id="motorKalibre">Kalibre et</button>
    </div>
    <div class="motor-kal-sahne" id="motorKalSahne" hidden>
      <strong id="motorKalSayac">Hazırlan…</strong>
      <button type="button" class="motor-kal-pad" id="motorKalPad">DUYDUĞUNDA VUR</button>
      <span id="motorKalIlerleme">0/12</span>
      <button type="button" class="btn btn-kucuk btn-golge" id="motorKalIptal">İptal</button>
    </div>

    <div class="motor-oturum-ayar">
      <label class="form-alan">Katılımcı
        <select id="motorOgrenci" class="secim">
          <option value="">— Genel çalışma / seçilmedi —</option>
          <?php foreach ($ogrenciler as $o): ?>
          <option value="<?= (int)$o['id'] ?>"><?= e($o['kod']) ?><?= $o['grup_ad'] ? ' — ' . e($o['grup_ad']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-alan">Klik düzeyi
        <input type="range" id="motorSes" min="0" max="100" value="70" aria-label="Klik ses düzeyi">
      </label>
      <div class="form-alan">
        <span>Harici pad</span>
        <button type="button" class="btn btn-golge" id="motorMidiBagla"><span class="emoji-sus" aria-hidden="true">🎹</span> MIDI bağla</button>
        <small class="alan-ipucu" id="motorMidiDurum">C4 altı sol, C4 ve üstü sağ el kabul edilir.</small>
      </div>
    </div>

    <div class="motor-oturum-ozet" id="motorOturumOzet">
      <strong>Dönüşümlü · 60 BPM</strong>
      <span>30 sn · 4 hazırlık · ±140 ms</span>
    </div>
    <button type="button" class="btn btn-birincil motor-baslat" id="motorBaslat"><span class="emoji-sus" aria-hidden="true">▶</span> Çalışmayı Başlat</button>
  </section>
</div>

<section class="kart motor-sahne" id="motorSahne" hidden aria-labelledby="motorFaz">
  <div class="motor-sahne-ust">
    <div>
      <span class="motor-faz" id="motorFaz">Hazırlık</span>
      <strong id="motorHedefGoster">4</strong>
      <small id="motorSahneAlt">Klikleri dinle; ŞİMDİ işaretinden sonra başla.</small>
    </div>
    <div class="motor-sure" id="motorKalan">00:30</div>
  </div>
  <div class="motor-ilerleme"><span id="motorIlerleme"></span></div>
  <div class="motor-padlar">
    <button type="button" class="motor-el-pad motor-sol-pad" id="motorSolPad" data-el="L">
      <span>SOL EL</span><kbd>F</kbd><small id="motorSolCanli">hazır</small>
    </button>
    <button type="button" class="motor-el-pad motor-sag-pad" id="motorSagPad" data-el="R">
      <span>SAĞ EL</span><kbd>J</kbd><small id="motorSagCanli">hazır</small>
    </button>
  </div>
  <div class="motor-sahne-butonlar">
    <button type="button" class="btn btn-golge" id="motorNormalDurdur">Çalışmayı erken bitir</button>
    <button type="button" class="btn btn-tehlike" id="motorSahneAcil"><span class="emoji-sus" aria-hidden="true">■</span> Güvenlik Durdurması</button>
  </div>
</section>

<section class="kart motor-sonuc" id="motorSonuc" hidden aria-labelledby="motorSonucBaslik">
  <div class="kart-baslik">
    <div>
      <h2 id="motorSonucBaslik">Koordinasyon Sonucu</h2>
      <span class="alan-ipucu" id="motorSonucAciklama">Sonuçlar eğitim içi karşılaştırma içindir.</span>
    </div>
    <span class="motor-sonuc-kayit" id="motorSonucKayit">Kaydediliyor…</span>
  </div>
  <div class="motor-skor-grid">
    <div class="motor-ana-skor"><strong id="motorSkor">–</strong><span>GENEL SKOR</span></div>
    <div><strong id="motorDogruluk">–</strong><span>Doğruluk</span></div>
    <div><strong id="motorSapma">–</strong><span>Ort. mutlak sapma</span></div>
    <div><strong id="motorAsimetri">–</strong><span>Sağ–sol zaman farkı</span></div>
    <div><strong id="motorEszaman">–</strong><span>Eşzamanlılık</span></div>
    <div><strong id="motorYorgunluk">–</strong><span>Oturum eğilimi</span></div>
  </div>
  <div class="motor-el-sonuclar">
    <article>
      <h3>Sol el</h3>
      <div id="motorSolSonuc"></div>
    </article>
    <article>
      <h3>Sağ el</h3>
      <div id="motorSagSonuc"></div>
    </article>
  </div>
  <div class="motor-hata-grafik" id="motorHataGrafik" aria-label="Vuruş zamanlama sapmaları"></div>
  <p class="bilgi-kutu" id="motorSonucYorum"></p>
  <div class="form-butonlar">
    <button type="button" class="btn btn-birincil" id="motorTekrar">Aynı Protokolü Tekrarla</button>
    <button type="button" class="btn btn-golge" id="motorSonucuKapat">Sonucu Kapat</button>
  </div>
</section>

<section class="kart" aria-labelledby="motorGecmisBaslik">
  <div class="kart-baslik">
    <div>
      <h2 id="motorGecmisBaslik">Son Motor Koordinasyon Kayıtları</h2>
      <span class="alan-ipucu">Tamamlanan ve güvenlik nedeniyle durdurulan çalışmalar birlikte izlenir.</span>
    </div>
  </div>
  <div class="motor-gecmis" id="motorGecmis"></div>
</section>

<dialog class="motor-dialog" id="motorGuvenlikDialog">
  <form method="dialog">
    <h2>Güvenlik durdurması</h2>
    <p>Çalışmanın neden durdurulduğunu seçin. Bu kayıt skor üretmez.</p>
    <div class="motor-dialog-secenekler">
      <button value="agri" class="btn btn-tehlike" data-guvenlik-neden="Ağrı veya rahatsızlık">Ağrı / rahatsızlık</button>
      <button value="bas-donmesi" class="btn btn-tehlike" data-guvenlik-neden="Baş dönmesi">Baş dönmesi</button>
      <button value="yorulma" class="btn btn-tehlike" data-guvenlik-neden="Olağandışı yorulma">Olağandışı yorulma</button>
      <button value="diger" class="btn btn-tehlike" data-guvenlik-neden="Diğer güvenlik nedeni">Diğer</button>
    </div>
    <button value="iptal" class="btn btn-golge">İptal</button>
  </form>
</dialog>

<script <?= csp_nonce_attr() ?>>
window.MOTOR_STUDYO_VERI = <?= json_encode([
    'api' => url('motor-api.php'),
    'csrfToken' => csrf_token(),
    'protokoller' => $protokoller,
    'sonKayitlar' => $sonKayitlar,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="<?= e(asset('js/zamanlama-cekirdegi.js')) ?>"></script>
<script src="<?= e(asset('js/motor-koordinasyon.js')) ?>"></script>
<script src="<?= e(asset('js/motor-studyo.js')) ?>"></script>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
