<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import * as XLSX from 'xlsx'
import api from '../services/api'
import { useAuthStore } from '../stores/auth'
import { useManagerApprovalStore } from '../stores/managerApproval'

const router = useRouter()
const auth = useAuthStore()
const approval = useManagerApprovalStore()

const transactions = ref([])
const loading = ref(false)
const exporting = ref(false)
const error = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const processingVoidId = ref(null)
const processingRefund = ref(false)
const processingVoid = ref(false)

const filters = ref({
  payment_status: '',
  payment_method: '',
  is_voided: '',
  start_date: '',
  end_date: '',
  per_page: 15,
})

const showDetailModal = ref(false)
const loadingDetail = ref(false)
const selectedTransactionDetail = ref(null)

const showVoidModal = ref(false)
const voidTransactionTarget = ref(null)
const voidReason = ref('')

const showRefundModal = ref(false)
const refundTransaction = ref(null)
const refundDetail = ref(null)
const refundReason = ref('')
const refundQtyMap = ref({})

const isAdmin = computed(() => auth.user?.role === 'admin')
const requiresManagerApproval = computed(() => !isAdmin.value)

const approvalSummary = computed(() => {
  if (!approval.isValid) {
    return 'Approval manager belum aktif.'
  }

  const remainingMinutes = Math.max(1, Math.floor(approval.secondsLeft / 60))
  return `Approval aktif: ${approval.managerName} (${approval.managerEmail}) - sisa ${remainingMinutes} menit`
})

function buildQueryParams(page = 1) {
  const params = {
    page,
    per_page: Number(filters.value.per_page || 15),
  }

  if (filters.value.payment_status) {
    params.payment_status = filters.value.payment_status
  }

  if (filters.value.payment_method) {
    params.payment_method = filters.value.payment_method
  }

  if (filters.value.is_voided !== '') {
    params.is_voided = filters.value.is_voided
  }

  if (filters.value.start_date) {
    params.start_date = filters.value.start_date
  }

  if (filters.value.end_date) {
    params.end_date = filters.value.end_date
  }

  return params
}

async function loadTransactions(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/v1/transactions', { params: buildQueryParams(page) })
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

function applyFilters() {
  loadTransactions(1)
}

function resetFilters() {
  filters.value = {
    payment_status: '',
    payment_method: '',
    is_voided: '',
    start_date: '',
    end_date: '',
    per_page: 15,
  }

  loadTransactions(1)
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
  const map = {
    cash: 'bg-emerald-100 text-emerald-700',
    qris: 'bg-blue-100 text-blue-700',
    debit: 'bg-violet-100 text-violet-700',
    credit_card: 'bg-fuchsia-100 text-fuchsia-700',
    e_wallet: 'bg-cyan-100 text-cyan-700',
    bank_transfer: 'bg-amber-100 text-amber-700',
    mixed: 'bg-slate-100 text-slate-700',
  }
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

async function fetchTransactionDetail(transactionId) {
  const res = await api.get(`/v1/transactions/${transactionId}`)
  return res.data
}

async function openDetailModal(transaction) {
  showDetailModal.value = true
  loadingDetail.value = true
  selectedTransactionDetail.value = null

  try {
    selectedTransactionDetail.value = await fetchTransactionDetail(transaction.id)
  } catch {
    error.value = 'Gagal memuat detail transaksi.'
    showDetailModal.value = false
  } finally {
    loadingDetail.value = false
  }
}

function closeDetailModal() {
  showDetailModal.value = false
  selectedTransactionDetail.value = null
}

function openVoidModal(transaction) {
  showVoidModal.value = true
  voidTransactionTarget.value = transaction
  voidReason.value = ''
}

function closeVoidModal() {
  showVoidModal.value = false
  voidTransactionTarget.value = null
  voidReason.value = ''
}

async function submitVoid() {
  if (!voidTransactionTarget.value) {
    return
  }

  if (String(voidReason.value).trim().length < 5) {
    error.value = 'Alasan void minimal 5 karakter.'
    return
  }

  const payload = {
    reason: String(voidReason.value).trim(),
  }

  if (requiresManagerApproval.value) {
    if (!approval.isValid) {
      error.value = 'Approval manager belum aktif. Buka menu Approval Manager terlebih dahulu.'
      return
    }

    payload.manager_user_id = Number(approval.managerUserId)
    payload.manager_pin = String(approval.managerPin)
  }

  processingVoid.value = true
  processingVoidId.value = voidTransactionTarget.value.id
  error.value = ''

  try {
    await api.put(`/v1/transactions/${voidTransactionTarget.value.id}/void`, payload)
    closeVoidModal()
    await loadTransactions(currentPage.value)
  } catch (err) {
    error.value = err.response?.data?.message ?? 'Gagal melakukan void transaksi.'
  } finally {
    processingVoid.value = false
    processingVoidId.value = null
  }
}

async function openRefundModal(transaction) {
  showRefundModal.value = true
  processingRefund.value = true
  error.value = ''
  refundTransaction.value = transaction
  refundReason.value = ''
  refundQtyMap.value = {}

  try {
    const detail = await fetchTransactionDetail(transaction.id)
    refundDetail.value = detail
    const details = detail?.details ?? []
    const nextMap = {}
    details.forEach((detailRow) => {
      nextMap[detailRow.id] = 0
    })
    refundQtyMap.value = nextMap
  } catch {
    error.value = 'Gagal memuat detail transaksi untuk refund.'
    showRefundModal.value = false
  } finally {
    processingRefund.value = false
  }
}

function closeRefundModal() {
  showRefundModal.value = false
  refundTransaction.value = null
  refundDetail.value = null
  refundReason.value = ''
  refundQtyMap.value = {}
}

async function submitRefund() {
  if (!refundTransaction.value || !refundDetail.value) {
    return
  }

  const items = (refundDetail.value.details ?? [])
    .map((detail) => ({
      transaction_detail_id: detail.id,
      quantity: Number(refundQtyMap.value[detail.id] || 0),
    }))
    .filter((item) => item.quantity > 0)

  if (items.length === 0) {
    error.value = 'Pilih minimal satu item untuk direfund.'
    return
  }

  if (String(refundReason.value).trim().length < 5) {
    error.value = 'Alasan refund minimal 5 karakter.'
    return
  }

  const payload = {
    reason: String(refundReason.value).trim(),
    items,
  }

  if (requiresManagerApproval.value) {
    if (!approval.isValid) {
      error.value = 'Approval manager belum aktif. Buka menu Approval Manager terlebih dahulu.'
      return
    }

    payload.manager_user_id = Number(approval.managerUserId)
    payload.manager_pin = String(approval.managerPin)
  }

  processingRefund.value = true
  error.value = ''

  try {
    await api.post(`/v1/transactions/${refundTransaction.value.id}/refund`, payload)
    closeRefundModal()
    await loadTransactions(currentPage.value)
  } catch (err) {
    error.value = err.response?.data?.message ?? 'Gagal memproses refund.'
  } finally {
    processingRefund.value = false
  }
}

function formatDateTimeCsv(value) {
  if (!value) return ''

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)

  return date.toLocaleString('id-ID', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  })
}

function buildSheetColumns(rows) {
  const colCount = Math.max(...rows.map((row) => row.length))

  return Array.from({ length: colCount }, (_, colIndex) => {
    const maxLen = rows.reduce((longest, row) => {
      const value = row[colIndex]
      const size = String(value ?? '').length
      return Math.max(longest, size)
    }, 10)

    return { wch: Math.min(42, Math.max(12, maxLen + 2)) }
  })
}

function applyCurrencyFormat(worksheet, rows, currencyColumns = [], startDataRow = 1) {
  const totalRows = rows.length
  if (totalRows <= startDataRow || currencyColumns.length === 0) {
    return
  }

  for (let rowIndex = startDataRow; rowIndex < totalRows; rowIndex += 1) {
    currencyColumns.forEach((columnIndex) => {
      const cellRef = XLSX.utils.encode_cell({ c: columnIndex, r: rowIndex })
      const cell = worksheet[cellRef]
      if (!cell) return

      const numericValue = Number(cell.v)
      if (!Number.isFinite(numericValue)) return

      cell.t = 'n'
      cell.v = numericValue
      cell.z = '[$Rp-421] #,##0'
    })
  }
}

function applySheetReadability(worksheet, rows, options = {}) {
  const currencyColumns = options.currencyColumns ?? []
  const startDataRow = options.startDataRow ?? 1

  worksheet['!cols'] = buildSheetColumns(rows)
  worksheet['!autofilter'] = {
    ref: XLSX.utils.encode_range({
      s: { c: 0, r: 0 },
      e: { c: Math.max(0, rows[0].length - 1), r: Math.max(0, rows.length - 1) },
    }),
  }

  worksheet['!freeze'] = {
    xSplit: 0,
    ySplit: 1,
    topLeftCell: 'A2',
    activePane: 'bottomLeft',
    state: 'frozen',
  }

  applyCurrencyFormat(worksheet, rows, currencyColumns, startDataRow)

  // Community edition may ignore style metadata in some environments, but keep it for compatible engines.
  for (let col = 0; col < rows[0].length; col += 1) {
    const cellRef = XLSX.utils.encode_cell({ c: col, r: 0 })
    if (!worksheet[cellRef]) continue
    worksheet[cellRef].s = {
      font: { bold: true },
    }
  }
}

function downloadExcelWorkbook(filename, sheets) {
  const workbook = XLSX.utils.book_new()

  sheets.forEach((sheet) => {
    const worksheet = XLSX.utils.aoa_to_sheet(sheet.rows)
    applySheetReadability(worksheet, sheet.rows, {
      currencyColumns: sheet.currencyColumns ?? [],
      startDataRow: sheet.startDataRow ?? 1,
    })
    XLSX.utils.book_append_sheet(workbook, worksheet, sheet.name)
  })

  XLSX.writeFile(workbook, filename)
}

function buildHistoryDetailRows(transactionsList) {
  const rows = [
    [
      'Invoice',
      'Tanggal',
      'Kasir',
      'Customer',
      'Produk',
      'Varian',
      'Qty',
      'Harga Satuan',
      'Diskon Item',
      'Pajak Item',
      'Subtotal Item',
    ],
  ]

  transactionsList.forEach((trx) => {
    const details = Array.isArray(trx.details) ? trx.details : []

    if (details.length === 0) {
      rows.push([
        trx.invoice_number,
        formatDateTimeCsv(trx.created_at),
        trx.user?.name ?? '',
        trx.customer?.name ?? '',
        '(tidak ada detail)',
        '',
        '',
        '',
        '',
        '',
        '',
      ])
      return
    }

    details.forEach((line) => {
      rows.push([
        trx.invoice_number,
        formatDateTimeCsv(trx.created_at),
        trx.user?.name ?? '',
        trx.customer?.name ?? '',
        line.product_name_snapshot ?? '',
        line.variant_name_snapshot ?? '',
        line.quantity ?? 0,
        line.price_snapshot ?? 0,
        line.line_discount_amount ?? 0,
        line.line_tax_amount ?? 0,
        line.subtotal ?? 0,
      ])
    })
  })

  return rows
}

function normalizeModifierSummary(modifiers) {
  if (!Array.isArray(modifiers) || modifiers.length === 0) {
    return ''
  }

  return modifiers
    .map((modifier) => `${modifier.name} x${modifier.quantity} (${Number(modifier.price_delta || 0)})`)
    .join(' | ')
}

async function exportExcel() {
  exporting.value = true
  error.value = ''

  try {
    const allRows = []
    let page = 1
    let finalPage = 1

    do {
      const res = await api.get('/v1/transactions', {
        params: {
          ...buildQueryParams(page),
          per_page: 100,
        },
      })

      const data = res.data
      finalPage = Number(data.last_page || 1)
      const list = data.data ?? []
      allRows.push(...list)
      page += 1
    } while (page <= finalPage)

    const summaryRows = [
      [
        'Invoice',
        'Tanggal',
        'Kasir',
        'Customer',
        'Metode',
        'Status',
        'Voided',
        'Subtotal',
        'Diskon',
        'Pajak',
        'Total Pembayaran',
        'Uang Diterima',
        'Refund',
        'Kas Diterima',
        'Kembalian',
        'Alasan Void',
      ],
      ...allRows.map((trx) => [
        trx.invoice_number,
        formatDateTimeCsv(trx.created_at),
        trx.user?.name ?? '',
        trx.customer?.name ?? '',
        trx.payment_method ?? '',
        trx.payment_status ?? '',
        trx.is_voided ? 'yes' : 'no',
        trx.subtotal ?? 0,
        trx.discount ?? 0,
        trx.tax ?? 0,
        trx.grand_total,
        trx.amount_paid ?? 0,
        trx.refunded_amount ?? 0,
        trx.cash_received ?? 0,
        trx.cash_change ?? 0,
        trx.void_reason ?? '',
      ]),
    ]

    const detailRows = buildHistoryDetailRows(allRows)

    const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')
    downloadExcelWorkbook(`riwayat-transaksi-${stamp}.xlsx`, [
      {
        name: 'Ringkasan',
        rows: summaryRows,
        currencyColumns: [7, 8, 9, 10, 11, 12, 13, 14],
      },
      {
        name: 'Detail',
        rows: detailRows,
        currencyColumns: [7, 8, 9, 10],
      },
    ])
  } catch (err) {
    error.value = err.response?.data?.message ?? 'Gagal export Excel.'
  } finally {
    exporting.value = false
  }
}

async function exportTransactionDetailExcel(transaction) {
  error.value = ''

  try {
    const detail = await fetchTransactionDetail(transaction.id)
    const summaryRows = [
      ['Invoice', detail.invoice_number],
      ['Tanggal', formatDateTimeCsv(detail.created_at)],
      ['Kasir', detail.user?.name ?? ''],
      ['Customer', detail.customer?.name ?? ''],
      ['Metode Pembayaran', detail.payment_method ?? ''],
      ['Status Pembayaran', detail.payment_status ?? ''],
      ['Subtotal', detail.subtotal ?? 0],
      ['Diskon', detail.discount ?? 0],
      ['Pajak', detail.tax ?? 0],
      ['Total Pembayaran', detail.grand_total ?? 0],
      ['Uang Diterima', detail.amount_paid ?? detail.cash_received ?? 0],
      ['Kembalian', detail.cash_change ?? 0],
    ]

    const detailRows = [
      [
        'Invoice',
        'Tanggal',
        'Kasir',
        'Customer',
        'Product',
        'Variant',
        'Qty',
        'Unit Price',
        'Line Discount',
        'Line Tax',
        'Line Subtotal',
        'Modifiers',
      ],
      ...(detail.details ?? []).map((line) => [
        detail.invoice_number,
        formatDateTimeCsv(detail.created_at),
        detail.user?.name ?? '',
        detail.customer?.name ?? '',
        line.product_name_snapshot,
        line.variant_name_snapshot ?? '',
        line.quantity,
        line.price_snapshot,
        line.line_discount_amount ?? 0,
        line.line_tax_amount ?? 0,
        line.subtotal,
        normalizeModifierSummary(line.modifiers),
      ]),
    ]

    const safeInvoice = String(detail.invoice_number || `trx-${transaction.id}`).replace(/[^a-zA-Z0-9-_]/g, '_')
    downloadExcelWorkbook(`detail-${safeInvoice}.xlsx`, [
      {
        name: 'Ringkasan',
        rows: summaryRows,
        currencyColumns: [1],
        startDataRow: 6,
      },
      {
        name: 'Detail',
        rows: detailRows,
        currencyColumns: [7, 8, 9, 10],
      },
    ])
  } catch {
    error.value = 'Gagal export detail transaksi ke Excel.'
  }
}

function goToManagerApproval() {
  router.push('/manager-approval')
}

onMounted(() => {
  loadTransactions()
})
</script>

<template>
  <div>
    <div class="mb-5 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Riwayat Transaksi</h1>
        <p class="text-sm text-slate-500">Total {{ total }} transaksi</p>
      </div>
      <div class="flex gap-2">
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="resetFilters">
          Reset Filter
        </button>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="loadTransactions(currentPage)">
          Muat Ulang
        </button>
        <button class="rounded-lg border border-indigo-300 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 disabled:opacity-50" :disabled="exporting" @click="exportExcel">
          {{ exporting ? 'Mengunduh...' : 'Export Excel' }}
        </button>
      </div>
    </div>

    <div
      v-if="requiresManagerApproval"
      :class="[
        'mb-4 rounded-lg border px-4 py-3 text-sm',
        approval.isValid ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-800',
      ]"
    >
      <div class="flex items-center justify-between gap-3">
        <p>{{ approvalSummary }}</p>
        <button class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="goToManagerApproval">
          Buka Approval Manager
        </button>
      </div>
    </div>

    <div class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-6">
      <label class="block text-sm">
        <span class="mb-1 block text-xs text-slate-500">Status Bayar</span>
        <select v-model="filters.payment_status" class="w-full rounded border border-slate-300 px-3 py-2" @change="applyFilters">
          <option value="">Semua</option>
          <option value="paid">Paid</option>
          <option value="partial">Partial</option>
          <option value="unpaid">Unpaid</option>
        </select>
      </label>
      <label class="block text-sm">
        <span class="mb-1 block text-xs text-slate-500">Metode Bayar</span>
        <select v-model="filters.payment_method" class="w-full rounded border border-slate-300 px-3 py-2" @change="applyFilters">
          <option value="">Semua</option>
          <option value="cash">Cash</option>
          <option value="qris">QRIS</option>
          <option value="debit">Debit</option>
          <option value="credit_card">Credit Card</option>
          <option value="e_wallet">E-Wallet</option>
          <option value="bank_transfer">Bank Transfer</option>
          <option value="mixed">Mixed</option>
        </select>
      </label>
      <label class="block text-sm">
        <span class="mb-1 block text-xs text-slate-500">Status Void</span>
        <select v-model="filters.is_voided" class="w-full rounded border border-slate-300 px-3 py-2" @change="applyFilters">
          <option value="">Semua</option>
          <option value="false">Aktif</option>
          <option value="true">Voided</option>
        </select>
      </label>
      <label class="block text-sm">
        <span class="mb-1 block text-xs text-slate-500">Dari Tanggal</span>
        <input v-model="filters.start_date" type="date" class="w-full rounded border border-slate-300 px-3 py-2" @change="applyFilters" />
      </label>
      <label class="block text-sm">
        <span class="mb-1 block text-xs text-slate-500">Sampai Tanggal</span>
        <input v-model="filters.end_date" type="date" class="w-full rounded border border-slate-300 px-3 py-2" @change="applyFilters" />
      </label>
      <label class="block text-sm">
        <span class="mb-1 block text-xs text-slate-500">Baris per Halaman</span>
        <select v-model.number="filters.per_page" class="w-full rounded border border-slate-300 px-3 py-2" @change="applyFilters">
          <option :value="10">10</option>
          <option :value="15">15</option>
          <option :value="25">25</option>
          <option :value="50">50</option>
        </select>
      </label>
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
            <th class="px-4 py-3 text-right">Aksi</th>
            <th class="px-4 py-3 text-right">Total</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="transactions.length === 0">
            <td colspan="6" class="py-12 text-center text-slate-400">Belum ada transaksi.</td>
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
            <td class="px-4 py-3 text-right">
              <div class="flex justify-end gap-2">
                <button
                  class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                  @click="openDetailModal(trx)"
                >
                  Detail
                </button>
                <button
                  class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                  @click="exportTransactionDetailExcel(trx)"
                >
                  Export Detail Excel
                </button>
                <button
                  v-if="!trx.is_voided"
                  class="rounded-lg border border-indigo-300 px-2.5 py-1 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 disabled:opacity-50"
                  @click="openRefundModal(trx)"
                >
                  Refund
                </button>
                <button
                  v-if="!trx.is_voided"
                  class="rounded-lg border border-rose-300 px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 disabled:opacity-50"
                  :disabled="processingVoidId === trx.id"
                  @click="openVoidModal(trx)"
                >
                  {{ processingVoidId === trx.id ? 'Memproses...' : 'Void' }}
                </button>
              </div>
            </td>
            <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ formatCurrency(trx.grand_total) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

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

  <div v-if="showDetailModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
    <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-800">Detail Transaksi</h2>
        <button class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" @click="closeDetailModal">Tutup</button>
      </div>

      <div v-if="loadingDetail" class="py-8 text-center text-sm text-slate-500">Memuat detail...</div>

      <div v-else-if="selectedTransactionDetail" class="space-y-4">
        <div class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 md:grid-cols-2">
          <p class="text-sm"><span class="text-slate-500">Invoice:</span> <span class="font-semibold">{{ selectedTransactionDetail.invoice_number }}</span></p>
          <p class="text-sm"><span class="text-slate-500">Kasir:</span> <span class="font-semibold">{{ selectedTransactionDetail.user?.name ?? '-' }}</span></p>
          <p class="text-sm"><span class="text-slate-500">Customer:</span> <span class="font-semibold">{{ selectedTransactionDetail.customer?.name ?? '-' }}</span></p>
          <p class="text-sm"><span class="text-slate-500">Tanggal:</span> <span class="font-semibold">{{ formatDate(selectedTransactionDetail.created_at) }}</span></p>
        </div>

        <div class="rounded-xl border border-slate-200">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-3 py-2">Item</th>
                <th class="px-3 py-2 text-right">Qty</th>
                <th class="px-3 py-2 text-right">Subtotal</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="detail in selectedTransactionDetail.details" :key="detail.id">
                <td class="px-3 py-2">{{ detail.product_name_snapshot }}</td>
                <td class="px-3 py-2 text-right">{{ detail.quantity }}</td>
                <td class="px-3 py-2 text-right">{{ formatCurrency(detail.subtotal) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <div class="rounded-lg border border-slate-200 p-3">
            <p class="mb-2 text-xs font-semibold uppercase text-slate-500">Pembayaran</p>
            <p v-if="!selectedTransactionDetail.payments?.length" class="text-sm text-slate-500">Tidak ada data pembayaran rinci.</p>
            <ul v-else class="space-y-1 text-sm">
              <li v-for="payment in selectedTransactionDetail.payments" :key="payment.id" class="flex justify-between">
                <span>{{ payment.payment_method }}</span>
                <span class="font-semibold">{{ formatCurrency(payment.amount) }}</span>
              </li>
            </ul>
          </div>
          <div class="rounded-lg border border-slate-200 p-3">
            <p class="mb-2 text-xs font-semibold uppercase text-slate-500">Refund</p>
            <p v-if="!selectedTransactionDetail.refunds?.length" class="text-sm text-slate-500">Belum ada refund.</p>
            <ul v-else class="space-y-1 text-sm">
              <li v-for="refund in selectedTransactionDetail.refunds" :key="refund.id" class="flex justify-between">
                <span>{{ refund.reason || 'Tanpa alasan' }}</span>
                <span class="font-semibold">{{ formatCurrency(refund.refund_total) }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div v-if="showVoidModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
    <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-800">Void Transaksi</h2>
        <button class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" @click="closeVoidModal">Tutup</button>
      </div>

      <p v-if="voidTransactionTarget" class="mb-3 text-sm text-slate-500">Invoice: {{ voidTransactionTarget.invoice_number }}</p>

      <label class="block">
        <span class="mb-1 block text-sm text-slate-600">Alasan Void</span>
        <textarea v-model="voidReason" rows="3" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="Contoh: pesanan batal" />
      </label>

      <div v-if="requiresManagerApproval" class="mt-3 rounded border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
        <p>{{ approvalSummary }}</p>
        <button class="mt-2 rounded border border-amber-300 bg-white px-2 py-1 font-semibold text-amber-800 hover:bg-amber-100" @click="goToManagerApproval">
          Buka Approval Manager
        </button>
      </div>

      <button
        class="mt-4 w-full rounded-lg bg-rose-600 py-2.5 text-sm font-semibold text-white hover:bg-rose-500 disabled:opacity-50"
        :disabled="processingVoid"
        @click="submitVoid"
      >
        {{ processingVoid ? 'Memproses...' : 'Konfirmasi Void' }}
      </button>
    </div>
  </div>

  <div v-if="showRefundModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
    <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold text-slate-800">Refund Transaksi</h2>
        <button class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" @click="closeRefundModal">Tutup</button>
      </div>

      <p v-if="refundTransaction" class="mb-4 text-sm text-slate-500">Invoice: {{ refundTransaction.invoice_number }}</p>

      <div v-if="processingRefund" class="py-6 text-center text-sm text-slate-500">Memuat detail...</div>

      <div v-else-if="refundDetail" class="space-y-4">
        <div class="rounded-xl border border-slate-200">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-3 py-2">Item</th>
                <th class="px-3 py-2 text-right">Qty Jual</th>
                <th class="px-3 py-2 text-right">Refund Qty</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="detail in refundDetail.details" :key="detail.id">
                <td class="px-3 py-2">{{ detail.product_name_snapshot }}</td>
                <td class="px-3 py-2 text-right">{{ detail.quantity }}</td>
                <td class="px-3 py-2 text-right">
                  <input
                    v-model.number="refundQtyMap[detail.id]"
                    type="number"
                    min="0"
                    :max="detail.quantity"
                    class="w-24 rounded border border-slate-300 px-2 py-1 text-right text-sm"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <label class="block">
          <span class="mb-1 block text-sm text-slate-600">Alasan Refund</span>
          <textarea v-model="refundReason" rows="2" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="Contoh: produk rusak" />
        </label>

        <div v-if="requiresManagerApproval" class="rounded border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
          <p>{{ approvalSummary }}</p>
          <button class="mt-2 rounded border border-amber-300 bg-white px-2 py-1 font-semibold text-amber-800 hover:bg-amber-100" @click="goToManagerApproval">
            Buka Approval Manager
          </button>
        </div>

        <button
          class="w-full rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
          :disabled="processingRefund"
          @click="submitRefund"
        >
          {{ processingRefund ? 'Memproses...' : 'Proses Refund' }}
        </button>
      </div>
    </div>
  </div>
</template>
