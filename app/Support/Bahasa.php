<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Number;

/**
 * Satu tempat untuk semua urusan pilihan bahasa.
 *
 * Urutan penentuan bahasa: kolom pada akun pengguna, lalu pilihan yang
 * tersimpan di sesi, lalu bahasa bawaan aplikasi. Kolom pengguna diutamakan
 * agar pilihan seseorang ikut ke perangkat mana pun ia masuk.
 */
final class Bahasa
{
    /** @return array<string, array{nama: string, singkat: string, html: string, arah: string}> */
    public static function tersedia(): array
    {
        return config('bahasa.tersedia', []);
    }

    /** @return array<int, string> */
    public static function kode(): array
    {
        return array_keys(self::tersedia());
    }

    public static function didukung(?string $kode): bool
    {
        return $kode !== null && array_key_exists($kode, self::tersedia());
    }

    public static function bawaan(): string
    {
        $bawaan = config('app.locale');

        return self::didukung($bawaan) ? $bawaan : (self::kode()[0] ?? 'id');
    }

    public static function sekarang(): string
    {
        $aktif = App::getLocale();

        return self::didukung($aktif) ? $aktif : self::bawaan();
    }

    /**
     * Keterangan bahasa yang sedang aktif.
     *
     * @return array{kode: string, nama: string, singkat: string, html: string, arah: string}
     */
    public static function info(?string $kode = null): array
    {
        $kode = self::didukung($kode) ? $kode : self::sekarang();

        return ['kode' => $kode] + self::tersedia()[$kode];
    }

    /**
     * Angka dengan pemisah ribuan sesuai kebiasaan bahasa yang aktif:
     * 1.000 dalam bahasa Indonesia, 1,000 dalam bahasa Inggris dan Mandarin.
     */
    public static function angka(int|float|null $nilai, int $desimal = 0): string
    {
        return Number::format((float) ($nilai ?? 0), $desimal, locale: self::htmlLocale());
    }

    /**
     * Nilai rupiah. Mata uangnya tidak diterjemahkan — transaksinya memang
     * dalam rupiah, apa pun bahasa yang dipakai pembaca — tapi cara menulis
     * angkanya mengikuti bahasa yang aktif.
     */
    public static function rupiah(int|float|null $nilai): string
    {
        return 'Rp '.self::angka($nilai);
    }

    /** Kode bahasa dalam bentuk BCP 47, yang dikenali pustaka pemformat. */
    public static function htmlLocale(): string
    {
        return self::info()['html'];
    }

    /** Bahasa yang seharusnya dipakai permintaan ini. */
    public static function pilihan(?User $pengguna): string
    {
        if ($pengguna !== null && self::didukung($pengguna->locale)) {
            return $pengguna->locale;
        }

        $dariSesi = Session::get(config('bahasa.kunci_sesi'));

        if (self::didukung($dariSesi)) {
            return $dariSesi;
        }

        return self::bawaan();
    }

    /**
     * Menetapkan bahasa untuk permintaan ini dan menyimpannya.
     *
     * Pilihan selalu ditulis ke sesi, juga bagi pengguna yang sudah masuk,
     * supaya bahasanya tidak berubah tiba-tiba pada halaman setelah keluar.
     */
    public static function pakai(string $kode, ?User $pengguna = null): void
    {
        if (! self::didukung($kode)) {
            return;
        }

        App::setLocale($kode);
        Session::put(config('bahasa.kunci_sesi'), $kode);

        if ($pengguna !== null && $pengguna->locale !== $kode) {
            $pengguna->forceFill(['locale' => $kode])->save();
        }
    }
}
