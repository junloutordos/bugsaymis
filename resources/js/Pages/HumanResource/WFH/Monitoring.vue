<template>
  <Head title="WFH Monitoring" />
  <AdminLayout title="WFH Monitoring">
    <div class="space-y-5">

      <AppPageHeader title="WFH Monitoring" subtitle="Review employee work-from-home time logs and accomplishments." />

      <!-- Filters -->
      <AppFilterBar>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Month</label>
          <input
            v-model="filterMonth"
            type="month"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>

        <div class="flex-1 min-w-[160px]">
          <label class="block text-xs font-medium text-slate-600 mb-1">Search Employee</label>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Name…"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
          />
        </div>

        <template #actions>
          <AppButton :loading="loading" @click="load()">Apply</AppButton>
        </template>
      </AppFilterBar>

      <!-- Summary strip -->
      <div class="grid grid-cols-3 gap-4">
        <AppCard class="text-center">
          <p class="text-xs text-slate-500 mb-1">Total WFH Days</p>
          <p class="text-2xl font-bold text-indigo-600">{{ summary.total }}</p>
        </AppCard>
        <AppCard class="text-center">
          <p class="text-xs text-slate-500 mb-1">Completed (In + Out)</p>
          <p class="text-2xl font-bold text-success-700">{{ summary.completed }}</p>
        </AppCard>
        <AppCard class="text-center">
          <p class="text-xs text-slate-500 mb-1">Incomplete (No Time-Out)</p>
          <p class="text-2xl font-bold text-warning-700">{{ summary.incomplete }}</p>
        </AppCard>
      </div>

      <!-- Table -->
      <AppTable :loading="loading" :is-empty="!filteredRows.length" :skeleton-cols="10">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap w-6"></th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Employee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Time In</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Time Out</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Duration</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Accomplishments</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Location</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Photos</th>
          </tr>
        </template>

        <template v-for="row in filteredRows" :key="row.id">
          <!-- Main row -->
          <tr
            class="hover:bg-indigo-50/40 cursor-pointer transition"
            :class="{ 'bg-indigo-50': expanded === row.id }"
            @click="toggleExpand(row.id)"
          >
            <!-- Expand chevron -->
            <td class="px-4 py-3 text-slate-400">
              <ChevronRightIcon
                class="w-4 h-4 transition-transform"
                :class="{ 'rotate-90': expanded === row.id }"
              />
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
              <AppBadge :color="row.accomplishments?.length ? 'indigo' : 'slate'">
                {{ row.accomplishments?.length ?? 0 }}
              </AppBadge>
            </td>

            <td class="px-4 py-3 text-center">
              <AppBadge :color="statusColor(row)">{{ statusLabel(row) }}</AppBadge>
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

        <template #mobileCard>
          <div v-for="row in filteredRows" :key="row.id" class="p-4 space-y-2">
            <button class="w-full text-left" @click="toggleExpand(row.id)">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-medium text-slate-800 text-sm">{{ row.user?.name ?? '—' }}</p>
                  <p class="text-xs text-slate-400">{{ row.user?.position ?? '' }}</p>
                </div>
                <AppBadge :color="statusColor(row)">{{ statusLabel(row) }}</AppBadge>
              </div>
              <div class="flex justify-between text-xs text-slate-500 mt-2">
                <span>{{ formatDate(row.date) }}</span>
                <span>{{ calcDuration(row.time_in, row.time_out) }}</span>
              </div>
              <div class="flex justify-between text-xs mt-1">
                <span class="text-emerald-600 font-medium">In {{ formatTime(row.time_in) }}</span>
                <span class="text-blue-600 font-medium">Out {{ formatTime(row.time_out) }}</span>
              </div>
              <div class="flex items-center justify-between mt-2">
                <AppBadge :color="row.accomplishments?.length ? 'indigo' : 'slate'">
                  📝 {{ row.accomplishments?.length ?? 0 }}
                </AppBadge>
                <a v-if="row.latitude && row.longitude"
                   :href="`https://www.google.com/maps?q=${row.latitude},${row.longitude}`"
                   target="_blank"
                   @click.stop
                   class="text-xs text-indigo-600 hover:underline font-medium">
                  📍 Location
                </a>
              </div>
            </button>

            <div v-if="expanded === row.id" class="pt-2 border-t border-slate-100 space-y-2">
              <p class="text-xs font-semibold text-indigo-700 uppercase tracking-wide">Accomplishments</p>
              <div v-if="!row.accomplishments?.length" class="text-sm text-slate-400">
                No accomplishments recorded.
              </div>
              <ul v-else class="space-y-2">
                <li
                  v-for="acc in row.accomplishments"
                  :key="acc.id"
                  class="bg-slate-50 rounded-lg border border-slate-100 px-3 py-2"
                >
                  <p class="font-semibold text-slate-800 text-sm">{{ acc.title }}</p>
                  <p v-if="acc.description" class="text-xs text-slate-500 mt-0.5 line-clamp-2">
                    {{ acc.description }}
                  </p>
                </li>
              </ul>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No WFH records found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="meta.current_page"
            :total-pages="meta.last_page"
            @prev="changePage(meta.current_page - 1)"
            @next="changePage(meta.current_page + 1)"
            @page="changePage"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'
import Swal from 'sweetalert2'

// ── State ─────────────────────────────────────────────────────────────────────
const rows        = ref([])
const loading     = ref(false)
const expanded    = ref(null)
const filterMonth = ref(currentMonth())
const searchQuery = ref('')
const meta        = ref({ current_page: 1, last_page: 1 })

// ── Summary ───────────────────────────────────────────────────────────────────
const summary = computed(() => ({
  total:      rows.value.length,
  completed:  rows.value.filter(r => r.time_out).length,
  incomplete: rows.value.filter(r => !r.time_out).length,
}))

// Rows is already the server-filtered result; template iterates it directly.
const filteredRows = computed(() => rows.value)

// ── Badge helpers ─────────────────────────────────────────────────────────────
function statusColor(row) {
  return row.time_out ? 'green' : 'blue'
}

function statusLabel(row) {
  return row.time_out ? 'Complete' : 'In Progress'
}

// ── Load ──────────────────────────────────────────────────────────────────────
async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await axios.get('/hr/wfh/monitor/data', {
      params: {
        month:  filterMonth.value || undefined,
        search: searchQuery.value.trim() || undefined,
        page,
      },
    })
    rows.value     = data.data ?? []
    meta.value     = { current_page: data.current_page, last_page: data.last_page }
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
  if (match) return `/hr/wfh/photo/${match[1]}`
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
