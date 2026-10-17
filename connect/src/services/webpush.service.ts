import apiClient from './api.ts';
import type {
  PushConfigResponse,
  DeviceSubscriptionItem,
  RegisterSubscriptionPayload,
} from '../types/notification.ts';

/**
 * Convert URL-safe base64 string to Uint8Array for PushManager subscription.
 */
export function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding)
    .replace(/-/g, '+')
    .replace(/_/g, '/');

  const atobFn = (typeof window !== 'undefined' && typeof window.atob === 'function')
    ? window.atob.bind(window)
    : atob;
  const rawData = atobFn(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

/**
 * Detect client device category based on user agent heuristics.
 */
export function detectDeviceType(customUa?: string): 'mobile' | 'tablet' | 'desktop' {
  const ua = (customUa ?? (typeof navigator !== 'undefined' ? navigator.userAgent : '')).toLowerCase();
  if (/(ipad|tablet|(android(?!.*mobile))|(windows(?!.*phone)(.*touch))|kindle|playbook|silk)/i.test(ua)) {
    return 'tablet';
  }
  if (/(mobi|ipod|phone|blackberry|opera mini|fennec|minimo|symbian|psp)/i.test(ua)) {
    return 'mobile';
  }
  return 'desktop';
}

/**
 * Generate human-friendly device name for UI display.
 */
export function detectDeviceName(customUa?: string): string {
  const ua = customUa ?? (typeof navigator !== 'undefined' ? navigator.userAgent : '');
  if (!ua) return 'Web Browser';
  let browser = 'Browser';
  let os = 'Device';

  if (ua.includes('Firefox')) browser = 'Firefox';
  else if (ua.includes('Edg')) browser = 'Edge';
  else if (ua.includes('Chrome')) browser = 'Chrome';
  else if (ua.includes('Safari')) browser = 'Safari';

  if (ua.includes('Windows')) os = 'Windows';
  else if (ua.includes('Mac OS')) os = 'macOS';
  else if (ua.includes('Android')) os = 'Android';
  else if (ua.includes('iPhone') || ua.includes('iPad')) os = 'iOS';
  else if (ua.includes('Linux')) os = 'Linux';

  return `${browser} on ${os}`;
}

export const webPushService = {
  /**
   * Check whether Web Push is supported by the client browser.
   */
  isSupported(): boolean {
    return (
      typeof window !== 'undefined' &&
      'serviceWorker' in navigator &&
      'PushManager' in window &&
      'Notification' in window
    );
  },

  /**
   * Get current Notification permission state.
   */
  getPermissionState(): NotificationPermission | 'unsupported' {
    if (!this.isSupported()) return 'unsupported';
    return Notification.permission;
  },

  /**
   * Register the Connect PWA service worker.
   */
  async registerServiceWorker(): Promise<ServiceWorkerRegistration | null> {
    if (!this.isSupported()) return null;

    try {
      const registration = await navigator.serviceWorker.register('/sw.js', {
        scope: '/',
      });
      return registration;
    } catch (err) {
      console.warn('Failed to register service worker for Web Push:', err);
      return null;
    }
  },

  /**
   * Get Web Push server configuration (VAPID public key & enable status).
   */
  async getConfig(): Promise<PushConfigResponse> {
    const response = await apiClient.get<PushConfigResponse>('/push/config');
    return response.data;
  },

  /**
   * List the authenticated user's registered active device subscriptions.
   */
  async getSubscriptions(): Promise<DeviceSubscriptionItem[]> {
    const response = await apiClient.get<{ subscriptions?: DeviceSubscriptionItem[]; devices?: DeviceSubscriptionItem[] }>('/push/subscriptions');
    return response.data.devices || response.data.subscriptions || [];
  },

  /**
   * Request browser permission and register this device with the backend.
   */
  async subscribeCurrentDevice(): Promise<{ success: boolean; message: string }> {
    if (!this.isSupported()) {
      return { success: false, message: 'Web Push notifications are not supported in this browser.' };
    }

    // 1. Request explicit browser notification permission
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      return {
        success: false,
        message: permission === 'denied'
          ? 'Push notifications are blocked in your browser settings. Please enable notifications for connect.sfumsa.ca.'
          : 'Notification permission prompt was dismissed.',
      };
    }

    // 2. Fetch server push configuration and VAPID public key
    const config = await this.getConfig();
    if (!config.enabled || !config.public_key) {
      return { success: false, message: 'Web Push is currently disabled or not configured on the server.' };
    }

    // 3. Ensure service worker is registered and ready
    await this.registerServiceWorker();
    const registration = await navigator.serviceWorker.ready;

    // 4. Subscribe to browser PushManager
    const applicationServerKey = urlBase64ToUint8Array(config.public_key);
    let subscription = await registration.pushManager.getSubscription();

    if (!subscription) {
      subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey,
      });
    }

    // 5. Extract keys and raw endpoint
    const subJson = subscription.toJSON();
    const endpoint = subscription.endpoint;
    const publicKey = subJson.keys?.p256dh;
    const authToken = subJson.keys?.auth;

    if (!endpoint || !publicKey || !authToken) {
      return { success: false, message: 'Browser failed to generate push subscription keys.' };
    }

    // 6. Register subscription with the backend
    const payload: RegisterSubscriptionPayload = {
      endpoint,
      keys: {
        p256dh: publicKey,
        auth: authToken,
      },
      public_key: publicKey,
      auth_token: authToken,
      content_encoding: 'aes128gcm',
      device_name: detectDeviceName(),
      device_type: detectDeviceType(),
      user_agent: navigator.userAgent,
    };

    await apiClient.post('/push/subscriptions', payload);
    return { success: true, message: 'Device successfully registered for push notifications.' };
  },

  /**
   * Check if the current browser already has an active local push subscription.
   */
  async isCurrentDeviceSubscribed(): Promise<boolean> {
    if (!this.isSupported() || Notification.permission !== 'granted') {
      return false;
    }
    try {
      const registration = await navigator.serviceWorker.getRegistration('/');
      if (!registration) return false;
      const sub = await registration.pushManager.getSubscription();
      return !!sub;
    } catch {
      return false;
    }
  },

  /**
   * Revoke an individual device subscription by its UUID.
   */
  async revokeDevice(uuid: string): Promise<boolean> {
    try {
      await apiClient.delete(`/push/subscriptions/${encodeURIComponent(uuid)}`);
      return true;
    } catch (err) {
      console.warn('Failed to revoke device subscription:', err);
      return false;
    }
  },

  /**
   * Revoke all of the authenticated user's device subscriptions.
   */
  async revokeAllDevices(): Promise<boolean> {
    try {
      await apiClient.delete('/push/subscriptions');

      // Also clean up local push subscription if active
      if (this.isSupported()) {
        try {
          const registration = await navigator.serviceWorker.getRegistration('/');
          const sub = await registration?.pushManager.getSubscription();
          if (sub) {
            await sub.unsubscribe();
          }
        } catch (ignored) {
          // Local cleanup failure should not prevent server revocation success
        }
      }

      return true;
    } catch (err) {
      console.warn('Failed to revoke all device subscriptions:', err);
      return false;
    }
  },

  /**
   * Request the backend to dispatch a test Web Push notification to current user's registered devices.
   */
  async sendTestNotification(): Promise<{ success: boolean; message: string }> {
    try {
      const response = await apiClient.post<{ success: boolean; message: string }>('/push/test');
      return {
        success: response.data?.success ?? true,
        message: response.data?.message || 'Test notification dispatched to your registered devices.',
      };
    } catch (err: unknown) {
      const apiErr = err as { response?: { data?: { message?: string } } };
      const msg = apiErr.response?.data?.message || 'Failed to dispatch test notification.';
      return { success: false, message: msg };
    }
  },
};

export default webPushService;
