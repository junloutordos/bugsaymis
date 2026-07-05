<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppButton from '@/Components/AppButton.vue'
import AppInput from '@/Components/AppInput.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
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
        <div class="space-y-5">
            <AppPageHeader title="Student Medical Records" :subtitle="`Allergies, immunizations, medical history, vitamins — ${students.length} students`">
                <template #actions>
                    <AppButton as="a" variant="secondary" :href="route('guidance.cumulative.index')">Cumulative Records</AppButton>
                </template>
            </AppPageHeader>

            <AppFilterBar>
                <div class="relative w-full max-w-md">
                    <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-4 h-4 text-slate-400" />
                    <AppInput v-model="search" type="text" placeholder="Search by name or student ID…" class="pl-9" />
                </div>
            </AppFilterBar>

            <AppTable :is-empty="displayed.length === 0" :skeleton-cols="5">
                <template #head>
                    <tr>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden sm:table-cell">Barcode</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden md:table-cell">Batch</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Records</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </template>

                <tr
                    v-for="s in displayed"
                    :key="s.id"
                    class="hover:bg-slate-50 cursor-pointer"
                    @click="router.visit(route('students.health.show', s.pisaysystemID))"
                >
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-danger-50 flex items-center justify-center shrink-0">
                                <UserIcon class="w-4 h-4 text-danger-600" />
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
                        <span v-if="s.has_health_record" class="inline-flex items-center gap-1 text-success-600 text-xs font-medium">
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

                <template #empty>
                    <EmptyState title="No students found" />
                </template>

                <template #footer>
                    <PaginationControl
                        :current-page="currentPage"
                        :total-pages="totalPages"
                        @prev="currentPage--"
                        @next="currentPage++"
                        @page="currentPage = $event"
                    />
                </template>
            </AppTable>
        </div>
    </AdminLayout>
</template>
