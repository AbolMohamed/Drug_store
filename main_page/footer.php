<!-- Pharmacy Footer Navigation -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<nav class="pharmacy-nav">
  <!-- Home -->
  <a href="view.php" class="nav-item alert-item" data-page="view">
    <i class="material-icons nav-icon">inventory</i>
    <span class="nav-label">Stock</span>
  </a>
  
  <!-- Selling -->
  <a href="salling.php" class="nav-item alert-item" data-page="salling">
    <i class="material-icons nav-icon">point_of_sale</i>
    <span class="nav-label">Selling</span>
  </a>
  
  <!-- Cart -->
  <a href="cart.php" class="nav-item alert-item" data-page="cart">
    <i class="material-icons nav-icon">shopping_cart</i>
    <span class="nav-label">Cart</span>
    <span class="cart-badge" id="cart-item">0</span>
  </a>
  
  <!-- Category -->
  <a href="drug_categories.php" class="nav-item alert-item" data-page="catagory">
    <i class="material-icons nav-icon">category</i>
    <span class="nav-label">Category</span>
  </a>

  <!-- Orders -->
  <a href="view_orders.php" class="nav-item alert-item" data-page="view_orders">
    <i class="material-icons nav-icon">assignment</i>
    <span class="nav-label">Orders</span>
  </a>

  <!-- Supplier -->
  <a href="supplier_&_customer.php" class="nav-item alert-item" data-page="supplier">
    <i class="material-icons nav-icon">local_shipping</i>
    <span class="nav-label">Supplier</span>
  </a>
  
  <!-- Settings -->
  <a href="manage_users.php" class="nav-item alert-item" data-page="manage_users">
    <i class="material-icons nav-icon">settings</i>
    <span class="nav-label">Settings</span>
  </a>
</nav>

<style>
  /* Add this to your stylesheet */
  .quick-icon {
    color: #325ba8;
    font-size: 24px; /* Adjust as needed */
  }

  /* Base Styles */
  .pharmacy-nav {
    display: flex;
    justify-content: space-around;
    align-items: center;
    position: fixed;
    bottom: 0;
    width: 100%;
    background: #ffffff;
    box-shadow: 0 -2px 15px rgba(0,0,0,0.1);
    padding: 8px 0;
    z-index: 1000;
    overflow-x: auto; /* Allow horizontal scrolling on small screens */
  }
  
  .nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #666;
    font-size: 12px;
    position: relative;
    flex: 1;
    min-width: 60px; /* Minimum width for each item */
    padding: 5px 0;
    transition: all 0.3s ease;
  }
  
  .nav-icon {
    font-size: 22px; /* Slightly smaller to fit more items */
    margin-bottom: 3px;
  }
  
  /* Active State */
  .nav-item.active {
    color: #17a2b8;
  }
  
  .nav-item.active .nav-icon {
    color: #17a2b8;
  }
  
  /* Badges */
  .cart-badge, .alert-badge {
    position: absolute;
    top: 0;
    right: 20%;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: bold;
  }
  
  /* Responsive badge positioning */
  @media (max-width: 480px) {
    .cart-badge, .alert-badge {
      right: 15%;
    }
    
    .nav-item {
      font-size: 11px; /* Smaller font for mobile */
      min-width: 55px;
    }
    
    .nav-icon {
      font-size: 20px;
    }
  }
  
  @media (min-width: 768px) {
    .cart-badge, .alert-badge {
      right: 25%;
    }
  }
  
  /* For very small screens, make text smaller or hide it */
  @media (max-width: 360px) {
    .nav-label {
      font-size: 10px;
    }
    
    .nav-item {
      min-width: 50px;
    }
  }
  
  /* Alert Items */
  .alert-item .nav-icon {
    color: #ffc107;
  }
  
  .alert-item.active .nav-icon {
    color: #dc3545;
  }
  
  /* Hover Effects */
  .nav-item:hover {
    transform: translateY(-3px);
  }
  
  .nav-item:hover .nav-icon {
    color: #17a2b8;
  }
</style>

<script>
// Update cart item count
function load_cart_item_number() {
  $.ajax({
    url: 'action.php',
    method: 'get',
    data: {cartItem: "cart_item"},
    success: function(response) {
      $("#cart-item").html(response);
    }
  });
}
load_cart_item_number();

// Auto-update every 5 seconds
setInterval(load_cart_item_number, 5000);

// Set active navigation item based on current page
document.addEventListener('DOMContentLoaded', function() {
  // Get current page filename
  const currentPage = window.location.pathname.split('/').pop().split('.')[0];
  
  // Find and activate the corresponding nav item
  const navItems = document.querySelectorAll('.nav-item');
  navItems.forEach(item => {
    const pageName = item.getAttribute('data-page');
    if (pageName === currentPage) {
      item.classList.add('active');
    }
  });
  
  // Also check for query parameters that might indicate the page
  if (!document.querySelector('.nav-item.active')) {
    const urlParams = new URLSearchParams(window.location.search);
    const action = urlParams.get('action');
    
    if (action) {
      const actionItem = document.querySelector(`.nav-item[data-page="${action}"]`);
      if (actionItem) {
        actionItem.classList.add('active');
      }
    }
  }
});
</script>