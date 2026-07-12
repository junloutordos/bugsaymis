<template>
  <Head title="Training Records" />
  <AdminLayout title="Training Records">
    <div class="space-y-5">

      <AppPageHeader title="Training Records" subtitle="L&D interventions attended by faculty and staff">
        <template #actions>
          <AppButton @click="openForm()">
            <PlusIcon class="h-4 w-4" /> Add Record
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.errors.error }}
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <div class="w-48">
          <AppSelect v-model="filterYear" :show-blank="false" @change="applyFilters">
            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">S.Y. {{ sy.name }}</option>
          </AppSelect>
        </div>
        <div class="w-44">
          <AppSelect v-model="filterStatus" placeholder="All Statuses" @change="applyFilters">
            <option value="pending">Pending</option>
            <option value="verified">Verified</option>
            <option value="rejected">Rejected</option>
          </AppSelect>
        </div>
        <div class="w-44">
          <AppSelect v-model="filterLevel" placeholder="All Levels" @change="applyFilters">
            <option value="international">International</option>
            <option value="national">National</option>
            <option value="regional">Regional</option>
            <option value="division">Division</option>
            <option value="school">School</option>
          </AppSelect>
        </div>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!records.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Faculty</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Training</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Dates</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Level</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Hours</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="r in records" :key="r.id" class="hover:bg-slate-50/50">
          <td class="px-4 py-3 font-medium text-slate-800">{{ r.user?.name ?? '—' }}</td>
          <td class="px-4 py-3">
            <p class="font-medium text-slate-800">{{ r.training_title }}</p>
            <p class="text-xs text-slate-400 mt-0.5">{{ r.organizer ?? '—' }}</p>
          </td>
          <td class="px-4 py-3 text-xs text-slate-500">
            {{ fmtDate(r.date_from) }}<br>{{ fmtDate(r.date_to) }}
          </td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="levelColor(r.level)">{{ r.level }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center text-slate-700 font-mono text-xs">{{ r.hours_earned }}h</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusColor(r.status)">{{ r.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-right">
            <div class="flex items-center justify-end gap-1">
              <AppButton v-if="r.status === 'pending'" size="sm" variant="success" @click="verifyRecord(r)">Verify</AppButton>
              <AppButton v-if="r.status === 'pending'" size="sm" variant="danger" @click="openRejectModal(r)">Reject</AppButton>
              <AppIconButton v-if="r.status !== 'verified'" label="Edit" @click="openForm(r)"><PencilIcon class="h-4 w-4" /></AppIconButton>
              <AppIconButton v-if="r.status !== 'verified'" label="Delete" variant="danger" @click="deleteRecord(r)"><TrashIcon class="h-4 w-4" /></AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="r in records" :key="r.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ r.training_title }}</p>
                <p class="text-xs text-slate-400">{{ r.user?.name ?? '—' }} &middot; {{ r.organizer ?? '—' }}</p>
              </div>
              <AppBadge :color="statusColor(r.status)">{{ r.status }}</AppBadge>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
              <AppBadge :color="levelColor(r.level)">{{ r.level }}</AppBadge>
              <span>{{ fmtDate(r.date_from) }} – {{ fmtDate(r.date_to) }}</span>
              <span>{{ r.hours_earned }}h</span>
            </div>
            <div class="flex items-center gap-1 pt-1">
              <AppButton v-if="r.status === 'pending'" size="sm" variant="success" @click="verifyRecord(r)">Verify</AppButton>
              <AppButton v-if="r.status === 'pending'" size="sm" variant="danger" @click="openRejectModal(r)">Reject</AppButton>
              <AppIconButton v-if="r.status !== 'verified'" label="Edit" @click="openForm(r)"><PencilIcon class="h-4 w-4" /></AppIconButton>
              <AppIconButton v-if="r.status !== 'verified'" label="Delete" variant="danger" @click="deleteRecord(r)"><TrashIcon class="h-4 w-4" /></AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No training records found" />
        </template>
      </AppTable>
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :show="formModal" :title="`${form.id ? 'Edit' : 'Add'} Training Record`" size="lg" @close="formModal = false">
      <div class="space-y-3">
        <AppInput v-model="form.training_title" label="Training Title" required :error="form.errors.training_title" />
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="form.organizer" label="Organizer" />
          <AppInput v-model="form.venue" label="Venue" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="form.date_from" label="Date From" required type="date" />
          <AppInput v-model="form.date_to" label="Date To" required type="date" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <AppSelect v-model="form.level" label="Level" required :show-blank="false">
            <option value="international">International</option>
            <option value="national">National</option>
            <option value="regional">Regional</option>
            <option value="division">Division</option>
            <option value="school">School</option>
          </AppSelect>
          <AppInput v-model.number="form.hours_earned" label="Hours Earned" required type="number" min="0" step="0.5" />
        </div>
        <AppTextarea v-model="form.remarks" label="Remarks" :rows="2" />
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="formModal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="save">{{ form.id ? 'Update' : 'Save' }}</AppButton>
      </template>
    </AppModal>

    <!-- Reject Modal -->
    <AppModal :show="rejectModal" title="Reject Training Record" size="sm" @close="rejectModal = false">
      <AppTextarea v-model="rejectForm.remarks" label="Reason" required :rows="3" placeholder="Explain why this record is being rejected" :error="rejectForm.errors.remarks" />

      <template #footer>
        <AppButton variant="secondary" @click="rejectModal = false">Cancel</AppButton>
        <AppButton variant="danger" :loading="rejectForm.processing" @click="submitReject">Reject</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmAction, confirmDelete } from '@/Composables/useConfirm.js'
import { CheckCircleIcon, ExclamationCircleIcon, PencilIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  records:        { type: Array,  default: () => [] },
  schoolYears:    { type: Array,  default: () => [] },
  selectedYearId: { type: Number, default: null },
  filters:        { type: Object, default: () => ({}) },
})

const filterYear   = ref(props.selectedYearId)
const filterStatus = ref(props.filters.status ?? '')
const filterLevel  = ref(props.filters.level  ?? '')

function applyFilters() {
  router.get(route('faculty-loading.training-records.index'), {
    school_year_id: filterYear.value,
    status:  filterStatus.value  || undefined,
    level:   filterLevel.value   || undefined,
  }, { preserveScroll: true, preserveState: true })
}

// ── Create / Edit ─────────────────────────────────────────────────────────────
const formModal = ref(false)
const form = useForm({ id: null, user_id: null, training_title: '', organizer: '', venue: '', date_from: '', date_to: '', level: 'school', hours_earned: 0, load_units: null, remarks: '' })

function openForm(r = null) {
  if (r) {
    Object.assign(form, { id: r.id, user_id: r.user?.id, training_title: r.training_title, organizer: r.organizer ?? '', venue: r.venue ?? '', date_from: r.date_from, date_to: r.date_to, level: r.level, hours_earned: r.hours_earned, load_units: r.load_units, remarks: r.remarks ?? '' })
  } else {
    form.reset(); form.id = null; form.level = 'school'; form.hours_earned = 0
  }
  formModal.value = true
}

function save() {
  if (form.id) {
    form.put(route('faculty-loading.training-records.update', form.id), { onSuccess: () => { formModal.value = false } })
  } else {
    form.post(route('faculty-loading.training-records.store'), { onSuccess: () => { formModal.value = false } })
  }
}

// ── Verify / Reject ───────────────────────────────────────────────────────────
async function verifyRecord(r) {
  const confirmed = await confirmAction({ title: 'Verify training record?', text: `Verify training record "${r.training_title}"?`, confirmText: 'Verify' })
  if (!confirmed) return
  useForm({}).post(route('faculty-loading.training-records.verify', r.id))
}

const rejectModal  = ref(false)
const rejectTarget = ref(null)
const rejectForm   = useForm({ remarks: '' })

function openRejectModal(r) { rejectTarget.value = r; rejectForm.reset(); rejectModal.value = true }
function submitReject() {
  rejectForm.post(route('faculty-loading.training-records.reject', rejectTarget.value.id), { onSuccess: () => { rejectModal.value = false } })
}

// ── Delete ────────────────────────────────────────────────────────────────────
async function deleteRecord(r) {
  const confirmed = await confirmDelete(`Delete training record "${r.training_title}"?`)
  if (!confirmed) return
  useForm({}).delete(route('faculty-loading.training-records.destroy', r.id))
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const STATUS_COLORS = { pending: 'amber', verified: 'green', rejected: 'red' }
const LEVEL_COLORS  = { international: 'purple', national: 'blue', regional: 'indigo', division: 'orange', school: 'slate' }
const statusColor = (s) => STATUS_COLORS[s] ?? 'slate'
const levelColor  = (l) => LEVEL_COLORS[l]  ?? 'slate'
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
</script>
