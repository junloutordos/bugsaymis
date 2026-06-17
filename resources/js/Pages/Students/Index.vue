<script setup>
import { Head, usePage, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import { EyeIcon } from "@heroicons/vue/24/outline"
import { storageUrl } from "@/Composables/useStorage.js"

const props = defineProps({
  students: Object,
  columns: Array,
  editing: Number,
  can_manage_students: Boolean,
})

const students = ref(props.students?.data ?? props.students ?? [])
const columns = ref(props.columns || [])
const showModal = ref(false)
const form = ref({})
const editing = ref(props.editing ?? null)
const showViewModal = ref(false)
const viewStudent = ref(null)
const page = usePage()
const csrfToken = ref(page.props.csrf_token ?? page.props.csrfToken ?? null)

const searchQuery = ref(page.props.q ?? '')

// Server-driven list; filteredStudents reflects server paginator data
const filteredStudents = computed(() => students.value)

let searchTimer = null
watch(searchQuery, (val) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    router.get(route('students.index'), { q: val }, { replace: true, preserveState: false })
  }, 400)
})

onMounted(() => {
  if (!csrfToken.value && typeof document !== 'undefined') {
    const m = document.querySelector('meta[name=csrf-token]')
    csrfToken.value = m ? m.getAttribute('content') : csrfToken.value
  }
})

// initialize form with column keys
const initForm = (record = {}) => {
  form.value = {}
  columns.value.forEach(c => {
    const field = c.Field ?? c.field ?? c.name
    if (!field) return
    form.value[field] = record[field] ?? ''
  })
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
const visibleFields = [
  { label: 'PISAYSYSTEMID', keys: ['pisaysystemid','pisaysystemID','pisaysystem_id','pisay_system_id','pisay_id'] },
  { label: 'Last Name', keys: ['last_name','lastname','lname'] },
  { label: 'First Name', keys: ['first_name','firstname','fname'] },
  { label: 'Middle Name', keys: ['middle_name','middlename','mname'] },
  { label: 'AGE', keys: ['birthday','birthdate','dob'], type: 'age' },
  { label: 'Sex', keys: ['sex','gender'] },
]

const getFieldValue = (student, keys) => {
  for (const k of keys) {
    if (student && (student[k] !== undefined && student[k] !== null && student[k] !== '')) return student[k]
  }
  return '—'
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
const imgDispW = computed(() => cropImg.value ? Math.round(cropImg.value.naturalWidth  * effectiveScale.value) : 0)
const imgDispH = computed(() => cropImg.value ? Math.round(cropImg.value.naturalHeight * effectiveScale.value) : 0)
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
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Students</h1>
          <p class="text-sm text-slate-500">Browse and view student records</p>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search students..."
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64"
        />
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Desktop table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th v-for="vf in visibleFields" :key="vf.label" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">{{ vf.label }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="student in filteredStudents" :key="student.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ student.id }}</td>
                <td v-for="vf in visibleFields" :key="vf.label" class="px-4 py-3 text-sm text-slate-700">
                  <span v-if="vf.type === 'age'">{{ getAge(student, vf.keys) }}</span>
                  <span v-else>
                    <span v-if="vf.label === 'Last Name' || vf.label === 'First Name' || vf.label === 'Middle Name'">
                      {{ (getFieldValue(student, vf.keys) ?? '').toString().toUpperCase() }}
                    </span>
                    <span v-else>{{ getFieldValue(student, vf.keys) }}</span>
                  </span>
                </td>
                <td class="px-4 py-3">
                  <button @click="openView(student)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="View">
                    <EyeIcon class="w-4 h-4" />
                  </button>
                </td>
              </tr>
              <tr v-if="filteredStudents.length === 0">
                <td :colspan="visibleFields.length + 2" class="py-16 text-center text-slate-400 text-sm">No students found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile card list -->
        <div class="md:hidden divide-y divide-slate-100">
          <div v-for="student in filteredStudents" :key="student.id" class="p-4">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-xs text-slate-500">ID: {{ student.id }}</div>
                <div class="font-medium text-slate-800 mt-0.5">{{ (getFieldValue(student, ['last_name','lastname','lname']) ?? '').toString().toUpperCase() }}, {{ (getFieldValue(student, ['first_name','firstname','fname']) ?? '').toString().toUpperCase() }}</div>
                <div class="text-xs text-slate-500 mt-1">PISAY ID: {{ getFieldValue(student, ['pisaysystemid','pisaysystemID','pisaysystem_id','pisay_system_id','pisay_id']) }}</div>
                <div class="text-xs text-slate-500">Age: {{ getAge(student, ['birthday','birthdate','dob']) }} · Sex: {{ getFieldValue(student, ['sex','gender']) }}</div>
              </div>
              <button @click="openView(student)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="View">
                <EyeIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
          <div v-if="filteredStudents.length === 0" class="py-16 text-center text-slate-400 text-sm">No students found.</div>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ lastPage }}</span>
          <div class="flex gap-2">
            <button @click.prevent="goTo(prevUrl)" :disabled="!prevUrl" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
            <button @click.prevent="goTo(nextUrl)" :disabled="!nextUrl" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
          </div>
        </div>
      </div>

      <!-- Edit/Create Modal -->
      <div v-if="showModal" class="fixed inset-0 flex items-start sm:items-center justify-center py-8 sm:py-0 bg-slate-900/50 z-50 overflow-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-full sm:max-w-2xl max-h-[90vh] overflow-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800">{{ editing ? 'Edit Student' : 'New Student' }}</h3>
            <button @click="showModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
          </div>
          <form :action="editing ? route('students.update', editing) : route('students.store')" method="POST" class="px-6 py-5">
            <input type="hidden" name="_method" :value="editing ? 'PUT' : 'POST'" />
            <input type="hidden" name="_token" :value="csrfToken" />

            <div class="max-h-[55vh] overflow-auto pr-1">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div v-for="col in columns" :key="col.Field">
                  <label class="block text-xs font-medium text-slate-600 mb-1">{{ col.Field }}</label>
                  <input :name="col.Field" v-model="form[col.Field]" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
                </div>
              </div>
            </div>

            <div class="flex justify-end mt-6 gap-2">
              <button type="button" @click="showModal = false" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
              <button type="submit" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Save</button>
            </div>
          </form>
        </div>
      </div>

      <!-- View Modal -->
      <div v-if="showViewModal" class="fixed inset-0 flex items-start sm:items-center justify-center py-8 sm:py-0 bg-slate-900/50 z-50 overflow-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-full sm:max-w-2xl max-h-[90vh] overflow-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800">Student Details</h3>
            <button @click="closeView" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
          </div>

          <div class="px-6 py-5 max-h-[70vh] overflow-auto">
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
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-3">
            <a v-if="viewStudent" :href="route('students.id-card', viewStudent.id)" target="_blank" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Print ID Card</a>
            <button @click="closeView" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Photo Crop Modal -->
    <div v-if="showPhotoModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/60 z-[60]">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-base font-semibold text-slate-800">Update Photo</h3>
          <button @click="showPhotoModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
          </button>
        </div>

        <div class="px-6 py-5">
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
                  height: imgDispH + 'px',
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
            <div v-if="photoError" class="mt-2 text-xs text-red-600 text-center">{{ photoError }}</div>
          </div>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between gap-2">
          <button v-if="photoPreviewSrc" @click="photoPreviewSrc = null" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium transition-colors">Change Image</button>
          <div v-else></div>
          <div class="flex gap-2">
            <button @click="showPhotoModal = false" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Cancel</button>
            <button v-if="photoPreviewSrc" @click="confirmCrop" :disabled="photoUploading" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              {{ photoUploading ? 'Uploading…' : 'Upload Photo' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
