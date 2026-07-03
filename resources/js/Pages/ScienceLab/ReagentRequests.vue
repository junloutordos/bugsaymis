<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import { PlusIcon, TrashIcon, DocumentArrowDownIcon, CheckBadgeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ requests: Array, reagents: Array, schoolYear: Object, canManage: Boolean, hasPin: Boolean })

const statusTone = {
  pending: 'bg-amber-50 text-amber-600', endorsed: 'bg-sky-50 text-sky-600', approved: 'bg-emerald-50 text-emerald-600',
  released: 'bg-violet-50 text-violet-600', completed: 'bg-slate-100 text-slate-500',
  declined: 'bg-rose-50 text-rose-600', cancelled: 'bg-slate-100 text-slate-400',
}

const showCreate = ref(false)
const signFor = ref(null)
const releaseFor = ref(null)

const form = useForm({
  grade_level_section: '', number_of_students: '', subject: '', concurrent_topic: '', unit: '',
  teacher_in_charge: '', venue: '', date_start: '', time_start: '', time_end: '',
  requester_type: 'teacher', sds_acknowledged: false, remarks: '',
  items: [{ lab_consumable_id: '', quantity: 1, reagent: '', sds_read: false }],
})
const signForm = useForm({ pin: '' })
const releaseForm = useForm({ items: [] })

const addItem = () => form.items.push({ lab_consumable_id: '', quantity: 1, reagent: '', sds_read: false })
const removeItem = (i) => form.items.splice(i, 1)
function onReagentPick(it) { const r = props.reagents.find(x => x.id === Number(it.lab_consumable_id)); if (r) it.reagent = r.name }
function submitCreate() { form.post(route('science-lab.reagent-requests.store'), { preserveScroll: true, onSuccess: () => { showCreate.value = false; form.reset() } }) }
function openSign(r, action) { signFor.value = { r, action }; signForm.reset() }
function submitSign() { signForm.post(route(`science-lab.reagent-requests.${signFor.value.action}`, signFor.value.r.id), { preserveScroll: true, onSuccess: () => (signFor.value = null) }) }
function openRelease(r) { releaseFor.value = r; releaseForm.items = r.items.map(i => ({ id: i.id, issued_amount: '' })) }
function submitRelease() { releaseForm.post(route('science-lab.reagent-requests.release', releaseFor.value.id), { preserveScroll: true, onSuccess: () => (releaseFor.value = null) }) }
</script>

<template>
  <Head title="Reagent Requests" />
  <AdminLayout title="Reagent Requests">
    <div class="mb-4 flex items-center justify-between">
      <p class="text-sm text-slate-500">CID-19 · SY {{ schoolYear?.name || '—' }}</p>
      <button @click="showCreate = true" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"><PlusIcon class="h-4 w-4" /> New Request</button>
    </div>

    <div class="space-y-3">
      <div v-for="r in requests" :key="r.id" class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2"><span class="font-mono text-xs text-slate-500">{{ r.control_no }}</span><span :class="['rounded-full px-2 py-0.5 text-xs font-medium capitalize', statusTone[r.status]]">{{ r.status }}</span></div>
            <div class="mt-1 font-medium text-slate-800">{{ r.subject || '—' }} <span class="text-xs font-normal text-slate-400">{{ r.grade_level_section }}</span></div>
            <div class="text-xs text-slate-400">{{ r.date_start }} · {{ r.requester?.name || r.requester_name }} · {{ r.items.length }} reagent(s)</div>
          </div>
          <div class="flex flex-wrap items-center gap-1">
            <button v-if="r.status === 'pending'" @click="openSign(r, 'endorse')" class="rounded px-2 py-1 text-xs font-medium text-sky-600 hover:bg-sky-50">Endorse</button>
            <button v-if="canManage && ['pending','endorsed'].includes(r.status)" @click="openSign(r, 'approve')" class="rounded px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50">Approve</button>
            <button v-if="canManage && r.status === 'approved'" @click="openRelease(r)" class="rounded px-2 py-1 text-xs font-medium text-violet-600 hover:bg-violet-50">Release</button>
            <a :href="route('science-lab.reagent-requests.pdf', r.id)" target="_blank" class="rounded p-1.5 text-slate-400 hover:bg-slate-100"><DocumentArrowDownIcon class="h-4 w-4" /></a>
          </div>
        </div>
        <table class="mt-3 w-full text-xs">
          <thead class="text-slate-400"><tr><th class="py-1 text-left">Qty</th><th class="text-left">Reagent</th><th class="text-left">SDS</th><th class="text-left">Issued</th></tr></thead>
          <tbody><tr v-for="it in r.items" :key="it.id" class="border-t border-slate-50"><td class="py-1">{{ it.quantity }}</td><td>{{ it.reagent }}</td><td>{{ it.sds_read ? '✓' : '✗' }}</td><td class="text-slate-500">{{ it.issued_amount || '—' }}</td></tr></tbody>
        </table>
      </div>
      <div v-if="!requests.length" class="rounded-xl border border-slate-200 bg-white py-10 text-center text-slate-400">No requests.</div>
    </div>

    <!-- Create -->
    <AppModal :show="showCreate" title="New Reagent Request" size="3xl" @close="showCreate = false">
      <div class="grid grid-cols-3 gap-3">
        <label class="text-sm">Grade & Section<input v-model="form.grade_level_section" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5" /></label>
        <label class="text-sm">Subject<input v-model="form.subject" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5" /></label>
        <label class="text-sm">Concurrent Topic<input v-model="form.concurrent_topic" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5" /></label>
        <label class="text-sm">Unit<input v-model="form.unit" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5" /></label>
        <label class="text-sm">Teacher In-Charge<input v-model="form.teacher_in_charge" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5" /></label>
        <label class="text-sm">Venue<input v-model="form.venue" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5" /></label>
        <label class="text-sm">Date Start *<input type="date" v-model="form.date_start" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5" /></label>
        <label class="text-sm">Time Start<input type="time" v-model="form.time_start" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5" /></label>
        <label class="text-sm">Time End<input type="time" v-model="form.time_end" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5" /></label>
      </div>
      <div class="mt-4">
        <div class="mb-1 flex items-center justify-between"><span class="text-sm font-medium text-slate-600">Reagents</span><button @click="addItem" class="text-xs text-indigo-600 hover:underline">+ Add row</button></div>
        <div v-for="(it, i) in form.items" :key="i" class="mb-2 grid grid-cols-12 items-center gap-2">
          <select v-model="it.lab_consumable_id" @change="onReagentPick(it)" class="col-span-4 rounded-lg border border-slate-200 px-2 py-1.5 text-sm"><option value="">Free-text…</option><option v-for="rg in reagents" :key="rg.id" :value="rg.id">{{ rg.name }} ({{ rg.balance }} {{ rg.unit }})</option></select>
          <input v-model="it.reagent" placeholder="Reagent" class="col-span-4 rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
          <input type="number" step="0.001" v-model="it.quantity" placeholder="Qty" class="col-span-2 rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
          <label class="col-span-1 flex items-center gap-1 text-xs"><input type="checkbox" v-model="it.sds_read" /> SDS</label>
          <button @click="removeItem(i)" class="col-span-1 rounded p-1.5 text-slate-400 hover:text-rose-600"><TrashIcon class="h-4 w-4" /></button>
        </div>
        <label class="mt-2 flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" v-model="form.sds_acknowledged" /> I certify that the students have read the Safety Data Sheet (SDS) of the requested reagents.</label>
        <p v-if="form.errors.sds_acknowledged" class="text-xs text-rose-600">{{ form.errors.sds_acknowledged }}</p>
      </div>
      <template #footer>
        <button @click="showCreate = false" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button>
        <button @click="submitCreate" :disabled="form.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">File</button>
      </template>
    </AppModal>

    <!-- Sign -->
    <AppModal :show="!!signFor" title="Sign Request" size="sm" @close="signFor = null">
      <p v-if="!hasPin" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-700">Set a signing PIN in your profile first.</p>
      <template v-else>
        <p class="mb-3 text-sm text-slate-500 capitalize">{{ signFor?.action }} {{ signFor?.r.control_no }}.</p>
        <input v-model="signForm.pin" type="password" placeholder="Signing PIN" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-center tracking-widest" />
        <p v-if="signForm.errors.pin" class="mt-1 text-xs text-rose-600">{{ signForm.errors.pin }}</p>
      </template>
      <template #footer>
        <button @click="signFor = null" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button>
        <button v-if="hasPin" @click="submitSign" class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"><CheckBadgeIcon class="h-4 w-4" /> Sign</button>
      </template>
    </AppModal>

    <!-- Release -->
    <AppModal :show="!!releaseFor" title="Release Reagents" size="lg" @close="releaseFor = null">
      <p class="mb-2 text-xs text-slate-500">Linked catalogue reagents are deducted from inventory (FEFO) on release.</p>
      <div v-for="(row, i) in releaseForm.items" :key="row.id" class="mb-2 grid grid-cols-12 items-center gap-2">
        <div class="col-span-6 text-sm">{{ releaseFor.items[i].reagent }} <span class="text-xs text-slate-400">× {{ releaseFor.items[i].quantity }}</span></div>
        <input v-model="row.issued_amount" placeholder="Issued amount/remarks" class="col-span-6 rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
      </div>
      <p v-if="releaseForm.errors.release" class="text-xs text-rose-600">{{ releaseForm.errors.release }}</p>
      <template #footer>
        <button @click="releaseFor = null" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button>
        <button @click="submitRelease" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">Release</button>
      </template>
    </AppModal>
  </AdminLayout>
</template>
