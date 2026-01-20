<template>
  <Head title="Work Requests" />
  <AdminLayout title="Work Requests">
    <div class="p-6">
      <div v-if="page.props.flash?.success" class="mb-4">
        <div class="px-4 py-3 rounded bg-green-50 border border-green-100 text-green-700">{{ page.props.flash.success }}</div>
      </div>

      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Work Requests</h1>
        <button
          v-if="page.props.auth?.user?.role?.name !== 'GSU Head'"
          @click.prevent="openModal()"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
        >
          + New Request
        </button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <div class="hidden sm:block overflow-x-auto">
          <table class="table-fixed w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Issue</th>
                <th class="px-4 py-3 text-left">Description</th>
                <th class="px-4 py-3 text-left">Assigned Personnel</th>
                <th class="px-4 py-3 text-left">Acted By</th>
                <th class="px-4 py-3 text-left">Expected Completion</th>
                <th class="px-4 py-3 text-left">Action Taken</th>
                <th class="px-4 py-3 text-left">Date Completed</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="wr in props.workRequests" :key="wr.id">
                <td class="px-4 py-3">{{ wr.id }}</td>
                <td class="px-4 py-3">{{ wr.issue ?? '—' }}</td>
                <td class="px-4 py-3">{{ wr.description ?? '—' }}</td>
                <td class="px-4 py-3">{{ wr.assigned_user?.name ?? '—' }}</td>
                <td class="px-4 py-3">{{ wr.actedBy?.name ?? '—' }}</td>
                <td class="px-4 py-3">{{ wr.expected_completion_date ? new Date(wr.expected_completion_date).toLocaleDateString() : '—' }}</td>
                <td class="px-4 py-3">{{ wr.action_taken ?? '—' }}</td>
                <td class="px-4 py-3">{{ wr.date_completed ? new Date(wr.date_completed).toLocaleDateString() : '—' }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-2 justify-center">
                    <button
                      v-if="page.props.auth?.user?.role?.name === 'Administrator'"
                      @click.prevent="openModal(wr)"
                      class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700"
                      title="Edit"
                    >
                      <PencilSquareIcon class="w-5 h-5" />
                    </button>

                    <button
                      v-if="page.props.auth?.user?.role?.name === 'Administrator'"
                      @click.prevent="destroy(wr)"
                      class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700"
                      title="Delete"
                    >
                      <TrashIcon class="w-5 h-5" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="props.workRequests.length === 0">
                <td colspan="9" class="px-4 py-6 text-center text-gray-500">No work requests found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile list -->
        <div v-if="props.workRequests.length === 0" class="text-center text-gray-500 py-6">No work requests found.</div>
      </div>

      <!-- Modal -->
      <div v-show="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">{{ editingId ? 'Edit Work Request' : 'New Work Request' }}</h2>
          <form @submit.prevent="submitForm" class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700">Issue <span class="text-red-500">*</span></label>
              <input v-model="form.issue" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Description</label>
              <textarea v-model="form.description" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" rows="4"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Priority</label>
                <select v-model="form.priority" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                  <option>Low</option>
                  <option>Normal</option>
                  <option>High</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Expected Completion</label>
                <input v-model="form.expected_completion_date" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
              </div>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, usePage, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  divisions: Array,
  offices: Array,
  users: Array,
  workRequests: Array,
})

const page = usePage();

const showModal = ref(false)
const editingId = ref(null)

const form = useForm({
  issue: '',
  description: '',
  priority: 'Normal',
  location_division_id: '',
  location_office_id: '',
  expected_completion_date: '',
})

const openModal = (wr = null) => {
  editingId.value = wr ? wr.id : null
  if (wr) {
    form.reset()
    form.issue = wr.issue ?? ''
    form.description = wr.description ?? ''
    form.priority = wr.priority ?? 'Normal'
    form.location_division_id = wr.location_division_id ?? ''
    form.location_office_id = wr.location_office_id ?? ''
    form.expected_completion_date = wr.expected_completion_date ?? ''
  } else {
    form.reset()
    form.priority = 'Normal'
  }
  showModal.value = true
}

const closeModal = () => { showModal.value = false; editingId.value = null; form.reset() }

const submitForm = () => {
  if (editingId.value) {
    form.put(`/work-requests/${editingId.value}`, {
      onSuccess: () => { closeModal(); window.location.reload() },
      onError: (errors) => { alert(Object.values(errors).flat().join('\n')) }
    })
  } else {
    form.post('/work-requests', {
      onSuccess: () => { closeModal(); window.location.reload() },
      onError: (errors) => { alert(Object.values(errors).flat().join('\n')) }
    })
  }
}

const destroy = (wr) => {
  if (!confirm('Delete this work request?')) return
  import('@inertiajs/vue3').then(({ router }) => {
    router.delete(`/work-requests/${wr.id}`, {
      onSuccess: () => { window.location.reload() },
      onError: (e) => { alert('Failed to delete request') }
    })
  })
}
</script>
