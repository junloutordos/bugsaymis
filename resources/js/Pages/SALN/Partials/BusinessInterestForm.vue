<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

    <div class="sm:col-span-2">
      <FieldInput label="Name of Entity / Business" v-model="form.entity_name" required
        placeholder="Registered business or organization name"
        :error="form.errors?.entity_name" />
    </div>

    <div class="sm:col-span-2">
      <FieldInput label="Business Address" v-model="form.business_address"
        placeholder="Street, City/Municipality, Province"
        :error="form.errors?.business_address" />
    </div>

    <div class="sm:col-span-2">
      <FieldInput label="Nature of Business / Financial Interest" v-model="form.nature_of_business"
        placeholder="e.g. Retail, Real Estate, Shares/Stocks, Partnership"
        :error="form.errors?.nature_of_business" />
    </div>

    <div>
      <FieldInput label="Date Acquired" v-model="form.date_acquired"
        type="date"
        :error="form.errors?.date_acquired" />
    </div>

    <div>
      <FieldInput label="Acquisition Cost (₱)" v-model="form.acquisition_cost"
        type="number" min="0" step="0.01"
        :error="form.errors?.acquisition_cost" />
    </div>

    <div>
      <FieldInput label="Present Fair Market Value (₱)" v-model="form.present_fair_market_value"
        type="number" min="0" step="0.01"
        :error="form.errors?.present_fair_market_value" />
    </div>

    <div>
      <FieldInput label="Owner" type="select" v-model="form.owner"
        :error="form.errors?.owner">
        <option value="self">Self</option>
        <option value="spouse">Spouse</option>
      </FieldInput>
    </div>

  </div>
</template>

<script setup>
import FieldInput from './FieldInput.vue'

defineProps({ form: Object })
</script>
