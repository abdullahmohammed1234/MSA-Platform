export const easings = {
  premium: [0.16, 1, 0.3, 1],
  natural: [0.4, 0, 0.2, 1],
  sharp: [0.4, 0, 0.6, 1],
  soft: [0.25, 0.1, 0.25, 1],
}

export const springs = {
  subtle: { stiffness: 150, damping: 25, mass: 0.8 },
  gentle: { stiffness: 220, damping: 28, mass: 1 },
  snappy: { stiffness: 380, damping: 35, mass: 0.9 },
  bouncy: { stiffness: 450, damping: 22, mass: 0.95 },
}

export const durations = {
  fast: 0.2,
  normal: 0.35,
  slow: 0.6,
}

// Standard Page and Section Transitions
export const pageTransition = {
  initial: { opacity: 0, y: 10 },
  animate: { opacity: 1, y: 0 },
  exit: { opacity: 0, y: -6 },
  transition: { duration: durations.slow, easing: easings.premium }
}

export const tabTransition = {
  initial: { opacity: 0, scale: 0.995, y: 6 },
  animate: { opacity: 1, scale: 1, y: 0 },
  exit: { opacity: 0, y: -4 },
  transition: { duration: durations.normal, easing: easings.premium }
}
