'use strict';

/*
 * Kalın–İnce Tını kalıp çekirdeği birim testi.
 *
 * Asıl amaç: kalıp setinin EKSİKSİZLİĞİNİ kalıcı kılmak. Belgedeki set
 * matematiksel olarak tam (A = 2² , B+C = 2³) ve bu tesadüf değil. Liste elle
 * düzenlenirken bir varyant düşerse ya da iki kez yazılırsa kimse fark etmez —
 * bu test eder.
 */

const assert = require('node:assert/strict');
const T = require('../assets/js/tini-cekirdegi.js');

let gecen = 0;
function test(ad, fn) {
  try { fn(); gecen++; console.log('  ✔ ' + ad); }
  catch (hata) { console.error('  ✘ ' + ad); throw hata; }
}
function yakin(a, b, esik = 1e-9) {
  assert.ok(Math.abs(a - b) <= esik, `${a} ≠ ${b}`);
}

console.log('Kalın–İnce Tını çekirdeği testi\n');

console.log('— Kademe yapısı —');
test('üç kademe var ve kodları A/B/C', () => {
  assert.deepEqual(T.KADEMELER.map((k) => k.kod), ['A', 'B', 'C']);
});

test('her kademede 4 kalıp var', () => {
  T.KADEMELER.forEach((k) => {
    assert.equal(k.kaliplar.length, 4, k.kod + ' kademesinde 4 kalıp olmalı');
  });
});

test('kalıp uzunlukları kademenin olay sayısıyla uyuşuyor', () => {
  T.KADEMELER.forEach((k) => {
    k.kaliplar.forEach((kalip) => {
      assert.equal(kalip.length, k.olay, k.kod + ': ' + kalip.join('') + ' uzunluğu ' + k.olay + ' olmalı');
    });
  });
});

test('yalnız k ve i simgeleri kullanılıyor', () => {
  T.tumKaliplar().forEach((kalip) => {
    kalip.forEach((s) => assert.ok(s === 'k' || s === 'i', 'geçersiz simge: ' + s));
  });
});

console.log('\n— EKSİKSİZLİK (setin matematiksel bütünlüğü) —');
test('A kademesi 2 olayın TÜM 4 kombinasyonunu kapsıyor', () => {
  const d = T.eksiksizMi(T.kaliplar('A'), 2);
  assert.ok(d.eksiksiz, `eksik: ${d.eksikler.join(', ')} | tekrar: ${d.tekrarlar.join(', ')}`);
  assert.equal(d.beklenen, 4);
  assert.equal(d.bulunan, 4);
});

test('B + C birlikte 3 olayın TÜM 8 kombinasyonunu kapsıyor', () => {
  const bc = T.kaliplar('B').concat(T.kaliplar('C'));
  const d = T.eksiksizMi(bc, 3);
  assert.ok(d.eksiksiz, `eksik: ${d.eksikler.join(', ')} | tekrar: ${d.tekrarlar.join(', ')}`);
  assert.equal(d.beklenen, 8);
  assert.equal(d.bulunan, 8);
});

test('B ve C birbiriyle ÇAKIŞMIYOR (aynı kalıp iki kademede yok)', () => {
  const b = T.kaliplar('B').map((x) => x.join(''));
  const c = T.kaliplar('C').map((x) => x.join(''));
  const ortak = b.filter((x) => c.includes(x));
  assert.deepEqual(ortak, [], 'iki kademede tekrarlanan kalıp: ' + ortak.join(', '));
});

test('eksiksizMi eksik bir seti YAKALAR (test kendini sınıyor)', () => {
  const eksik = T.kaliplar('A').slice(0, 3);          // bir varyant düşürüldü
  const d = T.eksiksizMi(eksik, 2);
  assert.equal(d.eksiksiz, false);
  assert.equal(d.eksikler.length, 1);
});

test('eksiksizMi tekrarı YAKALAR', () => {
  const tekrarli = T.kaliplar('A').slice(0, 3).concat([T.kaliplar('A')[0]]);
  const d = T.eksiksizMi(tekrarli, 2);
  assert.equal(d.eksiksiz, false);
  assert.ok(d.tekrarlar.length >= 1);
});

console.log('\n— olasiKaliplar —');
test('2 uzunlukta 4, 3 uzunlukta 8 kalıp üretir', () => {
  assert.equal(T.olasiKaliplar(2).length, 4);
  assert.equal(T.olasiKaliplar(3).length, 8);
  assert.equal(T.olasiKaliplar(4).length, 16);
});

test('ürettiği kalıpların hepsi benzersiz', () => {
  const hepsi = T.olasiKaliplar(3).map((x) => x.join(''));
  assert.equal(new Set(hepsi).size, hepsi.length);
});

console.log('\n— Zamanlama —');
test('vuruşlar eşit aralıklı ve tempoyla uyumlu', () => {
  const z = T.zamanlama(['k', 'i', 'k'], 60);          // 60 BPM → 1 sn ara
  yakin(z.araSn, 1);
  assert.deepEqual(z.olaylar.map((o) => o.t), [0, 1, 2]);
  assert.deepEqual(z.olaylar.map((o) => o.tini), ['k', 'i', 'k']);
});

test('tempo değişince aralık orantılı değişir', () => {
  const z = T.zamanlama(['k', 'k'], 120);
  yakin(z.araSn, 0.5);
  yakin(z.sureSn, 1);
});

test('BPM uç değerleri sınırlanır (bölme hatası olmaz)', () => {
  [0, -50, 5, 9999, NaN, null].forEach((bpm) => {
    const z = T.zamanlama(['k', 'i'], bpm);
    assert.ok(Number.isFinite(z.araSn) && z.araSn > 0, 'bpm ' + bpm + ' → ara ' + z.araSn);
  });
});

test('boş kalıpta güvenli sonuç', () => {
  const z = T.zamanlama([], 72);
  assert.deepEqual(z.olaylar, []);
  assert.equal(z.sureSn, 0);
});

console.log('\n— Grup dağılımı (etkinliğin iki gruplu formatı) —');
test('karma kalıpta iki grup da çalar', () => {
  const g = T.grupDagilimi(['k', 'i', 'k']);
  assert.deepEqual(g.kalinSiralar, [0, 2]);
  assert.deepEqual(g.inceSiralar, [1]);
  assert.equal(g.tekGrupMu, false);
});

test('tek tınılı kalıpta öteki grup susar — eğitmen bunu görmeli', () => {
  const hepsiKalin = T.grupDagilimi(['k', 'k', 'k']);
  assert.equal(hepsiKalin.tekGrupMu, true);
  assert.equal(hepsiKalin.inceAdet, 0);
  const hepsiInce = T.grupDagilimi(['i', 'i', 'i']);
  assert.equal(hepsiInce.tekGrupMu, true);
  assert.equal(hepsiInce.kalinAdet, 0);
});

test('her kademede en az bir tek-gruplu kalıp var (susma da çalışılıyor)', () => {
  const tekli = T.tumKaliplar().filter((k) => T.grupDagilimi(k).tekGrupMu);
  assert.ok(tekli.length >= 3, 'bulunan: ' + tekli.length);
});

console.log('\n— Okunur metin (yazdırma ve ekran okuyucu) —');
test('kalıp Türkçe okunur metne çevriliyor', () => {
  assert.equal(T.okunurMetin(['k', 'i', 'k']), 'kalın – ince – kalın');
  assert.equal(T.okunurMetin([]), '');
});

console.log('\n=================================');
console.log(`  Geçen: ${gecen}   Kalan: 0`);
console.log('=================================');
