
let SellersPageHandler = null;

function setupSellersPage(){
    const itemCategorySelect = document.getElementById('item-category');

    if (itemCategorySelect) {
        if(SellersPageHandler === "not null") {
            itemCategorySelect.removeEventListener('change', updateSizeInput);
        }
        SellersPageHandler = "not null";
        itemCategorySelect.addEventListener('change', updateSizeInput);

        document.addEventListener('DOMContentLoaded', updateSizeInput);
    }
}

function updateSizeInput() {
    const categorySelect = document.getElementById('item-category');
    const sizeInputContainer = document.getElementById('size-input-container');
    
    const selectedCategoryValue = categorySelect.value;

    sizeInputContainer.innerHTML = '';

    const label = document.createElement('label');
    label.setAttribute('for', 'item-size');
    label.textContent = 'Size';

    if (selectedCategoryValue === '3') { 
        const input = document.createElement('input');
        input.type = 'number';
        input.id = 'item-size';
        input.name = 'size';
        input.placeholder = 'Enter shoe size (e.g., 10)';
        input.required = true;

        sizeInputContainer.appendChild(label);
        sizeInputContainer.appendChild(input);
    } 
    else if (selectedCategoryValue === '5' || selectedCategoryValue === '7') {
        
    } 
    else if (selectedCategoryValue !== '') { 
        const select = document.createElement('select');
        select.id = 'item-size';
        select.name = 'size';
        select.required = true;

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = '-- Select Size --';
        select.appendChild(defaultOption);

        const sizes = ['XS', 'S', 'M', 'L', 'XL'];
        sizes.forEach(size => {
            const option = document.createElement('option');
            option.value = size;
            option.textContent = size;
            select.appendChild(option);
        });

        sizeInputContainer.appendChild(label);
        sizeInputContainer.appendChild(select);
    }
}

async function loadItemDetailPage(itemId) {
    const contentDiv = document.getElementById('content');
    contentDiv.innerHTML = '<p>Loading item details...</p>'; // Show loading message in the content div

    if (!itemId) {
        alert('No item ID provided. Redirecting to home page.');
        window.location.hash = 'home';
    }

    try {

        const response = await fetch(`php/items/${itemId}`)
        const data = await response.json();

        if (data.status === 'success' && data.item) {
            const item = data.item;

            const categoryDisplayName = item.category_name
                ? item.category_name.charAt(0).toUpperCase() + item.category_name.slice(1)
                : 'Unknown Category';

            const brandedStatus = (item.brand && item.brand.toLowerCase() === 'yes') ? 'Yes' : 'No';

            // Dynamically create the HTML for the item detail page
            contentDiv.innerHTML = `
                <div class="item-detail-spa-container">
                    <div class="detail-card">
                        <img src="${item.image_url}" alt="${item.name}" class="detail-image">
                        <div class="detail-info">
                            <h1 class="detail-name">${item.name}</h1>
                            <p class="detail-price">$${parseFloat(item.price).toFixed(2)}</p>
                            <p class="detail-condition">Condition: <span>${item.item_condition}</span></p>
                            <p class="detail-category">Category: <span>${categoryDisplayName}</span></p>
                            <p class="detail-seller">Seller: <span>${item.seller_username}</span></p>
                            <p class="detail-branded">Branded: <span>${brandedStatus}</span></p>
                            ${item.size ? `<p class="detail-size">Size: <span>${item.size}</span></p>` : ''}
                            <p class="detail-description">${item.description}</p>
                            <button class="add-to-cart-btn" data-item-id="${item.id}">Add To Cart</button>
                        </div>
                    </div>
                </div>
            `;

            // Attach event listener for the "Add To Cart" button on the detail page
            document.querySelector('.add-to-cart-btn').addEventListener('click', (event) => {
                const itemId = event.target.dataset.itemId;
                addToCart(itemId);
            });

        } else {
            contentDiv.innerHTML = `<p class="no-items-message">Error: ${data.message || 'Item details could not be loaded.'}</p>`;
        }
    } catch (error) {
        console.error('Error fetching item details:', error);
        contentDiv.innerHTML = '<p class="error-message">Failed to load item details. Please check your network connection or try again later.</p>';
    }
}

