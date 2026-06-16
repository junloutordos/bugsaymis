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
const showPhotoModal = ref(false)
const photoStudent = ref(null)
const photoPreviewSrc = ref(null)
const cropImg = ref(null)
const cropContainerRef = ref(null)
const cropX = ref(0)
const cropY = ref(0)
const cropSize = ref(0)
const displayedImgW = ref(0)
const displayedImgH = ref(0)
const isDragging = ref(false)
const dragStart = ref({ x: 0, y: 0, cx: 0, cy: 0 })
const photoUploading = ref(false)
const photoError = ref(null)

const openPhotoModal = (student) => {
  photoStudent.value = student
  photoPreviewSrc.value = null
  photoError.value = null
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
    if (!container) return
    const rect = container.getBoundingClientRect()
    displayedImgW.value = rect.width
    displayedImgH.value = rect.height
    const minDim = Math.min(rect.width, rect.height)
    cropSize.value = Math.round(minDim * 0.85)
    cropX.value = Math.round((rect.width - cropSize.value) / 2)
    cropY.value = Math.round((rect.height - cropSize.value) / 2)
  })
}

const onDrag = (e) => {
  if (!isDragging.value) return
  if (e.cancelable) e.preventDefault()
  const clientX = e.touches?.[0]?.clientX ?? e.clientX
  const clientY = e.touches?.[0]?.clientY ?? e.clientY
  const maxX = displayedImgW.value - cropSize.value
  const maxY = displayedImgH.value - cropSize.value
  cropX.value = Math.round(Math.min(maxX, Math.max(0, dragStart.value.cx + clientX - dragStart.value.x)))
  cropY.value = Math.round(Math.min(maxY, Math.max(0, dragStart.value.cy + clientY - dragStart.value.y)))
}

const endDrag = () => {
  isDragging.value = false
  window.removeEventListener('mousemove', onDrag)
  window.removeEventListener('mouseup', endDrag)
  window.removeEventListener('touchmove', onDrag)
  window.removeEventListener('touchend', endDrag)
}

const startDrag = (e) => {
  e.preventDefault()
  isDragging.value = true
  const clientX = e.touches?.[0]?.clientX ?? e.clientX
  const clientY = e.touches?.[0]?.clientY ?? e.clientY
  dragStart.value = { x: clientX, y: clientY, cx: cropX.value, cy: cropY.value }
  window.addEventListener('mousemove', onDrag)
  window.addEventListener('mouseup', endDrag)
  window.addEventListener('touchmove', onDrag, { passive: false })
  window.addEventListener('touchend', endDrag)
}

const confirmCrop = async () => {
  if (!cropImg.value || !photoPreviewSrc.value || !photoStudent.value || cropSize.value === 0) return
  photoUploading.value = true
  photoError.value = null
  try {
    const img = cropImg.value
    const scaleX = img.naturalWidth / displayedImgW.value
    const scaleY = img.naturalHeight / displayedImgH.value
    const canvas = document.createElement('canvas')
    canvas.width = 400
    canvas.height = 400
    const ctx = canvas.getContext('2d')
    ctx.drawImage(img, cropX.value * scaleX, cropY.value * scaleY, cropSize.value * scaleX, cropSize.value * scaleY, 0, 0, 400, 400)
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
            <p class="text-xs text-slate-500 text-center">JPG, PNG, or WebP. Will be cropped to a square.</p>
          </div>

          <!-- Crop UI -->
          <div v-else>
            <div class="flex justify-center py-2 overflow-hidden rounded-lg bg-slate-900">
              <div class="relative inline-block select-none" ref="cropContainerRef">
                <img ref="cropImg" :src="photoPreviewSrc" @load="onImgLoad" class="block" style="max-width: 100%; max-height: 280px; display: block;" alt="" />
                <!-- Overlay regions outside crop box -->
                <div class="absolute pointer-events-none bg-black/55" :style="{ top: '0px', left: '0px', right: '0px', height: cropY + 'px' }"></div>
                <div class="absolute pointer-events-none bg-black/55" :style="{ top: (cropY + cropSize) + 'px', left: '0px', right: '0px', bottom: '0px' }"></div>
                <div class="absolute pointer-events-none bg-black/55" :style="{ top: cropY + 'px', left: '0px', width: cropX + 'px', height: cropSize + 'px' }"></div>
                <div class="absolute pointer-events-none bg-black/55" :style="{ top: cropY + 'px', left: (cropX + cropSize) + 'px', right: '0px', height: cropSize + 'px' }"></div>
                <!-- Draggable crop box -->
                <div
                  class="absolute border border-white/90 cursor-move"
                  :style="{ top: cropY + 'px', left: cropX + 'px', width: cropSize + 'px', height: cropSize + 'px' }"
                  @mousedown.prevent="startDrag"
                  @touchstart.prevent="startDrag"
                >
                  <div class="absolute top-0 left-0 w-3 h-3 border-t-2 border-l-2 border-white"></div>
                  <div class="absolute top-0 right-0 w-3 h-3 border-t-2 border-r-2 border-white"></div>
                  <div class="absolute bottom-0 left-0 w-3 h-3 border-b-2 border-l-2 border-white"></div>
                  <div class="absolute bottom-0 right-0 w-3 h-3 border-b-2 border-r-2 border-white"></div>
                </div>
              </div>
            </div>
            <p class="text-xs text-slate-500 mt-2 text-center">Drag the square to position the crop area.</p>
            <div v-if="photoError" class="mt-2 text-xs text-red-600 text-center">{{ photoError }}</div>
          </div>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between gap-2">
          <button v-if="photoPreviewSrc" @click="photoPreviewSrc = null" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium transition-colors">Change Image</button>
          <div v-else></div>
          <div class="flex gap-2">
            <button @click="showPhotoModal = false" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Cancel</button>
            <button v-if="photoPreviewSrc" @click="confirmCrop" :disabled="photoUploading || cropSize === 0" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              {{ photoUploading ? 'Uploading…' : 'Upload Photo' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
