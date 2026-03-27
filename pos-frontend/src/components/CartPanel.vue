<script setup>
defineProps({
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
  discount: {
    type: Number,
    default: 0,
  },
  tax: {
    type: Number,
    default: 0,
  },
  cashReceived: {
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
  'update:paymentMethod',
  'update:discount',
  'update:tax',
  'update:cashReceived',
])

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0))
}
</script>

<template>
  <section class="rounded-2xl border border-slate-800 bg-gradient-to-b from-slate-900 to-slate-950 p-5 text-slate-100 shadow-sm">
    <h2 class="text-xl font-bold">Keranjang</h2>
    <p class="mt-1 text-sm text-slate-300">{{ itemCount }} item</p>

    <div class="mt-4 space-y-3">
      <div
        v-for="item in items"
        :key="item.product_id"
        class="rounded-lg border border-slate-700 bg-slate-800 p-3"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="font-medium">{{ item.name }}</p>
            <p class="text-xs text-slate-400">{{ item.sku }}</p>
          </div>
          <button class="text-xs text-rose-300 hover:text-rose-200" @click="emit('remove-item', item.product_id)">
            Hapus
          </button>
        </div>

        <div class="mt-2 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <button class="rounded bg-slate-700 px-2 py-1" @click="emit('decrement-item', item.product_id)">
              -
            </button>
            <span class="w-6 text-center">{{ item.quantity }}</span>
            <button
              class="rounded bg-slate-700 px-2 py-1 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="item.quantity >= item.stock"
              @click="emit('increment-item', item.product_id)"
            >
              +
            </button>
          </div>
          <p class="font-semibold">{{ formatCurrency(item.subtotal) }}</p>
        </div>

        <p class="mt-1 text-xs text-slate-400">Maks stok: {{ item.stock }}</p>
      </div>

      <p v-if="items.length === 0" class="text-sm text-slate-400">Belum ada item dipilih.</p>
    </div>

    <div class="mt-5 space-y-3 border-t border-slate-700 pt-4 text-sm">
      <label class="block">
        <span class="mb-1 block text-slate-300">Metode Pembayaran</span>
        <select
          :value="paymentMethod"
          class="w-full rounded border border-slate-600 bg-slate-800 p-2"
          @change="emit('update:paymentMethod', $event.target.value)"
        >
          <option value="cash">Cash</option>
          <option value="qris">QRIS</option>
          <option value="debit">Debit</option>
        </select>
      </label>

      <label class="block">
        <span class="mb-1 block text-slate-300">Diskon</span>
        <input
          :value="discount"
          type="number"
          min="0"
          class="w-full rounded border border-slate-600 bg-slate-800 p-2"
          @input="emit('update:discount', Number($event.target.value))"
        />
      </label>

      <label class="block">
        <span class="mb-1 block text-slate-300">Pajak</span>
        <input
          :value="tax"
          type="number"
          min="0"
          class="w-full rounded border border-slate-600 bg-slate-800 p-2"
          @input="emit('update:tax', Number($event.target.value))"
        />
      </label>

      <label v-if="paymentMethod === 'cash'" class="block">
        <span class="mb-1 block text-slate-300">Uang Diterima</span>
        <input
          :value="cashReceived"
          type="number"
          min="0"
          class="w-full rounded border border-slate-600 bg-slate-800 p-2"
          @input="emit('update:cashReceived', Number($event.target.value))"
        />
      </label>

      <div class="space-y-1 border-t border-slate-700 pt-3">
        <p class="flex justify-between"><span>Subtotal</span><span>{{ formatCurrency(subtotal) }}</span></p>
        <p class="flex justify-between"><span>Grand Total</span><span>{{ formatCurrency(grandTotal) }}</span></p>
        <p v-if="paymentMethod === 'cash'" class="flex justify-between">
          <span>Kembalian</span><span>{{ formatCurrency(cashChange) }}</span>
        </p>
      </div>

      <button
        class="w-full rounded-lg bg-slate-600 px-3 py-2.5 text-sm font-medium text-slate-200 hover:bg-slate-500 disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="items.length === 0"
        @click="emit('hold')"
      >
        ⏸ Tahan Order
      </button>

      <button
        class="w-full rounded-lg bg-emerald-500 px-3 py-3 font-semibold text-emerald-950 disabled:cursor-not-allowed disabled:bg-emerald-800 disabled:text-emerald-300"
        :disabled="loadingCheckout || items.length === 0"
        @click="emit('checkout')"
      >
        {{ loadingCheckout ? 'Memproses...' : 'Bayar Sekarang' }}
      </button>
    </div>
  </section>
</template>
