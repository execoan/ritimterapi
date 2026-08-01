/* =======================================================
   Metronom Stüdyosu v4 — Web Audio motoru + dikkat protokolleri
   Zamanlama: lookahead planlayıcı (25 ms tarama, 120 ms ileri)
   v4: swing/shuffle, 12/8'e kadar ölçü grupları, sayarak giriş,
   alt bölünme, vuruş başına aksan deseni, tempo trainer,
   görsel poliritim, geniş ses kiti + seçim önizlemesi,
   flaş modu, preset'ler, Spontan Tempo + Aksak Bulma testleri.
   ======================================================= */
(function () {
  'use strict';

  var zaman = window.RitimZamanlama;
  if (!zaman) { throw new Error('Ortak zamanlama çekirdeği yüklenemedi.'); }
  var metronomCekirdegi = window.MetronomCekirdegi;
  if (!metronomCekirdegi) { throw new Error('Profesyonel metronom çekirdeği yüklenemedi.'); }
  var setlistCekirdegi = window.MetronomSetlist;

  function byId(id) { return document.getElementById(id); }
  function ort(dizi) {
    if (!dizi.length) { return 0; }
    return dizi.reduce(function (a, b) { return a + b; }, 0) / dizi.length;
  }

  /* Ortak kronolojik çekirdeği eski protokol sonuç biçimine uyarlar. */
  function vuruslariEsle(taplar, hedefler, esikSn, fazUygun) {
    var kal = zaman.kalibrasyonOku();
    var sonuc = zaman.eslestir(taplar, hedefler, {
      esikSn: esikSn,
      hedefUygun: fazUygun,
      telafiMs: kal.yapildi ? kal.telafiMs : 0
    });
    var denemeler = sonuc.eslesenler.map(function (d) {
      return {
        tap: taplar[d.tapIdx], tapIdx: d.tapIdx, hedef: d.hedef, hedefIdx: d.hedefIdx,
        sapmaMs: d.sapmaMs, hamSapmaMs: d.hamSapmaMs,
        mutlakSn: Math.abs(d.sapmaMs) / 1000, eslesti: true
      };
    }).concat(sonuc.fazlaTaplar.map(function (d) {
      return {
        tap: taplar[d.tapIdx], tapIdx: d.tapIdx, hedef: d.hedef, hedefIdx: d.hedefIdx,
        sapmaMs: d.sapmaMs, hamSapmaMs: d.hamSapmaMs,
        mutlakSn: Math.abs(d.sapmaMs) / 1000, eslesti: false
      };
    })).sort(function (a, b) {
      return a.tapIdx - b.tapIdx;
    });
    return {
      denemeler: denemeler,
      eslesenler: denemeler.filter(function (d) { return d.eslesti; }),
      kacirilanHedefler: sonuc.kacirilanHedefler
    };
  }

  /* ---------- İlerleme çubuğu ----------
     Çubuk vuruş vuruş SIÇRAMAZ: tek bir doğrusal geçişle baştan sona akar.
     Sıçrayan çubuk sessiz fazda görsel metronom işlevi görür — öğrenci
     vuruşu içinden saymak yerine ekrandan yakalar ve ölçüm bozulur. */
  function ilerlemeAkit(id, sureSn, gecikmeMs) {
    var el = byId(id);
    if (!el) { return; }
    el.style.transition = 'none';
    el.style.width = '0';
    void el.offsetWidth;                       // sıfırlama uygulansın
    setTimeout(function () {
      el.style.transition = 'width ' + sureSn.toFixed(2) + 's linear';
      el.style.width = '100%';
    }, Math.max(0, gecikmeMs || 0));
  }

  /** Akışı bulunduğu yerde dondurur (iptal/bitiş). */
  function ilerlemeDondur(id) {
    var el = byId(id);
    if (!el) { return; }
    var simdiki = window.getComputedStyle(el).width;
    el.style.transition = 'none';
    el.style.width = simdiki;
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

    /* Zamanlanmış TÜM sesleri anında keser.
       Testler vuruşları ileri tarihli olarak Web Audio'ya yazar (BPM Bulma
       8 vuruşu tek seferde); yalnız zamanlayıcıyı durdurmak sesi kesmez.
       Master kazancı yeniden kurmak, çalmayı bekleyen düğümleri çıkıştan
       kopararak hepsini tek hamlede susturur. */
    sustur: function () {
      if (!this.ctx || !this.master) { return; }
      var duzey = this.master.gain.value;
      try { this.master.disconnect(); } catch (e) { /* zaten kopuk */ }
      this.master = this.ctx.createGain();
      this.master.gain.value = duzey;
      this.master.connect(this.ctx.destination);
    },

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
    vurCapraz: function (zaman, duzey, aksan) {
      var o = this.ctx.createOscillator();
      var g = this.ctx.createGain();
      o.type = 'triangle';
      o.frequency.value = aksan ? 1865 : 1568;
      g.gain.setValueAtTime((aksan ? 0.42 : 0.32) * (duzey === undefined ? 0.55 : duzey), zaman);
      g.gain.exponentialRampToValueAtTime(0.001, zaman + 0.055);
      o.connect(g);
      if (this.ctx.createStereoPanner) {
        var pan = this.ctx.createStereoPanner();
        pan.pan.value = 0.38;
        g.connect(pan).connect(this.master);
      } else {
        g.connect(this.master);
      }
      o.start(zaman); o.stop(zaman + 0.06);
    }
  };

  /* ================================================================
     ORTAK CİHAZ KALİTESİ VE KALİBRASYON
     ================================================================ */
  var zkKalibrator = null;

  function kisaTarih(iso) {
    if (!iso) { return '—'; }
    var d = new Date(iso);
    return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString('tr-TR');
  }

  function zkGuncelle(ozelMesaj) {
    var durum = zaman.kaliteDurumu(ses.ctx);
    var kal = durum.kalibrasyon;
    var rozet = byId('zkRozet');
    if (!rozet) { return durum; }
    rozet.className = 'm-zaman-rozet ' + durum.kod;
    rozet.textContent = durum.etiket;
    byId('zkTelafi').textContent = kal.yapildi
      ? (kal.telafiMs > 0 ? '+' : '') + Math.round(kal.telafiMs) + ' ms' : '—';
    byId('zkDagilim').textContent = kal.yapildi ? '±' + Math.round(kal.dagilimMs) + ' ms' : '—';
    byId('zkTarayici').textContent = ses.ctx ? durum.tarayiciGecikmesiMs + ' ms' : 'ses açılınca';
    byId('zkOrnekleme').textContent = durum.sampleRate
      ? (durum.sampleRate / 1000).toFixed(1) + ' kHz' : '—';
    byId('zkTarih').textContent = kisaTarih(kal.tarih);
    var aciklama = ozelMesaj;
    if (!aciklama) {
      if (!kal.yapildi) {
        aciklama = 'Mutlak senkronizasyon testlerinden önce 4 hazırlık ve 12 ölçüm vuruşuyla kalibre et.';
      } else if (durum.imzaDegisti) {
        aciklama = 'Ses çıkışı veya tarayıcı gecikmesi değişmiş görünüyor. Sonuçları karşılaştırmadan önce yenile.';
      } else if (kal.tur === 'elle' || kal.ornek < 8) {
        aciklama = 'Elle telafi aktif. Karşılaştırılabilir profesyonel ölçüm için 12 vuruşluk otomatik kalibrasyon önerilir.';
      } else if (kal.dagilimMs > 65) {
        aciklama = 'Vuruşlar dağınık ölçüldü. Kulaklıkla ve ekrana bakmadan yeniden kalibrasyon önerilir.';
      } else {
        aciklama = 'Ortak telafi Vuruş Tutturma, Ritim Okuma ve İçsel Ritim ölçümlerine uygulanıyor.';
      }
    }
    byId('zkAciklama').textContent = aciklama;
    return durum;
  }

  function zkDurumGoster(veri) {
    if (veri.faz === 'hazirlik') {
      byId('zkSayac').textContent = veri.kalan + ' · sonra duyduğun kliklerde vur';
    } else {
      byId('zkSayac').textContent = 'ŞİMDİ · her klikte vur';
    }
    byId('zkIlerleme').textContent = veri.ilerleme + '/' + veri.toplam;
  }

  function zkIptalEt() {
    if (zkKalibrator) { zkKalibrator.iptal(); }
    zkKalibrator = null;
    if (byId('zkSahne')) { byId('zkSahne').hidden = true; }
    if (byId('zkKalibre')) { byId('zkKalibre').disabled = false; }
    ses.sustur();
  }

  function zkBaslat() {
    digerleriniIptalEt('zk');
    ses.hazirla();
    ses.duzey(parseInt(m.el.duzey.value, 10) / 100);
    byId('zkSahne').hidden = false;
    byId('zkKalibre').disabled = true;
    byId('zkIlerleme').textContent = '0/12';
    zkKalibrator = zaman.kalibrasyonBaslat({
      ctx: ses.ctx,
      klik: function (z, aksan) { ses.vur(z, aksan, 'klik'); },
      durum: zkDurumGoster,
      tamam: function (sonuc) {
        zkKalibrator = null;
        byId('zkSahne').hidden = true;
        byId('zkKalibre').disabled = false;
        if (!sonuc.basarili) {
          zkGuncelle('Kalibrasyon tamamlanamadı: 12 klikten en az 8’inde vurmalısın. Tekrar dene.');
          return;
        }
        zkGuncelle('Kalibrasyon tamamlandı: '
          + (sonuc.telafiMs > 0 ? '+' : '') + sonuc.telafiMs + ' ms telafi · '
          + 'dağılım ±' + sonuc.dagilimMs + ' ms.');
      }
    });
  }

  function olcumIcinKalibrasyonHazir() {
    var durum = zkGuncelle();
    if (durum.kullanilabilir) { return true; }
    var panel = byId('zamanKalitePanel');
    panel.classList.remove('m-zaman-dikkat');
    void panel.offsetWidth;
    panel.classList.add('m-zaman-dikkat');
    panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    byId('zkKalibre').focus();
    zkGuncelle('Bu protokol mutlak vuruş zamanını ölçüyor. Başlamadan önce cihazı kalibre et.');
    return false;
  }

  byId('zkKalibre').addEventListener('click', zkBaslat);
  byId('zkIptal').addEventListener('click', zkIptalEt);
  byId('zkPad').addEventListener('pointerdown', function (ev) {
    ev.preventDefault();
    if (zkKalibrator && zkKalibrator.tap(ev)) {
      this.classList.add('vurdum');
      var pad = this;
      setTimeout(function () { pad.classList.remove('vurdum'); }, 80);
    }
  });
  byId('zkYenile').addEventListener('click', function () {
    ses.hazirla();
    zkGuncelle();
  });
  byId('zkSifirla').addEventListener('click', function () {
    zkIptalEt();
    zaman.kalibrasyonSifirla();
    zkGuncelle('Kalibrasyon sıfırlandı. Puanlı senkronizasyon testlerinden önce yeniden ölç.');
  });
  window.addEventListener('ritim-zamanlama-guncellendi', function () { zkGuncelle(); });
  window.addEventListener('storage', function (ev) {
    if (ev.key === zaman.AYAR_ANAHTARI) { zkGuncelle(); }
  });
  zkGuncelle();

  /* ================================================================
     A) SERBEST METRONOM
     ================================================================ */
  var m = {
    bpm: 92, calisiyor: false, zamanlayici: null,
    sonrakiZaman: 0, vurusNo: 0, olcuSayaci: 0, girisOlcuAktif: 0,
    sarkacYonu: 1, jenerasyon: 0,
    aksanDeseni: [2, 1, 1, 1],
    oturumBaslangicMs: 0, oturumBpmMin: 92, oturumBpmMax: 92,
    el: {
      bpm: byId('mBpm'), tempoAdi: byId('mTempoAdi'), surgu: byId('mBpmSurgu'),
      noktalar: byId('mNoktalar'), olcu: byId('mOlcu'), ses: byId('mSes'),
      duzey: byId('mSesDuzeyi'), altBolunme: byId('mAltBolunme'),
      gruplama: byId('mGruplama'), swing: byId('mSwing'), girisOlcu: byId('mGirisOlcu'),
      poliritim: byId('mPoliritim'), poliDuzey: byId('mPoliDuzey'),
      poliSatir: byId('mPoliSatir'), poliEtiket: byId('mPoliEtiket'),
      poliNoktalar: byId('mPoliNoktalar'),
      flasModu: byId('mFlasModu'), flas: byId('mFlas'),
      sayac: byId('mSayac'),
      durum: byId('mDurum'), durumMetin: byId('mDurumMetin'), birimSembol: byId('mBirimSembol'),
      sessizModu: byId('mSessizModu'), sessizSecim: byId('mSessizSecim'),
      sesliOlcu: byId('mSesliOlcu'), sessizOlcu: byId('mSessizOlcu'),
      trainer: byId('mTrainer'), trainerSecim: byId('mTrainerSecim'),
      trainerHedef: byId('mTrainerHedef'), trainerOlcu: byId('mTrainerOlcu'),
      trainerArtis: byId('mTrainerArtis'),
      rastgeleSus: byId('mRastgeleSus'), zamanlayiciSel: byId('mZamanlayici'),
      titresim: byId('mTitresim'), tamEkran: byId('mTamEkran'),
      sarkiTurler: byId('sarkiTurler'), sarkiListe: byId('sarkiListe'), sarkiAra: byId('sarkiAra'),
      presetler: byId('mPresetler'), presetKaydet: byId('mPresetKaydet'),
      presetForm: byId('mPresetForm'), presetAdi: byId('mPresetAdi'),
      presetOnay: byId('mPresetOnay'), presetIptal: byId('mPresetIptal'),
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

  function olcuPaydasi() {
    var secenek = m.el.olcu.options[m.el.olcu.selectedIndex];
    return parseInt(secenek && secenek.dataset.payda, 10) || 4;
  }

  function vurusSuresi() {
    return (60 / m.bpm) * (4 / olcuPaydasi());
  }

  function gruplamaSecenekleriniKur(secili, deseniUygula) {
    var adet = parseInt(m.el.olcu.value, 10) || 4;
    var secenekler = metronomCekirdegi.gruplamaSecenekleri(adet, olcuPaydasi());
    m.el.gruplama.replaceChildren();
    secenekler.forEach(function (ifade, i) {
      var option = document.createElement('option');
      option.value = ifade;
      option.textContent = ifade + (i === 0 ? ' · önerilen' : '');
      m.el.gruplama.appendChild(option);
    });
    var ozel = document.createElement('option');
    ozel.value = 'ozel';
    ozel.textContent = 'Özel aksan deseni';
    m.el.gruplama.appendChild(ozel);
    m.el.gruplama.value = secimdeVar(m.el.gruplama, secili) ? String(secili) : secenekler[0];
    if (deseniUygula && m.el.gruplama.value !== 'ozel') {
      m.aksanDeseni = metronomCekirdegi.gruplamaDeseni(adet, m.el.gruplama.value);
    }
  }

  function swingDurumunuGuncelle() {
    var alt = parseInt(m.el.altBolunme.value, 10) || 1;
    var kullanilabilir = alt === 2 || alt === 4;
    m.el.swing.disabled = !kullanilabilir;
    m.el.swing.title = kullanilabilir
      ? 'İkilinin ilk notasının vuruş içindeki payı'
      : 'Swing yalnız sekizlik ve onaltılık alt bölünmede uygulanır';
  }

  /*
   * Durum cubugu bir CANLI BOLGE (role=status). Her vuruste ve BPM surgusunun
   * her adiminda yazilinca ekran okuyucunun polite kuyrugu doluyor ve kullanici
   * baska hicbir sey duyamiyordu. Cozum: gorsel metin ANINDA guncellenir
   * (goz icin), duyuru ise ayri bir gizli bolgeye GECIKMELI ve yalniz metin
   * GERCEKTEN degistiginde yazilir.
   */
  var duyuruZamani = null;
  var sonDuyurulan = '';
  /** Kullanici isletim sisteminde hareketi azaltmayi secmis mi? */
  function hareketAzaltilmis() {
    return typeof window.matchMedia === 'function'
      && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function durumGuncelle(metin, calisiyor) {
    if (!m.el.durumMetin) { return; }
    m.el.durumMetin.textContent = metin;
    m.el.durum.classList.toggle('calisiyor', !!calisiyor);
    var duyuru = byId('mDurumDuyuru');
    if (!duyuru) { return; }
    clearTimeout(duyuruZamani);
    duyuruZamani = setTimeout(function () {
      if (metin === sonDuyurulan) { return; }
      sonDuyurulan = metin;
      duyuru.textContent = metin;
    }, 600);
  }

  var AYAR_ANAHTAR = 'ritim_metronom_ayar_v1';
  var ayarYazmaZamani = null;
  function secimdeVar(el, deger) {
    return Array.prototype.some.call(el.options, function (o) { return o.value === String(deger); });
  }
  function ayarKaydet() {
    clearTimeout(ayarYazmaZamani);
    ayarYazmaZamani = setTimeout(function () {
      try {
        localStorage.setItem(AYAR_ANAHTAR, JSON.stringify({
          bpm: m.bpm, olcu: m.el.olcu.value, ses: m.el.ses.value,
          duzey: m.el.duzey.value, alt: m.el.altBolunme.value,
          grup: m.el.gruplama.value, swing: m.el.swing.value, girisOlcu: m.el.girisOlcu.value,
          poliritim: m.el.poliritim.value, poliDuzey: m.el.poliDuzey.value,
          desen: m.aksanDeseni.slice()
        }));
      } catch (e) { /* gizli mod veya dolu depolama */ }
    }, 120);
  }
  function ayarYukle() {
    try {
      var a = JSON.parse(localStorage.getItem(AYAR_ANAHTAR) || '{}');
      if (secimdeVar(m.el.olcu, a.olcu)) { m.el.olcu.value = String(a.olcu); }
      if (secimdeVar(m.el.ses, a.ses)) { m.el.ses.value = String(a.ses); }
      if (secimdeVar(m.el.altBolunme, a.alt)) { m.el.altBolunme.value = String(a.alt); }
      if (secimdeVar(m.el.swing, a.swing)) { m.el.swing.value = String(a.swing); }
      if (secimdeVar(m.el.girisOlcu, a.girisOlcu)) { m.el.girisOlcu.value = String(a.girisOlcu); }
      if (secimdeVar(m.el.poliritim, a.poliritim)) { m.el.poliritim.value = String(a.poliritim); }
      if (Number.isFinite(Number(a.duzey))) { m.el.duzey.value = Math.max(0, Math.min(100, Number(a.duzey))); }
      if (Number.isFinite(Number(a.poliDuzey))) { m.el.poliDuzey.value = Math.max(10, Math.min(100, Number(a.poliDuzey))); }
      if (Array.isArray(a.desen)) {
        m.aksanDeseni = a.desen.slice(0, 12).map(function (v) { return [0, 1, 2].indexOf(Number(v)) >= 0 ? Number(v) : 1; });
      }
      m.yuklenenGruplama = a.grup || (Array.isArray(a.desen) ? 'ozel' : '');
      return Number.isFinite(Number(a.bpm)) ? Number(a.bpm) : 92;
    } catch (e) { return 92; }
  }

  function bpmAyarla(yeni) {
    m.bpm = Math.max(30, Math.min(240, Math.round(yeni)));
    if (m.calisiyor && m.oturumBaslangicMs) {
      m.oturumBpmMin = Math.min(m.oturumBpmMin, m.bpm);
      m.oturumBpmMax = Math.max(m.oturumBpmMax, m.bpm);
    }
    m.el.bpm.textContent = m.bpm;
    m.el.surgu.value = m.bpm;
    m.el.tempoAdi.textContent = tempoAdi(m.bpm);
    if (m.el.birimSembol) { m.el.birimSembol.textContent = olcuPaydasi() === 8 ? '♪' : '♩'; }
    if (m.calisiyor) {
      var giris = metronomCekirdegi.girisBilgisi(
        m.vurusNo, parseInt(m.el.olcu.value, 10), m.girisOlcuAktif
      );
      durumGuncelle(giris.giriste
        ? 'Sayarak giriş · ' + giris.kalanOlcu + ' ölçü kaldı · ' + m.bpm + ' BPM'
        : 'Çalıyor · ' + m.el.olcu.options[m.el.olcu.selectedIndex].text + ' · ' + m.bpm + ' BPM', true);
    }
    ayarKaydet();
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
    if (m.el.birimSembol) { m.el.birimSembol.textContent = olcuPaydasi() === 8 ? '♪' : '♩'; }
    ayarKaydet();
  }

  function poliritimNoktalariKur() {
    var capraz = parseInt(m.el.poliritim.value, 10) || 0;
    var ana = parseInt(m.el.olcu.value, 10) || 4;
    m.el.poliSatir.hidden = capraz === 0;
    m.el.poliNoktalar.replaceChildren();
    if (!capraz) { return; }
    m.el.poliEtiket.textContent = capraz + ':' + ana;
    m.el.poliNoktalar.setAttribute('aria-label', capraz + ' vuruşa karşı ' + ana + ' vuruş');
    for (var i = 0; i < capraz; i++) {
      var nokta = document.createElement('span');
      nokta.className = 'm-poli-nokta';
      nokta.title = 'Poliritim ' + (i + 1) + ' / ' + capraz;
      m.el.poliNoktalar.appendChild(nokta);
    }
  }

  m.el.noktalar.addEventListener('click', function (ev) {
    var n = ev.target.closest('.m-nokta');
    if (!n) { return; }
    var i = parseInt(n.dataset.idx, 10);
    m.aksanDeseni[i] = (m.aksanDeseni[i] + 2) % 3; /* 2→1→0→2 */
    n.className = noktaSinifi(m.aksanDeseni[i]);
    n.title = (i + 1) + '. vuruş: ' + (m.aksanDeseni[i] === 2 ? 'aksan' : m.aksanDeseni[i] === 1 ? 'normal' : 'sessiz');
    m.el.gruplama.value = 'ozel';
    ayarKaydet();
  });

  function gorselVurus(zaman, olcuIcindeki, vurgu, sesli, faz) {
    var gecikme = Math.max(0, (zaman - ses.ctx.currentTime) * 1000);
    var sure = vurusSuresi();
    var jenerasyon = m.jenerasyon;
    setTimeout(function () {
      if (!m.calisiyor || jenerasyon !== m.jenerasyon) { return; }
      m.sarkacYonu = -m.sarkacYonu;
      m.el.sarkac.style.transition = 'transform ' + sure.toFixed(3) + 's ease-in-out';
      m.el.sarkac.style.transform = 'rotate(' + (26 * m.sarkacYonu) + 'deg)';
      if (faz && faz.giriste) {
        m.el.sayac.classList.remove('m-sayac-simdi');
        m.el.sayac.textContent = 'G ' + (olcuIcindeki + 1);
        durumGuncelle('Sayarak giriş · ' + faz.kalanOlcu + ' ölçü kaldı · sonra ŞİMDİ', true);
      } else if (faz && faz.calismaBaslangici) {
        m.el.sayac.classList.add('m-sayac-simdi');
        m.el.sayac.textContent = 'ŞİMDİ';
        durumGuncelle('ŞİMDİ · ' + m.el.olcu.options[m.el.olcu.selectedIndex].text + ' · ' + m.bpm + ' BPM', true);
        setTimeout(function () { m.el.sayac.classList.remove('m-sayac-simdi'); }, Math.max(180, sure * 650));
      } else {
        m.el.sayac.classList.remove('m-sayac-simdi');
        m.el.sayac.textContent = olcuIcindeki + 1;
        if (faz && faz.girisTamamlandi) {
          durumGuncelle('Çalıyor · ' + m.el.olcu.options[m.el.olcu.selectedIndex].text
            + ' · ' + m.bpm + ' BPM', true);
        }
      }

      var noktalar = m.el.noktalar.children;
      for (var i = 0; i < noktalar.length; i++) { noktalar[i].classList.remove('aktif'); }
      if (noktalar[olcuIcindeki]) { noktalar[olcuIcindeki].classList.add('aktif'); }

      if (sesli && vurgu > 0) {
        m.el.halka.classList.add(vurgu === 2 ? 'vur-aksan' : 'vur');
        setTimeout(function () { m.el.halka.classList.remove('vur', 'vur-aksan'); }, 100);
        /*
         * FLAS MODU — yanip sonme siniri (WCAG 2.3.1).
         * Tum sahneyi kaplayan gradyan her vuruste aciliyordu; 176 BPM ustunde
         * saniyede 3'ten fazla flas demek ve cocuk grubuyla projeksiyonda en
         * riskli oge bu. Iki koruma: (a) hareket azaltma acikken HIC flaslama,
         * (b) vurus araligi 0,34 sn'nin altindaysa yalniz OLCU BASLARINDA.
         */
        if (m.el.flasModu.checked && !hareketAzaltilmis()) {
          var araSn = 60 / Math.max(1, m.bpm);
          if (araSn >= 0.34 || vurgu === 2) {
            m.el.flas.classList.add(vurgu === 2 ? 'flas-aksan' : 'flas');
            setTimeout(function () { m.el.flas.classList.remove('flas', 'flas-aksan'); }, 90);
          }
        }
        if (m.el.titresim.checked && navigator.vibrate) {
          navigator.vibrate(vurgu === 2 ? 40 : 20);
        }
      }
    }, gecikme);
  }

  function gorselPoliritimVurusu(zaman, idx) {
    var gecikme = Math.max(0, (zaman - ses.ctx.currentTime) * 1000);
    var jenerasyon = m.jenerasyon;
    setTimeout(function () {
      if (!m.calisiyor || jenerasyon !== m.jenerasyon) { return; }
      var noktalar = m.el.poliNoktalar.children;
      Array.prototype.forEach.call(noktalar, function (n) { n.classList.remove('aktif'); });
      if (noktalar[idx]) {
        noktalar[idx].classList.add('aktif');
        setTimeout(function () {
          if (noktalar[idx]) { noktalar[idx].classList.remove('aktif'); }
        }, 90);
      }
    }, gecikme);
  }

  function planla() {
    var olcuAdedi = parseInt(m.el.olcu.value, 10);
    var alt = parseInt(m.el.altBolunme.value, 10);
    var capraz = parseInt(m.el.poliritim.value, 10);

    /* Çalışma zamanlayıcısı: süre dolunca kendiliğinden dur */
    if (m.bitisMs && Date.now() >= m.bitisMs) {
      metronomDurdur('Zamanlayıcı tamamlandı · tekrar başlatmaya hazır');
      m.el.sayac.textContent = '✓';
      return;
    }

    while (m.sonrakiZaman < ses.ctx.currentTime + 0.12) {
      var spb = vurusSuresi();
      var giris = metronomCekirdegi.girisBilgisi(m.vurusNo, olcuAdedi, m.girisOlcuAktif);
      var olcuIcindeki = giris.olcuIcindeki;
      var olcuNo = giris.giriste ? -1 : Math.floor(giris.calismaVurusNo / olcuAdedi);

      /* Tempo trainer: her N ölçüde hedefe doğru yaklaş */
      if (!giris.giriste && olcuIcindeki === 0 && giris.calismaVurusNo > 0 && m.el.trainer.checked) {
        m.olcuSayaci++;
        var herOlcu = parseInt(m.el.trainerOlcu.value, 10);
        if (m.olcuSayaci % herOlcu === 0) {
          var hedef = Math.max(30, Math.min(240, parseInt(m.el.trainerHedef.value, 10) || m.bpm));
          var artis = parseInt(m.el.trainerArtis.value, 10);
          if (m.bpm < hedef) { bpmAyarla(Math.min(hedef, m.bpm + artis)); }
          else if (m.bpm > hedef) { bpmAyarla(Math.max(hedef, m.bpm - artis)); }
          spb = vurusSuresi();
        }
      }

      var sesli = true;
      if (!giris.giriste && m.el.sessizModu.checked) {
        var a = parseInt(m.el.sesliOlcu.value, 10);
        var s = parseInt(m.el.sessizOlcu.value, 10);
        sesli = (olcuNo % (a + s)) < a;
      }

      var vurgu = giris.giriste
        ? (olcuIcindeki === 0 ? 2 : 1)
        : (m.aksanDeseni[olcuIcindeki] === undefined ? 1 : m.aksanDeseni[olcuIcindeki]);

      /* 🎲 Rastgele sus (Time Guru tarzı): görsel akar, ses o vuruşta susar */
      var rastgeleSustu = false;
      var susYuzde = parseInt(m.el.rastgeleSus.value, 10);
      if (!giris.giriste && sesli && susYuzde > 0 && Math.random() * 100 < susYuzde) {
        rastgeleSustu = true;
      }

      if (sesli && !rastgeleSustu && vurgu > 0) {
        ses.vur(m.sonrakiZaman, vurgu, m.el.ses.value);
      }
      if (!giris.giriste && sesli && !rastgeleSustu && alt > 1) {
        var altOfsetler = metronomCekirdegi.altVurusOfsetleri(alt, parseFloat(m.el.swing.value));
        altOfsetler.slice(1).forEach(function (ofset) {
          ses.vurSub(m.sonrakiZaman + ofset * spb);
        });
      }
      if (!giris.giriste && sesli && capraz > 0 && olcuIcindeki === 0) {
        var olcuSuresi = spb * olcuAdedi;
        for (var c = 0; c < capraz; c++) {
          var poliZamani = m.sonrakiZaman + c * olcuSuresi / capraz;
          ses.vurCapraz(poliZamani, parseInt(m.el.poliDuzey.value, 10) / 100, c === 0);
          gorselPoliritimVurusu(poliZamani, c);
        }
      }
      gorselVurus(m.sonrakiZaman, olcuIcindeki, vurgu, sesli, {
        giriste: giris.giriste,
        kalanOlcu: giris.kalanOlcu,
        calismaBaslangici: !giris.giriste && giris.calismaVurusNo === 0 && giris.toplamVurus > 0,
        girisTamamlandi: !giris.giriste && giris.calismaVurusNo > 0 && giris.toplamVurus > 0
      });
      m.sonrakiZaman += spb;
      m.vurusNo++;
    }
  }

  var ekranKilidi = null;
  function ekranAcikTut() {
    if (!('wakeLock' in navigator) || ekranKilidi) { return; }
    navigator.wakeLock.request('screen').then(function (kilit) {
      ekranKilidi = kilit;
      kilit.addEventListener('release', function () { ekranKilidi = null; });
    }).catch(function () { /* tarayıcı/izin desteklemiyor; metronom yine çalışır */ });
  }
  function ekranKilidiniBirak() {
    if (!ekranKilidi) { return; }
    ekranKilidi.release().catch(function () {}).then(function () { ekranKilidi = null; });
  }

  function metronomBaslat(secenek) {
    secenek = secenek || {};
    ses.hazirla();
    ses.duzey(parseInt(m.el.duzey.value, 10) / 100);
    m.vurusNo = 0;
    m.olcuSayaci = 0;
    m.girisOlcuAktif = secenek.girisYok
      ? 0
      : Math.max(0, parseInt(m.el.girisOlcu.value, 10) || 0);
    m.jenerasyon++;
    var dk = secenek.zamanlayiciYok ? 0 : parseInt(m.el.zamanlayiciSel.value, 10);
    var girisSuresiMs = m.girisOlcuAktif * (parseInt(m.el.olcu.value, 10) || 4) * vurusSuresi() * 1000;
    m.bitisMs = dk > 0 ? Date.now() + girisSuresiMs + dk * 60000 : 0;
    m.calisiyor = true;
    if (!secenek.setlist) {
      m.oturumBaslangicMs = Date.now();
      m.oturumBpmMin = m.bpm;
      m.oturumBpmMax = m.bpm;
    }
    m.el.girisOlcu.disabled = true;
    m.sonrakiZaman = ses.ctx.currentTime + 0.08;
    planla();
    m.zamanlayici = setInterval(planla, 25);
    m.el.baslat.textContent = '⏸ Durdur';
    m.el.baslat.classList.add('calisiyor');
    m.el.baslat.setAttribute('aria-pressed', 'true');
    durumGuncelle(m.girisOlcuAktif
      ? 'Sayarak giriş hazırlanıyor · ' + m.girisOlcuAktif + ' ölçü'
      : 'Çalıyor · ' + m.el.olcu.options[m.el.olcu.selectedIndex].text + ' · ' + m.bpm + ' BPM', true);
    ekranAcikTut();
  }

  function metronomDurdur(durumMetni, secenek) {
    secenek = secenek || {};
    if (setAkis && setAkis.aktif && !secenek.setlistKor) {
      setlistBitir('Setlist durduruldu');
      return;
    }
    var serbestBaslangic = m.oturumBaslangicMs;
    var serbestSure = serbestBaslangic ? Math.round((Date.now() - serbestBaslangic) / 1000) : 0;
    clearInterval(m.zamanlayici);
    m.calisiyor = false;
    m.girisOlcuAktif = 0;
    m.jenerasyon++;
    ses.sustur();
    m.el.baslat.textContent = '▶ Başlat';
    m.el.baslat.classList.remove('calisiyor');
    m.el.baslat.setAttribute('aria-pressed', 'false');
    m.el.sarkac.style.transition = 'transform .4s ease';
    m.el.sarkac.style.transform = 'rotate(0deg)';
    m.el.sayac.textContent = '–';
    m.el.sayac.classList.remove('m-sayac-simdi');
    m.el.girisOlcu.disabled = false;
    Array.prototype.forEach.call(m.el.noktalar.children, function (n) { n.classList.remove('aktif'); });
    Array.prototype.forEach.call(m.el.poliNoktalar.children, function (n) { n.classList.remove('aktif'); });
    durumGuncelle(durumMetni || 'Hazır · son ayarlar bu cihazda saklandı', false);
    ekranKilidiniBirak();
    m.oturumBaslangicMs = 0;
    if (!secenek.kaydetme && !secenek.setlistKor && serbestSure >= 10) {
      calismaKaydet({
        tur: 'serbest',
        baslik: 'Serbest metronom',
        sureSn: serbestSure,
        bpmMin: m.oturumBpmMin,
        bpmMax: m.oturumBpmMax,
        detay: {
          olcu: parseInt(m.el.olcu.value, 10) || 4,
          payda: olcuPaydasi(),
          alt: parseInt(m.el.altBolunme.value, 10) || 1,
          poliritim: parseInt(m.el.poliritim.value, 10) || 0
        }
      });
    }
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible' && m.calisiyor) { ekranAcikTut(); }
  });

  m.el.baslat.addEventListener('click', function () {
    if (setAkis && setAkis.aktif) { setlistDuraklatDevam(); }
    else if (m.calisiyor) { metronomDurdur(); }
    else { metronomBaslat(); }
  });
  m.el.surgu.addEventListener('input', function () { bpmAyarla(this.value); });
  document.querySelectorAll('[data-bpm-degistir]').forEach(function (b) {
    b.addEventListener('click', function () {
      bpmAyarla(m.bpm + parseInt(b.dataset.bpmDegistir, 10));
    });
  });
  m.el.olcu.addEventListener('change', function () {
    gruplamaSecenekleriniKur('', true);
    noktalariKur();
    poliritimNoktalariKur();
    bpmAyarla(m.bpm);
  });
  m.el.duzey.addEventListener('input', function () {
    ses.duzey(parseInt(this.value, 10) / 100);
    ayarKaydet();
  });
  m.el.gruplama.addEventListener('change', function () {
    if (this.value !== 'ozel') {
      m.aksanDeseni = metronomCekirdegi.gruplamaDeseni(parseInt(m.el.olcu.value, 10), this.value);
      noktalariKur();
    }
    ayarKaydet();
  });
  m.el.altBolunme.addEventListener('change', function () {
    swingDurumunuGuncelle();
    ayarKaydet();
  });
  m.el.swing.addEventListener('change', ayarKaydet);
  m.el.girisOlcu.addEventListener('change', ayarKaydet);
  m.el.poliritim.addEventListener('change', function () {
    poliritimNoktalariKur();
    ayarKaydet();
  });
  m.el.poliDuzey.addEventListener('input', ayarKaydet);
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
    ayarKaydet();
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
    try {
      var liste = JSON.parse(localStorage.getItem(PRESET_ANAHTAR) || '[]');
      return Array.isArray(liste) ? liste.slice(0, 12) : [];
    }
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
      var kume = document.createElement('span');
      kume.className = 'm-preset-kume';
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'm-preset-chip';
      var ad = document.createElement('b');
      ad.textContent = String(p.ad || 'Preset').slice(0, 18);
      var bilgi = document.createTextNode(' ' + Math.max(30, Math.min(240, parseInt(p.bpm, 10) || 92)) + ' ');
      var sil = document.createElement('button');
      sil.type = 'button';
      sil.className = 'm-preset-sil';
      sil.title = 'Preseti sil';
      sil.setAttribute('aria-label', String(p.ad || 'Preset').slice(0, 18) + ' presetini sil');
      sil.textContent = '×';
      chip.appendChild(ad);
      chip.appendChild(bilgi);
      sil.addEventListener('click', function () {
        liste.splice(i, 1);
        presetYaz(liste);
        presetCiz();
      });
      chip.addEventListener('click', function () {
        if (secimdeVar(m.el.olcu, p.olcu)) { m.el.olcu.value = String(p.olcu); }
        gruplamaSecenekleriniKur(p.grup || 'ozel', false);
        if (secimdeVar(m.el.ses, p.ses)) { m.el.ses.value = String(p.ses); }
        if (secimdeVar(m.el.altBolunme, p.alt || '1')) { m.el.altBolunme.value = String(p.alt || '1'); }
        if (secimdeVar(m.el.swing, p.swing || '50')) { m.el.swing.value = String(p.swing || '50'); }
        if (secimdeVar(m.el.girisOlcu, p.girisOlcu || '0')) { m.el.girisOlcu.value = String(p.girisOlcu || '0'); }
        if (secimdeVar(m.el.poliritim, p.poliritim || '0')) { m.el.poliritim.value = String(p.poliritim || '0'); }
        if (Number.isFinite(Number(p.poliDuzey))) {
          m.el.poliDuzey.value = Math.max(10, Math.min(100, Number(p.poliDuzey)));
        }
        m.aksanDeseni = Array.isArray(p.desen)
          ? p.desen.slice(0, 12)
          : metronomCekirdegi.gruplamaDeseni(parseInt(m.el.olcu.value, 10), m.el.gruplama.value);
        noktalariKur();
        poliritimNoktalariKur();
        swingDurumunuGuncelle();
        bpmAyarla(parseInt(p.bpm, 10) || 92);
      });
      kume.appendChild(chip);
      kume.appendChild(sil);
      m.el.presetler.appendChild(kume);
    });
  }
  function presetFormKapat() {
    m.el.presetForm.hidden = true;
    m.el.presetKaydet.hidden = false;
  }
  function presetOlustur() {
    var ad = m.el.presetAdi.value.trim();
    if (!ad) {
      m.el.presetAdi.focus();
      return;
    }
    var liste = presetOku();
    liste.push({ ad: ad.slice(0, 18), bpm: m.bpm, olcu: m.el.olcu.value, ses: m.el.ses.value,
                 alt: m.el.altBolunme.value, poliritim: m.el.poliritim.value,
                 grup: m.el.gruplama.value, swing: m.el.swing.value, girisOlcu: m.el.girisOlcu.value,
                 poliDuzey: m.el.poliDuzey.value, desen: m.aksanDeseni.slice() });
    presetYaz(liste.slice(0, 12));
    presetCiz();
    presetFormKapat();
  }
  m.el.presetKaydet.addEventListener('click', function () {
    m.el.presetKaydet.hidden = true;
    m.el.presetForm.hidden = false;
    m.el.presetAdi.value = tempoAdi(m.bpm) + ' ' + m.bpm;
    m.el.presetAdi.focus();
    m.el.presetAdi.select();
  });
  m.el.presetOnay.addEventListener('click', presetOlustur);
  m.el.presetIptal.addEventListener('click', presetFormKapat);
  m.el.presetAdi.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') { ev.preventDefault(); presetOlustur(); }
    else if (ev.key === 'Escape') { presetFormKapat(); m.el.presetKaydet.focus(); }
  });

  /* ================================================================
     ÇALIŞMA MERKEZİ — hesaba bağlı setlist + otomatik günlük
     ================================================================ */
  var cmVeri = window.METRONOM_CALISMA_VERI || null;
  var setler = [];
  var duzenlenenSet = null;
  var setAkis = {
    aktif: false, idx: 0, bekliyor: false, duraklatildi: false,
    segmentBaslangicMs: 0, adimCalisilanMs: 0, toplamCalisilanMs: 0,
    tik: null, set: null, tamamlanan: 0
  };

  function cmDurum(metin, tur) {
    var el = byId('mCmKayitDurum');
    if (!el) { return; }
    el.textContent = metin;
    el.className = 'm-cm-kayit-durum' + (tur ? ' ' + tur : '');
  }

  function apiGonder(veri) {
    if (!cmVeri || !cmVeri.api) { return Promise.reject(new Error('Çalışma günlüğü bağlantısı yok.')); }
    veri.csrfToken = cmVeri.csrfToken;
    return fetch(cmVeri.api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(veri)
    }).then(function (yanit) {
      return yanit.json().catch(function () { return {}; }).then(function (json) {
        if (!yanit.ok || json.ok === false && !json.atlandi) {
          throw new Error(json.error || 'İşlem tamamlanamadı.');
        }
        return json;
      });
    });
  }

  function calismaKaydet(veri) {
    cmDurum('Günlüğe yazılıyor…');
    return apiGonder(Object.assign({ islem: 'calisma_kaydet' }, veri)).then(function (sonuc) {
      if (sonuc.ozet) { gunlukCiz(sonuc.ozet); }
      cmDurum(sonuc.atlandi ? 'Kısa deneme kaydedilmedi' : 'Çalışma günlüğe yazıldı', 'basarili');
      return sonuc;
    }).catch(function (hata) {
      cmDurum(hata.message, 'hata');
      return null;
    });
  }

  function dakikaYaz(saniye) {
    var dk = Math.round((Number(saniye) || 0) / 60);
    return dk < 1 && saniye > 0 ? '<1 dk' : dk + ' dk';
  }

  function gunlukCiz(ozet) {
    if (!ozet || !byId('mGunlukBugun')) { return; }
    var hedef = ozet.hedef || { gunlukDk: 20, haftalikGun: 5 };
    var bugunDk = (Number(ozet.bugunSn) || 0) / 60;
    var oran = Math.min(100, Math.round(100 * bugunDk / Math.max(1, hedef.gunlukDk)));
    byId('mGunlukBugun').textContent = dakikaYaz(ozet.bugunSn);
    byId('mGunlukHalka').style.setProperty('--oran', oran + '%');
    byId('mGunlukHedefMetin').textContent = hedef.gunlukDk + ' dk hedef · %' + oran;
    byId('mGunlukHedefCubuk').style.width = oran + '%';
    byId('mGunlukHaftaMetin').textContent =
      'Son 7 gün ' + (ozet.haftaGunSayisi || 0) + ' / ' + hedef.haftalikGun + ' çalışma günü';
    byId('mGunlukSeri').textContent = (ozet.seri || 0) + ' gün seri';
    byId('mHedefDk').value = hedef.gunlukDk;
    byId('mHedefGun').value = hedef.haftalikGun;

    var grafik = byId('mGunlukGrafik');
    grafik.replaceChildren();
    var gunler = Array.isArray(ozet.gunler) ? ozet.gunler : [];
    var enYuksek = Math.max.apply(null, [60].concat(gunler.map(function (g) { return Number(g.sureSn) || 0; })));
    var gunAdlari = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];
    gunler.forEach(function (g) {
      var parca = String(g.tarih || '').split('-').map(Number);
      var tarih = new Date(parca[0], parca[1] - 1, parca[2]);
      var kutu = document.createElement('div');
      kutu.className = 'm-gunluk-gun';
      kutu.title = g.tarih + ' · ' + dakikaYaz(g.sureSn);
      var cubuk = document.createElement('i');
      cubuk.style.height = Math.max(3, Math.round(100 * (Number(g.sureSn) || 0) / enYuksek)) + '%';
      var etiket = document.createElement('small');
      etiket.textContent = gunAdlari[tarih.getDay()];
      kutu.appendChild(cubuk); kutu.appendChild(etiket); grafik.appendChild(kutu);
    });

    var son = byId('mSonCalismalar');
    son.replaceChildren();
    var kayitlar = Array.isArray(ozet.sonKayitlar) ? ozet.sonKayitlar : [];
    if (!kayitlar.length) {
      var bos = document.createElement('span');
      bos.className = 'alan-ipucu';
      bos.textContent = 'İlk çalışmandan sonra kayıtlar burada görünecek.';
      son.appendChild(bos);
    }
    kayitlar.forEach(function (k) {
      var satir = document.createElement('div');
      satir.className = 'm-son-calisma';
      var baslik = document.createElement('strong');
      baslik.textContent = k.baslik || (k.tur === 'setlist' ? 'Setlist çalışması' : 'Serbest metronom');
      var sure = document.createElement('b');
      sure.textContent = dakikaYaz(k.sure_sn);
      var alt = document.createElement('small');
      var bpm = k.bpm_min ? ' · ' + k.bpm_min + (k.bpm_max && k.bpm_max !== k.bpm_min ? '–' + k.bpm_max : '') + ' BPM' : '';
      alt.textContent = String(k.created_at || '').slice(0, 16) + bpm;
      satir.appendChild(baslik); satir.appendChild(sure); satir.appendChild(alt); son.appendChild(satir);
    });
  }

  function geciciSet() {
    return setlistCekirdegi.setNormalle({
      id: duzenlenenSet ? duzenlenenSet.id : 0,
      ad: byId('mSetAd').value,
      aciklama: byId('mSetAciklama').value,
      adimlar: duzenlenenSet ? duzenlenenSet.adimlar : []
    });
  }

  function setSecenekleriniCiz(seciliId) {
    var sec = byId('mSetSec');
    sec.replaceChildren();
    var bos = document.createElement('option');
    bos.value = ''; bos.textContent = '— Yeni setlist —'; sec.appendChild(bos);
    setler.forEach(function (s) {
      var o = document.createElement('option');
      o.value = s.id; o.textContent = s.ad + ' · ' + s.adimlar.length + ' adım';
      sec.appendChild(o);
    });
    sec.value = seciliId ? String(seciliId) : '';
  }

  function setAdimlariniCiz() {
    var kok = byId('mSetAdimlar');
    kok.replaceChildren();
    var adimlar = duzenlenenSet ? duzenlenenSet.adimlar : [];
    byId('mSetBos').hidden = adimlar.length > 0;
    byId('mSetToplam').textContent = adimlar.length + ' adım · ' + setlistCekirdegi.sureYaz(setlistCekirdegi.toplamSure(adimlar));
    adimlar.forEach(function (adim, idx) {
      var satir = document.createElement('div');
      satir.className = 'm-set-adim' + (setAkis.aktif && setAkis.idx === idx ? ' aktif' : '');
      satir.dataset.idx = idx;
      satir.innerHTML =
        '<span class="m-set-sira">' + (idx + 1) + '</span>' +
        '<label>Adım adı<input type="text" class="girdi" data-alan="baslik" maxlength="60"></label>' +
        '<label>BPM<input type="number" class="girdi" data-alan="bpm" min="30" max="240"></label>' +
        '<label>Ölçü<select class="secim" data-alan="olcu">' +
          '<option>2/4</option><option>3/4</option><option>4/4</option><option>5/4</option>' +
          '<option>6/8</option><option>7/8</option><option>8/8</option><option>9/8</option>' +
          '<option>10/8</option><option>11/8</option><option>12/8</option></select></label>' +
        '<label>Süre (dk)<input type="number" class="girdi" data-alan="sure" min=".25" max="120" step=".25"></label>' +
        '<label>Geçiş<select class="secim" data-alan="gecis"><option value="otomatik">Otomatik</option><option value="bekle">Bekle</option></select></label>' +
        '<label>Alt bölünme<select class="secim" data-alan="alt"><option value="1">Kapalı</option><option value="2">Sekizlik</option><option value="3">Üçleme</option><option value="4">Onaltılık</option></select></label>' +
        '<label>Poliritim<select class="secim" data-alan="poliritim"><option value="0">Kapalı</option>' +
          Array.from({ length: 11 }, function (_, i) { return '<option value="' + (i + 2) + '">' + (i + 2) + '</option>'; }).join('') +
          '</select></label>' +
        '<div class="m-set-adim-islem"><button type="button" class="m-mini-btn" data-islem="yukari" title="Yukarı taşı">↑</button>' +
          '<button type="button" class="m-mini-btn" data-islem="asagi" title="Aşağı taşı">↓</button>' +
          '<button type="button" class="m-mini-btn" data-islem="sil" title="Adımı sil">×</button></div>';
      satir.querySelector('[data-alan="baslik"]').value = adim.baslik;
      satir.querySelector('[data-alan="bpm"]').value = adim.bpm;
      satir.querySelector('[data-alan="olcu"]').value = adim.olcu + '/' + adim.payda;
      satir.querySelector('[data-alan="sure"]').value = Math.round(adim.sureSn / 15) / 4;
      satir.querySelector('[data-alan="gecis"]').value = adim.gecis;
      satir.querySelector('[data-alan="alt"]').value = adim.alt;
      satir.querySelector('[data-alan="poliritim"]').value = adim.poliritim;
      kok.appendChild(satir);
    });
  }

  function setYukle(set) {
    duzenlenenSet = setlistCekirdegi.setNormalle(set || {});
    byId('mSetAd').value = duzenlenenSet.id ? duzenlenenSet.ad : '';
    byId('mSetAciklama').value = duzenlenenSet.aciklama;
    byId('mSetSil').disabled = !duzenlenenSet.id;
    setSecenekleriniCiz(duzenlenenSet.id);
    setAdimlariniCiz();
  }

  function mevcutAyariAdimYap() {
    var sira = duzenlenenSet ? duzenlenenSet.adimlar.length : 0;
    return setlistCekirdegi.adimNormalle({
      baslik: 'Bölüm ' + (sira + 1) + ' · ' + m.bpm + ' BPM',
      bpm: m.bpm,
      olcu: parseInt(m.el.olcu.value, 10) || 4,
      payda: olcuPaydasi(),
      gruplama: m.el.gruplama.value,
      alt: parseInt(m.el.altBolunme.value, 10) || 1,
      swing: parseFloat(m.el.swing.value) || 50,
      poliritim: parseInt(m.el.poliritim.value, 10) || 0,
      poliDuzey: parseInt(m.el.poliDuzey.value, 10) || 55,
      girisOlcu: parseInt(m.el.girisOlcu.value, 10) || 0,
      sureSn: 300,
      gecis: 'otomatik',
      desen: m.aksanDeseni.slice()
    }, sira);
  }

  function adimAyarlariniUygula(adim) {
    var hedefOlcu = String(adim.olcu);
    Array.prototype.some.call(m.el.olcu.options, function (o) {
      if (o.value === hedefOlcu && (parseInt(o.dataset.payda, 10) || 4) === adim.payda) {
        m.el.olcu.value = o.value; return true;
      }
      return false;
    });
    gruplamaSecenekleriniKur(adim.gruplama, false);
    if (secimdeVar(m.el.altBolunme, adim.alt)) { m.el.altBolunme.value = String(adim.alt); }
    if (secimdeVar(m.el.swing, adim.swing)) { m.el.swing.value = String(adim.swing); }
    if (secimdeVar(m.el.poliritim, adim.poliritim)) { m.el.poliritim.value = String(adim.poliritim); }
    if (secimdeVar(m.el.girisOlcu, adim.girisOlcu)) { m.el.girisOlcu.value = String(adim.girisOlcu); }
    m.el.poliDuzey.value = adim.poliDuzey;
    m.aksanDeseni = adim.desen.slice();
    noktalariKur();
    poliritimNoktalariKur();
    swingDurumunuGuncelle();
    bpmAyarla(adim.bpm);
  }

  function setlistSegmentKapat() {
    if (!setAkis.segmentBaslangicMs) { return; }
    var sure = Date.now() - setAkis.segmentBaslangicMs;
    setAkis.adimCalisilanMs += sure;
    setAkis.toplamCalisilanMs += sure;
    setAkis.segmentBaslangicMs = 0;
  }

  function setlistKalanMs() {
    if (!setAkis.set || !setAkis.set.adimlar[setAkis.idx]) { return 0; }
    var gecen = setAkis.adimCalisilanMs
      + (setAkis.segmentBaslangicMs ? Date.now() - setAkis.segmentBaslangicMs : 0);
    return Math.max(0, setAkis.set.adimlar[setAkis.idx].sureSn * 1000 - gecen);
  }

  function setlistOynaticiyiCiz() {
    if (!setAkis.aktif || !setAkis.set) { return; }
    var adim = setAkis.set.adimlar[setAkis.idx];
    var kalan = setlistKalanMs();
    var oran = Math.min(100, 100 * (1 - kalan / Math.max(1, adim.sureSn * 1000)));
    byId('mSetOynaticiBaslik').textContent = (setAkis.idx + 1) + '/' + setAkis.set.adimlar.length + ' · ' + adim.baslik;
    byId('mSetOynaticiAlt').textContent = adim.bpm + ' BPM · ' + adim.olcu + '/' + adim.payda +
      (adim.poliritim ? ' · poliritim ' + adim.poliritim + ':' + adim.olcu : '') +
      (setAkis.bekliyor ? ' · sonraki adım hazır' : setAkis.duraklatildi ? ' · duraklatıldı' : '');
    byId('mSetSayac').textContent = setlistCekirdegi.sureYaz(Math.ceil(kalan / 1000));
    byId('mSetIlerleme').style.width = oran + '%';
    byId('mSetBaslat').textContent = setAkis.bekliyor
      ? '▶ Sonraki Adım'
      : (m.calisiyor ? '⏸ Duraklat' : '▶ Devam');
    setAdimlariniCiz();
  }

  function setlistAdimBaslat(idx, devam) {
    if (!setAkis.set || !setAkis.set.adimlar[idx]) { return; }
    setAkis.idx = idx;
    setAkis.bekliyor = false;
    setAkis.duraklatildi = false;
    if (!devam) {
      setAkis.adimCalisilanMs = 0;
      adimAyarlariniUygula(setAkis.set.adimlar[idx]);
    }
    setAkis.segmentBaslangicMs = Date.now();
    metronomBaslat({ setlist: true, zamanlayiciYok: true, girisYok: !!devam });
    setlistOynaticiyiCiz();
  }

  function setlistAdimTamam() {
    var adim = setAkis.set.adimlar[setAkis.idx];
    setlistSegmentKapat();
    metronomDurdur('Setlist adımı tamamlandı', { setlistKor: true, kaydetme: true });
    setAkis.tamamlanan = Math.max(setAkis.tamamlanan, setAkis.idx + 1);
    if (setAkis.idx >= setAkis.set.adimlar.length - 1) {
      setlistBitir('Setlist tamamlandı');
      return;
    }
    if (adim.gecis === 'bekle') {
      setAkis.bekliyor = true;
      setAkis.duraklatildi = true;
      zilCal();
      setlistOynaticiyiCiz();
      return;
    }
    setlistAdimBaslat(setAkis.idx + 1, false);
  }

  function setlistTik() {
    if (!setAkis.aktif) { return; }
    if (m.calisiyor && setlistKalanMs() <= 0) { setlistAdimTamam(); return; }
    setlistOynaticiyiCiz();
  }

  function setlistBaslat() {
    if (!duzenlenenSet || !duzenlenenSet.adimlar.length) {
      cmDurum('Başlatmak için en az bir adım ekleyin.', 'hata'); return;
    }
    if (m.calisiyor) { metronomDurdur('Setlist hazırlanıyor'); }
    setAkis.aktif = true;
    setAkis.idx = 0;
    setAkis.bekliyor = false;
    setAkis.duraklatildi = false;
    setAkis.segmentBaslangicMs = 0;
    setAkis.adimCalisilanMs = 0;
    setAkis.toplamCalisilanMs = 0;
    setAkis.tamamlanan = 0;
    setAkis.set = setlistCekirdegi.setNormalle(duzenlenenSet);
    byId('mSetOynatici').hidden = false;
    clearInterval(setAkis.tik);
    setAkis.tik = setInterval(setlistTik, 250);
    cmDurum('Setlist çalıyor', 'basarili');
    setlistAdimBaslat(0, false);
  }

  function setlistDuraklatDevam() {
    if (!setAkis.aktif) { setlistBaslat(); return; }
    if (setAkis.bekliyor) {
      setlistAdimBaslat(setAkis.idx + 1, false);
      return;
    }
    if (m.calisiyor) {
      setlistSegmentKapat();
      metronomDurdur('Setlist duraklatıldı', { setlistKor: true, kaydetme: true });
      setAkis.duraklatildi = true;
      setlistOynaticiyiCiz();
    } else {
      setlistAdimBaslat(setAkis.idx, true);
    }
  }

  function setlistAtla(yeniIdx) {
    if (!setAkis.aktif || !setAkis.set || yeniIdx < 0 || yeniIdx >= setAkis.set.adimlar.length) { return; }
    if (m.calisiyor) {
      setlistSegmentKapat();
      metronomDurdur('Setlist adımı değiştiriliyor', { setlistKor: true, kaydetme: true });
    }
    setlistAdimBaslat(yeniIdx, false);
  }

  function setlistBitir(mesaj) {
    if (!setAkis || !setAkis.aktif) { return; }
    if (m.calisiyor) {
      setlistSegmentKapat();
      metronomDurdur(mesaj || 'Setlist bitti', { setlistKor: true, kaydetme: true });
    }
    clearInterval(setAkis.tik);
    var sureSn = Math.round(setAkis.toplamCalisilanMs / 1000);
    var kayitSet = setAkis.set;
    var tamamlanan = setAkis.tamamlanan;
    setAkis.aktif = false;
    setAkis.bekliyor = false;
    setAkis.duraklatildi = false;
    byId('mSetOynatici').hidden = true;
    setAdimlariniCiz();
    if (sureSn >= 10) {
      var bpmler = kayitSet.adimlar.map(function (a) { return a.bpm; });
      calismaKaydet({
        tur: 'setlist',
        setId: kayitSet.id || null,
        baslik: kayitSet.ad || 'Setlist çalışması',
        sureSn: sureSn,
        bpmMin: Math.min.apply(null, bpmler),
        bpmMax: Math.max.apply(null, bpmler),
        detay: {
          adimSayisi: kayitSet.adimlar.length,
          tamamlananAdim: tamamlanan,
          planlananSn: setlistCekirdegi.toplamSure(kayitSet.adimlar)
        }
      });
    } else {
      cmDurum(mesaj || 'Setlist kapatıldı');
    }
  }

  if (cmVeri && setlistCekirdegi && byId('mCalismaMerkezi')) {
    setler = (Array.isArray(cmVeri.setler) ? cmVeri.setler : []).map(setlistCekirdegi.setNormalle);
    setSecenekleriniCiz(0);
    setYukle(null);
    gunlukCiz(cmVeri.ozet);

    byId('mSetYeni').addEventListener('click', function () {
      if (setAkis.aktif) { setlistBitir('Yeni setlist açıldı'); }
      setYukle(null);
      byId('mSetAd').focus();
    });
    byId('mSetSec').addEventListener('change', function () {
      var id = parseInt(this.value, 10) || 0;
      setYukle(setler.find(function (s) { return s.id === id; }) || null);
    });
    byId('mSetAdimEkle').addEventListener('click', function () {
      if (!duzenlenenSet) { setYukle(null); }
      duzenlenenSet.adimlar.push(mevcutAyariAdimYap());
      setAdimlariniCiz();
    });
    byId('mSetAdimlar').addEventListener('input', function (ev) {
      var satir = ev.target.closest('.m-set-adim');
      if (!satir || !duzenlenenSet) { return; }
      var idx = parseInt(satir.dataset.idx, 10);
      var alan = ev.target.dataset.alan;
      var a = duzenlenenSet.adimlar[idx];
      if (alan === 'baslik') { a.baslik = ev.target.value.slice(0, 60); }
      else if (alan === 'bpm') { a.bpm = Math.max(30, Math.min(240, parseInt(ev.target.value, 10) || 92)); }
      else if (alan === 'sure') { a.sureSn = Math.max(15, Math.min(7200, Math.round((parseFloat(ev.target.value) || .25) * 60))); }
      else if (alan === 'gecis') { a.gecis = ev.target.value === 'bekle' ? 'bekle' : 'otomatik'; }
      else if (alan === 'alt') { a.alt = parseInt(ev.target.value, 10) || 1; }
      else if (alan === 'poliritim') { a.poliritim = parseInt(ev.target.value, 10) || 0; }
      else if (alan === 'olcu') {
        var olcu = ev.target.value.split('/');
        a.olcu = parseInt(olcu[0], 10); a.payda = parseInt(olcu[1], 10);
        a.gruplama = 'ozel';
        a.desen = metronomCekirdegi.gruplamaDeseni(a.olcu, '');
      }
      byId('mSetToplam').textContent = duzenlenenSet.adimlar.length + ' adım · ' +
        setlistCekirdegi.sureYaz(setlistCekirdegi.toplamSure(duzenlenenSet.adimlar));
    });
    byId('mSetAdimlar').addEventListener('click', function (ev) {
      var dugme = ev.target.closest('[data-islem]');
      var satir = ev.target.closest('.m-set-adim');
      if (!dugme || !satir || !duzenlenenSet) { return; }
      var idx = parseInt(satir.dataset.idx, 10);
      if (dugme.dataset.islem === 'sil') { duzenlenenSet.adimlar.splice(idx, 1); }
      else if (dugme.dataset.islem === 'yukari') { duzenlenenSet.adimlar = setlistCekirdegi.adimTasi(duzenlenenSet.adimlar, idx, idx - 1); }
      else if (dugme.dataset.islem === 'asagi') { duzenlenenSet.adimlar = setlistCekirdegi.adimTasi(duzenlenenSet.adimlar, idx, idx + 1); }
      setAdimlariniCiz();
    });
    byId('mSetKaydet').addEventListener('click', function () {
      var set = geciciSet();
      if (!set.ad) { cmDurum('Setlist adı yazın.', 'hata'); byId('mSetAd').focus(); return; }
      if (!set.adimlar.length) { cmDurum('En az bir adım ekleyin.', 'hata'); return; }
      cmDurum('Setlist kaydediliyor…');
      apiGonder({ islem: 'set_kaydet', id: set.id, ad: set.ad, aciklama: set.aciklama, adimlar: set.adimlar })
        .then(function (sonuc) {
          var kayit = setlistCekirdegi.setNormalle(sonuc.set);
          var idx = setler.findIndex(function (s) { return s.id === kayit.id; });
          if (idx >= 0) { setler[idx] = kayit; } else { setler.unshift(kayit); }
          setYukle(kayit);
          cmDurum('Setlist kaydedildi', 'basarili');
        }).catch(function (hata) { cmDurum(hata.message, 'hata'); });
    });
    byId('mSetCalistir').addEventListener('click', setlistBaslat);
    byId('mSetSil').addEventListener('click', function () {
      if (!duzenlenenSet || !duzenlenenSet.id || !window.confirm('Bu setlist silinsin mi? Geçmiş çalışma kayıtları korunur.')) { return; }
      var id = duzenlenenSet.id;
      apiGonder({ islem: 'set_sil', id: id }).then(function () {
        setler = setler.filter(function (s) { return s.id !== id; });
        setYukle(null);
        cmDurum('Setlist silindi', 'basarili');
      }).catch(function (hata) { cmDurum(hata.message, 'hata'); });
    });
    byId('mSetBaslat').addEventListener('click', setlistDuraklatDevam);
    byId('mSetBitir').addEventListener('click', function () { setlistBitir('Setlist bitirildi'); });
    byId('mSetOnceki').addEventListener('click', function () { setlistAtla(setAkis.idx - 1); });
    byId('mSetSonraki').addEventListener('click', function () { setlistAtla(setAkis.idx + 1); });
    byId('mHedefKaydet').addEventListener('click', function () {
      apiGonder({
        islem: 'hedef_kaydet',
        gunlukDk: parseInt(byId('mHedefDk').value, 10) || 20,
        haftalikGun: parseInt(byId('mHedefGun').value, 10) || 5
      }).then(function (sonuc) {
        gunlukCiz(sonuc.ozet);
        cmDurum('Çalışma hedefi kaydedildi', 'basarili');
      }).catch(function (hata) { cmDurum(hata.message, 'hata'); });
    });
  }

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
        if (s.olcu) {
          m.el.olcu.value = String(s.olcu);
          gruplamaSecenekleriniKur('', true);
          noktalariKur();
          poliritimNoktalariKur();
        }
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

  var ilkBpm = ayarYukle();
  gruplamaSecenekleriniKur(m.yuklenenGruplama || '', m.yuklenenGruplama !== 'ozel');
  noktalariKur();
  poliritimNoktalariKur();
  swingDurumunuGuncelle();
  bpmAyarla(ilkBpm);
  presetCiz();

  /* ================================================================
     Sekmeler (+ oturumdan derin bağlantı: ?protokol=…)
     ================================================================ */
  /* Sekme değişince çalışan testi durdur: aksi hâlde önceki protokolün
     zamanlanmış sesleri ve sayaçları arka planda sürer. */
  var sekmeler = Array.prototype.slice.call(document.querySelectorAll('.m-sekme'));

  /* Gezici odak (roving tabindex): sekme şeridi TEK Tab durağıdır, sekmeler
     arasında ok tuşuyla gezilir (WAI-ARIA APG "Tabs"). Beş sekmeyi Tab ile
     tek tek geçmek zorunda kalmak klavye kullanıcısını yorar. */
  function sekmeSec(s, odakla) {
    if (!s) { return; }
    if (!s.classList.contains('aktif')) { digerleriniIptalEt(''); }
    sekmeler.forEach(function (x) {
      x.classList.remove('aktif');
      x.setAttribute('aria-selected', 'false');
      x.setAttribute('tabindex', '-1');
    });
    s.classList.add('aktif');
    s.setAttribute('aria-selected', 'true');
    s.setAttribute('tabindex', '0');
    document.querySelectorAll('.m-sekme-icerik').forEach(function (x) { x.hidden = true; });
    var panel = byId('sekme-' + s.dataset.sekme);
    if (panel) { panel.hidden = false; }
    if (odakla) { s.focus(); }
  }

  sekmeler.forEach(function (s, i) {
    s.addEventListener('click', function () { sekmeSec(s, false); });
    s.addEventListener('keydown', function (ev) {
      var adim = { ArrowRight: 1, ArrowLeft: -1 }[ev.key];
      var hedef = null;
      if (adim) {
        hedef = sekmeler[(i + adim + sekmeler.length) % sekmeler.length];
      } else if (ev.key === 'Home') {
        hedef = sekmeler[0];
      } else if (ev.key === 'End') {
        hedef = sekmeler[sekmeler.length - 1];
      }
      if (!hedef) { return; }
      ev.preventDefault();
      sekmeSec(hedef, true);
    });
  });

  var PROTOKOL_SEKME = {
    vurus_tutturma: 'vurus', bpm_bulma: 'bpm',
    spontan_tempo: 'spontan', aksak_bulma: 'aksak', icsel_ritim: 'icsel'
  };
  (function () {
    var kap = byId('mSekmeler');
    var acilacak = kap && kap.dataset.acilacak;
    if (acilacak && PROTOKOL_SEKME[acilacak]) {
      var sekme = document.querySelector('.m-sekme[data-sekme="' + PROTOKOL_SEKME[acilacak] + '"]');
      if (sekme) {
        sekme.click();
        sekme.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }
  })();

  /*
   * Ölçümle birlikte KARARLILIK ve KALİBRASYON KALİTESİ de kaydedilir.
   * Skor, sabit kaymayı (cihaz gecikmesi + kişisel erken/geç vurma eğilimi)
   * içinde taşır; asenkroni standart sapması taşımaz. Dönem karşılaştırması
   * bu yüzden SD'den okunmalıdır. Kalite kodu, sonradan "yalnız güvenilir
   * ölçümler" diye süzebilmek için saklanır.
   */
  function olcumEkBilgisiYaz(onek, sapmalar) {
    var sdAlan = byId(onek + 'FormSd');
    var kaliteAlan = byId(onek + 'FormKalite');
    if (sdAlan) {
      var sd = (sapmalar && sapmalar.length >= 2) ? zaman.standartSapma(sapmalar) : null;
      sdAlan.value = sd === null ? '' : Math.round(sd);
    }
    if (kaliteAlan) {
      kaliteAlan.value = (ses.ctx ? zaman.kaliteDurumu(ses.ctx).kod : '') || '';
    }
  }

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
    vuruslar: [], taplar: [], bpm: 72, fazVurus: 16, toplamVurus: 0, bitisZamani: 0,
    gosterilenFaz: -1, vurusAcik: false, kapiZamanlayici: null
  };

  function vtBaslat() {
    if (m.calisiyor) { metronomDurdur(); }
    digerleriniIptalEt('vt');
    ses.hazirla();
    if (!olcumIcinKalibrasyonHazir()) { return; }
    ses.duzey(parseInt(m.el.duzey.value, 10) / 100);
    vt.bpm = parseInt(byId('vtBpm').value, 10);
    vt.fazVurus = parseInt(byId('vtVurusSayisi').value, 10);
    vt.toplamVurus = 4 + vt.fazVurus * 2;
    vt.vuruslar = [];
    vt.taplar = [];
    vt.vurusNo = 0;
    vt.sonrakiZaman = ses.ctx.currentTime + 0.6;
    vt.gosterilenFaz = -1;
    vt.vurusAcik = false;
    vt.aktif = true;
    byId('vtSahne').hidden = false;
    byId('vtSonuc').hidden = true;
    byId('vtPad').classList.add('m-pad-hazir');
    clearTimeout(vt.kapiZamanlayici);
    vt.kapiZamanlayici = setTimeout(function () {
      if (vt.aktif) { vt.vurusAcik = true; }
    }, Math.max(0, (vt.sonrakiZaman + 4 * 60 / vt.bpm - ses.ctx.currentTime) * 1000
      - zaman.BASLANGIC_KAPISI_MS));
    ilerlemeAkit('vtIlerleme', vt.toplamVurus * 60 / vt.bpm,
                 (vt.sonrakiZaman - ses.ctx.currentTime) * 1000);
    vtFazEtiketi(0);
    vt.zamanlayici = setInterval(vtPlanla, 25);
  }

  function vtFazEtiketi(faz, kalan) {
    var el = byId('vtFaz');
    el.classList.toggle('sessiz-faz', faz === 2);
    el.textContent = faz === 0 ? '🎧 Hazırlık · ' + (kalan || 4) + ' → geri sayım bitince vur'
      : faz === 1 ? '🔊 Sesli faz — metronomla birlikte vur'
      : '🔇 Sessiz faz — içinden say, vurmaya devam et';
  }

  function vtPlanla() {
    while (vt.aktif && vt.sonrakiZaman < ses.ctx.currentTime + 0.12 && vt.vurusNo < vt.toplamVurus) {
      var faz = vt.vurusNo < 4 ? 0 : (vt.vurusNo < 4 + vt.fazVurus ? 1 : 2);
      if (faz < 2) {
        ses.vur(vt.sonrakiZaman, vt.vurusNo % 4 === 0, m.el.ses.value);
      }
      var hazirlikKalan = faz === 0 ? 4 - vt.vurusNo : 0;
      vt.vuruslar.push({ zaman: vt.sonrakiZaman, faz: faz, hazirlikKalan: hazirlikKalan });
      (function (vurus) {
        var gecikme = Math.max(0, (vurus.zaman - ses.ctx.currentTime) * 1000);
        setTimeout(function () {
          if (!vt.aktif) { return; }
          if (vurus.faz === 0) {
            vtFazEtiketi(0, vurus.hazirlikKalan);
            return;
          }
          if (vt.gosterilenFaz === vurus.faz) { return; }
          vt.gosterilenFaz = vurus.faz;
          if (vurus.faz === 1) {
            vt.vurusAcik = true;
            byId('vtPad').classList.remove('m-pad-hazir');
          }
          vtFazEtiketi(vurus.faz);
        }, gecikme);
      })(vt.vuruslar[vt.vuruslar.length - 1]);
      vt.sonrakiZaman += 60 / vt.bpm;
      vt.vurusNo++;
    }
    if (vt.vurusNo >= vt.toplamVurus) {
      vt.bitisZamani = vt.vuruslar[vt.vuruslar.length - 1].zaman + 60 / vt.bpm;
      if (ses.ctx.currentTime > vt.bitisZamani + 0.3) {
        clearInterval(vt.zamanlayici);
        clearTimeout(vt.kapiZamanlayici);
        vt.aktif = false;
        vt.vurusAcik = false;
        vtDegerlendir();
      }
    }
  }

  function vtTap(olay) {
    if (!vt.aktif || !vt.vurusAcik) { return; }
    vt.taplar.push(zaman.olayZamani(ses.ctx, olay));
    var pad = byId('vtPad');
    pad.classList.add('vurdum');
    setTimeout(function () { pad.classList.remove('vurdum'); }, 90);
  }

  function vtDegerlendir() {
    var aralikMs = 60000 / vt.bpm;
    var fazlar = { 1: { sapmalar: [], vurulan: {}, deneme: 0 }, 2: { sapmalar: [], vurulan: {}, deneme: 0 } };
    var grafik = [];

    var eslesme = vuruslariEsle(vt.taplar, vt.vuruslar, aralikMs * 0.45 / 1000, function (v) {
      return v.faz === 1 || v.faz === 2;
    });
    eslesme.denemeler.forEach(function (d) {
      var faz = d.hedef.faz;
      fazlar[faz].deneme++;
      if (d.eslesti) {
        fazlar[faz].sapmalar.push(d.sapmaMs);
        fazlar[faz].vurulan[d.hedefIdx] = true;
      }
      grafik.push({ sapma: d.sapmaMs, faz: faz, kacik: !d.eslesti });
    });

    function fazOzet(f) {
      var s = fazlar[f].sapmalar;
      var vurulanAdet = Object.keys(fazlar[f].vurulan).length;
      var kacirilan = vt.fazVurus - vurulanAdet;
      var fazla = Math.max(0, fazlar[f].deneme - vurulanAdet);
      var mutlak = ort(s.map(Math.abs));
      var hamSkor = s.length ? Math.max(0, 1 - mutlak / (0.30 * aralikMs)) : 0;
      var isabet = vurulanAdet / vt.fazVurus;
      var dogruluk = vurulanAdet / Math.max(1, fazlar[f].deneme);
      return {
        n: s.length, kacirilan: kacirilan, fazla: fazla,
        ortSapma: Math.round(ort(s)), ortMutlak: Math.round(mutlak),
        skor: Math.round(100 * hamSkor * isabet * dogruluk)
      };
    }
    var f1 = fazOzet(1), f2 = fazOzet(2);
    var genel = Math.round(0.4 * f1.skor + 0.6 * f2.skor);

    byId('vtSahne').hidden = true;
    byId('vtSonuc').hidden = false;
    byId('vtSkor').textContent = genel;
    byId('vtTablo').innerHTML =
      '<tr><td>🔊 Sesli (metronomla)</td><td class="sayi">' + f1.n + '/' + vt.fazVurus + '</td>' +
      '<td class="sayi">' + f1.kacirilan + '</td><td class="sayi">' + f1.fazla + '</td><td class="sayi">' + (f1.ortSapma > 0 ? '+' : '') + f1.ortSapma + ' ms</td>' +
      '<td class="sayi">' + f1.ortMutlak + ' ms</td><td class="sayi"><strong>' + f1.skor + '</strong></td></tr>' +
      '<tr><td>🔇 Sessiz (içsel tempo)</td><td class="sayi">' + f2.n + '/' + vt.fazVurus + '</td>' +
      '<td class="sayi">' + f2.kacirilan + '</td><td class="sayi">' + f2.fazla + '</td><td class="sayi">' + (f2.ortSapma > 0 ? '+' : '') + f2.ortSapma + ' ms</td>' +
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
    if (f1.fazla + f2.fazla > 0) {
      yorum.push((f1.fazla + f2.fazla) + ' fazla vuruş puana doğruluk cezası olarak yansıdı.');
    }
    if (f2.ortMutlak > f1.ortMutlak * 1.5 && f1.n > 0) {
      yorum.push('Metronom susunca sapma belirgin arttı — sessiz sürdürme çalışılabilir.');
    } else if (f2.n > 0 && f1.n > 0) {
      yorum.push('Sessiz fazda tempo büyük ölçüde korundu.');
    }
    byId('vtYorum').textContent = yorum.join(' ');

    byId('vtFormBpm').value = vt.bpm;
    byId('vtFormSkor').value = genel;
    var kal = zaman.kalibrasyonOku();
    byId('vtFormDetay').value = JSON.stringify({
      bpm: vt.bpm, fazVurus: vt.fazVurus, sesli: f1, sessiz: f2,
      zamanlamaSurumu: zaman.SURUM, telafiMs: kal.telafiMs,
      kalibrasyonDagilimMs: kal.dagilimMs
    });
    byId('vtFormStandart').value = (vt.bpm === 72 && vt.fazVurus === 16) ? '1' : '0';
    olcumEkBilgisiYaz('vt', [].concat(fazlar[1].sapmalar, fazlar[2].sapmalar));
  }

  function vtIptalEt() {
    clearInterval(vt.zamanlayici);
    clearTimeout(vt.kapiZamanlayici);
    vt.aktif = false;
    vt.vurusAcik = false;
    byId('vtPad').classList.remove('m-pad-hazir');
    ses.sustur();
    ilerlemeDondur('vtIlerleme');
    byId('vtSahne').hidden = true;
  }

  byId('vtBaslat').addEventListener('click', vtBaslat);
  byId('vtIptal').addEventListener('click', vtIptalEt);
  byId('vtTekrar').addEventListener('click', function () { byId('vtSonuc').hidden = true; vtBaslat(); });
    /*
   * Pad'ler <button>: klavyeden Enter/Space basilinca native 'click' gelir
   * ama 'pointerdown' GELMEZ — yani pad yalniz fareyle calisiyordu.
   * olay.detail === 0 = klavye kaynakli click (fare tiklamasinda >0).
   * poliritim-studyo.js'te kurulan desen buraya kopyalandi.
   */
  byId('vtPad').addEventListener('pointerdown', function (ev) { ev.preventDefault(); vtTap(ev); });
  byId('vtPad').addEventListener('click', function (ev) { if (ev.detail === 0) { vtTap(ev); } });
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
    var kit = m.el.ses.value;
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

  function bfTap(olay) {
    if (!bf.aktif || bf.dinlemede) { return; }
    bf.taplar.push(zaman.olayZamani(ses.ctx, olay));
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
    byId('bfFormDetay').value = JSON.stringify({ turlar: bf.sonuclar, zorluk: byId('bfZorluk').value });
    byId('bfFormStandart').value = byId('bfZorluk').value === 'orta' ? '1' : '0';
    olcumEkBilgisiYaz('bf', null);   // tempo tahmini: asenkroni SD'si tanımsız
  }

  function bfIptalEt() {
    clearTimeout(bf.zamanlayici);
    bf.aktif = false;
    ses.sustur();   // 8 vuruşluk dizi ileri tarihli zamanlanmıştır
    byId('bfSahne').hidden = true;
  }

  byId('bfBaslat').addEventListener('click', bfBaslatOyun);
  byId('bfIptal').addEventListener('click', bfIptalEt);
  byId('bfTekrar').addEventListener('click', function () { byId('bfSonuc').hidden = true; bfBaslatOyun(); });
  byId('bfPad').addEventListener('pointerdown', function (ev) { ev.preventDefault(); bfTap(ev); });
  byId('bfPad').addEventListener('click', function (ev) { if (ev.detail === 0) { bfTap(ev); } });
  kaydetFormuBagla('bf', 'bfOgrenci');

  /* ================================================================
     E) SPONTAN TEMPO (BAASTA: unpaced tapping)
     ================================================================ */
  var st = { aktif: false, vurusAcik: false, taplar: [], HEDEF: 21, baslangicZamanlayici: null };

  function stBaslat() {
    if (m.calisiyor) { metronomDurdur(); }
    digerleriniIptalEt('st');
    ses.hazirla();
    st.taplar = [];
    st.aktif = true;
    st.vurusAcik = false;
    byId('stSahne').hidden = false;
    byId('stSonuc').hidden = true;
    byId('stSayac').textContent = '0 / ' + st.HEDEF;
    byId('stPad').classList.add('m-pad-hazir');
    byId('stFaz').textContent = 'Hazır ol · rahat nefes al';
    clearTimeout(st.baslangicZamanlayici);
    st.baslangicZamanlayici = setTimeout(function () {
      if (!st.aktif) { return; }
      st.vurusAcik = true;
      byId('stPad').classList.remove('m-pad-hazir');
      byId('stFaz').textContent = 'ŞİMDİ · kendi rahat hızında vur';
    }, 1200);
  }

  function stTap(olay) {
    if (!st.aktif || !st.vurusAcik) { return; }
    st.taplar.push(zaman.olayZamani(ses.ctx, olay));
    var pad = byId('stPad');
    pad.classList.add('vurdum');
    setTimeout(function () { pad.classList.remove('vurdum'); }, 80);
    byId('stSayac').textContent = st.taplar.length + ' / ' + st.HEDEF;
    if (st.taplar.length >= st.HEDEF) { stBitir(); }
  }

  function stBitir() {
    st.aktif = false;
    st.vurusAcik = false;
    byId('stPad').classList.remove('m-pad-hazir');
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
    byId('stFormStandart').value = '1'; // parametresiz ölçüm: koşullar hep aynı
    olcumEkBilgisiYaz('st', araliklar);   // serbest tempoda kararlılık = aralık SD'si
  }

  function stIptalEt() {
    clearTimeout(st.baslangicZamanlayici);
    st.aktif = false;
    st.vurusAcik = false;
    byId('stPad').classList.remove('m-pad-hazir');
    byId('stSahne').hidden = true;
  }

  byId('stBaslat').addEventListener('click', stBaslat);
  byId('stIptal').addEventListener('click', stIptalEt);
  byId('stTekrar').addEventListener('click', function () { byId('stSonuc').hidden = true; stBaslat(); });
  byId('stPad').addEventListener('pointerdown', function (ev) { ev.preventDefault(); stTap(ev); });
  byId('stPad').addEventListener('click', function (ev) { if (ev.detail === 0) { stTap(ev); } });
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
    var kit = m.el.ses.value;
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
    byId('abFormStandart').value = byId('abZorluk').value === 'orta' ? '1' : '0';
    olcumEkBilgisiYaz('ab', null);   // saf algı testi: motor asenkroni yok
  }

  function abIptalEt() {
    clearTimeout(ab.zamanlayici);
    ab.aktif = false;
    ses.sustur();   // 6 vuruşluk dizi ileri tarihli zamanlanmıştır
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
  var IR_PROFILLER = {
    kolay: [0, 15, 25, 50],
    standart: [0, 25, 50, 75],
    ileri: [25, 50, 75, 90]
  };
  var ir = {
    aktif: false, zamanlayici: null, sonrakiZaman: 0, vurusNo: 0,
    vuruslar: [], taplar: [], bpm: 72, profil: 'standart',
    FAZ_YUZDE: IR_PROFILLER.standart, FAZ_VURUS: 8, gosterilenFaz: -2,
    vurusAcik: false, kapiZamanlayici: null
  };

  /* Uyarlanan zorluk: öğrenci seçilince son İçsel Ritim skoruna göre profil öner */
  byId('irOgrenci').addEventListener('change', function () {
    var oneri = byId('irOneri');
    var oid = this.value;
    if (stdAcik()) { oneri.textContent = '📏 Standart mod açık — profil sabit (Standart).'; return; }
    var skor = (window.SON_SKORLAR && window.SON_SKORLAR[oid] || {}).icsel_ritim;
    if (skor === undefined) {
      oneri.textContent = oid ? 'İlk ölçüm — Standart profille başlanır.' : 'Öğrenci seçilince son skora göre önerilir.';
      if (oid) { byId('irProfil').value = 'standart'; }
      return;
    }
    var profil = skor >= 80 ? 'ileri' : (skor < 50 ? 'kolay' : 'standart');
    byId('irProfil').value = profil;
    oneri.textContent = 'Son skoru ' + skor + ' → ' +
      (profil === 'ileri' ? 'İleri' : profil === 'kolay' ? 'Kolay' : 'Standart') + ' profil önerildi.';
  });

  function irPlanOlustur() {
    /* 4 hazırlık + 4 faz × 8 vuruş; her fazda tam yüzde kadar vuruş susturulur
       (fazın ilk vuruşu çapa olarak daima seslidir). */
    ir.vuruslar = [];
    for (var h = 0; h < 4; h++) {
      ir.vuruslar.push({ faz: -1, sessiz: false, hazirlikNo: h });
    }
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
    if (!olcumIcinKalibrasyonHazir()) { return; }
    ses.duzey(parseInt(m.el.duzey.value, 10) / 100);
    ir.bpm = parseInt(byId('irBpm').value, 10);
    ir.profil = byId('irProfil').value;
    ir.FAZ_YUZDE = IR_PROFILLER[ir.profil] || IR_PROFILLER.standart;
    irPlanOlustur();
    ir.taplar = [];
    ir.vurusNo = 0;
    ir.vurusAcik = false;
    ir.sonrakiZaman = ses.ctx.currentTime + 0.6;
    ir.aktif = true;
    byId('irSahne').hidden = false;
    byId('irSonuc').hidden = true;
    byId('irPad').classList.add('m-pad-hazir');
    clearTimeout(ir.kapiZamanlayici);
    ir.kapiZamanlayici = setTimeout(function () {
      if (ir.aktif) { ir.vurusAcik = true; }
    }, Math.max(0, (ir.sonrakiZaman + 4 * 60 / ir.bpm - ses.ctx.currentTime) * 1000
      - zaman.BASLANGIC_KAPISI_MS));
    ir.gosterilenFaz = -2;
    ilerlemeAkit('irIlerleme', ir.vuruslar.length * 60 / ir.bpm,
                 (ir.sonrakiZaman - ses.ctx.currentTime) * 1000);
    byId('irFaz').textContent = '🎧 Hazırlık — dinle, sonra her vuruşta vur';
    ir.zamanlayici = setInterval(irPlanla, 25);
  }

  function irPlanla() {
    var kit = m.el.ses.value;
    while (ir.aktif && ir.sonrakiZaman < ses.ctx.currentTime + 0.12 && ir.vurusNo < ir.vuruslar.length) {
      var v = ir.vuruslar[ir.vurusNo];
      v.zaman = ir.sonrakiZaman;
      if (!v.sessiz) {
        ses.vur(ir.sonrakiZaman, ir.vurusNo % 4 === 0, kit);
      }
      (function (vv) {
        var gecikme = Math.max(0, (vv.zaman - ses.ctx.currentTime) * 1000);
        setTimeout(function () {
          // Etiket yalnız faz değişiminde güncellenir: her vuruşta yazmak
          // sessiz vuruşu ele veren görsel bir işaret olur.
          if (!ir.aktif) { return; }
          if (vv.faz < 0) {
            byId('irFaz').textContent = '🎧 Hazırlık · ' + (4 - vv.hazirlikNo)
              + ' → geri sayım bitince vur';
            return;
          }
          if (ir.gosterilenFaz === vv.faz) { return; }
          ir.gosterilenFaz = vv.faz;
          if (vv.faz === 0) {
            ir.vurusAcik = true;
            byId('irPad').classList.remove('m-pad-hazir');
          }
          byId('irFaz').textContent = '🥁 Faz ' + (vv.faz + 1) + ' — %' + ir.FAZ_YUZDE[vv.faz] + ' sessiz · vurmaya devam';
        }, gecikme);
      })(v);
      ir.sonrakiZaman += 60 / ir.bpm;
      ir.vurusNo++;
    }
    if (ir.vurusNo >= ir.vuruslar.length) {
      var bitis = ir.vuruslar[ir.vuruslar.length - 1].zaman + 60 / ir.bpm;
      if (ses.ctx.currentTime > bitis + 0.3) {
        clearInterval(ir.zamanlayici);
        clearTimeout(ir.kapiZamanlayici);
        ir.aktif = false;
        ir.vurusAcik = false;
        irDegerlendir();
      }
    }
  }

  function irTap(olay) {
    if (!ir.aktif || !ir.vurusAcik) { return; }
    ir.taplar.push(zaman.olayZamani(ses.ctx, olay));
    var pad = byId('irPad');
    pad.classList.add('vurdum');
    setTimeout(function () { pad.classList.remove('vurdum'); }, 80);
  }

  function irDegerlendir() {
    var aralikMs = 60000 / ir.bpm;
    var fazVeri = ir.FAZ_YUZDE.map(function () { return { sapmalar: [], vurulan: {}, deneme: 0 }; });
    var sesliSapma = [], sessizSapma = [];
    var grafik = [];

    var eslesme = vuruslariEsle(ir.taplar, ir.vuruslar, aralikMs * 0.45 / 1000, function (v) {
      return v.faz >= 0;
    });
    eslesme.denemeler.forEach(function (d) {
      var hedef = d.hedef;
      fazVeri[hedef.faz].deneme++;
      if (d.eslesti) {
        fazVeri[hedef.faz].sapmalar.push(d.sapmaMs);
        fazVeri[hedef.faz].vurulan[d.hedefIdx] = true;
        (hedef.sessiz ? sessizSapma : sesliSapma).push(Math.abs(d.sapmaMs));
      }
      grafik.push({ sapma: d.sapmaMs, sessiz: hedef.sessiz, kacik: !d.eslesti });
    });

    var AGIRLIK = [0.1, 0.2, 0.3, 0.4];
    var fazlar = fazVeri.map(function (f, i) {
      var mutlak = ort(f.sapmalar.map(Math.abs));
      var vurulanAdet = Object.keys(f.vurulan).length;
      var hamSkor = f.sapmalar.length ? Math.max(0, 1 - mutlak / (0.30 * aralikMs)) : 0;
      var dogruluk = vurulanAdet / Math.max(1, f.deneme);
      return {
        yuzde: ir.FAZ_YUZDE[i], n: f.sapmalar.length,
        kacirilan: ir.FAZ_VURUS - vurulanAdet,
        fazla: Math.max(0, f.deneme - vurulanAdet),
        ortMutlak: Math.round(mutlak),
        skor: Math.round(100 * hamSkor * (vurulanAdet / ir.FAZ_VURUS) * dogruluk)
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
        '<td class="sayi">' + f.fazla + '</td>' +
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
    var toplamFazla = fazlar.reduce(function (t, f) { return t + f.fazla; }, 0);
    if (toplamFazla > 0) { yorum += ' ' + toplamFazla + ' fazla vuruş doğruluk cezasına katıldı.'; }
    byId('irYorum').textContent = yorum;

    byId('irFormBpm').value = ir.bpm;
    byId('irFormSkor').value = genel;
    var kal = zaman.kalibrasyonOku();
    byId('irFormDetay').value = JSON.stringify({
      bpm: ir.bpm, profil: ir.profil, fazlar: fazlar, sesliOrtMs: sesliOrt, sessizOrtMs: sessizOrt,
      zamanlamaSurumu: zaman.SURUM, telafiMs: kal.telafiMs,
      kalibrasyonDagilimMs: kal.dagilimMs
    });
    byId('irFormStandart').value = (ir.bpm === 72 && ir.profil === 'standart') ? '1' : '0';
    olcumEkBilgisiYaz('ir', fazVeri.reduce(function (t, f) { return t.concat(f.sapmalar); }, []));
  }

  function irIptalEt() {
    clearInterval(ir.zamanlayici);
    clearTimeout(ir.kapiZamanlayici);
    ir.aktif = false;
    ir.vurusAcik = false;
    byId('irPad').classList.remove('m-pad-hazir');
    ses.sustur();
    ilerlemeDondur('irIlerleme');
    byId('irSahne').hidden = true;
  }

  byId('irBaslat').addEventListener('click', irBaslat);
  byId('irIptal').addEventListener('click', irIptalEt);
  byId('irTekrar').addEventListener('click', function () { byId('irSonuc').hidden = true; irBaslat(); });
  byId('irPad').addEventListener('pointerdown', function (ev) { ev.preventDefault(); irTap(ev); });
  byId('irPad').addEventListener('click', function (ev) { if (ev.detail === 0) { irTap(ev); } });
  kaydetFormuBagla('ir', 'irOgrenci');

  /* Bir test başlarken (veya sekme değişince) diğerlerini iptal et.
     haric boş bırakılırsa hepsi durur. */
  function digerleriniIptalEt(haric) {
    if (haric !== 'setlist' && setAkis && setAkis.aktif) { setlistBitir('Protokol açıldığı için setlist durduruldu'); }
    if (haric !== 'zk' && zkKalibrator) { zkIptalEt(); }
    if (haric !== 'vt' && vt.aktif) { vtIptalEt(); }
    if (haric !== 'bf' && bf.aktif) { bfIptalEt(); }
    if (haric !== 'st' && st.aktif) { stIptalEt(); }
    if (haric !== 'ab' && ab.aktif) { abIptalEt(); }
    if (haric !== 'ir' && ir.aktif) { irIptalEt(); }
    if (haric !== 'oyun' && oyun.aktif) { oyunIptalEt(); }
  }

  /* ================================================================
     ŞARKI BPM TAHMİN OYUNU
     Şarkının kendisi ÇALINMAZ (telif + dosya yok): "önce dinle" modunda
     temposu 10 sn metronom kliğiyle dinletilir. Skorlar oyunlaştırma
     amaçlıdır; protokol ölçümlerine KARIŞMAZ (yalnız localStorage rekoru).
     ================================================================ */
  var OYUN_TUR_SAYISI = 5;
  var OYUN_DINLETME_SN = 10;
  var OYUN_REKOR_ANAHTARI = 'ritim_bpm_oyun_rekor_v1';
  var oyun = {
    aktif: false, faz: 'bos', tur: 0, sarkilar: [], sonuclar: [],
    taplar: [], sureSn: 20, mod: 'dinle', sayacId: null, bitisMs: 0
  };

  function oyunRekorOku() {
    try { return JSON.parse(localStorage.getItem(OYUN_REKOR_ANAHTARI) || '{}') || {}; }
    catch (e) { return {}; }
  }

  function oyunRekorYaz() {
    var r = oyunRekorOku();
    var el = byId('oyunRekor');
    if (!el) { return; }
    var mod = byId('oyunMod').value;
    el.textContent = r[mod] ? ('🏆 Rekorun: ' + r[mod] + ' puan') : '';
  }

  function oyunIptalEt() {
    clearInterval(oyun.sayacId);
    oyun.aktif = false;
    oyun.faz = 'bos';
    ses.sustur();   // dinletme vuruşları ileri tarihli zamanlanmıştır
    byId('oyunSahne').hidden = true;
  }

  function oyunBaslat() {
    digerleriniIptalEt('oyun');
    if (m.calisiyor) { metronomDurdur(); }
    ses.hazirla();
    ses.duzey(parseInt(m.el.duzey.value, 10) / 100);
    var tur = byId('oyunTur').value;
    var havuz = SARKILAR.filter(function (s) { return !tur || s.tur === tur; });
    for (var i = havuz.length - 1; i > 0; i--) {   // karıştır: aynı oyunda şarkı tekrarı olmasın
      var j = Math.floor(Math.random() * (i + 1));
      var tmp = havuz[i]; havuz[i] = havuz[j]; havuz[j] = tmp;
    }
    oyun.sarkilar = havuz.slice(0, OYUN_TUR_SAYISI);
    if (oyun.sarkilar.length < OYUN_TUR_SAYISI) { // tür havuzu küçükse tamamla
      oyun.sarkilar = oyun.sarkilar.concat(SARKILAR.slice(0, OYUN_TUR_SAYISI - oyun.sarkilar.length));
    }
    oyun.mod = byId('oyunMod').value;
    oyun.sureSn = parseInt(byId('oyunSure').value, 10) || 20;
    oyun.tur = 0;
    oyun.sonuclar = [];
    oyun.aktif = true;
    byId('oyunSahne').hidden = false;
    byId('oyunSonuc').hidden = true;
    oyunTurBaslat();
  }

  function oyunTurBaslat() {
    oyun.tur++;
    oyun.taplar = [];
    var s = oyun.sarkilar[oyun.tur - 1];
    byId('oyunTurGoster').textContent = 'Tur ' + oyun.tur + ' / ' + OYUN_TUR_SAYISI;
    byId('oyunSarki').textContent = '🎵 ' + s.ad + ' — ' + s.sanatci;
    byId('oyunTahmin').hidden = true;
    byId('oyunSayac').textContent = '';
    if (oyun.mod === 'dinle') {
      oyun.faz = 'dinleme';
      byId('oyunFaz').textContent = '🎧 Dinle — şarkının temposu ' + OYUN_DINLETME_SN + ' sn metronomla çalınıyor…';
      var kit = m.el.ses.value === 'sayma' ? 'tahta' : m.el.ses.value;
      var bas = ses.ctx.currentTime + 0.4;
      var adet = Math.ceil(OYUN_DINLETME_SN * s.bpm / 60);
      for (var i = 0; i < adet; i++) {
        ses.vur(bas + i * 60 / s.bpm, i % 4 === 0, kit);
      }
      setTimeout(function () {
        if (oyun.aktif && oyun.faz === 'dinleme') { oyunTahminFazi(); }
      }, (bas - ses.ctx.currentTime + OYUN_DINLETME_SN) * 1000 + 200);
    } else {
      oyunTahminFazi();
    }
  }

  function oyunTahminFazi() {
    oyun.faz = 'tahmin';
    ses.sustur();
    byId('oyunFaz').textContent = oyun.mod === 'dinle'
      ? '🥁 Şimdi hatırladığın tempoda vur!'
      : '🧠 Şarkıyı zihninde canlandır, temposunda vur!';
    oyun.bitisMs = Date.now() + oyun.sureSn * 1000;
    clearInterval(oyun.sayacId);
    oyun.sayacId = setInterval(function () {
      var kalan = Math.max(0, (oyun.bitisMs - Date.now()) / 1000);
      byId('oyunSayac').textContent = '⏱ ' + kalan.toFixed(1) + ' sn';
      if (kalan <= 0) { oyunTurBitir(); }
    }, 100);
  }

  function oyunTap(olay) {
    if (!oyun.aktif || oyun.faz !== 'tahmin') { return; }
    oyun.taplar.push(zaman.olayZamani(ses.ctx, olay));
    var pad = byId('oyunPad');
    pad.classList.add('vurdum');
    setTimeout(function () { pad.classList.remove('vurdum'); }, 80);
    byId('oyunFaz').textContent = '🥁 ' + oyun.taplar.length + ' vuruş' +
      (oyun.taplar.length >= 4 ? ' — hazırsan tahminini onayla' : ' (en az 4)');
    byId('oyunTahmin').hidden = oyun.taplar.length < 4;
  }

  function oyunTurBitir() {
    clearInterval(oyun.sayacId);
    var s = oyun.sarkilar[oyun.tur - 1];
    var sonuc = { ad: s.ad, sanatci: s.sanatci, gercek: s.bpm, tahmin: null, esas: '', hata: null, puan: 0 };
    if (oyun.taplar.length >= 4) {
      var araliklar = [];
      for (var i = 1; i < oyun.taplar.length; i++) { araliklar.push(oyun.taplar[i] - oyun.taplar[i - 1]); }
      sonuc.tahmin = Math.round(60 / zaman.medyan(araliklar));   // medyan: tek bozuk vuruşa dayanıklı
      /* Yarı/çift tempo müzikal olarak meşrudur (half/double-time hissi):
         puan, {gerçek, yarısı, iki katı} içinden EN YAKININA göre hesaplanır. */
      var adaylar = [
        { bpm: s.bpm, etiket: '' },
        { bpm: s.bpm / 2, etiket: '½ tempo' },
        { bpm: s.bpm * 2, etiket: '2× tempo' }
      ];
      var enIyi = adaylar[0];
      adaylar.forEach(function (a) {
        if (Math.abs(sonuc.tahmin - a.bpm) < Math.abs(sonuc.tahmin - enIyi.bpm)) { enIyi = a; }
      });
      sonuc.esas = enIyi.etiket;
      sonuc.hata = Math.round(Math.abs(sonuc.tahmin - enIyi.bpm) / enIyi.bpm * 1000) / 10;
      sonuc.puan = Math.max(0, Math.round(100 - sonuc.hata * 4));
      if (enIyi.etiket !== '') { sonuc.puan = Math.max(0, sonuc.puan - 10); } // oktav farkına küçük kesinti
    }
    oyun.sonuclar.push(sonuc);
    if (oyun.tur < OYUN_TUR_SAYISI) {
      byId('oyunFaz').textContent = sonuc.tahmin === null
        ? '⌛ Süre doldu — yetersiz vuruş (0 puan). Sıradaki şarkı…'
        : '✔ Gerçek: ' + sonuc.gercek + ' BPM · Tahminin: ' + sonuc.tahmin + ' BPM → ' + sonuc.puan + ' puan';
      oyun.faz = 'ara';
      setTimeout(function () { if (oyun.aktif) { oyunTurBaslat(); } }, 2200);
    } else {
      oyunBitir();
    }
  }

  function oyunBitir() {
    oyun.aktif = false;
    oyun.faz = 'bos';
    byId('oyunSahne').hidden = true;
    byId('oyunSonuc').hidden = false;
    var ortalama = Math.round(ort(oyun.sonuclar.map(function (x) { return x.puan; })));
    byId('oyunSkor').textContent = ortalama;
    byId('oyunTablo').innerHTML = oyun.sonuclar.map(function (x, i) {
      return '<tr><td>' + (i + 1) + '</td><td>' + x.ad + ' — ' + x.sanatci + '</td>' +
        '<td class="sayi">' + x.gercek + '</td>' +
        '<td class="sayi">' + (x.tahmin === null ? '—' : x.tahmin + (x.esas ? ' <small>(' + x.esas + ')</small>' : '')) + '</td>' +
        '<td class="sayi">' + (x.hata === null ? '—' : '%' + x.hata) + '</td>' +
        '<td class="sayi"><strong>' + x.puan + '</strong></td></tr>';
    }).join('');
    var rekorlar = oyunRekorOku();
    var eskiRekor = rekorlar[oyun.mod] || 0;
    var yorum;
    if (ortalama > eskiRekor) {
      rekorlar[oyun.mod] = ortalama;
      try { localStorage.setItem(OYUN_REKOR_ANAHTARI, JSON.stringify(rekorlar)); } catch (e) {}
      yorum = '🏆 Yeni rekor! (önceki: ' + (eskiRekor || '—') + ')';
    } else {
      yorum = 'Rekorun: ' + eskiRekor + ' puan.';
    }
    yorum += ' Bu bir oyundur; sonuçlar protokol ölçümlerine kaydedilmez.';
    byId('oyunYorum').textContent = yorum;
    oyunRekorYaz();
  }

  if (byId('oyunKart')) {
    byId('oyunBaslat').addEventListener('click', oyunBaslat);
    byId('oyunIptal').addEventListener('click', oyunIptalEt);
    byId('oyunTekrar').addEventListener('click', function () { byId('oyunSonuc').hidden = true; oyunBaslat(); });
    byId('oyunTahmin').addEventListener('click', oyunTurBitir);
    byId('oyunPad').addEventListener('pointerdown', function (ev) { ev.preventDefault(); oyunTap(ev); });
  byId('oyunPad').addEventListener('click', function (ev) { if (ev.detail === 0) { oyunTap(ev); } });
    byId('oyunMod').addEventListener('change', oyunRekorYaz);
    oyunRekorYaz();
  }

  /* ================================================================
     STANDART ÖLÇÜM MODU — sabit koşullar; toggle yalnız ayarları
     kilitleyen kolaylıktır. 📏 işareti kayıtta GERÇEK koşullardan
     türetilir (sonuç dolumlarındaki FormStandart satırları).
     ================================================================ */
  var STD_AYAR = {
    vtBpm: '72', vtVurusSayisi: '16',
    bfZorluk: 'orta',
    abZorluk: 'orta',
    irBpm: '72', irProfil: 'standart'
  };
  var stdKutu = byId('stdMod');
  var stdOnceki = {};
  function stdAcik() { return !!(stdKutu && stdKutu.checked); }
  if (stdKutu) {
    stdKutu.addEventListener('change', function () {
      Object.keys(STD_AYAR).forEach(function (id) {
        var el = byId(id);
        if (!el) { return; }
        if (stdKutu.checked) {
          stdOnceki[id] = el.value;
          if (el.value !== STD_AYAR[id]) { el.value = STD_AYAR[id]; }
          el.disabled = true;
        } else {
          el.disabled = false;
          if (stdOnceki[id] !== undefined) { el.value = stdOnceki[id]; }
        }
      });
    });
  }

  /* ================================================================
     DERS AKIŞI — oturum planındaki teknikleri sırayla çalıştırır.
     Sayaç metronomdan bağımsızdır; adım bitince zil çalar.
     ================================================================ */
  function zilCal() {
    ses.hazirla();
    var t = ses.ctx.currentTime + 0.02;
    [659.25, 880].forEach(function (frekans, i) {
      var o = ses.ctx.createOscillator();
      var g = ses.ctx.createGain();
      o.type = 'sine';
      o.frequency.value = frekans;
      g.gain.setValueAtTime(0.0001, t + i * 0.18);
      g.gain.exponentialRampToValueAtTime(0.5, t + i * 0.18 + 0.02);
      g.gain.exponentialRampToValueAtTime(0.001, t + i * 0.18 + 0.9);
      o.connect(g); g.connect(ses.master);
      o.start(t + i * 0.18); o.stop(t + i * 0.18 + 1);
    });
    if (navigator.vibrate) { navigator.vibrate([180, 90, 180]); }
  }

  var akisYukleBtn = byId('akisYukle');
  if (akisYukleBtn && byId('akisSecim')) {
    akisYukleBtn.addEventListener('click', function () {
      window.location = 'metronom.php?akis=' + encodeURIComponent(byId('akisSecim').value);
    });
  }

  var akisVeri = window.DERS_AKISI || null;
  if (akisVeri && byId('akisListe')) {
    var akis = { idx: 0, kalanSn: 0, calisiyor: false, tik: null, bitti: false };
    var akisTeknikler = akisVeri.teknikler || [];
    var akisToplamDk = akisTeknikler.reduce(function (a, t) { return a + t.sure_dk; }, 0);

    var akisCiz = function () {
      document.querySelectorAll('#akisListe li').forEach(function (li) {
        var i = parseInt(li.dataset.idx, 10);
        li.classList.toggle('aktif', i === akis.idx && !akis.bitti);
        li.classList.toggle('tamam', akis.bitti || i < akis.idx);
      });
      byId('akisToplam').textContent = 'Toplam plan: ' + akisToplamDk + ' dk · ' + akisTeknikler.length + ' teknik';
      if (akis.bitti) {
        byId('akisAdimEtiket').textContent = '🎉 Ders akışı tamamlandı';
        byId('akisSayac').textContent = '00:00';
        byId('akisIlerleme').style.width = '100%';
        return;
      }
      var t = akisTeknikler[akis.idx];
      byId('akisAdimEtiket').textContent =
        (akis.idx + 1) + ' / ' + akisTeknikler.length + ' — ' + t.ad + (t.not ? ' · ' + t.not : '');
      var dk = Math.floor(akis.kalanSn / 60);
      var sn = akis.kalanSn % 60;
      byId('akisSayac').textContent = (dk < 10 ? '0' : '') + dk + ':' + (sn < 10 ? '0' : '') + sn;
      var gecenSn = akisTeknikler.slice(0, akis.idx).reduce(function (a, x) { return a + x.sure_dk * 60; }, 0)
                  + (t.sure_dk * 60 - akis.kalanSn);
      byId('akisIlerleme').style.width =
        Math.min(100, Math.round(100 * gecenSn / Math.max(1, akisToplamDk * 60))) + '%';
    };
    var akisAdimYukle = function (i) {
      akis.bitti = false;
      akis.idx = Math.max(0, Math.min(akisTeknikler.length - 1, i));
      akis.kalanSn = akisTeknikler[akis.idx].sure_dk * 60;
      akisCiz();
    };
    var akisDur = function () {
      clearInterval(akis.tik);
      akis.tik = null;
      akis.calisiyor = false;
    };
    var akisTikla = function () {
      akis.kalanSn--;
      if (akis.kalanSn <= 0) {
        zilCal();
        if (akis.idx >= akisTeknikler.length - 1) {
          akis.bitti = true;
          akisDur();
          byId('akisBaslat').textContent = '↺ Yeniden Başlat';
          akisCiz();
          return;
        }
        akisAdimYukle(akis.idx + 1);
        return;
      }
      akisCiz();
    };
    byId('akisBaslat').addEventListener('click', function () {
      if (akis.calisiyor) {
        akisDur();
        byId('akisBaslat').textContent = '▶ Devam';
        return;
      }
      if (akis.bitti) { akisAdimYukle(0); }
      ses.hazirla(); // zil için kullanıcı hareketiyle ses bağlamını aç
      akis.calisiyor = true;
      byId('akisBaslat').textContent = '⏸ Duraklat';
      akis.tik = setInterval(akisTikla, 1000);
    });
    byId('akisSonraki').addEventListener('click', function () {
      if (akis.idx < akisTeknikler.length - 1) { akisAdimYukle(akis.idx + 1); }
    });
    byId('akisOnceki').addEventListener('click', function () { akisAdimYukle(akis.idx - 1); });
    byId('akisSifirla').addEventListener('click', function () {
      akisDur();
      byId('akisBaslat').textContent = '▶ Başlat';
      akisAdimYukle(0);
    });
    var akisProtokolAc = byId('akisProtokolAc');
    if (akisProtokolAc) {
      akisProtokolAc.addEventListener('click', function () {
        var sekmeAdi = PROTOKOL_SEKME[akisProtokolAc.dataset.protokol];
        var sekme = sekmeAdi && document.querySelector('.m-sekme[data-sekme="' + sekmeAdi + '"]');
        if (sekme) { sekme.click(); sekme.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
      });
    }
    akisAdimYukle(0);
  }

  /* ================================================================
     Klavye kısayolları
     ================================================================ */
  var KISAYOL_ANAHTARI = 'ritim_metronom_kisayol_v1';
  function kisayollarAcik() {
    try { return localStorage.getItem(KISAYOL_ANAHTARI) !== '0'; } catch (e) { return true; }
  }
  (function () {
    var kutu = byId('mKisayolAcik');
    if (!kutu) { return; }
    kutu.checked = kisayollarAcik();
    kutu.addEventListener('change', function () {
      try { localStorage.setItem(KISAYOL_ANAHTARI, kutu.checked ? '1' : '0'); } catch (e) {}
    });
  })();

  document.addEventListener('keydown', function (ev) {
    /* Tus basili tutulunca isletim sistemi saniyede ~25 keydown uretir; bunlar
       olcume gercek vurus diye giriyordu. Diger modullerde bu denetim zaten var. */
    if (ev.repeat) { return; }
    var etiket = (ev.target.tagName || '').toLowerCase();
    if (etiket === 'input' || etiket === 'select' || etiket === 'textarea') { return; }
    if (ev.code === 'Space') {
      /*
       * ERİŞİLEBİLİRLİK — Space KOŞULSUZ yakalanmaz.
       * Önceden hemen preventDefault çağrılıyordu; odak "Ölçümü Başlat" veya
       * "Sonucu Kaydet" üzerindeyken Space butonu DEĞİL metronomu tetikliyordu,
       * yani sayfanın tamamı klavyeyle kullanılamaz hâldeydi (WCAG 2.1.1).
       * Kural: bir ölçüm/oyun etkinken Space ona aittir; boştayken tarayıcıya
       * bırakılır ve odaktaki buton normal çalışır.
       */
      var odakEtkilesimli = ev.target && typeof ev.target.closest === 'function'
        && !!ev.target.closest('button, a[href], [role="button"], summary');
      var olcumEtkin = !!zkKalibrator || vt.aktif || bf.aktif || st.aktif || ir.aktif
        || (oyun.aktif && oyun.faz === 'tahmin');

      if (olcumEtkin) {
        ev.preventDefault();
        if (zkKalibrator) { zkKalibrator.tap(ev); }
        else if (vt.aktif) { vtTap(ev); }
        else if (bf.aktif) { bfTap(ev); }
        else if (st.aktif) { stTap(ev); }
        else if (ir.aktif) { irTap(ev); }
        else { oyunTap(ev); }
      } else if (odakEtkilesimli) {
        return;                       // odaktaki düğme kendi işini yapsın
      } else {
        ev.preventDefault();
        if (setAkis && setAkis.aktif) { setlistDuraklatDevam(); }
        else if (m.calisiyor) { metronomDurdur(); }
        else { metronomBaslat(); }
      }
    } else if (ev.key === 't' || ev.key === 'T') {
      /*
       * WCAG 2.1.4 — tek karakterli kisayol KAPATILABILIR olmali.
       * 't' her an BPM'i degistiriyordu; sesli giris veya yanlis tus kullanan
       * biri metronomun tempoyu neden degistirdigini anlayamiyordu.
       * Degistirici tus EKLENMEDI: 't' olculen bir motor eylemi (tap tempo) ve
       * Ctrl basili tutmak vurus zamanlamasini uzatir.
       */
      if (!kisayollarAcik()) { return; }
      tapTempo();
    } else if (ev.key === 'ArrowUp') {
      ev.preventDefault(); bpmAyarla(m.bpm + (ev.shiftKey ? 5 : 1));
    } else if (ev.key === 'ArrowDown') {
      ev.preventDefault(); bpmAyarla(m.bpm - (ev.shiftKey ? 5 : 1));
    }
  });
})();
