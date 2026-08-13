<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Nota {{ $pesanan->kode }}</title>
    <style>
        @page {
            size: 24cm {{ $ukuran === 'kecil' ? '14cm' : '28cm' }};
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
            font-family: 'Courier New', Courier, monospace;
            font-size: {{ $ukuran === 'kecil' ? '9px' : '10.5px' }};
            line-height: 1.35;
            color: #000;
            width: 22.4cm;
        }

        /* --- Kop: perusahaan | kepada | faktur, sebaris seperti aslinya --- */
        .header {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 4px;
        }

        .perusahaan {
            flex: 1.2;
        }

        .perusahaan p {
            margin: 1px 0;
        }

        .perusahaan .nama {
            font-size: 1.3em;
            font-weight: bold;
        }

        .kepada {
            flex: 1;
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
            flex: 0 0 auto;
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
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        table.item td.num, table.item th.num {
            text-align: right;
        }

        table.item tfoot .garis td {
            border-top: 1px solid #000;
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
            font-size: 0.85em;
            font-style: italic;
            color: #333;
        }

        /* --- Baris bawah: tanda tangan di kiri, status di kanan --- */
        .bawah {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 10px;
            margin-top: 6px;
        }

        .ttd {
            display: flex;
            flex: 0 0 50%;
            max-width: 50%;
            gap: 8px;
        }

        .ttd .kotak {
            flex: 1;
            text-align: center;
        }

        .ttd .kotak p {
            margin: 0 0 10px;
        }

        .ttd .garis-ttd {
            border-bottom: 1px solid #000;
        }

        .status {
            flex: 0 0 auto;
            text-align: right;
            white-space: nowrap;
        }

        .no-print {
            margin-top: 8px;
            text-align: center;
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
                <tr><td>Sales</td><td>: {{ $pencetak->name }}</td></tr>
                <tr><td>No. HP Sales</td><td>: {{ $pencetak->no_hp ?? '—' }}</td></tr>
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
        <div class="status">
            <p>Status : Belum Lunas</p>
            <p>{{ now()->format('d/m/Y, H:i') }}</p>
        </div>
    </div>

    <div class="no-print">
        <button type="button" onclick="window.print()">Cetak</button>
    </div>

    <script>
        window.addEventListener('load', function () {
            window.print();
        }, { once: true });
    </script>
</body>
</html>
