<?php

namespace App\Support;

use App\Models\Pesanan;
use App\Models\User;

/**
 * Membentuk nota sebagai perintah ESC/P mentah (teks asli + kode kontrol
 * printer), bukan gambar hasil rasterisasi seperti jalur Cetak/Unduh PDF
 * biasa. Inilah cara aplikasi lama (Accurate) mendapat hasil setajam
 * aslinya di printer dot-matrix: karakter digambar langsung oleh font ROM
 * printernya sendiri, tidak lewat proses "screenshot halaman lalu di-dot".
 *
 * Berkas hasilnya harus dikirim APA ADANYA ke printer (byte demi byte),
 * bukan lewat jalur cetak GDI/browser biasa — lihat catatan penggunaan di
 * NotaPesananController::unduhEscp().
 */
final class EscpNotaBuilder
{
    private const ESC = "\x1B";

    private const INIT = self::ESC.'@';

    private const NLQ_ON = self::ESC."x\x01";

    private const ELITE_ON = self::ESC.'M';

    private const BOLD_ON = self::ESC.'E';

    private const BOLD_OFF = self::ESC.'F';

    private const FORM_FEED = "\x0C";

    // Margin kiri kecil (bukan 0) supaya teks tidak menempel persis di tepi
    // kertas; lebar konten dilebarkan mendekati lebar cetak sungguhan
    // printer (bukan cuma 80 kolom) supaya sisi kanan tidak kosong dan nama
    // barang/toko yang panjang tidak keburu terpotong.
    private const MARGIN_KIRI = 3;

    private const LEBAR = 100;

    public static function build(Pesanan $pesanan, User $pencetak): string
    {
        $b = self::INIT.self::NLQ_ON.self::ELITE_ON;
        $b .= self::ESC.'l'.chr(self::MARGIN_KIRI);
        $b .= self::ESC.'Q'.chr(self::MARGIN_KIRI + self::LEBAR);

        $b .= self::header($pesanan, $pencetak);
        $b .= self::garis().self::crlf();
        $b .= self::baris(self::pusat($pesanan->toko->asset_id ?? '-', self::LEBAR)).self::crlf().self::crlf();
        $b .= self::tabelItem($pesanan);
        $b .= self::ringkasan($pesanan);
        $b .= self::crlf().self::crlf();
        $b .= self::tandaTangan();
        $b .= self::crlf();
        $b .= self::status();
        $b .= self::FORM_FEED;

        // Dikirim mentah ke printer, bukan lewat GDI — printer dot-matrix
        // cuma mengerti satu code page byte-tunggal (CP437 di LX-310), bukan
        // UTF-8. Tanpa ini, karakter di luar ASCII (termasuk yang sudah
        // ditransliterasi TRANSLIT-nya) tampil sebagai glyph acak di kertas.
        // Byte kontrol (ESC dkk, semuanya di bawah 0x80) tidak terpengaruh —
        // ASCII adalah subset identik di UTF-8 maupun CP437.
        return iconv('UTF-8', 'CP437//TRANSLIT//IGNORE', $b) ?: $b;
    }

    private static function header(Pesanan $pesanan, User $pencetak): string
    {
        $perusahaan = [
            self::BOLD_ON.config('perusahaan.nama').self::BOLD_OFF,
            (string) config('perusahaan.tagline'),
            (string) config('perusahaan.alamat'),
            'HP: '.config('perusahaan.telepon').' - '.config('perusahaan.email'),
            (string) config('perusahaan.bank'),
        ];

        $lebarKepada = 26;

        $kepada = [
            'Kepada: '.$pesanan->toko->nama,
            $pesanan->toko->telepon ?? '-',
            ...self::pecahBaris($pesanan->toko->alamatLengkap ?: '-', $lebarKepada),
        ];

        $faktur = [
            self::BOLD_ON.'FAKTUR PENJUALAN'.self::BOLD_OFF,
            'No. Faktur : '.$pesanan->kode,
            'Tanggal    : '.$pesanan->tanggal->format('d/m/Y'),
            'Sales      : '.$pencetak->name,
            'No. HP     : '.($pencetak->no_hp ?? '-'),
        ];

        // Kolom faktur sengaja dilebihkan (bukan cuma cukup pas) — kode nota
        // TIDAK BOLEH terpotong, beda dengan label lain yang aman kalau
        // kepanjangan dan sedikit terpotong.
        return self::gabungKolom([$perusahaan, $kepada, $faktur], [30, $lebarKepada, 40]);
    }

    private static function tabelItem(Pesanan $pesanan): string
    {
        $lebar = ['no' => 3, 'nama' => 40, 'qty' => 6, 'satuan' => 7, 'harga' => 13, 'disc' => 6, 'total' => 15];

        $b = self::BOLD_ON;
        $b .= self::gabung([
            self::kiri('No', $lebar['no']), self::kiri('Nama Barang', $lebar['nama']),
            self::kanan('Qty', $lebar['qty']), self::kiri('Satuan', $lebar['satuan']),
            self::kanan('Harga Satuan', $lebar['harga']), self::kanan('Disc %', $lebar['disc']),
            self::kanan('Total Harga', $lebar['total']),
        ]);
        $b .= self::BOLD_OFF.self::crlf();
        $b .= self::garis().self::crlf();

        foreach ($pesanan->items as $i => $item) {
            $b .= self::gabung([
                self::kiri((string) ($i + 1), $lebar['no']),
                self::kiri(mb_substr($item->produk->nama, 0, $lebar['nama'] - 1), $lebar['nama']),
                self::kanan(number_format($item->jumlah_dus, 0, ',', '.'), $lebar['qty']),
                self::kiri('DUS', $lebar['satuan']),
                self::kanan(number_format((float) $item->harga_satuan, 0, ',', '.'), $lebar['harga']),
                self::kanan('0', $lebar['disc']),
                self::kanan(number_format((float) $item->subtotal, 0, ',', '.'), $lebar['total']),
            ]).self::crlf();
        }

        $b .= self::garis().self::crlf();

        return $b;
    }

    private static function ringkasan(Pesanan $pesanan): string
    {
        $b = 'Terbilang: '.Terbilang::rupiah((float) $pesanan->total_nilai).self::crlf();
        $b .= 'Keterangan'.($pesanan->catatan ? ": {$pesanan->catatan}" : '').self::crlf().self::crlf();

        $b .= self::kananPenuh('Sub Total    : '.number_format((float) $pesanan->total_nilai, 0, ',', '.')).self::crlf();
        $b .= self::kananPenuh('Diskon       : 0').self::crlf();
        $b .= self::BOLD_ON;
        $b .= self::kananPenuh('Total Invoice: '.number_format((float) $pesanan->total_nilai, 0, ',', '.')).self::crlf();
        $b .= self::BOLD_OFF;
        $b .= self::kananPenuh('*Harga sudah termasuk pajak').self::crlf();

        return $b;
    }

    private static function tandaTangan(): string
    {
        $label = ['Fakturis,', 'Gudang,', 'Driver,', 'Pelanggan,'];
        $lebarKotak = 20;

        $b = '';
        foreach ($label as $l) {
            $b .= self::kiri($l, $lebarKotak);
        }
        $b .= self::crlf().self::crlf();

        foreach ($label as $l) {
            $b .= self::kiri(str_repeat('_', $lebarKotak - 3), $lebarKotak);
        }
        $b .= self::crlf();

        return $b;
    }

    private static function status(): string
    {
        return self::kananPenuh('Status : Belum Lunas').self::crlf()
            .self::kananPenuh(now()->format('d/m/Y, H:i')).self::crlf();
    }

    // ------------------------------------------------------------------
    // Perata teks & tata letak kolom
    // ------------------------------------------------------------------

    /**
     * Menyusun beberapa "kolom" (masing-masing array baris teks) sejajar,
     * meniru tata letak flex/table di versi HTML tapi lewat spasi biasa —
     * printer teks mentah tidak punya konsep tabel/flex sama sekali.
     *
     * @param  array<int, array<int, string>>  $kolomKolom
     * @param  array<int, int>  $lebarKolom
     */
    private static function gabungKolom(array $kolomKolom, array $lebarKolom): string
    {
        $jumlahBaris = max(array_map('count', $kolomKolom));
        $b = '';

        for ($i = 0; $i < $jumlahBaris; $i++) {
            $bagian = [];
            foreach ($kolomKolom as $idx => $kolom) {
                $bagian[] = self::kiri($kolom[$i] ?? '', $lebarKolom[$idx]);
            }
            $b .= self::gabung($bagian, '  ').self::crlf();
        }

        return $b;
    }

    /**
     * Menyambung field-field yang sudah dipad dengan pemisah eksplisit —
     * tanpa ini, kolom yang isinya pas memenuhi lebarnya (nama produk atau
     * alamat yang panjang) akan langsung menempel ke kolom berikutnya tanpa
     * jarak sama sekali.
     *
     * @param  array<int, string>  $bagian
     */
    private static function gabung(array $bagian, string $pemisah = ' '): string
    {
        return implode($pemisah, $bagian);
    }

    /** @return array<int, string> */
    private static function pecahBaris(string $teks, int $lebar): array
    {
        return array_filter(explode("\n", wordwrap($teks, $lebar, "\n", true)), fn ($b) => $b !== '');
    }

    /**
     * Bukan str_pad() biasa — str_pad() menghitung panjang per BYTE, sedangkan
     * karakter multi-byte (mis. sebelum konversi CP437 di atas) akan
     * membuat paddingnya salah hitung dan kolom-kolom geser. mb_strlen()
     * menghitung per KARAKTER, sesuai jumlah byte akhir setelah konversi
     * CP437 (satu karakter Unicode = tepat satu byte CP437).
     */
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

    private static function pusat(string $teks, int $lebar): string
    {
        $teks = self::potong($teks, $lebar);
        $sisa = $lebar - mb_strlen($teks);
        $kiri = (int) floor($sisa / 2);

        return str_repeat(' ', max(0, $kiri)).$teks;
    }

    private static function kananPenuh(string $teks): string
    {
        return self::kanan($teks, self::LEBAR);
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
