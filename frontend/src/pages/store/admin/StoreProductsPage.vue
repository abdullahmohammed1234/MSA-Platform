<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Plus, Search, Edit2, Trash2, RefreshCw, X } from 'lucide-vue-next';
import storeService from '@/services/storeService';
import { useToastStore } from '@/components/feedback/toast';
import type { StoreProduct } from '@/types/store';
import ImageInput from '@/components/admin/ImageInput.vue';

const toast = useToastStore();

const products = ref<StoreProduct[]>([]);
const isLoading = ref(true);
const search = ref('');
const isModalOpen = ref(false);
const editingProduct = ref<StoreProduct | null>(null);

const form = ref({
  name: '',
  slug: '',
  description: '',
  price_dollars: 0,
  sku: '',
  status: 'active',
  has_variants: false,
  inventory_quantity: 0,
  image_url: '',
  variants: [] as Array<{ name: string; sku: string; price_override_dollars: number; inventory_quantity: number }>,
});

const fetchProducts = async () => {
  isLoading.value = true;

  try {
    const res = await storeService.getAdminProducts({ search: search.value });
    products.value = res.data;
  } catch (err: any) {
    toast.error('Failed to load products.');
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  void fetchProducts();
});

const openModal = (product: StoreProduct | null = null) => {
  editingProduct.value = product;
  if (product) {
    form.value = {
      name: product.name,
      slug: product.slug,
      description: product.description || '',
      price_dollars: product.price_cents / 100,
      sku: product.sku || '',
      status: product.status,
      has_variants: product.has_variants,
      inventory_quantity: product.inventory_quantity,
      image_url: product.primary_image_url || '',
      variants: product.variants.map(v => ({
        name: v.name,
        sku: v.sku || '',
        price_override_dollars: v.price_override_cents ? v.price_override_cents / 100 : 0,
        inventory_quantity: v.inventory_quantity,
      })),
    };
  } else {
    form.value = {
      name: '',
      slug: '',
      description: '',
      price_dollars: 29.99,
      sku: '',
      status: 'active',
      has_variants: false,
      inventory_quantity: 10,
      image_url: '',
      variants: [],
    };
  }
  isModalOpen.value = true;
};

const addVariantRow = () => {
  form.value.variants.push({
    name: '',
    sku: '',
    price_override_dollars: form.value.price_dollars,
    inventory_quantity: 10,
  });
};

const removeVariantRow = (index: number) => {
  form.value.variants.splice(index, 1);
};

const handleSave = async () => {
  if (!form.value.name) {
    toast.error('Product name is required.');
    return;
  }

  const payload = {
    name: form.value.name,
    slug: form.value.slug,
    description: form.value.description,
    price_cents: Math.round(form.value.price_dollars * 100),
    sku: form.value.sku,
    status: form.value.status,
    has_variants: form.value.has_variants,
    inventory_quantity: form.value.inventory_quantity,
    image_url: form.value.image_url,
    variants: form.value.variants.map(v => ({
      name: v.name,
      sku: v.sku,
      price_override_cents: v.price_override_dollars ? Math.round(v.price_override_dollars * 100) : null,
      inventory_quantity: v.inventory_quantity,
    })),
  };

  try {
    if (editingProduct.value) {
      await storeService.updateAdminProduct(editingProduct.value.uuid, payload);
      toast.success('Product updated!');
    } else {
      await storeService.createAdminProduct(payload);
      toast.success('Product created!');
    }
    isModalOpen.value = false;
    void fetchProducts();
  } catch (err: any) {
    toast.error(err.message || 'Failed to save product.');
  }
};

const handleDelete = async (product: StoreProduct) => {
  if (!confirm(`Archive product "${product.name}"?`)) return;

  try {
    await storeService.deleteAdminProduct(product.uuid);
    toast.success('Product archived.');
    void fetchProducts();
  } catch (err: any) {
    toast.error(err.message || 'Failed to archive product.');
  }
};
</script>

<template>
  <div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-neutral-gray/10 pb-6">
      <div>
        <h1 class="text-3xl font-black text-neutral-black">Products Management</h1>
        <p class="text-xs text-neutral-muted">Create, edit, and archive merchandise products and variants.</p>
      </div>

      <button
        @click="openModal()"
        class="bg-primary text-white px-5 py-3 rounded-2xl font-bold text-xs uppercase tracking-wider hover:bg-secondary transition-all inline-flex items-center gap-2 cursor-pointer shadow-sm"
      >
        <Plus :size="16" /> Add Product
      </button>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-3xl border border-neutral-gray/20 shadow-soft overflow-hidden space-y-4 p-6">
      <div class="relative max-w-md">
        <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-neutral-muted" :size="16" />
        <input
          type="text"
          v-model="search"
          @input="fetchProducts"
          placeholder="Search products by name..."
          class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl pl-11 pr-4 py-2.5 text-xs text-neutral-black outline-none"
        />
      </div>

      <div v-if="isLoading" class="p-8 text-center text-neutral-muted space-y-2">
        <RefreshCw class="animate-spin mx-auto text-primary" :size="24" />
        <p class="text-xs">Loading products...</p>
      </div>

      <div v-else-if="products.length === 0" class="p-8 text-center text-neutral-muted text-sm">
        No products found. Click "Add Product" to create your first item.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-neutral-background border-b border-neutral-gray/10 text-neutral-muted font-bold uppercase tracking-wider">
              <th class="p-3">Product</th>
              <th class="p-3">Price</th>
              <th class="p-3">Stock</th>
              <th class="p-3">Status</th>
              <th class="p-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-gray/10">
            <tr v-for="product in products" :key="product.uuid" class="hover:bg-neutral-background/40 transition-colors">
              <td class="p-3 font-semibold text-neutral-black">
                {{ product.name }}
                <span v-if="product.has_variants" class="ml-2 px-2 py-0.5 bg-neutral-background rounded-full text-[10px] text-neutral-muted">
                  {{ product.variants.length }} options
                </span>
              </td>
              <td class="p-3 font-bold text-primary">{{ product.formatted_price }}</td>
              <td class="p-3 font-semibold">{{ product.inventory_quantity }} units</td>
              <td class="p-3">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase border capitalize" :class="product.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-neutral-100 text-neutral-600 border-neutral-200'">
                  {{ product.status }}
                </span>
              </td>
              <td class="p-3 text-right space-x-2">
                <button @click="openModal(product)" class="p-1.5 text-neutral-muted hover:text-primary rounded-lg transition-colors cursor-pointer">
                  <Edit2 :size="16" />
                </button>
                <button @click="handleDelete(product)" class="p-1.5 text-neutral-muted hover:text-red-600 rounded-lg transition-colors cursor-pointer">
                  <Trash2 :size="16" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Product Modal -->
    <div v-if="isModalOpen" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 md:p-8 space-y-6 shadow-xl">
        <div class="flex items-center justify-between border-b border-neutral-gray/10 pb-4">
          <h3 class="font-bold text-lg text-neutral-black">
            {{ editingProduct ? 'Edit Product' : 'Add New Product' }}
          </h3>
          <button @click="isModalOpen = false" class="p-2 text-neutral-muted hover:text-neutral-black">
            <X :size="20" />
          </button>
        </div>

        <div class="space-y-4 text-xs">
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="font-bold uppercase tracking-wider text-neutral-muted">Product Name *</label>
              <input type="text" v-model="form.name" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-xl px-3 py-2 text-neutral-black outline-none" />
            </div>

            <div class="space-y-1">
              <label class="font-bold uppercase tracking-wider text-neutral-muted">Price ($ CAD) *</label>
              <input type="number" step="0.01" v-model="form.price_dollars" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-xl px-3 py-2 text-neutral-black outline-none" />
            </div>
          </div>

          <div class="space-y-1">
            <label class="font-bold uppercase tracking-wider text-neutral-muted">Description</label>
            <textarea v-model="form.description" rows="3" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-xl p-3 text-neutral-black outline-none resize-none" />
          </div>

          <div class="space-y-1">
            <label class="font-bold uppercase tracking-wider text-neutral-muted">Status</label>
            <select v-model="form.status" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-xl px-3 py-2 text-neutral-black outline-none">
              <option value="active">Active</option>
              <option value="draft">Draft</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>

          <ImageInput
            v-model="form.image_url"
            label="Product Image"
            hint="Upload an image directly from your device (drag & drop or click) or paste an image URL link."
            placeholder="Paste an image link (https://...) or upload a file"
            preview-class="w-full max-h-48 object-contain rounded-2xl bg-neutral-background"
          />

          <div class="flex items-center gap-2 pt-2">
            <input type="checkbox" id="has_variants" v-model="form.has_variants" class="rounded cursor-pointer" />
            <label for="has_variants" class="font-bold text-neutral-black cursor-pointer">Product has variants (Sizes, Colors, etc.)</label>
          </div>

          <div v-if="!form.has_variants" class="space-y-1">
            <label class="font-bold uppercase tracking-wider text-neutral-muted">Inventory Quantity</label>
            <input type="number" v-model="form.inventory_quantity" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-xl px-3 py-2 text-neutral-black outline-none" />
          </div>

          <!-- Variants Editor -->
          <div v-else class="space-y-3 pt-2 border-t border-neutral-gray/10">
            <div class="flex items-center justify-between">
              <label class="font-bold uppercase tracking-wider text-neutral-muted">Product Variants</label>
              <button @click="addVariantRow" class="text-xs font-bold text-primary hover:underline">+ Add Option</button>
            </div>

            <div v-for="(v, idx) in form.variants" :key="idx" class="flex items-center gap-2 bg-neutral-background p-2 rounded-xl">
              <input type="text" v-model="v.name" placeholder="Size/Color (e.g. Medium / Black)" class="flex-1 bg-white border border-neutral-gray/20 rounded-lg px-2 py-1" />
              <input type="number" v-model="v.inventory_quantity" placeholder="Qty" class="w-20 bg-white border border-neutral-gray/20 rounded-lg px-2 py-1" />
              <button @click="removeVariantRow(idx)" class="text-red-500 hover:text-red-700 p-1">
                <X :size="16" />
              </button>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-neutral-gray/10">
          <button @click="isModalOpen = false" class="px-5 py-2.5 bg-neutral-background text-neutral-black rounded-xl font-bold text-xs uppercase cursor-pointer">Cancel</button>
          <button @click="handleSave" class="px-6 py-2.5 bg-primary text-white rounded-xl font-bold text-xs uppercase hover:bg-secondary cursor-pointer">Save Product</button>
        </div>
      </div>
    </div>
  </div>
</template>
