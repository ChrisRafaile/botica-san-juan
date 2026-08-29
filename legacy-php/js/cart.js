let cart = [];

function addToCart(product) {
    cart.push(product);
    updateCart();
}

function updateCart() {
    const cartItems = document.getElementById('cart-items');
    cartItems.innerHTML = '';
    cart.forEach(item => {
        const li = document.createElement('li');
        li.textContent = `${item.name} - $${item.price}`;
        cartItems.appendChild(li);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', () => {
            const product = {
                name: button.getAttribute('data-name'),
                price: parseFloat(button.getAttribute('data-price')).toFixed(2)
            };
            addToCart(product);
        });
    });
});

function checkout() {
    alert('Gracias por su compra!');
    cart = [];
    updateCart();
}
