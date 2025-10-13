<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon, PencilSquareIcon, PlusIcon, CheckIcon } from "@heroicons/vue/24/outline"
import { useIPCR } from "@/Composables/useIPCR.js"

const props = defineProps({
  plans: Array, // from controller
})

const {
  searchQuery,
  currentPage,
  totalPages,
  filteredPlans,
  showModal,
  modalMode,
  selectedPlan,
  form,
  openModal,
  closeModal,
  submitTarget,
  submitAccomplishment,
  approveTarget,
  reviewAccomplishment,
} = useIPCR(props)
</script>

<template>
  <Head title="Individual Performance Commitment and Review" />
  <AdminLayout title="IPCR">
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-6">My IPCR Plans</h1>

      <div class="bg-white rounded-xl shadow p-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search plans..."
          class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
        />

        <div class="overflow-x-auto mt-4">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">Plan</th>
                <th class="px-4 py-3 text-left">Target</th>
                <th class="px-4 py-3 text-left">Accomplishment</th>
                <th class="px-4 py-3 text-left">Ratings</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="plan in filteredPlans" :key="plan.id">
                <td class="px-4 py-3">{{ plan.success_indicator }}</td>
                <td class="px-4 py-3">{{ plan.ipcrs[0]?.target ?? '—' }}</td>
                <td class="px-4 py-3">{{ plan.ipcrs[0]?.accomplishment ?? '—' }}</td>
                <td class="px-4 py-3">
                  Self: {{ plan.ipcrs[0]?.self_rating ?? '—' }} /
                  Sup: {{ plan.ipcrs[0]?.supervisor_rating ?? '—' }}
                </td>
                <td class="px-4 py-3">
                  <span class="px-2 py-1 rounded text-xs bg-gray-100">
                    {{ plan.ipcrs[0]?.target_status ?? 'draft' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center space-x-2">
                  <button @click="openModal('view', plan)" class="text-blue-600">
                    <EyeIcon class="w-5 h-5 inline-block" />
                  </button>
                  <button v-if="!plan.ipcrs.length" @click="openModal('target', plan)" class="text-green-600">
                    <PlusIcon class="w-5 h-5 inline-block" />
                  </button>
                  <button v-if="plan.ipcrs[0]?.target_status==='submitted'" @click="approveTarget(plan.ipcrs[0])" class="text-indigo-600">
                    <CheckIcon class="w-5 h-5 inline-block" />
                  </button>
                  <button v-if="plan.ipcrs[0]?.target_status==='approved' && !plan.ipcrs[0]?.accomplishment" @click="openModal('accomplishment', plan)" class="text-yellow-600">
                    <PencilSquareIcon class="w-5 h-5 inline-block" />
                  </button>
                  <button v-if="plan.ipcrs[0]?.accomplishment" @click="reviewAccomplishment(plan.ipcrs[0])" class="text-purple-600">
                    <CheckIcon class="w-5 h-5 inline-block" />
                  </button>
                </td>
              </tr>
              <tr v-if="filteredPlans.length === 0">
                <td colspan="6" class="px-4 py-6 text-center text-gray-500">No assigned plans found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal -->
      <div v-show="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 relative">
          <button class="absolute top-3 right-3" @click="closeModal">✕</button>

          <h2 class="text-xl font-semibold mb-4">
            {{ modalMode==='target' ? 'Submit Target' :
               modalMode==='accomplishment' ? 'Submit Accomplishment' : 'View Plan' }}
          </h2>

          <form v-if="modalMode==='target'" @submit.prevent="submitTarget">
            <label class="block mb-2">Target</label>
            <textarea v-model="form.target" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"></textarea>
            <div class="flex justify-end mt-4">
              <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
            </div>
          </form>

          <form v-if="modalMode==='accomplishment'" @submit.prevent="submitAccomplishment">
            <label class="block mb-2">Accomplishment</label>
            <textarea v-model="form.accomplishment" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"></textarea>

            <div class="grid grid-cols-3 gap-2 mt-3">
                <div>
                <label>Quality</label>
                <input v-model.number="form.self_quality" type="number" min="1" max="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
                </div>
                <div>
                <label>Efficiency</label>
                <input v-model.number="form.self_efficiency" type="number" min="1" max="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
                </div>
                <div>
                <label>Timeliness</label>
                <input v-model.number="form.self_timeliness" type="number" min="1" max="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
                </div>
            </div>

            <div class="flex justify-end mt-4">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
            </div>
        </form>

        </div>
      </div>
    </div>
  </AdminLayout>
</template>
