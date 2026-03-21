<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { PlusIcon, PencilSquareIcon, TrashIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

// ─── State ───────────────────────────────────────────────────────────────────
const groups  = ref([])   // [{module, permissions:[{id,name,module,description,roles_count}]}]
const loading = ref(true)
const search  = ref('')

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

// ─── Load ─────────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  const res = await axios.get(route('admin.rbac.permissions.index'))
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
      await axios.post(route('admin.rbac.permissions.store'), form.value)
    } else {
      await axios.put(route('admin.rbac.permissions.update', form.value.id), form.value)
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
    await axios.delete(route('admin.rbac.permissions.destroy', perm.id))
    await load()
  } catch (e) {
    Swal.fire('Error', 'Could not delete permission.', 'error')
  }
}
</script>

<template>
  <Head title="Permissions" />
  <AdminLayout title="Permissions">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
      <h1 class="text-xl font-semibold text-slate-800">Permissions</h1>
      <button @click="openCreate"
        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
        <PlusIcon class="w-4 h-4" /> New Permission
      </button>
    </div>

    <!-- Search -->
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
      <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 shrink-0" />
      <input v-model="search" type="text" placeholder="Search permissions…"
        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64" />
    </div>

    <!-- Grouped tables -->
    <div v-if="loading" class="py-16 text-center text-slate-400 text-sm">Loading…</div>
    <div v-else class="space-y-4">
      <div v-for="group in filtered" :key="group.module" class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-xs font-semibold uppercase tracking-wider text-indigo-600">{{ group.module }}</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Permission</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Description</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap text-center">Roles</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="perm in group.permissions" :key="perm.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 font-mono text-indigo-800 text-sm">{{ perm.name }}</td>
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

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">{{ modalMode === 'create' ? 'New Permission' : 'Edit Permission' }}</h2>
          <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="showModal = false">✕</button>
        </div>
        <div class="px-6 py-5">
          <form @submit.prevent="submit" class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Name
                <span class="text-slate-400 font-normal ml-1">(lowercase, dots/dashes only)</span>
              </label>
              <input v-model="form.name" type="text" required maxlength="100"
                pattern="[a-z0-9._\-]+"
                placeholder="e.g. wfh.view"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 font-mono placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Module</label>
              <input v-model="form.module" list="module-list" type="text" required maxlength="50"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              <datalist id="module-list">
                <option v-for="m in moduleList" :key="m" :value="m" />
              </datalist>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
              <input v-model="form.description" type="text" maxlength="255"
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
