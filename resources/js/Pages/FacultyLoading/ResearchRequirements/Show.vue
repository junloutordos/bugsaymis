<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { ArrowPathIcon, PlusIcon, EyeSlashIcon, EyeIcon, UserGroupIcon, PaperClipIcon, CheckIcon, ArrowUturnLeftIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    requirement: Object,
    assignments: Array,
})

const page  = usePage()
const flash = computed(() => page.props.flash ?? {})

const statusFilter = ref('')
const filteredAssignments = computed(() => {
    if (!statusFilter.value) return props.assignments
    return props.assignments.filter(a => a.status === statusFilter.value)
})

const statusBadge = { pending: 'slate', submitted: 'blue', accepted: 'green', returned: 'red' }

const syncForm = useForm({})
function sync() {
    syncForm.post(route('faculty-loading.research-requirements.sync', props.requirement.id), { preserveScroll: true })
}

const toggleForm = useForm({})
function toggleExclude(assignment) {
    toggleForm.patch(route('faculty-loading.research-requirements.assignments.toggle-exclude', assignment.id), { preserveScroll: true })
}

// ── Add exception group ──────────────────────────────────────────────────────
const showAddModal = ref(false)
const availableGroups = ref([])
const addForm = useForm({ research_group_id: '' })

async function openAddModal() {
    const { data } = await axios.get(route('faculty-loading.research-requirements.groups'), {
        params: { term_id: props.requirement?.term?.id ?? undefined },
    })
    const assignedIds = props.assignments.map(a => a.research_group.id)
    availableGroups.value = data.filter(g => !assignedIds.includes(g.id))
    addForm.reset()
    showAddModal.value = true
}
function submitAdd() {
    addForm.post(route('faculty-loading.research-requirements.assignments.store', props.requirement.id), {
        preserveScroll: true, onSuccess: () => { showAddModal.value = false },
    })
}

// ── Review actions ────────────────────────────────────────────────────────────
const reviewForm = useForm({ decision: '', comment: '' })
const returnModal = ref({ show: false, submissionId: null })

function accept(submissionId) {
    reviewForm.decision = 'accepted'
    reviewForm.comment  = ''
    reviewForm.post(route('faculty-loading.research-requirements.submissions.review', submissionId), { preserveScroll: true })
}
function openReturn(submissionId) {
    returnModal.value = { show: true, submissionId }
    reviewForm.decision = 'returned'
    reviewForm.comment  = ''
}
function submitReturn() {
    reviewForm.post(route('faculty-loading.research-requirements.submissions.review', returnModal.value.submissionId), {
        preserveScroll: true, onSuccess: () => { returnModal.value.show = false },
    })
}
</script>

<template>
    <Head :title="requirement.title" />
    <AdminLayout :title="requirement.title">
        <div class="space-y-5">
            <AppPageHeader :title="requirement.title" :subtitle="requirement.description || 'No instructions provided.'">
                <template #actions>
                    <AppButton variant="secondary" :loading="syncForm.processing" @click="sync">
                        <ArrowPathIcon class="h-4 w-4" /> Sync New Groups
                    </AppButton>
                    <AppButton @click="openAddModal">
                        <PlusIcon class="h-4 w-4" /> Add Group
                    </AppButton>
                </template>
            </AppPageHeader>

            <div v-if="flash.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">{{ flash.success }}</div>

            <div class="grid grid-cols-4 gap-3 text-sm">
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-[11px] uppercase text-slate-400">Due</p>
                    <p class="font-medium text-slate-800">{{ new Date(requirement.due_at).toLocaleString('en-PH') }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-[11px] uppercase text-slate-400">Compliance</p>
                    <p class="font-medium text-slate-800">{{ requirement.stats.compliance_pct }}% ({{ requirement.stats.accepted }}/{{ requirement.stats.total }})</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-[11px] uppercase text-slate-400">Accepted File Types</p>
                    <p class="font-medium text-slate-800">{{ requirement.accepted_file_types || 'Any' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-[11px] uppercase text-slate-400">Late Submission</p>
                    <p class="font-medium text-slate-800">{{ requirement.allow_late_submission ? 'Allowed' : 'Blocked after deadline' }}</p>
                </div>
            </div>

            <AppFilterBar>
                <select v-model="statusFilter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="submitted">Submitted</option>
                    <option value="accepted">Accepted</option>
                    <option value="returned">Returned</option>
                </select>
            </AppFilterBar>

            <AppTable :is-empty="filteredAssignments.length === 0" :skeleton-cols="5">
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Research Group</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Adviser(s)</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Latest Submission</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </template>
                <tr v-for="a in filteredAssignments" :key="a.id" :class="a.excluded ? 'opacity-40' : ''" class="hover:bg-slate-50/50">
                    <td class="px-4 py-3">
                        <p class="font-medium text-slate-800">{{ a.research_group.title }}</p>
                        <p class="text-xs text-slate-500">Grade {{ a.research_group.grade_level }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        <div class="flex items-center gap-1"><UserGroupIcon class="h-3.5 w-3.5 text-slate-400" />
                            {{ a.research_group.advisers.map(x => x.name).join(', ') }}
                        </div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <AppBadge :color="statusBadge[a.status] ?? 'slate'" class="capitalize">{{ a.status }}</AppBadge>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600">
                        <template v-if="a.latest_submission">
                            {{ a.latest_submission.submitted_by }} — {{ new Date(a.latest_submission.submitted_at).toLocaleDateString('en-PH') }}
                            <span v-if="a.latest_submission.is_late" class="text-danger-500"> (late)</span>
                            <div class="flex flex-wrap gap-1 mt-1">
                                <span v-for="f in a.latest_submission.files" :key="f.id"
                                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">
                                    <PaperClipIcon class="h-3 w-3" /> {{ f.name }}
                                </span>
                            </div>
                            <p v-if="a.latest_submission.notes" class="mt-1 italic text-slate-500">"{{ a.latest_submission.notes }}"</p>
                            <div v-if="a.status === 'submitted'" class="mt-2 flex gap-2">
                                <button @click="accept(a.latest_submission.id)" class="inline-flex items-center gap-1 text-xs text-success-600 hover:underline">
                                    <CheckIcon class="h-3.5 w-3.5" /> Accept
                                </button>
                                <button @click="openReturn(a.latest_submission.id)" class="inline-flex items-center gap-1 text-xs text-danger-600 hover:underline">
                                    <ArrowUturnLeftIcon class="h-3.5 w-3.5" /> Return
                                </button>
                            </div>
                            <p v-else-if="a.latest_submission.review_comment" class="mt-1 text-amber-700">Feedback: "{{ a.latest_submission.review_comment }}"</p>
                        </template>
                        <span v-else class="text-slate-400 italic">No submission yet</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button @click="toggleExclude(a)" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1">
                            <component :is="a.excluded ? EyeIcon : EyeSlashIcon" class="h-4 w-4" />
                            {{ a.excluded ? 'Re-include' : 'Exclude' }}
                        </button>
                    </td>
                </tr>
                <template #empty>
                    <EmptyState title="No research groups assigned yet" :icon="UserGroupIcon" />
                </template>
            </AppTable>
        </div>

        <AppModal :show="showAddModal" title="Add Research Group" @close="showAddModal = false">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Research Group</label>
                <select v-model="addForm.research_group_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                    <option value="">Select group…</option>
                    <option v-for="g in availableGroups" :key="g.id" :value="g.id">{{ g.title }} (Grade {{ g.grade_level }})</option>
                </select>
                <p v-if="availableGroups.length === 0" class="mt-2 text-xs text-slate-400 italic">No additional groups available for this term.</p>
            </div>
            <template #footer>
                <AppButton variant="secondary" @click="showAddModal = false">Cancel</AppButton>
                <AppButton :disabled="!addForm.research_group_id" :loading="addForm.processing" @click="submitAdd">Add</AppButton>
            </template>
        </AppModal>

        <AppModal :show="returnModal.show" title="Return for Revision" @close="returnModal.show = false">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Comment <span class="text-red-500">*</span></label>
                <textarea v-model="reviewForm.comment" rows="3" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                <p v-if="reviewForm.errors.comment" class="mt-1 text-xs text-danger-500">{{ reviewForm.errors.comment }}</p>
            </div>
            <template #footer>
                <AppButton variant="secondary" @click="returnModal.show = false">Cancel</AppButton>
                <AppButton :disabled="!reviewForm.comment" :loading="reviewForm.processing" @click="submitReturn">Return for Revision</AppButton>
            </template>
        </AppModal>
    </AdminLayout>
</template>
