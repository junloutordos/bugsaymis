<script setup>
import { ref } from 'vue'
import { Head, usePage, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ vehicles: Array })
const page = usePage()

const showModal = ref(false)
const editing = ref(null)

const form = useForm({ name: '', plate_number: '', description: '', capacity: '', status: 'Good Working' })

const openCreate = () => { editing.value = null; form.reset(); showModal.value = true }
const openEdit = (v) => { editing.value = v; form.name = v.name; form.plate_number = v.plate_number ?? ''; form.description = v.description; form.capacity = v.capacity; form.status = v.status ?? 'Good Working'; showModal.value = true }

const submit = () => {
  if (editing.value) {
    form.put(route('vehicles.update', editing.value.id), {
      onSuccess: () => {
        showModal.value = false
        editing.value = null
        form.reset()
      },
      onError: (errors) => {
        console.error('Vehicle update errors', errors)
      }
    })
  } else {
    form.post(route('vehicles.store'), {
      onSuccess: () => {
        showModal.value = false
        form.reset()
      },
      onError: (errors) => {
        console.error('Vehicle create errors', errors)
      }
    })
  }
}

const destroy = (id) => {
  if (!confirm('Delete vehicle?')) return
  router.delete(route('vehicles.destroy', id))
}
</script>

<template>
  <Head title="Vehicles" />
  <AdminLayout title="Vehicles">
    <div class="p-6">
      <div v-if="page.props.flash?.success" class="mb-4">
        <div class="px-4 py-3 rounded bg-green-50 border border-green-100 text-green-700">{{ page.props.flash.success }}</div>
      </div>

      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Vehicles</h1>
        <button @click="openCreate" class="bg-blue-600 text-white px-4 py-2 rounded">+ Add Vehicle</button>
      </div>

      <div class="bg-white rounded-xl shadow p-4">
        <table class="min-w-full border border-gray-200">
          <thead class="bg-gray-100 text-gray-700 uppercase text-sm">
            <tr>
              <th class="px-4 py-3 text-left">#</th>
              <th class="px-4 py-3 text-left">Name</th>
              <th class="px-4 py-3 text-left">Plate Number</th>
              <th class="px-4 py-3 text-left">Capacity</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 text-sm">
            <tr v-for="v in props.vehicles" :key="v.id">
              <td class="px-4 py-3">{{ v.id }}</td>
              <td class="px-4 py-3">{{ v.name }}</td>
              <td class="px-4 py-3">{{ v.plate_number ?? '—' }}</td>
              <td class="px-4 py-3">{{ v.capacity ?? '—' }}</td>
              <td class="px-4 py-3">{{ v.status ?? 'Good Working' }}</td>
              <td class="px-4 py-3">
                <div class="flex gap-2">
                  <button @click="openEdit(v)" class="px-2 py-1 border rounded">Edit</button>
                  <button @click="destroy(v.id)" class="px-2 py-1 border rounded text-red-600">Delete</button>
                </div>
              </td>
            </tr>
            <tr v-if="props.vehicles.length===0">
              <td colspan="4" class="px-4 py-6 text-center text-gray-500">No vehicles added.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
        <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="showModal=false">✕</button>
        <h2 class="text-xl font-semibold mb-4">{{ editing ? 'Edit Vehicle' : 'Add Vehicle' }}</h2>

          <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Name</label>
            <input v-model="form.name" type="text" class="mt-1 block w-full rounded border-gray-300" />
            <p v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Plate Number</label>
            <input v-model="form.plate_number" type="text" class="mt-1 block w-full rounded border-gray-300" />
            <p v-if="form.errors.plate_number" class="text-red-600 text-sm mt-1">{{ form.errors.plate_number }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Capacity</label>
            <input v-model.number="form.capacity" type="number" min="1" class="mt-1 block w-32 rounded border-gray-300" />
            <p v-if="form.errors.capacity" class="text-red-600 text-sm mt-1">{{ form.errors.capacity }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <select v-model="form.status" class="mt-1 block w-48 rounded border-gray-300">
              <option value="Good Working">Good Working</option>
              <option value="Under Repair">Under Repair</option>
            </select>
            <p v-if="form.errors.status" class="text-red-600 text-sm mt-1">{{ form.errors.status }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Description</label>
            <textarea v-model="form.description" class="mt-1 block w-full rounded border-gray-300"></textarea>
          </div>

          <div class="flex gap-2">
            <button @click.prevent="submit" :disabled="form.processing" class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
            <button @click.prevent="showModal=false" class="px-4 py-2 rounded border">Cancel</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
