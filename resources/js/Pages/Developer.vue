<script setup>
import { Head } from '@inertiajs/vue3'
import {
  CodeBracketIcon,
  EnvelopeIcon,
  PhoneIcon,
  GlobeAltIcon,
  ArrowLeftIcon,
  ServerIcon,
  CircleStackIcon,
  CloudIcon,
  CpuChipIcon,
  ShieldCheckIcon,
  BoltIcon,
} from '@heroicons/vue/24/outline'

const team = [
  {
    icon: CodeBracketIcon,
    name: 'Junlou Tordos',
    role: 'Lead Developer & System Architect',
    bio: 'Designed and built Atlas from the ground up — from the data layer and backend API to the cloud infrastructure and CI/CD pipeline. Specializes in building practical, role-based management platforms for Philippine government institutions.',
    contacts: [
      { icon: EnvelopeIcon, label: 'jtordos@crc.pshs.edu.ph',  href: 'mailto:jtordos@crc.pshs.edu.ph' },
      { icon: PhoneIcon,    label: '+63 955 559 8885',        href: 'tel:+639555598885' },
      { icon: GlobeAltIcon, label: 'LinkedIn',                href: 'https://www.linkedin.com/in/junlou-tordos-9538a9307', external: true },
    ],
    accent: 'linear-gradient(135deg, #1447c0, #00c8e8)',
  },
  {
    icon: CpuChipIcon,
    name: 'Michael Francisco',
    role: 'Support Developer & System Analyst',
    bio: 'Responsible for system analysis, requirements gathering, quality assurance, and end-user support. Works closely with campus stakeholders to ensure Atlas aligns with the operational and administrative needs of PSHS-CRC.',
    contacts: [
      { icon: EnvelopeIcon, label: 'mafrancisco@crc.pshs.edu.ph', href: 'mailto:mafrancisco@crc.pshs.edu.ph' },
    ],
    accent: 'linear-gradient(135deg, #047857, #0891b2)',
  },
]

const modules = [
  'Human Resources', 'Faculty Loading', 'Class Records', 'Payroll',
  'Document Tracking', 'Recruitment', 'IPCR / PMS', 'Library',
  'Student Attendance', 'Service Requests', 'SALN', 'Health & Guidance',
]

const stack = [
  { icon: CodeBracketIcon, label: 'Laravel 12 / PHP 8.4', desc: 'Backend framework, RBAC, queues, and scheduled jobs' },
  { icon: CpuChipIcon,     label: 'Vue 3 + Inertia.js',   desc: 'Reactive SPA frontend without a separate API layer' },
  { icon: CircleStackIcon, label: 'MySQL 8.0 on AWS RDS',  desc: 'Multi-AZ, encrypted at rest, automated PITR backups' },
  { icon: CloudIcon,       label: 'AWS ECS Fargate',       desc: 'Containerized production, CI/CD, zero-downtime deploys' },
  { icon: BoltIcon,        label: 'Soketi + Pusher',       desc: 'Self-hosted WebSocket server for real-time notifications' },
  { icon: ShieldCheckIcon, label: 'Cloudflare WAF',        desc: 'DDoS protection, TLS 1.3, rate limiting, orange-cloud proxy' },
]
</script>

<template>
  <Head title="Development Team — Atlas" />
  <div class="page">

    <!-- ── Nav ───────────────────────────────────────── -->
    <header class="dev-nav">
      <div class="nav-inner">
        <a href="/" class="back-link">
          <ArrowLeftIcon class="back-icon" />
          Back to Atlas
        </a>
      </div>
    </header>

    <!-- ── Team Hero ─────────────────────────────────── -->
    <section class="hero">
      <div class="hero-base" />
      <div class="hero-glow1" />
      <div class="hero-glow2" />
      <div class="hero-inner">
        <div class="hero-head">
          <p class="hero-eyebrow">PSHS-CRC · Atlas</p>
          <h1 class="hero-title">Development Team</h1>
          <p class="hero-sub">The people behind the Campus Resource and Management Information System.</p>
        </div>
        <div class="team-grid">
          <div v-for="member in team" :key="member.name" class="member-card">
            <div class="member-top">
              <div class="avatar-ring" :style="{ background: member.accent }">
                <div class="avatar-bg">
                  <component :is="member.icon" class="avatar-icon" />
                </div>
              </div>
              <div class="member-meta">
                <p class="member-role">{{ member.role }}</p>
                <h2 class="member-name">{{ member.name }}</h2>
              </div>
            </div>
            <p class="member-bio">{{ member.bio }}</p>
            <div class="contacts">
              <a
                v-for="c in member.contacts"
                :key="c.label"
                :href="c.href"
                :target="c.external ? '_blank' : undefined"
                :rel="c.external ? 'noopener noreferrer' : undefined"
                class="contact-pill"
              >
                <component :is="c.icon" class="cp-icon" />
                {{ c.label }}
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Featured Project ──────────────────────────── -->
    <section class="section bg-white">
      <div class="section-inner">
        <p class="eyebrow">Featured Project</p>
        <h2 class="section-h2">Atlas</h2>
        <p class="section-lead">Campus Resource and Management Information System — PSHS-CRC, Butuan City</p>
        <div class="project-card">
          <div class="pc-meta">
            <div class="meta-row">
              <span class="meta-k">Client</span>
              <span class="meta-v">Philippine Science High School – Caraga Region Campus</span>
            </div>
            <div class="meta-row">
              <span class="meta-k">Type</span>
              <span class="meta-v">Campus Management Information System</span>
            </div>
            <div class="meta-row">
              <span class="meta-k">Users</span>
              <span class="meta-v">500+ (faculty, staff, students, administrators)</span>
            </div>
            <div class="meta-row">
              <span class="meta-k">Status</span>
              <span class="meta-v meta-active">Active · Production</span>
            </div>
          </div>
          <p class="pc-desc">
            A comprehensive, unified platform covering all aspects of campus administration —
            designed from the ground up for PSHS-CRC's specific workflows. CSC-compliant leave
            management, Salary Standardization Law schedules, AI-assisted faculty loading,
            biometric gate attendance, and more. Deployed on AWS ECS Fargate with multi-AZ RDS,
            Cloudflare WAF, and automated daily encrypted backups offloaded to Google Drive.
          </p>
          <div class="pc-chips">
            <span v-for="m in modules" :key="m" class="pc-chip">{{ m }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Tech Stack ─────────────────────────────────── -->
    <section class="section bg-dark">
      <div class="section-inner">
        <p class="eyebrow eyebrow-light">Technology Stack</p>
        <h2 class="section-h2 section-h2-light">Built with modern, proven tools.</h2>
        <div class="stack-grid">
          <div v-for="s in stack" :key="s.label" class="stack-card">
            <div class="sc-icon-wrap">
              <component :is="s.icon" class="sc-icon" />
            </div>
            <p class="sc-label">{{ s.label }}</p>
            <p class="sc-desc">{{ s.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Footer ─────────────────────────────────────── -->
    <footer class="dev-footer">
      <p class="df-copy">© 2026 PSHS-CRC Development Team</p>
      <a href="/" class="df-back">← Back to Atlas</a>
    </footer>

  </div>
</template>

<style scoped>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.page {
  font-family: 'Inter', system-ui, Arial, sans-serif;
  color: #0f172a;
  background: #fff;
}

/* ── Nav ── */
.dev-nav {
  position: sticky; top: 0; z-index: 100;
  background: rgba(6,14,80,.97);
  backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(0,200,232,.15);
}
.nav-inner {
  max-width: 1000px; margin: 0 auto;
  padding: 0 32px; height: 60px;
  display: flex; align-items: center;
}
.back-link {
  display: inline-flex; align-items: center; gap: 7px;
  font-size: .8rem; font-weight: 600;
  color: #93c5fd; text-decoration: none;
  transition: color .15s;
}
.back-link:hover { color: #fff; }
.back-icon { width: 15px; height: 15px; }

/* ── Hero ── */
.hero { position: relative; padding: 80px 32px 100px; overflow: hidden; }
.hero-base {
  position: absolute; inset: 0;
  background: linear-gradient(145deg, #060e50 0%, #0d1f8a 35%, #1447c0 65%, #1a5cd8 100%);
}
.hero-glow1 {
  position: absolute; border-radius: 50%;
  width: 500px; height: 500px; top: -150px; right: -80px;
  background: radial-gradient(circle, rgba(0,200,232,.18) 0%, transparent 65%);
  pointer-events: none;
}
.hero-glow2 {
  position: absolute; border-radius: 50%;
  width: 320px; height: 320px; bottom: -80px; left: 4%;
  background: radial-gradient(circle, rgba(245,192,0,.1) 0%, transparent 65%);
  pointer-events: none;
}
.hero-inner {
  position: relative; z-index: 2;
  max-width: 1000px; margin: 0 auto;
  display: flex; flex-direction: column; gap: 48px;
}
.hero-head { text-align: center; }
.hero-eyebrow {
  font-size: .7rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: .12em;
  color: #00c8e8; margin-bottom: 10px;
}
.hero-title {
  font-size: 2.8rem; font-weight: 900; color: #fff;
  letter-spacing: -.03em; line-height: 1.1; margin-bottom: 14px;
}
.hero-sub { font-size: .88rem; color: #bfdbfe; line-height: 1.7; }

/* ── Team grid ── */
.team-grid {
  display: grid; grid-template-columns: repeat(2, 1fr);
  gap: 24px;
}
.member-card {
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.13);
  border-radius: 20px; padding: 28px;
  backdrop-filter: blur(8px);
  display: flex; flex-direction: column; gap: 18px;
  transition: background .2s, border-color .2s;
}
.member-card:hover { background: rgba(255,255,255,.1); border-color: rgba(0,200,232,.3); }
.member-top { display: flex; align-items: center; gap: 16px; }
.avatar-ring {
  flex-shrink: 0;
  width: 64px; height: 64px; border-radius: 50%;
  padding: 2.5px;
  box-shadow: 0 0 28px rgba(0,200,232,.35);
}
.avatar-bg {
  width: 100%; height: 100%; border-radius: 50%;
  background: #0d1f8a;
  display: flex; align-items: center; justify-content: center;
}
.avatar-icon { width: 28px; height: 28px; color: #00c8e8; }
.member-meta { min-width: 0; }
.member-role {
  font-size: .68rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: .08em;
  color: #00c8e8; margin-bottom: 4px;
}
.member-name {
  font-size: 1.2rem; font-weight: 800; color: #fff;
  letter-spacing: -.02em; line-height: 1.2;
}
.member-bio {
  font-size: .82rem; color: #bfdbfe; line-height: 1.8;
}
.contacts { display: flex; flex-wrap: wrap; gap: 8px; }
.contact-pill {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 12px;
  background: rgba(255,255,255,.09);
  border: 1px solid rgba(255,255,255,.17);
  border-radius: 100px;
  font-size: .72rem; font-weight: 500; color: #e0f2fe;
  text-decoration: none;
  transition: background .15s, border-color .15s;
}
.contact-pill:hover { background: rgba(0,200,232,.18); border-color: rgba(0,200,232,.45); }
.cp-icon { width: 13px; height: 13px; flex-shrink: 0; color: #00c8e8; }

/* ── Sections ── */
.section { padding: 88px 32px; }
.bg-white { background: #fff; }
.bg-dark {
  background: linear-gradient(160deg, #060e50 0%, #0d1f8a 45%, #1447c0 100%);
}
.section-inner { max-width: 1000px; margin: 0 auto; }
.eyebrow {
  display: inline-block;
  font-size: .7rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .12em; margin-bottom: 10px;
  background: linear-gradient(90deg, #1447c0, #00c8e8);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.eyebrow-light {
  background: none !important;
  -webkit-text-fill-color: #00c8e8 !important;
  color: #00c8e8;
}
.section-h2 {
  font-size: 2rem; font-weight: 800; color: #0a1040;
  letter-spacing: -.025em; margin-bottom: 8px;
}
.section-h2-light { color: #fff; }
.section-lead { font-size: .88rem; color: #334155; margin-bottom: 40px; }

/* ── Project card ── */
.project-card {
  border: 1px solid #dbeafe; border-radius: 20px; padding: 32px;
  box-shadow: 0 4px 24px rgba(20,71,192,.07);
}
.pc-meta {
  display: grid; grid-template-columns: repeat(2, 1fr);
  gap: 14px 32px; margin-bottom: 24px;
}
.meta-row { display: flex; flex-direction: column; gap: 3px; }
.meta-k {
  font-size: .65rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .09em; color: #64748b;
}
.meta-v { font-size: .82rem; color: #1e293b; font-weight: 500; }
.meta-active { color: #059669; font-weight: 700; }
.pc-desc { font-size: .84rem; color: #334155; line-height: 1.8; margin-bottom: 22px; }
.pc-chips { display: flex; flex-wrap: wrap; gap: 7px; }
.pc-chip {
  display: inline-block; padding: 4px 11px;
  background: #eff6ff; border: 1px solid #bfdbfe;
  border-radius: 100px;
  font-size: .68rem; font-weight: 600; color: #1e40af;
}

/* ── Stack ── */
.stack-grid {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: 20px; margin-top: 44px;
}
.stack-card {
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 18px; padding: 26px 22px;
  transition: background .2s, border-color .2s;
}
.stack-card:hover { background: rgba(0,200,232,.1); border-color: rgba(0,200,232,.3); }
.sc-icon-wrap {
  width: 44px; height: 44px; border-radius: 12px;
  background: linear-gradient(135deg, #1447c0, #00c8e8);
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 14px; box-shadow: 0 4px 14px rgba(0,200,232,.3);
}
.sc-icon  { width: 22px; height: 22px; color: #fff; }
.sc-label { font-size: .875rem; font-weight: 700; color: #e0f2fe; margin-bottom: 6px; }
.sc-desc  { font-size: .76rem; color: #93c5fd; line-height: 1.65; }

/* ── Footer ── */
.dev-footer {
  background: #03071e;
  border-top: 1px solid #0d1f8a;
  padding: 32px;
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 12px;
}
.df-copy { font-size: .72rem; color: #94a3b8; }
.df-back {
  font-size: .72rem; color: #93c5fd;
  text-decoration: none; transition: color .15s;
}
.df-back:hover { color: #fff; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .team-grid  { grid-template-columns: 1fr; }
  .hero-title { font-size: 2.2rem; }
  .stack-grid { grid-template-columns: repeat(2, 1fr); }
  .pc-meta    { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
  .hero       { padding: 56px 20px 72px; }
  .section    { padding: 64px 20px; }
  .hero-title { font-size: 1.9rem; }
  .stack-grid { grid-template-columns: 1fr; }
  .project-card { padding: 22px 18px; }
}
</style>
