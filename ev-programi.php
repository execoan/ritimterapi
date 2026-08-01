<?php
/**
 * Ev Programı (yönetim) — çalışma kütüphanesi, ödev atama ve takip.
 * Dayanak: docs/ev-programi-dayanak.md (kısa-sık pratik, veli katılımı, izleme).
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('ev-programi.php');
    $islem = (string)($_POST['islem'] ?? '');

    if ($islem === 'ata') {
        $hedef = (string)($_POST['hedef'] ?? '');
        $ogrenciIds = [];
        if (str_starts_with($hedef, 'o')) {
            $ogrenciIds = [(int)substr($hedef, 1)];
        } elseif (str_starts_with($hedef, 'g')) {
            $ogrenciIds = array_map(fn($o) => (int)$o['id'], students_list((int)substr($hedef, 1), 1));
        }
        $olusan = assignment_create(
            $ogrenciIds,
            (int)($_POST['calisma_id'] ?? 0),
            (string)($_POST['baslangic'] ?? today()),
            (string)($_POST['bitis'] ?? ''),
            (int)($_POST['hedef_gun'] ?? 5),
            (string)($_POST['notlar'] ?? '')
        );
        flash_set($olusan > 0 ? 'basari' : 'uyari',
            $olusan > 0 ? $olusan . ' öğrenciye ödev atandı.'
                        : 'Ödev atanamadı (hedef boş ya da aynı çalışma zaten güncel atanmış).');
        redirect('ev-programi.php');
    }

    if ($islem === 'odev_sil') {
        assignment_delete((int)($_POST['id'] ?? 0));
        flash_set('basari', 'Ödev kaldırıldı.');
        redirect('ev-programi.php');
    }

    if ($islem === 'calisma_kaydet') {
        $cid = (int)($_POST['calisma_id'] ?? 0) ?: null;
        $res = home_exercise_save($_POST, $cid);
        flash_set($res['ok'] ? 'basari' : 'hata',
            $res['ok'] ? 'Ev çalışması kaydedildi.' : $res['error']);
        redirect('ev-programi.php' . ($res['ok'] ? '' : ($cid ? '?duzenle=' . $cid : '')));
    }
    redirect('ev-programi.php');
}

$calismalar = home_exercises_list();
$odevler = assignments_admin_list();
$ogrenciler = students_list(null, 1);
$gruplar = groups_list(true);
$duzenlenen = isset($_GET['duzenle']) ? home_exercise_get((int)$_GET['duzenle']) : null;
$varsayilanBitis = now()->modify('+21 days')->format('Y-m-d');

$PAGE_TITLE = 'Ev Programı';
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik">
  <h1>Ev Programı</h1>
  <span class="alan-ipucu">Kısa + sık çalışma ilkesi — dayanak: docs/ev-programi-dayanak.md</span>
</div>

<div class="kart">
  <h2>Ödev ata</h2>
  <p class="alan-ipucu">Öğrenci ev sayfasına erişim kodlarını öğrenci detayında bulabilirsiniz
     (<?= e(url('ev.php')) ?> adresi + 6 haneli kod).</p>
  <?php if (!$ogrenciler): ?>
    <div class="bos-durum">Önce aktif bir öğrenci kaydedin.</div>
  <?php else: ?>
  <form method="post" action="<?= e(url('ev-programi.php')) ?>" class="filtre-satir">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="ata">
    <label class="form-alan">Kime
      <select name="hedef" class="secim" required>
        <option value="">— Seçin —</option>
        <?php if ($gruplar): ?>
        <optgroup label="Gruba (tüm aktif öğrenciler)">
          <?php foreach ($gruplar as $g): ?>
          <option value="g<?= (int)$g['id'] ?>"><span class="emoji-sus" aria-hidden="true">👥</span> <?= e($g['ad']) ?></option>
          <?php endforeach; ?>
        </optgroup>
        <?php endif; ?>
        <optgroup label="Tek öğrenci">
          <?php foreach ($ogrenciler as $o): ?>
          <option value="o<?= (int)$o['id'] ?>"><?= e($o['kod']) ?><?= $o['grup_ad'] ? ' — ' . e($o['grup_ad']) : '' ?></option>
          <?php endforeach; ?>
        </optgroup>
      </select>
    </label>
    <label class="form-alan">Çalışma
      <select name="calisma_id" class="secim" required>
        <?php foreach ($calismalar as $c): if ((int)$c['aktif'] !== 1) continue; ?>
        <option value="<?= (int)$c['id'] ?>">
          <?= e($c['ad']) ?><?= $c['hafta_onerisi'] ? ' (H' . (int)$c['hafta_onerisi'] . ')' : '' ?>
        </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan">Başlangıç
      <input type="date" name="baslangic" class="girdi" value="<?= e(today()) ?>" required>
    </label>
    <label class="form-alan">Bitiş
      <input type="date" name="bitis" class="girdi" value="<?= e($varsayilanBitis) ?>" required>
    </label>
    <label class="form-alan">Hedef (gün/hafta)
      <select name="hedef_gun" class="secim">
        <?php foreach ([3, 4, 5, 6, 7] as $g): ?>
        <option value="<?= $g ?>" <?= $g === 5 ? 'selected' : '' ?>><?= $g ?> gün</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan" style="min-width:180px">Not (öğrenci görür)
      <input type="text" name="notlar" class="girdi" maxlength="200">
    </label>
    <button type="submit" class="btn btn-birincil">Ata</button>
  </form>
  <?php endif; ?>
</div>

<div class="kart">
  <div class="kart-baslik">
    <h2>Güncel ödevler</h2>
    <span class="rozet rozet-acik"><?= count($odevler) ?> ödev</span>
  </div>
  <?php if (!$odevler): ?>
    <div class="bos-durum">Güncel ödev yok — yukarıdan atayın.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th scope="col">Öğrenci</th><th scope="col">Çalışma</th><th scope="col">Aralık</th><th scope="col" class="sayi">Bu hafta</th><th scope="col" class="sayi">Toplam gün</th><th scope="col"></th></tr></thead>
      <tbody>
        <?php foreach ($odevler as $o): ?>
        <tr>
          <td>
            <a href="<?= e(url('ogrenci.php?id=' . (int)$o['ogrenci_id'])) ?>"><?= e($o['ogrenci_kod']) ?></a>
            <a class="btn btn-kucuk btn-golge" href="<?= e(url('ev.php?onizle=' . (int)$o['ogrenci_id'])) ?>"
               target="_blank" rel="noopener" title="Öğrencinin gördüğü ev sayfası">👁</a>
          </td>
          <td><?= e($o['calisma_ad']) ?> <span class="alan-ipucu">(<?= e(EV_TUR_LABELS[$o['tur']] ?? $o['tur']) ?>)</span></td>
          <td><?= e(format_date_tr($o['baslangic'], false)) ?> – <?= e(format_date_tr($o['bitis'], false)) ?></td>
          <td class="sayi"><strong><?= (int)$o['hafta_gun'] ?></strong>/<?= (int)$o['hedef_gun'] ?></td>
          <td class="sayi"><?= (int)$o['toplam_gun'] ?></td>
          <td style="text-align:right">
            <form method="post" action="<?= e(url('ev-programi.php')) ?>" style="display:inline"
                  data-onay="Bu ödev ve işaret geçmişi silinsin mi?">
              <?= csrf_field() ?>
              <input type="hidden" name="islem" value="odev_sil">
              <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <button type="submit" class="btn btn-kucuk btn-tehlike">Kaldır</button>
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
    <h2>Çalışma kütüphanesi</h2>
    <span class="rozet rozet-acik"><?= count($calismalar) ?> çalışma</span>
  </div>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th scope="col">Çalışma</th><th scope="col">Tür</th><th scope="col">Kitle</th><th scope="col" class="sayi">Hafta</th><th scope="col" class="sayi">Süre</th><th scope="col">Durum</th><th scope="col"></th></tr></thead>
      <tbody>
        <?php foreach ($calismalar as $c): ?>
        <tr>
          <td><strong><?= e($c['ad']) ?></strong><br><span class="alan-ipucu"><?= e($c['hedef_beceri']) ?></span></td>
          <td><?= e(EV_TUR_LABELS[$c['tur']] ?? $c['tur']) ?></td>
          <td><?= e(KITLE_LABELS[$c['kitle']] ?? $c['kitle']) ?></td>
          <td class="sayi"><?= $c['hafta_onerisi'] ? 'H' . (int)$c['hafta_onerisi'] : '—' ?></td>
          <td class="sayi"><?= (int)$c['sure_dk'] ?> dk</td>
          <td><?= (int)$c['aktif'] === 1 ? '<span class="rozet rozet-tamam">Aktif</span>' : '<span class="rozet rozet-gri">Pasif</span>' ?></td>
          <td style="text-align:right">
            <a class="btn btn-kucuk btn-golge" href="<?= e(url('ev-programi.php?duzenle=' . (int)$c['id'])) ?>#duzenle">Düzenle</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="kart" id="duzenle">
  <h2><?= $duzenlenen ? 'Çalışmayı düzenle: ' . e($duzenlenen['ad']) : 'Yeni ev çalışması' ?></h2>
  <form method="post" action="<?= e(url('ev-programi.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="calisma_kaydet">
    <?php if ($duzenlenen): ?><input type="hidden" name="calisma_id" value="<?= (int)$duzenlenen['id'] ?>"><?php endif; ?>
    <div class="form-grid">
      <label class="form-alan">Ad
        <input type="text" name="ad" class="girdi" value="<?= e($duzenlenen['ad'] ?? '') ?>" maxlength="120" required>
      </label>
      <label class="form-alan">Tür
        <select name="tur" class="secim">
          <?php foreach (EV_TUR_LABELS as $kod => $ad): ?>
          <option value="<?= e($kod) ?>" <?= ($duzenlenen['tur'] ?? 'serbest') === $kod ? 'selected' : '' ?>><?= e($ad) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-alan">Kitle
        <select name="kitle" class="secim">
          <?php foreach (KITLE_LABELS as $kod => $ad): ?>
          <option value="<?= e($kod) ?>" <?= ($duzenlenen['kitle'] ?? 'hepsi') === $kod ? 'selected' : '' ?>><?= e($ad) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-alan">Hafta önerisi (şablonla eş)
        <input type="number" name="hafta_onerisi" class="girdi" value="<?= e((string)($duzenlenen['hafta_onerisi'] ?? '')) ?>" min="1" max="52" placeholder="—">
      </label>
      <label class="form-alan">Süre (dk)
        <input type="number" name="sure_dk" class="girdi" value="<?= (int)($duzenlenen['sure_dk'] ?? 3) ?>" min="1" max="60">
      </label>
      <label class="form-alan">BPM (modüller için)
        <input type="number" name="bpm" class="girdi" value="<?= (int)($duzenlenen['bpm'] ?? 66) ?>" min="30" max="240">
      </label>
      <label class="form-alan">Seviye (ritim okuma)
        <select name="seviye" class="secim">
          <?php foreach ([1 => '1 — çeyrek + es', 2 => '2 — sekizlikler (“ve”)', 3 => '3 — üçlemeler'] as $no => $ad): ?>
          <option value="<?= $no ?>" <?= (int)($duzenlenen['seviye'] ?? 1) === $no ? 'selected' : '' ?>><?= e($ad) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-alan">Hedef beceri
        <input type="text" name="hedef_beceri" class="girdi" value="<?= e($duzenlenen['hedef_beceri'] ?? '') ?>" maxlength="120">
      </label>
      <label class="form-alan form-genis">Açıklama (öğrenci görür)
        <textarea name="aciklama" class="girdi" rows="2" maxlength="600"><?= e($duzenlenen['aciklama'] ?? '') ?></textarea>
      </label>
      <label class="form-alan form-genis">Veli yönergesi (öğrenci sayfasında görünür)
        <textarea name="veli_yonerge" class="girdi" rows="2" maxlength="400"><?= e($duzenlenen['veli_yonerge'] ?? '') ?></textarea>
      </label>
      <label class="form-alan form-genis">Kanıt notu (iç kullanım)
        <input type="text" name="kanit_notu" class="girdi" value="<?= e($duzenlenen['kanit_notu'] ?? '') ?>" maxlength="300">
      </label>
      <label class="form-alan">Durum
        <input type="hidden" name="aktif" value="0">
        <span class="onay-kutu"><input type="checkbox" name="aktif" value="1" <?= (int)($duzenlenen['aktif'] ?? 1) === 1 ? 'checked' : '' ?>> Aktif</span>
      </label>
    </div>
    <div class="form-butonlar">
      <button type="submit" class="btn btn-birincil"><?= $duzenlenen ? 'Kaydet' : 'Çalışma Ekle' ?></button>
      <?php if ($duzenlenen): ?>
      <a class="btn btn-golge" href="<?= e(url('ev-programi.php')) ?>">Yeni kayda geç</a>
      <?php endif; ?>
    </div>
  </form>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
