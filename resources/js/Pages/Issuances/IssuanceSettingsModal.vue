<script setup>
import { ref, watch } from 'vue'
import AppModal from '@/Components/AppModal.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import {
  PlusIcon, TrashIcon, PencilIcon, CheckIcon, XMarkIcon,
  Cog6ToothIcon, ListBulletIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({ show: Boolean })
const emit  = defineEmits(['close'])

// ── State ───────────────────────────────────────────────────────────────────
const activeTab  = ref('types')
const loading    = ref(false)
const types      = ref([])
const year       = ref(new Date().getFullYear())

// Editing existing type
const editingCode = ref(null)
const editForm    = ref({})
const editSaving  = ref(false)
const editError   = ref('')

// Adding new type
const showAddForm = ref(false)
const newType     = ref({ code: '', label: '', description: '', series_padding: 3 })
const addSaving   = ref(false)
const addError    = ref('')

// Series saving state — keyed by type code
const seriesSaving = ref({})

// ── Load ─────────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(route('issuances.settings.index', { year: year.value }))
    types.value = data.types.map(t => ({ ...t, series_start: t.series_start ?? 0 }))
  } finally {
    loading.value = false
  }
}

watch(() => props.show, v => { if (v) load() })
watch(year, load)

// ── Types tab ────────────────────────────────────────────────────────────────
function startEdit(type) {
  editingCode.value = type.code
  editForm.value    = { label: type.label, description: type.description ?? '', series_padding: type.series_padding }
  editError.value   = ''
}

function cancelEdit() {
  editingCode.value = null
  editForm.value    = {}
}

async function saveEdit(code) {
  editSaving.value = true
  editError.value  = ''
  try {
    await axios.put(route('issuances.settings.types.update', code), editForm.value)
    editingCode.value = null
    await load()
  } catch (e) {
    editError.value = e.response?.data?.message ?? 'Failed to save.'
  } finally {
    editSaving.value = false
  }
}

async function toggleActive(type) {
  try {
    await axios.put(route('issuances.settings.types.update', type.code), { is_active: !type.is_active })
    await load()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Cannot update type.')
  }
}

async function deleteType(type) {
  if (!(await confirmDelete(`Delete "${type.label}" (${type.code})? This cannot be undone.`))) return
  try {
    await axios.delete(route('issuances.settings.types.destroy', type.code))
    await load()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Cannot delete type.')
  }
}

async function addType() {
  addError.value  = ''
  addSaving.value = true
  try {
    await axios.post(route('issuances.settings.types.store'), newType.value)
    newType.value   = { code: '', label: '', description: '', series_padding: 3 }
    showAddForm.value = false
    await load()
  } catch (e) {
    const errs = e.response?.data?.errors
    addError.value = errs
      ? Object.values(errs).flat().join(' ')
      : (e.response?.data?.message ?? 'Failed to add type.')
  } finally {
    addSaving.value = false
  }
}

// ── Series tab ────────────────────────────────────────────────────────────────
async function saveSeries(type) {
  seriesSaving.value[type.code] = true
  try {
    await axios.put(route('issuances.settings.series.upsert'), {
      type_code:    type.code,
      year:         year.value,
      series_start: Number(type.series_start),
    })
  } catch (e) {
    alert(e.response?.data?.message ?? 'Failed to save series offset.')
  } finally {
    seriesSaving.value[type.code] = false
  }
}

const currentYear = new Date().getFullYear()
const yearOptions = Array.from({ length: 5 }, (_, i) => currentYear - 2 + i)
</script>

<template>
  <AppModal :show="show" title="Issuance Settings" size="3xl" @close="emit('close')">

    <!-- Tabs -->
    <div class="flex gap-0 border-b border-slate-200 -mx-6 px-6 mb-5">
      <button
        v-for="tab in [{ key: 'types', label: 'Issuance Types', icon: ListBulletIcon }, { key: 'series', label: 'Series Numbering', icon: Cog6ToothIcon }]"
        :key="tab.key"
        @click="activeTab = tab.key"
        class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
        :class="activeTab === tab.key ? 'text-indigo-600 border-indigo-600' : 'text-slate-500 border-transparent hover:text-slate-700'">
        <component :is="tab.icon" class="h-4 w-4" />
        {{ tab.label }}
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-10 text-sm text-slate-400">
      Loading…
    </div>

    <!-- ── Types Tab ──────────────────────────────────────────────────────── -->
    <div v-else-if="activeTab === 'types'" class="space-y-3">

      <!-- Types table -->
      <AppTable :is-empty="types.length === 0" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-20">Code</th>
            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Label / Description</th>
            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-16 text-center">Pad</th>
            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-20 text-center">Active</th>
            <th class="px-3 py-2.5 w-24"></th>
          </tr>
        </template>

        <tr v-for="type in types" :key="type.code">

          <!-- View mode -->
          <template v-if="editingCode !== type.code">
            <td class="px-4 py-3">
              <span class="font-mono text-xs font-bold text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded">{{ type.code }}</span>
            </td>
            <td class="px-4 py-3">
              <p class="font-medium text-slate-800 text-sm">{{ type.label }}</p>
              <p v-if="type.description" class="text-xs text-slate-400 mt-0.5 leading-snug">{{ type.description }}</p>
            </td>
            <td class="px-4 py-3 text-center text-xs text-slate-500">{{ type.series_padding }}</td>
            <td class="px-4 py-3 text-center">
              <button @click="toggleActive(type)"
                class="inline-flex items-center justify-center w-9 h-5 rounded-full transition-colors"
                :class="type.is_active ? 'bg-indigo-600' : 'bg-slate-200'"
                :title="type.is_active ? 'Active — click to disable' : 'Disabled — click to enable'">
                <span class="w-3.5 h-3.5 bg-white rounded-full shadow transition-transform"
                  :class="type.is_active ? 'translate-x-2' : '-translate-x-2'" />
              </button>
            </td>
            <td class="px-3 py-3">
              <div class="flex items-center justify-end gap-1">
                <AppIconButton label="Edit" @click="startEdit(type)">
                  <PencilIcon class="h-4 w-4" />
                </AppIconButton>
                <AppIconButton label="Delete" variant="danger" @click="deleteType(type)">
                  <TrashIcon class="h-4 w-4" />
                </AppIconButton>
              </div>
            </td>
          </template>

          <!-- Edit mode -->
          <template v-else>
            <td class="px-4 py-3">
              <span class="font-mono text-xs font-bold text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded">{{ type.code }}</span>
            </td>
            <td class="px-4 py-2 space-y-1.5" colspan="2">
              <input v-model="editForm.label" placeholder="Label" maxlength="100"
                class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <input v-model="editForm.description" placeholder="Description (optional)" maxlength="500"
                class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <div class="flex items-center gap-2">
                <label class="text-xs text-slate-500">Series padding:</label>
                <input v-model.number="editForm.series_padding" type="number" min="1" max="6"
                  class="w-14 rounded-lg border border-slate-200 px-2 py-1 text-xs text-center focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <p v-if="editError" class="text-xs text-danger-600">{{ editError }}</p>
            </td>
            <td class="px-3 py-2">
              <div class="flex items-center justify-end gap-1">
                <AppIconButton label="Save" variant="primary" :disabled="editSaving" @click="saveEdit(type.code)">
                  <CheckIcon class="h-4 w-4" />
                </AppIconButton>
                <AppIconButton label="Cancel" @click="cancelEdit">
                  <XMarkIcon class="h-4 w-4" />
                </AppIconButton>
              </div>
            </td>
          </template>

        </tr>

        <template #empty>
          <EmptyState title="No issuance types configured" />
        </template>
      </AppTable>

      <!-- Add new type -->
      <div v-if="!showAddForm">
        <button @click="showAddForm = true; addError = ''"
          class="flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800 px-1">
          <PlusIcon class="h-4 w-4" /> Add New Type
        </button>
      </div>

      <div v-else class="border border-indigo-200 bg-indigo-50/50 rounded-xl p-4 space-y-3">
        <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">New Issuance Type</p>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-slate-500 mb-1">Code <span class="text-danger-600">*</span></label>
            <input v-model="newType.code" placeholder="e.g. RES" maxlength="20"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-indigo-500"
              @input="newType.code = newType.code.toUpperCase().replace(/[^A-Z0-9]/g, '')" />
            <p class="text-[10px] text-slate-400 mt-0.5">Uppercase letters and numbers only</p>
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Label <span class="text-danger-600">*</span></label>
            <input v-model="newType.label" placeholder="e.g. Resolution" maxlength="100"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Description <span class="text-slate-300">(optional)</span></label>
          <input v-model="newType.description" placeholder="Brief description of this issuance type" maxlength="500"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div class="flex items-center gap-3">
          <label class="text-xs text-slate-500">Series number padding:</label>
          <input v-model.number="newType.series_padding" type="number" min="1" max="6"
            class="w-16 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm text-center focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          <span class="text-xs text-slate-400">→ e.g. {{ newType.code || 'RES' }}-{{ new Date().getFullYear() }}-{{ '1'.padStart(newType.series_padding, '0') }}</span>
        </div>
        <p v-if="addError" class="text-xs text-danger-600">{{ addError }}</p>
        <div class="flex gap-2 pt-1">
          <AppButton size="sm" :disabled="addSaving || !newType.code || !newType.label" :loading="addSaving" @click="addType">
            {{ addSaving ? 'Adding…' : 'Add Type' }}
          </AppButton>
          <AppButton size="sm" variant="secondary" @click="showAddForm = false; addError = ''">
            Cancel
          </AppButton>
        </div>
      </div>
    </div>

    <!-- ── Series Numbering Tab ───────────────────────────────────────────── -->
    <div v-else-if="activeTab === 'series'" class="space-y-4">

      <!-- Year selector -->
      <div class="flex items-center gap-3">
        <label class="text-sm font-medium text-slate-700">Year:</label>
        <select v-model.number="year"
          class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>

      <div class="bg-warning-50 border border-warning-100 rounded-lg px-4 py-3 text-xs text-warning-700 leading-relaxed">
        <strong>How this works:</strong> Enter the last number used in manual/offline issuances for each type.
        The system will start auto-numbering from the <em>next</em> number.
        For example, if you manually created 5 Special Orders, set it to <strong>5</strong> — the system will assign #6 next.
      </div>

      <AppTable :is-empty="types.filter(t => t.is_active).length === 0" :skeleton-cols="4">
        <template #head>
          <tr>
            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Type</th>
            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-44">Continue from #</th>
            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-36">Next will be</th>
            <th class="px-3 py-2.5 w-20"></th>
          </tr>
        </template>

        <tr v-for="type in types.filter(t => t.is_active)" :key="type.code">
          <td class="px-4 py-3">
            <p class="font-medium text-slate-800">{{ type.label }}</p>
            <span class="font-mono text-[11px] text-indigo-600">{{ type.code }}</span>
          </td>
          <td class="px-4 py-2.5">
            <input v-model.number="type.series_start" type="number" min="0"
              class="w-28 rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-center focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </td>
          <td class="px-4 py-3 font-mono text-xs text-slate-600">
            {{ type.code }}-{{ year }}-{{ String(Number(type.series_start) + 1).padStart(type.series_padding, '0') }}
          </td>
          <td class="px-3 py-2.5">
            <AppButton size="sm" :disabled="seriesSaving[type.code]" :loading="seriesSaving[type.code]" @click="saveSeries(type)">
              {{ seriesSaving[type.code] ? 'Saving…' : 'Save' }}
            </AppButton>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No active issuance types" />
        </template>
      </AppTable>
    </div>

  </AppModal>
</template>
