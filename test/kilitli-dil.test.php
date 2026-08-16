<?php
/**
 * locked_language_flags() birim testi.
 *
 * Bu fonksiyon veli raporunun tek otomatik bekçisi. İki yönlü hata yapabilir:
 *   • KAÇIRMA  → yasaklı ifade veliye gider (asıl tehlike)
 *   • YANLIŞ POZİTİF → uyarı ucuzlar, eğitmen kırmızı kutuyu okumadan kapatır
 * İkisi de test ediliyor.
 */

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Yalnız komut satırından çalışır.'); }

define('RITIM', 1);
require __DIR__ . '/../includes/helpers.php';

$GECTI = 0; $KALDI = 0; $HATALAR = [];

function bolum(string $ad): void { echo "\n— {$ad} —\n"; }

/** Metin işaretlenmeli. */
function yakalanmali(string $metin, string $neden): void
{
    global $GECTI, $KALDI, $HATALAR;
    $b = locked_language_flags($metin);
    if ($b) { $GECTI++; echo "  \u{2714} {$neden}\n"; }
    else { $KALDI++; $HATALAR[] = "KAÇIRDI: {$neden} — \"{$metin}\""; echo "  \u{2718} KAÇIRDI: {$neden}\n"; }
}

/** Metin işaretlenMEmeli (gözlemsel, meşru). */
function temizGecmeli(string $metin, string $neden): void
{
    global $GECTI, $KALDI, $HATALAR;
    $b = locked_language_flags($metin);
    if (!$b) { $GECTI++; echo "  \u{2714} {$neden}\n"; }
    else {
        $KALDI++;
        $HATALAR[] = "YANLIŞ POZİTİF: {$neden} — \"{$metin}\" → " . implode(', ', $b);
        echo "  \u{2718} YANLIŞ POZİTİF: {$neden}  [" . implode(', ', $b) . "]\n";
    }
}

echo "Kilitli dil denetimi testi\n";

bolum('Açık yasaklı sözcükler (CLAUDE.md §2)');
yakalanmali('Terapi sürecinde ilerleme var.', 'terapi');
yakalanmali('Tedaviye iyi yanıt veriyor.', 'tedavi');
yakalanmali('DEHB tanısı olan öğrenci.', 'DEHB');
yakalanmali('Hasta seansa geç geldi.', 'hasta');
yakalanmali('Semptomları azaldı.', 'semptom');
yakalanmali('Klinik gözlem yapıldı.', 'klinik');
yakalanmali('Doktordan teşhis aldı.', 'teşhis');

bolum('Psödobilim (§2: titreşim / rezonans / enerji / hücresel)');
yakalanmali('Ritmin titreşimi bedeni dengeler.', 'titreşim');
yakalanmali('Rezonans etkisiyle rahatlıyor.', 'rezonans');
yakalanmali('Enerjisi dengelendi.', 'ENERJİ — eski listede hiç yoktu');
yakalanmali('Hücresel düzeyde etki eder.', 'hücresel');

bolum('ÖRTÜK sonuç vaadi — eski liste bunların HİÇBİRİNİ yakalamıyordu');
yakalanmali('Bu çalışmalar odaklanmasına yardımcı oluyor.', 'yardımcı ol');
yakalanmali('Dönem içinde belirgin gelişme gösterdi.', 'gelişme göster');
yakalanmali('Akranlarına göre daha hızlı ilerliyor.', 'akran');
yakalanmali('Derse katılımı özgüvenini artırdı.', 'özgüven');
yakalanmali('Çalışmalar dikkatine katkı sağlıyor.', 'katkı sağla');
yakalanmali('Ritim çalışması sakinleşmesine yarıyor.', 'sakinleş / yara');
yakalanmali('Bilişsel gelişimi destekliyor.', 'destekliyor / gelişim');

bolum('Normatif değerlendirme');
yakalanmali('Yaşına göre geride kalıyor.', 'yaşına göre / geride kal');
yakalanmali('Ortalamanın altında performans.', 'ortalamanın');
yakalanmali('Bu alanda yetersiz kaldı.', 'yetersiz');

bolum('YANLIŞ POZİTİF olmamalı — gözlemsel, meşru cümleler');
temizGecmeli('Kalıbı ilk denemede tanıdı.', '"tanıdı" tanı-koyma değil');
temizGecmeli('Gruptaki arkadaşlarıyla tanıştı.', '"tanıştı" tanı-koyma değil');
temizGecmeli('Ritmi doğru tanımladı.', '"tanımladı" tanı-koyma değil');
temizGecmeli('Eğitmen metronomu tanıttı.', '"tanıttı" tanı-koyma değil — ders anlatımı');
temizGecmeli('Enstrüman tanıtımı yapıldı.', '"tanıtım" tanı-koyma değil');
temizGecmeli('Kendi hatasını fark edip düzeltti.', '"düzeltti" — ogrenci-rapor.php hazır cümlesi');
temizGecmeli('Sekiz vuruşluk kalıbı ezberden tekrarladı.', 'düz gözlem');
temizGecmeli('Metronomla 96 BPM tempoda çalıştı.', 'düz gözlem');
temizGecmeli('Oturumun tamamına katıldı.', 'düz gözlem');
temizGecmeli('Çağrı–cevap etkinliğinde sırasını bekledi.', 'düz gözlem');
temizGecmeli('Bu hafta üç gün ev çalışması işaretlendi.', 'düz gözlem');

bolum('Büyük/küçük harf ve Türkçe karakter');
yakalanmali('TERAPİ', 'büyük harf');
yakalanmali('Tanı konuldu.', 'büyük İ ile başlayan tanı');
temizGecmeli('TANIDI', 'büyük harf istisnası da çalışmalı');

echo "\n=================================\n";
printf("  Geçen: %d   Kalan: %d\n", $GECTI, $KALDI);
if ($HATALAR) {
    echo "\nBaşarısız:\n";
    foreach ($HATALAR as $h) { echo "  • {$h}\n"; }
}
echo "=================================\n";
exit($KALDI === 0 ? 0 : 1);
