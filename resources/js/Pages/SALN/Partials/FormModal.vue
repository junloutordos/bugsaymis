<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto">
      <!-- Backdrop -->
      <div class="fixed inset-0 bg-black/40" @click="!persistent && $emit('close')" />

      <!-- Dialog -->
      <div class="relative bg-white rounded-xl shadow-xl w-full my-8"
        :class="size === 'lg' ? 'max-w-2xl' : size === 'xl' ? 'max-w-3xl' : 'max-w-lg'">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
          <h3 class="text-base font-semibold text-slate-800">{{ title }}</h3>
          <button type="button" @click="$emit('close')"
            class="p-1 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>

        <!-- Body -->
        <div class="px-6 py-5">
          <slot />
        </div>

        <!-- Footer -->
        <div class="flex gap-3 justify-end px-6 py-4 border-t border-slate-100">
          <button type="button" @click="$emit('close')"
            class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">
            Cancel
          </button>
          <button type="button" @click="$emit('save')" :disabled="processing"
            class="inline-flex items-center gap-2 px-5 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
            <CheckIcon class="h-4 w-4" />
            {{ processing ? 'Saving…' : (saveLabel ?? 'Save') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { XMarkIcon, CheckIcon } from '@heroicons/vue/24/outline'

defineProps({
  show: Boolean,
  title: String,
  size: { type: String, default: 'md' }, // 'md' | 'lg' | 'xl'
  processing: Boolean,
  saveLabel: String,
  persistent: Boolean, // prevent close on backdrop click
})

defineEmits(['close', 'save'])
</script>
