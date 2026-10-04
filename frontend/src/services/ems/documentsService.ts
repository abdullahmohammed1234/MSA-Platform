import { emsHttp } from './emsClient';
import type { EventDocument, QrCodeResponse, UploadDocumentResponse } from '@/types/ems';

export const documentsService = {
  /** GET /events/{event}/documents */
  list(eventUuid: string): Promise<EventDocument[]> {
    return emsHttp.get<EventDocument[]>(`/events/${eventUuid}/documents`);
  },

  /** POST /events/{event}/documents */
  upload(eventUuid: string, formData: FormData): Promise<UploadDocumentResponse> {
    return emsHttp.post<UploadDocumentResponse>(`/events/${eventUuid}/documents`, formData);
  },

  /** PATCH /events/{event}/documents/{document} */
  update(
    eventUuid: string,
    docUuid: string,
    payload: {
      name?: string;
      document_type?: string;
      description?: string | null;
      sort_order?: number;
      is_active?: boolean;
    }
  ): Promise<EventDocument> {
    return emsHttp.patch<EventDocument>(`/events/${eventUuid}/documents/${docUuid}`, payload);
  },

  /** POST /events/{event}/documents/{document}/replace */
  replacePdf(eventUuid: string, docUuid: string, formData: FormData): Promise<EventDocument> {
    return emsHttp.post<EventDocument>(`/events/${eventUuid}/documents/${docUuid}/replace`, formData);
  },

  /** DELETE /events/{event}/documents/{document} */
  remove(eventUuid: string, docUuid: string): Promise<null> {
    return emsHttp.delete(`/events/${eventUuid}/documents/${docUuid}`);
  },

  /** POST /events/{event}/documents/{document}/rotate-access */
  rotateAccess(eventUuid: string, docUuid: string): Promise<UploadDocumentResponse> {
    return emsHttp.post<UploadDocumentResponse>(`/events/${eventUuid}/documents/${docUuid}/rotate-access`);
  },

  /** GET /events/{event}/documents/{document}/qr */
  getQr(eventUuid: string, docUuid: string, token?: string): Promise<QrCodeResponse> {
    const params = token ? `?token=${encodeURIComponent(token)}` : '';
    return emsHttp.get<QrCodeResponse>(`/events/${eventUuid}/documents/${docUuid}/qr${params}`);
  },
};
