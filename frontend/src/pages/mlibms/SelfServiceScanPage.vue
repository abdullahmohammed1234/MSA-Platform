<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { 
  QrCode, 
  ArrowLeft, 
  CheckCircle2, 
  AlertCircle, 
  BookOpen, 
  RotateCcw, 
  Bookmark, 
  Sparkles,
  Barcode,
  HelpCircle
} from 'lucide-vue-next';
import CameraBarcodeScanner from '@/components/mlibms/CameraBarcodeScanner.vue';
import mlibmsService, { type MemberLoan } from '@/services/mlibms/mlibmsService';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

const activeMode = ref<'checkout' | 'return'>('checkout');
const scannedBarcode = ref('');
const isProcessing = ref(false);
const lastResult = ref<{ success: boolean; message: string; loan?: MemberLoan; mode?: 'checkout' | 'return' } | null>(null);

const handleScanSubmit = async () => {
  const barcode = scannedBarcode.value.trim();
  if (!barcode || isProcessing.value) return;

  isProcessing.value = true;

  try {
    let res;
    if (activeMode.value === 'checkout') {
      res = await mlibmsService.selfServiceCheckout(barcode);
    } else {
      res = await mlibmsService.selfServiceReturn(barcode);
    }

    lastResult.value = {
      success: true,
      message: res.message || 'Operation successful!',
      loan: res.data,
      mode: activeMode.value
    };
    scannedBarcode.value = '';
    toast.success(res.message);
  } catch (e: any) {
    const errorMsg = e.response?.data?.message || 'Self-service circulation request failed.';
    lastResult.value = {
      success: false,
      message: errorMsg,
      mode: activeMode.value
    };
    toast.error(errorMsg);
  } finally {
    isProcessing.value = false;
  }
};

const handleCameraDetected = (barcode: string) => {
  if (!barcode || isProcessing.value) return;
  scannedBarcode.value = barcode;
  handleScanSubmit();
};

const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Enter' && scannedBarcode.value.length > 3) {
    handleScanSubmit();
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
  <div class="pt-28 pb-16 md:pt-32 md:pb-20 bg-neutral-background min-h-[calc(100vh-16rem)]">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 space-y-6">
      
      <!-- Top Navigation & Breadcrumb -->
      <div class="flex flex-wrap items-center justify-between gap-4">
        <router-link 
          to="/library" 
          class="inline-flex items-center space-x-2 text-neutral-muted hover:text-neutral-black text-sm font-semibold transition-colors group"
        >
          <ArrowLeft class="w-4 h-4 transition-transform group-hover:-translate-x-1" />
          <span>Back to Library Catalog</span>
        </router-link>

        <router-link 
          to="/library/my-loans" 
          class="inline-flex items-center space-x-2 text-xs font-bold text-primary bg-primary/10 hover:bg-primary/20 border border-primary/20 px-3.5 py-1.5 rounded-full transition-all"
        >
          <Bookmark class="w-3.5 h-3.5" />
          <span>My Loans & Holds</span>
        </router-link>
      </div>

      <!-- Station Header Banner -->
      <div class="bg-white border border-neutral-ivory rounded-3xl p-6 sm:p-8 shadow-soft flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-start space-x-4">
          <div class="w-14 h-14 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0 shadow-sm">
            <QrCode class="w-7 h-7" />
          </div>
          <div class="space-y-1">
            <div class="flex items-center space-x-2">
              <h1 class="text-2xl sm:text-3xl font-display font-bold text-neutral-black">Self-Service Circulation Station</h1>
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                Live Station
              </span>
            </div>
            <p class="text-xs sm:text-sm text-neutral-muted">
              Scan book copy barcodes using your device camera or USB barcode reader to instantly check out or return items.
            </p>
          </div>
        </div>
      </div>

      <!-- Main Circulation Station Grid Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- LEFT COLUMN: Circulation Mode & Live Camera Scanner (7 cols) -->
        <div class="lg:col-span-7 space-y-6">
          
          <!-- Circulation Mode Selector Card -->
          <div class="bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft space-y-4">
            <div class="flex items-center justify-between">
              <span class="text-xs font-extrabold uppercase tracking-wider text-neutral-muted">
                1. Select Operation Mode
              </span>
              <span class="text-xs font-mono text-neutral-black font-semibold">
                Mode: {{ activeMode === 'checkout' ? 'Book Checkout' : 'Book Return' }}
              </span>
            </div>

            <div class="grid grid-cols-2 p-1.5 bg-neutral-background border border-neutral-ivory rounded-2xl gap-1">
              <button
                @click="activeMode = 'checkout'"
                :class="[
                  'py-3.5 px-4 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center space-x-2',
                  activeMode === 'checkout'
                    ? 'bg-primary text-white shadow-md'
                    : 'text-neutral-muted hover:text-neutral-black hover:bg-white/50'
                ]"
              >
                <BookOpen class="w-4 h-4" />
                <span>Borrow Book Copy</span>
              </button>

              <button
                @click="activeMode = 'return'"
                :class="[
                  'py-3.5 px-4 rounded-xl font-bold text-xs sm:text-sm transition-all flex items-center justify-center space-x-2',
                  activeMode === 'return'
                    ? 'bg-emerald-700 text-white shadow-md'
                    : 'text-neutral-muted hover:text-neutral-black hover:bg-white/50'
                ]"
              >
                <RotateCcw class="w-4 h-4" />
                <span>Return Book Copy</span>
              </button>
            </div>

            <p class="text-xs text-neutral-muted px-1 flex items-center space-x-1.5">
              <Sparkles class="w-3.5 h-3.5 text-primary shrink-0" />
              <span>
                {{ activeMode === 'checkout' 
                  ? 'Standard borrowing period is 14 days. Up to 2 renewals allowed if no holds exist.' 
                  : 'Return your borrowed books here to immediately update your account status.' 
                }}
              </span>
            </p>
          </div>

          <!-- Camera Scanner Viewport Card -->
          <div class="bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-extrabold uppercase tracking-wider text-neutral-muted">
                2. Live Camera Viewfinder
              </span>
              <span class="text-xs font-semibold text-emerald-700 flex items-center">
                <span class="w-2 h-2 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                Camera Ready
              </span>
            </div>

            <!-- Integrated Camera Barcode Scanner component -->
            <CameraBarcodeScanner 
              @scan="handleCameraDetected" 
              @scan-success="handleCameraDetected" 
            />
          </div>

        </div>

        <!-- RIGHT COLUMN: Barcode Form, Receipt & Station Guide (5 cols) -->
        <div class="lg:col-span-5 space-y-6">
          
          <!-- Barcode Input & Transaction Action Card -->
          <div class="bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft space-y-5">
            <div class="flex items-center justify-between border-b border-neutral-ivory pb-3">
              <div class="flex items-center space-x-2">
                <Barcode class="w-5 h-5 text-primary" />
                <span class="text-sm font-bold text-neutral-black">Manual Barcode Input</span>
              </div>
              <span 
                :class="[
                  'px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                  activeMode === 'checkout' ? 'bg-primary/10 text-primary' : 'bg-emerald-100 text-emerald-800'
                ]"
              >
                {{ activeMode }}
              </span>
            </div>

            <form @submit.prevent="handleScanSubmit" class="space-y-4">
              <div>
                <label class="block text-xs font-bold text-neutral-muted uppercase tracking-wider mb-2">
                  Enter or Scan Copy Barcode
                </label>
                <div class="relative">
                  <input
                    v-model="scannedBarcode"
                    type="text"
                    placeholder="e.g. MLIB-C-000104"
                    class="w-full px-4 py-3.5 bg-neutral-background border border-neutral-ivory rounded-xl text-primary font-mono font-bold placeholder-neutral-400 text-center text-lg focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-inner"
                  />
                  <button
                    v-if="scannedBarcode"
                    type="button"
                    @click="scannedBarcode = ''"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-neutral-400 hover:text-neutral-600 px-1"
                  >
                    Clear
                  </button>
                </div>
              </div>

              <button
                type="submit"
                :disabled="isProcessing || !scannedBarcode.trim()"
                :class="[
                  'w-full py-3.5 rounded-xl text-white font-bold text-sm transition-all shadow-md disabled:opacity-50 disabled:shadow-none flex items-center justify-center space-x-2',
                  activeMode === 'checkout' ? 'bg-primary hover:bg-primary-hover' : 'bg-emerald-700 hover:bg-emerald-800'
                ]"
              >
                <component :is="activeMode === 'checkout' ? BookOpen : RotateCcw" class="w-4 h-4" />
                <span>
                  {{ isProcessing 
                    ? 'Processing Request...' 
                    : (activeMode === 'checkout' ? 'Confirm Borrow Checkout' : 'Confirm Book Return') 
                  }}
                </span>
              </button>
            </form>
          </div>

          <!-- Live Transaction Receipt Card -->
          <div 
            v-if="lastResult" 
            :class="[
              'p-6 rounded-3xl border transition-all space-y-3 shadow-soft',
              lastResult.success 
                ? 'bg-emerald-50/70 border-emerald-200 text-emerald-900' 
                : 'bg-rose-50/70 border-rose-200 text-rose-900'
            ]"
          >
            <div class="flex items-center space-x-2 font-bold text-base">
              <component :is="lastResult.success ? CheckCircle2 : AlertCircle" class="w-5 h-5 shrink-0" />
              <span>{{ lastResult.message }}</span>
            </div>

            <div v-if="lastResult.loan" class="pt-3 border-t border-neutral-ivory/60 text-xs space-y-1.5">
              <div class="flex justify-between">
                <span class="text-neutral-600">Book Title:</span>
                <span class="font-bold text-neutral-black text-right">{{ lastResult.loan.copy?.book?.title || 'N/A' }}</span>
              </div>

              <div class="flex justify-between">
                <span class="text-neutral-600">Barcode:</span>
                <span class="font-mono font-bold text-neutral-black">{{ lastResult.loan.copy?.barcode }}</span>
              </div>

              <div v-if="lastResult.loan.due_at" class="flex justify-between pt-1">
                <span class="text-neutral-600 font-semibold">Due Date:</span>
                <span class="font-bold text-primary">{{ new Date(lastResult.loan.due_at).toLocaleDateString() }}</span>
              </div>
            </div>
          </div>

          <!-- Station Quick Instructions Card -->
          <div class="bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft space-y-3">
            <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-neutral-muted">
              <HelpCircle class="w-4 h-4 text-primary" />
              <span>Station Guidelines</span>
            </div>

            <ul class="text-xs text-neutral-muted space-y-2 leading-relaxed">
              <li class="flex items-start space-x-2">
                <span class="w-4 h-4 rounded-full bg-primary/10 text-primary font-bold text-[10px] flex items-center justify-center shrink-0 mt-0.5">1</span>
                <span>Align barcode label in live camera preview or use barcode scanner tool.</span>
              </li>
              <li class="flex items-start space-x-2">
                <span class="w-4 h-4 rounded-full bg-primary/10 text-primary font-bold text-[10px] flex items-center justify-center shrink-0 mt-0.5">2</span>
                <span>Select <strong>Borrow</strong> to check out or <strong>Return</strong> to check in.</span>
              </li>
              <li class="flex items-start space-x-2">
                <span class="w-4 h-4 rounded-full bg-primary/10 text-primary font-bold text-[10px] flex items-center justify-center shrink-0 mt-0.5">3</span>
                <span>View real-time receipt notification confirming due date or return status.</span>
              </li>
            </ul>
          </div>

        </div>

      </div>

    </div>
  </div>
</template>


