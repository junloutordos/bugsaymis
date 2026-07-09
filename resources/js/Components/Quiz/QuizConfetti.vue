<script setup>
// Self-contained canvas confetti — no external libs (strict CSP + no deps).
import { ref, watch, onBeforeUnmount } from 'vue'

const props = defineProps({
  active: { type: Boolean, default: false },
})

const canvasEl = ref(null)
let rafId = null
let particles = []

const COLORS = ['#ef4444', '#2563eb', '#fbbf24', '#10b981', '#ffffff', '#38bdf8']

function spawn(w) {
  return {
    x: Math.random() * w,
    y: -20 - Math.random() * 120,
    size: 6 + Math.random() * 7,
    color: COLORS[Math.floor(Math.random() * COLORS.length)],
    vy: 2 + Math.random() * 3,
    vx: -1.5 + Math.random() * 3,
    rot: Math.random() * Math.PI,
    vr: -0.12 + Math.random() * 0.24,
  }
}

function loop() {
  const canvas = canvasEl.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  const { width: w, height: h } = canvas

  ctx.clearRect(0, 0, w, h)
  for (const p of particles) {
    p.y += p.vy
    p.x += p.vx
    p.rot += p.vr
    if (p.y > h + 30) Object.assign(p, spawn(w), { y: -20 })
    ctx.save()
    ctx.translate(p.x, p.y)
    ctx.rotate(p.rot)
    ctx.fillStyle = p.color
    ctx.fillRect(-p.size / 2, -p.size / 3, p.size, p.size * 0.66)
    ctx.restore()
  }
  rafId = requestAnimationFrame(loop)
}

function start() {
  const canvas = canvasEl.value
  if (!canvas) return
  canvas.width = window.innerWidth
  canvas.height = window.innerHeight
  particles = Array.from({ length: 140 }, () => spawn(canvas.width))
  cancelAnimationFrame(rafId)
  loop()
}

function stop() {
  cancelAnimationFrame(rafId)
  rafId = null
  const canvas = canvasEl.value
  canvas?.getContext('2d')?.clearRect(0, 0, canvas.width, canvas.height)
}

// flush: 'post' — the canvas ref must exist when active is true at mount time
watch(() => props.active, (on) => (on ? start() : stop()), { immediate: true, flush: 'post' })
onBeforeUnmount(stop)
</script>

<template>
  <canvas v-show="active" ref="canvasEl" class="fixed inset-0 z-50 pointer-events-none" />
</template>
