<script setup lang="ts">
import { computed } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import type { ButtonProps } from '@/components/ui/button/types';

type PublicButtonVariant = ButtonProps['variant'] | 'white';

interface Props {
  variant?: PublicButtonVariant;
  size?: ButtonProps['size'];
  isLoading?: boolean;
  disabled?: boolean;
  isFullWidth?: boolean;
  isShiny?: boolean;
  type?: ButtonProps['type'];
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'md',
  isLoading: false,
  disabled: false,
  isFullWidth: false,
  isShiny: false,
  type: 'button',
});

const emit = defineEmits<{
  (e: 'click', event: MouseEvent): void;
}>();

const buttonVariant = computed<ButtonProps['variant']>(() => {
  if (props.variant === 'white') {
    return 'outline';
  }

  return props.variant ?? 'primary';
});
</script>

<template>
  <Button
    :variant="buttonVariant"
    :size="size"
    :is-loading="isLoading"
    :disabled="disabled"
    :is-full-width="isFullWidth"
    :is-shiny="isShiny"
    :type="type"
    @click="emit('click', $event)"
  >
    <slot />
  </Button>
</template>
