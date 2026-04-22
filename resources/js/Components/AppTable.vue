<!-- Responsive table wrapper: scrolls horizontally on small screens -->
<template>
  <div class="overflow-x-auto rounded-xl border border-slate-100">
    <table class="min-w-full divide-y divide-slate-100 text-sm">
      <thead class="bg-slate-50">
        <slot name="head" />
      </thead>
      <tbody class="divide-y divide-slate-100 bg-white">
        <slot />
      </tbody>
    </table>
    <div v-if="$slots.footer" class="border-t border-slate-100 px-4 py-3 bg-white">
      <slot name="footer" />
    </div>
  </div>
</template>
