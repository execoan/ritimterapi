# RitimTerapi — Ritim Atölyesi Yönetim Uygulaması

Ritim/perküsyon atölyesi veren bir eğitmenin kendi işini yönettiği, yerelde çalışan
tek kullanıcılı web uygulaması. **Eğitim aracıdır; sağlık ürünü değildir** — arayüzde
ve veliye giden çıktılarda klinik/sonuç dili kullanılmaz (bkz. `CLAUDE.md`).

## Çalıştırma

**En kolay yol:** `start.bat` dosyasına çift tıklayın. XAMPP PHP'sini bulur,
sunucuyu başlatır ve tarayıcıyı `http://localhost:8590` adresinde açar.
Pencerede ayrıca telefon/tablet için Wi-Fi adresi yazar (örn. `http://192.168.1.20:8590`)
— ilk çalıştırmada Windows güvenlik duvarı sorarsa "Özel ağlar" için izin verin.

Alternatif — XAMPP Apache altında: klasörü `C:\xampp\htdocs\RitimTerapi` olarak
kopyalayın/taşıyın ve `http://localhost/RitimTerapi` adresini açın.

Kurulum adımı yoktur: ilk açılışta veritabanı (`storage/ritim.sqlite`) kendiliğinden
oluşturulur ve teknik kütüphanesi başlangıç içeriğiyle dolar.

## Site ve giriş

- `http://localhost:8590` → **tanıtım sitesi** (herkese açık, animasyonlu tek sayfa:
  yöntem, bilimsel dayanak ve atölyedeki yansımaları, protokoller, biz kimiz).
- Sağ üstteki **Giriş Yap** → eğitmen paneli. Giriş ekranında **⚡ Admin** ve
  **⚡ Eğitmen** tek-tık hızlı giriş butonları vardır (geliştirme kolaylığı) ve
  klasik kullanıcı adı + şifre formu (varsayılan: `admin` / `ritim` ve
  `egitmen` / `ritim`). Hesaplar `storage/gizli.php` içindeki
  `PANEL_KULLANICILAR` dizisinden yönetilir; **yayına alırken** aynı dosyada
  `HIZLI_GIRIS` değerini `false` yapın ve şifreleri değiştirin.
- Ana sayfada **MOXO bölümü**: isteğe bağlı ön/son ölçüm — metinleri Site
  yönetiminden düzenlenir; testi deneyimli psikologların uyguladığı ve eğitmenin
  klinik test yorumlamadığı açıkça yazılıdır. Paket başlatırken "🧠 MOXO ön/son
  ölçüm dahil" kutusu işaretlenirse paket notuna eklenir ve paket kartlarında
  rozet olarak görünür.
- Panel, uygulama Wi-Fi'a açık çalıştığı için şifre kapısının arkasındadır;
  tanıtım sayfası şifresiz gezilir.

## Akademik dayanak (DOI'li kayıt defteri)

- **Teknikler → 📚 Akademik Çalışmalar**: RitimOdak kaynakçasındaki 19 hakemli
  çalışma DOI'leri ve nötr dilli özetleriyle kayıtlıdır (`calismalar.php`);
  yeni çalışma eklenebilir/düzenlenebilir.
- Her tekniğin detayında **Bilimsel dayanak** kartı vardır: bağlı çalışmaların
  künyesi, tıklanabilir DOI bağlantısı, "ne buldu" özeti ve "bu tekniğe bağı"
  notu. Eğitmen panelden çalışma bağlayıp kaldırabilir.
- Dayanağı olmayan teknikte açıkça **"Bu tekniğe bağlı akademik çalışma yok"**
  yazar (pedagojik gelenek meşrudur; kanıt etiketi buna uygun kalır). Kütüphane
  listesinde her tekniğin yanında 📚 sayısı ya da "yok" rozeti görünür.

## Site yönetimi (CMS) ve plan şablonları

- **Site** menüsü: tanıtım sayfasının bölümlerini sürükleyerek sırala, göster/gizle,
  başlıkları ve tüm metinleri (hero, sayı kartları, iletişim, alt uyarı…) düzenle —
  "Kaydet ve Yayınla" ile site anında güncellenir. Ayrıca:
  - **Bilim kartları**: makale kartlarını sürükle-sırala, alanlarını düzenle,
    kanıt rozetini seç, yeni kart ekle/sil — ana sayfadaki Bilim bölümü buradan beslenir.
  - **Foto galerisi**: JPEG/PNG/WebP yükle (≤4 MB), alt yazı ver, sürükle-sırala,
    göster/gizle. Görsel yokken ana sayfadaki Galeri bölümü ve menü bağlantısı
    kendiliğinden gizlenir; görseller tıklanınca büyür (lightbox).
- **Şablonlar** menüsü: RitimOdak-Ö (çocuk 8–15, 45 dk) ve RitimOdak-Y (yetişkin, 60 dk)
  12 haftalık hazır programlar. "Şablonu Uygula" ile seçilen gruba haftada 1 (A) veya
  2 (A+B) oturum, teknik planlarıyla birlikte tek adımda oluşturulur; mevcut tarihlere
  çakışanlar atlanır. Şablonlar tamamen düzenlenebilir: yeni şablon oluştur, bilgilerini
  değiştir, oturum ekle/sil ve her oturumun tekniklerini sürükle-bırak editörüyle düzenle.

## Ev Programı ve seans paketleri

- **Ev Programı** menüsü: 27 çalışmalık kütüphane (RitimOdak mikro uygulamaları:
  çocuk + yetişkin + 3 etkileşimli modül), öğrenciye veya tüm gruba ödev atama
  (tarih aralığı + haftada X gün hedefi), güncel ödevlerin haftalık takip tablosu.
  Bilimsel dayanak: `docs/ev-programi-dayanak.md` (kısa-sık pratik, veli katılımı,
  izleme/streak, sayma sistemleri).
- **Öğrenci ev sayfası** — `ev.php`: her öğrencinin 6 haneli **erişim kodu** vardır
  (öğrenci detayında görünür/yenilenir). Öğrenci telefondan girer; haftalık ödev
  kartları, günlük "✓ Bugün yaptım" işareti, hafta noktaları ve 🔥 seri rozeti.
  Etkileşimli görevler: **mini metronom** (süre sayaçlı), **Vuruş Tutturma mini**
  (sesli+sessiz faz, skorlu) ve **Ritim Okuma** ("1 ve 2 ve", üçlemede "1-le-me" —
  ekrandaki deseni sayarak vurma). Skorlar `kaynak='ev'` olarak protokol
  sonuçlarına kaydedilir; eğitmen aynı tabloda izler.
- **Seans paketleri**: öğrenci detayından 8–24 seanslık paket başlatılır; kullanım
  "katıldı/geç" yoklamalarından otomatik sayılır. Panelde "paketi bitmek üzere"
  uyarısı (kalan ≤ 2), öğrenci ev sayfasında paket çubuğu görünür.
- Veli raporuna yalnız olgu eklenir: "Evde yapılan çalışmalar — işaretlenen gün sayısı".

## Metronom Stüdyosu ve dikkat protokolleri

Menüdeki **Metronom** sayfası tek ses motoru üzerinde çalışır
(özellik araştırması: `docs/metronom-arastirma.md`):

- **Serbest metronom (v2):** 30–240 BPM, tap tempo, 2/4–7/8 ölçü,
  **alt bölünmeler** (sekizlik/üçleme/onaltılık), **vuruş başına aksan deseni**
  (noktaya tıkla: aksan → normal → sessiz), **tempo trainer** (hedefe her N ölçüde
  ±X BPM), **poliritim** katmanı (2/3/5 : ölçü), **7 sesli kit** (tahta, klik,
  klaves, inek çanı, davul, bip, Türkçe **sesli sayma**) + seçimde önizleme,
  ⚡ **flaş modu**, büyük vuruş sayacı, **preset** çipleri, sessiz aralık çalışması,
  🎲 **rastgele sus** (Time Guru tarzı içsel zamanlama), ⏲ çalışma zamanlayıcısı,
  📳 titreşim (mobil), ⛶ tam ekran sahne. **🎵 Şarkı tempo kütüphanesi:** 42 tanıdık
  parça (metal/rock/pop/jazz) — tıklayınca BPM (+Take Five'ta 5/4 ölçü) ayarlanır ve
  çalar; arama ve tür filtresi vardır. Kısayollar: Boşluk, T, ↑↓.
- **Protokoller** (skor 0–100, öğrenciye kaydedilir; BAASTA türü görevlerden uyarlama):
  - *Vuruş Tutturma* — senkronizasyon–devam: sesli + sessiz faz, ms sapma, eğilim.
  - *BPM Bulma* — tempo yeniden üretme, 3 tur.
  - *Ritim Okuma* — desen okuyarak sayma-vurma (stüdyo sürümü).
  - *Spontan Tempo* — serbest 21 vuruş: kişisel doğal tempo (SMT) + CV tutarlılık skoru.
  - *Aksak Bulma* — anizokroni algısı: 6 vuruşluk dizi düzenli mi aksak mı, 8 tur,
    %6–15 kayma zorlukları. Motorsuz saf dinleme ölçümü.
  - *İçsel Ritim (sessizlik merdiveni)* — "rastgele sus"un ölçülen hâli: metronom
    kademe kademe susar (%0→%25→%50→%75), her vuruşta vurulur; sessiz vuruş sapması
    ayrı raporlanır (grafikte mor), skor sessiz fazlara ağırlıklıdır. Ev programına
    mini sürümüyle (3 faz) atanabilir; gelişim öğrenci trendinde izlenir.
    **Uyarlanan zorluk:** öğrenci seçilince son skora göre Kolay/Standart/İleri
    profili önerilir (≥80 → İleri, <50 → Kolay).
- **Haftanın protokolü**: ders planına ve şablon oturumlarına protokol alanı —
  şablon uygulanınca oturuma taşınır; oturum ekranındaki "🎛 Protokolü Stüdyoda Aç"
  düğmesi Stüdyo'yu doğru sekmede açar (`metronom.php?protokol=…`).
  Dönemlik grup raporunda **"Protokol gelişimi"** bölümü: protokol başına haftalık
  ortalama skor çubukları ve öğrenci bazlı ilk→son skor tablosu (▲/▼).

Sonuçlar öğrenci detay sayfasında zaman içinde skor çubuklarıyla görünür.
**Protokol skorları eğitmenin iç izleme aracıdır; veli raporlarına yansıtılmaz.**

## Belgeler: raporlar ve katılım sertifikası

Raporlar sayfasından dört yazdırılabilir belge hazırlanır: haftalık eğitmen
raporu, dönemlik grup raporu, veli raporu ve **Katılım Belgesi (sertifika)**.

- **Katılım Belgesi** (`sertifika.php`): dönem sonu için şık, çerçeveli ve
  yatay (A4 landscape) belge — marka "Ritim Atölyesi", katılımcı kodu, tarih
  aralığı, katılım sayısı ve belge numarası. **"İlk/son ölçümleri ekle"**
  işaretlenirse dönem başı/sonu metronom ölçümleri *yalın sayı* olarak eklenir
  (en az iki ölçümü olan protokoller); dipnot bunların eğitsel izleme amaçlı
  olduğunu, değerlendirme/tanı aracı olmadığını belirtir.
- **Ekranda düzenleme** (optik-kocluk'un belge deseni): her belgenin üstündeki
  **✏️ Düzenle** düğmesi belgeyi Word gibi serbestçe düzenlenebilir yapar —
  ör. sertifikada takma kod yerine gerçek adı yazmak, gereksiz satırı silmek.
  Değişiklikler yalnız o ekran ve yazdırma/PDF çıktısı içindir;
  **kayıtlı veri hiçbir zaman değişmez** ("Orijinale Dön" ya da sayfa
  yenileme kayıtlı hâli geri getirir). Ortak parçalar:
  `includes/view/belge-arac-cubugu.php` + `assets/js/belge-duzenle.js`.

## Uygulama olarak kurma (PWA)

Uygulama, optik-kocluk ile aynı PWA yapısını taşır (`manifest.json`, `sw.js`,
`offline.html`, ikonlar). Menüde beliren **"📲 Uygulamayı Yükle"** butonu ya da
adres çubuğundaki yükleme simgesiyle:

- **Bilgisayar (Chrome/Edge):** kendi penceresinde, görev çubuğuna
  sabitlenebilen bir uygulama olarak kurulur.
- **Telefon/tablet (Android Chrome):** "Ana ekrana ekle" ile metronom simgesiyle
  ana ekrana yerleşir. Uygulama simgesine uzun basınca Panel, Oturum Planla,
  Yoklama, Öğrenciler ve Raporlar kısayolları açılır.
- **iPhone/iPad (Safari):** Paylaş → "Ana Ekrana Ekle".

Not: Tam PWA kurulumu (kendi penceresi) yalnız `localhost` üzerinde sunulur;
telefon/tabletten Wi-Fi adresiyle girildiğinde site ana ekran kısayolu olarak
eklenir ve tarayıcıda açılır. Sunucu kapalıyken açılırsa "Sunucuya Ulaşılamıyor"
sayfası gösterilir.

## Yedekleme

Veritabanı tek dosyadır: `storage/ritim.sqlite` dosyasını kopyalamak tam yedektir.
(Uygulama açıkken kopyalıyorsanız yanındaki `-wal` ve `-shm` dosyalarını da alın.)

## Yapı

| Bölüm | Dosyalar |
|---|---|
| Panel | `index.php` |
| Gruplar | `gruplar.php`, `grup.php`, `grup-sil.php` |
| Öğrenciler | `ogrenciler.php`, `ogrenci.php`, `ogrenci-sil.php` |
| Teknik kütüphanesi | `teknikler.php`, `teknik.php`, `teknik-sil.php` |
| Ders planı | `plan.php` (sürükle-bırak sıralama, 60 dk hedef uyarısı) |
| Oturum kaydı | `oturumlar.php`, `oturum.php` (yoklama + işlenen teknikler + notlar) |
| Raporlar | `raporlar.php`, `rapor-haftalik.php`, `rapor-donemlik.php`, `rapor-veli.php` |
| Altyapı | `includes/` (bootstrap, db, model, helpers, seed, görünüm), `assets/` |

- Şema göçleri `includes/db.php` içindedir (`PRAGMA user_version` ile izlenir);
  şema değişikliğinde mevcut göçler değiştirilmez, yeni göç eklenir.
- Seed verisi `includes/seed.php` dosyasındadır; yalnız kütüphane boşken çalışır,
  eğitmenin düzenlediği/sildiği kayıtları asla ezmez.
- Veli raporu şablonu kilitlidir: yalnız yapılanı, katılımı ve gözlemi aktarır.
  Gözlem notlarında uygunsuz ifade (tanı/sonuç/klinik dil) varsa rapor ekranında
  eğitmene uyarı gösterilir. Veliye giden çıktıda marka **"Ritim Atölyesi"** olarak
  geçer; "terapi" sözcüğü hiçbir veli çıktısında yer almaz.
