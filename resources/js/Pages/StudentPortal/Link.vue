<script setup>
import { ref } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { AcademicCapIcon, IdentificationIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    google_name: String,
})

const form = useForm({
    pisaysystemID: '',
})

function submit() {
    form.post(route('student-portal.link.submit'))
}
</script>

<template>
    <Head title="Student Portal — Link Your Record" />

    <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-blue-50 flex items-center justify-center px-4">
        <div class="w-full max-w-sm space-y-6">

            <!-- Logo -->
            <div class="text-center space-y-2">
                <div class="w-16 h-16 rounded-2xl bg-indigo-600 flex items-center justify-center mx-auto shadow-lg">
                    <AcademicCapIcon class="w-8 h-8 text-white" />
                </div>
                <h1 class="text-xl font-bold text-slate-800">One more step</h1>
                <p class="text-sm text-slate-500">Hi {{ google_name }}! Link your PISAY student record.</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 space-y-5">
                <div class="flex items-start gap-3 bg-indigo-50 border border-indigo-100 rounded-lg p-3">
                    <IdentificationIcon class="w-5 h-5 text-indigo-500 mt-0.5 shrink-0" />
                    <p class="text-sm text-indigo-800">
                        Enter the <strong>PISAY System ID</strong> printed on your school ID to link your Google account to your student record. You only need to do this once.
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            PISAY System ID
                        </label>
                        <input
                            v-model="form.pisaysystemID"
                            type="text"
                            placeholder="e.g. 13-2025-130"
                            autocomplete="off"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                            :class="{ 'border-red-400': form.errors.pisaysystemID }"
                        />
                        <p v-if="form.errors.pisaysystemID" class="mt-1.5 text-xs text-red-600">
                            {{ form.errors.pisaysystemID }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || !form.pisaysystemID.trim()"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-medium text-sm rounded-lg py-2.5 transition-colors"
                    >
                        {{ form.processing ? 'Verifying…' : 'Link My Record' }}
                    </button>
                </form>
            </div>

            <p class="text-xs text-center text-slate-400">
                Can't find your ID? Contact the Guidance Office.
            </p>
        </div>
    </div>
</template>
