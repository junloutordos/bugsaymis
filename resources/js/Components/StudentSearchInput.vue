<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: [Number, String], default: null },
  searchRouteName: { type: String, required: true },
  label: { type: String, default: 'Student' },
  error: { type: String, default: '' },
  required: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue', 'select'])

const query = ref('')
const results = ref([])
const selected = ref(null)
const searching = ref(false)
let timer = null

async function fetchResults() {
  const term = query.value.trim()
  if (term.length < 2) { results.value = []; return }
  try {
    searching.value = true
    const url = `${route(props.searchRouteName)}?q=${encodeURIComponent(term)}`
    const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    const data = await res.json().catch(() => ({}))
    results.value = res.ok ? (data.students || []) : []
  } catch {
    results.value = []
  } finally {
    searching.value = false
  }
}

watch(query, (val) => {
  if (selected.value && val !== selected.value.name) {
    selected.value = null
    emit('update:modelValue', null)
    emit('select', null)
  }
  clearTimeout(timer)
  timer = setTimeout(fetchResults, 250)
})

function pick(s) {
  selected.value = s
  query.value = s.name
  results.value = []
  emit('update:modelValue', s.id)
  emit('select', s)
}

function clear() {
  selected.value = null
  query.value = ''
  results.value = []
  emit('update:modelValue', null)
  emit('select', null)
}

defineExpose({ clear })
</script>

<template>
  <div>
    <label class="block text-xs font-medium text-slate-600 mb-1">
      {{ label }} <span v-if="required" class="text-red-500">*</span>
    </label>
    <div class="relative">
      <input
        v-model="query"
        type="text"
        placeholder="Type student name or ID…"
        autocomplete="off"
        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
        :class="{ 'border-red-400': error }"
      />
      <span v-if="searching" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">Searching…</span>
      <div v-if="results.length"
           class="absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg max-h-52 overflow-y-auto">
        <button
          v-for="s in results"
          :key="s.id"
          type="button"
          @click="pick(s)"
          class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors"
        >
          {{ s.name }}<span v-if="s.pisay" class="ml-2 text-xs text-slate-400">({{ s.pisay }})</span>
        </button>
      </div>
    </div>
    <p v-if="selected" class="mt-1 text-xs text-emerald-600 font-medium">Selected: {{ selected.name }}</p>
    <p v-if="error" class="text-red-500 text-xs mt-1">{{ error }}</p>
  </div>
</template>
