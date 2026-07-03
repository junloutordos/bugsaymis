<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import { PlusIcon, PencilIcon, TrashIcon, DocumentTextIcon } from '@heroicons/vue/24/outline'
import { storageUrl } from '@/Composables/useStorage'

const props = defineProps({
  profiles: Array,
  availableRooms: Array,
  units: Array,
  staff: Array,
})

const showModal = ref(false)
const editing = ref(null)

const form = useForm({
  room_id: '', unit: '', auh_user_id: '', in_charge_user_id: '',
  status: 'active', remarks: '', safety_procedure_base64: null,
})

function openCreate() {
  editing.value = null
  form.reset()
  form.clearErrors()
  showModal.value = true
}
function openEdit(p) {
  editing.value = p
  form.room_id = p.room_id
  form.unit = p.unit
  form.auh_user_id = p.auh_user_id || ''
  form.in_charge_user_id = p.in_charge_user_id || ''
  form.status = p.status
  form.remarks = p.remarks || ''
  form.safety_procedure_base64 = null
  showModal.value = true
}
function onFile(e) {
  const f = e.target.files[0]
  if (!f) return
  const reader = new FileReader()
  reader.onload = () => (form.safety_procedure_base64 = reader.result)
  reader.readAsDataURL(f)
}
function submit() {
  const opts = { preserveScroll: true, onSuccess: () => (showModal.value = false) }
  if (editing.value) form.put(route('science-lab.laboratories.update', editing.value.id), opts)
  else form.post(route('science-lab.laboratories.store'), opts)
}
function remove(p) {
  if (confirm(`Remove ${p.room} as a designated science laboratory?`)) {
    router.delete(route('science-lab.laboratories.destroy', p.id), { preserveScroll: true })
  }
}
</script>

<template>
  <Head title="Laboratories" />
  <AdminLayout title="Science Laboratories">
    <div class="mb-4 flex items-center justify-between">
      <p class="text-sm text-slate-500">Designate rooms as managed science laboratories and assign the in-charge SRS/SRA.</p>
      <button @click="openCreate" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
        <PlusIcon class="h-4 w-4" /> Designate Lab
      </button>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3 text-left">Laboratory</th>
            <th class="px-4 py-3 text-left">Unit</th>
            <th class="px-4 py-3 text-left">AUH</th>
            <th class="px-4 py-3 text-left">In-charge (SRS/SRA)</th>
            <th class="px-4 py-3 text-left">Safety Doc</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="p in profiles" :key="p.id" class="hover:bg-slate-50/50">
            <td class="px-4 py-3">
              <div class="font-medium text-slate-800">{{ p.room }}</div>
              <div class="text-xs text-slate-400">{{ p.building }}<span v-if="p.capacity"> · cap {{ p.capacity }}</span></div>
            </td>
            <td class="px-4 py-3">{{ p.unit }}</td>
            <td class="px-4 py-3">{{ p.auh || '—' }}</td>
            <td class="px-4 py-3">{{ p.in_charge || '—' }}</td>
            <td class="px-4 py-3">
              <a v-if="p.has_safety_doc" :href="storageUrl(p.safety_procedure_path)" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 hover:underline">
                <DocumentTextIcon class="h-4 w-4" /> View
              </a>
              <span v-else class="text-slate-400">—</span>
            </td>
            <td class="px-4 py-3">
              <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', p.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500']">{{ p.status }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <button @click="openEdit(p)" class="mr-1 rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"><PencilIcon class="h-4 w-4" /></button>
              <button @click="remove(p)" class="rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"><TrashIcon class="h-4 w-4" /></button>
            </td>
          </tr>
          <tr v-if="!profiles.length"><td colspan="7" class="px-4 py-10 text-center text-slate-400">No laboratories designated yet.</td></tr>
        </tbody>
      </table>
    </div>

    <AppModal :show="showModal" :title="editing ? 'Edit Laboratory' : 'Designate Laboratory'" size="lg" @close="showModal = false">
      <form @submit.prevent="submit" class="space-y-4">
        <div v-if="!editing">
          <label class="mb-1 block text-sm font-medium text-slate-600">Room</label>
          <select v-model="form.room_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            <option value="">Select room…</option>
            <option v-for="r in availableRooms" :key="r.id" :value="r.id">{{ r.label }}</option>
          </select>
          <p v-if="form.errors.room_id" class="mt-1 text-xs text-rose-600">{{ form.errors.room_id }}</p>
        </div>
        <div v-else class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ editing.room }}</div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-600">Unit</label>
            <select v-model="form.unit" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
              <option value="">Select…</option>
              <option v-for="u in units" :key="u" :value="u">{{ u }}</option>
            </select>
            <p v-if="form.errors.unit" class="mt-1 text-xs text-rose-600">{{ form.errors.unit }}</p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-600">Status</label>
            <select v-model="form.status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-600">Academic Unit Head</label>
            <select v-model="form.auh_user_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
              <option value="">—</option>
              <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-600">In-charge (SRS/SRA)</label>
            <select v-model="form.in_charge_user_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
              <option value="">—</option>
              <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-600">Safety Procedure (PDF/image)</label>
          <input type="file" accept="application/pdf,image/*" @change="onFile" class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-600" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-600">Remarks</label>
          <textarea v-model="form.remarks" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
        </div>
      </form>
      <template #footer>
        <button @click="showModal = false" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancel</button>
        <button @click="submit" :disabled="form.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">Save</button>
      </template>
    </AppModal>
  </AdminLayout>
</template>
