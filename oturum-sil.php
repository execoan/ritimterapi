<?php
/** Oturum silme (yalnız POST). Plan ve yoklama CASCADE ile silinir. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { redirect('oturumlar.php'); }
csrf_check('oturumlar.php');

$id = (int)($_POST['id'] ?? 0);
$oturum = session_get($id);
if (!$oturum) {
    flash_set('hata', 'Oturum bulunamadı.');
    redirect('oturumlar.php');
}
session_delete($id);
flash_set('basari', $oturum['grup_ad'] . ' — ' . format_date_tr($oturum['tarih'], false) . ' oturumu silindi.');
redirect('oturumlar.php');
