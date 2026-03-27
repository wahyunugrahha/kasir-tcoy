<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import api from '../services/api'
import { useAuthStore } from '../stores/auth'
import { useCartStore } from '../stores/cart'
import ProductCatalog from '../components/ProductCatalog.vue'
import CartPanel from '../components/CartPanel.vue'

const auth = useAuthStore()
const cart = useCartStore()
const { items, itemCount, subtotal } = storeToRefs(cart)

const products = ref([])
const loadingProducts = ref(false)
const loadingCheckout = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const paymentMethod = ref('cash')
const discount = ref(0)
const tax = ref(0)
const cashReceived = ref(0)
const lastReceipt = ref(null)

const grandTotal = computed(() => Number(subtotal.value) - Number(discount.value) + Number(tax.value))

const cashChange = computed(() => {
  if (paymentMethod.value !== 'cash') return 0
  return Math.max(0, Number(cashReceived.value) - grandTotal.value)
})

async function loadProducts() {
  loadingProducts.value = true
  errorMessage.value = ''
  try {
    const response = await api.get('/products')
    products.value = response.data.data ?? []
    cart.syncStock(products.value)
  } catch (error) {
    errorMessage.value = error.response?.data?.message ?? 'Gagal memuat produk.'
  } finally {
    loadingProducts.value = false
  }
}

function handleAddToCart(product) {
  const result = cart.addToCart(product)
  if (result?.ok === false) {
    errorMessage.value = result.message
    return
  }
  errorMessage.value = ''
}

function handleIncrementItem(productId) {
  const result = cart.incrementItem(productId)
  if (result?.ok === false) {
    errorMessage.value = result.message
    return
  }
  errorMessage.value = ''
}

async function checkout() {
  if (items.value.length === 0) {
    errorMessage.value = 'Keranjang masih kosong.'
    return
  }

  loadingCheckout.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const payload = {
      user_id: Number(auth.user?.id),
      payment_method: paymentMethod.value,
      discount: Number(discount.value),
      tax: Number(tax.value),
      cash_received: paymentMethod.value === 'cash' ? Number(cashReceived.value) : 0,
      items: items.value.map((item) => ({ product_id: item.product_id, quantity: item.quantity })),
    }

    const response = await api.post('/checkout', payload)
    successMessage.value = `✅ Transaksi berhasil! Invoice: ${response.data.invoice_number}`
    lastReceipt.value = response.data
    cart.clearCart()
    discount.value = 0
    tax.value = 0
    cashReceived.value = 0
    await loadProducts()
  } catch (error) {
    errorMessage.value = error.response?.data?.message ?? 'Checkout gagal.'
  } finally {
    loadingCheckout.value = false
  }
}

function printReceipt() {
  if (!lastReceipt.value) return

  const detailRows = (lastReceipt.value.details ?? [])
    .map((item) => `<tr><td>${item.product_name_snapshot} x${item.quantity}</td><td style="text-align:right;">Rp ${Number(item.subtotal).toLocaleString('id-ID')}</td></tr>`)
    .join('')

  const html = `
    <html>
      <head>
        <title>Struk ${lastReceipt.value.invoice_number}</title>
        <style>
          body { font-family: Arial, sans-serif; width: 280px; margin: 12px auto; }
          h3, p { margin: 2px 0; }
          table { width: 100%; border-collapse: collapse; margin-top: 8px; }
          td { font-size: 12px; padding: 2px 0; }
          .line { border-top: 1px dashed #777; margin: 8px 0; }
        </style>
      </head>
      <body>
        <h3>POS PRO</h3>
        <p>${new Date().toLocaleString('id-ID')}</p>
        <p>Invoice: ${lastReceipt.value.invoice_number}</p>
        <div class="line"></div>
        <table>${detailRows}</table>
        <div class="line"></div>
        <p>Total: Rp ${Number(lastReceipt.value.grand_total).toLocaleString('id-ID')}</p>
        <p>Bayar: Rp ${Number(lastReceipt.value.amount_paid ?? lastReceipt.value.cash_received ?? 0).toLocaleString('id-ID')}</p>
        <p>Kembalian: Rp ${Number(lastReceipt.value.cash_change ?? 0).toLocaleString('id-ID')}</p>
        <p>Terima kasih.</p>
      </body>
    </html>
  `

  const popup = window.open('', '_blank', 'width=420,height=640')
  if (!popup) return
  popup.document.write(html)
  popup.document.close()
  popup.focus()
  popup.print()
}

onMounted(loadProducts)
</script>

<template>
  <div class="grid gap-5 lg:grid-cols-[2fr_1fr]">
    <ProductCatalog
      :products="products"
      :loading="loadingProducts"
      @refresh="loadProducts"
      @add="handleAddToCart"
    />

    <CartPanel
      :items="items"
      :item-count="itemCount"
      :subtotal="subtotal"
      :grand-total="grandTotal"
      :cash-change="cashChange"
      :loading-checkout="loadingCheckout"
      :payment-method="paymentMethod"
      :discount="discount"
      :tax="tax"
      :cash-received="cashReceived"
      @remove-item="cart.removeItem"
      @increment-item="handleIncrementItem"
      @decrement-item="cart.decrementItem"
      @checkout="checkout"
      @hold="cart.holdCart()"
      @update:payment-method="paymentMethod = $event"
      @update:discount="discount = $event"
      @update:tax="tax = $event"
      @update:cash-received="cashReceived = $event"
    />
  </div>

  <!-- Alerts -->
  <div v-if="errorMessage" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
    {{ errorMessage }}
  </div>
  <div v-if="successMessage" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
    {{ successMessage }}
  </div>

  <button
    v-if="lastReceipt"
    class="mt-3 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700"
    @click="printReceipt"
  >
    Cetak Struk Terakhir
  </button>
</template>
