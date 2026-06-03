<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { MagnifyingGlassIcon, UserIcon, HeartIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    students: Array,
    filters: Object,
})

const search = ref(props.filters.search ?? '')

const PER_PAGE = 15
const currentPage = ref(1)

let searchTimeout = null
watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        currentPage.value = 1
        router.get(route('students.health.index'), { search: search.value }, { preserveState: true, replace: true })
    }, 350)
})

const totalPages = computed(() => Math.max(1, Math.ceil(props.students.length / PER_PAGE)))
const displayed = computed(() => {
    const start = (currentPage.value - 1) * PER_PAGE
    return props.students.slice(start, start + PER_PAGE)
})
</script>

<template>
    <Head title="Student Medical Records" />
    <AdminLayout title="Student Medical Records">
        <div class="space-y-4">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold text-slate-800">Student Medical Records</h1>
                    <p class="text-sm text-slate-500">Allergies, immunizations, medical history, vitamins — {{ students.length }} students</p>
                </div>
                <a
                    :href="route('guidance.cumulative.index')"
                    class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                >
                    Cumulative Records
                </a>
            </div>

            <!-- Search -->
            <div class="relative max-w-md">
                <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-4 h-4 text-slate-400" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or student ID…"
                    class="pl-9 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                />
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden sm:table-cell">Barcode</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden md:table-cell">Batch</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Records</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="displayed.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400 text-sm">No students found.</td>
                        </tr>
                        <tr
                            v-for="s in displayed"
                            :key="s.id"
                            class="hover:bg-slate-50 cursor-pointer"
                            @click="router.visit(route('students.health.show', s.pisaysystemID))"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center shrink-0">
                                        <UserIcon class="w-4 h-4 text-rose-500" />
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-800">{{ s.lastname }}, {{ s.firstname }}</p>
                                        <p class="text-xs text-slate-400">{{ s.sex }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-500 font-mono text-xs hidden sm:table-cell">{{ s.pisaysystemID || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 hidden md:table-cell">{{ s.batch || '—' }}</td>
                            <td class="px-4 py-3">
                                <span v-if="s.has_health_record" class="inline-flex items-center gap-1 text-emerald-600 text-xs font-medium">
                                    <CheckCircleIcon class="w-3.5 h-3.5" /> Has records
                                </span>
                                <span v-else class="text-slate-400 text-xs">—</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    :href="route('students.health.show', s.pisaysystemID)"
                                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                    @click.stop
                                >View</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="totalPages > 1" class="flex items-center justify-between text-sm text-slate-500">
                <span>Page {{ currentPage }} of {{ totalPages }}</span>
                <div class="flex gap-2">
                    <button :disabled="currentPage === 1" @click="currentPage--" class="px-3 py-1.5 rounded border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Previous</button>
                    <button :disabled="currentPage === totalPages" @click="currentPage++" class="px-3 py-1.5 rounded border border-slate-200 disabled:opacity-40 hover:bg-slate-50">Next</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
