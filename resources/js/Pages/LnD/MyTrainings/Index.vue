<script setup>
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({
  records: { type: Object, required: true },
})

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'

const attendanceColors = {
  registered: 'bg-gray-100 text-gray-600',
  attended:   'bg-green-100 text-green-700',
  absent:     'bg-red-100 text-red-600',
  excused:    'bg-yellow-100 text-yellow-700',
}
const completionColors = {
  pending:       'bg-gray-100 text-gray-600',
  completed:     'bg-green-100 text-green-700',
  not_completed: 'bg-red-100 text-red-600',
}
const typeColors = {
  mandatory:  'bg-purple-100 text-purple-700',
  technical:  'bg-indigo-100 text-indigo-700',
  leadership: 'bg-orange-100 text-orange-700',
  functional: 'bg-teal-100 text-teal-700',
}

const goToPage = (p) => {
  router.get(route('lnd.my-trainings'), { page: p }, { preserveState: true, replace: true })
}
</script>

<template>
  <AdminLayout title="My Trainings">
    <Head title="My Trainings" />

    <div class="p-6 space-y-5">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">My Trainings</h1>
          <p class="text-sm text-slate-500">Your training history and participation records</p>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Program</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Session Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Provider</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Attendance</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Completion</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Certificate</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Evaluation</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="records.data.length === 0">
                <td colspan="8" class="py-16 text-center text-slate-400 text-sm">
                  <div class="flex flex-col items-center gap-2">
                    <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span>No training records found.</span>
                  </div>
                </td>
              </tr>
              <tr v-for="r in records.data" :key="r.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-800">{{ r.session?.program?.title ?? '—' }}</div>
                  <div v-if="r.session?.venue" class="text-xs text-slate-500">{{ r.session.venue }}</div>
                </td>
                <td class="px-4 py-3">
                  <span v-if="r.session?.program?.type"
                    :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize', typeColors[r.session.program.type] ?? 'bg-slate-100 text-slate-600']">
                    {{ r.session.program.type }}
                  </span>
                  <span v-else class="text-slate-400 text-xs">—</span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ fmt(r.session?.session_date) }}</td>
                <td class="px-4 py-3 text-sm text-slate-600">{{ r.session?.program?.provider ?? '—' }}</td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize', attendanceColors[r.attendance_status] ?? 'bg-slate-100 text-slate-600']">
                    {{ r.attendance_status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', completionColors[r.completion_status] ?? 'bg-slate-100 text-slate-600']">
                    {{ r.completion_status.replace('_', ' ') }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span v-if="r.certificate_path" class="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Issued
                  </span>
                  <span v-else class="text-xs text-slate-400">—</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <a v-if="r.evaluation"
                    :href="route('lnd.evaluations.show', r.id)"
                    class="text-xs font-medium text-indigo-600 hover:underline">View</a>
                  <a v-else-if="r.attendance_status === 'attended'"
                    :href="route('lnd.evaluations.show', r.id)"
                    class="text-xs font-medium text-indigo-500 hover:underline">+ Evaluate</a>
                  <span v-else class="text-xs text-slate-400">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <PaginationControl :links="records.links" :total="records.total" />
    </div>
  </AdminLayout>
</template>
