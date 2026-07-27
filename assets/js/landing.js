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

  /* "Ritmi Hisset" — Web Audio ile 4/4 vuruş, halka nabzı eşliğinde */
  var btn = document.getElementById('ritimBtn');
  var halka = document.getElementById('vurusHalkasi');
  if (!btn) { return; }

  var ctx = null;
  var calıyor = false;
  var zamanlayici = null;
  var sonrakiVurusZamani = 0;
  var vurusNo = 0;
  var BPM = 96;

  function vurusCal(zaman, aksan) {
    var osc = ctx.createOscillator();
    var kazanc = ctx.createGain();
    osc.type = 'square';
    osc.frequency.value = aksan ? 1568 : 1046;
    kazanc.gain.setValueAtTime(aksan ? 0.5 : 0.3, zaman);
    kazanc.gain.exponentialRampToValueAtTime(0.001, zaman + 0.08);
    osc.connect(kazanc).connect(ctx.destination);
    osc.start(zaman);
    osc.stop(zaman + 0.09);
  }

  function planla() {
    while (sonrakiVurusZamani < ctx.currentTime + 0.12) {
      var aksan = vurusNo % 4 === 0;
      vurusCal(sonrakiVurusZamani, aksan);
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

  btn.addEventListener('click', function () {
    if (!ctx) {
      ctx = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (calıyor) {
      clearInterval(zamanlayici);
      calıyor = false;
      btn.textContent = '▶ Ritmi Hisset';
      return;
    }
    ctx.resume().then(function () {
      vurusNo = 0;
      sonrakiVurusZamani = ctx.currentTime + 0.1;
      zamanlayici = setInterval(planla, 25);
      calıyor = true;
      btn.textContent = '⏸ Durdur';
    });
  });
})();
