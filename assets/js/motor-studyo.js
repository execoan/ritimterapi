(function () {
  'use strict';

  var zaman = window.RitimZamanlama;
  var motor = window.MotorKoordinasyon;
  var ilkVeri = window.MOTOR_STUDYO_VERI || null;
  if (!zaman || !motor || !ilkVeri || !document.getElementById('motorBaslat')) { return; }

  function byId(id) { return document.getElementById(id); }
  function sinirla(n, alt, ust, varsayilan) {
    n = Number(n);
    return Number.isFinite(n) ? Math.max(alt, Math.min(ust, n)) : varsayilan;
  }
  function sureYaz(sn) {
    sn = Math.max(0, Math.ceil(Number(sn) || 0));
    return String(Math.floor(sn / 60)).padStart(2, '0') + ':' + String(sn % 60).padStart(2, '0');
  }

  var audio = {
    ctx: null,
    master: null,
    hazirla: function () {
      if (!this.ctx) {
        this.ctx = new (window.AudioContext || window.webkitAudioContext)();
        this.master = this.ctx.createGain();
        this.master.connect(this.ctx.destination);
      }
      if (this.ctx.state === 'suspended') { this.ctx.resume(); }
      this.duzey();
      return this.ctx;
    },
    duzey: function () {
      if (this.master) { this.master.gain.value = sinirla(byId('motorSes').value, 0, 100, 70) / 100; }
    },
    sustur: function () {
      if (!this.ctx || !this.master) { return; }
      try { this.master.disconnect(); } catch (e) { /* zaten kopuk */ }
      this.master = this.ctx.createGain();
      this.master.gain.value = sinirla(byId('motorSes').value, 0, 100, 70) / 100;
      this.master.connect(this.ctx.destination);
    },
    klik: function (t, tur, aksan) {
      var ctx = this.ctx;
      var eller = tur === 'B' ? ['L', 'R'] : [tur || 'L'];
      eller.forEach(function (el, i) {
        var o = ctx.createOscillator();
        var g = ctx.createGain();
        o.type = el === 'L' ? 'triangle' : 'sine';
        o.frequency.value = el === 'L' ? (aksan ? 620 : 440) : (aksan ? 1120 : 820);
        g.gain.setValueAtTime((aksan ? 0.5 : 0.34) / eller.length, t + i * 0.001);
        g.gain.exponentialRampToValueAtTime(0.001, t + 0.07);
        o.connect(g);
        if (ctx.createStereoPanner) {
          var pan = ctx.createStereoPanner();
          pan.pan.value = el === 'L' ? -0.42 : 0.42;
          g.connect(pan).connect(audio.master);
        } else {
          g.connect(audio.master);
        }
        o.start(t + i * 0.001);
        o.stop(t + 0.08);
      });
    }
  };

  var protokoller = (Array.isArray(ilkVeri.protokoller) ? ilkVeri.protokoller : []).map(normalleProtokol);
  var duzenlenen = normalleProtokol({});
  var kalibrator = null;
  var calisma = {
    aktif: false, jenerasyon: 0, zamanlayici: null,
    protokol: null, plan: null, taplar: [], baslangic: 0, isBaslangic: 0,
    sonrakiHazirlik: 0, sonrakiVurus: 0, sonEslesenler: [], sonuc: null
  };

  function normalleProtokol(p) {
    p = p || {};
    return {
      id: Math.max(0, parseInt(p.id, 10) || 0),
      ad: String(p.ad || '').slice(0, 80),
      hedef: String(p.hedef || '').slice(0, 300),
      desen: motor.DESENLER[p.desen] ? p.desen : 'donusumlu',
      bpm: Math.round(sinirla(p.bpm, 30, 180, 60)),
      sureSn: Math.round(sinirla(p.sure_sn !== undefined ? p.sure_sn : p.sureSn, 15, 600, 30)),
      hazirlikVurus: Math.round(sinirla(
        p.hazirlik_vurus !== undefined ? p.hazirlik_vurus : p.hazirlikVurus, 2, 16, 4
      )),
      toleransMs: Math.round(sinirla(
        p.tolerans_ms !== undefined ? p.tolerans_ms : p.toleransMs, 60, 300, 140
      ))
    };
  }

  function durum(metin, tur) {
    var el = byId('motorKayitDurum');
    el.textContent = metin;
    el.className = 'motor-durum' + (tur ? ' ' + tur : '');
  }

  function apiGonder(veri) {
    veri.csrfToken = ilkVeri.csrfToken;
    return fetch(ilkVeri.api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(veri)
    }).then(function (yanit) {
      return yanit.json().catch(function () { return {}; }).then(function (json) {
        if (!yanit.ok || json.ok === false) { throw new Error(json.error || 'İşlem tamamlanamadı.'); }
        return json;
      });
    });
  }

  function protokolFormdanOku() {
    return normalleProtokol({
      id: duzenlenen.id,
      ad: byId('motorProtokolAd').value.trim(),
      hedef: byId('motorHedef').value.trim(),
      desen: byId('motorDesen').value,
      bpm: byId('motorBpm').value,
      sureSn: byId('motorSure').value,
      hazirlikVurus: byId('motorHazirlik').value,
      toleransMs: byId('motorTolerans').value
    });
  }

  function protokolSecenekleri(seciliId) {
    var sec = byId('motorProtokolSec');
    sec.replaceChildren();
    var bos = document.createElement('option');
    bos.value = ''; bos.textContent = '— Yeni protokol —'; sec.appendChild(bos);
    protokoller.forEach(function (p) {
      var o = document.createElement('option');
      o.value = p.id; o.textContent = p.ad + ' · ' + p.bpm + ' BPM';
      sec.appendChild(o);
    });
    sec.value = seciliId ? String(seciliId) : '';
  }

  function selectDegeriKoy(id, deger) {
    var el = byId(id);
    var varMi = Array.prototype.some.call(el.options, function (o) { return o.value === String(deger); });
    if (varMi) { el.value = String(deger); }
  }

  function protokolYukle(p) {
    duzenlenen = normalleProtokol(p || {});
    byId('motorProtokolAd').value = duzenlenen.ad;
    byId('motorHedef').value = duzenlenen.hedef;
    byId('motorDesen').value = duzenlenen.desen;
    byId('motorBpm').value = duzenlenen.bpm;
    selectDegeriKoy('motorSure', duzenlenen.sureSn);
    selectDegeriKoy('motorHazirlik', duzenlenen.hazirlikVurus);
    selectDegeriKoy('motorTolerans', duzenlenen.toleransMs);
    byId('motorProtokolSil').disabled = !duzenlenen.id;
    protokolSecenekleri(duzenlenen.id);
    oturumOzetiniGuncelle();
  }

  function oturumOzetiniGuncelle() {
    var p = protokolFormdanOku();
    var desenAd = motor.DESENLER[p.desen].ad;
    var kok = byId('motorOturumOzet');
    kok.querySelector('strong').textContent = desenAd + ' · ' + p.bpm + ' BPM';
    kok.querySelector('span').textContent =
      p.sureSn + ' sn · ' + p.hazirlikVurus + ' hazırlık · ±' + p.toleransMs + ' ms';
  }

  function kalibrasyonDurumu(mesaj) {
    var kalite = zaman.kaliteDurumu(audio.ctx);
    var kal = kalite.kalibrasyon;
    kalite.motorKullanilabilir = kalite.kullanilabilir
      && kal.tur === 'otomatik' && kal.ornek >= 8 && kal.dagilimMs <= 80;
    var rozet = byId('motorKalRozet');
    rozet.textContent = kalite.motorKullanilabilir ? kalite.etiket
      : (kalite.kullanilabilir ? 'Kalibrasyonu tekrarla' : kalite.etiket);
    rozet.className = 'motor-kal-rozet ' + (kalite.motorKullanilabilir ? 'iyi'
      : (kalite.kullanilabilir ? 'uyari' : 'eksik'));
    if (mesaj) {
      byId('motorKalAciklama').textContent = mesaj;
    } else if (kalite.motorKullanilabilir) {
      byId('motorKalAciklama').textContent =
        (kal.telafiMs >= 0 ? '+' : '') + Math.round(kal.telafiMs) +
        ' ms telafi · dağılım ±' + Math.round(kal.dagilimMs) + ' ms.';
    } else if (kalite.kullanilabilir) {
      byId('motorKalAciklama').textContent =
        'Ölçüm dağılımı ±' + Math.round(kal.dagilimMs) +
        ' ms; puanlı kullanım için en fazla ±80 ms olmalı. Daha sabit vuruşlarla tekrarlayın.';
    } else {
      byId('motorKalAciklama').textContent =
        'Puanlı ölçümden önce kullanacağınız kulaklık veya hoparlörle 12 vuruşluk kalibrasyonu tamamlayın.';
    }
    return kalite;
  }

  function kalibrasyonIptal() {
    if (kalibrator) { kalibrator.iptal(); }
    kalibrator = null;
    byId('motorKalSahne').hidden = true;
    byId('motorKalibre').disabled = false;
    audio.sustur();
  }

  function kalibrasyonBaslat() {
    if (calisma.aktif) { return; }
    audio.hazirla();
    byId('motorKalSahne').hidden = false;
    byId('motorKalibre').disabled = true;
    byId('motorKalSayac').textContent = 'Hazırlan…';
    byId('motorKalIlerleme').textContent = '0/12';
    kalibrator = zaman.kalibrasyonBaslat({
      ctx: audio.ctx,
      klik: function (t, aksan) { audio.klik(t, 'B', aksan); },
      durum: function (v) {
        byId('motorKalSayac').textContent = v.faz === 'hazirlik'
          ? v.kalan + ' · sonra her klikte vur' : 'ŞİMDİ · duyduğunda vur';
        byId('motorKalIlerleme').textContent = v.ilerleme + '/' + v.toplam;
      },
      tamam: function (sonuc) {
        kalibrator = null;
        byId('motorKalSahne').hidden = true;
        byId('motorKalibre').disabled = false;
        if (!sonuc.basarili) {
          kalibrasyonDurumu('Kalibrasyon tamamlanamadı; en az 8 geçerli vuruş gerekli. Tekrar deneyin.');
        } else if (sonuc.dagilimMs > 80) {
          kalibrasyonDurumu('Kalibrasyon kaydedildi ancak dağılım ±' + sonuc.dagilimMs +
            ' ms. Puanlı oturum açılmadı; daha sabit vuruşlarla tekrarlayın.');
        } else {
          kalibrasyonDurumu('Kalibrasyon tamamlandı: ' +
            (sonuc.telafiMs >= 0 ? '+' : '') + sonuc.telafiMs + ' ms · dağılım ±' + sonuc.dagilimMs + ' ms.');
        }
      }
    });
  }

  function gorselHedef(t, tur, sira, hazirlikKalan) {
    var gecikme = Math.max(0, (t - audio.ctx.currentTime) * 1000);
    var jenerasyon = calisma.jenerasyon;
    setTimeout(function () {
      if (!calisma.aktif || jenerasyon !== calisma.jenerasyon) { return; }
      var sol = byId('motorSolPad');
      var sag = byId('motorSagPad');
      sol.classList.remove('hedef'); sag.classList.remove('hedef');
      if (hazirlikKalan !== null) {
        byId('motorFaz').textContent = 'HAZIRLIK';
        byId('motorHedefGoster').textContent = hazirlikKalan;
        return;
      }
      byId('motorFaz').textContent = sira === 0 ? 'ŞİMDİ' : 'ÇALIŞMA';
      byId('motorHedefGoster').textContent = tur === 'B' ? 'BİRLİKTE' : (tur === 'L' ? 'SOL' : 'SAĞ');
      if (tur === 'L' || tur === 'B') { sol.classList.add('hedef'); }
      if (tur === 'R' || tur === 'B') { sag.classList.add('hedef'); }
      setTimeout(function () {
        sol.classList.remove('hedef'); sag.classList.remove('hedef');
      }, Math.min(260, calisma.plan.aralikMs * 0.55));
    }, gecikme);
  }

  function vurusTuru(sira) {
    var grupHedefleri = calisma.plan.hedefler.filter(function (h) { return h.grup === sira; });
    return grupHedefleri.length > 1 ? 'B' : grupHedefleri[0].el;
  }

  function planla() {
    if (!calisma.aktif) { return; }
    var ctx = audio.ctx;
    var ileri = ctx.currentTime + 0.12;
    var aralikSn = calisma.plan.aralikMs / 1000;
    while (calisma.sonrakiHazirlik < calisma.protokol.hazirlikVurus) {
      var ht = calisma.baslangic + calisma.sonrakiHazirlik * aralikSn;
      if (ht >= ileri) { break; }
      var kalan = calisma.protokol.hazirlikVurus - calisma.sonrakiHazirlik;
      audio.klik(ht, 'B', calisma.sonrakiHazirlik === 0);
      gorselHedef(ht, null, -1, kalan);
      calisma.sonrakiHazirlik++;
    }
    while (calisma.sonrakiVurus < calisma.plan.vurusSayisi) {
      var t = calisma.isBaslangic + calisma.sonrakiVurus * aralikSn;
      if (t >= ileri) { break; }
      var tur = vurusTuru(calisma.sonrakiVurus);
      audio.klik(t, tur, calisma.sonrakiVurus === 0);
      gorselHedef(t, tur, calisma.sonrakiVurus, null);
      calisma.sonrakiVurus++;
    }
    var simdi = ctx.currentTime;
    var bitis = calisma.isBaslangic + calisma.protokol.sureSn;
    var basladi = simdi >= calisma.isBaslangic;
    var gecen = basladi ? Math.max(0, simdi - calisma.isBaslangic) : 0;
    byId('motorKalan').textContent = sureYaz(calisma.protokol.sureSn - gecen);
    byId('motorIlerleme').style.width = Math.min(100, 100 * gecen / calisma.protokol.sureSn) + '%';
    if (simdi >= bitis) { calismayiBitir('tamamlandi', ''); }
  }

  function tapKaydet(el, olay, velocity) {
    if (!calisma.aktif || !audio.ctx) { return; }
    var tapZamani = zaman.olayZamani(audio.ctx, olay);
    if (tapZamani < calisma.isBaslangic - 0.02
        || tapZamani > calisma.isBaslangic + calisma.protokol.sureSn + 0.1) { return; }
    calisma.taplar.push({
      el: el, zaman: tapZamani, velocity: Number(velocity) || 0
    });
    var pad = el === 'L' ? byId('motorSolPad') : byId('motorSagPad');
    pad.classList.add('vuruldu');
    var canli = el === 'L' ? byId('motorSolCanli') : byId('motorSagCanli');
    canli.textContent = (Number(velocity) > 0 ? 'şiddet ' + velocity : 'vuruldu');
    setTimeout(function () {
      pad.classList.remove('vuruldu');
      canli.textContent = 'hazır';
    }, 90);
  }

  function eslestirVeHesapla(kullanilanHedefler) {
    var kal = zaman.kalibrasyonOku();
    var tumEslesenler = [];
    var tumFazla = [];
    ['L', 'R'].forEach(function (el) {
      var taps = calisma.taplar.filter(function (x) { return x.el === el; }).map(function (x) {
        return {
          el: x.el,
          zamanMs: (x.zaman - calisma.isBaslangic) * 1000,
          velocity: x.velocity
        };
      });
      var hedefler = kullanilanHedefler.filter(function (h) { return h.el === el; });
      var sonuc = motor.eslestirEl(
        taps, hedefler, calisma.protokol.toleransMs, kal.yapildi ? kal.telafiMs : 0
      );
      sonuc.eslesenler.forEach(function (x) {
        var tap = x.tap;
        tumEslesenler.push({
          hedef: x.hedef,
          sapmaMs: x.sapmaMs,
          tapZamaniMs: tap.zamanMs,
          velocity: tap.velocity || 0
        });
      });
      sonuc.fazlaTaplar.forEach(function (x) {
        tumFazla.push({ el: el, zamanMs: x.zamanMs });
      });
    });
    calisma.sonEslesenler = tumEslesenler;
    return motor.sonucHesapla({
      hedefler: kullanilanHedefler,
      eslesenler: tumEslesenler,
      fazlaTaplar: tumFazla,
      toleransMs: calisma.protokol.toleransMs
    });
  }

  function elSonucuHtml(m) {
    return '<div class="motor-el-metrikler">' +
      '<span><b>' + m.isabet + '/' + m.hedef + '</b><small>isabet</small></span>' +
      '<span><b>' + m.dogruluk + '%</b><small>doğruluk</small></span>' +
      '<span><b>' + (m.ortSapmaMs > 0 ? '+' : '') + m.ortSapmaMs + ' ms</b><small>zaman eğilimi</small></span>' +
      '<span><b>' + m.ortMutlakMs + ' ms</b><small>mutlak sapma</small></span>' +
      '<span><b>' + m.kacirilan + '</b><small>kaçırılan</small></span>' +
      '<span><b>' + m.fazla + '</b><small>fazla</small></span>' +
      '<span><b>' + (m.velocity === null ? '—' : m.velocity) + '</b><small>MIDI şiddeti</small></span>' +
      '</div>';
  }

  function sonucuGoster(sonuc, durumKodu, neden) {
    byId('motorSonuc').hidden = false;
    byId('motorSonuc').scrollIntoView({ behavior: 'smooth', block: 'start' });
    var tamam = durumKodu === 'tamamlandi';
    byId('motorSkor').textContent = tamam ? sonuc.skor : '—';
    byId('motorDogruluk').textContent = sonuc.dogruluk + '%';
    byId('motorSapma').textContent = sonuc.ortMutlakMs + ' ms';
    byId('motorAsimetri').textContent = sonuc.asimetriMs + ' ms';
    byId('motorEszaman').textContent = sonuc.eszamanlilikMs === null ? '—' : sonuc.eszamanlilikMs + ' ms';
    var yorgunlukAd = { stabil: 'Stabil', dusuyor: 'Düşüş var', iyilesiyor: 'İyileşiyor', 'veri-yok': '—' };
    byId('motorYorgunluk').textContent = yorgunlukAd[sonuc.yorgunluk.durum] || '—';
    byId('motorSolSonuc').innerHTML = elSonucuHtml(sonuc.sol);
    byId('motorSagSonuc').innerHTML = elSonucuHtml(sonuc.sag);
    byId('motorSonucAciklama').textContent = tamam
      ? 'Tamamlanan protokol · sonuçlar eğitim içi karşılaştırma içindir.'
      : (durumKodu === 'guvenlik' ? 'Güvenlik durdurması · skor üretilmedi: ' + neden : 'Erken bitirildi · skor üretilmedi.');

    var yorum = 'Sağ–sol zaman farkı ' + sonuc.asimetriMs + ' ms. ';
    if (sonuc.yorgunluk.durum === 'dusuyor') {
      yorum += 'Son bölümde hata yükseldi; uzman süreyi veya tempoyu yeniden değerlendirebilir.';
    } else if (sonuc.yorgunluk.durum === 'iyilesiyor') {
      yorum += 'Oturum ilerledikçe zamanlama daha düzenli hâle geldi.';
    } else {
      yorum += 'Oturum boyunca zamanlama eğilimi belirgin değişmedi.';
    }
    if (sonuc.sol.velocity !== null && sonuc.sag.velocity !== null) {
      yorum += ' MIDI şiddet farkı: ' + Math.abs(sonuc.sol.velocity - sonuc.sag.velocity) + '.';
    }
    byId('motorSonucYorum').textContent = yorum;

    var grafik = byId('motorHataGrafik');
    grafik.replaceChildren();
    var eslesenHarita = {};
    calisma.sonEslesenler.forEach(function (x) { eslesenHarita[x.hedef.id] = x; });
    calisma.kullanilanHedefler.forEach(function (h) {
      var x = eslesenHarita[h.id];
      var cubuk = document.createElement('span');
      cubuk.className = 'motor-hata-cubuk ' + (h.el === 'R' ? 'sag' : 'sol') + (!x ? ' kacik' : '');
      cubuk.style.height = (!x ? 100 : Math.max(4, Math.min(100, 100 * Math.abs(x.sapmaMs) / calisma.protokol.toleransMs))) + '%';
      cubuk.title = (h.el === 'L' ? 'Sol' : 'Sağ') + ' · ' + (!x ? 'kaçırıldı' : Math.round(x.sapmaMs) + ' ms');
      grafik.appendChild(cubuk);
    });
  }

  function calismayiBitir(durumKodu, neden) {
    if (!calisma.aktif) { return; }
    var simdi = audio.ctx.currentTime;
    var calisilanSn = Math.max(0, Math.min(calisma.protokol.sureSn, simdi - calisma.isBaslangic));
    calisma.aktif = false;
    calisma.jenerasyon++;
    clearInterval(calisma.zamanlayici);
    audio.sustur();
    byId('motorSahne').hidden = true;
    byId('motorAcilDurdur').disabled = true;
    byId('motorBaslat').disabled = false;
    byId('motorKalibre').disabled = false;
    var hedefSiniriMs = Math.max(0, calisilanSn * 1000 + calisma.protokol.toleransMs);
    calisma.kullanilanHedefler = calisma.plan.hedefler.filter(function (h) {
      return durumKodu === 'tamamlandi' || h.zamanMs <= hedefSiniriMs;
    });
    var sonuc = eslestirVeHesapla(calisma.kullanilanHedefler);
    calisma.sonuc = sonuc;
    sonucuGoster(sonuc, durumKodu, neden);

    var kal = zaman.kalibrasyonOku();
    var kayit = {
      islem: 'sonuc_kaydet',
      protokolId: calisma.protokol.id || null,
      ogrenciId: parseInt(byId('motorOgrenci').value, 10) || null,
      durum: durumKodu,
      skor: durumKodu === 'tamamlandi' ? sonuc.skor : null,
      dogruluk: sonuc.dogruluk,
      detay: {
        protokol: calisma.protokol,
        calisilanSn: Math.round(calisilanSn),
        durmaNedeni: neden || '',
        sonuc: sonuc,
        kalibrasyon: {
          yapildi: kal.yapildi,
          telafiMs: kal.telafiMs,
          dagilimMs: kal.dagilimMs,
          tarih: kal.tarih
        },
        zamanlamaSurumu: zaman.SURUM,
        girdi: calisma.midiKullanildi ? 'midi' : 'dokunmatik-klavye'
      }
    };
    var kayitEl = byId('motorSonucKayit');
    kayitEl.textContent = 'Kaydediliyor…';
    kayitEl.className = 'motor-sonuc-kayit';
    apiGonder(kayit).then(function (yanit) {
      kayitEl.textContent = 'Sonuç kaydedildi';
      kayitEl.className = 'motor-sonuc-kayit basarili';
      if (yanit.sonKayitlar) { gecmisiCiz(yanit.sonKayitlar); }
    }).catch(function (hata) {
      kayitEl.textContent = hata.message;
      kayitEl.className = 'motor-sonuc-kayit hata';
    });
  }

  function calismayiBaslat() {
    var kalite = kalibrasyonDurumu();
    if (!kalite.motorKullanilabilir) {
      durum('Puanlı çalışma için önce kalibrasyonu tamamlayın.', 'hata');
      byId('motorKalibre').focus();
      byId('motorKalibre').scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    if (calisma.aktif) { return; }
    kalibrasyonIptal();
    var p = protokolFormdanOku();
    p.ad = p.ad || motor.DESENLER[p.desen].ad + ' çalışması';
    calisma.protokol = p;
    calisma.plan = motor.hedefleriOlustur({ desen: p.desen, bpm: p.bpm, sureSn: p.sureSn });
    calisma.taplar = [];
    calisma.sonEslesenler = [];
    calisma.midiKullanildi = false;
    calisma.sonrakiHazirlik = 0;
    calisma.sonrakiVurus = 0;
    calisma.aktif = true;
    calisma.jenerasyon++;
    audio.hazirla();
    var aralikSn = calisma.plan.aralikMs / 1000;
    calisma.baslangic = audio.ctx.currentTime + 0.16;
    calisma.isBaslangic = calisma.baslangic + p.hazirlikVurus * aralikSn;
    calisma.plan.hedefler = calisma.plan.hedefler.map(function (h) {
      return Object.assign({}, h, { zaman: calisma.isBaslangic + h.zamanMs / 1000 });
    });
    byId('motorSonuc').hidden = true;
    byId('motorSahne').hidden = false;
    byId('motorFaz').textContent = 'HAZIRLIK';
    byId('motorHedefGoster').textContent = p.hazirlikVurus;
    byId('motorKalan').textContent = sureYaz(p.sureSn);
    byId('motorIlerleme').style.width = '0';
    byId('motorAcilDurdur').disabled = false;
    byId('motorBaslat').disabled = true;
    byId('motorKalibre').disabled = true;
    byId('motorSahne').scrollIntoView({ behavior: 'smooth', block: 'center' });
    planla();
    calisma.zamanlayici = setInterval(planla, 25);
  }

  function guvenlikDialogAc() {
    if (!calisma.aktif) { return; }
    var d = byId('motorGuvenlikDialog');
    if (typeof d.showModal === 'function') { d.showModal(); }
    else { calismayiBitir('guvenlik', 'Güvenlik nedeni'); }
  }

  function gecmisiCiz(kayitlar) {
    var kok = byId('motorGecmis');
    kok.replaceChildren();
    if (!kayitlar || !kayitlar.length) {
      var bos = document.createElement('div');
      bos.className = 'bos-durum';
      bos.textContent = 'İlk çalışma tamamlandığında sonuçlar burada görünecek.';
      kok.appendChild(bos);
      return;
    }
    var tablo = document.createElement('table');
    tablo.className = 'tablo';
    tablo.innerHTML = '<thead><tr><th>Tarih</th><th>Katılımcı</th><th>Protokol</th><th>Durum</th>' +
      '<th class="sayi">Skor</th><th class="sayi">Doğruluk</th><th class="sayi">Sağ–sol farkı</th></tr></thead>';
    var govde = document.createElement('tbody');
    kayitlar.forEach(function (k) {
      var tr = document.createElement('tr');
      var asimetri = k.detay && k.detay.sonuc ? k.detay.sonuc.asimetriMs : null;
      var durumAd = k.durum === 'tamamlandi' ? 'Tamamlandı'
        : (k.durum === 'guvenlik' ? 'Güvenlik durdurması' : 'Erken bitirildi');
      [
        String(k.created_at || '').slice(0, 16),
        k.ogrenci_kod || 'Genel',
        k.protokol_ad || (k.detay && k.detay.protokol && k.detay.protokol.ad) || 'Serbest protokol',
        durumAd,
        k.skor === null ? '—' : k.skor,
        k.dogruluk === null ? '—' : k.dogruluk + '%',
        asimetri === null ? '—' : asimetri + ' ms'
      ].forEach(function (deger, i) {
        var td = document.createElement('td');
        td.textContent = deger;
        if (i >= 4) { td.className = 'sayi'; }
        if (i === 3) {
          td.className = 'motor-durum-etiket ' + (k.durum === 'guvenlik' ? 'guvenlik' : k.durum);
        }
        tr.appendChild(td);
      });
      govde.appendChild(tr);
    });
    tablo.appendChild(govde);
    kok.appendChild(tablo);
  }

  document.querySelectorAll('#motorProtokolAd,#motorHedef,#motorDesen,#motorBpm,#motorSure,#motorHazirlik,#motorTolerans')
    .forEach(function (el) { el.addEventListener('input', oturumOzetiniGuncelle); el.addEventListener('change', oturumOzetiniGuncelle); });
  byId('motorSes').addEventListener('input', function () { audio.duzey(); });
  byId('motorProtokolSec').addEventListener('change', function () {
    var id = parseInt(this.value, 10) || 0;
    protokolYukle(protokoller.find(function (p) { return p.id === id; }) || null);
  });
  byId('motorYeni').addEventListener('click', function () { protokolYukle(null); byId('motorProtokolAd').focus(); });
  document.querySelectorAll('[data-motor-sablon]').forEach(function (b) {
    b.addEventListener('click', function () {
      var sablonlar = {
        baslangic: {
          ad: 'Başlangıç koordinasyonu', hedef: 'Rahat tempoda dönüşümlü sağ–sol el düzenini koruma.',
          desen: 'donusumlu', bpm: 50, sureSn: 30, hazirlikVurus: 4, toleransMs: 200
        },
        eszamanli: {
          ad: 'Eşzamanlı iki el', hedef: 'İki elin aynı vuruş içinde birlikte başlamasını çalışma.',
          desen: 'eszamanli', bpm: 45, sureSn: 30, hazirlikVurus: 4, toleransMs: 140
        },
        ileri: {
          ad: 'İleri karma koordinasyon', hedef: 'Tekli ve eşzamanlı vuruşlar arasında düzenli geçiş.',
          desen: 'karma', bpm: 80, sureSn: 45, hazirlikVurus: 8, toleransMs: 100
        }
      };
      protokolYukle(sablonlar[this.dataset.motorSablon]);
    });
  });
  byId('motorProtokolKaydet').addEventListener('click', function () {
    var p = protokolFormdanOku();
    if (!p.ad) { durum('Protokol adı yazın.', 'hata'); byId('motorProtokolAd').focus(); return; }
    durum('Protokol kaydediliyor…');
    apiGonder(Object.assign({ islem: 'protokol_kaydet' }, p)).then(function (yanit) {
      var kayit = normalleProtokol(yanit.protokol);
      var idx = protokoller.findIndex(function (x) { return x.id === kayit.id; });
      if (idx >= 0) { protokoller[idx] = kayit; } else { protokoller.unshift(kayit); }
      protokolYukle(kayit);
      durum('Protokol kaydedildi', 'basarili');
    }).catch(function (hata) { durum(hata.message, 'hata'); });
  });
  byId('motorProtokolSil').addEventListener('click', function () {
    if (!duzenlenen.id || !window.confirm('Bu protokol silinsin mi? Eski sonuç kayıtları korunur.')) { return; }
    var id = duzenlenen.id;
    apiGonder({ islem: 'protokol_sil', id: id }).then(function () {
      protokoller = protokoller.filter(function (p) { return p.id !== id; });
      protokolYukle(null);
      durum('Protokol silindi', 'basarili');
    }).catch(function (hata) { durum(hata.message, 'hata'); });
  });

  byId('motorKalibre').addEventListener('click', kalibrasyonBaslat);
  byId('motorKalIptal').addEventListener('click', kalibrasyonIptal);
  byId('motorKalPad').addEventListener('pointerdown', function (ev) {
    ev.preventDefault();
    if (kalibrator) { kalibrator.tap(ev); }
  });
  byId('motorBaslat').addEventListener('click', calismayiBaslat);
  byId('motorTekrar').addEventListener('click', calismayiBaslat);
  byId('motorSonucuKapat').addEventListener('click', function () { byId('motorSonuc').hidden = true; });
  byId('motorNormalDurdur').addEventListener('click', function () { calismayiBitir('erken_durduruldu', 'Kullanıcı erken bitirdi'); });
  byId('motorAcilDurdur').addEventListener('click', guvenlikDialogAc);
  byId('motorSahneAcil').addEventListener('click', guvenlikDialogAc);
  byId('motorGuvenlikDialog').querySelectorAll('[data-guvenlik-neden]').forEach(function (b) {
    b.addEventListener('click', function () {
      byId('motorGuvenlikDialog').close();
      calismayiBitir('guvenlik', this.dataset.guvenlikNeden);
    });
  });

    /*
   * Pad'ler <button>: klavyeden Enter/Space basilinca native 'click' gelir
   * ama 'pointerdown' GELMEZ — yani pad yalniz fareyle calisiyordu.
   * olay.detail === 0 = klavye kaynakli click (fare tiklamasinda >0).
   * poliritim-studyo.js'te kurulan desen buraya kopyalandi.
   */
  byId('motorSolPad').addEventListener('pointerdown', function (ev) { ev.preventDefault(); tapKaydet('L', ev, 0); });
  byId('motorSolPad').addEventListener('click', function (ev) { if (ev.detail === 0) { tapKaydet('L', ev, 0); } });
  byId('motorSagPad').addEventListener('pointerdown', function (ev) { ev.preventDefault(); tapKaydet('R', ev, 0); });
  byId('motorSagPad').addEventListener('click', function (ev) { if (ev.detail === 0) { tapKaydet('R', ev, 0); } });
  document.addEventListener('keydown', function (ev) {
    if (!calisma.aktif || ev.repeat) { return; }
    var etiket = String(ev.target.tagName || '').toLowerCase();
    if (etiket === 'input' || etiket === 'textarea' || etiket === 'select') { return; }
    if (ev.code === 'KeyF') { ev.preventDefault(); tapKaydet('L', ev, 0); }
    else if (ev.code === 'KeyJ') { ev.preventDefault(); tapKaydet('R', ev, 0); }
    else if (ev.code === 'Escape') { guvenlikDialogAc(); }
  });

  byId('motorMidiBagla').addEventListener('click', function () {
    if (!navigator.requestMIDIAccess) {
      byId('motorMidiDurum').textContent = 'Bu tarayıcı Web MIDI desteklemiyor.';
      return;
    }
    navigator.requestMIDIAccess().then(function (erisim) {
      var adet = 0;
      erisim.inputs.forEach(function (girdi) {
        adet++;
        girdi.onmidimessage = function (ev) {
          var komut = ev.data[0] & 0xf0;
          var nota = ev.data[1];
          var velocity = ev.data[2];
          if (komut !== 0x90 || velocity <= 0 || !calisma.aktif) { return; }
          calisma.midiKullanildi = true;
          tapKaydet(nota < 60 ? 'L' : 'R', ev, velocity);
        };
      });
      byId('motorMidiDurum').textContent = adet
        ? adet + ' MIDI girişi hazır · C4 bölme noktası.'
        : 'MIDI erişimi açık fakat giriş bulunamadı.';
    }).catch(function () {
      byId('motorMidiDurum').textContent = 'MIDI bağlantı izni verilmedi.';
    });
  });

  window.addEventListener('beforeunload', function (ev) {
    if (!calisma.aktif) { return; }
    ev.preventDefault();
    ev.returnValue = '';
  });
  window.addEventListener('ritim-zamanlama-guncellendi', function () { kalibrasyonDurumu(); });

  protokolSecenekleri(0);
  protokolYukle(protokoller[0] || null);
  kalibrasyonDurumu();
  gecmisiCiz(ilkVeri.sonKayitlar || []);
}());
