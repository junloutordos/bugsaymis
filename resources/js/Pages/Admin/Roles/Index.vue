<template>
  <Head title="Roles & Permissions" />
  <AdminLayout title="Roles & Permissions">

    <!-- Sub-navigation -->
    <div class="flex gap-1 mb-6 bg-white border border-slate-200 rounded-xl p-1 w-fit shadow-sm">
      <Link href="/admin/roles" class="px-4 py-1.5 rounded-lg text-sm font-medium bg-indigo-600 text-white shadow-sm">Roles</Link>
      <Link href="/admin/permissions" class="px-4 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">Permissions</Link>
      <Link href="/admin/assign-roles" class="px-4 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">Assign to Users</Link>
    </div>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
      <div>
        <h1 class="text-xl font-semibold text-slate-800">Roles</h1>
        <p class="text-sm text-slate-500 mt-0.5">Define roles and assign permissions to each role.</p>
      </div>
      <button @click="openCreate"
        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
        <PlusIcon class="w-4 h-4" /> New Role
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-20 text-center text-slate-400 text-sm">Loading…</div>

    <template v-else>
      <!-- Search -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3 mb-4 flex items-center gap-2">
        <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 shrink-0" />
        <input v-model="search" type="text" placeholder="Search roles…"
          class="flex-1 border-none outline-none text-sm text-slate-800 placeholder-slate-400 bg-transparent" />
        <span class="text-xs text-slate-400">{{ filtered.length }} role(s)</span>
      </div>

      <!-- Roles grid -->
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="role in filtered" :key="role.id"
          class="bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col group hover:border-indigo-300 transition-colors">

          <!-- Card header -->
          <div class="px-4 pt-4 pb-3 border-b border-slate-100">
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="flex items-center gap-2">
                  <ShieldCheckIcon class="w-4 h-4 text-indigo-500 shrink-0" />
                  <span class="font-semibold text-slate-800">{{ role.name }}</span>
                  <span v-if="role.name === 'Administrator'"
                    class="text-[10px] font-semibold bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">Super</span>
                </div>
                <p class="text-xs text-slate-400 mt-0.5 ml-6">{{ role.description || 'No description' }}</p>
              </div>
              <!-- Actions -->
              <div class="flex items-center gap-1 shrink-0">
                <button @click="openEdit(role)" title="Edit role"
                  class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition-colors">
                  <PencilSquareIcon class="w-4 h-4" />
                </button>
                <button @click="deleteRole(role)" title="Delete role"
                  :disabled="role.name === 'Administrator'"
                  class="p-1.5 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-600 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Stats + action -->
          <div class="px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-4 text-xs text-slate-500">
              <span class="flex items-center gap-1">
                <KeyIcon class="w-3.5 h-3.5" />
                {{ role.permissions_count }} permission{{ role.permissions_count !== 1 ? 's' : '' }}
              </span>
              <span class="flex items-center gap-1">
                <UsersIcon class="w-3.5 h-3.5" />
                {{ role.users_count }} user{{ role.users_count !== 1 ? 's' : '' }}
              </span>
            </div>
            <button @click="openPermissions(role)"
              class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors">
              <ShieldCheckIcon class="w-3.5 h-3.5" />
              Edit Permissions
            </button>
          </div>
        </div>

        <!-- Empty -->
        <div v-if="filtered.length === 0" class="col-span-3 py-16 text-center text-slate-400 text-sm">
          No roles found.
        </div>
      </div>
    </template>

    <!-- ── Role create/edit modal ────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showRoleModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ modalMode === 'create' ? 'New Role' : 'Edit Role' }}</h2>
            <button @click="showRoleModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
          <div class="px-6 py-5">
            <form @submit.prevent="submitRole" class="space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Role Name <span class="text-red-500">*</span></label>
                <input v-model="form.name" type="text" required maxlength="100" placeholder="e.g. Librarian"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <input v-model="form.description" type="text" maxlength="255" placeholder="Brief description of this role"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div class="flex justify-end gap-2 pt-1">
                <button type="button" @click="showRoleModal = false"
                  class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                <button type="submit" :disabled="saving"
                  class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium disabled:opacity-60">
                  {{ saving ? 'Saving…' : (modalMode === 'create' ? 'Create Role' : 'Save Changes') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Permission assignment modal ──────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showPermModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col max-h-[90vh]">

          <!-- Header -->
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
            <div>
              <h2 class="text-base font-semibold text-slate-800">
                Permissions — <span class="text-indigo-600">{{ editingRole?.name }}</span>
              </h2>
              <p class="text-xs text-slate-400 mt-0.5">{{ rolePerms.length }} of {{ totalPerms }} permissions selected</p>
            </div>
            <div class="flex items-center gap-2">
              <div class="relative">
                <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
                <input v-model="permSearch" type="text" placeholder="Filter…"
                  class="pl-8 pr-3 py-1.5 rounded-lg border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-400 w-40" />
              </div>
              <button @click="showPermModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700">
                <XMarkIcon class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Permission groups -->
          <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">
            <div v-if="editingRole?.name === 'Administrator'"
              class="flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-700 rounded-lg px-4 py-3 text-sm">
              <ShieldCheckIcon class="w-4 h-4 shrink-0" />
              Administrator bypasses all permission checks — has full access regardless of assignments.
            </div>

            <div v-for="group in filteredPerms" :key="group.module">
              <!-- Module header -->
              <label class="flex items-center gap-2 mb-2.5 cursor-pointer group">
                <input type="checkbox"
                  :checked="moduleChecked(group)"
                  :indeterminate.prop="moduleIndeterminate(group)"
                  @change="toggleModule(group)"
                  class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4" />
                <span class="text-xs font-bold uppercase tracking-widest text-slate-700">{{ group.module }}</span>
                <span class="text-xs text-slate-400 font-normal ml-1">
                  ({{ group.permissions.filter(p => rolePerms.includes(p.id)).length }}/{{ group.permissions.length }})
                </span>
              </label>
              <!-- Permission items -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 pl-6">
                <label v-for="perm in group.permissions" :key="perm.id"
                  class="flex items-start gap-2 cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors"
                  :class="{ 'bg-indigo-50/60 border border-indigo-100': rolePerms.includes(perm.id) }">
                  <input type="checkbox"
                    :checked="rolePerms.includes(perm.id)"
                    @change="togglePerm(perm.id)"
                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 mt-0.5 shrink-0" />
                  <div class="min-w-0">
                    <div class="text-xs text-slate-700 font-medium leading-tight">{{ perm.description || perm.name }}</div>
                    <div class="text-[10px] font-mono text-slate-400 mt-0.5">{{ perm.name }}</div>
                  </div>
                </label>
              </div>
            </div>

            <p v-if="filteredPerms.length === 0" class="text-center text-slate-400 text-sm py-8">No permissions match your filter.</p>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between shrink-0">
            <button @click="selectAllPerms" class="text-xs text-slate-500 hover:text-indigo-600 transition-colors">
              {{ rolePerms.length === totalPerms ? 'Deselect all' : 'Select all' }}
            </button>
            <div class="flex gap-2">
              <button @click="showPermModal = false"
                class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
              <button @click="savePermissions" :disabled="syncingPerms"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium disabled:opacity-60">
                <span v-if="syncingPerms" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                {{ syncingPerms ? 'Saving…' : 'Save Permissions' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import {
  PlusIcon, PencilSquareIcon, TrashIcon,
  ShieldCheckIcon, MagnifyingGlassIcon,
  XMarkIcon, KeyIcon, UsersIcon,
} from '@heroicons/vue/24/outline'

// ─── State ───────────────────────────────────────────────────────────────────
const roles    = ref([])
const allPerms = ref([])
const loading  = ref(true)
const search   = ref('')

const showRoleModal = ref(false)
const modalMode     = ref('create')
const form          = ref({ id: null, name: '', description: '' })
const saving        = ref(false)

const showPermModal = ref(false)
const editingRole   = ref(null)
const rolePerms     = ref([])
const permSearch    = ref('')
const syncingPerms  = ref(false)

// ─── Computed ────────────────────────────────────────────────────────────────
const filtered = computed(() =>
  roles.value.filter(r => r.name.toLowerCase().includes(search.value.toLowerCase()))
)

const totalPerms = computed(() => allPerms.value.reduce((n, g) => n + g.permissions.length, 0))

const filteredPerms = computed(() => {
  if (!permSearch.value) return allPerms.value
  const q = permSearch.value.toLowerCase()
  return allPerms.value
    .map(g => ({
      ...g,
      permissions: g.permissions.filter(p =>
        p.name.toLowerCase().includes(q) || p.description?.toLowerCase().includes(q)
      ),
    }))
    .filter(g => g.permissions.length > 0)
})

// ─── Load ─────────────────────────────────────────────────────────────────────
async function loadRoles() {
  loading.value = true
  const [rolesRes, permsRes] = await Promise.all([
    axios.get('/admin/rbac/roles'),
    axios.get('/admin/rbac/permissions-all'),
  ])
  roles.value    = rolesRes.data
  allPerms.value = permsRes.data
  loading.value  = false
}
onMounted(loadRoles)

// ─── Role CRUD ────────────────────────────────────────────────────────────────
function openCreate() {
  form.value      = { id: null, name: '', description: '' }
  modalMode.value = 'create'
  showRoleModal.value = true
}

function openEdit(role) {
  form.value      = { id: role.id, name: role.name, description: role.description ?? '' }
  modalMode.value = 'edit'
  showRoleModal.value = true
}

async function submitRole() {
  saving.value = true
  try {
    if (modalMode.value === 'create') {
      const res = await axios.post('/admin/rbac/roles', form.value)
      roles.value.push({ ...res.data, permissions_count: 0, users_count: 0 })
    } else {
      await axios.put(`/admin/rbac/roles/${form.value.id}`, form.value)
      const idx = roles.value.findIndex(r => r.id === form.value.id)
      if (idx !== -1) Object.assign(roles.value[idx], form.value)
    }
    showRoleModal.value = false
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message ?? 'Something went wrong.', 'error')
  } finally {
    saving.value = false
  }
}

async function deleteRole(role) {
  const result = await Swal.fire({
    title: `Delete "${role.name}"?`,
    text: 'This will detach all users and permissions from this role.',
    icon: 'warning', showCancelButton: true,
    confirmButtonText: 'Delete', confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return
  try {
    await axios.delete(`/admin/rbac/roles/${role.id}`)
    roles.value = roles.value.filter(r => r.id !== role.id)
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message ?? 'Cannot delete this role.', 'error')
  }
}

// ─── Permission assignment ────────────────────────────────────────────────────
async function openPermissions(role) {
  editingRole.value = role
  permSearch.value  = ''
  const res = await axios.get(`/admin/rbac/roles/${role.id}`)
  rolePerms.value   = res.data.permissions.map(p => p.id)
  showPermModal.value = true
}

function togglePerm(id) {
  const idx = rolePerms.value.indexOf(id)
  idx === -1 ? rolePerms.value.push(id) : rolePerms.value.splice(idx, 1)
}

function toggleModule(group) {
  const ids   = group.permissions.map(p => p.id)
  const allOn = ids.every(id => rolePerms.value.includes(id))
  if (allOn) {
    rolePerms.value = rolePerms.value.filter(id => !ids.includes(id))
  } else {
    ids.forEach(id => { if (!rolePerms.value.includes(id)) rolePerms.value.push(id) })
  }
}

function moduleChecked(group)       { return group.permissions.every(p => rolePerms.value.includes(p.id)) }
function moduleIndeterminate(group) {
  const cnt = group.permissions.filter(p => rolePerms.value.includes(p.id)).length
  return cnt > 0 && cnt < group.permissions.length
}

function selectAllPerms() {
  if (rolePerms.value.length === totalPerms.value) {
    rolePerms.value = []
  } else {
    rolePerms.value = allPerms.value.flatMap(g => g.permissions.map(p => p.id))
  }
}

async function savePermissions() {
  syncingPerms.value = true
  try {
    await axios.put(`/admin/rbac/roles/${editingRole.value.id}/permissions`, {
      permission_ids: rolePerms.value,
    })
    const idx = roles.value.findIndex(r => r.id === editingRole.value.id)
    if (idx !== -1) roles.value[idx].permissions_count = rolePerms.value.length
    showPermModal.value = false
    Swal.fire({ icon: 'success', title: 'Permissions saved', timer: 1500, showConfirmButton: false })
  } catch (e) {
    Swal.fire('Error', 'Could not save permissions.', 'error')
  } finally {
    syncingPerms.value = false
  }
}
</script>
