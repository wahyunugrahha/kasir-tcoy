import { createRouter, createWebHistory } from 'vue-router'

function getAuthUser() {
  const raw = localStorage.getItem('pos_auth_user')
  if (!raw) return null

  try {
    return JSON.parse(raw)
  } catch {
    return null
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'landing',
      component: () => import('../views/LandingView.vue'),
      meta: { guestOnly: true, title: 'POS Professional' },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
      meta: { guestOnly: true, title: 'Login POS' },
    },
    {
      path: '/pos',
      name: 'pos',
      component: () => import('../views/POSView.vue'),
      meta: { title: 'Kasir', requiresAuth: true },
    },
    {
      path: '/products',
      name: 'products',
      component: () => import('../views/ProductView.vue'),
      meta: { title: 'Manajemen Produk', requiresAuth: true },
    },
    {
      path: '/history',
      name: 'history',
      component: () => import('../views/HistoryView.vue'),
      meta: { title: 'Riwayat Transaksi', requiresAuth: true },
    },
    {
      path: '/reports',
      name: 'reports',
      component: () => import('../views/ReportView.vue'),
      meta: { title: 'Laporan', requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/settings',
      name: 'settings',
      component: () => import('../views/SettingView.vue'),
      meta: { title: 'Pengaturan', requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/orders',
      name: 'orders',
      component: () => import('../pages/OrderListPage.vue'),
      meta: { title: 'Order List', requiresAuth: true },
    },
    {
      path: '/bills',
      name: 'bills',
      component: () => import('../pages/BillsPage.vue'),
      meta: { title: 'Tagihan', requiresAuth: true },
    },
    {
      path: '/settlement',
      name: 'settlement',
      component: () => import('../pages/SettlementPage.vue'),
      meta: { title: 'Settlement', requiresAuth: true },
    },
  ],
})

router.beforeEach((to) => {
  const token = localStorage.getItem('pos_auth_token')
  const user = getAuthUser()

  if (to.meta.requiresAuth && !token) {
    return { name: 'login' }
  }

  if (to.meta.guestOnly && token) {
    return { name: 'pos' }
  }

  if (to.meta.roles?.length && user && !to.meta.roles.includes(user.role)) {
    return { name: 'pos' }
  }

  return true
})

export default router

