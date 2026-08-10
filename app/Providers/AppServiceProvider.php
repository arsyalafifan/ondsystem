<?php

namespace App\Providers;

use App\Services\Peta\NominatimGeocoder;
use App\Services\Peta\OsrmClient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OsrmClient::class, fn () => OsrmClient::fromConfig());
        $this->app->singleton(NominatimGeocoder::class, fn () => NominatimGeocoder::fromConfig());
    }

    public function boot(): void
    {
        $this->percayaiProksi();

        // Menahan pemakaian data yang tidak lengkap sejak tahap pengembangan,
        // bukan menunggu ketahuan di produksi. Mode ketat juga menyala saat
        // pengujian, supaya kesalahan seperti relasi yang kolomnya kurang
        // terambil ditangkap tes, bukan baru muncul di layar pengguna.
        Model::shouldBeStrict(! $this->app->isProduction());

        Date::use(CarbonImmutable::class);

        // Angka dan nilai rupiah selalu ditulis mengikuti bahasa yang aktif.
        // Dibuat sebagai direktif supaya nama kelas panjang tidak perlu
        // disebut berulang di setiap berkas tampilan.
        Blade::directive('angka', fn (string $argumen) => "<?php echo \App\Support\Bahasa::angka({$argumen}); ?>");
        Blade::directive('rupiah', fn (string $argumen) => "<?php echo \App\Support\Bahasa::rupiah({$argumen}); ?>");
    }

    /**
     * Menetapkan proksi yang boleh dipercaya headernya.
     *
     * Ditaruh di sini, bukan di bootstrap/app.php, karena closure middleware
     * di berkas itu dijalankan sebelum berkas config dimuat — config() belum
     * bisa dibaca di sana. Provider ini di-boot sebelum middleware menangani
     * permintaan, jadi daftarnya sudah siap tepat pada waktunya.
     */
    private function percayaiProksi(): void
    {
        $proksi = config('ond.proksi_dipercaya');

        if ($proksi === [] || $proksi === null) {
            return;
        }

        TrustProxies::at($proksi);

        TrustProxies::withHeaders(
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB
        );
    }
}
