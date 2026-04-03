<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import {
  ClockIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  PlusIcon,
  ArchiveBoxIcon,
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
  if (!confirm(`Approve version "${v.name}"?`)) return
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
  if (!confirm(`Activate version "${v.name}"? This will archive the currently active version and capture a snapshot of the live org tree.`)) return
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
  if (!confirm(`Delete draft version "${v.name}"?`)) return
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
const STATUS_CLASS = {
  draft:    'bg-slate-100 text-slate-500',
  approved: 'bg-blue-50 text-blue-700',
  active:   'bg-emerald-50 text-emerald-700',
  archived: 'bg-amber-50 text-amber-600',
}
</script>

<template>
  <Head title="Org Versions" />
  <AdminLayout title="Organizational Versions">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
            <ClockIcon class="h-6 w-6 text-indigo-500" />
            Version History
          </h1>
          <p class="text-sm text-slate-500 mt-0.5">Track and manage org structure versions over time.</p>
        </div>
        <button
          v-if="can.manage"
          @click="showCreate = true"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg shadow-sm"
        >
          <PlusIcon class="h-4 w-4" /> New Draft
        </button>
      </div>

      <!-- Flash -->
      <div v-if="flash.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" /> {{ flash.success }}
      </div>
      <div v-if="flash.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" /> {{ flash.error }}
      </div>

      <!-- Active version banner -->
      <div v-if="current" class="bg-emerald-50 border border-emerald-200 rounded-xl px-5 py-4">
        <div class="flex items-start gap-3">
          <BoltIcon class="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" />
          <div>
            <p class="text-sm font-semibold text-emerald-800">Currently Active: {{ current.name }}</p>
            <p class="text-xs text-emerald-600 mt-0.5">
              v{{ current.version_number }} &bull; Effective {{ current.effective_date }} &bull;
              Approved by {{ current.approver?.name ?? 'N/A' }}
            </p>
            <p v-if="current.change_summary" class="text-xs text-emerald-700 mt-1">{{ current.change_summary }}</p>
          </div>
          <button
            v-if="current.snapshot"
            @click="viewSnapshot(current)"
            class="ml-auto shrink-0 text-xs px-2.5 py-1 bg-emerald-100 hover:bg-emerald-200 text-emerald-700 rounded-lg"
          >
            View Snapshot
          </button>
        </div>
      </div>

      <!-- Versions table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div v-if="!versions.length" class="px-5 py-12 text-center text-sm text-slate-400">
          No versions yet. Create a draft to start tracking changes.
        </div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-xs text-slate-400 uppercase tracking-wide border-b border-slate-100">
              <th class="px-5 py-3 text-left font-medium">Version</th>
              <th class="px-4 py-3 text-left font-medium hidden sm:table-cell">Effective</th>
              <th class="px-4 py-3 text-left font-medium hidden md:table-cell">Summary</th>
              <th class="px-4 py-3 text-left font-medium">Status</th>
              <th class="px-4 py-3 text-left font-medium hidden lg:table-cell">Created by</th>
              <th v-if="can.manage" class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="v in versions"
              :key="v.id"
              :class="v.status === 'active' ? 'bg-emerald-50/40' : 'hover:bg-slate-50/60'"
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
                <span :class="['text-xs font-semibold px-2 py-0.5 rounded capitalize', STATUS_CLASS[v.status] ?? 'bg-slate-100 text-slate-500']">
                  {{ v.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-500 hidden lg:table-cell text-xs">
                {{ v.creator?.name ?? '—' }}
              </td>
              <td v-if="can.manage" class="px-4 py-3">
                <div class="flex items-center gap-1.5 justify-end">
                  <!-- View snapshot -->
                  <button
                    v-if="v.snapshot"
                    @click="viewSnapshot(v)"
                    class="text-xs px-2 py-1 rounded hover:bg-slate-100 text-slate-500"
                  >
                    Snapshot
                  </button>
                  <!-- Approve -->
                  <button
                    v-if="v.status === 'draft'"
                    @click="approveVersion(v)"
                    :disabled="loading"
                    class="text-xs px-2 py-1 rounded bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium disabled:opacity-60"
                  >
                    Approve
                  </button>
                  <!-- Activate -->
                  <button
                    v-if="v.status === 'approved'"
                    @click="activateVersion(v)"
                    :disabled="loading"
                    class="text-xs px-2 py-1 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-medium disabled:opacity-60"
                  >
                    Activate
                  </button>
                  <!-- Delete draft -->
                  <button
                    v-if="v.status === 'draft'"
                    @click="deleteVersion(v)"
                    :disabled="loading"
                    class="text-xs px-2 py-1 rounded hover:bg-red-50 text-slate-400 hover:text-red-500 disabled:opacity-60"
                  >
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex gap-3">
        <a :href="route('hr.org.index')" class="text-sm text-indigo-600 hover:underline">← Back to Org Chart</a>
        <a :href="route('hr.org.reports')" class="text-sm text-indigo-600 hover:underline">Reports →</a>
      </div>
    </div>

    <!-- ── CREATE DRAFT MODAL ─────────────────────────────────────────────────── -->
    <AppModal :show="showCreate" title="New Draft Version" size="lg" @close="showCreate = false">
      <form @submit.prevent="submitCreate" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Version Number <span class="text-red-500">*</span></label>
            <input v-model="form.version_number" type="text" placeholder="e.g. 2026-001"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': errors.version_number }"
            />
            <p v-if="errors.version_number" class="text-red-500 text-xs mt-1">{{ errors.version_number[0] }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Effective Date <span class="text-red-500">*</span></label>
            <input v-model="form.effective_date" type="date"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': errors.effective_date }"
            />
            <p v-if="errors.effective_date" class="text-red-500 text-xs mt-1">{{ errors.effective_date[0] }}</p>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-red-500">*</span></label>
          <input v-model="form.name" type="text" placeholder="e.g. FY 2026 Reorganization"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            :class="{ 'border-red-400': errors.name }"
          />
          <p v-if="errors.name" class="text-red-500 text-xs mt-1">{{ errors.name[0] }}</p>
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
        <button @click="showCreate = false" class="text-sm px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">Cancel</button>
        <button @click="submitCreate" :disabled="loading"
          class="text-sm px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium disabled:opacity-60">
          {{ loading ? 'Creating…' : 'Create Draft' }}
        </button>
      </template>
    </AppModal>

    <!-- ── SNAPSHOT VIEWER MODAL ──────────────────────────────────────────────── -->
    <AppModal :show="showSnapshot" :title="`Snapshot — ${snapshotData?.name ?? ''}`" size="3xl" @close="showSnapshot = false">
      <div v-if="snapshotData?.snapshot" class="font-mono text-xs bg-slate-900 text-emerald-400 rounded-lg p-4 max-h-96 overflow-y-auto whitespace-pre-wrap">
        {{ JSON.stringify(snapshotData.snapshot, null, 2) }}
      </div>
      <p v-else class="text-sm text-slate-500">No snapshot captured yet. Snapshots are taken when a version is activated.</p>
      <template #footer>
        <button @click="showSnapshot = false" class="text-sm px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">Close</button>
      </template>
    </AppModal>

  </AdminLayout>
</template>
