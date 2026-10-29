<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 8mm; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #2D3142; margin: 0; padding: 0; }
    .grid { width: 100%; border-collapse: collapse; }
    .card-cell { width: 50%; height: 68mm; padding: 4mm; vertical-align: top; }
    .card-border { border: 2px dashed #9E9E9E; border-radius: 8px; height: 60mm; text-align: center; padding: 6px; background-color: #FAFAFA; }
    .icon-container { height: 30mm; margin-top: 1mm; }
    .card-icon { max-height: 28mm; max-width: 28mm; }
    .label-en { font-size: 14pt; font-weight: bold; letter-spacing: 1px; color: #1F2421; margin-top: 2mm; text-transform: uppercase; }
    .label-ur { font-size: 16pt; font-weight: bold; color: #E07A5F; direction: rtl; margin-top: 1mm; }
    .ur-img { height: 9mm; }
    .cut-guide { font-size: 7pt; color: #BDBDBD; margin-bottom: 2px; }
</style>
</head>
<body>
    <div style="text-align:center; margin-bottom: 5px;">
        <img src="{{ public_path('brand/mark-96.png') }}" style="width: 7mm; height: 7mm; vertical-align: middle; margin-right: 2mm;" alt="">
        <span style="font-size: 10pt; color: #757575; vertical-align: middle;"><b style="color:#120c15;">Kindo</b> · Visual Communication System (PECS) — Cut along dashed lines &amp; laminate</span>
    </div>
    <table class="grid">
        @foreach($cards->chunk(2) as $row)
        <tr>
            @foreach($row as $card)
            <td class="card-cell">
                <div class="card-border">
                    <div class="cut-guide">&#9986; CUT HERE</div>
                    <div class="icon-container">
                        @if(is_file(public_path($card->icon_path)))
                        <img src="{{ public_path($card->icon_path) }}" class="card-icon" alt="">
                        @endif
                    </div>
                    <div class="label-en">{{ $card->english_label }}</div>
                    <div class="label-ur">
                        @if($ur = \App\Support\UrduText::imageDataUri($card->urdu_label, 96, '#E07A5F'))
                        <img src="{{ $ur }}" class="ur-img" alt="{{ $card->urdu_label }}">
                        @else
                        {{ $card->urdu_label }}
                        @endif
                    </div>
                </div>
            </td>
            @endforeach
            @if($row->count() < 2)
            <td class="card-cell"></td>
            @endif
        </tr>
        @endforeach
    </table>
    <div style="position: fixed; bottom: -8mm; left: 0; right: 0; text-align: center; font-size: 7pt; color: #9a96a3;">Kindo · learning that fits every child</div>
</body>
</html>
