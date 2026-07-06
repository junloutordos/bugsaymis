<template>
  <Head title="School Years & Terms" />
  <AdminLayout title="School Years & Terms">
    <div class="space-y-5">

      <AppPageHeader title="School Years & Terms" subtitle="Manage school years and academic term periods">
        <template #actions>
          <AppButton @click="openSyForm()">
            <PlusIcon class="h-4 w-4" />
            New School Year
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.errors.error }}
      </div>

      <!-- Empty -->
      <AppCard v-if="schoolYears.length === 0">
        <EmptyState title="No school years yet" :icon="AcademicCapIcon" />
      </AppCard>

      <!-- School years list -->
      <AppCard v-for="sy in schoolYears" :key="sy.id" :padded="false">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 bg-slate-50/50">
          <div class="flex items-center gap-3">
            <p class="font-semibold text-slate-800">S.Y. {{ sy.name }}</p>
            <AppBadge v-if="sy.is_current" color="green">Current</AppBadge>
            <AppBadge :color="sy.status === 'active' ? 'blue' : 'slate'">{{ sy.status }}</AppBadge>
            <AppBadge :color="workflowClass(sy.workflow_status)">{{ sy.workflow_status ?? 'draft' }}</AppBadge>
          </div>
          <div class="flex items-center gap-2">
            <AppButton v-if="sy.workflow_status !== 'archived'" size="sm" variant="secondary" @click="advanceSy(sy)">
              Advance →
            </AppButton>
            <AppButton v-if="sy.workflow_status !== 'archived'" size="sm" variant="secondary" @click="archiveSy(sy)">
              Archive
            </AppButton>
            <AppButton size="sm" variant="ghost" @click="openTermForm(sy)">
              <PlusIcon class="h-3.5 w-3.5" /> Add Term
            </AppButton>
            <AppIconButton label="Edit school year" @click="openSyForm(sy)"><PencilIcon class="h-4 w-4" /></AppIconButton>
            <AppIconButton label="Delete school year" variant="danger" @click="deleteSy(sy)"><TrashIcon class="h-4 w-4" /></AppIconButton>
          </div>
        </div>
        <div class="px-5 py-2 text-xs text-slate-400">
          {{ fmtDate(sy.start_date) }} – {{ fmtDate(sy.end_date) }}
        </div>

        <!-- Terms -->
        <div v-if="sy.terms.length > 0" class="divide-y divide-slate-50">
          <div v-for="term in sy.terms" :key="term.id"
            class="flex items-center justify-between px-5 py-3 hover:bg-slate-50/50">
            <div class="flex items-center gap-3">
              <CalendarIcon class="h-4 w-4 text-slate-300 shrink-0" />
              <div>
                <p class="text-sm font-medium text-slate-700">{{ term.name }}</p>
                <p class="text-xs text-slate-400">{{ fmtDate(term.start_date) }} – {{ fmtDate(term.end_date) }}</p>
              </div>
              <AppBadge v-if="term.is_current" color="indigo">Current</AppBadge>
            </div>
            <div class="flex items-center gap-1">
              <AppIconButton label="Edit term" @click="openTermForm(sy, term)"><PencilIcon class="h-4 w-4" /></AppIconButton>
              <AppIconButton label="Delete term" variant="danger" @click="deleteTerm(term)"><TrashIcon class="h-4 w-4" /></AppIconButton>
            </div>
          </div>
        </div>
        <div v-else class="px-5 py-4 text-xs text-slate-400 italic">No terms yet.</div>
      </AppCard>

    </div>

    <!-- School Year Modal -->
    <AppModal :show="syModal" :title="`${syForm.id ? 'Edit' : 'New'} School Year`" @close="syModal = false">
      <div class="space-y-3">
        <AppInput v-model="syForm.name" label="Name (e.g. 2025-2026)" type="text" :error="syForm.errors.name" />
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="syForm.start_date" label="Start Date" type="date" :error="syForm.errors.start_date" />
          <AppInput v-model="syForm.end_date" label="End Date" type="date" :error="syForm.errors.end_date" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <AppSelect v-model="syForm.status" label="Status" :show-blank="false">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="archived">Archived</option>
          </AppSelect>
          <div class="flex items-center gap-2 pt-5">
            <input v-model="syForm.is_current" type="checkbox" id="sy-current" class="rounded text-indigo-600" />
            <label for="sy-current" class="text-sm text-slate-600">Set as current</label>
          </div>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="syModal = false">Cancel</AppButton>
        <AppButton :loading="syForm.processing" @click="saveSy">{{ syForm.id ? 'Update' : 'Create' }}</AppButton>
      </template>
    </AppModal>

    <!-- Term Modal -->
    <AppModal :show="termModal" :title="`${termForm.id ? 'Edit' : 'Add'} Academic Term`" @close="termModal = false">
      <div class="space-y-3">
        <AppInput v-model="termForm.name" label="Term Name" type="text" placeholder="e.g. 1st Semester, S.Y. 2025-2026" :error="termForm.errors.name" />
        <AppSelect v-model="termForm.term_type" label="Term Type" :show-blank="false">
          <option value="1st_semester">1st Semester</option>
          <option value="2nd_semester">2nd Semester</option>
          <option value="full_term">Full Term</option>
          <option value="summer">Summer</option>
          <option value="trimester">Trimester</option>
          <option value="quarter">Quarter</option>
        </AppSelect>
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="termForm.start_date" label="Start Date" type="date" :error="termForm.errors.start_date" />
          <AppInput v-model="termForm.end_date" label="End Date" type="date" :error="termForm.errors.end_date" />
        </div>
        <div class="flex items-center gap-2">
          <input v-model="termForm.is_current" type="checkbox" id="term-current" class="rounded text-indigo-600" />
          <label for="term-current" class="text-sm text-slate-600">Set as current term</label>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="termModal = false">Cancel</AppButton>
        <AppButton :loading="termForm.processing" @click="saveTerm">{{ termForm.id ? 'Update' : 'Add Term' }}</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmAction, confirmDelete } from '@/Composables/useConfirm.js'
import {
  AcademicCapIcon, CalendarIcon, CheckCircleIcon, ExclamationCircleIcon,
  PencilIcon, PlusIcon, TrashIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  schoolYears: { type: Array, default: () => [] },
})

function fmtDate(d) {
  if (! d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

// ── School Year CRUD ─────────────────────────────────────────────────────────
const syModal = ref(false)
const syForm  = useForm({ id: null, name: '', start_date: '', end_date: '', is_current: false, status: 'active' })

function openSyForm(sy = null) {
  if (sy) {
    syForm.id = sy.id; syForm.name = sy.name; syForm.start_date = sy.start_date
    syForm.end_date = sy.end_date; syForm.is_current = sy.is_current; syForm.status = sy.status
  } else {
    syForm.reset(); syForm.id = null
  }
  syModal.value = true
}

function saveSy() {
  if (syForm.id) {
    syForm.put(route('faculty-loading.school-years.update', syForm.id), {
      onSuccess: () => { syModal.value = false },
    })
  } else {
    syForm.post(route('faculty-loading.school-years.store'), {
      onSuccess: () => { syModal.value = false },
    })
  }
}

async function deleteSy(sy) {
  if (! await confirmDelete(`Delete S.Y. ${sy.name}? This cannot be undone.`)) return
  useForm({}).delete(route('faculty-loading.school-years.destroy', sy.id))
}

async function advanceSy(sy) {
  const next = { draft: 'proposed', proposed: 'approved', approved: 'published', published: 'archived' }[sy.workflow_status]
  if (! next) return
  if (! await confirmAction({ title: 'Advance school year?', text: `Advance S.Y. ${sy.name} from '${sy.workflow_status}' to '${next}'?`, confirmText: 'Advance' })) return
  useForm({}).post(route('faculty-loading.school-years.advance', sy.id))
}

async function archiveSy(sy) {
  if (! await confirmAction({ title: 'Archive school year?', text: `Archive S.Y. ${sy.name}? This cannot be undone if there are no active loads.`, confirmText: 'Archive' })) return
  useForm({}).post(route('faculty-loading.school-years.archive', sy.id))
}

const WORKFLOW_COLORS = {
  draft:     'slate',
  proposed:  'amber',
  approved:  'blue',
  published: 'green',
  archived:  'slate',
}
const workflowClass = (s) => WORKFLOW_COLORS[s] ?? 'slate'

// ── Term CRUD ────────────────────────────────────────────────────────────────
const termModal    = ref(false)
const activeSyId   = ref(null)
const termForm     = useForm({ id: null, name: '', term_type: '1st_semester', start_date: '', end_date: '', is_current: false })

function openTermForm(sy, term = null) {
  activeSyId.value = sy.id
  if (term) {
    termForm.id = term.id; termForm.name = term.name; termForm.term_type = term.term_type
    termForm.start_date = term.start_date; termForm.end_date = term.end_date; termForm.is_current = term.is_current
  } else {
    termForm.reset(); termForm.id = null; termForm.term_type = '1st_semester'
  }
  termModal.value = true
}

function saveTerm() {
  if (termForm.id) {
    termForm.put(route('faculty-loading.school-years.terms.update', termForm.id), {
      onSuccess: () => { termModal.value = false },
    })
  } else {
    termForm.post(route('faculty-loading.school-years.terms.store', activeSyId.value), {
      onSuccess: () => { termModal.value = false },
    })
  }
}

async function deleteTerm(term) {
  if (! await confirmDelete(`Delete term "${term.name}"?`)) return
  useForm({}).delete(route('faculty-loading.school-years.terms.destroy', term.id))
}
</script>
