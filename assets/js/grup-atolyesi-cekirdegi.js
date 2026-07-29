(function (root, factory) {
  'use strict';
  var api = factory();
  if (typeof module === 'object' && module.exports) { module.exports = api; }
  if (root) { root.GrupAtolyesiCekirdegi = api; }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  var PROTOKOLLER = [
    {
      id: 'ortak-nabiz',
      ad: 'Ortak Nabız Çemberi',
      ikon: '◎',
      ozet: 'Grup aynı nabzı kurar, kısa çağrı-cevaplar yapar ve sırayı sessiz işaretle devreder.',
      kanit: 'orta',
      dayanak: 'Kişilerarası senkronun sosyal yakınlık ve iş birliğiyle ilişkisi umut vericidir; atölye dışına aktarım garanti değildir.',
      uygunluk: 'Oturarak veya ayakta · başlangıç düzeyi',
      varsayilanBpm: 64,
      hareketli: false,
      adimlar: [
        ['Çerçeve ve seçim', 'Bugün herkes çalabilir, yalnız dinleyebilir veya kısa mola verebilir. El işaretiyle durma kuralını gösterin.', 'Seçim hakkını açıkça söyleyin; solo zorunlu değildir.', 2],
        ['Tını keşfi', 'İki güvenli yüzeyden birini seçin. Grup sırayla tek vuruşla kendi tınısını duyursun.', 'Yüksek ses yerine tını farkını dinletin.', 3],
        ['Ortak nabız', 'Dört hazırlık vuruşundan sonra herkes yalnız ana vuruşlara eşlik etsin.', 'İlk vuruş belirgin; tempo sabit ve rahat.', 4],
        ['Çağrı-cevap', 'Eğitmen iki ölçülük kolay bir kalıp çalsın; grup bir ölçü bekleyip aynısını tekrarlasın.', 'Uzunluğu ancak iki rahat tekrardan sonra artırın.', 4],
        ['Sıra devri', 'Her katılımcı isterse dört vuruşluk pencereyi yönetir; sessiz işaretle sırayı yanındakine geçirir.', 'Değerlendirme yok; grubun geri dönüşünü destekleyin.', 4],
        ['Kapanış', 'Sesleri azaltarak dört ortak vuruşla bitirin. Herkes başparmak ölçeğiyle “kolay / orta / zor” seçsin.', 'Kişi adıyla performans sıralaması yapmayın.', 2]
      ]
    },
    {
      id: 'iki-tini',
      ad: 'İki Tını Orkestrası',
      ikon: '◐',
      ozet: 'Kalın ve ince tını grupları ortak ölçüde farklı zamanları paylaşır, sonra roller değişir.',
      kanit: 'yok',
      dayanak: 'Tını ayırt etme ve birlikte müzik yapma açısından pedagojik değeri vardır; sosyal veya bilişsel sonuç iddiası taşımaz.',
      uygunluk: 'Oturarak · başlangıç düzeyi',
      varsayilanBpm: 60,
      hareketli: false,
      adimlar: [
        ['Güvenli tını seçimi', 'Keskin, kırılabilir veya kolay devrilen nesneleri ayırın. Her katılımcı iki tınıyı düşük sesle denesin.', 'Nesneleri paylaşırken yüzeyleri temizleyin.', 3],
        ['İki grup', 'Katılımcılar istedikleri tınıya göre Kalın ve İnce grubunu seçsin.', 'Atamayı zorlamayın; gruplar eşit olmak zorunda değildir.', 2],
        ['Dört vuruşlu düzen', 'Kalın grup 1 ve 3; İnce grup 2 ve 4. Dört hazırlık vuruşundan sonra başlayın.', 'Eğitmen eliyle bir sonraki grubu önceden göstersin.', 4],
        ['Boşluk ekle', 'Bir zamanı sessiz bırakın. Grup, boşluğu doldurmadan ortak nabzı bedende sürdürsün.', 'Sus da ritmin parçasıdır; yanlış diye anlık kesmeyin.', 3],
        ['Rol değişimi', 'Enstrüman taşımadan görevleri değiştirin: Kalın grup İnce zamanlarını, İnce grup Kalın zamanlarını çalsın.', 'Tek değişken rol olsun; tempo sabit kalsın.', 3],
        ['Ortak final', 'Gruplar kendi iki ölçülük düzenini seçsin ve birlikte iki kez çalsın.', 'Sonucu “aynı anda bitirebildik mi?” diye gözleyin.', 3]
      ]
    },
    {
      id: 'ritim-tamamlama',
      ad: 'Ritim Tamamlama Çemberi',
      ikon: '↪',
      ozet: 'Lider ölçünün bir bölümünü çalar; grup kalan zamanı dinleyerek tamamlar ve liderliği döndürür.',
      kanit: 'orta',
      dayanak: 'Doğrudan işitsel sıralama ve zamanlama görevi çalışılır; başka becerilere aktarım konusunda iddia sınırlıdır.',
      uygunluk: 'Oturarak · orta düzey',
      varsayilanBpm: 68,
      hareketli: false,
      adimlar: [
        ['Ortak ölçü', 'Dört vuruşu birlikte sayın ve yalnız 1. vuruşu çalın.', 'Başlamadan önce iki tam ölçü dinleme verin.', 2],
        ['Tek boşluğu tamamla', 'Lider 1-2-3 zamanlarını çalsın; grup yalnız 4. zamanı tamamlasın.', 'Cevabı önceden el işaretiyle hazırlayın.', 3],
        ['İki boşluğu tamamla', 'Lider 1 ve 3; grup 2 ve 4. Roller iki tur sonra değişsin.', 'Tempo değil görev dağılımı değişsin.', 4],
        ['Kısa çağrı-cevap', 'Lider bir ölçü çalsın, grup bir ölçü sonra tekrar etsin.', 'Kalıp en fazla dört olayla başlasın.', 4],
        ['Lider rotasyonu', 'İsteyen katılımcı bir sonraki ölçüyü başlatsın; pas deme seçeneği her zaman açık olsun.', '“En iyi lider” seçmeyin; anlaşılır işaretleri konuşun.', 3],
        ['Sessiz kontrol', 'Metronomu iki ölçü kapatın, sonra geri getirin. Grup hızlanmayı/yavaşlamayı birlikte fark etsin.', 'Skor vermeyin; yalnız gözlem cümlesi kurun.', 2]
      ]
    },
    {
      id: 'katman-degis',
      ad: 'Katman Değiş-Takas',
      ikon: '≋',
      ozet: 'İki basit parti aynı anda sürer; gruplar işaretle partileri değiştirir ve ortak bitiş yapar.',
      kanit: 'zayif',
      dayanak: 'Katmanlı ritim güçlü bir müzik eğitimi görevidir; dikkat veya sosyal beceriye aktarım kanıtı sınırlıdır.',
      uygunluk: 'Oturarak veya ayakta · ileri düzey',
      varsayilanBpm: 66,
      hareketli: false,
      adimlar: [
        ['Ana nabız', 'Tüm grup dörtlük ana vuruşu kurar; isteyen bu turu yalnız dinleyerek izler.', 'Rahatlık oluşmadan ikinci katmanı eklemeyin.', 3],
        ['Parti A', 'Birinci grup 1 ve 3. zamanları çalsın; diğer grup dinlesin.', 'Dört hazırlık vuruşu kullanın.', 3],
        ['Parti B', 'İkinci grup 2 ve 4. zamanları çalsın; birinci grup dinlesin.', 'Ses düzeylerini eşitleyin.', 3],
        ['Katmanla', 'İki grup partilerini aynı anda sürdürsün.', 'Hata olursa herkes durmasın; ana nabza geri dönme işareti kullanın.', 4],
        ['Değiş-takas', 'Eğitmenin sessiz işaretinde gruplar bir sonraki ölçü başında partileri değiştirsin.', 'İşareti en az iki vuruş önce gösterin.', 4],
        ['Birlikte bitir', 'Dört vuruşluk küçülme işaretiyle sesleri azaltın ve tek ortak vuruşla bitirin.', 'Bitişi başarı/başarısızlık değil ortak karar olarak konuşun.', 2]
      ]
    },
    {
      id: 'uyarlanmis-hareket',
      ad: 'Uyarlanmış Ritimli Hareket',
      ikon: '↔',
      ozet: 'Ritim işaretleri sağ-sol ağırlık aktarımı veya oturarak el hareketleriyle izlenir.',
      kanit: 'orta',
      dayanak: 'Ritmik işitsel ipuçlarının yürüme çalışmalarında kanıtı vardır; bu akış klinik rehabilitasyon değildir ve ayakta uygulama uzman kararına bağlıdır.',
      uygunluk: 'Oturarak varsayılan · hareket alanı gerekir',
      varsayilanBpm: 56,
      hareketli: true,
      adimlar: [
        ['Hareket seçimi', 'Her katılımcı oturarak el hareketi, oturarak ayak ucu veya uygunsa ayakta ağırlık aktarımı seçsin.', 'Baş dönmesi, ağrı veya denge kaygısında oturarak seçeneğe dönün.', 3],
        ['İşaretleri öğren', 'Kalın tını sağa, ince tını sola hareketi temsil etsin. Önce ritimsiz prova edin.', '90°/180°/360° dönüş yok.', 3],
        ['Sabit nabız', 'Dört hazırlık vuruşundan sonra seçilen hareketi ana vuruşa eşleyin.', 'Adım büyüklüğü kişiye göre serbesttir.', 4],
        ['Dur-devam', 'Açık el işaretinde durun; bir sonraki dört sayımla yeniden başlayın.', 'Ani yön değiştirmeyin; işareti önceden verin.', 3],
        ['Grup aynası', 'Bir grup hareketi seçer, diğer grup kendi güvenli aralığında aynalar; sonra roller değişir.', 'Temas yok; kişiler arasında kol mesafesi bırakın.', 4],
        ['Kapanış ve kontrol', 'Oturarak iki yavaş ortak vuruşla bitirin. Rahatlık durumunu sözlü veya kartla sorun.', 'Olağandışı belirti varsa oturumu bitirin ve uygun uzmana yönlendirin.', 2]
      ]
    }
  ];

  function sayiSinirla(deger, alt, ust, varsayilan) {
    deger = Number(deger);
    return Number.isFinite(deger) ? Math.max(alt, Math.min(ust, deger)) : varsayilan;
  }

  function protokolBul(id) {
    for (var i = 0; i < PROTOKOLLER.length; i++) {
      if (PROTOKOLLER[i].id === id) { return PROTOKOLLER[i]; }
    }
    return PROTOKOLLER[0];
  }

  function oturumOlustur(id, sureDk, bpm, mod) {
    var protokol = protokolBul(id);
    var toplamSn = Math.round(sayiSinirla(sureDk, 10, 90, 35) * 60);
    var tempo = Math.round(sayiSinirla(bpm, 40, 120, protokol.varsayilanBpm));
    var agirlik = protokol.adimlar.reduce(function (t, a) { return t + a[3]; }, 0);
    var kalan = toplamSn;
    var adimlar = protokol.adimlar.map(function (a, i) {
      var sure = i === protokol.adimlar.length - 1
        ? kalan
        : Math.max(30, Math.round(toplamSn * a[3] / agirlik));
      kalan -= sure;
      return { baslik: a[0], yonerge: a[1], kolaylastirici: a[2], sureSn: sure };
    });
    return {
      protokol: protokol,
      toplamSn: toplamSn,
      bpm: tempo,
      mod: mod === 'ayakta' ? 'ayakta' : 'oturarak',
      adimlar: adimlar
    };
  }

  function guvenlikHazir(mi) {
    mi = mi || {};
    return Boolean(mi.alan && mi.ses && mi.secim && mi.durdurma);
  }

  function sureYaz(saniye) {
    saniye = Math.max(0, Math.ceil(Number(saniye) || 0));
    return String(Math.floor(saniye / 60)).padStart(2, '0') + ':' +
      String(saniye % 60).padStart(2, '0');
  }

  return {
    PROTOKOLLER: PROTOKOLLER,
    protokolBul: protokolBul,
    oturumOlustur: oturumOlustur,
    guvenlikHazir: guvenlikHazir,
    sureYaz: sureYaz
  };
});
