<template>
  <Head title="Faculty Loading" />
  <AdminLayout title="Faculty Loading">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Faculty Loading</h1>
          <p class="text-sm text-slate-500 mt-0.5">Load summary per faculty for the selected term</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link :href="route('faculty-loading.assignments.index')"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-lg font-medium transition-colors">
            <ClipboardDocumentListIcon class="h-4 w-4" /> Assignments
          </Link>
          <Link :href="route('faculty-loading.schedules.index')"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm">
            <CalendarIcon class="h-4 w-4" /> Schedules
          </Link>
        </div>
      </div>

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
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>

      <!-- Term filter -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
          <label class="text-sm font-medium text-slate-600 shrink-0">Academic Term</label>
          <select v-model="selectedTermId" @change="applyFilter"
            class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option v-for="t in terms" :key="t.id" :value="t.id">
              {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
            </option>
          </select>
          <span v-if="currentTerm" class="text-xs text-slate-400">Current: {{ currentTerm.label }}</span>
        </div>
      </div>

      <!-- Load stats -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-slate-800">{{ loads.length }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Total Faculty</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-emerald-600">{{ fullLoadCount }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Full Load</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-amber-600">{{ underloadCount }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Underload</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-red-600">{{ overloadCount }}</p>
          <p class="text-xs text-slate-500 mt-0.5">Overload</p>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="loads.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <UserGroupIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No faculty load records for this term</p>
        <p class="text-xs text-slate-400 mt-1">Load records are created automatically when schedules are assigned.</p>
      </div>

      <!-- Load table -->
      <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
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
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
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
                      :class="load.load_status === 'overload' ? 'bg-red-400'
                             : load.load_status === 'underload' ? 'bg-amber-300' : 'bg-emerald-400'"
                      :style="{ width: Math.min(100, (parseFloat(load.total_units) / 24) * 100) + '%' }" />
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-center">
                <span :class="statusBadge(load.load_status)"
                  class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold">
                  {{ statusLabel(load.load_status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <span v-if="load.load_status === 'overload'">
                  <span v-if="load.overload_approved"
                    class="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
                    <CheckCircleIcon class="h-3.5 w-3.5" /> Approved
                  </span>
                  <button v-else-if="canApprove" @click="openApproval(load)"
                    class="text-xs text-amber-600 hover:text-amber-800 font-medium underline">
                    Pending ({{ load.overload_units }} u)
                  </button>
                  <span v-else class="text-xs text-amber-500 font-medium">Pending</span>
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
            </tr>
          </tbody>
        </table>
      </div>

    </div>

    <!-- Overload Approval Modal -->
    <div v-if="approvalLoad" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">Overload Approval</h2>
        <p class="text-sm text-slate-600">
          <strong>{{ approvalLoad.faculty?.name }}</strong> has
          <strong>{{ approvalLoad.overload_units }} overload unit(s)</strong>.
          Approve or reject below.
        </p>
        <textarea v-model="approvalForm.approval_remarks" rows="3"
          placeholder="Remarks (optional)"
          class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none" />
        <div class="flex justify-end gap-3 pt-1">
          <button @click="approvalLoad = null" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="submitApproval(false)"
            class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium">Reject</button>
          <button @click="submitApproval(true)"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">Approve</button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  BanknotesIcon, BeakerIcon, CalendarIcon, ChartBarIcon, CheckCircleIcon,
  ClipboardDocumentListIcon, CpuChipIcon, LockClosedIcon, LockOpenIcon,
  RectangleGroupIcon, ScaleIcon, UserGroupIcon,
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

function statusBadge(status) {
  return {
    underload: 'bg-amber-50 text-amber-700',
    full_load: 'bg-emerald-50 text-emerald-700',
    overload:  'bg-red-50 text-red-700',
  }[status] ?? 'bg-slate-50 text-slate-600'
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
</script>
