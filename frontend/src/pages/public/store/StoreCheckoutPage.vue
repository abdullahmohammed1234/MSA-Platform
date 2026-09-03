<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ShieldCheck, Lock, CreditCard, ArrowLeft } from 'lucide-vue-next';
import storeService from '@/services/storeService';
import { useToastStore } from '@/components/feedback/toast';
import { useAuthStore } from '@/stores/auth';
import type { StoreCart } from '@/types/store';

const router = useRouter();
const toast = useToastStore();
const authStore = useAuthStore();

const cart = ref<StoreCart | null>(null);
const isLoading = ref(true);
const isSubmitting = ref(false);

const customerName = ref(authStore.user?.name || '');
const customerEmail = ref(authStore.user?.email || '');
const customerPhone = ref('');
const notes = ref('');

onMounted(async () => {
  try {
    cart.value = await storeService.getCart();
    if (!cart.value || cart.value.items.length === 0) {
      void router.push({ name: 'public-store-cart' });
    }
  } catch (err: any) {
    toast.error('Failed to load cart for checkout.');
  } finally {
    isLoading.value = false;
  }
});

const handleProcessCheckout = async () => {
  if (!customerName.value || !customerEmail.value) {
    toast.error('Please fill in your name and email address.');
    return;
  }

  isSubmitting.value = true;

  try {
    const result = await storeService.checkout({
      customer_name: customerName.value,
      customer_email: customerEmail.value,
      customer_phone: customerPhone.value,
      notes: notes.value,
      redirect_url: window.location.origin + '/store/checkout/success',
    });

    toast.success('Order created! Redirecting to Square secure payment...');
    window.location.href = result.checkout_url;
  } catch (err: any) {
    toast.error(err.message || 'Checkout failed. Please review your cart.');
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    <router-link to="/store/cart" class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-neutral-muted hover:text-primary transition-colors">
      <ArrowLeft :size="16" /> Back to Cart
    </router-link>

    <div class="border-b border-neutral-gray/10 pb-6">
      <h1 class="text-3xl font-black text-neutral-black">Store Checkout</h1>
      <p class="text-xs text-neutral-muted">Fulfillment via SFU Burnaby Campus Pickup. Payment processed securely via Square.</p>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="p-16 text-center text-neutral-muted space-y-3">
      <div class="w-8 h-8 border-3 border-primary/30 border-t-primary rounded-full animate-spin mx-auto" />
      <p class="text-sm font-medium">Preparing checkout...</p>
    </div>

    <div v-else-if="cart" class="grid lg:grid-cols-3 gap-8 items-start">
      <!-- Left: Form -->
      <div class="lg:col-span-2 bg-white rounded-3xl border border-neutral-gray/20 p-6 md:p-8 shadow-soft space-y-6">
        <h3 class="font-bold text-base text-neutral-black flex items-center gap-2 border-b border-neutral-gray/10 pb-4">
          <ShieldCheck :size="18" class="text-primary" /> Contact & Pickup Details
        </h3>

        <div class="space-y-4 text-sm">
          <div class="space-y-1">
            <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Full Name *</label>
            <input
              type="text"
              v-model="customerName"
              placeholder="e.g. Ahmad Ali"
              class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl px-4 py-3 text-neutral-black outline-none focus:ring-2 focus:ring-primary/20"
            />
          </div>

          <div class="space-y-1">
            <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Email Address *</label>
            <input
              type="email"
              v-model="customerEmail"
              placeholder="e.g. ahmad@sfu.ca"
              class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl px-4 py-3 text-neutral-black outline-none focus:ring-2 focus:ring-primary/20"
            />
          </div>

          <div class="space-y-1">
            <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Phone Number (Optional)</label>
            <input
              type="tel"
              v-model="customerPhone"
              placeholder="e.g. 778-123-4567"
              class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl px-4 py-3 text-neutral-black outline-none focus:ring-2 focus:ring-primary/20"
            />
          </div>

          <div class="space-y-1">
            <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Pickup Notes / Instructions</label>
            <textarea
              v-model="notes"
              rows="3"
              placeholder="Special instructions or preferred pickup day..."
              class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl p-4 text-neutral-black outline-none focus:ring-2 focus:ring-primary/20 resize-none"
            />
          </div>
        </div>

        <div class="p-4 bg-primary/5 rounded-2xl border border-primary/10 text-xs text-neutral-black space-y-1">
          <span class="font-bold text-primary flex items-center gap-1.5"><Lock :size="14" /> Campus Pickup Info</span>
          <p>Orders can be collected at the SFU Burnaby MSA Student Lounge or at Friday Jumuah prayer tables once marked Ready for Pickup.</p>
        </div>
      </div>

      <!-- Right: Summary & Payment -->
      <div class="bg-white rounded-3xl border border-neutral-gray/20 p-6 md:p-8 shadow-soft space-y-6">
        <h3 class="font-bold text-base text-neutral-black border-b border-neutral-gray/10 pb-4">
          Order Summary ({{ cart.item_count }} items)
        </h3>

        <div class="space-y-3 text-xs divide-y divide-neutral-gray/10">
          <div v-for="item in cart.items" :key="item.uuid" class="pt-3 first:pt-0 flex items-center justify-between">
            <div>
              <p class="font-bold text-neutral-black">{{ item.product_name }}</p>
              <p class="text-neutral-muted">{{ item.quantity }}x {{ item.variant_name ? `(${item.variant_name})` : '' }}</p>
            </div>
            <span class="font-bold text-neutral-black">{{ item.formatted_line_total }}</span>
          </div>
        </div>

        <div class="pt-4 border-t border-neutral-gray/10 flex items-center justify-between text-base font-bold text-neutral-black">
          <span>Total CAD</span>
          <span class="text-2xl font-black text-primary">{{ cart.formatted_subtotal }}</span>
        </div>

        <button
          @click="handleProcessCheckout"
          :disabled="isSubmitting"
          class="w-full bg-primary text-white py-4 rounded-2xl font-bold uppercase tracking-[0.2em] text-xs hover:bg-secondary transition-all flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer border-none shadow-sm"
        >
          <div v-if="isSubmitting" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
          <template v-else>
            <CreditCard :size="18" /> Pay with Square
          </template>
        </button>
      </div>
    </div>
  </div>
</template>
