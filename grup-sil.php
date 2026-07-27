<?php
/** Grup silme (yalnız POST). Oturum geçmişi CASCADE ile silinir. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { redirect('gruplar.php'); }
csrf_check('gruplar.php');

$id = (int)($_POST['id'] ?? 0);
$grup = group_get($id);
if (!$grup) {
    flash_set('hata', 'Grup bulunamadı.');
    redirect('gruplar.php');
}
group_delete($id);
flash_set('basari', $grup['ad'] . ' grubu ve oturum geçmişi silindi.');
redirect('gruplar.php');
