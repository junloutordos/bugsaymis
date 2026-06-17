<template>
  <Head title="Faculty Loading — Print" />

  <div id="fl-print-root">
    <template v-for="(load, idx) in loads" :key="load.id">

      <table class="fl-pt-wrap">

        <!-- Repeating header on every page -->
        <thead>
          <tr><td class="fl-pt-head">
            <img src="/images/report_header.jpeg" style="width:100%; display:block;" />
          </td></tr>
        </thead>

        <!-- Repeating footer on every page -->
        <tfoot>
          <tr><td class="fl-pt-foot">
            <img src="/images/report_footer.jpeg" style="width:100%; display:block;" />
          </td></tr>
        </tfoot>

        <tbody>
          <tr><td class="fl-pt-body">

            <!-- Date (right-aligned) -->
            <div class="fl-date">{{ today }}</div>

            <!-- Faculty info block -->
            <table class="fl-info-table">
              <tr>
                <td><strong>Name of Faculty:</strong>&nbsp;{{ load.faculty?.name?.toUpperCase() }}</td>
                <td class="fl-pos">{{ load.faculty?.position }}</td>
              </tr>
              <tr>
                <td colspan="2"><strong>Academic Unit:</strong>&nbsp;{{ load.academic_unit_name ?? '—' }}</td>
              </tr>
            </table>

            <!-- Document subject -->
            <p class="fl-subject">
              <strong>SUBJECT: Individual Faculty Loading for S.Y. {{ load.term?.school_year }}</strong>
            </p>

            <!-- Greeting -->
            <p class="fl-greeting">
              Greetings! Please be informed that your workloads for
              <strong>{{ load.term?.start_date }}</strong> to <strong>{{ load.term?.end_date }}</strong>
              are the following:
            </p>

            <!-- Load assignments table -->
            <table class="fl-load-table">
              <thead>
                <tr>
                  <th class="fl-col-type">Load Type</th>
                  <th class="fl-col-assignment">Assignment</th>
                  <th class="fl-col-units">Units</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(group, type) in groupedAssignments(load.assignments)" :key="type">
                  <tr v-for="(a, i) in group" :key="a.id" class="fl-assignment-row">
                    <td v-if="i === 0" :rowspan="group.length" class="fl-col-type fl-type-cell">
                      {{ typeLabel(type) }}
                    </td>
                    <td class="fl-col-assignment">{{ a.display_label }}</td>
                    <td class="fl-col-units">{{ a.load_units }}</td>
                  </tr>
                </template>
                <tr class="fl-total-row">
                  <td colspan="2"><strong>TOTAL</strong></td>
                  <td class="fl-col-units"><strong>{{ load.total_units }}</strong></td>
                </tr>
              </tbody>
            </table>

            <p class="fl-closing">Please be guided accordingly.</p>

            <!-- Signatures -->
            <div class="fl-sig-section">
              <p class="fl-sig-top">Prepared &amp; submitted by:</p>
              <div class="fl-sig-single">
                <div class="fl-sig-name">{{ load.signatories?.auh?.name?.toUpperCase() ?? '' }}</div>
                <div class="fl-sig-sub">{{ load.signatories?.auh?.position ?? 'Academic Unit Head' }}</div>
              </div>
            </div>

            <div class="fl-sig-section">
              <p class="fl-sig-top">Reviewed &amp; recommended by:</p>
              <div class="fl-sig-single">
                <div class="fl-sig-name">{{ load.signatories?.cid_chief?.name?.toUpperCase() ?? '' }}</div>
                <div class="fl-sig-sub">{{ load.signatories?.cid_chief?.position ?? 'CID Chief' }}</div>
              </div>
            </div>

            <div class="fl-sig-section">
              <p class="fl-sig-top">Approved by:</p>
              <div class="fl-sig-single">
                <div class="fl-sig-name">{{ load.signatories?.director?.name?.toUpperCase() ?? '' }}</div>
                <div class="fl-sig-sub">{{ load.signatories?.director?.position ?? 'Director III' }}</div>
              </div>
            </div>

            <div class="fl-sig-section">
              <p class="fl-sig-top">Concurred by:</p>
              <div class="fl-sig-single">
                <div class="fl-sig-name">{{ load.faculty?.name?.toUpperCase() ?? '' }}</div>
                <div class="fl-sig-sub">{{ load.faculty?.position ?? '' }}</div>
              </div>
            </div>

            <p class="fl-cc">cc: File, CID, FAD, SSD, OCD, HR</p>

          </td></tr>
        </tbody>
      </table>

      <!-- Page break between faculty members -->
      <div v-if="idx < loads.length - 1" class="fl-page-break"></div>

    </template>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  loads: { type: Array, default: () => [] },
})

const today = new Date().toLocaleDateString('en-PH', {
  month: 'long', day: 'numeric', year: 'numeric',
})

const TYPE_ORDER = ['teaching', 'research', 'admin', 'cocurricular', 'committee', 'designation']

const TYPE_LABELS = {
  teaching:    'TEACHING LOAD',
  research:    'RESEARCH ADVISORY',
  admin:       'ADMIN FUNCTIONS',
  cocurricular:'CO-CURRICULAR ACTIVITIES',
  committee:   'COMMITTEE ASSIGNMENT',
  designation: 'DESIGNATION',
}

function typeLabel(type) {
  return TYPE_LABELS[type] ?? type.toUpperCase()
}

function groupedAssignments(assignments) {
  if (!assignments?.length) return {}
  const groups = {}
  for (const a of assignments) {
    const t = a.assignment_type
    if (!groups[t]) groups[t] = []
    groups[t].push(a)
  }
  // Return in canonical type order
  const ordered = {}
  for (const t of TYPE_ORDER) {
    if (groups[t]) ordered[t] = groups[t]
  }
  for (const t of Object.keys(groups)) {
    if (!ordered[t]) ordered[t] = groups[t]
  }
  return ordered
}

onMounted(() => setTimeout(() => window.print(), 400))
</script>

<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #fff; }

#fl-print-root {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 9.5pt;
  color: #000;
}

.fl-pt-wrap {
  width: 100%;
  border-collapse: collapse;
}

.fl-pt-head,
.fl-pt-foot {
  padding: 0 0.75in;
}

.fl-pt-body {
  padding: 10px 0.75in;
  vertical-align: top;
}

/* ── Date ── */
.fl-date {
  text-align: right;
  font-size: 9pt;
  margin-bottom: 14px;
}

/* ── Faculty info block ── */
.fl-info-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
  font-size: 9.5pt;
}
.fl-info-table td { padding: 2px 0; }
.fl-pos { text-align: right; font-weight: 600; }

/* ── Subject / greeting / closing ── */
.fl-subject  { font-size: 9.5pt; margin: 8px 0 10px; }
.fl-greeting { font-size: 9.5pt; margin-bottom: 10px; line-height: 1.6; }
.fl-closing  { font-size: 9.5pt; margin: 12px 0 18px; }

/* ── Load assignments table ── */
.fl-load-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 6px;
}
.fl-load-table th,
.fl-load-table td {
  border: 1.5px solid #000;
  padding: 4px 7px;
  font-size: 9pt;
  vertical-align: middle;
}
.fl-load-table th {
  font-weight: 700;
  text-align: center;
  background: #f0f0f0;
}
.fl-col-type       { width: 26%; font-weight: 600; }
.fl-col-assignment { width: 58%; }
.fl-col-units      { width: 16%; text-align: center; }
.fl-type-cell      { vertical-align: middle; }
.fl-total-row td   { font-weight: 700; }
.fl-assignment-row { break-inside: avoid; page-break-inside: avoid; }

/* ── Signatures ── */
.fl-sig-section { margin-bottom: 16px; }
.fl-sig-top     { font-size: 9pt; margin-bottom: 22px; }

.fl-sig-single { display: inline-block; min-width: 260px; }
.fl-sig-name {
  font-weight: 700;
  font-size: 10pt;
  text-decoration: underline;
  border-bottom: 1px solid #000;
  padding-bottom: 2px;
  margin-bottom: 3px;
  text-align: center;
}
.fl-sig-sub  { font-size: 8.5pt; text-align: center; }

/* ── CC line ── */
.fl-cc { font-size: 8pt; margin-top: 20px; }

/* ── Page break between batch items ── */
.fl-page-break { display: none; }

@page { margin: 0.25in 0 0 0; }

@media print {
  body { margin: 0; }
  .fl-page-break { display: block; page-break-after: always; }
}
</style>
