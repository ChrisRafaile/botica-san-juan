import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import cartService from '@/services/cart'
import type { CartItem } from '@/services/cart'

export const useCartStore = defineStore('cart', () => {
  const items = ref<CartItem[]>([])
  const isLoading = ref(false)

  const totalItems = computed(() => {
    return items.value.reduce((total, item) => total + item.cantidad, 0)
  })

  const totalPrice = computed(() => {
    return items.value.reduce((total, item) => {
      if (item.producto) {
        return total + (item.producto.precio * item.cantidad)
      }
      return total
    }, 0)
  })

  const loadCart = async () => {
    try {
      isLoading.value = true
      const cartItems = await cartService.getCart()
      items.value = cartItems
    } catch (error) {
      console.error('Error loading cart:', error)
      items.value = []
    } finally {
      isLoading.value = false
    }
  }

  const addItem = async (productId: number, quantity: number = 1) => {
    try {
      isLoading.value = true
      await cartService.addToCart(productId, quantity)
      await loadCart() // Reload cart to get updated data
    } catch (error) {
      console.error('Error adding item to cart:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const removeItem = async (productId: number) => {
    try {
      isLoading.value = true
      await cartService.removeFromCart(productId)
      await loadCart() // Reload cart to get updated data
    } catch (error) {
      console.error('Error removing item from cart:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const updateQuantity = async (productId: number, quantity: number) => {
    try {
      isLoading.value = true
      if (quantity <= 0) {
        await cartService.removeFromCart(productId)
      } else {
        await cartService.updateCartItem(productId, quantity)
      }
      await loadCart() // Reload cart to get updated data
    } catch (error) {
      console.error('Error updating cart item:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const clearCart = async () => {
    try {
      isLoading.value = true
      await cartService.clearCart()
      items.value = []
    } catch (error) {
      console.error('Error clearing cart:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const isInCart = (productId: number) => {
    return items.value.some(item => item.producto?.id === productId)
  }

  return {
    items,
    isLoading,
    totalItems,
    totalPrice,
    loadCart,
    addItem,
    removeItem,
    updateQuantity,
    clearCart,
    isInCart
  }
})
