<script setup>
import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useCartStore } from '../stores/cart'

const cart = useCartStore()
const { heldOrders } = storeToRefs(cart)
const router = useRouter()

const notification = ref('')

function recallOrder(orderId) {
  const result = cart.recallOrder(orderId)
  if (result?.ok === false) {
    notification.value = result.message
    return
  }
  // Navigate to cashier to process the recalled order
  router.push('/')
}

function removeOrder(orderId) {
  cart.removeHeldOrder(orderId)
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))
}

function formatTime(iso) {
  return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <div>
    <div class="mb-5 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Order List</h1>
        <p class="text-sm text-slate-500">Pesanan yang di-tahan (On Hold)</p>
      </div>
      <span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-700">
        {{ heldOrders.length }} order tertahan
      </span>
    </div>

    <div v-if="notification" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
      {{ notification }}
    </div>

    <div v-if="heldOrders.length === 0" class="rounded-2xl border-2 border-dashed border-slate-200 bg-white py-16 text-center">
      <span class="text-4xl">📋</span>
      <p class="mt-3 text-slate-500">Tidak ada order yang sedang ditahan.</p>
      <p class="text-xs text-slate-400">Gunakan tombol "Tahan Order" di halaman Kasir.</p>
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <div
        v-for="order in heldOrders"
        :key="order.id"
        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
      >
        <div class="mb-3 flex items-start justify-between">
          <div>
            <p class="font-semibold text-slate-800">{{ order.label }}</p>
            <p class="text-xs text-slate-400">{{ formatTime(order.timestamp) }}</p>
          </div>
          <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-600">On Hold</span>
        </div>

        <ul class="mb-3 space-y-1.5 text-sm">
          <li v-for="item in order.items" :key="item.product_id" class="flex justify-between text-slate-600">
            <span>{{ item.name }} × {{ item.quantity }}</span>
            <span>{{ formatCurrency(item.subtotal) }}</span>
          </li>
        </ul>

        <div class="mb-4 border-t border-slate-100 pt-2 text-right">
          <span class="text-sm text-slate-500">Total </span>
          <span class="font-bold text-slate-900">{{ formatCurrency(order.subtotal) }}</span>
        </div>

        <div class="flex gap-2">
          <button
            class="flex-1 rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
            @click="recallOrder(order.id)"
          >
            📲 Panggil
          </button>
          <button
            class="rounded-lg border border-rose-200 px-3 py-2 text-sm text-rose-500 hover:bg-rose-50"
            @click="removeOrder(order.id)"
          >
            Hapus
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
