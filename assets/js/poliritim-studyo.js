/* ================================================================
   Poliritim Stüdyosu — arayüz, ses ve animasyon katmanı.

   Matematik ve puanlama poliritim-cekirdegi.js'te (Node ile test edilir);
   bu dosya yalnız zamanlama motoru, çizim ve etkileşimi yönetir.

   TASARIM KARARLARI
   - İki AYRI ses: sağ el parlak/yüksek, sol el yumuşak/kalın, ortak vuruş
     ikisi birlikte + stereo ayrım. Kulak elleri sesle ayırt edebilmeli,
     yoksa iki ritmi birbirinden ayırmak imkânsızlaşır.
   - Her el KENDİ hedefiyle değerlendirilir (çekirdek şart koşuyor): ortak
     vuruşta sağ elin dokunuşu sol elin hedefine sayılmamalı.
   - Aşamalı çalışma: tek el → öteki el → iki el rehberli → iki el sessiz.
     Yalnız son aşama ölçüm; ilk üçü kayıt üretmez.
   ================================================================ */
(function () {
  'use strict';

  var zaman = window.RitimZamanlama;
  var cekirdek = window.PoliritimCekirdegi;
  if (!zaman || !cekirdek || !document.getElementById('prBaslat')) { return; }

  function byId(id) { return document.getElementById(id); }
  function sinirla(n, alt, ust, varsayilan) {
    n = Number(n);
    return Number.isFinite(n) ? Math.max(alt, Math.min(ust, n)) : varsayilan;
  }

  var AYAR_ANAHTARI = 'poliritim_ayar_v1';
  var HAZIRLIK_DONGU = 1;          // ölçümden önce bir tam döngü rehberli sayım
  var TAM_PUAN_MS = 45;            // bu sapmaya kadar tam puan
  var LOOKAHEAD_SN = 0.12;         // ses zamanlama ufku
  var TIK_MS = 25;                 // zamanlayıcı adımı

  /* ---------------- Aşamalar ---------------- */
  var ASAMALAR = [
    { no: 1, ad: 'Yalnız sağ el', eller: ['sag'], rehber: 'tam',
      ipucu: 'Sol el dursun. Sağ elin temposunu oturt.' },
    { no: 2, ad: 'Yalnız sol el', eller: ['sol'], rehber: 'tam',
      ipucu: 'Şimdi sağ el dursun. Sol elin kendi temposu var.' },
    { no: 3, ad: 'İki el · rehberli', eller: ['sag', 'sol'], rehber: 'tam',
      ipucu: 'İki el birlikte. Klik sesi yol gösteriyor.' },
    { no: 4, ad: 'İki el · rehber sessiz', eller: ['sag', 'sol'], rehber: 'sessiz',
      ipucu: 'ÖLÇÜM. Yalnız ilk döngü sayılır, sonra ritmi siz taşıyacaksınız.' }
  ];

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
        this.master.connect(this.ctx.destination);
      }
      if (this.ctx.state === 'suspended') { this.ctx.resume(); }
      this.duzey();
      return this.ctx;
    },
    duzey: function () {
      if (this.master) { this.master.gain.value = sinirla(byId('prSes').value, 0, 100, 70) / 100; }
    },
    /* Zamanlanmış sesleri de kesmek için master yeniden kurulur. */
    sustur: function () {
      if (!this.ctx || !this.master) { return; }
      try { this.master.disconnect(); } catch (e) { /* zaten kopuk */ }
      this.master = this.ctx.createGain();
      this.master.connect(this.ctx.destination);
      this.duzey();
    },
    /**
     * @param {number} t   AudioContext zamanı
     * @param {string} el  'sag' | 'sol' | 'ortak'
     * @param {boolean} vurgu döngü başı mı
     */
    klik: function (t, el, vurgu) {
      var ctx = this.ctx;
      if (!ctx) { return; }
      var eller = el === 'ortak' ? ['sol', 'sag'] : [el];
      var master = this.master;
      eller.forEach(function (e, i) {
        var sag = e === 'sag';
        var o = ctx.createOscillator();
        var g = ctx.createGain();
        /* Sağ el: parlak üçgen, yüksek. Sol el: yumuşak sinüs, kalın. */
        o.type = sag ? 'triangle' : 'sine';
        o.frequency.value = sag ? (vurgu ? 1560 : 1180) : (vurgu ? 560 : 420);
        var yuk = (vurgu ? 0.52 : 0.36) / eller.length;
        var bas = t + i * 0.0015;
        g.gain.setValueAtTime(0.0001, bas);
        g.gain.exponentialRampToValueAtTime(yuk, bas + 0.004);
        g.gain.exponentialRampToValueAtTime(0.0001, bas + (sag ? 0.055 : 0.085));
        o.connect(g);
        if (ctx.createStereoPanner) {
          var pan = ctx.createStereoPanner();
          pan.pan.value = sag ? 0.45 : -0.45;
          g.connect(pan).connect(master);
        } else {
          g.connect(master);
        }
        o.start(bas);
        o.stop(bas + (sag ? 0.09 : 0.12));
      });
    }
  };

  /* ---------------- Durum ---------------- */
  var d = {
    oran: '3:2',
    asama: 0,               // ASAMALAR indeksi
    faz: '',                // '' | 'dinleme' | 'hazirlik' | 'olcum'
    nesil: 0,
    zamanlayici: null,
    animasyon: null,
    t0: 0,                  // ölçümün başlangıç AudioContext zamanı
    hedefler: null,
    plan: [],               // zamanlanacak sesler [{t, el, vurgu}]
    planIdx: 0,
    taplar: { sag: [], sol: [] },
    seri: 0,
    enUzunSeri: 0,
    sonSonuc: null,
    tamamlanan: {}          // 'oran|asamaNo' → true
  };

  /* ---------------- Ayar okuma ---------------- */
  function ayarlar() {
    var bpm = Math.round(sinirla(byId('prBpm').value, 40, 200, 80));
    var referans = byId('prReferans').value === 'sol' ? 'sol' : 'sag';
    var dongu = Math.round(sinirla(byId('prDongu').value, 1, 24, 8));
    var istenenPencere = Math.round(sinirla(byId('prPencere').value, 20, 500, 140));
    var pencere = cekirdek.etkinPencere(d.oran, bpm, referans, istenenPencere);
    return { bpm: bpm, referans: referans, dongu: dongu, pencere: pencere };
  }

  function asama() { return ASAMALAR[d.asama] || ASAMALAR[0]; }
  function olcumAsamasiMi() { return asama().rehber === 'sessiz'; }

  /* ================================================================
     STANDART ÖLÇÜM KOŞULU
     Karşılaştırma yalnız aynı koşulda anlamlı. İşaretliyken ayarlar
     kilitlenir; oran kilitlenmez çünkü oran VARYANTtır (ayrı seri).
     ================================================================ */
  var STD = { prBpm: '80', prDongu: '8', prPencere: '140', prReferans: 'sag' };
  var stdOnceki = {};
  function stdAcikMi() { return !!(byId('prStdMod') && byId('prStdMod').checked); }

  byId('prStdMod').addEventListener('change', function () {
    Object.keys(STD).forEach(function (id) {
      var el = byId(id);
      if (!el) { return; }
      if (byId('prStdMod').checked) {
        stdOnceki[id] = el.value;
        el.value = STD[id];
        el.disabled = true;
      } else {
        el.disabled = false;
        if (stdOnceki[id] !== undefined) { el.value = stdOnceki[id]; }
      }
    });
    /* Standart koşul 4. aşamadır: rehber sessiz, iki el. */
    if (byId('prStdMod').checked) { d.asama = 3; }
    ayarKaydet();
    yenidenCiz();
  });

  /* ---------------- Kalıcılık ---------------- */
  function ayarKaydet() {
    try {
      localStorage.setItem(AYAR_ANAHTARI, JSON.stringify({
        oran: d.oran, asama: d.asama, tamamlanan: d.tamamlanan,
        bpm: byId('prBpm').value, referans: byId('prReferans').value,
        dongu: byId('prDongu').value, pencere: byId('prPencere').value,
        ses: byId('prSes').value
      }));
    } catch (e) { /* özel mod: sessizce geç */ }
  }

  function ayarYukle() {
    var ham = null;
    try { ham = JSON.parse(localStorage.getItem(AYAR_ANAHTARI) || 'null'); } catch (e) { ham = null; }
    if (!ham || typeof ham !== 'object') { return; }
    if (cekirdek.oranBul(ham.oran)) { d.oran = ham.oran; }
    d.asama = Math.round(sinirla(ham.asama, 0, ASAMALAR.length - 1, 0));
    if (ham.tamamlanan && typeof ham.tamamlanan === 'object') { d.tamamlanan = ham.tamamlanan; }
    ['bpm', 'referans', 'dongu', 'pencere', 'ses'].forEach(function (k) {
      var el = byId('pr' + k.charAt(0).toUpperCase() + k.slice(1));
      if (el && ham[k] !== undefined && ham[k] !== null) { el.value = ham[k]; }
    });
  }

  /* ================================================================
     ÇİZİM
     ================================================================ */
  function oranlariCiz() {
    var kap = byId('prOranlar');
    kap.innerHTML = '';
    cekirdek.ORANLAR.forEach(function (o) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'pr-oran-btn';
      b.setAttribute('aria-pressed', o.kod === d.oran ? 'true' : 'false');
      b.dataset.oran = o.kod;
      var zorluk = '';
      for (var i = 1; i <= 6; i++) { zorluk += '<i class="' + (i <= o.zorluk ? 'dolu' : '') + '"></i>'; }
      b.innerHTML = '<b>' + o.kod + '</b><small>' + o.ad + '</small>'
                  + '<span class="pr-oran-zorluk" aria-label="Zorluk ' + o.zorluk + '/6">' + zorluk + '</span>';
      b.addEventListener('click', function () {
        if (d.faz) { durdur(); }
        d.oran = o.kod;
        ayarKaydet();
        yenidenCiz();
      });
      kap.appendChild(b);
    });
  }

  function asamalariCiz() {
    var kap = byId('prAsamalar');
    kap.innerHTML = '';
    ASAMALAR.forEach(function (a, i) {
      var el = document.createElement('button');
      el.type = 'button';
      el.className = 'pr-asama' + (i === d.asama ? ' pr-asama-aktif' : '')
                   + (d.tamamlanan[d.oran + '|' + a.no] ? ' pr-asama-bitti' : '');
      el.innerHTML = '<strong>' + a.no + '. ' + a.ad + '</strong>' + a.ipucu;
      el.addEventListener('click', function () {
        if (d.faz) { durdur(); }
        d.asama = i;
        ayarKaydet();
        yenidenCiz();
      });
      kap.appendChild(el);
    });
  }

  /** Izgaradaki hücre öğeleri (imleç de bir çocuk olduğu için children KULLANILMAZ). */
  function hucreOgeleri() { return byId('prIzgara').querySelectorAll('.pr-hucre'); }

  function izgarayiCiz() {
    var hucreler = cekirdek.izgara(d.oran);
    var kap = byId('prIzgara');
    /* İmleç ızgaranın çocuğu: silinmemeli, yalnız hücreler yenilenir */
    Array.prototype.forEach.call(hucreOgeleri(), function (h) { kap.removeChild(h); });
    kap.style.gridTemplateColumns = 'repeat(' + hucreler.length + ', minmax(22px, 1fr))';
    kap.classList.toggle('pr-izgara-yogun', hucreler.length > 12);
    var akt = asama();
    hucreler.forEach(function (h) {
      var sagVar = h.sag && akt.eller.indexOf('sag') >= 0;
      var solVar = h.sol && akt.eller.indexOf('sol') >= 0;
      var el = document.createElement('div');
      el.className = 'pr-hucre' + (sagVar && solVar ? ' pr-hucre-ortak' : '');
      el.dataset.indeks = String(h.indeks);
      el.innerHTML = '<span class="pr-hucre-no">' + (h.indeks + 1) + '</span>'
        + '<span class="pr-isaret pr-isaret-sag' + (sagVar ? ' dolu' : '') + '"></span>'
        + '<span class="pr-isaret pr-isaret-sol' + (solVar ? ' dolu' : '') + '"></span>';
      kap.appendChild(el);
    });
    var o = cekirdek.oranBul(d.oran);
    byId('prIzgaraAciklama').textContent = o
      ? 'Bir döngü ' + hucreler.length + ' eşit parçaya bölünür. Üst şerit sağ el (camgöbeği), '
        + 'alt şerit sol el (eflatun); ikisi aynı hücrede ise ortak vuruş (amber).'
      : '';
  }

  function daireyiCiz() {
    var o = cekirdek.oranBul(d.oran);
    var svg = byId('prDaire');
    if (!o) { svg.innerHTML = ''; return; }
    var akt = asama();
    var merkez = 100;
    var rSag = 78;
    var rSol = 56;
    var parcalar = [
      '<circle cx="100" cy="100" r="' + rSag + '" fill="none" stroke="currentColor" stroke-opacity=".16" stroke-width="1.5"/>',
      '<circle cx="100" cy="100" r="' + rSol + '" fill="none" stroke="currentColor" stroke-opacity=".16" stroke-width="1.5"/>'
    ];
    /* Dönen ibre (animasyonda güncellenir) */
    parcalar.push('<line id="prDaireIbre" x1="100" y1="100" x2="100" y2="' + (merkez - rSag - 6)
      + '" stroke="var(--pr-ortak)" stroke-width="2" stroke-linecap="round" opacity="0"/>');

    function noktalar(adet, r, sinif, el) {
      var cikti = '';
      for (var i = 0; i < adet; i++) {
        /* Saat 12'den başlayıp saat yönünde */
        var aci = (i / adet) * Math.PI * 2 - Math.PI / 2;
        var x = merkez + Math.cos(aci) * r;
        var y = merkez + Math.sin(aci) * r;
        var pasif = akt.eller.indexOf(el) < 0;
        cikti += '<circle class="pr-daire-nokta" data-el="' + el + '" data-i="' + i + '"'
          + ' cx="' + x.toFixed(2) + '" cy="' + y.toFixed(2) + '" r="' + (i === 0 ? 7 : 5) + '"'
          + ' fill="var(--pr-' + el + ')" opacity="' + (pasif ? 0.18 : 0.55) + '"/>';
      }
      return cikti;
    }
    parcalar.push(noktalar(o.sag, rSag, 'sag', 'sag'));
    parcalar.push(noktalar(o.sol, rSol, 'sol', 'sol'));
    svg.innerHTML = parcalar.join('');
  }

  function mnemonikCiz() {
    var h = cekirdek.mnemonikHaritasi(d.oran);
    var kap = byId('prMnemonik');
    if (!h.yuvalar.length) { kap.textContent = ''; return; }
    kap.innerHTML = h.yuvalar.map(function (y) {
      return '<b data-el="' + y.el + '" data-indeks="' + y.indeks + '">' + y.hece + '</b>';
    }).join('');
    if (!h.uyusuyor) {
      kap.innerHTML += ' <small class="alan-ipucu">(bu oran için cümle tanımlı değil)</small>';
    }
  }

  function ozetiCiz() {
    var a = ayarlar();
    var o = cekirdek.oranBul(d.oran);
    byId('prOranNot').textContent = o ? o.not : '';
    var akt = asama();
    byId('prSahneAlt').textContent = akt.no + '. aşama · ' + akt.ad + ' — ' + akt.ipucu;
    byId('prDonguSayaci').textContent = a.dongu + ' döngü · ' + a.bpm + ' BPM ('
      + (a.referans === 'sag' ? 'sağ' : 'sol') + ' el) · ±' + Math.round(a.pencere.ms) + ' ms';

    /* Pencere kırpıldıysa sebebini söyle — sessizce değiştirmek yanıltıcı olur */
    var uyari = byId('prPencereUyari');
    if (a.pencere.kirpildi) {
      uyari.hidden = false;
      uyari.innerHTML = '⚠ Bu tempoda ±' + a.pencere.istenenMs + ' ms çok geniş: hızlı elin vuruş '
        + 'aralığı ' + Math.round(a.pencere.hizliAralikMs) + ' ms. Pencere <strong>±'
        + Math.round(a.pencere.ms) + ' ms</strong>\'ye kırpıldı — aksi hâlde her dokunuş bir hedefe '
        + 'uyar ve skor gerçek beceriyi değil tavan etkisini gösterir.';
    } else {
      uyari.hidden = true;
    }

    /* Bu aşamada kullanılmayan el soluk ve pasif */
    ['sag', 'sol'].forEach(function (el) {
      var pad = byId(el === 'sag' ? 'prSagPad' : 'prSolPad');
      pad.dataset.pasif = akt.eller.indexOf(el) >= 0 ? '0' : '1';
    });
  }

  function yenidenCiz() {
    oranlariCiz();
    asamalariCiz();
    izgarayiCiz();
    daireyiCiz();
    mnemonikCiz();
    ozetiCiz();
  }

  /* ================================================================
     ZAMANLAMA MOTORU
     ================================================================ */
  function planiKur(t0, a, rehberli) {
    var akt = asama();
    var plan = [];
    var hedefler = cekirdek.hedefleriUret(d.oran, a.bpm, a.dongu, a.referans);

    /* Hazırlık döngüsü: HER aşamada sesli — nereden başlanacağı bilinmeli */
    var hazirlik = cekirdek.hedefleriUret(d.oran, a.bpm, HAZIRLIK_DONGU, a.referans);
    var hazirlikSuresi = hazirlik.donguSn * HAZIRLIK_DONGU;
    var hBas = t0 - hazirlikSuresi;
    hazirlik.sag.forEach(function (t, i) {
      var ortakMi = hazirlik.sol.some(function (u) { return Math.abs(u - t) < 1e-9; });
      plan.push({ t: hBas + t, el: ortakMi ? 'ortak' : 'sag', vurgu: i === 0, hazirlik: true });
    });
    hazirlik.sol.forEach(function (t) {
      var ortakMi = hazirlik.sag.some(function (u) { return Math.abs(u - t) < 1e-9; });
      if (!ortakMi) { plan.push({ t: hBas + t, el: 'sol', vurgu: false, hazirlik: true }); }
    });

    /* Ölçüm bölümü: rehber sesli aşamalarda klik çalar, sessizde çalmaz */
    if (rehberli) {
      hedefler.sag.forEach(function (t) {
        if (akt.eller.indexOf('sag') < 0) { return; }
        var ortakMi = hedefler.ortak.some(function (u) { return Math.abs(u - t) < 1e-9; });
        var ortakCalinir = ortakMi && akt.eller.indexOf('sol') >= 0;
        plan.push({ t: t0 + t, el: ortakCalinir ? 'ortak' : 'sag', vurgu: ortakMi });
      });
      hedefler.sol.forEach(function (t) {
        if (akt.eller.indexOf('sol') < 0) { return; }
        var ortakMi = hedefler.ortak.some(function (u) { return Math.abs(u - t) < 1e-9; });
        if (ortakMi && akt.eller.indexOf('sag') >= 0) { return; }   // ortak zaten planlandı
        plan.push({ t: t0 + t, el: 'sol', vurgu: ortakMi });
      });
    } else {
      /* Sessiz aşamada yalnız döngü başları hafifçe işaretlenir: tempo
         referansı olmadan 8 döngü sürüklenme ölçümü değil kaos olur. */
      for (var c = 0; c < a.dongu; c++) {
        plan.push({ t: t0 + c * hedefler.donguSn, el: 'ortak', vurgu: true, cipa: true });
      }
    }

    plan.sort(function (x, y) { return x.t - y.t; });
    return { plan: plan, hedefler: hedefler, hazirlikSuresi: hazirlikSuresi };
  }

  function baslat() {
    if (d.faz) { return; }
    var ctx = ses.hazirla();
    if (!ctx) {
      byId('prFaz').textContent = 'Ses açılamadı';
      return;
    }
    var a = ayarlar();
    var rehberli = asama().rehber === 'tam';
    /*
     * Zaman çizelgesi:
     *   şimdi +0.25 sn ──[ hazırlık: 1 tam döngü, HER aşamada sesli ]──▶ t0
     *   t0 ──[ ölçüm: a.dongu döngü ]──▶ bitiş
     * Hazırlık planın başında olduğu için t0'ı doğrudan hesaplarız; planiKur
     * hazırlığı t0'dan GERİYE doğru yerleştirir.
     */
    var hazirlikSuresi = cekirdek.donguSuresi(d.oran, a.bpm, a.referans) * HAZIRLIK_DONGU;
    var gercekT0 = ctx.currentTime + 0.25 + hazirlikSuresi;
    var kurulum = planiKur(gercekT0, a, rehberli);

    d.faz = 'hazirlik';
    d.nesil++;
    d.t0 = gercekT0;
    d.hedefler = kurulum.hedefler;
    d.plan = kurulum.plan;
    d.planIdx = 0;
    d.taplar = { sag: [], sol: [] };
    d.seri = 0;
    d.enUzunSeri = 0;
    d.ayar = a;

    byId('prBaslat').hidden = true;
    byId('prDurdur').hidden = false;
    byId('prDinlet').disabled = true;
    byId('prSonuc').hidden = true;
    byId('prCanli').hidden = false;
    byId('prCanliSag').textContent = '0';
    byId('prCanliSol').textContent = '0';
    byId('prCanliSeri').textContent = '0';
    byId('prImlec').classList.add('pr-imlec-acik');
    kilitle(true);

    var bitis = gercekT0 + d.hedefler.donguSn * a.dongu + 0.4;
    var nesil = d.nesil;
    d.zamanlayici = setInterval(function () {
      if (nesil !== d.nesil) { return; }
      var simdi = ctx.currentTime;
      /* Lookahead: yaklaşan sesleri zamanla */
      while (d.planIdx < d.plan.length && d.plan[d.planIdx].t < simdi + LOOKAHEAD_SN) {
        var p = d.plan[d.planIdx];
        ses.klik(p.t, p.el, p.vurgu);
        d.planIdx++;
      }
      if (d.faz === 'hazirlik' && simdi >= gercekT0 - 0.02) {
        d.faz = 'olcum';
        byId('prFaz').textContent = olcumAsamasiMi() ? 'ÖLÇÜM' : 'Alıştırma';
      }
      if (simdi >= bitis) { bitir(); }
    }, TIK_MS);

    animasyonBaslat(ctx, gercekT0, a);
    byId('prFaz').textContent = 'Hazırlık';
    byId('prSayac').textContent = '…';
  }

  /* ---------------- Animasyon: imleç + daire ibresi ---------------- */
  function animasyonBaslat(ctx, t0, a) {
    var nesil = d.nesil;
    var imlec = byId('prImlec');
    var ibre = byId('prDaireIbre');
    var hucreler = hucreOgeleri();
    var bolumAdedi = hucreler.length;
    var sonHucre = -1;

    function kare() {
      if (nesil !== d.nesil || !d.faz) { return; }
      var gecen = ctx.currentTime - t0;
      var donguSn = d.hedefler.donguSn;
      var toplam = donguSn * a.dongu;

      if (gecen >= 0 && gecen <= toplam && bolumAdedi) {
        var donguIci = (gecen % donguSn) / donguSn;          // 0..1
        imlec.style.left = 'calc(3px + ' + (donguIci * 100) + '% - ' + (donguIci * 6) + 'px)';
        if (ibre) {
          ibre.setAttribute('opacity', '0.9');
          ibre.setAttribute('transform', 'rotate(' + (donguIci * 360) + ' 100 100)');
        }
        var aktifHucre = Math.floor(donguIci * bolumAdedi);
        if (aktifHucre !== sonHucre) {
          if (hucreler[sonHucre]) { hucreler[sonHucre].classList.remove('pr-simdi'); }
          if (hucreler[aktifHucre]) { hucreler[aktifHucre].classList.add('pr-simdi'); }
          sonHucre = aktifHucre;
        }
        byId('prSayac').textContent = (Math.floor(gecen / donguSn) + 1) + ' / ' + a.dongu;
      }
      d.animasyon = requestAnimationFrame(kare);
    }
    d.animasyon = requestAnimationFrame(kare);
  }

  function animasyonDurdur() {
    if (d.animasyon) { cancelAnimationFrame(d.animasyon); d.animasyon = null; }
    byId('prImlec').classList.remove('pr-imlec-acik');
    var ibre = byId('prDaireIbre');
    if (ibre) { ibre.setAttribute('opacity', '0'); }
    Array.prototype.forEach.call(hucreOgeleri(), function (h) {
      h.classList.remove('pr-simdi');
    });
  }

  function kilitle(kilit) {
    ['prBpm', 'prReferans', 'prDongu', 'prPencere', 'prOgrenci', 'prStdMod'].forEach(function (id) {
      var el = byId(id);
      if (!el) { return; }
      /* Standart mod açıkken zaten kilitli olanları serbest bırakmayalım */
      if (!kilit && stdAcikMi() && STD[id] !== undefined) { return; }
      el.disabled = kilit;
    });
    Array.prototype.forEach.call(document.querySelectorAll('.pr-oran-btn, .pr-asama'), function (b) {
      b.disabled = kilit;
    });
  }

  function durdur(mesaj) {
    d.nesil++;
    d.faz = '';
    if (d.zamanlayici) { clearInterval(d.zamanlayici); d.zamanlayici = null; }
    animasyonDurdur();
    ses.sustur();
    byId('prBaslat').hidden = false;
    byId('prDurdur').hidden = true;
    byId('prDinlet').disabled = false;
    byId('prCanli').hidden = true;
    byId('prFaz').textContent = mesaj || 'Durduruldu';
    byId('prSayac').textContent = '–';
    kilitle(false);
  }

  /* ================================================================
     VURUŞ
     ================================================================ */
  function tap(el, olay) {
    if (d.faz !== 'olcum' && d.faz !== 'hazirlik') { return; }
    if (asama().eller.indexOf(el) < 0) { return; }        // bu aşamada bu el yok
    var ctx = ses.ctx;
    if (!ctx) { return; }
    var t = zaman.olayZamani(ctx, olay) - d.t0;
    /* Hazırlık fazındaki vuruşlar sayılmaz ama görsel geri bildirim verilir */
    if (d.faz === 'olcum' && t >= -0.25) { d.taplar[el].push(t); }
    padGeriBildirim(el, t);
  }

  function padGeriBildirim(el, t) {
    var pad = byId(el === 'sag' ? 'prSagPad' : 'prSolPad');
    pad.classList.add('pr-pad-vuruldu');
    setTimeout(function () { pad.classList.remove('pr-pad-vuruldu'); }, 110);

    var dalga = document.createElement('span');
    dalga.className = 'pr-dalga';
    dalga.style.background = el === 'sag' ? 'var(--pr-sag)' : 'var(--pr-sol)';
    pad.appendChild(dalga);
    setTimeout(function () { if (dalga.parentNode) { dalga.parentNode.removeChild(dalga); } }, 520);

    if (d.faz !== 'olcum' || !d.hedefler) { return; }

    /* Canlı geri bildirim: en yakın hedefe uzaklık */
    var hedefler = d.hedefler[el] || [];
    var enYakin = Infinity;
    hedefler.forEach(function (h) { enYakin = Math.min(enYakin, Math.abs(h - t)); });
    var pencereSn = (d.ayar ? d.ayar.pencere.ms : 140) / 1000;
    var isabet = enYakin <= pencereSn;
    byId(el === 'sag' ? 'prSagCanli' : 'prSolCanli').textContent =
      isabet ? (Math.round(enYakin * 1000) + ' ms') : 'ıska';
    var sayac = byId(el === 'sag' ? 'prCanliSag' : 'prCanliSol');
    if (isabet) { sayac.textContent = String((parseInt(sayac.textContent, 10) || 0) + 1); }

    if (isabet) {
      d.seri++;
      d.enUzunSeri = Math.max(d.enUzunSeri, d.seri);
    } else {
      d.seri = 0;
    }
    byId('prCanliSeri').textContent = String(d.seri);
  }

  /* ================================================================
     BİTİŞ VE SONUÇ
     ================================================================ */
  function bitir() {
    if (!d.faz) { return; }
    var a = d.ayar || ayarlar();
    var akt = asama();
    var hedefler = d.hedefler;

    /* Bu aşamada kullanılmayan elin hedefleri değerlendirmeye girmez —
       aksi hâlde "yalnız sağ el" aşamasında sol el %100 kaçırılmış sayılır. */
    var kullanilanHedefler = {
      sag: akt.eller.indexOf('sag') >= 0 ? hedefler.sag : [],
      sol: akt.eller.indexOf('sol') >= 0 ? hedefler.sol : [],
      ortak: hedefler.ortak,
      donguSn: hedefler.donguSn
    };

    var sonuc = cekirdek.degerlendir({
      oran: d.oran,
      hedefler: kullanilanHedefler,
      sagTaplar: d.taplar.sag,
      solTaplar: d.taplar.sol,
      pencereMs: a.pencere.ms,
      tamMs: TAM_PUAN_MS
    });
    sonuc.enUzunSeri = d.enUzunSeri;
    sonuc.asama = akt.no;
    sonuc.olcum = olcumAsamasiMi();
    d.sonSonuc = sonuc;

    if (sonuc.skor >= 60) { d.tamamlanan[d.oran + '|' + akt.no] = true; }
    ayarKaydet();

    durdur('Bitti');
    sonucuGoster(sonuc, a);
    yenidenCiz();
  }

  function elSatiri(etiket, deger) {
    return '<div class="pr-el-satir"><span>' + etiket + '</span><b>' + deger + '</b></div>';
  }

  function elYaz(kap, el, akt, elAdi) {
    if (akt.eller.indexOf(elAdi) < 0) {
      kap.innerHTML = '<p class="alan-ipucu">Bu aşamada kullanılmadı.</p>';
      return;
    }
    kap.innerHTML =
      elSatiri('Yakalanan', el.isabet + ' / ' + el.hedefAdedi) +
      elSatiri('Kaçırılan', el.kacirilan) +
      elSatiri('Fazla vuruş', el.fazla) +
      elSatiri('Ort. sapma', (el.ortSapmaMs >= 0 ? '+' : '') + Math.round(el.ortSapmaMs) + ' ms') +
      elSatiri('Ort. |sapma|', Math.round(el.ortMutlakMs) + ' ms') +
      elSatiri('Kararlılık (SD)', Math.round(el.sdMs) + ' ms');
  }

  function sonucuGoster(s, a) {
    var akt = ASAMALAR[d.asama] || ASAMALAR[0];
    byId('prSonuc').hidden = false;
    byId('prSkor').textContent = s.skor;
    byId('prKapsama').textContent = Math.round(s.kapsama * 100) + '%';
    byId('prKararlilik').textContent = Math.round(s.sdMs) + ' ms';
    byId('prOranHata').textContent = s.oranHatasi ? (s.oranHatasi.hataYuzde > 0 ? '+' : '')
      + s.oranHatasi.hataYuzde + '%' : '—';
    byId('prFazKayma').textContent = s.fazKaymasi && s.fazKaymasi.adet
      ? Math.round(s.fazKaymasi.kenaraYaklasma * 100) + '%' : '—';

    byId('prSonucAltBaslik').textContent = d.oran + ' · ' + akt.no + '. aşama (' + akt.ad + ') · '
      + a.bpm + ' BPM · ±' + Math.round(a.pencere.ms) + ' ms';
    byId('prSonucRozet').textContent = s.olcum ? '📐 ölçüm' : 'alıştırma — kayıt üretmez';

    elYaz(byId('prSagSonuc'), s.sag, akt, 'sag');
    elYaz(byId('prSolSonuc'), s.sol, akt, 'sol');
    sapmaGrafigi(s, a);
    byId('prYorum').innerHTML = yorum(s, akt);

    /* Kayıt yalnız ÖLÇÜM aşamasında ve öğrenci seçiliyse mümkün */
    var ogrenciId = byId('prOgrenci').value;
    var form = byId('prKaydetForm');
    var uyari = byId('prKaydetUyari');
    if (s.olcum && ogrenciId) {
      form.hidden = false;
      uyari.hidden = true;
      byId('prFormOgrenci').value = ogrenciId;
      byId('prFormVaryant').value = d.oran;
      byId('prFormBpm').value = String(a.bpm);
      byId('prFormSkor').value = String(s.skor);
      byId('prFormStandart').value = stdAcikMi() ? '1' : '0';
      byId('prFormSd').value = String(Math.round(s.sdMs));
      var kalite = zaman.kaliteDurumu(ses.ctx);
      byId('prFormKalite').value = kalite && kalite.kod ? kalite.kod : '';
      byId('prFormDetay').value = detayJson(s, a);
    } else {
      form.hidden = true;
      uyari.hidden = false;
      uyari.textContent = !s.olcum
        ? 'Bu bir alıştırma aşaması — ölçüm olarak kaydedilmez. 4. aşamayı (rehber sessiz) tamamlayın.'
        : 'Kaydetmek için yukarıdan bir katılımcı seçin.';
    }

    byId('prSonrakiAsama').hidden = !(d.asama < ASAMALAR.length - 1 && s.skor >= 60);
  }

  /** Kayda giden ayrıntı. 4000 baytlık sınır var: ham tap listesi GİRMEZ. */
  function detayJson(s, a) {
    var ozet = {
      surum: s.surum,
      oran: s.oran,
      asama: s.asama,
      bpm: a.bpm,
      referans: a.referans,
      dongu: a.dongu,
      pencereMs: Math.round(a.pencere.ms),
      pencereKirpildi: a.pencere.kirpildi ? 1 : 0,
      tamMs: TAM_PUAN_MS,
      skor: s.skor,
      kapsama: s.kapsama,
      dogruluk: s.dogruluk,
      zayifEl: s.zayifEl,
      enUzunSeri: s.enUzunSeri,
      sag: elOzet(s.sag),
      sol: elOzet(s.sol),
      oranHatasi: s.oranHatasi,
      fazKaymasi: s.fazKaymasi
        ? { adet: s.fazKaymasi.adet, ortKayma: s.fazKaymasi.ortKayma,
            kenaraYaklasma: s.fazKaymasi.kenaraYaklasma }
        : null
    };
    var metin = JSON.stringify(ozet);
    return metin.length > 3900 ? JSON.stringify({ oran: s.oran, skor: s.skor, kisaltildi: 1 }) : metin;
  }

  function elOzet(el) {
    return {
      hedef: el.hedefAdedi, isabet: el.isabet, kacirilan: el.kacirilan, fazla: el.fazla,
      ortSapmaMs: Math.round(el.ortSapmaMs * 10) / 10,
      ortMutlakMs: Math.round(el.ortMutlakMs * 10) / 10,
      sdMs: Math.round(el.sdMs * 10) / 10
    };
  }

  function sapmaGrafigi(s, a) {
    var kap = byId('prSapmaGrafik');
    var pencere = a.pencere.ms;
    var parcalar = ['<div class="pr-sapma-orta"></div>',
      '<span class="pr-sapma-etiket" style="left:4px">−' + Math.round(pencere) + ' ms (erken)</span>',
      '<span class="pr-sapma-etiket" style="right:4px">+' + Math.round(pencere) + ' ms (geç)</span>'];
    [['sag', s.sag], ['sol', s.sol]].forEach(function (cift, ci) {
      cift[1].eslesen.forEach(function (x) {
        var oran = 0.5 + (x.sapmaMs / (pencere * 2));
        var sol = Math.max(0, Math.min(1, oran)) * 100;
        var ust = ci === 0 ? 32 : 62;
        parcalar.push('<span class="pr-sapma-nokta" style="left:' + sol.toFixed(1)
          + '%;top:' + ust + '%;background:var(--pr-' + cift[0] + ')"></span>');
      });
    });
    kap.innerHTML = parcalar.join('');
  }

  /**
   * Yorum: skoru DEĞİL, ne olduğunu anlatır. Sonuç iddiası ("dikkatini
   * geliştirir" gibi) yazmaz — yalnız ölçülen davranışı tarif eder.
   */
  function yorum(s, akt) {
    var satirlar = [];
    if (!s.sag.isabet && !s.sol.isabet) {
      return 'Hiç vuruş kaydedilmedi. Pad\'lere dokunun veya <kbd>F</kbd> / <kbd>J</kbd> tuşlarını kullanın.';
    }
    if (s.zayifEl) {
      var zayif = s.zayifEl === 'sag' ? 'Sağ' : 'Sol';
      var zEl = s.zayifEl === 'sag' ? s.sag : s.sol;
      satirlar.push('Skoru belirleyen el: <strong>' + zayif.toLowerCase() + ' el</strong> '
        + '(ort. |sapma| ' + Math.round(zEl.ortMutlakMs) + ' ms). Poliritimde zayıf el belirleyicidir; '
        + 'güçlü el ötekinin hatasını telafi etmez.');
    }
    if (s.oranHatasi && Math.abs(s.oranHatasi.hataYuzde) >= 8) {
      satirlar.push(s.oranHatasi.hataYuzde < 0
        ? 'Oran <strong>daraldı</strong> (' + s.oranHatasi.hataYuzde + '%): eller birbirine yaklaşıyor — '
          + 'poliritim tek ritme çökme eğiliminde. Aşama 1–2\'ye dönüp elleri ayrı ayrı oturtmak işe yarar.'
        : 'Oran <strong>açıldı</strong> (+' + s.oranHatasi.hataYuzde + '%): bir el ötekine göre fazla yavaşladı.');
    } else if (s.oranHatasi) {
      satirlar.push('Oran korundu (' + s.oranHatasi.gercek + ' ≈ hedef ' + s.oranHatasi.hedef + ').');
    }
    if (s.fazKaymasi && s.fazKaymasi.adet && s.fazKaymasi.kenaraYaklasma > 0.08) {
      satirlar.push('İkincil elin (' + (s.ikincilEl === 'sag' ? 'sağ' : 'sol')
        + ') serbest vuruşları birincil elin vuruşuna doğru kaymış '
        + '(%' + Math.round(s.fazKaymasi.kenaraYaklasma * 100) + '): bağımsızlık henüz tam kurulmamış.');
    }
    if (s.sag.fazla + s.sol.fazla > Math.max(2, (s.sag.hedefAdedi + s.sol.hedefAdedi) * 0.2)) {
      satirlar.push('Hedefsiz çok vuruş var — hız yerine yavaş ve az vuruşla başlamak daha verimli.');
    }
    if (!akt.eller.length || akt.eller.length === 1) {
      satirlar.push('Bu aşamada tek el çalıştı; poliritim ölçümü 4. aşamada yapılır.');
    }
    satirlar.push('En uzun kesintisiz doğru dizi: <strong>' + s.enUzunSeri + '</strong> vuruş.');
    return satirlar.join(' ');
  }

  /* ================================================================
     DİNLETME (vuruş beklemeden ritmi duyurma)
     ================================================================ */
  function dinlet() {
    if (d.faz) { return; }
    var ctx = ses.hazirla();
    if (!ctx) { return; }
    var a = ayarlar();
    var akt = asama();
    var t0 = ctx.currentTime + 0.2;
    var h = cekirdek.hedefleriUret(d.oran, a.bpm, 2, a.referans);
    d.faz = 'dinleme';
    d.nesil++;
    var nesil = d.nesil;

    h.sag.forEach(function (t) {
      if (akt.eller.indexOf('sag') < 0) { return; }
      var ortakMi = h.ortak.some(function (u) { return Math.abs(u - t) < 1e-9; });
      var ortakCalinir = ortakMi && akt.eller.indexOf('sol') >= 0;
      ses.klik(t0 + t, ortakCalinir ? 'ortak' : 'sag', ortakMi);
    });
    h.sol.forEach(function (t) {
      if (akt.eller.indexOf('sol') < 0) { return; }
      var ortakMi = h.ortak.some(function (u) { return Math.abs(u - t) < 1e-9; });
      if (ortakMi && akt.eller.indexOf('sag') >= 0) { return; }
      ses.klik(t0 + t, 'sol', ortakMi);
    });

    byId('prFaz').textContent = 'Dinle';
    byId('prImlec').classList.add('pr-imlec-acik');
    d.hedefler = h;
    d.ayar = a;
    animasyonBaslat(ctx, t0, { dongu: 2 });
    setTimeout(function () {
      if (nesil !== d.nesil) { return; }
      d.faz = '';
      animasyonDurdur();
      byId('prFaz').textContent = 'Hazır';
    }, (h.donguSn * 2 + 0.4) * 1000);
  }

  /** Mnemonik cümleyi hece hece çalar + heceleri sırayla vurgular. */
  function mnemonikCal() {
    if (d.faz) { return; }
    var ctx = ses.hazirla();
    if (!ctx) { return; }
    var a = ayarlar();
    var harita = cekirdek.mnemonikHaritasi(d.oran);
    if (!harita.yuvalar.length) { return; }
    var donguSn = cekirdek.donguSuresi(d.oran, a.bpm, a.referans);
    var t0 = ctx.currentTime + 0.2;
    var heceler = byId('prMnemonik').querySelectorAll('b');
    harita.yuvalar.forEach(function (y, i) {
      var t = t0 + y.oran * donguSn;
      ses.klik(t, y.el, y.indeks === 0);
      setTimeout(function () {
        if (!heceler[i]) { return; }
        heceler[i].style.outline = '2px solid var(--amber)';
        setTimeout(function () { heceler[i].style.outline = ''; }, 220);
      }, Math.max(0, (t - ctx.currentTime) * 1000));
    });
  }

  /* ================================================================
     OLAYLAR
     ================================================================ */
  byId('prBaslat').addEventListener('click', baslat);
  byId('prDurdur').addEventListener('click', function () { durdur('Durduruldu'); });
  byId('prDinlet').addEventListener('click', dinlet);
  byId('prMnemonikCal').addEventListener('click', mnemonikCal);
  byId('prSes').addEventListener('input', function () { ses.duzey(); ayarKaydet(); });
  ['prBpm', 'prReferans', 'prDongu', 'prPencere'].forEach(function (id) {
    byId(id).addEventListener('change', function () { ayarKaydet(); ozetiCiz(); });
  });
  byId('prTekrar').addEventListener('click', function () {
    byId('prSonuc').hidden = true;
    baslat();
  });
  byId('prSonucKapat').addEventListener('click', function () { byId('prSonuc').hidden = true; });
  byId('prSonrakiAsama').addEventListener('click', function () {
    if (d.asama < ASAMALAR.length - 1) { d.asama++; }
    byId('prSonuc').hidden = true;
    ayarKaydet();
    yenidenCiz();
  });

  /* Padlar: pointerdown ile (click'ten hızlı ve dokunmatikte tek olay) */
  [['prSagPad', 'sag'], ['prSolPad', 'sol']].forEach(function (cift) {
    var pad = byId(cift[0]);
    pad.addEventListener('pointerdown', function (olay) {
      olay.preventDefault();
      tap(cift[1], olay);
    });
    /* Klavye ile odaklanıp Space/Enter'a basılırsa da vuruş sayılsın:
       pad bir <button>, native click tetiklenir ve pointerdown gelmez. */
    pad.addEventListener('click', function (olay) {
      if (olay.detail === 0) { tap(cift[1], olay); }   // detail 0 = klavye kaynaklı
    });
  });

  /*
   * Klavye: F = sol el, J = sağ el (motor-studyo ile AYNI sözleşme).
   * Space bilinçli olarak KULLANILMAZ: bu sayfada iki el var, tek tuş
   * hangi ele ait olduğu belirsiz kalır. Ayrıca Space sayfayı kaydırır.
   */
  document.addEventListener('keydown', function (olay) {
    if (olay.repeat || olay.ctrlKey || olay.altKey || olay.metaKey) { return; }
    var etiket = (olay.target && olay.target.tagName || '').toLowerCase();
    if (etiket === 'input' || etiket === 'select' || etiket === 'textarea') { return; }
    var kod = olay.key ? olay.key.toLowerCase() : '';
    if (kod === 'f') { olay.preventDefault(); tap('sol', olay); }
    else if (kod === 'j') { olay.preventDefault(); tap('sag', olay); }
    else if (kod === 'escape' && d.faz) { olay.preventDefault(); durdur('Durduruldu'); }
  });

  /* Sekme arkaya alınırsa ölçüm geçersiz: zamanlayıcı kısılır, sapma uydurma olur */
  document.addEventListener('visibilitychange', function () {
    if (document.hidden && d.faz) { durdur('Sekme değişti — ölçüm iptal edildi'); }
  });

  window.addEventListener('resize', function () {
    /* İmleç yüzde tabanlı; yalnız ızgara sütun genişliği yeniden hesaplanmalı */
    if (!d.faz) { izgarayiCiz(); }
  });

  /* ---------------- Başlangıç ---------------- */
  ayarYukle();
  yenidenCiz();
})();
