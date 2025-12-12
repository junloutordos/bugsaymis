<script setup>
import { Head, usePage, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { ref, computed, watch } from "vue"
import Swal from "sweetalert2"
import Multiselect from "vue-multiselect"
import "vue-multiselect/dist/vue-multiselect.css"

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
  pmsSchedules: Array,
  users: Array,
  equipments: Array,
})

// Composable
const {
  schedules,
  errors,
  showModal,
  modalMode,
  selectedSchedule,
  form,
  scheduleDates,
  searchQuery,
  currentPage,
  totalPages,
  filteredSchedules,
  getSchedules,
  destroySchedule,
  sortBy,
  openModal,
  closeModal,
  submitSchedule,
  viewSchedule,
  exportCSV,
  printTable,
  formatDateForDisplay,
} = usePMS(props.pmsSchedules)

// User role
const page = usePage()
const userRole = page.props.auth?.user?.role?.name ?? null

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
  router.post(
    route("ict-pms.assign-equipments", selectedPMS.value.id),
    { equipment_ids: selectedEquipments.value.map(e => e.id) },
    {
      onSuccess: () => {
        closeAssignEquipmentModal()
        getSchedules()
        Swal.fire({
          icon: "success",
          title: "Equipments Assigned",
          timer: 2000,
          showConfirmButton: false,
        })
      },
      onError: (e) => {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: e?.equipment_ids || "Failed to assign equipments.",
        })
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
</script>

<template>
  <Head title="Preventive Maintenance Schedule" />
  <AdminLayout title="Preventive Maintenance Schedule">
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">
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
        <div class="flex justify-between items-center mb-4">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search schedules..."
            class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
          />
          <div class="flex gap-2">
            <button @click="exportCSV">
              <ArrowDownTrayIcon class="w-5 h-5 text-blue-600" />
            </button>
            <button @click="printTable">
              <PrinterIcon class="w-5 h-5 text-blue-600" />
            </button>
          </div>
        </div>

        <!-- PMS Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full border border-gray-200 text-center">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 cursor-pointer" @click="sortBy('id')">ID</th>
                <th class="px-4 py-3 cursor-pointer" @click="sortBy('title')">Title</th>
                <th class="px-4 py-3 cursor-pointer" @click="sortBy('school_year')">School Year</th>
                <th class="px-4 py-3 cursor-pointer" @click="sortBy('office_area')">Office</th>
                <th class="px-4 py-3 cursor-pointer" @click="sortBy('frequency')">Frequency</th>
                <th class="px-4 py-3 cursor-pointer" @click="sortBy('status')">Status</th>
                <th class="px-4 py-3">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm text-center">
              <tr v-for="s in filteredSchedules" :key="s.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ s.id }}</td>
                <td class="px-4 py-3">{{ s.title }}</td>
                <td class="px-4 py-3">{{ s.school_year }}</td>
                <td class="px-4 py-3">{{ s.office_area }}</td>
                <td class="px-4 py-3">{{ s.frequency }}</td>
                <td class="px-4 py-3">{{ s.status }}</td>
                <td class="px-4 py-3">
                  <div class="flex justify-center gap-1 items-center">
                    <button
                      @click="router.get(route('ict-pms.show-equipments', s.id))"
                      class="p-1 hover:bg-gray-100 rounded"
                    >
                      <EyeIcon class="w-5 h-5 text-blue-600" />
                    </button>
                    <button
                      @click="openAssignEquipmentModal(s)"
                      class="p-1 hover:bg-gray-100 rounded"
                      title="Assign Equipment"
                    >
                      <PlusIcon class="w-5 h-5 text-green-600" />
                    </button>
                    <button
                      @click="openModal('edit', s)"
                      class="p-1 hover:bg-gray-100 rounded"
                    >
                      <PencilSquareIcon class="w-5 h-5 text-yellow-600" />
                    </button>
                    <button
                      @click="destroySchedule(s.id)"
                      class="p-1 hover:bg-gray-100 rounded"
                    >
                      <TrashIcon class="w-5 h-5 text-red-600" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredSchedules.length === 0">
                <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                  No schedules found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>


        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
          <button
            @click="currentPage--"
            :disabled="currentPage === 1"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
          >
            Prev
          </button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button
            @click="currentPage++"
            :disabled="currentPage === totalPages"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
          >
            Next
          </button>
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
                :custom-label="eq => `${eq.location} -${eq.serial_no} - ${eq.description} - ${eq.category}`"
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
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
              >
                Assign
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
