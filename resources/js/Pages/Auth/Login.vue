<script setup>
import { ref, watch, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import axios from 'axios'
import { auth, provider, signInWithPopup } from '@/firebase'
import { usePage } from '@inertiajs/vue3'
import {
  ShieldCheckIcon, BoltIcon, UsersIcon,
  UsersIcon as UsersIcon2,
  CalendarDaysIcon, AcademicCapIcon, BriefcaseIcon, ChartBarIcon,
  DocumentTextIcon, BookOpenIcon, WrenchScrewdriverIcon, IdentificationIcon,
  HeartIcon, BuildingLibraryIcon, ClipboardDocumentListIcon,
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

const modules = [
  { icon: UsersIcon2,              color: '#1447c0', name: 'Human Resources',    desc: 'Leave, DTR, biometrics & employee records' },
  { icon: CalendarDaysIcon,        color: '#047857', name: 'Activity Management', desc: 'Campus events, co-proponents & tracking' },
  { icon: AcademicCapIcon,         color: '#6d28d9', name: 'Faculty Loading',     desc: 'AI schedules, overload & assignments' },
  { icon: BriefcaseIcon,           color: '#b45309', name: 'Recruitment',         desc: 'Postings, interviews & final placement' },
  { icon: ChartBarIcon,            color: '#0369a1', name: 'IPCR / PMS',          desc: 'Performance evaluation for all levels' },
  { icon: DocumentTextIcon,        color: '#0f766e', name: 'Document Tracking',   desc: 'Routing, signatures & digital workflow' },
  { icon: BookOpenIcon,            color: '#be123c', name: 'Library',             desc: 'Collections, borrowing & attendance' },
  { icon: WrenchScrewdriverIcon,   color: '#c2410c', name: 'Service Requests',    desc: 'IT, vehicles, facilities & messengerial' },
  { icon: IdentificationIcon,      color: '#0e7490', name: 'Student Attendance',  desc: 'Biometric gate with real-time alerts' },
  { icon: HeartIcon,               color: '#9f1239', name: 'Health & Guidance',   desc: 'Clinic, counseling & health records' },
  { icon: BuildingLibraryIcon,     color: '#1e40af', name: 'SALN',                desc: 'Assets & liabilities per CSC rules' },
  { icon: ClipboardDocumentListIcon, color: '#7e22ce', name: 'PDS',               desc: 'CSC Form 212 management & submission' },
]
</script>

<template>
  <Head title="CRCMIS — Campus Management Information System" />

  <div class="site">

    <!-- ╔══════════════════════════════════════════
         ║  NAVBAR
         ╚═════════════════════════════════════════ -->
    <header class="navbar">
      <div class="nav-inner">
        <div class="nav-brand">
          <img src="/images/pshslogo.png" alt="PSHS-CRC" class="nav-logo" />
          <div>
            <span class="nav-name">CRCMIS</span>
            <span class="nav-tag">Campus Management Information System</span>
          </div>
        </div>
        <span class="nav-ver">v{{ appVersion }}</span>
      </div>
    </header>

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

        </div>
      </div>

    </section>

    <!-- ╔══════════════════════════════════════════
         ║  MODULES
         ╚═════════════════════════════════════════ -->
    <section id="modules" class="modules-section">
      <div class="modules-inner">

        <div class="modules-head">
          <p class="modules-eyebrow">System Modules</p>
          <h2 class="modules-title">Everything campus operations needs, in one place.</h2>
        </div>

        <div class="modules-grid">
          <div v-for="m in modules" :key="m.name" class="mod-card">
            <div class="mod-icon-bg" :style="{ background: m.color }">
              <component :is="m.icon" class="mod-icon" />
            </div>
            <p class="mod-name">{{ m.name }}</p>
            <p class="mod-desc">{{ m.desc }}</p>
          </div>
        </div>

      </div>
    </section>

    <!-- ╔══════════════════════════════════════════
         ║  FOOTER
         ╚═════════════════════════════════════════ -->
    <footer class="site-footer">
      <div class="footer-inner">
        <div class="footer-left">
          <img src="/images/pshslogo.png" alt="PSHS-CRC" class="footer-logo" />
          <span class="footer-name">CRCMIS</span>
          <span class="footer-sep">·</span>
          <span class="footer-meta">Philippine Science High School – Caraga Region Campus</span>
        </div>
        <span class="footer-copy">© 2026 PSHS-CRC · v{{ appVersion }}</span>
      </div>
    </footer>

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
   NAVBAR
══════════════════════════════ */
.navbar {
  position: sticky; top: 0; z-index: 100;
  background: #060e50;
  border-bottom: 1px solid rgba(255,255,255,.07);
  height: 52px;
}
.nav-inner {
  max-width: 1280px; margin: 0 auto; padding: 0 32px;
  height: 100%; display: flex; align-items: center;
}
.nav-brand { display: flex; align-items: center; gap: 10px; }
.nav-logo  { width: 28px; height: 28px; }
.nav-name  { font-size: .82rem; font-weight: 800; color: #fff; letter-spacing: .07em; display: block; }
.nav-tag   { font-size: .56rem; color: #93c5fd; display: block; margin-top: 1px; }
.nav-ver   { margin-left: auto; font-size: .62rem; color: #334155; }

/* ══════════════════════════════
   HERO — SPLIT SCREEN
══════════════════════════════ */
.hero {
  flex: 1;
  display: flex;
  min-height: calc(100vh - 52px);
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

/* ══════════════════════════════
   MODULES
══════════════════════════════ */
.modules-section {
  background: #060e50;
  padding: 80px 32px;
}

.modules-inner { max-width: 1280px; margin: 0 auto; }

.modules-head { text-align: center; margin-bottom: 48px; }
.modules-eyebrow {
  font-size: .65rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .14em; color: #60a5fa; margin-bottom: 10px;
}
.modules-title {
  font-size: 1.7rem; font-weight: 800; color: #fff;
  letter-spacing: -.025em; line-height: 1.25;
}

.modules-grid {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px;
}

.mod-card {
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 16px; padding: 22px 18px;
  display: flex; flex-direction: column; gap: 10px;
  transition: background .2s, border-color .2s, transform .2s;
  cursor: default;
}
.mod-card:hover {
  background: rgba(255,255,255,.08);
  border-color: rgba(255,255,255,.14);
  transform: translateY(-2px);
}

.mod-icon-bg {
  width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  opacity: .92;
}
.mod-icon { width: 20px; height: 20px; color: #fff; }
.mod-name { font-size: .8rem; font-weight: 700; color: #e2e8f0; }
.mod-desc { font-size: .68rem; color: #64748b; line-height: 1.55; }

/* ══════════════════════════════
   FOOTER
══════════════════════════════ */
.site-footer {
  background: #03071e;
  border-top: 1px solid rgba(255,255,255,.05);
  padding: 20px 32px;
}
.footer-inner {
  max-width: 1280px; margin: 0 auto;
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px; flex-wrap: wrap;
}
.footer-left  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.footer-logo  { width: 24px; height: 24px; opacity: .4; }
.footer-name  { font-size: .72rem; font-weight: 700; color: #cbd5e1; letter-spacing: .06em; }
.footer-sep   { color: #334155; font-size: .72rem; }
.footer-meta  { font-size: .65rem; color: #334155; }
.footer-copy  { font-size: .65rem; color: #334155; }

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
  .modules-grid { grid-template-columns: repeat(3, 1fr); }
}

/* Mobile */
@media (max-width: 640px) {
  .nav-inner    { padding: 0 16px; }
  .nav-tag      { display: none; }
  .panel-left   { padding: 40px 20px; }
  .panel-right  { padding: 40px 20px; }
  .seal         { width: 64px; height: 64px; }
  .brand-sys    { font-size: 1.3rem; }
  .right-title  { font-size: 2.2rem; }
  .stats-row    { gap: 20px; }
  .stat-val     { font-size: 1.3rem; }
  .modules-section { padding: 56px 16px; }
  .modules-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .modules-title { font-size: 1.4rem; }
  .footer-meta  { display: none; }
  .site-footer  { padding: 16px; }
}

@media (max-width: 400px) {
  .modules-grid { grid-template-columns: 1fr; }
  .mod-card     { flex-direction: row; align-items: flex-start; gap: 12px; }
}
</style>
