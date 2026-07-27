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

  /* Onaylı formlar: <form data-onay="Emin misiniz?"> */
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (f && f.dataset && f.dataset.onay) {
      if (!window.confirm(f.dataset.onay)) { ev.preventDefault(); }
    }
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
