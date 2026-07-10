<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
</script>

<template>
  <Head title="Account & Data Deletion — AtlasGo" />
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
      <div class="max-w-4xl mx-auto space-y-5">
        <AppPageHeader
          title="Account & Data Deletion"
          subtitle="How to request deletion of your AtlasGo / PSHS-CRC MIS account and associated data"
        />

        <AppCard>
          <div class="space-y-4 text-sm text-slate-700 leading-relaxed">
            <p>
              AtlasGo is the official mobile app of the Philippine Science High School – Caraga Region Campus (PSHS-CRC) Management Information System. Accounts (for students, parents/guardians, and staff) are provisioned and managed by the school. This page explains how you can request the deletion of your account and the personal data associated with it.
            </p>
          </div>
        </AppCard>

        <AppCard>
          <div class="space-y-4 text-sm text-slate-700 leading-relaxed">
            <h2 class="text-base font-bold text-slate-800">How to request deletion</h2>
            <p>To request deletion of your account and associated personal data, send a request to our Data Privacy Officer:</p>
            <div class="bg-slate-50 rounded-lg border border-slate-200 p-4 space-y-1">
              <p class="font-semibold text-slate-800">Compliance Officer for Privacy (COP)</p>
              <p>
                Email:
                <a href="mailto:privacy@crc.pshs.edu.ph" class="text-indigo-600 hover:text-indigo-700 font-medium">privacy@crc.pshs.edu.ph</a>
              </p>
            </div>
            <p>Please include in your request:</p>
            <ul class="list-disc list-inside space-y-1.5 text-slate-600 pl-2">
              <li>Your full name;</li>
              <li>The email address associated with your AtlasGo account;</li>
              <li>Your role (student, parent/guardian, or staff) and, for students, the PISAY/student ID if available;</li>
              <li>A statement that you are requesting deletion of your account and associated data.</li>
            </ul>
            <p>
              We will verify your identity before acting on the request to protect your data. Once verified, we will process your request within a reasonable period as required by the Data Privacy Act of 2012.
            </p>
          </div>
        </AppCard>

        <AppCard>
          <div class="space-y-4 text-sm text-slate-700 leading-relaxed">
            <h2 class="text-base font-bold text-slate-800">What is deleted and what may be retained</h2>
            <p>
              Upon a verified request, we will delete or de-identify the personal data collected through the AtlasGo app that is associated with your account, including your name, email address, profile photo, user account identifiers, and content you submitted through the app.
            </p>
            <p>
              As a government educational institution, PSHS-CRC is required by law to retain certain student and employment records (for example, academic records, enrolment records, and official documents) for the periods mandated by the Civil Service Commission, the Department of Education, the National Archives of the Philippines, and other applicable regulations. Such records are retained only as required by these mandates and are handled in accordance with our
              <a href="/privacy-policy" class="text-indigo-600 hover:text-indigo-700 font-medium underline">Data Privacy Policy</a>.
            </p>
          </div>
        </AppCard>

        <div class="text-center text-xs text-slate-400 pb-4">
          Philippine Science High School – Caraga Region Campus · AtlasGo · Account &amp; Data Deletion
        </div>
      </div>
    </main>
  </div>
</template>
