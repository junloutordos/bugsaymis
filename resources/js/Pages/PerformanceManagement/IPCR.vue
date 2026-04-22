<script setup>
import { ref, reactive, computed } from "vue"
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
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
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">My IPCR Plans</h1>
        <div class="flex flex-wrap items-center gap-2">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search plans..."
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
          />
          <button
            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
            @click="showExportMetaModal = true"
          >
            <PrinterIcon class="w-4 h-4" /> CSC Format
          </button>
          <button
            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
            @click="exportAllExcel()"
          >
            <ArrowDownTrayIcon class="w-4 h-4" /> Excel
          </button>
          <button
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
            @click="openCreateModal()"
          >
            <PlusIcon class="w-4 h-4" /> Create IPCR Target
          </button>
        </div>
      </div>

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
                    <button v-if="!plan.ipcrs.length" @click="openModal('target', plan)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-indigo-700 transition-colors" title="Add target">
                      <PlusIcon class="w-4 h-4"/>
                    </button>
                    <button v-if="plan.ipcrs[0]?.target_status === 'approved' && !plan.ipcrs[0]?.accomplishment"
                            @click="openModal('accomplishment', plan)"
                            class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-amber-700 transition-colors" title="Add accomplishment">
                      <PencilSquareIcon class="w-4 h-4"/>
                    </button>
                    <div v-if="props.currentUserIsChief && plan.ipcrs[0]" class="flex gap-1">
                      <button v-if="plan.ipcrs[0]?.target_status === 'submitted'" @click="approveIPCR(plan.ipcrs[0])"
                        class="rounded px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50" title="Approve target">Approve</button>
                      <button v-if="plan.ipcrs[0]?.target_status !== 'approved'" @click="removeIPCR(plan.ipcrs[0])"
                        class="rounded px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50" title="Remove target">Remove</button>
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
      <div v-if="showModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{
                modalMode === "target" ? "Submit Target" :
                modalMode === "accomplishment" ? "Submit Accomplishment" :
                modalMode === "create" ? "Create IPCR Targets (Bulk)" : "View Plan"
              }}
            </h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors" @click="closeModal">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="px-6 py-5">

            <!-- CREATE (BULK) -->
            <form v-if="modalMode === 'create'" @submit.prevent="submitBulkCreateTargets">
              <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Rating Period</label>
                  <select v-model="createModalState.period" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select period</option>
                    <option value="Jan-Jun">January - June</option>
                    <option value="Jul-Dec">July - December</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Year</label>
                  <input type="number" v-model.number="createModalState.year" min="2000" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Search Plans to add</label>
                <input v-model="createModalState.search" type="text" placeholder="Search..." class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-2" />
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
                <button type="button" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors" @click="closeModal">Cancel</button>
                <button class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">Submit Targets</button>
              </div>
            </form>

            <!-- TARGET FORM (single plan) -->
            <form v-if="modalMode === 'target' && selectedPlan" @submit.prevent="submitTarget">
              <div class="mb-4 rounded-lg bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">{{ selectedPlan.success_indicator }}</div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Rating Period</label>
              <select v-model="form.period" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-2">
                <option value="">Select period</option>
                <option value="Jan-Jun">January - June</option>
                <option value="Jul-Dec">July - December</option>
              </select>
              <div class="flex justify-end mt-4">
                <button class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">Save</button>
              </div>
            </form>

            <!-- ACCOMPLISHMENT FORM -->
            <form v-if="modalMode === 'accomplishment' && selectedPlan" @submit.prevent="submitAccomplishment">
              <div class="mb-4 rounded-lg bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">{{ selectedPlan.success_indicator }}</div>
              <div class="space-y-3">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Actual Accomplishment</label>
                  <textarea v-model="form.accomplishment" rows="3" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" placeholder="Actual Accomplishment"></textarea>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Link to MOVs</label>
                  <input v-model="form.mov_link" type="text" placeholder="https://..." class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div class="grid grid-cols-3 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Quality</label>
                    <input v-model.number="form.self_quality" type="number" min="1" max="5" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Efficiency</label>
                    <input v-model.number="form.self_efficiency" type="number" min="1" max="5" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Timeliness</label>
                    <input v-model.number="form.self_timeliness" type="number" min="1" max="5" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  </div>
                </div>
              </div>
              <div class="flex justify-end mt-4">
                <button class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">Save</button>
              </div>
            </form>

          </div>
        </div>
      </div>

      <!-- MODAL: EXPORT META -->
      <div v-if="showExportMetaModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Export IPCR — Employee Info</h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors" @click="showExportMetaModal=false">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="px-6 py-5 space-y-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Employee Name</label>
              <input v-model="meta.personName" type="text" placeholder="Employee Name" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Position</label>
              <input v-model="meta.position" type="text" placeholder="Position" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Office / Division</label>
              <input v-model="meta.office" type="text" placeholder="Office / Division" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Rating Period</label>
              <input v-model="meta.period" type="text" placeholder="Rating Period" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors" @click="showExportMetaModal=false">Cancel</button>
            <button class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors" @click="() => { exportAllHTML(meta); showExportMetaModal=false }">Export HTML</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
