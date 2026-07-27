<?php
/**
 * PHP yerleşik sunucusu (php -S) için yönlendirici.
 * Veritabanı ve uygulama içi dosyalara doğrudan web erişimini kapatır;
 * diğer istekleri normal biçimde sunar. Apache altında aynı işi .htaccess yapar.
 */
$yol = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if (preg_match('#^/(storage|includes)(/|$)#i', $yol)) {
    http_response_code(403);
    exit('Erişim engellendi.');
}
return false;
