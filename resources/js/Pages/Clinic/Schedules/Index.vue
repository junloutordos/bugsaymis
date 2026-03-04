<template>
  <div>
    <h1 class="text-2xl font-bold mb-4">Doctor Schedules</h1>
    <div class="bg-white rounded p-6 shadow">
      <p class="text-gray-700">Doctor schedules have been removed from the system. Use physician schedules where applicable.</p>
    </div>
  </div>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import Pagination from '@/Components/Pagination.vue'
import Swal from 'sweetalert2'

// Doctor schedules UI removed — no client-side actions required

</script>

<style scoped>
.table-auto th, .table-auto td { border-bottom: 1px solid #eee }
</style>
