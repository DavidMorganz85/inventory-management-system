-- Sample inventory and movement history for every UIRI section/unit.
-- Each section receives three starter items. The stored item quantity equals
-- total stock-in minus total stock-out. Safe to execute repeatedly.
USE uiri_ims;

START TRANSACTION;

SET @seed_admin_id = (
    SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1
);
SET @office_category_id = (
    SELECT id FROM categories WHERE name = 'Office Supplies' LIMIT 1
);
SET @safety_category_id = (
    SELECT id FROM categories WHERE name = 'Safety Equipment' LIMIT 1
);
SET @ict_category_id = (
    SELECT id FROM categories WHERE name = 'ICT Equipment' LIMIT 1
);
SET @office_supplier_id = (
    SELECT id FROM suppliers WHERE name = 'UIRI Office Solutions Ltd' LIMIT 1
);
SET @safety_supplier_id = (
    SELECT id FROM suppliers WHERE name = 'Kampala Safety Supplies Ltd' LIMIT 1
);
SET @ict_supplier_id = (
    SELECT id FROM suppliers WHERE name = 'TechSource Uganda Ltd' LIMIT 1
);

-- Office-supply stock: 50 received - 5 issued = 45 available.
INSERT INTO items
    (section_id, category_id, supplier_id, item_code, name, description,
     quantity, unit, low_stock_threshold, unit_cost, added_by)
SELECT s.id, @office_category_id, @office_supplier_id,
       CONCAT('SEC-', LPAD(s.id, 4, '0'), '-OFF'),
       'Office Supplies Starter Pack',
       CONCAT('Sample office supplies allocated to ', s.name),
       45, 'packs', 10, 75000.00, @seed_admin_id
FROM sections s
WHERE NOT EXISTS (
    SELECT 1 FROM items i
    WHERE i.item_code = CONCAT('SEC-', LPAD(s.id, 4, '0'), '-OFF')
);

-- Safety stock: 30 received - 3 issued = 27 available.
INSERT INTO items
    (section_id, category_id, supplier_id, item_code, name, description,
     quantity, unit, low_stock_threshold, unit_cost, added_by)
SELECT s.id, @safety_category_id, @safety_supplier_id,
       CONCAT('SEC-', LPAD(s.id, 4, '0'), '-SAFE'),
       'Safety Equipment Starter Pack',
       CONCAT('Sample PPE and safety supplies allocated to ', s.name),
       27, 'packs', 8, 120000.00, @seed_admin_id
FROM sections s
WHERE NOT EXISTS (
    SELECT 1 FROM items i
    WHERE i.item_code = CONCAT('SEC-', LPAD(s.id, 4, '0'), '-SAFE')
);

-- ICT stock: 20 received - 2 issued = 18 available.
INSERT INTO items
    (section_id, category_id, supplier_id, item_code, name, description,
     quantity, unit, low_stock_threshold, unit_cost, added_by)
SELECT s.id, @ict_category_id, @ict_supplier_id,
       CONCAT('SEC-', LPAD(s.id, 4, '0'), '-ICT'),
       'ICT Accessories Starter Pack',
       CONCAT('Sample ICT accessories allocated to ', s.name),
       18, 'packs', 5, 250000.00, @seed_admin_id
FROM sections s
WHERE NOT EXISTS (
    SELECT 1 FROM items i
    WHERE i.item_code = CONCAT('SEC-', LPAD(s.id, 4, '0'), '-ICT')
);

-- Opening receipts for all generated items.
INSERT INTO stock_transactions (item_id, type, quantity, note, performed_by, created_at)
SELECT i.id, 'in',
       CASE
           WHEN i.item_code LIKE '%-OFF' THEN 50
           WHEN i.item_code LIKE '%-SAFE' THEN 30
           ELSE 20
       END,
       'Opening stock issued to section store',
       @seed_admin_id,
       '2026-07-15 09:00:00'
FROM items i
WHERE i.item_code REGEXP '^SEC-[0-9]+-(OFF|SAFE|ICT)$'
  AND NOT EXISTS (
      SELECT 1 FROM stock_transactions st
      WHERE st.item_id = i.id
        AND st.type = 'in'
        AND st.note = 'Opening stock issued to section store'
  );

-- Initial issues demonstrate stock-out and leave the balances stored above.
INSERT INTO stock_transactions (item_id, type, quantity, note, performed_by, created_at)
SELECT i.id, 'out',
       CASE
           WHEN i.item_code LIKE '%-OFF' THEN 5
           WHEN i.item_code LIKE '%-SAFE' THEN 3
           ELSE 2
       END,
       'Initial operational issue',
       @seed_admin_id,
       '2026-07-16 10:30:00'
FROM items i
WHERE i.item_code REGEXP '^SEC-[0-9]+-(OFF|SAFE|ICT)$'
  AND NOT EXISTS (
      SELECT 1 FROM stock_transactions st
      WHERE st.item_id = i.id
        AND st.type = 'out'
        AND st.note = 'Initial operational issue'
  );

COMMIT;
