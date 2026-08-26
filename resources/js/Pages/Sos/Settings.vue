<script setup>
import { useForm } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { AdjustmentsHorizontalIcon, PhoneIcon, DevicePhoneMobileIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  tiers: Array,
  externalContacts: Array,
  roles: Array,
  users: Array,
})

const ALERT_TYPES = ['medical', 'security', 'fire_disaster', 'general']
const CHANNELS = ['in_app', 'sms', 'email']

const tierForm = useForm({
  alert_type: 'medical', order: 1, role_id: null, timeout_minutes: 10,
  channels: ['in_app'], notify_external: false, user_ids: [],
})

function submitTier() {
  tierForm.post(route('sos.settings.tiers.store'), { preserveScroll: true, onSuccess: () => tierForm.reset() })
}

function removeTier(tier) {
  tierForm.delete(route('sos.settings.tiers.destroy', tier.id), { preserveScroll: true })
}

const contactForm = useForm({
  name: '', org: '', phone: '', email: '', alert_types: [], channel: 'sms', active: true,
})

function submitContact() {
  contactForm.post(route('sos.settings.external-contacts.store'), { preserveScroll: true, onSuccess: () => contactForm.reset() })
}

function removeContact(contact) {
  contactForm.delete(route('sos.settings.external-contacts.destroy', contact.id), { preserveScroll: true })
}

const mobileForms = Object.fromEntries(
  props.users.map(u => [u.id, useForm({ mobile_number: u.employee_profile?.mobile_number ?? '' })])
)

function saveMobile(user) {
  mobileForms[user.id].post(route('sos.settings.responders.mobile', user.id), { preserveScroll: true })
}
</script>

<template>
  <Head title="SOS Settings" />
  <AdminLayout title="SOS Settings">
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <AdjustmentsHorizontalIcon class="h-4 w-4" /> Escalation Tiers
        </h2>

        <div v-if="tiers.length === 0" class="mb-2 rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-8 text-center text-sm text-slate-400">
          No escalation tiers configured yet.
        </div>
        <div v-for="tier in tiers" :key="tier.id" class="mb-2 flex items-center justify-between rounded-lg border border-slate-200 p-3 text-sm">
          <div>
            <strong>{{ tier.alert_type.replace('_', ' ') }}</strong> — tier {{ tier.order }} —
            {{ tier.role?.name ?? 'no role' }} — {{ tier.timeout_minutes ? tier.timeout_minutes + 'min' : 'final' }}
          </div>
          <button class="text-red-600 hover:underline" @click="removeTier(tier)">Remove</button>
        </div>

        <form class="mt-4 space-y-2 rounded-xl border border-slate-200 p-4" @submit.prevent="submitTier">
          <select v-model="tierForm.alert_type" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option v-for="t in ALERT_TYPES" :key="t" :value="t">{{ t.replace('_', ' ') }}</option>
          </select>
          <input v-model.number="tierForm.order" type="number" min="1" placeholder="Order" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <select v-model="tierForm.role_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option :value="null">No role (explicit users only)</option>
            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
          <input v-model.number="tierForm.timeout_minutes" type="number" min="1" placeholder="Timeout minutes (blank = final tier)" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <div class="flex gap-3 text-sm">
            <label v-for="c in CHANNELS" :key="c" class="flex items-center gap-1">
              <input type="checkbox" :value="c" v-model="tierForm.channels" /> {{ c }}
            </label>
          </div>
          <label class="flex items-center gap-1 text-sm"><input type="checkbox" v-model="tierForm.notify_external" /> Notify external contacts</label>
          <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Add Tier</button>
        </form>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <PhoneIcon class="h-4 w-4" /> External Contacts
        </h2>

        <div v-if="externalContacts.length === 0" class="mb-2 rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-8 text-center text-sm text-slate-400">
          No external contacts added yet.
        </div>
        <div v-for="contact in externalContacts" :key="contact.id" class="mb-2 flex items-center justify-between rounded-lg border border-slate-200 p-3 text-sm">
          <div>
            <strong>{{ contact.name }}</strong> ({{ contact.org }}) — {{ contact.phone }} — {{ contact.alert_types.join(', ') }}
          </div>
          <button class="text-red-600 hover:underline" @click="removeContact(contact)">Remove</button>
        </div>

        <form class="mt-4 space-y-2 rounded-xl border border-slate-200 p-4" @submit.prevent="submitContact">
          <input v-model="contactForm.name" placeholder="Name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <input v-model="contactForm.org" placeholder="Organization" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <input v-model="contactForm.phone" placeholder="Phone" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <input v-model="contactForm.email" placeholder="Email" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <div class="flex flex-wrap gap-3 text-sm">
            <label v-for="t in ALERT_TYPES" :key="t" class="flex items-center gap-1">
              <input type="checkbox" :value="t" v-model="contactForm.alert_types" /> {{ t.replace('_', ' ') }}
            </label>
          </div>
          <select v-model="contactForm.channel" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="sms">SMS</option>
            <option value="email">Email</option>
            <option value="both">Both</option>
          </select>
          <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Add Contact</button>
        </form>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
        <h2 class="mb-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <DevicePhoneMobileIcon class="h-4 w-4" /> Responder Mobile Numbers
        </h2>
        <p class="mb-3 text-xs text-slate-500">Used for the SMS channel on escalation tiers — a responder with no number set here won't receive SMS, only in-app/email.</p>

        <div v-if="users.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-8 text-center text-sm text-slate-400">
          No responders to configure.
        </div>
        <div v-for="user in users" :key="user.id" class="mb-2 flex items-center gap-2">
          <span class="w-48 truncate text-sm text-slate-700">{{ user.name }}</span>
          <input v-model="mobileForms[user.id].mobile_number" placeholder="09XXXXXXXXX" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <button class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200" @click="saveMobile(user)">Save</button>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
