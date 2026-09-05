<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { BookOpen, CheckCircle2, XCircle, ArrowLeft, Bookmark, QrCode } from 'lucide-vue-next';
import mlibmsService, { type Book } from '@/services/mlibms/mlibmsService';
import { useToastStore } from '@/components/feedback/toast';

const route = useRoute();
const toast = useToastStore();

const book = ref<Book | null>(null);
const isLoading = ref(true);
const isReserving = ref(false);

const fetchDetails = async () => {
  const uuid = route.params.uuid as string;
  if (!uuid) return;

  isLoading.value = true;
  try {
    const res = await mlibmsService.getBookDetails(uuid);
    book.value = res.data;
  } catch (e) {
    toast.error('Failed to load book details.');
  } finally {
    isLoading.value = false;
  }
};

const handlePlaceHold = async () => {
  if (!book.value) return;
  isReserving.value = true;
  try {
    await mlibmsService.placeHold(book.value.uuid);
    toast.success('Hold reservation placed successfully!');
    fetchDetails();
  } catch (e: any) {
    toast.error(e.response?.data?.message || 'Failed to place hold reservation.');
  } finally {
    isReserving.value = false;
  }
};

onMounted(fetchDetails);
</script>

<template>
  <div class="pt-28 pb-16 md:pt-32 md:pb-20 bg-neutral-background min-h-[calc(100vh-16rem)]">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 space-y-6">
      <router-link to="/library" class="inline-flex items-center space-x-2 text-neutral-muted hover:text-neutral-black text-sm font-semibold transition-colors">
        <ArrowLeft class="w-4 h-4" />
        <span>Back to Catalog</span>
      </router-link>

      <div v-if="isLoading" class="h-96 bg-white animate-pulse rounded-3xl border border-neutral-ivory"></div>

      <div v-else-if="book" class="bg-white border border-neutral-ivory rounded-3xl p-6 md:p-8 space-y-8 shadow-soft">
        <div class="flex flex-col md:flex-row gap-8 items-start">
          <div class="w-full md:w-48 h-64 bg-neutral-background border border-neutral-ivory rounded-2xl flex items-center justify-center text-neutral-muted shrink-0 overflow-hidden shadow-sm">
            <img v-if="book.cover_image_url" :src="book.cover_image_url" :alt="book.title" class="w-full h-full object-cover" />
            <BookOpen v-else class="w-16 h-16 text-primary/40" />
          </div>

          <div class="flex-1 space-y-4">
            <div class="flex items-center space-x-3">
              <span class="text-xs px-3 py-1 rounded-md bg-primary/10 text-primary border border-primary/20 font-bold uppercase tracking-wider">
                {{ book.category?.name || 'General' }}
              </span>
              <span
                :class="[
                  'text-xs font-bold px-2.5 py-1 rounded-full flex items-center',
                  book.available_copies_count > 0 ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : 'text-rose-700 bg-rose-50 border border-rose-200'
                ]"
              >
                <component :is="book.available_copies_count > 0 ? CheckCircle2 : XCircle" class="w-3.5 h-3.5 mr-1" />
                <span>{{ book.available_copies_count > 0 ? `${book.available_copies_count} Copy Available` : 'Checked Out' }}</span>
              </span>
            </div>

            <div>
              <h1 class="text-2xl md:text-3xl font-display font-bold text-neutral-black leading-tight">{{ book.title }}</h1>
              <p v-if="book.subtitle" class="text-neutral-muted text-sm mt-1">{{ book.subtitle }}</p>
            </div>

            <p class="text-sm text-neutral-black font-semibold">
              By {{ book.authors?.map(a => a.name).join(', ') || 'Unknown Author' }}
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-2 text-xs text-neutral-muted">
              <div>
                <span class="block text-neutral-muted font-medium">ISBN-13</span>
                <span class="font-mono text-neutral-black font-bold">{{ book.isbn_13 || 'N/A' }}</span>
              </div>
              <div>
                <span class="block text-neutral-muted font-medium">Publisher</span>
                <span class="text-neutral-black font-semibold">{{ book.publisher?.name || 'N/A' }}</span>
              </div>
              <div>
                <span class="block text-neutral-muted font-medium">Publication Year</span>
                <span class="text-neutral-black font-semibold">{{ book.publication_year || 'N/A' }}</span>
              </div>
            </div>

            <div class="pt-4 flex flex-wrap items-center gap-3">
              <button
                @click="handlePlaceHold"
                :disabled="isReserving"
                class="inline-flex items-center space-x-2 px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-bold text-sm transition-all shadow-soft disabled:opacity-50"
              >
                <Bookmark class="w-4 h-4" />
                <span>{{ isReserving ? 'Placing Hold...' : 'Reserve Hold' }}</span>
              </button>
              <router-link
                to="/library/scan"
                class="inline-flex items-center space-x-2 px-5 py-2.5 rounded-xl bg-white hover:bg-neutral-background text-neutral-black font-semibold text-sm border border-neutral-ivory transition-all shadow-sm"
              >
                <QrCode class="w-4 h-4 text-primary" />
                <span>Self-Service Scan</span>
              </router-link>
            </div>
          </div>
        </div>

        <div v-if="book.summary" class="border-t border-neutral-ivory pt-6 space-y-2">
          <h3 class="text-xs font-bold text-neutral-muted uppercase tracking-wider">Summary</h3>
          <p class="text-sm text-neutral-black leading-relaxed whitespace-pre-line">{{ book.summary }}</p>
        </div>

        <div class="border-t border-neutral-ivory pt-6 space-y-4">
          <h3 class="text-xs font-bold text-neutral-muted uppercase tracking-wider">Physical Copy Availability</h3>
          <div v-if="!book.copies || book.copies.length === 0" class="text-xs text-neutral-muted">No copy items registered.</div>
          <div v-else class="space-y-2">
            <div
              v-for="copy in book.copies"
              :key="copy.id"
              class="flex items-center justify-between p-3 bg-neutral-background border border-neutral-ivory rounded-xl text-xs"
            >
              <div class="space-y-0.5">
                <span class="font-mono text-primary font-bold">{{ copy.barcode }}</span>
                <span class="text-neutral-muted block">{{ copy.location?.name || 'Main Shelf' }}</span>
              </div>
              <div class="flex items-center space-x-3">
                <span class="text-neutral-muted capitalize">Condition: {{ copy.condition_label }}</span>
                <span
                  :class="[
                    'px-2.5 py-1 rounded-full text-xs font-bold',
                    copy.status === 'available' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-neutral-background text-neutral-muted border border-neutral-ivory'
                  ]"
                >
                  {{ copy.status_label }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

