import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

export const useCartStore = defineStore('cart', () => {
  const items = ref([])

  function addToCart(product) {
    const stock = Number(product.stock ?? 0)
    if (stock <= 0) {
      return { ok: false, message: 'Stok produk habis.' }
    }

    const existing = items.value.find((item) => item.product_id === product.id)

    if (existing) {
      if (existing.quantity >= existing.stock) {
        return { ok: false, message: `Stok ${product.name} tidak mencukupi.` }
      }

      existing.quantity += 1
      existing.subtotal = existing.quantity * existing.price
      return { ok: true }
    }

    const price = Number(product.selling_price)

    items.value.push({
      product_id: product.id,
      sku: product.sku,
      name: product.name,
      price,
      stock,
      quantity: 1,
      subtotal: price,
    })

    return { ok: true }
  }

  function incrementItem(productId) {
    const target = items.value.find((item) => item.product_id === productId)

    if (!target) {
      return { ok: false, message: 'Produk tidak ditemukan di keranjang.' }
    }

    if (target.quantity >= target.stock) {
      return { ok: false, message: `Qty ${target.name} sudah mencapai stok maksimum.` }
    }

    target.quantity += 1
    target.subtotal = target.quantity * target.price

    return { ok: true }
  }

  function decrementItem(productId) {
    const target = items.value.find((item) => item.product_id === productId)

    if (!target) {
      return
    }

    target.quantity -= 1

    if (target.quantity <= 0) {
      removeItem(productId)
      return
    }

    target.subtotal = target.quantity * target.price
  }

  function syncStock(products = []) {
    const productMap = new Map(products.map((product) => [product.id, Number(product.stock ?? 0)]))

    items.value = items.value
      .map((item) => {
        const stock = productMap.get(item.product_id)

        if (stock == null || stock <= 0) {
          return null
        }

        const quantity = Math.min(item.quantity, stock)

        return {
          ...item,
          stock,
          quantity,
          subtotal: quantity * item.price,
        }
      })
      .filter(Boolean)
  }

  function removeItem(productId) {
    items.value = items.value.filter((item) => item.product_id !== productId)
  }

  function clearCart() {
    items.value = []
  }

  // ── Hold Order System ─────────────────────────────────────────────────────
  const heldOrders = ref([])

  function holdCart(label = null) {
    if (items.value.length === 0) {
      return { ok: false, message: 'Keranjang kosong, tidak bisa di-hold.' }
    }

    heldOrders.value.push({
      id: Date.now(),
      label: label ?? `Order #${heldOrders.value.length + 1}`,
      items: items.value.map((i) => ({ ...i })),
      subtotal: subtotal.value,
      timestamp: new Date().toISOString(),
    })

    items.value = []
    return { ok: true }
  }

  function recallOrder(orderId) {
    const target = heldOrders.value.find((o) => o.id === orderId)

    if (!target) {
      return { ok: false, message: 'Order tidak ditemukan.' }
    }

    // If current cart has items, hold it first so nothing is lost when switching orders.
    if (items.value.length > 0) {
      const holdResult = holdCart()

      if (holdResult?.ok === false) {
        return holdResult
      }
    }

    items.value = target.items.map((i) => ({ ...i }))
    heldOrders.value = heldOrders.value.filter((o) => o.id !== orderId)

    return { ok: true }
  }

  function removeHeldOrder(orderId) {
    heldOrders.value = heldOrders.value.filter((o) => o.id !== orderId)
  }

  const itemCount = computed(() => items.value.reduce((sum, item) => sum + item.quantity, 0))
  const subtotal = computed(() => items.value.reduce((sum, item) => sum + item.subtotal, 0))

  return {
    items,
    heldOrders,
    itemCount,
    subtotal,
    addToCart,
    incrementItem,
    decrementItem,
    syncStock,
    removeItem,
    clearCart,
    holdCart,
    recallOrder,
    removeHeldOrder,
  }
})
