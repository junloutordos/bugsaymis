<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import { PlusIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ schedules: Array, tickets: Array, equipment: Array, schoolYear: Object, pmStatuses: Array, ticketStatuses: Array, frequencies: Array })

// School-year month order Aug..Jul
const MONTHS = [{ n: 8, l: 'Aug' }, { n: 9, l: 'Sep' }, { n: 10, l: 'Oct' }, { n: 11, l: 'Nov' }, { n: 12, l: 'Dec' }, { n: 1, l: 'Jan' }, { n: 2, l: 'Feb' }, { n: 3, l: 'Mar' }, { n: 4, l: 'Apr' }, { n: 5, l: 'May' }, { n: 6, l: 'Jun' }, { n: 7, l: 'Jul' }]
const GLYPH = { as_scheduled: '✓', not_as_scheduled: '○', not_performed: '✗', none: '' }
const CYCLE = ['none', 'as_scheduled', 'not_as_scheduled', 'not_performed']
const cellTone = { as_scheduled: 'text-emerald-600', not_as_scheduled: 'text-amber-600', not_performed: 'text-rose-600', none: 'text-slate-300' }

const showSchedule = ref(false)
const showTicket = ref(false)
const showLog = ref(false)
const ticketEdit = ref(null)

const scheduleForm = useForm({ lab_equipment_id: '', frequency: 'Quarterly', remarks: '' })
const ticketForm = useForm({ lab_equipment_id: '', problem: '', priority: 'medium', immediate_action: '', external_provider: '' })
const ticketUpdateForm = useForm({ status: 'in_repair', immediate_action: '', external_provider: '', signage_placed: false, date_completed: '', remarks: '' })
const logForm = useForm({ lab_equipment_id: '', repair_ticket_id: '', log_date: '', particulars: '', type: 'preventive', remarks_reference: '', cost_of_materials: 0, serviced_by: '' })

function cycleCell(schedule, month) {
  const cur = schedule.records[month] || 'none'
  const next = CYCLE[(CYCLE.indexOf(cur) + 1) % CYCLE.length]
  router.post(route('science-lab.maintenance.records.update'), { lab_pm_schedule_id: schedule.id, month, status: next }, { preserveScroll: true, preserveState: false })
}
function submitSchedule() { scheduleForm.post(route('science-lab.maintenance.schedules.store'), { preserveScroll: true, onSuccess: () => { showSchedule.value = false; scheduleForm.reset() } }) }
function submitTicket() { ticketForm.post(route('science-lab.maintenance.tickets.store'), { preserveScroll: true, onSuccess: () => { showTicket.value = false; ticketForm.reset() } }) }
function openTicketEdit(t) { ticketEdit.value = t; ticketUpdateForm.status = t.status; ticketUpdateForm.immediate_action = t.immediate_action || ''; ticketUpdateForm.external_provider = t.external_provider || ''; ticketUpdateForm.signage_placed = !!t.signage_placed; ticketUpdateForm.remarks = t.remarks || '' }
function submitTicketUpdate() { ticketUpdateForm.put(route('science-lab.maintenance.tickets.update', ticketEdit.value.id), { preserveScroll: true, onSuccess: () => (ticketEdit.value = null) }) }
function submitLog() { logForm.post(route('science-lab.maintenance.service-logs.store'), { preserveScroll: true, onSuccess: () => { showLog.value = false; logForm.reset() } }) }

const ticketTone = { open: 'bg-rose-50 text-rose-600', in_repair: 'bg-amber-50 text-amber-600', resolved: 'bg-emerald-50 text-emerald-600', filed: 'bg-slate-100 text-slate-500', cancelled: 'bg-slate-100 text-slate-400' }
</script>

<template>
  <Head title="Maintenance" />
  <AdminLayout title="Maintenance">
    <div class="space-y-8">
      <!-- Preventive maintenance schedule grid -->
      <section>
        <div class="mb-3 flex items-center justify-between">
          <div><h2 class="text-sm font-semibold text-slate-700">Preventive Maintenance Schedule</h2><p class="text-xs text-slate-400">CID-08 · SY {{ schoolYear?.name || '—' }} · click a month to cycle ✓ ○ ✗</p></div>
          <div class="flex gap-2">
            <button @click="showLog = true" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">+ Service Log</button>
            <button @click="showSchedule = true" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"><PlusIcon class="h-4 w-4" /> Add to Schedule</button>
          </div>
        </div>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr><th class="px-3 py-2 text-left">Equipment</th><th class="px-3 py-2 text-left">Freq</th><th v-for="m in MONTHS" :key="m.n" class="px-2 py-2 text-center">{{ m.l }}</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="s in schedules" :key="s.id">
                <td class="px-3 py-2"><div class="font-medium text-slate-700">{{ s.equipment }}</div><div class="text-xs text-slate-400">{{ s.property_no }} · {{ s.location }}</div></td>
                <td class="px-3 py-2 text-xs">{{ s.frequency }}</td>
                <td v-for="m in MONTHS" :key="m.n" class="px-2 py-2 text-center">
                  <button @click="cycleCell(s, m.n)" :class="['h-6 w-6 rounded font-bold hover:bg-slate-100', cellTone[s.records[m.n] || 'none']]">{{ GLYPH[s.records[m.n] || 'none'] }}</button>
                </td>
              </tr>
              <tr v-if="!schedules.length"><td :colspan="2 + MONTHS.length" class="px-3 py-8 text-center text-slate-400">No equipment scheduled.</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Repair tickets -->
      <section>
        <div class="mb-3 flex items-center justify-between">
          <div><h2 class="text-sm font-semibold text-slate-700">Corrective Maintenance / Repair Tickets</h2><p class="text-xs text-slate-400">CID-10</p></div>
          <button @click="showTicket = true" class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700"><WrenchScrewdriverIcon class="h-4 w-4" /> New Ticket</button>
        </div>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 text-left">Control No</th><th class="px-4 py-3 text-left">Equipment</th><th class="px-4 py-3 text-left">Problem</th><th class="px-4 py-3 text-left">Reported</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="t in tickets" :key="t.id" class="hover:bg-slate-50/50">
                <td class="px-4 py-3 font-mono text-xs">{{ t.control_no }}</td>
                <td class="px-4 py-3">{{ t.equipment?.description || '—' }}</td>
                <td class="px-4 py-3 max-w-xs truncate text-slate-600">{{ t.problem }}</td>
                <td class="px-4 py-3 text-xs">{{ t.date_reported }}</td>
                <td class="px-4 py-3"><span :class="['rounded-full px-2 py-0.5 text-xs font-medium capitalize', ticketTone[t.status]]">{{ t.status.replace('_',' ') }}</span></td>
                <td class="px-4 py-3 text-right"><button @click="openTicketEdit(t)" class="rounded px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50">Update</button></td>
              </tr>
              <tr v-if="!tickets.length"><td colspan="6" class="px-4 py-8 text-center text-slate-400">No repair tickets.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- Schedule modal -->
    <AppModal :show="showSchedule" title="Add Equipment to PM Schedule" size="md" @close="showSchedule = false">
      <label class="text-sm">Equipment<select v-model="scheduleForm.lab_equipment_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="">Select…</option><option v-for="e in equipment" :key="e.id" :value="e.id">{{ e.description }} ({{ e.property_no || 'no prop' }})</option></select></label>
      <label class="mt-3 block text-sm">Frequency<select v-model="scheduleForm.frequency" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option v-for="f in frequencies" :key="f" :value="f">{{ f }}</option></select></label>
      <template #footer><button @click="showSchedule = false" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button><button @click="submitSchedule" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button></template>
    </AppModal>

    <!-- New ticket modal -->
    <AppModal :show="showTicket" title="New Repair Ticket" size="lg" @close="showTicket = false">
      <label class="text-sm">Equipment<select v-model="ticketForm.lab_equipment_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="">—</option><option v-for="e in equipment" :key="e.id" :value="e.id">{{ e.description }}</option></select></label>
      <label class="mt-3 block text-sm">Problem *<textarea v-model="ticketForm.problem" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea></label>
      <div class="mt-3 grid grid-cols-2 gap-3">
        <label class="text-sm">Priority<select v-model="ticketForm.priority" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select></label>
        <label class="text-sm">External Provider<input v-model="ticketForm.external_provider" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
      </div>
      <label class="mt-3 block text-sm">Immediate Action<textarea v-model="ticketForm.immediate_action" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea></label>
      <template #footer><button @click="showTicket = false" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button><button @click="submitTicket" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">Open Ticket</button></template>
    </AppModal>

    <!-- Update ticket modal -->
    <AppModal :show="!!ticketEdit" title="Update Repair Ticket" size="lg" @close="ticketEdit = null">
      <div class="grid grid-cols-2 gap-3">
        <label class="text-sm">Status<select v-model="ticketUpdateForm.status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 capitalize"><option v-for="s in ticketStatuses" :key="s" :value="s">{{ s.replace('_',' ') }}</option></select></label>
        <label class="text-sm">Date Completed<input type="date" v-model="ticketUpdateForm.date_completed" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">External Provider<input v-model="ticketUpdateForm.external_provider" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="ticketUpdateForm.signage_placed" /> Signage placed</label>
      </div>
      <label class="mt-3 block text-sm">Immediate Action<textarea v-model="ticketUpdateForm.immediate_action" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea></label>
      <label class="mt-3 block text-sm">Remarks<textarea v-model="ticketUpdateForm.remarks" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea></label>
      <template #footer><button @click="ticketEdit = null" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button><button @click="submitTicketUpdate" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Update</button></template>
    </AppModal>

    <!-- Service log modal -->
    <AppModal :show="showLog" title="Add Service Log Entry" size="lg" @close="showLog = false">
      <div class="grid grid-cols-2 gap-3">
        <label class="col-span-2 text-sm">Equipment<select v-model="logForm.lab_equipment_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="">Select…</option><option v-for="e in equipment" :key="e.id" :value="e.id">{{ e.description }}</option></select></label>
        <label class="text-sm">Date<input type="date" v-model="logForm.log_date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Type<select v-model="logForm.type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="preventive">Preventive</option><option value="corrective">Corrective</option></select></label>
        <label class="text-sm">Cost of Materials<input type="number" step="0.01" v-model="logForm.cost_of_materials" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Serviced By<input v-model="logForm.serviced_by" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="col-span-2 text-sm">Particulars *<textarea v-model="logForm.particulars" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea></label>
      </div>
      <template #footer><button @click="showLog = false" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button><button @click="submitLog" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Add</button></template>
    </AppModal>
  </AdminLayout>
</template>
