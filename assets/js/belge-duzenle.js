/**
 * Yazdırılabilir belgeler için ortak "Word gibi" düzenleme modu.
 * (optik-kocluk projesindeki desenin RitimTerapi uyarlaması.)
 *
 * Kullanım (HTML tarafı):
 *   <div data-belge> ... belge içeriği ... </div>
 *   ve sayfada includes/view/belge-arac-cubugu.php ile gelen düğmeler.
 *
 * ÖNEMLİ: Düzenlemeler YALNIZ bu tarayıcı görünümünde kalır ve yazdırma/PDF
 * çıktısına yansır; veritabanındaki kayıt HİÇBİR ZAMAN değişmez. Sayfa
 * yenilendiğinde veya "Orijinale Dön" ile kayıtlı hâl geri gelir.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var belge = document.querySelector('[data-belge]');
    var duzenleBtn = document.querySelector('[data-belge-duzenle]');
    if (!belge || !duzenleBtn) { return; }

    var orijinalBtn = document.querySelector('[data-belge-orijinal]');
    var yazdirBtn = document.querySelector('[data-belge-yazdir]');
    var not = document.querySelector('[data-belge-not]');

    var orijinalHTML = belge.innerHTML;
    var duzenleEtiketi = duzenleBtn.textContent;
    var duzenleme = false;
    var degisti = false;

    function moduAyarla(acik) {
      duzenleme = acik;
      belge.setAttribute('contenteditable', acik ? 'true' : 'false');
      belge.classList.toggle('belge-duzenleniyor', acik);
      duzenleBtn.textContent = acik ? '✔ Düzenlemeyi Bitir' : duzenleEtiketi;
      if (not) { not.classList.toggle('gizli', !acik); }
      if (acik) { belge.focus(); }
    }

    duzenleBtn.addEventListener('click', function () { moduAyarla(!duzenleme); });

    belge.addEventListener('input', function () {
      degisti = true;
      if (orijinalBtn) { orijinalBtn.classList.remove('gizli'); }
    });

    // Panodan gelen içerik DÜZ METİN olarak yapışsın: dış stil/HTML taşınmaz.
    belge.addEventListener('paste', function (ev) {
      if (!duzenleme) { return; }
      ev.preventDefault();
      var metin = (ev.clipboardData || window.clipboardData).getData('text/plain');
      if (metin) { document.execCommand('insertText', false, metin); }
    });

    if (orijinalBtn) {
      orijinalBtn.addEventListener('click', function () {
        if (!degisti || window.confirm('Bu ekranda yaptığınız tüm düzenlemeler atılacak ve belgenin kayıtlı hâli geri gelecek. Devam edilsin mi?')) {
          belge.innerHTML = orijinalHTML;
          degisti = false;
          orijinalBtn.classList.add('gizli');
          moduAyarla(false);
        }
      });
    }

    if (yazdirBtn) {
      yazdirBtn.addEventListener('click', function () {
        if (duzenleme) { moduAyarla(false); } // imleç/çerçeve çıktıya yansımasın
        window.print();
      });
    }
  });
})();
