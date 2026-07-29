(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) { module.exports = api; }
  else { root.MetronomSetlist = api; }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  function sayi(deger, alt, ust, varsayilan) {
    var n = Number(deger);
    return Number.isFinite(n) ? Math.max(alt, Math.min(ust, n)) : varsayilan;
  }

  function adimNormalle(ham, sira) {
    ham = ham && typeof ham === 'object' ? ham : {};
    var olcu = Math.round(sayi(ham.olcu, 2, 12, 4));
    var payda = Number(ham.payda) === 8 ? 8 : 4;
    var alt = [1, 2, 3, 4].indexOf(Number(ham.alt)) >= 0 ? Number(ham.alt) : 1;
    var poli = Math.round(sayi(ham.poliritim, 0, 12, 0));
    if (poli === 1) { poli = 0; }
    var desen = Array.isArray(ham.desen) ? ham.desen.slice(0, olcu).map(function (x) {
      return Math.round(sayi(x, 0, 2, 1));
    }) : [];
    while (desen.length < olcu) { desen.push(desen.length === 0 ? 2 : 1); }
    return {
      baslik: String(ham.baslik || ('Adım ' + ((sira || 0) + 1))).trim().slice(0, 60),
      bpm: Math.round(sayi(ham.bpm, 30, 240, 92)),
      olcu: olcu,
      payda: payda,
      gruplama: String(ham.gruplama || 'ozel').slice(0, 30),
      alt: alt,
      swing: sayi(ham.swing, 50, 75, 50),
      poliritim: poli,
      poliDuzey: Math.round(sayi(ham.poliDuzey, 10, 100, 55)),
      girisOlcu: [0, 1, 2, 4].indexOf(Number(ham.girisOlcu)) >= 0 ? Number(ham.girisOlcu) : 0,
      sureSn: Math.round(sayi(ham.sureSn, 15, 7200, 300)),
      gecis: ham.gecis === 'bekle' ? 'bekle' : 'otomatik',
      desen: desen
    };
  }

  function setNormalle(ham) {
    ham = ham && typeof ham === 'object' ? ham : {};
    return {
      id: Math.max(0, parseInt(ham.id, 10) || 0),
      ad: String(ham.ad || 'Yeni çalışma seti').trim().slice(0, 80),
      aciklama: String(ham.aciklama || '').trim().slice(0, 300),
      adimlar: (Array.isArray(ham.adimlar) ? ham.adimlar : []).slice(0, 50).map(adimNormalle)
    };
  }

  function adimTasi(adimlar, kaynak, hedef) {
    var kopya = adimlar.slice();
    if (kaynak < 0 || kaynak >= kopya.length || hedef < 0 || hedef >= kopya.length || kaynak === hedef) {
      return kopya;
    }
    var adim = kopya.splice(kaynak, 1)[0];
    kopya.splice(hedef, 0, adim);
    return kopya;
  }

  function toplamSure(adimlar) {
    return (adimlar || []).reduce(function (toplam, adim) {
      return toplam + adimNormalle(adim, 0).sureSn;
    }, 0);
  }

  function sureYaz(saniye) {
    saniye = Math.max(0, Math.round(Number(saniye) || 0));
    var saat = Math.floor(saniye / 3600);
    var dk = Math.floor((saniye % 3600) / 60);
    var sn = saniye % 60;
    if (saat) {
      return saat + ':' + String(dk).padStart(2, '0') + ':' + String(sn).padStart(2, '0');
    }
    return String(dk).padStart(2, '0') + ':' + String(sn).padStart(2, '0');
  }

  return {
    adimNormalle: adimNormalle,
    setNormalle: setNormalle,
    adimTasi: adimTasi,
    toplamSure: toplamSure,
    sureYaz: sureYaz
  };
}));
