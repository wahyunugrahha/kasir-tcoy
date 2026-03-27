<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  products: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['refresh', 'add'])

const searchQuery = ref('')
const selectedCategory = ref('all')

const categories = computed(() => {
  const values = props.products
    .map((product) => product.category?.name)
    .filter((name) => Boolean(name))

  return ['all', ...new Set(values)]
})

const filteredProducts = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return props.products.filter((product) => {
    const matchesCategory =
      selectedCategory.value === 'all' || product.category?.name === selectedCategory.value

    const target = [product.name, product.sku, product.category?.name].join(' ').toLowerCase()
    const matchesQuery = query.length === 0 || target.includes(query)

    return matchesCategory && matchesQuery
  })
})

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0))
}

function resolveProductImage(product) {
  if (product.image_url) {
    if (String(product.image_url).startsWith('http')) {
      return product.image_url
    }

    const baseUrl = (import.meta.env.VITE_API_BASE_URL ?? 'http://127.0.0.1:8000/api').replace('/api', '')
    return `${baseUrl}${product.image_url}`
  }

  // Temporary dummy image until every product has uploaded image
  return `https://picsum.photos/seed/pos-${product.id}/420/260`
}
</script>

<template>
  <section class="rounded-2xl bg-white p-5 shadow-sm">
    <div class="mb-4 flex items-center justify-between gap-3">
      <h1 class="text-2xl font-bold">Katalog Produk</h1>
      <button
        class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-100"
        @click="emit('refresh')"
      >
        Muat Ulang
      </button>
    </div>

    <div class="mb-4 grid gap-3 md:grid-cols-2">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Cari nama, SKU, atau kategori..."
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
      />

      <select v-model="selectedCategory" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option v-for="category in categories" :key="category" :value="category">
          {{ category === 'all' ? 'Semua Kategori' : category }}
        </option>
      </select>
    </div>

    <p v-if="loading" class="text-sm text-slate-500">Mengambil produk dari server...</p>

    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
      <button
        v-for="product in filteredProducts"
        :key="product.id"
        class="group overflow-hidden rounded-xl border border-slate-200 bg-white text-left shadow-sm transition-transform duration-200 hover:-translate-y-0.5 hover:border-slate-400 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="product.stock <= 0"
        @click="emit('add', product)"
      >
        <div class="h-36 overflow-hidden bg-slate-100">
          <img
            :src="resolveProductImage(product)"
            :alt="product.name"
            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            loading="lazy"
          />
        </div>

        <div class="p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ product.sku }}</p>
          <p class="mt-1 text-lg font-semibold">{{ product.name }}</p>
          <p class="mt-1 text-sm text-slate-500">{{ product.category?.name }}</p>
          <p class="mt-3 text-base font-bold text-emerald-700">
            {{ formatCurrency(product.selling_price) }}
          </p>
          <p class="mt-1 text-xs" :class="product.stock > 0 ? 'text-slate-500' : 'text-rose-500'">
            Stok: {{ product.stock }}
          </p>
        </div>
      </button>
    </div>

    <p v-if="!loading && filteredProducts.length === 0" class="mt-4 text-sm text-slate-500">
      Tidak ada produk yang sesuai pencarian.
    </p>
  </section>
</template>
