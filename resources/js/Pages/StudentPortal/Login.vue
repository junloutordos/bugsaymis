<script setup>
import { ref } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { AcademicCapIcon } from '@heroicons/vue/24/outline'
import { auth, provider, signInWithPopup } from '@/firebase'
import axios from 'axios'

const flash      = usePage().props.flash ?? {}
const isLoading  = ref(false)
const errorMsg   = ref('')

const OFFICIAL_DOMAIN = '@crc.pshs.edu.ph'

async function googleLogin() {
    if (isLoading.value) return
    errorMsg.value = ''

    try {
        isLoading.value = true

        const { user } = await signInWithPopup(auth, provider)

        if (!user.email?.toLowerCase().endsWith(OFFICIAL_DOMAIN)) {
            errorMsg.value = 'Only official PSHS-CRC accounts (@crc.pshs.edu.ph) are allowed.'
            return
        }

        const { data } = await axios.post(route('student-portal.auth.firebase'), {
            email: user.email,
            name:  user.displayName,
            uid:   user.uid,
        })

        if (!data?.success) {
            errorMsg.value = data?.message ?? 'Authentication failed. Please try again.'
            return
        }

        // Redirect — either to dashboard (already linked) or link page (first time)
        window.location.href = data.redirect_to

    } catch (err) {
        errorMsg.value = err.response?.data?.message ?? err.message ?? 'Sign-in failed. Please try again.'
    } finally {
        isLoading.value = false
    }
}
</script>

<template>
    <Head title="Student Portal — Sign In" />

    <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-blue-50 flex items-center justify-center px-4">
        <div class="w-full max-w-sm space-y-6">

            <!-- Logo -->
            <div class="text-center space-y-2">
                <div class="w-16 h-16 rounded-2xl bg-indigo-600 flex items-center justify-center mx-auto shadow-lg">
                    <AcademicCapIcon class="w-8 h-8 text-white" />
                </div>
                <h1 class="text-xl font-bold text-slate-800">Student Portal</h1>
                <p class="text-sm text-slate-500">PSHS–Caraga Region Campus</p>
            </div>

            <!-- Flash / error messages -->
            <div v-if="flash.error || errorMsg" class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">
                {{ flash.error || errorMsg }}
            </div>
            <div v-if="flash.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-lg px-4 py-3">
                {{ flash.success }}
            </div>

            <!-- Sign-in card -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 space-y-5">
                <p class="text-sm text-slate-600 text-center">
                    Sign in with your official PSHS-CRC school account to access and update your guidance and medical records.
                </p>

                <button
                    @click="googleLogin"
                    :disabled="isLoading"
                    class="flex items-center justify-center gap-3 w-full bg-white border border-slate-300 hover:bg-slate-50 hover:border-slate-400 text-slate-700 font-medium text-sm rounded-lg py-2.5 transition-colors shadow-sm disabled:opacity-60 disabled:cursor-wait"
                >
                    <!-- Google "G" logo -->
                    <svg v-if="!isLoading" class="w-5 h-5 shrink-0" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    <svg v-else class="w-4 h-4 shrink-0 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    {{ isLoading ? 'Signing in…' : 'Sign in with Google' }}
                </button>

                <p class="text-xs text-slate-400 text-center">
                    Use your <span class="font-medium">@crc.pshs.edu.ph</span> school account only.
                </p>
            </div>

            <p class="text-xs text-center text-slate-400">
                First time? After signing in, you'll be asked to enter your PISAY System ID to link your record.
            </p>
        </div>
    </div>
</template>
