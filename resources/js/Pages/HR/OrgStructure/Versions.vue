<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmAction, confirmDelete } from '@/Composables/useConfirm.js'
import {
  CheckCircleIcon,
  ExclamationCircleIcon,
  PlusIcon,
  BoltIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  versions: { type: Array,  default: () => [] },
  current:  { type: Object, default: null },
  can:      { type: Object, default: () => ({}) },
})

// ── State ──────────────────────────────────────────────────────────────────────
const flash   = ref({ success: null, error: null })
const loading = ref(false)
const errors  = ref({})

const showCreate   = ref(false)
const showSnapshot = ref(false)
const snapshotData = ref(null)

const form = ref({
  version_number: '',
  name:           '',
  effective_date: new Date().toISOString().slice(0, 10),
  end_date:       '',
  change_summary: '',
  basis_document: '',
})

// ── Helpers ────────────────────────────────────────────────────────────────────
function setFlash(type, msg) {
  flash.value = { success: null, error: null, [type]: msg }
  setTimeout(() => { flash.value = { success: null, error: null } }, 5000)
}

function reload() {
  router.reload({ only: ['versions', 'current'], preserveScroll: true })
}

// ── CRUD ───────────────────────────────────────────────────────────────────────
async function submitCreate() {
  loading.value = true
  errors.value  = {}
  try {
    await axios.post(route('hr.org.versions.store'), form.value)
    showCreate.value = false
    reload()
    setFlash('success', 'Draft version created.')
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors ?? {}
    } else {
      setFlash('error', e.response?.data?.message ?? 'An error occurred.')
    }
  } finally {
    loading.value = false
  }
}

async function approveVersion(v) {
  const confirmed = await confirmAction({ title: 'Approve version?', text: `Approve version "${v.name}"?`, confirmText: 'Approve' })
  if (!confirmed) return
  loading.value = true
  try {
    await axios.post(route('hr.org.versions.approve', v.id))
    reload()
    setFlash('success', `Version "${v.name}" approved.`)
  } catch (e) {
    setFlash('error', e.response?.data?.message ?? 'An error occurred.')
  } finally {
    loading.value = false
  }
}

async function activateVersion(v) {
  const confirmed = await confirmAction({
    title: 'Activate version?',
    text: `Activate version "${v.name}"? This will archive the currently active version and capture a snapshot of the live org tree.`,
    confirmText: 'Activate',
  })
  if (!confirmed) return
  loading.value = true
  try {
    await axios.post(route('hr.org.versions.activate', v.id))
    reload()
    setFlash('success', `Version "${v.name}" is now active.`)
  } catch (e) {
    setFlash('error', e.response?.data?.message ?? 'An error occurred.')
  } finally {
    loading.value = false
  }
}

async function deleteVersion(v) {
  const confirmed = await confirmDelete(`Delete draft version "${v.name}"? This cannot be undone.`)
  if (!confirmed) return
  loading.value = true
  try {
    await axios.delete(route('hr.org.versions.destroy', v.id))
    reload()
    setFlash('success', `Draft "${v.name}" deleted.`)
  } catch (e) {
    setFlash('error', e.response?.data?.message ?? 'An error occurred.')
  } finally {
    loading.value = false
  }
}

function viewSnapshot(v) {
  snapshotData.value = v
  showSnapshot.value = true
}

// ── Status styling ─────────────────────────────────────────────────────────────
function statusColor(status) {
  const map = { draft: 'slate', approved: 'blue', active: 'green', archived: 'amber' }
  return map[status] ?? 'slate'
}
</script>

<template>
  <Head title="Org Versions" />
  <AdminLayout title="Organizational Versions">
    <div class="space-y-5">

      <!-- Header -->
      <AppPageHeader title="Version History" subtitle="Track and manage org structure versions over time.">
        <template #actions>
          <AppButton v-if="can.manage" @click="showCreate = true">
            <PlusIcon class="h-4 w-4" /> New Draft
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="flash.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" /> {{ flash.success }}
      </div>
      <div v-if="flash.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" /> {{ flash.error }}
      </div>

      <!-- Active version banner -->
      <div v-if="current" class="bg-success-50 border border-success-100 rounded-xl px-5 py-4">
        <div class="flex items-start gap-3">
          <BoltIcon class="h-5 w-5 text-success-600 shrink-0 mt-0.5" />
          <div>
            <p class="text-sm font-semibold text-success-700">Currently Active: {{ current.name }}</p>
            <p class="text-xs text-success-600 mt-0.5">
              v{{ current.version_number }} &bull; Effective {{ current.effective_date }} &bull;
              Approved by {{ current.approver?.name ?? 'N/A' }}
            </p>
            <p v-if="current.change_summary" class="text-xs text-success-700 mt-1">{{ current.change_summary }}</p>
          </div>
          <AppButton v-if="current.snapshot" variant="success" size="sm" class="ml-auto shrink-0" @click="viewSnapshot(current)">
            View Snapshot
          </AppButton>
        </div>
      </div>

      <!-- Versions table -->
      <AppTable :is-empty="!versions.length" :skeleton-cols="can.manage ? 6 : 5">
        <template #head>
          <tr>
            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Version</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide hidden sm:table-cell">Effective</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide hidden md:table-cell">Summary</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide hidden lg:table-cell">Created by</th>
            <th v-if="can.manage" class="px-4 py-3"></th>
          </tr>
        </template>

        <tr
          v-for="v in versions"
          :key="v.id"
          :class="v.status === 'active' ? 'bg-success-50/40' : 'hover:bg-slate-50/60'"
        >
          <td class="px-5 py-3">
            <p class="font-medium text-slate-800">{{ v.name }}</p>
            <p class="text-xs text-slate-400 font-mono">v{{ v.version_number }}</p>
          </td>
          <td class="px-4 py-3 text-slate-600 hidden sm:table-cell text-xs">
            {{ v.effective_date }}<span v-if="v.end_date"> — {{ v.end_date }}</span>
          </td>
          <td class="px-4 py-3 text-slate-500 hidden md:table-cell text-xs max-w-xs truncate">
            {{ v.change_summary ?? '—' }}
          </td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(v.status)"><span class="capitalize">{{ v.status }}</span></AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-500 hidden lg:table-cell text-xs">
            {{ v.creator?.name ?? '—' }}
          </td>
          <td v-if="can.manage" class="px-4 py-3">
            <div class="flex items-center gap-1.5 justify-end">
              <AppButton v-if="v.snapshot" variant="secondary" size="sm" @click="viewSnapshot(v)">Snapshot</AppButton>
              <AppButton v-if="v.status === 'draft'" variant="success" size="sm" :disabled="loading" @click="approveVersion(v)">Approve</AppButton>
              <AppButton v-if="v.status === 'approved'" variant="primary" size="sm" :disabled="loading" @click="activateVersion(v)">Activate</AppButton>
              <AppButton v-if="v.status === 'draft'" variant="danger" size="sm" :disabled="loading" @click="deleteVersion(v)">Delete</AppButton>
            </div>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No versions yet" subtitle="Create a draft to start tracking changes." />
        </template>
      </AppTable>

      <div class="flex gap-3">
        <AppButton as="link" :href="route('hr.org.index')" variant="ghost" size="sm">← Back to Org Chart</AppButton>
        <AppButton as="link" :href="route('hr.org.reports')" variant="ghost" size="sm">Reports →</AppButton>
      </div>
    </div>

    <!-- ── CREATE DRAFT MODAL ─────────────────────────────────────────────────── -->
    <AppModal :show="showCreate" title="New Draft Version" size="lg" @close="showCreate = false">
      <form @submit.prevent="submitCreate" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Version Number <span class="text-danger-600">*</span></label>
            <input v-model="form.version_number" type="text" placeholder="e.g. 2026-001"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-danger-500': errors.version_number }"
            />
            <p v-if="errors.version_number" class="text-danger-600 text-xs mt-1">{{ errors.version_number[0] }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Effective Date <span class="text-danger-600">*</span></label>
            <input v-model="form.effective_date" type="date"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-danger-500': errors.effective_date }"
            />
            <p v-if="errors.effective_date" class="text-danger-600 text-xs mt-1">{{ errors.effective_date[0] }}</p>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-danger-600">*</span></label>
          <input v-model="form.name" type="text" placeholder="e.g. FY 2026 Reorganization"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            :class="{ 'border-danger-500': errors.name }"
          />
          <p v-if="errors.name" class="text-danger-600 text-xs mt-1">{{ errors.name[0] }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Change Summary</label>
          <textarea v-model="form.change_summary" rows="2" placeholder="What changed in this version?"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"
          ></textarea>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Basis Document</label>
          <input v-model="form.basis_document" type="text" placeholder="BOD Res. No. / EO No. / AO No."
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
          />
        </div>
      </form>

      <template #footer>
        <AppButton variant="secondary" @click="showCreate = false">Cancel</AppButton>
        <AppButton :loading="loading" @click="submitCreate">
          {{ loading ? 'Creating…' : 'Create Draft' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- ── SNAPSHOT VIEWER MODAL ──────────────────────────────────────────────── -->
    <AppModal :show="showSnapshot" :title="`Snapshot — ${snapshotData?.name ?? ''}`" size="3xl" @close="showSnapshot = false">
      <div v-if="snapshotData?.snapshot" class="font-mono text-xs bg-slate-900 text-success-500 rounded-lg p-4 max-h-96 overflow-y-auto whitespace-pre-wrap">
        {{ JSON.stringify(snapshotData.snapshot, null, 2) }}
      </div>
      <p v-else class="text-sm text-slate-500">No snapshot captured yet. Snapshots are taken when a version is activated.</p>
      <template #footer>
        <AppButton variant="secondary" @click="showSnapshot = false">Close</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
