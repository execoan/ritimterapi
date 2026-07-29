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

## Duman testi (smoke test)

`test.bat`, ortak zamanlama/puanlama, adaptif öğrenme, profesyonel metronom,
setlist, iki el motor koordinasyon ve grup atölyesi çekirdeklerinin toplam
61 deterministik JavaScript testini ve 79 denetimli PHP duman testini birlikte çalıştırır. Testler
**gerçek veriye asla dokunmaz**: `RITIM_STORAGE` ortam değişkeniyle geçici bir
depolama dizini kullanan kendi sunucusunu 127.0.0.1'de rastgele portta açar,
test sonunda her şeyi siler; gerçek veritabanının özeti test öncesi/sonrası
karşılaştırılarak dokunulmadığı ayrıca kanıtlanır.

```powershell
node test/zamanlama.test.js
node test/ritim-ogrenme.test.js
node test/metronom-cekirdegi.test.js
node test/metronom-setlist.test.js
node test/motor-koordinasyon.test.js
node test/grup-atolyesi.test.js
C:\xampp\php\php.exe test\smoke.php
```

Denetimlerin kapsamı: sayfaların hatasız açılması, göç + seed bütünlüğü,
CRUD + basamaklı silme, sertifika/rapor içerikleri ve **güvenlik** —
giriş kapısı yönlendirmeleri, CSRF zorunluluğu, XSS kaçışı, `storage/`,
`includes/`, `.git/`, `test/` erişim engelleri, yol gezinmesi (`..`),
yedek indirme dosya-adı doğrulaması, giriş deneme kilidi (5 hata → 30 sn)
ve veli çıktısında kilitli dil (CLAUDE.md §2).

Aynı test her push'ta GitHub Actions'ta da otomatik koşar
(`.github/workflows/smoke.yml`). Çıkış kodu 0 = hepsi geçti.

## Site ve giriş

- `http://localhost:8590` → **tanıtım sitesi** (herkese açık, animasyonlu tek sayfa:
  canlı deneyler, yöntem, bilimsel dayanak ve atölyedeki yansımaları, protokoller,
  biz kimiz).
- **🔊 Canlı deneyler bölümü** (sayfanın en başında, ziyaretçiyi ilk ekranda
  yakalamak için): ziyaretçi okumak yerine *dinler*.
  1. **"Saniyede kaç ses duyuyorsunuz?"** — sürgüyle klik dizisi 1→120 Hz
     hızlanır; ~20 Hz'de kulak vuruşları ayıramaz ve **perde (nota)** duymaya
     başlar. Ekran o anda mora döner, yaklaşık nota adını yazar (60 Hz ≈ Si1);
     canvas'ta dalga gerçek zamanlı çizilir, "otomatik" modu yavaştan hızlıya
     kendi gezer. Ritim ve notanın aynı olgunun iki hızı olduğunu gösterir.
  2. **"Vuruşu ne kadar yakalıyorsunuz?"** — 4 hazırlık + 8 ölçüm vuruşu;
     tap'lerin ms sapması renkli şeritle gösterilir (erken/geç/kaçan).
     Cihaz gecikmesi telafi edilmez ve sayfada bu açıkça yazar — *değerlendirme
     değil, gösterim*.
  3. **"Aksayanı duyabilir misiniz?"** — 6 vuruşluk dizi ya kusursuz düzenlidir
     ya bir vuruş %12 kayar; 3 tur, anlık geri bildirim. Atölyedeki
     "eğitilmeyen sonda" ölçümünün tanıtımı.
- **💓 Nabız bölümü**: ninniden koşu adımına altı kart, her biri **kendi
  temposunda** atar (CSS'te `--sure = 60/bpm`); dokununca o tempo çalar.
  Sayfadaki tüm sesler tek bir ses bağlamını paylaşır — yeni bir deney
  başlayınca diğerleri otomatik susar.
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
- **🏠 Ödev otomasyonu**: şablon oturum düzenleyicisinde her haftaya ev görevleri
  bağlanır (A/B ortak; RitimOdak şablonları hazır eşlenmiş gelir). Şablon gruba
  uygulanınca o haftaların görevleri, hafta tarih aralığıyla gruptaki aktif
  öğrencilere **otomatik ödev** atanır (mükerrer atama korunur; sonradan kaydolan
  öğrenciye elle atanır).
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

- **Özel ders + çoklu grup üyeliği:** Kayıtlı bir kişi özel dersinin yanında bir
  veya daha fazla grup dersine eklenebilir. Özel dersler sunucu tarafında tek aktif
  katılımcıyla sınırlandırılır. Üyelik kaldırıldığında oturum ve yoklama geçmişi
  korunur. Aynı kişinin aynı gün ve saatte iki derse yazılması; ayrıca mevcut ders
  saatinin üyelerde çakışma yaratacak biçimde değiştirilmesi engellenir. Eğitmen,
  yayın ve bitiş tarihi belirlenebilen grup duyuruları paylaşabilir. Katılımcı
  koduyla açılan ev ekranında ders günü/saatini, yaklaşan programı, aktif duyuruları
  ve grup arkadaşlarının yalnızca takma adlarını görür; doğum yılı, veli/eğitmen
  notları, gözlemler ve kişisel skorlar diğer üyelere gösterilmez.

- **Serbest metronom (v4):** 30–240 BPM, tap tempo, 2/4–12/8 ölçü,
  **alt bölünmeler** (sekizlik/üçleme/onaltılık), **vuruş başına aksan deseni**
  (noktaya tıkla: aksan → normal → sessiz), 5/4–12/8 için müzikal
  **vuruş grupları**, sekizlik ve onaltılık ikililerinde %50–75
  **swing/shuffle**, sesli sayma kullanmadan 1/2/4 ölçülük **sayarak giriş**,
  **tempo trainer** (hedefe her N ölçüde
  ±X BPM), ayrı ses düzeyi ve mavi vuruş ışıkları bulunan **poliritim** katmanı
  (2–12 vuruş : ölçü; ilk çapraz vuruş aksanlı), **6 sesli kit** (tahta, klik, klaves, inek çanı, davul,
  yumuşak bip) + seçimde önizleme,
  ⚡ **flaş modu**, büyük vuruş sayacı, **preset** çipleri, sessiz aralık çalışması,
  🎲 **rastgele sus** (Time Guru tarzı içsel zamanlama), ⏲ çalışma zamanlayıcısı,
  📳 titreşim (mobil), ⛶ tam ekran sahne. **🎵 Şarkı tempo kütüphanesi:** 42 tanıdık
  parça (metal/rock/pop/jazz) — tıklayınca BPM (+Take Five'ta 5/4 ölçü) ayarlanır ve
  çalar; arama ve tür filtresi vardır. Son ayarlar cihazda hatırlanır, çalışırken
  desteklenen cihazlarda ekran açık tutulur; paydası 8 olan ölçülerde vuruş birimi
  sekizlik nota olarak hesaplanır. Kısayollar: Boşluk, T, ↑↓.
  **🎯 BPM Tahmin Oyunu:** kütüphaneden rastgele 5 şarkı, iki mod — *Önce dinle*
  (temposu 10 sn metronomla dinletilir, sonra hatırlayıp tap'lersin) ve
  *Hafızadan* (şarkıyı zihninde canlandırıp süre içinde tap'lersin; 15/20/30 sn).
  Tahmin, tap aralıklarının **medyanından** hesaplanır; yarı/çift tempo müzikal
  sayılır (en yakın oktava göre puan, küçük kesintiyle). Tür filtresi ve cihazda
  saklanan mod bazlı rekor vardır. Şarkının kendisi çalınmaz; skorlar oyun
  amaçlıdır ve **protokol ölçümlerine karışmaz**.
- **Çalışma Merkezi:** Hesaba bağlı setlistlerde BPM, ölçü, gruplama, swing,
  poliritim, alt bölünme, sayarak giriş ve süre adım başına saklanır. Adımlar
  otomatik geçebilir veya müzisyeni bekleyebilir; duraklatma süresi çalışmaya
  eklenmez. Serbest metronom ve setlist oturumları 10 saniyeden sonra otomatik
  günlüğe yazılır. Günlük hedef, son 7 gün grafiği, çalışma serisi ve son
  oturumlar aynı panelden izlenir.
- **İki El Motor Koordinasyon Stüdyosu:** Uzman protokol oluşturucu ile
  dönüşümlü, ikişerli, çapraz, eşzamanlı ve karma sağ/sol el desenleri hazırlanır.
  Dokunmatik ekran, F/J tuşları ve Web MIDI desteklenir. Ortak cihaz
  kalibrasyonundan sonra her el için doğruluk, zamanlama sapması, fazla/kaçırılan
  vuruş, MIDI şiddeti, sağ–sol asimetrisi, eşzamanlılık ve oturum sonu performans
  eğilimi ölçülür. Güvenlik durdurmaları skor üretilmeden ayrıca kaydedilir.
  Modül eğitim ve uzman destek aracıdır; tanı veya bağımsız tedavi sunmaz.
- **Grup Atölyesi:** Yerel eğitim arşivindeki 16 PDF/169 sayfanın içerik ve
  bilimsel dayanak taramasından türetilen beş özgün grup akışı: Ortak Nabız
  Çemberi, İki Tını Orkestrası, Ritim Tamamlama Çemberi, Katman Değiş-Takas ve
  Uyarlanmış Ritimli Hareket. Her akışta dört maddelik güvenlik kilidi, katılım
  ve mola seçimi, oturarak eşdeğer, dört hazırlık vuruşu, Web Audio zamanlamalı
  kılavuz, adım sayacı ve sesi kesin kesen duraklatma vardır. Yerel dosyalar
  bilimsel yayın olarak gösterilmez; bağımsız çalışmalar ve sınırlılıkları
  ekranda birlikte sunulur. Ayrıntılı inceleme:
  `docs/kaynak-inceleme-grup-atolyesi.md`.
- **Protokoller** (skor 0–100, öğrenciye kaydedilir; BAASTA türü görevlerden uyarlama):
  - *Vuruş Tutturma* — senkronizasyon–devam: sesli + sessiz faz, ms sapma, eğilim.
  - *BPM Bulma* — tempo yeniden üretme, 3 tur.
  - *Ritim Okuma* — 3 seviye, 24 ders ve 384 kademeli örnek; yazılan notanın
    gerçek başlangıçlarını çalan dinleme modu, **abcjs 6.6.3 ile standart porteli
    nota**, `1-e-ve-a` / `1-le-me` sayımı,
    ölçü veya vuruş kılavuzu ve ayrıntılı isabet–sapma değerlendirmesi.
    Puanlı çalışma öncesi 12 vuruşluk **cihaz/kişisel gecikme kalibrasyonu**
    gerekir; ölçülen ms telafisi bu cihazda saklanır. Öğrenme, Dengeli, Arcade,
    Profesyonel ve kolay/orta/zor için ayrı 60–300 ms girilebilen Özel
    değerlendirme profilleri vardır. Sıralı eşleştirme hızlı notalarda vuruşların
    yanlışlıkla komşu notaya kaymasını önler. Başlangıçta büyük **4‑3‑2‑1 / ŞİMDİ**
    göstergesi ve önceden hazır görünen vuruş pedi kullanılır; yalnız ilk notaya
    verilen ek 80 ms başlangıç payı, ilk vuruşun arayüz geçişi yüzünden kaçmasını önler.
    `zamanlama-cekirdegi.js` bütün senkronizasyon protokollerinde olayın gerçek
    zaman damgasını, ortak cihaz telafisini ve kronolojik bire bir eşleştirmeyi
    uygular. Pencere dışındaki fazla dokunuşlar da kaybolmadan raporlanır.
    **Akıllı çalışma** katmanı her örneğin deneme sayısını, hareketli puanını,
    kalıcı ustalık güvenini ve ritim türü isabetlerini saklar. Başarısız örnekler
    oturum içinde 2 çalışma, gelişen örnekler 4 çalışma sonra yeniden önerilir;
    temiz okumalar 1–3–7–14–30 günlük aralıklarla kalıcılık kontrolüne girer.
    Tek bir yüksek puan “ustalaşıldı” sayılmaz; bunun için en az iki güvenilir
    okuma gerekir. Kullanıcı isterse tek düğmeyle klasik sıralı moda dönebilir.

  - *Spontan Tempo* — serbest 21 vuruş: kişisel doğal tempo (SMT) + CV tutarlılık skoru.
  - *Aksak Bulma* — anizokroni algısı: 6 vuruşluk dizi düzenli mi aksak mı, 8 tur,
    %6–15 kayma zorlukları. Motorsuz saf dinleme ölçümü.
  - *İçsel Ritim (sessizlik merdiveni)* — "rastgele sus"un ölçülen hâli: metronom
    kademe kademe susar (%0→%25→%50→%75), her vuruşta vurulur; sessiz vuruş sapması
    ayrı raporlanır (grafikte mor), skor sessiz fazlara ağırlıklıdır. Ev programına
    mini sürümüyle (3 faz) atanabilir; gelişim öğrenci trendinde izlenir.
    **Uyarlanan zorluk:** öğrenci seçilince son skora göre Kolay/Standart/İleri
    profili önerilir (≥80 → İleri, <50 → Kolay).

- **Profesyonel ölçüm sistemi:** Stüdyoda ve öğrenci ev ekranında ortak cihaz
  kalite paneli bulunur. 4 hazırlık + 12 ölçüm vuruşuyla hesaplanan medyan telafi,
  dağılım, tarayıcı çıkış gecikmesi, örnekleme hızı ve kalibrasyon tarihi gösterilir.
  Ses cihazı değiştiğinde veya ölçüm dağılımı yükseldiğinde yenileme önerilir.

Üçüncü taraf istemci kütüphanesi `assets/vendor/abcjs/` altında yerel tutulur;
MIT lisansı aynı klasördeki `LICENSE.md` dosyasındadır. CDN kullanılmadığı için
nota görünümü CSP ve çevrimdışı PWA çalışma düzeniyle uyumludur.

- **Haftanın protokolü**: ders planına ve şablon oturumlarına protokol alanı —
  şablon uygulanınca oturuma taşınır; oturum ekranındaki "🎛 Protokolü Stüdyoda Aç"
  düğmesi Stüdyo'yu doğru sekmede açar (`metronom.php?protokol=…`).
  Dönemlik grup raporunda **"Protokol gelişimi"** bölümü: protokol başına haftalık
  ortalama skor çubukları ve öğrenci bazlı ilk→son skor tablosu (▲/▼).

- **📏 Standart ölçüm modu**: protokoller kartındaki anahtar, tüm testleri sabit
  koşullara kilitler (Vuruş 72 BPM · 16+16, BPM Bulma orta, Ritim Okuma S1 · 60,
  Aksak orta, İçsel 72 · standart profil; Spontan Tempo zaten parametresizdir).
  📏 işareti kayıtta **gerçekten kullanılan koşullardan** türetilir — elle aynı
  ayarları seçmek de 📏 sayılır. Sertifika ve dönemlik rapor, ilk→son
  karşılaştırmasını en az iki 📏 ölçüm varsa **yalnız onlardan** yapar; böylece
  "80 BPM'de ilk, 110 BPM'de son ölçüm" gibi yanıltıcı kıyaslar önlenir.
- **Blok medyanı**: dönem başı/sonu, uçtaki tek ölçüm yerine **uç blokların
  medyanıdır** (6+ ölçümde 3'er, 4–5'te 2'şer). İlk denemedeki "görevi tanıma"
  çöküşü tek başına başlangıç sayılmaz — bu, sistemin en sık ürettiği sahte
  kazanım kaynağıydı.
- **Gürültü bandı (MDC95)**: dönemlik raporda her fark, öğrencinin kendi ölçüm
  dalgalanmasıyla birlikte gösterilir (1,96 × ardışık farkların RMS'i). Bandın
  içindeki fark "değişim yok" değil, **"ölçümle ayırt edilemiyor"** diye yazılır;
  3'ten az ölçümde karar verilmez.
- **Kararlılık (SD)**: her ölçümle birlikte asenkroni standart sapması `sd_ms`
  ve o andaki **kalibrasyon kalitesi** saklanır. Skor sabit kaymayı (cihaz
  gecikmesi + kişisel erken/geç vurma eğilimi) içinde taşır, SD taşımaz —
  dönemler arası karşılaştırma için SD tercih edilmelidir (düşük = iyi).
- **Eğitilmeyen sonda**: Aksak Vuruş Algısı ev programında bilerek
  çalıştırılmaz; eğitilenlerle birlikte o da yükseliyorsa görülen büyük
  olasılıkla genel alışma etkisidir. Raporda böyle etiketlenir.
- **Ev pratiği dozu**: dönemlik raporda hafta başına işaretlenen pratik günü,
  protokol trendinin yanında (nedensellik kanıtı değil, tutarlılık kontrolü).

Ayrıntılı yöntem: [docs/olcum-kilavuzu.md](docs/olcum-kilavuzu.md) — ne zaman
kaç ölçüm alınacağı, hangi sayıya bakılacağı ve **iddia edilmeyecekler**.
- **🧭 Ders Akışı**: oturum sayfasındaki "Ders Akışını Stüdyoda Başlat" (veya
  stüdyodaki oturum seçici) plandaki teknikleri sırayla çalıştırır — adım başına
  geri sayım, bitişte zil + titreşim, otomatik geçiş, ⏮/⏭/duraklat/sıfırla.
  Sayaç metronomdan bağımsızdır; tempoyu teknik gereksinimine göre serbestçe
  ayarlarsınız.

Sonuçlar öğrenci detay sayfasında zaman içinde skor çubuklarıyla görünür.
**Protokol skorları eğitmenin iç izleme aracıdır; veli raporlarına yansıtılmaz.**

## Belgeler: raporlar ve katılım sertifikası

Raporlar sayfasından beş yazdırılabilir belge hazırlanır: **Öğrenci Rapor
Merkezi**, haftalık eğitmen raporu, dönemlik grup raporu, veli raporu ve
**Katılım Belgesi (sertifika)**. Ayrıca **CSV dışa aktarma** (protokol
sonuçları + yoklama; UTF-8 BOM, noktalı virgül ayraç — Türkçe Excel ile
doğrudan açılır) kendi analizleriniz içindir.

- **Öğrenci Rapor Merkezi** (`ogrenci-rapor.php`): listeden öğrenci seç →
  tek sayfada dönem görünümü. Protokol başına **SVG çizgi grafik** (dolu
  nokta = 📏 standart, halka = koşul farklı, 🏠 = ev ölçümü) + kararlılık (SD)
  mini grafiği + gürültü bandı yorumlu dönem başı/sonu karşılaştırması;
  katılım çizelgesi, ev pratiği haftalık çubukları, çalışılan teknikler,
  gözlem notları. **Bölümler seçilebilir** (Egitimgen V2 deseni), belge
  ekranda **düzenlenip** Yazdır/PDF ile çıkarılır; "Eğitmen değerlendirmesi"
  bölümüne **hazır cümle havuzundan** (kilitli dile uygun, gözlemsel) tıklayıp
  ekleme yapılır. **İç rapordur** — skor içerdiği için veliye verilmez.

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

Menüdeki **Yedek** sayfası (`yedek.php`):

- **⬇ Yedeği İndir** — çalışan veritabanının tutarlı kopyasını indirir
  (`VACUUM INTO`; uygulama açıkken bile WAL içeriği dahildir).
- **Otomatik yedek** — her gün ilk açılışta `storage/yedek/otomatik-<tarih>.sqlite`
  alınır; en yeni 10 gün saklanır. Listeden indirilebilir veya "Bu Yedeğe Dön"
  ile geri yüklenebilir.
- **Geri yükleme** — indirilmiş bir `.sqlite` dosyası yüklenir; dosya doğrulanır
  (SQLite imzası + çekirdek tablolar) ve geri yüklemeden hemen önce mevcut durumun
  **emniyet kopyası** (`oncesi-…`) otomatik alınır.

Elle yedek de hâlâ geçerlidir: `storage/ritim.sqlite` dosyasını kopyalamak tam
yedektir (uygulama açıkken `-wal`/`-shm` dosyalarını da alın). Otomatik yedekler
bu bilgisayardadır; arada bir indirip USB/buluta kopyalamanız önerilir.

## Yapı

| Bölüm | Dosyalar |
|---|---|
| Panel | `index.php` |
| Gruplar | `gruplar.php`, `grup.php`, `grup-sil.php` |
| Öğrenciler | `ogrenciler.php`, `ogrenci.php`, `ogrenci-sil.php` |
| Teknik kütüphanesi | `teknikler.php`, `teknik.php`, `teknik-sil.php` |
| Ders planı | `plan.php` (sürükle-bırak sıralama, 60 dk hedef uyarısı) |
| Grup atölyesi | `grup-atolyesi.php`, `assets/js/grup-atolyesi*.js`, `assets/css/grup-atolyesi.css` |
| Motor koordinasyon | `motor-studyo.php`, `motor-api.php` |
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
