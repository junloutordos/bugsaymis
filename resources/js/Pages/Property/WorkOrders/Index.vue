<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { PlusIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({ workOrders: Array, propertyItems: Array, officers: Array })

const search = ref('')
const filterStatus = ref('')
const PER_PAGE = 15
const currentPage = ref(1)
const showModal = ref(false)
const submitting = ref(false)
const errors = ref({})

const form = ref({ property_item_id:'', description:'', priority:'normal', assigned_to_id:'', requested_date: new Date().toISOString().split('T')[0], target_completion_date:'', estimated_cost:'', remarks:'' })

const STATUSES = ['pending','in_progress','completed','cancelled']
const PRIORITIES = ['low','normal','high','urgent']

const filtered = computed(() => {
  let list = props.workOrders
  if (filterStatus.value) list = list.filter(w => w.status===filterStatus.value)
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(w => w.work_order_number.toLowerCase().includes(q) || (w.description||'').toLowerCase().includes(q))
  }
  return list
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => filtered.value.slice((currentPage.value-1)*PER_PAGE, currentPage.value*PER_PAGE))

function statusColor(status) {
  const map = { pending: 'amber', in_progress: 'blue', completed: 'green', cancelled: 'slate' }
  return map[status] ?? 'slate'
}
function priorityColor(priority) {
  const map = { low: 'slate', normal: 'blue', high: 'amber', urgent: 'red' }
  return map[priority] ?? 'slate'
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function openModal() { form.value = { property_item_id:'', description:'', priority:'normal', assigned_to_id:'', requested_date: new Date().toISOString().split('T')[0], target_completion_date:'', estimated_cost:'', remarks:'' }; errors.value={}; showModal.value=true }

async function submit() {
  submitting.value=true; errors.value={}
  try {
    await axios.post(route('property.work-orders.store'), form.value)
    showModal.value=false
    window.location.reload()
  } catch(e) {
    if (e.response?.status===422) errors.value = e.response.data.errors??{}
  } finally { submitting.value=false }
}

async function updateStatus(id, status) {
  await axios.patch(route('property.work-orders.update', id), { status })
  window.location.reload()
}
</script>
<template>
  <Head title="Work Orders" />
  <AdminLayout title="Work Orders">
    <div class="space-y-5">

      <AppPageHeader title="Work Orders" subtitle="Track repair and maintenance work orders for property items.">
        <template #actions>
          <AppButton @click="openModal">
            <PlusIcon class="h-4 w-4" />
            New Work Order
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative flex-1 min-w-48 sm:min-w-64">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input v-model="search" @input="currentPage=1" type="text" placeholder="Search work order or description…"
                 class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <select v-model="filterStatus" @change="currentPage=1"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Statuses</option>
          <option v-for="s in STATUSES" :key="s" :value="s">{{ s.replace('_',' ') }}</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!displayed.length" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">WO No.</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Description</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Property</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Priority</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Assigned To</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Target Date</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
          </tr>
        </template>

        <tr v-for="wo in displayed" :key="wo.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm font-medium text-indigo-700">{{ wo.work_order_number }}</td>
          <td class="px-4 py-3 text-sm text-slate-800 max-w-xs truncate">{{ wo.description }}</td>
          <td class="px-4 py-3 text-sm text-slate-600 font-mono">{{ wo.property_number ?? '—' }}</td>
          <td class="px-4 py-3 text-center"><AppBadge :color="priorityColor(wo.priority)">{{ wo.priority }}</AppBadge></td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ wo.assigned_to ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ fmtDate(wo.target_completion_date) }}</td>
          <td class="px-4 py-3 text-center"><AppBadge :color="statusColor(wo.status)">{{ wo.status.replace('_',' ') }}</AppBadge></td>
          <td class="px-4 py-3 text-center">
            <select v-if="wo.status!=='completed'&&wo.status!=='cancelled'" @change="e=>updateStatus(wo.id,e.target.value)" :value="wo.status"
                    class="text-xs rounded-lg border border-slate-200 bg-white px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-400">
              <option v-for="s in STATUSES" :key="s" :value="s">{{ s.replace('_',' ') }}</option>
            </select>
            <span v-else class="text-xs text-slate-400">—</span>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="wo in displayed" :key="wo.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-sm font-medium text-indigo-700">{{ wo.work_order_number }}</p>
                <p class="text-sm text-slate-800">{{ wo.description }}</p>
                <p class="text-xs text-slate-400 font-mono">{{ wo.property_number ?? '—' }}</p>
              </div>
              <AppBadge :color="statusColor(wo.status)">{{ wo.status.replace('_',' ') }}</AppBadge>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500">
              <AppBadge :color="priorityColor(wo.priority)">{{ wo.priority }}</AppBadge>
              <span>{{ wo.assigned_to ?? 'Unassigned' }}</span>
            </div>
            <div class="flex items-center justify-between pt-1">
              <span class="text-xs text-slate-400">Target: {{ fmtDate(wo.target_completion_date) }}</span>
              <select v-if="wo.status!=='completed'&&wo.status!=='cancelled'" @change="e=>updateStatus(wo.id,e.target.value)" :value="wo.status"
                      class="text-xs rounded-lg border border-slate-200 bg-white px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                <option v-for="s in STATUSES" :key="s" :value="s">{{ s.replace('_',' ') }}</option>
              </select>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No work orders found" />
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

    <!-- New Work Order Modal -->
    <AppModal :show="showModal" title="New Work Order" size="lg" @close="showModal=false">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Description *</label>
          <textarea v-model="form.description" required rows="2" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Property Item</label>
            <select v-model="form.property_item_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">— Not linked —</option>
              <option v-for="p in propertyItems" :key="p.id" :value="p.id">{{ p.property_number }} — {{ p.description }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Priority</label>
            <select v-model="form.priority" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option v-for="p in PRIORITIES" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Assigned To</label>
            <select v-model="form.assigned_to_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">— Unassigned —</option>
              <option v-for="u in officers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Requested Date *</label>
            <input v-model="form.requested_date" type="date" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Target Completion</label>
            <input v-model="form.target_completion_date" type="date" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Estimated Cost (₱)</label>
            <input v-model="form.estimated_cost" type="number" step="0.01" min="0" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
            <textarea v-model="form.remarks" rows="1" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
          </div>
        </div>
        <p v-if="Object.keys(errors).length" class="text-xs text-danger-600">{{ Object.values(errors).flat().join('; ') }}</p>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showModal=false">Cancel</AppButton>
        <AppButton :loading="submitting" @click="submit">Create</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
