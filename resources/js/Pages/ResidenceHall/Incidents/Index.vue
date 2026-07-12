<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import { PlusIcon, MagnifyingGlassIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  incidents: Array,
  filters:   Object,
  myHall:    String,
})

const search  = ref(props.filters.search || '')
const typeF   = ref(props.filters.type || '')
const statusF = ref(props.filters.status || '')
const showAdd = ref(false)
const showEdit = ref(null)
const editData = ref({})

const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  const lower = search.value.toLowerCase()
  return props.incidents.filter(i =>
    (!lower || i.student_name.toLowerCase().includes(lower)) &&
    (!typeF.value || i.incident_type === typeF.value) &&
    (!statusF.value || i.status === statusF.value)
  )
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

function applyFilters() {
  currentPage.value = 1
  router.get(route('rh.incidents.index'), { type: typeF.value, status: statusF.value, search: search.value }, { preserveState: true, replace: true })
}

const addForm = useForm({
  rh_intern_id:         '',
  incident_type:        'health',
  description:          '',
  initial_intervention: '',
  referred_to:          '',
})

function submitAdd() {
  addForm.post(route('rh.incidents.store'), {
    preserveScroll: true,
    onSuccess: () => { showAdd.value = false; addForm.reset() },
  })
}

function openEdit(inc) {
  showEdit.value = inc
  editData.value = {
    initial_intervention: inc.initial_intervention || '',
    referred_to:          inc.referred_to || '',
    follow_up_notes:      inc.follow_up_notes || '',
  }
}

function submitEdit() {
  router.put(route('rh.incidents.update', showEdit.value.id), editData.value, {
    preserveScroll: true,
    onSuccess: () => { showEdit.value = null },
  })
}

async function resolve(inc) {
  const confirmed = await confirmAction({ title: 'Resolve incident?', text: 'Mark this incident as resolved?', confirmText: 'Resolve' })
  if (!confirmed) return
  router.post(route('rh.incidents.resolve', inc.id), {}, { preserveScroll: true })
}

const typeColor = (t) => ({
  health:        'red',
  behavioral:    'amber',
  psychological: 'purple',
}[t] || 'slate')

const fmtDate = (d) => d
  ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
  : '—'
</script>

<template>
  <Head title="Incidents" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-5">

      <AppPageHeader title="Incident Log" subtitle="Health, behavioral, and psychological incident records">
        <template #actions>
          <AppButton @click="showAdd = true">
            <PlusIcon class="w-4 h-4" /> Log Incident
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
          <select v-model="typeF" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Types</option>
            <option value="health">Health</option>
            <option value="behavioral">Behavioral</option>
            <option value="psychological">Psychological</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
          <select v-model="statusF" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All</option>
            <option value="open">Open</option>
            <option value="resolved">Resolved</option>
          </select>
        </div>
        <div class="flex-1 min-w-[200px]">
          <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
          <div class="relative">
            <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input v-model="search" @input="currentPage = 1" type="text" placeholder="Student name…"
                   class="w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!displayed.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="text-left px-4 py-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Student</th>
            <th class="text-left px-4 py-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider hidden md:table-cell">Hall</th>
            <th class="text-left px-4 py-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Type</th>
            <th class="text-left px-4 py-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider hidden lg:table-cell">Description</th>
            <th class="text-left px-4 py-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
            <th class="text-left px-4 py-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="inc in displayed" :key="inc.id" class="hover:bg-slate-50 transition-colors">
          <td class="px-4 py-3 font-medium text-slate-800">{{ inc.student_name }}</td>
          <td class="px-4 py-3 hidden md:table-cell">
            <AppBadge v-if="inc.residence_hall" :color="inc.residence_hall === 'BRH' ? 'indigo' : 'purple'">{{ inc.residence_hall }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <AppBadge :color="typeColor(inc.incident_type)" class="capitalize">{{ inc.incident_type }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-600 max-w-xs truncate hidden lg:table-cell">{{ inc.description }}</td>
          <td class="px-4 py-3 text-slate-500 text-xs">{{ fmtDate(inc.created_at) }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="inc.status === 'open' ? 'amber' : 'green'">{{ inc.status }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <AppButton size="sm" variant="ghost" @click="openEdit(inc)">Edit</AppButton>
              <AppButton v-if="inc.status === 'open'" size="sm" variant="success" @click="resolve(inc)">Resolve</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="inc in displayed" :key="inc.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ inc.student_name }}</p>
                <AppBadge v-if="inc.residence_hall" :color="inc.residence_hall === 'BRH' ? 'indigo' : 'purple'">{{ inc.residence_hall }}</AppBadge>
              </div>
              <AppBadge :color="inc.status === 'open' ? 'amber' : 'green'">{{ inc.status }}</AppBadge>
            </div>
            <AppBadge :color="typeColor(inc.incident_type)" class="capitalize">{{ inc.incident_type }}</AppBadge>
            <p class="text-xs text-slate-500">{{ inc.description }}</p>
            <p class="text-xs text-slate-400">{{ fmtDate(inc.created_at) }}</p>
            <div class="flex items-center gap-2 pt-1">
              <AppButton size="sm" variant="ghost" @click="openEdit(inc)">Edit</AppButton>
              <AppButton v-if="inc.status === 'open'" size="sm" variant="success" @click="resolve(inc)">Resolve</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No incidents found" />
        </template>

        <template #footer>
          <PaginationControl
            v-if="totalPages > 1"
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

    </div>

    <!-- Add Incident Modal -->
    <AppModal :show="showAdd" title="Log Incident" size="lg" @close="showAdd = false; addForm.reset()">
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Dormer ID</label>
          <input v-model.number="addForm.rh_intern_id" type="number" placeholder="Intern record ID"
                 class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Incident Type</label>
          <select v-model="addForm.incident_type"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="health">Health</option>
            <option value="behavioral">Behavioral</option>
            <option value="psychological">Psychological</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
          <textarea v-model="addForm.description" rows="3"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="Describe the incident…"></textarea>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Initial Intervention</label>
          <textarea v-model="addForm.initial_intervention" rows="2"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="Actions taken immediately…"></textarea>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Referred To</label>
          <input v-model="addForm.referred_to" type="text"
                 placeholder="e.g. School Nurse, Guidance Counselor"
                 class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
      </div>

      <template #footer>
        <div class="flex gap-3 w-full">
          <AppButton block variant="secondary" @click="showAdd = false; addForm.reset()">Cancel</AppButton>
          <AppButton block :disabled="!addForm.rh_intern_id || !addForm.description" :loading="addForm.processing" @click="submitAdd">
            Log Incident
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- Edit Incident Modal -->
    <AppModal :show="!!showEdit" title="Update Incident" :subtitle="showEdit ? `${showEdit.student_name} — ${showEdit.incident_type}` : ''" size="lg" @close="showEdit = null">
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Initial Intervention</label>
          <textarea v-model="editData.initial_intervention" rows="2"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Referred To</label>
          <input v-model="editData.referred_to" type="text"
                 class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Follow-up Notes</label>
          <textarea v-model="editData.follow_up_notes" rows="3"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
        </div>
      </div>

      <template #footer>
        <div class="flex gap-3 w-full">
          <AppButton block variant="secondary" @click="showEdit = null">Cancel</AppButton>
          <AppButton block @click="submitEdit">Save</AppButton>
        </div>
      </template>
    </AppModal>

  </AdminLayout>
</template>
