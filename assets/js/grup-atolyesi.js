(function () {
  'use strict';

  var core = window.GrupAtolyesiCekirdegi;
  if (!core || !document.getElementById('grupBaslat')) { return; }

  function byId(id) { return document.getElementById(id); }
  function all(secici) { return Array.prototype.slice.call(document.querySelectorAll(secici)); }

  var el = {
    kartlar: byId('grupProtokolKartlari'),
    sure: byId('grupSure'),
    bpm: byId('grupBpm'),
    mod: byId('grupMod'),
    ses: byId('grupSes'),
    ozet: byId('grupSeciliOzet'),
    hazirlik: byId('grupHazirlikRozet'),
    hareketUyari: byId('grupHareketUyarisi'),
    baslat: byId('grupBaslat'),
    oynatici: byId('grupOynatici'),
    adimSayisi: byId('grupAdimSayisi'),
    baslik: byId('grupOynaticiBaslik'),
    adimSure: byId('grupAdimSure'),
    toplamSure: byId('grupToplamSure'),
    ilerleme: byId('grupIlerlemeCubugu'),
    nabiz: byId('grupNabiz'),
    yonerge: byId('grupYonerge'),
    kolaylastirici: byId('grupKolaylastirici'),
    durum: byId('grupDurum'),
    onceki: byId('grupOnceki'),
    duraklat: byId('grupDuraklat'),
    sonraki: byId('grupSonraki'),
    bitir: byId('grupBitir')
  };

  var durum = {
    protokolId: core.PROTOKOLLER[0].id,
    oturum: null,
    adim: 0,
    calisiyor: false,
    sayimda: false,
    adimKalanMs: 0,
    toplamKalanMs: 0,
    sonKare: 0,
    raf: 0,
    nesil: 0
  };

  var audio = {
    ctx: null,
    gain: null,
    zamanlayici: null,
    gorselZamanlayicilar: [],
    sonraki: 0,
    vurus: 0,
    nesil: 0,

    hazirla: function () {
      if (!this.ctx) {
        this.ctx = new (window.AudioContext || window.webkitAudioContext)();
      }
      if (this.ctx.state === 'suspended') { this.ctx.resume(); }
      this.yeniGain();
    },

    yeniGain: function () {
      if (this.gain) {
        try { this.gain.disconnect(); } catch (hata) { /* zaten ayrılmış */ }
      }
      this.gain = this.ctx.createGain();
      this.gain.gain.value = 0.42;
      this.gain.connect(this.ctx.destination);
    },

    klik: function (zaman, aksan) {
      if (el.ses.value !== 'acik') { return; }
      var osilator = this.ctx.createOscillator();
      var ses = this.ctx.createGain();
      osilator.type = 'sine';
      osilator.frequency.value = aksan ? 980 : 640;
      ses.gain.setValueAtTime(aksan ? 0.42 : 0.26, zaman);
      ses.gain.exponentialRampToValueAtTime(0.001, zaman + 0.055);
      osilator.connect(ses).connect(this.gain);
      osilator.start(zaman);
      osilator.stop(zaman + 0.065);
    },

    basla: function (bpm, sayimVurusu, sayimBitti) {
      this.durdur();
      this.hazirla();
      this.nesil++;
      var kendiNesli = this.nesil;
      var aralik = 60 / bpm;
      this.sonraki = this.ctx.currentTime + 0.08;
      this.vurus = 0;
      var self = this;

      function planla() {
        if (kendiNesli !== self.nesil) { return; }
        while (self.sonraki < self.ctx.currentTime + 0.12) {
          var sira = self.vurus;
          var zaman = self.sonraki;
          self.klik(zaman, sira % 4 === 0);
          (function (vurusSirasi, hedefZaman) {
            var gecikme = Math.max(0, (hedefZaman - self.ctx.currentTime) * 1000);
            var z = window.setTimeout(function () {
              if (kendiNesli !== self.nesil) { return; }
              nabizGoster(vurusSirasi % 4);
              if (vurusSirasi < sayimVurusu) {
                el.durum.textContent = 'Hazırlık: ' + (vurusSirasi + 1) + ' / ' + sayimVurusu;
              } else if (vurusSirasi === sayimVurusu) {
                sayimBitti();
              }
            }, gecikme);
            self.gorselZamanlayicilar.push(z);
          })(sira, zaman);
          self.vurus++;
          self.sonraki += aralik;
        }
      }

      planla();
      this.zamanlayici = window.setInterval(planla, 25);
    },

    durdur: function () {
      this.nesil++;
      if (this.zamanlayici) { window.clearInterval(this.zamanlayici); }
      this.zamanlayici = null;
      this.gorselZamanlayicilar.forEach(window.clearTimeout);
      this.gorselZamanlayicilar = [];
      if (this.gain) {
        try { this.gain.disconnect(); } catch (hata) { /* zaten ayrılmış */ }
        this.gain = null;
      }
      nabizTemizle();
    }
  };

  function protokolKartlariniCiz() {
    el.kartlar.innerHTML = '';
    core.PROTOKOLLER.forEach(function (p, i) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'grup-protokol-secim' + (i === 0 ? ' secili' : '');
      btn.setAttribute('role', 'radio');
      btn.setAttribute('aria-checked', i === 0 ? 'true' : 'false');
      /* Radyo grubunda TEK Tab durağı olur, seçim ok tuşlarıyla değişir
         (WAI-ARIA APG "Radio Group"). Hepsi Tab durağı olsaydı klavye
         kullanıcısı grubu geçmek için beş kez Tab'a basardı. */
      btn.setAttribute('tabindex', i === 0 ? '0' : '-1');
      btn.dataset.protokol = p.id;

      var ikon = document.createElement('span');
      ikon.className = 'grup-protokol-ikon';
      ikon.textContent = p.ikon;
      var metin = document.createElement('span');
      var ad = document.createElement('strong');
      ad.textContent = p.ad;
      var uygunluk = document.createElement('small');
      uygunluk.textContent = p.uygunluk;
      metin.appendChild(ad);
      metin.appendChild(uygunluk);
      btn.appendChild(ikon);
      btn.appendChild(metin);
      btn.addEventListener('click', function () { protokolSec(p.id); });
      btn.addEventListener('keydown', function (ev) {
        var adim = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[ev.key];
        var n = core.PROTOKOLLER.length;
        var j = null;
        if (adim) { j = (i + adim + n) % n; }
        else if (ev.key === 'Home') { j = 0; }
        else if (ev.key === 'End') { j = n - 1; }
        if (j === null) { return; }
        ev.preventDefault();
        protokolSec(core.PROTOKOLLER[j].id);
        var hedef = el.kartlar.querySelector('[data-protokol="' + core.PROTOKOLLER[j].id + '"]');
        if (hedef) { hedef.focus(); }
      });
      el.kartlar.appendChild(btn);
    });
  }

  function protokolSec(id) {
    durum.protokolId = id;
    var p = core.protokolBul(id);
    el.bpm.value = p.varsayilanBpm;
    all('[data-protokol]').forEach(function (btn) {
      var secili = btn.dataset.protokol === id;
      btn.classList.toggle('secili', secili);
      btn.setAttribute('aria-checked', secili ? 'true' : 'false');
      /* Tab durağı hep SEÇİLİ olana taşınır; yoksa kullanıcı gruba geri
         döndüğünde odak seçili olmayan bir karta düşer. */
      if (btn.getAttribute('role') === 'radio') {
        btn.setAttribute('tabindex', secili ? '0' : '-1');
      }
    });
    ozetGuncelle();
  }

  function ozetGuncelle() {
    var p = core.protokolBul(durum.protokolId);
    var etiket = p.kanit === 'orta' ? 'Orta / koşullu dayanak' :
      (p.kanit === 'zayif' ? 'Zayıf / sınırlı dayanak' : 'Bilimsel sonuç iddiası yok');
    el.ozet.innerHTML = '';
    var ust = document.createElement('div');
    var h = document.createElement('h3');
    h.textContent = p.ad;
    var r = document.createElement('span');
    r.className = 'rozet rozet-kanit-' + p.kanit;
    r.textContent = etiket;
    ust.appendChild(h);
    ust.appendChild(r);
    var o = document.createElement('p');
    o.textContent = p.ozet;
    var d = document.createElement('small');
    d.textContent = p.dayanak;
    el.ozet.appendChild(ust);
    el.ozet.appendChild(o);
    el.ozet.appendChild(d);
    el.hareketUyari.hidden = el.mod.value !== 'ayakta';
    guvenlikGuncelle();
  }

  function guvenlikDurumu() {
    var d = {};
    all('[data-grup-kontrol]').forEach(function (k) { d[k.dataset.grupKontrol] = k.checked; });
    return d;
  }

  function guvenlikGuncelle() {
    var d = guvenlikDurumu();
    var sayi = Object.keys(d).filter(function (k) { return d[k]; }).length;
    el.hazirlik.textContent = sayi + ' / 4';
    el.hazirlik.className = 'rozet ' + (sayi === 4 ? 'rozet-kanit-guclu' : 'rozet-gri');
    el.baslat.disabled = !core.guvenlikHazir(d) || durum.calisiyor || durum.sayimda;
  }

  function oturumKalaniniHesapla() {
    if (!durum.oturum) { return 0; }
    var kalan = durum.adimKalanMs;
    for (var i = durum.adim + 1; i < durum.oturum.adimlar.length; i++) {
      kalan += durum.oturum.adimlar[i].sureSn * 1000;
    }
    return kalan;
  }

  function adimGoster() {
    var a = durum.oturum.adimlar[durum.adim];
    el.adimSayisi.textContent = 'ADIM ' + (durum.adim + 1) + ' / ' + durum.oturum.adimlar.length;
    el.baslik.textContent = a.baslik;
    el.yonerge.textContent = a.yonerge;
    el.kolaylastirici.textContent = a.kolaylastirici;
    el.adimSure.textContent = core.sureYaz(durum.adimKalanMs / 1000);
    el.toplamSure.textContent = core.sureYaz(durum.toplamKalanMs / 1000);
    el.onceki.disabled = durum.adim === 0;
    el.sonraki.textContent = durum.adim === durum.oturum.adimlar.length - 1 ? 'Akışı tamamla ✓' : 'Sonraki →';
    ilerlemeGuncelle();
  }

  function ilerlemeGuncelle() {
    if (!durum.oturum) { return; }
    var gecen = durum.oturum.toplamSn * 1000 - durum.toplamKalanMs;
    var oran = Math.max(0, Math.min(100, gecen / (durum.oturum.toplamSn * 1000) * 100));
    el.ilerleme.style.width = oran.toFixed(2) + '%';
  }

  function nabizGoster(index) {
    var noktalar = Array.prototype.slice.call(el.nabiz.children);
    noktalar.forEach(function (n, i) { n.classList.toggle('aktif', i === index); });
  }

  function nabizTemizle() {
    Array.prototype.slice.call(el.nabiz.children).forEach(function (n) { n.classList.remove('aktif'); });
  }

  function kare(zaman) {
    if (!durum.calisiyor) { return; }
    if (!durum.sonKare) { durum.sonKare = zaman; }
    var fark = Math.min(250, Math.max(0, zaman - durum.sonKare));
    durum.sonKare = zaman;
    durum.adimKalanMs -= fark;
    durum.toplamKalanMs -= fark;

    if (durum.adimKalanMs <= 0) {
      if (durum.adim < durum.oturum.adimlar.length - 1) {
        durum.adim++;
        durum.adimKalanMs = durum.oturum.adimlar[durum.adim].sureSn * 1000;
        el.durum.textContent = 'Yeni adım: ' + durum.oturum.adimlar[durum.adim].baslik;
        adimGoster();
      } else {
        tamamla();
        return;
      }
    }
    el.adimSure.textContent = core.sureYaz(durum.adimKalanMs / 1000);
    el.toplamSure.textContent = core.sureYaz(durum.toplamKalanMs / 1000);
    ilerlemeGuncelle();
    durum.raf = window.requestAnimationFrame(kare);
  }

  function sayimlaCalistir(devamMi) {
    durum.sayimda = true;
    durum.calisiyor = false;
    el.baslat.disabled = true;
    el.duraklat.disabled = true;
    el.durum.textContent = 'Dört hazırlık vuruşunu dinleyin.';
    var nesil = ++durum.nesil;
    audio.basla(durum.oturum.bpm, 4, function () {
      if (nesil !== durum.nesil) { return; }
      durum.sayimda = false;
      durum.calisiyor = true;
      durum.sonKare = 0;
      el.duraklat.disabled = false;
      el.duraklat.textContent = 'Duraklat';
      el.durum.textContent = devamMi ? 'Yeniden giriş tamamlandı.' : 'Oturum başladı.';
      durum.raf = window.requestAnimationFrame(kare);
    });
  }

  function baslat() {
    if (!core.guvenlikHazir(guvenlikDurumu())) { return; }
    durum.oturum = core.oturumOlustur(durum.protokolId, el.sure.value, el.bpm.value, el.mod.value);
    durum.adim = 0;
    durum.adimKalanMs = durum.oturum.adimlar[0].sureSn * 1000;
    durum.toplamKalanMs = durum.oturum.toplamSn * 1000;
    el.oynatici.hidden = false;
    adimGoster();
    el.oynatici.scrollIntoView({ behavior: 'smooth', block: 'start' });
    sayimlaCalistir(false);
  }

  function duraklat() {
    if (durum.sayimda) { return; }
    if (durum.calisiyor) {
      durum.calisiyor = false;
      durum.nesil++;
      window.cancelAnimationFrame(durum.raf);
      audio.durdur();
      el.duraklat.textContent = 'Dört sayımla sürdür';
      el.durum.textContent = 'Duraklatıldı — ses tamamen kesildi.';
      guvenlikGuncelle();
    } else if (durum.oturum) {
      sayimlaCalistir(true);
    }
  }

  function adimDegistir(yon) {
    if (!durum.oturum || durum.sayimda) { return; }
    var hedef = durum.adim + yon;
    if (hedef >= durum.oturum.adimlar.length) {
      tamamla();
      return;
    }
    if (hedef < 0) { return; }
    durum.adim = hedef;
    durum.adimKalanMs = durum.oturum.adimlar[hedef].sureSn * 1000;
    durum.toplamKalanMs = oturumKalaniniHesapla();
    durum.sonKare = 0;
    el.durum.textContent = 'Adım değiştirildi.';
    adimGoster();
  }

  function tamamenDurdur(mesaj) {
    durum.nesil++;
    durum.calisiyor = false;
    durum.sayimda = false;
    window.cancelAnimationFrame(durum.raf);
    audio.durdur();
    el.duraklat.disabled = false;
    el.duraklat.textContent = 'Duraklat';
    el.durum.textContent = mesaj;
    guvenlikGuncelle();
  }

  function tamamla() {
    tamamenDurdur('Akış tamamlandı. Ses kesildi; kısa grup gözlemini kaydedebilirsiniz.');
    durum.toplamKalanMs = 0;
    durum.adimKalanMs = 0;
    el.adimSure.textContent = '00:00';
    el.toplamSure.textContent = '00:00';
    el.ilerleme.style.width = '100%';
    el.baslik.textContent = 'Oturum tamamlandı';
    el.yonerge.textContent = 'Katılımcılardan kolay / orta / zor geri bildirimi alın; performans sıralaması yapmayın.';
    el.kolaylastirici.textContent = 'Gözlem notunu davranışa dayalı yazın: “dört sayımdan sonra başladı”, “mola seçti” gibi.';
  }

  function bitir() {
    tamamenDurdur('Oturum eğitmen tarafından bitirildi; ses tamamen kesildi.');
  }

  protokolKartlariniCiz();
  ozetGuncelle();
  all('[data-grup-kontrol]').forEach(function (k) { k.addEventListener('change', guvenlikGuncelle); });
  [el.sure, el.bpm, el.ses].forEach(function (g) { g.addEventListener('change', ozetGuncelle); });
  el.mod.addEventListener('change', ozetGuncelle);
  el.baslat.addEventListener('click', baslat);
  el.duraklat.addEventListener('click', duraklat);
  el.onceki.addEventListener('click', function () { adimDegistir(-1); });
  el.sonraki.addEventListener('click', function () { adimDegistir(1); });
  el.bitir.addEventListener('click', bitir);

  document.addEventListener('visibilitychange', function () {
    if (document.hidden && (durum.calisiyor || durum.sayimda)) {
      tamamenDurdur('Sekme arka plana geçtiği için otomatik duraklatıldı; ses kesildi.');
    }
  });
  window.addEventListener('pagehide', function () { tamamenDurdur('Sayfa kapandı.'); });
})();
