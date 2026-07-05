<template>
  <Head title="Date Parameters" />
  <AdminLayout title="Date Parameters">
    <div class="space-y-5">

      <AppPageHeader title="Date Parameters">
        <template #actions>
          <AppButton @click="openModal()">
            <PlusIcon class="w-4 h-4" /> New Date Parameter
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filter bar -->
      <AppFilterBar>
        <input v-model="searchQuery" placeholder="Search..."
               class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64" />
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!paginated.length" :skeleton-cols="9">
        <template #head>
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
        </template>

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
              <AppIconButton label="Edit date parameter" @click="edit(p)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete date parameter" variant="danger" @click="remove(p)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="p in paginated" :key="p.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ displayType(p.type) }}</p>
                <p class="text-xs text-slate-400">{{ p.description || '—' }}</p>
              </div>
              <div class="flex items-center gap-1">
                <AppIconButton label="Edit date parameter" @click="edit(p)">
                  <PencilSquareIcon class="w-4 h-4" />
                </AppIconButton>
                <AppIconButton label="Delete date parameter" variant="danger" @click="remove(p)">
                  <TrashIcon class="w-4 h-4" />
                </AppIconButton>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-500">
              <span>Date: {{ p.date || '—' }}</span>
              <span>Time In: {{ p.timein || '—' }}</span>
              <span>Break Out: {{ p.breakout || '—' }}</span>
              <span>Break In: {{ p.breakin || '—' }}</span>
              <span>Time Out: {{ p.timeout || '—' }}</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No date parameters found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="goToPage(currentPage - 1)"
            @next="goToPage(currentPage + 1)"
            @page="goToPage"
          />
        </template>
      </AppTable>

    </div>

    <!-- Add/Edit Modal -->
    <AppModal :show="show" :title="editing ? 'Edit Date Parameter' : 'Add Date Parameter'" @close="closeModal">
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

      <template #footer>
        <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
        <AppButton @click="save">Save</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import Swal from 'sweetalert2'
import { PencilSquareIcon, TrashIcon, PlusIcon } from '@heroicons/vue/24/outline'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'

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
  const confirmed = await confirmDelete('This action cannot be undone.')
  if (!confirmed) return
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
