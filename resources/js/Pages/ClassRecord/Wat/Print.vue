<script setup>
import { computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  section:     { type: Object, required: true },
  wat:         { type: Object, required: true },
  adviserName: { type: String, default: null },
  acidaaName:  { type: String, default: null },
  schoolYear:  { type: Object, default: null },
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

onMounted(() => {
  setTimeout(() => window.print(), 300)
})
</script>

<template>
  <Head :title="documentTitle" />

  <div class="wat-print-sheet">
    <img class="wat-print-header" src="/images/report_header.jpeg" alt="" />

    <main>
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
            <th style="width: 14%">Day</th>
            <th style="width: 16%">Subject</th>
            <th>Assessment</th>
            <th style="width: 16%">Type</th>
            <th style="width: 9%">Category</th>
            <th style="width: 14%">Teacher</th>
            <th style="width: 10%">% Compliance</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="day in wat.days" :key="day.date">
            <tr v-if="!day.items.length">
              <td class="wat-day">{{ dayName(day.date) }}</td>
              <td colspan="6" class="wat-empty">No assessments scheduled</td>
            </tr>
            <tr v-for="(item, idx) in day.items" :key="item.id">
              <td v-if="idx === 0" class="wat-day" :rowspan="day.items.length">{{ dayName(day.date) }}</td>
              <td>{{ item.subject_name }}</td>
              <td>{{ item.title }}<span v-if="item.is_major" class="wat-major"> (Major)</span></td>
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
        <tfoot>
          <tr>
            <td colspan="7" class="wat-totals">
              Weekly totals: {{ wat.totals.graded }} graded (limit {{ wat.limits.weekly_graded }}) ·
              {{ wat.totals.major }} major (limit {{ wat.limits.weekly_major }})
              <span v-if="wat.totals.over_weekly" class="wat-over"> — WEEKLY LIMIT EXCEEDED</span>
            </td>
          </tr>
        </tfoot>
      </table>

      <div class="wat-signatures">
        <div class="wat-sign">
          <p class="wat-sign-name">{{ adviserName ?? '' }}</p>
          <p class="wat-sign-line">Homeroom Coordinator</p>
        </div>
        <div class="wat-sign">
          <p class="wat-sign-name">{{ acidaaName ?? '' }}</p>
          <p class="wat-sign-line">Assistant CID Chief for Academic Affairs</p>
        </div>
      </div>

      <p v-if="wat.review" class="wat-reviewed">
        Reviewed by {{ wat.review.reviewed_by?.name }} on
        {{ new Date(wat.review.reviewed_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}
        <template v-if="wat.review.remarks"> — “{{ wat.review.remarks }}”</template>
      </p>
    </main>
  </div>
</template>

<style scoped>
.wat-print-sheet {
  max-width: 960px;
  margin: 0 auto;
  padding: 24px;
  background: #fff;
  color: #0f172a;
  font-size: 12px;
}
.wat-print-header {
  width: 100%;
  margin-bottom: 16px;
}
.wat-heading h1 {
  text-align: center;
  font-size: 16px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 8px;
}
.wat-meta {
  display: flex;
  justify-content: center;
  gap: 24px;
  margin-bottom: 14px;
}
.wat-table {
  width: 100%;
  border-collapse: collapse;
}
.wat-table th,
.wat-table td {
  border: 1px solid #94a3b8;
  padding: 5px 7px;
  text-align: left;
  vertical-align: top;
}
.wat-table th {
  background: #f1f5f9;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.wat-day { font-weight: 600; white-space: nowrap; }
.wat-empty { color: #94a3b8; font-style: italic; }
.wat-center { text-align: center; }
.wat-major { font-weight: 700; }
.wat-totals { font-weight: 600; background: #f8fafc; }
.wat-over { color: #b91c1c; font-weight: 700; }
.wat-signatures {
  display: flex;
  justify-content: space-around;
  margin-top: 48px;
}
.wat-sign { text-align: center; min-width: 240px; }
.wat-sign-name {
  font-weight: 700;
  text-transform: uppercase;
  border-bottom: 1px solid #0f172a;
  padding-bottom: 2px;
  min-height: 18px;
}
.wat-sign-line { font-size: 11px; color: #475569; margin-top: 3px; }
.wat-reviewed { margin-top: 20px; font-size: 11px; color: #475569; font-style: italic; }

@media print {
  .wat-print-sheet { padding: 0; max-width: none; }
  @page { size: A4 landscape; margin: 12mm; }
}
</style>
