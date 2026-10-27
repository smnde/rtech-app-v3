<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Barang Lokal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #334155; font-size: 13px; line-height: 1.5; }
        .title { font-size: 16px; font-weight: bold; color: #0f172a; margin-bottom: 2px; }
        .periode { font-size: 11px; font-style: italic; color: #64748b; margin-bottom: 10px; }
        table.b { width: 100%; border-collapse: collapse; font-size: 12px; }
        table.b th { background: #1e293b; color: #fff; border: 1px solid #1e293b; padding: 8px 7px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
        table.b td { border: 1px solid #e2e8f0; padding: 6px 7px; vertical-align: top; }
        table.b td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        table.b td.no { text-align: center; color: #64748b; width: 5%; }
        table.b td.qty { text-align: center; font-weight: bold; width: 6%; }
        table.b td.nm { width: 34%; font-weight: 500; color: #0f172a; }
        table.b td.sp { font-size: 11px; color: #475569; width: 35%; }
        table.b td.pr { text-align: right; width: 14%; white-space: nowrap; }
        table.b td.st { width: 14%; text-align: center; }
        table.b tr:nth-child(even) td { background: #f8fafc; }
        table.b tr.sum td { background: #047857; color: #fff; font-weight: bold; border-top: 2px solid #047857; }
        table.b tr.sum td.num { font-size: 13px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 9px; font-size: 10px; font-weight: bold; }
        .kosong { text-align: center; padding: 40px; color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>

    @if($empty)
        <div class="kosong">Tidak ada barang.</div>
    @else
    @php
        $showKendala = $show_kendala ?? false;
        $badgeColors = [
            'READY STOCK' => ['#D1FAE5', '#047857'],
            'SIAP JUAL'   => ['#D1FAE5', '#047857'],
            'PENDING'     => ['#FEF3C7', '#B45309'],
            'ON PROCESS'  => ['#DBEAFE', '#0369A1'],
            'ON PROSES'   => ['#DBEAFE', '#0369A1'],
            'KENDALA'     => ['#FEE2E2', '#B91C1C'],
            'NUNGGU'      => ['#FEF9C3', '#A16207'],
            'DISPLAY'     => ['#DBEAFE', '#1D4ED8'],
            'HABIS'       => ['#F1F5F9', '#64748B'],
        ];
    @endphp
    <div class="title">List Item {{ $status_label }} (Barang Lokal)</div>
    @if(!empty($periode_label))
    <div class="periode">{{ $periode_label }}</div>
    @endif
    <table class="b">
        <thead>
            <tr>
                <th style="width:5%">No</th>
                <th style="width:{{ $showKendala ? '22' : '34' }}%">Nama</th>
                <th style="width:6%">Qty</th>
                <th style="width:{{ $showKendala ? '23' : '35' }}%">Spesifikasi</th>
                <th style="width:14%">Harga Jual</th>
                <th style="width:14%">Status</th>
                @if($showKendala)
                <th style="width:30%">Kendala</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td class="no">{{ $item['no'] }}</td>
                <td class="nm">{{ $item['name'] }}</td>
                <td class="qty">{{ $item['qty'] }}</td>
                <td class="sp">{{ $item['spec'] }}</td>
                <td class="pr">Rp {{ number_format($item['price'], 0, ',', '.') }}</td>
                <td class="st">
                    @if(!empty($item['status']) && isset($badgeColors[$item['status']]))
                        <span class="badge" style="background:{{ $badgeColors[$item['status']][0] }};color:{{ $badgeColors[$item['status']][1] }}">{{ $item['status'] }}</span>
                    @else
                        -
                    @endif
                </td>
                @if($showKendala)
                <td class="sp">{{ $item['kendala'] }}</td>
                @endif
            </tr>
            @endforeach
            <tr class="sum">
                <td colspan="2">TOTAL &middot; {{ count($items) }} item</td>
                <td class="num">{{ $total_unit }}</td>
                <td colspan="{{ $showKendala ? 4 : 3 }}"></td>
            </tr>
        </tbody>
    </table>
    @endif

</body>
</html>
