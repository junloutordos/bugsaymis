<script setup>
import { storageUrl } from '@/Composables/useStorage.js'
import NotificationBell from '@/Components/NotificationBell.vue'
import {
  Bars3Icon,
  BookOpenIcon,
  BugAntIcon,
  ChatBubbleLeftRightIcon,
  QuestionMarkCircleIcon,
  ShieldCheckIcon,
} from '@heroicons/vue/24/outline'

defineProps({
  title:           { type: String, default: '' },
  user:            { type: Object, required: true },
  roleName:        { type: String, default: 'Guest' },
  chatUnreadCount: { type: Number, default: 0 },
})

const emit = defineEmits(['toggle-mobile', 'toggle-collapse', 'report-error', 'open-profile'])
</script>

<template>
  <header class="sticky top-0 z-20 h-14 border-b border-slate-200/70 bg-white/80 px-4 backdrop-blur md:px-6">
    <div class="flex h-full items-center justify-between gap-3">
      <div class="flex min-w-0 items-center gap-3">
        <button
          type="button"
          class="rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 md:hidden"
          aria-label="Open sidebar"
          @click="emit('toggle-mobile')"
        >
          <Bars3Icon class="h-5 w-5" />
        </button>
        <button
          type="button"
          class="hidden rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 md:block"
          aria-label="Toggle sidebar"
          @click="emit('toggle-collapse')"
        >
          <Bars3Icon class="h-5 w-5" />
        </button>
        <span v-if="title" class="hidden truncate font-heading text-sm font-semibold text-slate-800 md:block">{{ title }}</span>
      </div>

      <div class="flex min-w-0 items-center gap-0.5 sm:gap-1">
        <a
          :href="route('privacy.index')"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-indigo-50 hover:text-indigo-600 lg:h-auto lg:w-auto lg:gap-1.5 lg:px-3 lg:py-1.5 lg:text-xs lg:font-medium"
          aria-label="Data Privacy Policy"
          title="Data Privacy Policy"
        >
          <ShieldCheckIcon class="h-4 w-4" />
          <span class="hidden lg:inline">Privacy Policy</span>
        </a>

        <a
          href="https://drive.google.com/drive/folders/16XAkvSwQCPquxuMtgFmEOWEMAeUfOqwL"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-indigo-50 hover:text-indigo-600 xl:h-auto xl:w-auto xl:gap-1.5 xl:px-3 xl:py-1.5 xl:text-xs xl:font-medium"
          aria-label="QMS Manuals"
          title="QMS Manuals"
        >
          <BookOpenIcon class="h-4 w-4" />
          <span class="hidden xl:inline">QMS Manuals</span>
        </a>

        <a
          :href="route('docs.index')"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-indigo-50 hover:text-indigo-600 lg:h-auto lg:w-auto lg:gap-1.5 lg:px-3 lg:py-1.5 lg:text-xs lg:font-medium"
          aria-label="Help Documentation"
          title="Help Documentation"
        >
          <QuestionMarkCircleIcon class="h-4 w-4" />
          <span class="hidden lg:inline">Need Help?</span>
        </a>

        <button
          type="button"
          class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-indigo-50 hover:text-indigo-600 sm:h-auto sm:w-auto sm:gap-1.5 sm:px-3 sm:py-1.5 sm:text-xs sm:font-medium"
          aria-label="Report an Error"
          title="Report an Error"
          @click="emit('report-error')"
        >
          <BugAntIcon class="h-4 w-4" />
          <span class="hidden sm:inline">Report Error</span>
        </button>

        <span class="mx-1 hidden h-5 w-px bg-slate-200 sm:block" aria-hidden="true"></span>

        <NotificationBell v-if="user?.id" :user-id="user.id" />

        <a :href="route('chat.index')" class="relative rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-indigo-50 hover:text-indigo-600" aria-label="Messenger">
          <ChatBubbleLeftRightIcon class="h-5 w-5" />
          <span
            v-if="chatUnreadCount > 0"
            class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white"
          >
            {{ chatUnreadCount > 99 ? '99+' : chatUnreadCount }}
          </span>
        </a>

        <button
          type="button"
          class="group flex min-w-0 items-center gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-slate-50"
          @click="emit('open-profile')"
        >
          <img
            :src="storageUrl(user.profile_picture) ?? 'https://i.pravatar.cc/40'"
            alt="User Avatar"
            class="h-8 w-8 shrink-0 rounded-full object-cover ring-2 ring-slate-200 transition group-hover:ring-indigo-300"
          />
          <div class="hidden min-w-0 text-left md:block">
            <p class="truncate text-sm font-medium leading-none text-slate-800">{{ user.name }}</p>
            <p class="mt-0.5 truncate text-[11px] leading-none text-slate-500">{{ roleName }}</p>
          </div>
        </button>
      </div>
    </div>
  </header>
</template>
