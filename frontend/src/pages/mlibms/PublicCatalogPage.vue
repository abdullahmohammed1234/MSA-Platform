<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { Search, BookOpen, CheckCircle2, XCircle, ArrowRight, QrCode } from 'lucide-vue-next';
import mlibmsService, { type Book, type BookCategory } from '@/services/mlibms/mlibmsService';

const books = ref<Book[]>([]);
const categories = ref<BookCategory[]>([]);
const isLoading = ref(true);
const searchQuery = ref('');
const selectedCategory = ref('');

const fetchCatalog = async () => {
  isLoading.value = true;
  try {
    const response = await mlibmsService.getPublicCatalog({
      search: searchQuery.value,
      category: selectedCategory.value,
    });
    books.value = response.data || [];
  } catch (e) {
    console.error('Failed to load public catalog', e);
  } finally {
    isLoading.value = false;
  }
};

const fetchCategories = async () => {
  try {
    const res = await mlibmsService.getCategories();
    categories.value = res.data || [];
  } catch (e) {
    console.error('Failed to load categories', e);
  }
};

onMounted(() => {
  fetchCategories();
  fetchCatalog();
});

watch([searchQuery, selectedCategory], () => {
  fetchCatalog();
});
</script>

<template>
  <div class="min-h-screen bg-neutral-background text-neutral-black font-sans pb-16">
    <!-- Hero Header -->
    <header class="relative bg-gradient-to-b from-primary via-primary/95 to-primary text-white pt-32 sm:pt-36 pb-14 sm:pb-16 px-6 shadow-premium overflow-hidden border-b border-primary-dark/30">
      <!-- Background Decorative Elements -->
      <div class="absolute top-0 right-0 w-96 h-96 bg-accent-gold/10 rounded-full blur-3xl opacity-50 pointer-events-none" />
      <div class="absolute bottom-0 left-0 w-80 h-80 bg-white/5 rounded-full blur-2xl opacity-40 pointer-events-none" />

      <div class="max-w-6xl mx-auto text-center space-y-5 relative z-10">
        <div class="inline-flex items-center space-x-2 px-4 py-2 rounded-full bg-white/10 border border-white/20 text-accent-gold text-xs font-extrabold uppercase tracking-widest backdrop-blur-md shadow-soft">
          <BookOpen class="w-4 h-4 text-accent-gold" />
          <span>SFU MSA Library Catalog</span>
        </div>

        <h1 class="text-3xl sm:text-5xl md:text-6xl font-display font-bold text-white tracking-tight">
          Explore Islamic Literature <span class="italic font-serif font-light text-accent-gold">& Resources</span>
        </h1>

        <p class="text-white/80 max-w-2xl mx-auto text-sm sm:text-base leading-relaxed font-light">
          Browse our curated collection of Quranic studies, Hadith, Seerah, Islamic jurisprudence, history, and community literature at the SFU MSA Library.
        </p>

        <!-- Action Bar -->
        <div class="pt-4 flex flex-wrap items-center justify-center gap-4">
          <router-link
            to="/library/scan"
            class="inline-flex items-center space-x-2 px-6 py-3.5 rounded-2xl bg-secondary hover:bg-secondary/90 text-white font-extrabold text-xs uppercase tracking-wider shadow-premium hover:-translate-y-0.5 transition-all"
          >
            <QrCode class="w-4 h-4" />
            <span>Self-Service Scanner</span>
          </router-link>
          <router-link
            to="/library/my-loans"
            class="inline-flex items-center space-x-2 px-6 py-3.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-extrabold text-xs uppercase tracking-wider border border-white/20 backdrop-blur-md transition-all"
          >
            <span>My Loans & Holds</span>
            <ArrowRight class="w-4 h-4 text-accent-gold" />
          </router-link>
        </div>
      </div>
    </header>

    <!-- Search & Filter Bar -->
    <main class="max-w-6xl mx-auto px-6 mt-8 space-y-8">
      <div class="flex flex-col md:flex-row items-center gap-4 bg-white p-4 rounded-2xl border border-neutral-ivory shadow-soft">
        <div class="relative flex-1 w-full">
          <Search class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-muted" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search catalog by title, author, or ISBN..."
            class="w-full pl-11 pr-4 py-2.5 bg-neutral-background border border-neutral-ivory rounded-xl text-neutral-black placeholder-neutral-muted focus:outline-none focus:border-primary text-sm font-medium"
          />
        </div>

        <div class="w-full md:w-64">
          <select
            v-model="selectedCategory"
            class="w-full py-2.5 px-3 bg-neutral-background border border-neutral-ivory rounded-xl text-neutral-black text-sm font-semibold focus:outline-none focus:border-primary"
          >
            <option value="">All Categories</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.slug">
              {{ cat.name }}
            </option>
          </select>
        </div>
      </div>

      <!-- Catalog Grid -->
      <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="n in 6" :key="n" class="h-64 bg-white animate-pulse rounded-2xl border border-neutral-ivory"></div>
      </div>

      <div v-else-if="books.length === 0" class="text-center py-16 bg-white rounded-2xl border border-neutral-ivory shadow-soft space-y-2">
        <BookOpen class="w-12 h-12 mx-auto text-neutral-muted" />
        <h3 class="text-lg font-bold text-neutral-black">No books found in catalog</h3>
        <p class="text-xs text-neutral-muted">Try adjusting your search query or category filter.</p>
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <router-link
          v-for="book in books"
          :key="book.uuid"
          :to="`/library/book/${book.uuid}`"
          class="group bg-white hover:bg-neutral-background/60 border border-neutral-ivory rounded-2xl p-5 shadow-soft transition-all flex flex-col justify-between"
        >
          <div class="space-y-3">
            <div class="flex items-start justify-between">
              <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-md bg-primary/10 text-primary border border-primary/20">
                {{ book.category?.name || 'General' }}
              </span>
              <span
                :class="[
                  'text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1 uppercase tracking-wider',
                  book.available_copies_count > 0 ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : 'text-amber-700 bg-amber-50 border border-amber-200'
                ]"
              >
                <component :is="book.available_copies_count > 0 ? CheckCircle2 : XCircle" class="w-3.5 h-3.5" />
                <span>{{ book.available_copies_count > 0 ? `${book.available_copies_count} Available` : 'Checked Out' }}</span>
              </span>
            </div>

            <div>
              <h3 class="text-base font-display font-bold text-neutral-black group-hover:text-primary transition-colors line-clamp-2">
                {{ book.title }}
              </h3>
              <p v-if="book.subtitle" class="text-xs text-neutral-muted line-clamp-1 mt-0.5">{{ book.subtitle }}</p>
            </div>

            <p class="text-xs text-neutral-black/70 font-semibold">
              By {{ book.authors?.map(a => a.name).join(', ') || 'Unknown Author' }}
            </p>

            <p v-if="book.summary" class="text-xs text-neutral-muted line-clamp-3 leading-relaxed">
              {{ book.summary }}
            </p>
          </div>

          <div class="pt-4 border-t border-neutral-ivory flex items-center justify-between text-xs text-neutral-muted mt-4">
            <span class="font-mono text-[11px]">ISBN: {{ book.isbn_13 || book.isbn_10 || 'N/A' }}</span>
            <span class="text-primary font-bold group-hover:translate-x-1 transition-transform inline-flex items-center gap-1">
              Details →
            </span>
          </div>
        </router-link>
      </div>
    </main>
  </div>
</template>
