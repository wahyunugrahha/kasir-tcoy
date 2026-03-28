<script setup>
import { RouterLink } from 'vue-router'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const navItems = [
  { to: '/pos', label: 'Kasir', icon: 'M3 7h18M6 7v13h12V7M9 11h6M9 15h4M8 4h8' },
  { to: '/products', label: 'Produk', icon: 'M4 7l8-4 8 4-8 4-8-4zm0 0v10l8 4 8-4V7' },
  { to: '/history', label: 'Riwayat', icon: 'M12 8v5l3 2M3.1 11A9 9 0 1112 21h-1m-7-4v4h4' },
  { to: '/manager-approval', label: 'Approval Manager', icon: 'M9 11V8a3 3 0 016 0v3m-7 0h8a1 1 0 011 1v6a1 1 0 01-1 1H8a1 1 0 01-1-1v-6a1 1 0 011-1z' },
  { to: '/reports', label: 'Laporan', icon: 'M5 20V10m7 10V4m7 16v-7M3 20h18' },
  { to: '/settings', label: 'Pengaturan', icon: 'M12 8.5A3.5 3.5 0 1112 15.5 3.5 3.5 0 0112 8.5zm0-6.5v2m0 16v2m8.66-15l-1.73 1m-13.86 0L3.34 7m17.32 10l-1.73-1m-13.86 0L3.34 17M2 12h2m16 0h2' },
  { to: '/orders', label: 'Order List', icon: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01' },
  { to: '/bills', label: 'Tagihan', icon: 'M7 3h10l2 2v14l-2 2H7l-2-2V5l2-2zm3 5h4m-4 4h6m-6 4h5' },
  { to: '/settlement', label: 'Settlement', icon: 'M4 7h16v10H4zM8 11h8M12 7v10M7 17h10' },
]

const visibleNavItems = navItems.filter((item) => {
  if (item.to === '/manager-approval' && auth.user?.role !== 'cashier') {
    return false
  }

  if (['/products', '/reports', '/settings'].includes(item.to) && auth.user?.role !== 'admin') {
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
  <aside class="flex w-64 shrink-0 flex-col border-r border-slate-200 bg-white/90 text-slate-700 backdrop-blur-sm">
    <div class="border-b border-slate-200 px-5 py-5">
      <span class="text-2xl font-black tracking-tight text-slate-800">KasirTcuy</span>
      <p class="mt-0.5 text-xs text-slate-500">POS System App</p>

      <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 shadow-sm">
        <p class="text-xs uppercase tracking-wide text-slate-500">Login Sebagai</p>
        <p class="mt-0.5 truncate text-sm font-semibold text-slate-800">{{ auth.user?.name ?? 'Guest' }}</p>
        <p class="text-xs text-slate-500">{{ auth.user?.role ?? '-' }}</p>
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4">
      <ul class="space-y-1">
        <li v-for="item in visibleNavItems" :key="item.to">
          <RouterLink
            :to="item.to"
            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition-all hover:bg-slate-100 hover:text-slate-900"
            active-class="bg-cyan-100 text-cyan-900 shadow-sm hover:bg-cyan-100"
          >
            <span class="grid h-6 w-6 place-items-center rounded-md border border-slate-300 bg-white text-slate-500">
              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
              </svg>
            </span>
            {{ item.label }}
          </RouterLink>
        </li>
      </ul>
    </nav>

    <div class="border-t border-slate-200 px-5 py-4">
      <button
        class="mb-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
        @click="logout"
      >
        Logout
      </button>
      <p class="text-xs text-slate-500">v2.0 By Ween</p>
    </div>
  </aside>
</template>
