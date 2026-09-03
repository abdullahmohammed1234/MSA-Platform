<script setup lang="ts">
import { CheckCircle2 } from 'lucide-vue-next';
</script>

<template>
  <div class="max-w-2xl mx-auto px-4 py-16 text-center space-y-8">
    <div class="w-20 h-20 bg-emerald-50 text-emerald-600 rounded-3xl flex items-center justify-center mx-auto shadow-sm">
      <CheckCircle2 :size="48" />
    </div>

    <div class="space-y-3">
      <h1 class="text-3xl font-black text-neutral-black">Jazakullah Khair! Order Received</h1>
      <p class="text-sm text-neutral-muted max-w-md mx-auto">
        Your order has been recorded. Once payment is confirmed by Square, our team will begin preparing your merchandise for campus pickup.
      </p>
    </div>

    <div class="p-6 bg-white rounded-3xl border border-neutral-gray/20 text-left text-sm space-y-3 shadow-soft">
      <h4 class="font-bold text-neutral-black">Campus Pickup Instructions</h4>
      <ul class="text-xs text-neutral-muted space-y-2 list-disc list-inside">
        <li>Pick up your merchandise at the SFU Burnaby MSA Student Lounge.</li>
        <li>Present your full name and order confirmation email when collecting.</li>
        <li>You will receive an email update when your order is marked Ready for Pickup.</li>
      </ul>
    </div>

    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
      <router-link to="/store" class="w-full sm:w-auto px-8 py-3.5 bg-primary text-white rounded-2xl font-bold text-xs uppercase tracking-wider hover:bg-secondary transition-all">
        Back to Store
      </router-link>
      <router-link to="/store/my-orders" class="w-full sm:w-auto px-8 py-3.5 bg-white border border-neutral-gray/20 text-neutral-black rounded-2xl font-bold text-xs uppercase tracking-wider hover:bg-neutral-background transition-all">
        View My Orders
      </router-link>
    </div>
  </div>
</template>
