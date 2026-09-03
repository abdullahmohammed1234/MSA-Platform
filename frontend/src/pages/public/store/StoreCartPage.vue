<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ShoppingBag, Trash2, ArrowRight, ArrowLeft, RefreshCw } from 'lucide-vue-next';
import storeService from '@/services/storeService';
import { useToastStore } from '@/components/feedback/toast';
import type { StoreCart } from '@/types/store';

const router = useRouter();
const toast = useToastStore();

const cart = ref<StoreCart | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');

const fetchCart = async () => {
  isLoading.value = true;
  errorMessage.value = '';

  try {
    cart.value = await storeService.getCart();
  } catch (err: any) {
    errorMessage.value = err.message || 'Failed to load cart.';
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  void fetchCart();
});

const handleUpdateQuantity = async (itemUuid: string, newQty: number) => {
  try {
    cart.value = await storeService.updateCartItem(itemUuid, newQty);
  } catch (err: any) {
    toast.error(err.message || 'Failed to update item quantity.');
  }
};

const handleRemoveItem = async (itemUuid: string) => {
  try {
    cart.value = await storeService.removeCartItem(itemUuid);
    toast.success('Item removed from cart.');
  } catch (err: any) {
    toast.error(err.message || 'Failed to remove item.');
  }
};

const navigateToCheckout = () => {
  void router.push({ name: 'public-store-checkout' });
};
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    <div class="flex items-center justify-between border-b border-neutral-gray/10 pb-6">
      <div>
        <h1 class="text-3xl font-black text-neutral-black">Shopping Cart</h1>
        <p class="text-xs text-neutral-muted">Review your items before proceeding to payment.</p>
      </div>

      <router-link to="/store" class="text-xs font-bold text-primary flex items-center gap-1 hover:underline">
        <ArrowLeft :size="14" /> Continue Shopping
      </router-link>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="p-16 text-center text-neutral-muted space-y-3">
      <RefreshCw class="animate-spin mx-auto text-primary" :size="28" />
      <p class="text-sm font-medium">Loading shopping cart...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="!cart || cart.items.length === 0" class="text-center py-16 space-y-4 bg-white rounded-3xl border border-neutral-gray/20 p-8 shadow-soft">
      <div class="w-16 h-16 bg-primary/10 rounded-3xl flex items-center justify-center mx-auto text-primary">
        <ShoppingBag :size="32" />
      </div>
      <h3 class="text-lg font-bold text-neutral-black">Your cart is empty</h3>
      <p class="text-sm text-neutral-muted max-w-sm mx-auto">Explore our store and pick out official SFU MSA merchandise!</p>
      <router-link to="/store" class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-2xl font-bold text-xs uppercase tracking-wider hover:bg-secondary transition-all">
        Browse Products <ArrowRight :size="16" />
      </router-link>
    </div>

    <!-- Cart Content -->
    <div v-else class="space-y-6">
      <div class="bg-white rounded-3xl border border-neutral-gray/20 shadow-soft overflow-hidden divide-y divide-neutral-gray/10">
        <div v-for="item in cart.items" :key="item.uuid" class="p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div class="flex items-center gap-4">
            <div class="w-16 h-16 bg-neutral-background rounded-2xl overflow-hidden shrink-0 flex items-center justify-center border border-neutral-gray/10">
              <img v-if="item.image_url" :src="item.image_url" :alt="item.product_name" class="w-full h-full object-cover" />
              <ShoppingBag v-else class="text-neutral-muted opacity-40" :size="24" />
            </div>

            <div class="space-y-1">
              <h4 class="font-bold text-base text-neutral-black">{{ item.product_name }}</h4>
              <p v-if="item.variant_name" class="text-xs text-neutral-muted font-medium">Option: {{ item.variant_name }}</p>
              <p class="text-xs font-bold text-primary">{{ item.formatted_unit_price }} each</p>
            </div>
          </div>

          <div class="flex items-center justify-between sm:justify-end gap-6 w-full sm:w-auto">
            <!-- Quantity Control -->
            <div class="flex items-center border border-neutral-gray/20 rounded-xl overflow-hidden bg-neutral-background">
              <button @click="handleUpdateQuantity(item.uuid, item.quantity - 1)" class="px-3 py-1 font-bold text-xs hover:bg-neutral-gray/20 cursor-pointer">-</button>
              <span class="px-3 text-xs font-bold">{{ item.quantity }}</span>
              <button @click="handleUpdateQuantity(item.uuid, item.quantity + 1)" class="px-3 py-1 font-bold text-xs hover:bg-neutral-gray/20 cursor-pointer">+</button>
            </div>

            <span class="font-black text-base text-neutral-black">{{ item.formatted_line_total }}</span>

            <button @click="handleRemoveItem(item.uuid)" class="p-2 text-neutral-muted hover:text-red-600 rounded-xl transition-colors cursor-pointer">
              <Trash2 :size="18" />
            </button>
          </div>
        </div>
      </div>

      <!-- Order Summary Card -->
      <div class="bg-white rounded-3xl border border-neutral-gray/20 p-6 shadow-soft space-y-4 max-w-md ml-auto">
        <div class="flex items-center justify-between text-base font-bold text-neutral-black">
          <span>Subtotal</span>
          <span class="text-xl font-black text-primary">{{ cart.formatted_subtotal }}</span>
        </div>
        <p class="text-[11px] text-neutral-muted">Taxes and fulfillment details calculated at checkout.</p>
        <button
          @click="navigateToCheckout"
          class="w-full bg-primary text-white py-4 rounded-2xl font-bold uppercase tracking-[0.2em] text-xs hover:bg-secondary transition-all flex items-center justify-center gap-2 cursor-pointer border-none shadow-sm"
        >
          Proceed to Checkout <ArrowRight :size="16" />
        </button>
      </div>
    </div>
  </div>
</template>
