<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { ShoppingBag, Search, Tag, ArrowRight, AlertCircle } from 'lucide-vue-next';
import storeService from '@/services/storeService';
import type { StoreProduct } from '@/types/store';

const router = useRouter();

const products = ref<StoreProduct[]>([]);
const isLoading = ref(true);
const errorMessage = ref('');
const search = ref('');
const sortBy = ref('created_at');

const fetchProducts = async () => {
  isLoading.value = true;
  errorMessage.value = '';

  try {
    const res = await storeService.getPublicProducts({
      search: search.value,
      sort_by: sortBy.value,
    });
    products.value = res.data;
  } catch (err: any) {
    errorMessage.value = err.message || 'Failed to load store products.';
  } finally {
    isLoading.value = false;
  }
};

let timeout: any = null;
watch(search, () => {
  clearTimeout(timeout);
  timeout = setTimeout(() => {
    void fetchProducts();
  }, 300);
});

watch(sortBy, () => {
  void fetchProducts();
});

onMounted(() => {
  void fetchProducts();
});

const activeSection = ref<'all' | 'merchandise' | 'general'>('all');
const hoveredProductUuid = ref<string | null>(null);

const toggleProductView = (uuid: string) => {
  hoveredProductUuid.value = hoveredProductUuid.value === uuid ? null : uuid;
};

const displayedProducts = computed(() => {
  if (activeSection.value === 'merchandise') {
    return products.value.filter(p => p.is_merchandise);
  }
  if (activeSection.value === 'general') {
    return products.value.filter(p => !p.is_merchandise);
  }
  return products.value;
});

const navigateToProduct = (slug: string) => {
  void router.push({ name: 'public-store-product-detail', params: { slug } });
};
</script>

<template>
  <div class="space-y-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Hero Banner -->
    <div class="bg-gradient-to-r from-primary via-secondary to-primary/90 rounded-3xl p-8 md:p-12 text-white shadow-soft relative overflow-hidden">
      <div class="relative z-10 space-y-4 max-w-2xl">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white/10 backdrop-blur-md rounded-full text-xs font-bold uppercase tracking-widest text-accent-gold border border-white/10">
          <Tag :size="14" class="text-accent-gold" /> Official SFU MSA Merch
        </div>
        <h1 class="text-3xl md:text-5xl font-display font-black tracking-tight leading-tight text-white">
          Wear Your Identity <span class="text-accent-gold italic font-serif font-light">with Pride</span>
        </h1>
        <p class="text-white/80 text-sm md:text-base leading-relaxed">
          High-quality hoodies, t-shirts, accessories, and MSA gear. All proceeds directly support SFU MSA campus programs and student initiatives.
        </p>
      </div>
      <ShoppingBag class="absolute -right-8 -bottom-8 w-64 h-64 text-white/5 pointer-events-none" />
    </div>

    <!-- Category Tabs -->
    <div class="flex items-center gap-2 border-b border-neutral-gray/15 pb-4">
      <button
        type="button"
        @click="activeSection = 'all'"
        class="px-5 py-2.5 rounded-2xl text-xs font-bold uppercase tracking-wider transition-all cursor-pointer"
        :class="activeSection === 'all'
          ? 'bg-primary text-white shadow-sm'
          : 'bg-white border border-neutral-gray/20 text-neutral-black hover:border-neutral-gray/40'"
      >
        All Items ({{ products.length }})
      </button>
      <button
        type="button"
        @click="activeSection = 'merchandise'"
        class="px-5 py-2.5 rounded-2xl text-xs font-bold uppercase tracking-wider transition-all cursor-pointer flex items-center gap-1.5"
        :class="activeSection === 'merchandise'
          ? 'bg-primary text-white shadow-sm'
          : 'bg-white border border-neutral-gray/20 text-neutral-black hover:border-neutral-gray/40'"
      >
        <Tag :size="13" />
        Merchandise Section ({{ products.filter(p => p.is_merchandise).length }})
      </button>
      <button
        type="button"
        @click="activeSection = 'general'"
        class="px-5 py-2.5 rounded-2xl text-xs font-bold uppercase tracking-wider transition-all cursor-pointer"
        :class="activeSection === 'general'
          ? 'bg-primary text-white shadow-sm'
          : 'bg-white border border-neutral-gray/20 text-neutral-black hover:border-neutral-gray/40'"
      >
        General Store ({{ products.filter(p => !p.is_merchandise).length }})
      </button>
    </div>

    <!-- Search & Sort Controls -->
    <div class="flex flex-col md:flex-row gap-4 items-stretch md:items-center justify-between">
      <div class="relative flex-1 max-w-md">
        <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-neutral-muted" :size="18" />
        <input
          type="text"
          v-model="search"
          placeholder="Search hoodies, t-shirts, gear..."
          class="w-full bg-white border border-neutral-gray/20 rounded-2xl pl-11 pr-4 py-3 text-sm text-neutral-black focus:ring-2 focus:ring-primary/20 outline-none"
        />
      </div>

      <div class="flex items-center gap-3">
        <label class="text-xs font-bold text-neutral-muted uppercase tracking-wider">Sort by:</label>
        <select
          v-model="sortBy"
          class="bg-white border border-neutral-gray/20 rounded-2xl px-4 py-2.5 text-sm text-neutral-black focus:ring-2 focus:ring-primary/20 outline-none cursor-pointer"
        >
          <option value="created_at">Newest Arrivals</option>
          <option value="price">Price</option>
        </select>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="errorMessage" class="p-6 bg-red-50 border border-red-500/20 text-red-600 rounded-3xl flex items-center justify-between">
      <div class="flex items-center gap-3">
        <AlertCircle :size="20" />
        <p class="text-sm font-medium">{{ errorMessage }}</p>
      </div>
      <button @click="fetchProducts" class="text-xs font-bold underline cursor-pointer">Retry</button>
    </div>

    <!-- Loading Grid -->
    <div v-if="isLoading" class="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
      <div v-for="i in 8" :key="i" class="bg-white rounded-3xl border border-neutral-gray/20 p-4 space-y-4 animate-pulse">
        <div class="w-full h-56 bg-neutral-gray/20 rounded-2xl" />
        <div class="h-4 bg-neutral-gray/20 rounded w-3/4" />
        <div class="h-4 bg-neutral-gray/20 rounded w-1/2" />
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="displayedProducts.length === 0" class="text-center py-16 space-y-4">
      <div class="w-16 h-16 bg-primary/10 rounded-3xl flex items-center justify-center mx-auto text-primary">
        <ShoppingBag :size="32" />
      </div>
      <h3 class="text-lg font-bold text-neutral-black">Coming soon ... Stay Tuned!</h3>
      <p class="text-sm text-neutral-muted">Check back soon for new merchandise drops!</p>
    </div>

    <!-- Products Grid -->
    <div v-else class="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
      <div
        v-for="product in displayedProducts"
        :key="product.uuid"
        @click="navigateToProduct(product.slug)"
        @mouseenter="hoveredProductUuid = product.uuid"
        @mouseleave="hoveredProductUuid = null"
        @focusin="hoveredProductUuid = product.uuid"
        @focusout="hoveredProductUuid = null"
        tabindex="0"
        role="button"
        :aria-label="`View details for ${product.name}`"
        class="group bg-white rounded-3xl border border-neutral-gray/20 p-4 shadow-soft hover:shadow-hover transition-all duration-300 cursor-pointer flex flex-col justify-between focus:ring-2 focus:ring-primary/30 outline-none"
      >
        <div class="space-y-4">
          <!-- Image with Smooth Front/Back Transition -->
          <div class="w-full h-56 bg-neutral-background rounded-2xl overflow-hidden relative flex items-center justify-center">
            <!-- Front View -->
            <img
              v-if="product.front_image_url || product.primary_image_url"
              :src="product.front_image_url || product.primary_image_url!"
              :alt="product.name"
              class="w-full h-full object-cover transition-all duration-500 ease-out"
              :class="hoveredProductUuid === product.uuid && product.back_image_url ? 'opacity-0 scale-95' : 'opacity-100 scale-100'"
              loading="lazy"
            />
            <!-- Back View -->
            <img
              v-if="product.back_image_url"
              :src="product.back_image_url"
              :alt="`${product.name} back view`"
              class="absolute inset-0 w-full h-full object-cover transition-all duration-500 ease-out"
              :class="hoveredProductUuid === product.uuid ? 'opacity-100 scale-100' : 'opacity-0 scale-105 pointer-events-none'"
              loading="lazy"
            />
            <ShoppingBag v-else-if="!product.front_image_url && !product.primary_image_url" class="text-neutral-muted opacity-40" :size="48" />

            <!-- Merchandise Badge -->
            <div v-if="product.is_merchandise" class="absolute top-3 left-3 bg-primary text-white text-[9px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full shadow-sm">
              Merchandise
            </div>

            <!-- Touch / Mobile Flip Button -->
            <button
              v-if="product.back_image_url"
              type="button"
              @click.stop="toggleProductView(product.uuid)"
              class="absolute bottom-2.5 right-2.5 bg-white/90 hover:bg-white text-neutral-black text-[9px] font-bold px-2 py-1 rounded-lg backdrop-blur-sm shadow-sm transition-all flex items-center gap-1 border border-neutral-gray/20"
              title="Flip view"
            >
              <span>{{ hoveredProductUuid === product.uuid ? 'Front' : 'Back' }}</span>
            </button>

            <div v-if="product.has_variants" class="absolute top-3 right-3 bg-white/90 backdrop-blur-md text-[10px] font-bold px-2.5 py-1 rounded-full text-neutral-black shadow-sm">
              Multiple Options
            </div>
          </div>

          <!-- Title & Price -->
          <div class="space-y-1">
            <h3 class="font-bold text-base text-neutral-black group-hover:text-primary transition-colors line-clamp-1">
              {{ product.name }}
            </h3>
            <p class="text-xs text-neutral-muted line-clamp-2">
              {{ product.description || 'Official SFU MSA merchandise.' }}
            </p>
          </div>
        </div>

        <div class="pt-4 border-t border-neutral-gray/10 flex items-center justify-between mt-4">
          <span class="text-lg font-black text-primary">{{ product.formatted_price }}</span>
          <span class="w-9 h-9 rounded-full bg-neutral-background group-hover:bg-primary group-hover:text-white flex items-center justify-center text-neutral-muted transition-all">
            <ArrowRight :size="16" />
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
