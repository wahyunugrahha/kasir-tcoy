<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import { useTheme } from '../composables/useTheme'
import { toggleLocale } from '../i18n'

const router = useRouter()
const auth = useAuthStore()
const { isDark, toggleTheme } = useTheme()
const { t, locale } = useI18n()

const navItems = computed(() => [
  { to: '/dashboard', label: t('nav.dashboard'), icon: 'M4 4h7v7H4V4zm9 0h7v4h-7V4zm0 7h7v9h-7v-9zM4 14h7v6H4v-6z' },
  { to: '/pos', label: t('nav.pos'), icon: 'M3 7h18M6 7v13h12V7M9 11h6M9 15h4M8 4h8' },
  { to: '/products', label: t('nav.products'), icon: 'M4 7l8-4 8 4-8 4-8-4zm0 0v10l8 4 8-4V7' },
  { to: '/history', label: t('nav.history'), icon: 'M12 8v5l3 2M3.1 11A9 9 0 1112 21h-1m-7-4v4h4' },
  { to: '/manager-approval', label: t('nav.managerApproval'), icon: 'M9 11V8a3 3 0 016 0v3m-7 0h8a1 1 0 011 1v6a1 1 0 01-1 1H8a1 1 0 01-1-1v-6a1 1 0 011-1z' },
  { to: '/reports', label: t('nav.reports'), icon: 'M5 20V10m7 10V4m7 16v-7M3 20h18' },
  { to: '/promo-codes', label: t('nav.promoCodes'), icon: 'M20.59 13.41L11 3.83A2 2 0 009.59 3.24L4 3a1 1 0 00-1 1l.24 5.59a2 2 0 00.59 1.41l9.58 9.58a2 2 0 002.83 0l4.35-4.35a2 2 0 000-2.82zM7 8a1 1 0 110-2 1 1 0 010 2z' },
  { to: '/settings', label: t('nav.settings'), icon: 'M12 8.5A3.5 3.5 0 1112 15.5 3.5 3.5 0 0112 8.5zm0-6.5v2m0 16v2m8.66-15l-1.73 1m-13.86 0L3.34 7m17.32 10l-1.73-1m-13.86 0L3.34 17M2 12h2m16 0h2' },
  { to: '/orders', label: t('nav.orders'), icon: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01' },
  { to: '/bills', label: t('nav.bills'), icon: 'M7 3h10l2 2v14l-2 2H7l-2-2V5l2-2zm3 5h4m-4 4h6m-6 4h5' },
  { to: '/settlement', label: t('nav.settlement'), icon: 'M4 7h16v10H4zM8 11h8M12 7v10M7 17h10' },
])

const visibleNavItems = computed(() => navItems.value.filter((item) => {
  if (item.to === '/manager-approval' && auth.user?.role !== 'cashier') {
    return false
  }

  if (['/dashboard', '/products', '/reports', '/settings', '/promo-codes'].includes(item.to) && auth.user?.role !== 'admin') {
    return false
  }

  return true
}))

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <!-- Theme-reactive shell — matches DESIGN.md's top-nav-light / top-nav-on-dark pair
       (Coinbase itself switches nav surface with the page, it isn't fixed). -->
  <aside class="flex h-full w-64 shrink-0 flex-col border-r border-line bg-surface text-ink">
    <div class="border-b border-line px-5 py-5">
      <div class="flex items-start justify-between gap-2">
        <div>
          <span class="text-2xl font-black tracking-tight text-ink">KasirTcuy</span>
          <p class="mt-0.5 text-xs text-ink-faint">POS System App</p>
        </div>
        <div class="flex shrink-0 items-center gap-1.5">
          <button
            type="button"
            :aria-label="locale === 'id' ? $t('common.toggleLanguage') : $t('common.toggleLanguageBack')"
            :title="locale === 'id' ? 'EN' : 'ID'"
            class="grid h-8 w-8 place-items-center rounded-full border border-line bg-surface-2 text-xs font-semibold text-ink transition hover:bg-line-soft"
            @click="toggleLocale"
          >
            {{ locale === 'id' ? 'EN' : 'ID' }}
          </button>
          <button
            type="button"
            :aria-label="isDark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap'"
            :title="isDark ? 'Mode Terang' : 'Mode Gelap'"
            class="grid h-8 w-8 place-items-center rounded-full border border-line bg-surface-2 text-ink transition hover:bg-line-soft"
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
      </div>

      <div class="mt-4 rounded-xl border border-line bg-surface-2 px-3 py-2.5">
        <p class="text-xs uppercase tracking-wide text-ink-faint">{{ t('nav.loggedInAs') }}</p>
        <p class="mt-0.5 truncate text-sm font-semibold text-ink">{{ auth.user?.name ?? 'Guest' }}</p>
        <p class="text-xs text-ink-faint">{{ auth.user?.role ?? '-' }}</p>
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4">
      <ul class="space-y-1">
        <li v-for="item in visibleNavItems" :key="item.to">
          <RouterLink :to="item.to" custom v-slot="{ isActive, navigate, href }">
            <a
              :href="href"
              class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all"
              :class="isActive ? 'bg-brand-600 text-white' : 'text-ink-soft hover:bg-surface-2 hover:text-ink'"
              @click="navigate"
            >
              <span
                class="grid h-6 w-6 place-items-center rounded-md text-current"
                :class="isActive ? 'bg-white/15' : 'border border-line bg-surface-2'"
              >
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                </svg>
              </span>
              {{ item.label }}
            </a>
          </RouterLink>
        </li>
      </ul>
    </nav>

    <div class="border-t border-line px-5 py-4">
      <button
        class="mb-2 w-full rounded-full border border-line bg-surface-2 px-3 py-2 text-sm font-medium text-ink transition hover:bg-line-soft"
        @click="logout"
      >
        {{ t('nav.logout') }}
      </button>
      <p class="text-xs text-ink-faint">v2.0 By Ween</p>
    </div>
  </aside>
</template>
