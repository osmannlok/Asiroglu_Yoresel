-- =============================================================================
-- Migration 008: attributes, attribute_values, product_attributes
-- Aşıroğlu Yöresel Market - Ürün özellikleri ve filtre altyapısı
-- Bağımlılık: products (005)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Özellik tanımı (örn. "Yöre", "Organik mi?")
-- -----------------------------------------------------------------------------
CREATE TABLE attributes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,

    -- Değer türü: serbest metin, tekli/çoklu seçim veya evet/hayır
    type ENUM('text','select','multiselect','boolean') NOT NULL DEFAULT 'text',

    -- Ürün listesinde filtre olarak gösterilsin mi?
    is_filterable TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Özelliğin seçilebilir değerleri (örn. "Antep", "Rize")
-- -----------------------------------------------------------------------------
CREATE TABLE attribute_values (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attribute_id INT UNSIGNED NOT NULL,
    value VARCHAR(255) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,

    -- Özellik silinirse değerleri de silinir
    CONSTRAINT fk_attribute_values_attribute
        FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE,

    -- Aynı özellik altında aynı slug tekrar edemez
    UNIQUE KEY uq_attribute_value (attribute_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Ürün - özellik ataması
--    Listeden seçilen değer: attribute_value_id
--    Serbest metin / listede olmayan değer: custom_value
-- -----------------------------------------------------------------------------
CREATE TABLE product_attributes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    attribute_id INT UNSIGNED NOT NULL,
    attribute_value_id INT UNSIGNED NULL,
    custom_value VARCHAR(255) NULL,

    -- Ürün silinirse atamaları da silinir
    CONSTRAINT fk_product_attributes_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,

    -- Özellik silinirse atamaları da silinir
    CONSTRAINT fk_product_attributes_attribute
        FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE,

    -- Değer silinirse atama kalır, değer NULL olur (custom_value korunabilir)
    CONSTRAINT fk_product_attributes_value
        FOREIGN KEY (attribute_value_id) REFERENCES attribute_values(id) ON DELETE SET NULL,

    INDEX idx_product_attributes_product (product_id),
    INDEX idx_product_attributes_attribute (attribute_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;