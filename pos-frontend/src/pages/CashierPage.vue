<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import api from '../services/api'
import { useCartStore } from '../stores/cart'
import ProductCatalog from '../components/ProductCatalog.vue'
import CartPanel from '../components/CartPanel.vue'
import { printReceipt as printReceiptDoc } from '../utils/receipt'

const { t } = useI18n()
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

// Rupiah discount per 1 loyalty point — mirrors TransactionService::POINTS_REDEEM_VALUE.
// Backend is authoritative and re-caps on submit; this is only for the on-screen preview.
const POINTS_REDEEM_VALUE = 100
const customerQuery = ref('')
const customerResults = ref([])
const searchingCustomer = ref(false)
const selectedCustomer = ref(null)
const pointsToRedeem = ref(0)
let customerSearchTimer = null

const promoCodeInput = ref('')
const appliedPromo = ref(null) // { code, discount_type, discount_value, max_discount }
const promoError = ref('')
const applyingPromo = ref(false)

const PRODUCTS_CACHE_KEY = 'pos_products_cache_v1'
const PENDING_CHECKOUTS_KEY = 'pos_pending_checkouts_v1'
const SETTINGS_KEY = 'pos_store_settings'
const barcodeBuffer = ref('')
let barcodeTimer = null
let errorToastTimer = null
let successToastTimer = null
let syncingPendingCheckouts = false

const isOnline = ref(navigator.onLine)

const discountAmount = computed(() => {
  const base = Number(subtotal.value || 0)
  const rate = Number(discount.value || 0)
  return Math.max(0, base * (Math.max(0, Math.min(100, rate)) / 100))
})

const promoDiscountAmount = computed(() => {
  if (!appliedPromo.value) return 0
  const base = Number(subtotal.value || 0)
  if (base <= 0) return 0

  let amount = appliedPromo.value.discount_type === 'percent'
    ? base * (Number(appliedPromo.value.discount_value) / 100)
    : Number(appliedPromo.value.discount_value)

  if (appliedPromo.value.discount_type === 'percent' && appliedPromo.value.max_discount != null) {
    amount = Math.min(amount, Number(appliedPromo.value.max_discount))
  }

  return Math.max(0, Math.min(base, amount))
})

const taxableAmount = computed(() => Math.max(0, Number(subtotal.value || 0) - Number(discountAmount.value || 0) - Number(promoDiscountAmount.value || 0)))

const taxAmount = computed(() => {
  const base = Number(taxableAmount.value || 0)
  const rate = Number(tax.value || 0)
  return Math.max(0, base * (Math.max(0, Math.min(100, rate)) / 100))
})

const preRedemptionTotal = computed(() => Math.max(0, Number(taxableAmount.value) + Number(taxAmount.value)))

const pointsDiscountAmount = computed(() => {
  if (!selectedCustomer.value || Number(pointsToRedeem.value) <= 0) return 0

  const maxByBalance = Number(selectedCustomer.value.points || 0)
  const maxByTotal = Math.floor(preRedemptionTotal.value / POINTS_REDEEM_VALUE)
  const capped = Math.max(0, Math.min(Number(pointsToRedeem.value), maxByBalance, maxByTotal))

  return capped * POINTS_REDEEM_VALUE
})

const grandTotal = computed(() => Math.max(0, preRedemptionTotal.value - pointsDiscountAmount.value))

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
      configuredTaxPercent.value = 0
      tax.value = 0
      return
    }

    const parsed = JSON.parse(raw)
    configuredTaxPercent.value = normalizePercent(parsed?.tax_percentage ?? 0)
    tax.value = configuredTaxPercent.value
  } catch {
    configuredTaxPercent.value = 0
    tax.value = 0
  }
}

function showToast(type, message) {
  if (type === 'error') {
    errorMessage.value = message
    clearTimeout(errorToastTimer)
    errorToastTimer = setTimeout(() => errorMessage.value = '', 5000)
  } else {
    successMessage.value = message
    clearTimeout(successToastTimer)
    successToastTimer = setTimeout(() => successMessage.value = '', 5000)
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
      showToast('error', t('cashier.offlineUsingCached'))
    } else {
      showToast('error', error.response?.data?.message ?? t('cashier.loadProductsError'))
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
  if (!isOnline.value || syncingPendingCheckouts) return

  const queue = getPendingCheckouts()
  if (queue.length === 0) return

  syncingPendingCheckouts = true
  // Claim the queue up front so an overlapping call (another 'online' event, a second
  // mount) sees it empty instead of re-posting the same offline transactions.
  setPendingCheckouts([])

  const failed = []
  for (const payload of queue) {
    try {
      await api.post('/checkout', payload)
    } catch {
      failed.push(payload)
    }
  }

  setPendingCheckouts([...failed, ...getPendingCheckouts()])
  if (failed.length === 0) {
    showToast('success', t('cashier.offlineSynced'))
    await loadProducts()
  }
  syncingPendingCheckouts = false
}

function searchCustomers(query) {
  customerQuery.value = query
  clearTimeout(customerSearchTimer)

  if (!query.trim()) {
    customerResults.value = []
    return
  }

  customerSearchTimer = setTimeout(async () => {
    searchingCustomer.value = true
    try {
      const response = await api.get('/v1/customers', { params: { search: query, per_page: 5 } })
      customerResults.value = response.data.data ?? []
    } catch {
      customerResults.value = []
    } finally {
      searchingCustomer.value = false
    }
  }, 300)
}

function selectCustomer(customer) {
  selectedCustomer.value = customer
  customerName.value = customer.name
  customerQuery.value = ''
  customerResults.value = []
  pointsToRedeem.value = 0
}

function clearSelectedCustomer() {
  selectedCustomer.value = null
  pointsToRedeem.value = 0
}

async function createAndSelectCustomer({ name, phone }) {
  try {
    const response = await api.post('/v1/customers', { name, phone })
    selectCustomer(response.data)
    showToast('success', t('cashier.customerCreated', { name: response.data.name }))
  } catch (error) {
    showToast('error', error.response?.data?.message ?? t('cashier.createCustomerError'))
  }
}

async function handleHold() {
  const result = await cart.holdCart()
  if (result?.ok === false) {
    showToast('error', result.message)
    return
  }
  showToast('success', t('cashier.orderHeld'))
}

async function applyPromoCode() {
  if (!promoCodeInput.value.trim()) return

  promoError.value = ''
  applyingPromo.value = true
  try {
    const response = await api.post('/v1/promo-codes/validate', {
      code: promoCodeInput.value.trim(),
      subtotal: subtotal.value,
    })
    appliedPromo.value = response.data
  } catch (error) {
    promoError.value = error.response?.data?.message ?? t('cashier.invalidPromo')
    appliedPromo.value = null
  } finally {
    applyingPromo.value = false
  }
}

function clearPromoCode() {
  appliedPromo.value = null
  promoCodeInput.value = ''
  promoError.value = ''
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
  if (loadingCheckout.value) return

  if (items.value.length === 0) {
    showToast('error', t('cashier.cartEmpty'))
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
      showToast('error', t('cashier.fillAtLeastOnePayment'))
      loadingCheckout.value = false
      return
    }

    if (Number(totalPaid.value) <= 0) {
      showToast('error', t('cashier.totalMustBePositive'))
      loadingCheckout.value = false
      return
    }
  }

  const payload = {
    customer_name: String(customerName.value || '').trim() || null,
    customer_id: selectedCustomer.value?.id ?? null,
    redeem_points: selectedCustomer.value && Number(pointsToRedeem.value) > 0 ? Number(pointsToRedeem.value) : undefined,
    promo_code: appliedPromo.value?.code,
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

    showToast('success', t('cashier.offlineQueued'))
    resetPosState()
    return
  }

  try {
    const response = await api.post('/checkout', payload)
    const pointsNote = response.data.points_earned > 0 ? t('cashier.pointsEarnedSuffix', { points: response.data.points_earned }) : ''
    showToast('success', t('cashier.checkoutSuccess', { invoice: response.data.invoice_number, pointsNote }))
    lastReceipt.value = response.data
    resetPosState()
    await loadProducts()
  } catch (error) {
    showToast('error', error.response?.data?.message ?? t('cashier.checkoutError'))
  } finally {
    loadingCheckout.value = false
  }
}

function resetPosState() {
  cart.clearCart()
  customerName.value = ''
  clearSelectedCustomer()
  clearPromoCode()
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
    showToast('error', t('cashier.barcodeNotFound', { code }))
    return
  }
  handleAddToCart(product)
}

function isTypingInField(event) {
  const tag = event.target?.tagName
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || event.target?.isContentEditable
}

function handleGlobalKeydown(event) {
  if (event.key === 'F2') { event.preventDefault(); focusProductSearch(); return }
  if (event.key === 'F4') { event.preventDefault(); if (!loadingCheckout.value) checkout(); return }
  if (event.key === 'F8') { event.preventDefault(); handleHold(); return }

  // Barcode-scanner input and its Enter terminator only make sense outside form fields —
  // typing in customer name / discount / payment amount shouldn't be hijacked as a scan.
  if (isTypingInField(event)) return

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
  printReceiptDoc(lastReceipt.value)
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
  <div class="flex h-full w-full flex-col overflow-hidden text-ink">

    <!-- Main Content Area -->
    <main class="flex flex-1 overflow-hidden gap-4 lg:gap-6">
      <!-- Kiri: Katalog Produk (Scrollable) -->
      <section class="flex-1 flex flex-col overflow-hidden rounded-2xl border border-line-soft bg-surface/95 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)]">
        <ProductCatalog ref="productCatalogRef" class="flex-1 overflow-y-auto" :products="products"
          :loading="loadingProducts" @refresh="loadProducts" @add="handleAddToCart" />
      </section>

      <!-- Kanan: Panel Keranjang (Fixed Layout) -->
      <aside
        class="shrink-0 flex w-full flex-col overflow-hidden rounded-2xl border border-line-soft bg-surface/95 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.35)] lg:sticky lg:top-0 lg:h-[calc(100vh-3.2rem)] lg:max-h-[calc(100vh-3.2rem)] lg:w-[420px] xl:w-[460px]">
        <CartPanel class="flex-1 overflow-y-auto" :items="items" :item-count="itemCount" :subtotal="subtotal"
          :grand-total="grandTotal" :cash-change="cashChange" :loading-checkout="loadingCheckout"
          :payment-method="paymentMethod" :customer-name="customerName"
          :enable-split-payment="enableSplitPayment" :payments="payments"
          :discount="discount" :tax="tax" :discount-amount="discountAmount" :tax-amount="taxAmount"
          :cash-received="cashReceived" :total-paid="totalPaid"
          :remaining-due="remainingDue"
          :selected-customer="selectedCustomer" :customer-query="customerQuery" :customer-results="customerResults"
          :searching-customer="searchingCustomer" :points-to-redeem="pointsToRedeem" :points-discount-amount="pointsDiscountAmount"
          :promo-code-input="promoCodeInput" :applied-promo="appliedPromo" :promo-error="promoError" :applying-promo="applyingPromo"
          :promo-discount-amount="promoDiscountAmount"
          @remove-item="cart.removeItem" @increment-item="handleIncrementItem"
          @decrement-item="cart.decrementItem" @checkout="checkout" @hold="handleHold"
          @add-payment-row="addPaymentRow" @remove-payment-row="removePaymentRow" @update-payment-row="updatePaymentRow"
          @update:payment-method="paymentMethod = $event" @update:customer-name="customerName = $event"
          @update:enable-split-payment="enableSplitPayment = $event"
          @update:discount="setDiscountPercent($event)"
          @update:cash-received="cashReceived = $event"
          @search-customer="searchCustomers" @select-customer="selectCustomer" @clear-customer="clearSelectedCustomer"
          @create-customer="createAndSelectCustomer" @update:points-to-redeem="pointsToRedeem = $event"
          @update:promo-code-input="promoCodeInput = $event" @apply-promo="applyPromoCode" @clear-promo="clearPromoCode" />

        <!-- Quick Action / Print Receipt Button Area -->
        <div v-if="lastReceipt" class="border-t border-line-soft bg-surface-2 p-4">
          <button
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-3 text-sm font-semibold text-white transition-all hover:bg-slate-900 active:scale-[0.98]"
            @click="printReceipt">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
              </path>
            </svg>
            {{ t('cashier.printReceiptLabel', { invoice: lastReceipt.invoice_number }) }}
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
