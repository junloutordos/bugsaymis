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
  ArrowTrendingUpIcon,
  SparklesIcon,
  RocketLaunchIcon,
  HandRaisedIcon,
} from '@heroicons/vue/24/outline'

const OFFICIAL_DOMAIN = '@crc.pshs.edu.ph'
const isLoading = ref(false)

const showAlert = (options) => Swal.fire({ confirmButtonColor: '#0867DB', ...options })
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

const brandPillars = [
  { icon: ArrowTrendingUpIcon, label: 'Direction', desc: 'The arrow mark points one way only — forward.' },
  { icon: SparklesIcon,        label: 'Clarity',   desc: 'A single shape, instantly recognizable at any size.' },
  { icon: RocketLaunchIcon,    label: 'Momentum',  desc: 'Built to move with PSHS-CRC into the future.' },
  { icon: HandRaisedIcon,      label: 'Trust',     desc: 'PSHS-backed, campus-owned, people-centered.' },
]
</script>

<template>
  <Head title="Atlas — Centralized MIS" />

  <div class="site">

    <!-- ════════════════════════════
         NAVBAR
    ═════════════════════════════ -->
    <header class="navbar">
      <div class="nav-inner">
        <div class="nav-brand">
          <img src="/images/atlas-logo-white.png" alt="Atlas" class="nav-logo" />
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
      <div class="deco deco-cyan" />
      <div class="deco deco-desc" />
      <img src="/images/atlas-mark.png" alt="" class="hero-mark-bg" aria-hidden="true" />
      <svg class="hero-route" viewBox="0 0 700 320" aria-hidden="true">
        <path d="M10,170 C160,40 340,280 690,80" />
      </svg>

      <div class="hero-inner">
        <!-- Left: hero text -->
        <div class="hero-left">
          <div class="hero-badge">
            <span class="badge-dot" />
            Philippine Science High School – Caraga Region Campus in Butuan City
          </div>

          <h1 class="hero-h1 font-heading">
            Charting the Way <br>
            <span class="hero-accent">Forward to Digital<br>Transformation.</span>
          </h1>

          <p class="hero-p">
            Atlas is the unified digital management platform of PSHS-CRC —
            consolidating HR, faculty loading, student services, and campus operations
            into one secure, cloud-native system backed by AWS and PSHS.
          </p>
        </div>

        <!-- Right: login card only -->
        <div id="hero-login" class="hero-right">
          <div class="lc-glow" />
          <div class="login-card">
            <div class="lc-content">
              <div class="lc-head">
                <div class="lc-icon-wrap">
                  <LockClosedIcon class="lc-icon" />
                </div>
                <div>
                  <p class="lc-title">Sign In to Atlas</p>
                  <p class="lc-sub">One login. Every tool you need today.</p>
                </div>
              </div>

              <div class="lc-divider" />

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

              <div class="lc-notice">
                <LockClosedIcon class="notice-icon" />
                <span>Only <strong>@crc.pshs.edu.ph</strong> accounts are authorized to sign in.</span>
              </div>
            </div>
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
          <p class="eyebrow">About Atlas</p>
          <h2 class="section-h2 font-heading">Built for PSHS-CRC.<br>Designed for everyone in it.</h2>
        </div>

        <div class="about-body">
          <p>
            <strong>Atlas</strong> is the Centralized Management Information System of the
            Philippine Science High School – Caraga Region Campus, designed to streamline
            operations, enhance collaboration, and support data-driven decision-making across
            the institution.
          </p>
          <p>
            Built and continuously developed in-house, Atlas serves as a unified digital
            ecosystem that connects students, parents, faculty, staff, and administrators
            through a centralized platform for academic, administrative, and institutional
            services.
          </p>
          <p>
            Inspired by the role of an atlas in providing direction and navigation, Atlas acts
            as the digital compass of the campus — guiding the community through a seamless,
            efficient, and future-ready digital experience. By bringing together information,
            processes, and services in one platform, Atlas reduces manual work, improves
            operational efficiency, and enables better access to information anytime and
            anywhere.
          </p>
          <p>
            More than an information system, Atlas represents the campus's commitment to
            innovation, continuous improvement, and digital transformation. As it evolves,
            Atlas is envisioned to become a comprehensive Government ERP platform that
            empowers educational institutions through intelligent, connected, and sustainable
            technology solutions.
          </p>

          <div class="about-signature">
            <span class="about-signature-name font-heading">Atlas</span>
            <span class="about-signature-tag">Charting the Way Forward to Digital Transformation.</span>
          </div>
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
         WHY ATLAS
    ═════════════════════════════ -->
    <section class="why-section">
      <div class="why-base" />
      <div class="why-deco" />
      <div class="why-inner">
        <div class="why-mark-wrap">
          <img src="/images/atlas-mark-white.png" alt="Atlas mark" class="why-mark-img" />
        </div>

        <div class="why-text">
          <p class="eyebrow eyebrow-dark">The Name</p>
          <h2 class="why-h2 font-heading">Why "Atlas"?</h2>
          <p class="why-p">
            The name <strong>Atlas</strong> was chosen to reflect the system's purpose as the
            central platform that guides, connects, and empowers the Philippine Science High
            School – Caraga Region Campus community.
          </p>
          <p class="why-p">
            Traditionally, an atlas is a collection of maps that helps people navigate, discover
            information, and find direction. In the same way, Atlas serves as the campus's
            digital compass — bringing together people, processes, data, and services into one
            unified and accessible platform.
          </p>
          <p class="why-p">
            The name embodies our vision of creating a connected, intelligent, and future-ready
            digital ecosystem where students, parents, faculty, staff, and administrators can
            seamlessly access the information and services they need.
          </p>
          <p class="why-p">
            Atlas also represents the institution's commitment to innovation, efficiency,
            collaboration, and continuous improvement. As the platform continues to grow, it
            will serve as the foundation for the campus's digital transformation journey and
            its future evolution into a comprehensive Government ERP platform.
          </p>
          <p class="why-p">
            More than a name, Atlas symbolizes direction, connectivity, and progress — guiding
            the Pisay community toward a smarter and more digitally empowered future.
          </p>

          <div class="why-pillars">
            <div v-for="p in brandPillars" :key="p.label" class="why-pillar">
              <component :is="p.icon" class="why-pillar-icon" />
              <div>
                <p class="why-pillar-label">{{ p.label }}</p>
                <p class="why-pillar-desc">{{ p.desc }}</p>
              </div>
            </div>
          </div>

          <div class="why-signature">
            <span class="why-signature-name font-heading">Atlas</span>
            <span class="why-signature-tag">Charting the Way Forward to Digital Transformation.</span>
          </div>
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
          <h2 class="section-h2 font-heading">Everything campus administration needs, unified.</h2>
          <p class="section-lead">
            Atlas covers all aspects of campus life — from personnel management and service requests
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
        <h2 class="cta-h2">Your Map to a Smarter PSHS.</h2>
        <p class="cta-p">Atlas handles the paperwork. You handle the science. Sign in with your official PSHS-CRC Google account to get started.</p>
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
          <img src="/images/atlas-mark.png" alt="Atlas" class="footer-logo" />
          <div>
            <p class="footer-name font-heading">Atlas</p>
            <p class="footer-sub">Centralized MIS</p>
            <p class="footer-sub">Philippine Science High School – Caraga Region Campus in Butuan City</p>
          </div>
        </div>
        <div class="footer-right">
          <p class="footer-copy">© 2026 PSHS-CRC. All rights reserved.</p>
          <p class="footer-ver">v{{ appVersion }}</p>
          <p class="footer-ver"><a href="/developer" class="dev-link">Developers' Information</a></p>
        </div>
      </div>
    </footer>

  </div>
</template>

<style scoped>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── CSS custom properties (Atlas brand palette) ─────────── */
.site {
  --navy:      #0A2A5E;
  --navy-2:    #073E85;
  --navy-dark: #061A3D;
  --blue:      #0867DB;
  --blue-lt:   #058FE0;
  --cyan:      #019FE6;
  --cyan-lt:   #92CBFC;
  --cyan-pale: #BFE0FD;
  --orange:    #FB9002;
  --gold:      #FDBE0E;
  --white:     #ffffff;
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
  background: rgba(10, 42, 94, 0.97);
  backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(1,159,230,.15);
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
.nav-brand { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.nav-logo  { width: auto; height: 30px; }

.nav-links { display: flex; gap: 4px; margin-left: auto; }
.nav-link  {
  padding: 7px 16px; font-size: .82rem; font-weight: 500;
  color: var(--cyan-pale); text-decoration: none; border-radius: 8px;
  transition: color .15s, background .15s;
}
.nav-link:hover { color: #fff; background: rgba(255,255,255,.09); }

.nav-cta {
  display: flex; align-items: center; gap: 6px;
  padding: 9px 20px;
  background: linear-gradient(135deg, var(--orange), var(--gold));
  color: #fff; border: none; border-radius: 10px;
  font-size: .82rem; font-weight: 700; cursor: pointer;
  box-shadow: 0 4px 16px rgba(251,144,2,.35);
  transition: opacity .15s, transform .15s, box-shadow .15s;
  flex-shrink: 0;
}
.nav-cta:hover { opacity: .9; transform: translateY(-1px); box-shadow: 0 6px 22px rgba(251,144,2,.45); }
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

/* White-dominant base with a soft brand-tinted glow */
.hero-base {
  position: absolute; inset: 0;
  background: var(--white);
}

/* Ambient glow elements */
.deco {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}
/* Soft cyan glow behind the headline */
.deco-cyan {
  width: 600px; height: 600px;
  top: -260px; left: -160px;
  background: radial-gradient(circle, rgba(1,159,230,.08) 0%, transparent 65%);
}
/* Soft glow behind the hero description paragraph */
.deco-desc {
  width: 380px; height: 380px;
  top: -10px; left: 40px;
  background: radial-gradient(circle, rgba(8,103,219,.08) 0%, transparent 65%);
}

/* Large, faint arrow-mark watermark in the hero background */
.hero-mark-bg {
  position: absolute;
  right: -40px; top: 50%; transform: translateY(-50%);
  height: 640px;
  opacity: .05;
  pointer-events: none;
}

/* Faint charted-route line behind the hero text column */
.hero-route {
  position: absolute;
  top: 10px; left: 30px;
  width: 620px; height: 300px;
  opacity: .09;
  pointer-events: none;
}
.hero-route path {
  fill: none;
  stroke: var(--blue);
  stroke-width: 2;
  stroke-dasharray: 6 10;
  stroke-linecap: round;
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
  background: #eaf4fe;
  border: 1px solid #cfe6fb;
  border-radius: 100px;
  font-size: .72rem; font-weight: 500; color: var(--navy-2);
  letter-spacing: .02em; margin-bottom: 28px;
}
.badge-dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--orange);
  flex-shrink: 0;
}

.hero-h1 {
  font-size: 3.6rem; font-weight: 900; color: var(--navy);
  line-height: 1.1; letter-spacing: -.03em; margin-bottom: 22px;
}
/* Wordmark gradient accent text (Blue → Cyan, per brand guide) */
.hero-accent {
  background: linear-gradient(90deg, var(--blue) 0%, var(--cyan) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-p {
  font-size: .95rem; color: #334155;
  line-height: 1.8; margin-bottom: 40px; max-width: 500px;
}

/* Login card */
.login-card {
  position: relative; overflow: hidden;
  background: var(--navy);
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px; padding: 32px; width: 100%;
  box-shadow: 0 28px 64px rgba(10,42,94,.28), inset 0 1px 0 rgba(255,255,255,.05);
}
.lc-content { position: relative; z-index: 1; }

/* Soft blue→cyan glow tying the card back to the headline accent */
.lc-glow {
  position: absolute; inset: -60px;
  background: radial-gradient(circle at 50% 40%, rgba(1,159,230,.18) 0%, transparent 70%);
  pointer-events: none; z-index: 0;
}

.lc-head { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }
.lc-icon-wrap {
  width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
  background: linear-gradient(135deg, var(--orange), var(--gold));
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 14px rgba(251,144,2,.35);
}
.lc-icon  { width: 22px; height: 22px; color: #fff; }
.lc-title { font-size: .95rem; font-weight: 700; color: #f0f9ff; }
.lc-sub   { font-size: .74rem; color: var(--cyan-lt); margin-top: 3px; }

.lc-divider { height: 1px; background: rgba(255,255,255,.1); margin-bottom: 22px; }

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
  box-shadow: 0 10px 30px rgba(1,159,230,.28);
}
.gbtn:active:not(:disabled) { transform: translateY(0); }
.gbtn:focus-visible { outline: 2px solid var(--cyan); outline-offset: 3px; }
.gbtn:disabled { opacity: .6; cursor: not-allowed; }
.g-icon-wrap {
  width: 36px; height: 36px; background: #f8fafc;
  border: 1px solid #e2e8f0; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.g-icon  { width: 20px; height: 20px; }
.g-label { flex: 1; text-align: left; }
.g-spin  { width: 16px; height: 16px; animation: spin .75s linear infinite; color: var(--blue); flex-shrink: 0; }
@keyframes spin { to { transform: rotate(360deg); } }

.lc-notice {
  display: flex; align-items: flex-start; gap: 8px;
  background: rgba(1,159,230,.1); border: 1px solid rgba(1,159,230,.22);
  border-radius: 12px; padding: 10px 12px;
  font-size: .72rem; color: var(--cyan-lt); margin-top: 18px; line-height: 1.5;
}
.lc-notice strong { white-space: nowrap; }
.notice-icon { width: 13px; height: 13px; flex-shrink: 0; color: var(--cyan); }

.dev-link {
  font-size: .64rem; color: #4a5568;
  text-decoration: none; letter-spacing: .03em;
  transition: color .2s;
}
.dev-link:hover { color: var(--cyan-lt); }

/* ── Hero left (hero text) ─────────── */
.hero-left { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 20px; }

/* ── Hero right (login card only) ─────────── */
/* padding-top = badge height (~30px) + badge margin-bottom (28px) to align with h1 */
.hero-right { position: relative; width: 380px; flex-shrink: 0; padding-top: 58px; }

/* Scroll hint */
.scroll-hint {
  position: relative; z-index: 2;
  display: flex; flex-direction: column; align-items: center; gap: 5px;
  padding-bottom: 32px;
}
.scroll-text { font-size: .68rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .1em; }
.scroll-icon { width: 20px; height: 20px; color: var(--blue); }

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
  background: linear-gradient(90deg, var(--blue), var(--cyan));
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
   ABOUT BODY
════════════════════════════════ */
.about-body {
  max-width: 720px; margin: 0 auto 56px;
}
.about-body p {
  font-size: .9rem; color: #334155; line-height: 1.8;
  margin-bottom: 18px;
}
.about-body p strong { color: #0a1040; }
.about-signature {
  text-align: center; margin-top: 36px; padding-top: 28px;
  border-top: 1px solid #e2e8f0;
}
.about-signature-name {
  display: block; font-size: 1.3rem; font-weight: 800; color: var(--navy);
  letter-spacing: -.01em;
}
.about-signature-tag {
  display: block; font-size: .8rem; font-style: italic; color: #64748b;
  margin-top: 4px;
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
.pillar:hover { box-shadow: 0 14px 32px rgba(8,103,219,.12); transform: translateY(-3px); }
.pillar-icon-wrap {
  width: 56px; height: 56px; border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 20px; box-shadow: 0 6px 18px rgba(0,0,0,.18);
}
.pillar-icon  { width: 28px; height: 28px; color: #fff; }
.pillar-title { font-size: 1.05rem; font-weight: 700; color: #0a1040; margin-bottom: 12px; }
.pillar-desc  { font-size: .83rem; color: #334155; line-height: 1.75; }

/* ════════════════════════════════
   WHY ATLAS
════════════════════════════════ */
.why-section { position: relative; padding: 96px 32px; overflow: hidden; }
.why-base {
  position: absolute; inset: 0;
  background: linear-gradient(130deg, var(--navy) 0%, var(--navy-2) 45%, var(--blue) 100%);
}
.why-deco {
  position: absolute; border-radius: 50%; pointer-events: none;
  width: 460px; height: 460px; right: -140px; top: -160px;
  background: radial-gradient(circle, rgba(1,159,230,.18) 0%, transparent 65%);
}
.why-inner {
  position: relative; z-index: 2;
  max-width: 1100px; margin: 0 auto;
  display: flex; align-items: center; gap: 64px;
}
.why-mark-wrap {
  flex-shrink: 0; width: 220px; display: flex; justify-content: center;
}
.why-mark-img { width: 140px; height: auto; opacity: .92; }
.why-text { flex: 1; min-width: 0; }
.eyebrow-dark { color: var(--cyan-lt); -webkit-text-fill-color: var(--cyan-lt); background: none; }
.why-h2 { font-size: 2rem; font-weight: 800; color: #ffffff; letter-spacing: -.02em; margin-bottom: 18px; }
.why-p { font-size: .88rem; color: var(--cyan-pale); line-height: 1.8; margin-bottom: 14px; max-width: 640px; }
.why-pillars {
  display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px 28px;
  margin-top: 28px;
}
.why-pillar { display: flex; align-items: flex-start; gap: 12px; }
.why-pillar-icon { width: 22px; height: 22px; color: var(--gold); flex-shrink: 0; margin-top: 1px; }
.why-pillar-label { font-size: .82rem; font-weight: 700; color: #ffffff; }
.why-pillar-desc { font-size: .76rem; color: var(--cyan-lt); line-height: 1.55; margin-top: 2px; }

.why-signature {
  margin-top: 36px; padding-top: 26px;
  border-top: 1px solid rgba(255,255,255,.12);
}
.why-signature-name {
  display: block; font-size: 1.2rem; font-weight: 800; color: #ffffff;
  letter-spacing: -.01em;
}
.why-signature-tag {
  display: block; font-size: .78rem; font-style: italic; color: var(--cyan-lt);
  margin-top: 4px;
}

/* ════════════════════════════════
   MODULES
════════════════════════════════ */
.modules-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 22px; }
.mod-card {
  background: #fff; border: 1px solid #dbeafe; border-radius: 18px;
  padding: 26px 22px; display: flex; flex-direction: column; gap: 14px;
  transition: box-shadow .25s, transform .25s, border-color .25s;
}
.mod-card:hover { box-shadow: 0 10px 24px rgba(8,103,219,.12); transform: translateY(-2px); border-color: var(--cyan-lt); }
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
  background: linear-gradient(160deg, var(--navy) 0%, var(--navy-2) 40%, var(--blue) 70%, var(--blue-lt) 100%);
}
.cta-deco { position: absolute; border-radius: 50%; pointer-events: none; }
.cta-d1 {
  width: 600px; height: 600px; top: -200px; left: 50%; transform: translateX(-60%);
  background: radial-gradient(circle, rgba(1,159,230,.2) 0%, transparent 60%);
}
.cta-d2 {
  width: 400px; height: 400px; bottom: -150px; right: -80px;
  background: radial-gradient(circle, rgba(253,190,14,.12) 0%, transparent 65%);
}
.cta-d3 {
  width: 300px; height: 300px; top: 20%; left: -60px;
  background: radial-gradient(circle, rgba(8,103,219,.15) 0%, transparent 65%);
}

.cta-inner { position: relative; z-index: 2; max-width: 560px; margin: 0 auto; }
.cta-icon-wrap {
  width: 64px; height: 64px; border-radius: 20px;
  background: linear-gradient(135deg, var(--blue), var(--cyan));
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 28px; box-shadow: 0 8px 24px rgba(1,159,230,.4);
}
.cta-icon { width: 32px; height: 32px; color: #fff; }
.cta-h2   { font-size: 2.5rem; font-weight: 900; color: #ffffff; letter-spacing: -.025em; margin-bottom: 16px; line-height: 1.15; }
.cta-p    { font-size: .9rem; color: var(--cyan-lt); line-height: 1.75; margin-bottom: 36px; }
.cta-btn  {
  display: inline-flex; align-items: center; gap: 12px;
  padding: 16px 36px; background: #fff; color: #0a1040;
  border: none; border-radius: 16px; font-size: .95rem; font-weight: 700;
  cursor: pointer; box-shadow: 0 6px 20px rgba(0,0,0,.22);
  transition: transform .2s, box-shadow .2s;
}
.cta-btn:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(1,159,230,.25); }
.cta-btn:disabled { opacity: .6; cursor: not-allowed; }
.cta-note { font-size: .72rem; color: var(--cyan-lt); margin-top: 18px; }

/* ════════════════════════════════
   FOOTER
════════════════════════════════ */
.site-footer { background: var(--navy-dark); padding: 44px 32px; border-top: 1px solid var(--navy-2); }
.footer-inner {
  max-width: 1200px; margin: 0 auto;
  display: flex; align-items: center; justify-content: space-between;
  gap: 24px; flex-wrap: wrap;
}
.footer-brand { display: flex; align-items: center; gap: 14px; }
.footer-logo  { width: auto; height: 40px; opacity: .55; }
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
  .why-section  { padding: 64px 24px; }
  .why-inner    { flex-direction: column; gap: 32px; text-align: center; }
  .why-mark-wrap{ width: auto; }
  .why-p        { max-width: 100%; }
  .why-pillars  { text-align: left; }
  .cta-section  { padding: 72px 24px; }
  .cta-h2       { font-size: 2.1rem; }
}

/* ── Mobile (≤640px) ─────────────────────── */
@media (max-width: 640px) {
  /* Navbar */
  .nav-inner    { padding: 0 16px; height: 58px; gap: 12px; }
  .nav-links    { display: none; }
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

  /* Hero left */
  .hero-left    { gap: 12px; }

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

  /* Why Atlas */
  .why-section    { padding: 40px 16px; }
  .why-mark-img   { width: 96px; }
  .why-h2         { font-size: 1.6rem; }
  .why-p          { font-size: .82rem; }
  .why-pillars    { grid-template-columns: 1fr; gap: 14px; }

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
}
</style>
