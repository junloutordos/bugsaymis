<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon } from "@heroicons/vue/24/outline"
import { useCommittees } from "@/Composables/useCommittees.js"

const props = defineProps({
  committees: Array,
  users: Array,
})

const {
  committeesList, form, showModal, modalMode, selectedCommittee,
  searchQuery, currentPage, totalPages, filteredCommittees,
  openModal, closeModal, toggleMember, submitCommittee, deleteCommittee,
} = useCommittees(props)
</script>

<template>
  <Head title="Committees" />
  <AdminLayout title="Committees">
    <div>
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Committees</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage committees and their members</p>
        </div>
        <button @click="openModal('create')"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <PlusIcon class="w-4 h-4" /> New Committee
        </button>
      </div>

      <!-- Table Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Search -->
        <div class="px-5 py-4 border-b border-slate-100">
          <input v-model="searchQuery" type="text" placeholder="Search committees..."
            class="w-full sm:w-80 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Head</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Members</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="committee in filteredCommittees" :key="committee.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ committee.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 font-medium">{{ committee.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ committee.head?.name ?? "—" }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ committee.members?.length ?? 0 }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1">
                    <button @click="openModal('view', committee)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="View">
                      <EyeIcon class="w-4 h-4" />
                    </button>
                    <button @click="openModal('edit', committee)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button @click="deleteCommittee(committee)"
                      class="p-1.5 rounded-lg hover:bg-red-50 text-red-400 hover:text-red-600 transition-colors" title="Delete">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredCommittees.length === 0">
                <td colspan="5" class="py-16 text-center text-slate-400 text-sm">No committees found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex items-center gap-2">
            <button @click="currentPage--" :disabled="currentPage === 1"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              Prev
            </button>
            <button @click="currentPage++" :disabled="currentPage === totalPages"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              Next
            </button>
          </div>
        </div>
      </div>

      <!-- MODAL -->
      <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-screen overflow-y-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{ modalMode === 'create' ? 'New Committee' : modalMode === 'edit' ? 'Edit Committee' : 'View Committee' }}
            </h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- VIEW MODE -->
          <div v-if="modalMode === 'view'" class="px-6 py-5 space-y-3 text-sm text-slate-700">
            <p><span class="font-medium text-slate-600">Name:</span> {{ selectedCommittee?.name }}</p>
            <p><span class="font-medium text-slate-600">Head:</span> {{ selectedCommittee?.head?.name ?? "—" }}</p>
            <p><span class="font-medium text-slate-600">Description:</span> {{ selectedCommittee?.description ?? "—" }}</p>
            <div>
              <p class="font-medium text-slate-600 mb-1">Members:</p>
              <ul v-if="selectedCommittee?.members?.length" class="mt-1 space-y-1">
                <li v-for="m in selectedCommittee.members" :key="m.id" class="text-sm text-slate-700">
                  {{ m.name }} <span v-if="m.pivot?.task" class="text-slate-400">— {{ m.pivot.task }}</span>
                </li>
              </ul>
              <p v-else class="text-slate-400 text-sm">No members.</p>
            </div>
          </div>

          <!-- FORM -->
          <form v-else @submit.prevent="submitCommittee" class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" required
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Committee Head</label>
              <select v-model="form.head_id"
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
              <button type="button" @click="closeModal"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Cancel
              </button>
              <button type="submit"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Save
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
