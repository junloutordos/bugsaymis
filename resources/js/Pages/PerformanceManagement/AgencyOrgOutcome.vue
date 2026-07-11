<script setup>
import { Head } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import AppPageHeader from "@/Components/AppPageHeader.vue";
import AppButton from "@/Components/AppButton.vue";
import AppIconButton from "@/Components/AppIconButton.vue";
import AppFilterBar from "@/Components/AppFilterBar.vue";
import AppTable from "@/Components/AppTable.vue";
import AppBadge from "@/Components/AppBadge.vue";
import EmptyState from "@/Components/EmptyState.vue";
import PaginationControl from "@/Components/PaginationControl.vue";
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon, XMarkIcon } from "@heroicons/vue/24/outline";
import FiscalYearFilter from "@/Components/FiscalYearFilter.vue";
import { useOutcomes } from "@/Composables/useOutcomes.js";

const props = defineProps({
  outcomes: Array,
  fiscalYears: { type: Array, default: () => [] },
  selectedFiscalYear: { type: [String, Number], default: "" },
  currentFiscalYear: { type: Number, default: null },
});

const {
  outcomesList,
  showModal,
  modalMode,
  selectedOutcome,
  searchQuery,
  currentPage,
  totalPages,
  filteredOutcomes,
  form,
  openModal,
  closeModal,
  submitOutcome,
  deleteOutcome,
} = useOutcomes(props);

// Dropdown options
const outcomeTypes = ["Strategic Functions", "Core Functions", "Support Functions"];

function outcomeTypeColor(type) {
  const map = {
    "Strategic Functions": "indigo",
    "Core Functions": "blue",
    "Support Functions": "purple",
  };
  return map[type] ?? "slate";
}
</script>

<template>
  <Head title="Agency Organizational Outcomes" />
  <AdminLayout title="Agency Organizational Outcome Management">
    <div class="space-y-5">

      <AppPageHeader title="Organizational Outcomes" subtitle="Manage agency strategic, core, and support function outcomes.">
        <template #actions>
          <AppButton @click="openModal('create')">
            <PlusIcon class="w-4 h-4" /> New Outcome
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filter bar -->
      <AppFilterBar>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search outcomes..."
          class="flex-1 min-w-52 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
        />
        <FiscalYearFilter :fiscal-years="fiscalYears" :selected="selectedFiscalYear" route-name="outcome.index" />
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="filteredOutcomes.length === 0" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Outcome</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Sub-Outcome</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Type</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Created At</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="outcome in filteredOutcomes" :key="outcome.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ outcome.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ outcome.outcome }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ outcome.sub_outcome ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <AppBadge v-if="outcome.function_type" :color="outcomeTypeColor(outcome.function_type)">{{ outcome.function_type }}</AppBadge>
            <span v-else>—</span>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ new Date(outcome.created_at).toLocaleDateString() }}</td>
          <td class="px-4 py-3 text-center">
            <div class="flex justify-center gap-1 items-center">
              <AppIconButton label="View" @click="openModal('view', outcome)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit" variant="warning" @click="openModal('edit', outcome)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="deleteOutcome(outcome)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="outcome in filteredOutcomes" :key="outcome.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ outcome.outcome }}</p>
                <p class="text-xs text-slate-500">{{ outcome.sub_outcome ?? '—' }}</p>
              </div>
              <AppBadge v-if="outcome.function_type" :color="outcomeTypeColor(outcome.function_type)">{{ outcome.function_type }}</AppBadge>
            </div>
            <p class="text-xs text-slate-400">Created {{ new Date(outcome.created_at).toLocaleDateString() }}</p>
            <div class="flex items-center gap-1 pt-1">
              <AppIconButton label="View" @click="openModal('view', outcome)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit" variant="warning" @click="openModal('edit', outcome)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="deleteOutcome(outcome)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No outcomes found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{ modalMode==='create' ? 'New Outcome' : modalMode==='edit' ? 'Edit Outcome' : 'View Outcome' }}
            </h2>
            <AppIconButton label="Close" @click="closeModal">
              <XMarkIcon class="w-4 h-4" />
            </AppIconButton>
          </div>

          <!-- VIEW MODE -->
          <div v-if="modalMode==='view' && selectedOutcome" class="px-6 py-5 space-y-3">
            <div class="flex justify-between text-sm">
              <span class="text-slate-500">Outcome</span>
              <span class="font-medium text-slate-800">{{ selectedOutcome.outcome }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-slate-500">Sub-Outcome</span>
              <span class="font-medium text-slate-800">{{ selectedOutcome.sub_outcome ?? '—' }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-slate-500">Type</span>
              <span class="font-medium text-slate-800">{{ selectedOutcome.function_type ?? '—' }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-slate-500">Created At</span>
              <span class="font-medium text-slate-800">{{ new Date(selectedOutcome.created_at).toLocaleString() }}</span>
            </div>
          </div>

          <!-- CREATE / EDIT FORM -->
          <form v-else @submit.prevent="submitOutcome">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Outcome</label>
                <input v-model="form.outcome" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" required />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Sub-Outcome</label>
                <input v-model="form.sub_outcome" type="text" placeholder="Optional" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
                <select v-model="form.function_type" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                  <option value="" disabled>Select type</option>
                  <option v-for="type in outcomeTypes" :key="type" :value="type">{{ type }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Fiscal Year</label>
                <select v-model="form.fiscal_year" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option :value="null">All years (unscoped)</option>
                  <option v-for="y in fiscalYears" :key="y" :value="y">FY {{ y }}</option>
                </select>
              </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
              <AppButton type="submit">Save</AppButton>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
