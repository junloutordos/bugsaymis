<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import AppModal from '@/Components/AppModal.vue'

defineProps({
  devices: { type: Array, default: () => [] },
  guards: { type: Array, default: () => [] },
  accessLogs: { type: Array, default: () => [] },
})

const pinTarget = ref(null)
const pinForm = useForm({ pin: '', pin_confirmation: '' })

function openPin(guard) {
  pinTarget.value = guard
  pinForm.reset()
  pinForm.clearErrors()
}

function savePin() {
  pinForm.put(route('student-attendance.operators.pin', pinTarget.value.id), {
    preserveScroll: true,
    onSuccess: () => { pinTarget.value = null; pinForm.reset() },
  })
}

function toggleGuard(guard) {
  router.put(route('student-attendance.operators.status', guard.id), {
    is_active: !guard.is_active,
  }, { preserveScroll: true })
}

function revoke(device) {
  if (!window.confirm(`Revoke camera scanning access for ${device.name}?`)) return
  router.post(route('student-attendance.devices.revoke', device.id), {}, { preserveScroll: true })
}

function formatDate(value) {
  if (!value) return 'Never'
  return new Date(value).toLocaleString('en-PH', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit', hour12: true,
  })
}

function eventColor(event) {
  return { unlocked: 'green', locked: 'slate', expired: 'amber', failed: 'red' }[event] ?? 'slate'
}
</script>

<template>
  <Head title="Gate Attendance Devices" />
  <AdminLayout title="Gate Attendance Devices">
    <div class="space-y-5">
      <AppPageHeader
        title="Registered Gate iPads"
        subtitle="Review gate assignments and revoke kiosk access. New iPads are paired from the kiosk page."
      >
        <template #actions>
          <AppButton as="a" :href="route('student-attendance.kiosk')">Open Kiosk</AppButton>
        </template>
      </AppPageHeader>

      <AppTable :is-empty="!devices.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Device</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Gate</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Paired By</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Last Seen</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Action</th>
          </tr>
        </template>

        <tr v-for="device in devices" :key="device.id">
          <td class="px-4 py-3 font-medium text-slate-800">{{ device.name }}</td>
          <td class="px-4 py-3 text-slate-600">{{ device.gate_label }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="device.is_active ? 'green' : 'slate'">{{ device.is_active ? 'Active' : 'Revoked' }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-600">{{ device.paired_by ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-500">{{ formatDate(device.last_seen_at) }}</td>
          <td class="px-4 py-3 text-right">
            <AppButton v-if="device.is_active" size="sm" variant="danger" @click="revoke(device)">Revoke</AppButton>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No registered gate iPads" description="Open the kiosk on an iPad as an Administrator to pair it." />
        </template>
      </AppTable>

      <section class="space-y-3">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Security Guard PIN Access</h2>
          <p class="text-sm text-slate-500">PINs are six digits, stored only as hashes, and can be reset but never viewed.</p>
        </div>

        <AppTable :is-empty="!guards.length" :skeleton-cols="5">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Security Guard</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PIN</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Access</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Last Changed</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
            </tr>
          </template>

          <tr v-for="guard in guards" :key="guard.id">
            <td class="px-4 py-3 font-medium text-slate-800">{{ guard.name }}</td>
            <td class="px-4 py-3"><AppBadge :color="guard.has_pin ? 'green' : 'amber'">{{ guard.has_pin ? 'Configured' : 'Not set' }}</AppBadge></td>
            <td class="px-4 py-3"><AppBadge :color="guard.is_active ? 'green' : 'slate'">{{ guard.is_active ? 'Enabled' : 'Disabled' }}</AppBadge></td>
            <td class="px-4 py-3 text-sm text-slate-500">
              {{ guard.pin_changed_at ? formatDate(guard.pin_changed_at) : '—' }}
              <span v-if="guard.set_by" class="block text-xs text-slate-400">by {{ guard.set_by }}</span>
            </td>
            <td class="px-4 py-3 text-right space-x-2">
              <AppButton size="sm" variant="secondary" @click="openPin(guard)">{{ guard.has_pin ? 'Reset PIN' : 'Set PIN' }}</AppButton>
              <AppButton v-if="guard.has_pin" size="sm" :variant="guard.is_active ? 'danger' : 'secondary'" @click="toggleGuard(guard)">
                {{ guard.is_active ? 'Disable' : 'Enable' }}
              </AppButton>
            </td>
          </tr>

          <template #empty>
            <EmptyState title="No Security Guard users" description="Assign the Security Guard role to a user before setting a kiosk PIN." />
          </template>
        </AppTable>
      </section>

      <section class="space-y-3">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Recent Kiosk Access</h2>
          <p class="text-sm text-slate-500">The latest guard unlock, lock, expiry, and failed PIN events.</p>
        </div>

        <AppTable :is-empty="!accessLogs.length" :skeleton-cols="5">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Time</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Event</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Guard</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Device</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Reason</th>
            </tr>
          </template>
          <tr v-for="log in accessLogs" :key="log.id">
            <td class="px-4 py-3 text-sm text-slate-500">{{ formatDate(log.occurred_at) }}</td>
            <td class="px-4 py-3"><AppBadge :color="eventColor(log.event)">{{ log.event }}</AppBadge></td>
            <td class="px-4 py-3 text-sm text-slate-700">{{ log.operator ?? 'Unknown' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ log.device ?? 'Unknown' }}</td>
            <td class="px-4 py-3 text-sm text-slate-500">{{ log.reason?.replaceAll('_', ' ') ?? '—' }}</td>
          </tr>
          <template #empty><EmptyState title="No kiosk access events" /></template>
        </AppTable>
      </section>
    </div>

    <AppModal :show="!!pinTarget" :title="`${pinTarget?.has_pin ? 'Reset' : 'Set'} Gate Scanner PIN`" :subtitle="pinTarget?.name" @close="pinTarget = null">
      <form class="space-y-4" @submit.prevent="savePin">
        <label class="block text-sm font-medium text-slate-700">
          Six-digit PIN
          <input v-model="pinForm.pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-center text-2xl tracking-[0.4em] focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </label>
        <label class="block text-sm font-medium text-slate-700">
          Confirm PIN
          <input v-model="pinForm.pin_confirmation" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-center text-2xl tracking-[0.4em] focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </label>
        <p v-if="pinForm.errors.pin" class="text-sm text-red-600">{{ pinForm.errors.pin }}</p>
        <div class="flex justify-end gap-2 pt-2">
          <AppButton type="button" variant="secondary" @click="pinTarget = null">Cancel</AppButton>
          <AppButton type="submit" :disabled="pinForm.processing">{{ pinForm.processing ? 'Saving…' : 'Save PIN' }}</AppButton>
        </div>
      </form>
    </AppModal>
  </AdminLayout>
</template>
