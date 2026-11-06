export interface MktCategory {
  id: number;
  name: string;
  slug: string;
  parent_id: number | null;
  sort_order: number;
  created_at?: string;
  updated_at?: string;
  children?: MktCategory[];
  parent?: MktCategory;
}

export interface MktProduct {
  id: number;
  name: string;
  sku: string;
  price?: number | null;
  description?: string | null;
  category_id?: number | null;
  category?: {
    id: number;
    name: string;
  };
  categories?: {
    id: number;
    name: string;
  }[];
  active: boolean;
  images_json?: string[];
  variants_json?: {
    name: string;
    values: string[];
  }[];
  created_at?: string;
  updated_at?: string;
}

export interface Marketplace {
  id: number;
  name: string;
  slug: string;
  dealer_email?: string;
  quote_email?: string;
  internal_email?: string;
  logo_url: string | null;
  hero_image_url?: string | null;
  primary_color?: string;
  active: boolean;
  created_at?: string;
  updated_at?: string;
  products?: MktProduct[];
  products_count?: number;
  users_count?: number;
  quotes_count?: number;
  pending_quotes_count?: number;
  latest_quote_at?: string | null;
}

export interface MktQuoteItem {
  id: number;
  quote_id: number;
  product_id: number;
  product_name: string;
  product_sku: string;
  unit_price?: number;
  quantity: number;
  notes: string | null;
  created_at?: string;
  updated_at?: string;
  product?: MktProduct;
}

export interface MktQuote {
  id: number;
  marketplace_id: number;
  quote_number: string;
  buyer_name: string;
  buyer_rut: string;
  buyer_email: string;
  buyer_phone: string;
  company_unit: string;
  address: string | null;
  notes: string | null;
  internal_notes?: string | null;
  status: 'pending' | 'reviewed' | 'responded' | 'closed' | 'rejected' | string;
  created_at?: string;
  updated_at?: string;
  marketplace?: Marketplace;
  items?: MktQuoteItem[];
}

export interface MktSetting {
  id: number;
  key: string;
  value: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface MktUser {
  id: number;
  marketplace_id: number;
  name: string;
  rut: string;
  email?: string | null;
  phone?: string | null;
  company_unit?: string | null;
  cargo?: string | null;
  active: boolean;
  is_admin?: boolean;
  created_at?: string;
  updated_at?: string;
  marketplace?: Marketplace;
}

// Request & Response Types
export interface MarketplaceLoginResponse {
  token: string;
  marketplace: {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
    hero_image_url?: string | null;
    primary_color?: string;
  };
  user?: MktUser | null;
}

export interface PaginatedResponse<T> {
  current_page: number;
  data: T[];
  first_page_url: string;
  from: number;
  last_page: number;
  last_page_url: string;
  links: {
    url: string | null;
    label: string;
    active: boolean;
  }[];
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number;
  total: number;
}

export interface QuoteRequestPayload {
  buyer_name: string;
  buyer_rut: string;
  buyer_email: string;
  buyer_phone: string;
  company_unit: string;
  address?: string;
  notes?: string;
  items: {
    product_id: number;
    quantity: number;
    notes?: string;
  }[];
}

export interface CartItem {
  product: MktProduct;
  quantity: number;
  notes?: string;
  selectedVariant?: {
    name: string;
    value: string;
  };
}
