import api from './api'
import type { AxiosResponse } from 'axios'

// Product interface based on PHP backend structure
export interface Product {
  id: number
  nombre: string
  concentracion: string
  adicional: string
  laboratorio: string
  presentacion: string
  tipo: string
  stock: number
  precio: number
  imagen: string
}

class ProductsService {
  // Get all products
  async getAllProducts(): Promise<Product[]> {
    try {
      const response: AxiosResponse<Product[]> = await api.get('/productos')
      return response.data
    } catch (error) {
      console.error('Error fetching products:', error)
      throw error
    }
  }

  // Get product by ID
  async getProductById(id: number): Promise<Product> {
    try {
      const response: AxiosResponse<Product> = await api.get(`/productos/${id}`)
      return response.data
    } catch (error) {
      console.error('Error fetching product:', error)
      throw error
    }
  }

  // Search products by query
  async searchProducts(query: string): Promise<Product[]> {
    try {
      const response: AxiosResponse<Product[]> = await api.get(`/productos?search=${encodeURIComponent(query)}`)
      return response.data
    } catch (error) {
      console.error('Error searching products:', error)
      throw error
    }
  }

  // Filter products by type
  async getProductsByType(type: string): Promise<Product[]> {
    try {
      const response: AxiosResponse<Product[]> = await api.get(`/productos?tipo=${encodeURIComponent(type)}`)
      return response.data
    } catch (error) {
      console.error('Error filtering products:', error)
      throw error
    }
  }

  // Get products by laboratory
  async getProductsByLaboratory(laboratory: string): Promise<Product[]> {
    try {
      const response: AxiosResponse<Product[]> = await api.get(`/productos?laboratorio=${encodeURIComponent(laboratory)}`)
      return response.data
    } catch (error) {
      console.error('Error filtering products by laboratory:', error)
      throw error
    }
  }
}

export default new ProductsService()