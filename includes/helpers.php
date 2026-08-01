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
