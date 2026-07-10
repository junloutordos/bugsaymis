<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PrivacyPolicyContent from '@/Components/PrivacyPolicyContent.vue'
</script>

<template>
  <Head title="Data Privacy Policy" />
  <div class="min-h-screen bg-slate-50">
    <header class="bg-[#1A3557] text-white">
      <div class="max-w-4xl mx-auto px-4 py-5 flex items-center justify-between">
        <div>
          <p class="text-base font-semibold">PSHS-CRC MIS</p>
          <p class="text-xs text-slate-300">Philippine Science High School – Caraga Region Campus</p>
        </div>
        <Link href="/login" class="text-sm text-slate-200 hover:text-white underline">Sign in</Link>
      </div>
    </header>
    <main class="px-4 py-8">
      <PrivacyPolicyContent />
    </main>
  </div>
</template>
