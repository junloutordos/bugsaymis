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
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import axios from 'axios'
import Swal from 'sweetalert2'
import { PlusIcon, PencilSquareIcon, TrashIcon, MagnifyingGlassIcon, InformationCircleIcon, KeyIcon } from '@heroicons/vue/24/outline'

// ─── State ───────────────────────────────────────────────────────────────────
const groups  = ref([])   // [{module, permissions:[{id,name,module,description,roles_count}]}]
const loading = ref(true)
const search  = ref('')
const showGuide = ref(false)

const showModal = ref(false)
const modalMode = ref('create')
const form      = ref({ id: null, name: '', module: '', description: '' })
const saving    = ref(false)

// ─── Computed ────────────────────────────────────────────────────────────────
const filtered = computed(() => {
  if (!search.value) return groups.value
  const q = search.value.toLowerCase()
  return groups.value
    .map(g => ({
      ...g,
      permissions: g.permissions.filter(p =>
        p.name.toLowerCase().includes(q) || p.description?.toLowerCase().includes(q)
      ),
    }))
    .filter(g => g.permissions.length > 0)
})

const moduleList = computed(() => [...new Set(groups.value.map(g => g.module))].sort())

const totalPerms = computed(() => groups.value.reduce((s, g) => s + g.permissions.length, 0))

// ─── Load ─────────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  const res = await axios.get('/admin/rbac/permissions')
  groups.value  = res.data
  loading.value = false
}
onMounted(load)

// ─── CRUD ─────────────────────────────────────────────────────────────────────
function openCreate() {
  form.value    = { id: null, name: '', module: '', description: '' }
  modalMode.value = 'create'
  showModal.value = true
}

function openEdit(perm) {
  form.value    = { id: perm.id, name: perm.name, module: perm.module, description: perm.description ?? '' }
  modalMode.value = 'edit'
  showModal.value = true
}

async function submit() {
  saving.value = true
  try {
    if (modalMode.value === 'create') {
      await axios.post('/admin/rbac/permissions', form.value)
    } else {
      await axios.put(`/admin/rbac/permissions/${form.value.id}`, form.value)
    }
    showModal.value = false
    await load()
  } catch (e) {
    const errors = e.response?.data?.errors
    const msg    = errors
      ? Object.values(errors).flat().join('\n')
      : e.response?.data?.message ?? 'Something went wrong.'
    Swal.fire('Error', msg, 'error')
  } finally {
    saving.value = false
  }
}

async function deletePerm(perm) {
  const confirmed = await confirmAction({
    title: `Delete "${perm.name}"?`,
    text: `This will detach it from all ${perm.roles_count} role(s).`,
    confirmText: 'Delete',
    icon: 'warning',
  })
  if (!confirmed) return
  try {
    await axios.delete(`/admin/rbac/permissions/${perm.id}`)
    await load()
  } catch (e) {
    Swal.fire('Error', 'Could not delete permission.', 'error')
  }
}
</script>

<template>
  <Head title="Permissions" />
  <AdminLayout title="Roles & Permissions">

    <!-- Sub-navigation -->
    <div class="flex gap-1 mb-6 bg-white border border-slate-200 rounded-xl p-1 w-fit shadow-sm">
      <Link href="/admin/roles" class="px-4 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">Roles</Link>
      <Link href="/admin/permissions" class="px-4 py-1.5 rounded-lg text-sm font-medium bg-indigo-600 text-white shadow-sm">Permissions</Link>
      <Link href="/admin/assign-roles" class="px-4 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">Assign to Users</Link>
    </div>

    <AppPageHeader title="Permissions" :subtitle="`${totalPerms} permission(s) across ${groups.length} module(s).`">
      <template #actions>
        <AppButton variant="secondary" @click="showGuide = !showGuide">
          <InformationCircleIcon class="h-4 w-4" /> Naming Guide
        </AppButton>
        <AppButton @click="openCreate">
          <PlusIcon class="h-4 w-4" /> New Permission
        </AppButton>
      </template>
    </AppPageHeader>

    <!-- Naming convention guide -->
    <div v-if="showGuide" class="bg-warning-50 border border-warning-100 rounded-xl p-5 mb-5 text-sm">
      <div class="flex items-start gap-3">
        <KeyIcon class="w-5 h-5 text-warning-600 shrink-0 mt-0.5" />
        <div class="space-y-3 flex-1">
          <p class="font-semibold text-warning-700">Permission Naming Convention</p>
          <p class="text-warning-700">
            Permissions follow the pattern <code class="bg-warning-100 px-1 rounded font-mono text-xs">module.action</code>
            or <code class="bg-warning-100 px-1 rounded font-mono text-xs">module.submodule.action</code>.
            Always use <strong>lowercase letters, dots, and hyphens only</strong>.
          </p>

          <div class="grid sm:grid-cols-2 gap-3">
            <div class="bg-white rounded-lg border border-warning-100 p-3">
              <p class="text-xs font-semibold text-warning-700 mb-2">Common Actions</p>
              <ul class="space-y-1 text-xs text-warning-700">
                <li><code class="font-mono bg-warning-50 px-1 rounded">.view</code> — read-only access</li>
                <li><code class="font-mono bg-warning-50 px-1 rounded">.manage</code> — create / edit / delete</li>
                <li><code class="font-mono bg-warning-50 px-1 rounded">.approve</code> — workflow approval</li>
                <li><code class="font-mono bg-warning-50 px-1 rounded">.monitor</code> — oversight / reports</li>
                <li><code class="font-mono bg-warning-50 px-1 rounded">.export</code> — export data</li>
              </ul>
            </div>
            <div class="bg-white rounded-lg border border-warning-100 p-3">
              <p class="text-xs font-semibold text-warning-700 mb-2">Examples</p>
              <ul class="space-y-1 text-xs font-mono text-warning-700">
                <li>hr.dtr.view</li>
                <li>hr.dtr.manage</li>
                <li>ipcr.view</li>
                <li>ipcr.approve</li>
                <li>leave.manage</li>
                <li>users.view</li>
              </ul>
            </div>
          </div>

          <p class="text-xs text-warning-600">
            <strong>Adding a new module:</strong> Just create a permission with a new module name (e.g. <code class="font-mono bg-warning-100 px-1 rounded">payroll.view</code>) and set the <strong>Module</strong> field to <code class="font-mono bg-warning-100 px-1 rounded">payroll</code>.
            It will automatically appear as a new group. Then protect the route with <code class="font-mono bg-warning-100 px-1 rounded">middleware('permission:payroll.view')</code> in <code class="font-mono bg-warning-100 px-1 rounded">routes/web.php</code>.
          </p>
        </div>
      </div>
    </div>

    <!-- Search bar -->
    <AppFilterBar :result-label="search ? `${filtered.reduce((s,g) => s + g.permissions.length, 0)} result(s)` : null">
      <div class="relative flex-1 min-w-[220px]">
        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
        <input v-model="search" type="text" placeholder="Search by name or description…"
          class="w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
      </div>
    </AppFilterBar>

    <!-- Grouped tables -->
    <div v-if="loading" class="py-16 text-center text-slate-400 text-sm">Loading…</div>
    <div v-else class="space-y-4">
      <AppCard v-for="group in filtered" :key="group.module" :padded="false"
        :title="group.module" :subtitle="`${group.permissions.length} permission(s)`">
        <AppTable :card="false">
          <template #head>
            <tr>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Key</th>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Description</th>
              <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Roles</th>
              <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
            </tr>
          </template>

          <tr v-for="perm in group.permissions" :key="perm.id" class="hover:bg-indigo-50/40">
            <td class="px-4 py-3">
              <code class="font-mono text-indigo-700 text-xs bg-indigo-50 px-2 py-0.5 rounded">{{ perm.name }}</code>
            </td>
            <td class="px-4 py-3 text-slate-500 text-sm">{{ perm.description ?? '—' }}</td>
            <td class="px-4 py-3 text-center">
              <AppBadge color="slate">{{ perm.roles_count }}</AppBadge>
            </td>
            <td class="px-4 py-3 text-center">
              <div class="flex items-center justify-center gap-1">
                <AppIconButton label="Edit" @click="openEdit(perm)"><PencilSquareIcon class="w-4 h-4" /></AppIconButton>
                <AppIconButton label="Delete" variant="danger" @click="deletePerm(perm)"><TrashIcon class="w-4 h-4" /></AppIconButton>
              </div>
            </td>
          </tr>

          <template #mobileCard>
            <div v-for="perm in group.permissions" :key="perm.id" class="p-4 space-y-1">
              <div class="flex items-start justify-between gap-2">
                <code class="font-mono text-indigo-700 text-xs bg-indigo-50 px-2 py-0.5 rounded">{{ perm.name }}</code>
                <AppBadge color="slate">{{ perm.roles_count }}</AppBadge>
              </div>
              <p class="text-xs text-slate-500">{{ perm.description ?? '—' }}</p>
              <div class="flex items-center gap-1 pt-1">
                <AppIconButton label="Edit" @click="openEdit(perm)"><PencilSquareIcon class="w-4 h-4" /></AppIconButton>
                <AppIconButton label="Delete" variant="danger" @click="deletePerm(perm)"><TrashIcon class="w-4 h-4" /></AppIconButton>
              </div>
            </div>
          </template>
        </AppTable>
      </AppCard>
      <EmptyState v-if="filtered.length === 0" title="No permissions found" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :show="showModal" :title="modalMode === 'create' ? 'New Permission' : 'Edit Permission'" size="md" @close="showModal = false">
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <AppInput v-model="form.name" label="Permission Key" required maxlength="100"
            pattern="[a-z0-9._\-]+" placeholder="e.g. hr.dtr.view or payroll.manage" class="font-mono" />
          <p class="text-[11px] text-slate-400 mt-1">This key is used in routes: <code class="font-mono">middleware('permission:{{ form.name || "…" }}')</code></p>
        </div>
        <div>
          <AppInput v-model="form.module" label="Module" required maxlength="50" list="module-list"
            placeholder="e.g. hr · ipcr · payroll" />
          <datalist id="module-list">
            <option v-for="m in moduleList" :key="m" :value="m" />
          </datalist>
          <p class="text-[11px] text-slate-400 mt-1">Enter a new module name to create a new group.</p>
        </div>
        <AppInput v-model="form.description" label="Description" maxlength="255" placeholder="e.g. View HR Daily Time Records" />
        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
          <AppButton type="button" variant="secondary" @click="showModal = false">Cancel</AppButton>
          <AppButton type="submit" :loading="saving" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</AppButton>
        </div>
      </form>
    </AppModal>

  </AdminLayout>
</template>
