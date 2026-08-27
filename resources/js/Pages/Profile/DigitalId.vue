<script setup>
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeftIcon, ArrowPathIcon, PrinterIcon } from '@heroicons/vue/24/outline'
import { storageUrl } from '@/Composables/useStorage.js'

const props = defineProps({
  employee:   Object,
  emergency:  Object,
  qr_svg:     String,
  verify_url: String,
  back_route: String,
  print_route: String,
  ocd:        Object,
})

const photoUrl = computed(() => storageUrl(props.employee.profile_picture))

const initials = computed(() => {
  const parts = (props.employee.name || '').split(',').map(s => s.trim()).filter(Boolean)
  if (parts.length >= 2) return (parts[1][0] || '') + (parts[0][0] || '')
  const words = (parts[0] || '').split(/\s+/).filter(Boolean)
  return words.length >= 2 ? words[0][0] + words[1][0] : (words[0]?.[0] || '')
})

// Stored name is in filing order ("Lastname, Firstname M.I."). The digital
// card reads more naturally in reading order — reverse only for display here.
const displayName = computed(() => {
  const raw = props.employee.name || ''
  const commaIndex = raw.indexOf(',')
  if (commaIndex === -1) return raw
  const lastName = raw.slice(0, commaIndex).trim()
  const rest = raw.slice(commaIndex + 1).trim()
  return `${rest} ${lastName}`.trim()
})

// Front-first: card starts unflipped, showing the front face.
const isFlipped = ref(false)
</script>

<template>
  <Head title="Employee Digital ID" />

  <div class="min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 px-4 py-8">
    <div class="mx-auto mb-6 flex w-full max-w-sm items-center justify-between">
      <Link :href="back_route" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-300 hover:text-white">
        <ArrowLeftIcon class="h-4 w-4" /> Back
      </Link>
      <span class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">Digital ID</span>
    </div>

    <!-- Flip card -->
    <div class="mx-auto w-full max-w-sm" style="perspective: 1600px">
      <div
        class="relative w-full transition-transform duration-700 ease-out"
        style="transform-style: preserve-3d"
        :style="{ transform: isFlipped ? 'rotateY(180deg)' : 'rotateY(0deg)' }"
      >
        <!-- Front face -->
        <div class="w-full [backface-visibility:hidden]">
          <div
            class="relative overflow-hidden rounded-t-3xl px-6 pb-16 pt-6"
            style="background: linear-gradient(135deg, #060e50 0%, #1447c0 65%, #0093b8 100%)"
          >
            <div class="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/5"></div>
            <div class="absolute -bottom-4 -left-8 h-32 w-32 rounded-full bg-white/5"></div>

            <div class="relative z-10 flex items-start justify-between gap-2">
              <div class="flex items-center gap-2">
                <img src="/images/pshslogo.png" class="h-9 w-9 flex-shrink-0 object-contain" alt="" onerror="this.style.display='none'" />
                <div class="leading-tight">
                  <p class="text-[8px] font-medium text-indigo-100">Republic of the Philippines</p>
                  <p class="text-[8px] font-medium text-indigo-100">Department of Science and Technology</p>
                  <p class="text-[11px] font-bold uppercase tracking-wide text-white">Philippine Science High School</p>
                  <p class="text-[8px] font-bold uppercase tracking-wide text-indigo-100">Caraga Region Campus in Butuan City</p>
                </div>
              </div>
              <span
                class="inline-flex flex-shrink-0 items-center gap-1 rounded-full border px-2 py-1 text-[9px] font-semibold uppercase tracking-wide backdrop-blur"
                :class="employee.is_active ? 'border-white/30 bg-white/15 text-white' : 'border-white/20 bg-black/20 text-white/70'"
              >
                <span class="h-1.5 w-1.5 rounded-full" :class="employee.is_active ? 'bg-emerald-300' : 'bg-slate-300'"></span>
                {{ employee.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>

            <div class="relative z-10 mt-5 flex justify-center">
              <div class="flex h-32 w-32 items-center justify-center overflow-hidden rounded-2xl border-4 border-white/30 bg-white/20 shadow-2xl backdrop-blur">
                <img v-if="photoUrl" :src="photoUrl" class="h-full w-full object-cover" style="object-position: center 20%" alt="" />
                <span v-else class="select-none text-3xl font-bold text-white">{{ initials }}</span>
              </div>
            </div>
          </div>

          <div class="-mt-8 rounded-b-3xl bg-white px-6 pb-6 pt-10 shadow-2xl">
            <div class="mb-5 text-center">
              <h1 class="break-words text-xl font-bold leading-snug tracking-tight text-slate-800">{{ displayName }}</h1>
              <p class="mt-1 text-sm font-semibold text-indigo-600">{{ employee.position || '—' }}</p>
              <p v-if="employee.office || employee.division" class="mt-1 text-xs text-slate-500">
                {{ employee.office || employee.division }}
              </p>
            </div>

            <div v-if="employee.employee_no" class="mb-5 rounded-xl bg-slate-50 px-4 py-2.5 text-center">
              <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Employee ID Number</p>
              <p class="mt-0.5 text-sm font-bold tracking-wider text-slate-800">{{ employee.employee_no }}</p>
            </div>

            <div class="flex flex-col items-center">
              <div class="rounded-2xl border border-slate-100 bg-white p-3 [&>svg]:h-32 [&>svg]:w-32" v-html="qr_svg"></div>
              <p class="mt-2 text-[11px] font-medium uppercase tracking-wide text-slate-400">Scan to verify</p>
            </div>

            <button type="button" class="mt-5 flex w-full items-center justify-center gap-1.5 text-xs font-medium text-indigo-600" @click="isFlipped = true">
              <ArrowPathIcon class="h-3.5 w-3.5" /> Flip to view back
            </button>
          </div>
        </div>

        <!-- Back face -->
        <div class="absolute inset-0 flex h-full w-full flex-col [backface-visibility:hidden]" style="transform: rotateY(180deg)">
          <div
            class="relative overflow-hidden rounded-t-3xl px-6 py-4 text-center"
            style="background: linear-gradient(135deg, #060e50 0%, #1447c0 65%, #0093b8 100%)"
          >
            <div class="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/5"></div>
            <span class="relative z-10 text-xs font-semibold uppercase tracking-widest text-white">Employee Information</span>
          </div>

          <div class="flex flex-1 flex-col rounded-b-3xl bg-white px-6 py-5 shadow-2xl overflow-y-auto">
            <div class="space-y-3">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Date of Birth</p>
                <p class="text-sm font-medium text-slate-700">{{ employee.date_of_birth || '—' }}</p>
              </div>
              <div v-if="employee.residential_address">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Residential Address</p>
                <p class="text-sm font-medium text-slate-700">{{ employee.residential_address }}</p>
              </div>
            </div>

            <div class="my-4 border-t border-slate-100"></div>

            <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-600">In Case of Emergency, Notify</p>
            <div class="mt-3 space-y-3">
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Contact Person</p>
                <p class="text-sm font-medium text-slate-700">{{ emergency.contact_name || '—' }}</p>
              </div>
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Mobile No.</p>
                <p class="text-sm font-medium text-slate-700">{{ emergency.contact_phone || '—' }}</p>
              </div>
              <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Address</p>
                <p class="text-sm font-medium text-slate-700">{{ emergency.contact_address || '—' }}</p>
              </div>
            </div>

            <div class="my-4 border-t border-slate-100"></div>

            <p class="text-center text-[11px] leading-relaxed text-slate-500">
              This card identifies the bearer as an employee of the Philippine Science High School – Caraga
              Region Campus. Non-transferable; must be surrendered upon separation from the service.
            </p>

            <button type="button" class="mt-auto flex w-full items-center justify-center gap-1.5 pt-4 text-xs font-medium text-indigo-600" @click="isFlipped = false">
              <ArrowPathIcon class="h-3.5 w-3.5" /> Flip to view front
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="mx-auto mt-6 flex w-full max-w-sm flex-col items-center gap-3 text-center">
      <Link
        :href="print_route"
        target="_blank"
        class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-5 py-2.5 text-sm font-semibold text-white backdrop-blur transition-colors hover:bg-white/15"
      >
        <PrinterIcon class="h-4 w-4" /> Print ID Card
      </Link>
      <p class="max-w-xs text-[11px] leading-relaxed text-slate-400">
        The QR code links to a live verification page showing your current employment status.
      </p>
    </div>
  </div>
</template>
