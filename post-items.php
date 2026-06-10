<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_type = getUserType();
if ($user_type !== 'seller') {
    header('Location: index.php');
    exit;
}

$user_id = getUserId();
$error = '';
$success = '';

$stmt = $pdo->prepare("SELECT profile_id FROM seller_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$seller = $stmt->fetch();
$seller_id = $seller['profile_id'] ?? null;

if (!$seller_id) {
    $error = "Seller profile not found. Please contact support.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'publish';
    $product_id = $_POST['product_id'] ?? null;
    
    $item_name = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    $description = trim($_POST['description'] ?? '');
    $item_condition = $_POST['condition'] ?? '';
    $is_discounted = isset($_POST['discounted']);
    $discount_percentage = $is_discounted ? floatval($_POST['discount_percentage'] ?? 0) : 0;
    
    // Calculate discounted price if applicable
    $original_price = $price;
    if ($is_discounted && $discount_percentage > 0) {
        $price = $original_price * (1 - $discount_percentage / 100);
    }

    $errors = [];
    
    if (empty($item_name)) {
        $errors[] = "Item name is required";
    }
    if (empty($category)) {
        $errors[] = "Category is required";
    }
    if ($price <= 0) {
        $errors[] = "Valid price is required";
    }
    if ($quantity < 1) {
        $errors[] = "Quantity must be at least 1";
    }
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    if (empty($item_condition)) {
        $errors[] = "Item condition is required";
    }
    
    $uploaded_images = [];
    $main_image_index = intval($_POST['main_image_index'] ?? 0);
    
    if (isset($_FILES['images'])) {
        $upload_dir = 'uploads/products/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        
        foreach ($_FILES['images']['tmp_name'] as $index => $tmp_name) {
            if ($_FILES['images']['error'][$index] === UPLOAD_ERR_OK) {
                $file_type = $_FILES['images']['type'][$index];
                $file_size = $_FILES['images']['size'][$index];
                
                if (!in_array($file_type, $allowed_types)) {
                    $errors[] = "File " . ($index + 1) . " is not an allowed image type";
                    continue;
                }
                
                if ($file_size > $max_file_size) {
                    $errors[] = "File " . ($index + 1) . " exceeds 5MB limit";
                    continue;
                }
                
                $file_ext = pathinfo($_FILES['images']['name'][$index], PATHINFO_EXTENSION);
                $file_name = time() . '_' . uniqid() . '.' . $file_ext;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($tmp_name, $file_path)) {
                    $uploaded_images[] = [
                        'path' => $file_path,
                        'is_main' => ($index == $main_image_index)
                    ];
                }
            }
        }
    }

    if ($action === 'draft' && $product_id) {
        if (empty($errors) || count($uploaded_images) > 0) {
            try {
                $draft_data = json_encode([
                    'item_name' => $item_name,
                    'category' => $category,
                    'price' => $price,
                    'original_price' => $original_price,
                    'quantity' => $quantity,
                    'description' => $description,
                    'item_condition' => $item_condition,
                    'discount_percentage' => $discount_percentage,
                    'images' => $uploaded_images
                ]);
                
                $stmt = $pdo->prepare("UPDATE products SET 
                    product_name = ?, 
                    category = ?, 
                    price = ?, 
                    original_price = ?, 
                    discount_percentage = ?, 
                    stock_quantity = ?, 
                    product_description = ?, 
                    item_condition = ?, 
                    draft_data = ?,
                    updated_at = NOW()
                    WHERE product_id = ? AND seller_id = ? AND is_draft = TRUE");
                
                $stmt->execute([
                    $item_name, $category, $price, $original_price, $discount_percentage,
                    $quantity, $description, $item_condition, $draft_data, $product_id, $seller_id
                ]);
                
                $hasMain = false;
				$stmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_main = 1");
				$stmt->execute([$product_id]);
				$hasMain = $stmt->fetchColumn() > 0;

				foreach ($uploaded_images as $index => $img) {
					$is_main = (!$hasMain && $index === 0) ? 1 : 0;
					$stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_main, display_order) VALUES (?, ?, ?, ?)");
					$stmt->execute([$product_id, $img['path'], $is_main, $index]);
				}
                
                $success = "Draft saved successfully!";
            } catch (Exception $e) {
                $error = "Failed to save draft: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in required fields or add at least one image";
        }
    } 
    elseif ($action === 'draft') {
        if (empty($errors) || count($uploaded_images) > 0) {
            try {
                $draft_data = json_encode([
                    'item_name' => $item_name,
                    'category' => $category,
                    'price' => $price,
                    'original_price' => $original_price,
                    'quantity' => $quantity,
                    'description' => $description,
                    'item_condition' => $item_condition,
                    'discount_percentage' => $discount_percentage,
                    'images' => $uploaded_images
                ]);
                
                $stmt = $pdo->prepare("INSERT INTO products 
                    (seller_id, product_name, category, price, original_price, discount_percentage, 
                     stock_quantity, product_description, item_condition, is_draft, draft_data, listing_status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?, 'inactive')");
                
                $stmt->execute([
                    $seller_id, $item_name, $category, $price, $original_price, $discount_percentage,
                    $quantity, $description, $item_condition, $draft_data
                ]);
                
                $product_id = $pdo->lastInsertId();
                
                foreach ($uploaded_images as $index => $img) {
					$is_main = ($index === 0) ? 1 : 0;
					$stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_main, display_order) VALUES (?, ?, ?, ?)");
					$stmt->execute([$product_id, $img['path'], $is_main, $index]);
				}
                
                $success = "Draft saved successfully! You can continue editing later.";
            } catch (Exception $e) {
                $error = "Failed to save draft: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in required fields or add at least one image";
        }
    }
    elseif ($action === 'publish') {
        if (count($uploaded_images) < 3) {
            $errors[] = "Please upload at least 3 images for publishing";
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO products 
                    (seller_id, product_name, category, price, original_price, discount_percentage, 
                     stock_quantity, product_description, item_condition, approval_status, listing_status, is_draft) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'inactive', FALSE)");
                
                $stmt->execute([
                    $seller_id, $item_name, $category, $price, $original_price, $discount_percentage,
                    $quantity, $description, $item_condition
                ]);
                
                $product_id = $pdo->lastInsertId();

                foreach ($uploaded_images as $index => $img) {
					$is_main = ($index === 0) ? 1 : 0;
					$stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_main, display_order) VALUES (?, ?, ?, ?)");
					$stmt->execute([$product_id, $img['path'], $is_main, $index]);
				}


                $_SESSION['upload_success'] = "Your item has been submitted for admin approval. You will be notified once approved.";

                header('Location: my-listings.php?success=submitted');
                exit;
                
            } catch (Exception $e) {
                $error = "Failed to publish item: " . $e->getMessage();
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}

$draft_product = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $draft_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ? AND seller_id = ? AND is_draft = TRUE");
    $stmt->execute([$draft_id, $seller_id]);
    $draft_product = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Post Items</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.upload-page {
				padding-top: 60px;
				padding-bottom: 40px;
			}

			.upload-container {
				max-width: 800px;
				margin: 0 auto;
				padding: 0 24px;
			}

			.upload-header {
				text-align: center;
				margin-bottom: 48px;
			}

			.upload-header h1 {
				font-size: 42px;
				margin-bottom: 12px;
				color: #8ff5ff;
			}

			.upload-header p {
				font-size: 16px;
				color: #adaaad;
			}

			.alert {
				padding: 12px 16px;
				border-radius: 8px;
				margin-bottom: 20px;
			}

			.alert-error {
				background: rgba(255, 107, 107, 0.1);
				border: 1px solid #ff6b6b;
				color: #ff6b6b;
			}

			.alert-success {
				background: rgba(76, 175, 80, 0.1);
				border: 1px solid #4caf50;
				color: #4caf50;
			}

			.form-section {
				background: #111;
				border: 1px solid #2a2a2a;
				border-radius: 10px;
				padding: 32px;
				margin-bottom: 28px;
			}

			.form-section h2 {
				font-size: 20px;
				font-weight: 600;
				margin-bottom: 24px;
				padding-bottom: 16px;
				border-bottom: 1px solid #2a2a2a;
				color: #8ff5ff;
			}

			.form-group {
				margin-bottom: 20px;
			}

			.form-group label {
				display: block;
				margin-bottom: 8px;
				font-size: 14px;
				font-weight: 500;
				color: #aaa;
			}

			.form-group label .required {
				color: #ff4444;
			}

			.form-group input,
			.form-group select,
			.form-group textarea {
				width: 100%;
				padding: 12px 16px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				color: #e5e5e5;
				font-size: 15px;
			}

			.form-group input:focus,
			.form-group select:focus,
			.form-group textarea:focus {
				outline: none;
				border-color: #8ff5ff;
			}

			textarea {
				resize: vertical;
				min-height: 120px;
			}

			.form-row {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 20px;
			}

			.discount-fields {
				margin-top: 15px;
				padding: 15px;
				background: #0a0a0c;
				border-radius: 8px;
				display: none;
			}

			.discount-fields.show {
				display: block;
			}

			.image-grid {
				display: grid;
				grid-template-columns: repeat(5, 1fr);
				gap: 12px;
			}

			.image-upload-box {
				aspect-ratio: 1 / 1;
				background: #0e0e10;
				border: 2px dashed #2a2a2a;
				border-radius: 12px;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				cursor: pointer;
				transition: all 0.2s;
				position: relative;
				overflow: hidden;
			}

			.image-upload-box:hover {
				border-color: #8ff5ff;
			}

			.image-upload-box i {
				font-size: 28px;
				color: #555;
				margin-bottom: 6px;
			}

			.image-upload-box span {
				font-size: 11px;
				color: #555;
			}

			.image-upload-box img {
				width: 100%;
				height: 100%;
				object-fit: cover;
			}

			.main-badge {
				position: absolute;
				top: 6px;
				left: 6px;
				background: #8ff5ff;
				color: #0a0a0a;
				font-size: 10px;
				padding: 2px 8px;
				border-radius: 6px;
				font-weight: 500;
				z-index: 2;
			}

			.remove-image {
				position: absolute;
				top: 6px;
				right: 6px;
				background: rgba(0, 0, 0, 0.7);
				border: none;
				color: white;
				width: 22px;
				height: 22px;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				cursor: pointer;
				font-size: 11px;
				z-index: 2;
			}

			.remove-image:hover {
				background: #ff4444;
			}

			.set-main-btn {
				position: absolute;
				bottom: 6px;
				left: 6px;
				background: rgba(0, 0, 0, 0.7);
				border: none;
				color: #8ff5ff;
				padding: 2px 6px;
				border-radius: 4px;
				font-size: 9px;
				cursor: pointer;
				z-index: 2;
			}

			.checkbox-group {
				display: flex;
				align-items: center;
				gap: 12px;
				margin-top: 8px;
			}

			.checkbox-group input {
				width: 18px;
				height: 18px;
				accent-color: #8ff5ff;
				cursor: pointer;
			}

			.image-hint {
				font-size: 12px;
				color: #666;
				margin-top: 12px;
				text-align: center;
			}

			.action-buttons {
				display: flex;
				gap: 16px;
				justify-content: flex-end;
				margin-top: 32px;
			}

			.submit-btn {
				background: #8ff5ff;
				color: #0a0a0a;
				border: none;
				padding: 12px 32px;
				border-radius: 40px;
				font-size: 15px;
				font-weight: 600;
				cursor: pointer;
			}

			.submit-btn:hover {
				background: #7de0ea;
				transform: translateY(-2px);
			}

			.draft-btn {
				background: transparent;
				border: 1px solid #2a2a2a;
				color: #aaa;
				padding: 12px 32px;
				border-radius: 40px;
				font-size: 15px;
				font-weight: 600;
				cursor: pointer;
			}

			.draft-btn:hover {
				border-color: #8ff5ff;
				color: #8ff5ff;
			}

			@media (max-width: 768px) {
				.upload-container { 
					padding: 0 16px; 
				}
				.form-row { 
					grid-template-columns: 1fr; 
				}
				.image-grid { 
					grid-template-columns: repeat(3, 1fr); 
				}
				.action-buttons { 
					flex-direction: column; 
				}
				.action-buttons button { 
					width: 100%; 
				}
			}
		</style>
	</head>
	<body>
		<?php include 'header.php'; ?>
		
		<main class="upload-page">
			<div class="upload-container">
				<div class="upload-header">
					<h1><?php echo $draft_product ? 'Edit Draft' : 'Upload Item'; ?></h1>
					<p><?php echo $draft_product ? 'Continue editing your draft' : 'List your item for sale on Nexus. Please be reminded that for every sale made on the platform, a 10% fee is deducted from the items sold'; ?></p>
				</div>

				<?php if ($error): ?>
					<div class="alert alert-error"><?php echo $error; ?></div>
				<?php endif; ?>
				
				<?php if ($success): ?>
					<div class="alert alert-success"><?php echo $success; ?></div>
				<?php endif; ?>

				<form id="uploadForm" method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" id="formAction" value="publish">
					<?php if ($draft_product): ?>
						<input type="hidden" name="product_id" value="<?php echo $draft_product['product_id']; ?>">
					<?php endif; ?>
					<input type="hidden" name="main_image_index" id="mainImageIndex" value="0">
					
					<div class="form-section">
						<h2>Images (3-5 required)</h2>
						<div class="image-grid" id="imageGrid"></div>
						<p class="image-hint">Upload 3-5 images. First image will be the main display image. Click "Set as Main" to change.</p>
					</div>

					<div class="form-section">
						<h2>Basic Information</h2>
						
						<div class="form-group">
							<label>Item Name <span class="required">*</span></label>
							<input type="text" id="itemName" name="item_name" placeholder="e.g., Vintage Leather Jacket" value="<?php echo htmlspecialchars($draft_product['product_name'] ?? ''); ?>" required>
						</div>

						<div class="form-group">
							<label>Category <span class="required">*</span></label>
							<select id="category" name="category" required>
								<option value="">Select category</option>
								<option value="Baby & Toddler" <?php echo (($draft_product['category'] ?? '') == 'Baby & Toddler') ? 'selected' : ''; ?>>Baby & Toddler</option>
								<option value="Beauty" <?php echo (($draft_product['category'] ?? '') == 'Beauty') ? 'selected' : ''; ?>>Beauty</option>
								<option value="Books" <?php echo (($draft_product['category'] ?? '') == 'Books') ? 'selected' : ''; ?>>Books</option>
								<option value="Electronics" <?php echo (($draft_product['category'] ?? '') == 'Electronics') ? 'selected' : ''; ?>>Electronics</option>
								<option value="Entertainment" <?php echo (($draft_product['category'] ?? '') == 'Entertainment') ? 'selected' : ''; ?>>Entertainment</option>
								<option value="Fashion" <?php echo (($draft_product['category'] ?? '') == 'Fashion') ? 'selected' : ''; ?>>Fashion</option>
								<option value="Gaming" <?php echo (($draft_product['category'] ?? '') == 'Gaming') ? 'selected' : ''; ?>>Gaming</option>
								<option value="Home & Living" <?php echo (($draft_product['category'] ?? '') == 'Home & Living') ? 'selected' : ''; ?>>Home & Living</option>
								<option value="Office" <?php echo (($draft_product['category'] ?? '') == 'Office') ? 'selected' : ''; ?>>Office</option>
								<option value="Pets" <?php echo (($draft_product['category'] ?? '') == 'Pets') ? 'selected' : ''; ?>>Pets</option>
								<option value="Sport" <?php echo (($draft_product['category'] ?? '') == 'Sport') ? 'selected' : ''; ?>>Sport</option>
								<option value="Other" <?php echo (($draft_product['category'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
							</select>
						</div>

						<div class="form-row">
							<div class="form-group">
								<label>Price (R) <span class="required">*</span></label>
								<input type="number" step="0.01" id="price" name="price" placeholder="0.00" value="<?php echo htmlspecialchars($draft_product['price'] ?? ''); ?>" required>
							</div>
							<div class="form-group">
								<label>Quantity <span class="required">*</span></label>
								<input type="number" id="quantity" name="quantity" placeholder="1" min="1" value="<?php echo htmlspecialchars($draft_product['stock_quantity'] ?? '1'); ?>" required>
							</div>
						</div>

						<div class="checkbox-group">
							<input type="checkbox" id="discounted" name="discounted" <?php echo (($draft_product['discount_percentage'] ?? 0) > 0) ? 'checked' : ''; ?>>
							<label for="discounted">This item is on discount</label>
						</div>

						<div class="discount-fields" id="discountFields">
							<div class="form-group">
								<label>Discount Percentage (%)</label>
								<input type="number" id="discountPercentage" name="discount_percentage" min="0" max="100" step="1" placeholder="e.g., 20" value="<?php echo htmlspecialchars($draft_product['discount_percentage'] ?? ''); ?>">
							</div>
						</div>

						<div class="form-group">
							<label>Description <span class="required">*</span></label>
							<textarea id="description" name="description" placeholder="Describe your item in detail..." required><?php echo htmlspecialchars($draft_product['product_description'] ?? ''); ?></textarea>
						</div>
					</div>

					<div class="form-section">
						<h2>Item Condition</h2>
						
						<div class="form-group">
							<label>Condition <span class="required">*</span></label>
							<select id="condition" name="condition" required>
								<option value="">Select condition</option>
								<option value="New" <?php echo (($draft_product['item_condition'] ?? '') == 'New') ? 'selected' : ''; ?>>New - Never used, original packaging</option>
								<option value="Like New" <?php echo (($draft_product['item_condition'] ?? '') == 'Like New') ? 'selected' : ''; ?>>Like New - Used briefly, no signs of wear</option>
								<option value="Excellent" <?php echo (($draft_product['item_condition'] ?? '') == 'Excellent') ? 'selected' : ''; ?>>Excellent - Minimal signs of wear</option>
								<option value="Good" <?php echo (($draft_product['item_condition'] ?? '') == 'Good') ? 'selected' : ''; ?>>Good - Moderate signs of wear, fully functional</option>
								<option value="Fair" <?php echo (($draft_product['item_condition'] ?? '') == 'Fair') ? 'selected' : ''; ?>>Fair - Visible wear, functional</option>
							</select>
						</div>
					</div>

					<div class="action-buttons">
						<button type="button" class="draft-btn" id="draftBtn">Save as Draft</button>
						<button type="submit" class="submit-btn">Publish Listing</button>
					</div>
				</form>
			</div>
		</main>
		
		<?php include 'footer.php'; ?>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
			let uploadedImages = [];
			const MAX_IMAGES = 5;
			const MIN_IMAGES = 3;

			const imageGrid = document.getElementById('imageGrid');
			
			for (let i = 0; i < MAX_IMAGES; i++) {
				const box = document.createElement('div');
				box.className = 'image-upload-box';
				box.setAttribute('data-index', i);
				box.innerHTML = `
					<i class="fas fa-plus"></i>
					<span>Add image</span>
					<input type="file" name="images[]" accept="image/*" style="display: none;" data-index="${i}">
				`;
				
				box.addEventListener('click', (e) => {
					if (!e.target.classList.contains('remove-image') && !e.target.classList.contains('set-main-btn')) {
						box.querySelector('input').click();
					}
				});
				
				box.querySelector('input').addEventListener('change', (e) => handleImageUpload(e, i, box));
				
				imageGrid.appendChild(box);
			}

			function handleImageUpload(event, index, box) {
				const file = event.target.files[0];
				if (file) {
					const reader = new FileReader();
					reader.onload = (e) => {
						uploadedImages[index] = { file: file, dataUrl: e.target.result };
						updateImageBox(box, index, e.target.result);
					};
					reader.readAsDataURL(file);
				}
			}
			
			function updateImageBox(box, index, imageUrl) {
				const isMain = (document.getElementById('mainImageIndex').value == index);
				box.innerHTML = `
					<img src="${imageUrl}" alt="Item image">
					<button class="remove-image" onclick="event.stopPropagation(); removeImage(${index})">×</button>
					<button class="set-main-btn" onclick="event.stopPropagation(); setMainImage(${index})">Set as Main</button>
					${isMain ? '<span class="main-badge">Main</span>' : ''}
					<input type="file" name="images[]" accept="image/*" style="display: none;" data-index="${index}">
				`;
				box.querySelector('input').addEventListener('change', (e) => handleImageUpload(e, index, box));
			}

			function removeImage(index) {
				uploadedImages[index] = null;
				const box = imageGrid.children[index];
				box.innerHTML = `
					<i class="fas fa-plus"></i>
					<span>Add image</span>
					<input type="file" name="images[]" accept="image/*" style="display: none;" data-index="${index}">
				`;
				box.querySelector('input').addEventListener('change', (e) => handleImageUpload(e, index, box));
				
				if (document.getElementById('mainImageIndex').value == index) {
					for (let i = 0; i < MAX_IMAGES; i++) {
						if (uploadedImages[i]) { setMainImage(i); break; }
					}
				}
			}
			
			function setMainImage(index) {
				if (!uploadedImages[index]) return;
				document.getElementById('mainImageIndex').value = index;
				for (let i = 0; i < MAX_IMAGES; i++) {
					const box = imageGrid.children[i];
					if (uploadedImages[i]) {
						const existingBadge = box.querySelector('.main-badge');
						if (existingBadge) existingBadge.remove();
						if (i == index) {
							const badge = document.createElement('span');
							badge.className = 'main-badge';
							badge.textContent = 'Main';
							box.appendChild(badge);
						}
					}
				}
				showTempMessage('Main image updated');
			}

			window.removeImage = removeImage;
			window.setMainImage = setMainImage;

			const discountedCheckbox = document.getElementById('discounted');
			const discountFields = document.getElementById('discountFields');
			
			discountedCheckbox.addEventListener('change', function() {
				discountFields.classList.toggle('show', this.checked);
			});
			
			if (discountedCheckbox.checked) discountFields.classList.add('show');

			const uploadForm = document.getElementById('uploadForm');
			
			uploadForm.addEventListener('submit', (e) => {
				e.preventDefault();
				
				const validImages = uploadedImages.filter(img => img !== null);
				if (validImages.length < MIN_IMAGES) {
					alert(`Please upload at least ${MIN_IMAGES} images (${validImages.length}/${MIN_IMAGES} uploaded)`);
					return;
				}
				
				if (!document.getElementById('itemName').value.trim()) { alert('Please enter an item name'); return; }
				if (!document.getElementById('category').value) { alert('Please select a category'); return; }
				if (!document.getElementById('price').value || document.getElementById('price').value <= 0) { alert('Please enter a valid price'); return; }
				if (!document.getElementById('quantity').value || document.getElementById('quantity').value < 1) { alert('Please enter a valid quantity'); return; }
				if (!document.getElementById('description').value.trim()) { alert('Please enter a description'); return; }
				if (!document.getElementById('condition').value) { alert('Please select item condition'); return; }
				
				document.getElementById('formAction').value = 'publish';
				
				const formData = new FormData(uploadForm);
				for (let i = 0; i < uploadedImages.length; i++) {
					if (uploadedImages[i] && uploadedImages[i].file) {
						formData.append('images[]', uploadedImages[i].file);
					}
				}
				
				fetch('post-items.php', {
					method: 'POST',
					body: formData
				})
				.then(() => { window.location.href = 'my-listings.php?success=submitted'; })
				.catch(error => { console.error('Error:', error); alert('Error publishing item. Please try again.'); });
			});
			
			document.getElementById('draftBtn').addEventListener('click', () => {
				const validImages = uploadedImages.filter(img => img !== null);
				if (validImages.length === 0) { alert('Please add at least one image to save as draft'); return; }
				
				document.getElementById('formAction').value = 'draft';
				
				const formData = new FormData(uploadForm);
				for (let i = 0; i < uploadedImages.length; i++) {
					if (uploadedImages[i] && uploadedImages[i].file) {
						formData.append('images[]', uploadedImages[i].file);
					}
				}
				
				fetch('post-items.php', {
					method: 'POST',
					body: formData
				})
				.then(() => { window.location.href = 'my-listings.php?success=draft_saved'; })
				.catch(error => { console.error('Error:', error); alert('Error saving draft. Please try again.'); });
			});
			
			function showTempMessage(message) {
				let messageDiv = document.getElementById('globalMessage');
				if (!messageDiv) {
					messageDiv = document.createElement('div');
					messageDiv.id = 'globalMessage';
					messageDiv.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#1f1f22;color:#8ff5ff;padding:12px 20px;border-radius:8px;z-index:9999;border:1px solid #8ff5ff;';
					document.body.appendChild(messageDiv);
				}
				messageDiv.textContent = message;
				messageDiv.style.display = 'block';
				setTimeout(() => { messageDiv.style.display = 'none'; }, 3000);
			}
		</script>
	</body>
</html>