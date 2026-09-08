<?php

namespace App\Livewire\Depot;

use App\Models\Depot;
use App\Services\DepotService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DaftarDepot extends Component
{
    public ?int $depotId = null;

    public bool $formTerbuka = false;

    public string $kode = '';

    public string $nama = '';

    public string $lat = '';

    public string $lng = '';

    public string $serviceMinutes = '10';

    public string $jamBerangkat = '08:00';

    public string $maxToko = '25';

    public string $maxDus = '220';

    public string $minDusPerToko = '5';

    public bool $aktif = true;

    #[Computed]
    public function depots()
    {
        return Depot::query()->orderBy('nama')->get();
    }

    public function buatBaru(): void
    {
        $this->resetForm();
        $this->formTerbuka = true;
    }

    public function sunting(int $id): void
    {
        $depot = Depot::findOrFail($id);

        $this->depotId = $depot->id;
        $this->kode = $depot->kode;
        $this->nama = $depot->nama;
        $this->lat = $depot->lat === null ? '' : (string) $depot->lat;
        $this->lng = $depot->lng === null ? '' : (string) $depot->lng;
        $this->serviceMinutes = (string) $depot->service_minutes;
        $this->jamBerangkat = mb_substr($depot->jam_berangkat, 0, 5);
        $this->maxToko = (string) $depot->max_toko;
        $this->maxDus = (string) $depot->max_dus;
        $this->minDusPerToko = (string) $depot->min_dus_per_toko;
        $this->aktif = $depot->aktif;

        $this->formTerbuka = true;
    }

    public function tutupForm(): void
    {
        $this->resetForm();
        $this->formTerbuka = false;
    }

    private function resetForm(): void
    {
        $this->reset(['depotId', 'kode', 'nama', 'lat', 'lng']);
        $this->serviceMinutes = '10';
        $this->jamBerangkat = '08:00';
        $this->maxToko = '25';
        $this->maxDus = '220';
        $this->minDusPerToko = '5';
        $this->aktif = true;
        $this->resetValidation();
    }

    public function simpan(DepotService $service): void
    {
        if (! auth()->user()->isSuperadmin()) {
            abort(403);
        }

        $data = $this->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('depots', 'kode')->ignore($this->depotId)],
            'nama' => 'required|string|max:255',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'serviceMinutes' => 'required|integer|min:1',
            'jamBerangkat' => 'required|date_format:H:i',
            'maxToko' => 'required|integer|min:1',
            'maxDus' => 'required|integer|min:1',
            'minDusPerToko' => 'required|integer|min:1',
        ], [], [
            'kode' => __('depot.atr_kode'),
            'nama' => __('depot.atr_nama'),
            'lat' => __('depot.atr_lat'),
            'lng' => __('depot.atr_lng'),
            'serviceMinutes' => __('depot.atr_service_minutes'),
            'jamBerangkat' => __('depot.atr_jam_berangkat'),
            'maxToko' => __('depot.atr_max_toko'),
            'maxDus' => __('depot.atr_max_dus'),
            'minDusPerToko' => __('depot.atr_min_dus_per_toko'),
        ]);

        $atribut = [
            'kode' => $data['kode'],
            'nama' => $data['nama'],
            'lat' => $data['lat'] !== null ? (float) $data['lat'] : null,
            'lng' => $data['lng'] !== null ? (float) $data['lng'] : null,
            'service_minutes' => (int) $data['serviceMinutes'],
            'jam_berangkat' => $data['jamBerangkat'],
            'max_toko' => (int) $data['maxToko'],
            'max_dus' => (int) $data['maxDus'],
            'min_dus_per_toko' => (int) $data['minDusPerToko'],
            'aktif' => $this->aktif,
        ];

        $isBaru = $this->depotId === null;

        if ($isBaru) {
            $service->buat($atribut);
        } else {
            $service->ubah(Depot::findOrFail($this->depotId), $atribut);
        }

        $this->tutupForm();
        unset($this->depots);

        $this->dispatch('notifikasi', pesan: $isBaru
            ? __('depot.depot_tersimpan')
            : __('depot.depot_diperbarui'));
    }

    public function render()
    {
        return view('livewire.depot.daftar-depot')->title(__('depot.judul'));
    }
}
