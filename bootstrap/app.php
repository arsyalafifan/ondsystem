<?php

use App\Http\Middleware\AturBahasa;
use App\Http\Middleware\PastikanPeran;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
        ]);

        $middleware->redirectGuestsTo(fn () => route('masuk'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
