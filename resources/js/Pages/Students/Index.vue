<script setup>
import { Head, usePage, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { ref, onMounted, computed, watch } from 'vue'
import { EyeIcon } from "@heroicons/vue/24/outline"

const props = defineProps({
  students: Object,
  columns: Array,
  editing: Number,
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

const profilePic = (student) => {
  if (!student) return null
  const fname = student.img ?? student.image ?? student.photo ?? null
  if (!fname) return null
  // public storage path (storage/app/public -> public/storage)
  return `/storage/students_profile_picture/${encodeURIComponent(fname)}`
}

</script>

<template>
  <Head title="Students" />
  <AdminLayout title="Students">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Students</h1>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <div class="flex items-center justify-between mb-4">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search students..."
            class="w-1/3 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div class="hidden md:block overflow-x-auto">
          <table class="min-w-full border">
            <thead class="bg-gray-100 text-sm text-gray-700">
              <tr>
                <th class="px-4 py-2">#</th>
                <th v-for="vf in visibleFields" :key="vf.label" class="px-4 py-2 uppercase">{{ vf.label }}</th>
                <th class="px-4 py-2 uppercase">ACTIONS</th>
              </tr>
            </thead>
            <tbody class="text-sm divide-y">
              <tr v-for="student in filteredStudents" :key="student.id">
                <td class="px-4 py-2">{{ student.id }}</td>
                <td v-for="vf in visibleFields" :key="vf.label" class="px-4 py-2">
                  <span v-if="vf.type === 'age'">{{ getAge(student, vf.keys) }}</span>
                  <span v-else>
                    <span v-if="vf.label === 'Last Name' || vf.label === 'First Name' || vf.label === 'Middle Name'">
                      {{ (getFieldValue(student, vf.keys) ?? '').toString().toUpperCase() }}
                    </span>
                    <span v-else>{{ getFieldValue(student, vf.keys) }}</span>
                  </span>
                </td>
                <td class="px-4 py-2">
                  <button @click="openView(student)" class="p-1 hover:bg-gray-100 rounded" title="View">
                    <EyeIcon class="w-5 h-5 text-blue-600" />
                  </button>
                </td>
              </tr>
              <tr v-if="filteredStudents.length === 0"><td :colspan="visibleFields.length + 2" class="px-4 py-6 text-center text-gray-500">No students</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile: card list for small screens -->
        <div class="md:hidden grid gap-4">
          <div v-for="student in filteredStudents" :key="student.id" class="bg-white p-4 rounded-lg shadow">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-sm text-gray-600">ID: {{ student.id }}</div>
                <div class="font-medium text-gray-800 mt-1">{{ (getFieldValue(student, ['last_name','lastname','lname']) ?? '').toString().toUpperCase() }}, {{ (getFieldValue(student, ['first_name','firstname','fname']) ?? '').toString().toUpperCase() }}</div>
                <div class="text-xs text-gray-500 mt-1">PISAY ID: {{ getFieldValue(student, ['pisaysystemid','pisaysystemID','pisaysystem_id','pisay_system_id','pisay_id']) }}</div>
                <div class="text-xs text-gray-500">Age: {{ getAge(student, ['birthday','birthdate','dob']) }} • Sex: {{ getFieldValue(student, ['sex','gender']) }}</div>
              </div>
              <div class="flex items-center gap-2">
                <button @click="openView(student)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" title="View">
                  <EyeIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
          <div v-if="filteredStudents.length === 0" class="text-center text-gray-500 py-6">No students</div>
        </div>

        <!-- Simple pagination controls -->
        <div class="mt-4 flex items-center justify-between">
          <div class="text-sm text-gray-600">Page {{ currentPage }} of {{ lastPage }}</div>
          <div class="space-x-2">
            <button @click.prevent="goTo(prevUrl)" :disabled="!prevUrl" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
            <button @click.prevent="goTo(nextUrl)" :disabled="!nextUrl" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>

      <!-- Modal -->
      <div v-show="showModal" class="fixed inset-0 flex items-start sm:items-center justify-center py-8 sm:py-0 bg-black bg-opacity-50 z-50 overflow-auto">
        <div class="bg-white rounded p-4 w-full max-w-full sm:max-w-2xl max-h-[90vh] overflow-auto shadow-lg">
          <h3 class="text-lg font-semibold mb-4">{{ editing ? 'Edit Student' : 'New Student' }}</h3>
          <form :action="editing ? route('students.update', editing) : route('students.store')" method="POST">
            <input type="hidden" name="_method" :value="editing ? 'PUT' : 'POST'" />
            <input type="hidden" name="_token" :value="csrfToken" />

            <div class="max-h-[65vh] overflow-auto pr-2">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div v-for="col in columns" :key="col.Field">
                  <label class="block text-sm font-medium mb-1">{{ col.Field }}</label>
                  <input :name="col.Field" v-model="form[col.Field]" type="text" class="mt-1 block w-full rounded border-gray-300" />
                </div>
              </div>
            </div>

            <div class="flex justify-end mt-4 space-x-2">
              <button type="button" @click="showModal = false" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
            </div>
          </form>
        </div>
      </div>
      <!-- View Modal -->
      <div v-show="showViewModal" class="fixed inset-0 flex items-start sm:items-center justify-center py-8 sm:py-0 bg-black bg-opacity-50 z-50 overflow-auto">
        <div class="bg-white rounded p-4 w-full max-w-full sm:max-w-2xl max-h-[90vh] overflow-auto shadow-lg">
          <div class="flex items-start justify-between">
            <h3 class="text-lg font-semibold mb-4">Student Details</h3>
            <button @click="closeView" class="text-gray-500 hover:text-gray-800">✕</button>
          </div>

          <div class="max-h-[75vh] overflow-auto pr-2">
            <div class="mb-4 flex items-center gap-4">
              <div v-if="profilePic(viewStudent)">
                <img :src="profilePic(viewStudent)" alt="Profile" class="w-32 h-32 object-cover rounded border" />
              </div>
              <div v-else class="w-32 h-32 bg-gray-100 rounded border flex items-center justify-center text-sm text-gray-500">No photo</div>
              <div class="flex-1">
                <div class="text-sm text-gray-700 font-semibold">{{ viewStudent ? ((viewStudent.last_name ?? viewStudent.lastname ?? viewStudent.lname ?? '') + ', ' + (viewStudent.first_name ?? viewStudent.firstname ?? viewStudent.fname ?? '') + (viewStudent.middle_name ? ' ' + (viewStudent.middle_name ?? viewStudent.middlename ?? viewStudent.mname) : '')) : '—' }}</div>
                <div class="text-xs text-gray-500">PISAY ID: {{ viewStudent ? (viewStudent.pisaysystemID ?? viewStudent.pisay_system_id ?? viewStudent.pisay_id ?? viewStudent.pisayid ?? '—') : '—' }}</div>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div v-for="col in columns" :key="'view-'+col.Field">
                <label class="block text-sm font-medium mb-1">{{ col.Field }}</label>
                <div class="mt-1 block w-full rounded border border-gray-200 bg-gray-50 p-2 text-sm">
                  {{ viewStudent ? (viewStudent[col.Field] ?? '—') : '—' }}
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end mt-4">
            <button @click="closeView" class="px-4 py-2 bg-gray-300 rounded">Close</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
