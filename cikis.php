<?php
/** Çıkış — oturumu kapatır, tanıtım sayfasına döner (yalnız POST). */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { redirect('panel.php'); }
csrf_check('panel.php');

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: ' . url('index.php'), true, 303);
exit;
