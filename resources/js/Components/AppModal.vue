<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline'

defineProps({
  show:               { type: Boolean, default: false },
  title:              { type: String,  default: '' },
  subtitle:           { type: String,  default: null },
  size:               { type: String,  default: 'md' }, // sm | md | lg | xl | 2xl
  closeOnBackdrop:    { type: Boolean, default: true },
  showCloseButton:    { type: Boolean, default: true },
  bodyClass:          { type: String,  default: 'px-6 py-5' },
  panelClass:         { type: String,  default: '' },
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
        <div
          class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"
          @click="closeOnBackdrop && $emit('close')"
        />

        <!-- Panel -->
        <div
          :class="['app-modal-panel relative w-full bg-white rounded-2xl shadow-xl ring-1 ring-slate-200/60 z-10 flex flex-col max-h-[90vh]', sizeMap[size] ?? sizeMap.md, panelClass]"
        >
          <!-- Header -->
          <div v-if="title || subtitle || $slots.header || showCloseButton" class="flex items-start justify-between gap-4 px-6 py-4 border-b border-slate-100 shrink-0">
            <div class="min-w-0">
              <h2 v-if="title" class="font-heading text-base font-semibold text-slate-900">{{ title }}</h2>
              <p v-if="subtitle" class="mt-0.5 text-sm text-slate-500">{{ subtitle }}</p>
            </div>
            <slot name="header" />
            <button
              v-if="showCloseButton"
              type="button"
              @click="$emit('close')"
              class="shrink-0 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
              aria-label="Close"
            >
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>

          <!-- Body -->
          <div :class="['overflow-y-auto flex-1', bodyClass]">
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

<style scoped>
/* Panel entrance: subtle rise + settle (backdrop fades via the Transition) */
.app-modal-panel {
  animation: app-modal-pop 180ms ease-out;
}
@keyframes app-modal-pop {
  from {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
@media (prefers-reduced-motion: reduce) {
  .app-modal-panel {
    animation: none;
  }
}
</style>
