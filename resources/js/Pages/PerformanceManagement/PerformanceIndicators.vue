<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
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
    <div>
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Performance Indicators</h1>
          <p class="text-sm text-slate-500">Manage performance indicators and their targets.</p>
        </div>
        <button
          @click="openModal('create')"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
        >
          <PlusIcon class="w-4 h-4" /> New Indicator
        </button>
      </div>

      <!-- Search + Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search indicators..."
            class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
          />
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
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
            </thead>
            <tbody class="divide-y divide-slate-100">
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
                    <button
                      @click="openModal('view', indicator)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
                      title="View"
                    >
                      <EyeIcon class="w-4 h-4" />
                    </button>
                    <button
                      @click="openModal('edit', indicator)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
                      title="Edit"
                    >
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button
                      @click="deleteIndicator(indicator)"
                      class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors"
                      title="Delete"
                    >
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredIndicators.length===0">
                <td colspan="8" class="py-16 text-center text-slate-400 text-sm">
                  No indicators found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex gap-2">
            <button @click="currentPage--" :disabled="currentPage===1"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Prev</button>
            <button @click="currentPage++" :disabled="currentPage===totalPages"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Next</button>
          </div>
        </div>
      </div>

      <!-- Modal -->
      <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{ modalMode==='create' ? 'New Indicator' : modalMode==='edit' ? 'Edit Indicator' : 'View Indicator' }}
            </h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition-colors" @click="closeModal"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>

          <!-- VIEW MODE -->
          <div v-if="modalMode==='view' && selectedIndicator" class="px-6 py-5 space-y-3 text-sm">
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
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end">
              <button type="button" @click="closeModal"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Close</button>
            </div>
          </div>

          <!-- CREATE / EDIT FORM -->
          <form v-else @submit.prevent="submitIndicator">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Sub-Outcome</label>
                <select v-model="form.agency_outcome_id"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" required>
                  <option value="">-- Select Sub-Outcome --</option>
                  <option v-for="o in props.outcomes" :key="o.id" :value="o.id">{{ o.sub_outcome }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <textarea v-model="form.description" rows="3"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"></textarea>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Target</label>
                <input v-model="form.target" type="text" required
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
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
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Budget</label>
                <input v-model="form.budget" type="number" min="0" step="0.01" required
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <button type="button" @click="closeModal"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
              <button type="submit"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Save</button>
            </div>
          </form>
        </div>
      </div>
      </Teleport>
    </div>
  </AdminLayout>
</template>
