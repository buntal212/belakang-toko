<?php

namespace App\Http\Controllers\Api\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Penjualan\Penjualan;
use App\Models\Penjualan\ReturPenjualan;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PenjualanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggal_awal' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_akhir' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_awal'],
            'kasir_id' => ['nullable', 'integer', 'exists:users,id'],
            'cara_bayar' => ['nullable', 'in:CASH,DEBIT,QRIS,HUTANG'],
        ]);

        $tanggalAwal = isset($data['tanggal_awal'])
            ? Carbon::createFromFormat('Y-m-d', $data['tanggal_awal'])->startOfDay()
            : null;
        $tanggalAkhir = isset($data['tanggal_akhir'])
            ? Carbon::createFromFormat('Y-m-d', $data['tanggal_akhir'])->endOfDay()
            : null;

        $filter = function (Builder $query) use ($data, $tanggalAwal, $tanggalAkhir): void {
            $query->whereIn('status', ['SELESAI', 'HUTANG'])
                ->when($tanggalAwal, fn (Builder $builder) => $builder->where('tanggal', '>=', $tanggalAwal))
                ->when($tanggalAkhir, fn (Builder $builder) => $builder->where('tanggal', '<=', $tanggalAkhir))
                ->when($data['kasir_id'] ?? null, fn (Builder $builder, $id) => $builder->where('created_by', $id))
                ->when($data['cara_bayar'] ?? null, fn (Builder $builder, $caraBayar) =>
                    $builder->where('cara_bayar', $caraBayar)
                )
                ->when($data['search'] ?? null, function (Builder $builder, string $search): void {
                    $builder->where(function (Builder $query) use ($search): void {
                        $query->where('nomortransaksi', 'like', "%{$search}%")
                            ->orWhereHas('pelanggan', fn (Builder $pelanggan) =>
                                $pelanggan->where('nama', 'like', "%{$search}%")
                            );
                    });
                });
        };

        $query = Penjualan::query();
        $filter($query);

        $ringkasan = (clone $query)
            ->selectRaw('COUNT(*) as jumlah_transaksi')
            ->selectRaw('COALESCE(SUM(jumlahitem), 0) as jumlah_item')
            ->selectRaw('COALESCE(SUM(grandtotal), 0) as omzet')
            ->selectRaw('COALESCE(SUM(hpp), 0) as hpp')
            ->selectRaw('COALESCE(SUM(grandtotal - hpp), 0) as laba_kotor')
            ->selectRaw('COALESCE(SUM(sisa_hutang), 0) as piutang')
            ->first();

        $penjualanIds = (clone $query)->select('id');
        $totalRetur = ReturPenjualan::query()
            ->where('status', 'SELESAI')
            ->whereIn('penjualan_id', $penjualanIds)
            ->sum('total');
        $totalHppRetur = DB::table('tretur_penjualan_rinci as rr')
            ->join('tretur_penjualan as r', 'r.id', '=', 'rr.retur_penjualan_id')
            ->join('tpenjualan_rinci as pr', 'pr.id', '=', 'rr.penjualan_rinci_id')
            ->where('r.status', 'SELESAI')
            ->whereIn('pr.penjualan_id', (clone $query)->select('id'))
            ->sum(DB::raw('CASE
                WHEN pr.qty_kecil > 0 THEN (rr.qty_kecil / pr.qty_kecil) * pr.hpp
                ELSE 0
            END'));

        $items = $query
            ->addSelect([
                'hpp_retur' => DB::table('tretur_penjualan_rinci as rr')
                    ->join('tretur_penjualan as r', 'r.id', '=', 'rr.retur_penjualan_id')
                    ->join('tpenjualan_rinci as pr', 'pr.id', '=', 'rr.penjualan_rinci_id')
                    ->selectRaw('COALESCE(SUM(CASE
                        WHEN pr.qty_kecil > 0 THEN (rr.qty_kecil / pr.qty_kecil) * pr.hpp
                        ELSE 0
                    END), 0)')
                    ->where('r.status', 'SELESAI')
                    ->whereColumn('pr.penjualan_id', 'tpenjualan.id'),
            ])
            ->with([
                'pengguna:id,name,username',
                'pelanggan:id,nama',
                'setoranBendahara:id,nomor_setoran,kasir_id',
                'setoranBendahara.kasir:id,name,username',
                'rincian:id,penjualan_id,barang_id,qty,satuan,harga,diskon,subtotal,hpp',
                'rincian.barang:id,kodebarang,namabarang',
            ])
            ->withSum([
                'retur as total_retur' => fn (Builder $retur) => $retur->where('status', 'SELESAI'),
            ], 'total')
            ->latest('tanggal')
            ->latest('id')
            ->get();

        return new JsonResponse([
            'data' => $items,
            'total' => $items->count(),
            'ringkasan' => [
                'jumlah_transaksi' => (int) $ringkasan->jumlah_transaksi,
                'jumlah_item' => (float) $ringkasan->jumlah_item,
                'omzet' => (float) $ringkasan->omzet,
                'retur' => (float) $totalRetur,
                'penjualan_bersih' => (float) $ringkasan->omzet - (float) $totalRetur,
                'hpp' => (float) $ringkasan->hpp - (float) $totalHppRetur,
                'laba_kotor' => ((float) $ringkasan->omzet - (float) $totalRetur)
                    - ((float) $ringkasan->hpp - (float) $totalHppRetur),
                'piutang' => (float) $ringkasan->piutang,
            ],
        ]);
    }

    public function excel(Request $request)
    {
        $laporan = $this->index($request)->getData(true);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Penjualan');
        $sheet->setCellValue('A1', 'LAPORAN PENJUALAN');
        $sheet->mergeCells('A1:M1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $periode = ($request->tanggal_awal ?: 'Semua').' s.d. '.($request->tanggal_akhir ?: 'Semua');
        $sheet->setCellValue('A2', 'Periode: '.$periode);
        $sheet->mergeCells('A2:M2');

        $ringkasan = $laporan['ringkasan'];
        $summary = [
            ['Jumlah Transaksi', $ringkasan['jumlah_transaksi']],
            ['Penjualan Bersih', $ringkasan['penjualan_bersih']],
            ['Retur', $ringkasan['retur']],
            ['HPP Bersih', $ringkasan['hpp']],
            ['Laba Kotor', $ringkasan['laba_kotor']],
            ['Sisa Piutang', $ringkasan['piutang']],
        ];
        foreach ($summary as $index => [$label, $value]) {
            $row = 4 + $index;
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
        }
        $sheet->getStyle('B5:B9')->getNumberFormat()->setFormatCode('"Rp" #,##0');

        $headerRow = 11;
        $headers = ['Nota', 'Tanggal', 'Kasir', 'No. Setoran', 'Kasir Penyetor', 'Pelanggan', 'Pembayaran', 'Item', 'Total', 'Retur', 'Bersih', 'HPP Bersih', 'Laba Kotor'];
        foreach ($headers as $column => $header) {
            $sheet->setCellValue([$column + 1, $headerRow], $header);
        }
        $sheet->getStyle("A{$headerRow}:M{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '246BFD']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = $headerRow + 1;
        foreach ($laporan['data'] as $item) {
            $retur = (float) ($item['total_retur'] ?? 0);
            $hppBersih = (float) $item['hpp'] - (float) ($item['hpp_retur'] ?? 0);
            $bersih = (float) $item['grandtotal'] - $retur;
            $values = [
                $item['nomortransaksi'],
                $item['tanggal'],
                $item['pengguna']['name'] ?? '-',
                $item['setoran_bendahara']['nomor_setoran'] ?? 'Belum disetor',
                $item['setoran_bendahara']['kasir']['name'] ?? '-',
                $item['pelanggan']['nama'] ?? 'Umum',
                $item['cara_bayar'],
                (float) $item['jumlahitem'],
                (float) $item['grandtotal'],
                $retur,
                $bersih,
                $hppBersih,
                $bersih - $hppBersih,
            ];
            foreach ($values as $column => $value) {
                $sheet->setCellValue([$column + 1, $row], $value);
            }
            $row++;
        }

        if ($row > $headerRow + 1) {
            $sheet->getStyle('I'.($headerRow + 1).':M'.($row - 1))
                ->getNumberFormat()->setFormatCode('"Rp" #,##0');
        }
        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A12');
        $sheet->setAutoFilter("A{$headerRow}:M{$headerRow}");

        $filename = 'laporan-penjualan-'.now()->format('Ymd-His').'.xlsx';
        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function pdf(Request $request)
    {
        $laporan = $this->index($request)->getData(true);
        return Pdf::loadView('laporan.penjualan', [
            'items' => $laporan['data'],
            'ringkasan' => $laporan['ringkasan'],
            'tanggalAwal' => $request->tanggal_awal,
            'tanggalAkhir' => $request->tanggal_akhir,
        ])
            ->setPaper('a4', 'landscape')
            ->download('laporan-penjualan-'.now()->format('Ymd-His').'.pdf');
    }
}
