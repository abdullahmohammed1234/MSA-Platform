<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ShoppingCart, AlertCircle, ShoppingBag, ArrowLeft } from 'lucide-vue-next';
import storeService from '@/services/storeService';
import { useToastStore } from '@/components/feedback/toast';
import type { StoreProduct, ProductVariant } from '@/types/store';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const product = ref<StoreProduct | null>(null);
const selectedVariant = ref<ProductVariant | null>(null);
const quantity = ref(1);
const isLoading = ref(true);
const isAdding = ref(false);
const errorMessage = ref('');

const fetchProduct = async () => {
  const slug = route.params.slug as string;
  if (!slug) return;

  isLoading.value = true;
  errorMessage.value = '';

  try {
    const data = await storeService.getPublicProductBySlug(slug);
    product.value = data;
    if (data.has_variants && data.variants.length > 0) {
      selectedVariant.value = data.variants.find(v => v.is_active) || data.variants[0];
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'Failed to load product details.';
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  void fetchProduct();
});

const currentPrice = computed(() => {
  if (selectedVariant.value) {
    return selectedVariant.value.formatted_price;
  }
  return product.value?.formatted_price || '$0.00';
});

const availableStock = computed(() => {
  if (selectedVariant.value) {
    return selectedVariant.value.inventory_quantity;
  }
  return product.value?.inventory_quantity || 0;
});

const handleAddToCart = async () => {
  if (!product.value) return;

  if (product.value.has_variants && !selectedVariant.value) {
    toast.error('Please select an option.');
    return;
  }

  isAdding.value = true;

  try {
    await storeService.addToCart(
      product.value.id,
      selectedVariant.value?.id,
      quantity.value
    );
    toast.success(`Added ${product.value.name} to cart!`);
    void router.push({ name: 'public-store-cart' });
  } catch (err: any) {
    toast.error(err.message || 'Failed to add item to cart.');
  } finally {
    isAdding.value = false;
  }
};
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    <!-- Back link -->
    <router-link to="/store" class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-neutral-muted hover:text-primary transition-colors">
      <ArrowLeft :size="16" /> Back to Store
    </router-link>

    <!-- Loading -->
    <div v-if="isLoading" class="p-16 text-center text-neutral-muted space-y-3">
      <div class="w-8 h-8 border-3 border-primary/30 border-t-primary rounded-full animate-spin mx-auto" />
      <p class="text-sm font-medium">Loading product details...</p>
    </div>

    <!-- Error -->
    <div v-else-if="errorMessage" class="p-6 bg-red-50 border border-red-500/20 text-red-600 rounded-3xl space-y-3">
      <div class="flex items-center gap-3">
        <AlertCircle :size="24" />
        <p class="font-bold text-base">{{ errorMessage }}</p>
      </div>
    </div>

    <!-- Product View -->
    <div v-else-if="product" class="grid lg:grid-cols-2 gap-12 items-start">
      <!-- Image Column -->
      <div class="space-y-4">
        <div class="w-full h-96 bg-white rounded-3xl border border-neutral-gray/20 overflow-hidden p-4 shadow-soft flex items-center justify-center">
          <img
            v-if="product.primary_image_url"
            :src="product.primary_image_url"
            :alt="product.name"
            class="w-full h-full object-cover rounded-2xl"
          />
          <ShoppingBag v-else class="text-neutral-muted opacity-40" :size="64" />
        </div>
      </div>

      <!-- Info & Actions Column -->
      <div class="space-y-8 bg-white rounded-3xl border border-neutral-gray/20 p-8 shadow-soft">
        <div class="space-y-2 border-b border-neutral-gray/10 pb-6">
          <span class="text-xs font-bold uppercase tracking-wider text-primary">SFU MSA Merchandise</span>
          <h1 class="text-3xl font-black text-neutral-black">{{ product.name }}</h1>
          <p class="text-2xl font-extrabold text-primary">{{ currentPrice }}</p>
        </div>

        <p class="text-sm text-neutral-black leading-relaxed whitespace-pre-wrap">
          {{ product.description || 'Official SFU MSA merchandise product.' }}
        </p>

        <!-- Variants Selection -->
        <div v-if="product.has_variants && product.variants.length > 0" class="space-y-3">
          <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Select Option / Size</label>
          <div class="grid grid-cols-2 gap-3">
            <button
              v-for="v in product.variants"
              :key="v.id"
              @click="selectedVariant = v"
              :class="[
                'p-3 rounded-2xl border text-xs font-bold transition-all cursor-pointer text-left',
                selectedVariant?.id === v.id
                  ? 'border-primary bg-primary/5 text-primary shadow-sm'
                  : 'border-neutral-gray/20 text-neutral-black hover:border-primary/40',
              ]"
            >
              <div>{{ v.name }}</div>
              <div class="text-[10px] text-neutral-muted font-normal mt-0.5">{{ v.formatted_price }}</div>
            </button>
          </div>
        </div>

        <!-- Stock Availability -->
        <div class="flex items-center gap-2 text-xs">
          <span :class="['w-2.5 h-2.5 rounded-full', availableStock > 0 ? 'bg-emerald-500' : 'bg-red-500']" />
          <span class="font-bold text-neutral-black">
            {{ availableStock > 0 ? `${availableStock} units in stock` : 'Out of Stock' }}
          </span>
        </div>

        <!-- Quantity & Add to Cart -->
        <div class="space-y-4 pt-4 border-t border-neutral-gray/10">
          <div class="flex items-center gap-4">
            <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Quantity</label>
            <div class="flex items-center border border-neutral-gray/20 rounded-2xl overflow-hidden bg-neutral-background">
              <button
                :disabled="quantity <= 1"
                @click="quantity--"
                class="px-4 py-2 font-bold hover:bg-neutral-gray/20 disabled:opacity-40 cursor-pointer"
              >
                -
              </button>
              <span class="px-4 text-sm font-bold">{{ quantity }}</span>
              <button
                :disabled="quantity >= availableStock"
                @click="quantity++"
                class="px-4 py-2 font-bold hover:bg-neutral-gray/20 disabled:opacity-40 cursor-pointer"
              >
                +
              </button>
            </div>
          </div>

          <button
            @click="handleAddToCart"
            :disabled="availableStock <= 0 || isAdding"
            class="w-full bg-primary text-white py-4 rounded-2xl font-bold uppercase tracking-[0.2em] text-xs hover:bg-secondary transition-all flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer border-none shadow-sm"
          >
            <div v-if="isAdding" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            <template v-else>
              <ShoppingCart :size="18" /> Add to Cart
            </template>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
