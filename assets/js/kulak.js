/**
 * Kulak Yolu sayfası — ses çalar + soru akışı + yol görünümü.
 *
 * Ses zinciri metronomla aynı ilkeleri izler: master → kompresör →
 * hoparlör (çift seslerde iki osilatör üst üste biner; kompresör kırpma
 * koruması). Sorular KulakCekirdegi'nden gelir; buradaki kod yalnız
 * çalar ve toplar — karar mantığı test edilebilir çekirdekte.
 */
(function () {
  'use strict';
  var yol = window.YolCekirdegi;
  var cek = window.KulakCekirdegi;
  if (!yol || !cek || !document.getElementById('kulakBolumler')) { return; }

  var DURUM_ANAHTARI = 'kulak_yolu_durum_v1';

  function durumOku() {
    try {
      var v = JSON.parse(localStorage.getItem(DURUM_ANAHTARI));
      return v && typeof v === 'object' ? v : {};
    } catch (e) { return {}; }
  }
  function durumYaz(d) {
    try { localStorage.setItem(DURUM_ANAHTARI, JSON.stringify(d)); } catch (e) {}
  }

  /* ---------------- Ses motoru ---------------- */
  var ses = {
    ctx: null, master: null,
    hazirla: function () {
      if (!this.ctx) {
        this.ctx = new (window.AudioContext || window.webkitAudioContext)();
        var komp = this.ctx.createDynamicsCompressor();
        komp.threshold.value = -10; komp.knee.value = 12; komp.ratio.value = 5;
        komp.attack.value = 0.002; komp.release.value = 0.08;
        komp.connect(this.ctx.destination);
        this.master = this.ctx.createGain();
        this.master.gain.value = 0.8;
        this.master.connect(komp);
      }
      if (this.ctx.state === 'suspended') { this.ctx.resume(); }
      return this.ctx;
    },
    nota: function (zaman, hz, sureSn, tini) {
      var o = this.ctx.createOscillator();
      var g = this.ctx.createGain();
      o.type = tini || 'sine';
      o.frequency.value = hz;
      /* yumuşak zarf: tık yok, kuyruk kısa */
      g.gain.setValueAtTime(0.0001, zaman);
      g.gain.exponentialRampToValueAtTime(0.5, zaman + 0.02);
      g.gain.setValueAtTime(0.5, zaman + Math.max(0.05, sureSn - 0.09));
      g.gain.exponentialRampToValueAtTime(0.001, zaman + sureSn);
      o.connect(g).connect(this.master);
      o.start(zaman); o.stop(zaman + sureSn + 0.02);
    },
    klik: function (zaman) {
      var o = this.ctx.createOscillator();
      var g = this.ctx.createGain();
      o.type = 'sine';
      o.frequency.setValueAtTime(950, zaman);
      o.frequency.exponentialRampToValueAtTime(620, zaman + 0.04);
      g.gain.setValueAtTime(0.55, zaman);
      g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.07);
      o.connect(g).connect(this.master);
      o.start(zaman); o.stop(zaman + 0.08);
    },
    /** Ses dizisini zamanlar; toplam süreyi (sn) döndürür. */
    dizi: function (sesler) {
      var ctx = this.hazirla();
      var t = ctx.currentTime + 0.12;
      var self = this;
      sesler.forEach(function (s) {
        if (s.tip === 'bosluk') { t += s.sureMs / 1000; return; }
        if (s.tip === 'nota') {
          self.nota(t, cek.midiHz(s.midi), s.sureMs / 1000, s.tini);
          t += s.sureMs / 1000;
        } else if (s.tip === 'cift') {
          s.midiler.forEach(function (m) { self.nota(t, cek.midiHz(m), s.sureMs / 1000); });
          t += s.sureMs / 1000;
        } else if (s.tip === 'ritim') {
          var adimSn = 60 / s.bpm / 2;         // kalıp yarım vuruş çözünürlüklü
          s.kalip.forEach(function (v, i) {
            if (v) { self.klik(t + i * adimSn); }
          });
          t += s.kalip.length * adimSn + 0.1;
        }
      });
      return t - ctx.currentTime;
    }
  };

  /* ---------------- Öğeler ---------------- */
  function el(id) { return document.getElementById(id); }
  var yolKarti = el('kulakYolKarti');
  var adimKarti = el('kulakAdimKarti');
  var bolumlerEl = el('kulakBolumler');
  var ozetEl = el('kulakOzet');
  var canEl = el('kulakCan');
  var secptEl = el('kulakSecenekler');
  var geriBildirimEl = el('kulakGeriBildirim');
  var ilerlemeEl = el('kulakIlerleme');
  var sonucEl = el('kulakSonuc');

  /* ---------------- Yol görünümü ---------------- */
  function yildizMetni(y) {
    return '★★★'.slice(0, y) + '<span class="bos">' + '★★★'.slice(y) + '</span>';
  }

  function yoluCiz() {
    var durum = durumOku();
    var gorunum = yol.yolGorunumu(cek.duzAdimlar(), durum);
    var harita = {};
    gorunum.forEach(function (g) { harita[g.id] = g; });

    var biten = gorunum.filter(function (g) { return g.yildiz > 0; }).length;
    ozetEl.innerHTML = biten + '/' + gorunum.length + '<small>adım tamamlandı</small>';

    bolumlerEl.replaceChildren();
    cek.BOLUMLER.forEach(function (b, bIdx) {
      var bolum = document.createElement('section');
      bolum.className = 'yol-bolum';
      var bBiten = b.adimlar.filter(function (a) { return harita[a.id].yildiz > 0; }).length;
      bolum.innerHTML =
        '<div class="yol-bolum-baslik">' +
        '  <span class="yol-bolum-no" aria-hidden="true">' + (bIdx + 1) + '</span>' +
        '  <h3></h3>' +
        '  <span class="yol-bolum-ozet">' + bBiten + '/' + b.adimlar.length + ' adım</span>' +
        '</div>' +
        '<p class="yol-bolum-aciklama"></p>' +
        '<div class="yol-adimlar"></div>';
      bolum.querySelector('h3').textContent = b.ad;
      bolum.querySelector('.yol-bolum-aciklama').textContent = b.aciklama;
      var dizi = bolum.querySelector('.yol-adimlar');

      b.adimlar.forEach(function (a, i) {
        var g = harita[a.id];
        var dugme = document.createElement(g.acik ? 'button' : 'div');
        dugme.className = 'yol-adim' + (g.acik ? '' : ' kilitli') + (g.siradaki ? ' siradaki' : '');
        if (g.acik) { dugme.type = 'button'; }
        dugme.setAttribute('aria-label', a.ad + ' — '
          + (g.acik ? g.yildiz + ' yıldız' : 'kilitli: önce önceki adımdan yıldız al'));
        var no = document.createElement('span');
        no.className = 'yol-adim-no';
        no.textContent = 'ADIM ' + (i + 1);
        var ad = document.createElement('span');
        ad.className = 'yol-adim-ad';
        ad.textContent = a.ad;
        var yildiz = document.createElement('span');
        yildiz.className = 'yol-adim-yildiz';
        yildiz.setAttribute('aria-hidden', 'true');
        yildiz.innerHTML = yildizMetni(g.yildiz);
        dugme.append(no, ad, yildiz);
        if (g.acik) { dugme.addEventListener('click', function () { adimiBaslat(a.id); }); }
        dizi.appendChild(dugme);
      });
      bolumlerEl.appendChild(bolum);
    });
  }

  /* ---------------- Adım akışı ---------------- */
  var oturum = null;   // {adimId, sorular, soruNo, dogru, cevapVerildi}

  function adimiBaslat(adimId) {
    var paket = cek.sorulariUret(adimId, (Date.now() ^ (Math.random() * 0xffffffff)) >>> 0);
    if (!paket) { return; }
    oturum = { adimId: adimId, adim: paket.adim, sorular: paket.sorular,
               soruNo: 0, dogru: 0 };
    el('kulakAdimAdi').textContent = paket.adim.ad;
    yolKarti.hidden = true;
    adimKarti.hidden = false;
    sonucEl.hidden = true;
    soruyuGoster();
  }

  function cal(sesler) {
    var sure = ses.dizi(sesler);
    canEl.classList.add('caliyor');
    setTimeout(function () { canEl.classList.remove('caliyor'); }, Math.max(300, sure * 1000));
  }

  function soruyuGoster() {
    var s = oturum.sorular[oturum.soruNo];
    el('kulakSoruNo').textContent = 'Soru ' + (oturum.soruNo + 1) + '/' + oturum.sorular.length;
    ilerlemeEl.style.width = (100 * oturum.soruNo / oturum.sorular.length) + '%';
    geriBildirimEl.textContent = '';
    oturum.cevapVerildi = false;

    secptEl.replaceChildren();
    s.secenekler.forEach(function (etiket, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'kulak-secenek';
      b.textContent = etiket;
      b.addEventListener('click', function () { cevapla(i, b); });
      secptEl.appendChild(b);
      /* kalip_bul: her seçeneğin kendi dinleme düğmesi */
      if (s.secenekSesleri) {
        var dinle = document.createElement('button');
        dinle.type = 'button';
        dinle.className = 'btn btn-kucuk btn-golge';
        dinle.setAttribute('aria-label', etiket + ' seçeneğini dinle');
        dinle.textContent = '🔊 ' + etiket;
        dinle.addEventListener('click', function () { cal([s.secenekSesleri[i]]); });
        secptEl.appendChild(dinle);
      }
    });
    cal(s.sesler);
  }

  function cevapla(i, dugme) {
    if (oturum.cevapVerildi) { return; }
    oturum.cevapVerildi = true;
    var s = oturum.sorular[oturum.soruNo];
    var dogruMu = i === s.dogru;
    if (dogruMu) { oturum.dogru++; }

    var dugmeler = secptEl.querySelectorAll('.kulak-secenek');
    dugmeler.forEach(function (b, j) {
      b.disabled = true;
      if (j === s.dogru) { b.classList.add('dogru'); }
    });
    if (!dogruMu) { dugme.classList.add('yanlis'); }
    geriBildirimEl.textContent = (dogruMu ? '✔ Doğru. ' : '✘ Bu değildi. ') + s.aciklama;

    setTimeout(function () {
      oturum.soruNo++;
      if (oturum.soruNo < oturum.sorular.length) { soruyuGoster(); }
      else { adimiBitir(); }
    }, dogruMu ? 1100 : 2100);
  }

  function adimiBitir() {
    ilerlemeEl.style.width = '100%';
    var s0 = oturum.sorular[0];
    var skor = yol.sansDuzeltmeli(oturum.dogru, oturum.sorular.length, s0.secenekler.length);
    var yildiz = yol.yildizHesapla(skor);

    var durum = durumOku();
    yol.sonucIsle(durum, oturum.adimId, skor);
    durumYaz(durum);

    secptEl.replaceChildren();
    geriBildirimEl.textContent = '';
    el('kulakSoruNo').textContent = '';
    sonucEl.hidden = false;
    el('kulakSonucYildiz').innerHTML = yildizMetni(yildiz);
    el('kulakSonucMetin').textContent =
      oturum.dogru + '/' + oturum.sorular.length + ' doğru · şans payı düşülmüş skor '
      + skor + '/100' + (yildiz === 0
        ? ' — yıldız için %50 gerekir; kulak dinledikçe alışır, tekrar dene.'
        : (yildiz === 3 ? ' — bu adım tamamen oturdu!' : ''));
  }

  el('kulakGeri').addEventListener('click', function () {
    adimKarti.hidden = true;
    yolKarti.hidden = false;
    yoluCiz();
  });
  el('kulakSonrakiAdim').addEventListener('click', function () {
    adimKarti.hidden = true;
    yolKarti.hidden = false;
    yoluCiz();
  });
  el('kulakTekrar').addEventListener('click', function () { adimiBaslat(oturum.adimId); });
  el('kulakDinle').addEventListener('click', function () {
    /* Yalnız soru sahnedeyken (sonuç ekranı kapalıyken) tekrar dinletir */
    if (oturum && sonucEl.hidden && oturum.sorular[oturum.soruNo]) {
      cal(oturum.sorular[oturum.soruNo].sesler);
    }
  });

  yoluCiz();
})();
