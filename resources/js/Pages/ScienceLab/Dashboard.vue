<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  BeakerIcon, WrenchScrewdriverIcon, ClipboardDocumentCheckIcon,
  ExclamationTriangleIcon, ArchiveBoxIcon, CalendarDaysIcon,
  ClockIcon, CubeIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  cards: Object,
  calibrationDue: Array,
  lowStock: Array,
  expiringSoon: Array,
  upcoming: Array,
  can: Object,
})

const stat = (label, value, icon, tone = 'indigo', href = null) => ({ label, value, icon, tone, href })

const tiles = [
  stat('Laboratories', props.cards.laboratories, BeakerIcon, 'indigo', route('science-lab.laboratories.index')),
  stat('Equipment', props.cards.equipment, CubeIcon, 'slate', route('science-lab.equipment.index')),
  stat('Equipment Down', props.cards.equipmentDown, WrenchScrewdriverIcon, 'amber', route('science-lab.maintenance.index')),
  stat('Open Repairs', props.cards.openRepairs, WrenchScrewdriverIcon, 'rose', route('science-lab.maintenance.index')),
  stat('Calibration Due (30d)', props.cards.calibrationDue, ClipboardDocumentCheckIcon, 'violet', route('science-lab.calibration.index')),
  stat('Low Stock Items', props.cards.lowStock, ArchiveBoxIcon, 'amber', route('science-lab.inventory.index')),
  stat('Expiring Soon', props.cards.expiringSoon, ExclamationTriangleIcon, 'rose', route('science-lab.inventory.index')),
  stat('Pending Reservations', props.cards.pendingReservations, CalendarDaysIcon, 'emerald', route('science-lab.reservations.index')),
]

const toneClass = {
  indigo: 'bg-indigo-50 text-indigo-600',
  slate: 'bg-slate-100 text-slate-600',
  amber: 'bg-amber-50 text-amber-600',
  rose: 'bg-rose-50 text-rose-600',
  violet: 'bg-violet-50 text-violet-600',
  emerald: 'bg-emerald-50 text-emerald-600',
}
</script>

<template>
  <Head title="Science Laboratory Management" />
  <AdminLayout title="Science Laboratory Management">
    <div class="space-y-6">
      <p class="text-sm text-slate-500">
        Manage science laboratories, equipment, reagents, maintenance, calibration, safety and waste — CIM 4.4.
      </p>

      <!-- Stat tiles -->
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        <Link v-for="t in tiles" :key="t.label" :href="t.href || '#'"
          class="rounded-xl border border-slate-200 bg-white p-4 transition hover:shadow-md">
          <div class="flex items-center gap-3">
            <span :class="['flex h-10 w-10 items-center justify-center rounded-lg', toneClass[t.tone]]">
              <component :is="t.icon" class="h-5 w-5" />
            </span>
            <div>
              <div class="text-2xl font-semibold text-slate-800">{{ t.value }}</div>
              <div class="text-xs text-slate-500">{{ t.label }}</div>
            </div>
          </div>
        </Link>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- Calibration due -->
        <section class="rounded-xl border border-slate-200 bg-white">
          <header class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-700">Calibration Due / Overdue</h2>
            <Link :href="route('science-lab.calibration.index')" class="text-xs text-indigo-600 hover:underline">View all</Link>
          </header>
          <ul class="divide-y divide-slate-50">
            <li v-for="c in calibrationDue" :key="c.id" class="flex items-center justify-between px-5 py-2.5 text-sm">
              <div>
                <div class="font-medium text-slate-700">{{ c.equipment }}</div>
                <div class="text-xs text-slate-400">{{ c.property_no || '—' }}</div>
              </div>
              <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', c.status === 'overdue' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600']">
                {{ c.due_date }}
              </span>
            </li>
            <li v-if="!calibrationDue.length" class="px-5 py-6 text-center text-sm text-slate-400">Nothing due.</li>
          </ul>
        </section>

        <!-- Upcoming reservations -->
        <section class="rounded-xl border border-slate-200 bg-white">
          <header class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-700">Upcoming Laboratory Use</h2>
            <Link :href="route('science-lab.reservations.index')" class="text-xs text-indigo-600 hover:underline">View all</Link>
          </header>
          <ul class="divide-y divide-slate-50">
            <li v-for="r in upcoming" :key="r.id" class="flex items-center justify-between px-5 py-2.5 text-sm">
              <div>
                <div class="font-medium text-slate-700">{{ r.room || 'Lab' }} — {{ r.subject || '—' }}</div>
                <div class="text-xs text-slate-400">{{ r.control_no }}</div>
              </div>
              <div class="flex items-center gap-1 text-xs text-slate-500">
                <ClockIcon class="h-4 w-4" />{{ r.date }} {{ r.time_start }}
              </div>
            </li>
            <li v-if="!upcoming.length" class="px-5 py-6 text-center text-sm text-slate-400">No upcoming bookings.</li>
          </ul>
        </section>

        <!-- Low stock -->
        <section class="rounded-xl border border-slate-200 bg-white">
          <header class="border-b border-slate-100 px-5 py-3"><h2 class="text-sm font-semibold text-slate-700">Low Stock</h2></header>
          <ul class="divide-y divide-slate-50">
            <li v-for="s in lowStock" :key="s.id" class="flex items-center justify-between px-5 py-2.5 text-sm">
              <span class="text-slate-700">{{ s.name }}</span>
              <span class="text-xs font-medium text-amber-600">{{ s.balance }} / {{ s.reorder_level }} {{ s.unit }}</span>
            </li>
            <li v-if="!lowStock.length" class="px-5 py-6 text-center text-sm text-slate-400">Stock levels healthy.</li>
          </ul>
        </section>

        <!-- Expiring reagents -->
        <section class="rounded-xl border border-slate-200 bg-white">
          <header class="border-b border-slate-100 px-5 py-3"><h2 class="text-sm font-semibold text-slate-700">Expiring Reagents (30d)</h2></header>
          <ul class="divide-y divide-slate-50">
            <li v-for="e in expiringSoon" :key="e.id" class="flex items-center justify-between px-5 py-2.5 text-sm">
              <div>
                <div class="text-slate-700">{{ e.name }}</div>
                <div class="text-xs text-slate-400">Lot {{ e.lot_no || '—' }} · {{ e.quantity }}</div>
              </div>
              <span class="text-xs font-medium text-rose-600">{{ e.expiry_date }}</span>
            </li>
            <li v-if="!expiringSoon.length" class="px-5 py-6 text-center text-sm text-slate-400">Nothing expiring soon.</li>
          </ul>
        </section>
      </div>
    </div>
  </AdminLayout>
</template>
