<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import { useTheme } from '../composables/useTheme'
import { toggleLocale } from '../i18n'

const auth = useAuthStore()
const router = useRouter()
const { isDark, toggleTheme } = useTheme()
const { t, locale } = useI18n()

const form = ref({
  email: '',
  password: '',
  device_name: 'pos-web',
})

const errorMessage = ref('')

async function submitLogin() {
  errorMessage.value = ''
  const result = await auth.login(form.value)

  if (!result.ok) {
    errorMessage.value = result.message
    return
  }

  router.push(auth.user?.role === 'admin' ? '/dashboard' : '/pos')
}
</script>

<template>
  <div class="relative flex min-h-screen items-center justify-center bg-gradient-to-b from-white to-brand-50 px-4 dark:from-app dark:to-app">
    <RouterLink
      to="/"
      class="absolute left-4 top-4 text-sm font-medium text-ink-soft hover:text-ink sm:left-6 sm:top-6"
    >
      &larr; {{ t('login.backHome') }}
    </RouterLink>

    <div class="absolute right-4 top-4 flex items-center gap-2 sm:right-6 sm:top-6">
      <button
        type="button"
        :aria-label="locale === 'id' ? t('common.toggleLanguage') : t('common.toggleLanguageBack')"
        :title="locale === 'id' ? 'EN' : 'ID'"
        class="grid h-9 w-9 place-items-center rounded-full border border-line bg-surface text-xs font-semibold text-ink transition hover:bg-surface-2"
        @click="toggleLocale"
      >
        {{ locale === 'id' ? 'EN' : 'ID' }}
      </button>

      <button
        type="button"
        :aria-label="isDark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap'"
        :title="isDark ? 'Mode Terang' : 'Mode Gelap'"
        class="grid h-9 w-9 place-items-center rounded-full border border-line bg-surface text-ink transition hover:bg-surface-2"
        @click="toggleTheme"
      >
        <svg v-if="isDark" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.36 6.36l-.7-.7M6.34 6.34l-.7-.7m12.72 0l-.7.7M6.34 17.66l-.7.7M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>
      </button>
    </div>

    <section class="w-full max-w-md rounded-3xl border border-line bg-surface p-8 shadow-sm">
      <p class="text-xs font-medium uppercase tracking-[0.2em] text-brand-600">{{ t('login.kicker') }}</p>
      <h1 class="mt-2 text-2xl font-bold tracking-tight text-ink">{{ t('login.title') }}</h1>
      <p class="mt-2 text-sm text-ink-soft">{{ t('login.subtitle') }}</p>

      <form class="mt-6 space-y-4" @submit.prevent="submitLogin">
        <label class="block">
          <span class="mb-1.5 block text-sm font-medium text-ink">{{ t('login.email') }}</span>
          <input
            v-model="form.email"
            type="email"
            required
            class="w-full rounded-xl border border-line bg-surface px-4 py-3 text-ink outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
          />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-sm font-medium text-ink">{{ t('login.password') }}</span>
          <input
            v-model="form.password"
            type="password"
            required
            class="w-full rounded-xl border border-line bg-surface px-4 py-3 text-ink outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
          />
        </label>

        <p v-if="errorMessage" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
          {{ errorMessage }}
        </p>

        <button
          type="submit"
          :disabled="auth.loading"
          class="w-full rounded-full bg-brand-600 active:scale-95 px-4 py-3 text-sm font-semibold text-white transition hover:bg-brand-500 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {{ auth.loading ? t('login.submitting') : t('login.submit') }}
        </button>
      </form>
    </section>
  </div>
</template>
