/* ================================================================
   Poliritim Hesaplama Çekirdeği
   - a:b oranı için iki elin hedef zamanları
   - Bileşik ızgara (LCM) ve sayım/mnemonik haritası
   - El başına değerlendirme + "çekim" (faz kilitlenmesi) ölçüsü
   Tarayıcı + Node.js için ortak, saf ve deterministik API.

   NEDEN AYRI ÇEKİRDEK: Poliritimin zor kısmı zamanlama matematiği ve
   iki elin BİRBİRİNDEN BAĞIMSIZ değerlendirilmesi. Bunu arayüzden
   ayırmak, Node ile birim testi yazılabilmesini sağlıyor (ses ve
   animasyon olmadan doğrulanabilir).
   ================================================================ */
(function (kok, fabrika) {
  'use strict';
  var api = fabrika();
  if (typeof module === 'object' && module.exports) { module.exports = api; }
  if (kok) { kok.PoliritimCekirdegi = api; }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  var SURUM = 1;

  /*
   * Oran kataloğu. 'sag' ve 'sol' bir DÖNGÜ içindeki vuruş sayısıdır.
   * Mnemonik heceler bileşik onset sayısına eşittir (bkz. mnemonikYuvalari):
   * 3:2 → 4 onset → 4 hece, 4:3 → 6 onset → 6 hece.
   * 2:1 gerçek bir poliritim değil (basit katsayı) ama ısınma için ilk adım.
   */
  var ORANLAR = [
    { kod: '2:1', sag: 2, sol: 1, zorluk: 1, ad: 'İki\'ye bir',
      not: 'Isınma: sağ el iki, sol el bir. Poliritim değil, katsayı ilişkisi.',
      mnemonik: ['tak', 'tik'] },
    { kod: '3:2', sag: 3, sol: 2, zorluk: 2, ad: 'Üç\'e iki (hemiola)',
      not: 'Klasik poliritim. Öğrenmeye buradan başlanır.',
      mnemonik: ['hiç', 'zor', 'de', 'ğil'] },
    { kod: '4:3', sag: 4, sol: 3, zorluk: 3, ad: 'Dörde üç',
      not: 'İkinci basamak; bileşik ızgara 12 alt bölüme çıkar.',
      mnemonik: ['bu', 'nu', 'ya', 'pa', 'bi', 'lir'] },
    { kod: '3:4', sag: 3, sol: 4, zorluk: 3, ad: 'Üçe dört (ters)',
      not: 'Aynı oran, eller yer değişir — baskın el değişince zorluk değişir.',
      mnemonik: ['ka', 'rış', 'tır', 'ma', 'sa', 'kın'] },
    { kod: '5:4', sag: 5, sol: 4, zorluk: 4, ad: 'Beşe dört',
      not: 'İleri seviye; bileşik ızgara 20 alt bölüm.',
      mnemonik: ['ya', 'vaş', 'ya', 'vaş', 'a', 'lı', 'şı', 'yor'] },
    { kod: '5:3', sag: 5, sol: 3, zorluk: 5, ad: 'Beşe üç',
      not: 'İleri seviye; ortak vuruş yalnız döngü başında.',
      mnemonik: ['her', 'a', 'dım', 'da', 'bir', 'de', 'ne'] },
    { kod: '7:4', sag: 7, sol: 4, zorluk: 6, ad: 'Yediye dört',
      not: 'Usta seviye; yoğun bileşik ızgara.',
      mnemonik: ['bu', 'ra', 'sı', 'ar', 'tık', 'cid', 'di', 'iş', 'ol', 'du'] }
  ];

  function obeb(a, b) {
    a = Math.abs(a); b = Math.abs(b);
    while (b) { var t = b; b = a % b; a = t; }
    return a;
  }

  function okek(a, b) {
    if (!a || !b) { return 0; }
    return Math.abs(a * b) / obeb(a, b);
  }

  function sinirla(sayi, alt, ust) {
    var n = Number(sayi);
    if (!Number.isFinite(n)) { return alt; }
    return Math.max(alt, Math.min(ust, n));
  }

  function oranBul(kod) {
    for (var i = 0; i < ORANLAR.length; i++) {
      if (ORANLAR[i].kod === kod) { return ORANLAR[i]; }
    }
    return null;
  }

  /**
   * Bir döngünün süresi (saniye). bpm, DÖNGÜ BAŞINA değil REFERANS EL
   * başına verilir: referans el 'sag' ise sağ elin vuruş temposu bpm olur.
   * Böylece kullanıcı "sağ elim 100 BPM" diye düşünebilir.
   */
  function donguSuresi(oran, bpm, referans) {
    var o = typeof oran === 'string' ? oranBul(oran) : oran;
    if (!o) { return 0; }
    var sayi = (referans === 'sol') ? o.sol : o.sag;
    return (60 / sinirla(bpm, 20, 300)) * sayi;
  }

  /**
   * İki elin hedef zamanlarını üretir.
   * @returns {{sag: number[], sol: number[], ortak: number[], donguSn: number}}
   *   Zamanlar t0'a GÖRELİdir (saniye). 'ortak', iki elin aynı anda vurduğu
   *   zamanlar (her döngünün başı; oranlar aralarında asal olduğunda yalnız o).
   */
  function hedefleriUret(oran, bpm, dongu, referans) {
    var o = typeof oran === 'string' ? oranBul(oran) : oran;
    if (!o) { return { sag: [], sol: [], ortak: [], donguSn: 0 }; }
    var donguSn = donguSuresi(o, bpm, referans);
    var adet = Math.max(1, Math.round(dongu || 1));
    var sag = [];
    var sol = [];
    for (var d = 0; d < adet; d++) {
      var bas = d * donguSn;
      for (var i = 0; i < o.sag; i++) { sag.push(bas + (i * donguSn) / o.sag); }
      for (var j = 0; j < o.sol; j++) { sol.push(bas + (j * donguSn) / o.sol); }
    }
    /* Ortak vuruşlar: kayan nokta hatasına karşı toleranslı karşılaştırma */
    var ortak = [];
    sag.forEach(function (t) {
      if (sol.some(function (u) { return Math.abs(u - t) < 1e-9; })) { ortak.push(t); }
    });
    return { sag: sag, sol: sol, ortak: ortak, donguSn: donguSn };
  }

  /**
   * Bileşik ızgara: bir döngü OKEK(a,b) alt bölüme ayrılır, her hücrede
   * hangi ellerin vurduğu işaretlenir. Kullanıcının tarif ettiği
   * "kare bölünüyor" görselinin veri karşılığı.
   * @returns {Array<{indeks:number, sag:boolean, sol:boolean, oran:number}>}
   *   oran: hücrenin döngü içindeki konumu (0..1) — animasyon için.
   */
  function izgara(oran) {
    var o = typeof oran === 'string' ? oranBul(oran) : oran;
    if (!o) { return []; }
    var bolum = okek(o.sag, o.sol);
    var sagAdim = bolum / o.sag;
    var solAdim = bolum / o.sol;
    var hucreler = [];
    for (var i = 0; i < bolum; i++) {
      hucreler.push({
        indeks: i,
        sag: i % sagAdim === 0,
        sol: i % solAdim === 0,
        oran: i / bolum
      });
    }
    return hucreler;
  }

  /**
   * Mnemonik yuvaları: bileşik ızgarada SES OLAN hücreler. Hece sayısı
   * buna eşit olmalı (3:2 → 4, 4:3 → 6). Mnemonik cümle bu yuvalara
   * hecesi hecesine oturur.
   */
  function mnemonikYuvalari(oran) {
    return izgara(oran).filter(function (h) { return h.sag || h.sol; });
  }

  /**
   * Mnemonik cümleyi ses olan hücrelere oturtur — poliritim öğretiminin
   * klasik aracı (3:2 için "hiç zor değil" gibi). Cümle söylenince ritim
   * kendiliğinden doğru çıkar; öğrenci saymak zorunda kalmaz.
   *
   * Hece sayısı yuva sayısına EŞİT olmak zorunda. Değilse hizalama sessizce
   * kayar ve öğrenci yanlış ritmi ezberler; o yüzden uyuşmazlıkta hece
   * yerine '·' konur ve uyusuyor:false döner (birim testi bunu yakalar).
   */
  function mnemonikHaritasi(oran) {
    var o = typeof oran === 'string' ? oranBul(oran) : oran;
    if (!o) { return { uyusuyor: false, yuvalar: [] }; }
    var yuvalar = mnemonikYuvalari(o);
    var heceler = o.mnemonik || [];
    var uyusuyor = heceler.length === yuvalar.length;
    return {
      uyusuyor: uyusuyor,
      heceAdedi: heceler.length,
      yuvaAdedi: yuvalar.length,
      yuvalar: yuvalar.map(function (h, i) {
        return {
          indeks: h.indeks,
          oran: h.oran,
          el: (h.sag && h.sol) ? 'ortak' : (h.sag ? 'sag' : 'sol'),
          hece: uyusuyor ? heceler[i] : '·'
        };
      })
    };
  }

  /**
   * Tek elin değerlendirmesi. Her hedefe EN YAKIN tap atanır; bir tap iki
   * hedefe sayılmaz (kronolojik tek eşleme). Pencere dışı hedef kaçırılmış,
   * eşlenmeyen tap fazladan sayılır.
   *
   * NOT: Her el KENDİ tap listesiyle değerlendirilir — sağ elin vuruşu asla
   * sol elin hedefine eşlenmez. Poliritimde iki el aynı anda vurabildiği
   * için (ortak downbeat) bu ayrım şart.
   */
  function eliDegerlendir(hedefler, taplar, pencereSn) {
    var kullanildi = {};
    var eslesen = [];
    var kacirilan = 0;
    hedefler.forEach(function (h, hi) {
      var enIyi = -1;
      var enIyiFark = Infinity;
      taplar.forEach(function (t, ti) {
        if (kullanildi[ti]) { return; }
        var fark = Math.abs(t - h);
        if (fark < enIyiFark) { enIyiFark = fark; enIyi = ti; }
      });
      if (enIyi >= 0 && enIyiFark <= pencereSn) {
        kullanildi[enIyi] = true;
        eslesen.push({ hedefIdx: hi, tapIdx: enIyi, sapmaMs: (taplar[enIyi] - h) * 1000 });
      } else {
        kacirilan++;
      }
    });
    var fazla = taplar.length - eslesen.length;
    var sapmalar = eslesen.map(function (x) { return x.sapmaMs; });
    return {
      eslesen: eslesen,
      hedefAdedi: hedefler.length,
      isabet: eslesen.length,
      kacirilan: kacirilan,
      fazla: Math.max(0, fazla),
      ortSapmaMs: ortalama(sapmalar),
      ortMutlakMs: ortalama(sapmalar.map(Math.abs)),
      sdMs: standartSapma(sapmalar)
    };
  }

  function ortalama(dizi) {
    if (!dizi.length) { return 0; }
    return dizi.reduce(function (a, b) { return a + b; }, 0) / dizi.length;
  }

  function standartSapma(dizi) {
    var n = dizi.length;
    if (n < 2) { return 0; }
    var ort = ortalama(dizi);
    var kare = dizi.reduce(function (t, x) { return t + (x - ort) * (x - ort); }, 0);
    return Math.sqrt(kare / (n - 1));
  }

  /**
   * ETKİN TOLERANS PENCERESİ — skor şişmesine karşı koruma.
   *
   * Pencere, elin kendi vuruş aralığına göre çok genişse her dokunuş bir
   * hedefe uyar; kapsama ve doğruluk 1'e yaklaşır, skor tempo yanlış olsa
   * bile yüksek çıkar. Bu bir ölçüm değil TAVAN ETKİSİdir.
   *
   * O yüzden pencere, HIZLI elin vuruş aralığının %40'ıyla sınırlanır:
   * bu sınırda bir dokunuş komşu hedefe taşamaz (komşuya olan uzaklık
   * aralığın yarısıdır). Kullanıcının seçtiği pencere daha darsa o kullanılır.
   *
   * @returns {{ms:number, istenenMs:number, sinirMs:number, kirpildi:boolean, hizliAralikMs:number}}
   */
  function etkinPencere(oran, bpm, referans, istenenMs) {
    var o = typeof oran === 'string' ? oranBul(oran) : oran;
    var istenen = sinirla(istenenMs, 20, 500);
    if (!o) { return { ms: istenen, istenenMs: istenen, sinirMs: istenen, kirpildi: false, hizliAralikMs: 0 }; }
    var donguSn = donguSuresi(o, bpm, referans);
    var hizliAdet = Math.max(o.sag, o.sol);
    var hizliAralikMs = (donguSn / hizliAdet) * 1000;
    var sinir = hizliAralikMs * 0.4;
    var ms = Math.min(istenen, sinir);
    return {
      ms: Math.round(ms * 10) / 10,
      istenenMs: istenen,
      sinirMs: Math.round(sinir * 10) / 10,
      kirpildi: istenen > sinir + 1e-9,
      hizliAralikMs: Math.round(hizliAralikMs * 10) / 10
    };
  }

  /** Ardışık vuruşların ortalama aralığı (saniye). */
  function ortalamaAralik(taplar) {
    if (!taplar || taplar.length < 2) { return 0; }
    var araliklar = [];
    for (var i = 1; i < taplar.length; i++) { araliklar.push(taplar[i] - taplar[i - 1]); }
    return ortalama(araliklar);
  }

  /**
   * ORAN HATASI — faz kilitlenmesinin en net göstergesi.
   *
   * Poliritimde en yaygın çöküş, iki elin birbirine yapışıp oranın
   * BOZULMASIdır (3:2 → 2:2 gibi). Bunu ölçmek için ellerin gerçek vuruş
   * aralıkları (ITI) oranlanır ve hedef oranla karşılaştırılır:
   *   hedef  = sag/sol   (3:2 → sol aralığı sağın 1,5 katı olmalı)
   *   gercek = ITI(sol)/ITI(sag)
   * hataYuzde NEGATİF ise oran 1'e doğru çökmüş (eller yapışmış),
   * POZİTİF ise oran açılmış.
   *
   * Bu ölçü yön belirsizliğinden etkilenmez ve her oranda tanımlıdır.
   */
  function oranHatasi(sagTaplar, solTaplar, oran) {
    var o = typeof oran === 'string' ? oranBul(oran) : oran;
    if (!o) { return null; }
    var itiSag = ortalamaAralik(sagTaplar);
    var itiSol = ortalamaAralik(solTaplar);
    if (!itiSag || !itiSol) { return null; }
    var gercek = itiSol / itiSag;
    var hedef = o.sag / o.sol;
    return {
      gercek: Math.round(gercek * 1000) / 1000,
      hedef: Math.round(hedef * 1000) / 1000,
      hataYuzde: Math.round((100 * (gercek - hedef) / hedef) * 10) / 10
    };
  }

  /**
   * FAZ KAYMASI — ikincil elin bağımsızlığı.
   *
   * İkincil elin ortak olmayan her vuruşu, birincil elin iki vuruşu
   * ARASINDA belirli bir orantısal konumda durur (3:2'de tam ortada, 0,50).
   * Vuruş bu konumdan bir KENARA (birincil vuruşa) kayarsa bağımsızlık
   * kaybediliyor demektir.
   *
   * DİKKAT — burada "diğer ele doğru" diye tek bir yön TANIMLANAMAZ: 3:2'de
   * serbest vuruş iki birincil vuruşun tam ortasındadır, iki yön de eşit
   * uzaklıktadır. Bu yüzden yön yerine KENARA YAKLAŞMA ölçülür:
   *   kenaraYaklasma = |gerçekFaz − 0,5| − |idealFaz − 0,5|
   * Pozitif değer, vuruşun bir birincil vuruşa yaklaştığını (kilitlenme
   * eğilimi) gösterir; sıfıra yakın değer bağımsızlığın korunduğunu.
   *
   * Bu bir "beceri puanı" değil, hatanın BİÇİMİNİ gösteren tanımlayıcı ölçüdür.
   */
  function fazKaymasiOlc(ikincilHedefler, ikincilEslesen, birincilHedefler, taplar) {
    var kaymalar = [];
    var kenarYaklasmalari = [];
    var sirali = birincilHedefler.slice().sort(function (a, b) { return a - b; });

    ikincilEslesen.forEach(function (e) {
      var h = ikincilHedefler[e.hedefIdx];
      if (h === undefined) { return; }
      /* Ortak vuruş: faz tanımsız (aralığın tam kenarında) — atlanır */
      if (sirali.some(function (p) { return Math.abs(p - h) < 1e-9; })) { return; }

      /* Hedefi çevreleyen birincil aralığı bul */
      var p1 = null, p2 = null;
      for (var i = 0; i < sirali.length - 1; i++) {
        if (sirali[i] <= h && h < sirali[i + 1]) { p1 = sirali[i]; p2 = sirali[i + 1]; break; }
      }
      if (p1 === null) { return; }              // aralık dışı (son vuruştan sonra)
      var genislik = p2 - p1;
      if (genislik <= 0) { return; }

      var tapZamani = taplar[e.tapIdx];
      if (tapZamani === undefined) { return; }
      var idealFaz = (h - p1) / genislik;
      var gercekFaz = (tapZamani - p1) / genislik;
      kaymalar.push(gercekFaz - idealFaz);
      kenarYaklasmalari.push(Math.abs(gercekFaz - 0.5) - Math.abs(idealFaz - 0.5));
    });

    return {
      adet: kaymalar.length,
      /* İşaretli kayma: pozitif = geriye/ileriye topluca kaymış */
      ortKayma: Math.round(ortalama(kaymalar) * 1000) / 1000,
      /* Kenara yaklaşma: pozitif = birincil vuruşa çekilme (kilitlenme) */
      kenaraYaklasma: Math.round(ortalama(kenarYaklasmalari) * 1000) / 1000,
      kaymalar: kaymalar
    };
  }

  /**
   * Tam değerlendirme: iki el + bileşik skor + çekim.
   *
   * Skor felsefesi (docs/olcum-kilavuzu.md ile uyumlu): tek bir bileşik sayı
   * yanıltıcıdır, bu yüzden el başına ayrıntı DA döndürülür. Bileşik skor
   * yalnız oyunlaştırma içindir; karşılaştırma kararlılık (SD) üzerinden
   * okunmalıdır.
   */
  function degerlendir(secenekler) {
    var oran = typeof secenekler.oran === 'string' ? oranBul(secenekler.oran) : secenekler.oran;
    var hedefler = secenekler.hedefler;
    var pencereSn = sinirla(secenekler.pencereMs, 20, 500) / 1000;
    var sag = eliDegerlendir(hedefler.sag, secenekler.sagTaplar || [], pencereSn);
    var sol = eliDegerlendir(hedefler.sol, secenekler.solTaplar || [], pencereSn);

    /* Bağımsızlık: hangi el "ikincil"? Daha SEYREK vuran el ötekine çekilir. */
    var solIkincil = (oran ? oran.sol <= oran.sag : true);
    var fazKaymasi = solIkincil
      ? fazKaymasiOlc(hedefler.sol, sol.eslesen, hedefler.sag, secenekler.solTaplar || [])
      : fazKaymasiOlc(hedefler.sag, sag.eslesen, hedefler.sol, secenekler.sagTaplar || []);
    var oranSapmasi = oranHatasi(secenekler.sagTaplar || [], secenekler.solTaplar || [], oran);

    var toplamHedef = sag.hedefAdedi + sol.hedefAdedi;
    var toplamIsabet = sag.isabet + sol.isabet;
    var toplamFazla = sag.fazla + sol.fazla;
    var kapsama = toplamHedef ? toplamIsabet / toplamHedef : 0;
    var dogruluk = (toplamIsabet + toplamFazla) ? toplamIsabet / (toplamIsabet + toplamFazla) : 0;

    /*
     * Zamanlama bileşeni: EL BAŞINA hesaplanır, sonra KÖTÜ olan alınır.
     *
     * Neden ortalama değil: iki elin sapması birlikte ortalanırsa tek elin
     * sistematik hatası ötekinin doğruluğuyla gizlenir. Ölçülen örnek:
     * sağ el 75 ms geç, sol el kusursuz → bileşik ortalama 45 ms → "kusursuz".
     * Poliritimde asıl beceri İKİ elin birlikte doğru olması; oyuncu zayıf
     * eli kadar iyidir. O yüzden kötü el belirleyici.
     *
     * tamMs içinde kalan el 1.0, pencere sınırındaki el 0.0 puan alır.
     */
    var tamMs = sinirla(secenekler.tamMs, 10, 300);
    var pencereMs = pencereSn * 1000;
    function elZamanlamasi(el) {
      if (!el.eslesen.length) { return null; }
      return sinirla(1 - Math.max(0, el.ortMutlakMs - tamMs) / Math.max(1, pencereMs - tamMs), 0, 1);
    }
    var zSag = elZamanlamasi(sag);
    var zSol = elZamanlamasi(sol);
    var elZamanlari = [zSag, zSol].filter(function (z) { return z !== null; });
    var zamanlama = elZamanlari.length ? Math.min.apply(null, elZamanlari) : 0;

    var sapmalar = sag.eslesen.concat(sol.eslesen).map(function (x) { return Math.abs(x.sapmaMs); });
    var ortMutlak = ortalama(sapmalar);
    var skor = Math.round(100 * zamanlama * kapsama * dogruluk);

    return {
      surum: SURUM,
      oran: oran ? oran.kod : '',
      skor: skor,
      zamanlamaBileseni: Math.round(zamanlama * 100) / 100,
      /* Hangi el skoru belirledi? Geri bildirimde bu söylenir. */
      zayifEl: (zSag === null || zSol === null) ? '' : (zSag <= zSol ? 'sag' : 'sol'),
      kapsama: Math.round(kapsama * 1000) / 1000,
      dogruluk: Math.round(dogruluk * 1000) / 1000,
      ortMutlakMs: Math.round(ortMutlak * 10) / 10,
      sdMs: Math.round(standartSapma(sag.eslesen.concat(sol.eslesen)
        .map(function (x) { return x.sapmaMs; })) * 10) / 10,
      sag: sag,
      sol: sol,
      fazKaymasi: fazKaymasi,
      oranHatasi: oranSapmasi,
      ikincilEl: solIkincilAd(solIkincil)
    };
  }

  function solIkincilAd(solIkincil) { return solIkincil ? 'sol' : 'sag'; }

  /**
   * Kademeli zorluk: son skora göre bir sonraki oranı önerir.
   * Poliritim çok zor bir beceri — kullanıcı ilk denemede boğulmasın diye
   * yalnız yüksek skorda yükseltilir, düşükte bir basamak geri alınır.
   */
  function sonrakiOran(mevcutKod, skor) {
    var idx = ORANLAR.findIndex(function (o) { return o.kod === mevcutKod; });
    if (idx < 0) { return ORANLAR[0].kod; }
    if (skor >= 80 && idx < ORANLAR.length - 1) { return ORANLAR[idx + 1].kod; }
    if (skor < 40 && idx > 0) { return ORANLAR[idx - 1].kod; }
    return ORANLAR[idx].kod;
  }

  return {
    SURUM: SURUM,
    ORANLAR: ORANLAR,
    obeb: obeb,
    okek: okek,
    oranBul: oranBul,
    donguSuresi: donguSuresi,
    hedefleriUret: hedefleriUret,
    izgara: izgara,
    mnemonikYuvalari: mnemonikYuvalari,
    mnemonikHaritasi: mnemonikHaritasi,
    eliDegerlendir: eliDegerlendir,
    etkinPencere: etkinPencere,
    ortalamaAralik: ortalamaAralik,
    oranHatasi: oranHatasi,
    fazKaymasiOlc: fazKaymasiOlc,
    degerlendir: degerlendir,
    sonrakiOran: sonrakiOran,
    ortalama: ortalama,
    standartSapma: standartSapma
  };
});
