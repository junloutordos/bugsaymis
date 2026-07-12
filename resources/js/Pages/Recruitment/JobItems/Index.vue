<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import Swal from 'sweetalert2'
import { TrashIcon } from '@heroicons/vue/24/outline'

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
  draft:     'amber',
  approved:  'blue',
  published: 'green',
  closed:    'red',
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
  submit_to_name:          '',
  submit_to_title:         '',
  contract_start_date:     '',
  contract_end_date:       '',
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

// Plantilla item numbers — one job item can represent several vacancies (e.g.
// "Special Science Teacher I" with 4 plantilla item numbers), tracked as a
// repeater instead of forcing HR to create a separate job item per number.
const plantillaRows = ref([]) // [{ id: number|null, plantilla_item_no: string, status: 'vacant'|'filled' }]
const addPlantillaRow    = () => { plantillaRows.value.push({ id: null, plantilla_item_no: '', status: 'vacant' }) }
const removePlantillaRow = (idx) => { plantillaRows.value.splice(idx, 1) }

// When type changes, clear compensation fields that don't apply
watch(() => form.value.recruitment_type_id, () => {
  if (isPlantilla.value) {
    form.value.daily_rate = ''
    if (!plantillaRows.value.length) addPlantillaRow()
  } else {
    form.value.salary_grade   = ''
    form.value.salary_step    = 1
    form.value.monthly_salary = ''
    plantillaRows.value = []
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
      submit_to_name:          item.submit_to_name          ?? '',
      submit_to_title:         item.submit_to_title         ?? '',
      contract_start_date:     item.contract_start_date     ?? '',
      contract_end_date:       item.contract_end_date       ?? '',
      office_id:               item.office_id               ?? '',
      requirement_ids:         reqIds,
      requirement_mandatory:   reqMand,
    }
    competencyMap.value = deserializeCompetencies(item.competencies)
    plantillaRows.value = (item.plantilla_numbers ?? []).map(p => ({ id: p.id, plantilla_item_no: p.plantilla_item_no, status: p.status }))
  } else {
    form.value = emptyForm()
    competencyMap.value = emptyCompetencies()
    plantillaRows.value = []
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
  const payload = {
    ...form.value,
    competencies: serializeCompetencies(competencyMap.value),
    plantilla_numbers: plantillaRows.value.map(r => r.plantilla_item_no.trim()).filter(Boolean),
  }
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
  const confirmed = await confirmAction({
    title: `${labels[newStatus] ?? newStatus} this item?`,
    confirmText: 'Yes',
  })
  if (!confirmed) return
  router.patch(route('recruitment.job-items.status', item.id), { status: newStatus }, {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Status updated!', timer: 1200, showConfirmButton: false }),
  })
}

// ── Delete ─────────────────────────────────────────────────────────────────────
const deleteItem = async (item) => {
  const confirmed = await confirmAction({
    title: 'Delete this job item?',
    text: 'This cannot be undone.',
    confirmText: 'Delete',
    icon: 'warning',
  })
  if (!confirmed) return
  router.delete(route('recruitment.job-items.destroy', item.id), {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1200, showConfirmButton: false }),
    onError:   (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
  })
}

const fmtSalary = (v) => v ? '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—'

// ── Art card ───────────────────────────────────────────────────────────────────
const regeneratingId = ref(null)
const regenerateArtCard = (item) => {
  regeneratingId.value = item.id
  router.post(route('recruitment.job-items.art-card.regenerate', item.id), {}, {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Art card regenerated!', timer: 1200, showConfirmButton: false }),
    onFinish:  () => { regeneratingId.value = null },
  })
}
</script>

<template>
  <Head title="Job Items — Recruitment" />
  <AdminLayout title="Job Items">
    <div>
      <AppPageHeader title="Job Items">
        <template #actions>
          <AppButton @click="openModal()">+ New Job Item</AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="mb-4 px-4 py-3 rounded-lg bg-success-50 border border-success-100 text-success-700 text-sm">{{ page.props.flash.success }}</div>
      <div v-if="page.props.flash?.error"   class="mb-4 px-4 py-3 rounded-lg bg-danger-50  border border-danger-100  text-danger-600  text-sm">{{ page.props.flash.error }}</div>

      <!-- Filter bar -->
      <AppFilterBar>
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
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!items.data?.length" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Position</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Engagement Type</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">SG / Salary</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Office</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Req.</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="item in items.data" :key="item.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ item.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <div class="font-medium text-slate-800">{{ item.position_title }}</div>
            <div v-if="item.plantilla_numbers?.length" class="text-xs text-slate-400">
              {{ item.plantilla_numbers.length }} item{{ item.plantilla_numbers.length !== 1 ? 's' : '' }}
              · {{ item.plantilla_numbers.filter(p => p.status === 'filled').length }} filled
              · {{ item.plantilla_numbers.filter(p => p.status === 'vacant').length }} vacant
            </div>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ item.recruitment_type?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <template v-if="PLANTILLA_NAMES.includes(item.recruitment_type?.name)">
              <div class="font-medium text-slate-800">SG {{ item.salary_grade ?? '—' }}<span v-if="item.salary_step"> · Step {{ item.salary_step }}</span></div>
              <div class="text-xs text-success-700">{{ fmtSalary(item.monthly_salary) }}/mo</div>
            </template>
            <template v-else>
              <div class="text-xs text-slate-500">Daily Rate</div>
              <div class="font-medium text-success-700">{{ item.daily_rate ? fmtSalary(item.daily_rate) + '/day' : '—' }}</div>
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
            <AppBadge :color="statusColors[item.status] ?? 'slate'">{{ item.status }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1 justify-center flex-wrap">
              <AppButton v-if="item.status === 'draft'" variant="secondary" size="sm" @click="openModal(item)">Edit</AppButton>
              <AppButton v-if="item.status === 'draft'" variant="secondary" size="sm" @click="changeStatus(item, 'approved')">Approve</AppButton>
              <AppButton v-if="item.status === 'approved'" size="sm" @click="openPublish(item)">Publish</AppButton>
              <AppButton v-if="item.status === 'published'" variant="secondary" size="sm" @click="changeStatus(item, 'closed')">Close</AppButton>
              <AppIconButton v-if="item.status === 'draft'" label="Delete" variant="danger" @click="deleteItem(item)"><TrashIcon class="w-4 h-4" /></AppIconButton>
              <!-- Art card buttons hidden until generation is fixed -->
              <template v-if="false">
                <template v-if="item.art_card_generated_at">
                  <AppButton as="a" variant="secondary" size="sm" :href="route('recruitment.job-items.art-card.download', [item.id, 'cover'])">Cover Card</AppButton>
                  <AppButton as="a" variant="secondary" size="sm" :href="route('recruitment.job-items.art-card.download', [item.id, 'detail'])">Detail Card</AppButton>
                </template>
                <AppButton variant="secondary" size="sm" :disabled="regeneratingId === item.id" @click="regenerateArtCard(item)">
                  {{ regeneratingId === item.id ? 'Generating…' : (item.art_card_generated_at ? 'Regenerate Card' : 'Generate Card') }}
                </AppButton>
              </template>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="item in items.data" :key="item.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ item.position_title }}</p>
                <p class="text-xs text-slate-400">{{ item.recruitment_type?.name ?? '—' }} &middot; {{ item.office?.name ?? '—' }}</p>
                <p v-if="item.plantilla_numbers?.length" class="text-xs text-slate-400">
                  {{ item.plantilla_numbers.length }} item{{ item.plantilla_numbers.length !== 1 ? 's' : '' }}
                  · {{ item.plantilla_numbers.filter(p => p.status === 'filled').length }} filled
                  · {{ item.plantilla_numbers.filter(p => p.status === 'vacant').length }} vacant
                </p>
              </div>
              <AppBadge :color="statusColors[item.status] ?? 'slate'">{{ item.status }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">
              <template v-if="PLANTILLA_NAMES.includes(item.recruitment_type?.name)">
                SG {{ item.salary_grade ?? '—' }}<span v-if="item.salary_step"> · Step {{ item.salary_step }}</span> · {{ fmtSalary(item.monthly_salary) }}/mo
              </template>
              <template v-else>
                Daily Rate: {{ item.daily_rate ? fmtSalary(item.daily_rate) + '/day' : '—' }}
              </template>
            </p>
            <button v-if="item.requirements?.length" @click="openReq(item)" class="text-xs text-indigo-600 hover:underline">
              {{ item.requirements.length }} doc{{ item.requirements.length !== 1 ? 's' : '' }}
            </button>
            <div class="flex items-center gap-1 flex-wrap pt-1">
              <AppButton v-if="item.status === 'draft'" variant="secondary" size="sm" @click="openModal(item)">Edit</AppButton>
              <AppButton v-if="item.status === 'draft'" variant="secondary" size="sm" @click="changeStatus(item, 'approved')">Approve</AppButton>
              <AppButton v-if="item.status === 'approved'" size="sm" @click="openPublish(item)">Publish</AppButton>
              <AppButton v-if="item.status === 'published'" variant="secondary" size="sm" @click="changeStatus(item, 'closed')">Close</AppButton>
              <AppIconButton v-if="item.status === 'draft'" label="Delete" variant="danger" @click="deleteItem(item)"><TrashIcon class="w-4 h-4" /></AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No job items found." />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="items.current_page"
            :total-pages="items.last_page"
            :total="items.total"
            @prev="goToPage(items.current_page - 1)"
            @next="goToPage(items.current_page + 1)"
            @page="goToPage"
          />
        </template>
      </AppTable>
    </div>

    <!-- ── Create / Edit Modal ───────────────────────────────────────────────── -->
    <AppModal :show="showModal" :title="editingItem ? 'Edit Job Item' : 'New Job Item'" size="3xl" @close="closeModal">
      <form @submit.prevent="submit" class="space-y-5">

        <!-- Section 1: Classification -->
        <fieldset class="border border-slate-200 rounded-xl p-4">
          <legend class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider px-1">Classification</legend>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">

            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Engagement / Employment Type *</label>
              <select v-model="form.recruitment_type_id" required
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="" disabled>Select engagement type</option>
                <option v-for="t in recruitmentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
              <p v-if="errors.recruitment_type_id" class="text-danger-500 text-xs mt-1">{{ errors.recruitment_type_id }}</p>
              <p class="text-xs text-slate-400 mt-1">This defines the engagement type (Plantilla, COS, JO, GIP, OJT, etc.) and drives the entire selection process.</p>
            </div>

            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Position Title *</label>
              <input v-model="form.position_title" type="text" required
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="errors.position_title" class="text-danger-500 text-xs mt-1">{{ errors.position_title }}</p>
            </div>

            <div v-if="isPlantilla" class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Plantilla Item No.(s) *</label>
              <p class="text-xs text-slate-400 mb-2">Add one row per vacancy this position covers — e.g. 4 item numbers for 4 vacancies of the same position.</p>
              <div class="space-y-2">
                <div v-for="(row, idx) in plantillaRows" :key="idx" class="flex items-center gap-2">
                  <input v-model="row.plantilla_item_no" type="text" placeholder="e.g. PSHS-CRC-T-1-2024"
                         :disabled="row.status === 'filled'"
                         class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 disabled:bg-slate-50 disabled:text-slate-500" />
                  <AppBadge v-if="row.status === 'filled'" color="green">Filled</AppBadge>
                  <AppIconButton v-else label="Remove" variant="danger" size="sm" @click="removePlantillaRow(idx)"><TrashIcon class="w-4 h-4" /></AppIconButton>
                </div>
              </div>
              <button type="button" @click="addPlantillaRow"
                      class="mt-2 inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                + Add Plantilla Item
              </button>
              <p v-if="errors.plantilla_numbers" class="text-danger-500 text-xs mt-1">{{ errors.plantilla_numbers }}</p>
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
          <legend class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider px-1">Compensation</legend>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">

            <template v-if="isPlantilla">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Salary Grade (SG)</label>
                <select v-model="form.salary_grade" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                  <option value="">— Select SG —</option>
                  <option v-for="g in 33" :key="g" :value="g">SG {{ g }}</option>
                </select>
                <p v-if="errors.salary_grade" class="text-danger-500 text-xs mt-1">{{ errors.salary_grade }}</p>
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
                <p v-if="errors.daily_rate" class="text-danger-500 text-xs mt-1">{{ errors.daily_rate }}</p>
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
          <legend class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider px-1">Qualifications</legend>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Education</label>
              <textarea v-model="form.education" rows="2"
                        placeholder="e.g. Bachelor's Degree relevant to the job"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="errors.education" class="text-danger-500 text-xs mt-1">{{ errors.education }}</p>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Experience</label>
              <textarea v-model="form.experience" rows="2"
                        placeholder="e.g. 2 years of relevant work experience"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="errors.experience" class="text-danger-500 text-xs mt-1">{{ errors.experience }}</p>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Training</label>
              <textarea v-model="form.training" rows="2"
                        placeholder="e.g. 4 hours of relevant training"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="errors.training" class="text-danger-500 text-xs mt-1">{{ errors.training }}</p>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Eligibility</label>
              <textarea v-model="form.eligibility" rows="2"
                        placeholder="e.g. RA 1080 (Teacher) / Career Service Professional"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="errors.eligibility" class="text-danger-500 text-xs mt-1">{{ errors.eligibility }}</p>
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

        <!-- Section 3c: Posting / Art Card Details -->
        <fieldset class="border border-slate-200 rounded-xl p-4">
          <legend class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider px-1">Posting Card Details</legend>
          <p class="text-xs text-slate-400 mt-2 mb-3">Used on the auto-generated art card (downloadable for social media / website posting).</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Submit Application To (Name)</label>
              <input v-model="form.submit_to_name" type="text" placeholder="e.g. Engr. Ramil A. Sanchez"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Title / Position</label>
              <input v-model="form.submit_to_title" type="text" placeholder="e.g. Director III"
                     class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <template v-if="!isPlantilla">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Contract Start Date</label>
                <input v-model="form.contract_start_date" type="date"
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Contract End Date</label>
                <input v-model="form.contract_end_date" type="date"
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                <p v-if="errors.contract_end_date" class="text-danger-500 text-xs mt-1">{{ errors.contract_end_date }}</p>
              </div>
            </template>
          </div>
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
          <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
          <AppButton type="submit" :loading="isSubmitting">
            {{ isSubmitting ? 'Saving…' : (editingItem ? 'Update' : 'Create') }}
          </AppButton>
        </div>
      </form>
    </AppModal>

    <!-- ── Requirements View Modal ───────────────────────────────────────────── -->
    <AppModal :show="showReqModal" title="Required Documents" :subtitle="viewReqItem?.position_title" size="md" @close="showReqModal = false">
      <div class="space-y-2">
        <div v-for="req in viewReqItem?.requirements" :key="req.id"
             class="flex items-start gap-2 p-3 rounded-lg border"
             :class="req.pivot?.is_mandatory ? 'border-danger-100 bg-danger-50' : 'border-slate-200 bg-slate-50'">
          <div>
            <p class="text-sm font-medium text-slate-800">{{ req.name }}</p>
            <p v-if="req.description" class="text-xs text-slate-500">{{ req.description }}</p>
            <p class="text-xs mt-0.5" :class="req.pivot?.is_mandatory ? 'text-danger-600 font-semibold' : 'text-slate-400'">
              {{ req.pivot?.is_mandatory ? 'Required' : 'Optional' }}
            </p>
          </div>
        </div>
      </div>
    </AppModal>

    <!-- ── Publish Modal ─────────────────────────────────────────────────────── -->
    <AppModal :show="showPublishModal" title="Publish Vacancy" :subtitle="publishTarget?.position_title" size="md" @close="closePublish">
      <form @submit.prevent="submitPublish" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Posting Date *</label>
          <input v-model="publishForm.posting_date" type="date" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <p v-if="errors.posting_date" class="text-danger-500 text-xs mt-1">{{ errors.posting_date }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Closing Date *</label>
          <input v-model="publishForm.closing_date" type="date" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
          <p v-if="errors.closing_date" class="text-danger-500 text-xs mt-1">{{ errors.closing_date }}</p>
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
          <AppButton type="button" variant="secondary" @click="closePublish">Cancel</AppButton>
          <AppButton type="submit" :loading="isSubmitting">
            {{ isSubmitting ? 'Publishing…' : 'Publish' }}
          </AppButton>
        </div>
      </form>
    </AppModal>
  </AdminLayout>
</template>
