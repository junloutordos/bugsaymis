<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon } from "@heroicons/vue/24/outline"
import { useWorkDistributionPlans } from "@/Composables/useWorkDistributionPlans.js"
import Multiselect from "vue-multiselect"
import "vue-multiselect/dist/vue-multiselect.css"

const props = defineProps({
  plans: Array,
  indicators: Array,
  users: Array,
})

const {
  plansList,
  showModal,
  modalMode,
  selectedPlan,
  searchQuery,
  currentPage,
  totalPages,
  filteredPlans,
  form,
  openModal,
  closeModal,
  submitPlan,
  deletePlan,
} = useWorkDistributionPlans(props)
</script>

<template>
  <Head title="Work Distribution Plan" />
  <AdminLayout title="Work Distribution Plan">
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Work Distribution Plan</h1>
        <button
          @click="openModal('create')"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
        >
          <PlusIcon class="w-5 h-5 inline-block mr-1" /> New WDP
        </button>
      </div>

      <!-- Search + Table -->
      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search plans..."
          class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
        />

        <!-- Table -->
        <div class="overflow-x-auto mt-4">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Performance Indicator</th>
                <th class="px-4 py-3 text-left">Success Indicator</th>
                <th class="px-4 py-3 text-left">Assigned Personnel</th>
                <th class="px-4 py-3 text-left">Rating Period</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="plan in filteredPlans" :key="plan.id">
                <td class="px-4 py-3">{{ plan.id }}</td>
                <td class="px-4 py-3">{{ plan.performance_indicator?.description ?? '—' }}</td>
                <td class="px-4 py-3">{{ plan.success_indicator ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span v-if="plan.personnel?.length">
                    {{ plan.personnel.map(u => u.name).join(', ') }}
                  </span>
                  <span v-else>—</span>
                </td>
                <td class="px-4 py-3">
                  {{ plan.start_date }} - {{ plan.end_date }}
                </td>
                <td class="px-4 py-3 text-center space-x-2">
                  <button @click="openModal('view', plan)" class="text-blue-600 hover:text-blue-800" title="View">
                    <EyeIcon class="w-5 h-5 inline-block" />
                  </button>
                  <button @click="openModal('edit', plan)" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                    <PencilSquareIcon class="w-5 h-5 inline-block" />
                  </button>
                  <button @click="deletePlan(plan)" class="text-red-600 hover:text-red-800" title="Delete">
                    <TrashIcon class="w-5 h-5 inline-block" />
                  </button>
                </td>
              </tr>
              <tr v-if="filteredPlans.length===0">
                <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                  No plans found.
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
            {{ modalMode==='create' ? 'New WDP' : modalMode==='edit' ? 'Edit WDP' : 'View WDP' }}
          </h2>

          <!-- VIEW MODE -->
          <div v-if="modalMode==='view' && selectedPlan" class="space-y-2">
            <p><strong>Performance Indicator:</strong> {{ selectedPlan.performance_indicator?.description ?? '—' }}</p>
            <p><strong>Success Indicator:</strong> {{ selectedPlan.success_indicator ?? '—' }}</p>
            <p><strong>Assigned Personnel:</strong>
              <span v-if="selectedPlan.personnel?.length">
                {{ selectedPlan.personnel.map(u => u.name).join(', ') }}
              </span>
              <span v-else>—</span>
            </p>
            <p><strong>Rating Period:</strong> {{ selectedPlan.start_date }} - {{ selectedPlan.end_date }}</p>
          </div>

          <!-- CREATE / EDIT FORM -->
          <form v-else @submit.prevent="submitPlan" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Performance Indicator</label>
              <select v-model="form.performance_indicator_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required>
                <option value="">-- Select Performance Indicator --</option>
                <option v-for="i in props.indicators" :key="i.id" :value="i.id">
                  {{ i.description }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Success Indicator</label>
              <textarea v-model="form.success_indicator" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Assign Personnel</label>
              <Multiselect
                v-model="form.personnel"
                :options="props.users"
                :multiple="true"
                :close-on-select="false"
                placeholder="Select personnel"
                label="name"
                track-by="id"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
              />
            </div>
            <div class="flex gap-3">
              <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700">Start Date</label>
                <input v-model="form.start_date" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
              </div>
              <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700">End Date</label>
                <input v-model="form.end_date" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
              </div>
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
