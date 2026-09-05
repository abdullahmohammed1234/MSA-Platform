import api from '@/services/api';

export const mlibmsAdminService = {
  // Intake & ISBN Workbench
  async lookupIsbn(isbn: string) {
    const response = await api.get('/admin/library/intake/lookup', { params: { isbn } });
    return response.data;
  },

  async storeIntake(payload: any) {
    const response = await api.post('/admin/library/intake/store', payload);
    return response.data;
  },

  // Catalog Books
  async getBooks(params?: Record<string, any>) {
    const response = await api.get('/admin/library/books', { params });
    return response.data;
  },

  async getBook(uuid: string) {
    const response = await api.get(`/admin/library/books/${uuid}`);
    return response.data;
  },

  async updateBook(uuid: string, payload: any) {
    const response = await api.put(`/admin/library/books/${uuid}`, payload);
    return response.data;
  },

  async deleteBook(uuid: string) {
    const response = await api.delete(`/admin/library/books/${uuid}`);
    return response.data;
  },

  // Inventory Copies
  async getCopies(params?: Record<string, any>) {
    const response = await api.get('/admin/library/copies', { params });
    return response.data;
  },

  async createCopy(payload: any) {
    const response = await api.post('/admin/library/copies', payload);
    return response.data;
  },

  async updateCopy(uuid: string, payload: any) {
    const response = await api.put(`/admin/library/copies/${uuid}`, payload);
    return response.data;
  },

  // Members Roster
  async getMembers(params?: Record<string, any>) {
    const response = await api.get('/admin/library/members', { params });
    return response.data;
  },

  async createGuestMember(payload: any) {
    const response = await api.post('/admin/library/members/guest', payload);
    return response.data;
  },

  async updateMember(uuid: string, payload: any) {
    const response = await api.put(`/admin/library/members/${uuid}`, payload);
    return response.data;
  },

  // Loans & Administrative Overrides
  async getLoans(params?: Record<string, any>) {
    const response = await api.get('/admin/library/loans', { params });
    return response.data;
  },

  async overrideReturn(copyBarcode: string) {
    const response = await api.post('/admin/library/loans/override-return', { copy_barcode: copyBarcode });
    return response.data;
  },

  // Reservations
  async getReservations(params?: Record<string, any>) {
    const response = await api.get('/admin/library/reservations', { params });
    return response.data;
  },

  async cancelReservation(uuid: string) {
    const response = await api.post(`/admin/library/reservations/${uuid}/cancel`);
    return response.data;
  },

  // Reports
  async getStats() {
    const response = await api.get('/admin/library/reports/stats');
    return response.data;
  },

  async exportLoansCsv() {
    const response = await api.get('/admin/library/reports/export-loans', { responseType: 'blob' });
    return response.data;
  },

  // Settings
  async getSettings() {
    const response = await api.get('/admin/library/settings');
    return response.data;
  },

  async updateSettings(payload: any) {
    const response = await api.put('/admin/library/settings', payload);
    return response.data;
  },
};

export default mlibmsAdminService;
