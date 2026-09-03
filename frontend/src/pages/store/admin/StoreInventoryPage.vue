<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { RefreshCw, Edit2, X } from 'lucide-vue-next';
import storeService from '@/services/storeService';
import { useToastStore } from '@/components/feedback/toast';
import type { StoreProduct, ProductVariant } from '@/types/store';

const toast = useToastStore();

const products = ref<StoreProduct[]>([]);
const isLoading = ref(true);
const isAdjusting = ref(false);

const selectedItem = ref<{ product: StoreProduct; variant?: ProductVariant } | null>(null);
const newQty = ref(0);
const reason = ref('');

const fetchInventory = async () => {
  isLoading.value = true;
  try {
    const res = await storeService.getAdminProducts({ per_page: 100 });
    products.value = res.data;
  } catch (err: any) {
    toast.error('Failed to load inventory.');
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  void fetchInventory();
});

const openAdjustModal = (product: StoreProduct, variant?: ProductVariant) => {
  selectedItem.value = { product, variant };
  newQty.value = variant ? variant.inventory_quantity : product.inventory_quantity;
  reason.value = 'Stock Reconciliation';
};

const handleSaveAdjustment = async () => {
  if (!selectedItem.value) return;

  isAdjusting.value = true;

  try {
    await storeService.adjustInventory({
      product_id: selectedItem.value.product.id,
      variant_id: selectedItem.value.variant?.id,
      new_quantity: newQty.value,
      reason: reason.value,
    });
    toast.success('Stock adjusted successfully.');
    selectedItem.value = null;
    void fetchInventory();
  } catch (err: any) {
    toast.error(err.message || 'Failed to adjust inventory.');
  } finally {
    isAdjusting.value = false;
  }
};
</script>

<template>
  <div class="space-y-8">
    <div class="border-b border-neutral-gray/10 pb-6">
      <h1 class="text-3xl font-black text-neutral-black">Inventory Tracking</h1>
      <p class="text-xs text-neutral-muted">Monitor stock levels across merchandise items and perform manual adjustments.</p>
    </div>

    <!-- Inventory Matrix Table -->
    <div class="bg-white rounded-3xl border border-neutral-gray/20 shadow-soft overflow-hidden p-6 space-y-4">
      <div v-if="isLoading" class="p-8 text-center text-neutral-muted space-y-2">
        <RefreshCw class="animate-spin mx-auto text-primary" :size="24" />
        <p class="text-xs">Loading inventory matrix...</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-neutral-background border-b border-neutral-gray/10 text-neutral-muted font-bold uppercase tracking-wider">
              <th class="p-3">Item / Variant</th>
              <th class="p-3">SKU</th>
              <th class="p-3">Current Stock</th>
              <th class="p-3">Status</th>
              <th class="p-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-gray/10">
            <template v-for="product in products" :key="product.uuid">
              <!-- Non-variant Product -->
              <tr v-if="!product.has_variants" class="hover:bg-neutral-background/40 transition-colors">
                <td class="p-3 font-semibold text-neutral-black">{{ product.name }}</td>
                <td class="p-3 font-mono text-neutral-muted">{{ product.sku || '—' }}</td>
                <td class="p-3 font-bold" :class="product.inventory_quantity <= 5 ? 'text-amber-600' : 'text-neutral-black'">
                  {{ product.inventory_quantity }} units
                </td>
                <td class="p-3">
                  <span v-if="product.inventory_quantity > 0" class="text-emerald-600 font-bold">In Stock</span>
                  <span v-else class="text-red-600 font-bold">Out of Stock</span>
                </td>
                <td class="p-3 text-right">
                  <button @click="openAdjustModal(product)" class="p-1.5 text-neutral-muted hover:text-primary rounded-lg transition-colors cursor-pointer">
                    <Edit2 :size="16" />
                  </button>
                </td>
              </tr>

              <!-- Variant Product Options -->
              <template v-else>
                <tr v-for="variant in product.variants" :key="variant.uuid" class="hover:bg-neutral-background/40 transition-colors">
                  <td class="p-3 font-medium text-neutral-black pl-6">
                    <span class="font-bold">{{ product.name }}</span> — {{ variant.name }}
                  </td>
                  <td class="p-3 font-mono text-neutral-muted">{{ variant.sku || product.sku || '—' }}</td>
                  <td class="p-3 font-bold" :class="variant.inventory_quantity <= 5 ? 'text-amber-600' : 'text-neutral-black'">
                    {{ variant.inventory_quantity }} units
                  </td>
                  <td class="p-3">
                    <span v-if="variant.inventory_quantity > 0" class="text-emerald-600 font-bold">In Stock</span>
                    <span v-else class="text-red-600 font-bold">Out of Stock</span>
                  </td>
                  <td class="p-3 text-right">
                    <button @click="openAdjustModal(product, variant)" class="p-1.5 text-neutral-muted hover:text-primary rounded-lg transition-colors cursor-pointer">
                      <Edit2 :size="16" />
                    </button>
                  </td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Adjust Modal -->
    <div v-if="selectedItem" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl max-w-md w-full p-6 space-y-6 shadow-xl text-xs">
        <div class="flex items-center justify-between border-b border-neutral-gray/10 pb-3">
          <h3 class="font-bold text-base text-neutral-black">Adjust Inventory Stock</h3>
          <button @click="selectedItem = null" class="text-neutral-muted hover:text-neutral-black">
            <X :size="18" />
          </button>
        </div>

        <div class="space-y-3">
          <p class="font-semibold text-neutral-black">
            {{ selectedItem.product.name }}
            <span v-if="selectedItem.variant">({{ selectedItem.variant.name }})</span>
          </p>

          <div class="space-y-1">
            <label class="font-bold uppercase tracking-wider text-neutral-muted">New Quantity</label>
            <input type="number" min="0" v-model="newQty" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-xl px-3 py-2 outline-none font-bold" />
          </div>

          <div class="space-y-1">
            <label class="font-bold uppercase tracking-wider text-neutral-muted">Reason for Adjustment</label>
            <input type="text" v-model="reason" placeholder="e.g. Stock Count Reconciliation" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-xl px-3 py-2 outline-none" />
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-3 border-t border-neutral-gray/10">
          <button @click="selectedItem = null" class="px-4 py-2 bg-neutral-background rounded-xl font-bold uppercase">Cancel</button>
          <button @click="handleSaveAdjustment" :disabled="isAdjusting" class="px-5 py-2 bg-primary text-white rounded-xl font-bold uppercase hover:bg-secondary">
            Save Adjustment
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
