<script setup>
import { ref, computed } from "vue"
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppTable from "@/Components/AppTable.vue"
import AppButton from "@/Components/AppButton.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppModal from "@/Components/AppModal.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import EmptyState from "@/Components/EmptyState.vue"
import { PencilSquareIcon, TrashIcon, PlusIcon, DocumentArrowDownIcon } from "@heroicons/vue/24/outline"
import { useOpcr } from "@/Composables/useOpcr.js"
import Multiselect from "vue-multiselect"
import "vue-multiselect/dist/vue-multiselect.css"

const props = defineProps({
  period: Object,
  periods: { type: Array, default: () => [] },
  indicators: { type: Array, default: () => [] },
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
  newPillarName,
  addPillar,
  newStrategy,
  addStrategy,
  newSubStrategy,
  addSubStrategy,
  newProgram,
  addProgram,
  showPeriodModal,
  periodForm,
  openPeriodModal,
  closePeriodModal,
  submitPeriod,
  showCloneModal,
  cloneForm,
  openCloneModal,
  closeCloneModal,
  submitClone,
} = useOpcr(props)

const showAddPillar = ref(false)
const showAddStrategy = ref(false)
const showAddSubStrategy = ref(false)
const showAddProgram = ref(false)

const allStrategies = computed(() => props.pillars.flatMap((p) => p.strategies ?? []))

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
        :subtitle="period ? `FY ${period.fiscal_year} — ${period.period_label}` : 'No current OPCR period set up yet.'"
      >
        <template #actions>
          <AppButton v-if="period" variant="secondary" as="a" :href="route('opcr-periods.pdf', period.id)" target="_blank">
            <DocumentArrowDownIcon class="w-4 h-4" /> Export PDF
          </AppButton>
          <template v-if="canManage">
            <AppButton variant="secondary" @click="openCloneModal">Clone from FY —</AppButton>
            <AppButton variant="secondary" @click="openPeriodModal(period)">{{ period ? 'Edit Period' : 'New FY' }}</AppButton>
            <AppButton @click="openIndicatorModal('create')" :disabled="!period">
              <PlusIcon class="w-4 h-4" /> New Indicator
            </AppButton>
          </template>
        </template>
      </AppPageHeader>

      <AppTable :is-empty="indicators.length === 0" :skeleton-cols="9">
        <template #head>
          <tr>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Pillar / Strategy / Sub-Strategy</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Indicator</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Target</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Division</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q1</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q2</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q3</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Q4</th>
            <th class="px-3 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Rating (Q/E/T/A)</th>
            <th v-if="canManage" class="px-3 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Action</th>
          </tr>
        </template>

        <template v-for="(strategies, pillarName) in groupedIndicators" :key="pillarName">
          <template v-for="(subStrategies, strategyName) in strategies" :key="strategyName">
            <template v-for="(rows, subStrategyName) in subStrategies" :key="subStrategyName">
              <tr v-for="(indicator, idx) in rows" :key="indicator.id" class="hover:bg-indigo-50/40">
                <td class="px-3 py-2 text-xs text-slate-600 align-top">
                  <template v-if="idx === 0">
                    <div class="font-medium text-slate-700">{{ pillarName }}</div>
                    <div>{{ strategyName }}</div>
                    <div class="text-slate-400">{{ subStrategyName }}</div>
                  </template>
                </td>
                <td class="px-3 py-2 text-sm text-slate-700 align-top">{{ indicator.description }}</td>
                <td class="px-3 py-2 text-sm text-slate-700 align-top">{{ indicator.target ?? '—' }}</td>
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
          </template>
        </template>

        <template #empty>
          <EmptyState title="No OPCR indicators yet" subtitle="Set up a current FY period, then add indicators." />
        </template>
      </AppTable>

      <!-- Indicator create/edit modal -->
      <AppModal :show="showIndicatorModal" :title="indicatorModalMode === 'create' ? 'New Indicator' : 'Edit Indicator'" size="lg" @close="closeIndicatorModal">
        <form id="opcr-indicator-form" @submit.prevent="submitIndicator" class="space-y-4">
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-medium text-slate-600">Pillar</label>
              <button type="button" class="text-xs text-indigo-600 hover:underline" @click="showAddPillar = !showAddPillar">+ Add new</button>
            </div>
            <div v-if="showAddPillar" class="flex gap-2 mb-2">
              <AppInput v-model="newPillarName" type="text" placeholder="New Pillar name" class="flex-1" />
              <AppButton type="button" variant="secondary" @click="addPillar(); showAddPillar = false">Add</AppButton>
            </div>

            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-medium text-slate-600">Strategy</label>
              <button type="button" class="text-xs text-indigo-600 hover:underline" @click="showAddStrategy = !showAddStrategy">+ Add new</button>
            </div>
            <div v-if="showAddStrategy" class="flex gap-2 mb-2">
              <AppSelect v-model="newStrategy.dost_pillar_id" placeholder="Pillar">
                <option v-for="p in pillars" :key="p.id" :value="p.id">{{ p.name }}</option>
              </AppSelect>
              <AppInput v-model="newStrategy.name" type="text" placeholder="New Strategy name" class="flex-1" />
              <AppButton type="button" variant="secondary" @click="addStrategy(); showAddStrategy = false">Add</AppButton>
            </div>

            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-medium text-slate-600">Sub-Strategy (this is what gets tagged)</label>
              <button type="button" class="text-xs text-indigo-600 hover:underline" @click="showAddSubStrategy = !showAddSubStrategy">+ Add new</button>
            </div>
            <div v-if="showAddSubStrategy" class="flex gap-2 mb-2">
              <AppSelect v-model="newSubStrategy.dost_strategy_id" placeholder="Strategy">
                <option v-for="s in allStrategies" :key="s.id" :value="s.id">{{ s.name }}</option>
              </AppSelect>
              <AppInput v-model="newSubStrategy.description" type="text" placeholder="New Sub-Strategy description" class="flex-1" />
              <AppButton type="button" variant="secondary" @click="addSubStrategy(); showAddSubStrategy = false">Add</AppButton>
            </div>

            <AppSelect v-model="indicatorForm.dost_sub_strategy_id" placeholder="-- None --">
              <optgroup v-for="pillar in pillars" :key="pillar.id" :label="pillar.name">
                <optgroup v-for="strategy in pillar.strategies" :key="strategy.id" :label="strategy.name">
                  <option v-for="sub in strategy.sub_strategies" :key="sub.id" :value="sub.id">{{ sub.description }}</option>
                </optgroup>
              </optgroup>
            </AppSelect>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-medium text-slate-600">PSHS Program (Agency Outcome)</label>
              <button type="button" class="text-xs text-indigo-600 hover:underline" @click="showAddProgram = !showAddProgram">+ Add new</button>
            </div>
            <div v-if="showAddProgram" class="flex gap-2 mb-2">
              <AppInput v-model="newProgram.outcome" type="text" placeholder="Program name, e.g. E. New Program" class="flex-1" />
              <AppSelect v-model="newProgram.function_type" placeholder="Function type">
                <option value="Strategic Functions">Strategic Functions</option>
                <option value="Core Functions">Core Functions</option>
                <option value="Support Functions">Support Functions</option>
              </AppSelect>
              <AppButton type="button" variant="secondary" @click="addProgram(); showAddProgram = false">Add</AppButton>
            </div>
            <AppSelect v-model="indicatorForm.agency_outcome_id" placeholder="-- None --">
              <option v-for="outcome in agencyOutcomes" :key="outcome.id" :value="outcome.id">{{ outcome.outcome }}</option>
            </AppSelect>
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

      <!-- Period create/edit modal -->
      <AppModal :show="showPeriodModal" title="OPCR Period" size="md" @close="closePeriodModal">
        <form id="opcr-period-form" @submit.prevent="submitPeriod" class="space-y-4">
          <AppInput v-model="periodForm.fiscal_year" label="Fiscal Year" type="number" required />
          <AppInput v-model="periodForm.period_label" label="Period Label" type="text" placeholder="January - December 2026" required />
          <AppInput v-model="periodForm.campus_director_name" label="Campus Director Name" type="text" />
          <AppInput v-model="periodForm.oic_campus_director_name" label="OIC-Campus Director Name" type="text" />
          <AppInput v-model="periodForm.executive_director_name" label="Executive Director Name" type="text" />
          <AppTextarea v-model="periodForm.commitment_statement" label="Commitment Statement (optional override)" :rows="3" />
          <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" v-model="periodForm.is_current" />
            Make this the current OPCR period
          </label>
        </form>
        <template #footer>
          <AppButton variant="secondary" @click="closePeriodModal">Cancel</AppButton>
          <AppButton type="submit" form="opcr-period-form">Save</AppButton>
        </template>
      </AppModal>

      <!-- Clone modal -->
      <AppModal :show="showCloneModal" title="Clone from a previous FY" size="sm" @close="closeCloneModal">
        <form id="opcr-clone-form" @submit.prevent="submitClone(period.id)" class="space-y-4">
          <AppSelect v-model="cloneForm.source_period_id" label="Source Period" required placeholder="-- Select --">
            <option v-for="p in periods.filter(p => p.id !== period?.id)" :key="p.id" :value="p.id">FY {{ p.fiscal_year }} — {{ p.period_label }}</option>
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
