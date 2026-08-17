<script setup>
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AppPageHeader from "@/Components/AppPageHeader.vue";
import AppButton from "@/Components/AppButton.vue";
import AppIconButton from "@/Components/AppIconButton.vue";
import AppBadge from "@/Components/AppBadge.vue";
import EmptyState from "@/Components/EmptyState.vue";
import {
  PlusIcon,
  PencilSquareIcon,
  TrashIcon,
  XMarkIcon,
  ChevronRightIcon,
} from "@heroicons/vue/24/outline";
import { useDostStrategicPlan } from "@/Composables/useDostStrategicPlan.js";

const props = defineProps({
  pillars: { type: Array, default: () => [] },
  agencyOutcomes: { type: Array, default: () => [] },
});

const {
  expandedPillars,
  expandedStrategies,
  togglePillar,
  toggleStrategy,
  showPillarModal,
  pillarModalMode,
  pillarForm,
  openPillarModal,
  closePillarModal,
  submitPillar,
  deletePillar,
  showStrategyModal,
  strategyModalMode,
  strategyForm,
  openStrategyModal,
  closeStrategyModal,
  submitStrategy,
  deleteStrategy,
  showSubStrategyModal,
  subStrategyModalMode,
  subStrategyForm,
  openSubStrategyModal,
  closeSubStrategyModal,
  submitSubStrategy,
  deleteSubStrategy,
} = useDostStrategicPlan();

const inputClass =
  "w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500";
</script>

<template>
  <Head title="DOST Strategic Plan" />
  <AdminLayout title="DOST Strategic Plan">
    <div class="space-y-5">
      <AppPageHeader
        title="DOST Strategic Plan"
        subtitle="Manage Pillar, Strategy, and Sub-Strategy alignment, linked to PSHS Programs (Agency Org Outcome)."
      >
        <template #actions>
          <AppButton @click="openPillarModal('create')">
            <PlusIcon class="w-4 h-4" /> New Pillar
          </AppButton>
        </template>
      </AppPageHeader>

      <EmptyState
        v-if="pillars.length === 0"
        title="No pillars yet"
        subtitle="Add a DOST Pillar to get started."
      />

      <div v-else class="space-y-3">
        <div
          v-for="pillar in pillars"
          :key="pillar.id"
          class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden"
        >
          <div
            class="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer hover:bg-slate-50"
            @click="togglePillar(pillar.id)"
          >
            <div class="flex items-center gap-2 min-w-0">
              <ChevronRightIcon
                :class="[
                  'w-4 h-4 text-slate-400 transition-transform shrink-0',
                  expandedPillars.has(pillar.id) ? 'rotate-90' : '',
                ]"
              />
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-800 truncate">{{ pillar.name }}</p>
                <p v-if="pillar.outcome_statement" class="text-xs text-slate-500 truncate">
                  {{ pillar.outcome_statement }}
                </p>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0" @click.stop>
              <AppIconButton label="New Strategy" @click="openStrategyModal('create', null, pillar)">
                <PlusIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit Pillar" variant="warning" @click="openPillarModal('edit', pillar)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete Pillar" variant="danger" @click="deletePillar(pillar)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>

          <div v-if="expandedPillars.has(pillar.id)" class="border-t border-slate-100 divide-y divide-slate-100">
            <EmptyState v-if="pillar.strategies.length === 0" title="No strategies under this pillar" />
            <div v-for="strategy in pillar.strategies" :key="strategy.id" class="pl-8 pr-4 py-3">
              <div class="flex items-center justify-between gap-3 cursor-pointer" @click="toggleStrategy(strategy.id)">
                <div class="flex items-center gap-2 min-w-0">
                  <ChevronRightIcon
                    :class="[
                      'w-3.5 h-3.5 text-slate-400 transition-transform shrink-0',
                      expandedStrategies.has(strategy.id) ? 'rotate-90' : '',
                    ]"
                  />
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-700 truncate">{{ strategy.name }}</p>
                    <AppBadge v-if="strategy.agency_outcome" color="indigo">{{ strategy.agency_outcome.outcome }}</AppBadge>
                    <AppBadge v-else color="slate">Unlinked</AppBadge>
                  </div>
                </div>
                <div class="flex items-center gap-1 shrink-0" @click.stop>
                  <AppIconButton label="New Sub-Strategy" @click="openSubStrategyModal('create', null, strategy)">
                    <PlusIcon class="w-4 h-4" />
                  </AppIconButton>
                  <AppIconButton label="Edit Strategy" variant="warning" @click="openStrategyModal('edit', strategy, pillar)">
                    <PencilSquareIcon class="w-4 h-4" />
                  </AppIconButton>
                  <AppIconButton label="Delete Strategy" variant="danger" @click="deleteStrategy(strategy)">
                    <TrashIcon class="w-4 h-4" />
                  </AppIconButton>
                </div>
              </div>

              <div v-if="expandedStrategies.has(strategy.id)" class="mt-2 pl-6 space-y-2">
                <EmptyState v-if="strategy.sub_strategies.length === 0" title="No sub-strategies yet" />
                <div
                  v-for="sub in strategy.sub_strategies"
                  :key="sub.id"
                  class="flex items-center justify-between gap-3 text-sm text-slate-600 bg-slate-50 rounded-lg px-3 py-2"
                >
                  <span class="truncate">{{ sub.description }}</span>
                  <div class="flex items-center gap-1 shrink-0">
                    <AppIconButton label="Edit Sub-Strategy" variant="warning" size="sm" @click="openSubStrategyModal('edit', sub, strategy)">
                      <PencilSquareIcon class="w-3.5 h-3.5" />
                    </AppIconButton>
                    <AppIconButton label="Delete Sub-Strategy" variant="danger" size="sm" @click="deleteSubStrategy(sub)">
                      <TrashIcon class="w-3.5 h-3.5" />
                    </AppIconButton>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pillar Modal -->
      <div v-if="showPillarModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{ pillarModalMode === "create" ? "New Pillar" : "Edit Pillar" }}
            </h2>
            <AppIconButton label="Close" @click="closePillarModal"><XMarkIcon class="w-4 h-4" /></AppIconButton>
          </div>
          <form @submit.prevent="submitPillar">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Pillar Name</label>
                <input v-model="pillarForm.name" type="text" :class="inputClass" required />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Outcome Statement</label>
                <textarea v-model="pillarForm.outcome_statement" rows="3" :class="inputClass" />
              </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <AppButton type="button" variant="secondary" @click="closePillarModal">Cancel</AppButton>
              <AppButton type="submit">Save</AppButton>
            </div>
          </form>
        </div>
      </div>

      <!-- Strategy Modal -->
      <div v-if="showStrategyModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{ strategyModalMode === "create" ? "New Strategy" : "Edit Strategy" }}
            </h2>
            <AppIconButton label="Close" @click="closeStrategyModal"><XMarkIcon class="w-4 h-4" /></AppIconButton>
          </div>
          <form @submit.prevent="submitStrategy">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Pillar</label>
                <select v-model="strategyForm.dost_pillar_id" :class="inputClass" required>
                  <option :value="null" disabled>Select a pillar</option>
                  <option v-for="pillar in pillars" :key="pillar.id" :value="pillar.id">{{ pillar.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">PSHS Program (Agency Org Outcome)</label>
                <select v-model="strategyForm.agency_outcome_id" :class="inputClass">
                  <option :value="null">Not yet linked</option>
                  <option v-for="outcome in agencyOutcomes" :key="outcome.id" :value="outcome.id">{{ outcome.outcome }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Strategy Name</label>
                <input v-model="strategyForm.name" type="text" :class="inputClass" required />
              </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <AppButton type="button" variant="secondary" @click="closeStrategyModal">Cancel</AppButton>
              <AppButton type="submit">Save</AppButton>
            </div>
          </form>
        </div>
      </div>

      <!-- Sub-Strategy Modal -->
      <div v-if="showSubStrategyModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{ subStrategyModalMode === "create" ? "New Sub-Strategy" : "Edit Sub-Strategy" }}
            </h2>
            <AppIconButton label="Close" @click="closeSubStrategyModal"><XMarkIcon class="w-4 h-4" /></AppIconButton>
          </div>
          <form @submit.prevent="submitSubStrategy">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <textarea v-model="subStrategyForm.description" rows="4" :class="inputClass" required />
              </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <AppButton type="button" variant="secondary" @click="closeSubStrategyModal">Cancel</AppButton>
              <AppButton type="submit">Save</AppButton>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
