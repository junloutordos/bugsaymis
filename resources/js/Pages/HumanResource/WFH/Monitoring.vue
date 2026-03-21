<template>
  <Head title="WFH Monitoring" />
  <AdminLayout title="WFH Monitoring">
    <div class="space-y-5">

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <!-- Month picker -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Month</label>
          <input
            v-model="filterMonth"
            type="month"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
          />
        </div>

        <!-- Name search -->
        <div class="flex-1 min-w-[160px]">
          <label class="block text-xs font-medium text-slate-600 mb-1">Search Employee</label>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Name…"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
          />
        </div>

        <button
          @click="load"
          :disabled="loading"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
        >
          {{ loading ? 'Loading…' : 'Apply' }}
        </button>
      </div>

      <!-- Summary strip -->
      <div class="grid grid-cols-3 gap-4">
        <SummaryCard label="Total WFH Days" :value="summary.total" color="indigo" />
        <SummaryCard label="Completed (In + Out)" :value="summary.completed" color="green" />
        <SummaryCard label="Incomplete (No Time-Out)" :value="summary.incomplete" color="yellow" />
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div v-if="!filteredRows.length && !loading" class="py-16 text-center text-slate-400 text-sm">
          No WFH records found.
        </div>

        <div v-else class="overflow-x-auto rounded-xl border border-slate-100">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap w-6"></th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Time In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Time Out</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Duration</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Accomplishments</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Location</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Photos</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <template v-for="row in filteredRows" :key="row.id">
                <!-- Main row -->
                <tr
                  class="hover:bg-slate-50/60 cursor-pointer transition"
                  :class="{ 'bg-indigo-50': expanded === row.id }"
                  @click="toggleExpand(row.id)"
                >
                  <!-- Expand chevron -->
                  <td class="px-4 py-3 text-slate-400">
                    <svg
                      class="w-4 h-4 transition-transform"
                      :class="{ 'rotate-90': expanded === row.id }"
                      xmlns="http://www.w3.org/2000/svg" fill="none"
                      viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                  </td>

                  <td class="px-4 py-3 text-sm text-slate-700 font-medium">
                    {{ row.user?.name ?? '—' }}
                    <div class="text-xs text-slate-400 font-normal">{{ row.user?.position ?? '' }}</div>
                  </td>

                  <td class="px-4 py-3 text-sm text-slate-700">{{ formatDate(row.date) }}</td>
                  <td class="px-4 py-3 text-sm text-emerald-600 font-medium">{{ formatTime(row.time_in) }}</td>
                  <td class="px-4 py-3 text-sm text-blue-600 font-medium">{{ formatTime(row.time_out) }}</td>
                  <td class="px-4 py-3 text-sm text-slate-700">{{ calcDuration(row.time_in, row.time_out) }}</td>

                  <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold"
                          :class="row.accomplishments?.length ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-400'">
                      {{ row.accomplishments?.length ?? 0 }}
                    </span>
                  </td>

                  <td class="px-4 py-3 text-center">
                    <span
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                      :class="row.time_out
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-blue-50 text-blue-700'"
                    >
                      {{ row.time_out ? 'Complete' : 'In Progress' }}
                    </span>
                  </td>

                  <!-- Location -->
                  <td class="px-4 py-3 text-center">
                    <a v-if="row.latitude && row.longitude"
                       :href="`https://www.google.com/maps?q=${row.latitude},${row.longitude}`"
                       target="_blank"
                       @click.stop
                       title="View on Google Maps"
                       class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 hover:underline font-medium">
                      📍 View
                    </a>
                    <span v-else class="text-slate-300 text-xs">—</span>
                  </td>

                  <!-- Time-in / time-out photo thumbnails -->
                  <td class="px-4 py-3">
                    <div class="flex items-center justify-center gap-2">
                      <a v-if="row.time_in_photo_link"
                         :href="row.time_in_photo_link" target="_blank"
                         @click.stop title="Time-in photo">
                        <img :src="driveThumb(row.time_in_photo_link)"
                             class="w-10 h-10 rounded-lg object-cover border-2 border-emerald-300"
                             alt="In" />
                      </a>
                      <a v-if="row.time_out_photo_link"
                         :href="row.time_out_photo_link" target="_blank"
                         @click.stop title="Time-out photo">
                        <img :src="driveThumb(row.time_out_photo_link)"
                             class="w-10 h-10 rounded-lg object-cover border-2 border-blue-300"
                             alt="Out" />
                      </a>
                      <span v-if="!row.time_in_photo_link && !row.time_out_photo_link"
                            class="text-slate-300 text-xs">—</span>
                    </div>
                  </td>
                </tr>

                <!-- Expanded accomplishments row -->
                <tr v-if="expanded === row.id" class="bg-indigo-50">
                  <td colspan="10" class="px-8 pb-4 pt-2">
                    <p class="text-xs font-semibold text-indigo-700 mb-2 uppercase tracking-wide">
                      Accomplishments for {{ row.user?.name }}
                    </p>

                    <div v-if="!row.accomplishments?.length" class="text-sm text-slate-400">
                      No accomplishments recorded.
                    </div>

                    <ul v-else class="space-y-2">
                      <li
                        v-for="acc in row.accomplishments"
                        :key="acc.id"
                        class="bg-white rounded-lg border border-indigo-100 px-4 py-3 flex items-start justify-between gap-4"
                      >
                        <div class="flex-1 min-w-0">
                          <p class="font-semibold text-slate-800 text-sm">{{ acc.title }}</p>
                          <p v-if="acc.description" class="text-xs text-slate-500 mt-0.5 line-clamp-2">
                            {{ acc.description }}
                          </p>
                        </div>

                        <!-- Proof -->
                        <div class="flex-shrink-0">
                          <a
                            v-if="acc.proof_type === 'photo' && acc.google_drive_link"
                            :href="acc.google_drive_link"
                            target="_blank"
                            class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline"
                          >
                            📷 {{ acc.file_name ?? 'Photo' }}
                          </a>
                          <a
                            v-else-if="acc.proof_type === 'link' && acc.proof_link"
                            :href="acc.proof_link"
                            target="_blank"
                            class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline"
                          >
                            🔗 View Link
                          </a>
                          <span v-else class="text-xs text-slate-300">No proof</span>
                        </div>
                      </li>
                    </ul>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1"
             class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
          <div class="flex gap-2">
            <button
              @click="changePage(meta.current_page - 1)"
              :disabled="meta.current_page <= 1"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50"
            >Prev</button>
            <button
              @click="changePage(meta.current_page + 1)"
              :disabled="meta.current_page >= meta.last_page"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50"
            >Next</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import Swal from 'sweetalert2'

// ── Inline sub-component ──────────────────────────────────────────────────────
const SummaryCard = {
  props: { label: String, value: [Number, String], color: String },
  template: `
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-col gap-1">
      <p class="text-xs font-medium text-slate-500">{{ label }}</p>
      <p class="text-2xl font-bold" :class="textColor">{{ value }}</p>
    </div>`,
  computed: {
    textColor() {
      return { indigo: 'text-indigo-600', green: 'text-emerald-600', yellow: 'text-amber-600' }[this.color] ?? 'text-slate-800'
    },
  },
}

// ── State ─────────────────────────────────────────────────────────────────────
const rows        = ref([])
const loading     = ref(false)
const expanded    = ref(null)
const filterMonth = ref(currentMonth())
const searchQuery = ref('')
const meta        = ref({ current_page: 1, last_page: 1 })
let   currentPage = 1

// ── Summary ───────────────────────────────────────────────────────────────────
const summary = computed(() => ({
  total:      rows.value.length,
  completed:  rows.value.filter(r => r.time_out).length,
  incomplete: rows.value.filter(r => !r.time_out).length,
}))

// ── Filtered rows (client-side name search) ───────────────────────────────────
const filteredRows = computed(() => {
  if (!searchQuery.value.trim()) return rows.value
  const q = searchQuery.value.toLowerCase()
  return rows.value.filter(r => r.user?.name?.toLowerCase().includes(q))
})

// Override rows in template with filtered (re-bind template rows → filteredRows)
// Note: the table iterates `rows` but we reassign via computed; simpler to use filteredRows directly.

// ── Load ──────────────────────────────────────────────────────────────────────
async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await axios.get(route('hr.wfh.monitor'), {
      params: {
        month: filterMonth.value || undefined,
        page,
      },
    })
    rows.value     = data.data ?? []
    meta.value     = { current_page: data.current_page, last_page: data.last_page }
    currentPage    = data.current_page
    expanded.value = null
  } catch (err) {
    if (err.response?.status === 403) {
      Swal.fire('Unauthorized', 'You do not have permission to view this page.', 'error')
    } else {
      Swal.fire('Error', 'Could not load WFH records.', 'error')
    }
  } finally {
    loading.value = false
  }
}

function changePage(page) {
  if (page < 1 || page > meta.value.last_page) return
  load(page)
}

function toggleExpand(id) {
  expanded.value = expanded.value === id ? null : id
}

onMounted(() => load())

// ── Helpers ───────────────────────────────────────────────────────────────────
function driveThumb(url) {
  if (!url) return ''
  const match = url.match(/\/d\/([a-zA-Z0-9_-]+)/)
  if (match) return route('hr.wfh.photo', { fileId: match[1] })
  return url
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function formatTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
}

function calcDuration(inIso, outIso) {
  if (!inIso || !outIso) return '—'
  const ms = new Date(outIso) - new Date(inIso)
  if (ms < 0) return '—'
  const h = Math.floor(ms / 3600000)
  const m = Math.floor((ms % 3600000) / 60000)
  return `${h}h ${m}m`
}

function currentMonth() {
  return new Date().toISOString().slice(0, 7)
}
</script>
