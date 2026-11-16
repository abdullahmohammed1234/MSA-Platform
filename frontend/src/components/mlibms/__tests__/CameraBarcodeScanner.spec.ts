import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';

type StartArgs = {
  camera: unknown;
  config: {
    fps?: number;
    qrbox?: (w: number, h: number) => { width: number; height: number };
  };
  success: (text: string) => void;
  failure: (message: string) => void;
};

const mockState = vi.hoisted(() => {
  const instances: Array<{
    config: Record<string, unknown>;
    isScanning: boolean;
    startArgs: StartArgs | null;
    stop: ReturnType<typeof vi.fn>;
    clear: ReturnType<typeof vi.fn>;
  }> = [];

  class Html5QrcodeMock {
    isScanning = false;
    config: Record<string, unknown>;
    startArgs: StartArgs | null = null;
    stop = vi.fn(async () => {
      this.isScanning = false;
    });
    clear = vi.fn(async () => undefined);

    constructor(_elementId: string, config: Record<string, unknown>) {
      this.config = config;
      instances.push(this);
    }

    async start(
      camera: unknown,
      config: StartArgs['config'],
      success: StartArgs['success'],
      failure: StartArgs['failure'],
    ) {
      this.isScanning = true;
      this.startArgs = { camera, config, success, failure };
      return null;
    }
  }

  return {
    instances,
    Html5QrcodeMock,
    Html5QrcodeSupportedFormats: {
      EAN_13: 9,
      EAN_8: 10,
      UPC_A: 14,
      UPC_E: 15,
      CODE_128: 5,
      CODE_39: 3,
      ITF: 8,
      QR_CODE: 0,
    },
  };
});

vi.mock('html5-qrcode', () => ({
  Html5Qrcode: mockState.Html5QrcodeMock,
  Html5QrcodeSupportedFormats: mockState.Html5QrcodeSupportedFormats,
}));

import CameraBarcodeScanner from '../CameraBarcodeScanner.vue';

describe('CameraBarcodeScanner', () => {
  beforeEach(() => {
    mockState.instances.length = 0;
    vi.useFakeTimers();
    vi.stubGlobal(
      'AudioContext',
      class {
        createOscillator() {
          return {
            type: 'sine',
            frequency: { setValueAtTime: vi.fn() },
            connect: vi.fn(),
            start: vi.fn(),
            stop: vi.fn(),
          };
        }
        createGain() {
          return {
            gain: { setValueAtTime: vi.fn() },
            connect: vi.fn(),
          };
        }
        get currentTime() {
          return 0;
        }
        get destination() {
          return {};
        }
      },
    );
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  async function mountScanner(props: Record<string, unknown> = {}) {
    const wrapper = mount(CameraBarcodeScanner, {
      props: { cooldownMs: 3000, ...props },
      attachTo: document.body,
    });
    await flushPromises();
    await nextTick();
    return wrapper;
  }

  it('enables native BarcodeDetector with ZXing fallback path and ~15 FPS EAN strip', async () => {
    await mountScanner();
    expect(mockState.instances).toHaveLength(1);

    const instance = mockState.instances[0];
    expect(instance.config.useBarCodeDetectorIfSupported).toBe(true);
    expect((instance.config.experimentalFeatures as { useBarCodeDetectorIfSupported: boolean })
      .useBarCodeDetectorIfSupported).toBe(true);
    expect(instance.startArgs?.config.fps).toBe(15);

    const formats = instance.config.formatsToSupport as number[];
    expect(formats).toContain(mockState.Html5QrcodeSupportedFormats.EAN_13);

    const qrbox = instance.startArgs?.config.qrbox?.(640, 360);
    expect(qrbox).toBeTruthy();
    expect(qrbox!.width / qrbox!.height).toBeGreaterThan(2);
  });

  it('emits scan and scan-success once for a formed ISBN-13 decode', async () => {
    const wrapper = await mountScanner();
    const success = mockState.instances[0].startArgs!.success;

    success('978-0-13-235088-4');
    await nextTick();

    expect(wrapper.emitted('scan-success')).toEqual([['9780132350884']]);
    expect(wrapper.emitted('scan')).toEqual([['9780132350884']]);
  });

  it('accepts 979-prefixed ISBN-13 and trims EAN+5 to ISBN-13', async () => {
    const wrapper = await mountScanner();
    const success = mockState.instances[0].startArgs!.success;

    success('9791234567896');
    await nextTick();
    expect(wrapper.emitted('scan-success')?.[0]).toEqual(['9791234567896']);

    // Advance past cooldown so a second accept is allowed
    vi.advanceTimersByTime(3000);
    success('978013235088451999');
    await nextTick();
    expect(wrapper.emitted('scan-success')?.[1]).toEqual(['9780132350884']);
  });

  it('prevents duplicate emits for the same barcode within the cooldown window', async () => {
    const wrapper = await mountScanner({ cooldownMs: 3000 });
    const success = mockState.instances[0].startArgs!.success;

    success('9780132350884');
    success('9780132350884');
    success('9780132350884');
    await nextTick();

    expect(wrapper.emitted('scan-success')).toHaveLength(1);

    vi.advanceTimersByTime(3000);
    success('9780132350884');
    await nextTick();
    expect(wrapper.emitted('scan-success')).toHaveLength(2);
  });

  it('shows a searching hint after sustained decode misses and clears timers on unmount', async () => {
    const wrapper = await mountScanner();
    const instance = mockState.instances[0];
    const failure = instance.startArgs!.failure;

    failure('NotFoundException');
    expect(wrapper.text()).not.toContain('Still looking for an ISBN barcode');

    vi.advanceTimersByTime(8000);
    await nextTick();
    expect(wrapper.text()).toContain('Still looking for an ISBN barcode');

    wrapper.unmount();
    await flushPromises();

    expect(instance.stop).toHaveBeenCalled();
    expect(instance.clear).toHaveBeenCalled();
  });
});
