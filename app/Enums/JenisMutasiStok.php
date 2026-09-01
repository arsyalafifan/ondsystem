<?php

namespace App\Enums;

/**
 * Jenis satu baris `StokMutasi` — kenapa angka stok sebuah produk berubah.
 *
 * `reserve`/`release` sama sekali tidak menyentuh stok fisik (`produks.stok`),
 * cuma kuncian (`stok_reserved`) — dus-nya belum/sudah tidak lagi dijanjikan
 * ke pesanan tertentu, tapi belum tentu berpindah tempat. `keluar` adalah
 * SATU-SATUNYA jenis yang benar-benar mengurangi stok fisik untuk selamanya
 * (barang sungguh meninggalkan gudang). `masuk` menambah stok fisik (barang
 * datang / stok awal produk baru). `penyesuaian` dipakai admin lewat
 * Master Produk untuk koreksi manual (stok opname, barang rusak, dsb.) —
 * bisa naik maupun turun, dibedakan dari `masuk` supaya riwayat tetap jelas
 * mana perubahan yang lewat penerimaan barang normal dan mana yang koreksi.
 */
enum JenisMutasiStok: string
{
    case Reserve = 'reserve';
    case Release = 'release';
    case Keluar = 'keluar';
    case Masuk = 'masuk';
    case Penyesuaian = 'penyesuaian';

    public function label(): string
    {
        return __('master.mutasi_'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Reserve => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::Release => 'bg-sky-100 text-sky-800 ring-sky-600/20',
            self::Keluar => 'bg-red-100 text-red-800 ring-red-600/20',
            self::Masuk => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::Penyesuaian => 'bg-violet-100 text-violet-800 ring-violet-600/20',
        };
    }
}
