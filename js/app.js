// Function to render content based on the current route
function renderPage() {
    const hash = window.location.hash.substring(1) || "home"; // default to home
    loadPage(hash);

    const navbar = document.getElementById('navbar');

    if(hash === "cart" || hash === "post_item") {
        fetch('php/user/status')
            .then(response => response.json())
            .then(data => {
                if (!data.loggedIn) {
                    window.location.hash = "login";
                    alert("Please log in to view this page.");
                }
            });
    }

    if (hash === "register" || hash === "login") {
        navbar.style.display = "none";
    } else {
        navbar.style.display = "block";
        updateNavbarForUser(); 
    }
}

async function loadPage(page) {
    try {
        const response = await fetch(`html/${page}.html`);
        if (!response.ok) throw new Error('Page not found');

        const html = await response.text();
        document.getElementById('content').innerHTML = html;

        if (page === 'login' || page === 'register') {
            attachFormHandlers();
        }
        else if (page === 'post_item') {
            setupSellersPage(); 

            setTimeout(() => {
                attachFormHandlers();
            }, 100);
        }
        else if(page === 'home'){
            setupHomePage();
        }
        else if (page === 'cart') {
            renderCart(); // Ensure cart is rendered when the cart page is loaded
        }
    } catch (error) {
        document.getElementById('content').innerHTML = "<h1>404 - Page Not Found</h1>";
    }
}

// Navbar animation
let navbar = document.getElementById('navbar');

// Shrink on scroll
window.addEventListener('scroll', () => {

  if (window.scrollY > 100) {
    navbar.classList.add('scrolled');
  } else {
    navbar.classList.remove('scrolled');
  }
});

// Listen for changes in the URL hash
window.addEventListener('hashchange', renderPage);

// On page load
window.addEventListener('load', () => {
    renderPage();
    filterCategory('all');
});



function attachFormHandlers() {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', event => {
            event.preventDefault();

            const email = document.getElementById('loginEmail').value;
            const password = document.getElementById('loginPassword').value;

            // Prepare data as a JavaScript object
            const loginData = {
                email: email,       // Use 'email' to match backend's expected JSON key
                password: password  // Use 'password' to match backend's expected JSON key
            };

            const passwordText = document.getElementById('password_text_login');
            
            // Send JSON data to the /login endpoint
            fetch('php/login', { // Changed endpoint to /login
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json' // Essential header for JSON requests
                },
                body: JSON.stringify(loginData) // Convert JS object to JSON string
            })
            .then(response => {
                // Check if the response was OK (2xx status code) or a specific error
                // Backend sends JSON even for errors, so always try to parse as JSON.
                if (!response.ok && response.status !== 401) { // 401 for unauthorized login attempts
                    console.error('HTTP Error Status:', response.status, response.statusText);
                    throw new Error('Network response was not ok.');
                }
                return response.json(); // Parse the JSON response
            })
            .then(data => {
                if (data.status === 'success') {
                    if (passwordText) passwordText.style.display = "none";
                    window.location.hash = "home";
                } else {
                    // Display specific error message from the backend
                    alert(data.message); 
                    const passwordInput = document.getElementById('loginPassword');
                    passwordInput.value = ''; // Clear password field
                    if (passwordText) passwordText.style.display = "inline"; // Or update this to display the specific error message
                }
            })
            .catch(error => {
                console.error('Error during login fetch:', error);
                alert('An unexpected error occurred during login. Please try again.');
            });
        });
    }

    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', event => {
            event.preventDefault();

            const newUsername = document.getElementById('newUsername').value;
            const newEmail = document.getElementById('newEmail').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const passwordText = document.getElementById('password_text');

            if (newPassword !== confirmPassword) {
                if (passwordText) passwordText.style.display = "inline";
                alert("Passwords do not match!"); // Clearer user feedback
                return;
            } else {
                if (passwordText) passwordText.style.display = "none";
            }

            // Prepare data as a JavaScript object
            const registerData = {
                username: newUsername, // Use 'username' to match backend's expected JSON key
                email: newEmail,       // Use 'email' to match backend's expected JSON key
                password: newPassword  // Use 'password' to match backend's expected JSON key
            };
            
            // Send JSON data to the /register endpoint
            fetch('php/register', { // Changed endpoint to /register
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json' // Essential header for JSON requests
                },
                body: JSON.stringify(registerData) // Convert JS object to JSON string
            })
            .then(response => {
                // Check for different HTTP status codes for registration (201 Created, 400 Bad Request, 409 Conflict)
                if (!response.ok && response.status !== 400 && response.status !== 409) {
                    console.error('HTTP Error Status:', response.status, response.statusText);
                    throw new Error('Network response was not ok.');
                }
                return response.json(); // Always parse as JSON
            })
            .then(data => {
                alert(data.message); // Display the message from the backend (success or error)
                if (data.status === 'success') {
                    window.location.hash = "login"; // Redirect on successful registration
                }
                // Optionally clear form fields on success or error
            })
            .catch(error => {
                console.error('Error during registration fetch:', error);
                alert('An unexpected error occurred during registration. Please try again.');
            });
        });
    }

    const postItemForm = document.querySelector('.post-item-form');
    if (postItemForm) {
        const fileInput = document.getElementById('item-image');
        const previewDiv = document.getElementById('preview');

        fileInput.addEventListener('change', function() {
            previewDiv.innerHTML = '';
            const files = this.files;
            if (files.length > 0) {
                const file = files[0];
                console.log('Selected file:', file);
                if (file.type.startsWith('image/')) {
                    const objectUrl = URL.createObjectURL(file);
                    const img = document.createElement('img');
                    img.src = objectUrl;
                    img.onload = () => {
                        URL.revokeObjectURL(objectUrl); // Free up memory after image loads [2]
                    };
                    previewDiv.appendChild(img);
                } else {
                    previewDiv.innerHTML = '<p style="color: red;">Please select an image file.</p>';
                }
            }
        });
        // Prevent multiple submit listeners if attachFormHandlers is called repeatedly
        // This is important in SPAs where content might be re-rendered
        if (!postItemForm.hasAttribute('data-submit-listener-attached')) {
            postItemForm.addEventListener('submit', async function(event) {
                event.preventDefault(); // Stop the browser's default form submission
                const files = fileInput.files;
                if (files.length === 0) {
                    alert('Please select an image file to upload.');
                    return;
                }
                const formDataImage = new FormData();
                formDataImage.append('imageFile', files[0]);
                let imageURL = '';
                try {
                    const response = await fetch('php/upload.php', {
                        method: 'POST',
                        body: formDataImage
                    });

                    // Fetch API only rejects on network errors. Check response.status for HTTP errors.
                    if (!response.ok) {
                         // response.ok is true for 2xx status codes
                        const errorData = await response.json(); // Assuming server sends JSON error
                        throw new Error(errorData.message || `HTTP error! Status: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    if (result.success) {
                        console.log('Image URL:', result.imageUrl);
                        imageURL = result.imageUrl;
                    } else {
                        alert(`Upload failed: ${result.message}`);
                        console.error('Upload error:', result.message);
                    }
                } catch (error) {
                    alert(`An error occurred during upload: ${error.message}`)
                    console.error('Fetch error:', error);
                }

                const itemData = {
                    category: document.getElementById('item-category').value,
                    name: document.getElementById('item-name').value,
                    description: document.getElementById('item-description').value,
                    brand: document.getElementById('item-brand').value,
                    condition: document.getElementById('item-condition').value, // Matches PHP's 'condition' key
                    price: parseFloat(document.getElementById('item-price').value), // Convert to number
                    image_path: imageURL // Use the URL obtained from the first upload
                };
                const sizeInput = document.querySelector('#size-input-container input[name="size"]');
                if (sizeInput && sizeInput.value) {
                    itemData.size = sizeInput.value;
                } else {
                    itemData.size = null; // Explicitly send null if size is not provided
                }
                try {
                    const response = await fetch('php/post-item', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(itemData),
                    });

                    // Assuming your backend sends a JSON response (e.g., {success: true, message: "..."})
                    const result = await response.json(); 

                    if (response.ok) { // Check if the HTTP status code is 2xx
                        alert('Success: ' + result.message);
                        postItemForm.reset(); // Clear all form fields
                        updateSizeInput(); // Re-run to clear or reset the dynamic size input state
                    } else {
                        // Handle server-side errors (e.g., validation errors)
                        const errorMessage = result.message || 'Something went wrong on the server.';
                        alert('Error: ' + errorMessage);
                        console.error('Server error:', result);
                    }
                } catch (error) {
                    // Handle network errors or issues with the fetch request itself
                    alert('Network error. Please check your connection and try again.');
                    console.error('Fetch error:', error);
                }
            });
            postItemForm.setAttribute('data-submit-listener-attached', 'true'); // Set flag
        }
    }

}

// Function to update navbar based on user login state
function updateNavbarForUser() {
    fetch('php/user/status')
        .then(response => response.json())
        .then(data => {
            const rightLinks = document.querySelector('.right-links');

            if (data.loggedIn) {
                rightLinks.innerHTML = `<span class="welcome_text">Welcome, ${data.username}</span> <a href="#" id="logoutBtn">Logout</a>`;

                const logoutBtn = document.getElementById('logoutBtn');
                logoutBtn.addEventListener('click', () => {
                    fetch('php/logout')
                        .then(() => {
                            window.location.hash = 'login';
                            updateNavbarForUser();
                        });
                });
            } else {
                rightLinks.innerHTML = `
                    <a href="#register">Register</a>
                    <a href="#login">Log in</a>
                `;
            }
        });
}



