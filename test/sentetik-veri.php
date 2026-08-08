<?php
/**
 * SENTETİK VERİ ÜRETİCİ — gerçekçi, deterministik atölye verisi.
 *
 * Ne yapar: alan tablolarını (grup/öğrenci/oturum/yoklama/protokol/ödev/
 * paket/ön kayıt) SIFIRLAR ve modelin KENDİ kayıt fonksiyonlarıyla yeniden
 * doldurur — yani doğrulama katmanından geçerek. Site içeriği (CMS metinleri,
 * tema, teknikler, şablonlar, ev çalışmaları) korunur; onlar tohum içeriktir.
 *
 * Neden model fonksiyonları: elle INSERT şemayı doldurur ama doğrulamayı
 * sınamaz. group_save/student_save/session_save_plan/protocol_result_save
 * kullanınca üretim, uygulamanın kendi yolunu da test etmiş olur.
 *
 * Determinizm: mt_srand sabit — her koşuda AYNI veri çıkar; tutarlılık
 * testleri (test/sentetik.test.php) beklentilerini buna göre kurar.
 *
 * Çalıştırma (yıkıcı olduğu için açık onay ister):
 *   php test/sentetik-veri.php --onay
 *   RITIM_STORAGE=<dizin> php test/sentetik-veri.php --onay   (izole depo)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit("Yalnız komut satırından çalışır.\n"); }
if (!in_array('--onay', $argv ?? [], true) && getenv('RITIM_SENTETIK_ONAY') !== '1') {
    exit("Bu araç grup/öğrenci/oturum/protokol verilerini SIFIRLAR.\n"
       . "Bilerek çalıştırıyorsanız: php test/sentetik-veri.php --onay\n");
}

/* Bootstrap'taki merkezî kimlik kapısı SCRIPT_NAME'e bakar; komut satırında
   herkese açık bir sayfa gibi görünerek yönlendirme-exit'i atlatıyoruz.
   (Oturum/kimlik burada anlamsız — dosya sistemine zaten sahibiz.) */
$_SERVER['SCRIPT_NAME'] = '/index.php';
define('RITIM', 1);
require dirname(__DIR__) . '/includes/bootstrap.php';

mt_srand(20260808);
$pdo = db();

/* =================================================================
   0) ALAN TABLOLARINI SIFIRLA (FK sırasına göre; tohum içerik kalır)
   ================================================================= */
echo "— Alan tabloları sıfırlanıyor —\n";
foreach (['katilim', 'oturum_teknikleri', 'oturumlar', 'ev_tamamlama',
          'ev_odevleri', 'paketler', 'protokol_sonuclari', 'hiz_siniri',
          'grup_uyelikleri', 'ogrenciler', 'gruplar', 'on_kayitlar'] as $t) {
    $pdo->exec("DELETE FROM {$t}");
}
echo "  temizlendi.\n";

/** Bugünden N hafta önceki Pazartesi'ye göre gün hesabı. */
function hafta_once(int $hafta, int $gunOfset = 0): string
{
    return now()->modify('monday this week')
                ->modify('-' . ($hafta * 7 - $gunOfset) . ' days')->format('Y-m-d');
}
function sec(array $a) { return $a[mt_rand(0, count($a) - 1)]; }

/* =================================================================
   1) GRUPLAR
   ================================================================= */
echo "— Gruplar —\n";
$g = [];
foreach ([
    ['ad' => 'Çocuk A — Çarşamba', 'yas' => '8–11',  'gun' => 3, 'saat' => '17:00', 'tur' => 'grup',  'bas' => hafta_once(10, 2)],
    ['ad' => 'Çocuk B — Cumartesi', 'yas' => '12–15', 'gun' => 6, 'saat' => '10:00', 'tur' => 'grup',  'bas' => hafta_once(8, 5)],
    ['ad' => 'Yetişkin — Salı',     'yas' => '18+',   'gun' => 2, 'saat' => '19:30', 'tur' => 'grup',  'bas' => hafta_once(8, 1)],
    ['ad' => 'Özel Ders — Perşembe', 'yas' => '18+',  'gun' => 4, 'saat' => '16:00', 'tur' => 'ozel',  'bas' => hafta_once(6, 3)],
] as $i => $t) {
    $r = group_save(['ad' => $t['ad'], 'yas_araligi' => $t['yas'], 'gun' => $t['gun'],
                     'saat' => $t['saat'], 'tur' => $t['tur'], 'aktif' => 1,
                     'baslangic_tarihi' => $t['bas']], null);
    if (!$r['ok']) { exit("GRUP HATASI: {$r['error']}\n"); }
    $g[$i + 1] = (int)$r['id'];
    echo "  + {$t['ad']} (#{$r['id']}, başlangıç {$t['bas']})\n";
}

/* =================================================================
   2) ÖĞRENCİLER — takma kod, karışık doğum yılları, çoklu üyelik
   ================================================================= */
echo "— Öğrenciler —\n";
$ogrTanim = [
    // [kod, dogum, grup, veli notu]
    ['SERÇE-01',    2016, $g[1], 'Dersten sonra servisle dönüyor.'],
    ['KARTAL-02',   2015, $g[1], ''],
    ['MARTI-03',    2017, $g[1], 'Su şişesini unutabiliyor, hatırlatmak yeterli.'],
    ['DOĞAN-04',    2014, $g[1], ''],
    ['ATMACA-05',   2013, $g[2], ''],
    ['TURNA-06',    2012, $g[2], 'Kardeşi de kayıt olmak istiyor, veli soracak.'],
    ['LEYLEK-07',   2013, $g[2], ''],
    ['BAYKUŞ-08',   1996, $g[3], ''],
    ['ŞAHİN-09',    1989, $g[3], ''],
    ['PELİKAN-10',  2001, $g[3], ''],
    ['FLAMİNGO-11', 1993, $g[4], 'Perşembe saatini bazen cumaya kaydırmak istiyor.'],
    ['KIRLANGIÇ-12', 2015, null, ''],   // henüz gruba yerleşmemiş
];
$o = [];
foreach ($ogrTanim as [$kod, $dy, $gid, $not]) {
    $r = student_save(['kod' => $kod, 'dogum_yili' => (string)$dy,
                       'grup_id' => $gid, 'veli_notu' => $not, 'aktif' => 1], null);
    if (!$r['ok']) { exit("ÖĞRENCİ HATASI ({$kod}): {$r['error']}\n"); }
    $o[$kod] = (int)$r['id'];
}
/* Çoklu üyelik: iki çocuk hem A hem B grubunda; bir yetişkin özel derste de */
group_member_add($g[2], $o['KARTAL-02']);
group_member_add($g[1], $o['ATMACA-05']);
group_member_add($g[4], $o['BAYKUŞ-08']);
/* Bir öğrenci pasife alınmış (ayrılan katılımcı yolu) */
student_save(['kod' => 'LEYLEK-07', 'dogum_yili' => '2013', 'veli_notu' => '', 'aktif' => 0], $o['LEYLEK-07']);
ensure_student_codes();
echo '  + ' . count($o) . " öğrenci (1 pasif, 3 çoklu üyelik, 1 grupsuz)\n";

/* =================================================================
   3) OTURUMLAR — iki grup şablondan, ikisi elle
   ================================================================= */
echo "— Oturumlar —\n";
$s1 = template_apply(1, $g[1], hafta_once(10, 2), 'AB', 3);   // Çocuk A: 10 hafta önce, haftada 2
$s2 = template_apply(2, $g[3], hafta_once(8, 1), 'A', 3);     // Yetişkin: 8 hafta önce, haftada 1
echo "  şablondan: Çocuk A {$s1['olusan']} oturum + {$s1['odev']} ödev · Yetişkin {$s2['olusan']} oturum + {$s2['odev']} ödev\n";

$teknikIds = array_map(fn($t) => (int)$t['id'],
    db()->query('SELECT id FROM teknikler WHERE aktif = 1 ORDER BY id')->fetchAll());
$elle = 0;
foreach ([[$g[2], 8, 5], [$g[4], 6, 3]] as [$gid, $haftaGeri, $gunOfset]) {
    for ($h = 0; $h < $haftaGeri; $h++) {
        $tarih = hafta_once($haftaGeri - $h, $gunOfset);
        $sayi = mt_rand(3, 4);
        $items = [];
        $baslangicIdx = mt_rand(0, max(0, count($teknikIds) - $sayi - 1));
        for ($k = 0; $k < $sayi; $k++) {
            $items[] = ['teknik_id' => $teknikIds[$baslangicIdx + $k], 'sure_dk' => sec([10, 12, 15, 20])];
        }
        $r = session_save_plan(['grup_id' => $gid, 'tarih' => $tarih,
            'notlar' => 'Hafta ' . ($h + 1) . ' — akış elle planlandı.'], $items, null);
        if ($r['ok']) { $elle++; }
    }
}
echo "  elle: {$elle} oturum\n";

/* =================================================================
   4) YOKLAMA + GÖZLEM — geçmiş oturumlara; İKİ oturum bilerek boş
   (panelin "bekleyen yoklama" uyarısı sınanabilsin)
   ================================================================= */
echo "— Yoklama —\n";
/* Gözlemler §2 kilitli dile uygun: yalnız NE YAPILDIĞI, iddiasız. */
$gozlemler = [
    'Tempoya ikinci turda oturdu, kalıbı sonuna dek sürdürdü.',
    'Dur işaretinde ilk denemede durdu; devamda bir vuruş erken girdi.',
    'Çağrı–cevapta dört kalıbın üçünü ilk dinleyişte tekrarladı.',
    'Sessiz fazda sayarak sürdürdü, girişleri yerindeydi.',
    'Kendi partisini korurken diğer grubu izledi.',
    'Vücut perküsyonunda el–diz geçişlerini yavaş tempoda tamamladı.',
    'Soloda iki ölçülük kalıp kurdu, sırayı arkadaşına işaretle devretti.',
    'Hız değişiminde yavaşlamayı takip etti, hızlanmada bir tur bekledi.',
    'Bugün ısınma turunda söz aldı, kalıbı kendisi seçti.',
    'Aksan vuruşlarını yüksek sesle sayarak yerleştirdi.',
];
$uygNotlari = ['Tempo 76 BPM ile başlandı.', 'Kalıp iki kez kısaltılarak tekrar edildi.',
               'Eşli çalışıldı.', 'Son tur sessiz sürdürüldü.', ''];
$oturumlar = db()->query('SELECT id, grup_id, tarih FROM oturumlar ORDER BY tarih')->fetchAll();
$bugun = today();
$gecmis = array_values(array_filter($oturumlar, fn($s) => $s['tarih'] <= $bugun));
$bosBirak = array_slice(array_map(fn($s) => (int)$s['id'], array_slice($gecmis, -4)), 0, 2);
$yoklamaSatiri = 0;
foreach ($gecmis as $s) {
    if (in_array((int)$s['id'], $bosBirak, true)) { continue; }
    $uyeler = students_list((int)$s['grup_id'], 1);
    if (!$uyeler) { continue; }
    $yok = [];
    foreach ($uyeler as $u) {
        $zar = mt_rand(1, 100);
        $durum = $zar <= 78 ? 'katildi' : ($zar <= 88 ? 'gec' : 'gelmedi');
        $yok[(int)$u['id']] = ['durum' => $durum,
            'gozlem_notu' => ($durum !== 'gelmedi' && mt_rand(1, 100) <= 40) ? sec($gozlemler) : ''];
        $yoklamaSatiri++;
    }
    $tek = [];
    $st = db()->prepare('SELECT teknik_id FROM oturum_teknikleri WHERE oturum_id = ?');
    $st->execute([(int)$s['id']]);
    foreach ($st->fetchAll() as $tRow) {
        $tek[(int)$tRow['teknik_id']] = ['islendi' => mt_rand(1, 100) <= 90 ? 1 : 0,
                                         'uygulama_notu' => sec($uygNotlari)];
    }
    session_record((int)$s['id'], $yok, $tek, mt_rand(1, 100) <= 30 ? 'Salon bugün kalabalıktı; düzen iki halka olarak kuruldu.' : '');
}
echo "  {$yoklamaSatiri} yoklama satırı · " . count($bosBirak) . " oturum bilerek yoklamasız bırakıldı\n";

/* =================================================================
   5) PROTOKOL SONUÇLARI — haftalara yayılmış seriler
   ================================================================= */
echo "— Protokol sonuçları —\n";
/**
 * Seriyi model yoluyla kaydeder, sonra created_at'i geriye tarihler
 * (fonksiyon her zaman şimdiyi basar; trend için geçmiş gerekli).
 */
function seri(int $ogrId, string $protokol, array $noktalar, array $ek = []): void
{
    $guncelle = db()->prepare('UPDATE protokol_sonuclari SET created_at = ? WHERE id = ?');
    foreach ($noktalar as [$gunOnce, $skor, $sd]) {
        $r = protocol_result_save(array_merge([
            'ogrenci_id' => $ogrId, 'protokol' => $protokol, 'skor' => $skor,
            'bpm' => $ek['bpm'] ?? 72, 'detay' => '{}',
            'sd_ms' => $sd, 'kalite' => $sd !== null ? 'iyi' : '',
            'standart' => $ek['standart'] ?? 1,
            'kaynak' => (mt_rand(1, 100) <= 25 && ($ek['ev_olabilir'] ?? true)) ? 'ev' : 'atolye',
            'varyant' => $ek['varyant'] ?? '',
        ], []));
        if (!$r['ok']) { exit("PROTOKOL HATASI: {$r['error']}\n"); }
        $guncelle->execute([now()->modify("-{$gunOnce} days")->format('Y-m-d') . ' '
            . sprintf('%02d:%02d:00', mt_rand(16, 20), mt_rand(0, 59)), $r['id']]);
    }
}
/* Belirgin gelişen seri (bandı aşmalı) */
seri($o['SERÇE-01'], 'vurus_tutturma',
    [[63, 52, 96], [56, 55, 92], [49, 58, 88], [42, 63, 80], [35, 66, 74], [21, 71, 68], [14, 74, 62], [7, 78, 57]]);
/* Gürültülü seri (band "ayırt edilemiyor" demeli) */
seri($o['KARTAL-02'], 'vurus_tutturma',
    [[56, 61, 85], [42, 54, 95], [35, 67, 78], [28, 57, 90], [14, 63, 84], [7, 60, 88]]);
/* Az veri (karar için 3 ölçüm gerekir yolu) */
seri($o['MARTI-03'], 'vurus_tutturma', [[21, 58, 90], [7, 64, 82]]);
seri($o['SERÇE-01'], 'ritim_okuma',
    [[49, 60, 70], [35, 66, 64], [21, 72, 58], [7, 79, 52]]);
seri($o['DOĞAN-04'], 'bpm_bulma',
    [[42, 48, null], [28, 56, null], [14, 60, null], [7, 68, null]], ['bpm' => 0]);
seri($o['BAYKUŞ-08'], 'spontan_tempo',
    [[42, 55, null], [28, 62, null], [14, 66, null], [7, 71, null]], ['bpm' => 0, 'standart' => 0]);
seri($o['ŞAHİN-09'], 'aksak_bulma',
    [[42, 0, null], [28, 25, null], [14, 50, null], [7, 50, null]], ['bpm' => 100, 'ev_olabilir' => false]);
seri($o['BAYKUŞ-08'], 'icsel_ritim',
    [[35, 58, 88], [28, 61, 84], [21, 65, 79], [14, 63, 81], [7, 70, 72]]);
/* Poliritim: iki AYRI varyant — seriler karışmamalı */
seri($o['PELİKAN-10'], 'poliritim',
    [[28, 44, null], [21, 52, null], [14, 58, null], [7, 63, null]], ['varyant' => '3:2', 'ev_olabilir' => false]);
seri($o['PELİKAN-10'], 'poliritim',
    [[18, 31, null], [9, 40, null], [4, 46, null]], ['varyant' => '4:3', 'ev_olabilir' => false]);
$toplamProtokol = (int)db()->query('SELECT COUNT(*) FROM protokol_sonuclari')->fetchColumn();
echo "  {$toplamProtokol} ölçüm (7 protokol, 2 poliritim varyantı)\n";

/* =================================================================
   6) EV ÖDEVLERİ + TAMAMLAMA — şablon ödevlerinin bir kısmı işaretli
   ================================================================= */
echo "— Ev çalışmaları —\n";
$odevler = db()->query('SELECT id, ogrenci_id, baslangic, bitis FROM ev_odevleri ORDER BY id')->fetchAll();
$isaret = 0;
foreach ($odevler as $od) {
    if ($od['bitis'] > $bugun) { continue; }             // gelecek haftalar boş kalsın
    if (mt_rand(1, 100) > 70) { continue; }              // bazı haftalar hiç yapılmamış
    $gunSayisi = mt_rand(2, 5);
    $bas = new DateTime($od['baslangic']);
    for ($gi = 0; $gi < $gunSayisi; $gi++) {
        $t = (clone $bas)->modify('+' . mt_rand(0, 6) . ' days')->format('Y-m-d');
        if ($t > $bugun) { continue; }
        completion_mark((int)$od['id'], $t, []);
        $isaret++;
    }
}
/* Elle ek ödev: Çocuk B'ye etkileşimli modül (bu hafta, kısmen yapılmış) */
$buPzt = now()->modify('monday this week')->format('Y-m-d');
$buPaz = now()->modify('monday this week')->modify('+6 days')->format('Y-m-d');
assignment_create([$o['ATMACA-05'], $o['TURNA-06']], 2, $buPzt, $buPaz, 5, 'Bu haftanın ev mini testi.');
echo '  ' . count($odevler) . " ödev · {$isaret} gün işaretlendi\n";

/* =================================================================
   7) PAKET + ÖN KAYIT
   ================================================================= */
package_create($o['FLAMİNGO-11'], 'Başlangıç Paketi (5 seans)', 5, hafta_once(6, 3), '');
package_create($o['BAYKUŞ-08'], 'Dönem Paketi (16 seans)', 16, hafta_once(8, 1), '');
foreach ([
    ['Deniz Y.', '05xx 111 22 33', 'cocuk', 'Çarşamba grubu için yaş uygun mu?'],
    ['Mert K.', 'mert@example.com', 'yetiskin', 'Akşam saatleri var mı?'],
    ['Elif A.', 'elif@example.com', 'cocuk', ''],
] as $i => [$ad, $iletisim, $kitle, $mesaj]) {
    $r = pre_registration_save(['ad' => $ad, 'iletisim' => $iletisim, 'kitle' => $kitle,
                                'mesaj' => $mesaj, 'ders_turu' => 'grup']);
    if (!$r['ok']) { echo "  ! ön kayıt atlandı: {$r['error']}\n"; }
}
$sonKayitlar = db()->query('SELECT id FROM on_kayitlar ORDER BY id')->fetchAll();
if (isset($sonKayitlar[1])) { pre_registration_set_status((int)$sonKayitlar[1]['id'], 'arandi'); }
echo "  2 paket · " . count($sonKayitlar) . " iletişim talebi\n";

/* =================================================================
   ÖZET
   ================================================================= */
echo "\n================= ÖZET =================\n";
foreach (['gruplar', 'ogrenciler', 'grup_uyelikleri', 'oturumlar', 'katilim',
          'protokol_sonuclari', 'ev_odevleri', 'ev_tamamlama', 'paketler', 'on_kayitlar'] as $t) {
    printf("  %-20s %d\n", $t, (int)db()->query("SELECT COUNT(*) FROM {$t}")->fetchColumn());
}
echo "\nEv portalı erişim kodları (ev.php):\n";
foreach (db()->query('SELECT kod, erisim_kodu FROM ogrenciler WHERE aktif = 1 ORDER BY kod LIMIT 5') as $r) {
    printf("  %-14s %s\n", $r['kod'], $r['erisim_kodu']);
}
echo "========================================\n";
