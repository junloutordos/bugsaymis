<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payslip — {{ $item->employee_name_raw }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 7.5pt; color: #000; background: #fff; }
  .page { width: 100%; max-width: 720px; margin: 0 auto; padding: 8px 14px; }

  table { border-collapse: collapse; width: 100%; }

  /* Info block */
  .info-tbl td { font-size: 7pt; padding: 1px 3px; vertical-align: top; }
  .lbl { font-weight: bold; white-space: nowrap; }
  .val { border-bottom: 1px solid #000; }

  /* Main payslip table */
  .ps { border: 1px solid #000; margin-top: 5px; }
  .ps td { font-size: 7pt; vertical-align: middle; padding: 1px 5px; }

  .sec-hdr td { background: #c8c8c8; font-weight: bold; text-align: center; font-size: 7.5pt;
                border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 2px 5px; }

  .grp  { background: #e8e8e8; font-weight: bold; font-size: 6.5pt; text-align: center;
          vertical-align: middle; border-right: 1px solid #000; }
  .itm  { border-right: 1px solid #000; padding-left: 8px; border-bottom: 1px solid #ddd; }
  .amt  { text-align: right; border-bottom: 1px solid #ddd; min-width: 75px; white-space: nowrap; }
  .zero { color: #aaa; }

  .sub td { background: #efefef; font-weight: bold; border-top: 1px solid #000;
            border-bottom: 1px solid #000; padding: 2px 5px; }

  .net td { background: #1e3a5f; color: #fff; font-weight: bold; padding: 3px 6px;
            border-top: 2px solid #000; font-size: 8.5pt; }

  /* Amount-due / ATM block */
  .due-tbl td { font-size: 7pt; padding: 1.5px 5px; }

  /* Signature */
  .sig-role  { font-size: 6.5pt; font-weight: bold; }
  .sig-ul    { border-top: 1px solid #000; margin: 0 10px 2px; }
  .sig-name  { font-weight: bold; font-size: 7.5pt; text-align: center; }
  .sig-title { font-size: 6.5pt; color: #444; text-align: center; }

  .footer { margin-top: 8px; font-size: 6.5pt; color: #555; text-align: center;
            border-top: 1px dashed #ccc; padding-top: 4px; }
</style>
</head>
<body>
<div class="page">

@php
  $fmt    = fn($v) => number_format((float)$v, 2);
  $dash   = fn($v) => (float)$v != 0.0 ? number_format((float)$v, 2) : '&ndash;';
  $nz     = fn($v) => (float)$v == 0.0;
  $adj    = $item->adjustments_json ?? [];
  $adjVal = fn(string $k) => (float)($adj[$k] ?? 0);
@endphp

{{-- ── Header ────────────────────────────────────────────────────── --}}
<table style="margin-bottom:3px;">
  <tr>
    <td style="width:56px;vertical-align:middle;">
      <img src="file://{{ public_path('pshslogo_sm.png') }}" width="50" height="50" alt="" />
    </td>
    <td style="text-align:center;vertical-align:middle;">
      <div style="font-size:6.5pt;">Republic of the Philippines</div>
      <div style="font-size:6.5pt;">Department of Science and Technology</div>
      <div style="font-size:7.5pt;font-weight:bold;">Philippine Science High School System</div>
      <div style="font-size:7.5pt;font-weight:bold;">CARAGA REGION CAMPUS</div>
      <div style="font-size:6.5pt;">Brgy. Ampayon, Butuan City</div>
    </td>
    <td style="width:56px;"></td>
  </tr>
</table>
<div style="border-top:2px solid #000;border-bottom:1px solid #000;padding:1.5px 0;text-align:center;
            font-size:9pt;font-weight:bold;letter-spacing:1px;margin-bottom:4px;">
  PAYROLL PAYMENT SLIP
</div>

{{-- ── Employee info ──────────────────────────────────────────────── --}}
<table class="info-tbl" style="margin-bottom:6px;">
  <tr>
    <td class="lbl" style="width:120px;">Office/Division:</td>
    <td class="val">{{ $item->office_division ?? '—' }}</td>
    <td style="width:14px;"></td>
    <td class="lbl" style="width:90px;">Pay Period:</td>
    <td class="val">
      {{ \Carbon\Carbon::parse($batch->period_start)->format('M j') }}
      &ndash;
      {{ \Carbon\Carbon::parse($batch->period_end)->format('M j, Y') }}
    </td>
  </tr>
  <tr>
    <td class="lbl">Employee Name:</td>
    <td class="val" style="font-weight:bold;">{{ $item->employee->name ?? $item->employee_name_raw }}</td>
    <td></td>
    <td class="lbl">Position:</td>
    <td class="val">{{ $item->position ?? '—' }}</td>
  </tr>
  <tr>
    <td class="lbl">Employee No.:</td>
    <td class="val">{{ $item->employee_no ?? $item->employee?->employee_no ?? '—' }}</td>
    <td></td>
    <td class="lbl">Disbursement:</td>
    <td class="val">{{ $batch->disbursementLabel() }}</td>
  </tr>
  @if($batch->isMonthly())
  <tr>
    <td class="lbl">1st Half Credit:</td>
    <td class="val">{{ $batch->first_half_credit_date?->format('M j, Y') ?? '—' }}</td>
    <td></td>
    <td class="lbl">2nd Half Credit:</td>
    <td class="val">{{ $batch->second_half_credit_date?->format('M j, Y') ?? '—' }}</td>
  </tr>
  @elseif($batch->credit_date)
  <tr>
    <td class="lbl">Credit Date:</td>
    <td class="val" colspan="4">{{ $batch->credit_date->format('M j, Y') }}</td>
  </tr>
  @endif
</table>

{{-- ── Main payslip table ──────────────────────────────────────────── --}}
@php
  $hasSalaBreakdown = (float)$item->subsistence_allowance || (float)$item->laundry_allowance;
  $earnings = array_filter([
    'Basic Salary'                                   => $item->basic_salary,
    'Salary Increase'                                => $item->salary_increase,
    'Additional Compensation'                        => $item->additional_compensation,
    'Personnel Economic Relief Allowance'            => $item->pera,
    // Show breakdown if new SALA template; otherwise show combined sala
    'Subsistence Allowance'                          => $hasSalaBreakdown ? $item->subsistence_allowance : null,
    'Laundry Allowance'                              => $hasSalaBreakdown ? $item->laundry_allowance : null,
    'Subsistence and Laundry Allowance'              => !$hasSalaBreakdown ? $item->sala : null,
    'Hazard Pay'                                     => $item->hazard_pay,
    'Longevity Pay'                                  => $item->longevity_pay,
    'Year-End Bonus'                                 => $item->year_end_bonus,
    'Cash Gift'                                      => $item->cash_gift,
    'Clothing Allowance'                             => $item->clothing_allowance,
    'Midyear Bonus'                                  => $item->midyear_bonus,
    'CNA Incentive'                                  => $item->cna_incentive,
    'Others (Bonuses/Incentives/Clothing Allowance)' => $item->others_bonuses,
  ], fn($v) => $v !== null && (float)$v != 0.0);

  $mandatory = array_filter([
    'LAWOP/Undertime/Tardiness'        => $item->lawop,
    'OB/Travel/Seminar with meals'     => $item->ob_travel_seminar,
    'PVP Overpayment'                  => $adjVal('pvp_overpayment') ?: $item->pvp_overpayment,
    'GSIS Contributions'               => $item->gsis_contribution,
    'BIR Withholding Tax'              => $item->bir_tax,
    'Withholding Tax on Hazard Pay'    => $adjVal('wht_hazard') ?: $item->wht_hazard,
    'Withholding Tax on CNA'           => $adjVal('wht_cna') ?: $item->wht_cna,
    'Longevity Pay Tax'                => $adjVal('longevity_tax') ?: $item->longevity_tax,
    'PHILHEALTH Contribution'          => $item->philhealth_contribution,
    'PHILHEALTH Differential'          => $adjVal('philhealth_differential') ?: $item->philhealth_differential,
    'HDMF (Pag-ibig) Contribution'     => $item->hdmf_contribution,
    'HDMF Contribution-Additional'     => $adjVal('hdmf_additional') ?: $item->hdmf_additional,
  ], fn($v) => (float)$v != 0.0);

  $gsisLoans = [
    'Salary Loan'       => $item->gsis_salary_loan,
    'Policy Loan'       => $item->gsis_policy_loan,
    'Consolidated Loan' => $item->gsis_consolidated_loan,
    'Emergency Loan'    => $item->gsis_emergency_loan,
    'MPL'               => $item->gsis_mpl,
    'GFAL'              => $item->gsis_gfal,
    'CPL'               => $item->gsis_cpl,
    'MPL Lite'          => $item->gsis_mpl_lite,
  ];

  $hdmfLoans = [
    'Salary Loan'   => $item->hdmf_salary_loan,
    'Calamity Loan' => $item->hdmf_calamity,
    'Housing Loan'  => $item->hdmf_housing,
    'Multi-Purpose' => $item->hdmf_multipurpose,
    'MP2'           => $item->hdmf_mp2,
  ];

  $otherLoans = [
    'Landbank Loan' => $item->landbank_loan,
  ];
@endphp

<table class="ps">
  {{-- Column widths --}}
  <colgroup>
    <col style="width:16%;" />
    <col />
    <col style="width:17%;" />
  </colgroup>

  {{-- ── EARNINGS ── --}}
  <tr class="sec-hdr"><td colspan="3">*** EARNINGS ***</td></tr>

  @foreach($earnings as $label => $amt)
  <tr>
    <td class="itm" colspan="2">{{ $label }}</td>
    <td class="amt {{ $nz($amt) ? 'zero' : '' }}">{!! $dash($amt) !!}</td>
  </tr>
  @endforeach

  <tr class="sub">
    <td colspan="2" style="text-align:right;border-right:1px solid #000;">GROSS EARNINGS</td>
    <td style="text-align:right;">{{ $fmt($item->gross_earnings) }}</td>
  </tr>

  {{-- ── DEDUCTIONS ── --}}
  <tr class="sec-hdr"><td colspan="3">*** DEDUCTIONS ***</td></tr>

  @foreach($mandatory as $label => $amt)
  <tr>
    <td class="itm" colspan="2">{{ $label }}</td>
    <td class="amt {{ $nz($amt) ? 'zero' : '' }}">{!! $dash($amt) !!}</td>
  </tr>
  @endforeach

  {{-- GSIS Loans --}}
  @php $first = true; @endphp
  @foreach($gsisLoans as $label => $amt)
  <tr>
    @if($first)
      <td class="grp" rowspan="{{ count($gsisLoans) }}" style="border-bottom:1px solid #000;">GSIS<br>Loans</td>
      @php $first = false; @endphp
    @endif
    <td class="itm">{{ $label }}</td>
    <td class="amt {{ $nz($amt) ? 'zero' : '' }}">{!! $dash($amt) !!}</td>
  </tr>
  @endforeach

  {{-- HDMF Loans --}}
  @php $first = true; @endphp
  @foreach($hdmfLoans as $label => $amt)
  <tr>
    @if($first)
      <td class="grp" rowspan="{{ count($hdmfLoans) }}" style="border-bottom:1px solid #000;">HDMF<br>Loans</td>
      @php $first = false; @endphp
    @endif
    <td class="itm">{{ $label }}</td>
    <td class="amt {{ $nz($amt) ? 'zero' : '' }}">{!! $dash($amt) !!}</td>
  </tr>
  @endforeach

  {{-- Other Loans --}}
  @php $first = true; @endphp
  @foreach($otherLoans as $label => $amt)
  <tr>
    @if($first)
      <td class="grp" rowspan="{{ count($otherLoans) }}" style="border-bottom:1px solid #000;">Other<br>Loans</td>
      @php $first = false; @endphp
    @endif
    <td class="itm">{{ $label }}</td>
    <td class="amt {{ $nz($amt) ? 'zero' : '' }}">{!! $dash($amt) !!}</td>
  </tr>
  @endforeach

  <tr class="sub">
    <td colspan="2" style="text-align:right;border-right:1px solid #000;">TOTAL DEDUCTIONS</td>
    <td style="text-align:right;">{{ $fmt($item->total_deductions) }}</td>
  </tr>

  {{-- NET PAY --}}
  <tr class="net">
    <td colspan="2" style="font-size:9pt;">NET PAY</td>
    <td style="text-align:right;font-size:10pt;">&#8369; {{ $fmt($item->net_pay) }}</td>
  </tr>
</table>

{{-- ── Amount Due / ATM ──────────────────────────────────────────── --}}
@php
  $hasHalfBreakdown = (float)$item->first_half_amount > 0;
  $addenda = [];
  if ((float)$item->hazard_pay     != 0) $addenda[] = 'Hazard Pay';
  if ((float)$item->sala           != 0) $addenda[] = $hasSalaBreakdown ? 'SALA' : 'Subsistence and Laundry';
  if ((float)$item->longevity_pay  != 0) $addenda[] = 'Longevity Pay';
  if ((float)$item->year_end_bonus != 0) $addenda[] = 'Year-End Bonus & Cash Gift';
  if ((float)$item->clothing_allowance != 0) $addenda[] = 'Clothing Allowance';
  if ((float)$item->midyear_bonus  != 0) $addenda[] = 'Midyear Bonus';
  if ((float)$item->cna_incentive  != 0) $addenda[] = 'CNA Incentive';
  if ((float)$item->others_bonuses != 0) $addenda[] = 'Others';
  $addendaStr = $addenda ? ' + (' . implode(' + ', $addenda) . ')' : '';
@endphp
<table class="due-tbl" style="margin-top:5px;">
  @if($hasHalfBreakdown)
  <tr>
    <td>Amount Due (1st Half){{ $addendaStr }}:</td>
    <td style="text-align:right;white-space:nowrap;font-weight:bold;">
      &#8369; {{ $fmt($item->first_half_amount) }}
    </td>
  </tr>
  <tr>
    <td>Amount Due (2nd Half):</td>
    <td style="text-align:right;white-space:nowrap;font-weight:bold;">
      &#8369; {{ $fmt($item->second_half_amount) }}
    </td>
  </tr>
  @else
  <tr>
    <td>Amount Due:</td>
    <td style="text-align:right;white-space:nowrap;font-weight:bold;">
      &#8369; {{ $fmt($item->net_pay) }}
    </td>
  </tr>
  @endif
  <tr>
    <td>Received thru: <strong>ATM</strong></td>
    <td></td>
  </tr>
</table>

{{-- ── Signature block ──────────────────────────────────────────── --}}
<table style="margin-top:10px;">
  <tr>
    <td style="width:50%;padding:0 20px;vertical-align:top;text-align:center;">
      <div class="sig-role">Prepared by:</div>
      <br /><br /><br />
      <div class="sig-ul"></div>
      <div class="sig-name">{{ $preparedBy?->name ?? '___________________________' }}</div>
      <div class="sig-title">{{ $preparedBy?->position ?? 'Administrative Officer V' }}</div>
    </td>
    <td style="width:50%;padding:0 20px;vertical-align:top;text-align:center;">
      <div class="sig-role">Certified Correct:</div>
      <br /><br /><br />
      <div class="sig-ul"></div>
      <div class="sig-name">{{ $certifiedBy?->name ?? '___________________________' }}</div>
      <div class="sig-title">{{ $certifiedBy?->position ?? 'Accountant II' }}</div>
    </td>
  </tr>
</table>

<div class="footer">
  *** This is a system-generated payslip. Do not alter. For discrepancies, contact the Cashier's Office. ***
</div>

</div>
</body>
</html>
