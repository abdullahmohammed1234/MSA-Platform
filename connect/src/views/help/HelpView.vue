<template>
  <div class="space-y-6">
    <!-- Page Hero -->
    <PageHero
      label="Campus Assistance"
      title="Help & Frequently Asked Questions"
      description="Platform troubleshooting, single sign-on security, event wallet access, and campus emergency contacts."
    >
      <template #actions>
        <router-link to="/app/support/new">
          <Button variant="primary" size="sm" class="shadow-2xs">
            <template #left-icon>
              <MessageSquarePlus class="w-3.5 h-3.5" />
            </template>
            <span>Open Support Ticket</span>
          </Button>
        </router-link>

        <router-link to="/app/support">
          <Button variant="outline" size="sm" class="bg-white hover:bg-[#fffbf4] border-[#ebe8de] text-xs font-semibold shadow-2xs">
            <template #left-icon>
              <Ticket class="w-3.5 h-3.5 text-[#b02e32]" />
            </template>
            <span>My Inquiries</span>
          </Button>
        </router-link>
      </template>
    </PageHero>

    <!-- Route-Based Tabs -->
    <RouteTabs :tabs="navTabs" aria-label="Resources and Help Navigation" />

    <!-- Help Topic Filter Toolbar (Non-scrolling topic filter, replaces prohibited anchor jump links) -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none text-xs">
      <button
        type="button"
        class="px-3.5 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer whitespace-nowrap"
        :class="selectedTopic === '' ? 'bg-[#640c0e] text-white shadow-xs' : 'bg-white border-[#ebe8de] text-neutral-700 hover:text-[#640c0e] hover:bg-[#fffbf4]'"
        @click="selectedTopic = ''"
      >
        All Help Topics
      </button>

      <button
        v-for="section in helpSections"
        :key="section.id"
        type="button"
        class="px-3.5 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer whitespace-nowrap flex items-center gap-1.5"
        :class="selectedTopic === section.id ? 'bg-[#640c0e] text-white shadow-xs' : 'bg-white border-[#ebe8de] text-neutral-700 hover:text-[#640c0e] hover:bg-[#fffbf4]'"
        @click="selectedTopic = section.id"
      >
        <component :is="section.icon" class="w-3.5 h-3.5" :class="selectedTopic === section.id ? 'text-[#ffdc83]' : 'text-[#b02e32]'" />
        <span>{{ section.title }}</span>
      </button>
    </div>

    <!-- 1. Authentication & Session Expiry -->
    <section id="auth-sessions" v-show="!selectedTopic || selectedTopic === 'auth-sessions'">
      <Card
        title="1. Signing In & Session Security"
        subtitle="Understanding authentication, single sign-on sessions, and automatic session renewals."
      >
        <div class="space-y-4 text-xs text-neutral-700">
          <p class="leading-relaxed">
            SFU MSA Connect uses central authentication powered by Laravel Sanctum. Your account credentials and personal access tokens are shared securely across the platform:
          </p>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
            <div class="p-4 rounded-xl bg-[#fffbf4] border border-[#ebe8de] space-y-1.5">
              <div class="flex items-center gap-2 font-bold text-[#640c0e]">
                <KeyRound class="w-4 h-4 text-[#b02e32]" />
                <span>Single Sign-On (SSO)</span>
              </div>
              <p class="text-neutral-600 leading-relaxed">
                Signing in on sfumsa.ca automatically authorizes MSA Connect on the same browser through shared session cookies, without requiring multiple passwords.
              </p>
            </div>

            <div class="p-4 rounded-xl bg-[#fffbf4] border border-[#ebe8de] space-y-1.5">
              <div class="flex items-center gap-2 font-bold text-[#640c0e]">
                <Clock class="w-4 h-4 text-[#b02e32]" />
                <span>Session Expiration & Renewal</span>
              </div>
              <p class="text-neutral-600 leading-relaxed">
                For security on shared campus workstations, tokens expire periodically. When expired, Connect safely saves your intended destination and prompts you to sign in again.
              </p>
            </div>
          </div>

          <div class="p-3.5 rounded-xl border border-neutral-200 bg-neutral-50 flex items-start gap-2.5 text-neutral-600">
            <Info class="w-4 h-4 text-[#640c0e] shrink-0 mt-0.5" />
            <p class="leading-relaxed">
              <strong>Need a password reset?</strong> You can request a reset link on the <router-link to="/login" class="text-[#b02e32] font-semibold underline">Sign In screen</router-link> or visit the <a href="https://sfumsa.ca/account" target="_blank" rel="noopener noreferrer" class="text-[#b02e32] font-semibold underline">Central Account Hub</a>. Instructions are sent strictly to your registered SFU student email.
            </p>
          </div>
        </div>
      </Card>
    </section>

    <!-- 2. Profile Management & Password Updates -->
    <section id="profile-management" v-show="!selectedTopic || selectedTopic === 'profile-management'">
      <Card
        title="2. Managing Your Profile & Password"
        subtitle="How to edit personal information and change your account password."
      >
        <div class="space-y-4 text-xs text-neutral-700">
          <div class="space-y-2">
            <h4 class="font-bold text-neutral-900">Member-Editable Attributes vs. Central Attributes</h4>
            <p class="leading-relaxed text-neutral-600">
              In Connect, you can update your <strong>Full Name</strong> and <strong>Email Address</strong> directly from the <router-link to="/app/profile" class="text-[#b02e32] font-semibold underline">Profile & Preferences</router-link> page. You can also update your account password directly inside the Security & Credentials section.
            </p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="p-3.5 rounded-xl border border-[#ebe8de] bg-[#fffbf4]">
              <span class="text-[10px] font-bold uppercase text-neutral-500 block">Editable in Connect</span>
              <ul class="mt-1 space-y-0.5 text-neutral-800 font-medium">
                <li>• Full Display Name</li>
                <li>• Student Email Address</li>
                <li>• Account Password</li>
                <li>• Communication Channels</li>
              </ul>
            </div>

            <div class="p-3.5 rounded-xl border border-[#ebe8de] bg-[#fffbf4]">
              <span class="text-[10px] font-bold uppercase text-neutral-500 block">Central / System-Managed</span>
              <ul class="mt-1 space-y-0.5 text-neutral-800 font-medium">
                <li>• Assigned Roles & Permissions</li>
                <li>• Platform UUID</li>
                <li>• Email Verification Status</li>
                <li>• Account Activation State</li>
              </ul>
            </div>

            <div class="p-3.5 rounded-xl border border-[#ebe8de] bg-[#fffbf4]">
              <span class="text-[10px] font-bold uppercase text-neutral-500 block">Actions Available</span>
              <div class="mt-2">
                <router-link to="/app/profile">
                  <Button variant="outline" size="sm" block class="text-xs">
                    <span>Open Profile Page</span>
                  </Button>
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </Card>
    </section>

    <!-- 3. Web Push Notifications Troubleshooting -->
    <section id="notifications-troubleshooting" v-show="!selectedTopic || selectedTopic === 'notifications-troubleshooting'">
      <Card
        title="3. Web Push Notifications Troubleshooting"
        subtitle="Resolving blocked browser permissions, registering devices, and testing delivery."
      >
        <div class="space-y-4 text-xs text-neutral-700">
          <p class="leading-relaxed">
            SFU MSA Connect provides synchronous, end-to-end encrypted Web Push alerts for direct messages, support replies, and campus announcements. If notifications are not appearing, check these common causes:
          </p>

          <div class="space-y-3">
            <!-- Step 1: Permission Denied -->
            <div class="p-4 rounded-xl border border-red-200 bg-red-50/50 space-y-2">
              <div class="flex items-center gap-2 font-bold text-red-900">
                <AlertTriangle class="w-4 h-4 text-red-700" />
                <span>Issue: Browser Site Permission is Blocked</span>
              </div>
              <p class="text-red-800 leading-relaxed text-[11px]">
                If your browser set Notifications to "Block", the browser will never prompt you again. To unblock:
              </p>
              <ul class="list-disc list-inside space-y-1 text-[11px] text-red-800/90 pl-1">
                <li><strong>Chrome / Edge / Firefox (Desktop):</strong> Click the icon next to the URL (padlock/tune) → Permissions → set Notifications to <strong>Allow</strong> → reload page.</li>
                <li><strong>Safari (macOS):</strong> Safari Settings → Websites → Notifications → find connect.sfumsa.ca and choose <strong>Allow</strong>.</li>
                <li><strong>Android Chrome:</strong> Tap ⋮ → Settings → Site settings → Notifications → set connect.sfumsa.ca to <strong>Allow</strong>.</li>
              </ul>
            </div>

            <!-- Step 2: Apple iOS PWA Requirement -->
            <div class="p-4 rounded-xl border border-amber-200 bg-amber-50/50 space-y-2">
              <div class="flex items-center gap-2 font-bold text-amber-900">
                <Smartphone class="w-4 h-4 text-amber-700" />
                <span>iPhone & iPad (iOS 16.4+) Requirement</span>
              </div>
              <p class="text-amber-800 leading-relaxed text-[11px]">
                Apple requires Progressive Web Apps to be added to your Home Screen before Web Push notifications can be granted. Open Safari, tap the <strong>Share</strong> button, tap <strong>Add to Home Screen</strong>, launch Connect from the Home Screen, and tap "Enable Push".
              </p>
            </div>

            <!-- Step 3: Do Not Disturb & Focus Modes -->
            <div class="p-4 rounded-xl border border-[#ebe8de] bg-[#fffbf4] space-y-2">
              <div class="flex items-center gap-2 font-bold text-[#640c0e]">
                <BellOff class="w-4 h-4 text-[#b02e32]" />
                <span>Operating System Do Not Disturb / Focus Modes</span>
              </div>
              <p class="text-neutral-600 leading-relaxed text-[11px]">
                When your device is in "Do Not Disturb", "Focus", or "Bedtime" mode, physical alert banners and chimes are silenced by the operating system until the mode ends.
              </p>
            </div>
          </div>

          <div class="pt-2 flex items-center justify-between">
            <span class="text-[11px] text-neutral-500">
              Manage your active devices and send test push alerts anytime.
            </span>
            <router-link to="/app/notifications/preferences">
              <Button variant="outline" size="sm">
                <template #left-icon>
                  <Bell class="w-3.5 h-3.5" />
                </template>
                <span>Push Device Settings</span>
              </Button>
            </router-link>
          </div>
        </div>
      </Card>
    </section>

    <!-- 4. PWA Installation Guide -->
    <section id="pwa-install" v-show="!selectedTopic || selectedTopic === 'pwa-install'">
      <Card
        title="4. Installing Connect as a Progressive Web App (PWA)"
        subtitle="Enjoy instant launching, home screen access, and native window presentation."
      >
        <div class="space-y-4 text-xs text-neutral-700">
          <p class="leading-relaxed">
            SFU MSA Connect is built as a standards-compliant Progressive Web App. You do not need an app store to install it.
          </p>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
            <!-- iOS Guide -->
            <div class="p-4 rounded-xl bg-[#fffbf4] border border-[#ebe8de] space-y-2">
              <div class="flex items-center gap-2 font-bold text-[#640c0e]">
                <Share2 class="w-4 h-4 text-[#b02e32]" />
                <span>Apple iOS / iPadOS Installation</span>
              </div>
              <ol class="list-decimal list-inside space-y-1.5 text-neutral-600 text-[11px]">
                <li>Open <strong>Safari</strong> on your iPhone or iPad.</li>
                <li>Navigate to <span class="font-mono text-neutral-700">connect.sfumsa.ca</span>.</li>
                <li>Tap the <strong>Share</strong> button at the bottom of Safari.</li>
                <li>Scroll down and tap <strong>Add to Home Screen</strong>.</li>
                <li>Tap <strong>Add</strong> in the top-right corner.</li>
              </ol>
            </div>

            <!-- Android / Desktop Guide -->
            <div class="p-4 rounded-xl bg-[#fffbf4] border border-[#ebe8de] space-y-2">
              <div class="flex items-center gap-2 font-bold text-[#640c0e]">
                <Download class="w-4 h-4 text-[#b02e32]" />
                <span>Android & Desktop (Chrome / Edge)</span>
              </div>
              <ol class="list-decimal list-inside space-y-1.5 text-neutral-600 text-[11px]">
                <li>Look for the <strong>Install</strong> prompt at the bottom of the screen.</li>
                <li>Or tap the install icon in your browser address bar.</li>
                <li>Click <strong>Install SFU MSA Connect</strong>.</li>
                <li>Launch the app directly from your desktop dock or app drawer.</li>
              </ol>
            </div>
          </div>
        </div>
      </Card>
    </section>

    <!-- 5. Network Connectivity & Privacy -->
    <section id="network-connectivity" v-show="!selectedTopic || selectedTopic === 'network-connectivity'">
      <Card
        title="5. Network Connectivity & Data Privacy"
        subtitle="How Connect communicates with the SFU MSA Platform API."
      >
        <div class="space-y-3.5 text-xs text-neutral-700">
          <div class="p-4 rounded-xl border border-blue-200 bg-blue-50/50 space-y-1.5 text-blue-950">
            <div class="flex items-center gap-2 font-bold text-blue-900">
              <Wifi class="w-4 h-4 text-blue-700" />
              <span>Online Platform Requirement</span>
            </div>
            <p class="leading-relaxed text-[11px] text-blue-900/90">
              SFU MSA Connect strictly communicates with the live platform API at api.sfumsa.ca. In order to protect member privacy, event ticket validity, and message synchronization, offline data persistence is explicitly not enabled. An active Wi-Fi or cellular data connection is required to fetch passes and messages.
            </p>
          </div>

          <div class="space-y-1 text-neutral-600">
            <h4 class="font-bold text-neutral-900">What to do if you encounter a connection error:</h4>
            <ul class="list-disc list-inside space-y-1 text-[11px] pl-1">
              <li>Check your Wi-Fi or mobile data signal (e.g. SFU-WIFI eduroam network status).</li>
              <li>Use the in-app <strong>Retry</strong> button on the error banner to reconnect.</li>
              <li>Refresh the page or relaunch the PWA window.</li>
            </ul>
          </div>
        </div>
      </Card>
    </section>

    <!-- 6. Contacting MSA Coordinators -->
    <section id="contact-support" v-show="!selectedTopic || selectedTopic === 'contact-support'">
      <Card
        title="6. Contact & Support Channels"
        subtitle="Get in touch with SFU Muslim Students Association directors and coordinators."
      >
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 text-xs">
          <!-- In-App Tickets -->
          <div class="p-4 rounded-xl bg-[#fffbf4] border border-[#ebe8de] space-y-2">
            <div class="flex items-center gap-2 font-bold text-[#640c0e]">
              <MessageSquareText class="w-4 h-4 text-[#b02e32]" />
              <span>In-App Support Ticket</span>
            </div>
            <p class="text-neutral-600 leading-relaxed text-[11px]">
              Submit a private support inquiry with category tracking, file notes, and coordinator replies.
            </p>
            <router-link to="/app/support/new" class="inline-block pt-1">
              <Button variant="primary" size="sm">
                <span>New Ticket</span>
              </Button>
            </router-link>
          </div>

          <!-- Direct Messages -->
          <div class="p-4 rounded-xl bg-[#fffbf4] border border-[#ebe8de] space-y-2">
            <div class="flex items-center gap-2 font-bold text-[#640c0e]">
              <MessageSquare class="w-4 h-4 text-[#b02e32]" />
              <span>Direct Messaging</span>
            </div>
            <p class="text-neutral-600 leading-relaxed text-[11px]">
              Chat directly with verified MSA executives, coordinators, or peer mentors in active threads.
            </p>
            <router-link to="/app/messages" class="inline-block pt-1">
              <Button variant="outline" size="sm">
                <span>View Messages</span>
              </Button>
            </router-link>
          </div>

          <!-- Email Support -->
          <div class="p-4 rounded-xl bg-[#fffbf4] border border-[#ebe8de] space-y-2">
            <div class="flex items-center gap-2 font-bold text-[#640c0e]">
              <Mail class="w-4 h-4 text-[#b02e32]" />
              <span>General Email</span>
            </div>
            <p class="text-neutral-600 leading-relaxed text-[11px]">
              For urgent campus concerns, event sponsorships, or inquiries from non-student visitors.
            </p>
            <a href="mailto:sfumsa@hotmail.com" class="inline-block pt-1">
              <Button variant="outline" size="sm">
                <span>Email Coordinators</span>
                <template #right-icon>
                  <ExternalLink class="w-3 h-3" />
                </template>
              </Button>
            </a>
          </div>
        </div>
      </Card>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import PageHero from '@/components/ui/PageHero.vue';
import RouteTabs, { type RouteTabItem } from '@/components/ui/RouteTabs.vue';
import Card from '@/components/ui/Card.vue';
import Button from '@/components/ui/Button.vue';
import {
  KeyRound,
  Clock,
  User,
  Bell,
  BellOff,
  Smartphone,
  Download,
  Share2,
  Wifi,
  Mail,
  MessageSquareText,
  MessageSquare,
  MessageSquarePlus,
  Ticket,
  AlertTriangle,
  Info,
  ExternalLink,
  BookOpen,
  HelpCircle,
} from 'lucide-vue-next';

interface HelpSectionItem {
  id: string;
  title: string;
  icon: any;
}

const selectedTopic = ref<string>('');

const navTabs = computed<RouteTabItem[]>(() => [
  {
    to: '/app/resources',
    label: 'Community Guides',
    icon: BookOpen,
  },
  {
    to: '/app/help',
    label: 'Help & FAQ',
    icon: HelpCircle,
  },
]);

const helpSections: HelpSectionItem[] = [
  { id: 'auth-sessions', title: 'Sign In & Sessions', icon: KeyRound },
  { id: 'profile-management', title: 'Profile & Password', icon: User },
  { id: 'notifications-troubleshooting', title: 'Push Notifications', icon: Bell },
  { id: 'pwa-install', title: 'PWA Installation', icon: Download },
  { id: 'network-connectivity', title: 'Connectivity', icon: Wifi },
  { id: 'contact-support', title: 'Contact MSA', icon: MessageSquareText },
];
</script>
