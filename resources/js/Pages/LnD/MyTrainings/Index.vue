<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { CheckCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  records: { type: Object, required: true },
})

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'

function attendanceBadgeColor (s) {
  const map = { registered: 'slate', attended: 'green', absent: 'red', excused: 'amber' }
  return map[s] ?? 'slate'
}
function completionBadgeColor (s) {
  const map = { pending: 'slate', completed: 'green', not_completed: 'red' }
  return map[s] ?? 'slate'
}
function typeBadgeColor (t) {
  const map = { mandatory: 'purple', technical: 'indigo', leadership: 'orange', functional: 'blue' }
  return map[t] ?? 'slate'
}

const goToPage = (p) => {
  router.get(route('lnd.my-trainings'), { page: p }, { preserveState: true, replace: true })
}
</script>

<template>
  <Head title="My Trainings" />
  <AdminLayout title="My Trainings">
    <div class="space-y-5">

      <AppPageHeader title="My Trainings" subtitle="Your training history and participation records" />

      <!-- Table -->
      <AppTable :is-empty="!records.data.length" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Program</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Type</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Session Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Provider</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Attendance</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Completion</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Certificate</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Evaluation</th>
          </tr>
        </template>

        <tr v-for="r in records.data" :key="r.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800">{{ r.session?.program?.title ?? '—' }}</div>
            <div v-if="r.session?.venue" class="text-xs text-slate-500">{{ r.session.venue }}</div>
          </td>
          <td class="px-4 py-3">
            <AppBadge v-if="r.session?.program?.type" :color="typeBadgeColor(r.session.program.type)">{{ r.session.program.type }}</AppBadge>
            <span v-else class="text-slate-400 text-xs">—</span>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ fmt(r.session?.session_date) }}</td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ r.session?.program?.provider ?? '—' }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="attendanceBadgeColor(r.attendance_status)">{{ r.attendance_status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="completionBadgeColor(r.completion_status)">{{ r.completion_status.replace('_', ' ') }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <span v-if="r.certificate_path" class="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
              <CheckCircleIcon class="w-3.5 h-3.5" />
              Issued
            </span>
            <span v-else class="text-xs text-slate-400">—</span>
          </td>
          <td class="px-4 py-3 text-center">
            <Link v-if="r.evaluation"
              :href="route('lnd.evaluations.show', r.id)"
              class="text-xs font-medium text-indigo-600 hover:underline">View</Link>
            <Link v-else-if="r.attendance_status === 'attended'"
              :href="route('lnd.evaluations.show', r.id)"
              class="text-xs font-medium text-indigo-500 hover:underline">+ Evaluate</Link>
            <span v-else class="text-xs text-slate-400">—</span>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No training records found." />
        </template>

        <template #footer>
          <PaginationControl :links="records.links" :total="records.total" />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
