# Metronom Stüdyosu v3 — Araştırma Notu (Temmuz 2026)

## 1. En iyi metronom uygulamalarından alınan özellikler

Soundbrenner ve Pro Metronome gibi lider uygulamaların ayırt edici özellikleri tarandı;
stüdyoya şunlar eklendi:

| Özellik | Esin | Bizde |
|---|---|---|
| Alt bölünmeler (sekizlik/üçleme/onaltılık) | Pro Metronome "subdivisions" | ♩/♫/③/♬ seçici; alt tıklar kısık ve tiz |
| Vuruş başına aksan deseni | Soundbrenner accent editor | Noktaya tıkla: aksan → normal → sessiz |
| Tempo trainer (kademeli hızlanma) | Pro Metronome Rhythm Trainer / speed trainer | Hedef BPM + her N ölçüde ±X |
| Poliritim | Soundbrenner polyrhythms | Ölçü başına 2–7 çapraz vuruş katmanı |
| Preset / setlist | Soundbrenner setlist yönetimi | localStorage preset çipleri (ad+bpm+desen) |
| Görsel / flaş modu | Pro Metronome Visual & Flash | ⚡ Flaş modu + büyük vuruş sayacı |
| Zengin ses seçenekleri | Her iki uygulama | 6 kit: tahta, klik, klaves, inek çanı, davul, yumuşak bip |
| Ses seçim önizlemesi | Kullanıcı isteği | Kit değişince 2 vuruşluk tadımlık çalar |

Kaynaklar: soundbrenner.com (Android metronom rehberi, poliritim rehberi),
Pro Metronome App Store sayfası, codamusictech.com ve rhythmnotes.net derlemeleri.

## 2. Bilimsel bataryalardan alınan görevler (makaleler ne yapmış?)

**BAASTA** (Dalla Bella et al. 2017, *Behav Res Methods*, 10.3758/s13428-016-0773-6;
mobil sürüm 2024, 10.3758/s13428-024-02363-x) zamanlama profilini şu görevlerle çıkarır:

- **Algısal:** süre ayrımı, **anizokroni tespiti** (ton ve müzikle), Beat Alignment Test
- **Motor:** **serbest (unpaced) tapping**, eşlikli (paced) tapping,
  **senkronizasyon–devam** (synchronization–continuation), tempo değişimine uyum (adaptive tapping)

Puyjarinet 2017 (kayıt defterimizde) tam bu bataryayı kullanır; Shin 2023 protokolü de
ritim temelli değerlendirme önerir. Stüdyodaki karşılıkları:

| BAASTA görevi | Stüdyodaki modül |
|---|---|
| Senkronizasyon–devam | 🎯 Vuruş Tutturma (sesli + sessiz faz) — v1'den beri |
| Serbest tapping (SMT) | 🫀 **Spontan Tempo** (yeni): 21 vuruş, SMT BPM + CV tutarlılık skoru |
| Anizokroni tespiti | 🕳 **Aksak Bulma** (yeni): 6 vuruşluk dizi, %6–15 kayma, 8 tur algı skoru |
| Tempo yeniden üretme | 🎧 BPM Bulma — v1'den beri |
| (okuma-eşgüdüm, pedagojik) | 🎼 Ritim Okuma — ev modülünün stüdyo sürümü |

Adaptive tapping (tempo değişimine uyum) ileride eklenebilir; motor temeli
Tempo trainer ile kuruldu.

## 3. Şarkı tempo kütüphanesi ve ek premium özellikler (v2.1)

**Şarkı BPM kütüphanesi:** 42 tanıdık parça (metal/rock/pop/jazz), tıklayınca tempo
(+ölçü: Take Five 5/4) ayarlanır ve metronom başlar. BPM değerleri getsongbpm.com ve
tunebat.com kayıtlarından derlendi (örn. Master of Puppets 220, Enter Sandman 123,
Smells Like Teen Spirit 117, So What 136, Take Five 176, Giant Steps 272 — üst sınır
240 olduğundan uyarıyla kısılır). Kayıt sürümüne göre küçük farklar olabilir.

**Premium eklemeler:**
- 🎲 **Rastgele sus** (%10–75): vuruşların bir kısmı rastgele susar, görsel akış sürer —
  Time Guru metronomunun ünlü içsel-zamanlama özelliğinin uyarlaması.
  **v2.2'de ölçülen protokole dönüştü:** 🧭 **İçsel Ritim (sessizlik merdiveni)** —
  4 faz (%0→%25→%50→%75 sus), faz başına skor, sessiz-vuruş sapması sesliyle
  karşılaştırılır (mor çubuklar), genel skor sessiz fazlara ağırlıklı (.1/.2/.3/.4).
  Ev sürümü (3 faz mini) ev programına atanabilir (tur: icsel_ritim, göç v7);
  gelişim öğrenci detayındaki protokol trendinde izlenir.
- ⏲ **Çalışma zamanlayıcısı**: 1–10 dk sonunda kendiliğinden durur.
- 📳 **Titreşim** (mobil): vuruşta Vibration API.
- ⛶ **Tam ekran sahne**: Fullscreen API; sahne büyür, sayaç 5rem.

Not: Bu modüller eğitim/izleme aracıdır; BAASTA'nın klinik norm ve eşik kestirimlerini
(MLP) birebir uygulamaz, tanı amacı taşımaz.

## 4. Gecikme kalibrasyonu ve ritim okuma zaman pencereleri (v3)

Web Audio tarafında tek başına `currentTime`, ses örneğinin kullanıcıya ulaştığı an
değildir. W3C; giriş, tamponlama, DSP ve çıkış gecikmelerinin birikerek müzik ve oyun
zamanlamasını etkilediğini açıkça belirtir. `AudioContext.outputLatency`,
`baseLatency` ve `getOutputTimestamp()` tarayıcının çıkış zamanını tahmin etmek için
sağladığı araçlardır:

- [W3C Web Audio API — latency](https://www.w3.org/TR/webaudio-1.0/#latency-section)
- [MDN — AudioContext.getOutputTimestamp](https://developer.mozilla.org/en-US/docs/Web/API/AudioContext/getOutputTimestamp)
- [web.dev — AudioContext.outputLatency ile senkronizasyon](https://web.dev/articles/audio-output-latency)

Ritim oyunları tek bir doğruluk eşiği kullanmaz. osu! farklı isabet sınıfları için
ayrı zaman pencereleri tanımlar; StepMania ise pencere değerlerine ek olarak
`TimingWindowScale`, `TimingWindowAdd` ve global ses ofseti/kalibrasyonu sunar:

- [osu! Overall difficulty ve timing windows](https://osu.ppy.sh/wiki/en/Beatmap/Overall_difficulty)
- [StepMania Preferences — timing windows](https://github-wiki-see.page/m/stepmania/stepmania/wiki/Preferences.ini)

Bu araştırmadan uygulanan kararlar:

1. Puanlı ritim okuma öncesi kalibrasyon zorunludur. Kullanıcı dört hazırlık
   vuruşundan sonra 12 klike eşlik eder.
2. Telafi değeri eşleşen vuruşların **medyan** sapmasıdır; tutarlılık, medyandan
   mutlak sapmanın medyanıyla raporlanır. Böylece tekil kötü vuruşlar sonucu bozmaz.
3. Tarayıcının `baseLatency + outputLatency` tahmini bilgi olarak gösterilir;
   puanlamada asıl olarak kullanıcının duyduğu gerçek zinciri ölçen kalibrasyon
   değeri uygulanır.
4. Öğrenme, Dengeli, Arcade ve Profesyonel hazır profilleri yanında kolay/orta/zor
   için ayrı 60–300 ms girilebilen Özel profil vardır.
5. Her profilde “tam puan” ve “doğru kabul” pencereleri ayrıdır. Bütün notaları dış
   pencere içinde çalan öğrenci yalnız küçük sapmalar yüzünden başarısız sayılmaz.
6. Hızlı onaltılık ve üçlemelerde komşu notaya kaymayı azaltmak için eşleşme,
   kronolojik sırayı koruyan dinamik programlama ile yapılır; önce en çok doğru
   nota, sonra en düşük toplam sapma seçilir.
7. Puanlı okumada ped hazırlık boyunca görünür; büyük 4‑3‑2‑1 geri sayımı ilk
   notada “ŞİMDİ”ye dönüşür. Hazırlık sırasındaki dokunuşlar yok sayılır. Kullanıcının
   ilk notayı arayüz değişikliğine tepki vererek kaçırmaması için yalnız ilk onsetin
   doğru kabul penceresine 80 ms eklenir; sonraki notaların seçilen profil hassasiyeti
   aynen korunur.
8. Vuruş Tutturma, Ritim Okuma, İçsel Ritim ve bunların ev sürümleri aynı
   `zamanlama-cekirdegi.js` modülünü kullanır. Dokunuş zamanı, ana iş parçacığındaki
   kısa takılmaların etkisini azaltmak için olayın `timeStamp` değeriyle AudioContext
   saatine taşınır. Eşleştirme kronolojik ve bire birdir; pencere dışındaki dokunuşlar
   “fazla” olarak korunur.
9. Kalibrasyon cihaz genelinde ortaktır. Kalite paneli medyan telafi, medyandan
   mutlak sapma, `baseLatency + outputLatency`, örnekleme hızı, tarih ve olası cihaz
   değişimini gösterir. Mutlak senkronizasyon protokolleri geçerli kalibrasyon yoksa
   puanlı ölçümü başlatmaz.
10. İlk hedefin 150 ms öncesinde kayıt kapısı açılır; bu, müzisyenin ilk vuruşu
    öngörerek az erken çalmasını kabul eder. Görsel “ŞİMDİ” ve normal ped durumu hedef
    anında değişir; hazırlık vuruşları ise kapının dışında kalır.
11. Ritim okuma ilerlemesi yalnız “geçti/kaldı” olarak tutulmaz. Ayrı
    `ritim-ogrenme.js` çekirdeği örnek başına hareketli skor, deneme güveni,
    başarı serisi ve nota türü isabeti hesaplar. Düşük sonuçlar oturum içi
    gecikmeli tekrar kuyruğuna, temiz sonuçlar 1–3–7–14–30 günlük kalıcılık
    aralıklarına alınır. Akıllı ileri seçimi önce zamanı gelmiş tekrarı, sonra
    aynı dersteki yeni örneği, ardından en zayıf eski örneği seçer.
12. Serbest metronomun swing matematiği `metronom-cekirdegi.js` içinde
    deterministik olarak hesaplanır. %50 düz, %66,7 klasik üçleme shuffle
    konumudur; onaltılık swing her yarım vuruştaki ikiliye ayrı uygulanır.
    5/4–12/8 ölçülerinde grup başlangıçları ayrı aksanlanabilir. Sayarak giriş
    sırasında rastgele sus, swing alt vuruşları ve poliritim katmanı devre dışı
    kalır; giriş bitince görsel “ŞİMDİ” işaretiyle çalışma fazı başlar.
