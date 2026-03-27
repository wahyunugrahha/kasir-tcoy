<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../services/api'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const transactions = ref([])
const loading = ref(false)
const error = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const processingVoidId = ref(null)

const isAdmin = computed(() => auth.user?.role === 'admin')

async function loadTransactions(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/v1/transactions', { params: { page, per_page: 15 } })
    const data = res.data
    transactions.value = data.data ?? []
    currentPage.value = data.current_page ?? 1
    lastPage.value = data.last_page ?? 1
    total.value = data.total ?? 0
  } catch {
    error.value = 'Gagal memuat riwayat transaksi.'
  } finally {
    loading.value = false
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))
}

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

function paymentBadge(method) {
  const map = { cash: 'bg-emerald-100 text-emerald-700', qris: 'bg-blue-100 text-blue-700', debit: 'bg-violet-100 text-violet-700' }
  return map[method] ?? 'bg-slate-100 text-slate-600'
}

function statusBadge(status) {
  const map = {
    paid: 'bg-emerald-100 text-emerald-700',
    partial: 'bg-amber-100 text-amber-700',
    unpaid: 'bg-rose-100 text-rose-700',
  }

  return map[status] ?? 'bg-slate-100 text-slate-600'
}

async function voidTransaction(transactionId) {
  const reason = window.prompt('Alasan void transaksi (minimal 5 karakter):')

  if (!reason) {
    return
  }

  processingVoidId.value = transactionId
  error.value = ''

  try {
    await api.put(`/v1/transactions/${transactionId}/void`, { reason })
    await loadTransactions(currentPage.value)
  } catch (err) {
    error.value = err.response?.data?.message ?? 'Gagal melakukan void transaksi.'
  } finally {
    processingVoidId.value = null
  }
}

onMounted(() => loadTransactions())
</script>

<template>
  <div>
    <div class="mb-5 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Riwayat Transaksi</h1>
        <p class="text-sm text-slate-500">Total {{ total }} transaksi</p>
      </div>
      <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="loadTransactions(currentPage)">
        Muat Ulang
      </button>
    </div>

    <div v-if="error" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div v-if="loading" class="py-12 text-center text-slate-500">Memuat data...</div>

      <table v-else class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3">Invoice</th>
            <th class="px-4 py-3">Tanggal</th>
            <th class="px-4 py-3">Metode</th>
            <th class="px-4 py-3">Status</th>
            <th v-if="isAdmin" class="px-4 py-3 text-right">Aksi</th>
            <th class="px-4 py-3 text-right">Total</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="transactions.length === 0">
            <td :colspan="isAdmin ? 6 : 5" class="py-12 text-center text-slate-400">Belum ada transaksi.</td>
          </tr>
          <tr v-for="trx in transactions" :key="trx.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 font-mono font-medium text-indigo-600">{{ trx.invoice_number }}</td>
            <td class="px-4 py-3 text-slate-600">{{ formatDate(trx.created_at) }}</td>
            <td class="px-4 py-3">
              <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', paymentBadge(trx.payment_method)]">
                {{ trx.payment_method?.toUpperCase() }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', statusBadge(trx.payment_status)]">
                {{ trx.payment_status }}
              </span>
              <p v-if="trx.is_voided" class="mt-1 text-xs text-rose-600">
                VOID: {{ trx.void_reason }}
              </p>
            </td>
            <td v-if="isAdmin" class="px-4 py-3 text-right">
              <button
                v-if="!trx.is_voided"
                class="rounded-lg border border-rose-300 px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 disabled:opacity-50"
                :disabled="processingVoidId === trx.id"
                @click="voidTransaction(trx.id)"
              >
                {{ processingVoidId === trx.id ? 'Memproses...' : 'Void' }}
              </button>
            </td>
            <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ formatCurrency(trx.grand_total) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="mt-4 flex items-center justify-center gap-2">
      <button
        v-for="page in lastPage"
        :key="page"
        :class="['rounded-lg px-3 py-1.5 text-sm font-medium', page === currentPage ? 'bg-indigo-600 text-white' : 'border border-slate-300 hover:bg-slate-50']"
        @click="loadTransactions(page)"
      >
        {{ page }}
      </button>
    </div>
  </div>
</template>
