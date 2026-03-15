<script setup>
import { Head, usePage } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import {
  EyeIcon,
  PencilSquareIcon,
  TrashIcon,
  PlusIcon,
  ArrowUpOnSquareIcon,
} from "@heroicons/vue/24/outline"
import { useUsers } from "@/Composables/useUsers.js"
import { ref, watch, computed } from "vue"
import { router } from "@inertiajs/vue3"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  users: Array,
  roles: Array,
  divisions: Array,
  offices: Array,
  pageTitle: { type: String, default: 'Users' },
  headerTitle: { type: String, default: 'Users List' },
})

  const {
  usersList,
  rolesList,
  divisionsList,
  officesList,
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
  activateUser,
  isEmployeesPage,
  isInactivePage,
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

  const filteredOffices = computed(() => {
    const divId = form.value.division_id
    if (!divId) return []
    return officesList.value.filter((o) => o.division_id === divId)
  })

const { isSubmitting: isUploading, submit: submitUpload } = useSubmit()

const page = usePage()
const userRole = page.props.auth?.user?.role?.name ?? null

const rolesMap = computed(() => Object.fromEntries(rolesList.value.map(r => [String(r.id), r.name])))

const getRoleNames = (user) => {
  if (!user) return '—'
  if (user.role && user.role.name) return user.role.name
  if (user.role_id) {
    return user.role_id.toString().split(',').map(id => rolesMap.value[id.trim()] ?? id.trim()).join(', ')
  }
  return '—'
}

const handleUpload = (user, e) => {
  const file = e.target.files && e.target.files[0] ? e.target.files[0] : null
  if (!file) return

  const fd = new FormData()
  fd.append('electronic_signature', file)

  submitUpload(
    (o) => router.post(`/users/${user.id}/upload-signature`, fd, { ...o, preserveState: false }),
    {
      onSuccess: () => { window.location.reload() },
      onError: (errors) => {
        const msg = errors && Object.values(errors).flat().join(', ')
        alert(msg || 'Failed to upload signature')
      }
    }
  )
}

const openSignaturePicker = (user) => {
  try {
    const id = 'sig-input-' + user.id
    console.log('openSignaturePicker called for', id)
    const el = document.getElementById(id)
    if (!el) {
      console.warn('signature input element not found', id)
      return
    }
    // ensure clickable invocation from user gesture
    el.click()
  } catch (err) {
    console.error('openSignaturePicker error', err)
  }
}
</script>


<template>
    <Head :title="props.pageTitle || 'Users'" />
    <AdminLayout :title="props.pageTitle || 'Users'">
    <div>
      <!-- Header -->
      <div class="flex items-center justify-between mb-4 gap-2">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800 truncate">{{ props.headerTitle || 'Users List' }}</h1>
        <button
          v-if="!isInactivePage"
          @click="openModal('create')"
          class="shrink-0 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 md:px-4 rounded-lg shadow text-sm md:text-base"
        >
          <PlusIcon class="w-4 h-4 md:w-5 md:h-5 inline-block mr-1" />
          <span class="hidden sm:inline">{{ (props.headerTitle && String(props.headerTitle).toLowerCase().includes('employee')) ? 'New Employee' : 'New User' }}</span>
          <span class="sm:hidden">New</span>
        </button>
      </div>

      <!-- Search -->
      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search users..."
          class="w-full sm:w-1/2 md:w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
        />

        <!-- Mobile Cards -->
        <div class="sm:hidden mt-4 space-y-3">
          <div
            v-for="user in filteredUsers"
            :key="'m-' + user.id"
            class="border border-gray-200 rounded-lg p-3"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-semibold text-gray-800 truncate">{{ user.name }}</p>
                <p class="text-xs text-gray-500 truncate">{{ user.email }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ getRoleNames(user) }}</p>
              </div>
              <div class="flex gap-1 shrink-0">
                <button @click="viewUser(user)" class="p-1 hover:bg-gray-100 rounded">
                  <EyeIcon class="w-4 h-4 text-blue-600" />
                </button>
                <button @click="openModal('edit', user)" class="p-1 hover:bg-gray-100 rounded">
                  <PencilSquareIcon class="w-4 h-4 text-yellow-600" />
                </button>
                <button @click="deleteUser(user)" class="p-1 hover:bg-gray-100 rounded">
                  <TrashIcon class="w-4 h-4 text-red-600" />
                </button>
              </div>
            </div>
            <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-gray-500">
              <span v-if="user.position">{{ user.position }}</span>
              <span v-if="user.division?.division_name">{{ user.division.division_name }}</span>
            </div>
          </div>
          <p v-if="filteredUsers.length === 0" class="text-center text-gray-500 py-4 text-sm">No users found.</p>
        </div>

        <!-- Desktop Table -->
        <div class="hidden sm:block overflow-x-auto mt-4">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-left">Sex</th>
                <th v-if="!isEmployeesPage" class="px-4 py-3 text-left">Email</th>
                <th v-if="!isEmployeesPage" class="px-4 py-3 text-left">Role</th>
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
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <svg
                      v-if="user.sex === 'Male'"
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 64 64"
                      class="w-5 h-5 text-blue-500"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="5"
                    >
                      <circle cx="26" cy="38" r="14" />
                      <line x1="36" y1="28" x2="54" y2="10" />
                      <line x1="42" y1="10" x2="54" y2="10" />
                      <line x1="54" y1="10" x2="54" y2="22" />
                    </svg>

                    <svg
                      v-else-if="user.sex === 'Female'"
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 64 64"
                      class="w-5 h-5 text-pink-500"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="5"
                    >
                      <circle cx="32" cy="24" r="14" />
                      <line x1="32" y1="38" x2="32" y2="56" />
                      <line x1="22" y1="46" x2="42" y2="46" />
                    </svg>

                    
                  </div>
                </td>
                <td v-if="!isEmployeesPage" class="px-4 py-3">{{ user.email }}</td>
                <td v-if="!isEmployeesPage" class="px-4 py-3">{{ getRoleNames(user) }}</td>
                <td class="px-4 py-3">{{ user.position ?? "—" }}</td>
                <td class="px-4 py-3">{{ user.division?.division_name ?? "—" }}</td>
                <td class="px-4 py-3">{{ user.office?.name ?? user.office ?? "—" }}</td>
                <td class="px-4 py-3">
                  {{ new Date(user.created_at).toLocaleDateString() }}
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-1 items-center">
                    <template v-if="!isInactivePage">
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
                      <!-- Upload signature button -->
                      <div class="relative">
                        <input
                          :id="'sig-input-' + user.id"
                          type="file"
                          accept=".png,image/png"
                          class="hidden"
                          @change="(e) => handleUpload(user, e)"
                        />
                        <button
                          @click.prevent="openSignaturePicker(user)"
                          :disabled="isUploading"
                          title="Upload signature (PNG)"
                          class="p-1 hover:bg-gray-100 rounded disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          <ArrowUpOnSquareIcon class="w-5 h-5 text-green-600" />
                        </button>
                      </div>
                    </template>
                    <template v-if="isInactivePage">
                      <button
                        @click="activateUser(user)"
                        class="p-1 hover:bg-gray-100 rounded"
                        title="Activate user"
                      >
                        <PlusIcon class="w-5 h-5 text-green-600" />
                      </button>
                    </template>
                    <template v-else>
                      <button
                        @click="deleteUser(user)"
                        class="p-1 hover:bg-gray-100 rounded"
                      >
                        <TrashIcon class="w-5 h-5 text-red-600" />
                      </button>
                    </template>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredUsers.length === 0">
                <td
                  :colspan="isEmployeesPage ? 8 : 10"
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
        class="fixed inset-0 flex items-start sm:items-center justify-center bg-black bg-opacity-50 z-50 transition-opacity p-4 sm:p-0"
      >
        <div
          class="bg-white rounded-xl shadow-lg w-full max-w-3xl sm:max-w-md p-4 sm:p-6 relative max-h-[90vh] overflow-auto"
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
                ? ((props.headerTitle && String(props.headerTitle).toLowerCase().includes('employee')) ? 'New Employee' : 'New User')
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
            <div class="flex items-center gap-4">
              <div>
                <p class="text-sm text-gray-600">Profile Picture</p>
                <div class="w-24 h-24 bg-gray-100 rounded overflow-hidden border">
                  <img
                    v-if="selectedUser.profile_picture"
                    :src="(
                      selectedUser.profile_picture.indexOf('http') === 0
                        ? selectedUser.profile_picture
                        : (selectedUser.profile_picture.startsWith('profile_pictures/')
                            ? '/storage/' + selectedUser.profile_picture
                            : '/storage/profile_pictures/' + selectedUser.profile_picture)
                    )"
                    alt="profile"
                    class="w-full h-full object-cover"
                  />
                  <div v-else class="w-full h-full flex items-center justify-center text-gray-400">No image</div>
                </div>
              </div>
              <div>
                <p class="text-sm text-gray-600">Electronic Signature</p>
                <div class="w-48 h-16 bg-white rounded overflow-hidden border flex items-center justify-center">
                  <img
                    v-if="selectedUser.electronic_signature"
                    :src="(
                      selectedUser.electronic_signature.indexOf('http') === 0
                        ? selectedUser.electronic_signature
                        : (selectedUser.electronic_signature.startsWith('signatures/')
                            ? '/storage/' + selectedUser.electronic_signature
                            : '/storage/signatures/' + selectedUser.electronic_signature)
                    )"
                    alt="signature"
                    class="max-h-12"
                  />
                  <div v-else class="text-gray-400">No signature</div>
                </div>
              </div>
            </div>
            <p>Name: <strong>{{ selectedUser.name }}</strong></p>
            <p>Sex: <strong>{{ selectedUser.sex }}</strong></p>
            <p>Email: <strong>{{ selectedUser.email }}</strong></p>
            <p>Biometric ID: <strong>{{ selectedUser.badge_id ?? '—' }}</strong></p>
            <p>Role: <strong>{{ getRoleNames(selectedUser) }}</strong></p>
            <p>Position: <strong>{{ selectedUser.position ?? "—" }}</strong></p>
            <p>Division: <strong>{{ selectedUser.division?.name ?? "—" }}</strong></p>
            <p>
              Division Chief:
              <strong>{{ selectedUser.division?.chief?.name ?? "—" }}</strong>
            </p>
            <p>Office: <strong>{{ selectedUser.office?.name ?? selectedUser.office ?? "—" }}</strong></p>
            <p>
              Created At:
              <strong>{{ new Date(selectedUser.created_at).toLocaleString() }}</strong>
            </p>
          </div>

          <!-- CREATE / EDIT FORM -->
          <form v-else @submit.prevent="submitUser" class="space-y-4 sm:space-y-0 sm:grid sm:grid-cols-2 sm:gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Name</label>
              <input
                v-model="form.name"
                type="text"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
                required
              />
            </div>

            <div v-if="!isEmployeesPage">
              <label class="block text-sm font-medium text-gray-700">Biometric ID</label>
              <input
                v-model="form.badge_id"
                type="text"
                placeholder=""
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
              />
              <p class="text-xs text-gray-500 mt-1">Alphanumeric, dashes and underscores allowed.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Sex</label>
              <select
                v-model="form.sex"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
                required
              >
                <option value="">-- Select Sex --</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
            <div class="sm:col-span-2" v-if="!isEmployeesPage">
              <label class="block text-sm font-medium text-gray-700">Email</label>
              <input
                v-model="form.email"
                type="email"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
                required
              />
            </div>

            <div class="sm:col-span-2" v-if="!isEmployeesPage">
              <label class="block text-sm font-medium text-gray-700">Role</label>
              <select
                v-model="form.role_id"
                multiple
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
                required
              >
                <option v-for="role in rolesList" :key="role.id" :value="role.id">{{ role.name }}</option>
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
            <div v-if="divisionChief" class="text-sm text-gray-600 sm:col-span-2">
              Division Chief: <strong>{{ divisionChief.name }}</strong>
            </div>

            <!-- Office (select filtered by Division) -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Office</label>
              <select
                v-model="form.office_id"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
              >
                <option value="">-- Select Office --</option>
                <option v-if="!form.division_id" disabled value="">Select a division first</option>
                <option
                  v-for="office in filteredOffices"
                  :key="office.id"
                  :value="office.id"
                >
                  {{ office.name }}
                </option>
                <option v-if="form.division_id && filteredOffices.length === 0" disabled>
                  No offices for this division
                </option>
              </select>
            </div>

            <!-- Employee Category -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Employee Category</label>
              <select v-model="form.emp_category" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                <option value="">-- Select Category --</option>
                <option value="Plantilla Teaching">Plantilla Teaching</option>
                <option value="Plantilla Non-Teaching">Plantilla Non-Teaching</option>
                <option value="COS Teaching">COS Teaching</option>
                <option value="COS Non Teaching">COS Non Teaching</option>
              </select>
            </div>

            <div class="flex justify-end space-x-3 pt-4 sm:col-span-2">
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
