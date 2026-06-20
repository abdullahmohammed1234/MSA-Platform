import { easings, durations } from './presets';

/**
 * Backdrop overlay fade animation
 */
export const modalOverlay = {
  initial: { opacity: 0 },
  animate: { opacity: 1 },
  exit: { opacity: 0 },
  transition: { duration: durations.fast, easing: easings.natural }
};

/**
 * Modal dialogue scale and translation lift animation
 */
export const modalContent = {
  initial: { opacity: 0, scale: 0.9, y: 15 },
  animate: { opacity: 1, scale: 1, y: 0 },
  exit: { opacity: 0, scale: 0.9, y: 15 },
  transition: { duration: durations.normal, easing: easings.premium }
};
