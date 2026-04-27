-- 1. Fix the 'apply' table
ALTER TABLE `apply` 
DROP FOREIGN KEY `fk_apply_request`;

ALTER TABLE `apply` 
ADD CONSTRAINT `fk_apply_request` 
FOREIGN KEY (`request_id`) REFERENCES `request` (`request_id`) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- 2. Fix the 'assigned' table
ALTER TABLE `assigned` 
DROP FOREIGN KEY `fk_assigned_request`;

ALTER TABLE `assigned` 
ADD CONSTRAINT `fk_assigned_request` 
FOREIGN KEY (`request_id`) REFERENCES `request` (`request_id`) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- 3. Fix the 'payment' table
ALTER TABLE `payment` 
DROP FOREIGN KEY `fk_payment_request`;

ALTER TABLE `payment` 
ADD CONSTRAINT `fk_payment_request` 
FOREIGN KEY (`request_id`) REFERENCES `request` (`request_id`) 
ON DELETE CASCADE ON UPDATE CASCADE;