<template>
  <Head title="Faculty Vacancies" />
  <AdminLayout title="Faculty Vacancies">
    <div class="space-y-5">

      <AppPageHeader title="Faculty Vacancies" subtitle="Track open faculty slots per academic unit and term">
        <template #actions>
          <AppButton @click="openForm()">
            <PlusIcon class="h-4 w-4" /> Open Vacancy
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
        <div class="w-40">
          <AppSelect v-model="filterYear" :show-blank="false" @change="applyFilters">
            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">S.Y. {{ sy.name }}</option>
          </AppSelect>
        </div>
        <div class="w-44">
          <AppSelect v-model="filterUnit" placeholder="All Units" @change="applyFilters">
            <option v-for="u in academicUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
          </AppSelect>
        </div>
        <div class="w-40">
          <AppSelect v-model="filterStatus" placeholder="All Statuses" @change="applyFilters">
            <option value="open">Open</option>
            <option value="filled">Filled</option>
            <option value="cancelled">Cancelled</option>
          </AppSelect>
        </div>
      </AppFilterBar>

      <!-- Summary chips -->
      <div class="flex flex-wrap gap-2">
        <template v-for="u in academicUnits" :key="u.id">
          <AppBadge v-if="openCounts[u.id]" color="amber">{{ u.code }}: {{ openCounts[u.id] }} open</AppBadge>
        </template>
      </div>

      <!-- Table -->
      <AppTable :is-empty="!vacancies.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Term</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Position</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Subject</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Filled By</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="v in vacancies" :key="v.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 font-medium text-slate-800">{{ v.academic_unit?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-500">{{ v.academic_term?.name ?? 'Full Year' }}</td>
          <td class="px-4 py-3 text-slate-600">{{ v.sst_position?.code ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-600">{{ v.subject?.name ?? '—' }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(v.status)">{{ v.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-500 text-xs">
            {{ v.filled_by?.name ?? '—' }}
            <span v-if="v.filled_at" class="text-slate-400"> · {{ fmtDate(v.filled_at) }}</span>
          </td>
          <td class="px-4 py-3 text-right">
            <div class="flex items-center justify-end gap-1">
              <AppButton v-if="v.status === 'open'" size="sm" variant="success" @click="openFillModal(v)">Fill</AppButton>
              <AppButton v-if="v.status === 'open'" size="sm" variant="warning" @click="cancelVacancy(v)">Cancel</AppButton>
              <AppIconButton v-if="v.status !== 'filled'" label="Delete" variant="danger" @click="deleteVacancy(v)"><TrashIcon class="h-4 w-4" /></AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="v in vacancies" :key="v.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ v.academic_unit?.name ?? '—' }}</p>
                <p class="text-xs text-slate-400">{{ v.academic_term?.name ?? 'Full Year' }}</p>
              </div>
              <AppBadge :color="statusColor(v.status)">{{ v.status }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">
              {{ v.sst_position?.code ?? '—' }} &middot; {{ v.subject?.name ?? '—' }}
            </p>
            <p class="text-xs text-slate-500">
              Filled by {{ v.filled_by?.name ?? '—' }}
              <span v-if="v.filled_at" class="text-slate-400"> · {{ fmtDate(v.filled_at) }}</span>
            </p>
            <div class="flex items-center gap-1 pt-1">
              <AppButton v-if="v.status === 'open'" size="sm" variant="success" @click="openFillModal(v)">Fill</AppButton>
              <AppButton v-if="v.status === 'open'" size="sm" variant="warning" @click="cancelVacancy(v)">Cancel</AppButton>
              <AppIconButton v-if="v.status !== 'filled'" label="Delete" variant="danger" @click="deleteVacancy(v)"><TrashIcon class="h-4 w-4" /></AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No vacancies found" />
        </template>
      </AppTable>
    </div>

    <!-- Open Vacancy Modal -->
    <AppModal :show="formModal" title="Open Vacancy" size="sm" @close="formModal = false">
      <div class="space-y-3">
        <AppSelect v-model="vacForm.academic_unit_id" label="Academic Unit" required :show-blank="false">
          <option v-for="u in academicUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
        </AppSelect>
        <AppTextarea v-model="vacForm.notes" label="Notes" rows="2" placeholder="Context or requirements for this vacancy" />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="formModal = false">Cancel</AppButton>
        <AppButton :loading="vacForm.processing" @click="saveVacancy">Open Vacancy</AppButton>
      </template>
    </AppModal>

    <!-- Fill Modal -->
    <AppModal :show="fillModal" title="Fill Vacancy" subtitle="Select the faculty member who will fill this slot." size="sm" @close="fillModal = false">
      <div class="space-y-1">
        <AppInput v-model="fillSearch" label="Faculty Member" required placeholder="Type to search faculty..." :error="fillForm.errors.user_id" />
        <p class="text-xs text-slate-400 mt-1">Selected: {{ fillSelectedName || '—' }}</p>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="fillModal = false">Cancel</AppButton>
        <AppButton variant="success" :disabled="!fillForm.user_id" :loading="fillForm.processing" @click="submitFill">Confirm Fill</AppButton>
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
import AppSelect from '@/Components/AppSelect.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmAction, confirmDelete } from '@/Composables/useConfirm.js'
import { CheckCircleIcon, ExclamationCircleIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  vacancies:      { type: Array,  default: () => [] },
  schoolYears:    { type: Array,  default: () => [] },
  academicUnits:  { type: Array,  default: () => [] },
  openCounts:     { type: Object, default: () => ({}) },
  selectedYearId: { type: Number, default: null },
  filters:        { type: Object, default: () => ({}) },
})

const filterYear   = ref(props.selectedYearId)
const filterUnit   = ref(props.filters.academic_unit_id ?? '')
const filterStatus = ref(props.filters.status ?? '')

function applyFilters() {
  router.get(route('faculty-loading.vacancies.index'), {
    school_year_id:   filterYear.value,
    academic_unit_id: filterUnit.value || undefined,
    status:           filterStatus.value || undefined,
  }, { preserveScroll: true, preserveState: true })
}

// ── Open Vacancy ──────────────────────────────────────────────────────────────
const formModal = ref(false)
const vacForm   = useForm({ school_year_id: props.selectedYearId, academic_unit_id: null, notes: '' })

function openForm() { vacForm.reset(); vacForm.school_year_id = props.selectedYearId; formModal.value = true }
function saveVacancy() {
  vacForm.post(route('faculty-loading.vacancies.store'), { onSuccess: () => { formModal.value = false } })
}

// ── Fill ──────────────────────────────────────────────────────────────────────
const fillModal        = ref(false)
const fillTargetId     = ref(null)
const fillSearch       = ref('')
const fillForm         = useForm({ user_id: null })
const fillSelectedName = ref('')

function openFillModal(v) {
  fillTargetId.value = v.id; fillForm.reset(); fillSearch.value = ''; fillSelectedName.value = ''
  fillModal.value = true
}
function submitFill() {
  fillForm.post(route('faculty-loading.vacancies.fill', fillTargetId.value), { onSuccess: () => { fillModal.value = false } })
}

// ── Cancel / Delete ───────────────────────────────────────────────────────────
async function cancelVacancy(v) {
  const confirmed = await confirmAction({ title: 'Cancel vacancy?', text: 'Cancel this vacancy?', confirmText: 'Cancel Vacancy' })
  if (!confirmed) return
  useForm({}).post(route('faculty-loading.vacancies.cancel', v.id))
}
async function deleteVacancy(v) {
  const confirmed = await confirmDelete('Delete this vacancy?')
  if (!confirmed) return
  useForm({}).delete(route('faculty-loading.vacancies.destroy', v.id))
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function statusColor(s) {
  return { open: 'amber', filled: 'green', cancelled: 'slate' }[s] ?? 'slate'
}
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
</script>
