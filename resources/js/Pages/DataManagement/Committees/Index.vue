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
      <div class="flex items-center justify-between mb-4 gap-2">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800 truncate">Committees</h1>
        <button @click="openModal('create')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">
          <PlusIcon class="w-5 h-5 inline-block mr-1" /> New Committee
        </button>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <input v-model="searchQuery" type="text" placeholder="Search committees..."
          class="w-1/3 rounded-lg border-gray-300 shadow-sm" />

        <div class="overflow-x-auto mt-4">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-left">Head</th>
                <th class="px-4 py-3 text-left">Members</th>
                <th class="px-4 py-3 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="committee in filteredCommittees" :key="committee.id">
                <td class="px-4 py-3">{{ committee.id }}</td>
                <td class="px-4 py-3 font-medium">{{ committee.name }}</td>
                <td class="px-4 py-3">{{ committee.head?.name ?? "—" }}</td>
                <td class="px-4 py-3">{{ committee.members?.length ?? 0 }}</td>
                <td class="px-4 py-3 text-center space-x-2">
                  <button @click="openModal('view', committee)" class="text-blue-600"><EyeIcon class="w-5 h-5" /></button>
                  <button @click="openModal('edit', committee)" class="text-yellow-600"><PencilSquareIcon class="w-5 h-5" /></button>
                  <button @click="deleteCommittee(committee)" class="text-red-600"><TrashIcon class="w-5 h-5" /></button>
                </td>
              </tr>
              <tr v-if="filteredCommittees.length === 0">
                <td colspan="5" class="px-4 py-6 text-center text-gray-500">No committees found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click="currentPage--" :disabled="currentPage === 1" class="px-3 py-1 bg-gray-200 rounded">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="currentPage++" :disabled="currentPage === totalPages" class="px-3 py-1 bg-gray-200 rounded">Next</button>
        </div>
      </div>

      <!-- MODAL -->
      <div v-show="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative max-h-screen overflow-y-auto">
          <button class="absolute top-3 right-3 text-gray-500" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">
            {{ modalMode === 'create' ? 'New Committee' : modalMode === 'edit' ? 'Edit Committee' : 'View Committee' }}
          </h2>

          <!-- VIEW MODE -->
          <div v-if="modalMode === 'view'" class="space-y-3">
            <p><strong>Name:</strong> {{ selectedCommittee?.name }}</p>
            <p><strong>Head:</strong> {{ selectedCommittee?.head?.name ?? "—" }}</p>
            <p><strong>Description:</strong> {{ selectedCommittee?.description ?? "—" }}</p>
            <div>
              <strong>Members:</strong>
              <ul v-if="selectedCommittee?.members?.length" class="mt-1 space-y-1">
                <li v-for="m in selectedCommittee.members" :key="m.id" class="text-sm text-gray-700">
                  {{ m.name }} <span v-if="m.pivot?.task" class="text-gray-400">— {{ m.pivot.task }}</span>
                </li>
              </ul>
              <p v-else class="text-gray-500 text-sm">No members.</p>
            </div>
          </div>

          <!-- FORM -->
          <form v-else @submit.prevent="submitCommittee" class="space-y-4">
            <div>
              <label class="font-medium">Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" required class="w-full mt-1 rounded-lg border-gray-300" />
            </div>
            <div>
              <label class="font-medium">Committee Head</label>
              <select v-model="form.head_id" class="w-full mt-1 rounded-lg border-gray-300">
                <option value="">— None —</option>
                <option v-for="u in props.users" :key="u.id" :value="u.id">{{ u.name }}<span v-if="u.position"> ({{ u.position }})</span></option>
              </select>
            </div>
            <div>
              <label class="font-medium">Description</label>
              <textarea v-model="form.description" rows="2" class="w-full mt-1 rounded-lg border-gray-300"></textarea>
            </div>

            <!-- Members -->
            <div>
              <label class="font-medium block mb-1">Members</label>
              <div class="border border-gray-300 rounded-lg p-2 max-h-52 overflow-y-auto space-y-2 text-sm">
                <div v-for="u in props.users" :key="u.id" class="flex items-start gap-2">
                  <input type="checkbox" :value="u.id" :checked="form.member_ids.includes(u.id)"
                    @change="toggleMember(u.id)" class="mt-1 rounded border-gray-300" />
                  <div class="flex-1">
                    <span>{{ u.name }}<span v-if="u.position" class="text-gray-400"> ({{ u.position }})</span></span>
                    <input v-if="form.member_ids.includes(u.id)" v-model="form.member_tasks[u.id]"
                      type="text" placeholder="Task / Role..." class="mt-1 w-full rounded border-gray-300 text-sm" />
                  </div>
                </div>
              </div>
              <p class="text-xs text-gray-400 mt-1">{{ form.member_ids.length }} member(s) selected</p>
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
