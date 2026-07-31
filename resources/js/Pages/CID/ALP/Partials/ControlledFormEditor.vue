<script setup>
const props = defineProps({ modelValue: { type: Object, default: () => ({}) }, disabled: Boolean })
const emit = defineEmits(['update:modelValue'])

const title = (key) => String(key).replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase())
const update = (key, value) => emit('update:modelValue', { ...props.modelValue, [key]: value })
const updateArrayItem = (key, index, value) => {
  const items = [...props.modelValue[key]]
  items[index] = value
  update(key, items)
}
const addArrayItem = (key, items) => update(key, [...items, items.length && typeof items[0] === 'object' ? Object.fromEntries(Object.keys(items[0]).map(field => [field, ''])) : ''])
const removeArrayItem = (key, items, index) => update(key, items.filter((_, itemIndex) => itemIndex !== index))
</script>

<template>
  <div class="space-y-4">
    <div v-for="(value, key) in modelValue" :key="key">
      <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ title(key) }}</label>
      <label v-if="typeof value === 'boolean'" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <input type="checkbox" :checked="value" :disabled="disabled" @change="update(key, $event.target.checked)" />
        {{ value ? 'Yes / Included' : 'No / Not completed' }}
      </label>
      <div v-else-if="Array.isArray(value)" class="space-y-2">
        <div v-for="(item, index) in value" :key="index" class="flex gap-2 rounded-lg border border-slate-200 p-2">
          <div v-if="item && typeof item === 'object'" class="grid flex-1 gap-2 sm:grid-cols-2">
            <label v-for="(_, field) in item" :key="field" class="text-xs text-slate-500">{{ title(field) }}
              <textarea :value="item[field]" :disabled="disabled" rows="2" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-sm text-slate-800" @input="updateArrayItem(key, index, { ...item, [field]: $event.target.value })" />
            </label>
          </div>
          <textarea v-else :value="item" :disabled="disabled" rows="2" class="flex-1 rounded border border-slate-200 px-2 py-1 text-sm" @input="updateArrayItem(key, index, $event.target.value)" />
          <button v-if="!disabled" type="button" class="self-start px-1 text-rose-600" @click="removeArrayItem(key, value, index)">×</button>
        </div>
        <button v-if="!disabled" type="button" class="text-sm font-medium text-indigo-600" @click="addArrayItem(key, value)">+ Add {{ title(key).toLowerCase() }} entry</button>
      </div>
      <textarea v-else :value="value" :disabled="disabled" :rows="String(value || '').length > 80 ? 4 : 2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" @input="update(key, $event.target.value)" />
    </div>
  </div>
</template>
