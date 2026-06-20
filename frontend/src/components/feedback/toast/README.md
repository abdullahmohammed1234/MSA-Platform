# Toast System

## Purpose
A global non-blocking notification alert queue integrated with Pinia. Supports Success, Error, Warning, and Info alerts with custom automatic timeouts and manual close triggers.

## Setup

First, render the `ToastContainer` globally in your root template (e.g. `App.vue` or standard layout wrapper):

```vue
<template>
  <div class="app">
    <router-view />
    <ToastContainer />
  </div>
</template>

<script setup>
import { ToastContainer } from '@/components/feedback/toast';
</script>
```

## API usage (Pinia Store)

```typescript
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

// Triggers
toast.success('Course completed successfully!');
toast.error('Could not verify credentials.');
toast.warning('Your session will expire in 2 minutes.');
toast.info('Additional reading materials added.');

// Custom duration (in ms, or 0 to never auto-dismiss)
toast.addToast('Loading files...', 'info', 0);
```

## Props

### Toast Props
| Prop | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `toast` | `ToastItem` | *Required* | Individual notification alert item object |

#### ToastItem Interface
```typescript
interface ToastItem {
  id: string;
  message: string;
  type?: 'success' | 'error' | 'warning' | 'info';
  duration?: number;
}
```
