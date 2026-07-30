<?php

namespace App\Services\Peta;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengubah alamat teks menjadi koordinat memakai Nominatim (OpenStreetMap).
 *
 * Nominatim publik membatasi satu permintaan per detik dan mewajibkan
 * User-Agent yang bisa dihubungi. Kelas ini menahan laju permintaan sendiri
 * dan menyimpan hasilnya di cache agar alamat yang sama tidak diminta ulang.
 */
class NominatimGeocoder
{
    private const CACHE_HARI = 90;

    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $email,
        private readonly string $country,
        private readonly int $timeout,
        private readonly int $rateLimitMs,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: config('ond.nominatim.url'),
            email: config('ond.nominatim.email'),
            country: config('ond.nominatim.country'),
            timeout: config('ond.nominatim.timeout'),
            rateLimitMs: config('ond.nominatim.rate_limit_ms'),
        );
    }

    /**
     * Cari koordinat dari sebuah alamat.
     *
     * @return array{lat: float, lng: float, display_name: string, tingkat: string}|null
     */
    public function cari(string $alamat): ?array
    {
        $alamat = trim(preg_replace('/\s+/', ' ', $alamat) ?? '');

        if ($alamat === '') {
            return null;
        }

        $kunci = 'geocode:'.md5(mb_strtolower($alamat).'|'.$this->country);

        $tersimpan = Cache::get($kunci);

        if ($tersimpan !== null) {
            // Alamat yang sebelumnya gagal disimpan sebagai false supaya
            // tidak dicoba berulang kali dalam waktu dekat.
            return $tersimpan === false ? null : $tersimpan;
        }

        $hasil = $this->minta($alamat);

        Cache::put($kunci, $hasil ?? false, now()->addDays(self::CACHE_HARI));

        return $hasil;
    }

    /**
     * Kebalikannya: koordinat menjadi alamat. Dipakai saat admin menaruh
     * penanda langsung di peta, agar kolom alamat ikut terisi.
     */
    public function cariBalik(float $lat, float $lng): ?string
    {
        try {
            $this->tahanLaju();

            $respons = $this->http()->get("{$this->baseUrl}/reverse", array_filter([
                'lat' => $lat,
                'lon' => $lng,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'email' => $this->email,
            ]));

            if (! $respons->successful()) {
                return null;
            }

            return $respons->json('display_name');
        } catch (Throwable $e) {
            Log::warning('Nominatim reverse error.', ['pesan' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{lat: float, lng: float, display_name: string, tingkat: string}|null
     */
    private function minta(string $alamat): ?array
    {
        try {
            $this->tahanLaju();

            $respons = $this->http()->get("{$this->baseUrl}/search", array_filter([
                'q' => $alamat,
                'format' => 'jsonv2',
                'limit' => 1,
                'countrycodes' => $this->country,
                'addressdetails' => 1,
                'email' => $this->email,
            ]));

            if (! $respons->successful()) {
                Log::warning('Nominatim search gagal.', ['status' => $respons->status()]);

                return null;
            }

            $baris = $respons->json(0);

            if (! is_array($baris) || ! isset($baris['lat'], $baris['lon'])) {
                return null;
            }

            return [
                'lat' => (float) $baris['lat'],
                'lng' => (float) $baris['lon'],
                'display_name' => (string) ($baris['display_name'] ?? $alamat),
                // Semakin spesifik tipe hasilnya, semakin bisa dipercaya
                // titiknya. "building"/"house" bagus, "city" berarti hanya
                // dapat titik tengah kota dan perlu dikoreksi manual.
                'tingkat' => (string) ($baris['addresstype'] ?? $baris['type'] ?? 'tidak diketahui'),
            ];
        } catch (Throwable $e) {
            Log::warning('Nominatim search error.', ['pesan' => $e->getMessage()]);

            return null;
        }
    }

    private function http()
    {
        return Http::timeout($this->timeout)
            ->withHeaders([
                'User-Agent' => config('app.name').' ('.($this->email ?? 'kontak tidak diisi').')',
                'Accept-Language' => 'id,en',
            ]);
    }

    /** Menahan laju agar tidak melanggar kebijakan pemakaian Nominatim. */
    private function tahanLaju(): void
    {
        $kunci = 'geocode:terakhir';
        $terakhir = Cache::get($kunci);

        if ($terakhir !== null) {
            $selisihMs = (microtime(true) - (float) $terakhir) * 1000;

            if ($selisihMs < $this->rateLimitMs) {
                usleep((int) (($this->rateLimitMs - $selisihMs) * 1000));
            }
        }

        Cache::put($kunci, microtime(true), now()->addMinutes(5));
    }
}
