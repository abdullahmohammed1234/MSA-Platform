<template>
  <div class="space-y-6">
    <!-- Page Hero -->
    <PageHero
      label="Delivery Settings"
      title="Notification Preferences"
      description="Configure Web Push notifications on your devices, select delivery channels, and manage topic alerts."
    >
      <template #badge>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-stone-100 border border-stone-200 text-stone-700 text-[10.5px] font-semibold">
          <Settings class="w-3.5 h-3.5 text-stone-500" />
          <span>Device Sync Active</span>
        </span>
      </template>

      <template #actions>
        <Button
          variant="outline"
          size="sm"
          :is-loading="notificationsStore.isLoadingPreferences"
          @click="handleRefresh"
          title="Refresh notification preferences"
          class="bg-white hover:bg-[#fffbf4] border-[#ebe8de] text-xs font-semibold shadow-2xs"
        >
          <template #left-icon>
            <RefreshCw
              class="w-3.5 h-3.5 text-[#b02e32]"
              :class="{ 'animate-spin': notificationsStore.isLoadingPreferences }"
            />
          </template>
          <span>Refresh</span>
        </Button>
      </template>
    </PageHero>

    <!-- Route-Based Tabs -->
    <RouteTabs :tabs="navTabs" aria-label="Notifications Navigation" />

    <!-- TAB CONTENT: Dedicated Notification Preferences -->
    <div class="space-y-6">
      <!-- Loading State -->
      <div v-if="notificationsStore.isLoadingPreferences && !notificationsStore.preferences" class="py-16 flex flex-col items-center justify-center space-y-3">
        <Spinner size="lg" />
        <p class="text-xs text-neutral-500 font-medium">Loading notification preferences...</p>
      </div>

      <!-- Preferences Form -->
      <div v-else-if="notificationsStore.preferences" class="space-y-6">
        <!-- Status / Feedback Banner -->
        <div
          v-if="preferenceFeedback"
          class="p-3.5 rounded-xl border text-xs font-medium flex items-center gap-2.5 transition-all"
          :class="preferenceFeedback.type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800'"
        >
          <component
            :is="preferenceFeedback.type === 'success' ? Check : AlertCircle"
            class="w-4 h-4 shrink-0"
          />
          <span>{{ preferenceFeedback.message }}</span>
        </div>

        <!-- Section 1: Delivery Channels -->
        <section class="community-card p-5 sm:p-6 bg-white border border-[#ebe8de] space-y-4">
          <div class="border-b border-[#ebe8de]/60 pb-3">
            <h2 class="text-base font-bold text-[#640c0e]">
              Delivery Channels
            </h2>
            <p class="text-xs text-neutral-500 mt-0.5">
              Control how SFU MSA delivers notifications to your account and devices.
            </p>
          </div>

          <div class="space-y-4 pt-1">
            <!-- In-App Alerts -->
            <div class="flex items-start justify-between gap-4 py-2 border-b border-[#ebe8de]/40">
              <div class="space-y-0.5">
                <label class="text-xs font-bold text-neutral-800 cursor-pointer" for="pref-in-app">
                  In-App Notification Center
                </label>
                <p class="text-xs text-neutral-500">
                  Receive badges, unread banners, and alerts inside MSA Connect and member portals.
                </p>
              </div>
              <button
                id="pref-in-app"
                type="button"
                role="switch"
                :aria-checked="notificationsStore.preferences.in_app_enabled"
                :disabled="notificationsStore.isSavingPreferences"
                @click="togglePreference('in_app_enabled', !notificationsStore.preferences.in_app_enabled)"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#640c0e]/30 disabled:opacity-50"
                :class="notificationsStore.preferences.in_app_enabled ? 'bg-[#640c0e]' : 'bg-neutral-300'"
              >
                <span
                  class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                  :class="notificationsStore.preferences.in_app_enabled ? 'translate-x-5' : 'translate-x-0'"
                />
              </button>
            </div>

            <!-- Email Alerts -->
            <div class="flex items-start justify-between gap-4 py-2">
              <div class="space-y-0.5">
                <label class="text-xs font-bold text-neutral-800 cursor-pointer" for="pref-email">
                  Email Notifications
                </label>
                <p class="text-xs text-neutral-500">
                  Receive event registrations, tickets, and direct message alerts. Direct message emails are throttled to at most once per 5 minutes per conversation when not recently active.
                </p>
              </div>
              <button
                id="pref-email"
                type="button"
                role="switch"
                :aria-checked="notificationsStore.preferences.email_enabled"
                :disabled="notificationsStore.isSavingPreferences"
                @click="togglePreference('email_enabled', !notificationsStore.preferences.email_enabled)"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#640c0e]/30 disabled:opacity-50"
                :class="notificationsStore.preferences.email_enabled ? 'bg-[#640c0e]' : 'bg-neutral-300'"
              >
                <span
                  class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                  :class="notificationsStore.preferences.email_enabled ? 'translate-x-5' : 'translate-x-0'"
                />
              </button>
            </div>

            <!-- Web Push Alerts -->
            <div class="flex items-start justify-between gap-4 py-2 border-t border-[#ebe8de]/40">
              <div class="space-y-0.5">
                <label class="text-xs font-bold text-neutral-800 cursor-pointer" for="pref-push">
                  Device Push Notifications
                </label>
                <p class="text-xs text-neutral-500">
                  Receive instant, synchronous push alerts on your phone or desktop browser when events, replies, or direct messages arrive.
                </p>
              </div>
              <button
                id="pref-push"
                type="button"
                role="switch"
                :aria-checked="notificationsStore.preferences.push_enabled"
                :disabled="notificationsStore.isSavingPreferences"
                @click="togglePreference('push_enabled', !notificationsStore.preferences.push_enabled)"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#640c0e]/30 disabled:opacity-50"
                :class="notificationsStore.preferences.push_enabled ? 'bg-[#640c0e]' : 'bg-neutral-300'"
              >
                <span
                  class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                  :class="notificationsStore.preferences.push_enabled ? 'translate-x-5' : 'translate-x-0'"
                />
              </button>
            </div>
          </div>
        </section>

        <!-- Section 2: Notification Topics & Categories -->
        <section class="community-card p-5 sm:p-6 bg-white border border-[#ebe8de] space-y-4">
          <div class="border-b border-[#ebe8de]/60 pb-3">
            <h2 class="text-base font-bold text-[#640c0e]">
              Notification Topics
            </h2>
            <p class="text-xs text-neutral-500 mt-0.5">
              Customize the types of communications and updates you wish to receive.
            </p>
          </div>

          <div class="space-y-4 pt-1">
            <!-- Support & Inquiries -->
            <div class="flex items-start justify-between gap-4 py-2 border-b border-[#ebe8de]/40">
              <div class="space-y-0.5">
                <label class="text-xs font-bold text-neutral-800 cursor-pointer" for="pref-support">
                  Support & Inquiry Updates
                </label>
                <p class="text-xs text-neutral-500">
                  Receive alerts when an SFU MSA coordinator replies to your inquiry or updates its resolution status.
                </p>
              </div>
              <button
                id="pref-support"
                type="button"
                role="switch"
                :aria-checked="notificationsStore.preferences.support_updates"
                :disabled="notificationsStore.isSavingPreferences"
                @click="togglePreference('support_updates', !notificationsStore.preferences.support_updates)"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#640c0e]/30 disabled:opacity-50"
                :class="notificationsStore.preferences.support_updates ? 'bg-[#640c0e]' : 'bg-neutral-300'"
              >
                <span
                  class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                  :class="notificationsStore.preferences.support_updates ? 'translate-x-5' : 'translate-x-0'"
                />
              </button>
            </div>

            <!-- Campus Announcements -->
            <div class="flex items-start justify-between gap-4 py-2 border-b border-[#ebe8de]/40">
              <div class="space-y-0.5">
                <label class="text-xs font-bold text-neutral-800 cursor-pointer" for="pref-announcements">
                  Campus & Community Announcements
                </label>
                <p class="text-xs text-neutral-500">
                  Official notices regarding Jumu'ah schedules, prayer room access, campus events, and general MSA news.
                </p>
              </div>
              <button
                id="pref-announcements"
                type="button"
                role="switch"
                :aria-checked="notificationsStore.preferences.new_announcements"
                :disabled="notificationsStore.isSavingPreferences"
                @click="togglePreference('new_announcements', !notificationsStore.preferences.new_announcements)"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#640c0e]/30 disabled:opacity-50"
                :class="notificationsStore.preferences.new_announcements ? 'bg-[#640c0e]' : 'bg-neutral-300'"
              >
                <span
                  class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                  :class="notificationsStore.preferences.new_announcements ? 'translate-x-5' : 'translate-x-0'"
                />
              </button>
            </div>

            <!-- Upcoming Training & Events -->
            <div class="flex items-start justify-between gap-4 py-2 border-b border-[#ebe8de]/40">
              <div class="space-y-0.5">
                <label class="text-xs font-bold text-neutral-800 cursor-pointer" for="pref-training">
                  Event & Workshop Reminders
                </label>
                <p class="text-xs text-neutral-500">
                  Reminders for upcoming seminars, guest lectures, social activities, and volunteer orientations.
                </p>
              </div>
              <button
                id="pref-training"
                type="button"
                role="switch"
                :aria-checked="notificationsStore.preferences.upcoming_training"
                :disabled="notificationsStore.isSavingPreferences"
                @click="togglePreference('upcoming_training', !notificationsStore.preferences.upcoming_training)"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#640c0e]/30 disabled:opacity-50"
                :class="notificationsStore.preferences.upcoming_training ? 'bg-[#640c0e]' : 'bg-neutral-300'"
              >
                <span
                  class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                  :class="notificationsStore.preferences.upcoming_training ? 'translate-x-5' : 'translate-x-0'"
                />
              </button>
            </div>

            <!-- Course & Learning Progress -->
            <div class="flex items-start justify-between gap-4 py-2 border-b border-[#ebe8de]/40">
              <div class="space-y-0.5">
                <label class="text-xs font-bold text-neutral-800 cursor-pointer" for="pref-course">
                  Academy & Learning Paths
                </label>
                <p class="text-xs text-neutral-500">
                  Progress milestones, course enrollment updates, and lesson completions.
                </p>
              </div>
              <button
                id="pref-course"
                type="button"
                role="switch"
                :aria-checked="notificationsStore.preferences.course_completion"
                :disabled="notificationsStore.isSavingPreferences"
                @click="togglePreference('course_completion', !notificationsStore.preferences.course_completion)"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#640c0e]/30 disabled:opacity-50"
                :class="notificationsStore.preferences.course_completion ? 'bg-[#640c0e]' : 'bg-neutral-300'"
              >
                <span
                  class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                  :class="notificationsStore.preferences.course_completion ? 'translate-x-5' : 'translate-x-0'"
                />
              </button>
            </div>

            <!-- Certificates & Achievements -->
            <div class="flex items-start justify-between gap-4 py-2 border-b border-[#ebe8de]/40">
              <div class="space-y-0.5">
                <label class="text-xs font-bold text-neutral-800 cursor-pointer" for="pref-certs">
                  Certificates & Milestone Badges
                </label>
                <p class="text-xs text-neutral-500">
                  Alerts when you are awarded verifiable certificates, leadership recognitions, or achievements.
                </p>
              </div>
              <button
                id="pref-certs"
                type="button"
                role="switch"
                :aria-checked="notificationsStore.preferences.certificate_earned"
                :disabled="notificationsStore.isSavingPreferences"
                @click="togglePreference('certificate_earned', !notificationsStore.preferences.certificate_earned)"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#640c0e]/30 disabled:opacity-50"
                :class="notificationsStore.preferences.certificate_earned ? 'bg-[#640c0e]' : 'bg-neutral-300'"
              >
                <span
                  class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                  :class="notificationsStore.preferences.certificate_earned ? 'translate-x-5' : 'translate-x-0'"
                />
              </button>
            </div>

            <!-- Direct Messages -->
            <div class="flex items-start justify-between gap-4 py-2">
              <div class="space-y-0.5">
                <label class="text-xs font-bold text-neutral-800 cursor-pointer" for="pref-dm">
                  Direct Messages
                </label>
                <p class="text-xs text-neutral-500">
                  Notifications when an authorized administrator or member contacts you in a direct conversation.
                </p>
              </div>
              <button
                id="pref-dm"
                type="button"
                role="switch"
                :aria-checked="notificationsStore.preferences.direct_messages"
                :disabled="notificationsStore.isSavingPreferences"
                @click="togglePreference('direct_messages', !notificationsStore.preferences.direct_messages)"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#640c0e]/30 disabled:opacity-50"
                :class="notificationsStore.preferences.direct_messages ? 'bg-[#640c0e]' : 'bg-neutral-300'"
              >
                <span
                  class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                  :class="notificationsStore.preferences.direct_messages ? 'translate-x-5' : 'translate-x-0'"
                />
              </button>
            </div>
          </div>
        </section>

        <!-- Section 3: Registered Devices & Web Push Subscriptions -->
        <section class="community-card p-5 sm:p-6 bg-white border border-[#ebe8de] space-y-5">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[#ebe8de]/60 pb-3">
            <div>
              <h2 class="text-base font-bold text-[#640c0e] flex items-center gap-2">
                <BellRing class="w-4 h-4 text-[#640c0e]" />
                <span>Registered Push Devices</span>
              </h2>
              <p class="text-xs text-neutral-500 mt-0.5">
                Active browsers and phones configured to receive real-time Web Push alerts.
              </p>
            </div>

            <div class="flex items-center gap-2">
              <Button
                v-if="isPushSupported && pushPermission === 'granted' && isThisDeviceSubscribed"
                variant="outline"
                size="sm"
                :is-loading="isSendingTest"
                :disabled="isSendingTest"
                @click="handleSendTestPush"
                title="Send a test notification to verify delivery on this device"
              >
                <template #left-icon>
                  <Send class="w-3.5 h-3.5 text-[#b02e32]" />
                </template>
                <span>Send Test Alert</span>
              </Button>

              <Button
                v-if="registeredDevices.length > 0"
                variant="outline"
                size="sm"
                @click="showRevokeAllModal = true"
                class="text-red-700 hover:text-red-800 hover:border-red-300"
              >
                <span>Revoke All</span>
              </Button>
            </div>
          </div>

          <!-- Browser Push Feedback Banner -->
          <div
            v-if="pushActionFeedback"
            class="p-3.5 rounded-xl border text-xs font-medium flex items-center gap-2.5 transition-all"
            :class="pushActionFeedback.type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800'"
          >
            <component
              :is="pushActionFeedback.type === 'success' ? Check : AlertCircle"
              class="w-4 h-4 shrink-0"
            />
            <span>{{ pushActionFeedback.message }}</span>
          </div>

          <!-- Device Status & Enrollment Prompt -->
          <div class="p-4 rounded-xl border border-[#ebe8de] bg-[#fffbf4] space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold text-neutral-800">Current Device:</span>
                  <Badge v-if="isThisDeviceSubscribed" variant="green" size="sm">
                    Enrolled & Active
                  </Badge>
                  <Badge v-else-if="pushPermission === 'denied'" variant="red" size="sm">
                    Browser Blocked
                  </Badge>
                  <Badge v-else variant="gold" size="sm">
                    Not Registered
                  </Badge>
                </div>
                <p class="text-xs text-neutral-600 max-w-xl">
                  {{ isThisDeviceSubscribed
                    ? 'This browser is successfully registered to receive real-time push alerts from SFU MSA Connect.'
                    : 'Enable push notifications on this device to get instant alerts for ticket updates and messages.'
                  }}
                </p>
              </div>

              <!-- Action: Register this device -->
              <div v-if="!isThisDeviceSubscribed && pushPermission !== 'denied'" class="shrink-0">
                <Button
                  variant="primary"
                  size="sm"
                  :is-loading="isSubscribing"
                  @click="handleSubscribeCurrentDevice"
                >
                  <template #left-icon>
                    <Bell class="w-3.5 h-3.5" />
                  </template>
                  <span>Register This Device</span>
                </Button>
              </div>
            </div>

            <!-- Permission Denied Warning Notice -->
            <div
              v-if="pushPermission === 'denied'"
              class="p-3 rounded-lg border border-red-200 bg-red-50/70 text-xs text-red-800 space-y-1"
            >
              <div class="flex items-center gap-1.5 font-bold">
                <AlertTriangle class="w-4 h-4 text-red-600 shrink-0" />
                <span>Browser Permission is Blocked</span>
              </div>
              <p class="text-[11px] leading-relaxed text-red-700">
                You previously blocked notifications for connect.sfumsa.ca. To enable push alerts, click the lock/settings icon in your browser address bar, reset the Notification permission to "Allow", and reload this page.
              </p>
            </div>
          </div>

          <!-- Active Devices List -->
          <div class="space-y-3 pt-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-500">
              Active Devices ({{ registeredDevices.length }})
            </h3>

            <div v-if="isLoadingDevices" class="py-6 flex justify-center">
              <Spinner size="md" />
            </div>

            <div v-else-if="registeredDevices.length === 0" class="p-6 rounded-xl border border-dashed border-[#ebe8de] text-center text-xs text-neutral-500">
              <BellOff class="w-6 h-6 mx-auto mb-1 text-neutral-400" />
              <span>No active push device subscriptions found for your account.</span>
            </div>

            <div v-else class="divide-y divide-[#ebe8de]/60 border border-[#ebe8de] rounded-xl overflow-hidden bg-white">
              <div
                v-for="device in registeredDevices"
                :key="device.uuid"
                class="p-3.5 sm:p-4 flex items-center justify-between gap-3 hover:bg-[#fffbf4]/40 transition-colors"
              >
                <div class="flex items-center gap-3 min-w-0">
                  <div class="w-8 h-8 rounded-lg bg-neutral-100 flex items-center justify-center text-neutral-600 shrink-0">
                    <Smartphone v-if="device.device_type === 'mobile'" class="w-4 h-4" />
                    <Tablet v-else-if="device.device_type === 'tablet'" class="w-4 h-4" />
                    <Monitor v-else class="w-4 h-4" />
                  </div>

                  <div class="space-y-0.5 min-w-0">
                    <div class="flex items-center gap-2">
                      <span class="text-xs font-bold text-neutral-800 truncate">
                        {{ device.device_name || 'Browser Device' }}
                      </span>
                      <span v-if="(device as any).is_current" class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-emerald-100 text-emerald-800">
                        This Device
                      </span>
                    </div>
                    <div class="text-[11px] text-neutral-500 flex items-center gap-3">
                      <span>Subscribed: {{ new Date(device.created_at).toLocaleDateString() }}</span>
                      <span v-if="device.last_used_at">Last Used: {{ new Date(device.last_used_at).toLocaleDateString() }}</span>
                    </div>
                  </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                  <Button
                    variant="ghost"
                    size="sm"
                    :disabled="isRevokingDevice === device.uuid"
                    @click="confirmRevokeDevice(device)"
                    class="text-neutral-500 hover:text-red-700 text-xs"
                    title="Revoke push alerts on this device"
                  >
                    <Trash2 class="w-3.5 h-3.5 text-neutral-400 hover:text-red-600" />
                    <span class="hidden sm:inline">Revoke</span>
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <!-- Modal: Confirm Revoke Single Device -->
    <Modal
      v-model="showRevokeSingleModal"
      title="Revoke Device Push Alerts"
      subtitle="Confirm removal of push registration"
    >
      <div class="space-y-3 text-xs text-neutral-700">
        <p>
          Are you sure you want to revoke push notifications for
          <strong class="text-neutral-900">{{ deviceToRevoke?.device_name || 'this device' }}</strong>?
        </p>
        <p class="text-neutral-500">
          This device will stop receiving push alerts for direct messages and campus updates immediately. You can re-enable push on the device at any time.
        </p>
      </div>

      <template #footer>
        <div class="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            @click="showRevokeSingleModal = false"
          >
            Cancel
          </Button>
          <Button
            variant="primary"
            size="sm"
            :is-loading="isRevokingDevice === deviceToRevoke?.uuid"
            @click="handleRevokeDevice"
            class="bg-red-700 hover:bg-red-800 text-white"
          >
            Revoke Device
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Modal: Confirm Revoke All Devices -->
    <Modal
      v-model="showRevokeAllModal"
      title="Revoke All Registered Devices"
      subtitle="Remove push notifications across all of your devices"
    >
      <div class="space-y-3 text-xs text-neutral-700">
        <p>
          Are you sure you want to revoke push notifications across <strong>all</strong> of your registered devices?
        </p>
        <p class="text-neutral-500">
          All active browser subscriptions on your phones, tablets, and computers will be immediately deactivated on the server.
        </p>
      </div>

      <template #footer>
        <div class="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            @click="showRevokeAllModal = false"
          >
            Cancel
          </Button>
          <Button
            variant="primary"
            size="sm"
            :is-loading="isRevokingAll"
            @click="handleRevokeAllDevices"
            class="bg-red-700 hover:bg-red-800 text-white"
          >
            Revoke All Devices
          </Button>
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useNotificationsStore } from '@/stores/notifications';
import PageHero from '@/components/ui/PageHero.vue';
import RouteTabs, { type RouteTabItem } from '@/components/ui/RouteTabs.vue';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import Spinner from '@/components/ui/Spinner.vue';
import Modal from '@/components/ui/Modal.vue';
import { webPushService } from '@/services/webpush.service';
import type { UpdatePreferencesPayload, DeviceSubscriptionItem } from '@/types/notification';
import {
  Bell,
  BellOff,
  BellRing,
  Check,
  Trash2,
  RefreshCw,
  Sliders,
  AlertCircle,
  AlertTriangle,
  Smartphone,
  Tablet,
  Monitor,
  Send,
} from 'lucide-vue-next';

const notificationsStore = useNotificationsStore();

const navTabs = computed<RouteTabItem[]>(() => [
  {
    to: '/app/notifications',
    label: 'Alerts & Updates',
    icon: Bell,
    badge: notificationsStore.unreadCount,
    exact: true,
  },
  {
    to: '/app/notifications/preferences',
    label: 'Delivery Preferences',
    icon: Sliders,
  },
]);

const preferenceFeedback = ref<{ message: string; type: 'success' | 'error' } | null>(null);
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null;

// Web Push state
const isPushSupported = ref<boolean>(webPushService.isSupported());
const pushPermission = ref<NotificationPermission | 'unsupported'>(webPushService.getPermissionState());
const isThisDeviceSubscribed = ref<boolean>(false);
const registeredDevices = ref<DeviceSubscriptionItem[]>([]);
const isLoadingDevices = ref<boolean>(false);
const isSubscribing = ref<boolean>(false);
const isSendingTest = ref<boolean>(false);
const isRevokingDevice = ref<string | null>(null);
const isRevokingAll = ref<boolean>(false);
const showRevokeSingleModal = ref<boolean>(false);
const deviceToRevoke = ref<DeviceSubscriptionItem | null>(null);
const showRevokeAllModal = ref<boolean>(false);
const pushActionFeedback = ref<{ message: string; type: 'success' | 'error' } | null>(null);
let pushFeedbackTimeout: ReturnType<typeof setTimeout> | null = null;

function handleRefresh() {
  notificationsStore.fetchPreferences(true);
  fetchDevices();
}

async function fetchDevices() {
  if (!isPushSupported.value) return;
  isLoadingDevices.value = true;
  try {
    pushPermission.value = webPushService.getPermissionState();
    registeredDevices.value = await webPushService.getSubscriptions();
    isThisDeviceSubscribed.value = await webPushService.isCurrentDeviceSubscribed();
  } catch (err) {
    console.warn('Failed to load registered push devices:', err);
  } finally {
    isLoadingDevices.value = false;
  }
}

function setPushFeedback(message: string, type: 'success' | 'error') {
  if (pushFeedbackTimeout) clearTimeout(pushFeedbackTimeout);
  pushActionFeedback.value = { message, type };
  pushFeedbackTimeout = setTimeout(() => {
    pushActionFeedback.value = null;
  }, 4000);
}

async function handleSubscribeCurrentDevice() {
  isSubscribing.value = true;
  try {
    const res = await webPushService.subscribeCurrentDevice();
    if (res.success) {
      setPushFeedback('This device has been successfully registered for push alerts.', 'success');
      await fetchDevices();
    } else {
      setPushFeedback(res.message || 'Failed to register this device.', 'error');
      pushPermission.value = webPushService.getPermissionState();
    }
  } catch (err: any) {
    setPushFeedback(err?.message || 'Error occurred while registering device.', 'error');
  } finally {
    isSubscribing.value = false;
  }
}

async function handleSendTestPush() {
  isSendingTest.value = true;
  try {
    const res = await webPushService.sendTestNotification();
    if (res.success) {
      setPushFeedback(
        (res.message || 'Test push alert accepted by push gateway.') + ' Note: Physical banner display is controlled by your operating system notification settings and active Focus/Do Not Disturb modes.',
        'success'
      );
      notificationsStore.fetchUnreadCount();
    } else {
      setPushFeedback(res.message || 'Failed to dispatch test notification.', 'error');
    }
  } catch (err: any) {
    setPushFeedback(err?.message || 'Error occurred while sending test notification.', 'error');
  } finally {
    isSendingTest.value = false;
  }
}

function confirmRevokeDevice(device: DeviceSubscriptionItem) {
  deviceToRevoke.value = device;
  showRevokeSingleModal.value = true;
}

async function handleRevokeDevice() {
  if (!deviceToRevoke.value) return;
  const uuid = deviceToRevoke.value.uuid;
  isRevokingDevice.value = uuid;
  try {
    const success = await webPushService.revokeDevice(uuid);
    if (success) {
      setPushFeedback('Device subscription revoked successfully.', 'success');
      showRevokeSingleModal.value = false;
      deviceToRevoke.value = null;
      await fetchDevices();
    } else {
      setPushFeedback('Failed to revoke device subscription.', 'error');
    }
  } finally {
    isRevokingDevice.value = null;
  }
}

async function handleRevokeAllDevices() {
  isRevokingAll.value = true;
  try {
    const success = await webPushService.revokeAllDevices();
    if (success) {
      setPushFeedback('All device subscriptions have been revoked.', 'success');
      showRevokeAllModal.value = false;
      await fetchDevices();
    } else {
      setPushFeedback('Failed to revoke all devices.', 'error');
    }
  } finally {
    isRevokingAll.value = false;
  }
}

async function togglePreference(key: keyof UpdatePreferencesPayload, value: boolean) {
  if (!notificationsStore.preferences || notificationsStore.isSavingPreferences) return;

  const previousValue = notificationsStore.preferences[key];
  notificationsStore.preferences[key] = value;

  const success = await notificationsStore.updatePreferences({ [key]: value });

  if (feedbackTimeout) clearTimeout(feedbackTimeout);

  if (success) {
    preferenceFeedback.value = { message: 'Preference updated successfully.', type: 'success' };
  } else {
    notificationsStore.preferences[key] = previousValue;
    preferenceFeedback.value = { message: 'Failed to update preference. Please try again.', type: 'error' };
  }

  feedbackTimeout = setTimeout(() => {
    preferenceFeedback.value = null;
  }, 4000);
}

onMounted(() => {
  notificationsStore.fetchPreferences();
  fetchDevices();
  notificationsStore.fetchUnreadCount();
});

onUnmounted(() => {
  if (feedbackTimeout) clearTimeout(feedbackTimeout);
  if (pushFeedbackTimeout) clearTimeout(pushFeedbackTimeout);
});
</script>
