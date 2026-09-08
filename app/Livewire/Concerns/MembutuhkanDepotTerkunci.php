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
 * Dua cara pakai:
 * - Halaman yang SELURUHNYA tidak berguna tanpa depot terkunci (Buat
 *   Pesanan, Generate Routing): panggil pastikanDepotTerkunci() di awal
 *   mount() (dan hentikan mount() lebih lanjut kalau false), lalu di Blade
 *   view bungkus konten dengan
 *   `@if ($depotBelumDipilih) <x-butuh-depot-terkunci /> @else ... @endif`.
 * - Aksi tulis tunggal di halaman yang selebihnya tetap berguna dibaca
 *   (mis. daftar master yang punya tombol "Simpan"): panggil
 *   `if ($this->tolakJikaTidakTerkunci()) { return; }` di awal method
 *   aksinya saja — menampilkan notifikasi ramah lalu berhenti, tanpa
 *   mengganggu sisa halaman.
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

    protected function tolakJikaTidakTerkunci(): bool
    {
        if (DepotContext::mode() === ModeDepot::Terkunci) {
            return false;
        }

        $this->dispatch('notifikasi', pesan: __('umum.butuh_depot_aksi'), jenis: 'error');

        return true;
    }
}
