document.addEventListener('DOMContentLoaded', function () {
    const elements = document.querySelectorAll('.about-hero, .products-highlight, .about-info, .about-image-section, .testimonials, .delivery-info, .contact-info');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    });

    elements.forEach(element => {
        observer.observe(element);
    });

    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            const productId = this.dataset.id;
            const quantity = 1; 

            fetch('php/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `producto_id=${productId}&cantidad=${quantity}`
            })
            .then(response => response.text())
            .then(data => {
                alert(data);  
            })
            .catch(error => console.error('Error:', error));
        });
    });
});
