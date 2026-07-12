<script setup>
defineOptions({ inheritAttrs: false })
defineProps({
  modelValue: { default: '' },
  label:      { type: String,  default: null },
  error:      { type: String,  default: null },
  required:   { type: Boolean, default: false },
  disabled:   { type: Boolean, default: false },
  placeholder:{ type: String,  default: 'Select…' },
  showBlank:  { type: Boolean, default: true },
})
defineEmits(['update:modelValue'])

const selectId = `app-select-${Math.random().toString(36).slice(2, 10)}`
const errorId = `${selectId}-error`
</script>

<template>
  <div>
    <label v-if="label" :for="selectId" class="block text-xs font-medium text-slate-600 mb-1">
      {{ label }} <span v-if="required" class="text-red-500">*</span>
    </label>
    <select
      v-bind="$attrs"
      :id="selectId"
      :value="modelValue"
      :disabled="disabled"
      :required="required"
      :aria-invalid="error ? 'true' : null"
      :aria-describedby="error ? errorId : null"
      @change="$emit('update:modelValue', $event.target.value)"
      class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/25 transition-colors"
      :class="[
        error    ? 'border-red-400 bg-red-50/30' : 'border-slate-200 bg-white hover:border-slate-300',
        disabled ? 'bg-slate-50 cursor-not-allowed text-slate-400' : '',
      ]"
    >
      <option v-if="showBlank" value="">{{ placeholder }}</option>
      <slot />
    </select>
    <p v-if="error" :id="errorId" class="mt-1 text-xs text-red-500">{{ error }}</p>
  </div>
</template>
