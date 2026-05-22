<script setup>
defineProps({
  modelValue: { default: '' },
  label:      { type: String,  default: null },
  error:      { type: String,  default: null },
  required:   { type: Boolean, default: false },
  rows:       { type: Number,  default: 3 },
  placeholder:{ type: String,  default: null },
  disabled:   { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <div>
    <label v-if="label" class="block text-xs font-medium text-slate-600 mb-1">
      {{ label }} <span v-if="required" class="text-red-500">*</span>
    </label>
    <textarea
      :value="modelValue"
      :rows="rows"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      @input="$emit('update:modelValue', $event.target.value)"
      class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y transition-colors"
      :class="[
        error    ? 'border-red-400 bg-red-50/30' : 'border-slate-200 bg-white',
        disabled ? 'bg-slate-50 cursor-not-allowed text-slate-400' : '',
      ]"
    />
    <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
  </div>
</template>
