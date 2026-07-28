<?php
/**
 * PHP yerleşik sunucusu (php -S) için yönlendirici.
 * Veritabanı, sırlar, uygulama içi kod, test ve depo dosyalarına doğrudan
 * web erişimini kapatır; diğer istekleri normal biçimde sunar.
 * Apache altında aynı işi .htaccess yapar.
 */
$yol = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$ham = rawurldecode($yol);
if (str_contains($ham, '..') || str_contains($ham, "\0")
    || preg_match('#^/(storage|includes|test|docs)(/|$)#i', $ham)
    || preg_match('#(^|/)\.#', $ham)) { // .git, .github, .gitignore, nokta dosyaları
    http_response_code(403);
    exit('Erişim engellendi.');
}
return false;
