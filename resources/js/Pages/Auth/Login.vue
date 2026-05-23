<script setup>
import { ref, watch, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import axios from 'axios'
import { auth, provider, signInWithPopup } from '@/firebase'
import { usePage } from '@inertiajs/vue3'
import {
  ShieldCheckIcon, BoltIcon, UsersIcon,
} from '@heroicons/vue/24/outline'

const OFFICIAL_DOMAIN = '@crc.pshs.edu.ph'
const isLoading = ref(false)

const showAlert = (opts) => Swal.fire({ confirmButtonColor: '#1447c0', ...opts })
const isAuthorizedEmail = (email) => email?.toLowerCase().endsWith(OFFICIAL_DOMAIN)

const page       = usePage()
const appVersion = computed(() => page.props.appVersion?.current ?? '1.0.0')

watch(() => page.props.errors, (errs) => {
  if (!errs) return
  const emailErr = errs.email || (Array.isArray(errs.email) ? errs.email[0] : null)
  const msg = emailErr || (typeof errs === 'string' ? errs : null)
  if (!msg) return
  if (String(msg).includes('Unable to logged in')) {
    showAlert({ icon: 'error', title: 'Unable to log in', text: 'Contact MIS administrator.' })
  } else {
    showAlert({ icon: 'error', title: 'Login Failed', text: String(msg) })
  }
}, { immediate: true })

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

const stats = [
  { value: '12+',   label: 'Modules' },
  { value: '500+',  label: 'Users' },
  { value: '99.9%', label: 'Uptime' },
]

const trust = [
  { icon: ShieldCheckIcon, label: 'Google SSO' },
  { icon: BoltIcon,        label: 'Real-time' },
  { icon: UsersIcon,       label: 'Role-based' },
]

</script>

<template>
  <Head title="CRCMIS — Campus Management Information System" />

  <div class="site">

    <!-- ╔══════════════════════════════════════════
         ║  HERO — SPLIT SCREEN
         ╚═════════════════════════════════════════ -->
    <section class="hero">

      <!-- LEFT PANEL — Navy gradient -->
      <div class="panel-left">
        <div class="panel-left-inner">

          <!-- School seal -->
          <img src="/images/pshslogo.png" alt="PSHS-CRC" class="seal" />

          <!-- Brand -->
          <div class="brand">
            <h2 class="brand-sys">Campus Resource &amp; Management</h2>
            <h2 class="brand-sys">Information System</h2>
            <p class="brand-school">Philippine Science High School</p>
            <p class="brand-campus">Caraga Region Campus · Butuan City</p>
          </div>

          <!-- Divider -->
          <div class="divider" />

          <!-- Stats -->
          <div class="stats-row">
            <div v-for="s in stats" :key="s.label" class="stat">
              <span class="stat-val">{{ s.value }}</span>
              <span class="stat-lbl">{{ s.label }}</span>
            </div>
          </div>

        </div>
      </div>

      <!-- RIGHT PANEL — Pure white -->
      <div class="panel-right">
        <div class="panel-right-inner">

          <!-- System acronym -->
          <p class="right-eyebrow">Official Portal</p>
          <h1 class="right-title">CRCMIS</h1>
          <p class="right-sub">Sign in to access your account</p>

          <!-- Google sign-in button -->
          <button @click="googleLogin" :disabled="isLoading" class="gbtn">
            <span class="g-icon-wrap">
              <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" class="g-icon" alt="Google" />
            </span>
            <span class="g-label">
              {{ isLoading ? 'Signing in…' : 'Continue with Google' }}
            </span>
            <svg v-if="isLoading" class="g-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"/>
            </svg>
          </button>

          <!-- Domain restriction notice -->
          <p class="domain-notice">
            Only <strong>@crc.pshs.edu.ph</strong> accounts are authorized
          </p>

          <!-- Trust badges -->
          <div class="trust-row">
            <div v-for="t in trust" :key="t.label" class="trust-item">
              <component :is="t.icon" class="trust-icon" />
              <span>{{ t.label }}</span>
            </div>
          </div>

          <!-- Copyright -->
          <p class="copyright">© 2026 PSHS-CRC · v{{ appVersion }}</p>

        </div>
      </div>

    </section>

  </div>
</template>

<style scoped>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.site {
  font-family: 'Inter', system-ui, Arial, sans-serif;
  color: #0f172a;
  background: #fff;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* ══════════════════════════════
   HERO — SPLIT SCREEN
══════════════════════════════ */
.hero {
  flex: 1;
  display: flex;
  min-height: 100vh;
}

/* ── LEFT PANEL ── */
.panel-left {
  width: 58%;
  background: linear-gradient(155deg, #060e50 0%, #0c1a7a 30%, #1447c0 65%, #1a5cd8 100%);
  display: flex; align-items: center; justify-content: center;
  padding: 60px 56px;
  position: relative;
  overflow: hidden;
}

/* Subtle texture line top-right */
.panel-left::before {
  content: '';
  position: absolute; top: 0; right: 0;
  width: 1px; height: 100%;
  background: linear-gradient(to bottom, transparent, rgba(255,255,255,.12) 20%, rgba(255,255,255,.12) 80%, transparent);
}

.panel-left-inner {
  max-width: 420px; width: 100%;
  display: flex; flex-direction: column; gap: 28px;
}

.seal {
  width: 88px; height: 88px;
  filter: drop-shadow(0 4px 24px rgba(0,147,184,.25));
}

.brand {}
.brand-sys {
  font-size: 1.6rem; font-weight: 800; color: #fff;
  line-height: 1.2; letter-spacing: -.02em;
}
.brand-school {
  font-size: .82rem; color: #93c5fd;
  margin-top: 10px; font-weight: 500;
}
.brand-campus {
  font-size: .72rem; color: #60a5fa; margin-top: 2px;
}

.divider {
  height: 1px;
  background: linear-gradient(to right, rgba(255,255,255,.2), transparent);
}

.stats-row {
  display: flex; gap: 32px;
}
.stat { display: flex; flex-direction: column; gap: 3px; }
.stat-val {
  font-size: 1.6rem; font-weight: 900; color: #fff;
  line-height: 1; letter-spacing: -.03em;
}
.stat-lbl {
  font-size: .62rem; font-weight: 600; color: #7dd3fc;
  text-transform: uppercase; letter-spacing: .1em;
}

/* ── RIGHT PANEL ── */
.panel-right {
  width: 42%;
  background: #ffffff;
  display: flex; align-items: center; justify-content: center;
  padding: 60px 48px;
}

.panel-right-inner {
  max-width: 340px; width: 100%;
  display: flex; flex-direction: column;
}

.right-eyebrow {
  font-size: .65rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .14em; color: #1447c0; margin-bottom: 6px;
}

.right-title {
  font-size: 3.2rem; font-weight: 900; color: #060e50;
  letter-spacing: -.05em; line-height: 1; margin-bottom: 6px;
}

.right-sub {
  font-size: .82rem; color: #64748b; margin-bottom: 32px;
}

/* Google button */
.gbtn {
  display: flex; align-items: center; gap: 12px; width: 100%;
  padding: 14px 18px;
  background: #060e50;
  border: none; border-radius: 12px;
  font-size: .875rem; font-weight: 600; color: #fff;
  cursor: pointer;
  transition: background .2s, transform .15s, box-shadow .15s;
  box-shadow: 0 4px 16px rgba(6,14,80,.25);
  margin-bottom: 16px;
}
.gbtn:hover:not(:disabled) {
  background: #0d1a80;
  transform: translateY(-1px);
  box-shadow: 0 8px 24px rgba(6,14,80,.35);
}
.gbtn:active:not(:disabled) { transform: translateY(0); }
.gbtn:disabled { opacity: .55; cursor: not-allowed; }

.g-icon-wrap {
  width: 32px; height: 32px; background: #fff;
  border-radius: 8px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.g-icon  { width: 18px; height: 18px; }
.g-label { flex: 1; text-align: left; }
.g-spin  { width: 16px; height: 16px; animation: spin .75s linear infinite; color: #93c5fd; flex-shrink: 0; }
@keyframes spin { to { transform: rotate(360deg); } }

.domain-notice {
  font-size: .7rem; color: #94a3b8; text-align: center; margin-bottom: 28px;
}
.domain-notice strong { color: #475569; }

/* Trust badges */
.trust-row {
  display: flex; gap: 16px; justify-content: center;
  border-top: 1px solid #f1f5f9; padding-top: 24px;
}
.trust-item {
  display: flex; flex-direction: column; align-items: center; gap: 5px;
  font-size: .62rem; color: #94a3b8; font-weight: 500; text-align: center;
}
.trust-icon { width: 18px; height: 18px; color: #cbd5e1; }

/* Copyright line inside right panel */
.copyright {
  font-size: .62rem; color: #94a3b8;
  text-align: center; margin-top: 20px;
}

/* ══════════════════════════════
   RESPONSIVE
══════════════════════════════ */

/* Tablet */
@media (max-width: 960px) {
  .hero         { flex-direction: column; min-height: auto; }
  .panel-left   { width: 100%; padding: 52px 32px; }
  .panel-left::before { display: none; }
  .panel-right  { width: 100%; padding: 48px 32px; border-top: 1px solid #f1f5f9; }
  .panel-right-inner { max-width: 100%; }
  .right-title  { font-size: 2.6rem; }
}

/* Mobile */
@media (max-width: 640px) {
  .panel-left   { padding: 40px 20px; }
  .panel-right  { padding: 40px 20px; }
  .seal         { width: 64px; height: 64px; }
  .brand-sys    { font-size: 1.3rem; }
  .right-title  { font-size: 2.2rem; }
  .stats-row    { gap: 20px; }
  .stat-val     { font-size: 1.3rem; }
}
</style>
