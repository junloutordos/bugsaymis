<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  CalendarDaysIcon,
  MapPinIcon,
  ClockIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClipboardDocumentCheckIcon,
  ClipboardDocumentListIcon,
  ArrowRightIcon,
  MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  activities: Array,
})

// ── Filters & pagination ──────────────────────────────────────────────────────

const search       = ref('')
const statusFilter = ref('all')   // all | yes | no
const evalFilter   = ref('all')   // all | yes | no
const page         = ref(1)
const PER_PAGE     = 15

const filtered = computed(() => {
  let list = props.activities ?? []

  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(a =>
      a.title?.toLowerCase().includes(q) ||
      a.venue?.toLowerCase().includes(q)
    )
  }

  if (statusFilter.value !== 'all') {
    list = list.filter(a => a.attended === statusFilter.value)
  }

  if (evalFilter.value !== 'all') {
    const want = evalFilter.value === 'yes'
    list = list.filter(a => a.evaluated === want)
  }

  return list
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const start = (page.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

function resetPage() { page.value = 1 }

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric',
  })
}

function dateRange(a) {
  if (!a.end_date || a.end_date === a.start_date) return formatDate(a.start_date)
  return `${formatDate(a.start_date)} – ${formatDate(a.end_date)}`
}
</script>

<template>
  <Head title="My Activities" />
  <AdminLayout title="My Activities">

    <div class="p-6 space-y-5">

      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold text-slate-800">My Activities</h1>
          <p class="text-sm text-slate-500 mt-0.5">Activities you have been enrolled in as a participant.</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-3 flex flex-wrap gap-3 items-center">
        <!-- Search -->
        <div class="relative flex-1 min-w-[200px]">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
          <input
            v-model="search"
            @input="resetPage"
            type="text"
            placeholder="Search by title or venue…"
            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>

        <!-- Status filter -->
        <select
          v-model="statusFilter"
          @change="resetPage"
          class="text-sm rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
        >
          <option value="all">All Status</option>
          <option value="yes">Present</option>
          <option value="no">Absent</option>
        </select>

        <!-- Evaluated filter -->
        <select
          v-model="evalFilter"
          @change="resetPage"
          class="text-sm rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
        >
          <option value="all">All Evaluation</option>
          <option value="yes">Evaluated</option>
          <option value="no">Not Evaluated</option>
        </select>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Activity</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Venue</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Evaluated</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Certificate</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-if="displayed.length === 0">
              <td colspan="7" class="px-4 py-12 text-center text-slate-400 text-sm">
                No activities found.
              </td>
            </tr>
            <tr v-for="a in displayed" :key="a.id" class="hover:bg-slate-50 transition-colors">
              <!-- Title -->
              <td class="px-4 py-3 font-medium text-slate-800 max-w-xs">
                {{ a.title }}
              </td>

              <!-- Date -->
              <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                <div class="flex items-center gap-1.5">
                  <CalendarDaysIcon class="w-4 h-4 text-slate-400 flex-shrink-0" />
                  {{ dateRange(a) }}
                </div>
              </td>

              <!-- Venue -->
              <td class="px-4 py-3 text-slate-600">
                <div v-if="a.venue" class="flex items-center gap-1.5">
                  <MapPinIcon class="w-4 h-4 text-slate-400 flex-shrink-0" />
                  {{ a.venue }}
                </div>
                <span v-else class="text-slate-400">—</span>
              </td>

              <!-- Attendance status -->
              <td class="px-4 py-3 text-center">
                <span v-if="a.attended === 'yes'"
                      class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                  <CheckCircleIcon class="w-3.5 h-3.5" /> Present
                </span>
                <span v-else
                      class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-600">
                  <XCircleIcon class="w-3.5 h-3.5" /> Absent
                </span>
              </td>

              <!-- Evaluated -->
              <td class="px-4 py-3 text-center">
                <span v-if="a.evaluated"
                      class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                  <ClipboardDocumentCheckIcon class="w-3.5 h-3.5" /> Done
                </span>
                <a v-else-if="a.attended === 'yes'"
                   :href="a.evaluate_url"
                   class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors">
                  <ClipboardDocumentListIcon class="w-3.5 h-3.5" /> Not Yet
                </a>
                <span v-else class="text-slate-400 text-xs">—</span>
              </td>

              <!-- Certificate -->
              <td class="px-4 py-3 text-center">
                <span v-if="a.has_certificate" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                  Ready
                </span>
                <span v-else class="text-slate-400 text-xs">—</span>
              </td>

              <!-- View -->
              <td class="px-4 py-3 text-right">
                <a :href="route('ams.my-activities.show', a.id)"
                   class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-sm font-medium transition-colors">
                  View <ArrowRightIcon class="w-4 h-4" />
                </a>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>
            Page {{ page }} of {{ totalPages }}
            &nbsp;·&nbsp; {{ filtered.length }} record{{ filtered.length !== 1 ? 's' : '' }}
          </span>
          <div class="flex gap-1">
            <button
              v-for="p in totalPages" :key="p"
              @click="page = p"
              class="w-8 h-8 rounded-lg text-sm font-medium transition-colors"
              :class="p === page
                ? 'bg-indigo-600 text-white'
                : 'text-slate-600 hover:bg-slate-100'"
            >{{ p }}</button>
          </div>
        </div>
        <div v-else class="px-4 py-3 border-t border-slate-100 text-sm text-slate-500">
          {{ filtered.length }} record{{ filtered.length !== 1 ? 's' : '' }}
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
