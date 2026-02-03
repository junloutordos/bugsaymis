<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Library Users Statistics</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 20px; color: #111 }
        .header { text-align: center; margin-bottom: 20px }
        .meta { margin-bottom: 10px }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left }
        th { background: #f4f4f4 }
        .summary { margin: 12px 0; font-weight: bold }
        .print-btn { position: fixed; right: 20px; top: 20px }
        @media print { .print-btn { display: none } }
    </style>
</head>
<body>
    <div style="text-align:center; margin-bottom:12px;">
        <img src="{{ asset('images/report_header.jpeg') }}" alt="Report Header" style="width:100%; max-height:160px; object-fit:cover;" />
    </div>

    <button class="print-btn" onclick="window.print()">Print</button>

    <div class="header">
        <h2>Library Users Statistics</h2>
        @php
            $period = '';
            try {
                if (!empty($start) && !empty($end)) {
                    $s = \Carbon\Carbon::parse($start);
                    $e = \Carbon\Carbon::parse($end);
                    if ($s->year === $e->year) {
                        if ($s->month === $e->month) {
                            $period = $s->format('F') . ' ' . $s->day . '-' . $e->day . ', ' . $s->year;
                        } else {
                            $period = $s->format('F j') . ' - ' . $e->format('F j') . ', ' . $s->year;
                        }
                    } else {
                        $period = $s->format('F j, Y') . ' - ' . $e->format('F j, Y');
                    }
                } elseif (!empty($start)) {
                    $period = \Carbon\Carbon::parse($start)->format('F j, Y');
                } elseif (!empty($end)) {
                    $period = \Carbon\Carbon::parse($end)->format('F j, Y');
                }
            } catch (\Throwable $ex) {
                $period = trim(($start ?? '') . ' ' . ($end ? 'to '.$end : ''));
            }
        @endphp
        <div class="meta">Period: {{ $period }}</div>
    </div>

    <div class="summary">Total scans: {{ $total }}</div>

    <h3>Library Traffic Summary (Grade 7 - 12)</h3>
    @php
        // Use totals provided by the controller (counts based on date range)
        $maleTotal = $maleTotal ?? array_sum($libraryAttendanceMaleByGrade ?? []);
        $femaleTotal = $femaleTotal ?? array_sum($libraryAttendanceFemaleByGrade ?? []);
        $grandTotal = $maleTotal + $femaleTotal;
    @endphp

    <div style="display:flex; align-items:flex-start; gap:24px;">
        <table style="width:320px; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="border:1px solid #000; padding:6px; text-align:left;">Sex</th>
                    <th style="border:1px solid #000; padding:6px; text-align:right; width:120px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="border:1px solid #000; padding:6px;">Male</td>
                    <td style="border:1px solid #000; padding:6px; text-align:right;">{{ $maleTotal }}</td>
                </tr>
                <tr>
                    <td style="border:1px solid #000; padding:6px;">Female</td>
                    <td style="border:1px solid #000; padding:6px; text-align:right;">{{ $femaleTotal }}</td>
                </tr>
                <tr>
                    <td style="border:1px solid #000; padding:6px; font-weight:bold;">Total</td>
                    <td style="border:1px solid #000; padding:6px; text-align:right; font-weight:bold;">{{ $grandTotal }}</td>
                </tr>
            </tbody>
        </table>

        <div style="width:220px; height:220px;">
            <canvas id="sexPieChart" width="220" height="220"></canvas>
        </div>
    </div>

    <h3>Library Traffic (Grade 7 - 12, by Sex)</h3>
    <div style="width:100%;max-width:900px;margin-bottom:16px; height: 320px;">
        <canvas id="trafficChart"></canvas>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const labels = {!! json_encode(['Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12']) !!};
            const male = {!! json_encode(array_values((array)($libraryAttendanceMaleByGrade ?? []))) !!};
            const female = {!! json_encode(array_values((array)($libraryAttendanceFemaleByGrade ?? []))) !!};
            const total = {!! json_encode(array_values((array)($libraryAttendanceByGrade ?? []))) !!};

            const ctx = document.getElementById('trafficChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Male',
                            data: male,
                            backgroundColor: 'rgba(54, 162, 235, 0.8)'
                        },
                        {
                            label: 'Female',
                            data: female,
                            backgroundColor: 'rgba(255, 99, 132, 0.8)'
                        }
                    ]
                },
                options: {
                    scales: {
                        x: {
                            stacked: false,
                            ticks: { maxRotation: 0 }
                        },
                        y: {
                            beginAtZero: true,
                            precision: 0
                        }
                    },
                    plugins: {
                        legend: { position: 'top' }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Auto-print shortly after the chart finishes rendering
            setTimeout(function () {
                window.print();
            }, 700);

                // Render sex pie chart next to the table
                try {
                    const sexCtx = document.getElementById('sexPieChart').getContext('2d');
                    const maleVal = {!! json_encode($maleTotal) !!} || 0;
                    const femaleVal = {!! json_encode($femaleTotal) !!} || 0;
                    new Chart(sexCtx, {
                        type: 'pie',
                        data: {
                            labels: ['Male','Female'],
                            datasets: [{
                                data: [maleVal, femaleVal],
                                backgroundColor: ['rgba(54,162,235,0.9)', 'rgba(255,99,132,0.9)']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom' },
                                datalabels: {
                                    color: '#fff',
                                    formatter: function(value, ctx) {
                                        const data = ctx.chart.data.datasets[0].data;
                                        const sum = data.reduce((a, b) => a + b, 0);
                                        if (!sum) return '';
                                        const pct = Math.round((value / sum) * 100);
                                        return pct + '%';
                                    },
                                    font: { weight: 'bold', size: 12 },
                                    anchor: 'center',
                                    align: 'center'
                                }
                            }
                        }
                    });
                } catch (e) {
                    // ignore if canvas not present
                }
        });
    </script>
</script>

    @php
        $clerk = $librarian = null;
        $clerkSig = $librarianSig = null;
        try {
            $clerk = \App\Models\User::where('position', 'Clerk-Library')->first();
            if ($clerk && !empty($clerk->electronic_signature)) {
                $clerkSig = $clerk->electronic_signature;
            }

            $librarian = \App\Models\User::where('position', 'Librarian I')->first();
            if ($librarian && !empty($librarian->electronic_signature)) {
                $librarianSig = $librarian->electronic_signature;
            }
        } catch (\Throwable $e) {
            $clerk = $librarian = null;
            $clerkSig = $librarianSig = null;
        }
    @endphp

    @php
        // ensure signatory variables are defined
        $clerk = $clerk ?? null;
        $librarian = $librarian ?? null;
        $clerkSig = $clerkSig ?? null;
        $librarianSig = $librarianSig ?? null;
        $approver = $approver ?? null;
        $approverSig = $approverSig ?? null;

        try {
            // resolve approver (division chief) if not already set
            if (! $approver) {
                $current = auth()->user();
                if ($current && method_exists($current, 'division') && $current->division && $current->division->divisionchief) {
                    $approver = $current->division->divisionchief;
                } elseif ($current && !empty($current->division)) {
                    $div = \App\Models\Division::where('division_name', $current->division)->first();
                    if ($div && $div->divisionchief) $approver = $div->divisionchief;
                }
            }

            if ($approver && empty($approverSig) && !empty($approver->electronic_signature)) {
                $approverSig = $approver->electronic_signature;
            }
        } catch (\Throwable $e) {
            $approver = null; $approverSig = null;
        }

    @endphp

    <div style="margin-top:24px; display:flex; justify-content:center; gap:48px; align-items:flex-end;">
        <div style="width:260px; text-align:center;">
            <div style="font-size:12px; margin-bottom:4px; text-align:left">Prepared by:</div>
            @if($clerkSig)
                <img src="{{ asset('storage/' . $clerkSig) }}" alt="prepared signature" style="max-height:60px; display:block; margin:0 auto 4px;" />
            @else
                <div style="height:60px;"></div>
            @endif
            <div style="font-weight:bold; text-decoration:underline; text-underline-offset:4px; font-size:13px;">{{ $clerk->name ?? '—' }}</div>
            <div style="font-size:11px;">Clerk - Library</div>
        </div>

        <div style="width:260px; text-align:center;">
            <div style="font-size:12px; margin-bottom:4px; text-align:left">&nbsp;</div>
            @if($librarianSig)
                <img src="{{ asset('storage/' . $librarianSig) }}" alt="librarian signature" style="max-height:60px; display:block; margin:0 auto 4px;" />
            @else
                <div style="height:60px;"></div>
            @endif
            <div style="font-weight:bold; text-decoration:underline; text-underline-offset:4px; font-size:13px;">{{ $librarian->name ?? '—' }}</div>
            <div style="font-size:11px;">Librarian I</div>
        </div>

        <div style="width:260px; text-align:center;">
            <div style="font-size:12px; margin-bottom:28px; text-align:left">Approved by:</div>
            <div style="font-weight:bold; text-decoration:underline; text-underline-offset:4px; font-size:13px;">{{ $approver->name ?? '—' }}</div>
            <div style="font-size:11px;">Division Chief</div>
        </div>
    </div>

</body>
</html>

<style>
    /* Footer should appear at bottom of page and print correctly */
    .report-footer { position: fixed; center: 0; bottom: 0; width: 100%; text-align: center; }
    @media print { .report-footer { position: fixed; bottom: 0 } }
</style>
<br>
<br>
<br>
<div class="report-footer">
    <img src="{{ asset('images/report_footer.jpeg') }}" alt="Report Footer" style="width:90%; max-height:90%; object-fit:cover; display:block;" />
</div>
