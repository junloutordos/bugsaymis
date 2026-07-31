<script setup>
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

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
  <div class="space-y-5">
    <div v-for="(value, key) in modelValue" :key="key">
      <p class="mb-1.5 text-xs font-medium text-slate-600">{{ title(key) }}</p>
      <label
        v-if="typeof value === 'boolean'"
        :class="[
          'flex items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700 transition-colors',
          disabled ? 'cursor-not-allowed bg-slate-50 text-slate-400' : 'cursor-pointer bg-white hover:border-slate-300',
        ]"
      >
        <input
          type="checkbox"
          :checked="value"
          :disabled="disabled"
          class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
          @change="update(key, $event.target.checked)"
        />
        {{ value ? 'Yes / Included' : 'No / Not completed' }}
      </label>
      <div v-else-if="Array.isArray(value)" class="space-y-2">
        <div v-for="(item, index) in value" :key="index" class="flex gap-2 rounded-xl bg-slate-50/80 p-3 ring-1 ring-slate-200/70">
          <div v-if="item && typeof item === 'object'" class="grid min-w-0 flex-1 gap-3 sm:grid-cols-2">
            <AppTextarea
              v-for="(_, field) in item"
              :key="field"
              :model-value="item[field]"
              :label="title(field)"
              :disabled="disabled"
              :rows="2"
              @update:model-value="updateArrayItem(key, index, { ...item, [field]: $event })"
            />
          </div>
          <AppTextarea
            v-else
            :model-value="item"
            :disabled="disabled"
            :rows="2"
            class="min-w-0 flex-1"
            @update:model-value="updateArrayItem(key, index, $event)"
          />
          <AppIconButton v-if="!disabled" label="Remove entry" variant="danger" size="sm" @click="removeArrayItem(key, value, index)">
            <TrashIcon class="h-4 w-4" />
          </AppIconButton>
        </div>
        <AppButton v-if="!disabled" type="button" variant="ghost" size="sm" @click="addArrayItem(key, value)">
          <PlusIcon class="h-4 w-4" />
          Add {{ title(key).toLowerCase() }} entry
        </AppButton>
      </div>
      <AppTextarea
        v-else
        :model-value="value"
        :disabled="disabled"
        :rows="String(value || '').length > 80 ? 4 : 2"
        @update:model-value="update(key, $event)"
      />
    </div>
  </div>
</template>
