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
        $middleware->web(append: [
            AturBahasa::class,
        ]);

        $middleware->alias([
            'peran' => PastikanPeran::class,
            // Dipasang di level grup rute 'auth' (routes/web.php), bukan
            // global seperti AturBahasa — lihat catatan di TentukanDepot.
            'depot' => TentukanDepot::class,
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
