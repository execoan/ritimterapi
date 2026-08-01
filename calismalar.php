<?php
/**
 * Akademik Çalışmalar — teknik kütüphanesinin beslendiği kaynak kayıt defteri.
 * Künye + DOI + nötr dilli özet; teknikler bu kayıtlara teknik.php'den bağlanır.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('calismalar.php');
    $islem = (string)($_POST['islem'] ?? '');
    if ($islem === 'kaydet') {
        $cid = (int)($_POST['calisma_id'] ?? 0) ?: null;
        $res = study_save($_POST, $cid);
        flash_set($res['ok'] ? 'basari' : 'hata', $res['ok'] ? 'Çalışma kaydedildi.' : $res['error']);
        redirect('calismalar.php');
    }
    if ($islem === 'sil') {
        $c = study_get((int)($_POST['id'] ?? 0));
        if ($c) {
            study_delete((int)$c['id']);
            flash_set('basari', 'Çalışma silindi (tekniklerdeki bağlantıları da kaldırıldı).');
        }
        redirect('calismalar.php');
    }
    redirect('calismalar.php');
}

$calismalar = studies_list();
$duzenlenen = isset($_GET['duzenle']) ? study_get((int)$_GET['duzenle']) : null;

$PAGE_TITLE = 'Akademik Çalışmalar';
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik">
  <h1>Akademik Çalışmalar</h1>
  <span class="rozet rozet-acik"><?= count($calismalar) ?> kayıt</span>
  <div class="sag">
    <a class="btn btn-golge" href="<?= e(url('teknikler.php')) ?>"><span class="emoji-sus" aria-hidden="true">←</span> Teknik Kütüphanesi</a>
  </div>
</div>

<div class="kart">
  <div class="bilgi-kutu">
    Bu kayıt defteri, tekniklerin beslendiği hakemli çalışmaları DOI'leriyle tutar.
    Bir tekniğe bağlamak için tekniğin detay sayfasındaki <strong>Bilimsel dayanak</strong>
    kartını kullanın. Özetler nötr dille yazılır; sonuç vaadi içermez.
  </div>
  <?php if (!$calismalar): ?>
    <div class="bos-durum">Henüz çalışma kaydı yok — aşağıdan ekleyin.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th scope="col">Çalışma</th><th scope="col">Tür</th><th scope="col">DOI</th><th scope="col" class="sayi">Bağlı teknik</th><th scope="col"></th></tr></thead>
      <tbody>
        <?php foreach ($calismalar as $c): ?>
        <tr>
          <td>
            <strong><?= e(mb_strimwidth($c['baslik'], 0, 90, '…')) ?></strong><br>
            <span class="alan-ipucu"><?= e(study_citation($c)) ?></span>
          </td>
          <td><?= e(CALISMA_TUR_LABELS[$c['tur']] ?? $c['tur']) ?></td>
          <td>
            <?php if ($c['doi'] !== ''): ?>
            <a href="https://doi.org/<?= e($c['doi']) ?>" target="_blank" rel="noopener"><?= e($c['doi']) ?></a>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td class="sayi"><?= (int)$c['bagli_teknik'] ?></td>
          <td style="text-align:right;white-space:nowrap">
            <a class="btn btn-kucuk btn-golge" href="<?= e(url('calismalar.php?duzenle=' . (int)$c['id'])) ?>#form">Düzenle</a>
            <form method="post" action="<?= e(url('calismalar.php')) ?>" style="display:inline"
                  data-onay="Çalışma silinsin mi? Tekniklerdeki bağlantıları da kalkar.">
              <?= csrf_field() ?>
              <input type="hidden" name="islem" value="sil">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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

<div class="kart" id="form">
  <h2><?= $duzenlenen ? 'Çalışmayı düzenle' : 'Yeni çalışma ekle' ?></h2>
  <form method="post" action="<?= e(url('calismalar.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="kaydet">
    <?php if ($duzenlenen): ?><input type="hidden" name="calisma_id" value="<?= (int)$duzenlenen['id'] ?>"><?php endif; ?>
    <div class="form-grid">
      <label class="form-alan form-genis">Başlık
        <input type="text" name="baslik" class="girdi" value="<?= e($duzenlenen['baslik'] ?? '') ?>" maxlength="300" required>
      </label>
      <label class="form-alan">Yazarlar
        <input type="text" name="yazarlar" class="girdi" value="<?= e($duzenlenen['yazarlar'] ?? '') ?>" maxlength="200"
               placeholder="Örn. Puyjarinet, Bégel et al.">
      </label>
      <label class="form-alan">Yıl
        <input type="number" name="yil" class="girdi" value="<?= e((string)($duzenlenen['yil'] ?? '')) ?>" min="1900" max="2100">
      </label>
      <label class="form-alan">Dergi
        <input type="text" name="dergi" class="girdi" value="<?= e($duzenlenen['dergi'] ?? '') ?>" maxlength="160">
      </label>
      <label class="form-alan">DOI
        <input type="text" name="doi" class="girdi" value="<?= e($duzenlenen['doi'] ?? '') ?>" maxlength="120"
               placeholder="10.xxxx/… (https'li de yapıştırabilirsiniz)">
      </label>
      <label class="form-alan">Tür
        <select name="tur" class="secim">
          <?php foreach (CALISMA_TUR_LABELS as $kod => $ad): ?>
          <option value="<?= e($kod) ?>" <?= ($duzenlenen['tur'] ?? 'diger') === $kod ? 'selected' : '' ?>><?= e($ad) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-alan form-genis">Özet — ne buldu? (nötr dil)
        <textarea name="ozet" class="girdi" rows="3" maxlength="700"><?= e($duzenlenen['ozet'] ?? '') ?></textarea>
      </label>
    </div>
    <div class="form-butonlar">
      <button type="submit" class="btn btn-birincil"><?= $duzenlenen ? 'Kaydet' : 'Çalışma Ekle' ?></button>
      <?php if ($duzenlenen): ?>
      <a class="btn btn-golge" href="<?= e(url('calismalar.php')) ?>">Yeni kayda geç</a>
      <?php endif; ?>
    </div>
  </form>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
