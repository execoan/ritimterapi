# Ritim Atölyesi Yönetim Uygulaması — Proje Brief'i

Bu dosya projenin sabit bağlamıdır. Her oturumun başında oku.
Arayüz dili **Türkçe**. Kod içi değişken/fonksiyon isimleri İngilizce.

---

## 1. Ne yapıyoruz

Ritim/perküsyon atölyesi veren bir eğitmenin kendi işini yönettiği web uygulaması.
Tek kullanıcı (eğitmen), yerelde çalışır.

Ana işler:
- Öğrenci ve grup kaydı
- Teknik kütüphanesi (ders içerikleri)
- Haftalık ders planı hazırlama
- Oturum kaydı: yoklama + işlenen teknikler + gözlem notu
- Rapor üretimi (haftalık, dönemlik, veli raporu)

**Geliştirici:** Fizik mezunu, fizik öğretmeni, ritim atölyesi eğitmeni.
Kodlama deneyimi var. Windows'ta çalışıyor.

---

## 2. Kırmızı çizgiler — tartışmaya kapalı

Uygulama bir **eğitim aracı**, sağlık ürünü değil. Kodda, arayüzde, rapor
şablonlarında şunlar geçmez:

- terapi, tedavi, tanı, hasta, danışan, iyileşme, semptom
- klinik etiketler (DEHB, dikkat eksikliği, gelişim geriliği vb.)
- sonuç vaadi ("dikkatini geliştirir", "iyi gelir", "düzeltir")
- titreşim / rezonans / enerji / hücresel denge söylemi
- normatif değerlendirme ("yaşına göre geride", "riskli")

Kullanılacak dil: **katılımcı/öğrenci, oturum, atölye, eğitmen, teknik, gözlem.**

Bu kural özellikle **veli raporunda** kritik. Rapor ne yapıldığını anlatır,
ne kazandırdığını iddia etmez. Şablon bu kurala göre kilitli yazılacak; serbest
metin alanı varsa eğitmene uyarı gösterilir.

Klasör adı `RitimTerapi` olabilir (yereldir), ama uygulamanın kendi adı ve veliye
giden hiçbir çıktı "terapi" içermez.

---

## 3. Teknoloji

KARAR (Temmuz 2026, uygulandı): kullanıcının mevcut projeleri (optik-kocluk,
Egitimgen-Otomasyon-PHP) PHP olduğu için aynı desenlerle yazıldı:

- **PHP 8.2 (XAMPP: `C:\xampp\php\php.exe`) — framework yok**, optik-kocluk
  tarzı sayfa-başına-dosya + `includes/` altyapısı (bootstrap, db, model, helpers)
- **SQLite (PDO)** — tek dosyalık veritabanı: `storage/ritim.sqlite`; yedeklemesi
  dosya kopyalamak kadar kolay. Şema göçleri `includes/db.php` içinde
  (`PRAGMA user_version`); şema değişikliğinde mevcut göç değiştirilmez, yenisi eklenir
- El yazımı CSS (`assets/css/app.css`, Türkçe sınıf adları: kart/rozet/tablo/btn)
- Rapor çıktısı: yazdırılabilir HTML (`window.print()`)
- Çalıştırma: `start.bat` (php -S 0.0.0.0:8590 + router.php; telefon/tablet
  Wi-Fi'dan bağlanır) veya XAMPP htdocs
- PWA katmanı optik-kocluk yapısında: `manifest.json` (kısayollar), `sw.js`
  (statik SWR + PHP yalnız ağ + offline.html), PNG ikonlar, menüde yükle butonu

Kimlik: `index.php` herkese açık animasyonlu tanıtım sitesi (bilim + atölye
yansımaları); panel sayfaları `giris.php` şifre kapısının arkasında
(`storage/gizli.php` → `PANEL_SIFRE`, varsayılan "ritim"; koruma bootstrap'ta
merkezî). CSRF ve flash desenleri var.

**Görünüm paneli (`site.php#gorunum`) — tanıtım sayfası KODSUZ yönetilir.**
Yeni bir renk/animasyon isteği geldiğinde CSS'e dokunma, önce buraya bak:
- Tema: eğitmen **tek ana renk + tek karşı ışık** seçer; açık/koyu tonlar
  HSL uzayında `tema_ton()` ile türetilir (`includes/helpers.php`). Beş ayrı
  renk seçtirmek uyumsuz skala üretir — türetme her zaman tutarlıdır.
- `landing.css` hiçbir yerde sabit marka rengi taşımaz: `rgb(var(--vurgu) / a)`
  deseni kullanılır. Yeni CSS yazarken de bu desene uy, hex gömme.
  Canvas (`landing.js` → `TEMA_VURGU_RGB`) ve SVG logo degradeleri de okur.
- `tema_stil_blogu()` çıktısı yalnız **doğrulanmış hex'ten sayısal türetmedir**;
  `<style>` içine kullanıcı metni asla girmez (CSS enjeksiyonu yapısal olarak
  imkânsız). Varsayılan temada blok hiç basılmaz.
- Animasyon kipi (tam/sakin/kapalı) `<html>` sınıfı olarak iner; **ziyaretçinin
  sistem tercihi ve sayfadaki ⏸ düğmesi her durumda üstündür**.

**Tanıtım videosu:** `video_embed_bilgisi()` KATI ayrıştırıcıdır — yalnız
YouTube (çerezsiz `youtube-nocookie` gömme), Vimeo ve yerel mp4/webm tanınır,
gerisi `null`. CSP `frame-src` ikinci kilittir. Gömme **tıkla-yükle**: ziyaretçi
kapağa dokunmadan dış servise tek istek gitmez. Bölümü kapatmak için URL'yi
silmek yeter (bölüm + menü bağlantısı kendiliğinden kaybolur).

Metronom Stüdyosu (`metronom.php`): Web Audio lookahead motoru; serbest metronom
(tap tempo, ölçü, aksan, sessiz aralık) + dikkat protokolleri (Vuruş Tutturma
sesli/sessiz fazlı ms-sapma testi, BPM Bulma) → `protokol_sonuclari` tablosuna
0-100 skorla kaydedilir, öğrenci detayında trend gösterilir.

**Skorların veliye giden çıktılarda yeri (karar, Ağustos 2026):**
- *Veli raporu* (`rapor-veli.php`): skor **yansıtılmaz**. Rapor ne yapıldığını
  anlatır, ne kazandırdığını iddia etmez (§2).
- *Katılım Belgesi* (`sertifika.php`): eğitmen isterse (`?olcumler=1`) dönem
  başı/sonu ölçümleri **yalın sayı** olarak eklenebilir. Yorum, değerlendirme
  veya sonuç iddiası eklenmez; belgede "eğitsel izleme amaçlıdır, değerlendirme
  veya tanı aracı değildir" dipnotu ve ölçüm koşullarının aynı olup olmadığını
  gösteren 📏 işareti bulunur. Bu bilinçli bir istisnadır — kural ile kodun
  çelişmemesi için buraya yazıldı.

**İki ad, iki kitle** (`includes/bootstrap.php`):
- `APP_NAME = "RitimTerapi"` — yalnız **eğitmenin gördüğü panelde**. Yereldir,
  klasör adı gibi.
- `PUBLIC_BRAND = "Ritim Atölyesi"` — **dışarıya açılan her yüzeyde**: tanıtım
  sitesi, giriş sayfası, PWA manifest'i, telefon ana ekranı adı, veli belgeleri,
  katılımcı portalı. (`REPORT_BRAND` bunun geriye dönük adıdır, aynı değer.)

Site internete açıldığı için bu ayrım artık yalnız belgelerde değil, her dış
yüzeyde geçerli: arama motorunda ya da telefon ana ekranında "terapi" adıyla
görünmek sunulmayan bir hizmeti çağrıştırır.

---

## 4. Veri modeli

```
Grup
  id, ad, yasAraligi, gun, saat, aktif, baslangicTarihi

Ogrenci
  id, kod (takma ad), dogumYili, grupId, veliNotu, aktif, kayitTarihi

Teknik                      # ders içeriği kütüphanesi
  id, ad, kategori, enstruman, seviye (1-3), sureDk,
  aciklama, hedefBeceri, kanitDuzeyi, kaynak, malzeme

Oturum
  id, grupId, tarih, haftaNo, notlar

OturumTeknik                # oturumda işlenen teknikler (sıralı)
  oturumId, teknikId, sira, sureDk, uygulamaNotu

Katilim
  oturumId, ogrenciId, durum (katildi/gelmedi/gec), gozlemNotu
```

`kanitDuzeyi` alanı **zorunlu** ve enum: `guclu | orta | zayif | yok`.
Her tekniğe bu etiket girilmeden kayıt kabul edilmez. Amaç eğitmenin kendi
içeriğine dürüst bakmasını zorunlu kılmak.

`kaynak` alanı serbest metin — makale künyesi veya "pedagojik gelenek" gibi.

---

## 5. Teknik kütüphanesi — başlangıç içeriği

Uygulama boş kurulmasın; şu kategoriler seed verisi olarak gelsin.
Kanıt düzeyleri gerçekçi girilmiş, yükseltilmeyecek.

| Kategori | Ne yapılıyor | Hedef beceri | Kanıt |
|---|---|---|---|
| Metronoma eşlik | Sabit tempoda vuruş, tempo kademeli değişir | Senkronizasyon, zamanlama | güçlü |
| Dur–devam | İşaretle aniden susma, işaretle devam | Dürtü kontrolü | orta |
| Çağrı–cevap | Eğitmen kalıp çalar, grup tekrarlar | İşitsel bellek, sıralama | orta |
| Ritmik dizi tekrarı | Uzayan kalıpların ezberden tekrarı | Çalışma belleği | orta |
| Tempo değişimi takibi | Hızlanma/yavaşlama, ani geçişler | Esneklik, uyum | orta |
| Katmanlı grup ritmi | Herkes farklı parti, kendi partisini koruma | Sürdürülen dikkat | zayıf |
| Vücut perküsyonu | El–ayak–göğüs kombinasyonları | Motor koordinasyon | orta |
| Serbest doğaçlama | Sıra ile solo | Kendini ifade, grup dinamiği | yok |

Son satır önemli: "kanıtı yok" etiketi de meşru. Etkinlik keyifli ve pedagojik
olarak değerli olabilir; bilimsel iddia taşımaması onu değersizleştirmez.

Not: "güçlü" etiketi yalnızca metronoma eşlik için — orada ölçülen şey doğrudan
senkronizasyon becerisinin kendisi. Diğerlerinde iddia edilen transfer etkisi,
literatürde umut verici ama kesin değil.

---

## 6. Ekranlar

**Panel** — bu haftaki oturumlar, bekleyen yoklamalar, aktif grup/öğrenci sayısı

**Gruplar** — liste, yeni grup, grup detayı (öğrenciler + oturum geçmişi)

**Öğrenciler** — tablo görünümü, filtre (grup/aktiflik), detay sayfası:
kişisel oturum geçmişi, katılım oranı, gözlem notları zaman çizelgesi

**Teknik Kütüphanesi** — tablo, kategori/seviye filtresi, kanıt düzeyi rozeti,
yeni teknik ekleme

**Ders Planı** — bir gruba oturum planla: tarih seç, tekniği sürükle-bırak sırala,
toplam süre otomatik hesaplansın (oturum 60 dk hedefli, aşımda uyarı)

**Oturum Kaydı** — o günkü ders: yoklama listesi, işlenen teknikleri işaretle,
öğrenci başına kısa gözlem notu, oturum notu

**Raporlar** — üç tip:
- *Haftalık eğitmen raporu:* hangi gruplarda ne işlendi, katılım, notlar
- *Dönemlik grup raporu:* teknik dağılımı, katılım grafiği
- *Veli raporu:* öğrenci bazlı, kilitli dil — "şu teknikler çalışıldı, katılım
  şu oranda, gözlemler şunlar". Sonuç iddiası yok.

---

## 7. Yol haritası

**Faz 1:** Veri modeli + grup/öğrenci CRUD + teknik kütüphanesi (seed dahil).
Rapor yok, plan yok. Amaç: veri girilebiliyor mu.

**Faz 2:** Ders planı + oturum kaydı + yoklama.

**Faz 3:** Raporlar (üç tip), yazdırma çıktısı.

**Faz 4:** Öğrenci detayında zaman içindeki gözlem/katılım grafiği. Yedekleme
(veritabanı dışa/içe aktarma).

**Sonra (opsiyonel):** Vuruş tutarlılığı ölçen modül — YAPILDI (Temmuz 2026,
kullanıcı isteğiyle öne alındı): Metronom Stüdyosu + Vuruş Tutturma / BPM Bulma
protokolleri, `protokol_sonuclari` tablosu.

---

## 8. Çalışma tarzı

- Her fazı bitince çalışır durumda bırak; yarım bırakılmış özellik olmasın
- Veritabanı şeması değişirse Prisma migration üret, elle SQL yazma
- Türkçe karakter ve sıralama: `localeCompare('tr')` kullan
- Tarihler `Europe/Istanbul`, hafta başlangıcı Pazartesi
- Seed verisi ayrı dosyada tutulsun, tekrar çalıştırılabilir olsun
