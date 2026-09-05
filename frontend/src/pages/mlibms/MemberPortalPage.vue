<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { 
  Clock, 
  Bookmark, 
  RotateCcw, 
  AlertTriangle, 
  ArrowLeft, 
  BookOpen, 
  QrCode, 
  UserCheck, 
  CreditCard, 
  Info,
  Calendar,
  ChevronRight
} from 'lucide-vue-next';
import mlibmsService, { type MemberLoan, type BookReservation, type LibraryMember } from '@/services/mlibms/mlibmsService';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

const member = ref<LibraryMember | null>(null);
const activeLoans = ref<MemberLoan[]>([]);
const activeHolds = ref<BookReservation[]>([]);
const isLoading = ref(true);
const activeTab = ref<'loans' | 'holds' | 'rules'>('loans');

const overdueCount = computed(() => {
  return activeLoans.value.filter(l => l.is_overdue).length;
});

const fetchPortalData = async () => {
  isLoading.value = true;
  try {
    const res = await mlibmsService.getMyPortalData();
    member.value = res.data.member;
    activeLoans.value = res.data.active_loans || [];
    activeHolds.value = res.data.active_holds || [];
  } catch (e) {
    toast.error('Failed to load member portal data.');
  } finally {
    isLoading.value = false;
  }
};

const handleRenew = async (loanUuid: string) => {
  try {
    await mlibmsService.renewLoan(loanUuid);
    toast.success('Loan renewed successfully!');
    fetchPortalData();
  } catch (e: any) {
    toast.error(e.response?.data?.message || 'Failed to renew loan.');
  }
};

const handleCancelHold = async (reservationUuid: string) => {
  try {
    await mlibmsService.cancelHold(reservationUuid);
    toast.success('Hold reservation cancelled.');
    fetchPortalData();
  } catch (e: any) {
    toast.error(e.response?.data?.message || 'Failed to cancel hold.');
  }
};

const formatDate = (dateStr: string) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
};

const getDaysRemainingText = (dueAtStr: string) => {
  const due = new Date(dueAtStr);
  const now = new Date();
  const diffTime = due.getTime() - now.getTime();
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  
  if (diffDays < 0) return `${Math.abs(diffDays)} day${Math.abs(diffDays) === 1 ? '' : 's'} overdue`;
  if (diffDays === 0) return 'Due today!';
  return `${diffDays} day${diffDays === 1 ? '' : 's'} left`;
};

onMounted(fetchPortalData);
</script>

<template>
  <div class="pt-28 pb-16 md:pt-32 md:pb-20 bg-neutral-background min-h-[calc(100vh-16rem)]">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 space-y-6">
      
      <!-- Back Navigation & Breadcrumb -->
      <div class="flex items-center justify-between">
        <router-link 
          to="/library" 
          class="inline-flex items-center space-x-2 text-neutral-muted hover:text-neutral-black text-sm font-semibold transition-colors group"
        >
          <ArrowLeft class="w-4 h-4 transition-transform group-hover:-translate-x-1" />
          <span>Back to Library Catalog</span>
        </router-link>

        <router-link 
          to="/library/scan" 
          class="inline-flex items-center space-x-2 text-xs font-bold text-primary bg-primary/10 hover:bg-primary/20 border border-primary/20 px-3.5 py-1.5 rounded-full transition-all"
        >
          <QrCode class="w-3.5 h-3.5" />
          <span>Self-Service Circulation Scanner</span>
        </router-link>
      </div>

      <!-- Member Profile & Key Statistics Header -->
      <div v-if="isLoading" class="bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft animate-pulse h-40"></div>
      
      <div v-else-if="member" class="bg-white border border-neutral-ivory rounded-3xl p-6 sm:p-8 shadow-soft space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-neutral-ivory pb-6">
          <div class="flex items-center space-x-4">
            <div class="w-14 h-14 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0">
              <UserCheck class="w-7 h-7" />
            </div>
            <div>
              <div class="flex items-center space-x-2">
                <h1 class="text-2xl font-display font-bold text-neutral-black">{{ member.name }}</h1>
                <span
                  :class="[
                    'px-2.5 py-0.5 rounded-full text-xs font-bold capitalize border',
                    member.status === 'active' 
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
                      : 'bg-rose-50 text-rose-700 border-rose-200'
                  ]"
                >
                  {{ member.status }} Member
                </span>
              </div>
              <p class="text-xs text-neutral-muted font-mono mt-1 flex items-center space-x-3">
                <span class="inline-flex items-center"><CreditCard class="w-3.5 h-3.5 mr-1" /> Card #: {{ member.library_card_number }}</span>
                <span>•</span>
                <span>{{ member.email }}</span>
              </p>
            </div>
          </div>
        </div>

        <!-- Metric KPI Cards Bar -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-neutral-background border border-neutral-ivory rounded-2xl p-3.5 text-center">
            <span class="text-xs font-bold text-neutral-muted uppercase tracking-wider block">Active Loans</span>
            <span class="text-xl font-display font-bold text-neutral-black mt-0.5 block">
              {{ member.active_loans_count }} <span class="text-xs text-neutral-muted font-normal">/ {{ member.max_active_loans }}</span>
            </span>
          </div>

          <div class="bg-neutral-background border border-neutral-ivory rounded-2xl p-3.5 text-center">
            <span class="text-xs font-bold text-neutral-muted uppercase tracking-wider block">Hold Requests</span>
            <span class="text-xl font-display font-bold text-neutral-black mt-0.5 block">
              {{ activeHolds.length }}
            </span>
          </div>

          <div class="bg-neutral-background border border-neutral-ivory rounded-2xl p-3.5 text-center">
            <span class="text-xs font-bold text-neutral-muted uppercase tracking-wider block">Overdue Items</span>
            <span :class="['text-xl font-display font-bold mt-0.5 block', overdueCount > 0 ? 'text-rose-600' : 'text-neutral-black']">
              {{ overdueCount }}
            </span>
          </div>

          <div class="bg-neutral-background border border-neutral-ivory rounded-2xl p-3.5 text-center">
            <span class="text-xs font-bold text-neutral-muted uppercase tracking-wider block">Borrowing Limit</span>
            <span class="text-xl font-display font-bold text-emerald-700 mt-0.5 block">
              {{ member.max_active_loans - member.active_loans_count }} left
            </span>
          </div>
        </div>
      </div>

      <!-- Segmented Navigation Tabs -->
      <div class="bg-white border border-neutral-ivory rounded-3xl shadow-soft overflow-hidden">
        
        <!-- Tab Bar Header -->
        <div class="bg-neutral-background/60 border-b border-neutral-ivory p-2 sm:p-3 flex flex-wrap items-center justify-between gap-2">
          <nav class="flex space-x-1 sm:space-x-2" aria-label="Tabs">
            <button
              @click="activeTab = 'loans'"
              :class="[
                'px-4 py-2.5 rounded-2xl text-xs sm:text-sm font-bold transition-all flex items-center space-x-2',
                activeTab === 'loans'
                  ? 'bg-white text-primary shadow-sm border border-neutral-ivory'
                  : 'text-neutral-muted hover:text-neutral-black hover:bg-white/50'
              ]"
            >
              <Clock class="w-4 h-4" />
              <span>Active Checkouts</span>
              <span 
                :class="[
                  'px-2 py-0.5 rounded-full text-xs font-bold ml-1',
                  activeTab === 'loans' ? 'bg-primary/10 text-primary' : 'bg-neutral-200 text-neutral-600'
                ]"
              >
                {{ activeLoans.length }}
              </span>
            </button>

            <button
              @click="activeTab = 'holds'"
              :class="[
                'px-4 py-2.5 rounded-2xl text-xs sm:text-sm font-bold transition-all flex items-center space-x-2',
                activeTab === 'holds'
                  ? 'bg-white text-amber-700 shadow-sm border border-neutral-ivory'
                  : 'text-neutral-muted hover:text-neutral-black hover:bg-white/50'
              ]"
            >
              <Bookmark class="w-4 h-4" />
              <span>Hold Reservations</span>
              <span 
                :class="[
                  'px-2 py-0.5 rounded-full text-xs font-bold ml-1',
                  activeTab === 'holds' ? 'bg-amber-100 text-amber-800' : 'bg-neutral-200 text-neutral-600'
                ]"
              >
                {{ activeHolds.length }}
              </span>
            </button>

            <button
              @click="activeTab = 'rules'"
              :class="[
                'px-4 py-2.5 rounded-2xl text-xs sm:text-sm font-bold transition-all flex items-center space-x-2',
                activeTab === 'rules'
                  ? 'bg-white text-neutral-black shadow-sm border border-neutral-ivory'
                  : 'text-neutral-muted hover:text-neutral-black hover:bg-white/50'
              ]"
            >
              <Info class="w-4 h-4" />
              <span>Rules & Help</span>
            </button>
          </nav>

          <span class="text-xs text-neutral-muted px-2 font-mono hidden md:inline-block">
            MLibMS Member Circulation
          </span>
        </div>

        <!-- Tab Body Content -->
        <div class="p-6 sm:p-8">
          
          <!-- TAB 1: Active Checkouts -->
          <div v-if="activeTab === 'loans'" class="space-y-4">
            <div v-if="isLoading" class="space-y-3">
              <div v-for="i in 2" :key="i" class="h-24 bg-neutral-background animate-pulse rounded-2xl"></div>
            </div>

            <!-- Empty State -->
            <div v-else-if="activeLoans.length === 0" class="text-center py-12 px-4 space-y-4">
              <div class="w-16 h-16 rounded-full bg-primary/10 text-primary mx-auto flex items-center justify-center">
                <BookOpen class="w-8 h-8" />
              </div>
              <div class="max-w-md mx-auto space-y-1">
                <h3 class="text-lg font-display font-bold text-neutral-black">No Active Checkouts</h3>
                <p class="text-xs text-neutral-muted">You currently do not have any borrowed books. Browse our catalog to reserve or check out books!</p>
              </div>
              <router-link 
                to="/library" 
                class="inline-flex items-center space-x-2 px-5 py-2.5 rounded-xl bg-primary text-white hover:bg-primary-hover font-semibold text-xs transition-all shadow-md"
              >
                <span>Browse Catalog</span>
                <ChevronRight class="w-4 h-4" />
              </router-link>
            </div>

            <!-- Active Loan Cards Grid -->
            <div v-else class="grid grid-cols-1 gap-4">
              <div
                v-for="loan in activeLoans"
                :key="loan.id"
                :class="[
                  'p-5 rounded-2xl border transition-all flex flex-col md:flex-row md:items-center justify-between gap-4',
                  loan.is_overdue 
                    ? 'bg-rose-50/50 border-rose-200' 
                    : 'bg-neutral-background border-neutral-ivory hover:border-neutral-200'
                ]"
              >
                <div class="flex items-start space-x-4">
                  <div 
                    :class="[
                      'w-12 h-12 rounded-xl flex items-center justify-center shrink-0 font-bold',
                      loan.is_overdue ? 'bg-rose-100 text-rose-700' : 'bg-primary/10 text-primary'
                    ]"
                  >
                    <BookOpen class="w-6 h-6" />
                  </div>

                  <div class="space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                      <h4 class="text-base font-bold text-neutral-black">{{ loan.copy?.book?.title || 'Library Book' }}</h4>
                      <span v-if="loan.copy?.barcode" class="px-2 py-0.5 rounded bg-white border border-neutral-ivory font-mono text-[10px] text-neutral-muted">
                        Barcode: {{ loan.copy.barcode }}
                      </span>
                    </div>

                    <p class="text-xs text-neutral-muted">
                      Checked out: <span class="font-medium text-neutral-black">{{ formatDate(loan.checked_out_at) }}</span>
                    </p>

                    <div class="flex flex-wrap items-center gap-3 pt-1 text-xs">
                      <div :class="['font-semibold flex items-center space-x-1', loan.is_overdue ? 'text-rose-700' : 'text-neutral-black']">
                        <Calendar class="w-3.5 h-3.5" />
                        <span>Due: {{ formatDate(loan.due_at) }}</span>
                        <span class="text-neutral-muted">({{ getDaysRemainingText(loan.due_at) }})</span>
                      </div>

                      <span 
                        v-if="loan.is_overdue" 
                        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-600 text-white flex items-center space-x-1"
                      >
                        <AlertTriangle class="w-3 h-3" />
                        <span>OVERDUE</span>
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Action & Renew Button -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 shrink-0 md:pl-4 md:border-l md:border-neutral-200">
                  <div class="text-right sm:text-center px-2 hidden sm:block">
                    <span class="text-[10px] font-bold text-neutral-muted uppercase tracking-wider block">Renewals</span>
                    <span class="text-xs font-bold text-neutral-black">{{ loan.renewed_count }} / 2</span>
                  </div>

                  <button
                    @click="handleRenew(loan.uuid)"
                    :disabled="loan.renewed_count >= 2 || loan.is_overdue"
                    class="px-4 py-2.5 rounded-xl bg-white hover:bg-neutral-50 text-neutral-black border border-neutral-ivory font-semibold text-xs transition-all shadow-sm disabled:opacity-40 disabled:hover:bg-white flex items-center justify-center space-x-2"
                  >
                    <RotateCcw class="w-3.5 h-3.5 text-primary" />
                    <span>Renew Loan ({{ loan.renewed_count }}/2)</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 2: Hold Reservations -->
          <div v-if="activeTab === 'holds'" class="space-y-4">
            <div v-if="isLoading" class="space-y-3">
              <div v-for="i in 2" :key="i" class="h-24 bg-neutral-background animate-pulse rounded-2xl"></div>
            </div>

            <!-- Empty State -->
            <div v-else-if="activeHolds.length === 0" class="text-center py-12 px-4 space-y-4">
              <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-700 mx-auto flex items-center justify-center">
                <Bookmark class="w-8 h-8" />
              </div>
              <div class="max-w-md mx-auto space-y-1">
                <h3 class="text-lg font-display font-bold text-neutral-black">No Active Hold Reservations</h3>
                <p class="text-xs text-neutral-muted">You do not have any active book hold requests. When books are checked out by others, you can reserve your spot in line.</p>
              </div>
              <router-link 
                to="/library" 
                class="inline-flex items-center space-x-2 px-5 py-2.5 rounded-xl bg-amber-700 text-white hover:bg-amber-800 font-semibold text-xs transition-all shadow-md"
              >
                <span>Find Books to Reserve</span>
                <ChevronRight class="w-4 h-4" />
              </router-link>
            </div>

            <!-- Hold Reservation Cards Grid -->
            <div v-else class="grid grid-cols-1 gap-4">
              <div
                v-for="hold in activeHolds"
                :key="hold.id"
                class="p-5 bg-neutral-background border border-neutral-ivory rounded-2xl transition-all flex flex-col md:flex-row md:items-center justify-between gap-4"
              >
                <div class="flex items-start space-x-4">
                  <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0 font-bold">
                    <Bookmark class="w-6 h-6" />
                  </div>

                  <div class="space-y-1.5">
                    <h4 class="text-base font-bold text-neutral-black">{{ hold.book?.title || 'Reserved Book' }}</h4>
                    
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                      <span class="px-2.5 py-0.5 rounded-full font-bold bg-neutral-200 text-neutral-700">
                        Queue Position #{{ hold.queue_position }}
                      </span>

                      <span 
                        :class="[
                          'px-2.5 py-0.5 rounded-full font-bold capitalize border',
                          hold.status === 'ready_for_pickup' 
                            ? 'bg-emerald-100 text-emerald-800 border-emerald-300' 
                            : 'bg-amber-50 text-amber-800 border-amber-200'
                        ]"
                      >
                        {{ hold.status_label || hold.status }}
                      </span>
                    </div>

                    <p v-if="hold.expires_at" class="text-xs font-semibold text-emerald-800 pt-1">
                      Ready! Pickup window expires on {{ formatDate(hold.expires_at) }}
                    </p>
                  </div>
                </div>

                <!-- Action Button -->
                <div class="shrink-0 md:pl-4 md:border-l md:border-neutral-200 flex items-center justify-end">
                  <button
                    @click="handleCancelHold(hold.uuid)"
                    class="px-4 py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold text-xs border border-rose-200 transition-all flex items-center space-x-1.5"
                  >
                    <span>Cancel Hold</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 3: Rules & Help -->
          <div v-if="activeTab === 'rules'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="p-5 bg-neutral-background border border-neutral-ivory rounded-2xl space-y-2">
                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">
                  <Clock class="w-5 h-5" />
                </div>
                <h4 class="text-sm font-bold text-neutral-black">Standard Loan Period</h4>
                <p class="text-xs text-neutral-muted leading-relaxed">
                  Books may be borrowed for up to 14 calendar days. Daily automated reminders will be sent 2 days prior to your due date.
                </p>
              </div>

              <div class="p-5 bg-neutral-background border border-neutral-ivory rounded-2xl space-y-2">
                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">
                  <RotateCcw class="w-5 h-5" />
                </div>
                <h4 class="text-sm font-bold text-neutral-black">Loan Renewals</h4>
                <p class="text-xs text-neutral-muted leading-relaxed">
                  Active loans may be renewed up to 2 times (adding 14 days each), provided no other member has placed a hold request on the book.
                </p>
              </div>

              <div class="p-5 bg-neutral-background border border-neutral-ivory rounded-2xl space-y-2">
                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">
                  <QrCode class="w-5 h-5" />
                </div>
                <h4 class="text-sm font-bold text-neutral-black">Self-Service Scanner</h4>
                <p class="text-xs text-neutral-muted leading-relaxed">
                  Use your mobile device or desktop webcam to scan book barcodes directly in the MSA Library room for quick checkouts and returns.
                </p>
              </div>
            </div>

            <div class="bg-primary/5 border border-primary/20 rounded-2xl p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
              <div class="space-y-1">
                <h4 class="text-base font-bold text-neutral-black">Ready to Check Out or Return a Book?</h4>
                <p class="text-xs text-neutral-muted">Launch the self-service circulation scanner to scan book barcodes directly on site.</p>
              </div>
              <router-link 
                to="/library/scan" 
                class="px-5 py-2.5 rounded-xl bg-primary text-white hover:bg-primary-hover font-semibold text-xs transition-all shrink-0 flex items-center space-x-2 shadow-md"
              >
                <QrCode class="w-4 h-4" />
                <span>Open Circulation Scanner</span>
              </router-link>
            </div>
          </div>

        </div>

      </div>

    </div>
  </div>
</template>
e>

