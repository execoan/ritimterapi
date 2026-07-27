<?php
/** Öğrenci silme (yalnız POST). Yoklama kayıtları CASCADE ile silinir. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { redirect('ogrenciler.php'); }
csrf_check('ogrenciler.php');

$id = (int)($_POST['id'] ?? 0);
$ogrenci = student_get($id);
if (!$ogrenci) {
    flash_set('hata', 'Öğrenci bulunamadı.');
    redirect('ogrenciler.php');
}
student_delete($id);
flash_set('basari', $ogrenci['kod'] . ' kodlu öğrenci silindi.');
redirect('ogrenciler.php');
