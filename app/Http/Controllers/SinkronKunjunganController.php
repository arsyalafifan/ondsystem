<?php

namespace App\Http\Controllers;

use App\Services\Kunjungan\SinkronOffline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pintu masuk kunjungan yang dikerjakan sales tanpa jaringan.
 *
 * Sengaja rute web biasa (sesi + CSRF), bukan token API: perangkat yang
 * mengirim adalah peramban sales yang sudah login, jadi tidak perlu jalur
 * kredensial kedua yang harus dijaga terpisah.
 *
 * Kalau sesinya sudah kedaluwarsa saat kiriman datang, Laravel menjawab 419
 * dan perangkat cukup meminta sales login ulang — antrean di perangkat tidak
 * ikut hilang, jadi tidak ada foto yang terbuang karena sesi basi.
 */
class SinkronKunjunganController extends Controller
{
    public function __invoke(Request $request, SinkronOffline $sinkron): JsonResponse
    {
        $data = $request->validate([
            'kunjungans' => ['required', 'array', 'min:1', 'max:'.(int) config('visit.offline.maks_per_kiriman')],
            'kunjungans.*.uuid_klien' => ['required', 'uuid'],
            'kunjungans.*.toko_id' => ['required', 'integer'],
            'kunjungans.*.status' => ['required', 'string'],
            'kunjungans.*.mulai_at' => ['required', 'string'],
            'kunjungans.*.selesai_at' => ['nullable', 'string'],
            'kunjungans.*.asset_id_terpindai' => ['nullable', 'string', 'max:40'],
            'kunjungans.*.catatan_sales' => ['nullable', 'string'],
            'kunjungans.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'kunjungans.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'kunjungans.*.akurasi_m' => ['nullable', 'integer', 'min:0'],
            'kunjungans.*.fotos' => ['required', 'array', 'min:1'],
            'kunjungans.*.fotos.*.jenis' => ['required', 'string'],
            'kunjungans.*.fotos.*.gambar' => ['required', 'string'],
            'kunjungans.*.fotos.*.diambil_at' => ['nullable', 'string'],
            'kunjungans.*.fotos.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'kunjungans.*.fotos.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'kunjungans.*.fotos.*.akurasi_m' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json([
            'hasil' => $sinkron->sinkronkan($data['kunjungans'], $request->user()),
        ]);
    }
}
