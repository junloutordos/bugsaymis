@extends('alp.forms._layout')
@php
    $data = $report->data ?? [];
    $peso = fn ($amount) => 'PHP '.number_format((float) $amount, 2);
    $income = data_get($data, 'income', []);
    $expenses = data_get($data, 'expenses', []);
@endphp
@section('content')
@include('alp.forms._header', [
    'title' => 'FINANCIAL REPORT',
    'periodLine' => 'S.Y. '.$cycle->schoolYear->name,
])

<p class="field-line">NAME OF ALP: <span class="fill">{{ $cycle->program->name }}</span></p>

<h2>I. Operating Funds Available <span style="font-weight:normal;font-size:9pt">(as of previous School Year if applicable)</span> {{ $peso(data_get($data, 'opening_balance', 0)) }}</h2>

<h2>II. Operating Income (Current School Year): {{ $peso(data_get($data, 'total_income', 0)) }}</h2>
<p style="font-style:italic;font-size:9pt">Report all sources of generated income</p>
<table class="bordered">
    <thead><tr><th>Category</th><th>Description</th><th style="text-align:right">Amount</th></tr></thead>
    <tbody>
        <tr><td>Membership Fees <span style="font-weight:normal">(amount collected from the members if applicable)</span></td><td>{{ data_get($income, 'membership_fees.description') }}</td><td style="text-align:right">{{ $peso(data_get($income, 'membership_fees.amount', 0)) }}</td></tr>
        <tr><td>Income Generating Activities <span style="font-weight:normal">(if applicable)</span></td><td>{{ data_get($income, 'income_generating.description') }}</td><td style="text-align:right">{{ $peso(data_get($income, 'income_generating.amount', 0)) }}</td></tr>
        <tr><td>Other Sources of Income <span style="font-weight:normal">(donations, grants, subsidies, etc., if applicable)</span></td><td>{{ data_get($income, 'other_sources.description') }}</td><td style="text-align:right">{{ $peso(data_get($income, 'other_sources.amount', 0)) }}</td></tr>
    </tbody>
</table>
<p style="text-align:right"><strong>TOTAL OPERATING INCOME: {{ $peso(data_get($data, 'total_income', 0)) }}</strong></p>

<h2>III. Operating Expenses</h2>
<p style="font-style:italic;font-size:9pt">Provide detailed expense incurred during the current year</p>
<table class="bordered">
    <thead><tr><th>Category</th><th>Description</th><th style="text-align:right">Amount</th></tr></thead>
    <tbody>
        <tr><td>A. Sponsored Activity Expenses <span style="font-weight:normal">(incurred from events and activities sponsored by ALP)</span></td><td>{{ data_get($expenses, 'sponsored_activity.description') }}</td><td style="text-align:right">{{ $peso(data_get($expenses, 'sponsored_activity.amount', 0)) }}</td></tr>
        <tr><td>B. Administrative/Operational Expenses <span style="font-weight:normal">(materials, communication, transportation, etc.)</span></td><td>{{ data_get($expenses, 'administrative.description') }}</td><td style="text-align:right">{{ $peso(data_get($expenses, 'administrative.amount', 0)) }}</td></tr>
        <tr><td>C. Other Expenses <span style="font-weight:normal">(specify any additional allowable expenses)</span></td><td>{{ data_get($expenses, 'other.description') }}</td><td style="text-align:right">{{ $peso(data_get($expenses, 'other.amount', 0)) }}</td></tr>
    </tbody>
</table>
<p style="text-align:right"><strong>TOTAL OPERATING EXPENSES: {{ $peso(data_get($data, 'total_expenses', 0)) }}</strong></p>

<h2>IV. Operating Funds Available (end of the present school year)</h2>
<p>(Beginning Balance + Total Income - Total Expenses): <strong>{{ $peso(data_get($data, 'ending_balance', 0)) }}</strong></p>

@include('alp.forms._signatures', ['rows' => [
    [
        ['action' => 'Prepared by:', 'name' => $signatories['treasurer'] ?? null, 'role' => 'ALP Treasurer', 'date' => true],
        ['action' => 'Certified by:', 'name' => $signatories['president'] ?? null, 'role' => 'ALP President', 'date' => true],
    ],
    [
        ['action' => 'Noted by:', 'name' => $signatories['adviser'] ?? null, 'role' => 'ALP Adviser', 'date' => true],
        ['action' => 'Submitted to:', 'name' => null, 'role' => 'Assistant CID Chief for Student Affairs/DSA Chief', 'date' => true],
    ],
]])
@endsection
