<script setup>
import { ref, computed } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  applications: Object,
  counts:       Object,
  filters:      Object,
})

const TABS = [
  { key: 'pending',      label: 'Pending' },
  { key: 'under_review', label: 'Under Review' },
  { key: 'approved',     label: 'Approved' },
  { key: 'rejected',     label: 'Rejected' },
]

const search = ref(props.filters.search ?? '')

function applyFilter(status) {
  router.get(route('registrar.enrollment-applications.index'), { status, search: search.value }, { preserveState: true })
}

function applySearch() {
  router.get(route('registrar.enrollment-applications.index'),
    { status: props.filters.status, search: search.value },
    { preserveState: true })
}

const statusBadge = {
  pending:      'bg-amber-100 text-amber-700',
  under_review: 'bg-blue-100 text-blue-700',
  approved:     'bg-green-100 text-green-700',
  rejected:     'bg-red-100 text-red-700',
}
const statusLabel = {
  pending:      'Pending',
  under_review: 'Under Review',
  approved:     'Approved',
  rejected:     'Rejected',
}

function fmt(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
  <Head title="Enrollment Applications" />
  <AdminLayout title="Enrollment Applications">

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-slate-200 mb-5">
      <button v-for="tab in TABS" :key="tab.key"
              @click="applyFilter(tab.key)"
              class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition"
              :class="filters.status === tab.key
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-slate-500 hover:text-slate-700'">
        {{ tab.label }}
        <span v-if="counts[tab.key]"
              class="ml-1.5 rounded-full px-2 py-0.5 text-xs"
              :class="filters.status === tab.key ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-100 text-slate-500'">
          {{ counts[tab.key] }}
        </span>
      </button>
    </div>

    <!-- Search -->
    <div class="mb-4 flex items-center gap-2">
      <div class="relative flex-1 max-w-xs">
        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
        <input v-model="search" @keyup.enter="applySearch"
               placeholder="Search name, reference, LRN…"
               class="pl-9 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
      </div>
      <button @click="applySearch"
              class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Search
      </button>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Reference</th>
            <th class="px-4 py-3 text-left">Applicant</th>
            <th class="px-4 py-3 text-left">Grade</th>
            <th class="px-4 py-3 text-left">LRN</th>
            <th class="px-4 py-3 text-left">Previous School</th>
            <th class="px-4 py-3 text-left">Submitted</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="app in applications.data" :key="app.id" class="hover:bg-slate-50/60">
            <td class="px-4 py-3 font-mono text-xs text-slate-600 font-medium">{{ app.reference_no }}</td>
            <td class="px-4 py-3">
              <div class="font-medium text-slate-800">{{ app.lastname }}, {{ app.firstname }}</div>
              <div v-if="app.middlename" class="text-xs text-slate-400">{{ app.middlename }}</div>
            </td>
            <td class="px-4 py-3 text-slate-600">Grade {{ app.grade_level }}</td>
            <td class="px-4 py-3 text-slate-500 text-xs font-mono">{{ app.lrn || '—' }}</td>
            <td class="px-4 py-3 text-slate-500 text-xs">{{ app.previous_school || '—' }}</td>
            <td class="px-4 py-3 text-slate-500 text-xs">{{ fmt(app.created_at) }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="statusBadge[app.status]">
                {{ statusLabel[app.status] }}
              </span>
            </td>
            <td class="px-4 py-3">
              <Link :href="route('registrar.enrollment-applications.show', app.id)"
                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                Review →
              </Link>
            </td>
          </tr>
          <tr v-if="!applications.data.length">
            <td colspan="8" class="px-4 py-10 text-center text-slate-400 text-sm">
              No {{ filters.status }} applications.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="applications.last_page > 1" class="mt-4 flex justify-center gap-2">
      <a v-for="link in applications.links" :key="link.label"
         :href="link.url ?? '#'"
         v-html="link.label"
         class="px-3 py-1.5 rounded text-sm border"
         :class="link.active
           ? 'bg-indigo-600 text-white border-indigo-600'
           : link.url ? 'border-slate-200 text-slate-600 hover:bg-slate-50' : 'border-slate-100 text-slate-300 pointer-events-none'" />
    </div>

  </AdminLayout>
</template>
