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
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800 truncate">{{ props.headerTitle || 'Users List' }}</h1>
        <button
          v-if="!isInactivePage"
          @click="openModal('create')"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm shrink-0"
        >
          <PlusIcon class="w-4 h-4" />
          <span class="hidden sm:inline">{{ (props.headerTitle && String(props.headerTitle).toLowerCase().includes('employee')) ? 'New Employee' : 'New User' }}</span>
          <span class="sm:hidden">New</span>
        </button>
      </div>

      <!-- Search / filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search users..."
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64"
        />
      </div>

      <!-- Main card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Mobile Cards -->
        <div class="sm:hidden p-4 space-y-3">
          <div
            v-for="user in filteredUsers"
            :key="'m-' + user.id"
            class="border border-slate-100 rounded-lg p-3"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-semibold text-slate-800 truncate">{{ user.name }}</p>
                <p class="text-xs text-slate-500 truncate">{{ user.email }}</p>
              </div>
              <div class="flex gap-1 shrink-0">
                <button @click="viewUser(user)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
                  <EyeIcon class="w-4 h-4" />
                </button>
                <button @click="openModal('edit', user)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
                  <PencilSquareIcon class="w-4 h-4" />
                </button>
                <button @click="deleteUser(user)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
            <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-slate-500">
              <span v-if="user.position">{{ user.position }}</span>
              <span v-if="user.division?.division_name">{{ user.division.division_name }}</span>
            </div>
          </div>
          <p v-if="filteredUsers.length === 0" class="py-16 text-center text-slate-400 text-sm">No users found.</p>
        </div>

        <!-- Desktop Table -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Sex</th>
                <th v-if="!isEmployeesPage" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Email</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Position</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Division</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Office</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Created At</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="user in filteredUsers"
                :key="user.id"
                class="hover:bg-slate-50/60"
              >
                <td class="px-4 py-3 text-sm text-slate-700">{{ user.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ user.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
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
                <td v-if="!isEmployeesPage" class="px-4 py-3 text-sm text-slate-700">{{ user.email }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ user.position ?? "—" }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ user.division?.division_name ?? "—" }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ user.office?.name ?? user.office ?? "—" }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  {{ new Date(user.created_at).toLocaleDateString() }}
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-1 items-center">
                    <template v-if="!isInactivePage">
                      <button
                        @click="viewUser(user)"
                        class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
                      >
                        <EyeIcon class="w-4 h-4" />
                      </button>
                      <button
                        @click="openModal('edit', user)"
                        class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
                      >
                        <PencilSquareIcon class="w-4 h-4" />
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
                          class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          <ArrowUpOnSquareIcon class="w-4 h-4" />
                        </button>
                      </div>
                    </template>
                    <template v-if="isInactivePage">
                      <button
                        @click="activateUser(user)"
                        class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-emerald-600 transition-colors"
                        title="Activate user"
                      >
                        <PlusIcon class="w-4 h-4" />
                      </button>
                    </template>
                    <template v-else>
                      <button
                        @click="deleteUser(user)"
                        class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors"
                      >
                        <TrashIcon class="w-4 h-4" />
                      </button>
                    </template>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredUsers.length === 0">
                <td
                  :colspan="isEmployeesPage ? 8 : 9"
                  class="py-16 text-center text-slate-400 text-sm"
                >
                  No users found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <button
            @click="currentPage--"
            :disabled="currentPage === 1"
            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40"
          >
            Prev
          </button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button
            @click="currentPage++"
            :disabled="currentPage === totalPages"
            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40"
          >
            Next
          </button>
        </div>
      </div>

      <!-- Modal -->
      <div
        v-show="showModal"
        class="fixed inset-0 flex items-start sm:items-center justify-center bg-slate-900/50 z-50 p-4 sm:p-0"
      >
        <div
          class="bg-white rounded-2xl shadow-xl w-full max-w-3xl sm:max-w-md relative max-h-[90vh] overflow-auto"
        >
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{
                modalMode === "create"
                  ? ((props.headerTitle && String(props.headerTitle).toLowerCase().includes('employee')) ? 'New Employee' : 'New User')
                  : modalMode === "edit"
                  ? "Edit User"
                  : "View User"
              }}
            </h2>
            <button
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
              @click="closeModal"
            >
              ✕
            </button>
          </div>

          <div class="px-6 py-5">
            <!-- VIEW MODE -->
            <div
              v-if="modalMode === 'view' && selectedUser"
              class="space-y-2"
            >
              <div class="flex items-center gap-4">
                <div>
                  <p class="text-xs font-medium text-slate-600 mb-1">Profile Picture</p>
                  <div class="w-24 h-24 bg-slate-50 rounded overflow-hidden border border-slate-200">
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
                    <div v-else class="w-full h-full flex items-center justify-center text-slate-400 text-xs">No image</div>
                  </div>
                </div>
                <div>
                  <p class="text-xs font-medium text-slate-600 mb-1">Electronic Signature</p>
                  <div class="w-48 h-16 bg-white rounded overflow-hidden border border-slate-200 flex items-center justify-center">
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
                    <div v-else class="text-slate-400 text-xs">No signature</div>
                  </div>
                </div>
              </div>
              <p class="text-sm text-slate-700">Name: <strong>{{ selectedUser.name }}</strong></p>
              <p class="text-sm text-slate-700">Sex: <strong>{{ selectedUser.sex }}</strong></p>
              <p class="text-sm text-slate-700">Email: <strong>{{ selectedUser.email }}</strong></p>
              <p class="text-sm text-slate-700">Biometric ID: <strong>{{ selectedUser.badge_id ?? '—' }}</strong></p>
              <p class="text-sm text-slate-700">Role: <strong>{{ getRoleNames(selectedUser) }}</strong></p>
              <p class="text-sm text-slate-700">Position: <strong>{{ selectedUser.position ?? "—" }}</strong></p>
              <p class="text-sm text-slate-700">Division: <strong>{{ selectedUser.division?.name ?? "—" }}</strong></p>
              <p class="text-sm text-slate-700">
                Division Chief:
                <strong>{{ selectedUser.division?.chief?.name ?? "—" }}</strong>
              </p>
              <p class="text-sm text-slate-700">Office: <strong>{{ selectedUser.office?.name ?? selectedUser.office ?? "—" }}</strong></p>
              <p class="text-sm text-slate-700">
                Created At:
                <strong>{{ new Date(selectedUser.created_at).toLocaleString() }}</strong>
              </p>
            </div>

            <!-- CREATE / EDIT FORM -->
            <form v-else @submit.prevent="submitUser" class="space-y-4 sm:grid sm:grid-cols-2 sm:gap-4 sm:space-y-0">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
                <input
                  v-model="form.name"
                  type="text"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                  required
                />
              </div>

              <div v-if="!isEmployeesPage">
                <label class="block text-xs font-medium text-slate-600 mb-1">Biometric ID</label>
                <input
                  v-model="form.badge_id"
                  type="text"
                  placeholder=""
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                />
                <p class="text-xs text-slate-400 mt-1">Alphanumeric, dashes and underscores allowed.</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Sex</label>
                <select
                  v-model="form.sex"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                  required
                >
                  <option value="">-- Select Sex --</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
              <div class="sm:col-span-2" v-if="!isEmployeesPage">
                <label class="block text-xs font-medium text-slate-600 mb-1">Email</label>
                <input
                  v-model="form.email"
                  type="email"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                  required
                />
              </div>

              <!-- Position -->
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Position</label>
                <input
                  v-model="form.position"
                  type="text"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                />
              </div>

              <!-- Specialization -->
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Specialization</label>
                <input
                  v-model="form.specialization"
                  type="text"
                  placeholder="e.g. Mathematics, Biology, English"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                />
                <p class="text-xs text-slate-400 mt-0.5">Used for auto-assignment of teaching loads.</p>
              </div>

              <!-- Division -->
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Division</label>
                <select
                  v-model="form.division_id"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
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
              <div v-if="divisionChief" class="text-sm text-slate-600 sm:col-span-2">
                Division Chief: <strong>{{ divisionChief.name }}</strong>
              </div>

              <!-- Office (select filtered by Division) -->
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Office</label>
                <select
                  v-model="form.office_id"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
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
                <label class="block text-xs font-medium text-slate-600 mb-1">Employee Category</label>
                <select v-model="form.emp_category" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full">
                  <option value="">-- Select Category --</option>
                  <option value="Plantilla Teaching">Plantilla Teaching</option>
                  <option value="Plantilla Non-Teaching">Plantilla Non-Teaching</option>
                  <option value="COS Teaching">COS Teaching</option>
                  <option value="COS Non Teaching">COS Non Teaching</option>
                </select>
              </div>

              <div class="flex justify-end gap-2 pt-4 sm:col-span-2">
                <button
                  type="button"
                  @click="closeModal"
                  class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
                >
                  Save
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
