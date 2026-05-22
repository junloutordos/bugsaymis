<script setup>
import { ref, watch, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import axios from 'axios'
import { auth, provider, signInWithPopup } from '@/firebase'
import { usePage } from '@inertiajs/vue3'
import {
  LockClosedIcon,
  ChevronDownIcon,
  UsersIcon,
  CalendarDaysIcon,
  AcademicCapIcon,
  BriefcaseIcon,
  ChartBarIcon,
  DocumentTextIcon,
  BookOpenIcon,
  WrenchScrewdriverIcon,
  IdentificationIcon,
  HeartIcon,
  BuildingLibraryIcon,
  ClipboardDocumentListIcon,
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

const modules = [
  { icon: UsersIcon,                 color: '#1447c0', name: 'Human Resources',    desc: 'Leave, DTR, biometric sync, and employee records.' },
  { icon: CalendarDaysIcon,          color: '#047857', name: 'Activity Management', desc: 'Campus activity planning and participant tracking.' },
  { icon: AcademicCapIcon,           color: '#6d28d9', name: 'Faculty Loading',     desc: 'AI-assisted schedule generation and overload computation.' },
  { icon: BriefcaseIcon,             color: '#b45309', name: 'Recruitment',         desc: 'End-to-end hiring from postings to placement.' },
  { icon: ChartBarIcon,              color: '#0369a1', name: 'Performance (IPCR)',  desc: 'IPCR and PMS for all employee levels.' },
  { icon: DocumentTextIcon,          color: '#0f766e', name: 'Document Tracking',   desc: 'Official document routing and digital signatures.' },
  { icon: BookOpenIcon,              color: '#be123c', name: 'Library',             desc: 'Collections, borrowing, and library attendance.' },
  { icon: WrenchScrewdriverIcon,     color: '#c2410c', name: 'Service Requests',    desc: 'IT, vehicles, facilities, and messengerial services.' },
  { icon: IdentificationIcon,        color: '#0e7490', name: 'Student Attendance',  desc: 'Biometric gate attendance with real-time alerts.' },
  { icon: HeartIcon,                 color: '#9f1239', name: 'Health & Guidance',   desc: 'Clinic, counseling, and health records.' },
  { icon: BuildingLibraryIcon,       color: '#1e40af', name: 'SALN',                desc: 'Assets and liabilities filing per CSC requirements.' },
  { icon: ClipboardDocumentListIcon, color: '#7e22ce', name: 'PDS',                 desc: 'Personal Data Sheet (CSC Form 212) management.' },
]

const stats = [
  { value: '12+',   label: 'Core Modules' },
  { value: '500+',  label: 'System Users' },
  { value: '290+',  label: 'DB Migrations' },
  { value: '99.9%', label: 'Uptime' },
]
</script>

<template>
  <Head title="CRCMIS — Campus Management Information System" />

  <div class="site">

    <!-- ── NAVBAR ─────────────────────────────────────── -->
    <header class="navbar">
      <div class="nav-inner">
        <div class="nav-brand">
          <img src="/images/pshslogo.png" alt="PSHS-CRC" class="nav-logo" />
          <div>
            <span class="nav-name">CRCMIS</span>
            <span class="nav-sub">PSHS – Caraga Region Campus</span>
          </div>
        </div>
        <nav class="nav-links">
          <a href="#modules" class="nav-link">Modules</a>
          <a href="#hero"    class="nav-link">Sign In</a>
        </nav>
      </div>
    </header>

    <!-- ── HERO ───────────────────────────────────────── -->
    <section id="hero" class="hero">
      <div class="hero-bg" />

      <div class="hero-content">
        <!-- Logo -->
        <img src="/images/pshslogo.png" alt="PSHS-CRC" class="hero-logo" />

        <!-- System name -->
        <h1 class="hero-title">CRCMIS</h1>
        <p class="hero-subtitle">Campus Resource &amp; Management Information System</p>
        <p class="hero-school">Philippine Science High School — Caraga Region Campus in Butuan City</p>

        <!-- Login card -->
        <div class="login-card">
          <div class="lc-header">
            <div class="lc-icon-wrap">
              <LockClosedIcon class="lc-icon" />
            </div>
            <div>
              <p class="lc-title">Access the System</p>
              <p class="lc-sub">Sign in with your official PSHS-CRC account</p>
            </div>
          </div>

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

          <p class="lc-notice">
            <LockClosedIcon class="notice-icon" />
            Only <strong>@crc.pshs.edu.ph</strong> accounts are authorized
          </p>
        </div>

        <!-- Scroll hint -->
        <div class="scroll-hint">
          <ChevronDownIcon class="scroll-icon" />
        </div>
      </div>
    </section>

    <!-- ── MODULES ────────────────────────────────────── -->
    <section id="modules" class="modules-section">
      <div class="section-inner">
        <div class="section-head">
          <p class="eyebrow">System Modules</p>
          <h2 class="section-title">Everything campus administration needs, unified.</h2>
        </div>

        <div class="modules-grid">
          <div v-for="m in modules" :key="m.name" class="mod-card">
            <div class="mod-icon-wrap" :style="{ background: m.color }">
              <component :is="m.icon" class="mod-icon" />
            </div>
            <div class="mod-body">
              <p class="mod-name">{{ m.name }}</p>
              <p class="mod-desc">{{ m.desc }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ── STATS ──────────────────────────────────────── -->
    <section class="stats-section">
      <div class="stats-bg" />
      <div class="stats-inner">
        <div v-for="s in stats" :key="s.label" class="stat-item">
          <span class="stat-val">{{ s.value }}</span>
          <span class="stat-label">{{ s.label }}</span>
        </div>
      </div>
    </section>

    <!-- ── FOOTER ─────────────────────────────────────── -->
    <footer class="site-footer">
      <div class="footer-inner">
        <div class="footer-brand">
          <img src="/images/pshslogo.png" alt="PSHS-CRC" class="footer-logo" />
          <div>
            <p class="footer-name">CRCMIS</p>
            <p class="footer-meta">Campus Management Information System · PSHS-CRC · Butuan City</p>
          </div>
        </div>
        <div class="footer-right">
          <p class="footer-copy">© 2026 PSHS-CRC. All rights reserved.</p>
          <p class="footer-ver">v{{ appVersion }}</p>
        </div>
      </div>
    </footer>

  </div>
</template>

<style scoped>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.site {
  --navy:  #060e50;
  --blue:  #1447c0;
  --blue2: #1a5cd8;
  --cyan:  #0093b8;
  font-family: 'Inter', system-ui, Arial, sans-serif;
  color: #0f172a;
  background: #fff;
  scroll-behavior: smooth;
}

/* ── NAVBAR ────────────────────────────────────────── */
.navbar {
  position: sticky; top: 0; z-index: 100;
  background: rgba(6, 14, 80, 0.97);
  backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(255,255,255,.06);
}
.nav-inner {
  max-width: 1100px; margin: 0 auto; padding: 0 32px;
  height: 60px; display: flex; align-items: center; gap: 24px;
}
.nav-brand { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.nav-logo  { width: 32px; height: 32px; }
.nav-name  { display: block; font-size: .88rem; font-weight: 800; color: #fff; letter-spacing: .06em; }
.nav-sub   { display: block; font-size: .58rem; color: #93c5fd; }
.nav-links { display: flex; gap: 4px; margin-left: auto; }
.nav-link  {
  padding: 7px 16px; font-size: .8rem; font-weight: 500;
  color: #bfdbfe; text-decoration: none; border-radius: 8px;
  transition: color .15s, background .15s;
}
.nav-link:hover { color: #fff; background: rgba(255,255,255,.08); }

/* ── HERO ──────────────────────────────────────────── */
.hero {
  position: relative; min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  overflow: hidden;
}
.hero-bg {
  position: absolute; inset: 0;
  background: linear-gradient(150deg, #060e50 0%, #0d1a80 25%, #1447c0 60%, #1a5cd8 85%, #0093b8 100%);
}

.hero-content {
  position: relative; z-index: 2;
  display: flex; flex-direction: column; align-items: center;
  text-align: center; padding: 80px 24px 60px;
  width: 100%; max-width: 520px;
}

.hero-logo {
  width: 72px; height: 72px; margin-bottom: 24px;
  filter: drop-shadow(0 0 20px rgba(0, 147, 184, 0.5));
}

.hero-title {
  font-size: 3.5rem; font-weight: 900; color: #fff;
  letter-spacing: -.04em; line-height: 1; margin-bottom: 10px;
}

.hero-subtitle {
  font-size: 1rem; font-weight: 500; color: #bfdbfe;
  margin-bottom: 4px; letter-spacing: .01em;
}

.hero-school {
  font-size: .72rem; color: #7dd3fc;
  margin-bottom: 44px; letter-spacing: .02em;
}

/* Login card */
.login-card {
  width: 100%;
  background: rgba(255, 255, 255, .08);
  border: 1px solid rgba(255, 255, 255, .14);
  border-radius: 20px; padding: 28px 24px;
  backdrop-filter: blur(20px);
  box-shadow: 0 20px 60px rgba(6, 14, 80, .5), inset 0 1px 0 rgba(255,255,255,.1);
  margin-bottom: 36px;
}

.lc-header {
  display: flex; align-items: center; gap: 12px; margin-bottom: 22px;
}
.lc-icon-wrap {
  width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
  background: linear-gradient(135deg, #1447c0, #0093b8);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 12px rgba(0,147,184,.35);
}
.lc-icon  { width: 20px; height: 20px; color: #fff; }
.lc-title { font-size: .95rem; font-weight: 700; color: #f0f9ff; }
.lc-sub   { font-size: .72rem; color: #93c5fd; margin-top: 2px; }

/* Google sign-in button */
.gbtn {
  display: flex; align-items: center; gap: 12px; width: 100%;
  padding: 13px 16px; background: #fff; border: none;
  border-radius: 12px; font-size: .875rem; font-weight: 600;
  color: #1e293b; cursor: pointer;
  box-shadow: 0 2px 8px rgba(0,0,0,.15);
  transition: transform .2s, box-shadow .2s;
}
.gbtn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 8px 24px rgba(0, 147, 184, .25);
}
.gbtn:disabled { opacity: .6; cursor: not-allowed; }
.g-icon-wrap {
  width: 32px; height: 32px; background: #f8fafc;
  border: 1px solid #e2e8f0; border-radius: 8px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.g-icon  { width: 18px; height: 18px; }
.g-label { flex: 1; text-align: left; }
.g-spin  { width: 16px; height: 16px; animation: spin .75s linear infinite; color: #1447c0; flex-shrink: 0; }
@keyframes spin { to { transform: rotate(360deg); } }

.lc-notice {
  display: flex; align-items: center; gap: 6px; justify-content: center;
  font-size: .7rem; color: #7dd3fc; margin-top: 14px;
}
.lc-notice strong { color: #bfdbfe; }
.notice-icon { width: 12px; height: 12px; flex-shrink: 0; }

/* Scroll hint */
.scroll-hint { display: flex; flex-direction: column; align-items: center; }
.scroll-icon {
  width: 20px; height: 20px; color: rgba(255,255,255,.3);
  animation: bounce 2s ease-in-out infinite;
}
@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50%       { transform: translateY(6px); }
}

/* ── MODULES ───────────────────────────────────────── */
.modules-section { padding: 96px 32px; background: #fff; }
.section-inner   { max-width: 1100px; margin: 0 auto; }

.section-head    { text-align: center; margin-bottom: 60px; }
.eyebrow {
  display: inline-block; font-size: .68rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .12em; margin-bottom: 12px;
  color: #1447c0;
}
.section-title {
  font-size: 2rem; font-weight: 800; color: #0a1040;
  line-height: 1.2; letter-spacing: -.025em;
}

.modules-grid {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
}
.mod-card {
  display: flex; flex-direction: column; gap: 12px;
  padding: 22px 18px; background: #f8fafc;
  border: 1px solid #e2e8f0; border-radius: 16px;
  transition: background .2s, border-color .2s, transform .2s, box-shadow .2s;
}
.mod-card:hover {
  background: #fff; border-color: #bfdbfe;
  transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(20, 71, 192, .1);
}
.mod-icon-wrap {
  width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.mod-icon { width: 22px; height: 22px; color: #fff; }
.mod-name { font-size: .82rem; font-weight: 700; color: #0a1040; margin-bottom: 4px; }
.mod-desc { font-size: .72rem; color: #475569; line-height: 1.6; }

/* ── STATS ─────────────────────────────────────────── */
.stats-section { position: relative; padding: 72px 32px; overflow: hidden; }
.stats-bg {
  position: absolute; inset: 0;
  background: linear-gradient(130deg, #060e50 0%, #0d1a80 35%, #1447c0 65%, #1a5cd8 100%);
}
.stats-inner {
  position: relative; z-index: 2; max-width: 900px; margin: 0 auto;
  display: flex; justify-content: space-around; gap: 32px; flex-wrap: wrap;
}
.stat-item   { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.stat-val {
  font-size: 2.8rem; font-weight: 900; line-height: 1;
  background: linear-gradient(90deg, #f5c000 0%, #00c8e8 60%, #7dd3fc 100%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.stat-label { font-size: .68rem; font-weight: 600; color: #93c5fd; text-transform: uppercase; letter-spacing: .1em; }

/* ── FOOTER ────────────────────────────────────────── */
.site-footer { background: #03071e; padding: 36px 32px; border-top: 1px solid #0d1f8a; }
.footer-inner {
  max-width: 1100px; margin: 0 auto;
  display: flex; align-items: center; justify-content: space-between;
  gap: 20px; flex-wrap: wrap;
}
.footer-brand { display: flex; align-items: center; gap: 12px; }
.footer-logo  { width: 32px; height: 32px; opacity: .5; }
.footer-name  { font-size: .82rem; font-weight: 700; color: #e2e8f0; letter-spacing: .05em; }
.footer-meta  { font-size: .62rem; color: #64748b; margin-top: 2px; }
.footer-right { text-align: right; }
.footer-copy  { font-size: .68rem; color: #94a3b8; }
.footer-ver   { font-size: .62rem; color: #475569; margin-top: 3px; }

/* ── RESPONSIVE ────────────────────────────────────── */
@media (max-width: 900px) {
  .hero-title     { font-size: 2.8rem; }
  .modules-grid   { grid-template-columns: repeat(3, 1fr); }
  .modules-section{ padding: 72px 24px; }
}

@media (max-width: 640px) {
  .nav-inner      { padding: 0 16px; height: 54px; }
  .nav-sub        { display: none; }
  .hero-logo      { width: 56px; height: 56px; }
  .hero-title     { font-size: 2.4rem; }
  .hero-subtitle  { font-size: .875rem; }
  .hero-school    { font-size: .68rem; margin-bottom: 32px; }
  .login-card     { padding: 22px 18px; }
  .modules-grid   { grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .modules-section{ padding: 56px 16px; }
  .section-title  { font-size: 1.65rem; }
  .stats-section  { padding: 56px 16px; }
  .stats-inner    { display: grid; grid-template-columns: repeat(2, 1fr); gap: 28px 16px; justify-items: center; }
  .stat-val       { font-size: 2.2rem; }
  .site-footer    { padding: 28px 16px; }
  .footer-inner   { flex-direction: column; text-align: center; }
  .footer-right   { text-align: center; }
}

@media (max-width: 400px) {
  .hero-title     { font-size: 2rem; }
  .modules-grid   { grid-template-columns: 1fr; }
  .mod-card       { flex-direction: row; }
  .mod-body       { min-width: 0; }
}
</style>
