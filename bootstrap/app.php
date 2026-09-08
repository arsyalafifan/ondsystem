<?php

use App\Http\Middleware\AturBahasa;
use App\Http\Middleware\PastikanPeran;
use App\Http\Middleware\SemuaDepotUntukCetak;
use App\Http\Middleware\TentukanDepot;
use App\Support\DepotContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Daftar proksi yang dipercaya ditetapkan di AppServiceProvider,
        // bukan di sini: closure ini dijalankan sebelum berkas config dimuat,
        // sehingga config('ond.proksi_dipercaya') belum bisa dibaca.

        // Bahasa ditetapkan sebelum apa pun dijalankan, termasuk sebelum
        // pesan galat validasi dibentuk.
        //
        // TentukanDepot TERNYATA harus global juga (bukan hanya di grup
        // 'auth' seperti rencana awal): StartSession menulis ulang baris
        // sesi di akhir SETIAP permintaan lewat DatabaseSessionHandler,
        // dan langkah itu memanggil SessionGuard->user() untuk mencatat
        // user_id pemiliknya — query User yang ikut kena DepotScope. Kalau
        // ada sesi login lama yang masih aktif saat rute tamu diakses
        // (/masuk, /bahasa, dst — yang tidak lewat grup 'auth'), query itu
        // meledak DepotTidakDiketahui walau rute itu sendiri tidak pernah
        // membaca data yang di-scope. TentukanDepot sendiri sudah aman
        // dipanggil untuk tamu (no-op kalau $request->user() null).
        $middleware->web(append: [
            AturBahasa::class,
            TentukanDepot::class,
        ]);

        $middleware->alias([
            'peran' => PastikanPeran::class,
            'depot.semua' => SemuaDepotUntukCetak::class,
        ]);

        // Route-model binding implisit (mis. Pesanan $pesanan di parameter
        // rute) di-resolve middleware SubstituteBindings — kalau itu jalan
        // sebelum konteks depot ditetapkan, query binding-nya sendiri akan
        // melempar DepotTidakDiketahui. Eksplisit dipaksa lebih dulu di sini,
        // bukan mengandalkan urutan penulisan middleware yang ambigu.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: TentukanDepot::class,
        );
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SemuaDepotUntukCetak::class,
        );

        $middleware->redirectGuestsTo(fn () => route('masuk'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Setiap galat yang masuk log otomatis membawa info depot mana yang
        // sedang aktif — tidak perlu menelusuri stack trace panjang untuk
        // tahu galat itu dari depot mana, walau jumlah depot terus bertambah.
        $exceptions->context(fn () => [
            'depot' => DepotContext::mode()->value.':'.(DepotContext::current()?->kode ?? '-'),
        ]);
    })->create();
