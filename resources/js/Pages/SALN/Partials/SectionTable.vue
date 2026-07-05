<template>
  <div class="space-y-4">
    <!-- Section header -->
    <div class="flex items-start justify-between gap-3">
      <div>
        <h3 class="text-sm font-semibold text-slate-800">{{ title }}</h3>
        <p v-if="description" class="text-xs text-slate-500 mt-0.5">{{ description }}</p>
      </div>
      <button v-if="canAdd" type="button" @click="$emit('add')"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors shrink-0">
        <PlusIcon class="h-3.5 w-3.5" />
        Add
      </button>
    </div>

    <!-- If a custom #table slot is provided, render it directly -->
    <slot name="table">
      <!-- Empty state (fallback when using items/columns props) -->
      <div v-if="!items || items.length === 0"
        class="border border-dashed border-slate-200 rounded-lg py-8 text-center">
        <component :is="emptyIcon ?? FolderOpenIcon" class="mx-auto h-8 w-8 text-slate-200 mb-2" />
        <p class="text-sm text-slate-400">{{ emptyText ?? 'No entries yet' }}</p>
        <button v-if="canAdd" type="button" @click="$emit('add')"
          class="mt-2 text-xs text-indigo-500 hover:text-indigo-700 font-medium">
          + Add entry
        </button>
      </div>

      <!-- Items-based table -->
      <div v-else class="overflow-x-auto rounded-lg border border-slate-100">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th v-for="col in columns" :key="col.key"
                class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide"
                :class="col.class">
                {{ col.label }}
              </th>
              <th v-if="canAdd" class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide w-20">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 bg-white">
            <tr v-for="(item, i) in items" :key="item.id ?? i" class="hover:bg-slate-50 transition-colors">
              <td v-for="col in columns" :key="col.key"
                class="px-4 py-3 text-slate-700"
                :class="col.tdClass">
                <slot :name="`cell-${col.key}`" :item="item" :value="item[col.key]">
                  {{ item[col.key] ?? '—' }}
                </slot>
              </td>
              <td v-if="canAdd" class="px-4 py-3 text-right">
                <div class="inline-flex gap-1">
                  <button type="button" @click="$emit('edit', item)"
                    class="p-1 rounded text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                    title="Edit">
                    <PencilIcon class="h-4 w-4" />
                  </button>
                  <button type="button" @click="$emit('delete', item)"
                    class="p-1 rounded text-slate-400 hover:text-danger-600 hover:bg-danger-50 transition-colors"
                    title="Delete">
                    <TrashIcon class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
          <!-- Footer slot (totals row) -->
          <tfoot v-if="$slots.footer">
            <slot name="footer" />
          </tfoot>
        </table>
      </div>
    </slot>

    <!-- Summary slot -->
    <slot name="summary" />
  </div>
</template>

<script setup>
import { PlusIcon, PencilIcon, TrashIcon, FolderOpenIcon } from '@heroicons/vue/24/outline'

defineProps({
  title: String,
  description: String,
  items: Array,
  columns: Array,  // [{ key, label, class, tdClass }]
  canAdd: { type: Boolean, default: true },
  emptyText: String,
  emptyIcon: Object,
})

defineEmits(['add', 'edit', 'delete'])
</script>
