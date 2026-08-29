import api from './api'
import type { AxiosResponse } from 'axios'
import type { Product } from './products'

// Cart item interface
export interface CartItem {
  usuario_id: number
  producto_id: number
  cantidad: number
  producto?: Product // Joined product data
}

// Cart response types
export interface CartResponse {
  success: boolean
  data: CartItem[]
  message?: string
}

export interface CartActionResponse {
  success: boolean
  message?: string
}

class CartService {
  // Get user's cart items
  async getCart(): Promise<CartItem[]> {
    try {
      const response: AxiosResponse<CartResponse> = await api.get('/view_cart.php')
      if (response.data.success) {
        return response.data.data
      } else {
        throw new Error(response.data.message || 'Failed to fetch cart')
      }
    } catch (error) {
      console.error('Error fetching cart:', error)
      throw error
    }
  }

  // Add product to cart
  async addToCart(productId: number, quantity: number = 1): Promise<void> {
    try {
      const formData = new FormData()
      formData.append('producto_id', productId.toString())
      formData.append('cantidad', quantity.toString())

      const response: AxiosResponse<CartActionResponse> = await api.post('/add_to_cart.php', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })

      if (!response.data.success) {
        throw new Error(response.data.message || 'Failed to add item to cart')
      }
    } catch (error) {
      console.error('Error adding to cart:', error)
      throw error
    }
  }

  // Remove product from cart
  async removeFromCart(productId: number): Promise<void> {
    try {
      const formData = new FormData()
      formData.append('id', productId.toString())

      const response: AxiosResponse<CartActionResponse> = await api.post('/eliminar_del_carrito.php', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })

      if (!response.data.success) {
        throw new Error(response.data.message || 'Failed to remove item from cart')
      }
    } catch (error) {
      console.error('Error removing from cart:', error)
      throw error
    }
  }

  // Update cart item quantity
  async updateCartItem(productId: number, quantity: number): Promise<void> {
    try {
      // First remove the item, then add it back with new quantity
      await this.removeFromCart(productId)
      if (quantity > 0) {
        await this.addToCart(productId, quantity)
      }
    } catch (error) {
      console.error('Error updating cart item:', error)
      throw error
    }
  }

  // Clear entire cart
  async clearCart(): Promise<void> {
    try {
      const cartItems = await this.getCart()
      const removePromises = cartItems.map(item =>
        this.removeFromCart(item.producto_id)
      )
      await Promise.all(removePromises)
    } catch (error) {
      console.error('Error clearing cart:', error)
      throw error
    }
  }

  // Get cart total
  async getCartTotal(): Promise<number> {
    try {
      const cartItems = await this.getCart()
      return cartItems.reduce((total, item) => {
        if (item.producto) {
          return total + (item.producto.precio * item.cantidad)
        }
        return total
      }, 0)
    } catch (error) {
      console.error('Error calculating cart total:', error)
      throw error
    }
  }

  // Get cart item count
  async getCartItemCount(): Promise<number> {
    try {
      const cartItems = await this.getCart()
      return cartItems.reduce((count, item) => count + item.cantidad, 0)
    } catch (error) {
      console.error('Error getting cart item count:', error)
      throw error
    }
  }
}

export default new CartService()