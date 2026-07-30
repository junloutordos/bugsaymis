<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'

const props = defineProps({
  sections: { type: Array, required: true },
  sectionId: { type: [Number, String, null], default: null },
  pending: { type: Array, default: () => [] },
  issued: { type: Array, default: () => [] },
})

const sectionModel = ref(props.sectionId ? String(props.sectionId) : '')

function goto() {
  router.get(route('homeroom-attendance.admission-slips.index'), { section: sectionModel.value }, { preserveState: false })
}

const showModal = ref(false)
const submitting = ref(false)
const active = ref(null)
const excusedStatus = ref('unexcused')
const reason = ref('')
const documentBase64 = ref(null)
const documentName = ref('')

function openSlipModal(record) {
  active.value = record
  excusedStatus.value = 'unexcused'
  reason.value = ''
  documentBase64.value = null
  documentName.value = ''
  showModal.value = true
}

function onFileChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  documentName.value = file.name
  const reader = new FileReader()
  reader.onload = () => { documentBase64.value = reader.result }
  reader.readAsDataURL(file)
}

async function issueSlip() {
  submitting.value = true
  try {
    await axios.post(route('homeroom-attendance.admission-slips.store'), {
      student_id: active.value.student_id,
      section_id: Number(sectionModel.value),
      infraction_date: active.value.date,
      infraction_type: active.value.infraction_type,
      excused_status: excusedStatus.value,
      reason: reason.value || null,
      document_base64: documentBase64.value,
    })
    showModal.value = false
    router.reload()
    await Swal.fire({ icon: 'success', title: 'Admission slip issued', timer: 1000, showConfirmButton: false })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Failed to issue slip.', 'error')
  } finally {
    submitting.value = false
  }
}

function formatDate(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<template>
  <Head title="Class Admission Slips" />
  <AdminLayout title="Class Admission Slips">
    <AppPageHeader title="Class Admission Slips" subtitle="Determine Excused/Unexcused for students returning from an absence, tardy, or cut class" />

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-4 mb-4">
      <AppSelect v-model="sectionModel" label="Section" @update:modelValue="goto">
        <option v-for="s in sections" :key="s.id" :value="String(s.id)">Grade {{ s.levelid }} - {{ s.sectionname }}</option>
      </AppSelect>
    </div>

    <template v-if="sectionId">
      <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-2">Awaiting Admission Slip</h2>
      <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden mb-6">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Infraction</th>
              <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="!pending.length">
              <td colspan="4" class="px-4 py-8 text-center text-slate-400 text-sm">No infractions awaiting a slip.</td>
            </tr>
            <tr v-for="record in pending" :key="`${record.type}_${record.reference_id}`">
              <td class="px-4 py-2 text-slate-700">{{ record.student_name }}</td>
              <td class="px-4 py-2 text-slate-500">{{ formatDate(record.date) }}</td>
              <td class="px-4 py-2 text-slate-500">
                <span :class="record.infraction_type === 'cutting_suspected' ? 'text-slate-400' : ''">
                  {{ record.infraction_type_label }}
                </span>
                <span v-if="record.infraction_type === 'cut_class'" class="ml-1 text-[10px] text-orange-500 normal-case">(marked by subject teacher)</span>
                <span v-else-if="record.infraction_type === 'cutting_suspected'" class="ml-1 text-[10px] text-slate-400 normal-case">(detected — subject absent, homeroom present)</span>
              </td>
              <td class="px-4 py-2 text-right">
                <AppButton size="sm" @click="openSlipModal(record)">Issue Slip</AppButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-2">Recently Issued</h2>
      <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Slip</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="!issued.length">
              <td colspan="4" class="px-4 py-8 text-center text-slate-400 text-sm">No slips issued yet.</td>
            </tr>
            <tr v-for="slip in issued" :key="slip.id">
              <td class="px-4 py-2 text-slate-700">{{ slip.student_name }}</td>
              <td class="px-4 py-2 text-slate-500">{{ formatDate(slip.infraction_date) }}</td>
              <td class="px-4 py-2">
                <span :class="slip.excused_status === 'excused' ? 'text-emerald-600' : 'text-red-600'" class="font-medium capitalize">
                  {{ slip.excused_status }}
                </span>
              </td>
              <td class="px-4 py-2 text-right">
                <a :href="slip.print_url" target="_blank" class="text-indigo-600 hover:underline text-xs">Print</a>
                <a v-if="slip.document_url" :href="slip.document_url" target="_blank" class="ml-3 text-indigo-600 hover:underline text-xs">Document</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <AppModal :show="showModal" @close="showModal = false" title="Issue Class Admission Slip">
      <div v-if="active" class="space-y-3">
        <p class="text-sm text-slate-600">
          <span class="font-medium">{{ active.student_name }}</span> — {{ formatDate(active.date) }} ({{ active.infraction_type_label }})
        </p>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Determination</label>
          <div class="flex gap-4 text-sm">
            <label class="flex items-center gap-1.5"><input type="radio" value="excused" v-model="excusedStatus" /> Excused</label>
            <label class="flex items-center gap-1.5"><input type="radio" value="unexcused" v-model="excusedStatus" /> Unexcused</label>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Reason</label>
          <input type="text" v-model="reason" placeholder="e.g. EA - DSPC Training & Competition"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Supporting Document (optional)</label>
          <input type="file" accept="image/*,application/pdf" @change="onFileChange"
            class="text-sm text-slate-600" />
          <p v-if="documentName" class="text-xs text-slate-400 mt-1">{{ documentName }}</p>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
        <AppButton :loading="submitting" @click="issueSlip">Issue Slip</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
