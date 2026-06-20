<script setup lang="ts">
import { ref } from 'vue';
import { 
  Settings, 
  Palette, 
  Bell, 
  ShieldAlert, 
  Award, 
  Sliders, 
  Save, 
  RefreshCw, 
  Image as ImageIcon
} from 'lucide-vue-next';

// State variables
const activeTab = ref<"general" | "branding" | "notifications" | "moderation" | "certification" | "theme">("general");
const isLoading = ref(false);
const success = ref(false);

// Settings Configuration States
const generalConf = ref({
  siteName: "SFU MSA Dawah Academy",
  siteMotto: "Continuous Learning & Scholarly Outreach",
  sessionLength: "45",
  timezone: "America/Vancouver (PST)",
  contactEmail: "academy@sfumsa.org",
  maintenanceMode: false,
  publicRegistration: true,
});

const brandConf = ref({
  primaryColor: "#640c0e", // Maroon
  goldAccent: "#ffdc83", // Gold
  logoUrl: "https://images.unsplash.com/photo-1579621970563-ebec7560ff3e?w=80&auto=format&fit=crop",
  arabicCalligraphy: true,
  fontFamily: "Inter",
});

const notifConf = ref({
  emailAlerts: true,
  webhookUrl: "https://api.telegram.org/bot6821213...",
  frequency: "instant",
  criticalOnly: false,
  volunteerDailyDigest: true,
  mentorResponseNotif: true,
});

const modConf = ref({
  profanityFilter: true,
  replyLimit: "15",
  requireApproval: false,
  flaggedWords: "scam, hate, spam, unauthorized, pricing, query",
  notifyModeratorCount: "3",
  allowAnonSubmissions: false,
});

const certConf = ref({
  verificationLedger: true,
  approvalThreshold: "1500", // Min XP 
  signatureCoordsX: "240",
  signatureCoordsY: "480",
  mentorEndorsementRequired: true,
  serialPrefix: "SFU-SANAD-2026-",
});

const selectedTheme = ref("Royal Maroon");

const themeOptions = [
  { name: "Royal Maroon", primary: "bg-primary", secondary: "bg-accent-gold", desc: "The official academic layout styling with velvet colors." },
  { name: "Academic Ivory", primary: "bg-primary", secondary: "bg-neutral-ivory", desc: "Warm ivory panels with burgundy headers for administrative review." },
  { name: "Scholarly Contrast", primary: "bg-primary-dark", secondary: "bg-secondary", desc: "High-contrast burgundy and academic red for focused desk work." },
  { name: "Achievement Gold", primary: "bg-primary", secondary: "bg-accent-gold", desc: "Burgundy structure with gold accents for certificates and milestones." },
];

const tabs = [
  { id: "general", label: "General Settings", icon: Sliders, desc: "LMS identity & properties" },
  { id: "branding", label: "Branding Styles", icon: ImageIcon, desc: "Logos, calligraphies & colors" },
  { id: "notifications", label: "Notification Desk", icon: Bell, desc: "Emails, logs & Telegram bots" },
  { id: "moderation", label: "Dialogue Moderation", icon: ShieldAlert, desc: "Flag lists & comment filters" },
  { id: "certification", label: "Sanad Certificates", icon: Award, desc: "Verification ledger & coordinates" },
  { id: "theme", label: "Theme Schemes", icon: Palette, desc: "Set visual skin of your panel" },
];

const handleSave = () => {
  isLoading.value = true;
  success.value = false;
  setTimeout(() => {
    isLoading.value = false;
    success.value = true;
    setTimeout(() => {
      success.value = false;
    }, 4000);
  }, 900);
};
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
          <Settings class="h-6 w-6 text-[#ffdc83]" />
          Academy Workspace Settings
        </h2>
        <p class="text-sm text-neutral-muted font-sans">
          Manage global configurations, credential schemas, security triggers, and brand behaviors.
        </p>
      </div>
    </div>

    <!-- Success Banner -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="transform -translate-y-4 opacity-0"
      enter-to-class="transform translate-y-0 opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="success" 
        class="p-4 bg-secondary/10 border border-secondary/20 text-primary rounded-2xl flex items-center gap-3 text-sm shadow-sm"
      >
        <div class="h-6 w-6 rounded-full bg-secondary text-white flex items-center justify-center font-bold text-xs">✓</div>
        <div>
          <span class="font-bold">Configurations Committed!</span> Admin configurations have been saved successfully and propagated across all student viewports.
        </div>
      </div>
    </Transition>

    <!-- Outer Settings Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      
      <!-- Settings Sidebar -->
      <div class="lg:col-span-4 space-y-6">
        <div class="rounded-3xl border border-[#ebe8de] bg-white shadow-premium p-5 space-y-6">
          <div class="pb-4 border-b border-[#ebe8de] border-dashed">
            <h3 class="font-display font-black text-lg text-[#640c0e] tracking-tight">Configuration Hub</h3>
            <p class="text-xs text-neutral-muted mt-0.5">Equipped with real-time reactive save controls.</p>
          </div>

          <div class="space-y-1.5">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id as any"
              :class="[
                'w-full flex items-center gap-3.5 p-3 rounded-2xl text-left transition-all duration-200 cursor-pointer border border-transparent',
                activeTab === tab.id 
                  ? 'bg-[#640c0e] text-white shadow-premium' 
                  : 'hover:bg-[#fffbf4]/40 text-neutral-muted hover:text-[#640c0e] hover:border-[#ebe8de]/40'
              ]"
            >
              <div :class="[
                'p-2 rounded-xl shrink-0',
                activeTab === tab.id ? 'bg-white/10 text-[#ffdc83]' : 'bg-[#fffbf4] text-[#640c0e]'
              ]">
                <component :is="tab.icon" :size="18" />
              </div>
              <div class="min-w-0">
                <p :class="['text-xs font-bold', activeTab === tab.id ? 'text-white' : 'text-[#640c0e]']">{{ tab.label }}</p>
                <p :class="['text-[10px] truncate', activeTab === tab.id ? 'text-[#ffdc83]/85' : 'text-neutral-muted']">{{ tab.desc }}</p>
              </div>
            </button>
          </div>

          <div class="pt-6 border-t border-[#ebe8de] border-dashed text-center">
            <div class="flex items-center justify-between gap-4 text-left p-3.5 bg-[#ebe8de]/70 border border-[#ebe8de]/50 rounded-2xl">
              <div class="space-y-0.5">
                <p class="text-[9px] font-mono font-bold text-[#640c0e]/80">VERSION REGISTRY</p>
                <p class="text-xs text-neutral-muted font-semibold">Build v4.16 - Dev Preview</p>
              </div>
              <div class="h-2.5 w-2.5 rounded-full bg-secondary animate-pulse" />
            </div>
          </div>
        </div>
      </div>

      <!-- Settings Active View -->
      <div class="lg:col-span-8">
        <div class="rounded-3xl border border-[#ebe8de] bg-white shadow-premium overflow-hidden">
          <form @submit.prevent="handleSave">
            <div class="p-6 md:p-8 border-b border-[#ebe8de]/60 bg-[#ebe8de]/20 flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div>
                <h2 class="font-display font-black text-xl text-[#640c0e] tracking-tight capitalize flex items-center gap-2">
                  <Sliders :size="20" class="text-[#ffdc83]" />
                  {{ activeTab }} Preferences
                </h2>
                <p class="text-xs text-neutral-muted mt-0.5">
                  Change settings parameters below. Always press Save to write ledger.
                </p>
              </div>

              <button 
                type="submit" 
                :disabled="isLoading"
                class="h-10 px-5 rounded-xl bg-[#640c0e] text-white hover:bg-[#640c0e]/95 font-bold font-sans flex items-center gap-2 cursor-pointer shadow-premium disabled:opacity-50 transition-all shrink-0"
              >
                <RefreshCw v-if="isLoading" class="h-4 w-4 animate-spin" />
                <Save v-else class="h-4 w-4 text-[#ffdc83]" />
                {{ isLoading ? "Saving changes..." : "Save Settings" }}
              </button>
            </div>

            <div class="p-6 md:p-8 space-y-8 min-h-[350px]">
              <Transition
                mode="out-in"
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="transform translate-x-2 opacity-0"
                enter-to-class="transform translate-x-0 opacity-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
              >
                <div :key="activeTab" class="space-y-6">
                  
                  <!-- General Settings Form -->
                  <div v-if="activeTab === 'general'" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div class="space-y-2">
                        <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">LMS Platform/Site Name</label>
                        <input
                          type="text"
                          v-model="generalConf.siteName"
                          class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl p-3 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-[#640c0e]/20"
                        />
                      </div>

                      <div class="space-y-2">
                        <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">Academy Motto & Subtitle</label>
                        <input
                          type="text"
                          v-model="generalConf.siteMotto"
                          class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl p-3 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-[#640c0e]/20"
                        />
                      </div>

                      <div class="space-y-2">
                        <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">Ideal Session duration (Mins)</label>
                        <input
                          type="number"
                          v-model="generalConf.sessionLength"
                          class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl p-3 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-[#640c0e]/20"
                        />
                      </div>

                      <div class="space-y-2">
                        <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">Ledger Timezone Registry</label>
                        <select
                          v-model="generalConf.timezone"
                          class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl p-3 text-xs font-semibold focus:outline-none"
                        >
                          <option>America/Vancouver (PST)</option>
                          <option>Europe/London (GMT)</option>
                          <option>Asia/Riyadh (AST)</option>
                          <option>America/New_York (EST)</option>
                        </select>
                      </div>
                    </div>

                    <div class="space-y-2">
                      <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">Primary Administration Email Contact</label>
                      <input
                        type="email"
                        v-model="generalConf.contactEmail"
                        class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl p-3 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-[#640c0e]/20"
                      />
                    </div>

                    <!-- Toggles -->
                    <div class="pt-4 border-t border-[#ebe8de]/60 space-y-4">
                      <div class="flex items-center justify-between p-4 bg-[#fffbf4]/20 border border-[#ebe8de]/60 rounded-2xl">
                        <div class="space-y-0.5">
                          <h4 class="text-xs font-bold text-[#640c0e]">Enable Emergency Maintenance Mode</h4>
                          <p class="text-[10px] text-neutral-muted max-w-sm">Suspends all interactive quiz testing and public pages with an elegant notice page.</p>
                        </div>
                        <input 
                          type="checkbox" 
                          v-model="generalConf.maintenanceMode" 
                          class="h-5 w-5 rounded text-[#640c0e] border-[#ebe8de] focus:ring-[#640c0e]/20 cursor-pointer" 
                        />
                      </div>

                      <div class="flex items-center justify-between p-4 bg-[#fffbf4]/20 border border-[#ebe8de]/60 rounded-2xl">
                        <div class="space-y-0.5">
                          <h4 class="text-xs font-bold text-[#640c0e]">Allow Public Registration</h4>
                          <p class="text-[10px] text-neutral-muted max-w-sm">Allows guest visitors to register accounts organically without invite tokens.</p>
                        </div>
                        <input 
                          type="checkbox" 
                          v-model="generalConf.publicRegistration" 
                          class="h-5 w-5 rounded text-[#640c0e] border-[#ebe8de] focus:ring-[#640c0e]/20 cursor-pointer" 
                        />
                      </div>
                    </div>
                  </div>

                  <!-- Branding Settings Form -->
                  <div v-if="activeTab === 'branding'" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div class="space-y-2">
                        <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">Primary Accent Color</label>
                        <div class="flex gap-2">
                          <input
                            type="color"
                            v-model="brandConf.primaryColor"
                            class="h-10 w-14 shrink-0 rounded-xl border border-[#ebe8de] p-1 cursor-pointer bg-white"
                          />
                          <input
                            type="text"
                            v-model="brandConf.primaryColor"
                            class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl px-3 text-xs font-semibold focus:outline-none"
                          />
                        </div>
                      </div>

                      <div class="space-y-2">
                        <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">Secondary Gold Color</label>
                        <div class="flex gap-2">
                          <input
                            type="color"
                            v-model="brandConf.goldAccent"
                            class="h-10 w-14 shrink-0 rounded-xl border border-[#ebe8de] p-1 cursor-pointer bg-white"
                          />
                          <input
                            type="text"
                            v-model="brandConf.goldAccent"
                            class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl px-3 text-xs font-semibold focus:outline-none"
                          />
                        </div>
                      </div>
                    </div>

                    <div class="space-y-2">
                      <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">Academy Brand Logo URL</label>
                      <div class="flex items-center gap-4">
                        <img 
                          :src="brandConf.logoUrl" 
                          class="w-12 h-12 rounded-xl border border-[#ebe8de] object-cover" 
                          alt="Logo preview"
                        />
                        <input
                          type="text"
                          v-model="brandConf.logoUrl"
                          class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl p-3 text-xs font-semibold focus:outline-none"
                        />
                      </div>
                    </div>

                    <div class="pt-4 border-t border-[#ebe8de]/60 space-y-4">
                      <div class="flex items-center justify-between p-4 bg-[#fffbf4]/20 border border-[#ebe8de]/60 rounded-2xl">
                        <div class="space-y-0.5">
                          <h4 class="text-xs font-bold text-[#640c0e]">Enable Calligraphy and Arabesque Borders</h4>
                          <p class="text-[10px] text-neutral-muted max-w-sm">Attaches beautiful custom Islamic designs to the corners of course cards and certificate previews.</p>
                        </div>
                        <input 
                          type="checkbox" 
                          v-model="brandConf.arabicCalligraphy" 
                          class="h-5 w-5 rounded text-[#640c0e] border-[#ebe8de] focus:ring-[#640c0e]/20 cursor-pointer" 
                        />
                      </div>
                    </div>
                  </div>

                  <!-- Notification Settings Form -->
                  <div v-if="activeTab === 'notifications'" class="space-y-6">
                    <div class="space-y-2">
                      <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">Telegram Webhook Alert URL</label>
                      <p class="text-[10px] text-neutral-muted">Posts system events (new registrations, flags, and certification sign-offs) directly to a Telegram channel or custom bot.</p>
                      <input
                        type="text"
                        v-model="notifConf.webhookUrl"
                        class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl p-3 text-xs font-mono focus:outline-none"
                      />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-[#ebe8de]/60">
                      <div class="flex items-center justify-between p-4 bg-[#fffbf4]/20 border border-[#ebe8de]/60 rounded-2xl">
                        <div class="space-y-0.5">
                          <h4 class="text-xs font-bold text-[#640c0e]">Push Outbound Emails</h4>
                          <p class="text-[10px] text-neutral-muted max-w-xs">Global toggle for sending classroom alerts via SMTP.</p>
                        </div>
                        <input 
                          type="checkbox" 
                          v-model="notifConf.emailAlerts" 
                          class="h-5 w-5 rounded text-[#640c0e] border-[#ebe8de] focus:ring-[#640c0e]/20 cursor-pointer" 
                        />
                      </div>

                      <div class="flex items-center justify-between p-4 bg-[#fffbf4]/20 border border-[#ebe8de]/60 rounded-2xl">
                        <div class="space-y-0.5">
                          <h4 class="text-xs font-bold text-[#640c0e]">Volunteer Daily Digest</h4>
                          <p class="text-[10px] text-neutral-muted max-w-xs">Bundles study plans and active streaks into single daily summaries.</p>
                        </div>
                        <input 
                          type="checkbox" 
                          v-model="notifConf.volunteerDailyDigest" 
                          class="h-5 w-5 rounded text-[#640c0e] border-[#ebe8de] focus:ring-[#640c0e]/20 cursor-pointer" 
                        />
                      </div>
                    </div>
                  </div>

                  <!-- Moderation Settings Form -->
                  <div v-if="activeTab === 'moderation'" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div class="flex items-center justify-between p-4 bg-[#fffbf4]/20 border border-[#ebe8de]/60 rounded-2xl">
                        <div class="space-y-0.5">
                          <h4 class="text-xs font-bold text-[#640c0e]">Automated Profanity Filters</h4>
                          <p class="text-[10px] text-neutral-muted max-w-xs">Instantly mask or flag dialog entries containing vulgarities.</p>
                        </div>
                        <input 
                          type="checkbox" 
                          v-model="modConf.profanityFilter" 
                          class="h-5 w-5 rounded text-[#640c0e] border-[#ebe8de] focus:ring-[#640c0e]/20 cursor-pointer" 
                        />
                      </div>

                      <div class="space-y-2">
                        <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">Max Dialogue Replies per student/day</label>
                        <input
                          type="number"
                          v-model="modConf.replyLimit"
                          class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl p-3 text-xs font-semibold focus:outline-none"
                        />
                      </div>
                    </div>

                    <div class="space-y-2 pt-4 border-t border-[#ebe8de]/60">
                      <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">Banned Terms & Slogans Dictionary</label>
                      <p class="text-[10px] text-neutral-muted mb-2">Separate words by commas. Comments matching these will be directed into administrative review immediately.</p>
                      <textarea
                        rows="3"
                        v-model="modConf.flaggedWords"
                        class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl p-3.5 text-xs font-mono focus:ring-1 focus:ring-[#640c0e]/20"
                      />
                    </div>

                    <div class="flex items-center justify-between p-4 bg-[#fffbf4]/20 border border-[#ebe8de]/60 rounded-2xl">
                      <div class="space-y-0.5">
                        <h4 class="text-xs font-bold text-[#640c0e]">Require manual post-approval</h4>
                        <p class="text-[10px] text-neutral-muted max-w-sm">Replies remain invisible until verified by a forum supervisor.</p>
                      </div>
                      <input 
                        type="checkbox" 
                        v-model="modConf.requireApproval" 
                        class="h-5 w-5 rounded text-[#640c0e] border-[#ebe8de] focus:ring-[#640c0e]/20 cursor-pointer" 
                      />
                    </div>
                  </div>

                  <!-- Certification Settings Form -->
                  <div v-if="activeTab === 'certification'" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div class="space-y-2">
                        <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">Sanad Approval Min XP Threshold</label>
                        <input
                          type="number"
                          v-model="certConf.approvalThreshold"
                          class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl p-3 text-xs font-semibold focus:outline-none"
                        />
                      </div>

                      <div class="space-y-2">
                        <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">Serial Certificate Prefix Stamp</label>
                        <input
                          type="text"
                          v-model="certConf.serialPrefix"
                          class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl p-3 text-xs font-semibold focus:outline-none"
                        />
                      </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-[#ebe8de]/60">
                      <div class="flex items-center justify-between p-4 bg-[#fffbf4]/20 border border-[#ebe8de]/60 rounded-2xl">
                        <div class="space-y-0.5">
                          <h4 class="text-xs font-bold text-[#640c0e]">Sanad Verification Ledger Enabled</h4>
                          <p class="text-[10px] text-neutral-muted max-w-xs">Allows masjids and third-parties to query signatures online.</p>
                        </div>
                        <input 
                          type="checkbox" 
                          v-model="certConf.verificationLedger" 
                          class="h-5 w-5 rounded text-[#640c0e] border-[#ebe8de] focus:ring-[#640c0e]/20 cursor-pointer" 
                        />
                      </div>

                      <div class="flex items-center justify-between p-4 bg-[#fffbf4]/20 border border-[#ebe8de]/60 rounded-2xl">
                        <div class="space-y-0.5">
                          <h4 class="text-xs font-bold text-[#640c0e]">Mentor Endorsements compulsory</h4>
                          <p class="text-[10px] text-neutral-muted max-w-xs">Requires signature from at least one guild supervisor prior to ledger print.</p>
                        </div>
                        <input 
                          type="checkbox" 
                          v-model="certConf.mentorEndorsementRequired" 
                          class="h-5 w-5 rounded text-[#640c0e] border-[#ebe8de] focus:ring-[#640c0e]/20 cursor-pointer" 
                        />
                      </div>
                    </div>

                    <div class="space-y-2 pt-4 border-t border-[#ebe8de]/60">
                      <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">Authorized Signature Position Coordinates (SVG px)</label>
                      <div class="grid grid-cols-2 gap-4">
                        <div>
                          <span class="text-[10px] text-neutral-muted block mb-1">X Coordinates</span>
                          <input
                            type="number"
                            v-model="certConf.signatureCoordsX"
                            class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl p-3 text-xs focus:outline-none"
                          />
                        </div>
                        <div>
                          <span class="text-[10px] text-neutral-muted block mb-1">Y Coordinates</span>
                          <input
                            type="number"
                            v-model="certConf.signatureCoordsY"
                            class="w-full bg-[#ebe8de]/40 text-[#640c0e] border border-[#ebe8de] rounded-xl p-3 text-xs focus:outline-none"
                          />
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Theme Settings Form -->
                  <div v-if="activeTab === 'theme'" class="space-y-6">
                    <label class="text-[9px] uppercase font-bold tracking-widest text-neutral-muted block">Choose Active Workspace Theme Skin</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div
                        v-for="opt in themeOptions"
                        :key="opt.name"
                        @click="selectedTheme = opt.name"
                        :class="[
                          'rounded-2xl border p-4 flex gap-4 transition-all duration-300 cursor-pointer text-left select-none',
                          selectedTheme === opt.name 
                            ? 'border-[#640c0e] bg-[#fffbf4]/30 ring-2 ring-[#640c0e]/10' 
                            : 'border-[#ebe8de] bg-white hover:bg-[#ebe8de]/30'
                        ]"
                      >
                        <div class="flex flex-col gap-1.5 items-center justify-center shrink-0">
                          <div class="flex gap-1 h-8 w-14 rounded-lg bg-gray-100 border border-gray-200 overflow-hidden relative">
                            <div :class="['w-1/2 h-full', opt.primary]" />
                            <div :class="['w-1/2 h-full', opt.secondary]" />
                          </div>
                          <span v-if="selectedTheme === opt.name" class="text-[8px] font-mono font-bold text-[#640c0e] bg-white border border-[#640c0e] px-1 rounded uppercase">Active</span>
                        </div>

                        <div>
                          <h4 class="text-xs font-bold text-[#640c0e]">{{ opt.name }}</h4>
                          <p class="text-[10px] text-neutral-muted leading-normal mt-1">{{ opt.desc }}</p>
                        </div>
                      </div>
                    </div>
                  </div>

                </div>
              </Transition>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
</template>
