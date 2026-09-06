<script setup>
import { computed } from "vue"
import { Head, Link } from "@inertiajs/vue3"
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
import { PencilSquareIcon, DocumentArrowDownIcon, ArrowLeftIcon } from "@heroicons/vue/24/outline"
import { useOpcr } from "@/Composables/useOpcr.js"
import Multiselect from "vue-multiselect"
import "vue-multiselect/dist/vue-multiselect.css"

const props = defineProps({
  indicators: { type: Array, default: () => [] },
  fiscalYears: { type: Array, default: () => [] },
  selectedFiscalYear: { type: [String, Number], default: "" },
  currentFiscalYear: { type: Number, default: null },
  settings: { type: Object, default: () => ({}) },
  agencyOutcomes: { type: Array, default: () => [] },
  divisions: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
})

const {
  groupedIndicators,
  showIndicatorModal,
  indicatorForm,
  openIndicatorModal,
  closeIndicatorModal,
  submitIndicator,
  updateActual,
  updateRating,
  updateAccomplishment,
  isPropagatedIndicator,
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
          </template>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <Link :href="route('opcr.index')" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-indigo-600">
          <ArrowLeftIcon class="w-4 h-4" /> All Fiscal Years
        </Link>
        <FiscalYearFilter :fiscal-years="fiscalYears" :selected="selectedFiscalYear" route-name="opcr.index" />
      </AppFilterBar>

      <p class="text-xs italic font-semibold text-slate-600">
        AGENCY ORGANIZATIONAL OUTCOME: Increased Competitiveness of Filipinos in Science and Engineering
      </p>

      <AppTable :is-empty="indicators.length === 0" :skeleton-cols="11">
        <template #head>
          <tr>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Indicator</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Target</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Budget</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Division</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q1</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q2</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q3</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q4</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Accomplishment</th>
            <th class="px-3 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Rating (Q/E/T/A)</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Remarks</th>
            <th v-if="canManage" class="px-3 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Action</th>
          </tr>
        </template>

        <template v-for="(rows, programName) in groupedIndicators" :key="programName">
          <tr class="bg-indigo-50/60">
            <td :colspan="canManage ? 12 : 11" class="px-3 py-1.5 text-xs font-semibold text-indigo-700">{{ programName }}</td>
          </tr>
          <tr v-for="indicator in rows" :key="indicator.id" class="hover:bg-indigo-50/40">
            <td class="px-3 py-2 text-sm text-slate-700 align-top">
              {{ indicator.description }}
              <span v-if="!isSpecificYear" class="block text-[10px] text-slate-400">FY {{ indicator.fiscal_year }}</span>
            </td>
            <td class="px-3 py-2 text-sm text-slate-700 align-top">{{ indicator.target ?? '—' }}</td>
            <td class="px-3 py-2 text-sm text-slate-700 align-top">
              {{ indicator.budget !== null && indicator.budget !== undefined ? Number(indicator.budget).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—' }}
            </td>
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
              <textarea
                v-if="canManage"
                :value="indicator.accomplishment ?? indicator.accomplishment_summary ?? ''"
                rows="1"
                class="w-40 rounded border border-slate-200 px-1 py-0.5 text-xs"
                @change="updateAccomplishment(indicator, $event.target.value)"
              />
              <span v-else class="text-xs text-slate-500">{{ indicator.displayed_accomplishment ?? '—' }}</span>
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
            <td class="px-3 py-2 text-xs text-slate-500 align-top">{{ indicator.remarks ?? '—' }}</td>
            <td v-if="canManage" class="px-3 py-2 text-center align-top">
              <AppIconButton label="Edit" @click="openIndicatorModal(indicator)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
            </td>
          </tr>
        </template>

        <template #empty>
          <EmptyState title="No OPCR indicators yet" subtitle="Tag a Performance Indicator to a PSHS Program (Strategic Functions) — it will appear here automatically." />
        </template>
      </AppTable>

      <!-- Indicator edit modal — DOST tagging + remarks only; Program,
           Description, Target, Budget, and Divisions are mirrored from the
           source Performance Indicator and edited there instead. -->
      <AppModal :show="showIndicatorModal" title="Edit Indicator" size="lg" @close="closeIndicatorModal">
        <form id="opcr-indicator-form" @submit.prevent="submitIndicator" class="space-y-4">
          <p v-if="isPropagatedIndicator" class="rounded-lg bg-indigo-50 px-3 py-2 text-xs text-indigo-700">
            This indicator is synced from a Performance Indicator. Fiscal Year, Program, Description, Target, Budget, and Divisions are read-only here — edit them on the source Performance Indicator instead.
          </p>

          <AppInput v-model="indicatorForm.fiscal_year" label="Fiscal Year" type="number" required :disabled="isPropagatedIndicator" />

          <AppSelect v-model="indicatorForm.agency_outcome_id" label="PSHS Program" required placeholder="-- Select Program --" :disabled="isPropagatedIndicator">
            <option v-for="outcome in agencyOutcomes" :key="outcome.id" :value="outcome.id">{{ outcome.outcome }}</option>
          </AppSelect>

          <p class="text-xs text-slate-500">
            DOST Pillar/Strategy/Sub-Strategy alignment is tagged on the Program itself, in the DOST Strategic Plan module — it's not set per-indicator here.
          </p>

          <AppTextarea v-model="indicatorForm.description" label="Indicator Description" :rows="2" required :disabled="isPropagatedIndicator" />
          <AppInput v-model="indicatorForm.target" label="Target" type="text" :disabled="isPropagatedIndicator" />
          <AppInput v-model="indicatorForm.budget" label="Budget" type="number" min="0" step="0.01" :disabled="isPropagatedIndicator" />

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Division(s) Accountable</label>
            <Multiselect
              v-model="indicatorForm.divisions"
              :options="divisions"
              :multiple="true"
              :close-on-select="false"
              :disabled="isPropagatedIndicator"
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
