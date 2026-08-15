<?php
if (!defined('RITIM')) { http_response_code(403); exit; }

/* ======================== GRUPLAR ======================== */

function groups_list(bool $onlyActive = false): array
{
    $sql = 'SELECT g.*,
                   (SELECT COUNT(*) FROM grup_uyelikleri gu
                     JOIN ogrenciler o ON o.id = gu.ogrenci_id
                    WHERE gu.grup_id = g.id AND gu.aktif = 1 AND o.aktif = 1) AS ogrenci_sayisi,
                   (SELECT COUNT(*) FROM grup_uyelikleri gu
                    WHERE gu.grup_id = g.id AND gu.aktif = 1) AS uyelik_sayisi,
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
    if ($saat !== '' && !valid_time_hm($saat)) {
        return ['ok' => false, 'error' => 'Saat SS:DD biçiminde olmalı (örn. 17:30).', 'id' => null];
    }
    $baslangic = trim((string)($d['baslangic_tarihi'] ?? ''));
    if ($baslangic !== '' && !valid_date_ymd($baslangic)) {
        return ['ok' => false, 'error' => 'Başlangıç tarihi geçersiz.', 'id' => null];
    }
    $tur = (string)($d['tur'] ?? 'grup');
    if (!isset(GRUP_TUR_LABELS[$tur])) {
        return ['ok' => false, 'error' => 'Geçerli bir ders türü seçin.', 'id' => null];
    }
    if ($id !== null && $tur === 'ozel') {
        $st = db()->prepare('SELECT COUNT(*) FROM grup_uyelikleri WHERE grup_id = ? AND aktif = 1');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 1) {
            return ['ok' => false, 'error' => 'Birden fazla aktif katılımcısı olan ders özel derse çevrilemez.', 'id' => null];
        }
    }
    if ($id !== null && $saat !== '') {
        $st = db()->prepare('SELECT o.kod, diger.ad AS diger_grup
                               FROM grup_uyelikleri mevcut
                               JOIN ogrenciler o ON o.id = mevcut.ogrenci_id
                               JOIN grup_uyelikleri gu ON gu.ogrenci_id = mevcut.ogrenci_id
                                    AND gu.aktif = 1 AND gu.grup_id != mevcut.grup_id
                               JOIN gruplar diger ON diger.id = gu.grup_id AND diger.aktif = 1
                              WHERE mevcut.grup_id = ? AND mevcut.aktif = 1
                                AND diger.gun = ? AND diger.saat = ?
                              LIMIT 1');
        $st->execute([$id, $gun, $saat]);
        if ($cakisma = $st->fetch()) {
            return ['ok' => false,
                    'error' => $cakisma['kod'] . ' için program çakışması var: ' . $cakisma['diger_grup']
                             . ' aynı gün ve saatte.', 'id' => null];
        }
    }
    $vals = [$ad, trim((string)($d['yas_araligi'] ?? '')), $gun, $saat,
             isset($d['aktif']) ? (int)!!$d['aktif'] : 1, $baslangic, $tur];
    if ($id === null) {
        $vals[] = now_str();
        db()->prepare('INSERT INTO gruplar (ad, yas_araligi, gun, saat, aktif, baslangic_tarihi, tur, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute($vals);
        return ['ok' => true, 'error' => null, 'id' => (int)db()->lastInsertId()];
    }
    $vals[] = $id;
    db()->prepare('UPDATE gruplar SET ad = ?, yas_araligi = ?, gun = ?, saat = ?, aktif = ?, baslangic_tarihi = ?, tur = ?
                   WHERE id = ?')->execute($vals);
    return ['ok' => true, 'error' => null, 'id' => $id];
}

/** Grubu ve (CASCADE ile) oturum geçmişini siler; öğrenciler grupsuz kalır. */
function group_delete(int $id): void
{
    db()->prepare('DELETE FROM gruplar WHERE id = ?')->execute([$id]);
}

/* ---------- Grup duyuruları ---------- */

function group_announcements(int $grupId, bool $onlyVisible = false): array
{
    $sql = 'SELECT * FROM grup_duyurulari WHERE grup_id = ?';
    $par = [$grupId];
    if ($onlyVisible) {
        $sql .= ' AND aktif = 1 AND yayin_tarihi <= ?
                  AND (bitis_tarihi IS NULL OR bitis_tarihi = "" OR bitis_tarihi >= ?)';
        $par[] = today();
        $par[] = today();
    }
    $sql .= ' ORDER BY aktif DESC, yayin_tarihi DESC, id DESC';
    $st = db()->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

/** @return array{ok:bool,error:?string,id:?int} */
function group_announcement_save(int $grupId, array $d): array
{
    if (!group_get($grupId)) {
        return ['ok' => false, 'error' => 'Ders/grup bulunamadı.', 'id' => null];
    }
    $baslik = trim((string)($d['baslik'] ?? ''));
    $mesaj = trim((string)($d['mesaj'] ?? ''));
    if ($baslik === '') {
        return ['ok' => false, 'error' => 'Duyuru başlığı boş olamaz.', 'id' => null];
    }
    if (mb_strlen($baslik) > 80 || mb_strlen($mesaj) > 500) {
        return ['ok' => false, 'error' => 'Duyuru metni izin verilen uzunluğu aşıyor.', 'id' => null];
    }
    $yayin = trim((string)($d['yayin_tarihi'] ?? today()));
    $bitis = trim((string)($d['bitis_tarihi'] ?? ''));
    if (!valid_date_ymd($yayin) || ($bitis !== '' && !valid_date_ymd($bitis))) {
        return ['ok' => false, 'error' => 'Duyuru tarihlerini kontrol edin.', 'id' => null];
    }
    if ($bitis !== '' && $bitis < $yayin) {
        return ['ok' => false, 'error' => 'Bitiş tarihi yayın tarihinden önce olamaz.', 'id' => null];
    }
    db()->prepare('INSERT INTO grup_duyurulari
            (grup_id, baslik, mesaj, yayin_tarihi, bitis_tarihi, aktif, created_at)
         VALUES (?, ?, ?, ?, ?, 1, ?)')
        ->execute([$grupId, $baslik, $mesaj, $yayin, $bitis !== '' ? $bitis : null, now_str()]);
    return ['ok' => true, 'error' => null, 'id' => (int)db()->lastInsertId()];
}

function group_announcement_set_active(int $grupId, int $duyuruId, bool $aktif): bool
{
    $st = db()->prepare('UPDATE grup_duyurulari SET aktif = ?
                         WHERE id = ? AND grup_id = ?');
    $st->execute([$aktif ? 1 : 0, $duyuruId, $grupId]);
    return $st->rowCount() > 0;
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
    if ($grupId !== null) {
        $kosul[] = 'EXISTS (SELECT 1 FROM grup_uyelikleri guf
                            WHERE guf.ogrenci_id = o.id AND guf.grup_id = ? AND guf.aktif = 1)';
        $par[] = $grupId;
    }
    if ($aktif !== null)  { $kosul[] = 'o.aktif = ?';   $par[] = $aktif; }
    $sql = 'SELECT o.*, g.ad AS grup_ad,
                   (SELECT GROUP_CONCAT(g2.ad, " • ")
                      FROM grup_uyelikleri gu2
                      JOIN gruplar g2 ON g2.id = gu2.grup_id
                     WHERE gu2.ogrenci_id = o.id AND gu2.aktif = 1) AS grup_adlari
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
    $st = db()->prepare('SELECT o.*, g.ad AS grup_ad,
                         (SELECT GROUP_CONCAT(g2.ad, " • ")
                            FROM grup_uyelikleri gu2
                            JOIN gruplar g2 ON g2.id = gu2.grup_id
                           WHERE gu2.ogrenci_id = o.id AND gu2.aktif = 1) AS grup_adlari
                         FROM ogrenciler o
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
        if (!preg_match('/^\d{4}$/', $dogum)) {
            return ['ok' => false, 'error' => 'Doğum yılı dört haneli olmalıdır.', 'id' => null];
        }
        $dogumYili = (int)$dogum;
        $buYil = (int)now()->format('Y');
        if ($dogumYili < 1920 || $dogumYili > $buYil) {
            return ['ok' => false, 'error' => 'Doğum yılı geçersiz.', 'id' => null];
        }
    }
    $grupDegisecek = array_key_exists('grup_id', $d);
    $grupId = $grupDegisecek ? ((int)($d['grup_id'] ?? 0) ?: null) : null;
    if ($grupDegisecek && $grupId !== null && !group_get($grupId)) {
        return ['ok' => false, 'error' => 'Seçilen grup bulunamadı.', 'id' => null];
    }
    $aktif = isset($d['aktif']) ? (int)!!$d['aktif'] : 1;
    $veliNotu = trim((string)($d['veli_notu'] ?? ''));
    if ($id === null) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO ogrenciler (kod, dogum_yili, grup_id, veli_notu, aktif, kayit_tarihi)
                           VALUES (?, ?, NULL, ?, ?, ?)')->execute([$kod, $dogumYili, $veliNotu, $aktif, today()]);
            $yeniId = (int)$pdo->lastInsertId();
            if ($grupId !== null) {
                $uyelik = group_member_add($grupId, $yeniId);
                if (!$uyelik['ok']) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => $uyelik['error'], 'id' => null];
                }
            }
            $pdo->commit();
            return ['ok' => true, 'error' => null, 'id' => $yeniId];
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $ex;
        }
    }
    db()->prepare('UPDATE ogrenciler SET kod = ?, dogum_yili = ?, veli_notu = ?, aktif = ?
                   WHERE id = ?')->execute([$kod, $dogumYili, $veliNotu, $aktif, $id]);
    if ($grupDegisecek && $grupId !== null) {
        $uyelik = group_member_add($grupId, $id);
        if (!$uyelik['ok']) { return ['ok' => false, 'error' => $uyelik['error'], 'id' => null]; }
    }
    return ['ok' => true, 'error' => null, 'id' => $id];
}

/** Katılımcının aktif ders/grup üyelikleri. */
function student_groups(int $ogrenciId, bool $onlyActive = true): array
{
    $sql = 'SELECT g.*, gu.baslangic_tarihi AS uyelik_baslangic, gu.bitis_tarihi AS uyelik_bitis,
                   gu.aktif AS uyelik_aktif
              FROM grup_uyelikleri gu
              JOIN gruplar g ON g.id = gu.grup_id
             WHERE gu.ogrenci_id = ?' . ($onlyActive ? ' AND gu.aktif = 1' : '')
         . ' ORDER BY g.aktif DESC, g.ad';
    $st = db()->prepare($sql);
    $st->execute([$ogrenciId]);
    return $st->fetchAll();
}

/** Grupta etkin üyeliği olmayan aktif katılımcılar. */
function students_not_in_group(int $grupId): array
{
    $st = db()->prepare('SELECT o.*
                           FROM ogrenciler o
                          WHERE o.aktif = 1
                            AND NOT EXISTS (
                                SELECT 1 FROM grup_uyelikleri gu
                                 WHERE gu.grup_id = ? AND gu.ogrenci_id = o.id AND gu.aktif = 1
                            )
                          ORDER BY o.kod');
    $st->execute([$grupId]);
    return $st->fetchAll();
}

/** @return array{ok:bool,error:?string} */
function group_member_add(int $grupId, int $ogrenciId): array
{
    $grup = group_get($grupId);
    if (!$grup || !student_get($ogrenciId)) {
        return ['ok' => false, 'error' => 'Grup veya katılımcı bulunamadı.'];
    }
    if (($grup['tur'] ?? 'grup') === 'ozel') {
        $st = db()->prepare('SELECT COUNT(*) FROM grup_uyelikleri
                             WHERE grup_id = ? AND aktif = 1 AND ogrenci_id != ?');
        $st->execute([$grupId, $ogrenciId]);
        if ((int)$st->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'Özel derse yalnızca bir aktif katılımcı eklenebilir.'];
        }
    }
    if ((string)$grup['saat'] !== '') {
        $st = db()->prepare('SELECT g.ad
                               FROM grup_uyelikleri gu
                               JOIN gruplar g ON g.id = gu.grup_id
                              WHERE gu.ogrenci_id = ? AND gu.aktif = 1
                                AND g.aktif = 1 AND g.id != ?
                                AND g.gun = ? AND g.saat = ?
                              LIMIT 1');
        $st->execute([$ogrenciId, $grupId, (int)$grup['gun'], (string)$grup['saat']]);
        if ($cakisan = $st->fetchColumn()) {
            return ['ok' => false,
                    'error' => 'Program çakışması: ' . $cakisan . ' aynı gün ve saatte.'];
        }
    }
    db()->prepare('INSERT INTO grup_uyelikleri
            (grup_id, ogrenci_id, aktif, baslangic_tarihi, bitis_tarihi, created_at)
         VALUES (?, ?, 1, ?, NULL, ?)
         ON CONFLICT(grup_id, ogrenci_id) DO UPDATE SET
            aktif = 1, baslangic_tarihi = excluded.baslangic_tarihi, bitis_tarihi = NULL')
        ->execute([$grupId, $ogrenciId, today(), now_str()]);
    db()->prepare('UPDATE ogrenciler SET grup_id = COALESCE(grup_id, ?) WHERE id = ?')
        ->execute([$grupId, $ogrenciId]);
    return ['ok' => true, 'error' => null];
}

/** Üyeliği tarihçeyi koruyarak pasife alır; oturum/yoklama geçmişini silmez. */
function group_member_remove(int $grupId, int $ogrenciId): bool
{
    $st = db()->prepare('UPDATE grup_uyelikleri SET aktif = 0, bitis_tarihi = ?
                         WHERE grup_id = ? AND ogrenci_id = ? AND aktif = 1');
    $st->execute([today(), $grupId, $ogrenciId]);
    if ($st->rowCount() < 1) { return false; }
    $st = db()->prepare('SELECT grup_id FROM grup_uyelikleri
                         WHERE ogrenci_id = ? AND aktif = 1 ORDER BY baslangic_tarihi, grup_id LIMIT 1');
    $st->execute([$ogrenciId]);
    $yedekGrup = $st->fetchColumn();
    db()->prepare('UPDATE ogrenciler SET grup_id = ? WHERE id = ? AND grup_id = ?')
        ->execute([$yedekGrup === false ? null : (int)$yedekGrup, $ogrenciId, $grupId]);
    return true;
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
    if (!valid_date_ymd($tarih)) {
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
    if (!$sablon || !$grup || !valid_date_ymd($baslangicTarihi)) {
        return ['olusan' => 0, 'atlanan' => 0, 'odev' => 0];
    }
    $baslangic = DateTime::createFromFormat('Y-m-d', $baslangicTarihi);
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
    // Kararlılık (asenkroni SD) ve ölçüm anındaki kalibrasyon kalitesi:
    // ikisi de sonradan süzme/yorumlama için sonuçla birlikte saklanır.
    $sd = $d['sd_ms'] ?? null;
    $sdMs = ($sd === null || $sd === '' || !is_numeric($sd)) ? null : max(0, min(60000, (int)round((float)$sd)));
    $kalite = (string)($d['kalite'] ?? '');
    if (!isset(OLCUM_KALITE_LABELS[$kalite])) { $kalite = ''; }
    // Varyant: aynı protokolün karşılaştırılamaz koşulu (poliritimde oran gibi).
    // Serilerin karışmaması buna bağlı; kısa ve düzenli tutulur.
    $varyant = preg_replace('/[^0-9A-Za-z:\/._-]/', '', (string)($d['varyant'] ?? ''));
    $varyant = substr((string)$varyant, 0, 20);
    db()->prepare('INSERT INTO protokol_sonuclari (ogrenci_id, protokol, bpm, skor, detay, notlar, kaynak, standart, sd_ms, kalite, varyant, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$ogrenciId, $protokol, $bpm, $skor,
                   $detay, trim((string)($d['notlar'] ?? '')), $kaynak, $standart, $sdMs, $kalite, $varyant, now_str()]);
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

/* ==================== ÖLÇÜM KARŞILAŞTIRMA ÇEKİRDEĞİ ====================
 * Tek ilk ölçümü tek son ölçümle karşılaştırmak iki yönden yanıltıcıdır:
 * (1) ilk ölçüm neredeyse her zaman "görevi ilk kez yapma" etkisiyle düşüktür,
 * (2) tek ölçüm gün içi dalgalanmaya açıktır. Bu yüzden uçlardan blok medyanı
 * alınır ve fark, ölçümün kendi gürültü bandıyla birlikte sunulur.
 */

function measure_median(array $sayilar): float
{
    if (!$sayilar) { return 0.0; }
    sort($sayilar);
    $n = count($sayilar);
    $orta = intdiv($n, 2);
    return $n % 2 ? (float)$sayilar[$orta] : ($sayilar[$orta - 1] + $sayilar[$orta]) / 2;
}

/** Uç blok boyu — bloklar çakışmayacak biçimde seçilir. */
function measure_block_size(int $n): int
{
    if ($n >= 6) { return 3; }
    if ($n >= 4) { return 2; }
    return 1;
}

/**
 * Ölçüm gürültüsü bandı (MDC95): "bu fark ölçüm hatasından büyük mü?".
 * Ardışık ölçüm farklarından kestirilir — SEM = RMS(fark)/√2, MDC95 = 1,96×√2×SEM
 * (sadeleşince 1,96 × RMS(fark)). Gerçek bir eğilim varsa band genişler, yani
 * tahmin temkinli tarafta kalır. En az 3 ölçüm ister.
 */
function measure_noise_band(array $skorlar): ?array
{
    $n = count($skorlar);
    if ($n < 3) { return null; }
    $kareler = [];
    for ($i = 1; $i < $n; $i++) {
        $fark = $skorlar[$i] - $skorlar[$i - 1];
        $kareler[] = $fark * $fark;
    }
    $rms = sqrt(array_sum($kareler) / count($kareler));
    return ['mdc' => 1.96 * $rms, 'sem' => $rms / M_SQRT2, 'adet' => $n];
}

/**
 * Kronolojik ölçüm serisinden ilk→son karşılaştırması.
 * $seri: [['skor'=>int, 'tarih'=>string, 'kalite'=>string], …]
 */
function measure_compare(array $seri): array
{
    $n = count($seri);
    $k = measure_block_size($n);
    $ilkBlok = array_slice($seri, 0, $k);
    $sonBlok = array_slice($seri, -$k);
    $skorlar = array_map(fn($x) => (int)$x['skor'], $seri);
    $ilk = (int)round(measure_median(array_map(fn($x) => (int)$x['skor'], $ilkBlok)));
    $son = (int)round(measure_median(array_map(fn($x) => (int)$x['skor'], $sonBlok)));
    $band = measure_noise_band($skorlar);
    $fark = $son - $ilk;
    $supheli = count(array_filter($seri, fn($x) => !in_array((string)($x['kalite'] ?? ''), OLCUM_KALITE_GUVENLI, true)));
    return [
        'ilk' => $ilk, 'son' => $son, 'fark' => $fark, 'adet' => $n, 'blok' => $k,
        'ilk_tarih' => (string)($ilkBlok[0]['tarih'] ?? ''),
        'son_tarih' => (string)($sonBlok[count($sonBlok) - 1]['tarih'] ?? ''),
        'mdc' => $band ? (int)round($band['mdc']) : null,
        // null = karar verilemiyor (3'ten az ölçüm)
        'anlamli' => $band ? abs($fark) >= $band['mdc'] : null,
        'supheli_olcum' => $supheli,
    ];
}

/** Karşılaştırmaya girecek seriyi seçer: yeterliyse yalnız standart ölçümler. */
function measure_pick_series(array $hepsi, array $standart): array
{
    return count($standart) >= 2 ? $standart : $hepsi;
}

/**
 * Trend/karşılaştırma serisinin anahtarı. Varyant doluysa protokolden AYRI
 * seri açılır — 3:2 poliritim skoru ile 7:4 skoru aynı çizgide okunamaz
 * (bkz. db.php v16). Varyantsız kayıtlar eskisi gibi protokol adıyla gruplanır.
 */
function protokol_seri_anahtari(string $protokol, string $varyant): string
{
    return $varyant === '' ? $protokol : $protokol . '|' . $varyant;
}

/** Seri anahtarlarını PROTOKOL_LABELS sırasına, varyantları alfabetik dizer. */
function protokol_seri_sirala(array &$seriler): void
{
    $sira = array_keys(PROTOKOL_LABELS);
    uksort($seriler, function (string $a, string $b) use ($sira): int {
        [$ap, $av] = array_pad(explode('|', $a, 2), 2, '');
        [$bp, $bv] = array_pad(explode('|', $b, 2), 2, '');
        return [array_search($ap, $sira), $av] <=> [array_search($bp, $sira), $bv];
    });
}

/**
 * Dönemlik rapor için grup protokol gelişimi:
 * - haftalik: [protokol => [haftaPzt => ['toplam','adet']]]
 * - ogrenciler: [protokol => [ogrenci_kod => measure_compare(...) + 'standart']]
 */
function report_group_protocols(int $grupId, string $from, string $to): array
{
    $st = db()->prepare("SELECT p.protokol, p.varyant, p.skor, p.standart, p.kalite, p.created_at, o.kod
                           FROM protokol_sonuclari p
                           JOIN ogrenciler o ON o.id = p.ogrenci_id
                           JOIN grup_uyelikleri gu ON gu.ogrenci_id = o.id
                            AND gu.grup_id = ? AND gu.aktif = 1
                          WHERE date(p.created_at) BETWEEN ? AND ?
                          ORDER BY p.created_at, p.id");
    $st->execute([$grupId, $from, $to]);
    $haftalik = [];
    $seriler = [];
    foreach ($st->fetchAll() as $r) {
        $anahtar = protokol_seri_anahtari((string)$r['protokol'], (string)($r['varyant'] ?? ''));
        [$pzt] = week_bounds(substr($r['created_at'], 0, 10));
        $haftalik[$anahtar][$pzt]['toplam'] = ($haftalik[$anahtar][$pzt]['toplam'] ?? 0) + (int)$r['skor'];
        $haftalik[$anahtar][$pzt]['adet'] = ($haftalik[$anahtar][$pzt]['adet'] ?? 0) + 1;
        $kayit = ['skor' => (int)$r['skor'], 'tarih' => substr((string)$r['created_at'], 0, 10),
                  'kalite' => (string)($r['kalite'] ?? '')];
        $seriler[$anahtar][$r['kod']]['hepsi'][] = $kayit;
        if ((int)$r['standart'] === 1) { $seriler[$anahtar][$r['kod']]['std'][] = $kayit; }
    }
    $ogrenciler = [];
    foreach ($seriler as $anahtar => $kodlar) {
        foreach ($kodlar as $kod => $s) {
            $std = $s['std'] ?? [];
            $dizi = measure_pick_series($s['hepsi'], $std);
            $ogrenciler[$anahtar][$kod] = measure_compare($dizi) + ['standart' => count($std) >= 2 ? 1 : 0];
        }
    }
    protokol_seri_sirala($haftalik);
    protokol_seri_sirala($ogrenciler);
    return ['haftalik' => $haftalik, 'ogrenciler' => $ogrenciler];
}

/**
 * Grubun dönem içi ev pratiği DOZU: hafta başına işaretlenen pratik günü.
 * Aynı gün birden çok çalışma işaretlense de bir "pratik günü" sayılır.
 * Protokol trendinin yanında durur: değişim pratikle birlikte mi gidiyor?
 * Dönüş: [haftaPzt => ['gun' => int, 'ogrenci' => int]]
 */
function report_group_practice(int $grupId, string $from, string $to): array
{
    $st = db()->prepare('SELECT DISTINCT o.ogrenci_id, t.tarih
                           FROM ev_tamamlama t
                           JOIN ev_odevleri o ON o.id = t.odev_id
                           JOIN grup_uyelikleri gu ON gu.ogrenci_id = o.ogrenci_id
                            AND gu.grup_id = ? AND gu.aktif = 1
                          WHERE t.tarih BETWEEN ? AND ?');
    $st->execute([$grupId, $from, $to]);
    $haftalar = [];
    foreach ($st->fetchAll() as $r) {
        [$pzt] = week_bounds((string)$r['tarih']);
        $haftalar[$pzt]['gun'] = ($haftalar[$pzt]['gun'] ?? 0) + 1;
        $haftalar[$pzt]['ogrenciler'][(int)$r['ogrenci_id']] = true;
    }
    $sonuc = [];
    foreach ($haftalar as $pzt => $v) {
        $sonuc[$pzt] = ['gun' => (int)$v['gun'], 'ogrenci' => count($v['ogrenciler'] ?? [])];
    }
    ksort($sonuc);
    return $sonuc;
}

/**
 * Öğrenci rapor merkezi için kronolojik tam ölçüm serisi.
 * Dönüş: [protokol => [['tarih','skor','sd_ms','standart','kalite','kaynak'], …]]
 * (PROTOKOL_LABELS sırasıyla; grafik ve measure_compare girdisi olarak kullanılır)
 */
function student_protocol_series(int $ogrenciId, string $from, string $to): array
{
    $st = db()->prepare('SELECT protokol, varyant, skor, sd_ms, standart, kalite, kaynak, created_at
                           FROM protokol_sonuclari
                          WHERE ogrenci_id = ? AND date(created_at) BETWEEN ? AND ?
                          ORDER BY created_at, id');
    $st->execute([$ogrenciId, $from, $to]);
    $seriler = [];
    foreach ($st->fetchAll() as $r) {
        $anahtar = protokol_seri_anahtari((string)$r['protokol'], (string)($r['varyant'] ?? ''));
        $seriler[$anahtar][] = [
            'tarih'    => substr((string)$r['created_at'], 0, 10),
            'skor'     => (int)$r['skor'],
            'sd_ms'    => $r['sd_ms'] !== null ? (int)$r['sd_ms'] : null,
            'standart' => (int)$r['standart'],
            'kalite'   => (string)($r['kalite'] ?? ''),
            'kaynak'   => (string)($r['kaynak'] ?? 'atolye'),
        ];
    }
    protokol_seri_sirala($seriler);
    return $seriler;
}

/** Öğrencinin haftalık ev pratiği günleri: [haftaPzt => gün]. */
function student_practice_weekly(int $ogrenciId, string $from, string $to): array
{
    $st = db()->prepare('SELECT DISTINCT t.tarih
                           FROM ev_tamamlama t
                           JOIN ev_odevleri o ON o.id = t.odev_id
                          WHERE o.ogrenci_id = ? AND t.tarih BETWEEN ? AND ?');
    $st->execute([$ogrenciId, $from, $to]);
    $haftalar = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $tarih) {
        [$pzt] = week_bounds((string)$tarih);
        $haftalar[$pzt] = ($haftalar[$pzt] ?? 0) + 1;
    }
    ksort($haftalar);
    return $haftalar;
}

/**
 * Sertifika için tek öğrencinin protokol başına dönem başı/sonu ölçümü.
 * Uçlardan blok medyanı alınır (measure_compare); en az iki STANDART ölçüm
 * varsa karşılaştırma yalnız onlardan yapılır ('standart' => 1).
 * Dönüş: [protokol => measure_compare(...) + 'standart'].
 */
function student_protocol_first_last(int $ogrenciId, string $from, string $to): array
{
    $st = db()->prepare('SELECT protokol, skor, standart, kalite, created_at
                           FROM protokol_sonuclari
                          WHERE ogrenci_id = ? AND date(created_at) BETWEEN ? AND ?
                          ORDER BY created_at, id');
    $st->execute([$ogrenciId, $from, $to]);
    $seriler = [];
    foreach ($st->fetchAll() as $r) {
        $kayit = ['skor' => (int)$r['skor'], 'tarih' => substr((string)$r['created_at'], 0, 10),
                  'kalite' => (string)($r['kalite'] ?? '')];
        $seriler[$r['protokol']]['hepsi'][] = $kayit;
        if ((int)$r['standart'] === 1) { $seriler[$r['protokol']]['std'][] = $kayit; }
    }
    $harita = [];
    foreach ($seriler as $p => $s) {
        $std = $s['std'] ?? [];
        $dizi = measure_pick_series($s['hepsi'], $std);
        $harita[$p] = measure_compare($dizi) + ['standart' => count($std) >= 2 ? 1 : 0];
    }
    uksort($harita, fn($a, $b) => array_search($a, array_keys(PROTOKOL_LABELS)) <=> array_search($b, array_keys(PROTOKOL_LABELS)));
    return $harita;
}

/* ======================== ÖN KAYIT (tanıtım sitesi) ======================== */

const ON_KAYIT_DURUM_LABELS = [
    'yeni' => 'Yeni', 'arandi' => 'Arandı', 'kayit' => 'Kayıt oldu', 'vazgecti' => 'Vazgeçti',
];
const ON_KAYIT_KITLE_LABELS = [
    'cocuk' => 'Çocuk / genç (8–15)', 'yetiskin' => 'Yetişkin (18+)',
    'belirtilmedi' => 'Belirtilmedi',
];
const ON_KAYIT_DERS_TURU_LABELS = [
    'grup' => 'Grup dersi',
    'ozel' => 'Özel ders',
    'kararsiz' => 'Kararsız / görüşmede belirlenecek',
];

/**
 * Tanıtım sitesindeki genel iletişim formundan gelen talebi kaydeder
 * (deneme dersi/oturumu sunulmaz — yalnız "bize ulaşın" mesajıdır).
 * Herkese açık uçtur: alan uzunlukları sınırlanır, HTML saklanmaz ve
 * oturum başına saatlik gönderim sınırı uygulanır (kaba spam koruması).
 * @return array{ok:bool, error:?string}
 */
function pre_registration_save(array $d): array
{
    $ad = trim((string)($d['ad'] ?? ''));
    $iletisim = trim((string)($d['iletisim'] ?? ''));
    if (mb_strlen($ad) < 2 || mb_strlen($ad) > 80) {
        return ['ok' => false, 'error' => 'Lütfen adınızı yazın (2–80 karakter).'];
    }
    if (mb_strlen($iletisim) < 5 || mb_strlen($iletisim) > 120) {
        return ['ok' => false, 'error' => 'Size ulaşabileceğimiz bir telefon veya e-posta yazın.'];
    }
    // Satır sonu enjeksiyonu (e-posta başlığı vb.) taşımasın
    if (preg_match('/[\r\n]/', $ad . $iletisim)) {
        return ['ok' => false, 'error' => 'Geçersiz karakter kullanıldı.'];
    }
    $kitle = (string)($d['kitle'] ?? 'belirtilmedi');
    if (!isset(ON_KAYIT_KITLE_LABELS[$kitle])) { $kitle = 'belirtilmedi'; }
    $dersTuru = (string)($d['ders_turu'] ?? 'kararsiz');
    if (!isset(ON_KAYIT_DERS_TURU_LABELS[$dersTuru])) { $dersTuru = 'kararsiz'; }
    $ziyaretciMesaji = trim((string)($d['mesaj'] ?? ''));
    $kayitMesaji = 'Ders tercihi: ' . ON_KAYIT_DERS_TURU_LABELS[$dersTuru]
        . ($ziyaretciMesaji !== '' ? ' · ' . $ziyaretciMesaji : '');

    /*
     * SUNUCU TARAFLI saatlik sınır (IP başına, veritabanında).
     * Önceden sayaç $_SESSION'daydı: çerez göndermeyen ya da her istekte
     * çerezini atan bir bot sınırsız satır yazabiliyordu — ve bu tablo
     * KİŞİSEL VERİ tutuyor (ad, telefon/e-posta). Giriş ve ev kodu için
     * kullanılan aynı mekanizmaya taşındı; çerez silinerek atlatılamaz.
     */
    $hs = hiz_siniri_dene('onkayit', 3, 3600);
    if (!$hs['izin']) {
        return ['ok' => false, 'error' => 'Kısa sürede birden çok talep aldık. Bir saat sonra tekrar deneyin.'];
    }

    db()->prepare('INSERT INTO on_kayitlar (ad, iletisim, kitle, mesaj, profil, durum, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            mb_substr($ad, 0, 80),
            mb_substr($iletisim, 0, 120),
            $kitle,
            mb_substr($kayitMesaji, 0, 600),
            mb_substr(trim((string)($d['profil'] ?? '')), 0, 300),
            'yeni',
            now_str(),
        ]);
    return ['ok' => true, 'error' => null];
}

/** @param string $durum '' = hepsi */
function pre_registrations(string $durum = ''): array
{
    if ($durum !== '' && isset(ON_KAYIT_DURUM_LABELS[$durum])) {
        $st = db()->prepare('SELECT * FROM on_kayitlar WHERE durum = ? ORDER BY created_at DESC, id DESC');
        $st->execute([$durum]);
        return $st->fetchAll();
    }
    return db()->query('SELECT * FROM on_kayitlar ORDER BY created_at DESC, id DESC')->fetchAll();
}

function pre_registration_count_new(): int
{
    return (int)db()->query("SELECT COUNT(*) FROM on_kayitlar WHERE durum = 'yeni'")->fetchColumn();
}

function pre_registration_set_status(int $id, string $durum): bool
{
    if (!isset(ON_KAYIT_DURUM_LABELS[$durum])) { return false; }
    db()->prepare('UPDATE on_kayitlar SET durum = ? WHERE id = ?')->execute([$durum, $id]);
    return true;
}

function pre_registration_delete(int $id): void
{
    db()->prepare('DELETE FROM on_kayitlar WHERE id = ?')->execute([$id]);
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
    $st = db()->prepare('SELECT o.*, g.ad AS grup_ad,
                         (SELECT GROUP_CONCAT(g2.ad, " • ")
                            FROM grup_uyelikleri gu2
                            JOIN gruplar g2 ON g2.id = gu2.grup_id
                           WHERE gu2.ogrenci_id = o.id AND gu2.aktif = 1) AS grup_adlari
                         FROM ogrenciler o
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
    if (!valid_date_ymd($baslangic) || !valid_date_ymd($bitis) || $bitis < $baslangic) { return 0; }
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
    if (!student_get($ogrenciId) || !valid_date_ymd($baslangic)) { return false; }
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

/**
 * Katılımcı portalında gösterilecek güvenli ders programı.
 * Yalnızca grup planı ve üyelerin takma adları döner; kişisel not/değerlendirme alanları yoktur.
 */
function student_group_programs(int $ogrenciId): array
{
    $gruplar = student_groups($ogrenciId);
    $uyeSt = db()->prepare('SELECT o.kod, CASE WHEN o.id = ? THEN 1 ELSE 0 END AS kendisi
                              FROM grup_uyelikleri gu
                              JOIN ogrenciler o ON o.id = gu.ogrenci_id
                             WHERE gu.grup_id = ? AND gu.aktif = 1 AND o.aktif = 1
                             ORDER BY o.kod');
    $oturumSt = db()->prepare('SELECT s.id, s.tarih, s.hafta_no, s.protokol,
                                     COALESCE(GROUP_CONCAT(t.ad, " • "), "") AS teknikler,
                                     COALESCE(SUM(ot.sure_dk), 0) AS sure_dk
                                FROM oturumlar s
                                LEFT JOIN oturum_teknikleri ot ON ot.oturum_id = s.id
                                LEFT JOIN teknikler t ON t.id = ot.teknik_id
                               WHERE s.grup_id = ? AND s.tarih >= ?
                               GROUP BY s.id
                               ORDER BY s.tarih, s.id
                               LIMIT 6');
    $sonuc = [];
    foreach ($gruplar as $grup) {
        if ((int)$grup['aktif'] !== 1) { continue; }
        $uyeSt->execute([$ogrenciId, (int)$grup['id']]);
        $grup['uyeler'] = $uyeSt->fetchAll();
        $oturumSt->execute([(int)$grup['id'], today()]);
        $grup['program'] = $oturumSt->fetchAll();
        $grup['duyurular'] = group_announcements((int)$grup['id'], true);
        $sonuc[] = $grup;
    }
    return $sonuc;
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

/* ======================== METRONOM ÇALIŞMA MERKEZİ ======================== */

/** Oturum hesabını veri kayıtlarında kullanılabilecek güvenli bir anahtara dönüştürür. */
function metronom_kullanici_anahtari(): string
{
    $rol = trim((string)($_SESSION['rol'] ?? 'egitmen'));
    return mb_substr($rol !== '' ? $rol : 'egitmen', 0, 50);
}

/** Setlist adımlarını güvenli aralıklara sıkıştırır. @return array<int,array<string,mixed>> */
function metronom_adimlari_normalle(array $adimlar): array
{
    $sonuc = [];
    foreach (array_slice($adimlar, 0, 50) as $i => $ham) {
        if (!is_array($ham)) { continue; }
        $olcu = (int)($ham['olcu'] ?? 4);
        $payda = (int)($ham['payda'] ?? 4);
        if (!in_array($olcu, [2,3,4,5,6,7,8,9,10,11,12], true)) { $olcu = 4; }
        if (!in_array($payda, [4,8], true)) { $payda = 4; }
        $alt = (int)($ham['alt'] ?? 1);
        if (!in_array($alt, [1,2,3,4], true)) { $alt = 1; }
        $poli = max(0, min(12, (int)($ham['poliritim'] ?? 0)));
        if ($poli === 1) { $poli = 0; }
        $gecis = ($ham['gecis'] ?? 'otomatik') === 'bekle' ? 'bekle' : 'otomatik';
        $desen = [];
        foreach (array_slice((array)($ham['desen'] ?? []), 0, $olcu) as $v) {
            $desen[] = max(0, min(2, (int)$v));
        }
        while (count($desen) < $olcu) { $desen[] = count($desen) === 0 ? 2 : 1; }
        $sonuc[] = [
            'baslik' => mb_substr(trim((string)($ham['baslik'] ?? 'Adım ' . ($i + 1))), 0, 60),
            'bpm' => max(30, min(240, (int)($ham['bpm'] ?? 92))),
            'olcu' => $olcu,
            'payda' => $payda,
            'gruplama' => mb_substr(trim((string)($ham['gruplama'] ?? 'ozel')), 0, 30),
            'alt' => $alt,
            'swing' => max(50, min(75, (float)($ham['swing'] ?? 50))),
            'poliritim' => $poli,
            'poliDuzey' => max(10, min(100, (int)($ham['poliDuzey'] ?? 55))),
            'girisOlcu' => in_array((int)($ham['girisOlcu'] ?? 0), [0,1,2,4], true)
                ? (int)$ham['girisOlcu'] : 0,
            'sureSn' => max(15, min(7200, (int)($ham['sureSn'] ?? 300))),
            'gecis' => $gecis,
            'desen' => $desen,
        ];
    }
    return $sonuc;
}

function metronom_setleri_listele(string $kullanici): array
{
    $st = db()->prepare('SELECT * FROM metronom_setleri WHERE kullanici = ? ORDER BY updated_at DESC, id DESC');
    $st->execute([$kullanici]);
    $sonuc = [];
    foreach ($st->fetchAll() as $r) {
        $r['id'] = (int)$r['id'];
        $coz = json_decode((string)$r['adimlar'], true);
        $r['adimlar'] = metronom_adimlari_normalle(is_array($coz) ? $coz : []);
        $sonuc[] = $r;
    }
    return $sonuc;
}

/** @return array{ok:bool,id?:int,error?:string,set?:array} */
function metronom_seti_kaydet(string $kullanici, array $veri): array
{
    $ad = mb_substr(trim((string)($veri['ad'] ?? '')), 0, 80);
    $aciklama = mb_substr(trim((string)($veri['aciklama'] ?? '')), 0, 300);
    $adimlar = metronom_adimlari_normalle(is_array($veri['adimlar'] ?? null) ? $veri['adimlar'] : []);
    if ($ad === '') { return ['ok' => false, 'error' => 'Setlist adı boş bırakılamaz.']; }
    if (!$adimlar) { return ['ok' => false, 'error' => 'Setlistte en az bir adım olmalı.']; }
    $json = json_encode($adimlar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $id = max(0, (int)($veri['id'] ?? 0));
    if ($id > 0) {
        $st = db()->prepare('UPDATE metronom_setleri SET ad = ?, aciklama = ?, adimlar = ?, updated_at = ?
                              WHERE id = ? AND kullanici = ?');
        $st->execute([$ad, $aciklama, $json, now_str(), $id, $kullanici]);
        if ($st->rowCount() < 1) { return ['ok' => false, 'error' => 'Setlist bulunamadı veya bu hesaba ait değil.']; }
    } else {
        $st = db()->prepare('INSERT INTO metronom_setleri
            (kullanici, ad, aciklama, adimlar, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
        $simdi = now_str();
        $st->execute([$kullanici, $ad, $aciklama, $json, $simdi, $simdi]);
        $id = (int)db()->lastInsertId();
    }
    $st = db()->prepare('SELECT * FROM metronom_setleri WHERE id = ? AND kullanici = ?');
    $st->execute([$id, $kullanici]);
    $set = $st->fetch() ?: [];
    $set['id'] = $id;
    $set['adimlar'] = $adimlar;
    return ['ok' => true, 'id' => $id, 'set' => $set];
}

function metronom_seti_sil(string $kullanici, int $id): bool
{
    $st = db()->prepare('DELETE FROM metronom_setleri WHERE id = ? AND kullanici = ?');
    $st->execute([$id, $kullanici]);
    return $st->rowCount() > 0;
}

/** On saniyeden kısa yanlış başlatmaları günlüğe yazmaz. */
function metronom_calisma_kaydet(string $kullanici, array $veri): array
{
    $sure = max(0, min(43200, (int)($veri['sureSn'] ?? 0)));
    if ($sure < 10) { return ['ok' => false, 'atlandi' => true, 'error' => '10 saniyeden kısa deneme kaydedilmedi.']; }
    $tur = ($veri['tur'] ?? '') === 'setlist' ? 'setlist' : 'serbest';
    $setId = max(0, (int)($veri['setId'] ?? 0)) ?: null;
    if ($setId !== null) {
        $st = db()->prepare('SELECT 1 FROM metronom_setleri WHERE id = ? AND kullanici = ?');
        $st->execute([$setId, $kullanici]);
        if (!$st->fetchColumn()) { $setId = null; }
    }
    $detay = is_array($veri['detay'] ?? null) ? $veri['detay'] : [];
    $st = db()->prepare('INSERT INTO metronom_calisma_kayitlari
        (kullanici, set_id, tur, baslik, sure_sn, bpm_min, bpm_max, detay, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $st->execute([
        $kullanici, $setId, $tur, mb_substr(trim((string)($veri['baslik'] ?? '')), 0, 100), $sure,
        isset($veri['bpmMin']) ? max(30, min(240, (int)$veri['bpmMin'])) : null,
        isset($veri['bpmMax']) ? max(30, min(240, (int)$veri['bpmMax'])) : null,
        json_encode($detay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), now_str()
    ]);
    return ['ok' => true, 'id' => (int)db()->lastInsertId()];
}

function metronom_hedef_getir(string $kullanici): array
{
    $st = db()->prepare('SELECT gunluk_dk, haftalik_gun FROM metronom_hedefleri WHERE kullanici = ?');
    $st->execute([$kullanici]);
    $r = $st->fetch();
    return $r
        ? ['gunlukDk' => (int)$r['gunluk_dk'], 'haftalikGun' => (int)$r['haftalik_gun']]
        : ['gunlukDk' => 20, 'haftalikGun' => 5];
}

function metronom_hedef_kaydet(string $kullanici, int $gunlukDk, int $haftalikGun): array
{
    $gunlukDk = max(1, min(480, $gunlukDk));
    $haftalikGun = max(1, min(7, $haftalikGun));
    db()->prepare('INSERT INTO metronom_hedefleri (kullanici, gunluk_dk, haftalik_gun, updated_at)
        VALUES (?, ?, ?, ?)
        ON CONFLICT(kullanici) DO UPDATE SET gunluk_dk = excluded.gunluk_dk,
          haftalik_gun = excluded.haftalik_gun, updated_at = excluded.updated_at')
        ->execute([$kullanici, $gunlukDk, $haftalikGun, now_str()]);
    return ['gunlukDk' => $gunlukDk, 'haftalikGun' => $haftalikGun];
}

/** Bugün, son yedi gün, seri ve son kayıtları tek istekte döndürür. */
function metronom_calisma_ozeti(string $kullanici): array
{
    $bugun = today();
    $baslangic = now()->modify('-6 days')->format('Y-m-d');
    $st = db()->prepare("SELECT substr(created_at, 1, 10) AS gun, SUM(sure_sn) AS sure
                           FROM metronom_calisma_kayitlari
                          WHERE kullanici = ? AND substr(created_at, 1, 10) BETWEEN ? AND ?
                          GROUP BY substr(created_at, 1, 10)");
    $st->execute([$kullanici, $baslangic, $bugun]);
    $harita = [];
    foreach ($st->fetchAll() as $r) { $harita[(string)$r['gun']] = (int)$r['sure']; }
    $gunler = [];
    for ($i = 6; $i >= 0; $i--) {
        $tarih = now()->modify("-$i days")->format('Y-m-d');
        $gunler[] = ['tarih' => $tarih, 'sureSn' => $harita[$tarih] ?? 0];
    }

    $st = db()->prepare("SELECT DISTINCT substr(created_at, 1, 10) AS gun
                           FROM metronom_calisma_kayitlari
                          WHERE kullanici = ? ORDER BY gun DESC LIMIT 366");
    $st->execute([$kullanici]);
    $aktifGunler = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
    $tarih = now();
    if (!isset($aktifGunler[$tarih->format('Y-m-d')])) { $tarih->modify('-1 day'); }
    $seri = 0;
    while (isset($aktifGunler[$tarih->format('Y-m-d')])) {
        $seri++;
        $tarih->modify('-1 day');
    }

    $st = db()->prepare('SELECT id, set_id, tur, baslik, sure_sn, bpm_min, bpm_max, created_at
                           FROM metronom_calisma_kayitlari
                          WHERE kullanici = ? ORDER BY created_at DESC, id DESC LIMIT 12');
    $st->execute([$kullanici]);
    $son = $st->fetchAll();
    foreach ($son as &$r) {
        $r['id'] = (int)$r['id'];
        $r['set_id'] = $r['set_id'] === null ? null : (int)$r['set_id'];
        $r['sure_sn'] = (int)$r['sure_sn'];
        $r['bpm_min'] = $r['bpm_min'] === null ? null : (int)$r['bpm_min'];
        $r['bpm_max'] = $r['bpm_max'] === null ? null : (int)$r['bpm_max'];
    }
    unset($r);
    return [
        'bugunSn' => $harita[$bugun] ?? 0,
        'haftaGunSayisi' => count(array_filter($gunler, fn($g) => $g['sureSn'] > 0)),
        'seri' => $seri,
        'gunler' => $gunler,
        'sonKayitlar' => $son,
        'hedef' => metronom_hedef_getir($kullanici),
    ];
}

/* ======================== İKİ EL MOTOR KOORDİNASYONU ======================== */

function motor_protokol_normalle(array $veri): array
{
    $desenler = ['donusumlu', 'ikiserli', 'capraz', 'eszamanli', 'karma'];
    $desen = (string)($veri['desen'] ?? 'donusumlu');
    if (!in_array($desen, $desenler, true)) { $desen = 'donusumlu'; }
    return [
        'id' => max(0, (int)($veri['id'] ?? 0)),
        'ad' => mb_substr(trim((string)($veri['ad'] ?? '')), 0, 80),
        'hedef' => mb_substr(trim((string)($veri['hedef'] ?? '')), 0, 300),
        'desen' => $desen,
        'bpm' => max(30, min(180, (int)($veri['bpm'] ?? 60))),
        'sure_sn' => max(15, min(600, (int)($veri['sureSn'] ?? $veri['sure_sn'] ?? 30))),
        'hazirlik_vurus' => max(2, min(16, (int)($veri['hazirlikVurus'] ?? $veri['hazirlik_vurus'] ?? 4))),
        'tolerans_ms' => max(60, min(300, (int)($veri['toleransMs'] ?? $veri['tolerans_ms'] ?? 140))),
    ];
}

function motor_protokolleri_listele(string $kullanici): array
{
    $st = db()->prepare('SELECT * FROM motor_protokolleri WHERE kullanici = ?
                          ORDER BY updated_at DESC, id DESC');
    $st->execute([$kullanici]);
    $sonuc = [];
    foreach ($st->fetchAll() as $r) {
        $n = motor_protokol_normalle($r);
        $n['created_at'] = $r['created_at'];
        $n['updated_at'] = $r['updated_at'];
        $sonuc[] = $n;
    }
    return $sonuc;
}

/** @return array{ok:bool,error?:string,protokol?:array} */
function motor_protokol_kaydet(string $kullanici, array $veri): array
{
    $p = motor_protokol_normalle($veri);
    if ($p['ad'] === '') { return ['ok' => false, 'error' => 'Protokol adı boş bırakılamaz.']; }
    $simdi = now_str();
    if ($p['id'] > 0) {
        $st = db()->prepare('UPDATE motor_protokolleri
            SET ad = ?, hedef = ?, desen = ?, bpm = ?, sure_sn = ?, hazirlik_vurus = ?,
                tolerans_ms = ?, updated_at = ?
            WHERE id = ? AND kullanici = ?');
        $st->execute([
            $p['ad'], $p['hedef'], $p['desen'], $p['bpm'], $p['sure_sn'],
            $p['hazirlik_vurus'], $p['tolerans_ms'], $simdi, $p['id'], $kullanici
        ]);
        if ($st->rowCount() < 1) {
            $kontrol = db()->prepare('SELECT 1 FROM motor_protokolleri WHERE id = ? AND kullanici = ?');
            $kontrol->execute([$p['id'], $kullanici]);
            if (!$kontrol->fetchColumn()) {
                return ['ok' => false, 'error' => 'Protokol bulunamadı veya bu hesaba ait değil.'];
            }
        }
    } else {
        $st = db()->prepare('INSERT INTO motor_protokolleri
            (kullanici, ad, hedef, desen, bpm, sure_sn, hazirlik_vurus, tolerans_ms, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([
            $kullanici, $p['ad'], $p['hedef'], $p['desen'], $p['bpm'], $p['sure_sn'],
            $p['hazirlik_vurus'], $p['tolerans_ms'], $simdi, $simdi
        ]);
        $p['id'] = (int)db()->lastInsertId();
    }
    return ['ok' => true, 'protokol' => $p];
}

function motor_protokol_sil(string $kullanici, int $id): bool
{
    $st = db()->prepare('DELETE FROM motor_protokolleri WHERE id = ? AND kullanici = ?');
    $st->execute([$id, $kullanici]);
    return $st->rowCount() > 0;
}

/** @return array{ok:bool,error?:string,id?:int} */
function motor_sonuc_kaydet(string $kullanici, array $veri): array
{
    $durum = (string)($veri['durum'] ?? 'tamamlandi');
    if (!in_array($durum, ['tamamlandi', 'erken_durduruldu', 'guvenlik'], true)) {
        $durum = 'erken_durduruldu';
    }
    $protokolId = max(0, (int)($veri['protokolId'] ?? 0)) ?: null;
    if ($protokolId !== null) {
        $st = db()->prepare('SELECT 1 FROM motor_protokolleri WHERE id = ? AND kullanici = ?');
        $st->execute([$protokolId, $kullanici]);
        if (!$st->fetchColumn()) { $protokolId = null; }
    }
    $ogrenciId = max(0, (int)($veri['ogrenciId'] ?? 0)) ?: null;
    if ($ogrenciId !== null && !student_get($ogrenciId)) { $ogrenciId = null; }
    $skor = isset($veri['skor']) ? max(0, min(100, (int)$veri['skor'])) : null;
    $dogruluk = isset($veri['dogruluk']) ? max(0, min(100, (int)$veri['dogruluk'])) : null;
    if ($durum !== 'tamamlandi') { $skor = null; }
    $detay = is_array($veri['detay'] ?? null) ? $veri['detay'] : [];
    $json = json_encode($detay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || strlen($json) > 50000) {
        return ['ok' => false, 'error' => 'Sonuç ayrıntısı geçersiz veya çok büyük.'];
    }
    $st = db()->prepare('INSERT INTO motor_sonuclari
        (kullanici, protokol_id, ogrenci_id, durum, skor, dogruluk, detay, notlar, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $st->execute([
        $kullanici, $protokolId, $ogrenciId, $durum, $skor, $dogruluk, $json,
        mb_substr(trim((string)($veri['notlar'] ?? '')), 0, 500), now_str()
    ]);
    return ['ok' => true, 'id' => (int)db()->lastInsertId()];
}

function motor_sonuclari_son(string $kullanici, int $limit = 20): array
{
    $st = db()->prepare('SELECT s.id, s.protokol_id, s.ogrenci_id, s.durum, s.skor, s.dogruluk,
                                s.detay, s.notlar, s.created_at,
                                p.ad AS protokol_ad, o.kod AS ogrenci_kod
                           FROM motor_sonuclari s
                      LEFT JOIN motor_protokolleri p ON p.id = s.protokol_id
                      LEFT JOIN ogrenciler o ON o.id = s.ogrenci_id
                          WHERE s.kullanici = ?
                       ORDER BY s.created_at DESC, s.id DESC LIMIT ?');
    $st->bindValue(1, $kullanici, PDO::PARAM_STR);
    $st->bindValue(2, max(1, min(100, $limit)), PDO::PARAM_INT);
    $st->execute();
    $sonuc = [];
    foreach ($st->fetchAll() as $r) {
        $r['id'] = (int)$r['id'];
        $r['protokol_id'] = $r['protokol_id'] === null ? null : (int)$r['protokol_id'];
        $r['ogrenci_id'] = $r['ogrenci_id'] === null ? null : (int)$r['ogrenci_id'];
        $r['skor'] = $r['skor'] === null ? null : (int)$r['skor'];
        $r['dogruluk'] = $r['dogruluk'] === null ? null : (int)$r['dogruluk'];
        $coz = json_decode((string)$r['detay'], true);
        $r['detay'] = is_array($coz) ? $coz : [];
        $sonuc[] = $r;
    }
    return $sonuc;
}

/* =================================================================
   MOXO ÖLÇÜM ARŞİVİ (dış rapor)
   =================================================================
   SINIR — bu bölüm bilerek "aptal"dır: veri girer, veri çıkarır.
   Yorum yapmaz, eşik uygulamaz, normalize etmez, grafik için ölçek
   varsaymaz. Nedeni CLAUDE.md §2: uygulama bir eğitim aracıdır;
   MOXO d-CPT'yi uygulama ve yorumlama yetkisi eğitmende değildir.
   Sayılar raporda ne yazıyorsa odur.

   Veli raporu (rapor-veli.php) ve katılım belgesi (sertifika.php)
   bu tabloyu HİÇ OKUMAZ. Duman testi bunu ayrıca sınar.
   ================================================================= */

const MOXO_ASAMALARI = ['on' => 'Ön ölçüm', 'son' => 'Son ölçüm', 'ara' => 'Ara ölçüm'];
const MOXO_INDEKSLERI = [
    'dikkat'        => 'Dikkat',
    'zamanlama'     => 'Zamanlama',
    'durtusellik'   => 'Dürtüsellik',
    'hiperaktivite' => 'Hiperaktivite',
];

/** Bir öğrencinin ölçümleri, eskiden yeniye (ön → son okunsun diye). */
function moxo_results_for_student(int $ogrenciId): array
{
    $st = db()->prepare('SELECT * FROM moxo_olcumleri WHERE ogrenci_id = ? ORDER BY tarih ASC, id ASC');
    $st->execute([$ogrenciId]);
    return $st->fetchAll();
}

function moxo_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM moxo_olcumleri WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/**
 * Ölçüm kaydeder.
 * İndeksler İSTEĞE BAĞLIDIR: rapor hangilerini veriyorsa o girilir, boş
 * bırakılan alan NULL kalır — sıfır yazmak "ölçüldü ve 0 çıktı" demek olurdu.
 */
function moxo_save(array $d, ?int $id = null): array
{
    $ogrenciId = (int)($d['ogrenci_id'] ?? 0);
    if (!student_get($ogrenciId)) { return ['ok' => false, 'error' => 'Öğrenci bulunamadı.', 'id' => null]; }

    $tarih = trim((string)($d['tarih'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarih) || !strtotime($tarih)) {
        return ['ok' => false, 'error' => 'Geçerli bir ölçüm tarihi girin.', 'id' => null];
    }
    /* Gelecek tarihli ölçüm bir veri giriş hatasıdır: rapor henüz yok. */
    if ($tarih > today()) {
        return ['ok' => false, 'error' => 'Ölçüm tarihi gelecekte olamaz.', 'id' => null];
    }

    $asama = (string)($d['asama'] ?? 'on');
    if (!isset(MOXO_ASAMALARI[$asama])) { $asama = 'on'; }

    /* Sayılar: boş → NULL. Virgüllü giriş (Türkçe klavye) noktaya çevrilir.
       Üst sınır dayatılmaz, yalnız akıl dışı değer elenir; ölçeği uygulama
       bilmiyor, bilmediği bir aralığa göre "geçersiz" demeye hakkı yok. */
    $sayilar = [];
    foreach (array_keys(MOXO_INDEKSLERI) as $alan) {
        $ham = trim((string)($d[$alan] ?? ''));
        if ($ham === '') { $sayilar[$alan] = null; continue; }
        $ham = str_replace(',', '.', $ham);
        if (!is_numeric($ham)) {
            return ['ok' => false, 'error' => MOXO_INDEKSLERI[$alan] . ' alanına sayı girin (ya da boş bırakın).', 'id' => null];
        }
        $deger = (float)$ham;
        if ($deger < -999 || $deger > 9999) {
            return ['ok' => false, 'error' => MOXO_INDEKSLERI[$alan] . ' değeri beklenen aralığın dışında.', 'id' => null];
        }
        $sayilar[$alan] = $deger;
    }

    $vals = [$ogrenciId, $tarih, $asama,
             $sayilar['dikkat'], $sayilar['zamanlama'], $sayilar['durtusellik'], $sayilar['hiperaktivite'],
             mb_substr(trim((string)($d['olcek'] ?? '')), 0, 80),
             mb_substr(trim((string)($d['uygulayan'] ?? '')), 0, 120),
             mb_substr(trim((string)($d['rapor_no'] ?? '')), 0, 60),
             mb_substr(trim((string)($d['notlar'] ?? '')), 0, 600)];

    if ($id === null) {
        $vals[] = now_str();
        db()->prepare('INSERT INTO moxo_olcumleri
                (ogrenci_id, tarih, asama, dikkat, zamanlama, durtusellik, hiperaktivite,
                 olcek, uygulayan, rapor_no, notlar, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute($vals);
        return ['ok' => true, 'error' => null, 'id' => (int)db()->lastInsertId()];
    }
    $vals[] = $id;
    db()->prepare('UPDATE moxo_olcumleri SET ogrenci_id = ?, tarih = ?, asama = ?, dikkat = ?, zamanlama = ?,
                durtusellik = ?, hiperaktivite = ?, olcek = ?, uygulayan = ?, rapor_no = ?, notlar = ?
             WHERE id = ?')->execute($vals);
    return ['ok' => true, 'error' => null, 'id' => $id];
}

function moxo_delete(int $id): bool
{
    $st = db()->prepare('DELETE FROM moxo_olcumleri WHERE id = ?');
    $st->execute([$id]);
    return $st->rowCount() > 0;
}

/**
 * Ön ve son ölçümü YAN YANA koyar — yorum üretmez.
 *
 * NEDEN FARK HESAPLANMIYOR: MOXO indekslerinde yönün ne anlama geldiği
 * (yüksek mi iyi, düşük mü) ölçeğe bağlıdır ve uygulama bunu bilmez.
 * "+12" yazmak nötr görünür ama okuyan onu iyileşme diye okur — bu bir
 * sonuç iddiasıdır (CLAUDE.md §2). İki sayı gösterilir, yorum raporundur.
 *
 * @return array<string, array{on: ?float, son: ?float}>
 */
function moxo_on_son(array $olcumler): array
{
    $ilk = null; $sonuncu = null;
    foreach ($olcumler as $o) {
        if ($o['asama'] === 'on'  && $ilk === null)  { $ilk = $o; }
        if ($o['asama'] === 'son')                   { $sonuncu = $o; }
    }
    if (!$ilk || !$sonuncu) { return []; }

    $cikti = [];
    foreach (MOXO_INDEKSLERI as $alan => $etiket) {
        if ($ilk[$alan] === null && $sonuncu[$alan] === null) { continue; }
        $cikti[$etiket] = [
            'on'  => $ilk[$alan] === null ? null : (float)$ilk[$alan],
            'son' => $sonuncu[$alan] === null ? null : (float)$sonuncu[$alan],
        ];
    }
    return $cikti;
}

/** Sayıyı olduğu gibi gösterir: 12.0 → "12", 12.50 → "12,5" (TR ondalık). */
function moxo_sayi(?float $d): string
{
    if ($d === null) { return '—'; }
    $s = rtrim(rtrim(number_format($d, 2, ',', ''), '0'), ',');
    return $s === '' ? '0' : $s;
}
