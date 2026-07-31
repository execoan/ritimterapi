<?php
/**
 * PHP yerleşik sunucusu (php -S) için yönlendirici.
 * Veritabanı, sırlar, uygulama içi kod, test ve depo dosyalarına doğrudan
 * web erişimini kapatır; diğer istekleri normal biçimde sunar.
 * Apache altında aynı işi .htaccess yapar.
 *
 * GÜVENLİK NOTU — neden bu kadar ayrıntılı:
 * Önceki sürüm yalnız '/' ayırıcısına bakıyordu ve şu istek 200 dönüyordu:
 *     GET /storage%5Critim.sqlite
 * %5C = ters bölü. rawurldecode sonrası yol "/storage\ritim.sqlite" oluyor;
 * "^/storage(/|$)" deseni eşleşmiyor, ama WINDOWS'ta '\' de yol ayırıcı
 * olduğu için sunucu dosyayı veriyordu — yani TÜM veritabanı (öğrenciler,
 * veli notları, erişim kodları) girişsiz indirilebiliyordu. Uygulama
 * start.bat ile 0.0.0.0'a bağlandığından bu, aynı ağdaki herkese açıktı.
 *
 * Ders: yol denetimi ÖNCE normalleştirme ister. Aşağıdaki sıra bilinçli:
 *   1) yüzde-çöz  2) '\' → '/'  3) tekrar eden '/' sadeleştir
 *   4) ancak ondan sonra desen denetimi
 */

/*
 * DİKKAT — parse_url BURADA KULLANILMAZ. Ölçtüm:
 *   parse_url('//storage/ritim.sqlite', PHP_URL_PATH) === '/ritim.sqlite'
 * Çift bölüyle başlayan istekte "storage" AUTHORITY (host) sanılıp yol
 * parçası düşüyor; denetim yanlış dizeyi görüyordu. Ham REQUEST_URI'den
 * sorgu dizesini elle kesmek bu tuzağı tamamen ortadan kaldırıyor.
 */
$yol = (string)($_SERVER['REQUEST_URI'] ?? '/');
if (($s = strpos($yol, '?')) !== false) { $yol = substr($yol, 0, $s); }
if (($s = strpos($yol, '#')) !== false) { $yol = substr($yol, 0, $s); }

/* 1) Yüzde kodlamasını çöz. Çift kodlama (%252e) tek çözümde "%2e" olarak
      kalır ve var olmayan bir dosya adına denk gelir (404). */
$ham = rawurldecode($yol);

/* 2) Ters bölüyü normalleştir: Windows'ta yol ayırıcı, Linux'ta dosya adı
      karakteri — ikisinde de engellemek doğru taraf. */
$ham = str_replace('\\', '/', $ham);

/* 3) Tekrar eden ayırıcıları sadeleştir ("//storage" gibi kaçışlara karşı) */
$ham = preg_replace('#/+#', '/', $ham);
if (!str_starts_with($ham, '/')) { $ham = '/' . $ham; }

$engel = false;

/* Yol gezinmesi ve null bayt */
if (str_contains($ham, '..') || str_contains($ham, "\0")) { $engel = true; }

/* Uygulama içi dizinler — web'e hiç açılmaz */
if (preg_match('#^/(storage|includes|test|docs|node_modules)(/|$)#i', $ham)) { $engel = true; }

/* Nokta ile başlayan her yol parçası (.git, .github, .env, .gitignore …) */
foreach (explode('/', $ham) as $parca) {
    if ($parca !== '' && $parca[0] === '.') { $engel = true; break; }
}

/*
 * Uzantı kara listesi — dizin denetimi bir gün delinse bile veri sızmasın.
 * Son emniyet katmanı; yukarıdaki kuralların tek başına yetmesi beklenir.
 */
if (preg_match('#\.(sqlite|sqlite-wal|sqlite-shm|db|sql|bak|ini|log|bat|cmd|ps1|md|yml|yaml)$#i', $ham)) { $engel = true; }

if ($engel) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Erişim engellendi.');
}

return false;
