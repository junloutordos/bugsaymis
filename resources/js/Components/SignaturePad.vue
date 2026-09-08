<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
  label: { type: String, default: 'Signature' },
  height: { type: Number, default: 140 },
})

const canvasEl = ref(null)
const isEmpty = ref(true)
let ctx = null
let drawing = false
let last = { x: 0, y: 0 }

function resizeCanvas() {
  const canvas = canvasEl.value
  if (!canvas) return
  const ratio = window.devicePixelRatio || 1
  const rect = canvas.getBoundingClientRect()
  canvas.width = rect.width * ratio
  canvas.height = props.height * ratio
  ctx = canvas.getContext('2d')
  ctx.scale(ratio, ratio)
  ctx.lineWidth = 2
  ctx.lineCap = 'round'
  ctx.strokeStyle = '#111827'
}

function pointerPos(e) {
  const rect = canvasEl.value.getBoundingClientRect()
  const point = e.touches ? e.touches[0] : e
  return { x: point.clientX - rect.left, y: point.clientY - rect.top }
}

function start(e) {
  e.preventDefault()
  drawing = true
  isEmpty.value = false
  last = pointerPos(e)
}

function move(e) {
  if (!drawing) return
  e.preventDefault()
  const p = pointerPos(e)
  ctx.beginPath()
  ctx.moveTo(last.x, last.y)
  ctx.lineTo(p.x, p.y)
  ctx.stroke()
  last = p
}

function end() {
  drawing = false
}

function clear() {
  const canvas = canvasEl.value
  if (!canvas || !ctx) return
  ctx.clearRect(0, 0, canvas.width, canvas.height)
  isEmpty.value = true
}

function getDataUrl() {
  if (isEmpty.value || !canvasEl.value) return ''
  return canvasEl.value.toDataURL('image/png')
}

onMounted(() => {
  resizeCanvas()
  window.addEventListener('resize', resizeCanvas)
})

defineExpose({ clear, getDataUrl, isEmpty })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-1">
      <label class="block text-xs font-medium text-slate-600">{{ label }}</label>
      <button type="button" @click="clear" class="text-xs text-indigo-600 hover:text-indigo-800">Clear</button>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white overflow-hidden">
      <canvas
        ref="canvasEl"
        :style="{ height: height + 'px', width: '100%', touchAction: 'none', cursor: 'crosshair' }"
        @mousedown="start"
        @mousemove="move"
        @mouseup="end"
        @mouseleave="end"
        @touchstart="start"
        @touchmove="move"
        @touchend="end"
      />
    </div>
    <p class="mt-1 text-xs text-slate-400">Sign above using your mouse, finger, or stylus.</p>
  </div>
</template>
