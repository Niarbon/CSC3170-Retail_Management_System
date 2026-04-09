-- Employee System.sql
USE csc3170_store;

-- 1. Employee onboarding
INSERT INTO employee (
    employee_name,
    salary,
    job_position,
    phone_number,
    hire_date,
    is_active
) VALUES
    ('Alice Zhang', 6500.00, 'Cashier', '13800000001', '2026-04-01', 1);

-- Generic reusable onboarding template:
-- INSERT INTO employee (employee_name, salary, job_position, phone_number, hire_date, is_active)
-- VALUES (?, ?, ?, ?, ?, 1);


-- 2. Logical resignation / internal transfer
-- Requirement says: logical leave should use UPDATE job position instead of DELETE.
UPDATE employee
SET job_position = 'Resigned',
    is_active = 0,
    leave_date = CURRENT_DATE,
    updated_at = CURRENT_TIMESTAMP
WHERE employee_id = 1
  AND is_active = 1;

-- Alternative: transfer instead of leaving
-- UPDATE employee
-- SET job_position = 'Inventory Clerk',
--     updated_at = CURRENT_TIMESTAMP
-- WHERE employee_id = ?;


-- 3. Salary distribution analysis by job position
SELECT
    job_position,
    COUNT(*) AS employee_count,
    MIN(salary) AS min_salary,
    MAX(salary) AS max_salary,
    ROUND(AVG(salary), 2) AS avg_salary,
    ROUND(SUM(salary), 2) AS total_salary
FROM employee
GROUP BY job_position
ORDER BY avg_salary DESC, job_position ASC;


-- 4. Active employee roster
SELECT
    employee_id,
    employee_name,
    job_position,
    salary,
    phone_number,
    hire_date
FROM employee
WHERE is_active = 1
ORDER BY hire_date DESC, employee_id DESC;


-- 5. Resigned employee roster
SELECT
    employee_id,
    employee_name,
    phone_number,
    hire_date,
    leave_date,
    job_position
FROM employee
WHERE is_active = 0
ORDER BY leave_date DESC, employee_id DESC;
