<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Payslip</title></head>
<body style="font-family:Arial,sans-serif;font-size:10pt;background:#f4f4f4;padding:20px;">
<div style="max-width:760px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">

  <div style="background:#1e3a5f;color:#fff;padding:16px 24px;">
    <div style="font-size:8pt;opacity:.8;">Republic of the Philippines · Department of Science and Technology</div>
    <div style="font-size:13pt;font-weight:bold;margin-top:2px;">Philippine Science High School — Caraga Region Campus</div>
    <div style="font-size:8pt;opacity:.8;">Brgy. Ampayon, Butuan City</div>
  </div>

  <div style="padding:20px 24px 8px;">
    <p>Dear <strong>{{ $firstName }}</strong>,</p>

    @php
      // $notificationItem = per-batch item (drives the banner credit amount for this disbursement)
      // $item             = combined item (drives the inline payslip showing all disbursements)
      $n         = $notificationItem ?? $item; // fallback for safety
      $pass      = $sendType ?? null; // 'first_half', 'second_half', or type string
      $disbLabel = $batch->disbursementLabel($pass);
      // For release groups, append the period range to the label
      if (!empty($releaseItems) && $releaseItems->count() > 1) {
        $firstMonth = \Carbon\Carbon::createFromDate($releaseItems->first()->year, $releaseItems->first()->month, 1)->format('F Y');
        $lastMonth  = \Carbon\Carbon::createFromDate($releaseItems->last()->year, $releaseItems->last()->month, 1)->format('F Y');
        if ($firstMonth !== $lastMonth) {
          $disbLabel .= " ({$firstMonth}–{$lastMonth})";
        }
      }

      $creditAmt = match($pass) {
        'first_half'  => (float) $n->first_half_amount,
        'second_half' => (float) $n->net_pay - (float) $n->first_half_amount,
        default       => (float) $n->net_pay,
      };

      $creditDate = match($pass) {
        'first_half'  => $batch->first_half_credit_date?->format('F j, Y'),
        'second_half' => $batch->second_half_credit_date?->format('F j, Y'),
        default       => $batch->credit_date?->format('F j, Y'),
      };

      // For release groups, show the full accumulated period range
      if (!empty($releaseItems) && $releaseItems->count() > 1) {
        $firstBatch = $releaseItems->first()->batch;
        $lastBatch  = $releaseItems->last()->batch;
        $period = \Carbon\Carbon::parse($firstBatch->period_start)->format('F j') . ' – ' .
                  \Carbon\Carbon::parse($lastBatch->period_end)->format('F j, Y');
      } else {
        $period = \Carbon\Carbon::parse($batch->period_start)->format('F j') . ' – ' .
                  \Carbon\Carbon::parse($batch->period_end)->format('F j, Y');
      }
    @endphp

    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px 18px;margin:12px 0;line-height:1.7;">
      Your <strong>{{ $disbLabel }}</strong> for the period <strong>{{ $period }}</strong>
      amounting to <strong style="font-size:12pt;">₱ {{ number_format((float)$creditAmt, 2) }}</strong>
      has been credited to your ATM account{!! $creditDate ? ' on <strong>' . $creditDate . '</strong>' : '' !!}.
    </div>

    @php
      $fmt2 = fn($v) => '&#8369;&nbsp;' . number_format((float)$v, 2);

      // Release group: define columns for the per-month breakdown table
      $releaseColumns = ($releaseItems && $releaseItems->count() > 0) ? match(true) {
        str_contains($pass, 'hazard_pay') => [
          ['label' => 'Hazard Pay',      'field' => 'hazard_pay'],
          ['label' => 'WHT',             'field' => 'wht_hazard'],
          ['label' => 'Net Amount',      'field' => 'net_pay', 'bold' => true],
        ],
        str_contains($pass, 'sala') => [
          ['label' => 'Subsistence',     'field' => 'subsistence_allowance'],
          ['label' => 'Laundry',         'field' => 'laundry_allowance'],
          ['label' => 'Net SALA',        'field' => 'net_pay', 'bold' => true],
        ],
        str_contains($pass, 'longevity_pay') => [
          ['label' => 'Longevity Pay',   'field' => 'longevity_pay'],
          ['label' => 'Tax',             'field' => 'longevity_tax'],
          ['label' => 'Net Amount',      'field' => 'net_pay', 'bold' => true],
        ],
        str_contains($pass, 'year_end_bonus') => [
          ['label' => 'Year-End Bonus',  'field' => 'year_end_bonus'],
          ['label' => 'Cash Gift',       'field' => 'cash_gift'],
          ['label' => 'BIR Tax',         'field' => 'bir_tax'],
          ['label' => 'Net Amount',      'field' => 'net_pay', 'bold' => true],
        ],
        str_contains($pass, 'clothing_allowance') => [
          ['label' => 'Clothing Allow.', 'field' => 'clothing_allowance'],
          ['label' => 'Deductions',      'field' => 'total_deductions'],
          ['label' => 'Net Amount',      'field' => 'net_pay', 'bold' => true],
        ],
        str_contains($pass, 'midyear_bonus') => [
          ['label' => 'Midyear Bonus',   'field' => 'midyear_bonus'],
          ['label' => 'Deductions',      'field' => 'total_deductions'],
          ['label' => 'Net Amount',      'field' => 'net_pay', 'bold' => true],
        ],
        str_contains($pass, 'cna') => [
          ['label' => 'CNA Incentive',   'field' => 'cna_incentive'],
          ['label' => 'Deductions',      'field' => 'total_deductions'],
          ['label' => 'Net Amount',      'field' => 'net_pay', 'bold' => true],
        ],
        default => [
          ['label' => 'Net Amount',      'field' => 'net_pay', 'bold' => true],
        ],
      } : null;

      // Single-month breakdown rows (non-release or monthly salary)
      $rows = (!$releaseColumns) ? match(true) {
        in_array($pass, ['first_half', 'second_half']) => array_filter([
          'Gross Earnings'       => $fmt2($n->gross_earnings),
          'Total Deductions'     => $fmt2($n->total_deductions),
          'Net Pay'              => '<strong>' . $fmt2($n->net_pay) . '</strong>',
          ($pass === 'first_half' ? 'Amount Due (1st Half)' : 'Amount Due (2nd Half)') =>
            '<strong>' . $fmt2($pass === 'first_half' ? $n->first_half_amount : ((float)$n->net_pay - (float)$n->first_half_amount)) . '</strong>',
        ]),
        str_contains($pass, 'hazard_pay') => [
          'Hazard Pay'              => $fmt2($n->hazard_pay),
          'WHT on Hazard Pay'       => $fmt2($n->wht_hazard),
          'Net Amount'              => '<strong>' . $fmt2($n->net_pay) . '</strong>',
        ],
        str_contains($pass, 'sala') => array_filter([
          'Subsistence Allowance'       => $fmt2($n->subsistence_allowance),
          'Laundry Allowance'           => $fmt2($n->laundry_allowance),
          'LAWOP Deduction'             => $fmt2($n->lawop),
          'OB/Travel/Seminar Deduction' => $fmt2($n->ob_travel_seminar),
          'Net SALA'                    => '<strong>' . $fmt2($n->net_pay) . '</strong>',
        ]),
        str_contains($pass, 'year_end_bonus') => [
          'Year-End Bonus'    => $fmt2($n->year_end_bonus),
          'Cash Gift'         => $fmt2($n->cash_gift),
          'BIR Tax Withheld'  => $fmt2($n->bir_tax),
          'Net Amount'        => '<strong>' . $fmt2($n->net_pay) . '</strong>',
        ],
        str_contains($pass, 'clothing_allowance') => [
          'Clothing Allowance' => $fmt2($n->clothing_allowance),
          'Deductions'         => $fmt2($n->total_deductions),
          'Net Amount'         => '<strong>' . $fmt2($n->net_pay) . '</strong>',
        ],
        str_contains($pass, 'midyear_bonus') => [
          'Midyear Bonus' => $fmt2($n->midyear_bonus),
          'Deductions'    => $fmt2($n->total_deductions),
          'Net Amount'    => '<strong>' . $fmt2($n->net_pay) . '</strong>',
        ],
        str_contains($pass, 'cna') => [
          'CNA Incentive' => $fmt2($n->cna_incentive),
          'Deductions'    => $fmt2($n->total_deductions),
          'Net Amount'    => '<strong>' . $fmt2($n->net_pay) . '</strong>',
        ],
        str_contains($pass, 'longevity_pay') => [
          'Longevity Pay'     => $fmt2($n->longevity_pay),
          'Longevity Pay Tax' => $fmt2($n->longevity_tax ?? 0),
          'Net Amount'        => '<strong>' . $fmt2($n->net_pay) . '</strong>',
        ],
        default => [
          'Net Amount' => '<strong>' . $fmt2($n->net_pay) . '</strong>',
        ],
      } : [];
    @endphp

    @if ($releaseColumns)
      {{-- Release group: per-month breakdown table --}}
      <p style="margin-top:8px;font-size:9pt;color:#555;">Monthly Breakdown:</p>
      <table style="width:100%;border-collapse:collapse;font-size:9pt;margin-bottom:12px;">
        <thead>
          <tr style="background:#f1f5f9;">
            <th style="padding:5px 8px;text-align:left;color:#555;font-weight:600;border-bottom:1px solid #e2e8f0;">Period</th>
            @foreach($releaseColumns as $col)
            <th style="padding:5px 8px;text-align:right;color:#555;font-weight:600;border-bottom:1px solid #e2e8f0;">{{ $col['label'] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($releaseItems as $ri)
          @php $rowPeriod = \Carbon\Carbon::createFromDate($ri->year, $ri->month, 1)->format('F Y'); @endphp
          <tr style="border-bottom:1px solid #f0f0f0;">
            <td style="padding:5px 8px;color:#555;">{{ $rowPeriod }}</td>
            @foreach($releaseColumns as $col)
            <td style="padding:5px 8px;text-align:right;font-family:monospace;">
              {!! ($col['bold'] ?? false) ? '<strong>' . $fmt2($ri->{$col['field']}) . '</strong>' : $fmt2($ri->{$col['field']}) !!}
            </td>
            @endforeach
          </tr>
          @endforeach
          {{-- Total row --}}
          <tr style="border-top:2px solid #cbd5e1;font-weight:600;background:#f8fafc;">
            <td style="padding:5px 8px;color:#334155;">TOTAL</td>
            @foreach($releaseColumns as $col)
            <td style="padding:5px 8px;text-align:right;font-family:monospace;">
              {!! '<strong>' . $fmt2($n->{$col['field']}) . '</strong>' !!}
            </td>
            @endforeach
          </tr>
        </tbody>
      </table>
    @else
      {{-- Single month: existing breakdown rows --}}
      <p style="margin-top:8px;font-size:9pt;color:#555;">Breakdown:</p>
      <table style="width:100%;border-collapse:collapse;font-size:9pt;margin-bottom:12px;">
        @foreach($rows as $rowLabel => $val)
        <tr style="border-bottom:1px solid #f0f0f0;">
          <td style="padding:5px 8px;color:#555;">{{ $rowLabel }}</td>
          <td style="padding:5px 8px;text-align:right;font-family:monospace;">{!! $val !!}</td>
        </tr>
        @endforeach
      </table>
    @endif

    <p style="font-size:9pt;color:#555;">
      Please see the attached payslip for the complete breakdown of your earnings and deductions.
    </p>
  </div>

  <div style="padding:12px 24px;background:#f9f9f9;border-top:1px solid #eee;font-size:8pt;color:#666;">
    This is a system-generated message. If you spot a discrepancy, reply to this email or contact the Cashier's Office.<br>
    PSHS-CRC Management Information System &mdash; <em>Do not reply directly to this email.</em>
  </div>

</div>
</body>
</html>
