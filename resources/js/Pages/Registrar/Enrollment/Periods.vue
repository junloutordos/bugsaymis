<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  CalendarDaysIcon,
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

function deletePeriod(period) {
  if (! confirm(`Delete "${period.label}"?`)) return
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
  if (period.is_open) return 'bg-green-100 text-green-700'
  return 'bg-slate-100 text-slate-600'
}
</script>

<template>
  <Head title="Enrollment Periods" />
  <AdminLayout title="Enrollment Periods">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <div class="flex items-center gap-2">
        <CalendarDaysIcon class="w-6 h-6 text-indigo-600" />
        <h1 class="text-lg font-semibold text-slate-800">Enrollment Periods</h1>
      </div>

      <div class="flex items-center gap-3">
        <select
          v-model="schoolYearId"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
            {{ sy.name }}{{ sy.is_current ? ' (Current)' : '' }}
          </option>
        </select>

        <button
          @click="openCreate"
          class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
        >
          <PlusIcon class="w-4 h-4" />
          New Period
        </button>
      </div>
    </div>

    <!-- Back link -->
    <div class="mb-4">
      <a :href="route('registrar.enrollment.index')" class="text-sm text-indigo-600 hover:underline">
        ← Back to Enrollment
      </a>
    </div>

    <!-- Table -->
    <div v-if="periods.length === 0" class="text-center text-slate-500 text-sm py-12">
      No enrollment periods found for this school year.
    </div>

    <div v-else class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Label</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Grade</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Opens</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Closes</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="p in periods" :key="p.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 font-medium text-slate-800">{{ p.label }}</td>
            <td class="px-4 py-3 text-slate-500">{{ p.grade_level ? GRADE_LABELS[p.grade_level] : 'All Grades' }}</td>
            <td class="px-4 py-3 text-slate-500">{{ formatDt(p.open_at) }}</td>
            <td class="px-4 py-3 text-slate-500">{{ formatDt(p.close_at) }}</td>
            <td class="px-4 py-3">
              <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusColor(p)]">
                {{ p.is_open ? 'Open' : 'Closed' }}
              </span>
              <span v-if="p.is_manually_open" class="text-xs text-green-600 ml-1">(forced open)</span>
              <span v-else-if="p.is_manually_closed" class="text-xs text-slate-500 ml-1">(forced closed)</span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <!-- Force open -->
                <button
                  v-if="!p.is_manually_open"
                  @click="toggle(p, 'open')"
                  title="Force open"
                  class="p-1 rounded hover:bg-green-50 text-green-600"
                >
                  <LockOpenIcon class="w-4 h-4" />
                </button>
                <!-- Force close -->
                <button
                  v-if="!p.is_manually_closed"
                  @click="toggle(p, 'close')"
                  title="Force close"
                  class="p-1 rounded hover:bg-red-50 text-red-500"
                >
                  <LockClosedIcon class="w-4 h-4" />
                </button>
                <!-- Reset to auto -->
                <button
                  v-if="p.is_manually_open || p.is_manually_closed"
                  @click="toggle(p, 'auto')"
                  title="Reset to automatic"
                  class="p-1 rounded hover:bg-slate-100 text-slate-500"
                >
                  <ArrowPathIcon class="w-4 h-4" />
                </button>
                <!-- Edit -->
                <button
                  @click="openEdit(p)"
                  class="p-1 rounded hover:bg-indigo-50 text-indigo-500"
                >
                  <PencilSquareIcon class="w-4 h-4" />
                </button>
                <!-- Delete -->
                <button
                  @click="deletePeriod(p)"
                  class="p-1 rounded hover:bg-red-50 text-red-500"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Create / Edit modal ────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="absolute inset-0 bg-black/40" @click="showModal = false" />
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
          <h3 class="font-semibold text-slate-800 mb-4">
            {{ editingPeriod ? 'Edit Enrollment Period' : 'New Enrollment Period' }}
          </h3>

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
              <button
                type="button"
                @click="showModal = false"
                class="px-4 py-2 rounded-lg text-sm text-slate-600 border border-slate-200 hover:bg-slate-50"
              >Cancel</button>
              <button
                type="submit"
                :disabled="form.processing"
                class="px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50"
              >
                {{ form.processing ? 'Saving…' : 'Save' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
