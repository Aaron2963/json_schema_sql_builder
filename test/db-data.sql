--
-- Database: `test`
--

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL PRIMARY KEY,
  `name` varchar(255) DEFAULT NULL,
  `type_id` int(11) DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `weight_unit` varchar(50) DEFAULT NULL,
  `keywords` varchar(255) NOT NULL,
  `price` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `type_id`, `weight`, `weight_unit`, `keywords`, `price`) VALUES
(1, 'Apple', 1, 100.00, 'g', 'fruit,apple', 100),
(2, 'Orange', 1, 2.5, 'kg', 'fruit,orange', 2000);

-- --------------------------------------------------------

--
-- Table structure for table `product_types`
--

CREATE TABLE `product_types` (
  `id` int(11) NOT NULL PRIMARY KEY,
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `product_types`
--

INSERT INTO `product_types` (`id`, `name`) VALUES
(1, 'Fruit');