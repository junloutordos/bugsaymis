<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, MagnifyingGlassIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  incidents: Array,
  filters:   Object,
  myHall:    String,
})

const search  = ref(props.filters.search || '')
const typeF   = ref(props.filters.type || '')
const statusF = ref(props.filters.status || '')
const showAdd = ref(false)
const showEdit = ref(null)
const editData = ref({})

const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  const lower = search.value.toLowerCase()
  return props.incidents.filter(i =>
    (!lower || i.student_name.toLowerCase().includes(lower)) &&
    (!typeF.value || i.incident_type === typeF.value) &&
    (!statusF.value || i.status === statusF.value)
  )
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

function applyFilters() {
  currentPage.value = 1
  router.get(route('rh.incidents.index'), { type: typeF.value, status: statusF.value, search: search.value }, { preserveState: true, replace: true })
}

const addForm = useForm({
  rh_intern_id:         '',
  incident_type:        'health',
  description:          '',
  initial_intervention: '',
  referred_to:          '',
})

function submitAdd() {
  addForm.post(route('rh.incidents.store'), {
    preserveScroll: true,
    onSuccess: () => { showAdd.value = false; addForm.reset() },
  })
}

function openEdit(inc) {
  showEdit.value = inc
  editData.value = {
    initial_intervention: inc.initial_intervention || '',
    referred_to:          inc.referred_to || '',
    follow_up_notes:      inc.follow_up_notes || '',
  }
}

function submitEdit() {
  router.put(route('rh.incidents.update', showEdit.value.id), editData.value, {
    preserveScroll: true,
    onSuccess: () => { showEdit.value = null },
  })
}

function resolve(inc) {
  if (!confirm('Mark this incident as resolved?')) return
  router.post(route('rh.incidents.resolve', inc.id), {}, { preserveScroll: true })
}

const typeClass = (t) => ({
  health:        'bg-rose-100 text-rose-700',
  behavioral:    'bg-amber-100 text-amber-700',
  psychological: 'bg-purple-100 text-purple-700',
}[t] || 'bg-slate-100 text-slate-600')

const fmtDate = (d) => d
  ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
  : '—'
</script>

<template>
  <Head title="Incidents" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-5">

      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Incident Log</h1>
          <p class="text-sm text-slate-500">Health, behavioral, and psychological incident records</p>
        </div>
        <button @click="showAdd = true"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          <PlusIcon class="w-4 h-4" /> Log Incident
        </button>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
          <select v-model="typeF" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Types</option>
            <option value="health">Health</option>
            <option value="behavioral">Behavioral</option>
            <option value="psychological">Psychological</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
          <select v-model="statusF" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All</option>
            <option value="open">Open</option>
            <option value="resolved">Resolved</option>
          </select>
        </div>
        <div class="flex-1 min-w-[200px]">
          <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
          <div class="relative">
            <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input v-model="search" @input="currentPage = 1" type="text" placeholder="Student name…"
                   class="w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50">
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden md:table-cell">Hall</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden lg:table-cell">Description</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="inc in displayed" :key="inc.id" class="hover:bg-slate-50 transition-colors">
              <td class="px-4 py-3 font-medium text-slate-800">{{ inc.student_name }}</td>
              <td class="px-4 py-3 hidden md:table-cell">
                <span v-if="inc.residence_hall"
                      :class="['text-xs px-2 py-0.5 rounded-full font-medium', inc.residence_hall === 'BRH' ? 'bg-indigo-100 text-indigo-700' : 'bg-pink-100 text-pink-700']">
                  {{ inc.residence_hall }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium capitalize', typeClass(inc.incident_type)]">
                  {{ inc.incident_type }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-600 max-w-xs truncate hidden lg:table-cell">{{ inc.description }}</td>
              <td class="px-4 py-3 text-slate-500 text-xs">{{ fmtDate(inc.created_at) }}</td>
              <td class="px-4 py-3">
                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium',
                  inc.status === 'open' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700']">
                  {{ inc.status }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-1">
                  <button @click="openEdit(inc)"
                          class="text-xs text-indigo-600 hover:underline font-medium">Edit</button>
                  <span v-if="inc.status === 'open'" class="text-slate-300">·</span>
                  <button v-if="inc.status === 'open'" @click="resolve(inc)"
                          class="text-xs text-emerald-600 hover:underline font-medium">Resolve</button>
                </div>
              </td>
            </tr>
            <tr v-if="!displayed.length">
              <td colspan="7" class="text-center py-12 text-slate-400 text-sm">No incidents found.</td>
            </tr>
          </tbody>
        </table>
        <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
          <p class="text-xs text-slate-500">Page {{ currentPage }} of {{ totalPages }}</p>
          <div class="flex gap-2">
            <button @click="currentPage--" :disabled="currentPage <= 1"
                    class="px-3 py-1.5 rounded-lg text-xs border border-slate-200 text-slate-600 disabled:opacity-40 hover:bg-slate-50">Prev</button>
            <button @click="currentPage++" :disabled="currentPage >= totalPages"
                    class="px-3 py-1.5 rounded-lg text-xs border border-slate-200 text-slate-600 disabled:opacity-40 hover:bg-slate-50">Next</button>
          </div>
        </div>
      </div>

    </div>

    <!-- Add Incident Modal -->
    <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-base font-semibold text-slate-800 mb-4">Log Incident</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Intern ID</label>
            <input v-model.number="addForm.rh_intern_id" type="number" placeholder="Intern record ID"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Incident Type</label>
            <select v-model="addForm.incident_type"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="health">Health</option>
              <option value="behavioral">Behavioral</option>
              <option value="psychological">Psychological</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
            <textarea v-model="addForm.description" rows="3"
                      class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      placeholder="Describe the incident…"></textarea>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Initial Intervention</label>
            <textarea v-model="addForm.initial_intervention" rows="2"
                      class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      placeholder="Actions taken immediately…"></textarea>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Referred To</label>
            <input v-model="addForm.referred_to" type="text"
                   placeholder="e.g. School Nurse, Guidance Counselor"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="showAdd = false; addForm.reset()"
                  class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50">Cancel</button>
          <button @click="submitAdd" :disabled="!addForm.rh_intern_id || !addForm.description || addForm.processing"
                  class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium">
            Log Incident
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Incident Modal -->
    <div v-if="showEdit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-base font-semibold text-slate-800 mb-1">Update Incident</h3>
        <p class="text-sm text-slate-500 mb-4">{{ showEdit.student_name }} — {{ showEdit.incident_type }}</p>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Initial Intervention</label>
            <textarea v-model="editData.initial_intervention" rows="2"
                      class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Referred To</label>
            <input v-model="editData.referred_to" type="text"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Follow-up Notes</label>
            <textarea v-model="editData.follow_up_notes" rows="3"
                      class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
          </div>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="showEdit = null"
                  class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50">Cancel</button>
          <button @click="submitEdit"
                  class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
            Save
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
