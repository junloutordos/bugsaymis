<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import {
  BuildingOfficeIcon,
  UserGroupIcon,
  UserCircleIcon,
  ClockIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  ChevronRightIcon,
} from '@heroicons/vue/24/outline'

// ── Props ──────────────────────────────────────────────────────────────────────
const props = defineProps({
  unit:       { type: Object, required: true },
  breadcrumb: { type: String, default: '' },
  employees:  { type: Array,  default: () => [] },
  can:        { type: Object, default: () => ({}) },
})

// ── State ──────────────────────────────────────────────────────────────────────
const activeTab  = ref('overview')    // overview | assignments | heads
const flash      = ref({ success: null, error: null })
const loading    = ref(false)
const errors     = ref({})

// ── Assignment modal ───────────────────────────────────────────────────────────
const showAssign    = ref(false)
const allEmployees  = ref([])
const assignForm    = ref({
  user_id:                props.unit.id,
  organizational_unit_id: props.unit.id,
  is_primary:             false,
  assignment_type:        'organic',
  appointment_type:       'permanent',
  position_title:         '',
  item_number:            '',
  effective_date:         '',
  end_date:               '',
  remarks:                '',
})

async function openAssignModal() {
  if (!allEmployees.value.length) {
    const res = await axios.get(route('users.select')).catch(() => ({ data: [] }))
    allEmployees.value = res.data ?? []
  }
  assignForm.value = {
    user_id:                null,
    organizational_unit_id: props.unit.id,
    is_primary:             false,
    assignment_type:        'organic',
    appointment_type:       'permanent',
    position_title:         '',
    item_number:            '',
    effective_date:         new Date().toISOString().slice(0, 10),
    end_date:               '',
    remarks:                '',
  }
  errors.value = {}
  showAssign.value = true
}

async function submitAssign() {
  loading.value = true
  errors.value  = {}
  try {
    await axios.post(route('hr.org.assignments.store'), assignForm.value)
    showAssign.value = false
    reloadUnit()
    setFlash('success', 'Employee assigned to this unit.')
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors ?? {}
    } else {
      setFlash('error', e.response?.data?.message ?? 'An error occurred.')
    }
  } finally {
    loading.value = false
  }
}

// ── Head designation modal ─────────────────────────────────────────────────────
const showHead  = ref(false)
const headForm  = ref({
  organizational_unit_id: props.unit.id,
  user_id:                null,
  designation_title:      '',
  is_acting:              false,
  is_current:             true,
  effective_date:         '',
  designation_order:      '',
  remarks:                '',
})

async function openHeadModal() {
  if (!allEmployees.value.length) {
    const res = await axios.get(route('users.select')).catch(() => ({ data: [] }))
    allEmployees.value = res.data ?? []
  }
  headForm.value = {
    organizational_unit_id: props.unit.id,
    user_id:                null,
    designation_title:      '',
    is_acting:              false,
    is_current:             true,
    effective_date:         new Date().toISOString().slice(0, 10),
    designation_order:      '',
    remarks:                '',
  }
  errors.value  = {}
  showHead.value = true
}

async function submitHead() {
  loading.value = true
  errors.value  = {}
  try {
    await axios.post(route('hr.org.heads.store'), headForm.value)
    showHead.value = false
    reloadUnit()
    setFlash('success', 'Unit head designated.')
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors ?? {}
    } else {
      setFlash('error', e.response?.data?.message ?? 'An error occurred.')
    }
  } finally {
    loading.value = false
  }
}

// ── End assignment ─────────────────────────────────────────────────────────────
async function endAssignment(assignment) {
  if (!confirm(`End assignment for ${assignment.user?.name}?`)) return
  try {
    await axios.patch(route('hr.org.assignments.end', assignment.id))
    reloadUnit()
    setFlash('success', 'Assignment ended.')
  } catch (e) {
    setFlash('error', e.response?.data?.message ?? 'An error occurred.')
  }
}

// ── Helpers ────────────────────────────────────────────────────────────────────
function setFlash(type, msg) {
  flash.value = { success: null, error: null, [type]: msg }
  setTimeout(() => { flash.value = { success: null, error: null } }, 4000)
}

function reloadUnit() {
  router.reload({ only: ['unit', 'tree'], preserveScroll: true })
}

// ── Computed ───────────────────────────────────────────────────────────────────
const currentHead = computed(() => props.unit.current_head?.[0] ?? null)

const ASSIGNMENT_TYPE_LABELS = {
  organic:    'Organic',
  seconded:   'Seconded',
  designated: 'Designated',
  concurrent: 'Concurrent',
  detailed:   'Detailed',
}

const APPOINTMENT_TYPE_LABELS = {
  permanent:  'Permanent',
  temporary:  'Temporary',
  casual:     'Casual',
  cos:        'COS',
  job_order:  'Job Order',
  secondment: 'Secondment',
}

function categoryColor(c) {
  const map = {
    'Teaching':     'bg-blue-100 text-blue-700',
    'Non-Teaching': 'bg-amber-100 text-amber-700',
    'Contractual':  'bg-violet-100 text-violet-700',
    'COS':          'bg-pink-100 text-pink-700',
    'JO':           'bg-orange-100 text-orange-700',
  }
  return map[c] ?? 'bg-slate-100 text-slate-500'
}
</script>

<template>
  <Head :title="unit.name" />
  <AdminLayout :title="unit.name">
    <div class="space-y-5">

      <!-- ── Breadcrumb ───────────────────────────────────────────────────────── -->
      <nav class="flex items-center gap-1.5 text-sm text-slate-500">
        <a :href="route('hr.org.index')" class="hover:text-indigo-600">Org Structure</a>
        <template v-for="(crumb, i) in breadcrumb.split(' › ')" :key="i">
          <ChevronRightIcon class="h-3.5 w-3.5 text-slate-300" />
          <span :class="i === breadcrumb.split(' › ').length - 1 ? 'text-slate-800 font-medium' : ''">
            {{ crumb }}
          </span>
        </template>
      </nav>

      <!-- ── Flash ────────────────────────────────────────────────────────────── -->
      <div v-if="flash.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" /> {{ flash.success }}
      </div>
      <div v-if="flash.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" /> {{ flash.error }}
      </div>

      <!-- ── Hero card ─────────────────────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-6 py-5 flex flex-col sm:flex-row sm:items-start gap-4">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs font-semibold uppercase tracking-wide bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded">{{ unit.type }}</span>
            <span class="text-xs font-mono text-slate-400">{{ unit.code }}</span>
            <span v-if="!unit.is_active" class="text-xs bg-amber-50 text-amber-600 px-2 py-0.5 rounded">Inactive</span>
          </div>
          <h1 class="text-xl font-semibold text-slate-800 mt-2">{{ unit.name }}</h1>
          <p v-if="unit.short_name" class="text-sm text-slate-500">{{ unit.short_name }}</p>
          <p v-if="unit.description" class="text-sm text-slate-600 mt-2 max-w-2xl">{{ unit.description }}</p>

          <div v-if="currentHead" class="mt-3 flex items-center gap-2 text-sm text-slate-600">
            <UserCircleIcon class="h-4 w-4 text-indigo-400 shrink-0" />
            <span class="font-medium">{{ currentHead.user?.name }}</span>
            <span v-if="currentHead.designation_title" class="text-slate-400">— {{ currentHead.designation_title }}</span>
            <span v-if="currentHead.is_acting" class="text-xs bg-amber-50 text-amber-600 px-1.5 py-0.5 rounded">Acting</span>
          </div>
        </div>

        <div class="flex flex-wrap gap-2 shrink-0">
          <button
            v-if="can.assign"
            @click="openAssignModal"
            class="inline-flex items-center gap-1.5 text-sm px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium"
          >
            <UserGroupIcon class="h-4 w-4" /> Assign Employee
          </button>
          <button
            v-if="can.heads"
            @click="openHeadModal"
            class="inline-flex items-center gap-1.5 text-sm px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium"
          >
            <UserCircleIcon class="h-4 w-4" /> Designate Head
          </button>
        </div>
      </div>

      <!-- ── Tabs ──────────────────────────────────────────────────────────────── -->
      <div class="flex gap-1 border-b border-slate-200">
        <button
          v-for="tab in [
            { key: 'overview',     label: 'Overview',     icon: BuildingOfficeIcon },
            { key: 'assignments',  label: `Employees (${employees.length})`, icon: UserGroupIcon },
            { key: 'heads',        label: 'Heads',        icon: UserCircleIcon },
          ]"
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="[
            'flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors',
            activeTab === tab.key
              ? 'border-indigo-600 text-indigo-700'
              : 'border-transparent text-slate-500 hover:text-slate-700',
          ]"
        >
          <component :is="tab.icon" class="h-4 w-4" />
          {{ tab.label }}
        </button>
      </div>

      <!-- ── Tab: Overview ─────────────────────────────────────────────────────── -->
      <div v-if="activeTab === 'overview'" class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Unit info -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-5 py-4 space-y-3">
          <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit Info</h3>
          <dl class="space-y-2 text-sm">
            <div v-if="unit.established_date">
              <dt class="text-slate-400 text-xs">Established</dt>
              <dd class="text-slate-700">{{ unit.established_date }}</dd>
            </div>
            <div v-if="unit.legal_basis">
              <dt class="text-slate-400 text-xs">Legal Basis</dt>
              <dd class="text-slate-700">{{ unit.legal_basis }}</dd>
            </div>
            <div v-if="unit.mandate">
              <dt class="text-slate-400 text-xs">Mandate</dt>
              <dd class="text-slate-600 leading-relaxed text-xs">{{ unit.mandate }}</dd>
            </div>
            <div v-if="unit.remarks">
              <dt class="text-slate-400 text-xs">Remarks</dt>
              <dd class="text-slate-600 text-xs">{{ unit.remarks }}</dd>
            </div>
          </dl>
        </div>

        <!-- Child units -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-100 shadow-sm">
          <div class="px-5 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
              <BuildingOfficeIcon class="h-4 w-4 text-indigo-400" />
              Child Units
              <span class="text-xs text-slate-400 font-normal">({{ unit.active_children?.length ?? 0 }})</span>
            </h3>
          </div>
          <div v-if="!unit.active_children?.length" class="px-5 py-8 text-center text-sm text-slate-400">
            No child units.
          </div>
          <ul v-else class="divide-y divide-slate-100">
            <li v-for="child in unit.active_children" :key="child.id" class="px-5 py-3 flex items-center justify-between hover:bg-slate-50/60">
              <div>
                <a :href="route('hr.org.units.show', child.id)" class="text-sm font-medium text-slate-800 hover:text-indigo-600">
                  {{ child.name }}
                </a>
                <span class="ml-2 text-xs font-mono text-slate-400">{{ child.code }}</span>
              </div>
              <span class="text-xs text-slate-400 capitalize">{{ child.type }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- ── Tab: Employees ────────────────────────────────────────────────────── -->
      <div v-if="activeTab === 'assignments'" class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <UserGroupIcon class="h-4 w-4 text-indigo-400" />
            Employees
            <span class="text-xs text-slate-400 font-normal">({{ employees.length }})</span>
          </h3>
        </div>

        <div v-if="!unit.division_id && !unit.office_id" class="px-5 py-10 text-center text-sm text-slate-400">
          This unit is not linked to a Division or Office. Run a sync to link it.
        </div>
        <div v-else-if="!employees.length" class="px-5 py-10 text-center text-sm text-slate-400">
          No active employees assigned to this unit.
        </div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-xs text-slate-500 uppercase tracking-wide border-b border-slate-100 bg-slate-50">
              <th class="px-5 py-2.5 text-left font-semibold">Name</th>
              <th class="px-4 py-2.5 text-left font-semibold">Badge ID</th>
              <th class="px-4 py-2.5 text-left font-semibold hidden sm:table-cell">Position</th>
              <th class="px-4 py-2.5 text-left font-semibold">Category</th>
              <th class="px-4 py-2.5 text-left font-semibold hidden md:table-cell">Sex</th>
              <th v-if="!unit.office_id" class="px-4 py-2.5 text-left font-semibold hidden lg:table-cell">Office/Unit</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="emp in employees" :key="emp.id" class="hover:bg-slate-50/60 transition-colors">
              <td class="px-5 py-2.5 font-medium text-slate-800">{{ emp.name }}</td>
              <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ emp.badge_id ?? '—' }}</td>
              <td class="px-4 py-2.5 text-slate-600 text-xs hidden sm:table-cell">{{ emp.position ?? '—' }}</td>
              <td class="px-4 py-2.5">
                <span v-if="emp.emp_category"
                  :class="['text-xs px-2 py-0.5 rounded-full font-medium', categoryColor(emp.emp_category)]">
                  {{ emp.emp_category }}
                </span>
                <span v-else class="text-xs text-slate-400">—</span>
              </td>
              <td class="px-4 py-2.5 text-xs text-slate-500 capitalize hidden md:table-cell">{{ emp.sex ?? '—' }}</td>
              <td v-if="!unit.office_id" class="px-4 py-2.5 text-xs text-slate-500 hidden lg:table-cell">
                {{ emp.office ?? '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ── Tab: Heads ─────────────────────────────────────────────────────────── -->
      <div v-if="activeTab === 'heads'" class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <UserCircleIcon class="h-4 w-4 text-indigo-400" /> Head Designations
          </h3>
          <button
            v-if="can.heads"
            @click="openHeadModal"
            class="text-xs px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg font-medium"
          >
            + Designate
          </button>
        </div>
        <div v-if="!unit.head_designations?.length" class="px-5 py-10 text-center text-sm text-slate-400">
          No head designations recorded.
        </div>
        <ul v-else class="divide-y divide-slate-100">
          <li v-for="h in unit.head_designations" :key="h.id" class="px-5 py-3 flex items-start justify-between gap-3">
            <div>
              <p class="text-sm font-medium text-slate-800">{{ h.user?.name }}</p>
              <p class="text-xs text-slate-400 mt-0.5">
                {{ h.designation_title ?? 'Unit Head' }}
                <span v-if="h.is_acting" class="ml-1 text-amber-600">(Acting)</span>
              </p>
              <p class="text-xs text-slate-400">{{ h.effective_date }} — {{ h.end_date ?? 'Present' }}</p>
            </div>
            <span
              :class="h.is_current && !h.end_date ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400'"
              class="shrink-0 text-[10px] font-semibold px-2 py-0.5 rounded"
            >
              {{ h.is_current && !h.end_date ? 'Current' : 'Past' }}
            </span>
          </li>
        </ul>
      </div>

    </div>

    <!-- ── ASSIGN EMPLOYEE MODAL ──────────────────────────────────────────────── -->
    <AppModal :show="showAssign" title="Assign Employee to Unit" size="lg" @close="showAssign = false">
      <form @submit.prevent="submitAssign" class="space-y-4">

        <!-- Employee select -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Employee <span class="text-red-500">*</span></label>
          <select v-model="assignForm.user_id"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            :class="{ 'border-red-400': errors.user_id }"
          >
            <option :value="null">— Select employee —</option>
            <option v-for="emp in allEmployees" :key="emp.id" :value="emp.id">{{ emp.name }}</option>
          </select>
          <p v-if="errors.user_id" class="text-red-500 text-xs mt-1">{{ errors.user_id[0] }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <!-- Assignment type -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Assignment Type <span class="text-red-500">*</span></label>
            <select v-model="assignForm.assignment_type"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            >
              <option v-for="(label, val) in ASSIGNMENT_TYPE_LABELS" :key="val" :value="val">{{ label }}</option>
            </select>
          </div>
          <!-- Appointment type -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Appointment Type</label>
            <select v-model="assignForm.appointment_type"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            >
              <option v-for="(label, val) in APPOINTMENT_TYPE_LABELS" :key="val" :value="val">{{ label }}</option>
            </select>
          </div>
        </div>

        <!-- Position title -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Position Title</label>
          <input v-model="assignForm.position_title" type="text" placeholder="e.g. Division Chief III"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
          />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Item Number</label>
            <input v-model="assignForm.item_number" type="text" placeholder="Plantilla item #"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Effective Date <span class="text-red-500">*</span></label>
            <input v-model="assignForm.effective_date" type="date"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': errors.effective_date }"
            />
            <p v-if="errors.effective_date" class="text-red-500 text-xs mt-1">{{ errors.effective_date[0] }}</p>
          </div>
        </div>

        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="assignForm.is_primary" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
          <span class="text-sm text-slate-700">Set as primary unit assignment</span>
        </label>

      </form>
      <template #footer>
        <button @click="showAssign = false" class="text-sm px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">Cancel</button>
        <button @click="submitAssign" :disabled="loading"
          class="text-sm px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium disabled:opacity-60">
          {{ loading ? 'Saving…' : 'Assign' }}
        </button>
      </template>
    </AppModal>

    <!-- ── HEAD DESIGNATION MODAL ─────────────────────────────────────────────── -->
    <AppModal :show="showHead" title="Designate Unit Head" size="lg" @close="showHead = false">
      <form @submit.prevent="submitHead" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Employee <span class="text-red-500">*</span></label>
          <select v-model="headForm.user_id"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            :class="{ 'border-red-400': errors.user_id }"
          >
            <option :value="null">— Select employee —</option>
            <option v-for="emp in allEmployees" :key="emp.id" :value="emp.id">{{ emp.name }}</option>
          </select>
          <p v-if="errors.user_id" class="text-red-500 text-xs mt-1">{{ errors.user_id[0] }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Designation Title</label>
          <input v-model="headForm.designation_title" type="text" placeholder="e.g. Division Chief, OIC Director"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
          />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Effective Date <span class="text-red-500">*</span></label>
            <input v-model="headForm.effective_date" type="date"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': errors.effective_date }"
            />
            <p v-if="errors.effective_date" class="text-red-500 text-xs mt-1">{{ errors.effective_date[0] }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Designation Order No.</label>
            <input v-model="headForm.designation_order" type="text" placeholder="DO No. / CSC Order"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            />
          </div>
        </div>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="headForm.is_acting" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
            <span class="text-sm text-slate-700">Acting / OIC</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="headForm.is_current" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
            <span class="text-sm text-slate-700">Set as current head</span>
          </label>
        </div>
      </form>
      <template #footer>
        <button @click="showHead = false" class="text-sm px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">Cancel</button>
        <button @click="submitHead" :disabled="loading"
          class="text-sm px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium disabled:opacity-60">
          {{ loading ? 'Saving…' : 'Designate' }}
        </button>
      </template>
    </AppModal>

  </AdminLayout>
</template>
