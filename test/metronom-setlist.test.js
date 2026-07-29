'use strict';

const assert = require('node:assert/strict');
const S = require('../assets/js/metronom-setlist.js');

let gecen = 0;
function test(ad, fn) {
  fn();
  gecen++;
  console.log('  ✔ ' + ad);
}

console.log('Metronom setlist çekirdeği testi');

test('boş adımı profesyonel varsayılanlarla kurar', () => {
  const a = S.adimNormalle({}, 1);
  assert.equal(a.baslik, 'Adım 2');
  assert.equal(a.bpm, 92);
  assert.equal(a.sureSn, 300);
  assert.deepEqual(a.desen, [2, 1, 1, 1]);
});

test('tempo, swing ve süreyi güvenli aralığa sınırlar', () => {
  const a = S.adimNormalle({ bpm: 900, swing: 10, sureSn: 2 });
  assert.equal(a.bpm, 240);
  assert.equal(a.swing, 50);
  assert.equal(a.sureSn, 15);
});

test('geçersiz poliritim 1 değerini kapalıya çevirir', () => {
  assert.equal(S.adimNormalle({ poliritim: 1 }).poliritim, 0);
  assert.equal(S.adimNormalle({ poliritim: 9 }).poliritim, 9);
});

test('aksan desenini ölçü uzunluğuna tamamlar', () => {
  assert.deepEqual(S.adimNormalle({ olcu: 5, desen: [2, 0] }).desen, [2, 0, 1, 1, 1]);
});

test('adımları yukarı ve aşağı taşır, girdiyi değiştirmez', () => {
  const a = [{ baslik: 'A' }, { baslik: 'B' }, { baslik: 'C' }];
  const b = S.adimTasi(a, 2, 0);
  assert.deepEqual(b.map(x => x.baslik), ['C', 'A', 'B']);
  assert.deepEqual(a.map(x => x.baslik), ['A', 'B', 'C']);
});

test('geçersiz taşıma diziyi aynı sırada bırakır', () => {
  assert.deepEqual(S.adimTasi([1, 2], 0, 8), [1, 2]);
});

test('toplam süreyi saniye cinsinden hesaplar', () => {
  assert.equal(S.toplamSure([{ sureSn: 60 }, { sureSn: 90 }]), 150);
});

test('süreyi dakika ve saat biçiminde yazar', () => {
  assert.equal(S.sureYaz(65), '01:05');
  assert.equal(S.sureYaz(3665), '1:01:05');
});

console.log('Toplam: ' + gecen + ' test geçti.');
