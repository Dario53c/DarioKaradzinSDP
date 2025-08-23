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

    if (hash === "post_item"){
        fetch('php/user/verified')
            .then(response => response.json())
            .then(verificationData => {
                if (verificationData.status === "error") {
                    alert("Verification error: " + verificationData.message);
                    window.location.hash = "home";
                } else if (verificationData.verified == 0) {
                    alert("Please verify your account before posting items.");
                    window.location.hash = "home";
                }
            })
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
        else if (page === 'profile') {
            setupProfilePage();
        } else if (page === 'success') {
            setupSuccessfulTransactionPage();
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

// Define the expected value for each item characteristic
const itemValueMap = {
    // Base values for categories
    '1': 20, // Shirts
    '2': 25, // Pants
    '3': 40, // Shoes
    '4': 50, // Jackets
    '5': 15, // Accessories
    '6': 35, // Dresses
    '7': 10, // Hats
    '8': 30, // Sportswear

    // Multipliers for condition
    'Factory New': 2.0,
    'Minimal Wear': 1.5,
    'Used': 1.0,
    'Well Worn': 0.8,
    'Worn Out': 0.5,

    // Multipliers for brand
    'Yes': 1.5,
    'No': 1.0,
};

function calculateExpectedPrice() {
    // Get all the relevant input values from the form
    const categoryValue = document.getElementById('item-category').value;
    const conditionValue = document.getElementById('item-condition').value;
    const brandValue = document.getElementById('item-brand').value;

    let basePrice = 0;
    
    // Check if values exist in the map before adding to the price
    if (itemValueMap[categoryValue]) {
        basePrice += itemValueMap[categoryValue];
    }
    
    // Apply the condition multiplier
    let finalPrice = basePrice * (itemValueMap[conditionValue] || 1.0);
    
    // Apply the brand multiplier
    finalPrice = finalPrice * (itemValueMap[brandValue] || 1.0);

    // Ensure the price is a positive number
    return finalPrice;
}

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

                const expectedPrice = calculateExpectedPrice();
                const userPrice = parseFloat(document.getElementById('item-price').value);

                // Validation for prices
                if (userPrice > (expectedPrice * 2)) {
                    alert("We don't support scams on our site. The price is too high based on our internal value calculation.");
                    return;
                }

                if (userPrice < (expectedPrice * 0.5)) {
                    const userConfirmation = confirm(
                        `We believe this item's value is worth more than the set price of $${userPrice.toFixed(2)}. The recommended price is $${expectedPrice.toFixed(2)}. Do you wish to continue with the price you have set?`
                    );
                    if (!userConfirmation) {
                        return;
                    }
                }

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
                    console.error('Fetch error:', error);  //line 249
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
// A separate async function to handle the entire navbar update logic
async function updateNavbarForUser() {
    const rightLinks = document.querySelector('.right-links');
    const leftLinks = document.querySelector('.left-links');

    try {
        // Fetch the user status first
        const statusResponse = await fetch('php/user/status');
        const statusData = await statusResponse.json();

        if (statusData.loggedIn) {
            let navHtml = `<span class="welcome_text">Welcome, ${statusData.username}</span> `;

            // Fetch verification status only if the user is logged in
            const verificationResponse = await fetch('php/user/verified');
            const verificationData = await verificationResponse.json();

            // Handle verification status
            if (verificationData.status === 'success' && verificationData.verified == 1) {
                navHtml = `<span class="welcome_text">Welcome, ${statusData.username} &#10003;</span> `;
            } else if (verificationData.status === 'success' && verificationData.verified == 0) {
                navHtml += `<a href="#" class="verify-link">Verify Account</a> `;
            }

            // Always add the logout button
            navHtml += `<a href="#" id="logoutBtn">Log out</a>`;
            rightLinks.innerHTML = navHtml;

            // Handle profile picture, but only if it doesn't already exist
            if (!leftLinks.querySelector('.profile-pfp')) {
                const profileImageResponse = await fetch('php/user/profile-image');
                const profileImageData = await profileImageResponse.json();
                console.log('Profile image data:', profileImageData);
                if (profileImageData.status === 'success' && profileImageData.imageUrl) {
                    const profileImage = document.createElement('img');
                    profileImage.src = profileImageData.imageUrl;
                    profileImage.alt = 'Profile Picture';
                    profileImage.classList.add('profile-pfp');
                    profileImage.removeEventListener('click', changeLocationToProfile);
                    profileImage.addEventListener('click', changeLocationToProfile);
                    leftLinks.prepend(profileImage);
                }
            }

            // Add event listeners after elements are in the DOM
            const logoutBtn = document.getElementById('logoutBtn');
            logoutBtn.addEventListener('click', async () => {
                await fetch('php/logout');
                window.location.hash = 'login';
                updateNavbarForUser(); // Update to reflect logged-out state
            });

            const verifyLink = document.querySelector('.verify-link');
            if (verifyLink) {
                // Assuming handleVerifyLinkClick is defined elsewhere
                verifyLink.addEventListener('click', handleVerifyLinkClick);
            }

        } else {
            // User is not logged in
            rightLinks.innerHTML = `
                <a href="#register">Register</a>
                <a href="#login">Log in</a>
            `;
            // If the user logs out, remove the profile picture from the left links
            const existingPfp = leftLinks.querySelector('.profile-pfp');
            if (existingPfp) {
                existingPfp.remove();
            }
        }
    } catch (error) {
        console.error('An error occurred during navbar update:', error);
        // Handle all errors here, providing a fallback for the user
        rightLinks.innerHTML = `
            <a href="#register">Register</a>
            <a href="#login">Log in</a>
            <span style="color: red; margin-left: 10px;">(API Error)</span>
        `;
    }
}

function changeLocationToProfile(event) {
    event.preventDefault();
    window.location.hash = 'profile';
}

function handleVerifyLinkClick(event) {
    event.preventDefault();
    fetch('php/user/send-verification-email')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(verificationResponse => {
            if (verificationResponse.status === 'success') {
                alert('Verification email sent. Please check your email.');
            } else {
                alert(verificationResponse.message || 'Error sending verification email. Please try again.');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('An error occurred while sending the verification email. Please try again later.');
        });
}

// Profile Page Setup
async function setupProfilePage() {
    const response = await fetch('php/user/details');
    if (!response.ok) {
        throw new Error('Failed to fetch user details');
    }

    const userDetails = await response.json();
    if (userDetails.status !== 'success' || !userDetails.user) {
        throw new Error(userDetails.message || 'Failed to load user details');
    }

    const user = userDetails.user;
    document.getElementById('profile-image').src = user.profile_pic_url;
    document.getElementById('profile-username').textContent = "Username: " + user.username;
    document.getElementById('profile-email').textContent = "email: " + user.email;
    document.getElementById('profile-about-me').textContent = user.About_me;

    const profileImageContainer = document.querySelector('.profile-image-container');
    const fileInput = document.getElementById('file-input');
    profileImageContainer.addEventListener('click', () => {
        fileInput.click();
    });

    fileInput.removeEventListener('change', handleImageChange);
    fileInput.addEventListener('change', handleImageChange);
}


async function handleImageChange(event) {
    const selectedFile = event.target.files[0];
    if (!selectedFile) {
        return; // Exit if no file was selected
    }

    // Optional: Immediately display a local preview for user feedback
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('profile-image').src = e.target.result;
        document.querySelector('.left-links .profile-pfp').src = e.target.result;
    };
    reader.readAsDataURL(selectedFile);

    // Step 1: Upload the image to the server and get its URL
    try {
        // Change 'profileImage' to 'imageFile' to match the PHP backend
        const formData = new FormData();
        formData.append('imageFile', selectedFile);

        const uploadResponse = await fetch('php/upload.php', {
            method: 'POST',
            body: formData
        });

        // Check for HTTP errors from the upload
        if (!uploadResponse.ok) {
            const errorData = await uploadResponse.json();
            throw new Error(errorData.message || `HTTP error! Status: ${uploadResponse.status}`);
        }

        const uploadResult = await uploadResponse.json();

        if (uploadResult.success && uploadResult.imageUrl) {
            console.log('Image uploaded successfully. URL:', uploadResult.imageUrl);
            
            // Step 2: Send the new image URL to the database
            const dbUpdateResponse = await fetch('php/user/update-profile-image', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ imageUrl: uploadResult.imageUrl })
            });

            if (!dbUpdateResponse.ok) {
                const dbErrorData = await dbUpdateResponse.json();
                throw new Error(dbErrorData.message || `HTTP error! Status: ${dbUpdateResponse.status}`);
            }

            const dbUpdateResult = await dbUpdateResponse.json();

            if (dbUpdateResult.status === 'success') {
                console.log('Profile image updated successfully.');
            } else {
                alert(`Database update failed: ${dbUpdateResult.message}`);
            }
        } else {
            alert(`Upload failed: ${uploadResult.message}`);
            console.error('Upload error:', uploadResult.message);
        }
    } catch (error) {
        alert(`An error occurred: ${error.message}`);
        console.error('Fetch error:', error);
    }
}

function editProfile(){
    const updateBtns = document.querySelector('.edit-profile-btns');
    updateBtns.style.display = 'none';

    username = document.getElementById('profile-username').textContent.replace('Username: ', '');
    email = document.getElementById('profile-email').textContent.replace('email: ', '');
    const profileInfo = document.querySelector('.profile-info');
    profileInfo.innerHTML = `
        <input type="text" id="profile-username-input" value="${username}" />
        <br>
        <input type="email" id="profile-email-input" value="${email}" />
    `
    aboutMe = document.getElementById('profile-about-me').textContent;
    aboutMeArea = document.querySelector('.about-me-container');
    aboutMeArea.innerHTML = `
        <textarea id="about-me-textarea" rows="5" cols="50">${aboutMe}</textarea>
        <br>
        <button class="edit-profile-btn" id="save-about-me-btn" onclick="saveProfileChanges()">Save</button>
        <button class="edit-profile-btn" id="cancel-changes" onclick="cancelChanges()">Cancel</button>`;
}

function cancelChanges() {
    const updateBtns = document.querySelector('.edit-profile-btns');
    updateBtns.style.display = 'block';

    const profileInfo = document.querySelector('.profile-info');
    profileInfo.innerHTML = `
        <h1 class="profile-name" id="profile-username">Username: ${username}</h1>
        <p class="profile-email" id="profile-email">email: ${email}</p>
    `;

    const aboutMeArea = document.querySelector('.about-me-container');
    aboutMeArea.innerHTML = `
        <p id="profile-about-me">${aboutMe}</p>`;
}

async function saveProfileChanges() {
    const aboutMeTextarea = document.getElementById('about-me-textarea');
    const aboutMeText = aboutMeTextarea.value.trim();
    const usernameInput = document.getElementById('profile-username-input').value.trim();
    const emailInput = document.getElementById('profile-email-input').value.trim();

    if (aboutMeText === '') {
        alert('About Me section cannot be empty.');
        return;
    } else if( aboutMeText.length > 255) {
        alert('About Me section cannot exceed 255 characters.');
        return;
    }

    if(usernameInput.length < 3) {
        alert('Username must be atleast 3 characters');
        return;
    }

    if (!isValidEmail(emailInput)) {
        alert('Please enter a valid email address.');
        return;
    }

    try {
        const response = await fetch('php/user/edit-profile', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ aboutMe: aboutMeText, username: usernameInput, email: emailInput })
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || `HTTP error! Status: ${response.status}`);
        }

        const result = await response.json();
        if (result.status === 'success') {
            alert(result.message);
            const content = document.getElementById('content');
            content.innerHTML = `<div class="profile-details">
                <div class="profile-header">
                    <div class="profile-image-container">
                        <img src="images/default_pfp.jpg" alt="Profile Picture" class="profile-picture" id="profile-image">
                        <img src="images/edit-icon.png" alt="Edit Icon" class="edit-icon">
                        <span class="tooltiptext">Edit Profile Picture</span>
                    </div>
                    <input type="file" id="file-input" accept="image/*" style="display: none;">
                    <div class="profile-info">
                        <h1 class="profile-name" id="profile-username">Loading...</h1>
                        <p class="profile-email" id = "profile-email">Loading...</p>
                    </div>
                </div>
                <div class="profile-bio">
                    <h2>About Me</h2>
                    <div class="about-me-container">
                        <p id="profile-about-me">Loading...</p>
                    </div>
                </div>
                <div class="edit-profile-btns">
                    <button class="edit-profile-btn" id="update-profile-btn" onclick="editProfile()">Edit Profile</button>
                    <button class="edit-profile-btn" id="change-password-btn" onclick="changePassword()">Change Password</button>
                </div>
            </div>`;
            setupProfilePage();
        } else {
            alert(`Update failed: ${result.message}`);
        }
    } catch (error) {
        alert(`An error occurred: ${error.message}`);
        console.error('Fetch error:', error);
    }
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function changePassword(){
    const updateBtns = document.querySelector('.edit-profile-btns');
    updateBtns.style.display = 'none';

    const aboutme = document.querySelector('.profile-bio');
    aboutme.style.display = 'none';

    const profileInfo = document.querySelector('.profile-info');
    profileInfo.innerHTML = `
        <input type="password" id="current-password" placeholder="Current Password" />
        <br>
        <input type="password" id="new-password" placeholder="New Password" />
        <br>
        <input type="password" id="confirm-new-password" placeholder="Confirm New Password" />
        <br>
        <button class="edit-profile-btn" id="save-password-btn" onclick="savePasswordChanges()">Save</button>
        <button class="edit-profile-btn" id="cancel-changes" onclick="cancelChanges2()">Cancel</button>`;
}

function cancelChanges2() {
            const content = document.getElementById('content');
            content.innerHTML = `<div class="profile-details">
                <div class="profile-header">
                    <div class="profile-image-container">
                        <img src="images/default_pfp.jpg" alt="Profile Picture" class="profile-picture" id="profile-image">
                        <img src="images/edit-icon.png" alt="Edit Icon" class="edit-icon">
                        <span class="tooltiptext">Edit Profile Picture</span>
                    </div>
                    <input type="file" id="file-input" accept="image/*" style="display: none;">
                    <div class="profile-info">
                        <h1 class="profile-name" id="profile-username">Loading...</h1>
                        <p class="profile-email" id = "profile-email">Loading...</p>
                    </div>
                </div>
                <div class="profile-bio">
                    <h2>About Me</h2>
                    <div class="about-me-container">
                        <p id="profile-about-me">Loading...</p>
                    </div>
                </div>
                <div class="edit-profile-btns">
                    <button class="edit-profile-btn" id="update-profile-btn" onclick="editProfile()">Edit Profile</button>
                    <button class="edit-profile-btn" id="change-password-btn" onclick="changePassword()">Change Password</button>
                </div>
            </div>`;
            setupProfilePage();
}

async function savePasswordChanges() {
    const currentPassword = document.getElementById('current-password').value.trim();
    const newPassword = document.getElementById('new-password').value.trim();
    const confirmNewPassword = document.getElementById('confirm-new-password').value.trim();

    if (newPassword !== confirmNewPassword) {
        alert("New passwords do not match!");
        return;
    }

    if (newPassword.length < 8) {
        alert("New password must be at least 8 characters long.");
        return;
    }

    try {
        const response = await fetch('php/user/change-password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ currentPassword, newPassword })
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || `HTTP error! Status: ${response.status}`);
        }

        const result = await response.json();
        if (result.status === 'success') {
            alert(result.message);
            cancelChanges2();
        } else {
            alert(`Update failed: ${result.message}`);
        }
    } catch (error) {
        alert(`An error occurred: ${error.message}`);
        console.error('Fetch error:', error);
    }
}


