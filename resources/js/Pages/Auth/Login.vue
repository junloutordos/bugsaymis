<script setup>
import { Head } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { auth, provider, signInWithPopup } from '@/firebase'
import axios from 'axios'

const officialDomain = "@crc.pshs.edu.ph"

const googleLogin = async () => {
  try {
    const result = await signInWithPopup(auth, provider)
    const user = result.user

    // Restrict domain
    if (!user.email.endsWith(officialDomain)) {
      Swal.fire({
        icon: "error",
        title: "Unauthorized Email",
        text: "Only official PSHS-CRC accounts are allowed.",
      })
      return
    }

    // Send user to backend
    const response = await axios.post('/google/login', {
      email: user.email,
      name: user.displayName,
      uid: user.uid,
    })

    if (response.data.success) {
      const { redirect_to, role } = response.data

      Swal.fire({
        icon: "success",
        title: "Login Successful",
        text: `Welcome, ${user.displayName}! Redirecting to your ${role} portal...`,
        timer: 1500,
        showConfirmButton: false,
      })

      window.location.href = redirect_to
    } else {
      Swal.fire({
        icon: "error",
        title: "Login Failed",
        text: "Could not verify your account.",
      })
    }
  } catch (error) {
    console.error(error)
    Swal.fire({
      icon: "error",
      title: "Google Login Failed",
      text: error.response?.data?.message || error.message,
    })
  }
}
</script>

<template>
  <Head title="Login" />

  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-500 via-blue-300 to-white">
    <div class="w-full max-w-md bg-white shadow-xl rounded-2xl p-8">

      <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">
        <img src="/images/pshslogo.png" alt="PSHS-CRC Logo" class="h-16 mx-auto mb-4" />
        Welcome to <span class="text-indigo-600">BUGSAYMIS</span>
      </h1>

      <!-- Google Login -->
      <div class="mt-6">
        <button
          @click="googleLogin"
          type="button"
          class="w-full flex items-center justify-center py-2 px-4 bg-red-600 text-white font-semibold rounded-lg shadow hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition"
        >
          <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" class="h-5 w-5 mr-2" />
          Continue with Google
        </button>
      </div>
    </div>
  </div>
</template>
