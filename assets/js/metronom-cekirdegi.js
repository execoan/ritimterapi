/* ================================================================
   Profesyonel Metronom Hesaplama Çekirdeği
   - Swing/shuffle alt bölünme ofsetleri
   - Bileşik ve aksak ölçü grupları
   - Sayarak giriş fazı
   Tarayıcı + Node.js için ortak, saf ve deterministik API
   ================================================================ */
(function (kok, fabrika) {
  'use strict';
  var api = fabrika();
  if (typeof module === 'object' && module.exports) { module.exports = api; }
  if (kok) { kok.MetronomCekirdegi = api; }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  var SURUM = 1;
  var GRUPLAMALAR = {
    '2/4': ['2'],
    '3/4': ['3'],
    '4/4': ['4', '2+2'],
    '5/4': ['3+2', '2+3', '5'],
    '6/8': ['3+3', '2+2+2', '6'],
    '7/8': ['2+2+3', '2+3+2', '3+2+2', '7'],
    '8/8': ['3+3+2', '3+2+3', '2+3+3', '4+4', '8'],
    '9/8': ['3+3+3', '2+2+2+3', '9'],
    '11/8': ['3+3+3+2', '3+2+3+3', '2+3+3+3', '11'],
    '12/8': ['3+3+3+3', '4+4+4', '12']
  };

  function sinirla(sayi, alt, ust) {
    sayi = Number(sayi);
    if (!Number.isFinite(sayi)) { sayi = alt; }
    return Math.max(alt, Math.min(ust, sayi));
  }

  function olcuAnahtari(pay, payda) {
    pay = Math.max(1, Math.round(Number(pay) || 4));
    payda = Number(payda) === 8 ? 8 : 4;
    return pay + '/' + payda;
  }

  function gruplamaSecenekleri(pay, payda) {
    var anahtar = olcuAnahtari(pay, payda);
    return (GRUPLAMALAR[anahtar] || [String(Math.max(1, Math.round(Number(pay) || 4)))]).slice();
  }

  function gruplamaCoz(pay, ifade) {
    pay = Math.max(1, Math.round(Number(pay) || 4));
    var parcalar = String(ifade || '').split('+').map(function (x) {
      return Math.round(Number(x));
    });
    if (!parcalar.length
      || parcalar.some(function (x) { return !Number.isFinite(x) || x <= 0; })
      || parcalar.reduce(function (t, x) { return t + x; }, 0) !== pay) {
      return [pay];
    }
    return parcalar;
  }

  function gruplamaDeseni(pay, ifade) {
    pay = Math.max(1, Math.round(Number(pay) || 4));
    var gruplar = gruplamaCoz(pay, ifade);
    var desen = Array.from({ length: pay }, function () { return 1; });
    var konum = 0;
    gruplar.forEach(function (uzunluk) {
      desen[konum] = 2;
      konum += uzunluk;
    });
    return desen;
  }

  /*
   * Swing yüzdesi, ikilinin ilk notasının toplam vuruş içindeki payıdır:
   * 50 = düz, 60 = hafif, 66.7 = klasik üçleme shuffle, 75 = sert.
   * Onaltılıkta oran her yarım vuruştaki ikiliye ayrı uygulanır.
   */
  function altVurusOfsetleri(altBolunme, swingYuzde) {
    var alt = [1, 2, 3, 4].indexOf(Number(altBolunme)) >= 0 ? Number(altBolunme) : 1;
    var swing = sinirla(swingYuzde === undefined ? 50 : swingYuzde, 50, 75) / 100;
    if (alt === 1) { return [0]; }
    if (alt === 2) { return [0, swing]; }
    if (alt === 3) { return [0, 1 / 3, 2 / 3]; }
    return [0, swing / 2, 0.5, 0.5 + swing / 2];
  }

  function girisBilgisi(vurusNo, olcuAdedi, girisOlcu) {
    vurusNo = Math.max(0, Math.round(Number(vurusNo) || 0));
    olcuAdedi = Math.max(1, Math.round(Number(olcuAdedi) || 4));
    girisOlcu = Math.max(0, Math.min(4, Math.round(Number(girisOlcu) || 0)));
    var toplam = olcuAdedi * girisOlcu;
    var giriste = vurusNo < toplam;
    var kalan = giriste ? toplam - vurusNo : 0;
    return {
      giriste: giriste,
      toplamVurus: toplam,
      kalanVurus: kalan,
      kalanOlcu: giriste ? Math.ceil(kalan / olcuAdedi) : 0,
      olcuIcindeki: vurusNo % olcuAdedi,
      calismaVurusNo: giriste ? -1 : vurusNo - toplam
    };
  }

  /* ================================================================
     SKORLAMA — 0-100 ölçekler
     ================================================================
     Buradaki üç fonksiyon metronom.js'in içindeydi ve sınanamıyordu.
     Sayısal denetimde (Ağu 2026) üçünde de ölçek sorunu çıktı; hepsi
     düzeltildi ve buraya, birim testin gördüğü yere taşındı.

     ORTAK KURAL: 0 = "ölçülebilir beceri gösterilmedi", 100 = "bu araçla
     ayırt edilebilen en iyi başarım". Farklı protokollerin skorları aynı
     grafikte yan yana çizildiği için ortak tabanları olmak ZORUNDA.
     ================================================================ */

  /**
   * Aksak Bulma — İKİ SEÇENEKLİ zorunlu seçim (aksak / düzenli).
   *
   * Eski formül doğrudan yüzde alıyordu: hiç becerisi olmayan biri madenî
   * para atarak ortalama 50 alıyor, 8 turda %14 olasılıkla 75+ çıkıyordu.
   * Aynı grafikte Vuruş Tutturma'nın 50'siyle yan yana durunca yanıltıcı.
   * Şans düzeyi çıkarılır: p=0,50 → 0 · p=0,75 → 50 · p=1,00 → 100.
   */
  function aksakSkoru(dogru, toplam) {
    toplam = Math.round(Number(toplam) || 0);
    /* Sıfır tur = beceri kanıtı yok. Toplamı 1'e yükseltip bölmek,
       hiç yapılmamış bir testten 100 puan çıkarabiliyordu. */
    if (toplam <= 0) { return 0; }
    dogru = Math.max(0, Math.min(toplam, Math.round(Number(dogru) || 0)));
    var p = dogru / toplam;
    return Math.max(0, Math.min(100, Math.round(100 * (2 * p - 1))));
  }

  /**
   * Spontan Tempo — kendiliğinden vuruşun düzenliliği (CV).
   *
   * Eski eşikler 0,02–0,12 idi: literatürde yetişkin kendiliğinden vuruş
   * CV'si ~%2-5, yani tipik bir yetişkin ÖLÇÜMÜN TAVANINDA başlıyor ve
   * gelişme hiç görünmüyordu. Aralık gerçek dağılımı kapsayacak biçimde
   * genişletildi: CV %1 → 100, %15 → 0. Çocukta tipik ~%8-12 olduğu için
   * orta bant artık çocuklar için de ayırt edici.
   */
  var ST_TAVAN_CV = 0.01;
  var ST_TABAN_CV = 0.15;
  function spontanSkoru(cv) {
    cv = Number(cv);
    if (!isFinite(cv) || cv < 0) { return 0; }
    var oran = (cv - ST_TAVAN_CV) / (ST_TABAN_CV - ST_TAVAN_CV);
    return Math.max(0, Math.min(100, Math.round(100 * (1 - oran))));
  }

  /**
   * Vuruş Tutturma değerlendirme penceresi.
   *
   * Eskiden pencere hep 0,30 × vuruş aralığıydı. 72 BPM'de bu ±250 ms
   * demek; oysa yetişkinde eşzamanlama sapması tipik 20-50 ms, çocukta
   * 40-80 ms. Sonuç: herkes 87-97 arasına sıkışıyor, ölçeğin %90'ı
   * kullanılmıyor ve 20 ms → 10 ms gibi GERÇEK bir gelişme 3 puan
   * ediyordu (gürültü bandının çok altında, yani görünmez).
   *
   * Oran kuralı hızlı tempoda korunur (Weber: değişkenlik aralıkla
   * ölçeklenir), yavaş tempoda 120 ms'de kırpılır. Aynı kırpma deseni
   * poliritim çekirdeğinde de var (etkinPencere) — tutarlı.
   */
  var VT_PENCERE_TAVAN_MS = 120;
  function vurusPenceresi(aralikMs) {
    var oransal = 0.30 * Math.max(1, Number(aralikMs) || 0);
    return Math.min(oransal, VT_PENCERE_TAVAN_MS);
  }

  /**
   * Vuruş Tutturma faz skoru.
   * @param ortMutlakMs ortalama |sapma|
   * @param vurulanAdet pencereye giren AYRI hedef sayısı
   * @param hedefAdet   fazdaki hedef sayısı
   * @param denemeAdet  toplam dokunuş (fazla vuruş cezası için)
   */
  function vurusSkoru(ortMutlakMs, vurulanAdet, hedefAdet, denemeAdet, aralikMs) {
    hedefAdet = Math.max(1, Number(hedefAdet) || 0);
    vurulanAdet = Math.max(0, Math.min(hedefAdet, Number(vurulanAdet) || 0));
    if (!vurulanAdet) { return 0; }
    var pencere = vurusPenceresi(aralikMs);
    var hamSkor = Math.max(0, 1 - Math.max(0, Number(ortMutlakMs) || 0) / pencere);
    var isabet = vurulanAdet / hedefAdet;
    var dogruluk = vurulanAdet / Math.max(1, Number(denemeAdet) || 0);
    return Math.round(100 * hamSkor * isabet * dogruluk);
  }

  return {
    SURUM: SURUM,
    GRUPLAMALAR: GRUPLAMALAR,
    olcuAnahtari: olcuAnahtari,
    gruplamaSecenekleri: gruplamaSecenekleri,
    gruplamaCoz: gruplamaCoz,
    gruplamaDeseni: gruplamaDeseni,
    altVurusOfsetleri: altVurusOfsetleri,
    girisBilgisi: girisBilgisi,
    ST_TAVAN_CV: ST_TAVAN_CV,
    ST_TABAN_CV: ST_TABAN_CV,
    VT_PENCERE_TAVAN_MS: VT_PENCERE_TAVAN_MS,
    aksakSkoru: aksakSkoru,
    spontanSkoru: spontanSkoru,
    vurusPenceresi: vurusPenceresi,
    vurusSkoru: vurusSkoru
  };
});
