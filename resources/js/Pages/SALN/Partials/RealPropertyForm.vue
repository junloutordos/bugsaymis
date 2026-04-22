<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

    <div class="sm:col-span-2">
      <FieldInput label="Description / Kind of Property" v-model="form.kind" required
        placeholder="e.g. Residential House and Lot, Agricultural Land"
        :error="form.errors?.kind" />
    </div>

    <div class="sm:col-span-2">
      <FieldInput label="Exact Location" v-model="form.exact_location" required
        placeholder="Street, Barangay, Municipality/City, Province"
        :error="form.errors?.exact_location" />
    </div>

    <div>
      <FieldInput label="Assessed Value (₱)" v-model="form.assessed_value"
        type="number" min="0" step="0.01"
        :error="form.errors?.assessed_value" />
    </div>

    <div>
      <FieldInput label="Current Fair Market Value (₱)" v-model="form.current_fair_market_value"
        type="number" min="0" step="0.01" required
        :error="form.errors?.current_fair_market_value" />
    </div>

    <div>
      <FieldInput label="Year Acquired" v-model="form.year_acquired"
        type="number" min="1900" :max="currentYear"
        :error="form.errors?.year_acquired" />
    </div>

    <div>
      <FieldInput label="Acquisition Cost (₱)" v-model="form.acquisition_cost"
        type="number" min="0" step="0.01"
        :error="form.errors?.acquisition_cost" />
    </div>

    <div class="sm:col-span-2">
      <FieldInput label="Mode of Acquisition" type="select" v-model="form.acquisition_mode"
        placeholder="— Select mode —"
        :error="form.errors?.acquisition_mode">
        <option value="purchased">Bought / Purchased</option>
        <option value="inherited">Inherited</option>
        <option value="gift">Gift / Donation</option>
        <option value="others">Others</option>
      </FieldInput>
    </div>

    <div v-if="form.acquisition_mode === 'others'" class="sm:col-span-2">
      <FieldInput label="Specify Other Mode" v-model="form.acquisition_mode_other"
        :error="form.errors?.acquisition_mode_other" />
    </div>

    <div class="sm:col-span-2">
      <FieldInput label="Owner" type="select" v-model="form.owner"
        :error="form.errors?.owner">
        <option value="self">Self</option>
        <option value="spouse">Spouse</option>
        <option value="joint">Joint</option>
      </FieldInput>
    </div>

  </div>
</template>

<script setup>
import FieldInput from './FieldInput.vue'

defineProps({ form: Object })

const currentYear = new Date().getFullYear()
</script>
