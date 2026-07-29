/* ================================================================
   Ritim Okuma Adaptif Öğrenme Çekirdeği
   - Örnek bazlı ustalık ve tekrar aralığı
   - Oturum içi tekrar kuyruğu
   - Ritim türü bazlı zayıflık analizi
   - Tarayıcı ve Node.js için ortak, deterministik API
   ================================================================ */
(function (kok, fabrika) {
  'use strict';
  var api = fabrika();
  if (typeof module === 'object' && module.exports) { module.exports = api; }
  if (kok) { kok.RitimOgrenme = api; }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  var SURUM = 1;
  var GUN_MS = 24 * 60 * 60 * 1000;

  function sinirla(sayi, alt, ust) {
    sayi = Number(sayi);
    return Math.max(alt, Math.min(ust, Number.isFinite(sayi) ? sayi : alt));
  }

  function sayi(deger, varsayilan) {
    deger = Number(deger);
    return Number.isFinite(deger) ? deger : (varsayilan || 0);
  }

  function bosDurum() {
    return {
      surum: SURUM,
      adim: 0,
      toplamDeneme: 0,
      basariSerisi: 0,
      enIyiSeri: 0,
      ornekler: {},
      tipler: {}
    };
  }

  function kayitNormalle(kayit) {
    kayit = kayit && typeof kayit === 'object' ? kayit : {};
    return {
      deneme: Math.max(0, Math.round(sayi(kayit.deneme))),
      sonSkor: Math.round(sinirla(kayit.sonSkor, 0, 100)),
      enIyiSkor: Math.round(sinirla(kayit.enIyiSkor, 0, 100)),
      hareketliSkor: sinirla(kayit.hareketliSkor, 0, 100),
      ustalik: Math.round(sinirla(kayit.ustalik, 0, 100)),
      temizSeri: Math.max(0, Math.round(sayi(kayit.temizSeri))),
      sonDeneme: Math.max(0, sayi(kayit.sonDeneme)),
      tekrarZamani: Math.max(0, sayi(kayit.tekrarZamani)),
      tekrarAdimi: Math.max(0, Math.round(sayi(kayit.tekrarAdimi))),
      sonAdim: Math.max(0, Math.round(sayi(kayit.sonAdim))),
      ortMutlakMs: Math.max(0, Math.round(sayi(kayit.ortMutlakMs))),
      kacirilan: Math.max(0, Math.round(sayi(kayit.kacirilan))),
      ekstra: Math.max(0, Math.round(sayi(kayit.ekstra)))
    };
  }

  function tipNormalle(kayit) {
    kayit = kayit && typeof kayit === 'object' ? kayit : {};
    return {
      deneme: Math.max(0, Math.round(sayi(kayit.deneme))),
      toplam: Math.max(0, Math.round(sayi(kayit.toplam))),
      dogru: Math.max(0, Math.round(sayi(kayit.dogru))),
      hareketliSkor: sinirla(kayit.hareketliSkor, 0, 100),
      ustalik: Math.round(sinirla(kayit.ustalik, 0, 100)),
      sonDeneme: Math.max(0, sayi(kayit.sonDeneme))
    };
  }

  function normalle(ham) {
    ham = ham && typeof ham === 'object' ? ham : {};
    var sonuc = bosDurum();
    sonuc.adim = Math.max(0, Math.round(sayi(ham.adim)));
    sonuc.toplamDeneme = Math.max(0, Math.round(sayi(ham.toplamDeneme)));
    sonuc.basariSerisi = Math.max(0, Math.round(sayi(ham.basariSerisi)));
    sonuc.enIyiSeri = Math.max(sonuc.basariSerisi, Math.round(sayi(ham.enIyiSeri)));

    if (ham.ornekler && typeof ham.ornekler === 'object') {
      Object.keys(ham.ornekler).forEach(function (id) {
        if (id && id.length <= 80) { sonuc.ornekler[id] = kayitNormalle(ham.ornekler[id]); }
      });
    }
    if (ham.tipler && typeof ham.tipler === 'object') {
      Object.keys(ham.tipler).forEach(function (kod) {
        if (kod && kod.length <= 40) { sonuc.tipler[kod] = tipNormalle(ham.tipler[kod]); }
      });
    }
    return sonuc;
  }

  function guvenCarpani(deneme) {
    if (deneme <= 1) { return 0.85; }
    if (deneme === 2) { return 0.95; }
    return 1;
  }

  function yeniUstalik(eskiHareketli, eskiDeneme, skor) {
    var deneme = eskiDeneme + 1;
    var eskiAgirlik = skor < 70 ? 0.45 : 0.65;
    var hareketli = eskiDeneme
      ? eskiHareketli * eskiAgirlik + skor * (1 - eskiAgirlik)
      : skor;
    return {
      hareketliSkor: hareketli,
      ustalik: Math.round(hareketli * guvenCarpani(deneme))
    };
  }

  function tekrarPlani(skor, temizSeri, adim, simdi) {
    if (skor < 70) {
      return { tekrarAdimi: adim + 2, tekrarZamani: simdi + 10 * 60 * 1000 };
    }
    if (skor < 85) {
      return { tekrarAdimi: adim + 4, tekrarZamani: simdi + 12 * 60 * 60 * 1000 };
    }
    var araliklar = [1, 3, 7, 14, 30];
    var gun = araliklar[Math.min(araliklar.length - 1, Math.max(0, temizSeri - 1))];
    return { tekrarAdimi: 0, tekrarZamani: simdi + gun * GUN_MS };
  }

  function denemeyiIsle(hamDurum, sonuc, simdi) {
    var durum = normalle(hamDurum);
    sonuc = sonuc && typeof sonuc === 'object' ? sonuc : {};
    var id = String(sonuc.id || '');
    if (!id) { throw new Error('Örnek kimliği gerekli.'); }
    simdi = Math.max(0, sayi(simdi, Date.now()));
    var skor = Math.round(sinirla(sonuc.skor, 0, 100));
    var onceki = durum.ornekler[id] || kayitNormalle({});
    var deneme = onceki.deneme + 1;
    var hesap = yeniUstalik(onceki.hareketliSkor, onceki.deneme, skor);
    var temizSeri = skor >= 85 ? onceki.temizSeri + 1 : 0;

    durum.adim++;
    durum.toplamDeneme++;
    durum.basariSerisi = skor >= 70 ? durum.basariSerisi + 1 : 0;
    durum.enIyiSeri = Math.max(durum.enIyiSeri, durum.basariSerisi);

    var plan = tekrarPlani(skor, temizSeri, durum.adim, simdi);
    durum.ornekler[id] = {
      deneme: deneme,
      sonSkor: skor,
      enIyiSkor: Math.max(onceki.enIyiSkor, skor),
      hareketliSkor: hesap.hareketliSkor,
      ustalik: hesap.ustalik,
      temizSeri: temizSeri,
      sonDeneme: simdi,
      tekrarZamani: plan.tekrarZamani,
      tekrarAdimi: plan.tekrarAdimi,
      sonAdim: durum.adim,
      ortMutlakMs: Math.max(0, Math.round(sayi(sonuc.ortMutlakMs))),
      kacirilan: Math.max(0, Math.round(sayi(sonuc.kacirilan))),
      ekstra: Math.max(0, Math.round(sayi(sonuc.ekstra)))
    };

    var tipSonuclari = sonuc.tipSonuclari && typeof sonuc.tipSonuclari === 'object'
      ? sonuc.tipSonuclari : {};
    Object.keys(tipSonuclari).forEach(function (kod) {
      var olcum = tipSonuclari[kod] || {};
      var toplam = Math.max(0, Math.round(sayi(olcum.toplam)));
      if (!toplam) { return; }
      var dogru = Math.round(sinirla(olcum.dogru, 0, toplam));
      var tipSkoru = 100 * dogru / toplam;
      var eski = durum.tipler[kod] || tipNormalle({});
      var tipHesap = yeniUstalik(eski.hareketliSkor, eski.deneme, tipSkoru);
      durum.tipler[kod] = {
        deneme: eski.deneme + 1,
        toplam: eski.toplam + toplam,
        dogru: eski.dogru + dogru,
        hareketliSkor: tipHesap.hareketliSkor,
        ustalik: tipHesap.ustalik,
        sonDeneme: simdi
      };
    });
    return durum;
  }

  function tamamlananlariAktar(hamDurum, tamamlananlar, simdi) {
    var durum = normalle(hamDurum);
    if (!Array.isArray(tamamlananlar)) { return durum; }
    simdi = Math.max(0, sayi(simdi, Date.now()));
    tamamlananlar.forEach(function (id, sira) {
      id = String(id || '');
      if (!id || durum.ornekler[id]) { return; }
      durum.ornekler[id] = {
        deneme: 1,
        sonSkor: 75,
        enIyiSkor: 75,
        hareketliSkor: 75,
        ustalik: 64,
        temizSeri: 0,
        sonDeneme: simdi - (tamamlananlar.length - sira) * 1000,
        tekrarZamani: simdi,
        tekrarAdimi: durum.adim,
        sonAdim: 0,
        ortMutlakMs: 0,
        kacirilan: 0,
        ekstra: 0
      };
    });
    return durum;
  }

  function tekrarGerekli(kayit, durum, simdi) {
    if (!kayit || !kayit.deneme) { return false; }
    return (kayit.tekrarAdimi > 0 && kayit.tekrarAdimi <= durum.adim)
      || (kayit.tekrarZamani > 0 && kayit.tekrarZamani <= simdi);
  }

  function ozet(katalog, hamDurum, simdi) {
    var durum = normalle(hamDurum);
    katalog = Array.isArray(katalog) ? katalog : [];
    simdi = Math.max(0, sayi(simdi, Date.now()));
    var denenen = 0;
    var ustalasilan = 0;
    var tekrar = 0;
    var toplamUstalik = 0;
    var calisilanUstalik = 0;

    katalog.forEach(function (ornek) {
      var kayit = durum.ornekler[ornek.id];
      if (!kayit || !kayit.deneme) { return; }
      denenen++;
      toplamUstalik += kayit.ustalik;
      calisilanUstalik += kayit.ustalik;
      if (kayit.deneme >= 2 && kayit.ustalik >= 85) { ustalasilan++; }
      if (tekrarGerekli(kayit, durum, simdi)) { tekrar++; }
    });
    return {
      toplam: katalog.length,
      denenen: denenen,
      ustalasilan: ustalasilan,
      tekrar: tekrar,
      kursYuzdesi: katalog.length ? Math.round(toplamUstalik / katalog.length) : 0,
      calisilanUstaligi: denenen ? Math.round(calisilanUstalik / denenen) : 0,
      basariSerisi: durum.basariSerisi,
      enIyiSeri: durum.enIyiSeri,
      toplamDeneme: durum.toplamDeneme
    };
  }

  function zayifTipler(hamDurum, sinir) {
    var durum = normalle(hamDurum);
    sinir = Math.max(1, Math.round(sayi(sinir, 3)));
    return Object.keys(durum.tipler)
      .map(function (kod) {
        var tip = durum.tipler[kod];
        return {
          kod: kod,
          ustalik: tip.ustalik,
          deneme: tip.deneme,
          dogruluk: tip.toplam ? Math.round(100 * tip.dogru / tip.toplam) : 0
        };
      })
      .filter(function (x) { return x.deneme > 0 && x.ustalik < 85; })
      .sort(function (a, b) {
        return a.ustalik - b.ustalik || b.deneme - a.deneme || a.kod.localeCompare(b.kod);
      })
      .slice(0, sinir);
  }

  function sonrakiniSec(katalog, hamDurum, mevcutId, simdi) {
    var durum = normalle(hamDurum);
    katalog = Array.isArray(katalog) ? katalog : [];
    simdi = Math.max(0, sayi(simdi, Date.now()));
    if (!katalog.length) { return null; }
    var mevcutIndeks = Math.max(0, katalog.findIndex(function (o) { return o.id === mevcutId; }));
    var mevcutDers = Math.max(0, sayi(katalog[mevcutIndeks].dersNo));

    var tekrarlar = katalog.filter(function (ornek) {
      var kayit = durum.ornekler[ornek.id];
      return ornek.id !== mevcutId && tekrarGerekli(kayit, durum, simdi);
    }).sort(function (a, b) {
      var ka = durum.ornekler[a.id];
      var kb = durum.ornekler[b.id];
      return ka.ustalik - kb.ustalik || ka.sonDeneme - kb.sonDeneme;
    });
    if (tekrarlar.length) {
      return {
        indeks: katalog.indexOf(tekrarlar[0]),
        id: tekrarlar[0].id,
        neden: 'Tekrar zamanı geldi',
        tur: 'tekrar'
      };
    }

    function siraliIlk(filtre) {
      for (var ofset = 1; ofset <= katalog.length; ofset++) {
        var idx = (mevcutIndeks + ofset) % katalog.length;
        if (filtre(katalog[idx], idx)) { return idx; }
      }
      return -1;
    }

    var ayniDersteYeni = siraliIlk(function (ornek) {
      return ornek.dersNo === mevcutDers && !durum.ornekler[ornek.id];
    });
    if (ayniDersteYeni >= 0) {
      return {
        indeks: ayniDersteYeni,
        id: katalog[ayniDersteYeni].id,
        neden: 'Dersi sırayla ilerletir',
        tur: 'yeni'
      };
    }

    var zayiflar = katalog.filter(function (ornek) {
      var kayit = durum.ornekler[ornek.id];
      return ornek.id !== mevcutId
        && ornek.dersNo <= mevcutDers
        && kayit && kayit.deneme
        && kayit.ustalik < 85
        && kayit.sonAdim <= durum.adim - 2;
    }).sort(function (a, b) {
      var ka = durum.ornekler[a.id];
      var kb = durum.ornekler[b.id];
      return ka.ustalik - kb.ustalik || ka.sonDeneme - kb.sonDeneme;
    });
    if (zayiflar.length) {
      return {
        indeks: katalog.indexOf(zayiflar[0]),
        id: zayiflar[0].id,
        neden: 'Zayıf örneği güçlendirir',
        tur: 'zayif'
      };
    }

    var yeni = siraliIlk(function (ornek) { return !durum.ornekler[ornek.id]; });
    if (yeni >= 0) {
      return {
        indeks: yeni,
        id: katalog[yeni].id,
        neden: katalog[yeni].dersNo > mevcutDers ? 'Yeni derse geçer' : 'Yeni örnek',
        tur: 'yeni'
      };
    }

    var enZayif = katalog.filter(function (ornek) { return ornek.id !== mevcutId; })
      .sort(function (a, b) {
        var ka = durum.ornekler[a.id] || kayitNormalle({});
        var kb = durum.ornekler[b.id] || kayitNormalle({});
        return ka.ustalik - kb.ustalik || ka.sonDeneme - kb.sonDeneme;
      })[0];
    var secilenIndeks = enZayif ? katalog.indexOf(enZayif) : (mevcutIndeks + 1) % katalog.length;
    return {
      indeks: secilenIndeks,
      id: katalog[secilenIndeks].id,
      neden: 'En düşük ustalığı çalıştırır',
      tur: 'zayif'
    };
  }

  return {
    SURUM: SURUM,
    GUN_MS: GUN_MS,
    bosDurum: bosDurum,
    normalle: normalle,
    denemeyiIsle: denemeyiIsle,
    tamamlananlariAktar: tamamlananlariAktar,
    ozet: ozet,
    zayifTipler: zayifTipler,
    sonrakiniSec: sonrakiniSec
  };
});
