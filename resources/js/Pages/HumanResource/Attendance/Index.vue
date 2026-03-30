<template>
  <Head title="Attendance Logs" />
  <AdminLayout title="Attendance Logs">
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Attendance Logs</h1>
      </div>

      <div v-if="(attendances.data || []).length === 0" class="py-16 text-center text-slate-400 text-sm">No attendance records.</div>

      <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto rounded-xl border border-slate-100">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Badge</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Time In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Break Out</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Break In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Time Out</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Tardiness</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Under Time</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Over Time In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Over Time Out</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="a in rows" :key="a.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ a.BadgeNumber }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ a.AttDate }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ a.StartTime1 || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ a.StartTime2 || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ a.StartTime3 || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ a.StartTime4 || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ a.tardiness !== null ? a.tardiness : '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ a.undertime !== null ? a.undertime : '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ a.OTIn || a.OTin || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ a.OTout || a.OTOut || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ attendances.current_page }} of {{ attendances.last_page }}</span>
          <div class="flex gap-2">
            <button @click.prevent="prev" :disabled="!attendances.prev_page_url" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Prev</button>
            <button @click.prevent="next" :disabled="!attendances.next_page_url" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed } from 'vue'

const page = usePage()
const attendances = computed(() => page.props.attendances || { data: [], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null })

// helper to read possibly differently-cased keys and return numeric value
function getNumericField(row, candidates) {
  for (const k of candidates) {
    if (row[k] !== undefined && row[k] !== null && row[k] !== '') {
      const n = Number(row[k]);
      if (!isNaN(n)) return n;
    }
  }
  return 0;
}

const rows = computed(() => {
  const d = attendances.value.data || [];
  return d.map(r => {
    const t1 = getNumericField(r, ['startTime1_tardiness','starttime1_tardiness','StartTime1_tardiness']);
    const t3 = getNumericField(r, ['startTime3_tardiness','starttime3_tardiness','StartTime3_tardiness']);
    const u2 = getNumericField(r, ['startTime2_undertime','starttime2_undertime','StartTime2_undertime']);
    const u4 = getNumericField(r, ['startTime4_undertime','starttime4_undertime','StartTime4_undertime']);

    const tardSum = t1 + t3;
    const underSum = u2 + u4;

    return Object.assign({}, r, {
      tardiness: Number.isFinite(tardSum) ? Math.round(tardSum) : null,
      undertime: Number.isFinite(underSum) ? Math.round(underSum) : null,
    })
  })
})

function prev() {
  const url = attendances.value.prev_page_url
  if (url) window.location.href = url
}
function next() {
  const url = attendances.value.next_page_url
  if (url) window.location.href = url
}
</script>
