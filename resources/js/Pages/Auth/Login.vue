<script setup>
import { ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import axios from 'axios'
import { auth, provider, signInWithPopup } from '@/firebase'
import { usePage } from '@inertiajs/vue3'

/* --------------------------------------------------------------------------
 | Constants
 * -------------------------------------------------------------------------- */
const OFFICIAL_DOMAIN = '@crc.pshs.edu.ph'
const BG_IMAGE_URL = '/storage/bg.jpg'

/* --------------------------------------------------------------------------
 | State
 * -------------------------------------------------------------------------- */
const isLoading = ref(false)

/* --------------------------------------------------------------------------
 | Helpers
 * -------------------------------------------------------------------------- */
const showAlert = (options) => {
  Swal.fire({
    confirmButtonColor: '#2563eb',
    ...options,
  })
}

const isAuthorizedEmail = (email) =>
  email?.toLowerCase().endsWith(OFFICIAL_DOMAIN)

// Show server-side errors (e.g. inactive account) using SweetAlert
const page = usePage()
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

/* --------------------------------------------------------------------------
 | Actions
 * -------------------------------------------------------------------------- */
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

    if (!data?.success) {
      throw new Error('Account verification failed')
    }

    showAlert({
      icon: 'success',
      title: 'Login Successful',
      text: `Welcome, ${user.displayName}! Redirecting to your portal...`,
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

  <div class="page-container">
    <!-- Background -->
    <div
      class="absolute inset-0 bg-cover bg-center"
      :style="{ backgroundImage: `url(${BG_IMAGE_URL})` }"
    />
    <div class="absolute inset-0 bg-black/30" />

    <!-- Decorative blobs -->
    <div class="blob blob-pink" />
    <div class="blob blob-blue" />

    <!-- Content Wrapper -->
    <div class="relative z-10 flex flex-col items-center justify-center min-h-screen px-4">
      <!-- Login Card -->
      <div class="login-card">
        <!-- Header -->
        <header class="text-center mb-8">
          <img
            src="/images/pshslogo.png"
            alt="PSHS-CRC Logo"
            class="h-28 mx-auto mb-4"
          />
          <h1 class="text-3xl font-extrabold text-blue-600 tracking-wide">
            BUGSAY-MIS
          </h1>
          <p class="mt-2 text-sm text-gray-600">
            <strong>PHILIPPINE SCIENCE HIGH SCHOOL</strong><br />
            <span class="text-xs">Caraga Region Campus in Butuan City</span>
          </p>
        </header>

        <!-- Login Button -->
        <button
          @click="googleLogin"
          :disabled="isLoading"
          class="login-button"
        >
          <img
            src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg"
            class="h-7 w-7 mr-4"
          />
          <span><small>
            {{ isLoading ? 'Signing in…' : 'Login PSHS-CRC Google Account' }}
            </small>
          </span>
        </button>
      </div>

      <!-- ✅ PAGE FOOTER (OUTSIDE CARD) -->
      <footer class="page-footer">
        <p class="italic">
          Navigating Together: Centralized Management Information System of <br>
          Philippine Science High School – Caraga Region Campus in Butuan City
        </p><br><br><br>
        <p class="mt-1"><strong>&copy; 2026 PSHS-CRC. All Rights Reserved.<br>v 1.0.0</strong></p>
      </footer>
    </div>
  </div>
</template>

<style scoped>
/* --------------------------------------------------
 | Page Container
 * -------------------------------------------------- */
.page-container {
  @apply relative overflow-hidden;
}

/* --------------------------------------------------
 | Login Card
 * -------------------------------------------------- */
.login-card {
  @apply w-full max-w-md bg-white rounded-3xl shadow-2xl p-10;
  animation: fadeIn 0.8s ease-out forwards;
}

/* --------------------------------------------------
 | Button
 * -------------------------------------------------- */
.login-button {
  @apply w-full flex items-center justify-center py-3 px-4
         bg-blue-600 text-white font-semibold rounded-xl
         shadow-lg transition transform
         hover:bg-blue-700 hover:-translate-y-1 hover:scale-105
         focus:outline-none focus:ring-4 focus:ring-blue-500 focus:ring-offset-2
         disabled:opacity-60 disabled:cursor-not-allowed;
}

/* --------------------------------------------------
 | Page Footer (Outside Card)
 * -------------------------------------------------- */
.page-footer {
  @apply mt-8 text-center text-xs text-white/80 max-w-lg;
}

/* --------------------------------------------------
 | Decorative Blobs
 * -------------------------------------------------- */
.blob {
  @apply absolute rounded-full blur-3xl opacity-70;
  animation: blob 7s infinite;
}

.blob-pink {
  @apply top-10 left-10 w-40 h-40 bg-pink-400/20;
}

.blob-blue {
  @apply bottom-20 right-20 w-60 h-60 bg-indigo-400/20;
  animation-delay: 2s;
}

/* --------------------------------------------------
 | Animations
 * -------------------------------------------------- */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes blob {
  0%, 100% { transform: translate(0, 0) scale(1); }
  33% { transform: translate(20px, -30px) scale(1.1); }
  66% { transform: translate(-20px, 20px) scale(0.9); }
}
</style>
