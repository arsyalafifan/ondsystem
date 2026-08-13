<?php

namespace App\Services\Peta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pembungkus OSRM untuk jarak dan durasi jalan sebenarnya.
 *
 * Semua method dirancang tidak pernah melempar exception ke pemanggil:
 * kalau server tidak bisa dihubungi, hasilnya null dan mesin routing
 * otomatis memakai perhitungan garis lurus. Rute tetap jadi, hanya
 * akurasinya sedikit menurun.
 *
 * Dua server bisa dipasang: $baseUrl (utama, biasanya self-hosted lewat
 * Docker) dicoba lebih dulu, lalu $fallbackUrl (biasanya server publik)
 * dicoba hanya kalau yang utama gagal dihubungi — bukan kalau utama
 * sekadar menolak permintaan (mis. matriks kebesaran).
 */
class OsrmClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $fallbackUrl,
        private readonly int $timeout,
        private readonly int $maxTableSize,
        private readonly bool $enabled,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: config('ond.osrm.url'),
            fallbackUrl: config('ond.osrm.fallback_url'),
            timeout: config('ond.osrm.timeout'),
            maxTableSize: config('ond.osrm.max_table_size'),
            enabled: config('ond.osrm.enabled'),
        );
    }

    public function aktif(): bool
    {
        return $this->enabled;
    }

    /**
     * Matriks jarak dan durasi antar semua titik.
     *
     * @param  array<int, Koordinat>  $titik
     */
    public function matriks(array $titik): ?MatriksJarak
    {
        $n = count($titik);

        if (! $this->enabled || $n < 2) {
            return null;
        }

        if ($n > $this->maxTableSize) {
            Log::info('OSRM: titik melebihi batas matriks, memakai perhitungan garis lurus.', [
                'jumlah' => $n,
                'batas' => $this->maxTableSize,
            ]);

            return null;
        }

        $koordinat = implode(';', array_map(fn (Koordinat $k) => $k->untukOsrm(), $titik));

        foreach ($this->urlUntukDicoba() as $url) {
            $hasil = $this->mintaMatriks($url, $koordinat, $titik);

            if ($hasil !== null) {
                return $hasil;
            }
        }

        return null;
    }

    private function mintaMatriks(string $url, string $koordinat, array $titik): ?MatriksJarak
    {
        try {
            $respons = Http::timeout($this->timeout)
                ->retry(2, 500, throw: false)
                ->get("{$url}/table/v1/driving/{$koordinat}", [
                    'annotations' => 'duration,distance',
                ]);

            if (! $respons->successful()) {
                Log::warning('OSRM /table gagal.', ['url' => $url, 'status' => $respons->status()]);

                return null;
            }

            $data = $respons->json();

            if (($data['code'] ?? null) !== 'Ok') {
                Log::warning('OSRM /table menolak permintaan.', ['url' => $url, 'code' => $data['code'] ?? '?']);

                return null;
            }

            $durasi = $data['durations'] ?? null;
            $jarak = $data['distances'] ?? null;

            if (! is_array($durasi) || ! is_array($jarak)) {
                return null;
            }

            // OSRM mengirim null bila dua titik tidak terhubung jalan.
            // Titik seperti itu diberi nilai garis lurus supaya matriks tetap utuh.
            foreach ($durasi as $i => $baris) {
                foreach ($baris as $j => $nilai) {
                    if ($nilai === null || $jarak[$i][$j] === null) {
                        $m = Geo::haversine($titik[$i], $titik[$j]) * 1.35;
                        $jarak[$i][$j] = $m;
                        $durasi[$i][$j] = $m / (25 * 1000 / 3600);
                    }
                }
            }

            return new MatriksJarak($jarak, $durasi, 'osrm');
        } catch (Throwable $e) {
            Log::warning('OSRM /table error.', ['url' => $url, 'pesan' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Rute melewati titik-titik sesuai urutan yang diberikan.
     *
     * @param  array<int, Koordinat>  $titik
     * @return array{jarak_m: float, durasi_s: float, geometry: string, legs: array<int, array{jarak_m: float, durasi_s: float}>}|null
     */
    public function rute(array $titik): ?array
    {
        if (! $this->enabled || count($titik) < 2) {
            return null;
        }

        $koordinat = implode(';', array_map(fn (Koordinat $k) => $k->untukOsrm(), $titik));

        foreach ($this->urlUntukDicoba() as $url) {
            $hasil = $this->mintaRute($url, $koordinat);

            if ($hasil !== null) {
                return $hasil;
            }
        }

        return null;
    }

    private function mintaRute(string $url, string $koordinat): ?array
    {
        try {
            $respons = Http::timeout($this->timeout)
                ->retry(2, 500, throw: false)
                ->get("{$url}/route/v1/driving/{$koordinat}", [
                    'overview' => 'full',
                    'geometries' => 'polyline',
                    'steps' => 'false',
                    'annotations' => 'false',
                ]);

            if (! $respons->successful()) {
                return null;
            }

            $data = $respons->json();

            if (($data['code'] ?? null) !== 'Ok' || empty($data['routes'][0])) {
                return null;
            }

            $rute = $data['routes'][0];

            $legs = array_map(fn (array $leg): array => [
                'jarak_m' => (float) ($leg['distance'] ?? 0),
                'durasi_s' => (float) ($leg['duration'] ?? 0),
            ], $rute['legs'] ?? []);

            return [
                'jarak_m' => (float) ($rute['distance'] ?? 0),
                'durasi_s' => (float) ($rute['duration'] ?? 0),
                'geometry' => (string) ($rute['geometry'] ?? ''),
                'legs' => $legs,
            ];
        } catch (Throwable $e) {
            Log::warning('OSRM /route error.', ['url' => $url, 'pesan' => $e->getMessage()]);

            return null;
        }
    }

    /** Cek cepat apakah server OSRM (utama) bisa dihubungi. */
    public function cekKoneksi(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        try {
            $depot = new Koordinat(config('ond.depot.lat'), config('ond.depot.lng'));
            $dekat = new Koordinat(config('ond.depot.lat') + 0.01, config('ond.depot.lng') + 0.01);
            $koordinat = implode(';', [$depot->untukOsrm(), $dekat->untukOsrm()]);

            return $this->mintaRute($this->baseUrl, $koordinat) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<int, string> */
    private function urlUntukDicoba(): array
    {
        return array_values(array_filter([$this->baseUrl, $this->fallbackUrl]));
    }
}
