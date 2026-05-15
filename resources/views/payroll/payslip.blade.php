<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payslip — {{ $item->employee_name_raw }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; background: #fff; }
  .page { width: 100%; max-width: 720px; margin: 0 auto; padding: 18px 24px; }

  /* Header */
  .header-band { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 6px; }
  .header-logo { width: 60px; height: 60px; }
  .header-text { text-align: center; flex: 1; }
  .header-text .republic { font-size: 7.5pt; }
  .header-text .agency  { font-size: 8pt; font-weight: bold; }
  .header-text .campus  { font-size: 7.5pt; }
  .header-text .address { font-size: 7pt; color: #333; }
  .slip-title { text-align: center; font-size: 12pt; font-weight: bold; margin: 8px 0 10px; letter-spacing: 1px; }

  /* Info block */
  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 16px; margin-bottom: 10px; }
  .info-row { display: flex; gap: 6px; }
  .info-label { font-weight: bold; white-space: nowrap; min-width: 110px; }
  .info-val { border-bottom: 1px solid #999; flex: 1; }

  /* Two-column payslip body */
  .columns { display: grid; grid-template-columns: 1fr 1fr; gap: 0; border: 1px solid #000; }
  .col { border-right: 1px solid #000; }
  .col:last-child { border-right: none; }
  .col-header { background: #e8e8e8; font-weight: bold; text-align: center; padding: 4px 6px; border-bottom: 1px solid #000; font-size: 9pt; }
  .line { display: flex; justify-content: space-between; padding: 1.5px 6px; border-bottom: 1px solid #eee; }
  .line-label { flex: 1; }
  .line-amt { text-align: right; min-width: 80px; white-space: nowrap; }
  .line-amt.zero { color: #999; }
  .subtotal { display: flex; justify-content: space-between; padding: 3px 6px; border-top: 1px solid #000; border-bottom: 1px solid #000; font-weight: bold; background: #f5f5f5; }
  .section-head { background: #f0f0f0; font-weight: bold; padding: 2px 6px; border-bottom: 1px solid #ddd; font-size: 8pt; }

  /* Net pay */
  .net-band { margin-top: 10px; border: 2px solid #000; padding: 6px 12px; display: flex; justify-content: space-between; align-items: center; }
  .net-label { font-size: 11pt; font-weight: bold; }
  .net-amount { font-size: 14pt; font-weight: bold; }
  .halves { margin-top: 6px; display: flex; gap: 20px; font-size: 8.5pt; }
  .half-item { display: flex; gap: 6px; }

  /* Signature block */
  .sig-block { margin-top: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
  .sig-box { text-align: center; }
  .sig-line { border-bottom: 1px solid #000; margin: 28px 16px 4px; }
  .sig-name { font-weight: bold; font-size: 9pt; }
  .sig-title { font-size: 8pt; color: #555; }
  .sig-role-label { font-size: 7.5pt; margin-top: 2px; }

  .footer-note { margin-top: 10px; font-size: 7.5pt; color: #555; text-align: center; }
  .atm-line { margin-top: 6px; font-size: 8.5pt; }
</style>
</head>
<body>
<div class="page">

  {{-- ── Header ──────────────────────────────────────────────────────── --}}
  <div class="header-band">
    <div class="header-text" style="text-align:center; flex:1;">
      <div class="republic">Republic of the Philippines</div>
      <div class="agency">Department of Science and Technology</div>
      <div class="agency">Philippine Science High School — Caraga Region Campus</div>
      <div class="address">Brgy. Ampayon, Butuan City</div>
    </div>
  </div>

  <div class="slip-title">PAYROLL PAYMENT SLIP</div>

  {{-- ── Employee info ─────────────────────────────────────────────── --}}
  <div class="info-grid">
    <div class="info-row">
      <span class="info-label">Office/Division:</span>
      <span class="info-val">{{ $item->office_division ?? '—' }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Pay Period:</span>
      <span class="info-val">
        {{ \Carbon\Carbon::parse($batch->period_start)->format('M j') }} –
        {{ \Carbon\Carbon::parse($batch->period_end)->format('M j, Y') }}
      </span>
    </div>
    <div class="info-row">
      <span class="info-label">Employee Name:</span>
      <span class="info-val">{{ $item->employee->name ?? $item->employee_name_raw }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Employee No.:</span>
      <span class="info-val">{{ $item->employee_no ?? '—' }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Position:</span>
      <span class="info-val">{{ $item->position ?? '—' }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Disbursement:</span>
      <span class="info-val">{{ $batch->label ?? ucwords(str_replace('_', ' ', $batch->disbursement_type ?? '')) }}</span>
    </div>
    @if(($batch->disbursement_type ?? '') === 'monthly_salary')
    <div class="info-row">
      <span class="info-label">1st Half Credit:</span>
      <span class="info-val">{{ $batch->first_half_credit_date?->format('M j, Y') ?? '—' }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">2nd Half Credit:</span>
      <span class="info-val">{{ $batch->second_half_credit_date?->format('M j, Y') ?? '—' }}</span>
    </div>
    @elseif($batch->credit_date ?? null)
    <div class="info-row">
      <span class="info-label">Credit Date:</span>
      <span class="info-val">{{ $batch->credit_date->format('M j, Y') }}</span>
    </div>
    @endif
  </div>

  {{-- ── Two-column body ──────────────────────────────────────────── --}}
  @php
    $fmt = fn($v) => number_format((float)$v, 2);
    $show = fn($v) => (float)$v != 0.0;
    $adj  = $item->adjustments_json ?? [];
    $adjVal = fn(string $k) => (float)($adj[$k] ?? 0);
  @endphp

  <div class="columns">
    {{-- Earnings --}}
    <div class="col">
      <div class="col-header">EARNINGS</div>

      @php $earnings = [
        'Basic Salary'                    => $item->basic_salary,
        'Salary Increase'                 => $item->salary_increase,
        'Additional Compensation'         => $item->additional_compensation,
        'PERA'                            => $item->pera,
        'Others (Bonus/Incentives/CA)'    => $item->others_bonuses,
        'Subsistence and Laundry'         => $item->sala,
        'Hazard Pay'                      => $item->hazard_pay,
        'Longevity Pay'                   => $item->longevity_pay,
      ]; @endphp

      @foreach ($earnings as $label => $amt)
        <div class="line">
          <span class="line-label">{{ $label }}</span>
          <span class="line-amt {{ !$show($amt) ? 'zero' : '' }}">{{ $fmt($amt) }}</span>
        </div>
      @endforeach

      <div class="subtotal">
        <span>GROSS EARNINGS</span>
        <span>{{ $fmt($item->gross_earnings) }}</span>
      </div>
    </div>

    {{-- Deductions --}}
    <div class="col">
      <div class="col-header">DEDUCTIONS</div>

      {{-- Mandatory contributions --}}
      @php $mandatory = [
        'LAWOP/Undertime/Tardiness'       => $item->lawop,
        'PVP Overpayment'                 => $adjVal('pvp_overpayment') ?: $item->pvp_overpayment,
        'GSIS Contributions'              => $item->gsis_contribution,
        'BIR Withholding Tax'             => $item->bir_tax,
        'Withholding Tax Hazard Pay'      => $adjVal('wht_hazard') ?: $item->wht_hazard,
        'Withholding Tax CNA'             => $adjVal('wht_cna') ?: $item->wht_cna,
        'Longevity Pay Tax'               => $adjVal('longevity_tax') ?: $item->longevity_tax,
        'PhilHealth Contribution'         => $item->philhealth_contribution,
        'PhilHealth Differential'         => $adjVal('philhealth_differential') ?: $item->philhealth_differential,
        'HDMF (Pag-ibig) Contribution'    => $item->hdmf_contribution,
        'HDMF Contribution-Additional'    => $adjVal('hdmf_additional') ?: $item->hdmf_additional,
      ]; @endphp

      @foreach ($mandatory as $label => $amt)
        <div class="line">
          <span class="line-label">{{ $label }}</span>
          <span class="line-amt {{ !$show($amt) ? 'zero' : '' }}">{{ $fmt($amt) }}</span>
        </div>
      @endforeach

      {{-- GSIS Loans --}}
      <div class="section-head">GSIS Loans</div>
      @php $gsisLoans = [
        'Salary Loan'        => $item->gsis_salary_loan,
        'Policy Loan'        => $item->gsis_policy_loan,
        'Consolidated Loan'  => $item->gsis_consolidated_loan,
        'Emergency Loan'     => $item->gsis_emergency_loan,
        'MPL'                => $item->gsis_mpl,
        'GFAL'               => $item->gsis_gfal,
        'CPL'                => $item->gsis_cpl,
        'MPL Lite'           => $item->gsis_mpl_lite,
      ]; @endphp
      @foreach ($gsisLoans as $label => $amt)
        <div class="line">
          <span class="line-label">{{ $label }}</span>
          <span class="line-amt {{ !$show($amt) ? 'zero' : '' }}">{{ $fmt($amt) }}</span>
        </div>
      @endforeach

      {{-- HDMF Loans --}}
      <div class="section-head">HDMF Loans</div>
      @php $hdmfLoans = [
        'Salary Loan'     => $item->hdmf_salary_loan,
        'Calamity Loan'   => $item->hdmf_calamity,
        'Housing Loan'    => $item->hdmf_housing,
        'Multi-Purpose'   => $item->hdmf_multipurpose,
        'MP2'             => $item->hdmf_mp2,
      ]; @endphp
      @foreach ($hdmfLoans as $label => $amt)
        <div class="line">
          <span class="line-label">{{ $label }}</span>
          <span class="line-amt {{ !$show($amt) ? 'zero' : '' }}">{{ $fmt($amt) }}</span>
        </div>
      @endforeach

      {{-- Other Loans --}}
      <div class="section-head">Other Loans</div>
      @php $otherLoans = [
        'Landbank Loan'         => $item->landbank_loan,
        'Credit Union/Cooperative' => $adjVal('credit_union') ?: $item->credit_union,
      ]; @endphp
      @foreach ($otherLoans as $label => $amt)
        <div class="line">
          <span class="line-label">{{ $label }}</span>
          <span class="line-amt {{ !$show($amt) ? 'zero' : '' }}">{{ $fmt($amt) }}</span>
        </div>
      @endforeach

      <div class="subtotal">
        <span>TOTAL DEDUCTIONS</span>
        <span>{{ $fmt($item->total_deductions) }}</span>
      </div>
    </div>
  </div>

  {{-- ── Net Pay ─────────────────────────────────────────────────── --}}
  <div class="net-band">
    <span class="net-label">NET PAY</span>
    <span class="net-amount">₱ {{ $fmt($item->net_pay) }}</span>
  </div>

  <div class="halves">
    <div class="half-item">
      <span>Amount Due (1st half):</span>
      <strong>₱ {{ $fmt($item->first_half_amount) }}</strong>
    </div>
    <div class="half-item">
      <span>Amount Due (2nd half):</span>
      <strong>₱ {{ $fmt($item->second_half_amount) }}</strong>
    </div>
  </div>
  <div class="atm-line">Received thru: <strong>ATM</strong></div>

  {{-- ── Signature block ─────────────────────────────────────────── --}}
  <div class="sig-block">
    <div class="sig-box">
      <div class="sig-line"></div>
      <div class="sig-name">{{ $preparedBy?->name ?? '___________________________' }}</div>
      <div class="sig-title">{{ $preparedBy?->position ?? 'Administrative Officer V' }}</div>
      <div class="sig-role-label">Prepared by</div>
    </div>
    <div class="sig-box">
      <div class="sig-line"></div>
      <div class="sig-name">{{ $certifiedBy?->name ?? '___________________________' }}</div>
      <div class="sig-title">{{ $certifiedBy?->position ?? 'Accountant II' }}</div>
      <div class="sig-role-label">Certified Correct</div>
    </div>
  </div>

  <div class="footer-note">
    This is a system-generated payslip. If you spot a discrepancy, contact the Cashier's Office.
  </div>

</div>
</body>
</html>
