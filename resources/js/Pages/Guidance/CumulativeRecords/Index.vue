<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { MagnifyingGlassIcon, UserIcon, AcademicCapIcon, HeartIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    students: Array,
    filters: Object,
})

const search = ref(props.filters.search ?? '')
const statusFilter = ref(props.filters.status ?? '')

const PER_PAGE = 15
const currentPage = ref(1)

let searchTimeout = null
watch([search, statusFilter], () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        currentPage.value = 1
        router.get(route('guidance.cumulative.index'), {
            search: search.value,
            status: statusFilter.value,
        }, { preserveState: true, replace: true })
    }, 350)
})

const totalPages = computed(() => Math.max(1, Math.ceil(props.students.length / PER_PAGE)))
const displayed = computed(() => {
    const start = (currentPage.value - 1) * PER_PAGE
    return props.students.slice(start, start + PER_PAGE)
})

function completionColor(pct) {
    if (pct >= 80) return 'bg-emerald-100 text-emerald-700'
    if (pct >= 40) return 'bg-amber-100 text-amber-700'
    return 'bg-slate-100 text-slate-500'
}

function batchLabel(batch) {
    if (!batch || batch === 'batch') return '—'
    const yr = parseInt(batch)
    if (isNaN(yr)) return batch
    return `Batch ${yr} (G${Math.max(7, 12 - (yr - new Date().getFullYear()))}–12)`
}

function statusBadge(status) {
    const map = {
        Enrolled: 'bg-indigo-100 text-indigo-700',
        Graduated: 'bg-emerald-100 text-emerald-700',
        Inactive: 'bg-slate-100 text-slate-500',
        Grade13: 'bg-purple-100 text-purple-700',
    }
    return map[status] ?? 'bg-slate-100 text-slate-500'
}
</script>

<template>
    <Head title="Student Cumulative Records" />
    <AdminLayout title="Student Cumulative Records">
        <div class="space-y-4">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold text-slate-800">Student Cumulative Records</h1>
                    <p class="text-sm text-slate-500">EGCU guidance profiles — {{ students.length }} students</p>
                </div>
                <a
                    :href="route('students.health.index')"
                    class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                >
                    <HeartIcon class="w-4 h-4" />
                    Medical Records
                </a>
            </div>

            <!-- Filters -->
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-4 h-4 text-slate-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search by name, ID, or LRN…"
                        class="pl-9 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                    />
                </div>
                <select
                    v-model="statusFilter"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">All Statuses</option>
                    <option value="Enrolled">Enrolled</option>
                    <option value="Grade13">Grade 13</option>
                    <option value="Graduated">Graduated</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden sm:table-cell">Barcode</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden md:table-cell">Batch</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden lg:table-cell">Profile</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="displayed.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400 text-sm">
                                No students found.
                            </td>
                        </tr>
                        <tr
                            v-for="s in displayed"
                            :key="s.id"
                            class="hover:bg-slate-50 cursor-pointer"
                            @click="router.visit(route('guidance.cumulative.show', s.pisaysystemID))"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center shrink-0">
                                        <UserIcon class="w-4 h-4 text-indigo-500" />
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-800">{{ s.lastname }}, {{ s.firstname }}</p>
                                        <p v-if="s.middlename" class="text-xs text-slate-400">{{ s.middlename }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-500 font-mono text-xs hidden sm:table-cell">
                                {{ s.pisaysystemID || '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-500 hidden md:table-cell">
                                {{ s.batch || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="statusBadge(s.status)">
                                    {{ s.status || 'Unknown' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell">
                                <div class="flex items-center gap-2">
                                    <div class="w-24 bg-slate-100 rounded-full h-1.5">
                                        <div
                                            class="h-1.5 rounded-full transition-all"
                                            :class="s.profile_pct >= 80 ? 'bg-emerald-500' : s.profile_pct >= 40 ? 'bg-amber-400' : 'bg-slate-300'"
                                            :style="{ width: s.profile_pct + '%' }"
                                        ></div>
                                    </div>
                                    <span class="text-xs" :class="s.profile_pct > 0 ? 'text-slate-600' : 'text-slate-400'">
                                        {{ s.profile_pct }}%
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    :href="route('guidance.cumulative.show', s.pisaysystemID)"
                                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                    @click.stop
                                >
                                    View
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="totalPages > 1" class="flex items-center justify-between text-sm text-slate-500">
                <span>Page {{ currentPage }} of {{ totalPages }}</span>
                <div class="flex gap-2">
                    <button
                        :disabled="currentPage === 1"
                        @click="currentPage--"
                        class="px-3 py-1.5 rounded border border-slate-200 disabled:opacity-40 hover:bg-slate-50"
                    >Previous</button>
                    <button
                        :disabled="currentPage === totalPages"
                        @click="currentPage++"
                        class="px-3 py-1.5 rounded border border-slate-200 disabled:opacity-40 hover:bg-slate-50"
                    >Next</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
