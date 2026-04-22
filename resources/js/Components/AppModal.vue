<script setup>
defineProps({
  show:  { type: Boolean, default: false },
  title: { type: String,  default: '' },
  size:  { type: String,  default: 'md' }, // sm | md | lg | xl | 2xl
})
defineEmits(['close'])

const sizeMap = {
  sm:  'max-w-sm',
  md:  'max-w-md',
  lg:  'max-w-lg',
  xl:  'max-w-xl',
  '2xl': 'max-w-2xl',
  '3xl': 'max-w-3xl',
  '4xl': 'max-w-4xl',
  full: 'max-w-full',
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-150"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="$emit('close')" />

        <!-- Panel -->
        <div
          :class="['relative w-full bg-white rounded-2xl shadow-xl z-10 flex flex-col max-h-[90vh]', sizeMap[size] ?? sizeMap.md]"
        >
          <!-- Header -->
          <div v-if="title || $slots.header" class="flex items-center justify-between px-6 py-4 border-b border-slate-100 shrink-0">
            <h2 class="text-base font-semibold text-slate-800">{{ title }}</h2>
            <slot name="header" />
            <button @click="$emit('close')" class="ml-4 p-1 rounded-md hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="overflow-y-auto flex-1 px-6 py-5">
            <slot />
          </div>

          <!-- Footer -->
          <div v-if="$slots.footer" class="px-6 py-4 border-t border-slate-100 shrink-0 flex justify-end gap-2">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
