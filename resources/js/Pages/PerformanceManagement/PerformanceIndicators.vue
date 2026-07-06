<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import AppTable from "@/Components/AppTable.vue"
import AppButton from "@/Components/AppButton.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppModal from "@/Components/AppModal.vue"
import EmptyState from "@/Components/EmptyState.vue"
import PaginationControl from "@/Components/PaginationControl.vue"
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon } from "@heroicons/vue/24/outline"
import { usePerformanceIndicators } from "@/Composables/usePerformanceIndicators.js"
import Multiselect from "vue-multiselect"
import "vue-multiselect/dist/vue-multiselect.css"

// Props from backend (PerformanceIndicatorController@index)
const props = defineProps({
  indicators: Array,
  divisions: Array,
  outcomes: Array,
})

const {
  indicatorsList,
  showModal,
  modalMode,
  selectedIndicator,
  searchQuery,
  currentPage,
  totalPages,
  filteredIndicators,
  form,
  openModal,
  closeModal,
  submitIndicator,
  deleteIndicator,
} = usePerformanceIndicators(props)
</script>

<template>
  <Head title="Performance Indicators" />
  <AdminLayout title="Performance Indicator Management">
    <div class="space-y-5">

      <AppPageHeader title="Performance Indicators" subtitle="Manage performance indicators and their targets.">
        <template #actions>
          <AppButton @click="openModal('create')">
            <PlusIcon class="w-4 h-4" /> New Indicator
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Search -->
      <AppFilterBar>
        <AppInput v-model="searchQuery" placeholder="Search indicators..." class="w-full sm:w-72" />
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="filteredIndicators.length === 0" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Description</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Outcome</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Target</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Division</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Budget</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Created At</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="indicator in filteredIndicators" :key="indicator.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ indicator.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ indicator.description }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ indicator.agency_outcome?.sub_outcome ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ indicator.target ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <span v-if="indicator.divisions?.length">
              {{ indicator.divisions.map(d => d.division_name).join(', ') }}
            </span>
            <span v-else class="text-slate-400">—</span>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">₱{{ indicator.budget?.toLocaleString() ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ new Date(indicator.created_at).toLocaleDateString() }}</td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center justify-center gap-1">
              <AppIconButton label="View" @click="openModal('view', indicator)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit" @click="openModal('edit', indicator)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="deleteIndicator(indicator)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No indicators found" />
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
      <AppModal
        :show="showModal"
        :title="modalMode==='create' ? 'New Indicator' : modalMode==='edit' ? 'Edit Indicator' : 'View Indicator'"
        size="lg"
        @close="closeModal"
      >
        <!-- VIEW MODE -->
        <div v-if="modalMode==='view' && selectedIndicator" class="space-y-3 text-sm">
          <p><span class="font-medium text-slate-700">Description:</span> <span class="text-slate-600">{{ selectedIndicator.description ?? '—' }}</span></p>
          <p><span class="font-medium text-slate-700">Target:</span> <span class="text-slate-600">{{ selectedIndicator.target ?? '—' }}</span></p>
          <p><span class="font-medium text-slate-700">Outcome:</span> <span class="text-slate-600">{{ selectedIndicator.agency_outcome?.sub_outcome ?? '—' }}</span></p>
          <p><span class="font-medium text-slate-700">Division:</span>
            <span class="text-slate-600" v-if="selectedIndicator.divisions?.length">
              {{ selectedIndicator.divisions.map(d => d.division_name).join(', ') }}
            </span>
            <span v-else class="text-slate-400">—</span>
          </p>
          <p><span class="font-medium text-slate-700">Budget:</span> <span class="text-slate-600">₱{{ selectedIndicator.budget?.toLocaleString() ?? '—' }}</span></p>
          <p><span class="font-medium text-slate-700">Created At:</span> <span class="text-slate-600">{{ new Date(selectedIndicator.created_at).toLocaleString() }}</span></p>
        </div>

        <!-- CREATE / EDIT FORM -->
        <form v-else id="indicator-form" @submit.prevent="submitIndicator" class="space-y-4">
          <AppSelect v-model="form.agency_outcome_id" label="Sub-Outcome" required placeholder="-- Select Sub-Outcome --">
            <option v-for="o in props.outcomes" :key="o.id" :value="o.id">{{ o.sub_outcome }}</option>
          </AppSelect>

          <AppTextarea v-model="form.description" label="Description" :rows="3" />

          <AppInput v-model="form.target" label="Target" type="text" required />

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Division(s)</label>
            <Multiselect
              v-model="form.divisions"
              :options="props.divisions"
              :multiple="true"
              :close-on-select="false"
              :clear-on-select="false"
              :preserve-search="true"
              placeholder="Select one or more divisions"
              label="division_name"
              track-by="id"
              class="mt-1 block w-full rounded-lg border-slate-200"
            />
          </div>

          <AppInput v-model="form.budget" label="Budget" type="number" min="0" step="0.01" required />
        </form>

        <template #footer>
          <template v-if="modalMode==='view'">
            <AppButton variant="secondary" @click="closeModal">Close</AppButton>
          </template>
          <template v-else>
            <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
            <AppButton type="submit" form="indicator-form">Save</AppButton>
          </template>
        </template>
      </AppModal>

    </div>
  </AdminLayout>
</template>
