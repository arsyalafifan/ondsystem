<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Nota {{ $pesanan->kode }}</title>
    <style>
        {{-- Selalu ukuran besar (24x28cm, portrait): kertas continuous form fisiknya
             9.5x11 inci, dan bila item pesanannya sedikit, sisa kertas kosong di
             bawah nota tinggal disobek manual. Ukuran "kecil" sengaja tidak lagi
             dipakai — bentuknya lebih lebar daripada tinggi (landscape), yang
             membuat driver printer salah merotasi hasil cetak. --}}
        @page {
            size: 24cm 28cm;
            margin: 0.5cm 0.8cm;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
        }

        {{-- Sans-serif tebal dipilih sengaja, bukan sekadar selera: baris tipis
             font monospace (Courier) sebagian hilang saat dirasterisasi ke
             kerapatan titik printer dot-matrix, membuat hasil cetak terlihat
             pecah/kurang tajam meski mode printer sudah NLQ. --}}
        body {
            font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;
            font-size: 13.5px;
            font-weight: 500;
            line-height: 1.4;
            color: #000;
            width: 22.4cm;
        }

        {{-- display:table dipakai di seluruh berkas ini, bukan flex — supaya
             satu template yang sama bisa dirender identik oleh Dompdf (untuk
             tombol Unduh PDF) maupun oleh browser (untuk tombol Cetak).
             Dompdf tidak mendukung flexbox sama sekali. --}}
        /* --- Kop: perusahaan | kepada | faktur, sebaris seperti aslinya --- */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 4px;
        }

        .perusahaan {
            display: table-cell;
            vertical-align: top;
            width: 38%;
            padding-right: 10px;
        }

        .perusahaan p {
            margin: 1px 0;
        }

        .perusahaan .nama {
            font-size: 1.3em;
            font-weight: bold;
        }

        .kepada {
            display: table-cell;
            vertical-align: top;
            padding-right: 10px;
        }

        .kepada table {
            border-collapse: collapse;
        }

        .kepada td {
            border: none;
            padding: 0 4px 1px 0;
            vertical-align: top;
        }

        .kepada td.label {
            white-space: nowrap;
            padding-right: 6px;
        }

        .faktur {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            white-space: nowrap;
        }

        .faktur .judul {
            font-size: 1.15em;
            font-weight: bold;
            letter-spacing: 1px;
            margin: 0 0 2px;
        }

        .faktur table {
            margin-left: auto;
            border: none;
            border-collapse: collapse;
        }

        .faktur td {
            border: none;
            padding: 0 0 0 6px;
            text-align: left;
        }

        .aset {
            font-size: 0.95em;
            text-align: center;
            margin: 2px 0 4px;
        }

        /* --- Tabel barang: cuma 2 garis di header, 1 garis di bawah body --- */
        table.item {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 2px;
        }

        table.item th, table.item td {
            border: none;
            padding: 2px 4px;
        }

        table.item th {
            text-align: left;
            font-weight: 700;
            border-top: 1.5px solid #000;
            border-bottom: 1.5px solid #000;
        }

        table.item td.num, table.item th.num {
            text-align: right;
        }

        table.item tfoot .garis td {
            border-top: 1.5px solid #000;
            padding: 0;
        }

        table.item tfoot td {
            padding: 2px 4px;
        }

        table.item tfoot .label-kanan {
            text-align: right;
            padding-right: 8px;
        }

        table.item tfoot .terbilang em {
            font-style: italic;
        }

        table.item tfoot .catatan-pajak {
            text-align: right;
            font-size: 1em;
            font-style: italic;
            color: #333;
        }

        /* --- Baris bawah: tanda tangan di kiri, status di kanan --- */
        .bawah {
            display: table;
            width: 100%;
            margin-top: 6px;
        }

        .ttd {
            display: table-cell;
            width: 50%;
            vertical-align: bottom;
        }

        .ttd-tabel {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .ttd .kotak {
            display: table-cell;
            text-align: center;
            padding: 0 4px;
        }

        .ttd .kotak p {
            margin: 0 0 10px;
        }

        .ttd .garis-ttd {
            border-bottom: 1.5px solid #000;
        }

        .status {
            display: table-cell;
            width: 50%;
            vertical-align: bottom;
            text-align: right;
            white-space: nowrap;
        }

        .no-print {
            margin-top: 16px;
            text-align: center;
        }

        .no-print button,
        .no-print a {
            display: inline-block;
            margin: 0 4px;
            padding: 8px 20px;
            border: 1px solid #2563eb;
            border-radius: 6px;
            background: #2563eb;
            color: #fff;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .no-print a {
            background: #fff;
            color: #2563eb;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="perusahaan">
            <p class="nama">{{ config('perusahaan.nama') }}</p>
            <p>{{ config('perusahaan.tagline') }}</p>
            <p>{{ config('perusahaan.alamat') }}</p>
            <p>HP: {{ config('perusahaan.telepon') }} &middot; {{ config('perusahaan.email') }}</p>
            <p>{{ config('perusahaan.bank') }}</p>
        </div>
        <div class="kepada">
            <table>
                <tr>
                    <td class="label">Kepada</td>
                    <td><strong>{{ $pesanan->toko->nama }}</strong></td>
                </tr>
                <tr>
                    <td></td>
                    <td>{{ $pesanan->toko->telepon ?? '—' }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td>{{ $pesanan->toko->alamatLengkap ?: '—' }}</td>
                </tr>
            </table>
        </div>
        <div class="faktur">
            <p class="judul">FAKTUR PENJUALAN</p>
            <table>
                <tr><td>No. Faktur</td><td>: {{ $pesanan->kode }}</td></tr>
                <tr><td>Tanggal</td><td>: {{ $pesanan->tanggal->format('d/m/Y') }}</td></tr>
                <tr><td>Sales</td><td>: {{ $pesanan->pembuat->name }}</td></tr>
                <tr><td>No. HP Sales</td><td>: {{ $pesanan->pembuat->no_hp ?? '—' }}</td></tr>
            </table>
        </div>
    </div>

    <p class="aset">{{ $pesanan->toko->asset_id ?? '—' }}</p>

    <table class="item">
        <colgroup>
            <col style="width:3%">
            <col style="width:41%">
            <col style="width:8%">
            <col style="width:8%">
            <col style="width:14%">
            <col style="width:8%">
            <col style="width:18%">
        </colgroup>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Barang</th>
                <th class="num">Qty</th>
                <th>Satuan</th>
                <th class="num">Harga Satuan</th>
                <th class="num">Disc %</th>
                <th class="num">Total Harga</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pesanan->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->produk->nama }}</td>
                    <td class="num">{{ number_format($item->jumlah_dus, 0, ',', '.') }}</td>
                    <td>DUS</td>
                    <td class="num">{{ number_format((float) $item->harga_satuan, 0, ',', '.') }}</td>
                    <td class="num">0</td>
                    <td class="num">{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            {{-- Satu-satunya garis di footer, memisahkan barang dari ringkasan. --}}
            <tr class="garis"><td colspan="7"></td></tr>
            <tr class="terbilang">
                <td colspan="2">Terbilang <em>{{ \App\Support\Terbilang::rupiah((float) $pesanan->total_nilai) }}</em></td>
                <td class="num"><strong>{{ number_format($pesanan->total_dus, 0, ',', '.') }}</strong></td>
                <td></td>
                <td colspan="2" class="label-kanan">Sub Total</td>
                <td class="num">: {{ number_format((float) $pesanan->total_nilai, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td colspan="2">Keterangan{{ $pesanan->catatan ? ": {$pesanan->catatan}" : '' }}</td>
                <td></td>
                <td></td>
                <td colspan="2" class="label-kanan">Diskon</td>
                <td class="num">: 0</td>
            </tr>
            <tr>
                <td colspan="2"></td>
                <td></td>
                <td></td>
                <td colspan="2" class="label-kanan"><strong>Total Invoice</strong></td>
                <td class="num"><strong>: {{ number_format((float) $pesanan->total_nilai, 0, ',', '.') }}</strong></td>
            </tr>
            <tr>
                <td colspan="4"></td>
                <td colspan="3" class="catatan-pajak">*Harga sudah termasuk pajak</td>
            </tr>
        </tfoot>
    </table>

    <div class="bawah">
        <div class="ttd">
            <div class="ttd-tabel">
                <div class="kotak">
                    <p>Fakturis,</p>
                    <div class="garis-ttd">&nbsp;</div>
                </div>
                <div class="kotak">
                    <p>Gudang,</p>
                    <div class="garis-ttd">&nbsp;</div>
                </div>
                <div class="kotak">
                    <p>Driver,</p>
                    <div class="garis-ttd">&nbsp;</div>
                </div>
                <div class="kotak">
                    <p>Pelanggan,</p>
                    <div class="garis-ttd">&nbsp;</div>
                </div>
            </div>
        </div>
        <div class="status">
            <p>Status : Belum Lunas</p>
            <p>{{ now()->format('d/m/Y, H:i') }}</p>
        </div>
    </div>

    {{-- Dua opsi saja, meniru menu cetak Accurate: Cetak (window.print, jadi
         terhubung ke driver printer lokal apa pun yang tersedia) dan Unduh PDF
         (berkas PDF sungguhan dari server, bukan hasil "print to PDF"
         browser). Tidak ada auto-print saat halaman dibuka — pengguna
         memilih sendiri salah satu tombol, sama seperti alur Accurate.
         Disembunyikan total (bukan cuma @media print) saat dirender Dompdf
         untuk Unduh PDF — Dompdf tidak memedulikan @media print maupun
         window.print(). --}}
    @unless ($untukPdf)
        @php
            // Tanda tangan sementara (5 menit) supaya OND Print Helper bisa
            // mengambil isi ESC/P tanpa sesi login sama sekali — link ini
            // hanya berlaku sekali proses cetak, bukan link permanen.
            $urlEscpUntukAgen = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'pesanan.nota.escp.signed',
                now()->addMinutes(5),
                ['pesanan' => $pesanan->id],
            );
            // Nama printer TIDAK dikirim dari sini — OND Print Helper memakai
            // pilihan yang sudah disimpan lokal di komputer masing-masing
            // (lihat PengaturanForm/PengaturanPrinter di sisi .exe), karena
            // nama printer yang sama bisa berbeda-beda per komputer Windows.
            $linkOndPrint = 'ondprint://print?url='.urlencode($urlEscpUntukAgen);
        @endphp

        <div class="no-print">
            <button type="button" onclick="window.print()">🖨 Cetak</button>
            <a href="{{ route('pesanan.nota.pdf', $pesanan) }}">⬇ Unduh PDF</a>
            {{-- <a href="{{ route('pesanan.nota.escp', $pesanan) }}">⬇ Unduh ESC/P (.prn)</a> --}}
            <a href="{{ $linkOndPrint }}">🖨 Print Direct</a>
        </div>

        {{-- <p class="no-print" style="font-size:11px;color:#666;max-width:22.4cm;margin:8px auto 0;">
            "Cetak Langsung ke Printer" butuh OND Print Helper terpasang di komputer Windows ini
            (sekali install, printernya dipilih sendiri saat pasang). Kalau belum terpasang, pakai
            "Unduh ESC/P" berikut ini untuk sementara:
        </p> --}}

        {{-- Opsi manual: teks mentah + kode printer, tanpa lewat rasterisasi
             browser sama sekali — untuk hasil setajam printer aslinya. Bukan
             untuk dibuka/di-print biasa; kirim apa adanya ke printer (lihat
             komentar di NotaPesananController::unduhEscp()). --}}
        {{-- <p class="no-print" style="font-size:11px;color:#666;max-width:22.4cm;margin:8px auto 0;">
            "Cetak Langsung (ESC/P)" mengunduh berkas .prn — jangan dibuka lewat dialog cetak biasa,
            kirim apa adanya ke printer (mis. lewat antrean "Generic / Text Only" di port yang sama,
            atau <code>copy /b nama-berkas.prn USB001</code> dari Command Prompt).
        </p> --}}
    @endunless
</body>
</html>
