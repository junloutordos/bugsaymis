<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 8pt;
    color: #000;
    line-height: 1.3;
  }

  /* ── Page layout ───────────────────────────────────────────── */
  .page { width: 100%; }

  /* ── Header ────────────────────────────────────────────────── */
  .header-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
  .header-logo  { width: 60px; text-align: center; vertical-align: middle; }
  .header-logo img { width: 50px; height: auto; }
  .header-text  { text-align: center; vertical-align: middle; }
  .header-text .agency { font-size: 8pt; font-weight: bold; text-transform: uppercase; }
  .header-text .form-title { font-size: 11pt; font-weight: bold; margin-top: 3px; letter-spacing: 0.5px; }
  .header-text .form-subtitle { font-size: 7.5pt; }
  .header-text .law  { font-size: 7pt; color: #444; }
  .header-right { width: 100px; text-align: right; vertical-align: top; font-size: 7pt; }

  /* ── Section divider ───────────────────────────────────────── */
  .section-header {
    background: #1a1a2e;
    color: #fff;
    font-size: 7.5pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 3px 6px;
    margin-top: 5px;
    margin-bottom: 2px;
  }

  /* ── Data tables ───────────────────────────────────────────── */
  .data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7.5pt;
    margin-bottom: 3px;
  }
  .data-table th {
    background: #f0f0f0;
    border: 0.5pt solid #bbb;
    padding: 2px 4px;
    text-align: center;
    font-size: 7pt;
    font-weight: bold;
  }
  .data-table td {
    border: 0.5pt solid #bbb;
    padding: 2px 4px;
    vertical-align: top;
  }
  .data-table .num { text-align: right; }
  .data-table .ctr { text-align: center; }
  .data-table .total-row td {
    background: #f8f8f8;
    font-weight: bold;
  }

  /* ── Info grid ─────────────────────────────────────────────── */
  .info-table { width: 100%; border-collapse: collapse; font-size: 7.5pt; margin-bottom: 4px; }
  .info-table td { padding: 2px 4px; vertical-align: top; }
  .info-table .lbl { font-weight: bold; width: 130px; white-space: nowrap; color: #333; }
  .info-table .val { border-bottom: 0.5pt solid #aaa; }

  /* ── Net worth summary bar ─────────────────────────────────── */
  .nw-table { width: 100%; border-collapse: collapse; margin: 4px 0; font-size: 8pt; }
  .nw-table td { border: 0.5pt solid #999; padding: 3px 6px; text-align: center; }
  .nw-table .nw-label { font-size: 6.5pt; color: #555; text-transform: uppercase; letter-spacing: 0.4px; }
  .nw-table .nw-value { font-weight: bold; font-size: 9pt; margin-top: 1px; }
  .nw-table .nw-assets { background: #eef6ff; }
  .nw-table .nw-liab   { background: #fff5f5; }
  .nw-table .nw-nw     { background: #f0fff4; }

  /* ── Declaration box ───────────────────────────────────────── */
  .declaration {
    border: 0.5pt solid #999;
    padding: 5px 8px;
    font-size: 7pt;
    margin-top: 6px;
    background: #fffdf0;
  }

  /* ── Signature area ────────────────────────────────────────── */
  .sig-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 7.5pt; }
  .sig-table td { padding: 2px 8px; vertical-align: bottom; }
  .sig-line { border-top: 0.5pt solid #000; margin-top: 18px; padding-top: 2px; text-align: center; }
  .sig-lbl  { font-size: 6.5pt; color: #555; text-align: center; }

  /* ── Footer ────────────────────────────────────────────────── */
  .footer { font-size: 6.5pt; color: #888; text-align: center; margin-top: 8px; border-top: 0.5pt solid #ccc; padding-top: 3px; }

  /* ── Empty notice ──────────────────────────────────────────── */
  .empty-notice { font-size: 7pt; color: #999; font-style: italic; padding: 3px 4px; }

  hr { border: none; border-top: 0.5pt solid #ccc; margin: 4px 0; }
</style>
</head>
<body>
<div class="page">

  {{-- ── HEADER ──────────────────────────────────────────────────────── --}}
  <table class="header-table">
    <tr>
      <td class="header-text">
        <div class="agency">{{ config('app.agency_name', 'Government Agency') }}</div>
        <div class="form-title">STATEMENT OF ASSETS, LIABILITIES AND NET WORTH</div>
        <div class="form-subtitle">(SALN) — CSC Form No. 1</div>
        <div class="law">Pursuant to Republic Act 6713 (Code of Conduct and Ethical Standards for Public Officials and Employees)</div>
      </td>
      <td class="header-right">
        <div><strong>SALN Year:</strong> {{ $saln->year }}</div>
        <div>As of: {{ \Carbon\Carbon::parse($saln->as_of_date)->format('F d, Y') }}</div>
        <div style="margin-top:4px;font-size:6.5pt;color:#555;">Status: <strong>{{ strtoupper($saln->status) }}</strong></div>
        @if($saln->filed_at)
        <div style="font-size:6.5pt;color:#555;">Filed: {{ $saln->filed_at->format('m/d/Y') }}</div>
        @endif
      </td>
    </tr>
  </table>

  <hr />

  {{-- ── FILER INFORMATION ───────────────────────────────────────────── --}}
  <div class="section-header">I. Personal Information</div>
  <table class="info-table">
    <tr>
      <td class="lbl">Name:</td>
      <td class="val">{{ strtoupper($saln->user->name) }}</td>
      <td class="lbl">Position / Designation:</td>
      <td class="val">{{ $saln->user->position ?? '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">Agency / Office:</td>
      <td class="val" colspan="3">{{ config('app.agency_name', '—') }}</td>
    </tr>
    @if($saln->spouse_name)
    <tr>
      <td class="lbl">Spouse's Name:</td>
      <td class="val">{{ strtoupper($saln->spouse_name) }}</td>
      <td class="lbl">Spouse's Position:</td>
      <td class="val">{{ $saln->spouse_position ?? '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">Spouse's Office/Employer:</td>
      <td class="val">{{ $saln->spouse_office ?? '—' }}</td>
      <td class="lbl">Spouse's Gov't ID:</td>
      <td class="val">{{ $saln->spouse_government_id ?? '—' }}</td>
    </tr>
    @else
    <tr>
      <td class="lbl">Spouse:</td>
      <td class="val" colspan="3">None / Not Applicable</td>
    </tr>
    @endif
  </table>

  {{-- ── NET WORTH SUMMARY ──────────────────────────────────────────── --}}
  <table class="nw-table">
    <tr>
      <td class="nw-assets">
        <div class="nw-label">Total Real Properties</div>
        <div class="nw-value">{{ number_format($saln->total_real_properties, 2) }}</div>
      </td>
      <td class="nw-assets">
        <div class="nw-label">Total Personal Properties</div>
        <div class="nw-value">{{ number_format($saln->total_personal_properties, 2) }}</div>
      </td>
      <td class="nw-assets">
        <div class="nw-label">Total Assets</div>
        <div class="nw-value">{{ number_format($saln->total_assets, 2) }}</div>
      </td>
      <td class="nw-liab">
        <div class="nw-label">Total Liabilities</div>
        <div class="nw-value">{{ number_format($saln->total_liabilities, 2) }}</div>
      </td>
      <td class="nw-nw">
        <div class="nw-label">Net Worth</div>
        <div class="nw-value" style="{{ $saln->net_worth < 0 ? 'color:#c00;' : 'color:#1a6b3a;' }}">
          {{ $saln->net_worth < 0 ? '(' . number_format(abs($saln->net_worth), 2) . ')' : number_format($saln->net_worth, 2) }}
        </div>
      </td>
    </tr>
  </table>

  {{-- ── SECTION A: REAL PROPERTIES ───────────────────────────────────── --}}
  <div class="section-header">A. Real Properties</div>
  @if($saln->realProperties->isEmpty())
    <p class="empty-notice">No real properties declared.</p>
  @else
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:20%">Description / Kind</th>
        <th style="width:25%">Exact Location</th>
        <th style="width:12%">Assessed Value</th>
        <th style="width:13%">Fair Market Value</th>
        <th style="width:8%">Year Acq.</th>
        <th style="width:14%">Mode of Acquisition</th>
        <th style="width:8%">Owner</th>
      </tr>
    </thead>
    <tbody>
      @foreach($saln->realProperties as $p)
      <tr>
        <td>{{ $p->kind }}</td>
        <td>{{ $p->exact_location }}</td>
        <td class="num">{{ $p->assessed_value ? number_format($p->assessed_value, 2) : '—' }}</td>
        <td class="num">{{ number_format($p->current_fair_market_value, 2) }}</td>
        <td class="ctr">{{ $p->year_acquired ?? '—' }}</td>
        <td>{{ ucfirst(str_replace('_', ' ', $p->acquisition_mode ?? '—')) }}{{ $p->acquisition_mode_other ? ' (' . $p->acquisition_mode_other . ')' : '' }}</td>
        <td class="ctr">{{ ucfirst($p->owner) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr class="total-row">
        <td colspan="3" style="text-align:right">Sub-total Real Properties:</td>
        <td class="num">{{ number_format($saln->total_real_properties, 2) }}</td>
        <td colspan="3"></td>
      </tr>
    </tfoot>
  </table>
  @endif

  {{-- ── SECTION B: PERSONAL PROPERTIES ──────────────────────────────── --}}
  <div class="section-header">B. Personal Properties</div>
  @if($saln->personalProperties->isEmpty())
    <p class="empty-notice">No personal properties declared.</p>
  @else
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:30%">Description</th>
        <th style="width:20%">Category</th>
        <th style="width:10%">Year Acq.</th>
        <th style="width:18%">Acquisition Cost</th>
        <th style="width:10%">Owner</th>
      </tr>
    </thead>
    <tbody>
      @foreach($saln->personalProperties as $p)
      <tr>
        <td>{{ $p->description }}</td>
        <td>{{ ucwords(str_replace('_', ' ', $p->category)) }}</td>
        <td class="ctr">{{ $p->year_acquired ?? '—' }}</td>
        <td class="num">{{ number_format($p->acquisition_cost, 2) }}</td>
        <td class="ctr">{{ ucfirst($p->owner) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr class="total-row">
        <td colspan="3" style="text-align:right">Sub-total Personal Properties:</td>
        <td class="num">{{ number_format($saln->total_personal_properties, 2) }}</td>
        <td></td>
      </tr>
    </tfoot>
  </table>
  @endif

  {{-- ── SECTION C: LIABILITIES ───────────────────────────────────────── --}}
  <div class="section-header">C. Liabilities</div>
  @if($saln->liabilities->isEmpty())
    <p class="empty-notice">No liabilities declared.</p>
  @else
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:35%">Nature of Liability</th>
        <th style="width:35%">Name of Creditor</th>
        <th style="width:30%">Outstanding Balance</th>
      </tr>
    </thead>
    <tbody>
      @foreach($saln->liabilities as $l)
      <tr>
        <td>{{ ucwords(str_replace('_', ' ', $l->nature)) }}{{ $l->nature_other ? ' — ' . $l->nature_other : '' }}</td>
        <td>{{ $l->creditor_name }}</td>
        <td class="num">{{ number_format($l->outstanding_balance, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr class="total-row">
        <td colspan="2" style="text-align:right">Total Liabilities:</td>
        <td class="num">{{ number_format($saln->total_liabilities, 2) }}</td>
      </tr>
    </tfoot>
  </table>
  @endif

  {{-- ── SECTION D: BUSINESS INTERESTS ───────────────────────────────── --}}
  <div class="section-header">D. Business Interests and Financial Connections</div>
  @if($saln->businessInterests->isEmpty())
    <p class="empty-notice">None declared.</p>
  @else
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:25%">Name of Entity</th>
        <th style="width:25%">Business Address</th>
        <th style="width:18%">Nature of Business</th>
        <th style="width:12%">Date Acquired</th>
        <th style="width:12%">FMV / Cost</th>
        <th style="width:8%">Owner</th>
      </tr>
    </thead>
    <tbody>
      @foreach($saln->businessInterests as $b)
      <tr>
        <td>{{ $b->entity_name }}</td>
        <td>{{ $b->business_address ?? '—' }}</td>
        <td>{{ $b->nature_of_business ?? '—' }}</td>
        <td class="ctr">{{ $b->date_acquired ? \Carbon\Carbon::parse($b->date_acquired)->format('m/Y') : '—' }}</td>
        <td class="num">{{ $b->present_fair_market_value ? number_format($b->present_fair_market_value, 2) : '—' }}</td>
        <td class="ctr">{{ ucfirst($b->owner) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  {{-- ── SECTION E: RELATIVES IN GOVERNMENT ──────────────────────────── --}}
  <div class="section-header">E. Relatives in Government Service (within 4th Civil Degree)</div>
  @if($saln->relatives->isEmpty())
    <p class="empty-notice">None declared.</p>
  @else
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:28%">Name</th>
        <th style="width:18%">Relationship</th>
        <th style="width:20%">Position</th>
        <th style="width:22%">Agency / Office</th>
        <th style="width:12%">Address</th>
      </tr>
    </thead>
    <tbody>
      @foreach($saln->relatives as $r)
      <tr>
        <td>{{ strtoupper($r->name) }}</td>
        <td>{{ $r->relationship }}</td>
        <td>{{ $r->position ?? '—' }}</td>
        <td>{{ $r->agency_office }}</td>
        <td>{{ $r->agency_address ?? '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  {{-- ── DECLARATION ──────────────────────────────────────────────────── --}}
  <div class="declaration">
    <strong>DECLARATION:</strong> I hereby certify that I have fully disclosed all my assets, liabilities and net worth
    in accordance with the provisions of Republic Act No. 6713. This SALN truly reflects the true and complete financial
    condition of the undersigned as of {{ \Carbon\Carbon::parse($saln->as_of_date)->format('F d, Y') }}.
    I understand that any false statement shall be punishable under the law.
  </div>

  {{-- ── SIGNATURE BLOCK ──────────────────────────────────────────────── --}}
  <table class="sig-table">
    <tr>
      <td style="width:45%">
        <div class="sig-line">{{ strtoupper($saln->user->name) }}</div>
        <div class="sig-lbl">Signature over Printed Name of Declarant</div>
      </td>
      <td style="width:10%"></td>
      <td style="width:45%">
        <div class="sig-line"></div>
        <div class="sig-lbl">Administered / Subscribed before me (Administering Officer)</div>
      </td>
    </tr>
    <tr>
      <td style="padding-top:8px;">
        <div class="sig-line"></div>
        <div class="sig-lbl">Date Signed</div>
      </td>
      <td></td>
      @if($saln->filed_at || $saln->approved_at)
      <td style="padding-top:8px;">
        <div class="sig-line">
          @if($saln->filer){{ strtoupper($saln->filer->name) }}@else — @endif
        </div>
        <div class="sig-lbl">HR Officer / SALN Receiving Officer</div>
      </td>
      @endif
    </tr>
  </table>

  {{-- ── FOOTER ───────────────────────────────────────────────────────── --}}
  <div class="footer">
    CSC Form No. SALN-1 — Generated by {{ config('app.name') }} &bull;
    Printed: {{ now()->format('F d, Y \a\t h:i A') }} &bull;
    SALN Year: {{ $saln->year }}
  </div>

</div>
</body>
</html>
