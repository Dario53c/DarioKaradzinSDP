// --- Category ID to Name Mapping (from previous step) ---
const categoryMap = {
    1: 'shirts',
    2: 'pants',
    3: 'shoes',
    4: 'jackets',
    5: 'accessories',
    6: 'dresses',
    7: 'hats',
    8: 'sportswear'
};

// Function to show or filter items by category (no change needed here)
function filterCategory(category) {
    const itemCards = document.querySelectorAll('.item-card'); 
    itemCards.forEach(card => {
        const itemCategoryName = card.dataset.categoryName; 
        card.style.display = (category === 'all' || itemCategoryName === category) ? 'flex' : 'none';
    });
}

function setupHomeLink() {
    setupHomePage();
}

// Function to fetch and display items (MODIFIED)
async function setupHomePage() {
    const contentDiv = document.getElementById('content');
    contentDiv.innerHTML = `
    <div id="home_title" class="home_title">
    <h1 >Welcome to Fits n' Finds</h1>
</div>


        <!-- Category filter buttons -->
        <div id="category-buttons">
            <button onclick="filterCategory('all')">All</button>
            <button onclick="filterCategory('shirts')">Shirts</button>
            <button onclick="filterCategory('pants')">Pants</button>
            <button onclick="filterCategory('shoes')">Shoes</button>
            <button onclick="filterCategory('jackets')">Jackets</button>
            <button onclick="filterCategory('accessories')">Accessories</button>
            <button onclick="filterCategory('dresses')">Dresses</button>
            <button onclick="filterCategory('hats')">Hats</button>
            <button onclick="filterCategory('sportswear')">Sportswear</button>
            <input type="text" id="search-input" class="searchBySeller" placeholder="Search By Seller..." oninput="filterItemsBySeller()">    
        </div>

        <!-- Items container -->
        <div id="item-list-container" class="item-list-container">

        </div>
    `; // Show loading message in the content div
    const homeLink = document.getElementById('home-link');
    if (homeLink) {
        homeLink.removeEventListener('click', setupHomeLink);
        homeLink.addEventListener('click', setupHomeLink);
    }
    const itemListContainer = document.getElementById('item-list-container');
    itemListContainer.innerHTML = 'Loading items...';

    try {
        // Updated fetch path to directly use 'php/items' based on previous context
        const response = await fetch('php/items');
        const data = await response.json();

        if (data.status === 'success' && data.items && data.items.length > 0) {
            itemListContainer.innerHTML = '';

            data.items.forEach(item => {
                const itemCard = document.createElement('div');
                itemCard.classList.add('item-card');

                const categoryName = categoryMap[item.category_id] || 'unknown';
                itemCard.dataset.categoryName = categoryName;

                const brandedStatus = (item.brand && item.brand.toLowerCase() === 'yes') ? 'Yes' : 'No';

                itemCard.innerHTML = `
                    <img src="${item.image_url}" alt="${item.name}">
                    <div class="item-details">
                        <h3 class="item-name">${item.name}</h3>
                        <p class="item-price">$${parseFloat(item.price).toFixed(2)}</p>
                        <p class="item-condition">Condition: ${item.item_condition}</p>
                        <p class="item-category">Category: ${categoryName.charAt(0).toUpperCase() + categoryName.slice(1)}</p>
                        <p class="item-seller">Seller: ${item.username}</p>
                        ${item.size ? `<p class="item-size">Size: ${item.size}</p>` : ''}
                    </div>
                    <button class="view-item-button" data-item-id="${item.id}">View Item</button>
                    <button class="add-to-cart-home-btn" data-item-id="${item.id}">Add to Cart</button>
                `;
                itemListContainer.appendChild(itemCard);
            });

            // NEW: Call the function to add event listeners to the new buttons
            addHomeViewItemButtonListeners();

        } else {
            itemListContainer.innerHTML = '<p class="no-items-message">No items found.</p>';
        }
    } catch (error) {
        console.error('Error fetching items:', error);
        itemListContainer.innerHTML = '<p class="error-message">Failed to load items. Please try again later.</p>';
    }
}


async function addHomeViewItemButtonListeners() {
    // Listener for 'View Item' buttons
    document.querySelectorAll('.view-item-button').forEach(button => {
        button.addEventListener('click', (event) => {
            const itemId = event.target.dataset.itemId;
            loadItemDetailPage(itemId); 
        });
    });

    // Listener for 'Add to Cart' buttons (from a previous request)
    document.querySelectorAll('.add-to-cart-home-btn').forEach(button => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const itemId = event.target.dataset.itemId;
            addToCart(itemId);
        });
    });
}

function filterItemsBySeller() {
    const searchInput = document.getElementById('search-input').value.toLowerCase();
    const itemCards = document.querySelectorAll('.item-card');

    itemCards.forEach(card => {
        const sellerName = card.querySelector('.item-seller').innerText.toLowerCase();
        card.style.display = sellerName.includes(searchInput) ? 'flex' : 'none';
    });
}

// let cart = {};

async function addToCart(itemId) {
    /*if (cart[itemId]) {
        console.log(`Item ${itemId} is already in the cart. Quantity fixed at 1.`);
        alert('This item is already in your cart.');
        return;
    }

    try {
        // Fetch item details to store comprehensive info in the cart
        // Assuming your 'php/items/${itemId}' endpoint provides all necessary details.
        const response = await fetch(`php/items/${itemId}`);
        const data = await response.json();

        if (data.status === 'success' && data.item) {
            const item = data.item;
            // Add the item to the cart object with a fixed quantity of 1
            cart[itemId] = {
                id: item.id, // Or item.item_id, ensure consistency with your PHP data
                name: item.name,
                price: parseFloat(item.price), // Store as a number for calculations
                imageUrl: item.image_url,
                quantity: 1 // Fixed at 1 as per your requirement
            };
            console.log(`Item ${item.name} (ID: ${itemId}) added to cart.`);
            alert(`"${item.name}" added to cart!`); // User feedback
        } else {
            console.error('Failed to add item to cart: Item details not found.', data.message);
            alert('Could not add item to cart. Item details not found.');
        }
    } catch (error) {
        console.error('Error adding item to cart:', error);
        alert('Error adding item to cart. Please try again.');
    }*/

    try {
        const response = await fetch('php/items/putItemInCart', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ item_id: itemId })
        });

        // 1. Check for a successful HTTP status code (e.g., 200, 201)
        if (!response.ok) {
            // If the status is not in the 2xx range, throw an error with the status
            const errorData = await response.json();
            throw new Error(`Server responded with status: ${response.status} - ${errorData.message}`);
        }

        // 2. Parse the JSON response
        const data = await response.json();

        // 3. Check the application-specific status from the JSON response
        if (data.status === 'success') {
            alert('Item successfully added to cart!');
            console.log(data.message);
            // You might want to update your cart UI here
        } else {
            // Handle application-level errors (e.g., item already in cart, not found)
            throw new Error(data.message || 'An unknown error occurred.');
        }

    } catch (error) {
        // 4. Catch any network errors or errors thrown above
        console.error('Failed to add item to cart:', error);
        alert(`Failed to add item to cart: ${error.message}`);
    }
}

/**
 * Removes an item from the cart.
 * @param {string} itemId The ID of the item to remove.
 */


function getCartItemCount() {
    return Object.values(cart).reduce((total, item) => total + item.quantity, 0);
}


/**
 * Renders the cart content into the cart-container.
 */
async function renderCart() {
    const cartContainer = document.getElementById('content');
    cartContainer.innerHTML = '<h2>Loading cart...</h2>';

    try {
        const response = await fetch('php/items/cart');
        const data = await response.json();

        if (!response.ok) {
            cartContainer.innerHTML = `<p class="error-message">${data.message}</p>`;
            return;
        }

        const cartItems = data.cart_items; 

        if (!cartItems || cartItems.length === 0) {
            cartContainer.innerHTML = `
                <div class="cart-page-wrapper">
                    <h1>Your Cart (0 Items)</h1>
                    <p class="empty-cart-message">Your cart is empty.</p>
                </div>
            `;
            return;
        }

        let cartTotal = 0;
        let cartHtml = `
            <div class="cart-page-wrapper">
                <h1>Your Cart (${cartItems.length} Items)</h1>
                <div class="cart-items-list">
        `;

        for (const item of cartItems) {
            cartTotal += parseFloat(item.price);
            cartHtml += `
                <div class="cart-item" data-item-id="${item.id}">
                    <img src="${item.image_url}" alt="${item.name}" class="cart-item-image">
                    <div class="cart-item-details">
                        <h3>${item.name}</h3>
                        <p>Price: $${parseFloat(item.price).toFixed(2)}</p>
                    </div>
                    <button class="remove-from-cart-btn" data-item-id="${item.id}">Remove</button>
                </div>
            `;
        }
        
        cartHtml += `</div>
            <div class="checkout">
                <h3>Total: $<span id="cart-total-price">${cartTotal.toFixed(2)}</span></h3>
                <button class="checkout-btn" id="checkout-btn" onclick="sellItemsInCart()">Checkout</button>
            </div>
            
            <div class="overlay"></div>
            <div id="payment-box" class="payment-form">
                <h1 id="payment-load">Loading Payment Form...</h1>
                
            <form id="payment-form-content" style="display: none;">
                <div id="address-details">
                    <h3>Shipping Address</h3>
                    <input type="text" id="name-input" placeholder="Full Name" required>
                    <input type="text" id="street-input" placeholder="Street Address" required>
                    <input type="text" id="city-input" placeholder="City" required>
                    <input type="text" id="state-input" placeholder="State/Province">
                    <input type="text" id="zip-input" placeholder="Postal/Zip Code" required>
                </div>

                <div id="payment-element"></div>
                <div id="payment-message" role="alert"></div>
                <button id="submit-button" type="submit">Pay</button>
                
            </form>
            </div>

        </div>`;

        cartContainer.innerHTML = cartHtml;
        setTimeout(() => {removeItemFromCartListeners()},100); 

    } catch (error) {
        console.error('Error fetching cart items:', error);
        cartContainer.innerHTML = `<p class="error-message">Failed to load cart. Please try again.</p>`;
    }
}

function removeFromCart(itemId) {
    fetch(`php/items/removeItemFromCart/${itemId}`, {
        method: 'DELETE'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Failed to remove item from cart.');
        }
        return response.json();
    })
    .then(data => {
        if (data.status === 'success') {
            alert('Item removed from cart successfully.');
            renderCart(); // Re-render the cart to reflect changes
        } else {
            throw new Error(data.message || 'An unknown error occurred.');
        }
    })
    .catch(error => {
        console.error('Error removing item from cart:', error);
        alert(`Error: ${error.message}`);
    });
}

function removeItemFromCartListeners() {

    const removeButtons = document.querySelectorAll('.remove-from-cart-btn');
    removeButtons.forEach(button => {
        const itemId = button.dataset.itemId;
        button.removeEventListener('click', () => removeFromCart(itemId));
        button.addEventListener('click', () => {
            removeFromCart(itemId);
        });
    });
}



async function sellItemsInCart() {
    // 1. Get all item IDs from the rendered cart
    const cartItems = document.querySelectorAll('.cart-item');
    const itemIds = Array.from(cartItems).map(item => item.dataset.itemId);

    if (itemIds.length === 0) {
        alert('Your cart is empty. Please add items to checkout.');
        return;
    }

    // 2. Get the total price from the rendered cart
    const totalPriceElement = document.getElementById('cart-total-price');
    const totalPrice = totalPriceElement ? parseFloat(totalPriceElement.innerText) : 0;
    const amountInCents = Math.round(totalPrice * 100);

    localStorage.setItem('checkoutData', JSON.stringify({ itemIds, totalPrice }));

    await createAndMountPaymentForm(amountInCents);
    

}

async function createAndMountPaymentForm(amountInCents) {
    const paymentBox = document.getElementById('payment-box');
    const paymentLoader = document.getElementById('payment-load');
    const paymentFormContent = document.getElementById('payment-form-content');
    const overlay = document.querySelector('.overlay');

    // Show the box and the overlay immediately
    paymentBox.style.display = 'flex';
    paymentLoader.style.display = 'flex';
    overlay.style.display = 'block';

    const stripe = Stripe('pk_test_51RyAtVDwPzptKpdZYej9wczxCosCsAQJTxvVDWKTvNercrvIG80BkdDdhaL8bTutmYXlqBU2ATDEjGtaMiDc5PP0009Qt80ZE1');

    try {
        // ... (Your existing code to fetch clientSecret) ...
        const response = await fetch('php/create-payment-intent', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ amount: amountInCents }),
        });
        const { clientSecret } = await response.json();

        // Hide the loading message and show the form after the clientSecret is received
        paymentLoader.style.display = 'none';
        paymentFormContent.style.display = 'block'; // Make the inner form block or flex

        // ... (Your existing Stripe initialization and form submission code) ...
        const elements = stripe.elements({ clientSecret });
        const paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');

        const form = document.getElementById('payment-form-content');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Collect shipping details from the inputs
            const shippingDetails = {
                name: document.getElementById('name-input').value,
                address: {
                    street: document.getElementById('street-input').value,
                    city: document.getElementById('city-input').value,
                    state: document.getElementById('state-input').value,
                    zip: document.getElementById('zip-input').value,
                },
            };
            
            // Retrieve the existing data and update it with shipping details
            const checkoutData = JSON.parse(localStorage.getItem('checkoutData'));
            checkoutData.shippingDetails = shippingDetails;
            localStorage.setItem('checkoutData', JSON.stringify(checkoutData));
            
            // Submit the payment to Stripe
            const { error: submitError } = await elements.submit();
            
            if (submitError) {
                const messageContainer = document.querySelector('#payment-message');
                messageContainer.textContent = submitError.message;
                return;
            }

            // After successful submission, confirm the payment
            const baseURL = window.location.origin;
            const returnPath = baseURL.includes('localhost') ? '/Fits_n_Finds%20-%20Copy/#success' : '/#success';
            const returnUrl = `${baseURL}${returnPath}`;

            const { error } = await stripe.confirmPayment({
                elements,
                clientSecret,
                confirmParams: {
                    return_url: returnUrl,
                },
            });

            if (error) {
                const messageContainer = document.querySelector('#payment-message');
                messageContainer.textContent = error.message;
            }
        });

    } catch (error) {
        console.error('Failed to initialize payment:', error);
        paymentBox.style.display = 'none'; // Hide the entire box on error
        overlay.style.display = 'none'; // Hide the overlay as well
        alert('An error occurred while preparing the payment. Please try again.');
    }
}

async function setupSuccessfulTransactionPage() {
    const checkoutData = JSON.parse(localStorage.getItem('checkoutData'));
    console.log('Updated checkoutData:', checkoutData);
    if (!checkoutData) {
        console.error('Checkout data not found in local storage.');
        // Handle error, maybe redirect to cart page
        return;
    }

    // Extract the item IDs, total price, and shipping details
    const { itemIds, totalPrice, shippingDetails } = checkoutData;

    try {
        const response = await fetch('php/items/sell', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                item_ids: itemIds, 
                total_price: totalPrice,
                shipping_details: shippingDetails
            }),
        });

        const data = await response.json();

        if (response.ok) {
            alert('Thank you for your purchase!');
            renderCart(); 
        } else {
            alert(`Error during checkout: ${data.message}`);
            console.error('Checkout error:', data.message);
        }

    } catch (error) {
        console.error('Failed to complete checkout:', error);
        alert('An unexpected error occurred during checkout. Please try again.');
    }
}