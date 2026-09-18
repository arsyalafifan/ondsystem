<?php

namespace App\Services\Izin;

use App\Akses\AksesTambahan;
use App\Akses\HakAkses;
use App\Enums\ModePersetujuanIzin;
use App\Enums\PeranPengguna;
use App\Models\Karyawan;
use App\Models\PengajuanIzin;
use App\Models\PengajuanLembur;
use App\Models\PengaturanIzin;
use App\Models\Scopes\DepotScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Menjawab "siapa yang boleh memutuskan pengajuan ini" — izin/sakit
 * maupun lembur, dengan aturan yang sama — dari setting Approval
 * (App\Models\PengaturanIzin).
 *
 * Aturan yang TIDAK bisa diubah lewat setting:
 * 1. Tidak ada yang boleh memutuskan pengajuannya sendiri — termasuk HR
 *    dan superadmin.
 * 2. Pengajuan tidak boleh macet: tanpa approver yang sah (mis. HR cuma
 *    satu dan dialah yang izin), jatuh ke superadmin.
 *
 * Mode "atasan langsung": atasan (lewat akun yang tertaut ke karyawan
 * atasannya) yang memutuskan; bila atasan kosong / tanpa akun aktif / dia
 * sendiri pengajunya, jatuh ke approver umum. Superadmin selalu boleh
 * memutuskan (kecuali miliknya sendiri), sama seperti di seluruh aplikasi.
 *
 * Didaftarkan scoped() (AppServiceProvider) supaya setting & hasil
 * per-pengguna cukup dihitung sekali per permintaan — sidebar menanyakan
 * akses menu berkali-kali.
 */
class ApproverIzin implements AksesTambahan
{
    public const JALUR_ATASAN = 'atasan';

    public const JALUR_UMUM = 'umum';

    public const JALUR_CADANGAN = 'cadangan';

    private ?PengaturanIzin $pengaturan = null;

    /** @var array<int, bool> */
    private array $approverMemo = [];

    public function pengaturan(): PengaturanIzin
    {
        return $this->pengaturan ??= PengaturanIzin::ambil();
    }

    public function lupakan(): void
    {
        $this->pengaturan = null;
        $this->approverMemo = [];
    }

    /**
     * Approver umum dari setting (peran + orang), tanpa akun-akun $kecuali.
     *
     * @param  list<int>  $kecuali
     * @return Collection<int, User>
     */
    public function approverUmum(array $kecuali = []): Collection
    {
        $pengaturan = $this->pengaturan();

        return $this->pengguna()
            ->where(fn (Builder $q) => $q
                ->whereIn('role', $pengaturan->peran())
                ->orWhereIn('id', $pengaturan->idPengguna()))
            ->whereNotIn('id', $kecuali)
            ->orderBy('name')
            ->get();
    }

    /**
     * Penyetuju sebuah pengajuan beserta jalurnya — dipakai untuk
     * menampilkan "menunggu persetujuan siapa" dan memeriksa keputusan.
     *
     * @return array{jalur: string, pengguna: Collection<int, User>}
     */
    public function untuk(PengajuanIzin|PengajuanLembur $pengajuan): array
    {
        $kecuali = $this->idPengaju($pengajuan);

        if ($this->pengaturan()->mode === ModePersetujuanIzin::Atasan) {
            $pengajuan->loadMissing('karyawan.atasan');
            $akunAtasan = $pengajuan->karyawan?->atasan?->user_id;

            if ($akunAtasan !== null && ! in_array($akunAtasan, $kecuali, true)) {
                $atasan = $this->pengguna()->whereKey($akunAtasan)->get();

                if ($atasan->isNotEmpty()) {
                    return ['jalur' => self::JALUR_ATASAN, 'pengguna' => $atasan];
                }
            }
        }

        $umum = $this->approverUmum($kecuali);

        if ($umum->isNotEmpty()) {
            return ['jalur' => self::JALUR_UMUM, 'pengguna' => $umum];
        }

        return [
            'jalur' => self::JALUR_CADANGAN,
            'pengguna' => $this->pengguna()->where('role', PeranPengguna::Superadmin)->whereNotIn('id', $kecuali)->orderBy('name')->get(),
        ];
    }

    public function bolehMemutuskan(User $pengguna, PengajuanIzin|PengajuanLembur $pengajuan): bool
    {
        if (in_array($pengguna->id, $this->idPengaju($pengajuan), true)) {
            return false;
        }

        if ($pengguna->isSuperadmin()) {
            return true;
        }

        return $this->untuk($pengajuan)['pengguna']->contains('id', $pengguna->id);
    }

    /**
     * Apakah pengguna ini (berpotensi) approver — dipakai membuka menu
     * Persetujuan Izin untuk yang perannya sendiri tidak memberi akses.
     */
    public function bolehBukaMenu(User $pengguna): bool
    {
        return $this->approverMemo[$pengguna->id] ??= $this->adalahApproverUmum($pengguna)
            || ($this->pengaturan()->mode === ModePersetujuanIzin::Atasan && $this->punyaBawahan($pengguna));
    }

    /**
     * Membatasi daftar pengajuan yang boleh DILIHAT: yang berhak atas menu
     * lewat perannya (HR) atau approver umum melihat semua; atasan hanya
     * melihat milik bawahannya.
     */
    public function batasiDaftar(Builder $query, User $pengguna): void
    {
        if ($pengguna->isSuperadmin()
            || app(HakAkses::class)->bolehPeran($pengguna->role, 'hr.persetujuan_izin')
            || app(HakAkses::class)->bolehPeran($pengguna->role, 'hr.persetujuan_lembur')
            || $this->adalahApproverUmum($pengguna)) {
            return;
        }

        $karyawanId = Karyawan::query()->where('user_id', $pengguna->id)->value('id');

        $query->whereHas('karyawan', fn (Builder $k) => $k->where('atasan_id', $karyawanId ?? 0));
    }

    private function adalahApproverUmum(User $pengguna): bool
    {
        $pengaturan = $this->pengaturan();

        return $pengguna->aktif
            && (in_array($pengguna->role->value, $pengaturan->peran(), true)
                || in_array($pengguna->id, $pengaturan->idPengguna(), true));
    }

    private function punyaBawahan(User $pengguna): bool
    {
        return Karyawan::query()
            ->where('user_id', $pengguna->id)
            ->whereHas('bawahan')
            ->exists();
    }

    /** @return list<int> akun karyawan yang izin dan akun yang mengajukan */
    private function idPengaju(PengajuanIzin|PengajuanLembur $pengajuan): array
    {
        $pengajuan->loadMissing('karyawan');

        return array_values(array_unique(array_filter([
            $pengajuan->karyawan?->user_id,
            $pengajuan->diajukan_oleh,
        ])));
    }

    /** Akun aktif lintas gudang — HR & approver tidak terikat satu depot. */
    private function pengguna(): Builder
    {
        return User::withoutGlobalScope(DepotScope::class)->where('aktif', true);
    }
}
