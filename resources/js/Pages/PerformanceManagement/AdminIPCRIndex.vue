<script setup>
import { Head, Link } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppTable from "@/Components/AppTable.vue"
import AppBadge from "@/Components/AppBadge.vue"
import EmptyState from "@/Components/EmptyState.vue"
import { EyeIcon } from "@heroicons/vue/24/outline"
import { ref, computed, watch } from "vue"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"

const props = defineProps({
  ipcrs:         Array,
  statuses:      { type: Array, default: () => [] },
  statusCounts:  { type: Object, default: () => ({}) },
  ratingPeriods: { type: Array, default: () => [] }, // {id, label} pairs (id null on legacy rows)
})

const periodLabels   = computed(() => props.ratingPeriods.map(p => p.label))
const searchQuery    = ref("")
const selectedPeriod = ref("")
const selectedStatus = ref("")
const adjectival     = ipcrAdjectivalRating

const filtered = computed(() => {
  const q = searchQuery.value.toLowerCase()
  return (props.ipcrs || []).filter(i => {
    const matchSearch = !q || i.user?.name?.toLowerCase().includes(q) || i.title?.toLowerCase().includes(q) || i.rating_period?.toLowerCase().includes(q)
    const matchPeriod = !selectedPeriod.value || (i.period?.label ?? i.rating_period) === selectedPeriod.value
    const matchStatus = !selectedStatus.value || i.status === selectedStatus.value
    return matchSearch && matchPeriod && matchStatus
  })
})

const formatDate = (val) => val ? new Date(val).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }) : "—"

// ── Local pagination ────────────────────────────────────────────
const PER_PAGE = 15
const currentPage = ref(1)
const totalPages  = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed    = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})
watch([searchQuery, selectedPeriod, selectedStatus], () => { currentPage.value = 1 })

// Maps each distinct IPCR status string to the closest AppBadge color.
function statusBadgeColor(status) {
  const map = {
    'New Target':                'blue',
    'For Review':                'amber',
    'Targets Approved':          'green',
    'Submitted for Rating':      'orange',
    'Rated & For PMT Review':    'purple',
    'Submitted to PMT':          'purple',
    'PMT Returned for Revision': 'red',
    'Submitted to HR':           'blue',
    'Approved by PMT':           'green',
    'Director Signed':           'green',
    'Returned for Revision':     'red',
    'Rejected':                  'red',
  }
  return map[status] ?? 'slate'
}
</script>

<template>
  <Head title="IPCR Monitoring (All Stages)" />
  <AdminLayout title="IPCR Monitoring — All Stages">
    <div class="space-y-5">

      <AppPageHeader title="IPCR Monitoring" subtitle="Every IPCR across the workflow — Division Chief, HR, PMT, and beyond." />

      <!-- Stage pills -->
      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          :class="['px-3 py-1.5 rounded-full text-xs font-medium border transition-colors',
            selectedStatus === '' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-300']"
          @click="selectedStatus = ''"
        >
          All ({{ ipcrs.length }})
        </button>
        <button
          v-for="s in statuses" :key="s"
          type="button"
          :class="['px-3 py-1.5 rounded-full text-xs font-medium border transition-colors',
            selectedStatus === s ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-300']"
          @click="selectedStatus = selectedStatus === s ? '' : s"
        >
          {{ s }} ({{ statusCounts[s] ?? 0 }})
        </button>
      </div>

      <!-- Filter bar -->
      <AppFilterBar>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by employee, title, or period..."
          class="flex-1 min-w-52 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        />
        <select
          v-model="selectedPeriod"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        >
          <option value="">All Periods</option>
          <option v-for="p in periodLabels" :key="p" :value="p">{{ p }}</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!filtered.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Employee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Division</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Rating Period</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Title</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Stage</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Avg Rating</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="ipcr in displayed" :key="ipcr.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800 text-sm">{{ ipcr.user?.name ?? "—" }}</div>
            <div class="text-xs text-slate-500">{{ ipcr.user?.position ?? "" }}</div>
          </td>
          <td class="px-4 py-3 text-xs text-slate-600">{{ ipcr.user?.division?.name ?? "—" }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.period?.label ?? ipcr.rating_period ?? "—" }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.title ?? "—" }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusBadgeColor(ipcr.status)">{{ ipcr.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <template v-if="ipcr.overall_average">
              <div class="font-semibold text-slate-800 text-sm">{{ ipcr.overall_average }}</div>
              <div class="text-xs text-slate-400">{{ adjectival(ipcr.overall_average) }}</div>
            </template>
            <span v-else class="text-slate-400">—</span>
          </td>
          <td class="px-4 py-3 text-center">
            <Link :href="route('admin-ipcr.show', ipcr.id)" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-xs font-medium">
              <EyeIcon class="w-4 h-4" /> View
            </Link>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="ipcr in displayed" :key="ipcr.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800 text-sm">{{ ipcr.user?.name ?? "—" }}</p>
                <p class="text-xs text-slate-500">{{ ipcr.user?.position ?? "" }}</p>
                <p class="text-xs text-slate-500">{{ ipcr.user?.division?.name ?? "—" }}</p>
              </div>
              <AppBadge :color="statusBadgeColor(ipcr.status)">{{ ipcr.status }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ ipcr.title ?? "—" }} &middot; {{ ipcr.period?.label ?? ipcr.rating_period ?? "—" }}</p>
            <div class="flex justify-between text-xs text-slate-500">
              <span v-if="ipcr.overall_average">Avg {{ ipcr.overall_average }} &middot; {{ adjectival(ipcr.overall_average) }}</span>
              <span v-else>—</span>
              <Link :href="route('admin-ipcr.show', ipcr.id)" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-medium">
                <EyeIcon class="w-4 h-4" /> View
              </Link>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No IPCRs match the current filters." />
        </template>
      </AppTable>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between text-sm text-slate-500">
        <span>Page {{ currentPage }} of {{ totalPages }} ({{ filtered.length }} total)</span>
        <div class="flex gap-2">
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white disabled:opacity-40 disabled:cursor-not-allowed hover:border-indigo-300"
            :disabled="currentPage === 1"
            @click="currentPage--"
          >Previous</button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white disabled:opacity-40 disabled:cursor-not-allowed hover:border-indigo-300"
            :disabled="currentPage === totalPages"
            @click="currentPage++"
          >Next</button>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
