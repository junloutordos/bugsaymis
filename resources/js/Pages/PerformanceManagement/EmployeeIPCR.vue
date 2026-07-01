<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon } from "@heroicons/vue/24/outline"
import useEmployeeIPCR from "@/Composables/useEmployeeIPCR.js"

const props = defineProps({
  ipcrs: Array,
  workPlans: Array,
  ratingPeriods: Array,
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
    <div class="p-6 space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">My IPCR Targets</h1>
        <button @click="openModal('create')" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <PlusIcon class="w-4 h-4" /> Add Target
        </button>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search targets..."
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-64"
        />
      </div>

      <!-- IPCR Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th @click="sortBy('id')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap cursor-pointer">ID</th>
                <th @click="sortBy('rating_period')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap cursor-pointer">Rating Period</th>
                <th @click="sortBy('title')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap cursor-pointer">Title</th>
                <th @click="sortBy('status')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap cursor-pointer">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Submitted at</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Approved at</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Accomplishment Submitted</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Rated at</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="t in filteredIPCRs" :key="t.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ t.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ t.rating_period }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ t.title }}</td>
                <td class="px-4 py-3">
                  <span :class="`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium ${statusClasses(t.status)}`">
                    {{ t.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ t.submitted_for_review_at }}</td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ t.target_approved_at }}</td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ t.submitted_for_rating_at }}</td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ t.submitted_rating_at }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center justify-center gap-1">
                    <button @click="viewIPCR(t)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="View">
                      <EyeIcon class="w-4 h-4"/>
                    </button>
                    <button @click="openAddPlansModal(t)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-emerald-700 transition-colors" title="Add Plans">
                      <PlusIcon class="w-4 h-4"/>
                    </button>
                    <button @click="openModal('edit', t)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-amber-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="w-4 h-4"/>
                    </button>
                    <button @click="destroyIPCR(t.id)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors" title="Delete">
                      <TrashIcon class="w-4 h-4"/>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredIPCRs.length === 0">
                <td colspan="9" class="py-16 text-center text-slate-400 text-sm">No targets found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
                <PaginationControl
          :current-page="currentPage"
          :total-pages="totalPages"
          @prev="currentPage--"
          @next="currentPage++"
          @page="currentPage = $event"
        />
      </div>
    </div>

    <!-- Add/Edit IPCR Target Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">{{ modalMode === 'create' ? 'Add Target' : 'Edit Target' }}</h2>
          <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Rating Period</label>
            <select v-model="form.rating_period" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="" disabled>-- Select Rating Period --</option>
              <option v-for="period in props.ratingPeriods" :key="period" :value="period">{{ period }}</option>
            </select>
            <div v-if="errors.rating_period" class="text-red-500 text-xs mt-1">{{ errors.rating_period }}</div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Title</label>
            <input v-model="form.title" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <div v-if="errors.title" class="text-red-500 text-xs mt-1">{{ errors.title }}</div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
            <textarea v-model="form.remarks" rows="3" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
          <button @click="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Cancel</button>
          <button @click="submitIPCR" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">{{ modalMode === 'create' ? 'Add' : 'Update' }}</button>
        </div>
      </div>
    </div>

    <!-- Add Plans Modal -->
    <div v-if="showAddPlansModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl flex flex-col max-h-[80vh]">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
          <h2 class="text-base font-semibold text-slate-800">Select Plans for "{{ selectedIPCR?.title }}"</h2>
          <button @click="closeAddPlansModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="px-6 py-4 shrink-0">
          <input
            v-model="planSearch"
            type="text"
            placeholder="Search plans..."
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>
        <div class="flex-1 overflow-y-auto px-6 pb-2">
          <div class="space-y-1 rounded-lg border border-slate-200 p-2">
            <div v-for="plan in filteredPlans" :key="'plan-'+plan.id" class="flex items-start gap-2 py-2">
              <input type="checkbox" :id="'plan-'+plan.id" :checked="isPlanSelected(plan.id)" @change="togglePlanSelection(plan)" class="mt-0.5" />
              <label :for="'plan-'+plan.id" class="flex-1 cursor-pointer">
                <div class="text-sm font-medium text-slate-700">{{ plan.success_indicator }}</div>
                <div class="text-xs text-slate-500" v-if="plan.performance_indicator">{{ plan.performance_indicator.description }}</div>
                <div class="text-xs text-slate-500" v-if="plan.office_involved">{{ plan.office_involved }}</div>
              </label>
            </div>
            <div v-if="filteredPlans.length === 0" class="py-8 text-center text-slate-400 text-sm">No plans found.</div>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 shrink-0">
          <button @click="closeAddPlansModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Cancel</button>
          <button @click="submitPlans" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">Add Plans</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
