<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
  pdsList: Object,
})
</script>

<template>
  <AdminLayout title="All PDS">
    <div class="bg-white shadow rounded p-6">
      <h1 class="text-xl font-bold mb-4">Personal Data Sheets</h1>

      <table class="w-full border">
        <thead>
          <tr class="bg-gray-100">
            <th class="p-2">Name</th>
            <th class="p-2">Email</th>
            <th class="p-2">Created</th>
            <th class="p-2">Action</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="pds in pdsList.data" :key="pds.id">
            <td class="p-2">{{ pds.user.name }}</td>
            <td class="p-2">{{ pds.user.email }}</td>
            <td class="p-2">{{ pds.created_at }}</td>
            <td class="p-2">
              <Link
                :href="route('pds.show', pds.id)"
                class="text-blue-600 hover:underline"
              >
                View
              </Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
