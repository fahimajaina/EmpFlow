-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 29, 2025 at 08:00 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `empflow`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `UserName` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `updationDate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `UserName`, `Password`, `updationDate`) VALUES
(1, 'admin', '$2y$10$0EV4sc9E.oKX3Z8ud391ye0wfIOO94im0.MNtk3jXzv2Mr1MQDjI6', '2025-10-18 12:35:10');

-- --------------------------------------------------------

--
-- Table structure for table `tblattendance`
--

CREATE TABLE `tblattendance` (
  `id` int(11) NOT NULL,
  `empid` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `work_hours` varchar(50) DEFAULT NULL,
  `status` enum('Present','Absent','Late') DEFAULT 'Absent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbldepartments`
--

CREATE TABLE `tbldepartments` (
  `id` int(11) NOT NULL,
  `DepartmentName` varchar(150) NOT NULL,
  `DepartmentShortName` varchar(100) NOT NULL,
  `DepartmentCode` varchar(50) NOT NULL,
  `CreationDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbldepartments`
--

INSERT INTO `tbldepartments` (`id`, `DepartmentName`, `DepartmentShortName`, `DepartmentCode`, `CreationDate`) VALUES
(2, 'Comp sci', 'CS', 'CS01', '2025-08-29 14:41:16'),
(3, 'Human Resource', 'HR', 'HR001', '2025-08-29 14:51:03'),
(5, 'ret', 'qw', '12w', '2025-08-31 07:14:45'),
(6, 'hbdhdb', 'asbsbd', '55443', '2025-11-15 15:48:50');

-- --------------------------------------------------------

--
-- Table structure for table `tblemployees`
--

CREATE TABLE `tblemployees` (
  `id` int(11) NOT NULL,
  `EmpId` varchar(100) NOT NULL,
  `FirstName` varchar(150) NOT NULL,
  `LastName` varchar(150) NOT NULL,
  `EmailId` varchar(200) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Gender` enum('Male','Female','Other') NOT NULL,
  `Dob` date NOT NULL,
  `Department` int(11) NOT NULL,
  `Address` varchar(255) NOT NULL,
  `City` varchar(200) NOT NULL,
  `Country` varchar(150) NOT NULL,
  `Phonenumber` varchar(15) NOT NULL,
  `Status` tinyint(1) NOT NULL DEFAULT 1,
  `RegDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblemployees`
--

INSERT INTO `tblemployees` (`id`, `EmpId`, `FirstName`, `LastName`, `EmailId`, `Password`, `Gender`, `Dob`, `Department`, `Address`, `City`, `Country`, `Phonenumber`, `Status`, `RegDate`) VALUES
(3, 'EMP250001', 'sathee', 'Akhter', 'sathee@gmail.com', '$2y$10$NKo8MYGh4ZqlSWHh2r0M9eyUT6xlQkT4D5.F3yNMTkKrcV/PxE2VK', 'Female', '2003-05-28', 2, 'tajpur', 'sylhet', 'bangladesh', '01777777777', 1, '2025-10-16 04:20:05'),
(4, 'EMP250002', 'fahima', 'jaina', 'fahima@gmail.com', '$2y$10$U3vmTbmEudNk.IS/5x5cQujZGqfTQcG9Q5gpmFkCR7oAwZ3aIw45G', 'Female', '2002-11-26', 2, 'hetimgonj', 'sylhet', 'bangladesh', '01765921728', 1, '2025-10-25 05:27:55'),
(5, 'EMP250003', 'majid', 'zaman', 'majid@gmail.com', '$2y$10$KG.chlSNHOuSX5qhjMhWF..UsHHGAi.hmRoIXOYEDyT9NMcKi.3be', 'Female', '2002-12-29', 3, 'hetimgonj', 'sylhet', 'Bangladesh', '01756666667', 1, '2025-11-02 06:40:55'),
(6, 'EMP250004', 'arshad', 'zaman', 'arshad@gmail.com', '$2y$10$xCQRB8yzvXvkMmqegmzYE.cFG7AC3rt0IVW.zXP.UO49QOA7D89nG', 'Male', '2002-06-13', 6, 'jdjdjdjhhs', 'sylhet', 'habdhjbdhjwbd', '01876549282', 1, '2025-11-15 15:50:32');

-- --------------------------------------------------------

--
-- Table structure for table `tblleaves`
--

CREATE TABLE `tblleaves` (
  `id` int(11) NOT NULL,
  `empid` int(11) NOT NULL,
  `LeaveTypeID` int(11) NOT NULL,
  `FromDate` date NOT NULL,
  `ToDate` date NOT NULL,
  `Description` mediumtext NOT NULL,
  `PostingDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `AdminRemark` mediumtext DEFAULT NULL,
  `AdminRemarkDate` timestamp NULL DEFAULT NULL,
  `Status` tinyint(1) DEFAULT 0,
  `IsRead` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblleaves`
--

INSERT INTO `tblleaves` (`id`, `empid`, `LeaveTypeID`, `FromDate`, `ToDate`, `Description`, `PostingDate`, `AdminRemark`, `AdminRemarkDate`, `Status`, `IsRead`) VALUES
(1, 4, 3, '2025-10-25', '2025-10-31', 'i have a fever', '2025-10-25 05:33:35', 'hgfcdre', '2025-11-15 15:57:10', 1, 0),
(2, 5, 5, '2025-11-15', '2025-11-20', 'hsbdhsdhs', '2025-11-15 16:02:13', NULL, NULL, 0, 0),
(3, 6, 4, '2025-11-16', '2025-11-27', 'yegyeg', '2025-11-16 05:42:02', 'fhfhjsjbdsj', '2025-11-29 17:09:48', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tblleavetype`
--

CREATE TABLE `tblleavetype` (
  `id` int(11) NOT NULL,
  `LeaveType` varchar(200) NOT NULL,
  `Description` mediumtext NOT NULL,
  `max` int(11) NOT NULL,
  `CreationDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblleavetype`
--

INSERT INTO `tblleavetype` (`id`, `LeaveType`, `Description`, `max`, `CreationDate`) VALUES
(3, 'Sick leave', 'if you are too sick to walk', 7, '2025-10-25 05:32:27'),
(4, 'Casual leave', 'can take for a few times', 5, '2025-10-25 05:32:52'),
(5, 'hbdhdb', 'whebdheb', 8, '2025-11-15 15:49:07');

-- --------------------------------------------------------

--
-- Table structure for table `tblreset_tokens`
--

CREATE TABLE `tblreset_tokens` (
  `id` int(11) NOT NULL,
  `emp_id` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UserName` (`UserName`);

--
-- Indexes for table `tblattendance`
--
ALTER TABLE `tblattendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_date` (`empid`,`attendance_date`);

--
-- Indexes for table `tbldepartments`
--
ALTER TABLE `tbldepartments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `DepartmentCode` (`DepartmentCode`),
  ADD UNIQUE KEY `DepartmentName` (`DepartmentName`),
  ADD UNIQUE KEY `DepartmentShortName` (`DepartmentShortName`);

--
-- Indexes for table `tblemployees`
--
ALTER TABLE `tblemployees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `EmpId` (`EmpId`),
  ADD UNIQUE KEY `EmailId` (`EmailId`),
  ADD KEY `Department` (`Department`);

--
-- Indexes for table `tblleaves`
--
ALTER TABLE `tblleaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `empid` (`empid`),
  ADD KEY `LeaveTypeID` (`LeaveTypeID`);

--
-- Indexes for table `tblleavetype`
--
ALTER TABLE `tblleavetype`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblreset_tokens`
--
ALTER TABLE `tblreset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tblattendance`
--
ALTER TABLE `tblattendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbldepartments`
--
ALTER TABLE `tbldepartments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tblemployees`
--
ALTER TABLE `tblemployees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tblleaves`
--
ALTER TABLE `tblleaves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tblleavetype`
--
ALTER TABLE `tblleavetype`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tblreset_tokens`
--
ALTER TABLE `tblreset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tblattendance`
--
ALTER TABLE `tblattendance`
  ADD CONSTRAINT `tblattendance_ibfk_1` FOREIGN KEY (`empid`) REFERENCES `tblemployees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tblemployees`
--
ALTER TABLE `tblemployees`
  ADD CONSTRAINT `tblemployees_ibfk_1` FOREIGN KEY (`Department`) REFERENCES `tbldepartments` (`id`);

--
-- Constraints for table `tblleaves`
--
ALTER TABLE `tblleaves`
  ADD CONSTRAINT `tblleaves_ibfk_1` FOREIGN KEY (`empid`) REFERENCES `tblemployees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tblleaves_ibfk_2` FOREIGN KEY (`LeaveTypeID`) REFERENCES `tblleavetype` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tblreset_tokens`
--
ALTER TABLE `tblreset_tokens`
  ADD CONSTRAINT `tblreset_tokens_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `tblemployees` (`EmpId`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
