# Metronom Stüdyosu v2 — Araştırma Notu (Temmuz 2026)

## 1. En iyi metronom uygulamalarından alınan özellikler

Soundbrenner ve Pro Metronome gibi lider uygulamaların ayırt edici özellikleri tarandı;
stüdyoya şunlar eklendi:

| Özellik | Esin | Bizde |
|---|---|---|
| Alt bölünmeler (sekizlik/üçleme/onaltılık) | Pro Metronome "subdivisions" | ♩/♫/③/♬ seçici; alt tıklar kısık ve tiz |
| Vuruş başına aksan deseni | Soundbrenner accent editor | Noktaya tıkla: aksan → normal → sessiz |
| Tempo trainer (kademeli hızlanma) | Pro Metronome Rhythm Trainer / speed trainer | Hedef BPM + her N ölçüde ±X |
| Poliritim | Soundbrenner polyrhythms | Ölçü başına 2/3/5 çapraz vuruş katmanı |
| Preset / setlist | Soundbrenner setlist yönetimi | localStorage preset çipleri (ad+bpm+desen) |
| Görsel / flaş modu | Pro Metronome Visual & Flash | ⚡ Flaş modu + büyük vuruş sayacı |
| Zengin ses seçenekleri | Her iki uygulama | 7 kit: tahta, klik, klaves, inek çanı, davul, bip, sesli sayma (TR) |
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
