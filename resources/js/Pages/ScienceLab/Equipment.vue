<script setup>
import { ref } from 'vue'
import { Head, useForm, router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import { PlusIcon, PencilIcon, TrashIcon, MagnifyingGlassIcon, EyeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  equipment: Array, labRooms: Array, endUsers: Array, statuses: Array, frequencies: Array, filters: Object,
})

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')
const showModal = ref(false)
const editing = ref(null)

const statusLabels = {
  active: 'Active', under_repair: 'Under Repair', for_calibration: 'For Calibration',
  out_of_service: 'Out of Service', for_disposal: 'For Disposal', disposed: 'Disposed',
}
const statusTone = {
  active: 'bg-emerald-50 text-emerald-600', under_repair: 'bg-amber-50 text-amber-600',
  for_calibration: 'bg-violet-50 text-violet-600', out_of_service: 'bg-rose-50 text-rose-600',
  for_disposal: 'bg-slate-100 text-slate-500', disposed: 'bg-slate-100 text-slate-400',
}

const form = useForm({
  property_no: '', description: '', specifications: '', model: '', serial_number: '', room_id: '', end_user_id: '', unit: '',
  category: '', date_acquired: '', unit_cost: '', status: 'active',
  calibration_required: false, calibration_frequency: '', pm_required: false, pm_frequency: '', remarks: '',
})

function applyFilters() {
  router.get(route('science-lab.equipment.index'), { search: search.value, status: statusFilter.value }, { preserveState: true, replace: true })
}
function openCreate() { editing.value = null; form.reset(); form.clearErrors(); showModal.value = true }
function openEdit(e) {
  editing.value = e
  Object.keys(form.data()).forEach(k => (form[k] = e[k] ?? (typeof form[k] === 'boolean' ? false : '')))
  showModal.value = true
}
function submit() {
  const opts = { preserveScroll: true, onSuccess: () => (showModal.value = false) }
  editing.value ? form.put(route('science-lab.equipment.update', editing.value.id), opts)
                : form.post(route('science-lab.equipment.store'), opts)
}
function remove(e) {
  if (confirm(`Remove ${e.description} from the registry?`)) router.delete(route('science-lab.equipment.destroy', e.id), { preserveScroll: true })
}
</script>

<template>
  <Head title="Equipment Registry" />
  <AdminLayout title="Equipment Registry">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <div class="relative">
          <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input v-model="search" @keyup.enter="applyFilters" placeholder="Search property no / description…" class="w-72 rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm focus:ring-2 focus:ring-indigo-500" />
        </div>
        <select v-model="statusFilter" @change="applyFilters" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
          <option value="">All statuses</option>
          <option v-for="s in statuses" :key="s" :value="s">{{ statusLabels[s] }}</option>
        </select>
      </div>
      <button @click="openCreate" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
        <PlusIcon class="h-4 w-4" /> Add Equipment
      </button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3 text-left">Property No</th>
            <th class="px-4 py-3 text-left">Equipment / Specifications</th>
            <th class="px-4 py-3 text-left">End User</th>
            <th class="px-4 py-3 text-left">Location</th>
            <th class="px-4 py-3 text-left">Cal / PM</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="e in equipment" :key="e.id" class="hover:bg-slate-50/50">
            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ e.property_no || '—' }}</td>
            <td class="px-4 py-3">
              <div class="font-medium text-slate-800">{{ e.description }}</div>
              <div class="max-w-xs truncate text-xs text-slate-400">{{ e.specifications || e.model || e.category || '' }}</div>
            </td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ e.end_user || '—' }}</td>
            <td class="px-4 py-3">{{ e.room || '—' }}</td>
            <td class="px-4 py-3 text-xs">
              <span v-if="e.calibration_required" class="mr-1 rounded bg-violet-50 px-1.5 py-0.5 text-violet-600">Cal {{ e.calibration_frequency }}</span>
              <span v-if="e.pm_required" class="rounded bg-sky-50 px-1.5 py-0.5 text-sky-600">PM {{ e.pm_frequency }}</span>
              <span v-if="!e.calibration_required && !e.pm_required" class="text-slate-400">—</span>
            </td>
            <td class="px-4 py-3"><span :class="['rounded-full px-2 py-0.5 text-xs font-medium', statusTone[e.status]]">{{ statusLabels[e.status] }}</span></td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <Link :href="route('science-lab.equipment.show', e.id)" class="mr-1 inline-block rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"><EyeIcon class="h-4 w-4" /></Link>
              <button @click="openEdit(e)" class="mr-1 rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"><PencilIcon class="h-4 w-4" /></button>
              <button @click="remove(e)" class="rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"><TrashIcon class="h-4 w-4" /></button>
            </td>
          </tr>
          <tr v-if="!equipment.length"><td colspan="7" class="px-4 py-10 text-center text-slate-400">No equipment registered.</td></tr>
        </tbody>
      </table>
    </div>

    <AppModal :show="showModal" :title="editing ? 'Edit Equipment' : 'Add Equipment'" size="2xl" @close="showModal = false">
      <div class="grid grid-cols-2 gap-4">
        <label class="text-sm">Property No<input v-model="form.property_no" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Equipment Name *<input v-model="form.description" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /><span v-if="form.errors.description" class="text-xs text-rose-600">{{ form.errors.description }}</span></label>
        <label class="col-span-2 text-sm">Description / Specifications<textarea v-model="form.specifications" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea></label>
        <label class="text-sm">Model<input v-model="form.model" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Serial Number<input v-model="form.serial_number" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Location (Lab)
          <select v-model="form.room_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
            <option value="">—</option><option v-for="r in labRooms" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </label>
        <label class="text-sm">Unit<input v-model="form.unit" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">End User
          <select v-model="form.end_user_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
            <option value="">—</option><option v-for="user in endUsers" :key="user.id" :value="user.id">{{ user.name }}</option>
          </select>
        </label>
        <label class="text-sm">Category<input v-model="form.category" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Date Acquired<input type="date" v-model="form.date_acquired" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Unit Cost<input type="number" step="0.01" v-model="form.unit_cost" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Status
          <select v-model="form.status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
            <option v-for="s in statuses" :key="s" :value="s">{{ statusLabels[s] }}</option>
          </select>
        </label>
        <div class="col-span-2 grid grid-cols-2 gap-4 rounded-lg bg-slate-50 p-3">
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.calibration_required" /> Requires calibration</label>
          <select v-model="form.calibration_frequency" :disabled="!form.calibration_required" class="rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:opacity-40">
            <option value="">Frequency…</option><option v-for="f in frequencies" :key="f" :value="f">{{ f }}</option>
          </select>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.pm_required" /> Requires preventive maintenance</label>
          <select v-model="form.pm_frequency" :disabled="!form.pm_required" class="rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:opacity-40">
            <option value="">Frequency…</option><option v-for="f in frequencies" :key="f" :value="f">{{ f }}</option>
          </select>
        </div>
        <label class="col-span-2 text-sm">Remarks<textarea v-model="form.remarks" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea></label>
      </div>
      <template #footer>
        <button @click="showModal = false" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancel</button>
        <button @click="submit" :disabled="form.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">Save</button>
      </template>
    </AppModal>
  </AdminLayout>
</template>
