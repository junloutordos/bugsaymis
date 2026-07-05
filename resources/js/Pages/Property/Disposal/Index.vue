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
import { PlusIcon, MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({ disposals: Array, bsrs: Array, propertyItems: Array, officers: Array })

const tab = ref('bsr')
const search = ref('')
const PER_PAGE = 15
const currentPage = ref(1)

const showBsrModal = ref(false)
const showDisposalModal = ref(false)
const submitting = ref(false)
const errors = ref({})

const bsrForm = ref({ bsr_date: new Date().toISOString().split('T')[0], board_members:[], remarks:'', items:[] })
const memberName = ref('')
const disposalForm = ref({ bsr_id:'', disposal_date: new Date().toISOString().split('T')[0], method:'sale', net_proceeds:'', remarks:'' })

const METHODS = ['sale','donation','destruction','trade_in','barter','condemnation']

const filteredBsrs = computed(() => {
  if (!search.value) return props.bsrs
  const q = search.value.toLowerCase()
  return props.bsrs.filter(b => b.bsr_number.toLowerCase().includes(q))
})
const filteredDisposals = computed(() => {
  if (!search.value) return props.disposals
  const q = search.value.toLowerCase()
  return props.disposals.filter(d => d.disposal_number.toLowerCase().includes(q))
})
const displayedBsrs = computed(() => filteredBsrs.value.slice((currentPage.value-1)*PER_PAGE, currentPage.value*PER_PAGE))
const displayedDisposals = computed(() => filteredDisposals.value.slice((currentPage.value-1)*PER_PAGE, currentPage.value*PER_PAGE))
const totalPages = computed(() => {
  const list = tab.value==='bsr' ? filteredBsrs.value : filteredDisposals.value
  return Math.max(1, Math.ceil(list.length / PER_PAGE))
})

function bsrStatusColor(status) {
  const map = { draft: 'slate', approved: 'green' }
  return map[status] ?? 'slate'
}
function disposalStatusColor(status) {
  const map = { pending: 'amber', approved: 'blue', completed: 'green' }
  return map[status] ?? 'slate'
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function openBsrModal() {
  bsrForm.value = { bsr_date: new Date().toISOString().split('T')[0], board_members:[], remarks:'', items:[] }
  memberName.value = ''
  errors.value = {}
  showBsrModal.value = true
}

function addMember() {
  if (memberName.value.trim()) { bsrForm.value.board_members.push(memberName.value.trim()); memberName.value = '' }
}

function removeMember(idx) { bsrForm.value.board_members.splice(idx,1) }
function addBsrItem() { bsrForm.value.items.push({ property_item_id:'', condition:'', recommendation:'for_disposal', appraised_value:'' }) }
function removeBsrItem(idx) { bsrForm.value.items.splice(idx,1) }

async function submitBsr() {
  if (!bsrForm.value.items.length) { alert('Add at least one item.'); return }
  submitting.value=true; errors.value={}
  try {
    await axios.post(route('property.disposal.bsr.store'), bsrForm.value)
    showBsrModal.value=false
    window.location.reload()
  } catch(e) {
    if (e.response?.status===422) errors.value = e.response.data.errors??{}
  } finally { submitting.value=false }
}

async function approveBsr(id) {
  if (!await confirmAction({ title: 'Approve BSR?', text: 'Approve this Board of Survey Report?', confirmText: 'Approve' })) return
  await axios.post(route('property.disposal.bsr.approve', id))
  window.location.reload()
}

function openDisposalModal() {
  disposalForm.value = { bsr_id:'', disposal_date: new Date().toISOString().split('T')[0], method:'sale', net_proceeds:'', remarks:'' }
  errors.value = {}
  showDisposalModal.value = true
}

async function submitDisposal() {
  submitting.value=true; errors.value={}
  try {
    await axios.post(route('property.disposal.store'), disposalForm.value)
    showDisposalModal.value=false
    window.location.reload()
  } catch(e) {
    if (e.response?.status===422) errors.value = e.response.data.errors??{}
  } finally { submitting.value=false }
}

async function approveDisposal(id) {
  if (!await confirmAction({ title: 'Approve disposal?', text: 'Approve this disposal record?', confirmText: 'Approve' })) return
  await axios.post(route('property.disposal.approve', id))
  window.location.reload()
}

async function completeDisposal(id) {
  if (!await confirmAction({ title: 'Complete disposal?', text: 'Mark disposal as completed? This will permanently mark affected items as disposed.', confirmText: 'Complete' })) return
  await axios.post(route('property.disposal.complete', id))
  window.location.reload()
}
</script>
<template>
  <Head title="Property Disposal" />
  <AdminLayout title="Property Disposal">
    <div class="space-y-5">

      <AppPageHeader title="Property Disposal" subtitle="Board of Survey Reports and disposal records for unserviceable property." />

      <!-- Tabs -->
      <div class="flex gap-1 bg-slate-100 rounded-lg p-1 w-fit">
        <button @click="tab='bsr';search='';currentPage=1" :class="tab==='bsr'?'bg-white shadow text-slate-800':'text-slate-500 hover:text-slate-700'" class="px-4 py-1.5 rounded-md text-sm font-medium transition-all">Board of Survey</button>
        <button @click="tab='disposal';search='';currentPage=1" :class="tab==='disposal'?'bg-white shadow text-slate-800':'text-slate-500 hover:text-slate-700'" class="px-4 py-1.5 rounded-md text-sm font-medium transition-all">Disposal Records</button>
      </div>

      <!-- BSR Tab -->
      <template v-if="tab==='bsr'">
        <AppFilterBar>
          <div class="relative flex-1 min-w-48 sm:min-w-64">
            <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input v-model="search" @input="currentPage=1" type="text" placeholder="Search BSR number…"
                   class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <template #actions>
            <AppButton @click="openBsrModal">
              <PlusIcon class="h-4 w-4" />
              New BSR
            </AppButton>
          </template>
        </AppFilterBar>

        <AppTable :is-empty="!displayedBsrs.length" :skeleton-cols="5">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">BSR No.</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Items</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
            </tr>
          </template>

          <tr v-for="b in displayedBsrs" :key="b.id" class="hover:bg-slate-50/60">
            <td class="px-4 py-3 text-sm font-medium text-indigo-700">{{ b.bsr_number }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ fmtDate(b.bsr_date) }}</td>
            <td class="px-4 py-3 text-center text-sm text-slate-600">{{ b.items_count }}</td>
            <td class="px-4 py-3 text-center"><AppBadge :color="bsrStatusColor(b.status)">{{ b.status }}</AppBadge></td>
            <td class="px-4 py-3 text-center">
              <AppButton v-if="b.status==='draft'" size="sm" variant="success" @click="approveBsr(b.id)">Approve</AppButton>
              <span v-else class="text-xs text-slate-400">—</span>
            </td>
          </tr>

          <template #mobileCard>
            <div v-for="b in displayedBsrs" :key="b.id" class="p-4 space-y-2">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-sm font-medium text-indigo-700">{{ b.bsr_number }}</p>
                  <p class="text-xs text-slate-500">{{ fmtDate(b.bsr_date) }} &middot; {{ b.items_count }} item(s)</p>
                </div>
                <AppBadge :color="bsrStatusColor(b.status)">{{ b.status }}</AppBadge>
              </div>
              <AppButton v-if="b.status==='draft'" size="sm" variant="success" @click="approveBsr(b.id)">Approve</AppButton>
            </div>
          </template>

          <template #empty>
            <EmptyState title="No BSR records found" />
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
      </template>

      <!-- Disposal Tab -->
      <template v-else>
        <AppFilterBar>
          <div class="relative flex-1 min-w-48 sm:min-w-64">
            <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input v-model="search" @input="currentPage=1" type="text" placeholder="Search disposal number…"
                   class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <template #actions>
            <AppButton @click="openDisposalModal">
              <PlusIcon class="h-4 w-4" />
              New Disposal
            </AppButton>
          </template>
        </AppFilterBar>

        <AppTable :is-empty="!displayedDisposals.length" :skeleton-cols="7">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Disposal No.</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">BSR</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Method</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Net Proceeds</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
            </tr>
          </template>

          <tr v-for="d in displayedDisposals" :key="d.id" class="hover:bg-slate-50/60">
            <td class="px-4 py-3 text-sm font-medium text-indigo-700">{{ d.disposal_number }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ fmtDate(d.disposal_date) }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ d.bsr_number ?? '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600 capitalize">{{ d.method.replace('_',' ') }}</td>
            <td class="px-4 py-3 text-right text-sm">{{ d.net_proceeds ? '₱'+Number(d.net_proceeds).toLocaleString('en-PH',{minimumFractionDigits:2}) : '—' }}</td>
            <td class="px-4 py-3 text-center"><AppBadge :color="disposalStatusColor(d.status)">{{ d.status }}</AppBadge></td>
            <td class="px-4 py-3 text-center">
              <div class="flex justify-center gap-2">
                <AppButton v-if="d.status==='pending'" size="sm" variant="primary" @click="approveDisposal(d.id)">Approve</AppButton>
                <AppButton v-if="d.status==='approved'" size="sm" variant="success" @click="completeDisposal(d.id)">Complete</AppButton>
                <span v-if="d.status==='completed'" class="text-xs text-slate-400">—</span>
              </div>
            </td>
          </tr>

          <template #mobileCard>
            <div v-for="d in displayedDisposals" :key="d.id" class="p-4 space-y-2">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-sm font-medium text-indigo-700">{{ d.disposal_number }}</p>
                  <p class="text-xs text-slate-500">{{ fmtDate(d.disposal_date) }} &middot; {{ d.bsr_number ?? '—' }}</p>
                </div>
                <AppBadge :color="disposalStatusColor(d.status)">{{ d.status }}</AppBadge>
              </div>
              <p class="text-xs text-slate-500 capitalize">{{ d.method.replace('_',' ') }} &middot; {{ d.net_proceeds ? '₱'+Number(d.net_proceeds).toLocaleString('en-PH',{minimumFractionDigits:2}) : '—' }}</p>
              <div class="flex gap-2 pt-1">
                <AppButton v-if="d.status==='pending'" size="sm" variant="primary" @click="approveDisposal(d.id)">Approve</AppButton>
                <AppButton v-if="d.status==='approved'" size="sm" variant="success" @click="completeDisposal(d.id)">Complete</AppButton>
              </div>
            </div>
          </template>

          <template #empty>
            <EmptyState title="No disposal records found" />
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
      </template>

    </div>

    <!-- BSR Modal -->
    <AppModal :show="showBsrModal" title="New Board of Survey Report" size="xl" @close="showBsrModal=false">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">BSR Date *</label>
          <input v-model="bsrForm.bsr_date" type="date" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Board Members</label>
          <div class="flex gap-2 mb-2">
            <input v-model="memberName" type="text" placeholder="Member name" @keydown.enter.prevent="addMember" class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <AppButton type="button" variant="secondary" @click="addMember">Add</AppButton>
          </div>
          <div class="flex flex-wrap gap-2">
            <span v-for="(m,i) in bsrForm.board_members" :key="i" class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 text-xs pl-2 pr-1 py-1 rounded-full">
              {{ m }}
              <AppIconButton label="Remove member" variant="ghost" size="sm" @click="removeMember(i)">
                <XMarkIcon class="h-3 w-3" />
              </AppIconButton>
            </span>
          </div>
        </div>
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-semibold text-slate-700">Items for Survey *</span>
            <button type="button" @click="addBsrItem" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ Add Item</button>
          </div>
          <div v-for="(item,idx) in bsrForm.items" :key="idx" class="border border-slate-100 rounded-lg p-3 mb-2">
            <div class="grid grid-cols-2 gap-2">
              <div class="col-span-2">
                <select v-model="item.property_item_id" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="">— Select property item —</option>
                  <option v-for="p in propertyItems" :key="p.id" :value="p.id">{{ p.property_number }} — {{ p.description }}</option>
                </select>
              </div>
              <div><input v-model="item.condition" type="text" placeholder="Condition" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" /></div>
              <div>
                <select v-model="item.recommendation" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="for_disposal">For Disposal</option>
                  <option value="for_repair">For Repair</option>
                  <option value="for_condemnation">For Condemnation</option>
                </select>
              </div>
              <div class="col-span-2 flex gap-2 items-center">
                <input v-model="item.appraised_value" type="number" step="0.01" min="0" placeholder="Appraised Value (₱)" class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                <AppIconButton label="Remove item" variant="danger" @click="removeBsrItem(idx)">
                  <XMarkIcon class="h-4 w-4" />
                </AppIconButton>
              </div>
            </div>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
          <textarea v-model="bsrForm.remarks" rows="2" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
        </div>
        <p v-if="Object.keys(errors).length" class="text-xs text-danger-600">{{ Object.values(errors).flat().join('; ') }}</p>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showBsrModal=false">Cancel</AppButton>
        <AppButton :loading="submitting" @click="submitBsr">Create BSR</AppButton>
      </template>
    </AppModal>

    <!-- Disposal Modal -->
    <AppModal :show="showDisposalModal" title="New Disposal Record" @close="showDisposalModal=false">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Board of Survey Report *</label>
          <select v-model="disposalForm.bsr_id" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">— Select approved BSR —</option>
            <option v-for="b in bsrs.filter(b=>b.status==='approved')" :key="b.id" :value="b.id">{{ b.bsr_number }}</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Disposal Date *</label>
            <input v-model="disposalForm.disposal_date" type="date" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Method *</label>
            <select v-model="disposalForm.method" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option v-for="m in METHODS" :key="m" :value="m">{{ m.replace('_',' ') }}</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Net Proceeds (₱)</label>
            <input v-model="disposalForm.net_proceeds" type="number" step="0.01" min="0" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
            <textarea v-model="disposalForm.remarks" rows="2" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
          </div>
        </div>
        <p v-if="Object.keys(errors).length" class="text-xs text-danger-600">{{ Object.values(errors).flat().join('; ') }}</p>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showDisposalModal=false">Cancel</AppButton>
        <AppButton :loading="submitting" @click="submitDisposal">Create</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
