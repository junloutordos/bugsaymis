<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import {
    UserIcon, AcademicCapIcon, TrophyIcon, UserGroupIcon,
    BriefcaseIcon, HomeIcon, HeartIcon, ArrowLeftIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    student: Object,
    profile: Object,
})

const activeTab = ref('academic')

const tabs = [
    { key: 'academic',     label: 'Academic',    icon: AcademicCapIcon },
    { key: 'activities',   label: 'Activities',  icon: TrophyIcon },
    { key: 'social',       label: 'Social',      icon: UserGroupIcon },
    { key: 'career',       label: 'Career',      icon: BriefcaseIcon },
    { key: 'residence',    label: 'Residence',   icon: HomeIcon },
    { key: 'health',       label: 'Health',      icon: HeartIcon },
]

const studentName = computed(() => {
    const s = props.student
    return `${s.lastname}, ${s.firstname}${s.middlename ? ' ' + s.middlename : ''}`
})

const gradeLabel = { 7: 'Grade 7', 8: 'Grade 8', 9: 'Grade 9', 10: 'Grade 10', 11: 'Grade 11', 12: 'Grade 12' }

// Group array of records by levelid, returning sorted level keys
function byLevel(rows) {
    const map = {}
    for (const r of rows) {
        const lvl = String(r.levelid ?? 'Unknown')
        if (!map[lvl]) map[lvl] = []
        map[lvl].push(r)
    }
    const sorted = Object.keys(map).sort((a, b) => parseInt(a) - parseInt(b))
    return sorted.map(lvl => ({ level: lvl, label: gradeLabel[parseInt(lvl)] ?? `Grade ${lvl}`, rows: map[lvl] }))
}

const academicStanding   = computed(() => props.profile.academic_standing ?? [])
const academicPrefs      = computed(() => byLevel(props.profile.academic_preferences ?? []))
const clubs              = computed(() => byLevel(props.profile.club_memberships ?? []))
const activities         = computed(() => byLevel(props.profile.activity_participations ?? []))
const awards             = computed(() => byLevel(props.profile.awards ?? []))
const external           = computed(() => byLevel(props.profile.external_activities ?? []))
const personality        = computed(() => byLevel(props.profile.personality_traits ?? []))
const selfAssessment     = computed(() => byLevel(props.profile.selfassessment ?? []))
const selfStatements     = computed(() => byLevel(props.profile.self_statements ?? []))
const talents            = computed(() => byLevel(props.profile.talents ?? []))
const friendAge          = computed(() => byLevel(props.profile.friend_age_patterns ?? []))
const friendGroup        = computed(() => byLevel(props.profile.friend_group_patterns ?? []))
const bestFriends        = computed(() => byLevel(props.profile.bestfriends ?? []))
const vocational         = computed(() => byLevel(props.profile.vocational ?? []))
const residence          = computed(() => byLevel(props.profile.residence ?? []))
const health             = computed(() => byLevel(props.profile.health ?? []))

const selfLabels = [
    'My parents…', 'My greatest mistake was…', 'I wish…', 'My teachers…',
    'I like my friends because…', 'My family…', 'I always wanted to…',
    'My mother/father…', 'At school, I get along with…', 'My role model…',
]

const ratingLabel = (v) => {
    const map = { 1: 'Excellent', 2: 'Very Good', 3: 'Good', 4: 'Fair', 5: 'Poor' }
    return map[parseInt(v)] ?? v
}
</script>

<template>
    <Head :title="`${studentName} — Cumulative Record`" />
    <AdminLayout :title="studentName">
        <div class="space-y-5">
            <!-- Back + Student Header -->
            <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                <AppButton variant="ghost" @click="router.visit(route('guidance.cumulative.index'))">
                    <ArrowLeftIcon class="w-4 h-4" /> Back to list
                </AppButton>
            </div>

            <!-- Student Info Card -->
            <AppCard>
                <div class="flex flex-wrap gap-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center">
                            <UserIcon class="w-6 h-6 text-indigo-500" />
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-base">{{ studentName }}</p>
                            <p class="text-sm text-slate-500">{{ student.pisaysystemID || 'No barcode' }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-8 gap-y-1 text-sm">
                        <div><span class="text-slate-400">Batch</span><p class="font-medium text-slate-700">{{ student.batch || '—' }}</p></div>
                        <div><span class="text-slate-400">Status</span><p class="font-medium text-slate-700">{{ student.status || '—' }}</p></div>
                        <div><span class="text-slate-400">Sex</span><p class="font-medium text-slate-700">{{ student.sex || '—' }}</p></div>
                        <div><span class="text-slate-400">Email</span><p class="font-medium text-slate-700 text-xs">{{ student.student_email || '—' }}</p></div>
                    </div>
                    <a
                        :href="route('students.health.show', student.pisaysystemID)"
                        class="ml-auto self-center inline-flex items-center gap-1.5 text-sm text-rose-600 hover:text-rose-800 font-medium"
                    >
                        <HeartIcon class="w-4 h-4" /> Medical Records
                    </a>
                </div>
            </AppCard>

            <!-- Tabs -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="flex overflow-x-auto border-b border-slate-200">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="activeTab = tab.key"
                        class="flex items-center gap-1.5 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors"
                        :class="activeTab === tab.key
                            ? 'border-indigo-600 text-indigo-600 bg-indigo-50'
                            : 'border-transparent text-slate-500 hover:text-slate-700'"
                    >
                        <component :is="tab.icon" class="w-4 h-4" />
                        {{ tab.label }}
                    </button>
                </div>

                <div class="p-5 space-y-6">

                    <!-- ── ACADEMIC ── -->
                    <template v-if="activeTab === 'academic'">
                        <!-- Academic Standing per year -->
                        <section>
                            <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Academic Standing</h3>
                            <div v-if="academicStanding.length === 0" class="text-sm text-slate-400">No data recorded.</div>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div v-for="r in academicStanding" :key="r.id" class="bg-slate-50 rounded-lg p-3 text-sm">
                                    <p class="font-semibold text-slate-700">{{ r.gradelevel }}</p>
                                    <p class="text-slate-500">Section: {{ r.section }}</p>
                                    <p class="text-slate-500">Scholarship: {{ r.scholarshipstatus }}</p>
                                    <p class="text-slate-500">Academic: {{ r.academicstatus }}</p>
                                </div>
                            </div>
                        </section>

                        <!-- Subject Preferences -->
                        <section v-if="academicPrefs.length">
                            <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Subject Preferences per Year</h3>
                            <div class="space-y-3">
                                <details v-for="group in academicPrefs" :key="group.level" class="bg-slate-50 rounded-lg">
                                    <summary class="px-4 py-2 text-sm font-medium text-slate-700 cursor-pointer">{{ group.label }}</summary>
                                    <div class="px-4 pb-3 grid sm:grid-cols-2 gap-x-6 gap-y-1 text-sm text-slate-600">
                                        <p v-for="r in group.rows" :key="r.id">
                                            <span class="text-slate-400">Liked:</span> {{ r.subject_like }}<br/>
                                            <span class="text-slate-400">Least liked:</span> {{ r.subject_least }}<br/>
                                            <span class="text-slate-400">Hardest:</span> {{ r.subject_difficult }}<br/>
                                            <span class="text-slate-400">Learned most:</span> {{ r.subjectlearnedmost }}
                                        </p>
                                    </div>
                                </details>
                            </div>
                        </section>
                    </template>

                    <!-- ── ACTIVITIES ── -->
                    <template v-if="activeTab === 'activities'">
                        <section v-if="clubs.length">
                            <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Club Memberships</h3>
                            <div class="space-y-2">
                                <details v-for="g in clubs" :key="g.level" class="bg-slate-50 rounded-lg">
                                    <summary class="px-4 py-2 text-sm font-medium text-slate-700 cursor-pointer">{{ g.label }}</summary>
                                    <table class="min-w-full text-sm px-4 pb-3">
                                        <tbody class="divide-y divide-slate-100">
                                            <tr v-for="r in g.rows" :key="r.id" class="px-4">
                                                <td class="py-1.5 pl-4 pr-2 text-slate-700 font-medium">{{ r.clubname }}</td>
                                                <td class="py-1.5 text-slate-500">{{ r.position }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </details>
                            </div>
                        </section>

                        <section v-if="awards.length">
                            <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Awards & Competitions</h3>
                            <div class="space-y-2">
                                <details v-for="g in awards" :key="g.level" class="bg-slate-50 rounded-lg">
                                    <summary class="px-4 py-2 text-sm font-medium text-slate-700 cursor-pointer">{{ g.label }}</summary>
                                    <table class="min-w-full text-sm">
                                        <tbody class="divide-y divide-slate-100">
                                            <tr v-for="r in g.rows" :key="r.id">
                                                <td class="py-1.5 pl-4 pr-2 font-medium text-slate-700">{{ r.award }}</td>
                                                <td class="py-1.5 pr-2 text-slate-500">{{ r.competition }}</td>
                                                <td class="py-1.5 pr-4 text-xs text-slate-400">{{ r.category }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </details>
                            </div>
                        </section>

                        <section v-if="activities.length">
                            <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Activity Participations</h3>
                            <div class="space-y-2">
                                <details v-for="g in activities" :key="g.level" class="bg-slate-50 rounded-lg">
                                    <summary class="px-4 py-2 text-sm font-medium text-slate-700 cursor-pointer">{{ g.label }}</summary>
                                    <table class="min-w-full text-sm">
                                        <tbody class="divide-y divide-slate-100">
                                            <tr v-for="r in g.rows" :key="r.id">
                                                <td class="py-1.5 pl-4 pr-2 font-medium text-slate-700">{{ r.activityname }}</td>
                                                <td class="py-1.5 pr-2 text-slate-500">{{ r.involvement }}</td>
                                                <td class="py-1.5 pr-4 text-xs text-slate-400">{{ r.category }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </details>
                            </div>
                        </section>

                        <p v-if="!clubs.length && !awards.length && !activities.length" class="text-sm text-slate-400">No activity data recorded.</p>
                    </template>

                    <!-- ── SOCIAL / PERSONALITY ── -->
                    <template v-if="activeTab === 'social'">
                        <section v-if="personality.length">
                            <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Personality Traits</h3>
                            <div class="space-y-2">
                                <div v-for="g in personality" :key="g.level" class="bg-slate-50 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-slate-500 mb-1">{{ g.label }}</p>
                                    <p v-for="r in g.rows" :key="r.id" class="text-sm text-slate-700">{{ r.traits }}</p>
                                </div>
                            </div>
                        </section>

                        <section v-if="selfAssessment.length">
                            <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Self-Assessment (1=Excellent, 5=Poor)</h3>
                            <div class="space-y-2">
                                <details v-for="g in selfAssessment" :key="g.level" class="bg-slate-50 rounded-lg">
                                    <summary class="px-4 py-2 text-sm font-medium text-slate-700 cursor-pointer">{{ g.label }}</summary>
                                    <div v-for="r in g.rows" :key="r.id" class="px-4 pb-3 grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
                                        <div><span class="text-slate-400 text-xs">Physical</span><p>{{ ratingLabel(r.physical) }}</p></div>
                                        <div><span class="text-slate-400 text-xs">Mental</span><p>{{ ratingLabel(r.mental) }}</p></div>
                                        <div><span class="text-slate-400 text-xs">English</span><p>{{ ratingLabel(r.speech_en) }}</p></div>
                                        <div><span class="text-slate-400 text-xs">Filipino</span><p>{{ ratingLabel(r.speech_fil) }}</p></div>
                                        <div><span class="text-slate-400 text-xs">Writing</span><p>{{ ratingLabel(r.writing) }}</p></div>
                                        <div><span class="text-slate-400 text-xs">Personality</span><p>{{ ratingLabel(r.personality) }}</p></div>
                                        <div><span class="text-slate-400 text-xs">Character</span><p>{{ ratingLabel(r.character1) }}</p></div>
                                    </div>
                                </details>
                            </div>
                        </section>

                        <section v-if="selfStatements.length">
                            <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Self-Completion Statements</h3>
                            <div class="space-y-2">
                                <details v-for="g in selfStatements" :key="g.level" class="bg-slate-50 rounded-lg">
                                    <summary class="px-4 py-2 text-sm font-medium text-slate-700 cursor-pointer">{{ g.label }}</summary>
                                    <div v-for="r in g.rows" :key="r.id" class="px-4 pb-3 space-y-1 text-sm">
                                        <p v-for="(label, i) in selfLabels" :key="i">
                                            <span class="text-slate-400">{{ label }}</span>
                                            {{ r[`state${i+1}`] }}
                                        </p>
                                    </div>
                                </details>
                            </div>
                        </section>

                        <section v-if="talents.length">
                            <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Talents & Hobbies</h3>
                            <div class="space-y-2">
                                <div v-for="g in talents" :key="g.level" class="bg-slate-50 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-slate-500 mb-1">{{ g.label }}</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span v-for="r in g.rows" :key="r.id" class="px-2 py-0.5 bg-white border border-slate-200 rounded text-xs text-slate-700">{{ r.talent }}</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section v-if="bestFriends.length">
                            <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Friends</h3>
                            <div class="space-y-2">
                                <div v-for="g in bestFriends" :key="g.level" class="bg-slate-50 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-slate-500 mb-1">{{ g.label }}</p>
                                    <p v-for="r in g.rows" :key="r.id" class="text-sm text-slate-700">{{ r.fr_name }}</p>
                                </div>
                            </div>
                        </section>

                        <p v-if="!personality.length && !selfAssessment.length && !selfStatements.length && !talents.length && !bestFriends.length" class="text-sm text-slate-400">No social/personality data recorded.</p>
                    </template>

                    <!-- ── CAREER ── -->
                    <template v-if="activeTab === 'career'">
                        <section v-if="vocational.length">
                            <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Vocational / Career Choices</h3>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div v-for="g in vocational" :key="g.level">
                                    <div v-for="r in g.rows" :key="r.id" class="bg-slate-50 rounded-lg p-3 text-sm">
                                        <p class="text-xs font-semibold text-slate-500 mb-1">{{ g.label }}</p>
                                        <p><span class="text-slate-400">1st choice:</span> <span class="font-medium text-slate-700">{{ r['1stchoice'] || '—' }}</span></p>
                                        <p><span class="text-slate-400">2nd choice:</span> <span class="text-slate-600">{{ r['2ndchoice'] || '—' }}</span></p>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <p v-else class="text-sm text-slate-400">No career data recorded.</p>
                    </template>

                    <!-- ── RESIDENCE ── -->
                    <template v-if="activeTab === 'residence'">
                        <section v-if="residence.length">
                            <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Residence & Transportation</h3>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div v-for="g in residence" :key="g.level">
                                    <div v-for="r in g.rows" :key="r.id" class="bg-slate-50 rounded-lg p-3 text-sm">
                                        <p class="text-xs font-semibold text-slate-500 mb-1">{{ g.label }}</p>
                                        <p><span class="text-slate-400">Type:</span> {{ r.residencetype || '—' }}</p>
                                        <p><span class="text-slate-400">Transport:</span> {{ r.transportation || '—' }}</p>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <p v-else class="text-sm text-slate-400">No residence data recorded.</p>
                    </template>

                    <!-- ── HEALTH (physical, via egcu_health) ── -->
                    <template v-if="activeTab === 'health'">
                        <div class="flex items-start justify-between">
                            <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Physical Health per Year</h3>
                            <a :href="route('students.health.show', student.pisaysystemID)" class="text-xs text-rose-600 hover:text-rose-800 font-medium">
                                Full Medical Records →
                            </a>
                        </div>
                        <div v-if="health.length" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div v-for="g in health" :key="g.level">
                                <div v-for="r in g.rows" :key="r.id" class="bg-slate-50 rounded-lg p-3 text-sm">
                                    <p class="text-xs font-semibold text-slate-500 mb-1">{{ g.label }}</p>
                                    <p><span class="text-slate-400">Weight:</span> {{ r.weight || '—' }} kg</p>
                                    <p><span class="text-slate-400">Height:</span> {{ r.height || '—' }} cm</p>
                                    <p><span class="text-slate-400">Sight issue:</span> {{ r.sight }}</p>
                                    <p><span class="text-slate-400">Hearing issue:</span> {{ r.hearing }}</p>
                                    <p v-if="r.ailment_remarks && r.ailment_remarks !== 'N/A'">
                                        <span class="text-slate-400">Ailment:</span> {{ r.ailment_remarks }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-slate-400">No physical health data recorded. Check full medical records.</p>
                    </template>

                </div>
            </div>
        </div>
    </AdminLayout>
</template>
