<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

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

function lowStockThreshold(product) {
  return Number(product.min_stock ?? 5)
}

const searchQuery = ref('')
const selectedCategory = ref('all')
const searchInput = ref(null)
const viewMode = ref('grid')

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
  <section class="rounded-2xl bg-surface p-4 md:p-5">
    <div class="mb-4 flex items-center justify-between gap-3">
      <h1 class="text-3xl font-semibold tracking-tight text-ink">{{ t('productCatalog.title') }}</h1>
      <div class="flex items-center gap-2">
        <div class="flex items-center gap-0.5 rounded-lg border border-line bg-surface p-0.5">
          <button
            type="button"
            :aria-label="t('productCatalog.gridView')"
            class="grid h-8 w-8 place-items-center rounded-md transition"
            :class="viewMode === 'grid' ? 'bg-brand-600 text-white' : 'text-ink-soft hover:bg-surface-2'"
            @click="viewMode = 'grid'"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zm0 9h7v7h-7v-7zM4 13h7v7H4v-7z" />
            </svg>
          </button>
          <button
            type="button"
            :aria-label="t('productCatalog.listView')"
            class="grid h-8 w-8 place-items-center rounded-md transition"
            :class="viewMode === 'list' ? 'bg-brand-600 text-white' : 'text-ink-soft hover:bg-surface-2'"
            @click="viewMode = 'list'"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>
        <button
          class="rounded-lg border border-line bg-surface px-3 py-2 text-sm font-medium text-ink transition hover:bg-surface-2"
          @click="emit('refresh')"
        >
          {{ t('productCatalog.reload') }}
        </button>
      </div>
    </div>

    <div class="mb-4 grid gap-3 md:grid-cols-2">
      <div class="relative">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
          </svg>
        </span>
        <input
          ref="searchInput"
          v-model="searchQuery"
          type="text"
          :placeholder="t('productCatalog.searchPlaceholder')"
          class="w-full rounded-xl border border-line bg-surface py-2 pl-9 pr-3 text-sm text-ink outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
        />
      </div>

      <select
        v-model="selectedCategory"
        class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
      >
        <option v-for="category in categories" :key="category" :value="category">
          {{ category === 'all' ? t('productCatalog.allCategories') : category }}
        </option>
      </select>
    </div>

    <p v-if="loading" class="text-sm text-ink-faint">{{ t('productCatalog.loading') }}</p>

    <div v-else-if="viewMode === 'grid'" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-5">
      <button
        v-for="product in filteredProducts"
        :key="product.id"
        class="group flex flex-col overflow-hidden rounded-2xl border border-line-soft bg-surface text-left transition-all hover:border-line hover:shadow-sm disabled:cursor-not-allowed"
        :disabled="product.stock <= 0"
        @click="emit('add', product)"
      >
        <div class="relative aspect-square overflow-hidden bg-surface-2">
          <img
            :src="resolveProductImage(product)"
            :alt="product.name"
            class="h-full w-full object-cover transition-transform duration-300"
            :class="product.stock <= 0 ? 'opacity-50' : 'group-hover:scale-105'"
            loading="lazy"
          />
          <span v-if="product.stock <= 0" class="absolute inset-0 grid place-items-center bg-surface/90 text-xs font-semibold text-ink">
            {{ t('productCatalog.outOfStock') }}
          </span>
          <span v-else-if="product.stock <= lowStockThreshold(product)" class="absolute right-2 top-2 rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-semibold text-white shadow-sm">
            {{ t('productCatalog.lowStockBadge') }}
          </span>
        </div>

        <div class="flex flex-1 flex-col gap-1 p-3">
          <p class="line-clamp-2 text-sm font-semibold leading-snug text-ink">{{ product.name }}</p>
          <p class="text-xs text-ink-faint">{{ product.category?.name ?? '-' }}</p>
          <p class="mt-auto pt-1.5 text-lg font-bold text-ink">{{ formatCurrency(product.selling_price) }}</p>
        </div>
      </button>
    </div>

    <div v-else class="space-y-2">
      <button
        v-for="product in filteredProducts"
        :key="product.id"
        class="flex w-full items-center gap-3 rounded-xl border px-3 py-2 text-left shadow-sm transition-all disabled:cursor-not-allowed"
        :class="product.stock > 0 && product.stock <= lowStockThreshold(product)
          ? 'border-amber-300 bg-amber-50'
          : 'border-line-soft bg-surface hover:bg-surface-2'"
        :disabled="product.stock <= 0"
        @click="emit('add', product)"
      >
        <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-surface-2">
          <img :src="resolveProductImage(product)" :alt="product.name" class="h-full w-full object-cover" :class="{ 'opacity-50': product.stock <= 0 }" loading="lazy" />
        </div>

        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-semibold text-ink">{{ product.name }}</p>
          <p class="text-xs text-ink-faint">{{ product.sku }} &middot; {{ product.category?.name ?? '-' }}</p>
        </div>

        <div class="shrink-0 text-right">
          <p class="text-sm font-semibold text-ink">{{ formatCurrency(product.selling_price) }}</p>
          <p class="flex items-center justify-end gap-1 text-xs" :class="product.stock <= 0 ? 'text-rose-600 dark:text-rose-400' : product.stock <= lowStockThreshold(product) ? 'font-semibold text-amber-600' : 'text-emerald-600'">
            <svg v-if="product.stock > 0 && product.stock <= lowStockThreshold(product)" class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM4.32 19.5h15.36c1.33 0 2.16-1.44 1.5-2.6L13.5 4.6c-.66-1.15-2.34-1.15-3 0L2.82 16.9c-.66 1.16.17 2.6 1.5 2.6z" />
            </svg>
            {{ product.stock <= 0 ? t('productCatalog.outOfStockLower') : product.stock <= lowStockThreshold(product) ? t('productCatalog.lowStockWithCount', { stock: product.stock }) : t('productCatalog.stockCount', { stock: product.stock }) }}
          </p>
        </div>

        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full border border-line-soft bg-surface text-base text-ink-faint shadow-sm">+</span>
      </button>
    </div>

    <p v-if="!loading && filteredProducts.length === 0" class="mt-4 text-sm text-ink-faint">
      {{ t('productCatalog.noResults') }}
    </p>
  </section>
</template>
