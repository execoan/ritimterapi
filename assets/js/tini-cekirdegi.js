/* ================================================================
   Kalın–İnce Tını — kalıp çekirdeği

   ÖLÇÜM ARACI DEĞİLDİR. Puanlama yok, kayıt yok. Bu dosya yalnız
   atölyede gösterilecek kalıpları ve çalma zamanlamasını üretir.

   Kalıplar eğitmenin etkinlik belgesinden alındı. Belgedeki set
   MATEMATİKSEL OLARAK EKSİKSİZ ve bu tesadüf değil:
     A kademesi → 2 olayın 2² = 4 kombinasyonunun HEPSİ
     B + C      → 3 olayın 2³ = 8 kombinasyonunun HEPSİ
   Birim testi bu eksiksizliği doğrular; kalıp listesi elle
   düzenlenirken bir varyant düşerse test yakalar.

   Simge sözleşmesi: 'k' = kalın (dikdörtgen), 'i' = ince (daire).
   ================================================================ */
(function (kok, fabrika) {
  'use strict';
  var api = fabrika();
  if (typeof module === 'object' && module.exports) { module.exports = api; }
  if (kok) { kok.TiniCekirdegi = api; }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  var SURUM = 1;

  /*
   * Kademeler. 'olay' = kalıptaki vuruş sayısı.
   * Sıra belgedekiyle birebir korundu — eğitmen kâğıttan takip edebilsin.
   */
  var KADEMELER = [
    {
      kod: 'A', ad: 'A — iki vuruş', olay: 2,
      aciklama: 'İki olaylı kalıplar. Kalın ve ince ayrımının ilk oturduğu kademe.',
      kaliplar: [
        ['k', 'i'],
        ['k', 'k'],
        ['i', 'i'],
        ['i', 'k']
      ]
    },
    {
      kod: 'B', ad: 'B — üç vuruş', olay: 3,
      aciklama: 'Üç olaya uzar. Kalın ağırlıklı yarısı.',
      kaliplar: [
        ['k', 'k', 'k'],
        ['k', 'k', 'i'],
        ['k', 'i', 'i'],
        ['i', 'k', 'k']
      ]
    },
    {
      kod: 'C', ad: 'C — üç vuruş (ince ağırlıklı)', olay: 3,
      aciklama: 'Üç olayın kalan yarısı. B ile birlikte tüm kombinasyonlar tamamlanır.',
      kaliplar: [
        ['i', 'i', 'k'],
        ['k', 'i', 'k'],
        ['i', 'k', 'i'],
        ['i', 'i', 'i']
      ]
    }
  ];

  function kademeBul(kod) {
    for (var i = 0; i < KADEMELER.length; i++) {
      if (KADEMELER[i].kod === kod) { return KADEMELER[i]; }
    }
    return null;
  }

  /** Bir kademedeki kalıpların tümü (kopya döner, dışarıdan bozulmasın). */
  function kaliplar(kod) {
    var k = kademeBul(kod);
    if (!k) { return []; }
    return k.kaliplar.map(function (x) { return x.slice(); });
  }

  /** Tüm kademelerin kalıpları peş peşe. */
  function tumKaliplar() {
    return KADEMELER.reduce(function (t, k) {
      return t.concat(k.kaliplar.map(function (x) { return x.slice(); }));
    }, []);
  }

  /**
   * Verilen uzunluktaki OLASI tüm kalıpları üretir (2^n).
   * Eksiksizlik denetiminin karşılaştırma tarafı.
   */
  function olasiKaliplar(uzunluk) {
    var sonuc = [];
    var toplam = Math.pow(2, uzunluk);
    for (var n = 0; n < toplam; n++) {
      var kalip = [];
      for (var b = uzunluk - 1; b >= 0; b--) {
        kalip.push(((n >> b) & 1) ? 'i' : 'k');
      }
      sonuc.push(kalip);
    }
    return sonuc;
  }

  function anahtar(kalip) { return kalip.join(''); }

  /**
   * Bir kalıp kümesi, o uzunluktaki tüm kombinasyonları kapsıyor mu?
   * @returns {{eksiksiz:boolean, beklenen:number, bulunan:number, eksikler:string[], tekrarlar:string[]}}
   */
  function eksiksizMi(kalipKumesi, uzunluk) {
    var beklenen = olasiKaliplar(uzunluk).map(anahtar);
    var bulunan = kalipKumesi.filter(function (k) { return k.length === uzunluk; }).map(anahtar);
    var sayac = {};
    bulunan.forEach(function (a) { sayac[a] = (sayac[a] || 0) + 1; });
    return {
      eksiksiz: beklenen.every(function (b) { return sayac[b] >= 1; }) && bulunan.length === beklenen.length,
      beklenen: beklenen.length,
      bulunan: bulunan.length,
      eksikler: beklenen.filter(function (b) { return !sayac[b]; }),
      tekrarlar: Object.keys(sayac).filter(function (a) { return sayac[a] > 1; })
    };
  }

  /**
   * Bir kalıbın çalma zamanlaması (saniye, t0'a göreli).
   * Vuruşlar eşit aralıklı; sonda kalıbın "kapanması" için bir boşluk bırakılır.
   * @returns {{olaylar:Array<{t:number, tini:string, sira:number}>, sureSn:number, araSn:number}}
   */
  function zamanlama(kalip, bpm) {
    var b = Math.max(30, Math.min(200, Number(bpm) || 72));
    var araSn = 60 / b;
    var olaylar = (kalip || []).map(function (tini, i) {
      return { t: i * araSn, tini: tini === 'i' ? 'i' : 'k', sira: i };
    });
    return {
      olaylar: olaylar,
      araSn: araSn,
      /* Son vuruştan sonra bir vuruşluk boşluk: kalıp kapanmış hissedilsin */
      sureSn: olaylar.length ? olaylar.length * araSn : 0
    };
  }

  /**
   * İKİ GRUP DAĞILIMI — etkinliğin grup formatı.
   * Belgede grup ikiye ayrılır: bir yan kalın tınılı cisimler, öteki ince.
   * Her olayın hangi gruba düştüğünü verir; eğitmen ekrandan okuyabilsin.
   */
  function grupDagilimi(kalip) {
    var kalin = [];
    var ince = [];
    (kalip || []).forEach(function (t, i) {
      if (t === 'i') { ince.push(i); } else { kalin.push(i); }
    });
    return {
      kalinSiralar: kalin,
      inceSiralar: ince,
      kalinAdet: kalin.length,
      inceAdet: ince.length,
      /* Tek grubun tüm kalıbı çaldığı durum: öteki grup bu turda susar */
      tekGrupMu: kalin.length === 0 || ince.length === 0
    };
  }

  /** Kalıbı okunur metne çevirir (ekran okuyucu ve yazdırma için). */
  function okunurMetin(kalip) {
    return (kalip || []).map(function (t) { return t === 'i' ? 'ince' : 'kalın'; }).join(' – ');
  }

  return {
    SURUM: SURUM,
    KADEMELER: KADEMELER,
    kademeBul: kademeBul,
    kaliplar: kaliplar,
    tumKaliplar: tumKaliplar,
    olasiKaliplar: olasiKaliplar,
    eksiksizMi: eksiksizMi,
    zamanlama: zamanlama,
    grupDagilimi: grupDagilimi,
    okunurMetin: okunurMetin
  };
});
