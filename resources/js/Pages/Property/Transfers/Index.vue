<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import { PlusIcon, MagnifyingGlassIcon, XMarkIcon, CheckIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({ transfers: Array, propertyItems: Array, officers: Array })

const search = ref('')
const PER_PAGE = 15
const currentPage = ref(1)
const showModal = ref(false)
const submitting = ref(false)
const errors = ref({})

const form = ref({ transfer_date: new Date().toISOString().split('T')[0], from_officer_id:'', to_officer_id:'', remarks:'', items:[] })

const filtered = computed(() => {
  if (!search.value) return props.transfers
  const q = search.value.toLowerCase()
  return props.transfers.filter(t =>
    t.transfer_number.toLowerCase().includes(q) ||
    (t.from_officer||'').toLowerCase().includes(q) ||
    (t.to_officer||'').toLowerCase().includes(q)
  )
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => filtered.value.slice((currentPage.value-1)*PER_PAGE, currentPage.value*PER_PAGE))

function statusColor(status) {
  const map = { pending: 'amber', completed: 'green', cancelled: 'slate' }
  return map[status] ?? 'slate'
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function openModal() { form.value = { transfer_date: new Date().toISOString().split('T')[0], from_officer_id:'', to_officer_id:'', remarks:'', items:[] }; errors.value={}; showModal.value=true }
function addItem() { form.value.items.push({ property_item_id:'' }) }
function removeItem(idx) { form.value.items.splice(idx,1) }

async function submit() {
  if (!form.value.items.length) { alert('Add at least one property item.'); return }
  submitting.value=true; errors.value={}
  try {
    await axios.post(route('property.transfers.store'), form.value)
    showModal.value=false
    window.location.reload()
  } catch(e) {
    if (e.response?.status===422) errors.value = e.response.data.errors??{}
  } finally { submitting.value=false }
}

async function complete(id) {
  if (!await confirmAction({ title: 'Mark as completed?', text: 'Mark this transfer as completed? This will update accountability records.', confirmText: 'Complete' })) return
  await axios.post(route('property.transfers.complete', id))
  window.location.reload()
}

async function cancel(id) {
  if (!await confirmAction({ title: 'Cancel transfer?', text: 'Cancel this transfer?', confirmText: 'Cancel Transfer' })) return
  await axios.post(route('property.transfers.cancel', id))
  window.location.reload()
}
</script>
<template>
  <Head title="Property Transfers" />
  <AdminLayout title="Property Transfers">
    <div class="space-y-5">

      <AppPageHeader title="Property Transfers" subtitle="Transfer accountability of property items between officers.">
        <template #actions>
          <AppButton @click="openModal">
            <PlusIcon class="h-4 w-4" />
            New Transfer
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative flex-1 min-w-48 sm:min-w-64">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input v-model="search" @input="currentPage=1" type="text" placeholder="Search transfer number or officer…"
                 class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!displayed.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Transfer No.</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">From</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">To</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Items</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
          </tr>
        </template>

        <tr v-for="t in displayed" :key="t.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm font-medium text-indigo-700">{{ t.transfer_number }}</td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ fmtDate(t.transfer_date) }}</td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ t.from_officer ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-800">{{ t.to_officer ?? '—' }}</td>
          <td class="px-4 py-3 text-center text-sm text-slate-600">{{ t.items_count }}</td>
          <td class="px-4 py-3 text-center"><AppBadge :color="statusColor(t.status)">{{ t.status }}</AppBadge></td>
          <td class="px-4 py-3 text-center">
            <div v-if="t.status==='pending'" class="flex justify-center gap-2">
              <AppButton size="sm" variant="success" @click="complete(t.id)">
                <CheckIcon class="h-3 w-3" />
                Complete
              </AppButton>
              <AppButton size="sm" variant="danger" @click="cancel(t.id)">
                <XMarkIcon class="h-3 w-3" />
                Cancel
              </AppButton>
            </div>
            <span v-else class="text-xs text-slate-400">—</span>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="t in displayed" :key="t.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-sm font-medium text-indigo-700">{{ t.transfer_number }}</p>
                <p class="text-xs text-slate-500">{{ fmtDate(t.transfer_date) }}</p>
              </div>
              <AppBadge :color="statusColor(t.status)">{{ t.status }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ t.from_officer ?? '—' }} &rarr; {{ t.to_officer ?? '—' }} &middot; {{ t.items_count }} item(s)</p>
            <div v-if="t.status==='pending'" class="flex gap-2 pt-1">
              <AppButton size="sm" variant="success" @click="complete(t.id)">
                <CheckIcon class="h-3 w-3" />
                Complete
              </AppButton>
              <AppButton size="sm" variant="danger" @click="cancel(t.id)">
                <XMarkIcon class="h-3 w-3" />
                Cancel
              </AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No transfer records found" />
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

    <!-- New Transfer Modal -->
    <AppModal :show="showModal" title="New Property Transfer" size="xl" @close="showModal=false">
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Transfer Date *</label>
            <input v-model="form.transfer_date" type="date" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div></div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">From Officer *</label>
            <select v-model="form.from_officer_id" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">— Select —</option>
              <option v-for="u in officers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">To Officer *</label>
            <select v-model="form.to_officer_id" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">— Select —</option>
              <option v-for="u in officers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
            <textarea v-model="form.remarks" rows="2" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
          </div>
        </div>
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-semibold text-slate-700">Property Items</span>
            <button type="button" @click="addItem" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ Add Item</button>
          </div>
          <p v-if="!form.items.length" class="text-xs text-slate-400 text-center py-3">No items added.</p>
          <div v-for="(item,idx) in form.items" :key="idx" class="flex gap-2 mb-2">
            <select v-model="item.property_item_id" required class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">— Select property item —</option>
              <option v-for="p in propertyItems" :key="p.id" :value="p.id">{{ p.property_number }} — {{ p.description }}</option>
            </select>
            <AppIconButton label="Remove item" variant="danger" @click="removeItem(idx)">
              <XMarkIcon class="h-4 w-4" />
            </AppIconButton>
          </div>
        </div>
        <p v-if="errors.items" class="text-xs text-danger-600">{{ errors.items }}</p>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showModal=false">Cancel</AppButton>
        <AppButton :loading="submitting" @click="submit">Create Transfer</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
