/* =======================================================
   RitimTerapi — Service Worker v19
   Cache stratejisi (optik-kocluk deseniyle aynı):
   - Statik varlıklar (CSS, JS, ikon, görsel) → Stale-While-Revalidate
   - PHP sayfaları → YALNIZCA ağ (dinamik veri asla cache'lenmez),
     ağ yoksa offline.html gösterilir.
   Not: CACHE_ADI değişince aktivasyonda eski cache silinir.
   ======================================================= */

var CACHE_ADI   = 'ritim-v48';
/* Çevrimdışı sayfası SW'nin kendi konumuna göre çözülür (alt dizin kurulumunda da doğru). */
var OFFLINE_URL = new URL('offline.html', self.location).pathname;

/* Uygulama kabuğu: ilk kurulumda cache'e alınır */
var KABUK = [
  'offline.html',
  'assets/css/app.css',
  'assets/css/landing.css',
  'assets/css/metronom.css',
  'assets/css/motor.css',
  'assets/css/grup-atolyesi.css',
  'assets/css/ev.css',
  'assets/css/poliritim.css',
  'assets/css/tini.css',
  'assets/js/app.js',
  'assets/js/uygulama-yukle.js',
  'assets/js/yol-cekirdegi.js',
  'assets/js/kulak-cekirdegi.js',
  'assets/js/kulak.js',
  'assets/js/ritim-yolu.js',
  'assets/css/yol.css',
  'assets/js/landing.js',
  'assets/js/metronom.js',
  'assets/js/ev.js',
  'assets/js/zamanlama-cekirdegi.js',
  'assets/js/ritim-ogrenme.js',
  'assets/js/metronom-cekirdegi.js',
  'assets/js/metronom-setlist.js',
  'assets/js/motor-koordinasyon.js',
  'assets/js/motor-studyo.js',
  'assets/js/grup-atolyesi-cekirdegi.js',
  'assets/js/grup-atolyesi.js',
  'assets/js/ritim-okuma.js',
  'assets/js/ritim-okuma-sayfa.js',
  'assets/js/poliritim-cekirdegi.js',
  'assets/js/poliritim-studyo.js',
  'assets/js/tini-cekirdegi.js',
  'assets/js/tini-kartlari.js',
  'assets/vendor/abcjs/abcjs-basic-min.js',
  'assets/js/belge-duzenle.js',
  'assets/img/favicon.svg',
  'assets/img/icon-192.png',
  'assets/img/icon-512.png',
  'assets/img/apple-touch-icon.png'
];

/* ---- Kurulum: kabuk cache'e alınır ---- */
self.addEventListener('install', function (ev) {
  ev.waitUntil(
    caches.open(CACHE_ADI).then(function (cache) {
      /* Tek tek dene; bir dosya yoksa kurulumu patlatma */
      return Promise.allSettled(
        KABUK.map(function (url) {
          return cache.add(url).catch(function () { /* sessiz geç */ });
        })
      );
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

/* ---- Aktivasyon: eski cache'leri sil ---- */
self.addEventListener('activate', function (ev) {
  ev.waitUntil(
    caches.keys().then(function (anahtarlar) {
      return Promise.all(
        anahtarlar.filter(function (k) { return k !== CACHE_ADI; })
                  .map(function (k)   { return caches.delete(k); })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

/* ---- Fetch: istek stratejisi ---- */
self.addEventListener('fetch', function (ev) {
  var req = ev.request;

  /* Yalnızca GET isteklerini yönet */
  if (req.method !== 'GET') return;

  /* Farklı origin'e giden isteklere dokunma */
  if (!req.url.startsWith(self.location.origin)) return;

  /* Statik varlıklar — Stale While Revalidate: önbellekten anında sun,
     arka planda ağdan yenisini indirip önbelleği güncelle. */
  if (/\.(css|js|png|jpg|jpeg|svg|gif|woff2?|ico)(\?|$)/.test(req.url)) {
    ev.respondWith(
      caches.open(CACHE_ADI).then(function (cache) {
        return cache.match(req).then(function (cached) {
          var agdan = fetch(req).then(function (res) {
            if (res && res.status === 200) { cache.put(req, res.clone()); }
            return res;
          }).catch(function () { return cached; });
          return cached || agdan;
        });
      })
    );
    return;
  }

  /* PHP sayfaları — YALNIZCA ağdan. Yoklama/rapor gibi dinamik içerik
     ASLA cache'lenmez; ağ yoksa çevrimdışı bilgi sayfası gösterilir. */
  ev.respondWith(agdanGetir(req));
});

/** Ağdan getir; bağlantı yoksa çevrimdışı sayfası döndür. */
function agdanGetir(req) {
  return fetch(req).catch(function () {
    if (req.mode !== 'navigate') {
      return new Response('', { status: 503, statusText: 'Baglanti yok' });
    }
    return caches.match(OFFLINE_URL).then(function (yanit) {
      return yanit || new Response(YEDEK_CEVRIMDISI, {
        status: 503,
        headers: { 'Content-Type': 'text/html; charset=UTF-8' }
      });
    });
  });
}

/* offline.html hiç önbelleğe alınamadıysa devreye giren küçük yedek sayfa. */
var YEDEK_CEVRIMDISI =
  '<!doctype html><html lang="tr"><head><meta charset="utf-8">' +
  '<meta name="viewport" content="width=device-width, initial-scale=1">' +
  '<title>Sunucuya Ulaşılamıyor</title></head>' +
  '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;' +
  'font-family:Segoe UI,system-ui,sans-serif;background:#faf8f5;color:#1c1917;text-align:center;padding:24px">' +
  '<div><div style="font-size:42px">🥁</div>' +
  '<h1 style="font-size:22px;margin:12px 0 8px">Sunucuya Ulaşılamıyor</h1>' +
  '<p style="color:#78716c;margin:0 0 20px">Bilgisayarda start.bat çalışıyor mu kontrol edin.</p>' +
  '<button onclick="location.reload()" style="padding:13px 24px;background:#d97706;color:#fff;border:0;' +
  'border-radius:12px;font-size:15px;font-weight:600;cursor:pointer">Tekrar Dene</button>' +
  '</div></body></html>';
