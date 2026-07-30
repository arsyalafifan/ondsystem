<?php

namespace App\Providers;

use App\Services\Peta\NominatimGeocoder;
use App\Services\Peta\OsrmClient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
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
}
