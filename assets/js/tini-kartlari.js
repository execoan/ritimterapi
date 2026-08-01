/* ================================================================
   Kalın–İnce Kalıp Kartları — arayüz.

   ÖLÇÜM YOK: hiçbir yerde puan hesaplanmaz, hiçbir şey kaydedilmez,
   sunucuya istek atılmaz. Bu bilinçli — bkz. tini-kartlari.php başlığı.

   Ses tasarımı: gerçek bir cisme vurma sesi taklit edilir — kısa gürültü
   patlaması (darbe) + sönümlenen ton (cismin rezonansı). Kalın için alçak
   merkez frekans, ince için yüksek. Saf sinüs "masaya vurma" gibi duyulmuyordu.
   ================================================================ */
(function () {
  'use strict';

  var cekirdek = window.TiniCekirdegi;
  if (!cekirdek || !document.getElementById('tkKalip')) { return; }

  function byId(id) { return document.getElementById(id); }

  var d = {
    kademe: 'A',
    indeks: 0,
    kaliplar: cekirdek.kaliplar('A'),
    calisiyor: false,
    otomatikZaman: null,
    nesil: 0
  };

  /* ---------------- Ses ---------------- */
  var ses = {
    ctx: null,
    master: null,
    hazirla: function () {
      if (!this.ctx) {
        var AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) { return null; }
        this.ctx = new AC();
        this.master = this.ctx.createGain();
        this.master.gain.value = 0.9;
        this.master.connect(this.ctx.destination);
      }
      if (this.ctx.state === 'suspended') { this.ctx.resume(); }
      return this.ctx;
    },
    sustur: function () {
      if (!this.ctx || !this.master) { return; }
      try { this.master.disconnect(); } catch (e) { /* zaten kopuk */ }
      this.master = this.ctx.createGain();
      this.master.gain.value = 0.9;
      this.master.connect(this.ctx.destination);
    },
    /** Cisme vurma sesi. tini: 'k' (kalın) | 'i' (ince) */
    vur: function (t, tini) {
      var ctx = this.ctx;
      if (!ctx) { return; }
      var kalin = tini !== 'i';
      var merkez = kalin ? 180 : 1250;
      var sure = kalin ? 0.42 : 0.20;

      /* 1) Darbe: kısa filtrelenmiş gürültü — tahtaya/masaya vurma tokluğu */
      var uzunluk = Math.floor(ctx.sampleRate * 0.05);
      var tampon = ctx.createBuffer(1, uzunluk, ctx.sampleRate);
      var veri = tampon.getChannelData(0);
      for (var i = 0; i < uzunluk; i++) {
        veri[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / uzunluk, 2.2);
      }
      var kaynak = ctx.createBufferSource();
      kaynak.buffer = tampon;
      var bant = ctx.createBiquadFilter();
      bant.type = 'bandpass';
      bant.frequency.value = merkez * 1.7;
      bant.Q.value = 0.9;
      var gGurultu = ctx.createGain();
      gGurultu.gain.setValueAtTime(kalin ? 0.55 : 0.42, t);
      gGurultu.gain.exponentialRampToValueAtTime(0.001, t + 0.06);
      kaynak.connect(bant).connect(gGurultu).connect(this.master);
      kaynak.start(t);

      /* 2) Rezonans: cismin sönümlenen tonu */
      var o = ctx.createOscillator();
      o.type = kalin ? 'sine' : 'triangle';
      o.frequency.setValueAtTime(merkez, t);
      o.frequency.exponentialRampToValueAtTime(merkez * 0.82, t + sure);
      var g = ctx.createGain();
      g.gain.setValueAtTime(0.0001, t);
      g.gain.exponentialRampToValueAtTime(kalin ? 0.5 : 0.34, t + 0.006);
      g.gain.exponentialRampToValueAtTime(0.0001, t + sure);
      o.connect(g).connect(this.master);
      o.start(t);
      o.stop(t + sure + 0.02);
    }
  };

  /* ---------------- Çizim ---------------- */
  function kademeleriCiz() {
    var kap = byId('tkKademeler');
    kap.innerHTML = '';
    cekirdek.KADEMELER.forEach(function (k) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'tk-kademe-btn';
      b.setAttribute('aria-pressed', k.kod === d.kademe ? 'true' : 'false');
      b.innerHTML = '<b>' + k.ad + '</b><small>' + k.aciklama + '</small>';
      b.addEventListener('click', function () {
        durdur();
        d.kademe = k.kod;
        d.kaliplar = cekirdek.kaliplar(k.kod);
        d.indeks = 0;
        yenile();
      });
      kap.appendChild(b);
    });
  }

  function mevcut() { return d.kaliplar[d.indeks] || []; }

  function kalibiCiz() {
    var kalip = mevcut();
    var kap = byId('tkKalip');
    kap.className = 'tk-kalip' + (byId('tkGorunum').value === 'el' ? ' tk-gorunum-el' : '');
    kap.innerHTML = '';
    kalip.forEach(function (tini, i) {
      var kalin = tini !== 'i';
      var olay = document.createElement('div');
      olay.className = 'tk-olay';
      olay.dataset.sira = String(i);
      olay.innerHTML =
        '<div class="tk-simge ' + (kalin ? 'tk-simge-kalin' : 'tk-simge-ince') + '"></div>' +
        '<div class="tk-olay-etiket">' + (kalin ? 'KALIN' : 'İNCE') + '</div>' +
        '<div class="tk-olay-sira">' + (i + 1) + '. vuruş</div>';
      kap.appendChild(olay);
    });

    byId('tkKademeEtiket').textContent = d.kademe;
    byId('tkKalipBaslik').textContent = (d.indeks + 1) + ' / ' + d.kaliplar.length;
    byId('tkOkunur').textContent = cekirdek.okunurMetin(kalip);

    /* Grup şeridi — hangi grup çalıyor, hangisi susuyor */
    var g = cekirdek.grupDagilimi(kalip);
    byId('tkGrupSerit').innerHTML =
      '<div class="tk-grup-kutu tk-grup-kalin' + (g.kalinAdet ? '' : ' tk-grup-susuyor') + '">' +
        '<b>Kalın grubu</b>' + (g.kalinAdet ? g.kalinAdet + ' vuruş · sıra: ' +
          g.kalinSiralar.map(function (x) { return x + 1; }).join(', ') : '') +
      '</div>' +
      '<div class="tk-grup-kutu tk-grup-ince' + (g.inceAdet ? '' : ' tk-grup-susuyor') + '">' +
        '<b>İnce grubu</b>' + (g.inceAdet ? g.inceAdet + ' vuruş · sıra: ' +
          g.inceSiralar.map(function (x) { return x + 1; }).join(', ') : '') +
      '</div>';
  }

  function yenile() {
    kademeleriCiz();
    kalibiCiz();
  }

  /* ---------------- Çalma ---------------- */
  function vurguTemizle() {
    Array.prototype.forEach.call(byId('tkKalip').children, function (o) {
      o.classList.remove('tk-olay-aktif');
    });
  }

  function cal() {
    var ctx = ses.hazirla();
    if (!ctx) { return; }
    durdur(true);
    d.calisiyor = true;
    d.nesil++;
    var nesil = d.nesil;

    var bpm = Number(byId('tkBpm').value) || 72;
    var z = cekirdek.zamanlama(mevcut(), bpm);
    var t0 = ctx.currentTime + 0.25;

    byId('tkCal').textContent = '■ Durdur';

    z.olaylar.forEach(function (olay) {
      ses.vur(t0 + olay.t, olay.tini);
      /* Görsel vurgu sesle aynı anda: setTimeout AudioContext saatine göre kurulur */
      var gecikmeMs = Math.max(0, (t0 + olay.t - ctx.currentTime) * 1000);
      setTimeout(function () {
        if (nesil !== d.nesil) { return; }
        vurguTemizle();
        var el = byId('tkKalip').children[olay.sira];
        if (el) { el.classList.add('tk-olay-aktif'); }
      }, gecikmeMs);
    });

    var bitisMs = Math.max(0, (t0 + z.sureSn - ctx.currentTime) * 1000);
    setTimeout(function () {
      if (nesil !== d.nesil) { return; }
      vurguTemizle();
      d.calisiyor = false;
      byId('tkCal').textContent = '▶ Çal';
      if (byId('tkOtomatik').checked) {
        d.otomatikZaman = setTimeout(function () {
          if (nesil !== d.nesil) { return; }
          sonraki();
          cal();
        }, 1400);
      }
    }, bitisMs + 120);
  }

  function durdur(sessiz) {
    d.nesil++;
    d.calisiyor = false;
    if (d.otomatikZaman) { clearTimeout(d.otomatikZaman); d.otomatikZaman = null; }
    ses.sustur();
    vurguTemizle();
    byId('tkCal').textContent = '▶ Çal';
    if (!sessiz && byId('tkOtomatik').checked) { byId('tkOtomatik').checked = false; }
  }

  function sonraki() { d.indeks = (d.indeks + 1) % d.kaliplar.length; kalibiCiz(); }
  function onceki()  { d.indeks = (d.indeks - 1 + d.kaliplar.length) % d.kaliplar.length; kalibiCiz(); }

  /* ---------------- Yazdırma ---------------- */
  function yazdir() {
    var eski = document.querySelector('.tk-yazdir-sayfa');
    if (eski) { eski.remove(); }
    var sayfa = document.createElement('div');
    sayfa.className = 'tk-yazdir-sayfa';
    var html = '<h2>Kalın–İnce Kalıp Kartları</h2>';
    cekirdek.KADEMELER.forEach(function (k) {
      html += '<div class="tk-yazdir-kart"><h3>' + k.ad + '</h3>';
      k.kaliplar.forEach(function (kalip, i) {
        html += '<div class="tk-yazdir-satir"><span>' + (i + 1) + '.</span>';
        kalip.forEach(function (t) {
          html += '<span class="' + (t === 'i' ? 'tk-simge-ince' : 'tk-simge-kalin') + '"></span>';
        });
        html += '<em style="margin-left:auto;font-size:.8rem">' + cekirdek.okunurMetin(kalip) + '</em></div>';
      });
      html += '</div>';
    });
    sayfa.innerHTML = html;
    document.body.appendChild(sayfa);
    window.print();
  }

  /* ---------------- Olaylar ---------------- */
  byId('tkCal').addEventListener('click', function () {
    if (d.calisiyor) { durdur(); } else { cal(); }
  });
  byId('tkSonraki').addEventListener('click', function () { durdur(true); sonraki(); });
  byId('tkOnceki').addEventListener('click', function () { durdur(true); onceki(); });
  byId('tkGorunum').addEventListener('change', kalibiCiz);
  byId('tkBpm').addEventListener('input', function () { byId('tkBpmYazi').textContent = this.value; });
  byId('tkYazdir').addEventListener('click', yazdir);

  byId('tkKarisik').addEventListener('click', function () {
    durdur(true);
    /* Tüm kademelerden rastgele bir kalıp — sürpriz öğesi çocuklar için */
    var hepsi = cekirdek.tumKaliplar();
    d.kaliplar = [hepsi[Math.floor(Math.random() * hepsi.length)]];
    d.indeks = 0;
    d.kademe = '🔀';
    kalibiCiz();
    byId('tkKademeEtiket').textContent = '🔀';
  });

  byId('tkTamEkran').addEventListener('click', function () {
    var s = byId('tkSahne');
    if (document.fullscreenElement) { document.exitFullscreen(); }
    else if (s.requestFullscreen) { s.requestFullscreen(); }
  });

  /* Klavye: boşluk çal/durdur, oklar gezinme — eğitmen uzaktan kumandayla da kullanabilsin */
  document.addEventListener('keydown', function (ev) {
    var etiket = (ev.target && ev.target.tagName || '').toLowerCase();
    if (etiket === 'input' || etiket === 'select' || etiket === 'textarea') { return; }
    if (ev.code === 'Space') { ev.preventDefault(); byId('tkCal').click(); }
    else if (ev.key === 'ArrowRight') { ev.preventDefault(); durdur(true); sonraki(); }
    else if (ev.key === 'ArrowLeft') { ev.preventDefault(); durdur(true); onceki(); }
  });

  /* Sekme arkaya alınırsa zamanlayıcılar kısılır; sesi ve otomatik sırayı kes */
  document.addEventListener('visibilitychange', function () {
    if (document.hidden && (d.calisiyor || d.otomatikZaman)) { durdur(); }
  });

  yenile();
})();
