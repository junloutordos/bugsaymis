<script setup>
import { ref, watch, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import axios from 'axios'
import { auth, provider, signInWithPopup } from '@/firebase'
import { usePage } from '@inertiajs/vue3'

const OFFICIAL_DOMAIN = '@crc.pshs.edu.ph'
const BG_IMAGE_URL = '/storage/bg.jpg'

const isLoading = ref(false)

const showAlert = (options) => {
  Swal.fire({ confirmButtonColor: '#6366f1', ...options })
}

const isAuthorizedEmail = (email) =>
  email?.toLowerCase().endsWith(OFFICIAL_DOMAIN)

const page = usePage()
const appVersion = computed(() => page.props.appVersion?.current ?? '1.0.0')
watch(
  () => page.props.errors,
  (errs) => {
    if (!errs) return
    const emailErr = errs.email || (Array.isArray(errs.email) ? errs.email[0] : null)
    const msg = emailErr || (typeof errs === 'string' ? errs : null)
    if (!msg) return
    if (String(msg).includes('Unable to logged in')) {
      showAlert({ icon: 'error', title: 'Unable to logged in', text: 'Contact MIS administrator.' })
    } else {
      showAlert({ icon: 'error', title: 'Login Failed', text: String(msg) })
    }
  },
  { immediate: true }
)

const googleLogin = async () => {
  if (isLoading.value) return
  try {
    isLoading.value = true
    const { user } = await signInWithPopup(auth, provider)
    if (!isAuthorizedEmail(user.email)) {
      showAlert({
        icon: 'error',
        title: 'Unauthorized Email',
        text: 'Only official PSHS-CRC accounts are allowed.',
      })
      return
    }
    const { data } = await axios.post('/google/login', {
      email: user.email,
      name: user.displayName,
      uid: user.uid,
    })
    if (!data?.success) throw new Error('Account verification failed')
    showAlert({
      icon: 'success',
      title: 'Login Successful',
      text: `Welcome, ${user.displayName}! Redirecting…`,
      timer: 1500,
      showConfirmButton: false,
    })
    window.location.href = data.redirect_to
  } catch (error) {
    console.error(error)
    showAlert({
      icon: 'error',
      title: 'Login Failed',
      text: error.response?.data?.message || error.message,
    })
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <Head title="Login" />

  <div class="login-root">
    <!-- ── Left panel (branding) ── -->
    <div class="left-panel">
      <!-- bg photo -->
      <div class="left-bg" :style="{ backgroundImage: `url(${BG_IMAGE_URL})` }" />
      <!-- dark overlay + gradient -->
      <div class="left-overlay" />

      <!-- Content -->
      <div class="left-content">
        <!-- Accent dot grid (decorative) -->
        <div class="dot-grid" aria-hidden="true">
          <span v-for="n in 48" :key="n" class="dot" />
        </div>

        <!-- Logo + school identity -->
        <div class="brand">
          <img src="/images/pshslogo.png" alt="PSHS-CRC Logo" class="brand-logo" />
          <h1 class="brand-title">BUGSAY-MIS</h1>
          <p class="brand-sub">PHILIPPINE SCIENCE HIGH SCHOOL</p>
          <p class="brand-campus">Caraga Region Campus in Butuan City</p>
        </div>

        <!-- Tagline -->
        <p class="tagline">
          Navigating Together — Centralized Management Information System
          
        </p>

        <!-- Indigo glow blob -->
        <div class="glow-blob" aria-hidden="true" />
      </div>

      <!-- Version chip at bottom -->
      <p class="left-version">v {{ appVersion }} &nbsp;·&nbsp; &copy; 2026 PSHS-CRC</p>
    </div>

    <!-- ── Right panel (login form) ── -->
    <div class="right-panel">
      <div class="login-card">
        <!-- Logo (mobile only — left panel is hidden) -->
        <div class="mobile-brand">
          <img src="/images/pshslogo.png" alt="PSHS-CRC Logo" class="h-16 mx-auto mb-3 mobile-logo" />
          <h2 class="mobile-brand-title">BUGSAY-MIS</h2>
          <p class="mobile-brand-sub">Philippine Science High School – Caraga Region Campus in Butuan City</p>
        </div>

        <!-- Greeting -->
        <div class="card-header">
          <h2 class="card-title">Welcome back</h2>
          <p class="card-sub">Sign in with your official PSHS-CRC Google account to continue.</p>
        </div>

        <!-- Google login button -->
        <button @click="googleLogin" :disabled="isLoading" class="google-btn">
          <span class="google-icon-wrap">
            <img
              src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg"
              class="h-5 w-5"
            />
          </span>
          <span class="google-btn-text">
            {{ isLoading ? 'Signing in…' : 'Continue with Google' }}
          </span>
          <!-- spinner -->
          <svg
            v-if="isLoading"
            class="ml-auto h-4 w-4 animate-spin text-indigo-400"
            fill="none" viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"/>
          </svg>
        </button>

        <!-- Domain notice -->
        <p class="domain-notice">
          <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
          </svg>
          only <strong>@crc.pshs.edu.ph</strong> accounts are allowed.
        </p>

        <!-- Footer -->
        <p class="card-footer">
          &copy; 2026 PSHS-CRC &nbsp;·&nbsp; v {{ appVersion }}
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ── Root layout ── */
.login-root {
  display: flex;
  min-height: 100vh;
  background-color: #f8fafc; /* slate-50 */
}

/* ── Left panel ── */
.left-panel {
  position: relative;
  display: none;
  width: 45%;
  overflow: hidden;
  background-color: #0f172a; /* slate-900 */
  flex-direction: column;
  justify-content: space-between;
}
@media (min-width: 768px) {
  .left-panel { display: flex; }
}

.left-bg {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  opacity: 0.75;
  mix-blend-mode: luminosity;
}

.left-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(160deg, rgba(15,23,42,0.72) 0%, rgba(15,23,42,0.82) 50%, rgba(30,27,75,0.88) 100%);
}

.left-content {
  position: relative;
  z-index: 10;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex: 1;
  padding: 3rem 2.5rem;
  text-align: center;
}

.dot-grid {
  position: absolute;
  top: 2rem;
  right: 2rem;
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 6px;
  opacity: 0.15;
}
.dot {
  display: block;
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background-color: #94a3b8;
}

.brand {
  margin-bottom: 2rem;
}
.brand-logo {
  height: 96px;
  margin: 0 auto 1.25rem;
  filter: drop-shadow(0 0 24px rgba(99,102,241,0.5));
}
.brand-title {
  font-size: 2rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #ffffff;
  line-height: 1;
}
.brand-sub {
  margin-top: 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}
.brand-campus {
  font-size: 0.75rem;
  color: #64748b;
  margin-top: 0.2rem;
}

.tagline {
  font-size: 0.8rem;
  color: #94a3b8;
  line-height: 1.7;
  max-width: 260px;
  font-style: italic;
}

.glow-blob {
  position: absolute;
  bottom: -80px;
  left: 50%;
  transform: translateX(-50%);
  width: 280px;
  height: 280px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(99,102,241,0.3) 0%, transparent 70%);
  pointer-events: none;
}

.left-version {
  position: relative;
  z-index: 10;
  padding: 1rem 2rem;
  text-align: center;
  font-size: 0.7rem;
  color: #475569;
}

/* ── Right panel ── */
.right-panel {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1.5rem;
  position: relative;
  background-color: #0f172a;
  overflow: hidden;
}
.right-panel::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: url('/storage/bg.jpg');
  background-size: cover;
  background-position: center;
  opacity: 0.45;
  mix-blend-mode: luminosity;
  z-index: 0;
}
.right-panel::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(160deg, rgba(15,23,42,0.72) 0%, rgba(15,23,42,0.82) 50%, rgba(30,27,75,0.88) 100%);
  z-index: 1;
}
@media (min-width: 768px) {
  .right-panel {
    background-color: #f8fafc;
    overflow: visible;
  }
  .right-panel::before,
  .right-panel::after {
    display: none;
  }
}

/* ── Login card ── */
.login-card {
  width: 100%;
  max-width: 400px;
  position: relative;
  z-index: 2;
  animation: fadeUp 0.5s ease-out both;
}

.mobile-brand {
  text-align: center;
  margin-bottom: 2rem;
}
@media (min-width: 768px) {
  .mobile-brand { display: none; }
}
.mobile-logo {
  filter: drop-shadow(0 0 20px rgba(99,102,241,0.45));
}
.mobile-brand-title {
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #ffffff;
}
.mobile-brand-sub {
  font-size: 0.7rem;
  color: #94a3b8;
  margin-top: 0.25rem;
}
@media (min-width: 768px) {
  .mobile-brand-title { color: #1e293b; }
  .mobile-brand-sub   { color: #64748b; }
}

.card-header {
  margin-bottom: 2rem;
}
.card-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #ffffff;
  line-height: 1.2;
}
.card-sub {
  margin-top: 0.5rem;
  font-size: 0.875rem;
  color: #94a3b8;
  line-height: 1.6;
}
@media (min-width: 768px) {
  .card-title { color: #0f172a; }
  .card-sub   { color: #64748b; }
}

/* ── Google button ── */
.google-btn {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.25rem;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.625rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #1e293b;
  cursor: pointer;
  transition: background-color 0.15s, border-color 0.15s, box-shadow 0.15s, transform 0.15s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.07);
}
.google-btn:hover:not(:disabled) {
  background-color: #f8fafc;
  border-color: #c7d2fe;
  box-shadow: 0 4px 12px rgba(99,102,241,0.12);
  transform: translateY(-1px);
}
.google-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.google-icon-wrap {
  width: 32px;
  height: 32px;
  border-radius: 0.375rem;
  background-color: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.google-btn-text {
  flex: 1;
}

/* ── Domain notice ── */
.domain-notice {
  margin-top: 1rem;
  display: flex;
  align-items: flex-start;
  gap: 0.375rem;
  font-size: 0.75rem;
  color: #64748b;
  line-height: 1.5;
}
@media (max-width: 767px) {
  .domain-notice { color: #94a3b8; }
}

/* ── Footer ── */
.card-footer {
  margin-top: 2.5rem;
  font-size: 0.7rem;
  color: #94a3b8;
  text-align: center;
}
@media (min-width: 768px) {
  .card-footer { color: #cbd5e1; }
}

/* ── Animation ── */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>
