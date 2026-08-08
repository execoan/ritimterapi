<?php
if (!defined('RITIM')) { http_response_code(403); exit; }

/* ---------- Çıktı ve adres yardımcıları ---------- */

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Uygulamanın site kökünden sonraki yolu ('' veya '/RitimTerapi' gibi). */
function base_url(): string
{
    static $base = null;
    if ($base !== null) { return $base; }
    $doc = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
    $app = str_replace('\\', '/', APP_DIR);
    if ($doc !== '' && (strcasecmp($app, $doc) === 0 || stripos($app, $doc . '/') === 0)) {
        return $base = rtrim(substr($app, strlen($doc)), '/');
    }
    return $base = '';
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

/** CSS/JS/görsel adresi — dosya değişince önbellek damgası da değişir. */
function asset(string $path): string
{
    $path  = ltrim($path, '/');
    $adres = url('assets/' . $path);
    $zaman = @filemtime(APP_DIR . '/assets/' . $path);
    return $zaman ? $adres . '?v=' . $zaman : $adres;
}

function redirect(string $path, int $code = 303): void
{
    header('Location: ' . url($path), true, $code);
    exit;
}

function not_found(string $mesaj = 'Aradığınız kayıt bulunamadı.'): void
{
    http_response_code(404);
    $PAGE_TITLE = 'Bulunamadı';
    require APP_DIR . '/includes/view/header.php';
    echo '<div class="kart"><h1>Bulunamadı</h1><p>' . e($mesaj) . '</p>'
       . '<p><a class="btn btn-birincil" href="' . e(url('index.php')) . '">Panele Dön</a></p></div>';
    require APP_DIR . '/includes/view/footer.php';
    exit;
}

/* ---------- Flash mesajları ve eski form girdisi ---------- */

/** $type: 'basari' | 'hata' | 'bilgi' | 'uyari' */
function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function set_old(array $data): void
{
    $_SESSION['old_input'] = $data;
}

function old(string $key, $default = '')
{
    static $old = null;
    if ($old === null) {
        $old = $_SESSION['old_input'] ?? [];
        unset($_SESSION['old_input']);
    }
    return $old[$key] ?? $default;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(?string $redirectTo = null): void
{
    $gelen = $_POST['csrf_token'] ?? '';
    if (is_string($gelen) && $gelen !== '' && hash_equals(csrf_token(), $gelen)) {
        return;
    }
    if ($redirectTo !== null) {
        flash_set('hata', 'Oturum doğrulaması zaman aşımına uğradı. Lütfen tekrar deneyin.');
        redirect($redirectTo);
    }
    http_response_code(400);
    exit('Geçersiz istek. Sayfayı yenileyip tekrar deneyin.');
}

/* ---------- Zaman ve tarih (Europe/Istanbul, hafta başı Pazartesi) ---------- */

function now(): DateTime
{
    return new DateTime('now');
}

function now_str(): string
{
    return now()->format('Y-m-d H:i:s');
}

function today(): string
{
    return now()->format('Y-m-d');
}

/** YYYY-MM-DD biçiminde ve takvimde gerçekten var olan tarih mi? */
function valid_date_ymd(?string $value): bool
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) { return false; }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
}

/** 24 saatlik HH:MM biçiminde gerçekten var olan saat mi? */
function valid_time_hm(?string $value): bool
{
    if (!is_string($value) || !preg_match('/^\d{2}:\d{2}$/', $value)) { return false; }
    $d = DateTimeImmutable::createFromFormat('!H:i', $value);
    return $d !== false && $d->format('H:i') === $value;
}

const GUNLER = [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe',
                5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'];
const GUNLER_KISA = [1 => 'Pzt', 2 => 'Sal', 3 => 'Çar', 4 => 'Per', 5 => 'Cum', 6 => 'Cmt', 7 => 'Paz'];
const AYLAR_KISA = [1 => 'Oca', 2 => 'Şub', 3 => 'Mar', 4 => 'Nis', 5 => 'May', 6 => 'Haz',
                    7 => 'Tem', 8 => 'Ağu', 9 => 'Eyl', 10 => 'Eki', 11 => 'Kas', 12 => 'Ara'];

/** 'YYYY-MM-DD' → '12 Oca 2026 Pzt' (geçersizse olduğu gibi döner). */
function format_date_tr(?string $ymd, bool $gunAdi = true): string
{
    if (!$ymd) { return '—'; }
    $tarih = substr($ymd, 0, 10);
    if (!valid_date_ymd($tarih)) { return $ymd; }
    $d = DateTime::createFromFormat('Y-m-d', $tarih);
    $s = (int)$d->format('j') . ' ' . AYLAR_KISA[(int)$d->format('n')] . ' ' . $d->format('Y');
    return $gunAdi ? $s . ' ' . GUNLER_KISA[(int)$d->format('N')] : $s;
}

/** Verilen tarihin (varsayılan bugün) Pazartesi–Pazar sınırları: ['YYYY-MM-DD', 'YYYY-MM-DD']. */
function week_bounds(?string $ymd = null): array
{
    $d = $ymd && valid_date_ymd($ymd) ? DateTime::createFromFormat('Y-m-d', $ymd) : now();
    $pzt = (clone $d)->modify('monday this week');
    $paz = (clone $pzt)->modify('+6 days');
    return [$pzt->format('Y-m-d'), $paz->format('Y-m-d')];
}

/** Grup başlangıcının haftasından (Pazartesi temelli) itibaren kaçıncı hafta. */
function week_no_for(?string $grupBaslangic, string $tarih): int
{
    $base = $grupBaslangic ?: $tarih;
    [$basePzt] = week_bounds($base);
    [$tarihPzt] = week_bounds($tarih);
    $fark = (new DateTime($basePzt))->diff(new DateTime($tarihPzt))->days;
    $isaret = $tarihPzt < $basePzt ? -1 : 1;
    return 1 + $isaret * (int)floor($fark / 7);
}

/* ---------- Türkçe sıralama (intl eklentisi olmadan) ---------- */

/** Türkçe kurallarla BÜYÜK harfe çevirir (i→İ, ı→I; mb_strtoupper bunu bilmez). */
function tr_upper(string $s): string
{
    return mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $s), 'UTF-8');
}

/** Türk alfabesi sırasına uyan karşılaştırma anahtarı üretir. */
function tr_sort_key(string $s): string
{
    // Önce Türkçe'ye özgü büyük harf dönüşümü, sonra küçült
    $s = strtr($s, ['I' => 'ı', 'İ' => 'i']);
    $s = mb_strtolower($s, 'UTF-8');
    // '{' > 'z' olduğundan ç/ğ/ö/ş/ü kendi tabanından hemen sonra sıralanır;
    // 'ı' için 'h{' kullanılır (h < ı < i).
    return strtr($s, ['ç' => 'c{', 'ğ' => 'g{', 'ı' => 'h{', 'ö' => 'o{', 'ş' => 's{', 'ü' => 'u{']);
}

/** Diziyi verilen alana göre Türkçe alfabetik sıralar. */
function tr_sort_by(array &$rows, string $key): void
{
    usort($rows, fn($a, $b) => strcmp(tr_sort_key((string)$a[$key]), tr_sort_key((string)$b[$key])));
}

/* ---------- Etiketler ---------- */

const KANIT_LABELS = ['guclu' => 'Güçlü', 'orta' => 'Orta', 'zayif' => 'Zayıf', 'yok' => 'Kanıt yok'];
const SEVIYE_LABELS = [1 => 'Başlangıç', 2 => 'Orta', 3 => 'İleri'];
const KATILIM_LABELS = ['katildi' => 'Katıldı', 'gec' => 'Geç geldi', 'gelmedi' => 'Gelmedi'];
const GRUP_TUR_LABELS = ['grup' => 'Grup dersi', 'ozel' => 'Özel ders'];
const PROTOKOL_LABELS = ['vurus_tutturma' => 'Vuruş Tutturma', 'bpm_bulma' => 'BPM Bulma',
                         'ritim_okuma' => 'Ritim Okuma', 'spontan_tempo' => 'Spontan Tempo',
                         'aksak_bulma' => 'Aksak Vuruş Algısı', 'icsel_ritim' => 'İçsel Ritim',
                         'poliritim' => 'Poliritim'];

/**
 * Seri anahtarını okunur etikete çevirir. Varyantlı protokoller "protokol|varyant"
 * biçiminde anahtarlanır (bkz. db.php v16): 'poliritim|3:2' → 'Poliritim · 3:2'.
 * Varyantsız anahtar eskisi gibi çalışır.
 */
function protokol_etiketi(string $anahtar): string
{
    [$kod, $varyant] = array_pad(explode('|', $anahtar, 2), 2, '');
    $ad = PROTOKOL_LABELS[$kod] ?? $kod;
    return $varyant === '' ? $ad : $ad . ' · ' . $varyant;
}
const EV_TUR_LABELS = ['serbest' => 'Serbest (işaretlemeli)', 'metronom' => 'Metronomlu süre',
                       'vurus_tutturma' => 'Vuruş Tutturma (mini)', 'ritim_okuma' => 'Ritim Okuma',
                       'icsel_ritim' => 'İçsel Ritim (mini)'];
const KITLE_LABELS = ['cocuk' => 'Çocuk & Genç', 'yetiskin' => 'Yetişkin', 'hepsi' => 'Hepsi'];

/* Ölçüm anındaki kalibrasyon kalitesi (zamanlama-cekirdegi.js kaliteDurumu.kod) */
const OLCUM_KALITE_LABELS = [
    'cok-iyi' => 'Çok iyi', 'iyi' => 'İyi', 'orta' => 'Orta', 'zayif' => 'Zayıf',
    'elle' => 'Elle ayarlı', 'eski' => 'Kalibrasyon eski', 'cihaz-degisti' => 'Cihaz değişmiş',
    'eksik' => 'Kalibrasyonsuz',
];
/** Karşılaştırmaya girmesi güvenli sayılan kalibrasyon kaliteleri. */
const OLCUM_KALITE_GUVENLI = ['cok-iyi', 'iyi', 'orta'];
const CALISMA_TUR_LABELS = [
    'rct' => 'Randomize kontrollü', 'deneysel' => 'Deneysel', 'meta' => 'Meta-analiz',
    'derleme' => 'Sistematik derleme', 'pilot' => 'Pilot / uygulanabilirlik',
    'gecerleme' => 'Geçerleme', 'protokol' => 'Protokol makalesi', 'diger' => 'Diğer',
];

/** DOI'yi çıplak biçime indirger (https://doi.org/ öneki ve boşluklar atılır). */
function doi_normalize(string $doi): string
{
    $doi = trim($doi);
    $doi = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi);
    return trim($doi);
}

function kanit_rozet(string $duzey): string
{
    $etiket = KANIT_LABELS[$duzey] ?? $duzey;
    return '<span class="rozet rozet-kanit-' . e($duzey) . '">' . e($etiket) . '</span>';
}

/* ---------- Veli raporu dil koruması ---------- */

/**
 * Veliye giden serbest metinde geçmemesi gereken ifadeler (CLAUDE.md kırmızı
 * çizgileri). Bulunan ifadeleri döndürür; boş dizi = temiz.
 */
/*
 * Denetimden çıkan iki kusur burada giderildi:
 *
 * 1) ÖRTÜK SONUÇ VAADİ kaçıyordu. Şu cümlelerin hiçbiri eski listeye takılmıyordu:
 *    "odaklanmasına yardımcı oluyor" · "belirgin gelişme gösterdi" ·
 *    "akranlarına göre daha hızlı ilerliyor" · "enerjisini yönetmeyi öğrendi"
 *    Hiçbiri yasaklı sözcük içermiyor ama hepsi CLAUDE.md §2'nin yasakladığı
 *    sonuç vaadi. Ayrıca §2'de açıkça yasaklanan "enerji" listede hiç yoktu.
 *
 * 2) YANLIŞ POZİTİF üretiyordu. Çıplak alt-dize araması yüzünden "kalıbı
 *    tanıdı", "arkadaşlarıyla tanıştı", "ritmi tanımladı" → "tanı" olarak
 *    işaretleniyordu; "hatasını fark edip düzeltti" → "düzelt". Üstelik bu
 *    cümlenin kardeşi ogrenci-rapor.php'de HAZIR CÜMLE olarak öneriliyor.
 *    Yanlış pozitif uyarıyı ucuzlatır; eğitmen bir süre sonra kırmızı kutuyu
 *    okumadan kapatır — yani koruma kendini yok eder.
 *    Çözüm: kök + izinli Türkçe ek deseni, artı bir istisna listesi.
 */

/** Kırmızı çizgi kalıpları: kök => sonrasına izin verilen ek deseni (regex). */
const KILITLI_DIL_KALIPLARI = [
    // Klinik / sağlık
    'terapi' => '', 'tedavi' => '', 'hasta' => '', 'danışan' => '', 'semptom' => '',
    'dehb' => '', 'dikkat eksikliği' => '', 'hiperakt' => '', 'gelişim geriliği' => '',
    'otizm' => '', 'disleksi' => '', 'klinik' => '', 'teşhis' => '', 'bozukluğ' => '',
    'spektrum' => '', 'psikolog' => '', 'psikiyatr' => '', 'rapor aldı' => '',
    // "tanı" YALNIZ tanı-koyma anlamında: "tanıdı/tanıştı/tanımla" hariç tutulur
    'tanı' => '(?![dşmyv])',
    'iyileş' => '', 'düzel' => '(?!t?me?y)',   // "düzeltti" gözlemsel olabilir → aşağıda istisna
    // Psödobilim (§2'de açıkça sayılanlar)
    'rezonans' => '', 'titreşim' => '', 'hücresel' => '', 'enerji' => '', 'frekans' => '',
    'şifa' => '', 'çakra' => '', 'blokaj' => '',
    // Normatif değerlendirme
    'geride kal' => '', 'geri kal' => '', 'yaşına göre' => '', 'riskli' => '',
    'akran' => '', 'yaşıt' => '', 'ortalamanın' => '', 'normalin' => '', 'yetersiz' => '',
    // Sonuç vaadi (açık)
    'geliştir' => '', 'iyi gel' => '', 'düzelt' => '',
    // Sonuç vaadi (örtük) — asıl boşluk buradaydı
    // DİKKAT — Türkçe çekim: "sağla" + "-ıyor" → "sağlıyor" (sondaki a düşer).
    // Kökü "sağla" yazmak "sağlıyor"u kaçırır; o yüzden kökler kısa tutuldu.
    'yardımcı ol' => '', 'katkı sağl' => '', 'destekliyor' => '', 'fayda' => '',
    'yarar' => '', 'işe yar' => '', 'olumlu etki' => '', 'ilerleme kaydet' => '',
    'kazanım' => '', 'daha iyi' => '', 'sakinleş' => '', 'rahatla' => '',
    'özgüven' => '', 'gelişme göster' => '', 'gelişim' => '',
];

/**
 * Yanlış pozitif istisnaları: bu kalıplar geçiyorsa o eşleşme sayılmaz.
 * Hepsi GÖZLEMSEL ifadelerdir — ne yapıldığını anlatır, sonuç iddia etmez.
 */
const KILITLI_DIL_ISTISNALARI = [
    'tanıdı', 'tanıştı', 'tanımla', 'tanıyor', 'tanır',
    'düzeltti', 'düzeltir', 'düzeltme yaptı', 'kendi kendini düzelt',
];

/**
 * Veliye giden serbest metinde geçmemesi gereken ifadeler (CLAUDE.md kırmızı
 * çizgileri). Bulunan ifadeleri döndürür; boş dizi = temiz.
 */
function locked_language_flags(string $text): array
{
    $t = mb_strtolower($text, 'UTF-8');
    // Türkçe büyük harf inceliği: I→ı, İ→i (mb_strtolower ikisini de kaçırabilir)
    $t = str_replace(['ı', 'İ', 'I'], ['ı', 'i', 'ı'], $t);

    // İstisna geçen bölümleri metinden düşür ki kök eşleşmesi tetiklenmesin
    $tarama = $t;
    foreach (KILITLI_DIL_ISTISNALARI as $istisna) {
        $tarama = str_replace(mb_strtolower($istisna, 'UTF-8'), ' ', $tarama);
    }

    $bulunan = [];
    foreach (KILITLI_DIL_KALIPLARI as $kok => $ek) {
        $desen = '/' . preg_quote(mb_strtolower($kok, 'UTF-8'), '/') . $ek . '/u';
        if (preg_match($desen, $tarama)) { $bulunan[] = $kok; }
    }
    return array_values(array_unique($bulunan));
}

/* =================================================================
   GÖRÜNÜM (TEMA) — WordPress benzeri: panelden renk/animasyon seçimi
   =================================================================
   Tasarım kararı: eğitmen TEK ana renk + TEK karşı ışık seçer; açık/koyu
   tonlar HSL uzayında buradan türetilir. Beş ayrı renk seçtirmek hem
   yorucu hem riskli (uyumsuz skala kolay); tek kaynaktan türetme her
   zaman tutarlı bir skala verir. Değerler site_icerik'te durur, landing
   CSS'i bu değişkenleri :root'ta bekler (varsayılan Amber).
   ================================================================= */

const TEMA_HAZIR = [
    'amber'   => ['ad' => 'Amber Sahne', 'vurgu' => '#f59e0b', 'ikincil' => '#7c3aed'],
    'okyanus' => ['ad' => 'Okyanus',     'vurgu' => '#38bdf8', 'ikincil' => '#f59e0b'],
    'zumrut'  => ['ad' => 'Zümrüt',      'vurgu' => '#34d399', 'ikincil' => '#818cf8'],
    'gul'     => ['ad' => 'Gül',         'vurgu' => '#fb7185', 'ikincil' => '#38bdf8'],
    'lavanta' => ['ad' => 'Lavanta',     'vurgu' => '#a78bfa', 'ikincil' => '#fbbf24'],
];

function hex_gecerli(string $h): bool
{
    return (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $h);
}

/** '#f59e0b' → '245 158 11' (CSS `rgb(var(--x) / a)` sözdizimi için). */
function hex_rgb_uclusu(string $hex): string
{
    return sprintf('%d %d %d',
        hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
}

/** RGB(0-255) → HSL(0-1) */
function rgb_hsl(int $r, int $g, int $b): array
{
    $r /= 255; $g /= 255; $b /= 255;
    $maks = max($r, $g, $b); $min = min($r, $g, $b);
    $l = ($maks + $min) / 2;
    if ($maks === $min) { return [0.0, 0.0, $l]; }
    $f = $maks - $min;
    $s = $l > .5 ? $f / (2 - $maks - $min) : $f / ($maks + $min);
    $h = match (true) {
        $maks === $r => (($g - $b) / $f + ($g < $b ? 6 : 0)) / 6,
        $maks === $g => (($b - $r) / $f + 2) / 6,
        default      => (($r - $g) / $f + 4) / 6,
    };
    return [$h, $s, $l];
}

/** HSL(0-1) → '#rrggbb' */
function hsl_hex(float $h, float $s, float $l): string
{
    if ($s == 0) { $r = $g = $b = $l; }
    else {
        $ton = function (float $p, float $q, float $t): float {
            if ($t < 0) { $t += 1; }
            if ($t > 1) { $t -= 1; }
            if ($t < 1 / 6) { return $p + ($q - $p) * 6 * $t; }
            if ($t < 1 / 2) { return $q; }
            if ($t < 2 / 3) { return $p + ($q - $p) * (2 / 3 - $t) * 6; }
            return $p;
        };
        $q = $l < .5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $r = $ton($p, $q, $h + 1 / 3); $g = $ton($p, $q, $h); $b = $ton($p, $q, $h - 1 / 3);
    }
    return sprintf('#%02x%02x%02x',
        (int)round($r * 255), (int)round($g * 255), (int)round($b * 255));
}

/** Rengi aydınlık ekseninde kaydırır (lFark: -1..+1), uçlarda kırpar. */
function tema_ton(string $hex, float $lFark): string
{
    [$h, $s, $l] = rgb_hsl(
        (int)hexdec(substr($hex, 1, 2)),
        (int)hexdec(substr($hex, 3, 2)),
        (int)hexdec(substr($hex, 5, 2)));
    return hsl_hex($h, $s, max(.08, min(.92, $l + $lFark)));
}

/** Kayıtlı tema; geçersiz/boş değerde Amber varsayılanı. */
function tema_ayarlari(): array
{
    $vurgu = site_text('tema_vurgu', '');
    if (!hex_gecerli($vurgu)) { $vurgu = '#f59e0b'; }
    $ikincil = site_text('tema_ikincil', '');
    if (!hex_gecerli($ikincil)) { $ikincil = '#7c3aed'; }
    $anim = site_text('tema_animasyon', '');
    if (!in_array($anim, ['tam', 'sakin', 'kapali'], true)) { $anim = 'tam'; }
    return ['vurgu' => strtolower($vurgu), 'ikincil' => strtolower($ikincil), 'animasyon' => $anim];
}

/**
 * <head> için :root değişken bloğu. Değerler yalnız doğrulanmış hex'ten
 * sayısal türetmeyle üretilir — CSS enjeksiyonu YAPISAL olarak imkânsız.
 * Varsayılan temada boş döner (CSS'teki değerler zaten Amber).
 */
function tema_stil_blogu(): string
{
    $t = tema_ayarlari();
    if ($t['vurgu'] === '#f59e0b' && $t['ikincil'] === '#7c3aed') { return ''; }
    $v = $t['vurgu'];
    return ':root{'
        . '--vurgu:'       . hex_rgb_uclusu($v) . ';'
        . '--vurgu-acik:'  . hex_rgb_uclusu(tema_ton($v, +.13)) . ';'
        . '--vurgu-koyu:'  . hex_rgb_uclusu(tema_ton($v, -.07)) . ';'
        . '--vurgu-derin:' . hex_rgb_uclusu(tema_ton($v, -.17)) . ';'
        . '--vurgu-kahve:' . hex_rgb_uclusu(tema_ton($v, -.27)) . ';'
        . '--ikincil:'     . hex_rgb_uclusu($t['ikincil']) . ';'
        . '}';
}

/** <html> sınıfı: sakin/kapalı animasyon kipi (Görünüm panelinden). */
function tema_html_sinif(): string
{
    $anim = tema_ayarlari()['animasyon'];
    if ($anim === 'kapali') { return ' class="hareket-kapali"'; }
    if ($anim === 'sakin')  { return ' class="tema-sakin"'; }
    return '';
}

/* =================================================================
   TANITIM VİDEOSU — panelden bağlantı, tıkla-yükle gömme
   ================================================================= */

/**
 * Video bağlantısını KATI biçimde çözer; tanımadığı her şey null.
 * Yalnız üç biçim: YouTube (nocookie gömme), Vimeo, yerel mp4/webm.
 * Serbest URL iframe'e gitmez — CSP frame-src de ikinci kilit.
 */
function video_embed_bilgisi(string $url): ?array
{
    $url = trim($url);
    if ($url === '') { return null; }

    /* YouTube: watch?v=ID · youtu.be/ID · shorts/ID · embed/ID
       Sınırlayıcı ~ ÇÜNKÜ desenin içinde # var ([^#]*): # sınırlayıcı
       olsaydı desen orada biterdi — bu tam olarak yaşandı ve YouTube
       bağlantıları sessizce çözülemedi. */
    if (preg_match('~^https?://(?:www\.|m\.)?(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~', $url, $m)) {
        return ['tur' => 'youtube',
                'kaynak' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0'];
    }
    /* Vimeo: vimeo.com/123456789 */
    if (preg_match('#^https?://(?:www\.)?vimeo\.com/(\d{6,12})#', $url, $m)) {
        return ['tur' => 'vimeo',
                'kaynak' => 'https://player.vimeo.com/video/' . $m[1]];
    }
    /* Yerel dosya: göreli yol, gezinme yok, yalnız mp4/webm, var olmalı */
    if (preg_match('#^[a-z0-9_/.-]+\.(mp4|webm)$#i', $url)
        && !str_contains($url, '..') && $url[0] !== '/'
        && is_file(APP_DIR . '/' . $url)) {
        return ['tur' => 'dosya', 'kaynak' => $url];
    }
    return null;
}

/* -----------------------------------------------------------------
   KONTRAST KORUMASI
   -----------------------------------------------------------------
   Görünüm panelinde renk seçici serbest: eğitmen #000000 seçebilir.
   Ölçüldü — o durumda türevler #212121/#141414 çıkıyor ve site zemini
   #0c0a09 olduğu için marka yazısı, bağlantılar ve dolu düğmeler
   okunamaz hale geliyordu. Renk sessizce kabul edilip site bozulmasın:
   aydınlık ekseni WCAG AA eşiğine (4,5:1) çıkana dek yükseltilir ve
   düzeltme kullanıcıya AÇIKÇA bildirilir.
   ----------------------------------------------------------------- */

/** WCAG bağıl parlaklık (0-1). */
function bagil_parlaklik(string $hex): float
{
    $kanal = function (int $s): float {
        $o = $s / 255;
        return $o <= 0.03928 ? $o / 12.92 : (($o + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * $kanal((int)hexdec(substr($hex, 1, 2)))
         + 0.7152 * $kanal((int)hexdec(substr($hex, 3, 2)))
         + 0.0722 * $kanal((int)hexdec(substr($hex, 5, 2)));
}

/** İki renk arasındaki WCAG kontrast oranı (1:1 – 21:1). */
function kontrast_orani(string $a, string $b): float
{
    $x = bagil_parlaklik($a);
    $y = bagil_parlaklik($b);
    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}

const TEMA_ZEMIN = '#0c0a09';      // body.tanitim arka planı
const TEMA_KONTRAST_ESIK = 4.5;    // WCAG AA, normal metin

/**
 * Vurgu rengini koyu zeminde okunur olana dek açar.
 * @return array{renk:string, duzeltildi:bool, oran:float}
 */
function tema_okunur_vurgu(string $hex): array
{
    $oran = kontrast_orani($hex, TEMA_ZEMIN);
    if ($oran >= TEMA_KONTRAST_ESIK) {
        return ['renk' => $hex, 'duzeltildi' => false, 'oran' => $oran];
    }
    /* Aydınlığı adım adım yükselt; ton ve doygunluk KORUNUR ki eğitmenin
       seçtiği renk kimliği kaybolmasın — yalnız okunur hale gelsin. */
    $son = $hex;
    for ($adim = 1; $adim <= 60; $adim++) {
        $son = tema_ton($hex, $adim * 0.015);
        if (kontrast_orani($son, TEMA_ZEMIN) >= TEMA_KONTRAST_ESIK) { break; }
    }
    return ['renk' => $son, 'duzeltildi' => true, 'oran' => kontrast_orani($son, TEMA_ZEMIN)];
}
