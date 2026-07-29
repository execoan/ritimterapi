<?php
/** Raporlar — üç rapor tipi için hazırlama ekranı. */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

$gruplar = groups_list();
$ogrenciler = students_list();
$sekizHaftaOnce = now()->modify('-8 weeks')->format('Y-m-d');

$PAGE_TITLE = 'Raporlar';
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik"><h1>Raporlar</h1></div>

<div class="kart">
  <h2>📊 Öğrenci Rapor Merkezi</h2>
  <p class="alan-ipucu">Tek öğrencinin dönem görünümü: protokol trend grafikleri (gürültü bandı yorumlu),
     katılım çizelgesi, ev pratiği, teknikler ve gözlemler. Bölümler seçilebilir; belge ekranda
     düzenlenip <strong>Yazdır / PDF</strong> ile çıkarılır. <strong>İç rapordur</strong> — skor içerdiği
     için veliye verilmez; veli çıktısı aşağıdaki veli raporudur.</p>
  <?php if (!$ogrenciler): ?>
    <div class="bos-durum">Önce bir öğrenci kaydedin.</div>
  <?php else: ?>
  <form method="get" action="<?= e(url('ogrenci-rapor.php')) ?>" class="filtre-satir">
    <label class="form-alan">Öğrenci
      <select name="ogrenci_id" class="secim" required>
        <?php foreach ($ogrenciler as $o): ?>
        <option value="<?= (int)$o['id'] ?>"><?= e($o['kod']) ?><?= $o['grup_ad'] ? ' — ' . e($o['grup_ad']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan">Başlangıç
      <input type="date" name="from" class="girdi" value="<?= e($sekizHaftaOnce) ?>" required>
    </label>
    <label class="form-alan">Bitiş
      <input type="date" name="to" class="girdi" value="<?= e(today()) ?>" required>
    </label>
    <button type="submit" class="btn btn-birincil">Raporu Aç</button>
  </form>
  <?php endif; ?>
</div>

<div class="kart">
  <h2>Haftalık eğitmen raporu</h2>
  <p class="alan-ipucu">Seçilen haftada hangi gruplarda ne işlendi, katılım ve notlar.</p>
  <form method="get" action="<?= e(url('rapor-haftalik.php')) ?>" class="filtre-satir">
    <label class="form-alan">Hafta (herhangi bir günü seçin)
      <input type="date" name="tarih" class="girdi" value="<?= e(today()) ?>" required>
    </label>
    <button type="submit" class="btn btn-birincil">Raporu Hazırla</button>
  </form>
</div>

<div class="kart">
  <h2>Dönemlik grup raporu</h2>
  <p class="alan-ipucu">Teknik dağılımı ve katılım grafiğiyle grup dönem özeti.</p>
  <?php if (!$gruplar): ?>
    <div class="bos-durum">Önce bir grup oluşturun.</div>
  <?php else: ?>
  <form method="get" action="<?= e(url('rapor-donemlik.php')) ?>" class="filtre-satir">
    <label class="form-alan">Grup
      <select name="grup_id" class="secim" required>
        <?php foreach ($gruplar as $g): ?>
        <option value="<?= (int)$g['id'] ?>"><?= e($g['ad']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan">Başlangıç
      <input type="date" name="from" class="girdi" value="<?= e($sekizHaftaOnce) ?>" required>
    </label>
    <label class="form-alan">Bitiş
      <input type="date" name="to" class="girdi" value="<?= e(today()) ?>" required>
    </label>
    <button type="submit" class="btn btn-birincil">Raporu Hazırla</button>
  </form>
  <?php endif; ?>
</div>

<div class="kart">
  <h2>Veli raporu</h2>
  <p class="alan-ipucu">Öğrenci bazlı, kilitli dille: çalışılan teknikler, katılım oranı ve gözlemler.
     <strong>Sonuç/gelişim iddiası içermez.</strong></p>
  <?php if (!$ogrenciler): ?>
    <div class="bos-durum">Önce bir öğrenci kaydedin.</div>
  <?php else: ?>
  <form method="get" action="<?= e(url('rapor-veli.php')) ?>" class="filtre-satir">
    <label class="form-alan">Öğrenci
      <select name="ogrenci_id" class="secim" required>
        <?php foreach ($ogrenciler as $o): ?>
        <option value="<?= (int)$o['id'] ?>"><?= e($o['kod']) ?><?= $o['grup_ad'] ? ' — ' . e($o['grup_ad']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan">Başlangıç
      <input type="date" name="from" class="girdi" value="<?= e($sekizHaftaOnce) ?>" required>
    </label>
    <label class="form-alan">Bitiş
      <input type="date" name="to" class="girdi" value="<?= e(today()) ?>" required>
    </label>
    <button type="submit" class="btn btn-birincil">Raporu Hazırla</button>
  </form>
  <?php endif; ?>
</div>

<div class="kart">
  <h2>Katılım belgesi (sertifika)</h2>
  <p class="alan-ipucu">Dönem sonu için yazdırılabilir, şık katılım belgesi. İstenirse dönem başı/sonu
     metronom ölçümleri <strong>yalın sayı</strong> olarak eklenir — yorum veya sonuç iddiası içermez.
     Belge yazdırılmadan önce ekranda düzenlenebilir (ör. takma kod yerine gerçek ad).</p>
  <?php if (!$ogrenciler): ?>
    <div class="bos-durum">Önce bir öğrenci kaydedin.</div>
  <?php else: ?>
  <form method="get" action="<?= e(url('sertifika.php')) ?>" class="filtre-satir">
    <label class="form-alan">Öğrenci
      <select name="ogrenci_id" class="secim" required>
        <?php foreach ($ogrenciler as $o): ?>
        <option value="<?= (int)$o['id'] ?>"><?= e($o['kod']) ?><?= $o['grup_ad'] ? ' — ' . e($o['grup_ad']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan">Başlangıç
      <input type="date" name="from" class="girdi" value="<?= e($sekizHaftaOnce) ?>" required>
    </label>
    <label class="form-alan">Bitiş
      <input type="date" name="to" class="girdi" value="<?= e(today()) ?>" required>
    </label>
    <label class="form-alan" style="justify-content:flex-end">
      <span style="display:flex;align-items:center;gap:.4rem;padding:.45rem 0">
        <input type="checkbox" name="olcumler" value="1"> İlk/son ölçümleri ekle
      </span>
    </label>
    <button type="submit" class="btn btn-birincil">Belgeyi Hazırla</button>
  </form>
  <?php endif; ?>
</div>

<div class="kart">
  <h2>Dışa aktarma (CSV)</h2>
  <p class="alan-ipucu">Kendi analizleriniz için ham veri — Excel/LibreOffice ile açılır
     (UTF-8, noktalı virgül ayraçlı). Kişisel izleme amaçlıdır; veli çıktısı değildir.</p>
  <div class="filtre-satir">
    <a class="btn btn-golge" href="<?= e(url('disa-aktar.php?tur=protokol')) ?>">📊 Protokol sonuçları (CSV)</a>
    <a class="btn btn-golge" href="<?= e(url('disa-aktar.php?tur=yoklama')) ?>">🗓 Yoklama kayıtları (CSV)</a>
  </div>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
