<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center gap-6 bg-slate-50 px-4 py-10"
    >
        <Link href="/">
            <img src="/images/atlas-logo-full.png" alt="Atlas" class="h-12 w-auto object-contain" />
        </Link>

        <div
            class="w-full overflow-hidden rounded-2xl border border-slate-100 bg-white px-6 py-6 shadow-sm sm:max-w-md"
        >
            <slot />
        </div>
    </div>
</template>
