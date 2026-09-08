<?php

namespace App\Livewire\Concerns;

use App\Support\DepotContext;
use App\Support\ModeDepot;

/**
 * Dipasang di komponen Livewire yang butuh SATU depot terkunci untuk bisa
 * berfungsi (membuat pesanan, generate routing, dst) — bukan sekadar
 * menampilkan data yang sudah ada. Superadmin dalam mode "Semua Depot"
 * tidak punya satu depot spesifik untuk dijadikan konteks penulisan;
 * tanpa penjagaan ini, halaman akan meledak DepotTidakDiketahui yang
 * membingungkan alih-alih pesan yang jelas.
 *
 * Pemakaian: panggil pastikanDepotTerkunci() di awal mount() (dan hentikan
 * mount() lebih lanjut kalau false), lalu di Blade view bungkus konten
 * dengan `@if ($depotBelumDipilih) <x-butuh-depot-terkunci /> @else ... @endif`.
 */
trait MembutuhkanDepotTerkunci
{
    public bool $depotBelumDipilih = false;

    protected function pastikanDepotTerkunci(): bool
    {
        if (DepotContext::mode() === ModeDepot::Terkunci) {
            return true;
        }

        $this->depotBelumDipilih = true;

        return false;
    }
}
