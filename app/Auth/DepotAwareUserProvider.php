<?php

namespace App\Auth;

use App\Models\Scopes\DepotScope;
use Illuminate\Auth\EloquentUserProvider;

/**
 * Provider auth khusus supaya Laravel bisa menemukan "siapa pemilik sesi
 * ini" TANPA lebih dulu tahu depot mana yang aktif.
 *
 * Ini bukan sekadar preferensi desain — melainkan celah ayam-dan-telur
 * yang nyata: App\Models\Scopes\DepotScope butuh App\Support\DepotContext
 * sudah ditetapkan sebelum query User manapun boleh jalan, tapi
 * DepotContext (untuk user biasa) justru DITENTUKAN dari depot_id milik
 * User yang baru mau ditemukan. EloquentUserProvider bawaan Laravel
 * (dipakai SessionGuard->user(), termasuk saat DatabaseSessionHandler
 * mencatat pemilik sesi di akhir SETIAP permintaan) memakai query User
 * yang di-scope biasa — provider ini melewati DepotScope khusus untuk
 * pencarian identitas, sebelum konteks depotnya sendiri ada.
 *
 * Aman: menemukan baris User lewat id/token/kredensial pada dasarnya
 * operasi lintas-depot yang sah (memang itu prasyarat SEBELUM konteks
 * depot bisa ditentukan) — bukan celah kebocoran data, karena provider
 * ini hanya dipakai jalur otentikasi bawaan Laravel, tidak dipakai untuk
 * menampilkan daftar/isi data ke pengguna.
 */
class DepotAwareUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope(DepotScope::class);
    }
}
