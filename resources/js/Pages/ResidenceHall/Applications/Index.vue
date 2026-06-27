<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  applications: Array,
  schoolYears: Array,
  activeSyId: Number,
  filters: Object,
  myHall: String,
})

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')
const syId   = ref(props.activeSyId)

const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  const lower = search.value.toLowerCase()
  return props.applications.filter(a =>
    (!lower || a.student_name.toLowerCase().includes(lower)) &&
    (!status.value || a.status === status.value)
  )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))

const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

function applyFilters() {
  currentPage.value = 1
  router.get(route('rh.applications.index'), { school_year_id: syId.value, status: status.value, search: search.value }, { preserveState: true, replace: true })
}

const fmtDate = (d) => d
  ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
  : '—'

const statusClass = (s) => ({
  pending:   'bg-amber-100 text-amber-700',
  evaluated: 'bg-sky-100 text-sky-700',
  approved:  'bg-emerald-100 text-emerald-700',
  rejected:  'bg-rose-100 text-rose-700',
  waitlisted: 'bg-slate-100 text-slate-600',
}[s] || 'bg-slate-100 text-slate-600')
</script>

<template>
  <Head title="RH Applications" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Accommodation Applications</h1>
          <p class="text-sm text-slate-500">SSM 5.1 — Evaluation and approval of RH applications</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
          <label class="block text-xs font-medium text-slate-600 mb-1">School Year</label>
          <select v-model="syId" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
              SY {{ sy.name }}{{ sy.is_current ? ' (Current)' : '' }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
          <select v-model="status" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="evaluated">Evaluated</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="waitlisted">Waitlisted</option>
          </select>
        </div>
        <div class="flex-1 min-w-[200px]">
          <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
          <div class="relative">
            <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input v-model="search" @input="currentPage = 1" type="text" placeholder="Student name…"
                   class="w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50">
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Grade</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Hall</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Province</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Filed</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="app in displayed" :key="app.id" class="hover:bg-slate-50 transition-colors">
              <td class="px-4 py-3 font-medium text-slate-800">{{ app.student_name }}</td>
              <td class="px-4 py-3 text-slate-600">{{ app.grade_level || '—' }}</td>
              <td class="px-4 py-3">
                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium',
                  app.preferred_hall === 'BRH' ? 'bg-indigo-100 text-indigo-700' : 'bg-pink-100 text-pink-700']">
                  {{ app.preferred_hall }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-600">{{ app.home_province || '—' }}</td>
              <td class="px-4 py-3 text-slate-500 text-xs">{{ fmtDate(app.created_at) }}</td>
              <td class="px-4 py-3">
                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium capitalize', statusClass(app.status)]">
                  {{ app.status }}
                </span>
              </td>
              <td class="px-4 py-3">
                <Link :href="route('rh.applications.show', app.id)"
                      class="text-xs text-indigo-600 hover:underline font-medium">Review</Link>
              </td>
            </tr>
            <tr v-if="!displayed.length">
              <td colspan="7" class="text-center py-12 text-slate-400 text-sm">No applications found.</td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
          <p class="text-xs text-slate-500">Page {{ currentPage }} of {{ totalPages }}</p>
          <div class="flex gap-2">
            <button @click="currentPage--" :disabled="currentPage <= 1"
                    class="px-3 py-1.5 rounded-lg text-xs border border-slate-200 text-slate-600 disabled:opacity-40 hover:bg-slate-50">Prev</button>
            <button @click="currentPage++" :disabled="currentPage >= totalPages"
                    class="px-3 py-1.5 rounded-lg text-xs border border-slate-200 text-slate-600 disabled:opacity-40 hover:bg-slate-50">Next</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
