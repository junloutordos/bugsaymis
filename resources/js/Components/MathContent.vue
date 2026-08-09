<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import DOMPurify from 'dompurify'
import renderMathInElement from 'katex/contrib/auto-render'
import 'katex/dist/katex.min.css'

// Sanitizes internally so every caller is safe by default, even one that forgets to
// pre-sanitize — callers may still pass raw or already-sanitized HTML either way.
const props = defineProps({ html: { type: String, default: '' } })
const sanitized = computed(() => DOMPurify.sanitize(props.html || ''))
const el = ref(null)

function render() {
  if (! el.value) return
  renderMathInElement(el.value, {
    delimiters: [
      { left: '$$', right: '$$', display: true },
      { left: '$', right: '$', display: false },
    ],
    throwOnError: false,
  })
}

onMounted(render)
watch(sanitized, () => render())
</script>

<template>
  <div ref="el" v-html="sanitized" />
</template>
