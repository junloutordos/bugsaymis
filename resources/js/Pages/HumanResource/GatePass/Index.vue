<template>
  <AdminLayout :title="(currentUser.role && currentUser.role.name === 'DivisionChief') ? 'Pending Gate Pass' : 'Gate Pass'">
    <div class="p-6 bg-white rounded shadow">
      <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">{{ (currentUser.role && currentUser.role.name === 'DivisionChief') ? 'Pending Gate Pass' : 'Gate Pass' }}</h1>
        <button v-if="!(currentUser.role && currentUser.role.name === 'DivisionChief')" @click="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow inline-flex items-center">
          <PlusIcon class="w-5 h-5 inline-block mr-1" /> New Gatepass
        </button>
      </div>

      <div class="mb-4">
        <input v-model="searchQuery" placeholder="Search..." class="w-full sm:w-1/2 md:w-1/3 rounded-lg border-gray-300 shadow-sm px-3 py-2" />
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full border-collapse border border-gray-300">
          <thead>
            <tr class="bg-gray-50">
              <th class="border px-2 py-1">ID</th>
              <th class="border px-2 py-1">Control No</th>
              <th v-if="!isSelf" class="border px-2 py-1">Name</th>
              <th v-if="!isSelf" class="border px-2 py-1">Badge</th>
              <th class="border px-2 py-1">Type</th>
              <th class="border px-2 py-1">Date</th>
              <th class="border px-2 py-1">Time In</th>
              <th class="border px-2 py-1">Time Out</th>
              <th class="border px-2 py-1">Destination</th>
              <th class="border px-2 py-1">Status</th>
              <th class="border px-2 py-1">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in paginated" :key="r.id">
              <td class="border px-2 py-1">{{ r.id }}</td>
              <td class="border px-2 py-1">{{ r.controlno || '—' }}</td>
              <td v-if="!isSelf" class="border px-2 py-1">{{ r.name || r.employee_name || r.fullname || '—' }}</td>
              <td v-if="!isSelf" class="border px-2 py-1">{{ r.badgeNumber || '—' }}</td>
              <td class="border px-2 py-1">{{ r.gatepass_type || '—' }}</td>
              <td class="border px-2 py-1">{{ r.gatepass_date || '—' }}</td>
              <td class="border px-2 py-1">{{ r.gatepass_timein || '—' }}</td>
              <td class="border px-2 py-1">{{ r.gatepass_timeout || '—' }}</td>
              <td class="border px-2 py-1">{{ r.destination || '—' }}</td>
              <td class="border px-2 py-1">
                <span v-if="r.status && r.status.toLowerCase().includes('approved')" class="px-3 py-1 text-xs bg-green-100 text-green-700 rounded-full font-medium">{{ r.status }}</span>
                <span v-else-if="r.status && r.status.toLowerCase().includes('declined')" class="px-3 py-1 text-xs bg-red-100 text-red-700 rounded-full font-medium">{{ r.status }}</span>
                <span v-else class="">{{ r.status || '—' }}</span>
              </td>
              <td class="border px-2 py-1">
                <div class="flex items-center gap-2">
                  <template v-if="currentUser.role && currentUser.role.name === 'DivisionChief'">
                    <div class="flex items-center gap-2">
                      <button v-if="r.status === 'Pending'" @click="approve(r)" class="p-2 rounded-full bg-green-100 hover:bg-green-200 text-green-700" title="Approve">
                        Approve
                      </button>
                      <button v-if="r.status === 'Pending'" @click="decline(r)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Decline">
                        Decline
                      </button>
                    </div>
                  </template>
                  <template v-else>
                    <button v-if="isSelf ? r.status === 'Pending' : true" @click="edit(r)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" title="Edit">
                      <PencilSquareIcon class="w-5 h-5" />
                    </button>
                    <button v-if="(isSelf ? r.status === 'OCD Approved' : (isAdmin ? r.status === 'OCD Approved' : true))" @click="printGatepass(r)" class="p-2 rounded-full bg-green-100 hover:bg-green-200 text-green-700" title="Print">
                      <PrinterIcon class="w-5 h-5" />
                    </button>
                    <button v-if="isSelf ? r.status === 'Pending' : true" @click="remove(r)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Delete">
                      <TrashIcon class="w-5 h-5" />
                    </button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex items-center justify-center">
        <div class="inline-flex items-center gap-2">
          <button @click="goToPage(currentPage-1)" :disabled="currentPage===1" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Prev</button>
          <span class="text-sm">Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="goToPage(currentPage+1)" :disabled="currentPage>=totalPages" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- Modal -->
      <div v-show="show" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 transition-opacity">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">{{ editing ? 'Edit' : 'Add' }} Gate Pass</h2>

          <div class="grid grid-cols-1 gap-3">
            <label v-if="!isSelf">
              <input v-model="form.name" readonly placeholder="Name" class="border p-2 rounded-lg shadow-sm w-full bg-gray-100" />
            </label>
            <label v-if="!isSelf">
              <input v-model="form.position" readonly placeholder="Position" class="border p-2 rounded-lg shadow-sm w-full bg-gray-100" />
            </label>
            <label>
              <select v-model="form.gatepass_type" class="border p-2 rounded-lg shadow-sm w-full">
                <option value="">-- Select Type --</option>
                <option value="Official Business">Official Business</option>
                <option value="Personal">Personal</option>
                <option value="Office Time">Office Time</option>
              </select>
            </label>
            <input v-model="form.gatepass_date" type="date" placeholder="Date" class="border p-2 rounded-lg shadow-sm" />
            <div class="flex gap-2">
              <input v-model="form.gatepass_timein" type="time" class="border p-2 rounded-lg shadow-sm flex-1" />
              <input v-model="form.gatepass_timeout" type="time" class="border p-2 rounded-lg shadow-sm flex-1" />
            </div>
            <input v-model="form.destination" placeholder="Destination" class="border p-2 rounded-lg shadow-sm" />
            <input v-model="form.purpose" placeholder="Purpose" class="border p-2 rounded-lg shadow-sm" />
          </div>

          <div class="mt-4 flex justify-end gap-2">
            <button @click="closeModal" :disabled="saving" class="px-3 py-1 border rounded-lg">Cancel</button>
            <button @click="save" :disabled="saving" class="px-3 py-1 bg-blue-600 text-white rounded-lg inline-flex items-center">
              <svg v-if="saving" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
              <span>{{ saving ? (editing ? 'Saving...' : 'Saving...') : 'Save' }}</span>
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
