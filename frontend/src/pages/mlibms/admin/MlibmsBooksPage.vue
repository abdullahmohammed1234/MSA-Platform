<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Search, Plus, Trash2, Edit3, X, ChevronLeft, ChevronRight, BookOpen, AlertCircle, RefreshCw } from 'lucide-vue-next';
import mlibmsAdminService from '@/services/mlibms/mlibmsAdminService';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

const books = ref<any[]>([]);
const categories = ref<any[]>([]);
const isLoading = ref(true);
const searchQuery = ref('');
const selectedCategory = ref('');

// Pagination state
const currentPage = ref(1);
const lastPage = ref(1);
const perPage = ref(20);
const totalBooks = ref(0);

// Edit Book Modal state
const isEditModalOpen = ref(false);
const isSavingEdit = ref(false);
const editingBook = ref<any>(null);

const editForm = ref({
  uuid: '',
  title: '',
  subtitle: '',
  author_names: [''],
  publisher_name: '',
  primary_category_id: null as number | null,
  isbn_13: '',
  isbn_10: '',
  edition: '',
  publication_year: null as number | null,
  language: 'English',
  summary: '',
  cover_image_url: '',
  default_loan_days: 14,
  is_reference_only: false,
});

const fetchCategories = async () => {
  try {
    const res = await mlibmsAdminService.getCategories();
    categories.value = res.data || [];
  } catch (e) {
    // Non-fatal
  }
};

const fetchBooks = async (page = 1) => {
  isLoading.value = true;
  currentPage.value = page;

  try {
    const params: Record<string, any> = {
      page: currentPage.value,
      per_page: perPage.value,
    };
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }
    if (selectedCategory.value) {
      params.category = selectedCategory.value;
    }

    const res = await mlibmsAdminService.getBooks(params);
    books.value = res.data || [];

    if (res.meta) {
      currentPage.value = res.meta.current_page || 1;
      lastPage.value = res.meta.last_page || 1;
      perPage.value = res.meta.per_page || 20;
      totalBooks.value = res.meta.total || 0;
    } else {
      totalBooks.value = books.value.length;
      lastPage.value = 1;
    }
  } catch (e) {
    toast.error('Failed to load catalog books.');
  } finally {
    isLoading.value = false;
  }
};

const handleSearch = () => {
  fetchBooks(1);
};

const handleCategoryChange = () => {
  fetchBooks(1);
};

const handlePerPageChange = () => {
  fetchBooks(1);
};

const clearFilters = () => {
  searchQuery.value = '';
  selectedCategory.value = '';
  fetchBooks(1);
};

const openEditModal = (book: any) => {
  editingBook.value = book;
  editForm.value = {
    uuid: book.uuid,
    title: book.title || '',
    subtitle: book.subtitle || '',
    author_names: book.authors && book.authors.length > 0
      ? book.authors.map((a: any) => a.name || a)
      : [''],
    publisher_name: book.publisher?.name || '',
    primary_category_id: book.category?.id || book.primary_category_id || null,
    isbn_13: book.isbn_13 || '',
    isbn_10: book.isbn_10 || '',
    edition: book.edition || '',
    publication_year: book.publication_year || null,
    language: book.language || 'English',
    summary: book.summary || '',
    cover_image_url: book.cover_image_url || '',
    default_loan_days: book.default_loan_days || 14,
    is_reference_only: !!book.is_reference_only,
  };
  isEditModalOpen.value = true;
};

const closeEditModal = () => {
  isEditModalOpen.value = false;
  editingBook.value = null;
};

const addAuthorField = () => {
  editForm.value.author_names.push('');
};

const removeAuthorField = (index: number) => {
  editForm.value.author_names.splice(index, 1);
};

const saveBookEdit = async () => {
  if (!editForm.value.title.trim()) {
    toast.error('Book title is required.');
    return;
  }

  isSavingEdit.value = true;
  try {
    const payload = {
      title: editForm.value.title.trim(),
      subtitle: editForm.value.subtitle ? editForm.value.subtitle.trim() : null,
      primary_category_id: editForm.value.primary_category_id,
      publisher_name: editForm.value.publisher_name ? editForm.value.publisher_name.trim() : null,
      author_names: editForm.value.author_names.filter((a: string) => a.trim() !== ''),
      isbn_13: editForm.value.isbn_13 ? editForm.value.isbn_13.trim() : null,
      isbn_10: editForm.value.isbn_10 ? editForm.value.isbn_10.trim() : null,
      edition: editForm.value.edition ? editForm.value.edition.trim() : null,
      publication_year: editForm.value.publication_year,
      language: editForm.value.language || 'English',
      summary: editForm.value.summary ? editForm.value.summary.trim() : null,
      cover_image_url: editForm.value.cover_image_url ? editForm.value.cover_image_url.trim() : null,
      default_loan_days: editForm.value.default_loan_days,
      is_reference_only: editForm.value.is_reference_only,
    };

    const res = await mlibmsAdminService.updateBook(editForm.value.uuid, payload);
    const updatedBook = res.data;

    // In-place reactive update without full reload
    const index = books.value.findIndex(b => b.uuid === editForm.value.uuid);
    if (index !== -1) {
      books.value[index] = {
        ...books.value[index],
        ...updatedBook,
      };
    }

    toast.success('Book record updated successfully.');
    closeEditModal();
  } catch (e: any) {
    toast.error(e.response?.data?.message || 'Failed to update book.');
  } finally {
    isSavingEdit.value = false;
  }
};

const handleDelete = async (uuid: string) => {
  if (!confirm('Are you sure you want to delete this book record?')) return;
  try {
    await mlibmsAdminService.deleteBook(uuid);
    toast.success('Book record deleted.');
    fetchBooks(currentPage.value);
  } catch (e) {
    toast.error('Failed to delete book.');
  }
};

const showingRange = computed(() => {
  if (totalBooks.value === 0) return '0 books';
  const start = (currentPage.value - 1) * perPage.value + 1;
  const end = Math.min(currentPage.value * perPage.value, totalBooks.value);
  return `${start}–${end} of ${totalBooks.value} books`;
});

onMounted(() => {
  fetchCategories();
  fetchBooks(1);
});
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-display font-bold text-neutral-black flex items-center gap-2">
          <BookOpen class="w-6 h-6 text-primary" />
          <span>Bibliographic Book Catalog</span>
        </h1>
        <p class="text-xs text-neutral-muted mt-1">Manage library book records, update bibliographic details, and track copy inventory.</p>
      </div>
      <router-link
        to="/library/admin/intake"
        class="inline-flex items-center space-x-2 px-4 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-bold text-xs shadow-soft transition-all shrink-0"
      >
        <Plus class="w-4 h-4" />
        <span>Add Book / Intake</span>
      </router-link>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 rounded-2xl border border-neutral-ivory shadow-soft space-y-3">
      <div class="flex flex-col md:flex-row gap-3">
        <div class="relative flex-1">
          <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-muted" />
          <input
            v-model="searchQuery"
            @keyup.enter="handleSearch"
            type="text"
            placeholder="Search by title, author, or ISBN..."
            class="w-full pl-10 pr-4 py-2 bg-neutral-background border border-neutral-ivory rounded-xl text-neutral-black placeholder-neutral-muted text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <div class="flex items-center gap-2">
          <select
            v-model="selectedCategory"
            @change="handleCategoryChange"
            class="px-3 py-2 bg-neutral-background border border-neutral-ivory rounded-xl text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          >
            <option value="">All Categories</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.slug">{{ cat.name }}</option>
          </select>

          <select
            v-model.number="perPage"
            @change="handlePerPageChange"
            class="px-3 py-2 bg-neutral-background border border-neutral-ivory rounded-xl text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          >
            <option :value="10">10 / page</option>
            <option :value="20">20 / page</option>
            <option :value="50">50 / page</option>
            <option :value="100">100 / page</option>
          </select>

          <button
            @click="handleSearch"
            class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-xl shadow-sm hover:bg-primary/90 transition-colors shrink-0"
          >
            Filter
          </button>

          <button
            v-if="searchQuery || selectedCategory"
            @click="clearFilters"
            class="px-3 py-2 bg-neutral-background text-neutral-muted hover:text-neutral-black text-xs font-bold rounded-xl border border-neutral-ivory transition-colors shrink-0"
          >
            Clear
          </button>
        </div>
      </div>

      <!-- Active filters banner -->
      <div v-if="searchQuery || selectedCategory" class="flex items-center gap-2 text-[11px] text-neutral-muted">
        <span>Active filters:</span>
        <span v-if="searchQuery" class="px-2 py-0.5 rounded-full bg-primary/10 text-primary font-medium">Search: "{{ searchQuery }}"</span>
        <span v-if="selectedCategory" class="px-2 py-0.5 rounded-full bg-primary/10 text-primary font-medium">Category: {{ selectedCategory }}</span>
      </div>
    </div>

    <!-- Books Table Container with Horizontal Scroll Protection -->
    <div class="bg-white border border-neutral-ivory rounded-2xl overflow-hidden shadow-soft">
      <div class="overflow-x-auto w-full">
        <table class="w-full text-left text-xs text-neutral-black min-w-[700px]">
          <thead class="bg-neutral-background text-neutral-muted font-bold uppercase tracking-wider border-b border-neutral-ivory">
            <tr>
              <th class="p-4">Title & Subtitle</th>
              <th class="p-4">Authors</th>
              <th class="p-4">ISBN</th>
              <th class="p-4">Category</th>
              <th class="p-4 text-center">Copies (Avail/Total)</th>
              <th class="p-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory">
            <tr v-if="isLoading">
              <td colspan="6" class="p-8 text-center text-neutral-muted">
                <div class="flex items-center justify-center space-x-2">
                  <RefreshCw class="w-4 h-4 animate-spin text-primary" />
                  <span>Loading catalog books...</span>
                </div>
              </td>
            </tr>
            <tr v-else-if="books.length === 0">
              <td colspan="6" class="p-8 text-center text-neutral-muted">
                <AlertCircle class="w-8 h-8 mx-auto mb-2 text-neutral-muted opacity-50" />
                <p class="font-semibold">No books found matching the current criteria.</p>
                <p class="text-[11px] mt-1" v-if="searchQuery || selectedCategory">Try clearing or adjusting your filters.</p>
              </td>
            </tr>
            <tr v-for="book in books" :key="book.id" class="hover:bg-neutral-background/60 transition-colors">
              <td class="p-4 font-bold text-neutral-black">
                <div class="truncate max-w-xs text-sm text-neutral-black">{{ book.title }}</div>
                <div v-if="book.subtitle" class="text-[11px] text-neutral-muted truncate max-w-xs">{{ book.subtitle }}</div>
                <span v-if="book.is_reference_only" class="inline-block mt-1 text-[10px] text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded font-bold">Ref Only</span>
              </td>
              <td class="p-4 text-neutral-muted">
                {{ book.authors?.map((a: any) => a.name).join(', ') || 'N/A' }}
              </td>
              <td class="p-4 font-mono text-neutral-muted text-[11px]">
                <div>{{ book.isbn_13 || book.isbn_10 || 'N/A' }}</div>
                <div v-if="book.edition" class="text-[10px] text-neutral-muted">Ed: {{ book.edition }}</div>
              </td>
              <td class="p-4 text-neutral-muted">{{ book.category?.name || 'General' }}</td>
              <td class="p-4 text-center font-bold text-primary">
                {{ book.available_copies_count ?? 0 }} / {{ book.total_copies_count ?? 0 }}
              </td>
              <td class="p-4 text-right space-x-1 shrink-0">
                <button
                  @click="openEditModal(book)"
                  title="Edit Book Details"
                  class="p-1.5 text-neutral-muted hover:text-primary rounded-lg hover:bg-neutral-background transition-colors"
                >
                  <Edit3 class="w-4 h-4" />
                </button>
                <button
                  @click="handleDelete(book.uuid)"
                  title="Delete Book"
                  class="p-1.5 text-neutral-muted hover:text-rose-600 rounded-lg hover:bg-neutral-background transition-colors"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Bar -->
      <div class="p-4 border-t border-neutral-ivory bg-white flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-neutral-muted">
        <div>
          <span>Showing {{ showingRange }}</span>
        </div>

        <div class="flex items-center space-x-1">
          <button
            @click="fetchBooks(currentPage - 1)"
            :disabled="currentPage <= 1 || isLoading"
            class="px-3 py-1.5 rounded-lg border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 disabled:cursor-not-allowed flex items-center space-x-1 font-semibold"
          >
            <ChevronLeft class="w-3.5 h-3.5" />
            <span>Prev</span>
          </button>

          <span class="px-3 py-1 font-mono font-bold text-neutral-black">
            Page {{ currentPage }} of {{ lastPage }}
          </span>

          <button
            @click="fetchBooks(currentPage + 1)"
            :disabled="currentPage >= lastPage || isLoading"
            class="px-3 py-1.5 rounded-lg border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 disabled:cursor-not-allowed flex items-center space-x-1 font-semibold"
          >
            <span>Next</span>
            <ChevronRight class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Book Modal (LIB-03) -->
    <div
      v-if="isEditModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-black/40 backdrop-blur-xs overflow-y-auto"
      @click.self="closeEditModal"
    >
      <div class="bg-white border border-neutral-ivory rounded-2xl w-full max-w-2xl max-h-[90vh] flex flex-col shadow-premium overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="p-5 border-b border-neutral-ivory flex items-center justify-between bg-neutral-background/50">
          <div class="flex items-center space-x-2">
            <Edit3 class="w-5 h-5 text-primary" />
            <h3 class="font-display font-bold text-base text-neutral-black">Edit Book Details</h3>
          </div>
          <button @click="closeEditModal" class="p-1.5 text-neutral-muted hover:text-neutral-black rounded-lg hover:bg-white transition-colors">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 overflow-y-auto space-y-4 text-xs">
          <div>
            <label class="block font-bold text-neutral-muted mb-1">Book Title *</label>
            <input
              v-model="editForm.title"
              type="text"
              class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
            />
          </div>

          <div>
            <label class="block font-bold text-neutral-muted mb-1">Subtitle</label>
            <input
              v-model="editForm.subtitle"
              type="text"
              class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
            />
          </div>

          <!-- Authors Field -->
          <div class="space-y-2">
            <label class="block font-bold text-neutral-muted">Authors</label>
            <div v-for="(_author, idx) in editForm.author_names" :key="idx" class="flex gap-2">
              <input
                v-model="editForm.author_names[idx]"
                type="text"
                placeholder="Author name"
                class="flex-1 px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
              />
              <button
                @click="removeAuthorField(idx)"
                type="button"
                class="px-2.5 py-1.5 bg-rose-50 text-rose-700 rounded-xl border border-rose-200 text-[11px] font-bold hover:bg-rose-100 transition-colors"
              >
                Remove
              </button>
            </div>
            <button
              @click="addAuthorField"
              type="button"
              class="text-xs text-primary hover:underline font-bold flex items-center space-x-1"
            >
              <Plus class="w-3.5 h-3.5" />
              <span>Add Author</span>
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-neutral-muted mb-1">Publisher</label>
              <input
                v-model="editForm.publisher_name"
                type="text"
                class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
              />
            </div>

            <div>
              <label class="block font-bold text-neutral-muted mb-1">Category</label>
              <select
                v-model.number="editForm.primary_category_id"
                class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
              >
                <option :value="null">Unassigned / General</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-neutral-muted mb-1">ISBN-13</label>
              <input
                v-model="editForm.isbn_13"
                type="text"
                class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl font-mono text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
              />
            </div>

            <div>
              <label class="block font-bold text-neutral-muted mb-1">ISBN-10</label>
              <input
                v-model="editForm.isbn_10"
                type="text"
                class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl font-mono text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
              />
            </div>
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block font-bold text-neutral-muted mb-1">Publication Year</label>
              <input
                v-model.number="editForm.publication_year"
                type="number"
                class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
              />
            </div>

            <div>
              <label class="block font-bold text-neutral-muted mb-1">Edition</label>
              <input
                v-model="editForm.edition"
                type="text"
                placeholder="e.g. 2nd Edition"
                class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
              />
            </div>

            <div>
              <label class="block font-bold text-neutral-muted mb-1">Language</label>
              <input
                v-model="editForm.language"
                type="text"
                class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
              />
            </div>
          </div>

          <div>
            <label class="block font-bold text-neutral-muted mb-1">Summary / Description</label>
            <textarea
              v-model="editForm.summary"
              rows="3"
              class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
            ></textarea>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-neutral-muted mb-1">Default Loan Duration (Days)</label>
              <input
                v-model.number="editForm.default_loan_days"
                type="number"
                min="1"
                max="365"
                class="w-full px-3.5 py-2 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-xs focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
              />
            </div>

            <div class="flex items-center space-x-2 pt-5">
              <input
                v-model="editForm.is_reference_only"
                type="checkbox"
                id="editRefOnly"
                class="rounded border-neutral-ivory text-primary focus:ring-primary/20"
              />
              <label for="editRefOnly" class="font-semibold text-neutral-black cursor-pointer">
                Reference Only (Cannot be checked out)
              </label>
            </div>
          </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="p-4 border-t border-neutral-ivory bg-neutral-background/50 flex justify-end space-x-2">
          <button
            @click="closeEditModal"
            class="px-4 py-2 rounded-xl border border-neutral-ivory text-neutral-muted hover:text-neutral-black font-bold text-xs hover:bg-white transition-colors"
          >
            Cancel
          </button>
          <button
            @click="saveBookEdit"
            :disabled="isSavingEdit || !editForm.title.trim()"
            class="px-5 py-2 rounded-xl bg-primary hover:bg-primary/90 text-white font-bold text-xs shadow-soft disabled:opacity-50 transition-all flex items-center space-x-2"
          >
            <RefreshCw v-if="isSavingEdit" class="w-3.5 h-3.5 animate-spin" />
            <span>{{ isSavingEdit ? 'Saving...' : 'Save Changes' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
