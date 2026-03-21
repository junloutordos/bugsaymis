<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import {
  PlusIcon, PencilSquareIcon, TrashIcon,
  ShieldCheckIcon, MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

// ─── State ───────────────────────────────────────────────────────────────────
const roles       = ref([])
const allPerms    = ref([])   // [{module, permissions:[]}]
const loading     = ref(true)

// Role modal
const showRoleModal = ref(false)
const modalMode     = ref('create')  // 'create' | 'edit'
const form          = ref({ id: null, name: '', description: '' })
const saving        = ref(false)

// Permission modal
const showPermModal = ref(false)
const editingRole   = ref(null)
const rolePerms     = ref([])   // ids of currently assigned permissions
const syncingPerms  = ref(false)

const search = ref('')

// ─── Computed ────────────────────────────────────────────────────────────────
const filtered = computed(() =>
  roles.value.filter(r =>
    r.name.toLowerCase().includes(search.value.toLowerCase())
  )
)

// ─── Data loading ─────────────────────────────────────────────────────────────
async function loadRoles() {
  loading.value = true
  const [rolesRes, permsRes] = await Promise.all([
    axios.get(route('admin.rbac.roles.index')),
    axios.get(route('admin.rbac.permissions.all')),
  ])
  roles.value    = rolesRes.data
  allPerms.value = permsRes.data
  loading.value  = false
}

onMounted(loadRoles)

// ─── Role CRUD ───────────────────────────────────────────────────────────────
function openCreate() {
  form.value    = { id: null, name: '', description: '' }
  modalMode.value  = 'create'
  showRoleModal.value = true
}

function openEdit(role) {
  form.value    = { id: role.id, name: role.name, description: role.description ?? '' }
  modalMode.value  = 'edit'
  showRoleModal.value = true
}

async function submitRole() {
  saving.value = true
  try {
    if (modalMode.value === 'create') {
      const res = await axios.post(route('admin.rbac.roles.store'), form.value)
      roles.value.push({ ...res.data, permissions_count: 0, users_count: 0 })
    } else {
      await axios.put(route('admin.rbac.roles.update', form.value.id), form.value)
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
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete',
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return

  try {
    await axios.delete(route('admin.rbac.roles.destroy', role.id))
    roles.value = roles.value.filter(r => r.id !== role.id)
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message ?? 'Cannot delete this role.', 'error')
  }
}

// ─── Permission assignment ────────────────────────────────────────────────────
async function openPermissions(role) {
  editingRole.value = role
  const res = await axios.get(route('admin.rbac.roles.show', role.id))
  rolePerms.value = res.data.permissions.map(p => p.id)
  showPermModal.value = true
}

function togglePerm(id) {
  const idx = rolePerms.value.indexOf(id)
  if (idx === -1) rolePerms.value.push(id)
  else rolePerms.value.splice(idx, 1)
}

function toggleModule(module) {
  const ids = module.permissions.map(p => p.id)
  const allOn = ids.every(id => rolePerms.value.includes(id))
  if (allOn) {
    rolePerms.value = rolePerms.value.filter(id => !ids.includes(id))
  } else {
    ids.forEach(id => { if (!rolePerms.value.includes(id)) rolePerms.value.push(id) })
  }
}

function moduleChecked(module) {
  return module.permissions.every(p => rolePerms.value.includes(p.id))
}
function moduleIndeterminate(module) {
  const ids = module.permissions.map(p => p.id)
  const count = ids.filter(id => rolePerms.value.includes(id)).length
  return count > 0 && count < ids.length
}

async function savePermissions() {
  syncingPerms.value = true
  try {
    await axios.put(route('admin.rbac.roles.permissions.sync', editingRole.value.id), {
      permission_ids: rolePerms.value,
    })
    // Update count in list
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

<template>
  <Head title="Roles & Permissions" />
  <AdminLayout title="Roles & Permissions">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
      <h1 class="text-xl font-semibold text-slate-800">Roles</h1>
      <button @click="openCreate"
        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
        <PlusIcon class="w-4 h-4" /> New Role
      </button>
    </div>

    <!-- Search + Table -->
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
      <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
        <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 shrink-0" />
        <input v-model="search" type="text" placeholder="Search roles…"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64" />
      </div>

      <div v-if="loading" class="py-16 text-center text-slate-400 text-sm">Loading…</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Role</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Description</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap text-center">Permissions</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap text-center">Users</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="role in filtered" :key="role.id" class="hover:bg-slate-50/60">
              <td class="px-4 py-3 font-medium text-slate-800">{{ role.name }}</td>
              <td class="px-4 py-3 text-slate-500">{{ role.description ?? '—' }}</td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-50 text-blue-700">
                  {{ role.permissions_count }}
                </span>
              </td>
              <td class="px-4 py-3 text-center text-slate-600">{{ role.users_count }}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                  <button @click="openPermissions(role)"
                    class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-emerald-600 transition-colors" title="Edit permissions">
                    <ShieldCheckIcon class="w-4 h-4" />
                  </button>
                  <button @click="openEdit(role)"
                    class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit role">
                    <PencilSquareIcon class="w-4 h-4" />
                  </button>
                  <button @click="deleteRole(role)"
                    class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors" title="Delete role"
                    :disabled="role.name === 'Administrator'">
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="filtered.length === 0">
              <td colspan="5" class="py-16 text-center text-slate-400 text-sm">No roles found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Role create/edit modal -->
    <div v-if="showRoleModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">{{ modalMode === 'create' ? 'New Role' : 'Edit Role' }}</h2>
          <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="showRoleModal = false">✕</button>
        </div>
        <div class="px-6 py-5">
          <form @submit.prevent="submitRole" class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
              <input v-model="form.name" type="text" required maxlength="100"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
              <input v-model="form.description" type="text" maxlength="255"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="showRoleModal = false"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
              <button type="submit" :disabled="saving"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
                {{ saving ? 'Saving…' : 'Save' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Permission assignment modal -->
    <div v-if="showPermModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">
            Permissions — <span class="text-indigo-600">{{ editingRole?.name }}</span>
          </h2>
          <span class="text-sm text-slate-500">{{ rolePerms.length }} selected</span>
        </div>

        <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">
          <div v-for="group in allPerms" :key="group.module">
            <label class="flex items-center gap-2 mb-2 cursor-pointer">
              <input type="checkbox"
                :checked="moduleChecked(group)"
                :indeterminate="moduleIndeterminate(group)"
                @change="toggleModule(group)"
                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
              <span class="font-semibold text-slate-700 uppercase text-xs tracking-wider">{{ group.module }}</span>
            </label>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 pl-5">
              <label v-for="perm in group.permissions" :key="perm.id"
                class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox"
                  :checked="rolePerms.includes(perm.id)"
                  @change="togglePerm(perm.id)"
                  class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                <span :title="perm.description" class="truncate text-slate-700">{{ perm.name }}</span>
              </label>
            </div>
          </div>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
          <button @click="showPermModal = false"
            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
          <button @click="savePermissions" :disabled="syncingPerms"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
            {{ syncingPerms ? 'Saving…' : 'Save Permissions' }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
