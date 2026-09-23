<script setup>
import { computed, onMounted, ref } from 'vue'
import { Bar, Doughnut } from 'vue-chartjs'
import * as XLSX from 'xlsx'
import {
  ArcElement,
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  Legend,
  LinearScale,
  Title,
  Tooltip,
} from 'chart.js'
import { useI18n } from 'vue-i18n'
import api from '../services/api'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement)

const { t } = useI18n()

const loading = ref(false)
const error = ref('')
const summary = ref(null)

const selectedMonth = ref(new Date().toISOString().slice(0, 7)) // YYYY-MM

const barChartData = ref({ labels: [], datasets: [] })
const doughnutChartData = ref({ labels: [], datasets: [] })
const salesByDate = ref([])
const topProducts = ref([])
const cashierPerformance = ref([])
const profitByCategory = ref([])
const stockValuation = ref(null)
const voidRefunds = ref(null)

const barChartOptions = computed(() => ({
  responsive: true,
  plugins: { legend: { position: 'top' }, title: { display: true, text: t('reports.dailySalesChartTitle') } },
}))

const doughnutOptions = computed(() => ({
  responsive: true,
  plugins: { legend: { position: 'right' }, title: { display: true, text: t('reports.topProductsChartTitle') } },
}))

function monthDateRange() {
  const [year, month] = selectedMonth.value.split('-').map(Number)
  const start = new Date(year, month - 1, 1)
  const end = new Date(year, month, 0)
  const iso = (d) => d.toISOString().slice(0, 10)
  return { start_date: iso(start), end_date: iso(end) }
}

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const range = monthDateRange()
    const [sumRes, salesRes, topRes, cashierRes, profitRes, stockRes, voidRes] = await Promise.all([
      api.get('/v1/reports/summary'),
      api.get('/v1/reports/sales-by-date', { params: { month: selectedMonth.value } }),
      api.get('/v1/reports/top-products', { params: { limit: 8, ...range } }),
      api.get('/v1/reports/cashier-performance', { params: range }),
      api.get('/v1/reports/profit-by-category', { params: range }),
      api.get('/v1/reports/stock-valuation'),
      api.get('/v1/reports/void-refunds', { params: range }),
    ])

    summary.value = sumRes.data
    salesByDate.value = salesRes.data
    topProducts.value = topRes.data
    cashierPerformance.value = cashierRes.data
    profitByCategory.value = profitRes.data
    stockValuation.value = stockRes.data
    voidRefunds.value = voidRes.data

    barChartData.value = {
      labels: salesByDate.value.map((r) => r.date),
      datasets: [
        {
          label: t('reports.totalSalesLabel'),
          data: salesByDate.value.map((r) => Number(r.total_sales)),
          backgroundColor: 'rgba(99, 102, 241, 0.7)',
          borderColor: 'rgba(99, 102, 241, 1)',
          borderWidth: 1,
        },
      ],
    }

    const palette = ['#6366f1', '#f59e0b', '#10b981', '#f43f5e', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6']
    doughnutChartData.value = {
      labels: topProducts.value.map((r) => r.product_name),
      datasets: [
        {
          data: topProducts.value.map((r) => Number(r.total_quantity)),
          backgroundColor: palette.slice(0, topProducts.value.length),
        },
      ],
    }
  } catch {
    error.value = t('reports.loadError')
  } finally {
    loading.value = false
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))
}

// Note: Excel export sheet names stay in Indonesian regardless of the active UI locale —
// they're generated file content, not on-screen UI.
function exportToExcel() {
  const workbook = XLSX.utils.book_new()

  XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(salesByDate.value), 'Penjualan Harian')
  XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(topProducts.value), 'Produk Terlaris')
  XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(cashierPerformance.value), 'Performa Kasir')
  XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(profitByCategory.value), 'Profit per Kategori')
  XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(stockValuation.value?.by_category ?? []), 'Valuasi Stok')
  XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(voidRefunds.value?.voided_transactions ?? []), 'Void')
  XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(voidRefunds.value?.refunds ?? []), 'Refund')

  XLSX.writeFile(workbook, `laporan-${selectedMonth.value}.xlsx`)
}

onMounted(loadData)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end gap-4">
      <div>
        <h1 class="text-xl font-bold text-ink">{{ t('reports.title') }}</h1>
        <p class="text-sm text-ink-faint">{{ t('reports.subtitle') }}</p>
      </div>
      <div class="ml-auto flex items-end gap-3">
        <label class="block text-sm">
          <span class="mb-1 block text-ink-soft">{{ t('reports.month') }}</span>
          <input v-model="selectedMonth" type="month" class="rounded-lg border border-line px-3 py-2 text-sm" />
        </label>
        <button class="rounded-full bg-brand-600 active:scale-95 transition-transform px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500" @click="loadData">
          {{ t('reports.show') }}
        </button>
        <button class="flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100" @click="exportToExcel">
          <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M5 19h14" />
          </svg>
          {{ t('reports.exportExcel') }}
        </button>
      </div>
    </div>

    <div v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
    <div v-if="loading" class="py-12 text-center text-ink-faint">{{ t('reports.loading') }}</div>

    <template v-else>
      <!-- Summary cards -->
      <div v-if="summary" class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <div class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
          <p class="text-xs text-ink-faint uppercase tracking-wide">{{ t('reports.todayRevenue') }}</p>
          <p class="mt-1 text-xl font-bold text-brand-700">{{ formatCurrency(summary.today.revenue) }}</p>
        </div>
        <div class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
          <p class="text-xs text-ink-faint uppercase tracking-wide">{{ t('reports.todayTransactions') }}</p>
          <p class="mt-1 text-xl font-bold text-brand-600">{{ summary.today.transactions }}</p>
        </div>
        <div class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
          <p class="text-xs text-ink-faint uppercase tracking-wide">{{ t('reports.todayItemsSold') }}</p>
          <p class="mt-1 text-xl font-bold text-ink">{{ summary.today.items_sold }}</p>
        </div>
        <div class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
          <p class="text-xs text-ink-faint uppercase tracking-wide">{{ t('reports.monthRevenue') }}</p>
          <p class="mt-1 text-xl font-bold text-brand-900">{{ formatCurrency(summary.month.revenue) }}</p>
        </div>
      </div>

      <!-- Charts -->
      <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
          <Bar v-if="barChartData.labels.length > 0" :data="barChartData" :options="barChartOptions" />
          <p v-else class="py-10 text-center text-sm text-ink-faint">{{ t('reports.noSalesData') }}</p>
        </div>
        <div class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
          <Doughnut v-if="doughnutChartData.labels.length > 0" :data="doughnutChartData" :options="doughnutOptions" />
          <p v-else class="py-10 text-center text-sm text-ink-faint">{{ t('reports.noProductData') }}</p>
        </div>
      </div>

      <!-- Cashier performance & profit by category -->
      <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
          <h3 class="mb-3 text-sm font-semibold text-ink">{{ t('reports.cashierPerformance') }}</h3>
          <table v-if="cashierPerformance.length > 0" class="w-full text-sm">
            <thead class="text-left text-xs uppercase text-ink-faint">
              <tr><th class="pb-2">{{ t('reports.nameCol') }}</th><th class="pb-2 text-right">{{ t('reports.transactionsCol') }}</th><th class="pb-2 text-right">{{ t('reports.totalSalesCol') }}</th></tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <tr v-for="row in cashierPerformance" :key="row.user_id">
                <td class="py-1.5">{{ row.user_name }}</td>
                <td class="py-1.5 text-right">{{ row.transaction_count }}</td>
                <td class="py-1.5 text-right font-semibold">{{ formatCurrency(row.total_sales) }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="text-sm text-ink-faint">{{ t('reports.noData') }}</p>
        </div>

        <div class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
          <h3 class="mb-3 text-sm font-semibold text-ink">{{ t('reports.profitByCategory') }}</h3>
          <table v-if="profitByCategory.length > 0" class="w-full text-sm">
            <thead class="text-left text-xs uppercase text-ink-faint">
              <tr><th class="pb-2">{{ t('reports.categoryCol') }}</th><th class="pb-2 text-right">{{ t('reports.revenueCol') }}</th><th class="pb-2 text-right">{{ t('reports.grossProfitCol') }}</th></tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <tr v-for="row in profitByCategory" :key="row.category_id">
                <td class="py-1.5">{{ row.category_name }}</td>
                <td class="py-1.5 text-right">{{ formatCurrency(row.revenue) }}</td>
                <td class="py-1.5 text-right font-semibold text-emerald-600">{{ formatCurrency(row.gross_profit) }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="text-sm text-ink-faint">{{ t('reports.noData') }}</p>
        </div>
      </div>

      <!-- Stock valuation & void/refund -->
      <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
          <h3 class="mb-3 text-sm font-semibold text-ink">
            {{ t('reports.stockValuation') }}
            <span v-if="stockValuation" class="ml-2 text-xs font-normal text-ink-faint">{{ t('reports.totalLabel', { value: formatCurrency(stockValuation.total_value) }) }}</span>
          </h3>
          <table v-if="stockValuation?.by_category?.length > 0" class="w-full text-sm">
            <thead class="text-left text-xs uppercase text-ink-faint">
              <tr><th class="pb-2">{{ t('reports.categoryCol') }}</th><th class="pb-2 text-right">{{ t('reports.unitCol') }}</th><th class="pb-2 text-right">{{ t('reports.stockValueCol') }}</th></tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <tr v-for="row in stockValuation.by_category" :key="row.category_id">
                <td class="py-1.5">{{ row.category_name }}</td>
                <td class="py-1.5 text-right">{{ row.total_units }}</td>
                <td class="py-1.5 text-right font-semibold">{{ formatCurrency(row.stock_value) }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="text-sm text-ink-faint">{{ t('reports.noData') }}</p>
        </div>

        <div class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
          <h3 class="mb-3 text-sm font-semibold text-ink">{{ t('reports.voidRefund') }}</h3>
          <div v-if="voidRefunds" class="mb-3 flex gap-4 text-xs text-ink-faint">
            <span>{{ t('reports.totalVoid') }} <span class="font-semibold text-rose-600 dark:text-rose-400">{{ formatCurrency(voidRefunds.total_voided) }}</span></span>
            <span>{{ t('reports.totalRefund') }} <span class="font-semibold text-amber-600">{{ formatCurrency(voidRefunds.total_refunded) }}</span></span>
          </div>
          <table v-if="voidRefunds?.voided_transactions?.length > 0" class="w-full text-sm">
            <thead class="text-left text-xs uppercase text-ink-faint">
              <tr><th class="pb-2">{{ t('reports.invoiceCol') }}</th><th class="pb-2">{{ t('reports.reasonCol') }}</th><th class="pb-2 text-right">{{ t('reports.valueCol') }}</th></tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <tr v-for="row in voidRefunds.voided_transactions" :key="row.id">
                <td class="py-1.5 font-mono">{{ row.invoice_number }}</td>
                <td class="py-1.5 text-ink-faint">{{ row.void_reason }}</td>
                <td class="py-1.5 text-right font-semibold text-rose-600 dark:text-rose-400">{{ formatCurrency(row.grand_total) }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="text-sm text-ink-faint">{{ t('reports.noVoidRefund') }}</p>
        </div>
      </div>
    </template>
  </div>
</template>
