<script setup>
import { ref, computed } from "vue"
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppTable from "@/Components/AppTable.vue"
import AppButton from "@/Components/AppButton.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppModal from "@/Components/AppModal.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import EmptyState from "@/Components/EmptyState.vue"
import { PlusIcon, LockClosedIcon, LockOpenIcon, StarIcon, DocumentDuplicateIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import { useSubmit } from "@/Composables/useSubmit"
import { confirmAction } from "@/Composables/useConfirm.js"

const props = defineProps({
  periods: Array, // IPCRRatingPeriod rows with ipcrs_count
})

const { isSubmitting, submit } = useSubmit()

// Group periods by fiscal year (PH government FY = calendar year), newest first
const groupedByYear = computed(() => {
  const groups = {}
  for (const p of props.periods ?? []) {
    if (!groups[p.year]) groups[p.year] = []
    groups[p.year].push(p)
  }
  return Object.keys(groups)
    .sort((a, b) => b - a)
    .map(year => ({ year, periods: groups[year].sort((a, b) => (a.semester ?? 0) - (b.semester ?? 0)) }))
})

const years = computed(() => groupedByYear.value.map(g => g.year))

const formatDate = (d) => d
  ? new Date(d).toLocaleDateString("en-PH", { year: "numeric", month: "long", day: "numeric" })
  : "—"

// ── Add Fiscal Year ───────────────────────────────────────────
const showAddModal = ref(false)
const newYear = ref(new Date().getFullYear() + 1)

const previewSemesters = computed(() => [
  `January 1 to June 30, ${newYear.value}`,
  `July 1 to December 31, ${newYear.value}`,
])

const addFiscalYear = () => {
  submit.post(route("ipcr-rating-periods.storeFiscalYear"), { year: Number(newYear.value) }, {
    onSuccess: () => { showAddModal.value = false },
    onError: (e) => Swal.fire({ icon: "error", title: "Cannot create fiscal year", text: Object.values(e)[0] ?? "Validation failed." }),
  })
}

// ── Close / Reopen / Set current ──────────────────────────────
const closePeriod = async (period) => {
  const confirmed = await confirmAction({
    title: "Close this rating period?",
    text: `"${period.label}" will become read-only: no IPCR in it can be created, edited, rated, or moved through the workflow until it is reopened. ${period.ipcrs_count} IPCR(s) are in this period.`,
    confirmText: "Yes, close it",
  })
  if (confirmed) submit.post(route("ipcr-rating-periods.close", period.id))
}

const reopenPeriod = async (period) => {
  const confirmed = await confirmAction({
    title: "Reopen this rating period?",
    text: `"${period.label}" will accept changes again.`,
    confirmText: "Yes, reopen",
  })
  if (confirmed) submit.post(route("ipcr-rating-periods.reopen", period.id))
}

const setCurrent = async (period) => {
  const confirmed = await confirmAction({
    title: "Set as current period?",
    text: `"${period.label}" will be marked as the current rating period (used as the default fiscal year across Performance Management).`,
    confirmText: "Yes, set current",
  })
  if (confirmed) submit.post(route("ipcr-rating-periods.setCurrent", period.id))
}

// ── Copy framework ────────────────────────────────────────────
const showCopyModal = ref(false)
const copySourceYear = ref("")
const copyTargetYear = ref("")

const openCopyModal = (targetYear) => {
  copyTargetYear.value = String(targetYear)
  const prev = years.value.find(y => Number(y) < Number(targetYear))
  copySourceYear.value = prev ? String(prev) : ""
  showCopyModal.value = true
}

const copyFramework = () => {
  submit.post(route("ipcr-rating-periods.copyFramework"), {
    source_year: Number(copySourceYear.value),
    target_year: Number(copyTargetYear.value),
  }, {
    onSuccess: () => { showCopyModal.value = false },
    onError: (e) => Swal.fire({ icon: "error", title: "Copy failed", text: Object.values(e)[0] ?? "Validation failed." }),
  })
}
</script>

<template>
  <Head title="Rating Periods" />
  <AdminLayout title="Rating Periods">
    <div class="p-6 space-y-5">

      <AppPageHeader
        title="IPCR Rating Periods"
        subtitle="Fiscal years and semestral rating periods per CSC MC No. 6, s. 2012 (SPMS). Closing a period makes everything in it read-only."
      >
        <template #actions>
          <AppButton @click="showAddModal = true">
            <PlusIcon class="w-4 h-4" /> Add Fiscal Year
          </AppButton>
        </template>
      </AppPageHeader>

      <div v-if="!groupedByYear.length">
        <EmptyState title="No rating periods yet." subtitle="Add a fiscal year to create its two semestral periods." />
      </div>

      <div v-for="group in groupedByYear" :key="group.year" class="space-y-2">
        <div class="flex items-center justify-between">
          <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Fiscal Year {{ group.year }}</h2>
          <AppButton size="sm" variant="secondary" @click="openCopyModal(group.year)">
            <DocumentDuplicateIcon class="w-4 h-4" /> Copy framework into FY {{ group.year }}
          </AppButton>
        </div>

        <AppTable :is-empty="!group.periods.length" :skeleton-cols="7">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Rating Period</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Semester</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Start</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">End</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">IPCRs</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
            </tr>
          </template>

          <tr v-for="p in group.periods" :key="p.id" class="hover:bg-slate-50/60">
            <td class="px-4 py-3 text-sm text-slate-700">
              {{ p.label }}
              <AppBadge v-if="p.is_current" color="indigo" class="ml-2">Current</AppBadge>
            </td>
            <td class="px-4 py-3 text-sm text-slate-700">{{ p.semester ? `Semester ${p.semester}` : '—' }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ formatDate(p.start_date) }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ formatDate(p.end_date) }}</td>
            <td class="px-4 py-3 text-center text-sm text-slate-700">{{ p.ipcrs_count }}</td>
            <td class="px-4 py-3 text-center">
              <AppBadge :color="p.status === 'open' ? 'green' : 'slate'">{{ p.status === 'open' ? 'Open' : 'Closed' }}</AppBadge>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-center gap-1.5">
                <AppButton v-if="p.status === 'open' && !p.is_current" size="sm" variant="ghost" :disabled="isSubmitting" @click="setCurrent(p)">
                  <StarIcon class="w-4 h-4" /> Set Current
                </AppButton>
                <AppButton v-if="p.status === 'open'" size="sm" variant="danger" :disabled="isSubmitting" @click="closePeriod(p)">
                  <LockClosedIcon class="w-4 h-4" /> Close
                </AppButton>
                <AppButton v-else size="sm" variant="secondary" :disabled="isSubmitting" @click="reopenPeriod(p)">
                  <LockOpenIcon class="w-4 h-4" /> Reopen
                </AppButton>
              </div>
            </td>
          </tr>

          <template #empty>
            <EmptyState title="No periods for this year." />
          </template>
        </AppTable>
      </div>
    </div>

    <!-- Add Fiscal Year Modal -->
    <AppModal :show="showAddModal" title="Add Fiscal Year" @close="showAddModal = false">
      <div class="space-y-4">
        <AppInput v-model="newYear" label="Fiscal Year" type="number" min="2000" max="2100" />
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Two semestral periods will be created (open):</p>
          <ul class="text-sm text-slate-700 space-y-1">
            <li v-for="label in previewSemesters" :key="label">• {{ label }}</li>
          </ul>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showAddModal = false">Cancel</AppButton>
        <AppButton :loading="isSubmitting" :disabled="isSubmitting" @click="addFiscalYear">Create</AppButton>
      </template>
    </AppModal>

    <!-- Copy Framework Modal -->
    <AppModal :show="showCopyModal" title="Copy Performance Framework" @close="showCopyModal = false">
      <div class="space-y-4">
        <p class="text-sm text-slate-600">
          Deep-copies the Agency Outcomes → Performance Indicators → Work Distribution Plans chain
          (including division, office, committee, special-assignment, and personnel links) from the source
          fiscal year into FY {{ copyTargetYear }}. Only allowed when the target year has no framework entries yet.
        </p>
        <AppSelect v-model="copySourceYear" label="Copy from Fiscal Year" placeholder="-- Select source year --">
          <option v-for="y in years.filter(y => y !== copyTargetYear)" :key="y" :value="y">FY {{ y }}</option>
        </AppSelect>
        <AppInput v-model="copyTargetYear" label="Into Fiscal Year" disabled />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showCopyModal = false">Cancel</AppButton>
        <AppButton :loading="isSubmitting" :disabled="isSubmitting || !copySourceYear" @click="copyFramework">Copy Framework</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
