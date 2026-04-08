<template>
  <Head :title="`CS Form 6 — ${application.control_no}`" />

  <div class="page">

    <!-- ── Header ─────────────────────────────────────────────────── -->
    <div class="form-header">
      <div class="logo-area">
        <!-- School seal placeholder -->
        <div class="seal-circle">PSHS</div>
      </div>
      <div class="school-info">
        <div class="school-name">PHILIPPINE SCIENCE HIGH SCHOOL</div>
        <div class="school-sub">CARAGA REGION CAMPUS IN BUTUAN CITY</div>
        <div class="school-dept">DEPARTMENT OF SCIENCE AND TECHNOLOGY</div>
        <div class="school-slogan">OneDOST4U: Solutions and Opportunities for All</div>
      </div>
      <div class="bagong-area">
        <div class="bagong-box">BAGONG<br>PILIPINAS</div>
      </div>
    </div>

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
        <td class="lbl" style="width:22%">3. DATE OF FILING:</td>
        <td class="lbl" style="width:40%">4. POSITION:</td>
        <td class="lbl" colspan="2">5. SALARY:</td>
      </tr>
      <tr>
        <td class="val">{{ fmtDate(application.filed_at ?? application.created_at) }}</td>
        <td class="val">{{ application.user?.position }}</td>
        <td class="val" colspan="2">{{ salary }}</td>
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
          <div class="sig-space"></div>
          <div class="sig-line-el"></div>
          <div class="sig-lbl">Signature of Applicant</div>
        </td>
        <td class="body-cell" style="padding:4px 6px; vertical-align:top;">
          <div class="lbl-sm">6.D COMMUTATION</div>
          <div class="cb-row mt-1"><span class="cb">☐</span> Not Requested</div>
          <div class="cb-row"><span class="cb">☐</span> Requested</div>
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
          <div class="mt-6 officer-name">{{ certifyingOfficer?.name?.toUpperCase() ?? '' }}</div>
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
          <div class="sig-line-el mt-12"></div>
          <div class="sig-lbl">Authorized Officer</div>
        </td>
      </tr>

      <!-- 7C + 7D -->
      <tr>
        <td class="body-cell" style="padding:4px 6px; vertical-align:top;">
          <div class="lbl-sm">7.C APPROVED FOR:</div>
          <div class="approved-row">
            <span class="approved-blank">{{ withPayDays }}</span> days with pay
          </div>
          <div class="approved-row">
            <span class="approved-blank">{{ withoutPayDays }}</span> days without pay
          </div>
          <div class="approved-row">
            <span class="approved-blank">_______</span> others (Specify)
          </div>
        </td>
        <td class="body-cell" style="padding:4px 6px; vertical-align:top;">
          <div class="lbl-sm">7.D DISAPPROVED DUE TO:</div>
          <div class="sig-line-el mt-2"></div>
          <div class="sig-line-el mt-3"></div>
          <div class="sig-line-el mt-3"></div>
        </td>
      </tr>
    </table>

    <!-- Authorized Official (full width) -->
    <div class="auth-official">
      <div class="officer-name">{{ authorizedOfficial?.name?.toUpperCase() ?? '' }}</div>
      <div class="sig-lbl">(Authorized Official)</div>
    </div>

  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  application:        Object,
  credits:            Object,  // { VL: {earned,used,balance}, SL: {...}, ... }
  certifyingOfficer:  Object,
  authorizedOfficial: Object,
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
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

onMounted(() => setTimeout(() => window.print(), 500))
</script>

<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #fff; font-family: Arial, Helvetica, sans-serif; }

.page {
  width: 210mm;
  min-height: 297mm;
  margin: 0 auto;
  padding: 8mm 10mm;
  font-size: 8.5pt;
  color: #000;
}

/* ── Header ───────────────────────────────────────────────────────── */
.form-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 3px;
}
.seal-circle {
  width: 44px; height: 44px;
  border: 2px solid #000;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 7pt; font-weight: bold;
  flex-shrink: 0;
}
.school-info { flex: 1; text-align: center; }
.school-name  { font-size: 10pt; font-weight: bold; }
.school-sub   { font-size: 8pt;  font-weight: bold; }
.school-dept  { font-size: 7.5pt; }
.school-slogan{ font-size: 6.5pt; font-style: italic; color: #444; }
.bagong-box {
  width: 44px; height: 44px;
  border: 2px solid #c00;
  display: flex; align-items: center; justify-content: center;
  font-size: 6pt; font-weight: bold; text-align: center; line-height: 1.2;
  color: #c00; flex-shrink: 0;
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
.lbl { font-size: 7.5pt; font-weight: bold; background: #f5f5f5; }
.val { font-size: 8.5pt; min-height: 16px; }
.font-bold { font-weight: bold; }

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

/* ── Authorized official ──────────────────────────────────────────── */
.auth-official {
  text-align: center; padding: 6px 0 2px;
  border-top: 1px solid #000; margin-top: -1px;
}

/* ── Print overrides ──────────────────────────────────────────────── */
@media print {
  @page { size: A4 portrait; margin: 8mm 10mm; }
  html, body { background: #fff; }
  .page { margin: 0; padding: 0; width: 100%; }
}
</style>
