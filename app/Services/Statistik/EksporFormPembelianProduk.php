<?php

namespace App\Services\Statistik;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Menyusun berkas Excel "Form Pembelian Produk Harian Outlet" dengan tata
 * letak meniru form manual yang selama ini dipakai: dua baris judul
 * (Mandarin lalu Indonesia), dua baris header hijau dwibahasa — label
 * bulan "9.2026" di atas deretan tanggal — lalu satu baris per toko.
 *
 * Baris judul sampai header hijau dibekukan (freeze pane), jadi tetap
 * terlihat saat daftar toko digulir ke bawah.
 *
 * Logo dipasang hanya kalau berkasnya ada di LOGO — repositori ini belum
 * menyimpan logo merek, dan logo tiruan lebih buruk daripada tanpa logo.
 */
final class EksporFormPembelianProduk
{
    public const LOGO = 'images/logo-halocoko.png';

    public const HIJAU = 'A9D08E';

    /** Kolom tetap sebelum deretan tanggal: [header, lebar]. */
    private const KOLOM_TETAP = [
        ['NO序号', 7],
        ["SALES\n业务员", 20],
        ["IDN FREEZER\n冰柜编号", 19],
        ["NAMA TOKO\n终端店名", 24],
        ["Alamat Toko\n终端地址", 32],
        ["NAMA PEMILIK TOKO\n店主姓名", 22],
        ["NO TELP\n终端电话", 15],
        ["KOORDINAT\n坐标", 22],
    ];

    /** @param array<string, mixed> $rekap hasil RekapPembelianHarian::susun() */
    public function buat(array $rekap): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Form Pembelian');
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(14);

        $jumlahTanggal = array_sum(array_map(fn (array $b) => count($b['tanggal']), $rekap['bulan']));
        $kolomTotal = count(self::KOLOM_TETAP) + $jumlahTanggal + 1;
        $hurufAkhir = Coordinate::stringFromColumnIndex($kolomTotal);
        $barisAkhir = 4 + max(count($rekap['baris']), 0);

        // --- Judul ---
        $sheet->mergeCells("A1:{$hurufAkhir}1");
        $sheet->mergeCells("A2:{$hurufAkhir}2");
        $sheet->setCellValue('A1', $rekap['judul_zh']);
        $sheet->setCellValue('A2', $rekap['judul_id']);
        $sheet->getStyle('A1')->getFont()->setName('FangSong')->setSize(36)->setBold(true);
        $sheet->getStyle('A2')->getFont()->setName('FangSong')->setSize(36)->setBold(true);
        $sheet->getStyle('A3')->getFont()->setName('FangSong')->setSize(14)->setBold(true);
        $sheet->getStyle("A1:{$hurufAkhir}2")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getRowDimension(2)->setRowHeight(30);

        $this->pasangLogo($sheet);

        // --- Header kolom tetap (baris 3-4 digabung) ---
        foreach (self::KOLOM_TETAP as $i => [$judul, $lebar]) {
            $huruf = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->mergeCells("{$huruf}3:{$huruf}4");
            $sheet->setCellValue("{$huruf}3", $judul);
            $sheet->getColumnDimension($huruf)->setWidth($lebar);
        }

        // --- Header bulan (baris 3) + tanggal (baris 4) ---
        $kolom = count(self::KOLOM_TETAP) + 1;

        foreach ($rekap['bulan'] as $bulan) {
            $awal = Coordinate::stringFromColumnIndex($kolom);
            $akhir = Coordinate::stringFromColumnIndex($kolom + count($bulan['tanggal']) - 1);

            if ($awal !== $akhir) {
                $sheet->mergeCells("{$awal}3:{$akhir}3");
            }

            $sheet->setCellValueExplicit("{$awal}3", $bulan['label'], DataType::TYPE_STRING);

            foreach ($bulan['tanggal'] as $tanggal) {
                $huruf = Coordinate::stringFromColumnIndex($kolom);
                $sheet->setCellValue("{$huruf}4", (int) substr($tanggal, 8, 2));
                $sheet->getColumnDimension($huruf)->setWidth(4.5);
                $kolom++;
            }
        }

        $sheet->mergeCells("{$hurufAkhir}3:{$hurufAkhir}4");
        $sheet->setCellValue("{$hurufAkhir}3", "合计\nTotal");
        $sheet->getColumnDimension($hurufAkhir)->setWidth(8);

        $header = $sheet->getStyle("A3:{$hurufAkhir}4");
        $header->getFont()->setBold(true);
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HIJAU);
        $header->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension(3)->setRowHeight(24);
        $sheet->getRowDimension(4)->setRowHeight(15);

        // --- Isi ---
        $baris = 5;

        foreach ($rekap['baris'] as $toko) {
            $sheet->setCellValue("A{$baris}", $toko['no']);
            $sheet->setCellValue("B{$baris}", $toko['sales']);
            // Dipaksa teks: nomor telepon/aset kehilangan nol di depan atau
            // berubah jadi notasi ilmiah kalau dibiarkan ditebak Excel.
            $sheet->setCellValueExplicit("C{$baris}", $toko['asset_id'], DataType::TYPE_STRING);
            $sheet->setCellValue("D{$baris}", $toko['nama']);
            $sheet->setCellValue("E{$baris}", $toko['alamat']);
            $sheet->setCellValue("F{$baris}", $toko['pemilik']);
            $sheet->setCellValueExplicit("G{$baris}", $toko['telepon'], DataType::TYPE_STRING);

            if ($toko['latitude'] !== null && $toko['longitude'] !== null) {
                $koordinat = $toko['latitude'].', '.$toko['longitude'];
                $sheet->setCellValueExplicit("H{$baris}", $koordinat, DataType::TYPE_STRING);
                // Tautan ke peta, meniru kolom alamat form asli yang berisi
                // link Google Maps berwarna biru.
                $sheet->getCell("H{$baris}")->getHyperlink()
                    ->setUrl("https://www.google.com/maps?q={$toko['latitude']},{$toko['longitude']}");
                $sheet->getStyle("H{$baris}")->getFont()->setUnderline(true)->getColor()->setRGB('0563C1');
            }

            $kolom = count(self::KOLOM_TETAP) + 1;

            foreach ($rekap['bulan'] as $bulan) {
                foreach ($bulan['tanggal'] as $tanggal) {
                    // Hari tanpa pembelian dibiarkan kosong, bukan 0 — sama
                    // seperti form asli, supaya hari yang ADA pembelian
                    // langsung menonjol di antara deretan sel.
                    if (($toko['harian'][$tanggal] ?? 0) > 0) {
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($kolom).$baris, $toko['harian'][$tanggal]);
                    }

                    $kolom++;
                }
            }

            $sheet->setCellValue("{$hurufAkhir}{$baris}", $toko['total']);
            $baris++;
        }

        $tabel = $sheet->getStyle("A3:{$hurufAkhir}".max($barisAkhir, 4));
        $tabel->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        if ($barisAkhir >= 5) {
            $sheet->getStyle("A5:{$hurufAkhir}{$barisAkhir}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        }

        $sheet->freezePane('A5');

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);

        return $spreadsheet;
    }

    private function pasangLogo($sheet): void
    {
        $path = public_path(self::LOGO);

        if (! is_file($path)) {
            return;
        }

        $logo = new Drawing;
        $logo->setPath($path);
        $logo->setHeight(60);
        $logo->setCoordinates('B1');
        $logo->setOffsetY(3);
        $logo->setWorksheet($sheet);
    }
}
