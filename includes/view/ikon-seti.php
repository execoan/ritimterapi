<?php
/**
 * İKON SETİ — tanıtım sayfasının özel çizim dili.
 *
 * Neden emoji değil: emoji figürler her cihazda BAŞKA çizilir (Windows'ta
 * Segoe, Apple'da apple-emoji, Android'de Noto), renk paletiniz dışına
 * çıkar, tema rengini almaz ve "hazır şablon" izlenimi verir. Bu set tek
 * bir çizim dili kullanır: 24×24 ızgara, 1,6 birim kontur, yuvarlak uçlar,
 * dolgu yok — yani her ikon tema rengini currentColor üzerinden alır.
 *
 * Kullanım:  <?= ikon('davul', 't-kart-ikon') ?>
 * Süs olduğu için aria-hidden; anlam her zaman yanındaki başlıktadır.
 */
if (!defined('RITIM')) { exit; }

const IKONLAR = [
    /* Atölye */
    'davul' => '<path d="M4 9c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3Z"/>'
             . '<path d="M4 9v6c0 1.7 3.6 3 8 3s8-1.3 8-3V9"/>'
             . '<path d="m7.5 6.5 3 5m6-5-3 5"/><path d="M17 3.5 20 6M7 3.5 4 6"/>',
    'metronom' => '<path d="M9.5 3h5l3.5 17H6L9.5 3Z"/><path d="M12 18V6.5"/>'
                . '<path d="m12 11 4-2.5"/><path d="M4.5 20h15"/>',
    'nota' => '<circle cx="7" cy="17.5" r="2.6"/><circle cx="17" cy="15.5" r="2.6"/>'
            . '<path d="M9.6 17.5V6.2l10-2v11.3"/><path d="M9.6 9.4l10-2"/>',
    'dalga' => '<path d="M2 12h2.5l2-6 3 12 3-15 3 12 2-3H22"/>',
    'kulak' => '<path d="M7 9a5 5 0 0 1 10 0c0 3-2.2 4-3.2 5.4-.8 1.1-.5 2.4-1.3 3.3-.7.8-2 .9-2.9.2"/>'
             . '<path d="M10.4 9.2a1.7 1.7 0 0 1 3.2.8c0 1.3-1.4 1.6-1.6 2.8"/>'
             . '<path d="M7 9c0 3.4 1.2 6.6 3 9"/>',
    'yol' => '<path d="M6 21c0-4 4-4 4-8s-4-4-4-8"/><path d="M18 3c0 4-4 4-4 8s4 4 4 8"/>'
           . '<circle cx="6" cy="4" r="1.4"/><circle cx="18" cy="20" r="1.4"/>',

    'tumbek' => '<path d="M6.9 5.6c0-1.2 2.3-2.1 5.1-2.1s5.1.9 5.1 2.1-2.3 2.1-5.1 2.1-5.1-.9-5.1-2.1Z"/>'
              . '<path d="M6.9 5.6 8.5 19a1.7 1.7 0 0 0 1.7 1.5h3.6a1.7 1.7 0 0 0 1.7-1.5l1.6-13.4"/>'
              . '<path d="M7.7 12.4h8.6"/>',

    /* Bilgi ve süreç */
    'kitap' => '<path d="M4 5.5A2 2 0 0 1 6 3.5h5v15H6a2 2 0 0 0-2 2v-15Z"/>'
             . '<path d="M20 5.5a2 2 0 0 0-2-2h-5v15h5a2 2 0 0 1 2 2v-15Z"/>',
    'not' => '<path d="M6 3.5h8.5L19 8v12.5H6V3.5Z"/><path d="M14 3.5V8h5"/>'
           . '<path d="M9 12.5h7M9 16h5"/>',
    'grafik' => '<path d="M4 20V4"/><path d="M4 20h16"/>'
              . '<path d="m7.5 15.5 3.5-4 3 2.5 4.5-6"/><circle cx="11" cy="11.5" r="1.1"/>'
              . '<circle cx="14.5" cy="14" r="1.1"/>',
    'hedef' => '<circle cx="12" cy="12" r="8.2"/><circle cx="12" cy="12" r="4.4"/>'
             . '<circle cx="12" cy="12" r="1.1"/>',
    'sessiz' => '<path d="M11 5.5 6.5 9H3.5v6h3L11 18.5v-13Z"/><path d="m16 9.5 4.5 5m0-5-4.5 5"/>',
    'kulaklik' => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/>'
                . '<path d="M4 13.5h2.2a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H5.4A1.4 1.4 0 0 1 4 18.1v-4.6Z"/>'
                . '<path d="M20 13.5h-2.2a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h.8a1.4 1.4 0 0 0 1.4-1.4v-4.6Z"/>',

    'goz' => '<path d="M2.6 12S6.2 5.9 12 5.9 21.4 12 21.4 12 17.8 18.1 12 18.1 2.6 12 2.6 12Z"/>'
           . '<circle cx="12" cy="12" r="2.9"/>',
    'simsek' => '<path d="M13.4 2.6 5.9 13.1h5L10.6 21.4l7.5-10.5h-5l.3-8.3Z"/>',
    'girdap' => '<path d="M12 12.1a1.9 1.9 0 1 0 1.9 1.9c0-2.2-2-3.6-4.2-3.6s-4.4 1.9-4.4 4.6 3 5.4 6.7 5.4 7-3 7-7.2S15.7 3.4 11.2 3.4"/>',
    'kilit' => '<rect x="4.6" y="10.3" width="14.8" height="10.2" rx="2.4"/>'
             . '<path d="M8.3 10.3V7.7a3.7 3.7 0 0 1 7.4 0v2.6"/><path d="M12 14.2v2.6"/>',

    /* Kişi */
    'cocuk' => '<circle cx="12" cy="6.5" r="3"/><path d="M6.5 20.5v-2.8a5.5 5.5 0 0 1 11 0v2.8"/>'
             . '<path d="M9.5 20.5v-3m5 3v-3"/>',
    'yetiskin' => '<circle cx="12" cy="6.2" r="3.2"/>'
                . '<path d="M5 20.5v-2a4.6 4.6 0 0 1 4.6-4.6h4.8a4.6 4.6 0 0 1 4.6 4.6v2"/>'
                . '<path d="M12 13.9v6.6"/>',

    /* Tempo kartları — gündelik hayattan tempo çağrışımları */
    'ay' => '<path d="M20.2 14.8A8.6 8.6 0 0 1 9.2 3.8a8.6 8.6 0 1 0 11 11Z"/>',
    'kalp' => '<path d="M12 20.2 5.1 13.3a4.5 4.5 0 0 1 6.3-6.4l.6.6.6-.6a4.5 4.5 0 0 1 6.3 6.4Z"/>'
            . '<path d="M4.6 12.4h3.2l1.4-2.3 1.8 4 1.5-2.9.9 1.2h5.9"/>',
    'ayakizi' => '<path d="M9.2 3.6c1.6 0 2.7 1.6 2.7 3.7 0 1.6-.6 2.8-.6 3.9 0 1 .7 1.5.7 2.5 0 1.3-1.2 2.1-2.8 2.1s-2.8-.8-2.8-2.1c0-1 .7-1.5.7-2.5 0-1.1-.6-2.3-.6-3.9 0-2.1 1.1-3.7 2.7-3.7Z"/>'
               . '<path d="M16.7 12.6c1.1 0 1.9 1.1 1.9 2.5 0 1.1-.4 1.9-.4 2.6 0 .7.5 1 .5 1.7 0 .9-.8 1.4-2 1.4s-2-.5-2-1.4c0-.7.5-1 .5-1.7 0-.7-.4-1.5-.4-2.6 0-1.4.8-2.5 1.9-2.5Z"/>',
    'kure' => '<circle cx="12" cy="13.8" r="6.4"/><path d="M12 3.2v4.2"/><path d="M9.6 3.2h4.8"/>'
            . '<path d="M5.6 13.8h12.8"/><path d="M12 7.4v12.8"/>'
            . '<path d="M7.4 9.7c1.3.8 2.9 1.2 4.6 1.2s3.3-.4 4.6-1.2"/>'
            . '<path d="M7.4 17.9c1.3-.8 2.9-1.2 4.6-1.2s3.3.4 4.6 1.2"/>',
    'kronometre' => '<circle cx="12" cy="13.8" r="7"/><path d="M12 13.8V9.9"/>'
                  . '<path d="M9.8 3.2h4.4"/><path d="M12 3.2v3.6"/><path d="m18.6 8.1 1.5-1.5"/>',

    /* İletişim */
    'telefon' => '<path d="M7.5 3.5h9a1.5 1.5 0 0 1 1.5 1.5v14a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 19V5a1.5 1.5 0 0 1 1.5-1.5Z"/>'
               . '<path d="M10.5 17.5h3"/>',
    'zarf' => '<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="m3.6 6.8 8.4 6 8.4-6"/>',
    'kamera' => '<rect x="3" y="7" width="18" height="12.5" rx="2.5"/>'
              . '<circle cx="12" cy="13.2" r="3.6"/><path d="M8.5 7l1.3-2.5h4.4L15.5 7"/>',
    'konum' => '<path d="M12 21s7-5.8 7-11a7 7 0 1 0-14 0c0 5.2 7 11 7 11Z"/><circle cx="12" cy="10" r="2.7"/>',
];

/**
 * Satır içi SVG ikon döndürür.
 * @param string $ad   IKONLAR anahtarı (bilinmiyorsa boş döner — sayfa kırılmaz)
 * @param string $sinif CSS sınıfı
 */
function ikon(string $ad, string $sinif = 't-ikon'): string
{
    if (!isset(IKONLAR[$ad])) { return ''; }
    return '<svg class="' . e($sinif) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false"'
        . ' fill="none" stroke="currentColor" stroke-width="1.6"'
        . ' stroke-linecap="round" stroke-linejoin="round">' . IKONLAR[$ad] . '</svg>';
}
