<?php
/**
 * İletişim talepleri — tanıtım sitesindeki iletişim formundan gelen mesajlar.
 * Deneme dersi/oturumu SUNULMAZ; bu yalnız genel bir "bize ulaşın" formudur.
 * Kişisel veri içerir (ad + iletişim): yalnız geri dönüş için tutulur.
 * CSV dışa aktarma yoktur; iş bitince kayıt silinir.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('on-kayitlar.php');
    $islem = (string)($_POST['islem'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($islem === 'durum') {
        pre_registration_set_status($id, (string)($_POST['durum'] ?? ''));
        flash_set('basari', 'Talebin durumu güncellendi.');
    } elseif ($islem === 'sil') {
        pre_registration_delete($id);
        flash_set('basari', 'Talep silindi.');
    }
    redirect('on-kayitlar.php' . (($_POST['filtre'] ?? '') !== '' ? '?durum=' . urlencode((string)$_POST['filtre']) : ''));
}

$filtre = (string)($_GET['durum'] ?? '');
if ($filtre !== '' && !isset(ON_KAYIT_DURUM_LABELS[$filtre])) { $filtre = ''; }
$kayitlar = pre_registrations($filtre);

$PAGE_TITLE = 'İletişim Talepleri';
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik">
  <h1>İletişim Talepleri</h1>
  <span class="rozet rozet-acik"><?= count($kayitlar) ?> talep</span>
</div>

<div class="bilgi-kutu">
  🔒 Bu sayfa <strong>kişisel veri</strong> içerir (ad ve iletişim bilgisi). Tanıtım sitesindeki
  formdan gelir, yalnız geri dönüş için tutulur ve dışa aktarılmaz. Görüşme tamamlanınca
  kaydı silmeniz önerilir. Kişi kayıt olursa Öğrenciler bölümüne <strong>kod/takma adla</strong> eklenir.
</div>

<div class="kart">
  <div class="filtre-satir">
    <a class="btn btn-kucuk <?= $filtre === '' ? 'btn-birincil' : 'btn-golge' ?>"
       href="<?= e(url('on-kayitlar.php')) ?>">Hepsi</a>
    <?php foreach (ON_KAYIT_DURUM_LABELS as $kod => $etiket): ?>
    <a class="btn btn-kucuk <?= $filtre === $kod ? 'btn-birincil' : 'btn-golge' ?>"
       href="<?= e(url('on-kayitlar.php?durum=' . $kod)) ?>"><?= e($etiket) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$kayitlar): ?>
    <div class="bos-durum">Bu filtrede talep yok.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th>Tarih</th><th>Ad</th><th>İletişim</th><th>Kimin için</th>
                 <th>Mesaj / profil</th><th>Durum</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($kayitlar as $k): ?>
        <tr>
          <td style="white-space:nowrap"><?= e(format_date_tr(substr($k['created_at'], 0, 10), false)) ?>
            <small class="alan-ipucu"><?= e(substr($k['created_at'], 11, 5)) ?></small></td>
          <td><strong><?= e($k['ad']) ?></strong></td>
          <td><?= e($k['iletisim']) ?></td>
          <td><?= e(ON_KAYIT_KITLE_LABELS[$k['kitle']] ?? $k['kitle']) ?></td>
          <td>
            <?= e($k['mesaj']) ?>
            <?php if (trim((string)$k['profil']) !== ''): ?>
            <div class="alan-ipucu">🎧 <?= e($k['profil']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" action="<?= e(url('on-kayitlar.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="islem" value="durum">
              <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
              <input type="hidden" name="filtre" value="<?= e($filtre) ?>">
              <select name="durum" class="secim secim-dar" onchange="this.form.submit()">
                <?php foreach (ON_KAYIT_DURUM_LABELS as $kod => $etiket): ?>
                <option value="<?= e($kod) ?>" <?= $k['durum'] === $kod ? 'selected' : '' ?>><?= e($etiket) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td style="text-align:right">
            <form method="post" action="<?= e(url('on-kayitlar.php')) ?>"
                  data-onay="<?= e($k['ad']) ?> adlı talep silinsin mi? (kişisel veri kaldırılır)">
              <?= csrf_field() ?>
              <input type="hidden" name="islem" value="sil">
              <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
              <input type="hidden" name="filtre" value="<?= e($filtre) ?>">
              <button type="submit" class="btn btn-kucuk btn-tehlike">Sil</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
