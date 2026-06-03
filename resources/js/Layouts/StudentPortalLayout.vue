<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { AcademicCapIcon, ArrowRightStartOnRectangleIcon, HeartIcon, UserIcon } from '@heroicons/vue/24/outline'

defineProps({
    title: String,
})

const studentName = computed(() => {
    try { return window.__STUDENT_NAME__ ?? sessionStorage.getItem('student_name') ?? '' } catch { return '' }
})

function logout() {
    router.post(route('student-portal.logout'))
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-blue-50 flex flex-col">
        <!-- Top nav -->
        <header class="bg-white border-b border-slate-200 shadow-sm">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center shrink-0">
                        <AcademicCapIcon class="w-4 h-4 text-white" />
                    </div>
                    <div class="leading-none">
                        <p class="text-xs font-semibold text-indigo-700 uppercase tracking-wide">PSHS-CRC</p>
                        <p class="text-xs text-slate-500">Student Portal</p>
                    </div>
                </div>

                <nav class="flex items-center gap-1 sm:gap-2">
                    <a
                        :href="route('student-portal.dashboard')"
                        class="text-sm text-slate-600 hover:text-indigo-700 px-2 py-1.5 rounded-lg hover:bg-indigo-50 transition-colors"
                    >Dashboard</a>
                    <a
                        :href="route('student-portal.profile')"
                        class="text-sm text-slate-600 hover:text-indigo-700 px-2 py-1.5 rounded-lg hover:bg-indigo-50 transition-colors hidden sm:inline"
                    >Profile</a>
                    <a
                        :href="route('student-portal.medical')"
                        class="text-sm text-slate-600 hover:text-indigo-700 px-2 py-1.5 rounded-lg hover:bg-indigo-50 transition-colors hidden sm:inline"
                    >Medical</a>
                    <a
                        :href="route('student-portal.grades')"
                        class="text-sm text-slate-600 hover:text-indigo-700 px-2 py-1.5 rounded-lg hover:bg-indigo-50 transition-colors hidden sm:inline"
                    >Grades</a>
                    <button
                        @click="logout"
                        class="flex items-center gap-1 text-sm text-slate-400 hover:text-red-500 px-2 py-1.5 rounded-lg hover:bg-red-50 transition-colors"
                    >
                        <ArrowRightStartOnRectangleIcon class="w-4 h-4" />
                        <span class="hidden sm:inline">Sign out</span>
                    </button>
                </nav>
            </div>
        </header>

        <!-- Page content -->
        <main class="flex-1 max-w-4xl mx-auto w-full px-4 sm:px-6 py-6">
            <slot />
        </main>

        <footer class="text-center py-4 text-xs text-slate-400">
            Philippine Science High School – Caraga Region Campus
        </footer>
    </div>
</template>
