<template>
  <Head title="Roles" />
  <AdminLayout title="Roles Management">
    <div class="space-y-5">

      <AppPageHeader title="Roles List" subtitle="Manage system roles.">
        <template #actions>
          <AppButton @click="openModal('create')">
            <PlusIcon class="w-4 h-4" /> New Role
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filter bar -->
      <AppFilterBar>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search roles..."
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64"
        />
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="filteredRoles.length === 0" :skeleton-cols="4">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Role Name</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Created At</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="role in filteredRoles" :key="role.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ role.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ role.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ new Date(role.created_at).toLocaleDateString() }}</td>
          <td class="px-4 py-3">
            <div class="flex justify-center gap-1 items-center">
              <AppIconButton label="Edit role" @click="openModal('edit', role)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete role" variant="danger" @click="deleteRole(role)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="role in filteredRoles" :key="role.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs text-slate-500">#{{ role.id }}</p>
                <p class="font-semibold text-slate-800">{{ role.name }}</p>
                <p class="text-xs text-slate-400">{{ new Date(role.created_at).toLocaleDateString() }}</p>
              </div>
              <div class="flex items-center gap-1">
                <AppIconButton label="Edit role" @click="openModal('edit', role)">
                  <PencilSquareIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton label="Delete role" variant="danger" @click="deleteRole(role)">
                  <TrashIcon class="w-4 h-4" />
                </AppIconButton>
              </div>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No roles found" />
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

      <!-- Modal -->
      <AppModal :show="showModal" :title="modalMode === 'create' ? 'New Role' : 'Edit Role'" @close="closeModal">
        <form @submit.prevent="submitRole">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Role Name</label>
            <input
              v-model="form.name"
              type="text"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
              required
            />
          </div>
        </form>

        <template #footer>
          <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
          <AppButton @click.prevent="submitRole">Save</AppButton>
        </template>
      </AppModal>

    </div>
  </AdminLayout>
</template>

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
import PaginationControl from "@/Components/PaginationControl.vue"
import { PencilSquareIcon, TrashIcon, PlusIcon } from "@heroicons/vue/24/outline"
import { useRoles } from "@/Composables/useRoles.js"

// Props from backend (RolesController@index)
const props = defineProps({
  roles: Array
})

// Composable: all roles logic
const {
  rolesList,
  showModal,
  modalMode,
  selectedRole,
  searchQuery,
  currentPage,
  totalPages,
  filteredRoles,
  form,
  openModal,
  closeModal,
  submitRole,
  deleteRole,
} = useRoles(props)
</script>
