<?php
/**
 * Site Yönetimi — tanıtım sayfasına panelden hükmet:
 * bölümleri sürükleyerek sırala, göster/gizle, başlık ve metinleri düzenle.
 */
define('RITIM', 1);
require __DIR__ . '/includes/bootstrap.php';

// Panelden düzenlenebilen metin anahtarları (etiketleriyle)
$METIN_ALANLARI = [
    'Hero (giriş sahnesi)' => [
        'hero_ustbaslik' => ['Üst başlık (küçük şerit yazı)', 'girdi'],
        'hero_baslik'    => ['Ana başlık — her satır ayrı yazın; SON satır turuncu vurgulu olur', 'coksatir'],
        'hero_aciklama'  => ['Açıklama paragrafı', 'coksatir'],
        'serit_metin'    => ['Kayan şerit metni (madde aralarına · koyun)', 'girdi'],
    ],
    /* Video bölümünün metinleri index.php'de okunuyordu ama hiçbir yerden
       yazılamıyordu — denetimde çıktı. Bağlantının kendisi Görünüm panelinde. */
    'Tanıtım videosu' => [
        'video_ustbaslik' => ['Üst başlık (küçük şerit yazı)', 'girdi'],
        'video_baslik'    => ['Bölüm başlığı', 'girdi'],
        'video_aciklama'  => ['Açıklama (boş bırakılırsa hiç gösterilmez)', 'coksatir'],
    ],
    'Sayı kartları' => [
        'sayi_1_deger' => ['1. değer', 'girdi'], 'sayi_1_etiket' => ['1. etiket', 'girdi'],
        'sayi_2_deger' => ['2. değer', 'girdi'], 'sayi_2_etiket' => ['2. etiket', 'girdi'],
        'sayi_3_deger' => ['3. değer', 'girdi'], 'sayi_3_etiket' => ['3. etiket', 'girdi'],
        'sayi_4_deger' => ['4. değer', 'girdi'], 'sayi_4_etiket' => ['4. etiket', 'girdi'],
    ],
    'Program bölümü' => [
        'program_aciklama' => ['Program tanıtım paragrafı', 'coksatir'],
    ],
    'Canlı deneyler ve nabız' => [
        'deney_aciklama' => ['Deney bölümü tanıtım paragrafı', 'coksatir'],
        'deney_cta'      => ['Deneylerin altındaki davet cümlesi', 'coksatir'],
        'nabiz_aciklama' => ['Nabız bölümü tanıtım paragrafı', 'coksatir'],
    ],
    'MOXO bölümü' => [
        'moxo_aciklama'   => ['MOXO tanıtım paragrafı', 'coksatir'],
        'moxo_uygulayici' => ['Uygulayıcı cümlesi (psikologlar)', 'coksatir'],
        'moxo_not'        => ['Dürüst çerçeve notu', 'coksatir'],
    ],
    'Biz kimiz' => [
        'bizkimiz_metin' => ['Tanıtım metni', 'coksatir'],
    ],
    'İletişim' => [
        'iletisim_telefon'   => ['Telefon', 'girdi'],
        'iletisim_eposta'    => ['E-posta', 'girdi'],
        'iletisim_instagram' => ['Instagram', 'girdi'],
        'iletisim_adres'     => ['Adres', 'girdi'],
    ],
    'Alt bilgi' => [
        'alt_uyari' => ['Alt uyarı metni (eğitim aracı ibaresi)', 'coksatir'],
    ],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check('site.php');
    $islem = (string)($_POST['islem'] ?? 'icerik');

    if ($islem === 'icerik') {
        site_sections_save(
            (array)($_POST['bolum_sira'] ?? []),
            (array)($_POST['gorunur'] ?? []),
            (array)($_POST['baslik'] ?? [])
        );
        $gelenMetinler = (array)($_POST['metin'] ?? []);
        foreach ($METIN_ALANLARI as $grup) {
            foreach ($grup as $anahtar => $_tanim) {
                if (array_key_exists($anahtar, $gelenMetinler)) {
                    site_text_set($anahtar, (string)$gelenMetinler[$anahtar]);
                }
            }
        }
        flash_set('basari', 'Site içeriği kaydedildi. Tanıtım sayfası güncellendi.');
        redirect('site.php');
    }

    if ($islem === 'makaleler') {
        if (!empty($_POST['makale_sil'])) {
            site_article_delete((int)$_POST['makale_sil']);
            flash_set('basari', 'Makale kartı silindi.');
        } else {
            site_articles_save(
                (array)($_POST['makale_sira'] ?? []),
                (array)($_POST['makale'] ?? []),
                (array)($_POST['makale_gorunur'] ?? [])
            );
            flash_set('basari', 'Bilim kartları kaydedildi.');
        }
        redirect('site.php#makaleler');
    }

    if ($islem === 'makale_ekle') {
        site_article_add();
        flash_set('basari', 'Yeni makale kartı eklendi — alanlarını doldurup kaydedin.');
        redirect('site.php#makaleler');
    }

    if ($islem === 'galeri_yukle') {
        $res = gallery_add($_FILES['gorsel'] ?? [], (string)($_POST['gorsel_baslik'] ?? ''));
        flash_set($res['ok'] ? 'basari' : 'hata', $res['ok'] ? 'Görsel galeriye eklendi.' : $res['error']);
        redirect('site.php#galeri');
    }

    if ($islem === 'galeri_kaydet') {
        if (!empty($_POST['galeri_sil'])) {
            gallery_delete((int)$_POST['galeri_sil']);
            flash_set('basari', 'Görsel silindi.');
        } else {
            gallery_save(
                (array)($_POST['galeri_sira'] ?? []),
                (array)($_POST['galeri_baslik'] ?? []),
                (array)($_POST['galeri_gorunur'] ?? [])
            );
            flash_set('basari', 'Galeri kaydedildi.');
        }
        redirect('site.php#galeri');
    }

    /* ---- Görünüm: tema rengi + animasyon + tanıtım videosu ----
       WordPress mantığı: eğitmen kod dosyasına dokunmadan siteyi giydirir.
       Tüm değerler doğrulanır; geçersiz olan SESSİZCE eski değerde kalmaz,
       açıkça söylenir — "kaydettim sandım" en sinsi hatadır. */
    if ($islem === 'gorunum') {
        $hatalar = [];

        /* Hazır tema düğmesi: iki rengi birden doldurur */
        $hazir = (string)($_POST['hazir'] ?? '');
        if ($hazir !== '' && isset(TEMA_HAZIR[$hazir])) {
            site_text_set('tema_vurgu', TEMA_HAZIR[$hazir]['vurgu']);
            site_text_set('tema_ikincil', TEMA_HAZIR[$hazir]['ikincil']);
            flash_set('basari', '“' . TEMA_HAZIR[$hazir]['ad'] . '” teması uygulandı.');
            redirect('site.php#gorunum');
        }

        $vurgu = strtolower(trim((string)($_POST['tema_vurgu'] ?? '')));
        $ikincil = strtolower(trim((string)($_POST['tema_ikincil'] ?? '')));
        $kontrastNotu = '';
        if (hex_gecerli($vurgu)) {
            /* Renk seçici serbest; site zemini koyu (#0c0a09). Çok koyu bir
               ana renk marka yazısını, bağlantıları ve dolu düğmeleri
               okunamaz yapar (#000000 → türevler #141414). Sessizce kabul
               edip siteyi bozmak yerine WCAG AA eşiğine açıyoruz ve
               DEĞİŞTİRDİĞİMİZİ söylüyoruz — kullanıcı ne olduğunu bilsin. */
            $okunur = tema_okunur_vurgu($vurgu);
            site_text_set('tema_vurgu', $okunur['renk']);
            if ($okunur['duzeltildi']) {
                $kontrastNotu = ' Not: seçtiğiniz ana renk koyu zeminde okunmuyordu ('
                    . number_format(kontrast_orani($vurgu, TEMA_ZEMIN), 1, ',', '') . ':1); '
                    . 'tonu korunarak ' . $okunur['renk'] . ' değerine açıldı.';
            }
        }
        else { $hatalar[] = 'ana renk (#rrggbb biçiminde olmalı)'; }
        if (hex_gecerli($ikincil)) { site_text_set('tema_ikincil', $ikincil); }
        else { $hatalar[] = 'karşı ışık rengi'; }

        $anim = (string)($_POST['tema_animasyon'] ?? 'tam');
        site_text_set('tema_animasyon',
            in_array($anim, ['tam', 'sakin', 'kapali'], true) ? $anim : 'tam');

        $videoUrl = trim((string)($_POST['video_url'] ?? ''));
        if ($videoUrl === '') {
            site_text_set('video_url', '');   // bölüm kendiliğinden gizlenir
        } elseif (video_embed_bilgisi($videoUrl)) {
            site_text_set('video_url', $videoUrl);
        } else {
            $hatalar[] = 'video bağlantısı (YouTube/Vimeo bağlantısı ya da'
                . ' assets/ altında .mp4 yolu olmalı)';
        }

        flash_set($hatalar ? 'hata' : 'basari', ($hatalar
            ? 'Şunlar kaydedilemedi: ' . implode(' · ', $hatalar) . '. Kalanlar kaydedildi.'
            : 'Görünüm kaydedildi. Tanıtım sayfası, giriş ve katılımcı portalı güncellendi.')
            . $kontrastNotu);
        redirect('site.php#gorunum');
    }

    redirect('site.php');
}

$bolumler = site_sections();
$makaleler = site_articles();
$galeri = gallery_list();
$PAGE_TITLE = 'Site Yönetimi';
require APP_DIR . '/includes/view/header.php';
?>
<div class="sayfa-baslik">
  <h1>Site Yönetimi</h1>
  <div class="sag">
    <a class="btn btn-golge" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener"><span class="emoji-sus" aria-hidden="true">🌐</span> Siteyi Görüntüle</a>
  </div>
</div>

<?php $tema = tema_ayarlari(); $videoKayitli = site_text('video_url', ''); ?>
<div class="kart" id="gorunum">
  <div class="kart-baslik">
    <div>
      <h2><span class="emoji-sus" aria-hidden="true">🎨</span> Görünüm</h2>
      <span class="alan-ipucu">Tanıtım sayfasının teması — kod dosyasına dokunmadan.
        Açık/koyu tonlar seçtiğiniz ana renkten kendiliğinden türetilir.</span>
    </div>
  </div>

  <?php /* Hazır temalar AYRI bir form. Aynı formdayken metin alanında Enter'a
           basmak, tarayıcı kuralı gereği formun İLK gönder düğmesini tetikliyordu:
           yazılan video bağlantısı çöpe gidiyor, tema sessizce "Amber Sahne"ye
           dönüyordu. Ayırınca Enter her zaman "Görünümü Kaydet"i çalıştırır. */ ?>
  <form method="post" action="<?= e(url('site.php')) ?>" class="tema-hazir-form">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="gorunum">
    <div class="form-alan"><span>Hazır temalar</span>
      <div class="tema-hazirlar">
        <?php foreach (TEMA_HAZIR as $anahtar => $h): ?>
        <button type="submit" name="hazir" value="<?= e($anahtar) ?>" class="tema-hazir-btn"
                title="<?= e($h['ad']) ?> temasını uygula">
          <span class="tema-renk" style="background:<?= e($h['vurgu']) ?>"></span><span
                class="tema-renk" style="background:<?= e($h['ikincil']) ?>"></span>
          <?= e($h['ad']) ?><?= $tema['vurgu'] === $h['vurgu'] && $tema['ikincil'] === $h['ikincil'] ? ' ✓' : '' ?>
        </button>
        <?php endforeach; ?>
      </div>
      <small class="alan-ipucu">Hazır tema yalnız iki rengi değiştirir; animasyon ve
        video ayarları aşağıdaki formda, ayrıca kaydedilir.</small>
    </div>
  </form>

  <form method="post" action="<?= e(url('site.php')) ?>" class="gorunum-form">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="gorunum">

    <div class="form-grid">
      <label class="form-alan">Ana renk
        <input type="color" name="tema_vurgu" value="<?= e($tema['vurgu']) ?>" class="girdi tema-renk-girdi">
        <small class="alan-ipucu">Düğmeler, vurgular, metronom — sitenin kimliği.</small>
      </label>
      <label class="form-alan">Karşı ışık
        <input type="color" name="tema_ikincil" value="<?= e($tema['ikincil']) ?>" class="girdi tema-renk-girdi">
        <small class="alan-ipucu">Arka plandaki ikinci parıltı (aurora).</small>
      </label>
      <label class="form-alan">Animasyon
        <select name="tema_animasyon" class="secim">
          <option value="tam" <?= $tema['animasyon'] === 'tam' ? 'selected' : '' ?>>Tam — sahne ışıkları, kayan şerit, tümü</option>
          <option value="sakin" <?= $tema['animasyon'] === 'sakin' ? 'selected' : '' ?>>Sakin — süs animasyonları durur, geçişler kalır</option>
          <option value="kapali" <?= $tema['animasyon'] === 'kapali' ? 'selected' : '' ?>>Kapalı — tümü durur</option>
        </select>
        <small class="alan-ipucu">Ziyaretçinin sistem tercihi ve sayfadaki ⏸ düğmesi her durumda üstündür.</small>
      </label>
    </div>

    <div class="form-alan">
      <label for="videoUrl">Tanıtım videosu bağlantısı</label>
      <input type="text" name="video_url" id="videoUrl" class="girdi"
             value="<?= e($videoKayitli) ?>" placeholder="https://www.youtube.com/watch?v=…  ·  https://vimeo.com/…  ·  assets/video/tanitim.mp4">
      <small class="alan-ipucu">
        Doluysa ana sayfada “Tanıtım” bölümü görünür; <strong>boş bırakınca bölüm kaybolur</strong>.
        YouTube/Vimeo videoları ziyaretçi dokunmadan yüklenmez (tıkla-oynat kapak).
        Sıralamadaki “Tanıtım videosu” satırından da açıp kapatabilirsiniz.
      </small>
    </div>

    <button type="submit" class="btn btn-birincil">Görünümü Kaydet</button>
  </form>
</div>

<form method="post" action="<?= e(url('site.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="islem" value="icerik">

  <div class="kart">
    <div class="kart-baslik">
      <h2>Bölüm sıralaması</h2>
      <span class="alan-ipucu">Satırları sürükleyerek sitedeki sırayı değiştirin; kutudan görünürlüğü kapatın.</span>
    </div>
    <ul class="plan-liste surukle-liste">
      <?php foreach ($bolumler as $b): ?>
      <li draggable="true">
        <span class="plan-tutamac" title="Sürükleyerek sırala">☰</span>
        <input type="hidden" name="bolum_sira[]" value="<?= e($b['anahtar']) ?>">
        <div class="plan-bilgi">
          <span class="ad"><?= e(['yontem' => 'Yöntem', 'program' => 'Program', 'bilim' => 'Bilim',
              'protokoller' => 'Protokoller', 'bizkimiz' => 'Biz Kimiz', 'iletisim' => 'İletişim'][$b['anahtar']] ?? $b['anahtar']) ?></span>
          <input type="text" name="baslik[<?= e($b['anahtar']) ?>]" class="girdi plan-not-girdi"
                 value="<?= e($b['baslik']) ?>" maxlength="120" aria-label="Bölüm başlığı">
        </div>
        <label class="onay-kutu" style="align-self:center">
          <input type="checkbox" name="gorunur[<?= e($b['anahtar']) ?>]" value="1" <?= (int)$b['gorunur'] === 1 ? 'checked' : '' ?>>
          Görünür
        </label>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <?php foreach ($METIN_ALANLARI as $grupAdi => $alanlar): ?>
  <div class="kart">
    <h2><?= e($grupAdi) ?></h2>
    <div class="form-grid">
      <?php foreach ($alanlar as $anahtar => [$etiket, $tur]): ?>
      <label class="form-alan<?= $tur === 'coksatir' ? ' form-genis' : '' ?>"><?= e($etiket) ?>
        <?php if ($tur === 'coksatir'): ?>
        <textarea name="metin[<?= e($anahtar) ?>]" class="girdi" rows="3" maxlength="1200"><?= e(site_text($anahtar)) ?></textarea>
        <?php else: ?>
        <input type="text" name="metin[<?= e($anahtar) ?>]" class="girdi" value="<?= e(site_text($anahtar)) ?>" maxlength="300">
        <?php endif; ?>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="uyari-kutu">
    Dil hatırlatması: tanıtım sitesi veliye açık bir yüzeydir — tanı, tedavi ve sonuç vaadi
    içeren ifadeler kullanmayın (bkz. proje kırmızı çizgileri).
  </div>

  <div class="form-butonlar">
    <button type="submit" class="btn btn-birincil">Kaydet ve Yayınla</button>
    <a class="btn btn-golge" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">Önizle</a>
  </div>
</form>

<!-- ==================== BİLİM KARTLARI ==================== -->
<div class="kart" id="makaleler">
  <div class="kart-baslik">
    <h2>Bilim kartları</h2>
    <span class="alan-ipucu">Sürükleyerek sıralayın; alanları düzenleyip topluca kaydedin.</span>
  </div>
  <form method="post" action="<?= e(url('site.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="makaleler">
    <?php if (!$makaleler): ?>
      <div class="bos-durum">Henüz makale kartı yok — aşağıdan ekleyin.</div>
    <?php else: ?>
    <ul class="plan-liste surukle-liste">
      <?php foreach ($makaleler as $mk): $mid = (int)$mk['id']; ?>
      <li draggable="true">
        <span class="plan-tutamac" title="Sürükleyerek sırala">☰</span>
        <input type="hidden" name="makale_sira[]" value="<?= $mid ?>">
        <div class="plan-bilgi">
          <div class="form-grid" style="gap:.5rem .8rem">
            <label class="form-alan">Başlık
              <input type="text" name="makale[<?= $mid ?>][baslik]" class="girdi" value="<?= e($mk['baslik']) ?>" maxlength="140" required>
            </label>
            <label class="form-alan">Künye (yazar, yıl, dergi)
              <input type="text" name="makale[<?= $mid ?>][kunye]" class="girdi" value="<?= e($mk['kunye']) ?>" maxlength="180">
            </label>
            <label class="form-alan">Kanıt rozeti
              <select name="makale[<?= $mid ?>][rozet]" class="secim">
                <?php foreach (KANIT_LABELS as $kod => $ad): ?>
                <option value="<?= e($kod) ?>" <?= $mk['rozet'] === $kod ? 'selected' : '' ?>><?= e($ad) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="form-alan form-genis">Ne buldu?
              <textarea name="makale[<?= $mid ?>][bulgu]" class="girdi" rows="2" maxlength="500"><?= e($mk['bulgu']) ?></textarea>
            </label>
            <label class="form-alan form-genis">Atölyedeki yansıması
              <textarea name="makale[<?= $mid ?>][yansima]" class="girdi" rows="2" maxlength="500"><?= e($mk['yansima']) ?></textarea>
            </label>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:.5rem;align-items:flex-end">
          <label class="onay-kutu">
            <input type="checkbox" name="makale_gorunur[<?= $mid ?>]" value="1" <?= (int)$mk['gorunur'] === 1 ? 'checked' : '' ?>> Görünür
          </label>
          <button type="submit" name="makale_sil" value="<?= $mid ?>" class="btn btn-kucuk btn-tehlike"
                  data-onay-btn="Bu makale kartı silinsin mi?">Sil</button>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <div class="form-butonlar">
      <button type="submit" class="btn btn-birincil">Bilim Kartlarını Kaydet</button>
    </div>
  </form>
  <form method="post" action="<?= e(url('site.php')) ?>" style="margin-top:.6rem">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="makale_ekle">
    <button type="submit" class="btn btn-golge">+ Yeni Makale Kartı</button>
  </form>
</div>

<!-- ==================== FOTO GALERİSİ ==================== -->
<div class="kart" id="galeri">
  <div class="kart-baslik">
    <h2>Foto galerisi</h2>
    <span class="alan-ipucu">Ana sayfadaki "Atölyeden kareler" bölümü. Görsel yoksa bölüm otomatik gizlenir.</span>
  </div>

  <form method="post" action="<?= e(url('site.php')) ?>" enctype="multipart/form-data" class="filtre-satir">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="galeri_yukle">
    <label class="form-alan">Görsel (JPEG/PNG/WebP, ≤ 4 MB)
      <input type="file" name="gorsel" class="girdi" accept="image/jpeg,image/png,image/webp,image/gif" required>
    </label>
    <label class="form-alan">Alt yazı (isteğe bağlı)
      <input type="text" name="gorsel_baslik" class="girdi" maxlength="120" placeholder="Örn. Daire senkroni çalışması">
    </label>
    <button type="submit" class="btn btn-birincil">Yükle</button>
  </form>

  <?php if (!$galeri): ?>
    <div class="bos-durum">Henüz görsel yok. Atölyeden kareler yükleyin — kişilerin yüzü görünen fotoğraflar için izin almayı unutmayın.</div>
  <?php else: ?>
  <form method="post" action="<?= e(url('site.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="galeri_kaydet">
    <ul class="plan-liste surukle-liste">
      <?php foreach ($galeri as $foto): $fid = (int)$foto['id']; ?>
      <li draggable="true">
        <span class="plan-tutamac" title="Sürükleyerek sırala">☰</span>
        <input type="hidden" name="galeri_sira[]" value="<?= $fid ?>">
        <img src="<?= e(asset('img/galeri/' . basename($foto['dosya']))) ?>" alt=""
             style="width:86px;height:60px;object-fit:cover;border-radius:8px;border:1px solid var(--cizgi)">
        <div class="plan-bilgi">
          <input type="text" name="galeri_baslik[<?= $fid ?>]" class="girdi" value="<?= e($foto['baslik']) ?>"
                 maxlength="120" placeholder="Alt yazı">
        </div>
        <div style="display:flex;flex-direction:column;gap:.5rem;align-items:flex-end">
          <label class="onay-kutu">
            <input type="checkbox" name="galeri_gorunur[<?= $fid ?>]" value="1" <?= (int)$foto['gorunur'] === 1 ? 'checked' : '' ?>> Görünür
          </label>
          <button type="submit" name="galeri_sil" value="<?= $fid ?>" class="btn btn-kucuk btn-tehlike"
                  data-onay-btn="Bu görsel silinsin mi?">Sil</button>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <div class="form-butonlar">
      <button type="submit" class="btn btn-birincil">Galeriyi Kaydet</button>
    </div>
  </form>
  <?php endif; ?>
</div>
<?php require APP_DIR . '/includes/view/footer.php'; ?>
