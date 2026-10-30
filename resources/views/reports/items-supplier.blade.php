<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Barang Supplier</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #334155; font-size: 12px; line-height: 1.5; }
        .title { font-size: 16px; font-weight: bold; color: #0f172a; margin-bottom: 2px; }
        .meta { font-size: 10px; color: #64748b; margin-bottom: 12px; }
        .group-head { background: #1e293b; color: #fff; font-weight: bold; font-size: 11px; padding: 6px 8px; border: 1px solid #1e293b; margin-top: 14px; }
        .item-head { background: #e0e7ff; color: #1e293b; font-weight: bold; font-size: 11px; padding: 5px 8px; border: 1px solid #c7d2fe; border-bottom: none; }
        .item-spec { font-style: italic; font-size: 9px; color: #64748b; padding: 3px 8px; border: 1px solid #c7d2fe; border-bottom: none; }
        .item-price { font-weight: bold; font-size: 10px; color: #1e293b; padding: 3px 8px; border: 1px solid #c7d2fe; border-bottom: none; }
        table.b { width: 100%; border-collapse: collapse; font-size: 11px; }
        table.b th { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 5px 7px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.3px; }
        table.b td { border: 1px solid #e2e8f0; padding: 4px 7px; vertical-align: top; }
        table.b td.ket { width: 16%; font-weight: 500; }
        table.b td.tgl { width: 24%; }
        table.b td.qty { text-align: center; font-variant-numeric: tabular-nums; width: 10%; }
        /* Masuk = hijau (tgl + ket + qty), Laku = merah */
        table.b tr.masuk td { color: #047857; font-weight: bold; background: #ecfdf5; }
        table.b tr.keluar td { color: #b91c1c; background: #fef2f2; }
        table.b tr.kendala td { background: #fef9c3; color: #a16207; font-weight: 600; }
        table.b tr.sisa td { background: #f1f5f9; color: #1e293b; font-weight: bold; }
        table.b tr.stok td { background: #047857; color: #fff; font-weight: bold; font-size: 12px; }
        .kosong { text-align: center; padding: 40px; color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>

    @if($empty)
        <div class="kosong">Tidak ada barang supplier.</div>
    @else
    <div class="title">Laporan Barang Supplier (Laptop)</div>
    <div class="meta">
        Dicetak: {{ now('Asia/Jakarta')->format('d M Y, H:i') }}
        @if(!empty($periode_label))<br/>{{ $periode_label }}@endif
        @if(!empty($stock_label))<br/>{{ $stock_label }}@endif
    </div>

    @foreach($groups as $g)
        <div class="group-head">KODE SUPPLIER: {{ $g['code'] }} &middot; SUPPLIER: {{ $g['supplier_name'] }}</div>

        @foreach($g['items'] as $item)
            <div class="item-head">{{ $item['no'] }}. {{ $item['name'] }}</div>
            <div class="item-spec">{{ $item['spec'] }}</div>
            <div class="item-price">Harga Jual: Rp {{ number_format($item['sell_price'], 0, ',', '.') }}</div>
            <table class="b">
                <thead>
                    <tr>
                        <th style="width:24%">Tanggal</th>
                        <th style="width:16%">Keterangan</th>
                        <th style="width:10%">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($item['batches'] as $batch)
                        <tr class="masuk">
                            <td class="tgl">{{ $batch['tgl_masuk'] }}</td>
                            <td class="ket">Masuk</td>
                            <td class="qty">{{ $batch['qty'] }}</td>
                        </tr>
                        @forelse($batch['outs'] as $o)
                            <tr class="keluar">
                                <td class="tgl">{{ $o['tgl_keluar'] }}</td>
                                <td class="ket">Laku</td>
                                <td class="qty">{{ $o['qty'] }}</td>
                            </tr>
                        @empty
                            <tr class="keluar">
                                <td colspan="3" style="color:#94a3b8;font-style:italic;">— belum laku —</td>
                            </tr>
                        @endforelse
                        @if(count($item['batches']) > 1)
                            <tr class="sisa">
                                <td colspan="2">Sisa batch</td>
                                <td class="qty">{{ $batch['remaining'] }}</td>
                            </tr>
                        @endif
                    @endforeach
                    @if($item['kendala'] !== '-')
                    <tr class="kendala">
                        <td colspan="3">Kendala: {{ $item['kendala'] }}</td>
                    </tr>
                    @endif
                    <tr class="stok">
                        <td colspan="2">STOK SAAT INI</td>
                        <td class="qty">{{ $item['stock'] }}</td>
                    </tr>
                </tbody>
            </table>
        @endforeach
    @endforeach
    @endif

</body>
</html>
