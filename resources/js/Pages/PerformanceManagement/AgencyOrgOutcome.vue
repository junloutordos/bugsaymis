<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon } from "@heroicons/vue/24/outline"
import { useOutcomes } from "@/Composables/useOutcomes.js" // 👈 new composable

// Props from backend (AgencyOutcomeController@index)
const props = defineProps({
  outcomes: Array, // all outcomes with sub_outcomes relation
})

// Composable: all outcomes CRUD logic
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
} = useOutcomes(props)
</script>

<template>
  <Head title="Agency Organizational Outcomes" />
  <AdminLayout title="Agency Organizational Outcome Management">
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Organizational Outcomes</h1>
        <button
          @click="openModal('create')"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
        >
          <PlusIcon class="w-5 h-5 inline-block mr-1" /> New Outcome
        </button>
      </div>

      <!-- Search -->
      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search outcomes..."
          class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
        />

        <!-- Outcomes Table -->
        <div class="overflow-x-auto mt-4">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Outcome</th>
                <th class="px-4 py-3 text-left">Sub-Outcome</th>
                <th class="px-4 py-3 text-left">Created At</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="outcome in filteredOutcomes" :key="outcome.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ outcome.id }}</td>
                <td class="px-4 py-3">{{ outcome.outcome }}</td>
                <td class="px-4 py-3">{{ outcome.sub_outcome ?? '—' }}</td>
                <td class="px-4 py-3">{{ new Date(outcome.created_at).toLocaleDateString() }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-1 items-center">
                    <button @click="openModal('view', outcome)" class="p-1 hover:bg-gray-100 rounded">
                      <EyeIcon class="w-5 h-5 text-blue-600"/>
                    </button>
                    <button @click="openModal('edit', outcome)" class="p-1 hover:bg-gray-100 rounded">
                      <PencilSquareIcon class="w-5 h-5 text-yellow-600"/>
                    </button>
                    <button @click="deleteOutcome(outcome)" class="p-1 hover:bg-gray-100 rounded">
                      <TrashIcon class="w-5 h-5 text-red-600"/>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredOutcomes.length===0">
                <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                  No outcomes found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
          <button
            @click="currentPage--"
            :disabled="currentPage===1"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
          >
            Prev
          </button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button
            @click="currentPage++"
            :disabled="currentPage===totalPages"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
          >
            Next
          </button>
        </div>
      </div>

      <!-- Modal -->
      <div
        v-show="showModal"
        class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 transition-opacity"
      >
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
          <button
            class="absolute top-3 right-3 text-gray-500 hover:text-gray-800"
            @click="closeModal"
          >
            ✕
          </button>

          <h2 class="text-xl font-semibold mb-4">
            {{ modalMode==='create' ? 'New Outcome' : modalMode==='edit' ? 'Edit Outcome' : 'View Outcome' }}
          </h2>

          <!-- VIEW MODE -->
          <div v-if="modalMode==='view' && selectedOutcome" class="space-y-2">
            <p>Outcome: <strong>{{ selectedOutcome.outcome }}</strong></p>
            <p>Sub-Outcome: <strong>{{ selectedOutcome.sub_outcome ?? '—' }}</strong></p>
            <p>Created At: <strong>{{ new Date(selectedOutcome.created_at).toLocaleString() }}</strong></p>
          </div>

          <!-- CREATE / EDIT FORM -->
          <form v-else @submit.prevent="submitOutcome" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Outcome</label>
              <input
                v-model="form.outcome"
                type="text"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Sub-Outcome</label>
              <input
                v-model="form.sub_outcome"
                type="text"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
                placeholder="Optional"
              />
            </div>

            <div class="flex justify-end space-x-3 pt-4">
              <button
                type="button"
                @click="closeModal"
                class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
              >
                Save
              </button>
            </div>
          </form>

        </div>
      </div>
    </div>
  </AdminLayout>
</template>
