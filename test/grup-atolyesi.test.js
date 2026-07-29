'use strict';

const assert = require('node:assert/strict');
const G = require('../assets/js/grup-atolyesi-cekirdegi.js');

let gecen = 0;
function test(ad, fn) {
  fn();
  gecen++;
  console.log('  ✔ ' + ad);
}

console.log('Grup atölyesi çekirdeği testi');

test('beş özgün grup akışı benzersiz kimlikle gelir', () => {
  assert.equal(G.PROTOKOLLER.length, 5);
  assert.equal(new Set(G.PROTOKOLLER.map(p => p.id)).size, 5);
});

test('bilinmeyen akış güvenli varsayılana döner', () => {
  assert.equal(G.protokolBul('yok').id, 'ortak-nabiz');
});

test('oturum adımlarının toplamı seçilen süreye eşittir', () => {
  const o = G.oturumOlustur('iki-tini', 35, 60, 'oturarak');
  assert.equal(o.adimlar.reduce((t, a) => t + a.sureSn, 0), 35 * 60);
});

test('tempo ve süre güvenli aralıkta sınırlanır', () => {
  const o = G.oturumOlustur('ortak-nabiz', 999, 999, 'oturarak');
  assert.equal(o.toplamSn, 90 * 60);
  assert.equal(o.bpm, 120);
});

test('ayakta dışında gelen mod oturarak uygulanır', () => {
  assert.equal(G.oturumOlustur('ortak-nabiz', 20, 60, 'uçuyor').mod, 'oturarak');
  assert.equal(G.oturumOlustur('ortak-nabiz', 20, 60, 'ayakta').mod, 'ayakta');
});

test('dört güvenlik koşulu tamamlanmadan hazır sayılmaz', () => {
  assert.equal(G.guvenlikHazir({ alan: true, ses: true, secim: true }), false);
  assert.equal(G.guvenlikHazir({ alan: true, ses: true, secim: true, durdurma: true }), true);
});

test('süre iki basamaklı dakika-saniye biçimindedir', () => {
  assert.equal(G.sureYaz(65), '01:05');
  assert.equal(G.sureYaz(-4), '00:00');
});

test('her akışta seçim veya güvenlik sınırı açıkça yer alır', () => {
  for (const p of G.PROTOKOLLER) {
    const metin = p.adimlar.flat().join(' ').toLocaleLowerCase('tr-TR');
    assert.ok(/seç|zorunlu|güven|mola|oturarak|temas|sıralama|dinle/.test(metin), p.ad);
  }
});

console.log('\n' + gecen + ' grup atölyesi testi geçti.');
