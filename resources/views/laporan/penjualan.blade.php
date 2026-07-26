<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan</title>
    <style>
        @page { margin: 22px; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { margin: 0 0 3px; text-align: center; font-size: 17px; }
        .periode { margin-bottom: 14px; color: #64748b; text-align: center; }
        .summary { width: 100%; margin-bottom: 13px; border-collapse: separate; border-spacing: 5px 0; }
        .summary td { padding: 8px; border: 1px solid #dbe4ef; background: #f8fafc; }
        .summary span { display: block; color: #64748b; font-size: 7px; }
        .summary strong { display: block; margin-top: 2px; font-size: 10px; }
        .data { width: 100%; border-collapse: collapse; }
        .data th { padding: 6px 4px; border: 1px solid #cbd5e1; background: #246bfd; color: white; }
        .data td { padding: 5px 4px; border: 1px solid #dbe4ef; vertical-align: top; }
        .right { text-align: right; }
        .muted { color: #64748b; font-size: 7px; }
    </style>
</head>
<body>
    <h1>LAPORAN PENJUALAN</h1>
    <div class="periode">Periode: {{ $tanggalAwal ?: 'Semua' }} s.d. {{ $tanggalAkhir ?: 'Semua' }}</div>
    <table class="summary">
        <tr>
            <td><span>TRANSAKSI</span><strong>{{ number_format($ringkasan['jumlah_transaksi'], 0, ',', '.') }}</strong></td>
            <td><span>PENJUALAN BERSIH</span><strong>Rp {{ number_format($ringkasan['penjualan_bersih'], 0, ',', '.') }}</strong></td>
            <td><span>RETUR</span><strong>Rp {{ number_format($ringkasan['retur'], 0, ',', '.') }}</strong></td>
            <td><span>HPP BERSIH</span><strong>Rp {{ number_format($ringkasan['hpp'], 0, ',', '.') }}</strong></td>
            <td><span>LABA KOTOR</span><strong>Rp {{ number_format($ringkasan['laba_kotor'], 0, ',', '.') }}</strong></td>
            <td><span>PIUTANG</span><strong>Rp {{ number_format($ringkasan['piutang'], 0, ',', '.') }}</strong></td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th>Nota / Tanggal</th><th>Kasir</th><th>Setoran / Penyetor</th><th>Pelanggan</th>
                <th>Bayar</th><th>Item</th><th>Total</th><th>Retur</th><th>Bersih</th><th>HPP</th><th>Laba</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($items as $item)
            @php
                $retur = (float) ($item['total_retur'] ?? 0);
                $bersih = (float) $item['grandtotal'] - $retur;
                $hppBersih = (float) $item['hpp'] - (float) ($item['hpp_retur'] ?? 0);
            @endphp
            <tr>
                <td><strong>{{ $item['nomortransaksi'] }}</strong><div class="muted">{{ $item['tanggal'] }}</div></td>
                <td>{{ $item['pengguna']['name'] ?? '-' }}</td>
                <td>{{ $item['setoran_bendahara']['nomor_setoran'] ?? 'Belum disetor' }}<div class="muted">{{ $item['setoran_bendahara']['kasir']['name'] ?? '-' }}</div></td>
                <td>{{ $item['pelanggan']['nama'] ?? 'Umum' }}</td>
                <td>{{ $item['cara_bayar'] }}</td>
                <td class="right">{{ number_format($item['jumlahitem'], 2, ',', '.') }}</td>
                <td class="right">Rp {{ number_format($item['grandtotal'], 0, ',', '.') }}</td>
                <td class="right">Rp {{ number_format($retur, 0, ',', '.') }}</td>
                <td class="right">Rp {{ number_format($bersih, 0, ',', '.') }}</td>
                <td class="right">Rp {{ number_format($hppBersih, 0, ',', '.') }}</td>
                <td class="right">Rp {{ number_format($bersih - $hppBersih, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="11" style="text-align:center">Tidak ada transaksi sesuai filter.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
