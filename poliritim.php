<?php
/**
 * Poliritim Stüdyosu — iki elin bağımsız ritmi, aşamalı çalışma ve oyun.
 *
 * Pedagoji: klasik poliritim öğretimi sırası uygulanır — önce tek el, sonra
 * öteki el, sonra iki el rehberli, en sonda rehber sessizken iki el. Yalnız
 * son aşama ÖLÇÜMdür; ilk üçü alıştırma ve kayıt üretmez (bkz. §Aşamalar).
 *
 * Ölçüm ayrıştırması: her oran (3:2, 4:3 …) ayrı bir VARYANT olarak
 * kaydedilir; 3:2 skoru ile 7:4 skoru aynı seride karşılaştırılamaz.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('poliritim.php');
    $islem = (string)($_POST['islem'] ?? '');
    if ($islem === 'sonuc_kaydet') {
        /* protokol SUNUCUDA sabitlenir: array_merge ile sağ taraf kazanır,
           böylece istemcinin gönderdiği bir 'protokol' alanı yok sayılır. */
        $res = protocol_result_save(array_merge($_POST, ['protokol' => 'poliritim']));
        flash_set($res['ok'] ? 'basari' : 'hata',
            $res['ok'] ? 'Poliritim sonucu kaydedildi.' : $res['error']);
    } elseif ($islem === 'sonuc_sil') {
        protocol_result_delete((int)($_POST['id'] ?? 0));
        flash_set('basari', 'Kayıt silindi.');
    }
    redirect('poliritim.php');
}

$ogrenciler = students_list(null, 1);

/* Yalnız bu protokolün son kayıtları (varyantıyla birlikte) */
$sonKayitlar = db()->query("SELECT p.*, o.kod AS ogrenci_kod
                              FROM protokol_sonuclari p
                              JOIN ogrenciler o ON o.id = p.ogrenci_id
                             WHERE p.protokol = 'poliritim'
                             ORDER BY p.created_at DESC, p.id DESC LIMIT 12")->fetchAll();

$PAGE_TITLE = 'Poliritim Stüdyosu';
require APP_DIR . '/includes/view/header.php';
?>
<link rel="stylesheet" href="<?= e(asset('css/poliritim.css')) ?>">

<div class="sayfa-baslik pr-sayfa-baslik">
  <div>
    <h1>Poliritim Stüdyosu</h1>
    <p class="alan-ipucu">İki el aynı anda farklı ritim tutar. Sağ el <kbd>J</kbd>, sol el <kbd>F</kbd>
      — ya da ekrandaki padlara dokunun.</p>
  </div>
  <span class="rozet rozet-acik">İki elin bağımsızlığı</span>
</div>

<!-- ==================== ORAN SEÇİMİ ==================== -->
<section class="kart" aria-labelledby="prOranBaslik">
  <div class="kart-baslik">
    <div>
      <h2 id="prOranBaslik">Oran</h2>
      <span class="alan-ipucu">Sağ el : sol el vuruş sayısı. Kolaydan zora sıralı.</span>
    </div>
    <span class="rozet rozet-gri" id="prOranNot"></span>
  </div>
  <div class="pr-oranlar" id="prOranlar" role="group" aria-label="Poliritim oranı"></div>

  <div class="form-grid">
    <label class="form-alan">Referans el temposu
      <span class="motor-birim-girdi"><input type="number" id="prBpm" class="girdi" min="40" max="200" value="80"><b>BPM</b></span>
    </label>
    <label class="form-alan">Referans el
      <select id="prReferans" class="secim">
        <option value="sag" selected>Sağ el (tempo sağ ele göre)</option>
        <option value="sol">Sol el (tempo sol ele göre)</option>
      </select>
    </label>
    <label class="form-alan">Döngü sayısı
      <select id="prDongu" class="secim">
        <option value="4">4 döngü (kısa)</option>
        <option value="8" selected>8 döngü</option>
        <option value="12">12 döngü (uzun)</option>
      </select>
    </label>
    <label class="form-alan">Değerlendirme penceresi
      <select id="prPencere" class="secim">
        <option value="180">Rahat · ±180 ms</option>
        <option value="140" selected>Standart · ±140 ms</option>
        <option value="100">Hassas · ±100 ms</option>
        <option value="70">İleri · ±70 ms</option>
      </select>
    </label>
    <label class="form-alan">Katılımcı
      <select id="prOgrenci" class="secim">
        <option value="">— Seçin (kayıt için) —</option>
        <?php foreach ($ogrenciler as $o): ?>
        <option value="<?= (int)$o['id'] ?>"><?= e($o['kod']) ?><?= $o['grup_ad'] ? ' — ' . e($o['grup_ad']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan">Klik düzeyi
      <input type="range" id="prSes" min="0" max="100" value="70" aria-label="Klik ses düzeyi">
    </label>
  </div>

  <label class="form-alan" style="margin-top:.4rem">
    <span><input type="checkbox" id="prStdMod"> 📏 Standart ölçüm koşulu</span>
    <small class="alan-ipucu">80 BPM · 8 döngü · ±140 ms · referans sağ el · rehber sessiz.
      Dönem karşılaştırması yalnız aynı koşulda anlamlı; işaretliyken ayarlar kilitlenir.</small>
  </label>

  <p class="bilgi-kutu" id="prPencereUyari" hidden></p>
</section>

<!-- ==================== AŞAMALAR ==================== -->
<section class="kart" aria-labelledby="prAsamaBaslik">
  <div class="kart-baslik">
    <div>
      <h2 id="prAsamaBaslik">Aşamalar</h2>
      <span class="alan-ipucu">Poliritim tek elden öğrenilir: önce sağ, sonra sol, sonra birlikte.</span>
    </div>
  </div>
  <div class="pr-asamalar" id="prAsamalar"></div>
  <p class="alan-ipucu">İlk üç aşama <strong>alıştırmadır</strong> ve kayıt üretmez.
    Yalnız 4. aşama (rehber sessiz, iki el) ölçüm sayılır — rehber sesi açıkken ölçülen şey
    ritmi kurmak değil, sesi takip etmektir.</p>
</section>

<!-- ==================== MNEMONİK ==================== -->
<section class="kart" aria-labelledby="prMnemonikBaslik">
  <div class="kart-baslik">
    <div>
      <h2 id="prMnemonikBaslik">Söyleyerek öğren</h2>
      <span class="alan-ipucu">Cümleyi ritmiyle söyleyin; heceler vuruşlara oturur.
        Renk hangi elin vuracağını gösterir.</span>
    </div>
    <button type="button" class="btn btn-kucuk btn-golge" id="prMnemonikCal"><span class="emoji-sus" aria-hidden="true">🔊</span> Cümleyi dinlet</button>
  </div>
  <div class="pr-mnemonik" id="prMnemonik" aria-live="polite"></div>
</section>

<!-- ==================== SAHNE ==================== -->
<section class="kart" aria-labelledby="prFaz">
  <div class="pr-sahne-ust">
    <div>
      <div class="pr-faz" id="prFaz">Hazır</div>
      <div class="pr-buyuk-sayac" id="prSayac">–</div>
      <small class="alan-ipucu" id="prSahneAlt">Bir oran seçip “Başlat”a basın.</small>
    </div>
    <div class="pr-dongu-sayaci" id="prDonguSayaci"></div>
  </div>

  <div class="pr-gorseller">
    <div class="pr-daire-sar">
      <svg class="pr-daire" id="prDaire" viewBox="0 0 200 200" role="img"
           aria-label="İki elin vuruşlarının çember üzerindeki konumu"></svg>
    </div>
    <div class="pr-izgara-sar">
      <!-- İmleç ızgaranın İÇİNDE: yoğun oranlarda ızgara yatay kayınca hizası bozulmasın.
           Kaydırma yalnız bu kutuda olur; sayfa gövdesi asla yatay kaymaz. -->
      <div class="pr-izgara-kaydir">
        <div class="pr-izgara" id="prIzgara"><div class="pr-imlec" id="prImlec"></div></div>
      </div>
      <p class="alan-ipucu" id="prIzgaraAciklama" style="margin:.4rem 0 0"></p>
    </div>
  </div>

  <div class="pr-padlar">
    <button type="button" class="pr-pad pr-pad-sol" id="prSolPad" data-el="sol">
      <span class="pr-pad-ad">SOL EL</span><kbd>F</kbd><small id="prSolCanli">hazır</small>
    </button>
    <button type="button" class="pr-pad pr-pad-sag" id="prSagPad" data-el="sag">
      <span class="pr-pad-ad">SAĞ EL</span><kbd>J</kbd><small id="prSagCanli">hazır</small>
    </button>
  </div>

  <div class="pr-canli" id="prCanli" hidden>
    <div class="pr-canli-oge"><b id="prCanliSag">–</b><span>Sağ el · isabet</span></div>
    <div class="pr-canli-oge"><b id="prCanliSol">–</b><span>Sol el · isabet</span></div>
    <div class="pr-canli-oge"><b id="prCanliSeri">0</b><span>Üst üste doğru</span></div>
  </div>

  <div class="form-butonlar">
    <button type="button" class="btn btn-birincil" id="prBaslat"><span class="emoji-sus" aria-hidden="true">▶</span> Başlat</button>
    <button type="button" class="btn btn-golge" id="prDurdur" hidden><span class="emoji-sus" aria-hidden="true">■</span> Durdur</button>
    <button type="button" class="btn btn-golge" id="prDinlet"><span class="emoji-sus" aria-hidden="true">🔊</span> Önce dinlet</button>
  </div>
</section>

<!-- ==================== SONUÇ ==================== -->
<section class="kart" id="prSonuc" hidden aria-labelledby="prSonucBaslik">
  <div class="kart-baslik">
    <div>
      <h2 id="prSonucBaslik">Sonuç</h2>
      <span class="alan-ipucu" id="prSonucAltBaslik"></span>
    </div>
    <span class="rozet rozet-gri" id="prSonucRozet"></span>
  </div>

  <div class="pr-skor-grid">
    <div class="pr-ana-skor"><strong id="prSkor">–</strong><span>OYUN SKORU</span></div>
    <div><strong id="prKapsama">–</strong><span>Yakalanan vuruş</span></div>
    <div><strong id="prKararlilik">–</strong><span>Kararlılık (SD)</span></div>
    <div><strong id="prOranHata">–</strong><span>Oran sapması</span></div>
    <div><strong id="prFazKayma">–</strong><span>Bağımsızlık</span></div>
  </div>

  <div class="pr-el-sonuclar">
    <article class="pr-el-sag"><h3>Sağ el</h3><div id="prSagSonuc"></div></article>
    <article class="pr-el-sol"><h3>Sol el</h3><div id="prSolSonuc"></div></article>
  </div>

  <div class="pr-sapma-grafik" id="prSapmaGrafik" aria-label="Vuruş sapmalarının dağılımı"></div>

  <p class="pr-yorum" id="prYorum"></p>
  <p class="alan-ipucu">Skor oyunlaştırma içindir. Karşılaştırma <strong>kararlılık (SD)</strong> ve
    el başına sapma üzerinden okunmalıdır; tek bileşik sayı sabit gecikmeyle kararsızlığı karıştırır.</p>

  <form method="post" action="<?= e(url('poliritim.php')) ?>" class="pr-kaydet-form" id="prKaydetForm" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="sonuc_kaydet">
    <input type="hidden" name="ogrenci_id" id="prFormOgrenci" value="">
    <input type="hidden" name="varyant" id="prFormVaryant" value="">
    <input type="hidden" name="bpm" id="prFormBpm" value="">
    <input type="hidden" name="skor" id="prFormSkor" value="">
    <input type="hidden" name="detay" id="prFormDetay" value="">
    <input type="hidden" name="standart" id="prFormStandart" value="0">
    <input type="hidden" name="sd_ms" id="prFormSd" value="">
    <input type="hidden" name="kalite" id="prFormKalite" value="">
    <input type="text" name="notlar" class="girdi" maxlength="200" placeholder="Not (isteğe bağlı)" style="max-width:260px">
    <button type="submit" class="btn btn-birincil">Sonucu Kaydet</button>
  </form>
  <p class="alan-ipucu" id="prKaydetUyari" hidden></p>

  <div class="form-butonlar">
    <button type="button" class="btn btn-birincil" id="prTekrar">Tekrar dene</button>
    <button type="button" class="btn btn-golge" id="prSonrakiAsama" hidden>Sonraki aşama →</button>
    <button type="button" class="btn btn-golge" id="prSonucKapat">Kapat</button>
  </div>
</section>

<!-- ==================== SON KAYITLAR ==================== -->
<section class="kart" aria-labelledby="prGecmisBaslik">
  <div class="kart-baslik">
    <div>
      <h2 id="prGecmisBaslik">Son Poliritim Ölçümleri</h2>
      <span class="alan-ipucu">Her oran ayrı seri olarak izlenir — 3:2 ile 7:4 karşılaştırılmaz.</span>
    </div>
  </div>
  <?php if (!$sonKayitlar): ?>
    <div class="bos-durum">Henüz kayıt yok. 4. aşamayı tamamlayıp bir katılımcıya kaydedin.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th scope="col">Tarih</th><th scope="col">Katılımcı</th><th scope="col">Oran</th><th scope="col" class="sayi">BPM</th>
        <th scope="col" class="sayi">Skor</th><th scope="col" class="sayi">SD</th><th scope="col">Not</th><th scope="col"></th></tr></thead>
      <tbody>
        <?php foreach ($sonKayitlar as $k): ?>
        <tr>
          <td><?= e(format_date_tr(substr($k['created_at'], 0, 10))) ?> <?= e(substr($k['created_at'], 11, 5)) ?></td>
          <td><?= e($k['ogrenci_kod']) ?></td>
          <td><strong><?= e($k['varyant'] !== '' ? $k['varyant'] : '—') ?></strong></td>
          <td class="sayi"><?= $k['bpm'] ? (int)$k['bpm'] : '—' ?></td>
          <td class="sayi"><strong><?= (int)$k['skor'] ?></strong>/100<?= (int)($k['standart'] ?? 0) === 1 ? ' 📏' : '' ?></td>
          <td class="sayi"><?= $k['sd_ms'] !== null && $k['sd_ms'] !== '' ? (int)$k['sd_ms'] . ' ms' : '—' ?></td>
          <td><?= e(mb_strimwidth((string)$k['notlar'], 0, 40, '…')) ?></td>
          <td class="sag">
            <form method="post" action="<?= e(url('poliritim.php')) ?>" data-onay="Kayıt silinsin mi?">
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
  <p class="alan-ipucu">Skorlar eğitmenin iç izleme aracıdır; veli raporuna yansıtılmaz.</p>
</section>

<script src="<?= e(asset('js/zamanlama-cekirdegi.js')) ?>"></script>
<script src="<?= e(asset('js/poliritim-cekirdegi.js')) ?>"></script>
<script src="<?= e(asset('js/poliritim-studyo.js')) ?>"></script>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
