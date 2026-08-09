<script setup>
import { ref, watch, onMounted } from 'vue'
import renderMathInElement from 'katex/contrib/auto-render'
import 'katex/dist/katex.min.css'

const props = defineProps({ html: { type: String, default: '' } })
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
watch(() => props.html, () => render())
</script>

<template>
  <div ref="el" v-html="html" />
</template>
