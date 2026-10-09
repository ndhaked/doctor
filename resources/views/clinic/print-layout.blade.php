<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title')</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: #111827; font: 14px/1.5 -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        .toolbar { max-width: 800px; margin: 16px auto 0; display: flex; justify-content: flex-end; gap: 8px; padding: 0 12px; }
        .toolbar button, .toolbar a { background: #4f46e5; color: #fff; border: 0; border-radius: 8px; padding: 8px 16px; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .toolbar a { background: #fff; color: #374151; border: 1px solid #d1d5db; }
        .sheet { max-width: 800px; min-height: 1000px; margin: 12px auto 24px; background: #fff; padding: 36px 40px; box-shadow: 0 1px 3px rgba(0,0,0,.12); position: relative; }
        .head { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #0f766e; padding-bottom: 12px; }
        .head h1 { margin: 0; font-size: 22px; color: #0f766e; }
        .head p { margin: 2px 0; color: #4b5563; font-size: 13px; }
        .right { text-align: right; }
        .meta { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 24px; margin: 14px 0; padding: 10px 12px; background: #f9fafb; border-radius: 6px; font-size: 13px; }
        h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; margin: 18px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 7px 8px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; color: #6b7280; background: #f9fafb; }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; }
        .pre { white-space: pre-line; }
        .totals { margin-left: auto; width: 260px; margin-top: 10px; }
        .totals div { display: flex; justify-content: space-between; padding: 3px 0; }
        .totals .big { font-size: 16px; font-weight: 700; border-top: 1px solid #111827; margin-top: 4px; padding-top: 6px; }
        .sign { margin-top: 60px; text-align: right; }
        .sign img { max-height: 70px; max-width: 220px; }
        .sign .line { display: inline-block; min-width: 200px; border-top: 1px solid #9ca3af; padding-top: 4px; font-size: 13px; }
        .foot { position: absolute; left: 40px; right: 40px; bottom: 24px; text-align: center; color: #9ca3af; font-size: 12px; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; background: #dcfce7; color: #166534; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; padding: 0; max-width: none; min-height: 0; }
            .foot { position: static; margin-top: 30px; }
            @page { margin: 14mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="javascript:window.close()">Close</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <div class="sheet">
        <div class="head">
            <div>
                <h1>{{ $doctor->clinic_name ?: $doctor->name }}</h1>
                @if ($doctor->clinic_address) <p class="pre">{{ $doctor->clinic_address }}</p> @endif
                @if ($doctor->phone || $doctor->email) <p>{{ $doctor->phone }}@if ($doctor->phone && $doctor->email) &middot; @endif{{ $doctor->email }}</p> @endif
            </div>
            <div class="right">
                <p style="font-size:16px;font-weight:700;color:#111827">{{ $doctor->name }}</p>
                @if ($doctor->qualification) <p>{{ $doctor->qualification }}</p> @endif
                @if ($doctor->specialization) <p>{{ $doctor->specialization }}</p> @endif
                @if ($doctor->registration_no) <p>Reg. No: {{ $doctor->registration_no }}</p> @endif
            </div>
        </div>

        @yield('body')

        <div class="foot">@yield('footer')</div>
    </div>
</body>
</html>
