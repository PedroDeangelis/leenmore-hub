<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: '{{ \App\Services\WorkerRoster\RosterPdfRenderer::FONT_FAMILY }}', sans-serif;
            font-size: 8pt;
        }

        .title {
            width: 100%;
            margin-bottom: 4mm;
        }

        .title td {
            font-size: 12pt;
            font-weight: bold;
        }

        table.roster {
            width: 100%;
            border-collapse: collapse;
        }

        table.roster th,
        table.roster td {
            border: 0.3pt solid #000;
            padding: 1.2mm 1mm;
            vertical-align: middle;
            word-wrap: break-word;
        }

        table.roster th {
            background: #d9d9d9;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
    {{-- Title row mirrors the XLSX template: project name plus "(worker)". --}}
    <table class="title">
        <tr>
            <td>{{ $projectName }}</td>
            <td style="text-align: right;">({{ $workerName }})</td>
        </tr>
    </table>

    @php
        // Column widths roughly proportional to the XLSX template's columns A–L.
        $widths = ['3%', '7%', '3%', '5%', '5%', '34%', '7%', '7%', '7%', '5%', '9%', '8%'];
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
    @endphp

    <table class="roster">
        <thead>
            <tr>
                @foreach (\App\Exports\WorkerRosterExporter::HEADERS as $index => $header)
                    <th style="width: {{ $widths[$index] }}">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($letters as $letter)
                        <td>{{ $row[$letter] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
