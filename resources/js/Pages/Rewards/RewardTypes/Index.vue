<template>
  <AdminLayout title="Reward Types">
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Reward Types</h1>
        <button @click="openCreate"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          + New Type
        </button>
      </div>

      <div class="overflow-x-auto rounded-xl border border-slate-100">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Category</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Type</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Frequency</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-for="t in types" :key="t.id" class="hover:bg-slate-50/60">
              <td class="px-4 py-3 text-sm text-slate-700">
                <p class="font-medium text-slate-800">{{ t.name }}</p>
                <p v-if="t.description" class="text-xs text-slate-500">{{ t.description }}</p>
              </td>
              <td class="px-4 py-3 text-sm text-slate-700 capitalize">{{ t.category }}</td>
              <td class="px-4 py-3 text-sm text-slate-700 capitalize">{{ t.type.replace('_', ' ') }}</td>
              <td class="px-4 py-3 text-sm text-slate-700 capitalize">{{ t.frequency.replace('_', ' ') }}</td>
              <td class="px-4 py-3 text-sm text-slate-700">
                <span :class="t.is_active
                  ? 'bg-emerald-50 text-emerald-700'
                  : 'bg-slate-100 text-slate-600'"
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium">
                  {{ t.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-slate-700">
                <div class="flex gap-2">
                  <button @click="openEdit(t)"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shadow-sm">
                    Edit
                  </button>
                  <button @click="confirmDelete(t)"
                    class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors shadow-sm">
                    Delete
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!types.length">
              <td colspan="6" class="py-16 text-center text-slate-400 text-sm">No reward types yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-xl">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ editing ? 'Edit' : 'New' }} Reward Type</h2>
            <button type="button" @click="showModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <form @submit.prevent="submit">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
                <input v-model="form.name" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" required />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <textarea v-model="form.description" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="2" />
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Category</label>
                  <select v-model="form.category" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                    <option value="individual">Individual</option>
                    <option value="team">Team</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
                  <select v-model="form.type" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                    <option value="monetary">Monetary</option>
                    <option value="non_monetary">Non-Monetary</option>
                    <option value="mixed">Mixed</option>
                  </select>
                </div>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Frequency</label>
                <select v-model="form.frequency" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                  <option value="monthly">Monthly</option>
                  <option value="quarterly">Quarterly</option>
                  <option value="annual">Annual</option>
                  <option value="ad_hoc">Ad Hoc</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Criteria</label>
                <textarea v-model="form.criteria" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="3" />
              </div>
              <div class="flex items-center gap-2">
                <input type="checkbox" v-model="form.is_active" id="is_active" class="h-4 w-4" />
                <label for="is_active" class="text-sm text-slate-700">Active</label>
              </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <button type="button" @click="showModal = false"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
              <button type="submit" :disabled="processing"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
                {{ editing ? 'Update' : 'Create' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ types: Array })

const showModal = ref(false)
const editing = ref(null)
const processing = ref(false)

const form = useForm({
  name: '',
  description: '',
  category: 'individual',
  type: 'non_monetary',
  frequency: 'annual',
  criteria: '',
  is_active: true,
})

function openCreate() {
  editing.value = null
  form.reset()
  form.is_active = true
  form.category = 'individual'
  form.type = 'non_monetary'
  form.frequency = 'annual'
  showModal.value = true
}

function openEdit(t) {
  editing.value = t
  form.name = t.name
  form.description = t.description ?? ''
  form.category = t.category
  form.type = t.type
  form.frequency = t.frequency
  form.criteria = t.criteria ?? ''
  form.is_active = t.is_active
  showModal.value = true
}

function submit() {
  if (editing.value) {
    form.patch(route('rewards.types.update', editing.value.id), {
      onSuccess: () => { showModal.value = false },
    })
  } else {
    form.post(route('rewards.types.store'), {
      onSuccess: () => { showModal.value = false },
    })
  }
}

function confirmDelete(t) {
  if (confirm(`Delete "${t.name}"?`)) {
    useForm({}).delete(route('rewards.types.destroy', t.id))
  }
}
</script>
