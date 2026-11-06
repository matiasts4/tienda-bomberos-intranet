// frontend/services/api/marketplaceAdmin.ts
"use client"

import { api } from "../../lib/api";
import { Marketplace, MktProduct, MktCategory, MktQuote, PaginatedResponse, MktSetting } from "../../types/marketplace";

const BASE_PATH = "/api/admin/marketplace";

export const marketplaceAdminService = {
  // Marketplaces
  getMarketplaces: () => api.get<Marketplace[]>(`${BASE_PATH}/marketplaces`),
  getMarketplace: (id: number) => api.get<Marketplace>(`${BASE_PATH}/marketplaces/${id}`),
  createMarketplace: (data: Partial<Marketplace> & { password?: string }) => api.post<Marketplace>(`${BASE_PATH}/marketplaces`, data),
  updateMarketplace: (id: number, data: Partial<Marketplace> & { password?: string }) => api.put<Marketplace>(`${BASE_PATH}/marketplaces/${id}`, data),
  toggleMarketplace: (id: number) => api.del<Marketplace>(`${BASE_PATH}/marketplaces/${id}`),
  syncProducts: (id: number, product_ids: number[]) => api.post<{ message: string }>(`${BASE_PATH}/marketplaces/${id}/sync-products`, { product_ids }),

  // Products
  getProducts: (params?: Record<string, any>) => {
    const query = params ? `?${new URLSearchParams(params).toString()}` : '';
    return api.get<PaginatedResponse<MktProduct>>(`${BASE_PATH}/products${query}`);
  },
  createProduct: (data: Partial<MktProduct>) => api.post<MktProduct>(`${BASE_PATH}/products`, data),
  updateProduct: (id: number, data: Partial<MktProduct>) => api.put<MktProduct>(`${BASE_PATH}/products/${id}`, data),
  deleteProduct: (id: number) => api.del<MktProduct>(`${BASE_PATH}/products/${id}`),
  
  importProductsExcel: (file: File) => {
    const formData = new FormData();
    formData.append("file", file);
    return api.post<{ message: string }>(`${BASE_PATH}/products/import-excel`, formData);
  },
  uploadImagesZip: (file: File) => {
    const formData = new FormData();
    formData.append("file", file);
    return api.post<{ files: string[] }>(`${BASE_PATH}/products/upload-images-zip`, formData);
  },
  assignImages: (productId: number, images: string[]) => 
    api.post<{ message: string, product: MktProduct }>(`${BASE_PATH}/products/${productId}/assign-images`, { images }),

  // Categories
  getCategories: () => api.get<MktCategory[]>(`${BASE_PATH}/categories`),
  createCategory: (data: Partial<MktCategory>) => api.post<MktCategory>(`${BASE_PATH}/categories`, data),
  updateCategory: (id: number, data: Partial<MktCategory>) => api.put<MktCategory>(`${BASE_PATH}/categories/${id}`, data),
  deleteCategory: (id: number) => api.del<MktCategory>(`${BASE_PATH}/categories/${id}`),

  // Quotes
  getQuotes: (params?: Record<string, any>) => {
    const query = params ? `?${new URLSearchParams(params).toString()}` : '';
    return api.get<PaginatedResponse<MktQuote>>(`${BASE_PATH}/quotes${query}`);
  },
  getQuote: (id: number) => api.get<MktQuote>(`${BASE_PATH}/quotes/${id}`),

  // Settings
  getSettings: () => api.get<MktSetting[]>(`${BASE_PATH}/settings`),
  updateSettings: (keys: Record<string, string>) => api.put<{ message: string }>(`${BASE_PATH}/settings`, { keys }),
};
