'use strict';

/*
 * Poliritim çekirdeği birim testi.
 * Kapsam: oran matematiği (OKEK/OBEB), hedef zamanları, bileşik ızgara,
 * el başına değerlendirme, ORTAK DOWNBEAT ayrımı, çekim (faz kilitlenmesi)
 * ölçüsü ve kademeli zorluk.
 */

const assert = require('node:assert/strict');
const P = require('../assets/js/poliritim-cekirdegi.js');

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

function yakin(a, b, esik = 1e-9) {
  assert.ok(Math.abs(a - b) <= esik, `${a} ≠ ${b}`);
}

function yakinDizi(gercek, beklenen, esik = 1e-9) {
  assert.equal(gercek.length, beklenen.length, `uzunluk: ${gercek.length} ≠ ${beklenen.length}`);
  gercek.forEach((x, i) => assert.ok(Math.abs(x - beklenen[i]) <= esik, `${i}: ${x} ≠ ${beklenen[i]}`));
}

console.log('Poliritim çekirdeği testi\n');

console.log('— Oran matematiği —');
test('OBEB ve OKEK doğru', () => {
  assert.equal(P.obeb(3, 2), 1);
  assert.equal(P.obeb(4, 6), 2);
  assert.equal(P.okek(3, 2), 6);
  assert.equal(P.okek(4, 3), 12);
  assert.equal(P.okek(5, 4), 20);
  assert.equal(P.okek(4, 6), 12);
});

test('katalogda beklenen oranlar var ve zorluk sırası artan', () => {
  const kodlar = P.ORANLAR.map((o) => o.kod);
  ['2:1', '3:2', '4:3', '5:4', '7:4'].forEach((k) => assert.ok(kodlar.includes(k), k + ' yok'));
  const zorluklar = P.ORANLAR.map((o) => o.zorluk);
  for (let i = 1; i < zorluklar.length; i++) {
    assert.ok(zorluklar[i] >= zorluklar[i - 1], 'zorluk sırası bozuk');
  }
});

test('oranBul bilinmeyen kodda null döner', () => {
  assert.equal(P.oranBul('9:7'), null);
  assert.equal(P.oranBul('3:2').sag, 3);
});

console.log('\n— Döngü süresi —');
test('referans el temposu döngü süresini belirler', () => {
  // 3:2, sağ referans, 180 BPM → sağ el saniyede 3 vuruş → döngü 1 sn
  yakin(P.donguSuresi('3:2', 180, 'sag'), 1);
  // sol referans (2 vuruş), 120 BPM → döngü = 0.5 * 2 = 1 sn
  yakin(P.donguSuresi('3:2', 120, 'sol'), 1);
});

test('BPM uç değerleri sınırlanır (bölme hatası olmaz)', () => {
  assert.ok(Number.isFinite(P.donguSuresi('3:2', 0, 'sag')));
  assert.ok(Number.isFinite(P.donguSuresi('3:2', 99999, 'sag')));
  assert.ok(P.donguSuresi('3:2', 0, 'sag') > 0);
});

console.log('\n— Hedef zamanları —');
test('3:2 tek döngüde hedefler doğru yerlerde', () => {
  const h = P.hedefleriUret('3:2', 180, 1, 'sag');   // döngü 1 sn
  yakin(h.donguSn, 1);
  yakinDizi(h.sag, [0, 1 / 3, 2 / 3]);
  yakinDizi(h.sol, [0, 0.5]);
});

test('ortak downbeat tespit ediliyor (3:2 → yalnız döngü başı)', () => {
  const h = P.hedefleriUret('3:2', 180, 2, 'sag');
  // 2 döngü → ortak vuruşlar t=0 ve t=1
  yakinDizi(h.ortak, [0, 1]);
});

test('4:3 hedef sayıları ve ortak vuruş', () => {
  const h = P.hedefleriUret('4:3', 240, 1, 'sag');   // sağ 4 vuruş, 240 BPM → döngü 1 sn
  assert.equal(h.sag.length, 4);
  assert.equal(h.sol.length, 3);
  yakinDizi(h.ortak, [0]);
});

test('çok döngüde hedefler birikimli ilerler', () => {
  const h = P.hedefleriUret('3:2', 180, 3, 'sag');
  assert.equal(h.sag.length, 9);
  assert.equal(h.sol.length, 6);
  yakin(h.sag[h.sag.length - 1], 2 + 2 / 3);
});

console.log('\n— Bileşik ızgara —');
test('3:2 ızgarası 6 hücre, doğru eller', () => {
  const g = P.izgara('3:2');
  assert.equal(g.length, 6);
  // sağ (3) → her 2 hücrede: 0,2,4 ; sol (2) → her 3 hücrede: 0,3
  assert.deepEqual(g.filter((c) => c.sag).map((c) => c.indeks), [0, 2, 4]);
  assert.deepEqual(g.filter((c) => c.sol).map((c) => c.indeks), [0, 3]);
});

test('4:3 ızgarası 12 hücre, doğru eller', () => {
  const g = P.izgara('4:3');
  assert.equal(g.length, 12);
  assert.deepEqual(g.filter((c) => c.sag).map((c) => c.indeks), [0, 3, 6, 9]);
  assert.deepEqual(g.filter((c) => c.sol).map((c) => c.indeks), [0, 4, 8]);
});

test('mnemonik yuvası sayısı bilinen değerlerle uyuşuyor', () => {
  // 3:2 → 4 onset ("ÇOK zor de-ğil"), 4:3 → 6 onset
  assert.equal(P.mnemonikYuvalari('3:2').length, 4);
  assert.equal(P.mnemonikYuvalari('4:3').length, 6);
  assert.equal(P.mnemonikYuvalari('2:1').length, 2);
});

console.log('\n— El başına değerlendirme —');
test('kusursuz vuruşlarda tam isabet, sıfır sapma', () => {
  const h = P.hedefleriUret('3:2', 180, 1, 'sag');
  const s = P.eliDegerlendir(h.sag, h.sag.slice(), 0.15);
  assert.equal(s.isabet, 3);
  assert.equal(s.kacirilan, 0);
  assert.equal(s.fazla, 0);
  yakin(s.ortMutlakMs, 0);
});

test('pencere dışı vuruş KAÇIRILDI sayılır, fazla olarak da işlenir', () => {
  const hedefler = [0, 0.5, 1];
  const taplar = [0, 0.5, 5];        // üçüncü çok uzakta
  const s = P.eliDegerlendir(hedefler, taplar, 0.15);
  assert.equal(s.isabet, 2);
  assert.equal(s.kacirilan, 1);
  assert.equal(s.fazla, 1);
});

test('bir tap İKİ hedefe sayılmaz (tek eşleme)', () => {
  const hedefler = [0, 0.05];        // çok yakın iki hedef
  const taplar = [0.02];             // tek tap, ikisine de yakın
  const s = P.eliDegerlendir(hedefler, taplar, 0.15);
  assert.equal(s.isabet, 1, 'tek tap yalnız bir hedefe sayılmalı');
  assert.equal(s.kacirilan, 1);
});

test('sapma işareti korunur (geç pozitif, erken negatif)', () => {
  const gec = P.eliDegerlendir([1], [1.03], 0.15);
  const erken = P.eliDegerlendir([1], [0.97], 0.15);
  assert.ok(gec.ortSapmaMs > 0, 'geç vuruş pozitif olmalı');
  assert.ok(erken.ortSapmaMs < 0, 'erken vuruş negatif olmalı');
  yakin(Math.abs(gec.ortSapmaMs), 30, 1e-6);
});

test('kararlılık (SD) sabit kaymadan etkilenmez', () => {
  const hedefler = [0, 1, 2, 3];
  const sabitKayma = hedefler.map((t) => t + 0.05);   // hepsi 50ms geç
  const s = P.eliDegerlendir(hedefler, sabitKayma, 0.15);
  yakin(s.ortSapmaMs, 50, 1e-6);
  yakin(s.sdMs, 0, 1e-6);   // dağılım yok → SD sıfır
});

console.log('\n— ORTAK DOWNBEAT ayrımı (kritik) —');
test('sağ elin vuruşu sol elin hedefine ESLENMEZ', () => {
  const h = P.hedefleriUret('3:2', 180, 1, 'sag');
  // Kullanıcı YALNIZ sağ eliyle kusursuz vurdu, sol el hiç vurmadı
  const d = P.degerlendir({
    oran: '3:2', hedefler: h,
    sagTaplar: h.sag.slice(), solTaplar: [],
    pencereMs: 150, tamMs: 50
  });
  assert.equal(d.sag.isabet, 3, 'sağ el tam');
  assert.equal(d.sol.isabet, 0, 'sol el hiç vurmadı — sağın vuruşları buraya sayılmamalı');
  assert.equal(d.sol.kacirilan, 2);
});

test('ortak downbeat iki elde AYRI AYRI sayılır', () => {
  const h = P.hedefleriUret('3:2', 180, 1, 'sag');
  // Her iki el de kendi hedeflerini kusursuz vurdu (t=0'da ikisi birlikte)
  const d = P.degerlendir({
    oran: '3:2', hedefler: h,
    sagTaplar: h.sag.slice(), solTaplar: h.sol.slice(),
    pencereMs: 150, tamMs: 50
  });
  assert.equal(d.sag.isabet, 3);
  assert.equal(d.sol.isabet, 2);
  assert.equal(d.skor, 100, 'kusursuz oyun 100 vermeli');
});

console.log('\n— Zorluk sırası (literatür indeksi) —');
test('katalog sırası zorluk indeksine (a×b) göre artan', () => {
  /*
   * Deutsch (1983) / Summers ark. (1993) indeksi = a × b.
   * Sezgiye aykırı olan yer: 5:3 (15) < 5:4 (20). Sayı büyüklüğüne göre
   * sıralamak öğrenciyi yanlış basamağa yönlendiriyordu.
   */
  const indeksler = P.ORANLAR.map((o) => P.zorlukIndeksi(o.kod));
  for (let i = 1; i < indeksler.length; i++) {
    assert.ok(indeksler[i] >= indeksler[i - 1],
      `${P.ORANLAR[i - 1].kod}(${indeksler[i - 1]}) → ${P.ORANLAR[i].kod}(${indeksler[i]}) sıra bozuk`);
  }
});

test('bilinen indeks değerleri literatürle uyuşuyor', () => {
  assert.equal(P.zorlukIndeksi('3:2'), 6);
  assert.equal(P.zorlukIndeksi('4:3'), 12);
  assert.equal(P.zorlukIndeksi('5:3'), 15);
  assert.equal(P.zorlukIndeksi('5:4'), 20);
  assert.equal(P.zorlukIndeksi('7:4'), 28);
});

test('5:3, 5:4\'ten ÖNCE gelir (kritik: sayı büyüklüğü aldatıcı)', () => {
  const kodlar = P.ORANLAR.map((o) => o.kod);
  assert.ok(kodlar.indexOf('5:3') < kodlar.indexOf('5:4'),
    'katalog sırası: ' + kodlar.join(' → '));
  // Merdiven bu sırayı izler: 5:3'ten sonra 5:4 gelir
  assert.equal(P.sonrakiOran('5:3', 90), '5:4');
});

test('4:3 ve 3:4 aynı indeksi paylaşır ve yan yana durur', () => {
  /* Aynı çevrim; fark yalnız hangi elin nabız sayıldığı. Bu yüzden
     merdivende ardışıktır ve aralarına başka oran girmez. */
  assert.equal(P.zorlukIndeksi('4:3'), P.zorlukIndeksi('3:4'));
  assert.equal(P.sonrakiOran('4:3', 90), '3:4');
  assert.equal(P.sonrakiOran('3:4', 90), '5:3');
});

console.log('\n— Oynanabilirlik (bileşik ızgara boşluğu) —');
test('bileşik ızgara adımı = döngü / OKEK', () => {
  // 3:2 @ 80 BPM ref sağ → döngü 2.25 sn, OKEK 6 → adım 375 ms
  const p = P.oynanabilirlik('3:2', 80, 'sag');
  yakin(p.adimMs, 375, 0.1);
  assert.equal(p.oynanabilir, true);
  assert.equal(p.rahat, true);
});

test('yüksek tempoda 7:4 fiziksel olarak oynanamaz hâle gelir', () => {
  // 7:4 @ 200 BPM ref sağ → döngü 2.1 sn, OKEK 28 → adım 75 ms
  const p = P.oynanabilirlik('7:4', 200, 'sag');
  yakin(p.adimMs, 75, 0.1);
  assert.equal(p.oynanabilir, false, 'adım ' + p.adimMs + ' ms, 100 ms altı flam olarak duyulur');
});

test('önerilen üst tempo, adımı 150 ms\'de tutar', () => {
  ['3:2', '4:3', '5:3', '5:4', '7:4'].forEach((kod) => {
    const ust = P.oynanabilirlik(kod, 80, 'sag').onerilenUstBpm;
    const p = P.oynanabilirlik(kod, ust, 'sag');
    assert.ok(p.adimMs >= 150 - 0.6, kod + ' @ ' + ust + ' BPM → adım ' + p.adimMs + ' ms');
    assert.ok(p.rahat, kod + ' önerilen üst tempoda rahat olmalı');
  });
});

test('oynanabilirlik referans ele göre değişir', () => {
  // Aynı oran, referans sol el olunca döngü kısalır → adım daralır
  const sag = P.oynanabilirlik('3:2', 80, 'sag');
  const sol = P.oynanabilirlik('3:2', 80, 'sol');
  assert.ok(sol.adimMs < sag.adimMs, `sağ ${sag.adimMs} ms, sol ${sol.adimMs} ms`);
});

console.log('\n— Etkin tolerans penceresi (tavan etkisine karşı) —');
test('yavaş tempoda istenen pencere aynen kullanılır', () => {
  // 3:2 @ 80 BPM ref sağ → döngü 2.25 sn, hızlı el (3 vuruş) aralığı 750 ms.
  // Sınır 750*0.4 = 300 ms; istenen 140 ms bunun altında → kırpılmaz.
  const p = P.etkinPencere('3:2', 80, 'sag', 140);
  assert.equal(p.kirpildi, false);
  yakin(p.ms, 140, 1e-9);
  yakin(p.hizliAralikMs, 750, 0.1);
});

test('hızlı tempoda pencere KIRPILIR (dokunuş komşu hedefe taşamaz)', () => {
  // 7:4 @ 240 BPM ref sağ → sağ aralığı 250 ms → sınır 100 ms.
  const p = P.etkinPencere('7:4', 240, 'sag', 180);
  assert.equal(p.kirpildi, true);
  yakin(p.ms, 100, 0.1);
  assert.ok(p.ms < p.istenenMs, 'kırpılan pencere istenenden küçük olmalı');
});

test('pencere daima hızlı el aralığının yarısından KÜÇÜK — hiçbir oranda taşma yok', () => {
  ['2:1', '3:2', '4:3', '3:4', '5:4', '5:3', '7:4'].forEach((kod) => {
    [40, 80, 120, 200, 300].forEach((bpm) => {
      ['sag', 'sol'].forEach((ref) => {
        const p = P.etkinPencere(kod, bpm, ref, 500);
        assert.ok(p.ms < p.hizliAralikMs / 2,
          `${kod} @ ${bpm} ref ${ref}: pencere ${p.ms} ms, komşu uzaklığı ${p.hizliAralikMs / 2} ms`);
      });
    });
  });
});

console.log('\n— Zayıf el belirleyici (tek elin hatası gizlenemez) —');
test('bir el sistematik geç, öteki kusursuz → skor tam puan VERMEZ', () => {
  /*
   * Bu test gerçek bir kusuru yakaladı: sapmalar iki elden birlikte
   * ortalanırsa 75 ms + 0 ms → 45 ms çıkıyor ve tamMs=45 ile skor 100 oluyor.
   * Poliritimde oyuncu ZAYIF eli kadar iyidir; kötü el belirleyici olmalı.
   */
  const h = P.hedefleriUret('3:2', 300, 4, 'sag');
  const p = P.etkinPencere('3:2', 300, 'sag', 400);
  const d = P.degerlendir({
    oran: '3:2', hedefler: h,
    sagTaplar: h.sag.map((t) => t + 0.075),              // sağ el 75 ms geç
    solTaplar: h.sol.slice(),                            // sol el kusursuz
    pencereMs: p.ms, tamMs: 45
  });
  assert.ok(d.skor < 90, '75 ms sapan el tam puana engel olmalı, skor: ' + d.skor);
  assert.equal(d.zayifEl, 'sag', 'skoru belirleyen el sağ olmalı');
  yakin(d.sag.ortMutlakMs, 75, 0.5);
  yakin(d.sol.ortMutlakMs, 0, 1e-6);
});

test('iki el de kusursuzsa zayıf el bildirilir ama skor 100 kalır', () => {
  const h = P.hedefleriUret('3:2', 120, 3, 'sag');
  const d = P.degerlendir({
    oran: '3:2', hedefler: h, sagTaplar: h.sag.slice(), solTaplar: h.sol.slice(),
    pencereMs: 140, tamMs: 45
  });
  assert.equal(d.skor, 100);
});

test('zayıf el, hangi el kötüyse ONU gösterir (sol taraf da denenir)', () => {
  const h = P.hedefleriUret('4:3', 100, 3, 'sag');
  const d = P.degerlendir({
    oran: '4:3', hedefler: h,
    sagTaplar: h.sag.slice(),
    solTaplar: h.sol.map((t) => t - 0.09),               // sol el 90 ms erken
    pencereMs: 140, tamMs: 45
  });
  assert.equal(d.zayifEl, 'sol');
  assert.ok(d.skor < 95, 'skor: ' + d.skor);
});

console.log('\n— Mnemonik hizalaması —');
test('HER oranın hece sayısı ses olan hücre sayısına EŞİT', () => {
  /* Bu test şart: hece sayısı yuvadan bir eksik/fazla olursa cümle kayar ve
     öğrenci yanlış ritmi ezberler. Kulakla fark edilmez, sessizce yanlıştır. */
  P.ORANLAR.forEach((o) => {
    const h = P.mnemonikHaritasi(o.kod);
    assert.ok(h.uyusuyor,
      `${o.kod}: ${h.heceAdedi} hece ama ${h.yuvaAdedi} yuva — cümle "${(o.mnemonik || []).join('-')}" düzeltilmeli`);
  });
});

test('mnemonik yuvaları doğru ele işaretlenir', () => {
  const h = P.mnemonikHaritasi('3:2');
  // 3:2 ızgarası 6 hücre; sağ 0,2,4 — sol 0,3 → yuvalar 0(ortak),2,3,4
  assert.deepEqual(h.yuvalar.map((y) => y.indeks), [0, 2, 3, 4]);
  assert.deepEqual(h.yuvalar.map((y) => y.el), ['ortak', 'sag', 'sol', 'sag']);
  assert.deepEqual(h.yuvalar.map((y) => y.hece), ['hiç', 'zor', 'de', 'ğil']);
});

test('bilinmeyen oranda mnemonik güvenli boş döner', () => {
  const h = P.mnemonikHaritasi('9:8');
  assert.equal(h.uyusuyor, false);
  assert.deepEqual(h.yuvalar, []);
});

console.log('\n— Oran hatası (ellerin yapışması) —');
test('kusursuz oyunda oran hatası ≈ 0', () => {
  const h = P.hedefleriUret('3:2', 180, 3, 'sag');
  const o = P.oranHatasi(h.sag, h.sol, '3:2');
  yakin(o.gercek, 1.5, 1e-3);
  yakin(o.hedef, 1.5, 1e-9);
  yakin(o.hataYuzde, 0, 0.2);
});

test('eller birbirine yapışırsa (unison) oran 1e çöker → hata NEGATİF', () => {
  const h = P.hedefleriUret('3:2', 180, 3, 'sag');
  // Faz kilitlenmesinin ucu: sol el sağ elin temposuna kapılır → 3:3
  const o = P.oranHatasi(h.sag, h.sag.slice(), '3:2');
  yakin(o.gercek, 1.0, 1e-3);
  assert.ok(o.hataYuzde < -20, 'unisona çöküş belirgin negatif olmalı, bulunan: ' + o.hataYuzde);
});

test('oran hatası yön belirsizliğinden etkilenmez — her oranda tanımlı', () => {
  ['2:1', '3:2', '4:3', '3:4', '5:4', '5:3', '7:4'].forEach((kod) => {
    const h = P.hedefleriUret(kod, 160, 3, 'sag');
    const o = P.oranHatasi(h.sag, h.sol, kod);
    assert.ok(o !== null, kod + ' için oran hatası hesaplanmalı');
    yakin(o.hataYuzde, 0, 0.5);
  });
});

test('tek vuruşla oran hatası hesaplanamaz (null)', () => {
  assert.equal(P.oranHatasi([0], [0], '3:2'), null);
  assert.equal(P.oranHatasi([], [], '3:2'), null);
});

console.log('\n— Faz kayması (ikincil elin bağımsızlığı) —');
test('kusursuz oyunda faz kayması ve kenara yaklaşma ≈ 0', () => {
  const h = P.hedefleriUret('3:2', 180, 2, 'sag');
  const d = P.degerlendir({
    oran: '3:2', hedefler: h,
    sagTaplar: h.sag.slice(), solTaplar: h.sol.slice(),
    pencereMs: 150, tamMs: 50
  });
  assert.ok(d.fazKaymasi.adet > 0, 'ortak olmayan hedef bulunmalı');
  yakin(d.fazKaymasi.ortKayma, 0, 1e-3);
  yakin(d.fazKaymasi.kenaraYaklasma, 0, 1e-3);
});

/*
 * 3:2'de serbest sol vuruşu, iki sağ vuruşunun TAM ORTASINDADIR — yani
 * "diğer ele doğru" diye tek bir yön yoktur. Bu yüzden yön değil KENARA
 * YAKLAŞMA ölçülür: hangi yöne kayarsa kaysın bir birincil vuruşa yaklaşır.
 * Bu testler o simetriyi bilerek sınar.
 */
test('3:2 simetrisi: iki yöne de kayma AYNI kenara yaklaşmayı verir', () => {
  const h = P.hedefleriUret('3:2', 180, 2, 'sag');
  const kaydir = (dt) => h.sol.map((t) => (
    h.ortak.some((o) => Math.abs(o - t) < 1e-9) ? t : t + dt
  ));
  const geri = P.degerlendir({
    oran: '3:2', hedefler: h, sagTaplar: h.sag.slice(), solTaplar: kaydir(-0.04),
    pencereMs: 150, tamMs: 50
  });
  const ileri = P.degerlendir({
    oran: '3:2', hedefler: h, sagTaplar: h.sag.slice(), solTaplar: kaydir(+0.04),
    pencereMs: 150, tamMs: 50
  });
  assert.ok(geri.fazKaymasi.kenaraYaklasma > 0.05,
    'geriye kayma kenara yaklaşma olmalı, bulunan: ' + geri.fazKaymasi.kenaraYaklasma);
  assert.ok(ileri.fazKaymasi.kenaraYaklasma > 0.05,
    'ileriye kayma da kenara yaklaşma olmalı, bulunan: ' + ileri.fazKaymasi.kenaraYaklasma);
  yakin(geri.fazKaymasi.kenaraYaklasma, ileri.fazKaymasi.kenaraYaklasma, 1e-3);
  /* İşaretli kayma ise simetriyi AYIRT eder */
  assert.ok(geri.fazKaymasi.ortKayma < 0, 'geriye kayma işaretli olarak negatif');
  assert.ok(ileri.fazKaymasi.ortKayma > 0, 'ileriye kayma işaretli olarak pozitif');
});

test('faz kayması ölçek-bağımsız: kayma birincil aralığın oranı olarak verilir', () => {
  // 3:2 @ 180 BPM referans sağ → sağ aralığı 1/3 sn. 40 ms kayma = 0.12 faz.
  const h = P.hedefleriUret('3:2', 180, 1, 'sag');
  const sol = h.sol.map((t) => (h.ortak.some((o) => Math.abs(o - t) < 1e-9) ? t : t + 0.04));
  const d = P.degerlendir({
    oran: '3:2', hedefler: h, sagTaplar: h.sag.slice(), solTaplar: sol,
    pencereMs: 150, tamMs: 50
  });
  yakin(d.fazKaymasi.ortKayma, 0.04 / (1 / 3), 1e-3);
});

test('ortak vuruşta faz tanımsız — hesaba katılmaz', () => {
  const h = P.hedefleriUret('3:2', 180, 1, 'sag');
  const f = P.fazKaymasiOlc(h.sol, [{ hedefIdx: 0, tapIdx: 0, sapmaMs: 30 }], h.sag, [0.03]);
  // hedefIdx 0 = t:0 = ortak vuruş → atlanmalı
  assert.equal(f.adet, 0);
});

test('5:3 gibi asimetrik oranda faz kayması yönü anlamlı', () => {
  // 5:3'te sol hedefleri sağ vuruşlarının ortasında DEĞİL — yön belirsizliği yok
  const h = P.hedefleriUret('5:3', 150, 1, 'sag');
  const sol = h.sol.map((t) => (h.ortak.some((o) => Math.abs(o - t) < 1e-9) ? t : t + 0.03));
  const d = P.degerlendir({
    oran: '5:3', hedefler: h, sagTaplar: h.sag.slice(), solTaplar: sol,
    pencereMs: 150, tamMs: 50
  });
  assert.ok(d.fazKaymasi.adet >= 2, 'birden çok serbest hedef olmalı');
  assert.ok(d.fazKaymasi.ortKayma > 0, 'ileriye kayma pozitif işaretli olmalı');
});

console.log('\n— Skor davranışı —');
test('hiç vurmayan oyuncu 0 alır', () => {
  const h = P.hedefleriUret('3:2', 180, 1, 'sag');
  const d = P.degerlendir({ oran: '3:2', hedefler: h, sagTaplar: [], solTaplar: [], pencereMs: 150, tamMs: 50 });
  assert.equal(d.skor, 0);
});

test('rastgele fazla vuruş skoru düşürür (doğruluk cezası)', () => {
  const h = P.hedefleriUret('3:2', 180, 1, 'sag');
  const temiz = P.degerlendir({
    oran: '3:2', hedefler: h, sagTaplar: h.sag.slice(), solTaplar: h.sol.slice(),
    pencereMs: 150, tamMs: 50
  });
  const spam = P.degerlendir({
    oran: '3:2', hedefler: h,
    sagTaplar: h.sag.concat([5, 5.2, 5.4, 5.6]), solTaplar: h.sol.slice(),
    pencereMs: 150, tamMs: 50
  });
  assert.ok(spam.skor < temiz.skor, 'fazla vuruş cezalandırılmalı');
});

test('skor daima 0-100 aralığında', () => {
  const h = P.hedefleriUret('5:4', 200, 2, 'sag');
  [[], h.sag.slice(), h.sag.concat(h.sag)].forEach((sagT) => {
    const d = P.degerlendir({
      oran: '5:4', hedefler: h, sagTaplar: sagT, solTaplar: h.sol.slice(),
      pencereMs: 150, tamMs: 50
    });
    assert.ok(d.skor >= 0 && d.skor <= 100, 'skor aralık dışı: ' + d.skor);
  });
});

test('el başına ayrıntı DA döndürülür (tek bileşik sayıya güvenilmez)', () => {
  const h = P.hedefleriUret('3:2', 180, 1, 'sag');
  const d = P.degerlendir({
    oran: '3:2', hedefler: h, sagTaplar: h.sag.slice(), solTaplar: [],
    pencereMs: 150, tamMs: 50
  });
  assert.ok(d.sag && d.sol, 'iki elin ayrıntısı ayrı olmalı');
  assert.equal(typeof d.sdMs, 'number');
  assert.equal(typeof d.ortMutlakMs, 'number');
  assert.equal(d.ikincilEl, 'sol', '3:2 içinde seyrek vuran el sol');
});

console.log('\n— Kademeli zorluk —');
test('yüksek skor bir üst orana geçirir', () => {
  assert.equal(P.sonrakiOran('3:2', 85), '4:3');
});

test('düşük skor bir alt orana indirir', () => {
  assert.equal(P.sonrakiOran('4:3', 30), '3:2');
});

test('orta skorda aynı oranda kalır', () => {
  assert.equal(P.sonrakiOran('3:2', 60), '3:2');
});

test('uçlarda taşma olmaz', () => {
  assert.equal(P.sonrakiOran('2:1', 10), '2:1');
  const son = P.ORANLAR[P.ORANLAR.length - 1].kod;
  assert.equal(P.sonrakiOran(son, 100), son);
});

test('bilinmeyen oran ilk orana düşer', () => {
  assert.equal(P.sonrakiOran('9:7', 50), P.ORANLAR[0].kod);
});

console.log('\n=================================');
console.log('  Geçen: ' + gecen + '   Kalan: 0');
console.log('=================================');
