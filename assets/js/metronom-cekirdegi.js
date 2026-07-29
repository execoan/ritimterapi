/* ================================================================
   Profesyonel Metronom Hesaplama Çekirdeği
   - Swing/shuffle alt bölünme ofsetleri
   - Bileşik ve aksak ölçü grupları
   - Sayarak giriş fazı
   Tarayıcı + Node.js için ortak, saf ve deterministik API
   ================================================================ */
(function (kok, fabrika) {
  'use strict';
  var api = fabrika();
  if (typeof module === 'object' && module.exports) { module.exports = api; }
  if (kok) { kok.MetronomCekirdegi = api; }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  var SURUM = 1;
  var GRUPLAMALAR = {
    '2/4': ['2'],
    '3/4': ['3'],
    '4/4': ['4', '2+2'],
    '5/4': ['3+2', '2+3', '5'],
    '6/8': ['3+3', '2+2+2', '6'],
    '7/8': ['2+2+3', '2+3+2', '3+2+2', '7'],
    '8/8': ['3+3+2', '3+2+3', '2+3+3', '4+4', '8'],
    '9/8': ['3+3+3', '2+2+2+3', '9'],
    '11/8': ['3+3+3+2', '3+2+3+3', '2+3+3+3', '11'],
    '12/8': ['3+3+3+3', '4+4+4', '12']
  };

  function sinirla(sayi, alt, ust) {
    sayi = Number(sayi);
    if (!Number.isFinite(sayi)) { sayi = alt; }
    return Math.max(alt, Math.min(ust, sayi));
  }

  function olcuAnahtari(pay, payda) {
    pay = Math.max(1, Math.round(Number(pay) || 4));
    payda = Number(payda) === 8 ? 8 : 4;
    return pay + '/' + payda;
  }

  function gruplamaSecenekleri(pay, payda) {
    var anahtar = olcuAnahtari(pay, payda);
    return (GRUPLAMALAR[anahtar] || [String(Math.max(1, Math.round(Number(pay) || 4)))]).slice();
  }

  function gruplamaCoz(pay, ifade) {
    pay = Math.max(1, Math.round(Number(pay) || 4));
    var parcalar = String(ifade || '').split('+').map(function (x) {
      return Math.round(Number(x));
    });
    if (!parcalar.length
      || parcalar.some(function (x) { return !Number.isFinite(x) || x <= 0; })
      || parcalar.reduce(function (t, x) { return t + x; }, 0) !== pay) {
      return [pay];
    }
    return parcalar;
  }

  function gruplamaDeseni(pay, ifade) {
    pay = Math.max(1, Math.round(Number(pay) || 4));
    var gruplar = gruplamaCoz(pay, ifade);
    var desen = Array.from({ length: pay }, function () { return 1; });
    var konum = 0;
    gruplar.forEach(function (uzunluk) {
      desen[konum] = 2;
      konum += uzunluk;
    });
    return desen;
  }

  /*
   * Swing yüzdesi, ikilinin ilk notasının toplam vuruş içindeki payıdır:
   * 50 = düz, 60 = hafif, 66.7 = klasik üçleme shuffle, 75 = sert.
   * Onaltılıkta oran her yarım vuruştaki ikiliye ayrı uygulanır.
   */
  function altVurusOfsetleri(altBolunme, swingYuzde) {
    var alt = [1, 2, 3, 4].indexOf(Number(altBolunme)) >= 0 ? Number(altBolunme) : 1;
    var swing = sinirla(swingYuzde === undefined ? 50 : swingYuzde, 50, 75) / 100;
    if (alt === 1) { return [0]; }
    if (alt === 2) { return [0, swing]; }
    if (alt === 3) { return [0, 1 / 3, 2 / 3]; }
    return [0, swing / 2, 0.5, 0.5 + swing / 2];
  }

  function girisBilgisi(vurusNo, olcuAdedi, girisOlcu) {
    vurusNo = Math.max(0, Math.round(Number(vurusNo) || 0));
    olcuAdedi = Math.max(1, Math.round(Number(olcuAdedi) || 4));
    girisOlcu = Math.max(0, Math.min(4, Math.round(Number(girisOlcu) || 0)));
    var toplam = olcuAdedi * girisOlcu;
    var giriste = vurusNo < toplam;
    var kalan = giriste ? toplam - vurusNo : 0;
    return {
      giriste: giriste,
      toplamVurus: toplam,
      kalanVurus: kalan,
      kalanOlcu: giriste ? Math.ceil(kalan / olcuAdedi) : 0,
      olcuIcindeki: vurusNo % olcuAdedi,
      calismaVurusNo: giriste ? -1 : vurusNo - toplam
    };
  }

  return {
    SURUM: SURUM,
    GRUPLAMALAR: GRUPLAMALAR,
    olcuAnahtari: olcuAnahtari,
    gruplamaSecenekleri: gruplamaSecenekleri,
    gruplamaCoz: gruplamaCoz,
    gruplamaDeseni: gruplamaDeseni,
    altVurusOfsetleri: altVurusOfsetleri,
    girisBilgisi: girisBilgisi
  };
});
