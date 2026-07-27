<?php
/**
 * Metronom Stüdyosu — gelişmiş metronom + dikkat protokolleri.
 * v2: alt bölünmeler, vuruş başına aksan deseni, tempo trainer, poliritim,
 * genişletilmiş ses kiti (seçimde önizleme), sesli sayma, flaş modu, preset'ler.
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
          <small>BPM · <em id="mTempoAdi">Andante</em></small>
        </div>
        <button type="button" class="m-mini-btn" data-bpm-degistir="1">+1</button>
        <button type="button" class="m-mini-btn" data-bpm-degistir="5">+5</button>
      </div>
      <input type="range" id="mBpmSurgu" min="30" max="240" value="92" step="1" class="m-surgu" aria-label="Tempo">

      <div class="m-nokta-dizi" id="mNoktalar" aria-label="Aksan deseni: noktaya tıkla — aksan → normal → sessiz"></div>
      <p class="alan-ipucu" style="text-align:center;margin:-.3rem 0 .6rem">
        Noktalara tıkla: <strong>aksan</strong> → normal → sessiz
      </p>

      <div class="m-ayarlar">
        <label class="m-ayar">Ölçü
          <select id="mOlcu" class="secim">
            <option value="2">2/4</option>
            <option value="3">3/4</option>
            <option value="4" selected>4/4</option>
            <option value="5">5/4</option>
            <option value="6">6/8</option>
            <option value="7">7/8</option>
          </select>
        </label>
        <label class="m-ayar">Alt bölünme
          <select id="mAltBolunme" class="secim">
            <option value="1" selected>♩ Çeyrek</option>
            <option value="2">♫ Sekizlik</option>
            <option value="3">③ Üçleme</option>
            <option value="4">♬ Onaltılık</option>
          </select>
        </label>
        <label class="m-ayar">Ses kiti
          <select id="mSes" class="secim">
            <option value="tahta" selected>🪵 Tahta blok</option>
            <option value="klik">💻 Dijital klik</option>
            <option value="klaves">🥢 Klaves</option>
            <option value="zil">🔔 İnek çanı</option>
            <option value="davul">🥁 Davul</option>
            <option value="bip">🎹 Yumuşak bip</option>
            <option value="sayma">🗣 Sesli sayma</option>
          </select>
        </label>
        <label class="m-ayar">Ses düzeyi
          <input type="range" id="mSesDuzeyi" min="0" max="100" value="80" class="m-surgu m-surgu-kucuk" aria-label="Ses düzeyi">
        </label>
        <label class="m-ayar">Poliritim
          <select id="mPoliritim" class="secim">
            <option value="0" selected>Kapalı</option>
            <option value="2">2 : ölçü</option>
            <option value="3">3 : ölçü</option>
            <option value="5">5 : ölçü</option>
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
        <label class="m-ayar">🎲 Rastgele sus
          <select id="mRastgeleSus" class="secim">
            <option value="0" selected>Kapalı</option>
            <option value="10">%10</option>
            <option value="25">%25</option>
            <option value="50">%50</option>
            <option value="75">%75</option>
          </select>
        </label>
        <label class="m-ayar">⏲ Zamanlayıcı
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
        <button type="button" class="m-mini-btn" id="mTamEkran" title="Sahneyi tam ekran yap">⛶ Tam ekran</button>
      </div>

      <div class="m-preset-cubugu">
        <span class="alan-ipucu">Preset:</span>
        <span id="mPresetler"></span>
        <button type="button" class="m-mini-btn" id="mPresetKaydet" title="Geçerli ayarları preset olarak sakla">＋ Kaydet</button>
      </div>

      <div class="m-buton-satir">
        <button type="button" class="btn btn-birincil m-baslat" id="mBaslat">▶ Başlat</button>
        <button type="button" class="btn btn-golge" id="mTap">👆 Tap Tempo</button>
      </div>
      <p class="alan-ipucu">Kısayollar: <kbd>Boşluk</kbd> başlat/durdur · <kbd>T</kbd> tap tempo · <kbd>↑↓</kbd> BPM
        · Ses kitini değiştirince kısa önizleme çalar. 🎲 Rastgele sus: vuruşların bir kısmı
        rastgele susarak içsel sayımı çalıştırır.</p>
    </div>
  </div>

  <div class="m-sarki-bolum">
    <div class="m-sarki-baslik">
      <h3>🎵 Şarkı temposu seç</h3>
      <span class="alan-ipucu">BPM'ler songbpm/tunebat kayıtlarından; kayıt sürümüne göre küçük fark olabilir.</span>
      <input type="search" id="sarkiAra" class="m-sarki-ara" placeholder="Şarkı veya sanatçı ara…">
    </div>
    <div class="m-sarki-turler" id="sarkiTurler"></div>
    <div class="m-sarki-liste" id="sarkiListe"></div>
  </div>
</div>

<!-- ==================== PROTOKOLLER ==================== -->
<div class="kart">
  <div class="kart-baslik">
    <h2>Dikkat Protokolleri</h2>
    <span class="alan-ipucu">Skorlar eğitmenin iç izleme aracıdır; veli raporuna yansımaz.</span>
  </div>

  <div class="m-sekmeler" role="tablist">
    <button type="button" class="m-sekme aktif" data-sekme="vurus">🎯 Vuruş Tutturma</button>
    <button type="button" class="m-sekme" data-sekme="bpm">🎧 BPM Bulma</button>
    <button type="button" class="m-sekme" data-sekme="ritim">🎼 Ritim Okuma</button>
    <button type="button" class="m-sekme" data-sekme="spontan">🫀 Spontan Tempo</button>
    <button type="button" class="m-sekme" data-sekme="aksak">🕳 Aksak Bulma</button>
    <button type="button" class="m-sekme" data-sekme="icsel">🧭 İçsel Ritim</button>
  </div>

  <!-- Vuruş Tutturma -->
  <div class="m-sekme-icerik" id="sekme-vurus">
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
        </select>
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
          <thead><tr><th>Faz</th><th class="sayi">Vuruş</th><th class="sayi">Kaçırılan</th>
                     <th class="sayi">Ort. sapma</th><th class="sayi">Ort. |sapma|</th><th class="sayi">Skor</th></tr></thead>
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
  <div class="m-sekme-icerik" id="sekme-bpm" hidden>
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
          <thead><tr><th>Tur</th><th class="sayi">Gerçek BPM</th><th class="sayi">Tahmin</th>
                     <th class="sayi">Hata</th><th class="sayi">Skor</th></tr></thead>
          <tbody id="bfTablo"></tbody>
        </table>
        <div class="m-kaydet-satir">
          <?= sonuc_formu('bf', 'bpm_bulma') ?>
          <button type="button" class="btn btn-golge" id="bfTekrar">Tekrar Oyna</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Ritim Okuma -->
  <div class="m-sekme-icerik" id="sekme-ritim" hidden>
    <p class="alan-ipucu">Ekrandaki 2 ölçülük deseni sayarak tam yerinde vur: "1 ve 2 ve", üçlemede "1-le-me".
       Göz–el eşgüdümü ve nota okumaya ilk adım (ev sayfasındaki modülün stüdyo sürümü).</p>
    <div class="filtre-satir">
      <label class="form-alan">Öğrenci<?= ogrenci_secimi('roOgrenci', $ogrenciler) ?></label>
      <label class="form-alan">Seviye
        <select id="roSeviye" class="secim">
          <option value="1" selected>1 — çeyrek + es</option>
          <option value="2">2 — sekizlikler ("ve")</option>
          <option value="3">3 — üçlemeler</option>
        </select>
      </label>
      <label class="form-alan">Tempo
        <select id="roBpm" class="secim">
          <option value="50">50 BPM</option>
          <option value="60" selected>60 BPM</option>
          <option value="72">72 BPM</option>
        </select>
      </label>
      <button type="button" class="btn btn-golge" id="roYenile">↻ Yeni desen</button>
    </div>
    <div class="ro-kok" id="roKok"></div>
    <div class="m-kaydet-satir" id="roKaydetSatir" hidden>
      <?= sonuc_formu('ro', 'ritim_okuma') ?>
    </div>
  </div>

  <!-- Spontan Tempo -->
  <div class="m-sekme-icerik" id="sekme-spontan" hidden>
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
  <div class="m-sekme-icerik" id="sekme-aksak" hidden>
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
      <div class="m-faz-etiket" id="abFaz">🎧 Dinle…</div>
      <div class="ab-cevaplar" id="abCevaplar" hidden>
        <button type="button" class="btn btn-birincil ab-cevap" data-cevap="duzenli">✓ Düzenliydi</button>
        <button type="button" class="btn btn-birincil ab-cevap" data-cevap="aksak">⚠ Aksadı</button>
      </div>
      <div class="m-tur-goster" id="abTur">Tur 1 / 8</div>
      <button type="button" class="btn btn-golge btn-kucuk" id="abIptal">İptal</button>
    </div>

    <div class="m-sonuc" id="abSonuc" hidden>
      <div class="m-skor-kart">
        <div class="m-skor" id="abSkor">–</div>
        <div class="m-skor-etiket">ALGI SKORU<br><small>8 turda doğru oranı</small></div>
      </div>
      <div class="m-sonuc-detay">
        <table class="tablo">
          <thead><tr><th>Tur</th><th>Dizi</th><th>Cevabın</th><th>Sonuç</th></tr></thead>
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
  <div class="m-sekme-icerik" id="sekme-icsel" hidden>
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
          <thead><tr><th>Faz</th><th class="sayi">Vuruş</th><th class="sayi">Kaçırılan</th>
                     <th class="sayi">Ort. |sapma|</th><th class="sayi">Skor</th></tr></thead>
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
      <thead><tr><th>Tarih</th><th>Öğrenci</th><th>Protokol</th><th>Kaynak</th><th class="sayi">BPM</th><th class="sayi">Skor</th><th>Not</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($sonKayitlar as $k): ?>
        <tr>
          <td><?= e(format_date_tr(substr($k['created_at'], 0, 10))) ?> <?= e(substr($k['created_at'], 11, 5)) ?></td>
          <td><a href="<?= e(url('ogrenci.php?id=' . (int)$k['ogrenci_id'])) ?>"><?= e($k['ogrenci_kod']) ?></a></td>
          <td><?= e(PROTOKOL_LABELS[$k['protokol']] ?? $k['protokol']) ?></td>
          <td><?= ($k['kaynak'] ?? 'atolye') === 'ev' ? '🏠 Ev' : '🎓 Atölye' ?></td>
          <td class="sayi"><?= $k['bpm'] ? (int)$k['bpm'] : '—' ?></td>
          <td class="sayi"><strong><?= (int)$k['skor'] ?></strong>/100</td>
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

<script src="<?= e(asset('js/ritim-okuma.js')) ?>"></script>
<script src="<?= e(asset('js/metronom.js')) ?>"></script>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
