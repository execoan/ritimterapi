/* =======================================================
   Öğrenci Ev Sayfası — etkileşimli görevler
   - Metronomlu süre görevi (data-gorev="metronom")
   - Vuruş Tutturma mini (data-gorev="vurus")
   - Ritim Okuma (data-gorev="ritim", ritim-okuma.js widget'ı)
   Tamamlanınca ilgili gizli form doldurulup gönderilir.
   ======================================================= */
(function () {
  'use strict';

  var zaman = window.RitimZamanlama;
  if (!zaman) { throw new Error('Ortak zamanlama çekirdeği yüklenemedi.'); }

  var ctx = null;
  function sesHazirla() {
    if (!ctx) {
      var AudioCtor = window.AudioContext || window.webkitAudioContext;
      try { ctx = new AudioCtor({ latencyHint: 'interactive' }); }
      catch (e) { ctx = new AudioCtor(); }
    }
    if (ctx.state === 'suspended') { ctx.resume(); }
    return ctx;
  }
  function klik(zaman, aksan) {
    var o = ctx.createOscillator();
    var g = ctx.createGain();
    o.type = 'sine';
    o.frequency.setValueAtTime(aksan ? 980 : 700, zaman);
    o.frequency.exponentialRampToValueAtTime(aksan ? 620 : 460, zaman + 0.05);
    g.gain.setValueAtTime(aksan ? 0.6 : 0.42, zaman);
    g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.07);
    o.connect(g).connect(ctx.destination);
    o.start(zaman); o.stop(zaman + 0.08);
  }

  /* ---------- Ortak cihaz kalibrasyonu ---------- */
  var evKalibrator = null;

  /*
   * Sonuç formuna kararlılık (asenkroni SD) ve kalibrasyon kalitesi eklenir.
   * Skor sabit kaymayı içinde taşır, SD taşımaz; dönem karşılaştırması SD'den
   * okunur. Kalite kodu sonradan "yalnız güvenilir ölçümler" süzgeci içindir.
   */
  function olcumEkiYaz(form, sapmalar, hazirSd) {
    var sdAlan = form.querySelector('[name="sd_ms"]');
    var kaliteAlan = form.querySelector('[name="kalite"]');
    if (sdAlan) {
      var sd = Number.isFinite(hazirSd)
        ? hazirSd
        : ((sapmalar && sapmalar.length >= 2) ? Math.round(zaman.standartSapma(sapmalar)) : null);
      sdAlan.value = (sd === null || sd === undefined) ? '' : sd;
    }
    if (kaliteAlan) { kaliteAlan.value = (ctx ? zaman.kaliteDurumu(ctx).kod : '') || ''; }
  }

  function evKaliteGuncelle(ozelMesaj) {
    var panel = document.getElementById('evZamanKalite');
    if (!panel) { return zaman.kaliteDurumu(ctx); }
    var durum = zaman.kaliteDurumu(ctx);
    var kal = durum.kalibrasyon;
    var rozet = document.getElementById('evZkRozet');
    rozet.className = 'ev-zaman-rozet ' + durum.kod;
    rozet.textContent = durum.etiket;
    var ozet = ozelMesaj;
    if (!ozet) {
      if (!kal.yapildi) {
        ozet = 'Başlamadan önce 4 hazırlık ve 12 ölçüm vuruşuyla cihazını ayarla.';
      } else if (kal.tur === 'elle' || kal.ornek < 8) {
        ozet = 'Elle telafi aktif. Daha güvenilir sonuç için 12 vuruşluk otomatik kalibrasyonu yap.';
      } else {
        ozet = (kal.telafiMs > 0 ? '+' : '') + Math.round(kal.telafiMs) + ' ms telafi'
          + ' · dağılım ±' + Math.round(kal.dagilimMs) + ' ms'
          + (durum.tarayiciGecikmesiMs ? ' · tarayıcı çıkışı ' + durum.tarayiciGecikmesiMs + ' ms' : '');
      }
    }
    document.getElementById('evZkOzet').textContent = ozet;
    return durum;
  }

  function evKalibrasyonIptal() {
    if (evKalibrator) { evKalibrator.iptal(); }
    evKalibrator = null;
    var sahne = document.getElementById('evZkSahne');
    if (sahne) { sahne.hidden = true; }
    var btn = document.getElementById('evZkKalibre');
    if (btn) { btn.disabled = false; }
  }

  function evKalibrasyonBaslat() {
    sesHazirla();
    var sahne = document.getElementById('evZkSahne');
    sahne.hidden = false;
    document.getElementById('evZkKalibre').disabled = true;
    evKalibrator = zaman.kalibrasyonBaslat({
      ctx: ctx,
      klik: klik,
      durum: function (veri) {
        document.getElementById('evZkSayac').textContent = veri.faz === 'hazirlik'
          ? veri.kalan + ' · sonra kliklerde vur'
          : 'ŞİMDİ · her klikte vur';
        document.getElementById('evZkIlerleme').textContent = veri.ilerleme + '/' + veri.toplam;
      },
      tamam: function (sonuc) {
        evKalibrator = null;
        sahne.hidden = true;
        document.getElementById('evZkKalibre').disabled = false;
        if (!sonuc.basarili) {
          evKaliteGuncelle('En az 8 klike vurulamadı. Kalibrasyonu tekrar dene.');
          return;
        }
        evKaliteGuncelle('Hazır: ' + (sonuc.telafiMs > 0 ? '+' : '') + sonuc.telafiMs
          + ' ms telafi · dağılım ±' + sonuc.dagilimMs + ' ms.');
      }
    });
  }

  function olcumHazirMi(durumEl) {
    sesHazirla();
    var kalite = evKaliteGuncelle();
    if (kalite.kullanilabilir) { return true; }
    durumEl.textContent = 'Önce yukarıdaki “Cihaz zamanlama ayarı” bölümünden kalibre et.';
    var panel = document.getElementById('evZamanKalite');
    if (panel) {
      panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
      document.getElementById('evZkKalibre').focus();
    }
    return false;
  }

  var evZkKalibre = document.getElementById('evZkKalibre');
  if (evZkKalibre) {
    evZkKalibre.addEventListener('click', evKalibrasyonBaslat);
    document.getElementById('evZkIptal').addEventListener('click', evKalibrasyonIptal);
    document.getElementById('evZkSifirla').addEventListener('click', function () {
      evKalibrasyonIptal();
      zaman.kalibrasyonSifirla();
      evKaliteGuncelle('Ayar sıfırlandı. Puanlı çalışmadan önce yeniden kalibre et.');
    });
    document.getElementById('evZkPad').addEventListener('pointerdown', function (ev) {
      ev.preventDefault();
      if (evKalibrator && evKalibrator.tap(ev)) {
        this.classList.add('vurdum');
        var pad = this;
        setTimeout(function () { pad.classList.remove('vurdum'); }, 80);
      }
    });
    window.addEventListener('ritim-zamanlama-guncellendi', function () { evKaliteGuncelle(); });
    evKaliteGuncelle();
  }

  /* ---------- Metronomlu süre görevi ---------- */
  document.querySelectorAll('[data-gorev="metronom"]').forEach(function (kart) {
    var bpm = parseInt(kart.dataset.bpm, 10) || 66;
    var sureSn = (parseInt(kart.dataset.sure, 10) || 3) * 60;
    var btn = kart.querySelector('.ev-gorev-baslat');
    var sayac = kart.querySelector('.ev-sayac');
    var calisiyor = false;
    var zamanlayici = null;
    var sonraki = 0;
    var kalan = sureSn;

    function dur(tamamlandi) {
      clearInterval(zamanlayici);
      calisiyor = false;
      btn.textContent = '▶ Başlat';
      if (tamamlandi) {
        sayac.textContent = '🎉 Süre doldu — harikasın!';
        var form = kart.querySelector('form.ev-tamamla-form');
        if (form && !kart.dataset.bugunYapildi) { form.submit(); }
      } else {
        sayac.textContent = '';
      }
    }

    btn.addEventListener('click', function () {
      if (calisiyor) { dur(false); return; }
      sesHazirla();
      calisiyor = true;
      kalan = sureSn;
      btn.textContent = '⏸ Durdur';
      sonraki = ctx.currentTime + 0.2;
      var bitis = Date.now() + sureSn * 1000;
      zamanlayici = setInterval(function () {
        while (sonraki < ctx.currentTime + 0.12) {
          klik(sonraki, false);
          sonraki += 60 / bpm;
        }
        kalan = Math.max(0, Math.round((bitis - Date.now()) / 1000));
        sayac.textContent = '⏱ ' + Math.floor(kalan / 60) + ':' + String(kalan % 60).padStart(2, '0');
        if (kalan <= 0) { dur(true); }
      }, 25);
    });
  });

  /* ---------- Vuruş Tutturma mini (4 giriş + 8 sesli + 8 sessiz) ---------- */
  document.querySelectorAll('[data-gorev="vurus"]').forEach(function (kart) {
    var bpm = parseInt(kart.dataset.bpm, 10) || 72;
    var btn = kart.querySelector('.ev-gorev-baslat');
    var pad = kart.querySelector('.m-pad');
    var durum = kart.querySelector('.ev-sayac');
    var aktif = false;
    var vurusAcik = false;
    var vuruslar = [];
    var taplar = [];
    var zamanlayici = null;

    function tap(olay) {
      if (!aktif || !vurusAcik) { return; }
      taplar.push(zaman.olayZamani(ctx, olay));
      pad.classList.add('vurdum');
      setTimeout(function () { pad.classList.remove('vurdum'); }, 80);
    }
    pad.addEventListener('pointerdown', function (ev) { ev.preventDefault(); tap(ev); });
    kart.__tap = tap;
    kart.__aktifMi = function () { return aktif; };

    btn.addEventListener('click', function () {
      if (aktif) { return; }
      if (!olcumHazirMi(durum)) { return; }
      aktif = true;
      vurusAcik = false;
      vuruslar = [];
      taplar = [];
      pad.hidden = false;
      pad.classList.add('ro-pad-hazir');
      btn.hidden = true;
      var spb = 60 / bpm;
      var t0 = ctx.currentTime + 0.5;
      for (var i = 0; i < 20; i++) {
        var z = t0 + i * spb;
        var faz = i < 4 ? 0 : (i < 12 ? 1 : 2);
        if (faz < 2) { klik(z, i % 4 === 0); }
        vuruslar.push({ zaman: z, faz: faz });
      }
      durum.textContent = '🎧 Hazırlık · 4';
      for (var geri = 0; geri < 4; geri++) {
        (function (kalan, hedef) {
          setTimeout(function () {
            if (aktif && !vurusAcik) { durum.textContent = '🎧 Hazırlık · ' + kalan; }
          }, Math.max(0, (hedef - ctx.currentTime) * 1000));
        })(4 - geri, t0 + geri * spb);
      }
      setTimeout(function () {
        if (aktif) { vurusAcik = true; }
      }, Math.max(0, (t0 + 4 * spb - ctx.currentTime) * 1000 - zaman.BASLANGIC_KAPISI_MS));
      setTimeout(function () {
        if (!aktif) { return; }
        pad.classList.remove('ro-pad-hazir');
        durum.textContent = '🔊 ŞİMDİ · birlikte vur!';
      }, (t0 + 4 * spb - ctx.currentTime) * 1000);
      setTimeout(function () { durum.textContent = '🔇 Metronom sustu — içinden sayarak devam!'; },
        (t0 + 12 * spb - ctx.currentTime) * 1000);
      zamanlayici = setTimeout(function () { bitir(); }, (t0 + 20.6 * spb - ctx.currentTime) * 1000);
    });

    function bitir() {
      aktif = false;
      vurusAcik = false;
      pad.classList.remove('ro-pad-hazir');
      pad.hidden = true;
      btn.hidden = false;
      btn.textContent = '↻ Tekrar Dene';
      var spb = 60 / bpm;
      var fazlar = {
        1: { sapmalar: [], vurulan: {}, deneme: 0 },
        2: { sapmalar: [], vurulan: {}, deneme: 0 }
      };
      var kal = zaman.kalibrasyonOku();
      var eslesme = zaman.eslestir(taplar, vuruslar, {
        esikSn: spb * 0.45,
        telafiMs: kal.telafiMs,
        hedefUygun: function (v) { return v.faz === 1 || v.faz === 2; }
      });
      var tumSapmalar = [];   // işaretli sapmalar: kararlılık (SD) hesabı için
      eslesme.eslesenler.forEach(function (d) {
        var faz = d.hedef.faz;
        fazlar[faz].deneme++;
        fazlar[faz].sapmalar.push(d.sapmaMs);
        tumSapmalar.push(d.sapmaMs);
        fazlar[faz].vurulan[d.hedefIdx] = true;
      });
      eslesme.fazlaTaplar.forEach(function (d) {
        if (d.hedef && fazlar[d.hedef.faz]) { fazlar[d.hedef.faz].deneme++; }
      });
      function fazSkor(f) {
        var s = fazlar[f].sapmalar;
        if (!s.length) { return 0; }
        var mutlak = s.reduce(function (a, b) { return a + Math.abs(b); }, 0) / s.length;
        var isabet = Object.keys(fazlar[f].vurulan).length / 8;
        var dogruluk = Object.keys(fazlar[f].vurulan).length / Math.max(1, fazlar[f].deneme);
        return Math.round(100 * Math.max(0, 1 - mutlak / (0.3 * spb * 1000)) *
                          isabet * dogruluk);
      }
      var f1 = fazSkor(1), f2 = fazSkor(2);
      var skor = Math.round(0.4 * f1 + 0.6 * f2);
      durum.innerHTML = '⭐ Skor: <strong>' + skor + '</strong>/100 (sesli ' + f1 + ' · sessiz ' + f2 + ')';

      var form = kart.querySelector('form.ev-sonuc-form');
      if (form) {
        form.querySelector('[name="skor"]').value = skor;
        form.querySelector('[name="bpm"]').value = bpm;
        form.querySelector('[name="detay"]').value = JSON.stringify({
          sesli: f1, sessiz: f2, tap: taplar.length,
          fazla: eslesme.fazlaTaplar.length, zamanlamaSurumu: zaman.SURUM,
          telafiMs: kal.telafiMs, kalibrasyonDagilimMs: kal.dagilimMs
        });
        olcumEkiYaz(form, tumSapmalar);
        form.submit();
      }
    }
  });

  /* ---------- İçsel Ritim mini (4 hazırlık + %0/%50/%75 × 8 vuruş) ---------- */
  document.querySelectorAll('[data-gorev="icsel"]').forEach(function (kart) {
    var bpm = parseInt(kart.dataset.bpm, 10) || 72;
    var btn = kart.querySelector('.ev-gorev-baslat');
    var pad = kart.querySelector('.m-pad');
    var durum = kart.querySelector('.ev-sayac');
    var FAZLAR = [0, 50, 75];
    var aktif = false;
    var vurusAcik = false;
    var vuruslar = [];
    var taplar = [];
    var zamanlayici = null;

    function tap(olay) {
      if (!aktif || !vurusAcik) { return; }
      taplar.push(zaman.olayZamani(ctx, olay));
      pad.classList.add('vurdum');
      setTimeout(function () { pad.classList.remove('vurdum'); }, 80);
    }
    pad.addEventListener('pointerdown', function (ev) { ev.preventDefault(); tap(ev); });
    kart.__tap = tap;
    kart.__aktifMi = function () { return aktif; };

    btn.addEventListener('click', function () {
      if (aktif) { return; }
      if (!olcumHazirMi(durum)) { return; }
      aktif = true;
      vurusAcik = false;
      vuruslar = [];
      taplar = [];
      pad.hidden = false;
      pad.classList.add('ro-pad-hazir');
      btn.hidden = true;
      var spb = 60 / bpm;
      var t0 = ctx.currentTime + 0.5;
      var no = 0;
      for (var h = 0; h < 4; h++) {
        vuruslar.push({ zaman: t0 + no * spb, faz: -1, sessiz: false }); no++;
      }
      FAZLAR.forEach(function (yuzde, fazNo) {
        var susAdet = Math.round(8 * yuzde / 100);
        var adaylar = [1, 2, 3, 4, 5, 6, 7];
        for (var k = adaylar.length - 1; k > 0; k--) {
          var r = Math.floor(Math.random() * (k + 1));
          var tmp = adaylar[k]; adaylar[k] = adaylar[r]; adaylar[r] = tmp;
        }
        var sus = {};
        adaylar.slice(0, susAdet).forEach(function (p) { sus[p] = true; });
        for (var v = 0; v < 8; v++) {
          vuruslar.push({ zaman: t0 + no * spb, faz: fazNo, sessiz: !!sus[v] }); no++;
        }
      });
      vuruslar.forEach(function (v, i) {
        if (!v.sessiz) { klik(v.zaman, i % 4 === 0); }
      });
      durum.textContent = '🎧 Hazırlık · 4';
      for (var geri = 0; geri < 4; geri++) {
        (function (kalan, hedef) {
          setTimeout(function () {
            if (aktif && !vurusAcik) { durum.textContent = '🎧 Hazırlık · ' + kalan; }
          }, Math.max(0, (hedef - ctx.currentTime) * 1000));
        })(4 - geri, t0 + geri * spb);
      }
      setTimeout(function () {
        if (aktif) { vurusAcik = true; }
      }, Math.max(0, (vuruslar[4].zaman - ctx.currentTime) * 1000 - zaman.BASLANGIC_KAPISI_MS));
      setTimeout(function () {
        if (!aktif) { return; }
        pad.classList.remove('ro-pad-hazir');
        durum.textContent = '🥁 ŞİMDİ · her vuruşta vur';
      }, Math.max(0, (vuruslar[4].zaman - ctx.currentTime) * 1000));
      FAZLAR.forEach(function (yuzde, fazNo) {
        var fazBas = vuruslar[4 + fazNo * 8].zaman;
        setTimeout(function () {
          if (aktif) { durum.textContent = '🥁 Faz ' + (fazNo + 1) + ' — %' + yuzde + ' sessiz'; }
        }, (fazBas - ctx.currentTime) * 1000);
      });
      var bitis = vuruslar[vuruslar.length - 1].zaman + spb;
      zamanlayici = setTimeout(function () { bitir(); }, (bitis + 0.3 - ctx.currentTime) * 1000);
    });

    function bitir() {
      aktif = false;
      vurusAcik = false;
      pad.classList.remove('ro-pad-hazir');
      pad.hidden = true;
      btn.hidden = false;
      btn.textContent = '↻ Tekrar Dene';
      var spb = 60 / bpm;
      var fazVeri = FAZLAR.map(function () {
        return { sapmalar: [], vurulan: {}, deneme: 0 };
      });
      var kal = zaman.kalibrasyonOku();
      var eslesme = zaman.eslestir(taplar, vuruslar, {
        esikSn: spb * 0.45,
        telafiMs: kal.telafiMs,
        hedefUygun: function (v) { return v.faz >= 0; }
      });
      var tumSapmalar = [];   // işaretli sapmalar: kararlılık (SD) hesabı için
      eslesme.eslesenler.forEach(function (d) {
        fazVeri[d.hedef.faz].deneme++;
        fazVeri[d.hedef.faz].sapmalar.push(Math.abs(d.sapmaMs));
        tumSapmalar.push(d.sapmaMs);
        fazVeri[d.hedef.faz].vurulan[d.hedefIdx] = true;
      });
      eslesme.fazlaTaplar.forEach(function (d) {
        if (d.hedef && d.hedef.faz >= 0) { fazVeri[d.hedef.faz].deneme++; }
      });
      var AGIRLIK = [0.2, 0.35, 0.45];
      var fazSkorlar = fazVeri.map(function (f) {
        if (!f.sapmalar.length) { return 0; }
        var mutlak = f.sapmalar.reduce(function (a, b) { return a + b; }, 0) / f.sapmalar.length;
        var isabet = Object.keys(f.vurulan).length / 8;
        var dogruluk = Object.keys(f.vurulan).length / Math.max(1, f.deneme);
        return Math.round(100 * Math.max(0, 1 - mutlak / (0.3 * spb * 1000)) *
                          isabet * dogruluk);
      });
      var skor = Math.round(fazSkorlar.reduce(function (t, s, i) { return t + s * AGIRLIK[i]; }, 0));
      durum.innerHTML = '⭐ Skor: <strong>' + skor + '</strong>/100 (fazlar: ' + fazSkorlar.join(' · ') + ')';

      var form = kart.querySelector('form.ev-sonuc-form');
      if (form) {
        form.querySelector('[name="skor"]').value = skor;
        form.querySelector('[name="bpm"]').value = bpm;
        form.querySelector('[name="detay"]').value = JSON.stringify({
          fazlar: fazSkorlar, tap: taplar.length, fazla: eslesme.fazlaTaplar.length,
          zamanlamaSurumu: zaman.SURUM, telafiMs: kal.telafiMs,
          kalibrasyonDagilimMs: kal.dagilimMs
        });
        olcumEkiYaz(form, tumSapmalar);
        form.submit();
      }
    }
  });

  /* ---------- Ritim Okuma ---------- */
  document.querySelectorAll('[data-gorev="ritim"]').forEach(function (kart) {
    var kok = kart.querySelector('.ro-kok');
    if (!kok || !window.RitimOkuma) { return; }
    window.RitimOkuma.baslat(kok, {
      seviye: parseInt(kart.dataset.seviye, 10) || 1,
      bpm: parseInt(kart.dataset.bpm, 10) || 60,
      onBitti: function (sonuc) {
        var form = kart.querySelector('form.ev-sonuc-form');
        if (!form) { return; }
        form.querySelector('[name="skor"]').value = sonuc.skor;
        form.querySelector('[name="bpm"]').value = sonuc.bpm;
        form.querySelector('[name="detay"]').value = JSON.stringify(sonuc);
        olcumEkiYaz(form, null, sonuc.sapmaSdMs);
        form.submit();
      }
    });
  });

  /* Boşluk tuşu: aktif görevin pad'ine gider */
  document.addEventListener('keydown', function (ev) {
    if (ev.code !== 'Space') { return; }
    var etiket = (ev.target.tagName || '').toLowerCase();
    if (etiket === 'input' || etiket === 'textarea' || etiket === 'button' || etiket === 'select') { return; }
    if (evKalibrator) { ev.preventDefault(); evKalibrator.tap(ev); return; }
    var vurusKart = Array.from(document.querySelectorAll('[data-gorev="vurus"], [data-gorev="icsel"]'))
      .find(function (k) { return k.__aktifMi && k.__aktifMi(); });
    if (vurusKart) { ev.preventDefault(); vurusKart.__tap(ev); return; }
    var roKok = Array.from(document.querySelectorAll('.ro-kok'))
      .find(function (k) { return k.__roAktif && k.__roAktif(); });
    if (roKok) { ev.preventDefault(); roKok.__roTap(ev); }
  });
})();
