<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import AppSidebar from './components/AppSidebar.vue'
import { useManagerApprovalStore } from './stores/managerApproval'

const route = useRoute()
const approval = useManagerApprovalStore()
const SIDEBAR_STATE_KEY = 'pos_sidebar_visible'
const isSidebarVisible = ref(true)

function toggleSidebar() {
  isSidebarVisible.value = !isSidebarVisible.value
  localStorage.setItem(SIDEBAR_STATE_KEY, isSidebarVisible.value ? '1' : '0')
}

onMounted(() => {
  approval.startTicker()

  const saved = localStorage.getItem(SIDEBAR_STATE_KEY)
  if (saved === '0') {
    isSidebarVisible.value = false
  }
})

onUnmounted(() => {
  approval.stopTicker()
})
</script>

<template>
  <RouterView v-if="route.name === 'login' || route.name === 'landing'" />

  <div
    v-else
    class="relative flex h-screen overflow-hidden bg-gradient-to-b from-white to-brand-50 text-ink dark:from-app dark:to-app"
  >
    <div
      role="button"
      tabindex="0"
      aria-label="Toggle Sidebar"
      class="absolute top-1/2 z-20 flex h-20 w-6 -translate-y-1/2 cursor-pointer items-center justify-center rounded-r-xl border border-l-0 border-slate-300 bg-white/90 text-slate-500 shadow-sm backdrop-blur transition-all hover:bg-white hover:text-slate-700"
      :class="isSidebarVisible ? 'left-64' : 'left-0'"
      @click="toggleSidebar"
      @keydown.enter.prevent="toggleSidebar"
      @keydown.space.prevent="toggleSidebar"
    >
      <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          :d="isSidebarVisible ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7'"
        />
      </svg>
    </div>

    <div
      class="relative shrink-0 overflow-hidden transition-all duration-300"
      :class="isSidebarVisible ? 'w-64 opacity-100' : 'w-0 opacity-0'"
    >
      <AppSidebar />
    </div>

    <div class="relative flex min-w-0 flex-1 flex-col">
      <main class="flex-1 overflow-auto p-4 lg:p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style>
/* Inter substitutes for the source system's proprietary SoDoSans (DESIGN.md, section 3).
   JetBrains Mono substitutes for CoinbaseMono, used only for money/invoice figures. */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap');

body {
  font-family: 'Inter', 'Segoe UI', Tahoma, sans-serif;
  letter-spacing: -0.01em;
}
</style>