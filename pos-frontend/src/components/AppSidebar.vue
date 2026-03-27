<script setup>
import { RouterLink } from 'vue-router'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const navItems = [
  { to: '/pos', label: 'Kasir', icon: '🛒' },
  { to: '/products', label: 'Produk', icon: '📦' },
  { to: '/history', label: 'Riwayat', icon: '📜' },
  { to: '/reports', label: 'Laporan', icon: '📊' },
  { to: '/settings', label: 'Pengaturan', icon: '⚙️' },
  { to: '/orders', label: 'Order List', icon: '📋' },
  { to: '/bills', label: 'Tagihan', icon: '🧾' },
  { to: '/settlement', label: 'Settlement', icon: '💰' },
]

const visibleNavItems = navItems.filter((item) => {
  if (['/reports', '/settings'].includes(item.to) && auth.user?.role !== 'admin') {
    return false
  }

  return true
})

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <aside class="flex w-64 shrink-0 flex-col bg-gradient-to-b from-slate-950 via-slate-900 to-slate-800 text-white shadow-2xl">
    <div class="border-b border-white/10 px-5 py-5">
      <span class="text-xl font-black tracking-tight text-white">POS PRO</span>
      <p class="mt-0.5 text-xs text-indigo-200">Professional Suite</p>

      <div class="mt-4 rounded-xl border border-white/10 bg-white/5 px-3 py-2.5">
        <p class="text-xs uppercase tracking-wide text-slate-400">Login Sebagai</p>
        <p class="mt-0.5 truncate text-sm font-semibold text-white">{{ auth.user?.name ?? 'Guest' }}</p>
        <p class="text-xs text-indigo-200">{{ auth.user?.role ?? '-' }}</p>
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4">
      <ul class="space-y-1">
        <li v-for="item in visibleNavItems" :key="item.to">
          <RouterLink
            :to="item.to"
            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 transition-colors hover:bg-white/10 hover:text-white"
            active-class="bg-indigo-500 text-white hover:bg-indigo-500"
          >
            <span class="text-base leading-none">{{ item.icon }}</span>
            {{ item.label }}
          </RouterLink>
        </li>
      </ul>
    </nav>

    <div class="border-t border-white/10 px-5 py-4">
      <button
        class="mb-2 w-full rounded-xl border border-rose-300/20 bg-rose-500/10 px-3 py-2 text-sm font-medium text-rose-100 transition hover:bg-rose-500/20"
        @click="logout"
      >
        Logout
      </button>
      <p class="text-xs text-slate-400">v3.0 Professional POS</p>
    </div>
  </aside>
</template>
