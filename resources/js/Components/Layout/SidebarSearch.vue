<script setup>
import { nextTick, ref } from 'vue'
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: { type: String, default: '' },
  collapsed: { type: Boolean, default: false },
  resultCount: { type: Number, default: 0 },
  searching: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'expand'])
const input = ref(null)

const focus = async () => {
  if (props.collapsed) {
    emit('expand')
    await nextTick()
  }
  input.value?.focus()
}

const clear = async () => {
  emit('update:modelValue', '')
  await nextTick()
  input.value?.focus()
}

defineExpose({ focus })
</script>

<template>
  <div class="shrink-0 border-b border-slate-100 px-2 py-2">
    <button
      v-if="collapsed"
      type="button"
      class="mx-auto flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
      title="Search modules (Ctrl/⌘+K)"
      aria-label="Expand sidebar and search modules"
      @click="focus">
      <MagnifyingGlassIcon class="h-4 w-4" />
    </button>

    <div v-else class="relative">
      <MagnifyingGlassIcon
        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
      <input
        ref="input"
        type="search"
        :value="modelValue"
        placeholder="Search modules…"
        autocomplete="off"
        aria-label="Search sidebar modules"
        aria-keyshortcuts="Control+K Meta+K"
        class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-9 text-sm text-slate-700 placeholder-slate-400 transition-colors focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
        @input="emit('update:modelValue', $event.target.value)"
        @keydown.esc.prevent="clear" />
      <button
        v-if="modelValue"
        type="button"
        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        aria-label="Clear module search"
        @click="clear">
        <XMarkIcon class="h-3.5 w-3.5" />
      </button>
    </div>

    <p class="sr-only" aria-live="polite">
      {{ searching ? `${resultCount} module ${resultCount === 1 ? 'result' : 'results'} found` : '' }}
    </p>
  </div>
</template>

<style scoped>
input[type='search']::-webkit-search-cancel-button {
  display: none;
}
</style>
