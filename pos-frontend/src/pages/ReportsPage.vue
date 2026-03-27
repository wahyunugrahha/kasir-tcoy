<script setup>
import { onMounted, ref } from 'vue'
import { Bar, Doughnut } from 'vue-chartjs'
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
import api from '../services/api'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement)

const loading = ref(false)
const error = ref('')
const summary = ref(null)

const selectedMonth = ref(new Date().toISOString().slice(0, 7)) // YYYY-MM

const barChartData = ref({ labels: [], datasets: [] })
const doughnutChartData = ref({ labels: [], datasets: [] })

const barChartOptions = {
  responsive: true,
  plugins: { legend: { position: 'top' }, title: { display: true, text: 'Penjualan Harian' } },
}

const doughnutOptions = {
  responsive: true,
  plugins: { legend: { position: 'right' }, title: { display: true, text: 'Produk Terlaris (Qty)' } },
}

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [sumRes, salesRes, topRes] = await Promise.all([
      api.get('/v1/reports/summary'),
      api.get('/v1/reports/sales-by-date', { params: { month: selectedMonth.value } }),
      api.get('/v1/reports/top-products', { params: { limit: 8 } }),
    ])

    summary.value = sumRes.data

    const salesRows = salesRes.data
    barChartData.value = {
      labels: salesRows.map((r) => r.date),
      datasets: [
        {
          label: 'Total Penjualan (Rp)',
          data: salesRows.map((r) => Number(r.total_sales)),
          backgroundColor: 'rgba(99, 102, 241, 0.7)',
          borderColor: 'rgba(99, 102, 241, 1)',
          borderWidth: 1,
        },
      ],
    }

    const topRows = topRes.data
    const palette = ['#6366f1', '#f59e0b', '#10b981', '#f43f5e', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6']
    doughnutChartData.value = {
      labels: topRows.map((r) => r.product_name),
      datasets: [
        {
          data: topRows.map((r) => Number(r.total_quantity)),
          backgroundColor: palette.slice(0, topRows.length),
        },
      ],
    }
  } catch {
    error.value = 'Gagal memuat data laporan.'
  } finally {
    loading.value = false
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))
}

onMounted(loadData)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end gap-4">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Laporan</h1>
        <p class="text-sm text-slate-500">Analitik penjualan & performa</p>
      </div>
      <div class="ml-auto flex items-end gap-3">
        <label class="block text-sm">
          <span class="mb-1 block text-slate-600">Bulan</span>
          <input v-model="selectedMonth" type="month" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
        </label>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" @click="loadData">
          Tampilkan
        </button>
      </div>
    </div>

    <div v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
    <div v-if="loading" class="py-12 text-center text-slate-500">Memuat data laporan...</div>

    <template v-else>
      <!-- Summary cards -->
      <div v-if="summary" class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs text-slate-500 uppercase tracking-wide">Pendapatan Hari Ini</p>
          <p class="mt-1 text-xl font-bold text-indigo-600">{{ formatCurrency(summary.today.revenue) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs text-slate-500 uppercase tracking-wide">Transaksi Hari Ini</p>
          <p class="mt-1 text-xl font-bold text-emerald-600">{{ summary.today.transactions }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs text-slate-500 uppercase tracking-wide">Item Terjual Hari Ini</p>
          <p class="mt-1 text-xl font-bold text-amber-600">{{ summary.today.items_sold }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs text-slate-500 uppercase tracking-wide">Pendapatan Bulan Ini</p>
          <p class="mt-1 text-xl font-bold text-violet-600">{{ formatCurrency(summary.month.revenue) }}</p>
        </div>
      </div>

      <!-- Charts -->
      <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <Bar v-if="barChartData.labels.length > 0" :data="barChartData" :options="barChartOptions" />
          <p v-else class="py-10 text-center text-sm text-slate-400">Tidak ada data penjualan untuk bulan ini.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <Doughnut v-if="doughnutChartData.labels.length > 0" :data="doughnutChartData" :options="doughnutOptions" />
          <p v-else class="py-10 text-center text-sm text-slate-400">Tidak ada data produk untuk ditampilkan.</p>
        </div>
      </div>
    </template>
  </div>
</template>
