<template>
  <Head title="Work Requests" />
  <AdminLayout title="Work Requests">
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Work Requests</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage building and maintenance work requests</p>
        </div>
        <button
          v-if="!hasRole('GSU Head')"
          @click.prevent="handleNewRequest()"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
        >
          + New Request
        </button>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <!-- Search -->
        <div class="px-5 py-4 border-b border-slate-100">
          <input v-model="searchQuery" type="text" placeholder="Search work requests…"
                 class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        </div>

        <!-- Desktop table -->
        <div v-if="!isMobile" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date Created</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Issue</th>
                <th v-if="hasAnyRole('Administrator','GSU Head','DivisionChief')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Requestor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Description</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Assigned Personnel</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Acted By</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Expected Completion</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action Taken</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date Completed</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="wr in filteredWorkRequests" :key="wr.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ wr.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ wr.created_at ? new Date(wr.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ wr.issue ?? '—' }}</td>
                <td v-if="hasAnyRole('Administrator','GSU Head','DivisionChief')" class="px-4 py-3 text-sm text-slate-700">{{ wr.requester?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ wr.description ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ wr.assigned_user?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ wr.actedBy?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ wr.expected_completion_date ? new Date(wr.expected_completion_date).toLocaleDateString() : '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ wr.action_taken ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ wr.date_completed ? new Date(wr.date_completed).toLocaleDateString() : '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', statusClass(wr.status)]">
                    {{ wr.status ?? '—' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-1.5 justify-center">
                    <button
                      v-if="((wr.status === 'Division Approved' && hasRole('Administrator')) || (wr.status === 'Pending' && hasRole('GSU Head')))"
                      @click.prevent="openModal(wr)"
                      class="p-1.5 rounded-lg hover:bg-indigo-50 text-slate-500 hover:text-indigo-700 transition-colors"
                      title="Assign"
                    >
                      <UserPlusIcon class="w-4 h-4" />
                    </button>

                    <button
                      v-if="hasRole('Administrator')"
                      @click.prevent="openModal(wr)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
                      title="Edit"
                    >
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>

                    <button
                      v-if="hasRole('Administrator')"
                      @click.prevent="destroy(wr)"
                      class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors"
                      title="Delete"
                    >
                      <TrashIcon class="w-4 h-4" />
                    </button>

                    <button
                      v-if="((wr.status === 'FAD Approved' && (hasRole('GSU Head') || hasRole('Administrator'))) || (wr.status === 'Division Approved' && hasRole('GSU Head')))"
                      @click.prevent="openCompleteModal(wr)"
                      class="p-1.5 rounded-lg hover:bg-blue-50 text-slate-500 hover:text-blue-700 transition-colors"
                      title="Mark Completed"
                    >
                      <CheckCircleIcon class="w-4 h-4" />
                    </button>
                    <a
                      v-if="(wr.status === 'Completed') && (hasAnyRole('GSU Head','Administrator'))"
                      :href="`/work-requests/${wr.id}/print`"
                      target="_blank"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
                      title="Print"
                    >
                      <PrinterIcon class="w-4 h-4" />
                    </a>
                    <button
                      v-if="wr.status === 'Completed' && wr.requester_id === page.props.auth.user.id"
                      @click.prevent="openCsmModal(wr)"
                      class="inline-flex items-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white px-2 py-1 rounded-lg text-xs font-medium transition-colors"
                      title="Confirm & Rate"
                    >Confirm &amp; Rate</button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredWorkRequests.length === 0">
                <td :colspan="(hasAnyRole('Administrator','GSU Head','DivisionChief') ? 12 : 11)" class="py-16 text-center text-slate-400 text-sm">No work requests found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile list -->
        <div v-else class="p-4 space-y-3">
          <div v-for="wr in filteredWorkRequests" :key="wr.id" class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-xs text-slate-500">Request #{{ wr.id }} &bull; {{ wr.created_at ? new Date(wr.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—' }}</p>
                <p class="text-sm font-semibold text-slate-800 mt-0.5 truncate">{{ wr.issue ?? '—' }}</p>
                <p v-if="hasAnyRole('Administrator','GSU Head','DivisionChief')" class="text-xs text-slate-600 mt-1">Requestor: {{ wr.requester?.name ?? '—' }}</p>
                <p class="text-xs text-slate-600">{{ wr.description ?? '—' }}</p>
              </div>
              <div class="shrink-0 text-right text-xs text-slate-600">
                <div>{{ wr.expected_completion_date ? new Date(wr.expected_completion_date).toLocaleDateString() : '—' }}</div>
                <div class="text-slate-400">{{ wr.date_completed ? new Date(wr.date_completed).toLocaleDateString() : '—' }}</div>
              </div>
            </div>
            <div class="mt-2 space-y-1 text-xs text-slate-700">
              <div><span class="font-medium text-slate-500">Assigned:</span> {{ wr.assigned_user?.name ?? '—' }}</div>
              <div class="flex items-center gap-2"><span class="font-medium text-slate-500">Status:</span>
                <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', statusClass(wr.status)]">{{ wr.status }}</span>
              </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
              <button v-if="((wr.status === 'Division Approved' && hasRole('Administrator')) || (wr.status === 'Pending' && hasRole('GSU Head')))" @click.prevent="openModal(wr)" class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">Assign</button>
              <button v-if="hasRole('Administrator')" @click.prevent="openModal(wr)" class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">Edit</button>
              <button v-if="hasRole('Administrator')" @click.prevent="destroy(wr)" class="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">Delete</button>
              <button v-if="((wr.status === 'FAD Approved' && (hasRole('GSU Head') || hasRole('Administrator'))) || (wr.status === 'Division Approved' && hasRole('GSU Head')))" @click.prevent="openCompleteModal(wr)" class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">Mark Completed</button>
              <a v-if="(wr.status === 'Completed') && (hasAnyRole('GSU Head','Administrator'))" :href="`/work-requests/${wr.id}/print`" target="_blank" class="inline-flex items-center gap-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">Print</a>
              <button v-if="wr.status === 'Completed' && wr.requester_id === page.props.auth.user.id" @click.prevent="openCsmModal(wr)" class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">Confirm &amp; Rate</button>            </div>
          </div>
          <div v-if="filteredWorkRequests.length === 0" class="py-16 text-center text-slate-400 text-sm">No work requests found.</div>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex items-center gap-2">
            <button @click="currentPage--" :disabled="currentPage === 1" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">Prev</button>
            <button @click="currentPage++" :disabled="currentPage === totalPages" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>

      <!-- Create / Edit Modal -->
      <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl relative">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ editingId ? 'Edit Work Request' : 'New Work Request' }}</h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
          <form @submit.prevent="submitForm" class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Issue <span class="text-red-500">*</span></label>
              <input v-model="form.issue" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" required />
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
              <textarea v-model="form.description" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="4"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Building</label>
                <select v-model="form.location_division_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                  <option value="">Select building</option>
                  <option v-for="d in props.divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Room</label>
                <select v-model="form.location_office_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                  <option value="">Select room</option>
                  <option v-for="o in filteredOffices" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Priority</label>
                <select v-model="form.priority" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                  <option>Low</option>
                  <option>Normal</option>
                  <option>High</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Expected Completion</label>
                <input v-model="form.expected_completion_date" type="date" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
            </div>

            <div v-if="editingId && (hasRole('GSU Head') || hasRole('Administrator'))" class="mt-3">
              <label class="block text-xs font-medium text-slate-600 mb-1">Assign Personnel</label>
              <select v-model="form.assigned_user_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">Select staff</option>
                <option v-for="u in (props.skilledUsers || [])" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
          </form>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button type="button" @click="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button type="button" @click="editingId ? submitForm() : openPinModal()" :disabled="form.processing" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
              <span v-if="form.processing">Saving…</span>
              <span v-else>Save</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Completion Modal -->
      <div v-if="showCompleteModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md relative">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Mark Work Request Completed</h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeCompleteModal">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
          <form @submit.prevent="submitCompletion" class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Acted By</label>
              <select v-model="completeForm.acted_by_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">Select staff</option>
                <option v-for="u in (props.skilledUsers || [])" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Action Taken</label>
              <textarea v-model="completeForm.action_taken" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="4" required></textarea>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Date Completed</label>
              <input v-model="completeForm.date_completed" type="date" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" required />
            </div>
          </form>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button type="button" @click="closeCompleteModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button type="submit" @click="submitCompletion" :disabled="completeForm.processing" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
              <span v-if="completeForm.processing">Saving…</span>
              <span v-else>Save</span>
            </button>
          </div>
        </div>
      </div>
    </div>
    <DigitalSignaturePin
      :show="showSubmitPin"
      :hasPin="hasPin"
      :signatureUri="signatureUri"
      :loading="pinLoading"
      confirmLabel="Sign & Submit"
      @confirm="handlePinConfirm"
      @cancel="handlePinCancel"
    />
    <CsmForm
      :show="showCsmModal"
      respondable-type="work-request"
      :respondable-id="requestToCsm?.id ?? 0"
      :transaction-date="requestToCsm?.created_at?.slice(0,10) ?? ''"
      office-availed="General Services Unit"
      service-key="others"
      service-other-label="Work / Maintenance Request"
      @close="showCsmModal = false"
      @submitted="onCsmSubmitted"
    />
  </AdminLayout>
</template>

<script setup>
import { Head, usePage, useForm, router } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { PencilSquareIcon, TrashIcon, UserPlusIcon, CheckCircleIcon, XMarkIcon, PrinterIcon } from '@heroicons/vue/24/outline'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import CsmForm from '@/Components/CsmForm.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'

const props = defineProps({
  divisions: Array,
  offices: Array,
  users: Array,
  skilledUsers: Array,
  workRequests: Array,
  hasPendingCsm: { type: Boolean, default: false },
  hasPin: { type: Boolean, default: false },
  signatureUri: { type: String, default: null },
})

const showCsmModal = ref(false)
const requestToCsm = ref(null)
function openCsmModal(req) { requestToCsm.value = req; showCsmModal.value = true }

// Use server-side prop — accurate regardless of pagination or filters
const hasPendingConfirmation = computed(() => props.hasPendingCsm)

function onCsmSubmitted() {
  showCsmModal.value = false
}

async function handleNewRequest() {
  if (hasPendingConfirmation.value) {
    await Swal.fire({
      icon: 'warning',
      title: 'Action Required',
      text: 'You have a Work Request that has been completed and is pending your confirmation. Please rate the service first before submitting a new request.',
      confirmButtonText: 'OK',
    })
    return
  }
  openModal()
}

// client-side search + pagination for work requests
const workRequestsList = computed(() => props.workRequests || [])
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

// responsive: track window width to toggle between table and card layouts
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const isMobile = computed(() => windowWidth.value < 768)
const handleResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => { window.addEventListener('resize', handleResize) })
onBeforeUnmount(() => { window.removeEventListener('resize', handleResize) })

const filteredWorkRequestsAll = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return (workRequestsList.value || []).filter(wr =>
    (wr.issue || '').toString().toLowerCase().includes(q) ||
    (wr.description || '').toString().toLowerCase().includes(q) ||
    (wr.id || '').toString().includes(q) ||
    (wr.status || '').toString().toLowerCase().includes(q) ||
    (wr.requester?.name || '').toString().toLowerCase().includes(q) ||
    (wr.assigned_user?.name || '').toString().toLowerCase().includes(q) ||
    (wr.actedBy?.name || '').toString().toLowerCase().includes(q) ||
    (wr.action_taken || '').toString().toLowerCase().includes(q) ||
    (wr.priority || '').toString().toLowerCase().includes(q)
  )
})

const filteredWorkRequests = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredWorkRequestsAll.value.slice(start, start + perPage)
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredWorkRequestsAll.value.length / perPage)))

watch(searchQuery, () => { currentPage.value = 1 })

const page = usePage();
const roleName = computed(() => page.props.auth?.user?.role?.name ?? '');
const roleNames = computed(() => page.props.auth?.user?.roleNames ?? (roleName.value ? [roleName.value] : []));
const hasRole = (role) => roleNames.value.includes(role);
const hasAnyRole = (...roles) => roles.some(r => roleNames.value.includes(r));

const showModal = ref(false)
const editingId = ref(null)

const showCompleteModal = ref(false)
const completeEditingId = ref(null)

const form = useForm({
  issue: '',
  description: '',
  priority: 'Normal',
  location_division_id: '',
  location_office_id: '',
  expected_completion_date: '',
  assigned_user_id: '',
  pin: null,
})

const showSubmitPin = ref(false)
const pinLoading = ref(false)

const openPinModal = () => { showSubmitPin.value = true }
const handlePinCancel = () => { showSubmitPin.value = false }
const handlePinConfirm = (pin) => {
  form.pin = pin || null
  showSubmitPin.value = false
  doPostStore()
}
const doPostStore = () => {
  form.post('/work-requests', {
    onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Work request created', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
    onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to create', text: Object.values(errors).flat().join('\n') || 'Failed to create' }) }
  })
}

const completeForm = useForm({
  acted_by_id: '',
  action_taken: '',
  date_completed: '',
})

// Filter offices (rooms) by selected division (building)
const filteredOffices = computed(() => {
  if (!form.location_division_id) return props.offices || []
  return (props.offices || []).filter(o => String(o.building_id) === String(form.location_division_id))
})

// Clear room selection if it no longer belongs to the selected building
watch(() => form.location_division_id, (nv) => {
  if (!nv) return
  const ok = (props.offices || []).some(o => String(o.id) === String(form.location_office_id) && String(o.building_id) === String(nv))
  if (!ok) form.location_office_id = ''
})

const openModal = (wr = null) => {
  editingId.value = wr ? wr.id : null
  if (wr) {
    form.reset()
    form.issue = wr.issue ?? ''
    form.description = wr.description ?? ''
    form.priority = wr.priority ?? 'Normal'
    form.location_division_id = wr.location_division_id ?? ''
    form.location_office_id = wr.location_office_id ?? ''
    form.expected_completion_date = wr.expected_completion_date ?? ''
    form.assigned_user_id = wr.assigned_user_id ?? ''
  } else {
    form.reset()
    form.priority = 'Normal'
  }
  showModal.value = true
}

const openCompleteModal = (wr) => {
  completeEditingId.value = wr ? wr.id : null
  completeForm.reset()
  completeForm.acted_by_id = wr?.acted_by_id ?? ''
  completeForm.action_taken = wr?.action_taken ?? ''
  completeForm.date_completed = wr?.date_completed ?? ''
  showCompleteModal.value = true
}

const closeCompleteModal = () => { showCompleteModal.value = false; completeEditingId.value = null; completeForm.reset() }

const submitCompletion = () => {
  if (!completeEditingId.value) return
  completeForm.post(`/work-requests/${completeEditingId.value}/complete`, {
    onSuccess: () => { closeCompleteModal(); Swal.fire({ icon: 'success', title: 'Marked completed', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
    onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to save', text: Object.values(errors).flat().join('\n') || 'Failed to save' }) }
  })
}

const closeModal = () => { showModal.value = false; editingId.value = null; form.reset() }

const submitForm = () => {
  if (editingId.value) {
    form.put(`/work-requests/${editingId.value}`, {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Work request updated', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
      onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to update', text: Object.values(errors).flat().join('\n') || 'Failed to update' }) }
    })
  }
}

const destroy = (wr) => {
  Swal.fire({ title: 'Delete this work request?', text: 'This action cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', cancelButtonText: 'Cancel' }).then((res) => {
    if (!res.isConfirmed) return
    import('@inertiajs/vue3').then(({ router }) => {
      router.delete(`/work-requests/${wr.id}`, {
        onSuccess: () => { Swal.fire({ icon: 'success', title: 'Deleted', timer: 1000, showConfirmButton: false }).then(() => { window.location.reload() }) },
        onError: (e) => { Swal.fire({ icon: 'error', title: 'Failed to delete' }) }
      })
    })
  })
}


// Return CSS classes for status badges
const statusClass = (s) => {
  if (!s) return 'bg-slate-100 text-slate-600'
  const st = String(s).toLowerCase()
  if (st.includes('approve')) return 'bg-emerald-50 text-emerald-700'
  if (st.includes('declin')) return 'bg-red-50 text-red-600'
  if (st.includes('completed')) return 'bg-blue-50 text-blue-700'
  if (st.includes('pending')) return 'bg-amber-50 text-amber-700'
  return 'bg-slate-100 text-slate-600'
}
</script>
