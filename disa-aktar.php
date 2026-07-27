<?php
/**
 * CSV dışa aktarma — kendi analizin için (Excel/LibreOffice).
 * Türkçe Excel uyumu: UTF-8 BOM + noktalı virgül ayraç.
 * tur=protokol → tüm protokol sonuçları; tur=yoklama → tüm yoklama kayıtları.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$tur = (string)($_GET['tur'] ?? '');
if (!in_array($tur, ['protokol', 'yoklama'], true)) { not_found('Bilinmeyen dışa aktarma türü.'); }

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="ritim-' . $tur . '-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store');

$cikti = fopen('php://output', 'w');
fwrite($cikti, "\xEF\xBB\xBF"); // BOM: Excel'in UTF-8 tanıması için
$yaz = fn(array $satir) => fputcsv($cikti, $satir, ';', '"', '\\');

if ($tur === 'protokol') {
    $yaz(['Tarih', 'Saat', 'Öğrenci', 'Grup', 'Protokol', 'Skor (0-100)', 'BPM', 'Kaynak', 'Standart ölçüm', 'Not']);
    $st = db()->query('SELECT p.*, o.kod, g.ad AS grup_ad
                         FROM protokol_sonuclari p
                         JOIN ogrenciler o ON o.id = p.ogrenci_id
                         LEFT JOIN gruplar g ON g.id = o.grup_id
                        ORDER BY p.created_at, p.id');
    foreach ($st->fetchAll() as $r) {
        $yaz([
            substr($r['created_at'], 0, 10),
            substr($r['created_at'], 11, 5),
            $r['kod'],
            $r['grup_ad'] ?? '',
            PROTOKOL_LABELS[$r['protokol']] ?? $r['protokol'],
            (int)$r['skor'],
            $r['bpm'] !== null ? (int)$r['bpm'] : '',
            ($r['kaynak'] ?? 'atolye') === 'ev' ? 'Ev' : 'Atölye',
            (int)($r['standart'] ?? 0) === 1 ? 'Evet' : 'Hayır',
            (string)$r['notlar'],
        ]);
    }
} else {
    $yaz(['Tarih', 'Hafta', 'Grup', 'Öğrenci', 'Durum', 'Gözlem notu']);
    $st = db()->query('SELECT s.tarih, s.hafta_no, g.ad AS grup_ad, o.kod, k.durum, k.gozlem_notu
                         FROM katilim k
                         JOIN oturumlar s ON s.id = k.oturum_id
                         JOIN gruplar g ON g.id = s.grup_id
                         JOIN ogrenciler o ON o.id = k.ogrenci_id
                        ORDER BY s.tarih, g.ad, o.kod');
    foreach ($st->fetchAll() as $r) {
        $yaz([
            $r['tarih'],
            (int)$r['hafta_no'],
            $r['grup_ad'],
            $r['kod'],
            KATILIM_LABELS[$r['durum']] ?? $r['durum'],
            (string)$r['gozlem_notu'],
        ]);
    }
}
fclose($cikti);
exit;
