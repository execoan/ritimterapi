<?php
/** Öğrenci detayı — bilgiler, katılım özeti, gözlem zaman çizelgesi. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$ogrenci = student_get($id);
if (!$ogrenci) { not_found('Öğrenci bulunamadı.'); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('ogrenci.php?id=' . $id);
    $islem = (string)($_POST['islem'] ?? 'guncelle');

    if ($islem === 'kod_yenile') {
        $yeni = student_code_regenerate($id);
        flash_set('basari', 'Erişim kodu yenilendi: ' . $yeni . ' (eski kod artık geçersiz).');
    } elseif ($islem === 'paket_olustur') {
        $paketNot = trim((string)($_POST['paket_not'] ?? ''));
        if (!empty($_POST['moxo_dahil'])) {
            $paketNot = trim($paketNot . ($paketNot !== '' ? ' · ' : '')
                . 'MOXO ön/son ölçüm dahil (yetkili uzman uygular ve yorumlar)');
        }
        $tamam = package_create($id, (string)($_POST['paket_ad'] ?? ''),
            (int)($_POST['toplam_seans'] ?? 10), (string)($_POST['baslangic'] ?? today()),
            $paketNot);
        flash_set($tamam ? 'basari' : 'hata',
            $tamam ? 'Yeni seans paketi başlatıldı.' : 'Paket oluşturulamadı — tarihi kontrol edin.');
    } elseif ($islem === 'paket_kapat') {
        package_close((int)($_POST['paket_id'] ?? 0));
        flash_set('basari', 'Paket kapatıldı.');
    } elseif ($islem === 'grup_ekle') {
        $res = group_member_add((int)($_POST['grup_id'] ?? 0), $id);
        flash_set($res['ok'] ? 'basari' : 'hata',
            $res['ok'] ? 'Kişi derse/gruba eklendi.' : $res['error']);
    } elseif ($islem === 'grup_cikar') {
        $tamam = group_member_remove((int)($_POST['grup_id'] ?? 0), $id);
        flash_set($tamam ? 'basari' : 'hata',
            $tamam ? 'Ders üyeliği kaldırıldı; geçmiş kayıtları korundu.' : 'Aktif üyelik bulunamadı.');
    } else {
        $res = student_save($_POST, $id);
        flash_set($res['ok'] ? 'basari' : 'hata', $res['ok'] ? 'Öğrenci güncellendi.' : $res['error']);
        /* Hata varsa girilen veri KAYBOLMASIN — yeniden yazdırmak angarya ve
           uzun bir veli notunda gerçek veri kaybı demek (gruplar.php deseni). */
        if (!$res['ok']) { set_old($_POST); }
    }
    redirect('ogrenci.php?id=' . $id);
}

$istatistik = student_stats($id);
$gecmis = student_timeline($id);
$gruplar = groups_list();
$uyelikler = student_groups($id);
$uyeGrupIdleri = array_map(static fn(array $g): int => (int)$g['id'], $uyelikler);
$eklenebilirGruplar = array_values(array_filter($gruplar, static function (array $g) use ($uyeGrupIdleri): bool {
    if ((int)$g['aktif'] !== 1 || in_array((int)$g['id'], $uyeGrupIdleri, true)) { return false; }
    return ($g['tur'] ?? 'grup') !== 'ozel' || (int)$g['uyelik_sayisi'] === 0;
}));
$protokolSonuclari = protocol_results_for_student($id);
$evOdevleri = assignments_active_for_student($id);
[$haftaPzt, $haftaPaz] = week_bounds();
$evHarita = completions_map(array_map(fn($o) => (int)$o['id'], $evOdevleri), $haftaPzt, $haftaPaz);
$evSeri = streak_for_student($id);
$aktifPaket = package_active($id);
$tumPaketler = packages_for($id);

$PAGE_TITLE = 'Öğrenci: ' . $ogrenci['kod'];
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik">
  <h1><?= e($ogrenci['kod']) ?></h1>
  <?= (int)$ogrenci['aktif'] === 1 ? '<span class="rozet rozet-tamam">Aktif</span>' : '<span class="rozet rozet-gri">Pasif</span>' ?>
  <?php foreach ($uyelikler as $uyelik): ?>
    <span class="rozet rozet-acik"><?= e($uyelik['ad']) ?></span>
  <?php endforeach; ?>
  <div class="sag">
    <a class="btn btn-birincil" href="<?= e(url('rapor-veli.php?ogrenci_id=' . $id)) ?>">Veli raporu hazırla</a>
  </div>
</div>

<div class="ozet-dizi">
  <div class="ozet-kart">
    <div class="sayi"><?= $istatistik['oran'] === null ? '—' : $istatistik['oran'] . '%' ?></div>
    <div class="etiket">Katılım oranı</div>
  </div>
  <div class="ozet-kart"><div class="sayi"><?= $istatistik['katildi'] ?></div><div class="etiket">Katıldı</div></div>
  <div class="ozet-kart"><div class="sayi"><?= $istatistik['gec'] ?></div><div class="etiket">Geç geldi</div></div>
  <div class="ozet-kart"><div class="sayi"><?= $istatistik['gelmedi'] ?></div><div class="etiket">Gelmedi</div></div>
</div>

<div class="kart">
  <h2>Öğrenci bilgileri</h2>
  <form method="post" action="<?= e(url('ogrenci.php?id=' . $id)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="form-grid">
      <label class="form-alan">Kod (takma ad)
        <input type="text" name="kod" class="girdi" value="<?= e(old('kod', $ogrenci['kod'])) ?>" maxlength="40" required>
      </label>
      <label class="form-alan">Doğum yılı
        <input type="number" name="dogum_yili" class="girdi" value="<?= e((string)old('dogum_yili', $ogrenci['dogum_yili'])) ?>"
               min="1920" max="<?= (int)now()->format('Y') ?>">
      </label>
      <label class="form-alan">Durum
        <input type="hidden" name="aktif" value="0">
        <span class="onay-kutu"><input type="checkbox" name="aktif" value="1" <?= (int)$ogrenci['aktif'] === 1 ? 'checked' : '' ?>> Öğrenci aktif</span>
      </label>
      <label class="form-alan form-genis">Veli notu
        <textarea name="veli_notu" class="girdi" maxlength="500"><?= e(old('veli_notu', $ogrenci['veli_notu'])) ?></textarea>
      </label>
    </div>
    <div class="form-butonlar">
      <button type="submit" class="btn btn-birincil">Kaydet</button>
    </div>
  </form>
</div>

<div class="kart">
  <div class="kart-baslik">
    <h2>Özel ve grup dersleri</h2>
    <span class="rozet rozet-acik"><?= count($uyelikler) ?> aktif üyelik</span>
  </div>
  <p class="alan-ipucu">Aynı kişi bir özel dersin yanında birden fazla grup dersine katılabilir. Üyeliği kaldırmak geçmiş oturum ve yoklama kayıtlarını silmez.</p>
  <?php if ($eklenebilirGruplar): ?>
  <form method="post" action="<?= e(url('ogrenci.php?id=' . $id)) ?>" class="filtre-satir">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="islem" value="grup_ekle">
    <label class="form-alan">Ders / grup
      <select name="grup_id" class="secim" required>
        <option value="">Ders seçin</option>
        <?php foreach ($eklenebilirGruplar as $g): ?>
        <option value="<?= (int)$g['id'] ?>"><?= e($g['ad']) ?> — <?= e(GRUP_TUR_LABELS[$g['tur'] ?? 'grup']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="btn btn-birincil">Derse ekle</button>
  </form>
  <?php endif; ?>
  <?php if (!$uyelikler): ?>
    <div class="bos-durum">Bu kişi henüz bir derse veya gruba eklenmemiş.</div>
  <?php else: ?>
  <div class="tablo-sar" style="margin-top:1rem">
    <table class="tablo">
      <thead><tr><th scope="col">Ders / grup</th><th scope="col">Tür</th><th scope="col">Program</th><th scope="col">Üyelik başlangıcı</th><th scope="col"></th></tr></thead>
      <tbody>
      <?php foreach ($uyelikler as $g): ?>
        <tr>
          <td><a href="<?= e(url('grup.php?id=' . (int)$g['id'])) ?>"><?= e($g['ad']) ?></a></td>
          <td><?= e(GRUP_TUR_LABELS[$g['tur'] ?? 'grup']) ?></td>
          <td><?= e(GUNLER[(int)$g['gun']] ?? '') ?><?= $g['saat'] ? ' ' . e($g['saat']) : '' ?></td>
          <td><?= e(format_date_tr($g['uyelik_baslangic'], false)) ?></td>
          <td class="sayi">
            <form method="post" action="<?= e(url('ogrenci.php?id=' . $id)) ?>"
                  data-onay="<?= e($g['ad']) ?> üyeliği kaldırılsın mı? Geçmiş kayıtlar korunur.">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $id ?>">
              <input type="hidden" name="islem" value="grup_cikar">
              <input type="hidden" name="grup_id" value="<?= (int)$g['id'] ?>">
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
    <h2>Oturum geçmişi ve gözlemler</h2>
    <span class="rozet rozet-acik"><?= count($gecmis) ?> kayıt</span>
  </div>
  <?php if (!$gecmis): ?>
    <div class="bos-durum">Henüz yoklama kaydı yok. Oturum kaydı girildikçe burada zaman çizelgesi oluşur.</div>
  <?php else: ?>
  <ul class="zaman-cizelge">
    <?php foreach ($gecmis as $k): ?>
    <li>
      <div class="tarih">
        <a href="<?= e(url('oturum.php?id=' . (int)$k['oturum_id'])) ?>"><?= e(format_date_tr($k['tarih'])) ?></a>
        — <?= e($k['grup_ad']) ?> · Hafta <?= (int)$k['hafta_no'] ?>
        <span class="rozet rozet-<?= e($k['durum']) ?>"><?= e(KATILIM_LABELS[$k['durum']] ?? $k['durum']) ?></span>
      </div>
      <?php if (trim((string)$k['gozlem_notu']) !== ''): ?>
      <div class="not"><?= e($k['gozlem_notu']) ?></div>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>

<div class="kart">
  <div class="kart-baslik">
    <h2>Ev programı ve erişim</h2>
    <?php if ($evSeri > 0): ?><span class="rozet rozet-tamam"><span class="emoji-sus" aria-hidden="true">🔥</span> <?= $evSeri ?> gün seri</span><?php endif; ?>
    <div class="sag">
      <a class="btn btn-kucuk btn-golge" href="<?= e(url('ev.php?onizle=' . $id)) ?>" target="_blank" rel="noopener"><span class="emoji-sus" aria-hidden="true">👁</span> Ev sayfasını gör</a>
      <a class="btn btn-kucuk btn-golge" href="<?= e(url('ev-programi.php')) ?>">Ödev ata →</a>
    </div>
  </div>
  <div class="form-grid">
    <div class="form-alan">Erişim kodu
      <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
        <code style="font-size:1.5rem;font-weight:800;letter-spacing:.28em;background:#1c1917;color:#fbbf24;padding:.35rem .8rem;border-radius:10px"><?= e((string)$ogrenci['erisim_kodu']) ?></code>
        <form method="post" action="<?= e(url('ogrenci.php?id=' . $id)) ?>" style="display:inline"
              data-onay="Kod yenilenirse eski kod geçersiz olur. Devam edilsin mi?">
          <?= csrf_field() ?>
          <input type="hidden" name="islem" value="kod_yenile">
          <button type="submit" class="btn btn-kucuk btn-golge"><span class="emoji-sus" aria-hidden="true">↻</span> Yenile</button>
        </form>
      </div>
      <span class="alan-ipucu">Öğrenci <?= e(url('ev.php')) ?> adresine bu kodla girer.</span>
    </div>
    <div class="form-alan">Bu haftaki ödevler
      <?php if (!$evOdevleri): ?>
        <span class="alan-ipucu" style="font-weight:400">Güncel ödev yok.</span>
      <?php else: ?>
        <?php foreach ($evOdevleri as $o):
            $sayi = count($evHarita[(int)$o['id']] ?? []); ?>
        <div style="font-weight:400"><?= e($o['ad']) ?> —
          <strong><?= $sayi ?>/<?= (int)$o['hedef_gun'] ?></strong> gün</div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="kart">
  <div class="kart-baslik">
    <h2>Seans paketi</h2>
    <?php if ($aktifPaket && (int)$aktifPaket['kalan'] <= 2): ?>
    <span class="rozet rozet-bekliyor">Yenileme yaklaşıyor</span>
    <?php endif; ?>
  </div>
  <?php if ($aktifPaket): ?>
    <p><strong><?= e($aktifPaket['ad']) ?></strong>
       <?= mb_stripos((string)$aktifPaket['notlar'], 'moxo') !== false ? '<span class="rozet rozet-acik"><span class="emoji-sus" aria-hidden="true">🧠</span> MOXO\'lu</span>' : '' ?> ·
       <?= (int)$aktifPaket['kullanilan'] ?>/<?= (int)$aktifPaket['toplam_seans'] ?> seans kullanıldı ·
       <strong><?= (int)$aktifPaket['kalan'] ?> kaldı</strong>
       <span class="alan-ipucu">(başlangıç: <?= e(format_date_tr($aktifPaket['baslangic'], false)) ?>)</span></p>
    <?php if (trim((string)$aktifPaket['notlar']) !== ''): ?>
    <p class="alan-ipucu"><span class="emoji-sus" aria-hidden="true">📝</span> <?= e($aktifPaket['notlar']) ?></p>
    <?php endif; ?>
    <div class="sure-cubuk" style="max-width:420px;margin:.4rem 0 .8rem">
      <div class="sure-cubuk-dolu" style="width:<?= min(100, (int)round(100 * $aktifPaket['kullanilan'] / max(1, (int)$aktifPaket['toplam_seans']))) ?>%"></div>
    </div>
    <form method="post" action="<?= e(url('ogrenci.php?id=' . $id)) ?>" style="display:inline"
          data-onay="Aktif paket kapatılsın mı?">
      <?= csrf_field() ?>
      <input type="hidden" name="islem" value="paket_kapat">
      <input type="hidden" name="paket_id" value="<?= (int)$aktifPaket['id'] ?>">
      <button type="submit" class="btn btn-kucuk btn-golge">Paketi Kapat</button>
    </form>
  <?php else: ?>
    <p class="alan-ipucu">Aktif paket yok.</p>
  <?php endif; ?>
  <details style="margin-top:.8rem">
    <summary style="cursor:pointer;font-weight:700">＋ Yeni paket başlat</summary>
    <form method="post" action="<?= e(url('ogrenci.php?id=' . $id)) ?>" class="filtre-satir" style="margin-top:.6rem">
      <?= csrf_field() ?>
      <input type="hidden" name="islem" value="paket_olustur">
      <label class="form-alan">Seans sayısı
        <select name="toplam_seans" class="secim">
          <?php foreach ([8, 10, 12, 16, 20, 24] as $s): ?>
          <option value="<?= $s ?>" <?= $s === 10 ? 'selected' : '' ?>><?= $s ?> seans</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-alan">Başlangıç
        <input type="date" name="baslangic" class="girdi" value="<?= e(today()) ?>">
      </label>
      <label class="form-alan" style="min-width:170px">Ad (isteğe bağlı)
        <input type="text" name="paket_ad" class="girdi" maxlength="80" placeholder="Örn. Güz dönemi paketi">
      </label>
      <label class="form-alan" style="min-width:170px">Not
        <input type="text" name="paket_not" class="girdi" maxlength="200">
      </label>
      <label class="form-alan">MOXO
        <span class="onay-kutu"><input type="checkbox" name="moxo_dahil" value="1">
          🧠 Ön/son ölçüm dahil</span>
        <span class="alan-ipucu">Yetkili uzman uygular; eğitmen yorumlamaz.</span>
      </label>
      <button type="submit" class="btn btn-birincil">Başlat</button>
    </form>
    <span class="alan-ipucu">Yeni paket başlatınca önceki aktif paket kendiliğinden kapanır.
      Kullanım, başlangıç tarihinden itibaren "katıldı/geç" yoklamalarından sayılır.</span>
  </details>
  <?php if (count($tumPaketler) > ($aktifPaket ? 1 : 0)): ?>
  <div class="tablo-sar" style="margin-top:.8rem">
    <table class="tablo">
      <thead><tr><th scope="col">Paket</th><th scope="col">Başlangıç</th><th scope="col" class="sayi">Seans</th><th scope="col">Durum</th></tr></thead>
      <tbody>
        <?php foreach ($tumPaketler as $p): ?>
        <tr>
          <td><?= e($p['ad']) ?></td>
          <td><?= e(format_date_tr($p['baslangic'], false)) ?></td>
          <td class="sayi"><?= (int)$p['toplam_seans'] ?></td>
          <td><?= (int)$p['kapali'] === 1 ? '<span class="rozet rozet-gri">Kapalı</span>' : '<span class="rozet rozet-tamam">Aktif</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="kart">
  <div class="kart-baslik">
    <h2>Protokol sonuçları</h2>
    <span class="rozet rozet-acik"><?= count($protokolSonuclari) ?> kayıt</span>
    <div class="sag"><a class="btn btn-kucuk btn-golge" href="<?= e(url('metronom.php')) ?>">Metronom Stüdyosu →</a></div>
  </div>
  <?php if (!$protokolSonuclari): ?>
    <div class="bos-durum">Henüz protokol kaydı yok. <a href="<?= e(url('metronom.php')) ?>">Metronom Stüdyosu</a>'nda
      vuruş tutturma veya BPM bulma çalışması yapıp bu öğrenciye kaydedin.</div>
  <?php else: ?>
    <?php
    /* Varyant AYRI seri: 3:2 poliritim skoru ile 7:4 skoru aynı çubuk
       grafikte okunamaz (bkz. db.php v16). Varyantsız protokoller eskisi gibi. */
    $protokolSerileri = [];
    foreach ($protokolSonuclari as $r) {
        $protokolSerileri[protokol_seri_anahtari((string)$r['protokol'], (string)($r['varyant'] ?? ''))][] = $r;
    }
    protokol_seri_sirala($protokolSerileri);
    ?>
    <?php foreach ($protokolSerileri as $pKod => $serisi):
        $serisi = array_reverse($serisi); // eskiden yeniye trend ?>
    <h3 style="margin-top:.8rem"><?= e(protokol_etiketi($pKod)) ?> — zaman içinde skor</h3>
    <div class="cubuk-satir" style="align-items:flex-end;gap:6px;height:74px;margin-bottom:.4rem">
      <?php foreach ($serisi as $r): ?>
      <div title="<?= e(format_date_tr(substr($r['created_at'], 0, 10))) ?> · <?= (int)$r['skor'] ?>/100<?= $r['bpm'] ? ' · ' . (int)$r['bpm'] . ' BPM' : '' ?>"
           style="width:26px;background:var(--amber);border-radius:6px 6px 0 0;height:<?= max(4, (int)round(64 * (int)$r['skor'] / 100)) ?>px"></div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th scope="col">Tarih</th><th scope="col">Protokol</th><th scope="col" class="sayi">BPM</th><th scope="col" class="sayi">Skor</th><th scope="col">Not</th></tr></thead>
      <tbody>
        <?php foreach ($protokolSonuclari as $r): ?>
        <tr>
          <td><?= e(format_date_tr(substr($r['created_at'], 0, 10))) ?> <?= e(substr($r['created_at'], 11, 5)) ?></td>
          <td><?= e(protokol_etiketi(protokol_seri_anahtari((string)$r['protokol'], (string)($r['varyant'] ?? '')))) ?></td>
          <td class="sayi"><?= $r['bpm'] ? (int)$r['bpm'] : '—' ?></td>
          <td class="sayi"><strong><?= (int)$r['skor'] ?></strong>/100<?= !empty($r['standart']) ? ' <span title="Standart koşullarda ölçüm (📏)">📏</span>' : '' ?></td>
          <td><?= e(mb_strimwidth((string)$r['notlar'], 0, 50, '…')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="alan-ipucu">Skorlar eğitmenin iç izleme aracıdır; veli raporuna yansıtılmaz.</p>
  <?php endif; ?>
</div>

<div class="kart">
  <h2>Tehlikeli bölge</h2>
  <p class="alan-ipucu">Öğrenciyi silmek tüm yoklama ve gözlem kayıtlarını da siler. Geçmişi korumak için
     silmek yerine <strong>pasife almayı</strong> tercih edin.</p>
  <form method="post" action="<?= e(url('ogrenci-sil.php')) ?>"
        data-onay="<?= e($ogrenci['kod']) ?> kodlu öğrenciyi ve tüm kayıtlarını silmek üzeresiniz. Geri alınamaz. Emin misiniz?">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <button type="submit" class="btn btn-tehlike">Öğrenciyi Sil</button>
  </form>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
