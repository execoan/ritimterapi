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
