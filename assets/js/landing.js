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

  /*
   * Galeri lightbox — klavye erisimi.
   * Onceden dinleyici odaklanamayan <img> uzerindeydi ve acilan katmanda ne rol
   * ne odak yonetimi vardi: klavye kullanicisi ne acabiliyor ne kapatabiliyordu.
   * Artik resim bir <button> icinde; katman dialog rolunde, odak icine alinir,
   * kapaninca ACAN dugmeye geri doner (WCAG 2.4.3).
   */
  var lightboxAcan = null;
  function lightboxKapat() {
    var acik = document.querySelector('.t-lightbox');
    if (!acik) { return; }
    acik.remove();
    if (lightboxAcan && document.contains(lightboxAcan)) { lightboxAcan.focus(); }
    lightboxAcan = null;
  }
  document.addEventListener('click', function (ev) {
    var dugme = ev.target.closest('.t-galeri-btn');
    if (!dugme) { return; }
    var resim = dugme.querySelector('.t-galeri-resim');
    if (!resim) { return; }
    lightboxAcan = dugme;

    var kutu = document.createElement('div');
    kutu.className = 't-lightbox';
    kutu.setAttribute('role', 'dialog');
    kutu.setAttribute('aria-modal', 'true');
    kutu.setAttribute('aria-label', resim.alt || 'Büyütülmüş görsel');
    kutu.tabIndex = -1;

    var buyuk = document.createElement('img');
    buyuk.src = resim.src;
    buyuk.alt = resim.alt || '';
    kutu.appendChild(buyuk);
    if (resim.dataset.baslik) {
      var altYazi = document.createElement('figcaption');
      altYazi.textContent = resim.dataset.baslik;
      kutu.appendChild(altYazi);
    }
    var kapat = document.createElement('button');
    kapat.type = 'button';
    kapat.className = 't-lightbox-kapat';
    kapat.textContent = '✕';
    kapat.setAttribute('aria-label', 'Kapat');
    kapat.addEventListener('click', lightboxKapat);
    kutu.appendChild(kapat);

    kutu.addEventListener('click', function (e) {
      if (e.target === kutu || e.target === buyuk) { lightboxKapat(); }
    });
    /* Odak tuzagi: Tab katmanin disina cikmasin */
    kutu.addEventListener('keydown', function (e) {
      if (e.key === 'Tab') { e.preventDefault(); kapat.focus(); }
    });
    document.body.appendChild(kutu);
    kapat.focus();
  });
  document.addEventListener('keydown', function (ev) {
    /* Escape ile kapatirken de odak ACAN dugmeye donmeli */
    if (ev.key === 'Escape' && document.querySelector('.t-lightbox')) { lightboxKapat(); }
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

  /* Sayfadaki her vuruşu dinleyenler (canvas alanı buna tepki verir) */
  var vurusDinleyiciler = [];
  function vurusuDuyur(guc) {
    vurusDinleyiciler.forEach(function (f) { f(guc || 1); });
  }

  /* ================================================================
     HERO — ÜRETKEN RİTİM ALANI (canvas)
     Parçacıklar sakin bir alanda süzülür; her vuruşta merkezden bir
     basınç dalgası çıkar ve parçacıkları iter. Fare de alanı büker.
     ================================================================ */
  (function ritimAlani() {
    var tuval = document.getElementById('ritimAlani');
    if (!tuval || azHareket) { return; }
    var ciz = tuval.getContext('2d');
    var G = 0, Y = 0, oran = Math.min(2, window.devicePixelRatio || 1);
    var parcaciklar = [], dalgalar = [];
    var fare = { x: -999, y: -999, aktif: false };

    function boyutla() {
      var kutu = tuval.getBoundingClientRect();
      G = kutu.width; Y = kutu.height;
      tuval.width = Math.round(G * oran);
      tuval.height = Math.round(Y * oran);
      ciz.setTransform(oran, 0, 0, oran, 0, 0);
      kur();
    }

    function kur() {
      // Yoğunluk alana göre; küçük ekranda az parçacık (performans)
      var adet = Math.min(150, Math.round(G * Y / 9000));
      parcaciklar = [];
      for (var i = 0; i < adet; i++) {
        parcaciklar.push({
          x: Math.random() * G, y: Math.random() * Y,
          vx: (Math.random() - 0.5) * 0.14, vy: (Math.random() - 0.5) * 0.14,
          r: 0.7 + Math.random() * 1.8,
          p: Math.random()           // renk fazı: amber ↔ mor
        });
      }
    }

    /** Vuruş geldiğinde merkezden yayılan basınç dalgası. */
    function dalgaEkle(guc) {
      dalgalar.push({ r: 0, guc: guc, olu: false });
      if (dalgalar.length > 6) { dalgalar.shift(); }
    }
    vurusDinleyiciler.push(dalgaEkle);

    function dongu() {
      ciz.clearRect(0, 0, G, Y);
      var mx = G * 0.5, my = Y * 0.45;

      // Dalgalar: halka olarak çizilir ve parçacıkları iter
      dalgalar.forEach(function (d) {
        d.r += 6.5;
        var alfa = Math.max(0, 1 - d.r / (Math.max(G, Y) * 0.75));
        if (alfa <= 0) { d.olu = true; return; }
        ciz.beginPath();
        ciz.arc(mx, my, d.r, 0, Math.PI * 2);
        ciz.strokeStyle = 'rgba(245, 158, 11, ' + (alfa * 0.28 * d.guc).toFixed(3) + ')';
        ciz.lineWidth = 1.6;
        ciz.stroke();
      });
      dalgalar = dalgalar.filter(function (d) { return !d.olu; });

      parcaciklar.forEach(function (p, i) {
        // Dalga itişi: halkanın tam üstündeki parçacık en çok itilir
        dalgalar.forEach(function (d) {
          var dx = p.x - mx, dy = p.y - my;
          var uz = Math.sqrt(dx * dx + dy * dy) || 1;
          var fark = Math.abs(uz - d.r);
          if (fark < 34) {
            var itme = (1 - fark / 34) * 0.42 * d.guc;
            p.vx += (dx / uz) * itme;
            p.vy += (dy / uz) * itme;
          }
        });
        // Fare çekim/itme alanı
        if (fare.aktif) {
          var fx = p.x - fare.x, fy = p.y - fare.y;
          var fu = Math.sqrt(fx * fx + fy * fy) || 1;
          if (fu < 130) {
            var f = (1 - fu / 130) * 0.5;
            p.vx += (fx / fu) * f;
            p.vy += (fy / fu) * f;
          }
        }
        p.x += p.vx; p.y += p.vy;
        p.vx *= 0.965; p.vy *= 0.965;   // sürtünme: sonunda sakinliğe döner
        // Kenardan sarma
        if (p.x < -10) { p.x = G + 10; } else if (p.x > G + 10) { p.x = -10; }
        if (p.y < -10) { p.y = Y + 10; } else if (p.y > Y + 10) { p.y = -10; }

        var hiz = Math.sqrt(p.vx * p.vx + p.vy * p.vy);
        var parlak = Math.min(1, 0.18 + hiz * 1.7);
        ciz.beginPath();
        ciz.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ciz.fillStyle = p.p > 0.75
          ? 'rgba(167, 139, 250, ' + parlak.toFixed(3) + ')'
          : 'rgba(245, 158, 11, ' + parlak.toFixed(3) + ')';
        ciz.fill();

        // Yakın komşuları ince çizgiyle bağla — "alan" hissi
        for (var j = i + 1; j < parcaciklar.length; j++) {
          var q = parcaciklar[j];
          var ax = p.x - q.x, ay = p.y - q.y;
          var au = ax * ax + ay * ay;
          if (au < 6400) {
            ciz.beginPath();
            ciz.moveTo(p.x, p.y); ciz.lineTo(q.x, q.y);
            ciz.strokeStyle = 'rgba(245, 158, 11, ' + (0.09 * (1 - au / 6400)).toFixed(3) + ')';
            ciz.lineWidth = 0.6;
            ciz.stroke();
          }
        }
      });
      requestAnimationFrame(dongu);
    }

    var hero = document.getElementById('ust');
    hero.addEventListener('pointermove', function (ev) {
      var kutu = tuval.getBoundingClientRect();
      fare.x = ev.clientX - kutu.left; fare.y = ev.clientY - kutu.top; fare.aktif = true;
    });
    hero.addEventListener('pointerleave', function () { fare.aktif = false; });
    window.addEventListener('resize', boyutla);
    boyutla();
    requestAnimationFrame(dongu);
  })();

  /* ================================================================
     ZAMAN ÖLÇEĞİ — kaydırma ilerledikçe 2 aylık programdan milisaniyeye
     ================================================================ */
  (function zamanOlcegi() {
    var bolum = document.getElementById('olcek');
    if (!bolum) { return; }
    var etiket = document.getElementById('olcekEtiket');
    var deger = document.getElementById('olcekDeger');
    var aciklama = document.getElementById('olcekAciklama');
    var dolu = document.getElementById('olcekDolu');
    var halkalar = bolum.querySelectorAll('.t-olcek-halka span');

    var ADIMLAR = [
      { etiket: '2 AY',          deger: '4 838 400 000 ms',
        aciklama: 'Yoğunlaştırılmış akış: sekiz hafta, on altı oturum.' },
      { etiket: 'BİR OTURUM',    deger: '2 700 000 ms',
        aciklama: 'Kırk beş dakika. Isınma, hedef çalışma, oyun ve sakinleşme.' },
      { etiket: 'BİR ÇALIŞMA',   deger: '480 000 ms',
        aciklama: 'Sekiz dakikalık tek bir teknik: metronoma eşlik.' },
      { etiket: 'BİR VURUŞ',     deger: '833 ms',
        aciklama: '72 BPM’de iki vuruş arası. Artık saymıyorsunuz, hissediyorsunuz.' },
      { etiket: 'SAPMA',         deger: '± 25 ms',
        aciklama: 'Ölçtüğümüz büyüklük bu: vuruşun kaç milisaniye kaydığı.' },
      { etiket: 'İŞTE BURADA',   deger: '25 ms',
        aciklama: 'Göz kırpmanın onda biri. Atölyenin işi bu aralıkta geçiyor.' }
    ];

    function guncelle() {
      var kutu = bolum.getBoundingClientRect();
      var toplam = bolum.offsetHeight - window.innerHeight;
      if (toplam <= 0) { return; }
      var oran = Math.max(0, Math.min(1, -kutu.top / toplam));
      var idx = Math.min(ADIMLAR.length - 1, Math.floor(oran * ADIMLAR.length));
      var a = ADIMLAR[idx];
      if (etiket.textContent !== a.etiket) {
        etiket.textContent = a.etiket;
        deger.textContent = a.deger;
        aciklama.textContent = a.aciklama;
        // Yeni ölçekte kısa bir "yeniden odaklanma" hissi
        deger.animate(
          [{ opacity: 0, transform: 'translateY(14px) scale(.96)' }, { opacity: 1, transform: 'none' }],
          { duration: 420, easing: 'cubic-bezier(.2,.9,.3,1.1)' }
        );
      }
      dolu.style.width = Math.round(oran * 100) + '%';
      // Halkalar ölçek daraldıkça içe çöker
      halkalar.forEach(function (h, i) {
        h.style.transform = 'scale(' + (1 - oran * (0.55 + i * 0.12)).toFixed(3) + ')';
        h.style.opacity = (1 - oran * 0.5).toFixed(2);
      });
    }
    window.addEventListener('scroll', guncelle, { passive: true });
    window.addEventListener('resize', guncelle);
    guncelle();
  })();

  /* ================================================================
     TEK CANLI DENEY — EKSİK VURUŞ
     Dört sesli vuruştan sonra beşinci vuruş susar. Ziyaretçi o sessiz
     hedefi tek dokunuşla tamamlar. Sürgü, seviye veya teknik terim yoktur.
     ================================================================ */
  (function eksikVurus() {
    var kok = document.getElementById('sessizMeydan');
    if (!kok) { return; }

    var baslatBtn = document.getElementById('meydanBaslat');
    var vurBtn = document.getElementById('meydanVur');
    var durumEl = document.getElementById('meydanDurum');
    var etiketEl = document.getElementById('meydanEtiket');
    var merkezEl = document.getElementById('meydanMerkez');
    var altEl = document.getElementById('meydanAlt');
    var halka = document.getElementById('meydanHalka');
    var sonuc = document.getElementById('meydanSonuc');
    var sonucBaslik = document.getElementById('meydanSonucBaslik');
    var sonucDetay = document.getElementById('meydanSonucDetay');
    var noktalar = Array.prototype.slice.call(document.querySelectorAll('#meydanNoktalar span'));
    var profilAlan = document.getElementById('kayitProfil');
    var profilBilgi = document.getElementById('profilEklendi');
    var profilBilgiMetin = document.getElementById('profilEklendiMetin');

    var TEMPO_HAVUZU = [78, 84, 90];
    var aktif = false;
    var vurusAcik = false;
    var hedefZaman = 0;
    var zamanlayicilar = [];

    function zamanlayiciEkle(fn, gecikme) {
      zamanlayicilar.push(setTimeout(fn, Math.max(0, gecikme)));
    }

    function zamanlayicilariTemizle() {
      zamanlayicilar.forEach(clearTimeout);
      zamanlayicilar = [];
    }

    function gorunumuSifirla() {
      noktalar.forEach(function (n) { n.classList.remove('yandi', 'tamamlandi'); });
      kok.classList.remove('dinliyor', 'sira-sende', 'bitti');
      halka.classList.remove('vur', 'tamamlandi');
      sonuc.hidden = true;
      vurBtn.disabled = true;
      vurusAcik = false;
      merkezEl.textContent = 'HAZIR';
      altEl.textContent = '4 ses + 1 sessizlik';
      etiketEl.textContent = 'NASIL ÇALIŞIR?';
    }

    function gorselVurus(zaman, sira) {
      zamanlayiciEkle(function () {
        if (!aktif) { return; }
        noktalar.forEach(function (n) { n.classList.remove('yandi'); });
        if (noktalar[sira]) { noktalar[sira].classList.add('yandi', 'tamamlandi'); }
        halka.classList.add('vur');
        vurusuDuyur(sira === 0 ? 1.15 : 0.8);
        zamanlayiciEkle(function () {
          halka.classList.remove('vur');
          if (noktalar[sira]) { noktalar[sira].classList.remove('yandi'); }
        }, 130);
      }, (zaman - ctx.currentTime) * 1000);
    }

    function sonucuFormaEkle(metin) {
      if (profilAlan) { profilAlan.value = metin; }
      if (profilBilgi && profilBilgiMetin) {
        profilBilgi.hidden = false;
        profilBilgiMetin.textContent = metin;
      }
    }

    function bitir(sapmaMs) {
      if (!aktif) { return; }
      aktif = false;
      vurusAcik = false;
      zamanlayicilariTemizle();
      vurBtn.disabled = true;
      baslatBtn.textContent = '↻ Bir Daha Dene';
      kok.classList.remove('dinliyor', 'sira-sende');
      kok.classList.add('bitti');
      sonuc.hidden = false;

      if (sapmaMs === null) {
        merkezEl.textContent = 'KAÇTI';
        altEl.textContent = 'Bir sonraki tur hazır';
        etiketEl.textContent = 'BU TUR TAMAMLANMADI';
        durumEl.textContent = 'Sessiz vuruş geçti. Bir sonraki turda dört sesi dinleyip beşinciyi sen tamamla.';
        sonucBaslik.textContent = 'Vuruşu duymadan sürdürmek ilk anda şaşırtabilir.';
        sonucDetay.textContent = 'İstersen hemen yeniden deneyebilirsin.';
        sonucuFormaEkle('');
        return;
      }

      var mutlak = Math.round(Math.abs(sapmaMs));
      var yon = sapmaMs < 0 ? 'erken' : 'geç';
      noktalar.forEach(function (n) { n.classList.remove('yandi'); });
      if (noktalar[4]) { noktalar[4].classList.add('yandi', 'tamamlandi'); }
      halka.classList.add('tamamlandi');
      merkezEl.textContent = 'BULDUN';
      altEl.textContent = mutlak + ' ms ' + yon;
      etiketEl.textContent = 'EKSİK VURUŞ TAMAMLANDI';

      if (mutlak <= 70) {
        sonucBaslik.textContent = 'Tam yerine çok yakın.';
        durumEl.textContent = 'Sessizlik geldi ama nabız sende devam etti.';
      } else if (mutlak <= 160) {
        sonucBaslik.textContent = 'Nabzı korudun.';
        durumEl.textContent = 'Eksik vuruşu ' + mutlak + ' ms ' + yon + ' tamamladın.';
      } else {
        sonucBaslik.textContent = 'Bir tur daha?';
        durumEl.textContent = 'Vuruş ' + mutlak + ' ms ' + yon + ' geldi. Ritmi yeniden dinleyebilirsin.';
      }
      sonucDetay.textContent = mutlak + ' ms ' + yon + ' · cihaz gecikmesi bu kısa tanıtımda kalibre edilmez.';
      sonucuFormaEkle('Eksik vuruş deneyi: ' + mutlak + ' ms ' + yon);
    }

    function vur() {
      if (!aktif || !vurusAcik) { return; }
      var sapma = (ctx.currentTime - hedefZaman) * 1000;
      vurusAcik = false;
      klik(ctx.currentTime, true, 'yumusak');
      vurusuDuyur(1.25);
      // Düğmeyi aynı click döngüsü içinde devre dışı bırakmak bazı
      // tarayıcı/erişilebilirlik katmanlarında tıklamayı iptal edilmiş
      // gösterebilir. Sonuç ekranını bir sonraki görevde aç.
      setTimeout(function () { bitir(sapma); }, 0);
    }

    function durdur() {
      zamanlayicilariTemizle();
      aktif = false;
      vurusAcik = false;
      sesKes();
      gorunumuSifirla();
      baslatBtn.textContent = '▶ Ritmi Başlat';
      durumEl.textContent = 'Tur durduruldu. Hazır olduğunda dört vuruşu yeniden dinleyebilirsin.';
    }

    function baslat() {
      if (aktif) { durdur(); return; }
      digerleriniKapat('eksikvurus');
      sesHazirla();
      sesKes();
      gorunumuSifirla();

      var bpm = TEMPO_HAVUZU[Math.floor(Math.random() * TEMPO_HAVUZU.length)];
      var aralik = 60 / bpm;
      var ilk = ctx.currentTime + 0.45;
      hedefZaman = ilk + 4 * aralik;
      aktif = true;
      kok.classList.add('dinliyor');
      baslatBtn.textContent = '■ Durdur';
      etiketEl.textContent = 'DİNLİYORSUN';
      merkezEl.textContent = 'DİNLE';
      altEl.textContent = 'Vuruşları içinde say';
      durumEl.textContent = 'Dört vuruş geliyor. Beşincisi sessiz kalacak.';

      for (var i = 0; i < 4; i++) {
        var zaman = ilk + i * aralik;
        klik(zaman, i === 0, 'tahta');
        gorselVurus(zaman, i);
      }

      var sonSes = ilk + 3 * aralik;
      zamanlayiciEkle(function () {
        if (!aktif) { return; }
        kok.classList.remove('dinliyor');
        kok.classList.add('sira-sende');
        etiketEl.textContent = 'SIRA SENDE';
        merkezEl.textContent = 'ŞİMDİ?';
        altEl.textContent = 'Bir sonraki vuruş sessiz';
        durumEl.textContent = 'Nabzı içinde sürdür. Eksik vuruşun geldiğini hissettiğinde dokun.';
        vurBtn.disabled = false;
        vurusAcik = true;
      }, (sonSes - ctx.currentTime) * 1000 + 145);

      zamanlayiciEkle(function () { bitir(null); }, (hedefZaman - ctx.currentTime) * 1000 + 1200);
    }

    baslatBtn.addEventListener('click', baslat);
    vurBtn.addEventListener('click', function (ev) {
      ev.preventDefault();
      vur();
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.code === 'Space' && aktif && vurusAcik) {
        ev.preventDefault();
        vur();
      }
    });
    acikModuller.push({ ad: 'eksikvurus', durdur: function () { if (aktif) { durdur(); } } });
  })();

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

    /* Kişisel eşik: ziyaretçi "artık tek ses" dediği anı kendisi işaretler.
       Ölçülen şey öznel algı sınırı — profilin ilk parçası. */
    var isaretleBtn = document.getElementById('d1Isaretle');
    if (isaretleBtn) {
      isaretleBtn.addEventListener('click', function () {
        otomatikDur();
        profilBildir('esik', hz());
        durumEl.textContent = '🎯 Sizin eşiğiniz: ' + hz() + ' Hz — profilinize eklendi';
        var profilKutu = document.getElementById('ritimProfil');
        if (profilKutu) { profilKutu.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
      });
    }

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
      profilBildir('sapma', Math.round(ortMutlak));
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
        profilBildir('algi', dogru);
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
        (function (z) {
          setTimeout(function () { vurusuDuyur(0.7); },
            Math.max(0, (z - ctx.currentTime) * 1000));
        })(sonrakiZaman);
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

  /* ================================================================
     RİTİM PROFİLİ — üç deneyin ortak sonucu
     Hiçbir yere kaydedilmez; yalnız sayfada yaşar. Kullanıcı isterse
     tek cümlelik özeti ön kayıt formuna taşır.
     ================================================================ */
  var profil = { esik: null, sapma: null, algi: null, tempo: null };

  function profilBildir(alan, deger) {
    profil[alan] = deger;
    profilCiz();
  }

  function profilCiz() {
    var kutu = document.getElementById('ritimProfil');
    if (!kutu) { return; }
    var rozetler = document.getElementById('profilRozetler');
    var metin = document.getElementById('profilMetin');
    var dolu = [];

    rozetler.innerHTML = '';
    function rozet(buyuk, etiketMetni) {
      var d = document.createElement('div');
      d.className = 't-profil-rozet';
      var b = document.createElement('b'); b.textContent = buyuk;
      var s = document.createElement('span'); s.textContent = etiketMetni;
      d.appendChild(b); d.appendChild(s);
      rozetler.appendChild(d);
    }

    if (profil.esik !== null) {
      rozet(profil.esik + ' Hz', 'ritmi tona çevirdiğiniz nokta');
      dolu.push('eşik ' + profil.esik + ' Hz');
    }
    if (profil.sapma !== null) {
      rozet(profil.sapma + ' ms', 'ortalama vuruş sapmanız');
      dolu.push('sapma ' + profil.sapma + ' ms');
    }
    if (profil.algi !== null) {
      rozet(profil.algi + '/3', 'aksak vuruşu yakalama');
      dolu.push('aksak algısı ' + profil.algi + '/3');
    }
    if (profil.tempo !== null) {
      rozet(profil.tempo, 'gizli tempo tahmini');
      dolu.push('tempo tahmini ' + profil.tempo);
    }
    if (!dolu.length) { kutu.hidden = true; return; }

    kutu.hidden = false;
    var cumleler = [];
    if (profil.esik !== null) {
      cumleler.push(profil.esik <= 18
        ? 'Vuruşları oldukça geç noktada birleştirdiniz — ayrım gücünüz iyi.'
        : 'Vuruşlar sizde ' + profil.esik + ' Hz civarında tek sese dönüştü; tipik aralıkta.');
    }
    if (profil.sapma !== null) {
      cumleler.push(profil.sapma <= 40
        ? 'Metronoma yakın vurdunuz (' + profil.sapma + ' ms).'
        : 'Vuruşlarınız ortalama ' + profil.sapma + ' ms kaydı — pratikle daralan bir aralık.');
    }
    if (profil.algi !== null) {
      cumleler.push(profil.algi >= 2
        ? 'Aksayan diziyi ' + profil.algi + '/3 yakaladınız; kulağınız uyanık.'
        : 'Aksak diziyi ayırmak zorlandı — bu tam olarak pratikle gelişen beceri.');
    }
    if (profil.tempo !== null) {
      var parcalar = profil.tempo.split('/');
      var oran = parseInt(parcalar[0], 10) / Math.max(1, parseInt(parcalar[1], 10));
      cumleler.push(oran >= 0.6
        ? 'Gizli tempo oyununda ' + profil.tempo + ' doğru bildiniz — tempo hafızanız güçlü.'
        : 'Gizli tempo oyununda ' + profil.tempo + ' bildiniz; kulak bu işte pratikle keskinleşir.');
    }
    metin.textContent = cumleler.join(' ');

    // Forma taşınacak özet
    var ozet = dolu.join(' · ');
    var alan = document.getElementById('kayitProfil');
    var bilgi = document.getElementById('profilEklendi');
    var bilgiMetin = document.getElementById('profilEklendiMetin');
    if (alan) { alan.value = ozet; }
    if (bilgi && bilgiMetin) { bilgi.hidden = false; bilgiMetin.textContent = ozet; }
  }

  (function profilKontrolleri() {
    var sifirla = document.getElementById('profilSifirla');
    if (!sifirla) { return; }
    sifirla.addEventListener('click', function () {
      profil = { esik: null, sapma: null, algi: null, tempo: null };
      document.getElementById('ritimProfil').hidden = true;
      var alan = document.getElementById('kayitProfil');
      var bilgi = document.getElementById('profilEklendi');
      if (alan) { alan.value = ''; }
      if (bilgi) { bilgi.hidden = true; }
    });
  })();

  /* ================================================================
     HERO — "TEMPOYU YAKALA": gizli, ayarlanamaz tempo tahmin oyunu
     Eski "Ritmi Hisset" (sabit 96 BPM'de yalnız hissettiren toggle)
     yerine geldi. Burada sürgü YOK: tempo sabit bir havuzdan gizlice
     seçilir, dinletilir, ardından dört seçenekten biri işaretlenir.
     Havuzdaki değerler bilhassa "saniyede kaç vuruş" ile okunabilir:
     60 = saniyede 1, 120 = saniyede 2, 180 = saniyede 3 — Deney 1'deki
     "saniyede kaç ses duyuyorsunuz" sorusuyla aynı ekseni sağda taşır.
     Aynı ses bağlamını ve sağdaki halka/canvas görsellerini paylaşır.
     ================================================================ */
  (function tempoOyunu() {
    var kok = document.getElementById('tempoOyun');
    if (!kok) { return; }
    var durumEl = document.getElementById('tempoDurum');
    var baslatBtn = document.getElementById('tempoBaslat');
    var secenekKutu = document.getElementById('tempoSecenekler');
    var sonucEl = document.getElementById('tempoSonuc');
    var skorEl = document.getElementById('tempoSkor');
    var halka = document.getElementById('vurusHalkasi');
    var ikon = document.getElementById('tempoIkon');
    var noktaKutu = document.getElementById('tempoNoktalar');

    var HAVUZ = [60, 80, 100, 120, 140, 160, 180];
    /* Sabit dinletme süresi: tempodan bağımsız olarak HER TUR aynı sürede
       biter (eski sürümde 60 BPM'de 14 sn, 180 BPM'de ~5 sn sürüyordu —
       yavaş tempolarda beklemek can sıkıcıydı). Vuruş sayısı temposuna
       göre kendiliğinden çıkar; en az 3 vuruş garanti edilir. */
    var SURE_SN = 5;

    var calisiyor = false, hedef = null, sonrakiZaman = 0, vurusNo = 0, toplamVurus = 0, zamanlayici = null;
    var sonBeatZamani = 0;
    var dogru = 0, toplam = 0;

    /** Vuruş noktalarını kur: bu turdaki toplam vuruş kadar boş nokta. */
    function noktalariKur(adet) {
      noktaKutu.innerHTML = '';
      for (var i = 0; i < adet; i++) {
        var n = document.createElement('span');
        n.className = 't-tempo-nokta';
        noktaKutu.appendChild(n);
      }
    }

    function gorselVurus(zaman, sira) {
      var gecikme = Math.max(0, (zaman - ctx.currentTime) * 1000);
      setTimeout(function () {
        vurusuDuyur(1);
        if (halka) {
          halka.classList.add('vur');
          setTimeout(function () { halka.classList.remove('vur'); }, 110);
        }
        if (ikon) {
          ikon.classList.add('vur');
          setTimeout(function () { ikon.classList.remove('vur'); }, 110);
        }
        var nokta = noktaKutu.children[sira];
        if (nokta) { nokta.classList.add('yandi'); }
      }, gecikme);
    }

    function planla() {
      while (calisiyor && sonrakiZaman < ctx.currentTime + 0.12 && vurusNo < toplamVurus) {
        klik(sonrakiZaman, vurusNo % 4 === 0, 'klik');
        gorselVurus(sonrakiZaman, vurusNo);
        if (vurusNo === toplamVurus - 1) { sonBeatZamani = sonrakiZaman; }
        sonrakiZaman += 60 / hedef;
        vurusNo++;
      }
      // Son vuruş SCHEDULE edilir edilmez değil, gerçekten ÇALINIP noktası
      // yandıktan sonra seçenekleri göster (25-120ms'lik lookahead payı
      // yüzünden anında geçilirse son nokta seçeneklerden SONRA yanıyordu).
      if (calisiyor && vurusNo >= toplamVurus && ctx.currentTime > sonBeatZamani + 0.15) { bitirVeSor(); }
    }

    /** Dört seçenek: gizli tempo + havuzdan karışık 3 çeldirici. */
    function secenekleriKur() {
      var digerleri = HAVUZ.filter(function (b) { return b !== hedef; });
      for (var i = digerleri.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var t = digerleri[i]; digerleri[i] = digerleri[j]; digerleri[j] = t;
      }
      var secenekler = [hedef].concat(digerleri.slice(0, 3));
      for (i = secenekler.length - 1; i > 0; i--) {
        j = Math.floor(Math.random() * (i + 1));
        t = secenekler[i]; secenekler[i] = secenekler[j]; secenekler[j] = t;
      }
      secenekKutu.innerHTML = '';
      secenekler.forEach(function (bpm) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 't-tempo-secenek';
        b.textContent = bpm + ' BPM';
        b.dataset.bpm = bpm;
        secenekKutu.appendChild(b);
      });
      secenekKutu.hidden = false;
    }

    function bitirVeSor() {
      clearInterval(zamanlayici);
      calisiyor = false;
      sesKes();
      durumEl.textContent = 'Sence bu hangi tempoydu?';
      baslatBtn.hidden = true;
      secenekleriKur();
    }

    function baslat() {
      digerleriniKapat('herotempo');
      sesHazirla();
      sesKes();
      hedef = HAVUZ[Math.floor(Math.random() * HAVUZ.length)];
      toplamVurus = Math.max(3, Math.round(SURE_SN * hedef / 60));
      noktalariKur(toplamVurus);
      vurusNo = 0;
      sonrakiZaman = ctx.currentTime + 0.15;
      calisiyor = true;
      sonucEl.hidden = true;
      secenekKutu.hidden = true;
      baslatBtn.hidden = true;
      durumEl.textContent = '🎧 Dinle…';
      zamanlayici = setInterval(planla, 25);
    }

    /* Başka bir demo başlatılınca dışarıdan çağrılır: yarım kalan turu
       iptal edip kartı "yeniden başlat" durumuna döndürür — aksi hâlde
       "🎧 Dinle…" yazısında donup kalır ve kullanıcı devam edemez. */
    function durdur() {
      clearInterval(zamanlayici);
      calisiyor = false;
      sesKes();
      secenekKutu.hidden = true;
      sonucEl.hidden = true;
      baslatBtn.hidden = false;
      baslatBtn.textContent = '▶ Gizli Tempoyu Çal';
      durumEl.textContent = 'Başka bir ses çalındığı için tur iptal edildi. Tekrar deneyin.';
      noktaKutu.innerHTML = '';
    }

    secenekKutu.addEventListener('click', function (ev) {
      var b = ev.target.closest('.t-tempo-secenek');
      if (!b) { return; }
      var secilen = parseInt(b.dataset.bpm, 10);
      var dogruMu = secilen === hedef;
      toplam++;
      if (dogruMu) { dogru++; }
      Array.prototype.slice.call(secenekKutu.children).forEach(function (x) {
        x.disabled = true;
        if (parseInt(x.dataset.bpm, 10) === hedef) { x.classList.add('dogru'); }
      });
      if (!dogruMu) { b.classList.add('yanlis'); }
      sonucEl.hidden = false;
      sonucEl.className = 't-tempo-sonuc ' + (dogruMu ? 'basari' : 'hata');
      sonucEl.innerHTML = dogruMu
        ? '✓ Doğru! Gizli tempo <strong>' + hedef + ' BPM</strong> idi.'
        : '✕ Bu sefer olmadı — gizli tempo <strong>' + hedef + ' BPM</strong> idi.';
      skorEl.hidden = false;
      skorEl.textContent = 'Skor: ' + dogru + ' / ' + toplam;
      durumEl.textContent = 'Tekrar dene:';
      baslatBtn.hidden = false;
      baslatBtn.textContent = '🔁 Yeni Gizli Tempo';
      profilBildir('tempo', dogru + '/' + toplam);
    });

    baslatBtn.addEventListener('click', baslat);
    acikModuller.push({ ad: 'herotempo', durdur: function () { if (calisiyor) { durdur(); } } });
  })();

  /* Program kartındaki seçim, iletişim formundaki ders tercihini hazırlar. */
  (function dersTuruSecimi() {
    var baglantilar = document.querySelectorAll('[data-ders-tercihi]');
    if (!baglantilar.length) { return; }
    baglantilar.forEach(function (baglanti) {
      baglanti.addEventListener('click', function () {
        var deger = baglanti.getAttribute('data-ders-tercihi');
        var alan = document.querySelector('input[name="ders_turu"][value="' + deger + '"]');
        if (alan) { alan.checked = true; }
      });
    });
  })();

  /* ================================================================
     Hareket denetimi (WCAG 2.2.2 — Duraklat, Durdur, Gizle)
     ================================================================ */
  (function () {
    var btn = document.getElementById('tHareketAnahtari');
    if (!btn) { return; }
    var ANAHTAR = 'ritim-hareket-kapali';
    var metin = btn.querySelector('.t-hareket-metin');

    function uygula(kapali, yaz) {
      document.documentElement.classList.toggle('hareket-kapali', kapali);
      btn.setAttribute('aria-pressed', kapali ? 'true' : 'false');
      btn.title = kapali ? 'Sayfadaki hareketi geri aç' : 'Sayfadaki hareketi durdur';
      if (metin) { metin.textContent = kapali ? 'Hareketi aç' : 'Hareketi durdur'; }
      if (yaz) { try { localStorage.setItem(ANAHTAR, kapali ? '1' : '0'); } catch (e) {} }
    }

    /* Kullanıcının açık tercihi işletim sistemi tercihini EZER; hiç seçim
       yapmadıysa sistem tercihi (varsa) düğmeye yansır. */
    var kayitli = null;
    try { kayitli = localStorage.getItem(ANAHTAR); } catch (e) {}
    var sistem = window.matchMedia
      && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    uygula(kayitli !== null ? kayitli === '1' : !!sistem, false);

    btn.addEventListener('click', function () {
      uygula(btn.getAttribute('aria-pressed') !== 'true', true);
    });
  })();

})();

/* ================================================================
   PREMIUM KATMAN — imleç ışığı, paralaks, eğilen kartlar, başlık
   ================================================================
   Ayrı bir IIFE: ana modülün iç durumuna gerek yok. Tüm kare
   döngüleri her karede kisitli()'yi yoklar — kullanıcı "Hareketi
   durdur"a basınca bir sonraki karede dururlar; MutationObserver
   gerekmez, sınıf yoklaması ucuzdur.
   ================================================================ */
(function () {
  'use strict';

  function kisitli() {
    return document.documentElement.classList.contains('hareket-kapali')
      || (window.matchMedia
          && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }
  var inceIsaretci = window.matchMedia
    && window.matchMedia('(pointer: fine)').matches;

  /* ---------------------------------------------------------------
     1) Başlık: kelime kelime perde açılışı.
     Sözcükler span'lere sarılır (--i = sıra); em.t-gradyan TEK parça
     sarılır — background-clip:text'li öğenin çocuklarını ayrı ayrı
     dönüştürmek bazı tarayıcılarda degradeyi bozar.
     --------------------------------------------------------------- */
  (function () {
    if (kisitli()) { return; }   // kısıtlıysa başlık düz metin kalır
    var h1 = document.querySelector('.t-hero h1');
    if (!h1) { return; }
    var sira = 0;
    Array.prototype.slice.call(h1.childNodes).forEach(function (c) {
      if (c.nodeType === 3 && c.textContent.trim() !== '') {
        var parca = document.createDocumentFragment();
        c.textContent.split(/(\s+)/).forEach(function (p) {
          if (p === '') { return; }
          if (/^\s+$/.test(p)) { parca.appendChild(document.createTextNode(p)); return; }
          var d = document.createElement('span');
          d.className = 't-soz';
          d.style.setProperty('--i', String(sira++));
          d.textContent = p;
          parca.appendChild(d);
        });
        h1.replaceChild(parca, c);
      } else if (c.nodeType === 1 && c.tagName === 'EM') {
        var sargi = document.createElement('span');
        sargi.className = 't-soz';
        sargi.style.setProperty('--i', String(sira++));
        h1.replaceChild(sargi, c);
        sargi.appendChild(c);
      }
    });
    h1.classList.add('t-baslik-canli');
  })();

  /* ---------------------------------------------------------------
     2) İmleç ışığı — sayfayı gezen yumuşak spot.
     Yalnız ince işaretçi (fare); dokunmatikte anlamsız.
     --------------------------------------------------------------- */
  (function () {
    var isik = document.getElementById('tIsik');
    if (!isik || !inceIsaretci) { return; }
    var hx = -900, hy = -900, gx = hx, gy = hy, calisiyor = false;
    document.addEventListener('pointermove', function (ev) {
      if (kisitli()) { isik.classList.remove('acik'); return; }
      hx = ev.clientX; hy = ev.clientY;
      isik.classList.add('acik');
      if (!calisiyor) { calisiyor = true; requestAnimationFrame(adim); }
    }, { passive: true });
    function adim() {
      if (kisitli()) { calisiyor = false; return; }
      gx += (hx - gx) * .16;
      gy += (hy - gy) * .16;
      isik.style.transform = 'translate3d(' + (gx - 280).toFixed(1) + 'px,'
        + (gy - 280).toFixed(1) + 'px,0)';
      if (Math.abs(gx - hx) > .4 || Math.abs(gy - hy) > .4) {
        requestAnimationFrame(adim);
      } else { calisiyor = false; }
    }
  })();

  /* ---------------------------------------------------------------
     3) Hero paralaksı — data-derinlik taşıyan KAPSAYICILAR imlecin
     tersine kayar. Hedefler bilerek sarmalayıcı: kendi transform
     animasyonu olan öğeye inline transform yazmak animasyonu ezer.
     --------------------------------------------------------------- */
  (function () {
    if (!inceIsaretci) { return; }
    var hero = document.querySelector('.t-hero');
    if (!hero) { return; }
    var katmanlar = Array.prototype.slice.call(hero.querySelectorAll('[data-derinlik]'));
    if (!katmanlar.length) { return; }
    var hx = 0, hy = 0, gx = 0, gy = 0, calisiyor = false;
    hero.addEventListener('pointermove', function (ev) {
      var r = hero.getBoundingClientRect();
      hx = (ev.clientX - r.left) / Math.max(1, r.width) - .5;
      hy = (ev.clientY - r.top) / Math.max(1, r.height) - .5;
      basla();
    }, { passive: true });
    hero.addEventListener('pointerleave', function () { hx = 0; hy = 0; basla(); });
    function basla() {
      if (!calisiyor) { calisiyor = true; requestAnimationFrame(adim); }
    }
    function adim() {
      if (kisitli()) {
        katmanlar.forEach(function (k) { k.style.transform = ''; });
        calisiyor = false; return;
      }
      gx += (hx - gx) * .07;
      gy += (hy - gy) * .07;
      katmanlar.forEach(function (k) {
        var d = parseFloat(k.dataset.derinlik) || 0;
        k.style.transform = 'translate3d(' + (-gx * d).toFixed(2) + 'px,'
          + (-gy * d).toFixed(2) + 'px,0)';
      });
      if (Math.abs(gx - hx) > .002 || Math.abs(gy - hy) > .002) {
        requestAnimationFrame(adim);
      } else { calisiyor = false; }
    }
  })();

  /* ---------------------------------------------------------------
     4) Eğilen kartlar (data-tilt) — 3B derinlik, en çok ~7 derece.
     --------------------------------------------------------------- */
  (function () {
    if (!inceIsaretci) { return; }
    document.querySelectorAll('[data-tilt]').forEach(function (el) {
      el.addEventListener('pointermove', function (ev) {
        if (kisitli()) { el.style.transform = ''; return; }
        var r = el.getBoundingClientRect();
        var x = (ev.clientX - r.left) / Math.max(1, r.width) - .5;
        var y = (ev.clientY - r.top) / Math.max(1, r.height) - .5;
        el.style.transform = 'perspective(900px) rotateX(' + (-y * 7).toFixed(2)
          + 'deg) rotateY(' + (x * 7).toFixed(2) + 'deg) translateY(-2px)';
      });
      el.addEventListener('pointerleave', function () { el.style.transform = ''; });
    });
  })();

  /* ---------------------------------------------------------------
     5) Mıknatıs CTA — büyük düğme imlece hafifçe uzanır.
     --------------------------------------------------------------- */
  (function () {
    if (!inceIsaretci) { return; }
    var btn = document.querySelector('.t-cta-satir .t-btn-buyuk');
    if (!btn) { return; }
    btn.addEventListener('pointermove', function (ev) {
      if (kisitli()) { btn.style.transform = ''; return; }
      var r = btn.getBoundingClientRect();
      var x = (ev.clientX - r.left) / Math.max(1, r.width) - .5;
      var y = (ev.clientY - r.top) / Math.max(1, r.height) - .5;
      btn.style.transform = 'translate(' + (x * 9).toFixed(1) + 'px,'
        + (y * 7 - 2).toFixed(1) + 'px)';
    });
    btn.addEventListener('pointerleave', function () { btn.style.transform = ''; });
  })();
})();
