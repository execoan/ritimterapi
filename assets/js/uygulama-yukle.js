/**
 * Uygulama olarak kurma şeridi — herkese açık yüzeylerde.
 *
 * Neden ayrı bir modül: kurulum akışı üç farklı yola ayrılıyor ve bu üç yol
 * tek bir düğmeyle anlatılamıyor.
 *
 *  1. Chrome / Edge / Android — tarayıcı `beforeinstallprompt` olayı verir,
 *     tek dokunuşla kurulur.
 *  2. iOS Safari — böyle bir API YOK. Kullanıcının Paylaş menüsünden
 *     "Ana Ekrana Ekle" demesi gerekir; tek yapabileceğimiz tarif etmek.
 *  3. Zaten kurulu — şerit hiç görünmemeli, yoksa her açılışta rahatsız eder.
 *
 * Şerit kapatılınca localStorage'a yazılır ve bir daha gösterilmez; kurulum
 * bir öneridir, ısrar değil.
 */
(function () {
  'use strict';

  var ANAHTAR = 'ritim-uygulama-serit-kapali';
  var serit = document.getElementById('uygulamaSerit');
  if (!serit) { return; }

  var metinEl = serit.querySelector('.uyg-serit-metin');
  var kurBtn  = serit.querySelector('.uyg-serit-kur');
  var kapaBtn = serit.querySelector('.uyg-serit-kapat');
  var yardim  = serit.querySelector('.uyg-serit-yardim');

  /** Uygulama olarak mı açıldı? (kurulu olanı bir daha davet etme) */
  function kuruluMu() {
    return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
        || window.navigator.standalone === true;
  }

  /** iOS Safari: beforeinstallprompt yok, elle tarif gerekir. */
  function iosMu() {
    var ua = window.navigator.userAgent || '';
    var iCihaz = /iPad|iPhone|iPod/.test(ua)
      /* iPadOS 13+ kendini Mac gibi tanıtır; dokunma desteğiyle ayrılır. */
      || (/Macintosh/.test(ua) && typeof document.ontouchend !== 'undefined');
    /* Chrome/Firefox iOS'ta da WebKit kullanır ama "Ana Ekrana Ekle"yi
       yalnız Safari sunar; yanlış tarif vermemek için onları eliyoruz. */
    return iCihaz && !/CriOS|FxiOS|EdgiOS/.test(ua);
  }

  function kapaliMi() {
    try { return localStorage.getItem(ANAHTAR) === '1'; } catch (e) { return false; }
  }
  function kapat() {
    serit.hidden = true;
    try { localStorage.setItem(ANAHTAR, '1'); } catch (e) {}
  }

  if (kuruluMu() || kapaliMi()) { return; }

  if (kapaBtn) { kapaBtn.addEventListener('click', kapat); }

  if (iosMu()) {
    /* iOS: düğme değil tarif göster — tıklanınca hiçbir şey olmayan bir
       "Kur" düğmesi koymak kullanıcıyı yanıltır. */
    if (kurBtn) { kurBtn.hidden = true; }
    if (metinEl) {
      metinEl.innerHTML = 'Bunu telefonuna <strong>uygulama olarak</strong> ekleyebilirsin: '
        + 'aşağıdaki <strong>Paylaş</strong> düğmesine dokun, '
        + '<strong>“Ana Ekrana Ekle”</strong>yi seç.';
    }
    if (yardim) { yardim.hidden = false; }
    serit.hidden = false;
    return;
  }

  /* Chrome/Edge/Android yolu: olay gelmeden şerit gösterilmez, çünkü
     tarayıcı kurulumu desteklemiyorsa çalışmayan bir düğme kalır. */
  var beklemedekiOlay = null;
  window.addEventListener('beforeinstallprompt', function (ev) {
    ev.preventDefault();
    beklemedekiOlay = ev;
    serit.hidden = false;
  });

  if (kurBtn) {
    kurBtn.addEventListener('click', function () {
      if (!beklemedekiOlay) { return; }
      beklemedekiOlay.prompt();
      beklemedekiOlay.userChoice.then(function (sonuc) {
        /* Reddedilirse ısrar edilmez; kabul edilirse zaten kurulu sayılır. */
        if (sonuc && sonuc.outcome === 'accepted') { kapat(); }
        beklemedekiOlay = null;
      });
    });
  }

  window.addEventListener('appinstalled', kapat);
})();
