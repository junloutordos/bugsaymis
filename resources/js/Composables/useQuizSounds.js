import { ref } from 'vue'

// All audio is synthesized with WebAudio — no asset files, nothing external
// (CSP-safe). Shared module state so every caller sees the same mute flag.
const muted = ref(typeof localStorage !== 'undefined' && localStorage.getItem('quiz_muted') === '1')

let ctx = null
let lobbyTimer = null

function ensureCtx() {
  if (!ctx) ctx = new (window.AudioContext || window.webkitAudioContext)()
  // Browsers suspend the context until a user gesture; resume is a no-op after.
  if (ctx.state === 'suspended') ctx.resume()
  return ctx
}

function tone(freq, { start = 0, duration = 0.15, type = 'sine', gain = 0.15, slideTo = null } = {}) {
  const c = ensureCtx()
  const t0 = c.currentTime + start
  const osc = c.createOscillator()
  const g = c.createGain()
  osc.type = type
  osc.frequency.setValueAtTime(freq, t0)
  if (slideTo) osc.frequency.exponentialRampToValueAtTime(slideTo, t0 + duration)
  g.gain.setValueAtTime(0.0001, t0)
  g.gain.linearRampToValueAtTime(gain, t0 + 0.015)
  g.gain.exponentialRampToValueAtTime(0.0001, t0 + duration)
  osc.connect(g)
  g.connect(c.destination)
  osc.start(t0)
  osc.stop(t0 + duration + 0.05)
}

const SOUNDS = {
  // A player joined the lobby — soft two-note blip
  join: () => {
    tone(660, { duration: 0.09, type: 'triangle', gain: 0.1 })
    tone(880, { start: 0.07, duration: 0.12, type: 'triangle', gain: 0.1 })
  },
  // Question goes live — rising three-note sting
  start: () => {
    [523, 659, 784].forEach((f, i) => tone(f, { start: i * 0.11, duration: 0.16, type: 'triangle', gain: 0.16 }))
  },
  // Get-ready countdown beat
  countdown: () => tone(740, { duration: 0.1, type: 'triangle', gain: 0.12 }),
  // Last-seconds clock tick
  tick: () => tone(1050, { duration: 0.05, type: 'square', gain: 0.05 }),
  // Player locked an answer in
  lockin: () => tone(420, { duration: 0.09, type: 'square', gain: 0.08, slideTo: 620 }),
  // Correct — major arpeggio
  correct: () => {
    [523, 659, 784, 1047].forEach((f, i) => tone(f, { start: i * 0.08, duration: 0.2, type: 'triangle', gain: 0.16 }))
  },
  // Incorrect — descending buzz
  incorrect: () => {
    tone(290, { duration: 0.22, type: 'sawtooth', gain: 0.09, slideTo: 190 })
    tone(150, { start: 0.16, duration: 0.3, type: 'sawtooth', gain: 0.07 })
  },
  // Reveal / leaderboard whoosh
  reveal: () => tone(480, { duration: 0.32, type: 'sine', gain: 0.11, slideTo: 940 }),
  // Podium fanfare
  fanfare: () => {
    const seq = [
      [523, 0, 0.15], [523, 0.16, 0.15], [523, 0.32, 0.15],
      [659, 0.48, 0.2], [784, 0.7, 0.2], [1047, 0.92, 0.5],
    ]
    seq.forEach(([f, s, d]) => tone(f, { start: s, duration: d, type: 'triangle', gain: 0.17 }))
    seq.forEach(([f, s, d]) => tone(f / 2, { start: s, duration: d, type: 'sine', gain: 0.09 }))
  },
}

// Gentle four-note lobby arpeggio on a loop — atmosphere while players join.
const LOBBY_NOTES = [392, 494, 587, 494]

export function useQuizSounds() {
  function play(name) {
    if (muted.value || !SOUNDS[name]) return
    try { SOUNDS[name]() } catch { /* audio unavailable — never break gameplay */ }
  }

  function startLobbyLoop() {
    if (lobbyTimer) return
    let step = 0
    lobbyTimer = setInterval(() => {
      if (muted.value) return
      try {
        tone(LOBBY_NOTES[step % LOBBY_NOTES.length], { duration: 0.45, type: 'sine', gain: 0.05 })
        step++
      } catch { /* ignore */ }
    }, 600)
  }

  function stopLobbyLoop() {
    clearInterval(lobbyTimer)
    lobbyTimer = null
  }

  function toggleMute() {
    muted.value = !muted.value
    localStorage.setItem('quiz_muted', muted.value ? '1' : '0')
  }

  return { muted, toggleMute, play, startLobbyLoop, stopLobbyLoop }
}
