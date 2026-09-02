<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { ClipboardDocumentCheckIcon, PaperClipIcon, XMarkIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    assignments: Array,
})

const page  = usePage()
const flash = computed(() => page.props.flash ?? {})

const statusBadge = { pending: 'slate', submitted: 'blue', accepted: 'green', returned: 'red' }
const statusLabel = { pending: 'Not Submitted', submitted: 'Submitted — Awaiting Review', accepted: 'Accepted', returned: 'Returned for Revision' }

function isOverdue(a) {
    return a.status !== 'accepted' && new Date(a.requirement.due_at) < new Date()
}

// ── Submit modal ──────────────────────────────────────────────────────────────
const showModal   = ref(false)
const activeAssignment = ref(null)
const pendingFiles = ref([]) // [{ name, data }]
const fileError = ref('')

const form = useForm({ notes: '', files: [] })

function openSubmit(assignment) {
    activeAssignment.value = assignment
    pendingFiles.value = []
    fileError.value = ''
    form.reset()
    showModal.value = true
}
function closeModal() { showModal.value = false }

function handleFiles(e) {
    const list = e.target.files || e.dataTransfer.files
    const max = activeAssignment.value.requirement.max_files
    for (const file of list) {
        if (pendingFiles.value.length >= max) {
            fileError.value = `You can attach at most ${max} file(s).`
            break
        }
        if (file.size > 10 * 1024 * 1024) {
            fileError.value = `"${file.name}" is over the 10MB limit.`
            continue
        }
        const reader = new FileReader()
        reader.onload = (ev) => {
            pendingFiles.value.push({ name: file.name, data: ev.target.result })
        }
        reader.readAsDataURL(file)
    }
}
function removeFile(i) { pendingFiles.value.splice(i, 1) }

function submit() {
    form.files = pendingFiles.value
    form.post(route('faculty-loading.my-research-requirements.submit', activeAssignment.value.id), {
        preserveScroll: true, onSuccess: closeModal,
    })
}
</script>

<template>
    <Head title="My Research Submissions" />
    <AdminLayout title="My Research Submissions">
        <div class="space-y-5">
            <AppPageHeader title="My Research Submissions" subtitle="Deadlines and required files set by the Research Coordinator." />

            <div v-if="flash.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">{{ flash.success }}</div>

            <div v-if="assignments.length === 0">
                <EmptyState title="No submission requirements assigned to your research groups yet" :icon="ClipboardDocumentCheckIcon" />
            </div>

            <div v-else class="grid gap-3 sm:grid-cols-2">
                <div v-for="a in assignments" :key="a.id" class="rounded-lg border border-slate-200 bg-white p-4 space-y-2"
                    :class="isOverdue(a) && a.status !== 'submitted' ? 'border-danger-200 bg-danger-50/30' : ''">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-medium text-slate-800">{{ a.requirement.title }}</p>
                            <p class="text-xs text-slate-500">{{ a.research_group.title }} · Grade {{ a.research_group.grade_level }}</p>
                        </div>
                        <AppBadge :color="statusBadge[a.status] ?? 'slate'">{{ statusLabel[a.status] ?? a.status }}</AppBadge>
                    </div>

                    <p v-if="a.requirement.description" class="text-sm text-slate-600">{{ a.requirement.description }}</p>

                    <p class="text-xs" :class="isOverdue(a) ? 'text-danger-600 font-medium' : 'text-slate-500'">
                        Due {{ new Date(a.requirement.due_at).toLocaleString('en-PH') }}
                        <span v-if="isOverdue(a)">— overdue{{ !a.requirement.allow_late_submission ? ' (late submission blocked)' : '' }}</span>
                    </p>

                    <div v-if="a.latest_submission" class="rounded-md bg-slate-50 p-2 text-xs text-slate-600 space-y-1">
                        <p>Last submitted by {{ a.latest_submission.submitted_by }} on {{ new Date(a.latest_submission.submitted_at).toLocaleDateString('en-PH') }}
                            <span v-if="a.latest_submission.is_late" class="text-danger-500">(late)</span>
                        </p>
                        <p v-if="a.latest_submission.review_comment" class="text-amber-700">Coordinator: "{{ a.latest_submission.review_comment }}"</p>
                        <div class="flex flex-wrap gap-1">
                            <a v-for="f in a.latest_submission.files" :key="f.id"
                                :href="route('faculty-loading.research-requirements.files.show', f.id)" target="_blank"
                                class="inline-flex items-center gap-1 rounded-full bg-white border border-slate-200 px-2 py-0.5 text-[11px] text-indigo-600 hover:underline">
                                <PaperClipIcon class="h-3 w-3" /> {{ f.name }}
                            </a>
                        </div>
                    </div>

                    <AppButton v-if="a.status === 'pending' || a.status === 'returned'" size="sm" @click="openSubmit(a)">
                        <ArrowUpTrayIcon class="h-4 w-4" /> {{ a.status === 'returned' ? 'Resubmit' : 'Submit' }}
                    </AppButton>
                </div>
            </div>
        </div>

        <AppModal :show="showModal" :title="activeAssignment ? 'Submit — ' + activeAssignment.requirement.title : ''" @close="closeModal">
            <div class="space-y-3">
                <p v-if="activeAssignment?.requirement.accepted_file_types" class="text-xs text-slate-500">
                    Accepted file types: {{ activeAssignment.requirement.accepted_file_types }}. Max {{ activeAssignment.requirement.max_files }} file(s).
                </p>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Files</label>
                    <input type="file" multiple @change="handleFiles" class="block w-full text-sm" />
                    <p v-if="fileError" class="mt-1 text-xs text-danger-500">{{ fileError }}</p>
                    <p v-if="form.errors.files" class="mt-1 text-xs text-danger-500">{{ form.errors.files }}</p>
                    <div v-if="pendingFiles.length" class="mt-2 flex flex-wrap gap-2">
                        <span v-for="(f, i) in pendingFiles" :key="i"
                            class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-200 px-3 py-0.5 text-xs text-indigo-700">
                            {{ f.name }}
                            <button type="button" @click="removeFile(i)" class="hover:text-danger-500"><XMarkIcon class="h-3 w-3" /></button>
                        </span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Notes (optional)</label>
                    <textarea v-model="form.notes" rows="3" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                </div>
                <p v-if="form.errors.due_at" class="text-xs text-danger-500">{{ form.errors.due_at }}</p>
            </div>

            <template #footer>
                <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
                <AppButton :disabled="pendingFiles.length === 0" :loading="form.processing" @click="submit">Submit</AppButton>
            </template>
        </AppModal>
    </AdminLayout>
</template>
