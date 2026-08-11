<script setup>
import { CheckIcon } from '@heroicons/vue/24/solid'

defineProps({
  steps: { type: Array, required: true }, // [{ key, label, complete }]
  percent: { type: Number, required: true },
})
</script>

<template>
  <div>
    <p class="mb-3 text-xs font-medium text-slate-500">{{ percent }}% set up</p>
    <div class="mb-4 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
      <div class="h-full rounded-full bg-indigo-600 transition-all" :style="{ width: percent + '%' }" />
    </div>
    <ol class="flex flex-wrap gap-x-6 gap-y-2">
      <li v-for="step in steps" :key="step.key" class="flex items-center gap-2 text-sm">
        <span :class="['flex h-5 w-5 shrink-0 items-center justify-center rounded-full', step.complete ? 'bg-success-500 text-white' : 'border border-slate-300']">
          <CheckIcon v-if="step.complete" class="h-3 w-3" />
        </span>
        <span :class="step.complete ? 'text-slate-700' : 'text-slate-400'">{{ step.label }}</span>
      </li>
    </ol>
  </div>
</template>
