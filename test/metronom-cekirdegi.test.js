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

console.log('\nGeçen: ' + gecen + ' · Kalan: 0');
