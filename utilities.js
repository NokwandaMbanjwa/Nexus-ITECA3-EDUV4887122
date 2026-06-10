function loadWishlist() {
    // For logged-in users, don't use local storage
    if (typeof currentUserLoggedIn !== 'undefined' && currentUserLoggedIn) {
        return [];
    }
    
    // Guest users - use local storage
    var savedWishlist = localStorage.getItem("nexusWishlist");
    if (savedWishlist) {
        return JSON.parse(savedWishlist);
    }
    return [];
}

function saveWishlist(wishlist) {
    if (typeof currentUserLoggedIn === 'undefined' || !currentUserLoggedIn) {
        localStorage.setItem("nexusWishlist", JSON.stringify(wishlist));
    }
}

function isInWishlist(productId) {
    if (typeof currentUserLoggedIn !== 'undefined' && currentUserLoggedIn) {
        return false;
    }
    
    var wishlist = loadWishlist();
    return wishlist.includes(productId);
}

function toggleWishlistItem(productId) {
    if (typeof currentUserLoggedIn !== 'undefined' && currentUserLoggedIn) {
        fetch('wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=toggle&product_id=' + productId
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showTempMessage(data.action === 'added' ? "Item added to wishlist" : "Item removed from wishlist");
                updateWishlistCountBadge();
                updateWishlistIcons();
            }
        })
        .catch(function(error) {
            console.error('Wishlist toggle error:', error);
        });
        return;
    }
    
    var wishlist = loadWishlist();
    var index = wishlist.indexOf(productId);
    
    if (index === -1) {
        wishlist.push(productId);
        showTempMessage("Item added to wishlist");
    } else {
        wishlist.splice(index, 1);
        showTempMessage("Item removed from wishlist");
    }
    
    saveWishlist(wishlist);
    updateWishlistCountBadge();
    updateWishlistIcons();
}

function toggleWishlist(productId) {
    toggleWishlistItem(productId);
}

function updateWishlistIcons() {
    var wishlist = loadWishlist();
    
    if (typeof currentUserLoggedIn !== 'undefined' && currentUserLoggedIn) {
        fetch('wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get'
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success && data.wishlist) {
                updateHeartIcons(data.wishlist);
            }
        })
        .catch(function(error) {
            console.error('Error updating wishlist icons:', error);
        });
    } else {
        updateHeartIcons(wishlist);
    }
}

function updateHeartIcons(wishlistIds) {
    document.querySelectorAll('.wishlist-btn').forEach(function(btn) {
        var card = btn.closest('.item-card') || btn.closest('.wishlist-item');
        if (card) {
            var productId = parseInt(card.getAttribute('data-product-id'));
            var icon = btn.querySelector('i');
            
            if (icon) {
                if (wishlistIds.includes(productId)) {
                    icon.classList.remove('fa-regular');
                    icon.classList.add('fa-solid');
                } else {
                    icon.classList.remove('fa-solid');
                    icon.classList.add('fa-regular');
                }
            }
        }
    });
}

function updateWishlistCountBadge() {
    if (typeof updateWishlistCountBadgeExternal !== 'undefined') {
        updateWishlistCountBadgeExternal();
        return;
    }
    
    if (typeof currentUserLoggedIn !== 'undefined' && currentUserLoggedIn) {
        fetch('wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get'
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success && data.wishlist) {
                updateBadgeUI(data.wishlist.length);
            }
        })
        .catch(function(error) {
            console.error('Error updating badge:', error);
        });
    } else {
        var count = loadWishlist().length;
        updateBadgeUI(count);
    }
}

function updateBadgeUI(count) {
    var wishlistLink = document.querySelector('.icon-btn[aria-label="Wishlist"]');
    
    if (wishlistLink) {
        var existingBadge = wishlistLink.querySelector('.wishlist-badge');
        if (existingBadge) {
            existingBadge.remove();
        }
        
        if (count > 0) {
            var badge = document.createElement('span');
            badge.className = 'wishlist-badge';
            badge.textContent = count;
            badge.style.cssText = 'position:absolute;top:-5px;right:-10px;background-color:#ff6b6b;color:white;border-radius:50%;padding:2px 6px;font-size:10px;font-weight:bold;';
            wishlistLink.style.position = 'relative';
            wishlistLink.appendChild(badge);
        }
    }
}

function isUserLoggedIn() {
    if (typeof currentUserLoggedIn !== 'undefined') {
        return currentUserLoggedIn;
    }
    return false;
}

function addToCart(productId, productName, productPrice) {
    if (!isUserLoggedIn()) {
        if (confirm("You need to sign in to add items to your cart. Click OK to go to the registration page.")) {
            window.location.href = "register.php";
        }
        return false;
    }
    
    fetch('cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=add&product_id=' + productId + '&quantity=1'
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showTempMessage(productName + " added to cart");
        } else {
            showTempMessage(data.message || "Failed to add to cart");
        }
    })
    .catch(function(error) {
        console.error('Cart error:', error);
        showTempMessage("Failed to add to cart");
    });
    
    return true;
}

function showTempMessage(message) {
    var msg = document.getElementById('tempMessage');
    if (!msg) {
        msg = document.createElement('div');
        msg.id = 'tempMessage';
        msg.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#1f1f22;color:#8ff5ff;padding:12px 20px;border-radius:8px;z-index:9999;border:1px solid #8ff5ff;font-family:Inter,sans-serif;font-size:14px;';
        document.body.appendChild(msg);
    }
    msg.textContent = message;
    msg.style.display = 'block';
    setTimeout(function() {
        msg.style.display = 'none';
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    updateWishlistIcons();
    updateWishlistCountBadge();
});