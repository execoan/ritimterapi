/* ================================================================
   Ritim Okuma Laboratuvarı v5
   - 3 seviye × 8 ders × 16 alıştırma = 384 kademeli örnek
   - abcjs 6.6.3 ile standart porteli nota
   - Yazılan ritmi gerçek onset zamanlarında seslendirir
   - "1-e-ve-a" ve "1-le-me" sayım haritası
   - Dinleme, deşifre, tekil vuruş eşleme ve ayrıntılı sonuç
   Kullanım: RitimOkuma.baslat(kok, {seviye, bpm, rehber, onBitti})
   ================================================================ */
window.RitimOkuma = (function () {
  'use strict';

  var zamanlama = window.RitimZamanlama;
  if (!zamanlama) { throw new Error('Ortak zamanlama çekirdeği yüklenemedi.'); }
  var ogrenme = window.RitimOgrenme;
  if (!ogrenme) { throw new Error('Adaptif ritim öğrenme çekirdeği yüklenemedi.'); }

  var SEVIYE_ADI = { 1: 'Kolay', 2: 'Orta', 3: 'Zor' };
  var ORNEK_SAYISI = 16;
  var VURUS_ADEDI = 8; // iki ölçü × 4/4
  var ILK_VURUS_EK_MS = 80;
  var WIDGET_SAYACI = 0;
  var DEGERLENDIRME_ANAHTARI = 'ritim_okuma_degerlendirme_v1';

  /*
   * Ritim oyunları tek bir doğru/yanlış eşiği yerine farklı zaman
   * pencereleri kullanır. Buradaki "tam", tam puan bölgesini; "pencere"
   * ise vuruşun hâlâ doğru kabul edildiği en dış sınırı belirtir.
   * Özel profil 60–300 ms arasında kullanıcı tarafından değiştirilebilir.
   */
  var DEGERLENDIRME_PROFILLERI = {
    ogrenme: {
      ad: 'Öğrenme',
      aciklama: 'Yeni başlayanlar ve zor ritimleri düşük tempoda çözmek için geniş pencere.',
      pencere: { 1: 220, 2: 200, 3: 180 },
      tam: { 1: 70, 2: 60, 3: 55 }
    },
    dengeli: {
      ad: 'Dengeli',
      aciklama: 'Ders çalışması için önerilen, hatayı gösterirken ritmi bölmeyen profil.',
      pencere: { 1: 180, 2: 160, 3: 140 },
      tam: { 1: 55, 2: 45, 3: 40 }
    },
    arcade: {
      ad: 'Arcade',
      aciklama: 'Katmanlı ritim oyunu pencerelerine yakın, daha keskin değerlendirme.',
      pencere: { 1: 180, 2: 135, 3: 90 },
      tam: { 1: 45, 2: 30, 3: 23 }
    },
    profesyonel: {
      ad: 'Profesyonel',
      aciklama: 'Stüdyo ve ileri seviye zamanlama çalışması için dar pencere.',
      pencere: { 1: 110, 2: 90, 3: 70 },
      tam: { 1: 30, 2: 25, 3: 20 }
    }
  };

  /*
   * Her hücre bir dörtlük vuruşluk alanı kaplar. `onset`, vuruş içindeki
   * ses başlangıçlarını gösterir. Görseldeki sayım heceleri ve demo sesi
   * aynı onset listesinden üretildiği için nota ile duyulan ritim ayrışmaz.
   */
  var TIPLER = {
    /*
     * L:1/8 temelinde ABC karşılıkları. Bütün ritimler tek çizgideki B
     * perdesinde yazılır; amaç perde değil ritmik deşifredir.
     */
    q:       { ad: 'Dörtlük',                 abc: 'B2',       onset: [0],                    bolum: 4 },
    rq:      { ad: 'Dörtlük sus',             abc: 'z2',       onset: [],                     bolum: 4 },
    h:       { ad: 'İkilik başlangıcı',        abc: 'B4',       onset: [0],                    bolum: 4 },
    hold:    { ad: 'Uzatma',                   abc: '',         onset: [],                     bolum: 4, bag: true },
    w:       { ad: 'Birlik başlangıcı',        abc: 'B8',       onset: [0],                    bolum: 4 },
    ee:      { ad: 'İki sekizlik',             abc: 'BB',       onset: [0, 0.5],               bolum: 4 },
    er:      { ad: 'Sekizlik ve sekizlik sus', abc: 'Bz',       onset: [0],                    bolum: 4 },
    re:      { ad: 'Sekizlik sus ve sekizlik', abc: 'zB',       onset: [0.5],                  bolum: 4 },
    ssss:    { ad: 'Dört onaltılık',           abc: 'B/B/B/B/', onset: [0, 0.25, 0.5, 0.75],  bolum: 4 },
    e_ss:    { ad: 'Sekizlik, iki onaltılık',  abc: 'BB/B/',    onset: [0, 0.5, 0.75],        bolum: 4 },
    ss_e:    { ad: 'İki onaltılık, sekizlik',  abc: 'B/B/B',    onset: [0, 0.25, 0.5],        bolum: 4 },
    s_e_s:   { ad: 'Onaltılık-sekizlik-onaltılık', abc: 'B/BB/', onset: [0, 0.25, 0.75],      bolum: 4 },
    dot_s:   { ad: 'Noktalı sekizlik-onaltılık', abc: 'B3/2B/', onset: [0, 0.75],             bolum: 4 },
    s_dot:   { ad: 'Onaltılık-noktalı sekizlik', abc: 'B/B3/2', onset: [0, 0.25],             bolum: 4 },
    r_s_e:   { ad: 'Onaltılık sus-onaltılık-sekizlik', abc: 'z/B/B', onset: [0.25, 0.5],      bolum: 4 },
    s_r_ss:  { ad: 'Onaltılık, sus, iki onaltılık', abc: 'B/z/B/B/', onset: [0, 0.5, 0.75],   bolum: 4 },
    r_e_s:   { ad: 'Sekizlik sus ve iki onaltılık', abc: 'zB/B/', onset: [0.5, 0.75],          bolum: 4 },
    off_tie: { ad: 'Ve üzerinde bağlı senkop', abc: 'zB-',      onset: [0.5],                  bolum: 4, bagSonu: true },
    tie_end: { ad: 'Bağın devamı ve sekizlik', abc: 'BB',       onset: [0.5],                  bolum: 4, bagBasi: true },
    ttt:     { ad: 'Üçleme',                   abc: '(3BBB',     onset: [0, 1 / 3, 2 / 3],     bolum: 3 },
    trr:     { ad: 'Üçlemede son iki sus',     abc: '(3Bzz',     onset: [0],                    bolum: 3 },
    rtt:     { ad: 'Üçlemede ilk sus',         abc: '(3zBB',     onset: [1 / 3, 2 / 3],         bolum: 3 },
    trt:     { ad: 'Üçlemede orta sus',        abc: '(3BzB',     onset: [0, 2 / 3],             bolum: 3 },
    ttr:     { ad: 'Üçlemede son sus',         abc: '(3BBz',     onset: [0, 1 / 3],             bolum: 3 }
  };

  /*
   * Makrolar, müzikal olarak anlamlı hücre dizileridir. Uzun notalar ve
   * ölçü çizgisini aşan senkoplar tek tek rastgele hücrelerle bozulmasın diye
   * birlikte tutulur.
   */
  var DERSLER = {
    1: [
      { ad: 'Dörtlük ve uzun değerler', parca: [['q'], ['q'], ['h', 'hold'], ['w', 'hold', 'hold', 'hold']] },
      { ad: 'Dörtlük suslar', parca: [['q'], ['q'], ['rq'], ['h', 'hold']] },
      { ad: 'Sekizlik çiftleri', parca: [['q'], ['ee'], ['q'], ['h', 'hold']] },
      { ad: 'Dörtlük–sekizlik geçişi', parca: [['q'], ['ee'], ['ee'], ['rq']] },
      { ad: 'Sekizlik suslar', parca: [['q'], ['ee'], ['er'], ['re'], ['rq']] },
      { ad: 'Vuruşun “ve”si', parca: [['q'], ['re'], ['ee'], ['er'], ['re']] },
      { ad: 'İki ölçü süreklilik', parca: [['q'], ['ee'], ['h', 'hold'], ['re'], ['er']] },
      { ad: 'Kolay deşifre', parca: [['q'], ['rq'], ['ee'], ['er'], ['re'], ['h', 'hold']] }
    ],
    2: [
      { ad: 'Dört onaltılık', parca: [['q'], ['ee'], ['ssss']] },
      { ad: 'Sekizlik + iki onaltılık', parca: [['q'], ['ee'], ['e_ss']] },
      { ad: 'İki onaltılık + sekizlik', parca: [['q'], ['ee'], ['ss_e']] },
      { ad: 'Ortadaki sekizlik', parca: [['s_e_s'], ['e_ss'], ['ss_e'], ['q']] },
      { ad: 'Noktalı ritimler', parca: [['dot_s'], ['s_dot'], ['ee'], ['q']] },
      { ad: 'Onaltılık suslar', parca: [['r_s_e'], ['s_r_ss'], ['r_e_s'], ['q'], ['ee']] },
      { ad: 'Bağ ve senkop', parca: [['q'], ['off_tie', 'tie_end'], ['dot_s'], ['re'], ['ee']] },
      { ad: 'Orta seviye deşifre', parca: [['q'], ['ee'], ['ssss'], ['e_ss'], ['ss_e'], ['s_e_s'], ['dot_s'], ['r_s_e'], ['off_tie', 'tie_end']] }
    ],
    3: [
      { ad: 'Temel üçlemeler', parca: [['q'], ['ttt'], ['ttt'], ['ee']] },
      { ad: 'Üçleme susları', parca: [['ttt'], ['trr'], ['rtt'], ['trt'], ['ttr'], ['q']] },
      { ad: 'İkili–üçlü geçiş', parca: [['ee'], ['ssss'], ['ttt'], ['q']] },
      { ad: 'İleri onaltılık', parca: [['ssss'], ['e_ss'], ['ss_e'], ['s_e_s'], ['dot_s'], ['s_dot'], ['r_s_e']] },
      { ad: 'İleri senkop', parca: [['off_tie', 'tie_end'], ['re'], ['dot_s'], ['r_e_s'], ['s_r_ss']] },
      { ad: 'Ritmik yer değiştirme', parca: [['re'], ['r_s_e'], ['rtt'], ['s_dot'], ['off_tie', 'tie_end']] },
      { ad: 'Karma dayanıklılık', parca: [['ttt'], ['ssss'], ['dot_s'], ['rtt'], ['s_e_s'], ['off_tie', 'tie_end']] },
      { ad: 'Usta deşifre', parca: [['q'], ['ee'], ['ssss'], ['e_ss'], ['ss_e'], ['s_e_s'], ['dot_s'], ['s_dot'], ['r_s_e'], ['s_r_ss'], ['off_tie', 'tie_end'], ['ttt'], ['trr'], ['rtt'], ['trt'], ['ttr']] }
    ]
  };

  function rastgele(seed) {
    var s = seed >>> 0;
    return function () {
      s = (s * 1664525 + 1013904223) >>> 0;
      return s / 4294967296;
    };
  }

  function makroUygun(makro, kalan, konum) {
    if (makro.length > kalan) { return false; }
    var olcuKalan = 4 - (konum % 4);
    /*
     * Uzun nota makroları yanlışlıkla ölçü çizgisini aşmasın. Yalnız açıkça
     * bağ işareti taşıyan senkop makrosu ölçü çizgisinin ötesine geçebilir.
     */
    if (makro.length > olcuKalan && makro[0] !== 'off_tie') { return false; }
    if (konum !== 0) { return true; }
    var tip = TIPLER[makro[0]];
    return tip && tip.onset.some(function (x) { return x === 0; });
  }

  function ornekUret(seviye, dersNo, ornekNo, deneme) {
    var ders = DERSLER[seviye][dersNo];
    var rnd = rastgele(seviye * 100000 + dersNo * 1000 + ornekNo * 37 + (deneme || 0) * 7919 + 17);
    var kodlar = [];
    while (kodlar.length < VURUS_ADEDI) {
      var kalan = VURUS_ADEDI - kodlar.length;
      var uygun = ders.parca.filter(function (m) { return makroUygun(m, kalan, kodlar.length); });
      if (!uygun.length) { uygun = [['q']]; }
      var secilen = uygun[Math.floor(rnd() * uygun.length)];
      secilen.forEach(function (kod) { kodlar.push(kod); });
    }
    // Ölçü başlangıçları tamamen sessiz kalmasın; yön duygusu korunur.
    [0, 4].forEach(function (idx) {
      if (!TIPLER[kodlar[idx]].onset.length && !TIPLER[kodlar[idx]].bagBasi) { kodlar[idx] = 'q'; }
    });
    return {
      id: 'R' + seviye + '-' + (dersNo + 1) + '-' + (ornekNo + 1),
      seviye: seviye,
      dersNo: dersNo,
      ders: ders.ad,
      kodlar: kodlar
    };
  }

  function katalogOlustur(seviye) {
    var sonuc = [];
    var gorulen = {};
    DERSLER[seviye].forEach(function (_ders, dersNo) {
      for (var i = 0; i < ORNEK_SAYISI; i++) {
        var ornek;
        var deneme = 0;
        do {
          ornek = ornekUret(seviye, dersNo, i, deneme++);
        } while (gorulen[ornek.kodlar.join('|')] && deneme < 240);
        gorulen[ornek.kodlar.join('|')] = true;
        sonuc.push(ornek);
      }
    });
    return sonuc;
  }

  function guvenliOku(anahtar, varsayilan) {
    try {
      var deger = JSON.parse(localStorage.getItem(anahtar));
      return deger === null ? varsayilan : deger;
    } catch (e) { return varsayilan; }
  }

  function sinirla(sayi, alt, ust) {
    return Math.max(alt, Math.min(ust, Number(sayi)));
  }

  function degerlendirmeAyariOku() {
    var ham = guvenliOku(DEGERLENDIRME_ANAHTARI, {});
    var profil = ['ogrenme', 'dengeli', 'arcade', 'profesyonel', 'ozel'].indexOf(ham.profil) >= 0
      ? ham.profil : 'dengeli';
    return {
      profil: profil,
      ozel: {
        1: sinirla(ham.ozel && ham.ozel[1] || 180, 60, 300),
        2: sinirla(ham.ozel && ham.ozel[2] || 160, 60, 300),
        3: sinirla(ham.ozel && ham.ozel[3] || 140, 60, 300)
      },
      kalibrasyon: {
        yapildi: !!(ham.kalibrasyon && ham.kalibrasyon.yapildi),
        telafiMs: sinirla(ham.kalibrasyon && ham.kalibrasyon.telafiMs || 0, -400, 400),
        dagilimMs: Math.max(0, Number(ham.kalibrasyon && ham.kalibrasyon.dagilimMs) || 0),
        ornek: Math.max(0, Number(ham.kalibrasyon && ham.kalibrasyon.ornek) || 0),
        tarih: String(ham.kalibrasyon && ham.kalibrasyon.tarih || ''),
        tur: String(ham.kalibrasyon && ham.kalibrasyon.tur || ''),
        cihazImzasi: String(ham.kalibrasyon && ham.kalibrasyon.cihazImzasi || '')
      }
    };
  }

  function degerlendirmeAyariYaz(ayar) {
    try {
      localStorage.setItem(DEGERLENDIRME_ANAHTARI, JSON.stringify(ayar));
      window.dispatchEvent(new CustomEvent('ritim-zamanlama-guncellendi', {
        detail: { kalibrasyon: ayar.kalibrasyon }
      }));
    } catch (e) {}
  }

  function baslat(kok, opts) {
    opts = opts || {};
    if (kok.__roIptal) { kok.__roIptal(); }

    var seviye = [1, 2, 3].indexOf(Number(opts.seviye)) >= 0 ? Number(opts.seviye) : 1;
    var bpm = Math.max(35, Math.min(180, Number(opts.bpm) || 60));
    var rehber = ['tam', 'olcu', 'sessiz'].indexOf(opts.rehber) >= 0 ? opts.rehber : 'tam';
    var spb = 60 / bpm;
    var katalog = katalogOlustur(seviye);
    var indeksAnahtari = 'ritim_okuma_indeks_v3_' + seviye;
    var tamamAnahtari = 'ritim_okuma_tamam_v3_' + seviye;
    var ustalikAnahtari = 'ritim_okuma_ustalik_v1_' + seviye;
    var akilliAnahtari = 'ritim_okuma_akilli_v1';
    var indeks = Math.max(0, Math.min(katalog.length - 1, Number(guvenliOku(indeksAnahtari, 0)) || 0));
    var tamamlanan = guvenliOku(tamamAnahtari, []);
    if (!Array.isArray(tamamlanan)) { tamamlanan = []; }
    var ogrenmeDurumu = ogrenme.tamamlananlariAktar(
      guvenliOku(ustalikAnahtari, ogrenme.bosDurum()),
      tamamlanan,
      Date.now()
    );
    var akilliMod = guvenliOku(akilliAnahtari, true) !== false;
    try { localStorage.setItem(ustalikAnahtari, JSON.stringify(ogrenmeDurumu)); } catch (e) {}
    var portaId = 'roPorte' + (++WIDGET_SAYACI);

    var ctx = null;
    var cikis = null;
    var aktifTur = '';
    var zamanlayicilar = [];
    var taplar = [];
    var beklenen = [];
    var heceHaritasi = [];
    var notaHaritasi = [];
    var abcNotaSirasi = [];
    var porteCizimNo = 0;
    var nesil = 0;
    var degerlendirmeAyari = degerlendirmeAyariOku();
    var kalibrasyonBeklenen = [];
    var kalibrasyonTaplar = [];
    var kalibrasyonVurusAcik = false;

    kok.innerHTML =
      '<section class="ro-panel">' +
      '  <div class="ro-ust">' +
      '    <div><span class="ro-seviye"></span><strong class="ro-ders"></strong></div>' +
      '    <div class="ro-ilerleme-meta"></div>' +
      '  </div>' +
      '  <section class="ro-ogrenme" aria-label="Akıllı çalışma takibi">' +
      '    <div class="ro-ogrenme-baslik">' +
      '      <div><strong>🎯 Akıllı çalışma</strong><small>Eksik ritimleri bulur ve doğru zamanda yeniden getirir.</small></div>' +
      '      <button type="button" class="ro-akilli-toggle" aria-pressed="true"></button>' +
      '    </div>' +
      '    <div class="ro-ogrenme-istatistik">' +
      '      <span><b class="ro-kurs-ustalik">0%</b> kurs ustalığı</span>' +
      '      <span><b class="ro-calisilan-ustalik">0%</b> çalışılan ort.</span>' +
      '      <span><b class="ro-ustalasilan">0</b> ustalaşılan</span>' +
      '      <span><b class="ro-tekrar-sayisi">0</b> tekrar hazır</span>' +
      '      <span><b class="ro-basari-serisi">0</b> başarı serisi</span>' +
      '    </div>' +
      '    <div class="ro-ogrenme-ilerleme"><i></i></div>' +
      '    <div class="ro-zayif-ritimler"></div>' +
      '    <div class="ro-oneri-satiri">' +
      '      <span class="ro-oneri"></span>' +
      '      <button type="button" class="m-mini-btn ro-onerilen">Önerilene geç →</button>' +
      '    </div>' +
      '  </section>' +
      '  <div class="ro-nav">' +
      '    <button type="button" class="ro-nav-btn ro-onceki" aria-label="Önceki ritim">←</button>' +
      '    <div class="ro-nota-alani">' +
      '      <div class="ro-porteli" id="' + portaId + '" aria-label="İki ölçülük porteli ritim"></div>' +
      '      <div class="ro-sayim-baslik">Vuruş altı sayım heceleri</div>' +
      '      <div class="ro-desen" aria-label="İki ölçülük ritmin sayım heceleri"></div>' +
      '    </div>' +
      '    <button type="button" class="ro-nav-btn ro-sonraki" aria-label="Sonraki ritim">→</button>' +
      '  </div>' +
      '  <div class="ro-aciklama"></div>' +
      '  <section class="ro-degerlendirme" aria-label="Ritim değerlendirme ayarları">' +
      '    <div class="ro-degerlendirme-baslik">' +
      '      <div><strong>⏱ Zamanlama ve kalibrasyon</strong><small class="ro-pencere-ozet"></small></div>' +
      '      <span class="ro-kalibrasyon-rozet"></span>' +
      '    </div>' +
      '    <div class="ro-degerlendirme-grid">' +
      '      <label>Değerlendirme profili' +
      '        <select class="secim ro-profil">' +
      '          <option value="ogrenme">Öğrenme — geniş pencere</option>' +
      '          <option value="dengeli">Dengeli — önerilen</option>' +
      '          <option value="arcade">Arcade — katmanlı pencere</option>' +
      '          <option value="profesyonel">Profesyonel — dar pencere</option>' +
      '          <option value="ozel">Özel — seviyeye göre ms</option>' +
      '        </select>' +
      '      </label>' +
      '      <div class="ro-ozel-ms" hidden>' +
      '        <label>Kolay <input class="girdi ro-ms-kolay" type="number" min="60" max="300" step="5" inputmode="numeric"> ms</label>' +
      '        <label>Orta <input class="girdi ro-ms-orta" type="number" min="60" max="300" step="5" inputmode="numeric"> ms</label>' +
      '        <label>Zor <input class="girdi ro-ms-zor" type="number" min="60" max="300" step="5" inputmode="numeric"> ms</label>' +
      '      </div>' +
      '      <div class="ro-kalibrasyon-ayar">' +
      '        <label>Uygulanan telafi' +
      '          <span><input class="girdi ro-telafi-ms" type="number" min="-400" max="400" step="1" inputmode="numeric"> ms</span>' +
      '        </label>' +
      '        <button type="button" class="m-mini-btn ro-elle-uygula">Elle uygula</button>' +
      '        <button type="button" class="m-mini-btn ro-kalibre">🎧 Otomatik kalibre et</button>' +
      '        <button type="button" class="m-mini-btn ro-kal-sifirla">Sıfırla</button>' +
      '      </div>' +
      '    </div>' +
      '    <p class="ro-profil-aciklama"></p>' +
      '    <div class="ro-kalibrasyon-sahne" hidden>' +
      '      <div><strong class="ro-kalibrasyon-metin">Hazırlan…</strong><small class="ro-kalibrasyon-ilerleme">0/12</small></div>' +
      '      <button type="button" class="m-pad ro-kal-pad">VUR<small>duyduğun her klikte</small></button>' +
      '      <button type="button" class="btn btn-tehlike btn-kucuk ro-kal-durdur">Durdur</button>' +
      '    </div>' +
      '  </section>' +
      '  <div class="ro-baslangic-sayaci" hidden aria-live="assertive" aria-atomic="true">' +
      '    <small>Başlangıca</small><strong>4</strong><span>vuruş kaldı</span>' +
      '  </div>' +
      '  <div class="ro-durum" role="status" aria-live="polite">Ritmi dinleyebilir veya doğrudan okumaya başlayabilirsin.</div>' +
      '  <div class="ro-butonlar">' +
      '    <button type="button" class="btn btn-golge ro-dinle">🔊 Ritmi Dinle</button>' +
      '    <button type="button" class="btn btn-birincil ro-baslat">▶ Okumayı Başlat</button>' +
      '    <button type="button" class="btn btn-tehlike ro-iptal" hidden>■ Durdur</button>' +
      '    <button type="button" class="m-pad ro-pad" hidden>VUR<small>boşluk / dokun</small></button>' +
      '  </div>' +
      '  <div class="ro-sonuc" hidden></div>' +
      '</section>';

    var desenEl = kok.querySelector('.ro-desen');
    var portaEl = kok.querySelector('.ro-porteli');
    var durumEl = kok.querySelector('.ro-durum');
    var baslangicSayacEl = kok.querySelector('.ro-baslangic-sayaci');
    var dersEl = kok.querySelector('.ro-ders');
    var seviyeEl = kok.querySelector('.ro-seviye');
    var metaEl = kok.querySelector('.ro-ilerleme-meta');
    var akilliToggleBtn = kok.querySelector('.ro-akilli-toggle');
    var kursUstalikEl = kok.querySelector('.ro-kurs-ustalik');
    var calisilanUstalikEl = kok.querySelector('.ro-calisilan-ustalik');
    var ustalasilanEl = kok.querySelector('.ro-ustalasilan');
    var tekrarSayisiEl = kok.querySelector('.ro-tekrar-sayisi');
    var basariSerisiEl = kok.querySelector('.ro-basari-serisi');
    var ogrenmeIlerlemeEl = kok.querySelector('.ro-ogrenme-ilerleme i');
    var zayifRitimlerEl = kok.querySelector('.ro-zayif-ritimler');
    var oneriEl = kok.querySelector('.ro-oneri');
    var onerilenBtn = kok.querySelector('.ro-onerilen');
    var aciklamaEl = kok.querySelector('.ro-aciklama');
    var dinleBtn = kok.querySelector('.ro-dinle');
    var baslatBtn = kok.querySelector('.ro-baslat');
    var iptalBtn = kok.querySelector('.ro-iptal');
    var pad = kok.querySelector('.ro-pad');
    var oncekiBtn = kok.querySelector('.ro-onceki');
    var sonrakiBtn = kok.querySelector('.ro-sonraki');
    var sonucEl = kok.querySelector('.ro-sonuc');
    var profilEl = kok.querySelector('.ro-profil');
    var profilAciklamaEl = kok.querySelector('.ro-profil-aciklama');
    var pencereOzetEl = kok.querySelector('.ro-pencere-ozet');
    var ozelMsEl = kok.querySelector('.ro-ozel-ms');
    var kolayMsEl = kok.querySelector('.ro-ms-kolay');
    var ortaMsEl = kok.querySelector('.ro-ms-orta');
    var zorMsEl = kok.querySelector('.ro-ms-zor');
    var telafiMsEl = kok.querySelector('.ro-telafi-ms');
    var elleUygulaBtn = kok.querySelector('.ro-elle-uygula');
    var kalibreBtn = kok.querySelector('.ro-kalibre');
    var kalSifirlaBtn = kok.querySelector('.ro-kal-sifirla');
    var kalRozetEl = kok.querySelector('.ro-kalibrasyon-rozet');
    var kalSahneEl = kok.querySelector('.ro-kalibrasyon-sahne');
    var kalMetinEl = kok.querySelector('.ro-kalibrasyon-metin');
    var kalIlerlemeEl = kok.querySelector('.ro-kalibrasyon-ilerleme');
    var kalPad = kok.querySelector('.ro-kal-pad');
    var kalDurdurBtn = kok.querySelector('.ro-kal-durdur');

    function mevcut() { return katalog[indeks]; }

    function ogrenmeDurumunuKaydet() {
      try { localStorage.setItem(ustalikAnahtari, JSON.stringify(ogrenmeDurumu)); } catch (e) {}
    }

    function ogrenmeArayuzunuCiz() {
      var ozet = ogrenme.ozet(katalog, ogrenmeDurumu, Date.now());
      var zayiflar = ogrenme.zayifTipler(ogrenmeDurumu, 3);
      var oneri = ogrenme.sonrakiniSec(katalog, ogrenmeDurumu, mevcut().id, Date.now());
      var mevcutKayit = ogrenmeDurumu.ornekler[mevcut().id];

      akilliToggleBtn.textContent = akilliMod ? 'Açık' : 'Sıralı mod';
      akilliToggleBtn.setAttribute('aria-pressed', akilliMod ? 'true' : 'false');
      akilliToggleBtn.classList.toggle('ro-akilli-kapali', !akilliMod);
      kursUstalikEl.textContent = ozet.kursYuzdesi + '%';
      calisilanUstalikEl.textContent = ozet.calisilanUstaligi + '%';
      ustalasilanEl.textContent = ozet.ustalasilan + '/' + ozet.toplam;
      tekrarSayisiEl.textContent = String(ozet.tekrar);
      basariSerisiEl.textContent = String(ozet.basariSerisi);
      ogrenmeIlerlemeEl.style.width = ozet.kursYuzdesi + '%';

      zayifRitimlerEl.replaceChildren();
      if (!zayiflar.length) {
        var bos = document.createElement('span');
        bos.className = 'ro-zayif-bos';
        bos.textContent = ozet.denenen
          ? 'Belirgin bir zayıf ritim türü yok.'
          : 'İlk okumadan sonra zayıf ritim türlerin burada görünecek.';
        zayifRitimlerEl.appendChild(bos);
      } else {
        var baslik = document.createElement('small');
        baslik.textContent = 'Çalışılacak ritimler:';
        zayifRitimlerEl.appendChild(baslik);
        zayiflar.forEach(function (zayif) {
          var rozet = document.createElement('span');
          rozet.className = 'ro-zayif-rozet';
          rozet.textContent = (TIPLER[zayif.kod] ? TIPLER[zayif.kod].ad : zayif.kod)
            + ' · %' + zayif.ustalik;
          zayifRitimlerEl.appendChild(rozet);
        });
      }

      if (oneri) {
        var onerilen = katalog[oneri.indeks];
        oneriEl.textContent = 'Sıradaki öneri: ' + (onerilen.dersNo + 1) + '. ders, '
          + onerilen.id.split('-').pop() + '. örnek · ' + oneri.neden;
        onerilenBtn.dataset.indeks = String(oneri.indeks);
        onerilenBtn.hidden = false;
      } else {
        oneriEl.textContent = 'Bu seviyedeki çalışma planı tamamlandı.';
        onerilenBtn.hidden = true;
      }

      var durumMetni = 'Henüz ölçülmedi';
      if (mevcutKayit && mevcutKayit.deneme) {
        durumMetni = mevcutKayit.deneme >= 2 && mevcutKayit.ustalik >= 85
          ? 'Ustalaşıldı · %' + mevcutKayit.ustalik
          : 'Gelişiyor · %' + mevcutKayit.ustalik;
      }
      kok.querySelector('.ro-ogrenme').dataset.mevcutDurum = durumMetni;
    }

    function pencereBilgisi() {
      if (degerlendirmeAyari.profil === 'ozel') {
        var ozelPencere = sinirla(degerlendirmeAyari.ozel[seviye], 60, 300);
        return {
          profil: 'Özel',
          pencereMs: Math.round(ozelPencere),
          tamMs: Math.round(sinirla(ozelPencere * 0.3, 25, 70))
        };
      }
      var profil = DEGERLENDIRME_PROFILLERI[degerlendirmeAyari.profil]
        || DEGERLENDIRME_PROFILLERI.dengeli;
      return {
        profil: profil.ad,
        pencereMs: profil.pencere[seviye],
        tamMs: profil.tam[seviye]
      };
    }

    function tarayiciGecikmesiMs() {
      if (!ctx) { return 0; }
      var taban = Number(ctx.baseLatency) || 0;
      var cikisGecikmesi = Number(ctx.outputLatency) || 0;
      return Math.max(0, Math.round((taban + cikisGecikmesi) * 1000));
    }

    function degerlendirmeArayuzunuGuncelle() {
      var bilgi = pencereBilgisi();
      var kal = degerlendirmeAyari.kalibrasyon;
      profilEl.value = degerlendirmeAyari.profil;
      kolayMsEl.value = Math.round(degerlendirmeAyari.ozel[1]);
      ortaMsEl.value = Math.round(degerlendirmeAyari.ozel[2]);
      zorMsEl.value = Math.round(degerlendirmeAyari.ozel[3]);
      telafiMsEl.value = Math.round(kal.telafiMs);
      ozelMsEl.hidden = degerlendirmeAyari.profil !== 'ozel';
      pencereOzetEl.textContent = bilgi.profil + ' · doğru ±' + bilgi.pencereMs
        + ' ms · tam puan ±' + bilgi.tamMs + ' ms';

      if (degerlendirmeAyari.profil === 'ozel') {
        profilAciklamaEl.textContent = 'Her seviye için doğru sayılma penceresini ayrı belirleyebilirsin. '
          + 'Tam puan bölgesi seçilen pencerenin yaklaşık %30’udur.';
      } else {
        profilAciklamaEl.textContent = DEGERLENDIRME_PROFILLERI[degerlendirmeAyari.profil].aciklama;
      }

      if (!kal.yapildi) {
        kalRozetEl.className = 'ro-kalibrasyon-rozet ro-kalibrasyon-eksik';
        kalRozetEl.textContent = 'Kalibrasyon gerekli';
      } else {
        kalRozetEl.className = 'ro-kalibrasyon-rozet ro-kalibrasyon-hazir';
        kalRozetEl.textContent = (kal.telafiMs > 0 ? '+' : '') + Math.round(kal.telafiMs)
          + ' ms telafi' + (kal.dagilimMs ? ' · ±' + Math.round(kal.dagilimMs) + ' ms dağılım' : '');
      }
    }

    function degerlendirmeKontrolleriniKilitle(kilitli) {
      [profilEl, kolayMsEl, ortaMsEl, zorMsEl, telafiMsEl, elleUygulaBtn,
        kalibreBtn, kalSifirlaBtn].forEach(function (el) {
        if (el) { el.disabled = !!kilitli; }
      });
    }

    function heceler(tip, vurusNo) {
      return tip.bolum === 3
        ? [String(vurusNo), 'le', 'me']
        : [String(vurusNo), 'e', 've', 'a'];
    }

    function offsetler(tip) {
      return tip.bolum === 3 ? [0, 1 / 3, 2 / 3] : [0, 0.25, 0.5, 0.75];
    }

    function ayni(a, b) { return Math.abs(a - b) < 0.001; }

    function abcOlustur(ornek, dar) {
      var olculer = [[], []];
      abcNotaSirasi = [];
      for (var idx = 0; idx < VURUS_ADEDI; idx++) {
        var kod = ornek.kodlar[idx];
        var tip = TIPLER[kod];
        if (tip.abc) { olculer[Math.floor(idx / 4)].push(tip.abc); }

        /*
         * Bağın ikinci nota başı portede görünür fakat yeni bir ses başlangıcı
         * değildir. Haritada null bırakarak sonraki gerçek onsetin SVG notasıyla
         * doğru eşleşmesini koruruz.
         */
        if (kod === 'tie_end') { abcNotaSirasi.push(null); }
        tip.onset.forEach(function (_ofset, onsetIdx) {
          abcNotaSirasi.push({ hucre: idx, onset: onsetIdx });
        });
      }
      return [
        'X:1',
        'M:4/4',
        'L:1/8',
        'Q:1/4=' + bpm,
        '%%stretchlast 1',
        'K:C clef=perc',
        'V:ritim stem=up',
        dar
          ? olculer[0].join(' ') + ' |\n' + olculer[1].join(' ') + ' |]'
          : olculer[0].join(' ') + ' | ' + olculer[1].join(' ') + ' |]'
      ].join('\n');
    }

    function porteyiCiz() {
      porteCizimNo++;
      var ornek = mevcut();
      notaHaritasi = Array.from({ length: VURUS_ADEDI }, function () { return []; });
      if (!window.ABCJS || typeof window.ABCJS.renderAbc !== 'function') {
        portaEl.classList.add('ro-porteli-yedek');
        portaEl.textContent = 'Porteli nota görünümü yüklenemedi; sayım tablosu kullanılabilir.';
        return;
      }

      portaEl.classList.remove('ro-porteli-yedek');
      delete portaEl.dataset.renderHata;
      portaEl.replaceChildren();
      var dar = kok.clientWidth < 560;
      try {
        var abcAyar = {
          add_classes: true,
          ariaLabel: '4/4 ölçüde iki ölçülük ' + SEVIYE_ADI[seviye] + ' ritim alıştırması',
          staffwidth: dar ? 440 : 720,
          scale: dar ? 0.9 : 1,
          foregroundColor: '#f5f5f4',
          paddingtop: 4,
          paddingbottom: 2,
          paddingleft: 8,
          paddingright: 8
        };
        window.ABCJS.renderAbc(portaId, abcOlustur(ornek, dar), abcAyar);
      } catch (hata) {
        portaEl.classList.add('ro-porteli-yedek');
        portaEl.textContent = 'Nota çizimi tamamlanamadı; sayım tablosuyla çalışmaya devam edebilirsin.';
        portaEl.dataset.renderHata = String(hata && hata.message ? hata.message : hata);
      }

      var notaElemanlari = Array.prototype.slice.call(portaEl.querySelectorAll('.abcjs-note'));
      abcNotaSirasi.forEach(function (ref, i) {
        if (ref && notaElemanlari[i]) {
          notaHaritasi[ref.hucre][ref.onset] = notaElemanlari[i];
        }
      });
      portaEl.dataset.notaSayisi = String(notaElemanlari.length);
      portaEl.dataset.beklenenNotaSayisi = String(abcNotaSirasi.length);
      portaEl.dataset.cizimNo = String(porteCizimNo);
    }

    function ciz() {
      var ornek = mevcut();
      desenEl.replaceChildren();
      heceHaritasi = [];
      sonucEl.hidden = true;
      sonucEl.replaceChildren();
      seviyeEl.textContent = SEVIYE_ADI[seviye];
      dersEl.textContent = (ornek.dersNo + 1) + '/8 · ' + ornek.ders;
      cizMeta();
      var pencere = pencereBilgisi();
      aciklamaEl.textContent = '4/4 · 2 ölçü · ' + bpm + ' BPM · ' + pencere.profil
        + ' ±' + pencere.pencereMs + ' ms · Sayım: 1-e-ve-a / üçlemede 1-le-me';

      for (var olcu = 0; olcu < 2; olcu++) {
        var olcuEl = document.createElement('div');
        olcuEl.className = 'ro-olcu';
        olcuEl.setAttribute('aria-label', (olcu + 1) + '. ölçü');
        for (var v = 0; v < 4; v++) {
          var idx = olcu * 4 + v;
          var tip = TIPLER[ornek.kodlar[idx]];
          var hucre = document.createElement('div');
          hucre.className = 'ro-hucre ro-sayim-hucre' + (!tip.onset.length ? ' ro-es' : '') + (tip.bag ? ' ro-bag' : '');
          hucre.setAttribute('aria-label', (v + 1) + '. vuruş: ' + tip.ad);

          var sayim = document.createElement('span');
          sayim.className = 'ro-sayim-grid ro-bolum-' + tip.bolum;
          var etiketler = heceler(tip, v + 1);
          var konumlar = offsetler(tip);
          heceHaritasi[idx] = [];
          konumlar.forEach(function (ofset, hIdx) {
            var hece = document.createElement('span');
            hece.className = 'ro-hece' + (tip.onset.some(function (o) { return ayni(o, ofset); }) ? ' ro-nota-var' : '');
            hece.textContent = etiketler[hIdx];
            sayim.appendChild(hece);
            var onsetIdx = tip.onset.findIndex(function (o) { return ayni(o, ofset); });
            if (onsetIdx >= 0) { heceHaritasi[idx][onsetIdx] = hece; }
          });
          hucre.appendChild(sayim);
          olcuEl.appendChild(hucre);
        }
        desenEl.appendChild(olcuEl);
      }
      porteyiCiz();
    }

    function audioHazirla() {
      if (!ctx) {
        var AudioCtor = window.AudioContext || window.webkitAudioContext;
        try { ctx = new AudioCtor({ latencyHint: 'interactive' }); }
        catch (e) { ctx = new AudioCtor(); }
      }
      if (ctx.state === 'suspended') { ctx.resume(); }
      if (!cikis) {
        cikis = ctx.createGain();
        cikis.gain.value = 0.82;
        cikis.connect(ctx.destination);
      }
    }

    function klik(zaman, aksan, kisik) {
      var o = ctx.createOscillator();
      var g = ctx.createGain();
      o.type = 'sine';
      o.frequency.setValueAtTime(aksan ? 1100 : 760, zaman);
      o.frequency.exponentialRampToValueAtTime(aksan ? 700 : 500, zaman + 0.035);
      g.gain.setValueAtTime((aksan ? 0.5 : 0.28) * (kisik ? 0.55 : 1), zaman);
      g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.055);
      o.connect(g).connect(cikis);
      o.start(zaman);
      o.stop(zaman + 0.06);
    }

    function ritimSesi(zaman, aksan) {
      var o = ctx.createOscillator();
      var g = ctx.createGain();
      o.type = 'triangle';
      o.frequency.setValueAtTime(aksan ? 1320 : 980, zaman);
      o.frequency.exponentialRampToValueAtTime(aksan ? 880 : 620, zaman + 0.045);
      g.gain.setValueAtTime(aksan ? 0.58 : 0.43, zaman);
      g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.075);
      o.connect(g).connect(cikis);
      o.start(zaman);
      o.stop(zaman + 0.08);
    }

    function zamanla(fn, zaman) {
      var buNesil = nesil;
      var id = setTimeout(function () {
        if (buNesil === nesil) { fn(); }
      }, Math.max(0, (zaman - ctx.currentTime) * 1000));
      zamanlayicilar.push(id);
    }

    function durumZamanla(metin, zaman) {
      zamanla(function () { durumEl.textContent = metin; }, zaman);
    }

    function isikZamanla(eller, zaman, sinif) {
      eller = (Array.isArray(eller) ? eller : [eller]).filter(Boolean);
      if (!eller.length) { return; }
      zamanla(function () {
        kok.querySelectorAll('.ro-caliyor').forEach(function (x) {
          x.classList.remove('ro-caliyor', 'ro-demo-aktif');
        });
        eller.forEach(function (el) { el.classList.add('ro-caliyor', sinif); });
        setTimeout(function () {
          eller.forEach(function (el) { el.classList.remove('ro-caliyor', sinif); });
        }, Math.max(80, spb * 170));
      }, zaman);
    }

    function kontrolleriKilitle(kilitli) {
      dinleBtn.disabled = kilitli;
      baslatBtn.disabled = kilitli;
      oncekiBtn.disabled = kilitli;
      sonrakiBtn.disabled = kilitli;
      akilliToggleBtn.disabled = kilitli;
      onerilenBtn.disabled = kilitli;
      iptalBtn.hidden = !kilitli;
      pad.hidden = aktifTur !== 'uygulama';
      degerlendirmeKontrolleriniKilitle(kilitli);
    }

    function baslangicSayaciniGoster(deger, aciklama, simdi) {
      baslangicSayacEl.hidden = false;
      baslangicSayacEl.classList.toggle('ro-simdi', !!simdi);
      baslangicSayacEl.querySelector('small').textContent = simdi ? 'İlk nota' : 'Başlangıca';
      baslangicSayacEl.querySelector('strong').textContent = deger;
      baslangicSayacEl.querySelector('span').textContent = aciklama;
    }

    function baslangicSayaciniGizle() {
      baslangicSayacEl.hidden = true;
      baslangicSayacEl.classList.remove('ro-simdi');
    }

    function zamanlayicilariTemizle() {
      zamanlayicilar.forEach(clearTimeout);
      zamanlayicilar = [];
    }

    function sesiKes() {
      if (!ctx || !cikis) { return; }
      try { cikis.disconnect(); } catch (e) { /* zaten kopuk */ }
      cikis = ctx.createGain();
      cikis.gain.value = 0.82;
      cikis.connect(ctx.destination);
    }

    function durdur(metin) {
      nesil++;
      aktifTur = '';
      zamanlayicilariTemizle();
      sesiKes();
      kontrolleriKilitle(false);
      pad.classList.remove('ro-pad-hazir');
      baslangicSayaciniGizle();
      kalSahneEl.hidden = true;
      kalibrasyonBeklenen = [];
      kalibrasyonTaplar = [];
      kalibrasyonVurusAcik = false;
      kok.querySelectorAll('.ro-caliyor, .ro-demo-aktif').forEach(function (x) {
        x.classList.remove('ro-caliyor', 'ro-demo-aktif');
      });
      if (metin) { durumEl.textContent = metin; }
    }

    function kalibrasyonTap(olay) {
      if (aktifTur !== 'kalibrasyon' || !kalibrasyonVurusAcik) { return; }
      kalibrasyonTaplar.push(zamanlama.olayZamani(ctx, olay));
      kalPad.classList.add('vurdum');
      setTimeout(function () { kalPad.classList.remove('vurdum'); }, 80);
      kalIlerlemeEl.textContent = Math.min(kalibrasyonTaplar.length, 12) + '/12';
    }

    function kalibrasyonBitir() {
      if (aktifTur !== 'kalibrasyon') { return; }
      aktifTur = '';
      kalibrasyonVurusAcik = false;
      kontrolleriKilitle(false);
      kalSahneEl.hidden = true;
      var kalibrasyonSonucu = zamanlama.kalibrasyonHesapla(
        kalibrasyonBeklenen, kalibrasyonTaplar
      );
      if (!kalibrasyonSonucu.basarili) {
        durumEl.textContent = 'Kalibrasyon tamamlanamadı: 12 klikten en az 8’inde vurmalısın. Tekrar dene.';
        degerlendirmeArayuzunuGuncelle();
        return;
      }

      var telafi = kalibrasyonSonucu.telafiMs;
      var dagilim = kalibrasyonSonucu.dagilimMs;
      degerlendirmeAyari.kalibrasyon = {
        yapildi: true,
        telafiMs: telafi,
        dagilimMs: dagilim,
        ornek: kalibrasyonSonucu.ornek,
        tarih: new Date().toISOString(),
        tur: 'otomatik',
        cihazImzasi: zamanlama.cihazImzasi(ctx)
      };
      degerlendirmeAyariYaz(degerlendirmeAyari);
      degerlendirmeArayuzunuGuncelle();

      var cihazMs = tarayiciGecikmesiMs();
      durumEl.textContent = 'Kalibrasyon tamamlandı: '
        + (telafi >= 0 ? '+' : '') + Math.round(telafi) + ' ms telafi · '
        + 'dağılım ±' + Math.round(dagilim) + ' ms'
        + (cihazMs ? ' · tarayıcı gecikme tahmini ' + cihazMs + ' ms' : '')
        + (dagilim > 65 ? '. Vuruşlar dağınık; daha güvenilir sonuç için tekrar önerilir.' : '.');
    }

    function kalibrasyonBaslat() {
      if (aktifTur) { return; }
      audioHazirla();
      durdur();
      aktifTur = 'kalibrasyon';
      kontrolleriKilitle(true);
      iptalBtn.hidden = true;
      kalSahneEl.hidden = false;
      kalMetinEl.textContent = 'Hazırlık · dört vuruş dinle';
      kalIlerlemeEl.textContent = '0/12';
      kalibrasyonBeklenen = [];
      kalibrasyonTaplar = [];
      kalibrasyonVurusAcik = false;

      var kalSpb = 60 / 72;
      var t0 = ctx.currentTime + 0.35;
      baslangicSayaciniGoster('4', 'vuruş sonra kalibre et', false);
      for (var h = 0; h < 4; h++) {
        klik(t0 + h * kalSpb, h === 0, false);
        (function (kalan, zaman) {
          zamanla(function () {
            kalMetinEl.textContent = 'Hazırlık · ' + kalan;
            baslangicSayaciniGoster(String(kalan), 'vuruş sonra kalibre et', false);
          }, zaman);
        })(4 - h, t0 + h * kalSpb);
      }

      var baslangic = t0 + 4 * kalSpb;
      for (var i = 0; i < 12; i++) {
        var hedef = baslangic + i * kalSpb;
        kalibrasyonBeklenen.push(hedef);
        klik(hedef, i % 4 === 0, false);
      }
      zamanla(function () {
        kalibrasyonVurusAcik = true;
      }, baslangic - zamanlama.BASLANGIC_KAPISI_MS / 1000);
      zamanla(function () {
        kalMetinEl.textContent = 'ŞİMDİ · duyduğun her klikte vur';
        baslangicSayaciniGoster('ŞİMDİ', 'duyduğun her klikte vur', true);
      }, baslangic);
      zamanla(baslangicSayaciniGizle, baslangic + Math.max(0.45, kalSpb * 0.72));
      zamanla(kalibrasyonBitir, baslangic + 12 * kalSpb + 0.25);
      durumEl.textContent = 'Kalibrasyon başladı. Ekrana bakmadan, duyduğun 12 klike eşlik et.';
    }

    function sayimPlanla(t0, eylem) {
      baslangicSayaciniGoster('4', 'vuruş sonra ' + eylem, false);
      for (var i = 0; i < 4; i++) {
        klik(t0 + i * spb, i === 0, false);
        (function (kalan, zaman) {
          zamanla(function () {
            baslangicSayaciniGoster(String(kalan), 'vuruş sonra ' + eylem, false);
            durumEl.textContent = 'Hazırlık · ' + kalan + ' vuruş sonra ' + eylem + '.';
          }, zaman);
        })(4 - i, t0 + i * spb);
      }
      zamanla(function () {
        baslangicSayaciniGoster('ŞİMDİ', eylem, true);
      }, t0 + 4 * spb);
      zamanla(baslangicSayaciniGizle, t0 + 4 * spb + Math.max(0.45, spb * 0.72));
    }

    function ritimPlanla(baslangic, demo) {
      var ornek = mevcut();
      for (var idx = 0; idx < VURUS_ADEDI; idx++) {
        var tip = TIPLER[ornek.kodlar[idx]];
        var beatTime = baslangic + idx * spb;
        if (demo) {
          klik(beatTime, idx % 4 === 0, true);
        } else if (rehber === 'tam' || (rehber === 'olcu' && idx % 4 === 0)) {
          klik(beatTime, idx % 4 === 0, true);
        }
        tip.onset.forEach(function (ofset, onsetIdx) {
          var zaman = beatTime + ofset * spb;
          var hece = heceHaritasi[idx][onsetIdx];
          var nota = notaHaritasi[idx] && notaHaritasi[idx][onsetIdx];
          if (demo) {
            ritimSesi(zaman, idx % 4 === 0 && onsetIdx === 0);
            isikZamanla([nota, hece], zaman, 'ro-demo-aktif');
          } else {
            beklenen.push({
              zaman: zaman,
              el: nota,
              hece: hece,
              hucre: idx,
              onset: onsetIdx,
              ilk: beklenen.length === 0
            });
          }
        });
      }
    }

    function dinle() {
      if (aktifTur) { return; }
      audioHazirla();
      durdur();
      aktifTur = 'dinleme';
      kontrolleriKilitle(true);
      sonucEl.hidden = true;
      var t0 = ctx.currentTime + 0.3;
      durumEl.textContent = 'Hazır ol · geri sayımdan sonra ritmi dinle.';
      sayimPlanla(t0, 'dinlemeye başla');
      var bas = t0 + 4 * spb;
      durumZamanla('🔊 Yazılan ritmi dinle ve sayım hecelerini izle.', bas);
      ritimPlanla(bas, true);
      var bitis = bas + VURUS_ADEDI * spb + 0.2;
      zamanla(function () {
        aktifTur = '';
        kontrolleriKilitle(false);
        durumEl.textContent = 'Dinleme tamamlandı. Şimdi aynı ritmi okuyabilirsin.';
      }, bitis);
    }

    function uygulamaBaslat() {
      if (aktifTur) { return; }
      if (!degerlendirmeAyari.kalibrasyon.yapildi) {
        durumEl.textContent = 'Puanlı okumadan önce otomatik kalibrasyon yap veya elle bir telafi değeri uygula.';
        kalRozetEl.classList.add('ro-kalibrasyon-dikkat');
        kalibreBtn.focus();
        return;
      }
      audioHazirla();
      durdur();
      aktifTur = 'hazirlik';
      kontrolleriKilitle(true);
      pad.hidden = false;
      pad.classList.add('ro-pad-hazir');
      sonucEl.hidden = true;
      taplar = [];
      beklenen = [];
      kok.querySelectorAll('.ro-dogru, .ro-kacirildi').forEach(function (x) {
        x.classList.remove('ro-dogru', 'ro-kacirildi');
      });
      var t0 = ctx.currentTime + 0.3;
      durumEl.textContent = 'Hazır ol · geri sayım bitince ilk notayla birlikte vur.';
      sayimPlanla(t0, 'vurmaya başla');
      var bas = t0 + 4 * spb;
      durumZamanla(rehber === 'sessiz'
        ? '🎼 Şimdi! İçinden say ve notayı çal.'
        : '🥁 Şimdi! Kılavuzu dinleyerek notayı çal.', bas);
      zamanla(function () {
        aktifTur = 'uygulama';
      }, bas - zamanlama.BASLANGIC_KAPISI_MS / 1000);
      zamanla(function () {
        pad.hidden = false;
        pad.classList.remove('ro-pad-hazir');
      }, bas);
      ritimPlanla(bas, false);
      var bitis = bas + VURUS_ADEDI * spb + spb * 0.35;
      zamanla(uygulamaBitir, bitis);
    }

    function tap(olay) {
      if (aktifTur !== 'uygulama') { return; }
      taplar.push(zamanlama.olayZamani(ctx, olay));
      kok.classList.add('ro-vuruldu');
      setTimeout(function () { kok.classList.remove('ro-vuruldu'); }, 70);
    }

    function eslestir(tolerans) {
      var sonuc = zamanlama.eslestir(taplar, beklenen, {
        telafiMs: degerlendirmeAyari.kalibrasyon.telafiMs,
        esikSn: function (hedef) {
          return tolerans + (hedef.ilk ? ILK_VURUS_EK_MS / 1000 : 0);
        }
      });
      var kullanilanB = {};
      var kullanilanT = {};
      var eslesen = sonuc.eslesenler.map(function (a) {
        kullanilanB[a.hedefIdx] = true;
        kullanilanT[a.tapIdx] = true;
        return {
          beklenen: a.hedef,
          sapmaMs: a.sapmaMs,
          hamSapmaMs: a.hamSapmaMs
        };
      });
      return { eslesen: eslesen, kullanilanB: kullanilanB, kullanilanT: kullanilanT };
    }

    function uygulamaBitir() {
      if (aktifTur !== 'uygulama') { return; }
      aktifTur = '';
      kontrolleriKilitle(false);
      pad.classList.remove('ro-pad-hazir');
      baslangicSayaciniGizle();

      var pencere = pencereBilgisi();
      var tolerans = pencere.pencereMs / 1000;
      var es = eslestir(tolerans);
      var sapmalar = es.eslesen.map(function (x) { return x.sapmaMs; });
      var isabet = es.eslesen.length;
      var ekstra = Math.max(0, taplar.length - isabet);
      var kacirilan = Math.max(0, beklenen.length - isabet);
      var ortMutlak = sapmalar.length
        ? sapmalar.reduce(function (t, x) { return t + Math.abs(x); }, 0) / sapmalar.length
        : tolerans * 1000;
      var ortSapma = sapmalar.length ? sapmalar.reduce(function (t, x) { return t + x; }, 0) / sapmalar.length : 0;
      // Kararlılık: sabit kaymadan arınmış dağılım — dönem karşılaştırması bundan okunur
      var sapmaSd = zamanlama.standartSapma(sapmalar);
      var kapsama = isabet / Math.max(1, beklenen.length);
      var dogruluk = isabet / Math.max(1, taplar.length);
      var zamanKaliteleri = sapmalar.map(function (sapma) {
        var mutlak = Math.abs(sapma);
        if (mutlak <= pencere.tamMs) { return 1; }
        return Math.max(0, 1 - (mutlak - pencere.tamMs) / Math.max(1, pencere.pencereMs - pencere.tamMs));
      });
      var zamanSkoru = zamanKaliteleri.length
        ? zamanKaliteleri.reduce(function (t, x) { return t + x; }, 0) / zamanKaliteleri.length
        : 0;
      /*
       * Doğru pencerenin içine giren bütün vuruşlar temel olarak değer taşır.
       * Zaman kalitesi son %30'u belirler; böylece bütün notaları doğru çalan
       * öğrenci yalnız küçük sapmalar yüzünden başarısız sayılmaz.
       */
      var skor = Math.max(0, Math.min(100,
        Math.round(100 * kapsama * dogruluk * (0.7 + 0.3 * zamanSkoru))));

      var tipSonuclari = {};
      beklenen.forEach(function (b, i) {
        if (b.el) { b.el.classList.add(es.kullanilanB[i] ? 'ro-dogru' : 'ro-kacirildi'); }
        if (b.hece) { b.hece.classList.add(es.kullanilanB[i] ? 'ro-dogru' : 'ro-kacirildi'); }
        var kod = mevcut().kodlar[b.hucre];
        if (!tipSonuclari[kod]) { tipSonuclari[kod] = { toplam: 0, dogru: 0 }; }
        tipSonuclari[kod].toplam++;
        if (es.kullanilanB[i]) { tipSonuclari[kod].dogru++; }
      });

      ogrenmeDurumu = ogrenme.denemeyiIsle(ogrenmeDurumu, {
        id: mevcut().id,
        skor: skor,
        ortMutlakMs: ortMutlak,
        kacirilan: kacirilan,
        ekstra: ekstra,
        tipSonuclari: tipSonuclari
      }, Date.now());
      ogrenmeDurumunuKaydet();
      var ustalikKaydi = ogrenmeDurumu.ornekler[mevcut().id];

      if (skor >= 70 && tamamlanan.indexOf(mevcut().id) === -1) {
        tamamlanan.push(mevcut().id);
        try { localStorage.setItem(tamamAnahtari, JSON.stringify(tamamlanan.slice(-katalog.length))); } catch (e) {}
      }

      var yon = ortSapma > 12 ? 'geç' : (ortSapma < -12 ? 'erken' : 'dengeli');
      sonucEl.hidden = false;
      sonucEl.innerHTML =
        '<div class="ro-skor">' + skor + '<small>/100</small></div>' +
        '<div class="ro-metrikler">' +
        '<span><b>' + isabet + '/' + beklenen.length + '</b> doğru</span>' +
        '<span><b>' + kacirilan + '</b> kaçırılan</span>' +
        '<span><b>' + ekstra + '</b> fazla</span>' +
        '<span><b>' + Math.round(ortMutlak) + ' ms</b> ort. sapma</span>' +
        '<span><b>' + yon + '</b> eğilim</span>' +
        '<span><b>±' + pencere.pencereMs + ' ms</b> doğru penceresi</span>' +
        '<span><b>+' + ILK_VURUS_EK_MS + ' ms</b> ilk vuruş payı</span>' +
        '<span><b>' + (degerlendirmeAyari.kalibrasyon.telafiMs > 0 ? '+' : '')
          + Math.round(degerlendirmeAyari.kalibrasyon.telafiMs) + ' ms</b> telafi</span>' +
        '</div>' +
        '<div class="ro-ustalik-sonuc"><strong>Örnek ustalığı %' + ustalikKaydi.ustalik + '</strong><span>'
          + (skor < 70
            ? 'Bu örnek iki çalışma sonra tekrar kuyruğuna girecek.'
            : skor < 85
              ? 'Doğruluğu kalıcılaştırmak için bu örnek yeniden gösterilecek.'
              : ustalikKaydi.deneme < 2
                ? 'Ustalık için farklı bir denemede bir temiz okuma daha gerekli.'
                : 'Bu örnekte kalıcı ustalık oluşuyor.')
          + '</span></div>';
      durumEl.textContent = skor >= 85
        ? 'Çok temiz okuma. Bir sonraki örneğe geçebilirsin.'
        : skor >= 70
          ? 'Ritim oturuyor. Kaçırılan kırmızı heceleri tekrar çalış.'
          : 'Önce “Ritmi Dinle” ile onsetleri takip et, sonra daha yavaş tempoda yeniden dene.';

      cizMeta();
      if (typeof opts.onBitti === 'function') {
        opts.onBitti({
          skor: skor,
          seviye: seviye,
          seviyeAdi: SEVIYE_ADI[seviye],
          bpm: bpm,
          rehber: rehber,
          ders: mevcut().ders,
          ornek: indeks + 1,
          toplamOrnek: katalog.length,
          katalogId: mevcut().id,
          beklenen: beklenen.length,
          isabet: isabet,
          kacirilan: kacirilan,
          ekstra: ekstra,
          ortMutlakMs: Math.round(ortMutlak),
          ortSapmaMs: Math.round(ortSapma),
          sapmaSdMs: Math.round(sapmaSd),
          degerlendirmeProfili: degerlendirmeAyari.profil,
          dogruPenceresiMs: pencere.pencereMs,
          tamPuanPenceresiMs: pencere.tamMs,
          ilkVurusEkMs: ILK_VURUS_EK_MS,
          telafiMs: Math.round(degerlendirmeAyari.kalibrasyon.telafiMs),
          kalibrasyonDagilimMs: Math.round(degerlendirmeAyari.kalibrasyon.dagilimMs),
          zamanlamaSurumu: zamanlama.SURUM,
          ogrenmeSurumu: ogrenme.SURUM,
          ustalik: ustalikKaydi.ustalik,
          denemeSayisi: ustalikKaydi.deneme,
          desen: mevcut().kodlar.join(',')
        });
      }
    }

    function cizMeta() {
      var tamamAdet = tamamlanan.filter(function (id) { return katalog.some(function (o) { return o.id === id; }); }).length;
      var kayit = ogrenmeDurumu.ornekler[mevcut().id];
      var ustalik = kayit && kayit.deneme ? ' · ustalık %' + kayit.ustalik : ' · yeni';
      metaEl.textContent = 'Örnek ' + (indeks + 1) + '/' + katalog.length + ' · tamamlanan ' + tamamAdet + ustalik;
      ogrenmeArayuzunuCiz();
    }

    function ornekSec(yeniIndeks, mesaj) {
      durdur();
      indeks = (yeniIndeks + katalog.length) % katalog.length;
      try { localStorage.setItem(indeksAnahtari, JSON.stringify(indeks)); } catch (e) {}
      ciz();
      durumEl.textContent = mesaj || 'Yeni ritim hazır. Önce dinleyebilir veya doğrudan okuyabilirsin.';
    }

    function ornekDegistir(yon) {
      if (yon > 0 && akilliMod) {
        var oneri = ogrenme.sonrakiniSec(katalog, ogrenmeDurumu, mevcut().id, Date.now());
        if (oneri) {
          ornekSec(oneri.indeks, 'Akıllı çalışma seçti: ' + oneri.neden + '.');
          return;
        }
      }
      ornekSec(indeks + yon);
    }

    function ozelPencereleriKaydet() {
      degerlendirmeAyari.ozel = {
        1: sinirla(kolayMsEl.value || 180, 60, 300),
        2: sinirla(ortaMsEl.value || 160, 60, 300),
        3: sinirla(zorMsEl.value || 140, 60, 300)
      };
      degerlendirmeAyariYaz(degerlendirmeAyari);
      degerlendirmeArayuzunuGuncelle();
      ciz();
    }

    profilEl.addEventListener('change', function () {
      degerlendirmeAyari.profil = this.value;
      degerlendirmeAyariYaz(degerlendirmeAyari);
      degerlendirmeArayuzunuGuncelle();
      ciz();
    });
    [kolayMsEl, ortaMsEl, zorMsEl].forEach(function (el) {
      el.addEventListener('input', ozelPencereleriKaydet);
    });
    elleUygulaBtn.addEventListener('click', function () {
      var telafi = sinirla(telafiMsEl.value || 0, -400, 400);
      degerlendirmeAyari.kalibrasyon = {
        yapildi: true,
        telafiMs: Math.round(telafi),
        dagilimMs: 0,
        ornek: 0,
        tarih: new Date().toISOString(),
        tur: 'elle',
        cihazImzasi: ctx ? zamanlama.cihazImzasi(ctx) : ''
      };
      degerlendirmeAyariYaz(degerlendirmeAyari);
      degerlendirmeArayuzunuGuncelle();
      durumEl.textContent = 'Elle kalibrasyon uygulandı: ' + (telafi > 0 ? '+' : '') + Math.round(telafi)
        + ' ms. Pozitif değer geç vuruşları erkene çeker.';
    });
    kalSifirlaBtn.addEventListener('click', function () {
      degerlendirmeAyari.kalibrasyon = {
        yapildi: false, telafiMs: 0, dagilimMs: 0, ornek: 0,
        tarih: '', tur: '', cihazImzasi: ''
      };
      degerlendirmeAyariYaz(degerlendirmeAyari);
      degerlendirmeArayuzunuGuncelle();
      durumEl.textContent = 'Kalibrasyon sıfırlandı. Puanlı okuma için yeniden kalibre etmelisin.';
    });
    kalibreBtn.addEventListener('click', kalibrasyonBaslat);
    kalDurdurBtn.addEventListener('click', function () { durdur('Kalibrasyon durduruldu.'); });
    kalPad.addEventListener('pointerdown', function (ev) { ev.preventDefault(); kalibrasyonTap(ev); });

    dinleBtn.addEventListener('click', dinle);
    baslatBtn.addEventListener('click', uygulamaBaslat);
    iptalBtn.addEventListener('click', function () { durdur('Çalışma durduruldu.'); });
    pad.addEventListener('pointerdown', function (ev) { ev.preventDefault(); tap(ev); });
    oncekiBtn.addEventListener('click', function () { ornekDegistir(-1); });
    sonrakiBtn.addEventListener('click', function () { ornekDegistir(1); });
    akilliToggleBtn.addEventListener('click', function () {
      akilliMod = !akilliMod;
      try { localStorage.setItem(akilliAnahtari, JSON.stringify(akilliMod)); } catch (e) {}
      ogrenmeArayuzunuCiz();
      durumEl.textContent = akilliMod
        ? 'Akıllı çalışma açık: ileri düğmesi tekrar ve zayıflık planını izler.'
        : 'Sıralı mod açık: ileri düğmesi katalog sırasında ilerler.';
    });
    onerilenBtn.addEventListener('click', function () {
      var hedef = Number(this.dataset.indeks);
      if (Number.isFinite(hedef)) {
        ornekSec(hedef, 'Önerilen çalışma açıldı. Ritmi dinle, say ve ardından oku.');
      }
    });
    kok.addEventListener('pointerdown', function (ev) {
      if (ev.target.closest('button, input, select, a')) { return; }
      if (aktifTur === 'uygulama') { ev.preventDefault(); tap(ev); }
    });

    kok.__roTap = function (olay) {
      if (aktifTur === 'kalibrasyon') { kalibrasyonTap(olay); }
      else { tap(olay); }
    };
    if (kok.__roZamanDinleyici) {
      window.removeEventListener('ritim-zamanlama-guncellendi', kok.__roZamanDinleyici);
    }
    kok.__roZamanDinleyici = function () {
      degerlendirmeAyari = degerlendirmeAyariOku();
      if (!aktifTur) { degerlendirmeArayuzunuGuncelle(); }
    };
    window.addEventListener('ritim-zamanlama-guncellendi', kok.__roZamanDinleyici);
    /*
     * İki AYRI soru, iki ayrı yanıt:
     *  __roAktif  → "bu Space bir VURUŞ olarak sayılmalı mı?" (yalnız vuruş kabul
     *               eden fazlar)
     *  __roMesgul → "Space tuşunun sahibi şu an bu widget mı?" (dinleme ve geri
     *               sayım dahil TÜM etkin fazlar)
     * Bu ayrım şart: dinleme/hazırlık fazında Space vuruş değildir ama metronoma
     * da düşmemelidir — aksi hâlde alıştırmanın üstüne metronom açılıyordu.
     */
    kok.__roAktif = function () { return aktifTur === 'uygulama' || aktifTur === 'kalibrasyon'; };
    kok.__roMesgul = function () { return aktifTur !== ''; };
    kok.__roIptal = function () { durdur(); };
    kok.__roYeni = ornekDegistir;
    kok.__roYenidenCiz = function () {
      if (!aktifTur) { porteyiCiz(); }
    };
    kok.__roKatalogBoyutu = katalog.length;

    degerlendirmeArayuzunuGuncelle();
    ciz();
  }

  return {
    baslat: baslat,
    katalogBoyutu: function (seviye) {
      seviye = [1, 2, 3].indexOf(Number(seviye)) >= 0 ? Number(seviye) : 1;
      return katalogOlustur(seviye).length;
    }
  };
})();
