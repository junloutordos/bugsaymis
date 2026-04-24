<template>
  <Head title="Training Records" />
  <AdminLayout title="Training Records">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Training Records</h1>
          <p class="text-sm text-slate-500 mt-0.5">L&D interventions attended by faculty and staff</p>
        </div>
        <button @click="openForm()"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
          <PlusIcon class="h-4 w-4" /> Add Record
        </button>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.errors.error }}
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-3">
        <select v-model="filterYear" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">S.Y. {{ sy.name }}</option>
        </select>
        <select v-model="filterStatus" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="verified">Verified</option>
          <option value="rejected">Rejected</option>
        </select>
        <select v-model="filterLevel" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
          <option value="">All Levels</option>
          <option value="international">International</option>
          <option value="national">National</option>
          <option value="regional">Regional</option>
          <option value="division">Division</option>
          <option value="school">School</option>
        </select>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-100">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Faculty</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Training</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Dates</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Level</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Hours</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-if="records.length === 0">
              <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">No training records found.</td>
            </tr>
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
                <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="levelClass(r.level)">
                  {{ r.level }}
                </span>
              </td>
              <td class="px-4 py-3 text-center text-slate-700 font-mono text-xs">{{ r.hours_earned }}h</td>
              <td class="px-4 py-3 text-center">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="statusClass(r.status)">
                  {{ r.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button v-if="r.status === 'pending'" @click="verifyRecord(r)"
                    class="text-xs text-emerald-600 hover:text-emerald-800 border border-emerald-200 hover:bg-emerald-50 px-2 py-1 rounded transition-colors font-medium">
                    Verify
                  </button>
                  <button v-if="r.status === 'pending'" @click="openRejectModal(r)"
                    class="text-xs text-red-500 hover:text-red-700 border border-red-200 hover:bg-red-50 px-2 py-1 rounded transition-colors font-medium">
                    Reject
                  </button>
                  <button v-if="r.status !== 'verified'" @click="openForm(r)" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded">
                    <PencilIcon class="h-4 w-4" />
                  </button>
                  <button v-if="r.status !== 'verified'" @click="deleteRecord(r)" class="p-1.5 text-slate-400 hover:text-red-600 rounded">
                    <TrashIcon class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <div v-if="formModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <h2 class="text-lg font-semibold text-slate-800">{{ form.id ? 'Edit' : 'Add' }} Training Record</h2>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Training Title <span class="text-red-500">*</span></label>
            <input v-model="form.training_title" type="text"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
            <p v-if="form.errors.training_title" class="text-red-500 text-xs mt-1">{{ form.errors.training_title }}</p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Organizer</label>
              <input v-model="form.organizer" type="text"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Venue</label>
              <input v-model="form.venue" type="text"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Date From <span class="text-red-500">*</span></label>
              <input v-model="form.date_from" type="date"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Date To <span class="text-red-500">*</span></label>
              <input v-model="form.date_to" type="date"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Level <span class="text-red-500">*</span></label>
              <select v-model="form.level"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                <option value="international">International</option>
                <option value="national">National</option>
                <option value="regional">Regional</option>
                <option value="division">Division</option>
                <option value="school">School</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Hours Earned <span class="text-red-500">*</span></label>
              <input v-model="form.hours_earned" type="number" min="0" step="0.5"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
            <textarea v-model="form.remarks" rows="2"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-1">
          <button @click="formModal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="save" :disabled="form.processing"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium">
            {{ form.id ? 'Update' : 'Save' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="rejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">Reject Training Record</h2>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Reason <span class="text-red-500">*</span></label>
          <textarea v-model="rejectForm.remarks" rows="3" placeholder="Explain why this record is being rejected"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
          <p v-if="rejectForm.errors.remarks" class="text-red-500 text-xs mt-1">{{ rejectForm.errors.remarks }}</p>
        </div>
        <div class="flex justify-end gap-3 pt-1">
          <button @click="rejectModal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="submitReject" :disabled="rejectForm.processing"
            class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-lg font-medium">
            Reject
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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
function verifyRecord(r) {
  if (! confirm(`Verify training record "${r.training_title}"?`)) return
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
function deleteRecord(r) {
  if (! confirm(`Delete training record "${r.training_title}"?`)) return
  useForm({}).delete(route('faculty-loading.training-records.destroy', r.id))
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const STATUS_CLASSES = { pending: 'bg-amber-100 text-amber-700', verified: 'bg-emerald-100 text-emerald-700', rejected: 'bg-red-100 text-red-600' }
const LEVEL_CLASSES  = { international: 'bg-purple-100 text-purple-700', national: 'bg-blue-100 text-blue-700', regional: 'bg-indigo-100 text-indigo-700', division: 'bg-cyan-100 text-cyan-700', school: 'bg-slate-100 text-slate-600' }
const statusClass = (s) => STATUS_CLASSES[s] ?? 'bg-slate-100 text-slate-500'
const levelClass  = (l) => LEVEL_CLASSES[l]  ?? 'bg-slate-100 text-slate-500'
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
</script>
