<script setup>
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

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

      <!-- Header -->
      <div>
        <h1 class="text-xl font-bold text-gray-800">My Trainings</h1>
        <p class="text-sm text-gray-500">Your training history and participation records</p>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Program</th>
              <th class="px-4 py-3 text-left">Type</th>
              <th class="px-4 py-3 text-left">Session Date</th>
              <th class="px-4 py-3 text-left">Provider</th>
              <th class="px-4 py-3 text-center">Attendance</th>
              <th class="px-4 py-3 text-center">Completion</th>
              <th class="px-4 py-3 text-center">Certificate</th>
              <th class="px-4 py-3 text-center">Evaluation</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="records.data.length === 0">
              <td colspan="8" class="py-12 text-center text-gray-400">
                <div class="flex flex-col items-center gap-2">
                  <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                  </svg>
                  <span>No training records found.</span>
                </div>
              </td>
            </tr>
            <tr v-for="r in records.data" :key="r.id" class="hover:bg-gray-50">
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800">{{ r.session?.program?.title ?? '—' }}</div>
                <div v-if="r.session?.venue" class="text-xs text-gray-500">{{ r.session.venue }}</div>
              </td>
              <td class="px-4 py-3">
                <span v-if="r.session?.program?.type"
                  :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize', typeColors[r.session.program.type] ?? 'bg-gray-100 text-gray-600']">
                  {{ r.session.program.type }}
                </span>
                <span v-else class="text-gray-400 text-xs">—</span>
              </td>
              <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ fmt(r.session?.session_date) }}</td>
              <td class="px-4 py-3 text-gray-600 text-xs">{{ r.session?.program?.provider ?? '—' }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize', attendanceColors[r.attendance_status] ?? 'bg-gray-100 text-gray-600']">
                  {{ r.attendance_status }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', completionColors[r.completion_status] ?? 'bg-gray-100 text-gray-600']">
                  {{ r.completion_status.replace('_', ' ') }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <span v-if="r.certificate_path" class="inline-flex items-center gap-1 text-xs text-green-600 font-medium">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                  </svg>
                  Issued
                </span>
                <span v-else class="text-xs text-gray-400">—</span>
              </td>
              <td class="px-4 py-3 text-center">
                <a v-if="r.evaluation"
                  :href="route('lnd.evaluations.show', r.id)"
                  class="text-xs font-medium text-indigo-600 hover:underline">View</a>
                <a v-else-if="r.attendance_status === 'attended'"
                  :href="route('lnd.evaluations.show', r.id)"
                  class="text-xs font-medium text-blue-500 hover:underline">+ Evaluate</a>
                <span v-else class="text-xs text-gray-400">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="records.last_page > 1" class="flex items-center justify-between text-sm text-gray-600">
        <span>Showing {{ records.from }}–{{ records.to }} of {{ records.total }}</span>
        <div class="flex gap-1">
          <button v-for="p in records.links" :key="p.label"
            @click="p.url && goToPage(new URL(p.url).searchParams.get('page'))"
            :disabled="!p.url"
            :class="['px-3 py-1 rounded border text-xs', p.active ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50 disabled:opacity-40']"
            v-html="p.label" />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
