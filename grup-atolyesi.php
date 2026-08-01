<?php
/**
 * Kanıt ve güvenlik çerçeveli grup ritim akışları.
 * Klinik protokol değildir; eğitim ortamında kolaylaştırıcı kullanımına yöneliktir.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$PAGE_TITLE = 'Grup Atölyesi';
require APP_DIR . '/includes/view/header.php';
?>
<link rel="stylesheet" href="<?= e(asset('css/grup-atolyesi.css')) ?>">

<div class="sayfa-baslik grup-sayfa-baslik">
  <div>
    <span class="grup-ust-etiket">KANIT ÇERÇEVELİ · GÜVENLİ · UYARLANABİLİR</span>
    <h1>Grup Atölyesi Çalışma Alanı</h1>
    <p>Ortak nabız, sıra alma, çağrı-cevap ve uyarlanmış hareket için kolaylaştırıcı ekranı.</p>
  </div>
  <span class="rozet rozet-acik">Eğitim ve grup çalışması aracı</span>
</div>

<section class="grup-sinir" aria-labelledby="grupSinirBaslik">
  <span class="grup-sinir-ikon" aria-hidden="true">🛡️</span>
  <div>
    <strong id="grupSinirBaslik">Bu modül ne yapar, ne yapmaz?</strong>
    <p>Yapılandırılmış ritim etkinliği sunar; tanı koymaz, sağlık sonucu vaat etmez ve uzman değerlendirmesinin
      yerini almaz. Katılım isteğe bağlıdır. Dinleme, daha az sesle çalma, oturarak uygulama ve mola her zaman geçerli seçimlerdir.</p>
  </div>
</section>

<div class="grup-ana-grid">
  <section class="kart grup-kurucu" aria-labelledby="grupKurucuBaslik">
    <div class="kart-baslik">
      <div>
        <h2 id="grupKurucuBaslik">1. Akışı seç ve uyarla</h2>
        <span class="alan-ipucu">Bir oturumda tek ana hedef kullanmak izlemeyi kolaylaştırır.</span>
      </div>
    </div>

    <div id="grupProtokolKartlari" class="grup-protokol-kartlari" role="radiogroup" aria-label="Grup akışı"></div>

    <div class="form-grid grup-ayarlar">
      <label class="form-alan">Toplam süre
        <select id="grupSure" class="secim">
          <option value="20">20 dakika</option>
          <option value="30">30 dakika</option>
          <option value="35" selected>35 dakika</option>
          <option value="45">45 dakika</option>
          <option value="60">60 dakika</option>
        </select>
      </label>
      <label class="form-alan">Ortak nabız
        <span class="grup-birim-girdi">
          <input id="grupBpm" class="girdi" type="number" min="40" max="120" value="64">
          <b>BPM</b>
        </span>
      </label>
      <label class="form-alan">Uygulama biçimi
        <select id="grupMod" class="secim">
          <option value="oturarak" selected>Oturarak / masa başı</option>
          <option value="ayakta">Ayakta — uygun katılımcılar</option>
        </select>
      </label>
      <label class="form-alan">Kılavuz sesi
        <select id="grupSes" class="secim">
          <option value="acik" selected>Metronom açık</option>
          <option value="kapali">Sessiz görsel nabız</option>
        </select>
      </label>
    </div>

    <div id="grupSeciliOzet" class="grup-secili-ozet" aria-live="polite"></div>
  </section>

  <aside class="kart grup-guvenlik-kart" aria-labelledby="grupGuvenlikBaslik">
    <div class="kart-baslik">
      <div>
        <h2 id="grupGuvenlikBaslik">2. Başlama kontrolü</h2>
        <span class="alan-ipucu">Dört koşul tamamlanmadan sayaç başlamaz.</span>
      </div>
      <span id="grupHazirlikRozet" class="rozet rozet-gri">0 / 4</span>
    </div>

    <div class="grup-kontrol-listesi">
      <label>
        <input type="checkbox" data-grup-kontrol="alan">
        <span><strong>Alan güvenli</strong><small>Geçiş yolu açık; sandalye, kablo ve enstrümanlar sabit.</small></span>
      </label>
      <label>
        <input type="checkbox" data-grup-kontrol="ses">
        <span><strong>Ses güvenli</strong><small>Konuşma rahat duyuluyor; yüksek ses ve uzun kesintisiz çalma yok.</small></span>
      </label>
      <label>
        <input type="checkbox" data-grup-kontrol="secim">
        <span><strong>Seçim hakkı söylendi</strong><small>Dinleme, düşük yoğunluk, oturma ve mola seçenekleri açıklandı.</small></span>
      </label>
      <label>
        <input type="checkbox" data-grup-kontrol="durdurma">
        <span><strong>Dur işareti ortak</strong><small>Herkes tek işaretle durmayı ve yeniden giriş için dört sayım beklemeyi biliyor.</small></span>
      </label>
    </div>

    <div id="grupHareketUyarisi" class="uyari-kutu" hidden>
      <strong>Ayakta uygulama seçildi.</strong> Denge veya hareket kısıtı olanlar için oturarak eşdeğer hareket belirleyin.
      Hızlı yön değişimi, tam dönüş ve katılımcılar arası temas kullanmayın.
    </div>

    <button type="button" id="grupBaslat" class="btn btn-birincil grup-baslat" disabled>
      ▶ Dört hazırlık vuruşuyla başlat
    </button>
  </aside>
</div>

<section id="grupOynatici" class="kart grup-oynatici" aria-labelledby="grupOynaticiBaslik" hidden>
  <div class="grup-oynatici-ust">
    <div>
      <span id="grupAdimSayisi" class="grup-ust-etiket">ADIM 1 / 6</span>
      <h2 id="grupOynaticiBaslik">Hazırlanıyor</h2>
    </div>
    <div class="grup-zamanlar" aria-label="Süreler">
      <span><small>Bu adım</small><b id="grupAdimSure">00:00</b></span>
      <span><small>Oturum</small><b id="grupToplamSure">00:00</b></span>
    </div>
  </div>

  <div class="grup-ilerleme" aria-hidden="true"><span id="grupIlerlemeCubugu"></span></div>

  <div class="grup-oynatici-govde">
    <div id="grupNabiz" class="grup-nabiz" aria-hidden="true">
      <span></span><span></span><span></span><span></span>
    </div>
    <p id="grupYonerge" class="grup-ana-yonerge"></p>
    <div class="grup-kolaylastirici">
      <span class="emoji-sus" aria-hidden="true">🎙️</span>
      <p><strong>Kolaylaştırıcı notu</strong><br><span id="grupKolaylastirici"></span></p>
    </div>
    <p id="grupDurum" class="grup-durum" role="status" aria-live="polite">Hazır</p>
  </div>

  <div class="grup-oynatici-kontroller">
    <button type="button" id="grupOnceki" class="btn btn-golge"><span class="emoji-sus" aria-hidden="true">←</span> Önceki</button>
    <button type="button" id="grupDuraklat" class="btn btn-birincil">Duraklat</button>
    <button type="button" id="grupSonraki" class="btn btn-golge">Sonraki →</button>
    <button type="button" id="grupBitir" class="btn btn-tehlike"><span class="emoji-sus" aria-hidden="true">■</span> Bitir ve sesi kes</button>
  </div>
</section>

<section class="kart" aria-labelledby="grupBilimBaslik">
  <div class="kart-baslik">
    <div>
      <h2 id="grupBilimBaslik">Kaynak taramasının sonucu</h2>
      <span class="alan-ipucu">Yerel eğitim notlarındaki yöntemlerle bağımsız bilimsel literatür birbirinden ayrıldı.</span>
    </div>
    <a class="btn btn-kucuk btn-golge" href="<?= e(url('calismalar.php')) ?>">Bilimsel Çalışmalar →</a>
  </div>

  <div class="grup-kanit-grid">
    <article>
      <span class="rozet rozet-kanit-orta">Umut verici</span>
      <h3>Kişilerarası senkron</h3>
      <p>60 deneyin meta-analizi, eşzamanlı hareketin tutum ve davranış düzeyindeki prososyal ölçümlerle orta büyüklükte ilişkisini bildirdi.
        Deney etkileri ve bağlam sonucu değiştirebilir.</p>
      <a href="https://doi.org/10.1027/2151-2604/a000252" target="_blank" rel="noopener">Rennung &amp; Göritz, 2016 ↗</a>
    </article>
    <article>
      <span class="rozet rozet-kanit-zayif">Ön çalışma</span>
      <h3>Grup davulu</h3>
      <p>On haftalık kontrollü fakat randomize olmayan bir çalışma olumlu sonuçlar bildirdi. Tek çalışma, seçilim ve bağlam sınırlılıkları
        nedeniyle sonuç vaadi için yeterli değildir.</p>
      <a href="https://doi.org/10.1371/journal.pone.0151136" target="_blank" rel="noopener">Fancourt ve ark., 2016 ↗</a>
    </article>
    <article>
      <span class="rozet rozet-kanit-orta">Koşullu kanıt</span>
      <h3>Ritmik hareket ipucu</h3>
      <p>İnme sonrası yürümede ritmik işitsel ipuçları için olumlu fakat heterojen bulgular var. Bu, genel atölyede bağımsız rehabilitasyon
        uygulama yetkisi vermez.</p>
      <a href="https://doi.org/10.1186/s12906-023-04310-3" target="_blank" rel="noopener">Gonzalez-Hoelling ve ark., 2024 ↗</a>
    </article>
    <article>
      <span class="rozet rozet-kanit-yok">Kaynak değil</span>
      <h3>Yerel PDF müfredatı</h3>
      <p>16 dosya ayrıntılı etkinlik yönergeleri içeriyor; fakat kaynakça, örneklem, karşılaştırma grubu ve ölçüm yöntemi yok.
        Bu nedenle bilimsel kanıt değil, uygulama fikri olarak kullanıldı.</p>
      <span class="alan-ipucu">Özgün simge sistemi ve metinler kopyalanmadı.</span>
    </article>
  </div>
</section>

<section class="kart grup-dislananlar" aria-labelledby="grupDislananBaslik">
  <h2 id="grupDislananBaslik">Kaynaklardan neden doğrudan alınmayanlar</h2>
  <div class="grup-dislanan-grid">
    <p><strong>“En iyi katılımcı” ilanı</strong><span>Grup güveni ve öğrenme odağı için performans sıralaması kullanılmıyor.</span></p>
    <p><strong>Zorunlu temas ve katılım</strong><span>Temas, solo ve hareket isteğe bağlı; pas deme hakkı korunuyor.</span></p>
    <p><strong>Mum ve kontrollü nefes</strong><span>Yangın riski kaldırıldı; nefes protokolü sağlık iddiasına dönüştürülmedi.</span></p>
    <p><strong>Tam dönüş ve göğse vurma</strong><span>Düşme ve fiziksel rahatsızlık riski nedeniyle daha güvenli eşdeğerlerle değiştirildi.</span></p>
  </div>
</section>

<script src="<?= e(asset('js/grup-atolyesi-cekirdegi.js')) ?>"></script>
<script src="<?= e(asset('js/grup-atolyesi.js')) ?>"></script>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
