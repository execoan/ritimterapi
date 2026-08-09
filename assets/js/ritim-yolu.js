/**
 * Ritim Yolu sayfası — laboratuvar kayıtlarından yol görünümü TÜRETİR.
 *
 * Yıldız kuralı ders düğümü için: tamamlanan alıştırma sayısına göre
 * (16 üzerinden) ★≥4 · ★★≥10 · ★★★≥14. Kilit zinciri YolCekirdegi'nden.
 * Yol ayrı ilerleme kaydetmez; laboratuvarın 'ritim_okuma_tamam_v3_{s}'
 * listesi tek kaynaktır (id biçimi: R{seviye}-{ders}-{örnek}).
 */
(function () {
  'use strict';
  var yol = window.YolCekirdegi;
  var ro = window.RitimOkuma;
  var kap = document.getElementById('yolBolumler');
  if (!yol || !ro || !kap) { return; }

  var YILDIZ_ESIK = [4, 10, 14];   // 16 alıştırma üzerinden

  function tamamlananlar(seviye) {
    try {
      var v = JSON.parse(localStorage.getItem('ritim_okuma_tamam_v3_' + seviye));
      return Array.isArray(v) ? v : [];
    } catch (e) { return []; }
  }

  function dersYildizi(seviye, dersNo, tamamListesi) {
    var onek = 'R' + seviye + '-' + dersNo + '-';
    var adet = tamamListesi.filter(function (id) {
      return typeof id === 'string' && id.indexOf(onek) === 0;
    }).length;
    var y = 0;
    for (var i = 0; i < YILDIZ_ESIK.length; i++) { if (adet >= YILDIZ_ESIK[i]) { y = i + 1; } }
    return { yildiz: y, adet: adet };
  }

  var SEVIYE_ADI = { 1: 'Kolay — temel değerler', 2: 'Orta — onaltılık ve senkop', 3: 'Zor — üçleme ve karma' };

  /* 24 düğümlük düz adım listesi + türetilmiş durum */
  var adimlar = [];
  var durum = {};
  var dersBilgisi = {};
  [1, 2, 3].forEach(function (seviye) {
    var tamam = tamamlananlar(seviye);
    ro.dersListesi(seviye).forEach(function (ders) {
      var id = 'R' + seviye + '-D' + ders.no;
      adimlar.push({ id: id });
      var sonuc = dersYildizi(seviye, ders.no, tamam);
      if (sonuc.yildiz > 0) { durum[id] = { yildiz: sonuc.yildiz }; }
      dersBilgisi[id] = { seviye: seviye, no: ders.no, ad: ders.ad, adet: sonuc.adet };
    });
  });

  var gorunum = yol.yolGorunumu(adimlar, durum);
  var gorunumHarita = {};
  gorunum.forEach(function (g) { gorunumHarita[g.id] = g; });

  function yildizMetni(y) {
    var dolu = '★★★'.slice(0, y);
    var bos = '★★★'.slice(y);
    return dolu + '<span class="bos">' + bos + '</span>';
  }

  var toplamBiten = gorunum.filter(function (g) { return g.yildiz > 0; }).length;
  var ozetEl = document.getElementById('yolOzet');
  if (ozetEl) {
    ozetEl.innerHTML = toplamBiten + '/24<small>ders tamamlandı</small>';
  }

  [1, 2, 3].forEach(function (seviye, bIdx) {
    var bolum = document.createElement('section');
    bolum.className = 'yol-bolum';
    var dersler = ro.dersListesi(seviye);
    var biten = dersler.filter(function (d) {
      var g = gorunumHarita['R' + seviye + '-D' + d.no];
      return g && g.yildiz > 0;
    }).length;

    bolum.innerHTML =
      '<div class="yol-bolum-baslik">' +
      '  <span class="yol-bolum-no" aria-hidden="true">' + (bIdx + 1) + '</span>' +
      '  <h3>Seviye ' + seviye + ' · ' + SEVIYE_ADI[seviye] + '</h3>' +
      '  <span class="yol-bolum-ozet">' + biten + '/' + dersler.length + ' ders</span>' +
      '</div>' +
      '<div class="yol-adimlar"></div>';
    var dizi = bolum.querySelector('.yol-adimlar');

    dersler.forEach(function (ders) {
      var id = 'R' + seviye + '-D' + ders.no;
      var g = gorunumHarita[id];
      var bilgi = dersBilgisi[id];
      var etiket = 'Ders ' + ders.no + ': ' + ders.ad + ' — '
        + (g.acik ? (g.yildiz + ' yıldız, ' + bilgi.adet + '/16 alıştırma') : 'kilitli');
      if (g.acik) {
        var a = document.createElement('a');
        a.className = 'yol-adim' + (g.siradaki ? ' siradaki' : '');
        a.href = 'ritim-okuma.php?seviye=' + seviye + '&ders=' + ders.no;
        a.setAttribute('aria-label', etiket);
        a.innerHTML =
          '<span class="yol-adim-no">DERS ' + ders.no + ' · ' + bilgi.adet + '/16</span>' +
          '<span class="yol-adim-ad">' + ders.ad + '</span>' +
          '<span class="yol-adim-yildiz" aria-hidden="true">' + yildizMetni(g.yildiz) + '</span>';
        dizi.appendChild(a);
      } else {
        var d = document.createElement('div');
        d.className = 'yol-adim kilitli';
        d.setAttribute('aria-label', etiket);
        d.innerHTML =
          '<span class="yol-adim-no">DERS ' + ders.no + '</span>' +
          '<span class="yol-adim-ad">' + ders.ad + '</span>' +
          '<span class="yol-adim-yildiz" aria-hidden="true">' + yildizMetni(0) + '</span>';
        dizi.appendChild(d);
      }
    });
    kap.appendChild(bolum);
  });
})();
