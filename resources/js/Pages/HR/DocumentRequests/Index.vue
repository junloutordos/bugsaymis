<template>
  <Head title="My Document Requests" />
  <AdminLayout title="My Document Requests">
    <div class="space-y-5">
      <AppPageHeader title="My Document Requests" subtitle="Request official HR certifications and securely track every stage.">
        <template #actions><AppButton @click="openRequest"><PlusIcon class="h-4 w-4" /> New Request</AppButton></template>
      </AppPageHeader>

      <div v-if="$page.props.flash?.success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ $page.props.flash.success }}</div>

      <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-4">
        <button v-for="type in types" :key="type.id" @click="openRequest(type)" class="rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md">
          <div class="mb-3 flex items-start justify-between gap-2"><div class="rounded-xl bg-indigo-50 p-2 text-indigo-600"><DocumentTextIcon class="h-5 w-5" /></div><span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ type.sla_business_days }} business days</span></div>
          <p class="font-semibold text-slate-800">{{ type.name }}</p>
          <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ type.description }}</p>
          <p v-if="type.requires_ocd_approval" class="mt-3 text-[11px] font-medium text-amber-600">Requires OCD approval and signature</p>
        </button>
      </div>

      <AppCard>
        <div class="mb-4 flex items-center justify-between"><div><h2 class="font-semibold text-slate-800">Request History</h2><p class="text-xs text-slate-500">Secure access to current and issued requests</p></div><input v-model="search" placeholder="Search requests..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" /></div>
        <AppTable :is-empty="filtered.length === 0" :skeleton-cols="6">
          <template #head><tr><th class="px-4 py-3 text-left">Reference</th><th class="px-4 py-3 text-left">Document</th><th class="px-4 py-3 text-left">Filed</th><th class="px-4 py-3 text-left">Due</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-right">Action</th></tr></template>
          <tr v-for="row in filtered" :key="row.id" class="border-t border-slate-100 hover:bg-slate-50">
            <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ row.reference_no }}</td><td class="px-4 py-3 text-sm text-slate-700">{{ row.type.name }}</td><td class="px-4 py-3 text-xs text-slate-500">{{ date(row.submitted_at) }}</td><td class="px-4 py-3 text-xs" :class="isOverdue(row) ? 'font-semibold text-rose-600' : 'text-slate-500'">{{ date(row.due_at) }}</td><td class="px-4 py-3"><AppBadge :color="statusColor(row.status)">{{ label(row.status) }}</AppBadge></td><td class="px-4 py-3 text-right"><Link :href="route('hr.document-requests.show', row.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">View details</Link></td>
          </tr>
          <template #empty><EmptyState title="No document requests yet" subtitle="Choose a document above to file your first request." /></template>
        </AppTable>
      </AppCard>
    </div>

    <AppModal :show="showModal" title="New HR Document Request" size="2xl" body-class="space-y-4 px-6 py-5" @close="showModal=false">
      <div v-if="Object.keys(form.errors).length" class="rounded-lg bg-rose-50 p-3 text-xs text-rose-700"><p v-for="error in form.errors" :key="error">{{ error }}</p></div>
      <div><label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Document Type</label><select v-model="form.request_type_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="" disabled>Select a document</option><option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option></select></div>
      <AppTextarea v-model="form.purpose" label="Purpose" :rows="3" placeholder="State exactly where and why the document will be used" />
      <div class="grid gap-3 md:grid-cols-2"><AppInput v-model="form.addressed_to" label="Addressed To" placeholder="Person or office (optional)" /><AppInput v-model="form.destination_agency" label="Receiving Agency" placeholder="Agency or institution" /><AppInput v-model="form.needed_at" type="date" label="Date Needed" /><AppInput v-model="form.copies" type="number" min="1" max="10" label="Number of Copies" /></div>
      <div><label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Delivery Method</label><div class="flex gap-4 text-sm"><label class="flex items-center gap-2"><input v-model="form.delivery_method" type="radio" value="digital"> Secure digital copy</label><label class="flex items-center gap-2"><input v-model="form.delivery_method" type="radio" value="pickup"> Physical pickup</label></div></div>
      <AppTextarea v-model="form.special_instructions" label="Special Instructions" :rows="2" />
      <div><label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Supporting Files <span class="font-normal normal-case">(PDF/JPG/PNG, up to 10 MB each)</span></label><input type="file" multiple accept="application/pdf,image/jpeg,image/png" class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-indigo-700" @change="readFiles"><p v-if="fileNames.length" class="mt-2 text-xs text-slate-500">{{ fileNames.join(', ') }}</p></div>
      <template #footer><AppButton variant="secondary" @click="showModal=false">Cancel</AppButton><AppButton :loading="form.processing" @click="submit">Submit Request</AppButton></template>
    </AppModal>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { DocumentTextIcon, PlusIcon } from '@heroicons/vue/24/outline'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'

const props = defineProps({ requests: { type: Array, default: () => [] }, types: { type: Array, default: () => [] } })
const showModal = ref(false), search = ref(''), fileNames = ref([])
const form = useForm({ request_type_id: '', purpose: '', addressed_to: '', destination_agency: '', needed_at: '', copies: 1, delivery_method: 'digital', special_instructions: '', attachments: [] })
const filtered = computed(() => { const q=search.value.toLowerCase(); return props.requests.filter(r => `${r.reference_no} ${r.type.name} ${r.status}`.toLowerCase().includes(q)) })
function openRequest(type=null){ form.reset(); form.clearErrors(); fileNames.value=[]; if(type) form.request_type_id=type.id; showModal.value=true }
function readFiles(e){ const files=[...e.target.files]; fileNames.value=files.map(f=>f.name); Promise.all(files.map(file => new Promise((resolve,reject)=>{ const reader=new FileReader(); reader.onload=()=>resolve({name:file.name,data_uri:reader.result}); reader.onerror=reject; reader.readAsDataURL(file) }))).then(rows=>{ form.attachments=rows }) }
function submit(){ form.post(route('hr.document-requests.store'), { onSuccess:()=>{ showModal.value=false; form.reset() } }) }
function date(v){ return v ? new Date(v).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}) : '—' }
function label(s){ return s.replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase()) }
function isOverdue(r){ return r.due_at && !['issued','rejected','cancelled'].includes(r.status) && new Date(r.due_at)<new Date() }
function statusColor(s){ return ({issued:'green',submitted:'blue',processing:'indigo',pending_ocd_approval:'amber',ready_for_release:'emerald',returned:'orange',ocd_returned:'orange',rejected:'red',cancelled:'slate'})[s] ?? 'slate' }
</script>
