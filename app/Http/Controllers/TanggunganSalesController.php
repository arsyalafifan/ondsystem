<?php

namespace App\Http\Controllers;

use App\Enums\JenisFotoKunjungan;
use App\Models\Toko;
use App\Services\Kunjungan\KunjunganService;
use App\Services\Kunjungan\PeriodeKunjunganService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Daftar toko tanggungan sales untuk disimpan di perangkat.
 *
 * Diambil selagi masih ada sinyal, lalu dipakai saat tidak ada jaringan
 * untuk mencocokkan hasil pindaian QR dengan toko — pekerjaan yang biasanya
 * dilakukan server lewat `KunjunganService::mulaiDariQr()`.
 *
 * Sengaja ramping: hanya kolom yang benar-benar dibutuhkan layar kunjungan
 * offline. Data toko ikut tersimpan di ponsel sales, jadi semakin sedikit
 * yang dibawa semakin sedikit pula yang ikut hilang bila ponselnya hilang.
 */
class TanggunganSalesController extends Controller
{
    public function __invoke(Request $request, KunjunganService $kunjungan, PeriodeKunjunganService $periodeService): JsonResponse
    {
        $periode = $periodeService->periodeBerjalan();

        $toko = $kunjungan->tanggungan($request->user(), $periode)
            ->map(fn (Toko $t): array => [
                'id' => $t->id,
                'kode' => $t->kode,
                'nama' => $t->nama,
                'alamat' => $t->alamat,
                'asset_id' => $t->asset_id,
                'latitude' => $t->latitude === null ? null : (float) $t->latitude,
                'longitude' => $t->longitude === null ? null : (float) $t->longitude,
                // Toko yang sudah tertangani minggu ini tetap dikirim supaya
                // perangkat bisa memberi tahu sales "ini sudah dikunjungi"
                // alih-alih membiarkannya memotret ulang percuma.
                'perlu_dikunjungi' => $t->perluDikunjungi(),
            ])
            ->values();

        return response()->json([
            'periode' => [
                'id' => $periode->id,
                'kode' => $periode->kode,
            ],
            'diambil_at' => now()->toIso8601String(),
            'foto_wajib' => array_map(
                fn (JenisFotoKunjungan $jenis): array => [
                    'nilai' => $jenis->value,
                    'label' => $jenis->label(),
                    'petunjuk' => $jenis->petunjuk(),
                ],
                JenisFotoKunjungan::urut(),
            ),
            'toko' => $toko,
        ]);
    }
}
