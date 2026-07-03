<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import { PlusIcon, CheckBadgeIcon } from '@heroicons/vue/24/outline'
import { storageUrl } from '@/Composables/useStorage'

const props = defineProps({ schedules: Array, equipment: Array, schoolYear: Object, frequencies: Array, canApprove: Boolean })

const schedTone = { draft: 'bg-slate-100 text-slate-500', submitted: 'bg-amber-50 text-amber-600', approved: 'bg-emerald-50 text-emerald-600' }
const eventTone = { scheduled: 'bg-sky-50 text-sky-600', done: 'bg-emerald-50 text-emerald-600', overdue: 'bg-rose-50 text-rose-600', out_of_service: 'bg-rose-100 text-rose-700' }

const showSchedule = ref(false)
const eventFor = ref(null)
const scheduleForm = useForm({ lab_equipment_id: '', frequency: 'Yearly', remarks: '' })
const eventForm = useForm({ lab_calibration_schedule_id: '', target_date: '', actual_date: '', calibrated_by: '', is_external: true, sticker_attached: false, due_date: '', result: 'pending', remarks: '', certificate_base64: null })

function submitSchedule() { scheduleForm.post(route('science-lab.calibration.schedules.store'), { preserveScroll: true, onSuccess: () => { showSchedule.value = false; scheduleForm.reset() } }) }
function submit(action, id) { router.post(route(`science-lab.calibration.schedules.${action}`, id), {}, { preserveScroll: true }) }
function openEvent(s) { eventFor.value = s; eventForm.reset(); eventForm.lab_calibration_schedule_id = s.id }
function onCert(e) { const f = e.target.files[0]; if (!f) return; const r = new FileReader(); r.onload = () => (eventForm.certificate_base64 = r.result); r.readAsDataURL(f) }
function submitEvent() { eventForm.post(route('science-lab.calibration.events.store'), { preserveScroll: true, onSuccess: () => (eventFor.value = null) }) }
</script>

<template>
  <Head title="Calibration" />
  <AdminLayout title="Calibration">
    <div class="mb-4 flex items-center justify-between">
      <p class="text-sm text-slate-500">CID-09 · SY {{ schoolYear?.name || '—' }}</p>
      <button @click="showSchedule = true" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"><PlusIcon class="h-4 w-4" /> Add Schedule</button>
    </div>

    <div class="space-y-3">
      <div v-for="s in schedules" :key="s.id" class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <span class="font-medium text-slate-800">{{ s.equipment?.description }}</span>
              <span :class="['rounded-full px-2 py-0.5 text-xs font-medium capitalize', schedTone[s.status]]">{{ s.status }}</span>
            </div>
            <div class="text-xs text-slate-400">{{ s.equipment?.property_no }} · {{ s.equipment?.room?.name }} · {{ s.frequency }}</div>
          </div>
          <div class="flex items-center gap-1">
            <button v-if="s.status === 'draft'" @click="submit('submit', s.id)" class="rounded px-2 py-1 text-xs font-medium text-amber-600 hover:bg-amber-50">Submit</button>
            <button v-if="canApprove && ['submitted','draft'].includes(s.status)" @click="submit('approve', s.id)" class="rounded px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50">Approve</button>
            <button @click="openEvent(s)" class="rounded px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50">+ Record</button>
          </div>
        </div>
        <table v-if="s.events.length" class="mt-3 w-full text-xs">
          <thead class="text-slate-400"><tr><th class="py-1 text-left">Target</th><th class="text-left">Actual</th><th class="text-left">By</th><th class="text-left">Due</th><th class="text-left">Result</th><th class="text-left">Sticker</th><th class="text-left">Cert</th><th class="text-left">Status</th></tr></thead>
          <tbody>
            <tr v-for="ev in s.events" :key="ev.id" class="border-t border-slate-50">
              <td class="py-1">{{ ev.target_date || '—' }}</td><td>{{ ev.actual_date || '—' }}</td><td>{{ ev.calibrated_by || '—' }}</td>
              <td>{{ ev.due_date || '—' }}</td><td class="capitalize">{{ ev.result }}</td><td>{{ ev.sticker_attached ? '✓' : '—' }}</td>
              <td><a v-if="ev.certificate_path" :href="storageUrl(ev.certificate_path)" target="_blank" class="text-indigo-600 hover:underline">View</a><span v-else>—</span></td>
              <td><span :class="['rounded-full px-2 py-0.5 font-medium capitalize', eventTone[ev.status]]">{{ ev.status.replace('_',' ') }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!schedules.length" class="rounded-xl border border-slate-200 bg-white py-10 text-center text-slate-400">No calibration schedules.</div>
    </div>

    <AppModal :show="showSchedule" title="Add Calibration Schedule" size="md" @close="showSchedule = false">
      <label class="text-sm">Instrument<select v-model="scheduleForm.lab_equipment_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="">Select…</option><option v-for="e in equipment" :key="e.id" :value="e.id">{{ e.description }} ({{ e.property_no || 'no prop' }})</option></select></label>
      <label class="mt-3 block text-sm">Frequency<select v-model="scheduleForm.frequency" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option v-for="f in frequencies" :key="f" :value="f">{{ f }}</option></select></label>
      <template #footer><button @click="showSchedule = false" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button><button @click="submitSchedule" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button></template>
    </AppModal>

    <AppModal :show="!!eventFor" title="Calibration Record" size="lg" @close="eventFor = null">
      <div class="grid grid-cols-2 gap-3">
        <label class="text-sm">Target Date<input type="date" v-model="eventForm.target_date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Actual Date<input type="date" v-model="eventForm.actual_date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Calibrated By<input v-model="eventForm.calibrated_by" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Due Date<input type="date" v-model="eventForm.due_date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Result<select v-model="eventForm.result" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="pending">Pending</option><option value="passed">Passed</option><option value="failed">Failed</option></select></label>
        <div class="flex items-end gap-4">
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="eventForm.is_external" /> External</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="eventForm.sticker_attached" /> Sticker</label>
        </div>
        <label class="col-span-2 text-sm">Certificate (PDF)<input type="file" accept="application/pdf,image/*" @change="onCert" class="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-600" /></label>
      </div>
      <template #footer><button @click="eventFor = null" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button><button @click="submitEvent" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Record</button></template>
    </AppModal>
  </AdminLayout>
</template>
