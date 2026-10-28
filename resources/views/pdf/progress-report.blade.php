<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 14mm; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #120c15; font-size: 10pt; }
    h1 { font-size: 20pt; margin: 0 0 2mm; }
    h2 { font-size: 12pt; margin: 8mm 0 2mm; border-bottom: 1px solid #e7e3ef; padding-bottom: 1mm; }
    .muted { color: #6f6a78; }
    .tiles { width: 100%; border-collapse: separate; border-spacing: 3mm 0; margin: 4mm -3mm 0; }
    .tile { background: #f3f0fa; padding: 4mm; text-align: center; width: 25%; }
    .tile b { display: block; font-size: 18pt; }
    table.t { width: 100%; border-collapse: collapse; }
    table.t th { text-align: left; font-size: 8.5pt; color: #6f6a78; padding: 1.5mm 2mm; border-bottom: 1px solid #e7e3ef; }
    table.t td { padding: 1.5mm 2mm; border-bottom: 1px solid #f0edf6; vertical-align: middle; }
    .name { height: 9mm; }
    .glyph { height: 7mm; }
    .foot { margin-top: 10mm; font-size: 8pt; color: #6f6a78; }
</style>
</head>
<body>
@php($p = $s['practice'])
    <h1>{{ $ur($child->name, 80, '#120c15', 'name') }} — progress report</h1>
    <div class="muted">{{ $from->format('j M Y') }} to {{ $to->format('j M Y') }}</div>

    <table class="tiles"><tr>
        <td class="tile"><b>{{ $p['minutes'] }}</b>minutes practised</td>
        <td class="tile"><b>{{ $p['sessions'] }}</b>sessions</td>
        <td class="tile"><b>{{ $p['accuracy_pct'] === null ? '—' : $p['accuracy_pct'].'%' }}</b>accuracy ({{ $p['attempts'] }} tries)</td>
        <td class="tile"><b>{{ $letters['mastered'] }}</b>letters mastered</td>
    </tr></table>

    <h2>Letter by letter</h2>
    @if(count($letters['letters']))
    <table class="t">
        <tr><th>Letter</th><th>Right</th><th>Tries</th><th>Guide used</th><th>Trend</th><th>Status</th></tr>
        @foreach(array_slice($letters['letters'], 0, 20) as $l)
        <tr>
            <td>{{ $ur($l['letter']) }}</td>
            <td>{{ $l['accuracy_pct'] ?? '—' }}%</td><td>{{ $l['attempts'] }}</td><td>{{ $l['assisted_pct'] }}%</td>
            <td>{{ ['up' => 'improving', 'down' => 'slipping', 'steady' => 'steady'][$l['trend']] ?? '—' }}</td>
            <td>{{ ['mastered' => 'Got it', 'practising' => 'Getting there', 'needs_help' => 'Needs help', 'new' => 'New'][$l['status']] }}</td>
        </tr>
        @endforeach
    </table>
    @else
    <p class="muted">No tracing results in this period.</p>
    @endif

    <h2>Most missed</h2>
    @if(count($p['hardest_targets']))
        @foreach($p['hardest_targets'] as $target => $n)
            {{ $ur((string) $target) }} ({{ $n }}) &nbsp;
        @endforeach
    @else
    <p class="muted">No mistakes recorded.</p>
    @endif

    <h2>Alerts</h2>
    <p>{{ $s['needs']['total'] }} in this period, {{ $s['needs']['answered'] }} answered{{ $s['needs']['avg_reply_seconds'] === null ? '' : ', average reply '.(int) floor($s['needs']['avg_reply_seconds'] / 60).' min '.($s['needs']['avg_reply_seconds'] % 60).' s' }}.</p>

    @if(count($milestones))
    <h2>Milestones</h2>
    <ul>@foreach($milestones as $m)<li>{{ $m['title']['en'] }}&nbsp;<span class="muted">{{ $m['earned_on'] ? '('.$m['earned_on'].')' : '' }}</span></li>@endforeach</ul>
    @endif

    <div class="foot">Made with Kindo. This report shows practice results only; it is not a medical assessment.</div>
</body>
</html>
