<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppCard from "@/Components/AppCard.vue"
import AppButton from "@/Components/AppButton.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppModal from "@/Components/AppModal.vue"
import AppInput from "@/Components/AppInput.vue"
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
})

const { isSubmitting, submit } = useSubmit()

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
const form = ref({ function_type: "core", label: "", weight_percent: null, work_distribution_plan_ids: [] })

const wdpSearch = ref("")
const filteredWorkDistributionPlans = computed(() => {
  const q = wdpSearch.value.trim().toLowerCase()
  if (!q) return props.workDistributionPlans
  return props.workDistributionPlans.filter(p => p.success_indicator?.toLowerCase().includes(q))
})

function openAddModal(type) {
  editingFunction.value = null
  form.value = { function_type: type, label: "", weight_percent: null, work_distribution_plan_ids: [] }
  wdpSearch.value = ""
  showFormModal.value = true
}

function openEditModal(fn) {
  editingFunction.value = fn
  form.value = {
    function_type: fn.function_type,
    label: fn.label,
    weight_percent: fn.weight_percent,
    work_distribution_plan_ids: (fn.work_distribution_plans ?? []).map(p => p.id),
  }
  wdpSearch.value = ""
  showFormModal.value = true
}

function toggleWdp(id) {
  const ix = form.value.work_distribution_plan_ids.indexOf(id)
  ix >= 0 ? form.value.work_distribution_plan_ids.splice(ix, 1) : form.value.work_distribution_plan_ids.push(id)
}

function saveForm() {
  const payload = {
    function_type: form.value.function_type,
    label: form.value.label,
    weight_percent: form.value.weight_percent,
    work_distribution_plan_ids: form.value.work_distribution_plan_ids,
  }
  const opts = { onSuccess: () => { showFormModal.value = false } }

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
            <td :class="TD">{{ fn.label }}</td>
            <td :class="TD">
              <AppBadge>{{ fn.source_type }}</AppBadge>
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
            <td :class="TD">{{ fn.label }}</td>
            <td :class="TD">
              <AppBadge>{{ fn.source_type }}</AppBadge>
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
        <AppInput v-if="form.function_type === 'core'" v-model="form.weight_percent" type="number" label="Weight %" />
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
