<script setup>
import { Head, usePage, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { ref, computed } from "vue"
import Swal from "sweetalert2"
import { useSubmit } from "@/Composables/useSubmit"

import {
  EyeIcon,
  PencilSquareIcon,
  TrashIcon,
  PlusIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  MapPinIcon,
  ComputerDesktopIcon,
  CheckIcon,
} from "@heroicons/vue/24/outline"
import usePMS from "@/Composables/usePMS.js"

import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import AppTable from "@/Components/AppTable.vue"
import AppModal from "@/Components/AppModal.vue"
import AppBadge from "@/Components/AppBadge.vue"
import EmptyState from "@/Components/EmptyState.vue"

const props = defineProps({
  pmsSchedules: Object,
  users: Array,
  equipments: Array,
  filters: Object,
})

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

const page = usePage()
const userRole = page.props.auth?.user?.role?.name ?? null
const { isSubmitting, submit } = useSubmit()

// ── Assign Equipment Modal ────────────────────────────────────────────────────

const showAssignEquipmentModal = ref(false)
const selectedPMS              = ref(null)
const addMode                  = ref('individual')   // 'individual' | 'location'
const equipSearch              = ref('')
const selectedIds              = ref(new Set())
const expandedLocations        = ref(new Set())

const availableEquipments = computed(() => props.equipments ?? [])

// Group by room for location mode
const locationGroups = computed(() => {
  const groups = {}
  for (const eq of availableEquipments.value) {
    const key  = eq.room?.id ?? '__none__'
    const name = eq.room?.name ?? 'No Location'
    if (!groups[key]) groups[key] = { id: key, name, equipments: [] }
    groups[key].equipments.push(eq)
  }
  return Object.values(groups).sort((a, b) => a.name.localeCompare(b.name))
})

// Filtered list — individual mode
const filteredEquipments = computed(() => {
  const q = equipSearch.value.toLowerCase().trim()
  if (!q) return availableEquipments.value
  return availableEquipments.value.filter(eq =>
    eq.description?.toLowerCase().includes(q) ||
    eq.serial_no?.toLowerCase().includes(q) ||
    eq.category?.toLowerCase().includes(q) ||
    eq.room?.name?.toLowerCase().includes(q)
  )
})

// Filtered groups — location mode
const filteredLocations = computed(() => {
  const q = equipSearch.value.toLowerCase().trim()
  if (!q) return locationGroups.value
  return locationGroups.value
    .map(loc => ({
      ...loc,
      equipments: loc.equipments.filter(eq =>
        loc.name.toLowerCase().includes(q) ||
        eq.description?.toLowerCase().includes(q) ||
        eq.serial_no?.toLowerCase().includes(q)
      ),
    }))
    .filter(loc => loc.name.toLowerCase().includes(q) || loc.equipments.length)
})

// Selection helpers
const isSelected = (id) => selectedIds.value.has(id)

function toggleEquipment(id) {
  const s = new Set(selectedIds.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selectedIds.value = s
}

const isLocationFull = (loc) => loc.equipments.every(eq => selectedIds.value.has(eq.id))
const isLocationPartial = (loc) =>
  loc.equipments.some(eq => selectedIds.value.has(eq.id)) && !isLocationFull(loc)

function toggleLocation(loc) {
  const s    = new Set(selectedIds.value)
  const full = loc.equipments.every(eq => s.has(eq.id))
  loc.equipments.forEach(eq => full ? s.delete(eq.id) : s.add(eq.id))
  selectedIds.value = s
}

function toggleExpand(id) {
  const s = new Set(expandedLocations.value)
  s.has(id) ? s.delete(id) : s.add(id)
  expandedLocations.value = s
}

const selectedCount = computed(() => selectedIds.value.size)

// Selected equipment objects for display in summary chips
const selectedEquipmentObjects = computed(() =>
  availableEquipments.value.filter(eq => selectedIds.value.has(eq.id))
)

function openAssignEquipmentModal(schedule) {
  selectedPMS.value       = schedule
  addMode.value           = 'individual'
  equipSearch.value       = ''
  selectedIds.value       = new Set()
  expandedLocations.value = new Set()
  showAssignEquipmentModal.value = true
}

function closeAssignEquipmentModal() {
  showAssignEquipmentModal.value = false
  selectedPMS.value       = null
  selectedIds.value       = new Set()
  equipSearch.value       = ''
  expandedLocations.value = new Set()
}

function assignEquipment() {
  if (!selectedIds.value.size || !selectedPMS.value) return
  Swal.fire({
    title: 'Assigning equipment…',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading(),
  })
  submit.post(
    route("ict-pms.assign-equipments", selectedPMS.value.id),
    { equipment_ids: [...selectedIds.value] },
    {
      onSuccess: () => {
        closeAssignEquipmentModal()
        Swal.fire({ icon: "success", title: "Equipment Assigned", timer: 2000, showConfirmButton: false })
      },
      onError: (e) => {
        Swal.fire({ icon: "error", title: "Error", text: e?.equipment_ids || "Failed to assign equipment." })
      },
    }
  )
}

// ── Frequency / date helpers ──────────────────────────────────────────────────

function formatFrequencyAndDates(schedule) {
  if (!schedule.schedule_dates?.length) return schedule.frequency || "N/A"
  return `${schedule.frequency} - ${schedule.schedule_dates
    .map(d => formatDateForDisplay(d))
    .join(", ")}`
}

// ── Status badge color mapping ────────────────────────────────────────────────

function statusColor(status) {
  const map = { Pending: 'amber', Ongoing: 'blue', Completed: 'green' }
  return map[status] ?? 'slate'
}

// ── Server-side filters (schedule list) ──────────────────────────────────────

const search          = ref(props.filters?.search    ?? '')
const filterStatus    = ref(props.filters?.status    ?? '')
const filterFrequency = ref(props.filters?.frequency ?? '')
const isLoading       = ref(false)

const buildParams = (page = undefined) => ({
  search:    search.value          || undefined,
  status:    filterStatus.value    || undefined,
  frequency: filterFrequency.value || undefined,
  page:      page                  || undefined,
})

const applyFilters = () => {
    isLoading.value = true
    router.get(route('ict-pms.index'), buildParams(), {
      preserveState: true,
      replace: true,
      only: ['pmsSchedules', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
}

const clearFilters = () => {
  search.value = ''
  filterStatus.value = ''
  filterFrequency.value = ''
  isLoading.value = true
  router.get(route('ict-pms.index'), {}, {
    preserveState: true,
    replace: true,
    only: ['pmsSchedules', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

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

      <AppPageHeader title="Preventive Maintenance Schedule" subtitle="Manage and track PMS schedules">
        <template #actions>
          <AppButton @click="openModal('create')">
            <PlusIcon class="w-4 h-4" /> Add Schedule
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filter Bar -->
      <AppFilterBar class="mb-4">
        <div class="relative flex-1 min-w-[200px]">
          <AppInput
            v-model="search"
            type="text"
            placeholder="Search schedules…"
            @keydown.enter.prevent="applyFilters"
          />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">⏳</span>
        </div>

        <AppSelect v-model="filterStatus" :show-blank="false">
          <option value="">All Statuses</option>
          <option value="Pending">Pending</option>
          <option value="Ongoing">Ongoing</option>
          <option value="Completed">Completed</option>
        </AppSelect>

        <AppSelect v-model="filterFrequency" :show-blank="false">
          <option value="">All Frequencies</option>
          <option value="Monthly">Monthly</option>
          <option value="Quarterly">Quarterly</option>
          <option value="Bi-Annual">Bi-Annual</option>
          <option value="Annually">Annually</option>
        </AppSelect>

        <template #actions>
          <AppButton @click="applyFilters" :disabled="isLoading">Search</AppButton>
          <AppButton v-if="search || filterStatus || filterFrequency" @click="clearFilters" :disabled="isLoading" variant="secondary">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="visibleSchedules.length === 0" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">ID</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Title</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">School Year</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Office</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Frequency</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="s in visibleSchedules" :key="s.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ s.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 font-medium">{{ s.title }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ s.school_year }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ s.office_area }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ s.frequency }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(s.status)">{{ s.status }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <AppIconButton label="View Equipment" @click="router.get(route('ict-pms.show-equipments', s.id))">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Add Equipment" variant="success" @click="openAssignEquipmentModal(s)">
                <PlusIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit" @click="openModal('edit', s)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="destroySchedule(s.id)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="s in visibleSchedules" :key="s.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-sm font-medium text-slate-800">{{ s.title }}</p>
                <p class="text-xs text-slate-400">{{ s.school_year }} &middot; {{ s.office_area }}</p>
              </div>
              <AppBadge :color="statusColor(s.status)">{{ s.status }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">Frequency: {{ s.frequency }}</p>
            <div class="flex items-center gap-1 pt-1">
              <AppIconButton label="View Equipment" @click="router.get(route('ict-pms.show-equipments', s.id))">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Add Equipment" variant="success" @click="openAssignEquipmentModal(s)">
                <PlusIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit" @click="openModal('edit', s)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="destroySchedule(s.id)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No schedules found." />
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

      <!-- ── Create / Edit / View Modal ────────────────────────────────────── -->
      <AppModal
        :show="showModal"
        :title="modalMode === 'create' ? 'New PMS Schedule' : modalMode === 'edit' ? 'Edit PMS Schedule' : 'Schedule Details'"
        size="2xl"
        @close="closeModal"
      >
        <!-- VIEW MODE -->
        <div v-if="modalMode === 'view' && selectedSchedule" class="space-y-3 text-sm text-slate-700">
          <p><span class="font-medium text-slate-600">Title:</span> {{ selectedSchedule.title }}</p>
          <p><span class="font-medium text-slate-600">School Year:</span> {{ selectedSchedule.school_year }}</p>
          <p><span class="font-medium text-slate-600">Office/Area:</span> {{ selectedSchedule.office_area }}</p>
          <p><span class="font-medium text-slate-600">Frequency:</span> {{ selectedSchedule.frequency }}</p>
          <div>
            <p class="font-medium text-slate-600 mb-1">Scheduled Dates:</p>
            <ul class="list-disc pl-5 space-y-0.5">
              <li v-for="(d, i) in selectedSchedule.schedule_dates" :key="i">{{ formatDateForDisplay(d) }}</li>
            </ul>
          </div>
          <p><span class="font-medium text-slate-600">Status:</span> {{ selectedSchedule.status }}</p>
          <p><span class="font-medium text-slate-600">Remarks:</span> {{ selectedSchedule.remarks }}</p>
        </div>

        <!-- CREATE / EDIT FORM -->
        <div v-else class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <AppInput v-model="form.title" type="text" placeholder="Enter schedule title" label="Title" required />
          </div>
          <div>
            <AppInput v-model="form.school_year" type="text" placeholder="e.g. 2025-2026" label="School Year" />
          </div>
          <div>
            <AppInput v-model="form.office_area" type="text" placeholder="Enter office/area" label="Office/Area" />
          </div>
          <div>
            <AppSelect v-model="form.frequency" label="Frequency" required :show-blank="false">
              <option disabled value="">Select frequency</option>
              <option value="Monthly">Monthly</option>
              <option value="Quarterly">Quarterly</option>
              <option value="Bi-Annual">Bi-Annual</option>
              <option value="Annually">Annually</option>
            </AppSelect>
          </div>
          <div>
            <AppSelect v-model="form.status" label="Status" :show-blank="false">
              <option value="Pending">Pending</option>
              <option value="Ongoing">Ongoing</option>
              <option value="Completed">Completed</option>
            </AppSelect>
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Scheduled Date(s) <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 gap-2 mt-1">
              <div v-for="(d, i) in scheduleDates" :key="i">
                <AppInput v-model="d.date" type="date" required />
              </div>
            </div>
          </div>
          <div class="col-span-2">
            <AppTextarea v-model="form.remarks" :rows="2" label="Remarks" />
          </div>
        </div>

        <template v-if="modalMode !== 'view'" #footer>
          <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
          <AppButton @click="submitSchedule">Save</AppButton>
        </template>
      </AppModal>

      <!-- ── Assign Equipment Modal ────────────────────────────────────────── -->
      <AppModal
        :show="showAssignEquipmentModal"
        title="Add Equipment to Schedule"
        :subtitle="selectedPMS?.title"
        size="2xl"
        body-class="!p-0 flex flex-col"
        @close="closeAssignEquipmentModal"
      >
        <!-- Mode Tabs + Search -->
        <div class="px-6 pt-4 pb-3 shrink-0 space-y-3 border-b border-slate-100">
          <!-- Mode Toggle -->
          <div class="flex gap-1 bg-slate-100 rounded-lg p-1">
            <button @click="addMode = 'individual'; equipSearch = ''"
              :class="addMode === 'individual'
                ? 'bg-white shadow text-indigo-600 font-semibold'
                : 'text-slate-500 hover:text-slate-700'"
              class="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-md text-sm transition-colors">
              <ComputerDesktopIcon class="h-4 w-4" />
              Individual
            </button>
            <button @click="addMode = 'location'; equipSearch = ''"
              :class="addMode === 'location'
                ? 'bg-white shadow text-indigo-600 font-semibold'
                : 'text-slate-500 hover:text-slate-700'"
              class="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-md text-sm transition-colors">
              <MapPinIcon class="h-4 w-4" />
              By Location
            </button>
          </div>

          <!-- Search -->
          <AppInput
            v-model="equipSearch"
            type="text"
            :placeholder="addMode === 'individual' ? 'Search by name, serial, category, or room…' : 'Search by location or equipment name…'"
          />
        </div>

        <!-- List Area -->
        <div class="flex-1 overflow-y-auto px-6 py-3">

          <!-- ── Individual Mode ── -->
          <template v-if="addMode === 'individual'">
            <p v-if="filteredEquipments.length === 0" class="text-sm text-slate-400 text-center py-10">
              No equipment matches your search.
            </p>
            <ul v-else class="divide-y divide-slate-50">
              <li
                v-for="eq in filteredEquipments"
                :key="eq.id"
                @click="toggleEquipment(eq.id)"
                class="flex items-center gap-3 py-2.5 px-2 rounded-lg cursor-pointer hover:bg-slate-50 transition-colors"
                :class="isSelected(eq.id) ? 'bg-indigo-50/60' : ''"
              >
                <!-- Checkbox -->
                <div class="h-4 w-4 rounded border flex-shrink-0 flex items-center justify-center transition-colors"
                  :class="isSelected(eq.id) ? 'bg-indigo-600 border-indigo-600' : 'border-slate-300 bg-white'">
                  <CheckIcon v-if="isSelected(eq.id)" class="h-3 w-3 text-white" />
                </div>
                <!-- Info -->
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-slate-800 truncate">{{ eq.description }}</p>
                  <p class="text-xs text-slate-400 truncate">
                    <span v-if="eq.serial_no" class="font-mono">{{ eq.serial_no }}</span>
                    <span v-if="eq.serial_no && eq.category"> · </span>
                    <span v-if="eq.category">{{ eq.category }}</span>
                  </p>
                </div>
                <!-- Location badge -->
                <span class="inline-flex items-center gap-1 text-[11px] text-slate-400 shrink-0 ml-2">
                  <MapPinIcon class="h-3 w-3" />{{ eq.room?.name ?? 'No Location' }}
                </span>
              </li>
            </ul>
          </template>

          <!-- ── By Location Mode ── -->
          <template v-else>
            <p v-if="filteredLocations.length === 0" class="text-sm text-slate-400 text-center py-10">
              No locations match your search.
            </p>
            <ul v-else class="space-y-1">
              <li v-for="loc in filteredLocations" :key="loc.id" class="rounded-lg border border-slate-100 overflow-hidden">

                <!-- Location Header Row -->
                <div
                  class="flex items-center gap-3 px-3 py-2.5 cursor-pointer select-none hover:bg-slate-50 transition-colors"
                  :class="isLocationFull(loc) ? 'bg-indigo-50/50' : ''"
                >
                  <!-- Location checkbox -->
                  <div
                    @click.stop="toggleLocation(loc)"
                    class="h-4 w-4 rounded border flex-shrink-0 flex items-center justify-center transition-colors cursor-pointer"
                    :class="isLocationFull(loc) ? 'bg-indigo-600 border-indigo-600'
                           : isLocationPartial(loc) ? 'bg-indigo-200 border-indigo-400'
                           : 'border-slate-300 bg-white'"
                  >
                    <CheckIcon v-if="isLocationFull(loc)" class="h-3 w-3 text-white" />
                    <span v-else-if="isLocationPartial(loc)" class="h-1.5 w-1.5 rounded-sm bg-indigo-600 block"></span>
                  </div>

                  <!-- Location name + count -->
                  <div class="flex-1 min-w-0 flex items-center gap-2" @click="toggleExpand(loc.id)">
                    <MapPinIcon class="h-4 w-4 text-slate-400 shrink-0" />
                    <span class="text-sm font-medium text-slate-700 truncate">{{ loc.name }}</span>
                    <span class="text-[11px] text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded-full shrink-0">
                      {{ loc.equipments.length }} item{{ loc.equipments.length !== 1 ? 's' : '' }}
                    </span>
                  </div>

                  <!-- Expand toggle -->
                  <button @click.stop="toggleExpand(loc.id)" class="p-0.5 text-slate-400 hover:text-slate-600 transition-colors">
                    <ChevronDownIcon v-if="expandedLocations.has(loc.id)" class="h-4 w-4" />
                    <ChevronRightIcon v-else class="h-4 w-4" />
                  </button>
                </div>

                <!-- Equipment rows (expanded) -->
                <ul v-if="expandedLocations.has(loc.id)" class="border-t border-slate-100 divide-y divide-slate-50 bg-slate-50/30">
                  <li
                    v-for="eq in loc.equipments"
                    :key="eq.id"
                    @click="toggleEquipment(eq.id)"
                    class="flex items-center gap-3 px-5 py-2.5 cursor-pointer hover:bg-white transition-colors"
                    :class="isSelected(eq.id) ? 'bg-indigo-50/40' : ''"
                  >
                    <div class="h-4 w-4 rounded border flex-shrink-0 flex items-center justify-center transition-colors"
                      :class="isSelected(eq.id) ? 'bg-indigo-600 border-indigo-600' : 'border-slate-300 bg-white'">
                      <CheckIcon v-if="isSelected(eq.id)" class="h-3 w-3 text-white" />
                    </div>
                    <ComputerDesktopIcon class="h-4 w-4 text-slate-300 shrink-0" />
                    <div class="flex-1 min-w-0">
                      <p class="text-sm text-slate-700 truncate">{{ eq.description }}</p>
                      <p class="text-xs text-slate-400 font-mono">{{ eq.serial_no ?? '—' }}
                        <span v-if="eq.category" class="font-sans"> · {{ eq.category }}</span>
                      </p>
                    </div>
                  </li>
                </ul>
              </li>
            </ul>
          </template>
        </div>

        <template #footer>
          <div class="w-full">
            <!-- Selected chips -->
            <div v-if="selectedCount > 0" class="mb-3">
              <p class="text-xs font-medium text-slate-500 mb-1.5">
                {{ selectedCount }} equipment selected
              </p>
              <div class="flex flex-wrap gap-1 max-h-16 overflow-y-auto">
                <AppBadge v-for="eq in selectedEquipmentObjects" :key="eq.id" color="indigo">
                  {{ eq.description }}
                  <button @click.stop="toggleEquipment(eq.id)" class="ml-0.5 text-indigo-400 hover:text-indigo-700 leading-none">&times;</button>
                </AppBadge>
              </div>
            </div>
            <p v-else class="text-xs text-slate-400 mb-3">No equipment selected yet.</p>

            <div class="flex justify-end gap-2">
              <AppButton variant="secondary" @click="closeAssignEquipmentModal">Cancel</AppButton>
              <AppButton
                :loading="isSubmitting"
                :disabled="isSubmitting || selectedCount === 0"
                @click="assignEquipment"
              >
                {{ isSubmitting ? 'Assigning…' : `Assign ${selectedCount > 0 ? selectedCount + ' item' + (selectedCount > 1 ? 's' : '') : ''}` }}
              </AppButton>
            </div>
          </div>
        </template>
      </AppModal>

    </div>
  </AdminLayout>
</template>
