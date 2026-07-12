<template>
  <Head title="Attendance Logs" />
  <AdminLayout title="Attendance Logs">
    <div class="space-y-5">

      <AppPageHeader title="Attendance Logs" />

      <!-- Table -->
      <AppTable :is-empty="!rows.length" :skeleton-cols="10">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Badge</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Time In</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Break Out</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Break In</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Time Out</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Tardiness</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Under Time</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Over Time In</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Over Time Out</th>
          </tr>
        </template>

        <tr v-for="a in rows" :key="a.id" class="hover:bg-indigo-50/40">
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

        <template #mobileCard>
          <div v-for="a in rows" :key="a.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">Badge {{ a.BadgeNumber }}</p>
                <p class="text-xs text-slate-400">{{ a.AttDate }}</p>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-500">
              <span>Time In: {{ a.StartTime1 || '—' }}</span>
              <span>Time Out: {{ a.StartTime4 || '—' }}</span>
              <span>Break Out: {{ a.StartTime2 || '—' }}</span>
              <span>Break In: {{ a.StartTime3 || '—' }}</span>
              <span>Tardiness: {{ a.tardiness !== null ? a.tardiness : '—' }}</span>
              <span>Under Time: {{ a.undertime !== null ? a.undertime : '—' }}</span>
              <span>OT In: {{ a.OTIn || a.OTin || '—' }}</span>
              <span>OT Out: {{ a.OTout || a.OTOut || '—' }}</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No attendance records" />
        </template>

        <template #footer>
          <PaginationControl
            :links="attendances.links"
            :current-page="attendances.current_page"
            :total-pages="attendances.last_page"
            :total="attendances.total"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { computed } from 'vue'

const page = usePage()
const attendances = computed(() => page.props.attendances || { data: [], current_page: 1, last_page: 1, links: [], total: 0 })

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
</script>
