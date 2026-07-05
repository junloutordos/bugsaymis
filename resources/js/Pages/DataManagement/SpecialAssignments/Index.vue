<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppTable from "@/Components/AppTable.vue"
import AppModal from "@/Components/AppModal.vue"
import EmptyState from "@/Components/EmptyState.vue"
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon } from "@heroicons/vue/24/outline"
import { useSpecialAssignments } from "@/Composables/useSpecialAssignments.js"

const props = defineProps({
  assignments: Array,
  users: Array,
})

const {
  assignmentsList, form, showModal, modalMode, selectedAssignment,
  searchQuery, currentPage, totalPages, filteredAssignments,
  applyFilters, clearFilters,
  openModal, closeModal, toggleMember, submitAssignment, deleteAssignment,
} = useSpecialAssignments(props)
</script>

<template>
  <Head title="Special Assignments" />
  <AdminLayout title="Special Assignments">
    <div class="space-y-5">

      <AppPageHeader title="Special Assignments" subtitle="Manage special assignments and coordinators">
        <template #actions>
          <AppButton @click="openModal('create')">
            <PlusIcon class="w-4 h-4" /> New Assignment
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <input v-model="searchQuery" type="text" placeholder="Search assignments..."
          @keydown.enter.prevent="applyFilters"
          class="w-full sm:w-80 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />

        <template #actions>
          <AppButton size="sm" @click="applyFilters">Search</AppButton>
          <AppButton v-if="searchQuery" size="sm" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!filteredAssignments.length" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Coordinator</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Members</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="assignment in filteredAssignments" :key="assignment.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ assignment.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 font-medium">{{ assignment.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ assignment.coordinator?.name ?? "—" }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ assignment.members?.length ?? 0 }}</td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <AppIconButton label="View" @click="openModal('view', assignment)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit" @click="openModal('edit', assignment)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="deleteAssignment(assignment)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="assignment in filteredAssignments" :key="assignment.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs text-slate-400">ID: {{ assignment.id }}</p>
                <p class="font-medium text-slate-800">{{ assignment.name }}</p>
                <p class="text-xs text-slate-500 mt-1">Coordinator: {{ assignment.coordinator?.name ?? "—" }}</p>
                <p class="text-xs text-slate-500">Members: {{ assignment.members?.length ?? 0 }}</p>
              </div>
            </div>
            <div class="flex items-center gap-1 pt-1">
              <AppIconButton label="View" @click="openModal('view', assignment)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit" @click="openModal('edit', assignment)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="deleteAssignment(assignment)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No assignments found" />
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

      <!-- MODAL -->
      <AppModal
        :show="showModal"
        :title="modalMode === 'create' ? 'New Special Assignment' : modalMode === 'edit' ? 'Edit Special Assignment' : 'View Special Assignment'"
        size="2xl"
        @close="closeModal"
      >
        <!-- VIEW MODE -->
        <div v-if="modalMode === 'view'" class="space-y-3 text-sm text-slate-700">
          <p><span class="font-medium text-slate-600">Name:</span> {{ selectedAssignment?.name }}</p>
          <p><span class="font-medium text-slate-600">Coordinator:</span> {{ selectedAssignment?.coordinator?.name ?? "—" }}</p>
          <p><span class="font-medium text-slate-600">Description:</span> {{ selectedAssignment?.description ?? "—" }}</p>
          <div>
            <p class="font-medium text-slate-600 mb-1">Members:</p>
            <ul v-if="selectedAssignment?.members?.length" class="mt-1 space-y-1">
              <li v-for="m in selectedAssignment.members" :key="m.id" class="text-sm text-slate-700">
                {{ m.name }} <span v-if="m.pivot?.task" class="text-slate-400">— {{ m.pivot.task }}</span>
              </li>
            </ul>
            <p v-else class="text-slate-400 text-sm">No members.</p>
          </div>
        </div>

        <!-- FORM -->
        <form v-else @submit.prevent="submitAssignment" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-danger-600">*</span></label>
            <input v-model="form.name" type="text" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Coordinator</label>
            <select v-model="form.coordinator_id"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="">— None —</option>
              <option v-for="u in props.users" :key="u.id" :value="u.id">{{ u.name }}<span v-if="u.position"> ({{ u.position }})</span></option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
            <textarea v-model="form.description" rows="2"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"></textarea>
          </div>

          <!-- Members -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Members</label>
            <div class="border border-slate-200 rounded-lg p-2 max-h-52 overflow-y-auto space-y-2 text-sm">
              <div v-for="u in props.users" :key="u.id" class="flex items-start gap-2">
                <input type="checkbox" :value="u.id" :checked="form.member_ids.includes(u.id)"
                  @change="toggleMember(u.id)" class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                <div class="flex-1">
                  <span class="text-slate-700">{{ u.name }}<span v-if="u.position" class="text-slate-400"> ({{ u.position }})</span></span>
                  <input v-if="form.member_ids.includes(u.id)" v-model="form.member_tasks[u.id]"
                    type="text" placeholder="Task / Role..."
                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                </div>
              </div>
            </div>
            <p class="text-xs text-slate-400 mt-1">{{ form.member_ids.length }} member(s) selected</p>
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
            <AppButton type="submit">Save</AppButton>
          </div>
        </form>
      </AppModal>
    </div>
  </AdminLayout>
</template>
