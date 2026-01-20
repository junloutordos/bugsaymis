<template>
  <Head title="Buildings" />
  <AdminLayout title="Buildings">
    <div class="p-6">
      <div v-if="page.props.flash?.success" class="mb-4">
        <div class="px-4 py-3 rounded bg-green-50 border border-green-100 text-green-700">{{ page.props.flash.success }}</div>
      </div>

      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Buildings</h1>
        <button @click.prevent="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">+ New Building</button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <div class="hidden sm:block overflow-x-auto">
          <table class="table-fixed w-full border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-left">Code</th>
                <th class="px-4 py-3 text-left">No of Rooms</th>
                <th class="px-4 py-3 text-left">Occupants</th>
                <th class="px-4 py-3 text-left">Remarks</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="b in props.buildings" :key="b.id">
                <td class="px-4 py-3">{{ b.id }}</td>
                <td class="px-4 py-3">{{ b.name }}</td>
                <td class="px-4 py-3">{{ b.code ?? '—' }}</td>
                <td class="px-4 py-3">{{ b.no_of_rooms ?? '—' }}</td>
                <td class="px-4 py-3">{{ b.occupants_count ?? 0 }}</td>
                <td class="px-4 py-3">{{ b.remarks ?? '—' }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center gap-2 justify-center">
                    <button @click.prevent="openModal(b)" class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-700" title="Edit">
                      <PencilSquareIcon class="h-5 w-5" />
                    </button>
                    <button @click.prevent="destroy(b)" class="p-2 rounded-full bg-red-100 hover:bg-red-200 text-red-700" title="Delete">
                      <TrashIcon class="h-5 w-5" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="props.buildings.length === 0">
                <td colspan="7" class="px-4 py-6 text-center text-gray-500">No buildings found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal -->
      <div v-show="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">{{ editingId ? 'Edit Building' : 'New Building' }}</h2>
          <form @submit.prevent="submitForm" class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Code</label>
              <input v-model="form.code" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">No of Rooms</label>
              <input v-model="form.no_of_rooms" type="number" min="0" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Remarks</label>
              <textarea v-model="form.remarks" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" rows="3"></textarea>
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
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ buildings: Array })
const page = usePage()

const showModal = ref(false)
const editingId = ref(null)

const form = useForm({ name: '', code: '', no_of_rooms: '', remarks: '' })

const openModal = (b = null) => {
  editingId.value = b ? b.id : null
  if (b) {
    form.reset()
    form.name = b.name
    form.code = b.code
    form.no_of_rooms = b.no_of_rooms
    form.remarks = b.remarks
  } else {
    form.reset()
  }
  showModal.value = true
}

const closeModal = () => { showModal.value = false; editingId.value = null; form.reset() }

const submitForm = () => {
  if (editingId.value) {
    form.put(`/data-management/buildings/${editingId.value}`, {
      onSuccess: () => { closeModal(); window.location.reload() },
      onError: (errors) => { alert(Object.values(errors).flat().join('\n')) }
    })
  } else {
    form.post('/data-management/buildings', {
      onSuccess: () => { closeModal(); window.location.reload() },
      onError: (errors) => { alert(Object.values(errors).flat().join('\n')) }
    })
  }
}

const destroy = (b) => {
  if (!confirm('Delete this building?')) return
  import('@inertiajs/vue3').then(({ router }) => {
    router.delete(`/data-management/buildings/${b.id}`, {
      onSuccess: () => { window.location.reload() },
      onError: () => { alert('Failed to delete') }
    })
  })
}
</script>
