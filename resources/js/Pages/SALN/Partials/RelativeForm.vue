<template>
  <div class="space-y-3">

    <div class="bg-warning-50 border border-warning-100 rounded-lg px-3 py-2 text-xs text-warning-700">
      Disclose relatives within the 4th civil degree of consanguinity or affinity who are in government service (R.A. 6713, Sec. 8).
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

      <div class="sm:col-span-2">
        <FieldInput label="Full Name of Relative" v-model="form.name" required
          placeholder="Last Name, First Name Middle Name"
          :error="form.errors?.name" />
      </div>

      <div>
        <FieldInput label="Relationship" v-model="form.relationship" required
          placeholder="e.g. Brother, Mother, Cousin (3rd degree)"
          :error="form.errors?.relationship" />
      </div>

      <div>
        <FieldInput label="Position / Designation" v-model="form.position"
          placeholder="e.g. Teacher III, Clerk II"
          :error="form.errors?.position" />
      </div>

      <div class="sm:col-span-2">
        <FieldInput label="Agency / Office" v-model="form.agency_office" required
          placeholder="e.g. DepEd Division of Cebu City"
          :error="form.errors?.agency_office" />
      </div>

      <div class="sm:col-span-2">
        <FieldInput label="Agency Address" v-model="form.agency_address"
          placeholder="Street, City/Municipality, Province"
          :error="form.errors?.agency_address" />
      </div>

    </div>
  </div>
</template>

<script setup>
import FieldInput from './FieldInput.vue'

defineProps({ form: Object })
</script>
