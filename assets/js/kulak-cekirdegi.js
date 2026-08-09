/**
 * KULAK YOLU ÇEKİRDEĞİ — atölyeye uyarlanmış kulak eğitimi müfredatı ve
 * soru üretimi. Saf modül: ses ve DOM yok, Node ile birim test edilir.
 *
 * Esin: ilerlemeli "kulak yolu" uygulamaları (bölüm → adım → 10 soru).
 * Uyarlama: burası gitar okulu değil ritim atölyesi — çalgıya özgü bölümler
 * (tel/klavye, akor adları) yerine GENEL işitsel ayrım + atölyenin kimliği
 * olan RİTMİK KULAK bölümü kondu. Adlandırma çocuk dostu ve teoriye
 * zorlamadan: "tiz/pes", "dar/geniş", "parlak/yumuşak".
 *
 * Dil kuralı (CLAUDE.md §2): beceri adları gözlemseldir, sonuç vaadi yok.
 */
(function (kok, kur) {
  if (typeof module === 'object' && module.exports) { module.exports = kur(); }
  else { kok.KulakCekirdegi = kur(); }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var SURUM = 1;
  var SORU_SAYISI = 10;

  /* ---------------------------------------------------------------
     Deterministik rastgele — aynı tohum aynı soruları üretir.
     Sayfada tohum her oturumda değişir (ezber olmasın); testte sabittir.
     --------------------------------------------------------------- */
  function rastgele(tohum) {
    var d = tohum >>> 0;
    return function () {
      d = (d * 1103515245 + 12345) & 0x7fffffff;
      return d / 0x7fffffff;
    };
  }

  /* Nota yardımcıları: MIDI numarası ↔ frekans (A4=69=440 Hz) */
  function midiHz(midi) { return 440 * Math.pow(2, (midi - 69) / 12); }

  /* ---------------------------------------------------------------
     MÜFREDAT — 5 bölüm, 24 adım.
     Her adımın 'tur'u soru üreticisini seçer; 'ayar' zorluk parametreleri.
     secenekler: kullanıcıya gösterilen cevap düğmeleri (sırası sabit).
     --------------------------------------------------------------- */
  var BOLUMLER = [
    { ad: 'Tiz–Pes Farkındalığı',
      aciklama: 'İki sesi karşılaştır: aynı mı, hangisi tiz? Kalın–İnce çalışmasının kulak hali.',
      adimlar: [
        { id: 'K1-1', ad: 'Aynı mı Farklı mı', tur: 'ayni_farkli', ayar: { minFark: 4, maxFark: 9 } },
        { id: 'K1-2', ad: 'Tiz mi Pes mi', tur: 'tiz_pes', ayar: { minFark: 5, maxFark: 10 } },
        { id: 'K1-3', ad: 'Oktav Tanıma', tur: 'ayni_farkli', ayar: { minFark: 12, maxFark: 12, oktavIpucu: true } },
        { id: 'K1-4', ad: 'Yakın Farklar', tur: 'tiz_pes', ayar: { minFark: 2, maxFark: 3 } },
        { id: 'K1-5', ad: 'En İnce Farklar', tur: 'tiz_pes', ayar: { minFark: 1, maxFark: 1 } }
      ] },
    { ad: 'Ses Yönü ve Kontur',
      aciklama: 'Ezgi nereye gidiyor? Yükseliş, alçalış ve iniş-çıkış çizgisini yakala.',
      adimlar: [
        { id: 'K2-1', ad: 'Yükseliyor mu Alçalıyor mu', tur: 'yon', ayar: { notaSayisi: 2, adim: [2, 5] } },
        { id: 'K2-2', ad: 'Üç Nota Kontur', tur: 'kontur', ayar: { adim: [2, 4] } },
        { id: 'K2-3', ad: 'Ezgi Aynı mı Farklı mı', tur: 'ezgi_ayni', ayar: { notaSayisi: 3, degisim: 2 } },
        { id: 'K2-4', ad: 'Kontur Kontrolü', tur: 'kontur', ayar: { adim: [1, 3] } }
      ] },
    { ad: 'Aralık Genişliği',
      aciklama: 'İki ses arasındaki mesafeyi ayır: adım mı atlama mı, dar mı geniş mi?',
      adimlar: [
        { id: 'K3-1', ad: 'Dar mı Geniş mi', tur: 'dar_genis', ayar: { dar: [1, 2], genis: [7, 12] } },
        { id: 'K3-2', ad: 'Adım mı Atlama mı', tur: 'dar_genis', ayar: { dar: [1, 2], genis: [3, 5], etiket: ['Adım', 'Atlama'] } },
        { id: 'K3-3', ad: 'Orta Aralıklar', tur: 'dar_genis', ayar: { dar: [3, 4], genis: [7, 9] } },
        { id: 'K3-4', ad: 'İnce Ayrım', tur: 'dar_genis', ayar: { dar: [3, 4], genis: [5, 6] } },
        { id: 'K3-5', ad: 'Aralık Kontrolü', tur: 'dar_genis', ayar: { dar: [1, 4], genis: [5, 12] } }
      ] },
    { ad: 'Ritmik Kulak',
      aciklama: 'Atölyenin kalbi: kalıpları sesten tanı — göz yok, yalnız kulak.',
      adimlar: [
        { id: 'K4-1', ad: 'Ritim Aynı mı Farklı mı', tur: 'ritim_ayni', ayar: { uzunluk: 4 } },
        { id: 'K4-2', ad: 'Kaç Vuruş Duydun', tur: 'vurus_say', ayar: { min: 2, maks: 5 } },
        { id: 'K4-3', ad: 'Hangisi Daha Uzun', tur: 'uzun_nota', ayar: {} },
        { id: 'K4-4', ad: 'Kalıbı Bul', tur: 'kalip_bul', ayar: { uzunluk: 4 } },
        { id: 'K4-5', ad: 'Ritmik Kontrol', tur: 'ritim_ayni', ayar: { uzunluk: 6 } }
      ] },
    { ad: 'Renk ve Doku',
      aciklama: 'Sesin dokusunu duy: tek mi çift mi, parlak mı yumuşak mı, uyumlu mu sürtünmeli mi?',
      adimlar: [
        { id: 'K5-1', ad: 'Tek mi Çift mi', tur: 'tek_cift', ayar: {} },
        { id: 'K5-2', ad: 'Parlak mı Yumuşak mı', tur: 'parlak_yumusak', ayar: {} },
        { id: 'K5-3', ad: 'Uyumlu mu Sürtünmeli mi', tur: 'uyum', ayar: {} },
        { id: 'K5-4', ad: 'Tını Aynı mı', tur: 'tini_ayni', ayar: {} },
        { id: 'K5-5', ad: 'Final Kontrolü', tur: 'karisik', ayar: {} }
      ] }
  ];

  /* Orta oktav çevresi: çocuk kulağına rahat bant */
  var TABAN_MIN = 55;   // G3 ~196 Hz
  var TABAN_MAX = 76;   // E5 ~659 Hz

  function taban(r) { return TABAN_MIN + Math.floor(r() * (TABAN_MAX - TABAN_MIN - 12)); }
  function aralikta(r, cift) { return cift[0] + Math.floor(r() * (cift[1] - cift[0] + 1)); }

  /* Iki notalik cift uretimi — iki birim test iki ayri kusuru yakaladi:
     (1) yon onceden secilip taban serbest birakilinca buyuk araliklar bandi
         deliyordu (139 Hz olculdu — cocuk kulagi icin fazla pes);
     (2) "sigarsa yukari" duzeltmesi ise YON YANLILIGI uretti (%75 tiz —
         dugme ezberlenebilir olurdu).
     Dogru sira: ONCE yon yazi-tura ile secilir, SONRA baslangic o yonun
     sigabildigi alt araliktan cekilir. f <= 12 oldugu icin aralik hep dolu. */
  function ciftUret(r, f) {
    var yukari = r() < 0.5;
    var lo = yukari ? TABAN_MIN : TABAN_MIN + f;
    var hi = yukari ? TABAN_MAX - f : TABAN_MAX;
    var m1 = lo + Math.floor(r() * (hi - lo + 1));
    return { m1: m1, m2: yukari ? m1 + f : m1 - f, yukari: yukari };
  }

  /* Her üretici şunu döndürür:
     { sesler: [...], secenekler: ['A','B'], dogru: 0-idx, aciklama }
     sesler öğeleri: {tip:'nota', midi, sureMs} | {tip:'ritim', kalip:[0/1...], bpm}
                     | {tip:'cift', midiler:[..]} | {tip:'bosluk', sureMs} */
  var URETICILER = {
    ayni_farkli: function (r, ayar) {
      var farkli = r() < 0.5;
      var m1, m2;
      if (farkli) {
        var c = ciftUret(r, aralikta(r, [ayar.minFark, ayar.maxFark]));
        m1 = c.m1; m2 = c.m2;
      } else {
        m1 = m2 = TABAN_MIN + Math.floor(r() * (TABAN_MAX - TABAN_MIN + 1));
      }
      return { sesler: [{ tip: 'nota', midi: m1, sureMs: 700 }, { tip: 'bosluk', sureMs: 350 },
                        { tip: 'nota', midi: m2, sureMs: 700 }],
               secenekler: ['Aynı', 'Farklı'], dogru: farkli ? 1 : 0,
               aciklama: farkli ? 'İkinci ses ' + Math.abs(m2 - m1) + ' yarım ses '
                                  + (m2 > m1 ? 'tizdeydi.' : 'pesteydi.') : 'İki ses birebir aynıydı.' };
    },
    tiz_pes: function (r, ayar) {
      var f = aralikta(r, [ayar.minFark, ayar.maxFark]);
      var c = ciftUret(r, f);
      var m1 = c.m1, m2 = c.m2, ikinciTiz = c.yukari;
      return { sesler: [{ tip: 'nota', midi: m1, sureMs: 700 }, { tip: 'bosluk', sureMs: 350 },
                        { tip: 'nota', midi: m2, sureMs: 700 }],
               secenekler: ['İkinci ses TİZ', 'İkinci ses PES'], dogru: ikinciTiz ? 0 : 1,
               aciklama: 'Fark ' + f + ' yarım sesti.' };
    },
    yon: function (r, ayar) {
      var f = aralikta(r, ayar.adim);
      var c = ciftUret(r, f);
      var m1 = c.m1, m2 = c.m2, yukselen = c.yukari;
      return { sesler: [{ tip: 'nota', midi: m1, sureMs: 550 }, { tip: 'bosluk', sureMs: 180 },
                        { tip: 'nota', midi: m2, sureMs: 550 }],
               secenekler: ['Yükseliyor', 'Alçalıyor'], dogru: yukselen ? 0 : 1,
               aciklama: yukselen ? 'Ezgi yukarı gitti.' : 'Ezgi aşağı indi.' };
    },
    kontur: function (r, ayar) {
      var desenler = [[1, 1], [-1, -1], [1, -1], [-1, 1]];
      var etiketler = ['Hep Yukarı', 'Hep Aşağı', 'Yukarı–Aşağı', 'Aşağı–Yukarı'];
      var idx = Math.floor(r() * 4);
      var m = 62 + Math.floor(r() * 6);   // desen ±2 adım: 62-68 başlangıcı bandı korur
      var sesler = [{ tip: 'nota', midi: m, sureMs: 480 }];
      desenler[idx].forEach(function (yon) {
        m += yon * aralikta(r, ayar.adim);
        sesler.push({ tip: 'bosluk', sureMs: 140 }, { tip: 'nota', midi: m, sureMs: 480 });
      });
      return { sesler: sesler, secenekler: etiketler, dogru: idx,
               aciklama: 'Çizgi: ' + etiketler[idx] + '.' };
    },
    ezgi_ayni: function (r, ayar) {
      var kok = 61 + Math.floor(r() * 7);   // adımlar ±4 + değişim ±2 bantta kalsın
      var ezgi = [kok];
      for (var i = 1; i < ayar.notaSayisi; i++) {
        ezgi.push(ezgi[i - 1] + (r() < 0.5 ? 1 : -1) * aralikta(r, [1, 4]));
      }
      var farkli = r() < 0.5;
      var ikinci = ezgi.slice();
      if (farkli) {
        var hangi = 1 + Math.floor(r() * (ezgi.length - 1));
        ikinci[hangi] += (r() < 0.5 ? 1 : -1) * ayar.degisim;
        if (ikinci[hangi] === ezgi[hangi]) { ikinci[hangi] += ayar.degisim; }
      }
      var sesler = [];
      ezgi.forEach(function (n) { sesler.push({ tip: 'nota', midi: n, sureMs: 420 }, { tip: 'bosluk', sureMs: 90 }); });
      sesler.push({ tip: 'bosluk', sureMs: 420 });
      ikinci.forEach(function (n) { sesler.push({ tip: 'nota', midi: n, sureMs: 420 }, { tip: 'bosluk', sureMs: 90 }); });
      return { sesler: sesler, secenekler: ['Aynı', 'Farklı'], dogru: farkli ? 1 : 0,
               aciklama: farkli ? 'İkinci ezgide bir nota değişti.' : 'İki ezgi birebir aynıydı.' };
    },
    dar_genis: function (r, ayar) {
      var genisMi = r() < 0.5;
      var f = aralikta(r, genisMi ? ayar.genis : ayar.dar);
      var c = ciftUret(r, f);
      var m1 = c.m1, m2 = c.m2;
      var et = ayar.etiket || ['Dar', 'Geniş'];
      return { sesler: [{ tip: 'nota', midi: m1, sureMs: 600 }, { tip: 'bosluk', sureMs: 250 },
                        { tip: 'nota', midi: m2, sureMs: 600 }],
               secenekler: et, dogru: genisMi ? 1 : 0,
               aciklama: 'Aralık ' + f + ' yarım ses (' + (genisMi ? et[1] : et[0]).toLowerCase() + ').' };
    },
    ritim_ayni: function (r, ayar) {
      function kalipUret() {
        var k = [1];
        for (var i = 1; i < ayar.uzunluk * 2; i++) { k.push(r() < 0.55 ? 1 : 0); }
        return k;
      }
      var k1 = kalipUret();
      var farkli = r() < 0.5;
      var k2 = k1.slice();
      if (farkli) {
        var yer = 1 + Math.floor(r() * (k2.length - 1));
        k2[yer] = k2[yer] ? 0 : 1;
      }
      return { sesler: [{ tip: 'ritim', kalip: k1, bpm: 92 }, { tip: 'bosluk', sureMs: 650 },
                        { tip: 'ritim', kalip: k2, bpm: 92 }],
               secenekler: ['Aynı', 'Farklı'], dogru: farkli ? 1 : 0,
               aciklama: farkli ? 'İkinci kalıpta bir vuruş değişti.' : 'İki kalıp birebir aynıydı.' };
    },
    vurus_say: function (r, ayar) {
      var adet = aralikta(r, [ayar.min, ayar.maks]);
      var kalip = [];
      for (var i = 0; i < adet; i++) { kalip.push(1); if (r() < 0.4) { kalip.push(0); } }
      var secenekler = [];
      for (var s = ayar.min; s <= ayar.maks; s++) { secenekler.push(String(s)); }
      return { sesler: [{ tip: 'ritim', kalip: kalip, bpm: 84 }],
               secenekler: secenekler, dogru: adet - ayar.min,
               aciklama: adet + ' vuruş vardı.' };
    },
    uzun_nota: function (r) {
      var ilkUzun = r() < 0.5;
      var kisa = 350, uzun = 900;
      var m = taban(r) + 5;
      return { sesler: [{ tip: 'nota', midi: m, sureMs: ilkUzun ? uzun : kisa },
                        { tip: 'bosluk', sureMs: 400 },
                        { tip: 'nota', midi: m, sureMs: ilkUzun ? kisa : uzun }],
               secenekler: ['Birinci', 'İkinci'], dogru: ilkUzun ? 0 : 1,
               aciklama: (ilkUzun ? 'Birinci' : 'İkinci') + ' ses daha uzundu.' };
    },
    kalip_bul: function (r, ayar) {
      function kalipUret() {
        var k = [1];
        for (var i = 1; i < ayar.uzunluk * 2; i++) { k.push(r() < 0.55 ? 1 : 0); }
        return k;
      }
      var dogruKalip = kalipUret();
      var digerleri = [];
      while (digerleri.length < 2) {
        var aday = dogruKalip.slice();
        var yer = 1 + Math.floor(r() * (aday.length - 1));
        aday[yer] = aday[yer] ? 0 : 1;
        var anahtar = aday.join('');
        if (anahtar !== dogruKalip.join('')
            && !digerleri.some(function (d) { return d.join('') === anahtar; })) {
          digerleri.push(aday);
        }
      }
      var hepsi = [dogruKalip].concat(digerleri);
      /* karıştır (deterministik) */
      for (var i = hepsi.length - 1; i > 0; i--) {
        var j = Math.floor(r() * (i + 1));
        var t = hepsi[i]; hepsi[i] = hepsi[j]; hepsi[j] = t;
      }
      var dogruIdx = hepsi.findIndex(function (k) { return k.join('') === dogruKalip.join(''); });
      return { sesler: [{ tip: 'ritim', kalip: dogruKalip, bpm: 88 }],
               secenekler: ['A', 'B', 'C'], dogru: dogruIdx,
               secenekSesleri: hepsi.map(function (k) { return { tip: 'ritim', kalip: k, bpm: 88 }; }),
               aciklama: 'Duyduğun kalıp ' + ['A', 'B', 'C'][dogruIdx] + ' seçeneğiydi.' };
    },
    tek_cift: function (r) {
      var ciftMi = r() < 0.5;
      var m = taban(r) + 4;
      var ses = ciftMi ? { tip: 'cift', midiler: [m, m + 7], sureMs: 900 }
                       : { tip: 'nota', midi: m, sureMs: 900 };
      return { sesler: [ses], secenekler: ['Tek ses', 'İki ses birlikte'], dogru: ciftMi ? 1 : 0,
               aciklama: ciftMi ? 'İki ses birlikte tınladı.' : 'Tek ses vardı.' };
    },
    parlak_yumusak: function (r) {
      /* Majör 3'lü "parlak", minör 3'lü "yumuşak" duyulur — teori adı zorunlu
         değil; açıklamada ipucu olarak verilir. */
      var majorMu = r() < 0.5;
      var m = taban(r) + 4;
      return { sesler: [{ tip: 'cift', midiler: [m, m + (majorMu ? 4 : 3)], sureMs: 1000 }],
               secenekler: ['Parlak', 'Yumuşak'], dogru: majorMu ? 0 : 1,
               aciklama: majorMu ? 'Parlak (büyük üçlü) duyuldu.' : 'Yumuşak (küçük üçlü) duyuldu.' };
    },
    uyum: function (r) {
      var uyumluMu = r() < 0.5;
      var m = taban(r) + 4;
      var aralik = uyumluMu ? [7, 12, 5][Math.floor(r() * 3)] : [1, 2, 6][Math.floor(r() * 3)];
      return { sesler: [{ tip: 'cift', midiler: [m, m + aralik], sureMs: 1000 }],
               secenekler: ['Uyumlu', 'Sürtünmeli'], dogru: uyumluMu ? 0 : 1,
               aciklama: 'Aralık ' + aralik + ' yarım ses — ' + (uyumluMu ? 'uyumlu.' : 'sürtünmeli.') };
    },
    tini_ayni: function (r) {
      var tinilar = ['sine', 'triangle', 'square'];
      var t1 = tinilar[Math.floor(r() * 3)];
      var farkli = r() < 0.5;
      var t2 = t1;
      if (farkli) {
        var kalan = tinilar.filter(function (x) { return x !== t1; });
        t2 = kalan[Math.floor(r() * kalan.length)];
      }
      var m = taban(r) + 5;
      return { sesler: [{ tip: 'nota', midi: m, sureMs: 650, tini: t1 }, { tip: 'bosluk', sureMs: 300 },
                        { tip: 'nota', midi: m, sureMs: 650, tini: t2 }],
               secenekler: ['Aynı tını', 'Farklı tını'], dogru: farkli ? 1 : 0,
               aciklama: farkli ? 'Aynı nota, iki farklı tını.' : 'Aynı nota, aynı tını.' };
    },
    karisik: function (r, ayar, derinlik) {
      /* Finalde önceki türlerden karışık soru gelir (kalip_bul hariç: kendi
         seçenek sesleri var, karışıma girmesi UI'ı dallandırır). */
      var havuz = ['tiz_pes', 'yon', 'dar_genis', 'ritim_ayni', 'parlak_yumusak', 'uyum'];
      var tur = havuz[Math.floor(r() * havuz.length)];
      var ayarlar = {
        tiz_pes: { minFark: 1, maxFark: 4 },
        yon: { notaSayisi: 2, adim: [1, 4] },
        dar_genis: { dar: [1, 4], genis: [5, 12] },
        ritim_ayni: { uzunluk: 5 },
        parlak_yumusak: {},
        uyum: {}
      };
      return URETICILER[tur](r, ayarlar[tur]);
    }
  };

  /** Bir adımın 10 sorusunu üretir (tohum → deterministik). */
  function sorulariUret(adimId, tohum) {
    var adim = null;
    BOLUMLER.forEach(function (b) {
      b.adimlar.forEach(function (a) { if (a.id === adimId) { adim = a; } });
    });
    if (!adim) { return null; }
    var r = rastgele((tohum >>> 0) ^ 0x9e3779b9);
    var sorular = [];
    for (var i = 0; i < SORU_SAYISI; i++) {
      sorular.push(URETICILER[adim.tur](r, adim.ayar || {}));
    }
    return { adim: adim, sorular: sorular };
  }

  /** Düz adım listesi (yol görünümü için). */
  function duzAdimlar() {
    var duz = [];
    BOLUMLER.forEach(function (b) { duz = duz.concat(b.adimlar); });
    return duz;
  }

  return {
    SURUM: SURUM,
    SORU_SAYISI: SORU_SAYISI,
    BOLUMLER: BOLUMLER,
    midiHz: midiHz,
    rastgele: rastgele,
    sorulariUret: sorulariUret,
    duzAdimlar: duzAdimlar
  };
}));
