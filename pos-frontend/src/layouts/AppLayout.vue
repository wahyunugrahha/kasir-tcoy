<script setup>
import { RouterLink, RouterView, useRoute } from 'vue-router'

const route = useRoute()

const navItems = [
  { to: '/', label: 'Kasir', icon: '🛒' },
  { to: '/orders', label: 'Order List', icon: '📋' },
  { to: '/bills', label: 'Tagihan', icon: '🧾' },
  { to: '/history', label: 'Riwayat', icon: '📜' },
  { to: '/products', label: 'Produk', icon: '📦' },
  { to: '/settlement', label: 'Settlement', icon: '💰' },
  { to: '/reports', label: 'Laporan', icon: '📊' },
  { to: '/settings', label: 'Pengaturan', icon: '⚙️' },
]
</script>

<template>
  <div class="flex min-h-screen bg-slate-100 text-slate-900">
    <!-- Sidebar -->
    <aside class="flex w-56 shrink-0 flex-col bg-slate-800 text-white shadow-xl">
      <!-- Logo area -->
      <div class="border-b border-slate-700 px-5 py-5">
        <span class="text-xl font-bold tracking-tight text-white">⚡ POS PRO</span>
        <p class="mt-0.5 text-xs text-slate-400">Point of Sale</p>
      </div>

      <!-- Nav links -->
      <nav class="flex-1 overflow-y-auto px-3 py-4">
        <ul class="space-y-1">
          <li v-for="item in navItems" :key="item.to">
            <RouterLink
              :to="item.to"
              class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 transition-colors hover:bg-slate-700 hover:text-white"
              active-class="bg-indigo-600 text-white hover:bg-indigo-500"
              :exact="item.to === '/'"
            >
              <span class="text-base leading-none">{{ item.icon }}</span>
              {{ item.label }}
            </RouterLink>
          </li>
        </ul>
      </nav>

      <!-- Footer -->
      <div class="border-t border-slate-700 px-5 py-3 text-xs text-slate-500">
        v2.0 — POS Pro
      </div>
    </aside>

    <!-- Main content -->
    <div class="flex min-w-0 flex-1 flex-col">
      <!-- Page header bar -->
      <header class="flex items-center border-b border-slate-200 bg-white px-6 py-3 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-700">{{ route.meta.title ?? 'POS Pro' }}</h2>
      </header>

      <!-- Router outlet -->
      <main class="flex-1 overflow-auto p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
