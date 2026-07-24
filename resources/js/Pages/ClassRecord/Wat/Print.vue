<script setup>
import { computed, nextTick, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  section:          { type: Object, required: true },
  wat:              { type: Object, required: true },
  coordinatorName:  { type: String, default: null },
  acidaaName:       { type: String, default: null },
  cidChiefName:     { type: String, default: null },
  schoolYear:       { type: Object, default: null },
})

const weekLabel = computed(() => {
  const opts = { month: 'long', day: 'numeric', year: 'numeric' }
  return `${new Date(props.wat.week_start + 'T00:00:00').toLocaleDateString('en-PH', opts)} – ${new Date(props.wat.week_end + 'T00:00:00').toLocaleDateString('en-PH', opts)}`
})

function dayName(dateStr) {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'long', month: 'short', day: 'numeric' })
}

const documentTitle = computed(() =>
  `WAT — Grade ${props.section.level} ${props.section.name} — Week of ${props.wat.week_start}`
)

onMounted(async () => {
  await nextTick()
  const images = [...document.querySelectorAll('.print-asset')]
  await Promise.all(images.map(image => image.complete ? Promise.resolve() : new Promise(resolve => {
    image.addEventListener('load', resolve, { once: true })
    image.addEventListener('error', resolve, { once: true })
  })))
  await document.fonts?.ready
  setTimeout(() => window.print(), 100)
})
</script>

<template>
  <Head :title="documentTitle" />

  <div class="wat-print-sheet">
    <img class="wat-print-header print-asset" src="/images/report_header_landscape.jpg" alt="" />

    <main class="wat-print-body">
      <div class="wat-heading">
        <h1>Weekly Assessment Tracker</h1>
        <div class="wat-meta">
          <div><strong>Section:</strong> Grade {{ section.level }} — {{ section.name }}</div>
          <div><strong>Week:</strong> {{ weekLabel }}</div>
          <div><strong>School Year:</strong> {{ schoolYear?.name }}</div>
        </div>
      </div>

      <table class="wat-table">
        <thead>
          <tr>
            <th style="width: 12%">Day</th>
            <th style="width: 10%">Time</th>
            <th style="width: 14%">Subject</th>
            <th>Assessment</th>
            <th style="width: 15%">Type of Assessment</th>
            <th style="width: 8%">Category</th>
            <th style="width: 13%">Teacher</th>
            <th style="width: 9%">% Compliance</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="day in wat.days" :key="day.date">
            <tr v-if="!day.items.length">
              <td class="wat-day">{{ dayName(day.date) }}</td>
              <td colspan="7" class="wat-empty">No assessments scheduled</td>
            </tr>
            <tr v-for="(item, idx) in day.items" :key="item.id">
              <td v-if="idx === 0" class="wat-day" :rowspan="day.items.length">{{ dayName(day.date) }}</td>
              <td class="wat-center">{{ item.time_label ?? '—' }}</td>
              <td>{{ item.subject_name }}</td>
              <td>
                <span class="wat-title">{{ item.title }}</span>
                <span v-if="item.is_major" class="wat-major"> (Major)</span>
              </td>
              <td>{{ item.type_label }}</td>
              <td>{{ item.is_graded ? 'Graded' : 'Non-graded' }}</td>
              <td>{{ item.teacher_name }}</td>
              <td class="wat-center">
                <template v-if="item.compliance !== null">{{ item.compliance }}%</template>
                <template v-else>—</template>
              </td>
            </tr>
          </template>
        </tbody>
      </table>

      <div class="wat-signatories">
        <div class="wat-signatory">
          <div class="wat-signatory-caption">Consolidated by:</div>
          <div class="wat-signatory-name">{{ coordinatorName ?? '' }}</div>
          <div class="wat-signatory-position">Homeroom Coordinator</div>
        </div>
        <div class="wat-signatory">
          <div class="wat-signatory-caption">Reviewed by:</div>
          <div class="wat-signatory-name">{{ acidaaName ?? '' }}</div>
          <div class="wat-signatory-position">Assistant CID Chief for Academic Affairs</div>
        </div>
        <div class="wat-signatory">
          <div class="wat-signatory-caption">Approved by:</div>
          <div class="wat-signatory-name">{{ cidChiefName ?? '' }}</div>
          <div class="wat-signatory-position">CID Chief</div>
        </div>
      </div>

      <p v-if="wat.review" class="wat-reviewed">
        Reviewed by {{ wat.review.reviewed_by?.name }} on
        {{ new Date(wat.review.reviewed_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}
        <template v-if="wat.review.remarks"> — “{{ wat.review.remarks }}”</template>
      </p>
    </main>

    <img class="wat-print-footer print-asset" src="/images/report_footer_landscape.jpg" alt="" />
  </div>
</template>

<style>
/* Global (unscoped) on purpose — matches SchedulePrintSheet.vue's approach:
 * @page margin is 0 on this page's own style block below, and the header/
 * footer images render full-bleed edge to edge; actual content gets its
 * margin from .wat-print-body's own padding instead of the page margin. */

* {
  box-sizing: border-box;
}

html,
body {
  margin: 0;
  padding: 0;
  background: #d1d5db;
}

@page {
  size: A4 landscape;
  margin: 0;
}

.wat-print-sheet {
  width: 297mm;
  height: 210mm;
  margin: 0 auto;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  background: #fff;
  color: #0f172a;
  font-family: 'Helvetica Neue', Arial, Helvetica, sans-serif;
  font-size: 8.5pt;
}

.wat-print-header,
.wat-print-footer {
  display: block;
  width: 100%;
  height: auto;
  margin: 0;
  flex: 0 0 auto;
}

.wat-print-body {
  min-height: 0;
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  padding: 1mm 10mm 0;
}

.wat-heading {
  flex: 0 0 auto;
  margin-bottom: 1.5mm;
}

.wat-heading h1 {
  margin: 0;
  font-size: 13pt;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  text-align: center;
}

.wat-meta {
  display: flex;
  justify-content: flex-start;
  gap: 18px;
  margin-top: 1mm;
  font-size: 8pt;
}

.wat-table {
  width: 100%;
  border-collapse: collapse;
  flex: 1 1 auto;
}

.wat-table th,
.wat-table td {
  border: 1px solid #94a3b8;
  padding: 4px 6px;
  text-align: left;
  vertical-align: top;
}

.wat-table th {
  background: #f1f5f9;
  font-size: 7.5pt;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.wat-day { font-weight: 600; white-space: nowrap; }
.wat-empty { color: #94a3b8; font-style: italic; }
.wat-center { text-align: center; white-space: nowrap; }
.wat-title { font-weight: 700; }
.wat-major { font-weight: 700; color: #b91c1c; }

/* ── Signatories — 3 columns, matches SchedulePrintSheet.vue conventions ──── */

.wat-signatories {
  flex: 0 0 auto;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0 12mm;
  padding: 3mm 6mm 1mm;
  margin-top: 2mm;
}

.wat-signatory {
  text-align: center;
}

.wat-signatory-caption {
  text-align: left;
  font-size: 7.5pt;
  color: #334155;
  margin-bottom: 6mm;
}

.wat-signatory-name {
  font-size: 8.5pt;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  border-bottom: 1px solid #0f172a;
  padding-bottom: 2px;
  min-height: 18px;
}

.wat-signatory-position {
  font-size: 7pt;
  color: #475569;
  margin-top: 3px;
}

.wat-reviewed {
  margin-top: 2mm;
  padding: 0 6mm;
  font-size: 7.5pt;
  color: #475569;
  font-style: italic;
}

@media print {
  html,
  body {
    width: 297mm;
    height: 210mm;
    margin: 0;
    padding: 0;
    overflow: hidden;
    background: #fff;
  }

  .wat-print-sheet {
    margin: 0;
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
    break-after: avoid;
    break-inside: avoid;
    page-break-after: avoid;
    page-break-inside: avoid;
  }
}
</style>
