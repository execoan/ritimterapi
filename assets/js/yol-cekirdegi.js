/**
 * YOL ÇEKİRDEĞİ — ilerlemeli öğrenme yolunun saf kuralları.
 *
 * Ritim Yolu ve Kulak Yolu aynı kuralları paylaşır: adımlar sırayla açılır,
 * her adım skora göre 0-3 yıldız alır, bir sonraki adım ancak öncekinden
 * en az bir yıldız alınınca açılır. DOM/ses yok — Node ile birim test edilir.
 *
 * Dil kuralı (CLAUDE.md §2 + ev programı ilkesi): yarışmasız, teşvik edici.
 * Madalya/lig/sıralama YOK; yıldız yalnız kişinin kendi ilerlemesini gösterir.
 */
(function (kok, kur) {
  if (typeof module === 'object' && module.exports) { module.exports = kur(); }
  else { kok.YolCekirdegi = kur(); }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var SURUM = 1;

  /* Yıldız eşikleri ŞANS DÜZELTMELİ skora göre okunur (bkz. sansDuzeltmeli):
     iki seçenekli bir soruda para atan biri %50 tutturur; ham yüzdeye yıldız
     vermek "hiç dinlemeden iki yıldız" demek olurdu. */
  var YILDIZ_ESIKLERI = [50, 70, 90];

  function yildizHesapla(skor) {
    skor = Number(skor);
    if (!isFinite(skor)) { return 0; }
    var y = 0;
    for (var i = 0; i < YILDIZ_ESIKLERI.length; i++) {
      if (skor >= YILDIZ_ESIKLERI[i]) { y = i + 1; }
    }
    return y;
  }

  /**
   * Şans düzeltmeli skor (0-100). k = seçenek sayısı.
   * p = dogru/toplam; düzeltilmiş = (p - 1/k) / (1 - 1/k).
   * Rastgele işaretleyen 0 alır; k büyüdükçe düzeltme küçülür.
   */
  function sansDuzeltmeli(dogru, toplam, secenekSayisi) {
    toplam = Math.max(0, Math.round(Number(toplam) || 0));
    if (toplam === 0) { return 0; }
    var k = Math.max(2, Math.round(Number(secenekSayisi) || 2));
    dogru = Math.max(0, Math.min(toplam, Math.round(Number(dogru) || 0)));
    var p = dogru / toplam;
    var duz = (p - 1 / k) / (1 - 1 / k);
    return Math.max(0, Math.min(100, Math.round(100 * duz)));
  }

  /**
   * Adım durum haritasından kilit/yıldız görünümünü çıkarır.
   * @param adimlar  [{id}, ...] — kataloğun düz adım listesi (sıralı)
   * @param durum    {adimId: {yildiz, enIyi, deneme}} — kalıcı ilerleme
   * @return [{id, yildiz, acik, siradaki}] aynı sırada
   */
  function yolGorunumu(adimlar, durum) {
    durum = durum || {};
    var sonuc = [];
    var oncekiYildiz = 1;          // ilk adım her zaman açık
    var siradakiVerildi = false;
    for (var i = 0; i < adimlar.length; i++) {
      var kayit = durum[adimlar[i].id] || {};
      var yildiz = Math.max(0, Math.min(3, Math.round(Number(kayit.yildiz) || 0)));
      var acik = oncekiYildiz >= 1;
      var siradaki = acik && yildiz === 0 && !siradakiVerildi;
      if (siradaki) { siradakiVerildi = true; }
      sonuc.push({ id: adimlar[i].id, yildiz: yildiz, acik: acik, siradaki: siradaki });
      oncekiYildiz = yildiz;
    }
    return sonuc;
  }

  /** Adım sonucunu duruma işler; en iyi skor ve yıldız asla GERİLEMEZ. */
  function sonucIsle(durum, adimId, skor) {
    durum = durum || {};
    var eski = durum[adimId] || { yildiz: 0, enIyi: 0, deneme: 0 };
    var yeniYildiz = yildizHesapla(skor);
    durum[adimId] = {
      yildiz: Math.max(eski.yildiz | 0, yeniYildiz),
      enIyi: Math.max(eski.enIyi | 0, Math.round(Number(skor) || 0)),
      deneme: (eski.deneme | 0) + 1
    };
    return durum;
  }

  /** Bölüm özetleri: toplam/açılan/yıldızlı adım sayıları. */
  function bolumOzeti(bolumler, durum) {
    var duz = [];
    bolumler.forEach(function (b) { duz = duz.concat(b.adimlar); });
    var gorunum = yolGorunumu(duz, durum);
    var haritada = {};
    gorunum.forEach(function (g) { haritada[g.id] = g; });
    return bolumler.map(function (b) {
      var biten = 0, toplamYildiz = 0;
      b.adimlar.forEach(function (a) {
        var g = haritada[a.id];
        if (g && g.yildiz > 0) { biten++; }
        toplamYildiz += g ? g.yildiz : 0;
      });
      return { ad: b.ad, toplam: b.adimlar.length, biten: biten, yildiz: toplamYildiz };
    });
  }

  return {
    SURUM: SURUM,
    YILDIZ_ESIKLERI: YILDIZ_ESIKLERI,
    yildizHesapla: yildizHesapla,
    sansDuzeltmeli: sansDuzeltmeli,
    yolGorunumu: yolGorunumu,
    sonucIsle: sonucIsle,
    bolumOzeti: bolumOzeti
  };
}));
