<template>
  <Head title="Guidance Consultations" />
  <AdminLayout :title="'Guidance Consultations'">
    <template #default>
      <div>
        <div class="mb-4 flex items-center justify-between">
          <h1 class="text-xl md:text-2xl font-bold text-gray-800">Guidance Consultations</h1>
          <button v-if="canRefer" @click.prevent="openReferralModal" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Add Referral</button>
        </div>

        <div class="mb-4">
          <input v-model="q" type="text" placeholder="Search consultations..." class="w-full md:w-1/3 rounded-lg border-gray-300 p-2" />
        </div>

        <div class="bg-white shadow rounded overflow-x-auto">
          <table class="min-w-full divide-y w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left w-12">#</th>
                <th class="px-4 py-3 text-left">Requestor</th>
                <th class="px-4 py-3 text-left">Concern</th>
                <th class="px-4 py-3 text-left hidden md:table-cell">Referred By</th>
                <th class="px-4 py-3 text-left hidden md:table-cell">Referral Category</th>
                <th class="px-4 py-3 text-left hidden md:table-cell">Behavior Spotted</th>
                <th class="px-4 py-3 text-left hidden md:table-cell">Description</th>
                <th class="px-4 py-3 text-left hidden md:table-cell">Preferred</th>
                <th class="px-4 py-3 text-left hidden md:table-cell">Assigned</th>
                <th class="px-4 py-3 text-left">Status</th>
                  <th class="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in paged" :key="c.id" class="border-t">
                <td class="px-4 py-3">{{ c.id }}</td>
                <td class="px-4 py-3">{{ c.requestor_name || c.requestor_id || '—' }}</td>
                <td class="px-4 py-3">{{ c.concern }}</td>
                <td class="px-4 py-3 hidden md:table-cell">{{ c.referred_by_name || c.referred_by || '—' }}</td>
                <td class="px-4 py-3 hidden md:table-cell">
                  <div class="max-w-[20rem] truncate">{{ c.referral_category || '—' }}</div>
                </td>
                <td class="px-4 py-3 hidden md:table-cell">
                  <div class="max-w-[24rem] truncate">{{ c.behavior_spotted || '—' }}</div>
                  <button v-if="c.behavior_spotted && c.behavior_spotted.length > 120" @click.prevent="openDesc(c.behavior_spotted)" class="text-sm text-blue-600 mt-1">View</button>
                </td>
                <td class="px-4 py-3 hidden md:table-cell">
                  <div class="max-w-[28rem] truncate">{{ c.brief_description || c.description || '—' }}</div>
                  <button v-if="(c.brief_description || c.description) && (c.brief_description || c.description).length > 120" @click.prevent="openDesc(c.brief_description || c.description)" class="text-sm text-blue-600 mt-1">View</button>
                </td>
                <td class="px-4 py-3 hidden md:table-cell">{{ formatDate(c.date_time_preferred) }}</td>
                <td class="px-4 py-3 hidden md:table-cell">{{ c.assigned_personnel_name || '—' }}</td>
                <td class="px-4 py-3">{{ c.status }}</td>
                  <td class="px-4 py-3">
                    <div class="flex gap-2">
                        <button v-if="canAssign && String(c.status).toLowerCase() === 'pending'" @click.prevent="openAssign(c)" class="p-2 rounded-full bg-indigo-100 hover:bg-indigo-200 text-indigo-700" :title="'Update Appointment for #'+c.id" aria-label="Update Appointment">
                          <ClockIcon class="h-5 w-5" />
                      </button>
                        <button v-if="canAssign && String(c.status).toLowerCase() === 'scheduled'" @click.prevent="openIntervention(c)" class="p-2 rounded-full bg-green-100 hover:bg-green-200 text-green-700" :title="'Add Intervention for #'+c.id" aria-label="Add Intervention">
                          <HeartIcon class="h-5 w-5" />
                      </button>
                        <button v-if="canAssign && c.status === 'For Follow-up'" @click.prevent="openEditIntervention(c)" class="p-2 rounded-full bg-yellow-100 hover:bg-yellow-200 text-yellow-700 flex items-center justify-center" :title="'Update Intervention for #'+c.id" aria-label="Update Intervention">
                          <PencilIcon class="h-5 w-5" />
                      </button>
                        <span v-if="c.status === 'Done Intervention'" class="p-2 rounded-full bg-green-100 text-green-700 flex items-center justify-center" :title="'Done'" aria-hidden="true">
                          <CheckCircleIcon class="h-5 w-5" />
                      </span>
                      <!-- Print icon for specified statuses -->
                        <button v-if="['For Follow-up','For Monitoring','Done Intervention','Refer to School Psychologist'].includes(c.status)" @click.prevent="openAdmissionSlip(c)" class="p-2 rounded-full bg-white text-gray-700" :title="'Print Admission Slip'" aria-label="Print Admission Slip">
                          <PrinterIcon class="h-5 w-5" />
                      </button>
                    </div>
                  </td>
              </tr>
              <tr v-if="filtered.length === 0">
                <td class="px-4 py-6 text-center text-gray-500" colspan="11">No consultations found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-4 flex items-center justify-between">
          <div>Page {{ page }} of {{ totalPages }}</div>
          <div>
            <button @click="prev" :disabled="page===1" class="px-3 py-1 rounded bg-gray-200 mr-2">Prev</button>
            <button @click="next" :disabled="page===totalPages" class="px-3 py-1 rounded bg-gray-200">Next</button>
          </div>
        </div>
      </div>

      <!-- Add Referral modal -->
      <div v-if="showReferralModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
          <h2 class="text-lg font-semibold mb-4">Student Referral to Guidance Office</h2>

          <div class="mb-4 relative">
            <label class="block text-sm font-medium text-gray-700">Student</label>
            <input
              v-model="studentSearchQuery"
              type="text"
              placeholder="Search student name..."
              class="mt-1 block w-full rounded border-gray-300 p-2"
              autocomplete="off"
            />
            <div v-if="searchingStudents" class="text-xs text-gray-500 mt-1">Searching...</div>
            <div v-if="studentSearchResults.length > 0" class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded shadow max-h-56 overflow-y-auto">
              <button
                v-for="student in studentSearchResults"
                :key="student.id"
                type="button"
                @click="selectStudent(student)"
                class="w-full text-left px-3 py-2 hover:bg-gray-100"
              >
                <div class="font-medium text-sm">{{ student.name }}</div>
                <div v-if="student.pisay" class="text-xs text-gray-500">{{ student.pisay }}</div>
              </button>
            </div>
            <div v-if="selectedStudent" class="text-xs text-green-700 mt-2">Selected: {{ selectedStudent.name }}</div>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Referral Category</label>
            <div class="space-y-2">
              <label v-for="category in referralCategoryOptions" :key="category" class="flex items-center gap-2">
                <input type="checkbox" :value="category" v-model="referralForm.referral_category" />
                <span>{{ category }}</span>
              </label>
            </div>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Brief Description</label>
            <textarea v-model="referralForm.brief_description" rows="4" class="mt-1 block w-full rounded border-gray-300 p-2"></textarea>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Behavior Spotted</label>
            <div class="space-y-2 max-h-56 overflow-y-auto border rounded p-3">
              <label v-for="behavior in behaviorOptions" :key="behavior" class="flex items-start gap-2">
                <input type="checkbox" :value="behavior" v-model="referralForm.behavior_spotted" class="mt-1" />
                <span class="text-sm">{{ behavior }}</span>
              </label>
            </div>
            <div v-if="showBehaviorOtherInput" class="mt-3">
              <label class="block text-sm font-medium text-gray-700">Others (please specify)</label>
              <input v-model="referralForm.behavior_other" type="text" class="mt-1 block w-full rounded border-gray-300 p-2" />
            </div>
          </div>

          <div class="flex justify-end gap-2">
            <button @click="closeReferralModal" :disabled="loadingReferral" class="px-4 py-2 rounded border">Cancel</button>
            <button @click.prevent="submitReferral" :disabled="loadingReferral" class="px-4 py-2 bg-blue-600 text-white rounded flex items-center gap-2">
              <svg v-if="loadingReferral" class="w-4 h-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
              <span>{{ loadingReferral ? 'Saving...' : 'Save Referral' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Description modal -->
      <div v-if="showDescModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6">
          <h2 class="text-lg font-semibold mb-4">Description</h2>
          <div class="mb-4 whitespace-pre-wrap">{{ descModalText }}</div>
          <div class="flex justify-end">
            <button @click="closeDesc" class="px-4 py-2 rounded border">Close</button>
          </div>
        </div>
      </div>

      <!-- Assign modal -->
      <div v-if="showAssignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
          <h2 class="text-lg font-semibold mb-4">Update Appointment</h2>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Assigned Date & Time</label>
            <input type="datetime-local" v-model="assignDateTime" class="mt-1 block w-full rounded border-gray-300 p-2" />
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Class Adviser (Faculty)</label>
            <select v-model="adviserId" class="mt-1 block w-full rounded border-gray-300 p-2">
              <option :value="null">-- select adviser (optional) --</option>
              <option v-for="f in facultyUsers" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div class="flex justify-end gap-2">
            <button @click="closeAssign" :disabled="loadingAssign" class="px-4 py-2 rounded border">Cancel</button>
            <button @click.prevent="submitAssign" :disabled="loadingAssign" class="px-4 py-2 bg-blue-600 text-white rounded flex items-center gap-2">
              <svg v-if="loadingAssign" class="w-4 h-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
              <span>{{ loadingAssign ? 'Saving...' : 'Save' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Intervention modal -->
      <div v-if="showInterventionModal" class="intervention-modal fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
          <h2 class="text-lg font-semibold mb-4">Record Intervention</h2>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Intervention Details</label>
            <textarea v-model="interventionText" rows="6" class="mt-1 block w-full rounded border-gray-300 p-2"></textarea>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <select v-model="interventionStatus" class="mt-1 block w-full rounded border-gray-300 p-2">
              <option value="">-- select status --</option>
              <option>For Follow-up</option>
              <option>For Monitoring</option>
              <option>Done Intervention</option>
              <option>Refer to School Psychologist</option>
            </select>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Class Adviser (Faculty)</label>
            <select v-model="interventionTeacherId" class="mt-1 block w-full rounded border-gray-300 p-2">
              <option :value="null">-- select adviser (optional) --</option>
              <option v-for="f in facultyUsers" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div v-if="interventionStatus === 'For Follow-up'" class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Follow-up Date</label>
            <input type="date" v-model="interventionFollowupDate" class="mt-1 block w-full rounded border-gray-300 p-2" />
          </div>
          <div class="flex justify-end gap-2">
            <button @click="closeIntervention" :disabled="loadingIntervention" class="px-4 py-2 rounded border">Cancel</button>
            <button @click.prevent="submitIntervention" :disabled="loadingIntervention" class="px-4 py-2 bg-green-600 text-white rounded flex items-center gap-2">
              <svg v-if="loadingIntervention" class="w-4 h-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
              <span>{{ loadingIntervention ? 'Saving...' : 'Save Intervention' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Edit Intervention modal (separate, not nested) -->
      <div v-if="showEditInterventionModal" class="edit-intervention-modal fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
          <h2 class="text-lg font-semibold mb-4">Update Intervention</h2>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Previous Intervention Details</label>
            <textarea v-model="editInterventionText" rows="6" class="mt-1 block w-full rounded border-gray-300 p-2"></textarea>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <select v-model="editInterventionStatus" class="mt-1 block w-full rounded border-gray-300 p-2">
              <option value="">-- select status --</option>
              <option>For Follow-up</option>
              <option>For Monitoring</option>
              <option>Done Intervention</option>
              <option>Refer to School Psychologist</option>
            </select>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Class Adviser (Faculty)</label>
            <select v-model="editInterventionTeacherId" class="mt-1 block w-full rounded border-gray-300 p-2">
              <option :value="null">-- select adviser (optional) --</option>
              <option v-for="f in facultyUsers" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div v-if="editInterventionStatus === 'For Follow-up'" class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Follow-up Date</label>
            <input type="date" v-model="editInterventionFollowupDate" class="mt-1 block w-full rounded border-gray-300 p-2" />
          </div>
          <div class="flex justify-end gap-2">
            <button @click="closeEditIntervention" :disabled="loadingEditIntervention" class="px-4 py-2 rounded border">Cancel</button>
            <button @click.prevent="submitEditIntervention" :disabled="loadingEditIntervention" class="px-4 py-2 bg-yellow-600 text-white rounded flex items-center gap-2">
              <svg v-if="loadingEditIntervention" class="w-4 h-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
              <span>{{ loadingEditIntervention ? 'Saving...' : 'Save Changes' }}</span>
            </button>
          </div>
        </div>
      </div>
    </template>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import Swal from 'sweetalert2'
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ClockIcon, HeartIcon, PencilIcon, CheckCircleIcon, PrinterIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ consultations: Array })
const list = ref(props.consultations || [])
// keep faculty list sorted alphabetically by name
const _faculty = (usePage().props.facultyUsers || []).map(u => ({ id: u.id, name: u.name }))
_faculty.sort((a, b) => (a.name || '').localeCompare(b.name || ''))
const facultyUsers = ref(_faculty)
const q = ref('')
const page = ref(1)
const perPage = 10

const pageProps = usePage().props
const roleName = pageProps.auth?.user?.role?.name || ''
const roleNames = pageProps.auth?.user?.roleNames || []
const canAssign = (roleName === 'Administrator' || roleName === 'Guidance' || (Array.isArray(roleNames) && (roleNames.includes('Administrator') || roleNames.includes('Guidance'))))
const canRefer = (Array.isArray(roleNames) && (roleNames.includes('Administrator') || roleNames.includes('Guidance') || roleNames.includes('Faculty') || roleNames.includes('Staff')))

const referralCategoryOptions = ['Academic', 'Behavior', 'Personal / Social']
const behaviorOptions = [
  'Depressed or apathetic mood',
  'Expression of helplessness, hopelessness, or worthlessness',
  'Evidence of crying',
  'Verbal expressions or gestures of suicide',
  'Noticeable changes in mood and/or sudden outbursts',
  'Inappropriate or exaggerated emotional reactions to situations, including lack of emotional response to stressful events',
  'Excessive dependency on others or extreme withdrawal and isolation from others',
  'Excessive activity or talkativeness',
  'Unusual or noticeable changes in interaction patterns with friends or classmates',
  'New or continuous behavior that disrupts the class',
  'Noticeable changes in physical appearance (weight, dress, hygiene)',
  'Extremely poor academic performance or a drastic decline in grades',
  'Others (please specify)',
]
const behaviorOthersLabel = 'Others (please specify)'

const showReferralModal = ref(false)
const studentSearchQuery = ref('')
const studentSearchResults = ref([])
const selectedStudent = ref(null)
const searchingStudents = ref(false)
const loadingReferral = ref(false)
let studentSearchTimer = null

const referralForm = ref({
  requestor_id: null,
  referral_category: [],
  behavior_spotted: [],
  behavior_other: '',
  brief_description: '',
})

const showBehaviorOtherInput = computed(() => referralForm.value.behavior_spotted.includes(behaviorOthersLabel))

const showAssignModal = ref(false)
const selectedConsultation = ref(null)
const assignDateTime = ref('')
const adviserId = ref(null)
const loadingAssign = ref(false)

const showDescModal = ref(false)
const descModalText = ref('')

// Intervention modal state
const showInterventionModal = ref(false)
const selectedInterventionConsultation = ref(null)
const interventionText = ref('')
const interventionStatus = ref('')
const interventionFollowupDate = ref('')
const interventionTeacherId = ref(null)
const loadingIntervention = ref(false)

// Edit intervention state
const showEditInterventionModal = ref(false)
const editSelectedConsultation = ref(null)
const editInterventionText = ref('')
const editInterventionStatus = ref('')
const editInterventionFollowupDate = ref('')
const editInterventionTeacherId = ref(null)
const loadingEditIntervention = ref(false)

function openDesc(text) { descModalText.value = text; showDescModal.value = true }
function closeDesc() { showDescModal.value = false; descModalText.value = '' }

function openAssign(c) {
  selectedConsultation.value = c
  assignDateTime.value = c.date_time_assigned ? new Date(c.date_time_assigned).toISOString().slice(0,16) : ''
  adviserId.value = null
  showAssignModal.value = true
}

function closeAssign() { showAssignModal.value = false; selectedConsultation.value = null; assignDateTime.value = '' }

function resetReferralForm() {
  studentSearchQuery.value = ''
  studentSearchResults.value = []
  selectedStudent.value = null
  referralForm.value = {
    requestor_id: null,
    referral_category: [],
    behavior_spotted: [],
    behavior_other: '',
    brief_description: '',
  }
}

function openReferralModal() {
  resetReferralForm()
  showReferralModal.value = true
}

function closeReferralModal() {
  showReferralModal.value = false
  resetReferralForm()
}

function selectStudent(student) {
  selectedStudent.value = student
  referralForm.value.requestor_id = student.id
  studentSearchQuery.value = student.name
  studentSearchResults.value = []
}

async function fetchStudentSearch() {
  const term = (studentSearchQuery.value || '').trim()
  if (term.length < 2) {
    studentSearchResults.value = []
    return
  }

  try {
    searchingStudents.value = true
    const url = `${route('guidance.students.search')}?q=${encodeURIComponent(term)}`
    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    const data = await response.json().catch(() => ({}))
    if (response.ok) {
      studentSearchResults.value = data.students || []
    } else {
      studentSearchResults.value = []
    }
  } catch (error) {
    studentSearchResults.value = []
  } finally {
    searchingStudents.value = false
  }
}

watch(studentSearchQuery, (value) => {
  if (selectedStudent.value && value !== selectedStudent.value.name) {
    selectedStudent.value = null
    referralForm.value.requestor_id = null
  }

  if (studentSearchTimer) clearTimeout(studentSearchTimer)
  studentSearchTimer = setTimeout(() => {
    fetchStudentSearch()
  }, 250)
})

watch(showBehaviorOtherInput, (visible) => {
  if (!visible) {
    referralForm.value.behavior_other = ''
  }
})

async function submitReferral() {
  if (loadingReferral.value) return

  if (!referralForm.value.requestor_id) {
    Swal.fire({ icon: 'warning', text: 'Please select a student.' })
    return
  }
  if (!referralForm.value.referral_category.length) {
    Swal.fire({ icon: 'warning', text: 'Please select at least one referral category.' })
    return
  }
  if (!referralForm.value.brief_description || !referralForm.value.brief_description.trim()) {
    Swal.fire({ icon: 'warning', text: 'Please enter a brief description.' })
    return
  }
  if (!referralForm.value.behavior_spotted.length) {
    Swal.fire({ icon: 'warning', text: 'Please select at least one behavior spotted.' })
    return
  }
  if (showBehaviorOtherInput.value && (!referralForm.value.behavior_other || !referralForm.value.behavior_other.trim())) {
    Swal.fire({ icon: 'warning', text: 'Please specify the Other behavior.' })
    return
  }

  loadingReferral.value = true
  try {
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    const response = await fetch(route('guidance.referrals.store'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': token,
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        requestor_id: referralForm.value.requestor_id,
        referral_category: referralForm.value.referral_category,
        behavior_spotted: referralForm.value.behavior_spotted,
        behavior_other: referralForm.value.behavior_other,
        brief_description: referralForm.value.brief_description,
      }),
    })

    const data = await response.json().catch(() => ({}))
    if (!response.ok) {
      Swal.fire({ icon: 'error', text: data.message || 'Failed to save referral.' })
      return
    }

    Swal.fire({ icon: 'success', title: data.message || 'Referral saved.', timer: 1300, showConfirmButton: false })
    closeReferralModal()
    router.reload({ only: ['consultations'], preserveScroll: true })
  } catch (error) {
    Swal.fire({ icon: 'error', text: 'Unexpected error while saving referral.' })
  } finally {
    loadingReferral.value = false
  }
}

async function openIntervention(c) {
  // debug log to confirm click handler fires
  console.log('openIntervention() called for consultation id=', c?.id)
  console.log('showInterventionModal before =', showInterventionModal.value)
  selectedInterventionConsultation.value = c
  interventionText.value = ''
  interventionStatus.value = ''
  interventionFollowupDate.value = ''
  interventionTeacherId.value = c.teacher || null
  showInterventionModal.value = true
  await nextTick()
  console.log('showInterventionModal after =', showInterventionModal.value)
  // check if modal element exists in DOM
  setTimeout(() => {
    const found = document.querySelector('.intervention-modal')
    console.log('intervention modal element found?', !!found, found)
  }, 80)
}

async function openEditIntervention(c) {
  editSelectedConsultation.value = c
  editInterventionText.value = ''
  editInterventionStatus.value = ''
  editInterventionFollowupDate.value = ''
  showEditInterventionModal.value = true
  // fetch decrypted intervention and current status/followup
  try {
    const url = route('guidance.consultations.intervention.get', c.id)
    const resp = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
    const data = await resp.json().catch(() => ({}))
    if (resp.ok) {
      editInterventionText.value = data.intervention || ''
      editInterventionStatus.value = data.status || ''
      editInterventionFollowupDate.value = data.followup_date || ''
      editInterventionTeacherId.value = data.consultation?.teacher || null
    } else {
      showEditInterventionModal.value = false
      Swal.fire({ icon: 'error', text: data.message || 'Failed to load intervention.' })
    }
  } catch (e) {
    showEditInterventionModal.value = false
    console.error(e)
    Swal.fire({ icon: 'error', text: 'Unexpected error while loading intervention.' })
  }
}

function closeEditIntervention() { showEditInterventionModal.value = false; editSelectedConsultation.value = null; editInterventionText.value = ''; editInterventionStatus.value = ''; editInterventionFollowupDate.value = ''; editInterventionTeacherId.value = null }

async function submitEditIntervention() {
  if (loadingEditIntervention.value) return
  if (!editInterventionText.value || !editInterventionStatus.value) {
    Swal.fire({ icon: 'warning', text: 'Please enter intervention details and select a status.' })
    return
  }
  if (editInterventionStatus.value === 'For Follow-up' && !editInterventionFollowupDate.value) {
    Swal.fire({ icon: 'warning', text: 'Please select a follow-up date for For Follow-up status.' })
    return
  }
  loadingEditIntervention.value = true
  try {
    const url = route('guidance.consultations.intervention', editSelectedConsultation.value.id)
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    const resp = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token,
      },
      body: JSON.stringify({ intervention: editInterventionText.value, status: editInterventionStatus.value, followup_date: editInterventionFollowupDate.value || null, teacher: editInterventionTeacherId.value || null }),
      credentials: 'same-origin',
    })
    const data = await resp.json().catch(() => ({}))
    if (resp.ok) {
      if (data && data.consultation) {
        const updated = data.consultation
        const idx = list.value.findIndex(x => x.id === updated.id)
        if (idx !== -1) {
          list.value.splice(idx, 1, Object.assign({}, list.value[idx], updated))
        }
      }
      closeEditIntervention()
      Swal.fire({ icon: 'success', title: data.message || 'Intervention updated', timer: 1400, showConfirmButton: false })
    } else {
      const msg = data.message || 'Failed to update intervention.'
      Swal.fire({ icon: 'error', text: msg })
    }
  } catch (e) {
    console.error(e)
    Swal.fire({ icon: 'error', text: 'Unexpected error. See console.' })
  } finally {
    loadingEditIntervention.value = false
  }
}

function closeIntervention() { showInterventionModal.value = false; selectedInterventionConsultation.value = null; interventionText.value = ''; interventionStatus.value = ''; interventionTeacherId.value = null }

async function submitIntervention() {
  if (loadingIntervention.value) return
  if (!interventionText.value || !interventionStatus.value) {
    // eslint-disable-next-line no-undef
    Swal.fire({ icon: 'warning', text: 'Please enter intervention details and select a status.' })
    return
  }
  if (interventionStatus.value === 'For Follow-up' && !interventionFollowupDate.value) {
    // eslint-disable-next-line no-undef
    Swal.fire({ icon: 'warning', text: 'Please select a follow-up date for For Follow-up status.' })
    return
  }
  loadingIntervention.value = true
  try {
    const url = route('guidance.consultations.intervention', selectedInterventionConsultation.value.id)
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    const resp = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token,
      },
      body: JSON.stringify({ intervention: interventionText.value, status: interventionStatus.value, followup_date: interventionFollowupDate.value || null, teacher: interventionTeacherId.value || null }),
      credentials: 'same-origin',
    })
    const data = await resp.json().catch(() => ({}))
    if (resp.ok) {
      if (data && data.consultation) {
        const updated = data.consultation
        const idx = list.value.findIndex(x => x.id === updated.id)
        if (idx !== -1) {
          list.value.splice(idx, 1, Object.assign({}, list.value[idx], updated))
        }
      }
      closeIntervention()
      // eslint-disable-next-line no-undef
      Swal.fire({ icon: 'success', title: data.message || 'Intervention recorded', timer: 1400, showConfirmButton: false })
    } else {
      const msg = data.message || 'Failed to save intervention.'
      // eslint-disable-next-line no-undef
      Swal.fire({ icon: 'error', text: msg })
    }
  } catch (e) {
    console.error(e)
    // eslint-disable-next-line no-undef
    Swal.fire({ icon: 'error', text: 'Unexpected error. See console.' })
  } finally {
    loadingIntervention.value = false
  }
}

async function submitAssign() {
  if (loadingAssign.value) return
  if (!assignDateTime.value) { alert('Please select a date and time'); return }
  const chosen = new Date(assignDateTime.value)
  const now = new Date()
  if (chosen < now) { alert('Selected date/time cannot be in the past'); return }
  try {
    loadingAssign.value = true
    const url = route('guidance.consultations.assign', selectedConsultation.value.id)
    try {
      const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      const resp = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify({ date_time_assigned: assignDateTime.value, adviser_id: adviserId.value }),
        credentials: 'same-origin',
      })

      const data = await resp.json().catch(() => ({}))
      if (resp.ok) {
        // update the local list with the returned consultation if provided
        if (data && data.consultation) {
          const updated = data.consultation
          const idx = list.value.findIndex(x => x.id === updated.id)
          if (idx !== -1) {
            // merge to preserve any existing fields not returned
            list.value.splice(idx, 1, Object.assign({}, list.value[idx], updated))
          } else {
            // if not found, prepend
            list.value.unshift(updated)
          }
        }
        closeAssign()
        // eslint-disable-next-line no-undef
        Swal.fire({ icon: 'success', title: data.message || 'Appointment updated', timer: 1200, showConfirmButton: false })
      } else {
        closeAssign()
        const msg = data.message || 'Failed to assign appointment or send email.'
        // eslint-disable-next-line no-undef
        Swal.fire({ icon: 'error', text: msg })
      }
    } finally {
      loadingAssign.value = false
    }
  } catch (e) {
    closeAssign()
    // eslint-disable-next-line no-undef
    Swal.fire({ icon: 'error', text: 'Unexpected error. See console.' })
    console.error(e)
  }
}

function openAdmissionSlip(c) {
  try {
    const url = route('guidance.consultations.admission-slip', c.id)
    window.open(url, '_blank')
  } catch (e) {
    console.error('Failed to open admission slip', e)
    Swal.fire({ icon: 'error', text: 'Failed to open admission slip.' })
  }
}

const filtered = computed(() => {
  const term = (q.value || '').toString().toLowerCase().trim()
  if (!term) return list.value
  return list.value.filter(c => {
    return (c.concern||'').toString().toLowerCase().includes(term)
      || (c.requestor_name||'').toString().toLowerCase().includes(term)
      || (c.status||'').toString().toLowerCase().includes(term)
      || (c.id||'').toString().includes(term)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const paged = computed(() => {
  const start = (page.value - 1) * perPage
  return filtered.value.slice(start, start + perPage)
})

watch(q, () => { page.value = 1 })

// keep local list in sync when Inertia updates the shared `consultations` prop
watch(() => usePage().props.consultations, (val) => {
  list.value = val || []
})

const prev = () => { if (page.value>1) page.value-- }
const next = () => { if (page.value<totalPages.value) page.value++ }

function formatDate(d) {
  if (!d) return '—'
  try { return new Date(d).toLocaleString() } catch (e) { return d }
}
</script>
