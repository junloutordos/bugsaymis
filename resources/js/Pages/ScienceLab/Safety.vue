<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import { PlusIcon, DocumentTextIcon } from '@heroicons/vue/24/outline'
import { storageUrl } from '@/Composables/useStorage'

const props = defineProps({ procedures: Array, orientations: Array, firstAid: Array, safetyDataSheets: Array, labRooms: Array, schoolYear: Object })

const tab = ref('procedures')
const showProc = ref(false)
const showOrient = ref(false)
const showAid = ref(false)

const procForm = useForm({ room_id: '', unit: '', title: '', body: '', is_posted: false, effective_date: '', document_base64: null })
const orientForm = useForm({ room_id: '', orientation_date: '', grade_level_section: '', topic: '', remarks: '', document_base64: null })
const aidForm = useForm({ room_id: '', check_date: '', means_of_verification: '', inclusion: '', is_complete: false, remarks: '', document_base64: null })

function onDoc(e) { const f = e.target.files[0]; if (!f) return; const r = new FileReader(); r.onload = () => (procForm.document_base64 = r.result); r.readAsDataURL(f) }
function onOrientDoc(e) { const f = e.target.files[0]; if (!f) return; const r = new FileReader(); r.onload = () => (orientForm.document_base64 = r.result); r.readAsDataURL(f) }
function onAidDoc(e) { const f = e.target.files[0]; if (!f) return; const r = new FileReader(); r.onload = () => (aidForm.document_base64 = r.result); r.readAsDataURL(f) }
function submitProc() { procForm.post(route('science-lab.safety.procedures.store'), { preserveScroll: true, onSuccess: () => { showProc.value = false; procForm.reset() } }) }
function submitOrient() { orientForm.post(route('science-lab.safety.orientations.store'), { preserveScroll: true, onSuccess: () => { showOrient.value = false; orientForm.reset() } }) }
function submitAid() { aidForm.post(route('science-lab.safety.first-aid.store'), { preserveScroll: true, onSuccess: () => { showAid.value = false; aidForm.reset() } }) }

const tabs = [['procedures', 'Safety Procedures'], ['orientations', 'Orientations'], ['firstAid', 'First-Aid Checks'], ['sds', 'Safety Data Sheets']]
</script>

<template>
  <Head title="Laboratory Safety" />
  <AdminLayout title="Laboratory Safety">
    <div class="mb-4 flex items-center justify-between">
      <div class="flex gap-1 rounded-lg bg-slate-100 p-1">
        <button v-for="[k, l] in tabs" :key="k" @click="tab = k" :class="['rounded-md px-3 py-1.5 text-sm font-medium', tab === k ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500']">{{ l }}</button>
      </div>
      <button v-if="tab === 'procedures'" @click="showProc = true" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"><PlusIcon class="h-4 w-4" /> Procedure</button>
      <button v-else-if="tab === 'orientations'" @click="showOrient = true" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"><PlusIcon class="h-4 w-4" /> Orientation</button>
      <button v-else-if="tab === 'firstAid'" @click="showAid = true" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"><PlusIcon class="h-4 w-4" /> First-Aid Check</button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
      <table v-if="tab === 'procedures'" class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 text-left">Title</th><th class="px-4 py-3 text-left">Lab / Unit</th><th class="px-4 py-3 text-left">Posted</th><th class="px-4 py-3 text-left">Effective</th><th class="px-4 py-3 text-left">Doc</th></tr></thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="p in procedures" :key="p.id"><td class="px-4 py-3 font-medium text-slate-800">{{ p.title }}</td><td class="px-4 py-3">{{ p.room?.name || p.unit || '—' }}</td><td class="px-4 py-3">{{ p.is_posted ? '✓' : '—' }}</td><td class="px-4 py-3 text-xs">{{ p.effective_date || '—' }}</td><td class="px-4 py-3"><a v-if="p.document_path" :href="storageUrl(p.document_path)" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 hover:underline"><DocumentTextIcon class="h-4 w-4" /> View</a><span v-else>—</span></td></tr>
          <tr v-if="!procedures.length"><td colspan="5" class="px-4 py-10 text-center text-slate-400">No procedures.</td></tr>
        </tbody>
      </table>
      <table v-else-if="tab === 'orientations'" class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Lab</th><th class="px-4 py-3 text-left">Section / Topic</th><th class="px-4 py-3 text-left">Conducted By</th><th class="px-4 py-3 text-left">Document</th></tr></thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="o in orientations" :key="o.id"><td class="px-4 py-3 text-xs">{{ o.orientation_date }}</td><td class="px-4 py-3">{{ o.room?.name || '—' }}</td><td class="px-4 py-3"><div>{{ o.grade_level_section || '—' }}</div><div class="text-xs text-slate-400">{{ o.topic || '' }}</div></td><td class="px-4 py-3">{{ o.conducted_by?.name || '—' }}</td><td class="px-4 py-3"><a v-if="o.document_path" :href="storageUrl(o.document_path)" target="_blank" class="text-indigo-600 hover:underline">View</a><span v-else>—</span></td></tr>
          <tr v-if="!orientations.length"><td colspan="5" class="px-4 py-10 text-center text-slate-400">No orientations logged.</td></tr>
        </tbody>
      </table>
      <table v-else-if="tab === 'firstAid'" class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 text-left">Lab</th><th class="px-4 py-3 text-left">Means of Verification</th><th class="px-4 py-3 text-left">Inclusion</th><th class="px-4 py-3 text-left">Last Inspection</th><th class="px-4 py-3 text-left">Document</th></tr></thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="a in firstAid" :key="a.id"><td class="px-4 py-3">{{ a.room?.name || '—' }}</td><td class="px-4 py-3">{{ a.means_of_verification || '—' }}</td><td class="px-4 py-3">{{ a.inclusion || '—' }}</td><td class="px-4 py-3 text-xs">{{ a.check_date }}</td><td class="px-4 py-3"><a v-if="a.document_path" :href="storageUrl(a.document_path)" target="_blank" class="text-indigo-600 hover:underline">View</a><span v-else>—</span></td></tr>
          <tr v-if="!firstAid.length"><td colspan="5" class="px-4 py-10 text-center text-slate-400">No checks logged.</td></tr>
        </tbody>
      </table>
      <table v-else class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 text-left">Reagent / Chemical</th><th class="px-4 py-3 text-left">CAS Number</th><th class="px-4 py-3 text-left">Hazards</th><th class="px-4 py-3 text-left">Location</th><th class="px-4 py-3 text-left">SDS</th></tr></thead>
        <tbody class="divide-y divide-slate-50"><tr v-for="sheet in safetyDataSheets" :key="sheet.id"><td class="px-4 py-3 font-medium text-slate-800">{{ sheet.name }}</td><td class="px-4 py-3">{{ sheet.cas_number || '—' }}</td><td class="px-4 py-3">{{ sheet.hazards || '—' }}</td><td class="px-4 py-3">{{ sheet.room?.name || '—' }}</td><td class="px-4 py-3"><a :href="storageUrl(sheet.sds_path)" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 hover:underline"><DocumentTextIcon class="h-4 w-4" /> View PDF</a></td></tr><tr v-if="!safetyDataSheets.length"><td colspan="5" class="px-4 py-10 text-center text-slate-400">No safety data sheets uploaded.</td></tr></tbody>
      </table>
    </div>

    <AppModal :show="showProc" title="Safety Procedure" size="lg" @close="showProc = false">
      <div class="grid grid-cols-2 gap-3">
        <label class="col-span-2 text-sm">Title *<input v-model="procForm.title" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Lab<select v-model="procForm.room_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="">—</option><option v-for="r in labRooms" :key="r.id" :value="r.id">{{ r.name }}</option></select></label>
        <label class="text-sm">Unit<input v-model="procForm.unit" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Effective Date<input type="date" v-model="procForm.effective_date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="procForm.is_posted" /> Posted in lab</label>
        <label class="col-span-2 text-sm">Body<textarea v-model="procForm.body" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea></label>
        <label class="col-span-2 text-sm">Document (PDF)<input type="file" accept="application/pdf,image/*" @change="onDoc" class="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-600" /></label>
      </div>
      <template #footer><button @click="showProc = false" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button><button @click="submitProc" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button></template>
    </AppModal>

    <AppModal :show="showOrient" title="Safety Orientation" size="md" @close="showOrient = false">
      <div class="grid grid-cols-2 gap-3">
        <label class="text-sm">Lab<select v-model="orientForm.room_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="">—</option><option v-for="r in labRooms" :key="r.id" :value="r.id">{{ r.name }}</option></select></label>
        <label class="text-sm">Date *<input type="date" v-model="orientForm.orientation_date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Section<input v-model="orientForm.grade_level_section" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Topic<input v-model="orientForm.topic" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="col-span-2 text-sm">Document (PDF)<input type="file" accept="application/pdf,image/*" @change="onOrientDoc" class="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-600" /></label>
        <label class="col-span-2 text-sm">Remarks<textarea v-model="orientForm.remarks" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea></label>
      </div>
      <template #footer><button @click="showOrient = false" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button><button @click="submitOrient" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Log</button></template>
    </AppModal>

    <AppModal :show="showAid" title="First-Aid Kit Check" size="md" @close="showAid = false">
      <div class="grid grid-cols-2 gap-3">
        <label class="text-sm">Lab<select v-model="aidForm.room_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="">—</option><option v-for="r in labRooms" :key="r.id" :value="r.id">{{ r.name }}</option></select></label>
        <label class="text-sm">Date *<input type="date" v-model="aidForm.check_date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="col-span-2 text-sm">Means of Verification<input v-model="aidForm.means_of_verification" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="col-span-2 text-sm">Inclusion<input v-model="aidForm.inclusion" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="col-span-2 text-sm">Document (PDF)<input type="file" accept="application/pdf,image/*" @change="onAidDoc" class="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-600" /></label>
        <label class="col-span-2 flex items-center gap-2 text-sm"><input type="checkbox" v-model="aidForm.is_complete" /> Kit complete &amp; within expiry</label>
        <label class="col-span-2 text-sm">Remarks<textarea v-model="aidForm.remarks" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea></label>
      </div>
      <template #footer><button @click="showAid = false" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button><button @click="submitAid" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button></template>
    </AppModal>
  </AdminLayout>
</template>
