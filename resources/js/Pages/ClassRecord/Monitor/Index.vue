<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  UserGroupIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ClipboardDocumentCheckIcon,
  EyeIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  schoolYear: { type: Object, default: null },
  stats: { type: Object, required: true },
  rows: { type: Array, default: () => [] },
  isCidChief: { type: Boolean, default: false },
})

const STATUS_LABEL = {
  not_created: 'Not Created',
  draft: 'Draft',
  submitted: 'Submitted',
  checked: 'Checked',
}
const STATUS_COLOR = {
  not_created: 'red',
  draft: 'slate',
  submitted: 'amber',
  checked: 'green',
}

const completionPct = computed(() => {
  if (!props.stats.total_assignments) return 0
  return Math.round((props.stats.created / props.stats.total_assignments) * 100)
})

const statusFilter = ref('all')
const search = ref('')

const filtered = computed(() => {
  let rows = props.rows
  if (statusFilter.value !== 'all') {
    rows = rows.filter(r => r.status === statusFilter.value)
  }
  const q = search.value.trim().toLowerCase()
  if (q) {
    rows = rows.filter(r =>
      (r.teacher_name ?? '').toLowerCase().includes(q) ||
      (r.subject_name ?? '').toLowerCase().includes(q) ||
      (r.section_label ?? '').toLowerCase().includes(q)
    )
  }
  return rows
})

function viewRecord(row) {
  if (!row.class_record_id) return
  router.visit(route('class-records.page.show', row.class_record_id))
}
</script>

<template>
  <Head title="Class Record Monitoring" />
  <AdminLayout title="Class Record Monitoring">
    <AppPageHeader
      title="Class Record Monitoring"
      :subtitle="schoolYear
        ? `S.Y. ${schoolYear.name} — read-only oversight of every teaching assignment's class record status${isCidChief ? '' : ' for your unit'}`
        : 'No active school year configured.'"
    />

    <div v-if="!schoolYear">
      <EmptyState title="No active school year" subtitle="Set a current school year to see class record coverage." />
    </div>

    <div v-else class="space-y-5">
      <!-- Stat cards -->
      <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <AppCard class="p-4">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase text-slate-500">Teaching Assignments</p>
              <p class="mt-2 text-3xl font-bold text-slate-900 tabular-nums">{{ stats.total_assignments }}</p>
              <p class="mt-1 text-xs text-slate-500">this school year</p>
            </div>
            <div class="rounded-lg bg-indigo-100 p-2 text-indigo-700"><UserGroupIcon class="h-5 w-5" /></div>
          </div>
        </AppCard>
        <AppCard class="p-4">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase text-slate-500">Created</p>
              <p class="mt-2 text-3xl font-bold text-emerald-600 tabular-nums">{{ stats.created }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ completionPct }}% completion</p>
            </div>
            <div class="rounded-lg bg-emerald-100 p-2 text-emerald-700"><CheckCircleIcon class="h-5 w-5" /></div>
          </div>
        </AppCard>
        <AppCard class="p-4">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase text-slate-500">Not Created Yet</p>
              <p class="mt-2 text-3xl font-bold text-red-600 tabular-nums">{{ stats.missing }}</p>
              <p class="mt-1 text-xs text-slate-500">needs follow-up</p>
            </div>
            <div class="rounded-lg bg-red-100 p-2 text-red-700"><ExclamationTriangleIcon class="h-5 w-5" /></div>
          </div>
        </AppCard>
        <AppCard class="p-4">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase text-slate-500">Checked</p>
              <p class="mt-2 text-3xl font-bold text-slate-900 tabular-nums">{{ stats.checked }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ stats.submitted }} submitted · {{ stats.draft }} draft</p>
            </div>
            <div class="rounded-lg bg-indigo-100 p-2 text-indigo-700"><ClipboardDocumentCheckIcon class="h-5 w-5" /></div>
          </div>
        </AppCard>
      </section>

      <!-- Drill-down table -->
      <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <h2 class="font-heading text-sm font-semibold text-slate-900">Coverage by Teaching Assignment</h2>
          <div class="flex flex-wrap items-center gap-2">
            <input v-model="search" type="text" placeholder="Search teacher, subject, section…"
              class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-56" />
            <select v-model="statusFilter"
              class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="all">All statuses</option>
              <option value="not_created">Not Created</option>
              <option value="draft">Draft</option>
              <option value="submitted">Submitted</option>
              <option value="checked">Checked</option>
            </select>
          </div>
        </div>

        <AppTable :is-empty="!filtered.length" :skeleton-cols="5">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Teacher</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Subject</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Section</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </template>
          <tr v-for="(r, idx) in filtered" :key="idx" class="hover:bg-slate-50/60">
            <td class="px-4 py-3 font-medium text-slate-700">{{ r.teacher_name ?? '—' }}</td>
            <td class="px-4 py-3 text-slate-600">{{ r.subject_name ?? '—' }}</td>
            <td class="px-4 py-3 text-slate-600">{{ r.section_label ?? '—' }}</td>
            <td class="px-4 py-3">
              <AppBadge :color="STATUS_COLOR[r.status]">{{ STATUS_LABEL[r.status] }}</AppBadge>
            </td>
            <td class="px-4 py-3 text-right">
              <button v-if="r.class_record_id" @click="viewRecord(r)"
                class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800">
                <EyeIcon class="h-3.5 w-3.5" /> View
              </button>
            </td>
          </tr>
        </AppTable>
      </div>
    </div>
  </AdminLayout>
</template>
