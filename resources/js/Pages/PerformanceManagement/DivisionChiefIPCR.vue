<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon } from "@heroicons/vue/24/outline"
import useDivisionChiefIPCR from "@/Composables/useIPCRDC.js"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { ref, computed } from "vue"
import Swal from "sweetalert2"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  ipcrs:             Array,
  divisionEmployees: { type: Array, default: () => [] },
  workPlans:         Array,
  supervisor:        Object,
  ratingPeriods:     { type: Array, default: () => [] },
})

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

// ── Submit to HR ─────────────────────────────────────────────
const { isSubmitting, submit } = useSubmit()

const submitToHRPeriod = ref(props.ratingPeriods[0] ?? "")
const ratedForHRCount  = computed(() =>
  props.ipcrs.filter(i =>
    i.status === 'Rated & For PMT Review' &&
    (!submitToHRPeriod.value || i.rating_period === submitToHRPeriod.value)
  ).length
)

const submitToHR = () => {
  if (!submitToHRPeriod.value) return
  Swal.fire({
    title: "Submit to HR?",
    text: `Submit all ${ratedForHRCount.value} rated IPCR(s) for "${submitToHRPeriod.value}" to HR?`,
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#0891b2",
    confirmButtonText: "Yes, submit!",
  }).then(result => {
    if (result.isConfirmed) {
      submit.post(route('division-chief-ipcr.submitToHR'), { rating_period: submitToHRPeriod.value })
    }
  })
}

// ── Memo Report ──────────────────────────────────────────────
const showReportModal = ref(false)
const reportTo        = ref('')
const reportPeriod    = ref('')

const ratedStatusList = ['Rated & For PMT Review', 'Submitted to PMT', 'PMT Returned for Revision', 'Approved by PMT']

const ratedIPCRs = computed(() =>
  props.ipcrs.filter(i =>
    ratedStatusList.includes(i.status) &&
    (!reportPeriod.value || i.rating_period === reportPeriod.value)
  )
)

const openReportModal = () => {
  reportPeriod.value = props.ratingPeriods[0] ?? ''
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
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Division IPCR Targets</h1>
        <div class="flex flex-wrap items-center gap-2">
          <!-- Submit to HR batch action -->
          <div class="flex items-center gap-2 rounded-lg px-3 py-2 bg-white border border-slate-200 shadow-sm">
            <select
              v-model="submitToHRPeriod"
              class="border-0 bg-transparent text-sm text-slate-700 focus:ring-0 p-0"
            >
              <option value="" disabled>— Period —</option>
              <option v-for="p in ratingPeriods" :key="p" :value="p">{{ p }}</option>
            </select>
            <button
              @click="submitToHR"
              :disabled="ratedForHRCount === 0 || !submitToHRPeriod || isSubmitting"
              class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white px-3 py-1 rounded-lg text-sm font-medium transition-colors"
            >
              {{ isSubmitting ? 'Processing…' : `Submit to HR (${ratedForHRCount})` }}
            </button>
          </div>
          <button
            @click="openReportModal"
            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
          >
            Generate Memo Report
          </button>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap items-center gap-3">
        <input
          v-model="searchQuery"
          @input="resetPage"
          type="text"
          placeholder="Search by employee, title, period, or status..."
          class="flex-1 min-w-52 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        />
        <select
          v-model="selectedPeriod"
          @change="resetPage"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        >
          <option value="">All Periods</option>
          <option v-for="p in ratingPeriods" :key="p" :value="p">{{ p }}</option>
        </select>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Division</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Rating Period</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Title</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Avg Rating</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Submitted</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="paginated.length === 0 && employeesWithoutIpcr.length === 0">
                <td colspan="8" class="py-16 text-center text-slate-400 text-sm">
                  No IPCRs found for your division with the selected filters.
                </td>
              </tr>

              <!-- IPCRs -->
              <tr v-for="ipcr in paginated" :key="ipcr.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-800 text-sm">{{ ipcr.user?.name ?? "—" }}</div>
                  <div class="text-xs text-slate-500">{{ ipcr.user?.position ?? "" }}</div>
                </td>
                <td class="px-4 py-3 text-xs text-slate-600">{{ ipcr.user?.division?.name ?? "—" }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.rating_period ?? "—" }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ ipcr.title ?? "—" }}</td>
                <td class="px-4 py-3 text-center">
                  <span :class="statusClasses(ipcr.status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap">
                    {{ ipcr.status }}
                  </span>
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
                  <button @click="viewIPCR(ipcr)" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                    <EyeIcon class="w-4 h-4" /> View
                  </button>
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
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">No IPCR Created</span>
                </td>
                <td class="px-4 py-3 text-slate-400 text-center">—</td>
                <td class="px-4 py-3 text-slate-400 text-center">—</td>
                <td class="px-4 py-3"></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
                <PaginationControl
          :current-page="currentPage"
          :total-pages="totalPages"
          @prev="goToPage(currentPage - 1)"
          @next="goToPage(currentPage + 1)"
          @page="goToPage"
        />
      </div>

    </div>

    <!-- Add/Edit IPCR Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">{{ modalMode === 'create' ? 'Add Target' : 'Edit Target' }}</h2>
          <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Rating Period</label>
            <input v-model="form.rating_period" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Title</label>
            <input v-model="form.title" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
            <textarea v-model="form.remarks" rows="3" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
          <button @click="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Cancel</button>
          <button @click="submitIPCR" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">{{ modalMode === 'create' ? 'Add' : 'Update' }}</button>
        </div>
      </div>
    </div>

    <!-- Add Plans Modal -->
    <div v-if="showAddPlansModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl flex flex-col max-h-[80vh]">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
          <h2 class="text-base font-semibold text-slate-800">Select Plans for "{{ selectedIPCR?.title }}"</h2>
          <button @click="closeAddPlansModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="px-6 py-4 shrink-0">
          <input v-model="planSearch" type="text" placeholder="Search plans..." class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div class="flex-1 overflow-y-auto px-6 pb-2">
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
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 shrink-0">
          <button @click="closeAddPlansModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Cancel</button>
          <button @click="submitPlans" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">Add Plans</button>
        </div>
      </div>
    </div>

    <!-- Memo Report Modal -->
    <div v-if="showReportModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">Generate IPCR Memo Report</h2>
          <button @click="showReportModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">TO (Recipient Name &amp; Designation)</label>
            <input v-model="reportTo" type="text" placeholder="e.g. JUAN D. DELA CRUZ, AO V / HRMO"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Rating Period</label>
            <select v-model="reportPeriod" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="" disabled>— Select a rating period —</option>
              <option v-for="period in ratingPeriods" :key="period" :value="period">{{ period }}</option>
            </select>
            <p v-if="ratingPeriods.length === 0" class="text-xs text-red-500 mt-1">No rated IPCRs found.</p>
          </div>
          <p class="text-xs text-slate-500">{{ ratedIPCRs.length }} rated employee(s) will be included for this period.</p>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
          <button @click="showReportModal = false" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Cancel</button>
          <button @click="printMemo" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">Print</button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
