<script setup>
import { ref, watch, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import axios from 'axios'
import { auth, provider, signInWithPopup } from '@/firebase'
import { usePage } from '@inertiajs/vue3'
import {
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
  ShieldCheckIcon,
  BoltIcon,
  FlagIcon,
  LockClosedIcon,
  ChevronDoubleDownIcon,
  ArrowRightIcon,
} from '@heroicons/vue/24/outline'

const OFFICIAL_DOMAIN = '@crc.pshs.edu.ph'
const isLoading = ref(false)

const showAlert = (options) => Swal.fire({ confirmButtonColor: '#1447c0', ...options })
const isAuthorizedEmail = (email) => email?.toLowerCase().endsWith(OFFICIAL_DOMAIN)

const page = usePage()
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
  { icon: UsersIcon,                 bg: '#1447c0', name: 'Human Resources',    desc: 'Leave, DTR, biometric sync, employee schedules, and records management.' },
  { icon: CalendarDaysIcon,          bg: '#047857', name: 'Activity Management',  desc: 'Campus activity planning, co-proponent coordination, and participant tracking.' },
  { icon: AcademicCapIcon,           bg: '#6d28d9', name: 'Faculty Loading',     desc: 'AI-assisted schedule generation, overload computation, and teaching assignments.' },
  { icon: BriefcaseIcon,             bg: '#b45309', name: 'Recruitment',         desc: 'End-to-end recruitment from job postings through interviews to final placement.' },
  { icon: ChartBarIcon,              bg: '#0369a1', name: 'Performance (IPCR)',  desc: 'IPCR and PMS evaluation system for all employee levels including faculty and staff.' },
  { icon: DocumentTextIcon,          bg: '#0f766e', name: 'Document Tracking',   desc: 'Official document routing, tracking, and digital signature workflow across offices.' },
  { icon: BookOpenIcon,              bg: '#be123c', name: 'Library',             desc: 'Collection cataloging, borrowing management, overdue tracking, and library attendance.' },
  { icon: WrenchScrewdriverIcon,     bg: '#c2410c', name: 'Service Requests',    desc: 'IT, vehicle scheduling, facility booking, work orders, and messengerial services.' },
  { icon: IdentificationIcon,        bg: '#0e7490', name: 'Student Attendance',  desc: 'Biometric gate attendance with real-time notifications and attendance reports.' },
  { icon: HeartIcon,                 bg: '#9f1239', name: 'Health & Guidance',   desc: 'Clinic consultations, health records, guidance counseling, and referral tracking.' },
  { icon: BuildingLibraryIcon,       bg: '#1e40af', name: 'SALN',                desc: 'Statement of Assets, Liabilities, and Net Worth filing per CSC requirements.' },
  { icon: ClipboardDocumentListIcon, bg: '#7e22ce', name: 'PDS',                 desc: 'Personal Data Sheet (CSC Form 212) management and electronic submission.' },
]

const pillars = [
  {
    icon: ShieldCheckIcon,
    bg: 'linear-gradient(135deg,#1447c0,#00c8e8)',
    title: 'Secure & Role-Based',
    desc: 'Google SSO restricts access to official PSHS-CRC accounts only. Every user has a fine-grained role — administrators, HR officers, faculty, staff, and students each see exactly what they need.',
  },
  {
    icon: BoltIcon,
    bg: 'linear-gradient(135deg,#0369a1,#00c8e8)',
    title: 'Real-Time & Integrated',
    desc: 'Built on Laravel and Vue 3 with live WebSocket notifications via Soketi. Approvals, alerts, and status changes propagate instantly — no page refreshes, no delays.',
  },
  {
    icon: FlagIcon,
    bg: 'linear-gradient(135deg,#047857,#0891b2)',
    title: 'Built for the Philippines',
    desc: 'Fully compliant with CSC leave types, Salary Standardization Law schedules, SALN requirements, and Philippine locale date and currency formatting.',
  },
]

const stats = [
  { value: '12+',  label: 'Core Modules' },
  { value: '500+', label: 'System Users' },
  { value: '290+', label: 'DB Migrations' },
  { value: '99.9%',label: 'Uptime' },
]
</script>

<template>
  <Head title="CRCMIS — Campus Management Information System" />

  <div class="site">

    <!-- ════════════════════════════
         NAVBAR
    ═════════════════════════════ -->
    <header class="navbar">
      <div class="nav-inner">
        <div class="nav-brand">
          <img src="/images/pshslogo.png" alt="PSHS-CRC" class="nav-logo" />
          <div>
            <span class="nav-name">CRCMIS</span>
            <span class="nav-sub">PSHS – Caraga Region Campus in Butuan City</span>
          </div>
        </div>
        <nav class="nav-links">
          <a href="#about"      class="nav-link">About</a>
          <a href="#modules"    class="nav-link">Modules</a>
          <a href="#hero-login" class="nav-link">Access</a>
        </nav>
        <button @click="googleLogin" :disabled="isLoading" class="nav-cta">
          Sign In <ArrowRightIcon class="nav-cta-icon" />
        </button>
      </div>
    </header>

    <!-- ════════════════════════════
         HERO
    ═════════════════════════════ -->
    <section class="hero">
      <!-- background layers -->
      <div class="hero-base" />
      <!-- bottom-left ambient glows -->
      <div class="deco deco-cyan" />
      <div class="deco deco-bl" />

      <div class="hero-inner">
        <!-- Left: hero text + stats + chips -->
        <div class="hero-left">
          <div class="hero-badge">
            <span class="badge-dot" />
            Philippine Science High School – Caraga Region Campus in Butuan City
          </div>

          <h1 class="hero-h1">
            a Way Forward <br>
            <span class="hero-accent">to Digital Transformation.</span>
          </h1>

          <p class="hero-p">
            CRCMIS is the Centralized Resource and Campus Management Information System of PSHS-CRC in Butuan City.
            It unifies HR, activity, academics, student services, and campus operations into a single secure platform.
          </p>

          <div class="chip-box">
            <p class="chip-box-label">Integrated Modules</p>
            <div class="chip-list">
              <span v-for="m in modules" :key="m.name" class="chip">
                <component :is="m.icon" class="chip-icon" />
                {{ m.name }}
              </span>
            </div>
          </div>
        </div>

        <!-- Right: login card only -->
        <div id="hero-login" class="hero-right">
          <div class="login-card">
            <div class="lc-head">
              <div class="lc-icon-wrap">
                <LockClosedIcon class="lc-icon" />
              </div>
              <div>
                <p class="lc-title">Access the System</p>
                <p class="lc-sub">Sign in with your official PSHS-CRC Google account.</p>
              </div>
            </div>

            <button @click="googleLogin" :disabled="isLoading" class="gbtn">
              <span class="g-icon-wrap">
                <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" class="g-icon" alt="Google" />
              </span>
              <span class="g-label">{{ isLoading ? 'Signing in…' : 'Continue with your PSHS-CRC Google Account' }}</span>
              <svg v-if="isLoading" class="g-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"/>
              </svg>
            </button>

            <p class="lc-notice">
              <LockClosedIcon class="notice-icon" />
              <span>Only <strong>@crc.pshs.edu.ph</strong> accounts are authorized to sign in.</span>
            </p>
          </div>
        </div>
      </div>

      <div class="scroll-hint">
        <span class="scroll-text">Scroll to explore</span>
        <ChevronDoubleDownIcon class="scroll-icon" />
      </div>
    </section>

    <!-- ════════════════════════════
         ABOUT / PILLARS
    ═════════════════════════════ -->
    <section id="about" class="section bg-white">
      <div class="section-inner">
        <div class="section-hd">
          <p class="eyebrow">About CRCMIS</p>
          <h2 class="section-h2">Built for PSHS-CRC.<br>Designed for everyone in it.</h2>
          <p class="section-lead">
            From the mancom to faculty, staff and students, CRCMIS gives each stakeholder
            the exact tools and data they need — nothing more, nothing less.
          </p>
        </div>

        <div class="pillars">
          <div v-for="p in pillars" :key="p.title" class="pillar">
            <div class="pillar-icon-wrap" :style="{ background: p.bg }">
              <component :is="p.icon" class="pillar-icon" />
            </div>
            <h3 class="pillar-title">{{ p.title }}</h3>
            <p class="pillar-desc">{{ p.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ════════════════════════════
         STATS BAND
    ═════════════════════════════ -->
    <section class="stats-band">
      <div class="sb-base" />
      <div class="sb-deco sb-deco1" />
      <div class="sb-deco sb-deco2" />
      <div class="sb-inner">
        <div v-for="s in stats" :key="s.label" class="sb-item">
          <span class="sb-val">{{ s.value }}</span>
          <span class="sb-lbl">{{ s.label }}</span>
        </div>
      </div>
    </section>

    <!-- ════════════════════════════
         MODULES
    ═════════════════════════════ -->
    <section id="modules" class="section bg-light">
      <div class="section-inner">
        <div class="section-hd">
          <p class="eyebrow">System Modules</p>
          <h2 class="section-h2">Everything campus administration needs, unified.</h2>
          <p class="section-lead">
            CRCMIS covers all aspects of campus life — from personnel management and service requests
            to student attendance and library services.
          </p>
        </div>

        <div class="modules-grid">
          <div v-for="m in modules" :key="m.name" class="mod-card">
            <div class="mod-icon-wrap" :style="{ background: m.bg }">
              <component :is="m.icon" class="mod-icon" />
            </div>
            <div>
              <h3 class="mod-name">{{ m.name }}</h3>
              <p class="mod-desc">{{ m.desc }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ════════════════════════════
         CTA
    ═════════════════════════════ -->
    <section class="cta-section">
      <div class="cta-base" />
      <div class="cta-deco cta-d1" />
      <div class="cta-deco cta-d2" />
      <div class="cta-deco cta-d3" />
      <div class="cta-inner">
        <div class="cta-icon-wrap">
          <ShieldCheckIcon class="cta-icon" />
        </div>
        <h2 class="cta-h2">Ready to get started?</h2>
        <p class="cta-p">Sign in with your official PSHS-CRC Google account to access the system.</p>
        <button @click="googleLogin" :disabled="isLoading" class="cta-btn">
          <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" style="width:22px;height:22px;" alt="Google" />
          {{ isLoading ? 'Signing in…' : 'Sign in with Google' }}
          <svg v-if="isLoading" class="g-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"/>
          </svg>
        </button>
        <p class="cta-note">Only @crc.pshs.edu.ph accounts are authorized.</p>
      </div>
    </section>

    <!-- ════════════════════════════
         FOOTER
    ═════════════════════════════ -->
    <footer class="site-footer">
      <div class="footer-inner">
        <div class="footer-brand">
          <img src="/images/pshslogo.png" alt="PSHS-CRC" class="footer-logo" />
          <div>
            <p class="footer-name">CRCMIS</p>
            <p class="footer-sub">Campus Management Information System</p>
            <p class="footer-sub">Philippine Science High School – Caraga Region Campus in Butuan City</p>
          </div>
        </div>
        <div class="footer-right">
          <p class="footer-copy">© 2026 PSHS-CRC. All rights reserved.</p>
          <p class="footer-ver">v{{ appVersion }}</p>
          <p class="footer-ver"><a href="/developer" class="dev-link">Developer Information</a></p>
        </div>
      </div>
    </footer>

  </div>
</template>

<style scoped>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── CSS custom properties (DOST-SEI palette) ─────────── */
.site {
  --navy:   #060e50;
  --blue:   #1447c0;
  --blue2:  #1a5cd8;
  --bright: #1e7de8;
  --cyan:   #00c8e8;
  --gold:   #f5c000;
  --white:  #ffffff;
  font-family: 'Inter', system-ui, Arial, sans-serif;
  color: #0f172a;
  background: #fff;
  scroll-behavior: smooth;
}

/* ════════════════════════════════
   NAVBAR
════════════════════════════════ */
.navbar {
  position: sticky;
  top: 0;
  z-index: 100;
  background: rgba(6, 14, 80, 0.97);
  backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(0,200,232,.15);
}
.nav-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 32px;
  height: 66px;
  display: flex;
  align-items: center;
  gap: 24px;
}
.nav-brand { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.nav-logo  { width: 36px; height: 36px; filter: drop-shadow(0 0 10px rgba(0,200,232,.5)); }
.nav-name  { display: block; font-size: .92rem; font-weight: 800; color: #ffffff; letter-spacing: .06em; }
.nav-sub   { display: block; font-size: .58rem; color: #93c5fd; }

.nav-links { display: flex; gap: 4px; margin-left: auto; }
.nav-link  {
  padding: 7px 16px; font-size: .82rem; font-weight: 500;
  color: #bfdbfe; text-decoration: none; border-radius: 8px;
  transition: color .15s, background .15s;
}
.nav-link:hover { color: #fff; background: rgba(255,255,255,.09); }

.nav-cta {
  display: flex; align-items: center; gap: 6px;
  padding: 9px 20px;
  background: linear-gradient(135deg, #1447c0, #00c8e8);
  color: #fff; border: none; border-radius: 10px;
  font-size: .82rem; font-weight: 700; cursor: pointer;
  box-shadow: 0 4px 16px rgba(0,200,232,.35);
  transition: opacity .15s, transform .15s, box-shadow .15s;
  flex-shrink: 0;
}
.nav-cta:hover { opacity: .9; transform: translateY(-1px); box-shadow: 0 6px 22px rgba(0,200,232,.45); }
.nav-cta-icon { width: 14px; height: 14px; }

/* ════════════════════════════════
   HERO
════════════════════════════════ */
.hero {
  position: relative;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* Deep navy-to-blue base gradient */
.hero-base {
  position: absolute; inset: 0;
  background: linear-gradient(145deg, #060e50 0%, #0d1f8a 30%, #1447c0 60%, #1a5cd8 85%, #1e7de8 100%);
}

/* Ambient glow elements */
.deco {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}
/* Cyan glow bottom-left */
.deco-cyan {
  width: 500px; height: 500px;
  bottom: -200px; left: -100px;
  background: radial-gradient(circle, rgba(0,200,232,.15) 0%, transparent 65%);
}
/* Subtle glow bottom-right */
.deco-bl {
  width: 300px; height: 300px;
  bottom: 10%; right: 20%;
  background: radial-gradient(circle, rgba(30,125,232,.10) 0%, transparent 65%);
}

.hero-inner {
  position: relative; z-index: 2;
  flex: 1;
  display: flex; align-items: flex-start; gap: 60px;
  max-width: 1200px; width: 100%; margin: 0 auto;
  padding: 80px 32px 48px;
}

.hero-badge {
  display: inline-flex; align-items: center; gap: 8px;
  align-self: flex-start;
  padding: 6px 16px;
  background: rgba(0,200,232,.12);
  border: 1px solid rgba(0,200,232,.3);
  border-radius: 100px;
  font-size: .72rem; font-weight: 500; color: #7dd3fc;
  letter-spacing: .02em; margin-bottom: 28px;
}
.badge-dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: #00c8e8; box-shadow: 0 0 8px #00c8e8;
  animation: pulse-dot 2s ease-in-out infinite;
  flex-shrink: 0;
}
@keyframes pulse-dot {
  0%, 100% { box-shadow: 0 0 8px #00c8e8; }
  50%       { box-shadow: 0 0 2px #00c8e8; opacity: .5; }
}

.hero-h1 {
  font-size: 3.6rem; font-weight: 900; color: #ffffff;
  line-height: 1.1; letter-spacing: -.03em; margin-bottom: 22px;
}
/* Cyan gradient accent text — same feel as "15 May 2026" in the reference */
.hero-accent {
  background: linear-gradient(90deg, #00c8e8 0%, #7dd3fc 60%, #bfdbfe 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-p {
  font-size: .95rem; color: #bfdbfe;
  line-height: 1.8; margin-bottom: 40px; max-width: 500px;
}

/* Login card */
.login-card {
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.15);
  border-radius: 20px; padding: 28px; width: 100%;
  backdrop-filter: blur(14px);
  box-shadow: 0 24px 64px rgba(6,14,80,.5), inset 0 1px 0 rgba(255,255,255,.1);
}
.lc-head { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }
.lc-icon-wrap {
  width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
  background: linear-gradient(135deg, #1447c0, #00c8e8);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 14px rgba(0,200,232,.35);
}
.lc-icon  { width: 22px; height: 22px; color: #fff; }
.lc-title { font-size: .95rem; font-weight: 700; color: #f0f9ff; }
.lc-sub   { font-size: .74rem; color: #93c5fd; margin-top: 3px; }

.gbtn {
  display: flex; align-items: center; gap: 12px; width: 100%;
  padding: 14px 18px; background: #fff; border: none;
  border-radius: 14px; font-size: .875rem; font-weight: 600;
  color: #1e293b; cursor: pointer;
  box-shadow: 0 2px 12px rgba(0,0,0,.18);
  transition: transform .2s, box-shadow .2s;
}
.gbtn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(0,200,232,.28);
}
.gbtn:active:not(:disabled) { transform: translateY(0); }
.gbtn:focus-visible { outline: 2px solid #00c8e8; outline-offset: 3px; }
.gbtn:disabled { opacity: .6; cursor: not-allowed; }
.g-icon-wrap {
  width: 36px; height: 36px; background: #f8fafc;
  border: 1px solid #e2e8f0; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.g-icon  { width: 20px; height: 20px; }
.g-label { flex: 1; text-align: left; }
.g-spin  { width: 16px; height: 16px; animation: spin .75s linear infinite; color: #1447c0; flex-shrink: 0; }
@keyframes spin { to { transform: rotate(360deg); } }

.lc-notice {
  display: flex; align-items: flex-start; gap: 7px;
  font-size: .72rem; color: #93c5fd; margin-top: 14px; line-height: 1.5;
}
.lc-notice strong { white-space: nowrap; }
.notice-icon { width: 13px; height: 13px; flex-shrink: 0; color: #00c8e8; }

.dev-link {
  font-size: .64rem; color: #4a5568;
  text-decoration: none; letter-spacing: .03em;
  transition: color .2s;
}
.dev-link:hover { color: #93c5fd; }

/* ── Hero left (hero text + stats + chips) ─────────── */
.hero-left { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 20px; }

/* ── Hero right (login card only) ─────────── */
/* padding-top = badge height (~30px) + badge margin-bottom (28px) to align with h1 */
.hero-right { width: 380px; flex-shrink: 0; padding-top: 58px; }

.stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
.stat-card {
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.11);
  border-radius: 18px; padding: 22px 16px;
  display: flex; flex-direction: column; align-items: center; gap: 5px;
  transition: background .2s, border-color .2s;
}
.stat-card:hover { background: rgba(0,200,232,.1); border-color: rgba(0,200,232,.35); }
.stat-val {
  font-size: 2rem; font-weight: 900; line-height: 1;
  background: linear-gradient(90deg, #00c8e8, #7dd3fc);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.stat-lbl { font-size: .64rem; color: #93c5fd; text-transform: uppercase; letter-spacing: .09em; text-align: center; }

.chip-box {
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.09);
  border-radius: 18px; padding: 18px;
}
.chip-box-label { font-size: .64rem; font-weight: 700; color: #93c5fd; text-transform: uppercase; letter-spacing: .09em; margin-bottom: 12px; }
.chip-list { display: flex; flex-wrap: wrap; gap: 6px; }
.chip {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px;
  background: rgba(0,200,232,.12);
  border: 1px solid rgba(0,200,232,.22);
  border-radius: 100px;
  font-size: .63rem; font-weight: 500; color: #7dd3fc;
}
.chip-icon { width: 11px; height: 11px; flex-shrink: 0; }

/* Scroll hint */
.scroll-hint {
  position: relative; z-index: 2;
  display: flex; flex-direction: column; align-items: center; gap: 5px;
  padding-bottom: 32px;
}
.scroll-text { font-size: .68rem; font-weight: 600; color: #93c5fd; text-transform: uppercase; letter-spacing: .1em; }
.scroll-icon {
  width: 20px; height: 20px; color: #00c8e8;
  animation: bounce-down 2s ease-in-out infinite;
}
@keyframes bounce-down {
  0%, 100% { transform: translateY(0); }
  50%       { transform: translateY(6px); }
}

/* ════════════════════════════════
   SHARED SECTION
════════════════════════════════ */
.section { padding: 100px 32px; }
.bg-white { background: #ffffff; }
.bg-light { background: #f0f6ff; }
.section-inner { max-width: 1200px; margin: 0 auto; }

.section-hd { text-align: center; margin-bottom: 72px; }
.eyebrow {
  display: inline-block;
  font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .12em;
  margin-bottom: 14px;
  background: linear-gradient(90deg, #1447c0, #00c8e8);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.section-h2 {
  font-size: 2.25rem; font-weight: 800; color: #0a1040;
  line-height: 1.2; letter-spacing: -.025em; margin-bottom: 18px;
}
.section-lead {
  font-size: .9rem; color: #334155; line-height: 1.75;
  max-width: 560px; margin: 0 auto;
}

/* ════════════════════════════════
   PILLARS
════════════════════════════════ */
.pillars { display: grid; grid-template-columns: repeat(3,1fr); gap: 28px; }
.pillar {
  padding: 36px 28px; background: #fff;
  border: 1px solid #dbeafe; border-radius: 22px;
  transition: box-shadow .25s, transform .25s;
}
.pillar:hover { box-shadow: 0 20px 48px rgba(20,71,192,.13); transform: translateY(-6px); }
.pillar-icon-wrap {
  width: 56px; height: 56px; border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 20px; box-shadow: 0 6px 18px rgba(0,0,0,.18);
}
.pillar-icon  { width: 28px; height: 28px; color: #fff; }
.pillar-title { font-size: 1.05rem; font-weight: 700; color: #0a1040; margin-bottom: 12px; }
.pillar-desc  { font-size: .83rem; color: #334155; line-height: 1.75; }

/* ════════════════════════════════
   STATS BAND
════════════════════════════════ */
.stats-band { position: relative; padding: 72px 32px; overflow: hidden; }
.sb-base {
  position: absolute; inset: 0;
  background: linear-gradient(130deg, #060e50 0%, #0d1f8a 35%, #1447c0 65%, #1a5cd8 100%);
}
.sb-deco { position: absolute; border-radius: 50%; pointer-events: none; }
.sb-deco1 {
  width: 500px; height: 500px; right: -100px; top: -200px;
  background: radial-gradient(circle, rgba(0,200,232,.25) 0%, transparent 65%);
}
.sb-deco2 {
  width: 350px; height: 350px; left: -80px; bottom: -150px;
  background: radial-gradient(circle, rgba(245,192,0,.1) 0%, transparent 65%);
}
.sb-inner {
  position: relative; z-index: 2;
  max-width: 900px; margin: 0 auto;
  display: flex; justify-content: space-around; gap: 32px; flex-wrap: wrap;
}
.sb-item { display: flex; flex-direction: column; align-items: center; gap: 8px; }
.sb-val {
  font-size: 3rem; font-weight: 900; line-height: 1;
  background: linear-gradient(90deg, #f5c000 0%, #00c8e8 60%, #7dd3fc 100%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.sb-lbl { font-size: .72rem; font-weight: 600; color: #93c5fd; text-transform: uppercase; letter-spacing: .1em; }

/* ════════════════════════════════
   MODULES
════════════════════════════════ */
.modules-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 22px; }
.mod-card {
  background: #fff; border: 1px solid #dbeafe; border-radius: 18px;
  padding: 26px 22px; display: flex; flex-direction: column; gap: 14px;
  transition: box-shadow .25s, transform .25s, border-color .25s;
}
.mod-card:hover { box-shadow: 0 12px 32px rgba(20,71,192,.14); transform: translateY(-4px); border-color: #93c5fd; }
.mod-icon-wrap {
  width: 48px; height: 48px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 12px rgba(0,0,0,.18); flex-shrink: 0;
}
.mod-icon { width: 24px; height: 24px; color: #fff; }
.mod-name { font-size: .85rem; font-weight: 700; color: #0a1040; margin-bottom: 4px; }
.mod-desc { font-size: .75rem; color: #334155; line-height: 1.65; }

/* ════════════════════════════════
   CTA
════════════════════════════════ */
.cta-section { position: relative; padding: 100px 32px; text-align: center; overflow: hidden; }
.cta-base {
  position: absolute; inset: 0;
  background: linear-gradient(160deg, #060e50 0%, #0d1f8a 40%, #1447c0 70%, #1a5cd8 100%);
}
.cta-deco { position: absolute; border-radius: 50%; pointer-events: none; }
.cta-d1 {
  width: 600px; height: 600px; top: -200px; left: 50%; transform: translateX(-60%);
  background: radial-gradient(circle, rgba(0,200,232,.2) 0%, transparent 60%);
}
.cta-d2 {
  width: 400px; height: 400px; bottom: -150px; right: -80px;
  background: radial-gradient(circle, rgba(245,192,0,.12) 0%, transparent 65%);
}
.cta-d3 {
  width: 300px; height: 300px; top: 20%; left: -60px;
  background: radial-gradient(circle, rgba(30,125,232,.15) 0%, transparent 65%);
}

.cta-inner { position: relative; z-index: 2; max-width: 560px; margin: 0 auto; }
.cta-icon-wrap {
  width: 64px; height: 64px; border-radius: 20px;
  background: linear-gradient(135deg, #1447c0, #00c8e8);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 28px; box-shadow: 0 8px 24px rgba(0,200,232,.4);
}
.cta-icon { width: 32px; height: 32px; color: #fff; }
.cta-h2   { font-size: 2.5rem; font-weight: 900; color: #ffffff; letter-spacing: -.025em; margin-bottom: 16px; line-height: 1.15; }
.cta-p    { font-size: .9rem; color: #93c5fd; line-height: 1.75; margin-bottom: 36px; }
.cta-btn  {
  display: inline-flex; align-items: center; gap: 12px;
  padding: 16px 36px; background: #fff; color: #0a1040;
  border: none; border-radius: 16px; font-size: .95rem; font-weight: 700;
  cursor: pointer; box-shadow: 0 6px 20px rgba(0,0,0,.22);
  transition: transform .2s, box-shadow .2s;
}
.cta-btn:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(0,200,232,.25); }
.cta-btn:disabled { opacity: .6; cursor: not-allowed; }
.cta-note { font-size: .72rem; color: #7dd3fc; margin-top: 18px; }

/* ════════════════════════════════
   FOOTER
════════════════════════════════ */
.site-footer { background: #03071e; padding: 44px 32px; border-top: 1px solid #0d1f8a; }
.footer-inner {
  max-width: 1200px; margin: 0 auto;
  display: flex; align-items: center; justify-content: space-between;
  gap: 24px; flex-wrap: wrap;
}
.footer-brand { display: flex; align-items: center; gap: 14px; }
.footer-logo  { width: 40px; height: 40px; opacity: .55; }
.footer-name  { font-size: .88rem; font-weight: 700; color: #e2e8f0; letter-spacing: .06em; }
.footer-sub   { font-size: .64rem; color: #94a3b8; margin-top: 3px; line-height: 1.5; }
.footer-right { text-align: right; }
.footer-copy  { font-size: .72rem; color: #cbd5e1; }
.footer-ver   { font-size: .64rem; color: #94a3b8; margin-top: 4px; }

/* ════════════════════════════════
   RESPONSIVE
════════════════════════════════ */

/* ── Tablet (≤1100px) ─────────────────────── */
@media (max-width: 1100px) {
  .modules-grid { grid-template-columns: repeat(3, 1fr); }
}

/* ── Large tablet / small laptop (≤900px) ─── */
@media (max-width: 900px) {
  .hero-inner   { flex-direction: column; padding: 56px 24px 40px; gap: 36px; }
  .hero-right   { width: 100%; padding-top: 0; }
  .hero-left    { width: 100%; }
  .hero-h1      { font-size: 2.6rem; }
  .hero-p       { max-width: 100%; }
  .login-card   { max-width: 100%; }
  .pillars      { grid-template-columns: 1fr; gap: 18px; }
  .pillar       { padding: 28px 22px; }
  .modules-grid { grid-template-columns: repeat(2, 1fr); }
  .section      { padding: 72px 24px; }
  .section-hd   { margin-bottom: 48px; }
  .section-h2   { font-size: 2rem; }
  .stats-band   { padding: 56px 24px; }
  .cta-section  { padding: 72px 24px; }
  .cta-h2       { font-size: 2.1rem; }
}

/* ── Mobile (≤640px) ─────────────────────── */
@media (max-width: 640px) {
  /* Navbar */
  .nav-inner    { padding: 0 16px; height: 58px; gap: 12px; }
  .nav-links    { display: none; }
  .nav-sub      { display: none; }
  .nav-cta      { padding: 8px 14px; font-size: .78rem; }

  /* Hero */
  .hero         { min-height: auto; }
  .hero-inner   { padding: 44px 16px 32px; gap: 24px; flex-direction: column; }
  .hero-badge   { font-size: .65rem; padding: 5px 11px; margin-bottom: 20px; }
  .hero-h1      { font-size: 2rem; margin-bottom: 14px; }
  .hero-p       { font-size: .875rem; margin-bottom: 24px; }
  .login-card   { padding: 20px; max-width: 100%; }
  .lc-head      { gap: 10px; margin-bottom: 16px; }
  .lc-icon-wrap { width: 38px; height: 38px; }
  .lc-title     { font-size: .875rem; }
  .gbtn         { padding: 12px 14px; font-size: .82rem; gap: 10px; }
  .g-icon-wrap  { width: 30px; height: 30px; }
  .g-icon       { width: 18px; height: 18px; }

  /* Hero left (stats) */
  .hero-left    { gap: 12px; }
  .stats-grid   { grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .stat-card    { padding: 16px 12px; border-radius: 14px; }
  .stat-val     { font-size: 1.7rem; }
  .stat-lbl     { font-size: .6rem; }
  .chip-box     { display: none; } /* Covered by the Modules section */

  /* Sections */
  .section      { padding: 56px 16px; }
  .section-hd   { margin-bottom: 36px; }
  .section-h2   { font-size: 1.7rem; }
  .section-lead { font-size: .85rem; }

  /* Pillars */
  .pillar       { padding: 22px 18px; }
  .pillar-icon-wrap { width: 48px; height: 48px; border-radius: 14px; }
  .pillar-title { font-size: .95rem; }
  .pillar-desc  { font-size: .8rem; }

  /* Stats band */
  .stats-band   { padding: 44px 16px; }
  .sb-inner     { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px 16px; justify-items: center; }
  .sb-val       { font-size: 2.2rem; }
  .sb-lbl       { font-size: .65rem; }

  /* Modules */
  .modules-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .mod-card     { padding: 18px 14px; gap: 10px; border-radius: 14px; }
  .mod-icon-wrap{ width: 40px; height: 40px; border-radius: 12px; }
  .mod-icon     { width: 20px; height: 20px; }
  .mod-name     { font-size: .8rem; }
  .mod-desc     { font-size: .7rem; }

  /* CTA */
  .cta-section  { padding: 56px 16px; }
  .cta-icon-wrap{ width: 54px; height: 54px; border-radius: 16px; margin-bottom: 22px; }
  .cta-icon     { width: 26px; height: 26px; }
  .cta-h2       { font-size: 1.9rem; }
  .cta-p        { font-size: .85rem; margin-bottom: 28px; }
  .cta-btn      { padding: 14px 24px; font-size: .875rem; gap: 10px; }

  /* Footer */
  .site-footer  { padding: 32px 16px; }
  .footer-inner { flex-direction: column; text-align: center; gap: 16px; }
  .footer-brand { flex-direction: column; align-items: center; text-align: center; gap: 10px; }
  .footer-right { text-align: center; }
}

/* ── Small phones (≤380px) ────────────────── */
@media (max-width: 380px) {
  .hero-h1      { font-size: 1.75rem; }
  .hero-badge   { font-size: .6rem; }
  .modules-grid { grid-template-columns: 1fr; }
  .mod-card     { flex-direction: row; gap: 14px; padding: 16px 14px; }
  .mod-icon-wrap{ flex-shrink: 0; }
  .stat-val     { font-size: 1.5rem; }
}
</style>
