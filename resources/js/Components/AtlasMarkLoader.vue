<template>
  <div class="atlas-loader" :class="size === 'lg' ? 'atlas-loader-lg' : ''">
    <svg viewBox="0 0 187 240" class="atlas-loader-svg" aria-hidden="true">
      <defs>
        <linearGradient :id="`${uid}-fill`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#FE9203" />
          <stop offset="50%" stop-color="#FFA908" />
          <stop offset="100%" stop-color="#FEBD0D" />
        </linearGradient>
        <linearGradient :id="`${uid}-spark`" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#FFD54F" />
          <stop offset="100%" stop-color="#FF8F00" />
        </linearGradient>
      </defs>

      <!-- Soft glow copy behind the mark, breathing with the heartbeat -->
      <path :d="MARK_PATH" class="atlas-loader-glow" :fill="`url(#${uid}-fill)`" />

      <!-- Gradient-filled mark, softly beating -->
      <path :d="MARK_PATH" class="atlas-loader-fill" :fill="`url(#${uid}-fill)`" />

      <!-- Faint static outline so the shape always reads -->
      <path :d="MARK_PATH" pathLength="100" class="atlas-loader-outline" />

      <!-- Bright segment orbiting the outline -->
      <path :d="MARK_PATH" pathLength="100" class="atlas-loader-orbit" :stroke="`url(#${uid}-spark)`" />
    </svg>
    <p v-if="caption" class="atlas-loader-caption">{{ caption }}<span class="atlas-loader-dots" /></p>
  </div>
</template>

<script setup>
import { useId } from 'vue'

defineProps({
  /** Caption under the mark (animated ellipsis appended). Omit for mark only. */
  caption: { type: String, default: '' },
  size: { type: String, default: 'md' }, // 'md' | 'lg'
})

const uid = `atlas-loader-${useId()}`

/** Atlas mark outline, traced from public/images/atlas-mark.png (187×240). */
const MARK_PATH = 'M89.0 5.6 L95.0 5.1 L98.9 6.6 L101.7 10.0 L103.6 14.0 L106.1 22.0 L179.9 218.0 L181.1 222.0 L181.1 226.0 L180.6 228.0 L177.9 231.7 L174.0 233.9 L166.0 235.0 L162.0 234.4 L158.0 232.9 L156.0 231.6 L150.0 226.9 L132.0 207.0 L118.0 193.6 L112.0 189.0 L104.0 184.3 L100.0 182.9 L94.0 182.0 L88.0 182.3 L82.0 184.0 L76.0 187.1 L68.0 192.7 L62.0 198.0 L44.0 216.0 L35.0 226.0 L27.0 232.4 L23.0 234.1 L19.0 234.9 L13.0 234.1 L9.0 232.4 L7.1 230.9 L4.7 227.0 L4.4 223.0 L7.1 215.0 L77.9 27.0 L83.4 11.0 L86.1 7.3 Z'
</script>

<style>
.atlas-loader {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.875rem;
}

.atlas-loader-svg {
  width: 3.5rem;
  height: 4.5rem;
  overflow: visible;
}

.atlas-loader-lg .atlas-loader-svg {
  width: 4.5rem;
  height: 5.75rem;
}

.atlas-loader-glow,
.atlas-loader-fill {
  transform-origin: 50% 55%;
  animation: atlas-loader-beat 1.6s ease-in-out infinite;
}

.atlas-loader-glow {
  filter: blur(10px);
  opacity: 0.45;
  animation-name: atlas-loader-beat, atlas-loader-breathe;
  animation-duration: 1.6s, 1.6s;
}

.atlas-loader-outline {
  fill: none;
  stroke: #f8b133;
  stroke-width: 4;
  stroke-linecap: round;
  opacity: 0.25;
}

.atlas-loader-orbit {
  fill: none;
  stroke-width: 7;
  stroke-linecap: round;
  stroke-dasharray: 26 74;
  filter: drop-shadow(0 0 6px rgba(255, 160, 20, 0.9));
  animation: atlas-loader-orbit 1.9s linear infinite;
}

/* lub-dub heartbeat */
@keyframes atlas-loader-beat {
  0%, 60%, 100% { transform: scale(1); }
  12% { transform: scale(1.055); }
  24% { transform: scale(1.005); }
  36% { transform: scale(1.04); }
}

@keyframes atlas-loader-breathe {
  0%, 60%, 100% { opacity: 0.35; }
  12% { opacity: 0.65; }
  36% { opacity: 0.55; }
}

@keyframes atlas-loader-orbit {
  to { stroke-dashoffset: -100; }
}

.atlas-loader-caption {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #64748b;
}

.atlas-loader-dots::after {
  content: '';
  animation: atlas-loader-dots 1.6s steps(4, end) infinite;
}

@keyframes atlas-loader-dots {
  0% { content: ''; }
  25% { content: '.'; }
  50% { content: '..'; }
  75% { content: '...'; }
}

@media (prefers-reduced-motion: reduce) {
  .atlas-loader-glow,
  .atlas-loader-fill,
  .atlas-loader-dots::after {
    animation: none;
  }
  .atlas-loader-fill { animation: atlas-loader-breathe 2s ease-in-out infinite; }
  .atlas-loader-orbit { display: none; }
}
</style>
