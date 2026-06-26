<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  cases: Object,
  filters: Object,
  canManage: Boolean,
  canFile: Boolean,
})

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')
const level = ref(props.filters.level || '')

const statusOptions = ['filed', 'received', 'under_review', 'resolved', 'dismissed', 'cancelled']

const statusClass = (s) => ({
  filed: 'bg-amber-100 text-amber-700',
  received: 'bg-sky-100 text-sky-700',
  under_review: 'bg-indigo-100 text-indigo-700',
  resolved: 'bg-emerald-100 text-emerald-700',
  dismissed: 'bg-slate-100 text-slate-600',
  cancelled: 'bg-rose-100 text-rose-700',
}[s] || 'bg-slate-100 text-slate-600')

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

function applyFilters() {
  router.get(route('discipline.cases.index'),
    { search: search.value, status: status.value, level: level.value },
    { preserveState: true, replace: true })
}
</script>

<template>
  <Head title="Discipline Cases" />
  <AdminLayout title="Student Discipline">
    <div class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Discipline Cases</h1>
          <p class="text-sm text-slate-500">Anecdotal Reports and case resolution</p>
        </div>
        <Link v-if="canFile" :href="route('discipline.cases.create')"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          <PlusIcon class="w-4 h-4" /> File a Report
        </Link>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
          <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
          <div class="relative">
            <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input v-model="search" @keyup.enter="applyFilters" type="text" placeholder="Case no. or offense…"
                   class="w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
          <select v-model="status" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All</option>
            <option v-for="s in statusOptions" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Level</label>
          <select v-model="level" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All</option>
            <option value="1">Level 1</option>
            <option value="2">Level 2</option>
            <option value="3">Level 3</option>
          </select>
        </div>
        <button @click="applyFilters"
                class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium">Apply</button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100">
              <th class="px-4 py-3">Case No.</th>
              <th class="px-4 py-3">Student</th>
              <th class="px-4 py-3">Offense</th>
              <th class="px-4 py-3">Level</th>
              <th class="px-4 py-3">Filed</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in cases.data" :key="c.id" class="border-b border-slate-50 hover:bg-slate-50/60">
              <td class="px-4 py-3 font-medium text-slate-700">{{ c.case_no }}</td>
              <td class="px-4 py-3 text-slate-600">{{ c.student_name }}</td>
              <td class="px-4 py-3 text-slate-600">{{ c.offense?.title || c.nature_of_offense || '—' }}</td>
              <td class="px-4 py-3 text-slate-600">{{ c.offense_level ? ('L' + c.offense_level) : '—' }}</td>
              <td class="px-4 py-3 text-slate-500">{{ fmtDate(c.date_filed) }}</td>
              <td class="px-4 py-3">
                <span class="inline-block px-2 py-1 rounded-md text-xs font-medium" :class="statusClass(c.status)">
                  {{ c.status.replace('_', ' ') }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <Link :href="route('discipline.cases.show', c.id)" class="text-indigo-600 hover:text-indigo-800 font-medium">View</Link>
              </td>
            </tr>
            <tr v-if="!cases.data.length">
              <td colspan="7" class="px-4 py-10 text-center text-slate-400">No discipline cases found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="cases.links?.length > 3" class="flex flex-wrap gap-1 justify-center">
        <template v-for="(l, i) in cases.links" :key="i">
          <Link v-if="l.url" :href="l.url" v-html="l.label"
                class="px-3 py-1.5 rounded-md text-sm border"
                :class="l.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'" />
          <span v-else v-html="l.label" class="px-3 py-1.5 rounded-md text-sm text-slate-300"></span>
        </template>
      </div>
    </div>
  </AdminLayout>
</template>
