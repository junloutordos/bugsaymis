<template>
  <Head title="Offense Catalog" />
  <AdminLayout title="Offense Catalog">
    <div class="max-w-4xl mx-auto space-y-5">

      <AppPageHeader title="Offense Catalog" subtitle="Code of Conduct offenses (Level 1–3)">
        <template #actions>
          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            Add Offense
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Table -->
      <AppTable :is-empty="!offenses.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Level</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Code</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Title</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Category</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Active</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="o in offenses" :key="o.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3"><AppBadge :color="levelColor(o.level)">L{{ o.level }}</AppBadge></td>
          <td class="px-4 py-3 text-slate-500">{{ o.code || '—' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ o.title }}</td>
          <td class="px-4 py-3 text-slate-500">{{ o.category || '—' }}</td>
          <td class="px-4 py-3"><AppBadge :color="o.is_active ? 'green' : 'slate'">{{ o.is_active ? 'Yes' : 'No' }}</AppBadge></td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1 justify-end">
              <AppIconButton label="Edit offense" @click="openEdit(o)">
                <PencilSquareIcon class="w-5 h-5" />
              </AppIconButton>
              <AppIconButton label="Delete offense" variant="danger" @click="remove(o)">
                <TrashIcon class="w-5 h-5" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="o in offenses" :key="o.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs text-slate-500">{{ o.code || '—' }}</p>
                <p class="font-medium text-slate-800">{{ o.title }}</p>
                <p v-if="o.category" class="text-xs text-slate-400">{{ o.category }}</p>
              </div>
              <AppBadge :color="levelColor(o.level)">L{{ o.level }}</AppBadge>
            </div>
            <div class="flex items-center justify-between pt-1">
              <AppBadge :color="o.is_active ? 'green' : 'slate'">{{ o.is_active ? 'Active' : 'Inactive' }}</AppBadge>
              <div class="flex items-center gap-1">
                <AppIconButton label="Edit offense" @click="openEdit(o)">
                  <PencilSquareIcon class="w-5 h-5" />
                </AppIconButton>
                <AppIconButton label="Delete offense" variant="danger" @click="remove(o)">
                  <TrashIcon class="w-5 h-5" />
                </AppIconButton>
              </div>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No offenses yet" />
        </template>
      </AppTable>

    </div>

    <!-- Add/Edit Modal -->
    <AppModal :show="showForm" :title="editingId ? 'Edit Offense' : 'Add Offense'" @close="showForm = false">
      <div class="space-y-4">
        <div class="grid sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Code</label>
            <input v-model="form.code" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Level <span class="text-red-500">*</span></label>
            <select v-model="form.level" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option :value="1">Level 1</option>
              <option :value="2">Level 2</option>
              <option :value="3">Level 3</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Category</label>
            <input v-model="form.category" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Title <span class="text-red-500">*</span></label>
          <input v-model="form.title" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                 :class="{ 'border-red-400': form.errors.title }" />
          <p v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Default sanction</label>
          <textarea v-model="form.default_sanction" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"></textarea>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" v-model="form.is_active" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" /> Active
        </label>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showForm = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="save">Save</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
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
async function remove(o) {
  const ok = await confirmDelete(`Remove "${o.title}" from the catalog?`)
  if (ok) {
    router.delete(route('discipline.offenses.destroy', o.id), { preserveScroll: true })
  }
}

const levelColor = (l) => ({ 1: 'amber', 2: 'orange', 3: 'red' }[l] || 'slate')
</script>
