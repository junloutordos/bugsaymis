<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ offenses: Array })

const showForm = ref(false)
const editingId = ref(null)

const form = useForm({
  code: '', category: '', level: 1, title: '',
  description: '', default_sanction: '', sort_order: 0, is_active: true,
})

function openCreate() {
  editingId.value = null
  form.reset()
  form.level = 1
  form.is_active = true
  showForm.value = true
}
function openEdit(o) {
  editingId.value = o.id
  form.code = o.code || ''
  form.category = o.category || ''
  form.level = o.level
  form.title = o.title
  form.description = o.description || ''
  form.default_sanction = o.default_sanction || ''
  form.sort_order = o.sort_order || 0
  form.is_active = !!o.is_active
  showForm.value = true
}
function save() {
  const opts = { preserveScroll: true, onSuccess: () => { showForm.value = false } }
  if (editingId.value) form.put(route('discipline.offenses.update', editingId.value), opts)
  else form.post(route('discipline.offenses.store'), opts)
}
function remove(o) {
  if (confirm(`Remove "${o.title}" from the catalog?`)) {
    router.delete(route('discipline.offenses.destroy', o.id), { preserveScroll: true })
  }
}

const levelClass = (l) => ({ 1: 'bg-amber-100 text-amber-700', 2: 'bg-orange-100 text-orange-700', 3: 'bg-rose-100 text-rose-700' }[l] || 'bg-slate-100 text-slate-600')
</script>

<template>
  <Head title="Offense Catalog" />
  <AdminLayout title="Offense Catalog">
    <div class="max-w-4xl mx-auto space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Offense Catalog</h1>
          <p class="text-sm text-slate-500">Code of Conduct offenses (Level 1–3)</p>
        </div>
        <button @click="openCreate" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          <PlusIcon class="w-4 h-4" /> Add Offense
        </button>
      </div>

      <!-- Form -->
      <div v-if="showForm" class="bg-white rounded-xl border border-indigo-100 shadow-sm p-5 space-y-4">
        <h3 class="font-semibold text-slate-700">{{ editingId ? 'Edit' : 'Add' }} Offense</h3>
        <div class="grid sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Code</label>
            <input v-model="form.code" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Level <span class="text-red-500">*</span></label>
            <select v-model="form.level" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
              <option :value="1">Level 1</option><option :value="2">Level 2</option><option :value="3">Level 3</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Category</label>
            <input v-model="form.category" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Title <span class="text-red-500">*</span></label>
          <input v-model="form.title" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"
                 :class="{ 'border-red-400': form.errors.title }" />
          <p v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Default sanction</label>
          <textarea v-model="form.default_sanction" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-y"></textarea>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" v-model="form.is_active" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" /> Active
        </label>
        <div class="flex justify-end gap-3">
          <button @click="showForm = false" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
          <button @click="save" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">Save</button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100">
              <th class="px-4 py-3">Level</th>
              <th class="px-4 py-3">Code</th>
              <th class="px-4 py-3">Title</th>
              <th class="px-4 py-3">Category</th>
              <th class="px-4 py-3">Active</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="o in offenses" :key="o.id" class="border-b border-slate-50 hover:bg-slate-50/60">
              <td class="px-4 py-3"><span class="px-2 py-1 rounded-md text-xs font-medium" :class="levelClass(o.level)">L{{ o.level }}</span></td>
              <td class="px-4 py-3 text-slate-500">{{ o.code || '—' }}</td>
              <td class="px-4 py-3 text-slate-700">{{ o.title }}</td>
              <td class="px-4 py-3 text-slate-500">{{ o.category || '—' }}</td>
              <td class="px-4 py-3">{{ o.is_active ? 'Yes' : 'No' }}</td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <button @click="openEdit(o)" class="text-slate-400 hover:text-indigo-600 mr-3"><PencilSquareIcon class="w-4 h-4 inline" /></button>
                <button @click="remove(o)" class="text-slate-400 hover:text-rose-600"><TrashIcon class="w-4 h-4 inline" /></button>
              </td>
            </tr>
            <tr v-if="!offenses.length"><td colspan="6" class="px-4 py-10 text-center text-slate-400">No offenses yet.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>
