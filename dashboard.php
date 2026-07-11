<?php
/**
 * Dashboard Router
 * Redirects users to their appropriate dashboard based on role
 */
if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: minor.php");
    exit;
}

// Redirect to appropriate dashboard based on role
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard.php");
    exit;
} else {
    header("Location: user_dashboard.php");
    exit;
}
?>
            </div>
            
            <?php if ($result->num_rows > 0): ?>
                <div class="products-grid">
                    <?php while ($product = $result->fetch_assoc()): ?>
                        <div class="product-card">
                            <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                            <div class="product-details">
                                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                                <div class="product-price">₹<?php echo number_format($product['price'], 2); ?></div>
                                <div class="product-quantity">
                                    <i class="fas fa-cubes"></i> Stock: <?php echo $product['quantity']; ?>
                                </div>
                                <div class="product-description">
                                    <?php echo htmlspecialchars(substr($product['description'], 0, 80)); ?>...
                                </div>
                                <div class="product-actions">
                                    <a href="checkout.php?product_id=<?php echo $product['id']; ?>" 
                                       class="btn-order">
                                        <i class="fas fa-shopping-cart"></i> Order Now
                                    </a>
                                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                       class="btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="delete_product.php?id=<?php echo $product['id']; ?>" 
                                       class="btn-delete" onclick="return confirm('Delete this product?');">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No products added yet</p>
                    <a href="product.html" class="btn-add-product">Add Your First Product</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
