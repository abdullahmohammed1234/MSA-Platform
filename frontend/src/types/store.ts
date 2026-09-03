export type ProductStatus = 'draft' | 'active' | 'inactive' | 'archived';
export type StorePaymentStatus = 'pending' | 'paid' | 'failed' | 'refunded' | 'partially_refunded';
export type StoreFulfillmentStatus = 'pending' | 'preparing' | 'ready_for_pickup' | 'completed' | 'cancelled';

export interface ProductVariant {
  id: number;
  uuid: string;
  name: string;
  sku?: string | null;
  price_override_cents?: number | null;
  effective_price_cents: number;
  formatted_price: string;
  inventory_quantity: number;
  is_active: boolean;
}

export interface ProductImage {
  uuid: string;
  image_url: string;
  is_primary: boolean;
  display_order: number;
}

export interface StoreProduct {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  description?: string | null;
  price_cents: number;
  formatted_price: string;
  currency: string;
  sku?: string | null;
  status: ProductStatus;
  status_label: string;
  has_variants: boolean;
  inventory_quantity: number;
  primary_image_url?: string | null;
  images: ProductImage[];
  variants: ProductVariant[];
  created_at: string;
  updated_at: string;
}

export interface CartItem {
  uuid: string;
  product_id: number;
  variant_id?: number | null;
  product_name: string;
  product_slug: string;
  variant_name?: string | null;
  sku?: string | null;
  unit_price_cents: number;
  formatted_unit_price: string;
  quantity: number;
  line_total_cents: number;
  formatted_line_total: string;
  image_url?: string | null;
  max_available: number;
}

export interface StoreCart {
  uuid: string;
  subtotal_cents: number;
  formatted_subtotal: string;
  items: CartItem[];
  item_count: number;
}

export interface StoreOrderItem {
  uuid: string;
  product_name: string;
  variant_name?: string | null;
  sku?: string | null;
  unit_price_cents: number;
  formatted_unit_price: string;
  quantity: number;
  line_total_cents: number;
  formatted_line_total: string;
}

export interface StoreOrder {
  id: number;
  uuid: string;
  order_number: string;
  customer_name: string;
  customer_email: string;
  customer_phone?: string | null;
  subtotal_cents: number;
  tax_cents: number;
  total_cents: number;
  formatted_total: string;
  currency: string;
  payment_status: StorePaymentStatus;
  payment_status_label: string;
  fulfillment_status: StoreFulfillmentStatus;
  fulfillment_status_label: string;
  square_payment_id?: string | null;
  square_order_id?: string | null;
  square_checkout_url?: string | null;
  notes?: string | null;
  paid_at?: string | null;
  fulfilled_at?: string | null;
  created_at: string;
  items: StoreOrderItem[];
}
