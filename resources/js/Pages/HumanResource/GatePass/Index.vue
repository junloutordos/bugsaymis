<template>
  <AdminLayout :title="(currentUser.role && currentUser.role.name === 'DivisionChief') ? 'Pending Gate Pass' : 'Gate Pass'">
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">{{ (currentUser.role && currentUser.role.name === 'DivisionChief') ? 'Pending Gate Pass' : 'Gate Pass' }}</h1>
        <button v-if="!(currentUser.role && currentUser.role.name === 'DivisionChief')" @click="openModal()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <PlusIcon class="w-4 h-4" /> New Gatepass
        </button>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <input v-model="searchQuery" placeholder="Search..." class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64" />
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto rounded-xl border border-slate-100">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">ID</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Control No</th>
                <th v-if="!isSelf" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                <th v-if="!isSelf" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Badge</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Time In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Time Out</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Destination</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="r in paginated" :key="r.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.controlno || '—' }}</td>
                <td v-if="!isSelf" class="px-4 py-3 text-sm text-slate-700">{{ r.name || r.employee_name || r.fullname || '—' }}</td>
                <td v-if="!isSelf" class="px-4 py-3 text-sm text-slate-700">{{ r.badgeNumber || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.gatepass_type || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.gatepass_date || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.gatepass_timein || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.gatepass_timeout || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ r.destination || '—' }}</td>
                <td class="px-4 py-3">
                  <span v-if="r.status && r.status.toLowerCase().includes('approved')" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700">{{ r.status }}</span>
                  <span v-else-if="r.status && r.status.toLowerCase().includes('declined')" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-red-50 text-red-600">{{ r.status }}</span>
                  <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700">{{ r.status || '—' }}</span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1">
                    <template v-if="currentUser.role && currentUser.role.name === 'DivisionChief'">
                      <button v-if="r.status === 'Pending'" @click="approve(r)" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm" title="Approve">
                        Approve
                      </button>
                      <button v-if="r.status === 'Pending'" @click="decline(r)" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm" title="Decline">
                        Decline
                      </button>
                    </template>
                    <template v-else>
                      <button v-if="isSelf ? r.status === 'Pending' : true" @click="edit(r)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                        <PencilSquareIcon class="w-4 h-4" />
                      </button>
                      <button v-if="(isSelf ? r.status === 'OCD Approved' : (isAdmin ? r.status === 'OCD Approved' : true))" @click="printGatepass(r)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Print">
                        <PrinterIcon class="w-4 h-4" />
                      </button>
                      <button v-if="isSelf ? r.status === 'Pending' : true" @click="remove(r)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors" title="Delete">
                        <TrashIcon class="w-4 h-4" />
                      </button>
                    </template>
                  </div>
                </td>
              </tr>
              <tr v-if="paginated.length === 0">
                <td :colspan="isSelf ? 9 : 11" class="py-16 text-center text-slate-400 text-sm">No gate passes found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex items-center gap-2">
            <button @click="goToPage(currentPage-1)" :disabled="currentPage===1" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Prev</button>
            <button @click="goToPage(currentPage+1)" :disabled="currentPage>=totalPages" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>

      <!-- Modal -->
      <div v-show="show" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50">
        <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full relative">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">{{ editing ? 'Edit' : 'Add' }} Gate Pass</h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal">✕</button>
          </div>
          <div class="px-6 py-5">
            <div class="grid grid-cols-1 gap-3">
              <label v-if="!isSelf">
                <span class="block text-xs font-medium text-slate-600 mb-1">Name</span>
                <input v-model="form.name" readonly placeholder="Name" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 w-full focus:outline-none" />
              </label>
              <label v-if="!isSelf">
                <span class="block text-xs font-medium text-slate-600 mb-1">Position</span>
                <input v-model="form.position" readonly placeholder="Position" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 w-full focus:outline-none" />
              </label>
              <div>
                <span class="block text-xs font-medium text-slate-600 mb-1">Type</span>
                <select v-model="form.gatepass_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full">
                  <option value="">-- Select Type --</option>
                  <option value="Official Business">Official Business</option>
                  <option value="Personal">Personal</option>
                  <option value="Office Time">Office Time</option>
                </select>
              </div>
              <div>
                <span class="block text-xs font-medium text-slate-600 mb-1">Date</span>
                <input v-model="form.gatepass_date" type="date" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              </div>
              <div class="flex gap-2">
                <div class="flex-1">
                  <span class="block text-xs font-medium text-slate-600 mb-1">Time In</span>
                  <input v-model="form.gatepass_timein" type="time" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
                </div>
                <div class="flex-1">
                  <span class="block text-xs font-medium text-slate-600 mb-1">Time Out</span>
                  <input v-model="form.gatepass_timeout" type="time" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
                </div>
              </div>
              <div>
                <span class="block text-xs font-medium text-slate-600 mb-1">Destination</span>
                <input v-model="form.destination" placeholder="Destination" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              </div>
              <div>
                <span class="block text-xs font-medium text-slate-600 mb-1">Purpose</span>
                <input v-model="form.purpose" placeholder="Purpose" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="closeModal" :disabled="saving" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click="save" :disabled="saving" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              <svg v-if="saving" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
              <span>{{ saving ? 'Saving...' : 'Save' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { usePage } from '@inertiajs/vue3'
import { PlusIcon, PencilSquareIcon, TrashIcon, PrinterIcon } from '@heroicons/vue/24/outline'

const page = usePage()
const rows = computed(() => page.props.rows || [])
const divisionChief = computed(() => page.props.divisionChief || null)
const director = computed(() => page.props.director || null)
const currentUser = computed(() => page.props.auth?.user || {})
const isSelf = computed(() => ['Staff', 'Faculty'].includes(currentUser.value.role?.name || ''))
const isAdmin = computed(() => (currentUser.value.role?.name || '').toLowerCase() === 'administrator')

const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

const filtered = computed(() => {
  const q = (searchQuery.value || '').toLowerCase().trim()
  if (!q) return rows.value
  return rows.value.filter(r => [r.controlno, r.badgeNumber, r.name || r.employee_name || r.fullname, r.gatepass_type, r.destination, r.purpose, r.status].join(' ').toLowerCase().includes(q))
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filtered.value.slice(start, start + perPage)
})

function goToPage(n) {
  if (n < 1) n = 1
  if (n > totalPages.value) n = totalPages.value
  currentPage.value = n
}

watch(searchQuery, () => { currentPage.value = 1 })

const show = ref(false)
const editing = ref(false)
const saving = ref(false)
const form = ref({ controlno: '', badgeNumber: '', name: '', position: '', gatepass_type: '', gatepass_timeout: '', gatepass_timein: '', gatepass_date: '', destination: '', purpose: '', status: '' })
const currentId = ref(null)

function openModal() {
  editing.value = false
  form.value = { controlno: '', badgeNumber: currentUser.value.badgeNumber || '', name: currentUser.value.name || currentUser.value.fullname || currentUser.value.first_name || '', position: currentUser.value.position || currentUser.value.job_title || '', gatepass_type: '', gatepass_timeout: '', gatepass_timein: '', gatepass_date: '', destination: '', purpose: '', status: 'Pending' }
  currentId.value = null
  show.value = true
}
function closeModal() { show.value = false }

function edit(r) {
  editing.value = true
  currentId.value = r.id
  form.value = { controlno: r.controlno, badgeNumber: currentUser.value.badgeNumber || r.badgeNumber || r.badgeID, name: currentUser.value.name || r.name || r.employee_name || r.fullname || '', position: currentUser.value.position || r.position || r.job_title || '', gatepass_type: r.gatepass_type, gatepass_timeout: r.gatepass_timeout, gatepass_timein: r.gatepass_timein, gatepass_date: r.gatepass_date, destination: r.destination, purpose: r.purpose, status: r.status }
  show.value = true
}

async function save() {
  if (saving.value) return
  saving.value = true
  const url = editing.value ? `/hr/gatepass/${currentId.value}` : '/hr/gatepass'
  const method = editing.value ? 'PUT' : 'POST'
  try {
    form.value.badgeNumber = currentUser.value.badgeNumber || form.value.badgeNumber
    if (!editing.value) form.value.status = form.value.status || 'Pending'
    const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }, body: JSON.stringify(form.value) })
    if (res.ok) {
      await Swal.fire({ icon: 'success', title: editing.value ? 'Gate pass updated' : 'Gate pass added', timer: 1200, showConfirmButton: false })
      location.reload()
    } else {
      let text = 'Save failed'
      if (res.status === 422) {
        const data = await res.json().catch(() => ({}))
        text = Object.values(data.errors || {}).flat().join('\n') || text
      } else {
        const data = await res.json().catch(() => null)
        if (data && data.message) text = data.message
      }
      Swal.fire({ icon: 'error', title: 'Failed to save', text })
    }
  } catch (e) { Swal.fire({ icon: 'error', title: 'Save failed', text: e.message || 'Save failed' }) }
  finally { saving.value = false }
}

async function remove(r) {
  const result = await Swal.fire({ title: 'Delete this gate pass?', text: 'This action cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', cancelButtonText: 'Cancel' })
  if (!result.isConfirmed) return
  try {
    const res = await fetch(`/hr/gatepass/${r.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') } })
    if (res.ok) {
      await Swal.fire({ icon: 'success', title: 'Deleted', timer: 1000, showConfirmButton: false })
      location.reload()
    } else {
      let text = 'Delete failed'
      const data = await res.json().catch(() => null)
      if (data && data.message) text = data.message
      Swal.fire({ icon: 'error', title: 'Failed to delete', text })
    }
  } catch (e) { Swal.fire({ icon: 'error', title: 'Delete failed', text: e.message || 'Delete failed' }) }
}

function printGatepass(r) {
  const url = `/hr/gatepass/${r.id}/print`
  const w = window.open(url, '_blank')
  if (!w) Swal.fire({ icon: 'error', title: 'Unable to open print window' })
}

async function approve(r) {
  const res = await Swal.fire({ title: 'Approve this gate pass?', icon: 'question', showCancelButton: true, confirmButtonText: 'Approve' })
  if (!res.isConfirmed) return
  try {
    const url = `/hr/gatepass/${r.id}`
    const body = { status: 'Division Approved', date_time_approved: new Date().toISOString() }
    const t = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    const resp = await fetch(url, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': t }, body: JSON.stringify(body) })
    if (resp.ok) {
      await Swal.fire({ icon: 'success', title: 'Approved', timer: 1000, showConfirmButton: false })
      location.reload()
    } else {
      let text = 'Approve failed'
      const data = await resp.json().catch(() => null)
      if (data && data.message) text = data.message
      Swal.fire({ icon: 'error', title: 'Failed to approve', text })
    }
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed to approve', text: e.message || 'Failed to approve' })
  }
}

async function decline(r) {
  const { value: reason } = await Swal.fire({
    title: 'Reason for decline',
    input: 'textarea',
    inputPlaceholder: 'Enter reason for declining this gate pass...',
    showCancelButton: true,
    confirmButtonText: 'Decline',
    cancelButtonText: 'Cancel',
    inputAttributes: { 'aria-label': 'Reason for decline' }
  })
  if (!reason) return
  try {
    const url = `/hr/gatepass/${r.id}`
    const body = { status: 'Division Declined', decline_reason: reason, date_time_declined: new Date().toISOString() }
    const t = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    const resp = await fetch(url, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': t }, body: JSON.stringify(body) })
    if (resp.ok) {
      await Swal.fire({ icon: 'success', title: 'Declined', timer: 1000, showConfirmButton: false })
      location.reload()
    } else {
      let text = 'Decline failed'
      const data = await resp.json().catch(() => null)
      if (data && data.message) text = data.message
      Swal.fire({ icon: 'error', title: 'Failed to decline', text })
    }
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Failed to decline', text: e.message || 'Failed to decline' })
  }
}
</script>
