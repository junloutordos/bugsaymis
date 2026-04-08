<script setup>
/**
 * OrgUnitSelect — a searchable org-unit dropdown.
 *
 * Usage:
 *   <OrgUnitSelect v-model="form.organizational_unit_id" placeholder="Select unit…" />
 *
 * Props:
 *   modelValue  — bound unit ID (null for none)
 *   placeholder — select placeholder text
 *   activeOnly  — only show active units (default true)
 *   error       — validation error string
 *
 * Emits:
 *   update:modelValue — selected unit id
 *   change            — full unit object
 */
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue:  { default: null },
  placeholder: { type: String,  default: 'Select organizational unit…' },
  activeOnly:  { type: Boolean, default: true },
  error:       { type: String,  default: '' },
})

const emit = defineEmits(['update:modelValue', 'change'])

// ── State ──────────────────────────────────────────────────────────────────────
const units     = ref([])
const search    = ref('')
const open      = ref(false)
const loading   = ref(false)
const selected  = ref(null)

// ── Load units once ────────────────────────────────────────────────────────────
async function loadUnits() {
  if (units.value.length) return
  loading.value = true
  try {
    const res = await axios.get(route('hr.org.units.list'), {
      params: { active_only: props.activeOnly ? 1 : 0, per_page: 500 }
    })
    units.value = res.data.data ?? res.data ?? []
  } catch {
    units.value = []
  } finally {
    loading.value = false
  }
}

// ── Filtered list ──────────────────────────────────────────────────────────────
const filtered = computed(() => {
  if (!search.value.trim()) return units.value
  const q = search.value.toLowerCase()
  return units.value.filter(u =>
    u.name.toLowerCase().includes(q) ||
    u.code.toLowerCase().includes(q) ||
    (u.short_name ?? '').toLowerCase().includes(q)
  )
})

// ── Sync selected label when modelValue changes externally ─────────────────────
watch(() => props.modelValue, (id) => {
  if (!id) { selected.value = null; return }
  if (units.value.length) {
    selected.value = units.value.find(u => u.id === id) ?? null
  }
}, { immediate: true })

watch(units, (list) => {
  if (props.modelValue && list.length) {
    selected.value = list.find(u => u.id === props.modelValue) ?? null
  }
})

// ── Interaction ────────────────────────────────────────────────────────────────
async function toggle() {
  if (!open.value) {
    await loadUnits()
  }
  open.value = !open.value
  if (open.value) search.value = ''
}

function select(unit) {
  selected.value = unit
  emit('update:modelValue', unit.id)
  emit('change', unit)
  open.value = false
  search.value = ''
}

function clear() {
  selected.value = null
  emit('update:modelValue', null)
  emit('change', null)
}

// Close on outside click
function onClickOutside(e) {
  if (!e.target.closest('[data-org-select]')) open.value = false
}
onMounted(() => document.addEventListener('click', onClickOutside))

// ── Type badge color ──────────────────────────────────────────────────────────
const TYPE_COLOR = {
  institution: 'bg-violet-100 text-violet-700',
  division:    'bg-blue-100 text-blue-700',
  department:  'bg-cyan-100 text-cyan-700',
  section:     'bg-teal-100 text-teal-700',
  unit:        'bg-emerald-100 text-emerald-700',
  office:      'bg-amber-100 text-amber-700',
  committee:   'bg-pink-100 text-pink-700',
}
</script>

<template>
  <div data-org-select class="relative">
    <!-- Trigger -->
    <button
      type="button"
      @click="toggle"
      :class="[
        'w-full flex items-center gap-2 border rounded-lg px-3 py-2 text-sm text-left focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white',
        error ? 'border-red-400' : 'border-slate-200',
      ]"
    >
      <template v-if="selected">
        <span :class="['text-[10px] font-semibold px-1.5 py-0.5 rounded uppercase tracking-wide shrink-0', TYPE_COLOR[selected.type] ?? 'bg-slate-100 text-slate-500']">
          {{ selected.type }}
        </span>
        <span class="flex-1 truncate text-slate-800">{{ selected.name }}</span>
        <span class="text-xs font-mono text-slate-400 shrink-0">{{ selected.code }}</span>
        <button type="button" @click.stop="clear" class="shrink-0 text-slate-400 hover:text-slate-600">
          <XMarkIcon class="h-3.5 w-3.5" />
        </button>
      </template>
      <template v-else>
        <span class="flex-1 text-slate-400">{{ placeholder }}</span>
        <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </template>
    </button>

    <!-- Error -->
    <p v-if="error" class="text-red-500 text-xs mt-1">{{ error }}</p>

    <!-- Dropdown -->
    <div
      v-if="open"
      class="absolute z-50 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg max-h-72 flex flex-col"
    >
      <!-- Search -->
      <div class="px-3 py-2 border-b border-slate-100 shrink-0">
        <div class="relative">
          <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
          <input
            v-model="search"
            type="text"
            placeholder="Search…"
            autofocus
            class="w-full pl-7 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-400"
          />
        </div>
      </div>

      <!-- Options -->
      <ul class="overflow-y-auto flex-1">
        <li v-if="loading" class="px-4 py-3 text-sm text-slate-400 text-center">Loading…</li>
        <li v-else-if="!filtered.length" class="px-4 py-3 text-sm text-slate-400 text-center">No units found.</li>
        <li
          v-for="u in filtered"
          :key="u.id"
          @click="select(u)"
          :class="[
            'flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-indigo-50 text-sm',
            modelValue === u.id ? 'bg-indigo-50' : '',
          ]"
          :style="{ paddingLeft: (u.depth * 10 + 12) + 'px' }"
        >
          <span :class="['text-[10px] font-semibold px-1.5 py-0.5 rounded uppercase tracking-wide shrink-0', TYPE_COLOR[u.type] ?? 'bg-slate-100 text-slate-500']">
            {{ u.type }}
          </span>
          <span class="flex-1 truncate text-slate-800" :class="{ 'font-medium': modelValue === u.id }">{{ u.name }}</span>
          <span class="text-xs font-mono text-slate-400 shrink-0">{{ u.code }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>
