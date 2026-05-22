<script setup>
import { watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import TextAlign from '@tiptap/extension-text-align'
import { TextStyle, FontSize } from '@tiptap/extension-text-style'
import Color from '@tiptap/extension-color'
import Highlight from '@tiptap/extension-highlight'
import { Table, TableRow, TableCell, TableHeader } from '@tiptap/extension-table'

// ── Props / emits ─────────────────────────────────────────────────────────────
const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Type the body of the issuance here…' },
})
const emit = defineEmits(['update:modelValue'])

// ── Editor setup ──────────────────────────────────────────────────────────────
const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({
      heading: { levels: [1, 2, 3] },
    }),
    Underline,
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    TextStyle,
    FontSize,
    Color,
    Highlight.configure({ multicolor: true }),
    Table.configure({ resizable: false }),
    TableRow,
    TableHeader,
    TableCell,
  ],
  editorProps: {
    attributes: {
      class: 'rich-editor-content',
      spellcheck: 'true',
    },
  },
  onUpdate({ editor }) {
    emit('update:modelValue', editor.getHTML())
  },
})

// Keep editor in sync if parent resets the value (e.g., template load)
watch(() => props.modelValue, (val) => {
  if (editor.value && editor.value.getHTML() !== val) {
    editor.value.commands.setContent(val || '', false)
  }
})

onBeforeUnmount(() => {
  editor.value?.destroy()
})

// ── Toolbar helpers ───────────────────────────────────────────────────────────
const FONT_SIZES = ['10px', '12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px', '36px']
const TEXT_COLORS = [
  '#000000', '#1e293b', '#334155', '#64748b', '#94a3b8',
  '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0891b2',
  '#2563eb', '#7c3aed', '#db2777', '#ffffff',
]
const HIGHLIGHT_COLORS = [
  '#fef08a', '#bbf7d0', '#bae6fd', '#e9d5ff', '#fecaca',
  '#fed7aa', '#fbcfe8', '#ccfbf1',
]

function insertTable() {
  editor.value?.chain().focus()
    .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
    .run()
}

function setFontSize(size) {
  editor.value?.chain().focus().setFontSize(size).run()
}

function setColor(color) {
  editor.value?.chain().focus().setColor(color).run()
}

function setHighlight(color) {
  editor.value?.chain().focus().setHighlight({ color }).run()
}
</script>

<template>
  <div class="rich-editor-wrapper border border-slate-200 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500">

    <!-- ── Toolbar ──────────────────────────────────────────────────────────── -->
    <div v-if="editor" class="toolbar bg-slate-50 border-b border-slate-200 px-2 py-1.5 flex flex-wrap gap-0.5 items-center">

      <!-- History -->
      <div class="flex items-center gap-0.5 pr-2 mr-1 border-r border-slate-200">
        <button type="button" @click="editor.chain().focus().undo().run()"
          :disabled="!editor.can().undo()"
          class="tb-btn" title="Undo (Ctrl+Z)">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v6h6"/><path d="M3 13a9 9 0 1 0 3-7.7L3 7"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().redo().run()"
          :disabled="!editor.can().redo()"
          class="tb-btn" title="Redo (Ctrl+Y)">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 7v6h-6"/><path d="M21 13a9 9 0 1 1-3-7.7L21 7"/></svg>
        </button>
      </div>

      <!-- Block type -->
      <div class="pr-2 mr-1 border-r border-slate-200">
        <select @change="e => {
          const v = e.target.value
          if (v === 'paragraph') editor.chain().focus().setParagraph().run()
          else editor.chain().focus().toggleHeading({ level: parseInt(v) }).run()
          e.target.value = v
        }"
          class="tb-select"
          :value="editor.isActive('heading', {level:1}) ? '1' : editor.isActive('heading', {level:2}) ? '2' : editor.isActive('heading', {level:3}) ? '3' : 'paragraph'"
        >
          <option value="paragraph">Normal</option>
          <option value="1">Heading 1</option>
          <option value="2">Heading 2</option>
          <option value="3">Heading 3</option>
        </select>
      </div>

      <!-- Font size -->
      <div class="pr-2 mr-1 border-r border-slate-200">
        <select @change="e => { setFontSize(e.target.value); }" class="tb-select" title="Font size">
          <option value="" disabled selected>Size</option>
          <option v-for="s in FONT_SIZES" :key="s" :value="s">{{ s.replace('px','') }}px</option>
        </select>
      </div>

      <!-- Format -->
      <div class="flex items-center gap-0.5 pr-2 mr-1 border-r border-slate-200">
        <button type="button" @click="editor.chain().focus().toggleBold().run()"
          :class="['tb-btn', editor.isActive('bold') ? 'active' : '']" title="Bold (Ctrl+B)">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M6 4h8a4 4 0 0 1 0 8H6V4zm0 8h9a4 4 0 0 1 0 8H6v-8z"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().toggleItalic().run()"
          :class="['tb-btn', editor.isActive('italic') ? 'active' : '']" title="Italic (Ctrl+I)">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().toggleUnderline().run()"
          :class="['tb-btn', editor.isActive('underline') ? 'active' : '']" title="Underline (Ctrl+U)">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3v7a6 6 0 0 0 12 0V3"/><line x1="4" y1="21" x2="20" y2="21"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().toggleStrike().run()"
          :class="['tb-btn', editor.isActive('strike') ? 'active' : '']" title="Strikethrough">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="12" x2="20" y2="12"/><path d="M16.5 7C16.5 5.34 14.69 4 12 4S7.5 5.34 7.5 7c0 1.47 1.13 2.67 3 3.2"/><path d="M7.5 17c0 1.66 1.81 3 4.5 3s4.5-1.34 4.5-3c0-1.47-1.13-2.67-3-3.2"/></svg>
        </button>
      </div>

      <!-- Text color -->
      <div class="flex items-center gap-0.5 pr-2 mr-1 border-r border-slate-200">
        <div class="relative group">
          <button type="button" class="tb-btn flex-col gap-0 h-7" title="Text color">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3h6l5 18H4L9 3z"/><line x1="4" y1="15" x2="20" y2="15"/></svg>
            <span class="block w-4 h-1.5 rounded-sm" :style="{ background: editor.getAttributes('textStyle').color || '#000' }"></span>
          </button>
          <div class="color-dropdown hidden group-hover:flex group-focus-within:flex absolute left-0 top-full mt-1 z-20 bg-white border border-slate-200 rounded-lg shadow-lg p-2 flex-wrap gap-1 w-[140px]">
            <button v-for="c in TEXT_COLORS" :key="c" type="button"
              @click="setColor(c)"
              class="w-5 h-5 rounded border border-slate-200 hover:scale-110 transition-transform"
              :style="{ background: c }" :title="c" />
            <button type="button" @click="editor.chain().focus().unsetColor().run()"
              class="w-full mt-1 text-[10px] text-slate-500 hover:text-slate-800 text-center">Remove color</button>
          </div>
        </div>

        <!-- Highlight -->
        <div class="relative group">
          <button type="button" class="tb-btn flex-col gap-0 h-7" title="Highlight">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3L5 21"/><path d="M15 3l4 18"/><path d="M3 12h18"/><path d="M5 21h14"/></svg>
            <span class="block w-4 h-1.5 rounded-sm" :style="{ background: editor.getAttributes('highlight').color || '#fef08a' }"></span>
          </button>
          <div class="color-dropdown hidden group-hover:flex group-focus-within:flex absolute left-0 top-full mt-1 z-20 bg-white border border-slate-200 rounded-lg shadow-lg p-2 flex-wrap gap-1 w-[104px]">
            <button v-for="c in HIGHLIGHT_COLORS" :key="c" type="button"
              @click="setHighlight(c)"
              class="w-5 h-5 rounded border border-slate-200 hover:scale-110 transition-transform"
              :style="{ background: c }" :title="c" />
            <button type="button" @click="editor.chain().focus().unsetHighlight().run()"
              class="w-full mt-1 text-[10px] text-slate-500 hover:text-slate-800 text-center">Remove</button>
          </div>
        </div>
      </div>

      <!-- Alignment -->
      <div class="flex items-center gap-0.5 pr-2 mr-1 border-r border-slate-200">
        <button type="button" @click="editor.chain().focus().setTextAlign('left').run()"
          :class="['tb-btn', editor.isActive({ textAlign: 'left' }) ? 'active' : '']" title="Align left">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().setTextAlign('center').run()"
          :class="['tb-btn', editor.isActive({ textAlign: 'center' }) ? 'active' : '']" title="Align center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().setTextAlign('right').run()"
          :class="['tb-btn', editor.isActive({ textAlign: 'right' }) ? 'active' : '']" title="Align right">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="6" y1="18" x2="21" y2="18"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().setTextAlign('justify').run()"
          :class="['tb-btn', editor.isActive({ textAlign: 'justify' }) ? 'active' : '']" title="Justify">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
      </div>

      <!-- Lists -->
      <div class="flex items-center gap-0.5 pr-2 mr-1 border-r border-slate-200">
        <button type="button" @click="editor.chain().focus().toggleBulletList().run()"
          :class="['tb-btn', editor.isActive('bulletList') ? 'active' : '']" title="Bullet list">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="9" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="9" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1.5" fill="currentColor" stroke="none"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().toggleOrderedList().run()"
          :class="['tb-btn', editor.isActive('orderedList') ? 'active' : '']" title="Numbered list">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1.5"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().toggleBlockquote().run()"
          :class="['tb-btn', editor.isActive('blockquote') ? 'active' : '']" title="Blockquote">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().setHorizontalRule().run()"
          class="tb-btn" title="Horizontal rule">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/></svg>
        </button>
      </div>

      <!-- Indent -->
      <div class="flex items-center gap-0.5 pr-2 mr-1 border-r border-slate-200">
        <button type="button" @click="editor.chain().focus().sinkListItem('listItem').run()"
          :disabled="!editor.can().sinkListItem('listItem')"
          class="tb-btn" title="Indent (Tab)">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/><path d="M3 12l5 3V9l-5 3z" fill="currentColor" stroke="none"/><line x1="11" y1="12" x2="21" y2="12"/></svg>
        </button>
        <button type="button" @click="editor.chain().focus().liftListItem('listItem').run()"
          :disabled="!editor.can().liftListItem('listItem')"
          class="tb-btn" title="Outdent (Shift+Tab)">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/><path d="M11 9l-5 3 5 3V9z" fill="currentColor" stroke="none"/><line x1="13" y1="12" x2="21" y2="12"/></svg>
        </button>
      </div>

      <!-- Table -->
      <div class="flex items-center gap-0.5">
        <div class="relative group">
          <button type="button"
            :class="['tb-btn', editor.isActive('table') ? 'active' : '']"
            title="Table tools">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
          </button>
          <div class="hidden group-hover:block group-focus-within:block absolute left-0 top-full mt-1 z-20 bg-white border border-slate-200 rounded-lg shadow-lg py-1 min-w-[160px]">
            <button type="button" @click="insertTable()" class="table-menu-item">Insert Table (3×3)</button>
            <hr class="my-1 border-slate-100" />
            <button type="button" @click="editor.chain().focus().addColumnBefore().run()" class="table-menu-item">Add column before</button>
            <button type="button" @click="editor.chain().focus().addColumnAfter().run()" class="table-menu-item">Add column after</button>
            <button type="button" @click="editor.chain().focus().deleteColumn().run()" class="table-menu-item text-red-600">Delete column</button>
            <hr class="my-1 border-slate-100" />
            <button type="button" @click="editor.chain().focus().addRowBefore().run()" class="table-menu-item">Add row before</button>
            <button type="button" @click="editor.chain().focus().addRowAfter().run()" class="table-menu-item">Add row after</button>
            <button type="button" @click="editor.chain().focus().deleteRow().run()" class="table-menu-item text-red-600">Delete row</button>
            <hr class="my-1 border-slate-100" />
            <button type="button" @click="editor.chain().focus().deleteTable().run()" class="table-menu-item text-red-600">Delete table</button>
          </div>
        </div>
      </div>

    </div>

    <!-- ── Editor area ──────────────────────────────────────────────────────── -->
    <EditorContent :editor="editor" class="min-h-[320px]" />

    <!-- ── Word count footer ───────────────────────────────────────────────── -->
    <div v-if="editor" class="px-3 py-1.5 bg-slate-50 border-t border-slate-100 flex justify-end">
      <span class="text-[11px] text-slate-400">
        {{ editor.storage.characterCount?.characters?.() ?? editor.getText().length }} characters ·
        {{ editor.getText().trim().split(/\s+/).filter(Boolean).length }} words
      </span>
    </div>

  </div>
</template>

<style>
/* ── Editor content styles ────────────────────────────────────────────────── */
.rich-editor-content {
  padding: 16px;
  min-height: 320px;
  outline: none;
  font-size: 14px;
  line-height: 1.7;
  color: #1e293b;
}

.rich-editor-content h1 { font-size: 1.75em; font-weight: 700; margin: 0.6em 0 0.4em; }
.rich-editor-content h2 { font-size: 1.35em; font-weight: 700; margin: 0.6em 0 0.4em; }
.rich-editor-content h3 { font-size: 1.15em; font-weight: 600; margin: 0.5em 0 0.3em; }

.rich-editor-content p { margin: 0 0 0.5em; }
.rich-editor-content p:last-child { margin-bottom: 0; }

.rich-editor-content strong { font-weight: 700; }
.rich-editor-content em     { font-style: italic; }
.rich-editor-content u      { text-decoration: underline; }
.rich-editor-content s      { text-decoration: line-through; }

.rich-editor-content ul,
.rich-editor-content ol {
  padding-left: 1.5em;
  margin: 0.4em 0;
}
.rich-editor-content ul { list-style-type: disc; }
.rich-editor-content ol { list-style-type: decimal; }
.rich-editor-content li { margin: 0.2em 0; }

.rich-editor-content blockquote {
  border-left: 3px solid #cbd5e1;
  padding-left: 12px;
  color: #475569;
  margin: 0.5em 0;
  font-style: italic;
}

.rich-editor-content hr {
  border: none;
  border-top: 1px solid #e2e8f0;
  margin: 1em 0;
}

/* Tables */
.rich-editor-content table {
  width: 100%;
  border-collapse: collapse;
  margin: 0.75em 0;
  font-size: 0.9em;
}
.rich-editor-content th,
.rich-editor-content td {
  border: 1px solid #cbd5e1;
  padding: 6px 10px;
  vertical-align: top;
  text-align: left;
}
.rich-editor-content th {
  background: #f1f5f9;
  font-weight: 600;
}
.rich-editor-content .selectedCell {
  background: #e0f2fe !important;
}

/* ── Toolbar shared styles ─────────────────────────────────────────────────── */
.tb-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 5px;
  color: #475569;
  transition: background 0.1s, color 0.1s;
  border: none;
  background: transparent;
  cursor: pointer;
  flex-shrink: 0;
}
.tb-btn:hover:not(:disabled) {
  background: #e2e8f0;
  color: #1e293b;
}
.tb-btn.active {
  background: #e0e7ff;
  color: #4f46e5;
}
.tb-btn:disabled {
  opacity: 0.35;
  cursor: default;
}

.tb-select {
  font-size: 12px;
  padding: 3px 6px;
  border: 1px solid #e2e8f0;
  border-radius: 5px;
  background: white;
  color: #334155;
  outline: none;
  cursor: pointer;
  height: 28px;
}
.tb-select:hover {
  border-color: #94a3b8;
}

.color-dropdown {
  flex-wrap: wrap;
}

.table-menu-item {
  display: block;
  width: 100%;
  text-align: left;
  padding: 5px 12px;
  font-size: 12px;
  color: #334155;
  background: transparent;
  border: none;
  cursor: pointer;
  white-space: nowrap;
}
.table-menu-item:hover {
  background: #f1f5f9;
}
</style>
