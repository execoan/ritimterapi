/* RitimTerapi — ortak arayüz davranışları */
(function () {
  'use strict';

  /* Mobil menü */
  var ham = document.getElementById('navHamburger');
  var nav = document.getElementById('ustNav');
  if (ham && nav) {
    ham.addEventListener('click', function () {
      var acik = nav.classList.toggle('mobil-acik');
      ham.setAttribute('aria-expanded', acik ? 'true' : 'false');
      ham.textContent = acik ? '✕' : '☰';
    });
  }

  /* Kategorili üst menü: aynı anda tek kategori açık; dışarı tıkla/Esc kapatır.
     <details> kullanıldığı için tıklama ve klavye davranışı tarayıcıdan gelir. */
  var navGruplar = Array.prototype.slice.call(document.querySelectorAll('.nav-grup'));
  if (navGruplar.length) {
    var tumunuKapat = function (haric) {
      navGruplar.forEach(function (g) { if (g !== haric) { g.open = false; } });
    };
    navGruplar.forEach(function (grup) {
      var ozet = grup.querySelector('summary');
      // Diğerlerini summary tıklamasında kapat: 'toggle' olayı eşzamansızdır ve
      // iki menünün bir an birlikte görünmesine yol açar. (Enter/Boşluk da
      // summary üzerinde click üretir; klavye de bu yolla kapsanır.)
      if (ozet) {
        ozet.addEventListener('click', function () { if (!grup.open) { tumunuKapat(grup); } });
      }
      grup.addEventListener('toggle', function () { if (grup.open) { tumunuKapat(grup); } });
    });
    document.addEventListener('click', function (ev) {
      if (!ev.target.closest || !ev.target.closest('.nav-grup')) { tumunuKapat(null); }
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Escape') { return; }
      var acik = navGruplar.filter(function (g) { return g.open; });
      if (!acik.length) { return; }
      acik.forEach(function (g) { g.open = false; });
      var ozet = acik[0].querySelector('summary');
      if (ozet) { ozet.focus(); }
    });
  }

  /*
   * Onay ve otomatik gonderim — DELEGASYONLA.
   * Satir ici on* oznitelikleri (onclick/onsubmit/onchange) CSP'de
   * script-src 'unsafe-inline' gerektirir; o bayrak acikken saklı XSS
   * dogrudan calisabilir. Bes satir ici isleyici buraya tasindi ve
   * 'unsafe-inline' CSP'den KALDIRILDI (bkz. includes/bootstrap.php).
   */
  /* Onaylı formlar: <form data-onay="Emin misiniz?"> */
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (f && f.dataset && f.dataset.onay) {
      if (!window.confirm(f.dataset.onay)) { ev.preventDefault(); }
    }
  });

  /* Onaylı düğme: <button data-onay-btn="Silinsin mi?"> */
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-onay-btn]');
    if (b && !window.confirm(b.dataset.onayBtn)) { ev.preventDefault(); }
  });

  /* Seçim değişince formu gönder: <select data-oto-gonder> */
  document.addEventListener('change', function (ev) {
    var s = ev.target;
    if (s && s.dataset && s.dataset.otoGonder !== undefined && s.form) { s.form.submit(); }
  });

  /* ---------- Ders planı ekranı ---------- */
  var planListe = document.getElementById('planListe');
  if (planListe) {
    var teknikSec = document.getElementById('teknikSec');
    var ekleBtn = document.getElementById('teknikEkleBtn');
    var sablon = document.getElementById('planSablon');
    var sureMetin = document.getElementById('sureToplam');
    var sureCubuk = document.getElementById('sureCubukDolu');
    var hedefGirdi = document.getElementById('hedefSure');
    var bosMesaj = document.getElementById('planBosMesaj');

    function hedef() {
      var h = parseInt(hedefGirdi && hedefGirdi.value, 10);
      return (h && h > 0) ? h : 60;
    }

    function topla() {
      var toplam = 0;
      planListe.querySelectorAll('input[name="sure_dk[]"]').forEach(function (g) {
        toplam += parseInt(g.value, 10) || 0;
      });
      var h = hedef();
      if (sureMetin) {
        sureMetin.textContent = toplam + ' dk / hedef ' + h + ' dk';
        sureMetin.classList.toggle('asim', toplam > h);
      }
      if (sureCubuk) {
        sureCubuk.style.width = Math.min(100, Math.round(100 * toplam / h)) + '%';
        sureCubuk.classList.toggle('asim', toplam > h);
      }
      var uyari = document.getElementById('sureUyari');
      if (uyari) { uyari.style.display = toplam > h ? '' : 'none'; }
      if (bosMesaj) { bosMesaj.style.display = planListe.children.length ? 'none' : ''; }
    }

    function ekle(opt) {
      if (!opt || !opt.value) { return; }
      var varMi = planListe.querySelector('input[name="teknik_id[]"][value="' + opt.value + '"]');
      if (varMi) {
        window.alert('Bu teknik zaten planda.');
        return;
      }
      var li = sablon.content.firstElementChild.cloneNode(true);
      li.querySelector('input[name="teknik_id[]"]').value = opt.value;
      li.querySelector('.plan-ad').textContent = opt.dataset.ad;
      li.querySelector('.plan-kategori').textContent = opt.dataset.kategori;
      var rozet = li.querySelector('.plan-kanit');
      rozet.textContent = opt.dataset.kanitEtiket;
      rozet.className = 'rozet plan-kanit rozet-kanit-' + opt.dataset.kanit;
      li.querySelector('input[name="sure_dk[]"]').value = opt.dataset.sure || 10;
      planListe.appendChild(li);
      topla();
    }

    if (ekleBtn && teknikSec) {
      ekleBtn.addEventListener('click', function () {
        ekle(teknikSec.options[teknikSec.selectedIndex]);
      });
    }

    planListe.addEventListener('click', function (ev) {
      var btn = ev.target.closest('.plan-kaldir');
      if (btn) { btn.closest('li').remove(); topla(); }
    });
    planListe.addEventListener('input', function (ev) {
      if (ev.target.name === 'sure_dk[]') { topla(); }
    });
    if (hedefGirdi) { hedefGirdi.addEventListener('input', topla); }

    /* Sürükle-bırak sıralama */
    var suruklenen = null;
    planListe.addEventListener('dragstart', function (ev) {
      var li = ev.target.closest('li');
      if (!li) { return; }
      suruklenen = li;
      li.classList.add('suruklenen');
      ev.dataTransfer.effectAllowed = 'move';
      try { ev.dataTransfer.setData('text/plain', ''); } catch (e) { /* IE uyumu */ }
    });
    planListe.addEventListener('dragend', function () {
      if (suruklenen) { suruklenen.classList.remove('suruklenen'); suruklenen = null; }
    });
    /*
     * KLAVYE SIRALAMA — surukle-birak tek yol olamaz (WCAG 2.1.1).
     * Odak tasinan ogenin butonunda KALIR ki kullanici arka arkaya basabilsin;
     * her tasimadan sonra konum sesli duyurulur.
     */
    planListe.addEventListener('click', function (ev) {
      var dugme = ev.target.closest('.plan-tasi');
      if (!dugme) { return; }
      var li = dugme.closest('li');
      var yon = parseInt(dugme.dataset.yon, 10);
      if (!li || !yon) { return; }
      if (yon < 0 && li.previousElementSibling) {
        planListe.insertBefore(li, li.previousElementSibling);
      } else if (yon > 0 && li.nextElementSibling) {
        planListe.insertBefore(li.nextElementSibling, li);
      } else {
        return;                                   // uçtaysa bir şey yapma
      }
      dugme.focus();                              // odak kaybolmasın
      topla();
      var siralar = Array.prototype.indexOf.call(planListe.children, li) + 1;
      var duyuru = document.getElementById('planSiraDuyuru');
      if (duyuru) {
        var ad = li.querySelector('.plan-ad');
        duyuru.textContent = (ad ? ad.textContent : 'Teknik') + ' — '
          + siralar + '. sıraya taşındı (' + planListe.children.length + ' teknik)';
      }
    });

    planListe.addEventListener('dragover', function (ev) {
      if (!suruklenen) { return; }
      ev.preventDefault();
      var hedefLi = ev.target.closest('li');
      if (!hedefLi || hedefLi === suruklenen) { return; }
      var kutu = hedefLi.getBoundingClientRect();
      var sonra = (ev.clientY - kutu.top) > kutu.height / 2;
      planListe.insertBefore(suruklenen, sonra ? hedefLi.nextSibling : hedefLi);
    });

    topla();
  }

  /* ---------- Genel sürükle-bırak listeler (.surukle-liste > li[draggable]) ---------- */
  document.querySelectorAll('.surukle-liste').forEach(function (liste) {
    var tasinan = null;
    liste.addEventListener('dragstart', function (ev) {
      var li = ev.target.closest('li');
      if (!li) { return; }
      tasinan = li;
      li.classList.add('suruklenen');
      ev.dataTransfer.effectAllowed = 'move';
      try { ev.dataTransfer.setData('text/plain', ''); } catch (e) { /* eski tarayıcı */ }
    });
    liste.addEventListener('dragend', function () {
      if (tasinan) { tasinan.classList.remove('suruklenen'); tasinan = null; }
    });
    liste.addEventListener('dragover', function (ev) {
      if (!tasinan) { return; }
      ev.preventDefault();
      var hedef = ev.target.closest('li');
      if (!hedef || hedef === tasinan) { return; }
      var kutu = hedef.getBoundingClientRect();
      var sonra = (ev.clientY - kutu.top) > kutu.height / 2;
      liste.insertBefore(tasinan, sonra ? hedef.nextSibling : hedef);
    });
  });

  /* ---------- Oturum kaydı ekranı ---------- */
  var tumKatildi = document.getElementById('tumKatildiBtn');
  if (tumKatildi) {
    tumKatildi.addEventListener('click', function () {
      document.querySelectorAll('input[type="radio"][value="katildi"]').forEach(function (r) {
        r.checked = true;
      });
    });
  }

  /* ---------- Yazdır butonu ---------- */
  document.querySelectorAll('[data-yazdir]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });

  /* ---------- PWA: service worker + "Uygulamayı Yükle" ---------- */
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js').catch(function () { /* yerel http kısıtı — sessiz geç */ });
  }
  var yukleBtn = document.getElementById('uygulamaYukleBtn');
  var bekleyenPrompt = null;
  window.addEventListener('beforeinstallprompt', function (ev) {
    ev.preventDefault();
    bekleyenPrompt = ev;
    if (yukleBtn) { yukleBtn.hidden = false; }
  });
  if (yukleBtn) {
    yukleBtn.addEventListener('click', function () {
      if (!bekleyenPrompt) { return; }
      bekleyenPrompt.prompt();
      bekleyenPrompt.userChoice.then(function () {
        bekleyenPrompt = null;
        yukleBtn.hidden = true;
      });
    });
  }
  window.addEventListener('appinstalled', function () {
    if (yukleBtn) { yukleBtn.hidden = true; }
  });
})();
