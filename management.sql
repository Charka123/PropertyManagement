-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 17, 2026 at 11:16 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS property_management;
USE property_management;

CREATE USER IF NOT EXISTS 'app_user'@'localhost' IDENTIFIED BY 'pmanagement';
GRANT SELECT, INSERT, UPDATE, DELETE ON property_management.* TO 'app_user'@'localhost';
FLUSH PRIVILEGES; 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `property_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `apply`
--

CREATE TABLE `apply` (
  `worker_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `status` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `apply`
--

INSERT INTO `apply` (`worker_id`, `request_id`, `status`) VALUES
(1, 1, 'Approved'),
(2, 2, 'Submitted');

-- --------------------------------------------------------

--
-- Table structure for table `assigned`
--

CREATE TABLE `assigned` (
  `worker_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assigned`
--

INSERT INTO `assigned` (`worker_id`, `request_id`) VALUES
(1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `lives`
--

CREATE TABLE `lives` (
  `tenant_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lives`
--

INSERT INTO `lives` (`tenant_id`, `property_id`) VALUES
(1, 1),
(2, 6),
(3, 3),
(4, 4),
(5, 5),
(6, 2),
(7, 7);

-- --------------------------------------------------------

--
-- Table structure for table `owns`
--

CREATE TABLE `owns` (
  `owner_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `owns`
--

INSERT INTO `owns` (`owner_id`, `property_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(2, 5),
(2, 6),
(2, 7);

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `payment_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `amount` decimal(10,0) NOT NULL,
  `number` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`payment_id`, `request_id`, `date`, `amount`, `number`) VALUES
(1, 1, '2026-03-17', 100, '1');

-- --------------------------------------------------------

--
-- Table structure for table `property`
--

CREATE TABLE `property` (
  `property_id` int(11) NOT NULL,
  `street` varchar(100) NOT NULL,
  `apartment` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property`
--

INSERT INTO `property` (`property_id`, `street`, `apartment`) VALUES
(1, '1521 Virginia Ave, Charlottesville, VA, 22903', '4'),
(2, '2509 Plateau Rd, Charlottesville, VA, 22903', NULL),
(3, '6207 Hibbling Ave, Springfield, VA, 22150', NULL),
(4, '7454 Zanuck Ct, Annandale, VA, 22003', NULL),
(5, '1 Lefevre House, Charlottesville, VA, 22907', '201'),
(6, '180 McCormick Rd, Charlottesville, VA, 22903', NULL),
(7, '1819 Jefferson Park Ave, Charlottesville, VA, 22903', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `property_owner`
--

CREATE TABLE `property_owner` (
  `owner_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_owner`
--

INSERT INTO `property_owner` (`owner_id`, `name`, `email`, `password`) VALUES
(1, 'Ayael Eslaquit', 'email@gmail.com', 'papichampu'),
(2, 'Hamdael Eslaquit', 'spain@gmail.com', 'plscomeback');

-- --------------------------------------------------------

--
-- Table structure for table `request`
--

CREATE TABLE `request` (
  `request_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `urgency` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `status` varchar(30) NOT NULL,
  `date` date NOT NULL,
  `number` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `request`
--

INSERT INTO `request` (`request_id`, `property_id`, `tenant_id`, `type`, `urgency`, `description`, `status`, `date`, `number`) VALUES
(1, 1, 1, 'Maintenance', 'Red', 'some description', 'Pending', '2026-03-17', 1),
(2, 6, 2, 'Orange', 'Urgent', 'description', 'In Progress', '2026-03-17', 2);

-- --------------------------------------------------------

--
-- Table structure for table `tenant`
--

CREATE TABLE `tenant` (
  `tenant_id` int(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenant`
--

INSERT INTO `tenant` (`tenant_id`, `name`, `email`, `password`) VALUES
(1, 'Andrew Zurita', 'jxg8fm@virignia.edu', 'bruh'),
(2, 'Sagar Dwivedy', 'email@email.com', 'bruh2'),
(3, 'Oshil Ghimere', 'johndoe@gmail.com', 'bruh3'),
(4, 'Hanzhe Dong', 'randomemail@gmail.com', 'bruh4'),
(5, 'Cesar Martinez', 'bobobobo@gmail.com', 'diablo'),
(6, 'Christopher Ventura', 'tuknuk@gmail.com', 'piton'),
(7, 'Roy Alcala', 'roycs@gmail.com', 'el_hueso');

-- --------------------------------------------------------

--
-- Table structure for table `workers`
--

CREATE TABLE `workers` (
  `worker_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password` varchar(200) NOT NULL,
  `specialization` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workers`
--

INSERT INTO `workers` (`worker_id`, `name`, `email`, `password`, `specialization`) VALUES
(1, 'worker 1', 'worker1@gmail.com', 'woker1password', 'Plumbing'),
(2, 'worker 2', 'worker2@gmail.com', 'worker2password', 'Electrician'),
(3, 'worker 3', 'worker3@gmail.com', 'worker3password', 'Carpentry'),
(4, 'worker 4', 'worker4@gmail.com', 'worker4password', 'Carpentry');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `apply`
--
ALTER TABLE `apply`
  ADD PRIMARY KEY (`worker_id`,`request_id`),
  ADD KEY `fk_apply_request` (`request_id`);

--
-- Indexes for table `assigned`
--
ALTER TABLE `assigned`
  ADD PRIMARY KEY (`worker_id`,`request_id`),
  ADD KEY `fk_assigned_request` (`request_id`);

--
-- Indexes for table `lives`
--
ALTER TABLE `lives`
  ADD PRIMARY KEY (`tenant_id`,`property_id`),
  ADD KEY `fk_lives_property_id` (`property_id`);

--
-- Indexes for table `owns`
--
ALTER TABLE `owns`
  ADD PRIMARY KEY (`owner_id`,`property_id`),
  ADD KEY `fk_property` (`property_id`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_payment_request` (`request_id`);

--
-- Indexes for table `property`
--
ALTER TABLE `property`
  ADD PRIMARY KEY (`property_id`);

--
-- Indexes for table `property_owner`
--
ALTER TABLE `property_owner`
  ADD PRIMARY KEY (`owner_id`);

--
-- Indexes for table `request`
--
ALTER TABLE `request`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_property_id` (`property_id`),
  ADD KEY `fk_tenant_id` (`tenant_id`);

--
-- Indexes for table `tenant`
--
ALTER TABLE `tenant`
  ADD PRIMARY KEY (`tenant_id`);

--
-- Indexes for table `workers`
--
ALTER TABLE `workers`
  ADD PRIMARY KEY (`worker_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `property`
--
ALTER TABLE `property`
  MODIFY `property_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `property_owner`
--
ALTER TABLE `property_owner`
  MODIFY `owner_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `request`
--
ALTER TABLE `request`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tenant`
--
ALTER TABLE `tenant`
  MODIFY `tenant_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `workers`
--
ALTER TABLE `workers`
  MODIFY `worker_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `apply`
--
ALTER TABLE `apply`
  ADD CONSTRAINT `fk_apply_request` FOREIGN KEY (`request_id`) REFERENCES `request` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_apply_worker` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`worker_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `assigned`
--
ALTER TABLE `assigned`
  ADD CONSTRAINT `fk_assigned_request` FOREIGN KEY (`request_id`) REFERENCES `request` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assigned_worker` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`worker_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lives`
--
ALTER TABLE `lives`
  ADD CONSTRAINT `fk_lives_property_id` FOREIGN KEY (`property_id`) REFERENCES `property` (`property_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `owns`
--
ALTER TABLE `owns`
  ADD CONSTRAINT `fk_owner_id` FOREIGN KEY (`owner_id`) REFERENCES `property_owner` (`owner_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_property` FOREIGN KEY (`property_id`) REFERENCES `property` (`property_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `fk_payment_request` FOREIGN KEY (`request_id`) REFERENCES `request` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `request`
--
ALTER TABLE `request`
  ADD CONSTRAINT `fk_property_id` FOREIGN KEY (`property_id`) REFERENCES `property` (`property_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

ALTER TABLE request
ADD CONSTRAINT chk_request_status
CHECK (status IN ('Pending', 'In Progress', 'Completed', 'Cancelled'));

ALTER TABLE apply
ADD CONSTRAINT chk_apply_status
CHECK (status IN ('Submitted', 'Approved', 'Rejected'));

DELIMITER //

CREATE PROCEDURE GetRequestsByProperty(IN p_property_id INT)
BEGIN
   SELECT request_id, property_id, tenant_id, type, urgency, description, status, date, number
   FROM request
   WHERE property_id = p_property_id;
END //

DELIMITER ;

DELIMITER //

CREATE TRIGGER prevent_multiple_properties_per_tenant
BEFORE INSERT ON lives
FOR EACH ROW
BEGIN
   IF EXISTS (
       SELECT 1
       FROM lives
       WHERE tenant_id = NEW.tenant_id
   ) THEN
       SIGNAL SQLSTATE '45000'
       SET MESSAGE_TEXT = 'Tenant already lives in a property';
   END IF;
END //

DELIMITER ;