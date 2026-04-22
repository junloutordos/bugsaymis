<script setup>
import { ref } from 'vue'
import {
  ChevronRightIcon,
  ChevronDownIcon,
  PlusIcon,
  PencilSquareIcon,
  TrashIcon,
  BuildingOfficeIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  node:  { type: Object, required: true },
  can:   { type: Object, required: true },
  depth: { type: Number, default: 0 },
})

const emit = defineEmits(['edit', 'delete', 'add-child', 'select'])

const open = ref(props.depth < 2)   // auto-expand first two levels

const TYPE_COLORS = {
  institution: 'bg-violet-100 text-violet-700',
  division:    'bg-blue-100 text-blue-700',
  department:  'bg-cyan-100 text-cyan-700',
  section:     'bg-teal-100 text-teal-700',
  unit:        'bg-emerald-100 text-emerald-700',
  office:      'bg-amber-100 text-amber-700',
  committee:   'bg-pink-100 text-pink-700',
}

function typeColor(type) {
  return TYPE_COLORS[type] ?? 'bg-slate-100 text-slate-600'
}

const hasChildren = props.node.children?.length > 0
</script>

<template>
  <div :class="['select-none', depth > 0 ? 'ml-5 border-l border-slate-200' : '']">
    <!-- Node row -->
    <div
      :class="[
        'flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-50 group cursor-pointer',
        node.is_active ? '' : 'opacity-50',
      ]"
      @click="emit('select', node)"
    >
      <!-- Expand/collapse toggle -->
      <button
        v-if="hasChildren"
        @click.stop="open = !open"
        class="shrink-0 w-5 h-5 flex items-center justify-center rounded hover:bg-slate-200 text-slate-400"
      >
        <ChevronDownIcon v-if="open" class="h-3.5 w-3.5" />
        <ChevronRightIcon v-else class="h-3.5 w-3.5" />
      </button>
      <span v-else class="shrink-0 w-5 h-5 flex items-center justify-center">
        <BuildingOfficeIcon class="h-3.5 w-3.5 text-slate-300" />
      </span>

      <!-- Type badge -->
      <span :class="['shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded uppercase tracking-wide', typeColor(node.type)]">
        {{ node.type }}
      </span>

      <!-- Name -->
      <div class="flex-1 min-w-0">
        <span class="text-sm font-medium text-slate-800 truncate">{{ node.name }}</span>
        <span v-if="node.short_name" class="ml-1.5 text-xs text-slate-400">({{ node.short_name }})</span>
      </div>

      <!-- Head badge -->
      <span
        v-if="node.current_head?.length"
        class="hidden sm:inline shrink-0 text-[10px] text-slate-400 truncate max-w-[120px]"
        :title="node.current_head[0]?.user?.name"
      >
        {{ node.current_head[0]?.user?.name }}
      </span>

      <!-- Code -->
      <span class="hidden md:inline shrink-0 text-xs font-mono text-slate-400">{{ node.code }}</span>

      <!-- Action buttons (visible on hover) -->
      <div class="shrink-0 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
        <button
          v-if="can.create"
          @click.stop="emit('add-child', node)"
          class="p-1 rounded hover:bg-indigo-50 text-slate-400 hover:text-indigo-600"
          title="Add child unit"
        >
          <PlusIcon class="h-3.5 w-3.5" />
        </button>
        <button
          v-if="can.update"
          @click.stop="emit('edit', node)"
          class="p-1 rounded hover:bg-indigo-50 text-slate-400 hover:text-indigo-600"
          title="Edit"
        >
          <PencilSquareIcon class="h-3.5 w-3.5" />
        </button>
        <button
          v-if="can.delete"
          @click.stop="emit('delete', node)"
          class="p-1 rounded hover:bg-red-50 text-slate-400 hover:text-red-500"
          title="Delete"
        >
          <TrashIcon class="h-3.5 w-3.5" />
        </button>
      </div>
    </div>

    <!-- Children (recursive) -->
    <div v-if="hasChildren && open">
      <OrgTreeNode
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :can="can"
        :depth="depth + 1"
        @edit="emit('edit', $event)"
        @delete="emit('delete', $event)"
        @add-child="emit('add-child', $event)"
        @select="emit('select', $event)"
      />
    </div>
  </div>
</template>
