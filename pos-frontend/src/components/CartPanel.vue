<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  itemCount: {
    type: Number,
    default: 0,
  },
  subtotal: {
    type: Number,
    default: 0,
  },
  grandTotal: {
    type: Number,
    default: 0,
  },
  cashChange: {
    type: Number,
    default: 0,
  },
  loadingCheckout: {
    type: Boolean,
    default: false,
  },
  paymentMethod: {
    type: String,
    default: 'cash',
  },
  customerName: {
    type: String,
    default: '',
  },
  enableSplitPayment: {
    type: Boolean,
    default: false,
  },
  payments: {
    type: Array,
    default: () => [],
  },
  discount: {
    type: Number,
    default: 0,
  },
  discountAmount: {
    type: Number,
    default: 0,
  },
  tax: {
    type: Number,
    default: 0,
  },
  taxAmount: {
    type: Number,
    default: 0,
  },
  cashReceived: {
    type: Number,
    default: 0,
  },
  totalPaid: {
    type: Number,
    default: 0,
  },
  remainingDue: {
    type: Number,
    default: 0,
  },
  selectedCustomer: {
    type: Object,
    default: null,
  },
  customerQuery: {
    type: String,
    default: '',
  },
  customerResults: {
    type: Array,
    default: () => [],
  },
  searchingCustomer: {
    type: Boolean,
    default: false,
  },
  pointsToRedeem: {
    type: Number,
    default: 0,
  },
  pointsDiscountAmount: {
    type: Number,
    default: 0,
  },
  promoCodeInput: {
    type: String,
    default: '',
  },
  appliedPromo: {
    type: Object,
    default: null,
  },
  promoError: {
    type: String,
    default: '',
  },
  applyingPromo: {
    type: Boolean,
    default: false,
  },
  promoDiscountAmount: {
    type: Number,
    default: 0,
  },
})

const emit = defineEmits([
  'remove-item',
  'increment-item',
  'decrement-item',
  'checkout',
  'hold',
  'add-payment-row',
  'remove-payment-row',
  'update-payment-row',
  'update:payment-method',
  'update:customer-name',
  'update:enable-split-payment',
  'update:discount',
  'update:cash-received',
  'search-customer',
  'select-customer',
  'clear-customer',
  'create-customer',
  'update:points-to-redeem',
  'update:promo-code-input',
  'apply-promo',
  'clear-promo',
])

const showNewCustomerForm = ref(false)
const newCustomerName = ref('')
const newCustomerPhone = ref('')

function submitNewCustomer() {
  if (!newCustomerName.value.trim() || !newCustomerPhone.value.trim()) return
  emit('create-customer', { name: newCustomerName.value.trim(), phone: newCustomerPhone.value.trim() })
  newCustomerName.value = ''
  newCustomerPhone.value = ''
  showNewCustomerForm.value = false
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0))
}

const showDiscountInput = ref(false)
const showManualCashInput = ref(false)
const discountInput = ref('0.00')

const quickCashOptions = computed(() => {
  const total = Math.max(0, Number(props.grandTotal || 0))
  if (total <= 0) return []

  const roundUpTo = (value, factor) => Math.ceil(value / factor) * factor

  // Bigger-note step scales with the total (pecahan 20rb/50rb/100rb) instead of a flat
  // +10.000, so a round total like Rp 1.000.000 still gets a realistic Rp 1.100.000 /
  // Rp 1.200.000 suggestion rather than collapsing into an oddly-specific Rp 1.010.000.
  const bigStep = total < 100000 ? 20000 : total < 500000 ? 50000 : 100000

  const candidates = [
    total,
    roundUpTo(total, 1000),
    roundUpTo(total, 5000),
    roundUpTo(total, 10000),
    roundUpTo(total + 1, bigStep),
    roundUpTo(total + 1, bigStep) + bigStep,
  ]

  return [...new Set(candidates)].filter((value) => value > 0).sort((a, b) => a - b).slice(0, 5)
})

function applyCashAmount(amount) {
  emit('update:cash-received', Number(amount || 0))
}

function clearDiscount() {
  emit('update:discount', 0)
  discountInput.value = '0.00'
  showDiscountInput.value = false
}

function parsePercentInput(value) {
  const raw = Number.parseFloat(value)
  if (!Number.isFinite(raw)) {
    return 0
  }

  const clamped = Math.max(0, Math.min(100, raw))
  return Math.round(clamped * 100) / 100
}

function updateDiscountPercent(value) {
  const normalized = parsePercentInput(value)
  discountInput.value = value
  emit('update:discount', normalized)
}

function openDiscountInput() {
  showDiscountInput.value = true
  discountInput.value = Number(props.discount || 0).toFixed(2)
}

function formatDiscountOnBlur() {
  const normalized = parsePercentInput(discountInput.value)
  discountInput.value = normalized.toFixed(2)
  emit('update:discount', normalized)
}
</script>

<template>
  <section class="rounded-xl bg-surface p-3 text-ink md:p-4">
    <h2 class="text-3xl font-semibold tracking-tight text-ink">{{ t('cartPanel.title') }}</h2>
    <p class="mt-1 text-xs text-ink-faint">{{ t('cartPanel.itemsSelected', { count: itemCount }) }}</p>

    <div class="mt-3 space-y-1.5">
      <div
        v-for="item in items"
        :key="item.product_id"
        class="flex items-center gap-2 rounded-lg border border-line-soft bg-surface-2 py-1.5 pl-2.5 pr-2"
      >
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-ink">{{ item.name }}</p>
          <p class="text-xs text-ink-faint">{{ formatCurrency(item.price) }}</p>
        </div>

        <div class="flex shrink-0 items-center gap-1">
          <button class="grid h-6 w-6 place-items-center rounded border border-line bg-surface text-ink" @click="emit('decrement-item', item.product_id)">
            -
          </button>
          <span class="w-4 text-center text-xs font-semibold text-ink">{{ item.quantity }}</span>
          <button
            class="grid h-6 w-6 place-items-center rounded border border-line bg-surface text-ink disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="item.quantity >= item.stock"
            @click="emit('increment-item', item.product_id)"
          >
            +
          </button>
        </div>

        <p class="w-[4.5rem] shrink-0 text-right text-xs font-semibold text-ink">{{ formatCurrency(item.subtotal) }}</p>

        <button class="shrink-0 text-ink-faint hover:text-rose-600" :aria-label="t('cartPanel.removeItem')" @click="emit('remove-item', item.product_id)">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m3 0l-.6 12.2a2 2 0 01-2 1.8H8.6a2 2 0 01-2-1.8L6 7h12z" />
          </svg>
        </button>
      </div>

      <p v-if="items.length === 0" class="text-sm text-ink-faint">{{ t('cartPanel.empty') }}</p>
    </div>

    <div class="mt-5 space-y-3 border-t border-line-soft pt-4 text-xs">
      <!-- Gold is reserved for the loyalty/rewards feature only (DESIGN.md: never a general accent). -->
      <div v-if="selectedCustomer" class="rounded-lg border border-gold-400 bg-gold-100 p-2.5">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-sm font-semibold text-ink">{{ selectedCustomer.name }}</p>
            <p class="text-xs text-gold-700">{{ selectedCustomer.phone }} &middot; {{ selectedCustomer.points }} {{ t('cartPanel.pointsUnit') }}</p>
          </div>
          <button type="button" class="text-xs text-gold-700 hover:text-ink" @click="emit('clear-customer')">{{ t('cartPanel.change') }}</button>
        </div>

        <label v-if="selectedCustomer.points > 0" class="mt-2 block">
          <span class="mb-1 block text-xs font-medium text-gold-700">{{ t('cartPanel.redeemPoints', { amount: 100 }) }}</span>
          <input
            :value="pointsToRedeem"
            type="number"
            min="0"
            :max="selectedCustomer.points"
            class="w-full rounded border border-gold-400 bg-surface p-1.5 text-xs"
            placeholder="0"
            @input="emit('update:points-to-redeem', Math.max(0, Number($event.target.value) || 0))"
          />
        </label>
      </div>

      <div v-else class="space-y-1.5">
        <label class="block">
          <span class="mb-1 block text-xs font-medium text-ink">{{ t('cartPanel.customerNameLabel') }}</span>
          <input
            :value="customerName || customerQuery"
            type="text"
            maxlength="100"
            class="w-full rounded-lg border border-line bg-surface p-2 text-xs text-ink outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
            :placeholder="t('cartPanel.customerNamePlaceholder')"
            @input="emit('update:customer-name', $event.target.value); emit('search-customer', $event.target.value)"
          />
        </label>

        <div v-if="searchingCustomer" class="text-[11px] text-ink-faint">{{ t('cartPanel.searchingCustomer') }}</div>

        <div v-if="customerResults.length > 0" class="max-h-28 space-y-1 overflow-y-auto rounded-lg border border-line-soft bg-surface p-1">
          <button
            v-for="result in customerResults"
            :key="result.id"
            type="button"
            class="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-xs hover:bg-surface-2"
            @click="emit('select-customer', result)"
          >
            <span>{{ result.name }} <span class="text-ink-faint">{{ result.phone }}</span></span>
            <span class="text-gold-700">{{ result.points }} {{ t('cartPanel.pointsUnit') }}</span>
          </button>
        </div>

        <button
          v-if="customerQuery && customerResults.length === 0 && !searchingCustomer && !showNewCustomerForm"
          type="button"
          class="text-xs font-medium text-brand-700 hover:text-brand-800"
          @click="showNewCustomerForm = true; newCustomerName = customerQuery"
        >
          {{ t('cartPanel.registerNewCustomer', { query: customerQuery }) }}
        </button>

        <div v-if="showNewCustomerForm" class="space-y-1.5 rounded-lg border border-line-soft bg-surface-2 p-2">
          <input v-model="newCustomerName" type="text" :placeholder="t('cartPanel.newCustomerNamePlaceholder')" class="w-full rounded border border-line bg-surface p-1.5 text-xs" />
          <input v-model="newCustomerPhone" type="text" :placeholder="t('cartPanel.newCustomerPhonePlaceholder')" class="w-full rounded border border-line bg-surface p-1.5 text-xs" />
          <div class="flex gap-2">
            <button type="button" class="flex-1 rounded bg-brand-700 px-2 py-1.5 text-xs font-semibold text-white hover:bg-brand-800" @click="submitNewCustomer">{{ t('cartPanel.save') }}</button>
            <button type="button" class="text-xs text-ink-faint hover:text-ink" @click="showNewCustomerForm = false">{{ t('cartPanel.cancel') }}</button>
          </div>
        </div>
      </div>

      <label class="block">
        <span class="mb-1 block text-xs font-medium text-ink">{{ t('cartPanel.paymentMethod') }}</span>
        <select
          :value="paymentMethod"
          class="w-full rounded-lg border border-line bg-surface p-2 text-xs text-ink outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
          :disabled="enableSplitPayment"
          @change="emit('update:payment-method', $event.target.value)"
        >
          <option value="cash">Cash</option>
          <option value="qris">QRIS</option>
          <option value="debit">Debit</option>
          <option value="credit_card">Credit Card</option>
          <option value="e_wallet">E-Wallet</option>
          <option value="bank_transfer">Bank Transfer</option>
        </select>
      </label>

      <label class="block">
        <span class="mb-1 block text-xs font-medium text-ink">{{ t('cartPanel.splitPayment') }}</span>
        <span class="flex cursor-pointer items-center gap-2 text-xs text-ink">
          <input
            :checked="enableSplitPayment"
            type="checkbox"
            class="h-4 w-4 rounded border-line"
            @change="emit('update:enable-split-payment', $event.target.checked)"
          />
          {{ t('cartPanel.enableSplitPayment') }}
        </span>
      </label>

      <div v-if="enableSplitPayment" class="space-y-2 rounded-lg border border-line-soft bg-surface-2 p-2.5">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">{{ t('cartPanel.paymentBreakdown') }}</p>

        <div
          v-for="(row, index) in payments"
          :key="index"
          class="grid grid-cols-[1fr_1fr_auto] gap-2"
        >
          <select
            :value="row.payment_method"
            class="rounded border border-line bg-surface p-1.5 text-xs"
            @change="emit('update-payment-row', index, { payment_method: $event.target.value })"
          >
            <option value="cash">Cash</option>
            <option value="qris">QRIS</option>
            <option value="debit">Debit</option>
            <option value="credit_card">Credit Card</option>
            <option value="e_wallet">E-Wallet</option>
            <option value="bank_transfer">Bank Transfer</option>
          </select>

          <input
            :value="row.amount"
            type="number"
            min="0"
            class="rounded border border-line bg-surface p-1.5 text-xs"
            :placeholder="t('cartPanel.amountPlaceholder')"
            @input="emit('update-payment-row', index, { amount: Number($event.target.value) })"
          />

          <button
            class="rounded border border-rose-300 bg-rose-50 px-2 text-xs text-rose-600 hover:bg-rose-100"
            @click="emit('remove-payment-row', index)"
          >
            {{ t('cartPanel.remove') }}
          </button>

          <input
            :value="row.reference_number"
            type="text"
            class="col-span-3 rounded border border-line bg-surface p-1.5 text-xs"
            :placeholder="t('cartPanel.referenceNumberPlaceholder')"
            @input="emit('update-payment-row', index, { reference_number: $event.target.value })"
          />
        </div>

        <button
          class="w-full rounded border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink hover:bg-surface-2"
          @click="emit('add-payment-row')"
        >
          + {{ t('cartPanel.addPaymentMethod') }}
        </button>
      </div>

      <div class="space-y-1.5">
        <div v-if="appliedPromo" class="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-2 text-xs">
          <span class="flex items-center gap-1.5 font-semibold text-emerald-800">
            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20.59 13.41L11 3.83A2 2 0 009.59 3.24L4 3a1 1 0 00-1 1l.24 5.59a2 2 0 00.59 1.41l9.58 9.58a2 2 0 002.83 0l4.35-4.35a2 2 0 000-2.82zM7 8a1 1 0 110-2 1 1 0 010 2z" />
            </svg>
            {{ t('cartPanel.promoApplied', { code: appliedPromo.code }) }}
          </span>
          <button type="button" class="text-emerald-700 hover:text-emerald-900" @click="emit('clear-promo')">{{ t('cartPanel.remove') }}</button>
        </div>
        <div v-else class="flex gap-2">
          <input
            :value="promoCodeInput"
            type="text"
            :placeholder="t('cartPanel.promoCodePlaceholder')"
            class="flex-1 rounded-lg border border-line bg-surface p-2 text-xs uppercase text-ink outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
            @input="emit('update:promo-code-input', $event.target.value)"
            @keydown.enter.prevent="emit('apply-promo')"
          />
          <button
            type="button"
            :disabled="applyingPromo || !promoCodeInput"
            class="rounded-lg border border-line bg-surface px-3 text-xs font-semibold text-ink hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-50"
            @click="emit('apply-promo')"
          >
            {{ applyingPromo ? '...' : t('cartPanel.apply') }}
          </button>
        </div>
        <p v-if="promoError" class="text-[11px] text-rose-600 dark:text-rose-400">{{ promoError }}</p>
      </div>

      <div class="rounded-lg border border-line-soft bg-surface-2 p-3">
        <h3 class="mb-2 text-xl font-semibold tracking-tight text-ink">{{ t('cartPanel.priceSummary') }}</h3>

        <div class="space-y-1 border-b border-line-soft pb-3">
          <p class="flex justify-between"><span class="text-ink-soft">{{ t('cartPanel.subtotal') }}</span><span class="font-medium">{{ formatCurrency(subtotal) }}</span></p>
          <p v-if="discount > 0" class="flex justify-between"><span class="text-emerald-700">{{ t('cartPanel.discountLabel', { percent: Number(discount).toFixed(2) }) }}</span><span class="font-medium text-emerald-700">- {{ formatCurrency(discountAmount) }}</span></p>
          <p v-if="promoDiscountAmount > 0" class="flex justify-between"><span class="text-emerald-700">{{ t('cartPanel.promoLabel', { code: appliedPromo?.code }) }}</span><span class="font-medium text-emerald-700">- {{ formatCurrency(promoDiscountAmount) }}</span></p>
          <p v-if="tax > 0" class="flex justify-between"><span class="text-amber-700">{{ t('cartPanel.taxLabel', { percent: Number(tax).toFixed(2) }) }}</span><span class="font-medium text-amber-700">+ {{ formatCurrency(taxAmount) }}</span></p>
          <p v-if="pointsDiscountAmount > 0" class="flex justify-between"><span class="text-gold-700">{{ t('cartPanel.pointsDeduction') }}</span><span class="font-medium text-gold-700">- {{ formatCurrency(pointsDiscountAmount) }}</span></p>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2.5 text-xs">
          <button
            v-if="!showDiscountInput"
            type="button"
            class="font-medium text-brand-700 hover:text-brand-800"
            @click="openDiscountInput"
          >
            {{ t('cartPanel.addDiscount') }}
          </button>
          <div v-else class="flex items-center gap-2">
            <input
              v-model="discountInput"
              type="text"
              inputmode="decimal"
              :placeholder="t('cartPanel.discountPercentPlaceholder')"
              class="w-28 rounded border border-line bg-surface px-2.5 py-1.5 text-xs"
              @input="updateDiscountPercent($event.target.value)"
              @blur="formatDiscountOnBlur"
            />
            <button type="button" class="text-xs text-ink-faint hover:text-ink" @click="clearDiscount">{{ t('cartPanel.removeLower') }}</button>
          </div>

          <p v-if="showDiscountInput" class="text-[11px] text-ink-faint">{{ t('cartPanel.maxPercent') }}</p>

          <RouterLink to="/settings" class="font-medium text-brand-700 hover:text-brand-800">
            {{ t('cartPanel.taxManagedFromSettings') }}
          </RouterLink>
        </div>

        <div class="mt-3 border-t border-line-soft pt-2.5">
          <p class="flex items-end justify-between">
            <span class="text-ink-soft">{{ t('cartPanel.grandTotal') }}</span>
            <span class="text-2xl font-semibold tracking-tight text-ink">{{ formatCurrency(grandTotal) }}</span>
          </p>
        </div>

        <div v-if="paymentMethod === 'cash' && !enableSplitPayment" class="mt-3 rounded-lg border border-line-soft bg-surface p-2.5">
          <div class="mb-2 flex items-center justify-between">
            <span class="text-xs font-medium text-ink">{{ t('cartPanel.cashReceived') }}</span>
            <span class="text-xs font-semibold text-ink">{{ formatCurrency(cashReceived) }}</span>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="option in quickCashOptions"
              :key="option"
              type="button"
              class="rounded border px-2.5 py-1.5 text-left text-xs font-medium transition"
              :class="Number(cashReceived) === option ? 'border-brand-300 bg-brand-50 text-brand-800' : 'border-line-soft bg-surface text-ink hover:bg-surface-2'"
              @click="applyCashAmount(option)"
            >
              {{ formatCurrency(option) }}
            </button>
          </div>

          <button
            type="button"
            class="mt-2 text-sm font-medium text-brand-700 hover:text-brand-800"
            @click="showManualCashInput = !showManualCashInput"
          >
            {{ t('cartPanel.otherAmount') }}
          </button>

          <input
            v-if="showManualCashInput"
            :value="cashReceived"
            type="number"
            min="0"
            class="mt-2 w-full rounded border border-line bg-surface px-2.5 py-1.5 text-xs"
            :placeholder="t('cartPanel.manualAmountPlaceholder')"
            @input="emit('update:cash-received', Number($event.target.value))"
          />

          <p class="mt-2 flex justify-between text-xs">
            <span class="text-ink-soft">{{ t('cartPanel.changeDue') }}</span>
            <span class="font-semibold text-ink">{{ formatCurrency(cashChange) }}</span>
          </p>
        </div>

        <div v-if="enableSplitPayment" class="mt-3 space-y-1 border-t border-line-soft pt-2.5 text-xs">
          <p class="flex justify-between"><span class="text-ink-soft">{{ t('cartPanel.totalPaid') }}</span><span>{{ formatCurrency(totalPaid) }}</span></p>
          <p class="flex justify-between"><span class="text-ink-soft">{{ t('cartPanel.remainingDue') }}</span><span>{{ formatCurrency(remainingDue) }}</span></p>
        </div>
      </div>

      <button
        class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-xs font-medium text-ink hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="items.length === 0"
        @click="emit('hold')"
      >
        {{ t('cartPanel.holdOrder') }}
      </button>

      <button
        class="w-full rounded-full bg-brand-600 active:scale-95 px-3 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-500 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-ink-faint"
        :disabled="loadingCheckout || items.length === 0"
        @click="emit('checkout')"
      >
        {{ loadingCheckout ? t('cartPanel.processing') : t('cartPanel.payNow') }}
      </button>
    </div>
  </section>
</template>
