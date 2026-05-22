<script setup>
import { Head, usePage, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { ref, computed, watch } from "vue"
import {
  EyeIcon,
  PencilSquareIcon,
  PrinterIcon,
  TrashIcon,
  ArrowDownTrayIcon,
  ClockIcon,
  ChartBarIcon,
  PlusIcon,
} from "@heroicons/vue/24/outline"
import useEquipments from "@/Composables/useEquipments.js"

// Props from backend
const props = defineProps({
  equipments: Object,
  users: Array,
  rooms: Array,
  filters: Object,
})

const {
  errors,
  showModal,
  modalMode,
  selectedEquipment,
  form,
  destroyEquipment,
  openModal,
  closeModal,
  formatDate,
  submitEquipment,
  viewEquipment,
  printPmsHistory,
  showPmsModal,
  selectedPmsHistory,
  openPmsHistory,
  showAddPmsModal,
  pmsForm,
  pmsFormErrors,
  isSubmittingPms,
  openAddPmsHistory,
  submitPmsHistory,
} = useEquipments(props.equipments?.data ?? [], props.users)

const page = usePage()
const userRole = page.props.auth?.user?.role?.name ?? null
const csrfToken = page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.content || ''

// Report state
const showReportModal = ref(false)
const reportGroupBy = ref('category') // 'category' or 'location'

// Group all equipments by category or location for the print report
const groupedEquipments = computed(() => {
  const groups = {}
  ;(props.equipments?.data ?? []).forEach(eq => {
    const key = reportGroupBy.value === 'category'
      ? (eq.category || 'Uncategorized')
      : (eq.room?.name || 'No Location')
    if (!groups[key]) groups[key] = []
    groups[key].push(eq)
  })
  return groups
})

function generateReport() {
  showReportModal.value = false
  window.print()
}

// ✅ Print Modal Content
function printModal() {
  const printArea = document.getElementById("printArea");
  if (!printArea) return;

  const clonedContent = printArea.cloneNode(true);

  // Fix QR image path
  const images = clonedContent.getElementsByTagName("img");
  for (let img of images) {
    if (img.src.startsWith("/")) {
      img.src = `${window.location.origin}${img.src}`;
    }
  }

  const newWindow = window.open("", "", "width=900,height=650");
  newWindow.document.write(`
    <html>
      <head>
        <style>
          body { font-family: Arial, sans-serif; padding: 20px; }
          h2 { text-align: center; margin-bottom: 20px; }
          .print-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            align-items: start;
          }
          .print-left { text-align: center; }
          .print-left img {
            max-width: 200px;
            height: auto;
            display: block;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 8px;
            border-radius: 8px;
          }
          .print-right p { margin: 6px 0; font-size: 14px; }
          .print-right strong {
            display: inline-block;
            width: 120px;
          }
        </style>
      </head>
      <body>

        <div class="print-container">
          <div class="print-left">
            ${clonedContent.querySelector("img")?.outerHTML || ""}
          </div>
          <div class="print-right">
            ${Array.from(clonedContent.querySelectorAll("p"))
              .map((p) => p.outerHTML)
              .join("")}
          </div>
        </div>
      </body>
    </html>
  `);

  newWindow.document.close();
  newWindow.onload = function () {
    newWindow.focus();
    newWindow.print();
    newWindow.close();
  };
}

// Server-side filters
const search         = ref(props.filters?.search   ?? '')
const filterCategory = ref(props.filters?.category ?? '')
const filterStatus   = ref(props.filters?.status   ?? '')
const perPage        = ref(props.filters?.per_page ?? 15) // Default to 15 items per page
const isLoading      = ref(false)
let debounceTimer    = null

const buildParams = (page = undefined) => ({
  search:   search.value         || undefined,
  category: filterCategory.value || undefined,
  status:   filterStatus.value   || undefined,
  per_page: perPage.value        || undefined,
  page:     page                 || undefined,
})

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('ict-equipments.index'), buildParams(), {
      preserveState: true,
      replace: true,
      only: ['equipments', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
  }
  if (immediate) go()
  else debounceTimer = setTimeout(go, 400)
}

watch(search, () => applyFilters(false))
watch(filterCategory, () => applyFilters(true))
watch(filterStatus,   () => applyFilters(true))
watch(perPage, () => applyFilters(true))

const goToPage = (pageNum) => {
  isLoading.value = true
  router.get(route('ict-equipments.index'), buildParams(pageNum), {
    preserveState: true,
    replace: true,
    only: ['equipments', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

const visibleEquipments = computed(() => props.equipments?.data ?? [])
const currentPage       = computed(() => props.equipments?.current_page ?? 1)
const totalPages        = computed(() => props.equipments?.last_page ?? 1)
const showAllChecked    = computed({
  get: () => perPage.value === 1000,
  set: (value) => perPage.value = value ? 1000 : 15
})
</script>

<template>
  <Head title="ICT Equipment Inventory" />
  <AdminLayout title="ICT Equipment Inventory">
    <div>
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">ICT Equipment Inventory</h1>
        <button
          @click="openModal('create')"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
        >
          + Add Equipment
        </button>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2">
          <div class="relative">
            <input
              v-model="search"
              type="text"
              placeholder="Search equipment..."
              @keydown.enter.prevent="applyFilters(true)"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-64"
            />
            <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
              <svg class="animate-spin h-4 w-4 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
            </span>
          </div>
          <button @click="applyFilters(true)" :disabled="isLoading" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
            Search
          </button>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <select v-model="filterCategory" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
            <option value="">All Categories</option>
            <option value="CPU/System Unit">CPU/System Unit</option>
            <option value="Monitor">Monitor</option>
            <option value="Mouse">Mouse</option>
            <option value="Keyboard">Keyboard</option>
            <option value="UPS">UPS</option>
            <option value="AVR">AVR</option>
            <option value="Printer">Printer</option>
            <option value="Laptop">Laptop</option>
            <option value="Scanner">Scanner</option>
            <option value="Projector">Projector</option>
            <option value="Network Devices">Network Devices</option>
            <option value="CCTV Camera">CCTV Camera</option>
            <option value="CCTV NVR/DVR">CCTV NVR/DVR</option>
            <option value="Access Point">Access Point</option>
            <option value="Other">Other</option>
          </select>
          <select v-model="filterStatus" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
            <option value="">All Statuses</option>
            <option value="Good Working">Good Working</option>
            <option value="For Repair">For Repair</option>
            <option value="Disposed">Disposed</option>
          </select>
        </div>
        <div class="flex gap-2 items-center">
          <label class="flex items-center gap-1 text-sm text-slate-600">
            <input
              type="checkbox"
              v-model="showAllChecked"
              class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
            />
            Show All
          </label>
          <button @click="showReportModal = true" title="Generate Report" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
            <PrinterIcon class="w-5 h-5" />
          </button>
        </div>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Loading overlay -->
        <div v-if="isLoading" class="relative">
          <div class="absolute inset-0 bg-white/70 flex items-center justify-center z-10 rounded-xl">
            <div class="flex flex-col items-center gap-2 text-indigo-600">
              <svg class="animate-spin h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              <span class="text-sm font-medium">Loading...</span>
            </div>
          </div>
        </div>

        <!-- Equipment Table -->
        <div class="overflow-x-auto" :class="{ 'opacity-50 pointer-events-none': isLoading }">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">ID</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Serial No</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Description</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Owner</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="eq in visibleEquipments" :key="eq.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ eq.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ eq.serial_no }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ eq.description }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  {{ props.users.find(u => u.id === eq.owner_id)?.name || 'N/A' }}
                </td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                    :class="{
                      'bg-emerald-50 text-emerald-700': eq.status === 'Good Working',
                      'bg-amber-50 text-amber-700': eq.status === 'For Repair',
                      'bg-red-50 text-red-600': eq.status === 'Disposed',
                      'bg-slate-100 text-slate-600': !['Good Working','For Repair','Disposed'].includes(eq.status)
                    }"
                  >{{ eq.status }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-1 items-center">
                    <button @click="viewEquipment(eq)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="View">
                      <EyeIcon class="w-4 h-4"/>
                    </button>
                    <button @click="openModal('edit', eq)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="w-4 h-4"/>
                    </button>
                    <button @click="openAddPmsHistory(eq)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-indigo-600 transition-colors" title="Add PMS History">
                      <PlusIcon class="w-4 h-4"/>
                    </button>
                    <button @click="openPmsHistory(eq)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-emerald-600 transition-colors" title="PMS History">
                      <ClockIcon class="w-4 h-4" />
                    </button>
                    <button @click="destroyEquipment(eq)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors" title="Delete">
                      <TrashIcon class="w-4 h-4"/>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="visibleEquipments.length===0">
                <td colspan="6" class="py-16 text-center text-slate-400 text-sm">
                  No equipment found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1 || isLoading" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages || isLoading" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Next</button>
        </div>
      </div>

      <!-- Equipment Modal -->
      <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{ modalMode==='create' ? 'New Equipment Form' : modalMode==='edit' ? 'Edit Equipment' : 'View Equipment Details' }}
            </h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>

          <div class="px-6 py-5">
            <!-- VIEW MODE -->
            <div v-if="modalMode==='view' && selectedEquipment" class="space-y-2">
              <div id="printArea">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <!-- First column: QR Code -->
                  <div class="flex items-center justify-center">
                    <img
                      v-if="selectedEquipment.id"
                      :src="route('equipment.qr', { ictEquipment: selectedEquipment.id })"
                      alt="QR Code"
                      class="w-48 h-48 border border-slate-200 rounded-lg p-2"
                    />
                  </div>

                  <!-- Second column: Details -->
                  <div class="space-y-1 text-sm text-slate-700">
                    <p><strong>Owner:</strong> {{ props.users.find(u => u.id === selectedEquipment.owner_id)?.name || 'N/A' }}</p>
                    <p><strong>Category:</strong> {{ selectedEquipment.category }}</p>
                    <p><strong>Property No:</strong> {{ selectedEquipment.property_no }}</p>
                    <p><strong>Serial No:</strong> {{ selectedEquipment.serial_no }}</p>
                    <p><strong>Description:</strong> {{ selectedEquipment.description }}</p>
                    <p><strong>Date Acquired:</strong> {{ selectedEquipment.date_acquired }}</p>
                    <p><strong>Amount:</strong> {{ selectedEquipment.amount }}</p>
                    <p><strong>Status:</strong> {{ selectedEquipment.status }}</p>
                    <p>
                      <strong>Location:</strong>
                      {{ selectedEquipment.room?.name || 'N/A' }}
                    </p>
                    <p><strong>Remarks:</strong> {{ selectedEquipment.remarks }}</p>
                  </div>
                </div>
              </div>

              <!-- Print button -->
              <div class="mt-4 text-right">
                <button @click="printModal" title="Print" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
                  <PrinterIcon class="w-5 h-5" />
                </button>
              </div>
            </div>

            <!-- CREATE / EDIT FORM -->
            <form v-else @submit.prevent="submitEquipment" class="grid grid-cols-2 gap-4">
              <!-- Equipment Category -->
              <div class="col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Equipment Category <span class="text-red-500">*</span></label>
                <select v-model="form.category" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" required>
                  <option value="">Please select category</option>
                  <option value="CPU/System Unit">CPU/System Unit</option>
                  <option value="Monitor">Monitor</option>
                  <option value="Mouse">Mouse</option>
                  <option value="Keyboard">Keyboard</option>
                  <option value="UPS">UPS</option>
                  <option value="AVR">AVR</option>
                  <option value="Printer">Printer</option>
                  <option value="Laptop">Laptop</option>
                  <option value="Scanner">Scanner</option>
                  <option value="Projector">Projector</option>
                  <option value="Network Devices">Network Devices</option>
                  <option value="CCTV Camera">CCTV Camera</option>
                  <option value="CCTV NVR/DVR">CCTV NVR/DVR</option>
                  <option value="Access Point">Access Point</option>
                  <option value="Other">Other</option>
                </select>
              </div>

              <!-- Owner (Dropdown) -->
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Owner <span class="text-red-500">*</span></label>
                <select v-model="form.owner_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" required>
                  <option value="">Select Owner</option>
                  <option v-for="user in props.users" :key="user.id" :value="user.id">
                    {{ user.name }}
                  </option>
                </select>
              </div>

              <!-- Property No -->
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Property No</label>
                <input v-model="form.property_no" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              </div>

              <!-- Serial No -->
              <div class="col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Serial No <span class="text-red-500">*</span></label>
                <input v-model="form.serial_no" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" required />
              </div>

              <!-- Device Description -->
              <div class="col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Device Description / Model <span class="text-red-500">*</span></label>
                <input v-model="form.description" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" required />
              </div>

              <!-- Date Acquired -->
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date Acquired</label>
                <input v-model="form.date_acquired" type="date" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              </div>

              <!-- Amount -->
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Amount</label>
                <input v-model="form.amount" type="number" step="0.01" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              </div>

              <!-- Equipment Status -->
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Equipment Status <span class="text-red-500">*</span></label>
                <select v-model="form.status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" required>
                  <option value="">Select Status</option>
                  <option value="Good Working">Good Working</option>
                  <option value="For Repair">For Repair</option>
                  <option value="Disposed">Disposed</option>
                </select>
              </div>

              <!-- Location / Room -->
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">
                  Location<span class="text-red-500">*</span>
                </label>
                <select
                  v-model="form.room_id"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                  required
                >
                  <option value="">Select location</option>
                  <option v-for="room in props.rooms" :key="room.id" :value="room.id">
                    {{ room.name }}
                  </option>
                </select>
              </div>


              <!-- Remarks -->
              <div class="col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
                <textarea v-model="form.remarks" rows="2" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"></textarea>
              </div>

              <!-- Buttons -->
              <div class="col-span-2 flex justify-end gap-2 pt-2">
                <button type="button" @click="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
                <button type="submit" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Save</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- PMS HISTORY MODAL -->
      <div
        v-if="showPmsModal"
        class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-xl">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              PMS History for {{ selectedEquipment?.description }} / {{ selectedEquipment?.serial_no }}
            </h2>
            <button
              @click="showPmsModal = false"
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="px-6 py-5">
            <div v-if="selectedPmsHistory.length === 0" class="py-16 text-center text-slate-400 text-sm">
              No PMS history found.
            </div>

            <ul v-else class="space-y-3 max-h-96 overflow-y-auto">
              <li
                v-for="pms in selectedPmsHistory"
                :key="pms.id"
                class="border border-slate-100 p-4 rounded-lg bg-slate-50/50"
              >
                <div class="font-semibold text-slate-800 text-sm">{{ formatDate(pms.pms_date) }}</div>
                <div class="text-sm text-slate-600 mt-1">
                  <b>Type:</b> {{ pms.type }}
                </div>
                <div class="text-sm text-slate-600">
                  <b>Description:</b> {{ pms.description }}
                </div>
                <div class="text-sm text-slate-600">
                  <b>Cost of Repair:</b> ₱{{ pms.cost_of_repair }}
                </div>
                <div class="text-sm text-slate-600">
                  <b>Remarks:</b> {{ pms.remarks }}
                </div>
                <div class="text-sm text-slate-600">
                  <b>Created By:</b> User ID {{ pms.created_by }}
                </div>
              </li>
            </ul>
          </div>
          <!-- Print button -->
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end">
            <button @click="printPmsHistory" title="Print History" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              <PrinterIcon class="w-4 h-4" /> Print History
            </button>
          </div>
        </div>
      </div>

      <!-- ADD PMS HISTORY MODAL -->
      <div
        v-if="showAddPmsModal"
        class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-xl">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              Add PMS History for {{ selectedEquipment?.description }} / {{ selectedEquipment?.serial_no }}
            </h2>
            <button
              @click="showAddPmsModal = false"
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="px-6 py-5">
            <form @submit.prevent="submitPmsHistory" class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">PMS Date</label>
                  <input
                    v-model="pmsForm.pms_date"
                    type="date"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                    :class="{ 'border-red-500': pmsFormErrors.pms_date }"
                  />
                  <div v-if="pmsFormErrors.pms_date" class="text-red-500 text-xs mt-1">{{ pmsFormErrors.pms_date }}</div>
                </div>

                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
                  <select
                    v-model="pmsForm.type"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                    :class="{ 'border-red-500': pmsFormErrors.type }"
                  >
                    <option value="PMS">PMS</option>
                    <option value="Repair">Repair</option>
                  </select>
                  <div v-if="pmsFormErrors.type" class="text-red-500 text-xs mt-1">{{ pmsFormErrors.type }}</div>
                </div>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <textarea
                  v-model="pmsForm.description"
                  rows="3"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                  placeholder="List of checked items or repair details"
                  :class="{ 'border-red-500': pmsFormErrors.description }"
                ></textarea>
                <div v-if="pmsFormErrors.description" class="text-red-500 text-xs mt-1">{{ pmsFormErrors.description }}</div>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Cost of Repair (₱)</label>
                <input
                  v-model.number="pmsForm.cost_of_repair"
                  type="number"
                  step="0.01"
                  min="0"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                  :class="{ 'border-red-500': pmsFormErrors.cost_of_repair }"
                />
                <div v-if="pmsFormErrors.cost_of_repair" class="text-red-500 text-xs mt-1">{{ pmsFormErrors.cost_of_repair }}</div>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
                <textarea
                  v-model="pmsForm.remarks"
                  rows="2"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                  placeholder="Additional remarks"
                  :class="{ 'border-red-500': pmsFormErrors.remarks }"
                ></textarea>
                <div v-if="pmsFormErrors.remarks" class="text-red-500 text-xs mt-1">{{ pmsFormErrors.remarks }}</div>
              </div>

              <div class="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  @click="showAddPmsModal = false"
                  :disabled="isSubmittingPms"
                  class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="isSubmittingPms"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50"
                >
                  {{ isSubmittingPms ? 'Adding...' : 'Add PMS History' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- REPORT MODAL -->
      <div
        v-if="showReportModal"
        class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Generate Equipment Report</h2>
            <button
              @click="showReportModal = false"
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-2">Group By:</label>
              <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                  <input
                    type="radio"
                    v-model="reportGroupBy"
                    value="category"
                    class="text-indigo-600"
                  />
                  <span>Category</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                  <input
                    type="radio"
                    v-model="reportGroupBy"
                    value="location"
                    class="text-indigo-600"
                  />
                  <span>Location / Room</span>
                </label>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button
              @click="showReportModal = false"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
            >
              Cancel
            </button>
            <button
              @click="generateReport(); showReportModal = false"
              class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
            >
              Generate & Print
            </button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>

  <!-- Print-only area (teleported outside #app for clean isolation) -->
  <Teleport to="body">
  <div id="ict-print-area">
    <table id="ict-pt-wrap">
      <thead>
        <tr><td id="ict-pt-head">
          <img src="/images/report_header.jpeg" style="width:100%; display:block;" />
        </td></tr>
      </thead>
      <tfoot>
        <tr><td id="ict-pt-foot">
          <img src="/images/report_footer.jpeg" style="width:100%; display:block;" />
        </td></tr>
      </tfoot>
      <tbody>
        <tr><td id="ict-pt-body">

          <!-- Title -->
          <div style="text-align:center; margin:10px 0 14px;">
            <h2 style="font-size:14pt; font-weight:bold; margin:0;">ICT EQUIPMENT INVENTORY REPORT</h2>
            <p style="margin:4px 0 0; font-size:9pt; color:#555;">
              Grouped by {{ reportGroupBy === 'category' ? 'Category' : 'Location / Room' }}
              &nbsp;&mdash;&nbsp;
              As of {{ new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}
            </p>
          </div>

          <!-- Summary Table -->
          <div style="margin-bottom:6px; font-size:10pt; font-weight:bold; color:#1e3a8a;">SUMMARY</div>
          <table style="width:50%; border-collapse:collapse; font-size:9pt; margin-bottom:20px;">
            <thead>
              <tr style="background:#e5e7eb;">
                <th style="border:1px solid #000; padding:5px 8px; text-align:left; font-weight:bold; color:#000;">
                  {{ reportGroupBy === 'category' ? 'Category' : 'Location / Room' }}
                </th>
                <th style="border:1px solid #000; padding:5px 8px; text-align:center; width:60px; font-weight:bold; color:#000;">Count</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(items, groupName) in groupedEquipments" :key="'s-'+groupName" class="ict-summary-row">
                <td style="border:1px solid #000; padding:4px 8px;">{{ groupName }}</td>
                <td style="border:1px solid #000; padding:4px 8px; text-align:center;">{{ items.length }}</td>
              </tr>
              <tr style="background:#f3f4f6; font-weight:bold;">
                <td style="border:1px solid #000; padding:4px 8px;">TOTAL</td>
                <td style="border:1px solid #000; padding:4px 8px; text-align:center;">{{ props.equipments?.total ?? 0 }}</td>
              </tr>
            </tbody>
          </table>

          <!-- Section divider -->
          <div style="border-top:2px solid #1d4ed8; margin-bottom:14px;"></div>
          <div style="font-size:10pt; font-weight:bold; color:#1e3a8a; margin-bottom:10px;">DETAILED INVENTORY</div>

          <!-- Equipment groups -->
          <template v-for="(items, groupName) in groupedEquipments" :key="groupName">
            <div class="ict-group-header" style="margin-top:14px; margin-bottom:0; font-size:10pt; font-weight:bold; background:#f3f4f6; padding:4px 8px; border-left:3px solid #2563eb;">
              {{ groupName }} ({{ items.length }})
            </div>
            <table class="ict-group-table" style="width:100%; border-collapse:collapse; font-size:8.5pt; margin-bottom:4px;">
              <thead>
                <tr style="background:#e5e7eb;">
                  <th style="border:1px solid #999; padding:4px 6px; text-align:left; width:60px;">Prop. No.</th>
                  <th style="border:1px solid #999; padding:4px 6px; text-align:left; width:90px;">Serial No.</th>
                  <th style="border:1px solid #999; padding:4px 6px; text-align:left;">Description</th>
                  <th style="border:1px solid #999; padding:4px 6px; text-align:left; width:120px;">Owner</th>
                  <th v-if="reportGroupBy === 'location'" style="border:1px solid #999; padding:4px 6px; text-align:left; width:90px;">Category</th>
                  <th v-if="reportGroupBy === 'category'" style="border:1px solid #999; padding:4px 6px; text-align:left; width:100px;">Location</th>
                  <th style="border:1px solid #999; padding:4px 6px; text-align:left; width:80px;">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="eq in items" :key="eq.id" class="ict-data-row">
                  <td style="border:1px solid #ccc; padding:3px 6px;">{{ eq.property_no || '—' }}</td>
                  <td style="border:1px solid #ccc; padding:3px 6px;">{{ eq.serial_no || '—' }}</td>
                  <td style="border:1px solid #ccc; padding:3px 6px;">{{ eq.description }}</td>
                  <td style="border:1px solid #ccc; padding:3px 6px;">{{ props.users.find(u => u.id === eq.owner_id)?.name || 'N/A' }}</td>
                  <td v-if="reportGroupBy === 'location'" style="border:1px solid #ccc; padding:3px 6px;">{{ eq.category || '—' }}</td>
                  <td v-if="reportGroupBy === 'category'" style="border:1px solid #ccc; padding:3px 6px;">{{ eq.room?.name || '—' }}</td>
                  <td style="border:1px solid #ccc; padding:3px 6px;">{{ eq.status }}</td>
                </tr>
              </tbody>
            </table>
          </template>

        </td></tr>
      </tbody>
    </table>
  </div>
  </Teleport>

</template>

<style>
#ict-print-area {
  display: none;
}

@page {
  margin: 0.25in 0 0 0;
}

@media print {
  #app {
    display: none !important;
  }

  #ict-print-area {
    display: block !important;
  }

  #ict-pt-wrap {
    width: 100%;
    border-collapse: collapse;
  }

  #ict-pt-head {
    padding: 0;
  }

  #ict-pt-foot {
    padding: 0;
  }

  #ict-pt-body {
    padding: 10px 1in;
    vertical-align: top;
  }

  /* Prevent individual data rows from being split across pages */
  .ict-data-row {
    break-inside: avoid;
    page-break-inside: avoid;
  }

  /* Summary rows should also stay whole */
  .ict-summary-row {
    break-inside: avoid;
    page-break-inside: avoid;
  }

  /* Keep group header attached to the table that follows it */
  .ict-group-header {
    break-after: avoid;
    page-break-after: avoid;
  }

  /* Allow the group table itself to break across pages (for large groups) */
  .ict-group-table {
    break-inside: auto;
    page-break-inside: auto;
  }
}
</style>
