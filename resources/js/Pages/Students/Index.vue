<script setup>
import { Head, usePage, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { ref, onMounted, computed, nextTick, watch } from 'vue'
import { EyeIcon, PencilSquareIcon } from "@heroicons/vue/24/outline"
import { storageUrl } from "@/Composables/useStorage.js"
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTabs from '@/Components/AppTabs.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({
  students: Object,
  columns: Array,
  writable_columns: { type: Array, default: () => [] },
  editing: Number,
  can_manage_students: Boolean,
  tab: { type: String, default: 'active' },
  filters: { type: Object, default: () => ({}) },
  tab_counts: { type: Object, default: () => ({ active: 0, inactive: 0 }) },
  section_options: { type: Array, default: () => [] },
  grade_options: { type: Array, default: () => [] },
  current_school_year: String,
})

const students = ref(props.students?.data ?? props.students ?? [])
const columns = ref(props.columns || [])
const showModal = ref(false)
const form = ref({})
const originalForm = ref({})
const formError = ref('')
const editing = ref(props.editing ?? null)
const showViewModal = ref(false)
const viewStudent = ref(null)
const page = usePage()
const csrfToken = ref(page.props.csrf_token ?? page.props.csrfToken ?? null)

const searchQuery = ref(page.props.q ?? '')
const activeTab = ref(props.tab ?? 'active')
const filterSectionId = ref(props.filters?.section_id ?? '')
const filterGradeLevel = ref(props.filters?.grade_level ?? '')
const filterSex = ref(props.filters?.sex ?? '')

const tabs = computed(() => [
  { key: 'active', label: `Active (${props.tab_counts?.active ?? 0})` },
  { key: 'inactive', label: `Inactive (${props.tab_counts?.inactive ?? 0})` },
])

// Server-driven list; filteredStudents reflects server paginator data
const filteredStudents = computed(() => students.value)

const applyFilters = ({ resetPage = true } = {}) => {
  router.get(route('students.index'), {
    q: searchQuery.value || undefined,
    tab: activeTab.value,
    section_id: filterSectionId.value || undefined,
    grade_level: filterGradeLevel.value || undefined,
    sex: filterSex.value || undefined,
    ...(resetPage ? {} : { page: page.props.students?.current_page }),
  }, { replace: true, preserveState: false })
}

const performSearch = () => applyFilters()

let searchDebounceTimer = null
watch(searchQuery, () => {
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => applyFilters(), 400)
})

watch(activeTab, () => applyFilters())
watch([filterSectionId, filterGradeLevel, filterSex], () => applyFilters())


const writableColumnSet = computed(() => new Set(props.writable_columns))
const editableColumns = computed(() => columns.value.filter(c => {
  const field = c.Field ?? c.field ?? c.name
  return field && writableColumnSet.value.has(field)
}))

const dateFields = new Set(['birthday', 'dateofgraduation', 'mbirthday', 'fbirthday'])
const emailFields = new Set(['student_email', 'memailaddress', 'femailaddress'])
const numberFields = new Set(['noofgraduates', 'average', 'math', 'verbal', 'science', 'abtract'])
const inputType = (field) => dateFields.has(field) ? 'date' : emailFields.has(field) ? 'email' : numberFields.has(field) ? 'number' : 'text'
const fieldLabel = (field) => field
  .replace(/([a-z])([A-Z])/g, '$1 $2')
  .replaceAll('_', ' ')
  .replace(/\b\w/g, char => char.toUpperCase())

const isSaving = ref(false)
const submitEdit = () => {
  formError.value = ''
  const changes = Object.fromEntries(
    Object.entries(form.value).filter(([field, value]) => String(value ?? '') !== String(originalForm.value[field] ?? ''))
  )

  if (Object.keys(changes).length === 0) {
    formError.value = 'No changes to save.'
    return
  }

  isSaving.value = true
  router.put(route('students.update', editing.value), changes, {
    onFinish: () => { isSaving.value = false },
    onSuccess: () => {
      const index = students.value.findIndex(student => student.id === editing.value)
      if (index !== -1) students.value[index] = { ...students.value[index], ...changes }
      if (viewStudent.value?.id === editing.value) viewStudent.value = { ...viewStudent.value, ...changes }
      originalForm.value = { ...form.value }
      showModal.value = false
      formError.value = ''
    },
    onError: (errors) => { formError.value = Object.values(errors)[0] ?? 'Unable to update the student.' },
  })
}

onMounted(() => {
  if (!csrfToken.value && typeof document !== 'undefined') {
    const m = document.querySelector('meta[name=csrf-token]')
    csrfToken.value = m ? m.getAttribute('content') : csrfToken.value
  }
})

// initialize form with column keys
const initForm = (record = {}) => {
  form.value = {}
  props.writable_columns.forEach(field => {
    form.value[field] = record[field] ?? ''
  })
  originalForm.value = { ...form.value }
  formError.value = ''
}

if (editing.value && students.value.length > 0) {
  initForm(students.value[0])
} else {
  initForm()
}

const openCreate = () => { editing.value = null; initForm(); showModal.value = true }
const openEdit = (student) => { editing.value = student.id; initForm(student); showModal.value = true }
const openView = (student) => { viewStudent.value = student; showViewModal.value = true }
const closeView = () => { viewStudent.value = null; showViewModal.value = false }

// Only show these student fields in the table
const visibleFields = computed(() => [
  { label: 'PISAYSYSTEMID', keys: ['pisaysystemid','pisaysystemID','pisaysystem_id','pisay_system_id','pisay_id'] },
  { label: 'Name', keys: [], type: 'name' },
  { label: 'Official Email', keys: ['student_email','email'], type: 'email' },
  { label: 'AGE', keys: ['birthday','birthdate','dob'], type: 'age' },
  { label: 'Sex', keys: ['sex','gender'] },
  ...(activeTab.value === 'active'
    ? [{ label: 'Grade & Section', keys: [], type: 'section' }]
    : []),
])

const sectionLabel = (student) => {
  const grade = student?.current_grade_level
  const section = student?.current_section_name
  if (!grade && !section) return '—'
  return `${grade ? `Grade ${grade}` : ''}${grade && section ? ' — ' : ''}${section ?? ''}`.trim() || '—'
}

const getFieldValue = (student, keys) => {
  for (const k of keys) {
    if (student && (student[k] !== undefined && student[k] !== null && student[k] !== '')) return student[k]
  }
  return '—'
}

// LASTNAME, FIRSTNAME M. — same format as the ID card
const formatName = (student) => {
  const last = getFieldValue(student, ['lastname','last_name','lname'])
  const first = getFieldValue(student, ['firstname','first_name','fname'])
  const middle = getFieldValue(student, ['middlename','middle_name','mname'])
  const mi = middle !== '—' ? ` ${middle.toString().trim().charAt(0).toUpperCase()}.` : ''
  if (last === '—' && first === '—') return '—'
  return `${last === '—' ? '' : last}, ${first === '—' ? '' : first}${mi}`.toUpperCase()
}

const OFFICIAL_DOMAIN = '@crc.pshs.edu.ph'
const emailOf = (student) => {
  const raw = getFieldValue(student, ['student_email','email'])
  if (raw === '—') return null
  const email = raw.toString().trim().toLowerCase()
  // treat junk placeholders as blank
  if (!email || !email.includes('@') || ['n/a','none','na','-'].includes(email)) return null
  return email
}
const isOfficialEmail = (student) => {
  const email = emailOf(student)
  return !!email && email.endsWith(OFFICIAL_DOMAIN)
}

const getAge = (student, keys) => {
  const val = getFieldValue(student, keys)
  if (!val || val === '—') return '—'
  // try parse date
  const d = new Date(val)
  if (isNaN(d)) {
    // try alternative formats (e.g., YYYY-mm-dd stored differently)
    const parsed = Date.parse(val)
    if (isNaN(parsed)) return val
    d.setTime(parsed)
  }
  const today = new Date()
  let age = today.getFullYear() - d.getFullYear()
  const m = today.getMonth() - d.getMonth()
  if (m < 0 || (m === 0 && today.getDate() < d.getDate())) {
    age--
  }
  return age >= 0 ? `${age}` : '—'
}

const pager = computed(() => page.props.students || null)
const students_total = computed(() => pager.value?.total ?? filteredStudents.value.length)
const prevUrl = computed(() => pager.value?.prev_page_url ?? null)
const nextUrl = computed(() => pager.value?.next_page_url ?? null)
const currentPage = computed(() => pager.value?.current_page ?? null)
const lastPage = computed(() => pager.value?.last_page ?? null)

const goTo = (url) => { if (!url) return; window.location.href = url }

// Photo versions keyed by student id — bumped after a successful upload to cache-bust the proxy URL
const photoVersions = ref({})

const profilePic = (student) => {
  if (!student?.img) return null
  const img = student.img
  // New uploads are stored as S3 keys containing '/'; legacy filenames have no '/'
  if (img.includes('/')) {
    const v = photoVersions.value[student.id]
    return route('students.photo', { id: student.id }) + (v ? `?v=${v}` : '')
  }
  return storageUrl(`students_profile_picture/${encodeURIComponent(img)}`)
}

// ── Photo crop modal ──────────────────────────────────────────────
const FRAME = 240        // fixed crop square size in px
const CONTAINER_H = 320  // crop container height in px

const showPhotoModal = ref(false)
const photoStudent = ref(null)
const photoPreviewSrc = ref(null)
const cropImg = ref(null)
const cropContainerRef = ref(null)
const photoUploading = ref(false)
const photoError = ref(null)

// Zoom & pan state
const imgScale  = ref(1)    // user zoom multiplier (1 = just covers frame)
const minScale  = ref(1)    // base: image just covers frame at this value
const panX      = ref(0)    // image-center offset from container-center, px
const panY      = ref(0)
const containerW = ref(400)
const isDragging = ref(false)
const dragStart  = ref({ x: 0, y: 0, px: 0, py: 0 })

const effectiveScale = computed(() => minScale.value * imgScale.value)
const imgDispW = computed(() => cropImg.value ? Math.round(cropImg.value.naturalWidth * effectiveScale.value) : 0)
const imgDispH = computed(() => {
  if (!cropImg.value || !imgDispW.value) return 0
  const { naturalWidth, naturalHeight } = cropImg.value
  return naturalWidth > 0 ? Math.round(imgDispW.value * naturalHeight / naturalWidth) : 0
})
const imgLeft  = computed(() => Math.round(containerW.value / 2 + panX.value - imgDispW.value / 2))
const imgTop   = computed(() => Math.round(CONTAINER_H      / 2 + panY.value - imgDispH.value / 2))
const frameLeft = computed(() => Math.round((containerW.value - FRAME) / 2))
const frameTop  = computed(() => Math.round((CONTAINER_H     - FRAME) / 2))
const maxPanX  = computed(() => Math.max(0, (imgDispW.value - FRAME) / 2))
const maxPanY  = computed(() => Math.max(0, (imgDispH.value - FRAME) / 2))

const clampPan = () => {
  panX.value = Math.max(-maxPanX.value, Math.min(maxPanX.value, panX.value))
  panY.value = Math.max(-maxPanY.value, Math.min(maxPanY.value, panY.value))
}

const openPhotoModal = (student) => {
  photoStudent.value = student
  photoPreviewSrc.value = null
  photoError.value = null
  imgScale.value = 1
  panX.value = 0
  panY.value = 0
  showPhotoModal.value = true
}

const onFileSelect = (e) => {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = (ev) => { photoPreviewSrc.value = ev.target.result }
  reader.readAsDataURL(file)
}

const onImgLoad = () => {
  nextTick(() => {
    const container = cropContainerRef.value
    const img = cropImg.value
    if (!container || !img) return
    containerW.value = container.clientWidth
    minScale.value = Math.max(FRAME / img.naturalWidth, FRAME / img.naturalHeight)
    imgScale.value = 1
    panX.value = 0
    panY.value = 0
  })
}

// ── Pan (drag image within the fixed frame) ───────────────────────
const onPan = (e) => {
  if (!isDragging.value) return
  if (e.cancelable) e.preventDefault()
  const clientX = e.touches?.[0]?.clientX ?? e.clientX
  const clientY = e.touches?.[0]?.clientY ?? e.clientY
  panX.value = Math.max(-maxPanX.value, Math.min(maxPanX.value, dragStart.value.px + clientX - dragStart.value.x))
  panY.value = Math.max(-maxPanY.value, Math.min(maxPanY.value, dragStart.value.py + clientY - dragStart.value.y))
}

const endPan = () => {
  isDragging.value = false
  window.removeEventListener('mousemove', onPan)
  window.removeEventListener('mouseup', endPan)
  window.removeEventListener('touchmove', onPan)
  window.removeEventListener('touchend', endPan)
}

const startPan = (e) => {
  e.preventDefault()
  isDragging.value = true
  const clientX = e.touches?.[0]?.clientX ?? e.clientX
  const clientY = e.touches?.[0]?.clientY ?? e.clientY
  dragStart.value = { x: clientX, y: clientY, px: panX.value, py: panY.value }
  window.addEventListener('mousemove', onPan)
  window.addEventListener('mouseup', endPan)
  window.addEventListener('touchmove', onPan, { passive: false })
  window.addEventListener('touchend', endPan)
}

const confirmCrop = async () => {
  if (!cropImg.value || !photoPreviewSrc.value || !photoStudent.value) return
  photoUploading.value = true
  photoError.value = null
  try {
    const img = cropImg.value
    const eff  = effectiveScale.value
    // Frame center in natural-image coordinates
    const natCX   = img.naturalWidth  / 2 - panX.value / eff
    const natCY   = img.naturalHeight / 2 - panY.value / eff
    const natSize = FRAME / eff
    const canvas  = document.createElement('canvas')
    canvas.width  = 400
    canvas.height = 400
    canvas.getContext('2d').drawImage(img, natCX - natSize / 2, natCY - natSize / 2, natSize, natSize, 0, 0, 400, 400)
    const dataUrl = canvas.toDataURL('image/jpeg', 0.85)

    const { data } = await axios.post(route('students.photo.update', { id: photoStudent.value.id }), { photo_base64: dataUrl })

    photoVersions.value[photoStudent.value.id] = Date.now()
    if (viewStudent.value?.id === photoStudent.value.id) {
      viewStudent.value = { ...viewStudent.value, img: data.img }
    }
    const idx = students.value.findIndex(s => s.id === photoStudent.value.id)
    if (idx !== -1) students.value[idx] = { ...students.value[idx], img: data.img }

    showPhotoModal.value = false
  } catch {
    photoError.value = 'Failed to upload photo. Please try again.'
  } finally {
    photoUploading.value = false
  }
}

</script>

<template>
  <Head title="Students" />
  <AdminLayout title="Students">
    <div class="space-y-5">
      <AppPageHeader title="Students" subtitle="Browse and view student records" />

      <AppTabs v-model="activeTab" :tabs="tabs" />

      <AppFilterBar :result-label="`${students_total} student${students_total === 1 ? '' : 's'}`">
        <AppInput v-model="searchQuery" type="text" placeholder="Search name, email, LRN, PISAY ID..." @keydown.enter="performSearch" class="w-full sm:w-64" />
        <AppSelect v-model="filterSectionId" placeholder="All Sections" class="w-full sm:w-44">
          <option v-for="sec in section_options" :key="sec.id" :value="sec.id">Grade {{ sec.levelid }} — {{ sec.sectionname }}</option>
        </AppSelect>
        <AppSelect v-model="filterGradeLevel" placeholder="All Grades" class="w-full sm:w-36">
          <option v-for="g in grade_options" :key="g" :value="g">Grade {{ g }}</option>
        </AppSelect>
        <AppSelect v-model="filterSex" placeholder="All Sexes" class="w-full sm:w-32">
          <option value="M">Male</option>
          <option value="F">Female</option>
        </AppSelect>
        <template #actions>
          <AppButton size="sm" @click="performSearch">Search</AppButton>
        </template>
      </AppFilterBar>

      <AppTable :is-empty="filteredStudents.length === 0" :skeleton-cols="visibleFields.length + 2">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th v-for="vf in visibleFields" :key="vf.label" class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">{{ vf.label }}</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="student in filteredStudents" :key="student.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ student.id }}</td>
          <td v-for="vf in visibleFields" :key="vf.label" class="px-4 py-3 text-sm text-slate-700">
            <span v-if="vf.type === 'age'">{{ getAge(student, vf.keys) }}</span>
            <span v-else-if="vf.type === 'name'" class="font-medium text-slate-800">{{ formatName(student) }}</span>
            <span v-else-if="vf.type === 'section'">{{ sectionLabel(student) }}</span>
            <span v-else-if="vf.type === 'email'">
              <span v-if="!emailOf(student)" class="text-slate-400">—</span>
              <span v-else-if="isOfficialEmail(student)">{{ emailOf(student) }}</span>
              <span v-else class="text-warning-600" title="Not an official @crc.pshs.edu.ph email">{{ emailOf(student) }}</span>
            </span>
            <span v-else>{{ getFieldValue(student, vf.keys) }}</span>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <AppIconButton label="View" @click="openView(student)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton v-if="can_manage_students" label="Edit" @click="openEdit(student)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="student in filteredStudents" :key="student.id" class="p-4">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-xs text-slate-500">ID: {{ student.id }}</div>
                <div class="font-medium text-slate-800 mt-0.5">{{ formatName(student) }}</div>
                <div class="text-xs text-slate-500 mt-1">PISAY ID: {{ getFieldValue(student, ['pisaysystemid','pisaysystemID','pisaysystem_id','pisay_system_id','pisay_id']) }}</div>
                <div class="text-xs mt-0.5" :class="!emailOf(student) ? 'text-slate-400' : (isOfficialEmail(student) ? 'text-slate-500' : 'text-warning-600')">{{ emailOf(student) ?? 'No email' }}</div>
                <div class="text-xs text-slate-500">Age: {{ getAge(student, ['birthday','birthdate','dob']) }} · Sex: {{ getFieldValue(student, ['sex','gender']) }}</div>
                <div v-if="activeTab === 'active'" class="text-xs text-slate-500">{{ sectionLabel(student) }}</div>
              </div>
              <div class="flex items-center gap-1">
                <AppIconButton label="View" @click="openView(student)">
                  <EyeIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton v-if="can_manage_students" label="Edit" @click="openEdit(student)">
                  <PencilSquareIcon class="w-4 h-4" />
                </AppIconButton>
              </div>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No students found" />
        </template>

        <template #footer>
          <div class="flex items-center justify-between px-4 py-3 text-sm text-slate-600">
            <span>Page {{ currentPage }} of {{ lastPage }}</span>
            <div class="flex gap-2">
              <AppButton size="sm" variant="secondary" :disabled="!prevUrl" @click.prevent="goTo(prevUrl)">Prev</AppButton>
              <AppButton size="sm" variant="secondary" :disabled="!nextUrl" @click.prevent="goTo(nextUrl)">Next</AppButton>
            </div>
          </div>
        </template>
      </AppTable>

      <!-- Edit Modal -->
      <AppModal :show="showModal" title="Edit Student" size="2xl" @close="showModal = false">
        <div class="max-h-[55vh] overflow-auto pr-1">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <AppInput
              v-for="col in editableColumns"
              :key="col.Field"
              v-model="form[col.Field]"
              :label="fieldLabel(col.Field)"
              :type="inputType(col.Field)"
            />
          </div>
        </div>

        <p v-if="formError" class="mt-3 text-sm text-danger-600">{{ formError }}</p>

        <template #footer>
          <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
          <AppButton :loading="isSaving" @click="submitEdit">Save Changes</AppButton>
        </template>
      </AppModal>

      <!-- View Modal -->
      <AppModal :show="showViewModal" title="Student Details" size="2xl" @close="closeView">
        <div class="mb-5 flex items-center gap-4">
          <div class="flex flex-col items-center gap-1.5">
            <div v-if="profilePic(viewStudent)">
              <img :src="profilePic(viewStudent)" alt="Profile" class="w-24 h-24 object-cover rounded-xl border border-slate-200" />
            </div>
            <div v-else class="w-24 h-24 bg-slate-100 rounded-xl border border-slate-200 flex items-center justify-center text-xs text-slate-500">No photo</div>
            <button v-if="props.can_manage_students" @click="openPhotoModal(viewStudent)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium transition-colors">Change Photo</button>
          </div>
          <div class="flex-1">
            <div class="text-sm font-semibold text-slate-800">{{ viewStudent ? ((viewStudent.last_name ?? viewStudent.lastname ?? viewStudent.lname ?? '') + ', ' + (viewStudent.first_name ?? viewStudent.firstname ?? viewStudent.fname ?? '') + (viewStudent.middle_name ? ' ' + (viewStudent.middle_name ?? viewStudent.middlename ?? viewStudent.mname) : '')) : '—' }}</div>
            <div class="text-xs text-slate-500 mt-1">PISAY ID: {{ viewStudent ? (viewStudent.pisaysystemID ?? viewStudent.pisay_system_id ?? viewStudent.pisay_id ?? viewStudent.pisayid ?? '—') : '—' }}</div>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div v-for="col in columns" :key="'view-'+col.Field">
            <label class="block text-xs font-medium text-slate-600 mb-1">{{ col.Field }}</label>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
              {{ viewStudent ? (viewStudent[col.Field] ?? '—') : '—' }}
            </div>
          </div>
        </div>

        <template #footer>
          <AppButton v-if="can_manage_students && viewStudent" variant="secondary" @click="openEdit(viewStudent); closeView()">
            <PencilSquareIcon class="w-4 h-4" /> Edit
          </AppButton>
          <AppButton v-if="viewStudent" as="a" :href="route('students.id-card', viewStudent.id)" target="_blank" @click="closeView">Print ID Card</AppButton>
          <AppButton variant="secondary" @click="closeView">Close</AppButton>
        </template>
      </AppModal>

      <!-- Photo Crop Modal -->
      <AppModal :show="showPhotoModal" title="Update Photo" size="sm" @close="showPhotoModal = false">
        <!-- File picker -->
        <div v-if="!photoPreviewSrc" class="flex flex-col items-center gap-4 py-6">
          <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
          </div>
          <label class="cursor-pointer">
            <span class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Choose Photo</span>
            <input type="file" accept="image/*" @change="onFileSelect" class="sr-only" />
          </label>
          <p class="text-xs text-slate-500 text-center">JPG, PNG, or WebP.</p>
        </div>

        <!-- Crop UI -->
        <div v-else>
          <!-- Crop container: image pans freely under the fixed square frame -->
          <div
            ref="cropContainerRef"
            class="relative overflow-hidden rounded-lg bg-slate-900 select-none"
            :class="isDragging ? 'cursor-grabbing' : 'cursor-grab'"
            :style="{ height: CONTAINER_H + 'px' }"
            @mousedown.prevent="startPan"
            @touchstart.prevent="startPan"
          >
            <img
              ref="cropImg"
              :src="photoPreviewSrc"
              @load="onImgLoad"
              class="absolute pointer-events-none"
              :style="{
                width: imgDispW + 'px',
                maxWidth: 'none',
                left: imgLeft + 'px',
                top: imgTop + 'px',
              }"
              alt=""
            />
            <!-- Fixed square frame: box-shadow darkens everything outside it -->
            <div
              class="absolute pointer-events-none"
              :style="{
                top: frameTop + 'px',
                left: frameLeft + 'px',
                width: FRAME + 'px',
                height: FRAME + 'px',
                boxShadow: '0 0 0 9999px rgba(0,0,0,0.55)',
                border: '2px solid rgba(255,255,255,0.75)',
              }"
            ></div>
          </div>

          <!-- Zoom slider -->
          <div class="mt-3 flex items-center gap-3 px-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="6"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
            </svg>
            <input
              type="range"
              v-model.number="imgScale"
              :min="1"
              :max="3"
              step="0.001"
              @input="clampPan"
              class="w-full h-1.5 accent-indigo-600 cursor-pointer"
            />
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="6"/><path stroke-linecap="round" d="M21 21l-4.35-4.35M11 8v6M8 11h6"/>
            </svg>
          </div>
          <p class="text-xs text-slate-500 mt-1.5 text-center">Drag to reposition · slider to zoom</p>
          <div v-if="photoError" class="mt-2 text-xs text-danger-600 text-center">{{ photoError }}</div>
        </div>

        <template #footer>
          <div class="flex w-full items-center justify-between gap-2">
            <AppButton v-if="photoPreviewSrc" variant="secondary" @click="photoPreviewSrc = null">Change Image</AppButton>
            <div v-else></div>
            <div class="flex gap-2">
              <AppButton variant="secondary" @click="showPhotoModal = false">Cancel</AppButton>
              <AppButton v-if="photoPreviewSrc" :loading="photoUploading" @click="confirmCrop">Upload Photo</AppButton>
            </div>
          </div>
        </template>
      </AppModal>
    </div>
  </AdminLayout>
</template>
