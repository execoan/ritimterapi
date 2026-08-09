'use strict';

const assert = require('node:assert/strict');
const Y = require('../assets/js/yol-cekirdegi.js');
const K = require('../assets/js/kulak-cekirdegi.js');

let gecen = 0;
function test(ad, fn) {
  try { fn(); gecen++; console.log('  ✔ ' + ad); }
  catch (h) { console.error('  ✘ ' + ad); throw h; }
}

console.log('Yol + Kulak çekirdeği testleri');

/* ---------------- Yol kuralları ---------------- */

test('yıldız eşikleri: 50/70/90', () => {
  assert.equal(Y.yildizHesapla(0), 0);
  assert.equal(Y.yildizHesapla(49), 0);
  assert.equal(Y.yildizHesapla(50), 1);
  assert.equal(Y.yildizHesapla(70), 2);
  assert.equal(Y.yildizHesapla(89), 2);
  assert.equal(Y.yildizHesapla(90), 3);
  assert.equal(Y.yildizHesapla(100), 3);
});

test('şans düzeltmesi: rastgele işaretleyen 0 alır', () => {
  /* 2 seçenekte 5/10 = şans düzeyi → 0 */
  assert.equal(Y.sansDuzeltmeli(5, 10, 2), 0);
  assert.equal(Y.sansDuzeltmeli(10, 10, 2), 100);
  assert.equal(Y.sansDuzeltmeli(0, 10, 2), 0);       // altı da 0 (negatif yok)
  /* 4 seçenekte şans 2,5/10 → 0; 10/10 → 100 */
  assert.equal(Y.sansDuzeltmeli(2, 10, 4), 0);
  assert.equal(Y.sansDuzeltmeli(10, 10, 4), 100);
  /* 3 seçenekte 7/10: (0,7−1/3)/(2/3) = %55 */
  assert.equal(Y.sansDuzeltmeli(7, 10, 3), 55);
});

test('kilit zinciri: yıldızsız adım sonrakini kilitler', () => {
  const adimlar = [{ id: 'a' }, { id: 'b' }, { id: 'c' }];
  const g1 = Y.yolGorunumu(adimlar, {});
  assert.deepEqual(g1.map(x => x.acik), [true, false, false]);
  assert.equal(g1[0].siradaki, true);

  const g2 = Y.yolGorunumu(adimlar, { a: { yildiz: 2 } });
  assert.deepEqual(g2.map(x => x.acik), [true, true, false]);
  assert.equal(g2[1].siradaki, true);
  assert.equal(g2[0].siradaki, false);
});

test('sonuç işleme: en iyi skor ve yıldız gerilemez', () => {
  let d = {};
  d = Y.sonucIsle(d, 'a', 92);
  assert.equal(d.a.yildiz, 3);
  d = Y.sonucIsle(d, 'a', 40);       // kötü deneme
  assert.equal(d.a.yildiz, 3, 'yıldız düşmemeli');
  assert.equal(d.a.enIyi, 92);
  assert.equal(d.a.deneme, 2);
});

test('bölüm özeti sayıları tutarlı', () => {
  const bolumler = [{ ad: 'B1', adimlar: [{ id: 'a' }, { id: 'b' }] },
                    { ad: 'B2', adimlar: [{ id: 'c' }] }];
  const ozet = Y.bolumOzeti(bolumler, { a: { yildiz: 3 }, b: { yildiz: 1 } });
  assert.deepEqual(ozet[0], { ad: 'B1', toplam: 2, biten: 2, yildiz: 4 });
  assert.deepEqual(ozet[1], { ad: 'B2', toplam: 1, biten: 0, yildiz: 0 });
});

/* ---------------- Kulak müfredatı ---------------- */

test('müfredat: 5 bölüm, 24 adım, benzersiz kimlikler', () => {
  assert.equal(K.BOLUMLER.length, 5);
  const duz = K.duzAdimlar();
  assert.equal(duz.length, 24);
  assert.equal(new Set(duz.map(a => a.id)).size, 24);
});

test('aynı tohum aynı soruları üretir (determinizm)', () => {
  const a = K.sorulariUret('K1-2', 42);
  const b = K.sorulariUret('K1-2', 42);
  assert.deepEqual(a, b);
  const c = K.sorulariUret('K1-2', 43);
  assert.notDeepEqual(a.sorular.map(s => s.dogru), c.sorular.map(s => s.dogru));
});

test('her adım 10 geçerli soru üretir (200 tohumla tarama)', () => {
  K.duzAdimlar().forEach(adim => {
    for (let tohum = 1; tohum <= 200; tohum += 40) {
      const p = K.sorulariUret(adim.id, tohum);
      assert.equal(p.sorular.length, K.SORU_SAYISI, adim.id);
      p.sorular.forEach((s, i) => {
        assert.ok(Array.isArray(s.sesler) && s.sesler.length >= 1, adim.id + ' ses yok');
        assert.ok(s.secenekler.length >= 2, adim.id + ' seçenek az');
        assert.ok(s.dogru >= 0 && s.dogru < s.secenekler.length,
          adim.id + ' soru ' + i + ': doğru indeksi taşıyor (' + s.dogru + '/' + s.secenekler.length + ')');
        assert.ok(typeof s.aciklama === 'string' && s.aciklama.length > 0, adim.id + ' açıklama yok');
      });
    }
  });
});

test('cevap dağılımı yanlı değil (tiz_pes 400 soruda ~yarı yarıya)', () => {
  /* Hep aynı cevap doğruysa çocuk düğme ezberler; üretici dengeli olmalı. */
  let tiz = 0, toplam = 0;
  for (let tohum = 1; tohum <= 40; tohum++) {
    K.sorulariUret('K1-2', tohum).sorular.forEach(s => { toplam++; if (s.dogru === 0) tiz++; });
  }
  const oran = tiz / toplam;
  assert.ok(oran > 0.35 && oran < 0.65, 'tiz oranı yanlı: ' + oran.toFixed(2));
});

test('nota frekans bandı çocuk kulağına uygun (150–800 Hz)', () => {
  K.duzAdimlar().forEach(adim => {
    const p = K.sorulariUret(adim.id, 7);
    p.sorular.forEach(s => {
      s.sesler.forEach(ses => {
        if (ses.tip === 'nota') {
          const hz = K.midiHz(ses.midi);
          assert.ok(hz > 140 && hz < 900, adim.id + ': ' + hz.toFixed(0) + ' Hz bant dışı');
        }
        if (ses.tip === 'cift') {
          ses.midiler.forEach(m => {
            const hz = K.midiHz(m);
            assert.ok(hz > 140 && hz < 1400, adim.id + ' çift: ' + hz.toFixed(0) + ' Hz');
          });
        }
      });
    });
  });
});

test('kalip_bul: seçenek sesleri var ve doğru kalıp seçeneklerde', () => {
  const p = K.sorulariUret('K4-4', 11);
  p.sorular.forEach(s => {
    assert.equal(s.secenekSesleri.length, 3);
    const duyulan = s.sesler[0].kalip.join('');
    assert.equal(s.secenekSesleri[s.dogru].kalip.join(''), duyulan,
      'doğru seçenek duyulan kalıpla aynı olmalı');
    /* üç seçenek birbirinden farklı */
    const anahtarlar = s.secenekSesleri.map(x => x.kalip.join(''));
    assert.equal(new Set(anahtarlar).size, 3);
  });
});

test('ritim_ayni: "farklı" kalıplar gerçekten tek vuruş farklı', () => {
  const p = K.sorulariUret('K4-1', 23);
  p.sorular.forEach(s => {
    const k1 = s.sesler[0].kalip, k2 = s.sesler[2].kalip;
    let fark = 0;
    for (let i = 0; i < k1.length; i++) { if (k1[i] !== k2[i]) fark++; }
    assert.equal(fark, s.dogru === 1 ? 1 : 0);
  });
});

test('midiHz: A4=440, oktav ikiye katlar', () => {
  assert.ok(Math.abs(K.midiHz(69) - 440) < 0.01);
  assert.ok(Math.abs(K.midiHz(81) - 880) < 0.01);
});

console.log('\nGeçen: ' + gecen + ' · Kalan: 0');
