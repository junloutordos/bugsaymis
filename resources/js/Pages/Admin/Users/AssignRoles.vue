<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { MagnifyingGlassIcon, UserCircleIcon, CheckIcon, ShieldCheckIcon, KeyIcon } from '@heroicons/vue/24/outline'

// ─── State ───────────────────────────────────────────────────────────────────
const users      = ref([])
const pagination = ref(null)
const allRoles   = ref([])   // [{id,name,description,permissions:[{id,name,description}]}]
const loading    = ref(true)
const search     = ref('')
const filterRole = ref('')
const searchTimer = ref(null)

// Selected user panel
const selected    = ref(null)
const userRoles   = ref([])   // current role ids for the selected user
const syncing     = ref(false)

// Hover-preview for roles in the side panel
const hoveredRole = ref(null)

// ─── Computed ─────────────────────────────────────────────────────────────────
const previewRole = computed(() => {
  if (hoveredRole.value) return allRoles.value.find(r => r.id === hoveredRole.value)
  return null
})

// ─── Load ─────────────────────────────────────────────────────────────────────
async function loadUsers(page = 1) {
  loading.value = true
  const params = { page, per_page: 20 }
  if (search.value)     params.search = search.value
  if (filterRole.value) params.role   = filterRole.value
  const res = await axios.get('/admin/rbac/users', { params })
  users.value      = res.data.data
  pagination.value = res.data
  loading.value    = false
}

async function loadRoles() {
  const res = await axios.get('/admin/rbac/roles-list')
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
  hoveredRole.value = null
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
      `/admin/rbac/users/${selected.value.id}/roles`,
      { role_ids: userRoles.value }
    )
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

// Group permissions by module for preview
function groupedPerms(permissions) {
  if (!permissions?.length) return []
  const map = {}
  for (const p of permissions) {
    const mod = p.module ?? p.name.split('.').slice(0, -1).join('.') ?? 'Other'
    if (!map[mod]) map[mod] = []
    map[mod].push(p)
  }
  return Object.entries(map).sort((a, b) => a[0].localeCompare(b[0]))
}
</script>

<template>
  <Head title="Assign Roles" />
  <AdminLayout title="Roles & Permissions">

    <!-- Sub-navigation -->
    <div class="flex gap-1 mb-6 bg-white border border-slate-200 rounded-xl p-1 w-fit shadow-sm">
      <Link href="/admin/roles" class="px-4 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">Roles</Link>
      <Link href="/admin/permissions" class="px-4 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">Permissions</Link>
      <Link href="/admin/assign-roles" class="px-4 py-1.5 rounded-lg text-sm font-medium bg-indigo-600 text-white shadow-sm">Assign to Users</Link>
    </div>

    <!-- Page header -->
    <div class="mb-5">
      <h1 class="text-xl font-semibold text-slate-800">Assign Roles to Users</h1>
      <p class="text-sm text-slate-500 mt-0.5">Click a user to open the role assignment panel. Hover a role to preview its permissions.</p>
    </div>

    <div class="flex gap-4 min-h-0">
      <!-- Left: User list -->
      <div class="flex-1 min-w-0">
        <!-- Filters -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-3 mb-4 flex flex-wrap items-center gap-2">
          <div class="flex items-center gap-1 flex-1 min-w-[180px]">
            <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 shrink-0" />
            <input v-model="search" @input="onSearch" type="text" placeholder="Search name, email, position…"
              class="flex-1 border-none outline-none text-sm text-slate-800 placeholder-slate-400 bg-transparent" />
          </div>
          <select v-model="filterRole" @change="onFilterChange"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
            <option value="">All roles</option>
            <option v-for="r in allRoles" :key="r.id" :value="r.name">{{ r.name }}</option>
          </select>
          <span class="text-xs text-slate-400 shrink-0">{{ pagination?.total ?? 0 }} user(s)</span>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
          <div v-if="loading" class="py-16 text-center text-slate-400 text-sm">Loading…</div>
          <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap hidden sm:table-cell">Position</th>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Roles</th>
                  <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="user in users" :key="user.id"
                  @click="selectUser(user)"
                  class="hover:bg-indigo-50/40 cursor-pointer transition-colors"
                  :class="{ 'bg-indigo-50 ring-1 ring-inset ring-indigo-200': selected?.id === user.id }">
                  <td class="px-4 py-3">
                    <div class="font-medium text-slate-800">{{ user.name }}</div>
                    <div class="text-xs text-slate-400">{{ user.email }}</div>
                  </td>
                  <td class="px-4 py-3 text-slate-500 hidden sm:table-cell">{{ user.position ?? '—' }}</td>
                  <td class="px-4 py-3 text-slate-600 max-w-[200px]">
                    <div class="flex flex-wrap gap-1">
                      <span v-for="r in (user.roles ?? [])" :key="r.id"
                        class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-indigo-50 text-indigo-700">
                        {{ r.name }}
                      </span>
                      <span v-if="!(user.roles?.length)" class="text-slate-400 text-xs">—</span>
                    </div>
                  </td>
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

      <!-- Right panel: fixed width, relative so preview can float left without affecting layout -->
      <div class="w-72 shrink-0 self-start sticky top-4 relative">
        <!-- Empty state -->
        <div v-if="!selected" class="bg-white rounded-xl border border-slate-100 shadow-sm p-6 text-center text-slate-400 flex flex-col items-center gap-3">
          <UserCircleIcon class="w-12 h-12 text-slate-200" />
          <p class="text-sm">Select a user to assign roles</p>
        </div>

        <!-- Role assignment card -->
        <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
          <!-- User info -->
          <div class="pb-3 mb-3 border-b border-slate-100">
            <p class="font-semibold text-slate-800 truncate">{{ selected.name }}</p>
            <p class="text-xs text-slate-400 truncate">{{ selected.email }}</p>
            <p class="text-xs text-slate-500 mt-0.5">{{ selected.position ?? '—' }}</p>
          </div>

          <!-- Role checkboxes -->
          <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Assign Roles</h3>
          <div class="space-y-1 mb-4">
            <label v-for="role in allRoles" :key="role.id"
              class="flex items-center gap-2.5 cursor-pointer group rounded-lg px-2 py-1.5 transition-colors"
              :class="userRoles.includes(role.id) ? 'bg-indigo-50' : 'hover:bg-slate-50'"
              @mouseenter="hoveredRole = role.id"
              @mouseleave="hoveredRole = null">
              <input type="checkbox"
                :checked="userRoles.includes(role.id)"
                @change="toggleRole(role.id)"
                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 shrink-0" />
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5">
                  <ShieldCheckIcon class="w-3.5 h-3.5 text-indigo-400 shrink-0" />
                  <span class="text-sm font-medium text-slate-700 truncate group-hover:text-indigo-700 transition-colors">{{ role.name }}</span>
                  <span v-if="role.name === 'Administrator'"
                    class="text-[10px] font-semibold bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full shrink-0">Super</span>
                </div>
                <p v-if="role.description" class="text-[11px] text-slate-400 ml-5 truncate">{{ role.description }}</p>
              </div>
            </label>
          </div>

          <button @click="saveRoles" :disabled="syncing"
            class="w-full inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
            <CheckIcon class="w-4 h-4" />
            {{ syncing ? 'Saving…' : 'Save Roles' }}
          </button>
        </div>

        <!-- Permissions preview: absolutely positioned to the LEFT — never shifts the card or button -->
        <Transition enter-active-class="transition-all duration-150" enter-from-class="opacity-0 translate-x-2" enter-to-class="opacity-100 translate-x-0"
          leave-active-class="transition-all duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
          <div v-if="previewRole"
            class="absolute top-0 right-full mr-3 w-64 bg-white rounded-xl border border-indigo-200 shadow-lg p-4 z-10">
            <div class="flex items-center gap-2 mb-3">
              <KeyIcon class="w-4 h-4 text-indigo-500" />
              <p class="text-xs font-semibold text-indigo-700">{{ previewRole.name }} — Permissions</p>
            </div>
            <div v-if="previewRole.name === 'Administrator'" class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
              Administrator bypasses all permission checks — has access to everything.
            </div>
            <div v-else-if="!previewRole.permissions?.length" class="text-xs text-slate-400 text-center py-2">
              No permissions assigned to this role.
            </div>
            <div v-else class="space-y-2 max-h-64 overflow-y-auto pr-1">
              <div v-for="[mod, perms] in groupedPerms(previewRole.permissions)" :key="mod">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">{{ mod }}</p>
                <div class="flex flex-wrap gap-1">
                  <span v-for="p in perms" :key="p.id"
                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono bg-indigo-50 text-indigo-700 border border-indigo-100"
                    :title="p.description">
                    {{ p.name }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </div>

  </AdminLayout>
</template>
