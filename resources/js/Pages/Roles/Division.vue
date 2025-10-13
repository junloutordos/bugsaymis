<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon } from "@heroicons/vue/24/outline"
import { useDivisions } from "@/Composables/useDivisions.js"

// Props from backend (DivisionsController@index)
const props = defineProps({
  divisions: Array,
  users: Array, // 👈 all possible chiefs
})

// Composable: all divisions logic
const {
  divisionsList,
  showModal,
  modalMode,
  selectedDivision,
  searchQuery,
  currentPage,
  totalPages,
  filteredDivisions,
  form,
  openModal,
  closeModal,
  submitDivision,
  deleteDivision,
} = useDivisions(props)
</script>

<template>
  <Head title="Divisions" />
  <AdminLayout title="Divisions Management">
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Divisions List</h1>
        <button
          @click="openModal('create')"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
        >
          <PlusIcon class="w-5 h-5 inline-block mr-1" /> New Division
        </button>
      </div>

      <!-- Search -->
      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search divisions..."
          class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
        />

        <!-- Divisions Table -->
        <div class="overflow-x-auto mt-4">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Division Name</th>
                <th class="px-4 py-3 text-left">Chief</th>
                <th class="px-4 py-3 text-left">Year Assigned</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-left">Created At</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="division in filteredDivisions" :key="division.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ division.id }}</td>
                <td class="px-4 py-3">{{ division.division_name }}</td>
                <td class="px-4 py-3">{{ division.divisionchief?.name ?? '—' }}</td>
                <td class="px-4 py-3">{{ division.year ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span
                    class="px-2 py-1 rounded text-xs font-medium"
                    :class="division.status==='active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                  >
                    {{ division.status }}
                  </span>
                </td>
                <td class="px-4 py-3">{{ new Date(division.created_at).toLocaleDateString() }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-1 items-center">
                    <button @click="openModal('view', division)" class="p-1 hover:bg-gray-100 rounded">
                      <EyeIcon class="w-5 h-5 text-blue-600"/>
                    </button>
                    <button @click="openModal('edit', division)" class="p-1 hover:bg-gray-100 rounded">
                      <PencilSquareIcon class="w-5 h-5 text-yellow-600"/>
                    </button>
                    <button @click="deleteDivision(division)" class="p-1 hover:bg-gray-100 rounded">
                      <TrashIcon class="w-5 h-5 text-red-600"/>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredDivisions.length===0">
                <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                  No divisions found.
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
            {{ modalMode==='create' ? 'New Division' : modalMode==='edit' ? 'Edit Division' : 'View Division' }}
          </h2>

          <!-- VIEW MODE -->
          <div v-if="modalMode==='view' && selectedDivision" class="space-y-2">
            <p>Division: <strong>{{ selectedDivision.division_name }}</strong></p>
            <p>Chief: <strong>{{ selectedDivision.divisionchief?.name ?? '—' }}</strong></p>
            <p>Year Assigned: <strong>{{ selectedDivision.year ?? '—' }}</strong></p>
            <p>Status: 
              <span
                class="px-2 py-1 rounded text-xs font-medium"
                :class="selectedDivision.status==='active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
              >
                {{ selectedDivision.status }}
              </span>
            </p>
            <p>Created At: <strong>{{ new Date(selectedDivision.created_at).toLocaleString() }}</strong></p>
          </div>

          <!-- CREATE / EDIT FORM -->
          <form v-else @submit.prevent="submitDivision" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Division Name</label>
              <input
                v-model="form.division_name"
                type="text"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Division Chief (optional)</label>
              <select
                v-model="form.division_chief_id"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
              >
                <option :value="null">— None —</option>
                <option v-for="user in props.users" :key="user.id" :value="user.id">
                  {{ user.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Year Assigned</label>
              <input
                v-model="form.year"
                type="number"
                min="1900"
                max="2100"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
                placeholder="e.g. 2023"
              />
            </div>

            <!-- Status -->
            <div v-if="modalMode === 'edit'">
              <label class="block text-sm font-medium text-gray-700">Status</label>
              <select
                v-model="form.status"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
              >
                <option value="active">Active</option>
                <option value="not_active">Not Active</option>
              </select>
            </div>
            <input v-else type="hidden" v-model="form.status" value="active" />

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
