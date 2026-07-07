<script setup>
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import {
    ArchiveBoxIcon, CheckCircleIcon, PlusIcon, SparklesIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    board:      { type: Array,  default: () => [] },
    my_reports: { type: Array,  default: () => [] },
    my_points:  { type: Number, default: 0 },
    categories: { type: Object, default: () => ({}) },
})

const flash = usePage().props.flash ?? {}

const tab = ref('board')

const showModal  = ref(false)
const submitting = ref(false)

const form = ref({
    type: 'lost',
    item_name: '',
    description: '',
    category: '',
    location: '',
    date_lost_found: '',
    photo_base64: '',
})

function openModal() {
    Object.assign(form.value, {
        type: 'lost', item_name: '', description: '', category: '',
        location: '', date_lost_found: new Date().toISOString().slice(0, 10), photo_base64: '',
    })
    showModal.value = true
}

function onPhotoSelected(e) {
    const file = e.target.files?.[0]
    if (!file) return
    const reader = new FileReader()
    reader.onload = () => { form.value.photo_base64 = reader.result }
    reader.readAsDataURL(file)
}

function submit() {
    submitting.value = true
    router.post(route('student-portal.lost-found.store'), form.value, {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false },
        onFinish:  () => { submitting.value = false },
    })
}

function fmtDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

function statusClass(status) {
    return {
        open:             'bg-amber-50 text-amber-700 border-amber-100',
        matched:          'bg-blue-50 text-blue-700 border-blue-100',
        claimed:          'bg-emerald-50 text-emerald-700 border-emerald-100',
        closed:           'bg-slate-50 text-slate-500 border-slate-200',
        pending_turnover: 'bg-amber-50 text-amber-700 border-amber-100',
        in_custody:       'bg-indigo-50 text-indigo-700 border-indigo-100',
        disposed:         'bg-slate-50 text-slate-500 border-slate-200',
    }[status] ?? 'bg-slate-50 text-slate-500 border-slate-200'
}
</script>

<template>
    <Head title="Lost & Found" />

    <StudentPortalLayout>
        <div class="space-y-5">

            <!-- Header -->
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-lg font-bold text-slate-800">Lost &amp; Found</h1>
                    <p class="text-sm text-slate-500">
                        Report lost items or turn over found items to the GSU office.
                    </p>
                </div>
                <button @click="openModal"
                    class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium shrink-0">
                    <PlusIcon class="w-4 h-4" /> Report Item
                </button>
            </div>

            <!-- Flash -->
            <div v-if="flash.success"
                class="bg-emerald-50 border border-emerald-100 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                <CheckCircleIcon class="w-4 h-4 shrink-0" />{{ flash.success }}
            </div>

            <!-- Honesty points banner -->
            <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-100 rounded-xl px-4 py-3 flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                    <SparklesIcon class="w-5 h-5 text-amber-600" />
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-amber-800">{{ my_points }} Honesty Points</p>
                    <p class="text-xs text-amber-700">
                        Earn 10 points for every found item you turn over to the GSU office.
                    </p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm bg-white">
                <button type="button" @click="tab = 'board'"
                    :class="['px-4 py-2 font-medium', tab === 'board' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50']">
                    Found Items ({{ board.length }})
                </button>
                <button type="button" @click="tab = 'mine'"
                    :class="['px-4 py-2 font-medium border-l border-slate-200', tab === 'mine' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50']">
                    My Reports ({{ my_reports.length }})
                </button>
            </div>

            <!-- Found items board -->
            <div v-if="tab === 'board'">
                <div v-if="board.length === 0"
                    class="bg-white rounded-xl border border-slate-200 p-10 text-center">
                    <ArchiveBoxIcon class="w-10 h-10 text-slate-300 mx-auto mb-2" />
                    <p class="text-sm font-medium text-slate-600">No items in GSU custody</p>
                    <p class="text-xs text-slate-400 mt-1">Found items turned over to GSU will appear here.</p>
                </div>
                <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div v-for="item in board" :key="item.id"
                        class="bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col">
                        <div class="h-28 bg-slate-100 flex items-center justify-center overflow-hidden">
                            <img v-if="item.has_photo" :src="route('student-portal.lost-found.photo', item.id)"
                                class="w-full h-full object-cover" loading="lazy" alt="" />
                            <ArchiveBoxIcon v-else class="w-8 h-8 text-slate-300" />
                        </div>
                        <div class="p-3">
                            <p class="text-sm font-semibold text-slate-800">{{ item.item_name }}</p>
                            <p v-if="item.category_label" class="text-xs text-slate-500">{{ item.category_label }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Found {{ fmtDate(item.date_lost_found) }}<template v-if="item.location"> · {{ item.location }}</template>
                            </p>
                            <p class="text-xs text-indigo-600 font-medium mt-1.5">Yours? Claim it at the GSU office.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- My reports -->
            <div v-else class="space-y-3">
                <div v-if="my_reports.length === 0"
                    class="bg-white rounded-xl border border-slate-200 p-10 text-center">
                    <ArchiveBoxIcon class="w-10 h-10 text-slate-300 mx-auto mb-2" />
                    <p class="text-sm font-medium text-slate-600">You haven't reported anything yet</p>
                </div>
                <div v-for="r in my_reports" :key="r.id"
                    class="bg-white rounded-xl border border-slate-200 px-4 py-3 flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-800">{{ r.item_name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ r.type === 'found' ? 'Found' : 'Lost' }} · {{ fmtDate(r.date_lost_found) }}
                            <template v-if="r.location"> · {{ r.location }}</template>
                        </p>
                    </div>
                    <span :class="['text-xs font-medium px-2.5 py-1 rounded-full border shrink-0', statusClass(r.status)]">
                        {{ r.custody_label }}
                    </span>
                </div>
            </div>

        </div>

        <!-- Report modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showModal = false" />
                <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-xl z-10 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                        <h2 class="text-base font-semibold text-slate-800">Report an Item</h2>
                        <button @click="showModal = false" class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="px-5 py-4 space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1.5">What happened?</label>
                            <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm">
                                <button type="button" @click="form.type = 'lost'"
                                    :class="['px-4 py-2 font-medium', form.type === 'lost' ? 'bg-amber-500 text-white' : 'bg-white text-slate-600']">
                                    I lost an item
                                </button>
                                <button type="button" @click="form.type = 'found'"
                                    :class="['px-4 py-2 font-medium border-l border-slate-200', form.type === 'found' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600']">
                                    I found an item
                                </button>
                            </div>
                            <p v-if="form.type === 'found'" class="text-xs text-emerald-700 mt-1.5">
                                Bring the item to the GSU office — you'll earn 10 honesty points once GSU confirms the turnover.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Item Name *</label>
                            <input v-model="form.item_name" type="text" placeholder="e.g. Blue water bottle, scientific calculator"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Category</label>
                                <select v-model="form.category"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                                    <option value="">Select...</option>
                                    <option v-for="(label, value) in categories" :key="value" :value="value">{{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    {{ form.type === 'found' ? 'Date Found' : 'Date Lost' }} *
                                </label>
                                <input v-model="form.date_lost_found" type="date"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">
                                {{ form.type === 'found' ? 'Where did you find it?' : 'Where did you last see it?' }}
                            </label>
                            <input v-model="form.location" type="text" placeholder="e.g. Canteen, Room 204, Gym"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                            <textarea v-model="form.description" rows="2" placeholder="Color, brand, distinguishing marks..."
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Photo (optional)</label>
                            <input type="file" accept="image/*" @change="onPhotoSelected"
                                class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700" />
                            <img v-if="form.photo_base64" :src="form.photo_base64" class="mt-2 h-24 rounded-lg object-cover" alt="Preview" />
                        </div>
                    </div>

                    <div class="px-5 py-4 border-t border-slate-100 flex justify-end gap-2">
                        <button @click="showModal = false"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100">Cancel</button>
                        <button @click="submit" :disabled="submitting || !form.item_name || !form.date_lost_found"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50">
                            {{ submitting ? 'Submitting…' : 'Submit Report' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </StudentPortalLayout>
</template>
