<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { rolePermissionService } from '@/services/admin/rolePermission.service';
import type { AuditLog } from '@/types/auth';
import { 
  FileText, 
  Clock, 
  Search, 
  ChevronRight, 
  RefreshCw, 
  X,
  Database,
  Globe
} from 'lucide-vue-next';

// State variables
const logs = ref<AuditLog[]>([]);
const pagination = ref({ page: 1, lastPage: 1, total: 0 });
const searchQuery = ref('');
const categoryFilter = ref('All');
const severityFilter = ref('All');
const isLoading = ref(false);
const isRefreshing = ref(false);
const selectedLog = ref<any | null>(null);

// Static mock logs removed — audit logs are API-backed only.

const fetchLogs = async (page = 1) => {
  isLoading.value = true;
  try {
    const res = await rolePermissionService.getAuditLogs(page);
    const realLogs = res.audit_logs?.data || [];
    logs.value = realLogs;
    pagination.value = {
      page: res.audit_logs?.current_page || page,
      lastPage: res.audit_logs?.last_page || 1,
      total: res.audit_logs?.total || realLogs.length,
    };
  } catch (err) {
    console.error('Failed to load audit logs', err);
    logs.value = [];
    pagination.value = { page: 1, lastPage: 1, total: 0 };
  } finally {
    isLoading.value = false;
    isRefreshing.value = false;
  }
};

const handleRefresh = async () => {
  isRefreshing.value = true;
  await fetchLogs(1);
  setTimeout(() => {
    isRefreshing.value = false;
  }, 600);
};

onMounted(() => {
  fetchLogs();
});

// Helper functions for categorization and severities
const getCategory = (log: AuditLog) => {
  const target = log.target_type?.toLowerCase() || '';
  const action = log.action.toLowerCase();
  
  if (target.includes('course') || target.includes('lesson') || target.includes('module') || action.includes('course')) {
    return 'Curriculum';
  }
  if (target.includes('cert') || action.includes('sanad') || action.includes('certificate')) {
    return 'Certification';
  }
  if (target.includes('discus') || action.includes('moderation') || action.includes('comment') || target.includes('moderation')) {
    return 'Moderation';
  }
  return 'Security';
};

const getSeverity = (log: AuditLog) => {
  const action = log.action.toLowerCase();
  
  if (action.includes('delete') || action.includes('revoke') || action.includes('force') || action.includes('rotated') || action.includes('fail')) {
    return 'critical';
  }
  if (action.includes('update') || action.includes('modify') || action.includes('change') || action.includes('resolve') || action.includes('flag')) {
    return 'warning';
  }
  return 'info';
};

const getSeverityBadgeClass = (sev: string) => {
  switch (sev) {
    case 'critical':
      return 'bg-red-50 text-red-700 border-red-200';
    case 'warning':
      return 'bg-accent-gold/20 text-primary border-amber-200';
    default:
      return 'bg-primary/5 text-blue-700 border-blue-200';
  }
};

const getRelativeTime = (dateStr: string) => {
  const time = new Date(dateStr).getTime();
  const now = Date.now();
  const diff = now - time;
  
  const mins = Math.floor(diff / 60000);
  if (mins < 60) return mins <= 1 ? 'Just now' : `${mins} minutes ago`;
  
  const hours = Math.floor(mins / 60);
  if (hours < 24) return hours === 1 ? '1 hour ago' : `${hours} hours ago`;
  
  return new Date(dateStr).toLocaleDateString();
};

const getActorAvatar = (log: any) => {
  const name = log.user?.name || 'System';
  return `https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(name)}`;
};

// Filtered Logs
const filteredLogs = computed(() => {
  return logs.value.filter(log => {
    const actionMatch = log.action.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                        (log.description || '').toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                        (log.user?.name || '').toLowerCase().includes(searchQuery.value.toLowerCase());
                        
    const cat = getCategory(log);
    const categoryMatch = categoryFilter.value === 'All' || cat === categoryFilter.value;
    
    const sev = getSeverity(log);
    const severityMatch = severityFilter.value === 'All' || sev === severityFilter.value;
    
    return actionMatch && categoryMatch && severityMatch;
  });
});
</script>

<template>
  <div class="space-y-8 max-w-7xl mx-auto p-1 text-[#640c0e]">
    <!-- Visual Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white border border-[#ebe8de] rounded-2xl p-6 shadow-premium relative overflow-hidden">
      <div class="absolute top-0 right-0 h-24 w-24 opacity-[0.015] font-display pointer-events-none text-9xl">
        ☪
      </div>
      <div class="space-y-1">
        <h2 class="font-display font-black text-2xl text-[#640c0e] tracking-tight flex items-center gap-2">
          <FileText class="h-6 w-6 text-[#ffdc83]" />
          System Audit Trail & Event Logs
        </h2>
        <p class="text-sm text-neutral-muted font-sans">
          Cryptographically tracked logs recording supervisor changes, curriculum updates, and credentials minting cycles.
        </p>
      </div>
      <div class="text-[10px] sm:bg-neutral-ivory/50 border border-[#ebe8de] rounded-xl px-4 py-2 font-mono text-primary font-bold text-center">
        Ledger Status: Active & Operational
      </div>
    </div>

    <!-- Filters and Sync Button -->
    <div class="bg-white border border-[#ebe8de] rounded-2xl p-5 shadow-premium">
      <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
        
        <!-- Search and filters -->
        <div class="flex flex-col sm:flex-row gap-3 w-full md:max-w-3xl">
          <div class="relative flex-grow">
            <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-muted h-4 w-4" />
            <input
              type="text"
              placeholder="Filter by action description or actor name..."
              v-model="searchQuery"
              class="w-full bg-[#ebe8de]/40 text-xs border border-[#ebe8de] rounded-xl pl-10 pr-4 py-3 font-semibold focus:outline-none focus:ring-1 focus:ring-[#640c0e]/20 text-[#640c0e]"
            />
          </div>

          <div class="flex gap-2">
            <select
              v-model="categoryFilter"
              class="bg-[#ebe8de]/40 border border-[#ebe8de] text-xs font-semibold rounded-xl px-4 py-3 focus:outline-none focus:ring-1 focus:ring-[#640c0e]/20 text-[#640c0e]"
            >
              <option value="All">All Categories</option>
              <option value="Curriculum">Curriculum</option>
              <option value="Moderation">Moderation</option>
              <option value="Certification">Certification</option>
              <option value="Security">Security</option>
            </select>

            <select
              v-model="severityFilter"
              class="bg-[#ebe8de]/40 border border-[#ebe8de] text-xs font-semibold rounded-xl px-4 py-3 focus:outline-none focus:ring-1 focus:ring-[#640c0e]/20 text-[#640c0e]"
            >
              <option value="All">All Severities</option>
              <option value="info">Info Only</option>
              <option value="warning">Warning Only</option>
              <option value="critical">Critical Only</option>
            </select>
          </div>
        </div>

        <!-- Refresh Button -->
        <button
          @click="handleRefresh"
          :disabled="isRefreshing"
          class="w-full md:w-auto h-11 px-5 rounded-xl border border-[#ebe8de] bg-[#ebe8de]/50 hover:bg-[#fffbf4] text-[#640c0e] font-serif text-xs font-bold flex items-center justify-center gap-2 cursor-pointer transition-all shrink-0"
        >
          <RefreshCw :class="['h-3.5 w-3.5', isRefreshing ? 'animate-spin' : '']" />
          {{ isRefreshing ? "Reading ledger..." : "Re-Sync Trail" }}
        </button>
      </div>
    </div>

    <!-- Main Content Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      <!-- Chronology Timeline -->
      <div class="lg:col-span-8 space-y-4">
        <h3 class="font-display font-black text-sm text-[#640c0e]/60 tracking-wider px-1">IMMUTABLE CHRONOLOGY</h3>

        <div v-if="isLoading" class="space-y-4">
          <div v-for="v in [1, 2, 3]" :key="v" class="bg-white border border-[#ebe8de] rounded-2xl p-5 flex gap-4 animate-pulse">
            <div class="w-10 h-10 bg-neutral-ivory rounded-xl shrink-0" />
            <div class="space-y-2 flex-1">
              <div class="h-4 bg-neutral-ivory rounded-md w-1/3" />
              <div class="h-3 bg-neutral-ivory rounded-md w-3/4" />
              <div class="h-3 bg-neutral-ivory rounded-md w-2/3" />
            </div>
          </div>
        </div>

        <div v-else-if="filteredLogs.length === 0" class="bg-white border border-[#ebe8de] rounded-2xl p-12 text-center">
          <Clock class="w-12 h-12 text-neutral-muted/30 mx-auto mb-3" />
          <h4 class="font-bold text-sm text-[#640c0e]">No matching audit logs</h4>
          <p class="text-xs text-neutral-muted max-w-xs mx-auto mt-1">Refine your queries or select 'All Categories' to retrieve previous ledger indexes.</p>
        </div>

        <div v-else class="relative pl-6 border-l-2 border-[#640c0e]/20 space-y-6">
          <div 
            v-for="log in filteredLogs" 
            :key="log.id" 
            @click="selectedLog = log"
            class="relative group cursor-pointer"
          >
            <!-- Timeline dot -->
            <div class="absolute -left-[31px] top-6 w-3.5 h-3.5 rounded-full bg-[#640c0e] border-4 border-white group-hover:scale-125 transition-transform shadow-sm" />

            <div class="bg-white border border-[#ebe8de] rounded-2xl p-5 hover:border-[#640c0e]/45 hover:shadow-md transition-all space-y-3">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                  <span class="text-[9px] uppercase font-mono font-black text-[#640c0e] tracking-widest bg-[#fffbf4] px-2.5 py-0.5 rounded border border-[#ebe8de]">
                    {{ getCategory(log) }}
                  </span>
                  <span :class="['inline-flex items-center text-[8px] font-mono font-bold uppercase tracking-widest px-2 py-0.5 border rounded-sm', getSeverityBadgeClass(getSeverity(log))]">
                    {{ getSeverity(log) }}
                  </span>
                </div>

                <span class="text-[10px] text-neutral-muted font-mono flex items-center gap-1 shrink-0">
                  <Clock :size="11" />
                  {{ getRelativeTime(log.created_at) }}
                </span>
              </div>

              <h4 class="font-display font-black text-sm text-[#640c0e] leading-tight group-hover:underline">
                {{ log.action }}
              </h4>
              <p class="text-[11px] text-neutral-muted/95 leading-relaxed line-clamp-2">
                {{ log.description || 'System operation executed.' }}
              </p>

              <!-- Footer with Actor -->
              <div class="flex items-center justify-between gap-3 pt-3 border-t border-[#ebe8de]/50 mt-1">
                <div class="flex items-center gap-2">
                  <img :src="getActorAvatar(log)" alt="" class="w-6 h-6 rounded-full border border-[#ebe8de]" />
                  <span class="text-xs font-bold text-neutral-black">{{ log.user?.name || 'System' }}</span>
                  <span class="text-[8px] text-neutral-muted font-mono uppercase bg-neutral-100 px-1 py-0.5 rounded leading-none">{{ log.user?.roles?.[0] || 'System' }}</span>
                </div>

                <span class="text-[10px] font-mono font-semibold text-[#640c0e]/80 flex items-center gap-0.5 group-hover:translate-x-1 transition-transform">
                  Audit Details <ChevronRight :size="12" />
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Ledger Stats sidebar -->
      <div class="lg:col-span-4 space-y-6">
        <div class="bg-white border border-[#ebe8de] rounded-2xl p-6 shadow-premium relative overflow-hidden space-y-6">
          <div class="absolute top-0 right-0 w-32 h-32 bg-[#ffdc83]/5 rounded-full blur-xl pointer-events-none" />
          <div class="flex items-center gap-2 border-b border-[#ebe8de]/60 pb-4">
            <Database :size="18" class="text-[#ffdc83]" />
            <h3 class="font-display font-black text-sm text-[#640c0e] tracking-tight">Ledger Metadata</h3>
          </div>

          <div class="space-y-4 text-xs">
            <div class="p-3.5 bg-[#fffbf4]/40 border border-[#ebe8de] rounded-2xl space-y-1">
              <span class="text-[9px] font-mono font-bold text-neutral-muted uppercase">Total Records Indexed</span>
              <p class="font-display font-black text-lg text-[#640c0e]">{{ filteredLogs.length }} Commits</p>
            </div>

            <div class="space-y-2">
              <span class="text-[9px] font-mono font-bold text-neutral-muted uppercase">Ledger Sync Security</span>
              <div class="flex items-center justify-between p-2.5 bg-[#ebe8de] rounded-xl border border-[#ebe8de]/40">
                <span class="font-semibold text-neutral-black">Sync State</span>
                <span class="text-[9.5px] font-mono font-bold uppercase text-secondary bg-secondary/10 px-1.5 border border-secondary/20 rounded leading-none">Healthy</span>
              </div>
              <div class="flex items-center justify-between p-2.5 bg-[#ebe8de] rounded-xl border border-[#ebe8de]/40">
                <span class="font-semibold text-neutral-black">Audit Stamp</span>
                <span class="text-[9.5px] font-mono font-bold text-[#640c0e]">SHA256 SIGNED</span>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-[#ebe8de] border border-[#ebe8de] p-6 border-l-4 border-l-[#640c0e] rounded-2xl space-y-3">
          <div class="flex items-center gap-2">
            <Globe :size="16" class="text-[#640c0e] shrink-0" />
            <h4 class="font-bold text-xs text-primary">Campus Network Restrictions</h4>
          </div>
          <p class="text-[11px] text-neutral-muted leading-relaxed">
            All database state modifications are signed. Changes made outside university IP pools are flagged automatically within the system security center.
          </p>
        </div>
      </div>
    </div>

    <!-- Slide-out Drawer Panel -->
    <div v-if="selectedLog" class="fixed inset-0 z-50 flex items-center justify-end p-0 md:p-4">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black/50 backdrop-blur-xs" @click="selectedLog = null"></div>

      <!-- Drawer -->
      <div class="relative bg-white border-l border-[#ebe8de] w-full max-w-lg h-full md:h-[calc(100vh-32px)] md:rounded-3xl shadow-2xl p-6 md:p-8 flex flex-col justify-between overflow-y-auto z-10">
        <div class="space-y-6">
          <!-- Drawer Header -->
          <div class="flex items-center justify-between border-b border-[#ebe8de] border-dashed pb-4">
            <div class="flex items-center gap-2">
              <Database :size="18" class="text-[#ffdc83]" />
              <span class="text-xs font-mono font-bold text-neutral-muted">Ledger Transaction #{{ selectedLog.id }}</span>
            </div>
            <button 
              @click="selectedLog = null"
              class="p-1 rounded-lg border border-[#ebe8de]/60 text-neutral-muted hover:bg-[#fffbf4]"
            >
              <X :size="16" />
            </button>
          </div>

          <!-- Event Title -->
          <div class="space-y-2">
            <div class="flex gap-2 items-center">
              <span class="text-[9px] font-mono font-black uppercase text-[#640c0e] bg-[#fffbf4] px-2 py-0.5 rounded border border-[#ebe8de]">
                {{ getCategory(selectedLog) }}
              </span>
              <span :class="['inline-flex items-center text-[8px] font-mono font-bold uppercase tracking-widest px-2 py-0.5 border rounded-sm', getSeverityBadgeClass(getSeverity(selectedLog))]">
                {{ getSeverity(selectedLog) }}
              </span>
            </div>
            <h3 class="font-display font-black text-lg text-[#640c0e] leading-tight">
              {{ selectedLog.action }}
            </h3>
            <p class="text-[10px] text-neutral-muted font-mono">{{ new Date(selectedLog.created_at).toLocaleString() }}</p>
          </div>

          <!-- Description Box -->
          <div class="p-4 bg-[#ebe8de]/50 border border-[#ebe8de] rounded-2xl text-xs text-neutral-muted leading-relaxed">
            "{{ selectedLog.description || 'Action completed by administrative account.' }}"
          </div>

          <!-- Payload -->
          <div class="space-y-2">
            <h4 class="text-[10px] uppercase font-black text-[#640c0e] tracking-wider">Transaction Data Values</h4>
            <pre class="p-4 bg-[#fffbf4]/20 border border-[#ebe8de] rounded-2xl overflow-x-auto text-[10px] font-mono text-neutral-black">
{{ JSON.stringify(selectedLog.payload || { target_id: selectedLog.target_id, target_type: selectedLog.target_type }, null, 2) }}
            </pre>
          </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-[#ebe8de] border-dashed pt-4 flex justify-end">
          <button 
            @click="selectedLog = null"
            class="rounded-xl h-10 px-6 font-bold text-xs bg-[#640c0e] text-white hover:bg-[#640c0e]/95 cursor-pointer shadow-premium"
          >
            Dismiss Ledger View
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
