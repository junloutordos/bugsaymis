<template>
  <div>
    <label v-if="label" class="block text-xs font-medium text-slate-600 mb-1">
      {{ label }}<span v-if="required" class="text-danger-500 ml-0.5">*</span>
    </label>

    <!-- Textarea -->
    <textarea v-if="type === 'textarea'"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      :rows="rows ?? 3"
      :placeholder="placeholder"
      :disabled="disabled"
      class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 disabled:bg-slate-50"
      :class="error ? 'border-danger-500' : 'border-slate-200'" />

    <!-- Select -->
    <select v-else-if="type === 'select'"
      :value="modelValue"
      @change="$emit('update:modelValue', $event.target.value)"
      :disabled="disabled"
      class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 disabled:bg-slate-50"
      :class="error ? 'border-danger-500' : 'border-slate-200'">
      <option v-if="placeholder" value="">{{ placeholder }}</option>
      <slot />
    </select>

    <!-- Default: input -->
    <input v-else
      :type="type ?? 'text'"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      :placeholder="placeholder"
      :min="min"
      :max="max"
      :step="step"
      :maxlength="maxlength"
      :disabled="disabled"
      class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 disabled:bg-slate-50"
      :class="error ? 'border-danger-500' : 'border-slate-200'" />

    <p v-if="error" class="text-xs text-danger-500 mt-1">{{ error }}</p>
    <p v-else-if="hint" class="text-[11px] text-slate-400 mt-1">{{ hint }}</p>
  </div>
</template>

<script setup>
defineProps({
  modelValue: [String, Number],
  label: String,
  type: { type: String, default: 'text' },
  placeholder: String,
  required: Boolean,
  disabled: Boolean,
  error: String,
  hint: String,
  rows: Number,
  min: [String, Number],
  max: [String, Number],
  step: [String, Number],
  maxlength: [String, Number],
})
defineEmits(['update:modelValue'])
</script>
