<?php
/**
 * Ritim Okuma Laboratuvarı — kendi sayfası.
 *
 * Metronom Stüdyosu'ndan AYRILDI: iki modül de Space tuşunu istiyordu ve
 * alıştırmanın üstüne metronom açılıyordu. Ayrı sayfada Space tek sahibe ait.
 *
 * 3 seviye × 8 ders × 16 alıştırma = 384 deterministik örnek (abcjs portesi).
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('ritim-okuma.php');
    $islem = (string)($_POST['islem'] ?? '');
    if ($islem === 'sonuc_kaydet') {
        /* protokol SUNUCUDA sabitlenir; istemcinin gönderdiği alan yok sayılır */
        $res = protocol_result_save(array_merge($_POST, ['protokol' => 'ritim_okuma']));
        flash_set($res['ok'] ? 'basari' : 'hata',
            $res['ok'] ? 'Ritim okuma sonucu kaydedildi.' : $res['error']);
    } elseif ($islem === 'sonuc_sil') {
        protocol_result_delete((int)($_POST['id'] ?? 0));
        flash_set('basari', 'Kayıt silindi.');
    }
    redirect('ritim-okuma.php');
}

$ogrenciler = students_list(null, 1);
$sonKayitlar = db()->query("SELECT p.*, o.kod AS ogrenci_kod
                              FROM protokol_sonuclari p
                              JOIN ogrenciler o ON o.id = p.ogrenci_id
                             WHERE p.protokol = 'ritim_okuma'
                             ORDER BY p.created_at DESC, p.id DESC LIMIT 12")->fetchAll();

/* Ders akışından gelindiyse geri dönüş bağlantısını koru (akış sayacı
   yeni sekmede sürdüğü için oturum bilgisi yalnız gösterim amaçlı). */
$akisId = max(0, (int)($_GET['akis'] ?? 0));

$PAGE_TITLE = 'Ritim Okuma Laboratuvarı';
require APP_DIR . '/includes/view/header.php';
?>
<link rel="stylesheet" href="<?= e(asset('css/metronom.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/ev.css')) ?>">

<div class="sayfa-baslik">
  <div>
    <h1>Ritim Okuma Laboratuvarı</h1>
    <p class="alan-ipucu">Ritmi önce dinle, sonra notada yazıldığı zamanlarda vur.
      Vuruş için <kbd>Space</kbd> ya da alandaki pad.</p>
  </div>
  <?php if ($akisId): ?>
  <a class="btn btn-kucuk btn-golge" href="<?= e(url('metronom.php?akis=' . $akisId)) ?>">← Ders akışına dön</a>
  <?php endif; ?>
</div>

<div class="kart">
  <p class="alan-ipucu">Yüzlerce kademeli alıştırmada ritmi önce gerçekten dinle, sonra sayarak ve notada
     yazıldığı zamanlarda vur. Kolay → Orta → Zor müfredatı dörtlüklerden senkop, onaltılık ve üçlemelere ilerler.</p>
  <div class="filtre-satir">
    <label class="form-alan">Öğrenci
      <select id="roOgrenci" class="secim">
        <option value="">— Seçin (kayıt için) —</option>
        <?php foreach ($ogrenciler as $o): ?>
        <option value="<?= (int)$o['id'] ?>"><?= e($o['kod']) ?><?= $o['grup_ad'] ? ' — ' . e($o['grup_ad']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-alan">Seviye
      <select id="roSeviye" class="secim">
        <option value="1" selected>Kolay — temel değerler</option>
        <option value="2">Orta — onaltılık ve senkop</option>
        <option value="3">Zor — üçleme ve karma deşifre</option>
      </select>
    </label>
    <label class="form-alan">Tempo
      <select id="roBpm" class="secim">
        <option value="50">50 BPM</option>
        <option value="60" selected>60 BPM</option>
        <option value="72">72 BPM</option>
        <option value="84">84 BPM</option>
        <option value="96">96 BPM</option>
        <option value="112">112 BPM</option>
      </select>
    </label>
    <label class="form-alan">Kılavuz
      <select id="roRehber" class="secim">
        <option value="tam">Her vuruş</option>
        <option value="olcu">Yalnız ölçü başı</option>
        <option value="sessiz">Sessiz deşifre</option>
      </select>
    </label>
    <button type="button" class="btn btn-golge" id="roYenile">Sonraki örnek →</button>
  </div>

  <label class="form-alan" style="margin-top:.2rem">
    <span><input type="checkbox" id="roStdMod"> 📏 Standart ölçüm koşulu</span>
    <small class="alan-ipucu">Seviye 1 · 60 BPM · her vuruş kılavuzlu. Dönem karşılaştırması yalnız
      aynı koşulda anlamlı; işaretliyken bu üç ayar kilitlenir.</small>
  </label>

  <div class="ro-kok" id="roKok"></div>

  <div class="m-kaydet-satir" id="roKaydetSatir" hidden>
    <form method="post" action="<?= e(url('ritim-okuma.php')) ?>" class="m-kaydet-form" id="roForm">
      <?= csrf_field() ?>
      <input type="hidden" name="islem" value="sonuc_kaydet">
      <input type="hidden" name="ogrenci_id" id="roFormOgrenci" value="">
      <input type="hidden" name="bpm" id="roFormBpm" value="">
      <input type="hidden" name="skor" id="roFormSkor" value="">
      <input type="hidden" name="detay" id="roFormDetay" value="">
      <input type="hidden" name="standart" id="roFormStandart" value="0">
      <input type="hidden" name="sd_ms" id="roFormSd" value="">
      <input type="hidden" name="kalite" id="roFormKalite" value="">
      <input type="text" name="notlar" class="girdi" maxlength="200" placeholder="Not (isteğe bağlı)" style="max-width:260px">
      <button type="submit" class="btn btn-birincil">Sonucu Kaydet</button>
    </form>
  </div>
</div>

<section class="kart" aria-labelledby="roGecmisBaslik">
  <div class="kart-baslik">
    <div>
      <h2 id="roGecmisBaslik">Son Ritim Okuma Ölçümleri</h2>
      <span class="alan-ipucu">Karşılaştırma kararlılık (SD) üzerinden okunur; skor sabit kaymayı içinde taşır.</span>
    </div>
  </div>
  <?php if (!$sonKayitlar): ?>
    <div class="bos-durum">Henüz kayıt yok. Bir alıştırmayı tamamlayıp öğrenciye kaydedin.</div>
  <?php else: ?>
  <div class="tablo-sar">
    <table class="tablo">
      <thead><tr><th>Tarih</th><th>Katılımcı</th><th class="sayi">BPM</th>
        <th class="sayi">Skor</th><th class="sayi">SD</th><th>Not</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($sonKayitlar as $k): ?>
        <tr>
          <td><?= e(format_date_tr(substr($k['created_at'], 0, 10))) ?> <?= e(substr($k['created_at'], 11, 5)) ?></td>
          <td><?= e($k['ogrenci_kod']) ?></td>
          <td class="sayi"><?= $k['bpm'] ? (int)$k['bpm'] : '—' ?></td>
          <td class="sayi"><strong><?= (int)$k['skor'] ?></strong>/100<?= (int)($k['standart'] ?? 0) === 1 ? ' 📏' : '' ?></td>
          <td class="sayi"><?= $k['sd_ms'] !== null && $k['sd_ms'] !== '' ? (int)$k['sd_ms'] . ' ms' : '—' ?></td>
          <td><?= e(mb_strimwidth((string)$k['notlar'], 0, 40, '…')) ?></td>
          <td class="sag">
            <form method="post" action="<?= e(url('ritim-okuma.php')) ?>" onsubmit="return confirm('Kayıt silinsin mi?')">
              <?= csrf_field() ?>
              <input type="hidden" name="islem" value="sonuc_sil">
              <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
              <button type="submit" class="btn btn-kucuk btn-tehlike">Sil</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <p class="alan-ipucu">Skorlar eğitmenin iç izleme aracıdır; veli raporuna yansıtılmaz.</p>
</section>

<script src="<?= e(asset('js/zamanlama-cekirdegi.js')) ?>"></script>
<script src="<?= e(asset('js/ritim-ogrenme.js')) ?>"></script>
<script src="<?= e(asset('vendor/abcjs/abcjs-basic-min.js')) ?>"></script>
<script src="<?= e(asset('js/ritim-okuma.js')) ?>"></script>
<script src="<?= e(asset('js/ritim-okuma-sayfa.js')) ?>"></script>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
