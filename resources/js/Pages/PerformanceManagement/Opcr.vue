<script setup>
import { computed } from "vue"
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppTable from "@/Components/AppTable.vue"
import AppButton from "@/Components/AppButton.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppModal from "@/Components/AppModal.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import EmptyState from "@/Components/EmptyState.vue"
import FiscalYearFilter from "@/Components/FiscalYearFilter.vue"
import DostChainWizard from "@/Components/DostChainWizard.vue"
import { PencilSquareIcon, TrashIcon, PlusIcon, DocumentArrowDownIcon } from "@heroicons/vue/24/outline"
import { useOpcr } from "@/Composables/useOpcr.js"
import { dostAlignmentLabel } from "@/Utils/OPCR/opcrGrouping.js"
import Multiselect from "vue-multiselect"
import "vue-multiselect/dist/vue-multiselect.css"

const props = defineProps({
  indicators: { type: Array, default: () => [] },
  fiscalYears: { type: Array, default: () => [] },
  selectedFiscalYear: { type: [String, Number], default: "" },
  currentFiscalYear: { type: Number, default: null },
  settings: { type: Object, default: () => ({}) },
  pillars: { type: Array, default: () => [] },
  agencyOutcomes: { type: Array, default: () => [] },
  performanceIndicators: { type: Array, default: () => [] },
  divisions: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
})

const {
  groupedIndicators,
  showIndicatorModal,
  indicatorModalMode,
  indicatorForm,
  openIndicatorModal,
  closeIndicatorModal,
  submitIndicator,
  deleteIndicator,
  updateActual,
  updateRating,
  showChainWizardPanel,
  toggleChainWizardPanel,
  onChainWizardCreated,
  showSettingsModal,
  settingsForm,
  openSettingsModal,
  closeSettingsModal,
  submitSettings,
  showCloneModal,
  cloneForm,
  openCloneModal,
  closeCloneModal,
  submitClone,
} = useOpcr(props)

const isSpecificYear = computed(() => props.selectedFiscalYear !== "all")

const ratingFields = ["rating_quality", "rating_efficiency", "rating_timeliness", "rating_average"]

const ratingPayload = (indicator, overrideField, overrideValue) => {
  const payload = {}
  for (const field of ratingFields) {
    payload[field] = field === overrideField ? (overrideValue || null) : indicator[field]
  }
  return payload
}
</script>

<template>
  <Head title="OPCR" />
  <AdminLayout title="Office Performance Commitment and Review (OPCR)">
    <div class="space-y-5">
      <AppPageHeader
        title="OPCR"
        :subtitle="isSpecificYear ? `FY ${selectedFiscalYear}` : 'All fiscal years'"
      >
        <template #actions>
          <AppButton v-if="isSpecificYear" variant="secondary" as="a" :href="route('opcr.pdf', selectedFiscalYear)" target="_blank">
            <DocumentArrowDownIcon class="w-4 h-4" /> Export PDF
          </AppButton>
          <template v-if="canManage">
            <AppButton variant="secondary" @click="openSettingsModal">Edit Signatories</AppButton>
            <AppButton v-if="isSpecificYear" variant="secondary" @click="openCloneModal">Clone from FY —</AppButton>
            <AppButton @click="openIndicatorModal('create')">
              <PlusIcon class="w-4 h-4" /> New Indicator
            </AppButton>
          </template>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <FiscalYearFilter :fiscal-years="fiscalYears" :selected="selectedFiscalYear" route-name="opcr.index" />
      </AppFilterBar>

      <AppTable :is-empty="indicators.length === 0" :skeleton-cols="9">
        <template #head>
          <tr>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Indicator</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Target</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">DOST Alignment</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Division</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q1</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q2</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q3</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q4</th>
            <th class="px-3 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Rating (Q/E/T/A)</th>
            <th v-if="canManage" class="px-3 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Action</th>
          </tr>
        </template>

        <template v-for="(rows, programName) in groupedIndicators" :key="programName">
          <tr class="bg-indigo-50/60">
            <td :colspan="canManage ? 10 : 9" class="px-3 py-1.5 text-xs font-semibold text-indigo-700">{{ programName }}</td>
          </tr>
          <tr v-for="indicator in rows" :key="indicator.id" class="hover:bg-indigo-50/40">
            <td class="px-3 py-2 text-sm text-slate-700 align-top">
              {{ indicator.description }}
              <span v-if="!isSpecificYear" class="block text-[10px] text-slate-400">FY {{ indicator.fiscal_year }}</span>
            </td>
            <td class="px-3 py-2 text-sm text-slate-700 align-top">{{ indicator.target ?? '—' }}</td>
            <td class="px-3 py-2 text-xs text-slate-500 align-top">{{ dostAlignmentLabel(indicator) ?? '—' }}</td>
            <td class="px-3 py-2 text-sm text-slate-700 align-top">
              {{ indicator.divisions?.map(d => d.acronym ?? d.division_name).join(', ') || '—' }}
            </td>
            <td v-for="q in [1, 2, 3, 4]" :key="q" class="px-3 py-2 align-top">
              <input
                v-if="canManage"
                :value="indicator.actuals?.find(a => a.quarter === q)?.value ?? ''"
                class="w-16 rounded border border-slate-200 px-1 py-0.5 text-xs"
                @change="updateActual(indicator, q, $event.target.value)"
              />
              <span v-else class="text-sm text-slate-700">{{ indicator.actuals?.find(a => a.quarter === q)?.value ?? '—' }}</span>
            </td>
            <td class="px-3 py-2 align-top">
              <div v-if="canManage" class="flex gap-1">
                <input
                  v-for="field in ratingFields"
                  :key="field"
                  :value="indicator[field] ?? ''"
                  type="number" min="1" max="5" step="0.01"
                  class="w-12 rounded border border-slate-200 px-1 py-0.5 text-xs"
                  @change="updateRating(indicator, ratingPayload(indicator, field, $event.target.value))"
                />
              </div>
              <span v-else class="text-sm text-slate-700">
                {{ [indicator.rating_quality, indicator.rating_efficiency, indicator.rating_timeliness, indicator.rating_average].map(v => v ?? '—').join(' / ') }}
              </span>
            </td>
            <td v-if="canManage" class="px-3 py-2 text-center align-top">
              <div class="flex items-center justify-center gap-1">
                <AppIconButton label="Edit" @click="openIndicatorModal('edit', indicator)">
                  <PencilSquareIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton label="Delete" variant="danger" @click="deleteIndicator(indicator)">
                  <TrashIcon class="w-4 h-4" />
                </AppIconButton>
              </div>
            </td>
          </tr>
        </template>

        <template #empty>
          <EmptyState title="No OPCR indicators yet" subtitle="Add an indicator for this fiscal year, or clone from a previous one." />
        </template>
      </AppTable>

      <!-- Indicator create/edit modal -->
      <AppModal :show="showIndicatorModal" :title="indicatorModalMode === 'create' ? 'New Indicator' : 'Edit Indicator'" size="lg" @close="closeIndicatorModal">
        <form id="opcr-indicator-form" @submit.prevent="submitIndicator" class="space-y-4">
          <AppInput v-model="indicatorForm.fiscal_year" label="Fiscal Year" type="number" required />

          <AppSelect v-model="indicatorForm.agency_outcome_id" label="PSHS Program" required placeholder="-- Select Program --">
            <option v-for="outcome in agencyOutcomes" :key="outcome.id" :value="outcome.id">{{ outcome.outcome }}</option>
          </AppSelect>

          <AppSelect v-model="indicatorForm.dost_sub_strategy_id" label="Sub-Strategy (optional alignment)" placeholder="-- None --">
            <optgroup v-for="pillar in pillars" :key="pillar.id" :label="pillar.name">
              <optgroup v-for="strategy in pillar.strategies" :key="strategy.id" :label="strategy.name">
                <option v-for="sub in strategy.sub_strategies" :key="sub.id" :value="sub.id">{{ sub.description }}</option>
              </optgroup>
            </optgroup>
          </AppSelect>

          <button type="button" class="text-xs text-indigo-600 hover:underline" @click="toggleChainWizardPanel">
            {{ showChainWizardPanel ? '− Hide' : '+ Create new tagging chain' }}
          </button>
          <div v-if="showChainWizardPanel" class="rounded-lg bg-slate-50 p-3">
            <DostChainWizard
              :pillars="pillars"
              :agency-outcomes="agencyOutcomes"
              submit-label="Create & Use"
              @created="onChainWizardCreated"
              @cancel="showChainWizardPanel = false"
            />
          </div>

          <AppSelect v-model="indicatorForm.performance_indicator_id" label="Link to an existing IPCR indicator (optional)" placeholder="-- None --">
            <option v-for="pi in performanceIndicators" :key="pi.id" :value="pi.id">{{ pi.description }}</option>
          </AppSelect>

          <AppTextarea v-model="indicatorForm.description" label="Indicator Description" :rows="2" required />
          <AppInput v-model="indicatorForm.target" label="Target" type="text" />
          <AppInput v-model="indicatorForm.budget" label="Budget" type="number" min="0" step="0.01" />

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Division(s) Accountable</label>
            <Multiselect
              v-model="indicatorForm.divisions"
              :options="divisions"
              :multiple="true"
              :close-on-select="false"
              label="division_name"
              track-by="id"
              placeholder="Select one or more divisions"
            />
          </div>

          <AppTextarea v-model="indicatorForm.remarks" label="Remarks" :rows="2" />
        </form>

        <template #footer>
          <AppButton variant="secondary" @click="closeIndicatorModal">Cancel</AppButton>
          <AppButton type="submit" form="opcr-indicator-form">Save</AppButton>
        </template>
      </AppModal>

      <!-- Signatories settings modal -->
      <AppModal :show="showSettingsModal" title="OPCR Signatories" size="md" @close="closeSettingsModal">
        <form id="opcr-settings-form" @submit.prevent="submitSettings" class="space-y-4">
          <AppInput v-model="settingsForm.campus_director_name" label="Campus Director Name" type="text" />
          <AppInput v-model="settingsForm.oic_campus_director_name" label="OIC-Campus Director Name" type="text" />
          <AppInput v-model="settingsForm.executive_director_name" label="Executive Director Name" type="text" />
          <AppTextarea v-model="settingsForm.commitment_statement" label="Commitment Statement (optional override)" :rows="3" />
        </form>
        <template #footer>
          <AppButton variant="secondary" @click="closeSettingsModal">Cancel</AppButton>
          <AppButton type="submit" form="opcr-settings-form">Save</AppButton>
        </template>
      </AppModal>

      <!-- Clone modal -->
      <AppModal :show="showCloneModal" title="Clone from a previous FY" size="sm" @close="closeCloneModal">
        <form id="opcr-clone-form" @submit.prevent="submitClone(selectedFiscalYear)" class="space-y-4">
          <AppSelect v-model="cloneForm.source_fiscal_year" label="Source Fiscal Year" required placeholder="-- Select --">
            <option v-for="y in fiscalYears.filter(y => String(y) !== String(selectedFiscalYear))" :key="y" :value="y">FY {{ y }}</option>
          </AppSelect>
        </form>
        <template #footer>
          <AppButton variant="secondary" @click="closeCloneModal">Cancel</AppButton>
          <AppButton type="submit" form="opcr-clone-form">Clone</AppButton>
        </template>
      </AppModal>
    </div>
  </AdminLayout>
</template>
