<template>
  <Head title="Faculty Loading" />
  <AdminLayout title="Faculty Loading">
    <div class="space-y-5">

      <AppPageHeader title="Faculty Loading" subtitle="Load summary per faculty for the selected term">
        <template #actions>
          <AppButton variant="secondary" @click="printBatch">
            <PrinterIcon class="h-4 w-4" /> Print All
          </AppButton>
          <AppButton as="link" variant="secondary" :href="route('faculty-loading.assignments.index')">
            <ClipboardDocumentListIcon class="h-4 w-4" /> Assignments
          </AppButton>
          <AppButton as="link" :href="route('faculty-loading.schedules.index')">
            <CalendarIcon class="h-4 w-4" /> Schedules
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Quick nav pills -->
      <div class="flex flex-wrap gap-2">
        <Link :href="route('faculty-loading.committee-assignments.index')"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-orange-50 text-orange-700 hover:bg-orange-100 rounded-full font-medium transition-colors">
          <UserGroupIcon class="h-3.5 w-3.5" /> Committee Assignments
        </Link>
        <Link :href="route('faculty-loading.research-advisories.index')"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-violet-50 text-violet-700 hover:bg-violet-100 rounded-full font-medium transition-colors">
          <BeakerIcon class="h-3.5 w-3.5" /> Research Advisories
        </Link>
        <Link :href="route('faculty-loading.sections.index')"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-teal-50 text-teal-700 hover:bg-teal-100 rounded-full font-medium transition-colors">
          <RectangleGroupIcon class="h-3.5 w-3.5" /> Sections
        </Link>
        <Link :href="route('faculty-loading.overload-computations.index')"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-full font-medium transition-colors">
          <BanknotesIcon class="h-3.5 w-3.5" /> Overload Pay
        </Link>
        <Link :href="route('faculty-loading.reports.loads')"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-sky-50 text-sky-700 hover:bg-sky-100 rounded-full font-medium transition-colors">
          <ChartBarIcon class="h-3.5 w-3.5" /> Reports
        </Link>
        <Link :href="route('faculty-loading.ai-dashboard')"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-full font-medium transition-colors">
          <CpuChipIcon class="h-3.5 w-3.5" /> AI Optimizer
        </Link>
        <Link :href="route('faculty-loading.load-balance.index')"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-full font-medium transition-colors">
          <ScaleIcon class="h-3.5 w-3.5" /> Load Balance
        </Link>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>

      <!-- Term filter -->
      <AppFilterBar>
        <div class="w-64">
          <AppSelect v-model="selectedTermId" label="Academic Term" :show-blank="false" @change="applyFilter">
            <option v-for="t in terms" :key="t.id" :value="t.id">
              {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
            </option>
          </AppSelect>
        </div>
        <template #actions>
          <span v-if="currentTerm" class="text-xs text-slate-400">Current: {{ currentTerm.label }}</span>
        </template>
      </AppFilterBar>

      <!-- Load stats -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-slate-800">{{ loads.length }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Total Faculty</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-success-600">{{ fullLoadCount }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Full Load</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-warning-600">{{ underloadCount }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Underload</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-danger-600">{{ overloadCount }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Overload</p>
        </div>
      </div>

      <!-- Load table -->
      <AppTable :is-empty="loads.length === 0" :skeleton-cols="canApprove ? 11 : 10">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Faculty</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Teaching</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Research</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Admin</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Co-curr</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Committee</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Overload</th>
            <th v-if="canApprove" class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Lock</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Print</th>
          </tr>
        </template>

        <tr v-for="load in loads" :key="load.id" :class="load.is_locked ? 'bg-slate-50/60' : ''" class="hover:bg-slate-50/50">
          <td class="px-4 py-3">
            <p class="font-medium text-slate-800">{{ load.faculty?.name }}</p>
            <p v-if="load.faculty?.position" class="text-xs text-slate-400">{{ load.faculty.position }}</p>
            <span v-if="load.is_locked" class="inline-flex items-center gap-1 text-xs text-slate-400 mt-0.5">
              <LockClosedIcon class="h-3 w-3" /> Locked
            </span>
          </td>
          <td class="px-4 py-3 text-center text-slate-700">{{ load.teaching_units }}</td>
          <td class="px-4 py-3 text-center text-slate-700">{{ load.research_units }}</td>
          <td class="px-4 py-3 text-center text-slate-700">{{ load.admin_units }}</td>
          <td class="px-4 py-3 text-center text-slate-700">{{ load.cocurricular_units }}</td>
          <td class="px-4 py-3 text-center text-slate-700">{{ load.committee_units }}</td>
          <td class="px-4 py-3 text-center font-semibold text-slate-800">
            <div class="flex flex-col items-center gap-1">
              <span>{{ load.total_units }}</span>
              <!-- Mini load bar -->
              <div class="w-16 bg-slate-100 rounded-full h-1.5 relative overflow-hidden">
                <div class="absolute inset-y-0 w-px bg-slate-400 z-10"
                  :style="{ left: ((18 / 24) * 100) + '%' }" />
                <div class="h-full rounded-full transition-all"
                  :class="load.load_status === 'overload' ? 'bg-danger-500'
                         : load.load_status === 'underload' ? 'bg-warning-500' : 'bg-success-500'"
                  :style="{ width: Math.min(100, (parseFloat(load.total_units) / 24) * 100) + '%' }" />
              </div>
            </div>
          </td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusColor(load.load_status)">{{ statusLabel(load.load_status) }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <span v-if="load.load_status === 'overload'">
              <span v-if="load.overload_approved"
                class="inline-flex items-center gap-1 text-xs text-success-600 font-medium">
                <CheckCircleIcon class="h-3.5 w-3.5" /> Approved
              </span>
              <button v-else-if="canApprove" @click="openApproval(load)"
                class="text-xs text-warning-600 hover:text-warning-700 font-medium underline">
                Pending ({{ load.overload_units }} u)
              </button>
              <span v-else class="text-xs text-warning-500 font-medium">Pending</span>
            </span>
            <span v-else class="text-xs text-slate-300">—</span>
          </td>
          <td v-if="canApprove" class="px-4 py-3 text-center">
            <button v-if="load.is_locked"
              @click="toggleLock(load, false)"
              class="text-xs text-slate-400 hover:text-indigo-600 font-medium flex items-center gap-1 mx-auto">
              <LockClosedIcon class="h-3.5 w-3.5" /> Unlock
            </button>
            <button v-else
              @click="toggleLock(load, true)"
              class="text-xs text-slate-400 hover:text-slate-700 font-medium flex items-center gap-1 mx-auto">
              <LockOpenIcon class="h-3.5 w-3.5" /> Lock
            </button>
          </td>
          <td class="px-4 py-3 text-center">
            <AppIconButton label="Print faculty load" @click="printLoad(load)"><PrinterIcon class="h-4 w-4" /></AppIconButton>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="load in loads" :key="load.id" class="p-4 space-y-2" :class="load.is_locked ? 'bg-slate-50/60' : ''">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ load.faculty?.name }}</p>
                <p v-if="load.faculty?.position" class="text-xs text-slate-400">{{ load.faculty.position }}</p>
                <span v-if="load.is_locked" class="inline-flex items-center gap-1 text-xs text-slate-400 mt-0.5">
                  <LockClosedIcon class="h-3 w-3" /> Locked
                </span>
              </div>
              <AppBadge :color="statusColor(load.load_status)">{{ statusLabel(load.load_status) }}</AppBadge>
            </div>
            <div class="grid grid-cols-3 gap-x-3 gap-y-1 text-xs text-slate-500">
              <span>Teaching {{ load.teaching_units }}</span>
              <span>Research {{ load.research_units }}</span>
              <span>Admin {{ load.admin_units }}</span>
              <span>Co-curr {{ load.cocurricular_units }}</span>
              <span>Committee {{ load.committee_units }}</span>
              <span class="font-semibold text-slate-800">Total {{ load.total_units }}</span>
            </div>
            <div class="flex items-center justify-between pt-1">
              <span v-if="load.load_status === 'overload'">
                <span v-if="load.overload_approved" class="inline-flex items-center gap-1 text-xs text-success-600 font-medium">
                  <CheckCircleIcon class="h-3.5 w-3.5" /> Approved
                </span>
                <button v-else-if="canApprove" @click="openApproval(load)" class="text-xs text-warning-600 hover:text-warning-700 font-medium underline">
                  Pending ({{ load.overload_units }} u)
                </button>
                <span v-else class="text-xs text-warning-500 font-medium">Pending</span>
              </span>
              <span v-else class="text-xs text-slate-300">—</span>
              <div class="flex items-center gap-2">
                <button v-if="canApprove && load.is_locked" @click="toggleLock(load, false)" class="text-xs text-slate-400 hover:text-indigo-600 font-medium flex items-center gap-1">
                  <LockClosedIcon class="h-3.5 w-3.5" /> Unlock
                </button>
                <button v-else-if="canApprove" @click="toggleLock(load, true)" class="text-xs text-slate-400 hover:text-slate-700 font-medium flex items-center gap-1">
                  <LockOpenIcon class="h-3.5 w-3.5" /> Lock
                </button>
                <AppIconButton label="Print faculty load" @click="printLoad(load)"><PrinterIcon class="h-4 w-4" /></AppIconButton>
              </div>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No faculty load records for this term" subtitle="Load records are created automatically when schedules are assigned." :icon="UserGroupIcon" />
        </template>
      </AppTable>

    </div>

    <!-- Overload Approval Modal -->
    <AppModal :show="!!approvalLoad" title="Overload Approval" size="sm" @close="approvalLoad = null">
      <p class="text-sm text-slate-600 mb-4">
        <strong>{{ approvalLoad?.faculty?.name }}</strong> has
        <strong>{{ approvalLoad?.overload_units }} overload unit(s)</strong>.
        Approve or reject below.
      </p>
      <AppTextarea v-model="approvalForm.approval_remarks" :rows="3" placeholder="Remarks (optional)" />

      <template #footer>
        <AppButton variant="secondary" @click="approvalLoad = null">Cancel</AppButton>
        <AppButton variant="danger" @click="submitApproval(false)">Reject</AppButton>
        <AppButton variant="success" @click="submitApproval(true)">Approve</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  BanknotesIcon, BeakerIcon, CalendarIcon, ChartBarIcon, CheckCircleIcon,
  ClipboardDocumentListIcon, CpuChipIcon, LockClosedIcon, LockOpenIcon,
  PrinterIcon, RectangleGroupIcon, ScaleIcon, UserGroupIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  loads:       { type: Array,  default: () => [] },
  terms:       { type: Array,  default: () => [] },
  currentTerm: { type: Object, default: null },
  filters:     { type: Object, default: () => ({}) },
})

const canApprove = usePage().props.auth?.user?.permissions?.includes('faculty_loading.approve') ?? false

const selectedTermId = ref(props.filters.term_id ?? props.currentTerm?.id)

function applyFilter() {
  router.get(route('faculty-loading.index'), { term_id: selectedTermId.value }, { preserveState: true })
}

const fullLoadCount  = computed(() => props.loads.filter(l => l.load_status === 'full_load').length)
const underloadCount = computed(() => props.loads.filter(l => l.load_status === 'underload').length)
const overloadCount  = computed(() => props.loads.filter(l => l.load_status === 'overload').length)

function statusColor(status) {
  return {
    underload: 'amber',
    full_load: 'green',
    overload:  'red',
  }[status] ?? 'slate'
}

function statusLabel(status) {
  return { underload: 'Underload', full_load: 'Full Load', overload: 'Overload' }[status] ?? status
}

// Overload approval
const approvalLoad = ref(null)
const approvalForm = useForm({ approved: true, approval_remarks: '' })

function openApproval(load) {
  approvalLoad.value = load
  approvalForm.reset()
}

function submitApproval(approved) {
  approvalForm.approved = approved
  approvalForm.post(route('faculty-loading.approve-overload', approvalLoad.value.id), {
    onSuccess: () => { approvalLoad.value = null },
  })
}

function toggleLock(load, lock) {
  const routeName = lock ? 'faculty-loading.lock' : 'faculty-loading.unlock'
  router.post(route(routeName, load.id))
}

function printLoad(load) {
  window.open(route('faculty-loading.print', load.id), '_blank')
}

function printBatch() {
  window.open(route('faculty-loading.print-batch', { term_id: selectedTermId.value }), '_blank')
}
</script>
