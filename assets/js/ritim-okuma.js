/* =======================================================
   Ritim Okuma modülü — notasyonu sayarak vurma ("1 ve 2 ve", "1-le-me")
   Kendi kendine yeten widget: RitimOkuma.baslat(kok, {seviye, bpm, onBitti})
   Ev sayfasında ve (ileride) Metronom Stüdyosu'nda kullanılır.
   ======================================================= */
window.RitimOkuma = (function () {
  'use strict';

  var HUCRE_ONSET = { n: [0], e: [0, 0.5], t: [0, 1 / 3, 2 / 3], r: [] };
  var HUCRE_SEMBOL = { n: '♩', e: '♫', t: '♪♪♪', r: '𝄽' };

  function sayimEtiketi(tur, vurus) {
    if (tur === 'n') { return String(vurus); }
    if (tur === 'e') { return vurus + ' ve'; }
    if (tur === 't') { return vurus + '-le-me'; }
    return 'es';
  }

  function desenUret(seviye) {
    var havuz = seviye >= 3 ? ['n', 'n', 'e', 'e', 't', 'r']
              : seviye === 2 ? ['n', 'n', 'n', 'e', 'e', 'r']
              : ['n', 'n', 'n', 'r'];
    var hucreler = [];
    for (var i = 0; i < 8; i++) {
      if (i === 0) { hucreler.push('n'); continue; } // çapa: ilk vuruş daima nota
      var h = havuz[Math.floor(Math.random() * havuz.length)];
      if (h === 'r' && hucreler[i - 1] === 'r') { h = 'n'; } // art arda iki es olmasın
      hucreler.push(h);
    }
    return hucreler;
  }

  function baslat(kok, opts) {
    var seviye = opts.seviye || 1;
    var bpm = opts.bpm || 60;
    var tekrar = 2;                      // desen 2 kez çalınır (2 ölçü × 2)
    var spb = 60 / bpm;

    var ctx = null;
    var cikis = null;                    // master kazanç: iptalde sesi kesmek için
    var aktif = false;
    var taplar = [];
    var beklenen = [];                   // {zaman}
    var zamanlayicilar = [];

    kok.innerHTML =
      '<div class="ro-desen"></div>' +
      '<div class="ro-durum">Başlamak için düğmeye bas. Sayarak vur: alt yazılar sana yol gösterir.</div>' +
      '<div class="ro-butonlar">' +
      '  <button type="button" class="btn btn-birincil ro-baslat">▶ Başla</button>' +
      '  <button type="button" class="m-pad ro-pad" hidden>VUR<small>boşluk / dokun</small></button>' +
      '</div>';

    var desenEl = kok.querySelector('.ro-desen');
    var durumEl = kok.querySelector('.ro-durum');
    var baslatBtn = kok.querySelector('.ro-baslat');
    var pad = kok.querySelector('.ro-pad');
    var hucreler = [];
    var hucreEl = [];

    function ciz() {
      desenEl.innerHTML = '';
      hucreEl = [];
      for (var olcu = 0; olcu < 2; olcu++) {
        var kutu = document.createElement('div');
        kutu.className = 'ro-olcu';
        for (var v = 0; v < 4; v++) {
          var idx = olcu * 4 + v;
          var tur = hucreler[idx];
          var h = document.createElement('div');
          h.className = 'ro-hucre' + (tur === 'r' ? ' ro-es' : '');
          h.innerHTML = '<span class="ro-sembol">' + HUCRE_SEMBOL[tur] + '</span>' +
                        '<span class="ro-sayim">' + sayimEtiketi(tur, v + 1) + '</span>';
          kutu.appendChild(h);
          hucreEl.push(h);
        }
        desenEl.appendChild(kutu);
      }
    }

    function klik(zaman, aksan, kisik) {
      var o = ctx.createOscillator();
      var g = ctx.createGain();
      o.type = 'sine';
      o.frequency.setValueAtTime(aksan ? 980 : 700, zaman);
      o.frequency.exponentialRampToValueAtTime(aksan ? 620 : 460, zaman + 0.05);
      g.gain.setValueAtTime((aksan ? 0.6 : 0.4) * (kisik ? 0.55 : 1), zaman);
      g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.07);
      o.connect(g).connect(cikis);
      o.start(zaman); o.stop(zaman + 0.08);
    }

    function vurgula(idx, zaman) {
      var gecikme = Math.max(0, (zaman - ctx.currentTime) * 1000);
      zamanlayicilar.push(setTimeout(function () {
        hucreEl.forEach(function (h) { h.classList.remove('ro-aktif'); });
        if (idx >= 0 && hucreEl[idx]) { hucreEl[idx].classList.add('ro-aktif'); }
      }, gecikme));
    }

    function durumYaz(metin, gecikmeSn) {
      zamanlayicilar.push(setTimeout(function () { durumEl.textContent = metin; },
        Math.max(0, gecikmeSn * 1000)));
    }

    function calistir() {
      ctx = ctx || new (window.AudioContext || window.webkitAudioContext)();
      ctx.resume();
      if (!cikis) { cikis = ctx.createGain(); cikis.connect(ctx.destination); }
      hucreler = desenUret(seviye);
      ciz();
      taplar = [];
      beklenen = [];
      aktif = true;
      baslatBtn.hidden = true;
      pad.hidden = false;

      var t0 = ctx.currentTime + 0.5;
      // 1 ölçü sayım girişi
      for (var i = 0; i < 4; i++) {
        klik(t0 + i * spb, i === 0, false);
      }
      durumYaz('🎧 Sayım: 1 - 2 - 3 - 4…', (t0 - ctx.currentTime));
      var desenBas = t0 + 4 * spb;
      durumYaz('🥁 Şimdi! Sayarak tam yerinde vur.', (desenBas - ctx.currentTime));

      for (var tk = 0; tk < tekrar; tk++) {
        for (var idx = 0; idx < 8; idx++) {
          var vurusZamani = desenBas + (tk * 8 + idx) * spb;
          klik(vurusZamani, idx % 4 === 0, true);   // kısık kılavuz klik
          vurgula(idx, vurusZamani);
          HUCRE_ONSET[hucreler[idx]].forEach(function (ofset) {
            beklenen.push({ zaman: vurusZamani + ofset * spb });
          });
        }
      }
      var bitis = desenBas + tekrar * 8 * spb + spb * 0.6;
      zamanlayicilar.push(setTimeout(function () { bitir(); },
        (bitis - ctx.currentTime) * 1000));
      vurgula(-1, bitis);
    }

    function tap() {
      if (!aktif) { return; }
      taplar.push(ctx.currentTime);
      pad.classList.add('vurdum');
      setTimeout(function () { pad.classList.remove('vurdum'); }, 80);
    }

    function bitir() {
      aktif = false;
      pad.hidden = true;
      baslatBtn.hidden = false;
      baslatBtn.textContent = '↻ Tekrar Dene';

      var tol = 0.3 * spb;
      var kullanilan = {};
      var sapmalar = [];
      var isabet = 0;
      beklenen.forEach(function (b) {
        var enIyi = -1, enKucuk = Infinity;
        taplar.forEach(function (t, i) {
          if (kullanilan[i]) { return; }
          var fark = Math.abs(t - b.zaman);
          if (fark < enKucuk) { enKucuk = fark; enIyi = i; }
        });
        if (enIyi >= 0 && enKucuk <= tol) {
          kullanilan[enIyi] = true;
          isabet++;
          sapmalar.push((taplar[enIyi] - b.zaman) * 1000);
        }
      });
      var ekstra = taplar.length - Object.keys(kullanilan).length;
      var ortMutlak = sapmalar.length
        ? sapmalar.reduce(function (a, b) { return a + Math.abs(b); }, 0) / sapmalar.length
        : 0;
      var oran = beklenen.length ? isabet / beklenen.length : 0;
      var hamSkor = 100 * oran * Math.max(0, 1 - ortMutlak / (tol * 1000));
      var skor = Math.max(0, Math.round(hamSkor - Math.min(20, Math.max(0, ekstra) * 3)));

      durumEl.innerHTML = '⭐ Skor: <strong>' + skor + '</strong>/100 — ' +
        isabet + '/' + beklenen.length + ' vuruş yerinde' +
        (ekstra > 0 ? ', ' + ekstra + ' fazla vuruş' : '') + '.';

      if (typeof opts.onBitti === 'function') {
        opts.onBitti({
          skor: skor, seviye: seviye, bpm: bpm,
          beklenen: beklenen.length, isabet: isabet, ekstra: Math.max(0, ekstra),
          ortMutlakMs: Math.round(ortMutlak),
          desen: hucreler.join('')
        });
      }
    }

    function iptal() {
      aktif = false;
      zamanlayicilar.forEach(clearTimeout);
      zamanlayicilar = [];
      // Desenin klikleri ileri tarihli zamanlanır; çıkışı koparmadan susmazlar.
      if (cikis) {
        try { cikis.disconnect(); } catch (e) { /* zaten kopuk */ }
        cikis = ctx ? ctx.createGain() : null;
        if (cikis) { cikis.connect(ctx.destination); }
      }
      pad.hidden = true;
      baslatBtn.hidden = false;
      hucreEl.forEach(function (h) { h.classList.remove('ro-aktif'); });
    }

    baslatBtn.addEventListener('click', calistir);
    pad.addEventListener('pointerdown', function (ev) { ev.preventDefault(); tap(); });
    kok.__roTap = tap;      // klavye köprüsü için (ev.js Space yönlendirir)
    kok.__roAktif = function () { return aktif; };
    kok.__roIptal = iptal;
    ciz.call(null);
    hucreler = desenUret(seviye);
    ciz();
  }

  return { baslat: baslat };
})();
