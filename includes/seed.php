<?php
if (!defined('RITIM')) { http_response_code(403); exit; }

/**
 * Teknik kütüphanesi başlangıç içeriği. Tekrar çalıştırılabilir: teknik adı
 * benzersizdir, var olan kayıt asla ezilmez (eğitmenin düzenlemeleri korunur).
 *
 * Kanıt düzeyleri gerçekçi girilmiştir ve YÜKSELTİLMEYECEK (CLAUDE.md §5).
 * "guclu" yalnız metronoma eşlik ailesinde: orada ölçülen şey doğrudan
 * senkronizasyon becerisinin kendisi. Diğerlerinde iddia edilen transfer
 * etkisi literatürde umut verici ama kesin değil. "yok" etiketi de meşru.
 */
function seed_techniques(): void
{
    $pdo = db();
    // Yalnız kütüphane TAMAMEN boşken çalışır: eğitmenin sildiği/düzenlediği
    // kayıtlar asla geri getirilmez, uygulama hiçbir zaman boş kurulmaz.
    if ((int)$pdo->query('SELECT COUNT(*) FROM teknikler')->fetchColumn() > 0) {
        return;
    }

    $satirlar = [
        // [ad, kategori, enstruman, seviye, sure_dk, aciklama, hedef_beceri, kanit, kaynak, malzeme]
        ['Metronoma eşlik', 'Metronoma eşlik', 'Davul / pad', 1, 10,
         'Sabit tempoda metronomla birlikte vuruş. Tempo oturunca kademeli olarak değiştirilir (bir seferde 4–6 BPM). Acele değil, doğruluk önceliklidir.',
         'Senkronizasyon, zamanlama', 'guclu',
         'Ölçülen şey doğrudan senkronizasyon becerisinin kendisi; transfer iddiası YOK. Puyjarinet et al. 2017 (Sci. Reports) '
         . 'becerinin ölçülebilirliğini gösterir (kesitsel; antrenman çalışması değildir). Shaffer et al. 2001 tarihsel bir örnektir, '
         . 'randomize değildir, dayanak sayılmaz.',
         'Metronom, el davulu veya pad'],

        ['Nabzı Bul', 'Metronoma eşlik', 'Davul / pad', 1, 8,
         'Eğitmen 8 vuruş çalar, katılımcı yalnız dinler. Katılımcı 16 vuruş eşlik eder; metronom 8 vuruş susturulur. Metronom döndüğünde sapma fark edilir ve hızlanmadan yeniden eşleşilir. İlerletme: önce sessiz süre uzatılır, sonra tempo 4–6 BPM değiştirilir.',
         'Dışsal vuruşa eşzamanlanma, tempoyu sürdürme', 'guclu',
         'RitimOdak kılavuzu Etkinlik 1; Puyjarinet et al. 2017 (Sci. Reports)',
         'Metronom, davul/pad; küçük yaş için görsel ayak izi kartı'],

        ['Dur–devam', 'Dur–devam', 'Davul / pad', 1, 8,
         'Grup sabit vuruşta çalarken eğitmenin işaretiyle aniden susulur, ikinci işaretle devam edilir. İşaret görsel (kart) veya işitsel olabilir.',
         'Dürtü kontrolü, tepki durdurma', 'orta',
         'Müzik eğitimi–inhibisyon literatürü umut verici ama kesin değil: Jamey et al. 2024 (Cognition)',
         'Davul/pad, işaret kartı'],

        ['Bekle–Dinle–Vur', 'Dur–devam', 'Davul / pad', 2, 10,
         'Mavi kartta duyulan kalıp hemen kopyalanır; sarı kartta kalıp bittikten iki sessiz vuruş sonra cevap verilir; kırmızı kartta hiç cevap verilmez. İlerletme: kart sayısı veya gecikme artırılır — ikisi aynı anda değil.',
         'Tepki erteleme, dürtü kontrolü', 'orta',
         'RitimOdak kılavuzu Etkinlik 2',
         'İki–üç renk kart, davul/pad'],

        ['Hedef Tını', 'Dur–devam', 'Davul + zil', 2, 8,
         'Yalnız hedef tını duyulunca tek vuruş yapılır; hedef olmayan tınıya tepki verilmez. Temel performans oturunca düşük düzey görsel çeldirici eklenir. Kaçırma ve yanlış alarm ayrı gözlenir.',
         'Seçici dikkat, tepki tutma', 'zayif',
         'RitimOdak kılavuzu Etkinlik 4. Dış künye yok; tek yakın kaynak Erarslan 2026 ve orada '
         . 'yürütücü işlev ölçümü null (p=0,122). Seçici dikkat transferine doğrudan kanıt bulunamadı.',
         'İki farklı tını (ör. davul + zil), çeldirici kartı'],

        ['Hata Sonrası Dönüş', 'Dur–devam', 'Davul / pad', 2, 8,
         'Eğitmen bilerek küçük bir hata yapar ve "dur–nefes–kural–dön" modelini gösterir. Katılımcı aynı döngüde hata sonrası dört vuruş içinde ritme geri döner. Hata sayısı değil, dönüş kalitesi övülür.',
         'Hata fark etme, yeniden başlama, öz-düzenleme', 'zayif',
         'RitimOdak kılavuzu Etkinlik 8; pedagojik gelenek',
         'Davul/pad, kısa ritim döngüsü'],

        ['Çağrı–cevap', 'Çağrı–cevap', 'Davul / pad', 1, 10,
         'Eğitmen 2–4 vuruşluk bir kalıp çalar, grup aynen tekrarlar. Kalıplar kademeli uzar ve aksan yerleri değişir.',
         'İşitsel bellek, sıralama', 'zayif',
         'Tek dayanak Frischen et al. 2019; orada bellek ölçümleri NULL (Matrix Span p=0,11; Corsi p=0,14) ve '
         . 'ölçülen şey görsel-uzamsal, işitsel değil.',
         'Davul/pad'],

        ['Ritmik dizi tekrarı', 'Ritmik dizi tekrarı', 'Davul / pad', 2, 10,
         'Uzayan kalıpların ezberden tekrarı: 2 vuruşla başlanır, her turda uzatılır. Diziyi uzatmadan önce en az iki başarılı tekrar beklenir.',
         'Çalışma belleği', 'zayif',
         'Bentley et al. 2023 çalışma belleği çıktısı RAPORLAMADI (anlamlı çıktılar öz-düzenleme/inhibisyon/okula hazırlık); '
         . 'Frischen 2019 bellek ölçümlerinde null.',
         'Davul/pad'],

        ['Ritim Hafıza Zinciri', 'Ritmik dizi tekrarı', 'Davul / pad', 2, 12,
         'Eğitmen iki birimli bir dizi çalar; katılımcı kopyalar ve bir birim ekler; dizi grup içinde dolaşır. Hata olduğunda suçlama yerine son doğru noktaya dönülür. İlerletme: uzunluk, aksan veya ters sıradan yalnız biri değiştirilir.',
         'Çalışma belleği, sıralama', 'zayif',
         'RitimOdak kılavuzu Etkinlik 3. Dış akademik künye yok; bağlı çalışmalarda ilgili çıktı null. '
         . 'Grup-halkası formatı hiçbir yayında sınanmadı.',
         'Davul/pad, sembol kartları'],

        ['Tempo değişimi takibi', 'Tempo değişimi takibi', 'Davul / pad', 2, 8,
         'Hızlanma/yavaşlama ve ani tempo geçişleri metronom veya eğitmen önderliğinde takip edilir. Bir seferde yalnız küçük bir değişiklik yapılır.',
         'Esneklik, uyum', 'zayif',
         'Tek dayanak Zhu 2026; orada kontrol grubu pasif ve ~12 kat doz farkı var. '
         . 'Frischen 2019 set-shifting ölçümünde null (p=0,47).',
         'Metronom, davul/pad'],

        ['Kural Değiştir', 'Tempo değişimi takibi', 'İki vurma yüzeyi', 3, 10,
         'Mavi kartta sağ el, yeşil kartta sol el kuralı uygulanır; eğitmen işaretle kuralı tersine çevirir. Katılımcı hatada durur, kuralı söyler ve ilk vuruşta yeniden girer. İlerletme: geçiş sıklığı veya üçüncü kural — önce yalnız biri.',
         'Bilişsel esneklik, geçiş', 'zayif',
         'RitimOdak kılavuzu Etkinlik 5. Dış künye yok. Bilişsel esneklik, ritim literatüründe kanıtı en zayıf '
         . 'yürütücü işlev bileşeni; Frischen 2019 set-shifting p=0,47 null.',
         'Renk/şekil kartları, iki vurma yüzeyi'],

        ['Katmanlı grup ritmi', 'Katmanlı grup ritmi', 'Grup davulları', 3, 12,
         'Herkes farklı bir parti çalar; hedef, kendi partisini diğerlerine karışmadan korumaktır. Partiler basitten karmaşığa dağıtılır.',
         'Sürdürülen dikkat', 'zayif',
         'Transfer iddiası literatürde zayıf; pedagojik gelenek',
         'Grup davulları/padler'],

        ['Çift Hat', 'Katmanlı grup ritmi', 'Davul / pad', 3, 10,
         'Katılımcı sabit ritmi sürdürürken her dört vuruşta bir kategori örneği söyler veya görsel hedef sayar. Görev bozulursa ana görev önceliği yeniden seçilir. Ritim hatası ve ikincil görev hatası ayrı gözlenir.',
         'Bölünmüş dikkat, önceliklendirme', 'zayif',
         'RitimOdak kılavuzu Etkinlik 6',
         'Davul/pad, kategori kartları'],

        ['Daire Senkroni', 'Katmanlı grup ritmi', 'Grup davulları', 2, 12,
         'Grup ortak nabzı kurar; her kişi yalnız kendi 4 vuruşluk penceresinde çalar. Lider sessiz işaretle giriş/çıkış verir; grup konuşmadan uyum sağlar. İlerletme: pencere kısalır, kanon veya lider değişimi eklenir.',
         'Ortak dikkat, sıra alma, sosyal zamanlama', 'zayif',
         'RitimOdak kılavuzu Etkinlik 7',
         'Grup davulları/padler, sıra kartı'],

        ['Vücut perküsyonu', 'Vücut perküsyonu', 'Beden', 1, 8,
         'El–ayak–göğüs kombinasyonları; tek elden çapraz beden düzenlerine kademeli geçiş. Ağrı veya aşırı efor olmadan çalışılır.',
         'Motor koordinasyon', 'orta',
         'Yakın-alan kazanımı: çalışılan şey koordinasyonun kendisidir. Pedagojik gelenek. '
         . 'Genel motor yetiye kalıcı transfer iddia edilmez.',
         'Malzeme gerekmez'],

        ['Serbest doğaçlama', 'Serbest doğaçlama', 'Davul / pad', 1, 10,
         'Sıra ile solo: her katılımcı kısa bir bölümde serbest çalar, grup nabzı korur. Zorunlu solo yoktur; liderlik seçeneklidir.',
         'Kendini ifade, grup dinamiği', 'yok',
         'Bilimsel iddia taşımaz; keyifli ve pedagojik olarak değerli olması onu değersizleştirmez',
         'Davul/pad'],

        ['Ritim Planla–Uygula–Onar', 'Serbest doğaçlama', 'Davul / pad', 3, 15,
         'Katılımcı veya grup 4 ölçülük bir düzen tasarlar; görevler ve girişler dağıtılır; prova edilir; tek bir iyileştirme seçilip yeniden uygulanır.',
         'Planlama, izleme, ekip koordinasyonu', 'yok',
         'RitimOdak kılavuzu Etkinlik 9',
         'Davul/pad, 4 ölçülük plan formu'],

        ['Ritimden Sessiz Göreve', 'Sessiz görev aktarımı', 'Davul / pad', 2, 15,
         '60–90 saniye sabit ritim ve nefesle hazırlık yapılır; enstrüman bırakılır ve 8–15 dakikalık sessiz çalışma görevi uygulanır. Göreve dönüşler ve yardım istekleri gözlemlenir. Görev süresi kademeli uzatılır, zorluğu sabit tutulur.',
         'Göreve geçiş, sürdürme', 'zayif',
         'RitimOdak kılavuzu Etkinlik 10; transfer kanıtı sınırlı',
         'Davul/pad, yaşa uygun sessiz görev, kronometre'],
    ];

    $st = $pdo->prepare(
        'INSERT OR IGNORE INTO teknikler
            (ad, kategori, enstruman, seviye, sure_dk, aciklama, hedef_beceri, kanit_duzeyi, kaynak, malzeme, aktif, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
    );
    $zaman = now_str();
    foreach ($satirlar as $s) {
        $st->execute([$s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7], $s[8], $s[9], $zaman]);
    }
}

/**
 * Tanıtım sitesi varsayılan bölümleri ve metinleri. Yalnız site_bolumleri
 * boşken çalışır; eğitmenin panelden yaptığı değişiklikler asla ezilmez.
 */
function seed_site(): void
{
    $pdo = db();
    if ((int)$pdo->query('SELECT COUNT(*) FROM site_bolumleri')->fetchColumn() > 0) {
        // Eski varsayılan 12 haftalık tanıtımı, yalnız kullanıcı tarafından
        // değiştirilmemiş alanlarda 2 aylık yoğun programa taşı.
        $baslikGuncelle = $pdo->prepare(
            'UPDATE site_bolumleri SET baslik = ? WHERE anahtar = ? AND baslik = ?'
        );
        $baslikGuncelle->execute([
            '2 Aylık Yoğunlaştırılmış Program',
            'program',
            'Program: iki iz, on iki hafta',
        ]);
        $metinGuncelle = $pdo->prepare(
            'UPDATE site_icerik SET deger = ? WHERE anahtar = ? AND deger = ?'
        );
        foreach ([
            ['8', 'sayi_1_deger', '12'],
            ['haftalık yoğun program', 'sayi_1_etiket', 'haftalık program'],
            ['16', 'sayi_2_deger', '24'],
        ] as [$yeni, $anahtar, $eski]) {
            $metinGuncelle->execute([$yeni, $anahtar, $eski]);
        }
        $eskiProgramAciklama = 'RitimOdak programı iki bağımsız izde yürür: öğrenci izi (8–15 yaş) ve yetişkin izi (18+). '
            . 'Her hafta bir hedef, her oturumda planlı bir akış vardır; ilerleme kararları kayıtla verilir. '
            . 'Katılım için ritim/müzik geçmişi gerekmez.';
        $yeniProgramAciklama = 'RitimOdak yoğunlaştırılmış programı sekiz hafta boyunca iki bağımsız izde yürür: '
            . 'öğrenci izi (8–15 yaş) ve yetişkin izi (18+). Haftada iki oturumla her hafta yeni bir hedef çalışılır; '
            . 'ilerleme kararları kayıtla verilir. Katılım için ritim/müzik geçmişi gerekmez.';
        $metinGuncelle->execute([$yeniProgramAciklama, 'program_aciklama', $eskiProgramAciklama]);
        $eskiHeroAciklama = 'Küçük grup ritim atölyeleri: metronomla senkronizasyon, dur–devam oyunları, '
            . 'ritim hafızası ve grup senkronisi. Her teknik, bilimsel literatürdeki karşılığıyla '
            . 'birlikte kanıt düzeyi etiketiyle çalışılır — ne fazlası vaat edilir, ne eksiği söylenir.';
        $yeniHeroAciklama = 'Özel ders ve küçük grup ritim programları: metronomla senkronizasyon, dur–devam oyunları, '
            . 'ritim hafızası ve birlikte çalma. Her teknik, bilimsel literatürdeki karşılığıyla '
            . 'birlikte kanıt düzeyi etiketiyle çalışılır — ne fazlası vaat edilir, ne eksiği söylenir.';
        $metinGuncelle->execute([$yeniHeroAciklama, 'hero_aciklama', $eskiHeroAciklama]);
        // Sonradan eklenen bölümler mevcut kuruluma da (bir kez) eklenir.
        $pdo->exec("INSERT OR IGNORE INTO site_bolumleri (anahtar, baslik, sira, gorunur)
                    SELECT 'galeri', 'Atölyeden kareler',
                           (SELECT COALESCE(MAX(sira), 0) + 1 FROM site_bolumleri), 1
                    WHERE NOT EXISTS (SELECT 1 FROM site_bolumleri WHERE anahtar = 'galeri')");
        $pdo->exec("INSERT OR IGNORE INTO site_bolumleri (anahtar, baslik, sira, gorunur)
                    SELECT 'moxo', 'MOXO dikkat ölçümü — isteğe bağlı',
                           (SELECT COALESCE(MAX(sira), 0) + 1 FROM site_bolumleri), 1
                    WHERE NOT EXISTS (SELECT 1 FROM site_bolumleri WHERE anahtar = 'moxo')");
        /* Etkileşimli bölümler ziyaretçiyi ilk ekranda yakalasın diye EN BAŞA
           eklenir (mevcut sıranın önüne). Eğitmen sürükle-bırakla istediği
           yere taşıyabilir; ilk kaydetmede sıra numaraları 1..N'e normalleşir. */
        $enKucuk = (int)$pdo->query('SELECT COALESCE(MIN(sira), 1) FROM site_bolumleri')->fetchColumn();
        $bolumEkle = $pdo->prepare('INSERT INTO site_bolumleri (anahtar, baslik, sira, gorunur)
                                    SELECT ?, ?, ?, 1
                                     WHERE NOT EXISTS (SELECT 1 FROM site_bolumleri WHERE anahtar = ?)');
        $bolumEkle->execute(['deney', 'Kulağını test et — canlı deneyler', $enKucuk - 2, 'deney']);
        $bolumEkle->execute(['nabiz', 'Dünyanın nabzı', $enKucuk - 1, 'nabiz']);
        seed_moxo_texts();
        seed_deney_texts();
        return;
    }

    $bolumler = [
        // [anahtar, başlık, sıra]
        ['deney',       'Kulağını test et — canlı deneyler', 1],
        ['yontem',      'Atölyede bir oturum nasıl akar?', 2],
        ['program',     '2 Aylık Yoğunlaştırılmış Program', 3],
        ['bilim',       'Literatür ne söylüyor, atölyede nasıl yankılanıyor?', 4],
        ['nabiz',       'Dünyanın nabzı', 5],
        ['protokoller', 'Metronom modülü ve dikkat protokolleri', 6],
        ['moxo',        'MOXO dikkat ölçümü — isteğe bağlı', 7],
        ['galeri',      'Atölyeden kareler', 8],
        ['bizkimiz',    'Fizikten ritme uzanan bir sınıf', 9],
        ['iletisim',    'Bize ulaşın', 10],
    ];
    $bs = $pdo->prepare('INSERT INTO site_bolumleri (anahtar, baslik, sira, gorunur) VALUES (?, ?, ?, 1)');
    foreach ($bolumler as $b) { $bs->execute($b); }
    seed_moxo_texts();
    seed_deney_texts();

    $metinler = [
        'hero_ustbaslik'  => 'RİTİM · DİKKAT · ÖZ-DÜZENLEME',
        'hero_baslik'     => "Vuruşu bul.\nRitmi koru.\nKendi temponu keşfet.",
        'hero_aciklama'   => 'Özel ders ve küçük grup ritim programları: metronomla senkronizasyon, dur–devam oyunları, '
                           . 'ritim hafızası ve birlikte çalma. Her teknik, bilimsel literatürdeki karşılığıyla '
                           . 'birlikte kanıt düzeyi etiketiyle çalışılır — ne fazlası vaat edilir, ne eksiği söylenir.',
        'serit_metin'     => 'SENKRONİZASYON · DÜRTÜ KONTROLÜ · ÇALIŞMA BELLEĞİ · BİLİŞSEL ESNEKLİK · '
                           . 'GRUP SENKRONİSİ · MOTOR KOORDİNASYON · KENDİNİ İFADE',
        'sayi_1_deger'    => '8',  'sayi_1_etiket' => 'haftalık yoğun program',
        'sayi_2_deger'    => '16', 'sayi_2_etiket' => 'oturumluk akış',
        'sayi_3_deger'    => '18', 'sayi_3_etiket' => 'teknik kütüphanede',
        'sayi_4_deger'    => '4',  'sayi_4_etiket' => 'kanıt düzeyi etiketi',
        'program_aciklama' => 'RitimOdak yoğunlaştırılmış programı sekiz hafta boyunca iki bağımsız izde yürür: öğrenci izi (8–15 yaş) ve yetişkin izi (18+). '
                           . 'Haftada iki oturumla her hafta yeni bir hedef çalışılır; ilerleme kararları kayıtla verilir. '
                           . 'Katılım için ritim/müzik geçmişi gerekmez.',
        'bizkimiz_metin'  => 'RitimTerapi; fizik öğretmenliğinden gelen ölçme alışkanlığını, ritim atölyesi '
                           . 'eğitmenliğinin sahne enerjisiyle birleştiren bir eğitim girişimidir. Dalgalar, '
                           . 'frekanslar ve rezonans fizik dersinin konusu; vuruş, tempo ve senkron ise atölyenin. '
                           . 'İkisinin kesişiminde disiplinli ama oyunlu bir öğrenme alanı kuruyoruz.',
        'iletisim_telefon'   => '0 (5xx) xxx xx xx',
        'iletisim_eposta'    => 'iletisim@ritimterapi.example',
        'iletisim_instagram' => '@ritimterapi',
        'iletisim_adres'     => 'Atölye adresi — şehir',
        'alt_uyari'       => 'RitimTerapi bir eğitim aracıdır; sağlık ürünü veya hizmeti değildir. '
                           . 'Tanı, tedavi ve klinik değerlendirme yalnız yetkili uzmanların işidir.',
    ];
    $ms = $pdo->prepare('INSERT INTO site_icerik (anahtar, deger) VALUES (?, ?)');
    foreach ($metinler as $anahtar => $deger) { $ms->execute([$anahtar, $deger]); }
}

/**
 * Akademik çalışma kayıt defteri: RitimOdak kaynakçasındaki çalışmalar DOI'leriyle
 * eklenir ve tekniklere bağlanır. Yalnız tablo boşken çalışır; özetler nötr dille
 * yazılmıştır (klinik etiket yerine "dikkat güçlüğü yaşayan katılımcılar").
 * Bağlantısı bilinçli boş bırakılan teknikler "akademik çalışma yok" olarak görünür.
 */
function seed_studies(): void
{
    $pdo = db();
    if ((int)$pdo->query('SELECT COUNT(*) FROM akademik_calismalar')->fetchColumn() > 0) {
        return;
    }

    $calismalar = [
        // anahtar => [baslik, yazarlar, yil, dergi, doi, tur, ozet]
        'puyjarinet2017' => ['Children and adults with attention-deficit/hyperactivity disorder cannot move to the beat',
            'Puyjarinet, Bégel, Lopez, Dellacherie & Dalla Bella', 2017, 'Scientific Reports',
            '10.1038/s41598-017-11295-w', 'deneysel',
            'Dikkat güçlüğü yaşayan çocuk ve yetişkinlerin dışsal bir vuruşa eşzamanlanmada belirgin biçimde daha değişken olduğunu nesnel ölçümle gösterdi; ritmik zamanlama güçlüğünü ortaya koyan temel çalışma.'],
        'shaffer2001' => ['Effect of Interactive Metronome training on children with ADHD',
            'Shaffer, Jacokes, Cassily et al.', 2001, 'American Journal of Occupational Therapy',
            '10.5014/ajot.55.2.155', 'deneysel',
            'Etkileşimli metronom antrenmanı alan çocuklarda bazı motor ve dikkat ölçümlerinde kontrol koşullarına göre gelişme '
            . 'raporlandı; randomize değil, yalnız erkek örneklem (n=19) — kanıt gücü sınırlıdır.'],
        'shin2023' => ['Rhythm-based assessment and training for children with ADHD: a feasibility study protocol',
            'Shin, Lee, Kang, Kim & Jeong', 2023, 'Frontiers in Human Neuroscience',
            '10.3389/fnhum.2023.1190736', 'protokol',
            'Ritim temelli değerlendirme ve eğitim yaklaşımının uygulanabilirliğini sınayan protokol makalesi; yöntem çerçevesi sunar, etkililik kanıtı değildir.'],
        'jamey2025' => ['Can you beat the music? Validation of a gamified rhythmic training in children with ADHD',
            'Jamey, Laflamme, Foster et al.', 2025, 'Behavior Research Methods',
            '10.3758/s13428-025-02802-3', 'gecerleme',
            'Oyunlaştırılmış ritim eğitimi uygulamasının çocuklarda geçerlemesi; ritim çalışmasının ev/uzaktan ortamda uygulanabilirliğini destekler.'],
        'jamey2024' => ['Does music training improve inhibition control in children? A systematic review and meta-analysis',
            'Jamey, Foster, Hyde & Dalla Bella', 2024, 'Cognition',
            '10.1016/j.cognition.2024.105913', 'meta',
            'Müzik eğitiminin çocuklarda ket vurma (inhibisyon) görevlerine etkisini toplayan meta-analiz; küçük-orta düzeyde, göreve göre değişken katkı bulundu.'],
        'frischen2019' => ['Comparing the effects of rhythm-based music training and pitch-based music training on executive functions in preschoolers',
            'Frischen, Schwarzer & Degé', 2019, 'Frontiers in Integrative Neuroscience',
            '10.3389/fnint.2019.00041', 'rct',
            'Okul öncesinde ritim temelli eğitim alan grup inhibisyon görevinde spor kontrol grubuna göre anlamlı fark gösterdi (F(1,42)=7,14; p=0,011). '
            . 'Ritim ile ezgi grupları arasında anlamlı fark BULUNMADI. Set-shifting (p=0,47) ve görsel-uzamsal çalışma belleği '
            . '(p=0,11 / p=0,14) ölçümlerinde etki bulunmadı. Üç kollu randomize, n=76.'],
        'bentley2023' => ['A translational application of music for preschool cognitive development: RCT evidence for improved executive function, self-regulation, and school readiness',
            'Bentley, Eager, Savage et al.', 2023, 'Developmental Science',
            '10.1111/desc.13358', 'rct',
            'Randomize kontrollü denemede müzik programına katılan okul öncesi çocuklarda yürütücü işlev, öz-düzenleme ve okul hazırlığı ölçümlerinde gelişme raporlandı.'],
        'rickson2006' => ['Instructional and improvisational models of music therapy with adolescents who have ADHD: effects on motor impulsivity',
            'Rickson, D. J.', 2006, 'Journal of Music Therapy',
            '10.1093/jmt/43.1.39', 'deneysel',
            'Öğretimsel ve doğaçlama modeller arasında eşzamanlı vuruş görevinde istatistiksel fark BULUNMADI; her iki grup da vuruş '
            . 'doğruluğunda gelişti. Yazarlar bu artışı özellikle motor dürtüsellikle değil genel gelişim alanlarıyla ilişkilendiriyor. n=13.'],
        'erarslan2026' => ['Effects of music-based occupational therapy activities on attention executive functions in children',
            'Erarslan, Budak & Tarakci', 2026, 'PLOS ONE',
            '10.1371/journal.pone.0349284', 'rct',
            'Dikkat ölçümünde HER İKİ grupta da gelişme raporlandı (F(1,37)=9,675; p=0,004); yürütücü işlev (BRIEF; p=0,122) ve '
            . 'bakım veren yükü (p=0,967) ölçümlerinde anlamlı değişim BULUNMADI. n=39, aktif karşılaştırma kollu randomize.'],
        'zhu2026' => ['Influence of rhythmic music game intervention on executive function and sensorimotor ability of children',
            'Zhu, Honghui', 2026, 'Frontiers in Public Health',
            '10.3389/fpubh.2026.1808386', 'rct',
            'Ritmik müzik oyunu müdahalesi alan çocuklarda yürütücü işlev ve duyu-motor ölçümlerinde gelişme bildirildi (n=64 randomize). '
            . 'ÖNEMLİ SINIRLILIK: kontrol grubu pasif (8 haftada ~80 dk) iken müdahale grubu ~960 dk çalıştı — yaklaşık 12 kat temas farkı. '
            . 'Gözlenen fark ritmik içeriğe özgü değil, ek etkinliğe de bağlı olabilir. Tek merkez, tek yazar, körleme bildirilmemiş.'],
        'haigh2024' => ['Rhythmic attention and ADHD: a narrative and systematic review',
            'Haigh & Buckby', 2024, 'Applied Psychophysiology and Biofeedback',
            '10.1007/s10484-023-09618-x', 'derleme',
            'Dikkatin ritmik salınımlarını ve elektrofizyolojik (EEG) güç farklarını tarayan derleme (15 çalışma). '
            . 'Bildirilen örüntü: daha yüksek delta/teta, daha düşük alfa gücü. Ritim temelli EĞİTİM veya müdahale değerlendirmez.'],
        'goes2025' => ['Outcomes of music therapy on children and adolescents with ADHD: a systematic review and meta-analysis',
            'Goes, Nardi & Quagliato', 2025, 'Trends in Psychiatry and Psychotherapy',
            '10.47626/2237-6089-2024-1020', 'meta',
            'Çocuk ve ergenlerde müzik temelli müdahaleleri toplayan sistematik derleme/meta-analiz; karma ve düşük kesinlikli bulgular raporladı.'],
        'martin2023' => ['Effects of music on ADHD and potential application in serious video games: systematic review',
            'Martin-Moratinos, Bella-Fernández & Blasco-Fontecilla', 2023, 'Journal of Medical Internet Research',
            '10.2196/37742', 'derleme',
            'Müzik ve ciddi oyun uygulamalarını tarayan sistematik derleme; alanın genç, çalışmaların heterojen olduğunu vurgular.'],
        'puyjarinet2020' => ['Psychomotor therapy and ADHD: evaluation of a rhythm-based therapeutic program',
            'Puyjarinet, Jeannin-Fuzier, Blain, Fournier & Metivier', 2020, "Neuropsychiatrie de l'Enfance et de l'Adolescence",
            '10.1016/j.neurenf.2019.11.003', 'pilot',
            'Ritim temelli psikomotor programın pilot değerlendirmesi; inhibisyon ve bilişsel dürtüsellikte gelişme, buna karşılık '
            . 'sürdürülen işitsel dikkat, seçici görsel dikkat ve gecikmeye tahammülsüzlükte DEĞİŞİM YOK. Tek gruplu ön-son test (n=21); '
            . 'kontrol grubu ve randomizasyon olmadığı için gelişme test-tekrar etkisiyle de açıklanabilir.'],
        'abikoff1996' => ['The effects of auditory stimulation on the arithmetic performance of children with ADHD and nondisabled children',
            'Abikoff, Courtney, Szeibel & Koplewicz', 1996, 'Journal of Learning Disabilities',
            '10.1177/002221949602900302', 'deneysel',
            'Görev sırasında müzik dinlemenin bazı çocuklarda aritmetik performansı desteklediğini, etkinin kişiden kişiye değiştiğini gösterdi.'],
        'pelham2011' => ['Music and video as distractors for boys with ADHD in the classroom',
            'Pelham, Waschbusch, Hoza et al.', 2011, 'Journal of Abnormal Child Psychology',
            '10.1007/s10802-011-9529-z', 'deneysel',
            'Görev sırasında müzik/video çeldiricilerinin etkisini inceledi; müzik çoğu çocukta performansı bozmadı, bireysel farklar belirgindi.'],
        'woods2024' => ['Rapid modulation in music supports attention in listeners with attentional difficulties',
            'Woods, Sampaio, James et al.', 2024, 'Communications Biology',
            '10.1038/s42003-024-07026-3', 'deneysel',
            'Hızlı modülasyonlu müzik dinlemenin, dikkat güçlüğü bildiren dinleyicilerde görev sırasında dikkati desteklediğini raporladı.'],
        'berger2017' => ['Usefulness and validity of continuous performance tests in the diagnosis of ADHD children',
            'Berger, Slobodin & Cassuto', 2017, 'Archives of Clinical Neuropsychology',
            '10.1093/arclin/acw101', 'gecerleme',
            'MOXO d-CPT sürekli performans testinin ayırt ediciliğine ilişkin geçerlik verileri; test tek başına tanı aracı değildir.'],
        'arrondo2024' => ['Clinical utility of continuous performance tests for the identification of ADHD',
            'Arrondo, Mulraney, Iturmendi-Sabater et al.', 2024, 'Journal of the American Academy of Child & Adolescent Psychiatry',
            '10.1016/j.jaac.2023.03.011', 'meta',
            'Sürekli performans testlerinin klinik karar için tek başına yeterli olmadığını gösteren sistematik derleme/meta-analiz.'],
    ];

    $zaman = now_str();
    $ins = $pdo->prepare('INSERT INTO akademik_calismalar (baslik, yazarlar, yil, dergi, doi, tur, ozet, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $calismaId = [];
    foreach ($calismalar as $anahtar => $c) {
        $ins->execute([$c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6], $zaman]);
        $calismaId[$anahtar] = (int)$pdo->lastInsertId();
    }

    // Teknik → çalışma eşlemeleri. Hata Sonrası Dönüş, Çift Hat ve Ritimden
    // Sessiz Göreve burada BOŞ bırakılır; onlara sonradan yalnız olgu/komşu alan
    // kaynakları bağlanır (seed_unsupported_technique_studies). Bu bağlar
    // tekniğin ETKİSİNİN kanıtı değildir ve kanıt düzeyini YÜKSELTMEZ.
    // Ritim Planla–Uygula–Onar hiç bağlanmaz: 'yok' etiketi bilinçlidir.
    $baglar = [
        'Metronoma eşlik' => [
            ['puyjarinet2017', 'Ölçülen beceri doğrudan bu: vuruşa eşzamanlanma.'],
            ['shaffer2001', 'Metronom temelli antrenmanın erken örneği.'],
            ['shin2023', 'Ritim temelli değerlendirme çerçevesi.'],
        ],
        'Nabzı Bul' => [
            ['puyjarinet2017', 'Sessiz sürdürme ve yeniden eşleşme aynı beceri ailesinde.'],
            ['jamey2025', 'Oyunlaştırılmış ritim görevlerinin geçerlemesi.'],
        ],
        'Dur–devam' => [
            ['jamey2024', 'Başla-dur yapısı inhibisyon görev ailesindedir.'],
            ['frischen2019', 'Ritim grubunda inhibisyon kazanımı raporlandı.'],
        ],
        'Bekle–Dinle–Vur' => [
            ['jamey2024', 'Gecikmeli/no-go cevap inhibisyon ailesinde.'],
            ['rickson2006', 'Öğretimsel ve doğaçlama modeller arasında fark bulunmadı; yapılandırılmış tasarım pedagojik bir tercihtir.'],
        ],
        'Hedef Tını' => [
            ['erarslan2026', 'Müzik temelli dikkat etkinlikleriyle aynı ailede.'],
            ['jamey2024', 'Hedefe tepki / hedef dışını tutma inhibisyon bileşeni.'],
        ],
        'Çağrı–cevap' => [
            ['frischen2019', 'Ritmik taklit blokları çalışmanın eğitim içeriğindeydi.'],
            ['bentley2023', 'Program içeriği benzer taklit/yanıt oyunları içerir.'],
        ],
        'Ritmik dizi tekrarı' => [
            ['bentley2023', 'Bellek bileşenli müzik etkinlikleri programın parçasıydı.'],
            ['frischen2019', 'Dizi tekrarı ritim eğitiminin çekirdek görevlerindendi.'],
        ],
        'Ritim Hafıza Zinciri' => [
            ['frischen2019', 'Uzayan dizi tekrarının grup uyarlaması.'],
            ['bentley2023', 'Sıralama/bellek oyunlarıyla paralel.'],
        ],
        'Tempo değişimi takibi' => [
            ['zhu2026', 'Müdahale ritim senkronizasyonuydu; esneklik yalnız TMT-B ile ÖLÇÜLDÜ, çalışılmadı.'],
        ],
        'Kural Değiştir' => [
            ['zhu2026', 'Kural geçişi çalışmanın müdahale içeriğinde yok; yalnız ölçüm düzeyinde.'],
        ],
        'Katmanlı grup ritmi' => [
            ['goes2025', 'Grup müzik müdahaleleri geneli; bulgular karma ve düşük kesinlikli.'],
        ],
        'Daire Senkroni' => [
            ['goes2025', 'Grup temelli müzik etkinlikleri kapsamında; doğrudan kanıt yok.'],
        ],
        'Vücut perküsyonu' => [
            ['frischen2019', 'Beden perküsyonu ritim eğitimi bloklarının parçasıydı.'],
            ['bentley2023', 'Program benzer beden-ritim oyunları içerir.'],
        ],
        'Serbest doğaçlama' => [
            ['rickson2006', 'Doğaçlama model, öğretimsel modele üstün bulunmadı — kanıt "yok" etiketi bilinçlidir.'],
        ],
    ];

    $teknikId = $pdo->query('SELECT ad, id FROM teknikler')->fetchAll(PDO::FETCH_KEY_PAIR);
    $bagIns = $pdo->prepare('INSERT OR IGNORE INTO teknik_calismalari (teknik_id, calisma_id, iliski_notu) VALUES (?, ?, ?)');
    foreach ($baglar as $teknikAd => $liste) {
        if (!isset($teknikId[$teknikAd])) { continue; }
        foreach ($liste as [$anahtar, $not]) {
            if (!isset($calismaId[$anahtar])) { continue; }
            $bagIns->execute([(int)$teknikId[$teknikAd], $calismaId[$anahtar], $not]);
        }
    }
}

/**
 * Yerel eğitim PDF'lerinin grup çalışması taramasında doğrulanan bağımsız
 * kaynaklar. Mevcut kurulumlara da yalnız bir kez eklenir; aynı DOI varsa
 * kullanıcının kaydı korunur ve yalnız ilgili teknik bağı kurulur.
 */
function seed_group_workshop_studies(): void
{
    if (site_text('sistem_grup_atolyesi_kaynak_seed') === '1') { return; }
    $pdo = db();
    $calismalar = [
        // [anahtar, başlık, yazarlar, yıl, dergi, doi, tür, nötr özet]
        ['senkron_meta',
         'Prosocial Consequences of Interpersonal Synchrony: A Meta-Analysis',
         'Rennung & Göritz', 2016, 'Zeitschrift für Psychologie',
         '10.1027/2151-2604/a000252', 'meta',
         'Altmış yayımlanmış ve yayımlanmamış deneyi birleştiren meta-analiz, kişilerarası senkronun prososyal tutum ve davranış ölçümlerinde orta büyüklükte etkisini bildirdi. Deneyci etkisi ve bağlam sonucu değiştirebilir; atölye dışına kalıcı aktarım garanti değildir.'],
        ['grup_davul',
         'Effects of Group Drumming Interventions on Anxiety, Depression, Social Resilience and Inflammatory Immune Response among Mental Health Service Users',
         'Fancourt, Perkins, Ascenso et al.', 2016, 'PLOS ONE',
         '10.1371/journal.pone.0151136', 'deneysel',
         'On haftalık grup davulu çalışması olumlu sonuçlar bildirdi. Atama randomize değildi ve çalışma belirli bir hizmet bağlamında yürütüldü; bu nedenle bulgular ön ve bağlama duyarlı kanıt olarak değerlendirilmelidir.'],
        ['ras_derleme',
         'The effects of rhythmic auditory stimulation on functional ambulation after stroke: a systematic review',
         'Gonzalez-Hoelling, Reig-García, Bertran-Noguer & Suñer-Soler', 2024,
         'BMC Complementary Medicine and Therapies',
         '10.1186/s12906-023-04310-3', 'derleme',
         'Yirmi bir çalışmayı ve 948 katılımcıyı kapsayan derleme, ritmik işitsel ipuçlarının yürüme ölçümlerinde olumlu fakat heterojen bulgularını bildirdi. Yanlılık riski ve müdahale çeşitliliği nedeniyle genelleme dikkatle yapılmalıdır.'],
        ['nefes_meta',
         'The Effect of Slow-Paced Breathing on Cardiovascular and Emotion Functions: A Meta-Analysis and Systematic Review',
         'Shao, Man & Lee', 2024, 'Mindfulness',
         '10.1007/s12671-023-02294-2', 'meta',
         'Klinik olmayan 1.133 katılımcılı 31 çalışmanın sentezi bazı kısa süreli kardiyovasküler ölçümlerde etki, olumsuz duygu azalmasında ise sınırda sonuç bildirdi. Yerel eğitim notlarındaki nefes uygulamasının doğrulaması değildir.'],
    ];

    $sec = $pdo->prepare('SELECT id FROM akademik_calismalar WHERE lower(doi) = lower(?) LIMIT 1');
    $ekle = $pdo->prepare(
        'INSERT INTO akademik_calismalar
            (baslik, yazarlar, yil, dergi, doi, tur, ozet, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $bagla = $pdo->prepare(
        'INSERT OR IGNORE INTO teknik_calismalari (teknik_id, calisma_id, iliski_notu)
         VALUES (?, ?, ?)'
    );
    $teknikId = $pdo->query('SELECT ad, id FROM teknikler')->fetchAll(PDO::FETCH_KEY_PAIR);
    $id = [];

    $pdo->exec('BEGIN');
    try {
        foreach ($calismalar as $c) {
            $sec->execute([$c[5]]);
            $bulunan = (int)($sec->fetchColumn() ?: 0);
            if ($bulunan === 0) {
                $ekle->execute([$c[1], $c[2], $c[3], $c[4], $c[5], $c[6], $c[7], now_str()]);
                $bulunan = (int)$pdo->lastInsertId();
            }
            $id[$c[0]] = $bulunan;
        }

        $baglar = [
            ['Daire Senkroni', 'senkron_meta',
             'Ortak zamanlamanın sosyal ölçümlerle ilişkisine dayanak; atölye dışına aktarım iddiası sınırlıdır.'],
            ['Daire Senkroni', 'grup_davul',
             'Grup davulu için ön çalışma; randomize değildir ve sonuç vaadine dönüştürülmez.'],
            ['Katmanlı grup ritmi', 'senkron_meta',
             'Aynı anda ritim üretme, kişilerarası senkron görev ailesindedir.'],
            ['Metronoma eşlik', 'ras_derleme',
             'Ritmik işitsel ipucunun hareket alanındaki kanıtı; genel atölye sonuçlarına doğrudan genellenmez.'],
        ];
        foreach ($baglar as [$teknik, $anahtar, $not]) {
            if (isset($teknikId[$teknik], $id[$anahtar])) {
                $bagla->execute([(int)$teknikId[$teknik], $id[$anahtar], $not]);
            }
        }
        site_text_set('sistem_grup_atolyesi_kaynak_seed', '1');
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }
}

/**
 * DAYANAKSIZ TEKNİKLER İÇİN ARKA PLAN KAYNAKLARI (Ağustos 2026).
 *
 * NE DEĞİLDİR: Bu kaynakların HİÇBİRİ bu tekniklerin etkililiğini ölçmüş bir
 * deneme değildir. Hiçbiri kanıt düzeyini yükseltmez — bu fonksiyon zaten
 * kanit_duzeyi alanına DOKUNMAZ. Amaç, tekniğin varsaydığı sürecin komşu
 * alanda ölçülmüş olduğunu belgelemek; "bu teknik işe yarıyor" demek değil.
 *
 * İkisi bilinçli olarak KARŞIT bulgudur (Cocchini 2017, Infantes-Paniagua 2021):
 * kayıt defteri yalnız destekleyici kaynak toplayan bir vitrin olmamalı.
 *
 * Tüm DOI'ler Crossref'ten tek tek doğrulandı (11/11, HTTP 200, geri çekilme
 * kaydı yok). Yıl alanları basılı sayı yılıdır — Moradzadeh çevrimiçi 2014 /
 * basılı 2015 (Cogn Sci 39:5), Cocchini çevrimiçi 2016 / basılı 2017 (Memory 25:5).
 */
function seed_unsupported_technique_studies(): void
{
    if (site_text('sistem_dayanaksiz_teknik_kaynak_seed') === '1') { return; }
    $pdo = db();

    /* [anahtar, başlık, yazarlar, yıl, dergi, doi, tür, nötr özet] */
    $calismalar = [
        ['hata_maidhof',
         'Nobody is perfect: ERP effects prior to performance errors in musicians indicate fast monitoring processes',
         'Maidhof, Rieger, Prinz & Koelsch', 2009, 'PLOS ONE',
         '10.1371/journal.pone.0005032', 'deneysel',
         'Müzisyenlerde hatalı tuş vuruşundan ÖNCE ortaya çıkan beyin yanıtları ölçüldü; hata izleme sürecinin hızlı işlediğine işaret eder. '
         . 'Laboratuvarda piyano görevi; küçük örneklem. Bir atölye tekniğinin denendiği çalışma değildir.'],
        ['hata_danielmeier',
         'Post-error adjustments',
         'Danielmeier & Ullsperger', 2011, 'Frontiers in Psychology',
         '10.3389/fpsyg.2011.00233', 'derleme',
         'Hatadan sonraki davranış ayarlamalarını (yavaşlama, dikkat kayması) tarayan derleme. Bu ayarlamaların ÖĞRETİLEBİLİR olup '
         . 'olmadığı ayrı bir sorudur ve derleme buna yanıt vermez.'],

        ['cift_moradzadeh',
         'Musical training, bilingualism, and executive function: a closer look at task switching and dual-task performance',
         'Moradzadeh, Blumenthal & Wiseheart', 2015, 'Cognitive Science',
         '10.1111/cogs.12183', 'deneysel',
         'Müzik eğitimi almış yetişkinlerde görev değiştirme ve çift görev performansı karşılaştırıldı. Kesitsel tasarım: gruplar '
         . 'zaten farklı olabilir, nedensellik kurulamaz.'],
        ['cift_cocchini',
         'Musical expertise has minimal impact on dual task performance',
         'Cocchini, Filardi, Crhonkova & Halpern', 2017, 'Memory',
         '10.1080/09658211.2016.1205628', 'deneysel',
         'KARŞIT BULGU: müzik uzmanlığının çift görev performansına etkisi en fazla çok küçük bulundu. '
         . 'Kayıt defterine bilinçli olarak eklendi — alandaki bulgular tek yönlü değildir.'],
        ['cift_keller',
         'Rhythm in joint action: psychological and neurophysiological mechanisms for real-time interpersonal coordination',
         'Keller, Novembre & Hove', 2014, 'Philosophical Transactions of the Royal Society B',
         '10.1098/rstb.2013.0394', 'derleme',
         'Birlikte çalarken kişilerarası eşgüdümün psikolojik ve sinirsel düzeneklerini tarayan derleme; kuramsal çerçeve sunar, '
         . 'müdahale etkisi ölçmez.'],

        ['sessiz_mahar',
         'Effects of a classroom-based program on physical activity and on-task behavior',
         'Mahar, Murphy, Rowe, Golden, Shields & Raedeke', 2006, 'Medicine & Science in Sports & Exercise',
         '10.1249/01.mss.0000235359.16685.a3', 'deneysel',
         'Sınıf içi kısa hareket aralarının ardından göreve yönelik davranışta artış gözlendi. Randomize değil; '
         . 'ölçüm gözlemciye dayalı ve etkinlik ritmik değil genel bedensel harekettir.'],
        ['sessiz_masini',
         'Evaluation of school-based interventions of active breaks in primary schools: a systematic review and meta-analysis',
         'Masini, Marini, Gori, Leoni, Rochira & Dallolio', 2020, 'Journal of Science and Medicine in Sport',
         '10.1016/j.jsams.2019.10.008', 'meta',
         'İlkokullarda aktif ara uygulamalarını birleştiren meta-analiz; çalışmalar yöntemsel olarak heterojen, '
         . 'etki büyüklükleri değişken.'],
        ['sessiz_infantes',
         'Active school breaks and students attention: a systematic review with meta-analysis',
         'Infantes-Paniagua, Silva, Ramirez-Campillo et al.', 2021, 'Brain Sciences',
         '10.3390/brainsci11060675', 'meta',
         'DİKKAT: bu meta-analizde aktif ara uygulamalarının dikkat ölçümleri üzerindeki etkilerinin ÇOĞU anlamlı bulunmadı. '
         . 'Kayıt defterine sınırı göstermek için eklendi.'],

        ['aksak_hannon',
         'Familiarity overrides complexity in rhythm perception: a cross-cultural comparison of American and Turkish listeners',
         'Hannon, Soley & Ullal', 2012, 'Journal of Experimental Psychology: Human Perception and Performance',
         '10.1037/a0027225', 'deneysel',
         'Türk ve Amerikalı dinleyicilerin karşılaştırıldığı çalışma: aksak ölçüleri algılamada belirleyici olan şey ölçünün '
         . 'karmaşıklığı değil, dinleyicinin ona AŞİNALIĞI. Kültürel maruziyetin rolünü gösterir.'],
        ['aksak_moelants',
         'Perception and performance of aksak metre',
         'Moelants', 2006, 'Musicae Scientiae',
         '10.1177/102986490601000201', 'deneysel',
         'Aksak ölçülerin algılanması ve icrası üzerine ölçüm çalışması; uzun/kısa birim oranlarının uygulamada ideal '
         . '2:1 oranından saptığını bildirir.'],
        ['aksak_komurcu',
         'Effects of regular and irregular rhythms on time perception: the role of aksak and non-metric rhythms',
         'Kömürcü', 2025, 'Online Journal of Music Sciences',
         '10.31811/ojomus.1647605', 'deneysel',
         'Düzenli ve düzensiz ritimlerin zaman algısına etkisini inceleyen Türkçe kaynak; aksak ve ölçüsüz ritimleri karşılaştırır.'],
    ];

    /*
     * Bağ notları BİLİNÇLİ olarak iddiasız: her biri "teknik denenmedi" der.
     * Aksak kaynakları hiçbir tekniğe bağlanmaz — malzeme arka planıdır,
     * bir etkinliğin dayanağı değil.
     */
    $baglar = [
        ['Hata Sonrası Dönüş', 'hata_maidhof',   'Tekniğin varsaydığı hızlı hata fark etme süreci ölçülmüştür; teknik denenmemiştir.'],
        ['Hata Sonrası Dönüş', 'hata_danielmeier', 'Hata sonrası ayarlama olgusunun derlemesi; bu ayarlamanın öğretilebilirliği gösterilmemiştir.'],
        ['Çift Hat', 'cift_moradzadeh', 'Komşu alan bulgusu (müzik eğitimi ve çift görev); kesitsel, nedensellik yok.'],
        ['Çift Hat', 'cift_cocchini',   'KARŞIT bulgu: uzmanlık çift görev düşüşünü azaltmadı. Zayıf etiketi bilinçlidir.'],
        ['Çift Hat', 'cift_keller',     'Birlikte çalmada eşgüdüm düzeneklerinin kuramsal çerçevesi; müdahale çalışması değil.'],
        ['Ritimden Sessiz Göreve', 'sessiz_mahar',    'Hareket arasından göreve dönüş olgusu; etkinlik ritmik değil, randomize değil.'],
        ['Ritimden Sessiz Göreve', 'sessiz_masini',   'Aktif ara literatürünün meta-analizi; heterojen ve ritme özgü değil.'],
        ['Ritimden Sessiz Göreve', 'sessiz_infantes', 'Sınırı gösteren meta-analiz: dikkat ölçümlerinin çoğunda anlamlı etki yok.'],
    ];

    $sec  = $pdo->prepare('SELECT id FROM akademik_calismalar WHERE lower(doi) = lower(?) LIMIT 1');
    $ins  = $pdo->prepare('INSERT INTO akademik_calismalar (baslik, yazarlar, yil, dergi, doi, tur, ozet, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $bagla = $pdo->prepare('INSERT OR IGNORE INTO teknik_calismalari (teknik_id, calisma_id, iliski_notu)
                            VALUES (?, ?, ?)');

    $pdo->exec('BEGIN');
    try {
        $id = [];
        foreach ($calismalar as [$anahtar, $baslik, $yazar, $yil, $dergi, $doi, $tur, $ozet]) {
            $sec->execute([$doi]);
            $mevcut = $sec->fetchColumn();
            if ($mevcut) { $id[$anahtar] = (int)$mevcut; continue; }  // eğitmenin kaydı korunur
            $ins->execute([$baslik, $yazar, $yil, $dergi, $doi, $tur, $ozet, now_str()]);
            $id[$anahtar] = (int)$pdo->lastInsertId();
        }

        $teknikId = [];
        foreach ($pdo->query('SELECT id, ad FROM teknikler') as $t) { $teknikId[$t['ad']] = (int)$t['id']; }
        foreach ($baglar as [$teknik, $anahtar, $not]) {
            if (isset($teknikId[$teknik], $id[$anahtar])) {
                $bagla->execute([$teknikId[$teknik], $id[$anahtar], $not]);
            }
        }

        site_text_set('sistem_dayanaksiz_teknik_kaynak_seed', '1');
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }
}

/**
 * TEK SEFERLİK BİLİMSEL DÜZELTME (Ağustos 2026).
 *
 * NEDEN AYRI BİR FONKSİYON: seed_techniques() ve seed_studies() yalnız tablo
 * BOŞKEN çalışır (silinen kayıt dirilmesin diye). Dolayısıyla yukarıdaki
 * düzeltmeler mevcut kurulumlara KENDİLİĞİNDEN ULAŞMAZ — bu fonksiyon onları
 * canlı veritabanına taşır.
 *
 * Düzeltmelerin dayanağı: kayıtların Crossref künyeleri doğru çıktı (uydurma
 * DOI veya geri çekilmiş makale YOK), ama bazı ÖZETLER kaynakların bulgusunu
 * yanlış aktarıyordu — null çıkan sonuçlar "gelişme" diye özetlenmiş,
 * bulunmayan grup farkları iddia edilmiş, bir derlemenin konusu yanlış
 * yazılmıştı. Bu, CLAUDE.md §5'in "kanıt düzeyleri gerçekçi girilecek"
 * kuralının doğrudan ihlaliydi.
 *
 * SINIR: yalnız kanit_duzeyi, kaynak, ozet ve tur alanlarını günceller.
 * Eğitmenin KENDİ eklediği teknikler ve çalışmalar (seed anahtarında olmayan)
 * hiç dokunulmadan kalır.
 */
function seed_scientific_corrections_2026(): void
{
    if (site_text('sistem_bilimsel_duzeltme_2026') === '1') { return; }
    $pdo = db();

    /* [DOI => [tur, ozet]] — künye doğru, yalnız tür ve özet düzeltiliyor */
    $calismaDuzelt = [
        '10.3389/fnint.2019.00041' => ['rct',
            'Okul öncesinde ritim temelli eğitim alan grup inhibisyon görevinde spor kontrol grubuna göre anlamlı fark gösterdi '
            . '(F(1,42)=7,14; p=0,011). Ritim ile ezgi grupları arasında anlamlı fark BULUNMADI. Set-shifting (p=0,47) ve '
            . 'görsel-uzamsal çalışma belleği (p=0,11 / p=0,14) ölçümlerinde etki bulunmadı. Üç kollu randomize, n=76.'],
        '10.1093/jmt/43.1.39' => ['deneysel',
            'Öğretimsel ve doğaçlama modeller arasında eşzamanlı vuruş görevinde istatistiksel fark BULUNMADI; her iki grup da '
            . 'vuruş doğruluğunda gelişti. Yazarlar bu artışı özellikle motor dürtüsellikle değil genel gelişim alanlarıyla '
            . 'ilişkilendiriyor. n=13.'],
        '10.1371/journal.pone.0349284' => ['rct',
            'Dikkat ölçümünde HER İKİ grupta da gelişme raporlandı (F(1,37)=9,675; p=0,004); yürütücü işlev (BRIEF; p=0,122) ve '
            . 'bakım veren yükü (p=0,967) ölçümlerinde anlamlı değişim BULUNMADI. n=39, aktif karşılaştırma kollu randomize.'],
        '10.3389/fpubh.2026.1808386' => ['rct',
            'Ritmik müzik oyunu müdahalesi alan çocuklarda yürütücü işlev ve duyu-motor ölçümlerinde gelişme bildirildi (n=64 randomize). '
            . 'ÖNEMLİ SINIRLILIK: kontrol grubu pasif (8 haftada ~80 dk) iken müdahale grubu ~960 dk çalıştı — yaklaşık 12 kat temas '
            . 'farkı. Gözlenen fark ritmik içeriğe özgü değil, ek etkinliğe de bağlı olabilir. Tek merkez, tek yazar, körleme bildirilmemiş.'],
        '10.1007/s10484-023-09618-x' => ['derleme',
            'Dikkatin ritmik salınımlarını ve elektrofizyolojik (EEG) güç farklarını tarayan derleme (15 çalışma). '
            . 'Bildirilen örüntü: daha yüksek delta/teta, daha düşük alfa gücü. Ritim temelli EĞİTİM veya müdahale değerlendirmez.'],
        '10.5014/ajot.55.2.155' => ['deneysel',
            'Etkileşimli metronom antrenmanı alan çocuklarda bazı motor ve dikkat ölçümlerinde kontrol koşullarına göre gelişme '
            . 'raporlandı; randomize değil, yalnız erkek örneklem (n=19) — kanıt gücü sınırlıdır.'],
        '10.1016/j.neurenf.2019.11.003' => ['pilot',
            'Ritim temelli psikomotor programın pilot değerlendirmesi; inhibisyon ve bilişsel dürtüsellikte gelişme, buna karşılık '
            . 'sürdürülen işitsel dikkat, seçici görsel dikkat ve gecikmeye tahammülsüzlükte DEĞİŞİM YOK. Tek gruplu ön-son test '
            . '(n=21); kontrol grubu ve randomizasyon olmadığı için gelişme test-tekrar etkisiyle de açıklanabilir.'],
    ];

    /* [teknik adı => [yeni kanit_duzeyi, yeni kaynak]] */
    $teknikDuzelt = [
        'Çağrı–cevap' => ['zayif',
            'Tek dayanak Frischen et al. 2019; orada bellek ölçümleri NULL (Matrix Span p=0,11; Corsi p=0,14) ve '
            . 'ölçülen şey görsel-uzamsal, işitsel değil.'],
        'Ritmik dizi tekrarı' => ['zayif',
            'Bentley et al. 2023 çalışma belleği çıktısı RAPORLAMADI (anlamlı çıktılar öz-düzenleme/inhibisyon/okula hazırlık); '
            . 'Frischen 2019 bellek ölçümlerinde null.'],
        'Ritim Hafıza Zinciri' => ['zayif',
            'RitimOdak kılavuzu Etkinlik 3. Dış akademik künye yok; bağlı çalışmalarda ilgili çıktı null. '
            . 'Grup-halkası formatı hiçbir yayında sınanmadı.'],
        'Tempo değişimi takibi' => ['zayif',
            'Tek dayanak Zhu 2026; orada kontrol grubu pasif ve ~12 kat doz farkı var. '
            . 'Frischen 2019 set-shifting ölçümünde null (p=0,47).'],
        'Kural Değiştir' => ['zayif',
            'RitimOdak kılavuzu Etkinlik 5. Dış künye yok. Bilişsel esneklik, ritim literatüründe kanıtı en zayıf '
            . 'yürütücü işlev bileşeni; Frischen 2019 set-shifting p=0,47 null.'],
        'Hedef Tını' => ['zayif',
            'RitimOdak kılavuzu Etkinlik 4. Dış künye yok; tek yakın kaynak Erarslan 2026 ve orada '
            . 'yürütücü işlev ölçümü null (p=0,122). Seçici dikkat transferine doğrudan kanıt bulunamadı.'],
        'Metronoma eşlik' => ['guclu',
            'Ölçülen şey doğrudan senkronizasyon becerisinin kendisi; transfer iddiası YOK. Puyjarinet et al. 2017 (Sci. Reports) '
            . 'becerinin ölçülebilirliğini gösterir (kesitsel; antrenman çalışması değildir). Shaffer et al. 2001 tarihsel bir '
            . 'örnektir, randomize değildir, dayanak sayılmaz.'],
        'Vücut perküsyonu' => ['orta',
            'Yakın-alan kazanımı: çalışılan şey koordinasyonun kendisidir. Pedagojik gelenek. '
            . 'Genel motor yetiye kalıcı transfer iddia edilmez.'],
    ];

    $pdo->exec('BEGIN');
    try {
        $cs = $pdo->prepare('UPDATE akademik_calismalar SET tur = ?, ozet = ? WHERE lower(doi) = lower(?)');
        foreach ($calismaDuzelt as $doi => [$tur, $ozet]) { $cs->execute([$tur, $ozet, $doi]); }

        $ts = $pdo->prepare('UPDATE teknikler SET kanit_duzeyi = ?, kaynak = ? WHERE ad = ?');
        foreach ($teknikDuzelt as $ad => [$duzey, $kaynak]) { $ts->execute([$duzey, $kaynak, $ad]); }

        /* Yanlış bağ notları ve artık dayanaksız kalan bağlar */
        $pdo->prepare("UPDATE teknik_calismalari SET iliski_notu = ?
                        WHERE iliski_notu LIKE '%motor dürtüsellikte daha olumlu%'")
            ->execute(['Öğretimsel ve doğaçlama modeller arasında fark bulunmadı; yapılandırılmış tasarım pedagojik bir tercihtir.']);
        $pdo->prepare("UPDATE teknik_calismalari SET iliski_notu = ?
                        WHERE iliski_notu LIKE '%Tempo/kural geçişleri esneklik%'")
            ->execute(['Müdahale ritim senkronizasyonuydu; esneklik yalnız TMT-B ile ÖLÇÜLDÜ, çalışılmadı.']);
        $pdo->prepare("UPDATE teknik_calismalari SET iliski_notu = ?
                        WHERE iliski_notu LIKE '%Kural geçişi bilişsel esneklik bileşeni%'")
            ->execute(['Kural geçişi çalışmanın müdahale içeriğinde yok; yalnız ölçüm düzeyinde.']);
        /* Haigh 2024 bir EEG derlemesi — bu görev ailelerini hiç ele almıyor */
        $pdo->exec("DELETE FROM teknik_calismalari
                     WHERE calisma_id IN (SELECT id FROM akademik_calismalar
                                           WHERE lower(doi) = lower('10.1007/s10484-023-09618-x'))
                       AND (iliski_notu LIKE '%görev ailesi%' OR iliski_notu LIKE '%geçiş görevleri%')");

        site_text_set('sistem_bilimsel_duzeltme_2026', '1');
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }
}

/** MOXO bölümü metin varsayılanları (yalnız yoksa eklenir; düzenlemeler ezilmez). */
/** Deney + nabız bölümlerinin metinleri (mevcut kuruluma da INSERT OR IGNORE ile gelir). */
function seed_deney_texts(): void
{
    $st = db()->prepare('INSERT OR IGNORE INTO site_icerik (anahtar, deger) VALUES (?, ?)');
    $st->execute(['deney_aciklama',
        'Okumak yerine dinleyin: aşağıdaki üç mini deney, atölyede çalıştığımız işitsel '
        . 'zamanlama dünyasına açılan küçük pencereler. Kulaklıkla daha iyi; ses açık olsun. '
        . 'Bunlar eğlencelik gösterimlerdir — ölçüm ya da değerlendirme değildir.']);
    $st->execute(['nabiz_aciklama',
        'Tempo soyut bir sayı değil; bedenin ve müziğin ortak dili. Aşağıdaki her nokta '
        . 'kendi GERÇEK temposunda atıyor — üzerine dokunun, o tempoyu dinleyin.']);
    $st->execute(['deney_cta',
        'Kulağın ısındı mı? Atölyede bunların ölçümlü, programlı hâli çalışılıyor. '
        . 'Merak ettiklerinizi sormak için iletişime geçin.']);
}

function seed_moxo_texts(): void
{
    $st = db()->prepare('INSERT OR IGNORE INTO site_icerik (anahtar, deger) VALUES (?, ?)');
    $st->execute(['moxo_aciklama',
        'MOXO d-CPT; dikkat, zamanlama, dürtüsellik ve hiperaktivite başlıklarında dört ayrı '
        . 'indeks üreten, bilgisayar temelli nesnel bir dikkat performans testidir. '
        . 'İsteyen katılımcılar için program başında ve sonunda aynı standart koşullarda '
        . 'uygulanır; böylece dönem, nesnel bir ön–son karşılaştırmasıyla izlenebilir.']);
    $st->execute(['moxo_uygulayici',
        'Test, çalıştığımız deneyimli psikologlar tarafından uygulanır ve raporlanır; '
        . 'atölye eğitmeni klinik test uygulamaz ve yorumlamaz.']);
    $st->execute(['moxo_not',
        'MOXO tek başına tanı aracı değildir; sonuçlar yalnız yetkili uzman tarafından, '
        . 'çoklu kaynakla (gözlem, aile/öğretmen görüşü) birlikte değerlendirilir. '
        . 'Ön–son farkındaki değişim tek başına programa bağlanamaz; test-tekrar etkisi '
        . 'uzman raporunda ele alınır.']);
}

/**
 * Ev çalışması kütüphanesi: RitimOdak mikro uygulamaları (çocuk + yetişkin)
 * ve etkileşimli modüller. Yalnız tablo boşken çalışır.
 * Kanıt notları dürüst: kısa-sık pratik ilkesi güçlü, transfer iddiaları temkinli.
 */
function seed_home_exercises(): void
{
    $pdo = db();
    if ((int)$pdo->query('SELECT COUNT(*) FROM ev_calismalari')->fetchColumn() > 0) {
        // Sonradan eklenen çalışmalar mevcut kuruluma da (bir kez) eklenir; ad
        // benzersiz olduğundan INSERT OR IGNORE eğitmen düzenlemelerini ezmez.
        $pdo->prepare("INSERT OR IGNORE INTO ev_calismalari
                (ad, tur, kitle, hafta_onerisi, sure_dk, bpm, seviye, aciklama, veli_yonerge, hedef_beceri, kanit_notu, aktif, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)")
            ->execute(['İçsel Ritim (sessizlik merdiveni)', 'icsel_ritim', 'hepsi', 5, 2, 72, 2,
                'Metronom giderek daha çok susar (%0 → %50 → %75); sen her vuruşta vurmaya devam edersin. '
                . 'Sessiz vuruşlardaki sapman ölçülür — içsel sayımın fotoğrafı çekilir.',
                'Haftada 2–3 kez yeterli; skor kişisel izlemedir, yarış değildir.',
                'İçsel tempo, sessiz sürdürme',
                'Rastgele susturma Time Guru yaklaşımı; senkronizasyon-devam ölçümü BAASTA ailesi (Puyjarinet 2017).',
                now_str()]);
        return;
    }

    $satirlar = [
        // [ad, tur, kitle, hafta, sure_dk, bpm, seviye, aciklama, veli_yonerge, hedef_beceri, kanit_notu]

        // ---- Etkileşimli modüller (siteden çalışır, skorlu) ----
        ['Günlük Nabız (mini metronom)', 'metronom', 'hepsi', 1, 3, 66, 1,
         'Metronom çalar; süre boyunca elinle dizine veya masaya eşlik et. Amaç hız değil, düzen.',
         'Sessiz bir köşe yeterli; cihaz sesi rahat duyulacak kadar açık olsun.',
         'Sabit vuruş, senkronizasyon',
         'Kısa-sık pratik ilkesi (dağıtılmış öğrenme) güçlü; senkronizasyon doğrudan çalışılır.'],
        ['Vuruş Tutturma (ev mini testi)', 'vurus_tutturma', 'hepsi', 5, 2, 72, 1,
         '4 vuruş dinle, 8 vuruş metronomla birlikte vur, 8 vuruş metronom susunca içinden sayarak devam et. Skorun kaydedilir.',
         'Haftada 2–3 kez yeterli; skor bir yarış değil, kişisel izleme.',
         'Zamanlama, içsel tempo',
         'Atölyedeki protokolün kısa ev sürümü; oyunlaştırılmış ev ritim eğitimi uygulanabilirliği gösterildi (Jamey et al. 2025).'],
        ['Ritim Okuma — sayarak vur', 'ritim_okuma', 'hepsi', 3, 3, 60, 1,
         'Ekranda 2 ölçülük ritim görünür ("1 ve 2 ve", üçlemede "1-le-me"). Metronomla sayarak tam yerinde vur. Göz–el eşgüdümü ve nota okumaya ilk adım.',
         'Yüksek ses gerekmez; masa/diz vuruşu yeterli.',
         'Ritim okuma, alt bölünme sayma, göz–el eşgüdümü',
         'Sayma sistemleri (1-e-ve-a, Kodály, Takadimi) köklü pedagojik gelenek; sistemler arası üstünlük kanıtı zayıf belgelenmiş.'],

        ['İçsel Ritim (sessizlik merdiveni)', 'icsel_ritim', 'hepsi', 5, 2, 72, 2,
         'Metronom giderek daha çok susar (%0 → %50 → %75); sen her vuruşta vurmaya devam edersin. Sessiz vuruşlardaki sapman ölçülür — içsel sayımın fotoğrafı çekilir.',
         'Haftada 2–3 kez yeterli; skor kişisel izlemedir, yarış değildir.',
         'İçsel tempo, sessiz sürdürme',
         'Rastgele susturma Time Guru yaklaşımı; senkronizasyon-devam ölçümü BAASTA ailesi (Puyjarinet 2017).'],

        // ---- Çocuk izi mikro uygulamaları (RitimOdak-Ö) ----
        ['Masa Nabzı', 'serbest', 'cocuk', 1, 3, 66, 1,
         'Günde 3 dakika masa üstünde sabit vuruş. Ritim bozulursa durup yeniden başla.',
         'Aynı saate bağlayın (örn. akşam yemeği öncesi); bitince tek cümle övgü: "düzenliydi".',
         'Sabit vuruş', 'RitimOdak H1 mikro uygulaması; kısa-sık pratik ilkesi.'],
        ['Başla–Dur Turları', 'serbest', 'cocuk', 2, 4, 0, 1,
         'Evde 5 tur: bir aile üyesi işaret verir; işarette başla, işarette aniden dur.',
         'İşareti siz verin; duruşun "aniden" olması önemli, hız değil.',
         'Dürtü kontrolü', 'RitimOdak H2; ebeveyn katılımı uyumu artırır.'],
        ['Ritim Çizimi', 'serbest', 'cocuk', 3, 5, 0, 1,
         'Duyduğun veya uydurduğun 3 kısa ritmi sembollerle (çizgi/nokta) kâğıda çiz.',
         'Semboller serbest; "doğru yazım" aranmaz.',
         'Çalışma belleği, sıralama', 'RitimOdak H3.'],
        ['Sağ–Sol Düzeni', 'serbest', 'cocuk', 4, 2, 60, 1,
         '2 dakika sağ-sol el değişimli vuruş düzeni; şaşırınca yavaşlat, durma.',
         'Yavaş tempo yeterli.', 'Motor koordinasyon', 'RitimOdak H4.'],
        ['Ders Öncesi Odak Ritüeli', 'serbest', 'cocuk', 5, 1, 0, 1,
         'Derse/ödevine başlamadan önce 4 vuruşluk kısa ritim + derin nefes.',
         'Ritüeli hatırlatın ama zorlamayın; kısa olması önemli.',
         'Göreve geçiş', 'RitimOdak H5; rutine gömme uyumu artırır.'],
        ['Bekle–Dinle–Vur Kartı', 'serbest', 'cocuk', 6, 4, 0, 2,
         'Kart rengine göre: hemen tekrar / 2 vuruş bekleyip tekrar / hiç vurma. 5 deneme.',
         'Kartları siz gösterin; "beklemek" başarıdır, övün.',
         'Tepki erteleme', 'RitimOdak H6.'],
        ['İki Kural Tekrarı', 'serbest', 'cocuk', 7, 3, 0, 2,
         'Oturumda öğrenilen iki kuralı sözle söyle, sonra birer kez uygula.',
         'Kuralı çocuğun kendisinin söylemesi önemli.',
         'Bilişsel esneklik', 'RitimOdak H7.'],
        ['Tek Görev + Çift Görev', 'serbest', 'cocuk', 8, 7, 60, 2,
         'Önce 5 dk tek görev (sadece ritim), sonra 2 dk ritim + kategori sayma (hayvan, renk…).',
         'Çift görev kısa tutulur; bozulursa tek göreve dönün.',
         'Bölünmüş dikkat', 'RitimOdak H8.'],
        ['İkili Echo (Aile ile)', 'serbest', 'cocuk', 9, 5, 0, 1,
         'Bir aile üyesiyle karşılıklı: biri kısa ritim çalar, diğeri aynen tekrar eder; roller değişir.',
         'Eğlence önde; hata olursa gülüp yeniden.',
         'İşitsel bellek, sıra alma', 'RitimOdak H9; ebeveyn katılımı.'],
        ['Ritim Planı Yazımı', 'serbest', 'cocuk', 10, 6, 0, 2,
         '4 ölçülük kendi ritmini işaretlerle yaz; yarın aynı kâğıttan çalmayı dene.',
         'Plan "okunabilir" olsun yeter.', 'Planlama', 'RitimOdak H10.'],
        ['Ödev Öncesi 60 Saniye', 'serbest', 'cocuk', 11, 1, 66, 1,
         'Ödeve oturmadan önce 60 saniye sabit ritim; sonra süre tutarak göreve başla.',
         'Süreyi birlikte kurun; ritim sırasında başka müzik açık olmasın.',
         'Göreve geçiş, sürdürme', 'RitimOdak H11.'],
        ['Kişisel Odak Kartım', 'serbest', 'cocuk', 12, 10, 0, 1,
         'Bu dönemde işine yarayan 2 stratejiyi kendi cümlelerinle karta yaz (örn. "önce kuralı söylerim").',
         'Stratejileri çocuğun kendi bulması değerli; siz yalnız yazıya yardım edin.',
         'Öz-düzenleme farkındalığı', 'RitimOdak H12.'],

        // ---- Yetişkin izi mikro uygulamaları (RitimOdak-Y) ----
        ['3×5 Odak Bloğu Kaydı', 'serbest', 'yetiskin', 1, 15, 0, 1,
         'Gün içinde üç kez 5 dakikalık tek işe odak bloğu; her birinin sonunda tek satır not.',
         '', 'Odak bloğu, öz-izleme', 'RitimOdak-Y H1.'],
        ['Dur – Ne Yapıyorum – Seç', 'serbest', 'yetiskin', 2, 2, 0, 1,
         'Günde 3 kez: dur, "şu an ne yapıyorum?" sorusunu yanıtla, bilinçli olarak sürdür ya da değiştir.',
         '', 'Tepki başlatma-durdurma', 'RitimOdak-Y H2.'],
        ['10 Saniye Bekleme Kuralı', 'serbest', 'yetiskin', 3, 1, 0, 1,
         'E-posta/mesaj dürtüsünde göndermeden/açmadan önce 10 saniye bekle.',
         '', 'Dürtü erteleme', 'RitimOdak-Y H3.'],
        ['Üçlü Blok Listesi', 'serbest', 'yetiskin', 4, 5, 0, 1,
         'Kısa yapılacakları 3\'erli bloklara ayır; her bloğu tek seferde bitir.',
         '', 'Çalışma belleği, planlama', 'RitimOdak-Y H4.'],
        ['15 Dakika Tek Görev + Hata İşareti', 'serbest', 'yetiskin', 5, 15, 0, 2,
         '15 dakika tek görev; dikkat her kaydığında kâğıda küçük bir işaret koy ve göreve dön.',
         '', 'Sürdürülen dikkat, hata farkındalığı', 'RitimOdak-Y H5.'],
        ['Bir Çeldirici Kaldır', 'serbest', 'yetiskin', 6, 2, 0, 1,
         'Çalışma alanından her gün bilinçli olarak bir çeldiriciyi kaldır (bildirim, sekme, eşya).',
         '', 'Çeldirici yönetimi', 'RitimOdak-Y H6.'],
        ['4 Vuruş Reset', 'serbest', 'yetiskin', 7, 1, 60, 1,
         'Görevler arası geçişte 4 vuruşluk kısa ritim + nefes; sonra yeni göreve başla.',
         '', 'Görev geçişi', 'RitimOdak-Y H7.'],
        ['Önce Tek, Sonra Kısa Çift', 'serbest', 'yetiskin', 8, 10, 0, 2,
         'Önce tek görev önceliği; ardından bilinçli, kısa bir çift görev denemesi ve tek göreve dönüş.',
         '', 'Önceliklendirme', 'RitimOdak-Y H8.'],
        ['60 Saniye Yavaş Ritim', 'serbest', 'yetiskin', 9, 1, 50, 1,
         'Gerginlik/hızlanma fark edince 60 saniye yavaş tempoda vuruş ile tempoyu düşür.',
         '', 'Uyarılma düzenleme', 'RitimOdak-Y H9.'],
        ['Görevi Adımlara Ayır', 'serbest', 'yetiskin', 10, 5, 0, 2,
         'Bir iş görevini ritimsel adımlara ayır (başla-sürdür-kapat) ve adım adım yürüt.',
         '', 'Planlama', 'RitimOdak-Y H10.'],
        ['25 Dakika Odak + Dönüş Kaydı', 'serbest', 'yetiskin', 11, 25, 0, 2,
         '25 dakikalık odak bloğu; her dikkat dağılmasında göreve dönüş süreni kısa not et.',
         '', 'Odak bloğu, göreve dönüş', 'RitimOdak-Y H11.'],
        ['Haftalık 3 Mikro Plan', 'serbest', 'yetiskin', 12, 10, 0, 1,
         'Önümüzdeki hafta için 3 mikro uygulama seç ve takvimine yaz.',
         '', 'Sürdürülebilirlik', 'RitimOdak-Y H12.'],
    ];

    $st = $pdo->prepare('INSERT INTO ev_calismalari
            (ad, tur, kitle, hafta_onerisi, sure_dk, bpm, seviye, aciklama, veli_yonerge, hedef_beceri, kanit_notu, aktif, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
    $zaman = now_str();
    foreach ($satirlar as $s) {
        $st->execute([$s[0], $s[1], $s[2], $s[3], $s[4], $s[5] ?: 66, $s[6], $s[7], $s[8], $s[9], $s[10], $zaman]);
    }
}

/**
 * Bilim kartları başlangıç içeriği. Yalnız tablo boşken çalışır;
 * eğitmenin panelden yaptığı düzenlemeler asla ezilmez.
 */
function seed_articles(): void
{
    $pdo = db();
    if ((int)$pdo->query('SELECT COUNT(*) FROM site_makaleler')->fetchColumn() > 0) {
        return;
    }
    $makaleler = [
        // [baslik, kunye, bulgu, yansima, rozet]
        ['Senkronizasyon ölçülebilir bir beceridir',
         'Puyjarinet et al. 2017, Scientific Reports',
         'Dikkat güçlüğü yaşayan çocuk ve yetişkinlerin dışsal bir vuruşa eşzamanlanmada belirgin zorlandığını gösterdi.',
         '"Nabzı Bul" ve metronoma eşlik — ölçtüğümüz şey doğrudan senkronizasyonun kendisi.',
         'guclu'],
        ['Ritim temelli eğitim ve yürütücü işlevler',
         'Frischen et al. 2019, Frontiers in Integrative Neuroscience',
         'Okul öncesinde ritim temelli müzik eğitimi, ezgi temelliye göre yürütücü işlev görevlerinde daha belirgin gelişim gösterdi.',
         'Çağrı–cevap ve ritmik dizi tekrarı blokları bu desenden türetildi.',
         'orta'],
        ['Erken müzik programı randomize denemede',
         'Bentley et al. 2023, Developmental Science',
         "RCT'de müzik programına katılan çocuklarda öz-düzenleme ve okul hazırlığı ölçümlerinde iyileşme raporlandı.",
         'Beden perküsyonu ve dur–devam oyunlarının yapısı bu programlarla paralel.',
         'orta'],
        ['Müzik eğitimi ve ket vurma (inhibisyon)',
         'Jamey et al. 2024, Cognition (meta-analiz)',
         'Meta-analiz, müzik eğitiminin çocuklarda tepki kontrolü görevlerine küçük-orta düzeyde katkısına işaret etti.',
         'Bekle–Dinle–Vur ve Hedef Tını — yalnız hedefe tepki, hedef dışını tutma.',
         'orta'],
        ['Ritmik oyunla bilişsel esneklik',
         'Zhu 2026, Frontiers in Public Health',
         'Ritmik müzik oyunu müdahalesi, yürütücü işlev ve duyu-motor ölçümlerinde gelişme bildirdi.',
         'Kural Değiştir ve tempo geçişi çalışmaları — işaretle kural tersine döner.',
         'orta'],
        ['Metronom temelli antrenman',
         'Shaffer et al. 2001, AJOT',
         'Etkileşimli metronom antrenmanı sonrası bazı motor ve dikkat ölçümlerinde gelişme raporlandı; tasarım sınırlılıkları belirgin.',
         'Vuruş tutturma protokolümüzün esin kaynağı — skorları yalnız kendi içimizde izleriz.',
         'zayif'],
    ];
    $st = $pdo->prepare('INSERT INTO site_makaleler (baslik, kunye, bulgu, yansima, rozet, sira, gorunur)
                         VALUES (?, ?, ?, ?, ?, ?, 1)');
    foreach ($makaleler as $i => $m) {
        $st->execute([$m[0], $m[1], $m[2], $m[3], $m[4], $i + 1]);
    }
}

/**
 * Eğitim planı şablonları: RitimOdak-Ö (8–15 yaş) ve RitimOdak-Y (yetişkin),
 * 12 hafta × A/B oturumları, teknikler ada göre bağlanır. Yalnız tablo boşken çalışır.
 */
function seed_templates(): void
{
    $pdo = db();
    if ((int)$pdo->query('SELECT COUNT(*) FROM plan_sablonlari')->fetchColumn() > 0) {
        return;
    }
    $teknikId = $pdo->query('SELECT ad, id FROM teknikler')->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!$teknikId) { return; }

    // [haftaNo => [hedef, A => [[ad, dk, not], …], B => […]]]
    $cocuk = [
        1 => ['Güvenli vuruş ve başlangıç',
            [['Vücut perküsyonu', 8, 'Isınma: el-diz, kolay başarı'], ['Metronoma eşlik', 10, '60–72 BPM nabız'], ['Çağrı–cevap', 10, '2–3 vuruşluk kalıplar'], ['Serbest doğaçlama', 10, 'Oda ve ses kurallarıyla tanışma']],
            [['Vücut perküsyonu', 8, ''], ['Nabzı Bul', 8, 'Görsel ayak izi kartıyla'], ['Çağrı–cevap', 10, ''], ['Metronoma eşlik', 10, 'Eşlik + kısa susma']]],
        2 => ['Başla–dur',
            [['Metronoma eşlik', 8, ''], ['Dur–devam', 10, 'Yeşil-sarı-kırmızı kart'], ['Çağrı–cevap', 8, ''], ['Serbest doğaçlama', 10, '']],
            [['Vücut perküsyonu', 8, ''], ['Dur–devam', 10, 'Yalnız hedef işarette çal'], ['Hedef Tını', 8, ''], ['Nabzı Bul', 8, '']]],
        3 => ['Ritim belleği',
            [['Metronoma eşlik', 8, ''], ['Ritmik dizi tekrarı', 10, '2–4 birimli diziyi kopyala'], ['Çağrı–cevap', 8, ''], ['Serbest doğaçlama', 10, '']],
            [['Vücut perküsyonu', 8, ''], ['Ritim Hafıza Zinciri', 12, 'Dizinin son vuruşunu değiştir'], ['Dur–devam', 8, ''], ['Nabzı Bul', 8, '']]],
        4 => ['İki el ve koordinasyon',
            [['Vücut perküsyonu', 10, 'Sağ-sol dönüşüm'], ['Metronoma eşlik', 8, ''], ['Ritmik dizi tekrarı', 10, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 10, 'Çapraz beden + aksan vuruşu'], ['Ritim Hafıza Zinciri', 10, ''], ['Çağrı–cevap', 8, ''], ['Dur–devam', 8, '']]],
        5 => ['Seçici dikkat',
            [['Metronoma eşlik', 8, ''], ['Hedef Tını', 10, 'Yalnız hedef tınıya cevap'], ['Ritmik dizi tekrarı', 8, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Hedef Tını', 10, 'Düşük düzey görsel çeldirici'], ['Dur–devam', 8, ''], ['Nabzı Bul', 8, '']]],
        6 => ['Dürtü kontrolü',
            [['Metronoma eşlik', 8, ''], ['Bekle–Dinle–Vur', 12, 'Kırmızı kartta hiç cevap yok'], ['Çağrı–cevap', 8, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Bekle–Dinle–Vur', 12, 'İki vuruş gecikmeli cevap'], ['Hedef Tını', 8, ''], ['Nabzı Bul', 8, '']]],
        7 => ['Kural değiştirme',
            [['Metronoma eşlik', 8, ''], ['Kural Değiştir', 12, 'Kart rengine göre el değiştir'], ['Ritmik dizi tekrarı', 8, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Kural Değiştir', 12, 'İşaretle kural tersine döner'], ['Tempo değişimi takibi', 8, ''], ['Dur–devam', 8, '']]],
        8 => ['Çift görev',
            [['Metronoma eşlik', 8, ''], ['Çift Hat', 12, 'Ritim + kategori kelimesi'], ['Bekle–Dinle–Vur', 8, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Çift Hat', 12, 'Görsel hedef sayma'], ['Kural Değiştir', 8, ''], ['Nabzı Bul', 8, '']]],
        9 => ['Grup senkronisi',
            [['Metronoma eşlik', 8, ''], ['Daire Senkroni', 12, 'Giriş sırası, sıra kartı'], ['Katmanlı grup ritmi', 10, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Daire Senkroni', 12, 'Kanon; dinlemeden girmeme'], ['Çağrı–cevap', 8, ''], ['Hata Sonrası Dönüş', 8, '']]],
        10 => ['Planlama ve üretim',
            [['Metronoma eşlik', 8, ''], ['Ritim Planla–Uygula–Onar', 15, '4 ölçülük ritim tasarla'], ['Daire Senkroni', 10, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Ritim Planla–Uygula–Onar', 15, 'Prova → hata bul → onar → sun'], ['Hata Sonrası Dönüş', 8, ''], ['Nabzı Bul', 8, '']]],
        11 => ['Sınıf görevine aktarım',
            [['Metronoma eşlik', 8, ''], ['Ritimden Sessiz Göreve', 15, '8 dakikalık sessiz görev'], ['Dur–devam', 8, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Ritimden Sessiz Göreve', 15, 'Çeldirici kontrollü deneme'], ['Hedef Tını', 8, ''], ['Nabzı Bul', 8, '']]],
        12 => ['Bütünleştirme ve kapanış',
            [['Metronoma eşlik', 8, 'Dönem sonu eşliği'], ['Kural Değiştir', 8, ''], ['Ritim Hafıza Zinciri', 10, ''], ['Daire Senkroni', 10, 'Beceri parkuru']],
            [['Vücut perküsyonu', 8, ''], ['Nabzı Bul', 8, 'Dönem sonu karşılaştırması'], ['Ritim Planla–Uygula–Onar', 12, 'Küçük gösterim'], ['Serbest doğaçlama', 10, 'Kapanış ve geri bildirim']]],
    ];

    $yetiskin = [
        1 => ['Başlangıç ve ritim profili',
            [['Vücut perküsyonu', 8, 'Masa tapping ısınması'], ['Metronoma eşlik', 12, 'Rahat tempoyu katılımcı seçer'], ['Nabzı Bul', 10, ''], ['Çağrı–cevap', 10, ''], ['Serbest doğaçlama', 10, '']],
            [['Vücut perküsyonu', 8, ''], ['Nabzı Bul', 12, 'İç/dış nabız, sessizlikten dönüş'], ['Metronoma eşlik', 12, ''], ['Ritmik dizi tekrarı', 10, ''], ['Serbest doğaçlama', 8, '']]],
        2 => ['Tepki başlatma-durdurma',
            [['Metronoma eşlik', 10, ''], ['Dur–devam', 12, 'İşaretle başla-dur'], ['Çağrı–cevap', 10, ''], ['Hedef Tını', 10, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Dur–devam', 12, 'Hedef olmayan işarette tepkiyi tut'], ['Bekle–Dinle–Vur', 12, ''], ['Nabzı Bul', 10, ''], ['Serbest doğaçlama', 8, '']]],
        3 => ['Dürtü kontrolü',
            [['Metronoma eşlik', 10, ''], ['Bekle–Dinle–Vur', 14, 'No-go + gecikmeli cevap'], ['Hedef Tını', 10, ''], ['Ritmik dizi tekrarı', 10, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Bekle–Dinle–Vur', 14, 'Hız yerine doğruluk stratejisi'], ['Dur–devam', 10, ''], ['Nabzı Bul', 10, ''], ['Serbest doğaçlama', 8, '']]],
        4 => ['Çalışma belleği',
            [['Metronoma eşlik', 10, ''], ['Ritmik dizi tekrarı', 12, '4–6 birimli dizi'], ['Ritim Hafıza Zinciri', 14, ''], ['Çağrı–cevap', 8, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Ritim Hafıza Zinciri', 14, 'Ters sırala, aksanı taşı'], ['Ritmik dizi tekrarı', 12, ''], ['Dur–devam', 8, ''], ['Serbest doğaçlama', 8, '']]],
        5 => ['Sürdürülen dikkat',
            [['Metronoma eşlik', 14, '8–12 dakikalık sürdürme bloğu'], ['Nabzı Bul', 10, ''], ['Hedef Tını', 10, ''], ['Hata Sonrası Dönüş', 10, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Metronoma eşlik', 14, ''], ['Hata Sonrası Dönüş', 12, 'Hata sonrası ritme dönüş kaydı'], ['Ritmik dizi tekrarı', 10, ''], ['Serbest doğaçlama', 8, '']]],
        6 => ['Çeldirici yönetimi',
            [['Metronoma eşlik', 10, ''], ['Hedef Tını', 14, 'Düşük işitsel/görsel çeldirici'], ['Çift Hat', 12, ''], ['Dur–devam', 8, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Hedef Tını', 14, 'Çeldirici seçme ve kapatma stratejisi'], ['Bekle–Dinle–Vur', 10, ''], ['Nabzı Bul', 10, ''], ['Serbest doğaçlama', 8, '']]],
        7 => ['Bilişsel esneklik',
            [['Metronoma eşlik', 10, ''], ['Kural Değiştir', 14, 'Tempo ve el kuralı geçişi'], ['Tempo değişimi takibi', 12, ''], ['Ritmik dizi tekrarı', 8, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Kural Değiştir', 14, 'Beklenmedik işarette yeni kalıp'], ['Tempo değişimi takibi', 12, ''], ['Hata Sonrası Dönüş', 8, ''], ['Serbest doğaçlama', 8, '']]],
        8 => ['Çift görev',
            [['Metronoma eşlik', 10, ''], ['Çift Hat', 14, 'Ritim + kategori/hesap'], ['Kural Değiştir', 10, ''], ['Hedef Tını', 8, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Çift Hat', 14, 'İş akışı simülasyonu'], ['Bekle–Dinle–Vur', 10, ''], ['Nabzı Bul', 10, ''], ['Serbest doğaçlama', 8, '']]],
        9 => ['Tempo ve düzenleme',
            [['Tempo değişimi takibi', 12, 'Hızlı uyarılmayı fark et, tempoyu indir'], ['Metronoma eşlik', 10, ''], ['Hata Sonrası Dönüş', 12, ''], ['Daire Senkroni', 10, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Tempo değişimi takibi', 12, ''], ['Hata Sonrası Dönüş', 12, 'Hata sonrası yeniden giriş'], ['Katmanlı grup ritmi', 12, ''], ['Serbest doğaçlama', 8, '']]],
        10 => ['Planlama ve liderlik',
            [['Metronoma eşlik', 8, ''], ['Ritim Planla–Uygula–Onar', 16, 'Grup ritmi tasarla'], ['Daire Senkroni', 12, ''], ['Katmanlı grup ritmi', 10, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Ritim Planla–Uygula–Onar', 16, 'Görev dağıt, prova, analiz'], ['Daire Senkroni', 12, ''], ['Hata Sonrası Dönüş', 8, ''], ['Serbest doğaçlama', 8, '']]],
        11 => ['İş/öğrenim aktarımı',
            [['Metronoma eşlik', 8, ''], ['Ritimden Sessiz Göreve', 20, '15 dakikalık sessiz iş bloğu'], ['Dur–devam', 8, ''], ['Hedef Tını', 8, ''], ['Serbest doğaçlama', 8, '']],
            [['Vücut perküsyonu', 8, ''], ['Ritimden Sessiz Göreve', 20, 'Toplantı/çalışma simülasyonu'], ['Kural Değiştir', 10, ''], ['Nabzı Bul', 8, ''], ['Serbest doğaçlama', 8, '']]],
        12 => ['Sürdürülebilir plan ve kapanış',
            [['Metronoma eşlik', 10, 'Dönem sonu eşliği'], ['Kural Değiştir', 10, ''], ['Ritim Hafıza Zinciri', 10, ''], ['Daire Senkroni', 12, 'Beceri parkuru'], ['Serbest doğaçlama', 10, '']],
            [['Vücut perküsyonu', 8, ''], ['Nabzı Bul', 10, 'Dönem sonu karşılaştırması'], ['Ritim Planla–Uygula–Onar', 14, 'Kişisel kombinasyon'], ['Katmanlı grup ritmi', 10, ''], ['Serbest doğaçlama', 10, 'Kapanış ve bakım planı']]],
    ];

    $sablonlar = [
        ['RitimOdak-Ö — Çocuk & Genç (8–15 yaş)',
         '12 haftalık öğrenci izi: güvenli vuruştan sınıf görevine aktarıma kademeli akış. 45 dakikalık oturumlara göre süreler.',
         'cocuk', 45, $cocuk],
        ['RitimOdak-Y — Yetişkin (18+)',
         '12 haftalık yetişkin izi: ritim profili çıkarma, tepki kontrolü, çift görev ve iş/öğrenim aktarımı. 60 dakikalık oturumlar.',
         'yetiskin', 60, $yetiskin],
    ];

    // Haftanın protokolü: A oturumlarında ölçüm önerisi (RitimOdak ölçüm ritmiyle uyumlu)
    $protokolHaftalari = [1 => 'vurus_tutturma', 3 => 'ritim_okuma', 5 => 'icsel_ritim',
                          6 => 'aksak_bulma', 9 => 'bpm_bulma', 12 => 'vurus_tutturma'];

    $zaman = now_str();
    $si = $pdo->prepare('INSERT INTO plan_sablonlari (ad, aciklama, hedef_kitle, sure_dk, created_at) VALUES (?, ?, ?, ?, ?)');
    $oi = $pdo->prepare('INSERT INTO sablon_oturumlari (sablon_id, hafta_no, oturum_adi, hedef, protokol) VALUES (?, ?, ?, ?, ?)');
    $ti = $pdo->prepare('INSERT INTO sablon_teknikleri (sablon_oturum_id, teknik_id, sira, sure_dk, uygulama_notu) VALUES (?, ?, ?, ?, ?)');

    $pdo->exec('BEGIN');
    try {
        foreach ($sablonlar as [$ad, $aciklama, $kitle, $sure, $haftalar]) {
            $si->execute([$ad, $aciklama, $kitle, $sure, $zaman]);
            $sablonId = (int)$pdo->lastInsertId();
            foreach ($haftalar as $haftaNo => [$hedef, $a, $b]) {
                foreach (['A' => $a, 'B' => $b] as $oturumAdi => $teknikListe) {
                    $oi->execute([$sablonId, $haftaNo, $oturumAdi, $hedef,
                                  $oturumAdi === 'A' ? ($protokolHaftalari[$haftaNo] ?? '') : '']);
                    $oturumId = (int)$pdo->lastInsertId();
                    $sira = 1;
                    foreach ($teknikListe as [$teknikAd, $dk, $not]) {
                        if (!isset($teknikId[$teknikAd])) { continue; }
                        $ti->execute([$oturumId, (int)$teknikId[$teknikAd], $sira++, $dk, $not]);
                    }
                }
            }
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }
}

/**
 * RitimOdak şablonlarına haftalık ev görevi bağları (BİR KEZ kurulur; eğitmenin
 * sildiği bağlar dirilmez — işaret site_icerik'te tutulur). Eşleme, ev
 * çalışmalarının kendi hafta_onerisi + kitle alanlarından türetilir.
 */
function seed_template_home_tasks(): void
{
    if (site_text('sistem_sablon_ev_seed') === '1') { return; }
    $pdo = db();
    $sablonlar = $pdo->query("SELECT id, hedef_kitle FROM plan_sablonlari WHERE ad LIKE 'RitimOdak-%'")->fetchAll();
    if ($sablonlar) {
        $ins = $pdo->prepare('INSERT OR IGNORE INTO sablon_ev_gorevleri (sablon_id, hafta_no, calisma_id) VALUES (?, ?, ?)');
        $sec = $pdo->prepare("SELECT id, hafta_onerisi FROM ev_calismalari
                               WHERE aktif = 1 AND hafta_onerisi BETWEEN 1 AND 52 AND kitle IN (?, 'hepsi')");
        foreach ($sablonlar as $s) {
            $sec->execute([$s['hedef_kitle'] === 'yetiskin' ? 'yetiskin' : 'cocuk']);
            foreach ($sec->fetchAll() as $c) {
                $ins->execute([(int)$s['id'], (int)$c['hafta_onerisi'], (int)$c['id']]);
            }
        }
    }
    site_text_set('sistem_sablon_ev_seed', '1');
}
