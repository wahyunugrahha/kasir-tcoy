import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'landing',
      component: () => import('../views/LandingView.vue'),
      meta: { guestOnly: true, title: 'KasirTcuy' },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
      meta: { guestOnly: true, title: 'Login POS' },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../pages/DashboardPage.vue'),
      meta: { title: 'Dashboard', requiresAuth: true, roles: ['admin'] },
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
      meta: { title: 'Manajemen Produk', requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/history',
      name: 'history',
      component: () => import('../views/HistoryView.vue'),
      meta: { title: 'Riwayat Transaksi', requiresAuth: true },
    },
    {
      path: '/manager-approval',
      name: 'manager-approval',
      component: () => import('../views/ManagerApprovalView.vue'),
      meta: { title: 'Approval Manager', requiresAuth: true, roles: ['cashier'] },
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
    {
      path: '/promo-codes',
      name: 'promo-codes',
      component: () => import('../pages/PromoCodesPage.vue'),
      meta: { title: 'Kode Promo', requiresAuth: true, roles: ['admin'] },
    },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: auth.user?.role === 'admin' ? 'dashboard' : 'pos' }
  }

  if (to.meta.roles?.length && auth.user && !to.meta.roles.includes(auth.user.role)) {
    return { name: auth.user.role === 'admin' ? 'dashboard' : 'pos' }
  }

  return true
})

export default router

