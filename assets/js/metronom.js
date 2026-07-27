/* =======================================================
   Metronom Stüdyosu v2 — Web Audio motoru + dikkat protokolleri
   Zamanlama: lookahead planlayıcı (25 ms tarama, 120 ms ileri)
   v2: alt bölünme, vuruş başına aksan deseni, tempo trainer,
   poliritim, geniş ses kiti + seçim önizlemesi, sesli sayma,
   flaş modu, preset'ler, Spontan Tempo + Aksak Bulma testleri.
   ======================================================= */
(function () {
  'use strict';

  function byId(id) { return document.getElementById(id); }
  function ort(dizi) {
    if (!dizi.length) { return 0; }
    return dizi.reduce(function (a, b) { return a + b; }, 0) / dizi.length;
  }

  /* ================================================================
     SES MOTORU — kit sesleri sentezlenir, dosya yok
     ================================================================ */
  var ses = {
    ctx: null,
    master: null,
    hazirla: function () {
      if (!this.ctx) {
        this.ctx = new (window.AudioContext || window.webkitAudioContext)();
        this.master = this.ctx.createGain();
        this.master.gain.value = 0.8;
        this.master.connect(this.ctx.destination);
      }
      if (this.ctx.state === 'suspended') { this.ctx.resume(); }
      return this.ctx;
    },
    duzey: function (v) { if (this.master) { this.master.gain.value = v; } },

    /* Ana vuruş. vurgu: true/2 = aksan, false/1 = normal (0 çağrılmaz). */
    vur: function (zaman, vurgu, tur) {
      var aksan = vurgu === true || vurgu === 2;
      var ctx = this.ctx;
      var g = ctx.createGain();
      g.connect(this.master);

      if (tur === 'klik') {
        var o = ctx.createOscillator();
        o.type = 'square';
        o.frequency.value = aksan ? 1600 : 1000;
        g.gain.setValueAtTime(aksan ? 0.55 : 0.35, zaman);
        g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.045);
        o.connect(g); o.start(zaman); o.stop(zaman + 0.05);

      } else if (tur === 'bip') {
        var o2 = ctx.createOscillator();
        o2.type = 'sine';
        o2.frequency.value = aksan ? 1318 : 880;
        g.gain.setValueAtTime(aksan ? 0.5 : 0.32, zaman);
        g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.1);
        o2.connect(g); o2.start(zaman); o2.stop(zaman + 0.11);

      } else if (tur === 'klaves') {
        var o3 = ctx.createOscillator();
        o3.type = 'sine';
        o3.frequency.setValueAtTime(aksan ? 2800 : 2200, zaman);
        o3.frequency.exponentialRampToValueAtTime(aksan ? 2000 : 1600, zaman + 0.025);
        g.gain.setValueAtTime(aksan ? 0.65 : 0.45, zaman);
        g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.035);
        o3.connect(g); o3.start(zaman); o3.stop(zaman + 0.04);

      } else if (tur === 'zil') { /* inek çanı: iki metalik kare dalga */
        var z1 = ctx.createOscillator(); var z2 = ctx.createOscillator();
        z1.type = 'square'; z2.type = 'square';
        z1.frequency.value = aksan ? 940 : 800;
        z2.frequency.value = aksan ? 635 : 540;
        g.gain.setValueAtTime(aksan ? 0.35 : 0.24, zaman);
        g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.14);
        z1.connect(g); z2.connect(g);
        z1.start(zaman); z2.start(zaman);
        z1.stop(zaman + 0.15); z2.stop(zaman + 0.15);

      } else if (tur === 'davul') { /* kick; aksanda üstüne trampet fırçası */
        var k = ctx.createOscillator();
        k.type = 'sine';
        k.frequency.setValueAtTime(150, zaman);
        k.frequency.exponentialRampToValueAtTime(48, zaman + 0.11);
        g.gain.setValueAtTime(0.9, zaman);
        g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.13);
        k.connect(g); k.start(zaman); k.stop(zaman + 0.14);
        if (aksan) {
          var n = ctx.createBufferSource();
          var buf = ctx.createBuffer(1, ctx.sampleRate * 0.06, ctx.sampleRate);
          var d = buf.getChannelData(0);
          for (var i = 0; i < d.length; i++) { d[i] = (Math.random() * 2 - 1) * (1 - i / d.length); }
          n.buffer = buf;
          var ng = ctx.createGain();
          ng.gain.setValueAtTime(0.35, zaman);
          ng.gain.exponentialRampToValueAtTime(0.001, zaman + 0.06);
          n.connect(ng).connect(this.master);
          n.start(zaman);
        }

      } else if (tur === 'sayma') { /* sesli sayma: alt tık; sayı TTS ile görselde */
        var s = ctx.createOscillator();
        s.type = 'sine';
        s.frequency.value = aksan ? 1200 : 900;
        g.gain.setValueAtTime(0.12, zaman);
        g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.03);
        s.connect(g); s.start(zaman); s.stop(zaman + 0.035);

      } else { /* tahta blok (varsayılan) */
        var o4 = ctx.createOscillator();
        o4.type = 'sine';
        o4.frequency.setValueAtTime(aksan ? 980 : 720, zaman);
        o4.frequency.exponentialRampToValueAtTime(aksan ? 640 : 480, zaman + 0.05);
        g.gain.setValueAtTime(aksan ? 0.7 : 0.5, zaman);
        g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.07);
        o4.connect(g); o4.start(zaman); o4.stop(zaman + 0.08);
        var t = ctx.createOscillator();
        var tg = ctx.createGain();
        t.type = 'square';
        t.frequency.value = 2200;
        tg.gain.setValueAtTime(0.12, zaman);
        tg.gain.exponentialRampToValueAtTime(0.001, zaman + 0.015);
        t.connect(tg).connect(this.master);
        t.start(zaman); t.stop(zaman + 0.02);
      }
    },

    /* Alt bölünme tıkı: kısık ve tiz. */
    vurSub: function (zaman) {
      var o = this.ctx.createOscillator();
      var g = this.ctx.createGain();
      o.type = 'sine';
      o.frequency.value = 1500;
      g.gain.setValueAtTime(0.16, zaman);
      g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.028);
      o.connect(g).connect(this.master);
      o.start(zaman); o.stop(zaman + 0.03);
    },

    /* Poliritim çapraz vuruşu: ayırt edici tiz bip. */
    vurCapraz: function (zaman) {
      var o = this.ctx.createOscillator();
      var g = this.ctx.createGain();
      o.type = 'triangle';
      o.frequency.value = 1976;
      g.gain.setValueAtTime(0.3, zaman);
      g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.07);
      o.connect(g).connect(this.master);
      o.start(zaman); o.stop(zaman + 0.08);
    }
  };

  var SAYILAR_TR = ['bir', 'iki', 'üç', 'dört', 'beş', 'altı', 'yedi'];
  function konus(metin) {
    if (!('speechSynthesis' in window)) { return; }
    var u = new SpeechSynthesisUtterance(metin);
    u.lang = 'tr-TR';
    u.rate = 1.25;
    u.volume = 1;
    window.speechSynthesis.speak(u);
  }

  /* ================================================================
     A) SERBEST METRONOM
     ================================================================ */
  var m = {
    bpm: 92, calisiyor: false, zamanlayici: null,
    sonrakiZaman: 0, vurusNo: 0, olcuSayaci: 0,
    sarkacYonu: 1,
    aksanDeseni: [2, 1, 1, 1],
    el: {
      bpm: byId('mBpm'), tempoAdi: byId('mTempoAdi'), surgu: byId('mBpmSurgu'),
      noktalar: byId('mNoktalar'), olcu: byId('mOlcu'), ses: byId('mSes'),
      duzey: byId('mSesDuzeyi'), altBolunme: byId('mAltBolunme'),
      poliritim: byId('mPoliritim'), flasModu: byId('mFlasModu'), flas: byId('mFlas'),
      sayac: byId('mSayac'),
      sessizModu: byId('mSessizModu'), sessizSecim: byId('mSessizSecim'),
      sesliOlcu: byId('mSesliOlcu'), sessizOlcu: byId('mSessizOlcu'),
      trainer: byId('mTrainer'), trainerSecim: byId('mTrainerSecim'),
      trainerHedef: byId('mTrainerHedef'), trainerOlcu: byId('mTrainerOlcu'),
      trainerArtis: byId('mTrainerArtis'),
      rastgeleSus: byId('mRastgeleSus'), zamanlayiciSel: byId('mZamanlayici'),
      titresim: byId('mTitresim'), tamEkran: byId('mTamEkran'),
      sarkiTurler: byId('sarkiTurler'), sarkiListe: byId('sarkiListe'), sarkiAra: byId('sarkiAra'),
      presetler: byId('mPresetler'), presetKaydet: byId('mPresetKaydet'),
      baslat: byId('mBaslat'), tap: byId('mTap'),
      sarkac: byId('mSarkac'), halka: byId('mHalka')
    }
  };
  if (!m.el.bpm) { return; } /* farklı sayfa */

  var TEMPO_ADLARI = [
    [60, 'Largo'], [76, 'Adagio'], [108, 'Andante'], [120, 'Moderato'],
    [156, 'Allegro'], [200, 'Presto'], [999, 'Prestissimo']
  ];
  function tempoAdi(bpm) {
    for (var i = 0; i < TEMPO_ADLARI.length; i++) {
      if (bpm < TEMPO_ADLARI[i][0]) { return TEMPO_ADLARI[i][1]; }
    }
    return 'Presto';
  }

  function bpmAyarla(yeni) {
    m.bpm = Math.max(30, Math.min(240, Math.round(yeni)));
    m.el.bpm.textContent = m.bpm;
    m.el.surgu.value = m.bpm;
    m.el.tempoAdi.textContent = tempoAdi(m.bpm);
  }

  /* ---- Aksan deseni editörü: nokta tıkla → aksan(2) → normal(1) → sessiz(0) ---- */
  function noktaSinifi(deger) {
    return 'm-nokta ' + (deger === 2 ? 'm-nokta-aksan' : (deger === 1 ? 'm-nokta-normal' : 'm-nokta-sessiz'));
  }

  function noktalariKur() {
    var adet = parseInt(m.el.olcu.value, 10);
    while (m.aksanDeseni.length < adet) { m.aksanDeseni.push(1); }
    m.aksanDeseni = m.aksanDeseni.slice(0, adet);
    if (!m.aksanDeseni.some(function (v) { return v > 0; })) { m.aksanDeseni[0] = 2; }
    m.el.noktalar.innerHTML = '';
    m.aksanDeseni.forEach(function (deger, i) {
      var n = document.createElement('button');
      n.type = 'button';
      n.className = noktaSinifi(deger);
      n.dataset.idx = i;
      n.title = (i + 1) + '. vuruş: ' + (deger === 2 ? 'aksan' : deger === 1 ? 'normal' : 'sessiz');
      m.el.noktalar.appendChild(n);
    });
  }

  m.el.noktalar.addEventListener('click', function (ev) {
    var n = ev.target.closest('.m-nokta');
    if (!n) { return; }
    var i = parseInt(n.dataset.idx, 10);
    m.aksanDeseni[i] = (m.aksanDeseni[i] + 2) % 3; /* 2→1→0→2 */
    n.className = noktaSinifi(m.aksanDeseni[i]);
    n.title = (i + 1) + '. vuruş: ' + (m.aksanDeseni[i] === 2 ? 'aksan' : m.aksanDeseni[i] === 1 ? 'normal' : 'sessiz');
  });

  function gorselVurus(zaman, olcuIcindeki, vurgu, sesli) {
    var gecikme = Math.max(0, (zaman - ses.ctx.currentTime) * 1000);
    var sure = 60 / m.bpm;
    setTimeout(function () {
      if (!m.calisiyor) { return; }
      m.sarkacYonu = -m.sarkacYonu;
      m.el.sarkac.style.transition = 'transform ' + sure.toFixed(3) + 's ease-in-out';
      m.el.sarkac.style.transform = 'rotate(' + (26 * m.sarkacYonu) + 'deg)';
      m.el.sayac.textContent = olcuIcindeki + 1;

      var noktalar = m.el.noktalar.children;
      for (var i = 0; i < noktalar.length; i++) { noktalar[i].classList.remove('aktif'); }
      if (noktalar[olcuIcindeki]) { noktalar[olcuIcindeki].classList.add('aktif'); }

      if (sesli && vurgu > 0) {
        m.el.halka.classList.add(vurgu === 2 ? 'vur-aksan' : 'vur');
        setTimeout(function () { m.el.halka.classList.remove('vur', 'vur-aksan'); }, 100);
        if (m.el.flasModu.checked) {
          m.el.flas.classList.add(vurgu === 2 ? 'flas-aksan' : 'flas');
          setTimeout(function () { m.el.flas.classList.remove('flas', 'flas-aksan'); }, 90);
        }
        if (m.el.titresim.checked && navigator.vibrate) {
          navigator.vibrate(vurgu === 2 ? 40 : 20);
        }
        if (m.el.ses.value === 'sayma') { konus(SAYILAR_TR[olcuIcindeki] || String(olcuIcindeki + 1)); }
      }
    }, gecikme);
  }

  function planla() {
    var olcuAdedi = parseInt(m.el.olcu.value, 10);
    var alt = parseInt(m.el.altBolunme.value, 10);
    var capraz = parseInt(m.el.poliritim.value, 10);

    /* Çalışma zamanlayıcısı: süre dolunca kendiliğinden dur */
    if (m.bitisMs && Date.now() >= m.bitisMs) {
      metronomDurdur();
      m.el.sayac.textContent = '✓';
      return;
    }

    while (m.sonrakiZaman < ses.ctx.currentTime + 0.12) {
      var spb = 60 / m.bpm;
      var olcuIcindeki = m.vurusNo % olcuAdedi;
      var olcuNo = Math.floor(m.vurusNo / olcuAdedi);

      /* Tempo trainer: her N ölçüde hedefe doğru yaklaş */
      if (olcuIcindeki === 0 && m.vurusNo > 0 && m.el.trainer.checked) {
        m.olcuSayaci++;
        var herOlcu = parseInt(m.el.trainerOlcu.value, 10);
        if (m.olcuSayaci % herOlcu === 0) {
          var hedef = Math.max(30, Math.min(240, parseInt(m.el.trainerHedef.value, 10) || m.bpm));
          var artis = parseInt(m.el.trainerArtis.value, 10);
          if (m.bpm < hedef) { bpmAyarla(Math.min(hedef, m.bpm + artis)); }
          else if (m.bpm > hedef) { bpmAyarla(Math.max(hedef, m.bpm - artis)); }
          spb = 60 / m.bpm;
        }
      }

      var sesli = true;
      if (m.el.sessizModu.checked) {
        var a = parseInt(m.el.sesliOlcu.value, 10);
        var s = parseInt(m.el.sessizOlcu.value, 10);
        sesli = (olcuNo % (a + s)) < a;
      }

      var vurgu = m.aksanDeseni[olcuIcindeki] === undefined ? 1 : m.aksanDeseni[olcuIcindeki];

      /* 🎲 Rastgele sus (Time Guru tarzı): görsel akar, ses o vuruşta susar */
      var rastgeleSustu = false;
      var susYuzde = parseInt(m.el.rastgeleSus.value, 10);
      if (sesli && susYuzde > 0 && Math.random() * 100 < susYuzde) {
        rastgeleSustu = true;
      }

      if (sesli && !rastgeleSustu && vurgu > 0) {
        ses.vur(m.sonrakiZaman, vurgu, m.el.ses.value);
      }
      if (sesli && !rastgeleSustu && alt > 1 && m.el.ses.value !== 'sayma') {
        for (var sb = 1; sb < alt; sb++) {
          ses.vurSub(m.sonrakiZaman + sb * spb / alt);
        }
      }
      if (sesli && capraz > 0 && olcuIcindeki === 0) {
        var olcuSuresi = spb * olcuAdedi;
        for (var c = 0; c < capraz; c++) {
          ses.vurCapraz(m.sonrakiZaman + c * olcuSuresi / capraz);
        }
      }
      gorselVurus(m.sonrakiZaman, olcuIcindeki, vurgu, sesli);
      m.sonrakiZaman += spb;
      m.vurusNo++;
    }
  }

  function metronomBaslat() {
    ses.hazirla();
    ses.duzey(parseInt(m.el.duzey.value, 10) / 100);
    m.vurusNo = 0;
    m.olcuSayaci = 0;
    var dk = parseInt(m.el.zamanlayiciSel.value, 10);
    m.bitisMs = dk > 0 ? Date.now() + dk * 60000 : 0;
    m.sonrakiZaman = ses.ctx.currentTime + 0.08;
    m.zamanlayici = setInterval(planla, 25);
    m.calisiyor = true;
    m.el.baslat.textContent = '⏸ Durdur';
    m.el.baslat.classList.add('calisiyor');
  }

  function metronomDurdur() {
    clearInterval(m.zamanlayici);
    m.calisiyor = false;
    m.el.baslat.textContent = '▶ Başlat';
    m.el.baslat.classList.remove('calisiyor');
    m.el.sarkac.style.transition = 'transform .4s ease';
    m.el.sarkac.style.transform = 'rotate(0deg)';
    m.el.sayac.textContent = '–';
    Array.prototype.forEach.call(m.el.noktalar.children, function (n) { n.classList.remove('aktif'); });
  }

  m.el.baslat.addEventListener('click', function () {
    if (m.calisiyor) { metronomDurdur(); } else { metronomBaslat(); }
  });
  m.el.surgu.addEventListener('input', function () { bpmAyarla(this.value); });
  document.querySelectorAll('[data-bpm-degistir]').forEach(function (b) {
    b.addEventListener('click', function () {
      bpmAyarla(m.bpm + parseInt(b.dataset.bpmDegistir, 10));
    });
  });
  m.el.olcu.addEventListener('change', noktalariKur);
  m.el.duzey.addEventListener('input', function () { ses.duzey(parseInt(this.value, 10) / 100); });
  m.el.sessizModu.addEventListener('change', function () { m.el.sessizSecim.hidden = !this.checked; });
  m.el.trainer.addEventListener('change', function () {
    m.el.trainerSecim.hidden = !this.checked;
    if (this.checked && !m.el.trainerHedef.value) { m.el.trainerHedef.value = m.bpm + 20; }
  });

  /* Ses kiti önizlemesi: seçince iki vuruşluk tadımlık */
  m.el.ses.addEventListener('change', function () {
    ses.hazirla();
    ses.duzey(parseInt(m.el.duzey.value, 10) / 100);
    var t = ses.ctx.currentTime + 0.03;
    ses.vur(t, 2, this.value);
    ses.vur(t + 0.32, 1, this.value);
    if (this.value === 'sayma') { konus('bir, iki'); }
  });

  /* Tap tempo */
  var tapZamanlari = [];
  function tapTempo() {
    var simdi = performance.now();
    tapZamanlari = tapZamanlari.filter(function (t) { return simdi - t < 3000; });
    tapZamanlari.push(simdi);
    if (tapZamanlari.length >= 2) {
      var toplam = 0;
      for (var i = 1; i < tapZamanlari.length; i++) { toplam += tapZamanlari[i] - tapZamanlari[i - 1]; }
      bpmAyarla(60000 / (toplam / (tapZamanlari.length - 1)));
    }
    m.el.tap.textContent = '👆 ' + (tapZamanlari.length < 2 ? 'devam…' : m.bpm + ' BPM');
    clearTimeout(tapTempo._sifirla);
    tapTempo._sifirla = setTimeout(function () {
      tapZamanlari = [];
      m.el.tap.textContent = '👆 Tap Tempo';
    }, 2400);
  }
  m.el.tap.addEventListener('click', tapTempo);

  /* ---- Preset'ler (localStorage) ---- */
  var PRESET_ANAHTAR = 'ritim_presetler_v1';
  function presetOku() {
    try { return JSON.parse(localStorage.getItem(PRESET_ANAHTAR) || '[]'); }
    catch (e) { return []; }
  }
  function presetYaz(liste) {
    try { localStorage.setItem(PRESET_ANAHTAR, JSON.stringify(liste)); } catch (e) { /* dolu */ }
  }
  function presetCiz() {
    var liste = presetOku();
    m.el.presetler.innerHTML = '';
    if (!liste.length) {
      m.el.presetler.innerHTML = '<span class="alan-ipucu">yok</span>';
      return;
    }
    liste.forEach(function (p, i) {
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'm-preset-chip';
      chip.innerHTML = '<b>' + p.ad + '</b> ' + p.bpm + ' <span class="m-preset-sil" title="Sil">×</span>';
      chip.addEventListener('click', function (ev) {
        if (ev.target.closest('.m-preset-sil')) {
          liste.splice(i, 1);
          presetYaz(liste);
          presetCiz();
          return;
        }
        bpmAyarla(p.bpm);
        m.el.olcu.value = p.olcu;
        m.el.ses.value = p.ses;
        m.el.altBolunme.value = p.alt || '1';
        m.el.poliritim.value = p.poliritim || '0';
        m.aksanDeseni = (p.desen || []).slice();
        noktalariKur();
      });
      m.el.presetler.appendChild(chip);
    });
  }
  m.el.presetKaydet.addEventListener('click', function () {
    var ad = window.prompt('Preset adı:', tempoAdi(m.bpm) + ' ' + m.bpm);
    if (!ad) { return; }
    var liste = presetOku();
    liste.push({ ad: ad.slice(0, 18), bpm: m.bpm, olcu: m.el.olcu.value, ses: m.el.ses.value,
                 alt: m.el.altBolunme.value, poliritim: m.el.poliritim.value, desen: m.aksanDeseni.slice() });
    presetYaz(liste.slice(0, 12));
    presetCiz();
  });

  /* ---- ⛶ Tam ekran ---- */
  m.el.tamEkran.addEventListener('click', function () {
    var sahne = byId('mSahne');
    if (!document.fullscreenElement) {
      if (sahne.requestFullscreen) { sahne.requestFullscreen().catch(function () { /* desteklenmiyor */ }); }
    } else if (document.exitFullscreen) {
      document.exitFullscreen();
    }
  });
  document.addEventListener('fullscreenchange', function () {
    m.el.tamEkran.textContent = document.fullscreenElement ? '⛶ Çık' : '⛶ Tam ekran';
  });

  /* ================================================================
     🎵 ŞARKI TEMPO KÜTÜPHANESİ
     BPM'ler getsongbpm.com / tunebat kayıtlarından (Temmuz 2026);
     kayıt sürümüne göre küçük farklar olabilir.
     ================================================================ */
  var SARKILAR = [
    /* METAL */
    { tur: 'metal', ad: 'Master of Puppets', sanatci: 'Metallica', bpm: 220 },
    { tur: 'metal', ad: 'Enter Sandman', sanatci: 'Metallica', bpm: 123 },
    { tur: 'metal', ad: 'Paranoid', sanatci: 'Black Sabbath', bpm: 163 },
    { tur: 'metal', ad: 'Ace of Spades', sanatci: 'Motörhead', bpm: 141 },
    { tur: 'metal', ad: 'Painkiller', sanatci: 'Judas Priest', bpm: 176 },
    { tur: 'metal', ad: 'The Trooper', sanatci: 'Iron Maiden', bpm: 161 },
    { tur: 'metal', ad: 'Chop Suey!', sanatci: 'System of a Down', bpm: 127 },
    { tur: 'metal', ad: 'Duality', sanatci: 'Slipknot', bpm: 148 },
    { tur: 'metal', ad: 'Symphony of Destruction', sanatci: 'Megadeth', bpm: 140 },
    /* ROCK */
    { tur: 'rock', ad: 'Back in Black', sanatci: 'AC/DC', bpm: 94 },
    { tur: 'rock', ad: 'Highway to Hell', sanatci: 'AC/DC', bpm: 116 },
    { tur: 'rock', ad: 'Smoke on the Water', sanatci: 'Deep Purple', bpm: 112 },
    { tur: 'rock', ad: "Sweet Child O' Mine", sanatci: "Guns N' Roses", bpm: 125 },
    { tur: 'rock', ad: 'Smells Like Teen Spirit', sanatci: 'Nirvana', bpm: 117 },
    { tur: 'rock', ad: 'Come As You Are', sanatci: 'Nirvana', bpm: 120 },
    { tur: 'rock', ad: 'Seven Nation Army', sanatci: 'The White Stripes', bpm: 124 },
    { tur: 'rock', ad: 'We Will Rock You', sanatci: 'Queen', bpm: 81 },
    { tur: 'rock', ad: 'Should I Stay or Should I Go', sanatci: 'The Clash', bpm: 113 },
    { tur: 'rock', ad: '(I Can’t Get No) Satisfaction', sanatci: 'The Rolling Stones', bpm: 136 },
    { tur: 'rock', ad: 'Hotel California', sanatci: 'Eagles', bpm: 74 },
    { tur: 'rock', ad: 'Sultans of Swing', sanatci: 'Dire Straits', bpm: 148 },
    /* POP */
    { tur: 'pop', ad: 'Billie Jean', sanatci: 'Michael Jackson', bpm: 117 },
    { tur: 'pop', ad: 'Beat It', sanatci: 'Michael Jackson', bpm: 139 },
    { tur: 'pop', ad: 'Uptown Funk', sanatci: 'Mark Ronson ft. Bruno Mars', bpm: 115 },
    { tur: 'pop', ad: 'Blinding Lights', sanatci: 'The Weeknd', bpm: 171 },
    { tur: 'pop', ad: 'Shape of You', sanatci: 'Ed Sheeran', bpm: 96 },
    { tur: 'pop', ad: 'bad guy', sanatci: 'Billie Eilish', bpm: 135 },
    { tur: 'pop', ad: 'Rolling in the Deep', sanatci: 'Adele', bpm: 105 },
    { tur: 'pop', ad: 'Shake It Off', sanatci: 'Taylor Swift', bpm: 160 },
    { tur: 'pop', ad: 'Happy', sanatci: 'Pharrell Williams', bpm: 160 },
    { tur: 'pop', ad: 'Levitating', sanatci: 'Dua Lipa', bpm: 103 },
    { tur: 'pop', ad: 'Dance Monkey', sanatci: 'Tones and I', bpm: 98 },
    { tur: 'pop', ad: 'Get Lucky', sanatci: 'Daft Punk', bpm: 116 },
    /* JAZZ */
    { tur: 'jazz', ad: 'Take Five', sanatci: 'Dave Brubeck', bpm: 176, olcu: 5 },
    { tur: 'jazz', ad: 'So What', sanatci: 'Miles Davis', bpm: 136 },
    { tur: 'jazz', ad: 'Blue in Green', sanatci: 'Miles Davis', bpm: 55 },
    { tur: 'jazz', ad: 'Giant Steps', sanatci: 'John Coltrane', bpm: 272 },
    { tur: 'jazz', ad: 'Fly Me to the Moon', sanatci: 'Frank Sinatra', bpm: 116 },
    { tur: 'jazz', ad: 'Autumn Leaves', sanatci: 'Cannonball Adderley', bpm: 132 },
    { tur: 'jazz', ad: 'Take the “A” Train', sanatci: 'Duke Ellington', bpm: 160 },
    { tur: 'jazz', ad: 'Cantaloupe Island', sanatci: 'Herbie Hancock', bpm: 105 },
    { tur: 'jazz', ad: 'Watermelon Man', sanatci: 'Herbie Hancock', bpm: 120 }
  ];
  var TUR_ETIKET = { hepsi: '🎼 Tümü', metal: '🤘 Metal', rock: '🎸 Rock', pop: '🎤 Pop', jazz: '🎷 Jazz' };
  var seciliTur = 'hepsi';

  function sarkilariCiz() {
    var arama = tr_kucult(m.el.sarkiAra.value || '');
    m.el.sarkiListe.innerHTML = '';
    var gosterilen = 0;
    SARKILAR.forEach(function (s) {
      if (seciliTur !== 'hepsi' && s.tur !== seciliTur) { return; }
      if (arama && tr_kucult(s.ad + ' ' + s.sanatci).indexOf(arama) === -1) { return; }
      gosterilen++;
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'm-sarki';
      b.innerHTML = '<b>' + s.ad + '</b><i>' + s.sanatci + '</i>'
        + '<span>' + s.bpm + ' BPM' + (s.olcu ? ' · ' + s.olcu + '/4' : '') + '</span>';
      b.addEventListener('click', function () {
        bpmAyarla(s.bpm > 240 ? 240 : s.bpm);
        if (s.bpm > 240) { window.alert(s.ad + ' aslında ' + s.bpm + ' BPM — metronom üst sınırı 240 olarak ayarlandı.'); }
        if (s.olcu) { m.el.olcu.value = String(s.olcu); noktalariKur(); }
        document.querySelectorAll('.m-sarki').forEach(function (x) { x.classList.remove('secili'); });
        b.classList.add('secili');
        if (!m.calisiyor) { metronomBaslat(); } /* seçince direkt çalsın */
      });
      m.el.sarkiListe.appendChild(b);
    });
    if (!gosterilen) {
      m.el.sarkiListe.innerHTML = '<span class="alan-ipucu">Eşleşen şarkı yok.</span>';
    }
  }
  function tr_kucult(s) {
    return s.replace(/İ/g, 'i').replace(/I/g, 'ı').toLowerCase();
  }
  function turleriCiz() {
    m.el.sarkiTurler.innerHTML = '';
    Object.keys(TUR_ETIKET).forEach(function (tur) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'm-tur-chip' + (tur === seciliTur ? ' aktif' : '');
      b.textContent = TUR_ETIKET[tur];
      b.addEventListener('click', function () {
        seciliTur = tur;
        turleriCiz();
        sarkilariCiz();
      });
      m.el.sarkiTurler.appendChild(b);
    });
  }
  if (m.el.sarkiListe) {
    turleriCiz();
    sarkilariCiz();
    m.el.sarkiAra.addEventListener('input', sarkilariCiz);
  }

  bpmAyarla(92);
  noktalariKur();
  presetCiz();

  /* ================================================================
     Sekmeler
     ================================================================ */
  document.querySelectorAll('.m-sekme').forEach(function (s) {
    s.addEventListener('click', function () {
      document.querySelectorAll('.m-sekme').forEach(function (x) { x.classList.remove('aktif'); });
      s.classList.add('aktif');
      document.querySelectorAll('.m-sekme-icerik').forEach(function (x) { x.hidden = true; });
      byId('sekme-' + s.dataset.sekme).hidden = false;
    });
  });

  /* Kaydet formu: öğrenci seçilmeden gönderilmesin */
  function kaydetFormuBagla(onek, ogrenciSelId) {
    var form = byId(onek + 'Form');
    if (!form) { return; }
    form.addEventListener('submit', function (ev) {
      var secim = byId(ogrenciSelId).value;
      if (!secim) {
        ev.preventDefault();
        window.alert('Kaydetmek için önce öğrenci seçin.');
        return;
      }
      byId(onek + 'FormOgrenci').value = secim;
    });
  }

  /* ================================================================
     B) VURUŞ TUTTURMA TESTİ
     ================================================================ */
  var vt = {
    aktif: false, zamanlayici: null, sonrakiZaman: 0, vurusNo: 0,
    vuruslar: [], taplar: [], bpm: 72, fazVurus: 16, toplamVurus: 0, bitisZamani: 0
  };

  function vtBaslat() {
    if (m.calisiyor) { metronomDurdur(); }
    digerleriniIptalEt('vt');
    ses.hazirla();
    ses.duzey(parseInt(m.el.duzey.value, 10) / 100);
    vt.bpm = parseInt(byId('vtBpm').value, 10);
    vt.fazVurus = parseInt(byId('vtVurusSayisi').value, 10);
    vt.toplamVurus = 4 + vt.fazVurus * 2;
    vt.vuruslar = [];
    vt.taplar = [];
    vt.vurusNo = 0;
    vt.sonrakiZaman = ses.ctx.currentTime + 0.6;
    vt.aktif = true;
    byId('vtSahne').hidden = false;
    byId('vtSonuc').hidden = true;
    byId('vtIlerleme').style.width = '0';
    vtFazEtiketi(0);
    vt.zamanlayici = setInterval(vtPlanla, 25);
  }

  function vtFazEtiketi(faz) {
    var el = byId('vtFaz');
    el.classList.toggle('sessiz-faz', faz === 2);
    el.textContent = faz === 0 ? '🎧 Hazırlık — yalnız dinle'
      : faz === 1 ? '🔊 Sesli faz — metronomla birlikte vur'
      : '🔇 Sessiz faz — içinden say, vurmaya devam et';
  }

  function vtPlanla() {
    while (vt.aktif && vt.sonrakiZaman < ses.ctx.currentTime + 0.12 && vt.vurusNo < vt.toplamVurus) {
      var faz = vt.vurusNo < 4 ? 0 : (vt.vurusNo < 4 + vt.fazVurus ? 1 : 2);
      if (faz < 2) {
        ses.vur(vt.sonrakiZaman, vt.vurusNo % 4 === 0, m.el.ses.value === 'sayma' ? 'tahta' : m.el.ses.value);
      }
      vt.vuruslar.push({ zaman: vt.sonrakiZaman, faz: faz });
      (function (no, f, zamanX) {
        var gecikme = Math.max(0, (zamanX - ses.ctx.currentTime) * 1000);
        setTimeout(function () {
          if (!vt.aktif) { return; }
          vtFazEtiketi(f);
          byId('vtIlerleme').style.width = Math.round(100 * (no + 1) / vt.toplamVurus) + '%';
        }, gecikme);
      })(vt.vurusNo, faz, vt.sonrakiZaman);
      vt.sonrakiZaman += 60 / vt.bpm;
      vt.vurusNo++;
    }
    if (vt.vurusNo >= vt.toplamVurus) {
      vt.bitisZamani = vt.vuruslar[vt.vuruslar.length - 1].zaman + 60 / vt.bpm;
      if (ses.ctx.currentTime > vt.bitisZamani + 0.3) {
        clearInterval(vt.zamanlayici);
        vt.aktif = false;
        vtDegerlendir();
      }
    }
  }

  function vtTap() {
    if (!vt.aktif) { return; }
    vt.taplar.push(ses.ctx.currentTime);
    var pad = byId('vtPad');
    pad.classList.add('vurdum');
    setTimeout(function () { pad.classList.remove('vurdum'); }, 90);
  }

  function vtDegerlendir() {
    var aralikMs = 60000 / vt.bpm;
    var fazlar = { 1: { sapmalar: [], vurulan: {} }, 2: { sapmalar: [], vurulan: {} } };
    var grafik = [];

    vt.taplar.forEach(function (tap) {
      var enYakin = null, enKucukFark = Infinity, enYakinIdx = -1;
      vt.vuruslar.forEach(function (v, i) {
        var fark = Math.abs(tap - v.zaman);
        if (fark < enKucukFark) { enKucukFark = fark; enYakin = v; enYakinIdx = i; }
      });
      if (!enYakin || enYakin.faz === 0) { return; }
      var sapmaMs = (tap - enYakin.zaman) * 1000;
      var kacik = Math.abs(sapmaMs) > aralikMs * 0.45;
      if (!kacik) {
        fazlar[enYakin.faz].sapmalar.push(sapmaMs);
        fazlar[enYakin.faz].vurulan[enYakinIdx] = true;
      }
      grafik.push({ sapma: sapmaMs, faz: enYakin.faz, kacik: kacik });
    });

    function fazOzet(f) {
      var s = fazlar[f].sapmalar;
      var vurulanAdet = Object.keys(fazlar[f].vurulan).length;
      var kacirilan = vt.fazVurus - vurulanAdet;
      var mutlak = ort(s.map(Math.abs));
      var hamSkor = s.length ? Math.max(0, 1 - mutlak / (0.30 * aralikMs)) : 0;
      var isabet = vurulanAdet / vt.fazVurus;
      return {
        n: s.length, kacirilan: kacirilan,
        ortSapma: Math.round(ort(s)), ortMutlak: Math.round(mutlak),
        skor: Math.round(100 * hamSkor * isabet)
      };
    }
    var f1 = fazOzet(1), f2 = fazOzet(2);
    var genel = Math.round(0.4 * f1.skor + 0.6 * f2.skor);

    byId('vtSahne').hidden = true;
    byId('vtSonuc').hidden = false;
    byId('vtSkor').textContent = genel;
    byId('vtTablo').innerHTML =
      '<tr><td>🔊 Sesli (metronomla)</td><td class="sayi">' + f1.n + '/' + vt.fazVurus + '</td>' +
      '<td class="sayi">' + f1.kacirilan + '</td><td class="sayi">' + (f1.ortSapma > 0 ? '+' : '') + f1.ortSapma + ' ms</td>' +
      '<td class="sayi">' + f1.ortMutlak + ' ms</td><td class="sayi"><strong>' + f1.skor + '</strong></td></tr>' +
      '<tr><td>🔇 Sessiz (içsel tempo)</td><td class="sayi">' + f2.n + '/' + vt.fazVurus + '</td>' +
      '<td class="sayi">' + f2.kacirilan + '</td><td class="sayi">' + (f2.ortSapma > 0 ? '+' : '') + f2.ortSapma + ' ms</td>' +
      '<td class="sayi">' + f2.ortMutlak + ' ms</td><td class="sayi"><strong>' + f2.skor + '</strong></td></tr>';

    var g = byId('vtGrafik');
    g.innerHTML = '';
    grafik.forEach(function (v) {
      var c = document.createElement('span');
      var yukseklik = Math.min(40, Math.abs(v.sapma) / (aralikMs * 0.45) * 40);
      c.className = 'm-sapma-cubuk ' + (v.kacik ? 'kacik' : (v.sapma > 15 ? 'gec' : (v.sapma < -15 ? 'erken' : '')));
      c.style.height = Math.max(4, Math.round(yukseklik)) + 'px';
      c.title = (v.sapma > 0 ? '+' : '') + Math.round(v.sapma) + ' ms (' + (v.faz === 1 ? 'sesli' : 'sessiz') + ')';
      g.appendChild(c);
    });

    var yorum = [];
    var bias = (f1.n + f2.n) ? ort([].concat(fazlar[1].sapmalar, fazlar[2].sapmalar)) : 0;
    if (bias > 15) { yorum.push('Vuruşlar geç kalma eğiliminde.'); }
    else if (bias < -15) { yorum.push('Vuruşlar erken gelme eğiliminde.'); }
    else { yorum.push('Erken/geç dengesi iyi.'); }
    if (f2.ortMutlak > f1.ortMutlak * 1.5 && f1.n > 0) {
      yorum.push('Metronom susunca sapma belirgin arttı — sessiz sürdürme çalışılabilir.');
    } else if (f2.n > 0 && f1.n > 0) {
      yorum.push('Sessiz fazda tempo büyük ölçüde korundu.');
    }
    byId('vtYorum').textContent = yorum.join(' ');

    byId('vtFormBpm').value = vt.bpm;
    byId('vtFormSkor').value = genel;
    byId('vtFormDetay').value = JSON.stringify({ bpm: vt.bpm, fazVurus: vt.fazVurus, sesli: f1, sessiz: f2 });
  }

  function vtIptalEt() {
    clearInterval(vt.zamanlayici);
    vt.aktif = false;
    byId('vtSahne').hidden = true;
  }

  byId('vtBaslat').addEventListener('click', vtBaslat);
  byId('vtIptal').addEventListener('click', vtIptalEt);
  byId('vtTekrar').addEventListener('click', function () { byId('vtSonuc').hidden = true; vtBaslat(); });
  byId('vtPad').addEventListener('pointerdown', function (ev) { ev.preventDefault(); vtTap(); });
  kaydetFormuBagla('vt', 'vtOgrenci');

  /* ================================================================
     C) BPM BULMA OYUNU
     ================================================================ */
  var bf = {
    aktif: false, dinlemede: false, zamanlayici: null,
    tur: 0, gercekBpm: 0, taplar: [], sonuclar: []
  };
  var BF_ARALIK = { kolay: [60, 100], orta: [50, 130], zor: [40, 160] };

  function bfBaslatOyun() {
    if (m.calisiyor) { metronomDurdur(); }
    digerleriniIptalEt('bf');
    ses.hazirla();
    ses.duzey(parseInt(m.el.duzey.value, 10) / 100);
    bf.tur = 0;
    bf.sonuclar = [];
    bf.aktif = true;
    byId('bfSahne').hidden = false;
    byId('bfSonuc').hidden = true;
    bfTurBaslat();
  }

  function bfTurBaslat() {
    bf.tur++;
    byId('bfTur').textContent = 'Tur ' + bf.tur + ' / 3';
    byId('bfFaz').textContent = '🎧 Dinle…';
    byId('bfFaz').classList.remove('sessiz-faz');
    bf.taplar = [];
    bf.dinlemede = true;
    var aralik = BF_ARALIK[byId('bfZorluk').value] || BF_ARALIK.kolay;
    bf.gercekBpm = Math.round(aralik[0] + Math.random() * (aralik[1] - aralik[0]));
    var baslangic = ses.ctx.currentTime + 0.5;
    var kit = m.el.ses.value === 'sayma' ? 'tahta' : m.el.ses.value;
    for (var i = 0; i < 8; i++) {
      ses.vur(baslangic + i * 60 / bf.gercekBpm, false, kit);
    }
    var bitis = baslangic + 8 * 60 / bf.gercekBpm;
    clearTimeout(bf.zamanlayici);
    bf.zamanlayici = setTimeout(function () {
      if (!bf.aktif) { return; }
      bf.dinlemede = false;
      byId('bfFaz').textContent = '🥁 Şimdi sürdür — 8 vuruş';
      byId('bfFaz').classList.add('sessiz-faz');
    }, (bitis - ses.ctx.currentTime) * 1000);
  }

  function bfTap() {
    if (!bf.aktif || bf.dinlemede) { return; }
    bf.taplar.push(ses.ctx.currentTime);
    var pad = byId('bfPad');
    pad.classList.add('vurdum');
    setTimeout(function () { pad.classList.remove('vurdum'); }, 90);
    byId('bfFaz').textContent = '🥁 Sürdür — ' + bf.taplar.length + ' / 8';
    if (bf.taplar.length >= 8) { bfTurBitir(); }
  }

  function bfTurBitir() {
    var araliklar = [];
    for (var i = 1; i < bf.taplar.length; i++) { araliklar.push(bf.taplar[i] - bf.taplar[i - 1]); }
    var tahmin = Math.round(60 / ort(araliklar));
    var hata = Math.abs(tahmin - bf.gercekBpm) / bf.gercekBpm * 100;
    var skor = Math.max(0, Math.round(100 - hata * 4));
    bf.sonuclar.push({ gercek: bf.gercekBpm, tahmin: tahmin, hata: Math.round(hata * 10) / 10, skor: skor });
    byId('bfFaz').textContent = '✔ Gerçek: ' + bf.gercekBpm + ' BPM · Senin tahminin: ' + tahmin + ' BPM';
    byId('bfFaz').classList.remove('sessiz-faz');
    if (bf.tur < 3) {
      bf.zamanlayici = setTimeout(function () { if (bf.aktif) { bfTurBaslat(); } }, 1800);
    } else {
      bf.zamanlayici = setTimeout(function () { if (bf.aktif) { bfBitir(); } }, 1400);
    }
  }

  function bfBitir() {
    bf.aktif = false;
    byId('bfSahne').hidden = true;
    byId('bfSonuc').hidden = false;
    var ortSkor = Math.round(ort(bf.sonuclar.map(function (s) { return s.skor; })));
    byId('bfSkor').textContent = ortSkor;
    byId('bfTablo').innerHTML = bf.sonuclar.map(function (s, i) {
      return '<tr><td>' + (i + 1) + '</td><td class="sayi">' + s.gercek + '</td>' +
             '<td class="sayi">' + s.tahmin + '</td><td class="sayi">%' + s.hata + '</td>' +
             '<td class="sayi"><strong>' + s.skor + '</strong></td></tr>';
    }).join('');
    byId('bfFormBpm').value = Math.round(ort(bf.sonuclar.map(function (s) { return s.gercek; })));
    byId('bfFormSkor').value = ortSkor;
    byId('bfFormDetay').value = JSON.stringify({ turlar: bf.sonuclar });
  }

  function bfIptalEt() {
    clearTimeout(bf.zamanlayici);
    bf.aktif = false;
    byId('bfSahne').hidden = true;
  }

  byId('bfBaslat').addEventListener('click', bfBaslatOyun);
  byId('bfIptal').addEventListener('click', bfIptalEt);
  byId('bfTekrar').addEventListener('click', function () { byId('bfSonuc').hidden = true; bfBaslatOyun(); });
  byId('bfPad').addEventListener('pointerdown', function (ev) { ev.preventDefault(); bfTap(); });
  kaydetFormuBagla('bf', 'bfOgrenci');

  /* ================================================================
     D) RİTİM OKUMA (stüdyo sürümü — ritim-okuma.js widget'ı)
     ================================================================ */
  var roKok = byId('roKok');
  function roKur() {
    if (!roKok || !window.RitimOkuma) { return; }
    byId('roKaydetSatir').hidden = true;
    window.RitimOkuma.baslat(roKok, {
      seviye: parseInt(byId('roSeviye').value, 10),
      bpm: parseInt(byId('roBpm').value, 10),
      onBitti: function (sonuc) {
        byId('roFormBpm').value = sonuc.bpm;
        byId('roFormSkor').value = sonuc.skor;
        byId('roFormDetay').value = JSON.stringify(sonuc);
        byId('roKaydetSatir').hidden = false;
      }
    });
  }
  if (roKok) {
    roKur();
    byId('roYenile').addEventListener('click', roKur);
    byId('roSeviye').addEventListener('change', roKur);
    byId('roBpm').addEventListener('change', roKur);
    kaydetFormuBagla('ro', 'roOgrenci');
  }

  /* ================================================================
     E) SPONTAN TEMPO (BAASTA: unpaced tapping)
     ================================================================ */
  var st = { aktif: false, taplar: [], HEDEF: 21 };

  function stBaslat() {
    if (m.calisiyor) { metronomDurdur(); }
    digerleriniIptalEt('st');
    ses.hazirla();
    st.taplar = [];
    st.aktif = true;
    byId('stSahne').hidden = false;
    byId('stSonuc').hidden = true;
    byId('stSayac').textContent = '0 / ' + st.HEDEF;
    byId('stFaz').textContent = 'Kendi hızında vur — acele yok';
  }

  function stTap() {
    if (!st.aktif) { return; }
    st.taplar.push(ses.ctx.currentTime);
    var pad = byId('stPad');
    pad.classList.add('vurdum');
    setTimeout(function () { pad.classList.remove('vurdum'); }, 80);
    byId('stSayac').textContent = st.taplar.length + ' / ' + st.HEDEF;
    if (st.taplar.length >= st.HEDEF) { stBitir(); }
  }

  function stBitir() {
    st.aktif = false;
    byId('stSahne').hidden = true;
    byId('stSonuc').hidden = false;

    var araliklar = [];
    for (var i = 1; i < st.taplar.length; i++) { araliklar.push((st.taplar[i] - st.taplar[i - 1]) * 1000); }
    var ortMs = ort(araliklar);
    var varyans = ort(araliklar.map(function (a) { return (a - ortMs) * (a - ortMs); }));
    var cv = ortMs > 0 ? Math.sqrt(varyans) / ortMs : 1;
    var smt = ortMs > 0 ? Math.round(60000 / ortMs) : 0;
    var skor = Math.max(0, Math.min(100, Math.round(100 * (1 - (cv - 0.02) / 0.10))));

    byId('stSkor').textContent = skor;
    byId('stOzet').innerHTML = 'Spontan tempon: <strong>' + smt + ' BPM</strong> '
      + '(ortalama aralık ' + Math.round(ortMs) + ' ms · dalgalanma CV %' + (cv * 100).toFixed(1) + ')';

    var g = byId('stGrafik');
    g.innerHTML = '';
    var enB = Math.max.apply(null, araliklar);
    araliklar.forEach(function (a) {
      var c = document.createElement('span');
      c.className = 'm-sapma-cubuk ' + (Math.abs(a - ortMs) / ortMs > 0.1 ? 'gec' : '');
      c.style.height = Math.max(5, Math.round(60 * a / enB)) + 'px';
      c.style.alignSelf = 'flex-end';
      c.title = Math.round(a) + ' ms';
      g.appendChild(c);
    });

    byId('stFormBpm').value = smt;
    byId('stFormSkor').value = skor;
    byId('stFormDetay').value = JSON.stringify({ smt: smt, ortMs: Math.round(ortMs), cv: Math.round(cv * 1000) / 1000, vurus: st.taplar.length });
  }

  function stIptalEt() {
    st.aktif = false;
    byId('stSahne').hidden = true;
  }

  byId('stBaslat').addEventListener('click', stBaslat);
  byId('stIptal').addEventListener('click', stIptalEt);
  byId('stTekrar').addEventListener('click', function () { byId('stSonuc').hidden = true; stBaslat(); });
  byId('stPad').addEventListener('pointerdown', function (ev) { ev.preventDefault(); stTap(); });
  kaydetFormuBagla('st', 'stOgrenci');

  /* ================================================================
     F) AKSAK BULMA (BAASTA: anizokroni algısı)
     ================================================================ */
  var ab = { aktif: false, tur: 0, TOPLAM: 8, dogru: 0, aksakMi: false, zamanlayici: null, sonuclar: [] };
  var AB_KAYMA = { kolay: 0.15, orta: 0.10, zor: 0.06 };

  function abBaslat() {
    if (m.calisiyor) { metronomDurdur(); }
    digerleriniIptalEt('ab');
    ses.hazirla();
    ses.duzey(parseInt(m.el.duzey.value, 10) / 100);
    ab.tur = 0;
    ab.dogru = 0;
    ab.sonuclar = [];
    ab.aktif = true;
    byId('abSahne').hidden = false;
    byId('abSonuc').hidden = true;
    abTurBaslat();
  }

  function abTurBaslat() {
    ab.tur++;
    byId('abTur').textContent = 'Tur ' + ab.tur + ' / ' + ab.TOPLAM;
    byId('abFaz').textContent = '🎧 Dinle…';
    byId('abCevaplar').hidden = true;
    ab.aksakMi = Math.random() < 0.5;
    var kayma = AB_KAYMA[byId('abZorluk').value] || 0.15;
    var IOI = 0.6; /* 100 BPM */
    var aksakIdx = 2 + Math.floor(Math.random() * 3); /* 3.–5. vuruş */
    var yon = Math.random() < 0.5 ? -1 : 1;
    var t = ses.ctx.currentTime + 0.5;
    var kit = m.el.ses.value === 'sayma' ? 'tahta' : m.el.ses.value;
    var zamanlar = [];
    for (var i = 0; i < 6; i++) {
      var z = t + i * IOI;
      if (ab.aksakMi && i === aksakIdx) { z += yon * kayma * IOI; }
      zamanlar.push(z);
    }
    zamanlar.forEach(function (z) { ses.vur(z, false, kit); });
    var bitis = t + 5 * IOI + 0.35;
    clearTimeout(ab.zamanlayici);
    ab.zamanlayici = setTimeout(function () {
      if (!ab.aktif) { return; }
      byId('abFaz').textContent = '❓ Dizi nasıldı?';
      byId('abCevaplar').hidden = false;
    }, (bitis - ses.ctx.currentTime) * 1000);
  }

  function abCevap(cevap) {
    if (!ab.aktif || byId('abCevaplar').hidden) { return; }
    byId('abCevaplar').hidden = true;
    var dogruCevap = ab.aksakMi ? 'aksak' : 'duzenli';
    var dogruMu = cevap === dogruCevap;
    if (dogruMu) { ab.dogru++; }
    ab.sonuclar.push({ dizi: ab.aksakMi ? 'Aksak' : 'Düzenli',
                       cevap: cevap === 'aksak' ? 'Aksadı' : 'Düzenliydi', dogru: dogruMu });
    byId('abFaz').textContent = (dogruMu ? '✅ Doğru!' : '❌ Değildi — dizi ' + (ab.aksakMi ? 'aksaktı.' : 'düzenliydi.'));
    if (ab.tur < ab.TOPLAM) {
      ab.zamanlayici = setTimeout(function () { if (ab.aktif) { abTurBaslat(); } }, 1300);
    } else {
      ab.zamanlayici = setTimeout(function () { if (ab.aktif) { abBitir(); } }, 1300);
    }
  }

  function abBitir() {
    ab.aktif = false;
    byId('abSahne').hidden = true;
    byId('abSonuc').hidden = false;
    var skor = Math.round(100 * ab.dogru / ab.TOPLAM);
    byId('abSkor').textContent = skor;
    byId('abTablo').innerHTML = ab.sonuclar.map(function (s, i) {
      return '<tr><td>' + (i + 1) + '</td><td>' + s.dizi + '</td><td>' + s.cevap + '</td>' +
             '<td>' + (s.dogru ? '✅' : '❌') + '</td></tr>';
    }).join('');
    byId('abFormBpm').value = 100;
    byId('abFormSkor').value = skor;
    byId('abFormDetay').value = JSON.stringify({ zorluk: byId('abZorluk').value, dogru: ab.dogru, tur: ab.TOPLAM, sonuclar: ab.sonuclar });
  }

  function abIptalEt() {
    clearTimeout(ab.zamanlayici);
    ab.aktif = false;
    byId('abSahne').hidden = true;
  }

  byId('abBaslat').addEventListener('click', abBaslat);
  byId('abIptal').addEventListener('click', abIptalEt);
  byId('abTekrar').addEventListener('click', function () { byId('abSonuc').hidden = true; abBaslat(); });
  document.querySelectorAll('.ab-cevap').forEach(function (b) {
    b.addEventListener('click', function () { abCevap(b.dataset.cevap); });
  });
  kaydetFormuBagla('ab', 'abOgrenci');

  /* ================================================================
     G) İÇSEL RİTİM — sessizlik merdiveni (%0 → %25 → %50 → %75)
     "Rastgele sus"un ölçülen protokol hâli: sessiz vuruş sapması ayrı izlenir.
     ================================================================ */
  var ir = {
    aktif: false, zamanlayici: null, sonrakiZaman: 0, vurusNo: 0,
    vuruslar: [], taplar: [], bpm: 72,
    FAZ_YUZDE: [0, 25, 50, 75], FAZ_VURUS: 8
  };

  function irPlanOlustur() {
    /* 4 hazırlık + 4 faz × 8 vuruş; her fazda tam yüzde kadar vuruş susturulur
       (fazın ilk vuruşu çapa olarak daima seslidir). */
    ir.vuruslar = [];
    for (var h = 0; h < 4; h++) { ir.vuruslar.push({ faz: -1, sessiz: false }); }
    ir.FAZ_YUZDE.forEach(function (yuzde, fazNo) {
      var susAdet = Math.round(ir.FAZ_VURUS * yuzde / 100);
      var adaylar = [];
      for (var i = 1; i < ir.FAZ_VURUS; i++) { adaylar.push(i); }
      /* karıştır ve ilk susAdet konumu sustur */
      for (var k = adaylar.length - 1; k > 0; k--) {
        var r = Math.floor(Math.random() * (k + 1));
        var tmp = adaylar[k]; adaylar[k] = adaylar[r]; adaylar[r] = tmp;
      }
      var susSet = {};
      adaylar.slice(0, susAdet).forEach(function (p) { susSet[p] = true; });
      for (var v = 0; v < ir.FAZ_VURUS; v++) {
        ir.vuruslar.push({ faz: fazNo, sessiz: !!susSet[v] });
      }
    });
  }

  function irBaslat() {
    if (m.calisiyor) { metronomDurdur(); }
    digerleriniIptalEt('ir');
    ses.hazirla();
    ses.duzey(parseInt(m.el.duzey.value, 10) / 100);
    ir.bpm = parseInt(byId('irBpm').value, 10);
    irPlanOlustur();
    ir.taplar = [];
    ir.vurusNo = 0;
    ir.sonrakiZaman = ses.ctx.currentTime + 0.6;
    ir.aktif = true;
    byId('irSahne').hidden = false;
    byId('irSonuc').hidden = true;
    byId('irIlerleme').style.width = '0';
    byId('irFaz').textContent = '🎧 Hazırlık — dinle, sonra her vuruşta vur';
    ir.zamanlayici = setInterval(irPlanla, 25);
  }

  function irPlanla() {
    var kit = m.el.ses.value === 'sayma' ? 'tahta' : m.el.ses.value;
    while (ir.aktif && ir.sonrakiZaman < ses.ctx.currentTime + 0.12 && ir.vurusNo < ir.vuruslar.length) {
      var v = ir.vuruslar[ir.vurusNo];
      v.zaman = ir.sonrakiZaman;
      if (!v.sessiz) {
        ses.vur(ir.sonrakiZaman, ir.vurusNo % 4 === 0, kit);
      }
      (function (no, vv) {
        var gecikme = Math.max(0, (vv.zaman - ses.ctx.currentTime) * 1000);
        setTimeout(function () {
          if (!ir.aktif) { return; }
          if (vv.faz >= 0) {
            byId('irFaz').textContent = '🥁 Faz ' + (vv.faz + 1) + ' — %' + ir.FAZ_YUZDE[vv.faz] + ' sessiz · vurmaya devam';
          }
          byId('irIlerleme').style.width = Math.round(100 * (no + 1) / ir.vuruslar.length) + '%';
        }, gecikme);
      })(ir.vurusNo, v);
      ir.sonrakiZaman += 60 / ir.bpm;
      ir.vurusNo++;
    }
    if (ir.vurusNo >= ir.vuruslar.length) {
      var bitis = ir.vuruslar[ir.vuruslar.length - 1].zaman + 60 / ir.bpm;
      if (ses.ctx.currentTime > bitis + 0.3) {
        clearInterval(ir.zamanlayici);
        ir.aktif = false;
        irDegerlendir();
      }
    }
  }

  function irTap() {
    if (!ir.aktif) { return; }
    ir.taplar.push(ses.ctx.currentTime);
    var pad = byId('irPad');
    pad.classList.add('vurdum');
    setTimeout(function () { pad.classList.remove('vurdum'); }, 80);
  }

  function irDegerlendir() {
    var aralikMs = 60000 / ir.bpm;
    var fazVeri = ir.FAZ_YUZDE.map(function () { return { sapmalar: [], vurulan: {} }; });
    var sesliSapma = [], sessizSapma = [];
    var grafik = [];

    ir.taplar.forEach(function (tap) {
      var enYakin = null, enKucuk = Infinity, enIdx = -1;
      ir.vuruslar.forEach(function (v, i) {
        var fark = Math.abs(tap - v.zaman);
        if (fark < enKucuk) { enKucuk = fark; enYakin = v; enIdx = i; }
      });
      if (!enYakin || enYakin.faz < 0) { return; }
      var sapmaMs = (tap - enYakin.zaman) * 1000;
      var kacik = Math.abs(sapmaMs) > aralikMs * 0.45;
      if (!kacik) {
        fazVeri[enYakin.faz].sapmalar.push(sapmaMs);
        fazVeri[enYakin.faz].vurulan[enIdx] = true;
        (enYakin.sessiz ? sessizSapma : sesliSapma).push(Math.abs(sapmaMs));
      }
      grafik.push({ sapma: sapmaMs, sessiz: enYakin.sessiz, kacik: kacik });
    });

    var AGIRLIK = [0.1, 0.2, 0.3, 0.4];
    var fazlar = fazVeri.map(function (f, i) {
      var mutlak = ort(f.sapmalar.map(Math.abs));
      var vurulanAdet = Object.keys(f.vurulan).length;
      var hamSkor = f.sapmalar.length ? Math.max(0, 1 - mutlak / (0.30 * aralikMs)) : 0;
      return {
        yuzde: ir.FAZ_YUZDE[i], n: f.sapmalar.length,
        kacirilan: ir.FAZ_VURUS - vurulanAdet,
        ortMutlak: Math.round(mutlak),
        skor: Math.round(100 * hamSkor * (vurulanAdet / ir.FAZ_VURUS))
      };
    });
    var genel = Math.round(fazlar.reduce(function (t, f, i) { return t + f.skor * AGIRLIK[i]; }, 0));

    byId('irSahne').hidden = true;
    byId('irSonuc').hidden = false;
    byId('irSkor').textContent = genel;
    byId('irTablo').innerHTML = fazlar.map(function (f, i) {
      return '<tr><td>Faz ' + (i + 1) + ' — %' + f.yuzde + ' sessiz</td>' +
        '<td class="sayi">' + f.n + '/' + ir.FAZ_VURUS + '</td>' +
        '<td class="sayi">' + f.kacirilan + '</td>' +
        '<td class="sayi">' + f.ortMutlak + ' ms</td>' +
        '<td class="sayi"><strong>' + f.skor + '</strong></td></tr>';
    }).join('');

    var g = byId('irGrafik');
    g.innerHTML = '';
    grafik.forEach(function (v) {
      var c = document.createElement('span');
      var yukseklik = Math.min(40, Math.abs(v.sapma) / (aralikMs * 0.45) * 40);
      c.className = 'm-sapma-cubuk ' + (v.kacik ? 'kacik' : (v.sessiz ? 'sessizv' : (v.sapma > 15 ? 'gec' : (v.sapma < -15 ? 'erken' : ''))));
      c.style.height = Math.max(4, Math.round(yukseklik)) + 'px';
      c.title = (v.sapma > 0 ? '+' : '') + Math.round(v.sapma) + ' ms (' + (v.sessiz ? 'sessiz vuruş' : 'sesli vuruş') + ')';
      g.appendChild(c);
    });

    var sesliOrt = Math.round(ort(sesliSapma));
    var sessizOrt = Math.round(ort(sessizSapma));
    var yorum;
    if (!sessizSapma.length) {
      yorum = 'Sessiz vuruşlarda yeterli veri yok — test yarıda kalmış olabilir.';
    } else if (sesliOrt > 0 && sessizOrt <= sesliOrt * 1.3) {
      yorum = 'İçsel sayım güçlü: metronom sussa da sapma neredeyse değişmedi (' + sesliOrt + ' → ' + sessizOrt + ' ms).';
    } else if (sesliOrt > 0 && sessizOrt <= sesliOrt * 2) {
      yorum = 'Sessiz vuruşlarda sapma arttı (' + sesliOrt + ' → ' + sessizOrt + ' ms) — içsel sayım gelişmeye açık.';
    } else {
      yorum = 'Metronom susunca sapma belirgin arttı (' + sesliOrt + ' → ' + sessizOrt + ' ms) — düşük yüzdelerle çalışmaya devam.';
    }
    byId('irYorum').textContent = yorum;

    byId('irFormBpm').value = ir.bpm;
    byId('irFormSkor').value = genel;
    byId('irFormDetay').value = JSON.stringify({
      bpm: ir.bpm, fazlar: fazlar, sesliOrtMs: sesliOrt, sessizOrtMs: sessizOrt
    });
  }

  function irIptalEt() {
    clearInterval(ir.zamanlayici);
    ir.aktif = false;
    byId('irSahne').hidden = true;
  }

  byId('irBaslat').addEventListener('click', irBaslat);
  byId('irIptal').addEventListener('click', irIptalEt);
  byId('irTekrar').addEventListener('click', function () { byId('irSonuc').hidden = true; irBaslat(); });
  byId('irPad').addEventListener('pointerdown', function (ev) { ev.preventDefault(); irTap(); });
  kaydetFormuBagla('ir', 'irOgrenci');

  /* Bir test başlarken diğerlerini iptal et */
  function digerleriniIptalEt(haric) {
    if (haric !== 'vt' && vt.aktif) { vtIptalEt(); }
    if (haric !== 'bf' && bf.aktif) { bfIptalEt(); }
    if (haric !== 'st' && st.aktif) { stIptalEt(); }
    if (haric !== 'ab' && ab.aktif) { abIptalEt(); }
    if (haric !== 'ir' && ir.aktif) { irIptalEt(); }
  }

  /* ================================================================
     Klavye kısayolları
     ================================================================ */
  document.addEventListener('keydown', function (ev) {
    var etiket = (ev.target.tagName || '').toLowerCase();
    if (etiket === 'input' || etiket === 'select' || etiket === 'textarea') { return; }
    if (ev.code === 'Space') {
      ev.preventDefault();
      if (vt.aktif) { vtTap(); }
      else if (bf.aktif) { bfTap(); }
      else if (st.aktif) { stTap(); }
      else if (ir.aktif) { irTap(); }
      else if (roKok && roKok.__roAktif && roKok.__roAktif()) { roKok.__roTap(); }
      else if (m.calisiyor) { metronomDurdur(); }
      else { metronomBaslat(); }
    } else if (ev.key === 't' || ev.key === 'T') {
      tapTempo();
    } else if (ev.key === 'ArrowUp') {
      ev.preventDefault(); bpmAyarla(m.bpm + (ev.shiftKey ? 5 : 1));
    } else if (ev.key === 'ArrowDown') {
      ev.preventDefault(); bpmAyarla(m.bpm - (ev.shiftKey ? 5 : 1));
    }
  });
})();
