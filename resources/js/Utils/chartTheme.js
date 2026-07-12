// Atlas premium chart theme — single source of chart styling.
// Importing this module applies brand-wide Chart.js defaults (idempotent);
// pages keep their vue-chartjs components and only pass semantic overrides.
import {
  Chart,
  CategoryScale, LinearScale, RadialLinearScale,
  BarElement, LineElement, PointElement, ArcElement,
  BarController, LineController, DoughnutController, RadarController,
  Legend, Tooltip, Filler,
} from 'chart.js'

// Scale/element/dataset defaults only exist after their type is registered,
// and this module runs before each page's ChartJS.register(...) call — so
// register everything the theme styles here first. Re-registration by pages
// is a no-op and does not reset the themed defaults (verified).
Chart.register(
  CategoryScale, LinearScale, RadialLinearScale,
  BarElement, LineElement, PointElement, ArcElement,
  BarController, LineController, DoughnutController, RadarController,
  Legend, Tooltip, Filler,
)

// Brand anchor (Atlas wordmark blue = indigo-600 in tailwind.config.js)
export const BRAND = '#0867DB'

// Categorical palette — fixed slot order, never cycled. Validated (light/white
// surface): lightness band, chroma floor, CVD worst-adjacent ΔE 21.7, all ≥3:1.
export const CHART_COLORS = ['#0867DB', '#D97706', '#0284C7', '#E11D48', '#0D9488', '#7C3AED']

// Fold-over bucket for series beyond the palette — never invent a 7th hue.
export const OTHER = '#94A3B8'

// Real status only (thresholds, health states) — never generic series colors.
export const STATUS = { success: '#10B981', warning: '#F59E0B', danger: '#EF4444' }

export function seriesColor(i) {
  return i < CHART_COLORS.length ? CHART_COLORS[i] : OTHER
}

export function withAlpha(hex, alpha) {
  const n = parseInt(hex.slice(1), 16)
  return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`
}

// Scriptable backgroundColor: soft vertical gradient under a filled line.
export function gradientFill(color, from = 0.18, to = 0.01) {
  return (context) => {
    const { ctx, chartArea } = context.chart
    if (!chartArea) return withAlpha(color, from)
    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom)
    gradient.addColorStop(0, withAlpha(color, from))
    gradient.addColorStop(1, withAlpha(color, to))
    return gradient
  }
}

const FONT_FAMILY = "'Inter', 'Figtree', system-ui, -apple-system, 'Segoe UI', sans-serif"

export function applyChartTheme() {
  const d = Chart.defaults

  d.responsive = true
  d.maintainAspectRatio = false
  d.font.family = FONT_FAMILY
  d.font.size = 11
  d.color = '#64748b' // slate-500 default ink

  d.animation.duration = 400
  d.animation.easing = 'easeOutQuart'

  // Recessive grid & axes
  d.scale.grid.color = '#f1f5f9' // slate-100 hairline
  d.scale.border.display = false
  d.scale.ticks.color = '#94a3b8' // slate-400
  // no gridlines along the category axis; quiet radar chrome
  d.set('scales.category', { grid: { display: false } })
  d.set('scales.radialLinear', {
    grid: { color: '#e2e8f0' },
    angleLines: { color: '#e2e8f0' },
    pointLabels: { color: '#64748b' },
  })

  // Legend: small circular chips, quiet labels
  const legendLabels = d.plugins.legend.labels
  legendLabels.usePointStyle = true
  legendLabels.pointStyle = 'circle'
  legendLabels.boxWidth = 8
  legendLabels.boxHeight = 8
  legendLabels.padding = 14
  legendLabels.color = '#64748b'

  // Premium tooltip
  Object.assign(d.plugins.tooltip, {
    backgroundColor: '#0f172a', // slate-900
    titleColor: '#f8fafc',
    bodyColor: '#cbd5e1',
    padding: { x: 12, y: 10 },
    cornerRadius: 8,
    caretSize: 5,
    boxPadding: 4,
    usePointStyle: true,
    boxWidth: 8,
    boxHeight: 8,
    titleFont: { family: FONT_FAMILY, size: 12, weight: '600' },
    bodyFont: { family: FONT_FAMILY, size: 11 },
  })

  // Marks
  d.elements.bar.borderRadius = 4
  d.datasets.bar.maxBarThickness = 32
  d.elements.line.borderWidth = 2
  d.elements.line.tension = 0.35
  d.elements.line.borderCapStyle = 'round'
  d.elements.line.borderJoinStyle = 'round'
  d.elements.point.radius = 0
  d.elements.point.hoverRadius = 4
  d.elements.point.hoverBorderWidth = 2
  d.elements.point.hoverBorderColor = '#ffffff'
  d.elements.point.hitRadius = 8
  d.elements.arc.borderWidth = 2
  d.elements.arc.borderColor = '#ffffff' // spacer gap between slices
  d.elements.arc.hoverOffset = 4
  d.datasets.doughnut.cutout = '68%'
}

applyChartTheme()
