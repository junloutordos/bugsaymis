<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon } from "@heroicons/vue/24/outline"
import { useWorkDistributionPlans } from "@/Composables/useWorkDistributionPlans.js"

const props = defineProps({
  plans: Array,
  indicators: Array,
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
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Work Distribution Plan</h1>
        <button
          @click="openModal('create')"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
        >
          <PlusIcon class="w-5 h-5 inline-block mr-1" /> New WDP
        </button>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search plans..."
          class="w-1/3 rounded-lg border-gray-300 shadow-sm"
        />

        <div class="overflow-x-auto mt-4">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Performance Indicator</th>
                <th class="px-4 py-3 text-left">Success Indicator</th>
                <th class="px-4 py-3 text-left">Office Involved</th>
                <th class="px-4 py-3 text-left">Rated By</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="plan in filteredPlans" :key="plan.id">
                <td class="px-4 py-3">{{ plan.id }}</td>
                <td class="px-4 py-3">{{ plan.performance_indicator?.description ?? "—" }}</td>
                <td class="px-4 py-3">{{ plan.success_indicator ?? "—" }}</td>
                <td class="px-4 py-3">{{ plan.office_involved ?? "—" }}</td>
                <td class="px-4 py-3">{{ plan.rated_by ?? "—" }}</td>
                <td class="px-4 py-3 text-center space-x-2">
                  <button @click="openModal('view', plan)" class="text-blue-600"><EyeIcon class="w-5 h-5" /></button>
                  <button @click="openModal('edit', plan)" class="text-yellow-600"><PencilSquareIcon class="w-5 h-5" /></button>
                  <button @click="deletePlan(plan)" class="text-red-600"><TrashIcon class="w-5 h-5" /></button>
                </td>
              </tr>

              <tr v-if="filteredPlans.length === 0">
                <td colspan="6" class="px-4 py-6 text-center text-gray-500">No plans found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click="currentPage--" :disabled="currentPage===1" class="px-3 py-1 bg-gray-200 rounded">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="currentPage++" :disabled="currentPage===totalPages" class="px-3 py-1 bg-gray-200 rounded">Next</button>
        </div>
      </div>

      <!-- MODAL -->
      <div v-show="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 relative">
          <button class="absolute top-3 right-3 text-gray-500" @click="closeModal">✕</button>

          <h2 class="text-xl font-semibold mb-4">
            {{ modalMode=='create' ? 'New WDP' : modalMode=='edit' ? 'Edit WDP' : 'View WDP' }}
          </h2>

          <!-- VIEW MODE -->
          <div v-if="modalMode==='view'">
            <p><strong>Performance Indicator:</strong> {{ selectedPlan.performance_indicator?.description }}</p>
            <p><strong>Success Indicator:</strong> {{ selectedPlan.success_indicator }}</p>
            <p><strong>Office Involved:</strong> {{ selectedPlan.office_involved }}</p>
            <p><strong>Rated By:</strong> {{ selectedPlan.rated_by }}</p>
          </div>

          <!-- FORM -->
          <form v-else @submit.prevent="submitPlan" class="space-y-4">
            <div>
              <label class="font-medium">Performance Indicator</label>
              <select v-model="form.performance_indicator_id" class="w-full mt-1 rounded-lg border-gray-300" required>
                <option value="">-- Select Performance Indicator --</option>
                <option v-for="i in props.indicators" :key="i.id" :value="i.id">{{ i.description }}</option>
              </select>
            </div>

            <div>
              <label class="font-medium">Success Indicator</label>
              <textarea v-model="form.success_indicator" rows="3" class="w-full rounded-lg border-gray-300"></textarea>
            </div>

            <div>
              <label class="font-medium">Office Involved</label>
              <input v-model="form.office_involved" type="text" class="w-full rounded-lg border-gray-300" />
            </div>

            <div>
              <label class="font-medium">Rated By</label>
              <input v-model="form.rated_by" type="text" class="w-full rounded-lg border-gray-300" />
            </div>

            <div class="flex justify-end gap-2 pt-4">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
            </div>
          </form>

        </div>
      </div>
    </div>
  </AdminLayout>
</template>
