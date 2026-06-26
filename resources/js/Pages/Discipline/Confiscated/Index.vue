<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StudentSearchInput from '@/Components/StudentSearchInput.vue'
import { PlusIcon, PrinterIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ items: Object, filters: Object, staff: Array })

const fmtDateTime = (d) => d ? new Date(d).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '—'
const statusClass = (s) => ({ held: 'bg-amber-100 text-amber-700', claimed: 'bg-emerald-100 text-emerald-700', disposed: 'bg-slate-100 text-slate-600' }[s] || 'bg-slate-100 text-slate-600')

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

function dispose(item) {
  if (confirm('Mark this item as disposed?')) {
    router.put(route('discipline.confiscated.dispose', item.id), {}, { preserveScroll: true })
  }
}
function printPdf(item) { window.open(route('discipline.confiscated.pdf', item.id), '_blank') }
</script>

<template>
  <Head title="Confiscated Items" />
  <AdminLayout title="Confiscated Items">
    <div class="max-w-4xl mx-auto space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Confiscated Items</h1>
          <p class="text-sm text-slate-500">Confiscation receipts and item claims</p>
        </div>
        <button @click="showConfiscate = true" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          <PlusIcon class="w-4 h-4" /> Confiscate Item
        </button>
      </div>

      <!-- Confiscate form -->
      <div v-if="showConfiscate" class="bg-white rounded-xl border border-indigo-100 shadow-sm p-5 space-y-4">
        <h3 class="font-semibold text-slate-700">New Confiscation</h3>
        <StudentSearchInput v-model="confForm.student_id" search-route-name="discipline.confiscated.students.search" label="Student" :error="confForm.errors.student_id" />
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Item(s) <span class="text-red-500">*</span></label>
          <textarea v-model="confForm.item_description" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-y"
                    :class="{ 'border-red-400': confForm.errors.item_description }"></textarea>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Place</label>
            <input v-model="confForm.place" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Reason</label>
            <input v-model="confForm.reason" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="showConfiscate = false" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
          <button @click="saveConfiscate" :disabled="confForm.processing" class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">Create Receipt</button>
        </div>
      </div>

      <!-- Claim form -->
      <div v-if="claiming" class="bg-white rounded-xl border border-emerald-100 shadow-sm p-5 space-y-4">
        <h3 class="font-semibold text-slate-700">Process Claim — {{ claiming.receipt_no }}</h3>
        <p class="text-sm text-slate-500">{{ claiming.item_description }}</p>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Acknowledged by (role)</label>
            <select v-model="claimForm.claim_ack_role" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
              <option value="adviser">Homeroom Adviser</option>
              <option value="counselor">Guidance Counselor</option>
              <option value="dorm">Dorm Manager</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Acknowledged by (person) <span class="text-red-500">*</span></label>
            <select v-model="claimForm.claim_ack_by" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500"
                    :class="{ 'border-red-400': claimForm.errors.claim_ack_by }">
              <option value="">— Select —</option>
              <option v-for="u in staff" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
          <input v-model="claimForm.claim_remarks" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500" />
        </div>
        <div class="flex justify-end gap-3">
          <button @click="claiming = null" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
          <button @click="saveClaim" :disabled="claimForm.processing" class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">Mark Claimed</button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100">
              <th class="px-4 py-3">Receipt</th>
              <th class="px-4 py-3">Student</th>
              <th class="px-4 py-3">Item</th>
              <th class="px-4 py-3">Confiscated</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="i in items.data" :key="i.id" class="border-b border-slate-50 hover:bg-slate-50/60">
              <td class="px-4 py-3 font-medium text-slate-700">{{ i.receipt_no }}</td>
              <td class="px-4 py-3 text-slate-600">{{ i.student_name }}</td>
              <td class="px-4 py-3 text-slate-600 max-w-[220px] truncate">{{ i.item_description }}</td>
              <td class="px-4 py-3 text-slate-500">{{ fmtDateTime(i.confiscated_at) }}</td>
              <td class="px-4 py-3"><span class="px-2 py-1 rounded-md text-xs font-medium" :class="statusClass(i.status)">{{ i.status }}</span></td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <button @click="printPdf(i)" class="text-slate-400 hover:text-slate-700 mr-3"><PrinterIcon class="w-4 h-4 inline" /></button>
                <button v-if="i.status === 'held'" @click="openClaim(i)" class="text-emerald-600 hover:text-emerald-800 font-medium mr-3">Claim</button>
                <button v-if="i.status === 'held'" @click="dispose(i)" class="text-slate-400 hover:text-rose-600">Dispose</button>
              </td>
            </tr>
            <tr v-if="!items.data.length"><td colspan="6" class="px-4 py-10 text-center text-slate-400">No confiscated items.</td></tr>
          </tbody>
        </table>
      </div>

      <div v-if="items.links?.length > 3" class="flex flex-wrap gap-1 justify-center">
        <template v-for="(l, i) in items.links" :key="i">
          <a v-if="l.url" :href="l.url" v-html="l.label" class="px-3 py-1.5 rounded-md text-sm border"
             :class="l.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"></a>
          <span v-else v-html="l.label" class="px-3 py-1.5 rounded-md text-sm text-slate-300"></span>
        </template>
      </div>
    </div>
  </AdminLayout>
</template>
