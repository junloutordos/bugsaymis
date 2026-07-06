<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'

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

async function destroy(contact) {
  if (! await confirmDelete(`Remove parent contact "${contact.name}"?`)) return
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
    <div class="space-y-5">

      <AppPageHeader title="Parent Contacts" subtitle="Manage parents who receive gate scan notifications">
        <template #actions>
          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            Add Contact
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Table -->
      <AppTable :is-empty="!contacts.data.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Email</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Mobile</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Linked Students</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Notify</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">FCM</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="contact in contacts.data" :key="contact.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 font-medium text-slate-800">{{ contact.name }}</td>
          <td class="px-4 py-3 text-slate-600">{{ contact.email ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-600">{{ contact.mobile_phone ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-500 text-xs max-w-xs truncate">{{ studentList(contact) }}</td>
          <td class="px-4 py-3">
            <div class="flex gap-1.5 flex-wrap">
              <AppBadge v-if="contact.notify_email" color="blue">Email</AppBadge>
              <AppBadge v-if="contact.notify_push" color="purple">Push</AppBadge>
              <AppBadge v-if="contact.notify_sms" color="green">SMS</AppBadge>
              <span v-if="!contact.notify_email && !contact.notify_push && !contact.notify_sms" class="text-slate-400 text-xs">None</span>
            </div>
          </td>
          <td class="px-4 py-3">
            <AppBadge v-if="contact.fcm_device_token" color="green">Registered</AppBadge>
            <span v-else class="text-slate-400 text-xs">—</span>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <AppIconButton label="Edit contact" variant="ghost" size="sm" @click="openEdit(contact)">
                <PencilIcon class="h-4 w-4" />
              </AppIconButton>
              <AppIconButton label="Delete contact" variant="danger" size="sm" @click="destroy(contact)">
                <TrashIcon class="h-4 w-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="contact in contacts.data" :key="contact.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800 text-sm">{{ contact.name }}</p>
                <p class="text-xs text-slate-500">{{ contact.email ?? '—' }} &middot; {{ contact.mobile_phone ?? '—' }}</p>
              </div>
              <div class="flex items-center gap-1 shrink-0">
                <AppIconButton label="Edit contact" variant="ghost" size="sm" @click="openEdit(contact)">
                  <PencilIcon class="h-4 w-4" />
                </AppIconButton>
                <AppIconButton label="Delete contact" variant="danger" size="sm" @click="destroy(contact)">
                  <TrashIcon class="h-4 w-4" />
                </AppIconButton>
              </div>
            </div>
            <p class="text-xs text-slate-500">{{ studentList(contact) }}</p>
            <div class="flex gap-1.5 flex-wrap">
              <AppBadge v-if="contact.notify_email" color="blue">Email</AppBadge>
              <AppBadge v-if="contact.notify_push" color="purple">Push</AppBadge>
              <AppBadge v-if="contact.notify_sms" color="green">SMS</AppBadge>
              <AppBadge v-if="contact.fcm_device_token" color="green">FCM Registered</AppBadge>
              <span v-if="!contact.notify_email && !contact.notify_push && !contact.notify_sms" class="text-slate-400 text-xs">No notifications</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No parent contacts yet" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="contacts.current_page"
            :total-pages="contacts.last_page"
            :total="contacts.total"
            @prev="goTo(contacts.prev_page_url)"
            @next="goTo(contacts.next_page_url)"
          />
        </template>
      </AppTable>

    </div>

    <!-- Add / Edit Modal -->
    <AppModal
      :show="showModal"
      :title="editTarget ? 'Edit Contact' : 'Add Parent Contact'"
      @close="closeModal"
    >
      <form @submit.prevent="save" class="space-y-4">
        <AppInput
          v-model="form.name" type="text" label="Full Name" required
          :error="form.errors.name"
        />

        <AppInput
          v-model="form.email" type="email" label="Email"
          :error="form.errors.email"
        />

        <AppInput
          v-model="form.mobile_phone" type="text" label="Mobile Phone"
        />

        <div>
          <AppInput
            v-model="form.fcm_device_token" type="text" label="FCM Device Token"
            placeholder="Registered by mobile app"
          />
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
        <p v-if="form.notify_sms && !form.mobile_phone" class="text-warning-600 text-xs -mt-2">
          SMS requires a mobile phone number above.
        </p>

        <div class="flex justify-end gap-3 pt-2">
          <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
          <AppButton type="submit" :loading="form.processing" :disabled="form.processing">
            {{ form.processing ? 'Saving…' : 'Save' }}
          </AppButton>
        </div>
      </form>
    </AppModal>

  </AdminLayout>
</template>
