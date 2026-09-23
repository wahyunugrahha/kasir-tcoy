<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Line, Doughnut } from 'vue-chartjs'
import {
  ArcElement, CategoryScale, Chart as ChartJS, Filler,
  Legend, LinearScale, LineElement, PointElement, Tooltip,
} from 'chart.js'
import api from '../services/api'
import { useTheme } from '../composables/useTheme'

ChartJS.register(CategoryScale, LinearScale, LineElement, PointElement, Filler, ArcElement, Tooltip, Legend)

const { isDark } = useTheme()
const { t, locale } = useI18n()

const loading = ref(false)
const error = ref('')
const summary = ref(null)
const lowStock = ref([])
const recentTransactions = ref([])
const salesByDate = ref([])
const activeStatusTab = ref('all')

const PAYMENT_METHOD_META = {
  cash: { label: 'Cash', color: '#0052ff' },
  qris: { label: 'QRIS', color: '#10b981' },
  debit: { label: 'Debit', color: '#f59e0b' },
  credit_card: { label: 'Credit Card', color: '#8b5cf6' },
  e_wallet: { label: 'E-Wallet', color: '#14b8a6' },
  bank_transfer: { label: 'Bank Transfer', color: '#f43f5e' },
}

async function loadDashboard() {
  loading.value = true
  error.value = ''
  try {
    const [summaryRes, lowStockRes, transactionsRes, salesRes] = await Promise.all([
      api.get('/v1/reports/summary'),
      api.get('/v1/reports/low-stock'),
      api.get('/v1/transactions', { params: { per_page: 20 } }),
      api.get('/v1/reports/sales-by-date'),
    ])
    summary.value = summaryRes.data
    lowStock.value = lowStockRes.data
    recentTransactions.value = transactionsRes.data.data ?? []
    salesByDate.value = salesRes.data ?? []
  } catch {
    error.value = t('dashboard.loadError')
  } finally {
    loading.value = false
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))
}

function formatDateTime(value) {
  return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

const todayLabel = computed(() =>
  new Date().toLocaleDateString(locale.value === 'en' ? 'en-US' : 'id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)

const statusLabel = computed(() => ({
  paid: t('dashboard.tabPaid'),
  partial: t('dashboard.tabPartial'),
  unpaid: t('dashboard.tabUnpaid'),
}))
const statusBadgeClass = {
  paid: 'bg-emerald-100 text-emerald-700',
  partial: 'bg-amber-100 text-amber-700',
  unpaid: 'bg-rose-100 text-rose-700',
}

// Real day-over-day delta only — never a fabricated percentage. Returns null when there's
// no real comparison point (e.g. zero transactions yesterday), and callers hide the badge then.
function deltaPct(current, previous) {
  if (!previous) return null
  return ((current - previous) / previous) * 100
}

const statCards = computed(() => {
  if (!summary.value) return []
  const today = summary.value.today
  const yesterday = summary.value.yesterday

  return [
    {
      key: 'transactions',
      label: t('dashboard.statTransactions'),
      value: String(today.transactions),
      delta: deltaPct(today.transactions, yesterday.transactions),
      icon: 'M3 7h18M6 7v13h12V7M9 11h6M9 15h4M8 4h8',
    },
    {
      key: 'items',
      label: t('dashboard.statItemsSold'),
      value: String(today.items_sold),
      delta: deltaPct(today.items_sold, yesterday.items_sold),
      icon: 'M4 7l8-4 8 4-8 4-8-4zm0 0v10l8 4 8-4V7',
    },
    {
      key: 'revenue',
      label: t('dashboard.statRevenue'),
      value: formatCurrency(today.revenue),
      delta: deltaPct(today.revenue, yesterday.revenue),
      icon: 'M12 6v3m0 6v3m-4.243-1.757c.549.549 1.494.943 2.454.996m1.789-8.478c-.96-.053-1.905.341-2.454.89-1.172 1.172-1.172 3.071 0 4.243.549.549 1.494.943 2.454.996m0-6.239c.96.053 1.905.447 2.454.996 1.172 1.172 1.172 3.07 0 4.242-.549.549-1.494.943-2.454.996M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    {
      key: 'aov',
      label: t('dashboard.statAov'),
      value: formatCurrency(today.average_order_value),
      delta: deltaPct(today.average_order_value, yesterday.average_order_value),
      icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
    },
  ]
})

const paymentBreakdown = computed(() => {
  if (!summary.value) return []
  const total = summary.value.today.payment_methods.reduce((sum, row) => sum + Number(row.total), 0)
  return summary.value.today.payment_methods.map((row) => {
    const meta = PAYMENT_METHOD_META[row.payment_method] ?? { label: row.payment_method, color: '#94a3b8' }
    return {
      ...meta,
      count: row.count,
      total: Number(row.total),
      pct: total > 0 ? (Number(row.total) / total) * 100 : 0,
    }
  })
})

const chartTextColor = computed(() => (isDark.value ? '#a8acb3' : '#5b616e'))
const chartGridColor = computed(() => (isDark.value ? '#23262b' : '#e5e8ec'))

const salesChartData = computed(() => ({
  labels: salesByDate.value.map((row) => new Date(row.date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })),
  datasets: [
    {
      label: 'Penjualan',
      data: salesByDate.value.map((row) => Number(row.total_sales)),
      borderColor: '#0052ff',
      backgroundColor: 'rgba(0, 82, 255, 0.12)',
      fill: true,
      tension: 0.35,
      pointRadius: 0,
      pointHoverRadius: 4,
    },
  ],
}))

const salesChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { callbacks: { label: (ctx) => formatCurrency(ctx.parsed.y) } },
  },
  scales: {
    x: { ticks: { color: chartTextColor.value }, grid: { display: false } },
    y: { ticks: { color: chartTextColor.value }, grid: { color: chartGridColor.value } },
  },
}))

const paymentChartData = computed(() => ({
  labels: paymentBreakdown.value.map((row) => row.label),
  datasets: [
    {
      data: paymentBreakdown.value.map((row) => row.total),
      backgroundColor: paymentBreakdown.value.map((row) => row.color),
      borderWidth: 0,
    },
  ],
}))

const paymentChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '70%',
  plugins: {
    legend: { display: false },
    tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${formatCurrency(ctx.parsed)}` } },
  },
}))

const statusTabs = computed(() => {
  const all = recentTransactions.value.length
  const counts = { paid: 0, partial: 0, unpaid: 0 }
  recentTransactions.value.forEach((tx) => {
    if (counts[tx.payment_status] !== undefined) counts[tx.payment_status] += 1
  })
  return [
    { key: 'all', label: t('dashboard.tabAll'), count: all },
    { key: 'paid', label: t('dashboard.tabPaid'), count: counts.paid },
    { key: 'partial', label: t('dashboard.tabPartial'), count: counts.partial },
    { key: 'unpaid', label: t('dashboard.tabUnpaid'), count: counts.unpaid },
  ]
})

const filteredTransactions = computed(() => {
  if (activeStatusTab.value === 'all') return recentTransactions.value.slice(0, 8)
  return recentTransactions.value.filter((tx) => tx.payment_status === activeStatusTab.value).slice(0, 8)
})

onMounted(loadDashboard)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-ink">{{ t('dashboard.title') }}</h1>
        <p class="text-sm text-ink-faint">{{ todayLabel }}</p>
      </div>
      <div class="flex items-center gap-2">
        <button class="rounded-lg border border-line px-4 py-2 text-sm hover:bg-surface-2" @click="loadDashboard">
          {{ t('dashboard.reload') }}
        </button>
        <RouterLink
          to="/pos"
          class="flex items-center gap-1.5 rounded-full bg-brand-600 active:scale-95 transition-transform px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          {{ t('dashboard.newTransaction') }}
        </RouterLink>
      </div>
    </div>

    <div v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
    <div v-if="loading" class="py-12 text-center text-ink-faint">{{ t('dashboard.loading') }}</div>

    <template v-else-if="summary">
      <!-- Stat cards -->
      <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <div v-for="card in statCards" :key="card.key" class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="grid h-9 w-9 place-items-center rounded-full border border-line bg-surface-2 text-brand-600">
              <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" :d="card.icon" />
              </svg>
            </span>
            <span
              v-if="card.delta !== null"
              class="flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-semibold"
              :class="card.delta >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
            >
              <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path v-if="card.delta >= 0" stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
              {{ Math.abs(card.delta).toFixed(0) }}%
            </span>
          </div>
          <p class="mt-3 text-xs uppercase tracking-wide text-ink-faint">{{ card.label }}</p>
          <p class="mt-1 text-2xl font-bold text-ink">{{ card.value }}</p>
          <p class="mt-0.5 text-xs text-ink-faint">{{ card.delta === null ? t('dashboard.noYesterdayData') : t('dashboard.vsYesterday') }}</p>
        </div>
      </div>

      <!-- Charts -->
      <div class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
        <div class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
          <h3 class="text-sm font-semibold text-ink">{{ t('dashboard.salesOverview') }}</h3>
          <div class="mt-4 h-64">
            <Line v-if="salesByDate.length > 0" :key="isDark" :data="salesChartData" :options="salesChartOptions" />
            <p v-else class="flex h-full items-center justify-center text-sm text-ink-faint">{{ t('dashboard.noSalesData') }}</p>
          </div>
        </div>

        <div class="flex h-full flex-col rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
          <h3 class="text-sm font-semibold text-ink">{{ t('dashboard.paymentMethodsToday') }}</h3>
          <div v-if="paymentBreakdown.length > 0" class="flex flex-1 items-center justify-center gap-4">
            <div class="h-32 w-32 shrink-0">
              <Doughnut :key="isDark" :data="paymentChartData" :options="paymentChartOptions" />
            </div>
            <ul class="w-40 shrink-0 space-y-2">
              <li v-for="row in paymentBreakdown" :key="row.label" class="flex items-center justify-between gap-2 text-xs">
                <span class="flex items-center gap-1.5 text-ink-soft">
                  <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: row.color }"></span>
                  {{ row.label }}
                </span>
                <span class="shrink-0 font-semibold text-ink">{{ row.count }} &middot; {{ row.pct.toFixed(0) }}%</span>
              </li>
            </ul>
          </div>
          <p v-else class="flex flex-1 items-center justify-center text-sm text-ink-faint">{{ t('dashboard.noTransactionsToday') }}</p>
        </div>
      </div>

      <!-- Low stock alert -->
      <div v-if="lowStock.length > 0" class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3">
        <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1.5 text-center">
          <h3 class="flex items-center gap-1.5 text-sm font-semibold text-amber-800">
            <svg class="h-4 w-4 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM4.32 19.5h15.36c1.33 0 2.16-1.44 1.5-2.6L13.5 4.6c-.66-1.15-2.34-1.15-3 0L2.82 16.9c-.66 1.16.17 2.6 1.5 2.6z" />
            </svg>
            {{ t('dashboard.lowStockTitle', { count: lowStock.length }) }}
          </h3>
          <p class="text-xs text-amber-800">
            {{ lowStock.map((p) => `${p.name} (${p.stock}/${p.min_stock})`).join(', ') }}
          </p>
          <RouterLink to="/products" class="text-xs font-medium text-amber-800 hover:underline">{{ t('dashboard.manageProducts') }}</RouterLink>
        </div>
      </div>

      <!-- Recent transactions -->
      <div class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
          <h3 class="text-sm font-semibold text-ink">{{ t('dashboard.recentTransactions') }}</h3>
          <RouterLink to="/history" class="text-xs font-medium text-brand-600 hover:underline">{{ t('dashboard.viewAll') }}</RouterLink>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
          <button
            v-for="tab in statusTabs"
            :key="tab.key"
            type="button"
            class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
            :class="activeStatusTab === tab.key ? 'border-brand-600 bg-brand-600 text-white' : 'border-line text-ink-soft hover:bg-surface-2'"
            @click="activeStatusTab = tab.key"
          >
            {{ tab.label }} <span class="opacity-80">{{ tab.count }}</span>
          </button>
        </div>

        <table v-if="filteredTransactions.length > 0" class="w-full text-sm">
          <thead>
            <tr class="border-b border-line-soft text-left text-xs uppercase tracking-wide text-ink-faint">
              <th class="pb-2 font-medium">{{ t('dashboard.colInvoice') }}</th>
              <th class="pb-2 font-medium">{{ t('dashboard.colTime') }}</th>
              <th class="pb-2 text-right font-medium">{{ t('dashboard.colMethod') }}</th>
              <th class="pb-2 text-right font-medium">{{ t('dashboard.colTotal') }}</th>
              <th class="pb-2 text-right font-medium">{{ t('dashboard.colStatus') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-soft">
            <tr v-for="tx in filteredTransactions" :key="tx.id">
              <td class="py-2 font-mono text-xs text-brand-600">{{ tx.invoice_number }}</td>
              <td class="py-2 text-xs text-ink-faint">{{ formatDateTime(tx.created_at) }}</td>
              <td class="py-2 text-right text-xs text-ink-soft">{{ PAYMENT_METHOD_META[tx.payment_method]?.label ?? tx.payment_method }}</td>
              <td class="py-2 text-right font-semibold text-ink">{{ formatCurrency(tx.grand_total) }}</td>
              <td class="py-2 text-right">
                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass[tx.payment_status]">
                  {{ statusLabel[tx.payment_status] ?? tx.payment_status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-sm text-ink-faint">{{ t('dashboard.noTransactionsForFilter') }}</p>
      </div>
    </template>
  </div>
</template>
