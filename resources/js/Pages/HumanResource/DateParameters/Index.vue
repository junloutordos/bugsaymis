<template>
  <AdminLayout title="Date Parameters">
    <div class="p-6 bg-white rounded shadow">
      <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Date Parameters</h1>
        <button @click="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow inline-flex items-center">
          <PlusIcon class="w-5 h-5 inline-block mr-1" /> New Date Parameter
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
              <th class="border px-2 py-1">Type</th>
              <th class="border px-2 py-1">Description</th>
              <th class="border px-2 py-1">Date</th>
              <th class="border px-2 py-1">Time In</th>
              <th class="border px-2 py-1">Break Out</th>
              <th class="border px-2 py-1">Break In</th>
              <th class="border px-2 py-1">Time Out</th>
              <th class="border px-2 py-1">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in paginated" :key="p.id">
              <td class="border px-2 py-1">{{ p.id }}</td>
              <td class="border px-2 py-1">{{ displayType(p.type) }}</td>
              <td class="border px-2 py-1">{{ p.description || '—' }}</td>
              <td class="border px-2 py-1">{{ p.date || '—' }}</td>
              <td class="border px-2 py-1">{{ p.timein || '—' }}</td>
              <td class="border px-2 py-1">{{ p.breakout || '—' }}</td>
              <td class="border px-2 py-1">{{ p.breakin || '—' }}</td>
              <td class="border px-2 py-1">{{ p.timeout || '—' }}</td>
              <td class="border px-2 py-1">
                <div class="flex items-center gap-2">
                  <button @click="edit(p)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" title="Edit">
                    <PencilSquareIcon class="w-5 h-5" />
                  </button>
                  <button @click="remove(p)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Delete">
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
      <div
        v-show="show"
        class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 transition-opacity"
      >
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
          <button
            class="absolute top-3 right-3 text-gray-500 hover:text-gray-800"
            @click="closeModal"
          >
            ✕
          </button>
          <h2 class="text-xl font-semibold mb-4">{{ editing ? 'Edit' : 'Add' }} Date Parameter</h2>

          <div class="grid grid-cols-1 gap-3">
            <label class="block">
              <select v-model="form.type" class="border p-2 w-full rounded-lg shadow-sm">
                <option value="">-- Select Type --</option>
                <option value="HOL">Holiday</option>
                <option value="SUSPENDED">Work Suspension</option>
                <option value="WFH">Work From Home</option>
              </select>
            </label>
            <input v-model="form.description" placeholder="Description" class="border p-2 rounded-lg shadow-sm" />
            <input v-model="form.date" type="date" placeholder="Date" class="border p-2 rounded-lg shadow-sm" />
            <input v-model="form.timein" type="time" placeholder="Time In" class="border p-2 rounded-lg shadow-sm" />
            <input v-model="form.breakout" type="time" placeholder="Break Out" class="border p-2 rounded-lg shadow-sm" />
            <input v-model="form.breakin" type="time" placeholder="Break In" class="border p-2 rounded-lg shadow-sm" />
            <input v-model="form.timeout" type="time" placeholder="Time Out" class="border p-2 rounded-lg shadow-sm" />
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
import { PencilSquareIcon, TrashIcon, PlusIcon } from '@heroicons/vue/24/outline'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const params = computed(() => page.props.params || [])

// search + pagination
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

  const typeLabels = {
    HOL: 'Holiday',
    SUSPENDED: 'Work Suspension',
    WFH: 'Work From Home',
  }

  function displayType(v) {
    if (!v) return '—'
    if (typeLabels[v]) return typeLabels[v]
    // handle legacy full-text values
    const legacy = String(v).toLowerCase()
    if (legacy.includes('holiday')) return typeLabels.HOL
    if (legacy.includes('suspend')) return typeLabels.SUSPENDED
    if (legacy.includes('work from home') || legacy.includes('wfh')) return typeLabels.WFH
    return v
  }

  function codeForType(v) {
    if (!v) return ''
    if (typeof v !== 'string') return ''
    const up = v.toUpperCase()
    if (['HOL','SUSPENDED','WFH'].includes(up)) return up
    const low = v.toLowerCase()
    if (low.includes('holiday')) return 'HOL'
    if (low.includes('suspend')) return 'SUSPENDED'
    if (low.includes('work from home') || low.includes('wfh')) return 'WFH'
    return ''
  }

  const filtered = computed(() => {
  const q = (searchQuery.value || '').toLowerCase().trim()
  if (!q) return params.value
  return params.value.filter(p => {
    return (String(p.type || '').toLowerCase().includes(q) ||
            String(p.description || '').toLowerCase().includes(q) ||
            String(p.date || '').toLowerCase().includes(q))
  })
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
const form = ref({ type: '', description: '', date: '', timein: '', breakout: '', breakin: '', timeout: '' })
const currentId = ref(null)

function openModal() {
  editing.value = false
  form.value = { type: '', description: '', date: '', timein: '', breakout: '', breakin: '', timeout: '' }
  currentId.value = null
  show.value = true
}
function closeModal() { show.value = false }

function edit(p) {
  editing.value = true
  currentId.value = p.id
  form.value = { type: codeForType(p.type), description: p.description, date: p.date, timein: p.timein, breakout: p.breakout, breakin: p.breakin, timeout: p.timeout }
  show.value = true
}

async function save() {
  const url = editing.value ? `/hr/date-parameters/${currentId.value}` : '/hr/date-parameters'
  const method = editing.value ? 'PUT' : 'POST'
  try {
    const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }, body: JSON.stringify(form.value) })
    if (res.ok) {
      await Swal.fire({ icon: 'success', title: editing.value ? 'Date parameter updated' : 'Date parameter added', timer: 1200, showConfirmButton: false })
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

async function remove(p) {
  const result = await Swal.fire({ title: 'Delete this parameter?', text: 'This action cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', cancelButtonText: 'Cancel' })
  if (!result.isConfirmed) return
  try {
    const res = await fetch(`/hr/date-parameters/${p.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') } })
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
</script>
