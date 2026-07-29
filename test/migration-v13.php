<?php
/** v11 tek-grup verisinin güncel v13 şemasına kayıpsız taşınma testi. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$temp = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'ritim-migration-' . bin2hex(random_bytes(4));
if (!mkdir($temp, 0755, true)) { throw new RuntimeException('Geçici dizin açılamadı.'); }

register_shutdown_function(static function () use ($temp): void {
    foreach (glob($temp . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file)) { @unlink($file); }
    }
    @rmdir($temp);
});

define('RITIM', 1);
define('STORAGE_DIR', $temp);
require dirname(__DIR__) . '/includes/helpers.php';
require dirname(__DIR__) . '/includes/db.php';

$pdo = db();
$pdo->exec("
    CREATE TABLE gruplar (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ad TEXT NOT NULL, yas_araligi TEXT NOT NULL DEFAULT '',
        gun INTEGER NOT NULL DEFAULT 1, saat TEXT NOT NULL DEFAULT '', aktif INTEGER NOT NULL DEFAULT 1,
        baslangic_tarihi TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL
    );
    CREATE TABLE ogrenciler (
        id INTEGER PRIMARY KEY AUTOINCREMENT, kod TEXT NOT NULL UNIQUE, dogum_yili INTEGER,
        grup_id INTEGER REFERENCES gruplar(id) ON DELETE SET NULL, veli_notu TEXT NOT NULL DEFAULT '',
        aktif INTEGER NOT NULL DEFAULT 1, kayit_tarihi TEXT NOT NULL
    );
    -- v2'den beri var olan tablo: sonraki göçler (v14) bunu genişletir
    CREATE TABLE protokol_sonuclari (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ogrenci_id INTEGER NOT NULL REFERENCES ogrenciler(id) ON DELETE CASCADE,
        protokol TEXT NOT NULL, bpm INTEGER, skor INTEGER,
        detay TEXT NOT NULL DEFAULT '{}', notlar TEXT NOT NULL DEFAULT '',
        kaynak TEXT NOT NULL DEFAULT 'atolye', standart INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    );
    PRAGMA user_version = 11;
    INSERT INTO gruplar (ad, gun, created_at) VALUES ('Eski Grup', 2, '2026-01-01 10:00:00');
    INSERT INTO ogrenciler (kod, grup_id, kayit_tarihi)
        VALUES ('ESKI-UYE', 1, '2026-01-02');
");

run_migrations();

$version = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
$membership = $pdo->query("SELECT gu.aktif, gu.baslangic_tarihi, g.tur
    FROM grup_uyelikleri gu
    JOIN ogrenciler o ON o.id = gu.ogrenci_id
    JOIN gruplar g ON g.id = gu.grup_id
    WHERE o.kod = 'ESKI-UYE'")->fetch();
$announcementTable = (string)$pdo->query(
    "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'grup_duyurulari'"
)->fetchColumn();

// v14 sütunları: ölçüm kararlılığı ve kalibrasyon kalitesi
$sutunlar = array_column($pdo->query("PRAGMA table_info(protokol_sonuclari)")->fetchAll(), 'name');

// Sürüm eşitliği yerine "en az" denetimi: yeni göçler bu testi kırmasın
if ($version < 13
    || !$membership
    || (int)$membership['aktif'] !== 1
    || $membership['baslangic_tarihi'] !== '2026-01-02'
    || $membership['tur'] !== 'grup'
    || $announcementTable !== 'grup_duyurulari'
    || !in_array('sd_ms', $sutunlar, true)
    || !in_array('kalite', $sutunlar, true)) {
    fwrite(STDERR, "Göç testi başarısız (sürüm={$version}).\n");
    exit(1);
}

echo "Göç testi geçti (v11 → v{$version}): eski üyelik korundu, duyuru şeması ve ölçüm sütunları eklendi.\n";
