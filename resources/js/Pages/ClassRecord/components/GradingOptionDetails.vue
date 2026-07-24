<script setup>
import { computed } from 'vue'

const props = defineProps({
  option: { type: Object, default: null },
})

// Leaves carry the weight; parents are organizational groups.
const leaves = computed(() => {
  const cats = props.option?.categories ?? []
  const parentIds = new Set(cats.map(c => c.parent_id).filter(Boolean))
  const byId = Object.fromEntries(cats.map(c => [c.id, c]))
  return cats
    .filter(c => !parentIds.has(c.id))
    .map(c => ({
      ...c,
      parentName: c.parent_id && byId[c.parent_id] ? byId[c.parent_id].name : null,
    }))
    .sort((a, b) => a.sort_order - b.sort_order)
})

const weightTotal = computed(() =>
  Math.round(leaves.value.reduce((s, c) => s + Number(c.weight || 0), 0) * 100)
)

const quartersLabel = computed(() => {
  const aq = props.option?.applicable_quarters
  if (!aq || !aq.length) return 'All quarters'
  return 'Quarters ' + [...aq].sort().map(q => `Q${q}`).join(', ')
})
</script>

<template>
  <div v-if="option" class="rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3">
    <p v-if="option.description" class="text-xs text-slate-600 mb-1">{{ option.description }}</p>
    <p v-else class="text-xs text-slate-400 italic mb-1">No description provided for this grading option.</p>
    <p class="text-[11px] font-medium text-indigo-600 mb-3">Applies to: {{ quartersLabel }}</p>

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
        <tr v-for="cat in leaves" :key="cat.id ?? cat.name">
          <td class="py-1 pr-2 text-slate-700">
            <span v-if="cat.parentName" class="text-slate-400">{{ cat.parentName }} · </span>{{ cat.name }}
          </td>
          <td class="py-1 pr-2 text-slate-500">{{ cat.code }}</td>
          <td class="py-1 pr-2 text-slate-500">{{ Math.round(cat.weight * 100) }}%</td>
          <td class="py-1 text-slate-500">{{ cat.max_assessments }}</td>
        </tr>
      </tbody>
    </table>
    <p class="text-right text-[11px] text-slate-400 mt-1">Total weight: {{ weightTotal }}%</p>
  </div>
</template>
