<script setup>
defineProps({
  tabs: { type: Array, required: true }, // [{ key, label, icon? }]
  modelValue: { type: String, required: true },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <div>
    <div class="border-b border-slate-200 overflow-x-auto">
      <nav class="flex gap-1 -mb-px min-w-max" role="tablist">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          role="tab"
          :aria-selected="modelValue === tab.key"
          :class="[
            'flex items-center gap-1.5 px-3 py-2.5 text-sm font-medium border-b-2 transition-colors',
            modelValue === tab.key
              ? 'border-indigo-600 text-indigo-600'
              : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
          ]"
          @click="$emit('update:modelValue', tab.key)"
        >
          <component :is="tab.icon" v-if="tab.icon" class="h-4 w-4" />
          {{ tab.label }}
        </button>
      </nav>
    </div>
    <div class="pt-4">
      <slot :name="`tab-${modelValue}`">
        <slot />
      </slot>
    </div>
  </div>
</template>
