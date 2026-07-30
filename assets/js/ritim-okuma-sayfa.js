/* ================================================================
   Ritim Okuma Laboratuvarı — sayfa sürücüsü.

   Widget'ın kendisi ritim-okuma.js'te (metronom.php ve ev.php de aynı
   widget'ı kullanır). Bu dosya yalnız SAYFAYA ait olanı yapar: ayar
   kutuları, standart ölçüm kilidi, kayıt formu ve klavye.

   NEDEN AYRI SAYFA: metronom.php'de iki modül aynı Space tuşunu istiyordu
   ve alıştırma sürerken metronom açılıyordu. Burada Space'in tek sahibi var.
   ================================================================ */
(function () {
  'use strict';

  var zaman = window.RitimZamanlama;
  var kok = document.getElementById('roKok');
  if (!zaman || !kok || !window.RitimOkuma) { return; }

  function byId(id) { return document.getElementById(id); }

  /* Kalibrasyon kalitesi için AudioContext gerekir; widget kendi bağlamını
     kurar ama bize vermez. Ayrı ve hafif bir bağlam yalnız kalite okumak
     için açılır (ses çalmaz), yoksa kalite alanı boş kalır — kayıt yine
     geçerli, sadece "kalibrasyonsuz" süzmesi yapılamaz. */
  var kaliteCtx = null;
  function kaliteKodu() {
    try {
      if (!kaliteCtx) {
        var AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) { return ''; }
        kaliteCtx = new AC();
      }
      var k = zaman.kaliteDurumu(kaliteCtx);
      return (k && k.kod) || '';
    } catch (e) { return ''; }
  }

  /** Standart koşul: seviye 1 · 60 BPM · her vuruş kılavuzlu. */
  function standartKosulMu() {
    return parseInt(byId('roSeviye').value, 10) === 1
        && parseInt(byId('roBpm').value, 10) === 60
        && byId('roRehber').value === 'tam';
  }

  function kur() {
    byId('roKaydetSatir').hidden = true;
    window.RitimOkuma.baslat(kok, {
      seviye: parseInt(byId('roSeviye').value, 10),
      bpm: parseInt(byId('roBpm').value, 10),
      rehber: byId('roRehber').value,
      onBitti: function (sonuc) {
        byId('roFormBpm').value = sonuc.bpm;
        byId('roFormSkor').value = sonuc.skor;
        byId('roFormDetay').value = JSON.stringify(sonuc);
        /* 📏 işareti toggle'dan DEĞİL gerçek koşullardan türetilir */
        byId('roFormStandart').value = standartKosulMu() ? '1' : '0';
        byId('roFormSd').value = Number.isFinite(sonuc.sapmaSdMs) ? sonuc.sapmaSdMs : '';
        byId('roFormKalite').value = kaliteKodu();
        byId('roKaydetSatir').hidden = false;
      }
    });
  }

  kur();
  byId('roYenile').addEventListener('click', function () {
    if (kok.__roYeni) { kok.__roYeni(1); }
  });
  ['roSeviye', 'roBpm', 'roRehber'].forEach(function (id) {
    byId(id).addEventListener('change', kur);
  });

  /* ---------------- Standart ölçüm kilidi ---------------- */
  var STD = { roSeviye: '1', roBpm: '60', roRehber: 'tam' };
  var stdOnceki = {};
  var stdKutu = byId('roStdMod');
  if (stdKutu) {
    stdKutu.addEventListener('change', function () {
      var degisti = false;
      Object.keys(STD).forEach(function (id) {
        var el = byId(id);
        if (!el) { return; }
        if (stdKutu.checked) {
          stdOnceki[id] = el.value;
          if (el.value !== STD[id]) { el.value = STD[id]; degisti = true; }
          el.disabled = true;
        } else {
          el.disabled = false;
          if (stdOnceki[id] !== undefined && el.value !== stdOnceki[id]) {
            el.value = stdOnceki[id];
            degisti = true;
          }
        }
      });
      if (degisti) { kur(); }
    });
  }

  /* ---------------- Kayıt formu ---------------- */
  byId('roForm').addEventListener('submit', function (ev) {
    var secim = byId('roOgrenci').value;
    if (!secim) {
      ev.preventDefault();
      window.alert('Kaydetmek için önce öğrenci seçin.');
      return;
    }
    byId('roFormOgrenci').value = secim;
  });

  /* ================================================================
     KLAVYE
     Süzgeç metronom.js ile AYNI: yalnız input/select/textarea hariç.
     BUTTON HARİÇ TUTULMAZ — bu bilinçli: .ro-pad bir <button> ve tek
     dinleyicisi 'pointerdown'. Klavyeden gelen native click pointerdown
     üretmediği için, button'ı hariç tutmak odak pad'e geldiğinde vuruşu
     sessizce düşürür (ya da odak Durdur'daysa alıştırmayı iptal eder).
     ================================================================ */
  document.addEventListener('keydown', function (ev) {
    if (ev.code !== 'Space' || ev.repeat) { return; }
    var etiket = (ev.target && ev.target.tagName || '').toLowerCase();
    if (etiket === 'input' || etiket === 'select' || etiket === 'textarea') { return; }
    /* Yalnız widget etkinken araya gir; boştayken butonlar normal çalışsın
       ("Sonucu Kaydet", "Sonraki örnek" gibi). */
    if (!kok.__roMesgul || !kok.__roMesgul()) { return; }
    ev.preventDefault();
    if (kok.__roAktif && kok.__roAktif() && kok.__roTap) { kok.__roTap(ev); }
    /* Dinleme/hazırlık fazında Space yalnız YUTULUR: vuruş değil, ama
       sayfayı kaydırmasına da izin verilmez. */
  });
})();
