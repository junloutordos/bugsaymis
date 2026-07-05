<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import {
  PlusIcon,
  PencilSquareIcon,
  TrashIcon,
  LockOpenIcon,
  LockClosedIcon,
  ArrowPathIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  periods: Array,
  schoolYears: Array,
  selectedSchoolYear: Number,
})

const schoolYearId = ref(props.selectedSchoolYear)
watch(schoolYearId, (val) => {
  router.get(route('registrar.enrollment-periods.index'), { school_year_id: val }, { preserveState: true })
})

// ── Create / edit modal ───────────────────────────────────────────────────────
const showModal     = ref(false)
const editingPeriod = ref(null)

const form = useForm({
  school_year_id: props.selectedSchoolYear,
  grade_level:    null,
  label:          '',
  open_at:        '',
  close_at:       '',
})

function openCreate() {
  editingPeriod.value   = null
  form.reset()
  form.school_year_id   = schoolYearId.value
  showModal.value       = true
}

function openEdit(period) {
  editingPeriod.value   = period
  form.school_year_id   = period.school_year_id
  form.grade_level      = period.grade_level
  form.label            = period.label
  form.open_at          = period.open_at?.slice(0, 16) ?? ''
  form.close_at         = period.close_at?.slice(0, 16) ?? ''
  showModal.value       = true
}

function submitForm() {
  if (editingPeriod.value) {
    form.put(route('registrar.enrollment-periods.update', editingPeriod.value.id), {
      onSuccess: () => { showModal.value = false },
    })
  } else {
    form.post(route('registrar.enrollment-periods.store'), {
      onSuccess: () => { showModal.value = false },
    })
  }
}

// ── Delete ────────────────────────────────────────────────────────────────────
const deleteForm = useForm({})

async function deletePeriod(period) {
  if (! await confirmDelete(`Delete "${period.label}"?`)) return
  deleteForm.delete(route('registrar.enrollment-periods.destroy', period.id))
}

// ── Toggle open/close ─────────────────────────────────────────────────────────
const toggleForm = useForm({ action: '' })

function toggle(period, action) {
  toggleForm.action = action
  toggleForm.post(route('registrar.enrollment-periods.toggle', period.id))
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const GRADE_LABELS = {
  7:'Grade 7',8:'Grade 8',9:'Grade 9',10:'Grade 10',11:'Grade 11',12:'Grade 12'
}

function formatDt(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-PH', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function statusColor(period) {
  return period.is_open ? 'green' : 'slate'
}
</script>

<template>
  <Head title="Enrollment Periods" />
  <AdminLayout title="Enrollment Periods">
    <div class="space-y-5">

      <AppPageHeader title="Enrollment Periods">
        <template #actions>
          <select
            v-model="schoolYearId"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
              {{ sy.name }}{{ sy.is_current ? ' (Current)' : '' }}
            </option>
          </select>

          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            New Period
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Back link -->
      <div>
        <Link :href="route('registrar.enrollment.index')" class="text-sm text-indigo-600 hover:underline">
          ← Back to Enrollment
        </Link>
      </div>

      <!-- Table -->
      <AppTable :is-empty="!periods.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Label</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Grade</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Opens</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Closes</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
          </tr>
        </template>

        <tr v-for="p in periods" :key="p.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 font-medium text-slate-800">{{ p.label }}</td>
          <td class="px-4 py-3 text-slate-500">{{ p.grade_level ? GRADE_LABELS[p.grade_level] : 'All Grades' }}</td>
          <td class="px-4 py-3 text-slate-500">{{ formatDt(p.open_at) }}</td>
          <td class="px-4 py-3 text-slate-500">{{ formatDt(p.close_at) }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(p)">{{ p.is_open ? 'Open' : 'Closed' }}</AppBadge>
            <span v-if="p.is_manually_open" class="text-xs text-green-600 ml-1">(forced open)</span>
            <span v-else-if="p.is_manually_closed" class="text-xs text-slate-500 ml-1">(forced closed)</span>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <!-- Force open -->
              <AppIconButton v-if="!p.is_manually_open" label="Force open" variant="success" @click="toggle(p, 'open')">
                <LockOpenIcon class="w-4 h-4" />
              </AppIconButton>
              <!-- Force close -->
              <AppIconButton v-if="!p.is_manually_closed" label="Force close" variant="danger" @click="toggle(p, 'close')">
                <LockClosedIcon class="w-4 h-4" />
              </AppIconButton>
              <!-- Reset to auto -->
              <AppIconButton v-if="p.is_manually_open || p.is_manually_closed" label="Reset to automatic" @click="toggle(p, 'auto')">
                <ArrowPathIcon class="w-4 h-4" />
              </AppIconButton>
              <!-- Edit -->
              <AppIconButton label="Edit" @click="openEdit(p)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <!-- Delete -->
              <AppIconButton label="Delete" variant="danger" @click="deletePeriod(p)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="p in periods" :key="p.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ p.label }}</p>
                <p class="text-xs text-slate-500">{{ p.grade_level ? GRADE_LABELS[p.grade_level] : 'All Grades' }}</p>
              </div>
              <AppBadge :color="statusColor(p)">{{ p.is_open ? 'Open' : 'Closed' }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ formatDt(p.open_at) }} – {{ formatDt(p.close_at) }}</p>
            <p v-if="p.is_manually_open" class="text-xs text-green-600">(forced open)</p>
            <p v-else-if="p.is_manually_closed" class="text-xs text-slate-500">(forced closed)</p>
            <div class="flex items-center gap-1 pt-1">
              <AppIconButton v-if="!p.is_manually_open" label="Force open" variant="success" @click="toggle(p, 'open')">
                <LockOpenIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton v-if="!p.is_manually_closed" label="Force close" variant="danger" @click="toggle(p, 'close')">
                <LockClosedIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton v-if="p.is_manually_open || p.is_manually_closed" label="Reset to automatic" @click="toggle(p, 'auto')">
                <ArrowPathIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Edit" @click="openEdit(p)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="deletePeriod(p)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No enrollment periods found" subtitle="No enrollment periods found for this school year." />
        </template>
      </AppTable>

    </div>

    <!-- ── Create / Edit modal ────────────────────────────────────────────────── -->
    <AppModal
      :show="showModal"
      :title="editingPeriod ? 'Edit Enrollment Period' : 'New Enrollment Period'"
      size="lg"
      @close="showModal = false"
    >
      <form @submit.prevent="submitForm" class="space-y-4">

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Label</label>
          <input
            v-model="form.label"
            type="text"
            placeholder="e.g. Grade 7 New Student Enrollment 2025-2026"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
          <p v-if="form.errors.label" class="text-xs text-red-600 mt-1">{{ form.errors.label }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Grade Level <span class="text-slate-400">(leave blank for all grades)</span></label>
          <select
            v-model="form.grade_level"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option :value="null">All Grades</option>
            <option v-for="g in [7,8,9,10,11,12]" :key="g" :value="g">Grade {{ g }}</option>
          </select>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Opens</label>
            <input
              v-model="form.open_at"
              type="datetime-local"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <p v-if="form.errors.open_at" class="text-xs text-red-600 mt-1">{{ form.errors.open_at }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Closes</label>
            <input
              v-model="form.close_at"
              type="datetime-local"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <p v-if="form.errors.close_at" class="text-xs text-red-600 mt-1">{{ form.errors.close_at }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <AppButton type="button" variant="secondary" @click="showModal = false">Cancel</AppButton>
          <AppButton type="submit" :loading="form.processing">Save</AppButton>
        </div>
      </form>
    </AppModal>

  </AdminLayout>
</template>
