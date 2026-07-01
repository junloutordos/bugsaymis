<template>
  <AdminLayout title="Date Parameters">
    <div>
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Date Parameters</h1>
        <button @click="openModal()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <PlusIcon class="w-4 h-4" /> New Date Parameter
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
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Description</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Time In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Break Out</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Break In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Time Out</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="p in paginated" :key="p.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ p.id }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ displayType(p.type) }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ p.description || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ p.date || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ p.timein || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ p.breakout || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ p.breakin || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ p.timeout || '—' }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1">
                    <button @click="edit(p)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button @click="remove(p)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-red-600 transition-colors" title="Delete">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="paginated.length === 0">
                <td colspan="9" class="py-16 text-center text-slate-400 text-sm">No date parameters found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
                <PaginationControl
          :current-page="currentPage"
          :total-pages="totalPages"
          @prev="goToPage(currentPage - 1)"
          @next="goToPage(currentPage + 1)"
          @page="goToPage"
        />
      </div>

      <!-- Modal -->
      <div v-show="show" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md relative">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">{{ editing ? 'Edit' : 'Add' }} Date Parameter</h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeModal"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>
          <div class="px-6 py-5">
            <div class="grid grid-cols-1 gap-3">
              <div>
                <span class="block text-xs font-medium text-slate-600 mb-1">Type</span>
                <select v-model="form.type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full">
                  <option value="">-- Select Type --</option>
                  <option value="HOL">Holiday</option>
                  <option value="SUSPENDED">Work Suspension</option>
                  <option value="WFH">Work From Home</option>
                </select>
              </div>
              <div>
                <span class="block text-xs font-medium text-slate-600 mb-1">Description</span>
                <input v-model="form.description" placeholder="Description" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              </div>
              <div>
                <span class="block text-xs font-medium text-slate-600 mb-1">Date</span>
                <input v-model="form.date" type="date" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              </div>
              <div>
                <span class="block text-xs font-medium text-slate-600 mb-1">Time In</span>
                <input v-model="form.timein" type="time" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              </div>
              <div>
                <span class="block text-xs font-medium text-slate-600 mb-1">Break Out</span>
                <input v-model="form.breakout" type="time" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              </div>
              <div>
                <span class="block text-xs font-medium text-slate-600 mb-1">Break In</span>
                <input v-model="form.breakin" type="time" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              </div>
              <div>
                <span class="block text-xs font-medium text-slate-600 mb-1">Time Out</span>
                <input v-model="form.timeout" type="time" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="closeModal" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click="save" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Save</button>
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
