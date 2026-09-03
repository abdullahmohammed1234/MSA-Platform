import client from '@/services/api';
import type { StoreProduct, StoreCart, StoreOrder } from '@/types/store';

export const storeService = {
  // Public Catalogue
  async getPublicProducts(params?: Record<string, any>): Promise<{ data: StoreProduct[]; meta: any }> {
    const res = await client.get<any>('/store/products', { params });
    return res.data;
  },

  async getPublicProductBySlug(slug: string): Promise<StoreProduct> {
    const res = await client.get<{ data: StoreProduct }>(`/store/products/${slug}`);
    return res.data.data;
  },

  // Cart
  async getCart(): Promise<StoreCart> {
    const res = await client.get<{ data: StoreCart }>('/store/cart');
    return res.data.data;
  },

  async addToCart(productId: number, variantId?: number | null, quantity: number = 1): Promise<StoreCart> {
    const res = await client.post<{ data: StoreCart }>('/store/cart/items', {
      product_id: productId,
      variant_id: variantId,
      quantity,
    });
    return res.data.data;
  },

  async updateCartItem(itemUuid: string, quantity: number): Promise<StoreCart> {
    const res = await client.patch<{ data: StoreCart }>(`/store/cart/items/${itemUuid}`, {
      quantity,
    });
    return res.data.data;
  },

  async removeCartItem(itemUuid: string): Promise<StoreCart> {
    const res = await client.delete<{ data: StoreCart }>(`/store/cart/items/${itemUuid}`);
    return res.data.data;
  },

  // Checkout
  async checkout(payload: {
    customer_name: string;
    customer_email: string;
    customer_phone?: string;
    notes?: string;
    redirect_url?: string;
  }): Promise<{ order: StoreOrder; checkout_url: string }> {
    const res = await client.post<{ data: { order: StoreOrder; checkout_url: string } }>('/store/checkout', payload);
    return res.data.data;
  },

  // Customer Orders
  async getMyOrders(): Promise<{ data: StoreOrder[]; meta: any }> {
    const res = await client.get<any>('/store/orders');
    return res.data;
  },

  async getMyOrder(uuid: string): Promise<StoreOrder> {
    const res = await client.get<{ data: StoreOrder }>(`/store/orders/${uuid}`);
    return res.data.data;
  },

  // Admin Operations
  async getAdminProducts(params?: Record<string, any>): Promise<{ data: StoreProduct[]; meta: any }> {
    const res = await client.get<any>('/store-admin/products', { params });
    return res.data;
  },

  async createAdminProduct(payload: any): Promise<StoreProduct> {
    const res = await client.post<{ data: StoreProduct }>('/store-admin/products', payload);
    return res.data.data;
  },

  async updateAdminProduct(uuid: string, payload: any): Promise<StoreProduct> {
    const res = await client.put<{ data: StoreProduct }>(`/store-admin/products/${uuid}`, payload);
    return res.data.data;
  },

  async deleteAdminProduct(uuid: string): Promise<void> {
    await client.delete(`/store-admin/products/${uuid}`);
  },

  async getAdminInventory(params?: Record<string, any>): Promise<{ data: any[]; meta: any }> {
    const res = await client.get<any>('/store-admin/inventory', { params });
    return res.data;
  },

  async adjustInventory(payload: { product_id: number; variant_id?: number | null; new_quantity: number; reason?: string }): Promise<void> {
    await client.post('/store-admin/inventory/adjust', payload);
  },

  async getAdminOrders(params?: Record<string, any>): Promise<{ data: StoreOrder[]; meta: any }> {
    const res = await client.get<any>('/store-admin/orders', { params });
    return res.data;
  },

  async updateFulfillmentStatus(uuid: string, status: string): Promise<StoreOrder> {
    const res = await client.patch<{ data: StoreOrder }>(`/store-admin/orders/${uuid}/fulfillment`, {
      fulfillment_status: status,
    });
    return res.data.data;
  },

  async refundAdminOrder(uuid: string, reason?: string): Promise<StoreOrder> {
    const res = await client.post<{ data: StoreOrder }>(`/store-admin/orders/${uuid}/refund`, {
      reason,
    });
    return res.data.data;
  },
};

export default storeService;
