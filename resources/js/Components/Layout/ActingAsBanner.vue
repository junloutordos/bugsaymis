<script setup>
import { computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

const page = usePage()
const actingAs = computed(() => page.props.actingAs)

function exitActingAs() {
  router.post(route('hr.substitutions.act-as.exit'))
}
</script>

<template>
  <div
    v-if="actingAs"
    class="w-full bg-amber-500 text-white text-sm px-4 py-2 flex items-center justify-between gap-3 flex-wrap"
  >
    <span>
      Acting as <strong>{{ actingAs.original_user_name }}</strong>
      — you are <strong>{{ actingAs.substitute_user_name }}</strong>
      (until {{ new Date(actingAs.end_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }})
    </span>
    <button
      @click="exitActingAs"
      class="bg-white text-amber-700 px-3 py-1 rounded-lg text-xs font-medium hover:bg-amber-50 shrink-0"
    >
      Return to my account
    </button>
  </div>
</template>
