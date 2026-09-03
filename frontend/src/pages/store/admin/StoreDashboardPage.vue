<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Package, ShoppingBasket, DollarSign, AlertTriangle, RefreshCw } from 'lucide-vue-next';
import storeService from '@/services/storeService';
import type { StoreProduct, StoreOrder } from '@/types/store';

const products = ref<StoreProduct[]>([]);
const recentOrders = ref<StoreOrder[]>([]);
const isLoading = ref(true);

const totalRevenueCents = ref(0);
const pendingOrdersCount = ref(0);
const lowStockCount = ref(0);

const fetchDashboardData = async () => {
  isLoading.value = true;

  try {
    const [pRes, oRes] = await Promise.all([
      storeService.getAdminProducts({ per_page: 100 }),
      storeService.getAdminOrders({ per_page: 10 }),
    ]);

    products.value = pRes.data;
    recentOrders.value = oRes.data;

    // Calculate metrics
    totalRevenueCents.value = oRes.data
      .filter((o: StoreOrder) => o.payment_status === 'paid')
      .reduce((sum: number, o: StoreOrder) => sum + o.total_cents, 0);

    pendingOrdersCount.value = oRes.data.filter((o: StoreOrder) => o.fulfillment_status === 'pending' || o.fulfillment_status === 'preparing').length;
    lowStockCount.value = pRes.data.filter((p: StoreProduct) => p.inventory_quantity <= 5).length;
  } catch (err: any) {
    // Graceful fallback
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  void fetchDashboardData();
});

const formatMoney = (cents: number) => '$' + number_format(cents / 100, 2);
function number_format(num: number, decimals: number) {
  return num.toFixed(decimals);
}
</script>

<template>
  <div class="space-y-8">
    <div class="border-b border-neutral-gray/10 pb-6">
      <h1 class="text-3xl font-black text-neutral-black">Store Dashboard</h1>
      <p class="text-xs text-neutral-muted">Overview of store sales, recent merchandise orders, and stock alerts.</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div class="bg-white rounded-3xl border border-neutral-gray/20 p-6 shadow-soft space-y-2">
        <div class="flex items-center justify-between text-neutral-muted">
          <span class="text-xs font-bold uppercase tracking-wider">Total Revenue</span>
          <div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl"><DollarSign :size="18" /></div>
        </div>
        <p class="text-2xl font-black text-neutral-black">{{ formatMoney(totalRevenueCents) }}</p>
      </div>

      <div class="bg-white rounded-3xl border border-neutral-gray/20 p-6 shadow-soft space-y-2">
        <div class="flex items-center justify-between text-neutral-muted">
          <span class="text-xs font-bold uppercase tracking-wider">Pending Orders</span>
          <div class="p-2 bg-blue-50 text-blue-600 rounded-xl"><ShoppingBasket :size="18" /></div>
        </div>
        <p class="text-2xl font-black text-neutral-black">{{ pendingOrdersCount }}</p>
      </div>

      <div class="bg-white rounded-3xl border border-neutral-gray/20 p-6 shadow-soft space-y-2">
        <div class="flex items-center justify-between text-neutral-muted">
          <span class="text-xs font-bold uppercase tracking-wider">Total Products</span>
          <div class="p-2 bg-primary/10 text-primary rounded-xl"><Package :size="18" /></div>
        </div>
        <p class="text-2xl font-black text-neutral-black">{{ products.length }}</p>
      </div>

      <div class="bg-white rounded-3xl border border-neutral-gray/20 p-6 shadow-soft space-y-2">
        <div class="flex items-center justify-between text-neutral-muted">
          <span class="text-xs font-bold uppercase tracking-wider">Low Stock Alerts</span>
          <div class="p-2 bg-amber-50 text-amber-600 rounded-xl"><AlertTriangle :size="18" /></div>
        </div>
        <p class="text-2xl font-black text-neutral-black">{{ lowStockCount }}</p>
      </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="bg-white rounded-3xl border border-neutral-gray/20 shadow-soft overflow-hidden space-y-4 p-6">
      <div class="flex items-center justify-between">
        <h3 class="font-bold text-base text-neutral-black">Recent Orders</h3>
        <router-link to="/store/admin/orders" class="text-xs font-bold text-primary hover:underline">View All Orders &rarr;</router-link>
      </div>

      <div v-if="isLoading" class="p-8 text-center text-neutral-muted space-y-2">
        <RefreshCw class="animate-spin mx-auto text-primary" :size="24" />
        <p class="text-xs">Loading dashboard data...</p>
      </div>

      <div v-else-if="recentOrders.length === 0" class="p-8 text-center text-neutral-muted text-sm">
        No merchandise orders recorded yet.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-neutral-background border-b border-neutral-gray/10 text-neutral-muted font-bold uppercase tracking-wider">
              <th class="p-3">Order #</th>
              <th class="p-3">Customer</th>
              <th class="p-3">Total</th>
              <th class="p-3">Payment</th>
              <th class="p-3">Fulfillment</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-gray/10">
            <tr v-for="order in recentOrders" :key="order.uuid" class="hover:bg-neutral-background/40 transition-colors">
              <td class="p-3 font-mono font-bold text-primary">{{ order.order_number }}</td>
              <td class="p-3 text-neutral-black">{{ order.customer_name }}</td>
              <td class="p-3 font-bold text-neutral-black">{{ order.formatted_total }}</td>
              <td class="p-3 capitalize font-semibold">{{ order.payment_status }}</td>
              <td class="p-3 capitalize font-semibold">{{ order.fulfillment_status }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
