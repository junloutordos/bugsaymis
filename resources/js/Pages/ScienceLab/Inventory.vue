<script setup>
import { ref } from 'vue'
import { Head, useForm, router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import { PlusIcon, PencilIcon, EyeIcon, ArrowDownTrayIcon, ScaleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ consumables: Array, labRooms: Array, types: Array, filters: Object })

const typeFilter = ref(props.filters.type || '')
const search = ref(props.filters.search || '')
const showModal = ref(false)
const editing = ref(null)
const receiveFor = ref(null)
const adjustFor = ref(null)

const typeTone = { consumable: 'bg-slate-100 text-slate-600', reagent: 'bg-sky-50 text-sky-600', chemical: 'bg-rose-50 text-rose-600' }

const form = useForm({ name: '', type: 'consumable', unit_of_measure: '', room_id: '', unit: '', is_chemical: false, reorder_level: 0, tracks_expiry: false, status: 'active', remarks: '', sds_base64: null })
const receiveForm = useForm({ quantity: '', lot_no: '', received_date: '', expiry_date: '', unit_cost: '', reference_number: '', remarks: '' })
const adjustForm = useForm({ delta: '', reason: '' })

function applyFilters() { router.get(route('science-lab.inventory.index'), { type: typeFilter.value, search: search.value }, { preserveState: true, replace: true }) }
function openCreate() { editing.value = null; form.reset(); form.clearErrors(); showModal.value = true }
function openEdit(c) { editing.value = c; Object.keys(form.data()).forEach(k => (form[k] = c[k] ?? (typeof form[k] === 'boolean' ? false : ''))); form.sds_base64 = null; showModal.value = true }
function onSds(e) { const f = e.target.files[0]; if (!f) return; const r = new FileReader(); r.onload = () => (form.sds_base64 = r.result); r.readAsDataURL(f) }
function submit() { const o = { preserveScroll: true, onSuccess: () => (showModal.value = false) }; editing.value ? form.put(route('science-lab.inventory.update', editing.value.id), o) : form.post(route('science-lab.inventory.store'), o) }
function submitReceive() { receiveForm.post(route('science-lab.inventory.receive', receiveFor.value.id), { preserveScroll: true, onSuccess: () => { receiveFor.value = null; receiveForm.reset() } }) }
function submitAdjust() { adjustForm.post(route('science-lab.inventory.adjust', adjustFor.value.id), { preserveScroll: true, onSuccess: () => { adjustFor.value = null; adjustForm.reset() } }) }
</script>

<template>
  <Head title="Inventory" />
  <AdminLayout title="Consumables & Reagents">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <input v-model="search" @keyup.enter="applyFilters" placeholder="Search…" class="w-64 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
        <select v-model="typeFilter" @change="applyFilters" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
          <option value="">All types</option><option v-for="t in types" :key="t" :value="t" class="capitalize">{{ t }}</option>
        </select>
      </div>
      <button @click="openCreate" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"><PlusIcon class="h-4 w-4" /> Add Item</button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3 text-left">Item</th><th class="px-4 py-3 text-left">Type</th>
            <th class="px-4 py-3 text-right">On Hand</th><th class="px-4 py-3 text-right">Reorder</th>
            <th class="px-4 py-3 text-left">SDS</th><th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="c in consumables" :key="c.id" class="hover:bg-slate-50/50">
            <td class="px-4 py-3"><div class="font-medium text-slate-800">{{ c.name }}</div><div class="text-xs text-slate-400">{{ c.room || c.unit || '' }}</div></td>
            <td class="px-4 py-3"><span :class="['rounded-full px-2 py-0.5 text-xs font-medium capitalize', typeTone[c.type]]">{{ c.type }}</span></td>
            <td class="px-4 py-3 text-right font-medium" :class="c.reorder_level > 0 && c.balance <= c.reorder_level ? 'text-amber-600' : 'text-slate-700'">{{ c.balance }} {{ c.unit_of_measure }}</td>
            <td class="px-4 py-3 text-right text-slate-500">{{ c.reorder_level }}</td>
            <td class="px-4 py-3">{{ c.has_sds ? '✓' : '—' }}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <button @click="receiveFor = c" title="Receive stock" class="mr-1 rounded p-1.5 text-emerald-500 hover:bg-emerald-50"><ArrowDownTrayIcon class="h-4 w-4" /></button>
              <button @click="adjustFor = c" title="Adjust" class="mr-1 rounded p-1.5 text-amber-500 hover:bg-amber-50"><ScaleIcon class="h-4 w-4" /></button>
              <Link :href="route('science-lab.inventory.show', c.id)" class="mr-1 inline-block rounded p-1.5 text-slate-400 hover:bg-slate-100"><EyeIcon class="h-4 w-4" /></Link>
              <button @click="openEdit(c)" class="rounded p-1.5 text-slate-400 hover:bg-slate-100"><PencilIcon class="h-4 w-4" /></button>
            </td>
          </tr>
          <tr v-if="!consumables.length"><td colspan="6" class="px-4 py-10 text-center text-slate-400">No items yet.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Item modal -->
    <AppModal :show="showModal" :title="editing ? 'Edit Item' : 'Add Item'" size="lg" @close="showModal = false">
      <div class="grid grid-cols-2 gap-4">
        <label class="col-span-2 text-sm">Name *<input v-model="form.name" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /><span v-if="form.errors.name" class="text-xs text-rose-600">{{ form.errors.name }}</span></label>
        <label class="text-sm">Type<select v-model="form.type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 capitalize"><option v-for="t in types" :key="t" :value="t">{{ t }}</option></select></label>
        <label class="text-sm">Unit of Measure<input v-model="form.unit_of_measure" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Location (Lab)<select v-model="form.room_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="">—</option><option v-for="r in labRooms" :key="r.id" :value="r.id">{{ r.name }}</option></select></label>
        <label class="text-sm">Owning Unit<input v-model="form.unit" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Reorder Level<input type="number" step="0.001" v-model="form.reorder_level" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Status<select v-model="form.status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.tracks_expiry" /> Track expiry (FEFO)</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.is_chemical" /> Chemical (needs SDS)</label>
        <label class="col-span-2 text-sm">Safety Data Sheet (PDF)<input type="file" accept="application/pdf,image/*" @change="onSds" class="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-600" /></label>
      </div>
      <template #footer>
        <button @click="showModal = false" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button>
        <button @click="submit" :disabled="form.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">Save</button>
      </template>
    </AppModal>

    <!-- Receive modal -->
    <AppModal :show="!!receiveFor" :title="`Receive stock — ${receiveFor?.name}`" size="md" @close="receiveFor = null">
      <div class="grid grid-cols-2 gap-4">
        <label class="text-sm">Quantity *<input type="number" step="0.001" v-model="receiveForm.quantity" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /><span v-if="receiveForm.errors.quantity" class="text-xs text-rose-600">{{ receiveForm.errors.quantity }}</span></label>
        <label class="text-sm">Lot No<input v-model="receiveForm.lot_no" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Received Date<input type="date" v-model="receiveForm.received_date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Expiry Date<input type="date" v-model="receiveForm.expiry_date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Unit Cost<input type="number" step="0.0001" v-model="receiveForm.unit_cost" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Reference No<input v-model="receiveForm.reference_number" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
      </div>
      <template #footer>
        <button @click="receiveFor = null" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button>
        <button @click="submitReceive" :disabled="receiveForm.processing" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Receive</button>
      </template>
    </AppModal>

    <!-- Adjust modal -->
    <AppModal :show="!!adjustFor" :title="`Adjust — ${adjustFor?.name}`" size="sm" @close="adjustFor = null">
      <label class="text-sm">Delta (+/-) *<input type="number" step="0.001" v-model="adjustForm.delta" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /><span v-if="adjustForm.errors.delta" class="text-xs text-rose-600">{{ adjustForm.errors.delta }}</span></label>
      <label class="mt-3 block text-sm">Reason *<input v-model="adjustForm.reason" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
      <template #footer>
        <button @click="adjustFor = null" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button>
        <button @click="submitAdjust" :disabled="adjustForm.processing" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">Adjust</button>
      </template>
    </AppModal>
  </AdminLayout>
</template>
