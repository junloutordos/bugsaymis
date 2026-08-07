<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppTable from "@/Components/AppTable.vue"
import AppButton from "@/Components/AppButton.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppModal from "@/Components/AppModal.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import EmptyState from "@/Components/EmptyState.vue"
import PaginationControl from "@/Components/PaginationControl.vue"
import { EyeIcon } from "@heroicons/vue/24/outline"
import useDivisionChiefIPCR from "@/Composables/useIPCRDC.js"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { ref, computed, watch } from "vue"
import { useSubmit } from "@/Composables/useSubmit"
import { confirmAction } from "@/Composables/useConfirm.js"

const props = defineProps({
  ipcrs:             Array,
  divisionEmployees: { type: Array, default: () => [] },
  workPlans:         Array,
  supervisor:        Object,
  ratingPeriods:     { type: Array, default: () => [] }, // {id, label} pairs from visible IPCRs (id null on legacy rows)
  openPeriods:       { type: Array, default: () => [] }, // open IPCRRatingPeriod objects for the create modal
  canEndorseAny:     { type: Boolean, default: false },  // Division Chiefs only — batch submit to HR
})

const periodLabels = computed(() => props.ratingPeriods.map(p => p.label))

const {
  workPlans: workPlansList,
  planSearch,
  showModal, showAddPlansModal, modalMode, selectedIPCR, selectedPlans,
  form, isPlanSelected, togglePlanSelection,
  openModal, closeModal, submitIPCR,
  openAddPlansModal, closeAddPlansModal, submitPlans,
  viewIPCR, destroyIPCR, statusClasses,
  filteredPlans,
} = useDivisionChiefIPCR(props.ipcrs, props.workPlans)

// ---------- Filters ----------
const searchQuery    = ref("")
const selectedPeriod = ref("")

const filtered = computed(() => {
  const q = searchQuery.value.toLowerCase()
  return (props.ipcrs || []).filter(ipcr => {
    const matchesSearch =
      !q ||
      ipcr.user?.name?.toLowerCase().includes(q) ||
      ipcr.title?.toLowerCase().includes(q) ||
      ipcr.rating_period?.toLowerCase().includes(q) ||
      ipcr.status?.toLowerCase().includes(q)
    const matchesPeriod = !selectedPeriod.value || ipcr.rating_period === selectedPeriod.value
    return matchesSearch && matchesPeriod
  })
})

// ---------- Pagination ----------
const perPage     = 10
const currentPage = ref(1)
const totalPages  = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const paginated   = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filtered.value.slice(start, start + perPage)
})
const goToPage  = (p) => { if (p >= 1 && p <= totalPages.value) currentPage.value = p }
const resetPage = () => { currentPage.value = 1 }

watch([searchQuery, selectedPeriod], () => resetPage())

// ---------- Employees without IPCR ----------
const employeesWithoutIpcr = computed(() => {
  const ipcrUserIds = new Set(props.ipcrs.map(i => i.user?.id).filter(Boolean))
  return props.divisionEmployees.filter(emp => !ipcrUserIds.has(emp.id))
})

const adjectivalRating = ipcrAdjectivalRating

const formatDate = (val) => {
  if (!val) return "—"
  return new Date(val).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" })
}

// ---------- Status badge color mapping (AppBadge) ----------
const ipcrBadgeColor = (status) => {
  const map = {
    "New Target":                "blue",
    "For Review":                 "amber",
    "Targets Approved":           "green",
    "Submitted for Rating":       "orange",
    "Rated & For PMT Review":     "purple",
    "Submitted to PMT":           "purple",
    "PMT Returned for Revision":  "red",
    "Submitted to HR":            "blue",
    "Approved by PMT":            "green",
    "Director Signed":            "green",
    "Returned for Revision":      "red",
    "Rejected":                   "red",
  }
  return map[status] ?? "slate"
}

// ── Submit to HR ─────────────────────────────────────────────
const { isSubmitting, submit } = useSubmit()

const submitToHRPeriod = ref(props.ratingPeriods[0]?.label ?? "")
const ratedForHRCount  = computed(() =>
  props.ipcrs.filter(i =>
    i.status === 'Rated & For PMT Review' &&
    (!submitToHRPeriod.value || (i.period?.label ?? i.rating_period) === submitToHRPeriod.value)
  ).length
)

const submitToHR = async () => {
  if (!submitToHRPeriod.value) return
  const confirmed = await confirmAction({
    title: "Submit to HR?",
    text: `Submit all ${ratedForHRCount.value} rated IPCR(s) for "${submitToHRPeriod.value}" to HR?`,
    confirmText: "Yes, submit!",
  })
  if (confirmed) {
    const period = props.ratingPeriods.find(p => p.label === submitToHRPeriod.value)
    submit.post(route('division-chief-ipcr.submitToHR'), {
      rating_period_id: period?.id ?? null,
      rating_period: submitToHRPeriod.value,
    })
  }
}

// ── Memo Report ──────────────────────────────────────────────
const showReportModal = ref(false)
const reportTo        = ref('')
const reportPeriod    = ref('')

const ratedStatusList = ['Rated & For PMT Review', 'Submitted to PMT', 'PMT Returned for Revision', 'Approved by PMT']

const ratedIPCRs = computed(() =>
  props.ipcrs.filter(i =>
    ratedStatusList.includes(i.status) &&
    (!reportPeriod.value || (i.period?.label ?? i.rating_period) === reportPeriod.value)
  )
)

const openReportModal = () => {
  reportPeriod.value = props.ratingPeriods[0]?.label ?? ''
  reportTo.value = ''
  showReportModal.value = true
}

const today = new Date().toLocaleDateString('en-PH', {
  year: 'numeric', month: 'long', day: 'numeric'
})

const printMemo = () => {
  showReportModal.value = false

  const division = props.supervisor?.division?.name?.toUpperCase() ?? 'DIVISION'
  const period   = reportPeriod.value?.toUpperCase() || '____________________________'
  const to       = reportTo.value || '________________________________'
  const from     = props.supervisor?.name ?? '________________________________'
  const fromPos  = props.supervisor?.position ?? ''

  const rows = ratedIPCRs.value.map(ipcr => `
    <tr>
      <td>${ipcr.user?.name?.toUpperCase() ?? '—'}</td>
      <td style="text-align:center">${ipcr.overall_average ?? '—'}</td>
      <td style="text-align:center">${adjectivalRating(ipcr.overall_average)}</td>
    </tr>`).join('')

  const html = `<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8"/>
  <title>IPCR Memo Report</title>
  <style>
    @page { size: A4 portrait; margin: 20mm; }
    body { font-family: Arial, sans-serif; font-size: 10px; color: #000; }
    .logos { display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 8px; }
    .logos img { width: 50px; height: 50px; object-fit: contain; }
    .header-text { text-align: center; flex: 1; }
    .header-text .republic { font-size: 9px; }
    .header-text .agency   { font-size: 9px; }
    .header-text .school   { font-size: 12px; font-weight: bold; }
    .header-text .campus   { font-size: 10px; font-weight: bold; }
    .header-text .address  { font-size: 9px; }
    hr { border: none; border-top: 2px solid #000; margin: 6px 0; }
    .memo-title { text-align: center; font-size: 14px; font-weight: bold; letter-spacing: 3px; margin: 10px 0; }
    .fields { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .fields td { padding: 3px 2px; vertical-align: top; }
    .fields .label { font-weight: bold; width: 70px; }
    .fields .colon { width: 12px; }
    .fields .subject { font-weight: bold; }
    .body-text { text-align: justify; line-height: 1.7; margin: 12px 0; }
    table.ratings { width: 100%; border-collapse: collapse; margin: 12px 0; }
    table.ratings th, table.ratings td { border: 1px solid #000; padding: 4px 8px; }
    table.ratings th { background: #ccc; text-align: center; font-weight: bold; }
    .sig-block { margin-top: 30px; margin-left: 40px; }
    .sig-name { font-weight: bold; border-top: 1px solid #000; display: inline-block; padding-top: 3px; min-width: 200px; }
    .sig-pos  { font-size: 9px; margin-top: 2px; }
    .footer { position: fixed; bottom: 10mm; left: 0; right: 0; text-align: center; font-size: 8px; border-top: 1px solid #000; padding-top: 3px; color: #555; }
  </style>
</head>
<body>
  <div class="logos">
    <img src="/images/republic_seal.png" onerror="this.style.display='none'" />
    <img src="/images/dost_logo.png" onerror="this.style.display='none'" />
    <div class="header-text">
      <div class="republic">Republic of the Philippines</div>
      <div class="agency">Department of Science and Technology</div>
      <div class="school">PHILIPPINE SCIENCE HIGH SCHOOL</div>
      <div class="campus">Caraga Region Campus</div>
      <div class="address">Ampayon, Butuan City</div>
    </div>
    <img src="/images/pshslogo.png" onerror="this.style.display='none'" />
    <img src="/images/bagong_pilipinas.png" onerror="this.style.display='none'" />
  </div>
  <hr/>
  <div class="memo-title">MEMORANDUM</div>
  <table class="fields">
    <tr><td class="label">TO</td><td class="colon">:</td><td>${to}</td></tr>
    <tr><td class="label">FROM</td><td class="colon">:</td><td>${from}<br/><small>${fromPos}</small></td></tr>
    <tr><td class="label">DATE</td><td class="colon">:</td><td>${today}</td></tr>
    <tr><td class="label">SUBJECT</td><td class="colon">:</td>
        <td class="subject">SUBMISSION OF ${division} ASSESSED IPCR FOR ${period} AND COACHING AND MENTORING FORMS</td></tr>
  </table>
  <hr/>
  <p class="body-text">
    Transmitted herewith are the assessed Individual Performance Commitment and Review Form (IPCR)
    of <strong>${division} PERSONNEL</strong> for <strong>${period}</strong>
    pursuant to Civil Service Commission (CSC) Memorandum Circular No. 06, s. 2012
    otherwise known as SPMS Guidelines.
  </p>
  <table class="ratings">
    <thead>
      <tr>
        <th>NAME OF PERSONNEL</th>
        <th>PERFORMANCE RATING FOR THE PERIOD<br/>${period}</th>
        <th>ADJECTIVAL RATING</th>
      </tr>
    </thead>
    <tbody>${rows}</tbody>
  </table>
  <div class="sig-block">
    <div class="sig-name">${from}</div>
    <div class="sig-pos">${fromPos}</div>
  </div>
  <div class="footer">
    Philippine Science High School – Caraga Region Campus | Ampayon, Butuan City 8600 | Tel: (085) 342-5969
  </div>
  <script>window.onload = () => { window.print(); window.onafterprint = () => window.close(); }<\/script>
</body>
</html>`

  const win = window.open('', '_blank')
  win.document.write(html)
  win.document.close()
}
</script>

<template>
  <Head title="My Division" />
  <AdminLayout title="My Division">
    <div class="p-6 space-y-5">

      <!-- Header -->
      <AppPageHeader title="Division IPCR Targets">
        <template #actions>
          <!-- Submit to HR batch action — Division Chiefs (endorsers) only -->
          <div v-if="canEndorseAny" class="flex items-center gap-2">
            <AppSelect v-model="submitToHRPeriod" placeholder="— Period —" :show-blank="false" class="min-w-40">
              <option value="" disabled>— Period —</option>
              <option v-for="p in periodLabels" :key="p" :value="p">{{ p }}</option>
            </AppSelect>
            <AppButton
              size="sm"
              :disabled="ratedForHRCount === 0 || !submitToHRPeriod || isSubmitting"
              @click="submitToHR"
            >
              {{ isSubmitting ? 'Processing…' : `Submit to HR (${ratedForHRCount})` }}
            </AppButton>
          </div>
          <AppButton v-if="canEndorseAny" variant="secondary" @click="openReportModal">Generate Memo Report</AppButton>
        </template>
      </AppPageHeader>

      <!-- Filter bar -->
      <AppFilterBar>
        <AppInput
          v-model="searchQuery"
          placeholder="Search by employee, title, period, or status..."
          class="flex-1 min-w-52"
        />
        <AppSelect v-model="selectedPeriod" placeholder="All Periods" class="min-w-40">
          <option v-for="p in periodLabels" :key="p" :value="p">{{ p }}</option>
        </AppSelect>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="paginated.length === 0 && employeesWithoutIpcr.length === 0" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Employee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Division</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Rating Period</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Title</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Avg Rating</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Submitted</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
          </tr>
        </template>

        <!-- IPCRs -->
        <tr v-for="ipcr in paginated" :key="ipcr.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800 text-sm">{{ ipcr.user?.name ?? "—" }}</div>
            <div class="text-xs text-slate-500">{{ ipcr.user?.position ?? "" }}</div>
          </td>
          <td class="px-4 py-3 text-xs text-slate-600">{{ ipcr.user?.division?.name ?? "—" }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.rating_period ?? "—" }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.title ?? "—" }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="ipcrBadgeColor(ipcr.status)">{{ ipcr.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <template v-if="ipcr.overall_average">
              <div class="font-semibold text-slate-800 text-sm">{{ ipcr.overall_average }}</div>
              <div class="text-xs text-slate-400">{{ adjectivalRating(ipcr.overall_average) }}</div>
            </template>
            <span v-else class="text-slate-400">—</span>
          </td>
          <td class="px-4 py-3 text-center text-xs text-slate-500">{{ formatDate(ipcr.submitted_for_rating_at) }}</td>
          <td class="px-4 py-3 text-center">
            <AppButton size="sm" variant="ghost" @click="viewIPCR(ipcr)">
              <EyeIcon class="w-4 h-4" /> View
            </AppButton>
          </td>
        </tr>

        <!-- Employees without IPCR -->
        <tr v-for="emp in employeesWithoutIpcr" :key="`emp-${emp.id}`" class="bg-slate-50/40">
          <td class="px-4 py-3">
            <div class="font-medium text-slate-700 text-sm">{{ emp.name }}</div>
            <div class="text-xs text-slate-500">{{ emp.position ?? "" }}</div>
          </td>
          <td class="px-4 py-3 text-slate-400 text-sm">—</td>
          <td class="px-4 py-3 text-slate-400 text-sm">—</td>
          <td class="px-4 py-3 text-slate-400 text-sm">—</td>
          <td class="px-4 py-3 text-center">
            <AppBadge color="slate">No IPCR Created</AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-400 text-center">—</td>
          <td class="px-4 py-3 text-slate-400 text-center">—</td>
          <td class="px-4 py-3"></td>
        </tr>

        <template #mobileCard>
          <div v-for="ipcr in paginated" :key="`m-${ipcr.id}`" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-sm text-slate-800">{{ ipcr.user?.name ?? "—" }}</p>
                <p class="text-xs text-slate-500">{{ ipcr.user?.position ?? "" }}</p>
                <p class="text-xs text-slate-400">{{ ipcr.rating_period ?? "—" }} &middot; {{ ipcr.title ?? "—" }}</p>
              </div>
              <AppBadge :color="ipcrBadgeColor(ipcr.status)">{{ ipcr.status }}</AppBadge>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500">
              <span v-if="ipcr.overall_average">Avg {{ ipcr.overall_average }} ({{ adjectivalRating(ipcr.overall_average) }})</span>
              <span v-else>No rating yet</span>
              <span>{{ formatDate(ipcr.submitted_for_rating_at) }}</span>
            </div>
            <AppButton size="sm" variant="ghost" @click="viewIPCR(ipcr)">
              <EyeIcon class="w-4 h-4" /> View
            </AppButton>
          </div>
          <div v-for="emp in employeesWithoutIpcr" :key="`m-emp-${emp.id}`" class="p-4 space-y-2 bg-slate-50/40">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-sm text-slate-700">{{ emp.name }}</p>
                <p class="text-xs text-slate-500">{{ emp.position ?? "" }}</p>
              </div>
              <AppBadge color="slate">No IPCR Created</AppBadge>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No IPCRs found for your division with the selected filters." />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="goToPage(currentPage - 1)"
            @next="goToPage(currentPage + 1)"
            @page="goToPage"
          />
        </template>
      </AppTable>

    </div>

    <!-- Add/Edit IPCR Modal -->
    <AppModal :show="showModal" :title="modalMode === 'create' ? 'Add Target' : 'Edit Target'" @close="closeModal">
      <div class="space-y-4">
        <AppSelect v-model="form.rating_period_id" label="Rating Period" required placeholder="-- Select Rating Period --">
          <option v-for="p in openPeriods" :key="p.id" :value="p.id">
            {{ p.label }}{{ p.is_current ? ' (Current)' : '' }}
          </option>
        </AppSelect>
        <AppInput v-model="form.title" label="Title" />
        <AppTextarea v-model="form.remarks" label="Remarks" :rows="3" />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
        <AppButton @click="submitIPCR">{{ modalMode === 'create' ? 'Add' : 'Update' }}</AppButton>
      </template>
    </AppModal>

    <!-- Add Plans Modal -->
    <AppModal :show="showAddPlansModal" :title="`Select Plans for &quot;${selectedIPCR?.title ?? ''}&quot;`" size="2xl" @close="closeAddPlansModal">
      <AppInput v-model="planSearch" placeholder="Search plans..." class="mb-3" />
      <div class="space-y-1 rounded-lg border border-slate-200 p-2">
        <div v-for="plan in filteredPlans" :key="'plan-'+plan.id" class="flex items-start gap-2 py-2">
          <input type="checkbox" :id="'plan-'+plan.id" :checked="isPlanSelected(plan.id)" @change="togglePlanSelection(plan)" class="mt-0.5" />
          <label :for="'plan-'+plan.id" class="flex-1 cursor-pointer">
            <div class="text-sm font-medium text-slate-700">{{ plan.success_indicator }}</div>
            <div class="text-xs text-slate-500" v-if="plan.performance_indicator">{{ plan.performance_indicator.description }}</div>
            <div class="text-xs text-slate-500" v-if="plan.office_involved">{{ plan.office_involved }}</div>
          </label>
        </div>
        <div v-if="filteredPlans.length === 0" class="py-8 text-center text-slate-400 text-sm">No plans found.</div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="closeAddPlansModal">Cancel</AppButton>
        <AppButton @click="submitPlans">Add Plans</AppButton>
      </template>
    </AppModal>

    <!-- Memo Report Modal -->
    <AppModal :show="showReportModal" title="Generate IPCR Memo Report" @close="showReportModal = false">
      <div class="space-y-4">
        <AppInput
          v-model="reportTo"
          label="TO (Recipient Name &amp; Designation)"
          placeholder="e.g. JUAN D. DELA CRUZ, AO V / HRMO"
        />
        <div>
          <AppSelect v-model="reportPeriod" label="Rating Period" :show-blank="false">
            <option value="" disabled>— Select a rating period —</option>
            <option v-for="period in periodLabels" :key="period" :value="period">{{ period }}</option>
          </AppSelect>
          <p v-if="ratingPeriods.length === 0" class="text-xs text-danger-600 mt-1">No rated IPCRs found.</p>
        </div>
        <p class="text-xs text-slate-500">{{ ratedIPCRs.length }} rated employee(s) will be included for this period.</p>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showReportModal = false">Cancel</AppButton>
        <AppButton @click="printMemo">Print</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
