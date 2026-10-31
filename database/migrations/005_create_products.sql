-- =============================================================================
-- Migration 005: products
-- Aşıroğlu Yöresel Market - Ürünler
-- Bağımlılık: categories (003), brands (004)
-- =============================================================================

CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Ürün en az bir kategoriye bağlı olmalıdır
    category_id INT UNSIGNED NOT NULL,

    -- Marka isteğe bağlıdır
    brand_id INT UNSIGNED NULL,

    name VARCHAR(255) NOT NULL,

    -- SEO uyumlu benzersiz URL parçası (örn. /urun/antep-fistigi)
    slug VARCHAR(255) NOT NULL UNIQUE,

    sku VARCHAR(64) NOT NULL UNIQUE,
    barcode VARCHAR(64) NULL,

    short_description VARCHAR(500) NULL,
    description TEXT NULL,

    -- Fiyatlar TRY ve KDV dahildir.
    -- price > 0 ve discount_price < price kuralları Validator/Model'de uygulanır.
    price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_price DECIMAL(12,2) NULL,
    discount_start_date DATETIME NULL,
    discount_end_date DATETIME NULL,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,

    -- Yalnızca VARYANTSIZ ürünlerde geçerlidir.
    -- Varyantı olan üründe geçerli stok product_variants.stock_quantity'dir
    -- (kural Product modelinde zorlanır).
    stock_quantity INT NOT NULL DEFAULT 0,
    critical_stock_quantity INT NOT NULL DEFAULT 5,

    unit_type ENUM('adet','kg','gram','paket','litre') NOT NULL DEFAULT 'adet',
    weight DECIMAL(10,2) NULL,

    -- Yöresel gıda bilgileri (ürün sayfasında gösterilir)
    origin VARCHAR(255) NULL,
    producer VARCHAR(255) NULL,
    storage_conditions VARCHAR(500) NULL,
    shelf_life VARCHAR(150) NULL,
    allergens TEXT NULL,
    ingredients TEXT NULL,

    meta_title VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,

    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_new TINYINT(1) NOT NULL DEFAULT 0,
    view_count INT UNSIGNED NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    -- Soft delete: NULL = silinmemiş
    deleted_at DATETIME NULL,

    -- Ürünü olan kategori fiziksel olarak silinemez (RESTRICT)
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories(id),

    -- Marka fiziksel silinirse ürün markasız kalır
    CONSTRAINT fk_products_brand
        FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,

    -- slug ve sku UNIQUE olduğu için ayrıca indeks yazılmadı
    INDEX idx_products_category (category_id),
    INDEX idx_products_brand (brand_id),
    INDEX idx_products_active (is_active),
    INDEX idx_products_featured (is_featured),
    INDEX idx_products_price (price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;