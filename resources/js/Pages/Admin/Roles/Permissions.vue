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
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-bold text-gray-800">Permissions</h1>
      <button @click="openCreate"
        class="flex items-center gap-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow text-sm">
        <PlusIcon class="w-4 h-4" /> New Permission
      </button>
    </div>

    <!-- Search -->
    <div class="bg-white rounded-xl shadow p-4 mb-4">
      <div class="flex items-center gap-2">
        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 shrink-0" />
        <input v-model="search" type="text" placeholder="Search permissions…"
          class="w-full sm:w-72 rounded-lg border-gray-300 text-sm shadow-sm focus:ring-blue-500 focus:border-blue-500" />
      </div>
    </div>

    <!-- Grouped tables -->
    <div v-if="loading" class="text-center py-10 text-gray-400">Loading…</div>
    <div v-else class="space-y-6">
      <div v-for="group in filtered" :key="group.module" class="bg-white rounded-xl shadow p-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-blue-700 mb-3">{{ group.module }}</h2>
        <table class="min-w-full text-sm border border-gray-200">
          <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
            <tr>
              <th class="px-4 py-2 text-left">Permission</th>
              <th class="px-4 py-2 text-left">Description</th>
              <th class="px-4 py-2 text-center">Roles</th>
              <th class="px-4 py-2 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="perm in group.permissions" :key="perm.id" class="hover:bg-gray-50">
              <td class="px-4 py-2 font-mono text-blue-800">{{ perm.name }}</td>
              <td class="px-4 py-2 text-gray-500">{{ perm.description ?? '—' }}</td>
              <td class="px-4 py-2 text-center">
                <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs">{{ perm.roles_count }}</span>
              </td>
              <td class="px-4 py-2 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button @click="openEdit(perm)"
                    class="p-1.5 rounded hover:bg-yellow-100 text-yellow-600" title="Edit">
                    <PencilSquareIcon class="w-4 h-4" />
                  </button>
                  <button @click="deletePerm(perm)"
                    class="p-1.5 rounded hover:bg-red-100 text-red-600" title="Delete">
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="filtered.length === 0" class="text-center py-8 text-gray-400">No permissions found.</p>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold mb-4">{{ modalMode === 'create' ? 'New Permission' : 'Edit Permission' }}</h2>
        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name
              <span class="text-gray-400 text-xs font-normal ml-1">(lowercase, dots/dashes only)</span>
            </label>
            <input v-model="form.name" type="text" required maxlength="100"
              pattern="[a-z0-9._\-]+"
              placeholder="e.g. wfh.view"
              class="w-full font-mono rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Module</label>
            <input v-model="form.module" list="module-list" type="text" required maxlength="50"
              class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
            <datalist id="module-list">
              <option v-for="m in moduleList" :key="m" :value="m" />
            </datalist>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <input v-model="form.description" type="text" maxlength="255"
              class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showModal = false"
              class="px-4 py-2 rounded-lg border text-gray-700 hover:bg-gray-50 text-sm">Cancel</button>
            <button type="submit" :disabled="saving"
              class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm disabled:opacity-60">
              {{ saving ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
