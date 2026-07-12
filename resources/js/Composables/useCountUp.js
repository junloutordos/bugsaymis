import { ref, watch, onMounted } from 'vue'

/**
 * Animated count-up for KPI/stat tile values (rAF, no deps).
 *
 * const { values } = useCountUp(() => cards.value.map(c => c.value))
 * Template: {{ values[i] ?? card.value }}
 *
 * Only pure-numeric targets (numbers or numeric strings) animate; anything
 * else ('—', '93%', '₱1,234.56') passes through untouched. Decimal places of
 * the target are preserved per frame. Targets are watched, so Inertia partial
 * reloads re-animate from the currently displayed value to the new one.
 */
export function useCountUp(getTargets, { duration = 700 } = {}) {
  const values = ref([])
  let frame = null

  const numeric = (v) =>
    (typeof v === 'number' && Number.isFinite(v)) ||
    (typeof v === 'string' && v.trim() !== '' && Number.isFinite(Number(v)))

  const decimalsOf = (v) => {
    const s = String(v)
    const i = s.indexOf('.')
    return i === -1 ? 0 : Math.min(s.length - i - 1, 4)
  }

  function animate(targets, from) {
    if (frame) cancelAnimationFrame(frame)

    const snap = typeof window === 'undefined'
      || window.matchMedia('(prefers-reduced-motion: reduce)').matches

    if (snap) {
      values.value = [...targets]
      return
    }

    const plan = targets.map((target, i) => {
      if (!numeric(target)) return { literal: target }
      const to = Number(target)
      const start = numeric(from[i]) ? Number(from[i]) : 0
      return { to, start, decimals: decimalsOf(target) }
    })

    const t0 = performance.now()
    const tick = (now) => {
      const t = Math.min((now - t0) / duration, 1)
      const ease = 1 - Math.pow(1 - t, 3)
      values.value = plan.map(p => {
        if ('literal' in p) return p.literal
        const v = p.start + (p.to - p.start) * ease
        const factor = 10 ** p.decimals
        return Math.round(v * factor) / factor
      })
      if (t < 1) frame = requestAnimationFrame(tick)
    }
    frame = requestAnimationFrame(tick)
  }

  onMounted(() => animate(getTargets() ?? [], []))

  watch(getTargets, (targets, old) => {
    // Ignore the pre-mount initial state; only re-animate real changes.
    if (JSON.stringify(targets) === JSON.stringify(old)) return
    animate(targets ?? [], values.value)
  })

  return { values }
}
