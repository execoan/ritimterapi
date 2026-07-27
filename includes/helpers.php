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

const GUNLER = [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe',
                5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'];
const GUNLER_KISA = [1 => 'Pzt', 2 => 'Sal', 3 => 'Çar', 4 => 'Per', 5 => 'Cum', 6 => 'Cmt', 7 => 'Paz'];
const AYLAR_KISA = [1 => 'Oca', 2 => 'Şub', 3 => 'Mar', 4 => 'Nis', 5 => 'May', 6 => 'Haz',
                    7 => 'Tem', 8 => 'Ağu', 9 => 'Eyl', 10 => 'Eki', 11 => 'Kas', 12 => 'Ara'];

/** 'YYYY-MM-DD' → '12 Oca 2026 Pzt' (geçersizse olduğu gibi döner). */
function format_date_tr(?string $ymd, bool $gunAdi = true): string
{
    if (!$ymd) { return '—'; }
    $d = DateTime::createFromFormat('Y-m-d', substr($ymd, 0, 10));
    if (!$d) { return $ymd; }
    $s = (int)$d->format('j') . ' ' . AYLAR_KISA[(int)$d->format('n')] . ' ' . $d->format('Y');
    return $gunAdi ? $s . ' ' . GUNLER_KISA[(int)$d->format('N')] : $s;
}

/** Verilen tarihin (varsayılan bugün) Pazartesi–Pazar sınırları: ['YYYY-MM-DD', 'YYYY-MM-DD']. */
function week_bounds(?string $ymd = null): array
{
    $d = $ymd ? DateTime::createFromFormat('Y-m-d', $ymd) : now();
    if (!$d) { $d = now(); }
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
const PROTOKOL_LABELS = ['vurus_tutturma' => 'Vuruş Tutturma', 'bpm_bulma' => 'BPM Bulma',
                         'ritim_okuma' => 'Ritim Okuma'];
const EV_TUR_LABELS = ['serbest' => 'Serbest (işaretlemeli)', 'metronom' => 'Metronomlu süre',
                       'vurus_tutturma' => 'Vuruş Tutturma (mini)', 'ritim_okuma' => 'Ritim Okuma'];
const KITLE_LABELS = ['cocuk' => 'Çocuk & Genç', 'yetiskin' => 'Yetişkin', 'hepsi' => 'Hepsi'];
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
function locked_language_flags(string $text): array
{
    $t = tr_sort_key($text); // küçük harfe indirger (tr kurallı), sıralama eki zararsız
    $yasakli = [
        'terapi', 'tedavi', 'tanı', 'hasta', 'danışan', 'iyileş', 'semptom',
        'dehb', 'dikkat eksikliği', 'hiperakt', 'gelişim geriliği', 'otizm',
        'disleksi', 'klinik', 'rezonans', 'titreşim', 'hücresel', 'geride kal',
        'yaşına göre geride', 'riskli', 'düzelt', 'iyi gel', 'geliştir',
    ];
    $bulunan = [];
    foreach ($yasakli as $kelime) {
        if (mb_strpos($t, tr_sort_key($kelime)) !== false) {
            $bulunan[] = $kelime;
        }
    }
    return $bulunan;
}
