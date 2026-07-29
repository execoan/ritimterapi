'use strict';

const assert = require('node:assert/strict');
const Z = require('../assets/js/zamanlama-cekirdegi.js');

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

console.log('Ortak zamanlama çekirdeği testi');

test('olay zamanını handler gecikmesinden arındırır', () => {
  const ctx = { currentTime: 10 };
  assert.equal(Z.olayZamani(ctx, { timeStamp: 980 }, 1000), 9.98);
  assert.equal(Z.olayZamani(ctx, { timeStamp: 100 }, 1000), 10);
});

test('her dokunuş ve hedef yalnız bir kez kullanılır', () => {
  const s = Z.eslestir([1.01, 1.02], [{ zaman: 1 }], { esikSn: 0.1 });
  assert.equal(s.eslesenler.length, 1);
  assert.equal(s.fazlaTaplar.length, 1);
  assert.equal(s.kacirilanHedefler.length, 0);
});

test('pencere dışındaki dokunuş fazla olarak korunur', () => {
  const s = Z.eslestir([0.1, 1.01, 4], [{ zaman: 1 }], { esikSn: 0.1 });
  assert.equal(s.eslesenler.length, 1);
  assert.equal(s.fazlaTaplar.length, 2);
});

test('kaçırılan hedefleri ayrı raporlar', () => {
  const s = Z.eslestir([1.01], [{ zaman: 1 }, { zaman: 2 }], { esikSn: 0.1 });
  assert.equal(s.eslesenler.length, 1);
  assert.equal(s.kacirilanHedefler.length, 1);
  assert.equal(s.kacirilanHedefler[0].hedefIdx, 1);
});

test('kronolojik sıra hızlı notalarda korunur', () => {
  const hedefler = [1, 1.125, 1.25, 1.375].map(zaman => ({ zaman }));
  const s = Z.eslestir([1.12, 1.255, 1.37], hedefler, { esikSn: 0.08 });
  assert.deepEqual(s.eslesenler.map(x => x.hedefIdx), [1, 2, 3]);
});

test('en çok eşleşme, sonra en küçük toplam sapma seçilir', () => {
  const s = Z.eslestir([1.04, 1.09], [{ zaman: 1 }, { zaman: 1.1 }], { esikSn: 0.1 });
  assert.equal(s.eslesenler.length, 2);
  assert.deepEqual(s.eslesenler.map(x => x.hedefIdx), [0, 1]);
});

test('kalibrasyon telafisi eşleşmeye uygulanır', () => {
  const s = Z.eslestir([1.12], [{ zaman: 1 }], { esikSn: 0.05, telafiMs: 120 });
  assert.equal(s.eslesenler.length, 1);
  assert.ok(Math.abs(s.eslesenler[0].sapmaMs) < 0.0001);
  assert.ok(Math.abs(s.eslesenler[0].hamSapmaMs - 120) < 0.0001);
});

test('hedefe özel pencere desteklenir', () => {
  const hedefler = [{ zaman: 1, ilk: true }, { zaman: 2, ilk: false }];
  const s = Z.eslestir([1.14, 2.14], hedefler, {
    esikSn: hedef => hedef.ilk ? 0.16 : 0.1
  });
  assert.equal(s.eslesenler.length, 1);
  assert.equal(s.eslesenler[0].hedefIdx, 0);
});

test('faz filtresi hazırlık vuruşlarını dışarıda bırakır', () => {
  const hedefler = [{ zaman: 1, faz: 0 }, { zaman: 2, faz: 1 }];
  const s = Z.eslestir([1, 2], hedefler, {
    esikSn: 0.1,
    hedefUygun: hedef => hedef.faz === 1
  });
  assert.equal(s.eslesenler.length, 1);
  assert.equal(s.eslesenler[0].hedefIdx, 1);
  assert.equal(s.fazlaTaplar.length, 1);
});

test('standart sapma kararlılığı ölçer, sabit kaymadan etkilenmez', () => {
  // Sabit kayma (cihaz gecikmesi) eklemek dağılımı değiştirmemeli:
  // dönem karşılaştırmasının SD'den okunmasının gerekçesi budur.
  const sapmalar = [-20, -10, 0, 10, 20];
  const kaymali = sapmalar.map(x => x + 45);
  assert.ok(Math.abs(Z.standartSapma(sapmalar) - Z.standartSapma(kaymali)) < 1e-9);
  // n-1 bölmeli örneklem SD'si: [-20,-10,0,10,20] için tam olarak √250
  assert.ok(Math.abs(Z.standartSapma(sapmalar) - Math.sqrt(250)) < 1e-9);
  assert.equal(Z.standartSapma([7]), 0, 'tek örnekte SD tanımsız → 0');
  assert.equal(Z.standartSapma([]), 0, 'boş dizide 0');
  assert.equal(Z.standartSapma([5, 5, 5, 5]), 0, 'kusursuz kararlılıkta 0');
});

test('kalibrasyon medyanı tekil kötü vuruştan etkilenmez', () => {
  const hedefler = Array.from({ length: 12 }, (_, i) => 1 + i);
  const taplar = hedefler.map((x, i) => x + (i === 5 ? 0.32 : 0.08));
  const s = Z.kalibrasyonHesapla(hedefler, taplar);
  assert.equal(s.basarili, true);
  assert.equal(s.telafiMs, 80);
  assert.equal(s.dagilimMs, 0);
});

test('sekizden az kalibrasyon örneğini reddeder', () => {
  const hedefler = Array.from({ length: 12 }, (_, i) => 1 + i);
  const s = Z.kalibrasyonHesapla(hedefler, hedefler.slice(0, 7));
  assert.equal(s.basarili, false);
  assert.equal(s.ornek, 7);
});

test('kalibrasyon ayarı diğer tercihleri bozmadan saklanır', () => {
  const veri = new Map([[Z.AYAR_ANAHTARI, JSON.stringify({ profil: 'dengeli' })]]);
  const depo = {
    getItem: k => veri.get(k) || null,
    setItem: (k, v) => veri.set(k, v)
  };
  Z.kalibrasyonKaydet({ telafiMs: 45, dagilimMs: 18, ornek: 12 }, {
    sampleRate: 48000, baseLatency: 0.01, outputLatency: 0.02
  }, depo);
  const ham = JSON.parse(veri.get(Z.AYAR_ANAHTARI));
  assert.equal(ham.profil, 'dengeli');
  assert.equal(ham.kalibrasyon.telafiMs, 45);
  assert.match(ham.kalibrasyon.cihazImzasi, /^48000\|/);
});

test('elle telafiyi ölçülmüş kalite gibi göstermez', () => {
  const veri = new Map([[Z.AYAR_ANAHTARI, JSON.stringify({
    kalibrasyon: {
      yapildi: true, telafiMs: 0, dagilimMs: 0, ornek: 0,
      tarih: new Date().toISOString(), tur: 'elle'
    }
  })]]);
  const depo = { getItem: k => veri.get(k) || null };
  const kalite = Z.kaliteDurumu(null, depo);
  assert.equal(kalite.kod, 'elle');
  assert.equal(kalite.kullanilabilir, true);
  assert.equal(kalite.yenilemeOnerilir, true);
});

test('500 rastgele senaryoda sıra ve bire birlik değişmez', () => {
  let seed = 123456789;
  const rnd = () => {
    seed = (1664525 * seed + 1013904223) >>> 0;
    return seed / 4294967296;
  };
  for (let tur = 0; tur < 500; tur++) {
    const hedefler = Array.from({ length: 16 }, (_, i) => ({ zaman: 1 + i * 0.125 }));
    const taplar = [];
    hedefler.forEach(h => {
      if (rnd() > 0.12) { taplar.push(h.zaman + (rnd() - 0.5) * 0.1); }
      if (rnd() < 0.08) { taplar.push(h.zaman + (rnd() - 0.5) * 0.06); }
    });
    taplar.sort((a, b) => a - b);
    const s = Z.eslestir(taplar, hedefler, { esikSn: 0.075 });
    const h = s.eslesenler.map(x => x.hedefIdx);
    const t = s.eslesenler.map(x => x.tapIdx);
    assert.equal(new Set(h).size, h.length);
    assert.equal(new Set(t).size, t.length);
    assert.deepEqual(h, h.slice().sort((a, b) => a - b));
    assert.deepEqual(t, t.slice().sort((a, b) => a - b));
  }
});

console.log('\nGeçen: ' + gecen + ' · Kalan: 0');
