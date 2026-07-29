/* RitimTerapi tanıtım sitesi — animasyon ve ritim davranışları */
(function () {
  'use strict';

  /* Sayfa ilerleme çubuğu + hero parallax */
  var ilerleme = document.getElementById('tIlerleme');
  var heroGorsel = document.getElementById('heroGorsel');
  var azHareket = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function kaydirmaGuncelle() {
    var y = window.scrollY || 0;
    if (ilerleme) {
      var toplam = document.documentElement.scrollHeight - window.innerHeight;
      ilerleme.style.width = (toplam > 0 ? Math.min(100, 100 * y / toplam) : 0) + '%';
    }
    if (heroGorsel && !azHareket && window.innerWidth > 860) {
      heroGorsel.style.transform = 'translateY(' + Math.min(120, y * 0.14) + 'px)';
    }
  }
  window.addEventListener('scroll', kaydirmaGuncelle, { passive: true });
  kaydirmaGuncelle();

  /* Kart tilt (fare ile hafif 3B eğim) */
  if (!azHareket) {
    document.querySelectorAll('[data-tilt]').forEach(function (kart) {
      kart.addEventListener('mousemove', function (ev) {
        var kutu = kart.getBoundingClientRect();
        var x = (ev.clientX - kutu.left) / kutu.width - 0.5;
        var y = (ev.clientY - kutu.top) / kutu.height - 0.5;
        kart.style.transform = 'translateY(-6px) rotateX(' + (-y * 7).toFixed(2) + 'deg) rotateY(' + (x * 7).toFixed(2) + 'deg)';
      });
      kart.addEventListener('mouseleave', function () {
        kart.style.transform = '';
      });
    });
  }

  /* Kayarak görünme */
  var gozlemci = new IntersectionObserver(function (girdiler) {
    girdiler.forEach(function (g) {
      if (g.isIntersecting) {
        g.target.classList.add('gorunur');
        gozlemci.unobserve(g.target);
      }
    });
  }, { threshold: 0.15 });
  document.querySelectorAll('.kayarak').forEach(function (el) { gozlemci.observe(el); });

  /* Sayı sayaçları */
  function say(el) {
    var hedef = parseInt(el.dataset.hedef, 10) || 0;
    var baslangic = performance.now();
    var sure = 1200;
    function adim(t) {
      var oran = Math.min(1, (t - baslangic) / sure);
      el.textContent = Math.round(hedef * (1 - Math.pow(1 - oran, 3)));
      if (oran < 1) { requestAnimationFrame(adim); }
    }
    requestAnimationFrame(adim);
  }
  var sayacGozlemci = new IntersectionObserver(function (girdiler) {
    girdiler.forEach(function (g) {
      if (g.isIntersecting) {
        say(g.target);
        sayacGozlemci.unobserve(g.target);
      }
    });
  }, { threshold: 0.4 });
  document.querySelectorAll('.t-sayi').forEach(function (el) { sayacGozlemci.observe(el); });

  /* Galeri lightbox */
  document.addEventListener('click', function (ev) {
    var resim = ev.target.closest('.t-galeri-resim');
    if (resim) {
      var kutu = document.createElement('div');
      kutu.className = 't-lightbox';
      var buyuk = document.createElement('img');
      buyuk.src = resim.src;
      buyuk.alt = resim.alt || '';
      kutu.appendChild(buyuk);
      if (resim.dataset.baslik) {
        var altYazi = document.createElement('figcaption');
        altYazi.textContent = resim.dataset.baslik;
        kutu.appendChild(altYazi);
      }
      kutu.addEventListener('click', function () { kutu.remove(); });
      document.body.appendChild(kutu);
      return;
    }
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
      var acik = document.querySelector('.t-lightbox');
      if (acik) { acik.remove(); }
    }
  });

  /* ================================================================
     ORTAK SES BAĞLAMI
     Sayfadaki tüm sesler (hero ritmi, deneyler, nabız kartları) tek bir
     AudioContext paylaşır ve master kazançtan geçer. Böylece yeni bir ses
     başlarken sesKes() ile bekleyen tüm zamanlanmış düğümler susturulabilir:
     Web Audio'da ileri tarihli düğüm, bayrak indirmekle durmaz.
     ================================================================ */
  var ctx = null;
  var master = null;

  function sesHazirla() {
    if (!ctx) {
      ctx = new (window.AudioContext || window.webkitAudioContext)();
      master = ctx.createGain();
      master.connect(ctx.destination);
    }
    if (ctx.state === 'suspended') { ctx.resume(); }
    return ctx;
  }

  /** Zamanlanmış tüm sesleri anında keser (master'ı yeniden kurar). */
  function sesKes() {
    if (!ctx || !master) { return; }
    try { master.disconnect(); } catch (e) { /* zaten kopuk */ }
    master = ctx.createGain();
    master.connect(ctx.destination);
  }

  /** Kısa klik. tur: 'klik' | 'tahta' | 'yumusak' */
  function klik(zaman, vurgu, tur) {
    var g = ctx.createGain();
    g.connect(master);
    var o = ctx.createOscillator();
    if (tur === 'yumusak') {
      o.type = 'sine';
      o.frequency.value = vurgu ? 880 : 660;
      g.gain.setValueAtTime(vurgu ? 0.32 : 0.22, zaman);
      g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.12);
      o.connect(g); o.start(zaman); o.stop(zaman + 0.13);
      return;
    }
    o.type = tur === 'tahta' ? 'sine' : 'square';
    if (tur === 'tahta') {
      o.frequency.setValueAtTime(vurgu ? 980 : 720, zaman);
      o.frequency.exponentialRampToValueAtTime(vurgu ? 640 : 480, zaman + 0.05);
    } else {
      o.frequency.value = vurgu ? 1568 : 1046;
    }
    g.gain.setValueAtTime(vurgu ? 0.42 : 0.28, zaman);
    g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.08);
    o.connect(g); o.start(zaman); o.stop(zaman + 0.09);
  }

  /* Çalışan deneyi kapatmak için kayıt: her modül kendi durdurucusunu bırakır */
  var acikModuller = [];
  function digerleriniKapat(haric) {
    acikModuller.forEach(function (m) { if (m.ad !== haric) { m.durdur(); } });
  }

  /* ================================================================
     DENEY 1 — Ritim mi, ton mu? (psikoakustik eşik)
     Aynı klik dizisi hızlandıkça ~20 Hz'de ayrı vuruş algısı bırakır,
     perde (ton) algısına döner. Sürgü doğrudan tekrar frekansını yönetir.
     ================================================================ */
  (function deney1() {
    var surgu = document.getElementById('d1Surgu');
    if (!surgu) { return; }
    var btn = document.getElementById('d1Btn');
    var otoBtn = document.getElementById('d1Otomatik');
    var hzEl = document.getElementById('d1Hz');
    var durumEl = document.getElementById('d1Durum');
    var notaEl = document.getElementById('d1Nota');
    var okuma = durumEl.closest('.t-deney-okuma');
    var tuval = document.getElementById('d1Dalga');
    var ciz = tuval ? tuval.getContext('2d') : null;

    var calisiyor = false;
    var otomatik = false;
    var zamanlayici = null;
    var cizimId = null;
    var sonrakiZaman = 0;
    var vurusNo = 0;
    var otoBaslangic = 0;
    var sonVurusAn = 0;

    var NOTALAR = ['Do', 'Do#', 'Re', 'Re#', 'Mi', 'Fa', 'Fa#', 'Sol', 'Sol#', 'La', 'La#', 'Si'];
    /** Frekanstan en yakın nota adı (A4 = 440 Hz). */
    function notaAdi(hz) {
      var yariTon = Math.round(12 * Math.log(hz / 440) / Math.LN2);
      var ad = NOTALAR[((yariTon + 9) % 12 + 12) % 12];
      var oktav = 4 + Math.floor((yariTon + 9) / 12);
      return ad + oktav;
    }

    function hz() { return parseInt(surgu.value, 10) || 1; }

    function etiketiGuncelle() {
      var f = hz();
      hzEl.textContent = f;
      var metin, tonMu = false;
      if (f < 8) {
        metin = 'Ayrı vuruşlar — rahatça sayabilirsiniz';
      } else if (f < 16) {
        metin = 'Hızlı ama hâlâ ayrı — saymak zorlaşıyor';
      } else if (f < 24) {
        metin = '⚡ Eşik bölgesi — vuruşlar birbirine karışıyor';
      } else if (f < 45) {
        metin = 'Artık bir uğultu / pürüzlü ton duyuyorsunuz';
        tonMu = true;
      } else {
        metin = '🎵 Tek bir ton — ritim değil, NOTA duyuyorsunuz';
        tonMu = true;
      }
      durumEl.textContent = metin;
      okuma.classList.toggle('ton', tonMu);
      if (tonMu) {
        notaEl.hidden = false;
        notaEl.textContent = '≈ ' + f + ' Hz · yaklaşık ' + notaAdi(f) + ' perdesi';
      } else {
        notaEl.hidden = true;
      }
    }

    function planla() {
      var aralik = 1 / hz();
      while (sonrakiZaman < ctx.currentTime + 0.12) {
        // Yüksek frekansta vurgu duyulmaz; ayrıca kazanç düşürülür (kulak koruma)
        klik(sonrakiZaman, hz() < 24 && vurusNo % 4 === 0, hz() > 30 ? 'yumusak' : 'klik');
        sonVurusAn = sonrakiZaman;
        sonrakiZaman += aralik;
        vurusNo++;
      }
      if (otomatik) {
        var gecen = (ctx.currentTime - otoBaslangic) / 14;   // 14 saniyede 1 → 120
        var yeni = Math.min(120, Math.round(1 + gecen * 119));
        surgu.value = yeni;
        etiketiGuncelle();
        if (yeni >= 120) { otomatikDur(); }
      }
    }

    /* Tuval: her vuruşta yeni bir dalga doğar, hız arttıkça üst üste biner */
    function cizimDongusu() {
      if (!ciz) { return; }
      var G = tuval.width, Y = tuval.height;
      ciz.clearRect(0, 0, G, Y);
      var f = hz();
      var orta = Y / 2;
      ciz.lineWidth = 2;
      ciz.strokeStyle = f >= 24 ? '#a78bfa' : '#f59e0b';
      ciz.beginPath();
      var t = ctx ? ctx.currentTime : 0;
      for (var x = 0; x <= G; x++) {
        /* Ekranda 2 saniyelik pencere gösterilir; genlik son vuruştan sonra söner */
        var zaman = t - 2 + (x / G) * 2;
        var faz = (zaman * f) % 1;
        var zarf = Math.exp(-faz * (f < 24 ? 6 : 1.2));
        var y = orta - Math.sin(faz * Math.PI * 2) * zarf * (Y * 0.4);
        if (x === 0) { ciz.moveTo(x, y); } else { ciz.lineTo(x, y); }
      }
      ciz.stroke();
      /* Eşik çizgisi hissi: düşük frekansta ayrı tepeler görünür kalsın */
      ciz.globalAlpha = 0.25;
      ciz.beginPath();
      ciz.moveTo(0, orta); ciz.lineTo(G, orta);
      ciz.strokeStyle = '#78716c';
      ciz.lineWidth = 1;
      ciz.stroke();
      ciz.globalAlpha = 1;
      cizimId = requestAnimationFrame(cizimDongusu);
    }

    function baslat() {
      digerleriniKapat('d1');
      sesHazirla();
      sesKes();
      vurusNo = 0;
      sonrakiZaman = ctx.currentTime + 0.08;
      zamanlayici = setInterval(planla, 25);
      calisiyor = true;
      btn.textContent = '⏸ Durdur';
      if (!azHareket) { cizimId = requestAnimationFrame(cizimDongusu); }
    }

    function durdur() {
      clearInterval(zamanlayici);
      cancelAnimationFrame(cizimId);
      calisiyor = false;
      otomatikDur();
      sesKes();
      btn.textContent = '▶ Deneyi Başlat';
      if (ciz) { ciz.clearRect(0, 0, tuval.width, tuval.height); }
    }

    function otomatikDur() {
      otomatik = false;
      otoBtn.textContent = '⟳ Yavaştan hızlıya otomatik';
    }

    btn.addEventListener('click', function () { calisiyor ? durdur() : baslat(); });
    otoBtn.addEventListener('click', function () {
      if (otomatik) { otomatikDur(); return; }
      if (!calisiyor) { baslat(); }
      surgu.value = 1;
      etiketiGuncelle();
      otoBaslangic = ctx.currentTime;
      otomatik = true;
      otoBtn.textContent = '⏹ Otomatiği durdur';
    });
    surgu.addEventListener('input', function () { otomatikDur(); etiketiGuncelle(); });

    acikModuller.push({ ad: 'd1', durdur: function () { if (calisiyor) { durdur(); } } });
    etiketiGuncelle();
  })();

  /* ================================================================
     DENEY 2 — Vuruşu ne kadar yakalıyorsunuz?
     4 hazırlık + 8 ölçüm vuruşu; her tap en yakın hedefe eşlenir.
     Cihaz gecikmesi telafi EDİLMEZ; sayfada bu açıkça yazıyor.
     ================================================================ */
  (function deney2() {
    var btn = document.getElementById('d2Btn');
    if (!btn) { return; }
    var pad = document.getElementById('d2Pad');
    var padYazi = document.getElementById('d2PadYazi');
    var sonucEl = document.getElementById('d2Sonuc');
    var durumEl = document.getElementById('d2Durum');
    var seritEl = document.getElementById('d2Serit');

    var BPM = 90, HAZIRLIK = 4, OLCUM = 8;
    var aralik = 60 / BPM;
    var aktif = false, olcumFazi = false;
    var hedefler = [], taplar = [], zamanlayici = null;

    function durdur() {
      clearInterval(zamanlayici);
      aktif = false; olcumFazi = false;
      sesKes();
      pad.classList.add('bekle');
      padYazi.textContent = 'VUR';
      btn.textContent = '▶ Başlat';
    }

    function baslat() {
      digerleriniKapat('d2');
      sesHazirla();
      sesKes();
      hedefler = []; taplar = [];
      seritEl.innerHTML = '';
      sonucEl.textContent = '—';
      aktif = true; olcumFazi = false;
      btn.textContent = '⏹ Bırak';
      pad.classList.add('bekle');
      durumEl.textContent = 'Dinleyin… 4 vuruş hazırlık';

      var t0 = ctx.currentTime + 0.5;
      for (var i = 0; i < HAZIRLIK; i++) { klik(t0 + i * aralik, i === 0, 'tahta'); }
      var olcumBas = t0 + HAZIRLIK * aralik;
      for (var j = 0; j < OLCUM; j++) {
        var z = olcumBas + j * aralik;
        hedefler.push(z);
        klik(z, j === 0, 'tahta');
      }
      /* Faz geçişleri ve bitiş, ses saatine bağlı zamanlayıcılarla sürülür */
      zamanlayici = setInterval(function () {
        if (!aktif) { return; }
        if (!olcumFazi && ctx.currentTime >= olcumBas - 0.05) {
          olcumFazi = true;
          pad.classList.remove('bekle');
          padYazi.textContent = 'VUR!';
          durumEl.textContent = 'Şimdi! Her vuruşta birlikte vurun.';
        }
        if (ctx.currentTime > olcumBas + OLCUM * aralik + 0.35) { bitir(); }
      }, 30);
    }

    function tap() {
      if (!aktif || !olcumFazi) { return; }
      taplar.push(ctx.currentTime);
      pad.classList.add('vurdu');
      setTimeout(function () { pad.classList.remove('vurdu'); }, 90);
    }

    function bitir() {
      clearInterval(zamanlayici);
      aktif = false; olcumFazi = false;
      pad.classList.add('bekle');
      padYazi.textContent = 'VUR';
      btn.textContent = '↻ Tekrar Dene';

      /* Her hedefe en yakın tap; yarım vuruştan uzaksa kaçırılmış sayılır */
      var sapmalar = [], kacan = 0;
      hedefler.forEach(function (h) {
        var enYakin = null;
        taplar.forEach(function (t) {
          if (enYakin === null || Math.abs(t - h) < Math.abs(enYakin - h)) { enYakin = t; }
        });
        if (enYakin === null || Math.abs(enYakin - h) > aralik * 0.5) { kacan++; sapmalar.push(null); }
        else { sapmalar.push((enYakin - h) * 1000); }
      });

      var gecerli = sapmalar.filter(function (s) { return s !== null; });
      seritEl.innerHTML = '';
      sapmalar.forEach(function (s) {
        var c = document.createElement('i');
        if (s === null) { c.className = 'kacik'; c.style.height = '46px'; }
        else {
          c.className = s > 0 ? 'gec' : 'erken';
          c.style.height = Math.max(6, Math.min(46, Math.abs(s) / 3)) + 'px';
          c.title = Math.round(s) + ' ms';
        }
        seritEl.appendChild(c);
      });

      if (gecerli.length < 3) {
        sonucEl.textContent = '—';
        durumEl.textContent = 'Yeterli vuruş alınamadı. Sesi açıp tekrar deneyin.';
        return;
      }
      var ortMutlak = gecerli.reduce(function (t, s) { return t + Math.abs(s); }, 0) / gecerli.length;
      var ortalama = gecerli.reduce(function (t, s) { return t + s; }, 0) / gecerli.length;
      sonucEl.textContent = Math.round(ortMutlak);
      var yon = ortalama < -12 ? 'Vuruşlarınız biraz erken geldi (bu çok yaygındır).'
              : ortalama > 12 ? 'Vuruşlarınız biraz geç geldi.'
              : 'Erken/geç dengeniz iyi.';
      durumEl.textContent = yon + (kacan ? ' ' + kacan + ' vuruş kaçtı.' : '');
    }

    btn.addEventListener('click', function () { aktif ? durdur() : baslat(); });
    pad.addEventListener('pointerdown', function (ev) { ev.preventDefault(); tap(); });
    document.addEventListener('keydown', function (ev) {
      if (ev.code === 'Space' && olcumFazi) { ev.preventDefault(); tap(); }
    });
    acikModuller.push({ ad: 'd2', durdur: function () { if (aktif) { durdur(); } } });
  })();

  /* ================================================================
     DENEY 3 — Aksayanı bul (anizokroni algısı)
     6 vuruşluk dizi: ya kusursuz düzenli, ya bir vuruş %12 kaymış.
     ================================================================ */
  (function deney3() {
    var btn = document.getElementById('d3Btn');
    if (!btn) { return; }
    var durumEl = document.getElementById('d3Durum');
    var cevapKutu = document.getElementById('d3Cevaplar');
    var skorEl = document.getElementById('d3Skor');
    var noktalar = document.querySelectorAll('#d3Noktalar i');

    var TUR = 3, ARALIK = 0.5, KAYMA = 0.12;
    var tur = 0, dogru = 0, aktif = false, aksakMi = false, zamanlayicilar = [];

    function temizle() {
      zamanlayicilar.forEach(clearTimeout);
      zamanlayicilar = [];
      noktalar.forEach(function (n) { n.classList.remove('yandi'); });
    }

    function durdur() {
      temizle();
      aktif = false;
      sesKes();
      cevapKutu.hidden = true;
      btn.textContent = '▶ 3 Tur Oyna';
      durumEl.textContent = 'Hazır olduğunuzda başlatın.';
    }

    function baslat() {
      digerleriniKapat('d3');
      sesHazirla();
      sesKes();
      tur = 0; dogru = 0; aktif = true;
      skorEl.textContent = '';
      btn.textContent = '⏹ Bırak';
      turBaslat();
    }

    function turBaslat() {
      temizle();
      tur++;
      cevapKutu.hidden = true;
      durumEl.textContent = '🎧 Tur ' + tur + ' / ' + TUR + ' — dinleyin…';
      aksakMi = Math.random() < 0.5;
      var kayanIdx = 2 + Math.floor(Math.random() * 3);   // 3., 4. veya 5. vuruş kayar
      var t0 = ctx.currentTime + 0.45;
      var zaman = t0;
      for (var i = 0; i < 6; i++) {
        if (aksakMi && i === kayanIdx) { zaman += ARALIK * KAYMA; }
        klik(zaman, false, 'tahta');
        (function (z, idx) {
          zamanlayicilar.push(setTimeout(function () {
            if (!aktif || !noktalar[idx]) { return; }
            noktalar[idx].classList.add('yandi');
            setTimeout(function () { noktalar[idx].classList.remove('yandi'); }, 150);
          }, Math.max(0, (z - ctx.currentTime) * 1000)));
        })(zaman, i);
        zaman += ARALIK;
      }
      zamanlayicilar.push(setTimeout(function () {
        if (!aktif) { return; }
        cevapKutu.hidden = false;
        durumEl.textContent = 'Sizce hangisiydi?';
      }, Math.max(0, (zaman - ctx.currentTime) * 1000)));
    }

    cevapKutu.addEventListener('click', function (ev) {
      var dugme = ev.target.closest('[data-cevap]');
      if (!dugme || !aktif) { return; }
      var dogruMu = (dugme.dataset.cevap === 'aksak') === aksakMi;
      if (dogruMu) { dogru++; }
      cevapKutu.hidden = true;
      durumEl.innerHTML = dogruMu
        ? '<span class="dogru">✓ Doğru!</span> Dizi ' + (aksakMi ? 'gerçekten aksıyordu.' : 'kusursuz düzenliydi.')
        : '<span class="yanlis">✕ Bu sefer olmadı.</span> Dizi ' + (aksakMi ? 'aksıyordu.' : 'düzenliydi.');
      skorEl.innerHTML = 'Skor: <strong>' + dogru + ' / ' + tur + '</strong>';
      if (tur < TUR) {
        zamanlayicilar.push(setTimeout(function () { if (aktif) { turBaslat(); } }, 1800));
      } else {
        aktif = false;
        btn.textContent = '↻ Yeniden Oyna';
        zamanlayicilar.push(setTimeout(function () {
          skorEl.innerHTML = 'Skor: <strong>' + dogru + ' / ' + TUR + '</strong> — ' +
            (dogru === TUR ? 'kulağınız keskin! 👏' : dogru >= 2 ? 'fena değil, kulak ısınıyor.' : 'bu iş pratikle gelişir.');
        }, 300));
      }
    });

    btn.addEventListener('click', function () { aktif ? durdur() : baslat(); });
    acikModuller.push({ ad: 'd3', durdur: function () { if (aktif) { durdur(); } } });
  })();

  /* ================================================================
     NABIZ KARTLARI — her kart kendi temposunu çalar
     ================================================================ */
  (function nabiz() {
    var dizi = document.getElementById('nabizDizi');
    if (!dizi) { return; }
    var calan = null, zamanlayici = null, sonrakiZaman = 0, vurusNo = 0, bpm = 60;

    function durdur() {
      clearInterval(zamanlayici);
      sesKes();
      if (calan) { calan.classList.remove('calıyor'); }
      calan = null;
    }

    function planla() {
      while (sonrakiZaman < ctx.currentTime + 0.12) {
        klik(sonrakiZaman, vurusNo % 4 === 0, 'tahta');
        sonrakiZaman += 60 / bpm;
        vurusNo++;
      }
    }

    dizi.addEventListener('click', function (ev) {
      var kart = ev.target.closest('.t-nabiz');
      if (!kart) { return; }
      if (calan === kart) { durdur(); return; }
      digerleriniKapat('nabiz');
      durdur();
      sesHazirla();
      bpm = parseInt(kart.dataset.bpm, 10) || 60;
      vurusNo = 0;
      sonrakiZaman = ctx.currentTime + 0.08;
      zamanlayici = setInterval(planla, 25);
      calan = kart;
      kart.classList.add('calıyor');
    });

    acikModuller.push({ ad: 'nabiz', durdur: durdur });
  })();

  /* "Ritmi Hisset" — Web Audio ile 4/4 vuruş, halka nabzı eşliğinde */
  var btn = document.getElementById('ritimBtn');
  var halka = document.getElementById('vurusHalkasi');
  if (!btn) { return; }

  var calıyor = false;
  var zamanlayici = null;
  var sonrakiVurusZamani = 0;
  var vurusNo = 0;
  var BPM = 96;

  function planla() {
    while (sonrakiVurusZamani < ctx.currentTime + 0.12) {
      var aksan = vurusNo % 4 === 0;
      klik(sonrakiVurusZamani, aksan, 'klik');
      gorselVurus(sonrakiVurusZamani);
      sonrakiVurusZamani += 60 / BPM;
      vurusNo++;
    }
  }

  function gorselVurus(zaman) {
    var gecikme = Math.max(0, (zaman - ctx.currentTime) * 1000);
    setTimeout(function () {
      if (!halka) { return; }
      halka.classList.add('vur');
      setTimeout(function () { halka.classList.remove('vur'); }, 110);
    }, gecikme);
  }

  function heroDurdur() {
    clearInterval(zamanlayici);
    calıyor = false;
    sesKes();
    btn.textContent = '▶ Ritmi Hisset';
  }

  btn.addEventListener('click', function () {
    if (calıyor) { heroDurdur(); return; }
    digerleriniKapat('hero');
    sesHazirla();
    sesKes();
    vurusNo = 0;
    sonrakiVurusZamani = ctx.currentTime + 0.1;
    zamanlayici = setInterval(planla, 25);
    calıyor = true;
    btn.textContent = '⏸ Durdur';
  });

  acikModuller.push({ ad: 'hero', durdur: function () { if (calıyor) { heroDurdur(); } } });
})();
