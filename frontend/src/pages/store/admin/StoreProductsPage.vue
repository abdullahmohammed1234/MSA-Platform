<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Plus, Search, Edit2, Trash2, RefreshCw, X, Eye } from 'lucide-vue-next';
import storeService from '@/services/storeService';
import cmsService from '@/services/cms/cmsService';
import { useToastStore } from '@/components/feedback/toast';
import type { StoreProduct } from '@/types/store';
import ImageInput from '@/components/admin/ImageInput.vue';

const toast = useToastStore();

const products = ref<StoreProduct[]>([]);
const isLoading = ref(true);
const search = ref('');
const isModalOpen = ref(false);
const editingProduct = ref<StoreProduct | null>(null);
const previewAudience = ref<'male' | 'female' | 'logged_out'>('logged_out');
const previewHovered = ref(false);

const form = ref({
  name: '',
  slug: '',
  description: '',
  price_dollars: 0,
  sku: '',
  status: 'active',
  has_variants: false,
  is_merchandise: false,
  inventory_quantity: 0,
  image_url: '',
  merchandise_images: {
    sister_front: '',
    sister_back: '',
    brother_front: '',
    brother_back: '',
    neutral_front: '',
    neutral_back: '',
  },
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
  previewAudience.value = 'logged_out';
  previewHovered.value = false;

  if (product) {
    const findSlotUrl = (type: string, view: string) => {
      if (product.merchandise_images && product.merchandise_images[`${type}_${view}`]) {
        return product.merchandise_images[`${type}_${view}`]?.image_url || '';
      }
      const found = product.images?.find(img => img.image_type === type && img.view_type === view);
      return found?.image_url || '';
    };

    form.value = {
      name: product.name,
      slug: product.slug,
      description: product.description || '',
      price_dollars: product.price_cents / 100,
      sku: product.sku || '',
      status: product.status,
      has_variants: product.has_variants,
      is_merchandise: Boolean(product.is_merchandise),
      inventory_quantity: product.inventory_quantity,
      image_url: product.primary_image_url || '',
      merchandise_images: {
        sister_front: findSlotUrl('sister', 'front'),
        sister_back: findSlotUrl('sister', 'back'),
        brother_front: findSlotUrl('brother', 'front'),
        brother_back: findSlotUrl('brother', 'back'),
        neutral_front: findSlotUrl('neutral', 'front'),
        neutral_back: findSlotUrl('neutral', 'back'),
      },
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
      is_merchandise: false,
      inventory_quantity: 10,
      image_url: '',
      merchandise_images: {
        sister_front: '',
        sister_back: '',
        brother_front: '',
        brother_back: '',
        neutral_front: '',
        neutral_back: '',
      },
      variants: [],
    };
  }
  isModalOpen.value = true;
};

const createSlotUploadFn = (imageType: string, viewType: string) => {
  return async (file: File) => {
    if (editingProduct.value) {
      const fd = new FormData();
      fd.append('file', file);
      fd.append('image_type', imageType);
      fd.append('view_type', viewType);
      const res = await storeService.uploadAdminProductImage(editingProduct.value.uuid, fd);
      return { url: res.data.image_url };
    } else {
      const res = await cmsService.uploadAsset(file);
      return { url: res.url };
    }
  };
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

  if (form.value.is_merchandise) {
    const required = [
      { key: 'sister_front', label: 'Sister Model Front' },
      { key: 'sister_back', label: 'Sister Model Back' },
      { key: 'brother_front', label: 'Brother Model Front' },
      { key: 'brother_back', label: 'Brother Model Back' },
      { key: 'neutral_front', label: 'Neutral Front' },
      { key: 'neutral_back', label: 'Neutral Back' },
    ];
    const missing = required.filter(r => !form.value.merchandise_images[r.key as keyof typeof form.value.merchandise_images]);
    if (missing.length > 0) {
      toast.error(`All 6 merchandise images are mandatory. Missing: ${missing.map(m => m.label).join(', ')}`);
      return;
    }
  }

  const payload: any = {
    name: form.value.name,
    slug: form.value.slug,
    description: form.value.description,
    price_cents: Math.round(form.value.price_dollars * 100),
    sku: form.value.sku,
    status: form.value.status,
    has_variants: form.value.has_variants,
    is_merchandise: form.value.is_merchandise,
    inventory_quantity: form.value.inventory_quantity,
    image_url: form.value.image_url,
    variants: form.value.variants.map(v => ({
      name: v.name,
      sku: v.sku,
      price_override_cents: v.price_override_dollars ? Math.round(v.price_override_dollars * 100) : null,
      inventory_quantity: v.inventory_quantity,
    })),
  };

  if (form.value.is_merchandise) {
    payload.merchandise_images = form.value.merchandise_images;
  }

  try {
    if (editingProduct.value) {
      await storeService.updateAdminProduct(editingProduct.value.uuid, payload);
      toast.success('Merchandise product updated!');
    } else {
      await storeService.createAdminProduct(payload);
      toast.success('Merchandise product created!');
    }
    isModalOpen.value = false;
    void fetchProducts();
  } catch (err: any) {
    toast.error(err.response?.data?.message || err.message || 'Failed to save product.');
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
                <span v-if="product.is_merchandise" class="ml-2 px-2 py-0.5 bg-primary/10 text-primary border border-primary/20 rounded-full text-[10px] font-bold">
                  Merch (6 Images)
                </span>
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

          <!-- Merchandise Toggle -->
          <div class="flex items-center gap-2 p-3.5 bg-primary/5 rounded-2xl border border-primary/20">
            <input type="checkbox" id="is_merchandise" v-model="form.is_merchandise" class="rounded cursor-pointer" />
            <label for="is_merchandise" class="font-bold text-neutral-black cursor-pointer text-xs">
              This is an Official Merchandise Product (Requires 6 Mandatory Audience-Specific Images)
            </label>
          </div>

          <!-- Non-Merchandise Single Image -->
          <div v-if="!form.is_merchandise">
            <ImageInput
              v-model="form.image_url"
              label="Product Image"
              hint="Upload an image directly from your device (drag & drop or click) or paste an image URL link."
              placeholder="Paste an image link (https://...) or upload a file"
              preview-class="w-full max-h-48 object-contain rounded-2xl bg-neutral-background"
            />
          </div>

          <!-- Merchandise 6-Image Grid -->
          <div v-else class="space-y-6 p-4 rounded-2xl border border-primary/20 bg-primary/[0.02]">
            <div class="border-b border-primary/10 pb-2">
              <h4 class="font-bold text-xs uppercase tracking-wider text-primary">
                Required Merchandise Imagery (All 6 Slots Mandatory)
              </h4>
              <p class="text-[11px] text-neutral-muted">
                Each type requires both a front and back view. Sister & Brother model images are strictly protected and streamed only to authorized audiences.
              </p>
            </div>

            <!-- Sister Model Images -->
            <div class="space-y-3 bg-white p-3.5 rounded-xl border border-purple-200 shadow-sm">
              <span class="text-[11px] font-bold uppercase tracking-wider text-purple-700 block">
                1. Sister Model Views (Delivered to female users only)
              </span>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <ImageInput
                  v-model="form.merchandise_images.sister_front"
                  :upload-fn="createSlotUploadFn('sister', 'front')"
                  label="Sister Model Front View *"
                  placeholder="Paste URL or upload sister front photo"
                  preview-class="w-full max-h-36 object-contain rounded-xl bg-neutral-background"
                />
                <ImageInput
                  v-model="form.merchandise_images.sister_back"
                  :upload-fn="createSlotUploadFn('sister', 'back')"
                  label="Sister Model Back View *"
                  placeholder="Paste URL or upload sister back photo"
                  preview-class="w-full max-h-36 object-contain rounded-xl bg-neutral-background"
                />
              </div>
            </div>

            <!-- Brother Model Images -->
            <div class="space-y-3 bg-white p-3.5 rounded-xl border border-blue-200 shadow-sm">
              <span class="text-[11px] font-bold uppercase tracking-wider text-blue-700 block">
                2. Brother Model Views (Delivered to male users only)
              </span>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <ImageInput
                  v-model="form.merchandise_images.brother_front"
                  :upload-fn="createSlotUploadFn('brother', 'front')"
                  label="Brother Model Front View *"
                  placeholder="Paste URL or upload brother front photo"
                  preview-class="w-full max-h-36 object-contain rounded-xl bg-neutral-background"
                />
                <ImageInput
                  v-model="form.merchandise_images.brother_back"
                  :upload-fn="createSlotUploadFn('brother', 'back')"
                  label="Brother Model Back View *"
                  placeholder="Paste URL or upload brother back photo"
                  preview-class="w-full max-h-36 object-contain rounded-xl bg-neutral-background"
                />
              </div>
            </div>

            <!-- Neutral Product Images -->
            <div class="space-y-3 bg-white p-3.5 rounded-xl border border-neutral-gray/20 shadow-sm">
              <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-black block">
                3. Neutral Product Views (Delivered to logged-out and unset-gender visitors)
              </span>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <ImageInput
                  v-model="form.merchandise_images.neutral_front"
                  :upload-fn="createSlotUploadFn('neutral', 'front')"
                  label="Neutral Product Front View *"
                  placeholder="Paste URL or upload neutral front photo"
                  preview-class="w-full max-h-36 object-contain rounded-xl bg-neutral-background"
                />
                <ImageInput
                  v-model="form.merchandise_images.neutral_back"
                  :upload-fn="createSlotUploadFn('neutral', 'back')"
                  label="Neutral Product Back View *"
                  placeholder="Paste URL or upload neutral back photo"
                  preview-class="w-full max-h-36 object-contain rounded-xl bg-neutral-background"
                />
              </div>
            </div>

            <!-- Audience Live Presentation Preview -->
            <div class="border-t border-primary/10 pt-4 space-y-3">
              <div class="flex items-center justify-between">
                <span class="font-bold text-xs uppercase tracking-wider text-neutral-black flex items-center gap-1.5">
                  <Eye :size="14" class="text-primary" /> Audience Presentation Preview
                </span>
                <div class="flex items-center gap-1 bg-neutral-background p-1 rounded-xl text-[10px] font-bold">
                  <button
                    type="button"
                    @click="previewAudience = 'logged_out'"
                    class="px-2 py-1 rounded-lg transition-all"
                    :class="previewAudience === 'logged_out' ? 'bg-primary text-white shadow-sm' : 'text-neutral-muted hover:text-neutral-black'"
                  >
                    Logged-out Public
                  </button>
                  <button
                    type="button"
                    @click="previewAudience = 'male'"
                    class="px-2 py-1 rounded-lg transition-all"
                    :class="previewAudience === 'male' ? 'bg-blue-600 text-white shadow-sm' : 'text-neutral-muted hover:text-neutral-black'"
                  >
                    Male Audience
                  </button>
                  <button
                    type="button"
                    @click="previewAudience = 'female'"
                    class="px-2 py-1 rounded-lg transition-all"
                    :class="previewAudience === 'female' ? 'bg-purple-600 text-white shadow-sm' : 'text-neutral-muted hover:text-neutral-black'"
                  >
                    Female Audience
                  </button>
                </div>
              </div>

              <!-- Preview Card -->
              <div
                @mouseenter="previewHovered = true"
                @mouseleave="previewHovered = false"
                class="bg-white border border-neutral-gray/20 rounded-2xl p-3 flex items-center gap-4 cursor-pointer hover:border-primary/40 transition-all max-w-sm mx-auto shadow-sm"
              >
                <div class="w-20 h-24 bg-neutral-background rounded-xl overflow-hidden relative flex items-center justify-center shrink-0">
                  <img
                    v-if="previewAudience === 'logged_out' && (form.merchandise_images.neutral_front || form.merchandise_images.neutral_back)"
                    :src="(previewHovered && form.merchandise_images.neutral_back) ? form.merchandise_images.neutral_back : (form.merchandise_images.neutral_front || form.merchandise_images.neutral_back)"
                    alt="Preview"
                    class="w-full h-full object-cover"
                  />
                  <img
                    v-else-if="previewAudience === 'male' && (form.merchandise_images.brother_front || form.merchandise_images.brother_back)"
                    :src="(previewHovered && form.merchandise_images.brother_back) ? form.merchandise_images.brother_back : (form.merchandise_images.brother_front || form.merchandise_images.brother_back)"
                    alt="Preview"
                    class="w-full h-full object-cover"
                  />
                  <img
                    v-else-if="previewAudience === 'female' && (form.merchandise_images.sister_front || form.merchandise_images.sister_back)"
                    :src="(previewHovered && form.merchandise_images.sister_back) ? form.merchandise_images.sister_back : (form.merchandise_images.sister_front || form.merchandise_images.sister_back)"
                    alt="Preview"
                    class="w-full h-full object-cover"
                  />
                  <span v-else class="text-[9px] text-neutral-muted p-1 text-center">Upload images</span>
                </div>
                <div class="space-y-1 text-left">
                  <span class="text-[9px] font-bold uppercase tracking-wider text-primary">
                    {{ previewHovered ? 'Hover / Back View' : 'Default / Front View' }}
                  </span>
                  <div class="font-bold text-xs text-neutral-black">{{ form.name || 'Sample Product' }}</div>
                  <div class="text-[10px] text-neutral-muted">Hover cursor to see back transition</div>
                </div>
              </div>
            </div>
          </div>

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
