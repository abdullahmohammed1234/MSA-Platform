import { emsHttp } from '@/services/ems/emsClient';
import type {
  StaleCapture,
  StaleCaptureListParams,
  StaleCaptureRefundResult,
} from '@/types/ems/staleCaptures';

export const staleCapturesService = {
  async list(params: StaleCaptureListParams = {}): Promise<{ items: StaleCapture[]; total: number }> {
    const envelope = await emsHttp.getWithMeta<StaleCapture[]>('/stale-captures', params as Record<string, unknown>);
    return {
      items: envelope.data,
      total: typeof envelope.meta?.total === 'number' ? envelope.meta.total : envelope.data.length,
    };
  },

  get(paymentUuid: string, squarePaymentId: string): Promise<StaleCapture> {
    return emsHttp.get(`/stale-captures/${paymentUuid}/${encodeURIComponent(squarePaymentId)}`);
  },

  refund(
    paymentUuid: string,
    squarePaymentId: string,
    payload: { reason: string; amount?: number }
  ): Promise<StaleCaptureRefundResult> {
    return emsHttp.post(
      `/stale-captures/${paymentUuid}/${encodeURIComponent(squarePaymentId)}/refund`,
      payload
    );
  },

  resolveWithoutRefund(
    paymentUuid: string,
    squarePaymentId: string,
    payload: { reason: string }
  ): Promise<StaleCapture> {
    return emsHttp.post(
      `/stale-captures/${paymentUuid}/${encodeURIComponent(squarePaymentId)}/resolve`,
      payload
    );
  },
};
