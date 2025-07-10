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

// NEW FUNCTION: Add event listeners to the "View Item" buttons on the home page
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
            event.preventDefault(); // Prevent default button behavior
            const itemId = event.target.dataset.itemId;
            addToCart(itemId);
        });
    });
}


let cart = {};

async function addToCart(itemId) {
    if (cart[itemId]) {
        console.log(`Item ${itemId} is already in the cart. Quantity fixed at 1.`);
        // Optionally, alert the user here
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
    }
}

/**
 * Removes an item from the cart.
 * @param {string} itemId The ID of the item to remove.
 */
function removeFromCart(itemId) {
    if (cart[itemId]) {
        delete cart[itemId];
        console.log(`Item ${itemId} removed from cart.`);
        renderCart(); // Re-render the cart display
    }
}

function getCartItemCount() {
    return Object.values(cart).reduce((total, item) => total + item.quantity, 0);
}


/**
 * Renders the cart content into the cart-container.
 */
async function renderCart() {
    const cartContainer = document.getElementById('content'); // Assuming 'content' is your main SPA div
    // Make sure cart-container div is present in your html/cart.html
    // If you plan to load html/cart.html, you'd target an inner div
    // For now, let's assume we replace #content directly with the cart HTML.

    let cartHtml = `
        <div class="cart-page-wrapper">
            <h1>Your Cart (<span id="cart-total-items">${getCartItemCount()}</span> Items)</h1>
            <div class="cart-items-list">
    `;

    const itemIdsInCart = Object.keys(cart);

    if (itemIdsInCart.length === 0) {
        cartHtml += '<p class="empty-cart-message">Your cart is empty.</p>';
    } else {
        let cartTotal = 0;
        for (const itemId of itemIdsInCart) {
            const item = cart[itemId];
            cartTotal += item.price;

            cartHtml += `
                <div class="cart-item" data-item-id="${item.id}">
                    <img src="${item.imageUrl}" alt="${item.name}" class="cart-item-image">
                    <div class="cart-item-details">
                        <h3>${item.name}</h3>
                        <p>Price: $${item.price.toFixed(2)}</p>
                    </div>
                    <button class="remove-from-cart-btn" data-item-id="${item.id}">Remove</button>
                </div>
            `;
        }
        cartHtml += `</div> <div class="checkout">
                <h3>Total: $<span id="cart-total-price">${cartTotal.toFixed(2)}</span></h3>
                <button class="checkout-btn" id="checkout-btn" onclick="sellItemsInCart()">Checkout</button>
            </div>
        </div> `;
    }

    cartContainer.innerHTML = cartHtml;
    setTimeout(() => {removeItemFromCartListeners()},100); // Allow DOM to update before adding listeners
    
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
    const itemIds = Object.keys(cart);
    if (itemIds.length === 0) {
        alert('Your cart is empty. Please add items to your cart before proceeding.');
        return;
    }

    try {
        const response = await fetch('php/items/sell', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json' // Essential for Flight to parse data correctly
            },
            body: JSON.stringify({ item_ids: itemIds }) // Send the array inside an object
        });

        const data = await response.json();

        if (data.status === 'success') {
            alert(data.message);
            // ... (handle UI update, e.g., redirect to home or re-render list) ...
            window.location.hash = '#home';
            cart = {};
        } else {
            alert('Error: ' + data.message);
            // ... (error handling) ...
        }
    } catch (error) {
        // ... (network error handling) ...
    }
}