<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import StudentSearchInput from '@/Components/StudentSearchInput.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import { PlusIcon, PrinterIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ items: Object, filters: Object, staff: Array })

const fmtDateTime = (d) => d ? new Date(d).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '—'
const statusColor = (s) => ({ held: 'amber', claimed: 'green', disposed: 'slate' }[s] || 'slate')

// ── Confiscate ────────────────────────────────────────────────────────────────
const showConfiscate = ref(false)
const confForm = useForm({ student_id: null, item_description: '', place: '', reason: '' })
function saveConfiscate() {
  confForm.post(route('discipline.confiscated.store'), {
    preserveScroll: true, onSuccess: () => { showConfiscate.value = false; confForm.reset() },
  })
}

// ── Claim ─────────────────────────────────────────────────────────────────────
const claiming = ref(null)
const claimForm = useForm({ claim_ack_role: 'adviser', claim_ack_by: '', claim_remarks: '' })
function openClaim(item) { claiming.value = item; claimForm.reset(); claimForm.claim_ack_role = 'adviser' }
function saveClaim() {
  claimForm.put(route('discipline.confiscated.claim', claiming.value.id), {
    preserveScroll: true, onSuccess: () => { claiming.value = null },
  })
}

async function dispose(item) {
  const ok = await confirmAction({ title: 'Mark this item as disposed?', confirmText: 'Yes, dispose' })
  if (ok) {
    router.put(route('discipline.confiscated.dispose', item.id), {}, { preserveScroll: true })
  }
}
function printPdf(item) { window.open(route('discipline.confiscated.pdf', item.id), '_blank') }
</script>

<template>
  <Head title="Confiscated Items" />
  <AdminLayout title="Confiscated Items">
    <div class="max-w-4xl mx-auto space-y-5">

      <AppPageHeader title="Confiscated Items" subtitle="Confiscation receipts and item claims">
        <template #actions>
          <AppButton @click="showConfiscate = true">
            <PlusIcon class="h-4 w-4" />
            Confiscate Item
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Confiscate form -->
      <AppCard v-if="showConfiscate" title="New Confiscation">
        <div class="space-y-4">
          <StudentSearchInput v-model="confForm.student_id" search-route-name="discipline.confiscated.students.search" label="Student" :error="confForm.errors.student_id" />
          <AppTextarea v-model="confForm.item_description" :rows="2" label="Item(s)" required :error="confForm.errors.item_description" />
          <div class="grid sm:grid-cols-2 gap-4">
            <AppInput v-model="confForm.place" label="Place" />
            <AppInput v-model="confForm.reason" label="Reason" />
          </div>
          <div class="flex justify-end gap-3">
            <AppButton variant="secondary" @click="showConfiscate = false">Cancel</AppButton>
            <AppButton :loading="confForm.processing" @click="saveConfiscate">Create Receipt</AppButton>
          </div>
        </div>
      </AppCard>

      <!-- Claim form -->
      <AppCard v-if="claiming" :title="`Process Claim — ${claiming.receipt_no}`">
        <div class="space-y-4">
          <p class="text-sm text-slate-500">{{ claiming.item_description }}</p>
          <div class="grid sm:grid-cols-2 gap-4">
            <AppSelect v-model="claimForm.claim_ack_role" label="Acknowledged by (role)" :show-blank="false">
              <option value="adviser">Homeroom Adviser</option>
              <option value="counselor">Guidance Counselor</option>
              <option value="dorm">Dorm Manager</option>
            </AppSelect>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Acknowledged by (person) <span class="text-red-500">*</span></label>
              <select v-model="claimForm.claim_ack_by"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      :class="{ 'border-red-400': claimForm.errors.claim_ack_by }">
                <option value="">— Select —</option>
                <option v-for="u in staff" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <p v-if="claimForm.errors.claim_ack_by" class="text-red-500 text-xs mt-1">{{ claimForm.errors.claim_ack_by }}</p>
            </div>
          </div>
          <AppInput v-model="claimForm.claim_remarks" label="Remarks" />
          <div class="flex justify-end gap-3">
            <AppButton variant="secondary" @click="claiming = null">Cancel</AppButton>
            <AppButton :loading="claimForm.processing" @click="saveClaim">Mark Claimed</AppButton>
          </div>
        </div>
      </AppCard>

      <!-- Table -->
      <AppTable :is-empty="!items.data.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Receipt</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Student</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Item</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Confiscated</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="i in items.data" :key="i.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 font-medium text-slate-700">{{ i.receipt_no }}</td>
          <td class="px-4 py-3 text-slate-600">{{ i.student_name }}</td>
          <td class="px-4 py-3 text-slate-600 max-w-[220px] truncate">{{ i.item_description }}</td>
          <td class="px-4 py-3 text-slate-500">{{ fmtDateTime(i.confiscated_at) }}</td>
          <td class="px-4 py-3"><AppBadge :color="statusColor(i.status)">{{ i.status }}</AppBadge></td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            <div class="flex items-center justify-end gap-1">
              <AppIconButton label="Print receipt" @click="printPdf(i)">
                <PrinterIcon class="w-5 h-5" />
              </AppIconButton>
              <button v-if="i.status === 'held'" @click="openClaim(i)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium px-2">Claim</button>
              <button v-if="i.status === 'held'" @click="dispose(i)" class="text-xs text-slate-400 hover:text-danger-600 px-2">Dispose</button>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="i in items.data" :key="i.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-mono text-xs text-slate-500">{{ i.receipt_no }}</p>
                <p class="font-medium text-slate-800">{{ i.student_name }}</p>
                <p class="text-xs text-slate-500">{{ i.item_description }}</p>
              </div>
              <AppBadge :color="statusColor(i.status)">{{ i.status }}</AppBadge>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500">
              <span>Confiscated {{ fmtDateTime(i.confiscated_at) }}</span>
              <div class="flex items-center gap-2">
                <AppIconButton label="Print receipt" size="sm" @click="printPdf(i)">
                  <PrinterIcon class="w-4 h-4" />
                </AppIconButton>
                <button v-if="i.status === 'held'" @click="openClaim(i)" class="text-indigo-600 hover:text-indigo-800 font-medium">Claim</button>
                <button v-if="i.status === 'held'" @click="dispose(i)" class="text-slate-400 hover:text-danger-600">Dispose</button>
              </div>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No confiscated items" />
        </template>

        <template #footer>
          <PaginationControl
            :links="items.links"
            :current-page="items.current_page"
            :total-pages="items.last_page"
            :total="items.total"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
