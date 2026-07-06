<script setup>
import { ref, computed, watch } from 'vue'
import { Head, usePage, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import {
    PlusIcon, PencilIcon, TrashIcon, MagnifyingGlassIcon,
    UserGroupIcon, ExclamationTriangleIcon, XMarkIcon,
    ChevronDownIcon, ChevronUpIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    advisories:  Array,
    terms:       Array,
    faculty:     Array,
    currentTerm: Object,
    filters:     Object,
})

const page  = usePage()
const flash = computed(() => page.props.flash ?? {})

// ── Filters ───────────────────────────────────────────────────────────────────
const termId    = ref(props.filters.term_id    ?? props.currentTerm?.id ?? '')
const facultyId = ref(props.filters.faculty_id ?? '')
const search    = ref('')

function applyFilters() {
    const params = {}
    if (termId.value)    params.term_id    = termId.value
    if (facultyId.value) params.faculty_id = facultyId.value
    window.location.href = route('faculty-loading.research-advisories.index') + '?' + new URLSearchParams(params).toString()
}
watch([termId, facultyId], applyFilters)

// ── Pagination ────────────────────────────────────────────────────────────────
const PER_PAGE    = 15
const currentPage = ref(1)
const filtered    = computed(() => {
    const q = search.value.toLowerCase()
    return props.advisories.filter(a =>
        !q ||
        a.research_title.toLowerCase().includes(q) ||
        (a.faculty?.name ?? '').toLowerCase().includes(q) ||
        String(a.grade_level).includes(q)
    )
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
    const start = (currentPage.value - 1) * PER_PAGE
    return filtered.value.slice(start, start + PER_PAGE)
})
watch(search, () => { currentPage.value = 1 })

// ── Expanded members row ──────────────────────────────────────────────────────
const expandedId = ref(null)
function toggleExpand(id) { expandedId.value = expandedId.value === id ? null : id }

// ── Modal ─────────────────────────────────────────────────────────────────────
const showModal  = ref(false)
const editTarget = ref(null)

const form = useForm({
    user_id: '', academic_term_id: '', research_title: '',
    grade_level: '', advisory_role: 'lead', research_type: '',
    load_units: 1.0, status: 'active', remarks: '', members: [],
})

function openCreate() {
    editTarget.value = null
    form.reset()
    form.user_id          = ''
    form.academic_term_id = termId.value || props.currentTerm?.id || ''
    form.advisory_role    = 'lead'
    form.load_units       = 1.0
    form.status           = 'active'
    form.members          = []
    availableStudents.value = []
    studentSearch.value     = ''
    showModal.value = true
}

function openEdit(advisory) {
    editTarget.value      = advisory
    form.research_title   = advisory.research_title
    form.grade_level      = advisory.grade_level
    form.advisory_role    = advisory.advisory_role
    form.research_type    = advisory.research_type ?? ''
    form.load_units       = advisory.load_units
    form.status           = advisory.status
    form.remarks          = advisory.remarks ?? ''
    form.members          = advisory.members.map(m => ({ student_id: m.student_id, student_name: m.student_name }))
    availableStudents.value = []
    studentSearch.value     = ''
    if (advisory.grade_level && advisory.term?.id) {
        loadStudents(advisory.grade_level, advisory.term.id, advisory.id)
    }
    showModal.value = true
}

function closeModal() { showModal.value = false }

// Auto-set default units on role change (create mode only)
watch(() => form.advisory_role, role => {
    if (!editTarget.value) form.load_units = role === 'lead' ? 1.0 : 0.5
})

// Auto-load students when grade level changes
watch(() => form.grade_level, gl => {
    const tid = editTarget.value?.term?.id ?? form.academic_term_id
    if (gl && tid) loadStudents(gl, tid, editTarget.value?.id)
    else availableStudents.value = []
})

function submit() {
    if (editTarget.value) {
        form.put(route('faculty-loading.research-advisories.update', editTarget.value.id), {
            preserveScroll: true, onSuccess: closeModal,
        })
    } else {
        form.post(route('faculty-loading.research-advisories.store'), {
            preserveScroll: true, onSuccess: closeModal,
        })
    }
}

// ── Delete ────────────────────────────────────────────────────────────────────
const deleteForm = useForm({})
async function remove(advisory) {
    if (! await confirmDelete(`Remove advisory group "${advisory.research_title}"?`)) return
    deleteForm.delete(route('faculty-loading.research-advisories.destroy', advisory.id), { preserveScroll: true })
}

// ── Student picker ────────────────────────────────────────────────────────────
const availableStudents = ref([])
const studentSearch     = ref('')
const loadingStudents   = ref(false)

async function loadStudents(gradeLevel, termIdVal, advisoryId = null) {
    if (!gradeLevel || !termIdVal) return
    loadingStudents.value   = true
    availableStudents.value = []
    try {
        const params = { grade_level: gradeLevel, term_id: termIdVal }
        if (advisoryId) params.advisory_id = advisoryId
        const { data } = await axios.get(route('faculty-loading.research-advisories.students'), { params })
        availableStudents.value = data
    } catch {
        availableStudents.value = []
    } finally {
        loadingStudents.value = false
    }
}

const filteredStudents = computed(() => {
    const q = studentSearch.value.toLowerCase()
    return availableStudents.value.filter(s => !q || s.student_name.toLowerCase().includes(q))
})

function isSelected(studentId) {
    return form.members.some(m => m.student_id === studentId)
}

function toggleStudent(student) {
    if (isSelected(student.id)) {
        form.members = form.members.filter(m => m.student_id !== student.id)
    } else {
        form.members.push({ student_id: student.id, student_name: student.student_name })
    }
}

function removeMember(index) { form.members.splice(index, 1) }

// ── Display helpers ───────────────────────────────────────────────────────────
const roleLabel = { lead: 'Lead', co_adviser: 'Co-Adviser' }
const roleBadge = { lead: 'indigo', co_adviser: 'purple' }
const typeLabel = { thesis: 'Thesis', investigatory: 'Investigatory', science_research: 'Science Research', feasibility: 'Feasibility' }
const statusBadge = { active: 'green', completed: 'blue', dropped: 'red' }
</script>

<template>
    <Head title="Research Advisories" />
    <AdminLayout title="Research Advisories">
        <div class="space-y-5">

            <AppPageHeader title="Research Advisories" subtitle="Manage research advisory groups per faculty per term.">
                <template #actions>
                    <AppButton @click="openCreate">
                        <PlusIcon class="h-4 w-4" /> New Advisory Group
                    </AppButton>
                </template>
            </AppPageHeader>

            <!-- Flash -->
            <div v-if="flash.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">{{ flash.success }}</div>
            <div v-if="flash.error"   class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">{{ flash.error }}</div>

            <!-- Filters -->
            <AppFilterBar>
                <select v-model="termId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Terms</option>
                    <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}</option>
                </select>
                <select v-model="facultyId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Faculty</option>
                    <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
                </select>
                <div class="w-64">
                    <AppInput v-model="search" placeholder="Search title / faculty…" />
                </div>
            </AppFilterBar>

            <!-- Table -->
            <AppTable :is-empty="displayed.length === 0" :skeleton-cols="9">
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Faculty</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Research Title</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Grade</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Role</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Type</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Units</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Members</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
                    </tr>
                </template>

                <template v-for="a in displayed" :key="a.id">
                    <tr class="hover:bg-slate-50/50">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ a.faculty?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-700 max-w-xs truncate" :title="a.research_title">{{ a.research_title }}</td>
                        <td class="px-4 py-3 text-center text-slate-600">Grade {{ a.grade_level }}</td>
                        <td class="px-4 py-3 text-center">
                            <AppBadge :color="roleBadge[a.advisory_role] ?? 'slate'">{{ roleLabel[a.advisory_role] ?? a.advisory_role }}</AppBadge>
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-slate-600">{{ typeLabel[a.research_type] ?? '—' }}</td>
                        <td class="px-4 py-3 text-center font-medium text-slate-800">{{ a.load_units }}</td>
                        <td class="px-4 py-3 text-center">
                            <button @click="toggleExpand(a.id)"
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 hover:bg-slate-200">
                                <UserGroupIcon class="h-3.5 w-3.5" />
                                {{ a.members.length }}
                                <ChevronDownIcon v-if="expandedId !== a.id" class="h-3 w-3" />
                                <ChevronUpIcon   v-else                      class="h-3 w-3" />
                            </button>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <AppBadge :color="statusBadge[a.status] ?? 'slate'" class="capitalize">{{ a.status }}</AppBadge>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <AppIconButton label="Edit" @click="openEdit(a)"><PencilIcon class="h-4 w-4" /></AppIconButton>
                                <AppIconButton label="Delete" variant="danger" @click="remove(a)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                            </div>
                        </td>
                    </tr>
                    <!-- Expanded members -->
                    <tr v-if="expandedId === a.id" class="bg-slate-50">
                        <td colspan="9" class="px-6 py-3">
                            <p v-if="a.members.length === 0" class="text-xs text-slate-400 italic">No members recorded.</p>
                            <div v-else class="flex flex-wrap gap-2">
                                <span v-for="m in a.members" :key="m.id"
                                    class="inline-block rounded-full bg-white border border-slate-200 px-3 py-0.5 text-xs text-slate-700 shadow-sm">
                                    {{ m.student_name }}
                                </span>
                            </div>
                        </td>
                    </tr>
                </template>

                <template #mobileCard>
                    <div v-for="a in displayed" :key="a.id" class="p-4 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800">{{ a.faculty?.name ?? '—' }}</p>
                                <p class="text-sm text-slate-700 truncate" :title="a.research_title">{{ a.research_title }}</p>
                            </div>
                            <AppBadge :color="statusBadge[a.status] ?? 'slate'" class="capitalize shrink-0">{{ a.status }}</AppBadge>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <AppBadge :color="roleBadge[a.advisory_role] ?? 'slate'">{{ roleLabel[a.advisory_role] ?? a.advisory_role }}</AppBadge>
                            <span class="text-xs text-slate-500">Grade {{ a.grade_level }}</span>
                            <span class="text-xs text-slate-500">{{ typeLabel[a.research_type] ?? '—' }}</span>
                            <span class="text-xs text-slate-500">{{ a.load_units }} unit(s)</span>
                        </div>
                        <button @click="toggleExpand(a.id)"
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 hover:bg-slate-200">
                            <UserGroupIcon class="h-3.5 w-3.5" />
                            {{ a.members.length }} member(s)
                            <ChevronDownIcon v-if="expandedId !== a.id" class="h-3 w-3" />
                            <ChevronUpIcon   v-else                      class="h-3 w-3" />
                        </button>
                        <div v-if="expandedId === a.id">
                            <p v-if="a.members.length === 0" class="text-xs text-slate-400 italic">No members recorded.</p>
                            <div v-else class="flex flex-wrap gap-2">
                                <span v-for="m in a.members" :key="m.id"
                                    class="inline-block rounded-full bg-white border border-slate-200 px-3 py-0.5 text-xs text-slate-700 shadow-sm">
                                    {{ m.student_name }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 pt-1">
                            <AppIconButton label="Edit" @click="openEdit(a)"><PencilIcon class="h-4 w-4" /></AppIconButton>
                            <AppIconButton label="Delete" variant="danger" @click="remove(a)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                        </div>
                    </div>
                </template>

                <template #empty>
                    <EmptyState title="No research advisory groups found" :icon="UserGroupIcon" />
                </template>

                <template #footer>
                    <PaginationControl
                        :current-page="currentPage"
                        :total-pages="totalPages"
                        :total="filtered.length"
                        @prev="currentPage--"
                        @next="currentPage++"
                        @page="currentPage = $event"
                    />
                </template>
            </AppTable>

        </div>

        <!-- Create / Edit Modal -->
        <AppModal :show="showModal" :title="editTarget ? 'Edit Advisory Group' : 'New Advisory Group'" size="2xl" @close="closeModal">
            <div class="space-y-4">

                <!-- Faculty + Term (create only) -->
                <template v-if="!editTarget">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Faculty <span class="text-red-500">*</span></label>
                            <select v-model="form.user_id" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                                <option value="">Select faculty…</option>
                                <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
                            </select>
                            <p v-if="form.errors.user_id" class="mt-1 text-xs text-danger-500">{{ form.errors.user_id }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Term <span class="text-red-500">*</span></label>
                            <select v-model="form.academic_term_id" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                                <option value="">Select term…</option>
                                <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}</option>
                            </select>
                            <p v-if="form.errors.academic_term_id" class="mt-1 text-xs text-danger-500">{{ form.errors.academic_term_id }}</p>
                        </div>
                    </div>
                </template>

                <!-- Research Title -->
                <AppInput v-model="form.research_title" label="Research Title" required maxlength="500" :error="form.errors.research_title" />

                <!-- Grade / Role / Type / Units -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Grade Level <span class="text-red-500">*</span></label>
                        <select v-model="form.grade_level" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                            <option value="">Select grade…</option>
                            <option v-for="g in [7,8,9,10,11,12]" :key="g" :value="g">Grade {{ g }}</option>
                        </select>
                        <p v-if="form.errors.grade_level" class="mt-1 text-xs text-danger-500">{{ form.errors.grade_level }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Advisory Role <span class="text-red-500">*</span></label>
                        <select v-model="form.advisory_role" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                            <option value="lead">Lead Adviser</option>
                            <option value="co_adviser">Co-Adviser</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Research Type</label>
                        <select v-model="form.research_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                            <option value="">— None —</option>
                            <option value="thesis">Thesis</option>
                            <option value="investigatory">Investigatory</option>
                            <option value="science_research">Science Research</option>
                            <option value="feasibility">Feasibility</option>
                        </select>
                    </div>
                    <AppInput v-model.number="form.load_units" type="number" step="0.5" min="0.5" max="5" required
                        label="Load Units" :error="form.errors.load_units" />
                </div>

                <!-- Status + Remarks -->
                <div class="grid gap-4" :class="editTarget ? 'grid-cols-2' : 'grid-cols-1'">
                    <div v-if="editTarget">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                        <select v-model="form.status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="dropped">Dropped</option>
                        </select>
                    </div>
                    <AppInput v-model="form.remarks" label="Remarks" maxlength="500" />
                </div>

                <!-- Member Picker -->
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Group Members</p>

                    <!-- Selected chips -->
                    <div v-if="form.members.length > 0" class="mb-3 flex flex-wrap gap-2">
                        <span v-for="(m, i) in form.members" :key="i"
                            class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-200 px-3 py-0.5 text-xs text-indigo-700">
                            {{ m.student_name }}
                            <button type="button" @click="removeMember(i)" class="hover:text-danger-500"><XMarkIcon class="h-3 w-3" /></button>
                        </span>
                    </div>
                    <p v-else class="mb-2 text-xs text-slate-400 italic">No members selected yet.</p>

                    <!-- Student list -->
                    <template v-if="form.grade_level && (form.academic_term_id || editTarget)">
                        <div class="rounded-lg border border-slate-200 bg-slate-50">
                            <div class="p-2 border-b border-slate-200">
                                <div class="relative">
                                    <MagnifyingGlassIcon class="absolute left-2 top-2 h-4 w-4 text-slate-400" />
                                    <input v-model="studentSearch" placeholder="Search students…"
                                        class="w-full rounded-md border border-slate-200 bg-white pl-8 pr-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                </div>
                            </div>
                            <div v-if="loadingStudents" class="px-3 py-4 text-center text-xs text-slate-400">Loading students…</div>
                            <div v-else-if="filteredStudents.length === 0" class="px-3 py-4 text-center text-xs text-slate-400">
                                {{ availableStudents.length === 0 ? 'No enrolled students found for this grade level.' : 'No students match your search.' }}
                            </div>
                            <ul v-else class="max-h-48 overflow-y-auto divide-y divide-slate-100">
                                <li v-for="s in filteredStudents" :key="s.id"
                                    class="flex items-center justify-between px-3 py-2 cursor-pointer transition-colors hover:bg-white"
                                    :class="isSelected(s.id) ? 'bg-indigo-50' : ''"
                                    @click="toggleStudent(s)">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <input type="checkbox" :checked="isSelected(s.id)" class="h-3.5 w-3.5 rounded border-slate-300 text-indigo-600" readonly />
                                        <span class="text-xs text-slate-700 truncate">{{ s.student_name }}</span>
                                    </div>
                                    <span v-if="s.already_assigned && !isSelected(s.id)"
                                        class="ml-2 shrink-0 inline-flex items-center gap-1 text-xs text-warning-600"
                                        :title="`Already in: ${s.assigned_group} (${s.assigned_faculty})`">
                                        <ExclamationTriangleIcon class="h-3.5 w-3.5 text-warning-500" />
                                        <span class="hidden sm:inline">Assigned</span>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </template>
                    <p v-else class="text-xs text-slate-400 italic">
                        Select a grade level{{ !editTarget ? ' and term' : '' }} to load students.
                    </p>
                </div>
            </div>

            <template #footer>
                <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
                <AppButton :loading="form.processing" @click="submit">{{ editTarget ? 'Save Changes' : 'Create Group' }}</AppButton>
            </template>
        </AppModal>

    </AdminLayout>
</template>
