<?php
/** Teknik detayı ve düzenleme. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$teknik = technique_get($id);
if (!$teknik) { not_found('Teknik bulunamadı.'); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('teknik.php?id=' . $id);
    $islem = (string)($_POST['islem'] ?? 'guncelle');

    if ($islem === 'calisma_bagla') {
        $tamam = technique_study_link($id, (int)($_POST['calisma_id'] ?? 0),
            (string)($_POST['iliski_notu'] ?? ''));
        flash_set($tamam ? 'basari' : 'hata',
            $tamam ? 'Çalışma tekniğe bağlandı.' : 'Çalışma bulunamadı.');
    } elseif ($islem === 'calisma_kaldir') {
        technique_study_unlink($id, (int)($_POST['calisma_id'] ?? 0));
        flash_set('basari', 'Çalışma bağlantısı kaldırıldı.');
    } else {
        $res = technique_save($_POST, $id);
        flash_set($res['ok'] ? 'basari' : 'hata', $res['ok'] ? 'Teknik güncellendi.' : $res['error']);
        if (!$res['ok']) { set_old($_POST); }
    }
    redirect('teknik.php?id=' . $id);
}

$st = db()->prepare('SELECT COUNT(*) FROM oturum_teknikleri WHERE teknik_id = ?');
$st->execute([$id]);
$kullanimSayisi = (int)$st->fetchColumn();
$kategoriler = technique_categories();
$bagliCalismalar = technique_studies($id);
$bagsizCalismalar = studies_not_linked($id);

$PAGE_TITLE = 'Teknik: ' . $teknik['ad'];
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik">
  <h1><?= e($teknik['ad']) ?></h1>
  <?= kanit_rozet($teknik['kanit_duzeyi']) ?>
  <?= (int)$teknik['aktif'] !== 1 ? '<span class="rozet rozet-gri">Pasif</span>' : '' ?>
  <div class="sag">
    <a class="btn btn-golge" href="<?= e(url('teknikler.php')) ?>"><span class="emoji-sus" aria-hidden="true">←</span> Kütüphaneye dön</a>
  </div>
</div>

<div class="kart">
  <h2>Teknik bilgileri</h2>
  <form method="post" action="<?= e(url('teknik.php?id=' . $id)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="form-grid">
      <label class="form-alan">Teknik adı
        <input type="text" name="ad" class="girdi" value="<?= e(old('ad', $teknik['ad'])) ?>" maxlength="80" required>
      </label>
      <label class="form-alan">Kategori
        <input type="text" name="kategori" class="girdi" value="<?= e($teknik['kategori']) ?>" maxlength="60" required list="kategoriListesi">
        <datalist id="kategoriListesi">
          <?php foreach ($kategoriler as $k): ?><option value="<?= e($k) ?>"></option><?php endforeach; ?>
        </datalist>
      </label>
      <label class="form-alan">Enstrüman
        <input type="text" name="enstruman" class="girdi" value="<?= e($teknik['enstruman']) ?>" maxlength="60">
      </label>
      <label class="form-alan">Seviye
        <select name="seviye" class="secim">
          <?php foreach (SEVIYE_LABELS as $no => $ad): ?>
          <option value="<?= $no ?>" <?= (int)$teknik['seviye'] === $no ? 'selected' : '' ?>><?= $no ?> — <?= e($ad) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-alan">Süre (dk)
        <input type="number" name="sure_dk" class="girdi" value="<?= (int)$teknik['sure_dk'] ?>" min="1" max="120">
      </label>
      <label class="form-alan">Kanıt düzeyi
        <select name="kanit_duzeyi" class="secim" required>
          <?php foreach (KANIT_LABELS as $kod => $ad): ?>
          <option value="<?= e($kod) ?>" <?= $teknik['kanit_duzeyi'] === $kod ? 'selected' : '' ?>><?= e($ad) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="alan-ipucu">Dürüst kalın: kanıt düzeyi pazarlama etiketi değildir.</span>
      </label>
      <label class="form-alan">Hedef beceri
        <input type="text" name="hedef_beceri" class="girdi" value="<?= e($teknik['hedef_beceri']) ?>" maxlength="120">
      </label>
      <label class="form-alan">Malzeme
        <input type="text" name="malzeme" class="girdi" value="<?= e($teknik['malzeme']) ?>" maxlength="160">
      </label>
      <label class="form-alan">Durum
        <input type="hidden" name="aktif" value="0">
        <span class="onay-kutu"><input type="checkbox" name="aktif" value="1" <?= (int)$teknik['aktif'] === 1 ? 'checked' : '' ?>> Teknik aktif (planlarda seçilebilir)</span>
      </label>
      <label class="form-alan form-genis">Açıklama (nasıl uygulanır)
        <textarea name="aciklama" class="girdi" maxlength="1500" rows="4"><?= e(old('aciklama', $teknik['aciklama'])) ?></textarea>
      </label>
      <?php /* Bkz. teknikler.php: gerekçe metinleri 300 karakteri aşıyor;
               tek satırlık input hem okunmuyor hem düzenlemede kırpıyordu. */ ?>
      <label class="form-alan form-genis">Kaynak ve kanıt gerekçesi
        <textarea name="kaynak" class="girdi" maxlength="1500" rows="4"><?= e(old('kaynak', $teknik['kaynak'])) ?></textarea>
        <span class="alan-ipucu">Kanıt yoksa bunu yazmak da bir bilgidir — boş bırakmaktan iyidir.</span>
      </label>
    </div>
    <div class="form-butonlar">
      <button type="submit" class="btn btn-birincil">Kaydet</button>
    </div>
  </form>
</div>

<div class="kart">
  <div class="kart-baslik">
    <h2>Bilimsel dayanak</h2>
    <?php if ($bagliCalismalar): ?>
    <span class="rozet rozet-acik"><span class="emoji-sus" aria-hidden="true">📚</span> <?= count($bagliCalismalar) ?> çalışma</span>
    <?php endif; ?>
    <div class="sag">
      <a class="btn btn-kucuk btn-golge" href="<?= e(url('calismalar.php')) ?>">Tüm çalışmalar →</a>
    </div>
  </div>

  <?php if (!$bagliCalismalar): ?>
  <div class="uyari-kutu">
    <strong>Bu tekniğe bağlı akademik çalışma yok.</strong> Teknik, pedagojik gelenekle
    gerekçelendiriliyor — bu meşrudur; kanıt düzeyi etiketinin de buna uygun
    (<em>zayıf</em> ya da <em>kanıt yok</em>) kalması beklenir. Bir çalışma biliyorsanız
    aşağıdan bağlayabilirsiniz.
  </div>
  <?php else: ?>
    <?php if (in_array((string)$teknik['kanit_duzeyi'], ['zayif', 'yok'], true)): ?>
    <!-- Bağ sayısının artması SESSİZ BİR YÜKSELTME gibi okunmasın diye kalıcı uyarı -->
    <div class="uyari-kutu">
      <strong>Bu çalışmalar tekniğin denendiği çalışmalar değildir.</strong>
      Aşağıdakiler tekniğin arka planını ve komşu alandaki bulguları belgeliyor;
      bir kısmı bilinçli olarak <em>karşıt</em> bulgudur. Bağlı çalışma sayısının
      artması kanıt düzeyini yükseltmez — etiket ayrı bir karardır.
    </div>
    <?php endif; ?>
    <?php foreach ($bagliCalismalar as $c): ?>
    <div style="border:1px solid var(--cizgi);border-radius:12px;padding:.9rem 1rem;margin-bottom:.7rem">
      <div style="display:flex;gap:.6rem;align-items:baseline;flex-wrap:wrap">
        <strong><?= e($c['baslik']) ?></strong>
        <span class="rozet rozet-gri"><?= e(CALISMA_TUR_LABELS[$c['tur']] ?? $c['tur']) ?></span>
      </div>
      <p class="alan-ipucu" style="margin:.25rem 0"><?= e(study_citation($c)) ?>
        <?php if ($c['doi'] !== ''): ?>
        · <a href="https://doi.org/<?= e($c['doi']) ?>" target="_blank" rel="noopener">DOI: <?= e($c['doi']) ?></a>
        <?php else: ?>
        · <span class="rozet rozet-gri">DOI yok</span>
        <?php endif; ?>
      </p>
      <?php if (trim((string)$c['ozet']) !== ''): ?>
      <p style="margin:.35rem 0"><?= e($c['ozet']) ?></p>
      <?php endif; ?>
      <?php if (trim((string)$c['iliski_notu']) !== ''): ?>
      <p style="margin:.35rem 0;border-left:3px solid var(--amber);padding-left:.7rem" class="alan-ipucu">
        <strong>Bu tekniğe bağı:</strong> <?= e($c['iliski_notu']) ?></p>
      <?php endif; ?>
      <form method="post" action="<?= e(url('teknik.php?id=' . $id)) ?>" style="margin-top:.4rem"
            data-onay="Bu çalışmanın tekniğe bağlantısı kaldırılsın mı? (Çalışma kaydı silinmez.)">
        <?= csrf_field() ?>
        <input type="hidden" name="islem" value="calisma_kaldir">
        <input type="hidden" name="calisma_id" value="<?= (int)$c['id'] ?>">
        <button type="submit" class="btn btn-kucuk btn-golge">Bağlantıyı kaldır</button>
      </form>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($bagsizCalismalar): ?>
  <form method="post" action="<?= e(url('teknik.php?id=' . $id)) ?>" class="filtre-satir" style="margin-top:.8rem">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="calisma_bagla">
    <label class="form-alan" style="flex:1;min-width:260px">Çalışma bağla
      <select name="calisma_id" class="secim" required>
        <?php foreach ($bagsizCalismalar as $c): ?>
        <option value="<?= (int)$c['id'] ?>">
          <?= e(mb_strimwidth($c['yazarlar'], 0, 34, '…')) ?> (<?= $c['yil'] ? (int)$c['yil'] : '—' ?>) — <?= e(mb_strimwidth($c['baslik'], 0, 60, '…')) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan" style="flex:1;min-width:220px">Bu tekniğe bağı (isteğe bağlı)
      <input type="text" name="iliski_notu" class="girdi" maxlength="200"
             placeholder="Örn. Ölçülen beceri doğrudan bu">
    </label>
    <button type="submit" class="btn btn-birincil">Bağla</button>
  </form>
  <?php endif; ?>
</div>

<div class="kart">
  <h2>Kullanım</h2>
  <p>Bu teknik <strong><?= $kullanimSayisi ?></strong> oturum planında yer aldı.</p>
  <?php if ($kullanimSayisi > 0): ?>
    <p class="alan-ipucu">Oturumlarda kullanılmış teknik silinemez (geçmiş bozulur). Kütüphaneden kaldırmak için pasife alın.</p>
  <?php else: ?>
  <form method="post" action="<?= e(url('teknik-sil.php')) ?>"
        data-onay="<?= e($teknik['ad']) ?> tekniğini silmek üzeresiniz. Emin misiniz?">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <button type="submit" class="btn btn-tehlike">Tekniği Sil</button>
  </form>
  <?php endif; ?>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
