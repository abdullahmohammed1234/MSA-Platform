import api from '@/services/api';

export interface BookAuthor {
  id: number;
  name: string;
  role: string;
}

export interface BookCategory {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  description?: string;
}

export interface CopyLocation {
  id: number;
  name: string;
  code: string;
  shelf_identifier?: string;
}

export interface BookCopy {
  id: number;
  uuid: string;
  book_id: number;
  barcode: string;
  accession_number: string;
  condition: string;
  condition_label: string;
  status: string;
  status_label: string;
  location?: CopyLocation;
  book?: Book;
  acquisition_date?: string;
  notes?: string;
}

export interface Book {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  subtitle?: string;
  isbn_10?: string;
  isbn_13?: string;
  edition?: string;
  publication_year?: number;
  language: string;
  summary?: string;
  cover_image_url?: string;
  default_loan_days: number;
  is_reference_only: boolean;
  category?: BookCategory;
  publisher?: { id: number; name: string };
  authors?: BookAuthor[];
  total_copies_count: number;
  available_copies_count: number;
  copies?: BookCopy[];
}

export interface LibraryMember {
  id: number;
  uuid: string;
  user_id?: number;
  library_card_number: string;
  name: string;
  email: string;
  phone?: string;
  membership_type: string;
  membership_type_label: string;
  status: string;
  max_active_loans: number;
  registered_at: string;
  suspended_at?: string;
  suspension_reason?: string;
  active_loans_count: number;
  is_guest: boolean;
}

export interface MemberLoan {
  id: number;
  uuid: string;
  copy: BookCopy;
  member: LibraryMember;
  checked_out_at: string;
  due_at: string;
  returned_at?: string;
  renewed_count: number;
  last_renewed_at?: string;
  reminder_sent_at?: string;
  status: string;
  status_label: string;
  is_overdue: boolean;
}

export interface BookReservation {
  id: number;
  uuid: string;
  book: Book;
  copy?: BookCopy;
  member: LibraryMember;
  status: string;
  status_label: string;
  queue_position: number;
  reserved_at: string;
  ready_at?: string;
  expires_at?: string;
}

export const mlibmsService = {
  async getPublicCatalog(params?: Record<string, any>) {
    const response = await api.get('/library/books', { params });
    return response.data;
  },

  async getBookDetails(uuid: string) {
    const response = await api.get(`/library/books/${uuid}`);
    return response.data;
  },

  async getCategories() {
    const response = await api.get('/library/categories');
    return response.data;
  },

  async selfServiceCheckout(copyBarcode: string) {
    const response = await api.post('/library/scan/checkout', { copy_barcode: copyBarcode });
    return response.data;
  },

  async selfServiceReturn(copyBarcode: string) {
    const response = await api.post('/library/scan/return', { copy_barcode: copyBarcode });
    return response.data;
  },

  async getMyPortalData() {
    const response = await api.get('/library/my-loans');
    return response.data;
  },

  async renewLoan(loanUuid: string) {
    const response = await api.post(`/library/loans/${loanUuid}/renew`);
    return response.data;
  },

  async placeHold(bookUuid: string) {
    const response = await api.post(`/library/books/${bookUuid}/hold`);
    return response.data;
  },

  async cancelHold(reservationUuid: string) {
    const response = await api.delete(`/library/holds/${reservationUuid}`);
    return response.data;
  },
};

export default mlibmsService;
