<?php
/**
 * Kalın–İnce Tını — Kalıp Kartları (atölye aracı).
 *
 * ÖLÇÜM ARACI DEĞİLDİR: puan yok, kayıt yok, protokol yok.
 * Eğitmen kalıbı ekrandan gösterir/çalar, grup kendi cisimleriyle tekrarlar.
 *
 * Neden ölçüm yok: tını alt ölçekleri psikometrik olarak ayrışan bir bireysel
 * puan vermiyor (M-Factor, n=1006, ω=0,367) ve işitsel ayırt etme eğitimi
 * eğitilen uyaranın ötesine genellenmiyor (Halliday ve ark. 2012). Puanlı bir
 * modül, literatürün anlamsız saydığı bir sayı üretirdi.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$gruplar = groups_list(true);

$PAGE_TITLE = 'Kalın–İnce Kalıp Kartları';
require APP_DIR . '/includes/view/header.php';
?>
<link rel="stylesheet" href="<?= e(asset('css/tini.css')) ?>">

<div class="sayfa-baslik tk-sayfa-baslik">
  <div>
    <h1>Kalın–İnce Kalıp Kartları</h1>
    <p class="alan-ipucu">Atölye aracı — kalıbı göster, çal, grup kendi cisimleriyle tekrarlasın.
      <strong>Puanlama yok</strong>, kayıt tutulmaz.</p>
  </div>
  <span class="rozet rozet-gri">ölçüm değil, öğretim aracı</span>
</div>

<section class="kart tk-ayar" aria-labelledby="tkAyarBaslik">
  <h2 id="tkAyarBaslik" class="tk-gizli-baslik">Ayarlar</h2>
  <div class="tk-kademeler" id="tkKademeler" role="group" aria-label="Kademe"></div>

  <div class="tk-ayar-satir">
    <label class="form-alan">Tempo
      <span class="tk-birim"><input type="range" id="tkBpm" min="40" max="120" step="4" value="72"><b id="tkBpmYazi">72</b></span>
    </label>
    <label class="form-alan">Görünüm
      <select id="tkGorunum" class="secim">
        <option value="simge" selected>Simge (dikdörtgen / daire)</option>
        <option value="el">El (yumruk / açık el)</option>
      </select>
    </label>
    <label class="form-alan">Grup
      <select id="tkGrup" class="secim">
        <option value="">— Genel çalışma —</option>
        <?php foreach ($gruplar as $g): ?>
        <option value="<?= (int)$g['id'] ?>"><?= e($g['ad']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan">
      <span><input type="checkbox" id="tkOtomatik"> Otomatik sıra</span>
      <small class="alan-ipucu">Kalıp çalınır, kısa ara verilir, sonrakine geçer.</small>
    </label>
  </div>
</section>

<!-- ==================== SAHNE (yansıtılabilir) ==================== -->
<section class="kart tk-sahne" id="tkSahne" aria-labelledby="tkKalipBaslik">
  <div class="tk-sahne-ust">
    <div>
      <span class="tk-kademe-etiket" id="tkKademeEtiket">A</span>
      <strong id="tkKalipBaslik">1 / 4</strong>
    </div>
    <div class="tk-sahne-arac">
      <button type="button" class="btn btn-kucuk btn-golge" id="tkTamEkran" title="Yansıtmak için tam ekran"><span class="emoji-sus" aria-hidden="true">⛶</span> Tam ekran</button>
    </div>
  </div>

  <!-- Grup şeridi: hangi grubun kaç vuruşu var, kim susuyor -->
  <!--
    İki görsel bölge de aria-live idi ve ikisi de AYNI işlemde yeniden
    çiziliyordu: ekran okuyucu her kalıp değişiminde kutuları ve tek tek
    vuruş karolarını üst üste okuyordu. Tek anlamlı duyuru okunur metindir;
    canlı bölge oraya taşındı, bunlar sessiz görsel kaldı.
  -->
  <div class="tk-grup-serit" id="tkGrupSerit"></div>

  <div class="tk-kalip" id="tkKalip"></div>

  <p class="tk-okunur" id="tkOkunur" role="status" aria-live="polite" aria-atomic="true"></p>

  <div class="tk-butonlar">
    <button type="button" class="btn btn-golge tk-nav" id="tkOnceki" aria-label="Önceki kalıp">←</button>
    <button type="button" class="btn btn-birincil tk-cal" id="tkCal"><span class="emoji-sus" aria-hidden="true">▶</span> Çal</button>
    <button type="button" class="btn btn-golge tk-nav" id="tkSonraki" aria-label="Sonraki kalıp">→</button>
  </div>
  <div class="tk-alt-butonlar">
    <button type="button" class="btn btn-kucuk btn-golge" id="tkKarisik"><span class="emoji-sus" aria-hidden="true">🔀</span> Karışık kalıp</button>
    <button type="button" class="btn btn-kucuk btn-golge" id="tkYazdir"><span class="emoji-sus" aria-hidden="true">🖨</span> Kartları yazdır</button>
  </div>
</section>

<section class="kart" aria-labelledby="tkNasilBaslik">
  <div class="kart-baslik">
    <div>
      <h2 id="tkNasilBaslik">Atölyede nasıl kullanılır</h2>
      <span class="alan-ipucu">Etkinliğin akışı — eğitmen için kısa hatırlatma.</span>
    </div>
  </div>
  <ol class="tk-adimlar">
    <li><strong>Keşif.</strong> Katılımcılar çevredeki cisimlere (masa, kitap, sandalye) vurup tınıları dinler.
      Aynı cisme farklı noktadan vurunca sesin değiştiğini kendileri bulur.</li>
    <li><strong>Ayrım.</strong> Cisimler <em>kalın</em> ve <em>ince</em> diye iki yana ayrılır.
      Neden öyle ayırdıklarını anlatırlar.</li>
    <li><strong>İki grup.</strong> Grup ikiye bölünür ve karşılıklı konumlanır: bir yan kalın, öteki ince.</li>
    <li><strong>Kalıp.</strong> Ekrandaki kalıp çalınır; her grup kendi sırası geldiğinde kendi cismine vurur.
      Bazı kalıplarda bir grup hiç çalmaz — susmayı beklemek de çalışmanın parçası.</li>
    <li><strong>Ellerle.</strong> Aynı kalıplar yalnız ellerle tekrarlanır: yumruk kalın, açık el ince.</li>
    <li><strong>Yer değişimi.</strong> Gruplar yer değiştirip baştan başlar.</li>
  </ol>
  <p class="bilgi-kutu">
    <strong>Not:</strong> Aynı cisme farklı noktadan vurmak sesin <em>perdesini</em> değil
    <em>parlaklığını</em> değiştirir. “Kalın/ince” burada günlük dildeki karşılığıyla kullanılır;
    bu bir perde eğitimi değildir. Etkinlik puanlanmaz ve öğrenci kaydına işlenmez.
  </p>
</section>

<script src="<?= e(asset('js/tini-cekirdegi.js')) ?>"></script>
<script src="<?= e(asset('js/tini-kartlari.js')) ?>"></script>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
