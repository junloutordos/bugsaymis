<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3'
import { UserCircleIcon, FingerPrintIcon, ArrowRightIcon, XMarkIcon, ArrowLeftStartOnRectangleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ show: Boolean })
const emit  = defineEmits(['close'])

const page = usePage()
// Read directly — no deep computed over reactive props to avoid tracking loops
const user = page.props.auth?.user

function initials(n) {
  return (n ?? '').split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2)
}

function go(url) {
  emit('close')
  router.visit(url)
}

const logoutForm = useForm({})
function logout() {
  emit('close')
  logoutForm.post(route('logout'))
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div v-if="show" class="fixed inset-0 z-50 flex items-start justify-end pt-14 pr-3">
        <div class="fixed inset-0 bg-black/20" @click="emit('close')" />

        <div class="relative bg-white rounded-2xl shadow-2xl border border-slate-100 w-72 overflow-hidden z-10">

          <!-- Header gradient — matches dashboard hero banner -->
          <div style="background: linear-gradient(135deg, #060e50 0%, #1447c0 65%, #0093b8 100%);" class="px-5 py-5">
            <div class="flex items-center gap-3">
              <div class="w-14 h-14 rounded-full overflow-hidden ring-2 ring-white/40 bg-white/20 flex items-center justify-center shrink-0">
                <img v-if="user?.profile_picture" :src="user.profile_picture" alt="Profile"
                  class="w-full h-full object-cover" />
                <span v-else class="text-lg font-bold text-white select-none">
                  {{ initials(user?.name) }}
                </span>
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-semibold text-white text-sm truncate">{{ user?.name }}</p>
                <p class="text-indigo-200 text-xs truncate">{{ user?.email }}</p>
                <p v-if="user?.position" class="text-indigo-300 text-[11px] truncate mt-0.5">{{ user.position }}</p>
              </div>
              <button @click="emit('close')" class="text-white/60 hover:text-white shrink-0">
                <XMarkIcon class="h-4 w-4" />
              </button>
            </div>
            <div class="flex flex-wrap gap-1 mt-3">
              <span v-for="role in (user?.roles ?? [])" :key="role.id ?? role"
                class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-white/20 text-white">
                {{ role.name ?? role }}
              </span>
            </div>
          </div>

          <!-- Links -->
          <div class="p-3 space-y-1">
            <button @click="go(route('profile.edit'))"
              class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 transition-colors text-left group">
              <UserCircleIcon class="h-4 w-4 text-slate-400 group-hover:text-indigo-500 shrink-0" />
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-700">Edit Profile</p>
                <p class="text-xs text-slate-400">Photo, name, specialization</p>
              </div>
              <ArrowRightIcon class="h-3.5 w-3.5 text-slate-300 group-hover:text-indigo-400" />
            </button>

            <button @click="go(route('profile.signature'))"
              class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 transition-colors text-left group">
              <FingerPrintIcon class="h-4 w-4 text-slate-400 group-hover:text-indigo-500 shrink-0" />
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-700">Digital Signature</p>
                <p class="text-xs text-slate-400">Manage signature & PIN</p>
              </div>
              <ArrowRightIcon class="h-3.5 w-3.5 text-slate-300 group-hover:text-indigo-400" />
            </button>

            <div class="border-t border-slate-100 my-1" />

            <button @click="logout"
              class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-red-50 transition-colors text-left group">
              <ArrowLeftStartOnRectangleIcon class="h-4 w-4 text-slate-400 group-hover:text-red-500 shrink-0" />
              <p class="text-sm font-medium text-slate-600 group-hover:text-red-600">Logout</p>
            </button>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>
