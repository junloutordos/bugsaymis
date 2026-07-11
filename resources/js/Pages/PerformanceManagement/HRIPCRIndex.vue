<script setup>
import { Head, Link } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppTable from "@/Components/AppTable.vue"
import AppBadge from "@/Components/AppBadge.vue"
import EmptyState from "@/Components/EmptyState.vue"
import { EyeIcon } from "@heroicons/vue/24/outline"
import { ref, computed } from "vue"
import { useSubmit } from "@/Composables/useSubmit"
import { confirmAction } from "@/Composables/useConfirm.js"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"

const props = defineProps({
  ipcrs:         Array,
  ratingPeriods: { type: Array, default: () => [] }, // {id, label} pairs (id null on legacy rows)
})

const periodLabels   = computed(() => props.ratingPeriods.map(p => p.label))
const searchQuery    = ref("")
const selectedPeriod = ref("")
const adjectival     = ipcrAdjectivalRating

const filtered = computed(() => {
  const q = searchQuery.value.toLowerCase()
  return (props.ipcrs || []).filter(i => {
    const matchSearch  = !q || i.user?.name?.toLowerCase().includes(q) || i.title?.toLowerCase().includes(q) || i.rating_period?.toLowerCase().includes(q)
    const matchPeriod  = !selectedPeriod.value || i.rating_period === selectedPeriod.value
    return matchSearch && matchPeriod
  })
})

const formatDate = (val) => val ? new Date(val).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }) : "—"

// ── Batch Submit to PMT ───────────────────────────────────────
const submitToPMTPeriod = ref(props.ratingPeriods[0]?.label ?? "")
const submittedToHRCount = computed(() =>
  (props.ipcrs || []).filter(i =>
    i.status === 'Submitted to HR' &&
    (!submitToPMTPeriod.value || i.rating_period === submitToPMTPeriod.value)
  ).length
)

const { isSubmitting, submit } = useSubmit()

async function batchSubmitToPMT() {
  if (!submitToPMTPeriod.value) return
  if (await confirmAction({
    title: "Submit to PMT?",
    text: `Submit all ${submittedToHRCount.value} IPCR(s) for "${submitToPMTPeriod.value}" to PMT?`,
    confirmText: "Yes, submit!",
  })) {
    const period = props.ratingPeriods.find(p => p.label === submitToPMTPeriod.value)
    submit.post(route('hr-ipcr.batchSubmitToPMT'), {
      rating_period_id: period?.id ?? null,
      rating_period: submitToPMTPeriod.value,
    })
  }
}

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
  <Head title="HR IPCR Review" />
  <AdminLayout title="HR IPCR Review">
    <div class="space-y-5">

      <AppPageHeader title="HR IPCR Review">
        <template #actions>
          <div class="flex items-center gap-2 rounded-lg px-3 py-2 bg-white border border-slate-200 shadow-sm">
            <select
              v-model="submitToPMTPeriod"
              class="border-0 bg-transparent text-sm text-slate-700 focus:ring-0 p-0"
            >
              <option value="" disabled>— Period —</option>
              <option v-for="p in periodLabels" :key="p" :value="p">{{ p }}</option>
            </select>
            <AppButton
              size="sm"
              :loading="isSubmitting"
              :disabled="submittedToHRCount === 0 || !submitToPMTPeriod"
              @click="batchSubmitToPMT"
            >
              {{ isSubmitting ? 'Processing…' : `Submit to PMT (${submittedToHRCount})` }}
            </AppButton>
          </div>
        </template>
      </AppPageHeader>

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
      <AppTable :is-empty="!filtered.length" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Division</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Rating Period</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Title</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Avg Rating</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Submitted to HR</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="ipcr in filtered" :key="ipcr.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800 text-sm">{{ ipcr.user?.name ?? "—" }}</div>
            <div class="text-xs text-slate-500">{{ ipcr.user?.position ?? "" }}</div>
          </td>
          <td class="px-4 py-3 text-xs text-slate-600">{{ ipcr.user?.division?.name ?? "—" }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.rating_period ?? "—" }}</td>
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
          <td class="px-4 py-3 text-center text-xs text-slate-500">{{ formatDate(ipcr.submitted_to_hr_at) }}</td>
          <td class="px-4 py-3 text-center">
            <Link :href="route('hr-ipcr.show', ipcr.id)" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-xs font-medium">
              <EyeIcon class="w-4 h-4" /> View
            </Link>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="ipcr in filtered" :key="ipcr.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800 text-sm">{{ ipcr.user?.name ?? "—" }}</p>
                <p class="text-xs text-slate-500">{{ ipcr.user?.position ?? "" }}</p>
                <p class="text-xs text-slate-500">{{ ipcr.user?.division?.name ?? "—" }}</p>
              </div>
              <AppBadge :color="statusBadgeColor(ipcr.status)">{{ ipcr.status }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ ipcr.title ?? "—" }} &middot; {{ ipcr.rating_period ?? "—" }}</p>
            <div class="flex justify-between text-xs text-slate-500">
              <span v-if="ipcr.overall_average">Avg {{ ipcr.overall_average }} &middot; {{ adjectival(ipcr.overall_average) }}</span>
              <span v-else>—</span>
              <span>Submitted {{ formatDate(ipcr.submitted_to_hr_at) }}</span>
            </div>
            <div class="flex items-center justify-end pt-1">
              <Link :href="route('hr-ipcr.show', ipcr.id)" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                <EyeIcon class="w-4 h-4" /> View
              </Link>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No IPCRs submitted to HR." />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
