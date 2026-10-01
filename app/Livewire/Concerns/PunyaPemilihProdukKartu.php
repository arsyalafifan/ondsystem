<?php

namespace App\Livewire\Concerns;

/**
 * Jembatan antara kartu pemilihan produk (resources/views/components/pilih-produk-kartu.blade.php)
 * dan daftar item yang SUDAH ADA di komponen ($baris, $barisBonus, ...).
 *
 * Kartu cuma menetapkan "produk X sejumlah N" — sisanya (validasi,
 * halangan(), simpan(), PesananService) tetap membaca daftar yang sama
 * persis dengan struktur [produk_id, jumlah_dus] seperti sebelumnya,
 * jadi logika pesanan tidak berubah sama sekali.
 */
trait PunyaPemilihProdukKartu
{
    /** @return list<string> nama properti daftar yang boleh diubah lewat kartu */
    abstract protected function daftarKartuDiizinkan(): array;

    public function aturJumlah(string $daftar, int $produkId, int|string|null $jumlah): void
    {
        if ($produkId <= 0 || ! in_array($daftar, $this->daftarKartuDiizinkan(), true)) {
            return;
        }

        $jumlah = max(0, min(99999, (int) $jumlah));
        $hasil = [];
        $ketemu = false;

        foreach ($this->{$daftar} as $b) {
            $id = (int) ($b['produk_id'] ?? 0);

            // Baris kosong bawaan (belum pilih produk) tidak dibutuhkan kartu.
            if ($id <= 0) {
                continue;
            }

            if ($id !== $produkId) {
                $hasil[] = $b;

                continue;
            }

            // Produk yang sama di beberapa baris disatukan ke posisi
            // pertamanya supaya urutan daftar tidak meloncat-loncat.
            if (! $ketemu && $jumlah > 0) {
                $hasil[] = ['produk_id' => $produkId, 'jumlah_dus' => $jumlah];
            }

            $ketemu = true;
        }

        if (! $ketemu && $jumlah > 0) {
            $hasil[] = ['produk_id' => $produkId, 'jumlah_dus' => $jumlah];
        }

        $this->{$daftar} = $hasil;

        // Pemicu updated*() yang sama seperti kalau daftarnya diubah lewat
        // wire:model biasa (mis. BuatPesanan::updatedBaris() untuk promo).
        $kait = 'updated'.ucfirst($daftar);

        if (method_exists($this, $kait)) {
            $this->{$kait}();
        }
    }
}
