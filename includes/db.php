<?php
if (!defined('RITIM')) { http_response_code(403); exit; }

/**
 * SQLite bağlantısı (tekil). Veritabanı tek dosyadır: storage/ritim.sqlite —
 * yedeklemek için bu dosyayı kopyalamak yeterlidir.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dizin = APP_DIR . '/storage';
        if (!is_dir($dizin)) { @mkdir($dizin, 0755, true); }
        $pdo = new PDO('sqlite:' . $dizin . '/ritim.sqlite', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 3000');
    }
    return $pdo;
}

/**
 * Şema göçleri. Her göç bir kez uygulanır (PRAGMA user_version ile izlenir).
 * Şema değişikliği gerektiğinde buraya YENİ bir madde eklenir; mevcut maddeler
 * değiştirilmez.
 */
function run_migrations(): void
{
    $pdo = db();
    $surum = (int)$pdo->query('PRAGMA user_version')->fetchColumn();

    $gocler = [
        // v1 — çekirdek veri modeli (CLAUDE.md §4)
        1 => "
            CREATE TABLE gruplar (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                ad               TEXT    NOT NULL,
                yas_araligi      TEXT    NOT NULL DEFAULT '',
                gun              INTEGER NOT NULL DEFAULT 1 CHECK (gun BETWEEN 1 AND 7),
                saat             TEXT    NOT NULL DEFAULT '',
                aktif            INTEGER NOT NULL DEFAULT 1,
                baslangic_tarihi TEXT    NOT NULL DEFAULT '',
                created_at       TEXT    NOT NULL
            );
            CREATE TABLE ogrenciler (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                kod          TEXT    NOT NULL UNIQUE,
                dogum_yili   INTEGER,
                grup_id      INTEGER REFERENCES gruplar(id) ON DELETE SET NULL,
                veli_notu    TEXT    NOT NULL DEFAULT '',
                aktif        INTEGER NOT NULL DEFAULT 1,
                kayit_tarihi TEXT    NOT NULL
            );
            CREATE TABLE teknikler (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                ad           TEXT    NOT NULL UNIQUE,
                kategori     TEXT    NOT NULL,
                enstruman    TEXT    NOT NULL DEFAULT '',
                seviye       INTEGER NOT NULL DEFAULT 1 CHECK (seviye BETWEEN 1 AND 3),
                sure_dk      INTEGER NOT NULL DEFAULT 10,
                aciklama     TEXT    NOT NULL DEFAULT '',
                hedef_beceri TEXT    NOT NULL DEFAULT '',
                kanit_duzeyi TEXT    NOT NULL CHECK (kanit_duzeyi IN ('guclu','orta','zayif','yok')),
                kaynak       TEXT    NOT NULL DEFAULT '',
                malzeme      TEXT    NOT NULL DEFAULT '',
                aktif        INTEGER NOT NULL DEFAULT 1,
                created_at   TEXT    NOT NULL
            );
            CREATE TABLE oturumlar (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                grup_id    INTEGER NOT NULL REFERENCES gruplar(id) ON DELETE CASCADE,
                tarih      TEXT    NOT NULL,
                hafta_no   INTEGER NOT NULL DEFAULT 1,
                notlar     TEXT    NOT NULL DEFAULT '',
                created_at TEXT    NOT NULL
            );
            CREATE INDEX ix_oturum_grup  ON oturumlar(grup_id);
            CREATE INDEX ix_oturum_tarih ON oturumlar(tarih);
            CREATE TABLE oturum_teknikleri (
                oturum_id     INTEGER NOT NULL REFERENCES oturumlar(id) ON DELETE CASCADE,
                teknik_id     INTEGER NOT NULL REFERENCES teknikler(id),
                sira          INTEGER NOT NULL DEFAULT 1,
                sure_dk       INTEGER NOT NULL DEFAULT 10,
                uygulama_notu TEXT    NOT NULL DEFAULT '',
                islendi       INTEGER,            -- NULL = plan, 1 = işlendi, 0 = atlandı
                PRIMARY KEY (oturum_id, teknik_id)
            );
            CREATE TABLE katilim (
                oturum_id   INTEGER NOT NULL REFERENCES oturumlar(id) ON DELETE CASCADE,
                ogrenci_id  INTEGER NOT NULL REFERENCES ogrenciler(id) ON DELETE CASCADE,
                durum       TEXT    NOT NULL CHECK (durum IN ('katildi','gelmedi','gec')),
                gozlem_notu TEXT    NOT NULL DEFAULT '',
                PRIMARY KEY (oturum_id, ogrenci_id)
            );
        ",

        // v2 — metronom protokol sonuçları (vuruş tutturma, BPM bulma …)
        2 => "
            CREATE TABLE protokol_sonuclari (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                ogrenci_id INTEGER NOT NULL REFERENCES ogrenciler(id) ON DELETE CASCADE,
                protokol   TEXT    NOT NULL,
                bpm        INTEGER,
                skor       INTEGER,
                detay      TEXT    NOT NULL DEFAULT '{}',
                notlar     TEXT    NOT NULL DEFAULT '',
                created_at TEXT    NOT NULL
            );
            CREATE INDEX ix_protokol_ogrenci ON protokol_sonuclari(ogrenci_id, protokol);
        ",

        // v3 — site içerik yönetimi (CMS) + eğitim planı şablonları
        3 => "
            CREATE TABLE site_icerik (
                anahtar TEXT PRIMARY KEY,
                deger   TEXT NOT NULL DEFAULT ''
            );
            CREATE TABLE site_bolumleri (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                anahtar TEXT    NOT NULL UNIQUE,
                baslik  TEXT    NOT NULL,
                sira    INTEGER NOT NULL DEFAULT 1,
                gorunur INTEGER NOT NULL DEFAULT 1
            );
            CREATE TABLE plan_sablonlari (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                ad          TEXT NOT NULL,
                aciklama    TEXT NOT NULL DEFAULT '',
                hedef_kitle TEXT NOT NULL DEFAULT '',
                sure_dk     INTEGER NOT NULL DEFAULT 60,
                created_at  TEXT NOT NULL
            );
            CREATE TABLE sablon_oturumlari (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                sablon_id  INTEGER NOT NULL REFERENCES plan_sablonlari(id) ON DELETE CASCADE,
                hafta_no   INTEGER NOT NULL,
                oturum_adi TEXT    NOT NULL DEFAULT 'A',
                hedef      TEXT    NOT NULL DEFAULT ''
            );
            CREATE INDEX ix_sablon_oturum ON sablon_oturumlari(sablon_id, hafta_no);
            CREATE TABLE sablon_teknikleri (
                sablon_oturum_id INTEGER NOT NULL REFERENCES sablon_oturumlari(id) ON DELETE CASCADE,
                teknik_id        INTEGER NOT NULL REFERENCES teknikler(id),
                sira             INTEGER NOT NULL DEFAULT 1,
                sure_dk          INTEGER NOT NULL DEFAULT 10,
                uygulama_notu    TEXT    NOT NULL DEFAULT '',
                PRIMARY KEY (sablon_oturum_id, teknik_id)
            );
        ",

        // v4 — tanıtım sitesi: bilim kartları ve foto galerisi CMS'e taşındı
        4 => "
            CREATE TABLE site_makaleler (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                baslik  TEXT    NOT NULL,
                kunye   TEXT    NOT NULL DEFAULT '',
                bulgu   TEXT    NOT NULL DEFAULT '',
                yansima TEXT    NOT NULL DEFAULT '',
                rozet   TEXT    NOT NULL DEFAULT 'orta' CHECK (rozet IN ('guclu','orta','zayif','yok')),
                sira    INTEGER NOT NULL DEFAULT 1,
                gorunur INTEGER NOT NULL DEFAULT 1
            );
            CREATE TABLE site_galeri (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                dosya   TEXT    NOT NULL,
                baslik  TEXT    NOT NULL DEFAULT '',
                sira    INTEGER NOT NULL DEFAULT 1,
                gorunur INTEGER NOT NULL DEFAULT 1
            );
        ",

        // v5 — ev programı (öğrenci erişim kodu, ev çalışmaları, ödevler,
        //       günlük tamamlama) + seans paketleri + protokol kaynağı (atölye/ev)
        5 => "
            ALTER TABLE ogrenciler ADD COLUMN erisim_kodu TEXT;
            CREATE UNIQUE INDEX ix_ogrenci_erisim ON ogrenciler(erisim_kodu)
                WHERE erisim_kodu IS NOT NULL;
            ALTER TABLE protokol_sonuclari ADD COLUMN kaynak TEXT NOT NULL DEFAULT 'atolye';
            CREATE TABLE ev_calismalari (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                ad            TEXT    NOT NULL UNIQUE,
                tur           TEXT    NOT NULL DEFAULT 'serbest'
                              CHECK (tur IN ('serbest','metronom','vurus_tutturma','ritim_okuma')),
                kitle         TEXT    NOT NULL DEFAULT 'hepsi'
                              CHECK (kitle IN ('cocuk','yetiskin','hepsi')),
                aciklama      TEXT    NOT NULL DEFAULT '',
                veli_yonerge  TEXT    NOT NULL DEFAULT '',
                sure_dk       INTEGER NOT NULL DEFAULT 3,
                bpm           INTEGER NOT NULL DEFAULT 66,
                seviye        INTEGER NOT NULL DEFAULT 1,
                hafta_onerisi INTEGER,
                hedef_beceri  TEXT    NOT NULL DEFAULT '',
                kanit_notu    TEXT    NOT NULL DEFAULT '',
                aktif         INTEGER NOT NULL DEFAULT 1,
                created_at    TEXT    NOT NULL
            );
            CREATE TABLE ev_odevleri (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                ogrenci_id  INTEGER NOT NULL REFERENCES ogrenciler(id) ON DELETE CASCADE,
                calisma_id  INTEGER NOT NULL REFERENCES ev_calismalari(id) ON DELETE CASCADE,
                baslangic   TEXT    NOT NULL,
                bitis       TEXT    NOT NULL,
                hedef_gun   INTEGER NOT NULL DEFAULT 5,
                notlar      TEXT    NOT NULL DEFAULT '',
                created_at  TEXT    NOT NULL
            );
            CREATE INDEX ix_odev_ogrenci ON ev_odevleri(ogrenci_id, bitis);
            CREATE TABLE ev_tamamlama (
                odev_id INTEGER NOT NULL REFERENCES ev_odevleri(id) ON DELETE CASCADE,
                tarih   TEXT    NOT NULL,
                veri    TEXT    NOT NULL DEFAULT '{}',
                PRIMARY KEY (odev_id, tarih)
            );
            CREATE TABLE paketler (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                ogrenci_id   INTEGER NOT NULL REFERENCES ogrenciler(id) ON DELETE CASCADE,
                ad           TEXT    NOT NULL DEFAULT '',
                toplam_seans INTEGER NOT NULL DEFAULT 10,
                baslangic    TEXT    NOT NULL,
                kapali       INTEGER NOT NULL DEFAULT 0,
                notlar       TEXT    NOT NULL DEFAULT '',
                created_at   TEXT    NOT NULL
            );
            CREATE INDEX ix_paket_ogrenci ON paketler(ogrenci_id, kapali);
        ",

        // v6 — akademik çalışma kayıt defteri ve teknik-çalışma bağları (DOI'li)
        6 => "
            CREATE TABLE akademik_calismalar (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                baslik     TEXT    NOT NULL,
                yazarlar   TEXT    NOT NULL DEFAULT '',
                yil        INTEGER,
                dergi      TEXT    NOT NULL DEFAULT '',
                doi        TEXT    NOT NULL DEFAULT '',
                tur        TEXT    NOT NULL DEFAULT 'diger',
                ozet       TEXT    NOT NULL DEFAULT '',
                created_at TEXT    NOT NULL
            );
            CREATE TABLE teknik_calismalari (
                teknik_id   INTEGER NOT NULL REFERENCES teknikler(id) ON DELETE CASCADE,
                calisma_id  INTEGER NOT NULL REFERENCES akademik_calismalar(id) ON DELETE CASCADE,
                iliski_notu TEXT    NOT NULL DEFAULT '',
                PRIMARY KEY (teknik_id, calisma_id)
            );
        ",

        // v7 — ev çalışması türlerine 'icsel_ritim' eklendi (CHECK genişletme:
        //       SQLite'ta tablo yeniden kurularak yapılır, FK kapalı/işlemsiz)
        7 => "-- NOTX
            CREATE TABLE ev_calismalari_v2 (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                ad            TEXT    NOT NULL UNIQUE,
                tur           TEXT    NOT NULL DEFAULT 'serbest'
                              CHECK (tur IN ('serbest','metronom','vurus_tutturma','ritim_okuma','icsel_ritim')),
                kitle         TEXT    NOT NULL DEFAULT 'hepsi'
                              CHECK (kitle IN ('cocuk','yetiskin','hepsi')),
                aciklama      TEXT    NOT NULL DEFAULT '',
                veli_yonerge  TEXT    NOT NULL DEFAULT '',
                sure_dk       INTEGER NOT NULL DEFAULT 3,
                bpm           INTEGER NOT NULL DEFAULT 66,
                seviye        INTEGER NOT NULL DEFAULT 1,
                hafta_onerisi INTEGER,
                hedef_beceri  TEXT    NOT NULL DEFAULT '',
                kanit_notu    TEXT    NOT NULL DEFAULT '',
                aktif         INTEGER NOT NULL DEFAULT 1,
                created_at    TEXT    NOT NULL
            );
            INSERT INTO ev_calismalari_v2 SELECT * FROM ev_calismalari;
            DROP TABLE ev_calismalari;
            ALTER TABLE ev_calismalari_v2 RENAME TO ev_calismalari;
        ",
    ];

    foreach ($gocler as $no => $sql) {
        if ($no <= $surum) { continue; }
        if (str_starts_with(ltrim($sql), '-- NOTX')) {
            // Tablo yeniden kurma göçleri: FK kapalı, işlemsiz (SQLite pragma
            // kısıtı). Sıra güvenli yazılır: yeni tablo → kopya → drop → rename.
            $pdo->exec('PRAGMA foreign_keys = OFF');
            try {
                $pdo->exec($sql);
                $pdo->exec('PRAGMA user_version = ' . (int)$no);
            } finally {
                $pdo->exec('PRAGMA foreign_keys = ON');
            }
            continue;
        }
        $pdo->exec('BEGIN');
        try {
            $pdo->exec($sql);
            $pdo->exec('PRAGMA user_version = ' . (int)$no);
            $pdo->exec('COMMIT');
        } catch (Throwable $ex) {
            $pdo->exec('ROLLBACK');
            throw $ex;
        }
    }
}
