# Ölçüm Kılavuzu

Bu belge protokol ölçümlerinin **nasıl alınacağını, nasıl okunacağını ve neyin
iddia edilmeyeceğini** tanımlar. Amaç, eğitmenin kendi verisine dürüst bakmasını
kolaylaştırmak; ölçümü bir değerlendirme aracına dönüştürmek değil.

Ölçüm skorları **iç izleme aracıdır**: veli raporuna yansıtılmaz (CLAUDE.md §2).
Katılım Belgesi'ne yalnız istenirse, yorumsuz sayı olarak eklenir.

---

## 1. Ölçümü ne zaman almalı

| Ne zaman | Kaç ölçüm | Neden |
|---|---|---|
| Dönem başı (1.–2. hafta) | **3** (farklı günlerde) | İlk ölçüm neredeyse her zaman "görevi ilk kez yapma" etkisiyle düşük çıkar. Tek ölçüm başlangıç kabul edilirse dönem sonunda sahte kazanım görünür. |
| Dönem ortası | 1–2 | Gidişatı görmek için |
| Dönem sonu (son 2 hafta) | **3** (farklı günlerde) | Aynı gerekçe: uçtaki tek ölçüm gün formuna açıktır |

Uygulama, uç blokların medyanını alır: 6+ ölçümde 3'er, 4–5 ölçümde 2'şer,
daha azında tek ölçüm. Yani **dönem başı ve sonunda 3'er ölçüm almak**, sistemin
en güvenilir çalıştığı senaryodur.

Aynı gün üst üste tekrar etmek yerine **farklı günlere yaymak** gerekir; arka
arkaya tekrar, yorgunluk ve kısa süreli alışma yüzünden gerçek düzeyi yansıtmaz.

## 2. Standart koşullar (📏)

Karşılaştırılabilirlik için ölçümler sabit parametrelerle alınır. Stüdyodaki
**📏 Standart ölçüm modu** anahtarı bunları kilitler:

| Protokol | Standart koşul |
|---|---|
| Vuruş Tutturma | 72 BPM · 16 + 16 vuruş |
| BPM Bulma | Orta zorluk |
| Ritim Okuma | Seviye 1 · 60 BPM · tam rehber |
| Aksak Vuruş Algısı | Orta zorluk |
| İçsel Ritim | 72 BPM · standart profil |
| Spontan Tempo | (parametresiz — her zaman standart) |

📏 işareti anahtardan değil, **gerçekten kullanılan koşullardan** türetilir; elle
aynı ayarları seçmek de standart sayılır. Dönem karşılaştırması, en az iki
standart ölçüm varsa **yalnız onlardan** yapılır.

## 3. Cihaz kalibrasyonu

Dokunuş ile sesin arasındaki gecikme cihazdan cihaza değişir. Her cihazda
(ve kulaklık/hoparlör değişiminde) **kalibrasyon yapılmalıdır** — 4 hazırlık +
12 ölçüm vuruşu. Uygulama kalibrasyonun yaşını, cihaz imzasını ve kararlılığını
izler; 30 günden eski veya cihaz imzası değişmiş kalibrasyonda uyarı verir.

Her ölçüm, o andaki kalibrasyon kalitesiyle birlikte kaydedilir. Raporda
kalibrasyonsuz/şüpheli ölçüm varsa satırda ⚠ görünür; CSV'de "Kalibrasyon
kalitesi" sütunundan süzülebilir.

**Önemli:** Kalibrasyon sabit kaymayı düzeltir, ama tamamen ortadan kaldırmaz.
Bu yüzden dönem karşılaştırmasında asıl bakılacak ölçü **kararlılıktır** (§4).

## 4. Hangi sayıya bakmalı

Her ölçüm iki farklı bilgi taşır:

- **Sabit kayma (ortalama sapma).** Kişinin sistematik olarak erken/geç vurması
  + cihaz gecikmesinin kalıntısı. Cihaz değişince kayar; beceriyi tek başına
  göstermez. (Tapping literatüründe insanların metronomdan biraz *önce* vurma
  eğilimi bilinen bir olgudur — kusur değildir.)
- **Kararlılık (asenkroni SD).** Vuruşların ne kadar tutarlı olduğu. Cihazdan
  bağımsızdır ve pratikle değişen esas budur.

0–100 skoru okunması kolay olsun diye üretilmiş bileşik bir sayıdır ve sabit
kaymayı içinde taşır. **Dönemler arası karşılaştırma için kararlılık (SD)
tercih edilmelidir**; SD her ölçümle birlikte `sd_ms` alanında saklanır ve
CSV'ye "Kararlılık SD (ms)" sütunuyla çıkar. SD'de **düşük = iyi**.

## 5. Fark ne zaman "gerçek"?

Her ölçümün doğal bir dalgalanması vardır. Uygulama, öğrencinin kendi ardışık
ölçüm farklarından bir **gürültü bandı** hesaplar (MDC95 ≈ 1,96 × ardışık
farkların karekök ortalaması) ve dönemlik raporda gösterir:

- Fark bandın **üstündeyse**: değişim ölçüm gürültüsüyle açıklanamıyor.
- Fark bandın **içindeyse**: *değişim yok* demek değil — **ölçümle ayırt
  edilemiyor** demektir. Bu ayrım önemlidir ve raporda bu dille yazılır.
- 3'ten az ölçümde band hesaplanamaz; karar verilmez.

Band, seride gerçek bir eğilim varken genişler; yani tahmin temkinli taraftadır.

## 6. Eğitilmeyen sonda: Aksak Vuruş Algısı

**Aksak Vuruş Algısı** ev programında bilerek çalıştırılmaz. Saf dinleme
ölçümüdür ve *eğitilmeyen kontrol* işlevi görür:

- Eğitilen protokollerle birlikte bu da benzer oranda yükseliyorsa → görülen
  şey büyük olasılıkla **genel alışma / test tekrarı etkisidir**.
- Yalnız eğitilen protokoller yükseliyorsa → değişim daha **özgüldür**.

Tek denekli izlemede elde bulunan en dürüst kontrol mekanizması budur. Bu
protokolü ev ödevi olarak vermek, kontrol işlevini ortadan kaldırır.

## 7. Pratik dozu

Dönemlik raporda hafta başına işaretlenen pratik günü gösterilir. Skorlardaki
değişimin pratiğin yoğun olduğu haftalarla birlikte gidip gitmediğine bakılır.
Bu bir **nedensellik kanıtı değildir**; yalnızca tutarlılık kontrolüdür.

## 8. İddia edilmeyecekler

Ölçümler eğitim içi izleme amaçlıdır. Şunlar **yapılmaz**:

- Tanı, tarama veya klinik değerlendirme aracı olarak sunmak
- Yaş normuyla karşılaştırmak, "yaşına göre geride/ileride" demek
- Skor değişimini dikkat, akademik başarı veya davranış değişimi olarak sunmak
- Veli raporuna skor veya skor yorumu koymak
- Ölçüm sonucuna dayanarak yönlendirme/öneri yapmak

Yapılabilecek: "şu koşullarda alınan ölçümde şu değerler kaydedildi" demek.

---

## Ek: veriyi dışarı çıkarma

Raporlar → **Protokol CSV**: tarih, öğrenci, protokol, skor, kararlılık SD,
BPM, kaynak (atölye/ev), standart ölçüm, kalibrasyon kalitesi, not ve ham
detay JSON'u. Kendi analizini bu dosya üzerinden yapabilirsin; ham JSON faz
bazlı sapmaları ve kalibrasyon parametrelerini de içerir.

---

## Ölçeklerin denetimi ve gücü (Ağustos 2026)

Skorlama formülleri benzetimle sınandı (her satır 3.000 sanal ölçüm).
Üç ölçekte sorun bulundu ve düzeltildi; sonuçlar aşağıda.

### Düzeltilenler

**Aksak Bulma — şans düzeyi çıkarılmıyordu.** Test iki seçenekli (aksak /
düzenli), yani madenî para atan biri ortalama 4/8 tutturur. Eski formül buna
50 puan veriyordu ve bu sayı, grafikte Vuruş Tutturma'nın 50'siyle yan yana
duruyordu. Hiç becerisi olmayan biri 8 turda **%14,2 olasılıkla 75+**
alıyordu. Yeni formül şans payını çıkarır (`100·max(0, 2p−1)`): aynı kişi
artık ortalama 15 alıyor, 75+ olasılığı %4,4'e düştü. 4/8 = 0 puan.

**Spontan Tempo — tavan çok alçaktı.** Eşikler CV %2 → 100, %12 → 0 idi.
Literatürde yetişkin kendiliğinden vuruş CV'si ~%2-5; yani tipik bir yetişkin
ölçümün tavanında başlıyor ve gelişme hiç görünmüyordu. Yeni aralık %1 → 100,
%15 → 0. Şimdi CV %2 → 93, %5 → 71, %8 → 50; hem yetişkin hem çocuk bandı
ayırt edici.

**Vuruş Tutturma — ölçeğin %90'ı kullanılmıyordu.** Değerlendirme penceresi
hep `0,30 × vuruş aralığı` idi; 72 BPM'de bu ±250 ms demek, oysa yetişkinde
eşzamanlama sapması tipik 20-50 ms, çocukta 40-80 ms. Sonuç: herkes 87-97
arasına sıkışıyordu. Pencere artık 120 ms'de kırpılıyor (oran kuralı hızlı
tempoda korunur — Weber). Ölçülen yayılım:

| zamanlama SD | eski skor | yeni skor |
|---|---|---|
| 10 ms | 96,7 | 93,2 |
| 30 ms | 90,2 | 79,6 |
| 60 ms | 80,4 | 57,5 |
| 90 ms | 70,4 | 37,5 |

### Gürültü bandı (MDC95) ne yapıyor, ne yapmıyor

**Yanlış alarm oranı düşük — bant işini yapıyor.** Yeteneği sabit tutulan
sanal bir katılımcıda sistem %1,4-3,1 oranında "anlamlı" diyor. Yani
"ilerleme var" dediğinde büyük olasılıkla haklı.

**Buna karşılık duyarlılığı düşük.** 6 oturumda, zamanlama SD'si 40 ms'den
20 ms'ye inen GERÇEK bir gelişme yalnız **%27** olasılıkla bandı aşıyor.
Yani "gürültü bandı içinde" çıktısı **"ilerleme yok" demek değildir** —
"bu kadar ölçümle ayırt edilemiyor" demektir. Arayüz dili de bunu söylüyor.

Ölçeği genişletmek bunu düzeltmez: yeni ve eski ölçek aynı gücü veriyor
(%26,1 vs %26,5) — sinyal ve gürültü birlikte ölçeklendiği için. Gücü
gerçekten artıran iki şey var:

| | 16 vuruş | 32 vuruş | 64 vuruş |
|---|---|---|---|
| **6 ölçüm** | %27 | %44 | %62 |
| **8 ölçüm** | %42 | %68 | %92 |
| **12 ölçüm** | %55 | %86 | %99 |

(40 ms → 20 ms gelişimini yakalama oranı; yanlış alarm her durumda %2'nin altında.)

**Pratik sonuç:** dönem başı/sonu karşılaştırması yapılacaksa faz başına
**32 vuruş** seçin ve dönem boyunca **en az 8 ölçüm** alın. Varsayılan 16
vuruş kısa ve çocuk için yorucu değil, ama trend izlemeye yetmez.
