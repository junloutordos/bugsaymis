<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useSpmsReviewerIpcr } from '@/Composables/useSpmsReviewerIpcr'

const props = defineProps({ ipcr: Object })
const ipcr = computed(() => props.ipcr)
const { ratings, approveTarget, submitRatings, finalizeIpcr } = useSpmsReviewerIpcr(ipcr)
</script>

<template>
  <Head title="Review IPCR (SPMS)" />
  <AdminLayout title="Review IPCR (SPMS)">
    <p class="mb-4 text-sm text-slate-500">{{ ipcr.user?.name }} — {{ ipcr.fiscal_period?.label }} — {{ ipcr.status }}</p>

    <button v-if="ipcr.status === 'Target Submitted'" @click="approveTarget"
      class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium mb-4">
      Approve Target
    </button>

    <div v-if="ipcr.status === 'Submitted for Rating'" class="space-y-3">
      <div v-for="target in ipcr.targets" :key="target.id" class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="mb-2">{{ target.success_indicator }}</p>
        <div class="flex gap-2">
          <input placeholder="Q" type="number" min="1" max="5" class="w-16 rounded-lg border border-slate-200 px-2 py-1 text-sm"
            @input="ratings[target.id] = { ...ratings[target.id], rating_q: $event.target.valueAsNumber }" />
          <input placeholder="E" type="number" min="1" max="5" class="w-16 rounded-lg border border-slate-200 px-2 py-1 text-sm"
            @input="ratings[target.id] = { ...ratings[target.id], rating_e: $event.target.valueAsNumber }" />
          <input placeholder="T" type="number" min="1" max="5" class="w-16 rounded-lg border border-slate-200 px-2 py-1 text-sm"
            @input="ratings[target.id] = { ...ratings[target.id], rating_t: $event.target.valueAsNumber }" />
        </div>
      </div>
      <button @click="submitRatings" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Save Ratings
      </button>
    </div>

    <button v-if="ipcr.status === 'PMT/HR Reviewed'" @click="finalizeIpcr"
      class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
      Sign as Director (Finalize)
    </button>
  </AdminLayout>
</template>
