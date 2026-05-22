<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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
  const result = await Swal.fire({
    title: `Delete "${perm.name}"?`,
    text: `This will detach it from all ${perm.roles_count} role(s).`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete',
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return
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

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
      <div>
        <h1 class="text-xl font-semibold text-slate-800">Permissions</h1>
        <p class="text-sm text-slate-500 mt-0.5">
          {{ totalPerms }} permission(s) across {{ groups.length }} module(s).
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="showGuide = !showGuide"
          class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <InformationCircleIcon class="w-4 h-4" /> Naming Guide
        </button>
        <button @click="openCreate"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <PlusIcon class="w-4 h-4" /> New Permission
        </button>
      </div>
    </div>

    <!-- Naming convention guide -->
    <div v-if="showGuide" class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-5 text-sm">
      <div class="flex items-start gap-3">
        <KeyIcon class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
        <div class="space-y-3 flex-1">
          <p class="font-semibold text-amber-800">Permission Naming Convention</p>
          <p class="text-amber-700">
            Permissions follow the pattern <code class="bg-amber-100 px-1 rounded font-mono text-xs">module.action</code>
            or <code class="bg-amber-100 px-1 rounded font-mono text-xs">module.submodule.action</code>.
            Always use <strong>lowercase letters, dots, and hyphens only</strong>.
          </p>

          <div class="grid sm:grid-cols-2 gap-3">
            <div class="bg-white rounded-lg border border-amber-200 p-3">
              <p class="text-xs font-semibold text-amber-700 mb-2">Common Actions</p>
              <ul class="space-y-1 text-xs text-amber-700">
                <li><code class="font-mono bg-amber-50 px-1 rounded">.view</code> — read-only access</li>
                <li><code class="font-mono bg-amber-50 px-1 rounded">.manage</code> — create / edit / delete</li>
                <li><code class="font-mono bg-amber-50 px-1 rounded">.approve</code> — workflow approval</li>
                <li><code class="font-mono bg-amber-50 px-1 rounded">.monitor</code> — oversight / reports</li>
                <li><code class="font-mono bg-amber-50 px-1 rounded">.export</code> — export data</li>
              </ul>
            </div>
            <div class="bg-white rounded-lg border border-amber-200 p-3">
              <p class="text-xs font-semibold text-amber-700 mb-2">Examples</p>
              <ul class="space-y-1 text-xs font-mono text-amber-700">
                <li>hr.dtr.view</li>
                <li>hr.dtr.manage</li>
                <li>ipcr.view</li>
                <li>ipcr.approve</li>
                <li>leave.manage</li>
                <li>users.view</li>
              </ul>
            </div>
          </div>

          <p class="text-xs text-amber-600">
            <strong>Adding a new module:</strong> Just create a permission with a new module name (e.g. <code class="font-mono bg-amber-100 px-1 rounded">payroll.view</code>) and set the <strong>Module</strong> field to <code class="font-mono bg-amber-100 px-1 rounded">payroll</code>.
            It will automatically appear as a new group. Then protect the route with <code class="font-mono bg-amber-100 px-1 rounded">middleware('permission:payroll.view')</code> in <code class="font-mono bg-amber-100 px-1 rounded">routes/web.php</code>.
          </p>
        </div>
      </div>
    </div>

    <!-- Search bar -->
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-3 mb-4 flex items-center gap-2">
      <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 shrink-0" />
      <input v-model="search" type="text" placeholder="Search by name or description…"
        class="flex-1 border-none outline-none text-sm text-slate-800 placeholder-slate-400 bg-transparent" />
      <span v-if="search" class="text-xs text-slate-400">{{ filtered.reduce((s,g) => s + g.permissions.length, 0) }} result(s)</span>
    </div>

    <!-- Grouped tables -->
    <div v-if="loading" class="py-16 text-center text-slate-400 text-sm">Loading…</div>
    <div v-else class="space-y-4">
      <div v-for="group in filtered" :key="group.module" class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <!-- Module header -->
        <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <KeyIcon class="w-4 h-4 text-indigo-400" />
            <h2 class="text-xs font-semibold uppercase tracking-wider text-indigo-600">{{ group.module }}</h2>
          </div>
          <span class="text-[11px] text-slate-400">{{ group.permissions.length }} permission(s)</span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Key</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Description</th>
                <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Roles</th>
                <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="perm in group.permissions" :key="perm.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3">
                  <code class="font-mono text-indigo-700 text-xs bg-indigo-50 px-2 py-0.5 rounded">{{ perm.name }}</code>
                </td>
                <td class="px-4 py-3 text-slate-500 text-sm">{{ perm.description ?? '—' }}</td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">{{ perm.roles_count }}</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <button @click="openEdit(perm)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button @click="deletePerm(perm)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors" title="Delete">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <p v-if="filtered.length === 0" class="py-16 text-center text-slate-400 text-sm">No permissions found.</p>
    </div>

    <!-- Create / Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">{{ modalMode === 'create' ? 'New Permission' : 'Edit Permission' }}</h2>
          <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="showModal = false"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
        </div>
        <div class="px-6 py-5">
          <form @submit.prevent="submit" class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">
                Permission Key
                <span class="text-slate-400 font-normal ml-1">(lowercase · dots · hyphens)</span>
              </label>
              <input v-model="form.name" type="text" required maxlength="100"
                pattern="[a-z0-9._\-]+"
                placeholder="e.g. hr.dtr.view or payroll.manage"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 font-mono placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              <p class="text-[11px] text-slate-400 mt-1">This key is used in routes: <code class="font-mono">middleware('permission:{{ form.name || "…" }}')</code></p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Module <span class="text-slate-400 font-normal">(groups this permission)</span></label>
              <input v-model="form.module" list="module-list" type="text" required maxlength="50"
                placeholder="e.g. hr · ipcr · payroll"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              <datalist id="module-list">
                <option v-for="m in moduleList" :key="m" :value="m" />
              </datalist>
              <p class="text-[11px] text-slate-400 mt-1">Enter a new module name to create a new group.</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Description <span class="text-slate-400 font-normal">(shown to admins)</span></label>
              <input v-model="form.description" type="text" maxlength="255"
                placeholder="e.g. View HR Daily Time Records"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="showModal = false"
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

  </AdminLayout>
</template>
