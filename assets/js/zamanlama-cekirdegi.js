/* ================================================================
   Ritim Atölyesi — ortak zamanlama ve ölçüm çekirdeği
   Tarayıcı: window.RitimZamanlama
   Node testi: require('../assets/js/zamanlama-cekirdegi.js')
   ================================================================ */
(function (kok, fabrika) {
  'use strict';
  var api = fabrika(kok);
  if (typeof module === 'object' && module.exports) { module.exports = api; }
  if (kok) { kok.RitimZamanlama = api; }
})(typeof globalThis !== 'undefined' ? globalThis : this, function (kok) {
  'use strict';

  var SURUM = '1.0.0';
  var AYAR_ANAHTARI = 'ritim_okuma_degerlendirme_v1';
  var BASLANGIC_KAPISI_MS = 150;

  function sinirla(deger, alt, ust) {
    return Math.max(alt, Math.min(ust, Number(deger) || 0));
  }

  function medyan(dizi) {
    if (!dizi.length) { return 0; }
    var sirali = dizi.slice().sort(function (a, b) { return a - b; });
    var orta = Math.floor(sirali.length / 2);
    return sirali.length % 2 ? sirali[orta] : (sirali[orta - 1] + sirali[orta]) / 2;
  }

  function mutlakSapmaMedyani(dizi, merkez) {
    if (!dizi.length) { return 0; }
    merkez = Number.isFinite(merkez) ? merkez : medyan(dizi);
    return medyan(dizi.map(function (x) { return Math.abs(x - merkez); }));
  }

  /*
   * Örneklem standart sapması (n-1). Zamanlama ölçümünde ASIL beceri
   * göstergesi budur: sabit kayma (cihaz gecikmesi + kişisel erken/geç
   * vurma eğilimi) ortalamaya girer, kararlılık ise dağılıma. Ortalama
   * cihaz değişince kayar, standart sapma kaymaz — bu yüzden dönem
   * karşılaştırmaları buradan okunmalıdır.
   */
  function standartSapma(dizi) {
    var n = dizi.length;
    if (n < 2) { return 0; }
    var ortalama = dizi.reduce(function (t, x) { return t + x; }, 0) / n;
    var kareToplam = dizi.reduce(function (t, x) {
      var f = x - ortalama;
      return t + f * f;
    }, 0);
    return Math.sqrt(kareToplam / (n - 1));
  }

  function guvenliAyarOku(depo) {
    try {
      var ham = JSON.parse((depo || kok.localStorage).getItem(AYAR_ANAHTARI) || '{}');
      return ham && typeof ham === 'object' ? ham : {};
    } catch (e) {
      return {};
    }
  }

  function kalibrasyonOku(depo) {
    var ham = guvenliAyarOku(depo).kalibrasyon || {};
    return {
      yapildi: !!ham.yapildi,
      telafiMs: sinirla(ham.telafiMs, -400, 400),
      dagilimMs: Math.max(0, Number(ham.dagilimMs) || 0),
      ornek: Math.max(0, Number(ham.ornek) || 0),
      tarih: String(ham.tarih || ''),
      tur: String(ham.tur || ''),
      cihazImzasi: String(ham.cihazImzasi || '')
    };
  }

  function tarayiciGecikmesiMs(ctx) {
    if (!ctx) { return 0; }
    return Math.max(0, Math.round(
      ((Number(ctx.baseLatency) || 0) + (Number(ctx.outputLatency) || 0)) * 1000
    ));
  }

  function cihazImzasi(ctx, ortam) {
    ortam = ortam || (typeof navigator !== 'undefined' ? navigator : {});
    var ua = String(ortam.userAgent || '').replace(/\s+/g, ' ').slice(0, 120);
    return [
      ctx && Number(ctx.sampleRate) || 0,
      ctx && Math.round((Number(ctx.baseLatency) || 0) * 1000000) || 0,
      ctx && Math.round((Number(ctx.outputLatency) || 0) * 1000000) || 0,
      ua
    ].join('|');
  }

  function kalibrasyonKaydet(kalibrasyon, ctx, depo) {
    depo = depo || (kok && kok.localStorage);
    if (!depo) { return false; }
    var ayar = guvenliAyarOku(depo);
    ayar.kalibrasyon = {
      yapildi: true,
      telafiMs: Math.round(sinirla(kalibrasyon.telafiMs, -400, 400)),
      dagilimMs: Math.round(Math.max(0, Number(kalibrasyon.dagilimMs) || 0)),
      ornek: Math.max(0, Number(kalibrasyon.ornek) || 0),
      tarih: kalibrasyon.tarih || new Date().toISOString(),
      tur: kalibrasyon.tur || 'otomatik',
      cihazImzasi: cihazImzasi(ctx)
    };
    try {
      depo.setItem(AYAR_ANAHTARI, JSON.stringify(ayar));
      if (kok && typeof kok.dispatchEvent === 'function' && typeof kok.CustomEvent === 'function') {
        kok.dispatchEvent(new kok.CustomEvent('ritim-zamanlama-guncellendi', {
          detail: { kalibrasyon: ayar.kalibrasyon }
        }));
      }
      return true;
    } catch (e) {
      return false;
    }
  }

  function kalibrasyonSifirla(depo) {
    depo = depo || (kok && kok.localStorage);
    if (!depo) { return false; }
    var ayar = guvenliAyarOku(depo);
    ayar.kalibrasyon = {
      yapildi: false, telafiMs: 0, dagilimMs: 0, ornek: 0,
      tarih: '', tur: '', cihazImzasi: ''
    };
    try {
      depo.setItem(AYAR_ANAHTARI, JSON.stringify(ayar));
      if (kok && typeof kok.dispatchEvent === 'function' && typeof kok.CustomEvent === 'function') {
        kok.dispatchEvent(new kok.CustomEvent('ritim-zamanlama-guncellendi', {
          detail: { kalibrasyon: ayar.kalibrasyon }
        }));
      }
      return true;
    } catch (e) {
      return false;
    }
  }

  function gunFarki(iso, simdi) {
    var tarih = Date.parse(iso || '');
    if (!Number.isFinite(tarih)) { return Infinity; }
    return Math.max(0, ((simdi || Date.now()) - tarih) / 86400000);
  }

  function kaliteDurumu(ctx, depo, simdi) {
    var kal = kalibrasyonOku(depo);
    var gecikmeMs = tarayiciGecikmesiMs(ctx);
    var yasGun = gunFarki(kal.tarih, simdi);
    var imzaDegisti = !!(kal.yapildi && kal.cihazImzasi && ctx
      && kal.cihazImzasi !== cihazImzasi(ctx));
    var eski = kal.yapildi && yasGun > 30;
    var kod = 'eksik';
    var etiket = 'Kalibrasyon gerekli';
    if (kal.yapildi) {
      if (imzaDegisti) {
        kod = 'cihaz-degisti'; etiket = 'Ses cihazı değişmiş olabilir';
      } else if (kal.tur === 'elle' || kal.ornek < 8) {
        kod = 'elle'; etiket = 'Elle ayarlandı';
      } else if (eski) {
        kod = 'eski'; etiket = 'Kalibrasyonu yenile';
      } else if (kal.dagilimMs > 80) {
        kod = 'zayif'; etiket = 'Ölçüm kararsız';
      } else if (kal.dagilimMs > 55) {
        kod = 'orta'; etiket = 'Ölçüm kullanılabilir';
      } else if (kal.dagilimMs > 30) {
        kod = 'iyi'; etiket = 'Ölçüm iyi';
      } else {
        kod = 'cok-iyi'; etiket = 'Ölçüm çok iyi';
      }
    }
    return {
      kod: kod,
      etiket: etiket,
      kullanilabilir: kal.yapildi && !imzaDegisti,
      yenilemeOnerilir: !kal.yapildi || imzaDegisti || eski || kal.dagilimMs > 65
        || kal.tur === 'elle' || kal.ornek < 8,
      kalibrasyon: kal,
      tarayiciGecikmesiMs: gecikmeMs,
      yasGun: yasGun,
      imzaDegisti: imzaDegisti,
      sampleRate: ctx && Number(ctx.sampleRate) || 0
    };
  }

  /*
   * Pointer/klavye olayının üretildiği anı AudioContext saatine taşır.
   * Ana iş parçacığı kısa süre takılmışsa handler'ın çalıştığı ctx.currentTime
   * yerine olayın timeStamp'i kullanılır. Şüpheli/eski zaman damgasında güvenli
   * biçimde currentTime'a döner.
   */
  function olayZamani(ctx, olay, performansSimdi) {
    if (!ctx) { return 0; }
    var simdi = Number.isFinite(performansSimdi)
      ? performansSimdi
      : (typeof performance !== 'undefined' && performance.now ? performance.now() : NaN);
    var olayMs = olay && Number(olay.timeStamp);
    var gecenMs = simdi - olayMs;
    if (Number.isFinite(olayMs) && Number.isFinite(gecenMs) && gecenMs >= 0 && gecenMs <= 250) {
      return ctx.currentTime - gecenMs / 1000;
    }
    return ctx.currentTime;
  }

  function hedefZamani(hedef) {
    return typeof hedef === 'number' ? hedef : Number(hedef && hedef.zaman);
  }

  function dahaIyi(a, b) {
    if (!a) { return b; }
    if (!b) { return a; }
    if (a.adet !== b.adet) { return a.adet > b.adet ? a : b; }
    if (Math.abs(a.maliyet - b.maliyet) > 0.000000001) {
      return a.maliyet < b.maliyet ? a : b;
    }
    return a;
  }

  /*
   * Kronolojik bire bir eşleştirme:
   * 1) en çok hedefi eşleştirir, 2) toplam mutlak sapmayı en aza indirir.
   * Böylece hızlı notalarda bir dokunuş iki hedefe yazılamaz ve komşu notaya
   * geriye doğru kayma oluşmaz.
   */
  function eslestir(taplar, hedefler, secenekler) {
    secenekler = secenekler || {};
    var uygun = typeof secenekler.hedefUygun === 'function'
      ? secenekler.hedefUygun
      : function () { return true; };
    var hedefListe = [];
    hedefler.forEach(function (hedef, asilIdx) {
      if (uygun(hedef, asilIdx)) {
        hedefListe.push({ hedef: hedef, asilIdx: asilIdx, zaman: hedefZamani(hedef) });
      }
    });
    var telafiSn = (Number(secenekler.telafiMs) || 0) / 1000;
    var duzeltilmis = taplar.map(function (zaman, asilIdx) {
      return { zaman: Number(zaman) - telafiSn, ham: Number(zaman), asilIdx: asilIdx };
    });
    var varsayilanEsik = Math.max(0, Number(secenekler.esikSn) || 0);
    var esikBul = typeof secenekler.esikSn === 'function'
      ? secenekler.esikSn
      : function () { return varsayilanEsik; };
    var satir = hedefListe.length + 1;
    var sutun = duzeltilmis.length + 1;
    var dp = Array.from({ length: satir }, function () { return Array(sutun); });

    for (var hi = hedefListe.length; hi >= 0; hi--) {
      for (var ti = duzeltilmis.length; ti >= 0; ti--) {
        if (hi === hedefListe.length && ti === duzeltilmis.length) {
          dp[hi][ti] = { adet: 0, maliyet: 0, ciftler: [] };
          continue;
        }
        var enIyi = null;
        if (hi < hedefListe.length) { enIyi = dahaIyi(enIyi, dp[hi + 1][ti]); }
        if (ti < duzeltilmis.length) { enIyi = dahaIyi(enIyi, dp[hi][ti + 1]); }
        if (hi < hedefListe.length && ti < duzeltilmis.length) {
          var fark = duzeltilmis[ti].zaman - hedefListe[hi].zaman;
          var esik = Math.max(0, Number(esikBul(
            hedefListe[hi].hedef, hedefListe[hi].asilIdx, hi
          )) || 0);
          if (Math.abs(fark) <= esik) {
            var devam = dp[hi + 1][ti + 1];
            enIyi = dahaIyi(enIyi, {
              adet: devam.adet + 1,
              maliyet: devam.maliyet + Math.abs(fark),
              ciftler: [{ hi: hi, ti: ti, fark: fark }].concat(devam.ciftler)
            });
          }
        }
        dp[hi][ti] = enIyi || { adet: 0, maliyet: 0, ciftler: [] };
      }
    }

    var hedefKullanildi = {};
    var tapKullanildi = {};
    var eslesenler = dp[0][0].ciftler.map(function (cift) {
      var h = hedefListe[cift.hi];
      var t = duzeltilmis[cift.ti];
      hedefKullanildi[h.asilIdx] = true;
      tapKullanildi[t.asilIdx] = true;
      return {
        hedef: h.hedef,
        hedefIdx: h.asilIdx,
        tapIdx: t.asilIdx,
        sapmaMs: cift.fark * 1000,
        hamSapmaMs: (t.ham - h.zaman) * 1000
      };
    });
    var kacirilanHedefler = hedefListe.filter(function (h) {
      return !hedefKullanildi[h.asilIdx];
    }).map(function (h) { return { hedef: h.hedef, hedefIdx: h.asilIdx }; });
    var fazlaTaplar = duzeltilmis.filter(function (t) {
      return !tapKullanildi[t.asilIdx];
    }).map(function (t) {
      var enYakin = null;
      hedefListe.forEach(function (h) {
        var fark = t.zaman - h.zaman;
        if (!enYakin || Math.abs(fark) < Math.abs(enYakin.sapmaMs / 1000)) {
          enYakin = {
            hedef: h.hedef, hedefIdx: h.asilIdx, tapIdx: t.asilIdx,
            sapmaMs: fark * 1000, hamSapmaMs: (t.ham - h.zaman) * 1000
          };
        }
      });
      return enYakin || {
        hedef: null, hedefIdx: -1, tapIdx: t.asilIdx, sapmaMs: 0, hamSapmaMs: 0
      };
    });

    return {
      eslesenler: eslesenler,
      kacirilanHedefler: kacirilanHedefler,
      fazlaTaplar: fazlaTaplar,
      hedefKullanildi: hedefKullanildi,
      tapKullanildi: tapKullanildi
    };
  }

  function kalibrasyonHesapla(beklenen, taplar) {
    var sonuc = eslestir(taplar, beklenen, { esikSn: 0.4, telafiMs: 0 });
    var sapmalar = sonuc.eslesenler.map(function (x) { return x.hamSapmaMs; });
    if (sapmalar.length < 8) {
      return { basarili: false, ornek: sapmalar.length, gerekli: 8 };
    }
    var merkez = medyan(sapmalar);
    return {
      basarili: true,
      telafiMs: Math.round(sinirla(merkez, -400, 400)),
      dagilimMs: Math.round(mutlakSapmaMedyani(sapmalar, merkez)),
      ornek: sapmalar.length,
      tarih: new Date().toISOString(),
      tur: 'otomatik'
    };
  }

  /*
   * Ortak 4 hazırlık + 12 ölçüm kalibrasyonu. Sesler Web Audio saatinde,
   * arayüz güncellemeleri ise aynı hedeflere bağlı zamanlayıcılarda yürür.
   */
  function kalibrasyonBaslat(secenekler) {
    var ctx = secenekler.ctx;
    var klik = secenekler.klik;
    var durum = typeof secenekler.durum === 'function' ? secenekler.durum : function () {};
    var tamam = typeof secenekler.tamam === 'function' ? secenekler.tamam : function () {};
    var bpm = Number(secenekler.bpm) || 72;
    var spb = 60 / bpm;
    var t0 = ctx.currentTime + 0.35;
    var beklenen = [];
    var taplar = [];
    var zamanlayicilar = [];
    var aktif = true;
    var vurusAcik = false;

    function planla(fn, zaman) {
      var id = setTimeout(function () { if (aktif) { fn(); } },
        Math.max(0, (zaman - ctx.currentTime) * 1000));
      zamanlayicilar.push(id);
    }

    durum({ faz: 'hazirlik', kalan: 4, ilerleme: 0, toplam: 12 });
    for (var h = 0; h < 4; h++) {
      var hz = t0 + h * spb;
      klik(hz, h === 0);
      (function (kalan, zaman) {
        planla(function () {
          durum({ faz: 'hazirlik', kalan: kalan, ilerleme: 0, toplam: 12 });
        }, zaman);
      })(4 - h, hz);
    }
    var baslangic = t0 + 4 * spb;
    for (var i = 0; i < 12; i++) {
      var hedef = baslangic + i * spb;
      beklenen.push(hedef);
      klik(hedef, i % 4 === 0);
    }
    planla(function () { vurusAcik = true; }, baslangic - BASLANGIC_KAPISI_MS / 1000);
    planla(function () {
      durum({ faz: 'olcum', kalan: 0, ilerleme: 0, toplam: 12 });
    }, baslangic);
    planla(function () {
      if (!aktif) { return; }
      aktif = false;
      vurusAcik = false;
      var sonuc = kalibrasyonHesapla(beklenen, taplar);
      if (sonuc.basarili && secenekler.kaydet !== false) {
        kalibrasyonKaydet(sonuc, ctx, secenekler.depo);
      }
      tamam(sonuc);
    }, baslangic + 12 * spb + 0.25);

    return {
      tap: function (olay) {
        if (!aktif || !vurusAcik) { return false; }
        taplar.push(olayZamani(ctx, olay));
        durum({
          faz: 'olcum', kalan: 0, ilerleme: Math.min(taplar.length, 12), toplam: 12
        });
        return true;
      },
      iptal: function () {
        aktif = false;
        vurusAcik = false;
        zamanlayicilar.forEach(clearTimeout);
        zamanlayicilar = [];
      },
      aktifMi: function () { return aktif; }
    };
  }

  return {
    SURUM: SURUM,
    AYAR_ANAHTARI: AYAR_ANAHTARI,
    BASLANGIC_KAPISI_MS: BASLANGIC_KAPISI_MS,
    sinirla: sinirla,
    medyan: medyan,
    mutlakSapmaMedyani: mutlakSapmaMedyani,
    standartSapma: standartSapma,
    olayZamani: olayZamani,
    eslestir: eslestir,
    kalibrasyonOku: kalibrasyonOku,
    kalibrasyonKaydet: kalibrasyonKaydet,
    kalibrasyonSifirla: kalibrasyonSifirla,
    kalibrasyonHesapla: kalibrasyonHesapla,
    kalibrasyonBaslat: kalibrasyonBaslat,
    tarayiciGecikmesiMs: tarayiciGecikmesiMs,
    cihazImzasi: cihazImzasi,
    kaliteDurumu: kaliteDurumu
  };
});
