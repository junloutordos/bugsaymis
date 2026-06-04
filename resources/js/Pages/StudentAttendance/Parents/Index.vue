<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, PencilIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const page     = usePage()
const contacts = computed(() => page.props.contacts ?? { data: [], current_page: 1, last_page: 1 })

// ── Modal state ───────────────────────────────────────────────────────────────
const showModal  = ref(false)
const editTarget = ref(null)   // null = create, object = editing

const form = useForm({
  name:             '',
  email:            '',
  mobile_phone:     '',
  fcm_device_token: '',
  notify_email:     true,
  notify_push:      true,
  notify_sms:       false,
})

function openCreate() {
  editTarget.value = null
  form.reset()
  form.notify_email = true
  form.notify_push  = true
  form.notify_sms   = false
  showModal.value   = true
}

function openEdit(contact) {
  editTarget.value        = contact
  form.name               = contact.name
  form.email              = contact.email ?? ''
  form.mobile_phone       = contact.mobile_phone ?? ''
  form.fcm_device_token   = contact.fcm_device_token ?? ''
  form.notify_email       = !! contact.notify_email
  form.notify_push        = !! contact.notify_push
  form.notify_sms         = !! contact.notify_sms
  showModal.value         = true
}

function closeModal() {
  showModal.value = false
  form.reset()
  form.clearErrors()
}

function save() {
  if (editTarget.value) {
    form.put(route('student-attendance.parents.update', editTarget.value.id), {
      preserveScroll: true,
      onSuccess: closeModal,
    })
  } else {
    form.post(route('student-attendance.parents.store'), {
      preserveScroll: true,
      onSuccess: closeModal,
    })
  }
}

function destroy(contact) {
  if (! confirm(`Remove parent contact "${contact.name}"?`)) return
  useForm({}).delete(route('student-attendance.parents.destroy', contact.id), {
    preserveScroll: true,
  })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function goTo(url) {
  if (url) window.location.href = url
}

function studentList(contact) {
  if (! contact.students?.length) return '—'
  return contact.students.map(s => `${s.lastname ?? ''}, ${s.firstname ?? ''}`.trim().replace(/^,\s*/, '')).join('; ')
}
</script>

<template>
  <Head title="Parent Contacts" />
  <AdminLayout title="Parent Contacts">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
      <div>
        <h1 class="text-xl font-semibold text-slate-800">Parent Contacts</h1>
        <p class="text-sm text-slate-500 mt-0.5">Manage parents who receive gate scan notifications</p>
      </div>
      <button
        @click="openCreate"
        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
      >
        <PlusIcon class="h-4 w-4" />
        Add Contact
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Email</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Mobile</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Linked Students</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Notify</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">FCM</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="contact in contacts.data" :key="contact.id" class="hover:bg-slate-50/60">
              <td class="px-4 py-3 font-medium text-slate-800">{{ contact.name }}</td>
              <td class="px-4 py-3 text-slate-600">{{ contact.email ?? '—' }}</td>
              <td class="px-4 py-3 text-slate-600">{{ contact.mobile_phone ?? '—' }}</td>
              <td class="px-4 py-3 text-slate-500 text-xs max-w-xs truncate">{{ studentList(contact) }}</td>
              <td class="px-4 py-3">
                <div class="flex gap-1.5 flex-wrap">
                  <span v-if="contact.notify_email" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700 font-medium">Email</span>
                  <span v-if="contact.notify_push"  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-violet-100 text-violet-700 font-medium">Push</span>
                  <span v-if="contact.notify_sms"   class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700 font-medium">SMS</span>
                  <span v-if="!contact.notify_email && !contact.notify_push && !contact.notify_sms" class="text-slate-400 text-xs">None</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <span v-if="contact.fcm_device_token" class="text-emerald-600 text-xs font-medium">Registered</span>
                <span v-else class="text-slate-400 text-xs">—</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <button @click="openEdit(contact)" class="text-slate-400 hover:text-indigo-600 transition-colors">
                    <PencilIcon class="h-4 w-4" />
                  </button>
                  <button @click="destroy(contact)" class="text-slate-400 hover:text-red-500 transition-colors">
                    <TrashIcon class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="contacts.data.length === 0">
              <td colspan="7" class="py-16 text-center text-slate-400">No parent contacts yet</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
        <button @click="goTo(contacts.prev_page_url)" :disabled="!contacts.prev_page_url"
          class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-40">Prev</button>
        <span>Page {{ contacts.current_page }} of {{ contacts.last_page }}</span>
        <button @click="goTo(contacts.next_page_url)" :disabled="!contacts.next_page_url"
          class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-40">Next</button>
      </div>
    </div>

    <!-- Add / Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-semibold text-slate-800">{{ editTarget ? 'Edit Contact' : 'Add Parent Contact' }}</h2>
            <button @click="closeModal" class="text-slate-400 hover:text-slate-600">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="save" class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Full Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" required
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                :class="{ 'border-red-400': form.errors.name }" />
              <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Email</label>
              <input v-model="form.email" type="email"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                :class="{ 'border-red-400': form.errors.email }" />
              <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Mobile Phone</label>
              <input v-model="form.mobile_phone" type="text"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">FCM Device Token</label>
              <input v-model="form.fcm_device_token" type="text" placeholder="Registered by mobile app"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full font-mono text-xs" />
              <p class="text-slate-400 text-xs mt-1">Leave blank — mobile app registers this automatically</p>
            </div>

            <div class="flex flex-wrap gap-4">
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="form.notify_email" type="checkbox" class="rounded text-indigo-600" />
                <span class="text-sm text-slate-700">Email</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="form.notify_push" type="checkbox" class="rounded text-indigo-600" />
                <span class="text-sm text-slate-700">Push</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="form.notify_sms" type="checkbox" class="rounded text-indigo-600" />
                <span class="text-sm text-slate-700">SMS</span>
              </label>
            </div>
            <p v-if="form.notify_sms && !form.mobile_phone" class="text-amber-600 text-xs -mt-2">
              SMS requires a mobile phone number above.
            </p>

            <div class="flex justify-end gap-3 pt-2">
              <button type="button" @click="closeModal"
                class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
              <button type="submit" :disabled="form.processing"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg disabled:opacity-60">
                {{ form.processing ? 'Saving…' : 'Save' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
