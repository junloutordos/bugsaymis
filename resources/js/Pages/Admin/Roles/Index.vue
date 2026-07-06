<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppCard from '@/Components/AppCard.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppInput from '@/Components/AppInput.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
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

<template>
  <Head title="Roles & Permissions" />
  <AdminLayout title="Roles & Permissions">

    <!-- Sub-navigation -->
    <div class="flex gap-1 mb-6 bg-white border border-slate-200 rounded-xl p-1 w-fit shadow-sm">
      <Link href="/admin/roles" class="px-4 py-1.5 rounded-lg text-sm font-medium bg-indigo-600 text-white shadow-sm">Roles</Link>
      <Link href="/admin/permissions" class="px-4 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">Permissions</Link>
      <Link href="/admin/assign-roles" class="px-4 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">Assign to Users</Link>
    </div>

    <AppPageHeader title="Roles" subtitle="Define roles and assign permissions to each role.">
      <template #actions>
        <AppButton @click="openCreate">
          <PlusIcon class="w-4 h-4" /> New Role
        </AppButton>
      </template>
    </AppPageHeader>

    <!-- Loading -->
    <div v-if="loading" class="py-20 text-center text-slate-400 text-sm">Loading…</div>

    <template v-else>
      <!-- Search -->
      <AppFilterBar :result-label="`${filtered.length} role(s)`">
        <div class="relative flex-1 min-w-[220px]">
          <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
          <input v-model="search" type="text" placeholder="Search roles…"
            class="w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        </div>
      </AppFilterBar>

      <!-- Roles grid -->
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <AppCard v-for="role in filtered" :key="role.id" :padded="false"
          class="flex flex-col group hover:border-indigo-300 transition-colors">
          <template #header>
            <div class="flex items-start justify-between gap-2 w-full">
              <div>
                <div class="flex items-center gap-2">
                  <ShieldCheckIcon class="w-4 h-4 text-indigo-500 shrink-0" />
                  <span class="font-semibold text-slate-800">{{ role.name }}</span>
                  <AppBadge v-if="role.name === 'Administrator'" color="amber">Super</AppBadge>
                </div>
                <p class="text-xs text-slate-400 mt-0.5 ml-6">{{ role.description || 'No description' }}</p>
              </div>
              <!-- Actions -->
              <div class="flex items-center gap-1 shrink-0">
                <AppIconButton label="Edit role" @click="openEdit(role)"><PencilSquareIcon class="w-4 h-4" /></AppIconButton>
                <AppIconButton label="Delete role" variant="danger" :disabled="role.name === 'Administrator'" @click="deleteRole(role)">
                  <TrashIcon class="w-4 h-4" />
                </AppIconButton>
              </div>
            </div>
          </template>

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
        </AppCard>

        <!-- Empty -->
        <EmptyState v-if="filtered.length === 0" class="col-span-full" title="No roles found" />
      </div>
    </template>

    <!-- ── Role create/edit modal ────────────────────────────────────────── -->
    <AppModal :show="showRoleModal" :title="modalMode === 'create' ? 'New Role' : 'Edit Role'" size="md" @close="showRoleModal = false">
      <form @submit.prevent="submitRole" class="space-y-4">
        <AppInput v-model="form.name" label="Role Name" required maxlength="100" placeholder="e.g. Librarian" />
        <AppInput v-model="form.description" label="Description" maxlength="255" placeholder="Brief description of this role" />
        <div class="flex justify-end gap-2 pt-1 border-t border-slate-100">
          <AppButton type="button" variant="secondary" @click="showRoleModal = false">Cancel</AppButton>
          <AppButton type="submit" :loading="saving" :disabled="saving">
            {{ saving ? 'Saving…' : (modalMode === 'create' ? 'Create Role' : 'Save Changes') }}
          </AppButton>
        </div>
      </form>
    </AppModal>

    <!-- ── Permission assignment modal ──────────────────────────────────── -->
    <AppModal :show="showPermModal" size="3xl" :show-close-button="false" body-class="px-6 py-5 space-y-5" @close="showPermModal = false">
      <template #header>
        <div class="min-w-0 flex-1">
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
          <AppIconButton label="Close" @click="showPermModal = false"><XMarkIcon class="w-4 h-4" /></AppIconButton>
        </div>
      </template>

      <!-- Permission groups -->
      <div v-if="editingRole?.name === 'Administrator'"
        class="flex items-center gap-2 bg-warning-50 border border-warning-100 text-warning-700 rounded-lg px-4 py-3 text-sm">
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

      <template #footer>
        <div class="w-full flex items-center justify-between">
          <button @click="selectAllPerms" class="text-xs text-slate-500 hover:text-indigo-600 transition-colors">
            {{ rolePerms.length === totalPerms ? 'Deselect all' : 'Select all' }}
          </button>
          <div class="flex gap-2">
            <AppButton variant="secondary" @click="showPermModal = false">Cancel</AppButton>
            <AppButton :loading="syncingPerms" :disabled="syncingPerms" @click="savePermissions">
              {{ syncingPerms ? 'Saving…' : 'Save Permissions' }}
            </AppButton>
          </div>
        </div>
      </template>
    </AppModal>

  </AdminLayout>
</template>
