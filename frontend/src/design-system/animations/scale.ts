import { easings, durations } from './presets';

/**
 * Standard scale-up and fade transition
 */
export const scale = {
  initial: { opacity: 0, scale: 0.95 },
  animate: { opacity: 1, scale: 1 },
  exit: { opacity: 0, scale: 0.95 },
  transition: { duration: durations.normal, easing: easings.premium }
};

/**
 * Subtle dashboard panel scale transition
 */
export const tabScale = {
  initial: { opacity: 0, scale: 0.995, y: 6 },
  animate: { opacity: 1, scale: 1, y: 0 },
  exit: { opacity: 0, y: -4 },
  transition: { duration: durations.normal, easing: easings.premium }
};
