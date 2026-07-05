<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black/40" @click="$emit('cancel')" />

      <!-- Dialog -->
      <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-start gap-3">
          <div class="shrink-0 flex items-center justify-center w-10 h-10 rounded-full"
            :class="variant === 'danger' ? 'bg-danger-100' : 'bg-indigo-100'">
            <component :is="icon ?? (variant === 'danger' ? ExclamationTriangleIcon : QuestionMarkCircleIcon)"
              class="h-5 w-5"
              :class="variant === 'danger' ? 'text-danger-600' : 'text-indigo-600'" />
          </div>
          <div>
            <h3 class="text-base font-semibold text-slate-800">{{ title }}</h3>
            <p class="text-sm text-slate-500 mt-1">{{ message }}</p>
            <slot />
          </div>
        </div>

        <!-- Remarks slot (optional) -->
        <div v-if="showRemarks" class="pt-1">
          <label class="block text-xs font-medium text-slate-600 mb-1">
            Remarks <span v-if="remarksRequired" class="text-danger-500">*</span>
          </label>
          <textarea v-model="localRemarks" rows="3"
            :placeholder="remarksPlaceholder ?? 'Enter remarks…'"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        </div>

        <div class="flex gap-3 justify-end pt-2">
          <button type="button" @click="$emit('cancel')"
            class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">
            {{ cancelLabel ?? 'Cancel' }}
          </button>
          <button type="button" @click="handleConfirm" :disabled="processing"
            class="px-4 py-2 text-sm text-white rounded-lg font-medium disabled:opacity-50 transition-colors"
            :class="variant === 'danger' ? 'bg-danger-600 hover:bg-danger-700' : 'bg-indigo-600 hover:bg-indigo-700'">
            {{ processing ? 'Processing…' : (confirmLabel ?? 'Confirm') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue'
import { ExclamationTriangleIcon, QuestionMarkCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show: { type: Boolean, default: true },
  title: { type: String, default: 'Confirm Action' },
  message: String,
  variant: { type: String, default: 'default' }, // 'default' | 'danger'
  confirmLabel: String,
  cancelLabel: String,
  processing: Boolean,
  showRemarks: Boolean,
  remarksRequired: Boolean,
  remarksPlaceholder: String,
  icon: Object,
})

const emit = defineEmits(['confirm', 'cancel'])

const localRemarks = ref('')

watch(() => props.show, (val) => {
  if (!val) localRemarks.value = ''
})

function handleConfirm() {
  emit('confirm', localRemarks.value)
}
</script>
