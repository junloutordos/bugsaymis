<script setup>
import { ref, reactive } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import { CheckCircleIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    student:        Object,
    grade_level:    Number,
    school_year:    String,
    school_year_id: Number,
    existing:       Object,
    submitted:      Object,
})

const flash = usePage().props.flash ?? {}
const activeTab = ref('academic')
const saving = ref(false)

const tabs = [
    { key: 'academic',  label: 'Academic' },
    { key: 'activities',label: 'Activities' },
    { key: 'social',    label: 'Social' },
    { key: 'career',    label: 'Career' },
    { key: 'residence', label: 'Residence' },
    { key: 'health',    label: 'Health' },
]

// ── Form data ──────────────────────────────────────────────────────────────

const academic = reactive({
    subject_like:        props.existing.academic?.subject_like ?? '',
    subject_least:       props.existing.academic?.subject_least ?? '',
    subject_difficult:   props.existing.academic?.subject_difficult ?? '',
    subjectlearnedmost:  props.existing.academic?.subjectlearnedmost ?? '',
    subjectlearnedleast: props.existing.academic?.subjectlearnedleast ?? '',
    subjecttaugtbest:    props.existing.academic?.subjecttaugtbest ?? '',
    subjecttaugtworst:   props.existing.academic?.subjecttaugtworst ?? '',
})

const clubs        = ref(props.existing.clubs.length ? [...props.existing.clubs] : [{ clubname: '', position: '' }])
const activities   = ref(props.existing.activities.length ? [...props.existing.activities] : [{ activityname: '', category: '', involvement: '' }])
const awards       = ref(props.existing.awards.length ? [...props.existing.awards] : [{ competition: '', award: '', category: '' }])

const social = reactive({
    traits:       props.existing.personality?.traits ?? '',
    physical:     props.existing.selfassessment?.physical ?? '',
    mental:       props.existing.selfassessment?.mental ?? '',
    speech_en:    props.existing.selfassessment?.speech_en ?? '',
    speech_fil:   props.existing.selfassessment?.speech_fil ?? '',
    writing:      props.existing.selfassessment?.writing ?? '',
    personality:  props.existing.selfassessment?.personality ?? '',
    character1:   props.existing.selfassessment?.character1 ?? '',
    state1:  props.existing.statements?.state1 ?? '',
    state2:  props.existing.statements?.state2 ?? '',
    state3:  props.existing.statements?.state3 ?? '',
    state4:  props.existing.statements?.state4 ?? '',
    state5:  props.existing.statements?.state5 ?? '',
    state6:  props.existing.statements?.state6 ?? '',
    state7:  props.existing.statements?.state7 ?? '',
    state8:  props.existing.statements?.state8 ?? '',
    state9:  props.existing.statements?.state9 ?? '',
    state10: props.existing.statements?.state10 ?? '',
    fr_age:           props.existing.friend_age?.fr_age ?? '',
    fr_older:         props.existing.friend_age?.fr_older ?? '',
    fr_younger:       props.existing.friend_age?.fr_younger ?? '',
    fr_boys:          props.existing.friend_group?.fr_boys ?? '',
    fr_girls:         props.existing.friend_group?.fr_girls ?? '',
    fr_otherschool:   props.existing.friend_group?.fr_otherschool ?? '',
    fr_fromthischool: props.existing.friend_group?.fr_fromthischool ?? '',
})

const friends = ref(props.existing.friends.length ? props.existing.friends.map(f => f.fr_name) : [''])
const talents  = ref(props.existing.talents.length ? props.existing.talents.map(t => t.talent) : [''])

const career = reactive({
    '1stchoice': props.existing.vocational?.['1stchoice'] ?? '',
    '2ndchoice': props.existing.vocational?.['2ndchoice'] ?? '',
})

const residence = reactive({
    residencetype:  props.existing.residence?.residencetype ?? '',
    transportation: props.existing.residence?.transportation ?? '',
})

const health = reactive({
    weight:         props.existing.health?.weight ?? '',
    height:         props.existing.health?.height ?? '',
    sight:          props.existing.health?.sight ?? 'No',
    hearing:        props.existing.health?.hearing ?? 'No',
    speech:         props.existing.health?.speech ?? 'No',
    general_health: props.existing.health?.general_health ?? 'No',
    ailment:        props.existing.health?.ailment ?? 'No',
    ailment_remarks:props.existing.health?.ailment_remarks ?? '',
})

// ── Save ──────────────────────────────────────────────────────────────────

function saveTab(section) {
    saving.value = true
    const payloads = {
        academic,
        activities: { clubs: clubs.value, activities: activities.value, awards: awards.value },
        social:     { ...social, friends: friends.value, talents: talents.value },
        career,
        residence,
        health,
    }
    router.post(route('student-portal.profile.save', section), payloads[section], {
        preserveScroll: true,
        onFinish: () => { saving.value = false },
    })
}

const selfLabels = [
    'My parents…', 'My greatest mistake was…', 'I wish…', 'My teachers…',
    'I like my friends because…', 'My family…', 'I always wanted to…',
    'My mother/father…', 'At school, I get along with…', 'My role model…',
]

const ratingOptions = [
    { value: '1', label: '1 — Excellent' },
    { value: '2', label: '2 — Very Good' },
    { value: '3', label: '3 — Good' },
    { value: '4', label: '4 — Fair' },
    { value: '5', label: '5 — Poor' },
]

const yesNoOptions = ['Yes', 'No']

function addRow(list, template) { list.value.push({ ...template }) }
function removeRow(list, i) { if (list.value.length > 1) list.value.splice(i, 1) }
function addItem(list) { list.value.push('') }
function removeItem(list, i) { if (list.value.length > 1) list.value.splice(i, 1) }
</script>

<template>
    <Head title="Student Portal — Guidance Profile" />
    <StudentPortalLayout>
        <div class="space-y-5">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-semibold text-slate-800">Guidance Profile</h1>
                    <p class="text-sm text-slate-500">Grade {{ grade_level }} · S.Y. {{ school_year }}</p>
                </div>
            </div>

            <!-- Flash -->
            <div v-if="flash.success" class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-lg px-4 py-3">
                <CheckCircleIcon class="w-4 h-4 shrink-0" /> {{ flash.success }}
            </div>

            <!-- Tabs + Form -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="flex overflow-x-auto border-b border-slate-200">
                    <button
                        v-for="tab in tabs" :key="tab.key"
                        @click="activeTab = tab.key"
                        class="relative flex items-center gap-1.5 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors"
                        :class="activeTab === tab.key ? 'border-indigo-600 text-indigo-600 bg-indigo-50' : 'border-transparent text-slate-500 hover:text-slate-700'"
                    >
                        {{ tab.label }}
                        <CheckCircleIcon v-if="submitted[tab.key]" class="w-3.5 h-3.5 text-emerald-500" />
                    </button>
                </div>

                <div class="p-6 space-y-5">

                    <!-- ── ACADEMIC ── -->
                    <template v-if="activeTab === 'academic'">
                        <p class="text-sm text-slate-500">Tell us about your academic preferences this year.</p>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div v-for="(label, key) in {
                                subject_like: 'Subject you like most',
                                subject_least: 'Subject you like least',
                                subject_difficult: 'Most difficult subject',
                                subjectlearnedmost: 'Subject you learned from most',
                                subjectlearnedleast: 'Subject you learned from least',
                                subjecttaugtbest: 'Best-taught subject',
                                subjecttaugtworst: 'Least-taught subject',
                            }" :key="key">
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ label }}</label>
                                <input v-model="academic[key]" type="text" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                            </div>
                        </div>
                        <button @click="saveTab('academic')" :disabled="saving" class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
                            {{ saving ? 'Saving…' : 'Save Academic' }}
                        </button>
                    </template>

                    <!-- ── ACTIVITIES ── -->
                    <template v-if="activeTab === 'activities'">
                        <!-- Clubs -->
                        <div>
                            <h3 class="text-sm font-semibold text-slate-700 mb-2">Club Memberships</h3>
                            <div v-for="(row, i) in clubs" :key="i" class="flex gap-2 mb-2">
                                <input v-model="row.clubname" placeholder="Club name" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 flex-1" />
                                <input v-model="row.position" placeholder="Position" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-32" />
                                <button @click="removeRow(clubs, i)" class="p-2 text-slate-400 hover:text-red-500"><TrashIcon class="w-4 h-4" /></button>
                            </div>
                            <button @click="addRow(clubs, { clubname: '', position: '' })" class="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
                                <PlusIcon class="w-4 h-4" /> Add club
                            </button>
                        </div>

                        <!-- Activities -->
                        <div>
                            <h3 class="text-sm font-semibold text-slate-700 mb-2">Activities & Events</h3>
                            <div v-for="(row, i) in activities" :key="i" class="flex gap-2 mb-2 flex-wrap">
                                <input v-model="row.activityname" placeholder="Activity name" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 flex-1 min-w-0" />
                                <input v-model="row.category" placeholder="Category" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-32" />
                                <input v-model="row.involvement" placeholder="Involvement" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-32" />
                                <button @click="removeRow(activities, i)" class="p-2 text-slate-400 hover:text-red-500"><TrashIcon class="w-4 h-4" /></button>
                            </div>
                            <button @click="addRow(activities, { activityname: '', category: '', involvement: '' })" class="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
                                <PlusIcon class="w-4 h-4" /> Add activity
                            </button>
                        </div>

                        <!-- Awards -->
                        <div>
                            <h3 class="text-sm font-semibold text-slate-700 mb-2">Awards & Competitions</h3>
                            <div v-for="(row, i) in awards" :key="i" class="flex gap-2 mb-2 flex-wrap">
                                <input v-model="row.competition" placeholder="Competition" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 flex-1 min-w-0" />
                                <input v-model="row.award" placeholder="Award / Place" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-32" />
                                <input v-model="row.category" placeholder="Level" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-28" />
                                <button @click="removeRow(awards, i)" class="p-2 text-slate-400 hover:text-red-500"><TrashIcon class="w-4 h-4" /></button>
                            </div>
                            <button @click="addRow(awards, { competition: '', award: '', category: '' })" class="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
                                <PlusIcon class="w-4 h-4" /> Add award
                            </button>
                        </div>

                        <button @click="saveTab('activities')" :disabled="saving" class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
                            {{ saving ? 'Saving…' : 'Save Activities' }}
                        </button>
                    </template>

                    <!-- ── SOCIAL ── -->
                    <template v-if="activeTab === 'social'">
                        <!-- Personality traits -->
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Personality Traits</label>
                            <p class="text-xs text-slate-400 mb-2">Describe your personality in your own words.</p>
                            <textarea v-model="social.traits" rows="3" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                        </div>

                        <!-- Self-assessment -->
                        <div>
                            <p class="text-sm font-semibold text-slate-700 mb-2">Self-Assessment <span class="text-slate-400 font-normal">(1 = Excellent, 5 = Poor)</span></p>
                            <div class="grid sm:grid-cols-2 gap-3">
                                <div v-for="(label, key) in { physical: 'Physical', mental: 'Mental/Academic', speech_en: 'English Communication', speech_fil: 'Filipino Communication', writing: 'Writing', personality: 'Personality', character1: 'Character' }" :key="key">
                                    <label class="block text-sm text-slate-600 mb-1">{{ label }}</label>
                                    <select v-model="social[key]" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                                        <option value="">Select…</option>
                                        <option v-for="o in ratingOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Self-completion statements -->
                        <div>
                            <p class="text-sm font-semibold text-slate-700 mb-2">Complete these statements</p>
                            <div class="space-y-3">
                                <div v-for="(label, i) in selfLabels" :key="i">
                                    <label class="block text-sm text-slate-500 mb-1">{{ label }}</label>
                                    <input v-model="social[`state${i+1}`]" type="text" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                                </div>
                            </div>
                        </div>

                        <!-- Friends -->
                        <div>
                            <p class="text-sm font-semibold text-slate-700 mb-2">Friends (list names)</p>
                            <div v-for="(_, i) in friends" :key="i" class="flex gap-2 mb-2">
                                <input v-model="friends[i]" placeholder="Friend's name" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 flex-1" />
                                <button @click="removeItem(friends, i)" class="p-2 text-slate-400 hover:text-red-500"><TrashIcon class="w-4 h-4" /></button>
                            </div>
                            <button @click="addItem(friends)" class="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"><PlusIcon class="w-4 h-4" /> Add friend</button>
                        </div>

                        <!-- Friend patterns -->
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div v-for="(label, key) in { fr_age: 'Friends same age?', fr_older: 'Friends older than you?', fr_younger: 'Friends younger than you?', fr_boys: 'Friends who are boys?', fr_girls: 'Friends who are girls?', fr_otherschool: 'Friends from other schools?', fr_fromthischool: 'Friends from PSHS-CRC?' }" :key="key">
                                <label class="block text-sm text-slate-600 mb-1">{{ label }}</label>
                                <select v-model="social[key]" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                                    <option value="">Select…</option>
                                    <option v-for="o in yesNoOptions" :key="o" :value="o">{{ o }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Talents -->
                        <div>
                            <p class="text-sm font-semibold text-slate-700 mb-2">Talents & Hobbies</p>
                            <div v-for="(_, i) in talents" :key="i" class="flex gap-2 mb-2">
                                <input v-model="talents[i]" placeholder="e.g. Playing guitar" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 flex-1" />
                                <button @click="removeItem(talents, i)" class="p-2 text-slate-400 hover:text-red-500"><TrashIcon class="w-4 h-4" /></button>
                            </div>
                            <button @click="addItem(talents)" class="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"><PlusIcon class="w-4 h-4" /> Add talent</button>
                        </div>

                        <button @click="saveTab('social')" :disabled="saving" class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
                            {{ saving ? 'Saving…' : 'Save Social & Personality' }}
                        </button>
                    </template>

                    <!-- ── CAREER ── -->
                    <template v-if="activeTab === 'career'">
                        <p class="text-sm text-slate-500">What do you want to be when you grow up?</p>
                        <div class="space-y-4 max-w-md">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">1st Choice Career</label>
                                <input v-model="career['1stchoice']" type="text" placeholder="e.g. Medical Doctor" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">2nd Choice Career</label>
                                <input v-model="career['2ndchoice']" type="text" placeholder="e.g. Engineer" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                            </div>
                        </div>
                        <button @click="saveTab('career')" :disabled="saving" class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
                            {{ saving ? 'Saving…' : 'Save Career' }}
                        </button>
                    </template>

                    <!-- ── RESIDENCE ── -->
                    <template v-if="activeTab === 'residence'">
                        <div class="grid sm:grid-cols-2 gap-4 max-w-lg">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Residence Type</label>
                                <select v-model="residence.residencetype" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                                    <option value="">Select…</option>
                                    <option>Family Home</option>
                                    <option>School Dormitory</option>
                                    <option>Boarding House</option>
                                    <option>Relatives</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Mode of Transportation</label>
                                <select v-model="residence.transportation" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                                    <option value="">Select…</option>
                                    <option>Family-owned vehicle</option>
                                    <option>Car-pool</option>
                                    <option>Public transportation</option>
                                    <option>Service</option>
                                    <option>Walking</option>
                                    <option>N/A</option>
                                </select>
                            </div>
                        </div>
                        <button @click="saveTab('residence')" :disabled="saving" class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
                            {{ saving ? 'Saving…' : 'Save Residence' }}
                        </button>
                    </template>

                    <!-- ── HEALTH ── -->
                    <template v-if="activeTab === 'health'">
                        <p class="text-sm text-slate-500">Basic physical health information for this school year.</p>
                        <div class="grid sm:grid-cols-2 gap-4 max-w-lg">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Weight (kg)</label>
                                <input v-model="health.weight" type="text" placeholder="e.g. 55" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Height (cm)</label>
                                <input v-model="health.height" type="text" placeholder="e.g. 162" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                            </div>
                            <div v-for="(label, key) in { sight: 'Sight issue?', hearing: 'Hearing issue?', speech: 'Speech issue?', ailment: 'Any ailment?' }" :key="key">
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ label }}</label>
                                <select v-model="health[key]" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                                    <option value="No">No</option>
                                    <option value="Yes">Yes</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="health.ailment === 'Yes'" class="max-w-lg">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ailment details</label>
                            <input v-model="health.ailment_remarks" type="text" placeholder="Describe your ailment" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                        </div>
                        <button @click="saveTab('health')" :disabled="saving" class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
                            {{ saving ? 'Saving…' : 'Save Health' }}
                        </button>
                    </template>

                </div>
            </div>
        </div>
    </StudentPortalLayout>
</template>
