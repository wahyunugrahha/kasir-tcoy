<script setup>
import { RouterView, useRoute } from 'vue-router'
import AppSidebar from './components/AppSidebar.vue'

const route = useRoute()
</script>

<template>
  <RouterView v-if="route.name === 'login' || route.name === 'landing'" />

  <div v-else class="flex min-h-screen bg-gradient-to-br from-slate-100 via-slate-100 to-indigo-50 text-slate-900">
    <AppSidebar />

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="flex items-center border-b border-slate-200 bg-white px-6 py-3 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-700">{{ route.meta.title ?? 'POS' }}</h2>
      </header>

      <main class="flex-1 overflow-auto p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>