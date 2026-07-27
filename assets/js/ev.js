/* =======================================================
   Öğrenci Ev Sayfası — etkileşimli görevler
   - Metronomlu süre görevi (data-gorev="metronom")
   - Vuruş Tutturma mini (data-gorev="vurus")
   - Ritim Okuma (data-gorev="ritim", ritim-okuma.js widget'ı)
   Tamamlanınca ilgili gizli form doldurulup gönderilir.
   ======================================================= */
(function () {
  'use strict';

  var ctx = null;
  function sesHazirla() {
    if (!ctx) { ctx = new (window.AudioContext || window.webkitAudioContext)(); }
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
    var vuruslar = [];
    var taplar = [];
    var zamanlayici = null;

    function tap() {
      if (!aktif) { return; }
      taplar.push(ctx.currentTime);
      pad.classList.add('vurdum');
      setTimeout(function () { pad.classList.remove('vurdum'); }, 80);
    }
    pad.addEventListener('pointerdown', function (ev) { ev.preventDefault(); tap(); });
    kart.__tap = tap;
    kart.__aktifMi = function () { return aktif; };

    btn.addEventListener('click', function () {
      if (aktif) { return; }
      sesHazirla();
      aktif = true;
      vuruslar = [];
      taplar = [];
      pad.hidden = false;
      btn.hidden = true;
      var spb = 60 / bpm;
      var t0 = ctx.currentTime + 0.5;
      for (var i = 0; i < 20; i++) {
        var z = t0 + i * spb;
        var faz = i < 4 ? 0 : (i < 12 ? 1 : 2);
        if (faz < 2) { klik(z, i % 4 === 0); }
        vuruslar.push({ zaman: z, faz: faz });
      }
      durum.textContent = '🎧 4 vuruş dinle…';
      setTimeout(function () { durum.textContent = '🔊 Birlikte vur!'; }, (t0 + 4 * spb - ctx.currentTime) * 1000);
      setTimeout(function () { durum.textContent = '🔇 Metronom sustu — içinden sayarak devam!'; },
        (t0 + 12 * spb - ctx.currentTime) * 1000);
      zamanlayici = setTimeout(function () { bitir(); }, (t0 + 20.6 * spb - ctx.currentTime) * 1000);
    });

    function bitir() {
      aktif = false;
      pad.hidden = true;
      btn.hidden = false;
      btn.textContent = '↻ Tekrar Dene';
      var spb = 60 / bpm;
      var fazlar = { 1: [], 2: [] };
      var vurulan = { 1: {}, 2: {} };
      taplar.forEach(function (t) {
        var enIyi = null, enKucuk = Infinity, enIdx = -1;
        vuruslar.forEach(function (v, i) {
          var f = Math.abs(t - v.zaman);
          if (f < enKucuk) { enKucuk = f; enIyi = v; enIdx = i; }
        });
        if (!enIyi || enIyi.faz === 0 || enKucuk > spb * 0.45) { return; }
        fazlar[enIyi.faz].push((t - enIyi.zaman) * 1000);
        vurulan[enIyi.faz][enIdx] = true;
      });
      function fazSkor(f) {
        var s = fazlar[f];
        if (!s.length) { return 0; }
        var mutlak = s.reduce(function (a, b) { return a + Math.abs(b); }, 0) / s.length;
        return Math.round(100 * Math.max(0, 1 - mutlak / (0.3 * spb * 1000)) *
                          (Object.keys(vurulan[f]).length / 8));
      }
      var f1 = fazSkor(1), f2 = fazSkor(2);
      var skor = Math.round(0.4 * f1 + 0.6 * f2);
      durum.innerHTML = '⭐ Skor: <strong>' + skor + '</strong>/100 (sesli ' + f1 + ' · sessiz ' + f2 + ')';

      var form = kart.querySelector('form.ev-sonuc-form');
      if (form) {
        form.querySelector('[name="skor"]').value = skor;
        form.querySelector('[name="bpm"]').value = bpm;
        form.querySelector('[name="detay"]').value = JSON.stringify({ sesli: f1, sessiz: f2, tap: taplar.length });
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
        form.submit();
      }
    });
  });

  /* Boşluk tuşu: aktif görevin pad'ine gider */
  document.addEventListener('keydown', function (ev) {
    if (ev.code !== 'Space') { return; }
    var etiket = (ev.target.tagName || '').toLowerCase();
    if (etiket === 'input' || etiket === 'textarea' || etiket === 'button' || etiket === 'select') { return; }
    var vurusKart = Array.from(document.querySelectorAll('[data-gorev="vurus"]'))
      .find(function (k) { return k.__aktifMi && k.__aktifMi(); });
    if (vurusKart) { ev.preventDefault(); vurusKart.__tap(); return; }
    var roKok = Array.from(document.querySelectorAll('.ro-kok'))
      .find(function (k) { return k.__roAktif && k.__roAktif(); });
    if (roKok) { ev.preventDefault(); roKok.__roTap(); }
  });
})();
