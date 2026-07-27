<?php
/** Teknik silme (yalnız POST). Oturumlarda kullanılmışsa reddedilir. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { redirect('teknikler.php'); }
csrf_check('teknikler.php');

$id = (int)($_POST['id'] ?? 0);
$teknik = technique_get($id);
if (!$teknik) {
    flash_set('hata', 'Teknik bulunamadı.');
    redirect('teknikler.php');
}
$res = technique_delete($id);
if ($res['ok']) {
    flash_set('basari', $teknik['ad'] . ' tekniği silindi.');
    redirect('teknikler.php');
}
flash_set('hata', $res['error']);
redirect('teknik.php?id=' . $id);
