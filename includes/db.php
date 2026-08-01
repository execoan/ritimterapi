<?php
if (!defined('RITIM')) { http_response_code(403); exit; }

/**
 * SQLite bağlantısı (tekil). Veritabanı tek dosyadır: storage/ritim.sqlite —
 * yedeklemek için bu dosyayı kopyalamak yeterlidir.
 * $sifirla: bağlantıyı kapatır (geri yükleme öncesi dosyayı serbest bırakmak için).
 */
function db_ref(bool $sifirla = false): ?PDO
{
    static $pdo = null;
    if ($sifirla) { $pdo = null; return null; }
    if ($pdo === null) {
        $dizin = STORAGE_DIR;
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

function db(): PDO
{
    return db_ref();
}

function db_close(): void
{
    db_ref(true);
}

/* ---------- Yedekleme ---------- */

function backup_dir(): string
{
    $dizin = STORAGE_DIR . '/yedek';
    if (!is_dir($dizin)) { @mkdir($dizin, 0755, true); }
    return $dizin;
}

/**
 * Çalışan veritabanının TUTARLI kopyasını hedefe yazar (WAL dahil).
 * VACUUM INTO var olan dosyaya yazmaz; kalıntı varsa önce silinir.
 */
function backup_snapshot(string $hedef): bool
{
    if (is_file($hedef)) { @unlink($hedef); }
    try {
        db()->exec("VACUUM INTO '" . str_replace("'", "''", $hedef) . "'");
        return is_file($hedef);
    } catch (Throwable $e) {
        error_log('RitimTerapi yedek hatası: ' . $e->getMessage());
        return false;
    }
}

/** Günde bir otomatik yedek alır; en yeni 10 otomatik yedek tutulur. */
function auto_backup_daily(): void
{
    $dizin = backup_dir();
    $hedef = $dizin . '/otomatik-' . date('Y-m-d') . '.sqlite';
    if (is_file($hedef)) { return; }
    if (!backup_snapshot($hedef)) { return; }
    $eskiler = glob($dizin . '/otomatik-*.sqlite') ?: [];
    sort($eskiler); // ad = tarih → kronolojik
    foreach (array_slice($eskiler, 0, max(0, count($eskiler) - 10)) as $dosya) {
        @unlink($dosya);
    }
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

        // v8 — oturum ve şablon oturumlarına haftanın protokolü alanı
        8 => "
            ALTER TABLE oturumlar ADD COLUMN protokol TEXT NOT NULL DEFAULT '';
            ALTER TABLE sablon_oturumlari ADD COLUMN protokol TEXT NOT NULL DEFAULT '';
        ",

        // v9 — standart ölçüm işareti (sabit koşullarda alınan protokol sonucu)
        //      + şablon haftasına bağlı ev görevleri (uygulanınca otomatik ödev)
        9 => "
            ALTER TABLE protokol_sonuclari ADD COLUMN standart INTEGER NOT NULL DEFAULT 0;
            CREATE TABLE sablon_ev_gorevleri (
                sablon_id  INTEGER NOT NULL REFERENCES plan_sablonlari(id) ON DELETE CASCADE,
                hafta_no   INTEGER NOT NULL,
                calisma_id INTEGER NOT NULL REFERENCES ev_calismalari(id) ON DELETE CASCADE,
                PRIMARY KEY (sablon_id, hafta_no, calisma_id)
            );
        ",

        // v10 — profesyonel metronom çalışma setleri ve otomatik çalışma günlüğü
        10 => "
            CREATE TABLE metronom_setleri (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                kullanici  TEXT    NOT NULL,
                ad         TEXT    NOT NULL,
                aciklama   TEXT    NOT NULL DEFAULT '',
                adimlar    TEXT    NOT NULL DEFAULT '[]',
                created_at TEXT    NOT NULL,
                updated_at TEXT    NOT NULL
            );
            CREATE INDEX ix_metronom_set_kullanici
                ON metronom_setleri(kullanici, updated_at DESC);

            CREATE TABLE metronom_calisma_kayitlari (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                kullanici  TEXT    NOT NULL,
                set_id     INTEGER REFERENCES metronom_setleri(id) ON DELETE SET NULL,
                tur        TEXT    NOT NULL CHECK (tur IN ('serbest','setlist')),
                baslik     TEXT    NOT NULL DEFAULT '',
                sure_sn    INTEGER NOT NULL CHECK (sure_sn BETWEEN 1 AND 43200),
                bpm_min    INTEGER,
                bpm_max    INTEGER,
                detay      TEXT    NOT NULL DEFAULT '{}',
                created_at TEXT    NOT NULL
            );
            CREATE INDEX ix_metronom_calisma_kullanici
                ON metronom_calisma_kayitlari(kullanici, created_at DESC);

            CREATE TABLE metronom_hedefleri (
                kullanici   TEXT PRIMARY KEY,
                gunluk_dk   INTEGER NOT NULL DEFAULT 20 CHECK (gunluk_dk BETWEEN 1 AND 480),
                haftalik_gun INTEGER NOT NULL DEFAULT 5 CHECK (haftalik_gun BETWEEN 1 AND 7),
                updated_at  TEXT NOT NULL
            );
        ",

        // v11 — uzman kontrollü iki el motor koordinasyon protokolleri
        11 => "
            CREATE TABLE motor_protokolleri (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                kullanici       TEXT    NOT NULL,
                ad              TEXT    NOT NULL,
                hedef           TEXT    NOT NULL DEFAULT '',
                desen           TEXT    NOT NULL DEFAULT 'donusumlu'
                                        CHECK (desen IN ('donusumlu','ikiserli','capraz','eszamanli','karma')),
                bpm             INTEGER NOT NULL DEFAULT 60 CHECK (bpm BETWEEN 30 AND 180),
                sure_sn         INTEGER NOT NULL DEFAULT 30 CHECK (sure_sn BETWEEN 15 AND 600),
                hazirlik_vurus  INTEGER NOT NULL DEFAULT 4 CHECK (hazirlik_vurus BETWEEN 2 AND 16),
                tolerans_ms     INTEGER NOT NULL DEFAULT 140 CHECK (tolerans_ms BETWEEN 60 AND 300),
                created_at      TEXT    NOT NULL,
                updated_at      TEXT    NOT NULL
            );
            CREATE INDEX ix_motor_protokol_kullanici
                ON motor_protokolleri(kullanici, updated_at DESC);

            CREATE TABLE motor_sonuclari (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                kullanici    TEXT    NOT NULL,
                protokol_id  INTEGER REFERENCES motor_protokolleri(id) ON DELETE SET NULL,
                ogrenci_id   INTEGER REFERENCES ogrenciler(id) ON DELETE SET NULL,
                durum        TEXT    NOT NULL DEFAULT 'tamamlandi'
                                     CHECK (durum IN ('tamamlandi','erken_durduruldu','guvenlik')),
                skor         INTEGER,
                dogruluk     INTEGER,
                detay        TEXT    NOT NULL DEFAULT '{}',
                notlar       TEXT    NOT NULL DEFAULT '',
                created_at   TEXT    NOT NULL
            );
            CREATE INDEX ix_motor_sonuc_kullanici
                ON motor_sonuclari(kullanici, created_at DESC);
            CREATE INDEX ix_motor_sonuc_ogrenci
                ON motor_sonuclari(ogrenci_id, created_at DESC);
        ",

        // v12 — bir katılımcının birden fazla özel/grup dersine üyeliği.
        // Eski ogrenciler.grup_id alanı uyumluluk için korunur ve ilk aktif üyeliği gösterir.
        12 => "
            ALTER TABLE gruplar ADD COLUMN tur TEXT NOT NULL DEFAULT 'grup'
                CHECK (tur IN ('grup','ozel'));

            CREATE TABLE grup_uyelikleri (
                grup_id          INTEGER NOT NULL REFERENCES gruplar(id) ON DELETE CASCADE,
                ogrenci_id       INTEGER NOT NULL REFERENCES ogrenciler(id) ON DELETE CASCADE,
                aktif            INTEGER NOT NULL DEFAULT 1,
                baslangic_tarihi TEXT    NOT NULL,
                bitis_tarihi     TEXT,
                created_at       TEXT    NOT NULL,
                PRIMARY KEY (grup_id, ogrenci_id)
            );
            CREATE INDEX ix_grup_uyelik_ogrenci
                ON grup_uyelikleri(ogrenci_id, aktif);
            CREATE INDEX ix_grup_uyelik_grup
                ON grup_uyelikleri(grup_id, aktif);

            INSERT OR IGNORE INTO grup_uyelikleri
                (grup_id, ogrenci_id, aktif, baslangic_tarihi, bitis_tarihi, created_at)
            SELECT grup_id, id, 1, kayit_tarihi, NULL, kayit_tarihi
              FROM ogrenciler
             WHERE grup_id IS NOT NULL;
        ",

        // v13 — ders/grup duyuruları. Katılımcı portalı yalnız yayın aralığındaki
        // aktif duyuruları gösterir; kişisel öğrenci notlarıyla hiçbir bağı yoktur.
        13 => "
            CREATE TABLE grup_duyurulari (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                grup_id       INTEGER NOT NULL REFERENCES gruplar(id) ON DELETE CASCADE,
                baslik        TEXT    NOT NULL,
                mesaj         TEXT    NOT NULL DEFAULT '',
                yayin_tarihi  TEXT    NOT NULL,
                bitis_tarihi  TEXT,
                aktif         INTEGER NOT NULL DEFAULT 1,
                created_at    TEXT    NOT NULL
            );
            CREATE INDEX ix_grup_duyuru_gorunum
                ON grup_duyurulari(grup_id, aktif, yayin_tarihi, bitis_tarihi);
        ",

        // v14 — ölçüm kalitesi: asenkroni standart sapması (kararlılık) ve
        //       ölçüm anındaki kalibrasyon kalitesi. Skor tek başına sabit
        //       kayma ile kararlılığı karıştırır; trend SD'den okunmalıdır.
        14 => "
            ALTER TABLE protokol_sonuclari ADD COLUMN sd_ms INTEGER;
            ALTER TABLE protokol_sonuclari ADD COLUMN kalite TEXT NOT NULL DEFAULT '';
        ",

        // v15 — tanıtım sitesindeki genel iletişim formundan gelen talepler.
        //        Kişisel veri içerir: yalnız geri dönüş için tutulur, dışa aktarılmaz.
        15 => "
            CREATE TABLE on_kayitlar (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                ad          TEXT    NOT NULL,
                iletisim    TEXT    NOT NULL,
                kitle       TEXT    NOT NULL DEFAULT 'belirtilmedi',
                mesaj       TEXT    NOT NULL DEFAULT '',
                profil      TEXT    NOT NULL DEFAULT '',
                durum       TEXT    NOT NULL DEFAULT 'yeni'
                            CHECK (durum IN ('yeni','arandi','kayit','vazgecti')),
                created_at  TEXT    NOT NULL
            );
            CREATE INDEX ix_on_kayit_durum ON on_kayitlar(durum, created_at);
        ",

        // v16 — ölçüm VARYANTI. Aynı protokolün karşılaştırılamaz koşulları olur:
        //        poliritimde 3:2 ile 7:4 aynı beceri değil, aksak usulde 9/8 ile
        //        4/4 aynı zorluk değil. Varyant boş kalırsa davranış eskisi gibi;
        //        doluysa trend ve karşılaştırma AYRI seri olarak okunur. Bu ayrım
        //        sonradan yapılamaz (kayıtlar karışınca geri alınamaz), o yüzden
        //        varyantlı ilk protokolle birlikte açılıyor.
        16 => "
            ALTER TABLE protokol_sonuclari ADD COLUMN varyant TEXT NOT NULL DEFAULT '';
        ",

        // v17 — SUNUCU TARAFLI HIZ SINIRI.
        //   Deneme sayacı $_SESSION'daydı; çerez göndermeyen bir istemci HER
        //   istekte sıfır sayaçla başlıyordu, yani panel şifresine sınırsız kaba
        //   kuvvet mümkündü. İnternete açılan kurulumda bu doğrudan bir açık.
        //   Sayaç artık istemcinin silemeyeceği bir yerde: veritabanında.
        //   anahtar = 'giris:<ip>' | 'evkod:<ip>' gibi; pencere bazlı sayım.
        17 => "
            CREATE TABLE hiz_siniri (
                anahtar     TEXT    NOT NULL,
                pencere     INTEGER NOT NULL,   -- unix zaman / pencere boyu
                adet        INTEGER NOT NULL DEFAULT 0,
                son_deneme  INTEGER NOT NULL,
                PRIMARY KEY (anahtar, pencere)
            );
            CREATE INDEX ix_hiz_siniri_temizlik ON hiz_siniri(son_deneme);
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
