'use strict';

const assert = require('node:assert/strict');
const M = require('../assets/js/motor-koordinasyon.js');

let gecen = 0;
function test(ad, fn) {
  fn();
  gecen++;
  console.log('  ✔ ' + ad);
}

console.log('İki el motor koordinasyon çekirdeği testi');

test('dönüşümlü desen sol ve sağ eli sırayla üretir', () => {
  const h = M.hedefleriOlustur({ desen: 'donusumlu', bpm: 60, sureSn: 4 }).hedefler;
  assert.deepEqual(h.map(x => x.el), ['L', 'R', 'L', 'R']);
});

test('eşzamanlı desen her vuruşta iki ayrı el hedefi üretir', () => {
  const h = M.hedefleriOlustur({ desen: 'eszamanli', bpm: 60, sureSn: 2 }).hedefler;
  assert.equal(h.length, 4);
  assert.deepEqual(h.map(x => x.grup), [0, 0, 1, 1]);
  assert.ok(h.every(x => x.eszamanli));
});

test('tempo ve süre güvenli aralığa sınırlanır', () => {
  const h = M.hedefleriOlustur({ bpm: 999, sureSn: 999 });
  assert.equal(h.bpm, 180);
  assert.equal(h.sureSn, 600);
});

test('kusursuz dönüşümlü çalışmayı tam puanlar', () => {
  const hedefler = M.hedefleriOlustur({ desen: 'donusumlu', bpm: 60, sureSn: 4 }).hedefler;
  const eslesenler = hedefler.map(h => ({ hedef: h, sapmaMs: 0, tapZamaniMs: h.zamanMs }));
  const s = M.sonucHesapla({ hedefler, eslesenler, fazlaTaplar: [], toleransMs: 140 });
  assert.equal(s.skor, 100);
  assert.equal(s.dogruluk, 100);
  assert.equal(s.sol.isabet, 2);
  assert.equal(s.sag.isabet, 2);
});

test('fazla vuruş doğruluğu düşürür', () => {
  const hedefler = M.hedefleriOlustur({ desen: 'donusumlu', bpm: 60, sureSn: 4 }).hedefler;
  const eslesenler = hedefler.map(h => ({ hedef: h, sapmaMs: 0, tapZamaniMs: h.zamanMs }));
  const s = M.sonucHesapla({ hedefler, eslesenler, fazlaTaplar: [{ el: 'L' }, { el: 'R' }], toleransMs: 140 });
  assert.ok(s.dogruluk < 100);
  assert.equal(s.toplamFazla, 2);
});

test('sağ ve sol zamanlama farkını asimetri olarak raporlar', () => {
  const hedefler = M.hedefleriOlustur({ desen: 'donusumlu', bpm: 60, sureSn: 4 }).hedefler;
  const eslesenler = hedefler.map(h => ({
    hedef: h, sapmaMs: h.el === 'L' ? -30 : 50, tapZamaniMs: h.zamanMs
  }));
  const s = M.sonucHesapla({ hedefler, eslesenler, fazlaTaplar: [], toleransMs: 140 });
  assert.equal(s.asimetriMs, 80);
});

test('eşzamanlı iki el arasındaki gerçek dokunuş farkını ölçer', () => {
  const hedefler = M.hedefleriOlustur({ desen: 'eszamanli', bpm: 60, sureSn: 2 }).hedefler;
  const eslesenler = hedefler.map(h => ({
    hedef: h, sapmaMs: h.el === 'L' ? 0 : 35, tapZamaniMs: h.zamanMs + (h.el === 'L' ? 0 : 35)
  }));
  const s = M.sonucHesapla({ hedefler, eslesenler, fazlaTaplar: [], toleransMs: 140 });
  assert.equal(s.eszamanlilikMs, 35);
  assert.equal(s.eszamanliCift, 2);
});

test('son bölüm kötüleşince performans düşüşünü işaretler', () => {
  const hedefler = M.hedefleriOlustur({ desen: 'donusumlu', bpm: 60, sureSn: 9 }).hedefler;
  const eslesenler = hedefler.map((h, i) => ({
    hedef: h, sapmaMs: i < 6 ? 10 : 100, tapZamaniMs: h.zamanMs
  }));
  const s = M.sonucHesapla({ hedefler, eslesenler, fazlaTaplar: [], toleransMs: 140 });
  assert.equal(s.yorgunluk.durum, 'dusuyor');
  assert.ok(s.yorgunluk.farkMs > 0);
});

test('MIDI velocity değerlerini eller için ayrı özetler', () => {
  const hedefler = M.hedefleriOlustur({ desen: 'donusumlu', bpm: 60, sureSn: 4 }).hedefler;
  const eslesenler = hedefler.map(h => ({
    hedef: h, sapmaMs: 0, tapZamaniMs: h.zamanMs, velocity: h.el === 'L' ? 70 : 100
  }));
  const s = M.sonucHesapla({ hedefler, eslesenler, fazlaTaplar: [], toleransMs: 140 });
  assert.equal(s.sol.velocity, 70);
  assert.equal(s.sag.velocity, 100);
});

test('hızlı notalarda her dokunuşu yalnız bir hedefe eşler', () => {
  const hedefler = [
    { id: 0, zamanMs: 0, el: 'L' },
    { id: 1, zamanMs: 333, el: 'L' },
    { id: 2, zamanMs: 666, el: 'L' }
  ];
  const s = M.eslestirEl([{ zamanMs: 300 }, { zamanMs: 670 }], hedefler, 300, 0);
  assert.equal(s.eslesenler.length, 2);
  assert.equal(s.eslesenler[0].hedef.id, 1);
  assert.equal(s.eslesenler[1].hedef.id, 2);
  assert.equal(s.kacirilanHedefler[0].id, 0);
});

test('kalibrasyon telafisini dokunuş zamanından çıkarır', () => {
  const s = M.eslestirEl([{ zamanMs: 1120 }], [{ id: 0, zamanMs: 1000, el: 'L' }], 140, 120);
  assert.equal(Math.round(s.eslesenler[0].sapmaMs), 0);
  assert.equal(Math.round(s.eslesenler[0].hamSapmaMs), 120);
});

console.log('Toplam: ' + gecen + ' test geçti.');
