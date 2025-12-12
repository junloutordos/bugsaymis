<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon } from "@heroicons/vue/24/outline"
import useEmployeeIPCR from "@/Composables/useEmployeeIPCR.js"

const props = defineProps({
  ipcrs: Array,
  workPlans: Array,
})

const {
  ipcrTargets,
  workPlans: workPlansList,
  errors,
  showModal,
  showAddPlansModal,
  modalMode,
  selectedIPCR,
  selectedPlans,
  form,
  searchQuery,
  currentPage,
  totalPages,
  filteredIPCRs,
  planSearch,
  filteredPlans,
  isPlanSelected,
  togglePlanSelection,
  getIPCRs,
  destroyIPCR,
  openModal,
  closeModal,
  submitIPCR,
  openAddPlansModal,
  closeAddPlansModal,
  submitPlans,
  viewIPCR,
  sortBy,
  statusClasses
} = useEmployeeIPCR(props.ipcrs, props.workPlans)
</script>

<template>
  <Head title="My IPCR Targets" />
  <AdminLayout title="My IPCR Targets">
    <div class="p-6">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">My IPCR Targets</h1>
        <button @click="openModal('create')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow flex items-center gap-1">
          <PlusIcon class="w-5 h-5" /> Add Target
        </button>
      </div>

      <!-- Search Targets -->
      <div class="bg-white p-4 rounded-xl shadow mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search targets..."
          class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
        />
      </div>

      <!-- IPCR Table -->
      <div class="overflow-x-auto bg-white p-4 rounded-xl shadow">
        <table class="min-w-full border border-gray-200 text-center">
          <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
            <tr>
              <th @click="sortBy('id')" class="px-4 py-3 cursor-pointer">ID</th>
              <th @click="sortBy('rating_period')" class="px-4 py-3 cursor-pointer">Rating Period</th>
              <th @click="sortBy('title')" class="px-4 py-3 cursor-pointer">Title</th>
              <th @click="sortBy('status')" class="px-4 py-3 cursor-pointer">Status</th>
              <th class="px-4 py-3">Submitted at</th>
              <th class="px-4 py-3">Approved at</th>
              <th class="px-4 py-3">Accomplishment Submitted at</th>
              <th class="px-4 py-3">Rated at</th>
              <th class="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 text-sm">
            <tr v-for="t in filteredIPCRs" :key="t.id" class="hover:bg-gray-50">
              <td class="px-4 py-3">{{ t.id }}</td>
              <td class="px-4 py-3">{{ t.rating_period }}</td>
              <td class="px-4 py-3">{{ t.title }}</td>
              <td class="px-4 py-3">
                <span :class="`inline-block px-3 py-1 rounded-full text-xs font-semibold ${statusClasses(t.status)}`">
                  {{ t.status }}
                </span>
              </td>
              <td class="px-4 py-3"><small>{{ t.submitted_for_review_at }}</small></td>
              <td class="px-4 py-3"><small>{{ t.target_approved_at }}</small></td>
              <td class="px-4 py-3"><small>{{ t.submitted_for_rating_at }}</small></td>
              <td class="px-4 py-3"><small>{{ t.submitted_rating_at }}</small></td>
              <td class="px-4 py-3 flex justify-center gap-2">
                <button @click="viewIPCR(t)" class="p-2 hover:bg-gray-100 rounded" title="View">
                  <EyeIcon class="w-5 h-5 text-blue-600"/>
                </button>
                <button @click="openAddPlansModal(t)" class="p-2 hover:bg-gray-100 rounded" title="Add Plans">
                  <PlusIcon class="w-5 h-5 text-green-600"/>
                </button>
                <button @click="openModal('edit', t)" class="p-2 hover:bg-gray-100 rounded" title="Edit">
                  <PencilSquareIcon class="w-5 h-5 text-yellow-600"/>
                </button>
                <button @click="destroyIPCR(t.id)" class="p-2 hover:bg-gray-100 rounded" title="Delete">
                  <TrashIcon class="w-5 h-5 text-red-600"/>
                </button>
                
              </td>
            </tr>
            <tr v-if="filteredIPCRs.length === 0">
              <td colspan="12" class="px-4 py-6 text-gray-500">No targets found.</td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click="currentPage--" :disabled="currentPage === 1" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="currentPage++" :disabled="currentPage === totalPages" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>
      </div>
    </div>

    <!-- Add/Edit IPCR Target Modal -->
<div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-1/2 max-w-xl">
    <h2 class="text-lg font-semibold mb-4">{{ modalMode === 'create' ? 'Add Target' : 'Edit Target' }}</h2>

    <div class="flex flex-col gap-3">
      <div>
        <label class="block mb-1 font-medium">Rating Period</label>
        <input v-model="form.rating_period" type="text" class="w-full border rounded px-3 py-2" />
        <div v-if="errors.rating_period" class="text-red-500 text-sm">{{ errors.rating_period }}</div>
      </div>

      <div>
        <label class="block mb-1 font-medium">Title</label>
        <input v-model="form.title" type="text" class="w-full border rounded px-3 py-2" />
        <div v-if="errors.title" class="text-red-500 text-sm">{{ errors.title }}</div>
      </div>

      <div>
        <label class="block mb-1 font-medium">Remarks</label>
        <textarea v-model="form.remarks" rows="3" class="w-full border rounded px-3 py-2"></textarea>
      </div>
    </div>

    <div class="mt-4 flex justify-end gap-2">
      <button @click="closeModal" class="px-4 py-2 bg-gray-200 rounded">Cancel</button>
      <button @click="submitIPCR" class="px-4 py-2 bg-blue-600 text-white rounded">{{ modalMode === 'create' ? 'Add' : 'Update' }}</button>
    </div>
  </div>
</div>


    <!-- Add Plans Modal -->
    <div v-if="showAddPlansModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl p-6 w-3/4 max-w-3xl h-[500px] flex flex-col">
        <h2 class="text-lg font-semibold mb-4">Select Plans for "{{ selectedIPCR?.title }}"</h2>

        <!-- Search Plans -->
        <input
          v-model="planSearch"
          type="text"
          placeholder="Search plans..."
          class="mb-3 px-3 py-2 border rounded w-full focus:ring-blue-500 focus:border-blue-500"
        />

        <!-- Plan List -->
        <div class="flex-1 overflow-y-auto border-t border-b py-2">
          <div class="max-h-full overflow-auto border rounded mt-2 p-2">
            <div
              v-for="plan in filteredPlans"
              :key="'plan-'+plan.id"
              class="flex items-start gap-2 py-2"
            >
              <input
                type="checkbox"
                :id="'plan-'+plan.id"
                :checked="isPlanSelected(plan.id)"
                @change="togglePlanSelection(plan)"
                class="mt-1"
              />
              <label :for="'plan-'+plan.id" class="flex-1">
                <div class="font-semibold">{{ plan.success_indicator }}</div>
                <div class="text-sm text-gray-500" v-if="plan.performance_indicator">
                  {{ plan.performance_indicator.description }}
                </div>
                <div class="text-sm text-gray-500" v-if="plan.office_involved">
                  {{ plan.office_involved }}
                </div>
              </label>
            </div>
            <div v-if="filteredPlans.length === 0" class="text-sm text-gray-500 text-center mt-4">
              No plans found.
            </div>
          </div>
        </div>

        <!-- Modal Actions -->
        <div class="mt-4 flex justify-end gap-2">
          <button @click="closeAddPlansModal" class="px-4 py-2 bg-gray-200 rounded">Cancel</button>
          <button @click="submitPlans" class="px-4 py-2 bg-green-600 text-white rounded">Add</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
