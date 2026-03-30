<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

    <div class="sm:col-span-2">
      <FieldInput label="Nature of Liability" type="select" v-model="form.nature" required
        placeholder="— Select type —"
        :error="form.errors?.nature">
        <option value="housing_loan">Housing Loan (PAG-IBIG / Bank)</option>
        <option value="car_loan">Car Loan</option>
        <option value="personal_loan">Personal / Emergency Loan</option>
        <option value="credit_card">Credit Card Balance</option>
        <option value="gsis_policy_loan">GSIS Policy Loan</option>
        <option value="gsis_salary_loan">GSIS Salary Loan</option>
        <option value="pagibig_loan">PAG-IBIG Multi-Purpose / Calamity Loan</option>
        <option value="sss_loan">SSS Salary Loan</option>
        <option value="landbank_salary_loan">LandBank Salary Loan</option>
        <option value="business_loan">Business Loan</option>
        <option value="others">Others</option>
      </FieldInput>
    </div>

    <div v-if="form.nature === 'others'" class="sm:col-span-2">
      <FieldInput label="Specify Other Liability" v-model="form.nature_other"
        :error="form.errors?.nature_other" />
    </div>

    <div class="sm:col-span-2">
      <FieldInput label="Name of Creditor" v-model="form.creditor_name" required
        placeholder="e.g. Land Bank of the Philippines, BPI, GSIS"
        :error="form.errors?.creditor_name" />
    </div>

    <div class="sm:col-span-2">
      <FieldInput label="Outstanding Balance (₱)" v-model="form.outstanding_balance"
        type="number" min="0" step="0.01" required
        :error="form.errors?.outstanding_balance" />
    </div>

  </div>
</template>

<script setup>
import FieldInput from './FieldInput.vue'

defineProps({ form: Object })
</script>
