<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

    <div class="sm:col-span-2">
      <FieldInput label="Description" v-model="form.description" required
        placeholder="e.g. Toyota Vios 2020, Diamond Ring, Company Shares"
        :error="form.errors?.description" />
    </div>

    <div class="sm:col-span-2">
      <FieldInput label="Category" type="select" v-model="form.category" required
        placeholder="— Select category —"
        :error="form.errors?.category">
        <option value="motor_vehicle">Motor Vehicle</option>
        <option value="jewelry">Jewelry / Gems</option>
        <option value="artwork">Artwork / Collectibles</option>
        <option value="shares_of_stock">Shares of Stock</option>
        <option value="cash_on_hand">Cash on Hand</option>
        <option value="bank_deposits">Bank Deposits / Savings</option>
        <option value="receivables">Receivables / Loans Receivable</option>
        <option value="others">Others</option>
      </FieldInput>
    </div>

    <div>
      <FieldInput label="Year Acquired" v-model="form.year_acquired"
        type="number" min="1900" :max="currentYear"
        :error="form.errors?.year_acquired" />
    </div>

    <div>
      <FieldInput label="Acquisition Cost (₱)" v-model="form.acquisition_cost"
        type="number" min="0" step="0.01" required
        :error="form.errors?.acquisition_cost" />
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
