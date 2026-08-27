<script setup>
import { Head, usePage, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import {
  EyeIcon,
  PencilSquareIcon,
  TrashIcon,
  PlusIcon,
  ArrowUpOnSquareIcon,
  BanknotesIcon,
  XMarkIcon,
  IdentificationIcon,
} from "@heroicons/vue/24/outline"
import { useUsers } from "@/Composables/useUsers.js"
import { storageUrl } from "@/Composables/useStorage.js"
import { ref, watch, computed, nextTick } from "vue"
import { useSubmit } from "@/Composables/useSubmit"
import AppButton from "@/Components/AppButton.vue"
import AppCard from "@/Components/AppCard.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppInput from "@/Components/AppInput.vue"
import AppModal from "@/Components/AppModal.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppSelect from "@/Components/AppSelect.vue"
import PaginationControl from "@/Components/PaginationControl.vue"
import { TH, TH_C, TD, TD_MONO, TD_END, TR } from "@/Composables/useTableClasses.js"

const props = defineProps({
  users:        Array,
  roles:        Array,
  divisions:    Array,
  offices:      Array,
  salaryGrades: { type: Array, default: () => [] },
  pageTitle:    { type: String, default: 'Users' },
  headerTitle:  { type: String, default: 'Users List' },
})

const {
  usersList,
  rolesList,
  divisionsList,
  officesList,
  showModal,
  modalMode,
  selectedUser,
  searchQuery,
  currentPage,
  form,
  openModal,
  closeModal,
  submitUser,
  viewUser,
  deleteUser,
  activateUser,
  isEmployeesPage,
  isInactivePage,
} = useUsers(props)

// ── Local filters (employees page only) ───────────────────────────────────────
const filterDivision = ref('')
const filterCategory = ref('')
const filterSex      = ref('')

// Reset page when any filter changes
watch([filterDivision, filterCategory, filterSex, searchQuery], () => { currentPage.value = 1 })

const EMP_CATEGORIES = [
  'Plantilla Teaching',
  'Plantilla Non-Teaching',
  'COS Teaching',
  'COS Non Teaching',
]

const PER_PAGE = 15

const allFiltered = computed(() => {
  const q = searchQuery.value.toLowerCase()
  return usersList.value.filter(u => {
    const status = (u.status || '').toLowerCase()
    if (isInactivePage ? status !== 'inactive' : status === 'inactive') return false
    if (filterDivision.value && String(u.division_id) !== String(filterDivision.value)) return false
    if (filterCategory.value && u.emp_category !== filterCategory.value) return false
    if (filterSex.value && u.sex !== filterSex.value) return false
    if (!q) return true
    return (
      (u.name || '').toLowerCase().includes(q) ||
      (u.email || '').toLowerCase().includes(q) ||
      (u.position || '').toLowerCase().includes(q) ||
      (u.emp_category || '').toLowerCase().includes(q) ||
      (u.division?.division_name || '').toLowerCase().includes(q)
    )
  })
})

const displayedUsers = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return allFiltered.value.slice(start, start + PER_PAGE)
})

const totalPages = computed(() => Math.max(1, Math.ceil(allFiltered.value.length / PER_PAGE)))

// ── Division chief watcher ─────────────────────────────────────────────────────
const divisionChief = ref(null)
watch(
  () => form.value?.division_id,
  (newDivision) => {
    if (!newDivision) { divisionChief.value = null; return }
    const division = divisionsList.value.find(d => d.id === newDivision)
    divisionChief.value = division?.chief ?? null
  }
)

const filteredOffices = computed(() => {
  const divId = form.value?.division_id
  if (!divId) return []
  return officesList.value.filter(o => o.division_id === divId)
})

// ── Signature upload ───────────────────────────────────────────────────────────
const { isSubmitting: isUploading, submit: submitUpload } = useSubmit()

const page     = usePage()
const userRole = page.props.auth?.user?.role?.name ?? null

const rolesMap     = computed(() => Object.fromEntries(rolesList.value.map(r => [String(r.id), r.name])))
const getRoleNames = (user) => {
  if (!user) return '—'
  if (user.role?.name) return user.role.name
  if (user.role_id) return user.role_id.toString().split(',').map(id => rolesMap.value[id.trim()] ?? id.trim()).join(', ')
  return '—'
}

const openSignaturePicker = (user) => {
  const el = document.getElementById('sig-input-' + user.id)
  if (el) el.click()
}

// ── Profile photo crop/upload (ported from Students/Index.vue) ─────────────────
const FRAME = 240        // fixed crop square size in px
const CONTAINER_H = 320  // crop container height in px

const showPhotoModal = ref(false)
const photoTargetUser = ref(null)
const photoPreviewSrc = ref(null)
const cropImg = ref(null)
const cropContainerRef = ref(null)
const photoUploading = ref(false)
const photoError = ref(null)
const photoVersions = ref({})

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

const openPhotoModal = (user) => {
  photoTargetUser.value = user
  photoPreviewSrc.value = null
  photoError.value = null
  imgScale.value = 1
  panX.value = 0
  panY.value = 0
  showPhotoModal.value = true
}

const onPhotoFileSelect = (e) => {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = (ev) => { photoPreviewSrc.value = ev.target.result }
  reader.readAsDataURL(file)
}

const onPhotoImgLoad = () => {
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
const onPhotoPan = (e) => {
  if (!isDragging.value) return
  if (e.cancelable) e.preventDefault()
  const clientX = e.touches?.[0]?.clientX ?? e.clientX
  const clientY = e.touches?.[0]?.clientY ?? e.clientY
  panX.value = Math.max(-maxPanX.value, Math.min(maxPanX.value, dragStart.value.px + clientX - dragStart.value.x))
  panY.value = Math.max(-maxPanY.value, Math.min(maxPanY.value, dragStart.value.py + clientY - dragStart.value.y))
}

const endPhotoPan = () => {
  isDragging.value = false
  window.removeEventListener('mousemove', onPhotoPan)
  window.removeEventListener('mouseup', endPhotoPan)
  window.removeEventListener('touchmove', onPhotoPan)
  window.removeEventListener('touchend', endPhotoPan)
}

const startPhotoPan = (e) => {
  e.preventDefault()
  isDragging.value = true
  const clientX = e.touches?.[0]?.clientX ?? e.clientX
  const clientY = e.touches?.[0]?.clientY ?? e.clientY
  dragStart.value = { x: clientX, y: clientY, px: panX.value, py: panY.value }
  window.addEventListener('mousemove', onPhotoPan)
  window.addEventListener('mouseup', endPhotoPan)
  window.addEventListener('touchmove', onPhotoPan, { passive: false })
  window.addEventListener('touchend', endPhotoPan)
}

const confirmPhotoCrop = async () => {
  if (!cropImg.value || !photoPreviewSrc.value || !photoTargetUser.value) return
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

    const { data } = await axios.post(route('users.photo.update', { user: photoTargetUser.value.id }), { photo_base64: dataUrl })

    photoVersions.value[photoTargetUser.value.id] = Date.now()
    if (selectedUser.value?.id === photoTargetUser.value.id) {
      selectedUser.value = { ...selectedUser.value, profile_picture: data.profile_picture }
    }
    const idx = usersList.value.findIndex(u => u.id === photoTargetUser.value.id)
    if (idx !== -1) usersList.value[idx] = { ...usersList.value[idx], profile_picture: data.profile_picture }

    showPhotoModal.value = false
  } catch {
    photoError.value = 'Failed to upload photo. Please try again.'
  } finally {
    photoUploading.value = false
  }
}

const handleUpload = (user, e) => {
  const file = e.target.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = async (ev) => {
    try {
      await axios.post(`/users/${user.id}/upload-signature`, { signature_base64: ev.target.result })
      window.location.reload()
    } catch (err) {
      alert(err.response?.data?.message ?? 'Failed to upload signature')
    }
  }
  reader.readAsDataURL(file)
}

// ── Salary Grade modal ─────────────────────────────────────────────────────────
const showSgModal = ref(false)
const sgTarget    = ref(null)
const sgForm      = ref({ salary_grade: '', salary_step: 1 })
const sgSaving    = ref(false)

const distinctGrades = computed(() => {
  const grades = [...new Set(props.salaryGrades.map(sg => sg.salary_grade))]
  return grades.sort((a, b) => a - b)
})

const availableSteps = computed(() => {
  if (!sgForm.value.salary_grade) return []
  return props.salaryGrades
    .filter(sg => sg.salary_grade == sgForm.value.salary_grade)
    .map(sg => sg.step)
    .sort((a, b) => a - b)
})

const selectedRate = computed(() => {
  const row = props.salaryGrades.find(
    sg => sg.salary_grade == sgForm.value.salary_grade && sg.step == sgForm.value.salary_step
  )
  return row ? Number(row.monthly_rate).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : null
})

watch(() => sgForm.value.salary_grade, () => {
  // Reset step to first available when grade changes
  const steps = availableSteps.value
  sgForm.value.salary_step = steps.length ? steps[0] : 1
})

function openSgModal(user) {
  sgTarget.value = user
  sgForm.value = {
    salary_grade: user.salary_grade || '',
    salary_step:  user.salary_step  || 1,
  }
  showSgModal.value = true
}

function closeSgModal() {
  showSgModal.value = false
  sgTarget.value    = null
  sgSaving.value    = false
}

function saveSalaryGrade() {
  if (!sgTarget.value || !sgForm.value.salary_grade) return
  sgSaving.value = true
  router.patch(
    route('hr.employees.salary-grade', sgTarget.value.id),
    { salary_grade: sgForm.value.salary_grade, salary_step: sgForm.value.salary_step },
    {
      preserveScroll: true,
      onSuccess: () => {
        const idx = usersList.value.findIndex(u => u.id === sgTarget.value.id)
        if (idx !== -1) {
          usersList.value[idx] = {
            ...usersList.value[idx],
            salary_grade: Number(sgForm.value.salary_grade),
            salary_step:  Number(sgForm.value.salary_step),
          }
        }
        closeSgModal()
      },
      onFinish: () => { sgSaving.value = false },
    }
  )
}

function formatSg(user) {
  if (!user.salary_grade) return '—'
  return `SG ${user.salary_grade} / Step ${user.salary_step ?? 1}`
}
</script>

<template>
  <Head :title="props.pageTitle || 'Users'" />
  <AdminLayout :title="props.pageTitle || 'Users'">
    <div>

      <AppPageHeader :title="props.headerTitle || 'Users List'">
        <template #actions>
          <AppButton v-if="!isInactivePage" @click="openModal('create')">
            <PlusIcon class="h-4 w-4" />
            <span class="hidden sm:inline">{{ isEmployeesPage ? 'New Employee' : 'New User' }}</span>
            <span class="sm:hidden">New</span>
          </AppButton>
        </template>
      </AppPageHeader>

      <AppFilterBar :result-label="`${allFiltered.length} employee${allFiltered.length !== 1 ? 's' : ''}`">
        <AppInput
          v-model="searchQuery"
          label="Search"
          placeholder="Name, position, email..."
          class="min-w-[180px] flex-1"
        />

        <AppSelect v-model="filterDivision" label="Division" placeholder="All Divisions" class="min-w-[170px]">
          <option v-for="d in divisionsList" :key="d.id" :value="d.id">{{ d.division_name }}</option>
        </AppSelect>

        <AppSelect v-model="filterCategory" label="Category" placeholder="All Categories" class="min-w-[170px]">
          <option v-for="cat in EMP_CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
        </AppSelect>

        <AppSelect v-model="filterSex" label="Sex" placeholder="All" class="min-w-[120px]">
          <option value="Male">Male</option>
          <option value="Female">Female</option>
        </AppSelect>

        <template #actions>
          <AppButton
            v-if="filterDivision || filterCategory || filterSex || searchQuery"
            variant="secondary"
            size="sm"
            @click="filterDivision = ''; filterCategory = ''; filterSex = ''; searchQuery = ''"
          >
            <XMarkIcon class="h-3.5 w-3.5" />
            Clear
          </AppButton>
        </template>
      </AppFilterBar>

      <!-- Main card -->
      <AppCard :padded="false">

        <!-- Mobile Cards -->
        <div class="sm:hidden p-4 space-y-3">
          <div v-for="user in displayedUsers" :key="'m-' + user.id" class="border border-slate-100 rounded-lg p-3">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="font-semibold text-slate-800 truncate">{{ user.name }}</p>
                <p class="text-xs text-slate-500 truncate">{{ user.position ?? user.email }}</p>
              </div>
              <div class="flex gap-1 shrink-0">
                <AppIconButton label="View" @click="viewUser(user)">
                  <EyeIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton v-if="!isInactivePage" label="Edit" @click="openModal('edit', user)">
                  <PencilSquareIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton v-if="isEmployeesPage && !isInactivePage" label="Assign Salary Grade" variant="success" @click="openSgModal(user)">
                  <BanknotesIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton v-if="isInactivePage" label="Activate" variant="success" @click="activateUser(user)">
                  <PlusIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton v-else label="Deactivate" variant="danger" @click="deleteUser(user)">
                  <TrashIcon class="w-4 h-4" />
                </AppIconButton>
              </div>
            </div>
            <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-slate-500">
              <span v-if="user.division?.division_name">{{ user.division.division_name }}</span>
              <span v-if="user.emp_category" class="text-indigo-500">{{ user.emp_category }}</span>
              <span v-if="user.salary_grade" class="text-emerald-600">{{ formatSg(user) }}</span>
            </div>
          </div>
          <p v-if="displayedUsers.length === 0" class="py-16 text-center text-slate-400 text-sm">No employees found.</p>
        </div>

        <!-- Desktop Table -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50/80">
              <tr>
                <th :class="TH">#</th>
                <th :class="TH">Name</th>
                <th :class="TH">Sex</th>
                <th v-if="!isEmployeesPage" :class="TH">Email</th>
                <th v-if="isEmployeesPage" :class="TH">Emp. No.</th>
                <th :class="TH">Position</th>
                <th :class="TH">Division</th>
                <th v-if="isEmployeesPage" :class="TH">Category</th>
                <th v-if="isEmployeesPage" :class="TH">Salary Grade</th>
                <th v-if="!isEmployeesPage" :class="TH">Office</th>
                <th v-if="!isEmployeesPage" :class="TH">Created At</th>
                <th :class="TH_C">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="user in displayedUsers" :key="user.id" :class="TR">
                <td :class="TD">{{ user.id }}</td>
                <td class="px-4 py-3 font-medium text-slate-800">{{ user.name }}</td>
                <td :class="TD">
                  <div class="flex items-center gap-1.5">
                    <svg v-if="user.sex === 'Male'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="5">
                      <circle cx="26" cy="38" r="14" /><line x1="36" y1="28" x2="54" y2="10" /><line x1="42" y1="10" x2="54" y2="10" /><line x1="54" y1="10" x2="54" y2="22" />
                    </svg>
                    <svg v-else-if="user.sex === 'Female'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" class="w-4 h-4 text-pink-500" fill="none" stroke="currentColor" stroke-width="5">
                      <circle cx="32" cy="24" r="14" /><line x1="32" y1="38" x2="32" y2="56" /><line x1="22" y1="46" x2="42" y2="46" />
                    </svg>
                    <span class="text-xs text-slate-500">{{ user.sex ?? '—' }}</span>
                  </div>
                </td>
                <td v-if="!isEmployeesPage" :class="TD">{{ user.email }}</td>
                <td v-if="isEmployeesPage" :class="TD_MONO">{{ user.employee_no ?? '—' }}</td>
                <td :class="TD">{{ user.position ?? '—' }}</td>
                <td class="px-4 py-3 text-xs text-slate-700">{{ user.division?.division_name ?? '—' }}</td>
                <td v-if="isEmployeesPage" :class="TD">
                  <span v-if="user.emp_category" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium"
                    :class="user.emp_category?.includes('Teaching') && !user.emp_category?.includes('Non') ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600'">
                    {{ user.emp_category }}
                  </span>
                  <span v-else class="text-slate-400">—</span>
                </td>
                <td v-if="isEmployeesPage" :class="TD">
                  <span v-if="user.salary_grade" class="text-xs font-semibold text-emerald-700">
                    SG {{ user.salary_grade }}
                    <span class="font-normal text-slate-400"> / Step {{ user.salary_step ?? 1 }}</span>
                  </span>
                  <span v-else class="text-slate-400 text-xs">Not set</span>
                </td>
                <td v-if="!isEmployeesPage" :class="TD">{{ user.office?.name ?? user.office ?? '—' }}</td>
                <td v-if="!isEmployeesPage" :class="TD">{{ new Date(user.created_at).toLocaleDateString() }}</td>
                <td :class="TD_END">
                  <div class="flex justify-center gap-1 items-center">
                    <template v-if="!isInactivePage">
                      <AppIconButton label="View" @click="viewUser(user)">
                        <EyeIcon class="w-4 h-4" />
                      </AppIconButton>
                      <AppIconButton label="Edit" @click="openModal('edit', user)">
                        <PencilSquareIcon class="w-4 h-4" />
                      </AppIconButton>
                      <!-- Salary grade (employees page only) -->
                      <AppIconButton v-if="isEmployeesPage" label="Assign / Update Salary Grade" variant="success" @click="openSgModal(user)">
                        <BanknotesIcon class="w-4 h-4" />
                      </AppIconButton>
                      <!-- Print CR-80 ID card (employees page only) -->
                      <a v-if="isEmployeesPage" :href="route('hr.employees.id-card', user.id)" target="_blank" rel="noopener">
                        <AppIconButton label="Print ID Card">
                          <IdentificationIcon class="w-4 h-4" />
                        </AppIconButton>
                      </a>
                      <!-- Signature upload -->
                      <div class="relative">
                        <input :id="'sig-input-' + user.id" type="file" accept=".png,image/png" class="hidden" @change="(e) => handleUpload(user, e)" />
                        <AppIconButton label="Upload signature (PNG)" :disabled="isUploading" @click.prevent="openSignaturePicker(user)">
                          <ArrowUpOnSquareIcon class="w-4 h-4" />
                        </AppIconButton>
                      </div>
                    </template>
                    <template v-if="isInactivePage">
                      <AppIconButton label="Activate user" variant="success" @click="activateUser(user)">
                        <PlusIcon class="w-4 h-4" />
                      </AppIconButton>
                    </template>
                    <template v-else>
                      <AppIconButton label="Deactivate" variant="danger" @click="deleteUser(user)">
                        <TrashIcon class="w-4 h-4" />
                      </AppIconButton>
                    </template>
                  </div>
                </td>
              </tr>
              <tr v-if="displayedUsers.length === 0">
                <td :colspan="isEmployeesPage ? 9 : 9" class="py-16 text-center text-slate-400 text-sm">
                  No employees found matching the selected filters.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <PaginationControl
          :current-page="currentPage"
          :total-pages="totalPages"
          :total="allFiltered.length"
          @prev="currentPage--"
          @next="currentPage++"
          @page="currentPage = $event"
        />
      </AppCard>

      <!-- ── Edit / Create Modal ───────────────────────────────────────────── -->
      <AppModal
        :show="showModal"
        :title="modalMode === 'create' ? (isEmployeesPage ? 'New Employee' : 'New User') : modalMode === 'edit' ? 'Edit User' : 'View User'"
        size="3xl"
        @close="closeModal"
      >
            <!-- VIEW MODE -->
            <div v-if="modalMode === 'view' && selectedUser" class="space-y-2">
              <div class="flex items-center gap-4">
                <div>
                  <p class="text-xs font-medium text-slate-600 mb-1">Profile Picture</p>
                  <div class="w-24 h-24 bg-slate-50 rounded overflow-hidden border border-slate-200">
                    <img v-if="selectedUser.profile_picture"
                      :src="storageUrl(selectedUser.profile_picture.startsWith('profile_pictures/') ? selectedUser.profile_picture : 'profile_pictures/' + selectedUser.profile_picture)"
                      alt="profile" class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full flex items-center justify-center text-slate-400 text-xs">No image</div>
                  </div>
                </div>
                <div>
                  <p class="text-xs font-medium text-slate-600 mb-1">Electronic Signature</p>
                  <div class="w-48 h-16 bg-white rounded overflow-hidden border border-slate-200 flex items-center justify-center">
                    <img v-if="selectedUser.electronic_signature"
                      :src="storageUrl(selectedUser.electronic_signature.startsWith('signatures/') ? selectedUser.electronic_signature : 'signatures/' + selectedUser.electronic_signature)"
                      alt="signature" class="max-h-12" />
                    <div v-else class="text-slate-400 text-xs">No signature</div>
                  </div>
                </div>
              </div>
              <p class="text-sm text-slate-700">Name: <strong>{{ selectedUser.name }}</strong></p>
              <p class="text-sm text-slate-700">Pre-Nominal Title: <strong>{{ selectedUser.prenominal_title ?? '—' }}</strong></p>
              <p class="text-sm text-slate-700">Post-Nominal Title: <strong>{{ selectedUser.postnominal_title ?? '—' }}</strong></p>
              <p class="text-sm text-slate-700">Sex: <strong>{{ selectedUser.sex }}</strong></p>
              <p class="text-sm text-slate-700">Email: <strong>{{ selectedUser.email }}</strong></p>
              <p class="text-sm text-slate-700">Biometric ID: <strong>{{ selectedUser.badge_id ?? '—' }}</strong></p>
              <p class="text-sm text-slate-700">Role: <strong>{{ getRoleNames(selectedUser) }}</strong></p>
              <p class="text-sm text-slate-700">Position: <strong>{{ selectedUser.position ?? '—' }}</strong></p>
              <p class="text-sm text-slate-700">Category: <strong>{{ selectedUser.emp_category ?? '—' }}</strong></p>
              <p class="text-sm text-slate-700">Salary Grade: <strong>{{ formatSg(selectedUser) }}</strong></p>
              <p class="text-sm text-slate-700">Division: <strong>{{ selectedUser.division?.division_name ?? '—' }}</strong></p>
              <p class="text-sm text-slate-700">Division Chief: <strong>{{ selectedUser.division?.chief?.name ?? '—' }}</strong></p>
              <p class="text-sm text-slate-700">Office: <strong>{{ selectedUser.office?.name ?? selectedUser.office ?? '—' }}</strong></p>
              <p class="text-sm text-slate-700">Created At: <strong>{{ new Date(selectedUser.created_at).toLocaleString() }}</strong></p>

              <div v-if="isEmployeesPage" class="flex justify-end gap-2 pt-4">
                <AppButton as="a" :href="route('hr.employees.id-card', selectedUser.id)" target="_blank" variant="secondary">
                  <IdentificationIcon class="w-4 h-4" /> Print ID Card
                </AppButton>
              </div>
            </div>

            <!-- CREATE / EDIT FORM -->
            <form v-else @submit.prevent="submitUser" class="space-y-4 sm:grid sm:grid-cols-2 sm:gap-4 sm:space-y-0">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
                <input v-model="form.name" type="text" required
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Pre-Nominal Title</label>
                <input v-model="form.prenominal_title" type="text" placeholder="e.g. Dr., Engr., Atty." maxlength="50"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Post-Nominal Title</label>
                <input v-model="form.postnominal_title" type="text" placeholder="e.g. Ph.D., CESO III, LPT" maxlength="100"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
              </div>

              <div v-if="!isEmployeesPage">
                <label class="block text-xs font-medium text-slate-600 mb-1">Biometric ID</label>
                <input v-model="form.badge_id" type="text"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Sex</label>
                <select v-model="form.sex" required
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                  <option value="">-- Select Sex --</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>

              <div class="sm:col-span-2" v-if="!isEmployeesPage">
                <label class="block text-xs font-medium text-slate-600 mb-1">Email</label>
                <input v-model="form.email" type="email" required
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Position</label>
                <input v-model="form.position" type="text"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Employee No.</label>
                <input v-model="form.employee_no" type="text" placeholder="e.g. pshscrc13-00123"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Employee ID Number</label>
                <input v-model="form.employee_idno_new" type="text" placeholder="e.g. E13-2020-06-001"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full font-mono" />
                <p class="mt-1 text-[11px] text-slate-400">Printed on the CR-80 ID card. Format: E13-YYYY-MM-XXX.</p>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Specialization</label>
                <input v-model="form.specialization" type="text" placeholder="e.g. Mathematics, Biology"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Division</label>
                <select v-model="form.division_id"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                  <option value="">-- Select Division --</option>
                  <option v-for="d in divisionsList" :key="d.id" :value="d.id">{{ d.division_name }}</option>
                </select>
              </div>

              <div v-if="divisionChief" class="text-sm text-slate-600 sm:col-span-2">
                Division Chief: <strong>{{ divisionChief.name }}</strong>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Office</label>
                <select v-model="form.office_id"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                  <option value="">-- Select Office --</option>
                  <option v-if="!form.division_id" disabled value="">Select a division first</option>
                  <option v-for="o in filteredOffices" :key="o.id" :value="o.id">{{ o.name }}</option>
                  <option v-if="form.division_id && filteredOffices.length === 0" disabled>No offices for this division</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Employee Category</label>
                <select v-model="form.emp_category"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                  <option value="">-- Select Category --</option>
                  <option v-for="cat in EMP_CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
                </select>
              </div>

              <div v-if="modalMode === 'edit'" class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Account Status</label>
                <div class="rounded-xl border p-4 flex items-start gap-3"
                  :class="form.status === 'inactive' ? 'border-red-200 bg-red-50' : 'border-emerald-200 bg-emerald-50'">
                  <div class="flex-1">
                    <p class="text-sm font-medium" :class="form.status === 'inactive' ? 'text-red-800' : 'text-emerald-800'">
                      {{ form.status === 'inactive' ? 'Inactive' : 'Active' }}
                    </p>
                    <p class="text-xs mt-0.5" :class="form.status === 'inactive' ? 'text-red-500' : 'text-emerald-600'">
                      {{ form.status === 'inactive' ? 'This user is inactive.' : 'This user is active.' }}
                    </p>
                  </div>
                  <button type="button" @click="form.status = form.status === 'inactive' ? 'active' : 'inactive'"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                    :class="form.status === 'inactive' ? 'bg-red-400 focus:ring-red-400' : 'bg-emerald-500 focus:ring-emerald-500'">
                    <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform transition-transform"
                      :class="form.status === 'inactive' ? 'translate-x-5' : 'translate-x-0'" />
                  </button>
                </div>
              </div>

              <div v-if="modalMode === 'edit'" class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Profile Photo</label>
                <div class="flex items-center gap-4 rounded-xl border border-slate-200 p-4">
                  <div class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                    <img v-if="selectedUser?.profile_picture"
                      :src="storageUrl(selectedUser.profile_picture.startsWith('profile_pictures/') ? selectedUser.profile_picture : 'profile_pictures/' + selectedUser.profile_picture) + '?v=' + (photoVersions[selectedUser.id] || 0)"
                      alt="Current photo" class="h-full w-full object-cover" />
                    <div v-else class="flex h-full w-full items-center justify-center text-[10px] text-slate-400">No photo</div>
                  </div>
                  <AppButton type="button" variant="secondary" @click="openPhotoModal(selectedUser)">
                    <ArrowUpOnSquareIcon class="h-4 w-4" /> Change Photo
                  </AppButton>
                </div>
              </div>

              <div v-if="modalMode === 'edit'" class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Emergency Contact</label>
                <div class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-2">
                  <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1">Contact Person Name</label>
                    <input v-model="form.emergency_contact_name" type="text"
                      class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                  </div>
                  <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1">Mobile No.</label>
                    <input v-model="form.emergency_contact_phone" type="text"
                      class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-[11px] font-medium text-slate-500 mb-1">Address</label>
                    <input v-model="form.emergency_contact_address" type="text"
                      class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                  </div>
                </div>
              </div>

              <div class="flex justify-end gap-2 pt-4 sm:col-span-2">
                <AppButton type="button" variant="secondary" @click="closeModal">
                  Cancel
                </AppButton>
                <AppButton type="submit">
                  Save
                </AppButton>
              </div>
            </form>
      </AppModal>

      <!-- ── Profile Photo Crop Modal (ported from Students/Index.vue) ───────── -->
      <AppModal :show="showPhotoModal" title="Update Profile Photo" size="sm" @close="showPhotoModal = false">
        <!-- File picker -->
        <div v-if="!photoPreviewSrc" class="flex flex-col items-center gap-4 py-6">
          <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
          </div>
          <label class="cursor-pointer">
            <span class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Choose Photo</span>
            <input type="file" accept="image/*" @change="onPhotoFileSelect" class="sr-only" />
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
            @mousedown.prevent="startPhotoPan"
            @touchstart.prevent="startPhotoPan"
          >
            <img
              ref="cropImg"
              :src="photoPreviewSrc"
              @load="onPhotoImgLoad"
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
              <AppButton v-if="photoPreviewSrc" :disabled="photoUploading" @click="confirmPhotoCrop">
                {{ photoUploading ? 'Uploading…' : 'Save Photo' }}
              </AppButton>
            </div>
          </div>
        </template>
      </AppModal>

      <!-- ── Salary Grade Modal ────────────────────────────────────────────── -->
      <AppModal
        :show="showSgModal"
        title="Assign Salary Grade"
        :subtitle="sgTarget?.name"
        size="sm"
        @close="closeSgModal"
      >
          <div class="space-y-4">
            <!-- Grade select -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Salary Grade <span class="text-red-500">*</span></label>
              <select v-model="sgForm.salary_grade"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">-- Select Grade --</option>
                <option v-for="g in distinctGrades" :key="g" :value="g">SG {{ g }}</option>
              </select>
            </div>

            <!-- Step select -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Step <span class="text-red-500">*</span></label>
              <select v-model="sgForm.salary_step" :disabled="!sgForm.salary_grade"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50">
                <option v-for="s in availableSteps" :key="s" :value="s">Step {{ s }}</option>
              </select>
            </div>

            <!-- Monthly rate preview -->
            <div v-if="selectedRate" class="rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-3">
              <p class="text-xs text-emerald-600 font-medium">Monthly Rate</p>
              <p class="text-lg font-bold text-emerald-700 mt-0.5">₱ {{ selectedRate }}</p>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <AppButton type="button" variant="secondary" @click="closeSgModal">
                Cancel
              </AppButton>
              <AppButton variant="success" :disabled="!sgForm.salary_grade || sgSaving" @click="saveSalaryGrade">
                {{ sgSaving ? 'Saving…' : 'Save' }}
              </AppButton>
            </div>
          </div>
      </AppModal>

    </div>
  </AdminLayout>
</template>
