<?php
/** Grup detayı — bilgiler, öğrenciler, oturum geçmişi. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$grup = group_get($id);
if (!$grup) { not_found('Grup bulunamadı.'); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('grup.php?id=' . $id);
    $islem = (string)($_POST['islem'] ?? 'guncelle');
    if ($islem === 'uye_ekle') {
        $res = group_member_add($id, (int)($_POST['ogrenci_id'] ?? 0));
        flash_set($res['ok'] ? 'basari' : 'hata',
            $res['ok'] ? 'Katılımcı derse eklendi.' : $res['error']);
    } elseif ($islem === 'uye_cikar') {
        $tamam = group_member_remove($id, (int)($_POST['ogrenci_id'] ?? 0));
        flash_set($tamam ? 'basari' : 'hata',
            $tamam ? 'Katılımcı dersten çıkarıldı; geçmiş kayıtları korundu.' : 'Aktif üyelik bulunamadı.');
    } elseif ($islem === 'duyuru_ekle') {
        $res = group_announcement_save($id, $_POST);
        if (!$res['ok']) { set_old($_POST); }
        flash_set($res['ok'] ? 'basari' : 'hata',
            $res['ok'] ? 'Grup duyurusu yayınlandı.' : $res['error']);
    } elseif ($islem === 'duyuru_durum') {
        $aktif = (int)($_POST['aktif_yap'] ?? 0) === 1;
        $tamam = group_announcement_set_active($id, (int)($_POST['duyuru_id'] ?? 0), $aktif);
        flash_set($tamam ? 'basari' : 'hata',
            $tamam ? ($aktif ? 'Duyuru yeniden yayına alındı.' : 'Duyuru yayından kaldırıldı.')
                   : 'Duyuru bulunamadı.');
    } else {
        $res = group_save($_POST, $id);
        flash_set($res['ok'] ? 'basari' : 'hata', $res['ok'] ? 'Ders bilgileri güncellendi.' : $res['error']);
    }
    redirect('grup.php?id=' . $id);
}

$grup = group_get($id);
$ogrenciler = students_list($id);
$adaylar = students_not_in_group($id);
$oturumlar = sessions_list($id);
$duyurular = group_announcements($id);
$ozelDersDolu = ($grup['tur'] ?? 'grup') === 'ozel' && count($ogrenciler) >= 1;

$PAGE_TITLE = $grup['ad'];
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik">
  <h1><?= e($grup['ad']) ?></h1>
  <span class="rozet rozet-acik"><?= e(GRUP_TUR_LABELS[$grup['tur'] ?? 'grup']) ?></span>
  <?= (int)$grup['aktif'] === 1 ? '<span class="rozet rozet-tamam">Aktif</span>' : '<span class="rozet rozet-gri">Pasif</span>' ?>
  <div class="sag">
    <a class="btn btn-birincil" href="<?= e(url('plan.php?grup_id=' . $id)) ?>">+ Bu gruba oturum planla</a>
    <a class="btn btn-golge" href="<?= e(url('rapor-donemlik.php?grup_id=' . $id)) ?>">Dönem raporu</a>
  </div>
</div>

<div class="kart">
  <div class="kart-baslik">
    <h2>Grup duyuruları</h2>
    <span class="rozet rozet-acik"><?= count($duyurular) ?> duyuru</span>
  </div>
  <p class="alan-ipucu">Buraya yalnızca bütün ders/grup üyelerine açık bilgiler yazın. Kişisel gözlem ve veli notları duyuruya eklenmemelidir.</p>
  <form method="post" action="<?= e(url('grup.php?id=' . $id)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="islem" value="duyuru_ekle">
    <div class="form-grid">
      <label class="form-alan">Başlık
        <input type="text" name="baslik" class="girdi" value="<?= e(old('baslik')) ?>" maxlength="80" required
               placeholder="Örn. Bu haftaki ders malzemesi">
      </label>
      <label class="form-alan">Yayın tarihi
        <input type="date" name="yayin_tarihi" class="girdi" value="<?= e(old('yayin_tarihi', today())) ?>" required>
      </label>
      <label class="form-alan">Son görünme tarihi
        <input type="date" name="bitis_tarihi" class="girdi" value="<?= e(old('bitis_tarihi')) ?>">
        <span class="alan-ipucu">Boş bırakırsanız siz kapatana kadar görünür.</span>
      </label>
      <label class="form-alan form-genis">Duyuru
        <textarea name="mesaj" class="girdi" maxlength="500"
                  placeholder="Grubun tamamına gösterilecek kısa bilgi…"><?= e(old('mesaj')) ?></textarea>
      </label>
    </div>
    <div class="form-butonlar">
      <button type="submit" class="btn btn-birincil">Duyuru yayınla</button>
    </div>
  </form>

  <?php if ($duyurular): ?>
  <div class="tablo-sar" style="margin-top:1rem">
    <table class="tablo">
      <thead><tr><th scope="col">Duyuru</th><th scope="col">Görünürlük</th><th scope="col">Durum</th><th scope="col"></th></tr></thead>
      <tbody>
      <?php foreach ($duyurular as $d):
          $gelecek = $d['yayin_tarihi'] > today();
          $suresiDoldu = $d['bitis_tarihi'] && $d['bitis_tarihi'] < today();
          $gorunur = (int)$d['aktif'] === 1 && !$gelecek && !$suresiDoldu;
      ?>
        <tr>
          <td>
            <strong><?= e($d['baslik']) ?></strong>
            <?php if ($d['mesaj']): ?><div class="alan-ipucu"><?= nl2br(e($d['mesaj'])) ?></div><?php endif; ?>
          </td>
          <td>
            <?= e(format_date_tr($d['yayin_tarihi'], false)) ?>
            <?= $d['bitis_tarihi'] ? ' – ' . e(format_date_tr($d['bitis_tarihi'], false)) : ' – süresiz' ?>
          </td>
          <td>
            <?php if ($gorunur): ?><span class="rozet rozet-tamam">Portalda görünür</span>
            <?php elseif ((int)$d['aktif'] !== 1): ?><span class="rozet rozet-gri">Kapalı</span>
            <?php elseif ($gelecek): ?><span class="rozet rozet-bekliyor">Planlandı</span>
            <?php else: ?><span class="rozet rozet-gri">Süresi doldu</span><?php endif; ?>
          </td>
          <td class="sayi">
            <form method="post" action="<?= e(url('grup.php?id=' . $id)) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $id ?>">
              <input type="hidden" name="islem" value="duyuru_durum">
              <input type="hidden" name="duyuru_id" value="<?= (int)$d['id'] ?>">
              <input type="hidden" name="aktif_yap" value="<?= (int)$d['aktif'] === 1 ? 0 : 1 ?>">
              <button type="submit" class="btn btn-kucuk btn-golge">
                <?= (int)$d['aktif'] === 1 ? 'Yayından kaldır' : 'Yeniden yayınla' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="kart">
  <h2>Ders bilgileri</h2>
  <form method="post" action="<?= e(url('grup.php?id=' . $id)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="form-grid">
      <label class="form-alan">Ders / grup adı
        <input type="text" name="ad" class="girdi" value="<?= e($grup['ad']) ?>" maxlength="80" required>
      </label>
      <label class="form-alan">Ders türü
        <select name="tur" class="secim">
          <?php foreach (GRUP_TUR_LABELS as $tur => $etiket): ?>
          <option value="<?= e($tur) ?>" <?= ($grup['tur'] ?? 'grup') === $tur ? 'selected' : '' ?>><?= e($etiket) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="alan-ipucu">Özel derste en fazla bir aktif katılımcı bulunabilir.</span>
      </label>
      <label class="form-alan">Yaş aralığı
        <input type="text" name="yas_araligi" class="girdi" value="<?= e($grup['yas_araligi']) ?>" maxlength="40">
      </label>
      <label class="form-alan">Ders günü
        <select name="gun" class="secim">
          <?php foreach (GUNLER as $no => $ad): ?>
          <option value="<?= $no ?>" <?= (int)$grup['gun'] === $no ? 'selected' : '' ?>><?= e($ad) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-alan">Saat
        <input type="time" name="saat" class="girdi" value="<?= e($grup['saat']) ?>">
      </label>
      <label class="form-alan">Başlangıç tarihi
        <input type="date" name="baslangic_tarihi" class="girdi" value="<?= e($grup['baslangic_tarihi']) ?>">
      </label>
      <label class="form-alan">Durum
        <input type="hidden" name="aktif" value="0">
        <span class="onay-kutu"><input type="checkbox" name="aktif" value="1" <?= (int)$grup['aktif'] === 1 ? 'checked' : '' ?>> Ders aktif</span>
      </label>
    </div>
    <div class="form-butonlar">
      <button type="submit" class="btn btn-birincil">Kaydet</button>
    </div>
  </form>
</div>

<div class="kart">
  <div class="kart-baslik">
    <h2>Katılımcılar</h2>
    <span class="rozet rozet-acik"><?= count($ogrenciler) ?> kişi</span>
  </div>
  <p class="alan-ipucu">Mevcut bir kişiyi bu derse ekleyebilirsiniz. Aynı kişi özel dersinin yanında birden fazla grup dersinde yer alabilir.</p>
  <?php if ($ozelDersDolu): ?>
    <div class="bos-durum">Bu özel derste bir aktif katılımcı var. Yeni kişi eklemek için önce mevcut üyeliği kaldırın.</div>
  <?php elseif ($adaylar): ?>
    <form method="post" action="<?= e(url('grup.php?id=' . $id)) ?>" class="filtre-satir" style="margin-bottom:1rem">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="islem" value="uye_ekle">
      <label class="form-alan">Kayıtlı kişi
        <select name="ogrenci_id" class="secim" required>
          <option value="">Kişi seçin</option>
          <?php foreach ($adaylar as $aday): ?>
          <option value="<?= (int)$aday['id'] ?>"><?= e($aday['kod']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit" class="btn btn-birincil">Gruba ekle</button>
      <a class="btn btn-golge" href="<?= e(url('ogrenciler.php')) ?>">Yeni kişi oluştur</a>
    </form>
  <?php else: ?>
    <p class="alan-ipucu">Eklenebilecek başka aktif kişi yok. <a href="<?= e(url('ogrenciler.php')) ?>">Yeni kişi oluşturun.</a></p>
  <?php endif; ?>
  <?php if (!$ogrenciler): ?>
    <div class="bos-durum">Bu derste henüz katılımcı yok.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th scope="col">Kod</th><th scope="col">Doğum yılı</th><th scope="col">Kayıt tarihi</th><th scope="col">Durum</th><th scope="col"></th></tr></thead>
      <tbody>
        <?php foreach ($ogrenciler as $o): ?>
        <tr>
          <td><a href="<?= e(url('ogrenci.php?id=' . (int)$o['id'])) ?>"><?= e($o['kod']) ?></a></td>
          <td><?= $o['dogum_yili'] ? (int)$o['dogum_yili'] : '—' ?></td>
          <td><?= e(format_date_tr($o['kayit_tarihi'], false)) ?></td>
          <td><?= (int)$o['aktif'] === 1 ? '<span class="rozet rozet-tamam">Aktif</span>' : '<span class="rozet rozet-gri">Pasif</span>' ?></td>
          <td class="sayi">
            <form method="post" action="<?= e(url('grup.php?id=' . $id)) ?>"
                  data-onay="<?= e($o['kod']) ?> bu dersten çıkarılsın mı? Oturum ve yoklama geçmişi korunur.">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $id ?>">
              <input type="hidden" name="islem" value="uye_cikar">
              <input type="hidden" name="ogrenci_id" value="<?= (int)$o['id'] ?>">
              <button type="submit" class="btn btn-kucuk btn-golge">Üyeliği kaldır</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="kart">
  <div class="kart-baslik">
    <h2>Oturum geçmişi</h2>
    <span class="rozet rozet-acik"><?= count($oturumlar) ?> oturum</span>
  </div>
  <?php if (!$oturumlar): ?>
    <div class="bos-durum">Henüz oturum yok. <a href="<?= e(url('plan.php?grup_id=' . $id)) ?>">İlk oturumu planlayın</a>.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th scope="col">Tarih</th><th scope="col">Hafta</th><th scope="col" class="sayi">Teknik</th><th scope="col" class="sayi">Plan süresi</th><th scope="col">Yoklama</th></tr></thead>
      <tbody>
        <?php foreach ($oturumlar as $s): ?>
        <tr>
          <td><a href="<?= e(url('oturum.php?id=' . (int)$s['id'])) ?>"><?= e(format_date_tr($s['tarih'])) ?></a></td>
          <td>Hafta <?= (int)$s['hafta_no'] ?></td>
          <td class="sayi"><?= (int)$s['teknik_sayisi'] ?></td>
          <td class="sayi"><?= (int)$s['plan_sure'] ?> dk</td>
          <td>
            <?php if ((int)$s['yoklama_sayisi'] > 0): ?>
              <span class="rozet rozet-tamam"><?= (int)$s['gelen_sayisi'] ?>/<?= (int)$s['yoklama_sayisi'] ?> geldi</span>
            <?php elseif ($s['tarih'] <= today()): ?>
              <span class="rozet rozet-bekliyor">Bekliyor</span>
            <?php else: ?>
              <span class="rozet rozet-gri">Planlandı</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="kart">
  <h2>Tehlikeli bölge</h2>
  <p class="alan-ipucu">Dersi silmek, bu derse ait <strong>tüm oturum ve yoklama kayıtlarını</strong> da siler.
     Katılımcılar silinmez; diğer ders üyelikleri korunur. Geri alınamaz.</p>
  <form method="post" action="<?= e(url('grup-sil.php')) ?>"
        data-onay="<?= e($grup['ad']) ?> grubunu ve tüm oturum geçmişini silmek üzeresiniz. Bu işlem geri alınamaz. Emin misiniz?">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <button type="submit" class="btn btn-tehlike">Grubu Sil</button>
  </form>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
