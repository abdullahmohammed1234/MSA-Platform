<script setup lang="ts">
import { ref } from 'vue';
import { PackagePlus, Search, Plus, Printer } from 'lucide-vue-next';
import CameraBarcodeScanner from '@/components/mlibms/CameraBarcodeScanner.vue';
import mlibmsAdminService from '@/services/mlibms/mlibmsAdminService';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

const isbnInput = ref('');
const isSearching = ref(false);
const isSubmitting = ref(false);

const lookupResult = ref<any>(null);

const form = ref({
  title: '',
  subtitle: '',
  isbn_13: '',
  isbn_10: '',
  author_names: [''],
  publisher_name: '',
  publication_year: null as number | null,
  edition: '',
  language: 'English',
  summary: '',
  cover_image_url: '',
  default_loan_days: 14,
  is_reference_only: false,
  copies_count: 1,
  condition: 'good',
});

const createdBook = ref<any>(null);

const handleLookup = async () => {
  if (!isbnInput.value.trim() || isSearching.value) return;
  isSearching.value = true;
  lookupResult.value = null;
  createdBook.value = null;

  try {
    const res = await mlibmsAdminService.lookupIsbn(isbnInput.value.trim());
    lookupResult.value = res;

    if (res.exists_in_catalog && res.data) {
      toast.info('Book already exists in local catalog.');
      form.value.title = res.data.title;
      form.value.isbn_13 = res.data.isbn_13 || '';
      form.value.isbn_10 = res.data.isbn_10 || '';
    } else if (res.suggested_data) {
      toast.success('Found metadata online! Fields pre-populated.');
      const sug = res.suggested_data;
      form.value.title = sug.title || '';
      form.value.subtitle = sug.subtitle || '';
      form.value.isbn_13 = sug.isbn_13 || isbnInput.value.trim();
      form.value.isbn_10 = sug.isbn_10 || '';
      form.value.author_names = sug.authors && sug.authors.length > 0 ? sug.authors : [''];
      form.value.publisher_name = sug.publishers && sug.publishers.length > 0 ? sug.publishers[0] : '';
      form.value.publication_year = sug.publication_year || null;
      form.value.cover_image_url = sug.cover_image_url || '';
      form.value.summary = sug.summary || '';
    } else {
      toast.info('ISBN not found. Please enter details manually.');
      form.value.isbn_13 = isbnInput.value.trim();
    }
  } catch (e) {
    toast.error('ISBN lookup failed.');
  } finally {
    isSearching.value = false;
  }
};

const handleCameraIsbnScan = (isbn: string) => {
  isbnInput.value = isbn;
  handleLookup();
};

const addAuthorField = () => {
  form.value.author_names.push('');
};

const removeAuthorField = (index: number) => {
  form.value.author_names.splice(index, 1);
};

const handleSubmitIntake = async () => {
  if (!form.value.title.trim()) {
    toast.error('Book title is required.');
    return;
  }

  isSubmitting.value = true;
  try {
    const copies = [];
    for (let i = 0; i < form.value.copies_count; i++) {
      copies.push({
        condition: form.value.condition,
        notes: `Initial intake batch copy ${i + 1}`,
      });
    }

    const payload = {
      title: form.value.title,
      subtitle: form.value.subtitle,
      isbn_13: form.value.isbn_13,
      isbn_10: form.value.isbn_10,
      author_names: form.value.author_names.filter(a => a.trim() !== ''),
      publisher_name: form.value.publisher_name,
      publication_year: form.value.publication_year,
      edition: form.value.edition,
      language: form.value.language,
      summary: form.value.summary,
      cover_image_url: form.value.cover_image_url,
      default_loan_days: form.value.default_loan_days,
      is_reference_only: form.value.is_reference_only,
      copies: copies,
    };

    const res = await mlibmsAdminService.storeIntake(payload);
    createdBook.value = res.data;
    toast.success('Book and physical copy inventory created successfully!');
  } catch (e: any) {
    toast.error(e.response?.data?.message || 'Intake failed.');
  } finally {
    isSubmitting.value = false;
  }
};

const printBarcodeLabels = () => {
  window.print();
};
</script>

<template>
  <div class="space-y-8">
    <div>
      <h1 class="text-2xl font-display font-bold text-neutral-black flex items-center space-x-2">
        <PackagePlus class="w-6 h-6 text-primary" />
        <span>Book Intake & ISBN Scanner Workbench</span>
      </h1>
      <p class="text-xs text-neutral-muted mt-1">Scan book ISBN via camera or USB scanner, verify bibliographic metadata, add physical copy inventory, and generate barcode labels.</p>
    </div>

    <!-- Step 1: ISBN Scanner Input -->
    <div class="bg-white border border-neutral-ivory rounded-2xl p-6 space-y-6 shadow-soft">
      <h3 class="text-xs font-bold text-neutral-muted uppercase tracking-wider">Step 1: Scan Book ISBN</h3>
      
      <!-- Integrated Camera Scanner -->
      <div class="border border-neutral-ivory rounded-xl p-4 bg-neutral-background max-w-lg mx-auto">
        <div class="text-xs font-bold uppercase tracking-wider text-neutral-muted text-center mb-2">
          Live Camera ISBN Scanner
        </div>
        <CameraBarcodeScanner @scan-success="handleCameraIsbnScan" />
      </div>

      <form @submit.prevent="handleLookup" class="flex flex-col sm:flex-row gap-3 pt-2">
        <div class="relative flex-1">
          <Search class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-muted" />
          <input
            v-model="isbnInput"
            type="text"
            placeholder="Or type/scan ISBN-13 or ISBN-10 barcode (e.g. 9780132350884)..."
            class="w-full pl-11 pr-4 py-2.5 bg-white border border-neutral-ivory rounded-xl text-neutral-black placeholder-neutral-muted font-mono text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
          />
        </div>
        <button
          type="submit"
          :disabled="isSearching || !isbnInput"
          class="px-6 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-bold text-sm transition-all shadow-soft disabled:opacity-50 flex items-center justify-center space-x-2 shrink-0"
        >
          <span>{{ isSearching ? 'Searching...' : 'Scan / Lookup' }}</span>
        </button>
      </form>

      <div v-if="lookupResult" :class="['p-4 rounded-xl border text-xs font-medium', lookupResult.exists_in_catalog ? 'bg-amber-50 border-amber-200 text-amber-900' : 'bg-emerald-50 border-emerald-200 text-emerald-900']">
        <p class="font-bold">{{ lookupResult.message }}</p>
      </div>
    </div>

    <!-- Step 2: Bibliographic Form -->
    <div class="bg-white border border-neutral-ivory rounded-2xl p-6 space-y-6 shadow-soft">
      <h3 class="text-xs font-bold text-neutral-muted uppercase tracking-wider">Step 2: Confirm Bibliographic Record & Physical Copies</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-neutral-muted mb-1">Book Title *</label>
            <input v-model="form.title" type="text" class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm" />
          </div>

          <div>
            <label class="block text-xs font-bold text-neutral-muted mb-1">Subtitle</label>
            <input v-model="form.subtitle" type="text" class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-neutral-muted mb-1">ISBN-13</label>
              <input v-model="form.isbn_13" type="text" class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl font-mono text-neutral-black text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm" />
            </div>
            <div>
              <label class="block text-xs font-bold text-neutral-muted mb-1">ISBN-10</label>
              <input v-model="form.isbn_10" type="text" class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl font-mono text-neutral-black text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-neutral-muted mb-1">Publisher</label>
            <input v-model="form.publisher_name" type="text" class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm" />
          </div>

          <div class="space-y-2">
            <label class="block text-xs font-bold text-neutral-muted">Authors</label>
            <div v-for="(_author, idx) in form.author_names" :key="idx" class="flex gap-2">
              <input v-model="form.author_names[idx]" type="text" placeholder="Author name" class="flex-1 px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm" />
              <button @click="removeAuthorField(idx)" type="button" class="px-3 py-2 bg-rose-50 text-rose-700 rounded-xl border border-rose-200 text-xs font-bold hover:bg-rose-100 transition-colors">Remove</button>
            </div>
            <button @click="addAuthorField" type="button" class="text-xs text-primary hover:underline font-bold flex items-center space-x-1">
              <Plus class="w-3.5 h-3.5" />
              <span>Add Author</span>
            </button>
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-neutral-muted mb-1">Summary / Description</label>
            <textarea v-model="form.summary" rows="4" class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"></textarea>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-neutral-muted mb-1">Publication Year</label>
              <input v-model.number="form.publication_year" type="number" class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm" />
            </div>
            <div>
              <label class="block text-xs font-bold text-neutral-muted mb-1">Initial Copies Count</label>
              <input v-model.number="form.copies_count" type="number" min="1" max="50" class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm" />
            </div>
          </div>

          <div class="flex items-center space-x-3 pt-2">
            <input v-model="form.is_reference_only" type="checkbox" id="refOnly" class="rounded border-neutral-ivory text-primary focus:ring-primary/20" />
            <label for="refOnly" class="text-xs text-neutral-black font-semibold">Reference Only (Cannot be checked out)</label>
          </div>
        </div>
      </div>

      <div class="pt-4 border-t border-neutral-ivory flex justify-end">
        <button
          @click="handleSubmitIntake"
          :disabled="isSubmitting || !form.title"
          class="px-6 py-3 rounded-xl bg-primary hover:bg-primary/90 text-white font-bold text-sm transition-all shadow-soft disabled:opacity-50 flex items-center space-x-2"
        >
          <span>{{ isSubmitting ? 'Saving Intake...' : 'Create Book & Generate Copy Barcodes' }}</span>
        </button>
      </div>
    </div>

    <!-- Step 3: Created Copies Barcode Print Preview -->
    <div v-if="createdBook" class="bg-white border border-neutral-ivory rounded-2xl p-6 space-y-6 shadow-soft">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-xs font-bold text-neutral-muted uppercase tracking-wider">Step 3: Barcode Label Print Preview</h3>
          <p class="text-xs text-neutral-muted mt-0.5">Print generated barcode labels for physical copies.</p>
        </div>
        <button
          @click="printBarcodeLabels"
          class="px-4 py-2 rounded-xl bg-white hover:bg-neutral-background text-neutral-black font-bold text-xs border border-neutral-ivory transition-all flex items-center space-x-2 shadow-sm"
        >
          <Printer class="w-4 h-4 text-primary" />
          <span>Print Labels</span>
        </button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
        <div
          v-for="copy in createdBook.copies"
          :key="copy.id"
          class="p-4 bg-neutral-background text-neutral-black rounded-xl border border-neutral-ivory font-sans space-y-2 text-center shadow-sm"
        >
          <p class="text-xs font-bold truncate">{{ createdBook.title }}</p>
          <div class="py-2 bg-white rounded border border-neutral-ivory">
            <span class="font-mono text-base font-extrabold tracking-widest text-primary">{{ copy.barcode }}</span>
          </div>
          <p class="text-[10px] text-neutral-muted font-mono">Accession: {{ copy.accession_number }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

