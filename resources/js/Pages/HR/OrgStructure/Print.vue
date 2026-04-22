<script setup>
import { Head } from '@inertiajs/vue3'
import { onMounted } from 'vue'

const props = defineProps({
  tree:        { type: Array,  default: () => [] },
  generatedAt: { type: String, default: '' },
})

onMounted(() => {
  window.print()
})

const TYPE_COLORS = {
  institution: '#ede9fe',
  division:    '#dbeafe',
  department:  '#cffafe',
  section:     '#d1fae5',
  unit:        '#d1fae5',
  office:      '#fef3c7',
  committee:   '#fce7f3',
}

const TYPE_TEXT = {
  institution: '#5b21b6',
  division:    '#1d4ed8',
  department:  '#0e7490',
  section:     '#065f46',
  unit:        '#065f46',
  office:      '#92400e',
  committee:   '#9d174d',
}

function nodePadding(depth) {
  return `${depth * 14}px`
}
</script>

<template>
  <Head title="Org Structure — Print" />

  <div class="print-root">
    <!-- Header -->
    <div class="print-header">
      <h1>Organizational Structure</h1>
      <p>Generated {{ generatedAt }}</p>
    </div>

    <!-- Recursive tree -->
    <div class="print-tree">
      <template v-for="root in tree" :key="root.id">
        <PrintNode :node="root" :depth="0" :type-colors="TYPE_COLORS" :type-text="TYPE_TEXT" />
      </template>
    </div>

    <!-- Footer -->
    <div class="print-footer">
      Printed from BUGSAYMIS &bull; {{ generatedAt }}
    </div>
  </div>
</template>

<!-- Inline recursive component -->
<script>
const PrintNode = {
  name: 'PrintNode',
  props: {
    node:       { type: Object, required: true },
    depth:      { type: Number, default: 0 },
    typeColors: { type: Object, default: () => ({}) },
    typeText:   { type: Object, default: () => ({}) },
  },
  template: `
    <div class="pn-node">
      <div class="pn-row" :style="{ paddingLeft: depth * 14 + 'px' }">
        <span class="pn-badge" :style="{ background: typeColors[node.type] || '#f1f5f9', color: typeText[node.type] || '#475569' }">
          {{ node.type }}
        </span>
        <span class="pn-name">{{ node.name }}</span>
        <span v-if="node.short_name" class="pn-short">({{ node.short_name }})</span>
        <span class="pn-code">{{ node.code }}</span>
        <span v-if="node.current_head && node.current_head[0]" class="pn-head">
          {{ node.current_head[0].user?.name }}
        </span>
      </div>
      <template v-if="node.children && node.children.length">
        <PrintNode
          v-for="child in node.children"
          :key="child.id"
          :node="child"
          :depth="depth + 1"
          :type-colors="typeColors"
          :type-text="typeText"
        />
      </template>
    </div>
  `,
}
</script>

<style>
/* Force no layout shell for print pages */
#app > * { all: unset; }

.print-root {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 9pt;
  color: #1e293b;
  padding: 20px 24px;
  max-width: 900px;
  margin: 0 auto;
}

.print-header {
  border-bottom: 2px solid #4f46e5;
  padding-bottom: 10px;
  margin-bottom: 16px;
  text-align: center;
}
.print-header h1 {
  font-size: 15pt;
  font-weight: 700;
  color: #1e1b4b;
  letter-spacing: 0.3pt;
}
.print-header p {
  font-size: 8pt;
  color: #64748b;
  margin-top: 3px;
}

.pn-node { margin-bottom: 1px; }

.pn-row {
  display: flex;
  align-items: baseline;
  gap: 6px;
  padding: 3px 4px;
  border-radius: 3px;
}

.pn-badge {
  font-size: 6.5pt;
  font-weight: 700;
  text-transform: uppercase;
  padding: 1px 4px;
  border-radius: 2px;
  white-space: nowrap;
  flex-shrink: 0;
}

.pn-name { font-size: 9pt; font-weight: 600; flex: 1; }
.pn-short { font-size: 8pt; color: #64748b; }
.pn-code  { font-size: 7.5pt; color: #94a3b8; font-family: monospace; flex-shrink: 0; }
.pn-head  { font-size: 7.5pt; color: #64748b; font-style: italic; flex-shrink: 0; }

.print-footer {
  margin-top: 16px;
  border-top: 1px solid #e2e8f0;
  padding-top: 6px;
  text-align: right;
  font-size: 7.5pt;
  color: #94a3b8;
}

@media print {
  @page { margin: 15mm 12mm; }
  .print-root { padding: 0; }
  .pn-row { break-inside: avoid; }
}

@media screen {
  body { background: #f8fafc; }
  .print-root {
    background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-top: 24px;
    border-radius: 8px;
  }
}
</style>
