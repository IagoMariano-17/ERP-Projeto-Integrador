-- V03 - Uso de Materiais
-- Migração incremental: não remove dados existentes.
CREATE TABLE IF NOT EXISTS consumos_materiais (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 material_id INT NOT NULL,
 data_uso DATE NOT NULL,
 quantidade DECIMAL(15,3) NOT NULL,
 motivo VARCHAR(255) NULL,
 usuario_id INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 KEY idx_consumos_material (material_id),
 KEY idx_consumos_data (data_uso),
 CONSTRAINT fk_consumos_material FOREIGN KEY (material_id) REFERENCES suprimentos(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
