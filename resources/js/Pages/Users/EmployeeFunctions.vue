<script setup>
import { Head, router, usePage } from "@inertiajs/vue3"
import axios from "axios"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppCard from "@/Components/AppCard.vue"
import AppButton from "@/Components/AppButton.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppModal from "@/Components/AppModal.vue"
import AppInput from "@/Components/AppInput.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import { ArrowPathIcon, PencilSquareIcon, PlusIcon, TrashIcon } from "@heroicons/vue/24/outline"
import { computed, ref } from "vue"
import { useSubmit } from "@/Composables/useSubmit"
import { TH, TD, TR, TD_END } from "@/Composables/useTableClasses.js"

const props = defineProps({
  employee:               Object,
  functions:              Array,
  isFaculty:              Boolean,
  workDistributionPlans:  { type: Array, default: () => [] },
  scopeOptions:           { type: Object, default: () => ({ roles: [], offices: [], divisions: [], positions: [], empCategories: [], allEmployees: [] }) },
})

const { isSubmitting, submit } = useSubmit()
const formErrors = computed(() => usePage().props.errors ?? {})

const coreFunctions = computed(() => props.functions.filter(f => f.function_type === "core"))
const supportFunctions = computed(() => props.functions.filter(f => f.function_type === "support"))

function syncFromFacultyLoading() {
  submit((opts) => router.post(route("employee-functions.sync", props.employee.id), {}, opts))
}

function removeFunction(fn) {
  submit((opts) => router.delete(route("employee-functions.destroy", [props.employee.id, fn.id]), opts))
}

const showFormModal = ref(false)
const editingFunction = ref(null)
const form = ref({ function_type: "core", label: "", output_outcome: "", weight_percent: null, work_distribution_plan_ids: [] })

const wdpSearch = ref("")
const filteredWorkDistributionPlans = computed(() => {
  const q = wdpSearch.value.trim().toLowerCase()
  if (!q) return props.workDistributionPlans
  return props.workDistributionPlans.filter(p => p.success_indicator?.toLowerCase().includes(q))
})

// Bulk-assign scope (Support Functions only) — who besides "this employee" should get this function.
const scope = ref("this_only")
const scopeUserIds = ref([])
const scopeEmployeeSearch = ref("")
const scopeFilters = ref({ position: [], role_id: [], office_id: [], division_id: [], emp_category: [] })
const scopePreview = ref(null)
const previewLoading = ref(false)

const otherEmployees = computed(() => (props.scopeOptions.allEmployees ?? []).filter(e => e.id !== props.employee.id))
const filteredScopeEmployees = computed(() => {
  const q = scopeEmployeeSearch.value.trim().toLowerCase()
  if (!q) return otherEmployees.value
  return otherEmployees.value.filter(e => e.name.toLowerCase().includes(q) || (e.position ?? "").toLowerCase().includes(q))
})

function resetScope() {
  scope.value = "this_only"
  scopeUserIds.value = []
  scopeEmployeeSearch.value = ""
  scopeFilters.value = { position: [], role_id: [], office_id: [], division_id: [], emp_category: [] }
  scopePreview.value = null
}

function toggleScopeUser(id) {
  const ix = scopeUserIds.value.indexOf(id)
  ix >= 0 ? scopeUserIds.value.splice(ix, 1) : scopeUserIds.value.push(id)
  scopePreview.value = null
}

function toggleFilterValue(dimension, value) {
  const arr = scopeFilters.value[dimension]
  const ix = arr.indexOf(value)
  ix >= 0 ? arr.splice(ix, 1) : arr.push(value)
  scopePreview.value = null
}

function scopePayload() {
  if (scope.value === "selected") return { scope: "selected", user_ids: scopeUserIds.value }
  if (scope.value === "filtered") return { scope: "filtered", filters: scopeFilters.value }
  return { scope: "all" }
}

async function loadPreview() {
  previewLoading.value = true
  try {
    const { data } = await axios.post(route("employee-functions.preview-scope"), scopePayload())
    scopePreview.value = data
  } finally {
    previewLoading.value = false
  }
}

function openAddModal(type) {
  editingFunction.value = null
  form.value = { function_type: type, label: "", output_outcome: "", weight_percent: null, work_distribution_plan_ids: [] }
  wdpSearch.value = ""
  resetScope()
  showFormModal.value = true
}

function openEditModal(fn) {
  editingFunction.value = fn
  form.value = {
    function_type: fn.function_type,
    label: fn.label,
    output_outcome: fn.output_outcome ?? "",
    weight_percent: fn.weight_percent,
    work_distribution_plan_ids: (fn.work_distribution_plans ?? []).map(p => p.id),
  }
  wdpSearch.value = ""
  resetScope()
  showFormModal.value = true
}

function toggleWdp(id) {
  const ix = form.value.work_distribution_plan_ids.indexOf(id)
  ix >= 0 ? form.value.work_distribution_plan_ids.splice(ix, 1) : form.value.work_distribution_plan_ids.push(id)
}

function saveForm() {
  const opts = { onSuccess: () => { showFormModal.value = false } }

  const isBulk = !editingFunction.value && form.value.function_type === "support" && scope.value !== "this_only"
  if (isBulk) {
    const payload = { ...scopePayload(), label: form.value.label, output_outcome: form.value.output_outcome, work_distribution_plan_ids: form.value.work_distribution_plan_ids }
    submit((o) => router.post(route("employee-functions.bulk-store"), payload, o), opts)
    return
  }

  const payload = {
    function_type: form.value.function_type,
    label: form.value.label,
    output_outcome: form.value.output_outcome,
    weight_percent: form.value.weight_percent,
    work_distribution_plan_ids: form.value.work_distribution_plan_ids,
  }

  if (editingFunction.value) {
    submit((o) => router.put(route("employee-functions.update", [props.employee.id, editingFunction.value.id]), payload, o), opts)
  } else {
    submit((o) => router.post(route("employee-functions.store", props.employee.id), payload, o), opts)
  }
}
</script>

<template>
  <Head :title="`${employee.name} — Functions`" />
  <AdminLayout :title="`${employee.name} — Employee Functions`">
    <AppPageHeader :title="employee.name" :subtitle="employee.position" />

    <AppCard class="mb-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-slate-700">Core Functions</h3>
        <div class="flex gap-2">
          <AppButton v-if="isFaculty" variant="secondary" :disabled="isSubmitting" @click="syncFromFacultyLoading">
            <ArrowPathIcon class="w-4 h-4 mr-1" /> Sync from Faculty Loading
          </AppButton>
          <AppButton @click="openAddModal('core')"><PlusIcon class="w-4 h-4 mr-1" /> Add Core Function</AppButton>
        </div>
      </div>
      <table class="w-full">
        <thead>
          <tr>
            <th :class="TH">Label</th>
            <th :class="TH">Source</th>
            <th :class="TH">Weight %</th>
            <th :class="TH"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="fn in coreFunctions" :key="fn.id" :class="TR">
            <td :class="TD">
              {{ fn.label }}
              <div v-if="fn.output_outcome" class="text-xs text-slate-400 mt-0.5">{{ fn.output_outcome }}</div>
            </td>
            <td :class="TD">
              <AppBadge>{{ fn.source_label }}</AppBadge>
              <div v-if="fn.work_distribution_plans?.length" class="text-xs text-slate-500 mt-1 space-y-0.5">
                <div v-for="plan in fn.work_distribution_plans" :key="plan.id">{{ plan.success_indicator }}</div>
              </div>
            </td>
            <td :class="TD">{{ fn.weight_percent ?? "—" }}</td>
            <td :class="TD_END">
              <div class="flex justify-end gap-1">
                <AppIconButton label="Edit" @click="openEditModal(fn)"><PencilSquareIcon class="w-4 h-4" /></AppIconButton>
                <AppIconButton label="Remove" variant="danger" @click="removeFunction(fn)"><TrashIcon class="w-4 h-4" /></AppIconButton>
              </div>
            </td>
          </tr>
          <tr v-if="!coreFunctions.length">
            <td :class="TD" colspan="4">No Core Functions assigned yet.</td>
          </tr>
        </tbody>
      </table>
    </AppCard>

    <AppCard>
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-slate-700">Support Functions</h3>
        <AppButton @click="openAddModal('support')"><PlusIcon class="w-4 h-4 mr-1" /> Add Support Function</AppButton>
      </div>
      <table class="w-full">
        <thead>
          <tr>
            <th :class="TH">Label</th>
            <th :class="TH">Source</th>
            <th :class="TH"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="fn in supportFunctions" :key="fn.id" :class="TR">
            <td :class="TD">
              {{ fn.label }}
              <div v-if="fn.output_outcome" class="text-xs text-slate-400 mt-0.5">{{ fn.output_outcome }}</div>
            </td>
            <td :class="TD">
              <AppBadge>{{ fn.source_label }}</AppBadge>
              <div v-if="fn.work_distribution_plans?.length" class="text-xs text-slate-500 mt-1 space-y-0.5">
                <div v-for="plan in fn.work_distribution_plans" :key="plan.id">{{ plan.success_indicator }}</div>
              </div>
            </td>
            <td :class="TD_END">
              <div class="flex justify-end gap-1">
                <AppIconButton label="Edit" @click="openEditModal(fn)"><PencilSquareIcon class="w-4 h-4" /></AppIconButton>
                <AppIconButton label="Remove" variant="danger" @click="removeFunction(fn)"><TrashIcon class="w-4 h-4" /></AppIconButton>
              </div>
            </td>
          </tr>
          <tr v-if="!supportFunctions.length">
            <td :class="TD" colspan="3">No Support Functions assigned yet.</td>
          </tr>
        </tbody>
      </table>
    </AppCard>

    <AppModal :show="showFormModal" :title="editingFunction ? 'Edit Function' : 'Add Function'" @close="showFormModal = false">
      <div class="space-y-4">
        <AppInput v-model="form.label" label="Label" placeholder="e.g. Chairperson, Discipline Committee" />
        <AppTextarea
          v-model="form.output_outcome"
          label="Output/Outcome Statement"
          placeholder="A short statement of the output/outcome this function produces"
          rows="2"
        />
        <AppInput
          v-if="form.function_type === 'core'"
          v-model="form.weight_percent"
          type="number"
          label="Weight %"
          required
          :error="formErrors.weight_percent"
        />

        <div v-if="!editingFunction && form.function_type === 'support'" class="space-y-3 rounded-lg border border-slate-200 p-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Apply to</label>
            <div class="flex flex-wrap gap-3 text-sm">
              <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="radio" value="this_only" v-model="scope" class="text-indigo-600 focus:ring-indigo-500" /> This employee only
              </label>
              <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="radio" value="all" v-model="scope" class="text-indigo-600 focus:ring-indigo-500" /> All employees
              </label>
              <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="radio" value="selected" v-model="scope" class="text-indigo-600 focus:ring-indigo-500" /> Selected employees
              </label>
              <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="radio" value="filtered" v-model="scope" class="text-indigo-600 focus:ring-indigo-500" /> Filtered group
              </label>
            </div>
          </div>

          <div v-if="scope === 'selected'">
            <AppInput v-model="scopeEmployeeSearch" placeholder="Search employees…" class="mb-2" />
            <div class="max-h-40 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
              <label v-for="e in filteredScopeEmployees" :key="e.id" class="flex items-center gap-2 px-3 py-1.5 text-sm cursor-pointer hover:bg-slate-50">
                <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                  :checked="scopeUserIds.includes(e.id)" @change="toggleScopeUser(e.id)" />
                {{ e.name }} <span v-if="e.position" class="text-slate-400">— {{ e.position }}</span>
              </label>
              <p v-if="!filteredScopeEmployees.length" class="px-3 py-1.5 text-sm text-slate-400">No employees match your search.</p>
            </div>
          </div>

          <div v-if="scope === 'filtered'" class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Position</label>
              <div class="max-h-28 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
                <label v-for="p in scopeOptions.positions" :key="p" class="flex items-center gap-2 px-2 py-1 text-xs cursor-pointer hover:bg-slate-50">
                  <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="scopeFilters.position.includes(p)" @change="toggleFilterValue('position', p)" /> {{ p }}
                </label>
                <p v-if="!scopeOptions.positions?.length" class="px-2 py-1 text-xs text-slate-400">No positions on file.</p>
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Role</label>
              <div class="max-h-28 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
                <label v-for="r in scopeOptions.roles" :key="r.id" class="flex items-center gap-2 px-2 py-1 text-xs cursor-pointer hover:bg-slate-50">
                  <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="scopeFilters.role_id.includes(r.id)" @change="toggleFilterValue('role_id', r.id)" /> {{ r.name }}
                </label>
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Office</label>
              <div class="max-h-28 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
                <label v-for="o in scopeOptions.offices" :key="o.id" class="flex items-center gap-2 px-2 py-1 text-xs cursor-pointer hover:bg-slate-50">
                  <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="scopeFilters.office_id.includes(o.id)" @change="toggleFilterValue('office_id', o.id)" /> {{ o.name }}
                </label>
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Division</label>
              <div class="max-h-28 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
                <label v-for="d in scopeOptions.divisions" :key="d.id" class="flex items-center gap-2 px-2 py-1 text-xs cursor-pointer hover:bg-slate-50">
                  <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="scopeFilters.division_id.includes(d.id)" @change="toggleFilterValue('division_id', d.id)" /> {{ d.name }}
                </label>
              </div>
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Employment Category</label>
              <div class="flex flex-wrap gap-3 text-xs">
                <label v-for="c in scopeOptions.empCategories" :key="c" class="flex items-center gap-1.5 cursor-pointer">
                  <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="scopeFilters.emp_category.includes(c)" @change="toggleFilterValue('emp_category', c)" /> {{ c }}
                </label>
              </div>
            </div>
          </div>

          <div v-if="scope !== 'this_only'">
            <AppButton variant="secondary" :disabled="previewLoading" @click="loadPreview">
              {{ previewLoading ? "Checking…" : "Preview matched employees" }}
            </AppButton>
            <div v-if="scopePreview" class="mt-2 text-sm text-slate-600">
              <p class="font-medium">{{ scopePreview.count }} employee(s) will receive this function.</p>
              <div v-if="scopePreview.employees" class="max-h-32 overflow-y-auto mt-1 rounded-lg border border-slate-200 divide-y divide-slate-100">
                <div v-for="e in scopePreview.employees" :key="e.id" class="px-3 py-1 text-xs">
                  {{ e.name }} <span v-if="e.position" class="text-slate-400">— {{ e.position }}</span>
                </div>
                <p v-if="!scopePreview.employees.length" class="px-3 py-1 text-xs text-slate-400">No employees match this scope.</p>
              </div>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Linked Work Distribution Plans (optional)</label>
          <AppInput v-model="wdpSearch" placeholder="Search plans…" class="mb-2" />
          <div class="max-h-40 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
            <label v-for="plan in filteredWorkDistributionPlans" :key="plan.id" class="flex items-center gap-2 px-3 py-1.5 text-sm cursor-pointer hover:bg-slate-50">
              <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                :checked="form.work_distribution_plan_ids.includes(plan.id)" @change="toggleWdp(plan.id)" />
              {{ plan.success_indicator }}
            </label>
            <p v-if="!workDistributionPlans.length" class="px-3 py-1.5 text-sm text-slate-400">No plans available for the current fiscal year.</p>
            <p v-else-if="!filteredWorkDistributionPlans.length" class="px-3 py-1.5 text-sm text-slate-400">No plans match your search.</p>
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <AppButton variant="secondary" @click="showFormModal = false">Cancel</AppButton>
          <AppButton :disabled="isSubmitting" @click="saveForm">Save</AppButton>
        </div>
      </div>
    </AppModal>
  </AdminLayout>
</template>
