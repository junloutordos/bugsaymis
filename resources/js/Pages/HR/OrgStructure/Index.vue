<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import OrgTreeNode from './Partials/OrgTreeNode.vue'
import {
  PlusIcon,
  MagnifyingGlassIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  BuildingLibraryIcon,
  ArrowPathIcon,
  ArrowDownTrayIcon,
  PrinterIcon,
  ChartBarIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline'

// ── Props ──────────────────────────────────────────────────────────────────────
const props = defineProps({
  tree:            { type: Array,   default: () => [] },
  includeInactive: { type: Boolean, default: false },
  types:           { type: Array,   default: () => [] },
  can:             { type: Object,  default: () => ({}) },
})

// ── State ──────────────────────────────────────────────────────────────────────
const search       = ref('')
const flash        = ref({ success: null, error: null })
const loading      = ref(false)
const selectedNode = ref(null)

// Modal states
const showCreate = ref(false)
const showEdit   = ref(false)
const showDelete = ref(false)

// Blank form template
function blankForm() {
  return {
    code:             '',
    name:             '',
    short_name:       '',
    description:      '',
    type:             'unit',
    parent_id:        null,
    order_index:      0,
    is_active:        true,
    established_date: '',
    abolished_date:   '',
    legal_basis:      '',
    mandate:          '',
    remarks:          '',
  }
}

const form   = ref(blankForm())
const errors = ref({})
const target = ref(null)   // the unit being edited or deleted

// ── Computed — flat searchable list ───────────────────────────────────────────
function flattenTree(nodes, list = []) {
  for (const n of nodes) {
    list.push(n)
    if (n.children?.length) flattenTree(n.children, list)
  }
  return list
}

const flatUnits = computed(() => flattenTree(props.tree))

const filteredTree = computed(() => {
  if (!search.value.trim()) return props.tree

  const q = search.value.toLowerCase()
  const matchIds = new Set(
    flatUnits.value
      .filter(n =>
        n.name.toLowerCase().includes(q) ||
        n.code.toLowerCase().includes(q) ||
        (n.short_name ?? '').toLowerCase().includes(q)
      )
      .map(n => n.id)
  )

  // Include ancestors of matches so the tree stays connected
  function includesMatch(node) {
    if (matchIds.has(node.id)) return true
    return node.children?.some(includesMatch) ?? false
  }

  function filterTree(nodes) {
    return nodes
      .filter(includesMatch)
      .map(n => ({ ...n, children: filterTree(n.children ?? []) }))
  }

  return filterTree(props.tree)
})

// ── Open modals ────────────────────────────────────────────────────────────────
function openCreate(parentNode = null) {
  form.value   = blankForm()
  form.value.parent_id = parentNode?.id ?? null
  errors.value = {}
  showCreate.value = true
}

function openEdit(node) {
  target.value = node
  form.value   = {
    code:             node.code,
    name:             node.name,
    short_name:       node.short_name ?? '',
    description:      node.description ?? '',
    type:             node.type,
    parent_id:        node.parent_id,
    order_index:      node.order_index ?? 0,
    is_active:        node.is_active,
    established_date: node.established_date ?? '',
    abolished_date:   node.abolished_date ?? '',
    legal_basis:      node.legal_basis ?? '',
    mandate:          node.mandate ?? '',
    remarks:          node.remarks ?? '',
  }
  errors.value = {}
  showEdit.value = true
}

function openDelete(node) {
  target.value  = node
  showDelete.value = true
}

function selectNode(node) {
  selectedNode.value = selectedNode.value?.id === node.id ? null : node
}

// ── Submit helpers ─────────────────────────────────────────────────────────────
function setFlash(type, msg) {
  flash.value = { success: null, error: null, [type]: msg }
  setTimeout(() => { flash.value = { success: null, error: null } }, 4000)
}

function reloadTree() {
  router.reload({ only: ['tree'], preserveScroll: true })
}

async function submitCreate() {
  loading.value = true
  errors.value  = {}
  try {
    await axios.post(route('hr.org.units.store'), form.value)
    showCreate.value = false
    reloadTree()
    setFlash('success', 'Organizational unit created.')
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

async function submitUpdate() {
  loading.value = true
  errors.value  = {}
  try {
    await axios.put(route('hr.org.units.update', target.value.id), form.value)
    showEdit.value = false
    reloadTree()
    setFlash('success', `"${target.value.name}" updated.`)
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

async function submitDelete() {
  loading.value = true
  try {
    await axios.delete(route('hr.org.units.destroy', target.value.id))
    showDelete.value = false
    selectedNode.value = null
    reloadTree()
    setFlash('success', `"${target.value.name}" archived.`)
  } catch (e) {
    setFlash('error', e.response?.data?.message ?? 'An error occurred.')
    showDelete.value = false
  } finally {
    loading.value = false
  }
}

// ── Toggle inactive view ───────────────────────────────────────────────────────
function toggleInactive() {
  router.get(route('hr.org.index'), { include_inactive: !props.includeInactive ? 1 : 0 }, {
    preserveScroll: true,
    preserveState:  true,
    replace:        true,
  })
}
</script>

<template>
  <Head title="Organizational Structure" />
  <AdminLayout title="Organizational Structure">
    <div class="space-y-5">

      <!-- ── Page header ──────────────────────────────────────────────────────── -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
            <BuildingLibraryIcon class="h-6 w-6 text-indigo-500" />
            Organizational Structure
          </h1>
          <p class="text-sm text-slate-500 mt-0.5">
            Manage the official hierarchy of organizational units.
          </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Export/Report shortcuts -->
          <a
            v-if="can.export"
            :href="route('hr.org.export.pdf')"
            target="_blank"
            class="inline-flex items-center gap-1.5 text-sm px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg"
            title="Download PDF"
          >
            <ArrowDownTrayIcon class="h-4 w-4" />
          </a>
          <a
            v-if="can.reports"
            :href="route('hr.org.reports')"
            class="inline-flex items-center gap-1.5 text-sm px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg"
            title="Reports"
          >
            <ChartBarIcon class="h-4 w-4" />
          </a>
          <a
            v-if="can.versions"
            :href="route('hr.org.versions.index')"
            class="inline-flex items-center gap-1.5 text-sm px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg"
            title="Versions"
          >
            <ClockIcon class="h-4 w-4" />
          </a>
          <button
            v-if="can.create"
            @click="openCreate()"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors shadow-sm"
          >
            <PlusIcon class="h-4 w-4" /> New Unit
          </button>
        </div>
      </div>

      <!-- ── Flash messages ───────────────────────────────────────────────────── -->
      <div v-if="flash.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" /> {{ flash.success }}
      </div>
      <div v-if="flash.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" /> {{ flash.error }}
      </div>

      <!-- ── Main layout: tree + side panel ──────────────────────────────────── -->
      <div class="flex gap-5 items-start">

        <!-- Tree card -->
        <div class="flex-1 min-w-0 bg-white rounded-xl border border-slate-100 shadow-sm">

          <!-- Toolbar -->
          <div class="px-4 py-3 border-b border-slate-100 flex items-center gap-3">
            <div class="relative flex-1 max-w-xs">
              <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
              <input
                v-model="search"
                type="search"
                placeholder="Search units…"
                class="w-full pl-8 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400"
              />
            </div>
            <button
              v-if="can.update"
              @click="toggleInactive"
              :class="[
                'text-xs px-3 py-1.5 rounded-lg border transition-colors',
                includeInactive
                  ? 'bg-amber-50 border-amber-200 text-amber-700'
                  : 'bg-slate-50 border-slate-200 text-slate-500 hover:bg-slate-100',
              ]"
            >
              {{ includeInactive ? 'Hiding inactive' : 'Show inactive' }}
            </button>
            <button @click="reloadTree" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400" title="Refresh">
              <ArrowPathIcon class="h-4 w-4" />
            </button>
          </div>

          <!-- Tree -->
          <div class="px-2 py-2 max-h-[70vh] overflow-y-auto">
            <div v-if="!filteredTree.length" class="px-4 py-12 text-center text-slate-400 text-sm">
              No organizational units found.
            </div>
            <OrgTreeNode
              v-for="root in filteredTree"
              :key="root.id"
              :node="root"
              :can="can"
              :depth="0"
              @edit="openEdit"
              @delete="openDelete"
              @add-child="openCreate"
              @select="selectNode"
            />
          </div>
        </div>

        <!-- Side panel — selected unit detail -->
        <div
          v-if="selectedNode"
          class="hidden lg:block w-80 shrink-0 bg-white rounded-xl border border-slate-100 shadow-sm sticky top-4"
        >
          <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-700">Unit Details</h3>
            <button @click="selectedNode = null" class="text-slate-400 hover:text-slate-600 text-xs">Close</button>
          </div>
          <div class="px-5 py-4 space-y-3 text-sm">
            <div>
              <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Name</p>
              <p class="font-medium text-slate-800 mt-0.5">{{ selectedNode.name }}</p>
            </div>
            <div v-if="selectedNode.short_name">
              <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Abbreviation</p>
              <p class="text-slate-700 mt-0.5">{{ selectedNode.short_name }}</p>
            </div>
            <div>
              <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Code</p>
              <p class="font-mono text-slate-700 mt-0.5">{{ selectedNode.code }}</p>
            </div>
            <div>
              <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Type</p>
              <p class="text-slate-700 mt-0.5 capitalize">{{ selectedNode.type }}</p>
            </div>
            <div>
              <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Status</p>
              <span :class="selectedNode.is_active ? 'text-emerald-600' : 'text-slate-400'" class="mt-0.5">
                {{ selectedNode.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
            <div v-if="selectedNode.current_head?.length">
              <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Head</p>
              <p class="text-slate-700 mt-0.5">{{ selectedNode.current_head[0]?.user?.name }}</p>
            </div>
            <div v-if="selectedNode.description">
              <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Description</p>
              <p class="text-slate-600 mt-0.5 text-xs leading-relaxed">{{ selectedNode.description }}</p>
            </div>
            <!-- Actions -->
            <div class="pt-2 flex gap-2 flex-wrap">
              <button
                v-if="can.update"
                @click="openEdit(selectedNode)"
                class="text-xs px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg font-medium"
              >
                Edit
              </button>
              <button
                v-if="can.create"
                @click="openCreate(selectedNode)"
                class="text-xs px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium"
              >
                Add Child
              </button>
              <a
                :href="route('hr.org.units.show', selectedNode.id)"
                class="text-xs px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium"
              >
                View Page →
              </a>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ── CREATE MODAL ───────────────────────────────────────────────────────── -->
    <AppModal :show="showCreate" title="New Organizational Unit" size="2xl" @close="showCreate = false">
      <form @submit.prevent="submitCreate" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <!-- Code -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Code <span class="text-red-500">*</span></label>
            <input v-model="form.code" type="text" maxlength="50" placeholder="e.g. ACAD-DIV"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': errors.code }"
            />
            <p v-if="errors.code" class="text-red-500 text-xs mt-1">{{ errors.code[0] }}</p>
          </div>
          <!-- Type -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Type <span class="text-red-500">*</span></label>
            <select v-model="form.type"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': errors.type }"
            >
              <option v-for="t in types" :key="t" :value="t" class="capitalize">{{ t }}</option>
            </select>
            <p v-if="errors.type" class="text-red-500 text-xs mt-1">{{ errors.type[0] }}</p>
          </div>
        </div>

        <!-- Name -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Full Name <span class="text-red-500">*</span></label>
          <input v-model="form.name" type="text" placeholder="Official unit name"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            :class="{ 'border-red-400': errors.name }"
          />
          <p v-if="errors.name" class="text-red-500 text-xs mt-1">{{ errors.name[0] }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <!-- Short name -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Abbreviation</label>
            <input v-model="form.short_name" type="text" maxlength="100" placeholder="e.g. ACAD"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            />
          </div>
          <!-- Parent -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Parent Unit</label>
            <select v-model="form.parent_id"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': errors.parent_id }"
            >
              <option :value="null">— None (root) —</option>
              <option v-for="u in flatUnits" :key="u.id" :value="u.id">
                {{ '–'.repeat(u.depth) }} {{ u.name }} ({{ u.code }})
              </option>
            </select>
            <p v-if="errors.parent_id" class="text-red-500 text-xs mt-1">{{ errors.parent_id[0] }}</p>
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
          <textarea v-model="form.description" rows="2" placeholder="Brief description of the unit's function"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"
          ></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <!-- Established date -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Established Date</label>
            <input v-model="form.established_date" type="date"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            />
          </div>
          <!-- Order index -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Sort Order</label>
            <input v-model.number="form.order_index" type="number" min="0"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            />
          </div>
        </div>

        <!-- Legal basis -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Legal Basis</label>
          <input v-model="form.legal_basis" type="text" placeholder="RA / EO / CSC Resolution"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
          />
        </div>

        <!-- Active toggle -->
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
          <span class="text-sm text-slate-700">Active unit</span>
        </label>
      </form>

      <template #footer>
        <button @click="showCreate = false" class="text-sm px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">Cancel</button>
        <button @click="submitCreate" :disabled="loading"
          class="text-sm px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium disabled:opacity-60">
          {{ loading ? 'Saving…' : 'Create Unit' }}
        </button>
      </template>
    </AppModal>

    <!-- ── EDIT MODAL ─────────────────────────────────────────────────────────── -->
    <AppModal :show="showEdit" :title="`Edit — ${target?.name ?? ''}`" size="2xl" @close="showEdit = false">
      <form @submit.prevent="submitUpdate" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Code <span class="text-red-500">*</span></label>
            <input v-model="form.code" type="text" maxlength="50"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': errors.code }"
            />
            <p v-if="errors.code" class="text-red-500 text-xs mt-1">{{ errors.code[0] }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Type <span class="text-red-500">*</span></label>
            <select v-model="form.type"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            >
              <option v-for="t in types" :key="t" :value="t" class="capitalize">{{ t }}</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Full Name <span class="text-red-500">*</span></label>
          <input v-model="form.name" type="text"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            :class="{ 'border-red-400': errors.name }"
          />
          <p v-if="errors.name" class="text-red-500 text-xs mt-1">{{ errors.name[0] }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Abbreviation</label>
            <input v-model="form.short_name" type="text" maxlength="100"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Parent Unit</label>
            <select v-model="form.parent_id"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': errors.parent_id }"
            >
              <option :value="null">— None (root) —</option>
              <option
                v-for="u in flatUnits.filter(u => u.id !== target?.id)"
                :key="u.id"
                :value="u.id"
              >
                {{ '–'.repeat(u.depth) }} {{ u.name }} ({{ u.code }})
              </option>
            </select>
            <p v-if="errors.parent_id" class="text-red-500 text-xs mt-1">{{ errors.parent_id[0] }}</p>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
          <textarea v-model="form.description" rows="2"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"
          ></textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Established Date</label>
            <input v-model="form.established_date" type="date"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Abolished Date</label>
            <input v-model="form.abolished_date" type="date"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            />
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Legal Basis</label>
          <input v-model="form.legal_basis" type="text" placeholder="RA / EO / CSC Resolution"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Mandate</label>
          <textarea v-model="form.mandate" rows="2" placeholder="Official functions and responsibilities"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"
          ></textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Sort Order</label>
            <input v-model.number="form.order_index" type="number" min="0"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
            <input v-model="form.remarks" type="text"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
            />
          </div>
        </div>
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
          <span class="text-sm text-slate-700">Active unit</span>
        </label>
      </form>

      <template #footer>
        <button @click="showEdit = false" class="text-sm px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">Cancel</button>
        <button @click="submitUpdate" :disabled="loading"
          class="text-sm px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium disabled:opacity-60">
          {{ loading ? 'Saving…' : 'Save Changes' }}
        </button>
      </template>
    </AppModal>

    <!-- ── DELETE CONFIRM MODAL ───────────────────────────────────────────────── -->
    <AppModal :show="showDelete" title="Archive Unit?" size="sm" @close="showDelete = false">
      <p class="text-sm text-slate-600">
        Are you sure you want to archive
        <span class="font-semibold text-slate-800">{{ target?.name }}</span>?
        It will be soft-deleted and hidden from the org chart.
        Active employees or child units will prevent deletion.
      </p>
      <template #footer>
        <button @click="showDelete = false" class="text-sm px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">Cancel</button>
        <button @click="submitDelete" :disabled="loading"
          class="text-sm px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white font-medium disabled:opacity-60">
          {{ loading ? 'Archiving…' : 'Archive' }}
        </button>
      </template>
    </AppModal>

  </AdminLayout>
</template>
