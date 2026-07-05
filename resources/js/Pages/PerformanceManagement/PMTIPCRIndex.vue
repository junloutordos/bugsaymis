<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppTable from "@/Components/AppTable.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import EmptyState from "@/Components/EmptyState.vue"
import { EyeIcon } from "@heroicons/vue/24/outline"
import { ref, computed } from "vue"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"

const props = defineProps({
  ipcrs: Array,
})

const searchQuery = ref("")

const adjectivalRating = ipcrAdjectivalRating

const filteredIPCRs = computed(() => {
  const q = searchQuery.value.toLowerCase()
  if (!q) return props.ipcrs
  return props.ipcrs.filter(i =>
    i.title?.toLowerCase().includes(q) ||
    i.rating_period?.toLowerCase().includes(q) ||
    i.status?.toLowerCase().includes(q) ||
    i.user?.name?.toLowerCase().includes(q) ||
    i.user?.division?.division_name?.toLowerCase().includes(q)
  )
})

// Maps each distinct IPCR status string to the closest AppBadge color.
// The raw status label itself is always rendered verbatim — this only
// controls badge color, so no state-machine states are collapsed.
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
  <Head title="PMT IPCR Review" />
  <AdminLayout title="PMT IPCR Review">
    <div class="space-y-5">

      <AppPageHeader title="IPCR Review — Performance Management Team" />

      <!-- Filter bar -->
      <AppFilterBar>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by employee, title, period, division..."
          class="flex-1 min-w-52 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        />
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!filteredIPCRs.length" :skeleton-cols="9">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Division</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Rating Period</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Title</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Overall Avg.</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Adjectival Rating</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Submitted to PMT</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="ipcr in filteredIPCRs" :key="ipcr.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 font-medium text-slate-800">{{ ipcr.user?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.user?.division?.division_name ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.rating_period }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.title }}</td>
          <td class="px-4 py-3 text-center font-semibold text-slate-800">{{ ipcr.overall_average ?? '—' }}</td>
          <td class="px-4 py-3 text-center text-sm text-slate-700">{{ adjectivalRating(ipcr.overall_average) }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusBadgeColor(ipcr.status)">{{ ipcr.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center text-xs text-slate-500">{{ ipcr.submitted_for_pmtreview_at ?? '—' }}</td>
          <td class="px-4 py-3 text-center">
            <AppIconButton label="Review IPCR" @click="router.visit(route('pmt-ipcr.show', ipcr.id))">
              <EyeIcon class="w-4 h-4" />
            </AppIconButton>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="ipcr in filteredIPCRs" :key="ipcr.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ ipcr.user?.name ?? '—' }}</p>
                <p class="text-xs text-slate-500">{{ ipcr.user?.division?.division_name ?? '—' }}</p>
                <p class="text-xs text-slate-500">{{ ipcr.title }} &middot; {{ ipcr.rating_period }}</p>
              </div>
              <AppBadge :color="statusBadgeColor(ipcr.status)">{{ ipcr.status }}</AppBadge>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
              <span>Avg {{ ipcr.overall_average ?? '—' }} &middot; {{ adjectivalRating(ipcr.overall_average) }}</span>
              <span>Submitted {{ ipcr.submitted_for_pmtreview_at ?? '—' }}</span>
            </div>
            <div class="flex items-center justify-end pt-1">
              <AppIconButton label="Review IPCR" @click="router.visit(route('pmt-ipcr.show', ipcr.id))">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No IPCRs submitted for PMT review." />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
