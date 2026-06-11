<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  ChevronLeftIcon, ArrowDownTrayIcon, PencilSquareIcon, TrashIcon,
  UserGroupIcon, ShieldCheckIcon, LockClosedIcon, GlobeAltIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  issuance:  Object,
  canManage: Boolean,
  ackStats:  Object,
})

const categoryColors = {
  MEMO:  'bg-violet-100 text-violet-700',
  MC:    'bg-indigo-100 text-indigo-700',
  OO:    'bg-cyan-100 text-cyan-700',
  SO:    'bg-blue-100 text-blue-700',
  AO:    'bg-amber-100 text-amber-700',
  EO:    'bg-rose-100 text-rose-700',
  BR:    'bg-emerald-100 text-emerald-700',
  ADV:   'bg-sky-100 text-sky-700',
  GUIDE: 'bg-teal-100 text-teal-700',
  OTHER: 'bg-slate-100 text-slate-600',
}

const statusCls = {
  active:     'bg-emerald-100 text-emerald-700',
  superseded: 'bg-amber-100 text-amber-700',
  archived:   'bg-slate-100 text-slate-500',
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

function fmtDateTime(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit' })
}

function fmtSize(bytes) {
  if (!bytes) return '—'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

const isPdf = computed(() => (props.issuance.file_mime ?? '').includes('pdf'))

const recipientNames = computed(() =>
  (props.issuance.recipients ?? []).map(r => r.user?.name).filter(Boolean)
)

const showRecipients = ref(false)

const deleteForm = useForm({})
const showDeleteConfirm = ref(false)

function destroy() {
  deleteForm.delete(route('km.destroy', props.issuance.id))
}
</script>

<template>
  <Head :title="issuance.title" />
  <AdminLayout :title="issuance.title">
    <div class="max-w-4xl space-y-5">

      <button @click="router.visit(route('km.index'))"
        class="flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800">
        <ChevronLeftIcon class="h-4 w-4" /> Back to Knowledge Management
      </button>

      <!-- Header -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="h-1.5 bg-indigo-600"></div>
        <div class="p-6">
          <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div class="flex-1">
              <div class="flex flex-wrap items-center gap-2 mb-2">
                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold"
                  :class="categoryColors[issuance.category_code] ?? categoryColors.OTHER">
                  {{ issuance.category?.label ?? issuance.category_code }}
                </span>
                <span v-if="issuance.reference_no" class="font-mono text-xs font-bold text-slate-600">{{ issuance.reference_no }}</span>
                <span v-if="canManage" class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold capitalize"
                  :class="statusCls[issuance.status] ?? 'bg-slate-100 text-slate-600'">
                  {{ issuance.status }}
                </span>
              </div>
              <h1 class="text-xl font-semibold text-slate-800">{{ issuance.title }}</h1>
              <p v-if="issuance.description" class="text-sm text-slate-600 mt-1">{{ issuance.description }}</p>
              <p class="text-xs text-slate-500 mt-2">
                Issued {{ fmtDate(issuance.issued_date) }}
                <span v-if="issuance.effective_date"> · Effective {{ fmtDate(issuance.effective_date) }}</span>
                <span v-if="issuance.uploader"> · Uploaded by {{ issuance.uploader.name }}</span>
              </p>
              <p v-if="issuance.superseded_by" class="text-xs text-amber-600 mt-1">
                Superseded by:
                <a :href="route('km.show', issuance.superseded_by.id)" class="underline">
                  {{ issuance.superseded_by.reference_no ?? issuance.superseded_by.title }}
                </a>
              </p>
              <p v-if="issuance.supersedes" class="text-xs text-slate-500 mt-1">
                Supersedes:
                <a :href="route('km.show', issuance.supersedes.id)" class="underline">
                  {{ issuance.supersedes.reference_no ?? issuance.supersedes.title }}
                </a>
              </p>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-2 shrink-0">
              <a :href="route('km.download', issuance.id)"
                class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-700 border border-slate-200 rounded-lg hover:bg-slate-50">
                <ArrowDownTrayIcon class="h-4 w-4" /> Download
              </a>
              <a v-if="canManage" :href="route('km.edit', issuance.id)"
                class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-700 border border-slate-200 rounded-lg hover:bg-slate-50">
                <PencilSquareIcon class="h-4 w-4" /> Edit
              </a>
              <button v-if="canManage" @click="showDeleteConfirm = true"
                class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50">
                <TrashIcon class="h-4 w-4" /> Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Document viewer -->
        <div class="lg:col-span-2 space-y-5">
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3">
            <iframe v-if="isPdf" :src="route('km.file', issuance.id)" class="w-full h-[80vh] rounded-lg border border-slate-100" />
            <img v-else :src="route('km.file', issuance.id)" :alt="issuance.file_name" class="w-full rounded-lg border border-slate-100" />
            <p class="text-xs text-slate-400 mt-2 px-1">{{ issuance.file_name }} · {{ fmtSize(issuance.file_size) }}</p>
          </div>
        </div>

        <!-- Right panel -->
        <div class="space-y-4">

          <!-- Visibility -->
          <div v-if="canManage" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3 flex items-center gap-1.5">
              <component :is="issuance.recipient_type === 'all' ? GlobeAltIcon : LockClosedIcon" class="h-3.5 w-3.5" />
              Visibility
            </h2>
            <p v-if="issuance.recipient_type === 'all'" class="text-sm text-slate-700">All Employees</p>
            <div v-else>
              <p class="text-sm text-slate-700 mb-1">{{ recipientNames.length }} selected recipient(s)</p>
              <button @click="showRecipients = !showRecipients" class="text-xs text-indigo-600 font-medium">
                {{ showRecipients ? 'Hide' : 'Show' }} list
              </button>
              <div v-if="showRecipients" class="mt-2 max-h-40 overflow-y-auto space-y-1">
                <p v-for="(name, idx) in recipientNames" :key="idx" class="text-xs text-slate-600">{{ name }}</p>
              </div>
            </div>
          </div>

          <!-- Acknowledgment progress (manager) -->
          <div v-if="canManage && ackStats" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3 flex items-center gap-1.5">
              <UserGroupIcon class="h-3.5 w-3.5" /> Read Receipts
            </h2>
            <div class="flex items-center gap-3 mb-2">
              <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden">
                <div class="bg-emerald-500 h-full rounded-full transition-all" :style="`width:${ackStats.percentage}%`"></div>
              </div>
              <span class="text-xs font-bold text-slate-700 shrink-0">{{ ackStats.acknowledged }}/{{ ackStats.total }}</span>
            </div>
            <p class="text-xs text-slate-500">{{ ackStats.percentage }}% have viewed this document</p>

            <div v-if="ackStats.recent.length" class="mt-3 max-h-48 overflow-y-auto space-y-1">
              <div v-for="a in ackStats.recent" :key="a.user?.id"
                class="flex items-center justify-between py-1.5 border-b border-slate-50 last:border-0">
                <p class="text-xs font-medium text-slate-700 truncate">{{ a.user?.name ?? '—' }}</p>
                <span class="text-[10px] text-slate-400 shrink-0 ml-2">{{ fmtDateTime(a.acknowledged_at) }}</span>
              </div>
            </div>
          </div>

          <!-- Acknowledged badge (non-manager) -->
          <div v-if="!canManage" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2 flex items-center gap-1.5">
              <ShieldCheckIcon class="h-3.5 w-3.5 text-emerald-500" /> Read Status
            </h2>
            <p class="text-sm text-emerald-600 font-medium">Marked as viewed</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete confirmation -->
    <Teleport to="body">
      <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/60">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
          <h3 class="text-lg font-semibold text-slate-800 mb-2">Delete this document?</h3>
          <p class="text-sm text-slate-500 mb-5">This action cannot be undone. The file will be permanently removed.</p>
          <div class="flex justify-end gap-2">
            <button @click="showDeleteConfirm = false"
              class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="destroy" :disabled="deleteForm.processing"
              class="px-4 py-2 text-sm font-medium text-white rounded-lg bg-red-600 hover:bg-red-700 disabled:opacity-50">
              {{ deleteForm.processing ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
