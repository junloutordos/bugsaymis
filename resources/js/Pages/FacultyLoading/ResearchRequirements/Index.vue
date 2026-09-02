<script setup>
import { ref, computed, watch } from 'vue'
import { Head, usePage, useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import { PlusIcon, EyeIcon, ArchiveBoxIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    requirements: Array,
    terms:        Array,
    currentTerm:  Object,
    filters:      Object,
})

const page  = usePage()
const flash = computed(() => page.props.flash ?? {})

const termId = ref(props.filters.term_id ?? props.currentTerm?.id ?? '')
function applyFilters() {
    window.location.href = route('faculty-loading.research-requirements.index') + (termId.value ? `?term_id=${termId.value}` : '')
}
watch(termId, applyFilters)

const GRADE_LEVELS = [7, 8, 9, 10, 11, 12]
const RESEARCH_TYPES = [
    { value: '', label: 'All Types' },
    { value: 'thesis', label: 'Thesis' },
    { value: 'investigatory', label: 'Investigatory' },
    { value: 'science_research', label: 'Science Research' },
    { value: 'feasibility', label: 'Feasibility' },
]

const showModal = ref(false)
const form = useForm({
    academic_term_id: '', title: '', description: '', research_type: '',
    grade_levels: [], accepted_file_types: '', max_files: 5,
    due_at: '', allow_late_submission: true,
})

function openCreate() {
    form.reset()
    form.academic_term_id = termId.value || props.currentTerm?.id || ''
    form.grade_levels = []
    form.max_files = 5
    form.allow_late_submission = true
    showModal.value = true
}
function closeModal() { showModal.value = false }

function toggleGrade(g) {
    const idx = form.grade_levels.indexOf(g)
    if (idx === -1) form.grade_levels.push(g)
    else form.grade_levels.splice(idx, 1)
}

function submit() {
    form.post(route('faculty-loading.research-requirements.store'), {
        preserveScroll: true, onSuccess: closeModal,
    })
}

const archiveForm = useForm({})
async function archive(req) {
    if (! await confirmDelete(`Archive requirement "${req.title}"? Advisers will no longer see it.`)) return
    archiveForm.delete(route('faculty-loading.research-requirements.archive', req.id), { preserveScroll: true })
}

const statusBadge = { active: 'green', archived: 'slate' }
function dueLabel(iso) {
    return new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
    <Head title="Research Requirements" />
    <AdminLayout title="Research Requirements">
        <div class="space-y-5">

            <AppPageHeader title="Research Requirements" subtitle="Set submission deadlines and required files for research groups.">
                <template #actions>
                    <AppButton @click="openCreate">
                        <PlusIcon class="h-4 w-4" /> New Requirement
                    </AppButton>
                </template>
            </AppPageHeader>

            <div v-if="flash.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">{{ flash.success }}</div>
            <div v-if="flash.error"   class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">{{ flash.error }}</div>

            <AppFilterBar>
                <select v-model="termId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Terms</option>
                    <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}</option>
                </select>
            </AppFilterBar>

            <AppTable :is-empty="requirements.length === 0" :skeleton-cols="6">
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Title</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Due</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Scope</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Compliance</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </template>
                <template v-for="r in requirements" :key="r.id">
                    <tr class="hover:bg-slate-50/50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800">{{ r.title }}</p>
                            <p class="text-xs text-slate-500">{{ r.term?.label }}</p>
                        </td>
                        <td class="px-4 py-3 text-center text-slate-600">{{ dueLabel(r.due_at) }}</td>
                        <td class="px-4 py-3 text-center text-xs text-slate-600">
                            {{ r.grade_levels?.length ? 'Grade ' + r.grade_levels.join(', ') : 'All Grades' }}
                            <span v-if="r.research_type"> · {{ r.research_type }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <div class="w-20 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full bg-indigo-500" :style="{ width: r.stats.compliance_pct + '%' }" />
                                </div>
                                <span class="text-xs text-slate-600">{{ r.stats.compliance_pct }}%</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">{{ r.stats.accepted }}/{{ r.stats.total }} accepted</p>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <AppBadge :color="statusBadge[r.status] ?? 'slate'" class="capitalize">{{ r.status }}</AppBadge>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <Link :href="route('faculty-loading.research-requirements.show', r.id)">
                                    <AppIconButton label="View"><EyeIcon class="h-4 w-4" /></AppIconButton>
                                </Link>
                                <AppIconButton v-if="r.status === 'active'" label="Archive" variant="danger" @click="archive(r)">
                                    <ArchiveBoxIcon class="h-4 w-4" />
                                </AppIconButton>
                            </div>
                        </td>
                    </tr>
                </template>
                <template #empty>
                    <EmptyState title="No submission requirements yet" :icon="ClipboardDocumentCheckIcon" />
                </template>
            </AppTable>
        </div>

        <AppModal :show="showModal" title="New Submission Requirement" size="xl" @close="closeModal">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Term <span class="text-red-500">*</span></label>
                        <select v-model="form.academic_term_id" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                            <option value="">Select term…</option>
                            <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}</option>
                        </select>
                        <p v-if="form.errors.academic_term_id" class="mt-1 text-xs text-danger-500">{{ form.errors.academic_term_id }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Due Date/Time <span class="text-red-500">*</span></label>
                        <input v-model="form.due_at" type="datetime-local" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                        <p v-if="form.errors.due_at" class="mt-1 text-xs text-danger-500">{{ form.errors.due_at }}</p>
                    </div>
                </div>

                <AppInput v-model="form.title" label="Title" required maxlength="255" placeholder="e.g. Chapter 1 Draft" :error="form.errors.title" />

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Description / Instructions</label>
                    <textarea v-model="form.description" rows="3" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Research Type</label>
                        <select v-model="form.research_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                            <option v-for="t in RESEARCH_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <p class="mt-1 text-[11px] text-slate-400">Leave as "All Types" to target every research type.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Grade Levels</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="g in GRADE_LEVELS" :key="g" type="button" @click="toggleGrade(g)"
                                class="px-2.5 py-1 rounded-full text-xs font-medium border"
                                :class="form.grade_levels.includes(g) ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-slate-200 text-slate-600'">
                                G{{ g }}
                            </button>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">Leave empty to target all grade levels.</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <AppInput v-model="form.accepted_file_types" label="Accepted File Types" placeholder="pdf,docx" />
                    <AppInput v-model.number="form.max_files" type="number" min="1" max="20" label="Max Files" />
                    <div class="flex items-end pb-2">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" v-model="form.allow_late_submission" class="rounded border-slate-300 text-indigo-600" />
                            Allow late submission
                        </label>
                    </div>
                </div>
            </div>

            <template #footer>
                <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
                <AppButton :loading="form.processing" @click="submit">Create Requirement</AppButton>
            </template>
        </AppModal>
    </AdminLayout>
</template>
