<template>
  <Head :title="issuance.title" />
  <AdminLayout :title="issuance.title">
    <div class="max-w-4xl space-y-5">

      <Link :href="route('km.index')" class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800">
        <ChevronLeftIcon class="h-4 w-4" /> Back to Knowledge Management
      </Link>

      <!-- Header -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="h-1.5 bg-indigo-600"></div>
        <div class="p-6">
          <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div class="flex-1">
              <div class="flex flex-wrap items-center gap-2 mb-2">
                <AppBadge :color="categoryBadgeColor(issuance.category_code)">{{ issuance.category?.label ?? issuance.category_code }}</AppBadge>
                <span v-if="issuance.reference_no" class="font-mono text-xs font-bold text-slate-600">{{ issuance.reference_no }}</span>
                <AppBadge v-if="canManage" :color="statusBadgeColor(issuance.status)" class="capitalize">{{ issuance.status }}</AppBadge>
              </div>
              <h1 class="text-xl font-semibold text-slate-800">{{ issuance.title }}</h1>
              <p v-if="issuance.description" class="text-sm text-slate-600 mt-1">{{ issuance.description }}</p>
              <p class="text-xs text-slate-500 mt-2">
                Issued {{ fmtDate(issuance.issued_date) }}
                <span v-if="issuance.effective_date"> · Effective {{ fmtDate(issuance.effective_date) }}</span>
                <span v-if="issuance.uploader"> · Uploaded by {{ issuance.uploader.name }}</span>
              </p>
              <p v-if="issuance.superseded_by" class="text-xs text-warning-700 mt-1">
                Superseded by:
                <Link :href="route('km.show', issuance.superseded_by.id)" class="underline">
                  {{ issuance.superseded_by.reference_no ?? issuance.superseded_by.title }}
                </Link>
              </p>
              <p v-if="issuance.supersedes" class="text-xs text-slate-500 mt-1">
                Supersedes:
                <Link :href="route('km.show', issuance.supersedes.id)" class="underline">
                  {{ issuance.supersedes.reference_no ?? issuance.supersedes.title }}
                </Link>
              </p>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-2 shrink-0">
              <AppButton as="a" :href="route('km.download', issuance.id)" variant="secondary">
                <ArrowDownTrayIcon class="h-4 w-4" /> Download
              </AppButton>
              <AppButton v-if="canManage" as="link" :href="route('km.edit', issuance.id)" variant="secondary">
                <PencilSquareIcon class="h-4 w-4" /> Edit
              </AppButton>
              <AppButton v-if="canManage" variant="danger" @click="destroy">
                <TrashIcon class="h-4 w-4" /> Delete
              </AppButton>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Document viewer -->
        <div class="lg:col-span-2 space-y-5">
          <AppCard :padded="false">
            <div class="p-3">
              <iframe v-if="isPdf" :src="route('km.file', issuance.id)" class="w-full h-[80vh] rounded-lg border border-slate-100" />
              <img v-else :src="route('km.file', issuance.id)" :alt="issuance.file_name" class="w-full rounded-lg border border-slate-100" />
              <p class="text-xs text-slate-400 mt-2 px-1">{{ issuance.file_name }} · {{ fmtSize(issuance.file_size) }}</p>
            </div>
          </AppCard>
        </div>

        <!-- Right panel -->
        <div class="space-y-4">

          <!-- Visibility -->
          <AppCard v-if="canManage">
            <template #header>
              <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide flex items-center gap-1.5">
                <component :is="issuance.recipient_type === 'all' ? GlobeAltIcon : LockClosedIcon" class="h-3.5 w-3.5" />
                Visibility
              </h2>
            </template>
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
          </AppCard>

          <!-- Acknowledgment progress (manager) -->
          <AppCard v-if="canManage && ackStats">
            <template #header>
              <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide flex items-center gap-1.5">
                <UserGroupIcon class="h-3.5 w-3.5" /> Read Receipts
              </h2>
            </template>
            <div class="flex items-center gap-3 mb-2">
              <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden">
                <div class="bg-success-500 h-full rounded-full transition-all" :style="`width:${ackStats.percentage}%`"></div>
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
          </AppCard>

          <!-- Acknowledged badge (non-manager) -->
          <AppCard v-if="!canManage">
            <template #header>
              <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide flex items-center gap-1.5">
                <ShieldCheckIcon class="h-3.5 w-3.5 text-success-600" /> Read Status
              </h2>
            </template>
            <p class="text-sm text-success-700 font-medium">Marked as viewed</p>
          </AppCard>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import {
  ChevronLeftIcon, ArrowDownTrayIcon, PencilSquareIcon, TrashIcon,
  UserGroupIcon, ShieldCheckIcon, LockClosedIcon, GlobeAltIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  issuance:  Object,
  canManage: Boolean,
  ackStats:  Object,
})

const categoryBadgeColors = {
  MEMO:  'purple',
  MC:    'indigo',
  OO:    'blue',
  SO:    'blue',
  AO:    'amber',
  EO:    'red',
  BR:    'green',
  ADV:   'blue',
  GUIDE: 'orange',
  OTHER: 'slate',
}
function categoryBadgeColor(code) {
  return categoryBadgeColors[code] ?? categoryBadgeColors.OTHER
}

const statusBadgeColors = {
  active:     'green',
  superseded: 'amber',
  archived:   'slate',
}
function statusBadgeColor(status) {
  return statusBadgeColors[status] ?? 'slate'
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

async function destroy() {
  if (await confirmDelete('This action cannot be undone. The file will be permanently removed.')) {
    deleteForm.delete(route('km.destroy', props.issuance.id))
  }
}
</script>
