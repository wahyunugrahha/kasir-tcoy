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
const searchInput = ref(null)

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

function focusSearch() {
  searchInput.value?.focus()
}

defineExpose({ focusSearch })
</script>

<template>
  <section class="rounded-2xl bg-white p-4 md:p-5">
    <div class="mb-4 flex items-center justify-between gap-3">
      <h1 class="text-3xl font-semibold tracking-tight text-slate-800">Katalog Produk</h1>
      <button
        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
        @click="emit('refresh')"
      >
        Muat Ulang
      </button>
    </div>

    <div class="mb-4 grid gap-3 md:grid-cols-2">
      <div class="relative">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">🔍</span>
        <input
          ref="searchInput"
          v-model="searchQuery"
          type="text"
          placeholder="Cari nama, SKU, atau kategori produk..."
          class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-700 outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-100"
        />
      </div>

      <select
        v-model="selectedCategory"
        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-100"
      >
        <option v-for="category in categories" :key="category" :value="category">
          {{ category === 'all' ? 'Semua Kategori' : category }}
        </option>
      </select>
    </div>

    <p v-if="loading" class="text-sm text-slate-500">Mengambil produk dari server...</p>

    <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-5">
      <button
        v-for="product in filteredProducts"
        :key="product.id"
        class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="product.stock <= 0"
        @click="emit('add', product)"
      >
        <div class="h-24 overflow-hidden bg-slate-100">
          <img
            :src="resolveProductImage(product)"
            :alt="product.name"
            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            loading="lazy"
          />
        </div>

        <span
          class="absolute right-2 top-[5rem] grid h-7 w-7 place-items-center rounded-full border border-slate-200 bg-white text-base text-slate-500 shadow-sm"
        >+
        </span>

        <div class="p-3">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ product.sku }}</p>
          <p class="mt-1 line-clamp-1 text-lg font-semibold leading-tight text-slate-800">{{ product.name }}</p>
          <p class="mt-0.5 text-xs text-slate-500">{{ product.category?.name ?? '-' }}</p>
          <p class="mt-2 text-xl font-semibold text-slate-800">
            {{ formatCurrency(product.selling_price) }}
          </p>
          <p class="mt-0.5 text-xs" :class="product.stock > 0 ? 'text-emerald-600' : 'text-rose-500'">
            Stok {{ product.stock }}
          </p>
        </div>
      </button>
    </div>

    <p v-if="!loading && filteredProducts.length === 0" class="mt-4 text-sm text-slate-500">
      Tidak ada produk yang sesuai pencarian.
    </p>
  </section>
</template>
