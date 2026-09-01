<script setup>
import { Head, usePage, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { ref, computed, watch } from "vue"
import axios from "axios"
import Swal from "sweetalert2"
import {
  EyeIcon,
  PencilSquareIcon,
  PrinterIcon,
  TrashIcon,
  ArrowDownTrayIcon,
  ClockIcon,
  ChartBarIcon,
  PlusIcon,
  KeyIcon,
  ExclamationTriangleIcon,
  CpuChipIcon,
  Square3Stack3DIcon,
  CircleStackIcon,
  WifiIcon,
  FireIcon,
  BoltIcon,
  ShieldCheckIcon,
  ServerStackIcon,
  WrenchScrewdriverIcon,
  CheckBadgeIcon,
  ArchiveBoxIcon,
  MagnifyingGlassIcon,
  ArrowsRightLeftIcon,
  CameraIcon,
  CheckCircleIcon,
} from "@heroicons/vue/24/outline"
import useEquipments from "@/Composables/useEquipments.js"
import BarcodeScannerModal from "@/Components/ICTEquipment/BarcodeScannerModal.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import AppTable from "@/Components/AppTable.vue"
import AppModal from "@/Components/AppModal.vue"
import EmptyState from "@/Components/EmptyState.vue"
import PaginationControl from "@/Components/PaginationControl.vue"
import SecurityPanel from "./SecurityPanel.vue"

// Props from backend
const props = defineProps({
  equipments: Object,
  users: Array,
  rooms: Array,
  filters: Object,
  pendingSetupCount: { type: Number, default: 0 },
  latestAgentVersion: { type: String, default: null },
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
  showMergeModal,
  mergeTargetId,
  mergeErrors,
  isSubmittingMerge,
  openMergeModal,
  closeMergeModal,
  submitMerge,
} = useEquipments(props.equipments?.data ?? [], props.users)

const showScannerModal = ref(false)
const serialJustScanned = ref(false)
let serialScanFlashTimer = null

function openScanner() {
  showScannerModal.value = true
}

function handleSerialScanned(value) {
  form.value.serial_no = value
  showScannerModal.value = false
  serialJustScanned.value = true
  clearTimeout(serialScanFlashTimer)
  serialScanFlashTimer = setTimeout(() => { serialJustScanned.value = false }, 1500)
}

const page = usePage()
const userRole = page.props.auth?.user?.role?.name ?? null
const csrfToken = page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.content || ''

// Report state
const showReportModal = ref(false)
const reportGroupBy = ref('category') // 'category' or 'location'

// Atlas Sentinel enrollment token state
const showEnrollmentModal = ref(false)
const enrollmentToken = ref(null)
const enrollmentExpiresAt = ref(null)
const enrollmentMaxUses = ref(1)
const isGeneratingToken = ref(false)
const tokenCopied = ref(false)
const enrollmentMode = ref('single') // 'single' | 'bulk'
const bulkLabel = ref('')
const bulkUnlimited = ref(false)
const bulkQuantity = ref(10)
const bulkExpiryHours = ref(72)
const outstandingTokens = ref([])
const loadingTokens = ref(false)

function switchEnrollmentMode(mode) {
  enrollmentMode.value = mode
  enrollmentToken.value = null
  tokenCopied.value = false
}

async function loadOutstandingTokens() {
  loadingTokens.value = true
  try {
    const { data } = await axios.get(route('ict-equipments.enrollment-tokens.index'))
    outstandingTokens.value = data.tokens
  } finally {
    loadingTokens.value = false
  }
}

async function generateEnrollmentToken() {
  isGeneratingToken.value = true
  tokenCopied.value = false
  try {
    const { data } = await axios.post(route('ict-equipments.enrollment-token'))
    enrollmentToken.value = data.token
    enrollmentExpiresAt.value = data.expires_at
    enrollmentMaxUses.value = data.max_uses
    enrollmentMode.value = 'single'
    showEnrollmentModal.value = true
    loadOutstandingTokens()
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message || 'Could not generate enrollment token.', 'error')
  } finally {
    isGeneratingToken.value = false
  }
}

async function generateBulkToken() {
  isGeneratingToken.value = true
  tokenCopied.value = false
  try {
    const { data } = await axios.post(route('ict-equipments.enrollment-token'), {
      max_uses: bulkUnlimited.value ? null : bulkQuantity.value,
      expires_in_hours: bulkExpiryHours.value,
      label: bulkLabel.value || null,
    })
    enrollmentToken.value = data.token
    enrollmentExpiresAt.value = data.expires_at
    enrollmentMaxUses.value = data.max_uses
    loadOutstandingTokens()
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message || 'Could not generate enrollment token.', 'error')
  } finally {
    isGeneratingToken.value = false
  }
}

async function revokeToken(token) {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Revoke this token?',
    text: 'Devices that haven\'t enrolled yet with this token will no longer be able to.',
    showCancelButton: true,
    confirmButtonText: 'Revoke',
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return
  await axios.post(route('ict-equipments.enrollment-token.revoke', token))
  loadOutstandingTokens()
}

async function copyEnrollmentToken() {
  await navigator.clipboard.writeText(enrollmentToken.value)
  tokenCopied.value = true
}

// Atlas Sentinel open alerts
const showAlertsModal = ref(false)
const selectedAlerts = ref([])
const selectedAlertsEquipment = ref(null)

function openAlerts(eq) {
  selectedAlerts.value = eq.alerts ?? []
  selectedAlertsEquipment.value = eq
  showAlertsModal.value = true
}

// Atlas Sentinel latest reported specs — kept as a lookup by id (not a stored
// object reference) so it stays live across the router.reload() a "Fix Now"
// click triggers; the modal would otherwise keep showing pre-reload data.
const showSpecsModal = ref(false)
const selectedSpecsEquipmentId = ref(null)
const selectedSpecsEquipment = computed(() =>
  visibleEquipments.value.find(eq => eq.id === selectedSpecsEquipmentId.value) ?? null
)

function openSpecs(eq) {
  selectedSpecsEquipmentId.value = eq.id
  showSpecsModal.value = true
  softwareSearch.value = ''
}

function truncateVersion(v) {
  if (!v) return ''
  return v.split('.').slice(0, 3).join('.')
}

function versionBadgeInfo(deviceVersion, lastUpdateResult, latestVersion) {
  if (!latestVersion) return null
  const dv = truncateVersion(deviceVersion ?? '')
  const lv = truncateVersion(latestVersion)
  if (!dv) return { label: 'Version unknown', cls: 'bg-slate-100 text-slate-500' }
  if (dv === lv) return { label: `v${dv} · Up to date`, cls: 'bg-emerald-50 text-emerald-700' }
  if (lastUpdateResult === 'failed' || lastUpdateResult === 'failed_service_down')
    return { label: `v${dv} · Update failed → v${lv}`, cls: 'bg-red-50 text-red-700' }
  return { label: `v${dv} · Update available → v${lv}`, cls: 'bg-amber-50 text-amber-700' }
}

function staleBadgeInfo(checkinAt) {
  if (!checkinAt) return null
  const age = Math.floor((Date.now() - new Date(checkinAt)) / 60000)
  if (age > 120) return { label: 'Offline?', cls: 'bg-red-50 text-red-700' }
  if (age > 40) return { label: 'Stale', cls: 'bg-amber-50 text-amber-700' }
  return null
}

const specsVersionBadge = computed(() => {
  const d = selectedSpecsEquipment.value?.agent_device
  return versionBadgeInfo(d?.agent_version, d?.last_update_result, props.latestAgentVersion)
})

const specsCheckinBadge = computed(() => {
  return staleBadgeInfo(selectedSpecsEquipment.value?.agent_device?.last_checkin_at)
})

// Installed software list — read-only display plus an opt-in uninstall
// path. Only rows with a documented silent removal method (QuietUninstallString
// or an MSI ProductCode) get an Uninstall button; everything else has no
// reliable unattended path since the agent has no interactive desktop
// session to click through a GUI uninstaller.
const softwareSearch = ref('')
const filteredSoftware = computed(() => {
  const list = selectedSpecsEquipment.value?.agent_device?.software_inventory?.installed_software ?? []
  const term = softwareSearch.value.trim().toLowerCase()
  const sorted = [...list].sort((a, b) => (a.name || '').localeCompare(b.name || ''))
  if (!term) return sorted
  return sorted.filter(sw =>
    (sw.name || '').toLowerCase().includes(term) || (sw.publisher || '').toLowerCase().includes(term)
  )
})

function isSilentlyUninstallable(sw) {
  return !!(sw.quiet_uninstall_string || sw.is_msi)
}

async function confirmUninstall(eq, sw) {
  const result = await Swal.fire({
    icon: 'warning',
    title: `Uninstall ${sw.name}?`,
    html: `This will permanently remove <strong>${sw.name}</strong> from <strong>${eq.agent_device.hostname}</strong>. This cannot be undone.`,
    showCancelButton: true,
    confirmButtonText: 'Uninstall',
    confirmButtonColor: '#dc2626',
  })
  if (result.isConfirmed) {
    runFix(eq, 'software_uninstall', sw.uninstall_key)
  }
}

// Admin-triggered "Fix Now" — bypasses the auto_execute rule gate; the
// device only picks it up on its next ~20-min check-in, so this is a queue,
// not an instant action. fixingKeys covers the gap between click and the
// page reload picking up the new 'pending' row from the backend.
const fixingKeys = ref(new Set())
const QUICK_FIX_ACTIONS = [
  { action: 'print_spooler_recovery', label: 'Clear print queue', description: 'Restarts the spooler and clears stuck jobs' },
  { action: 'temp_file_cleanup', label: 'Clean temp files', description: 'Frees disk space from temp folders' },
  { action: 'dns_flush', label: 'Flush DNS', description: 'Clears the DNS resolver cache' },
  { action: 'windows_maintenance_task', label: 'Run disk cleanup', description: 'Triggers the Windows SilentCleanup task' },
]

function fixKey(action, target) {
  return `${action}::${target ?? ''}`
}

function findManualRequest(eq, action, target, statuses) {
  const requests = eq?.agent_device?.manual_remediation_requests ?? []
  return requests.find(r => r.action === action && (r.target ?? null) === (target ?? null) && statuses.includes(r.status))
}

function isFixPending(eq, action, target = null) {
  if (fixingKeys.value.has(fixKey(action, target))) return true
  return !!findManualRequest(eq, action, target, ['pending', 'delivered'])
}

function lastFixResult(eq, action, target = null) {
  return findManualRequest(eq, action, target, ['completed', 'failed'])
}

async function runFix(eq, action, target = null) {
  const key = fixKey(action, target)
  fixingKeys.value.add(key)
  try {
    const { data } = await axios.post(route('ict-equipments.remediate', eq.id), { action, target })
    Swal.fire({ icon: 'success', title: 'Fix queued', text: data.message, timer: 2500, showConfirmButton: false })
    router.reload({ only: ['equipments'] })
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message || 'Could not queue this fix.', 'error')
  } finally {
    fixingKeys.value.delete(key)
  }
}

// Same thresholds as AtlasSentinelHealthEvaluator, so the bar's color always
// matches whether this reading would actually trigger a PMS/alert finding.
const RAM_LOW_THRESHOLD = 10
const DISK_LOW_THRESHOLD = 15

function percentFree(free, total) {
  if (!total) return null
  return Math.round((free / total) * 1000) / 10
}

// Bar width uses % used (fills up as space runs out, matching every OS
// storage-meter convention) — color stays keyed off % free via freeBarColor.
function percentUsed(free, total) {
  const freePct = percentFree(free, total)
  return freePct === null ? 0 : 100 - freePct
}

function freeBarColor(percent, threshold) {
  if (percent === null) return 'bg-slate-300'
  if (percent < threshold) return 'bg-red-500'
  if (percent < threshold * 2) return 'bg-amber-500'
  return 'bg-emerald-500'
}

function formatDateTime(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit',
  })
}

const HIGH_CPU_USAGE_THRESHOLD = 85

// Inverted sense from freeBarColor — here a HIGH percent (CPU usage) is bad.
function usageBarColor(percent, threshold) {
  if (percent === null || percent === undefined) return 'bg-slate-300'
  if (percent > threshold) return 'bg-red-500'
  if (percent > threshold * 0.7) return 'bg-amber-500'
  return 'bg-emerald-500'
}

const riskTierClasses = {
  critical: 'bg-red-100 text-red-700',
  high: 'bg-orange-100 text-orange-700',
  medium: 'bg-amber-100 text-amber-700',
  low: 'bg-emerald-100 text-emerald-700',
}

function batteryWearPct(battery) {
  if (!battery?.design_capacity_mwh || !battery?.full_charge_capacity_mwh) return null
  return Math.round((1 - battery.full_charge_capacity_mwh / battery.design_capacity_mwh) * 1000) / 10
}

function securityRowClass(value) {
  return value === false ? 'text-red-600' : value === true ? 'text-emerald-600' : 'text-slate-400'
}

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

const buildParams = (page = undefined) => ({
  search:   search.value         || undefined,
  category: filterCategory.value || undefined,
  status:   filterStatus.value   || undefined,
  per_page: perPage.value        || undefined,
  page:     page                 || undefined,
})

const applyFilters = () => {
    isLoading.value = true
    router.get(route('ict-equipments.index'), buildParams(), {
      preserveState: true,
      replace: true,
      only: ['equipments', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
}

const showPendingSetup = () => {
  filterStatus.value = 'Pending Setup'
  applyFilters()
}

const clearFilters = () => {
  search.value = ''
  filterCategory.value = ''
  filterStatus.value = ''
  isLoading.value = true
  router.get(route('ict-equipments.index'), {}, {
    preserveState: true,
    replace: true,
    only: ['equipments', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

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

const EQUIPMENT_STATUS_BADGE_COLORS = {
  'Good Working': 'green',
  'For Repair': 'amber',
  'Disposed': 'red',
  'Pending Setup': 'orange',
}

function equipmentStatusBadgeColor(status) {
  return EQUIPMENT_STATUS_BADGE_COLORS[status] ?? 'slate'
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
      <AppPageHeader title="ICT Equipment Inventory">
        <template #actions>
          <AppButton variant="secondary" @click="generateEnrollmentToken" :disabled="isGeneratingToken">
            <KeyIcon class="w-4 h-4" /> Generate Enrollment Token
          </AppButton>
          <AppButton variant="secondary" @click="switchEnrollmentMode('bulk'); showEnrollmentModal = true; loadOutstandingTokens()">
            <Square3Stack3DIcon class="w-4 h-4" /> Bulk Enrollment
          </AppButton>
          <AppButton @click="openModal('create')">
            <PlusIcon class="w-4 h-4" /> Add Equipment
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filter bar -->
      <AppFilterBar class="mb-4">
        <div class="relative w-64">
          <AppInput
            v-model="search"
            type="text"
            placeholder="Search equipment..."
            @keydown.enter.prevent="applyFilters"
          />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
            <svg class="animate-spin h-4 w-4 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
          </span>
        </div>
        <AppSelect v-model="filterCategory" :show-blank="false">
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
        </AppSelect>
        <AppSelect v-model="filterStatus" :show-blank="false">
          <option value="">All Statuses</option>
          <option value="Good Working">Good Working</option>
          <option value="For Repair">For Repair</option>
          <option value="Disposed">Disposed</option>
          <option value="Pending Setup">Pending Setup</option>
        </AppSelect>
        <AppButton
          v-if="props.pendingSetupCount > 0"
          variant="warning"
          @click="showPendingSetup"
          title="Show devices that enrolled automatically and need to be completed"
        >
          <ExclamationTriangleIcon class="w-3.5 h-3.5" />
          Needs Setup ({{ props.pendingSetupCount }})
        </AppButton>
        <label class="flex items-center gap-1 text-sm text-slate-600">
          <input
            type="checkbox"
            v-model="showAllChecked"
            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
          />
          Show All
        </label>
        <AppIconButton label="Generate Report" variant="ghost" @click="showReportModal = true">
          <PrinterIcon class="w-5 h-5" />
        </AppIconButton>

        <template #actions>
          <AppButton @click="applyFilters" :disabled="isLoading">Search</AppButton>
          <AppButton v-if="search || filterCategory || filterStatus" variant="secondary" @click="clearFilters" :disabled="isLoading">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Pending Setup banner -->
      <div
        v-if="props.pendingSetupCount > 0 && filterStatus !== 'Pending Setup'"
        class="flex items-center justify-between gap-3 bg-orange-50 border border-orange-200 rounded-xl px-4 py-3 mb-4 text-sm"
      >
        <div class="flex items-center gap-2 text-orange-700">
          <ExclamationTriangleIcon class="w-4 h-4 flex-shrink-0" />
          <span>
            <strong>{{ props.pendingSetupCount }} device{{ props.pendingSetupCount === 1 ? '' : 's' }}</strong>
            enrolled via Atlas Sentinel but {{ props.pendingSetupCount === 1 ? 'has' : 'have' }} no owner, room, or status assigned yet.
          </span>
        </div>
        <button
          @click="showPendingSetup"
          class="flex-shrink-0 text-orange-700 underline hover:no-underline font-medium"
        >
          Review now
        </button>
      </div>

      <!-- Table card -->
      <div class="relative">
        <!-- Loading overlay -->
        <div v-if="isLoading" class="absolute inset-0 bg-white/70 flex items-center justify-center z-10 rounded-xl">
          <div class="flex flex-col items-center gap-2 text-indigo-600">
            <svg class="animate-spin h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span class="text-sm font-medium">Loading...</span>
          </div>
        </div>

        <div :class="{ 'opacity-50 pointer-events-none': isLoading }">
          <AppTable :is-empty="visibleEquipments.length === 0" :skeleton-cols="8">
            <template #head>
              <tr>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">ID</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Serial No</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Description</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Owner</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Location</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Agent</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap text-center">Action</th>
              </tr>
            </template>

            <tr v-for="eq in visibleEquipments" :key="eq.id" class="hover:bg-indigo-50/40">
              <td class="px-4 py-3 text-sm text-slate-700">{{ eq.id }}</td>
              <td class="px-4 py-3 text-sm text-slate-700">{{ eq.serial_no }}</td>
              <td class="px-4 py-3 text-sm text-slate-700">{{ eq.description }}</td>
              <td class="px-4 py-3 text-sm text-slate-700">
                {{ props.users.find(u => u.id === eq.owner_id)?.name || 'N/A' }}
              </td>
              <td class="px-4 py-3">
                <AppBadge :color="equipmentStatusBadgeColor(eq.status)">{{ eq.status ?? '—' }}</AppBadge>
              </td>
              <td class="px-4 py-3 text-sm text-slate-700">{{ eq.room?.name || '—' }}</td>
              <td class="px-4 py-3 text-xs">
                <div class="flex items-center gap-2">
                  <span v-if="eq.agent_device" class="inline-flex items-center gap-1 text-emerald-700" :title="`Last check-in: ${formatDate(eq.agent_device.last_checkin_at)}`">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Linked
                  </span>
                  <span v-else class="text-slate-400">—</span>
                  <button
                    v-if="eq.alerts?.length"
                    @click="openAlerts(eq)"
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-700 hover:bg-amber-100 transition-colors"
                    :title="`${eq.alerts.length} open alert(s)`"
                  >
                    <ExclamationTriangleIcon class="w-3 h-3" /> {{ eq.alerts.length }}
                  </button>
                </div>
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex justify-center gap-1 items-center">
                  <AppIconButton label="View" variant="ghost" @click="viewEquipment(eq)">
                    <EyeIcon class="w-4 h-4"/>
                  </AppIconButton>
                  <AppIconButton v-if="eq.agent_device" label="Agent Specs" variant="ghost" @click="openSpecs(eq)">
                    <ChartBarIcon class="w-4 h-4"/>
                  </AppIconButton>
                  <AppIconButton v-if="eq.status === 'Pending Setup'" label="Merge Into…" variant="ghost" @click="openMergeModal(eq)">
                    <ArrowsRightLeftIcon class="w-4 h-4"/>
                  </AppIconButton>
                  <AppIconButton label="Edit" variant="ghost" @click="openModal('edit', eq)">
                    <PencilSquareIcon class="w-4 h-4"/>
                  </AppIconButton>
                  <AppIconButton label="Add PMS History" variant="ghost" @click="openAddPmsHistory(eq)">
                    <PlusIcon class="w-4 h-4"/>
                  </AppIconButton>
                  <AppIconButton label="PMS History" variant="ghost" @click="openPmsHistory(eq)">
                    <ClockIcon class="w-4 h-4" />
                  </AppIconButton>
                  <AppIconButton label="Delete" variant="danger" @click="destroyEquipment(eq)">
                    <TrashIcon class="w-4 h-4"/>
                  </AppIconButton>
                </div>
              </td>
            </tr>

            <template #mobileCard>
              <div v-for="eq in visibleEquipments" :key="eq.id" class="p-4 space-y-2">
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <p class="text-sm font-medium text-slate-800">{{ eq.description }}</p>
                    <p class="text-xs text-slate-400">#{{ eq.id }} &middot; {{ eq.serial_no }}</p>
                  </div>
                  <AppBadge :color="equipmentStatusBadgeColor(eq.status)">{{ eq.status ?? '—' }}</AppBadge>
                </div>
                <div class="text-xs text-slate-500">Owner: {{ props.users.find(u => u.id === eq.owner_id)?.name || 'N/A' }}</div>
                <div class="text-xs text-slate-500">Location: {{ eq.room?.name || '—' }}</div>
                <div class="flex items-center gap-2 text-xs">
                  <span v-if="eq.agent_device" class="inline-flex items-center gap-1 text-emerald-700" :title="`Last check-in: ${formatDate(eq.agent_device.last_checkin_at)}`">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Linked
                  </span>
                  <span v-else class="text-slate-400">—</span>
                  <button
                    v-if="eq.alerts?.length"
                    @click="openAlerts(eq)"
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-700 hover:bg-amber-100 transition-colors"
                    :title="`${eq.alerts.length} open alert(s)`"
                  >
                    <ExclamationTriangleIcon class="w-3 h-3" /> {{ eq.alerts.length }}
                  </button>
                </div>
                <div class="flex justify-end gap-1 pt-1">
                  <AppIconButton label="View" variant="ghost" @click="viewEquipment(eq)">
                    <EyeIcon class="w-4 h-4"/>
                  </AppIconButton>
                  <AppIconButton v-if="eq.agent_device" label="Agent Specs" variant="ghost" @click="openSpecs(eq)">
                    <ChartBarIcon class="w-4 h-4"/>
                  </AppIconButton>
                  <AppIconButton v-if="eq.status === 'Pending Setup'" label="Merge Into…" variant="ghost" @click="openMergeModal(eq)">
                    <ArrowsRightLeftIcon class="w-4 h-4"/>
                  </AppIconButton>
                  <AppIconButton label="Edit" variant="ghost" @click="openModal('edit', eq)">
                    <PencilSquareIcon class="w-4 h-4"/>
                  </AppIconButton>
                  <AppIconButton label="Add PMS History" variant="ghost" @click="openAddPmsHistory(eq)">
                    <PlusIcon class="w-4 h-4"/>
                  </AppIconButton>
                  <AppIconButton label="PMS History" variant="ghost" @click="openPmsHistory(eq)">
                    <ClockIcon class="w-4 h-4" />
                  </AppIconButton>
                  <AppIconButton label="Delete" variant="danger" @click="destroyEquipment(eq)">
                    <TrashIcon class="w-4 h-4"/>
                  </AppIconButton>
                </div>
              </div>
            </template>

            <template #empty>
              <EmptyState title="No equipment found" />
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
      </div>

      <!-- Equipment Modal -->
      <AppModal
        :show="showModal"
        :title="modalMode==='create' ? 'New Equipment Form' : modalMode==='edit' ? 'Edit Equipment' : 'View Equipment Details'"
        size="2xl"
        @close="closeModal"
      >
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
        </div>

        <!-- CREATE / EDIT FORM -->
        <form v-else @submit.prevent="submitEquipment" class="grid grid-cols-2 gap-4">
          <!-- Pending Setup notice: auto-enrolled device needs owner/room/status -->
          <div
            v-if="modalMode === 'edit' && selectedEquipment?.status === 'Pending Setup'"
            class="col-span-2 rounded-xl bg-orange-50 border border-orange-200 px-4 py-3 text-sm text-orange-700"
          >
            <p class="font-semibold mb-1">Auto-enrolled device — complete setup below</p>
            <p class="text-orange-600">Atlas Sentinel enrolled this device automatically. Assign an owner, room, and status to register it properly.</p>
            <div v-if="selectedEquipment?.agent_device" class="mt-2 grid grid-cols-2 gap-x-6 gap-y-0.5 text-xs text-orange-700 font-mono">
              <span v-if="selectedEquipment.agent_device.hostname"><strong>Hostname:</strong> {{ selectedEquipment.agent_device.hostname }}</span>
              <span v-if="selectedEquipment.agent_device.mac_address"><strong>MAC:</strong> {{ selectedEquipment.agent_device.mac_address }}</span>
              <span v-if="selectedEquipment.agent_device.os_version"><strong>OS:</strong> {{ selectedEquipment.agent_device.os_version }}</span>
              <span v-if="selectedEquipment.agent_device.agent_version"><strong>Agent:</strong> v{{ selectedEquipment.agent_device.agent_version }}</span>
            </div>
          </div>
          <!-- Equipment Category -->
          <div class="col-span-2">
            <AppSelect v-model="form.category" label="Equipment Category" required :show-blank="false" :error="errors.category">
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
            </AppSelect>
          </div>

          <!-- Owner (Dropdown) -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Owner <span class="text-red-500">*</span></label>
            <select
              v-model="form.owner_id"
              :class="[
                'rounded-lg border px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full',
                errors.owner_id ? 'border-red-400 bg-red-50/30' : 'border-slate-200 bg-white',
              ]"
              required
            >
              <option value="">Select Owner</option>
              <option v-for="user in props.users" :key="user.id" :value="user.id">
                {{ user.name }}
              </option>
            </select>
            <p v-if="errors.owner_id" class="mt-1 text-xs text-red-500">{{ errors.owner_id }}</p>
          </div>

          <!-- Property No -->
          <div>
            <AppInput v-model="form.property_no" type="text" label="Property No" :error="errors.property_no" />
          </div>

          <!-- Serial No -->
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Serial No <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <input
                v-model="form.serial_no"
                type="text"
                required
                placeholder="Type, or scan the device's barcode/QR label"
                :class="[
                  'w-full rounded-lg border pl-3 pr-11 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/25 transition-colors',
                  errors.serial_no ? 'border-red-400 bg-red-50/30' : 'border-slate-200 bg-white hover:border-slate-300',
                  serialJustScanned ? 'ring-2 ring-emerald-400/50 border-emerald-400' : '',
                ]"
              />
              <Transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0 scale-75"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-75"
              >
                <CheckCircleIcon v-if="serialJustScanned" class="absolute right-2.5 top-1/2 -translate-y-1/2 h-5 w-5 text-emerald-500" />
                <button
                  v-else
                  type="button"
                  @click="openScanner"
                  title="Scan barcode / QR"
                  class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 transition-colors"
                >
                  <CameraIcon class="h-5 w-5" />
                </button>
              </Transition>
            </div>
            <p v-if="errors.serial_no" class="mt-1 text-xs text-red-500">{{ errors.serial_no }}</p>
          </div>

          <!-- Device Description -->
          <div class="col-span-2">
            <AppInput v-model="form.description" type="text" label="Device Description / Model" required :error="errors.description" />
          </div>

          <!-- Date Acquired -->
          <div>
            <AppInput v-model="form.date_acquired" type="date" label="Date Acquired" :error="errors.date_acquired" />
          </div>

          <!-- Amount -->
          <div>
            <AppInput v-model="form.amount" type="number" step="0.01" label="Amount" :error="errors.amount" />
          </div>

          <!-- Equipment Status -->
          <div>
            <AppSelect v-model="form.status" label="Equipment Status" required :show-blank="false" :error="errors.status">
              <option value="">Select Status</option>
              <option value="Good Working">Good Working</option>
              <option value="For Repair">For Repair</option>
              <option value="Disposed">Disposed</option>
              <option value="Pending Setup">Pending Setup</option>
            </AppSelect>
          </div>

          <!-- Location / Room -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Location<span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.room_id"
              :class="[
                'rounded-lg border px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full',
                errors.room_id ? 'border-red-400 bg-red-50/30' : 'border-slate-200 bg-white',
              ]"
              required
            >
              <option value="">Select location</option>
              <option v-for="room in props.rooms" :key="room.id" :value="room.id">
                {{ room.name }}
              </option>
            </select>
            <p v-if="errors.room_id" class="mt-1 text-xs text-red-500">{{ errors.room_id }}</p>
          </div>


          <!-- Warranty Expires -->
          <div>
            <AppInput v-model="form.warranty_expires_at" type="date" label="Warranty Expires" :error="errors.warranty_expires_at" />
          </div>

          <!-- Warranty Provider -->
          <div>
            <AppInput v-model="form.warranty_provider" type="text" label="Warranty Provider" :error="errors.warranty_provider" />
          </div>

          <!-- Decommissioned -->
          <div>
            <AppInput v-model="form.decommissioned_at" type="date" label="Decommissioned On" :error="errors.decommissioned_at" />
          </div>

          <!-- Remarks -->
          <div class="col-span-2">
            <AppTextarea v-model="form.remarks" :rows="2" label="Remarks" :error="errors.remarks" />
          </div>
        </form>

        <template #footer>
          <template v-if="modalMode==='view' && selectedEquipment">
            <AppIconButton label="Print" variant="ghost" @click="printModal">
              <PrinterIcon class="w-5 h-5" />
            </AppIconButton>
          </template>
          <template v-else>
            <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
            <AppButton @click="submitEquipment">Save</AppButton>
          </template>
        </template>
      </AppModal>

      <!-- BARCODE SCANNER MODAL -->
      <BarcodeScannerModal
        :show="showScannerModal"
        @close="showScannerModal = false"
        @detected="handleSerialScanned"
      />

      <!-- PMS HISTORY MODAL -->
      <AppModal
        :show="showPmsModal"
        :title="`PMS History for ${selectedEquipment?.description} / ${selectedEquipment?.serial_no}`"
        size="3xl"
        @close="showPmsModal = false"
      >
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

        <template #footer>
          <AppButton @click="printPmsHistory">
            <PrinterIcon class="w-4 h-4" /> Print History
          </AppButton>
        </template>
      </AppModal>

      <!-- ADD PMS HISTORY MODAL -->
      <AppModal
        :show="showAddPmsModal"
        :title="`Add PMS History for ${selectedEquipment?.description} / ${selectedEquipment?.serial_no}`"
        @close="showAddPmsModal = false"
      >
        <form @submit.prevent="submitPmsHistory" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <AppInput
                v-model="pmsForm.pms_date"
                type="date"
                label="PMS Date"
                :error="pmsFormErrors.pms_date"
              />
            </div>

            <div>
              <AppSelect v-model="pmsForm.type" label="Type" :show-blank="false" :error="pmsFormErrors.type">
                <option value="PMS">PMS</option>
                <option value="Repair">Repair</option>
              </AppSelect>
            </div>
          </div>

          <div>
            <AppTextarea
              v-model="pmsForm.description"
              :rows="3"
              label="Description"
              placeholder="List of checked items or repair details"
              :error="pmsFormErrors.description"
            />
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
            <AppTextarea
              v-model="pmsForm.remarks"
              :rows="2"
              label="Remarks"
              placeholder="Additional remarks"
              :error="pmsFormErrors.remarks"
            />
          </div>
        </form>

        <template #footer>
          <AppButton variant="secondary" @click="showAddPmsModal = false" :disabled="isSubmittingPms">
            Cancel
          </AppButton>
          <AppButton :loading="isSubmittingPms" :disabled="isSubmittingPms" @click="submitPmsHistory">
            {{ isSubmittingPms ? 'Adding...' : 'Add PMS History' }}
          </AppButton>
        </template>
      </AppModal>

      <!-- MERGE MODAL -->
      <AppModal
        :show="showMergeModal"
        :title="`Merge ${selectedEquipment?.description} / ${selectedEquipment?.serial_no}`"
        size="sm"
        @close="closeMergeModal"
      >
        <div class="space-y-3">
          <p class="text-sm text-slate-600">
            This will move this record's device history into the equipment you pick below, then delete this duplicate record.
            If the original record isn't listed, search for it first so it's on this page, then reopen this dialog.
          </p>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Merge Into <span class="text-red-500">*</span></label>
            <select
              v-model="mergeTargetId"
              :class="[
                'rounded-lg border px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full',
                mergeErrors.target_equipment_id ? 'border-red-400 bg-red-50/30' : 'border-slate-200 bg-white',
              ]"
              required
            >
              <option value="">Select equipment record</option>
              <option
                v-for="eq in visibleEquipments.filter(e => e.id !== selectedEquipment?.id)"
                :key="eq.id"
                :value="eq.id"
              >
                #{{ eq.id }} — {{ eq.description }} ({{ eq.serial_no }})
              </option>
            </select>
            <p v-if="mergeErrors.target_equipment_id" class="mt-1 text-xs text-red-500">{{ mergeErrors.target_equipment_id }}</p>
          </div>
        </div>

        <template #footer>
          <AppButton variant="secondary" @click="closeMergeModal" :disabled="isSubmittingMerge">
            Cancel
          </AppButton>
          <AppButton variant="danger" :loading="isSubmittingMerge" :disabled="isSubmittingMerge" @click="submitMerge">
            {{ isSubmittingMerge ? 'Merging...' : 'Merge' }}
          </AppButton>
        </template>
      </AppModal>

      <!-- REPORT MODAL -->
      <AppModal :show="showReportModal" title="Generate Equipment Report" size="sm" @close="showReportModal = false">
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

        <template #footer>
          <AppButton variant="secondary" @click="showReportModal = false">Cancel</AppButton>
          <AppButton @click="generateReport(); showReportModal = false">Generate &amp; Print</AppButton>
        </template>
      </AppModal>

      <!-- ATLAS SENTINEL ENROLLMENT TOKEN MODAL -->
      <div
        v-if="showEnrollmentModal"
        class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Atlas Sentinel Enrollment Token</h2>
            <button
              @click="showEnrollmentModal = false"
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="px-6 py-3 border-b border-slate-100 flex gap-1">
            <button
              @click="switchEnrollmentMode('single')"
              :class="enrollmentMode === 'single' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-slate-50'"
              class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
            >
              Single Device
            </button>
            <button
              @click="switchEnrollmentMode('bulk')"
              :class="enrollmentMode === 'bulk' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-slate-50'"
              class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
            >
              Bulk (Lab / Office)
            </button>
          </div>

          <div class="px-6 py-5 space-y-3">
            <!-- Bulk generation form — shown until a token has been generated for this tab -->
            <div v-if="enrollmentMode === 'bulk' && !enrollmentToken" class="space-y-3">
              <p class="text-sm text-slate-600">
                Generates one token you can paste into the installer on every machine in this batch — no need to generate a fresh one per device.
              </p>
              <div>
                <label class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Label (optional)</label>
                <input
                  v-model="bulkLabel"
                  type="text"
                  placeholder="e.g. Comp Lab 3 — June 2026"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full mt-1"
                />
              </div>
              <div class="flex items-center gap-2">
                <input id="bulk-unlimited" v-model="bulkUnlimited" type="checkbox" class="rounded border-slate-300" />
                <label for="bulk-unlimited" class="text-sm text-slate-600">Unlimited devices (until it expires)</label>
              </div>
              <div v-if="!bulkUnlimited">
                <label class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Number of devices</label>
                <input
                  v-model.number="bulkQuantity"
                  type="number"
                  min="1"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full mt-1"
                />
              </div>
              <div>
                <label class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Expires in</label>
                <select v-model.number="bulkExpiryHours" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full mt-1">
                  <option :value="24">24 hours</option>
                  <option :value="72">3 days</option>
                  <option :value="168">7 days</option>
                </select>
              </div>
              <button
                @click="generateBulkToken"
                :disabled="isGeneratingToken"
                class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 w-full"
              >
                Generate Bulk Token
              </button>
            </div>

            <!-- Resulting token, either mode -->
            <template v-if="enrollmentToken">
              <p class="text-sm text-slate-600">
                <template v-if="enrollmentMaxUses === 1">
                  Paste this token into the Atlas Sentinel installer on the target desktop/laptop. It expires in 24 hours and can only be used once.
                </template>
                <template v-else>
                  Paste this same token into the Atlas Sentinel installer on every machine in this batch —
                  {{ enrollmentMaxUses ? `usable on up to ${enrollmentMaxUses} devices` : 'usable on unlimited devices' }}
                  until it expires.
                </template>
              </p>
              <div class="flex items-center gap-2">
                <input
                  :value="enrollmentToken"
                  readonly
                  class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 font-mono w-full"
                />
                <button
                  @click="copyEnrollmentToken"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm whitespace-nowrap"
                >
                  {{ tokenCopied ? 'Copied!' : 'Copy' }}
                </button>
              </div>
              <div class="flex items-center justify-between">
                <p class="text-xs text-slate-400">
                  Expires: {{ formatDate(enrollmentExpiresAt) }}
                </p>
                <button @click="enrollmentToken = null" class="text-xs text-indigo-600 hover:underline">
                  Generate another
                </button>
              </div>
            </template>

            <!-- Outstanding tokens -->
            <div class="pt-2 border-t border-slate-100">
              <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Outstanding Tokens</h3>
              <div v-if="loadingTokens" class="text-xs text-slate-400">Loading…</div>
              <div v-else-if="!outstandingTokens.length" class="text-xs text-slate-400">No active tokens.</div>
              <ul v-else class="space-y-2 max-h-40 overflow-y-auto">
                <li
                  v-for="t in outstandingTokens"
                  :key="t.id"
                  class="flex items-center justify-between gap-2 text-xs border border-slate-100 rounded-lg px-3 py-2 bg-slate-50/50"
                >
                  <div>
                    <div class="font-medium text-slate-700">{{ t.label || 'Untitled' }}</div>
                    <div class="text-slate-400">
                      {{ t.max_uses ? `${t.uses_count}/${t.max_uses} used` : `${t.uses_count} used (unlimited)` }}
                      · expires {{ formatDate(t.expires_at) }}
                    </div>
                  </div>
                  <button @click="revokeToken(t.token)" class="text-red-600 hover:underline shrink-0">Revoke</button>
                </li>
              </ul>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex justify-end">
            <button
              @click="showEnrollmentModal = false"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
            >
              Close
            </button>
          </div>
        </div>
      </div>

      <!-- ATLAS SENTINEL OPEN ALERTS MODAL -->
      <div
        v-if="showAlertsModal"
        class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-xl">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              Open Alerts for {{ selectedAlertsEquipment?.description }} / {{ selectedAlertsEquipment?.serial_no }}
            </h2>
            <button
              @click="showAlertsModal = false"
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="px-6 py-5">
            <div v-if="selectedAlerts.length === 0" class="py-16 text-center text-slate-400 text-sm">
              No open alerts.
            </div>

            <ul v-else class="space-y-3 max-h-96 overflow-y-auto">
              <li
                v-for="alert in selectedAlerts"
                :key="alert.id"
                class="border border-slate-100 p-4 rounded-lg bg-slate-50/50"
              >
                <div class="flex items-center gap-2">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                    :class="alert.severity === 'critical' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700'"
                  >{{ alert.severity }}</span>
                  <span class="text-xs text-slate-400">{{ formatDate(alert.created_at) }}</span>
                </div>
                <div class="text-sm text-slate-700 mt-2">{{ alert.issue }}</div>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- ATLAS SENTINEL SPECS MODAL -->
      <div
        v-if="showSpecsModal"
        class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              Agent Specs for {{ selectedSpecsEquipment?.description }} / {{ selectedSpecsEquipment?.serial_no }}
            </h2>
            <button
              @click="showSpecsModal = false"
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="px-6 py-5">
            <div v-if="!selectedSpecsEquipment?.agent_device?.health_snapshot" class="py-16 text-center text-slate-400 text-sm">
              No check-in data yet — the agent reports every 20 minutes, so this fills in shortly after install.
            </div>

            <div v-else class="space-y-4">
              <!-- Header band -->
              <div class="rounded-xl bg-gradient-to-r from-indigo-50 to-slate-50 border border-indigo-100 px-4 py-3">
                <div class="flex items-center justify-between">
                  <div class="text-sm font-semibold text-slate-800">{{ selectedSpecsEquipment.agent_device.hostname }}</div>
                  <div class="flex items-center gap-1.5">
                    <span
                      v-if="selectedSpecsEquipment.agent_device.risk_tier"
                      :class="riskTierClasses[selectedSpecsEquipment.agent_device.risk_tier]"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium capitalize"
                    >{{ selectedSpecsEquipment.agent_device.risk_tier }} risk</span>
                    <span
                      v-if="selectedSpecsEquipment.agent_device.network_location === 'on_campus'"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700"
                    >On Campus</span>
                    <span
                      v-else-if="selectedSpecsEquipment.agent_device.network_location === 'off_campus'"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700"
                      :title="`Since ${formatDateTime(selectedSpecsEquipment.agent_device.network_location_changed_at)}`"
                    >Off Campus</span>
                  </div>
                </div>
                <div class="text-xs text-slate-500 mt-0.5 flex items-center flex-wrap gap-x-1.5 gap-y-1">
                  {{ selectedSpecsEquipment.agent_device.os_version }} &middot;
                  <span v-if="specsVersionBadge" :class="specsVersionBadge.cls" class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium">
                    {{ specsVersionBadge.label }}
                  </span>
                  <span v-else>Agent v{{ selectedSpecsEquipment.agent_device.agent_version }}</span>
                </div>
                <div class="text-xs mt-1 flex items-center gap-1.5">
                  <span class="text-indigo-600">Last reported {{ formatDateTime(selectedSpecsEquipment.agent_device.health_snapshot.recorded_at) }}</span>
                  <span v-if="specsCheckinBadge" :class="specsCheckinBadge.cls" class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium">
                    {{ specsCheckinBadge.label }}
                  </span>
                </div>
              </div>

              <!-- CPU -->
              <div class="border border-slate-100 rounded-lg p-3 flex items-start gap-3">
                <CpuChipIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
                <div class="flex-1">
                  <div class="flex items-center justify-between">
                    <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">CPU</div>
                    <div v-if="selectedSpecsEquipment.agent_device.health_snapshot.payload?.cpu_temp_c" class="text-xs text-slate-500 flex items-center gap-1">
                      <FireIcon class="w-3.5 h-3.5 text-slate-400" />
                      {{ selectedSpecsEquipment.agent_device.health_snapshot.payload.cpu_temp_c }}&deg;C
                    </div>
                  </div>
                  <div class="mt-0.5 text-sm text-slate-700">{{ selectedSpecsEquipment.agent_device.health_snapshot.payload?.cpu ?? '—' }}</div>
                  <template v-if="selectedSpecsEquipment.agent_device.health_snapshot.payload?.cpu_usage_pct !== undefined && selectedSpecsEquipment.agent_device.health_snapshot.payload?.cpu_usage_pct !== null">
                    <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                      <div
                        class="h-full rounded-full transition-all"
                        :class="usageBarColor(selectedSpecsEquipment.agent_device.health_snapshot.payload.cpu_usage_pct, HIGH_CPU_USAGE_THRESHOLD)"
                        :style="{ width: selectedSpecsEquipment.agent_device.health_snapshot.payload.cpu_usage_pct + '%' }"
                      ></div>
                    </div>
                    <div class="mt-1 text-xs text-slate-500">{{ selectedSpecsEquipment.agent_device.health_snapshot.payload.cpu_usage_pct }}% usage</div>
                  </template>
                </div>
              </div>

              <!-- Network -->
              <div
                v-if="selectedSpecsEquipment.agent_device.health_snapshot.payload?.network"
                class="border border-slate-100 rounded-lg p-3 flex items-start gap-3"
              >
                <WifiIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
                <div class="flex-1">
                  <div class="flex items-center justify-between">
                    <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Network</div>
                    <span v-if="selectedSpecsEquipment.agent_device.health_snapshot.payload.network.link_up === false" class="text-[11px] font-medium text-red-600">Link Down</span>
                  </div>
                  <div class="mt-0.5 text-sm text-slate-700 space-x-3">
                    <span v-if="selectedSpecsEquipment.agent_device.health_snapshot.payload.network.gateway_latency_ms !== null">
                      {{ selectedSpecsEquipment.agent_device.health_snapshot.payload.network.gateway_latency_ms }}ms latency
                    </span>
                    <span v-if="selectedSpecsEquipment.agent_device.health_snapshot.payload.network.packet_loss_pct" :class="selectedSpecsEquipment.agent_device.health_snapshot.payload.network.packet_loss_pct > 20 ? 'text-red-600' : ''">
                      {{ selectedSpecsEquipment.agent_device.health_snapshot.payload.network.packet_loss_pct }}% loss
                    </span>
                    <span v-if="selectedSpecsEquipment.agent_device.health_snapshot.payload.network.link_speed_mbps">
                      {{ selectedSpecsEquipment.agent_device.health_snapshot.payload.network.link_speed_mbps }} Mbps
                    </span>
                  </div>
                  <div class="mt-1 text-xs text-slate-500">
                    <span v-if="selectedSpecsEquipment.agent_device.health_snapshot.payload?.wifi_ssid">
                      Wi-Fi: {{ selectedSpecsEquipment.agent_device.health_snapshot.payload.wifi_ssid }}
                      <span v-if="selectedSpecsEquipment.agent_device.health_snapshot.payload?.wifi_bssid">&middot; AP {{ selectedSpecsEquipment.agent_device.health_snapshot.payload.wifi_bssid }}</span>
                    </span>
                    <span v-else-if="selectedSpecsEquipment.agent_device.health_snapshot.payload.network.link_up">Wired connection</span>
                  </div>
                  <div v-if="selectedSpecsEquipment.agent_device.health_snapshot.payload.network.local_ip" class="text-xs text-slate-500">
                    Local IP: {{ selectedSpecsEquipment.agent_device.health_snapshot.payload.network.local_ip }}
                  </div>
                </div>
              </div>

              <!-- Watched services -->
              <div
                v-if="selectedSpecsEquipment.agent_device.health_snapshot.payload?.services?.length"
                class="border border-slate-100 rounded-lg p-3 flex items-start gap-3"
              >
                <ServerStackIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
                <div class="flex-1">
                  <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Services</div>
                  <div class="flex flex-wrap gap-1.5 items-center">
                    <span
                      v-for="svc in selectedSpecsEquipment.agent_device.health_snapshot.payload.services"
                      :key="svc.name"
                      class="inline-flex items-center gap-1"
                    >
                      <span
                        :class="svc.status === 'Running' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium"
                      >{{ svc.name }}: {{ svc.status }}</span>
                      <button
                        v-if="svc.status !== 'Running' && svc.status !== 'NotInstalled'"
                        @click="runFix(selectedSpecsEquipment, 'service_restart', svc.name)"
                        :disabled="isFixPending(selectedSpecsEquipment, 'service_restart', svc.name)"
                        class="text-[11px] font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-50 disabled:cursor-not-allowed"
                      >{{ isFixPending(selectedSpecsEquipment, 'service_restart', svc.name) ? 'Queued…' : 'Restart' }}</button>
                    </span>
                  </div>
                </div>
              </div>

              <!-- Battery -->
              <div
                v-if="selectedSpecsEquipment.agent_device.hardware_inventory?.battery"
                class="border border-slate-100 rounded-lg p-3 flex items-start gap-3"
              >
                <BoltIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
                <div class="flex-1">
                  <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Battery</div>
                  <template v-if="batteryWearPct(selectedSpecsEquipment.agent_device.hardware_inventory.battery) !== null">
                    <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                      <div
                        class="h-full rounded-full transition-all"
                        :class="usageBarColor(batteryWearPct(selectedSpecsEquipment.agent_device.hardware_inventory.battery), 20)"
                        :style="{ width: batteryWearPct(selectedSpecsEquipment.agent_device.hardware_inventory.battery) + '%' }"
                      ></div>
                    </div>
                    <div class="mt-1 text-xs text-slate-500">{{ batteryWearPct(selectedSpecsEquipment.agent_device.hardware_inventory.battery) }}% worn from design capacity</div>
                  </template>
                  <div v-else class="mt-0.5 text-sm text-slate-400">—</div>
                </div>
              </div>

              <!-- Security posture -->
              <div
                v-if="selectedSpecsEquipment.agent_device.security_status"
                class="border border-slate-100 rounded-lg p-3 flex items-start gap-3"
              >
                <ShieldCheckIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
                <div class="flex-1">
                  <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Security</div>
                  <div class="grid grid-cols-2 gap-1 text-xs">
                    <div :class="securityRowClass(selectedSpecsEquipment.agent_device.security_status.antivirus_enabled)">
                      Antivirus: {{ selectedSpecsEquipment.agent_device.security_status.antivirus_enabled === false ? 'Disabled' : selectedSpecsEquipment.agent_device.security_status.antivirus_enabled === true ? 'Enabled' : '—' }}
                    </div>
                    <div :class="securityRowClass(selectedSpecsEquipment.agent_device.security_status.firewall_enabled)">
                      Firewall: {{ selectedSpecsEquipment.agent_device.security_status.firewall_enabled === false ? 'Disabled' : selectedSpecsEquipment.agent_device.security_status.firewall_enabled === true ? 'Enabled' : '—' }}
                    </div>
                    <div class="text-slate-500">
                      Pending updates: {{ selectedSpecsEquipment.agent_device.security_status.pending_updates_count ?? '—' }}
                    </div>
                    <div :class="selectedSpecsEquipment.agent_device.security_status.unauthorized_software_count > 0 ? 'text-amber-600' : 'text-slate-500'">
                      Unauthorized software: {{ selectedSpecsEquipment.agent_device.security_status.unauthorized_software_count ?? 0 }}
                    </div>
                  </div>
                  <div v-if="selectedSpecsEquipment.agent_device.security_status.reboot_required" class="mt-1 text-xs text-amber-600">Reboot required</div>
                </div>
              </div>

              <!-- Threat Containment -->
              <SecurityPanel :equipment-id="selectedSpecsEquipment.id" />

              <!-- RAM -->
              <div
                v-if="selectedSpecsEquipment.agent_device.health_snapshot.payload?.ram_total_mb"
                class="border border-slate-100 rounded-lg p-3 flex items-start gap-3"
              >
                <Square3Stack3DIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
                <div class="flex-1">
                  <div class="flex items-center justify-between">
                    <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">RAM</div>
                    <div class="text-xs text-slate-500">
                      {{ Math.round(selectedSpecsEquipment.agent_device.health_snapshot.payload.ram_free_mb / 1024) }} GB free of
                      {{ Math.round(selectedSpecsEquipment.agent_device.health_snapshot.payload.ram_total_mb / 1024) }} GB
                    </div>
                  </div>
                  <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div
                      class="h-full rounded-full transition-all"
                      :class="freeBarColor(percentFree(selectedSpecsEquipment.agent_device.health_snapshot.payload.ram_free_mb, selectedSpecsEquipment.agent_device.health_snapshot.payload.ram_total_mb), RAM_LOW_THRESHOLD)"
                      :style="{ width: percentUsed(selectedSpecsEquipment.agent_device.health_snapshot.payload.ram_free_mb, selectedSpecsEquipment.agent_device.health_snapshot.payload.ram_total_mb) + '%' }"
                    ></div>
                  </div>
                </div>
              </div>

              <!-- Disks -->
              <div class="border border-slate-100 rounded-lg p-3 flex items-start gap-3">
                <CircleStackIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
                <div class="flex-1">
                  <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Disks</div>
                  <div v-if="!selectedSpecsEquipment.agent_device.health_snapshot.payload?.disks?.length" class="text-sm text-slate-400">—</div>
                  <div v-else class="space-y-2.5">
                    <div v-for="disk in selectedSpecsEquipment.agent_device.health_snapshot.payload.disks" :key="disk.drive">
                      <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-700">{{ disk.drive }}</span>
                        <span class="text-slate-500 text-xs flex items-center gap-1">
                          {{ disk.free_gb }} GB free of {{ disk.total_gb }} GB
                          <ExclamationTriangleIcon
                            v-if="percentFree(disk.free_gb, disk.total_gb) < DISK_LOW_THRESHOLD"
                            class="w-3.5 h-3.5 text-red-500"
                            title="Low disk space"
                          />
                        </span>
                      </div>
                      <div class="mt-1 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                        <div
                          class="h-full rounded-full transition-all"
                          :class="freeBarColor(percentFree(disk.free_gb, disk.total_gb), DISK_LOW_THRESHOLD)"
                          :style="{ width: percentUsed(disk.free_gb, disk.total_gb) + '%' }"
                        ></div>
                      </div>
                    </div>
                  </div>

                  <!-- Physical disk SMART status — separate from the volume
                       list above since logical drive letters (C:, D:) don't
                       map 1:1 to physical disk device paths. -->
                  <div v-if="selectedSpecsEquipment.agent_device.hardware_inventory?.disks?.length" class="mt-3 pt-2 border-t border-slate-100 space-y-1">
                    <div
                      v-for="pdisk in selectedSpecsEquipment.agent_device.hardware_inventory.disks"
                      :key="pdisk.drive"
                      class="flex items-center justify-between text-xs"
                    >
                      <span class="text-slate-500">{{ pdisk.model || pdisk.drive }}</span>
                      <span
                        :class="pdisk.smart_status === 'failing' ? 'bg-red-50 text-red-700' : pdisk.smart_status === 'ok' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                        class="px-1.5 py-0.5 rounded-full font-medium"
                      >SMART: {{ pdisk.smart_status ?? 'unknown' }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Printers -->
              <div
                v-if="selectedSpecsEquipment.agent_device.health_snapshot.payload?.printers?.length"
                class="border border-slate-100 rounded-lg p-3 flex items-start gap-3"
              >
                <PrinterIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
                <div class="flex-1">
                  <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Printers</div>
                  <div class="space-y-2">
                    <div
                      v-for="printer in selectedSpecsEquipment.agent_device.health_snapshot.payload.printers"
                      :key="printer.name"
                      class="flex items-center justify-between text-sm"
                    >
                      <span class="flex items-center gap-1.5">
                        <span class="text-slate-700">{{ printer.name }}</span>
                        <CheckBadgeIcon v-if="printer.is_default" class="w-4 h-4 text-indigo-500" title="Default printer" />
                      </span>
                      <span class="flex items-center gap-2 text-xs">
                        <span v-if="printer.detected_error_state" class="px-1.5 py-0.5 rounded-full font-medium bg-amber-50 text-amber-700">{{ printer.detected_error_state }}</span>
                        <span v-if="printer.pending_jobs > 0" class="text-slate-500">{{ printer.pending_jobs }} job(s) pending</span>
                        <button
                          v-if="printer.detected_error_state || printer.pending_jobs > 0"
                          @click="runFix(selectedSpecsEquipment, 'print_spooler_recovery')"
                          :disabled="isFixPending(selectedSpecsEquipment, 'print_spooler_recovery')"
                          class="font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-50 disabled:cursor-not-allowed"
                        >{{ isFixPending(selectedSpecsEquipment, 'print_spooler_recovery') ? 'Queued…' : 'Clear queue' }}</button>
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Quick Fixes — admin-triggered, bypasses the auto_execute
                   rule gate; runs on the device's next check-in (~20 min). -->
              <div class="border border-slate-100 rounded-lg p-3 flex items-start gap-3">
                <WrenchScrewdriverIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
                <div class="flex-1">
                  <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Quick Fixes</div>
                  <div class="grid grid-cols-2 gap-2">
                    <div v-for="qf in QUICK_FIX_ACTIONS" :key="qf.action">
                      <button
                        @click="runFix(selectedSpecsEquipment, qf.action)"
                        :disabled="isFixPending(selectedSpecsEquipment, qf.action)"
                        class="w-full text-left rounded-lg border border-slate-200 hover:bg-slate-50 px-3 py-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                      >
                        <div class="text-xs font-medium text-slate-700">
                          {{ isFixPending(selectedSpecsEquipment, qf.action) ? 'Queued…' : qf.label }}
                        </div>
                        <div class="text-[11px] text-slate-400">{{ qf.description }}</div>
                        <div v-if="lastFixResult(selectedSpecsEquipment, qf.action)" class="text-[11px] mt-0.5"
                          :class="lastFixResult(selectedSpecsEquipment, qf.action).status === 'completed' ? 'text-emerald-600' : 'text-red-600'"
                        >Last run: {{ lastFixResult(selectedSpecsEquipment, qf.action).status === 'completed' ? 'succeeded' : 'failed' }}, {{ formatDateTime(lastFixResult(selectedSpecsEquipment, qf.action).completed_at) }}</div>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Installed Software — read-only list from the daily
                   inventory check-in. Uninstall is only offered for entries
                   with a documented silent removal path; everything else
                   has no reliable unattended uninstall (the agent has no
                   interactive desktop session to click through a GUI
                   uninstaller). -->
              <div
                v-if="selectedSpecsEquipment.agent_device.software_inventory?.installed_software?.length"
                class="border border-slate-100 rounded-lg p-3 flex items-start gap-3"
              >
                <ArchiveBoxIcon class="w-5 h-5 text-slate-400 shrink-0 mt-0.5" />
                <div class="flex-1">
                  <div class="flex items-center justify-between mb-2">
                    <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                      Installed Software ({{ selectedSpecsEquipment.agent_device.software_inventory.installed_software.length }})
                    </div>
                  </div>
                  <div class="relative mb-2">
                    <MagnifyingGlassIcon class="w-3.5 h-3.5 text-slate-400 absolute left-2 top-1/2 -translate-y-1/2" />
                    <input
                      v-model="softwareSearch"
                      type="text"
                      placeholder="Search software or publisher…"
                      class="w-full text-xs pl-7 pr-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div class="max-h-56 overflow-y-auto space-y-1">
                    <div
                      v-for="sw in filteredSoftware"
                      :key="sw.uninstall_key ?? sw.name"
                      class="flex items-center justify-between gap-2 text-xs py-1 border-b border-slate-50 last:border-b-0"
                    >
                      <div class="flex-1 min-w-0">
                        <div class="text-slate-700 truncate">{{ sw.name }}</div>
                        <div class="text-[11px] text-slate-400 truncate">{{ sw.publisher || '—' }} &middot; {{ sw.version || '—' }}</div>
                      </div>
                      <button
                        v-if="isSilentlyUninstallable(sw)"
                        @click="confirmUninstall(selectedSpecsEquipment, sw)"
                        :disabled="isFixPending(selectedSpecsEquipment, 'software_uninstall', sw.uninstall_key)"
                        class="shrink-0 text-[11px] font-medium text-red-600 hover:text-red-800 disabled:opacity-50 disabled:cursor-not-allowed"
                      >{{ isFixPending(selectedSpecsEquipment, 'software_uninstall', sw.uninstall_key) ? 'Queued…' : 'Uninstall' }}</button>
                      <span v-else class="shrink-0 text-[11px] text-slate-300" title="No silent uninstall method available">—</span>
                    </div>
                    <div v-if="!filteredSoftware.length" class="text-center text-slate-400 py-2">No matches.</div>
                  </div>
                </div>
              </div>
            </div>
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
