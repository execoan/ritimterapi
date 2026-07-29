<?php
/**
 * Ölçüm karşılaştırma çekirdeği testi (measure_* fonksiyonları).
 * Kapsam: blok medyanı, gürültü bandı (MDC95), standart seri seçimi,
 * "görevi tanıma" etkisinin blok medyanıyla yumuşatılması.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$temp = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'ritim-olcum-' . bin2hex(random_bytes(4));
if (!mkdir($temp, 0755, true)) { throw new RuntimeException('Geçici dizin açılamadı.'); }
register_shutdown_function(static function () use ($temp): void {
    foreach (glob($temp . DIRECTORY_SEPARATOR . '*') ?: [] as $f) { if (is_file($f)) { @unlink($f); } }
    @rmdir($temp);
});

define('RITIM', 1);
define('STORAGE_DIR', $temp);
require dirname(__DIR__) . '/includes/helpers.php';
require dirname(__DIR__) . '/includes/db.php';
require dirname(__DIR__) . '/includes/model.php';

$gecti = 0;
$kalan = 0;
function kontrol(bool $kosul, string $ad, string $detay = ''): void
{
    global $gecti, $kalan;
    if ($kosul) { $gecti++; echo "  \u{2714} {$ad}\n"; }
    else { $kalan++; echo "  \u{2718} {$ad}" . ($detay !== '' ? "  [{$detay}]" : '') . "\n"; }
}

/** Test serisi kurar: skorlar → measure_compare girdisi. */
function seri(array $skorlar, string $kalite = 'iyi'): array
{
    $out = [];
    foreach ($skorlar as $i => $s) {
        $out[] = ['skor' => $s, 'tarih' => sprintf('2026-01-%02d', $i + 1), 'kalite' => $kalite];
    }
    return $out;
}

echo "Ölçüm karşılaştırma testi\n\n— Blok boyu —\n";
kontrol(measure_block_size(2) === 1, '2 ölçüm → tek uç (blok yok)');
kontrol(measure_block_size(3) === 1, '3 ölçüm → tek uç (bloklar çakışmasın)');
kontrol(measure_block_size(4) === 2, "4 ölçüm → 2'şer blok");
kontrol(measure_block_size(5) === 2, "5 ölçüm → 2'şer blok");
kontrol(measure_block_size(6) === 3, "6 ölçüm → 3'er blok");
kontrol(measure_block_size(12) === 3, "çok ölçümde blok 3'te kalır");

echo "\n— Blok medyanı —\n";
$k = measure_compare(seri([40, 50, 60, 70, 80, 90]));
kontrol($k['ilk'] === 50 && $k['son'] === 80, 'ilk/son blok medyanı alınıyor',
    'ilk=' . $k['ilk'] . ' son=' . $k['son']);
kontrol($k['fark'] === 30, 'fark blok medyanları üzerinden', 'fark=' . $k['fark']);
kontrol($k['blok'] === 3 && $k['adet'] === 6, 'blok ve adet raporlanıyor');

// "Görevi tanıma" etkisi: ilk ölçüm anormal düşük, sonrası kararlı.
$tekUc = measure_compare(seri([10, 70, 72, 71, 73, 72]));
kontrol($tekUc['ilk'] > 10, 'ilk denemedeki çöküş tek başına başlangıç sayılmıyor',
    'ilk=' . $tekUc['ilk']);
kontrol($tekUc['fark'] < 62, 'blok medyanı sahte kazanımı küçültüyor', 'fark=' . $tekUc['fark']);

echo "\n— Gürültü bandı (MDC95) —\n";
kontrol(measure_noise_band([50, 60]) === null, "3'ten az ölçümde band hesaplanmaz");
$sabit = measure_noise_band([70, 70, 70, 70]);
kontrol($sabit !== null && abs($sabit['mdc']) < 0.001, 'dalgalanma yoksa band sıfır');
$dalgali = measure_noise_band([50, 70, 50, 70, 50]);
$duz = measure_noise_band([60, 61, 60, 61, 60]);
kontrol($dalgali['mdc'] > $duz['mdc'], 'dalgalı seride band daha geniş',
    'dalgalı=' . round($dalgali['mdc'], 1) . ' düz=' . round($duz['mdc'], 1));
// MDC95 = 1,96 × RMS(ardışık fark); ±20 salınımda RMS = 20
kontrol(abs($dalgali['mdc'] - 1.96 * 20) < 0.001, 'MDC95 = 1,96 × RMS(ardışık fark)',
    'bulunan=' . round($dalgali['mdc'], 3));

echo "\n— Anlamlılık kararı —\n";
$gurultuIcinde = measure_compare(seri([50, 70, 50, 70, 50, 70]));
kontrol($gurultuIcinde['anlamli'] === false, 'salınan seride fark gürültü içinde sayılıyor',
    'fark=' . $gurultuIcinde['fark'] . ' mdc=' . $gurultuIcinde['mdc']);
$gercekArtis = measure_compare(seri([40, 41, 42, 78, 79, 80]));
kontrol($gercekArtis['anlamli'] === true, 'kararlı ve büyük artış bandın üstünde',
    'fark=' . $gercekArtis['fark'] . ' mdc=' . $gercekArtis['mdc']);
$azOlcum = measure_compare(seri([40, 80]));
kontrol($azOlcum['anlamli'] === null, '2 ölçümde karar verilemiyor (null)');

echo "\n— Seri seçimi ve kalite —\n";
$hepsi = seri([10, 20, 30, 40]);
$std = seri([60, 62, 64]);
kontrol(measure_pick_series($hepsi, $std) === $std, '2+ standart ölçüm varsa yalnız onlar kullanılır');
kontrol(measure_pick_series($hepsi, [$std[0]]) === $hepsi, 'tek standart ölçüm yetmez, tümü kullanılır');
$supheliSeri = array_merge(seri([50, 55, 60], 'eksik'), seri([65, 70, 75], 'iyi'));
$sup = measure_compare($supheliSeri);
kontrol($sup['supheli_olcum'] === 3, 'kalibrasyonsuz ölçümler sayılıyor',
    'bulunan=' . $sup['supheli_olcum']);

echo "\n— Kayıt: sd_ms ve kalite —\n";
run_migrations();
$surum = (int)db()->query('PRAGMA user_version')->fetchColumn();
kontrol($surum === 14, 'göç v14 uygulandı', 'user_version=' . $surum);
db()->exec("INSERT INTO ogrenciler (kod, kayit_tarihi) VALUES ('OLCUM-1', '2026-01-01')");
$oid = (int)db()->lastInsertId();
$res = protocol_result_save(['ogrenci_id' => $oid, 'protokol' => 'vurus_tutturma',
    'skor' => 70, 'bpm' => 72, 'detay' => '{}', 'standart' => 1, 'sd_ms' => 23.6, 'kalite' => 'iyi']);
kontrol($res['ok'], 'sonuç kaydedildi');
$kayit = db()->query('SELECT sd_ms, kalite FROM protokol_sonuclari ORDER BY id DESC LIMIT 1')->fetch();
kontrol((int)$kayit['sd_ms'] === 24, 'sd_ms yuvarlanarak saklandı', 'sd=' . $kayit['sd_ms']);
kontrol($kayit['kalite'] === 'iyi', 'kalite kodu saklandı');
$res2 = protocol_result_save(['ogrenci_id' => $oid, 'protokol' => 'bpm_bulma',
    'skor' => 50, 'detay' => '{}', 'sd_ms' => '', 'kalite' => 'uydurma-kod']);
$kayit2 = db()->query('SELECT sd_ms, kalite FROM protokol_sonuclari ORDER BY id DESC LIMIT 1')->fetch();
kontrol($kayit2['sd_ms'] === null, 'boş sd_ms null saklanır');
kontrol($kayit2['kalite'] === '', 'bilinmeyen kalite kodu reddedilir');

echo "\n=================================\n";
echo "  Geçen: {$gecti}   Kalan: {$kalan}\n";
echo "=================================\n";
exit($kalan === 0 ? 0 : 1);
