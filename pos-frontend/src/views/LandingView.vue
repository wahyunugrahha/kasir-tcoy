<script setup>
import { ref, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useTheme } from '../composables/useTheme'
import { toggleLocale } from '../i18n'
import cashierScreenshot from '../assets/images/halaman-kasir.png'

const { isDark, toggleTheme } = useTheme()
const { t, locale } = useI18n()
const mobileMenuOpen = ref(false)

// Reuses the same icon vocabulary as AppSidebar's real nav, so the preview
// below matches the icons a logged-in user actually sees (R-04: relevance).
const workflow = computed(() => [
  {
    label: t('landing.flowStage1Title'),
    desc: t('landing.flowStage1Desc'),
    icon: 'M3 7h18M6 7v13h12V7M9 11h6M9 15h4M8 4h8',
  },
  {
    label: t('landing.flowStage2Title'),
    desc: t('landing.flowStage2Desc'),
    icon: 'M4 7l8-4 8 4-8 4-8-4zm0 0v10l8 4 8-4V7',
  },
  {
    label: t('landing.flowStage3Title'),
    desc: t('landing.flowStage3Desc'),
    icon: 'M4 7h16v10H4zM8 11h8M12 7v10M7 17h10',
  },
  {
    label: t('landing.flowStage4Title'),
    desc: t('landing.flowStage4Desc'),
    icon: 'M5 20V10m7 10V4m7 16v-7M3 20h18',
  },
])

const roles = computed(() => [
  {
    name: t('landing.roleAdminName'),
    tagline: t('landing.roleAdminTagline'),
    items: [
      t('landing.roleAdminItem1'),
      t('landing.roleAdminItem2'),
      t('landing.roleAdminItem3'),
      t('landing.roleAdminItem4'),
      t('landing.roleAdminItem5'),
    ],
  },
  {
    name: t('landing.roleCashierName'),
    tagline: t('landing.roleCashierTagline'),
    items: [
      t('landing.roleCashierItem1'),
      t('landing.roleCashierItem2'),
      t('landing.roleCashierItem3'),
      t('landing.roleCashierItem4'),
    ],
  },
])

const features = computed(() => [
  {
    title: t('landing.feature1Title'),
    desc: t('landing.feature1Desc'),
    icon: 'M13 10V3L4 14h7v7l9-11h-7z',
  },
  {
    title: t('landing.feature2Title'),
    desc: t('landing.feature2Desc'),
    icon: 'M20.59 13.41L11 3.83A2 2 0 009.59 3.24L4 3a1 1 0 00-1 1l.24 5.59a2 2 0 00.59 1.41l9.58 9.58a2 2 0 002.83 0l4.35-4.35a2 2 0 000-2.82zM7 8a1 1 0 110-2 1 1 0 010 2z',
  },
  {
    title: t('landing.feature3Title'),
    desc: t('landing.feature3Desc'),
    icon: 'M9 11V8a3 3 0 016 0v3m-7 0h8a1 1 0 011 1v6a1 1 0 01-1 1H8a1 1 0 01-1-1v-6a1 1 0 011-1z',
  },
  {
    title: t('landing.feature4Title'),
    desc: t('landing.feature4Desc'),
    icon: 'M7 3h10l2 2v14l-2 2H7l-2-2V5l2-2zm3 5h4m-4 4h6m-6 4h5',
  },
  {
    title: t('landing.feature5Title'),
    desc: t('landing.feature5Desc'),
    icon: 'M5 20V10m7 10V4m7 16v-7M3 20h18',
  },
  {
    title: t('landing.feature6Title'),
    desc: t('landing.feature6Desc'),
    icon: 'M12 3v12m0 0l-4-4m4 4l4-4M5 19h14',
  },
])

</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-white to-brand-50 text-ink dark:from-app dark:to-app">
    <header class="sticky top-0 z-30 border-b border-line bg-surface/80 backdrop-blur">
      <div class="mx-auto grid w-full max-w-7xl grid-cols-[auto_1fr_auto] items-center gap-4 px-6 py-4 lg:px-8">
        <RouterLink to="/">
          <h1 class="text-lg font-bold tracking-tight text-ink">KasirTcuy</h1>
        </RouterLink>

        <nav class="hidden items-center justify-center gap-8 text-sm font-medium text-ink-soft md:flex">
          <a href="#alur" class="hover:text-ink">{{ t('landing.navFlow') }}</a>
          <a href="#peran" class="hover:text-ink">{{ t('landing.navRoles') }}</a>
          <a href="#fitur" class="hover:text-ink">{{ t('landing.navFeatures') }}</a>
        </nav>

        <div class="flex items-center justify-end gap-2">
          <button
            type="button"
            :aria-label="locale === 'id' ? t('common.toggleLanguage') : t('common.toggleLanguageBack')"
            :title="locale === 'id' ? 'EN' : 'ID'"
            class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-line text-xs font-semibold text-ink-soft transition hover:bg-surface-2 hover:text-ink"
            @click="toggleLocale"
          >
            {{ locale === 'id' ? 'EN' : 'ID' }}
          </button>

          <button
            type="button"
            :aria-label="isDark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap'"
            :title="isDark ? 'Mode Terang' : 'Mode Gelap'"
            class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-line text-ink-soft transition hover:bg-surface-2 hover:text-ink"
            @click="toggleTheme"
          >
            <svg v-if="isDark" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.36 6.36l-.7-.7M6.34 6.34l-.7-.7m12.72 0l-.7.7M6.34 17.66l-.7.7M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
          </button>

          <RouterLink
            to="/login"
            class="hidden rounded-full bg-brand-600 active:scale-95 px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-500 sm:inline-block"
          >
            {{ t('landing.navLoginShort') }}
          </RouterLink>

          <button
            type="button"
            class="grid h-9 w-9 place-items-center rounded-lg border border-line text-ink md:hidden"
            :aria-expanded="mobileMenuOpen"
            aria-label="Buka menu navigasi"
            @click="mobileMenuOpen = !mobileMenuOpen"
          >
            <svg v-if="!mobileMenuOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <div v-if="mobileMenuOpen" class="border-t border-line px-6 py-4 md:hidden">
        <nav class="flex flex-col gap-3 text-sm font-medium text-ink-soft">
          <a href="#alur" class="hover:text-ink" @click="mobileMenuOpen = false">{{ t('landing.navFlow') }}</a>
          <a href="#peran" class="hover:text-ink" @click="mobileMenuOpen = false">{{ t('landing.navRoles') }}</a>
          <a href="#fitur" class="hover:text-ink" @click="mobileMenuOpen = false">{{ t('landing.navFeatures') }}</a>
          <RouterLink to="/login" class="rounded-full bg-brand-600 px-5 py-2.5 text-center font-semibold text-white hover:bg-brand-500">
            {{ t('landing.navLoginShort') }}
          </RouterLink>
        </nav>
      </div>
    </header>

    <main>
      <!-- Hero: confident SaaS display weight (modern-minimal voice), single blue accent from the app's own palette. Symmetric two-column split. -->
      <section class="mx-auto grid w-full max-w-7xl items-center gap-12 px-6 pb-20 pt-16 lg:grid-cols-2 lg:px-8 lg:pt-24">
        <div>
          <h2 class="hero-in text-5xl font-bold leading-[1.05] tracking-tight text-ink md:text-6xl">
            {{ t('landing.heroTitle') }}
          </h2>
          <p class="hero-in hero-in-delay-1 mt-3 text-3xl font-medium tracking-wide text-ink-soft md:text-4xl">
            {{ t('landing.heroTagline') }}
          </p>
          <p class="hero-in hero-in-delay-1 mt-6 max-w-xl text-base text-ink-soft md:text-lg">
            {{ t('landing.heroSubtitle') }}
          </p>

          <div class="hero-in hero-in-delay-2 mt-8 flex flex-wrap items-center gap-4">
            <RouterLink
              to="/login"
              class="inline-block rounded-full bg-brand-600 active:scale-95 px-6 py-3 text-sm font-semibold text-white transition hover:bg-brand-500"
            >
              {{ t('landing.ctaLogin') }}
            </RouterLink>
            <a href="#alur" class="text-sm font-semibold text-ink-soft hover:text-ink">
              {{ t('landing.ctaSeeFlow') }}
            </a>
          </div>
        </div>

        <!-- Tangkapan layar asli halaman Kasir (bukan rekreasi) — real screenshot per antislop/Hallmark, hairline border only, no re-drawn chrome. -->
        <figure class="hero-in hero-in-delay-1">
          <img
            :src="cashierScreenshot"
            :alt="t('landing.previewAlt')"
            class="w-full rounded-2xl border border-line shadow-sm"
            width="1920"
            height="1080"
          />
          <figcaption class="mt-3 text-center text-xs text-ink-faint">{{ t('landing.previewCaption') }}</figcaption>
        </figure>
      </section>

      <!-- Alur Kerja: 4 tahap nyata (bukan template 3-langkah), disusun sebagai daftar bertaut, bukan grid kartu seragam. -->
      <section id="alur" class="border-t border-line bg-surface-2/60 py-20">
        <div class="mx-auto w-full max-w-7xl px-6 lg:px-8">
          <p class="text-xs font-medium uppercase tracking-[0.2em] text-brand-600">{{ t('landing.flowKicker') }}</p>
          <h3 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ t('landing.flowTitle') }}</h3>

          <div class="mt-10 divide-y divide-line border-t border-line">
            <div
              v-for="(stage, index) in workflow"
              :key="stage.label"
              class="grid gap-3 py-8 sm:grid-cols-[96px_1fr] sm:gap-8"
            >
              <p class="text-4xl font-bold tabular-nums tracking-tight text-ink-faint sm:text-5xl">
                {{ String(index + 1).padStart(2, '0') }}
              </p>
              <div>
                <div class="flex items-center gap-2.5">
                  <svg class="h-4 w-4 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="stage.icon" />
                  </svg>
                  <p class="text-lg font-medium text-ink">{{ stage.label }}</p>
                </div>
                <p class="mt-2 max-w-2xl text-sm text-ink-soft">{{ stage.desc }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Peran Pengguna: dua kolom asimetris karena kapasitasnya memang berbeda (bukan kartu seragam dipaksa sama). -->
      <section id="peran" class="border-t border-line py-20">
        <div class="mx-auto w-full max-w-7xl px-6 lg:px-8">
          <p class="text-xs font-medium uppercase tracking-[0.2em] text-brand-600">{{ t('landing.rolesKicker') }}</p>
          <h3 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ t('landing.rolesTitle') }}</h3>
          <p class="mt-3 max-w-2xl text-sm text-ink-soft">
            {{ t('landing.rolesDesc') }}
          </p>

          <div class="mt-10 grid gap-6 lg:grid-cols-2">
            <div
              v-for="role in roles"
              :key="role.name"
              class="rounded-3xl border border-line bg-surface p-6"
            >
              <p class="text-xl font-medium text-ink">{{ role.name }}</p>
              <p class="mt-1 text-sm text-ink-faint">{{ role.tagline }}</p>
              <ul class="mt-5 space-y-3">
                <li v-for="item in role.items" :key="item" class="flex items-start gap-2.5 text-sm text-ink-soft">
                  <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                  {{ item }}
                </li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      <!-- Fitur: grid simetris, semua kartu ukuran dan struktur sama. -->
      <section id="fitur" class="border-t border-line bg-surface-2/60 py-20">
        <div class="mx-auto w-full max-w-7xl px-6 lg:px-8">
          <p class="text-xs font-medium uppercase tracking-[0.2em] text-brand-600">{{ t('landing.featuresKicker') }}</p>
          <h3 class="mt-2 text-3xl font-bold tracking-tight text-ink">{{ t('landing.featuresTitle') }}</h3>

          <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="feature in features" :key="feature.title" class="rounded-3xl border border-line bg-surface p-7">
              <span class="grid h-11 w-11 place-items-center rounded-full border border-line bg-surface-2 text-brand-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="feature.icon" />
                </svg>
              </span>
              <p class="mt-4 font-medium text-ink">{{ feature.title }}</p>
              <p class="mt-2 text-sm text-ink-soft">{{ feature.desc }}</p>
            </div>
          </div>
        </div>
      </section>
    </main>

    <!-- Ft2 inline single line: satu baris, hairline di atas, tanpa kolom footer palsu. -->
    <footer class="border-t border-line py-8">
      <div class="mx-auto w-full max-w-7xl px-6 text-center text-sm text-ink-faint lg:px-8">
        <p>{{ t('landing.footerCredit') }} <span class="font-medium text-ink">@wahyunugrahha</span></p>
      </div>
    </footer>
  </div>
</template>

<style scoped>
/* Calm, one-time hero entrance (MOTION dial: subtle, no loop, no bounce). */
@keyframes hero-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.hero-in {
  animation: hero-in 550ms ease-out both;
}

.hero-in-delay-1 {
  animation-delay: 90ms;
}

.hero-in-delay-2 {
  animation-delay: 160ms;
}

@media (prefers-reduced-motion: reduce) {
  .hero-in {
    animation: none;
  }
}
</style>
