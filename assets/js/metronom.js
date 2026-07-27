/* =======================================================
   Metronom Stüdyosu — Web Audio motoru + dikkat protokolleri
   Zamanlama: lookahead planlayıcı (25 ms tarama, 120 ms ileri)
   ======================================================= */
(function () {
  'use strict';

  function byId(id) { return document.getElementById(id); }

  /* ---------- Ses motoru ---------- */
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
    /* Tek vuruş sesi. tur: 'tahta' | 'klik' | 'bip' */
    vur: function (zaman, aksan, tur) {
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
      } else { /* tahta blok: gövde + klik geçici */
        var o3 = ctx.createOscillator();
        o3.type = 'sine';
        o3.frequency.setValueAtTime(aksan ? 980 : 720, zaman);
        o3.frequency.exponentialRampToValueAtTime(aksan ? 640 : 480, zaman + 0.05);
        g.gain.setValueAtTime(aksan ? 0.7 : 0.5, zaman);
        g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.07);
        o3.connect(g); o3.start(zaman); o3.stop(zaman + 0.08);
        var t = ctx.createOscillator();
        var tg = ctx.createGain();
        t.type = 'square';
        t.frequency.value = 2200;
        tg.gain.setValueAtTime(0.12, zaman);
        tg.gain.exponentialRampToValueAtTime(0.001, zaman + 0.015);
        t.connect(tg).connect(this.master);
        t.start(zaman); t.stop(zaman + 0.02);
      }
    }
  };

  /* ================================================================
     A) SERBEST METRONOM
     ================================================================ */
  var m = {
    bpm: 92, calisiyor: false, zamanlayici: null,
    sonrakiZaman: 0, vurusNo: 0,
    sarkacYonu: 1,
    el: {
      bpm: byId('mBpm'), tempoAdi: byId('mTempoAdi'), surgu: byId('mBpmSurgu'),
      noktalar: byId('mNoktalar'), olcu: byId('mOlcu'), ses: byId('mSes'),
      duzey: byId('mSesDuzeyi'), aksan: byId('mAksan'),
      sessizModu: byId('mSessizModu'), sessizSecim: byId('mSessizSecim'),
      sesliOlcu: byId('mSesliOlcu'), sessizOlcu: byId('mSessizOlcu'),
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

  function noktalariKur() {
    var adet = parseInt(m.el.olcu.value, 10);
    m.el.noktalar.innerHTML = '';
    for (var i = 0; i < adet; i++) {
      var n = document.createElement('span');
      n.className = 'm-nokta';
      m.el.noktalar.appendChild(n);
    }
  }

  function gorselVurus(zaman, olcuIcindeki, sesli) {
    var gecikme = Math.max(0, (zaman - ses.ctx.currentTime) * 1000);
    var sure = 60 / m.bpm;
    setTimeout(function () {
      if (!m.calisiyor) { return; }
      /* sarkaç: her vuruşta yön değiştir */
      m.sarkacYonu = -m.sarkacYonu;
      m.el.sarkac.style.transition = 'transform ' + sure.toFixed(3) + 's ease-in-out';
      m.el.sarkac.style.transform = 'rotate(' + (26 * m.sarkacYonu) + 'deg)';
      var noktalar = m.el.noktalar.children;
      for (var i = 0; i < noktalar.length; i++) {
        noktalar[i].className = 'm-nokta' + (sesli ? '' : ' sessiz');
      }
      if (sesli && noktalar[olcuIcindeki]) {
        noktalar[olcuIcindeki].className = 'm-nokta ' +
          (olcuIcindeki === 0 && m.el.aksan.checked ? 'aktif-aksan' : 'aktif');
      }
      if (sesli) {
        m.el.halka.classList.add(olcuIcindeki === 0 && m.el.aksan.checked ? 'vur-aksan' : 'vur');
        setTimeout(function () { m.el.halka.classList.remove('vur', 'vur-aksan'); }, 100);
      }
    }, gecikme);
  }

  function planla() {
    var olcuAdedi = parseInt(m.el.olcu.value, 10);
    while (m.sonrakiZaman < ses.ctx.currentTime + 0.12) {
      var olcuIcindeki = m.vurusNo % olcuAdedi;
      var olcuNo = Math.floor(m.vurusNo / olcuAdedi);
      var sesli = true;
      if (m.el.sessizModu.checked) {
        var a = parseInt(m.el.sesliOlcu.value, 10);
        var s = parseInt(m.el.sessizOlcu.value, 10);
        sesli = (olcuNo % (a + s)) < a;
      }
      if (sesli) {
        ses.vur(m.sonrakiZaman, olcuIcindeki === 0 && m.el.aksan.checked, m.el.ses.value);
      }
      gorselVurus(m.sonrakiZaman, olcuIcindeki, sesli);
      m.sonrakiZaman += 60 / m.bpm;
      m.vurusNo++;
    }
  }

  function metronomBaslat() {
    ses.hazirla();
    ses.duzey(parseInt(m.el.duzey.value, 10) / 100);
    m.vurusNo = 0;
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
    noktalariKur();
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
  m.el.sessizModu.addEventListener('change', function () {
    m.el.sessizSecim.hidden = !this.checked;
  });

  /* Tap tempo: son 5 dokunuşun ortalaması */
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

  bpmAyarla(92);
  noktalariKur();

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

  /* ================================================================
     Ortak test yardımcıları
     ================================================================ */
  function ort(dizi) {
    if (!dizi.length) { return 0; }
    return dizi.reduce(function (a, b) { return a + b; }, 0) / dizi.length;
  }

  /* ================================================================
     B) VURUŞ TUTTURMA TESTİ
     Akış: 4 vuruş hazırlık (sesli) → N vuruş sesli faz → N vuruş sessiz faz
     ================================================================ */
  var vt = {
    aktif: false, zamanlayici: null, sonrakiZaman: 0, vurusNo: 0,
    vuruslar: [],  /* {zaman, faz} faz: 0 hazırlık, 1 sesli, 2 sessiz */
    taplar: [], bpm: 72, fazVurus: 16, toplamVurus: 0, bitisZamani: 0
  };

  function vtBaslat() {
    if (m.calisiyor) { metronomDurdur(); }
    if (bf.aktif) { bfIptalEt(); }
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
        ses.vur(vt.sonrakiZaman, vt.vurusNo % 4 === 0, m.el.ses.value);
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
      if (!enYakin || enYakin.faz === 0) { return; } /* hazırlık vuruşları puanlanmaz */
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
    byId('vtFormDetay').value = JSON.stringify({
      bpm: vt.bpm, fazVurus: vt.fazVurus, sesli: f1, sessiz: f2
    });
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
  byId('vtForm').addEventListener('submit', function (ev) {
    var secim = byId('vtOgrenci').value;
    if (!secim) {
      ev.preventDefault();
      window.alert('Kaydetmek için önce öğrenci seçin (testin üstündeki liste).');
      return;
    }
    byId('vtFormOgrenci').value = secim;
  });

  /* ================================================================
     C) BPM BULMA OYUNU
     Tur: gizli tempoda 8 vuruş dinle → 8 vuruşla sürdür. 3 tur.
     ================================================================ */
  var bf = {
    aktif: false, dinlemede: false, zamanlayici: null,
    tur: 0, gercekBpm: 0, taplar: [], sonuclar: []
  };

  var BF_ARALIK = { kolay: [60, 100], orta: [50, 130], zor: [40, 160] };

  function bfBaslatOyun() {
    if (m.calisiyor) { metronomDurdur(); }
    if (vt.aktif) { vtIptalEt(); }
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
    for (var i = 0; i < 8; i++) {
      ses.vur(baslangic + i * 60 / bf.gercekBpm, false, m.el.ses.value);
    }
    var bitis = baslangic + 8 * 60 / bf.gercekBpm;
    var bekle = (bitis - ses.ctx.currentTime) * 1000;
    clearTimeout(bf.zamanlayici);
    bf.zamanlayici = setTimeout(function () {
      if (!bf.aktif) { return; }
      bf.dinlemede = false;
      byId('bfFaz').textContent = '🥁 Şimdi sürdür — 8 vuruş';
      byId('bfFaz').classList.add('sessiz-faz');
    }, bekle);
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
  byId('bfForm').addEventListener('submit', function (ev) {
    var secim = byId('bfOgrenci').value;
    if (!secim) {
      ev.preventDefault();
      window.alert('Kaydetmek için önce öğrenci seçin (oyunun üstündeki liste).');
      return;
    }
    byId('bfFormOgrenci').value = secim;
  });

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
