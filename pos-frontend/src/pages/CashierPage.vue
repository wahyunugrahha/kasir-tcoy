<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import api from '../services/api'
import { useAuthStore } from '../stores/auth'
import { useCartStore } from '../stores/cart'
import ProductCatalog from '../components/ProductCatalog.vue'
import CartPanel from '../components/CartPanel.vue'
// Opsional: Jika Anda menggunakan icon (misal: heroicons/lucide-vue)
// import { ClockIcon, WifiIcon, WifiOffIcon } from 'lucide-vue-next'

const auth = useAuthStore()
const cart = useCartStore()
const { items, itemCount, subtotal } = storeToRefs(cart)

const products = ref([])
const loadingProducts = ref(false)
const loadingCheckout = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const paymentMethod = ref('cash')
const customerName = ref('')
const enableSplitPayment = ref(false)
const payments = ref([
  { payment_method: 'cash', amount: 0, reference_number: '' },
])
const discount = ref(0)
const tax = ref(0)
const cashReceived = ref(0)
const lastReceipt = ref(null)
const productCatalogRef = ref(null)

const PRODUCTS_CACHE_KEY = 'pos_products_cache_v1'
const PENDING_CHECKOUTS_KEY = 'pos_pending_checkouts_v1'
const SETTINGS_KEY = 'pos_store_settings'
const barcodeBuffer = ref('')
let barcodeTimer = null

const isOnline = ref(navigator.onLine)
const storeInfo = ref({
  store_name: 'POS MODERN',
  store_address: 'Jl. Contoh Alamat No. 123',
  store_phone: '',
})

const discountAmount = computed(() => {
  const base = Number(subtotal.value || 0)
  const rate = Number(discount.value || 0)
  return Math.max(0, base * (Math.max(0, Math.min(100, rate)) / 100))
})

const taxableAmount = computed(() => Math.max(0, Number(subtotal.value || 0) - Number(discountAmount.value || 0)))

const taxAmount = computed(() => {
  const base = Number(taxableAmount.value || 0)
  const rate = Number(tax.value || 0)
  return Math.max(0, base * (Math.max(0, Math.min(100, rate)) / 100))
})

const grandTotal = computed(() => Number(taxableAmount.value) + Number(taxAmount.value))

const totalPaid = computed(() =>
  payments.value.reduce((sum, row) => sum + Number(row.amount || 0), 0)
)

const remainingDue = computed(() => Math.max(0, Number(grandTotal.value) - Number(totalPaid.value)))

const cashPortionPaid = computed(() =>
  payments.value
    .filter((row) => row.payment_method === 'cash')
    .reduce((sum, row) => sum + Number(row.amount || 0), 0)
)

const cashChange = computed(() => {
  if (enableSplitPayment.value) {
    return Math.max(0, Number(cashPortionPaid.value) - Number(grandTotal.value))
  }

  if (paymentMethod.value !== 'cash') return 0
  return Math.max(0, Number(cashReceived.value) - grandTotal.value)
})

const configuredTaxPercent = ref(0)

function normalizePercent(value) {
  const raw = Number.parseFloat(value)
  if (!Number.isFinite(raw)) return 0
  const clamped = Math.max(0, Math.min(100, raw))
  return Math.round(clamped * 100) / 100
}

function setDiscountPercent(value) {
  discount.value = normalizePercent(value)
}

function applyTaxFromSettings() {
  try {
    const raw = localStorage.getItem(SETTINGS_KEY)
    if (!raw) {
      storeInfo.value = {
        store_name: 'POS MODERN',
        store_address: 'Jl. Contoh Alamat No. 123',
        store_phone: '',
      }
      configuredTaxPercent.value = 0
      tax.value = 0
      return
    }

    const parsed = JSON.parse(raw)
    storeInfo.value = {
      store_name: String(parsed?.store_name || 'POS MODERN'),
      store_address: String(parsed?.store_address || 'Jl. Contoh Alamat No. 123'),
      store_phone: String(parsed?.store_phone || ''),
    }
    configuredTaxPercent.value = normalizePercent(parsed?.tax_percentage ?? 0)
    tax.value = configuredTaxPercent.value
  } catch {
    storeInfo.value = {
      store_name: 'POS MODERN',
      store_address: 'Jl. Contoh Alamat No. 123',
      store_phone: '',
    }
    configuredTaxPercent.value = 0
    tax.value = 0
  }
}

function showToast(type, message) {
  if (type === 'error') {
    errorMessage.value = message
    setTimeout(() => errorMessage.value = '', 5000)
  } else {
    successMessage.value = message
    setTimeout(() => successMessage.value = '', 5000)
  }
}

function resetPayments() {
  payments.value = [{
    payment_method: 'cash',
    amount: Math.max(0, Number(grandTotal.value || 0)),
    reference_number: '',
  }]
}

function addPaymentRow() {
  payments.value.push({ payment_method: 'qris', amount: 0, reference_number: '' })
}

function removePaymentRow(index) {
  if (payments.value.length <= 1) return
  payments.value.splice(index, 1)
}

function updatePaymentRow(index, patch) {
  const row = payments.value[index]
  if (!row) return
  payments.value[index] = { ...row, ...patch }
}

async function loadProducts() {
  loadingProducts.value = true
  try {
    const response = await api.get('/products')
    products.value = response.data.data ?? []
    localStorage.setItem(PRODUCTS_CACHE_KEY, JSON.stringify(products.value))
    cart.syncStock(products.value)
  } catch (error) {
    const cached = localStorage.getItem(PRODUCTS_CACHE_KEY)
    if (cached) {
      products.value = JSON.parse(cached)
      cart.syncStock(products.value)
      showToast('error', 'Mode offline: menggunakan data produk terakhir.')
    } else {
      showToast('error', error.response?.data?.message ?? 'Gagal memuat produk.')
    }
  } finally {
    loadingProducts.value = false
  }
}

function getPendingCheckouts() {
  try {
    return JSON.parse(localStorage.getItem(PENDING_CHECKOUTS_KEY) ?? '[]')
  } catch {
    return []
  }
}

function setPendingCheckouts(payloads) {
  localStorage.setItem(PENDING_CHECKOUTS_KEY, JSON.stringify(payloads))
}

async function syncPendingCheckouts() {
  if (!isOnline.value) return

  const queue = getPendingCheckouts()
  if (queue.length === 0) return

  const failed = []
  for (const payload of queue) {
    try {
      await api.post('/checkout', payload)
    } catch {
      failed.push(payload)
    }
  }

  setPendingCheckouts(failed)
  if (failed.length === 0) {
    showToast('success', 'Transaksi offline berhasil disinkronkan.')
    await loadProducts()
  }
}

function handleAddToCart(product) {
  const result = cart.addToCart(product)
  if (result?.ok === false) {
    showToast('error', result.message)
  }
}

function handleIncrementItem(productId) {
  const result = cart.incrementItem(productId)
  if (result?.ok === false) {
    showToast('error', result.message)
  }
}

async function checkout() {
  if (items.value.length === 0) {
    showToast('error', 'Keranjang masih kosong.')
    return
  }

  loadingCheckout.value = true

  if (enableSplitPayment.value) {
    const validPayments = payments.value
      .map((row) => ({
        payment_method: row.payment_method,
        amount: Number(row.amount || 0),
        reference_number: row.reference_number?.trim() || null,
      }))
      .filter((row) => row.amount > 0)

    if (validPayments.length === 0) {
      showToast('error', 'Isi minimal satu pembayaran untuk split payment.')
      loadingCheckout.value = false
      return
    }

    if (Number(totalPaid.value) <= 0) {
      showToast('error', 'Total pembayaran harus lebih besar dari 0.')
      loadingCheckout.value = false
      return
    }
  }

  const payload = {
    user_id: Number(auth.user?.id),
    customer_name: String(customerName.value || '').trim() || null,
    discount_percent: Number(discount.value),
    tax_percent: Number(tax.value),
    discount_type: 'percent',
    discount_value: Number(discount.value),
    tax_rate: Number(tax.value),
    tax: 0,
    items: items.value.map((item) => ({ product_id: item.product_id, quantity: item.quantity })),
  }

  if (enableSplitPayment.value) {
    payload.payments = payments.value
      .map((row) => ({
        payment_method: row.payment_method,
        amount: Number(row.amount || 0),
        reference_number: row.reference_number?.trim() || null,
      }))
      .filter((row) => row.amount > 0)
  } else {
    payload.payment_method = paymentMethod.value
    payload.cash_received = paymentMethod.value === 'cash' ? Number(cashReceived.value) : 0
  }

  if (!isOnline.value) {
    const queue = getPendingCheckouts()
    queue.push(payload)
    setPendingCheckouts(queue)

    showToast('success', 'Mode offline: transaksi disimpan ke antrean.')
    resetPosState()
    return
  }

  try {
    const response = await api.post('/checkout', payload)
    showToast('success', `✅ Transaksi berhasil! Invoice: ${response.data.invoice_number}`)
    lastReceipt.value = response.data
    resetPosState()
    await loadProducts()
  } catch (error) {
    showToast('error', error.response?.data?.message ?? 'Checkout gagal.')
  } finally {
    loadingCheckout.value = false
  }
}

function resetPosState() {
  cart.clearCart()
  customerName.value = ''
  discount.value = 0
  tax.value = configuredTaxPercent.value
  cashReceived.value = 0
  resetPayments()
  loadingCheckout.value = false
}

function focusProductSearch() {
  productCatalogRef.value?.focusSearch?.()
}

function findProductBySku(sku) {
  const normalized = String(sku ?? '').trim().toLowerCase()
  return products.value.find((product) => String(product.sku ?? '').trim().toLowerCase() === normalized)
}

function processBarcode(code) {
  if (!code) return
  const product = findProductBySku(code)
  if (!product) {
    showToast('error', `Barcode tidak ditemukan: ${code}`)
    return
  }
  handleAddToCart(product)
}

function handleGlobalKeydown(event) {
  if (event.key === 'F2') { event.preventDefault(); focusProductSearch(); return }
  if (event.key === 'F4') { event.preventDefault(); if (!loadingCheckout.value) checkout(); return }
  if (event.key === 'F8') { event.preventDefault(); cart.holdCart(); return }

  if (event.key === 'Enter' && barcodeBuffer.value.length >= 3) {
    event.preventDefault()
    const code = barcodeBuffer.value
    barcodeBuffer.value = ''
    if (barcodeTimer) clearTimeout(barcodeTimer)
    processBarcode(code)
    return
  }

  if (event.key.length === 1 && !event.ctrlKey && !event.altKey && !event.metaKey) {
    barcodeBuffer.value += event.key
    if (barcodeTimer) clearTimeout(barcodeTimer)
    barcodeTimer = setTimeout(() => { barcodeBuffer.value = '' }, 120)
  }
}

function updateOnlineStatus() {
  isOnline.value = navigator.onLine
  if (isOnline.value) syncPendingCheckouts()
}

function printReceipt() {
  if (!lastReceipt.value) return

  const storeName = storeInfo.value.store_name || 'POS MODERN'
  const storeAddress = storeInfo.value.store_address || '-'
  const storePhone = storeInfo.value.store_phone ? `<p>Telp: ${storeInfo.value.store_phone}</p>` : ''
  const transactionCustomerName = String(lastReceipt.value.customer_name || '-')

  const detailRows = (lastReceipt.value.details ?? [])
    .map((item) => `<tr><td>${item.product_name_snapshot} <br><small>x${item.quantity}</small></td><td style="text-align:right;">Rp ${Number(item.subtotal).toLocaleString('id-ID')}</td></tr>`)
    .join('')

  const receivedAmountFromPayments = Array.isArray(lastReceipt.value.payments)
    ? lastReceipt.value.payments.reduce((sum, payment) => sum + Number(payment.amount || 0), 0)
    : 0
  const receivedAmount = receivedAmountFromPayments > 0
    ? receivedAmountFromPayments
    : Number(lastReceipt.value.cash_received ?? lastReceipt.value.amount_paid ?? 0)

  const html = `
    <html>
      <head>
        <title>Struk ${lastReceipt.value.invoice_number}</title>
        <style>
          @page { margin: 0; }
          body { font-family: 'Courier New', Courier, monospace; width: 58mm; margin: 10px auto; color: #000; }
          .text-center { text-align: center; }
          h3 { margin: 0; font-size: 16px; }
          p { margin: 4px 0; font-size: 12px; }
          table { width: 100%; border-collapse: collapse; margin-top: 10px; }
          td { font-size: 12px; padding: 4px 0; vertical-align: top; }
          .line { border-top: 1px dashed #000; margin: 10px 0; }
          .bold { font-weight: bold; }
        </style>
      </head>
      <body>
        <div class="text-center">
          <h3>${storeName}</h3>
          <p>${storeAddress}</p>
          ${storePhone}
          <div class="line"></div>
          <p>${new Date().toLocaleString('id-ID')}</p>
          <p>Inv: ${lastReceipt.value.invoice_number}</p>
          <p>Pembeli: ${transactionCustomerName}</p>
        </div>
        <div class="line"></div>
        <table>${detailRows}</table>
        <div class="line"></div>
        <table>
          <tr><td class="bold">Total Pembayaran</td><td style="text-align:right;" class="bold">Rp ${Number(lastReceipt.value.grand_total).toLocaleString('id-ID')}</td></tr>
          <tr><td>Uang Diterima</td><td style="text-align:right;">Rp ${Number(receivedAmount).toLocaleString('id-ID')}</td></tr>
          <tr><td>Kembali</td><td style="text-align:right;">Rp ${Number(lastReceipt.value.cash_change ?? 0).toLocaleString('id-ID')}</td></tr>
        </table>
        <div class="line"></div>
        <p class="text-center">Terima Kasih<br>Silakan Berkunjung Kembali</p>
      </body>
    </html>
  `

  const popup = window.open('', '_blank', 'width=400,height=600')
  if (!popup) return
  popup.document.write(html)
  popup.document.close()
  popup.focus()
  popup.print()
}

onMounted(async () => {
  applyTaxFromSettings()
  customerName.value = ''
  resetPayments()
  await loadProducts()
  window.addEventListener('focus', applyTaxFromSettings)
  window.addEventListener('online', updateOnlineStatus)
  window.addEventListener('offline', updateOnlineStatus)
  window.addEventListener('keydown', handleGlobalKeydown)
  syncPendingCheckouts()
})

onUnmounted(() => {
  window.removeEventListener('focus', applyTaxFromSettings)
  window.removeEventListener('online', updateOnlineStatus)
  window.removeEventListener('offline', updateOnlineStatus)
  window.removeEventListener('keydown', handleGlobalKeydown)
  if (barcodeTimer) clearTimeout(barcodeTimer)
})
</script>

<template>
  <div class="flex h-full w-full flex-col overflow-hidden text-slate-800">

    <!-- Main Content Area -->
    <main class="flex flex-1 overflow-hidden gap-4 lg:gap-6">
      <!-- Kiri: Katalog Produk (Scrollable) -->
      <section class="flex-1 flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white/95 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)]">
        <ProductCatalog ref="productCatalogRef" class="flex-1 overflow-y-auto" :products="products"
          :loading="loadingProducts" @refresh="loadProducts" @add="handleAddToCart" />
      </section>

      <!-- Kanan: Panel Keranjang (Fixed Layout) -->
      <aside
        class="shrink-0 flex w-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white/95 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)] lg:sticky lg:top-0 lg:h-[calc(100vh-3.2rem)] lg:max-h-[calc(100vh-3.2rem)] lg:w-[420px] xl:w-[460px]">
        <CartPanel class="flex-1 overflow-y-auto" :items="items" :item-count="itemCount" :subtotal="subtotal"
          :grand-total="grandTotal" :cash-change="cashChange" :loading-checkout="loadingCheckout"
          :payment-method="paymentMethod" :customer-name="customerName"
          :enable-split-payment="enableSplitPayment" :payments="payments"
          :discount="discount" :tax="tax" :discount-amount="discountAmount" :tax-amount="taxAmount"
          :cash-received="cashReceived" :total-paid="totalPaid"
          :remaining-due="remainingDue" @remove-item="cart.removeItem" @increment-item="handleIncrementItem"
          @decrement-item="cart.decrementItem" @checkout="checkout" @hold="cart.holdCart()"
          @add-payment-row="addPaymentRow" @remove-payment-row="removePaymentRow" @update-payment-row="updatePaymentRow"
          @update:payment-method="paymentMethod = $event" @update:customer-name="customerName = $event"
          @update:enable-split-payment="enableSplitPayment = $event"
          @update:discount="setDiscountPercent($event)"
          @update:cash-received="cashReceived = $event" />

        <!-- Quick Action / Print Receipt Button Area -->
        <div v-if="lastReceipt" class="border-t border-slate-200 bg-slate-50 p-4">
          <button
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-3 text-sm font-semibold text-white transition-all hover:bg-slate-900 active:scale-[0.98]"
            @click="printReceipt">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
              </path>
            </svg>
            Cetak Struk: {{ lastReceipt.invoice_number }}
          </button>
        </div>
      </aside>
    </main>

    <!-- Global Floating Toasts -->
    <TransitionGroup tag="div" enter-active-class="transition duration-300 ease-out"
      enter-from-class="transform translate-y-2 opacity-0" enter-to-class="transform translate-y-0 opacity-100"
      leave-active-class="transition duration-200 ease-in" leave-from-class="transform translate-y-0 opacity-100"
      leave-to-class="transform translate-y-2 opacity-0" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3">
      <div v-if="errorMessage" key="error"
        class="flex max-w-sm items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-700 shadow-lg shadow-rose-200/40">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-sm font-medium">{{ errorMessage }}</p>
      </div>
      <div v-if="successMessage" key="success"
        class="flex max-w-sm items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700 shadow-lg shadow-emerald-200/40">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <p class="text-sm font-medium">{{ successMessage }}</p>
      </div>
    </TransitionGroup>

  </div>
</template>

<style scoped>
/* Opsional: Menyembunyikan scrollbar bawaan untuk tampilan lebih bersih */
::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}

::-webkit-scrollbar-track {
  background: transparent;
}

::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 10px;
}
</style>