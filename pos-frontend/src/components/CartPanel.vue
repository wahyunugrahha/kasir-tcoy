<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'

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
])

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

  const roundTo = (value, factor) => Math.ceil(value / factor) * factor
  const candidates = [
    total,
    roundTo(total, 1000),
    roundTo(total, 5000),
    roundTo(total, 10000),
    roundTo(total + 10000, 10000),
  ]

  return [...new Set(candidates)].filter((value) => value > 0).sort((a, b) => a - b)
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
  <section class="rounded-xl bg-white p-3 text-slate-800 md:p-4">
    <h2 class="text-3xl font-semibold tracking-tight text-slate-800">Keranjang</h2>
    <p class="mt-1 text-xs text-slate-500">({{ itemCount }} item dipilih)</p>

    <div class="mt-3 space-y-2.5">
      <div
        v-for="item in items"
        :key="item.product_id"
        class="rounded-lg border border-slate-200 bg-slate-50 p-2.5"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-sm font-medium text-slate-800">{{ item.name }}</p>
            <p class="text-xs text-slate-500">{{ item.sku }}</p>
          </div>
          <button class="text-xs text-rose-500 hover:text-rose-600" @click="emit('remove-item', item.product_id)">
            Hapus
          </button>
        </div>

        <div class="mt-1.5 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <button class="rounded border border-slate-300 bg-white px-2 py-0.5 text-slate-700" @click="emit('decrement-item', item.product_id)">
              -
            </button>
            <span class="w-5 text-center text-sm font-medium text-slate-700">{{ item.quantity }}</span>
            <button
              class="rounded border border-slate-300 bg-white px-2 py-0.5 text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="item.quantity >= item.stock"
              @click="emit('increment-item', item.product_id)"
            >
              +
            </button>
          </div>
          <p class="text-sm font-semibold text-slate-800">{{ formatCurrency(item.subtotal) }}</p>
        </div>

        <p class="mt-1 text-xs text-slate-500">Maks stok: {{ item.stock }}</p>
      </div>

      <p v-if="items.length === 0" class="text-sm text-slate-500">Belum ada item dipilih.</p>
    </div>

    <div class="mt-5 space-y-3 border-t border-slate-200 pt-4 text-xs">
      <label class="block">
        <span class="mb-1 block text-xs font-medium text-slate-700">Nama Pembeli</span>
        <input
          :value="customerName"
          type="text"
          maxlength="100"
          class="w-full rounded-lg border border-slate-300 bg-white p-2 text-xs text-slate-700 outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-100"
          placeholder="Masukkan nama pembeli"
          @input="emit('update:customer-name', $event.target.value)"
        />
      </label>

      <label class="block">
        <span class="mb-1 block text-xs font-medium text-slate-700">Metode Pembayaran</span>
        <select
          :value="paymentMethod"
          class="w-full rounded-lg border border-slate-300 bg-white p-2 text-xs text-slate-700 outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-100"
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
        <span class="mb-1 block text-xs font-medium text-slate-700">Split Payment</span>
        <span class="flex cursor-pointer items-center gap-2 text-xs text-slate-700">
          <input
            :checked="enableSplitPayment"
            type="checkbox"
            class="h-4 w-4 rounded border-slate-400"
            @change="emit('update:enable-split-payment', $event.target.checked)"
          />
          Aktifkan multi pembayaran
        </span>
      </label>

      <div v-if="enableSplitPayment" class="space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-2.5">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rincian Pembayaran</p>

        <div
          v-for="(row, index) in payments"
          :key="index"
          class="grid grid-cols-[1fr_1fr_auto] gap-2"
        >
          <select
            :value="row.payment_method"
            class="rounded border border-slate-300 bg-white p-1.5 text-xs"
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
            class="rounded border border-slate-300 bg-white p-1.5 text-xs"
            placeholder="Nominal"
            @input="emit('update-payment-row', index, { amount: Number($event.target.value) })"
          />

          <button
            class="rounded border border-rose-300 bg-rose-50 px-2 text-xs text-rose-600 hover:bg-rose-100"
            @click="emit('remove-payment-row', index)"
          >
            Hapus
          </button>

          <input
            :value="row.reference_number"
            type="text"
            class="col-span-3 rounded border border-slate-300 bg-white p-1.5 text-xs"
            placeholder="No referensi (opsional)"
            @input="emit('update-payment-row', index, { reference_number: $event.target.value })"
          />
        </div>

        <button
          class="w-full rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
          @click="emit('add-payment-row')"
        >
          + Tambah Metode Bayar
        </button>
      </div>

      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
        <h3 class="mb-2 text-xl font-semibold tracking-tight text-slate-800">Ringkasan Harga & Pembayaran</h3>

        <div class="space-y-1 border-b border-slate-200 pb-3">
          <p class="flex justify-between"><span class="text-slate-600">Subtotal</span><span class="font-medium">{{ formatCurrency(subtotal) }}</span></p>
          <p v-if="discount > 0" class="flex justify-between"><span class="text-emerald-700">Diskon ({{ Number(discount).toFixed(2) }}%)</span><span class="font-medium text-emerald-700">- {{ formatCurrency(discountAmount) }}</span></p>
          <p v-if="tax > 0" class="flex justify-between"><span class="text-amber-700">Pajak ({{ Number(tax).toFixed(2) }}%)</span><span class="font-medium text-amber-700">+ {{ formatCurrency(taxAmount) }}</span></p>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2.5 text-xs">
          <button
            v-if="!showDiscountInput"
            type="button"
            class="font-medium text-cyan-700 hover:text-cyan-800"
            @click="openDiscountInput"
          >
            + Tambah Diskon
          </button>
          <div v-else class="flex items-center gap-2">
            <input
              v-model="discountInput"
              type="text"
              inputmode="decimal"
              placeholder="Diskon %"
              class="w-28 rounded border border-slate-300 bg-white px-2.5 py-1.5 text-xs"
              @input="updateDiscountPercent($event.target.value)"
              @blur="formatDiscountOnBlur"
            />
            <button type="button" class="text-xs text-slate-500 hover:text-slate-700" @click="clearDiscount">hapus</button>
          </div>

          <p v-if="showDiscountInput" class="text-[11px] text-slate-500">Maks 100%</p>

          <RouterLink to="/settings" class="font-medium text-cyan-700 hover:text-cyan-800">
            Pajak diatur dari Pengaturan
          </RouterLink>
        </div>

        <div class="mt-3 border-t border-slate-200 pt-2.5">
          <p class="flex items-end justify-between">
            <span class="text-slate-600">Grand Total</span>
            <span class="text-2xl font-semibold tracking-tight text-slate-900">{{ formatCurrency(grandTotal) }}</span>
          </p>
        </div>

        <div v-if="paymentMethod === 'cash' && !enableSplitPayment" class="mt-3 rounded-lg border border-slate-200 bg-white p-2.5">
          <div class="mb-2 flex items-center justify-between">
            <span class="text-xs font-medium text-slate-700">Uang Diterima</span>
            <span class="text-xs font-semibold text-slate-700">{{ formatCurrency(cashReceived) }}</span>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="option in quickCashOptions"
              :key="option"
              type="button"
              class="rounded border px-2.5 py-1.5 text-left text-xs font-medium transition"
              :class="Number(cashReceived) === option ? 'border-cyan-300 bg-cyan-50 text-cyan-800' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
              @click="applyCashAmount(option)"
            >
              {{ formatCurrency(option) }}
            </button>
          </div>

          <button
            type="button"
            class="mt-2 text-sm font-medium text-cyan-700 hover:text-cyan-800"
            @click="showManualCashInput = !showManualCashInput"
          >
            Nominal Lainnya
          </button>

          <input
            v-if="showManualCashInput"
            :value="cashReceived"
            type="number"
            min="0"
            class="mt-2 w-full rounded border border-slate-300 bg-white px-2.5 py-1.5 text-xs"
            placeholder="Masukkan nominal manual"
            @input="emit('update:cash-received', Number($event.target.value))"
          />

          <p class="mt-2 flex justify-between text-xs">
            <span class="text-slate-600">Kembalian</span>
            <span class="font-semibold text-slate-800">{{ formatCurrency(cashChange) }}</span>
          </p>
        </div>

        <div v-if="enableSplitPayment" class="mt-3 space-y-1 border-t border-slate-200 pt-2.5 text-xs">
          <p class="flex justify-between"><span class="text-slate-600">Total Dibayar</span><span>{{ formatCurrency(totalPaid) }}</span></p>
          <p class="flex justify-between"><span class="text-slate-600">Sisa Tagihan</span><span>{{ formatCurrency(remainingDue) }}</span></p>
        </div>
      </div>

      <button
        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="items.length === 0"
        @click="emit('hold')"
      >
        Tahan Order
      </button>

      <button
        class="w-full rounded-lg bg-cyan-700 px-3 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-800 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
        :disabled="loadingCheckout || items.length === 0"
        @click="emit('checkout')"
      >
        {{ loadingCheckout ? 'Memproses...' : 'Bayar Sekarang' }}
      </button>
    </div>
  </section>
</template>
