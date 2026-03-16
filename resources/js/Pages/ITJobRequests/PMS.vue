<script setup>
import { Head, usePage, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { ref, computed, watch } from "vue"
import Swal from "sweetalert2"
import Multiselect from "vue-multiselect"
import "vue-multiselect/dist/vue-multiselect.css"
import { useSubmit } from "@/Composables/useSubmit"

import {
  EyeIcon,
  PencilSquareIcon,
  PrinterIcon,
  TrashIcon,
  ArrowDownTrayIcon,
  PlusIcon,
} from "@heroicons/vue/24/outline"
import usePMS from "@/Composables/usePMS.js"

// Props from backend
const props = defineProps({
  pmsSchedules: Object,
  users: Array,
  equipments: Array,
  filters: Object,
})

// Composable
const {
  errors,
  showModal,
  modalMode,
  selectedSchedule,
  form,
  scheduleDates,
  destroySchedule,
  openModal,
  closeModal,
  submitSchedule,
  formatDateForDisplay,
} = usePMS(props.pmsSchedules?.data ?? [])

// User role
const page = usePage()
const userRole = page.props.auth?.user?.role?.name ?? null

const { isSubmitting, submit } = useSubmit()

// Equipment assignment modal state
const showAssignEquipmentModal = ref(false)
const selectedPMS = ref(null)
const selectedEquipments = ref([])

// Equipments list
const availableEquipments = computed(() => props.equipments || [])

// Equipment assignment
function openAssignEquipmentModal(schedule) {
  selectedPMS.value = schedule
  showAssignEquipmentModal.value = true
  selectedEquipments.value = []
}
function closeAssignEquipmentModal() {
  showAssignEquipmentModal.value = false
  selectedPMS.value = null
  selectedEquipments.value = []
}
function assignEquipment() {
  if (!selectedEquipments.value.length || !selectedPMS.value) return
  Swal.fire({ title: 'Assigning equipment...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => { Swal.showLoading() } })
  submit.post(
    route("ict-pms.assign-equipments", selectedPMS.value.id),
    { equipment_ids: selectedEquipments.value.map(e => e.id) },
    {
      onSuccess: () => {
        closeAssignEquipmentModal()
        Swal.fire({ icon: "success", title: "Equipments Assigned", timer: 2000, showConfirmButton: false })
      },
      onError: (e) => {
        Swal.fire({ icon: "error", title: "Error", text: e?.equipment_ids || "Failed to assign equipments." })
      },
    }
  )
}

// --- Utility: format frequency + dates ---
function formatFrequencyAndDates(schedule) {
  if (!schedule.schedule_dates?.length) return schedule.frequency || "N/A"
  return `${schedule.frequency} - ${schedule.schedule_dates
    .map(d => formatDateForDisplay(d))
    .join(", ")}`
}

// Server-side filters
const search          = ref(props.filters?.search    ?? '')
const filterStatus    = ref(props.filters?.status    ?? '')
const filterFrequency = ref(props.filters?.frequency ?? '')
const isLoading       = ref(false)
let debounceTimer     = null

const buildParams = (page = undefined) => ({
  search:    search.value          || undefined,
  status:    filterStatus.value    || undefined,
  frequency: filterFrequency.value || undefined,
  page:      page                  || undefined,
})

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('ict-pms.index'), buildParams(), {
      preserveState: true,
      replace: true,
      only: ['pmsSchedules', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
  }
  if (immediate) go()
  else debounceTimer = setTimeout(go, 400)
}

watch(search,          () => applyFilters(false))
watch(filterStatus,    () => applyFilters(true))
watch(filterFrequency, () => applyFilters(true))

const goToPage = (pageNum) => {
  isLoading.value = true
  router.get(route('ict-pms.index'), buildParams(pageNum), {
    preserveState: true,
    replace: true,
    only: ['pmsSchedules', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

const visibleSchedules = computed(() => props.pmsSchedules?.data ?? [])
const currentPage      = computed(() => props.pmsSchedules?.current_page ?? 1)
const totalPages       = computed(() => props.pmsSchedules?.last_page ?? 1)
</script>

<template>
  <Head title="Preventive Maintenance Schedule" />
  <AdminLayout title="Preventive Maintenance Schedule">
    <div>
      <!-- Header -->
      <div class="flex items-center justify-between mb-4 gap-2">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800 truncate">
          Preventive Maintenance Schedule
        </h1>
        <button
          @click="openModal('create')"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
        >
          <PlusIcon class="w-5 h-5 inline mr-1" /> Add Schedule
        </button>
      </div>

      <!-- Search & Actions -->
      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
          <div class="flex items-center gap-2">
            <div class="relative">
              <input
                v-model="search"
                type="text"
                placeholder="Search schedules..."
                @keydown.enter.prevent="applyFilters(true)"
                class="w-64 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
              />
              <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
                <svg class="animate-spin h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
              </span>
            </div>
            <button @click="applyFilters(true)" :disabled="isLoading" class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
              Search
            </button>
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            <select v-model="filterStatus" class="rounded-lg border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
              <option value="">All Statuses</option>
              <option value="Pending">Pending</option>
              <option value="Ongoing">Ongoing</option>
              <option value="Completed">Completed</option>
            </select>
            <select v-model="filterFrequency" class="rounded-lg border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
              <option value="">All Frequencies</option>
              <option value="Monthly">Monthly</option>
              <option value="Quarterly">Quarterly</option>
              <option value="Bi-Annual">Bi-Annual</option>
              <option value="Annually">Annually</option>
            </select>
          </div>
        </div>

        <!-- Loading overlay -->
        <div v-if="isLoading" class="relative">
          <div class="absolute inset-0 bg-white bg-opacity-70 flex items-center justify-center z-10 rounded-lg">
            <div class="flex flex-col items-center gap-2 text-blue-600">
              <svg class="animate-spin h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              <span class="text-sm font-medium">Loading...</span>
            </div>
          </div>
        </div>

        <!-- PMS Table -->
        <div class="overflow-x-auto" :class="{ 'opacity-50 pointer-events-none': isLoading }">
          <table class="min-w-full border border-gray-200 text-center">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3">ID</th>
                <th class="px-4 py-3">Title</th>
                <th class="px-4 py-3">School Year</th>
                <th class="px-4 py-3">Office</th>
                <th class="px-4 py-3">Frequency</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm text-center">
              <tr v-for="s in visibleSchedules" :key="s.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ s.id }}</td>
                <td class="px-4 py-3">{{ s.title }}</td>
                <td class="px-4 py-3">{{ s.school_year }}</td>
                <td class="px-4 py-3">{{ s.office_area }}</td>
                <td class="px-4 py-3">{{ s.frequency }}</td>
                <td class="px-4 py-3">{{ s.status }}</td>
                <td class="px-4 py-3">
                  <div class="flex justify-center gap-1 items-center">
                    <button @click="router.get(route('ict-pms.show-equipments', s.id))" class="p-2 bg-blue-100 rounded hover:bg-blue-200" title="View">
                      <EyeIcon class="w-5 h-5 text-blue-700" />
                    </button>
                    <button @click="openAssignEquipmentModal(s)" class="p-2 bg-green-100 rounded hover:bg-green-200" title="Assign Equipment">
                      <PlusIcon class="w-5 h-5 text-green-700" />
                    </button>
                    <button @click="openModal('edit', s)" class="p-2 bg-yellow-100 rounded hover:bg-yellow-200" title="Edit">
                      <PencilSquareIcon class="w-5 h-5 text-yellow-700" />
                    </button>
                    <button @click="destroySchedule(s.id)" class="p-2 bg-red-100 rounded hover:bg-red-200" title="Delete">
                      <TrashIcon class="w-5 h-5 text-red-700" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="visibleSchedules.length === 0">
                <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                  No schedules found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>


        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1 || isLoading" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages || isLoading" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- Modal -->
      <div
        v-show="showModal"
        class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 transition-opacity"
      >
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative">
          <button
            class="absolute top-3 right-3 text-gray-500 hover:text-gray-800"
            @click="closeModal"
          >
            ✕
          </button>

          <h2 class="text-xl font-semibold mb-4">
            {{ modalMode === "create" ? "New PMS Schedule" : modalMode === "edit" ? "Edit PMS Schedule" : "Schedule Details" }}
          </h2>

          <!-- VIEW MODE -->
          <div v-if="modalMode === 'view' && selectedSchedule" class="space-y-2">
            <p><strong>Title:</strong> {{ selectedSchedule.title }}</p>
            <p><strong>School Year:</strong> {{ selectedSchedule.school_year }}</p>
            <p><strong>Office/Area:</strong> {{ selectedSchedule.office_area }}</p>
            <p><strong>Frequency:</strong> {{ selectedSchedule.frequency }}</p>
            <p><strong>Scheduled Dates:</strong></p>
            <ul class="list-disc pl-6">
              <li v-for="(d, i) in selectedSchedule.schedule_dates" :key="i">
                {{ formatDateForDisplay(d) }}
              </li>
            </ul>
            <p><strong>Status:</strong> {{ selectedSchedule.status }}</p>
            <p><strong>Remarks:</strong> {{ selectedSchedule.remarks }}</p>
          </div>

          <!-- CREATE / EDIT FORM -->
          <form v-else @submit.prevent="submitSchedule" class="grid grid-cols-2 gap-4">
            <!-- Title -->
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700">
                Title <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.title"
                type="text"
                placeholder="Enter schedule title"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
                required
              />
            </div>

            <!-- School Year -->
            <div>
              <label class="block text-sm font-medium text-gray-700">School Year</label>
              <input
                v-model="form.school_year"
                type="text"
                placeholder="e.g. 2025-2026"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
              />
            </div>

            <!-- Office/Area -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Office/Area</label>
              <input
                v-model="form.office_area"
                type="text"
                placeholder="Enter office/area"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
              />
            </div>

            <!-- Frequency -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Frequency</label>
              <select
                v-model="form.frequency"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
                required
              >
                <option disabled value="">Select frequency</option>
                <option value="Monthly">Monthly</option>
                <option value="Quarterly">Quarterly</option>
                <option value="Bi-Annual">Bi-Annual</option>
                <option value="Annually">Annually</option>
              </select>
            </div>

            <!-- Status -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Status</label>
              <select
                v-model="form.status"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
              >
                <option value="Pending">Pending</option>
                <option value="Completed">Completed</option>
                <option value="Ongoing">Ongoing</option>
              </select>
            </div>

            <!-- Dynamic Dates -->
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700">
                Scheduled Date(s) <span class="text-red-500">*</span>
              </label>
              <div class="grid grid-cols-2 gap-2 mt-2">
                <div v-for="(d, i) in scheduleDates" :key="i">
                  <input
                    v-model="d.date"
                    type="date"
                    class="block w-full rounded-lg border-gray-300 shadow-sm"
                    required
                  />
                </div>
              </div>
            </div>

            <!-- Remarks -->
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700">Remarks</label>
              <textarea
                v-model="form.remarks"
                rows="2"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
              ></textarea>
            </div>

            <!-- Actions -->
            <div class="col-span-2 flex justify-end space-x-3 pt-4">
              <button
                type="button"
                @click="closeModal"
                class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
              >
                Save
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Assign Equipment Modal -->
      <div
        v-if="showAssignEquipmentModal"
        class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 transition-opacity"
      >
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
          <button
            class="absolute top-3 right-3 text-gray-500 hover:text-gray-800"
            @click="closeAssignEquipmentModal"
          >
            ✕
          </button>
          <h2 class="text-xl font-semibold mb-4">Assign Equipment to PMS Schedule</h2>
          <form @submit.prevent="assignEquipment" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Select Equipment(s)</label>
              <multiselect
                v-model="selectedEquipments"
                :options="availableEquipments"
                :multiple="true"
                :close-on-select="false"
                :clear-on-select="false"
                :preserve-search="true"
                placeholder="Pick equipment(s)"
                label="description"
                track-by="id"
                :custom-label="eq => `${eq.room?.name || 'No Location'} - ${eq.serial_no || 'N/A'} - ${eq.description} - ${eq.category || 'N/A'}`"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
              />

            </div>
            <div class="flex justify-end space-x-3">
              <button
                type="button"
                @click="closeAssignEquipmentModal"
                class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="isSubmitting"
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ isSubmitting ? 'Assigning…' : 'Assign' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
