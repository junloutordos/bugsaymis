<script setup>
import { ref, watch, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import axios from 'axios'
import { auth, provider, signInWithPopup } from '@/firebase'
import { usePage } from '@inertiajs/vue3'

const OFFICIAL_DOMAIN = '@crc.pshs.edu.ph'
const isLoading = ref(false)

const showAlert = (options) => Swal.fire({ confirmButtonColor: '#6366f1', ...options })
const isAuthorizedEmail = (email) => email?.toLowerCase().endsWith(OFFICIAL_DOMAIN)

const page = usePage()
const appVersion = computed(() => page.props.appVersion?.current ?? '1.0.0')

watch(() => page.props.errors, (errs) => {
  if (!errs) return
  const emailErr = errs.email || (Array.isArray(errs.email) ? errs.email[0] : null)
  const msg = emailErr || (typeof errs === 'string' ? errs : null)
  if (!msg) return
  if (String(msg).includes('Unable to logged in')) {
    showAlert({ icon: 'error', title: 'Unable to logged in', text: 'Contact MIS administrator.' })
  } else {
    showAlert({ icon: 'error', title: 'Login Failed', text: String(msg) })
  }
}, { immediate: true })

/* ── Google Sign-In (unchanged) ── */
const googleLogin = async () => {
  if (isLoading.value) return
  try {
    isLoading.value = true
    const { user } = await signInWithPopup(auth, provider)
    if (!isAuthorizedEmail(user.email)) {
      showAlert({ icon: 'error', title: 'Unauthorized Email', text: 'Only official PSHS-CRC accounts are allowed.' })
      return
    }
    const { data } = await axios.post('/google/login', { email: user.email, name: user.displayName, uid: user.uid })
    if (!data?.success) throw new Error('Account verification failed')
    showAlert({ icon: 'success', title: 'Login Successful', text: `Welcome, ${user.displayName}! Redirecting…`, timer: 1500, showConfirmButton: false })
    window.location.href = data.redirect_to
  } catch (error) {
    showAlert({ icon: 'error', title: 'Login Failed', text: error.response?.data?.message || error.message })
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <Head title="Login" />

  <!-- ── Page background ── -->
  <div class="pg">
    <div class="card">

      <!-- ═══════════════════════════════════════════
           DARK LEFT PANEL — illustration
      ════════════════════════════════════════════ -->
      <div class="dp">

        <!-- Glow blobs (ambient light) -->
        <div class="glow g1"/><div class="glow g2"/><div class="glow g3"/>

        <!-- ── User–Server Network Visualization ── -->
        <div class="illus-wrap">
          <svg class="illus-svg" viewBox="0 0 580 420" fill="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <radialGradient id="hub-glow" cx="50%" cy="50%" r="50%">
                <stop offset="0%" stop-color="#00e5ff" stop-opacity="0.30"/>
                <stop offset="100%" stop-color="#00e5ff" stop-opacity="0"/>
              </radialGradient>
              <radialGradient id="node-bg" cx="40%" cy="35%" r="65%">
                <stop offset="0%" stop-color="#1e3a8a"/>
                <stop offset="100%" stop-color="#0d1b6e"/>
              </radialGradient>
              <linearGradient id="srv1" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stop-color="#1e3a8a"/>
                <stop offset="100%" stop-color="#1e4090"/>
              </linearGradient>
              <linearGradient id="srv2" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stop-color="#162060"/>
                <stop offset="100%" stop-color="#1a2878"/>
              </linearGradient>
              <path id="p1" d="M 82,90  C 148,150 215,190 290,210"/>
              <path id="p2" d="M 290,48 L 290,210"/>
              <path id="p3" d="M 498,90  C 432,150 365,190 290,210"/>
              <path id="p4" d="M 48,232 C 128,224 205,216 290,210"/>
              <path id="p5" d="M 532,232 C 452,224 372,216 290,210"/>
              <path id="p6" d="M 138,372 C 188,318 234,268 290,210"/>
              <path id="p7" d="M 442,372 C 392,318 346,268 290,210"/>
            </defs>
            <circle cx="290" cy="210" r="72"  stroke="#00e5ff" stroke-width="0.8" fill="none" opacity="0.12"/>
            <circle cx="290" cy="210" r="128" stroke="#00e5ff" stroke-width="0.6" fill="none" opacity="0.08"/>
            <circle cx="290" cy="210" r="185" stroke="#818cf8" stroke-width="0.5" fill="none" opacity="0.05"/>
            <circle cx="290" cy="210" r="72" stroke="#00e5ff" stroke-width="1.5" fill="none">
              <animate attributeName="r"       from="72"  to="185" dur="3.5s" repeatCount="indefinite"/>
              <animate attributeName="opacity" from="0.45" to="0"  dur="3.5s" repeatCount="indefinite"/>
            </circle>
            <circle cx="290" cy="210" r="72" stroke="#00e5ff" stroke-width="1.5" fill="none">
              <animate attributeName="r"       from="72"  to="185" dur="3.5s" begin="1.75s" repeatCount="indefinite"/>
              <animate attributeName="opacity" from="0.45" to="0"  dur="3.5s" begin="1.75s" repeatCount="indefinite"/>
            </circle>
            <path d="M 82,90  C 148,150 215,190 290,210" stroke="#4dd0e1" stroke-width="1.3" stroke-dasharray="6,5" opacity="0.5" class="fl fl1"/>
            <path d="M 290,48 L 290,210"                 stroke="#4dd0e1" stroke-width="1.3" stroke-dasharray="6,5" opacity="0.5" class="fl fl2"/>
            <path d="M 498,90  C 432,150 365,190 290,210" stroke="#4dd0e1" stroke-width="1.3" stroke-dasharray="6,5" opacity="0.5" class="fl fl3"/>
            <path d="M 48,232 C 128,224 205,216 290,210"  stroke="#818cf8" stroke-width="1.3" stroke-dasharray="6,5" opacity="0.5" class="fl fl4"/>
            <path d="M 532,232 C 452,224 372,216 290,210" stroke="#818cf8" stroke-width="1.3" stroke-dasharray="6,5" opacity="0.5" class="fl fl5"/>
            <path d="M 138,372 C 188,318 234,268 290,210" stroke="#a78bfa" stroke-width="1.3" stroke-dasharray="6,5" opacity="0.5" class="fl fl6"/>
            <path d="M 442,372 C 392,318 346,268 290,210" stroke="#a78bfa" stroke-width="1.3" stroke-dasharray="6,5" opacity="0.5" class="fl fl7"/>
            <circle r="3.5" fill="#00e5ff" opacity="0.95"><animateMotion dur="3.2s" repeatCount="indefinite"><mpath href="#p1"/></animateMotion></circle>
            <circle r="3.5" fill="#00e5ff" opacity="0.95"><animateMotion dur="2.4s" repeatCount="indefinite"><mpath href="#p2"/></animateMotion></circle>
            <circle r="3.5" fill="#00e5ff" opacity="0.95"><animateMotion dur="3.8s" repeatCount="indefinite"><mpath href="#p3"/></animateMotion></circle>
            <circle r="3.5" fill="#818cf8" opacity="0.95"><animateMotion dur="2.7s" repeatCount="indefinite"><mpath href="#p4"/></animateMotion></circle>
            <circle r="3.5" fill="#818cf8" opacity="0.95"><animateMotion dur="3.4s" repeatCount="indefinite"><mpath href="#p5"/></animateMotion></circle>
            <circle r="3.5" fill="#c084fc" opacity="0.95"><animateMotion dur="4.1s" repeatCount="indefinite"><mpath href="#p6"/></animateMotion></circle>
            <circle r="3.5" fill="#c084fc" opacity="0.95"><animateMotion dur="3.0s" repeatCount="indefinite"><mpath href="#p7"/></animateMotion></circle>
            <circle cx="290" cy="210" r="58" fill="url(#hub-glow)"/>
            <ellipse cx="290" cy="244" rx="40" ry="12" fill="#0f1e5c" stroke="#4dd0e1" stroke-width="1.2" opacity="0.6"/>
            <rect    x="250" y="222" width="80" height="22" fill="url(#srv1)" stroke="none"/>
            <line    x1="250" y1="222" x2="250" y2="244"   stroke="#4dd0e1" stroke-width="1.2"/>
            <line    x1="330" y1="222" x2="330" y2="244"   stroke="#4dd0e1" stroke-width="1.2"/>
            <ellipse cx="290" cy="222" rx="40" ry="12" fill="#1e4090" stroke="#4dd0e1" stroke-width="1.3"/>
            <circle cx="264" cy="233" r="2.2" fill="#34d399" class="led la"/>
            <circle cx="272" cy="233" r="2.2" fill="#34d399" class="led lb"/>
            <circle cx="280" cy="233" r="2.2" fill="#00e5ff"/>
            <rect x="291" y="229" width="22" height="7" rx="2" fill="#0d1b6e" stroke="#4dd0e1" stroke-width="0.8"/>
            <rect x="292" y="230" width="16" height="5" rx="1.5" fill="#34d399" opacity="0.75"/>
            <rect    x="250" y="200" width="80" height="22" fill="url(#srv2)" stroke="none"/>
            <line    x1="250" y1="200" x2="250" y2="222"   stroke="#818cf8" stroke-width="1.2"/>
            <line    x1="330" y1="200" x2="330" y2="222"   stroke="#818cf8" stroke-width="1.2"/>
            <ellipse cx="290" cy="200" rx="40" ry="12" fill="#1e3a8a" stroke="#818cf8" stroke-width="1.3"/>
            <circle cx="264" cy="211" r="2.2" fill="#818cf8" class="led lc"/>
            <circle cx="272" cy="211" r="2.2" fill="#00e5ff"/>
            <rect x="291" y="207" width="22" height="7" rx="2" fill="#0d1b6e" stroke="#818cf8" stroke-width="0.8"/>
            <rect x="292" y="208" width="10" height="5" rx="1.5" fill="#818cf8" opacity="0.8"/>
            <rect    x="250" y="178" width="80" height="22" fill="url(#srv1)" stroke="none"/>
            <line    x1="250" y1="178" x2="250" y2="200"   stroke="#4dd0e1" stroke-width="1.2"/>
            <line    x1="330" y1="178" x2="330" y2="200"   stroke="#4dd0e1" stroke-width="1.2"/>
            <ellipse cx="290" cy="178" rx="40" ry="12" fill="#1e4090" stroke="#4dd0e1" stroke-width="1.4"/>
            <circle cx="264" cy="189" r="2.2" fill="#34d399"/>
            <circle cx="272" cy="189" r="2.2" fill="#34d399" class="led la"/>
            <circle cx="280" cy="189" r="2.2" fill="#f59e0b" class="led lb"/>
            <rect x="291" y="185" width="22" height="7" rx="2" fill="#0d1b6e" stroke="#4dd0e1" stroke-width="0.8"/>
            <rect x="292" y="186" width="19" height="5" rx="1.5" fill="#00e5ff" opacity="0.7"/>
            <g class="unode un1">
              <circle cx="82" cy="90" r="26" fill="url(#node-bg)" stroke="#4dd0e1" stroke-width="1.6"/>
              <circle cx="82" cy="82" r="8"  fill="#4dd0e1" opacity="0.85"/>
              <path   d="M67,105 Q67,96 82,96 Q97,96 97,105" fill="#4dd0e1" opacity="0.85"/>
              <rect x="70" y="122" width="24" height="15" rx="2" stroke="#4dd0e1" stroke-width="1.1" fill="none" opacity="0.65"/>
              <line x1="82" y1="137" x2="82" y2="143" stroke="#4dd0e1" stroke-width="1.1" opacity="0.65"/>
              <line x1="77" y1="143" x2="87" y2="143" stroke="#4dd0e1" stroke-width="1.1" opacity="0.65"/>
              <text x="82" y="157" text-anchor="middle" font-size="8.5" fill="#94a3b8" font-family="Arial,sans-serif">Admin</text>
            </g>
            <g class="unode un2">
              <circle cx="290" cy="28" r="26" fill="url(#node-bg)" stroke="#4dd0e1" stroke-width="1.6"/>
              <circle cx="290" cy="20" r="8"  fill="#4dd0e1" opacity="0.85"/>
              <path   d="M275,43 Q275,34 290,34 Q305,34 305,43" fill="#4dd0e1" opacity="0.85"/>
              <text x="290" y="64" text-anchor="middle" font-size="8.5" fill="#94a3b8" font-family="Arial,sans-serif">HR Officer</text>
            </g>
            <g class="unode un3">
              <circle cx="498" cy="90" r="26" fill="url(#node-bg)" stroke="#4dd0e1" stroke-width="1.6"/>
              <circle cx="498" cy="82" r="8"  fill="#4dd0e1" opacity="0.85"/>
              <path   d="M483,105 Q483,96 498,96 Q513,96 513,105" fill="#4dd0e1" opacity="0.85"/>
              <path d="M485,124 L485,137 L511,137 L511,124 Z" stroke="#818cf8" stroke-width="1.1" fill="none" opacity="0.65"/>
              <path d="M481,137 L519,137"                       stroke="#818cf8" stroke-width="1.8"  opacity="0.65"/>
              <text x="498" y="151" text-anchor="middle" font-size="8.5" fill="#94a3b8" font-family="Arial,sans-serif">Faculty</text>
            </g>
            <g class="unode un4">
              <circle cx="48" cy="232" r="26" fill="url(#node-bg)" stroke="#818cf8" stroke-width="1.6"/>
              <circle cx="48" cy="224" r="8"  fill="#818cf8" opacity="0.85"/>
              <path   d="M33,247 Q33,238 48,238 Q63,238 63,247" fill="#818cf8" opacity="0.85"/>
              <rect x="40" y="254" width="16" height="26" rx="3" stroke="#818cf8" stroke-width="1.1" fill="none" opacity="0.65"/>
              <circle cx="48" cy="276" r="2"               stroke="#818cf8" stroke-width="1"   fill="none" opacity="0.65"/>
              <text x="48" y="293" text-anchor="middle" font-size="8.5" fill="#94a3b8" font-family="Arial,sans-serif">Staff</text>
            </g>
            <g class="unode un5">
              <circle cx="532" cy="232" r="26" fill="url(#node-bg)" stroke="#818cf8" stroke-width="1.6"/>
              <circle cx="532" cy="224" r="8"  fill="#818cf8" opacity="0.85"/>
              <path   d="M517,247 Q517,238 532,238 Q547,238 547,247" fill="#818cf8" opacity="0.85"/>
              <rect x="521" y="254" width="22" height="28" rx="3" stroke="#818cf8" stroke-width="1.1" fill="none" opacity="0.65"/>
              <line x1="522" y1="259" x2="542" y2="259"   stroke="#818cf8" stroke-width="0.8" opacity="0.45"/>
              <line x1="522" y1="264" x2="542" y2="264"   stroke="#818cf8" stroke-width="0.8" opacity="0.45"/>
              <text x="532" y="295" text-anchor="middle" font-size="8.5" fill="#94a3b8" font-family="Arial,sans-serif">Payroll</text>
            </g>
            <g class="unode un6">
              <circle cx="138" cy="372" r="26" fill="url(#node-bg)" stroke="#a78bfa" stroke-width="1.6"/>
              <circle cx="138" cy="364" r="8"  fill="#a78bfa" opacity="0.85"/>
              <path   d="M123,387 Q123,378 138,378 Q153,378 153,387" fill="#a78bfa" opacity="0.85"/>
              <text x="138" y="407" text-anchor="middle" font-size="8.5" fill="#94a3b8" font-family="Arial,sans-serif">Records</text>
            </g>
            <g class="unode un7">
              <circle cx="442" cy="372" r="26" fill="url(#node-bg)" stroke="#a78bfa" stroke-width="1.6"/>
              <circle cx="442" cy="364" r="8"  fill="#a78bfa" opacity="0.85"/>
              <path   d="M427,387 Q427,378 442,378 Q457,378 457,387" fill="#a78bfa" opacity="0.85"/>
              <text x="442" y="407" text-anchor="middle" font-size="8.5" fill="#94a3b8" font-family="Arial,sans-serif">MIS</text>
            </g>
            <g class="icard ic1">
              <rect x="378" y="42" width="124" height="42" rx="10" fill="rgba(10,18,75,0.88)" stroke="#4dd0e1" stroke-width="1"/>
              <circle cx="395" cy="63" r="5.5" fill="#34d399"/>
              <text x="406" y="57" font-size="7.5" fill="#a5b4fc" font-family="Arial,sans-serif">Active Users</text>
              <text x="406" y="72" font-size="13"  font-weight="bold" fill="#ffffff" font-family="Arial,sans-serif">24 Online</text>
            </g>
            <g class="icard ic2">
              <rect x="0" y="136" width="116" height="42" rx="10" fill="rgba(10,18,75,0.88)" stroke="#818cf8" stroke-width="1"/>
              <circle cx="15" cy="157" r="5.5" fill="#34d399" class="led la"/>
              <text x="26" y="151" font-size="7.5" fill="#a5b4fc" font-family="Arial,sans-serif">System Status</text>
              <text x="26" y="166" font-size="10"  font-weight="bold" fill="#34d399" font-family="Arial,sans-serif">All Systems OK</text>
            </g>
            <g class="icard ic3">
              <rect x="346" y="328" width="116" height="42" rx="10" fill="rgba(10,18,75,0.88)" stroke="#a78bfa" stroke-width="1"/>
              <path d="M360,348 C360,344 370,344 370,348 L370,357 L360,357 Z" stroke="#a78bfa" stroke-width="1.2" fill="none"/>
              <rect x="358" y="349" width="14" height="9" rx="2" stroke="#a78bfa" stroke-width="1.2" fill="none"/>
              <text x="376" y="349" font-size="7.5" fill="#a5b4fc" font-family="Arial,sans-serif">Connection</text>
              <text x="376" y="364" font-size="10"  font-weight="bold" fill="#a78bfa" font-family="Arial,sans-serif">Encrypted</text>
            </g>
            <g class="icard ic4">
              <rect x="136" y="42" width="100" height="42" rx="10" fill="rgba(10,18,75,0.88)" stroke="#34d399" stroke-width="1"/>
              <text x="150" y="57" font-size="7.5" fill="#a5b4fc" font-family="Arial,sans-serif">Uptime</text>
              <text x="150" y="74" font-size="14"  font-weight="bold" fill="#34d399" font-family="Arial,sans-serif">99.9%</text>
            </g>
          </svg>
        </div>

        <!-- Bottom brand -->
        <div class="dp-brand">
          <img src="/images/pshslogo.png" alt="PSHS-CRC" class="dp-logo"/>
          <div>
            <p class="dp-name">BUGSAY-MIS</p>
            <p class="dp-campus">v{{ appVersion }}</p>
          </div>
        </div>

      </div><!-- /dp -->

      <!-- ═══════════════════════════════════════════
           STRAIGHT DIVIDER
      ════════════════════════════════════════════ -->
      <div class="divider" aria-hidden="true"></div>

      <!-- ═══════════════════════════════════════════
           WHITE RIGHT PANEL — login form
      ════════════════════════════════════════════ -->
      <div class="wp">

        <!-- Logo -->
        <div class="logo-row">
          <img src="/images/pshslogo.png" alt="PSHS-CRC" class="logo-img" />
          <div class="logo-text">
            <span class="logo-name">BUGSAY-MIS</span>
            <span class="logo-sub">Philippine Science High School – Caraga Region Campus in Butuan City</span>
          </div>
        </div>

        <!-- Headline + form -->
        <div class="form-area">
          <h1 class="headline">Welcome back!</h1>
          <p class="sub">Access the PSHS-CRC centralized<br>management information system.</p>

          <!-- ↓ Google Sign-In — reuses existing googleLogin() handler ↓ -->
          <button @click="googleLogin" :disabled="isLoading" class="gbtn" aria-label="Continue with Google">
            <span class="g-icon-wrap">
              <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" class="g-icon" alt="" />
            </span>
            <span class="g-label">{{ isLoading ? 'Signing in…' : 'Continue with Google' }}</span>
            <svg v-if="isLoading" class="g-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"/>
            </svg>
          </button>

          <!-- Domain notice -->
          <p class="notice">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
            </svg>
            Only <strong>@crc.pshs.edu.ph</strong> accounts are allowed.
          </p>
        </div>

        <p class="wp-footer">&copy; 2026 PSHS-CRC &nbsp;·&nbsp; v{{ appVersion }}</p>
      </div>

    </div><!-- /card -->
  </div><!-- /pg -->
</template>

<style scoped>
/* ── Page background ────────────────────────────────────────── */
.pg {
  min-height: 100vh;
  display: flex;
}

/* ── Card container ─────────────────────────────────────────── */
.card {
  position: relative;
  width: 100%;
  height: 100vh;
  display: flex;
}

/* ══════════════════════════════════
   WHITE RIGHT PANEL
══════════════════════════════════ */
.wp {
  position: relative;
  z-index: 2;
  width: 38%;
  flex-shrink: 0;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  padding: 36px 44px;
}

/* Logo row */
.logo-row {
  display: flex;
  align-items: center;
  gap: 12px;
}
.logo-img  { width: 40px; height: 40px; flex-shrink: 0; }
.logo-name { font-size: .9rem; font-weight: 800; color: #0f172a; letter-spacing: .06em; line-height: 1.1; }
.logo-sub  { font-size: .6rem; color: #94a3b8; display: block; }

/* Main form area — vertically centered */
.form-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

/* Headline */
.headline {
  font-size: 3rem;
  font-weight: 900;
  color: #0f172a;
  line-height: 1.1;
  letter-spacing: -.02em;
  margin-bottom: 12px;
}
.sub {
  font-size: .825rem;
  color: #64748b;
  line-height: 1.65;
  margin-bottom: 32px;
}

/* ── Google Sign-In button ──────────────────────────────────── */
.gbtn {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 14px 20px;
  background: #ffffff;
  border: 1.5px solid #e2e8f0;
  border-radius: 14px;
  font-size: .875rem;
  font-weight: 600;
  color: #1e293b;
  cursor: pointer;
  transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
  text-align: left;
}
.gbtn:hover:not(:disabled) {
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 8px 24px rgba(99,102,241,.18);
  border-color: #a5b4fc;
}
.gbtn:active:not(:disabled) {
  transform: translateY(0) scale(.99);
  box-shadow: 0 2px 8px rgba(99,102,241,.12);
}
.gbtn:focus-visible {
  outline: 2px solid #6366f1;
  outline-offset: 3px;
}
.gbtn:disabled { opacity: .6; cursor: not-allowed; }

.g-icon-wrap {
  width: 36px; height: 36px;
  background: #f1f5f9;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.g-icon  { width: 20px; height: 20px; }
.g-label { flex: 1; }
.g-spin  { width: 16px; height: 16px; animation: spin .75s linear infinite; color: #6366f1; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Notice */
.notice {
  margin-top: 14px;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: .72rem;
  color: #94a3b8;
  line-height: 1.5;
}
.notice svg { width: 13px; height: 13px; flex-shrink: 0; color: #94a3b8; }

/* Footer */
.wp-footer {
  font-size: .65rem;
  color: #cbd5e1;
  text-align: center;
}

/* ══════════════════════════════════
   STRAIGHT DIVIDER
══════════════════════════════════ */
.divider {
  width: 1px;
  flex-shrink: 0;
  background: rgba(99, 102, 241, 0.25);
  z-index: 3;
}

/* ══════════════════════════════════
   DARK LEFT PANEL
══════════════════════════════════ */
.dp {
  flex: 1;
  position: relative;
  overflow: hidden;
  background: linear-gradient(145deg, #1a2070 0%, #0d1b6e 40%, #1a0a5e 100%);
  display: flex;
  flex-direction: column;
  z-index: 1;
}

/* Ambient glow blobs */
.glow {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}
.g1 { width: 420px; height: 420px; top: -100px; right: 5%;  background: radial-gradient(circle, rgba(99,102,241,.22) 0%, transparent 70%); }
.g2 { width: 300px; height: 300px; bottom: -80px; left: 10%; background: radial-gradient(circle, rgba(168,85,247,.18) 0%, transparent 70%); }
.g3 { width: 240px; height: 240px; top: 45%; left: 38%;    background: radial-gradient(circle, rgba(0,229,255,.12) 0%, transparent 70%); }

/* Top nav row */
.dp-nav {
  position: relative;
  z-index: 5;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 32px 0;
}
.dp-nav-title {
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .12em;
  color: rgba(165,180,252,.5);
  text-transform: uppercase;
}
.dp-nav-v { font-size: .65rem; color: rgba(100,116,139,.5); }

/* Central illustration */
.illus-wrap {
  position: relative;
  z-index: 4;
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 20px;
}
.illus-svg {
  width: 100%;
  max-width: 460px;
  max-height: 360px;
  animation: levitate 6s ease-in-out infinite;
}
@keyframes levitate {
  0%, 100% { transform: translateY(0); }
  50%       { transform: translateY(-14px); }
}

/* Bottom brand */
.dp-brand {
  position: relative;
  z-index: 5;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 32px 20px;
}
.dp-logo   { width: 36px; height: 36px; filter: brightness(1.1) drop-shadow(0 0 10px rgba(99,102,241,.5)); }
.dp-name   { font-size: .8rem; font-weight: 800; color: #e2e8f0; letter-spacing: .06em; line-height: 1.2; }
.dp-campus { font-size: .6rem; color: #64748b; margin-top: 1px; }

/* ══════════════════════════════════
   MOBILE
══════════════════════════════════ */
@media (max-width: 767px) {
  /* Full-screen dark background */
  .pg {
    background: linear-gradient(160deg, #1a2070 0%, #0d1b6e 45%, #1a0a5e 100%);
    align-items: center;
    justify-content: center;
    padding: 24px 20px;
  }

  .card {
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: auto;
    min-height: unset;
    width: 100%;
    background: transparent;
  }

  /* Dark panel hidden — its background is on .pg */
  .dp      { display: none; }
  .divider { display: none; }

  /* Ambient glow blobs behind the card — on .pg level */
  .pg::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
      radial-gradient(circle at 20% 20%, rgba(99,102,241,.28) 0%, transparent 50%),
      radial-gradient(circle at 80% 80%, rgba(168,85,247,.22) 0%, transparent 50%),
      radial-gradient(circle at 55% 50%, rgba(0,229,255,.10) 0%, transparent 40%);
    pointer-events: none;
    z-index: 0;
  }

  /* White form card — centered, floating */
  .wp {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 400px;
    padding: 36px 32px 32px;
    border-radius: 20px;
    box-shadow:
      0 24px 64px rgba(0, 0, 0, 0.45),
      0 0 0 1px rgba(255,255,255,0.06);
    background: #ffffff;
    min-height: unset;
    height: auto;
  }

  /* Remove vertical centering flex from form-area — natural flow */
  .form-area {
    flex: none;
    justify-content: flex-start;
    margin-top: 28px;
    margin-bottom: 28px;
  }

  .headline  { font-size: 2.1rem; }
  .sub       { margin-bottom: 24px; }
  .wp-footer { color: #94a3b8; }
}

/* ══════════════════════════════════
   FADE UP on load
══════════════════════════════════ */
.form-area { animation: fadeUp .5s ease-out both; }
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ══════════════════════════════════
   ILLUSTRATION ANIMATIONS
══════════════════════════════════ */

/* Flowing dashed connection lines */
.fl { animation: dash-flow 3s linear infinite; }
.fl1 { animation-duration: 3.2s; }
.fl2 { animation-duration: 2.4s; }
.fl3 { animation-duration: 3.8s; }
.fl4 { animation-duration: 2.7s; }
.fl5 { animation-duration: 3.4s; }
.fl6 { animation-duration: 4.1s; }
.fl7 { animation-duration: 3.0s; }
@keyframes dash-flow {
  from { stroke-dashoffset: 44; }
  to   { stroke-dashoffset: 0; }
}

/* LED indicator blink */
.led { animation: led-blink 1.5s ease-in-out infinite; }
.la  { animation-delay: 0s; }
.lb  { animation-delay: 0.5s; }
.lc  { animation-delay: 1s; }
@keyframes led-blink {
  0%, 100% { opacity: 0.9; }
  50%       { opacity: 0.2; }
}

/* User node subtle glow pulse */
.unode {
  animation: node-glow 4s ease-in-out infinite;
}
.un1 { animation-delay: 0s; }
.un2 { animation-delay: 0.6s; }
.un3 { animation-delay: 1.2s; }
.un4 { animation-delay: 1.8s; }
.un5 { animation-delay: 2.4s; }
.un6 { animation-delay: 3.0s; }
.un7 { animation-delay: 3.6s; }
@keyframes node-glow {
  0%, 100% { opacity: 0.88; }
  50%       { opacity: 1; filter: drop-shadow(0 0 6px rgba(77,208,225,0.45)); }
}

/* Floating info cards */
.icard {
  animation: card-float 5s ease-in-out infinite;
}
.ic1 { animation-delay: 0s; }
.ic2 { animation-delay: 1.25s; }
.ic3 { animation-delay: 2.5s; }
.ic4 { animation-delay: 3.75s; }
@keyframes card-float {
  0%, 100% { transform: translateY(0px); }
  50%       { transform: translateY(-5px); }
}
</style>
