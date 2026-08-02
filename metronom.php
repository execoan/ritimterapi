<?php
/**
 * Metronom Stüdyosu — gelişmiş metronom + dikkat protokolleri.
 * v4: swing/shuffle, 12/8'e kadar ölçü grupları, sayarak giriş, alt bölünmeler,
 * vuruş başına aksan deseni, tempo trainer, görsel poliritim, genişletilmiş
 * ses kiti (seçimde önizleme), flaş modu, preset'ler.
 * Protokoller: Vuruş Tutturma, BPM Bulma, Ritim Okuma, Spontan Tempo,
 * Aksak Vuruş Algısı (BAASTA türü görevlerden uyarlama).
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('metronom.php');
    $islem = (string)($_POST['islem'] ?? '');
    if ($islem === 'sonuc_kaydet') {
        $res = protocol_result_save($_POST);
        flash_set($res['ok'] ? 'basari' : 'hata',
            $res['ok'] ? 'Protokol sonucu kaydedildi.' : $res['error']);
    } elseif ($islem === 'sonuc_sil') {
        protocol_result_delete((int)($_POST['id'] ?? 0));
        flash_set('basari', 'Kayıt silindi.');
    }
    redirect('metronom.php');
}

$ogrenciler = students_list(null, 1);
$sonKayitlar = protocol_results_recent(12);
$sonSkorlar = protocol_last_scores();
$acilacakProtokol = (string)($_GET['protokol'] ?? '');
if (!isset(PROTOKOL_LABELS[$acilacakProtokol])) { $acilacakProtokol = ''; }
/* Kendi sayfasına taşınan protokoller: eski derin bağlantılar (oturum.php'deki
   "Protokolü stüdyoda aç" gibi) kırılmasın diye yönlendirilir. */
const PROTOKOL_SAYFALARI = ['ritim_okuma' => 'ritim-okuma.php', 'poliritim' => 'poliritim.php'];
if (isset(PROTOKOL_SAYFALARI[$acilacakProtokol])) {
    $hedef = PROTOKOL_SAYFALARI[$acilacakProtokol];
    if (($a = (int)($_GET['akis'] ?? 0)) > 0) { $hedef .= '?akis=' . $a; }
    redirect($hedef);
}

/* Ders akışı: bir oturumun teknik planını sırayla, süre sayaçlarıyla çalıştır */
$akisOturum = null;
$akisId = (int)($_GET['akis'] ?? 0);
if ($akisId > 0) {
    $o = session_get($akisId);
    if ($o) {
        $akisOturum = [
            'id' => (int)$o['id'],
            'grup' => (string)$o['grup_ad'],
            'tarih' => (string)$o['tarih'],
            'hafta_no' => (int)$o['hafta_no'],
            'protokol' => (string)($o['protokol'] ?? ''),
            'teknikler' => array_map(fn($t) => [
                'ad' => (string)$t['ad'],
                'sure_dk' => (int)$t['sure_dk'],
                'not' => (string)$t['uygulama_notu'],
            ], session_techniques($akisId)),
        ];
        if (!$akisOturum['teknikler']) { $akisOturum = null; }
    }
}
// Akış seçici: son 7 gün + önümüzdeki 21 günün planlı oturumları
$akisSecenekler = array_reverse(array_values(array_filter(
    sessions_list(null, now()->modify('-7 days')->format('Y-m-d'), now()->modify('+21 days')->format('Y-m-d')),
    fn($s) => (int)$s['teknik_sayisi'] > 0
)));

/* Hesaba bağlı setlistler ve otomatik çalışma günlüğü */
$metronomKullanici = metronom_kullanici_anahtari();
$metronomSetleri = metronom_setleri_listele($metronomKullanici);
$metronomOzet = metronom_calisma_ozeti($metronomKullanici);

/** Öğrenci seçme kutusu (her protokol sekmesinde aynı). */
function ogrenci_secimi(string $id, array $ogrenciler): string
{
    $html = '<select id="' . e($id) . '" class="secim"><option value="">— Seçin (kayıt için) —</option>';
    foreach ($ogrenciler as $o) {
        $html .= '<option value="' . (int)$o['id'] . '">' . e($o['kod'])
               . ($o['grup_ad'] ? ' — ' . e($o['grup_ad']) : '') . '</option>';
    }
    return $html . '</select>';
}

/** Gizli sonuç formu (JS doldurup gönderir). */
function sonuc_formu(string $onek, string $protokol): string
{
    return '<form method="post" action="' . e(url('metronom.php')) . '" class="m-kaydet-form" id="' . e($onek) . 'Form">'
        . csrf_field()
        . '<input type="hidden" name="islem" value="sonuc_kaydet">'
        . '<input type="hidden" name="protokol" value="' . e($protokol) . '">'
        . '<input type="hidden" name="ogrenci_id" id="' . e($onek) . 'FormOgrenci" value="">'
        . '<input type="hidden" name="bpm" id="' . e($onek) . 'FormBpm" value="">'
        . '<input type="hidden" name="skor" id="' . e($onek) . 'FormSkor" value="">'
        . '<input type="hidden" name="detay" id="' . e($onek) . 'FormDetay" value="">'
        . '<input type="hidden" name="standart" id="' . e($onek) . 'FormStandart" value="0">'
        . '<input type="hidden" name="sd_ms" id="' . e($onek) . 'FormSd" value="">'
        . '<input type="hidden" name="kalite" id="' . e($onek) . 'FormKalite" value="">'
        . '<input type="text" name="notlar" class="girdi" maxlength="200" placeholder="Not (isteğe bağlı)" style="max-width:260px">'
        . '<button type="submit" class="btn btn-birincil">Sonucu Kaydet</button>'
        . '</form>';
}

$PAGE_TITLE = 'Metronom Stüdyosu';
require APP_DIR . '/includes/view/header.php';
?>
<link rel="stylesheet" href="<?= e(asset('css/metronom.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/ev.css')) ?>">

<div class="sayfa-baslik">
  <h1>Metronom Stüdyosu</h1>
  <span class="rozet rozet-acik">Müzikte ve protokollerde aynı motor</span>
</div>

<!-- ==================== METRONOM ==================== -->
<div class="kart m-sahne" id="mSahne">
  <div class="m-flas" id="mFlas" aria-hidden="true"></div>
  <!-- Duyuru ayri bir gizli bolgede (metronom.js durumGuncelle): gorsel metin
       her vuruste degisiyor, ekran okuyucuya gecikmeli ve tekrarsiz gider. -->
  <div class="gorsel-gizli" id="mDurumDuyuru" role="status" aria-live="polite"></div>
  <div class="m-durum" id="mDurum">
    <span class="m-durum-nokta" aria-hidden="true"></span>
    <span id="mDurumMetin">Hazır · ayarlar bu cihazda hatırlanır</span>
  </div>
  <div class="m-ust">
    <div class="m-gorsel">
      <div class="m-halka" id="mHalka"></div>
      <div class="m-sayac" id="mSayac" aria-hidden="true">–</div>
      <svg class="m-metronom" viewBox="0 0 64 64" aria-hidden="true">
        <defs><linearGradient id="mg2" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#fbbf24"/><stop offset="1" stop-color="#d97706"/>
        </linearGradient></defs>
        <path d="M25 5 H39 L52.5 53.5 Q53.8 58 49.2 58 H14.8 Q10.2 58 11.5 53.5 Z" fill="url(#mg2)"/>
        <path d="M29.2 12 H34.8 L40.6 51 H23.4 Z" fill="#1e293b"/>
        <g id="mSarkac" class="m-sarkac">
          <line x1="32" y1="49" x2="32" y2="11.5" stroke="#f8fafc" stroke-width="2.5" stroke-linecap="round"/>
          <rect x="28.3" y="23" width="7.4" height="5.2" rx="1.2" fill="#f59e0b" stroke="#92400e" stroke-width="1"/>
        </g>
        <circle cx="32" cy="49" r="3" fill="#f8fafc"/><circle cx="32" cy="49" r="1.4" fill="#b45309"/>
        <rect x="9" y="58" width="46" height="3.2" rx="1.6" fill="#78350f"/>
      </svg>
    </div>

    <div class="m-kontrol">
      <div class="m-bpm-satir">
        <button type="button" class="m-mini-btn" data-bpm-degistir="-5">−5</button>
        <button type="button" class="m-mini-btn" data-bpm-degistir="-1">−1</button>
        <div class="m-bpm-goster">
          <span id="mBpm">92</span>
          <small><b id="mBirimSembol">♩</b> BPM · <em id="mTempoAdi">Andante</em></small>
        </div>
        <button type="button" class="m-mini-btn" data-bpm-degistir="1">+1</button>
        <button type="button" class="m-mini-btn" data-bpm-degistir="5">+5</button>
      </div>
      <input type="range" id="mBpmSurgu" min="30" max="240" value="92" step="1" class="m-surgu" aria-label="Tempo">

      <div class="m-nokta-dizi" id="mNoktalar" aria-label="Aksan deseni: noktaya tıkla — aksan → normal → sessiz"></div>
      <p class="alan-ipucu" style="text-align:center;margin:-.3rem 0 .6rem">
        Noktalara tıkla: <strong>aksan</strong> → normal → sessiz
      </p>
      <div class="m-poli-satir" id="mPoliSatir" hidden>
        <span class="m-poli-etiket" id="mPoliEtiket">3:4</span>
        <div class="m-poli-noktalar" id="mPoliNoktalar" aria-label="Poliritim katmanı"></div>
        <label class="m-poli-duzey">Katman
          <input type="range" id="mPoliDuzey" min="10" max="100" value="55"
                 aria-label="Poliritim ses düzeyi">
        </label>
      </div>

      <div class="m-ayarlar">
        <label class="m-ayar">Ölçü
          <select id="mOlcu" class="secim">
            <option value="2" data-payda="4">2/4</option>
            <option value="3" data-payda="4">3/4</option>
            <option value="4" data-payda="4" selected>4/4</option>
            <option value="5" data-payda="4">5/4</option>
            <option value="6" data-payda="8">6/8</option>
            <option value="7" data-payda="8">7/8</option>
            <option value="8" data-payda="8">8/8</option>
            <option value="9" data-payda="8">9/8</option>
            <option value="11" data-payda="8">11/8</option>
            <option value="12" data-payda="8">12/8</option>
          </select>
        </label>
        <label class="m-ayar">Vuruş grubu
          <select id="mGruplama" class="secim" aria-label="Ölçü içi vuruş gruplaması"></select>
        </label>
        <label class="m-ayar">Alt bölünme
          <select id="mAltBolunme" class="secim">
            <option value="1" selected><span class="emoji-sus" aria-hidden="true">♩</span> Çeyrek</option>
            <option value="2"><span class="emoji-sus" aria-hidden="true">♫</span> Sekizlik</option>
            <option value="3"><span class="emoji-sus" aria-hidden="true">③</span> Üçleme</option>
            <option value="4"><span class="emoji-sus" aria-hidden="true">♬</span> Onaltılık</option>
          </select>
        </label>
        <label class="m-ayar">Swing / shuffle
          <select id="mSwing" class="secim">
            <option value="50" selected>Düz · %50</option>
            <option value="55">Hafif · %55</option>
            <option value="60">Orta · %60</option>
            <option value="66.6667">Üçleme · %66,7</option>
            <option value="70">Yoğun · %70</option>
            <option value="75">Sert · %75</option>
          </select>
        </label>
        <label class="m-ayar">Ses kiti
          <select id="mSes" class="secim">
            <option value="tahta" selected><span class="emoji-sus" aria-hidden="true">🪵</span> Tahta blok</option>
            <option value="klik"><span class="emoji-sus" aria-hidden="true">💻</span> Dijital klik</option>
            <option value="klaves"><span class="emoji-sus" aria-hidden="true">🥢</span> Klaves</option>
            <option value="zil"><span class="emoji-sus" aria-hidden="true">🔔</span> İnek çanı</option>
            <option value="davul"><span class="emoji-sus" aria-hidden="true">🥁</span> Davul</option>
            <option value="bip"><span class="emoji-sus" aria-hidden="true">🎹</span> Yumuşak bip</option>
          </select>
        </label>
        <label class="m-ayar">Ses düzeyi
          <input type="range" id="mSesDuzeyi" min="0" max="100" value="80" class="m-surgu m-surgu-kucuk" aria-label="Ses düzeyi">
        </label>
        <label class="m-ayar">Poliritim
          <select id="mPoliritim" class="secim">
            <option value="0" selected>Kapalı</option>
            <option value="2">2 vuruş / ölçü</option>
            <option value="3">3 vuruş / ölçü</option>
            <option value="4">4 vuruş / ölçü</option>
            <option value="5">5 vuruş / ölçü</option>
            <option value="6">6 vuruş / ölçü</option>
            <option value="7">7 vuruş / ölçü</option>
            <option value="8">8 vuruş / ölçü</option>
            <option value="9">9 vuruş / ölçü</option>
            <option value="10">10 vuruş / ölçü</option>
            <option value="11">11 vuruş / ölçü</option>
            <option value="12">12 vuruş / ölçü</option>
          </select>
        </label>
        <label class="m-ayar">Sayarak giriş
          <select id="mGirisOlcu" class="secim">
            <option value="0" selected>Kapalı</option>
            <option value="1">1 ölçü</option>
            <option value="2">2 ölçü</option>
            <option value="4">4 ölçü</option>
          </select>
        </label>
        <label class="m-ayar-onay"><input type="checkbox" id="mFlasModu"> ⚡ Flaş modu</label>
      </div>

      <div class="m-sessiz-aralik">
        <label class="m-ayar-onay"><input type="checkbox" id="mSessizModu"> Sessiz aralık çalışması</label>
        <span class="m-sessiz-secim" id="mSessizSecim" hidden>
          <select id="mSesliOlcu" class="secim secim-dar">
            <option value="1">1</option><option value="2" selected>2</option><option value="4">4</option>
          </select> ölçü sesli →
          <select id="mSessizOlcu" class="secim secim-dar">
            <option value="1">1</option><option value="2" selected>2</option><option value="4">4</option>
          </select> ölçü sessiz
        </span>
      </div>

      <div class="m-trainer">
        <label class="m-ayar-onay"><input type="checkbox" id="mTrainer"> 🚀 Tempo trainer</label>
        <span class="m-trainer-secim" id="mTrainerSecim" hidden>
          hedef <input type="number" id="mTrainerHedef" class="girdi m-trainer-girdi" value="120" min="30" max="240"> BPM ·
          her <select id="mTrainerOlcu" class="secim secim-dar">
            <option value="1">1</option><option value="2" selected>2</option><option value="4">4</option><option value="8">8</option>
          </select> ölçüde
          <select id="mTrainerArtis" class="secim secim-dar">
            <option value="1">+1</option><option value="2" selected>+2</option><option value="5">+5</option>
          </select> BPM
        </span>
      </div>

      <div class="m-premium-satir">
        <label class="m-ayar"><span class="emoji-sus" aria-hidden="true">🎲</span> Rastgele sus
          <select id="mRastgeleSus" class="secim">
            <option value="0" selected>Kapalı</option>
            <option value="10">%10</option>
            <option value="25">%25</option>
            <option value="50">%50</option>
            <option value="75">%75</option>
          </select>
        </label>
        <label class="m-ayar"><span class="emoji-sus" aria-hidden="true">⏲</span> Zamanlayıcı
          <select id="mZamanlayici" class="secim">
            <option value="0" selected>Kapalı</option>
            <option value="1">1 dk</option>
            <option value="2">2 dk</option>
            <option value="3">3 dk</option>
            <option value="5">5 dk</option>
            <option value="10">10 dk</option>
          </select>
        </label>
        <label class="m-ayar-onay"><input type="checkbox" id="mTitresim"> 📳 Titreşim</label>
        <!-- WCAG 2.1.4: tek karakterli kısayol (t = tap tempo) kapatılabilir olmalı -->
        <label class="m-ayar-onay"><input type="checkbox" id="mKisayolAcik" checked> ⌨ “t” kısayolu (tap tempo)</label>
        <button type="button" class="m-mini-btn" id="mTamEkran" title="Sahneyi tam ekran yap"><span class="emoji-sus" aria-hidden="true">⛶</span> Tam ekran</button>
      </div>

      <div class="m-preset-cubugu">
        <span class="alan-ipucu">Preset:</span>
        <span id="mPresetler"></span>
        <button type="button" class="m-mini-btn" id="mPresetKaydet" title="Geçerli ayarları preset olarak sakla">＋ Kaydet</button>
        <span class="m-preset-form" id="mPresetForm" hidden>
          <input type="text" class="m-preset-ad" id="mPresetAdi" maxlength="18"
                 placeholder="Preset adı" aria-label="Preset adı">
          <button type="button" class="m-mini-btn m-preset-onay" id="mPresetOnay">Kaydet</button>
          <button type="button" class="m-mini-btn" id="mPresetIptal" aria-label="Preset kaydını iptal et">İptal</button>
        </span>
      </div>

      <div class="m-buton-satir">
        <button type="button" class="btn btn-birincil m-baslat" id="mBaslat" aria-pressed="false"><span class="emoji-sus" aria-hidden="true">▶</span> Başlat</button>
        <button type="button" class="btn btn-golge" id="mTap"><span class="emoji-sus" aria-hidden="true">👆</span> Tap Tempo</button>
      </div>
      <p class="alan-ipucu">Kısayollar: <kbd>Boşluk</kbd> başlat/durdur · <kbd>T</kbd> tap tempo · <kbd>↑↓</kbd> BPM
        · Swing sekizlik ve onaltılık ikililerine uygulanır. Sayarak giriş yalnız klik ve görsel sayaçla çalışır;
        sesli sayma kullanmaz. 🎲 Rastgele sus: vuruşların bir kısmı rastgele susarak içsel sayımı çalıştırır.</p>
    </div>
  </div>

  <div class="m-sarki-bolum">
    <div class="m-sarki-baslik">
      <h3><span class="emoji-sus" aria-hidden="true">🎵</span> Şarkı temposu seç</h3>
      <span class="alan-ipucu">BPM'ler songbpm/tunebat kayıtlarından; kayıt sürümüne göre küçük fark olabilir.</span>
      <input type="search" id="sarkiAra" class="m-sarki-ara" placeholder="Şarkı veya sanatçı ara…">
    </div>
    <div class="m-sarki-turler" id="sarkiTurler"></div>
    <div class="m-sarki-liste" id="sarkiListe"></div>

    <!-- ==================== ŞARKI BPM TAHMİN OYUNU ==================== -->
    <div class="oyun-kart" id="oyunKart">
      <div class="kart-baslik">
        <h3><span class="emoji-sus" aria-hidden="true">🎯</span> BPM Tahmin Oyunu</h3>
        <span class="alan-ipucu">Listeden rastgele şarkı gelir; temposunu tahmin et. Şarkının kendisi
          çalınmaz — "Önce dinle" modunda temposu metronomla dinletilir. 5 turluk oyun; skorlar
          yalnız bu cihazda (rekor) tutulur, ölçüm kayıtlarına <strong>karışmaz</strong>.</span>
      </div>
      <div class="filtre-satir">
        <label class="form-alan">Mod
          <select id="oyunMod" class="secim">
            <option value="dinle" selected><span class="emoji-sus" aria-hidden="true">🎧</span> Önce dinle — 10 sn tempo dinlet, sonra tahmin et</option>
            <option value="hafiza"><span class="emoji-sus" aria-hidden="true">🧠</span> Hafızadan — şarkıyı zihninde canlandır, tap'le</option>
          </select>
        </label>
        <label class="form-alan">Tahmin süresi
          <select id="oyunSure" class="secim">
            <option value="15">15 sn</option>
            <option value="20" selected>20 sn</option>
            <option value="30">30 sn</option>
          </select>
        </label>
        <label class="form-alan">Tür
          <select id="oyunTur" class="secim">
            <option value="" selected>Hepsi</option>
            <option value="metal">Metal</option>
            <option value="rock">Rock</option>
            <option value="pop">Pop</option>
            <option value="jazz">Jazz</option>
          </select>
        </label>
        <button type="button" class="btn btn-birincil" id="oyunBaslat">Oyunu Başlat</button>
        <span class="alan-ipucu" id="oyunRekor"></span>
      </div>

      <div class="m-test-sahne" id="oyunSahne" hidden>
        <div class="oyun-sarki" id="oyunSarki">—</div>
        <div class="m-faz-etiket" id="oyunFaz">Hazır…</div>
        <button type="button" class="m-pad" id="oyunPad">VUR<small>boşluk / dokun — en az 4 vuruş</small></button>
        <div class="oyun-alt">
          <span class="m-tur-goster" id="oyunTurGoster">Tur 1 / 5</span>
          <span class="oyun-sayac" id="oyunSayac"></span>
          <button type="button" class="btn btn-birincil btn-kucuk" id="oyunTahmin" hidden>Tahminim Bu ✓</button>
        </div>
        <button type="button" class="btn btn-golge btn-kucuk" id="oyunIptal">İptal</button>
      </div>

      <div class="m-sonuc" id="oyunSonuc" hidden>
        <div class="m-skor-kart">
          <div class="m-skor" id="oyunSkor">–</div>
          <div class="m-skor-etiket">OYUN PUANI<br><small>5 turun ortalaması</small></div>
        </div>
        <div class="m-sonuc-detay">
          <table class="tablo">
            <thead><tr><th scope="col">Tur</th><th scope="col">Şarkı</th><th scope="col" class="sayi">Gerçek</th>
                       <th scope="col" class="sayi">Tahminin</th><th scope="col" class="sayi">Hata</th><th scope="col" class="sayi">Puan</th></tr></thead>
            <tbody id="oyunTablo"></tbody>
          </table>
          <p class="alan-ipucu" id="oyunYorum"></p>
          <button type="button" class="btn btn-golge" id="oyunTekrar">Yeniden Oyna</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ==================== ÇALIŞMA MERKEZİ ==================== -->
<div class="kart m-calisma-merkezi" id="mCalismaMerkezi">
  <div class="kart-baslik m-cm-baslik">
    <div>
      <h2><span class="emoji-sus" aria-hidden="true">🎛</span> Çalışma Merkezi</h2>
      <span class="alan-ipucu">Şarkı, repertuvar veya teknik çalışma akışını hazırla; metronom adımları sırayla uygulasın ve süreyi günlüğe yazsın.</span>
    </div>
    <span class="m-cm-kayit-durum" id="mCmKayitDurum" role="status" aria-live="polite">Hazır</span>
  </div>

  <div class="m-cm-grid">
    <section class="m-cm-set" aria-labelledby="mCmSetBaslik">
      <div class="m-cm-bolum-baslik">
        <div>
          <h3 id="mCmSetBaslik">Setlist oluşturucu</h3>
          <small id="mSetToplam">0 adım · 00:00</small>
        </div>
        <div class="m-cm-butonlar">
          <button type="button" class="btn btn-kucuk btn-golge" id="mSetYeni">＋ Yeni</button>
          <button type="button" class="btn btn-kucuk btn-birincil" id="mSetKaydet">Kaydet</button>
          <button type="button" class="btn btn-kucuk btn-golge" id="mSetCalistir"><span class="emoji-sus" aria-hidden="true">▶</span> Çalıştır</button>
          <button type="button" class="btn btn-kucuk btn-tehlike" id="mSetSil" disabled>Sil</button>
        </div>
      </div>

      <div class="m-cm-set-ust">
        <label class="form-alan">Kayıtlı setlist
          <select id="mSetSec" class="secim">
            <option value="">— Yeni setlist —</option>
          </select>
        </label>
        <label class="form-alan">Setlist adı
          <input type="text" class="girdi" id="mSetAd" maxlength="80" placeholder="Örn. Konser repertuvarı">
        </label>
        <label class="form-alan m-cm-aciklama">Not
          <input type="text" class="girdi" id="mSetAciklama" maxlength="300" placeholder="Amaç, repertuvar veya çalışma notu">
        </label>
      </div>

      <div class="m-set-ekle-satir">
        <button type="button" class="btn btn-golge" id="mSetAdimEkle">＋ Geçerli metronom ayarını adım olarak ekle</button>
        <span class="alan-ipucu">BPM, ölçü, gruplama, swing, poliritim ve sayarak giriş birlikte alınır.</span>
      </div>
      <div class="m-set-adimlar" id="mSetAdimlar" aria-live="polite"></div>
      <div class="m-set-bos" id="mSetBos">Henüz adım yok. Metronomu istediğin ayara getirip yukarıdaki düğmeyle ilk adımı ekle.</div>
    </section>

    <aside class="m-cm-gunluk" aria-labelledby="mGunlukBaslik">
      <div class="m-cm-bolum-baslik">
        <div>
          <h3 id="mGunlukBaslik">Çalışma günlüğü</h3>
          <small>10 saniyeden uzun çalışmalar otomatik kaydedilir.</small>
        </div>
        <span class="m-seri-rozet" id="mGunlukSeri">0 gün seri</span>
      </div>
      <div class="m-gunluk-ozet">
        <div class="m-gunluk-hedef-halka" id="mGunlukHalka" style="--oran:0%">
          <strong id="mGunlukBugun">0 dk</strong><small>bugün</small>
        </div>
        <div>
          <strong id="mGunlukHedefMetin">20 dk hedef</strong>
          <div class="m-gunluk-hedef-cubuk"><span id="mGunlukHedefCubuk"></span></div>
          <small id="mGunlukHaftaMetin">Bu hafta 0 / 5 gün</small>
        </div>
      </div>
      <div class="m-gunluk-grafik" id="mGunlukGrafik" aria-label="Son yedi günlük çalışma grafiği"></div>
      <div class="m-hedef-form">
        <label>Günlük hedef
          <span><input type="number" class="girdi" id="mHedefDk" min="1" max="480" value="20"> dk</span>
        </label>
        <label>Haftalık
          <span><input type="number" class="girdi" id="mHedefGun" min="1" max="7" value="5"> gün</span>
        </label>
        <button type="button" class="btn btn-kucuk btn-golge" id="mHedefKaydet">Hedefi kaydet</button>
      </div>
      <h4 class="m-son-calisma-baslik">Son çalışmalar</h4>
      <div class="m-son-calismalar" id="mSonCalismalar"></div>
    </aside>
  </div>

  <section class="m-set-oynatici" id="mSetOynatici" hidden aria-labelledby="mSetOynaticiBaslik">
    <div class="m-set-oynatici-bilgi">
      <span class="m-set-canli">SETLIST</span>
      <div>
        <strong id="mSetOynaticiBaslik">Hazır</strong>
        <small id="mSetOynaticiAlt">Adım seçilmedi</small>
      </div>
    </div>
    <div class="m-set-sayac" id="mSetSayac">00:00</div>
    <div class="m-set-oynatici-cubuk"><span id="mSetIlerleme"></span></div>
    <div class="m-cm-butonlar">
      <button type="button" class="m-mini-btn" id="mSetOnceki" title="Önceki adım">⏮</button>
      <button type="button" class="btn btn-birincil" id="mSetBaslat"><span class="emoji-sus" aria-hidden="true">▶</span> Setlisti Başlat</button>
      <button type="button" class="m-mini-btn" id="mSetSonraki" title="Sonraki adım">⏭</button>
      <button type="button" class="btn btn-kucuk btn-golge" id="mSetBitir">Bitir</button>
    </div>
  </section>
</div>

<!-- ==================== DERS AKIŞI ==================== -->
<div class="kart" id="akisKart">
  <div class="kart-baslik">
    <h2><span class="emoji-sus" aria-hidden="true">🧭</span> Ders Akışı</h2>
    <span class="alan-ipucu">Oturum planındaki teknikleri sırayla, süre sayacı ve zille çalıştırır —
      metronom ayarları serbest kalır.</span>
  </div>

  <?php if (!$akisOturum): ?>
  <div class="filtre-satir">
    <?php if (!$akisSecenekler): ?>
      <span class="alan-ipucu">Yakın tarihli planlı oturum yok. Önce <a href="<?= e(url('plan.php')) ?>">ders planı</a> oluşturun
        ya da oturum sayfasındaki "Ders Akışını Stüdyoda Başlat" düğmesini kullanın.</span>
    <?php else: ?>
    <label class="form-alan" style="min-width:260px">Oturum seç
      <select id="akisSecim" class="secim">
        <?php foreach ($akisSecenekler as $s): ?>
        <option value="<?= (int)$s['id'] ?>">
          <?= e(format_date_tr($s['tarih'])) ?> — <?= e($s['grup_ad']) ?> · H<?= (int)$s['hafta_no'] ?>
          (<?= (int)$s['teknik_sayisi'] ?> teknik, <?= (int)$s['plan_sure'] ?> dk)
        </option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="button" class="btn btn-birincil" id="akisYukle">Akışı Yükle</button>
    <?php endif; ?>
  </div>

  <?php else: ?>
  <div class="akis-ust">
    <span class="rozet rozet-acik"><?= e($akisOturum['grup']) ?></span>
    <span class="rozet rozet-gri"><?= e(format_date_tr($akisOturum['tarih'])) ?> · Hafta <?= (int)$akisOturum['hafta_no'] ?></span>
    <?php if ($akisOturum['protokol'] && isset(PROTOKOL_LABELS[$akisOturum['protokol']])): ?>
      <?php if (isset(PROTOKOL_SAYFALARI[$akisOturum['protokol']])): ?>
      <!-- Kendi sayfasındaki protokol YENİ SEKMEDE açılır: ders akışı sayacı
           yalnız bellekte yaşıyor, aynı sekmede gidilirse baştan sıfırlanır. -->
      <a class="btn btn-kucuk btn-golge" target="_blank" rel="noopener"
         href="<?= e(url(PROTOKOL_SAYFALARI[$akisOturum['protokol']] . '?akis=' . (int)$akisOturum['id'])) ?>"
         title="Yeni sekmede açılır; ders akışı sayacı burada çalışmaya devam eder">
        🧭 Haftanın protokolü: <?= e(PROTOKOL_LABELS[$akisOturum['protokol']]) ?> ↗</a>
      <?php else: ?>
      <button type="button" class="btn btn-kucuk btn-golge" id="akisProtokolAc"
              data-protokol="<?= e($akisOturum['protokol']) ?>"><span class="emoji-sus" aria-hidden="true">🧭</span> Haftanın protokolü: <?= e(PROTOKOL_LABELS[$akisOturum['protokol']]) ?></button>
      <?php endif; ?>
    <?php endif; ?>
    <div class="sag">
      <a class="btn btn-kucuk btn-golge" href="<?= e(url('oturum.php?id=' . (int)$akisOturum['id'])) ?>">Oturum kaydına git</a>
      <a class="btn btn-kucuk btn-golge" href="<?= e(url('metronom.php')) ?>"><span class="emoji-sus" aria-hidden="true">✕</span> Akışı kapat</a>
    </div>
  </div>

  <div class="akis-sahne">
    <div class="akis-sol">
      <div class="akis-adim-etiket" id="akisAdimEtiket">Hazır — ▶ ile başlat</div>
      <div class="akis-sayac" id="akisSayac">--:--</div>
      <div class="m-ilerleme akis-cubuk"><div class="m-ilerleme-dolu" id="akisIlerleme" style="width:0"></div></div>
      <div class="akis-butonlar">
        <button type="button" class="m-mini-btn" id="akisOnceki" title="Önceki teknik">⏮</button>
        <button type="button" class="btn btn-birincil m-baslat" id="akisBaslat"><span class="emoji-sus" aria-hidden="true">▶</span> Başlat</button>
        <button type="button" class="m-mini-btn" id="akisSonraki" title="Sonraki teknik">⏭</button>
        <button type="button" class="m-mini-btn" id="akisSifirla" title="Akışı başa sar">↺</button>
      </div>
      <div class="alan-ipucu" id="akisToplam"></div>
    </div>
    <ol class="akis-liste" id="akisListe">
      <?php foreach ($akisOturum['teknikler'] as $i => $t): ?>
      <li data-idx="<?= (int)$i ?>">
        <span class="akis-li-ad"><?= e($t['ad']) ?></span>
        <span class="akis-li-sure"><?= (int)$t['sure_dk'] ?> dk</span>
        <?php if ($t['not'] !== ''): ?><small class="alan-ipucu"><?= e($t['not']) ?></small><?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ol>
  </div>
  <?php endif; ?>
</div>

<!-- ==================== PROTOKOLLER ==================== -->
<div class="kart">
  <div class="kart-baslik">
    <h2>Dikkat Protokolleri</h2>
    <span class="alan-ipucu">Skorlar eğitmenin iç izleme aracıdır; veli raporuna yansımaz.</span>
  </div>

  <div class="m-standart-satir">
    <label class="m-ayar-onay"><input type="checkbox" id="stdMod"> 📏 <strong>Standart ölçüm modu</strong></label>
    <span class="alan-ipucu">Dönem başı/sonu karşılaştırması için sabit koşullar: Vuruş 72 BPM · 16+16,
      BPM Bulma orta, Ritim Okuma S1 · 60, Aksak orta, İçsel 72 · standart profil (Spontan Tempo zaten sabittir).
      Bu koşullarda alınan sonuçlar 📏 işaretlenir; sertifika ve dönemlik rapor ilk→son karşılaştırmasını
      önce 📏 ölçümlerden yapar.</span>
  </div>

  <section class="m-zaman-kalite" id="zamanKalitePanel" aria-labelledby="zamanKaliteBaslik">
    <div class="m-zaman-kalite-ust">
      <div>
        <strong id="zamanKaliteBaslik"><span class="emoji-sus" aria-hidden="true">⏱</span> Profesyonel ölçüm sistemi</strong>
        <small>Bütün zamanlama protokolleri aynı cihaz telafisini ve eşleştirme motorunu kullanır.</small>
      </div>
      <span class="m-zaman-rozet" id="zkRozet">Ses motoru bekleniyor</span>
    </div>
    <div class="m-zaman-metrikler">
      <span><b id="zkTelafi">—</b><small>cihaz telafisi</small></span>
      <span><b id="zkDagilim">—</b><small>vuruş dağılımı</small></span>
      <span><b id="zkTarayici">—</b><small>tarayıcı çıkışı</small></span>
      <span><b id="zkOrnekleme">—</b><small>örnekleme</small></span>
      <span><b id="zkTarih">—</b><small>son kalibrasyon</small></span>
    </div>
    <p class="alan-ipucu" id="zkAciklama">
      Mutlak senkronizasyon ölçümleri için kulaklıkla veya kullanacağın hoparlörle bir kez kalibre et.
    </p>
    <div class="m-zaman-butonlar">
      <button type="button" class="btn btn-birincil btn-kucuk" id="zkKalibre"><span class="emoji-sus" aria-hidden="true">🎧</span> Kalibre et</button>
      <button type="button" class="btn btn-golge btn-kucuk" id="zkYenile">Durumu yenile</button>
      <button type="button" class="btn btn-golge btn-kucuk" id="zkSifirla">Kalibrasyonu sıfırla</button>
    </div>
    <div class="m-zaman-kalibrasyon" id="zkSahne" hidden>
      <div>
        <strong id="zkSayac">Hazırlan…</strong>
        <small id="zkIlerleme">0/12</small>
      </div>
      <button type="button" class="m-pad m-pad-kucuk" id="zkPad">VUR<small>duyduğun her klikte</small></button>
      <button type="button" class="btn btn-golge btn-kucuk" id="zkIptal">İptal</button>
    </div>
  </section>

  <div class="m-sekmeler" role="tablist" id="mSekmeler" data-acilacak="<?= e($acilacakProtokol) ?>">
    <button type="button" class="m-sekme aktif" role="tab" aria-selected="true" id="mSekme-vurus" aria-controls="sekme-vurus" tabindex="0" data-sekme="vurus"><span class="emoji-sus" aria-hidden="true">🎯</span> Vuruş Tutturma</button>
    <button type="button" class="m-sekme" role="tab" aria-selected="false" id="mSekme-bpm" aria-controls="sekme-bpm" tabindex="-1" data-sekme="bpm"><span class="emoji-sus" aria-hidden="true">🎧</span> BPM Bulma</button>
    <button type="button" class="m-sekme" role="tab" aria-selected="false" id="mSekme-spontan" aria-controls="sekme-spontan" tabindex="-1" data-sekme="spontan"><span class="emoji-sus" aria-hidden="true">🫀</span> Spontan Tempo</button>
    <button type="button" class="m-sekme" role="tab" aria-selected="false" id="mSekme-aksak" aria-controls="sekme-aksak" tabindex="-1" data-sekme="aksak"><span class="emoji-sus" aria-hidden="true">🕳</span> Aksak Bulma</button>
    <button type="button" class="m-sekme" role="tab" aria-selected="false" id="mSekme-icsel" aria-controls="sekme-icsel" tabindex="-1" data-sekme="icsel"><span class="emoji-sus" aria-hidden="true">🧭</span> İçsel Ritim</button>
  </div>

  <!-- Vuruş Tutturma -->
  <div class="m-sekme-icerik" id="sekme-vurus" role="tabpanel" aria-labelledby="mSekme-vurus" tabindex="0">
    <p class="alan-ipucu">4 vuruş hazırlık dinlenir → <strong>sesli fazda</strong> metronomla birlikte vurulur →
       metronom susar, <strong>sessiz fazda</strong> içsel tempoyla devam edilir. Her vuruşun milisaniye
       sapması ölçülür.</p>
    <div class="filtre-satir">
      <label class="form-alan">Öğrenci<?= ogrenci_secimi('vtOgrenci', $ogrenciler) ?></label>
      <label class="form-alan">Tempo
        <select id="vtBpm" class="secim">
          <option value="60">60 BPM</option>
          <option value="72" selected>72 BPM</option>
          <option value="84">84 BPM</option>
          <option value="100">100 BPM</option>
        </select>
      </label>
      <label class="form-alan">Faz uzunluğu
        <select id="vtVurusSayisi" class="secim">
          <option value="8">8 + 8 vuruş</option>
          <option value="16" selected>16 + 16 vuruş</option>
          <option value="24">24 + 24 vuruş</option>
          <option value="32">32 + 32 vuruş — dönem karşılaştırması için</option>
        </select>
        <?php /* Vuruş sayısı ölçümün KESİNLİĞİNİ belirler. Benzetimle ölçüldü
                 (docs/olcum-kilavuzu.md): 6 oturumda 40→20 ms'lik gerçek bir
                 gelişme 16 vuruşta %27, 32 vuruşta %44 olasılıkla gürültü
                 bandını aşıyor. Trend izlenecekse uzun sürüm gerekir. */ ?>
        <small class="alan-ipucu">Uzun sürüm daha kesin ölçer; trend izleyecekseniz 32 seçin.</small>
      </label>
      <button type="button" class="btn btn-birincil" id="vtBaslat">Testi Başlat</button>
    </div>

    <div class="m-test-sahne" id="vtSahne" hidden>
      <div class="m-faz-etiket" id="vtFaz">Hazırlık — dinle</div>
      <button type="button" class="m-pad" id="vtPad">VUR<small>boşluk / dokun</small></button>
      <div class="m-ilerleme"><div class="m-ilerleme-dolu" id="vtIlerleme"></div></div>
      <button type="button" class="btn btn-golge btn-kucuk" id="vtIptal">İptal</button>
    </div>

    <div class="m-sonuc" id="vtSonuc" hidden>
      <div class="m-skor-kart">
        <div class="m-skor" id="vtSkor">–</div>
        <div class="m-skor-etiket">GENEL SKOR<br><small>0–100 · sessiz faz %60 ağırlıklı</small></div>
      </div>
      <div class="m-sonuc-detay">
        <table class="tablo">
          <thead><tr><th scope="col">Faz</th><th scope="col" class="sayi">Vuruş</th><th scope="col" class="sayi">Kaçırılan</th><th scope="col" class="sayi">Fazla</th>
                     <th scope="col" class="sayi">Ort. sapma</th><th scope="col" class="sayi">Ort. |sapma|</th><th scope="col" class="sayi">Skor</th></tr></thead>
          <tbody id="vtTablo"></tbody>
        </table>
        <div class="m-sapma-grafik" id="vtGrafik" title="Her vuruşun sapması: yukarı geç, aşağı erken"></div>
        <p class="alan-ipucu" id="vtYorum"></p>
        <div class="m-kaydet-satir">
          <?= sonuc_formu('vt', 'vurus_tutturma') ?>
          <button type="button" class="btn btn-golge" id="vtTekrar">Tekrar Dene</button>
        </div>
      </div>
    </div>
  </div>

  <!-- BPM Bulma -->
  <div class="m-sekme-icerik" id="sekme-bpm" hidden role="tabpanel" aria-labelledby="mSekme-bpm" tabindex="0">
    <p class="alan-ipucu">Sistem gizli bir tempoda 8 vuruş çalar; ardından aynı tempoyu
       <strong>8 vuruşla sen sürdürürsün</strong>. Tahminin gerçek BPM ile karşılaştırılır; 3 tur oynanır.</p>
    <div class="filtre-satir">
      <label class="form-alan">Öğrenci<?= ogrenci_secimi('bfOgrenci', $ogrenciler) ?></label>
      <label class="form-alan">Zorluk
        <select id="bfZorluk" class="secim">
          <option value="kolay" selected>Kolay (60–100 BPM)</option>
          <option value="orta">Orta (50–130 BPM)</option>
          <option value="zor">Zor (40–160 BPM)</option>
        </select>
      </label>
      <button type="button" class="btn btn-birincil" id="bfBaslat">Oyunu Başlat</button>
    </div>

    <div class="m-test-sahne" id="bfSahne" hidden>
      <div class="m-faz-etiket" id="bfFaz">Dinle…</div>
      <button type="button" class="m-pad" id="bfPad">VUR<small>boşluk / dokun</small></button>
      <div class="m-tur-goster" id="bfTur">Tur 1 / 3</div>
      <button type="button" class="btn btn-golge btn-kucuk" id="bfIptal">İptal</button>
    </div>

    <div class="m-sonuc" id="bfSonuc" hidden>
      <div class="m-skor-kart">
        <div class="m-skor" id="bfSkor">–</div>
        <div class="m-skor-etiket">ORTALAMA SKOR<br><small>3 turun ortalaması</small></div>
      </div>
      <div class="m-sonuc-detay">
        <table class="tablo">
          <thead><tr><th scope="col">Tur</th><th scope="col" class="sayi">Gerçek BPM</th><th scope="col" class="sayi">Tahmin</th>
                     <th scope="col" class="sayi">Hata</th><th scope="col" class="sayi">Skor</th></tr></thead>
          <tbody id="bfTablo"></tbody>
        </table>
        <div class="m-kaydet-satir">
          <?= sonuc_formu('bf', 'bpm_bulma') ?>
          <button type="button" class="btn btn-golge" id="bfTekrar">Tekrar Oyna</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Spontan Tempo -->
  <div class="m-sekme-icerik" id="sekme-spontan" hidden role="tabpanel" aria-labelledby="mSekme-spontan" tabindex="0">
    <p class="alan-ipucu">BAASTA türü <strong>serbest (unpaced) tapping</strong>: metronom yok —
       kendi rahat hızında <strong>21 vuruş</strong> yap. Kişisel doğal tempon (SMT) ve
       vuruşlarının tutarlılığı ölçülür. Dönem başı/sonu karşılaştırması için birebirdir.</p>
    <div class="filtre-satir">
      <label class="form-alan">Öğrenci<?= ogrenci_secimi('stOgrenci', $ogrenciler) ?></label>
      <button type="button" class="btn btn-birincil" id="stBaslat">Ölçümü Başlat</button>
    </div>

    <div class="m-test-sahne" id="stSahne" hidden>
      <div class="m-faz-etiket" id="stFaz">Kendi hızında vur — acele yok</div>
      <button type="button" class="m-pad" id="stPad">VUR<small>boşluk / dokun</small></button>
      <div class="m-tur-goster" id="stSayac">0 / 21</div>
      <button type="button" class="btn btn-golge btn-kucuk" id="stIptal">İptal</button>
    </div>

    <div class="m-sonuc" id="stSonuc" hidden>
      <div class="m-skor-kart">
        <div class="m-skor" id="stSkor">–</div>
        <div class="m-skor-etiket">TUTARLILIK SKORU<br><small>düşük dalgalanma = yüksek skor</small></div>
      </div>
      <div class="m-sonuc-detay">
        <p id="stOzet" style="font-size:1.05rem"></p>
        <div class="m-sapma-grafik" id="stGrafik" title="Ardışık vuruş aralıkları (ms)"></div>
        <p class="alan-ipucu">SMT = spontan motor tempo. Tutarlılık, aralıkların değişim katsayısından (CV) hesaplanır.</p>
        <div class="m-kaydet-satir">
          <?= sonuc_formu('st', 'spontan_tempo') ?>
          <button type="button" class="btn btn-golge" id="stTekrar">Tekrar Ölç</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Aksak Bulma -->
  <div class="m-sekme-icerik" id="sekme-aksak" hidden role="tabpanel" aria-labelledby="mSekme-aksak" tabindex="0">
    <p class="alan-ipucu">BAASTA türü <strong>anizokroni algısı</strong>: 6 vuruşluk dizi çalınır —
       ya kusursuz düzenlidir ya da bir vuruş hafifçe <em>aksar</em>. Hangisi olduğunu söyle; 8 tur.
       Motorsuz, saf <strong>dinleme/algı</strong> ölçümüdür.</p>
    <div class="filtre-satir">
      <label class="form-alan">Öğrenci<?= ogrenci_secimi('abOgrenci', $ogrenciler) ?></label>
      <label class="form-alan">Zorluk
        <select id="abZorluk" class="secim">
          <option value="kolay" selected>Kolay (%15 kayma)</option>
          <option value="orta">Orta (%10 kayma)</option>
          <option value="zor">Zor (%6 kayma)</option>
        </select>
      </label>
      <button type="button" class="btn btn-birincil" id="abBaslat">Testi Başlat</button>
    </div>

    <div class="m-test-sahne" id="abSahne" hidden>
      <div class="m-faz-etiket" id="abFaz"><span class="emoji-sus" aria-hidden="true">🎧</span> Dinle…</div>
      <div class="ab-cevaplar" id="abCevaplar" hidden>
        <button type="button" class="btn btn-birincil ab-cevap" data-cevap="duzenli"><span class="emoji-sus" aria-hidden="true">✓</span> Düzenliydi</button>
        <button type="button" class="btn btn-birincil ab-cevap" data-cevap="aksak"><span class="emoji-sus" aria-hidden="true">⚠</span> Aksadı</button>
      </div>
      <div class="m-tur-goster" id="abTur">Tur 1 / 8</div>
      <button type="button" class="btn btn-golge btn-kucuk" id="abIptal">İptal</button>
    </div>

    <div class="m-sonuc" id="abSonuc" hidden>
      <div class="m-skor-kart">
        <div class="m-skor" id="abSkor">–</div>
        <?php /* Skor ŞANS DÜZELTMELİ: iki seçenekli testte para atarak %50
                 tutturulur, o yüzden %50 = 0 puan. Ham doğru sayısı ayrıca
                 gösterilir, yoksa "6/8 doğru → 50 puan" kafa karıştırır. */ ?>
        <div class="m-skor-etiket">ALGI SKORU<br><small id="abHam">—</small></div>
      </div>
      <div class="m-sonuc-detay">
        <p class="alan-ipucu">İki seçenek olduğu için rastgele işaretleyen biri
          8 turun ~4'ünü tutturur. Skor bu şans payı çıkarılarak hesaplanır:
          <strong>4/8 = 0 puan</strong>, 6/8 = 50, 8/8 = 100.</p>
        <table class="tablo">
          <thead><tr><th scope="col">Tur</th><th scope="col">Dizi</th><th scope="col">Cevabın</th><th scope="col">Sonuç</th></tr></thead>
          <tbody id="abTablo"></tbody>
        </table>
        <div class="m-kaydet-satir">
          <?= sonuc_formu('ab', 'aksak_bulma') ?>
          <button type="button" class="btn btn-golge" id="abTekrar">Tekrar Dene</button>
        </div>
      </div>
    </div>
  </div>

  <!-- İçsel Ritim (sessizlik merdiveni) -->
  <div class="m-sekme-icerik" id="sekme-icsel" hidden role="tabpanel" aria-labelledby="mSekme-icsel" tabindex="0">
    <p class="alan-ipucu">"Rastgele sus" özelliğinin <strong>ölçülen</strong> hâli: metronom
       kademe kademe susar (<strong>%0 → %25 → %50 → %75</strong>), sen her vuruşta vurmaya
       devam edersin. Sessiz vuruşlardaki sapma ayrı ölçülür; içsel sayımın dönem boyunca
       nasıl geliştiği skorla izlenir.</p>
    <div class="filtre-satir">
      <label class="form-alan">Öğrenci<?= ogrenci_secimi('irOgrenci', $ogrenciler) ?></label>
      <label class="form-alan">Tempo
        <select id="irBpm" class="secim">
          <option value="60">60 BPM</option>
          <option value="72" selected>72 BPM</option>
          <option value="84">84 BPM</option>
          <option value="100">100 BPM</option>
        </select>
      </label>
      <label class="form-alan">Zorluk profili
        <select id="irProfil" class="secim">
          <option value="kolay">Kolay (%0→15→25→50)</option>
          <option value="standart" selected>Standart (%0→25→50→75)</option>
          <option value="ileri">İleri (%25→50→75→90)</option>
        </select>
        <span class="alan-ipucu" id="irOneri">Öğrenci seçilince son skora göre önerilir.</span>
      </label>
      <button type="button" class="btn btn-birincil" id="irBaslat">Testi Başlat</button>
    </div>

    <div class="m-test-sahne" id="irSahne" hidden>
      <div class="m-faz-etiket" id="irFaz">Hazırlık — dinle</div>
      <button type="button" class="m-pad" id="irPad">VUR<small>boşluk / dokun</small></button>
      <div class="m-ilerleme"><div class="m-ilerleme-dolu" id="irIlerleme"></div></div>
      <button type="button" class="btn btn-golge btn-kucuk" id="irIptal">İptal</button>
    </div>

    <div class="m-sonuc" id="irSonuc" hidden>
      <div class="m-skor-kart">
        <div class="m-skor" id="irSkor">–</div>
        <div class="m-skor-etiket">İÇSEL RİTİM SKORU<br><small>sessiz fazlar daha ağırlıklı</small></div>
      </div>
      <div class="m-sonuc-detay">
        <table class="tablo">
          <thead><tr><th scope="col">Faz</th><th scope="col" class="sayi">Vuruş</th><th scope="col" class="sayi">Kaçırılan</th><th scope="col" class="sayi">Fazla</th>
                     <th scope="col" class="sayi">Ort. |sapma|</th><th scope="col" class="sayi">Skor</th></tr></thead>
          <tbody id="irTablo"></tbody>
        </table>
        <div class="m-sapma-grafik" id="irGrafik" title="Mor çubuklar: metronomun sustuğu vuruşlar"></div>
        <p class="alan-ipucu" id="irYorum"></p>
        <div class="m-kaydet-satir">
          <?= sonuc_formu('ir', 'icsel_ritim') ?>
          <button type="button" class="btn btn-golge" id="irTekrar">Tekrar Dene</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ==================== SON KAYITLAR ==================== -->
<div class="kart">
  <div class="kart-baslik"><h2>Son protokol kayıtları</h2></div>
  <?php if (!$sonKayitlar): ?>
    <div class="bos-durum">Henüz kayıt yok. Bir test tamamlayıp öğrenciye kaydedin.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th scope="col">Tarih</th><th scope="col">Öğrenci</th><th scope="col">Protokol</th><th scope="col">Kaynak</th><th scope="col" class="sayi">BPM</th><th scope="col" class="sayi">Skor</th><th scope="col">Not</th><th scope="col"></th></tr></thead>
      <tbody>
        <?php foreach ($sonKayitlar as $k): ?>
        <tr>
          <td><?= e(format_date_tr(substr($k['created_at'], 0, 10))) ?> <?= e(substr($k['created_at'], 11, 5)) ?></td>
          <td><a href="<?= e(url('ogrenci.php?id=' . (int)$k['ogrenci_id'])) ?>"><?= e($k['ogrenci_kod']) ?></a></td>
          <td><?= e(PROTOKOL_LABELS[$k['protokol']] ?? $k['protokol']) ?></td>
          <td><?= ($k['kaynak'] ?? 'atolye') === 'ev' ? '🏠 Ev' : '🎓 Atölye' ?></td>
          <td class="sayi"><?= $k['bpm'] ? (int)$k['bpm'] : '—' ?></td>
          <td class="sayi"><strong><?= (int)$k['skor'] ?></strong>/100<?= !empty($k['standart']) ? ' <span title="Standart koşullarda ölçüm (📏)">📏</span>' : '' ?></td>
          <td><?= e(mb_strimwidth((string)$k['notlar'], 0, 40, '…')) ?></td>
          <td style="text-align:right">
            <form method="post" action="<?= e(url('metronom.php')) ?>" data-onay="Bu protokol kaydını silmek istiyor musunuz?">
              <?= csrf_field() ?>
              <input type="hidden" name="islem" value="sonuc_sil">
              <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
              <button type="submit" class="btn btn-kucuk btn-tehlike">Sil</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script <?= csp_nonce_attr() ?>>
/*
 * GÜVENLİK: buraya gömülen her JSON'da JSON_HEX_TAG|JSON_HEX_AMP ZORUNLU.
 * Bunlar olmadan koruma yalnız PHP'nin varsayılan slash kaçırmasına kalıyordu;
 * ölçüldü: kapatma etiketi kaçışı engelleniyor AMA teknik adına yazılan bir
 * "açan yorum + betik" dizisi HTML ayrıştırıcısını çift-kaçış durumuna sokup
 * sonraki betik bloklarını yutuyor (sayfanın JS'i sessizce kırılıyor).
 * Aşağıdaki üçüncü ifade JSON_UNESCAPED_SLASHES kullanıyor — o bayrak buraya
 * da kopyalansa örtük koruma düşer ve doğrudan saklı XSS olurdu.
 *
 * NOT: o tehlikeli diziyi bu yoruma DÜZ METİN olarak yazmayın. Bir kez yazıldı
 * ve tarif ettiği hatanın aynısını bu sayfada tetikledi: betik etiketi içinde
 * geçen o dizi, ayrıştırıcıyı sayfanın kalanını yorum sayacak duruma soktu ve
 * metronom.js dahil altı betik hiç yüklenmedi. Sunucu 200 döndüğü için duman
 * testi de görmedi.
 */
/* Uyarlanan zorluk için: her öğrencinin protokol başına SON skoru */
window.SON_SKORLAR = <?= json_encode($sonSkorlar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
/* Ders akışı: seçili oturumun teknik planı (yoksa null) — grup/teknik adları
   eğitmenin serbest metni, yedek geri yüklemesiyle dışarıdan da gelebilir */
window.DERS_AKISI = <?= json_encode($akisOturum, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
/* Hesaba bağlı çalışma merkezi başlangıç verisi */
window.METRONOM_CALISMA_VERI = <?= json_encode([
    'api' => url('metronom-api.php'),
    'csrfToken' => csrf_token(),
    'setler' => $metronomSetleri,
    'ozet' => $metronomOzet,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="<?= e(asset('js/zamanlama-cekirdegi.js')) ?>"></script>
<script src="<?= e(asset('js/ritim-ogrenme.js')) ?>"></script>
<script src="<?= e(asset('js/metronom-cekirdegi.js')) ?>"></script>
<script src="<?= e(asset('js/metronom-setlist.js')) ?>"></script>
<script src="<?= e(asset('js/metronom.js')) ?>"></script>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
