<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../services/api'
import { useEscToClose } from '../composables/useEscToClose'

const { t } = useI18n()

// Bills = transactions that are unpaid / pending
const bills = ref([])
const loading = ref(false)
const error = ref('')
const storeName = ref(t('bills.defaultStoreName'))
const SETTINGS_KEY = 'pos_store_settings'

const payingBill = ref(null)
const payForm = ref({ payment_method: 'cash', amount: 0, reference_number: '' })
const paySubmitting = ref(false)
const payError = ref('')

useEscToClose(payingBill, () => { payingBill.value = null })

function loadStoreName() {
  try {
    const raw = localStorage.getItem(SETTINGS_KEY)
    if (!raw) return

    const parsed = JSON.parse(raw)
    storeName.value = String(parsed?.store_name || t('bills.defaultStoreName'))
  } catch {
    storeName.value = t('bills.defaultStoreName')
  }
}

async function loadBills() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/v1/transactions', { params: { payment_status: 'unpaid,partial', is_voided: false, page: 1, per_page: 50 } })
    bills.value = res.data.data ?? []
  } catch {
    error.value = t('bills.loadError')
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

function resolveReceivedAmount(bill) {
  if (Array.isArray(bill?.payments) && bill.payments.length > 0) {
    return bill.payments.reduce((sum, payment) => sum + Number(payment.amount || 0), 0)
  }

  return Number(bill?.cash_received ?? bill?.amount_paid ?? 0)
}

function remainingDue(bill) {
  return Math.max(0, Number(bill.grand_total || 0) - Number(bill.amount_paid || 0))
}

function openPayModal(bill) {
  payingBill.value = bill
  payError.value = ''
  payForm.value = { payment_method: 'cash', amount: remainingDue(bill), reference_number: '' }
}

async function submitPayment() {
  if (!payingBill.value) return

  payError.value = ''
  paySubmitting.value = true
  try {
    await api.post(`/v1/transactions/${payingBill.value.id}/pay`, {
      payment_method: payForm.value.payment_method,
      amount: Number(payForm.value.amount),
      reference_number: payForm.value.reference_number?.trim() || null,
    })
    payingBill.value = null
    await loadBills()
  } catch (e) {
    payError.value = e.response?.data?.message ?? t('bills.payError')
  } finally {
    paySubmitting.value = false
  }
}

onMounted(() => {
  loadStoreName()
  loadBills()
})
</script>

<template>
  <div>
    <div class="mb-5 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-ink">{{ t('bills.title') }}</h1>
        <p class="text-sm font-medium text-ink-soft">{{ storeName }}</p>
        <p class="text-sm text-ink-faint">{{ t('bills.subtitle') }}</p>
      </div>
      <button class="rounded-lg border border-line px-4 py-2 text-sm hover:bg-surface-2" @click="loadBills">
        {{ t('bills.reload') }}
      </button>
    </div>

    <div v-if="error" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>

    <div v-if="loading" class="rounded-2xl bg-surface py-12 text-center text-ink-faint shadow-sm">{{ t('bills.loading') }}</div>

    <div v-else-if="bills.length === 0" class="rounded-2xl border-2 border-dashed border-line-soft bg-surface py-16 text-center">
      <svg class="mx-auto h-10 w-10 text-line" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h10l2 2v14l-2 2H7l-2-2V5l2-2zm3 5h4m-4 4h6m-6 4h5" />
      </svg>
      <p class="mt-3 text-ink-faint">{{ t('bills.empty') }}</p>
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-line-soft bg-surface shadow-sm">
      <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b border-line-soft bg-surface-2 text-left text-xs font-semibold uppercase tracking-wide text-ink-faint">
          <tr>
            <th class="px-4 py-3">{{ t('bills.invoiceCol') }}</th>
            <th class="px-4 py-3">{{ t('bills.dateCol') }}</th>
            <th class="px-4 py-3">{{ t('bills.buyerNameCol') }}</th>
            <th class="px-4 py-3">{{ t('bills.customerCol') }}</th>
            <th class="px-4 py-3 text-right">{{ t('bills.totalCol') }}</th>
            <th class="px-4 py-3 text-right">{{ t('bills.paidCol') }}</th>
            <th class="px-4 py-3 text-right">{{ t('bills.remainingCol') }}</th>
            <th class="px-4 py-3">{{ t('bills.statusCol') }}</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line-soft">
          <tr v-for="bill in bills" :key="bill.id" class="hover:bg-surface-2">
            <td class="px-4 py-3 font-mono font-medium text-brand-600">{{ bill.invoice_number }}</td>
            <td class="px-4 py-3 text-ink-soft">{{ formatDate(bill.created_at) }}</td>
            <td class="px-4 py-3 text-ink-soft">{{ bill.customer_name ?? bill.customer?.name ?? '-' }}</td>
            <td class="px-4 py-3 text-ink-soft">{{ bill.customer?.name ?? '-' }}</td>
            <td class="px-4 py-3 text-right font-semibold">{{ formatCurrency(bill.grand_total) }}</td>
            <td class="px-4 py-3 text-right font-semibold text-ink">{{ formatCurrency(resolveReceivedAmount(bill)) }}</td>
            <td class="px-4 py-3 text-right font-semibold text-rose-600 dark:text-rose-400">{{ formatCurrency(remainingDue(bill)) }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ bill.payment_status }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <button class="rounded-full bg-brand-600 active:scale-95 transition-transform px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-500" @click="openPayModal(bill)">
                {{ t('bills.pay') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <Transition name="fade">
      <div v-if="payingBill" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div role="dialog" aria-modal="true" aria-labelledby="pay-bill-title" class="w-full max-w-sm rounded-2xl bg-surface p-6 shadow-2xl">
          <div class="mb-4 flex items-center justify-between">
            <h2 id="pay-bill-title" class="text-lg font-bold text-ink">{{ t('bills.payModalTitle') }}</h2>
            <button :aria-label="t('bills.close')" class="text-ink-faint hover:text-ink-soft" @click="payingBill = null">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <p class="mb-1 text-sm text-ink-faint">{{ payingBill.invoice_number }}</p>
          <p class="mb-4 text-sm text-ink-soft">{{ t('bills.remainingDue') }} <span class="font-semibold text-rose-600 dark:text-rose-400">{{ formatCurrency(remainingDue(payingBill)) }}</span></p>

          <div v-if="payError" class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ payError }}</div>

          <div class="space-y-3 text-sm">
            <label class="block">
              <span class="mb-1 block text-ink-soft">{{ t('bills.paymentMethod') }}</span>
              <select v-model="payForm.payment_method" class="w-full rounded-lg border border-line px-3 py-2">
                <option value="cash">Cash</option>
                <option value="qris">QRIS</option>
                <option value="debit">Debit</option>
                <option value="credit_card">Credit Card</option>
                <option value="e_wallet">E-Wallet</option>
                <option value="bank_transfer">Bank Transfer</option>
              </select>
            </label>

            <label class="block">
              <span class="mb-1 block text-ink-soft">{{ t('bills.amount') }}</span>
              <input v-model.number="payForm.amount" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2" />
            </label>

            <label class="block">
              <span class="mb-1 block text-ink-soft">{{ t('bills.referenceNumber') }}</span>
              <input v-model="payForm.reference_number" type="text" class="w-full rounded-lg border border-line px-3 py-2" />
            </label>
          </div>

          <div class="mt-5 flex gap-3">
            <button class="flex-1 rounded-lg border border-line py-2.5 text-sm hover:bg-surface-2" @click="payingBill = null">
              {{ t('bills.cancel') }}
            </button>
            <button
              :disabled="paySubmitting"
              class="flex-1 rounded-full bg-brand-600 active:scale-95 transition-transform py-2.5 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50"
              @click="submitPayment"
            >
              {{ paySubmitting ? t('bills.processing') : t('bills.confirmPay') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
