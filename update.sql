CREATE TABLE IF NOT EXISTS `property_request` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
  `date` date NOT NULL,
  PRIMARY KEY (`request_id`),
  -- This ensures a tenant doesn't spam the owner with multiple requests for 1 house
  UNIQUE KEY `one_app_per_tenant` (`tenant_id`, `property_id`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`tenant_id`) ON DELETE CASCADE,
  FOREIGN KEY (`property_id`) REFERENCES `property` (`property_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;