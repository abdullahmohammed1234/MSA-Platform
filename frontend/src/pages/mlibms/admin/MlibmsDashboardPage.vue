<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { BookOpen, QrCode, Clock, Users, Bookmark, PackagePlus, ArrowUpRight } from 'lucide-vue-next';
import mlibmsAdminService from '@/services/mlibms/mlibmsAdminService';

const stats = ref<any>(null);
const isLoading = ref(true);

const fetchStats = async () => {
  isLoading.value = true;
  try {
    const res = await mlibmsAdminService.getStats();
    stats.value = res;
  } catch (e) {
    console.error('Failed to load stats', e);
  } finally {
    isLoading.value = false;
  }
};

onMounted(fetchStats);
</script>

<template>
  <div class="space-y-8">
    <div>
      <h1 class="text-2xl font-display font-bold text-neutral-black">MLibMS Admin Dashboard</h1>
      <p class="text-xs text-neutral-muted mt-1">Operational metrics, circulation status, and library inventory oversight.</p>
    </div>

    <!-- Quick Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <div class="bg-white border border-neutral-ivory rounded-2xl p-5 space-y-2 shadow-soft">
        <div class="flex items-center justify-between text-neutral-muted">
          <span class="text-xs font-bold uppercase tracking-wider">Total Books</span>
          <BookOpen class="w-4 h-4 text-primary" />
        </div>
        <div class="text-3xl font-black text-neutral-black">{{ stats?.total_books ?? '0' }}</div>
        <p class="text-[11px] text-neutral-muted font-medium">{{ stats?.total_copies ?? 0 }} total physical copies</p>
      </div>

      <div class="bg-white border border-neutral-ivory rounded-2xl p-5 space-y-2 shadow-soft">
        <div class="flex items-center justify-between text-neutral-muted">
          <span class="text-xs font-bold uppercase tracking-wider">Active Loans</span>
          <Clock class="w-4 h-4 text-blue-600" />
        </div>
        <div class="text-3xl font-black text-neutral-black">{{ stats?.active_loans ?? '0' }}</div>
        <p class="text-[11px] text-neutral-muted font-medium">{{ stats?.overdue_loans ?? 0 }} currently overdue</p>
      </div>

      <div class="bg-white border border-neutral-ivory rounded-2xl p-5 space-y-2 shadow-soft">
        <div class="flex items-center justify-between text-neutral-muted">
          <span class="text-xs font-bold uppercase tracking-wider">Active Holds</span>
          <Bookmark class="w-4 h-4 text-amber-600" />
        </div>
        <div class="text-3xl font-black text-neutral-black">{{ stats?.active_holds ?? '0' }}</div>
        <p class="text-[11px] text-neutral-muted font-medium">Member reservation queue</p>
      </div>

      <div class="bg-white border border-neutral-ivory rounded-2xl p-5 space-y-2 shadow-soft">
        <div class="flex items-center justify-between text-neutral-muted">
          <span class="text-xs font-bold uppercase tracking-wider">Members Roster</span>
          <Users class="w-4 h-4 text-purple-600" />
        </div>
        <div class="text-3xl font-black text-neutral-black">{{ stats?.total_members ?? '0' }}</div>
        <p class="text-[11px] text-neutral-muted font-medium">{{ stats?.suspended_members ?? 0 }} suspended accounts</p>
      </div>
    </div>

    <!-- Quick Action Launchpad -->
    <div class="bg-white border border-neutral-ivory rounded-2xl p-6 space-y-4 shadow-soft">
      <h3 class="text-xs font-bold text-neutral-muted uppercase tracking-wider">Admin Launchpad</h3>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <router-link
          to="/library/admin/intake"
          class="p-4 rounded-xl bg-neutral-background hover:bg-white border border-neutral-ivory hover:border-primary/40 transition-all group flex flex-col justify-between space-y-3 shadow-sm hover:shadow-soft"
        >
          <div class="flex items-center justify-between">
            <div class="w-9 h-9 rounded-lg bg-primary/10 text-primary border border-primary/20 flex items-center justify-center">
              <PackagePlus class="w-5 h-5" />
            </div>
            <ArrowUpRight class="w-4 h-4 text-neutral-muted group-hover:text-primary transition-colors" />
          </div>
          <div>
            <h4 class="font-bold text-sm text-neutral-black group-hover:text-primary transition-colors">Book Intake (ISBN)</h4>
            <p class="text-xs text-neutral-muted">Scan ISBN, catalog new books, generate barcodes.</p>
          </div>
        </router-link>

        <router-link
          to="/library/admin/copies"
          class="p-4 rounded-xl bg-neutral-background hover:bg-white border border-neutral-ivory hover:border-blue-500/40 transition-all group flex flex-col justify-between space-y-3 shadow-sm hover:shadow-soft"
        >
          <div class="flex items-center justify-between">
            <div class="w-9 h-9 rounded-lg bg-blue-500/10 text-blue-700 border border-blue-500/20 flex items-center justify-center">
              <QrCode class="w-5 h-5" />
            </div>
            <ArrowUpRight class="w-4 h-4 text-neutral-muted group-hover:text-blue-700 transition-colors" />
          </div>
          <div>
            <h4 class="font-bold text-sm text-neutral-black group-hover:text-blue-700 transition-colors">Copy Inventory</h4>
            <p class="text-xs text-neutral-muted">Manage barcodes, accession labels, conditions.</p>
          </div>
        </router-link>

        <router-link
          to="/library/admin/members"
          class="p-4 rounded-xl bg-neutral-background hover:bg-white border border-neutral-ivory hover:border-purple-500/40 transition-all group flex flex-col justify-between space-y-3 shadow-sm hover:shadow-soft"
        >
          <div class="flex items-center justify-between">
            <div class="w-9 h-9 rounded-lg bg-purple-500/10 text-purple-700 border border-purple-500/20 flex items-center justify-center">
              <Users class="w-5 h-5" />
            </div>
            <ArrowUpRight class="w-4 h-4 text-neutral-muted group-hover:text-purple-700 transition-colors" />
          </div>
          <div>
            <h4 class="font-bold text-sm text-neutral-black group-hover:text-purple-700 transition-colors">Walk-in Guests</h4>
            <p class="text-xs text-neutral-muted">Register guest walk-in borrowers (staff-assisted).</p>
          </div>
        </router-link>
      </div>
    </div>
  </div>
</template>

