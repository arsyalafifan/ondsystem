<?php

namespace App\Support;

use App\Models\Kendaraan;

/**
 * Membentuk packing list sebagai perintah ESC/P mentah (teks asli + kode
 * kontrol printer), sama seperti EscpNotaBuilder — lihat komentar di kelas
 * itu untuk alasan lengkap kenapa dot-matrix butuh jalur teks mentah,
 * bukan hasil rasterisasi PDF/browser biasa.
 *
 * Kelas ini sengaja berdiri sendiri, tidak berbagi kode dengan
 * EscpNotaBuilder, supaya keduanya tetap jadi jalur cadangan yang independen
 * satu sama lain — kalau salah satu bermasalah, yang lain tidak ikut rusak.
 */
final class EscpPackingListBuilder
{
    private const ESC = "\x1B";

    private const INIT = self::ESC.'@';

    private const NLQ_ON = self::ESC."x\x01";

    private const ELITE_ON = self::ESC.'M';

    private const BOLD_ON = self::ESC.'E';

    private const BOLD_OFF = self::ESC.'F';

    private const FORM_FEED = "\x0C";

    // Nilai yang sama dengan EscpNotaBuilder — sudah terbukti pas di kertas
    // continuous form LX-310 (lihat catatan di sana).
    private const MARGIN_KIRI = 3;

    private const LEBAR = 92;

    public static function build(Kendaraan $kendaraan): string
    {
        $b = self::INIT.self::NLQ_ON.self::ELITE_ON;
        $b .= self::ESC.'l'.chr(self::MARGIN_KIRI);
        $b .= self::ESC.'Q'.chr(self::MARGIN_KIRI + self::LEBAR);

        $b .= self::header($kendaraan);
        $b .= self::crlf();
        $b .= self::tabelProduk($kendaraan);
        $b .= self::crlf();
        $b .= self::tabelToko($kendaraan);
        $b .= self::FORM_FEED;

        // Dikirim mentah ke printer, bukan lewat GDI — lihat penjelasan
        // lengkap di EscpNotaBuilder::build().
        return iconv('UTF-8', 'CP437//TRANSLIT//IGNORE', $b) ?: $b;
    }

    private static function header(Kendaraan $kendaraan): string
    {
        $b = self::BOLD_ON.(string) config('perusahaan.nama').self::BOLD_OFF.self::crlf();
        $b .= self::garis().self::crlf();
        $b .= self::baris('Nama Mobil    : '.$kendaraan->nama).self::crlf();
        $b .= self::baris('Jumlah Faktur : '.$kendaraan->stops_packing->count()).self::crlf();
        $b .= self::baris('Jumlah Dus    : '.$kendaraan->stops_packing->sum('total_dus')).self::crlf();
        $b .= self::baris('Tanggal Berangkat : '.$kendaraan->batch->tanggal->format('d/m/Y')).self::crlf();

        return $b;
    }

    private static function tabelProduk(Kendaraan $kendaraan): string
    {
        $lebar = ['nama' => 45, 'qty' => 10, 'terjual' => 15, 'pulang' => 15];

        $b = self::BOLD_ON;
        $b .= self::gabung([
            self::kiri('Nama Barang', $lebar['nama']),
            self::kanan('Qty', $lebar['qty']),
            self::kanan('Dus Terjual', $lebar['terjual']),
            self::kanan('Dus Pulang', $lebar['pulang']),
        ]);
        $b .= self::BOLD_OFF.self::crlf();
        $b .= self::garis().self::crlf();

        $ringkasan = $kendaraan->ringkasan_produk_packing;

        foreach ($ringkasan as $baris) {
            $b .= self::gabung([
                self::kiri($baris['produk'], $lebar['nama']),
                self::kanan((string) $baris['qty'], $lebar['qty']),
                self::kanan('', $lebar['terjual']),
                self::kanan('', $lebar['pulang']),
            ]).self::crlf();
        }

        $b .= self::garis().self::crlf();
        $b .= self::BOLD_ON;
        $b .= self::gabung([
            self::kiri('Total', $lebar['nama']),
            self::kanan((string) $ringkasan->sum('qty'), $lebar['qty']),
            self::kanan('', $lebar['terjual']),
            self::kanan('', $lebar['pulang']),
        ]);
        $b .= self::BOLD_OFF.self::crlf();

        return $b;
    }

    private static function tabelToko(Kendaraan $kendaraan): string
    {
        $lebar = ['nama' => 45, 'qty' => 10, 'terjual' => 15, 'pulang' => 15];

        $b = self::BOLD_ON;
        $b .= self::gabung([
            self::kiri('Nama Toko', $lebar['nama']),
            self::kanan('Qty', $lebar['qty']),
            self::kanan('Dus Terjual', $lebar['terjual']),
            self::kanan('Dus Pulang', $lebar['pulang']),
        ]);
        $b .= self::BOLD_OFF.self::crlf();
        $b .= self::garis().self::crlf();

        $stops = $kendaraan->stops_packing;

        foreach ($stops as $stop) {
            $b .= self::gabung([
                self::kiri($stop->toko->nama, $lebar['nama']),
                self::kanan((string) $stop->total_dus, $lebar['qty']),
                self::kanan('', $lebar['terjual']),
                self::kanan('', $lebar['pulang']),
            ]).self::crlf();
        }

        $b .= self::garis().self::crlf();
        $b .= self::BOLD_ON;
        $b .= self::gabung([
            self::kiri('Total', $lebar['nama']),
            self::kanan((string) $stops->sum('total_dus'), $lebar['qty']),
            self::kanan('', $lebar['terjual']),
            self::kanan('', $lebar['pulang']),
        ]);
        $b .= self::BOLD_OFF.self::crlf();

        return $b;
    }

    // ------------------------------------------------------------------
    // Perata teks & tata letak kolom — sama persis dengan EscpNotaBuilder.
    // ------------------------------------------------------------------

    /** @param  array<int, string>  $bagian */
    private static function gabung(array $bagian, string $pemisah = ' '): string
    {
        return implode($pemisah, $bagian);
    }

    private static function kiri(string $teks, int $lebar): string
    {
        $teks = self::potong($teks, $lebar);

        return $teks.str_repeat(' ', max(0, $lebar - mb_strlen($teks)));
    }

    private static function kanan(string $teks, int $lebar): string
    {
        $teks = self::potong($teks, $lebar);

        return str_repeat(' ', max(0, $lebar - mb_strlen($teks))).$teks;
    }

    private static function potong(string $teks, int $lebar): string
    {
        return mb_strlen($teks) > $lebar ? mb_substr($teks, 0, $lebar) : $teks;
    }

    private static function garis(): string
    {
        return str_repeat('-', self::LEBAR);
    }

    private static function baris(string $teks): string
    {
        return $teks;
    }

    private static function crlf(): string
    {
        return "\r\n";
    }
}
