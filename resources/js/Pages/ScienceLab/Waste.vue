<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ logs: Array, labRooms: Array, types: Array, filters: Object })

const typeFilter = ref(props.filters.waste_type || '')
const showModal = ref(false)
const form = useForm({ room_id: '', unit: '', waste_date: '', waste_type: 'chemical', description: '', quantity: '', disposal_method: '', remarks: '' })
const typeTone = { chemical: 'bg-rose-50 text-rose-600', biological: 'bg-amber-50 text-amber-600', sharps: 'bg-orange-50 text-orange-600', general: 'bg-slate-100 text-slate-600', other: 'bg-slate-100 text-slate-500' }

function applyFilter() { router.get(route('science-lab.waste.index'), { waste_type: typeFilter.value }, { preserveState: true, replace: true }) }
function submit() { form.post(route('science-lab.waste.store'), { preserveScroll: true, onSuccess: () => { showModal.value = false; form.reset() } }) }
function remove(l) { if (confirm('Remove this waste log?')) router.delete(route('science-lab.waste.destroy', l.id), { preserveScroll: true }) }
</script>

<template>
  <Head title="Waste Management" />
  <AdminLayout title="Laboratory Waste Management">
    <div class="mb-4 flex items-center justify-between">
      <select v-model="typeFilter" @change="applyFilter" class="rounded-lg border border-slate-200 px-3 py-2 text-sm capitalize">
        <option value="">All types</option><option v-for="t in types" :key="t" :value="t">{{ t }}</option>
      </select>
      <button @click="showModal = true" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"><PlusIcon class="h-4 w-4" /> Log Disposal</button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">Description</th><th class="px-4 py-3 text-left">Qty</th><th class="px-4 py-3 text-left">Method</th><th class="px-4 py-3 text-left">Lab</th><th class="px-4 py-3 text-left">By</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="l in logs" :key="l.id" class="hover:bg-slate-50/50">
            <td class="px-4 py-3 text-xs">{{ l.waste_date }}</td>
            <td class="px-4 py-3"><span :class="['rounded-full px-2 py-0.5 text-xs font-medium capitalize', typeTone[l.waste_type]]">{{ l.waste_type }}</span></td>
            <td class="px-4 py-3">{{ l.description }}</td><td class="px-4 py-3">{{ l.quantity || '—' }}</td>
            <td class="px-4 py-3 text-slate-500">{{ l.disposal_method || '—' }}</td><td class="px-4 py-3">{{ l.room?.name || l.unit || '—' }}</td>
            <td class="px-4 py-3">{{ l.disposed_by?.name || '—' }}</td>
            <td class="px-4 py-3 text-right"><button @click="remove(l)" class="rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"><TrashIcon class="h-4 w-4" /></button></td>
          </tr>
          <tr v-if="!logs.length"><td colspan="8" class="px-4 py-10 text-center text-slate-400">No waste logs.</td></tr>
        </tbody>
      </table>
    </div>

    <AppModal :show="showModal" title="Log Waste Disposal" size="lg" @close="showModal = false">
      <div class="grid grid-cols-2 gap-3">
        <label class="text-sm">Date *<input type="date" v-model="form.waste_date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Type<select v-model="form.waste_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 capitalize"><option v-for="t in types" :key="t" :value="t">{{ t }}</option></select></label>
        <label class="text-sm">Lab<select v-model="form.room_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="">—</option><option v-for="r in labRooms" :key="r.id" :value="r.id">{{ r.name }}</option></select></label>
        <label class="text-sm">Unit<input v-model="form.unit" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="col-span-2 text-sm">Description *<input v-model="form.description" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Quantity<input v-model="form.quantity" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Disposal Method<input v-model="form.disposal_method" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="col-span-2 text-sm">Remarks<textarea v-model="form.remarks" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea></label>
      </div>
      <template #footer><button @click="showModal = false" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button><button @click="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button></template>
    </AppModal>
  </AdminLayout>
</template>
