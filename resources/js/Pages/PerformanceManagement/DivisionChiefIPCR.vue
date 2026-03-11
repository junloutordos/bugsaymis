<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon } from "@heroicons/vue/24/outline"
import useDivisionChiefIPCR from "@/Composables/useIPCRDC.js"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { ref, computed } from "vue"

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

    <!-- Header -->
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-xl font-bold text-gray-800">Division IPCR Targets</h1>
      <button
        @click="openReportModal"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow text-sm"
      >
        Generate Memo Report
      </button>
    </div>

    <div class="bg-white p-4 rounded-lg shadow">

      <!-- Toolbar -->
      <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <input
          v-model="searchQuery"
          @input="resetPage"
          type="text"
          placeholder="Search by employee, title, period, or status..."
          class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
        />
        <select
          v-model="selectedPeriod"
          @change="resetPage"
          class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
        >
          <option value="">All Periods</option>
          <option v-for="p in ratingPeriods" :key="p" :value="p">{{ p }}</option>
        </select>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border-collapse">
          <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
            <tr>
              <th class="border px-4 py-2 text-left">Employee</th>
              <th class="border px-4 py-2 text-left">Division</th>
              <th class="border px-4 py-2 text-left">Rating Period</th>
              <th class="border px-4 py-2 text-left">Title</th>
              <th class="border px-4 py-2 text-center">Status</th>
              <th class="border px-4 py-2 text-center">Avg Rating</th>
              <th class="border px-4 py-2 text-center">Submitted</th>
              <th class="border px-4 py-2 text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="paginated.length === 0 && employeesWithoutIpcr.length === 0">
              <td colspan="8" class="border px-4 py-6 text-center text-gray-400">
                No IPCRs found for your division with the selected filters.
              </td>
            </tr>

            <!-- IPCRs -->
            <tr
              v-for="ipcr in paginated"
              :key="ipcr.id"
              class="hover:bg-gray-50"
            >
              <td class="border px-4 py-2">
                <div class="font-medium text-gray-800">{{ ipcr.user?.name ?? "—" }}</div>
                <div class="text-xs text-gray-500">{{ ipcr.user?.position ?? "" }}</div>
              </td>
              <td class="border px-4 py-2 text-gray-600 text-xs">
                {{ ipcr.user?.division?.name ?? "—" }}
              </td>
              <td class="border px-4 py-2 text-gray-600">{{ ipcr.rating_period ?? "—" }}</td>
              <td class="border px-4 py-2 text-gray-600">{{ ipcr.title ?? "—" }}</td>
              <td class="border px-4 py-2 text-center">
                <span
                  :class="statusClasses(ipcr.status)"
                  class="px-2 py-1 text-xs font-semibold rounded-full whitespace-nowrap"
                >
                  {{ ipcr.status }}
                </span>
              </td>
              <td class="border px-4 py-2 text-center font-medium">
                <template v-if="ipcr.overall_average">
                  {{ ipcr.overall_average }}
                  <div class="text-xs text-gray-400">{{ adjectivalRating(ipcr.overall_average) }}</div>
                </template>
                <span v-else class="text-gray-400">—</span>
              </td>
              <td class="border px-4 py-2 text-center text-xs text-gray-500">
                {{ formatDate(ipcr.submitted_for_rating_at) }}
              </td>
              <td class="border px-4 py-2 text-center">
                <button
                  @click="viewIPCR(ipcr)"
                  class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 text-sm font-medium"
                >
                  <EyeIcon class="w-4 h-4" /> View
                </button>
              </td>
            </tr>

            <!-- Employees without IPCR -->
            <tr
              v-for="emp in employeesWithoutIpcr"
              :key="`emp-${emp.id}`"
              class="hover:bg-gray-50 bg-gray-50"
            >
              <td class="border px-4 py-2">
                <div class="font-medium text-gray-800">{{ emp.name }}</div>
                <div class="text-xs text-gray-500">{{ emp.position ?? "" }}</div>
              </td>
              <td class="border px-4 py-2 text-gray-400">—</td>
              <td class="border px-4 py-2 text-gray-400">—</td>
              <td class="border px-4 py-2 text-gray-400">—</td>
              <td class="border px-4 py-2 text-center">
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-500">
                  No IPCR Created
                </span>
              </td>
              <td class="border px-4 py-2 text-gray-400 text-center">—</td>
              <td class="border px-4 py-2 text-gray-400 text-center">—</td>
              <td class="border px-4 py-2"></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between mt-4 text-sm text-gray-600">
        <span>Page {{ currentPage }} of {{ totalPages }} ({{ filtered.length }} records)</span>
        <div class="flex gap-1">
          <button
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="px-3 py-1 border rounded disabled:opacity-40 hover:bg-gray-100"
          >&laquo;</button>
          <button
            v-for="p in totalPages"
            :key="p"
            @click="goToPage(p)"
            :class="p === currentPage ? 'bg-blue-600 text-white' : 'hover:bg-gray-100'"
            class="px-3 py-1 border rounded"
          >{{ p }}</button>
          <button
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="px-3 py-1 border rounded disabled:opacity-40 hover:bg-gray-100"
          >&raquo;</button>
        </div>
      </div>

    </div>

    <!-- Add/Edit IPCR Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl p-6 w-1/2 max-w-xl">
        <h2 class="text-lg font-semibold mb-4">{{ modalMode === 'create' ? 'Add Target' : 'Edit Target' }}</h2>
        <div class="flex flex-col gap-3">
          <div>
            <label class="block mb-1 font-medium">Rating Period</label>
            <input v-model="form.rating_period" type="text" class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="block mb-1 font-medium">Title</label>
            <input v-model="form.title" type="text" class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="block mb-1 font-medium">Remarks</label>
            <textarea v-model="form.remarks" rows="3" class="w-full border rounded px-3 py-2"></textarea>
          </div>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button @click="closeModal" class="px-4 py-2 bg-gray-200 rounded">Cancel</button>
          <button @click="submitIPCR" class="px-4 py-2 bg-blue-600 text-white rounded">{{ modalMode === 'create' ? 'Add' : 'Update' }}</button>
        </div>
      </div>
    </div>

    <!-- Add Plans Modal -->
    <div v-if="showAddPlansModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl p-6 w-3/4 max-w-3xl h-[500px] flex flex-col">
        <h2 class="text-lg font-semibold mb-4">Select Plans for "{{ selectedIPCR?.title }}"</h2>
        <input v-model="planSearch" type="text" placeholder="Search plans..." class="mb-3 px-3 py-2 border rounded w-full focus:ring-blue-500 focus:border-blue-500" />
        <div class="flex-1 overflow-y-auto border-t border-b py-2">
          <div class="max-h-full overflow-auto border rounded mt-2 p-2">
            <div v-for="plan in filteredPlans" :key="'plan-'+plan.id" class="flex items-start gap-2 py-2">
              <input type="checkbox" :id="'plan-'+plan.id" :checked="isPlanSelected(plan.id)" @change="togglePlanSelection(plan)" class="mt-1" />
              <label :for="'plan-'+plan.id" class="flex-1">
                <div class="font-semibold">{{ plan.success_indicator }}</div>
                <div class="text-sm text-gray-500" v-if="plan.performance_indicator">{{ plan.performance_indicator.description }}</div>
                <div class="text-sm text-gray-500" v-if="plan.office_involved">{{ plan.office_involved }}</div>
              </label>
            </div>
            <div v-if="filteredPlans.length === 0" class="text-sm text-gray-500 text-center mt-4">No plans found.</div>
          </div>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button @click="closeAddPlansModal" class="px-4 py-2 bg-gray-200 rounded">Cancel</button>
          <button @click="submitPlans" class="px-4 py-2 bg-green-600 text-white rounded">Add</button>
        </div>
      </div>
    </div>

    <!-- Memo Report Modal -->
    <div v-if="showReportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
        <h2 class="text-lg font-semibold mb-4">Generate IPCR Memo Report</h2>
        <div class="flex flex-col gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">TO (Recipient Name & Designation)</label>
            <input
              v-model="reportTo"
              type="text"
              placeholder="e.g. JUAN D. DELA CRUZ, AO V / HRMO"
              class="w-full border rounded px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Rating Period</label>
            <select v-model="reportPeriod" class="w-full border rounded px-3 py-2 text-sm">
              <option value="" disabled>— Select a rating period —</option>
              <option v-for="period in ratingPeriods" :key="period" :value="period">{{ period }}</option>
            </select>
            <p v-if="ratingPeriods.length === 0" class="text-xs text-red-500 mt-1">No rated IPCRs found.</p>
          </div>
          <p class="text-xs text-gray-500">{{ ratedIPCRs.length }} rated employee(s) will be included for this period.</p>
        </div>
        <div class="mt-5 flex justify-end gap-2">
          <button @click="showReportModal = false" class="px-4 py-2 bg-gray-200 rounded text-sm">Cancel</button>
          <button @click="printMemo" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">Print</button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
