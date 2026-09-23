<script setup>
import { onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCartStore } from '../stores/cart'

const { t } = useI18n()
const cart = useCartStore()
const { heldOrders, loadingHeldOrders } = storeToRefs(cart)
const router = useRouter()

const notification = ref('')

async function recallOrder(orderId) {
  const result = await cart.recallOrder(orderId)
  if (result?.ok === false) {
    notification.value = result.message
    return
  }
  // Navigate to cashier to process the recalled order
  router.push('/')
}

async function removeOrder(orderId) {
  const result = await cart.removeHeldOrder(orderId)
  if (result?.ok === false) {
    notification.value = result.message
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))
}

function formatTime(iso) {
  return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

onMounted(() => {
  cart.fetchHeldOrders()
})
</script>

<template>
  <div>
    <div class="mb-5 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-ink">{{ t('orders.title') }}</h1>
        <p class="text-sm text-ink-faint">{{ t('orders.subtitle') }}</p>
      </div>
      <span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-700">
        {{ t('orders.heldCount', { count: heldOrders.length }) }}
      </span>
    </div>

    <div v-if="notification" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
      {{ notification }}
    </div>

    <div v-if="loadingHeldOrders" class="py-12 text-center text-ink-faint">{{ t('orders.loading') }}</div>

    <div v-else-if="heldOrders.length === 0" class="rounded-2xl border-2 border-dashed border-line-soft bg-surface py-16 text-center">
      <svg class="mx-auto h-10 w-10 text-line" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 7h6m-6 4h6" />
      </svg>
      <p class="mt-3 text-ink-faint">{{ t('orders.empty') }}</p>
      <p class="text-xs text-ink-faint">{{ t('orders.emptyHint') }}</p>
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <div
        v-for="order in heldOrders"
        :key="order.id"
        class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm"
      >
        <div class="mb-3 flex items-start justify-between">
          <div>
            <p class="font-semibold text-ink">{{ order.label }}</p>
            <p class="text-xs text-ink-faint">{{ formatTime(order.created_at) }} &middot; {{ order.user?.name ?? t('orders.cashierFallback') }}</p>
          </div>
          <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-600">{{ t('orders.onHold') }}</span>
        </div>

        <ul class="mb-3 space-y-1.5 text-sm">
          <li v-for="item in order.items" :key="item.product_id" class="flex justify-between text-ink-soft">
            <span>{{ item.name }} × {{ item.quantity }}</span>
            <span>{{ formatCurrency(item.subtotal) }}</span>
          </li>
        </ul>

        <div class="mb-4 border-t border-line-soft pt-2 text-right">
          <span class="text-sm text-ink-faint">{{ t('orders.total') }} </span>
          <span class="font-bold text-ink">{{ formatCurrency(order.subtotal) }}</span>
        </div>

        <div class="flex gap-2">
          <button
            class="flex flex-1 items-center justify-center gap-1.5 rounded-full bg-brand-600 active:scale-95 transition-transform py-2 text-sm font-semibold text-white hover:bg-brand-500"
            @click="recallOrder(order.id)"
          >
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-5 7l3 3m0 0l3-3m-3 3V9" />
            </svg>
            {{ t('orders.recall') }}
          </button>
          <button
            class="rounded-lg border border-rose-200 px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950"
            @click="removeOrder(order.id)"
          >
            {{ t('orders.remove') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
