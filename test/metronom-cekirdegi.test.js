'use strict';

const assert = require('node:assert/strict');
const M = require('../assets/js/metronom-cekirdegi.js');

let gecen = 0;
function test(ad, fn) {
  try {
    fn();
    gecen++;
    console.log('  ✔ ' + ad);
  } catch (hata) {
    console.error('  ✘ ' + ad);
    throw hata;
  }
}

function yakinDizi(gercek, beklenen, esik = 1e-8) {
  assert.equal(gercek.length, beklenen.length);
  gercek.forEach((x, i) => assert.ok(Math.abs(x - beklenen[i]) <= esik, `${i}: ${x} ≠ ${beklenen[i]}`));
}

console.log('Profesyonel metronom çekirdeği testi');

test('düz sekizlikleri eşit aralığa yerleştirir', () => {
  yakinDizi(M.altVurusOfsetleri(2, 50), [0, 0.5]);
});

test('üçleme swing sekizliğin ikinci notasını 2/3 konumuna taşır', () => {
  yakinDizi(M.altVurusOfsetleri(2, 66.6667), [0, 2 / 3], 1e-5);
});

test('onaltılık swing her yarım vuruş çiftine ayrı uygulanır', () => {
  yakinDizi(M.altVurusOfsetleri(4, 66.6667), [0, 1 / 3, 0.5, 5 / 6], 1e-5);
});

test('üçleme alt bölünmesi swing ayarından etkilenmez', () => {
  yakinDizi(M.altVurusOfsetleri(3, 75), [0, 1 / 3, 2 / 3]);
});

test('swing güvenli yüzde aralığına sınırlanır', () => {
  yakinDizi(M.altVurusOfsetleri(2, 10), [0, 0.5]);
  yakinDizi(M.altVurusOfsetleri(2, 90), [0, 0.75]);
});

test('7/8 için üç temel aksak gruplamayı sunar', () => {
  assert.deepEqual(M.gruplamaSecenekleri(7, 8).slice(0, 3), ['2+2+3', '2+3+2', '3+2+2']);
});

test('3+2 grubu doğru vuruşlara aksan koyar', () => {
  assert.deepEqual(M.gruplamaDeseni(5, '3+2'), [2, 1, 1, 2, 1]);
});

test('3+3+3+2 grubu 11/8 desenini eksiksiz üretir', () => {
  assert.deepEqual(M.gruplamaDeseni(11, '3+3+3+2'), [2, 1, 1, 2, 1, 1, 2, 1, 1, 2, 1]);
});

test('toplamı ölçüye uymayan grubu tek ölçü aksanına düşürür', () => {
  assert.deepEqual(M.gruplamaCoz(7, '2+2'), [7]);
  assert.deepEqual(M.gruplamaDeseni(7, '2+2'), [2, 1, 1, 1, 1, 1, 1]);
});

test('iki ölçülük giriş fazını vuruş vuruş raporlar', () => {
  const ilk = M.girisBilgisi(0, 4, 2);
  const sonGiris = M.girisBilgisi(7, 4, 2);
  const baslangic = M.girisBilgisi(8, 4, 2);
  assert.equal(ilk.giriste, true);
  assert.equal(ilk.kalanOlcu, 2);
  assert.equal(sonGiris.giriste, true);
  assert.equal(sonGiris.kalanVurus, 1);
  assert.equal(baslangic.giriste, false);
  assert.equal(baslangic.calismaVurusNo, 0);
  assert.equal(baslangic.olcuIcindeki, 0);
});

test('giriş kapalıyken ilk vuruş doğrudan çalışma başlangıcıdır', () => {
  const bilgi = M.girisBilgisi(0, 7, 0);
  assert.equal(bilgi.giriste, false);
  assert.equal(bilgi.toplamVurus, 0);
  assert.equal(bilgi.calismaVurusNo, 0);
});

/* ================================================================
   SKORLAMA — 0-100 ölçekler
   Ağustos 2026 sayısal denetiminde üçünde de ölçek sorunu bulundu.
   Bu testler o sorunların geri gelmesini engeller.
   ================================================================ */

test('aksak skoru şans düzeyini çıkarır (2 seçenekli test)', () => {
  /* Para atarak ortalama 4/8 tutturulur; bu "beceri gösterilmedi" demektir.
     Eski formül buna 50 puan veriyor ve grafikte Vuruş Tutturma'nın
     50'siyle yan yana duruyordu. */
  assert.equal(M.aksakSkoru(4, 8), 0);
  assert.equal(M.aksakSkoru(0, 8), 0);
  assert.equal(M.aksakSkoru(2, 8), 0);
  assert.equal(M.aksakSkoru(6, 8), 50);
  assert.equal(M.aksakSkoru(7, 8), 75);
  assert.equal(M.aksakSkoru(8, 8), 100);
});

test('aksak skoru artan doğru sayısıyla azalmaz', () => {
  let onceki = -1;
  for (let d = 0; d <= 8; d++) {
    const s = M.aksakSkoru(d, 8);
    assert.ok(s >= onceki, d + '/8 skoru düştü');
    onceki = s;
  }
});

test('aksak skoru bozuk girdide çökmez', () => {
  assert.equal(M.aksakSkoru(99, 8), 100);   // toplamı aşan doğru kırpılır
  assert.equal(M.aksakSkoru(-3, 8), 0);
  assert.equal(M.aksakSkoru(1, 0), 0);      // sıfır tur → 1'e yükseltilir
  assert.equal(M.aksakSkoru(NaN, NaN), 0);
});

test('spontan skoru tipik yetişkini tavana yapıştırmaz', () => {
  /* Literatürde yetişkin kendiliğinden vuruş CV'si ~%2-5. Eski eşik
     0,02 idi: tipik yetişkin ölçümün TAVANINDA başlıyor, gelişme
     görünmüyordu. Artık %2-5 bandı orta-üst alanda ve ayırt edici. */
  const cv2 = M.spontanSkoru(0.02);
  const cv5 = M.spontanSkoru(0.05);
  assert.ok(cv2 < 100, 'CV %2 tavana yapışmamalı, bulunan: ' + cv2);
  assert.ok(cv2 - cv5 >= 15, 'CV %2 ile %5 arasında ayrım zayıf: ' + cv2 + ' vs ' + cv5);
});

test('spontan skoru monoton azalır ve uçlarda kırpılır', () => {
  let onceki = 101;
  for (const cv of [0.005, 0.01, 0.03, 0.05, 0.08, 0.12, 0.15, 0.20]) {
    const s = M.spontanSkoru(cv);
    assert.ok(s <= onceki, 'CV ' + cv + ' skoru arttı');
    assert.ok(s >= 0 && s <= 100, 'CV ' + cv + ' skoru aralık dışı: ' + s);
    onceki = s;
  }
  assert.equal(M.spontanSkoru(M.ST_TAVAN_CV), 100);
  assert.equal(M.spontanSkoru(M.ST_TABAN_CV), 0);
  assert.equal(M.spontanSkoru(0), 100);
  assert.equal(M.spontanSkoru(-1), 0);       // anlamsız girdi
  assert.equal(M.spontanSkoru(Infinity), 0);
});

test('vuruş penceresi yavaş tempoda kırpılır, hızlı tempoda orantılı kalır', () => {
  /* 72 BPM'de aralık 833 ms; 0,30 katı ±250 ms olurdu — oysa yetişkinde
     eşzamanlama sapması tipik 20-50 ms. Kırpma olmadan herkes 87-97'ye
     sıkışıyor, ölçeğin %90'ı kullanılmıyordu. */
  assert.equal(M.vurusPenceresi(60000 / 72), M.VT_PENCERE_TAVAN_MS);
  assert.equal(M.vurusPenceresi(60000 / 60), M.VT_PENCERE_TAVAN_MS);
  /* 200 BPM → aralık 300 ms → 0,30 katı 90 ms; tavanın altında, orantılı kalır */
  assert.ok(Math.abs(M.vurusPenceresi(300) - 90) < 0.001);
  assert.ok(M.vurusPenceresi(0) > 0, 'sıfır aralıkta sıfıra bölme olmamalı');
});

test('vuruş skoru gerçekçi yetenek aralığını ayırt eder', () => {
  /* Tam isabet, fazla vuruş yok; yalnız ortalama |sapma| değişiyor.
     Denetimde ölçülen eski davranış: 8 ms → 97, 72 ms → 70 (27 puan).
     Yeni ölçekte aynı aralık belirgin biçimde açılmalı. */
  const A = 60000 / 72, N = 16;
  const iyi  = M.vurusSkoru(8,  N, N, N, A);   // SD ~10 ms
  const orta = M.vurusSkoru(32, N, N, N, A);   // SD ~40 ms
  const zayif = M.vurusSkoru(72, N, N, N, A);  // SD ~90 ms
  assert.ok(iyi > orta && orta > zayif, 'sıralama bozuk: ' + [iyi, orta, zayif]);
  assert.ok(iyi - zayif >= 50,
    'ölçek hâlâ sıkışık — iyi ile zayıf arası ' + (iyi - zayif) + ' puan');
});

test('vuruş skoru kaçırma ve fazla vuruşu cezalandırır', () => {
  const A = 60000 / 72, N = 16;
  const tam = M.vurusSkoru(20, N, N, N, A);
  const yarimIsabet = M.vurusSkoru(20, N / 2, N, N / 2, A);   // yarısını kaçırdı
  const fazlaVurus = M.vurusSkoru(20, N, N, N * 2, A);        // iki katı dokundu
  assert.ok(yarimIsabet < tam, 'kaçırma cezalandırılmıyor');
  assert.ok(fazlaVurus < tam, 'fazla vuruş cezalandırılmıyor');
  assert.equal(M.vurusSkoru(20, 0, N, N, A), 0, 'hiç isabet yoksa 0 olmalı');
});

test('vuruş skoru sınırların dışına taşmaz', () => {
  const A = 60000 / 72, N = 16;
  for (const sapma of [0, 1, 50, 200, 5000, -10, NaN]) {
    const s = M.vurusSkoru(sapma, N, N, N, A);
    assert.ok(s >= 0 && s <= 100, 'sapma ' + sapma + ' → ' + s);
  }
  assert.equal(M.vurusSkoru(0, N, N, N, A), 100, 'kusursuz zamanlama 100 olmalı');
});

console.log('\nGeçen: ' + gecen + ' · Kalan: 0');
