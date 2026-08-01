<?php
/**
 * Statik denetleyici — bağımlılıksız.
 *
 * Neden PHPStan değil: projede composer/vendor yok ve olmaması bilinçli bir
 * karar (CLAUDE.md §3 — framework yok, yapı adımı yok). Bunun yerine bu
 * projede GERÇEKTEN hata çıkarmış sınıflar hedeflenir:
 *
 *  1. Sözdizimi   — php -l / node --check (metronom.php bir kez tamamen kırıldı)
 *  2. HTML kapanışı — açılmamış yorum, <script> içinde "<!--" dizisi
 *  3. Tanımsız çağrı — var olmayan yardımcıya yapılan çağrı (ölümcül hata)
 *  4. Ölü kod      — hiç çağrılmayan fonksiyon
 *  5. Görünmez karakter — kaçış hatasından sızan denetim karakteri (0x08 vb.)
 *
 * Çalıştırma:  php test/statik.php
 */
declare(strict_types=1);

$KOK = dirname(__DIR__);
$gecen = 0;
$hatalar = [];

function bolum(string $ad): void { echo "\n— {$ad} —\n"; }
function dogrula(bool $kosul, string $ad, string $ayrinti = ''): void
{
    global $gecen, $hatalar;
    if ($kosul) { $gecen++; echo "  ✔ {$ad}\n"; }
    else { $hatalar[] = $ad . ($ayrinti ? " — {$ayrinti}" : ''); echo "  ✘ {$ad}" . ($ayrinti ? " — {$ayrinti}" : '') . "\n"; }
}

/** Belirtilen uzantıdaki tüm dosyalar (vendor ve depo hariç). */
function dosyalar(string $kok, array $uzantilar): array
{
    $sonuc = [];
    $yineleyici = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($kok, FilesystemIterator::SKIP_DOTS));
    foreach ($yineleyici as $d) {
        $yol = str_replace('\\', '/', $d->getPathname());
        if (preg_match('#/(vendor|storage|\.git|node_modules)/#', $yol)) { continue; }
        if (in_array(strtolower($d->getExtension()), $uzantilar, true)) { $sonuc[] = $yol; }
    }
    sort($sonuc);
    return $sonuc;
}

$phpDosyalar = dosyalar($KOK, ['php']);
$jsDosyalar  = dosyalar($KOK . '/assets/js', ['js']);
echo "Statik denetim: " . count($phpDosyalar) . " PHP, " . count($jsDosyalar) . " JS dosyası\n";

/* =================================================================
   1) SÖZDİZİMİ
   ================================================================= */
bolum('Sözdizimi');
$php = PHP_BINARY;
$bozuk = [];
foreach ($phpDosyalar as $f) {
    exec(escapeshellarg($php) . ' -l ' . escapeshellarg($f) . ' 2>&1', $cikti, $kod);
    if ($kod !== 0) { $bozuk[] = basename($f) . ': ' . trim(implode(' ', $cikti)); }
    $cikti = [];
}
dogrula(!$bozuk, 'tüm PHP dosyaları ayrıştırılıyor', implode('; ', array_slice($bozuk, 0, 3)));

$bozukJs = [];
foreach ($jsDosyalar as $f) {
    exec('node --check ' . escapeshellarg($f) . ' 2>&1', $cikti, $kod);
    if ($kod !== 0) { $bozukJs[] = basename($f); }
    $cikti = [];
}
dogrula(!$bozukJs, 'tüm JS dosyaları ayrıştırılıyor', implode(', ', $bozukJs));

/* =================================================================
   2) HTML BÜTÜNLÜĞÜ (kaynak düzeyinde)
   ================================================================= */
bolum('HTML bütünlüğü');
/* Bir kez şu olmuştu: <script> bloğunun İÇİNDE "<!--<script>" dizisi geçti,
   tarayıcı sayfanın geri kalanını yorum sandı, altı betik hiç yüklenmedi.
   Sunucu 200 döndüğü için HTTP düzeyinde görünmüyordu. */
$yorumBozuk = $betikDizi = [];
foreach ($phpDosyalar as $f) {
    if (basename($f) === 'statik.php') { continue; }   // kendi desenlerini sayar
    /* YALNIZ gerçek HTML'e bak: PHP açıklaması içindeki "<script" ya da
       "<!--" örneği hata değildir. Belirteçleyici ikisini ayırır; ilk
       sürüm regex'le bakıyordu ve bootstrap.php'nin açıklamasına takılmıştı. */
    $html = '';
    foreach (token_get_all(file_get_contents($f)) as $t) {
        if (is_array($t) && $t[0] === T_INLINE_HTML) { $html .= $t[1]; }
    }
    if (substr_count($html, '<!--') !== substr_count($html, '-->')) {
        $yorumBozuk[] = basename($f);
    }
    if (preg_match_all('#<script\b[^>]*>(.*?)</script>#is', $html, $m)) {
        foreach ($m[1] as $govde) {
            if (str_contains($govde, '<!--') || stripos($govde, '<script') !== false) {
                $betikDizi[] = basename($f);
            }
        }
    }
}
dogrula(!$yorumBozuk, 'HTML yorumları dengeli', implode(', ', array_unique($yorumBozuk)));
dogrula(!$betikDizi, 'gömülü betikte ayrıştırıcı kıran dizi yok', implode(', ', array_unique($betikDizi)));

/* =================================================================
   3) TANIMSIZ FONKSİYON ÇAĞRISI
   ================================================================= */
bolum('Tanımsız çağrı');
$tanimli = [];
$govdeler = [];
foreach ($phpDosyalar as $f) {
    $s = file_get_contents($f);
    $govdeler[$f] = $s;
    if (preg_match_all('/^\s*function\s+([a-z_][a-z0-9_]*)\s*\(/mi', $s, $m)) {
        foreach ($m[1] as $ad) { $tanimli[strtolower($ad)] = $f; }
    }
}
$dahiliHarita = array_flip(get_defined_functions()['internal']);

/* Regex yerine BELİRTEÇLEYİCİ: ilk sürüm dizge ve açıklama içindeki metne
   takılıyordu — Türkçe "döner (yalnız POST)" ifadesinde 'ö' çok baytlı
   olduğu için \w sınırı tutmuyor ve 'ner(' bir çağrı sanılıyordu. */
$eksik = [];
foreach ($govdeler as $f => $s) {
    $ts = token_get_all($s);
    $n = count($ts);
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($ts[$i]) || $ts[$i][0] !== T_STRING) { continue; }
        /* Sonraki anlamlı belirteç '(' değilse çağrı değildir */
        $k = $i + 1;
        while ($k < $n && is_array($ts[$k]) && in_array($ts[$k][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { $k++; }
        if ($k >= $n || $ts[$k] !== '(') { continue; }
        /* Önceki anlamlı belirteç: yöntem/özellik/tanım/new ise atla */
        $o = $i - 1;
        while ($o >= 0 && is_array($ts[$o]) && in_array($ts[$o][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { $o--; }
        if ($o >= 0) {
            $onceki = $ts[$o];
            if (is_array($onceki) && in_array($onceki[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON,
                T_FUNCTION, T_NEW, T_CLASS, T_NULLSAFE_OBJECT_OPERATOR], true)) { continue; }
        }
        $ad = strtolower($ts[$i][1]);
        if (isset($tanimli[$ad]) || isset($dahiliHarita[$ad])) { continue; }
        $eksik[] = basename($f) . ':' . $ts[$i][2] . ' ' . $ts[$i][1] . '()';
    }
}
$eksik = array_values(array_unique($eksik));
dogrula(!$eksik, 'çağrılan her fonksiyon tanımlı', implode(', ', array_slice($eksik, 0, 6)));

/* =================================================================
   4) ÖLÜ KOD
   ================================================================= */
bolum('Ölü kod');
$hepsi = implode("\n", $govdeler);
$olu = [];
foreach ($tanimli as $ad => $f) {
    /* Tanım satırı da sayılır; 1 = yalnız tanım, hiç çağrılmıyor */
    if (substr_count(strtolower($hepsi), $ad . '(') <= 1) { $olu[] = $ad; }
}
dogrula(!$olu, 'hiç çağrılmayan PHP fonksiyonu yok', implode(', ', $olu));

/* =================================================================
   5) GÖRÜNMEZ DENETİM KARAKTERİ
   ================================================================= */
bolum('Görünmez karakter');
/* Kaçış hatasından kaynağa sızan 0x08 (geri-al) bir regex'i sessizce
   bozmuştu: gözle görünmüyor, grep'te fark edilmiyor. */
$kirli = [];
foreach (array_merge($phpDosyalar, $jsDosyalar) as $f) {
    $s = file_get_contents($f);
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $s, $m)) {
        $kirli[] = basename($f) . ' (0x' . strtoupper(bin2hex($m[0])) . ')';
    }
}
dogrula(!$kirli, 'kaynakta denetim karakteri yok', implode(', ', $kirli));

/* =================================================================
   SONUÇ
   ================================================================= */
echo "\n=================================\n";
echo "  Geçen: {$gecen}   Kalan: " . count($hatalar) . "\n";
if ($hatalar) {
    echo "\nBaşarısız denetimler:\n";
    foreach ($hatalar as $h) { echo "  • {$h}\n"; }
}
echo "=================================\n";
exit($hatalar ? 1 : 0);
