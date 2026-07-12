<template>
  <Head title="Lost & Found" />
  <AdminLayout title="Lost & Found">
    <div class="space-y-5">

      <AppPageHeader title="Lost & Found"
        subtitle="Report lost items, turn over found items to GSU, and earn honesty points">
        <template #actions>
          <AppButton v-if="canManage" variant="secondary" @click="openWalkIn()">
            <InboxArrowDownIcon class="h-4 w-4" /> Log Walk-in Turnover
          </AppButton>
          <AppButton @click="openReport()">
            <PlusIcon class="h-4 w-4" /> Report Item
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length"
        class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm space-y-1">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- Stat cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <AppCard :padded="false">
          <div class="p-4 flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-amber-50 flex items-center justify-center shrink-0">
              <SparklesIcon class="h-5 w-5 text-amber-600" />
            </div>
            <div>
              <p class="text-2xl font-bold text-slate-800 leading-tight">{{ myPoints }}</p>
              <p class="text-xs text-slate-500">My Honesty Points</p>
            </div>
          </div>
        </AppCard>
        <AppCard :padded="false">
          <div class="p-4 flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-indigo-50 flex items-center justify-center shrink-0">
              <ArchiveBoxIcon class="h-5 w-5 text-indigo-600" />
            </div>
            <div>
              <p class="text-2xl font-bold text-slate-800 leading-tight">{{ board.length }}</p>
              <p class="text-xs text-slate-500">Items in GSU Custody</p>
            </div>
          </div>
        </AppCard>
        <AppCard :padded="false">
          <div class="p-4 flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-blue-50 flex items-center justify-center shrink-0">
              <DocumentTextIcon class="h-5 w-5 text-blue-600" />
            </div>
            <div>
              <p class="text-2xl font-bold text-slate-800 leading-tight">{{ myReports.length }}</p>
              <p class="text-xs text-slate-500">My Reports</p>
            </div>
          </div>
        </AppCard>
        <AppCard v-if="canManage" :padded="false">
          <div class="p-4 flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-warning-50 flex items-center justify-center shrink-0">
              <ClockIcon class="h-5 w-5 text-warning-600" />
            </div>
            <div>
              <p class="text-2xl font-bold text-slate-800 leading-tight">{{ pendingTurnover.length }}</p>
              <p class="text-xs text-slate-500">Awaiting Turnover</p>
            </div>
          </div>
        </AppCard>
        <AppCard v-else :padded="false">
          <div class="p-4 flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-purple-50 flex items-center justify-center shrink-0">
              <TrophyIcon class="h-5 w-5 text-purple-600" />
            </div>
            <div>
              <p class="text-2xl font-bold text-slate-800 leading-tight">{{ leaderboard[0]?.points ?? 0 }}</p>
              <p class="text-xs text-slate-500 truncate">Top: {{ leaderboard[0]?.name ?? '—' }}</p>
            </div>
          </div>
        </AppCard>
      </div>

      <!-- Tabs -->
      <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm bg-white">
        <button v-for="t in tabs" :key="t.key" type="button" @click="tab = t.key"
          :class="['px-4 py-2 font-medium transition-colors border-l border-slate-200 first:border-l-0',
            tab === t.key ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50']">
          {{ t.label }}
        </button>
      </div>

      <!-- ── Found items board ─────────────────────────────────────────── -->
      <div v-if="tab === 'board'">
        <AppCard v-if="board.length === 0">
          <EmptyState title="No items in custody"
            subtitle="Found items turned over to GSU will appear here so owners can claim them."
            :icon="ArchiveBoxIcon" />
        </AppCard>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <div v-for="item in board" :key="item.id"
            class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden flex flex-col">
            <div class="h-36 bg-slate-100 flex items-center justify-center overflow-hidden">
              <img v-if="item.has_photo" :src="route('lost-found.photo', item.id)"
                class="w-full h-full object-cover" loading="lazy" alt="" />
              <ArchiveBoxIcon v-else class="h-10 w-10 text-slate-300" />
            </div>
            <div class="p-3 flex-1 flex flex-col gap-1">
              <p class="text-sm font-semibold text-slate-800">{{ item.item_name }}</p>
              <p v-if="item.category_label" class="text-xs text-slate-500">{{ item.category_label }}</p>
              <p class="text-xs text-slate-400">
                Found {{ fmtDate(item.date_lost_found) }}<template v-if="item.location"> · {{ item.location }}</template>
              </p>
              <p class="text-xs text-indigo-600 font-medium mt-auto pt-1">
                Claim at the GSU office
              </p>
              <div v-if="canManage" class="flex flex-wrap gap-1 pt-1.5 border-t border-slate-100 mt-1">
                <button type="button" class="gsu-btn" @click="viewTimeline(item)">Timeline</button>
                <button type="button" class="gsu-btn" @click="doStoreAt(item)">Move</button>
                <button type="button" class="gsu-btn gsu-btn-primary" @click="doRelease(item)">Release</button>
                <button type="button" class="gsu-btn gsu-btn-danger" @click="doDispose(item)">Dispose</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── My reports ────────────────────────────────────────────────── -->
      <AppCard v-else-if="tab === 'mine'" :padded="false">
        <AppTable :is-empty="myReports.length === 0" :skeleton-cols="5" :card="false">
          <template #head>
            <tr>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Item</th>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Type</th>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Where is it?</th>
              <th class="px-4 py-2.5 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
            </tr>
          </template>
          <tr v-for="r in myReports" :key="r.id" class="hover:bg-indigo-50/40">
            <td class="px-4 py-3">
              <p class="text-sm font-medium text-slate-800">{{ r.item_name }}</p>
              <p v-if="r.category_label" class="text-xs text-slate-500">{{ r.category_label }}</p>
            </td>
            <td class="px-4 py-3">
              <AppBadge :color="r.type === 'found' ? 'green' : 'amber'">{{ r.type === 'found' ? 'Found' : 'Lost' }}</AppBadge>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">{{ fmtDate(r.date_lost_found) }}</td>
            <td class="px-4 py-3">
              <AppBadge :color="statusColor(r.status)">{{ r.custody_label }}</AppBadge>
            </td>
            <td class="px-4 py-3 text-right">
              <AppButton v-if="r.type === 'lost' && ['open', 'matched'].includes(r.status)"
                variant="ghost" size="sm" @click="closeReport(r)">Close</AppButton>
            </td>
          </tr>
          <template #empty>
            <p class="text-sm text-slate-400 py-6 text-center">You haven't reported anything yet.</p>
          </template>
        </AppTable>
      </AppCard>

      <!-- ── Leaderboard ───────────────────────────────────────────────── -->
      <AppCard v-else-if="tab === 'leaderboard'" title="Honesty Points Leaderboard" :padded="false">
        <div v-if="leaderboard.length === 0" class="p-8">
          <EmptyState title="No points awarded yet"
            subtitle="Turn over a found item to the GSU office to earn honesty points."
            :icon="SparklesIcon" />
        </div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="(row, i) in leaderboard" :key="i" class="px-4 py-3 flex items-center gap-3">
            <span :class="['w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0',
              i === 0 ? 'bg-amber-100 text-amber-700' : i === 1 ? 'bg-slate-200 text-slate-600' : i === 2 ? 'bg-orange-100 text-orange-700' : 'bg-slate-50 text-slate-400']">
              {{ i + 1 }}
            </span>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-slate-800 truncate">{{ row.name }}</p>
              <p class="text-xs text-slate-400">{{ row.kind === 'student' ? 'Student' : 'Employee' }} · {{ row.items }} item(s) turned over</p>
            </div>
            <span class="text-sm font-bold text-indigo-600 shrink-0">{{ row.points }} pts</span>
          </div>
        </div>
      </AppCard>

      <!-- ── GSU management ────────────────────────────────────────────── -->
      <div v-else-if="tab === 'gsu' && canManage" class="space-y-5">

        <!-- Pending turnover -->
        <AppCard title="Awaiting Turnover" :padded="false">
          <div v-if="pendingTurnover.length === 0" class="px-4 py-6 text-sm text-slate-400 text-center">
            No found items awaiting turnover.
          </div>
          <div v-else class="divide-y divide-slate-100">
            <div v-for="item in pendingTurnover" :key="item.id" class="px-4 py-3 flex items-center gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-slate-800">{{ item.item_name }}</p>
                <p class="text-xs text-slate-500">
                  Found by {{ item.reporter_name }} ({{ item.reporter_kind }}) · {{ fmtDate(item.date_lost_found) }}
                  <template v-if="item.location"> · {{ item.location }}</template>
                </p>
              </div>
              <button type="button" class="gsu-btn" @click="viewTimeline(item)">Timeline</button>
              <AppButton size="sm" @click="doReceive(item)">
                <InboxArrowDownIcon class="h-3.5 w-3.5" /> Receive
              </AppButton>
            </div>
          </div>
        </AppCard>

        <!-- Open lost reports -->
        <AppCard title="Lost Reports" :padded="false">
          <div v-if="openLostReports.length === 0" class="px-4 py-6 text-sm text-slate-400 text-center">
            No open lost reports.
          </div>
          <div v-else class="divide-y divide-slate-100">
            <div v-for="item in openLostReports" :key="item.id" class="px-4 py-3 flex items-center gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-slate-800">{{ item.item_name }}</p>
                <p class="text-xs text-slate-500">
                  Lost by {{ item.reporter_name }} ({{ item.reporter_kind }}) · {{ fmtDate(item.date_lost_found) }}
                  <template v-if="item.location"> · {{ item.location }}</template>
                </p>
              </div>
              <AppBadge :color="statusColor(item.status)">{{ item.status === 'matched' ? 'Matched' : 'Open' }}</AppBadge>
              <button type="button" class="gsu-btn" @click="viewTimeline(item)">Timeline</button>
              <AppButton v-if="item.status === 'open'" variant="secondary" size="sm" @click="openMatch(item)">
                <LinkIcon class="h-3.5 w-3.5" /> Match
              </AppButton>
            </div>
          </div>
        </AppCard>

        <!-- Resolved history -->
        <AppCard title="Resolved (Claimed / Disposed / Closed)" :padded="false">
          <div v-if="resolved.length === 0" class="px-4 py-6 text-sm text-slate-400 text-center">
            Nothing resolved yet.
          </div>
          <div v-else class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
            <div v-for="item in resolved" :key="item.id" class="px-4 py-2.5 flex items-center gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-sm text-slate-700">{{ item.item_name }}</p>
                <p class="text-xs text-slate-400">
                  {{ item.type === 'found' ? 'Found item' : 'Lost report' }}
                  <template v-if="item.claimed_by_name"> · claimed by {{ item.claimed_by_name }}</template>
                </p>
              </div>
              <AppBadge :color="statusColor(item.status)">{{ item.status }}</AppBadge>
              <button type="button" class="gsu-btn" @click="viewTimeline(item)">Timeline</button>
            </div>
          </div>
        </AppCard>
      </div>

    </div>

    <!-- Report modal -->
    <AppModal :show="reportModal" title="Report an Item" size="lg"
      body-class="px-6 py-4 space-y-4" @close="reportModal = false">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1.5">What happened? *</label>
        <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm">
          <button type="button" @click="form.type = 'lost'"
            :class="['px-4 py-2 font-medium transition-colors', form.type === 'lost' ? 'bg-amber-500 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            I lost an item
          </button>
          <button type="button" @click="form.type = 'found'"
            :class="['px-4 py-2 font-medium border-l border-slate-200 transition-colors', form.type === 'found' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            I found an item
          </button>
        </div>
        <p v-if="form.type === 'found'" class="text-xs text-emerald-700 mt-1.5">
          Please bring the item to the GSU office — you'll earn {{ 10 }} honesty points once GSU confirms the turnover.
        </p>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2">
          <AppInput v-model="form.item_name" label="Item Name" required placeholder="e.g. Black umbrella, Casio calculator" />
        </div>
        <AppSelect v-model="form.category" label="Category" placeholder="Select category...">
          <option v-for="(label, value) in categories" :key="value" :value="value">{{ label }}</option>
        </AppSelect>
        <AppInput v-model="form.date_lost_found" type="date" :label="form.type === 'found' ? 'Date Found' : 'Date Lost'" required />
        <div class="col-span-2">
          <AppInput v-model="form.location" :label="form.type === 'found' ? 'Where did you find it?' : 'Where did you last see it?'"
            placeholder="e.g. Canteen, Room 204, Gym" />
        </div>
        <div class="col-span-2">
          <AppTextarea v-model="form.description" label="Description" :rows="2"
            placeholder="Color, brand, distinguishing marks..." />
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-medium text-slate-600 mb-1">Photo (optional)</label>
          <input type="file" accept="image/*" @change="onPhotoSelected"
            class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100" />
          <img v-if="form.photo_base64" :src="form.photo_base64" class="mt-2 h-24 rounded-lg object-cover" alt="Preview" />
        </div>
      </div>

      <template #footer>
        <AppButton variant="ghost" @click="reportModal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="submitReport">Submit Report</AppButton>
      </template>
    </AppModal>

    <!-- Walk-in turnover modal (GSU) -->
    <AppModal :show="walkInModal" title="Log Walk-in Turnover" size="lg"
      body-class="px-6 py-4 space-y-4" @close="walkInModal = false">
      <p class="text-xs text-slate-500">
        For items handed to GSU without a prior report. The item goes straight into custody;
        an identified employee/student finder earns honesty points immediately.
      </p>
      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2">
          <AppInput v-model="walkInForm.item_name" label="Item Name" required />
        </div>
        <AppSelect v-model="walkInForm.category" label="Category" placeholder="Select category...">
          <option v-for="(label, value) in categories" :key="value" :value="value">{{ label }}</option>
        </AppSelect>
        <AppInput v-model="walkInForm.date_lost_found" type="date" label="Date Found" required />
        <AppInput v-model="walkInForm.location" label="Where was it found?" />
        <AppInput v-model="walkInForm.storage_location" label="Storage Location (shelf/box)" />
        <div class="col-span-2">
          <AppTextarea v-model="walkInForm.description" label="Description" :rows="2" />
        </div>
        <div class="col-span-2 border-t border-slate-100 pt-3">
          <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Finder (for honesty points)</p>
          <div class="grid grid-cols-2 gap-3">
            <AppInput v-model="walkInForm.finder_name" label="Finder Name (visitor / unknown)" placeholder="Walk-in name" />
            <AppInput v-model="walkInForm.finder_student_id" type="number" label="Student ID (if student)" placeholder="students.id" />
          </div>
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-medium text-slate-600 mb-1">Photo (optional)</label>
          <input type="file" accept="image/*" @change="onWalkInPhotoSelected"
            class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100" />
          <img v-if="walkInForm.photo_base64" :src="walkInForm.photo_base64" class="mt-2 h-24 rounded-lg object-cover" alt="Preview" />
        </div>
      </div>
      <template #footer>
        <AppButton variant="ghost" @click="walkInModal = false">Cancel</AppButton>
        <AppButton :loading="walkInForm.processing" @click="submitWalkIn">Log into Custody</AppButton>
      </template>
    </AppModal>

    <!-- Match modal (GSU) -->
    <AppModal :show="matchModal" title="Match Lost Report" size="lg"
      body-class="px-6 py-4 space-y-4" @close="matchModal = false">
      <p class="text-sm text-slate-600">
        Matching <span class="font-semibold">{{ matchingReport?.item_name }}</span> with a found item in custody:
      </p>
      <div class="max-h-72 overflow-y-auto divide-y divide-slate-100 border border-slate-100 rounded-lg">
        <button v-for="item in board" :key="item.id" type="button" @click="submitMatch(item)"
          class="w-full text-left px-4 py-3 hover:bg-indigo-50 flex items-center gap-3">
          <div class="w-12 h-12 rounded-lg bg-slate-100 overflow-hidden flex items-center justify-center shrink-0">
            <img v-if="item.has_photo" :src="route('lost-found.photo', item.id)" class="w-full h-full object-cover" alt="" />
            <ArchiveBoxIcon v-else class="h-5 w-5 text-slate-300" />
          </div>
          <div class="min-w-0">
            <p class="text-sm font-medium text-slate-800">{{ item.item_name }}</p>
            <p class="text-xs text-slate-500">{{ item.category_label }} · found {{ fmtDate(item.date_lost_found) }}</p>
          </div>
        </button>
        <p v-if="board.length === 0" class="px-4 py-6 text-sm text-slate-400 text-center">No items in custody to match.</p>
      </div>
      <template #footer>
        <AppButton variant="ghost" @click="matchModal = false">Cancel</AppButton>
      </template>
    </AppModal>

    <!-- Timeline modal -->
    <AppModal :show="timelineModal" :title="timelineItem ? `Custody Trail — ${timelineItem.item_name}` : 'Custody Trail'"
      size="lg" body-class="px-6 py-4" @close="timelineModal = false">
      <ol v-if="timelineItem?.movements?.length" class="relative border-l border-slate-200 ml-2 space-y-4">
        <li v-for="(m, i) in timelineItem.movements" :key="i" class="ml-4">
          <span class="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full bg-indigo-400"></span>
          <p class="text-sm font-medium text-slate-800 capitalize">{{ m.action.replace(/_/g, ' ') }}</p>
          <p v-if="m.notes" class="text-sm text-slate-600">{{ m.notes }}</p>
          <p class="text-xs text-slate-400">
            {{ fmtDateTime(m.at) }} · {{ m.actor }}<template v-if="m.location"> · {{ m.location }}</template>
          </p>
        </li>
      </ol>
      <p v-else class="text-sm text-slate-400">No movements recorded.</p>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Swal from 'sweetalert2'
import {
  ArchiveBoxIcon, CheckCircleIcon, ClockIcon, DocumentTextIcon, InboxArrowDownIcon,
  LinkIcon, PlusIcon, SparklesIcon, TrophyIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  board:           { type: Array,   default: () => [] },
  myReports:       { type: Array,   default: () => [] },
  myPoints:        { type: Number,  default: 0 },
  categories:      { type: Object,  default: () => ({}) },
  leaderboard:     { type: Array,   default: () => [] },
  canManage:       { type: Boolean, default: false },
  pendingTurnover: { type: Array,   default: () => [] },
  openLostReports: { type: Array,   default: () => [] },
  resolved:        { type: Array,   default: () => [] },
})

const tabs = [
  { key: 'board',       label: 'Found Items' },
  { key: 'mine',        label: 'My Reports' },
  { key: 'leaderboard', label: 'Leaderboard' },
  ...(props.canManage ? [{ key: 'gsu', label: 'GSU Management' }] : []),
]
const tab = ref(props.canManage && props.pendingTurnover.length ? 'gsu' : 'board')

// ── Formatting ───────────────────────────────────────────────────────────────

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
function fmtDateTime(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}
function statusColor(status) {
  return {
    open: 'amber', matched: 'blue', claimed: 'green', closed: 'slate',
    pending_turnover: 'amber', in_custody: 'indigo', disposed: 'slate',
  }[status] ?? 'slate'
}

// ── Report modal ─────────────────────────────────────────────────────────────

const reportModal = ref(false)
const form = useForm({
  type: 'lost', item_name: '', description: '', category: '',
  location: '', date_lost_found: '', photo_base64: '',
})

function openReport() {
  form.reset()
  form.clearErrors()
  form.date_lost_found = new Date().toISOString().slice(0, 10)
  reportModal.value = true
}

function readFileAsDataUrl(e, target, key) {
  const file = e.target.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => { target[key] = reader.result }
  reader.readAsDataURL(file)
}
const onPhotoSelected       = (e) => readFileAsDataUrl(e, form, 'photo_base64')
const onWalkInPhotoSelected = (e) => readFileAsDataUrl(e, walkInForm, 'photo_base64')

function submitReport() {
  form.post(route('lost-found.store'), {
    preserveScroll: true,
    onSuccess: () => { reportModal.value = false },
  })
}

function closeReport(r) {
  Swal.fire({
    icon: 'question',
    title: 'Close this report?',
    text: 'Close the report if you found your item or no longer need it tracked.',
    showCancelButton: true,
    confirmButtonText: 'Close report',
  }).then((res) => {
    if (!res.isConfirmed) return
    router.post(route('lost-found.close', r.id), {}, { preserveScroll: true })
  })
}

// ── GSU actions ──────────────────────────────────────────────────────────────

const walkInModal = ref(false)
const walkInForm = useForm({
  item_name: '', description: '', category: '', location: '', date_lost_found: '',
  storage_location: '', finder_name: '', finder_student_id: null, photo_base64: '',
})

function openWalkIn() {
  walkInForm.reset()
  walkInForm.clearErrors()
  walkInForm.date_lost_found = new Date().toISOString().slice(0, 10)
  walkInModal.value = true
}

function submitWalkIn() {
  walkInForm.transform(data => ({
    ...data,
    finder_student_id: data.finder_student_id || null,
  })).post(route('lost-found.walk-in'), {
    preserveScroll: true,
    onSuccess: () => { walkInModal.value = false },
  })
}

async function doReceive(item) {
  const { value, isConfirmed } = await Swal.fire({
    title: 'Confirm turnover receipt',
    text: `"${item.item_name}" — honesty points will be credited to ${item.reporter_name}.`,
    input: 'text',
    inputLabel: 'Storage location (shelf/box, optional)',
    showCancelButton: true,
    confirmButtonText: 'Receive into custody',
  })
  if (!isConfirmed) return
  router.post(route('lost-found.receive', item.id), { storage_location: value || null }, { preserveScroll: true })
}

async function doStoreAt(item) {
  const { value, isConfirmed } = await Swal.fire({
    title: 'Move item',
    input: 'text',
    inputLabel: 'New storage location',
    inputValue: item.storage_location ?? '',
    showCancelButton: true,
    inputValidator: v => (!v ? 'Storage location is required' : null),
  })
  if (!isConfirmed) return
  router.post(route('lost-found.store-at', item.id), { storage_location: value }, { preserveScroll: true })
}

async function doRelease(item) {
  const { value, isConfirmed } = await Swal.fire({
    title: 'Release to owner',
    text: `"${item.item_name}"`,
    input: 'text',
    inputLabel: 'Claimed by (full name)',
    showCancelButton: true,
    confirmButtonText: 'Release item',
    inputValidator: v => (!v ? 'Claimant name is required' : null),
  })
  if (!isConfirmed) return
  router.post(route('lost-found.release', item.id), { claimed_by_name: value }, { preserveScroll: true })
}

async function doDispose(item) {
  const { value, isConfirmed } = await Swal.fire({
    icon: 'warning',
    title: 'Dispose / donate item?',
    text: `"${item.item_name}" will be marked disposed.`,
    input: 'text',
    inputLabel: 'Notes (optional)',
    showCancelButton: true,
    confirmButtonText: 'Dispose',
    confirmButtonColor: '#dc2626',
  })
  if (!isConfirmed) return
  router.post(route('lost-found.dispose', item.id), { notes: value || null }, { preserveScroll: true })
}

// ── Matching ─────────────────────────────────────────────────────────────────

const matchModal = ref(false)
const matchingReport = ref(null)

function openMatch(report) {
  matchingReport.value = report
  matchModal.value = true
}

function submitMatch(foundItem) {
  router.post(route('lost-found.match', matchingReport.value.id), { found_item_id: foundItem.id }, {
    preserveScroll: true,
    onSuccess: () => { matchModal.value = false; matchingReport.value = null },
  })
}

// ── Timeline ─────────────────────────────────────────────────────────────────

const timelineModal = ref(false)
const timelineItem = ref(null)

function viewTimeline(item) {
  timelineItem.value = item
  timelineModal.value = true
}
</script>

<style scoped>
.gsu-btn {
  @apply text-xs px-2 py-1 rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors;
}
.gsu-btn-primary {
  @apply border-indigo-200 text-indigo-600 hover:bg-indigo-50;
}
.gsu-btn-danger {
  @apply border-danger-100 text-danger-600 hover:bg-danger-50;
}
</style>
