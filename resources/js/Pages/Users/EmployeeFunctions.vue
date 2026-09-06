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
import { ArrowPathIcon, PlusIcon, TrashIcon } from "@heroicons/vue/24/outline"
import { computed, ref } from "vue"
import { useSubmit } from "@/Composables/useSubmit"
import { TH, TD, TR, TD_END } from "@/Composables/useTableClasses.js"

const props = defineProps({
  employee:  Object,
  functions: Array,
  isFaculty: Boolean,
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

const showAddModal = ref(false)
const addForm = ref({ function_type: "core", label: "", weight_percent: null })

function openAddModal(type) {
  addForm.value = { function_type: type, label: "", weight_percent: null }
  showAddModal.value = true
}

function saveAddForm() {
  submit(
    (opts) => router.post(route("employee-functions.store", props.employee.id), addForm.value, opts),
    { onSuccess: () => { showAddModal.value = false } }
  )
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
            <td :class="TD"><AppBadge>{{ fn.source_type }}</AppBadge></td>
            <td :class="TD">{{ fn.weight_percent ?? "—" }}</td>
            <td :class="TD_END">
              <AppIconButton label="Remove" variant="danger" @click="removeFunction(fn)"><TrashIcon class="w-4 h-4" /></AppIconButton>
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
            <td :class="TD"><AppBadge>{{ fn.source_type }}</AppBadge></td>
            <td :class="TD_END">
              <AppIconButton label="Remove" variant="danger" @click="removeFunction(fn)"><TrashIcon class="w-4 h-4" /></AppIconButton>
            </td>
          </tr>
          <tr v-if="!supportFunctions.length">
            <td :class="TD" colspan="3">No Support Functions assigned yet.</td>
          </tr>
        </tbody>
      </table>
    </AppCard>

    <AppModal :show="showAddModal" title="Add Function" @close="showAddModal = false">
      <div class="space-y-4">
        <AppInput v-model="addForm.label" label="Label" placeholder="e.g. Chairperson, Discipline Committee" />
        <AppInput v-if="addForm.function_type === 'core'" v-model="addForm.weight_percent" type="number" label="Weight %" />
        <div class="flex justify-end gap-2 pt-2">
          <AppButton variant="secondary" @click="showAddModal = false">Cancel</AppButton>
          <AppButton :disabled="isSubmitting" @click="saveAddForm">Save</AppButton>
        </div>
      </div>
    </AppModal>
  </AdminLayout>
</template>
