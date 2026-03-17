<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { MagnifyingGlassIcon, UserCircleIcon, CheckIcon } from '@heroicons/vue/24/outline'

// ─── State ───────────────────────────────────────────────────────────────────
const users      = ref([])
const pagination = ref(null)
const allRoles   = ref([])
const loading    = ref(true)
const search     = ref('')
const filterRole = ref('')
const searchTimer = ref(null)

// Selected user panel
const selected    = ref(null)
const userRoles   = ref([])   // current role ids for the selected user
const syncing     = ref(false)

// ─── Load ─────────────────────────────────────────────────────────────────────
async function loadUsers(page = 1) {
  loading.value = true
  const params = { page, per_page: 20 }
  if (search.value)     params.search = search.value
  if (filterRole.value) params.role   = filterRole.value
  const res = await axios.get(route('admin.rbac.users.index'), { params })
  users.value      = res.data.data
  pagination.value = res.data
  loading.value    = false
}

async function loadRoles() {
  const res = await axios.get(route('admin.rbac.roles.list'))
  allRoles.value = res.data
}

onMounted(async () => {
  await Promise.all([loadUsers(), loadRoles()])
})

// ─── Search with debounce ────────────────────────────────────────────────────
function onSearch() {
  clearTimeout(searchTimer.value)
  searchTimer.value = setTimeout(() => loadUsers(1), 350)
}

function onFilterChange() {
  loadUsers(1)
}

// ─── Select user and open side panel ─────────────────────────────────────────
async function selectUser(user) {
  selected.value  = user
  userRoles.value = user.roles.map(r => r.id)
}

function toggleRole(id) {
  const idx = userRoles.value.indexOf(id)
  if (idx === -1) userRoles.value.push(id)
  else userRoles.value.splice(idx, 1)
}

async function saveRoles() {
  syncing.value = true
  try {
    const res = await axios.put(
      route('admin.rbac.users.roles.sync', selected.value.id),
      { role_ids: userRoles.value }
    )
    // Update roles in the list too
    const idx = users.value.findIndex(u => u.id === selected.value.id)
    if (idx !== -1) users.value[idx].roles = res.data.roles
    selected.value.roles = res.data.roles
    Swal.fire({ icon: 'success', title: 'Roles updated', timer: 1200, showConfirmButton: false })
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message ?? 'Could not update roles.', 'error')
  } finally {
    syncing.value = false
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function roleNames(user) {
  return user.roles?.map(r => r.name).join(', ') || '—'
}

function statusClass(status) {
  return status === 'active'
    ? 'bg-green-100 text-green-700'
    : 'bg-gray-100 text-gray-500'
}
</script>

<template>
  <Head title="Assign Roles" />
  <AdminLayout title="Assign Roles">
    <div class="flex gap-4 h-full">
      <!-- Left: User list -->
      <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between mb-4">
          <h1 class="text-xl font-bold text-gray-800">Users</h1>
          <span class="text-sm text-gray-500">{{ pagination?.total ?? 0 }} total</span>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow p-3 mb-4 flex flex-wrap gap-2">
          <div class="flex items-center gap-1 flex-1 min-w-0">
            <MagnifyingGlassIcon class="w-4 h-4 text-gray-400 shrink-0" />
            <input v-model="search" @input="onSearch" type="text" placeholder="Search name, email, position…"
              class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <select v-model="filterRole" @change="onFilterChange"
            class="rounded-lg border-gray-300 text-sm shadow-sm focus:ring-blue-500 focus:border-blue-500">
            <option value="">All roles</option>
            <option v-for="r in allRoles" :key="r.id" :value="r.name">{{ r.name }}</option>
          </select>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow overflow-hidden">
          <div v-if="loading" class="text-center py-10 text-gray-400">Loading…</div>
          <table v-else class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
              <tr>
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-left hidden sm:table-cell">Position</th>
                <th class="px-4 py-3 text-left">Roles</th>
                <th class="px-4 py-3 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="user in users" :key="user.id"
                @click="selectUser(user)"
                class="hover:bg-blue-50 cursor-pointer transition-colors"
                :class="{ 'bg-blue-50 ring-1 ring-inset ring-blue-300': selected?.id === user.id }">
                <td class="px-4 py-3">
                  <div class="font-medium text-gray-800">{{ user.name }}</div>
                  <div class="text-xs text-gray-400">{{ user.email }}</div>
                </td>
                <td class="px-4 py-3 text-gray-500 hidden sm:table-cell">{{ user.position ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600 max-w-[200px] truncate">{{ roleNames(user) }}</td>
                <td class="px-4 py-3 text-center">
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusClass(user.status)">
                    {{ user.status ?? 'active' }}
                  </span>
                </td>
              </tr>
              <tr v-if="users.length === 0">
                <td colspan="4" class="text-center py-8 text-gray-400">No users found.</td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination -->
          <div v-if="pagination && pagination.last_page > 1"
            class="flex items-center justify-between px-4 py-3 border-t text-sm text-gray-600">
            <button @click="loadUsers(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="px-3 py-1 rounded border hover:bg-gray-50 disabled:opacity-40">← Prev</button>
            <span>Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button @click="loadUsers(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="px-3 py-1 rounded border hover:bg-gray-50 disabled:opacity-40">Next →</button>
          </div>
        </div>
      </div>

      <!-- Right: Role assignment panel -->
      <div class="w-72 shrink-0">
        <div v-if="!selected" class="bg-white rounded-xl shadow p-6 text-center text-gray-400 flex flex-col items-center gap-3 mt-11">
          <UserCircleIcon class="w-12 h-12 text-gray-200" />
          <p class="text-sm">Select a user to assign roles</p>
        </div>

        <div v-else class="bg-white rounded-xl shadow p-4 sticky top-4">
          <div class="mb-4">
            <p class="font-semibold text-gray-800 truncate">{{ selected.name }}</p>
            <p class="text-xs text-gray-400 truncate">{{ selected.email }}</p>
            <p class="text-xs text-gray-500 mt-0.5">{{ selected.position ?? '—' }}</p>
          </div>

          <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Assign Roles</h3>
          <div class="space-y-2 mb-4">
            <label v-for="role in allRoles" :key="role.id"
              class="flex items-center gap-2 cursor-pointer group">
              <input type="checkbox"
                :checked="userRoles.includes(role.id)"
                @change="toggleRole(role.id)"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
              <span class="text-sm text-gray-700 group-hover:text-blue-600 transition-colors">{{ role.name }}</span>
            </label>
          </div>

          <button @click="saveRoles" :disabled="syncing"
            class="w-full flex items-center justify-center gap-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm disabled:opacity-60">
            <CheckIcon class="w-4 h-4" />
            {{ syncing ? 'Saving…' : 'Save Roles' }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
