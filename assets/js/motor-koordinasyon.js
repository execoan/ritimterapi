(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) { module.exports = api; }
  else { root.MotorKoordinasyon = api; }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var DESENLER = {
    donusumlu: { ad: 'Dönüşümlü', dizi: ['L', 'R'] },
    ikiserli: { ad: 'İkişerli', dizi: ['L', 'L', 'R', 'R'] },
    capraz: { ad: 'Çapraz bağlama', dizi: ['L', 'B', 'R', 'B'] },
    eszamanli: { ad: 'Eşzamanlı', dizi: ['B'] },
    karma: { ad: 'Karma koordinasyon', dizi: ['L', 'R', 'B', 'L', 'B', 'R', 'R', 'L'] }
  };

  function sinirla(n, alt, ust, varsayilan) {
    n = Number(n);
    return Number.isFinite(n) ? Math.max(alt, Math.min(ust, n)) : varsayilan;
  }

  function hedefleriOlustur(secenekler) {
    secenekler = secenekler || {};
    var bpm = sinirla(secenekler.bpm, 30, 180, 60);
    var sureSn = sinirla(secenekler.sureSn, 1, 600, 30);
    var desenKodu = DESENLER[secenekler.desen] ? secenekler.desen : 'donusumlu';
    var dizi = DESENLER[desenKodu].dizi;
    var aralikMs = 60000 / bpm;
    var vurusSayisi = Math.max(1, Math.floor(sureSn * 1000 / aralikMs));
    var hedefler = [];
    for (var i = 0; i < vurusSayisi; i++) {
      var kod = dizi[i % dizi.length];
      var eller = kod === 'B' ? ['L', 'R'] : [kod];
      eller.forEach(function (el) {
        hedefler.push({
          id: hedefler.length,
          grup: i,
          vurus: i,
          el: el,
          eszamanli: kod === 'B',
          zamanMs: i * aralikMs
        });
      });
    }
    return {
      bpm: bpm,
      sureSn: sureSn,
      desen: desenKodu,
      aralikMs: aralikMs,
      vurusSayisi: vurusSayisi,
      hedefler: hedefler
    };
  }

  function ortalama(dizi) {
    return dizi.length ? dizi.reduce(function (a, b) { return a + b; }, 0) / dizi.length : 0;
  }

  /*
   * Tek el için doğrusal, kronolojik bire bir eşleştirme.
   * Komşu hedef/tap daha yakınsa mevcut öğeyi atlayarak hızlı tempoda
   * bir dokunuşun yanlış vuruşa kaymasını önler; bellek kullanımı O(n)'dir.
   */
  function eslestirEl(taplar, hedefler, toleransMs, telafiMs) {
    toleransMs = sinirla(toleransMs, 30, 500, 140);
    telafiMs = Number(telafiMs) || 0;
    var taps = (taplar || []).map(function (t, idx) {
      return {
        asil: t,
        idx: idx,
        zamanMs: Number(t.zamanMs) - telafiMs
      };
    }).sort(function (a, b) { return a.zamanMs - b.zamanMs; });
    var hs = (hedefler || []).slice().sort(function (a, b) { return a.zamanMs - b.zamanMs; });
    var ti = 0;
    var hi = 0;
    var eslesenler = [];
    var fazlaTaplar = [];
    var kacirilanHedefler = [];
    while (hi < hs.length && ti < taps.length) {
      var h = hs[hi];
      var t = taps[ti];
      var fark = t.zamanMs - h.zamanMs;
      if (fark < -toleransMs) {
        fazlaTaplar.push(t.asil); ti++; continue;
      }
      if (fark > toleransMs) {
        kacirilanHedefler.push(h); hi++; continue;
      }
      var sonrakiH = hs[hi + 1];
      if (sonrakiH && Math.abs(t.zamanMs - sonrakiH.zamanMs) < Math.abs(fark)
          && Math.abs(t.zamanMs - sonrakiH.zamanMs) <= toleransMs) {
        kacirilanHedefler.push(h); hi++; continue;
      }
      var sonrakiT = taps[ti + 1];
      if (sonrakiT && Math.abs(sonrakiT.zamanMs - h.zamanMs) < Math.abs(fark)
          && Math.abs(sonrakiT.zamanMs - h.zamanMs) <= toleransMs) {
        fazlaTaplar.push(t.asil); ti++; continue;
      }
      eslesenler.push({
        hedef: h,
        tap: t.asil,
        sapmaMs: fark,
        hamSapmaMs: Number(t.asil.zamanMs) - h.zamanMs
      });
      hi++; ti++;
    }
    while (hi < hs.length) { kacirilanHedefler.push(hs[hi++]); }
    while (ti < taps.length) { fazlaTaplar.push(taps[ti++].asil); }
    return {
      eslesenler: eslesenler,
      kacirilanHedefler: kacirilanHedefler,
      fazlaTaplar: fazlaTaplar
    };
  }

  function elMetrigi(el, hedefler, eslesenler, fazlaTaplar) {
    var elHedef = hedefler.filter(function (h) { return h.el === el; });
    var elEslesen = eslesenler.filter(function (x) { return x.hedef.el === el; });
    var sapmalar = elEslesen.map(function (x) { return Number(x.sapmaMs) || 0; });
    var hizlar = elEslesen.map(function (x) { return Number(x.velocity) || 0; }).filter(function (x) { return x > 0; });
    return {
      hedef: elHedef.length,
      isabet: elEslesen.length,
      kacirilan: Math.max(0, elHedef.length - elEslesen.length),
      fazla: fazlaTaplar.filter(function (x) { return x.el === el; }).length,
      dogruluk: elHedef.length ? Math.round(100 * elEslesen.length / elHedef.length) : 0,
      ortSapmaMs: Math.round(ortalama(sapmalar)),
      ortMutlakMs: Math.round(ortalama(sapmalar.map(Math.abs))),
      velocity: hizlar.length ? Math.round(ortalama(hizlar)) : null
    };
  }

  function yorgunlukHesapla(hedefler, eslesenler, toleransMs) {
    if (!hedefler.length) { return { ilkMs: 0, sonMs: 0, farkMs: 0, durum: 'veri-yok' }; }
    var eslesenHarita = {};
    eslesenler.forEach(function (x) { eslesenHarita[x.hedef.id] = Math.abs(Number(x.sapmaMs) || 0); });
    var cezalar = hedefler.map(function (h) {
      return eslesenHarita[h.id] === undefined ? toleransMs * 1.25 : eslesenHarita[h.id];
    });
    var dilim = Math.max(1, Math.floor(cezalar.length / 3));
    var ilk = ortalama(cezalar.slice(0, dilim));
    var son = ortalama(cezalar.slice(-dilim));
    var fark = son - ilk;
    return {
      ilkMs: Math.round(ilk),
      sonMs: Math.round(son),
      farkMs: Math.round(fark),
      durum: fark > Math.max(25, ilk * 0.35) ? 'dusuyor'
        : fark < -Math.max(20, ilk * 0.25) ? 'iyilesiyor' : 'stabil'
    };
  }

  function sonucHesapla(secenekler) {
    secenekler = secenekler || {};
    var hedefler = Array.isArray(secenekler.hedefler) ? secenekler.hedefler : [];
    var eslesenler = Array.isArray(secenekler.eslesenler) ? secenekler.eslesenler : [];
    var fazlaTaplar = Array.isArray(secenekler.fazlaTaplar) ? secenekler.fazlaTaplar : [];
    var toleransMs = sinirla(secenekler.toleransMs, 30, 500, 140);
    var sol = elMetrigi('L', hedefler, eslesenler, fazlaTaplar);
    var sag = elMetrigi('R', hedefler, eslesenler, fazlaTaplar);
    var toplamHedef = hedefler.length;
    var duzeltilmisIsabet = Math.max(0, eslesenler.length - fazlaTaplar.length * 0.5);
    var dogruluk = toplamHedef ? Math.round(100 * duzeltilmisIsabet / toplamHedef) : 0;
    var mutlaklar = eslesenler.map(function (x) { return Math.abs(Number(x.sapmaMs) || 0); });
    var ortMutlak = Math.round(ortalama(mutlaklar));
    var zamanlama = Math.max(0, Math.round(100 * (1 - ortMutlak / Math.max(1, toleransMs))));
    var asimetriMs = Math.abs(sol.ortSapmaMs - sag.ortSapmaMs);
    var asimetriSkoru = Math.max(0, Math.round(100 * (1 - asimetriMs / Math.max(40, toleransMs))));

    var ciftler = {};
    eslesenler.forEach(function (x) {
      if (!x.hedef.eszamanli) { return; }
      if (!ciftler[x.hedef.grup]) { ciftler[x.hedef.grup] = {}; }
      ciftler[x.hedef.grup][x.hedef.el] = Number(x.tapZamaniMs);
    });
    var esFarklari = Object.keys(ciftler).map(function (k) {
      var c = ciftler[k];
      return Number.isFinite(c.L) && Number.isFinite(c.R) ? Math.abs(c.L - c.R) : null;
    }).filter(function (x) { return x !== null; });
    var esOrtalama = esFarklari.length ? Math.round(ortalama(esFarklari)) : null;
    var esSkoru = esOrtalama === null
      ? 100
      : Math.max(0, Math.round(100 * (1 - esOrtalama / Math.max(60, toleransMs))));
    var yorgunluk = yorgunlukHesapla(hedefler, eslesenler, toleransMs);
    var skor = Math.round(dogruluk * 0.55 + zamanlama * 0.25 + asimetriSkoru * 0.10 + esSkoru * 0.10);

    return {
      skor: Math.max(0, Math.min(100, skor)),
      dogruluk: Math.max(0, Math.min(100, dogruluk)),
      zamanlama: zamanlama,
      toplamHedef: toplamHedef,
      toplamIsabet: eslesenler.length,
      toplamFazla: fazlaTaplar.length,
      ortMutlakMs: ortMutlak,
      asimetriMs: asimetriMs,
      eszamanlilikMs: esOrtalama,
      eszamanliCift: esFarklari.length,
      sol: sol,
      sag: sag,
      yorgunluk: yorgunluk
    };
  }

  return {
    DESENLER: DESENLER,
    hedefleriOlustur: hedefleriOlustur,
    eslestirEl: eslestirEl,
    sonucHesapla: sonucHesapla,
    yorgunlukHesapla: yorgunlukHesapla
  };
}));
