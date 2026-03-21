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
    ? 'bg-emerald-50 text-emerald-700'
    : 'bg-slate-100 text-slate-600'
}
</script>

<template>
  <Head title="Assign Roles" />
  <AdminLayout title="Assign Roles">
    <div class="flex gap-4 h-full">
      <!-- Left: User list -->
      <div class="flex-1 min-w-0">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
          <h1 class="text-xl font-semibold text-slate-800">Users</h1>
          <span class="text-sm text-slate-500">{{ pagination?.total ?? 0 }} total</span>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
          <div class="flex items-center gap-1 flex-1 min-w-0">
            <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 shrink-0" />
            <input v-model="search" @input="onSearch" type="text" placeholder="Search name, email, position…"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
          </div>
          <select v-model="filterRole" @change="onFilterChange"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
            <option value="">All roles</option>
            <option v-for="r in allRoles" :key="r.id" :value="r.name">{{ r.name }}</option>
          </select>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
          <div v-if="loading" class="py-16 text-center text-slate-400 text-sm">Loading…</div>
          <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap hidden sm:table-cell">Position</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Roles</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap text-center">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="user in users" :key="user.id"
                  @click="selectUser(user)"
                  class="hover:bg-indigo-50/40 cursor-pointer transition-colors"
                  :class="{ 'bg-indigo-50 ring-1 ring-inset ring-indigo-200': selected?.id === user.id }">
                  <td class="px-4 py-3 text-sm text-slate-700">
                    <div class="font-medium text-slate-800">{{ user.name }}</div>
                    <div class="text-xs text-slate-400">{{ user.email }}</div>
                  </td>
                  <td class="px-4 py-3 text-sm text-slate-500 hidden sm:table-cell">{{ user.position ?? '—' }}</td>
                  <td class="px-4 py-3 text-sm text-slate-600 max-w-[200px] truncate">{{ roleNames(user) }}</td>
                  <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium" :class="statusClass(user.status)">
                      {{ user.status ?? 'active' }}
                    </span>
                  </td>
                </tr>
                <tr v-if="users.length === 0">
                  <td colspan="4" class="py-16 text-center text-slate-400 text-sm">No users found.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="pagination && pagination.last_page > 1"
            class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
            <button @click="loadUsers(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">← Prev</button>
            <span>Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button @click="loadUsers(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Next →</button>
          </div>
        </div>
      </div>

      <!-- Right: Role assignment panel -->
      <div class="w-72 shrink-0">
        <div v-if="!selected" class="bg-white rounded-xl border border-slate-100 shadow-sm p-6 text-center text-slate-400 flex flex-col items-center gap-3 mt-11">
          <UserCircleIcon class="w-12 h-12 text-slate-200" />
          <p class="text-sm">Select a user to assign roles</p>
        </div>

        <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 sticky top-4">
          <div class="mb-4">
            <p class="font-semibold text-slate-800 truncate">{{ selected.name }}</p>
            <p class="text-xs text-slate-400 truncate">{{ selected.email }}</p>
            <p class="text-xs text-slate-500 mt-0.5">{{ selected.position ?? '—' }}</p>
          </div>

          <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">Assign Roles</h3>
          <div class="space-y-2 mb-4">
            <label v-for="role in allRoles" :key="role.id"
              class="flex items-center gap-2 cursor-pointer group">
              <input type="checkbox"
                :checked="userRoles.includes(role.id)"
                @change="toggleRole(role.id)"
                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-sm text-slate-700 group-hover:text-indigo-600 transition-colors">{{ role.name }}</span>
            </label>
          </div>

          <button @click="saveRoles" :disabled="syncing"
            class="w-full inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
            <CheckIcon class="w-4 h-4" />
            {{ syncing ? 'Saving…' : 'Save Roles' }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
