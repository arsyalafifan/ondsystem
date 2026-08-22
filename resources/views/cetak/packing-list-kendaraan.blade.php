<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Packing List {{ $kendaraan->batch->kode }} - {{ $kendaraan->nama }}</title>
    <style>
        {{-- Ukuran dan alasan sama persis dengan cetak/nota-pesanan.blade.php:
             kertas continuous form fisiknya 9.5x11 inci, portrait wajib supaya
             driver printer tidak salah merotasi hasil cetak. --}}
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

        body {
            font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;
            font-size: 13.5px;
            font-weight: 500;
            line-height: 1.4;
            color: #000;
            width: 22.4cm;
        }

        {{-- display:table di seluruh berkas ini, bukan flex — satu template
             yang sama dirender identik oleh Dompdf (Unduh PDF) maupun
             browser (Cetak). Dompdf tidak mendukung flexbox. --}}
        .header {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }

        .perusahaan {
            display: table-cell;
            vertical-align: top;
        }

        .perusahaan .nama {
            font-size: 1.3em;
            font-weight: bold;
            margin: 0 0 4px;
        }

        .info {
            display: table-cell;
            vertical-align: top;
            text-align: right;
        }

        .info table {
            margin-left: auto;
            border: none;
            border-collapse: collapse;
        }

        .info td {
            border: none;
            padding: 0 0 1px 6px;
            text-align: left;
        }

        .info td.label {
            white-space: nowrap;
        }

        table.daftar {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 22px;
        }

        table.daftar th, table.daftar td {
            border: none;
            padding: 2px 4px;
        }

        table.daftar th {
            text-align: left;
            font-weight: 700;
            border-top: 1.5px solid #000;
            border-bottom: 1.5px solid #000;
        }

        table.daftar td.num, table.daftar th.num {
            text-align: right;
        }

        table.daftar tfoot .garis td {
            border-top: 1.5px solid #000;
            padding: 0;
        }

        table.daftar tfoot td {
            padding: 2px 4px;
            font-weight: 700;
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
    @php
        $stops = $kendaraan->stops_packing;
        $produk = $kendaraan->ringkasan_produk_packing;
    @endphp

    <div class="header">
        <div class="perusahaan">
            <p class="nama">{{ config('perusahaan.nama') }}</p>
        </div>
        <div class="info">
            <table>
                <tr><td class="label">Nama Mobil</td><td>: {{ $kendaraan->nama }}</td></tr>
                <tr><td class="label">Jumlah Faktur</td><td>: {{ $stops->count() }}</td></tr>
                <tr><td class="label">Jumlah Dus</td><td>: {{ $stops->sum('total_dus') }}</td></tr>
                <tr><td class="label">Tanggal Berangkat</td><td>: {{ $kendaraan->batch->tanggal->format('d/m/Y') }}</td></tr>
            </table>
        </div>
    </div>

    <table class="daftar">
        <colgroup>
            <col style="width:40%">
            <col style="width:20%">
            <col style="width:20%">
            <col style="width:20%">
        </colgroup>
        <thead>
            <tr>
                <th>Nama Barang</th>
                <th class="num">Qty</th>
                <th class="num">Dus Terjual</th>
                <th class="num">Dus Pulang</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($produk as $baris)
                <tr>
                    <td>{{ $baris['produk'] }}</td>
                    <td class="num">{{ $baris['qty'] }}</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="garis"><td colspan="4"></td></tr>
            <tr>
                <td>Total</td>
                <td class="num">{{ $produk->sum('qty') }}</td>
                <td></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <table class="daftar">
        <colgroup>
            <col style="width:40%">
            <col style="width:20%">
            <col style="width:20%">
            <col style="width:20%">
        </colgroup>
        <thead>
            <tr>
                <th>Nama Toko</th>
                <th class="num">Qty</th>
                <th class="num">Dus Terjual</th>
                <th class="num">Dus Pulang</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($stops as $stop)
                <tr>
                    <td>{{ $stop->toko->nama }}</td>
                    <td class="num">{{ $stop->total_dus }}</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="garis"><td colspan="4"></td></tr>
            <tr>
                <td>Total</td>
                <td class="num">{{ $stops->sum('total_dus') }}</td>
                <td></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    {{-- Tiga opsi, persis pola cetak/nota-pesanan.blade.php: Cetak
         (window.print), Unduh PDF (berkas PDF sungguhan dari server lewat
         Dompdf), dan Print Direct (ESC/P mentah lewat OND Print Helper).
         Disembunyikan total saat dirender Dompdf — Dompdf tidak memedulikan
         @media print maupun window.print(). --}}
    @unless ($untukPdf)
        @php
            $urlEscpUntukAgen = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'routing.packing-list.escp.signed',
                now()->addMinutes(5),
                ['kendaraan' => $kendaraan->id],
            );
            $linkOndPrint = 'ondprint://print?url='.urlencode($urlEscpUntukAgen);
        @endphp

        <div class="no-print">
            <button type="button" onclick="window.print()">🖨 Cetak</button>
            <a href="{{ route('routing.packing-list.pdf', $kendaraan) }}">⬇ Unduh PDF</a>
            <a href="{{ $linkOndPrint }}">🖨 Print Direct</a>
        </div>
    @endunless
</body>
</html>
