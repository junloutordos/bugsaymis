<template>
  <AdminLayout title="Gate Pass">
    <div class="p-6 bg-white rounded shadow">
        <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Gate Pass</h1>
        <button @click="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow inline-flex items-center">
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
              <th class="border px-2 py-1">Badge</th>
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
              <td class="border px-2 py-1">{{ r.badgeNumber || r.badgeID || '—' }}</td>
              <td class="border px-2 py-1">{{ r.gatepass_type || '—' }}</td>
              <td class="border px-2 py-1">{{ r.gatepass_date || '—' }}</td>
              <td class="border px-2 py-1">{{ r.gatepass_timein || '—' }}</td>
              <td class="border px-2 py-1">{{ r.gatepass_timeout || '—' }}</td>
              <td class="border px-2 py-1">{{ r.destination || '—' }}</td>
              <td class="border px-2 py-1">{{ r.status || '—' }}</td>
              <td class="border px-2 py-1">
                <div class="flex items-center gap-2">
                  <button @click="edit(r)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" title="Edit">
                    <PencilSquareIcon class="w-5 h-5" />
                  </button>
                  <button @click="printGatepass(r)" class="p-2 rounded-full bg-green-100 hover:bg-green-200 text-green-700" title="Print">
                    <PrinterIcon class="w-5 h-5" />
                  </button>
                  <button @click="remove(r)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Delete">
                    <TrashIcon class="w-5 h-5" />
                  </button>
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
            <label>
              <input v-model="form.name" readonly placeholder="Name" class="border p-2 rounded-lg shadow-sm w-full bg-gray-100" />
            </label>
            <label>
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
            <!-- Status is set automatically to 'Pending' on creation -->
          </div>

          <div class="mt-4 flex justify-end gap-2">
            <button @click="closeModal" class="px-3 py-1 border rounded-lg">Cancel</button>
            <button @click="save" class="px-3 py-1 bg-blue-600 text-white rounded-lg">Save</button>
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

const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

const filtered = computed(() => {
  const q = (searchQuery.value || '').toLowerCase().trim()
  if (!q) return rows.value
  return rows.value.filter(r => [r.controlno, r.badgeNumber || r.badgeID, r.gatepass_type, r.destination, r.purpose, r.status].join(' ').toLowerCase().includes(q))
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
  const url = editing.value ? `/hr/gatepass/${currentId.value}` : '/hr/gatepass'
  const method = editing.value ? 'PUT' : 'POST'
  try {
    // ensure badgeNumber is current user's badgeNumber
    form.value.badgeNumber = currentUser.value.badgeNumber || form.value.badgeNumber
    // ensure status is Pending when creating new gate pass
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
  const name = r.name || r.employee_name || r.fullname || currentUser.value.name || ''
  const position = r.position || r.job_title || currentUser.value.position || ''
  const control = r.controlno || ''
  const date = r.gatepass_date || new Date().toISOString().slice(0,10)
  const timeOut = r.gatepass_timeout || ''
  const timeIn = r.gatepass_timein || ''
  const destination = r.destination || ''
  const purpose = r.purpose || ''
  const badge = r.badgeNumber || r.badgeID || ''
  const type = r.gatepass_type || ''
  const checked = (t) => (type === t ? '☑' : '☐')

  const html = `<!doctype html>
  <html>
  <head>
    <meta charset="utf-8">
    <title>Gate Pass ${control}</title>
    <style>
      body{font-family: Arial, Helvetica, sans-serif; color:#000;}
      .container{width:820px;margin:0 auto;padding:10px}
      table{width:100%;border-collapse:collapse}
      .b{border:1px solid #000}
      .no-border{border:none}
      td, th{padding:6px}
      .center{text-align:center}
      .right{text-align:right}
      .small{font-size:12px}
      .checkbox{font-size:14px;padding-right:8px}
      .signature{height:70px}
    </style>
  </head>
  <body onload="window.print()">
    <div class="container">
      <table class="b">
        <tr>
          <td class="center small" colspan="3"><strong>PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</strong><br/>CAMPUS: CARAGA REGION</td>
          <td class="b small right" style="width:220px"><strong>CONTROL NO:</strong><br/>${control}</td>
        </tr>
        <tr>
          <td class="no-border" colspan="3"></td>
          <td class="b small right">DATE:<br/>${date}</td>
        </tr>
        <tr>
          <td class="b" style="width:150px"><strong>NAME:</strong></td>
          <td class="b" colspan="3">${name} </td>
        </tr>
        <tr>
          <td class="b"><strong>POSITION:</strong></td>
          <td class="b" colspan="3">${position}</td>
        </tr>
        <tr>
          <td class="b"><strong>TIME OUT:</strong></td>
          <td class="b" style="width:120px">${timeOut}</td>
          <td class="b"><strong>TIME IN:</strong></td>
          <td class="b" style="width:120px">${timeIn}</td>
        </tr>
        <tr>
          <td class="b"><strong>DESTINATION:</strong></td>
          <td class="b" colspan="3">${destination}</td>
        </tr>
        <tr>
          <td class="b"><strong>PURPOSE:</strong></td>
          <td class="b" colspan="3">${purpose}</td>
        </tr>
        <tr>
          <td class="b" colspan="4">
            <div style="padding:8px">
              <span class="checkbox">${checked('Official Business')}</span> Official Business<br/>
              <span class="checkbox">${checked('Office Time')}</span> Office Time<br/>
              <span class="checkbox">${checked('Personal')}</span> Personal
            </div>
          </td>
        </tr>
        <tr>
          <td class="b" colspan="4"><strong>Recommending Approval:</strong></td>
        </tr>
        <tr>
          <td class="b" colspan="4" style="height:80px">
            <div class="signature" style="text-align:left">
              <div style="height:40px"></div>
              <div style="border-bottom:1px solid #000; display:inline-block; padding-bottom:4px; text-transform:uppercase; font-weight:bold;">
                ${divisionChief.value && divisionChief.value.name ? divisionChief.value.name.toUpperCase() : '______________________________'}
              </div>
              <div style="margin-top:6px">Division Chief</div>
            </div>
          </td>
        </tr>
        <tr>
          <td class="b" colspan="4"><strong>Approved:</strong></td>
        </tr>
        <tr>
          <td class="b" colspan="4" style="height:120px">
            <div class="signature" style="text-align:left">
              <div style="height:40px"></div>
              <div style="border-bottom:1px solid #000; display:inline-block; padding-bottom:4px; text-transform:uppercase; font-weight:bold;">
                ${director && director.value && director.value.name ? director.value.name.toUpperCase() : '______________________________'}
              </div>
              <div style="margin-top:6px">Director</div>
            </div>
          </td>
        </tr>
      </table>
    </div>
  </body>
  </html>`

  const w = window.open('', '_blank')
  if (!w) { Swal.fire({ icon: 'error', title: 'Unable to open print window' }); return }
  w.document.open()
  w.document.write(html)
  w.document.close()
}
</script>
