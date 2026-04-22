<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $reportTitle }}</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', Times, serif;
            color: #000;
            font-size: 12px;
            line-height: 1.35;
        }

        @page {
            margin: 25mm 18mm 18mm 18mm;
        }

        /* ================= HEADER (FIXED) ================= */

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        /* Force remove borders even if global table styles exist */
        .header-table,
        .header-table tr,
        .header-table td {
            border: none !important;
        }

        .col-left {
            width: 55px;
            vertical-align: middle;
        }

        .col-left img {
            width: 42px !important; /* smaller logo */
            height: auto;
            display: block;
        }

        .col-right {
            vertical-align: middle;
        }

        .agency-name {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .agency-name-pisay {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .agency-dept {
            font-size: 12px;
            margin-top: 2px;
        }

        .system-label {
            font-size: 11px;
            text-align: right;
            font-weight: bold;
            margin-top: 2px;
        }

        /* ================= DOCUMENT META ================= */

        .document-meta {
            margin-top: 6px;
            margin-bottom: 12px;
            border-top: 1px solid #000;
            padding-top: 6px;
            font-size: 11px;
            width: 100%;
        }

        .document-meta table {
            width: 100%;
            border-collapse: collapse;
        }

        .document-meta td {
            border: none !important;
            padding: 0;
        }

        .meta-left {
            text-align: left;
        }

        .meta-right {
            text-align: right;
        }

        .title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
            text-decoration: underline;
        }

        .table-container {
            margin-bottom: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        th, td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: left;
        }

        tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        .section-heading {
            font-weight: bold;
            margin: 10px 0 6px 0;
            font-size: 12px;
        }

        .summary {
            margin-top: 8px;
            font-size: 11px;
        }

        .summary dl dt {
            float: left;
            width: 35%;
            clear: left;
            font-weight: bold;
        }

        .summary dl dd {
            margin-left: 36%;
            margin-bottom: 6px;
        }

        .category-list {
            margin-top: 6px;
        }

        .signatures {
            margin-top: 20px;
            width: 100%;
        }

        .signatures table {
            width: 100%;
            border-collapse: collapse;
        }

        .signatures td {
            border: none !important;
            vertical-align: top;
            width: 50%;
            font-size: 11px;
        }

        .sign-line {
            margin-top: 48px;
            border-top: 1px solid #000;
            width: 80%;
        }

        .footer {
            position: fixed;
            bottom: 4mm;
            left: 18mm;
            right: 18mm;
            text-align: right;
            font-size: 10px;
            color: #333;
        }
    </style>
</head>
<body>

    <!-- ================= HEADER ================= -->
    <table class="header-table">
        <tr>
            <td class="col-left">
                <img 
                    src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/pshslogo.png'))) }}" 
                    width="80" 
                    style="display:block;" 
                    alt="Seal">
            </td>
            <td class="col-right">
                <div class="agency-dept">Republic of the Philippines</div>
                <div class="system-label">Department of Science and Technology</div>
                <div class="agency-name">PHILIPPINE SCIENCE HIGH SCHOOL</div>
                <div class="agency-name-pisay">Caraga Region Campus in Butuan City</div>
                <div class="agency-dept">Ampayon, Butuan City, Agusan del Norte</div>
                
            </td>
        </tr>
    </table>

    <!-- ================= META ================= -->
    <div class="document-meta">
        <table>
            <tr>
                
                <td class="meta-right">Date Generated: <strong>{{ $generatedDate }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="title">{{ $reportTitle }}</div>

    @php
        $totalEquipments = 0;
        $totalAmount = 0;
        $categoryCount = [];
    @endphp

    @forelse($groupedData as $group => $items)
        @php
            $groupTotal = count($items);
            $totalEquipments += $groupTotal;

            if ($groupBy === 'location') {
                foreach ($items as $eq) {
                    $category = $eq->category ?? 'Uncategorized';
                    if (!isset($categoryCount[$category])) {
                        $categoryCount[$category] = 0;
                    }
                    $categoryCount[$category]++;
                }
            } else {
                $categoryCount[$group] = $groupTotal;
            }

            foreach ($items as $eq) {
                $totalAmount += $eq->amount ?? 0;
            }
        @endphp

        <div class="section-heading">{{ $group }} — ({{ $groupTotal }} item{{ $groupTotal > 1 ? 's' : '' }})</div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width:10%;">Serial No</th>
                        <th style="width:30%;">Description</th>
                        <th style="width:15%;">Owner</th>
                        <th style="width:10%;">Status</th>
                        <th style="width:12%;">Date Acquired</th>
                        <th style="width:13%;">Amount (PHP)</th>
                        @if($groupBy === 'category')
                            <th style="width:10%;">Location</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $eq)
                        <tr>
                            <td>{{ $eq->serial_no ?? 'N/A' }}</td>
                            <td>{{ substr($eq->description ?? 'N/A', 0, 120) }}</td>
                            <td>{{ substr($eq->owner?->name ?? 'N/A', 0, 40) }}</td>
                            <td>{{ $eq->status ?? 'N/A' }}</td>
                            <td>{{ $eq->date_acquired ?? 'N/A' }}</td>
                            <td style="text-align: right;">
                                @if($eq->amount)
                                    {{ number_format($eq->amount, 2) }}
                                @else
                                    -
                                @endif
                            </td>
                            @if($groupBy === 'category')
                                <td>{{ substr($eq->room?->name ?? 'No Location', 0, 30) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p style="text-align: center; color: #666;">No equipment found.</p>
    @endforelse

    {{-- Summary --}}
    <div class="section-heading">Report Summary</div>
    <div class="summary">
        <dl>
            <dt>Total Equipment Items:</dt>
            <dd>{{ $totalEquipments }}</dd>

            <dt>Total Groups:</dt>
            <dd>{{ count($groupedData) }}</dd>

            <dt>Grouped By:</dt>
            <dd>{{ $groupBy === 'category' ? 'Category' : 'Location / Room' }}</dd>
        </dl>

        <div class="category-list">
            <strong>Equipment Count by Category</strong>
            @php ksort($categoryCount); @endphp
            <table style="width:40%; margin-top:8px;">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th style="width:20%;">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categoryCount as $category => $count)
                        <tr>
                            <td>{{ substr($category, 0, 80) }}</td>
                            <td style="text-align:right;">{{ $count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2">No category data available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Signatures --}}
    <div class="signatures">
        <div class="sign-block">
            <div>Prepared by:</div>
            <br>
            <div style="margin-top:6px;"><strong>{{ strtoupper(Auth::user()->name ?? 'INVENTORY OFFICER') }}</strong></div>
            <div>{{ Auth::user()->position ?? 'Position' }}</div>
        </div>
            <br>
        <div class="sign-block">
            <div>Approved by:</div>
            <br>
            <div style="margin-top:6px;"><strong>ENGR. RAMIL A. SANCHEZ</strong></div>
            <div>Campus Director</div>
        </div>
    </div>

    <div class="footer">Page {PAGENO} of {nbpg}</div>

</body>
</html>
