<script setup>
import { Head, usePage } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import {
  EyeIcon,
  PencilSquareIcon,
  TrashIcon,
  PlusIcon,
} from "@heroicons/vue/24/outline"
import { useUsers } from "@/Composables/useUsers.js"
import { ref, watch } from "vue"

const props = defineProps({
  users: Array,
  roles: Array,
  divisions: Array,
})

const {
  usersList,
  rolesList,
  divisionsList, // ✅ now from composable
  showModal,
  modalMode,
  selectedUser,
  searchQuery,
  currentPage,
  totalPages,
  filteredUsers,
  form,
  openModal,
  closeModal,
  submitUser,
  viewUser,
  deleteUser,
} = useUsers(props)

// Division chief watcher
const divisionChief = ref(null)
watch(
  () => form.division_id,
  (newDivision) => {
    if (!newDivision) {
      divisionChief.value = null
      return
    }
    const division = divisionsList.value.find((d) => d.id === newDivision)
    divisionChief.value = division?.chief ?? null
  }
)

const page = usePage()
const userRole = page.props.auth?.user?.role?.name ?? null
</script>


<template>
  <Head title="Users" />
  <AdminLayout title="Users">
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Users List</h1>
        <button
          @click="openModal('create')"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
        >
          <PlusIcon class="w-5 h-5 inline-block mr-1" /> New User
        </button>
      </div>

      <!-- Search -->
      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search users..."
          class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
        />

        <!-- Users Table -->
        <div class="overflow-x-auto mt-4">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-left">Email</th>
                <th class="px-4 py-3 text-left">Role</th>
                <th class="px-4 py-3 text-left">Position</th>
                <th class="px-4 py-3 text-left">Division</th>
                <th class="px-4 py-3 text-left">Office</th>
                <th class="px-4 py-3 text-left">Created At</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr
                v-for="user in filteredUsers"
                :key="user.id"
                class="hover:bg-gray-50"
              >
                <td class="px-4 py-3">{{ user.id }}</td>
                <td class="px-4 py-3">{{ user.name }}</td>
                <td class="px-4 py-3">{{ user.email }}</td>
                <td class="px-4 py-3">{{ user.role?.name ?? "—" }}</td>
                <td class="px-4 py-3">{{ user.position ?? "—" }}</td>
                <td class="px-4 py-3">{{ user.division?.division_name ?? "—" }}</td>
                <td class="px-4 py-3">{{ user.office ?? "—" }}</td>
                <td class="px-4 py-3">
                  {{ new Date(user.created_at).toLocaleDateString() }}
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-1 items-center">
                    <button
                      @click="viewUser(user)"
                      class="p-1 hover:bg-gray-100 rounded"
                    >
                      <EyeIcon class="w-5 h-5 text-blue-600" />
                    </button>
                    <button
                      @click="openModal('edit', user)"
                      class="p-1 hover:bg-gray-100 rounded"
                    >
                      <PencilSquareIcon class="w-5 h-5 text-yellow-600" />
                    </button>
                    <button
                      @click="deleteUser(user)"
                      class="p-1 hover:bg-gray-100 rounded"
                    >
                      <TrashIcon class="w-5 h-5 text-red-600" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredUsers.length === 0">
                <td
                  colspan="9"
                  class="px-4 py-6 text-center text-gray-500"
                >
                  No users found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center items-center gap-2 mt-4">
          <button
            @click="currentPage--"
            :disabled="currentPage === 1"
            class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
          >
            Prev
          </button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button
            @click="currentPage++"
            :disabled="currentPage === totalPages"
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
        <div
          class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative"
        >
          <button
            class="absolute top-3 right-3 text-gray-500 hover:text-gray-800"
            @click="closeModal"
          >
            ✕
          </button>
          <h2 class="text-xl font-semibold mb-4">
            {{
              modalMode === "create"
                ? "New User"
                : modalMode === "edit"
                ? "Edit User"
                : "View User"
            }}
          </h2>

          <!-- VIEW MODE -->
          <div
            v-if="modalMode === 'view' && selectedUser"
            class="space-y-2"
          >
            <p>Name: <strong>{{ selectedUser.name }}</strong></p>
            <p>Email: <strong>{{ selectedUser.email }}</strong></p>
            <p>Role: <strong>{{ selectedUser.role?.name ?? "—" }}</strong></p>
            <p>Position: <strong>{{ selectedUser.position ?? "—" }}</strong></p>
            <p>Division: <strong>{{ selectedUser.division?.name ?? "—" }}</strong></p>
            <p>
              Division Chief:
              <strong>{{ selectedUser.division?.chief?.name ?? "—" }}</strong>
            </p>
            <p>Office: <strong>{{ selectedUser.office ?? "—" }}</strong></p>
            <p>
              Created At:
              <strong>{{ new Date(selectedUser.created_at).toLocaleString() }}</strong>
            </p>
          </div>

          <!-- CREATE / EDIT FORM -->
          <form v-else @submit.prevent="submitUser" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Name</label>
              <input
                v-model="form.name"
                type="text"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Email</label>
              <input
                v-model="form.email"
                type="email"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Role</label>
              <select
                v-model="form.role_id"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
                required
              >
                <option value="">-- Select Role --</option>
                <option v-for="role in rolesList" :key="role.id" :value="role.id">
                  {{ role.name }}
                </option>
              </select>
            </div>

            <!-- Position -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Position</label>
              <input
                v-model="form.position"
                type="text"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
              />
            </div>

            <!-- Division -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Division</label>
              <select
                v-model="form.division_id"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
              >
                <option value="">-- Select Division --</option>
                <option
                  v-for="division in divisionsList"
                  :key="division.id"
                  :value="division.id"
                >
                  {{ division.division_name }}
                </option>
              </select>
            </div>

            <!-- Auto-display Division Chief -->
            <div v-if="divisionChief" class="text-sm text-gray-600">
              Division Chief: <strong>{{ divisionChief.name }}</strong>
            </div>

            <!-- Office (manual input) -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Office</label>
              <input
                v-model="form.office"
                type="text"
                placeholder="Enter office name"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
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
