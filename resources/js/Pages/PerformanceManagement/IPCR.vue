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
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">My IPCR Plans</h1>

        <div class="flex flex-wrap items-center gap-3">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search plans..."
            class="w-72 rounded-lg border-gray-300 shadow-sm px-3 py-2"
          />

          <button
            class="bg-indigo-600 text-white px-4 py-2 rounded flex items-center gap-2"
            @click="showExportMetaModal = true"
          >
            <PrinterIcon class="w-4 h-4" /> CSC Format
          </button>

          <button
            class="bg-green-600 text-white px-4 py-2 rounded flex items-center gap-2"
            @click="exportAllExcel()"
          >
            <ArrowDownTrayIcon class="w-4 h-4" /> Excel
          </button>

          <!-- CREATE NEW IPCR TARGET -->
          <button
            class="bg-blue-600 text-white px-4 py-2 rounded flex items-center gap-2"
            @click="openCreateModal()"
          >
            <PlusIcon class="w-4 h-4" /> Create new IPCR Target
          </button>
        </div>
      </div>

      <!-- TABLE -->
      <div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
        <table class="min-w-full border border-black">
          <thead class="bg-gray-100 text-gray-700 text-sm uppercase">
            <tr>
              <th rowspan="2" colspan="2" class="border px-2 py-2 text-center">Output</th>
              <th rowspan="2" class="border px-2 py-2">Plan / Success Indicator</th>
              <th rowspan="2" class="border px-2 py-2">Target / Measure</th>
              <th rowspan="2" class="border px-2 py-2">Accomplishment</th>
              <th colspan="4" class="border px-2 py-2 text-center">Ratings</th>
              <th rowspan="2" class="border px-2 py-2">Status</th>
              <th rowspan="2" class="border px-2 py-2 text-center">Action</th>
              <th rowspan="2" class="border px-2 py-2 text-center">MOVs</th>
            </tr>
            <tr>
              <th class="border px-2 py-2">Q</th>
              <th class="border px-2 py-2">E</th>
              <th class="border px-2 py-2">T</th>
              <th class="border px-2 py-2">A</th>
            </tr>
          </thead>

          <tbody>
            <template v-for="(section, key) in groupedPlans" :key="key">
              <tr>
                <td colspan="12" class="bg-gray-200 font-semibold px-2 py-1">{{ key }}</td>
              </tr>

              <tr v-for="(plan, index) in section" :key="plan.id">
                <td v-if="shouldShowSubOutcome(section, index)"
                    :rowspan="countSubOutcomeRows(section, plan.performance_indicator.agency_outcome.sub_outcome)"
                    class="border px-2 py-1">
                  {{ plan.performance_indicator.agency_outcome.sub_outcome ?? '—' }}
                </td>

                <td v-if="shouldShowPI(section, index)"
                    :rowspan="countPIRows(section, plan.performance_indicator.description)"
                    class="border px-2 py-1 align-top">
                  {{ plan.performance_indicator?.description ?? '—' }}
                </td>

                <td class="border px-2 py-1">{{ plan.success_indicator }}</td>
                <td class="border px-2 py-1">{{ plan.ipcrs[0]?.target ?? '—' }}</td>
                <td class="border px-2 py-1">{{ plan.ipcrs[0]?.accomplishment ?? '—' }}</td>
                <td class="border px-2 py-1">{{ plan.ipcrs[0]?.self_quality ?? '—' }}</td>
                <td class="border px-2 py-1">{{ plan.ipcrs[0]?.self_efficiency ?? '—' }}</td>
                <td class="border px-2 py-1">{{ plan.ipcrs[0]?.self_timeliness ?? '—' }}</td>
                <td class="border px-2 py-1">{{ plan.ipcrs[0]?.self_rating ?? '—' }}</td>
                <td class="border px-2 py-1">{{ plan.ipcrs[0]?.target_status ?? 'draft' }}</td>

                <td class="border px-2 py-1 text-center space-x-1">
                  <!-- Add target (single plan) if no ipcr yet -->
                  <button v-if="!plan.ipcrs.length" @click="openModal('target', plan)" class="text-green-600" title="Add target">
                    <PlusIcon class="w-5 h-5 inline-block"/>
                  </button>

                  <!-- Add accomplishment -->
                  <button v-if="plan.ipcrs[0]?.target_status === 'approved' && !plan.ipcrs[0]?.accomplishment"
                          @click="openModal('accomplishment', plan)"
                          class="text-yellow-600" title="Add accomplishment">
                    <PencilSquareIcon class="w-5 h-5 inline-block"/>
                  </button>

                  <!-- Division Chief actions -->
                  <div v-if="props.currentUserIsChief && plan.ipcrs[0]">
                    <button v-if="plan.ipcrs[0]?.target_status === 'submitted'" @click="approveIPCR(plan.ipcrs[0])" class="text-blue-600 ml-2" title="Approve target">Approve</button>
                    <button v-if="plan.ipcrs[0]?.target_status !== 'approved'" @click="removeIPCR(plan.ipcrs[0])" class="text-red-600 ml-2" title="Remove target">Remove</button>
                  </div>
                </td>

                <td class="border px-2 py-1">
                  <a :href="plan.ipcrs[0]?.mov_link" target="_blank" class="text-blue-600 underline" v-if="plan.ipcrs[0]?.mov_link">View MOVs</a>
                  <span v-else>—</span>
                </td>
              </tr>
            </template>

            <tr v-if="filteredPlans.length === 0">
              <td colspan="12" class="px-4 py-6 text-center text-gray-500">No plans found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- MODAL: CREATE / TARGET / ACCOMPLISHMENT -->
      <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative">
          <button class="absolute top-3 right-3 text-gray-600" @click="closeModal">✕</button>

          <h2 class="text-xl font-semibold mb-4">
            {{
              modalMode === "target" ? "Submit Target" :
              modalMode === "accomplishment" ? "Submit Accomplishment" :
              modalMode === "create" ? "Create IPCR Targets (Bulk)" : "View Plan"
            }}
          </h2>

          <!-- CREATE (BULK) -->
          <form v-if="modalMode === 'create'" @submit.prevent="submitBulkCreateTargets">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block mb-1">Rating Period</label>
                <select v-model="createModalState.period" class="w-full border rounded-lg p-2">
                  <option value="">Select period</option>
                  <option value="Jan-Jun">January - June</option>
                  <option value="Jul-Dec">July - December</option>
                </select>
              </div>

              <div>
                <label class="block mb-1">Year</label>
                <input type="number" v-model.number="createModalState.year" min="2000" class="w-full border rounded-lg p-2" />
              </div>
            </div>
            
            <div class="mt-4">
              <label class="block mb-2">Search Plans to add</label>
              <input v-model="createModalState.search" type="text" placeholder="Search..." class="w-full border rounded-lg p-2" />
              <div class="max-h-48 overflow-auto border rounded mt-2 p-2">
                <div v-for="plan in filteredPlans" :key="'create-'+plan.id" class="flex items-start gap-2 py-2">
                  <input type="checkbox" :id="'plan-'+plan.id" :checked="isPlanSelected(plan.id)" @change="togglePlanSelection(plan)" />
                  <label :for="'plan-'+plan.id" class="flex-1">
                    <div class="font-semibold">{{ plan.success_indicator }}</div>
                    <div class="text-sm text-gray-500">{{ plan.performance_indicator?.description }}</div>
                    <div class="text-sm text-gray-500">{{ plan.office_involved }}</div>
                  </label>
                </div>
                <div v-if="filteredPlans.length===0" class="text-sm text-gray-500">No plans to choose from.</div>
              </div>
              </div>
            <div class="flex justify-end mt-4">
              <button type="button" class="bg-gray-300 text-black px-4 py-2 rounded mr-2" @click="closeModal">Cancel</button>
              <button class="bg-blue-600 text-white px-4 py-2 rounded">Submit Targets</button>
            </div>
          </form>

          <!-- TARGET FORM (single plan) -->
          <form v-if="modalMode === 'target' && selectedPlan" @submit.prevent="submitTarget">
            <div class="mb-2"><strong>{{ selectedPlan.success_indicator }}</strong></div>
            <label class="block mb-1">Rating Period</label>
            <select v-model="form.period" class="w-full border rounded-lg p-2 mb-2">
              <option value="">Select period</option>
              <option value="Jan-Jun">January - June</option>
              <option value="Jul-Dec">July - December</option>
            </select>
            <div class="flex justify-end mt-4">
              <button class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
            </div>
          </form>

          <!-- ACCOMPLISHMENT FORM -->
          <form v-if="modalMode === 'accomplishment' && selectedPlan" @submit.prevent="submitAccomplishment">
            <div class="mb-2"><strong>{{ selectedPlan.success_indicator }}</strong></div>
            <textarea v-model="form.accomplishment" rows="3" class="w-full border rounded-lg p-2" placeholder="Actual Accomplishment"></textarea>
            <input v-model="form.mov_link" type="text" placeholder="Link to MOVs" class="w-full border rounded-lg p-2 mt-2" />
            <div class="grid grid-cols-3 gap-3 mt-3">
              <div>
                <label>Quality</label>
                <input v-model.number="form.self_quality" type="number" min="1" max="5" class="w-full border rounded-lg p-2" />
              </div>
              <div>
                <label>Efficiency</label>
                <input v-model.number="form.self_efficiency" type="number" min="1" max="5" class="w-full border rounded-lg p-2" />
              </div>
              <div>
                <label>Timeliness</label>
                <input v-model.number="form.self_timeliness" type="number" min="1" max="5" class="w-full border rounded-lg p-2" />
              </div>
            </div>

            <div class="flex justify-end mt-4">
              <button class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
            </div>
          </form>
        </div>
      </div>

      <!-- MODAL: EXPORT META -->
      <div v-if="showExportMetaModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
          <button class="absolute top-3 right-3 text-gray-600" @click="showExportMetaModal=false">✕</button>
          <h2 class="text-xl font-semibold mb-4">Export IPCR - Employee Info</h2>
          <div class="space-y-3">
            <input v-model="meta.personName" type="text" placeholder="Employee Name" class="w-full border rounded-lg p-2" />
            <input v-model="meta.position" type="text" placeholder="Position" class="w-full border rounded-lg p-2" />
            <input v-model="meta.office" type="text" placeholder="Office / Division" class="w-full border rounded-lg p-2" />
            <input v-model="meta.period" type="text" placeholder="Rating Period" class="w-full border rounded-lg p-2" />
          </div>
          <div class="flex justify-end mt-4 space-x-2">
            <button class="bg-gray-400 text-white px-4 py-2 rounded" @click="showExportMetaModal=false">Cancel</button>
            <button class="bg-indigo-600 text-white px-4 py-2 rounded" @click="() => { exportAllHTML(meta); showExportMetaModal=false }">Export HTML</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
