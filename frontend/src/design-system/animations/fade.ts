import { easings, durations } from './presets';

/**
 * Fade transition preset (opacity only)
 */
export const fade = {
  initial: { opacity: 0 },
  animate: { opacity: 1 },
  exit: { opacity: 0 },
  transition: { duration: durations.normal, easing: easings.natural }
};
