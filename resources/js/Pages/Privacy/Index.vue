<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PrivacyPolicyContent from '@/Components/PrivacyPolicyContent.vue'
</script>

<template>
  <Head title="Data Privacy Policy" />
  <AdminLayout title="Data Privacy Policy">
    <PrivacyPolicyContent />
  </AdminLayout>
</template>
