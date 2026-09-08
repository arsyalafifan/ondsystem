<?php

namespace App\Support;

use App\Exceptions\DepotTidakDiketahui;
use App\Models\Depot;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Session\Session;

/**
 * Satu tempat untuk semua urusan "depot mana yang sedang aktif" pada
 * permintaan/job yang berjalan — dipakai App\Models\Scopes\DepotScope
 * untuk memutuskan filter apa yang diterapkan ke tiap query.
 *
 * Didaftarkan sebagai binding scoped() (lihat AppServiceProvider), bukan
 * singleton() biasa — supaya otomatis "kosong" lagi di antara job queue,
 * dan tidak ada depot aktif dari satu job yang bocor ke job berikutnya
 * di proses worker yang sama.
 *
 * API publiknya statis, mengikuti gaya App\Support\Bahasa, tapi di balik
 * layar mendelegasikan ke instance yang tersimpan di container — bukan
 * murni stateless seperti Bahasa, karena di sinilah state "depot aktif"
 * itu sendiri disimpan untuk sepanjang permintaan.
 */
final class DepotContext
{
    private ModeDepot $mode = ModeDepot::BelumDitentukan;

    private ?Depot $depot = null;

    public static function mode(): ModeDepot
    {
        return self::instance()->mode;
    }

    /** Non-null hanya kalau mode()-nya Terkunci. */
    public static function current(): ?Depot
    {
        $context = self::instance();

        return $context->mode === ModeDepot::Terkunci ? $context->depot : null;
    }

    public static function currentOrFail(): Depot
    {
        return self::current() ?? throw DepotTidakDiketahui::saatQuery(Depot::class);
    }

    public static function pakai(Depot $depot): void
    {
        $context = self::instance();
        $context->mode = ModeDepot::Terkunci;
        $context->depot = $depot;
    }

    public static function pakaiSemuaDepot(): void
    {
        $context = self::instance();
        $context->mode = ModeDepot::SemuaDepot;
        $context->depot = null;
    }

    /**
     * Satu-satunya jalur normal (HTTP) menuju pakai()/pakaiSemuaDepot() —
     * dipanggil App\Http\Middleware\TentukanDepot di setiap permintaan
     * yang sudah login.
     *
     * Sengaja memakai $pengguna->depot_id + query manual, BUKAN relasi
     * $pengguna->depot() — model di aplikasi ini mengaktifkan
     * Model::shouldBeStrict() di luar produksi, dan memanggil relasi yang
     * belum di-eager-load akan meledak LazyLoadingViolationException.
     * DepotContext bisa dipanggil dari banyak tempat yang tidak menjamin
     * eager-loading, jadi query langsung lebih aman.
     */
    public static function pakaiUntukPermintaan(User $pengguna, Session $session): void
    {
        if (! $pengguna->isSuperadmin()) {
            // User biasa SELALU terkunci ke depot miliknya sendiri — sesi
            // tidak pernah dikonsultasi untuk peran ini sama sekali, supaya
            // "terkunci ketat" bersifat struktural, bukan sekadar konvensi
            // yang bisa lupa diterapkan di satu tempat.
            $depot = $pengguna->depot_id !== null ? Depot::query()->find($pengguna->depot_id) : null;

            if ($depot === null) {
                throw DepotTidakDiketahui::saatQuery(User::class);
            }

            self::pakai($depot);

            return;
        }

        $pilihan = $session->get('depot_aktif');

        if ($pilihan === 'semua') {
            self::pakaiSemuaDepot();

            return;
        }

        $depot = is_numeric($pilihan) ? Depot::query()->aktif()->find((int) $pilihan) : null;

        if ($depot === null) {
            // Kosong, rusak, atau depot yang dulu dipilih sudah dinonaktifkan
            // — gagal aman ke "semua depot", tidak pernah diam-diam terkunci
            // ke depot yang salah.
            self::pakaiSemuaDepot();

            return;
        }

        self::pakai($depot);
    }

    /** Untuk command/queue job yang perlu bertindak sebagai satu depot spesifik. */
    public static function jalankanSebagai(Depot|int $depot, Closure $callback): mixed
    {
        $depot = $depot instanceof Depot ? $depot : Depot::findOrFail($depot);

        return self::jalankanDenganPemulihan(fn () => self::pakai($depot), $callback);
    }

    /** Untuk command/queue job yang perlu bertindak lintas semua depot. */
    public static function jalankanUntukSemuaDepot(Closure $callback): mixed
    {
        return self::jalankanDenganPemulihan(fn () => self::pakaiSemuaDepot(), $callback);
    }

    private static function jalankanDenganPemulihan(Closure $tetapkan, Closure $callback): mixed
    {
        $context = self::instance();
        $modeSemula = $context->mode;
        $depotSemula = $context->depot;

        $tetapkan();

        try {
            return $callback();
        } finally {
            $context->mode = $modeSemula;
            $context->depot = $depotSemula;
        }
    }

    private static function instance(): self
    {
        return app(self::class);
    }
}
