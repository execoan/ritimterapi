<?php
if (!defined('RITIM')) { http_response_code(403); exit; }

/* ======================== GRUPLAR ======================== */

function groups_list(bool $onlyActive = false): array
{
    $sql = 'SELECT g.*,
                   (SELECT COUNT(*) FROM ogrenciler o WHERE o.grup_id = g.id AND o.aktif = 1) AS ogrenci_sayisi,
                   (SELECT COUNT(*) FROM oturumlar s WHERE s.grup_id = g.id)                  AS oturum_sayisi
              FROM gruplar g' . ($onlyActive ? ' WHERE g.aktif = 1' : '');
    $rows = db()->query($sql)->fetchAll();
    tr_sort_by($rows, 'ad');
    return $rows;
}

function group_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM gruplar WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** @return array{ok:bool, error:?string, id:?int} */
function group_save(array $d, ?int $id = null): array
{
    $ad = trim((string)($d['ad'] ?? ''));
    if ($ad === '') { return ['ok' => false, 'error' => 'Grup adı boş olamaz.', 'id' => null]; }
    $gun = (int)($d['gun'] ?? 0);
    if ($gun < 1 || $gun > 7) { return ['ok' => false, 'error' => 'Geçerli bir gün seçin.', 'id' => null]; }
    $saat = trim((string)($d['saat'] ?? ''));
    if ($saat !== '' && !preg_match('/^\d{2}:\d{2}$/', $saat)) {
        return ['ok' => false, 'error' => 'Saat SS:DD biçiminde olmalı (örn. 17:30).', 'id' => null];
    }
    $baslangic = trim((string)($d['baslangic_tarihi'] ?? ''));
    if ($baslangic !== '' && !DateTime::createFromFormat('Y-m-d', $baslangic)) {
        return ['ok' => false, 'error' => 'Başlangıç tarihi geçersiz.', 'id' => null];
    }
    $vals = [$ad, trim((string)($d['yas_araligi'] ?? '')), $gun, $saat,
             isset($d['aktif']) ? (int)!!$d['aktif'] : 1, $baslangic];
    if ($id === null) {
        $vals[] = now_str();
        db()->prepare('INSERT INTO gruplar (ad, yas_araligi, gun, saat, aktif, baslangic_tarihi, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?)')->execute($vals);
        return ['ok' => true, 'error' => null, 'id' => (int)db()->lastInsertId()];
    }
    $vals[] = $id;
    db()->prepare('UPDATE gruplar SET ad = ?, yas_araligi = ?, gun = ?, saat = ?, aktif = ?, baslangic_tarihi = ?
                   WHERE id = ?')->execute($vals);
    return ['ok' => true, 'error' => null, 'id' => $id];
}

/** Grubu ve (CASCADE ile) oturum geçmişini siler; öğrenciler grupsuz kalır. */
function group_delete(int $id): void
{
    db()->prepare('DELETE FROM gruplar WHERE id = ?')->execute([$id]);
}

/** Grubun ders gününe göre bugünden itibaren ilk uygun tarih. */
function group_next_date(array $grup): string
{
    $hedefGun = (int)$grup['gun'];
    $d = now();
    $fark = ($hedefGun - (int)$d->format('N') + 7) % 7;
    return $d->modify('+' . $fark . ' days')->format('Y-m-d');
}

/* ======================== ÖĞRENCİLER ======================== */

function students_list(?int $grupId = null, ?int $aktif = null): array
{
    $kosul = [];
    $par = [];
    if ($grupId !== null) { $kosul[] = 'o.grup_id = ?'; $par[] = $grupId; }
    if ($aktif !== null)  { $kosul[] = 'o.aktif = ?';   $par[] = $aktif; }
    $sql = 'SELECT o.*, g.ad AS grup_ad
              FROM ogrenciler o LEFT JOIN gruplar g ON g.id = o.grup_id'
         . ($kosul ? ' WHERE ' . implode(' AND ', $kosul) : '');
    $st = db()->prepare($sql);
    $st->execute($par);
    $rows = $st->fetchAll();
    tr_sort_by($rows, 'kod');
    return $rows;
}

function student_get(int $id): ?array
{
    $st = db()->prepare('SELECT o.*, g.ad AS grup_ad FROM ogrenciler o
                         LEFT JOIN gruplar g ON g.id = o.grup_id WHERE o.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** @return array{ok:bool, error:?string, id:?int} */
function student_save(array $d, ?int $id = null): array
{
    $kod = trim((string)($d['kod'] ?? ''));
    if ($kod === '') { return ['ok' => false, 'error' => 'Öğrenci kodu boş olamaz.', 'id' => null]; }
    $st = db()->prepare('SELECT id FROM ogrenciler WHERE kod = ? AND id != ?');
    $st->execute([$kod, (int)$id]);
    if ($st->fetch()) { return ['ok' => false, 'error' => 'Bu kod başka bir öğrencide kayıtlı: ' . $kod, 'id' => null]; }

    $dogum = trim((string)($d['dogum_yili'] ?? ''));
    $dogumYili = null;
    if ($dogum !== '') {
        $dogumYili = (int)$dogum;
        $buYil = (int)now()->format('Y');
        if ($dogumYili < 1920 || $dogumYili > $buYil) {
            return ['ok' => false, 'error' => 'Doğum yılı geçersiz.', 'id' => null];
        }
    }
    $grupId = (int)($d['grup_id'] ?? 0) ?: null;
    if ($grupId !== null && !group_get($grupId)) {
        return ['ok' => false, 'error' => 'Seçilen grup bulunamadı.', 'id' => null];
    }
    $vals = [$kod, $dogumYili, $grupId, trim((string)($d['veli_notu'] ?? '')),
             isset($d['aktif']) ? (int)!!$d['aktif'] : 1];
    if ($id === null) {
        $vals[] = today();
        db()->prepare('INSERT INTO ogrenciler (kod, dogum_yili, grup_id, veli_notu, aktif, kayit_tarihi)
                       VALUES (?, ?, ?, ?, ?, ?)')->execute($vals);
        return ['ok' => true, 'error' => null, 'id' => (int)db()->lastInsertId()];
    }
    $vals[] = $id;
    db()->prepare('UPDATE ogrenciler SET kod = ?, dogum_yili = ?, grup_id = ?, veli_notu = ?, aktif = ?
                   WHERE id = ?')->execute($vals);
    return ['ok' => true, 'error' => null, 'id' => $id];
}

function student_delete(int $id): void
{
    db()->prepare('DELETE FROM ogrenciler WHERE id = ?')->execute([$id]);
}

/** Katılım özeti: toplam kayıt, katıldı, geç, gelmedi, oran (yoklaması girilmiş oturumlar üzerinden). */
function student_stats(int $id): array
{
    $st = db()->prepare("SELECT
            COUNT(*)                                        AS toplam,
            SUM(CASE WHEN durum = 'katildi' THEN 1 ELSE 0 END) AS katildi,
            SUM(CASE WHEN durum = 'gec'     THEN 1 ELSE 0 END) AS gec,
            SUM(CASE WHEN durum = 'gelmedi' THEN 1 ELSE 0 END) AS gelmedi
        FROM katilim WHERE ogrenci_id = ?");
    $st->execute([$id]);
    $r = $st->fetch() ?: ['toplam' => 0, 'katildi' => 0, 'gec' => 0, 'gelmedi' => 0];
    $r = array_map('intval', $r);
    $r['oran'] = $r['toplam'] > 0 ? (int)round(100 * ($r['katildi'] + $r['gec']) / $r['toplam']) : null;
    return $r;
}

/** Öğrencinin oturum geçmişi (yeniden eskiye): tarih, grup, durum, gözlem notu. */
function student_timeline(int $id): array
{
    $st = db()->prepare('SELECT k.*, s.tarih, s.hafta_no, s.id AS oturum_id, g.ad AS grup_ad
                           FROM katilim k
                           JOIN oturumlar s ON s.id = k.oturum_id
                           JOIN gruplar g   ON g.id = s.grup_id
                          WHERE k.ogrenci_id = ?
                          ORDER BY s.tarih DESC, s.id DESC');
    $st->execute([$id]);
    return $st->fetchAll();
}

/* ======================== TEKNİKLER ======================== */

function techniques_list(array $f = []): array
{
    $kosul = [];
    $par = [];
    if (!empty($f['kategori'])) { $kosul[] = 't.kategori = ?';     $par[] = $f['kategori']; }
    if (!empty($f['seviye']))   { $kosul[] = 't.seviye = ?';       $par[] = (int)$f['seviye']; }
    if (!empty($f['kanit']))    { $kosul[] = 't.kanit_duzeyi = ?'; $par[] = $f['kanit']; }
    if (isset($f['aktif']) && $f['aktif'] !== '') { $kosul[] = 't.aktif = ?'; $par[] = (int)$f['aktif']; }
    if (!empty($f['q'])) {
        $kosul[] = '(t.ad LIKE ? OR t.hedef_beceri LIKE ? OR t.aciklama LIKE ?)';
        $q = '%' . $f['q'] . '%';
        array_push($par, $q, $q, $q);
    }
    $sql = 'SELECT t.*,
                   (SELECT COUNT(*) FROM oturum_teknikleri ot WHERE ot.teknik_id = t.id AND ot.islendi = 1) AS islenme_sayisi,
                   (SELECT COUNT(*) FROM teknik_calismalari tc WHERE tc.teknik_id = t.id)                   AS calisma_sayisi
              FROM teknikler t' . ($kosul ? ' WHERE ' . implode(' AND ', $kosul) : '');
    $st = db()->prepare($sql);
    $st->execute($par);
    $rows = $st->fetchAll();
    usort($rows, fn($a, $b) =>
        strcmp(tr_sort_key($a['kategori']), tr_sort_key($b['kategori']))
        ?: strcmp(tr_sort_key($a['ad']), tr_sort_key($b['ad'])));
    return $rows;
}

function technique_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM teknikler WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function technique_categories(): array
{
    $rows = db()->query('SELECT DISTINCT kategori FROM teknikler')->fetchAll(PDO::FETCH_COLUMN);
    usort($rows, fn($a, $b) => strcmp(tr_sort_key($a), tr_sort_key($b)));
    return $rows;
}

/** @return array{ok:bool, error:?string, id:?int} */
function technique_save(array $d, ?int $id = null): array
{
    $ad = trim((string)($d['ad'] ?? ''));
    if ($ad === '') { return ['ok' => false, 'error' => 'Teknik adı boş olamaz.', 'id' => null]; }
    $st = db()->prepare('SELECT id FROM teknikler WHERE ad = ? AND id != ?');
    $st->execute([$ad, (int)$id]);
    if ($st->fetch()) { return ['ok' => false, 'error' => 'Bu adla kayıtlı bir teknik zaten var.', 'id' => null]; }

    $kategori = trim((string)($d['kategori'] ?? ''));
    if ($kategori === '') { return ['ok' => false, 'error' => 'Kategori boş olamaz.', 'id' => null]; }

    // Kanıt düzeyi ZORUNLUDUR — etiket girilmeden kayıt kabul edilmez (CLAUDE.md §4).
    $kanit = (string)($d['kanit_duzeyi'] ?? '');
    if (!isset(KANIT_LABELS[$kanit])) {
        return ['ok' => false, 'error' => 'Kanıt düzeyi seçilmeden teknik kaydedilemez. "Kanıt yok" da geçerli ve meşru bir seçenektir.', 'id' => null];
    }
    $seviye = (int)($d['seviye'] ?? 1);
    if ($seviye < 1 || $seviye > 3) { $seviye = 1; }
    $sure = max(1, min(120, (int)($d['sure_dk'] ?? 10)));

    $vals = [$ad, $kategori, trim((string)($d['enstruman'] ?? '')), $seviye, $sure,
             trim((string)($d['aciklama'] ?? '')), trim((string)($d['hedef_beceri'] ?? '')),
             $kanit, trim((string)($d['kaynak'] ?? '')), trim((string)($d['malzeme'] ?? '')),
             isset($d['aktif']) ? (int)!!$d['aktif'] : 1];
    if ($id === null) {
        $vals[] = now_str();
        db()->prepare('INSERT INTO teknikler
                (ad, kategori, enstruman, seviye, sure_dk, aciklama, hedef_beceri, kanit_duzeyi, kaynak, malzeme, aktif, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute($vals);
        return ['ok' => true, 'error' => null, 'id' => (int)db()->lastInsertId()];
    }
    $vals[] = $id;
    db()->prepare('UPDATE teknikler SET ad = ?, kategori = ?, enstruman = ?, seviye = ?, sure_dk = ?,
                aciklama = ?, hedef_beceri = ?, kanit_duzeyi = ?, kaynak = ?, malzeme = ?, aktif = ?
             WHERE id = ?')->execute($vals);
    return ['ok' => true, 'error' => null, 'id' => $id];
}

/** Oturumlarda kullanılmış teknik silinemez (geçmiş bozulur) — pasife alınır. */
function technique_delete(int $id): array
{
    $st = db()->prepare('SELECT COUNT(*) FROM oturum_teknikleri WHERE teknik_id = ?');
    $st->execute([$id]);
    if ((int)$st->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'Bu teknik oturum kayıtlarında kullanılmış; silmek geçmişi bozar. Bunun yerine pasife alın.'];
    }
    db()->prepare('DELETE FROM teknikler WHERE id = ?')->execute([$id]);
    return ['ok' => true, 'error' => null];
}

/* ======================== OTURUMLAR ======================== */

/** Oturum listesi; her satırda grup adı, teknik sayısı, planlanan süre ve yoklama durumu. */
function sessions_list(?int $grupId = null, ?string $fromDate = null, ?string $toDate = null): array
{
    $kosul = [];
    $par = [];
    if ($grupId !== null)  { $kosul[] = 's.grup_id = ?'; $par[] = $grupId; }
    if ($fromDate !== null) { $kosul[] = 's.tarih >= ?'; $par[] = $fromDate; }
    if ($toDate !== null)   { $kosul[] = 's.tarih <= ?'; $par[] = $toDate; }
    $sql = 'SELECT s.*, g.ad AS grup_ad, g.saat AS grup_saat,
                   (SELECT COUNT(*) FROM oturum_teknikleri ot WHERE ot.oturum_id = s.id)   AS teknik_sayisi,
                   (SELECT COALESCE(SUM(ot.sure_dk), 0) FROM oturum_teknikleri ot WHERE ot.oturum_id = s.id) AS plan_sure,
                   (SELECT COUNT(*) FROM katilim k WHERE k.oturum_id = s.id)               AS yoklama_sayisi,
                   (SELECT COUNT(*) FROM katilim k WHERE k.oturum_id = s.id AND k.durum IN (\'katildi\',\'gec\')) AS gelen_sayisi
              FROM oturumlar s JOIN gruplar g ON g.id = s.grup_id'
         . ($kosul ? ' WHERE ' . implode(' AND ', $kosul) : '')
         . ' ORDER BY s.tarih DESC, s.id DESC';
    $st = db()->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

function session_get(int $id): ?array
{
    $st = db()->prepare('SELECT s.*, g.ad AS grup_ad, g.saat AS grup_saat, g.baslangic_tarihi AS grup_baslangic
                           FROM oturumlar s JOIN gruplar g ON g.id = s.grup_id WHERE s.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Oturumun planlı teknikleri (sıralı), teknik bilgileriyle. */
function session_techniques(int $id): array
{
    $st = db()->prepare('SELECT ot.*, t.ad, t.kategori, t.kanit_duzeyi, t.seviye, t.malzeme
                           FROM oturum_teknikleri ot JOIN teknikler t ON t.id = ot.teknik_id
                          WHERE ot.oturum_id = ? ORDER BY ot.sira, t.ad');
    $st->execute([$id]);
    return $st->fetchAll();
}

/** Yoklama kayıtları: [ogrenci_id => satır]. */
function session_attendance(int $id): array
{
    $st = db()->prepare('SELECT * FROM katilim WHERE oturum_id = ?');
    $st->execute([$id]);
    $sonuc = [];
    foreach ($st->fetchAll() as $r) { $sonuc[(int)$r['ogrenci_id']] = $r; }
    return $sonuc;
}

/**
 * Oturum planını kaydeder (yeni veya güncelleme).
 * $items: [['teknik_id'=>, 'sure_dk'=>, 'uygulama_notu'=>], ...] — sıra dizideki sıradır.
 * Güncellemede korunan tekniklerin "işlendi" bilgisi kaybolmaz.
 * @return array{ok:bool, error:?string, id:?int}
 */
function session_save_plan(array $d, array $items, ?int $id = null): array
{
    $grupId = (int)($d['grup_id'] ?? 0);
    if (!group_get($grupId)) { return ['ok' => false, 'error' => 'Grup bulunamadı.', 'id' => null]; }
    $tarih = trim((string)($d['tarih'] ?? ''));
    if (!DateTime::createFromFormat('Y-m-d', $tarih)) {
        return ['ok' => false, 'error' => 'Geçerli bir tarih seçin.', 'id' => null];
    }
    $haftaNo = (int)($d['hafta_no'] ?? 0);
    if ($haftaNo === 0) {
        $grup = group_get($grupId);
        $haftaNo = week_no_for($grup['baslangic_tarihi'] ?: null, $tarih);
    }
    $temiz = [];
    $gorulen = [];
    foreach ($items as $it) {
        $tid = (int)($it['teknik_id'] ?? 0);
        if ($tid <= 0 || isset($gorulen[$tid]) || !technique_get($tid)) { continue; }
        $gorulen[$tid] = true;
        $temiz[] = ['teknik_id' => $tid,
                    'sure_dk' => max(1, min(120, (int)($it['sure_dk'] ?? 10))),
                    'uygulama_notu' => trim((string)($it['uygulama_notu'] ?? ''))];
    }
    if (!$temiz) { return ['ok' => false, 'error' => 'Plana en az bir teknik ekleyin.', 'id' => null]; }

    $protokol = (string)($d['protokol'] ?? '');
    if (!isset(PROTOKOL_LABELS[$protokol])) { $protokol = ''; }

    $pdo = db();
    $pdo->exec('BEGIN');
    try {
        if ($id === null) {
            $pdo->prepare('INSERT INTO oturumlar (grup_id, tarih, hafta_no, notlar, protokol, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$grupId, $tarih, $haftaNo, trim((string)($d['notlar'] ?? '')), $protokol, now_str()]);
            $id = (int)$pdo->lastInsertId();
            $eskiIslendi = [];
        } else {
            $pdo->prepare('UPDATE oturumlar SET grup_id = ?, tarih = ?, hafta_no = ?, protokol = ? WHERE id = ?')
                ->execute([$grupId, $tarih, $haftaNo, $protokol, $id]);
            $st = $pdo->prepare('SELECT teknik_id, islendi FROM oturum_teknikleri WHERE oturum_id = ?');
            $st->execute([$id]);
            $eskiIslendi = $st->fetchAll(PDO::FETCH_KEY_PAIR);
            $pdo->prepare('DELETE FROM oturum_teknikleri WHERE oturum_id = ?')->execute([$id]);
        }
        $ins = $pdo->prepare('INSERT INTO oturum_teknikleri (oturum_id, teknik_id, sira, sure_dk, uygulama_notu, islendi)
                              VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($temiz as $i => $it) {
            $ins->execute([$id, $it['teknik_id'], $i + 1, $it['sure_dk'], $it['uygulama_notu'],
                           $eskiIslendi[$it['teknik_id']] ?? null]);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }
    return ['ok' => true, 'error' => null, 'id' => $id];
}

/**
 * Oturum kaydı: yoklama + işlenen teknikler + notlar tek işlemde yazılır.
 * $yoklama: [ogrenci_id => ['durum'=>, 'gozlem_notu'=>]]
 * $teknikKayit: [teknik_id => ['islendi'=>0|1, 'uygulama_notu'=>]]
 */
function session_record(int $id, array $yoklama, array $teknikKayit, string $oturumNotu): void
{
    $pdo = db();
    $pdo->exec('BEGIN');
    try {
        $pdo->prepare('UPDATE oturumlar SET notlar = ? WHERE id = ?')->execute([trim($oturumNotu), $id]);

        $up = $pdo->prepare('INSERT INTO katilim (oturum_id, ogrenci_id, durum, gozlem_notu) VALUES (?, ?, ?, ?)
                             ON CONFLICT(oturum_id, ogrenci_id) DO UPDATE SET durum = excluded.durum, gozlem_notu = excluded.gozlem_notu');
        foreach ($yoklama as $ogrenciId => $k) {
            $durum = (string)($k['durum'] ?? '');
            if (!isset(KATILIM_LABELS[$durum])) { continue; }
            $up->execute([$id, (int)$ogrenciId, $durum, trim((string)($k['gozlem_notu'] ?? ''))]);
        }

        $ut = $pdo->prepare('UPDATE oturum_teknikleri SET islendi = ?, uygulama_notu = ? WHERE oturum_id = ? AND teknik_id = ?');
        foreach ($teknikKayit as $teknikId => $t) {
            $ut->execute([(int)!!($t['islendi'] ?? 0), trim((string)($t['uygulama_notu'] ?? '')), $id, (int)$teknikId]);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }
}

function session_delete(int $id): void
{
    db()->prepare('DELETE FROM oturumlar WHERE id = ?')->execute([$id]);
}

/** Tarihi geçmiş/bugün olup yoklaması hiç girilmemiş oturumlar (bekleyen yoklama). */
function pending_sessions(): array
{
    $st = db()->prepare('SELECT s.*, g.ad AS grup_ad, g.saat AS grup_saat,
                   (SELECT COUNT(*) FROM oturum_teknikleri ot WHERE ot.oturum_id = s.id) AS teknik_sayisi
              FROM oturumlar s JOIN gruplar g ON g.id = s.grup_id
             WHERE s.tarih <= ? AND NOT EXISTS (SELECT 1 FROM katilim k WHERE k.oturum_id = s.id)
             ORDER BY s.tarih ASC');
    $st->execute([today()]);
    return $st->fetchAll();
}

/* ======================== AKADEMİK ÇALIŞMALAR ======================== */

function studies_list(): array
{
    $rows = db()->query('SELECT c.*,
                (SELECT COUNT(*) FROM teknik_calismalari tc WHERE tc.calisma_id = c.id) AS bagli_teknik
           FROM akademik_calismalar c ORDER BY c.yil DESC, c.yazarlar')->fetchAll();
    return $rows;
}

function study_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM akademik_calismalar WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** @return array{ok:bool, error:?string, id:?int} */
function study_save(array $d, ?int $id = null): array
{
    $baslik = trim((string)($d['baslik'] ?? ''));
    if ($baslik === '') { return ['ok' => false, 'error' => 'Çalışma başlığı boş olamaz.', 'id' => null]; }
    $yil = trim((string)($d['yil'] ?? ''));
    $tur = isset(CALISMA_TUR_LABELS[(string)($d['tur'] ?? '')]) ? $d['tur'] : 'diger';
    $vals = [$baslik, trim((string)($d['yazarlar'] ?? '')), $yil === '' ? null : (int)$yil,
             trim((string)($d['dergi'] ?? '')), doi_normalize((string)($d['doi'] ?? '')),
             $tur, trim((string)($d['ozet'] ?? ''))];
    if ($id === null) {
        $vals[] = now_str();
        db()->prepare('INSERT INTO akademik_calismalar (baslik, yazarlar, yil, dergi, doi, tur, ozet, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute($vals);
        return ['ok' => true, 'error' => null, 'id' => (int)db()->lastInsertId()];
    }
    $vals[] = $id;
    db()->prepare('UPDATE akademik_calismalar SET baslik = ?, yazarlar = ?, yil = ?, dergi = ?, doi = ?, tur = ?, ozet = ?
                   WHERE id = ?')->execute($vals);
    return ['ok' => true, 'error' => null, 'id' => $id];
}

function study_delete(int $id): void
{
    db()->prepare('DELETE FROM akademik_calismalar WHERE id = ?')->execute([$id]);
}

/** Kısa künye: "Yazarlar (Yıl). Dergi." */
function study_citation(array $c): string
{
    $parcalar = [];
    if ($c['yazarlar'] !== '') { $parcalar[] = $c['yazarlar']; }
    if ($c['yil']) { $parcalar[] = '(' . (int)$c['yil'] . ')'; }
    $kunye = implode(' ', $parcalar);
    if ($c['dergi'] !== '') { $kunye .= ($kunye ? '. ' : '') . $c['dergi']; }
    return $kunye;
}

/** Tekniğe bağlı çalışmalar (ilişki notuyla). */
function technique_studies(int $teknikId): array
{
    $st = db()->prepare('SELECT c.*, tc.iliski_notu FROM teknik_calismalari tc
                           JOIN akademik_calismalar c ON c.id = tc.calisma_id
                          WHERE tc.teknik_id = ? ORDER BY c.yil DESC');
    $st->execute([$teknikId]);
    return $st->fetchAll();
}

/** Tekniğe henüz bağlı olmayan çalışmalar (bağlama formu için). */
function studies_not_linked(int $teknikId): array
{
    $st = db()->prepare('SELECT * FROM akademik_calismalar
                          WHERE id NOT IN (SELECT calisma_id FROM teknik_calismalari WHERE teknik_id = ?)
                          ORDER BY yil DESC, yazarlar');
    $st->execute([$teknikId]);
    return $st->fetchAll();
}

function technique_study_link(int $teknikId, int $calismaId, string $not): bool
{
    if (!technique_get($teknikId) || !study_get($calismaId)) { return false; }
    db()->prepare('INSERT OR REPLACE INTO teknik_calismalari (teknik_id, calisma_id, iliski_notu) VALUES (?, ?, ?)')
        ->execute([$teknikId, $calismaId, trim($not)]);
    return true;
}

function technique_study_unlink(int $teknikId, int $calismaId): void
{
    db()->prepare('DELETE FROM teknik_calismalari WHERE teknik_id = ? AND calisma_id = ?')
        ->execute([$teknikId, $calismaId]);
}

/* ======================== SİTE İÇERİĞİ (CMS) ======================== */

/** Tüm site metinleri: [anahtar => değer]. */
function site_texts(): array
{
    static $onbellek = null;
    if ($onbellek === null) {
        $onbellek = db()->query('SELECT anahtar, deger FROM site_icerik')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $onbellek;
}

function site_text(string $anahtar, string $varsayilan = ''): string
{
    $m = site_texts();
    $deger = trim((string)($m[$anahtar] ?? ''));
    return $deger !== '' ? $deger : $varsayilan;
}

function site_text_set(string $anahtar, string $deger): void
{
    db()->prepare('INSERT INTO site_icerik (anahtar, deger) VALUES (?, ?)
                   ON CONFLICT(anahtar) DO UPDATE SET deger = excluded.deger')
        ->execute([$anahtar, trim($deger)]);
}

/** Bölümler sıra ile (yönetim için tümü). */
function site_sections(bool $onlyVisible = false): array
{
    $sql = 'SELECT * FROM site_bolumleri' . ($onlyVisible ? ' WHERE gorunur = 1' : '') . ' ORDER BY sira, id';
    return db()->query($sql)->fetchAll();
}

/** Sıralamayı ve görünürlüğü kaydet. $sira: anahtar dizisi (yeni sıra); $gorunur: [anahtar => 1]. */
function site_sections_save(array $sira, array $gorunur, array $basliklar): void
{
    $pdo = db();
    $pdo->exec('BEGIN');
    try {
        $st = $pdo->prepare('UPDATE site_bolumleri SET sira = ?, gorunur = ?, baslik = ? WHERE anahtar = ?');
        foreach (array_values($sira) as $i => $anahtar) {
            $baslik = trim((string)($basliklar[$anahtar] ?? ''));
            if ($baslik === '') { continue; }
            $st->execute([$i + 1, isset($gorunur[$anahtar]) ? 1 : 0, $baslik, $anahtar]);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }
}

/* ---------- Bilim kartları (site_makaleler) ---------- */

function site_articles(bool $onlyVisible = false): array
{
    $sql = 'SELECT * FROM site_makaleler' . ($onlyVisible ? ' WHERE gorunur = 1' : '') . ' ORDER BY sira, id';
    return db()->query($sql)->fetchAll();
}

function site_article_add(): int
{
    db()->exec("INSERT INTO site_makaleler (baslik, kunye, bulgu, yansima, rozet, sira, gorunur)
                VALUES ('Yeni makale', '', '', '', 'orta',
                        (SELECT COALESCE(MAX(sira), 0) + 1 FROM site_makaleler), 1)");
    return (int)db()->lastInsertId();
}

/** $sira: id dizisi; $alanlar: [id => [baslik, kunye, bulgu, yansima, rozet]]; $gorunur: [id => 1]. */
function site_articles_save(array $sira, array $alanlar, array $gorunur): void
{
    $pdo = db();
    $pdo->exec('BEGIN');
    try {
        $st = $pdo->prepare('UPDATE site_makaleler SET sira = ?, baslik = ?, kunye = ?, bulgu = ?, yansima = ?, rozet = ?, gorunur = ? WHERE id = ?');
        foreach (array_values($sira) as $i => $id) {
            $id = (int)$id;
            $a = $alanlar[$id] ?? null;
            if (!$a) { continue; }
            $rozet = in_array((string)($a['rozet'] ?? ''), ['guclu', 'orta', 'zayif', 'yok'], true) ? $a['rozet'] : 'orta';
            $st->execute([$i + 1, trim((string)($a['baslik'] ?? '')), trim((string)($a['kunye'] ?? '')),
                          trim((string)($a['bulgu'] ?? '')), trim((string)($a['yansima'] ?? '')),
                          $rozet, isset($gorunur[$id]) ? 1 : 0, $id]);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }
}

function site_article_delete(int $id): void
{
    db()->prepare('DELETE FROM site_makaleler WHERE id = ?')->execute([$id]);
}

/* ---------- Foto galerisi (site_galeri) ---------- */

function gallery_list(bool $onlyVisible = false): array
{
    $sql = 'SELECT * FROM site_galeri' . ($onlyVisible ? ' WHERE gorunur = 1' : '') . ' ORDER BY sira, id';
    return db()->query($sql)->fetchAll();
}

/** Yüklenen görseli doğrular, assets/img/galeri/ altına kaydeder. */
function gallery_add(array $file, string $baslik): array
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Dosya yüklenemedi.'];
    }
    if ((int)$file['size'] > 4 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Görsel en fazla 4 MB olabilir.'];
    }
    $bilgi = @getimagesize($file['tmp_name']);
    $mimeUzanti = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = $bilgi['mime'] ?? '';
    if (!$bilgi || !isset($mimeUzanti[$mime])) {
        return ['ok' => false, 'error' => 'Yalnız JPEG, PNG, WebP veya GIF yükleyebilirsiniz.'];
    }
    $dizin = APP_DIR . '/assets/img/galeri';
    if (!is_dir($dizin)) { @mkdir($dizin, 0755, true); }
    $ad = 'galeri-' . bin2hex(random_bytes(6)) . '.' . $mimeUzanti[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dizin . '/' . $ad)) {
        return ['ok' => false, 'error' => 'Görsel kaydedilemedi (klasör izni?).'];
    }
    db()->prepare('INSERT INTO site_galeri (dosya, baslik, sira, gorunur)
                   VALUES (?, ?, (SELECT COALESCE(MAX(sira), 0) + 1 FROM site_galeri), 1)')
        ->execute([$ad, trim($baslik)]);
    return ['ok' => true, 'error' => null];
}

function gallery_save(array $sira, array $basliklar, array $gorunur): void
{
    $pdo = db();
    $pdo->exec('BEGIN');
    try {
        $st = $pdo->prepare('UPDATE site_galeri SET sira = ?, baslik = ?, gorunur = ? WHERE id = ?');
        foreach (array_values($sira) as $i => $id) {
            $id = (int)$id;
            $st->execute([$i + 1, trim((string)($basliklar[$id] ?? '')), isset($gorunur[$id]) ? 1 : 0, $id]);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }
}

function gallery_delete(int $id): void
{
    $st = db()->prepare('SELECT dosya FROM site_galeri WHERE id = ?');
    $st->execute([$id]);
    $dosya = $st->fetchColumn();
    if ($dosya) {
        @unlink(APP_DIR . '/assets/img/galeri/' . basename((string)$dosya));
    }
    db()->prepare('DELETE FROM site_galeri WHERE id = ?')->execute([$id]);
}

/* ======================== PLAN ŞABLONLARI ======================== */

function templates_list(): array
{
    return db()->query('SELECT s.*,
                (SELECT COUNT(*) FROM sablon_oturumlari o WHERE o.sablon_id = s.id) AS oturum_sayisi
           FROM plan_sablonlari s ORDER BY s.id')->fetchAll();
}

function template_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM plan_sablonlari WHERE id = ?');
    $st->execute([$id]);
    $sablon = $st->fetch();
    if (!$sablon) { return null; }
    $st = db()->prepare('SELECT o.*,
                (SELECT COALESCE(SUM(t.sure_dk), 0) FROM sablon_teknikleri t WHERE t.sablon_oturum_id = o.id) AS toplam_sure
           FROM sablon_oturumlari o WHERE o.sablon_id = ? ORDER BY o.hafta_no, o.oturum_adi');
    $st->execute([$id]);
    $sablon['oturumlar'] = $st->fetchAll();
    $tst = db()->prepare('SELECT st.*, tk.ad, tk.kategori, tk.kanit_duzeyi
                            FROM sablon_teknikleri st JOIN teknikler tk ON tk.id = st.teknik_id
                           WHERE st.sablon_oturum_id = ? ORDER BY st.sira');
    foreach ($sablon['oturumlar'] as &$o) {
        $tst->execute([(int)$o['id']]);
        $o['teknikler'] = $tst->fetchAll();
    }
    return $sablon;
}

/** @return array{ok:bool, error:?string, id:?int} */
function template_save(array $d, ?int $id = null): array
{
    $ad = trim((string)($d['ad'] ?? ''));
    if ($ad === '') { return ['ok' => false, 'error' => 'Şablon adı boş olamaz.', 'id' => null]; }
    $kitle = in_array((string)($d['hedef_kitle'] ?? ''), ['cocuk', 'yetiskin'], true) ? $d['hedef_kitle'] : 'cocuk';
    $sure = max(10, min(180, (int)($d['sure_dk'] ?? 60)));
    $vals = [$ad, trim((string)($d['aciklama'] ?? '')), $kitle, $sure];
    if ($id === null) {
        $vals[] = now_str();
        db()->prepare('INSERT INTO plan_sablonlari (ad, aciklama, hedef_kitle, sure_dk, created_at)
                       VALUES (?, ?, ?, ?, ?)')->execute($vals);
        return ['ok' => true, 'error' => null, 'id' => (int)db()->lastInsertId()];
    }
    $vals[] = $id;
    db()->prepare('UPDATE plan_sablonlari SET ad = ?, aciklama = ?, hedef_kitle = ?, sure_dk = ? WHERE id = ?')
        ->execute($vals);
    return ['ok' => true, 'error' => null, 'id' => $id];
}

function template_delete(int $id): void
{
    db()->prepare('DELETE FROM plan_sablonlari WHERE id = ?')->execute([$id]);
}

/** Tek şablon oturumu (şablon bilgisi ve teknikleriyle). */
function template_session_get(int $id): ?array
{
    $st = db()->prepare('SELECT o.*, s.ad AS sablon_ad, s.sure_dk AS sablon_sure
                           FROM sablon_oturumlari o JOIN plan_sablonlari s ON s.id = o.sablon_id
                          WHERE o.id = ?');
    $st->execute([$id]);
    $oturum = $st->fetch();
    if (!$oturum) { return null; }
    $st = db()->prepare('SELECT st.*, tk.ad, tk.kategori, tk.kanit_duzeyi
                           FROM sablon_teknikleri st JOIN teknikler tk ON tk.id = st.teknik_id
                          WHERE st.sablon_oturum_id = ? ORDER BY st.sira');
    $st->execute([$id]);
    $oturum['teknikler'] = $st->fetchAll();
    return $oturum;
}

/** Boş şablon oturumu ekler, id döner. */
function template_session_add(int $sablonId, int $haftaNo, string $oturumAdi): int
{
    db()->prepare('INSERT INTO sablon_oturumlari (sablon_id, hafta_no, oturum_adi, hedef, protokol) VALUES (?, ?, ?, ?, ?)')
        ->execute([$sablonId, max(1, min(52, $haftaNo)), mb_substr(trim($oturumAdi) ?: 'A', 0, 3), '', '']);
    return (int)db()->lastInsertId();
}

/**
 * Şablon oturumunu (hafta/ad/hedef + teknik planı) kaydeder.
 * $items: [['teknik_id','sure_dk','uygulama_notu'], …] — sıra dizideki sıradır.
 * @return array{ok:bool, error:?string}
 */
function template_session_save(int $id, array $d, array $items): array
{
    if (!template_session_get($id)) { return ['ok' => false, 'error' => 'Şablon oturumu bulunamadı.']; }
    $temiz = [];
    $gorulen = [];
    foreach ($items as $it) {
        $tid = (int)($it['teknik_id'] ?? 0);
        if ($tid <= 0 || isset($gorulen[$tid]) || !technique_get($tid)) { continue; }
        $gorulen[$tid] = true;
        $temiz[] = ['teknik_id' => $tid,
                    'sure_dk' => max(1, min(120, (int)($it['sure_dk'] ?? 10))),
                    'uygulama_notu' => trim((string)($it['uygulama_notu'] ?? ''))];
    }
    if (!$temiz) { return ['ok' => false, 'error' => 'Oturuma en az bir teknik ekleyin.']; }

    $protokol = (string)($d['protokol'] ?? '');
    if (!isset(PROTOKOL_LABELS[$protokol])) { $protokol = ''; }

    $pdo = db();
    $pdo->exec('BEGIN');
    try {
        $pdo->prepare('UPDATE sablon_oturumlari SET hafta_no = ?, oturum_adi = ?, hedef = ?, protokol = ? WHERE id = ?')
            ->execute([max(1, min(52, (int)($d['hafta_no'] ?? 1))),
                       mb_substr(trim((string)($d['oturum_adi'] ?? 'A')) ?: 'A', 0, 3),
                       trim((string)($d['hedef'] ?? '')), $protokol, $id]);
        $pdo->prepare('DELETE FROM sablon_teknikleri WHERE sablon_oturum_id = ?')->execute([$id]);
        $ins = $pdo->prepare('INSERT INTO sablon_teknikleri (sablon_oturum_id, teknik_id, sira, sure_dk, uygulama_notu)
                              VALUES (?, ?, ?, ?, ?)');
        foreach ($temiz as $i => $it) {
            $ins->execute([$id, $it['teknik_id'], $i + 1, $it['sure_dk'], $it['uygulama_notu']]);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }
    return ['ok' => true, 'error' => null];
}

function template_session_delete(int $id): void
{
    db()->prepare('DELETE FROM sablon_oturumlari WHERE id = ?')->execute([$id]);
}

/** Şablonun haftalara bağlı ev görevleri: [hafta_no => [satırlar]]. */
function template_home_tasks(int $sablonId): array
{
    $st = db()->prepare('SELECT g.hafta_no, g.calisma_id, c.ad, c.tur, c.sure_dk
                           FROM sablon_ev_gorevleri g JOIN ev_calismalari c ON c.id = g.calisma_id
                          WHERE g.sablon_id = ? ORDER BY g.hafta_no, c.ad');
    $st->execute([$sablonId]);
    $harita = [];
    foreach ($st->fetchAll() as $r) { $harita[(int)$r['hafta_no']][] = $r; }
    return $harita;
}

/** Bir haftanın ev görev listesini olduğu gibi yazar (A/B oturumları ortaktır). */
function template_home_tasks_set(int $sablonId, int $haftaNo, array $calismaIds): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM sablon_ev_gorevleri WHERE sablon_id = ? AND hafta_no = ?')
        ->execute([$sablonId, $haftaNo]);
    $ins = $pdo->prepare('INSERT OR IGNORE INTO sablon_ev_gorevleri (sablon_id, hafta_no, calisma_id) VALUES (?, ?, ?)');
    foreach ($calismaIds as $cid) {
        $cid = (int)$cid;
        if ($cid > 0 && home_exercise_get($cid)) { $ins->execute([$sablonId, $haftaNo, $cid]); }
    }
}

/**
 * Şablonu bir gruba uygular: her şablon oturumu için gerçek oturum + plan oluşturur.
 * $mod: 'A' (yalnız A oturumları) | 'AB' (ikisi de). $bGunFarki: B oturumunun A'dan kaç gün sonra olduğu.
 * Aynı grup + tarihte oturum varsa atlanır. Oturum oluşan haftalarda şablonun
 * ev görevleri gruptaki aktif öğrencilere hafta aralığıyla ödev olarak atanır.
 * @return array{olusan:int, atlanan:int, odev:int}
 */
function template_apply(int $sablonId, int $grupId, string $baslangicTarihi, string $mod, int $bGunFarki): array
{
    $sablon = template_get($sablonId);
    $grup = group_get($grupId);
    if (!$sablon || !$grup) { return ['olusan' => 0, 'atlanan' => 0, 'odev' => 0]; }
    $baslangic = DateTime::createFromFormat('Y-m-d', $baslangicTarihi) ?: new DateTime('now');
    $bGunFarki = max(1, min(6, $bGunFarki));

    $olusan = 0;
    $atlanan = 0;
    $olusanHaftalar = [];
    $varMi = db()->prepare('SELECT id FROM oturumlar WHERE grup_id = ? AND tarih = ?');
    foreach ($sablon['oturumlar'] as $o) {
        if ($mod !== 'AB' && $o['oturum_adi'] !== 'A') { continue; }
        $tarih = (clone $baslangic)->modify('+' . (((int)$o['hafta_no'] - 1) * 7) . ' days');
        if ($o['oturum_adi'] === 'B') { $tarih->modify('+' . $bGunFarki . ' days'); }
        $tarihStr = $tarih->format('Y-m-d');

        $varMi->execute([$grupId, $tarihStr]);
        if ($varMi->fetch()) { $atlanan++; continue; }

        $items = array_map(fn($t) => [
            'teknik_id' => (int)$t['teknik_id'],
            'sure_dk' => (int)$t['sure_dk'],
            'uygulama_notu' => (string)$t['uygulama_notu'],
        ], $o['teknikler']);
        if (!$items) { $atlanan++; continue; }

        $res = session_save_plan([
            'grup_id' => $grupId,
            'tarih' => $tarihStr,
            'hafta_no' => (int)$o['hafta_no'],
            'protokol' => (string)($o['protokol'] ?? ''),
            'notlar' => 'Şablon: ' . $sablon['ad'] . ' · Hafta ' . (int)$o['hafta_no'] . $o['oturum_adi']
                      . ' — ' . $o['hedef'],
        ], $items, null);
        if ($res['ok']) { $olusan++; $olusanHaftalar[(int)$o['hafta_no']] = true; } else { $atlanan++; }
    }

    $odev = 0;
    $gorevler = template_home_tasks($sablonId);
    if ($gorevler && $olusanHaftalar) {
        $ogrenciIds = array_map(fn($o) => (int)$o['id'], students_list($grupId, 1));
        if ($ogrenciIds) {
            foreach (array_keys($olusanHaftalar) as $haftaNo) {
                $bas = (clone $baslangic)->modify('+' . (($haftaNo - 1) * 7) . ' days')->format('Y-m-d');
                $bit = (clone $baslangic)->modify('+' . (($haftaNo - 1) * 7 + 6) . ' days')->format('Y-m-d');
                foreach ($gorevler[$haftaNo] ?? [] as $g) {
                    $odev += assignment_create($ogrenciIds, (int)$g['calisma_id'], $bas, $bit, 5,
                        'Şablon: ' . $sablon['ad'] . ' · Hafta ' . $haftaNo);
                }
            }
        }
    }
    return ['olusan' => $olusan, 'atlanan' => $atlanan, 'odev' => $odev];
}

/* ======================== PROTOKOL SONUÇLARI ======================== */

/** @return array{ok:bool, error:?string, id:?int} */
function protocol_result_save(array $d): array
{
    $ogrenciId = (int)($d['ogrenci_id'] ?? 0);
    if (!student_get($ogrenciId)) {
        return ['ok' => false, 'error' => 'Sonucu kaydetmek için öğrenci seçin.', 'id' => null];
    }
    $protokol = (string)($d['protokol'] ?? '');
    if (!isset(PROTOKOL_LABELS[$protokol])) {
        return ['ok' => false, 'error' => 'Geçersiz protokol.', 'id' => null];
    }
    $skor = max(0, min(100, (int)($d['skor'] ?? 0)));
    $bpm = (int)($d['bpm'] ?? 0) ?: null;
    $detay = (string)($d['detay'] ?? '{}');
    if (json_decode($detay) === null) { $detay = '{}'; }
    if (strlen($detay) > 4000) { $detay = '{}'; }

    $kaynak = ($d['kaynak'] ?? 'atolye') === 'ev' ? 'ev' : 'atolye';
    $standart = (int)($d['standart'] ?? 0) === 1 ? 1 : 0;
    db()->prepare('INSERT INTO protokol_sonuclari (ogrenci_id, protokol, bpm, skor, detay, notlar, kaynak, standart, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$ogrenciId, $protokol, $bpm, $skor,
                   $detay, trim((string)($d['notlar'] ?? '')), $kaynak, $standart, now_str()]);
    return ['ok' => true, 'error' => null, 'id' => (int)db()->lastInsertId()];
}

function protocol_results_for_student(int $ogrenciId): array
{
    $st = db()->prepare('SELECT * FROM protokol_sonuclari WHERE ogrenci_id = ? ORDER BY created_at DESC, id DESC');
    $st->execute([$ogrenciId]);
    return $st->fetchAll();
}

function protocol_results_recent(int $limit = 10): array
{
    $st = db()->prepare('SELECT p.*, o.kod AS ogrenci_kod FROM protokol_sonuclari p
                           JOIN ogrenciler o ON o.id = p.ogrenci_id
                          ORDER BY p.created_at DESC, p.id DESC LIMIT ?');
    $st->execute([$limit]);
    return $st->fetchAll();
}

function protocol_result_delete(int $id): void
{
    db()->prepare('DELETE FROM protokol_sonuclari WHERE id = ?')->execute([$id]);
}

/** Her öğrencinin her protokoldeki SON skoru: [ogrenci_id => [protokol => skor]]. */
function protocol_last_scores(): array
{
    $rows = db()->query('SELECT p.ogrenci_id, p.protokol, p.skor
                           FROM protokol_sonuclari p
                           JOIN (SELECT ogrenci_id, protokol, MAX(created_at) AS mx, MAX(id) AS mid
                                   FROM protokol_sonuclari GROUP BY ogrenci_id, protokol) s
                             ON s.ogrenci_id = p.ogrenci_id AND s.protokol = p.protokol AND s.mid = p.id')
                ->fetchAll();
    $harita = [];
    foreach ($rows as $r) { $harita[(int)$r['ogrenci_id']][$r['protokol']] = (int)$r['skor']; }
    return $harita;
}

/**
 * Dönemlik rapor için grup protokol gelişimi:
 * - haftalik: [protokol => [haftaPzt => ['toplam','adet']]]
 * - ogrenciler: [protokol => [ogrenci_kod => ['ilk','son','adet']]]
 */
function report_group_protocols(int $grupId, string $from, string $to): array
{
    $st = db()->prepare("SELECT p.protokol, p.skor, p.standart, p.created_at, o.kod
                           FROM protokol_sonuclari p
                           JOIN ogrenciler o ON o.id = p.ogrenci_id
                          WHERE o.grup_id = ? AND date(p.created_at) BETWEEN ? AND ?
                          ORDER BY p.created_at");
    $st->execute([$grupId, $from, $to]);
    $haftalik = [];
    $seriler = []; // [protokol][kod] => ['hepsi' => [skor,…], 'std' => [skor,…]]
    foreach ($st->fetchAll() as $r) {
        [$pzt] = week_bounds(substr($r['created_at'], 0, 10));
        $haftalik[$r['protokol']][$pzt]['toplam'] = ($haftalik[$r['protokol']][$pzt]['toplam'] ?? 0) + (int)$r['skor'];
        $haftalik[$r['protokol']][$pzt]['adet'] = ($haftalik[$r['protokol']][$pzt]['adet'] ?? 0) + 1;
        $seriler[$r['protokol']][$r['kod']]['hepsi'][] = (int)$r['skor'];
        if ((int)$r['standart'] === 1) { $seriler[$r['protokol']][$r['kod']]['std'][] = (int)$r['skor']; }
    }
    // İlk→son karşılaştırması: en az iki STANDART ölçüm varsa yalnız onlardan
    // (koşullar sabit → karşılaştırma dürüst); yoksa tüm ölçümlerden.
    $ogrenciler = [];
    foreach ($seriler as $protokol => $kodlar) {
        foreach ($kodlar as $kod => $s) {
            $std = $s['std'] ?? [];
            $dizi = count($std) >= 2 ? $std : $s['hepsi'];
            $ogrenciler[$protokol][$kod] = [
                'ilk' => $dizi[0], 'son' => $dizi[count($dizi) - 1],
                'adet' => count($dizi), 'standart' => count($std) >= 2 ? 1 : 0,
            ];
        }
    }
    return ['haftalik' => $haftalik, 'ogrenciler' => $ogrenciler];
}

/**
 * Sertifika için tek öğrencinin protokol başına İLK ve SON ölçümü (tarihli).
 * En az iki STANDART ölçüm varsa karşılaştırma yalnız onlardan yapılır
 * ('standart' => 1); yoksa tüm ölçümlerden ('standart' => 0).
 * Dönüş: [protokol => ['ilk','ilk_tarih','son','son_tarih','adet','standart']].
 */
function student_protocol_first_last(int $ogrenciId, string $from, string $to): array
{
    $st = db()->prepare('SELECT protokol, skor, standart, created_at
                           FROM protokol_sonuclari
                          WHERE ogrenci_id = ? AND date(created_at) BETWEEN ? AND ?
                          ORDER BY created_at, id');
    $st->execute([$ogrenciId, $from, $to]);
    $seriler = [];
    foreach ($st->fetchAll() as $r) {
        $kayit = ['skor' => (int)$r['skor'], 'tarih' => substr((string)$r['created_at'], 0, 10)];
        $seriler[$r['protokol']]['hepsi'][] = $kayit;
        if ((int)$r['standart'] === 1) { $seriler[$r['protokol']]['std'][] = $kayit; }
    }
    $harita = [];
    foreach ($seriler as $p => $s) {
        $std = $s['std'] ?? [];
        $dizi = count($std) >= 2 ? $std : $s['hepsi'];
        $ilk = $dizi[0];
        $son = $dizi[count($dizi) - 1];
        $harita[$p] = ['ilk' => $ilk['skor'], 'ilk_tarih' => $ilk['tarih'],
                       'son' => $son['skor'], 'son_tarih' => $son['tarih'],
                       'adet' => count($dizi), 'standart' => count($std) >= 2 ? 1 : 0];
    }
    uksort($harita, fn($a, $b) => array_search($a, array_keys(PROTOKOL_LABELS)) <=> array_search($b, array_keys(PROTOKOL_LABELS)));
    return $harita;
}

/* ======================== EV PROGRAMI ======================== */

/** Karışmayan karakterlerle 6 haneli öğrenci erişim kodu üretir (benzersiz). */
function generate_access_code(): string
{
    $harfler = 'ABCDEFGHJKLMNPRSTUVYZ23456789';
    $st = db()->prepare('SELECT 1 FROM ogrenciler WHERE erisim_kodu = ?');
    for ($deneme = 0; $deneme < 40; $deneme++) {
        $kod = '';
        for ($i = 0; $i < 6; $i++) { $kod .= $harfler[random_int(0, strlen($harfler) - 1)]; }
        $st->execute([$kod]);
        if (!$st->fetch()) { return $kod; }
    }
    return strtoupper(bin2hex(random_bytes(4)));
}

/** Kodsuz öğrencilere erişim kodu dağıtır (her istekte ucuz kontrol). */
function ensure_student_codes(): void
{
    $ids = db()->query("SELECT id FROM ogrenciler WHERE erisim_kodu IS NULL OR erisim_kodu = ''")
               ->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) { return; }
    $up = db()->prepare('UPDATE ogrenciler SET erisim_kodu = ? WHERE id = ?');
    foreach ($ids as $id) { $up->execute([generate_access_code(), (int)$id]); }
}

function student_by_code(string $kod): ?array
{
    $kod = strtoupper(trim($kod));
    if ($kod === '') { return null; }
    $st = db()->prepare('SELECT o.*, g.ad AS grup_ad FROM ogrenciler o
                         LEFT JOIN gruplar g ON g.id = o.grup_id
                         WHERE o.erisim_kodu = ? AND o.aktif = 1');
    $st->execute([$kod]);
    return $st->fetch() ?: null;
}

function student_code_regenerate(int $id): string
{
    $kod = generate_access_code();
    db()->prepare('UPDATE ogrenciler SET erisim_kodu = ? WHERE id = ?')->execute([$kod, $id]);
    return $kod;
}

/* ---------- Ev çalışması kütüphanesi ---------- */

function home_exercises_list(bool $sadeceAktif = false): array
{
    $sql = 'SELECT * FROM ev_calismalari' . ($sadeceAktif ? ' WHERE aktif = 1' : '')
         . ' ORDER BY hafta_onerisi IS NULL, hafta_onerisi, ad';
    return db()->query($sql)->fetchAll();
}

function home_exercise_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM ev_calismalari WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** @return array{ok:bool, error:?string, id:?int} */
function home_exercise_save(array $d, ?int $id = null): array
{
    $ad = trim((string)($d['ad'] ?? ''));
    if ($ad === '') { return ['ok' => false, 'error' => 'Çalışma adı boş olamaz.', 'id' => null]; }
    $st = db()->prepare('SELECT id FROM ev_calismalari WHERE ad = ? AND id != ?');
    $st->execute([$ad, (int)$id]);
    if ($st->fetch()) { return ['ok' => false, 'error' => 'Bu adla bir ev çalışması zaten var.', 'id' => null]; }
    $tur = isset(EV_TUR_LABELS[(string)($d['tur'] ?? '')]) ? $d['tur'] : 'serbest';
    $kitle = isset(KITLE_LABELS[(string)($d['kitle'] ?? '')]) ? $d['kitle'] : 'hepsi';
    $hafta = trim((string)($d['hafta_onerisi'] ?? ''));
    $vals = [$ad, $tur, $kitle, trim((string)($d['aciklama'] ?? '')), trim((string)($d['veli_yonerge'] ?? '')),
             max(1, min(60, (int)($d['sure_dk'] ?? 3))), max(30, min(240, (int)($d['bpm'] ?? 66))),
             max(1, min(3, (int)($d['seviye'] ?? 1))), $hafta === '' ? null : max(1, min(52, (int)$hafta)),
             trim((string)($d['hedef_beceri'] ?? '')), trim((string)($d['kanit_notu'] ?? '')),
             isset($d['aktif']) ? (int)!!$d['aktif'] : 1];
    if ($id === null) {
        $vals[] = now_str();
        db()->prepare('INSERT INTO ev_calismalari
                (ad, tur, kitle, aciklama, veli_yonerge, sure_dk, bpm, seviye, hafta_onerisi, hedef_beceri, kanit_notu, aktif, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute($vals);
        return ['ok' => true, 'error' => null, 'id' => (int)db()->lastInsertId()];
    }
    $vals[] = $id;
    db()->prepare('UPDATE ev_calismalari SET ad = ?, tur = ?, kitle = ?, aciklama = ?, veli_yonerge = ?,
                sure_dk = ?, bpm = ?, seviye = ?, hafta_onerisi = ?, hedef_beceri = ?, kanit_notu = ?, aktif = ?
             WHERE id = ?')->execute($vals);
    return ['ok' => true, 'error' => null, 'id' => $id];
}

/* ---------- Ödevler ve tamamlama ---------- */

/** Öğrencinin bugün geçerli ödevleri (çalışma bilgisiyle). */
function assignments_active_for_student(int $ogrenciId): array
{
    $st = db()->prepare('SELECT o.*, c.ad, c.tur, c.aciklama, c.veli_yonerge, c.sure_dk, c.bpm, c.seviye,
                                c.hedef_beceri, c.kanit_notu
                           FROM ev_odevleri o JOIN ev_calismalari c ON c.id = o.calisma_id
                          WHERE o.ogrenci_id = ? AND o.baslangic <= ? AND o.bitis >= ?
                          ORDER BY c.tur = \'serbest\', c.ad');
    $bugun = today();
    $st->execute([$ogrenciId, $bugun, $bugun]);
    return $st->fetchAll();
}

/** Yönetim listesi: tüm güncel ödevler + bu hafta / toplam işaret sayıları. */
function assignments_admin_list(): array
{
    [$pzt, $paz] = week_bounds();
    $st = db()->prepare('SELECT o.*, c.ad AS calisma_ad, c.tur, s.kod AS ogrenci_kod, s.id AS ogrenci_id,
                   (SELECT COUNT(*) FROM ev_tamamlama t WHERE t.odev_id = o.id)                                   AS toplam_gun,
                   (SELECT COUNT(*) FROM ev_tamamlama t WHERE t.odev_id = o.id AND t.tarih BETWEEN ? AND ?)      AS hafta_gun
              FROM ev_odevleri o
              JOIN ev_calismalari c ON c.id = o.calisma_id
              JOIN ogrenciler s ON s.id = o.ogrenci_id
             WHERE o.bitis >= ?
             ORDER BY s.kod, c.ad');
    $st->execute([$pzt, $paz, today()]);
    return $st->fetchAll();
}

/** Ödev oluşturur (öğrenci listesi için). @return int oluşturulan sayısı */
function assignment_create(array $ogrenciIds, int $calismaId, string $baslangic, string $bitis, int $hedefGun, string $notlar): int
{
    if (!home_exercise_get($calismaId)) { return 0; }
    if (!DateTime::createFromFormat('Y-m-d', $baslangic) || !DateTime::createFromFormat('Y-m-d', $bitis)) { return 0; }
    $olusan = 0;
    $ins = db()->prepare('INSERT INTO ev_odevleri (ogrenci_id, calisma_id, baslangic, bitis, hedef_gun, notlar, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?)');
    $varMi = db()->prepare('SELECT 1 FROM ev_odevleri WHERE ogrenci_id = ? AND calisma_id = ? AND bitis >= ?');
    foreach ($ogrenciIds as $oid) {
        $oid = (int)$oid;
        if (!student_get($oid)) { continue; }
        $varMi->execute([$oid, $calismaId, today()]);
        if ($varMi->fetch()) { continue; } // aynı çalışma zaten güncel atanmış
        $ins->execute([$oid, $calismaId, $baslangic, $bitis, max(1, min(7, $hedefGun)), trim($notlar), now_str()]);
        $olusan++;
    }
    return $olusan;
}

function assignment_get(int $id): ?array
{
    $st = db()->prepare('SELECT o.*, c.ad, c.tur FROM ev_odevleri o
                         JOIN ev_calismalari c ON c.id = o.calisma_id WHERE o.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function assignment_delete(int $id): void
{
    db()->prepare('DELETE FROM ev_odevleri WHERE id = ?')->execute([$id]);
}

/** Bugünü işaretler/geri alır. @return bool işaretli mi kaldı */
function completion_toggle(int $odevId, string $tarih, array $veri = []): bool
{
    $st = db()->prepare('SELECT 1 FROM ev_tamamlama WHERE odev_id = ? AND tarih = ?');
    $st->execute([$odevId, $tarih]);
    if ($st->fetch()) {
        db()->prepare('DELETE FROM ev_tamamlama WHERE odev_id = ? AND tarih = ?')->execute([$odevId, $tarih]);
        return false;
    }
    db()->prepare('INSERT INTO ev_tamamlama (odev_id, tarih, veri) VALUES (?, ?, ?)')
        ->execute([$odevId, $tarih, json_encode($veri, JSON_UNESCAPED_UNICODE)]);
    return true;
}

/** İşareti garantiye alır (modül tamamlanınca). */
function completion_mark(int $odevId, string $tarih, array $veri = []): void
{
    db()->prepare('INSERT INTO ev_tamamlama (odev_id, tarih, veri) VALUES (?, ?, ?)
                   ON CONFLICT(odev_id, tarih) DO UPDATE SET veri = excluded.veri')
        ->execute([$odevId, $tarih, json_encode($veri, JSON_UNESCAPED_UNICODE)]);
}

/** [odev_id => [tarih => 1]] biçiminde tamamlama haritası. */
function completions_map(array $odevIds, string $from, string $to): array
{
    if (!$odevIds) { return []; }
    $yer = implode(',', array_fill(0, count($odevIds), '?'));
    $st = db()->prepare("SELECT odev_id, tarih FROM ev_tamamlama
                          WHERE odev_id IN ($yer) AND tarih BETWEEN ? AND ?");
    $st->execute([...array_map('intval', $odevIds), $from, $to]);
    $harita = [];
    foreach ($st->fetchAll() as $r) { $harita[(int)$r['odev_id']][$r['tarih']] = 1; }
    return $harita;
}

/** Öğrencinin (tüm ödevlerinde) bugüne kadar kesintisiz işaretli gün serisi. */
function streak_for_student(int $ogrenciId): int
{
    $gunler = db()->prepare('SELECT DISTINCT t.tarih FROM ev_tamamlama t
                              JOIN ev_odevleri o ON o.id = t.odev_id
                             WHERE o.ogrenci_id = ? ORDER BY t.tarih DESC LIMIT 120');
    $gunler->execute([$ogrenciId]);
    $set = array_flip($gunler->fetchAll(PDO::FETCH_COLUMN));
    if (!$set) { return 0; }
    $g = new DateTime(today());
    if (!isset($set[$g->format('Y-m-d')])) { $g->modify('-1 day'); } // bugün henüz yapılmadıysa dünden say
    $seri = 0;
    while (isset($set[$g->format('Y-m-d')])) {
        $seri++;
        $g->modify('-1 day');
    }
    return $seri;
}

/** Veli raporu için: aralıkla kesişen ödevler + aralıktaki işaretli gün sayısı. */
function report_student_home(int $ogrenciId, string $from, string $to): array
{
    $st = db()->prepare('SELECT o.id, c.ad, o.hedef_gun, o.baslangic, o.bitis,
                   (SELECT COUNT(*) FROM ev_tamamlama t WHERE t.odev_id = o.id AND t.tarih BETWEEN ? AND ?) AS gun_sayisi
              FROM ev_odevleri o JOIN ev_calismalari c ON c.id = o.calisma_id
             WHERE o.ogrenci_id = ? AND o.baslangic <= ? AND o.bitis >= ?
             ORDER BY c.ad');
    $st->execute([$from, $to, $ogrenciId, $to, $from]);
    return $st->fetchAll();
}

/* ---------- Seans paketleri ---------- */

/** Paketin kullanılan seans sayısı: başlangıçtan beri katıldı/geç yoklamaları. */
function package_used(array $paket): int
{
    $st = db()->prepare("SELECT COUNT(*) FROM katilim k JOIN oturumlar s ON s.id = k.oturum_id
                          WHERE k.ogrenci_id = ? AND k.durum IN ('katildi','gec') AND s.tarih >= ?");
    $st->execute([(int)$paket['ogrenci_id'], $paket['baslangic']]);
    return (int)$st->fetchColumn();
}

function package_active(int $ogrenciId): ?array
{
    $st = db()->prepare('SELECT * FROM paketler WHERE ogrenci_id = ? AND kapali = 0
                         ORDER BY baslangic DESC, id DESC LIMIT 1');
    $st->execute([$ogrenciId]);
    $p = $st->fetch();
    if (!$p) { return null; }
    $p['kullanilan'] = package_used($p);
    $p['kalan'] = max(0, (int)$p['toplam_seans'] - $p['kullanilan']);
    return $p;
}

function packages_for(int $ogrenciId): array
{
    $st = db()->prepare('SELECT * FROM paketler WHERE ogrenci_id = ? ORDER BY baslangic DESC, id DESC');
    $st->execute([$ogrenciId]);
    return $st->fetchAll();
}

function package_create(int $ogrenciId, string $ad, int $toplam, string $baslangic, string $notlar): bool
{
    if (!student_get($ogrenciId) || !DateTime::createFromFormat('Y-m-d', $baslangic)) { return false; }
    // Aynı anda tek aktif paket: öncekini kapat.
    db()->prepare('UPDATE paketler SET kapali = 1 WHERE ogrenci_id = ? AND kapali = 0')->execute([$ogrenciId]);
    db()->prepare('INSERT INTO paketler (ogrenci_id, ad, toplam_seans, baslangic, kapali, notlar, created_at)
                   VALUES (?, ?, ?, ?, 0, ?, ?)')
        ->execute([$ogrenciId, trim($ad) ?: ($toplam . ' seanslık paket'),
                   max(1, min(60, $toplam)), $baslangic, trim($notlar), now_str()]);
    return true;
}

function package_close(int $id): void
{
    db()->prepare('UPDATE paketler SET kapali = 1 WHERE id = ?')->execute([$id]);
}

/** Paneldeki uyarı: kalan seansı eşik altına inen aktif paketler. */
function packages_expiring(int $esik = 2): array
{
    $rows = db()->query('SELECT p.*, s.kod AS ogrenci_kod FROM paketler p
                          JOIN ogrenciler s ON s.id = p.ogrenci_id
                         WHERE p.kapali = 0 ORDER BY s.kod')->fetchAll();
    $sonuc = [];
    foreach ($rows as $p) {
        $p['kullanilan'] = package_used($p);
        $p['kalan'] = max(0, (int)$p['toplam_seans'] - $p['kullanilan']);
        if ($p['kalan'] <= $esik) { $sonuc[] = $p; }
    }
    return $sonuc;
}

/** Grubun bugünden itibaren ilk planlı oturumu. */
function next_session_for_group(int $grupId): ?array
{
    $st = db()->prepare('SELECT * FROM oturumlar WHERE grup_id = ? AND tarih >= ? ORDER BY tarih LIMIT 1');
    $st->execute([$grupId, today()]);
    return $st->fetch() ?: null;
}

/* ======================== RAPOR SORGULARI ======================== */

/** Hafta raporu: verilen aralıktaki oturumlar, grup bazında gruplanmış. */
function report_weekly(string $from, string $to): array
{
    $oturumlar = sessions_list(null, $from, $to);
    usort($oturumlar, fn($a, $b) => strcmp($a['tarih'], $b['tarih']));
    $gruplu = [];
    foreach ($oturumlar as $s) {
        $gid = (int)$s['grup_id'];
        $gruplu[$gid]['grup_ad'] = $s['grup_ad'];
        $s['teknikler'] = session_techniques((int)$s['id']);
        $gruplu[$gid]['oturumlar'][] = $s;
    }
    return $gruplu;
}

/** Dönemlik grup raporu verileri. */
function report_group_period(int $grupId, string $from, string $to): array
{
    $pdo = db();

    $st = $pdo->prepare("SELECT t.kategori, COUNT(*) AS adet, SUM(ot.sure_dk) AS sure
                           FROM oturum_teknikleri ot
                           JOIN oturumlar s ON s.id = ot.oturum_id
                           JOIN teknikler t ON t.id = ot.teknik_id
                          WHERE s.grup_id = ? AND s.tarih BETWEEN ? AND ? AND ot.islendi = 1
                          GROUP BY t.kategori ORDER BY adet DESC");
    $st->execute([$grupId, $from, $to]);
    $kategoriDagilimi = $st->fetchAll();

    $oturumlar = sessions_list($grupId, $from, $to);
    usort($oturumlar, fn($a, $b) => strcmp($a['tarih'], $b['tarih']));

    $st = $pdo->prepare("SELECT COUNT(DISTINCT k.ogrenci_id) FROM katilim k
                           JOIN oturumlar s ON s.id = k.oturum_id
                          WHERE s.grup_id = ? AND s.tarih BETWEEN ? AND ?");
    $st->execute([$grupId, $from, $to]);
    $ogrenciSayisi = (int)$st->fetchColumn();

    return ['kategori_dagilimi' => $kategoriDagilimi, 'oturumlar' => $oturumlar, 'ogrenci_sayisi' => $ogrenciSayisi];
}

/** Veli raporu verileri: katılım + çalışılan teknikler + gözlemler. */
function report_student(int $ogrenciId, string $from, string $to): array
{
    $pdo = db();

    $st = $pdo->prepare("SELECT k.durum, k.gozlem_notu, s.tarih, s.hafta_no, s.id AS oturum_id
                           FROM katilim k JOIN oturumlar s ON s.id = k.oturum_id
                          WHERE k.ogrenci_id = ? AND s.tarih BETWEEN ? AND ?
                          ORDER BY s.tarih ASC");
    $st->execute([$ogrenciId, $from, $to]);
    $katilimlar = $st->fetchAll();

    $toplam = count($katilimlar);
    $gelen = count(array_filter($katilimlar, fn($k) => $k['durum'] !== 'gelmedi'));

    // Öğrencinin bulunduğu oturumlarda fiilen işlenen teknikler
    $st = $pdo->prepare("SELECT t.ad, t.kategori, COUNT(*) AS oturum_adedi
                           FROM oturum_teknikleri ot
                           JOIN teknikler t ON t.id = ot.teknik_id
                           JOIN oturumlar s ON s.id = ot.oturum_id
                           JOIN katilim k  ON k.oturum_id = s.id AND k.ogrenci_id = ? AND k.durum IN ('katildi','gec')
                          WHERE s.tarih BETWEEN ? AND ? AND ot.islendi = 1
                          GROUP BY t.id ORDER BY oturum_adedi DESC, t.ad");
    $st->execute([$ogrenciId, $from, $to]);
    $teknikler = $st->fetchAll();

    $gozlemler = array_values(array_filter($katilimlar, fn($k) => trim((string)$k['gozlem_notu']) !== ''));

    return [
        'katilimlar' => $katilimlar,
        'toplam'     => $toplam,
        'gelen'      => $gelen,
        'oran'       => $toplam > 0 ? (int)round(100 * $gelen / $toplam) : null,
        'teknikler'  => $teknikler,
        'gozlemler'  => $gozlemler,
    ];
}
