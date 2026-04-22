<script setup>
defineProps({
  modelValue: { default: '' },
  label:      { type: String, default: null },
  type:       { type: String, default: 'text' },
  placeholder:{ type: String, default: '' },
  error:      { type: String, default: null },
  required:   { type: Boolean, default: false },
  disabled:   { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <div class="space-y-1">
    <label v-if="label" class="block text-xs font-medium text-slate-600">
      {{ label }}<span v-if="required" class="text-red-500 ml-0.5">*</span>
    </label>
    <input
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      @input="$emit('update:modelValue', $event.target.value)"
      :class="[
        'w-full rounded-lg border px-3 py-2 text-sm bg-white text-slate-800 placeholder-slate-400 transition-colors',
        'focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400',
        error
          ? 'border-red-400 focus:ring-red-400'
          : 'border-slate-200 hover:border-slate-300',
        disabled && 'bg-slate-50 cursor-not-allowed opacity-70',
      ]"
    />
    <p v-if="error" class="text-xs text-red-500">{{ error }}</p>
  </div>
</template>
