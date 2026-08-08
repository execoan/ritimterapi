<?php
/**
 * VERİ TUTARLILIK PAKETİ — sentetik veriyle uçtan uca sayı doğrulaması.
 *
 * Duman testi "sayfa açılıyor mu"ya bakar; bu paket "sayfadaki SAYILAR
 * doğru mu"ya bakar: panel göstergeleri, katılım oranı, ilk→son ölçüm,
 * gürültü bandı kararları, CSV satır sayıları — hepsi veritabanından
 * bağımsız hesaplanıp çıktıyla karşılaştırılır.
 *
 * Kendi izole deposunu ve sunucusunu kurar, sentetik üreticiyi (bkz.
 * test/sentetik-veri.php) o depoda koşar; GERÇEK veriye dokunmaz.
 *
 * Çalıştırma:  php test/sentetik.test.php
 */
declare(strict_types=1);

$KOK   = dirname(__DIR__);
$TEMP  = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'ritim-sentetik-' . bin2hex(random_bytes(4));
$SIFRE = bin2hex(random_bytes(8));
$GECTI = 0;
$KALDI = 0;
$BASARISIZLAR = [];

function dogrula(bool $kosul, string $ad, string $detay = ''): void
{
    global $GECTI, $KALDI, $BASARISIZLAR;
    if ($kosul) { $GECTI++; echo "  \u{2714} {$ad}\n"; }
    else {
        $KALDI++;
        $BASARISIZLAR[] = $ad . ($detay !== '' ? " — {$detay}" : '');
        echo "  \u{2718} {$ad}" . ($detay !== '' ? "  [{$detay}]" : '') . "\n";
    }
}
function bolum(string $ad): void { echo "\n— {$ad} —\n"; }

function dizin_sil(string $dizin): void
{
    if (!is_dir($dizin)) { return; }
    foreach (scandir($dizin) ?: [] as $ad) {
        if ($ad === '.' || $ad === '..') { continue; }
        $yol = $dizin . DIRECTORY_SEPARATOR . $ad;
        is_dir($yol) ? dizin_sil($yol) : @unlink($yol);
    }
    @rmdir($dizin);
}
register_shutdown_function(function () {
    global $SUNUCU, $TEMP;
    if (isset($SUNUCU) && is_resource($SUNUCU)) { proc_terminate($SUNUCU); proc_close($SUNUCU); }
    dizin_sil($TEMP);
});

/* ---------- HTTP istemcisi (smoke ile aynı desen) ---------- */
function istek(string $metod, string $yol, ?array $veri, array &$cerez): array
{
    global $TABAN;
    $basliklar = "Accept: text/html\r\n";
    if ($cerez) {
        $p = [];
        foreach ($cerez as $k => $v) { $p[] = $k . '=' . $v; }
        $basliklar .= 'Cookie: ' . implode('; ', $p) . "\r\n";
    }
    $icerik = '';
    if ($veri !== null) {
        $icerik = http_build_query($veri);
        $basliklar .= "Content-Type: application/x-www-form-urlencoded\r\n";
    }
    $ctx = stream_context_create(['http' => ['method' => $metod, 'header' => $basliklar,
        'content' => $icerik, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 20]]);
    $govde = @file_get_contents($TABAN . $yol, false, $ctx);
    $ham = $http_response_header ?? [];
    $durum = 0;
    foreach ($ham as $satir) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $satir, $m)) { $durum = (int)$m[1]; }
        if (stripos($satir, 'Set-Cookie:') === 0
            && preg_match('/Set-Cookie:\s*([^=]+)=([^;]*)/i', $satir, $m)) {
            $cerez[trim($m[1])] = trim($m[2]);
        }
    }
    return ['durum' => $durum, 'govde' => (string)$govde];
}
function git_(string $yol, array &$c): array { return istek('GET', $yol, null, $c); }
function gonder(string $yol, array $v, array &$c): array { return istek('POST', $yol, $v, $c); }
function csrf_al(string $yol, array &$c): string
{
    return preg_match('/name="csrf_token" value="([^"]+)"/', git_($yol, $c)['govde'], $m) ? $m[1] : '';
}

/* ---------- Ölçüm çekirdeğinin kopyası (beklenti hesabı için) ----------
   Bilerek BAĞIMSIZ yazıldı: sayfa da model de aynı hatayı yapıyorsa
   modeli kopyalamak hatayı gizlerdi. Formül docs/olcum-kilavuzu.md'deki
   tanımın kendisidir: uç blok medyanı + MDC95 = 1,96 × RMS(ardışık fark). */
function medyan(array $a): float
{
    sort($a);
    $n = count($a);
    return $n % 2 ? (float)$a[intdiv($n, 2)] : ($a[$n / 2 - 1] + $a[$n / 2]) / 2;
}
function blok_boyu(int $n): int { return $n >= 6 ? 3 : ($n >= 4 ? 2 : 1); }
function beklenen_karsilastirma(array $skorlar): array
{
    $n = count($skorlar);
    $k = blok_boyu($n);
    $ilk = (int)round(medyan(array_slice($skorlar, 0, $k)));
    $son = (int)round(medyan(array_slice($skorlar, -$k)));
    $mdc = null;
    if ($n >= 3) {
        $kareler = [];
        for ($i = 1; $i < $n; $i++) { $kareler[] = ($skorlar[$i] - $skorlar[$i - 1]) ** 2; }
        $mdc = 1.96 * sqrt(array_sum($kareler) / count($kareler));
    }
    return ['ilk' => $ilk, 'son' => $son, 'fark' => $son - $ilk, 'mdc' => $mdc,
            'anlamli' => $mdc === null ? null : abs($son - $ilk) >= $mdc];
}

/* =================================================================
   HAZIRLIK: depo + sunucu + sentetik veri
   ================================================================= */
echo "RitimTerapi veri tutarlılık paketi\n";
if (!mkdir($TEMP, 0755, true)) { fwrite(STDERR, "Geçici dizin açılamadı.\n"); exit(1); }
file_put_contents($TEMP . '/gizli.php',
    "<?php\ndefine('PANEL_KULLANICILAR', ['admin' => '{$SIFRE}']);\ndefine('HIZLI_GIRIS', false);\n");

$PORT = 0;
for ($p = 18200; $p <= 18299; $p++) {
    $s = @fsockopen('127.0.0.1', $p, $hn, $h, 0.2);
    if ($s) { fclose($s); continue; }
    $PORT = $p;
    break;
}
if ($PORT === 0) { fwrite(STDERR, "Boş port yok.\n"); exit(1); }
$TABAN = "http://127.0.0.1:{$PORT}/";
putenv('RITIM_STORAGE=' . $TEMP);
$SUNUCU = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$PORT}", 'router.php'],
    [1 => ['file', $TEMP . '/sunucu.log', 'a'], 2 => ['file', $TEMP . '/sunucu.log', 'a']],
    $borular, $KOK, null, ['bypass_shell' => true]);

$jar = [];
$hazir = false;
for ($i = 0; $i < 100; $i++) {
    usleep(150000);
    if (git_('giris.php', $jar)['durum'] === 200) { $hazir = true; break; }
}
if (!$hazir) { fwrite(STDERR, "Sunucu açılmadı.\n"); exit(1); }

/* Sentetik üretici aynı depoda koşar (göç+seed ilk istekle tamamlandı) */
putenv('RITIM_SENTETIK_ONAY=1');
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($KOK . '/test/sentetik-veri.php')
    . ' --onay 2>&1', $uretimCiktisi, $uretimKodu);
if ($uretimKodu !== 0) {
    fwrite(STDERR, "Üretici başarısız:\n" . implode("\n", $uretimCiktisi) . "\n");
    exit(1);
}
echo "Sentetik veri hazır (depo: {$TEMP})\n";
$db = new PDO('sqlite:' . $TEMP . '/ritim.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
function sq(string $sql): array { global $db; return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
function s1(string $sql) { global $db; return $db->query($sql)->fetchColumn(); }
function ogr_id(string $kod): int
{
    global $db;
    $st = $db->prepare('SELECT id FROM ogrenciler WHERE kod = ?');
    $st->execute([$kod]);
    return (int)$st->fetchColumn();
}

/* Giriş */
$t = csrf_al('giris.php', $jar);
gonder('giris.php', ['csrf_token' => $t, 'kullanici' => 'admin', 'sifre' => $SIFRE], $jar);
dogrula(str_contains(git_('panel.php', $jar)['govde'], 'Aktif grup'), 'eğitmen girişi yapıldı');

/* =================================================================
   A) PANEL GÖSTERGELERİ == SQL
   ================================================================= */
bolum('Panel göstergeleri');
$panel = git_('panel.php', $jar)['govde'];
$aktifGrup = (int)s1('SELECT COUNT(*) FROM gruplar WHERE aktif = 1');
$aktifOgr  = (int)s1('SELECT COUNT(*) FROM ogrenciler WHERE aktif = 1');
dogrula((bool)preg_match('#<div class="sayi">' . $aktifGrup . '</div>\s*<div class="etiket">Aktif grup#', $panel),
    "aktif grup sayısı doğru ({$aktifGrup})");
dogrula((bool)preg_match('#<div class="sayi">' . $aktifOgr . '</div>\s*<div class="etiket">Aktif öğrenci#', $panel),
    "aktif öğrenci sayısı doğru ({$aktifOgr})");

$bekleyenSql = (int)s1("SELECT COUNT(*) FROM oturumlar s WHERE s.tarih <= date('now','localtime')
    AND NOT EXISTS (SELECT 1 FROM katilim k WHERE k.oturum_id = s.id)");
dogrula($bekleyenSql >= 2, "bekleyen yoklama üretildi ({$bekleyenSql})");
dogrula(str_contains($panel, 'Bekleyen yoklama') || str_contains($panel, 'bekleyen yoklama')
    || str_contains($panel, 'Yoklama bekleyen'), 'panel bekleyen yoklamaları duyuruyor');

/* Paket uyarısı: kalan <= 2 olan paket panelde görünmeli */
$dusukPaket = sq("SELECT p.ogrenci_id, o.kod, p.toplam_seans,
        (SELECT COUNT(*) FROM katilim k JOIN oturumlar s ON s.id = k.oturum_id
          WHERE k.ogrenci_id = p.ogrenci_id AND k.durum IN ('katildi','gec')
            AND s.tarih >= p.baslangic) AS kullanilan
    FROM paketler p JOIN ogrenciler o ON o.id = p.ogrenci_id");
$uyariBekleniyor = array_values(array_filter($dusukPaket,
    fn($p) => ((int)$p['toplam_seans'] - (int)$p['kullanilan']) <= 2));
if ($uyariBekleniyor) {
    dogrula(str_contains($panel, 'Seans paketi bitmek üzere')
         && str_contains($panel, $uyariBekleniyor[0]['kod']),
        'düşük paket uyarısı panelde (' . $uyariBekleniyor[0]['kod'] . ')');
} else {
    dogrula(!str_contains($panel, 'Seans paketi bitmek üzere'), 'paket uyarısı gereksiz yere çıkmıyor');
}

/* =================================================================
   B) ÖĞRENCİ DETAYI — katılım oranı ve çoklu üyelik
   ================================================================= */
bolum('Öğrenci detayı');
$serceId = ogr_id('SERÇE-01');
$ogrSayfa = git_('ogrenci.php?id=' . $serceId, $jar)['govde'];
$toplamK = (int)s1("SELECT COUNT(*) FROM katilim WHERE ogrenci_id = {$serceId}");
$gelenK  = (int)s1("SELECT COUNT(*) FROM katilim WHERE ogrenci_id = {$serceId} AND durum IN ('katildi','gec')");
$beklenenOran = $toplamK ? (int)round(100 * $gelenK / $toplamK) : null;
dogrula($beklenenOran !== null
     && (bool)preg_match('#>' . $beklenenOran . '%<#', $ogrSayfa),
    "katılım oranı SQL ile aynı ({$beklenenOran}% · {$gelenK}/{$toplamK})",
    'sayfada bulunan: ' . (preg_match('#<div class="sayi">(\d+)%#', $ogrSayfa, $m) ? $m[1] . '%' : 'yok'));

$kartalSayfa = git_('ogrenci.php?id=' . ogr_id('KARTAL-02'), $jar)['govde'];
dogrula(str_contains($kartalSayfa, 'Çocuk A') && str_contains($kartalSayfa, 'Çocuk B'),
    'çoklu grup üyeliği iki grubu da gösteriyor');

$grupsuz = git_('ogrenci.php?id=' . ogr_id('KIRLANGIÇ-12'), $jar);
dogrula($grupsuz['durum'] === 200 && !str_contains($grupsuz['govde'], 'Fatal'),
    'grupsuz öğrenci sayfası hatasız (null grup yolu)');
$pasif = git_('ogrenci.php?id=' . ogr_id('LEYLEK-07'), $jar)['govde'];
dogrula(str_contains($pasif, 'Pasif') || str_contains($pasif, 'pasif'),
    'pasif öğrenci durumunu gösteriyor');

/* =================================================================
   C) ÖLÇÜM RAPORU — ilk→son ve gürültü bandı kararları
   ================================================================= */
bolum('Ölçüm raporu (gürültü bandı kararları)');
$from = (new DateTime('monday this week'))->modify('-10 weeks')->format('Y-m-d');
$to = date('Y-m-d');

function skor_serisi(int $ogrId, string $protokol, string $varyant = ''): array
{
    global $db;
    $st = $db->prepare('SELECT skor FROM protokol_sonuclari
        WHERE ogrenci_id = ? AND protokol = ? AND varyant = ? ORDER BY created_at, id');
    $st->execute([$ogrId, $protokol, $varyant]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}
$raporSerce = git_("ogrenci-rapor.php?ogrenci_id={$serceId}&from={$from}&to={$to}", $jar)['govde'];
$bek = beklenen_karsilastirma(skor_serisi($serceId, 'vurus_tutturma'));
dogrula(str_contains($raporSerce, '<svg'), 'rapor merkezi grafik çiziyor');
dogrula(str_contains($raporSerce, '>' . $bek['ilk'] . '</strong>')
     && str_contains($raporSerce, '>' . $bek['son'] . '</strong>'),
    "ilk→son medyanları bağımsız hesapla aynı ({$bek['ilk']}→{$bek['son']})");
dogrula($bek['anlamli'] === true && str_contains($raporSerce, 'üstünde'),
    'belirgin gelişen seri "bandın üstünde" olarak raporlandı');

$kartalId = ogr_id('KARTAL-02');
$raporKartal = git_("ogrenci-rapor.php?ogrenci_id={$kartalId}&from={$from}&to={$to}", $jar)['govde'];
$bekK = beklenen_karsilastirma(skor_serisi($kartalId, 'vurus_tutturma'));
dogrula($bekK['anlamli'] === false && str_contains($raporKartal, 'ayırt edilemiyor'),
    'gürültülü seri "ölçümle ayırt edilemiyor" diyor');

$raporMarti = git_('ogrenci-rapor.php?ogrenci_id=' . ogr_id('MARTI-03') . "&from={$from}&to={$to}", $jar)['govde'];
dogrula(str_contains($raporMarti, 'en az 3 ölçüm'),
    'iki ölçümlü seride karar verilmiyor (en az 3 uyarısı)');

$raporPelikan = git_('ogrenci-rapor.php?ogrenci_id=' . ogr_id('PELİKAN-10') . "&from={$from}&to={$to}", $jar)['govde'];
dogrula(str_contains($raporPelikan, '3:2') && str_contains($raporPelikan, '4:3'),
    'poliritim varyantları AYRI seriler olarak listeleniyor');

/* =================================================================
   D) VELİ RAPORU — skor yok, kilitli dil
   ================================================================= */
bolum('Veli raporu');
$veli = git_("rapor-veli.php?ogrenci_id={$serceId}&from={$from}&to={$to}", $jar);
dogrula($veli['durum'] === 200, 'veli raporu açılıyor');
dogrula(!str_contains($veli['govde'], '/100') && !preg_match('/\bskor\b/iu', $veli['govde']),
    'veli raporunda protokol skoru YOK (§3 kuralı)');
dogrula(!preg_match('/terapi|tedavi|semptom|DEHB|iyileş|dikkatini geliştir/iu',
        (function (string $h): string {
            $b = strpos($h, 'data-belge>');
            if ($b === false) { return $h; }
            $s = strpos($h, 'yazdirmada-gizle', $b);
            return substr($h, $b, $s !== false ? $s - $b : null);
        })($veli['govde'])),
    'veli raporu belgesinde kilitli dil temiz');

/* =================================================================
   E) SERTİFİKA — ilk/son yalın sayılar
   ================================================================= */
bolum('Katılım belgesi');
$sert = git_("sertifika.php?ogrenci_id={$serceId}&from={$from}&to={$to}&olcumler=1", $jar)['govde'];
dogrula(str_contains($sert, 'Vuruş Tutturma')
     && str_contains($sert, $bek['ilk'] . ' / 100') && str_contains($sert, $bek['son'] . ' / 100'),
    "sertifika ilk/son ölçümleri doğru ({$bek['ilk']}→{$bek['son']})");
dogrula(str_contains($sert, '📏'), 'standart koşul işareti (📏) belgede');
dogrula(str_contains($sert, 'tanı aracı değildir'), 'eğitsel izleme dipnotu belgede');

/* =================================================================
   F) CSV DIŞA AKTARMALAR — satır sayıları
   ================================================================= */
bolum('CSV satır sayıları');
$csvP = git_('disa-aktar.php?tur=protokol', $jar)['govde'];
$beklenenP = (int)s1('SELECT COUNT(*) FROM protokol_sonuclari');
$satirP = substr_count(trim($csvP), "\n");
dogrula($satirP === $beklenenP, "protokol CSV satırı == veritabanı ({$beklenenP})", "bulunan {$satirP}");
$csvY = git_('disa-aktar.php?tur=yoklama', $jar)['govde'];
$beklenenY = (int)s1('SELECT COUNT(*) FROM katilim');
$satirY = substr_count(trim($csvY), "\n");
dogrula($satirY === $beklenenY, "yoklama CSV satırı == veritabanı ({$beklenenY})", "bulunan {$satirY}");

/* =================================================================
   G) RAPORLAR — dönemlik teknik dağılımı
   ================================================================= */
bolum('Dönemlik rapor');
$g1 = (int)s1("SELECT id FROM gruplar WHERE ad LIKE 'Çocuk A%'");
$don = git_("rapor-donemlik.php?grup_id={$g1}&from={$from}&to={$to}", $jar)['govde'];
$topTeknik = sq("SELECT t.ad, COUNT(*) n FROM oturum_teknikleri ot
    JOIN oturumlar s ON s.id = ot.oturum_id AND s.grup_id = {$g1}
        AND s.tarih BETWEEN '{$from}' AND '{$to}'
    JOIN teknikler t ON t.id = ot.teknik_id
    WHERE ot.islendi = 1 GROUP BY t.id ORDER BY n DESC LIMIT 1");
dogrula($topTeknik && str_contains($don, $topTeknik[0]['ad']),
    'dönemlik raporda en çok işlenen teknik listede (' . ($topTeknik[0]['ad'] ?? '—') . ')');
dogrula(str_contains($don, 'Vuruş Tutturma'), 'dönemlik rapor protokol tablosunu içeriyor');

/* Hafta numaraları: şablon 12 haftalık; üretilen oturumlar 1..12 olmalı */
$haftalar = sq("SELECT MIN(hafta_no) mn, MAX(hafta_no) mx FROM oturumlar WHERE grup_id = {$g1}");
dogrula((int)$haftalar[0]['mn'] === 1 && (int)$haftalar[0]['mx'] === 12,
    'şablon oturumlarının hafta numaraları 1..12',
    $haftalar[0]['mn'] . '..' . $haftalar[0]['mx']);

/* =================================================================
   H) EV PORTALI — kodla giriş + işaretleme yazıyor mu
   ================================================================= */
bolum('Ev portalı');
$evKodSatir = sq('SELECT id, erisim_kodu FROM ogrenciler WHERE kod = \'ATMACA-05\'');
$evJar = [];
$t = csrf_al('ev.php', $evJar);
gonder('ev.php', ['csrf_token' => $t, 'islem' => 'giris', 'kod' => $evKodSatir[0]['erisim_kodu']], $evJar);
$evSayfa = git_('ev.php', $evJar)['govde'];
dogrula(str_contains($evSayfa, 'ATMACA-05'), 'erişim koduyla portal girişi');
dogrula(str_contains($evSayfa, 'ev mini testi'), 'bu haftanın ödevi portalda görünüyor');

$odevId = (int)s1("SELECT id FROM ev_odevleri WHERE ogrenci_id = {$evKodSatir[0]['id']}
    AND baslangic <= date('now','localtime') AND bitis >= date('now','localtime') LIMIT 1");
if ($odevId) {
    $t = csrf_al('ev.php', $evJar);
    gonder('ev.php', ['csrf_token' => $t, 'islem' => 'isaretle', 'odev_id' => $odevId], $evJar);
    $bugunIsaret = (int)s1("SELECT COUNT(*) FROM ev_tamamlama
        WHERE odev_id = {$odevId} AND tarih = date('now','localtime')");
    dogrula($bugunIsaret === 1, 'portaldan "yaptım" işaretlemesi veritabanına yazıldı');
} else {
    dogrula(false, 'işaretlenecek güncel ödev bulunamadı');
}

/* =================================================================
   SONUÇ
   ================================================================= */
echo "\n=================================\n";
echo "  Geçen: {$GECTI}   Kalan: {$KALDI}\n";
if ($BASARISIZLAR) {
    echo "\nBaşarısız denetimler:\n";
    foreach ($BASARISIZLAR as $b) { echo "  • {$b}\n"; }
}
echo "=================================\n";
exit($KALDI ? 1 : 0);
