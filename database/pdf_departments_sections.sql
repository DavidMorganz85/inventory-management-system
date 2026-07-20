-- UIRI department and section/unit master data transcribed from
-- UIRI_IMS_Departments_and_Sections_Database_Representation.pdf (20 July 2026).
-- Expected result: Nakawa 23 departments / 60 sections;
--                  Namanve 16 departments / 42 sections.
USE uiri_ims;

START TRANSACTION;

-- Preserve the legacy record IDs and manager assignment while aligning names
-- with the PDF hierarchy. These updates only run when the PDF target is absent.
UPDATE departments legacy
JOIN branches b ON b.id = legacy.branch_id AND b.name = 'Nakawa HQ'
SET legacy.name = 'ICT Software Development Section'
WHERE legacy.name = 'Administration and ICT'
  AND NOT EXISTS (
      SELECT 1 FROM departments target
      WHERE target.branch_id = legacy.branch_id
        AND target.name = 'ICT Software Development Section'
  );

UPDATE sections s
JOIN departments d ON d.id = s.department_id
SET s.name = 'Network and Systems Support'
WHERE d.name = 'ICT Software Development Section'
  AND s.name = 'ICT Support'
  AND NOT EXISTS (
      SELECT 1 FROM sections target
      WHERE target.department_id = s.department_id
        AND target.name = 'Network and Systems Support'
  );

UPDATE sections s
JOIN departments d ON d.id = s.department_id
SET s.name = 'Software Development Unit'
WHERE d.name = 'ICT Software Development Section'
  AND s.name = 'Central Stores'
  AND NOT EXISTS (
      SELECT 1 FROM sections target
      WHERE target.department_id = s.department_id
        AND target.name = 'Software Development Unit'
  );

DROP TEMPORARY TABLE IF EXISTS pdf_structure;
CREATE TEMPORARY TABLE pdf_structure (
    branch_name VARCHAR(150) NOT NULL,
    department_name VARCHAR(150) NOT NULL,
    section_name VARCHAR(200) NOT NULL,
    PRIMARY KEY (branch_name, department_name, section_name)
);

INSERT INTO pdf_structure (branch_name, department_name, section_name) VALUES
-- Nakawa: 60 sections/units under 23 parent departments/directorates
('Nakawa HQ','Agro-Production and Value Addition Laboratory','Agricultural Product Development and Testing Unit'),
('Nakawa HQ','Agro-Production and Value Addition Laboratory','Product Development Unit'),
('Nakawa HQ','Agro-Production and Value Addition Laboratory','Quality and Safety Testing'),
('Nakawa HQ','Bakery and Confectionery Technology Section','Bread Production Unit'),
('Nakawa HQ','Bakery and Confectionery Technology Section','Bread, Pastry and Confectionery Production Unit'),
('Nakawa HQ','Bakery and Confectionery Technology Section','Confectionery Production Unit'),
('Nakawa HQ','Bakery and Confectionery Technology Section','Pastry and Cake Production Unit'),
('Nakawa HQ','Bamboo Processing Section','Bamboo Composite and Structural Unit'),
('Nakawa HQ','Bamboo Processing Section','Bamboo Crafts and Material Processing Unit'),
('Nakawa HQ','Bamboo Processing Section','Bamboo Weaving and Craft Unit'),
('Nakawa HQ','Central Warehouse & General Stores','Central Receiving and Stock Custody Unit'),
('Nakawa HQ','Ceramics and Materials Processing Section','Advanced Materials Lab'),
('Nakawa HQ','Ceramics and Materials Processing Section','Pottery Production Unit'),
('Nakawa HQ','Ceramics and Materials Processing Section','Pottery, Ceramics and Advanced Materials Unit'),
('Nakawa HQ','Chemistry Analytical Laboratory','Chemical Analysis and Quality Testing Lab'),
('Nakawa HQ','Chemistry Analytical Laboratory','Qualitative Analysis Lab'),
('Nakawa HQ','Chemistry Analytical Laboratory','Quantitative Analysis Lab'),
('Nakawa HQ','Civil Works & Estate Management','Estate Management and Facilities Maintenance Unit'),
('Nakawa HQ','Dairy Processing Technology Section','Butter and Ghee Production Unit'),
('Nakawa HQ','Dairy Processing Technology Section','Cheese and Yoghurt Production Unit'),
('Nakawa HQ','Dairy Processing Technology Section','Milk and Dairy Products Processing Unit'),
('Nakawa HQ','Dairy Processing Technology Section','Milk Reception and Testing Lab'),
('Nakawa HQ','Executive Directorate','Office of the Executive Director'),
('Nakawa HQ','Finance and Accounts Department','Financial Control and Accounts Unit'),
('Nakawa HQ','Fruits and Vegetables Processing Section','Dried Fruit and Vegetable Processing'),
('Nakawa HQ','Fruits and Vegetables Processing Section','Fruit and Vegetable Preservation and Processing Unit'),
('Nakawa HQ','Fruits and Vegetables Processing Section','Jam and Preserve Production'),
('Nakawa HQ','Fruits and Vegetables Processing Section','Juice and Beverage Production'),
('Nakawa HQ','Handmade Paper Technology Section','Paper Finishing and Coating Unit'),
('Nakawa HQ','Handmade Paper Technology Section','Paper Production and Craft Unit'),
('Nakawa HQ','Handmade Paper Technology Section','Paper Pulping and Formation Unit'),
('Nakawa HQ','Human Resources & Administration','Human Resource Management and Records Unit'),
('Nakawa HQ','ICT Software Development Section','Network and Systems Support'),
('Nakawa HQ','ICT Software Development Section','Software Development Unit'),
('Nakawa HQ','ICT Software Development Section','Systems Development and IT Support Unit'),
('Nakawa HQ','In-House Business Incubation Hub','Business Planning Unit'),
('Nakawa HQ','In-House Business Incubation Hub','Mentorship and Coaching Unit'),
('Nakawa HQ','In-House Business Incubation Hub','On-Site Business Incubation Unit'),
('Nakawa HQ','Instrumentation Design and Electronics Prototyping Laboratory','Circuit Design Unit'),
('Nakawa HQ','Instrumentation Design and Electronics Prototyping Laboratory','Device Testing and Calibration'),
('Nakawa HQ','Instrumentation Design and Electronics Prototyping Laboratory','Electronic Device Design and Testing Unit'),
('Nakawa HQ','Meat Processing Technology Section','Butchering and Processing Unit'),
('Nakawa HQ','Meat Processing Technology Section','Meat Butchering, Processing and Value Addition Unit'),
('Nakawa HQ','Meat Processing Technology Section','Meat Product Packaging Unit'),
('Nakawa HQ','Microbiology and Biotechnology Laboratory','Biotech Research Unit'),
('Nakawa HQ','Microbiology and Biotechnology Laboratory','Microbial Analysis and Biotech Research Lab'),
('Nakawa HQ','Microbiology and Biotechnology Laboratory','Microbial Identification Unit'),
('Nakawa HQ','Mineral Testing Laboratory','Mineral and Ore Analysis Unit'),
('Nakawa HQ','Mineral Testing Laboratory','Mineral Processing Unit'),
('Nakawa HQ','Mineral Testing Laboratory','Ore Testing and Analysis'),
('Nakawa HQ','Printed Circuit Board Production Unit','PCB Design and Manufacturing Unit'),
('Nakawa HQ','Printed Circuit Board Production Unit','PCB Design Unit'),
('Nakawa HQ','Printed Circuit Board Production Unit','PCB Manufacturing Unit'),
('Nakawa HQ','Procurement and Disposal Unit','PDU Secretariat and Contract Administration Unit'),
('Nakawa HQ','Virtual Business Incubation Hub','Digital Business Incubation and Support Unit'),
('Nakawa HQ','Virtual Business Incubation Hub','Digital Marketing Unit'),
('Nakawa HQ','Virtual Business Incubation Hub','E-Commerce Solutions Unit'),
('Nakawa HQ','Wood Technology and Carpentry Unit','Furniture Design Unit'),
('Nakawa HQ','Wood Technology and Carpentry Unit','Wood Finishing and Treatment'),
('Nakawa HQ','Wood Technology and Carpentry Unit','Wood Processing and Furniture Production Unit'),

-- Namanve: 42 sections/units under 16 parent departments/directorates
('Namanve','CNC Milling and Drilling Section','CNC Milling Operations'),
('Namanve','CNC Milling and Drilling Section','Precision CNC Milling and Drilling Unit'),
('Namanve','CNC Milling and Drilling Section','Tool Design and Maintenance'),
('Namanve','Computer-Aided Design and Manufacture Lab','CAD Design Unit'),
('Namanve','Computer-Aided Design and Manufacture Lab','CAD/CAM Design and Manufacturing Unit'),
('Namanve','Computer-Aided Design and Manufacture Lab','CAM and CNC Programming'),
('Namanve','Conventional Machining and Lathe Operations Section','Lathe Operations Unit'),
('Namanve','Conventional Machining and Lathe Operations Section','Milling Operations Unit'),
('Namanve','Conventional Machining and Lathe Operations Section','Traditional Machining and Lathe Operations Unit'),
('Namanve','Curriculum Development and Training Unit','Curriculum Development'),
('Namanve','Curriculum Development and Training Unit','Curriculum Development and Instructor Training Unit'),
('Namanve','Curriculum Development and Training Unit','Instructor Professional Development'),
('Namanve','Engineering Stores and Warehouse (Namanve)','Bulk Raw Materials and Workshop Issue Coordination Unit'),
('Namanve','Facilities and Admin Support Unit (Namanve)','Local Administration, Safety Gear and PPE Stores Unit'),
('Namanve','Heavy-Industry Technical Vocational Skilling Centre','Heavy Equipment Training'),
('Namanve','Heavy-Industry Technical Vocational Skilling Centre','Heavy Industry Practical Workshops'),
('Namanve','Heavy-Industry Technical Vocational Skilling Centre','Skills Certification Unit'),
('Namanve','ICT Infrastructure and Technical Support (Namanve)','CNC Network and CAD/CAM Software Licensing Unit'),
('Namanve','Industrial Foundry and Metal Casting Section','Finishing and Inspection'),
('Namanve','Industrial Foundry and Metal Casting Section','Metal Melting and Casting'),
('Namanve','Industrial Foundry and Metal Casting Section','Metallurgy and Metal Casting Workshop'),
('Namanve','Industrial Plant Maintenance and Repair Hub','Corrective Repairs Unit'),
('Namanve','Industrial Plant Maintenance and Repair Hub','Mechanical and Plant Equipment Repair Hub'),
('Namanve','Industrial Plant Maintenance and Repair Hub','Preventive Maintenance Unit'),
('Namanve','Industrial Robotics and Automation Section','Automation Systems Design'),
('Namanve','Industrial Robotics and Automation Section','Robot Programming and Mechatronics Lab'),
('Namanve','Industrial Robotics and Automation Section','Robot Programming Unit'),
('Namanve','Mechanical Assembly and Tooling Area','Assembly Operations and Tool Design Area'),
('Namanve','Mechanical Assembly and Tooling Area','Assembly Operations Unit'),
('Namanve','Mechanical Assembly and Tooling Area','Tool Design Unit'),
('Namanve','Pneumatics and Hydraulics Systems Unit','Fluid Power Control Workshop'),
('Namanve','Pneumatics and Hydraulics Systems Unit','Hydraulic Systems Unit'),
('Namanve','Pneumatics and Hydraulics Systems Unit','Pneumatic Systems Unit'),
('Namanve','Precision Parts Fabrication Shop','Precision Components Manufacturing Shop'),
('Namanve','Precision Parts Fabrication Shop','Precision Machining'),
('Namanve','Precision Parts Fabrication Shop','Quality Control and Measurement'),
('Namanve','Programmable Logic Controllers Laboratory','Industrial Control Systems'),
('Namanve','Programmable Logic Controllers Laboratory','PLC Programming Unit'),
('Namanve','Programmable Logic Controllers Laboratory','PLC Systems and Industrial Automation Controls Unit'),
('Namanve','Systems Integration and Technical Advisory Unit','Systems Design Unit'),
('Namanve','Systems Integration and Technical Advisory Unit','Technical Advisory Services'),
('Namanve','Systems Integration and Technical Advisory Unit','Technical Consulting and Advisory Unit');

INSERT INTO departments (branch_id, name)
SELECT DISTINCT b.id, ps.department_name
FROM pdf_structure ps
JOIN branches b ON b.name = ps.branch_name
WHERE NOT EXISTS (
    SELECT 1 FROM departments d
    WHERE d.branch_id = b.id AND d.name = ps.department_name
);

INSERT INTO sections (department_id, name)
SELECT d.id, ps.section_name
FROM pdf_structure ps
JOIN branches b ON b.name = ps.branch_name
JOIN departments d ON d.branch_id = b.id AND d.name = ps.department_name
WHERE NOT EXISTS (
    SELECT 1 FROM sections s
    WHERE s.department_id = d.id AND s.name = ps.section_name
);

DROP TEMPORARY TABLE pdf_structure;
COMMIT;
