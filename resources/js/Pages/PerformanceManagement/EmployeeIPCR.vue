<script setup>
import { computed } from "vue"
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import AppButton from "@/Components/AppButton.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppBadge from "@/Components/AppBadge.vue"
import AppTable from "@/Components/AppTable.vue"
import AppModal from "@/Components/AppModal.vue"
import EmptyState from "@/Components/EmptyState.vue"
import PaginationControl from "@/Components/PaginationControl.vue"
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
} = useEmployeeIPCR(props.ipcrs, props.workPlans)

// ---------- Status badge color mapping (mirrors ipcrStatusClass) ----------
function statusBadgeColor(status) {
  const map = {
    "New Target": "blue",
    "For Review": "amber",
    "Targets Approved": "green",
    "Submitted for Rating": "orange",
    "Rated & For PMT Review": "purple",
    "Submitted to PMT": "purple",
    "PMT Returned for Revision": "red",
    "Submitted to HR": "blue",
    "Approved by PMT": "green",
    "Director Signed": "green",
    "Returned for Revision": "red",
    "Rejected": "red",
  }
  return map[status] ?? "slate"
}

const addPlansModalTitle = computed(() => `Select Plans for "${selectedIPCR.value?.title ?? ''}"`)
</script>

<template>
  <Head title="My IPCR Targets" />
  <AdminLayout title="My IPCR Targets">
    <div class="p-6 space-y-5">

      <AppPageHeader title="My IPCR Targets">
        <template #actions>
          <AppButton @click="openModal('create')">
            <PlusIcon class="w-4 h-4" /> Add Target
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filter bar -->
      <AppFilterBar>
        <AppInput v-model="searchQuery" placeholder="Search targets..." class="min-w-[180px] flex-1 sm:flex-none sm:w-64" />
      </AppFilterBar>

      <!-- IPCR Table -->
      <AppTable :is-empty="!filteredIPCRs.length" :skeleton-cols="9">
        <template #head>
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
        </template>

        <tr v-for="t in filteredIPCRs" :key="t.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ t.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ t.rating_period }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ t.title }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusBadgeColor(t.status)">{{ t.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-xs text-slate-500">{{ t.submitted_for_review_at }}</td>
          <td class="px-4 py-3 text-xs text-slate-500">{{ t.target_approved_at }}</td>
          <td class="px-4 py-3 text-xs text-slate-500">{{ t.submitted_for_rating_at }}</td>
          <td class="px-4 py-3 text-xs text-slate-500">{{ t.submitted_rating_at }}</td>
          <td class="px-4 py-3">
            <div class="flex items-center justify-center gap-1">
              <AppIconButton label="View" @click="viewIPCR(t)">
                <EyeIcon class="w-4 h-4"/>
              </AppIconButton>
              <AppIconButton label="Add Plans" variant="success" @click="openAddPlansModal(t)">
                <PlusIcon class="w-4 h-4"/>
              </AppIconButton>
              <AppIconButton label="Edit" variant="warning" @click="openModal('edit', t)">
                <PencilSquareIcon class="w-4 h-4"/>
              </AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="destroyIPCR(t.id)">
                <TrashIcon class="w-4 h-4"/>
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="t in filteredIPCRs" :key="'m-'+t.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-sm font-medium text-slate-800 truncate">{{ t.title }}</p>
                <p class="text-xs text-slate-500">{{ t.rating_period }}</p>
              </div>
              <AppBadge :color="statusBadgeColor(t.status)">{{ t.status }}</AppBadge>
            </div>
            <div class="flex items-center gap-1 pt-1">
              <AppIconButton label="View" @click="viewIPCR(t)">
                <EyeIcon class="w-4 h-4"/>
              </AppIconButton>
              <AppIconButton label="Add Plans" variant="success" @click="openAddPlansModal(t)">
                <PlusIcon class="w-4 h-4"/>
              </AppIconButton>
              <AppIconButton label="Edit" variant="warning" @click="openModal('edit', t)">
                <PencilSquareIcon class="w-4 h-4"/>
              </AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="destroyIPCR(t.id)">
                <TrashIcon class="w-4 h-4"/>
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No targets found." />
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
    </div>

    <!-- Add/Edit IPCR Target Modal -->
    <AppModal :show="showModal" :title="modalMode === 'create' ? 'Add Target' : 'Edit Target'" @close="closeModal">
      <div class="space-y-4">
        <AppSelect v-model="form.rating_period" label="Rating Period" required :error="errors.rating_period" placeholder="-- Select Rating Period --">
          <option v-for="period in props.ratingPeriods" :key="period" :value="period">{{ period }}</option>
        </AppSelect>
        <AppInput v-model="form.title" label="Title" :error="errors.title" />
        <AppTextarea v-model="form.remarks" label="Remarks" :rows="3" />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
        <AppButton @click="submitIPCR">{{ modalMode === 'create' ? 'Add' : 'Update' }}</AppButton>
      </template>
    </AppModal>

    <!-- Add Plans Modal -->
    <AppModal :show="showAddPlansModal" :title="addPlansModalTitle" size="2xl" @close="closeAddPlansModal">
      <AppInput v-model="planSearch" placeholder="Search plans..." class="mb-3" />
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
      <template #footer>
        <AppButton variant="secondary" @click="closeAddPlansModal">Cancel</AppButton>
        <AppButton @click="submitPlans">Add Plans</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
