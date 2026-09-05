<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Search, Plus, Trash2 } from 'lucide-vue-next';
import mlibmsAdminService from '@/services/mlibms/mlibmsAdminService';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

const books = ref<any[]>([]);
const isLoading = ref(true);
const searchQuery = ref('');

const fetchBooks = async () => {
  isLoading.value = true;
  try {
    const res = await mlibmsAdminService.getBooks({ search: searchQuery.value });
    books.value = res.data || [];
  } catch (e) {
    toast.error('Failed to load catalog books.');
  } finally {
    isLoading.value = false;
  }
};

const handleDelete = async (uuid: string) => {
  if (!confirm('Are you sure you want to delete this book record?')) return;
  try {
    await mlibmsAdminService.deleteBook(uuid);
    toast.success('Book record deleted.');
    fetchBooks();
  } catch (e) {
    toast.error('Failed to delete book.');
  }
};

onMounted(fetchBooks);
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-display font-bold text-neutral-black">Bibliographic Book Catalog</h1>
        <p class="text-xs text-neutral-muted mt-1">Manage library book records and bibliographic details.</p>
      </div>
      <router-link
        to="/library/admin/intake"
        class="inline-flex items-center space-x-2 px-4 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-bold text-xs shadow-soft transition-all"
      >
        <Plus class="w-4 h-4" />
        <span>Add Book / Intake</span>
      </router-link>
    </div>

    <!-- Filter Bar -->
    <div class="flex items-center space-x-3 bg-white p-4 rounded-2xl border border-neutral-ivory shadow-soft">
      <div class="relative flex-1">
        <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-muted" />
        <input
          v-model="searchQuery"
          @keyup.enter="fetchBooks"
          type="text"
          placeholder="Search by title, author, or ISBN..."
          class="w-full pl-10 pr-4 py-2 bg-neutral-background border border-neutral-ivory rounded-xl text-neutral-black placeholder-neutral-muted text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
      </div>
      <button @click="fetchBooks" class="px-4 py-2 bg-white hover:bg-neutral-background text-neutral-black text-xs font-bold rounded-xl border border-neutral-ivory shadow-sm transition-colors">
        Search
      </button>
    </div>

    <!-- Books Table -->
    <div class="bg-white border border-neutral-ivory rounded-2xl overflow-hidden shadow-soft">
      <table class="w-full text-left text-xs text-neutral-black">
        <thead class="bg-neutral-background text-neutral-muted font-bold uppercase tracking-wider border-b border-neutral-ivory">
          <tr>
            <th class="p-4">Title</th>
            <th class="p-4">Authors</th>
            <th class="p-4">ISBN</th>
            <th class="p-4">Category</th>
            <th class="p-4 text-center">Copies</th>
            <th class="p-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-ivory">
          <tr v-if="isLoading">
            <td colspan="6" class="p-8 text-center text-neutral-muted">Loading catalog books...</td>
          </tr>
          <tr v-else-if="books.length === 0">
            <td colspan="6" class="p-8 text-center text-neutral-muted">No books found in catalog.</td>
          </tr>
          <tr v-for="book in books" :key="book.id" class="hover:bg-neutral-background/60 transition-colors">
            <td class="p-4 font-bold text-neutral-black">
              <div class="truncate max-w-xs">{{ book.title }}</div>
              <span v-if="book.is_reference_only" class="text-[10px] text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded font-bold">Ref Only</span>
            </td>
            <td class="p-4 text-neutral-muted">
              {{ book.authors?.map((a: any) => a.name).join(', ') || 'N/A' }}
            </td>
            <td class="p-4 font-mono text-neutral-muted">{{ book.isbn_13 || book.isbn_10 || 'N/A' }}</td>
            <td class="p-4 text-neutral-muted">{{ book.category?.name || 'General' }}</td>
            <td class="p-4 text-center font-bold text-primary">
              {{ book.available_copies_count }} / {{ book.total_copies_count }}
            </td>
            <td class="p-4 text-right space-x-2">
              <button @click="handleDelete(book.uuid)" class="p-1.5 text-neutral-muted hover:text-rose-600 rounded-lg hover:bg-neutral-background">
                <Trash2 class="w-4 h-4" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

