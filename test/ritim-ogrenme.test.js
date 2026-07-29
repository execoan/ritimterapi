'use strict';

const assert = require('node:assert/strict');
const O = require('../assets/js/ritim-ogrenme.js');

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

const T0 = Date.UTC(2026, 6, 29, 12, 0, 0);
const katalog = [
  { id: 'R1-1-1', dersNo: 0 },
  { id: 'R1-1-2', dersNo: 0 },
  { id: 'R1-1-3', dersNo: 0 },
  { id: 'R1-2-1', dersNo: 1 }
];

console.log('Adaptif ritim öğrenme çekirdeği testi');

test('ilk yüksek puanı güven payıyla ustalığa dönüştürür', () => {
  const d = O.denemeyiIsle(O.bosDurum(), { id: 'R1-1-1', skor: 100 }, T0);
  assert.equal(d.ornekler['R1-1-1'].ustalik, 85);
  assert.equal(d.ornekler['R1-1-1'].deneme, 1);
  assert.equal(d.basariSerisi, 1);
});

test('tekrarlanan temiz okumada güven ve aralık büyür', () => {
  let d = O.denemeyiIsle(O.bosDurum(), { id: 'R1-1-1', skor: 100 }, T0);
  d = O.denemeyiIsle(d, { id: 'R1-1-1', skor: 100 }, T0 + 1000);
  assert.equal(d.ornekler['R1-1-1'].ustalik, 95);
  assert.equal(d.ornekler['R1-1-1'].temizSeri, 2);
  assert.equal(d.ornekler['R1-1-1'].tekrarZamani, T0 + 1000 + 3 * O.GUN_MS);
  assert.equal(d.ornekler['R1-1-1'].tekrarAdimi, 0);
});

test('başarısız okuma seriyi keser ve iki çalışma sonrasına kuyruklar', () => {
  let d = O.denemeyiIsle(O.bosDurum(), { id: 'R1-1-1', skor: 95 }, T0);
  d = O.denemeyiIsle(d, { id: 'R1-1-1', skor: 40 }, T0 + 1000);
  assert.equal(d.basariSerisi, 0);
  assert.equal(d.ornekler['R1-1-1'].temizSeri, 0);
  assert.equal(d.ornekler['R1-1-1'].tekrarAdimi, d.adim + 2);
  assert.ok(d.ornekler['R1-1-1'].ustalik < 70);
});

test('tekrar adımı gelince yeni örnekten önce zayıf örneği seçer', () => {
  let d = O.denemeyiIsle(O.bosDurum(), { id: 'R1-1-1', skor: 35 }, T0);
  d = O.denemeyiIsle(d, { id: 'R1-1-2', skor: 90 }, T0 + 1000);
  d = O.denemeyiIsle(d, { id: 'R1-1-3', skor: 90 }, T0 + 2000);
  const secim = O.sonrakiniSec(katalog, d, 'R1-1-3', T0 + 2000);
  assert.equal(secim.id, 'R1-1-1');
  assert.equal(secim.tur, 'tekrar');
});

test('tekrar yoksa aynı dersteki görülmemiş örneği sırayla verir', () => {
  const secim = O.sonrakiniSec(katalog, O.bosDurum(), 'R1-1-1', T0);
  assert.equal(secim.id, 'R1-1-2');
  assert.equal(secim.tur, 'yeni');
});

test('nota türü isabetlerini ayrı öğrenir ve zayıf türü raporlar', () => {
  const d = O.denemeyiIsle(O.bosDurum(), {
    id: 'R1-1-1',
    skor: 70,
    tipSonuclari: {
      q: { toplam: 4, dogru: 4 },
      ee: { toplam: 4, dogru: 1 }
    }
  }, T0);
  const zayif = O.zayifTipler(d, 3);
  assert.equal(zayif[0].kod, 'ee');
  assert.equal(zayif[0].dogruluk, 25);
  assert.equal(d.tipler.q.ustalik, 85);
});

test('özet yalnız iki doğrulanmış temiz okumayı ustalaşıldı sayar', () => {
  let d = O.denemeyiIsle(O.bosDurum(), { id: 'R1-1-1', skor: 100 }, T0);
  let ozet = O.ozet(katalog, d, T0);
  assert.equal(ozet.ustalasilan, 0);
  d = O.denemeyiIsle(d, { id: 'R1-1-1', skor: 100 }, T0 + 1000);
  ozet = O.ozet(katalog, d, T0 + 1000);
  assert.equal(ozet.ustalasilan, 1);
  assert.equal(ozet.calisilanUstaligi, 95);
  assert.equal(ozet.kursYuzdesi, 24);
});

test('eski tamamlanan kayıtları kaybetmeden inceleme kuyruğuna aktarır', () => {
  const d = O.tamamlananlariAktar(O.bosDurum(), ['R1-1-1'], T0);
  assert.equal(d.ornekler['R1-1-1'].sonSkor, 75);
  assert.equal(d.ornekler['R1-1-1'].ustalik, 64);
  assert.equal(O.ozet(katalog, d, T0).tekrar, 1);
});

test('bozuk depolama verisini güvenli sınırlara çeker', () => {
  const d = O.normalle({
    adim: -20,
    basariSerisi: 'x',
    ornekler: { A: { deneme: -3, ustalik: 900, sonSkor: -1 } }
  });
  assert.equal(d.adim, 0);
  assert.equal(d.basariSerisi, 0);
  assert.equal(d.ornekler.A.deneme, 0);
  assert.equal(d.ornekler.A.ustalik, 100);
  assert.equal(d.ornekler.A.sonSkor, 0);
});

console.log('\nGeçen: ' + gecen + ' · Kalan: 0');
