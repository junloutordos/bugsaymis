<template>
  <Head title="Attendance Logs" />
  <AdminLayout title="Attendance Logs">
    <div class="p-6 bg-white rounded shadow">
      <h1 class="text-2xl font-bold mb-4">Attendance Logs</h1>
      <div v-if="(attendances.data || []).length === 0" class="text-gray-500 py-6">No attendance records.</div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full border-collapse border border-gray-300">
          <thead>
            <tr class="bg-gray-50">
              <th class="border border-gray-300 px-3 py-2 text-left">Badge</th>
              <th class="border border-gray-300 px-3 py-2 text-left">Date</th>
              <th class="border border-gray-300 px-3 py-2 text-left">Time In</th>
              <th class="border border-gray-300 px-3 py-2 text-left">Break Out</th>
              <th class="border border-gray-300 px-3 py-2 text-left">Break In</th>
              <th class="border border-gray-300 px-3 py-2 text-left">Time Out</th>
              <th class="border border-gray-300 px-3 py-2 text-left">Tardiness</th>
              <th class="border border-gray-300 px-3 py-2 text-left">Under Time</th>
              <th class="border border-gray-300 px-3 py-2 text-left">Over Time In</th>
              <th class="border border-gray-300 px-3 py-2 text-left">Over Time Out</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in rows" :key="a.id" class="hover:bg-gray-50">
              <td class="border border-gray-200 px-3 py-2">{{ a.BadgeNumber }}</td>
              <td class="border border-gray-200 px-3 py-2">{{ a.AttDate }}</td>
              <td class="border border-gray-200 px-3 py-2">{{ a.StartTime1 || '—' }}</td>
              <td class="border border-gray-200 px-3 py-2">{{ a.StartTime2 || '—' }}</td>
              <td class="border border-gray-200 px-3 py-2">{{ a.StartTime3 || '—' }}</td>
              <td class="border border-gray-200 px-3 py-2">{{ a.StartTime4 || '—' }}</td>
              <td class="border border-gray-200 px-3 py-2">{{ a.tardiness !== null ? a.tardiness : '—' }}</td>
              <td class="border border-gray-200 px-3 py-2">{{ a.undertime !== null ? a.undertime : '—' }}</td>
              <td class="border border-gray-200 px-3 py-2">{{ a.OTIn || a.OTin || '—' }}</td>
              <td class="border border-gray-200 px-3 py-2">{{ a.OTout || a.OTOut || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex items-center justify-center gap-3">
        <button @click.prevent="prev" :disabled="!attendances.prev_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
        <span class="text-sm">Page {{ attendances.current_page }} of {{ attendances.last_page }}</span>
        <button @click.prevent="next" :disabled="!attendances.next_page_url" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
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
