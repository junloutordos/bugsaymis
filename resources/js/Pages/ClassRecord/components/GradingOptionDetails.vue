<script setup>
import { computed } from 'vue'

const props = defineProps({
  option: { type: Object, default: null },
})

const weightTotal = computed(() =>
  Math.round((props.option?.categories ?? []).reduce((s, c) => s + Number(c.weight || 0), 0) * 100)
)
</script>

<template>
  <div v-if="option" class="rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3">
    <p v-if="option.description" class="text-xs text-slate-600 mb-3">{{ option.description }}</p>
    <p v-else class="text-xs text-slate-400 italic mb-3">No description provided for this grading option.</p>

    <table class="w-full text-xs">
      <thead>
        <tr class="text-slate-400">
          <th class="text-left font-semibold pb-1 pr-2">Category</th>
          <th class="text-left font-semibold pb-1 pr-2 w-14">Code</th>
          <th class="text-left font-semibold pb-1 pr-2 w-16">Weight</th>
          <th class="text-left font-semibold pb-1 w-20">Max Items</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200/70">
        <tr v-for="cat in option.categories ?? []" :key="cat.id ?? cat.name">
          <td class="py-1 pr-2 text-slate-700">{{ cat.name }}</td>
          <td class="py-1 pr-2 text-slate-500">{{ cat.code }}</td>
          <td class="py-1 pr-2 text-slate-500">{{ Math.round(cat.weight * 100) }}%</td>
          <td class="py-1 text-slate-500">{{ cat.max_assessments }}</td>
        </tr>
      </tbody>
    </table>
    <p class="text-right text-[11px] text-slate-400 mt-1">Total weight: {{ weightTotal }}%</p>
  </div>
</template>
