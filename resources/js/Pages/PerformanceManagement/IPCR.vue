<script setup>
import { ref, reactive, computed } from "vue"
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppModal from "@/Components/AppModal.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import {
  EyeIcon,
  PencilSquareIcon,
  PlusIcon,
  PrinterIcon,
  ArrowDownTrayIcon,
} from "@heroicons/vue/24/outline"

import { useIPCR } from "@/Composables/useIPCR.js"

const props = defineProps({
  plans: Array,
  currentUserIsChief: { type: Boolean, default: false },
})

// Use composable
const {
  searchQuery,
  filteredPlans,
  groupedPlans,
  showModal,
  modalMode,
  selectedPlan,
  form,
  openModal,
  openCreateModal,
  closeModal,
  submitTarget,
  submitAccomplishment,
  submitBulkCreateTargets,
  approveIPCR,
  removeIPCR,
  exportAllHTML,
  exportAllExcel,
  exportPlanHTML,
  exportPlanExcel,
  createModalState,
  togglePlanSelection,
  isPlanSelected,
  selectedPlansList,
} = useIPCR(props)

// META for export
const meta = reactive({
  personName: "",
  position: "",
  office: "",
  period: "",
})
const showExportMetaModal = ref(false)

// helper for table rowspan/merging
const shouldShowSubOutcome = (section, index) => {
  if (index === 0) return true
  const current = section[index].performance_indicator.agency_outcome.sub_outcome
  const previous = section[index - 1].performance_indicator.agency_outcome.sub_outcome
  return current !== previous
}

const countSubOutcomeRows = (section, sub) => section.filter(
  p => p.performance_indicator.agency_outcome.sub_outcome === sub
).length

const shouldShowPI = (section, index) => {
  if (index === 0) return true
  const current = section[index].performance_indicator.description
  const previous = section[index - 1].performance_indicator.description
  return current !== previous
}

const countPIRows = (section, pi) => section.filter(
  p => p.performance_indicator.description === pi
).length
</script>

<template>
  <Head title="My IPCR" />
  <AdminLayout title="Individual Performance">
    <div class="p-6 space-y-6">

      <!-- HEADER -->
      <AppPageHeader title="My IPCR Plans">
        <template #actions>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search plans..."
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
          />
          <AppButton variant="secondary" @click="showExportMetaModal = true">
            <PrinterIcon class="w-4 h-4" /> CSC Format
          </AppButton>
          <AppButton variant="secondary" @click="exportAllExcel()">
            <ArrowDownTrayIcon class="w-4 h-4" /> Excel
          </AppButton>
          <AppButton @click="openCreateModal()">
            <PlusIcon class="w-4 h-4" /> Create IPCR Target
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- TABLE -->
      <div class="overflow-x-auto rounded-xl border border-slate-100">
        <table class="min-w-full border border-slate-200 bg-white text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th rowspan="2" colspan="2" class="border border-slate-200 px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Output</th>
              <th rowspan="2" class="border border-slate-200 px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Plan / Success Indicator</th>
              <th rowspan="2" class="border border-slate-200 px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Target / Measure</th>
              <th rowspan="2" class="border border-slate-200 px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Accomplishment</th>
              <th colspan="4" class="border border-slate-200 px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Ratings</th>
              <th rowspan="2" class="border border-slate-200 px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th rowspan="2" class="border border-slate-200 px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Action</th>
              <th rowspan="2" class="border border-slate-200 px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">MOVs</th>
            </tr>
            <tr>
              <th class="border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">Q</th>
              <th class="border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">E</th>
              <th class="border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">T</th>
              <th class="border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">A</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100">
            <template v-for="(section, key) in groupedPlans" :key="key">
              <tr>
                <td colspan="12" class="bg-slate-100 font-semibold px-3 py-1.5 text-slate-700 text-xs uppercase tracking-wide">{{ key }}</td>
              </tr>

              <tr v-for="(plan, index) in section" :key="plan.id" class="hover:bg-slate-50/60">
                <td v-if="shouldShowSubOutcome(section, index)"
                    :rowspan="countSubOutcomeRows(section, plan.performance_indicator.agency_outcome.sub_outcome)"
                    class="border border-slate-100 px-3 py-2 text-sm text-slate-700 align-top">
                  {{ plan.performance_indicator.agency_outcome.sub_outcome ?? '—' }}
                </td>

                <td v-if="shouldShowPI(section, index)"
                    :rowspan="countPIRows(section, plan.performance_indicator.description)"
                    class="border border-slate-100 px-3 py-2 text-sm text-slate-700 align-top">
                  {{ plan.performance_indicator?.description ?? '—' }}
                </td>

                <td class="border border-slate-100 px-3 py-2 text-sm text-slate-700">{{ plan.success_indicator }}</td>
                <td class="border border-slate-100 px-3 py-2 text-sm text-slate-700">{{ plan.ipcrs[0]?.target ?? '—' }}</td>
                <td class="border border-slate-100 px-3 py-2 text-sm text-slate-700">{{ plan.ipcrs[0]?.accomplishment ?? '—' }}</td>
                <td class="border border-slate-100 px-3 py-2 text-sm text-slate-700 text-center">{{ plan.ipcrs[0]?.self_quality ?? '—' }}</td>
                <td class="border border-slate-100 px-3 py-2 text-sm text-slate-700 text-center">{{ plan.ipcrs[0]?.self_efficiency ?? '—' }}</td>
                <td class="border border-slate-100 px-3 py-2 text-sm text-slate-700 text-center">{{ plan.ipcrs[0]?.self_timeliness ?? '—' }}</td>
                <td class="border border-slate-100 px-3 py-2 text-sm text-slate-700 text-center">{{ plan.ipcrs[0]?.self_rating ?? '—' }}</td>
                <td class="border border-slate-100 px-3 py-2 text-sm text-slate-700">{{ plan.ipcrs[0]?.target_status ?? 'draft' }}</td>

                <td class="border border-slate-100 px-3 py-2 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <AppIconButton v-if="!plan.ipcrs.length" label="Add target" size="sm" @click="openModal('target', plan)">
                      <PlusIcon class="w-4 h-4"/>
                    </AppIconButton>
                    <AppIconButton v-if="plan.ipcrs[0]?.target_status === 'approved' && !plan.ipcrs[0]?.accomplishment"
                            label="Add accomplishment" variant="warning" size="sm"
                            @click="openModal('accomplishment', plan)">
                      <PencilSquareIcon class="w-4 h-4"/>
                    </AppIconButton>
                    <div v-if="props.currentUserIsChief && plan.ipcrs[0]" class="flex gap-1">
                      <button v-if="plan.ipcrs[0]?.target_status === 'submitted'" @click="approveIPCR(plan.ipcrs[0])"
                        class="rounded px-2 py-1 text-xs font-medium text-success-700 hover:bg-success-50" title="Approve target">Approve</button>
                      <button v-if="plan.ipcrs[0]?.target_status !== 'approved'" @click="removeIPCR(plan.ipcrs[0])"
                        class="rounded px-2 py-1 text-xs font-medium text-danger-600 hover:bg-danger-50" title="Remove target">Remove</button>
                    </div>
                  </div>
                </td>

                <td class="border border-slate-100 px-3 py-2 text-center">
                  <a :href="plan.ipcrs[0]?.mov_link" target="_blank" class="text-indigo-600 hover:underline text-xs" v-if="plan.ipcrs[0]?.mov_link">View MOVs</a>
                  <span v-else class="text-slate-400">—</span>
                </td>
              </tr>
            </template>

            <tr v-if="filteredPlans.length === 0">
              <td colspan="12" class="py-16 text-center text-slate-400 text-sm">No plans found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- MODAL: CREATE / TARGET / ACCOMPLISHMENT -->
      <AppModal
        :show="showModal"
        :title="modalMode === 'target' ? 'Submit Target' :
                modalMode === 'accomplishment' ? 'Submit Accomplishment' :
                modalMode === 'create' ? 'Create IPCR Targets (Bulk)' : 'View Plan'"
        size="2xl"
        @close="closeModal"
      >
        <!-- CREATE (BULK) -->
        <form v-if="modalMode === 'create'" @submit.prevent="submitBulkCreateTargets">
          <div class="grid grid-cols-2 gap-3 mb-4">
            <AppSelect v-model="createModalState.period" label="Rating Period" placeholder="Select period">
              <option value="Jan-Jun">January - June</option>
              <option value="Jul-Dec">July - December</option>
            </AppSelect>
            <AppInput v-model.number="createModalState.year" label="Year" type="number" min="2000" />
          </div>
          <div>
            <AppInput v-model="createModalState.search" label="Search Plans to add" type="text" placeholder="Search..." class="mb-2" />
            <div class="max-h-48 overflow-auto rounded-lg border border-slate-200 p-2 space-y-1">
              <div v-for="plan in filteredPlans" :key="'create-'+plan.id" class="flex items-start gap-2 py-2">
                <input type="checkbox" :id="'plan-'+plan.id" :checked="isPlanSelected(plan.id)" @change="togglePlanSelection(plan)" class="mt-0.5" />
                <label :for="'plan-'+plan.id" class="flex-1 cursor-pointer">
                  <div class="text-sm font-medium text-slate-700">{{ plan.success_indicator }}</div>
                  <div class="text-xs text-slate-500">{{ plan.performance_indicator?.description }}</div>
                  <div class="text-xs text-slate-500">{{ plan.office_involved }}</div>
                </label>
              </div>
              <div v-if="filteredPlans.length===0" class="text-sm text-slate-400 text-center py-4">No plans to choose from.</div>
            </div>
          </div>
          <div class="flex justify-end gap-2 mt-5">
            <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
            <AppButton type="submit">Submit Targets</AppButton>
          </div>
        </form>

        <!-- TARGET FORM (single plan) -->
        <form v-if="modalMode === 'target' && selectedPlan" @submit.prevent="submitTarget">
          <div class="mb-4 rounded-lg bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">{{ selectedPlan.success_indicator }}</div>
          <AppSelect v-model="form.period" label="Rating Period" placeholder="Select period" class="mb-2">
            <option value="Jan-Jun">January - June</option>
            <option value="Jul-Dec">July - December</option>
          </AppSelect>
          <div class="flex justify-end mt-4">
            <AppButton type="submit">Save</AppButton>
          </div>
        </form>

        <!-- ACCOMPLISHMENT FORM -->
        <form v-if="modalMode === 'accomplishment' && selectedPlan" @submit.prevent="submitAccomplishment">
          <div class="mb-4 rounded-lg bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">{{ selectedPlan.success_indicator }}</div>
          <div class="space-y-3">
            <AppTextarea v-model="form.accomplishment" label="Actual Accomplishment" :rows="3" placeholder="Actual Accomplishment" />
            <AppInput v-model="form.mov_link" label="Link to MOVs" type="text" placeholder="https://..." />
            <div class="grid grid-cols-3 gap-3">
              <AppInput v-model.number="form.self_quality" label="Quality" type="number" min="1" max="5" />
              <AppInput v-model.number="form.self_efficiency" label="Efficiency" type="number" min="1" max="5" />
              <AppInput v-model.number="form.self_timeliness" label="Timeliness" type="number" min="1" max="5" />
            </div>
          </div>
          <div class="flex justify-end mt-4">
            <AppButton type="submit">Save</AppButton>
          </div>
        </form>
      </AppModal>

      <!-- MODAL: EXPORT META -->
      <AppModal :show="showExportMetaModal" title="Export IPCR — Employee Info" @close="showExportMetaModal = false">
        <div class="space-y-3">
          <AppInput v-model="meta.personName" label="Employee Name" type="text" placeholder="Employee Name" />
          <AppInput v-model="meta.position" label="Position" type="text" placeholder="Position" />
          <AppInput v-model="meta.office" label="Office / Division" type="text" placeholder="Office / Division" />
          <AppInput v-model="meta.period" label="Rating Period" type="text" placeholder="Rating Period" />
        </div>
        <template #footer>
          <AppButton variant="secondary" @click="showExportMetaModal = false">Cancel</AppButton>
          <AppButton @click="() => { exportAllHTML(meta); showExportMetaModal = false }">Export HTML</AppButton>
        </template>
      </AppModal>

    </div>
  </AdminLayout>
</template>
