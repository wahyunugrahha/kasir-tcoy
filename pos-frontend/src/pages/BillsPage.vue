<script setup>
import { onMounted, ref } from 'vue'
import api from '../services/api'

// Bills = transactions that are unpaid / pending
const bills = ref([])
const loading = ref(false)
const error = ref('')

async function loadBills() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/v1/transactions', { params: { payment_status: 'unpaid,partial', is_voided: false, page: 1, per_page: 50 } })
    bills.value = res.data.data ?? []
  } catch {
    error.value = 'Gagal memuat tagihan.'
  } finally {
    loading.value = false
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))
}

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}

onMounted(loadBills)
</script>

<template>
  <div>
    <div class="mb-5 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Tagihan (Bills)</h1>
        <p class="text-sm text-slate-500">Transaksi belum lunas / pay-later</p>
      </div>
      <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="loadBills">
        Muat Ulang
      </button>
    </div>

    <div v-if="error" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>

    <div v-if="loading" class="rounded-2xl bg-white py-12 text-center text-slate-500 shadow-sm">Memuat data...</div>

    <div v-else-if="bills.length === 0" class="rounded-2xl border-2 border-dashed border-slate-200 bg-white py-16 text-center">
      <span class="text-4xl">🧾</span>
      <p class="mt-3 text-slate-500">Tidak ada tagihan yang tertunda.</p>
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <table class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3">Invoice</th>
            <th class="px-4 py-3">Tanggal</th>
            <th class="px-4 py-3">Pelanggan</th>
            <th class="px-4 py-3 text-right">Total</th>
            <th class="px-4 py-3">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="bill in bills" :key="bill.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 font-mono font-medium text-indigo-600">{{ bill.invoice_number }}</td>
            <td class="px-4 py-3 text-slate-600">{{ formatDate(bill.created_at) }}</td>
            <td class="px-4 py-3 text-slate-600">{{ bill.customer?.name ?? '-' }}</td>
            <td class="px-4 py-3 text-right font-semibold">{{ formatCurrency(bill.grand_total) }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ bill.payment_status }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
