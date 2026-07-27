<?php
/**
 * Eğitim Planı Şablonları — RitimOdak 12 haftalık izler (çocuk / yetişkin).
 * Şablonu görüntüle ve bir gruba tek adımda uygula: her şablon oturumu,
 * teknik planıyla birlikte gerçek oturum olarak oluşturulur.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('sablonlar.php');
    $islem = (string)($_POST['islem'] ?? '');

    if ($islem === 'sablon_olustur') {
        $res = template_save($_POST);
        flash_set($res['ok'] ? 'basari' : 'hata', $res['ok'] ? 'Şablon oluşturuldu — haftalık oturumlarını ekleyin.' : $res['error']);
        redirect('sablonlar.php' . ($res['ok'] ? '?id=' . $res['id'] : ''));
    }
    if ($islem === 'sablon_guncelle') {
        $sid = (int)($_POST['sablon_id'] ?? 0);
        $res = template_save($_POST, $sid);
        flash_set($res['ok'] ? 'basari' : 'hata', $res['ok'] ? 'Şablon bilgileri güncellendi.' : $res['error']);
        redirect('sablonlar.php?id=' . $sid);
    }
    if ($islem === 'sablon_sil') {
        $sid = (int)($_POST['sablon_id'] ?? 0);
        $s = template_get($sid);
        if ($s) {
            template_delete($sid);
            flash_set('basari', $s['ad'] . ' şablonu silindi. (Uygulanmış oturumlar etkilenmez.)');
        }
        redirect('sablonlar.php');
    }
    if ($islem === 'oturum_ekle') {
        $sid = (int)($_POST['sablon_id'] ?? 0);
        if (template_get($sid)) {
            $yeniId = template_session_add($sid, (int)($_POST['hafta_no'] ?? 1), (string)($_POST['oturum_adi'] ?? 'A'));
            flash_set('bilgi', 'Boş oturum eklendi — tekniklerini seçin.');
            redirect('sablon-oturum.php?id=' . $yeniId);
        }
        redirect('sablonlar.php');
    }
    if ($islem === 'oturum_sil') {
        $o = template_session_get((int)($_POST['oturum_id'] ?? 0));
        if ($o) {
            template_session_delete((int)$o['id']);
            flash_set('basari', 'Hafta ' . (int)$o['hafta_no'] . $o['oturum_adi'] . ' oturumu şablondan silindi.');
            redirect('sablonlar.php?id=' . (int)$o['sablon_id']);
        }
        redirect('sablonlar.php');
    }

    if ($islem === 'uygula') {
        $sablonId = (int)($_POST['sablon_id'] ?? 0);
        $grupId = (int)($_POST['grup_id'] ?? 0);
        $tarih = (string)($_POST['baslangic'] ?? '');
        $mod = ($_POST['mod'] ?? 'A') === 'AB' ? 'AB' : 'A';
        $bFark = (int)($_POST['b_fark'] ?? 3);
        if (!template_get($sablonId)) {
            flash_set('hata', 'Şablon bulunamadı.');
        } elseif (!group_get($grupId)) {
            flash_set('hata', 'Uygulanacak grubu seçin.');
        } elseif (!DateTime::createFromFormat('Y-m-d', $tarih)) {
            flash_set('hata', 'Geçerli bir başlangıç tarihi seçin.');
        } else {
            $sonuc = template_apply($sablonId, $grupId, $tarih, $mod, $bFark);
            flash_set($sonuc['olusan'] > 0 ? 'basari' : 'uyari',
                $sonuc['olusan'] . ' oturum planlandı' .
                ($sonuc['atlanan'] > 0 ? '; ' . $sonuc['atlanan'] . ' oturum atlandı (aynı tarihte kayıt vardı).' : '.'));
            redirect('oturumlar.php?grup_id=' . $grupId);
        }
    }
    redirect('sablonlar.php' . (isset($_POST['sablon_id']) ? '?id=' . (int)$_POST['sablon_id'] : ''));
}

$sablonlar = templates_list();
$secili = isset($_GET['id']) ? template_get((int)$_GET['id']) : null;
$gruplar = groups_list(true);

$PAGE_TITLE = 'Plan Şablonları';
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik"><h1>Eğitim Planı Şablonları</h1></div>

<div class="kart">
  <div class="kart-baslik">
    <h2>Hazır programlar</h2>
    <span class="alan-ipucu">RitimOdak uygulayıcı kılavuzundan türetilmiş 12 haftalık izler.</span>
  </div>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th>Şablon</th><th>Hedef kitle</th><th class="sayi">Oturum</th><th class="sayi">Oturum süresi</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($sablonlar as $s): ?>
        <tr>
          <td>
            <a href="<?= e(url('sablonlar.php?id=' . (int)$s['id'])) ?>"><?= e($s['ad']) ?></a><br>
            <span class="alan-ipucu"><?= e($s['aciklama']) ?></span>
          </td>
          <td><?= $s['hedef_kitle'] === 'cocuk' ? '🧒 Çocuk & Genç' : '🧑‍💼 Yetişkin' ?></td>
          <td class="sayi"><?= (int)$s['oturum_sayisi'] ?></td>
          <td class="sayi"><?= (int)$s['sure_dk'] ?> dk</td>
          <td style="text-align:right">
            <a class="btn btn-kucuk btn-birincil" href="<?= e(url('sablonlar.php?id=' . (int)$s['id'])) ?>">İncele &amp; Uygula</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="kart">
  <h2>Yeni şablon</h2>
  <form method="post" action="<?= e(url('sablonlar.php')) ?>" class="filtre-satir">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="sablon_olustur">
    <label class="form-alan" style="flex:1;min-width:220px">Şablon adı
      <input type="text" name="ad" class="girdi" maxlength="120" required placeholder="Örn. Yaz dönemi mini program">
    </label>
    <label class="form-alan">Hedef kitle
      <select name="hedef_kitle" class="secim">
        <option value="cocuk">🧒 Çocuk &amp; Genç</option>
        <option value="yetiskin">🧑‍💼 Yetişkin</option>
      </select>
    </label>
    <label class="form-alan">Oturum süresi (dk)
      <input type="number" name="sure_dk" class="girdi" value="45" min="10" max="180">
    </label>
    <label class="form-alan" style="flex:1;min-width:220px">Açıklama
      <input type="text" name="aciklama" class="girdi" maxlength="300">
    </label>
    <button type="submit" class="btn btn-birincil">Şablon Oluştur</button>
  </form>
</div>

<?php if ($secili): ?>
<div class="kart">
  <div class="kart-baslik">
    <h2><?= e($secili['ad']) ?></h2>
    <span class="rozet rozet-acik"><?= count($secili['oturumlar']) ?> oturum</span>
  </div>

  <details class="sablon-hafta" style="margin-bottom:1rem">
    <summary><strong>⚙ Şablon bilgilerini düzenle</strong></summary>
    <form method="post" action="<?= e(url('sablonlar.php')) ?>" class="filtre-satir" style="margin-top:.6rem">
      <?= csrf_field() ?>
      <input type="hidden" name="islem" value="sablon_guncelle">
      <input type="hidden" name="sablon_id" value="<?= (int)$secili['id'] ?>">
      <label class="form-alan" style="flex:1;min-width:220px">Şablon adı
        <input type="text" name="ad" class="girdi" value="<?= e($secili['ad']) ?>" maxlength="120" required>
      </label>
      <label class="form-alan">Hedef kitle
        <select name="hedef_kitle" class="secim">
          <option value="cocuk" <?= $secili['hedef_kitle'] === 'cocuk' ? 'selected' : '' ?>>🧒 Çocuk &amp; Genç</option>
          <option value="yetiskin" <?= $secili['hedef_kitle'] === 'yetiskin' ? 'selected' : '' ?>>🧑‍💼 Yetişkin</option>
        </select>
      </label>
      <label class="form-alan">Oturum süresi (dk)
        <input type="number" name="sure_dk" class="girdi" value="<?= (int)$secili['sure_dk'] ?>" min="10" max="180">
      </label>
      <label class="form-alan" style="flex:1;min-width:220px">Açıklama
        <input type="text" name="aciklama" class="girdi" value="<?= e($secili['aciklama']) ?>" maxlength="300">
      </label>
      <button type="submit" class="btn btn-birincil">Kaydet</button>
    </form>
    <form method="post" action="<?= e(url('sablonlar.php')) ?>" style="margin-top:.5rem"
          data-onay="<?= e($secili['ad']) ?> şablonu ve tüm haftalık planı silinecek (uygulanmış oturumlar etkilenmez). Emin misiniz?">
      <?= csrf_field() ?>
      <input type="hidden" name="islem" value="sablon_sil">
      <input type="hidden" name="sablon_id" value="<?= (int)$secili['id'] ?>">
      <button type="submit" class="btn btn-kucuk btn-tehlike">Şablonu Sil</button>
    </form>
  </details>

  <p class="alan-ipucu"><?= e($secili['aciklama']) ?></p>

  <div class="bilgi-kutu">
    <strong>Gruba uygula:</strong> seçtiğiniz başlangıç tarihinden itibaren her hafta A oturumu
    (istenirse +gün farkıyla B oturumu) teknik planlarıyla birlikte oluşturulur. Var olan
    oturumların tarihine denk gelenler atlanır; oluşan planları sonra tek tek düzenleyebilirsiniz.
  </div>
  <?php if (!$gruplar): ?>
    <div class="bos-durum">Uygulamak için önce aktif bir grup oluşturun.</div>
  <?php else: ?>
  <form method="post" action="<?= e(url('sablonlar.php')) ?>" class="filtre-satir"
        data-onay="Şablon seçilen gruba uygulanacak ve 12 haftalık oturum planı oluşturulacak. Devam edilsin mi?">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="uygula">
    <input type="hidden" name="sablon_id" value="<?= (int)$secili['id'] ?>">
    <label class="form-alan">Grup
      <select name="grup_id" class="secim" required>
        <option value="">— Seçin —</option>
        <?php foreach ($gruplar as $g): ?>
        <option value="<?= (int)$g['id'] ?>"><?= e($g['ad']) ?> (<?= e(GUNLER[(int)$g['gun']] ?? '') ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan">Başlangıç (1. hafta A oturumu)
      <input type="date" name="baslangic" class="girdi" value="<?= e(today()) ?>" required>
    </label>
    <label class="form-alan">Haftalık oturum
      <select name="mod" class="secim" id="sablonMod">
        <option value="A">Haftada 1 (yalnız A)</option>
        <option value="AB">Haftada 2 (A + B)</option>
      </select>
    </label>
    <label class="form-alan">B oturumu kaç gün sonra?
      <select name="b_fark" class="secim">
        <option value="2">2 gün</option>
        <option value="3" selected>3 gün</option>
        <option value="4">4 gün</option>
      </select>
    </label>
    <button type="submit" class="btn btn-birincil">Şablonu Uygula</button>
  </form>
  <?php endif; ?>

  <div class="kart-baslik" style="margin-top:1.2rem">
    <h2>Haftalık akış</h2>
    <div class="sag">
      <form method="post" action="<?= e(url('sablonlar.php')) ?>" class="filtre-satir" style="margin:0">
        <?= csrf_field() ?>
        <input type="hidden" name="islem" value="oturum_ekle">
        <input type="hidden" name="sablon_id" value="<?= (int)$secili['id'] ?>">
        <label class="form-alan" style="min-width:90px">Hafta
          <input type="number" name="hafta_no" class="girdi" value="1" min="1" max="52">
        </label>
        <label class="form-alan" style="min-width:80px">Oturum
          <select name="oturum_adi" class="secim">
            <option value="A">A</option><option value="B">B</option><option value="C">C</option>
          </select>
        </label>
        <button type="submit" class="btn btn-golge">+ Oturum Ekle</button>
      </form>
    </div>
  </div>
  <?php
  $haftalar = [];
  foreach ($secili['oturumlar'] as $o) { $haftalar[(int)$o['hafta_no']][] = $o; }
  foreach ($haftalar as $haftaNo => $oturumlar): ?>
  <details class="sablon-hafta" <?= $haftaNo === 1 ? 'open' : '' ?>>
    <summary>
      <strong>Hafta <?= $haftaNo ?></strong> — <?= e($oturumlar[0]['hedef']) ?>
      <span class="rozet rozet-gri"><?= count($oturumlar) ?> oturum</span>
    </summary>
    <div class="tablo-sar">
      <table class="tablo">
        <thead><tr><th style="width:60px">Oturum</th><th>Teknikler</th><th class="sayi" style="width:90px">Toplam</th><th style="width:150px"></th></tr></thead>
        <tbody>
          <?php foreach ($oturumlar as $o): ?>
          <tr>
            <td><strong><?= e($o['oturum_adi']) ?></strong>
              <?php if (!empty($o['protokol']) && isset(PROTOKOL_LABELS[$o['protokol']])): ?>
              <br><span class="rozet rozet-acik" title="Haftanın protokolü">🧭 <?= e(PROTOKOL_LABELS[$o['protokol']]) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php foreach ($o['teknikler'] as $t): ?>
              <div><?= e($t['ad']) ?> <span class="alan-ipucu">(<?= (int)$t['sure_dk'] ?> dk)</span>
                <?= trim((string)$t['uygulama_notu']) !== '' ? '<span class="alan-ipucu">— ' . e($t['uygulama_notu']) . '</span>' : '' ?>
              </div>
              <?php endforeach; ?>
              <?php if (!$o['teknikler']): ?><span class="rozet rozet-bekliyor">Teknik seçilmedi</span><?php endif; ?>
            </td>
            <td class="sayi"><?= (int)$o['toplam_sure'] ?> dk</td>
            <td style="text-align:right;white-space:nowrap">
              <a class="btn btn-kucuk btn-golge" href="<?= e(url('sablon-oturum.php?id=' . (int)$o['id'])) ?>">Düzenle</a>
              <form method="post" action="<?= e(url('sablonlar.php')) ?>" style="display:inline"
                    data-onay="Hafta <?= $haftaNo ?><?= e($o['oturum_adi']) ?> oturumu şablondan silinsin mi?">
                <?= csrf_field() ?>
                <input type="hidden" name="islem" value="oturum_sil">
                <input type="hidden" name="oturum_id" value="<?= (int)$o['id'] ?>">
                <button type="submit" class="btn btn-kucuk btn-tehlike">Sil</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>
  <?php endforeach; ?>
</div>
<style>
  .sablon-hafta { border: 1px solid var(--cizgi); border-radius: 10px; margin-bottom: .5rem; padding: .3rem .8rem; background: #fff; }
  .sablon-hafta summary { cursor: pointer; padding: .45rem 0; display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; }
  .sablon-hafta[open] summary { border-bottom: 1px solid var(--cizgi); margin-bottom: .5rem; }
</style>
<?php endif; ?>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
