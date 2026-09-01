<?php

namespace App\Support;

use App\Enums\StatusStop;
use App\Models\Kendaraan;
use App\Models\KendaraanStop;
use Illuminate\Support\Collection;

/**
 * Membentuk berkas KML berisi titik-titik toko pada satu rute kendaraan,
 * supaya driver bisa mengunduhnya SEBELUM kehilangan sinyal di jalan dan
 * tetap melihat toko mana saja yang akan diantar lewat aplikasi peta
 * offline (mis. Map Marker) — Peta Rute di layar ini sendiri butuh koneksi
 * untuk memuat ubin peta dari server.
 *
 * Placemark diberi warna sesuai status kunjungan (StatusStop::warna(),
 * dikonversi ke format warna KML aabbggrr) supaya driver tetap bisa
 * membedakan toko yang sudah selesai/dibatalkan/belum dikunjungi lewat
 * aplikasi peta mana pun yang membaca styleUrl standar KML — bukan format
 * khusus Map Marker (ExtendedData/piniconcode dsb.), karena KML standar
 * sudah cukup dan bisa diimpor aplikasi peta offline apa pun, bukan cuma
 * satu merek tertentu.
 */
final class KmlRuteBuilder
{
    /**
     * @param  Collection<int, KendaraanStop>  $stops  wajib sudah memuat relasi `toko`
     *                                                 (nama, alamat, telepon, latitude,
     *                                                 longitude) — lihat DaftarKunjungan::stops()
     */
    public static function build(Kendaraan $kendaraan, Collection $stops): string
    {
        $b = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $b .= '<kml xmlns="http://www.opengis.net/kml/2.2">'."\n";
        $b .= '<Document>'."\n";
        $b .= '<name>'.self::escape($kendaraan->nama.' - '.now()->format('d/m/Y')).'</name>'."\n";

        foreach (StatusStop::cases() as $status) {
            $b .= self::gayaStatus($status);
        }

        foreach ($stops as $stop) {
            $b .= self::placemark($stop);
        }

        $b .= '</Document>'."\n";
        $b .= '</kml>'."\n";

        return $b;
    }

    /**
     * Satu Style per status, dirujuk lewat styleUrl dari tiap Placemark —
     * bukan didefinisikan ulang di setiap Placemark seperti hasil ekspor
     * Map Marker sendiri (yang bisa menghasilkan ribuan blok Style
     * berulang untuk ratusan toko), supaya berkasnya jauh lebih ringkas.
     */
    private static function gayaStatus(StatusStop $status): string
    {
        return '<Style id="stop-'.$status->value.'"><IconStyle>'
            .'<color>'.self::warnaKml($status->warna()).'</color>'
            .'<scale>1.1</scale>'
            .'<Icon><href>http://maps.google.com/mapfiles/kml/paddle/wht-blank.png</href></Icon>'
            .'</IconStyle></Style>'."\n";
    }

    private static function placemark(KendaraanStop $stop): string
    {
        $toko = $stop->toko;

        // Toko tanpa koordinat tidak bisa diplot — dilewati apa adanya,
        // sama seperti Peta Rute di layar ini sendiri.
        if ($toko === null || $toko->latitude === null || $toko->longitude === null) {
            return '';
        }

        $keterangan = collect([
            $toko->alamat,
            $toko->telepon ? 'Telp: '.$toko->telepon : null,
            'Dus: '.$stop->total_dus,
            'Status: '.$stop->status->label(),
        ])->filter()->implode("\n");

        $b = '<Placemark>'."\n";
        $b .= '<name>'.self::escape($stop->urutan.'. '.$toko->nama).'</name>'."\n";
        $b .= '<description><![CDATA['.str_replace(']]>', ']]&gt;', $keterangan).']]></description>'."\n";
        $b .= '<styleUrl>#stop-'.$stop->status->value.'</styleUrl>'."\n";
        // Urutan KML SELALU longitude dulu baru latitude — kebalikan dari
        // kebiasaan "lat,lng" di tempat lain pada aplikasi ini. Tertukar di
        // sini berarti seluruh titik terplot di lokasi yang salah.
        $b .= '<Point><coordinates>'
            .sprintf('%.7F', $toko->longitude).','.sprintf('%.7F', $toko->latitude).',0'
            .'</coordinates></Point>'."\n";
        $b .= '</Placemark>'."\n";

        return $b;
    }

    /** '#RRGGBB' → format warna KML 'aabbggrr' (alpha lalu biru-hijau-merah, kebalikan urutan RGB biasa). */
    private static function warnaKml(string $hex): string
    {
        $hex = ltrim($hex, '#');

        return 'ff'.substr($hex, 4, 2).substr($hex, 2, 2).substr($hex, 0, 2);
    }

    private static function escape(string $teks): string
    {
        return htmlspecialchars($teks, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
