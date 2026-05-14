<template>
  <Head :title="`CS Form 6 — ${application.control_no}`" />

  <div id="leave-print-root">
    <table id="leave-pt-wrap">

      <!-- Repeating header on every page -->
      <thead>
        <tr><td id="leave-pt-head">
          <img src="/images/report_header.jpeg" style="width:100%; display:block;" />
        </td></tr>
      </thead>

      <!-- Repeating footer on every page -->
      <tfoot>
        <tr><td id="leave-pt-foot">
          <img src="/images/report_footer.jpeg" style="width:100%; display:block;" />
        </td></tr>
      </tfoot>

      <!-- Body -->
      <tbody>
        <tr><td id="leave-pt-body">

    <div class="form-meta-row">
      <div class="form-meta-left">
        <div>Civil Service Form No. 6</div>
        <div>Revised 2020</div>
      </div>
      <div class="control-no">Control No.: {{ application.control_no }}</div>
    </div>

    <div class="form-title">APPLICATION FOR LEAVE</div>

    <!-- ── Sections 1–5 ───────────────────────────────────────────── -->
    <table class="top-table">
      <tr>
        <td class="lbl" style="width:50%">1. OFFICE/DEPARTMENT</td>
        <td class="lbl" colspan="3">2. NAME:(Last Name) (First Name) (Middle Initial)</td>
      </tr>
      <tr>
        <td class="val">{{ officeDept }}</td>
        <td class="val font-bold" colspan="3">{{ application.user?.name?.toUpperCase() }}</td>
      </tr>
      <tr>
        <td class="lbl-val" style="width:22%">
          <span class="lbl-inline">3. DATE OF FILING:</span>
          <span class="val-inline">{{ fmtDate(application.filed_at ?? application.created_at) }}</span>
        </td>
        <td class="lbl-val" style="width:40%">
          <span class="lbl-inline">4. POSITION:</span>
          <span class="val-inline font-bold" style="text-transform:uppercase">{{ application.user?.position }}</span>
        </td>
        <td class="lbl-val" colspan="2">
          <span class="lbl-inline">5. SALARY:</span>
          <span class="val-inline font-bold">{{ salary }}</span>
        </td>
      </tr>
    </table>

    <!-- ── Section 6 ──────────────────────────────────────────────── -->
    <div class="section-hdr">6. DETAILS OF APPLICATION</div>
    <table class="split-table">
      <colgroup>
        <col style="width:52%">
        <col style="width:48%">
      </colgroup>
      <tr>
        <td class="lbl-cell">6.A TYPE OF LEAVE TO BE AVAILED OF</td>
        <td class="lbl-cell">6.B DETAILS OF LEAVE</td>
      </tr>
      <tr>
        <!-- 6A: Leave type checkboxes -->
        <td class="body-cell" style="vertical-align:top; padding:4px 6px;">
          <div v-for="lt in cscLeaveTypes" :key="lt.code" class="cb-row">
            <span class="cb">{{ isChecked(lt.code) ? '☑' : '☐' }}</span>
            <span :class="{ bold: isChecked(lt.code) }">{{ lt.label }}</span>
          </div>
          <div class="cb-row">
            <span class="cb">{{ isOther ? '☑' : '☐' }}</span>
            <span :class="{ bold: isOther }">Other purpose: <span v-if="isOther" class="underline">{{ application.leave_type?.name }}</span></span>
          </div>
        </td>

        <!-- 6B: Leave details -->
        <td class="body-cell" style="vertical-align:top; padding:4px 6px; font-size:7.5pt;">
          <div class="detail-group">
            <div class="detail-head">In case of Vacation/Special Privilege Leave:</div>
            <div class="cb-row">
              <span class="cb">{{ isDetail('within_philippines') ? '☑' : '☐' }}</span>
              Within the Philippines:
              <span v-if="isDetail('within_philippines')" class="underline">{{ application.leave_details_specify }}</span>
            </div>
            <div class="cb-row">
              <span class="cb">{{ isDetail('abroad') ? '☑' : '☐' }}</span>
              Abroad (Specify):
              <span v-if="isDetail('abroad')" class="underline">{{ application.leave_details_specify }}</span>
            </div>
          </div>
          <div class="detail-group">
            <div class="detail-head">In case of Sick Leave:</div>
            <div class="cb-row">
              <span class="cb">{{ isDetail('in_hospital') ? '☑' : '☐' }}</span>
              In Hospital (Specify Illness):
              <span v-if="isDetail('in_hospital')" class="underline">{{ application.leave_details_specify }}</span>
            </div>
            <div class="cb-row">
              <span class="cb">{{ isDetail('out_patient') ? '☑' : '☐' }}</span>
              Out Patient (Specify Illness):
              <span v-if="isDetail('out_patient')" class="underline">{{ application.leave_details_specify }}</span>
            </div>
          </div>
          <div class="detail-group">
            <div class="detail-head">In case of Special Leave Benefits for Women:</div>
            <div class="cb-row">(Specify Illness):<span class="underline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></div>
          </div>
          <div class="detail-group">
            <div class="detail-head">In case of Study Leave:</div>
            <div class="cb-row">
              <span class="cb">{{ isDetail('master_degree') ? '☑' : '☐' }}</span>
              Completion of Masters Degree:
            </div>
            <div class="cb-row">
              <span class="cb">{{ isDetail('bar_board_review') ? '☑' : '☐' }}</span>
              BAR/Board Examination Review:
            </div>
          </div>
          <div class="detail-group">
            <div class="detail-head">Other Purposes:</div>
            <div class="cb-row"><span class="cb">☐</span> Monetization of Leave Credits</div>
            <div class="cb-row"><span class="cb">☐</span> Terminal Leave</div>
          </div>
        </td>
      </tr>

      <!-- 6C + 6D -->
      <tr>
        <td class="body-cell" style="padding:4px 6px; vertical-align:top;">
          <div class="lbl-sm">6.C NUMBER OF WORKING DAYS APPLIED FOR:</div>
          <div class="days-val">{{ application.days_applied }} day(s)</div>
          <div class="lbl-sm mt-6">INCLUSIVE DATES:</div>
          <div class="days-val">{{ inclusiveDates }}</div>
        </td>
        <td class="body-cell" style="padding:4px 6px; vertical-align:top;">
          <div class="lbl-sm">6.D COMMUTATION</div>
          <div class="cb-row mt-1"><span class="cb">☐</span> Not Requested</div>
          <div class="cb-row"><span class="cb">☐</span> Requested</div>
          <div class="sig-space"></div>
          <img v-if="sigs['submission']?.uri" :src="sigs['submission'].uri" style="max-height:36px;display:block;margin:0 auto 2px;" alt="applicant signature" />
          <div class="sig-line-el"></div>
          <div v-if="sigs['submission']" class="dig-badge-sm">✓ Digitally Signed · {{ sigs['submission'].signed_at ? new Date(sigs['submission'].signed_at).toLocaleString('en-PH') : '' }}</div>
          <div class="sig-lbl">Signature of Applicant</div>
        </td>
      </tr>
    </table>

    <!-- ── Section 7 ──────────────────────────────────────────────── -->
    <div class="section-hdr">7. DETAILS OF ACTION ON APPLICATION</div>
    <table class="split-table">
      <colgroup>
        <col style="width:52%">
        <col style="width:48%">
      </colgroup>
      <tr>
        <td class="lbl-cell">7.A CERTIFICATION OF LEAVE CREDITS</td>
        <td class="lbl-cell">7.B RECOMMENDATION</td>
      </tr>
      <tr>
        <!-- 7A: Credits table -->
        <td class="body-cell" style="vertical-align:top; padding:4px 6px;">
          <div class="lbl-sm">As of: ____________________________</div>
          <table class="credits-tbl">
            <thead>
              <tr>
                <th></th>
                <th>Vacation<br>Leave</th>
                <th>Sick<br>Leave</th>
                <th>Compensatory<br>Overtime<br>Credit(hr)</th>
                <th>Service<br>Credit</th>
                <th>Wellness<br>Leave</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="cr-lbl">Total Earned</td>
                <td>{{ credits['VL']?.earned ?? '' }}</td>
                <td>{{ credits['SL']?.earned ?? '' }}</td>
                <td>{{ credits['COC']?.earned ?? 0 }}</td>
                <td>{{ credits['SC']?.earned ?? 0 }}</td>
                <td>{{ credits['WL']?.earned ?? '' }}</td>
              </tr>
              <tr>
                <td class="cr-lbl">Less this Application</td>
                <td>{{ isChecked('VL') ? application.days_applied : 0 }}</td>
                <td>{{ isChecked('SL') ? application.days_applied : 0 }}</td>
                <td>0</td>
                <td></td>
                <td>{{ isChecked('WL') ? application.days_applied : 0 }}</td>
              </tr>
              <tr>
                <td class="cr-lbl">Balance</td>
                <td>{{ credits['VL']?.balance ?? '' }}</td>
                <td>{{ credits['SL']?.balance ?? '' }}</td>
                <td></td>
                <td></td>
                <td></td>
              </tr>
            </tbody>
          </table>
          <div class="sig-space"></div>
          <img v-if="sigs['hr_officer']?.uri" :src="sigs['hr_officer'].uri" style="max-height:36px;display:block;margin:0 auto 2px;" alt="hr officer signature" />
          <div class="sig-space"></div>
          <div class="mt-6 officer-name">{{ certifyingOfficer?.name?.toUpperCase() ?? '' }}</div>
          <div v-if="sigs['hr_officer']" class="dig-badge-sm">✓ Digitally Signed</div>
          <div class="sig-lbl">(Authorized Officer)</div>
        </td>

        <!-- 7B: Recommendation -->
        <td class="body-cell" style="vertical-align:top; padding:4px 6px;">
          <div class="cb-row mt-1">
            <span class="cb">{{ recForApproval ? '☑' : '☐' }}</span> For approval
          </div>
          <div class="cb-row">
            <span class="cb">{{ recForDisapproval ? '☑' : '☐' }}</span> For disapproval due to :
          </div>
          <div class="rec-remarks" v-if="recForDisapproval">{{ application.division_chief_remarks || application.approval_remarks }}</div>
          <div class="disapprove-line mt-6"></div>
          <div class="disapprove-line mt-6"></div>
          <div class="disapprove-line mt-6"></div>
          <img v-if="sigs['division_chief']?.uri" :src="sigs['division_chief'].uri" style="max-height:36px;display:block;margin:4px auto 2px;" alt="division chief signature" />
          <div class="mt-12 officer-name">{{ authorizedOfficer?.name?.toUpperCase() ?? '' }}</div>
          <div v-if="sigs['division_chief']" class="dig-badge-sm">✓ Digitally Signed</div>
          <div class="sig-lbl">(Authorized Officer)</div>
        </td>
      </tr>

      <!-- 7C + 7D -->
      <tr>
        <td class="body-cell" style="padding:4px 6px; vertical-align:top;">
          <div class="lbl-sm">7.C APPROVED FOR:</div>
          <div class="approved-row">
            <span>{{ withPayDays }}</span> days with pay
          </div>
          <div class="approved-row">
            <span>{{ withoutPayDays }}</span> days without pay
          </div>
          <div class="approved-row">
            <span>_______</span> others (Specify)
          </div>
        </td>
        <td class="body-cell" style="padding:4px 6px; vertical-align:top;">
          <div class="lbl-sm">7.D DISAPPROVED DUE TO:</div>
          <div class="disapprove-line"></div>
          <div class="disapprove-line"></div>
          <div class="disapprove-line"></div>
        </td>
      </tr>

      <!-- Authorized Official — full width inside the table -->
      <tr>
        <td class="body-cell" colspan="2" style="padding:6px 4px; text-align:center; border-top:1px solid #000;">
          <img v-if="sigs['campus_director']?.uri" :src="sigs['campus_director'].uri" style="max-height:36px;display:block;margin:0 auto 2px;" alt="campus director signature" />
          <div class="officer-name">{{ authorizedOfficial?.name?.toUpperCase() ?? '' }}</div>
          <div v-if="sigs['campus_director']" class="dig-badge-sm">✓ Digitally Signed</div>
          <div class="sig-lbl">(Authorized Official)</div>
        </td>
      </tr>
    </table>

        </td></tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  application:        Object,
  credits:            Object,  // { VL: {earned,used,balance}, SL: {...}, ... }
  certifyingOfficer:  Object,  // 7.A — certifies leave credits
  authorizedOfficer:  Object,  // 7.B — Division Chief where user belongs
  authorizedOfficial: Object,  // bottom — Campus Director / Head of Agency
  monthlySalary:      { type: String, default: null },
  sigs:               { type: Object, default: () => ({}) },
})

// ── CSC Form 6 leave type definitions (order matches the official form) ──────
const cscLeaveTypes = [
  { code: 'VL',      label: 'Vacation Leave',                        ref: '(Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292)'  },
  { code: 'FL',      label: 'Mandatory/Forced Leave',                ref: '(Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292)'  },
  { code: 'SL',      label: 'Sick Leave',                            ref: '(Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292)'  },
  { code: 'ML',      label: 'Maternity Leave',                       ref: '(R.A. No. 11210 / IRR issued by CSC, DOLE and SSS)'           },
  { code: 'PL',      label: 'Paternity Leave',                       ref: '(R.A. No. 8187 / CSC MC No. 71, s. 1998, as amended)'         },
  { code: 'SPL',     label: 'Special Privilege Leave',               ref: '(Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292)'  },
  { code: 'SOLO',    label: 'Solo Parent Leave',                     ref: '(RA No. 8972 / CSC MC No. 8, s. 2004)'                        },
  { code: 'STUDY',   label: 'Study Leave',                           ref: '(Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292)'  },
  { code: 'VAWC',    label: '10-Day VAWC Leave',                     ref: '(RA No. 9262 / CSC MC No. 15, s. 2005)'                       },
  { code: 'REHAB',   label: 'Rehabilitation Privilege',              ref: '(Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292)'  },
  { code: 'SLW',     label: 'Special Leave Benefits for Women',      ref: '(RA No. 9710 / CSC MC No. 25, s. 2010)'                       },
  { code: 'CL',      label: 'Special Emergency (Calamity) Leave',    ref: '(CSC MC No. 2, s. 2012, as amended)'                          },
  { code: 'AL',      label: 'Adoption Leave',                        ref: '(R.A. No. 8552)'                                              },
]

// Name-based fallback matching for codes not stored exactly as above
const NAME_HINTS = {
  VL:    ['vacation'],
  FL:    ['mandatory', 'forced'],
  SL:    ['sick'],
  ML:    ['maternity'],
  PL:    ['paternity'],
  SPL:   ['special privilege'],
  SOLO:  ['solo parent'],
  STUDY: ['study leave'],
  VAWC:  ['vawc', 'violence against women'],
  REHAB: ['rehabilitation'],
  SLW:   ['leave benefits for women', 'slb', 'slw'],
  CL:    ['calamity', 'emergency'],
  AL:    ['adoption'],
  WL:    ['wellness'],
}

function isChecked(cscCode) {
  const appCode = (props.application.leave_type?.code ?? '').toUpperCase()
  if (appCode === cscCode) return true
  const appName = (props.application.leave_type?.name ?? '').toLowerCase()
  return (NAME_HINTS[cscCode] ?? []).some(h => appName.includes(h))
}

const isOther = computed(() => !cscLeaveTypes.some(lt => isChecked(lt.code)))

function isDetail(key) {
  return props.application.leave_details === key
}

// ── Derived display values ─────────────────────────────────────────────────
const officeDept = computed(() => {
  const u = props.application.user
  // Try division name first, then position office
  return u?.division?.division_name ?? u?.office?.name ?? 'Office of the Campus Director'
})

const salary = computed(() => {
  if (props.monthlySalary) return props.monthlySalary
  const u = props.application.user
  const s = u?.salary ?? u?.employeeProfile?.monthly_salary
  if (!s) return ''
  return Number(s).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
})

const inclusiveDates = computed(() => {
  const from = props.application.date_from
  const to   = props.application.date_to
  if (!from) return ''
  const fmtShort = d => {
    const dt = new Date(d + 'T00:00:00')
    return dt.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
  }
  return from === to ? fmtShort(from) : `${fmtShort(from)} to ${fmtShort(to)}`
})

const recForApproval    = computed(() => ['approved', 'forwarded'].includes(props.application.status))
const recForDisapproval = computed(() => props.application.status === 'rejected')

const withPayDays    = computed(() =>
  props.application.status === 'approved' && !props.application.is_without_pay
    ? props.application.days_applied : '_______'
)
const withoutPayDays = computed(() =>
  props.application.status === 'approved' && props.application.is_without_pay
    ? props.application.days_applied : '_______'
)

function fmtDate(d) {
  if (!d) return ''
  const dt = new Date(d)
  if (isNaN(dt.getTime())) return ''
  return dt.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

onMounted(() => setTimeout(() => window.print(), 500))
</script>

<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #fff; font-family: Arial, Helvetica, sans-serif; }

#leave-print-root {
  font-size: 8.5pt;
  color: #000;
}

#leave-pt-wrap {
  width: 100%;
  border-collapse: collapse;
}

#leave-pt-head,
#leave-pt-foot {
  padding: 0 0.75in;
}

#leave-pt-body {
  padding: 10px 0.75in;
  vertical-align: top;
}

/* ── Meta row ─────────────────────────────────────────────────────── */
.form-meta-row {
  display: flex; justify-content: space-between;
  font-size: 7pt; margin-bottom: 2px;
}
.control-no { font-weight: bold; }
.form-title {
  text-align: center; font-size: 11pt; font-weight: bold;
  padding: 4px 0; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000;
  margin-bottom: 0;
}

/* ── Top table (sections 1–5) ─────────────────────────────────────── */
.top-table {
  width: 100%; border-collapse: collapse;
  margin-bottom: 0;
}
.top-table td { border: 1px solid #000; padding: 2px 4px; }
.lbl { font-size: 7.5pt; font-weight: normal; background: #f5f5f5; }
.val { font-size: 8.5pt; font-weight: 800; min-height: 16px; }
.font-bold { font-weight: 800; }
.lbl-val { padding: 2px 4px; white-space: nowrap; }
.lbl-inline { font-size: 7.5pt; font-weight: normal; margin-right: 3px; }
.val-inline { font-size: 8.5pt; font-weight: 800; }

/* ── Section header ───────────────────────────────────────────────── */
.section-hdr {
  background: #000; color: #fff;
  font-size: 8pt; font-weight: bold;
  text-align: center; padding: 2px 4px;
}

/* ── Split table (6, 7) ───────────────────────────────────────────── */
.split-table {
  width: 100%; border-collapse: collapse;
  margin-bottom: 0;
}
.split-table td { border: 1px solid #000; }
.lbl-cell {
  font-size: 7.5pt; font-weight: bold;
  background: #f5f5f5; padding: 2px 4px;
}
.body-cell { padding: 0; }

/* ── Checkboxes ───────────────────────────────────────────────────── */
.cb-row {
  display: flex; align-items: flex-start; gap: 3px;
  margin-bottom: 1.5px; font-size: 7.5pt; line-height: 1.3;
}
.cb { font-size: 8.5pt; flex-shrink: 0; }
.bold { font-weight: bold; }
.underline { text-decoration: underline; }

/* ── Leave detail groups (6B) ─────────────────────────────────────── */
.detail-group { margin-bottom: 4px; }
.detail-head  { font-size: 7pt; font-style: italic; margin-bottom: 1px; }

/* ── 6C ───────────────────────────────────────────────────────────── */
.lbl-sm   { font-size: 7.5pt; font-weight: bold; }
.days-val { font-size: 9pt; font-weight: bold; padding: 1px 0 2px 8px; }
.mt-1  { margin-top: 4px; }
.mt-2  { margin-top: 8px; }
.mt-3  { margin-top: 12px; }
.mt-6  { margin-top: 22px; }
.mt-12 { margin-top: 44px; }
.sig-space    { height: 28px; }
.sig-line-el  { border-bottom: 1px solid #000; width: 100%; display: block; margin-top: 2px; }
.sig-lbl { font-size: 7pt; text-align: center; margin-top: 1px; }
.dig-badge-sm { font-size:7pt; color:#166534; background:#f0fdf4; border:1px solid #86efac; border-radius:2px; padding:1px 4px; margin:2px auto; display:inline-block; }
.rec-remarks { font-size: 7pt; color: #555; font-style: italic; margin: 2px 0; }

/* ── 7A credits table ─────────────────────────────────────────────── */
.credits-tbl {
  width: 100%; border-collapse: collapse;
  font-size: 7pt; margin-top: 3px;
}
.credits-tbl th, .credits-tbl td {
  border: 1px solid #555;
  padding: 1.5px 2px;
  text-align: center;
  line-height: 1.2;
}
.credits-tbl th { background: #eee; font-weight: bold; font-size: 6.5pt; }
.cr-lbl { text-align: left; font-style: italic; font-size: 7pt; }

/* ── Officer names ────────────────────────────────────────────────── */
.officer-name {
  text-align: center; font-weight: bold;
  font-size: 8.5pt; text-decoration: underline;
}

/* ── 7C approved rows ─────────────────────────────────────────────── */
.approved-row { font-size: 8pt; margin-bottom: 3px; display: flex; align-items: baseline; gap: 4px; }
.approved-blank { display: inline-block; min-width: 40px; border-bottom: 1px solid #000; text-align: center; }

/* ── 7D disapproval lines ─────────────────────────────────────────── */
.disapprove-line {
  border-bottom: 1px solid #000;
  width: 100%;
  display: block;
  margin-top: 18px;
}

/* ── Print overrides ──────────────────────────────────────────────── */
@page { margin: 0.25in 0 0 0; }

@media print {
  body { margin: 0; }
}
</style>
