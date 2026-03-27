<template>
  <Head title="School Years & Terms" />
  <AdminLayout title="School Years & Terms">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">School Years & Terms</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage school years and academic term periods</p>
        </div>
        <button @click="openSyForm()"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
          <PlusIcon class="h-4 w-4" />
          New School Year
        </button>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.errors.error }}
      </div>

      <!-- Empty -->
      <div v-if="schoolYears.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <AcademicCapIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No school years yet</p>
      </div>

      <!-- School years list -->
      <div v-for="sy in schoolYears" :key="sy.id"
        class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 bg-slate-50/50">
          <div class="flex items-center gap-3">
            <p class="font-semibold text-slate-800">S.Y. {{ sy.name }}</p>
            <span v-if="sy.is_current"
              class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-700">
              Current
            </span>
            <span :class="sy.status === 'active' ? 'bg-blue-50 text-blue-600' : 'bg-slate-100 text-slate-500'"
              class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
              {{ sy.status }}
            </span>
          </div>
          <div class="flex items-center gap-2">
            <button @click="openTermForm(sy)"
              class="text-xs text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1">
              <PlusIcon class="h-3.5 w-3.5" /> Add Term
            </button>
            <button @click="openSyForm(sy)" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded">
              <PencilIcon class="h-4 w-4" />
            </button>
            <button @click="deleteSy(sy)" class="p-1.5 text-slate-400 hover:text-red-600 rounded">
              <TrashIcon class="h-4 w-4" />
            </button>
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
              <span v-if="term.is_current"
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-indigo-100 text-indigo-700">
                Current
              </span>
            </div>
            <div class="flex items-center gap-1">
              <button @click="openTermForm(sy, term)" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded">
                <PencilIcon class="h-4 w-4" />
              </button>
              <button @click="deleteTerm(term)" class="p-1.5 text-slate-400 hover:text-red-600 rounded">
                <TrashIcon class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
        <div v-else class="px-5 py-4 text-xs text-slate-400 italic">No terms yet.</div>
      </div>

    </div>

    <!-- School Year Modal -->
    <div v-if="syModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">{{ syForm.id ? 'Edit' : 'New' }} School Year</h2>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Name (e.g. 2025-2026)</label>
            <input v-model="syForm.name" type="text" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Start Date</label>
              <input v-model="syForm.start_date" type="date" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">End Date</label>
              <input v-model="syForm.end_date" type="date" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
              <select v-model="syForm.status" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="archived">Archived</option>
              </select>
            </div>
            <div class="flex items-center gap-2 pt-5">
              <input v-model="syForm.is_current" type="checkbox" id="sy-current" class="rounded text-indigo-600" />
              <label for="sy-current" class="text-sm text-slate-600">Set as current</label>
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-1">
          <button @click="syModal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="saveSy"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium">
            {{ syForm.id ? 'Update' : 'Create' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Term Modal -->
    <div v-if="termModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">{{ termForm.id ? 'Edit' : 'Add' }} Academic Term</h2>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Term Name</label>
            <input v-model="termForm.name" type="text" placeholder="e.g. 1st Semester, S.Y. 2025-2026"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Term Type</label>
            <select v-model="termForm.term_type" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option value="1st_semester">1st Semester</option>
              <option value="2nd_semester">2nd Semester</option>
              <option value="summer">Summer</option>
              <option value="trimester">Trimester</option>
              <option value="quarter">Quarter</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Start Date</label>
              <input v-model="termForm.start_date" type="date" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">End Date</label>
              <input v-model="termForm.end_date" type="date" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
            </div>
          </div>
          <div class="flex items-center gap-2">
            <input v-model="termForm.is_current" type="checkbox" id="term-current" class="rounded text-indigo-600" />
            <label for="term-current" class="text-sm text-slate-600">Set as current term</label>
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-1">
          <button @click="termModal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="saveTerm"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium">
            {{ termForm.id ? 'Update' : 'Add Term' }}
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

function deleteSy(sy) {
  if (! confirm(`Delete S.Y. ${sy.name}?`)) return
  useForm({}).delete(route('faculty-loading.school-years.destroy', sy.id))
}

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

function deleteTerm(term) {
  if (! confirm(`Delete term "${term.name}"?`)) return
  useForm({}).delete(route('faculty-loading.school-years.terms.destroy', term.id))
}
</script>
