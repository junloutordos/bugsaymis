<script setup>
import { Head } from '@inertiajs/vue3'

const card = {
    name: 'Junlou Tordos',
    title: 'Information Systems Analyst I',
    agency: 'Philippine Science High School–Caraga Region Campus',
    phone: '+639555598885',
    phoneDisplay: '+63 955 559 8885',
    email: 'jtordos@crc.pshs.edu.ph',
    linkedin: 'https://www.linkedin.com/in/junlou-tordos-9538a9307',
    linkedinDisplay: 'linkedin.com/in/junlou-tordos',
}

function downloadVCard() {
    const vcf = [
        'BEGIN:VCARD',
        'VERSION:3.0',
        'FN:Junlou Tordos',
        'N:Tordos;Junlou;;;',
        'TITLE:Information Systems Analyst I',
        'ORG:Philippine Science High School-Caraga Region Campus',
        'TEL;TYPE=CELL:+639555598885',
        'EMAIL:jtordos@crc.pshs.edu.ph',
        'URL:https://www.linkedin.com/in/junlou-tordos-9538a9307',
        'END:VCARD',
    ].join('\r\n')

    const blob = new Blob([vcf], { type: 'text/vcard;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'junlou-tordos.vcf'
    a.click()
    URL.revokeObjectURL(url)
}

async function shareCard() {
    if (navigator.share) {
        await navigator.share({
            title: card.name,
            text: `${card.name} — ${card.title}, ${card.agency}`,
            url: window.location.href,
        })
    } else {
        await navigator.clipboard.writeText(window.location.href)
        alert('Link copied to clipboard!')
    }
}
</script>

<template>
    <Head title="Junlou Tordos — Digital Card" />

    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 flex items-center justify-center p-4">

        <!-- Card -->
        <div class="w-full max-w-sm">

            <!-- Header band -->
            <div class="bg-gradient-to-r from-indigo-600 to-blue-500 rounded-t-3xl px-6 pt-8 pb-16 relative overflow-hidden">
                <!-- Decorative circles -->
                <div class="absolute -top-8 -right-8 w-40 h-40 rounded-full bg-white/5"></div>
                <div class="absolute -bottom-4 -left-8 w-32 h-32 rounded-full bg-white/5"></div>

                <!-- PSHS badge -->
                <div class="relative z-10 mb-6">
                    <span class="text-xs font-semibold tracking-widest text-indigo-200 uppercase">PSHS–CRC</span>
                </div>

                <!-- Avatar -->
                <div class="relative z-10 flex justify-center">
                    <div class="w-24 h-24 rounded-full bg-white/20 backdrop-blur border-4 border-white/30 flex items-center justify-center shadow-2xl">
                        <span class="text-3xl font-bold text-white select-none">JT</span>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="bg-white rounded-b-3xl -mt-8 px-6 pt-10 pb-6 shadow-2xl">

                <!-- Name + title -->
                <div class="text-center mb-6">
                    <h1 class="text-2xl font-bold text-slate-800 tracking-tight">{{ card.name }}</h1>
                    <p class="text-sm font-semibold text-indigo-600 mt-1">{{ card.title }}</p>
                    <p class="text-xs text-slate-500 mt-1 leading-snug">{{ card.agency }}</p>
                </div>

                <!-- Divider -->
                <div class="border-t border-slate-100 mb-5"></div>

                <!-- Contact info -->
                <div class="space-y-3">

                    <!-- Phone -->
                    <a :href="`tel:${card.phone}`" class="flex items-center gap-3 group">
                        <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0 group-hover:bg-indigo-100 transition-colors">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 leading-none mb-0.5">Mobile</p>
                            <p class="text-sm font-medium text-slate-700">{{ card.phoneDisplay }}</p>
                        </div>
                    </a>

                    <!-- Email -->
                    <a :href="`mailto:${card.email}`" class="flex items-center gap-3 group">
                        <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0 group-hover:bg-indigo-100 transition-colors">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-400 leading-none mb-0.5">Email</p>
                            <p class="text-sm font-medium text-slate-700 truncate">{{ card.email }}</p>
                        </div>
                    </a>

                    <!-- LinkedIn -->
                    <a :href="card.linkedin" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 group">
                        <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0 group-hover:bg-indigo-100 transition-colors">
                            <svg class="w-4 h-4 text-indigo-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-400 leading-none mb-0.5">LinkedIn</p>
                            <p class="text-sm font-medium text-slate-700 truncate">{{ card.linkedinDisplay }}</p>
                        </div>
                    </a>

                </div>

                <!-- Action buttons -->
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button
                        @click="downloadVCard"
                        class="flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white px-4 py-3 rounded-xl text-sm font-semibold transition-colors shadow-sm shadow-indigo-200"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                        </svg>
                        Save Contact
                    </button>
                    <button
                        @click="shareCard"
                        class="flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 active:bg-slate-300 text-slate-700 px-4 py-3 rounded-xl text-sm font-semibold transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z"/>
                        </svg>
                        Share
                    </button>
                </div>

                <!-- Footer -->
                <p class="text-center text-xs text-slate-300 mt-5">PSHS–CRC MIS &middot; mis.crc.pshs.edu.ph</p>
            </div>
        </div>
    </div>
</template>
