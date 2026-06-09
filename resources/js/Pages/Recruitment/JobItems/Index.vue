<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  items:            { type: Object, required: true },
  recruitmentTypes: { type: Array,  default: () => [] },
  offices:          { type: Array,  default: () => [] },
  filters:          { type: Object, default: () => ({}) },
  allRequirements:  { type: Array,  default: () => [] },
  salaryTable:      { type: Object, default: () => ({}) },  // grade => { step => rate }
})

const page = usePage()

// ── Filters ────────────────────────────────────────────────────────────────────
const search    = ref(props.filters?.search  ?? '')
const typeId    = ref(props.filters?.type_id ?? '')
const status    = ref(props.filters?.status  ?? '')
const isLoading = ref(false)
let debounceTimer = null

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('recruitment.job-items.index'), {
      search:  search.value  || undefined,
      type_id: typeId.value  || undefined,
      status:  status.value  || undefined,
    }, {
      preserveState: true, replace: true,
      only: ['items', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
  }
  if (immediate) go()
  else debounceTimer = setTimeout(go, 400)
}

watch(search, () => applyFilters(false))
watch([typeId, status], () => applyFilters(true))

const goToPage = (p) => {
  isLoading.value = true
  router.get(route('recruitment.job-items.index'), {
    search: search.value || undefined, type_id: typeId.value || undefined,
    status: status.value || undefined, page: p,
  }, { preserveState: true, replace: true, only: ['items', 'filters'], onFinish: () => { isLoading.value = false } })
}

// ── Status helpers ─────────────────────────────────────────────────────────────
const statusColors = {
  draft:     'bg-gray-100 text-gray-600',
  approved:  'bg-blue-100 text-blue-700',
  published: 'bg-green-100 text-green-700',
  closed:    'bg-red-100 text-red-600',
}

// ── Modal state ────────────────────────────────────────────────────────────────
const showModal       = ref(false)
const showPublishModal = ref(false)
const showReqModal    = ref(false)   // requirements view modal
const editingItem     = ref(null)
const publishTarget   = ref(null)
const viewReqItem     = ref(null)
const isSubmitting    = ref(false)

// Plantilla types that use SG/Step/Monthly Salary
const PLANTILLA_NAMES = ['Plantilla Teaching', 'Plantilla Non-Teaching']

// Competency definitions — name + suggested proficiency level
const COMPETENCY_DEFS = [
  { name: 'Exemplifying Integrity',                                     suggested: 'advanced' },
  { name: 'Delivering Service Excellence',                              suggested: 'advanced' },
  { name: 'Solving Problems and Decision Making',                       suggested: 'advanced' },
  { name: 'Championing and Applying Innovation',                        suggested: 'advanced' },
  { name: 'Planning and Delivering',                                    suggested: 'intermediate' },
  { name: 'Demonstrating Personal Effectiveness',                       suggested: 'intermediate' },
  { name: 'Speaking Effectively',                                       suggested: 'intermediate' },
  { name: 'Writing Effectively',                                        suggested: 'intermediate' },
  { name: 'Managing Information',                                       suggested: 'intermediate' },
  { name: 'Building Collaborative and Inclusive Working Relationship',  suggested: 'intermediate' },
  { name: 'Managing Performance and Coaching for Results',              suggested: 'basic' },
  { name: 'Leading Change',                                             suggested: 'basic' },
  { name: 'Thinking Strategically and Creatively',                     suggested: 'basic' },
  { name: 'Creating and Nurturing a High-Performance Organization',     suggested: 'basic' },
]
const RESEARCH_COMPETENCY = 'Preferably with Research Outputs'

// Build an empty competency state map: name => { selected, level }
const emptyCompetencies = () => {
  const map = {}
  COMPETENCY_DEFS.forEach(c => { map[c.name] = { selected: false, level: 'basic' } })
  map[RESEARCH_COMPETENCY] = { selected: false, level: null }
  return map
}

// Convert competency map → array for storage
const serializeCompetencies = (map) => {
  return Object.entries(map)
    .filter(([, v]) => v.selected)
    .map(([name, v]) => ({ name, level: v.level }))
}

// Convert stored array → competency map
const deserializeCompetencies = (arr) => {
  const map = emptyCompetencies()
  if (!arr) return map
  arr.forEach(item => {
    if (map[item.name] !== undefined) {
      map[item.name].selected = true
      if (item.level) map[item.name].level = item.level
    }
  })
  return map
}

const competencyMap = ref(emptyCompetencies())

const emptyForm = () => ({
  recruitment_type_id:     '',
  position_title:          '',
  plantilla_item_no:       '',
  salary_grade:            '',
  salary_step:             1,
  monthly_salary:          '',
  daily_rate:              '',
  duties_responsibilities: '',
  education:               '',
  experience:              '',
  training:                '',
  eligibility:             '',
  duration_type:           '',
  budget_source:           '',
  office_id:               '',
  requirement_ids:         [],
  requirement_mandatory:   {},
})

const form        = ref(emptyForm())
const publishForm = ref({ posting_date: '', closing_date: '', publication_type: 'internal' })

const isPlantilla = computed(() => {
  const t = props.recruitmentTypes.find(t => t.id == form.value.recruitment_type_id)
  return t ? PLANTILLA_NAMES.includes(t.name) : false
})

// When type changes, clear compensation fields that don't apply
watch(() => form.value.recruitment_type_id, () => {
  if (isPlantilla.value) {
    form.value.daily_rate = ''
  } else {
    form.value.salary_grade   = ''
    form.value.salary_step    = 1
    form.value.monthly_salary = ''
  }
})
const errors      = ref({})

const openModal = (item = null) => {
  editingItem.value = item
  errors.value = {}
  if (item) {
    const reqIds  = item.requirements?.map(r => r.id) ?? []
    const reqMand = {}
    item.requirements?.forEach(r => { reqMand[r.id] = r.pivot?.is_mandatory ?? true })
    form.value = {
      recruitment_type_id:     item.recruitment_type_id,
      position_title:          item.position_title,
      plantilla_item_no:       item.plantilla_item_no       ?? '',
      salary_grade:            item.salary_grade            ?? '',
      salary_step:             item.salary_step             ?? 1,
      monthly_salary:          item.monthly_salary          ?? '',
      daily_rate:              item.daily_rate              ?? '',
      duties_responsibilities: item.duties_responsibilities ?? '',
      education:               item.education               ?? '',
      experience:              item.experience              ?? '',
      training:                item.training                ?? '',
      eligibility:             item.eligibility             ?? '',
      duration_type:           item.duration_type           ?? '',
      budget_source:           item.budget_source           ?? '',
      office_id:               item.office_id               ?? '',
      requirement_ids:         reqIds,
      requirement_mandatory:   reqMand,
    }
    competencyMap.value = deserializeCompetencies(item.competencies)
  } else {
    form.value = emptyForm()
    competencyMap.value = emptyCompetencies()
  }
  showModal.value = true
}

const closeModal   = () => { showModal.value = false; editingItem.value = null }
const openPublish  = (item) => { publishTarget.value = item; publishForm.value = { posting_date: '', closing_date: '', publication_type: 'internal' }; showPublishModal.value = true }
const closePublish = () => { showPublishModal.value = false; publishTarget.value = null }

const openReq = (item) => { viewReqItem.value = item; showReqModal.value = true }

// ── Salary auto-fill ───────────────────────────────────────────────────────────
const lookupSalary = () => {
  const g = parseInt(form.value.salary_grade)
  const s = parseInt(form.value.salary_step) || 1
  if (!g) return
  const gradeData = props.salaryTable[g]
  if (gradeData) {
    const rate = gradeData[s] ?? gradeData[1]
    if (rate) form.value.monthly_salary = rate
  }
}

watch(() => [form.value.salary_grade, form.value.salary_step], lookupSalary)

// ── Requirements toggle ────────────────────────────────────────────────────────
const toggleReq = (id) => {
  const idx = form.value.requirement_ids.indexOf(id)
  if (idx >= 0) {
    form.value.requirement_ids.splice(idx, 1)
    delete form.value.requirement_mandatory[id]
  } else {
    form.value.requirement_ids.push(id)
    form.value.requirement_mandatory[id] = true
  }
}

const isReqSelected = (id) => form.value.requirement_ids.includes(id)

// ── Submit ─────────────────────────────────────────────────────────────────────
const submit = () => {
  isSubmitting.value = true
  errors.value = {}
  const isEdit = !!editingItem.value
  const url    = isEdit ? route('recruitment.job-items.update', editingItem.value.id) : route('recruitment.job-items.store')
  const method = isEdit ? 'put' : 'post'
  const payload = { ...form.value, competencies: serializeCompetencies(competencyMap.value) }
  router[method](url, payload, {
    onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: isEdit ? 'Updated!' : 'Created!', timer: 1500, showConfirmButton: false }) },
    onError:   (e) => { errors.value = e },
    onFinish:  ()  => { isSubmitting.value = false },
  })
}

// ── Publish ────────────────────────────────────────────────────────────────────
const submitPublish = () => {
  isSubmitting.value = true
  router.post(route('recruitment.job-items.publish', publishTarget.value.id), publishForm.value, {
    onSuccess: () => { closePublish(); Swal.fire({ icon: 'success', title: 'Published!', timer: 1500, showConfirmButton: false }) },
    onError:   (e) => { errors.value = e },
    onFinish:  ()  => { isSubmitting.value = false },
  })
}

// ── Change status ──────────────────────────────────────────────────────────────
const changeStatus = async (item, newStatus) => {
  const labels = { approved: 'Approve', closed: 'Close' }
  const result = await Swal.fire({
    title: `${labels[newStatus] ?? newStatus} this item?`, icon: 'question',
    showCancelButton: true, confirmButtonText: 'Yes', cancelButtonText: 'Cancel', reverseButtons: true,
  })
  if (!result.isConfirmed) return
  router.patch(route('recruitment.job-items.status', item.id), { status: newStatus }, {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Status updated!', timer: 1200, showConfirmButton: false }),
  })
}

// ── Delete ─────────────────────────────────────────────────────────────────────
const deleteItem = async (item) => {
  const result = await Swal.fire({
    title: 'Delete this job item?', text: 'This cannot be undone.', icon: 'warning',
    showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Delete', reverseButtons: true,
  })
  if (!result.isConfirmed) return
  router.delete(route('recruitment.job-items.destroy', item.id), {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1200, showConfirmButton: false }),
    onError:   (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
  })
}

const fmtSalary = (v) => v ? '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—'
</script>

<template>
  <Head title="Job Items — Recruitment" />
  <AdminLayout title="Job Items">
    <div>
      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm">{{ page.props.flash.success }}</div>
      <div v-if="page.props.flash?.error"   class="mb-4 px-4 py-3 rounded-lg bg-red-50   border border-red-100   text-red-600   text-sm">{{ page.props.flash.error }}</div>

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Job Items</h1>
        <button @click="openModal()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          + New Job Item
        </button>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[180px] sm:max-w-xs">
          <input v-model="search" type="text" placeholder="Search position…"
                 class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <span v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">…</span>
        </div>
        <select v-model="typeId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Types</option>
          <option v-for="t in recruitmentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
        <select v-model="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Status</option>
          <option value="draft">Draft</option>
          <option value="approved">Approved</option>
          <option value="published">Published</option>
          <option value="closed">Closed</option>
        </select>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto rounded-xl">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Position</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Engagement Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">SG / Salary</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Office</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Req.</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in items.data" :key="item.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ item.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <div class="font-medium text-slate-800">{{ item.position_title }}</div>
                  <div v-if="item.plantilla_item_no" class="text-xs text-slate-400">{{ item.plantilla_item_no }}</div>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ item.recruitment_type?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <template v-if="PLANTILLA_NAMES.includes(item.recruitment_type?.name)">
                    <div class="font-medium text-slate-800">SG {{ item.salary_grade ?? '—' }}<span v-if="item.salary_step"> · Step {{ item.salary_step }}</span></div>
                    <div class="text-xs text-emerald-700">{{ fmtSalary(item.monthly_salary) }}/mo</div>
                  </template>
                  <template v-else>
                    <div class="text-xs text-slate-500">Daily Rate</div>
                    <div class="font-medium text-emerald-700">{{ item.daily_rate ? fmtSalary(item.daily_rate) + '/day' : '—' }}</div>
                  </template>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ item.office?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-center">
                  <button v-if="item.requirements?.length"
                          @click="openReq(item)"
                          class="text-xs text-indigo-600 hover:underline">
                    {{ item.requirements.length }} doc{{ item.requirements.length !== 1 ? 's' : '' }}
                  </button>
                  <span v-else class="text-xs text-slate-400">—</span>
                </td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                        :class="{
                          'bg-amber-50 text-amber-700':   item.status === 'draft',
                          'bg-blue-50 text-blue-700':     item.status === 'approved',
                          'bg-emerald-50 text-emerald-700': item.status === 'published',
                          'bg-red-50 text-red-600':       item.status === 'closed',
                        }">
                    {{ item.status }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1 justify-center flex-wrap">
                    <button v-if="item.status === 'draft'" @click="openModal(item)"
                            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">Edit</button>
                    <button v-if="item.status === 'draft'" @click="changeStatus(item, 'approved')"
                            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">Approve</button>
                    <button v-if="item.status === 'approved'" @click="openPublish(item)"
                            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">Publish</button>
                    <button v-if="item.status === 'published'" @click="changeStatus(item, 'closed')"
                            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">Close</button>
                    <button v-if="item.status === 'draft'" @click="deleteItem(item)"
                            class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">Delete</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!items.data?.length">
                <td colspan="8" class="py-16 text-center text-slate-400 text-sm">No job items found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="items.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <button @click="goToPage(items.current_page - 1)" :disabled="items.current_page === 1 || isLoading"
                  class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Prev</button>
          <span>Page {{ items.current_page }} of {{ items.last_page }}</span>
          <button @click="goToPage(items.current_page + 1)" :disabled="items.current_page === items.last_page || isLoading"
                  class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Next</button>
        </div>
      </div>
    </div>

    <!-- ── Create / Edit Modal ───────────────────────────────────────────────── -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
      <div class="bg-white rounded-2xl w-full max-w-3xl shadow-xl relative max-h-[92vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">{{ editingItem ? 'Edit Job Item' : 'New Job Item' }}</h2>
          <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
        </div>

        <form @submit.prevent="submit" class="px-6 py-5 space-y-5">

          <!-- Section 1: Classification -->
          <fieldset class="border border-slate-200 rounded-xl p-4">
            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide px-1">Classification</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">

              <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Engagement / Employment Type *</label>
                <select v-model="form.recruitment_type_id" required
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                  <option value="" disabled>Select engagement type</option>
                  <option v-for="t in recruitmentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <p v-if="errors.recruitment_type_id" class="text-red-500 text-xs mt-1">{{ errors.recruitment_type_id }}</p>
                <p class="text-xs text-slate-400 mt-1">This defines the engagement type (Plantilla, COS, JO, GIP, OJT, etc.) and drives the entire selection process.</p>
              </div>

              <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Position Title *</label>
                <input v-model="form.position_title" type="text" required
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                <p v-if="errors.position_title" class="text-red-500 text-xs mt-1">{{ errors.position_title }}</p>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Plantilla Item No.</label>
                <input v-model="form.plantilla_item_no" type="text" placeholder="e.g. PSHS-CRC-T-1-2024"
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Nature of Appointment</label>
                <select v-model="form.duration_type" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                  <option value="">— Select —</option>
                  <option value="permanent">Permanent</option>
                  <option value="temporary">Temporary</option>
                  <option value="contractual">Contractual</option>
                  <option value="casual">Casual</option>
                  <option value="cos">Contract of Service</option>
                  <option value="job_order">Job Order</option>
                </select>
              </div>

              <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Assigned Office / Unit</label>
                <select v-model="form.office_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                  <option value="">— None —</option>
                  <option v-for="o in offices" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
              </div>
            </div>
          </fieldset>

          <!-- Section 2: Compensation -->
          <fieldset class="border border-slate-200 rounded-xl p-4">
            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide px-1">Compensation</legend>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">

              <template v-if="isPlantilla">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Salary Grade (SG)</label>
                  <select v-model="form.salary_grade" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                    <option value="">— Select SG —</option>
                    <option v-for="g in 33" :key="g" :value="g">SG {{ g }}</option>
                  </select>
                  <p v-if="errors.salary_grade" class="text-red-500 text-xs mt-1">{{ errors.salary_grade }}</p>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Step</label>
                  <select v-model="form.salary_step" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                    <option v-for="s in 8" :key="s" :value="s">Step {{ s }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Monthly Salary (PHP)</label>
                  <input v-model="form.monthly_salary" type="number" min="0" step="0.01"
                         class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                         placeholder="Auto-filled from SG table" />
                  <p class="text-xs text-slate-400 mt-1">Auto-fills from SG table. You may override.</p>
                </div>
              </template>

              <template v-else>
                <div class="sm:col-span-2">
                  <label class="block text-xs font-medium text-slate-600 mb-1">Daily Rate (PHP)</label>
                  <input v-model="form.daily_rate" type="number" min="0" step="0.01"
                         class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                         placeholder="e.g. 650.00" />
                  <p v-if="errors.daily_rate" class="text-red-500 text-xs mt-1">{{ errors.daily_rate }}</p>
                  <p class="text-xs text-slate-400 mt-1">COS / JO / GIP / OJT positions are compensated by daily rate, not salary grade.</p>
                </div>
              </template>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Budget Source</label>
                <input v-model="form.budget_source" type="text" placeholder="e.g. DBM, CHED, Local"
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
            </div>
          </fieldset>

          <!-- Section 3: Qualifications -->
          <fieldset class="border border-slate-200 rounded-xl p-4">
            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide px-1">Qualifications</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
              <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Education</label>
                <textarea v-model="form.education" rows="2"
                          placeholder="e.g. Bachelor's Degree relevant to the job"
                          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                <p v-if="errors.education" class="text-red-500 text-xs mt-1">{{ errors.education }}</p>
              </div>
              <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Experience</label>
                <textarea v-model="form.experience" rows="2"
                          placeholder="e.g. 2 years of relevant work experience"
                          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                <p v-if="errors.experience" class="text-red-500 text-xs mt-1">{{ errors.experience }}</p>
              </div>
              <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Training</label>
                <textarea v-model="form.training" rows="2"
                          placeholder="e.g. 4 hours of relevant training"
                          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                <p v-if="errors.training" class="text-red-500 text-xs mt-1">{{ errors.training }}</p>
              </div>
              <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Eligibility</label>
                <textarea v-model="form.eligibility" rows="2"
                          placeholder="e.g. RA 1080 (Teacher) / Career Service Professional"
                          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                <p v-if="errors.eligibility" class="text-red-500 text-xs mt-1">{{ errors.eligibility }}</p>
              </div>
              <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Duties &amp; Responsibilities</label>
                <textarea v-model="form.duties_responsibilities" rows="3"
                          placeholder="Key duties and responsibilities of this position…"
                          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
            </div>
          </fieldset>

          <!-- Section 3b: Competencies -->
          <fieldset class="border border-indigo-200 rounded-xl p-4 bg-indigo-50">
            <legend class="text-xs font-semibold text-indigo-700 uppercase tracking-wide px-1">Competencies</legend>
            <p class="text-xs text-indigo-600 mt-2 mb-3">Check applicable competencies and set the required proficiency level.</p>

            <div class="space-y-2">
              <div v-for="def in COMPETENCY_DEFS" :key="def.name"
                   class="flex items-center gap-3 p-2 rounded-lg border transition"
                   :class="competencyMap[def.name].selected
                     ? 'bg-indigo-100 border-indigo-300'
                     : 'bg-white border-slate-200 hover:bg-slate-50'">
                <input type="checkbox"
                       v-model="competencyMap[def.name].selected"
                       class="rounded border-slate-300 text-indigo-600 shadow-sm flex-shrink-0" />
                <span class="flex-1 text-sm text-slate-800 leading-tight">{{ def.name }}</span>
                <select v-if="competencyMap[def.name].selected"
                        v-model="competencyMap[def.name].level"
                        class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                  <option value="basic">Basic</option>
                  <option value="intermediate">Intermediate</option>
                  <option value="advanced">Advanced</option>
                </select>
              </div>

              <div class="flex items-center gap-3 p-2 rounded-lg border transition"
                   :class="competencyMap[RESEARCH_COMPETENCY].selected
                     ? 'bg-indigo-100 border-indigo-300'
                     : 'bg-white border-slate-200 hover:bg-slate-50'">
                <input type="checkbox"
                       v-model="competencyMap[RESEARCH_COMPETENCY].selected"
                       class="rounded border-slate-300 text-indigo-600 shadow-sm flex-shrink-0" />
                <span class="flex-1 text-sm text-slate-800 leading-tight">{{ RESEARCH_COMPETENCY }}</span>
              </div>
            </div>

            <p class="text-xs text-indigo-500 mt-3 italic">
              {{ Object.values(competencyMap).filter(v => v.selected).length }} competenc{{ Object.values(competencyMap).filter(v => v.selected).length !== 1 ? 'ies' : 'y' }} selected.
            </p>
          </fieldset>

          <!-- Section 4: Application Document Requirements -->
          <fieldset class="border border-blue-200 rounded-xl p-4 bg-blue-50">
            <legend class="text-xs font-semibold text-blue-700 uppercase tracking-wide px-1">Application Document Requirements</legend>
            <p class="text-xs text-blue-600 mt-2 mb-3">Select the documents applicants must submit for this position.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <label v-for="req in allRequirements" :key="req.id"
                     class="flex items-start gap-2 p-2 rounded-lg cursor-pointer transition"
                     :class="isReqSelected(req.id) ? 'bg-blue-100 border border-blue-300' : 'bg-white border border-slate-200 hover:bg-slate-50'">
                <input type="checkbox" :checked="isReqSelected(req.id)" @change="toggleReq(req.id)"
                       class="mt-0.5 rounded border-slate-300 text-blue-600 shadow-sm flex-shrink-0" />
                <div class="min-w-0">
                  <p class="text-sm font-medium text-slate-800 leading-tight">{{ req.name }}</p>
                  <p v-if="req.description" class="text-xs text-slate-500 mt-0.5 leading-tight">{{ req.description }}</p>
                  <label v-if="isReqSelected(req.id)" class="flex items-center gap-1 mt-1 cursor-pointer">
                    <input type="checkbox" v-model="form.requirement_mandatory[req.id]"
                           class="rounded border-slate-300 text-orange-500 shadow-sm" />
                    <span class="text-xs text-orange-700">Required (uncheck = optional)</span>
                  </label>
                </div>
              </label>
            </div>
            <p v-if="form.requirement_ids.length === 0" class="text-xs text-blue-500 mt-2 italic">No requirements selected — applicants won't see a document upload list for this position.</p>
            <p v-else class="text-xs text-blue-700 mt-2 font-medium">{{ form.requirement_ids.length }} document{{ form.requirement_ids.length !== 1 ? 's' : '' }} selected.</p>
          </fieldset>

          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="closeModal"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button type="submit" :disabled="isSubmitting"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              {{ isSubmitting ? 'Saving…' : (editingItem ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Requirements View Modal ───────────────────────────────────────────── -->
    <div v-if="showReqModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-xl relative">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 class="text-base font-semibold text-slate-800">Required Documents</h2>
            <p class="text-sm text-slate-500">{{ viewReqItem?.position_title }}</p>
          </div>
          <button @click="showReqModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
        </div>
        <div class="px-6 py-5 space-y-2">
          <div v-for="req in viewReqItem?.requirements" :key="req.id"
               class="flex items-start gap-2 p-3 rounded-lg border"
               :class="req.pivot?.is_mandatory ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-slate-50'">
            <div>
              <p class="text-sm font-medium text-slate-800">{{ req.name }}</p>
              <p v-if="req.description" class="text-xs text-slate-500">{{ req.description }}</p>
              <p class="text-xs mt-0.5" :class="req.pivot?.is_mandatory ? 'text-red-600 font-semibold' : 'text-slate-400'">
                {{ req.pivot?.is_mandatory ? 'Required' : 'Optional' }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Publish Modal ─────────────────────────────────────────────────────── -->
    <div v-if="showPublishModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-xl relative">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 class="text-base font-semibold text-slate-800">Publish Vacancy</h2>
            <p class="text-sm text-slate-500">{{ publishTarget?.position_title }}</p>
          </div>
          <button @click="closePublish" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
        </div>
        <form @submit.prevent="submitPublish" class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Posting Date *</label>
            <input v-model="publishForm.posting_date" type="date" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <p v-if="errors.posting_date" class="text-red-500 text-xs mt-1">{{ errors.posting_date }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Closing Date *</label>
            <input v-model="publishForm.closing_date" type="date" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            <p v-if="errors.closing_date" class="text-red-500 text-xs mt-1">{{ errors.closing_date }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Publication Type *</label>
            <select v-model="publishForm.publication_type" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="internal">Internal Only</option>
              <option value="external">External (CSC Portal)</option>
              <option value="both">Both</option>
            </select>
          </div>
          <div class="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <button type="button" @click="closePublish" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button type="submit" :disabled="isSubmitting" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              {{ isSubmitting ? 'Publishing…' : 'Publish' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
