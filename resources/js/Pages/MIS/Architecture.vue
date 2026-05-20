<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PrinterIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline'

function printDiagram() {
  window.print()
}
</script>

<template>
  <Head title="System Architecture" />
  <AdminLayout title="System Architecture">

    <!-- Page header -->
    <div class="flex items-center justify-between mb-5">
      <div>
        <h1 class="text-xl font-bold text-slate-800">System Architecture</h1>
        <p class="text-sm text-slate-400 mt-0.5">CRCMIS (crcmis-prod) — AWS Deployment Architecture</p>
      </div>
      <button
        @click="printDiagram"
        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
      >
        <PrinterIcon class="w-4 h-4" />
        Print / Export PDF
      </button>
    </div>

    <!-- Diagram container -->
    <div class="arch-wrap">
      <div class="arch-scroll">
<svg width="1366" height="880" viewBox="0 0 1366 880"
     xmlns="http://www.w3.org/2000/svg"
     font-family="-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">

<!-- ══════════════════════════════════════════════════════════════
     DEFS
══════════════════════════════════════════════════════════════ -->
<defs>
  <marker id="mo" markerWidth="8" markerHeight="8" refX="7" refY="3.5" orient="auto">
    <path d="M0,0 L7,3.5 L0,7 Z" fill="#E07B00"/>
  </marker>
  <marker id="mb" markerWidth="8" markerHeight="8" refX="7" refY="3.5" orient="auto">
    <path d="M0,0 L7,3.5 L0,7 Z" fill="#1A73E8"/>
  </marker>
  <marker id="mg" markerWidth="8" markerHeight="8" refX="7" refY="3.5" orient="auto">
    <path d="M0,0 L7,3.5 L0,7 Z" fill="#1E8449"/>
  </marker>
  <marker id="mp" markerWidth="8" markerHeight="8" refX="7" refY="3.5" orient="auto">
    <path d="M0,0 L7,3.5 L0,7 Z" fill="#7B2FBE"/>
  </marker>
  <marker id="mob" markerWidth="8" markerHeight="8" refX="1" refY="3.5" orient="auto">
    <path d="M7,0 L0,3.5 L7,7 Z" fill="#E07B00"/>
  </marker>
  <filter id="card-shadow">
    <feDropShadow dx="0" dy="1" stdDeviation="2.5" flood-color="#00000018"/>
  </filter>
  <filter id="sec-shadow">
    <feDropShadow dx="0" dy="2" stdDeviation="4" flood-color="#00000012"/>
  </filter>

  <!-- ══════════ SERVICE ICON SYMBOLS (viewBox centred at 0,0) ══════════ -->

  <!-- RDS: stacked cylinders -->
  <symbol id="i-rds" viewBox="-12 -12 24 24">
    <ellipse cx="0" cy="-6" rx="8.5" ry="2.8" fill="white"/>
    <path d="M-8.5,-6 L-8.5,6" stroke="white" stroke-width=".4" opacity=".4"/>
    <path d="M8.5,-6 L8.5,6"  stroke="white" stroke-width=".4" opacity=".4"/>
    <ellipse cx="0" cy="0"  rx="8.5" ry="2.8" fill="white" opacity=".45"/>
    <ellipse cx="0" cy="6"  rx="8.5" ry="2.8" fill="white"/>
  </symbol>

  <!-- ElastiCache / Redis: lightning bolt -->
  <symbol id="i-redis" viewBox="-12 -12 24 24">
    <path d="M3.5,-11 L-5,1 L-.5,1 L-3.5,11 L5,-1 L.5,-1 Z" fill="white"/>
  </symbol>

  <!-- S3: bucket -->
  <symbol id="i-s3" viewBox="-12 -12 24 24">
    <ellipse cx="0" cy="-7" rx="8" ry="3" fill="white"/>
    <path d="M-8,-7 L-6,9 Q-6,11 0,11 Q6,11 6,9 L8,-7" fill="white" opacity=".3"/>
    <ellipse cx="0" cy="9"  rx="6" ry="2" fill="white" opacity=".8"/>
    <path d="M-8,-7 L-8,6" stroke="white" stroke-width=".6" opacity=".5"/>
    <path d="M8,-7  L8,6"  stroke="white" stroke-width=".6" opacity=".5"/>
    <ellipse cx="0" cy="-7" rx="8" ry="3" fill="none" stroke="white" stroke-width=".8" opacity=".6"/>
  </symbol>

  <!-- CloudWatch: eye -->
  <symbol id="i-cw" viewBox="-12 -12 24 24">
    <path d="M-10,0 Q-5,-8.5 0,-8.5 Q5,-8.5 10,0 Q5,8.5 0,8.5 Q-5,8.5 -10,0 Z" fill="white" opacity=".85"/>
    <circle cx="0" cy="0" r="3.5" fill="white"/>
    <circle cx="0" cy="0" r="1.5" fill="#E05243"/>
  </symbol>

  <!-- SSM / Secrets: shield with lock -->
  <symbol id="i-ssm" viewBox="-12 -12 24 24">
    <path d="M0,-11 L9,-7 L9,2 Q9,8.5 0,11 Q-9,8.5 -9,2 L-9,-7 Z" fill="white" opacity=".85"/>
    <rect x="-3" y="-1" width="6" height="5.5" rx="1" fill="#E05243"/>
    <path d="M-2.5,-1 C-2.5,-4.5 2.5,-4.5 2.5,-1" fill="none" stroke="#E05243" stroke-width="1.5"/>
    <circle cx="0" cy="1.5" r=".8" fill="white"/>
  </symbol>

  <!-- IAM: person silhouette -->
  <symbol id="i-iam" viewBox="-12 -12 24 24">
    <circle cx="0" cy="-5" r="5" fill="white"/>
    <path d="M-8,10 Q-8,0 0,0 Q8,0 8,10 Z" fill="white"/>
  </symbol>

  <!-- ECR: layered container images -->
  <symbol id="i-ecr" viewBox="-12 -12 24 24">
    <rect x="-9" y="-10" width="18" height="5.5" rx="2" fill="white"/>
    <rect x="-9" y="-3"  width="18" height="5.5" rx="2" fill="white" opacity=".7"/>
    <rect x="-9" y="4"   width="18" height="5.5" rx="2" fill="white" opacity=".45"/>
  </symbol>

  <!-- KMS: key -->
  <symbol id="i-kms" viewBox="-12 -12 24 24">
    <circle cx="-3.5" cy="-1" r="5.5" fill="none" stroke="white" stroke-width="2.5"/>
    <circle cx="-3.5" cy="-1" r="2" fill="white"/>
    <rect  x="2"   y="-2"   width="10"  height="3" rx="1" fill="white"/>
    <rect  x="8.5" y="-2"   width="3"   height="5" rx="1" fill="white"/>
    <rect  x="5.5" y="-2"   width="3"   height="4" rx="1" fill="white"/>
  </symbol>

  <!-- SNS: bell -->
  <symbol id="i-sns" viewBox="-12 -12 24 24">
    <path d="M0,-11 C-1.5,-11 -7,-7 -7,-1 L-9,5 L9,5 L7,-1 C7,-7 1.5,-11 0,-11 Z" fill="white"/>
    <ellipse cx="0" cy="7.5" rx="3.2" ry="2" fill="white"/>
  </symbol>

  <!-- CloudTrail: magnifying glass -->
  <symbol id="i-trail" viewBox="-12 -12 24 24">
    <circle cx="-2" cy="-2" r="7" fill="none" stroke="white" stroke-width="2.5"/>
    <line x1="3.5"  y1="3.5"  x2="10" y2="10"  stroke="white" stroke-width="2.5" stroke-linecap="round"/>
    <line x1="-4.5" y1="-2"   x2=".5" y2="-2"  stroke="white" stroke-width="1.5" opacity=".6"/>
    <line x1="-2"   y1="-4.5" x2="-2" y2=".5"  stroke="white" stroke-width="1.5" opacity=".6"/>
  </symbol>

  <!-- ALB: load-balancer nodes -->
  <symbol id="i-alb" viewBox="-12 -12 24 24">
    <circle cx="0"  cy="0"  r="3.5" fill="white"/>
    <circle cx="-8" cy="-7" r="2.2" fill="white" opacity=".85"/>
    <circle cx="8"  cy="-7" r="2.2" fill="white" opacity=".85"/>
    <circle cx="-8" cy="7"  r="2.2" fill="white" opacity=".85"/>
    <circle cx="8"  cy="7"  r="2.2" fill="white" opacity=".85"/>
    <line x1="-6"  y1="-5.5" x2="-2.5" y2="-2.5" stroke="white" stroke-width="1.5"/>
    <line x1="6"   y1="-5.5" x2="2.5"  y2="-2.5" stroke="white" stroke-width="1.5"/>
    <line x1="-6"  y1="5.5"  x2="-2.5" y2="2.5"  stroke="white" stroke-width="1.5"/>
    <line x1="6"   y1="5.5"  x2="2.5"  y2="2.5"  stroke="white" stroke-width="1.5"/>
  </symbol>

  <!-- ECS: 4 container boxes -->
  <symbol id="i-ecs" viewBox="-12 -12 24 24">
    <rect x="-11" y="-11" width="9" height="9" rx="2" fill="white"/>
    <rect x="2"   y="-11" width="9" height="9" rx="2" fill="white" opacity=".75"/>
    <rect x="-11" y="2"   width="9" height="9" rx="2" fill="white" opacity=".75"/>
    <rect x="2"   y="2"   width="9" height="9" rx="2" fill="white" opacity=".55"/>
  </symbol>

  <!-- nginx: bold N strokes -->
  <symbol id="i-nginx" viewBox="-12 -12 24 24">
    <path d="M-7,-9 L-7,9 L7,-9 L7,9" fill="none" stroke="white" stroke-width="3.8" stroke-linecap="round" stroke-linejoin="round"/>
  </symbol>

  <!-- PHP-FPM: angle brackets / code -->
  <symbol id="i-php" viewBox="-12 -12 24 24">
    <path d="M-4,-9 L-10,0 L-4,9"  fill="none" stroke="white" stroke-width="2.8" stroke-linecap="round"/>
    <path d="M4,-9  L10,0  L4,9"   fill="none" stroke="white" stroke-width="2.8" stroke-linecap="round"/>
  </symbol>

  <!-- Queue Worker: stacked lines (queue) -->
  <symbol id="i-queue" viewBox="-12 -12 24 24">
    <rect x="-9" y="-8" width="18" height="4" rx="2" fill="white"/>
    <rect x="-9" y="-2" width="13" height="4" rx="2" fill="white" opacity=".72"/>
    <rect x="-9" y="4"  width="8"  height="4" rx="2" fill="white" opacity=".48"/>
    <path d="M6,4 L9,6 L6,8" fill="none" stroke="white" stroke-width="1.5" stroke-linecap="round" opacity=".7"/>
  </symbol>

  <!-- Cron / Supervisord: clock -->
  <symbol id="i-cron" viewBox="-12 -12 24 24">
    <circle cx="0" cy="0" r="10" fill="none" stroke="white" stroke-width="2.5"/>
    <line x1="0" y1="0" x2="0"  y2="-7" stroke="white" stroke-width="2.2" stroke-linecap="round"/>
    <line x1="0" y1="0" x2="5.5" y2="3" stroke="white" stroke-width="2.2" stroke-linecap="round"/>
    <circle cx="0" cy="0" r="1.5" fill="white"/>
  </symbol>

  <!-- Google Drive: coloured triangle prism -->
  <symbol id="i-gdrive" viewBox="-12 -12 24 24">
    <path d="M0,-10 L10,7 L-10,7 Z" fill="none" stroke="white" stroke-width="2" stroke-linejoin="round"/>
    <path d="M0,-10 L-10,7 L-4,7 Z" fill="white" opacity=".5"/>
    <path d="M0,-10 L10,7 L4,7 Z"  fill="white" opacity=".75"/>
    <line x1="-10" y1="7" x2="10" y2="7" stroke="white" stroke-width="2.5"/>
  </symbol>

  <!-- Gmail: envelope with M fold -->
  <symbol id="i-gmail" viewBox="-12 -12 24 24">
    <rect x="-10" y="-7.5" width="20" height="15" rx="2" fill="white" opacity=".9"/>
    <path d="M-10,-7.5 L0,3 L10,-7.5" fill="none" stroke="#EA4335" stroke-width="2.5"/>
    <line x1="-10" y1="-7.5" x2="-10" y2="7.5" stroke="#EA4335" stroke-width=".8" opacity=".25"/>
    <line x1="10"  y1="-7.5" x2="10"  y2="7.5" stroke="#EA4335" stroke-width=".8" opacity=".25"/>
  </symbol>

  <!-- GitHub: octocat outline -->
  <symbol id="i-github" viewBox="-12 -12 24 24">
    <circle cx="0" cy="-1" r="9" fill="white"/>
    <path d="M-3,5 L-3,9 Q0,11 3,9 L3,5 Q1.5,3.5 -1.5,3.5 Z" fill="white"/>
    <circle cx="-3" cy="-2.5" r="1.5" fill="#24292F"/>
    <circle cx="3"  cy="-2.5" r="1.5" fill="#24292F"/>
    <path d="M-3,1 Q0,3 3,1" fill="none" stroke="#24292F" stroke-width="1.2"/>
    <path d="M-8,-5 Q-11,-1 -9,3" fill="none" stroke="white" stroke-width="2.2"/>
    <path d="M8,-5  Q11,-1  9,3"  fill="none" stroke="white" stroke-width="2.2"/>
  </symbol>

  <!-- Soketi / WebSocket: bidirectional arrows -->
  <symbol id="i-ws" viewBox="-12 -12 24 24">
    <path d="M-10,-4 L5,-4 L5,-8 L11,0 L5,8 L5,4 L-10,4 Z"  fill="white" opacity=".9"/>
    <path d="M10,4  L-5,4  L-5,8 L-11,0 L-5,-8 L-5,-4 L10,-4 Z" fill="white" opacity=".45"/>
  </symbol>

  <!-- Cloudflare: cloud -->
  <symbol id="i-cf" viewBox="-12 -12 24 24">
    <path d="M-5,5 Q-10,5 -10,1 Q-10,-3 -6,-3 Q-7,-9 -1,-9 Q3.5,-9 4.5,-6 Q8.5,-7 10,-3 Q12,0 9,4 Q8,5 6,5 Z" fill="white" opacity=".9"/>
    <path d="M3,5 Q0,8 -2,8 Q-4,8 -5,6" fill="none" stroke="white" stroke-width="1.5"/>
  </symbol>

  <!-- SNS external alert: megaphone -->
  <symbol id="i-megaphone" viewBox="-12 -12 24 24">
    <path d="M-2,-7 L8,-4 L8,4 L-2,7 L-2,-7 Z" fill="white"/>
    <rect x="-8" y="-4" width="7" height="8" rx="1.5" fill="white" opacity=".8"/>
    <path d="M-4,4 L-4,9 L-1,9" fill="none" stroke="white" stroke-width="2" stroke-linecap="round"/>
    <circle cx="9" cy="0" r="1.5" fill="white" opacity=".6"/>
  </symbol>

</defs>

<!-- ══════════════════════════════════════════════════════════════
     BACKGROUND
══════════════════════════════════════════════════════════════ -->
<rect width="1366" height="800" fill="#F8FAFC"/>

<!-- ══════════════════════════════════════════════════════════════
     TITLE BAR
══════════════════════════════════════════════════════════════ -->
<rect width="1366" height="42" fill="#1E293B"/>
<text x="683" y="25" text-anchor="middle" font-size="15" font-weight="700" fill="white" letter-spacing="-.2">
  CRCMIS (crcmis-prod) — AWS Deployment Architecture
</text>
<text x="683" y="37" text-anchor="middle" font-size="9" fill="#94A3B8">
  Philippine Science High School – Caraga Region Campus  ·  AWS ap-southeast-1 (Singapore)  ·  May 2026
</text>

<!-- ══════════════════════════════════════════════════════════════
     SECTION: INTERNET
══════════════════════════════════════════════════════════════ -->
<rect x="8" y="46" width="106" height="468" rx="8" fill="white" stroke="#CBD5E1" stroke-width="1.5" filter="url(#sec-shadow)"/>
<text x="61" y="63" text-anchor="middle" font-size="10" font-weight="700" fill="#334155" letter-spacing=".04em">INTERNET</text>

<!-- User figure -->
<circle cx="61" cy="134" r="18" fill="#E0E7EF"/>
<circle cx="61" cy="122" r="8" fill="#94A3B8"/>
<path d="M39,154 C39,142 83,142 83,154" fill="#94A3B8"/>
<!-- Browser window -->
<rect x="38" y="160" width="46" height="34" rx="4" fill="#E0E7EF"/>
<rect x="38" y="160" width="46" height="9" rx="4" fill="#94A3B8"/>
<circle cx="45" cy="165" r="2" fill="white" opacity=".8"/>
<circle cx="52" cy="165" r="2" fill="white" opacity=".8"/>
<circle cx="59" cy="165" r="2" fill="white" opacity=".8"/>
<text x="61" y="212" text-anchor="middle" font-size="10.5" font-weight="700" fill="#1E293B">Users</text>
<text x="61" y="226" text-anchor="middle" font-size="9" fill="#64748B">Browser</text>


<!-- ══════════════════════════════════════════════════════════════
     SECTION: EDGE (Cloudflare)
══════════════════════════════════════════════════════════════ -->
<rect x="122" y="46" width="148" height="468" rx="8" fill="#FFF8F0" stroke="#F59E0B" stroke-width="1.5" filter="url(#sec-shadow)"/>
<text x="137" y="63" font-size="9.5" font-weight="700" fill="#B45309" letter-spacing=".04em">EDGE</text>

<!-- Cloudflare card -->
<rect x="130" y="72" width="132" height="232" rx="7" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<!-- CF logo -->
<rect x="140" y="82" width="112" height="32" rx="5" fill="#F48120"/>
<image href="https://cdn.simpleicons.org/cloudflare/ffffff" x="143" y="84" width="26" height="26"/>
<text x="175" y="103" font-size="11" font-weight="800" fill="white">Cloudflare</text>
<!-- Divider -->
<line x1="130" y1="122" x2="262" y2="122" stroke="#F1F5F9" stroke-width="1"/>
<text x="140" y="140" font-size="9.5" font-weight="700" fill="#1E293B">WAF</text>
<text x="140" y="153" font-size="8.5" fill="#64748B">DDoS L3/L4/L7 Protection</text>
<text x="140" y="165" font-size="8.5" fill="#64748B">Rate Limiting</text>
<line x1="130" y1="174" x2="262" y2="174" stroke="#F1F5F9" stroke-width="1"/>
<text x="140" y="190" font-size="9.5" font-weight="700" fill="#1E293B">Proxy &amp; SSL</text>
<text x="140" y="202" font-size="8.5" fill="#64748B">Orange-Cloud Proxy</text>
<text x="140" y="214" font-size="8.5" fill="#64748B">Full (Strict) SSL/TLS</text>
<text x="140" y="226" font-size="8.5" fill="#64748B">Hides ALB origin DNS</text>
<line x1="130" y1="234" x2="262" y2="234" stroke="#F1F5F9" stroke-width="1"/>
<text x="140" y="248" font-size="9.5" font-weight="700" fill="#1E293B">CDN</text>
<text x="140" y="260" font-size="8.5" fill="#64748B">Static asset edge caching</text>
<text x="140" y="272" font-size="8.5" fill="#64748B">Blocks multipart uploads</text>
<text x="140" y="284" font-size="8.5" fill="#64748B">→ Cloudflare WAF rule</text>
<text x="140" y="297" font-size="8.5" fill="#64748B">mis.crc.pshs.edu.ph</text>


<!-- ══════════════════════════════════════════════════════════════
     AWS REGION
══════════════════════════════════════════════════════════════ -->
<rect x="278" y="46" width="792" height="706" rx="8" fill="#EFF6FF" stroke="#3B82F6" stroke-width="2" filter="url(#sec-shadow)"/>
<!-- AWS tag strip -->
<rect x="278" y="46" width="264" height="26" rx="5" fill="#232F3E"/>
<text x="290" y="63" font-size="11" font-weight="800" fill="#FF9900" letter-spacing="-.5">aws</text>
<text x="316" y="63" font-size="9" fill="#CBD5E1">AWS Asia Pacific (Singapore) · ap-southeast-1</text>

<!-- VPC boundary -->
<rect x="286" y="78" width="776" height="668" rx="6" fill="white" stroke="#93C5FD" stroke-width="1.5" stroke-dasharray="7,4"/>
<rect x="292" y="83" width="22" height="16" rx="3" fill="#60A5FA"/>
<text x="303" y="95" text-anchor="middle" font-size="7.5" fill="white" font-weight="800">VPC</text>
<text x="320" y="95" font-size="9" font-weight="700" fill="#1D4ED8">crcmis-vpc  ·  10.0.0.0/16  ·  2 AZs (1a + 1b)</text>

<!-- ── ALB ────────────────────────────────────────────────── -->
<rect x="294" y="192" width="134" height="158" rx="8" fill="white" stroke="#C4B5FD" stroke-width="1.5" filter="url(#card-shadow)"/>
<rect x="294" y="192" width="134" height="26" rx="8" fill="#8B5CF6"/>
<rect x="294" y="206" width="134" height="12" fill="#8B5CF6"/>
<text x="361" y="210" text-anchor="middle" font-size="9" font-weight="700" fill="white">Application LB</text>
<rect x="302" y="225" width="34" height="34" rx="6" fill="#8B5CF6"/>
<use href="#i-alb" x="307" y="230" width="24" height="24"/>
<text x="342" y="234" font-size="9" font-weight="700" fill="#1E293B">crcmis-alb</text>
<text x="342" y="246" font-size="8.5" fill="#64748B">Multi-AZ 1a + 1b</text>
<line x1="294" y1="265" x2="428" y2="265" stroke="#E2E8F0" stroke-width="1"/>
<text x="302" y="279" font-size="8.5" fill="#64748B">Port 80 → redirect 443</text>
<text x="302" y="292" font-size="8.5" fill="#64748B">Port 443 → TLS 1.3</text>
<text x="302" y="305" font-size="8.5" fill="#64748B">ELBSecurityPolicy-TLS13</text>
<text x="302" y="318" font-size="8.5" fill="#64748B">Target: ECS port 80</text>

<!-- ── ECS FARGATE CLUSTER ─────────────────────────────── -->
<rect x="438" y="86" width="300" height="516" rx="8" fill="#EDE9FE" stroke="#6D28D9" stroke-width="2"/>
<rect x="438" y="86" width="300" height="26" rx="8" fill="#5B21B6"/>
<rect x="438" y="100" width="300" height="12" fill="#5B21B6"/>
<circle cx="456" cy="100" r="8" fill="white" opacity=".25"/>
<text x="456" y="104" text-anchor="middle" font-size="7" fill="white" font-weight="800">ECS</text>
<text x="473" y="105" font-size="9.5" font-weight="700" fill="white">Amazon ECS (Fargate) Cluster</text>

<!-- ── TASK 1 ── -->
<rect x="446" y="118" width="284" height="202" rx="7" fill="white" stroke="#7C3AED" stroke-width="1.5"/>
<rect x="446" y="118" width="284" height="22" rx="7" fill="#7C3AED"/>
<rect x="446" y="130" width="284" height="10" fill="#7C3AED"/>
<text x="588" y="133" text-anchor="middle" font-size="9" font-weight="700" fill="white">Task Replica 1  ·  2 vCPU / 4 GB  ·  ap-southeast-1a</text>

<!-- nginx row -->
<rect x="452" y="146" width="272" height="38" rx="5" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1"/>
<rect x="456" y="150" width="28" height="28" rx="4" fill="#009639"/>
<image href="https://cdn.simpleicons.org/nginx/ffffff" x="458" y="152" width="24" height="24"/>
<text x="492" y="164" font-size="9" font-weight="700" fill="#1E293B">nginx 1.26</text>
<text x="492" y="176" font-size="8" fill="#64748B">Reverse proxy  ·  Static files  ·  Port 80</text>

<!-- PHP-FPM row -->
<rect x="452" y="189" width="272" height="38" rx="5" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1"/>
<rect x="456" y="193" width="28" height="28" rx="4" fill="#777BB4"/>
<image href="https://cdn.simpleicons.org/php/ffffff" x="458" y="195" width="24" height="24"/>
<text x="492" y="207" font-size="9" font-weight="700" fill="#1E293B">PHP-FPM 8.4</text>
<text x="492" y="219" font-size="8" fill="#64748B">Laravel 12  ·  FastCGI process manager</text>

<!-- Queue Worker row -->
<rect x="452" y="232" width="272" height="38" rx="5" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1"/>
<rect x="456" y="236" width="28" height="28" rx="4" fill="#E11D48"/>
<use href="#i-queue" x="458" y="238" width="24" height="24"/>
<text x="492" y="250" font-size="9" font-weight="700" fill="#1E293B">Queue Worker</text>
<text x="492" y="262" font-size="8" fill="#64748B">Laravel Horizon  ·  Redis-backed jobs</text>

<!-- Cron row -->
<rect x="452" y="275" width="272" height="38" rx="5" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1"/>
<rect x="456" y="279" width="28" height="28" rx="4" fill="#0369A1"/>
<use href="#i-cron" x="458" y="281" width="24" height="24"/>
<text x="492" y="293" font-size="9" font-weight="700" fill="#1E293B">Cron + Supervisord</text>
<text x="492" y="305" font-size="8" fill="#64748B">Scheduled jobs  ·  DB backup → Google Drive</text>

<!-- Healthy badge Task 1 -->
<rect x="452" y="316" width="272" height="2" rx="1" fill="#E2E8F0"/>
<text x="587" y="313" text-anchor="middle" font-size="7.5" fill="#16A34A" font-weight="700">✅  HEALTHY  ·  Container health check: GET /health (30s)</text>

<!-- ── TASK 2 ── -->
<rect x="446" y="332" width="284" height="202" rx="7" fill="white" stroke="#7C3AED" stroke-width="1.5"/>
<rect x="446" y="332" width="284" height="22" rx="7" fill="#7C3AED"/>
<rect x="446" y="344" width="284" height="10" fill="#7C3AED"/>
<text x="588" y="347" text-anchor="middle" font-size="9" font-weight="700" fill="white">Task Replica 2  ·  2 vCPU / 4 GB  ·  ap-southeast-1b</text>

<!-- nginx row T2 -->
<rect x="452" y="360" width="272" height="38" rx="5" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1"/>
<rect x="456" y="364" width="28" height="28" rx="4" fill="#009639"/>
<image href="https://cdn.simpleicons.org/nginx/ffffff" x="458" y="366" width="24" height="24"/>
<text x="492" y="378" font-size="9" font-weight="700" fill="#1E293B">nginx 1.26</text>
<text x="492" y="390" font-size="8" fill="#64748B">Reverse proxy  ·  Static files  ·  Port 80</text>

<!-- PHP-FPM row T2 -->
<rect x="452" y="403" width="272" height="38" rx="5" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1"/>
<rect x="456" y="407" width="28" height="28" rx="4" fill="#777BB4"/>
<image href="https://cdn.simpleicons.org/php/ffffff" x="458" y="409" width="24" height="24"/>
<text x="492" y="421" font-size="9" font-weight="700" fill="#1E293B">PHP-FPM 8.4</text>
<text x="492" y="433" font-size="8" fill="#64748B">Laravel 12  ·  FastCGI process manager</text>

<!-- Queue Worker row T2 -->
<rect x="452" y="446" width="272" height="38" rx="5" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1"/>
<rect x="456" y="450" width="28" height="28" rx="4" fill="#E11D48"/>
<use href="#i-queue" x="458" y="452" width="24" height="24"/>
<text x="492" y="464" font-size="9" font-weight="700" fill="#1E293B">Queue Worker</text>
<text x="492" y="476" font-size="8" fill="#64748B">Laravel Horizon  ·  Redis-backed jobs</text>

<!-- Cron row T2 -->
<rect x="452" y="489" width="272" height="38" rx="5" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1"/>
<rect x="456" y="493" width="28" height="28" rx="4" fill="#0369A1"/>
<use href="#i-cron" x="458" y="495" width="24" height="24"/>
<text x="492" y="507" font-size="9" font-weight="700" fill="#1E293B">Cron + Supervisord</text>
<text x="492" y="519" font-size="8" fill="#64748B">Scheduled jobs  ·  DB backup → Google Drive</text>

<!-- Healthy badge Task 2 -->
<text x="587" y="527" text-anchor="middle" font-size="7.5" fill="#16A34A" font-weight="700">✅  HEALTHY  ·  Container health check: GET /health (30s)</text>

<!-- Auto-scale badge -->
<rect x="446" y="546" width="284" height="44" rx="6" fill="#DBEAFE" stroke="#93C5FD" stroke-width="1"/>
<text x="588" y="561" text-anchor="middle" font-size="9" fill="#1D4ED8" font-weight="700">Auto-Scaling Policy</text>
<text x="588" y="573" text-anchor="middle" font-size="8" fill="#3B82F6">min=2 · CPU target 60% · MEM 75% · max=4 tasks</text>
<text x="588" y="584" text-anchor="middle" font-size="8" fill="#3B82F6">Circuit Breaker ON · Auto-rollback ON</text>

<!-- ── BACKEND SERVICES PANEL ──────────────────────────── -->
<rect x="752" y="86" width="296" height="660" rx="7" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1"/>
<text x="764" y="106" font-size="10" font-weight="700" fill="#1E293B" letter-spacing=".02em">Backend Services</text>

<!-- Macro: service box = rect(x,y,280,56) icon(x+6,y+8,36,36) title sub -->

<!-- 1. RDS MySQL -->
<rect x="758" y="112" width="282" height="58" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="764" y="118" width="36" height="36" rx="6" fill="#3F8EDB"/>
<image href="https://raw.githubusercontent.com/awslabs/aws-icons-for-plantuml/main/dist/Database/RDS.png" x="768" y="122" width="28" height="28"/>
<text x="808" y="133" font-size="9.5" font-weight="700" fill="#1E293B">Amazon RDS for MySQL</text>
<text x="808" y="147" font-size="8" fill="#64748B">MySQL 8.0 · Multi-AZ · db.t3.small · 20→100 GB</text>
<text x="808" y="159" font-size="8" fill="#16A34A">✅ Encrypted · Deletion protection · 7d backup</text>

<!-- 2. ElastiCache Redis -->
<rect x="758" y="178" width="282" height="58" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="764" y="184" width="36" height="36" rx="6" fill="#C925D1"/>
<image href="https://raw.githubusercontent.com/awslabs/aws-icons-for-plantuml/main/dist/Database/ElastiCache.png" x="768" y="188" width="28" height="28"/>
<text x="808" y="199" font-size="9.5" font-weight="700" fill="#1E293B">Amazon ElastiCache Redis 7.0</text>
<text x="808" y="213" font-size="8" fill="#64748B">Replication group · Primary + Replica · Multi-AZ</text>
<text x="808" y="225" font-size="8" fill="#16A34A">✅ HA failover · Encrypted · Snapshot 3d</text>

<!-- 3. S3 -->
<rect x="758" y="244" width="282" height="58" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="764" y="250" width="36" height="36" rx="6" fill="#3F8624"/>
<image href="https://raw.githubusercontent.com/awslabs/aws-icons-for-plantuml/main/dist/Storage/SimpleStorageService.png" x="768" y="254" width="28" height="28"/>
<text x="808" y="265" font-size="9.5" font-weight="700" fill="#1E293B">Amazon S3</text>
<text x="808" y="279" font-size="8" fill="#64748B">crcmis-mis-storage · WFH photos · PDFs · Uploads</text>
<text x="808" y="291" font-size="8" fill="#16A34A">✅ Block public · AES256 · IAM scoped · Proxy only</text>

<!-- 4. CloudWatch -->
<rect x="758" y="310" width="282" height="58" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="764" y="316" width="36" height="36" rx="6" fill="#E05243"/>
<image href="https://raw.githubusercontent.com/awslabs/aws-icons-for-plantuml/main/dist/ManagementGovernance/CloudWatch.png" x="768" y="320" width="28" height="28"/>
<text x="808" y="331" font-size="9.5" font-weight="700" fill="#1E293B">Amazon CloudWatch</text>
<text x="808" y="345" font-size="8" fill="#64748B">Logs (90d) · 6 Alarms · Container Insights</text>
<text x="808" y="357" font-size="8" fill="#16A34A">✅ RDS Enhanced Monitoring (60s interval)</text>

<!-- 5. SSM -->
<rect x="758" y="376" width="282" height="58" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="764" y="382" width="36" height="36" rx="6" fill="#E05243"/>
<image href="https://raw.githubusercontent.com/awslabs/aws-icons-for-plantuml/main/dist/ManagementGovernance/SystemsManager.png" x="768" y="386" width="28" height="28"/>
<text x="808" y="397" font-size="9.5" font-weight="700" fill="#1E293B">AWS Systems Manager</text>
<text x="808" y="411" font-size="8" fill="#64748B">/crcmis/prod/* · 24 parameters · DB, Redis, Mail…</text>
<text x="808" y="423" font-size="8" fill="#16A34A">✅ Secrets Manager: Google Drive credentials</text>

<!-- 6. IAM -->
<rect x="758" y="442" width="282" height="58" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="764" y="448" width="36" height="36" rx="6" fill="#DD344C"/>
<image href="https://raw.githubusercontent.com/awslabs/aws-icons-for-plantuml/main/dist/SecurityIdentityCompliance/IdentityandAccessManagement.png" x="768" y="452" width="28" height="28"/>
<text x="808" y="463" font-size="9.5" font-weight="700" fill="#1E293B">AWS IAM</text>
<text x="808" y="477" font-size="8" fill="#64748B">crcmisECSExecutionRole · crcmisECSTaskRole</text>
<text x="808" y="489" font-size="8" fill="#16A34A">✅ Execution ≠ Task role · Least privilege</text>

<!-- 7. ECR -->
<rect x="758" y="508" width="282" height="58" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="764" y="514" width="36" height="36" rx="6" fill="#FF9900"/>
<image href="https://raw.githubusercontent.com/awslabs/aws-icons-for-plantuml/main/dist/Containers/ElasticContainerRegistry.png" x="768" y="518" width="28" height="28"/>
<text x="808" y="529" font-size="9.5" font-weight="700" fill="#1E293B">Amazon ECR</text>
<text x="808" y="543" font-size="8" fill="#64748B">crcmis/app · crcmis/nginx · crcmis/soketi</text>
<text x="808" y="555" font-size="8" fill="#16A34A">✅ IMMUTABLE · Scan on push · Lifecycle: 10 imgs</text>

<!-- 8. KMS -->
<rect x="758" y="574" width="282" height="58" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="764" y="580" width="36" height="36" rx="6" fill="#DD344C"/>
<image href="https://raw.githubusercontent.com/awslabs/aws-icons-for-plantuml/main/dist/SecurityIdentityCompliance/KeyManagementService.png" x="768" y="584" width="28" height="28"/>
<text x="808" y="595" font-size="9.5" font-weight="700" fill="#1E293B">AWS KMS</text>
<text x="808" y="609" font-size="8" fill="#64748B">Document signing key · Sign / Verify / GetPublicKey</text>
<text x="808" y="621" font-size="8" fill="#16A34A">✅ Digital signatures for approvals &amp; completions</text>

<!-- 9. SNS (full width) -->
<rect x="758" y="640" width="282" height="44" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="764" y="646" width="32" height="32" rx="6" fill="#FF4F8B"/>
<image href="https://raw.githubusercontent.com/awslabs/aws-icons-for-plantuml/main/dist/ApplicationIntegration/SimpleNotificationService.png" x="766" y="648" width="28" height="28"/>
<text x="804" y="657" font-size="9" font-weight="700" fill="#1E293B">Amazon SNS  ·  crcmis-alerts</text>
<text x="804" y="670" font-size="8" fill="#64748B">6 CloudWatch alarms → jtordos@crc.pshs.edu.ph</text>

<!-- 10. CloudTrail (full width) -->
<rect x="758" y="692" width="282" height="44" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="764" y="698" width="32" height="32" rx="6" fill="#E05243"/>
<image href="https://raw.githubusercontent.com/awslabs/aws-icons-for-plantuml/main/dist/ManagementGovernance/CloudTrail.png" x="766" y="700" width="28" height="28"/>
<text x="804" y="709" font-size="9" font-weight="700" fill="#1E293B">AWS CloudTrail</text>
<text x="804" y="722" font-size="8" fill="#64748B">All API calls logged · crcmis-cloudtrail-logs S3</text>

<!-- ══════════════════════════════════════════════════════════════
     EXTERNAL & SUPPORTING SERVICES
══════════════════════════════════════════════════════════════ -->
<rect x="1080" y="46" width="278" height="520" rx="8" fill="white" stroke="#F59E0B" stroke-width="1.5" filter="url(#sec-shadow)"/>
<text x="1092" y="65" font-size="9.5" font-weight="700" fill="#B45309" letter-spacing=".04em">EXTERNAL &amp; SUPPORTING SERVICES</text>

<!-- Google Drive -->
<rect x="1088" y="74" width="262" height="66" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="1094" y="80" width="38" height="38" rx="6" fill="#4285F4"/>
<image href="https://cdn.simpleicons.org/googledrive/ffffff" x="1101" y="87" width="24" height="24"/>
<text x="1140" y="95" font-size="9.5" font-weight="700" fill="#1E293B">Google Drive</text>
<text x="1140" y="108" font-size="8" fill="#64748B">DB backups via cron (mysqldump → gzip)</text>
<text x="1140" y="120" font-size="8" fill="#64748B">Service account credentials via Secrets Manager</text>
<text x="1140" y="132" font-size="8" fill="#64748B">Uploaded by BackupDatabase Artisan command</text>

<!-- Gmail SMTP -->
<rect x="1088" y="150" width="262" height="66" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="1094" y="156" width="38" height="38" rx="6" fill="#EA4335"/>
<image href="https://cdn.simpleicons.org/gmail/ffffff" x="1101" y="163" width="24" height="24"/>
<text x="1140" y="171" font-size="9.5" font-weight="700" fill="#1E293B">Gmail SMTP</text>
<text x="1140" y="184" font-size="8" fill="#64748B">smtp.gmail.com:587 · TLS</text>
<text x="1140" y="196" font-size="8" fill="#64748B">From: portal@crc.pshs.edu.ph</text>
<text x="1140" y="208" font-size="8" fill="#64748B">Leave, IT requests, approval notifications</text>

<!-- Pusher / Soketi -->
<rect x="1088" y="226" width="262" height="66" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="1094" y="232" width="38" height="38" rx="6" fill="#6366F1"/>
<image href="https://cdn.simpleicons.org/pusher/ffffff" x="1101" y="239" width="24" height="24"/>
<text x="1140" y="247" font-size="9.5" font-weight="700" fill="#1E293B">Soketi / Pusher</text>
<text x="1140" y="260" font-size="8" fill="#64748B">WebSocket · Real-time chat (Laravel Echo)</text>
<text x="1140" y="272" font-size="8" fill="#64748B">crcmis/soketi image in ECR</text>
<text x="1140" y="284" font-size="8" fill="#64748B">Credentials in SSM Parameter Store</text>

<!-- GitHub Actions -->
<rect x="1088" y="302" width="262" height="66" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="1094" y="308" width="38" height="38" rx="6" fill="#24292F"/>
<image href="https://cdn.simpleicons.org/github/ffffff" x="1101" y="315" width="24" height="24"/>
<text x="1140" y="323" font-size="9.5" font-weight="700" fill="#1E293B">GitHub Actions</text>
<text x="1140" y="336" font-size="8" fill="#64748B">push to main → auto-deploy</text>
<text x="1140" y="348" font-size="8" fill="#64748B">Build → ECR → ECS rolling deploy</text>
<text x="1140" y="360" font-size="8" fill="#64748B">DB migrations run at container startup</text>

<!-- Cloudflare (reference) -->
<rect x="1088" y="378" width="262" height="50" rx="6" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="1094" y="384" width="38" height="34" rx="6" fill="#F48120"/>
<image href="https://cdn.simpleicons.org/cloudflare/ffffff" x="1101" y="389" width="24" height="24"/>
<text x="1140" y="399" font-size="9.5" font-weight="700" fill="#1E293B">Cloudflare</text>
<text x="1140" y="412" font-size="8" fill="#64748B">DNS · WAF · CDN · DDoS · Proxy</text>
<text x="1140" y="424" font-size="8" fill="#64748B">mis.crc.pshs.edu.ph → crcmis-alb</text>

<!-- AWS SNS alert -->
<rect x="1088" y="438" width="262" height="50" rx="6" fill="white" stroke="#FFB3D1" stroke-width="1" filter="url(#card-shadow)"/>
<rect x="1094" y="444" width="38" height="34" rx="6" fill="#FF4F8B"/>
<image href="https://raw.githubusercontent.com/awslabs/aws-icons-for-plantuml/main/dist/ApplicationIntegration/SimpleNotificationService.png" x="1099" y="447" width="28" height="28"/>
<text x="1140" y="459" font-size="9.5" font-weight="700" fill="#1E293B">SNS Alerts</text>
<text x="1140" y="472" font-size="8" fill="#64748B">6 CloudWatch alarms → email</text>
<text x="1140" y="484" font-size="8" fill="#64748B">jtordos@crc.pshs.edu.ph</text>

<!-- ══════════════════════════════════════════════════════════════
     ADMIN ACCESS BOX
══════════════════════════════════════════════════════════════ -->
<rect x="8" y="522" width="262" height="198" rx="8" fill="white" stroke="#CBD5E1" stroke-width="1.5" filter="url(#sec-shadow)"/>
<text x="20" y="540" font-size="9.5" font-weight="700" fill="#1D4ED8">Administrator Access via AWS CLI</text>

<!-- Admin user (shifted up) -->
<circle cx="40" cy="578" r="13" fill="#DBEAFE"/>
<circle cx="40" cy="569" r="6" fill="#3B82F6"/>
<path d="M27,591 C27,581 53,581 53,591" fill="#3B82F6"/>
<text x="40" y="606" text-anchor="middle" font-size="8" fill="#1E293B" font-weight="600">Admin</text>

<!-- Arrow -->
<line x1="56" y1="578" x2="70" y2="578" stroke="#8E44AD" stroke-width="1.5" stroke-dasharray="3,2" marker-end="url(#mp)"/>

<!-- AWS CLI box -->
<rect x="72" y="563" width="76" height="30" rx="5" fill="#232F3E"/>
<text x="110" y="575" text-anchor="middle" font-size="8.5" fill="#FF9900" font-weight="800">aws</text>
<text x="110" y="587" text-anchor="middle" font-size="7.5" fill="#94A3B8">CLI / Console</text>

<!-- Arrow -->
<line x1="148" y1="578" x2="162" y2="578" stroke="#8E44AD" stroke-width="1.5" stroke-dasharray="3,2" marker-end="url(#mp)"/>

<!-- IAM box -->
<rect x="164" y="563" width="96" height="30" rx="5" fill="white" stroke="#DD344C" stroke-width="1.5"/>
<rect x="168" y="567" width="22" height="22" rx="4" fill="#DD344C"/>
<image href="https://raw.githubusercontent.com/awslabs/aws-icons-for-plantuml/main/dist/SecurityIdentityCompliance/IdentityandAccessManagement.png" x="168" y="567" width="22" height="22"/>
<text x="196" y="577" font-size="8.5" font-weight="700" fill="#1E293B">IAM Role</text>
<text x="196" y="589" font-size="7.5" fill="#64748B">Access keys</text>

<!-- Divider -->
<line x1="18" y1="622" x2="260" y2="622" stroke="#F1F5F9" stroke-width="1"/>

<text x="20" y="637" font-size="8" fill="#475569">Uses AWS CLI to manage ECS, RDS, ECR,</text>
<text x="20" y="650" font-size="8" fill="#475569">S3, SSM, CloudWatch, and ECS Exec for</text>
<text x="20" y="663" font-size="8" fill="#475569">debugging running containers (SSM Messages).</text>

<!-- ══════════════════════════════════════════════════════════════
     LEGEND
══════════════════════════════════════════════════════════════ -->
<rect x="8" y="760" width="498" height="72" rx="7" fill="white" stroke="#E2E8F0" stroke-width="1"/>
<text x="20" y="776" font-size="9" font-weight="700" fill="#334155">Connection Legend</text>
<!-- Row 1 -->
<line x1="20" y1="788" x2="56" y2="788" stroke="#E07B00" stroke-width="2" marker-end="url(#mo)"/>
<text x="62" y="792" font-size="8.5" fill="#334155">Request Path (HTTPS/HTTP)</text>
<line x1="186" y1="788" x2="222" y2="788" stroke="#1A73E8" stroke-width="1.5" stroke-dasharray="6,3" marker-end="url(#mb)"/>
<text x="228" y="792" font-size="8.5" fill="#334155">Config / Pull / Logs</text>
<line x1="346" y1="788" x2="382" y2="788" stroke="#1E8449" stroke-width="1.5" stroke-dasharray="5,3" marker-end="url(#mg)"/>
<text x="388" y="792" font-size="8.5" fill="#334155">Data / Service Call</text>
<!-- Row 2 -->
<line x1="20" y1="808" x2="56" y2="808" stroke="#7B2FBE" stroke-width="1.5" stroke-dasharray="3,3" marker-end="url(#mp)"/>
<text x="62" y="812" font-size="8.5" fill="#334155">Auth / IAM / Secrets</text>
<line x1="186" y1="808" x2="222" y2="808" stroke="#E07B00" stroke-width="2" marker-end="url(#mo)" marker-start="url(#mob)"/>
<text x="228" y="812" font-size="8.5" fill="#334155">Bidirectional</text>
<text x="346" y="806" font-size="8.5" fill="#475569">✅ Security hardening applied</text>
<text x="346" y="818" font-size="8.5" fill="#475569">May 2026 · Audit score: C+ → A−</text>

<!-- ══════════════════════════════════════════════════════════════
     CI/CD STRIP
══════════════════════════════════════════════════════════════ -->
<rect x="514" y="760" width="844" height="72" rx="7" fill="#EFF6FF" stroke="#93C5FD" stroke-width="1.5"/>
<text x="526" y="776" font-size="9.5" font-weight="700" fill="#1D4ED8">🚀  CI/CD Pipeline  ·  GitHub Actions (auto-triggered on push to main)</text>
<g font-size="8" font-weight="600" fill="#1D4ED8">
  <!-- Steps -->
  <rect x="524" y="782" width="66" height="28" rx="5" fill="white" stroke="#93C5FD" stroke-width="1"/>
  <text x="557" y="794" text-anchor="middle" font-size="8" font-weight="600" fill="#1D4ED8">git push</text>
  <text x="557" y="805" text-anchor="middle" font-size="7.5" fill="#64748B">main branch</text>
  <text x="596" y="799" font-size="11" fill="#93C5FD">›</text>
  <rect x="602" y="782" width="80" height="28" rx="5" fill="white" stroke="#93C5FD" stroke-width="1"/>
  <text x="642" y="794" text-anchor="middle" font-size="8" font-weight="600" fill="#1D4ED8">composer + npm</text>
  <text x="642" y="805" text-anchor="middle" font-size="7.5" fill="#64748B">Vite build assets</text>
  <text x="688" y="799" font-size="11" fill="#93C5FD">›</text>
  <rect x="694" y="782" width="80" height="28" rx="5" fill="white" stroke="#93C5FD" stroke-width="1"/>
  <text x="734" y="794" text-anchor="middle" font-size="8" font-weight="600" fill="#1D4ED8">docker buildx</text>
  <text x="734" y="805" text-anchor="middle" font-size="7.5" fill="#64748B">linux/amd64</text>
  <text x="780" y="799" font-size="11" fill="#93C5FD">›</text>
  <rect x="786" y="782" width="84" height="28" rx="5" fill="white" stroke="#93C5FD" stroke-width="1"/>
  <text x="828" y="794" text-anchor="middle" font-size="8" font-weight="600" fill="#1D4ED8">Push to ECR</text>
  <text x="828" y="805" text-anchor="middle" font-size="7.5" fill="#64748B">:SHA IMMUTABLE</text>
  <text x="876" y="799" font-size="11" fill="#93C5FD">›</text>
  <rect x="882" y="782" width="90" height="28" rx="5" fill="white" stroke="#93C5FD" stroke-width="1"/>
  <text x="927" y="794" text-anchor="middle" font-size="8" font-weight="600" fill="#1D4ED8">Register task def</text>
  <text x="927" y="805" text-anchor="middle" font-size="7.5" fill="#64748B">Inherits health check</text>
  <text x="978" y="799" font-size="11" fill="#93C5FD">›</text>
  <rect x="984" y="782" width="80" height="28" rx="5" fill="white" stroke="#93C5FD" stroke-width="1"/>
  <text x="1024" y="794" text-anchor="middle" font-size="8" font-weight="600" fill="#1D4ED8">ECS Rolling</text>
  <text x="1024" y="805" text-anchor="middle" font-size="7.5" fill="#64748B">minHealthy=100%</text>
  <text x="1070" y="799" font-size="11" fill="#93C5FD">›</text>
  <rect x="1076" y="782" width="84" height="28" rx="5" fill="white" stroke="#93C5FD" stroke-width="1"/>
  <text x="1118" y="794" text-anchor="middle" font-size="8" font-weight="600" fill="#1D4ED8">DB Migrations</text>
  <text x="1118" y="805" text-anchor="middle" font-size="7.5" fill="#64748B">on container start</text>
  <text x="1166" y="799" font-size="11" fill="#93C5FD">›</text>
  <rect x="1172" y="782" width="78" height="28" rx="5" fill="#DCFCE7" stroke="#86EFAC" stroke-width="1"/>
  <text x="1211" y="794" text-anchor="middle" font-size="8" font-weight="600" fill="#16A34A">✅ Stable</text>
  <text x="1211" y="805" text-anchor="middle" font-size="7.5" fill="#16A34A">services-stable</text>
</g>

<!-- ══════════════════════════════════════════════════════════════
     CONNECTION LINES
══════════════════════════════════════════════════════════════ -->

<!-- 1. Users → Cloudflare (orange) -->
<line x1="114" y1="200" x2="124" y2="200" stroke="#E07B00" stroke-width="2" marker-end="url(#mo)"/>

<!-- 2. Cloudflare → ALB (orange, right side of edge section to ALB) -->
<path d="M270,260 L282,260 L282,280 L294,280" fill="none" stroke="#E07B00" stroke-width="2" marker-end="url(#mo)"/>

<!-- 3. ALB → Task 1 (orange) -->
<path d="M428,240 L438,240 L438,220 L446,220" fill="none" stroke="#E07B00" stroke-width="2" marker-end="url(#mo)"/>

<!-- 4. ALB → Task 2 (orange) -->
<path d="M428,330 L438,330 L438,435 L446,435" fill="none" stroke="#E07B00" stroke-width="2" marker-end="url(#mo)"/>

<!-- 5. Task 1 → ALB response (orange dashed, bidirectional indicator) -->
<path d="M446,260 L438,260 L438,295 L428,295" fill="none" stroke="#E07B00" stroke-width="1.5" stroke-dasharray="5,3" marker-end="url(#mo)"/>

<!-- VERTICAL BUS: ECS to backend, x=744 -->
<line x1="744" y1="141" x2="744" y2="659" stroke="#E2E8F0" stroke-width="1.5" stroke-dasharray="4,2"/>

<!-- 6. Task1 → bus (green dashed, data) for RDS -->
<path d="M730,180 L744,180 L744,141 L758,141" fill="none" stroke="#1E8449" stroke-width="1.5" stroke-dasharray="5,3" marker-end="url(#mg)"/>

<!-- 7. Task2 → bus (green dashed) for RDS -->
<path d="M730,370 L738,370 L738,141 L758,141" fill="none" stroke="#1E8449" stroke-width="1" stroke-dasharray="4,3"/>

<!-- 8. Tasks → Redis (green dashed bidirectional) -->
<path d="M730,200 L744,200 L744,207 L758,207" fill="none" stroke="#1E8449" stroke-width="1.5" stroke-dasharray="5,3" marker-end="url(#mg)" marker-start="url(#mob)"/>

<!-- 9. Tasks → S3 (green dashed) -->
<path d="M730,220 L744,220 L744,273 L758,273" fill="none" stroke="#1E8449" stroke-width="1.5" stroke-dasharray="5,3" marker-end="url(#mg)"/>

<!-- 10. Tasks → CloudWatch (blue dashed, logs) -->
<path d="M730,160 L744,160 L744,339 L758,339" fill="none" stroke="#1A73E8" stroke-width="1.5" stroke-dasharray="5,3" marker-end="url(#mb)"/>

<!-- 11. SSM → Tasks (blue dashed, startup config) -->
<path d="M758,405 L744,405 L744,390 L730,390" fill="none" stroke="#1A73E8" stroke-width="1.5" stroke-dasharray="5,3" marker-end="url(#mb)"/>

<!-- 12. Tasks → IAM (purple dotted) -->
<path d="M730,410 L744,410 L744,471 L758,471" fill="none" stroke="#7B2FBE" stroke-width="1.5" stroke-dasharray="3,3" marker-end="url(#mp)"/>

<!-- 13. ECR → Tasks (blue dashed, image pull at startup) -->
<path d="M758,537 L744,537 L744,520 L730,520" fill="none" stroke="#1A73E8" stroke-width="1.5" stroke-dasharray="5,3" marker-end="url(#mb)"/>

<!-- 14. Tasks → KMS (purple dotted) -->
<path d="M730,430 L744,430 L744,603 L758,603" fill="none" stroke="#7B2FBE" stroke-width="1.5" stroke-dasharray="3,3" marker-end="url(#mp)"/>

<!-- 15. CloudWatch → SNS (orange, alarm trigger) -->
<path d="M730,340 L736,340 L736,659 L758,659" fill="none" stroke="#FF4F8B" stroke-width="1.5" stroke-dasharray="4,3" marker-end="url(#mo)"/>

<!-- 16. ECS → Gmail SMTP (orange, email notifications) -->
<path d="M1078,500 L1085,500 L1085,183 L1088,183" fill="none" stroke="#1E8449" stroke-width="1.5" stroke-dasharray="5,3" marker-end="url(#mg)"/>

<!-- 17. ECS cron → Google Drive (green dashed) -->
<path d="M1078,600 L1085,600 L1085,107 L1088,107" fill="none" stroke="#1E8449" stroke-width="1.5" stroke-dasharray="5,3" marker-end="url(#mg)"/>

<!-- 18. ECR → GitHub Actions (blue dashed, images pushed from CI) -->
<path d="M1040,514 L1085,514 L1085,335 L1088,335" fill="none" stroke="#1A73E8" stroke-width="1.5" stroke-dasharray="5,3" marker-end="url(#mb)"/>

<!-- 19. SNS external alert -->
<path d="M1040,659 L1085,659 L1085,463 L1088,463" fill="none" stroke="#FF4F8B" stroke-width="1.5" stroke-dasharray="4,3" marker-end="url(#mo)"/>

<!-- ══════════════════════════════════════════════════════════════
     FOOTER
══════════════════════════════════════════════════════════════ -->
<!-- ══ CONNECTION LABELS — rendered last so they sit on top of all sections ══ -->
<!-- Internet → Cloudflare -->
<rect x="104" y="208" width="30" height="26" rx="4" fill="white"/>
<text x="119" y="220" text-anchor="middle" font-size="8" font-weight="700" fill="#E07B00">HTTPS</text>
<text x="119" y="230" text-anchor="middle" font-size="8" font-weight="700" fill="#E07B00">443</text>
<!-- Cloudflare → ALB -->
<rect x="260" y="248" width="34" height="26" rx="4" fill="white"/>
<text x="277" y="260" text-anchor="middle" font-size="8" font-weight="700" fill="#E07B00">HTTPS</text>
<text x="277" y="270" text-anchor="middle" font-size="8" font-weight="700" fill="#E07B00">443</text>
<!-- ALB → ECS -->
<rect x="421" y="211" width="26" height="18" rx="4" fill="white"/>
<text x="434" y="223" text-anchor="middle" font-size="8" font-weight="700" fill="#E07B00">HTTP 80</text>

<text x="683" y="872" text-anchor="middle" font-size="8.5" fill="#94A3B8">
  May 2026  ·  CRCMIS  ·  PSHS-CRC  ·  AWS ap-southeast-1  ·  Architecture by Claude Sonnet 4.6
</text>

</svg>
      </div>
    </div>

  </AdminLayout>
</template>

<style>
.arch-wrap {
  background: #e8edf2;
  border-radius: .75rem;
  padding: 1rem;
  overflow: hidden;
}

.arch-scroll {
  overflow-x: auto;
  overflow-y: auto;
  max-height: calc(100vh - 180px);
  border-radius: .5rem;
}

.arch-scroll svg {
  display: block;
  min-width: 1366px;
}

/* ── Print styles ── */
@media print {
  @page { size: A3 landscape; margin: 5mm; }

  body * { visibility: hidden; }
  .arch-scroll,
  .arch-scroll * { visibility: visible; }
  .arch-scroll {
    position: fixed;
    inset: 0;
    max-height: none;
    overflow: visible;
    background: white;
    border-radius: 0;
    padding: 0;
  }
  .arch-scroll svg {
    width: 100%;
    height: auto;
  }
}
</style>
