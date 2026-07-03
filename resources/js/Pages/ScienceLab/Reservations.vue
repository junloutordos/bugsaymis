<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import { PlusIcon, CheckBadgeIcon, DocumentArrowDownIcon, XCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ reservations: Array, labRooms: Array, schoolYear: Object, canManage: Boolean, hasPin: Boolean, filters: Object })

const statusTone = {
  pending: 'bg-amber-50 text-amber-600', endorsed: 'bg-sky-50 text-sky-600', approved: 'bg-emerald-50 text-emerald-600',
  declined: 'bg-rose-50 text-rose-600', cancelled: 'bg-slate-100 text-slate-500', completed: 'bg-indigo-50 text-indigo-600',
}

const showCreate = ref(false)
const signFor = ref(null)      // { reservation, action }
const declineFor = ref(null)

const form = useForm({
  grade_level_section: '', number_of_students: '', subject: '', teacher_in_charge: '',
  date_start: '', date_end: '', time_start: '', time_end: '', room_id: '',
  requester_type: 'teacher', student_group: [], remarks: '',
})
const groupText = ref('')
const signForm = useForm({ pin: '' })
const declineForm = useForm({ decline_reason: '' })

function submitCreate() {
  form.student_group = groupText.value.split('\n').map(s => s.trim()).filter(Boolean)
  form.post(route('science-lab.reservations.store'), { preserveScroll: true, onSuccess: () => { showCreate.value = false; form.reset(); groupText.value = '' } })
}
function openSign(r, action) { signFor.value = { r, action }; signForm.reset() }
function submitSign() {
  const name = signFor.value.action === 'endorse' ? 'reservations.endorse' : 'reservations.approve'
  signForm.post(route(`science-lab.${name}`, signFor.value.r.id), { preserveScroll: true, onSuccess: () => (signFor.value = null) })
}
function submitDecline() { declineForm.post(route('science-lab.reservations.decline', declineFor.value.id), { preserveScroll: true, onSuccess: () => (declineFor.value = null) }) }
function act(r, verb) { router.post(route(`science-lab.reservations.${verb}`, r.id), {}, { preserveScroll: true }) }
</script>

<template>
  <Head title="Laboratory Reservations" />
  <AdminLayout title="Laboratory Reservations">
    <div class="mb-4 flex items-center justify-between">
      <p class="text-sm text-slate-500">CID-05 · SY {{ schoolYear?.name || '—' }}</p>
      <button @click="showCreate = true" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"><PlusIcon class="h-4 w-4" /> File Reservation</button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <tr><th class="px-4 py-3 text-left">Control No</th><th class="px-4 py-3 text-left">Lab / Subject</th><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Requester</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3"></th></tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="r in reservations" :key="r.id" class="hover:bg-slate-50/50">
            <td class="px-4 py-3 font-mono text-xs">{{ r.control_no }}</td>
            <td class="px-4 py-3"><div class="font-medium text-slate-800">{{ r.room?.name || '—' }}</div><div class="text-xs text-slate-400">{{ r.subject }} · {{ r.grade_level_section }}</div></td>
            <td class="px-4 py-3 text-xs">{{ r.date_start }}<span v-if="r.date_end"> – {{ r.date_end }}</span><br>{{ r.time_start }}–{{ r.time_end }}</td>
            <td class="px-4 py-3">{{ r.requester?.name || r.requester_name }}</td>
            <td class="px-4 py-3"><span :class="['rounded-full px-2 py-0.5 text-xs font-medium capitalize', statusTone[r.status]]">{{ r.status }}</span></td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <button v-if="r.status === 'pending'" @click="openSign(r, 'endorse')" class="mr-1 rounded px-2 py-1 text-xs font-medium text-sky-600 hover:bg-sky-50">Endorse</button>
              <button v-if="canManage && ['pending','endorsed'].includes(r.status)" @click="openSign(r, 'approve')" class="mr-1 rounded px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50">Approve</button>
              <button v-if="canManage && ['pending','endorsed'].includes(r.status)" @click="declineFor = r" class="mr-1 rounded px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50">Decline</button>
              <button v-if="canManage && r.status === 'approved'" @click="act(r, 'complete')" class="mr-1 rounded px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50">Complete</button>
              <a :href="route('science-lab.reservations.pdf', r.id)" target="_blank" class="inline-block rounded p-1.5 text-slate-400 hover:bg-slate-100"><DocumentArrowDownIcon class="h-4 w-4" /></a>
            </td>
          </tr>
          <tr v-if="!reservations.length"><td colspan="6" class="px-4 py-10 text-center text-slate-400">No reservations.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Create -->
    <AppModal :show="showCreate" title="File Laboratory Reservation" size="2xl" @close="showCreate = false">
      <div class="grid grid-cols-2 gap-4">
        <label class="text-sm">Grade Level & Section<input v-model="form.grade_level_section" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">No. of Students<input type="number" v-model="form.number_of_students" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Subject<input v-model="form.subject" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Teacher In-Charge<input v-model="form.teacher_in_charge" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Preferred Lab<select v-model="form.room_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="">—</option><option v-for="r in labRooms" :key="r.id" :value="r.id">{{ r.name }}</option></select></label>
        <label class="text-sm">Requester Type<select v-model="form.requester_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><option value="teacher">Teacher</option><option value="student">Student</option></select></label>
        <label class="text-sm">Date Start *<input type="date" v-model="form.date_start" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /><span v-if="form.errors.date_start" class="text-xs text-rose-600">{{ form.errors.date_start }}</span></label>
        <label class="text-sm">Date End<input type="date" v-model="form.date_end" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Time Start<input type="time" v-model="form.time_start" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="text-sm">Time End<input type="time" v-model="form.time_end" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" /></label>
        <label class="col-span-2 text-sm">Student group (one name per line)<textarea v-model="groupText" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea></label>
        <label class="col-span-2 text-sm">Remarks<textarea v-model="form.remarks" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea></label>
      </div>
      <template #footer>
        <button @click="showCreate = false" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button>
        <button @click="submitCreate" :disabled="form.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">File</button>
      </template>
    </AppModal>

    <!-- Sign (PIN) -->
    <AppModal :show="!!signFor" :title="signFor?.action === 'endorse' ? 'Endorse Reservation' : 'Approve Reservation'" size="sm" @close="signFor = null">
      <p v-if="!hasPin" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-700">You have not set a signing PIN. Set it in your profile first.</p>
      <template v-else>
        <p class="mb-3 text-sm text-slate-500">Enter your signing PIN to digitally sign {{ signFor?.r.control_no }}.</p>
        <input v-model="signForm.pin" type="password" inputmode="numeric" placeholder="Signing PIN" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-center tracking-widest" />
        <p v-if="signForm.errors.pin" class="mt-1 text-xs text-rose-600">{{ signForm.errors.pin }}</p>
      </template>
      <template #footer>
        <button @click="signFor = null" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button>
        <button v-if="hasPin" @click="submitSign" :disabled="signForm.processing" class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"><CheckBadgeIcon class="h-4 w-4" /> Sign</button>
      </template>
    </AppModal>

    <!-- Decline -->
    <AppModal :show="!!declineFor" title="Decline Reservation" size="sm" @close="declineFor = null">
      <label class="text-sm">Reason *<textarea v-model="declineForm.decline_reason" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea></label>
      <template #footer>
        <button @click="declineFor = null" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancel</button>
        <button @click="submitDecline" class="inline-flex items-center gap-1 rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700"><XCircleIcon class="h-4 w-4" /> Decline</button>
      </template>
    </AppModal>
  </AdminLayout>
</template>
