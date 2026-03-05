<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon } from "@heroicons/vue/24/outline"
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
  ipcrTargets,
  workPlans: workPlansList,
  searchQuery,
  planSearch,
  currentPage,
  totalPages,
  filteredIPCRs,
  filteredPlans,
  showModal,
  showAddPlansModal,
  modalMode,
  selectedIPCR,
  selectedPlans,
  form,
  isPlanSelected,
  togglePlanSelection,
  openModal,
  closeModal,
  submitIPCR,
  openAddPlansModal,
  closeAddPlansModal,
  submitPlans,
  viewIPCR,
  destroyIPCR,
  sortBy,
  statusClasses
} = useDivisionChiefIPCR(props.ipcrs, props.workPlans)

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

// Employees in this division who have not created any IPCR yet
const employeesWithoutIpcr = computed(() => {
  const ipcrUserIds = new Set(props.ipcrs.map(i => i.user?.id).filter(Boolean))
  return props.divisionEmployees.filter(emp => !ipcrUserIds.has(emp.id))
})

const adjectivalRating = ipcrAdjectivalRating

const openReportModal = () => {
  reportPeriod.value = props.ratingPeriods[0] ?? ''
  reportTo.value = ''
  showReportModal.value = true
}

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

const today = new Date().toLocaleDateString('en-PH', {
  year: 'numeric', month: 'long', day: 'numeric'
})
</script>

<template>
  <Head title="Division IPCR Targets" />
  <AdminLayout title="Division IPCR Targets">
    <div>
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Division IPCR Targets</h1>
        <button
          @click="openReportModal"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow text-sm"
        >
          Generate Memo Report
        </button>
      </div>

      <!-- Search -->
      <div class="bg-white p-4 rounded-xl shadow mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search targets..."
          class="w-full sm:w-1/2 md:w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
        />
      </div>

      <!-- IPCR Table -->
      <div class="overflow-x-auto bg-white p-4 rounded-xl shadow">
        <table class="min-w-full border border-gray-200 text-center">
          <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
            <tr>
              <th @click="sortBy('user')" class="px-4 py-3 cursor-pointer">Employee Name</th>
              <th @click="sortBy('rating_period')" class="px-4 py-3 cursor-pointer">Rating Period</th>
              <th @click="sortBy('title')" class="px-4 py-3 cursor-pointer">Title</th>
              <th @click="sortBy('status')" class="px-4 py-3 cursor-pointer">Status</th>
              <th class="px-4 py-3">Submitted at</th>
              <th class="px-4 py-3">Approved at</th>
              <th class="px-4 py-3">Accomplishment Submitted at</th>
              <th class="px-4 py-3">Rated at</th>
              <th class="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 text-sm">
            <tr v-for="t in filteredIPCRs" :key="t.id" class="hover:bg-gray-50">
              <td class="px-4 py-3">{{ t.user.name }}</td>
              <td class="px-4 py-3">{{ t.rating_period }}</td>
              <td class="px-4 py-3">{{ t.title }}</td>
              <td class="px-4 py-3">
                <span :class="`inline-block px-3 py-1 rounded-full text-xs font-semibold ${statusClasses(t.status)}`">
                  {{ t.status }}
                </span>
              </td>
              <td class="px-4 py-3"><small>{{ t.submitted_for_review_at }}</small></td>
              <td class="px-4 py-3"><small>{{ t.target_approved_at }}</small></td>
              <td class="px-4 py-3"><small>{{ t.submitted_for_rating_at }}</small></td>
              <td class="px-4 py-3"><small>{{ t.submitted_rating_at }}</small></td>
              <td class="px-4 py-3 flex justify-center gap-2">
                <button @click="viewIPCR(t)" class="p-2 hover:bg-gray-100 rounded" title="View">
                  <EyeIcon class="w-5 h-5 text-blue-600"/>
                </button>
                
                
              </td>
            </tr>
            <!-- Employees who have not created an IPCR yet -->
            <tr
              v-for="emp in employeesWithoutIpcr"
              :key="`emp-${emp.id}`"
              class="hover:bg-gray-50 bg-gray-50"
            >
              <td class="px-4 py-3 font-medium">{{ emp.name }}</td>
              <td class="px-4 py-3 text-gray-400">—</td>
              <td class="px-4 py-3 text-gray-400">—</td>
              <td class="px-4 py-3">
                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">
                  No IPCR Created
                </span>
              </td>
              <td class="px-4 py-3 text-gray-400">—</td>
              <td class="px-4 py-3 text-gray-400">—</td>
              <td class="px-4 py-3 text-gray-400">—</td>
              <td class="px-4 py-3 text-gray-400">—</td>
              <td class="px-4 py-3"></td>
            </tr>

            <tr v-if="filteredIPCRs.length === 0 && employeesWithoutIpcr.length === 0">
              <td colspan="9" class="px-4 py-6 text-gray-500">No employees found in this division.</td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click="currentPage--" :disabled="currentPage === 1" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="currentPage++" :disabled="currentPage === totalPages" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
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
    <!-- Pre-print Modal -->
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
            <select
              v-model="reportPeriod"
              class="w-full border rounded px-3 py-2 text-sm"
            >
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

