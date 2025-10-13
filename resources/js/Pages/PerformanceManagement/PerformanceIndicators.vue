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
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Performance Indicators</h1>
        <button
          @click="openModal('create')"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
        >
          <PlusIcon class="w-5 h-5 inline-block mr-1" /> New Indicator
        </button>
      </div>

      <!-- Search + Table -->
      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search indicators..."
          class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
        />

        <!-- Table -->
        <div class="overflow-x-auto mt-4">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Description</th>
                <th class="px-4 py-3 text-left">Outcome</th>
                <th class="px-4 py-3 text-left">Target</th>
                <th class="px-4 py-3 text-left">Division</th>
                <th class="px-4 py-3 text-left">Budget</th>
                <th class="px-4 py-3 text-left">Created At</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="indicator in filteredIndicators" :key="indicator.id">
                <td class="px-4 py-3">{{ indicator.id }}</td>
                <td class="px-4 py-3">{{ indicator.description }}</td>
                <td class="px-4 py-3">{{ indicator.agency_outcome?.sub_outcome ?? '—' }}</td>
                <td class="px-4 py-3">{{ indicator.target ?? '—' }}</td>
                <td class="px-4 py-3">
                  <!-- show all assigned divisions -->
                  <span v-if="indicator.divisions?.length">
                    {{ indicator.divisions.map(d => d.division_name).join(', ') }}
                  </span>
                  <span v-else>—</span>
                </td>
                <td class="px-4 py-3">₱{{ indicator.budget?.toLocaleString() ?? '—' }}</td>
                <td class="px-4 py-3">{{ new Date(indicator.created_at).toLocaleDateString() }}</td>
                <td class="px-4 py-3 text-center space-x-2">
                  <!-- Actions -->
                  <button
                    @click="openModal('view', indicator)"
                    class="text-blue-600 hover:text-blue-800"
                    title="View"
                  >
                    <EyeIcon class="w-5 h-5 inline-block" />
                  </button>

                  <button
                    @click="openModal('edit', indicator)"
                    class="text-yellow-600 hover:text-yellow-800"
                    title="Edit"
                  >
                    <PencilSquareIcon class="w-5 h-5 inline-block" />
                  </button>

                  <button
                    @click="deleteIndicator(indicator)"
                    class="text-red-600 hover:text-red-800"
                    title="Delete"
                  >
                    <TrashIcon class="w-5 h-5 inline-block" />
                  </button>
                </td>
              </tr>
              <tr v-if="filteredIndicators.length===0">
                <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                  No indicators found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click="currentPage--" :disabled="currentPage===1" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="currentPage++" :disabled="currentPage===totalPages" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- Modal -->
      <div v-show="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 relative">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>

          <h2 class="text-xl font-semibold mb-4">
            {{ modalMode==='create' ? 'New Indicator' : modalMode==='edit' ? 'Edit Indicator' : 'View Indicator' }}
          </h2>

          <!-- VIEW MODE -->
          <div v-if="modalMode==='view' && selectedIndicator" class="space-y-2">
            <p><strong>Description:</strong> {{ selectedIndicator.description ?? '—' }}</p>
            <p><strong>Target:</strong> {{ selectedIndicator.target ?? '—' }}</p>
            <p><strong>Outcome:</strong> {{ selectedIndicator.agency_outcome?.sub_outcome ?? '—' }}</p>
            <p><strong>Division:</strong>
              <span v-if="selectedIndicator.divisions?.length">
                {{ selectedIndicator.divisions.map(d => d.division_name).join(', ') }}
              </span>
              <span v-else>—</span>
            </p>
            <p><strong>Budget:</strong> ₱{{ selectedIndicator.budget?.toLocaleString() ?? '—' }}</p>
            <p><strong>Created At:</strong> {{ new Date(selectedIndicator.created_at).toLocaleString() }}</p>
          </div>

          <!-- CREATE / EDIT FORM -->
          <form v-else @submit.prevent="submitIndicator" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Sub-Outcome</label>
              <select v-model="form.agency_outcome_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required>
                <option value="">-- Select Sub-Outcome --</option>
                <option v-for="o in props.outcomes" :key="o.id" :value="o.id">
                  {{ o.sub_outcome }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Description</label>
              <textarea v-model="form.description" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Target</label>
              <input v-model="form.target" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
            </div>
            <!-- Division Multi-Select -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Division(s)</label>
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
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
              />
            </div>


            <div>
              <label class="block text-sm font-medium text-gray-700">Budget</label>
              <input v-model="form.budget" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
            </div>
            <div class="flex justify-end space-x-3 pt-4">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
